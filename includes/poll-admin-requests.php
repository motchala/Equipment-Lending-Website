<?php
session_start();
if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    echo json_encode(['changed' => false]);
    exit();
}

header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'lending_db');
if ($conn->connect_error) {
    echo json_encode(['changed' => false]);
    exit();
}

// Get the timestamp of the most recent status change
// We compare against what the admin last saw using a session timestamp
$last_check = $_SESSION['admin_last_poll'] ?? date('Y-m-d H:i:s', strtotime('-1 minute'));
$_SESSION['admin_last_poll'] = date('Y-m-d H:i:s');

$stmt = $conn->prepare(
    "SELECT COUNT(*) as c FROM tbl_requests
      WHERE returned_at > ?
         OR (status = 'Returned' AND returned_at IS NOT NULL AND returned_at > ?)"
);
$stmt->bind_param('ss', $last_check, $last_check);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

$changed = (int)$row['c'] > 0;

echo json_encode([
    'changed' => $changed,
    'message' => $changed ? 'A return has been confirmed. Refreshing...' : ''
]);