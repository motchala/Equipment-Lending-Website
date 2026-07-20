<?php
/**
 * room-reservation/api/submit-room-issue.php
 * Faculty reports an issue with a room.
 * Inserts into tbl_room_issues (status = 'Open').
 * Does NOT change tbl_rooms.status — admin decides that separately.
 *
 * POST body (JSON):
 *   { room_id: int, description: string, csrf_token: string }
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

$roomId      = intval($body['room_id']     ?? 0);
$description = trim($body['description']  ?? '');

if (!$roomId) {
    echo json_encode(['error' => 'Invalid room.']);
    exit();
}
if (strlen($description) < 10) {
    echo json_encode(['error' => 'Please provide a description of at least 10 characters.']);
    exit();
}
if (strlen($description) > 1000) {
    echo json_encode(['error' => 'Description must be 1000 characters or fewer.']);
    exit();
}

$facultyId   = $_SESSION['faculty_id'];
$facultyName = $_SESSION['faculty_name'] ?? '';

$conn = getDB();

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

$ins = $conn->prepare(
    "INSERT INTO tbl_room_issues
        (room_id, reported_by_id, reported_by_name, description, status)
     VALUES (?, ?, ?, ?, 'Open')"
);
$ins->bind_param('isss', $roomId, $facultyId, $facultyName, $description);

if (!$ins->execute()) {
    error_log('[PUPSync] submit-room-issue insert failed: ' . $conn->error);
    $ins->close();
    echo json_encode(['error' => 'Failed to submit report. Please try again.']);
    exit();
}
$ins->close();

echo json_encode(['success' => true]);
