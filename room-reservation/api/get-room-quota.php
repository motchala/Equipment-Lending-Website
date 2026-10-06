<?php
/**
 * room-reservation/api/get-room-quota.php
 * Tells the Reserve dialog where the signed-in faculty member stands.
 *
 * GET (no params) → JSON:
 *   max          int    most active room reservations allowed at once
 *   active       int    how many the faculty member holds right now
 *   remaining    int    max - active (never below 0)
 *   reservations array  the active ones: id, room_name, date, start, end
 *   is_adviser   bool   Organization Adviser → may attach a supporting document
 *   doc          object accepted document types / size (for the UI)
 *
 * This is informational only. submit-faculty-reserve.php enforces every rule
 * again on the server.
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
require_once __DIR__ . '/../core/faculty-room-quota.php';

$conn       = getDB();
$faculty_id = (string)$_SESSION['faculty_id'];

$active = fcty_active_room_reservations($conn, $faculty_id);
$max    = FCTY_MAX_ACTIVE_ROOM_RESERVATIONS;

echo json_encode([
    'max'          => $max,
    'active'       => count($active),
    'remaining'    => max(0, $max - count($active)),
    'reservations' => $active,
    'is_adviser'   => fcty_is_org_adviser($conn, $faculty_id),
    'doc'          => [
        'types'     => ['image/jpeg', 'image/png', 'application/pdf'],
        'extensions'=> ['jpg', 'jpeg', 'png', 'pdf'],
        'max_bytes' => FCTY_ROOM_DOC_MAX_BYTES,
    ],
]);
