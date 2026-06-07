<?php
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

$body = json_decode(file_get_contents('php://input'), true);
$code         = trim($body['code']         ?? '');
$student_name = trim($body['student_name'] ?? '');
$student_id   = trim($body['student_id']   ?? '');

if (!$code || !$student_name || !$student_id) {
    echo json_encode(['error' => 'All fields are required.']);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'lending_db');
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB error']);
    exit();
}

// Look up the code
$stmt = $conn->prepare(
    "SELECT id, faculty_id, faculty_name, is_used
       FROM tbl_faculty_codes
      WHERE code = ?
      LIMIT 1"
);
$stmt->bind_param('s', $code);
$stmt->execute();
$result = $stmt->get_result();
$row    = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['error' => 'Invalid faculty code. Please check the code and try again.']);
    exit();
}
if ($row['is_used']) {
    echo json_encode(['error' => 'This code has already been used. Ask your faculty for a new code.']);
    exit();
}

// Code is valid — fetch available inventory
$inv = $conn->query(
    "SELECT item_id, item_name, category, quantity, image_path
       FROM tbl_inventory
      WHERE is_archived = 0 AND quantity > 0
      ORDER BY item_name ASC"
);
$inventory = [];
while ($item = $inv->fetch_assoc()) $inventory[] = $item;

$conn->close();

echo json_encode([
    'valid'        => true,
    'faculty_id'   => $row['faculty_id'],
    'faculty_name' => $row['faculty_name'],
    'code_db_id'   => $row['id'],
    'inventory'    => $inventory,
]);
