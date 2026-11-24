<?php
ini_set('display_errors', '0');

/**
 * faculty-reset-status.php
 *
 * Super-Admin-only, read-only: is this faculty account allowed to be reset yet?
 * Used by Faculty > Edit > Reset password to show "3 of 5 wrong attempts" or "ready to reset".
 *
 * GET: faculty_id
 * JSON: { status, fail_count, max, eligible, requested, notified_at (label|null) }
 * requested = used all attempts AND pressed "Notify Super Admin" (the only time the reset UI is shown).
 */

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function frs_respond(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    frs_respond(401, ['status' => 'error', 'message' => 'Unauthorized.']);
}

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/core/password-reset.php';
$conn = getDB();

if (!admin_is_super_admin($conn)) {
    frs_respond(403, ['status' => 'error', 'message' => 'Only a Super Admin can reset faculty passwords.']);
}

$faculty_id = trim((string)($_GET['faculty_id'] ?? ''));
if ($faculty_id === '' || strlen($faculty_id) > 255) {
    frs_respond(422, ['status' => 'error', 'message' => 'Faculty account not found.']);
}

$email = '';
if ($st = $conn->prepare('SELECT email FROM tbl_users WHERE faculty_id = ? LIMIT 1')) {
    $st->bind_param('s', $faculty_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $email = $row ? (string)$row['email'] : '';
}
if ($email === '') {
    frs_respond(404, ['status' => 'error', 'message' => 'That account no longer exists. Refresh the page.']);
}

$s = pwr_status($conn, $email);
$notified_label = null;
if ($s['notified_at'] !== null) {
    $ts = strtotime($s['notified_at']);
    $notified_label = $ts ? date('M j, Y g:i A', $ts) : $s['notified_at'];
}

frs_respond(200, [
    'status'      => 'success',
    'fail_count'  => min($s['fail_count'], $s['max']),
    'max'         => $s['max'],
    'eligible'    => $s['eligible'],
    'requested'   => $s['eligible'] && $s['notified_at'] !== null,
    'notified_at' => $notified_label,
]);
