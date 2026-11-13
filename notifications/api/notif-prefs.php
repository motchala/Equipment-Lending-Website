<?php
/**
 * notifications/api/notif-prefs.php
 * Notification preferences endpoint (Settings > Preferences, admin AND faculty).
 *
 *   GET  ?audience=admin|faculty            -> current preferences
 *   POST audience=…  action=save  prefs[key]=0|1 …   -> save the listed toggles
 *   POST audience=…  action=reset                   -> everything back on
 *
 * The audience must match the signed-in session (an admin session can only
 * change admin preferences, a faculty session only faculty ones), and each
 * account only ever touches its own row set. POST needs csrf_token.
 *
 * Depth: 2  ->  config prefix: __DIR__ . '/../../config/…'
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

$audience = (string)($_POST['audience'] ?? $_GET['audience'] ?? '');

$signedIn = false;
if ($audience === 'admin')   $signedIn = (($_SESSION['admin'] ?? null) === true);
if ($audience === 'faculty') $signedIn = !empty($_SESSION['faculty_id']);

if (!in_array($audience, ['admin', 'faculty'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Unknown audience.']);
    exit;
}
if (!$signedIn) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/preferences.php';

$conn    = getDB();
$userKey = notif_prefs_current_key($conn, $audience);

// Everything needed from the session has been read; release the lock early
// (csrf_verify() can still read $_SESSION afterwards).
session_write_close();

if ($userKey === '') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(['status' => 'success', 'prefs' => notif_prefs_get($conn, $audience, $userKey)]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
        exit;
    }

    csrf_verify();

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $in = $_POST['prefs'] ?? [];
        if (!is_array($in) || !$in) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Nothing to save.']);
            exit;
        }
        $changes = [];
        foreach ($in as $k => $v) {
            // Only an explicit 0 or 1 is a valid switch position -- anything else is refused
            // rather than guessed at (a garbled value must never silently turn alerts off).
            if (!is_string($k) || !is_string($v) || ($v !== '0' && $v !== '1')) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid value.']);
                exit;
            }
            $changes[$k] = ($v === '1');
        }
        if (!notif_prefs_save($conn, $audience, $userKey, $changes)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Could not save your preference. Please try again.']);
            exit;
        }
    } elseif ($action === 'reset') {
        if (!notif_prefs_reset($conn, $audience, $userKey)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Could not reset your preferences. Please try again.']);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
        exit;
    }

    echo json_encode(['status' => 'success', 'prefs' => notif_prefs_get($conn, $audience, $userKey)]);
} catch (Throwable $ex) {
    error_log('[PUPSync] notif-prefs.php: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Something went wrong. Please try again.']);
}
