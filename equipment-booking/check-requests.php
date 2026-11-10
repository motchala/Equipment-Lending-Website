<?php
require_once __DIR__ . '/../config/db.php';
$conn = getDB();
header('Content-Type: text/plain');
$r = $conn->query('SELECT id, equipment_name, status, submitted_as, booking_mode FROM tbl_requests ORDER BY id DESC LIMIT 10');
while ($row = $r->fetch_assoc()) {
    echo $row['id'] . ' | ' . $row['equipment_name'] . ' | ' . $row['status'] . ' | ' . $row['submitted_as'] . ' | ' . ($row['booking_mode'] ?? 'NULL') . "\n";
}
