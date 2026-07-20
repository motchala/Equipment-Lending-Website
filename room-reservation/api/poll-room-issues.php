<?php
/**
 * room-reservation/api/poll-room-issues.php
 * Admin polls all room issue reports (Open + recently resolved).
 * Returns JSON array ordered: Open first (newest first), then resolved.
 *
 * Admin-only endpoint.
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

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

$result = $conn->query(
    "SELECT ri.id, ri.room_id, ri.reported_by_id, ri.reported_by_name,
            ri.description, ri.status, ri.admin_notes,
            ri.created_at, ri.resolved_at,
            r.room_name,
            b.name                                               AS building_name,
            COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label,
            c.campus_name
       FROM tbl_room_issues ri
       JOIN tbl_rooms     r ON r.room_id     = ri.room_id
       JOIN tbl_buildings b ON b.building_id = r.building_id
       JOIN tbl_campuses  c ON c.campus_id   = b.campus_id
      ORDER BY
            FIELD(ri.status, 'Open', 'Resolved', 'Dismissed'),
            ri.created_at DESC"
);

$rows = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

echo json_encode($rows);
