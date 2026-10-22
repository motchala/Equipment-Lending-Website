<?php

/**
 * view-faculty-registry.php
 *
 * Super-Admin-only: returns the imported Faculty Registry list (Faculty tab ->
 * Faculty Registry -> "View registry list"), but ONLY after the acting Super
 * Admin re-enters their own password.
 *
 * POST fields
 *   csrf_token        required
 *   current_password  required - the acting Super Admin's own password
 *
 * Rules enforced here (never trusted to the UI):
 *   - POST only, valid CSRF token, logged-in admin who is a Super Admin
 *     (re-checked in the DB, not just the session).
 *   - The password is checked against the acting admin's own row in
 *     tbl_accounts. Wrong guesses are throttled per session (5 tries, then
 *     5 minutes) and share the counter used by Settings -> Manage Admins and
 *     the "delete anyway" confirmation, so there is no extra way to guess.
 *   - Nothing from the registry is read or returned until the password is
 *     correct.
 *   - Read-only: this endpoint never changes the registry.
 *
 * Returns JSON
 *   success: { status: "success", total, entries: [
 *               { faculty_id, full_name, department, is_newly_hired, uploaded_at } ] }
 *   failure: { status: "error", code, error }   (code "bad_password" for password problems)
 */
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function vr_respond(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Newer PHP versions make mysqli throw on SQL errors. Turn anything unexpected
// into a clean JSON error instead of an empty 500 the UI can't read.
set_exception_handler(function (Throwable $e): void {
    error_log('[view-faculty-registry] uncaught: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['status' => 'error', 'code' => 'server', 'error' => 'Could not load the registry. Please try again.']);
});

// -- Gate: method, session, CSRF ----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    vr_respond(405, ['status' => 'error', 'code' => 'method', 'error' => 'Method not allowed.']);
}
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    vr_respond(401, ['status' => 'error', 'code' => 'unauthorized', 'error' => 'Unauthorized.']);
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    vr_respond(403, ['status' => 'error', 'code' => 'forbidden', 'error' => 'Super Admin only.']);
}

// -- Re-enter password (own password, throttled) --------------------------------
$now = time();
if (($_SESSION['mgmt_reauth_lock_until'] ?? 0) > $now) {
    $mins = (int)ceil(($_SESSION['mgmt_reauth_lock_until'] - $now) / 60);
    vr_respond(429, [
        'status' => 'error',
        'code'   => 'bad_password',
        'error'  => "Too many incorrect passwords. Try again in {$mins} minute" . ($mins === 1 ? '' : 's') . '.',
    ]);
}

$given = (string)($_POST['current_password'] ?? '');
if ($given === '') {
    vr_respond(422, ['status' => 'error', 'code' => 'bad_password', 'error' => 'Enter your password to continue.']);
}

$actor_hash = '';
if ($st = $conn->prepare('SELECT password FROM tbl_accounts WHERE email = ? LIMIT 1')) {
    $email = (string)($_SESSION['admin_email'] ?? '');
    $st->bind_param('s', $email);
    $st->execute();
    $r = $st->get_result();
    $actor_hash = ($r && $r->num_rows === 1) ? (string)$r->fetch_assoc()['password'] : '';
    $st->close();
}

if ($actor_hash === '' || !password_verify($given, $actor_hash)) {
    $_SESSION['mgmt_reauth_fails'] = (int)($_SESSION['mgmt_reauth_fails'] ?? 0) + 1;
    if ($_SESSION['mgmt_reauth_fails'] >= 5) {
        $_SESSION['mgmt_reauth_fails']      = 0;
        $_SESSION['mgmt_reauth_lock_until'] = $now + 300;
        vr_respond(429, ['status' => 'error', 'code' => 'bad_password', 'error' => 'Too many incorrect passwords. Try again in 5 minutes.']);
    }
    vr_respond(403, ['status' => 'error', 'code' => 'bad_password', 'error' => 'Incorrect password.']);
}
unset($_SESSION['mgmt_reauth_fails'], $_SESSION['mgmt_reauth_lock_until']);

// -- Password OK: read the registry -----------------------------------------------
$entries = [];

$tbl = $conn->query("SHOW TABLES LIKE 'tbl_faculty_registry'");
if ($tbl && $tbl->num_rows > 0) {
    $res = $conn->query(
        'SELECT faculty_id, full_name, department, is_newly_hired, uploaded_at
           FROM tbl_faculty_registry
          ORDER BY full_name ASC
          LIMIT 10000'
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $entries[] = [
                'faculty_id'     => (string)($row['faculty_id'] ?? ''),
                'full_name'      => (string)$row['full_name'],
                'department'     => (string)($row['department'] ?? ''),
                'is_newly_hired' => (bool)$row['is_newly_hired'],
                'uploaded_at'    => (string)($row['uploaded_at'] ?? ''),
            ];
        }
    }
}

vr_respond(200, ['status' => 'success', 'total' => count($entries), 'entries' => $entries]);
