<?php
ini_set('display_errors', '0');

/**
 * faculty-reset-requests.php
 *
 * Super-Admin-only, read-only: which faculty accounts are asking for a password reset right now?
 * The Faculty screen polls this to put a "Reset requested" mark on those rows.
 *
 * A request exists only while the faculty has used all 5 attempts, pressed "Notify Super Admin" and
 * has NOT signed in correctly since. A correct sign-in (or a finished reset) removes it by itself.
 *
 * GET
 * JSON: { status, requests: [ { email, requested_at (label) } ] }
 */

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/core/password-reset.php';
$conn = getDB();

if (!admin_is_super_admin($conn)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Only a Super Admin can see reset requests.']);
    exit;
}

// Release the session lock: this endpoint is polled while the page is open.
session_write_close();

$out = [];
foreach (pwr_open_requests($conn, 500) as $r) {
    $ts    = strtotime((string)$r['notified_at']);
    $out[] = [
        'email'        => strtolower((string)$r['email']),
        'requested_at' => $ts ? date('M j, Y g:i A', $ts) : (string)$r['notified_at'],
    ];
}

echo json_encode(['status' => 'success', 'requests' => $out]);
