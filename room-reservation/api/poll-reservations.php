<?php
/**
 * room-reservation/api/poll-reservations.php
 * Returns all room reservations for the logged-in faculty member, ordered
 * newest-first.  Called every 10 seconds by faculty-dashboard.js to keep
 * the "My Reservations" table live without a full page reload.
 *
 * Response: JSON array of reservation row objects, shape:
 *   { id, room_name, floor_label, building_name,
 *     reservation_date, start_time, end_time,
 *     purpose, submitted_as, status, reason }
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
    echo json_encode([]);
    exit();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

$faculty_id = $_SESSION['faculty_id'];

$stmt = $conn->prepare(
    "SELECT rr.id,
            rr.reservation_date,
            rr.start_time,
            rr.end_time,
            rr.purpose,
            rr.submitted_as,
            rr.status,
            rr.reason,
            r.room_name,
            b.name                                         AS building_name,
            COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label
       FROM tbl_room_reservations rr
       JOIN tbl_rooms     r ON r.room_id     = rr.room_id
       JOIN tbl_buildings b ON b.building_id = r.building_id
      WHERE rr.faculty_id = ?
      ORDER BY rr.id DESC"
);

$stmt->bind_param('s', $faculty_id);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($rows);
