<?php
/**
 * room-reservation/api/leave-waitlist.php
 * Faculty leaves the waitlist for a room/date/time slot they had joined (My Activity > Waitlist).
 * The twin of join-waitlist.php: same body, same auth, same CSRF.
 *
 * POST body (JSON):
 *   { room_id, reservation_date, start_time, end_time, csrf_token }
 *
 * Responses:
 *   { success: true }
 *   { error: string }
 *
 * Only the signed-in faculty member's own waitlist entry for that exact slot is removed.
 * Depth: 2  →  config prefix: __DIR__ . '/../../config/…'
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

$body = json_decode(file_get_contents('php://input'), true);

// CSRF
$token = $body['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid request token. Please refresh and try again.']);
    exit();
}

$roomId    = intval($body['room_id']        ?? 0);
$resDate   = trim($body['reservation_date'] ?? '');
$startTime = trim($body['start_time']       ?? '');
$endTime   = trim($body['end_time']         ?? '');

if (!$roomId || !$resDate || !$startTime || !$endTime) {
    echo json_encode(['error' => 'All fields are required.']);
    exit();
}

$conn = getDB();

$del = $conn->prepare(
    'DELETE FROM tbl_room_waitlist
      WHERE faculty_id = ? AND room_id = ? AND reservation_date = ? AND start_time = ? AND end_time = ?'
);
$facultyId = (string)$_SESSION['faculty_id'];
$del->bind_param('sisss', $facultyId, $roomId, $resDate, $startTime, $endTime);
$del->execute();
$removed = $del->affected_rows;
$del->close();

if ($removed < 1) {
    echo json_encode(['error' => 'You are no longer on the waitlist for that slot.']);
    exit();
}

echo json_encode(['success' => true]);
