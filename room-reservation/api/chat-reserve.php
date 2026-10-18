<?php
declare(strict_types=1);

/**
 * room-reservation/api/chat-reserve.php
 *
 * AI chatbot middleware — executes a room reservation that the AI
 * has already collected and the faculty has confirmed via the chat UI.
 *
 * This endpoint reuses the same validation rules as submit-faculty-reserve.php
 * but accepts JSON only (no file upload — AI-assisted reservations are always
 * 'personal' or 'adviser' based on the faculty's role, no document attached).
 *
 * POST body (JSON):
 *   csrf_token, room_id, reservation_date, start_time, end_time,
 *   purpose, attendees (optional, default 1)
 *
 * Response:
 *   { status: 'success', reservation_id, room_name, final_status, message }
 *   { status: 'error',   message }
 *
 * Depth: 2  →  config prefix: __DIR__ . '/../../config/…'
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
date_default_timezone_set('Asia/Manila');

ob_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

function reserve_respond(int $code, string $status, string $message, array $extra = []): never
{
    ob_end_clean();
    http_response_code($code);
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

// ── Method guard ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reserve_respond(405, 'error', 'Method not allowed.');
}

// ── Session guard ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/session.php';
if (empty($_SESSION['faculty_id'])) {
    reserve_respond(401, 'error', 'Unauthorized.');
}
$faculty_id   = (string)$_SESSION['faculty_id'];
$faculty_name = (string)($_SESSION['faculty_name'] ?? '');

// ── Parse JSON body ───────────────────────────────────────────────────────────
$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) {
    reserve_respond(400, 'error', 'Invalid request body.');
}

// ── CSRF guard ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/csrf.php';
$submitted_token = trim((string)($body['csrf_token'] ?? ''));
$session_token   = (string)($_SESSION['csrf_token'] ?? '');
if ($session_token === '' || $submitted_token === '' || !hash_equals($session_token, $submitted_token)) {
    reserve_respond(403, 'error', 'Invalid session token. Please refresh the page.');
}

// ── Extract and validate fields ───────────────────────────────────────────────
$room_id    = (int)($body['room_id']           ?? 0);
$res_date   = trim((string)($body['reservation_date'] ?? ''));
$start_time = trim((string)($body['start_time']       ?? ''));
$end_time   = trim((string)($body['end_time']         ?? ''));
$purpose    = trim((string)($body['purpose']          ?? ''));
$attendees  = max(1, (int)($body['attendees']         ?? 1));

if (!$room_id || !$res_date || !$start_time || !$end_time || !$purpose) {
    reserve_respond(422, 'error', 'Missing required reservation details. Please try again.');
}

// Past datetime check
$now_manila   = new DateTime('now', new DateTimeZone('Asia/Manila'));
$req_datetime = DateTime::createFromFormat(
    'Y-m-d H:i',
    $res_date . ' ' . $start_time,
    new DateTimeZone('Asia/Manila')
);
if ($req_datetime === false || $req_datetime <= $now_manila) {
    reserve_respond(422, 'error', 'The reservation date and time is in the past. Please choose a future time.');
}

// Sunday check
if ($req_datetime->format('N') === '7') {
    reserve_respond(422, 'error', 'Reservations are not available on Sundays. Please choose another day.');
}

// End must be after start
if ($end_time <= $start_time) {
    reserve_respond(422, 'error', 'End time must be after start time.');
}

// Operating hours
if ($start_time < '07:00' || $end_time > '20:00') {
    reserve_respond(422, 'error', 'Reservations must be within operating hours: 7:00 AM to 8:00 PM.');
}

// Max advance booking: 21 days
$max_date = (new DateTime('now', new DateTimeZone('Asia/Manila')))->modify('+21 days')->format('Y-m-d');
if ($res_date > $max_date) {
    reserve_respond(422, 'error', 'Reservations can only be made up to 21 days in advance.');
}

// ── Database connection ───────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

// ── Verify room exists and is bookable ────────────────────────────────────────
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
    reserve_respond(422, 'error', 'That room is no longer available for booking. Please choose a different room.');
}
$room_name = (string)$room_row['room_name'];

// ── Quota check ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/../core/faculty-room-quota.php';
fcty_quota_lock($conn, $faculty_id);

$active_now = fcty_active_room_reservations($conn, $faculty_id);
if (count($active_now) >= FCTY_MAX_ACTIVE_ROOM_RESERVATIONS) {
    fcty_quota_unlock($conn, $faculty_id);
    reserve_respond(409, 'error',
        'You already have ' . FCTY_MAX_ACTIVE_ROOM_RESERVATIONS . ' active room reservations. '
        . 'Cancel one or wait until it ends before reserving another.'
    );
}

// ── Determine submitted_as based on faculty role ──────────────────────────────
// Organisation Advisers file as 'adviser' (no document attached via AI flow).
$submitted_as = fcty_is_org_adviser($conn, $faculty_id) ? 'adviser' : 'personal';

// ── Insert reservation ────────────────────────────────────────────────────────
$ins = $conn->prepare(
    "INSERT INTO tbl_room_reservations
        (room_id, faculty_id, faculty_name, submitted_as,
         purpose, attendees, reservation_date, start_time, end_time,
         status, request_date)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Approved', NOW())"
);
$ins->bind_param(
    'issssisss',
    $room_id, $faculty_id, $faculty_name, $submitted_as,
    $purpose, $attendees, $res_date, $start_time, $end_time
);

if (!$ins->execute()) {
    fcty_quota_unlock($conn, $faculty_id);
    error_log('[PUPSync chat-reserve] insert failed: ' . $conn->error);
    reserve_respond(500, 'error', 'Could not save the reservation. Please try again.');
}
$reservation_id = (int)$conn->insert_id;
$ins->close();

// ── Run Arbitration Engine ────────────────────────────────────────────────────
require_once __DIR__ . '/../../equipment-booking/core/arbitration-engine.php';
ArbitrationEngine::processRoomReservation($conn, $reservation_id);

// ── Check final status ────────────────────────────────────────────────────────
$sel = $conn->prepare(
    "SELECT status, reason FROM tbl_room_reservations WHERE id = ? LIMIT 1"
);
$sel->bind_param('i', $reservation_id);
$sel->execute();
$final = $sel->get_result()->fetch_assoc();
$sel->close();

fcty_quota_unlock($conn, $faculty_id);

$final_status = (string)($final['status'] ?? 'Approved');
$final_reason = (string)($final['reason'] ?? '');

// If declined, remove the row (same as submit-student-reserve.php behaviour)
if ($final_status === 'Declined') {
    $conn->prepare("DELETE FROM tbl_room_reservations WHERE id = ?")->execute();
    reserve_respond(409, 'error',
        'The reservation could not be approved: ' . ($final_reason ?: 'time slot conflict.')
        . ' Please choose a different room or time.'
    );
}

// ── Success ───────────────────────────────────────────────────────────────────
ob_end_clean();
http_response_code(200);
echo json_encode([
    'status'         => 'success',
    'message'        => 'Reservation confirmed.',
    'reservation_id' => $reservation_id,
    'room_name'      => $room_name,
    'final_status'   => $final_status,
    'res_date'       => $res_date,
    'start_time'     => $start_time,
    'end_time'       => $end_time,
]);
