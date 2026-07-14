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

$uid = $_SESSION['faculty_id'];

// Guard: check if submitted_as column exists (dual-borrowing-mode migration may not be run yet)
$_sa_col = $conn->query("SHOW COLUMNS FROM tbl_requests LIKE 'submitted_as'");
$_has_sa = $_sa_col && $_sa_col->num_rows > 0;
$_sa_select = $_has_sa ? ', submitted_as' : '';

$stmt = $conn->prepare(
    "SELECT id, faculty_name, faculty_id, equipment_name, instructor, room,
            borrow_date, return_date, status, reason, request_date,
            return_token, returned_at{$_sa_select}
       FROM tbl_requests
      WHERE faculty_id = ?
      ORDER BY request_date DESC"
);
$stmt->bind_param('s', $uid);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    if (!$_has_sa) $row['submitted_as'] = null;
    $rows[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($rows);
