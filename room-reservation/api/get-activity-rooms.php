<?php
/**
 * room-reservation/api/get-activity-rooms.php
 * Live refresh for the "Room reservations" half of the faculty My Activity screen.
 *
 * GET (no params) -> JSON:
 *   body   string  HTML for the body of the "Room reservations" card
 *   rows   string  HTML <tr> rows for the room entries of the history table
 *   stats  object  values for the two room tiles in the stat strip
 *
 * The HTML comes from the same functions faculty-dashboard.php uses on first
 * paint (core/faculty-activity-rooms.php), so both are always identical.
 * Read-only: nothing is changed by calling this.
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/faculty-activity-rooms.php';

$conn = getDB();
$session_id = $_SESSION['faculty_id'];
$data = fact_load_rooms($conn, is_scalar($session_id) ? (string)$session_id : '');

echo json_encode([
    'body'  => fact_render_rooms_body($data),
    'rows'  => fact_render_room_history_rows($data),
    'stats' => fact_room_stats($data),
]);
