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
 *   • Only Organization Advisers may attach a document (JPG, PNG, PDF; 5 MB).
 *     It is optional. Attaching one files the reservation as 'adviser' and
 *     stores document_path so the arbitration engine can use it for priority.
 *     Without one the reservation is filed as 'personal'.
 *     (The posted submitted_as value is ignored — the server decides.)
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
require_once __DIR__ . '/../core/faculty-room-quota.php';

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
// ── Operating hours enforcement (07:00–20:00) ─────────────────────────────
if ($start_time < '07:00' || $end_time > '20:00') {
    echo json_encode(['error' => 'Reservations must be within operating hours: 7:00 AM to 8:00 PM.']);
    exit();
}

$conn = getDB();

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

// ── Optional supporting document (Organization Advisers only) ────────────
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
    if (!fcty_is_org_adviser($conn, $faculty_id)) {
        echo json_encode(['error' => 'Only organization advisers can attach a supporting document.']);
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

// ── Room limit: at most N active reservations per faculty account ─────────
// Serialised per faculty so two quick submissions cannot both pass the check.
fcty_quota_lock($conn, $faculty_id);

$active_now = fcty_active_room_reservations($conn, $faculty_id);
if (count($active_now) >= FCTY_MAX_ACTIVE_ROOM_RESERVATIONS) {
    fcty_quota_unlock($conn, $faculty_id);
    echo json_encode([
        'error'         => fcty_quota_message(),
        'limit_reached' => true,
        'active'        => count($active_now),
        'max'           => FCTY_MAX_ACTIVE_ROOM_RESERVATIONS,
    ]);
    exit();
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
    $submitted_as  = 'adviser';   // an attached letter files this as an organization request
}

// ── Insert reservation ────────────────────────────────────────────────────
$notes_val = $notes !== '' ? $notes : null;
$ins = $conn->prepare(
    "INSERT INTO tbl_room_reservations
        (room_id, faculty_id, faculty_name, submitted_as,
         purpose, attendees, reservation_date, start_time, end_time,
         notes, document_path, status, request_date)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Approved', NOW())"
);
$ins->bind_param(
    'issssisssss',
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
    $document_path
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
require_once __DIR__ . '/../../equipment-booking/core/arbitration-engine.php';
ArbitrationEngine::processRoomReservation($conn, $reservation_id);

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

fcty_quota_unlock($conn, $faculty_id);

echo json_encode([
    'success'        => true,
    'reservation_id' => $reservation_id,
    'status'         => $final_status,
    'reason'         => $final_reason,
    'room_name'      => $room_name,
    'attached'       => $document_path !== null,
    'submitted_as'   => $submitted_as,
    'active'         => count($active_now) + ($final_status === 'Approved' ? 1 : 0),
    'max'            => FCTY_MAX_ACTIVE_ROOM_RESERVATIONS,
]);
