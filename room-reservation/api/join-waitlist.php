<?php
/**
 * room-reservation/api/join-waitlist.php
 * Faculty joins the waitlist for a specific room/date/time slot.
 *
 * POST body (JSON):
 *   { room_id, reservation_date, start_time, end_time, csrf_token }
 *
 * Responses:
 *   { success: true }
 *   { success: true, already: true }  — already on waitlist (not an error)
 *   { error: string }
 *
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

$roomId   = intval($body['room_id']          ?? 0);
$resDate  = trim($body['reservation_date']   ?? '');
$startTime = trim($body['start_time']        ?? '');
$endTime  = trim($body['end_time']           ?? '');

if (!$roomId || !$resDate || !$startTime || !$endTime) {
    echo json_encode(['error' => 'All fields are required.']);
    exit();
}

$facultyId   = $_SESSION['faculty_id'];
$facultyName = $_SESSION['faculty_name'] ?? '';

$conn = getDB();

// Fetch email from DB (never trust session for email)
$fac = $conn->prepare("SELECT email FROM tbl_users WHERE faculty_id = ? LIMIT 1");
$fac->bind_param('s', $facultyId);
$fac->execute();
$facRow = $fac->get_result()->fetch_assoc();
$fac->close();

if (!$facRow || empty($facRow['email'])) {
    echo json_encode(['error' => 'No email address found for your account. Please update your profile.']);
    exit();
}
$facultyEmail = $facRow['email'];

// Verify room exists
$roomChk = $conn->prepare(
    "SELECT room_id FROM tbl_rooms WHERE room_id = ? AND is_archived = 0 LIMIT 1"
);
$roomChk->bind_param('i', $roomId);
$roomChk->execute();
$roomChk->store_result();
if ($roomChk->num_rows === 0) {
    $roomChk->close();
    echo json_encode(['error' => 'Room not found.']);
    exit();
}
$roomChk->close();

// INSERT — UNIQUE constraint handles duplicates gracefully
$ins = $conn->prepare(
    "INSERT IGNORE INTO tbl_room_waitlist
        (room_id, reservation_date, start_time, end_time,
         faculty_id, faculty_name, faculty_email)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$ins->bind_param(
    'issssss',
    $roomId, $resDate, $startTime, $endTime,
    $facultyId, $facultyName, $facultyEmail
);
$ins->execute();
$affected = $ins->affected_rows;
$ins->close();

if ($affected === 0) {
    // Duplicate — already on waitlist
    echo json_encode(['success' => true, 'already' => true]);
    exit();
}

echo json_encode(['success' => true, 'already' => false]);
