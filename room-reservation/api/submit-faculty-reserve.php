<?php

/**
 * room-reservation/api/submit-faculty-reserve.php
 * Faculty submits their own room reservation.
 * Called via fetch() from faculty-dashboard.php.
 *
 * Depth: 2  →  config prefix: __DIR__ . '/../../config/…'
 *
 * Accepts EITHER
 *   • JSON body              (Content-Type: application/json), or
 *   • multipart/form-data    (FormData — needed when a document is attached)
 *
 * Fields:
 *   room_id, reservation_date, start_time, end_time,
 *   purpose, attendees, notes (optional), csrf_token
 *   document (optional file, multipart only)
 *
 * Rules (see core/faculty-room-quota.php):
 *   • Equipment never blocks a room — overdue / outstanding equipment is fine.
 *   • A faculty account may hold at most 2 active room reservations at once.
 *   • A request letter (JPG, PNG, PDF; 5 MB) may be attached to any reservation, same-day
 *     or later. It is optional (unless Settings require one for a LATER date) and is stored
 *     in document_path for the reviewing admin. A same-day reservation never needs one and
 *     is still decided on availability alone.
 *   • Every reservation from this endpoint is filed as 'personal'.
 *   • SAME-DAY requests get a 10-minute review buffer (core/room-buffer.php): saved as Waiting so an
 *     admin can approve/decline at once; if nobody acts, the system approves it when the buffer ends
 *     (no conflicts) or declines it (any overlap or assigned class). Overlapping an existing
 *     reservation or pending request is refused up front: first come, first served.
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/room-quota.php';
require_once __DIR__ . '/../core/room-buffer.php';   // 10-minute review buffer for same-day requests

// ── Read the body (multipart or JSON) ─────────────────────────────────────
$is_multipart = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === 0;

if ($is_multipart) {
    // A file bigger than post_max_size makes PHP drop $_POST and $_FILES entirely.
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        echo json_encode(['error' => 'That file is too large. Please attach a file under 5 MB.']);
        exit();
    }
    $body = $_POST;
} else {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        // Not JSON: accept a plain form-encoded body too.
        $body = !empty($_POST) ? $_POST : [];
    }
}

// CSRF verification
$token = $body['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid request token. Please refresh and try again.']);
    exit();
}

$faculty_id   = $_SESSION['faculty_id'];
$faculty_name = $_SESSION['faculty_name'];
$room_id      = intval($body['room_id']          ?? 0);
$res_date     = trim($body['reservation_date']   ?? '');
$start_time   = trim($body['start_time']         ?? '');
$end_time     = trim($body['end_time']           ?? '');
$purpose      = trim($body['purpose']            ?? '');
$attendees    = max(1, intval($body['attendees'] ?? 1));
$notes        = trim($body['notes']              ?? '');

// ── Input validation ──────────────────────────────────────────────────────
if (!$room_id || !$res_date || !$start_time || !$end_time || !$purpose) {
    echo json_encode(['error' => 'All required fields must be filled in.']);
    exit();
}
// Past datetime check — compare full date+time in Asia/Manila timezone
$now_manila   = new DateTime('now', new DateTimeZone('Asia/Manila'));
$req_datetime = DateTime::createFromFormat(
    'Y-m-d H:i',
    $res_date . ' ' . $start_time,
    new DateTimeZone('Asia/Manila')
);
if ($req_datetime === false || $req_datetime <= $now_manila) {
    echo json_encode(['error' => 'Reservation date and time cannot be in the past.']);
    exit();
}
if ($end_time <= $start_time) {
    echo json_encode(['error' => 'End time must be after start time.']);
    exit();
}
$conn = getDB();
RoomRules::load($conn);   // Settings → Arbitration → Reservation rules

// ── Room schedule rules (Settings → Arbitration → Room schedule): open days, hours, longest reservation ──
foreach ([RoomRules::closedDayError($res_date), RoomRules::hoursError($start_time, $end_time), RoomRules::lengthError($start_time, $end_time)] as $schedule_err) {
    if ($schedule_err !== null) {
        echo json_encode(['error' => $schedule_err]);
        exit();
    }
}

// ── How far ahead a room can be reserved ────────────────────────────────────
$advance_err = RoomRules::advanceError($res_date);
if ($advance_err !== null) {
    echo json_encode(['error' => $advance_err]);
    exit();
}

// ── Verify room exists and is available ──────────────────────────────────
$room_chk = $conn->prepare(
    "SELECT room_name FROM tbl_rooms
      WHERE room_id = ? AND is_archived = 0 AND status = 'Available'
      LIMIT 1"
);
$room_chk->bind_param('i', $room_id);
$room_chk->execute();
$room_row = $room_chk->get_result()->fetch_assoc();
$room_chk->close();

if (!$room_row) {
    echo json_encode(['error' => 'This room is no longer available for booking.']);
    exit();
}
$room_name = $room_row['room_name'];

// A reservation for a LATER DATE waits for an admin's decision and may carry a request letter;
// a same-day one is decided on availability alone and needs no letter.
$is_future_day = ps_room_is_future_date($res_date);

// ── Optional request letter (any date) ───────────────────────────────────
// Validated first so a bad file is rejected before anything is saved.
$doc_tmp  = null;   // temp path of a validated upload
$doc_ext  = '';
if ($is_multipart && isset($_FILES['document']) && $_FILES['document']['error'] !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['document'];

    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        echo json_encode(['error' => 'That file is too large. Please attach a file under 5 MB.']);
        exit();
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        echo json_encode(['error' => 'The file could not be uploaded. Please try again.']);
        exit();
    }
    if ($f['size'] > FCTY_ROOM_DOC_MAX_BYTES) {
        echo json_encode(['error' => 'That file is too large. Please attach a file under 5 MB.']);
        exit();
    }

    // Trust the file's real bytes, never the client's name or declared type.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $f['tmp_name']);
    finfo_close($finfo);
    $ext_by_mime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    if (!isset($ext_by_mime[$mime])) {
        echo json_encode(['error' => 'Unsupported file type. Please attach a JPG, PNG or PDF.']);
        exit();
    }
    if ($mime === 'application/pdf') {
        $head = (string)file_get_contents($f['tmp_name'], false, null, 0, 5);
        $valid = ($head === '%PDF-');
    } else {
        $valid = (@getimagesize($f['tmp_name']) !== false);
    }
    if (!$valid) {
        echo json_encode(['error' => 'That file looks damaged or is not a real image/PDF.']);
        exit();
    }
    $doc_tmp = $f['tmp_name'];
    $doc_ext = $ext_by_mime[$mime];
}

// ── "Require a request letter" (Settings → Arbitration): a later-date reservation needs one ──
if ($is_future_day && RoomRules::letterRequired() && $doc_tmp === null) {
    echo json_encode(['error' => 'A request letter is required for later-date reservations. Please attach one.']);
    exit();
}

// ── Room limit: at most N active reservations per faculty account ─────────
// Serialised per faculty so two quick submissions cannot both pass the check.
fcty_quota_lock($conn, $faculty_id);

$active_now = fcty_active_room_reservations($conn, $faculty_id);
if (count($active_now) >= RoomRules::maxActive()) {
    fcty_quota_unlock($conn, $faculty_id);
    echo json_encode([
        'error'         => fcty_quota_message(),
        'limit_reached' => true,
        'active'        => count($active_now),
        'max'           => RoomRules::maxActive(),
    ]);
    exit();
}

// ── Same-day buffer: refuse an overlap up front (first come, first served) ──
// Any Approved reservation or Waiting request for this room that overlaps even slightly blocks it.
$buffered = ps_room_is_buffered_same_day($res_date);
if ($buffered) {
    $overlap_err = ps_room_overlap_error($conn, $room_id, $res_date, $start_time, $end_time);
    if ($overlap_err !== null) {
        fcty_quota_unlock($conn, $faculty_id);
        echo json_encode(['error' => $overlap_err]);
        exit();
    }
}

// ── Store the document (after the limit check, before the insert) ─────────
$document_path = null;
$submitted_as  = 'personal';
$saved_abs     = null;

if ($doc_tmp !== null) {
    $dir_abs = __DIR__ . '/../../uploads/room_documents/';
    if (!is_dir($dir_abs) && !@mkdir($dir_abs, 0755, true)) {
        fcty_quota_unlock($conn, $faculty_id);
        error_log('[PUPSync] submit-faculty-reserve: cannot create ' . $dir_abs);
        echo json_encode(['error' => 'Could not save the document. Please try again.']);
        exit();
    }
    // Block script execution in this folder even if something odd lands here.
    $htaccess = $dir_abs . '.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents(
            $htaccess,
            "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|php[0-9])$\">\n    Require all denied\n</FilesMatch>\n"
        );
    }

    $safe_id   = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$faculty_id);
    $dest_name = date('Ymd_His') . '_' . $safe_id . '_' . bin2hex(random_bytes(4)) . '.' . $doc_ext;
    $saved_abs = $dir_abs . $dest_name;

    if (!move_uploaded_file($doc_tmp, $saved_abs)) {
        fcty_quota_unlock($conn, $faculty_id);
        error_log('[PUPSync] submit-faculty-reserve: move_uploaded_file failed for ' . $dest_name);
        echo json_encode(['error' => 'Could not save the document. Please try again.']);
        exit();
    }
    $document_path = 'uploads/room_documents/' . $dest_name;
}

// ── Insert reservation ────────────────────────────────────────────────────
$notes_val = $notes !== '' ? $notes : null;
// A reservation for a LATER DATE always waits for an admin's decision, with or without a letter.
// A same-day one is decided on the spot, unless the Super Admin turned "same-day confirmation" off
// (then it waits like a later-date one). See core/room-expiry.php for the deadline rule.
$hold_for_review = RoomRules::holdsForReview($res_date) || $buffered;   // validated by the engine, never approved on the spot
$initial_status  = $hold_for_review ? 'Waiting' : 'Approved';

// The legacy schema used an ENUM without Waiting even though the application
// already recognises pending room reservations. Add the value once when a
// later-date request is first submitted.
if ($initial_status === 'Waiting') {
    $status_col = $conn->query("SHOW COLUMNS FROM tbl_room_reservations LIKE 'status'");
    $status_def = $status_col ? (string)(($status_col->fetch_assoc()['Type'] ?? '')) : '';
    if (stripos($status_def, "'waiting'") === false) {
        $conn->query("ALTER TABLE tbl_room_reservations MODIFY status ENUM('Waiting','Approved','Declined','Cancelled') NOT NULL DEFAULT 'Approved'");
    }
}
$ins = $conn->prepare(
    "INSERT INTO tbl_room_reservations
        (room_id, faculty_id, faculty_name, submitted_as,
         purpose, attendees, reservation_date, start_time, end_time,
         notes, document_path, status, request_date)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
);
$ins->bind_param(
    'issssissssss',
    $room_id,
    $faculty_id,
    $faculty_name,
    $submitted_as,
    $purpose,
    $attendees,
    $res_date,
    $start_time,
    $end_time,
    $notes_val,
    $document_path,
    $initial_status
);

if (!$ins->execute()) {
    error_log('[PUPSync] submit-faculty-reserve insert failed: ' . $conn->error);
    $ins->close();
    if ($saved_abs !== null) {
        @unlink($saved_abs);   // don't leave an orphan file behind
    }
    fcty_quota_unlock($conn, $faculty_id);
    echo json_encode(['error' => 'Failed to save reservation. Please try again.']);
    exit();
}
$reservation_id = $conn->insert_id;
$ins->close();

// ── Run Arbitration Engine ────────────────────────────────────────────────
// Same-day: decided on the spot. Later date: validated now, then held for an admin.
require_once __DIR__ . '/../../equipment-booking/core/arbitration-engine.php';
ArbitrationEngine::processRoomReservation($conn, $reservation_id, $hold_for_review);

// ── A declined reservation is never kept: tell the user on the spot ───────
$declined_reason = ArbitrationEngine::discardRoomIfDeclined($conn, $reservation_id);
if ($declined_reason !== null) {
    fcty_quota_unlock($conn, $faculty_id);
    echo json_encode(['error' => $declined_reason]);
    exit();
}

// ── Read final status ─────────────────────────────────────────────────────
$status_stmt = $conn->prepare(
    "SELECT status, reason FROM tbl_room_reservations WHERE id = ? LIMIT 1"
);
$status_stmt->bind_param('i', $reservation_id);
$status_stmt->execute();
$status_row = $status_stmt->get_result()->fetch_assoc();
$status_stmt->close();
$final_status = $status_row['status'] ?? 'Approved';
$final_reason = $status_row['reason'] ?? '';

// Same-day request that passed every check: start its review buffer (admin can act; otherwise the
// system decides when it ends).
$buffer_info = [];
if ($buffered && $final_status === 'Waiting') {
    $buffer_deadline = ps_room_buffer_deadline($res_date, $start_time);
    ps_room_buffer_mark($conn, $reservation_id, $buffer_deadline);
    $buffer_info = ps_room_buffer_payload($buffer_deadline);
}

fcty_quota_unlock($conn, $faculty_id);

echo json_encode($buffer_info + [
    'success'        => true,
    'reservation_id' => $reservation_id,
    'status'         => $final_status,
    'reason'         => $final_reason,
    'room_name'      => $room_name,
    'attached'       => $document_path !== null,
    'submitted_as'   => $submitted_as,
    'active'         => count($active_now) + (in_array($final_status, ['Approved', 'Waiting'], true) ? 1 : 0),
    'max'            => RoomRules::maxActive(),
]);
