<?php

/**
 * reset-faculty-password.php
 *
 * Super-Admin-only: generate a new password for a faculty account that is locked out
 * (Faculty tab -> Edit -> Reset password -> Generate reset password).
 *
 * POST fields
 *   csrf_token        required
 *   faculty_id        required
 *   current_password  required - the acting Super Admin's OWN password
 *
 * Rules enforced here (never trusted to the UI):
 *   - POST only, valid CSRF token, logged-in admin who is a Super Admin (re-checked in the DB).
 *   - The faculty account must have used all PWR_MAX_ATTEMPTS (5) wrong passwords, pressed "Notify Super Admin"
 *     on the landing page, and not signed in correctly since (auth/core/password-reset.php). Otherwise: 409, nothing changes.
 *   - The Super Admin must confirm with their own password. Wrong guesses are throttled per session
 *     (5 tries, then 5 minutes) and share the counter used by Manage Admins and Delete Faculty.
 *   - A random password is generated on the server (faculty_fl_generate_password) and OVERWRITES the
 *     faculty's current password. It is returned ONCE in this response and never stored in plain text
 *     or written to the activity log.
 *   - The account is flagged must_change_password = 1, so the faculty's next sign-in with it lands on the
 *     same "choose your own password" screen a brand-new account gets (account-management/faculty-setup.php).
 *     reset_pending = 1 makes that screen ask for the password only.
 *   - "Stay logged in" tokens of the account are removed, and any session that started before the reset
 *     is ended by faculty_session_guard().
 *   - Claiming the reset and changing the password happen in ONE transaction, so a double click or two
 *     Super Admins pressing Generate together can only reset once.
 *
 * JSON: { status: success|error, message, password? (success only), code? }
 */
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function rfp_respond(int $code, string $status, string $message, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message] + $extra);
    exit;
}

// mysqli throws on SQL errors on newer PHP: turn anything unexpected into JSON the UI can read.
set_exception_handler(function (Throwable $e): void {
    error_log('[reset-faculty-password] uncaught: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['status' => 'error', 'message' => 'Could not reset this password. Please try again.']);
});

// -- Gate: method, session, CSRF ----------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    rfp_respond(405, 'error', 'Method not allowed.');
}
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    rfp_respond(401, 'error', 'Unauthorized.');
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/core/password-reset.php';
require_once __DIR__ . '/../core/faculty-first-login.php';
require_once __DIR__ . '/../../shared/core/activity-logger.php';
$conn = getDB();
$conn->set_charset('utf8mb4');

if (!admin_is_super_admin($conn)) {
    rfp_respond(403, 'error', 'Only a Super Admin can reset faculty passwords.');
}

// -- Which account? -----------------------------------------------------------
$faculty_id = trim((string)($_POST['faculty_id'] ?? ''));
if ($faculty_id === '' || strlen($faculty_id) > 255) {
    rfp_respond(422, 'error', 'Faculty account not found.');
}

$target = null;
if ($st = $conn->prepare('SELECT fullname, email FROM tbl_users WHERE faculty_id = ? LIMIT 1')) {
    $st->bind_param('s', $faculty_id);
    $st->execute();
    $res    = $st->get_result();
    $target = ($res && $res->num_rows === 1) ? $res->fetch_assoc() : null;
    $st->close();
}
if (!$target) {
    rfp_respond(404, 'error', 'That account no longer exists. Refresh the page.');
}
$target_email = (string)$target['email'];
$target_name  = (string)$target['fullname'];

if (!faculty_fl_ensure_schema($conn)) {
    rfp_respond(500, 'error', 'The database needs a one-time update before passwords can be reset. Please try again.');
}

// -- Validation first: only after 5 wrong passwords ------------------------------
$status = pwr_status($conn, $target_email);
if (!$status['eligible'] || $status['notified_at'] === null) {
    rfp_respond(409, 'error', 'This account has no open reset request, so its password cannot be reset.', [
        'code'       => 'not_eligible',
        'fail_count' => min($status['fail_count'], PWR_MAX_ATTEMPTS),
        'max'        => PWR_MAX_ATTEMPTS,
    ]);
}

// -- The acting Super Admin confirms with their OWN password ----------------------
$now = time();
if (($_SESSION['mgmt_reauth_lock_until'] ?? 0) > $now) {
    $mins = (int)ceil(($_SESSION['mgmt_reauth_lock_until'] - $now) / 60);
    rfp_respond(429, 'error', "Too many incorrect passwords. Try again in {$mins} minute" . ($mins === 1 ? '' : 's') . '.', ['code' => 'bad_password']);
}

$given = (string)($_POST['current_password'] ?? '');
if ($given === '') {
    rfp_respond(422, 'error', 'Enter your password to continue.', ['code' => 'bad_password']);
}

$actor_email = (string)($_SESSION['admin_email'] ?? '');
$actor_hash  = '';
if ($st = $conn->prepare('SELECT password FROM tbl_accounts WHERE email = ? LIMIT 1')) {
    $st->bind_param('s', $actor_email);
    $st->execute();
    $r          = $st->get_result();
    $actor_hash = ($r && $r->num_rows === 1) ? (string)$r->fetch_assoc()['password'] : '';
    $st->close();
}

if ($actor_hash === '' || !password_verify($given, $actor_hash)) {
    $_SESSION['mgmt_reauth_fails'] = (int)($_SESSION['mgmt_reauth_fails'] ?? 0) + 1;
    if ($_SESSION['mgmt_reauth_fails'] >= 5) {
        $_SESSION['mgmt_reauth_fails']      = 0;
        $_SESSION['mgmt_reauth_lock_until'] = $now + 300;
        rfp_respond(429, 'error', 'Too many incorrect passwords. Try again in 5 minutes.', ['code' => 'bad_password']);
    }
    rfp_respond(403, 'error', 'Incorrect password.', ['code' => 'bad_password']);
}
unset($_SESSION['mgmt_reauth_fails'], $_SESSION['mgmt_reauth_lock_until']);

// -- Generate + overwrite (one transaction) ----------------------------------------
$new_password = faculty_fl_generate_password(12);
$new_hash     = password_hash($new_password, PASSWORD_BCRYPT);

$conn->begin_transaction();
try {
    // Claim the reset: succeeds only while the account is STILL at 5+ wrong passwords.
    if (!pwr_claim_reset($conn, $target_email, $actor_email)) {
        $conn->rollback();
        rfp_respond(409, 'error', 'This account can no longer be reset (the faculty may have signed in, or it was just reset). Reopen the account to see its current state.', ['code' => 'not_eligible']);
    }

    // The new password REPLACES the current one. The faculty must choose their own at next sign-in.
    $upd = $conn->prepare('UPDATE tbl_users SET password = ?, must_change_password = 1 WHERE faculty_id = ?');
    if (!$upd) {
        throw new RuntimeException('UPDATE prepare failed: ' . $conn->error);
    }
    $upd->bind_param('ss', $new_hash, $faculty_id);
    $upd->execute();
    $upd->close();

    // "Stay logged in" tokens are tied to the old password and could not be used any more; remove them.
    try {
        if ($del = $conn->prepare("DELETE FROM tbl_remember_tokens WHERE account_type = 'faculty' AND account_email = ?")) {
            $del->bind_param('s', $target_email);
            $del->execute();
            $del->close();
        }
    } catch (Throwable $e) {
        error_log('[reset-faculty-password] remember tokens skipped: ' . $e->getMessage());   // table may not exist yet
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[reset-faculty-password] ' . $e->getMessage());
    rfp_respond(500, 'error', 'Could not reset this password. Please try again.');
}

// The password itself is never logged.
admin_log_activity($conn, 'faculty', 'Reset the password of ' . $target_name);

rfp_respond(200, 'success', 'A new password was generated for ' . $target_name . '.', [
    'password' => $new_password,
]);
