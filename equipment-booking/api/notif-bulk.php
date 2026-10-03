<?php
/**
 * equipment-booking/api/notif-bulk.php
 * Bulk actions for the admin Notifications modal (Select / Select all /
 * Delete all / Mark read / Mark unread). The single-item endpoints
 * (notif-mark-read.php, notif-delete.php, notif-mark-all-read.php) are
 * unchanged and still used by the per-card buttons.
 *
 *   POST action=mark_read    keys[]=overdue-12&keys[]=request-7 ...
 *   POST action=mark_unread  keys[]=...
 *   POST action=delete       keys[]=...   (one, many, or all -- the client
 *                                          sends every key it wants gone)
 *
 * Needs an admin session and a valid csrf_token. Keys that are not in the
 * live feed are ignored; at most 500 keys are processed per request.
 * Response: { status, affected, unread_count }.
 */
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/notif-functions.php';
$conn = getDB();

$action = (string)($_POST['action'] ?? '');
$keys   = $_POST['keys'] ?? [];
if (!is_array($keys)) $keys = [];
$keys = array_slice(array_values(array_filter($keys, 'is_string')), 0, 500);

try {
    switch ($action) {
        case 'mark_read':
            $n = notif_set_state_many($conn, $keys, true, null);
            break;
        case 'mark_unread':
            $n = notif_set_state_many($conn, $keys, false, null);
            break;
        case 'delete':
            // Deleting implies it's no longer unread, too.
            $n = notif_set_state_many($conn, $keys, true, true);
            break;
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
            exit;
    }

    echo json_encode([
        'status'       => 'success',
        'affected'     => $n,
        'unread_count' => notif_count_unread(notif_build_list($conn)),
    ]);
} catch (Throwable $ex) {
    error_log('[PUPSync] notif-bulk.php: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Something went wrong. Please try again.']);
}
