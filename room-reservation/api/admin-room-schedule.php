<?php

/**
 * room-reservation/api/admin-room-schedule.php
 * Admin-only. Returns EVERY approved reservation for one room (all weeks:
 * past, this week and every future week), so the admin "Room Schedule"
 * modal can show the complete picture and refresh itself while it is open.
 *
 * GET params:
 *   room_id — int, required
 *
 * Response:
 * {
 *   room_id: int,
 *   reservations: [
 *     { id, date: "Y-m-d", start: "HH:MM", end: "HH:MM",
 *       faculty, submitted_as, submitted_by, purpose, attendees }, ...
 *   ]
 * }
 *
 * Only status = 'Approved' is returned: that is the only status that blocks
 * a slot (see check-room-availability.php). Declined / Cancelled never do.
 *
 * Depth: 2  →  config prefix: __DIR__ . '/../../config/…'
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Manila');

$room_id = intval($_GET['room_id'] ?? 0);
if ($room_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'room_id is required.']);
    exit();
}

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

$stmt = $conn->prepare(
    "SELECT id, reservation_date,
            TIME_FORMAT(start_time, '%H:%i') AS start_hm,
            TIME_FORMAT(end_time,   '%H:%i') AS end_hm,
            faculty_name, submitted_as, submitted_by_name,
            purpose, attendees
       FROM tbl_room_reservations
      WHERE room_id = ?
        AND status  = 'Approved'
      ORDER BY reservation_date ASC, start_time ASC"
);

if ($stmt === false) {
    error_log('admin-room-schedule.php: prepare() failed — ' . $conn->error);
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred. Please try again.']);
    exit();
}

$stmt->bind_param('i', $room_id);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = [
        'id'           => (int)$row['id'],
        'date'         => $row['reservation_date'],
        'start'        => $row['start_hm'],
        'end'          => $row['end_hm'],
        'faculty'      => (string)$row['faculty_name'],
        'submitted_as' => (string)$row['submitted_as'],
        'submitted_by' => (string)($row['submitted_by_name'] ?? ''),
        'purpose'      => (string)($row['purpose'] ?? ''),
        'attendees'    => $row['attendees'] !== null ? (int)$row['attendees'] : null,
    ];
}
$stmt->close();
$conn->close();

echo json_encode(
    ['room_id' => $room_id, 'reservations' => $rows],
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES
);
