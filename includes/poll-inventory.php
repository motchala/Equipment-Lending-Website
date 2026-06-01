<?php
session_start();
if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit();
}

header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'lending_db');
if ($conn->connect_error) { echo json_encode([]); exit(); }

$result = $conn->query("SELECT item_id, quantity FROM tbl_inventory WHERE is_archived = 0");
$items = [];
while ($row = $result->fetch_assoc()) $items[] = $row;

echo json_encode($items);