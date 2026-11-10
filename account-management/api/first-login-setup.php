<?php
ini_set('display_errors', '0');

/**
 * first-login-setup.php
 *
 * Saves the one-time setup a NEW faculty account must finish before it can be used
 * (screen: faculty-setup.php). In ONE statement it:
 *   - replaces the generated password with the person's own,
 *   - saves the essential details (department, mobile number, optional backup email),
 *   - clears tbl_users.must_change_password, which unlocks the account for good.
 * Doing it all at once means there is never a half-finished state to resume from, and the
 * WHERE must_change_password = 1 makes a double click (or a replayed request) harmless.
 *
 * POST: csrf_token, new_password, confirm_password, department, phone, backup_email (optional)
 * JSON: { success, msg, field?, redirect? }
 */

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'msg' => 'Your session has ended. Please sign in again.', 'redirect' => '../../landing-page.php']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'msg' => 'Invalid request.']);
    exit;
}

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../settings/core/faculty-helpers.php';     // phone + department helpers
require_once __DIR__ . '/../core/faculty-first-login.php';
$conn = getDB();

$fail = static function (string $msg, string $field = '', int $code = 422): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'msg' => $msg, 'field' => $field]);
    exit;
};

if (!faculty_fl_ensure_schema($conn)) {
    $fail('Your account could not be set up because the database needs a one-time update. Please contact the administrator.', '', 500);
}

$faculty_id = (string)$_SESSION['faculty_id'];

// ── Current account ─────────────────────────────────────────────────────────
$cur = null;
try {
    $sel = $conn->prepare('SELECT email, password, must_change_password, department, backup_email FROM tbl_users WHERE faculty_id = ? LIMIT 1');
    if ($sel) {
        $sel->bind_param('s', $faculty_id);
        $sel->execute();
        $res = $sel->get_result();
        $cur = ($res && $res->num_rows === 1) ? $res->fetch_assoc() : null;
        $sel->close();
    }
} catch (Throwable $e) {
    error_log('[first-login-setup] load: ' . $e->getMessage());
}
if (!$cur) {
    $fail('We could not load your account. Please sign in again.', '', 404);
}

// Already done (another tab, or a double submit): nothing to do, just carry on to the portal.
if ((int)$cur['must_change_password'] !== 1) {
    echo json_encode(['success' => true, 'already' => true, 'msg' => 'Your account is already set up.', 'redirect' => 'faculty-dashboard.php']);
    exit;
}

// ── Password ────────────────────────────────────────────────────────────────
$new_pw  = (string)($_POST['new_password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');

if ($new_pw === '') {
    $fail('Choose a new password.', 'new_password');
}
if (strlen($new_pw) < 8) {
    $fail('Your password must be at least 8 characters.', 'new_password');
}
if (strlen($new_pw) > 72) {
    $fail('Your password is too long (72 characters at most).', 'new_password');   // bcrypt only reads 72 bytes
}
if (faculty_fl_password_level($new_pw) < 2) {
    $fail('That password is too easy to guess. Avoid common words, repeated characters and sequences like 1234, and mix letters with numbers or symbols.', 'new_password');
}
if ($new_pw !== $confirm) {
    $fail('The two passwords do not match.', 'confirm_password');
}
if (password_verify($new_pw, (string)$cur['password'])) {
    $fail('Your new password must be different from the temporary one you were given.', 'new_password');
}

// ── Details ─────────────────────────────────────────────────────────────────
$department = trim((string)($_POST['department'] ?? ''));
$cur_dept   = trim((string)($cur['department'] ?? ''));
if ($cur_dept !== '') {
    $department = $cur_dept;                       // already on file: it is permanent, keep it
} else {
    if ($department === '') {
        $fail('Select your department.', 'department');
    }
    if (!faculty_department_is_valid($department)) {
        $fail('Please choose a department from the list.', 'department');
    }
    if (!faculty_ensure_department_column($conn)) {
        $fail('Your department could not be saved because the database needs a one-time update. Please contact the administrator.', 'department', 500);
    }
}

// Backup email: optional and never locked (it can be changed any time in Settings).
$backup_in = strtolower(trim((string)($_POST['backup_email'] ?? '')));
$backup    = null;
if ($backup_in !== '') {
    if (strlen($backup_in) > 254 || !filter_var($backup_in, FILTER_VALIDATE_EMAIL)) {
        $fail('Enter a valid backup email address, or leave it empty.', 'backup_email');
    }
    if ($backup_in === strtolower((string)$cur['email'])) {
        $fail('Your backup email must be different from your PUPSync email.', 'backup_email');
    }
    $backup = $backup_in;
}

// ── Save everything at once ──────────────────────────────────────────────────
$hash = password_hash($new_pw, PASSWORD_DEFAULT);
$now  = date('Y-m-d H:i:s');
try {
    $upd = $conn->prepare(
        'UPDATE tbl_users
            SET password = ?, last_password_change = ?, must_change_password = 0,
                department = ?, backup_email = ?
          WHERE faculty_id = ? AND must_change_password = 1'
    );
    if (!$upd) {
        throw new RuntimeException('prepare failed: ' . $conn->error);
    }
    $upd->bind_param('sssss', $hash, $now, $department, $backup, $faculty_id);
    $upd->execute();
    $changed = $upd->affected_rows;
    $upd->close();
} catch (Throwable $e) {
    error_log('[first-login-setup] save: ' . $e->getMessage());
    $fail('We could not save your details. Please try again.', '', 500);
}
if ($changed !== 1) {
    // Lost a race with another tab that finished first: the account is unlocked already.
    echo json_encode(['success' => true, 'already' => true, 'msg' => 'Your account is already set up.', 'redirect' => 'faculty-dashboard.php']);
    exit;
}

// A new password invalidates every "remember me" token of this account (they are tied to the old
// password hash), so renew this browser's token if it had one, and start a fresh session id.
try {
    $had_remember = !empty($_COOKIE[REMEMBER_ME_COOKIE]);
    session_regenerate_id(true);
    if ($had_remember) {
        remember_me_issue($conn, 'faculty', (string)$cur['email'], $hash, (int)($_SESSION['login_time'] ?? time()));
    }
} catch (Throwable $e) {
    error_log('[first-login-setup] remember-me renewal: ' . $e->getMessage());
}

echo json_encode(['success' => true, 'msg' => 'You are all set.', 'redirect' => 'faculty-dashboard.php']);
exit;
