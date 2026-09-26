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
//    existing installs that haven't re-imported the SQL file yet) ───────────
foreach ([
    "ALTER TABLE tbl_accounts ADD COLUMN IF NOT EXISTS `id` int(11) NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY IF NOT EXISTS (`id`)",
    "ALTER TABLE tbl_accounts ADD COLUMN IF NOT EXISTS `role` enum('Super Admin','Admin') NOT NULL DEFAULT 'Admin' AFTER `password`",
    "ALTER TABLE tbl_accounts ADD COLUMN IF NOT EXISTS `created_at` datetime DEFAULT NULL AFTER `role`",
    "ALTER TABLE tbl_accounts ADD UNIQUE IF NOT EXISTS `email` (`email`)",
] as $ddl) {
    @$conn->query($ddl);
}

// ── Helper ───────────────────────────────────────────────────────────────────
function send_json(int $code, string $status, string $message): void {
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
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
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_json(422, 'error', 'A valid admin email is required.');
}
// Enforce @admin.edu convention
if (!str_ends_with($email, '@admin.edu')) {
    send_json(422, 'error', 'Admin emails must use the @admin.edu convention (e.g. name@admin.edu).');
}

// ── Validate password ─────────────────────────────────────────────────────────
if ($password_raw === '') {
    send_json(422, 'error', 'Password is required.');
}
if (strlen($password_raw) < 8) {
    send_json(422, 'error', 'Password must be at least 8 characters.');
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

$ins_stmt = $conn->prepare(
    "INSERT INTO tbl_accounts (fullName, email, password, role, created_at) VALUES (?, ?, ?, ?, ?)"
);
if (!$ins_stmt) {
    error_log('[create-admin-account] INSERT prepare failed: ' . $conn->error);
    send_json(500, 'error', 'Could not create account. Please try again.');
}
$ins_stmt->bind_param('sssss', $full_name, $email, $password_hash, $role, $now);
if (!$ins_stmt->execute()) {
    error_log('[create-admin-account] INSERT execute failed: ' . $conn->error);
    send_json(500, 'error', 'Could not create account. Please try again.');
}
$ins_stmt->close();

// ── Success ───────────────────────────────────────────────────────────────────
$remaining = 5 - ($current_count + 1);
http_response_code(201);
echo json_encode([
    'status'       => 'success',
    'message'      => "Admin account for {$full_name} created successfully.",
    'accounts_remaining' => $remaining,
]);
exit;
