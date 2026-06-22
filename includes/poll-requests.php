<?php
require_once __DIR__ . '/security-headers.php';
require_once __DIR__ . '/session-config.php';
if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit();
}

header('Content-Type: application/json');

require_once __DIR__ . '/db.php';
$conn = getDB();

$uid = $_SESSION['faculty_id'];
$stmt = $conn->prepare(
    "SELECT id, faculty_name, faculty_id, equipment_name, instructor, room,
            borrow_date, return_date, status, reason, request_date,
            return_token, returned_at
       FROM tbl_requests
      WHERE faculty_id = ?
      ORDER BY request_date DESC"
);
$stmt->bind_param('s', $uid);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($rows);
