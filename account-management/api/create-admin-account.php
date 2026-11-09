<?php
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

// Admin-role check — only logged-in admins can create other admins
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/csrf.php';
csrf_verify();

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

// ── Ensure tbl_accounts has the new columns (graceful migration for
//    existing installs that haven't re-imported the SQL file yet).
//    Uses SHOW COLUMNS + conditional ALTER rather than "ADD COLUMN IF
//    NOT EXISTS" — that syntax needs a fairly recent MySQL/MariaDB,
//    and this same SHOW COLUMNS pattern is already proven to work
//    against this project's actual database (see last_login in
//    landing-page.php). ───────────────────────────────────────────
$_existing_cols = [];
$_col_result = $conn->query("SHOW COLUMNS FROM tbl_accounts");
if ($_col_result) {
    while ($_col_row = $_col_result->fetch_assoc()) {
        $_existing_cols[] = $_col_row['Field'];
    }
}
if (!in_array('id', $_existing_cols, true)) {
    @$conn->query("ALTER TABLE tbl_accounts ADD COLUMN `id` int(11) NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`)");
}
if (!in_array('role', $_existing_cols, true)) {
    @$conn->query("ALTER TABLE tbl_accounts ADD COLUMN `role` enum('Super Admin','Admin') NOT NULL DEFAULT 'Admin' AFTER `password`");
    @$conn->query("UPDATE tbl_accounts SET role = 'Super Admin' WHERE email = 'main@admin.edu'");
}
if (!in_array('created_at', $_existing_cols, true)) {
    @$conn->query("ALTER TABLE tbl_accounts ADD COLUMN `created_at` datetime DEFAULT NULL AFTER `role`");
}

// Only a Super Admin may create new admin accounts. Checked after the
// migration above so $_SESSION['admin_role'] (set at login from the
// now-guaranteed-to-exist role column) can be trusted.
$_requester_role = ($_SESSION['admin_role'] ?? '') === 'Super Admin' ? 'Super Admin' : 'Admin';
if ($_requester_role !== 'Super Admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Only a Super Admin can create admin accounts.']);
    exit;
}

// ── Helper ───────────────────────────────────────────────────────────────────
function send_json(int $code, string $status, string $message): void
{
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// ── Credential rules for NEW admin accounts ──────────────────────────────────
// Keep in sync with admEmailError() / admPasswordInfo() in admin-dashboard.js (the dialog's live checks).
const ADMIN_EMAIL_DOMAIN = '@admin.edu';

/** Returns an error message, or null when the email is acceptable. $email is already trimmed + lower-cased. */
function admin_email_error(string $email): ?string
{
    if ($email === '') {
        return 'A valid admin email is required.';
    }
    if (!str_ends_with($email, ADMIN_EMAIL_DOMAIN)) {
        return 'Admin emails must end in ' . ADMIN_EMAIL_DOMAIN . ' (e.g. name' . ADMIN_EMAIL_DOMAIN . ').';
    }
    $local = substr($email, 0, -strlen(ADMIN_EMAIL_DOMAIN));
    if ($local === '') {
        return 'Add a name before ' . ADMIN_EMAIL_DOMAIN . ' (e.g. name' . ADMIN_EMAIL_DOMAIN . ').';
    }
    if (strlen($local) > 64) {
        return 'The part before ' . ADMIN_EMAIL_DOMAIN . ' can be at most 64 characters.';
    }
    // Letters, numbers, dot, hyphen, underscore; must start and end with a letter or number; no "..".
    if (!preg_match('/^[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?$/', $local) || strpos($local, '..') !== false) {
        return 'Before ' . ADMIN_EMAIL_DOMAIN . ', use only letters, numbers, dots, hyphens or underscores (start and end with a letter or number).';
    }
    return null;
}

/** Returns an error message, or null when the password is acceptable. */
function admin_password_error(string $pw): ?string
{
    if ($pw === '') {
        return 'Password is required.';
    }
    if (strlen($pw) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (strlen($pw) > 72) { // bcrypt silently ignores anything past 72 bytes
        return 'Password can be at most 72 characters.';
    }
    $missing = [];
    if (!preg_match('/[A-Z]/', $pw))          $missing[] = 'an uppercase letter';
    if (!preg_match('/[a-z]/', $pw))          $missing[] = 'a lowercase letter';
    if (!preg_match('/[0-9]/', $pw))          $missing[] = 'a number';
    if (!preg_match('/[^A-Za-z0-9\s]/', $pw)) $missing[] = 'a special character (for example ! @ # $ %)';
    if ($missing) {
        return 'Password must include ' . implode(', ', $missing) . '.';
    }
    return null;
}

// ── Inputs ───────────────────────────────────────────────────────────────────
$full_name      = trim($_POST['full_name'] ?? '');
$email          = strtolower(trim($_POST['email'] ?? ''));
$role           = trim($_POST['role'] ?? 'Admin');
$password_raw   = $_POST['password'] ?? '';
$confirm_raw    = $_POST['confirm_password'] ?? '';

// ── Validate role ─────────────────────────────────────────────────────────────
$allowed_roles = ['Super Admin', 'Admin'];
if (!in_array($role, $allowed_roles, true)) {
    send_json(422, 'error', 'Invalid role selected.');
}

// ── Validate full name ────────────────────────────────────────────────────────
if ($full_name === '' || strlen($full_name) > 255) {
    send_json(422, 'error', 'Full name is required.');
}

// ── Validate email ────────────────────────────────────────────────────────────
if (($email_err = admin_email_error($email)) !== null) {
    send_json(422, 'error', $email_err);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_json(422, 'error', 'A valid admin email is required.');
}

// ── Validate password ─────────────────────────────────────────────────────────
if (($pw_err = admin_password_error($password_raw)) !== null) {
    send_json(422, 'error', $pw_err);
}
if ($password_raw !== $confirm_raw) {
    send_json(422, 'error', 'Passwords do not match.');
}

// ── Seat limit: max 5 admin accounts (including the existing one) ─────────────
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_accounts");
if (!$count_stmt) {
    error_log('[create-admin-account] count prepare failed: ' . $conn->error);
    send_json(500, 'error', 'Could not create account. Please try again.');
}
$count_stmt->execute();
$count_stmt->bind_result($current_count);
$count_stmt->fetch();
$count_stmt->close();

if ($current_count >= 5) {
    send_json(409, 'error', 'The maximum of 5 admin accounts has been reached. Remove an existing account before adding a new one.');
}

// ── Duplicate email check ─────────────────────────────────────────────────────
$dup_stmt = $conn->prepare("SELECT email FROM tbl_accounts WHERE email = ? LIMIT 1");
if (!$dup_stmt) {
    error_log('[create-admin-account] dup check prepare failed: ' . $conn->error);
    send_json(500, 'error', 'Could not create account. Please try again.');
}
$dup_stmt->bind_param('s', $email);
$dup_stmt->execute();
$dup_stmt->store_result();
if ($dup_stmt->num_rows > 0) {
    $dup_stmt->close();
    send_json(409, 'error', 'An admin account with this email already exists.');
}
$dup_stmt->close();

// ── Hash password & insert ────────────────────────────────────────────────────
$password_hash = password_hash($password_raw, PASSWORD_BCRYPT);
$now = date('Y-m-d H:i:s');

// Seat number: Admin 1 is the Super Admin, so a new admin gets the lowest free number from 2 to 5.
// (The unique index on admin_no makes two simultaneous creates retry instead of sharing a number.)
require_once __DIR__ . '/../core/admin-numbers.php';
$has_admin_no = admin_no_ensure($conn);
$admin_no = 0;
$new_id = 0;
for ($attempt = 0; $attempt < 3 && $new_id === 0; $attempt++) {
    if ($has_admin_no) {
        $admin_no = admin_no_next($conn);
        if ($admin_no < 1) {
            send_json(409, 'error', 'All 5 admin numbers are in use. Remove an existing account before adding a new one.');
        }
        $ins_stmt = $conn->prepare(
            "INSERT INTO tbl_accounts (fullName, email, password, role, admin_no, created_at) VALUES (?, ?, ?, ?, ?, ?)"
        );
        if ($ins_stmt) {
            $ins_stmt->bind_param('ssssis', $full_name, $email, $password_hash, $role, $admin_no, $now);
        }
    } else {
        // admin_no column unavailable (no ALTER permission): still create the account, just unnumbered.
        $ins_stmt = $conn->prepare(
            "INSERT INTO tbl_accounts (fullName, email, password, role, created_at) VALUES (?, ?, ?, ?, ?)"
        );
        if ($ins_stmt) {
            $ins_stmt->bind_param('sssss', $full_name, $email, $password_hash, $role, $now);
        }
    }
    if (!$ins_stmt) {
        error_log('[create-admin-account] INSERT prepare failed: ' . $conn->error);
        send_json(500, 'error', 'Could not create account. Please try again.');
    }
    $errno = 0;
    try {
        if ($ins_stmt->execute()) {
            $new_id = (int)$ins_stmt->insert_id;
        } else {
            $errno = (int)$ins_stmt->errno;
        }
    } catch (mysqli_sql_exception $e) {   // PHP 8.1+ throws instead of returning false
        $errno = (int)$e->getCode();
    }
    $ins_stmt->close();
    if ($new_id === 0) {
        error_log('[create-admin-account] INSERT execute failed (errno ' . $errno . ')');
        if (!($errno === 1062 && $has_admin_no)) {   // 1062 = someone just took that number: pick again
            send_json(500, 'error', 'Could not create account. Please try again.');
        }
    }
}
if ($new_id === 0) {
    send_json(500, 'error', 'Could not create account. Please try again.');
}

require_once __DIR__ . '/../../shared/core/activity-logger.php';
admin_log_activity($conn, 'account', 'Created admin account for ' . $full_name . ($admin_no > 0 ? ' (Admin ' . $admin_no . ')' : ''));

// ── Success ───────────────────────────────────────────────────────────────────
$remaining = 5 - ($current_count + 1);
http_response_code(201);
echo json_encode([
    'status'       => 'success',
    'message'      => "Admin account for {$full_name} created successfully.",
    'id'           => $new_id,
    'admin_no'     => $admin_no,
    'accounts_remaining' => $remaining,
]);
exit;
