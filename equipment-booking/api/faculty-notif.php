<?php
/**
 * equipment-booking/api/faculty-notif.php
 * Faculty notification endpoint (the faculty-dashboard bell / modal).
 *
 *   GET  ?action=list                       → the live feed + unread count
 *   POST action=mark_read    keys[]=…       → mark the given notifications read
 *   POST action=mark_unread  keys[]=…       → mark them unread again
 *   POST action=delete       keys[]=…       → delete them (one, many, or all —
 *                                             the client sends every key it
 *                                             wants gone)
 *
 * POST requests need csrf_token (same as every other endpoint). Everything is
 * scoped to $_SESSION['faculty_id']; keys that aren't in that person's live
 * feed are ignored.
 *
 * Depth: 2  →  config prefix: __DIR__ . '/../../config/…'
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');
header('Cache-Control: no-store');

if (empty($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/faculty-notif-functions.php';

$conn = getDB();
$fid  = (string)$_SESSION['faculty_id'];

// Release the session lock early — this endpoint only reads from it, and the
// dashboard polls it while other requests are in flight.
session_write_close();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $list = fnotif_build_list($conn, $fid);
        echo json_encode([
            'status'        => 'success',
            'notifications' => $list,
            'unread_count'  => fnotif_count_unread($list),
        ], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
        exit;
    }

    // csrf_verify() reads $_SESSION, which session_write_close() left readable.
    csrf_verify();

    $action = (string)($_POST['action'] ?? '');
    $keys   = $_POST['keys'] ?? [];
    if (!is_array($keys)) $keys = [];
    $keys = array_slice(array_values(array_filter($keys, 'is_string')), 0, 500);

    switch ($action) {
        case 'mark_read':
            $n = fnotif_set_state_many($conn, $fid, $keys, true, null);
            break;
        case 'mark_unread':
            $n = fnotif_set_state_many($conn, $fid, $keys, false, null);
            break;
        case 'delete':
            // Deleting implies it's no longer unread, too.
            $n = fnotif_set_state_many($conn, $fid, $keys, true, true);
            break;
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
            exit;
    }

    echo json_encode([
        'status'       => 'success',
        'affected'     => $n,
        'unread_count' => fnotif_count_unread(fnotif_build_list($conn, $fid)),
    ]);
} catch (Throwable $ex) {
    error_log('[PUPSync] faculty-notif.php: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Something went wrong. Please try again.']);
}
