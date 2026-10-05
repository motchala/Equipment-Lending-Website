<?php
require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';
if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

require_once __DIR__ . '/../core/booking-schedule.php';
// Keeps Overdue marking and "booking has started" stock hand-over current while anyone is online.
BookingSchedule::tick($conn);
$totals = BookingSchedule::totalStockMap($conn);

$result = $conn->query("SELECT item_id, item_name, quantity FROM tbl_inventory WHERE is_archived = 0");
$items = [];
while ($row = $result->fetch_assoc()) {
    $row['total'] = $totals[$row['item_name']] ?? (int)$row['quantity'];
    $items[] = $row;
}

echo json_encode($items);
