<?php
/**
 * room-reservation/api/get-faculty-upcoming-classes.php
 *
 * The signed-in faculty member's next class meetings, for the Dashboard's "Upcoming Classes" card.
 * Read-only; classes that are already over are never returned (see core/upcoming-classes.php).
 *
 * GET  (no parameters)
 *
 * Response:
 * {
 *   status: "success",
 *   now_ts: 1794872400,            // the server's clock; the page uses it to stay in step with the server
 *   today: "2026-11-16",           // Asia/Manila
 *   items: [ { id, code, subject, section, component, group, room, building, floor,
 *              date, dow, start, end, minutes, start_ts, end_ts, live }, ... ]   // soonest first
 * }
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Faculty only: this is the signed-in faculty member's own timetable
if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit();
}

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/upcoming-classes.php';

try {
    $conn  = getDB();
    $items = cs_upcoming_classes($conn, (string)$_SESSION['faculty_id'], 10, 14);
    echo json_encode([
        'status' => 'success',
        'now_ts' => time(),
        'today'  => date('Y-m-d'),
        'items'  => $items,
    ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
} catch (Throwable $e) {
    error_log('[get-faculty-upcoming-classes] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Could not load your upcoming classes.']);
}
