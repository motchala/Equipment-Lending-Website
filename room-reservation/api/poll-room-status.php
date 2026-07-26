<?php
/**
 * room-reservation/api/poll-room-status.php
 * Returns the computed current status for every non-archived room.
 * Called every 30 seconds by fcty-facilities.js to keep chip colours fresh.
 *
 * Response: { "<room_id>": "<status>", ... }
 * Status values: "Available" | "Booked" | "Maintenance" | "Not Bookable"
 *
 * "Booked" is computed — a room is Booked when an Approved reservation
 * exists for today, and the current server time (Asia/Manila) falls
 * within [start_time, end_time). Records are never deleted; past rows
 * simply stop matching once their end_time passes.
 *
 * The ArbitrationEngine checks live DB data independently — this endpoint
 * only affects the visual chip display and has no influence on conflict
 * detection or approval decisions.
 *
 * Depth: 2  →  config prefix: __DIR__ . '/../../config/…'
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

// Must be authenticated (faculty or admin)
if (!isset($_SESSION['faculty_id']) && (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

$today    = date('Y-m-d');
$now_time = date('H:i:s');

// ── Fetch all non-archived rooms with their admin-set status ──────────────
$rooms_stmt = $conn->query(
    "SELECT room_id, status FROM tbl_rooms WHERE is_archived = 0"
);
if (!$rooms_stmt) {
    echo json_encode([]);
    exit();
}
$statuses = [];
while ($row = $rooms_stmt->fetch_assoc()) {
    $statuses[(string)$row['room_id']] = $row['status']; // 'Available'|'Maintenance'|'Not Bookable'
}
$rooms_stmt->free();

// ── Find rooms currently occupied (Approved reservation, now within window) ─
// Only applies to rooms whose admin status is 'Available' — Maintenance/Not Bookable
// always show their admin-set status regardless of reservation state.
if (!empty($statuses)) {
    $booked_stmt = $conn->prepare(
        "SELECT DISTINCT room_id
           FROM tbl_room_reservations
          WHERE reservation_date = ?
            AND start_time      <= ?
            AND end_time         > ?
            AND status           = 'Approved'"
    );
    $booked_stmt->bind_param('sss', $today, $now_time, $now_time);
    $booked_stmt->execute();
    $booked_result = $booked_stmt->get_result();
    while ($row = $booked_result->fetch_assoc()) {
        $rid = (string)$row['room_id'];
        // Only override if the admin status is Available; leave Maintenance/Not Bookable alone
        if (isset($statuses[$rid]) && $statuses[$rid] === 'Available') {
            $statuses[$rid] = 'Booked';
        }
    }
    $booked_stmt->close();
}

echo json_encode($statuses);
