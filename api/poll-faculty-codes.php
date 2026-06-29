<?php
require_once __DIR__ . '/../config/security-headers.php';
require_once __DIR__ . '/../config/session.php';
if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit();
}

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../config/db.php';
$conn = getDB();

$faculty_id = $_SESSION['faculty_id'];

// Get the most recent code for this faculty (used or unused)
$stmt = $conn->prepare(
    "SELECT code, is_used, used_by_name, used_by_id,
            created_at, used_at
       FROM tbl_faculty_codes
      WHERE faculty_id = ?
      ORDER BY created_at DESC
      LIMIT 1"
);
$stmt->bind_param('s', $faculty_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) {
    echo json_encode(['has_code' => false]);
    exit();
}

echo json_encode([
    'has_code'     => true,
    'code'         => $row['code'],
    'is_used'      => (bool) $row['is_used'],
    'used_by_name' => $row['used_by_name'],
    'used_by_id'   => $row['used_by_id'],
    'created_at'   => date('M j, Y g:i A', strtotime($row['created_at'])),
    'used_at'      => $row['used_at'] ? date('M j, Y g:i A', strtotime($row['used_at'])) : null,
]);
