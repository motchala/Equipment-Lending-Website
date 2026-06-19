<?php
require_once __DIR__ . '/session-config.php';
if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit();
}

header('Content-Type: application/json');

require_once __DIR__ . '/db.php';
$conn = getDB();

$result = $conn->query("SELECT item_id, quantity FROM tbl_inventory WHERE is_archived = 0");
$items = [];
while ($row = $result->fetch_assoc()) $items[] = $row;

echo json_encode($items);
