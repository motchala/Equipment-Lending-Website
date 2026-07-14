<?php
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

// Admin-role check
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

// ── Local JSON helper ─────────────────────────────────────────────────────────
function send_json($code, $status, $message) {
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// ── Input collection ──────────────────────────────────────────────────────────
$pupsync_email = strtolower(trim($_POST['pupsync_email'] ?? ''));
$backup_email  = strtolower(trim($_POST['backup_email'] ?? ''));
$first_name    = trim($_POST['first_name'] ?? '');
$middle_name   = trim($_POST['middle_name'] ?? '');
$last_name     = trim($_POST['last_name'] ?? '');
$password_raw  = $_POST['password'] ?? '';
$confirm_raw   = $_POST['confirm_password'] ?? '';
$is_org_adviser = (($_POST['is_org_adviser'] ?? '0') === '1') ? 1 : 0;
$allow_org_borrowing = (($_POST['allow_org_borrowing'] ?? '0') === '1') ? 1 : 0;
$organization_id_raw = intval($_POST['organization_id'] ?? 0);

// ── Validation: pupsync_email ─────────────────────────────────────────────────
if ($pupsync_email === '' || strlen($pupsync_email) > 254 || !filter_var($pupsync_email, FILTER_VALIDATE_EMAIL)) {
    send_json(422, 'error', 'PUPSync email is required.');
}

// ── Validation: first_name ────────────────────────────────────────────────────
if ($first_name === '' || strlen($first_name) > 100) {
    send_json(422, 'error', 'First name is required.');
}

// ── Validation: last_name ─────────────────────────────────────────────────────
if ($last_name === '' || strlen($last_name) > 100) {
    send_json(422, 'error', 'Last name is required.');
}

// ── Validation: password ──────────────────────────────────────────────────────
if ($password_raw === '') {
    send_json(422, 'error', 'Password is required.');
}
if (strlen($password_raw) < 8) {
    send_json(422, 'error', 'Password must be at least 8 characters.');
}
if ($password_raw !== $confirm_raw) {
    send_json(422, 'error', 'Passwords do not match.');
}

// ── Duplicate email check ─────────────────────────────────────────────────────
$dup_stmt = $conn->prepare("SELECT faculty_id FROM tbl_users WHERE email = ? LIMIT 1");
if (!$dup_stmt) {
    error_log('[create-faculty-account] Duplicate check prepare failed: ' . $conn->error);
    send_json(500, 'error', 'Could not create account. Please try again.');
}
$dup_stmt->bind_param('s', $pupsync_email);
$dup_stmt->execute();
$dup_stmt->store_result();
if ($dup_stmt->num_rows > 0) {
    $dup_stmt->close();
    send_json(409, 'error', 'A faculty account with this email already exists.');
}
$dup_stmt->close();

// ── Adviser + organization validation ─────────────────────────────────────────
$organization_id = null;
if ($is_org_adviser === 1) {
    if ($organization_id_raw <= 0) {
        send_json(422, 'error', 'An organization must be selected for an adviser.');
    }
    // Confirm organization exists
    $org_stmt = $conn->prepare("SELECT id FROM tbl_organizations WHERE id = ? LIMIT 1");
    if (!$org_stmt) {
        error_log('[create-faculty-account] Organization check prepare failed: ' . $conn->error);
        send_json(500, 'error', 'Could not create account. Please try again.');
    }
    $org_stmt->bind_param('i', $organization_id_raw);
    $org_stmt->execute();
    $org_stmt->store_result();
    if ($org_stmt->num_rows === 0) {
        $org_stmt->close();
        send_json(422, 'error', 'An organization must be selected for an adviser.');
    }
    $org_stmt->close();
    $organization_id = $organization_id_raw;
}
// If is_org_adviser is 0, organization_id remains NULL (Requirement 4.8)

// ── faculty_id generation (inside transaction) ───────────────────────────────
$year = date('Y');
$pattern = $year . '-%-BN-0';

$conn->begin_transaction();

$seq_stmt = $conn->prepare("SELECT faculty_id FROM tbl_users WHERE faculty_id LIKE ? ORDER BY faculty_id DESC LIMIT 1 FOR UPDATE");
if (!$seq_stmt) {
    $conn->rollback();
    error_log('[create-faculty-account] seq lookup prepare failed: ' . $conn->error);
    send_json(500, 'error', 'Could not create account. Please try again.');
}
$seq_stmt->bind_param('s', $pattern);
if (!$seq_stmt->execute()) {
    $conn->rollback();
    error_log('[create-faculty-account] seq lookup execute failed: ' . $conn->error);
    send_json(500, 'error', 'Could not create account. Please try again.');
}
$seq_result = $seq_stmt->get_result();
$seq_row = $seq_result->fetch_assoc();
$seq_stmt->close();

if ($seq_row === null) {
    $seq = 1;
} else {
    $parts = explode('-', $seq_row['faculty_id']);
    $seq = intval($parts[1]) + 1;
}

$faculty_id = $year . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT) . '-BN-0';

// ── Fullname concatenation ───────────────────────────────────────────────────
if ($middle_name === '') {
    $fullname = $first_name . ' ' . $last_name;
} else {
    $fullname = $first_name . ' ' . $middle_name . ' ' . $last_name;
}

// ── Password & role ──────────────────────────────────────────────────────────
$password_hash = password_hash($password_raw, PASSWORD_BCRYPT);
$role = ($is_org_adviser === 1) ? 'Organization Adviser' : 'Regular Faculty';
$backup_val = ($backup_email === '') ? null : $backup_email;

// ── Insert ───────────────────────────────────────────────────────────────────
$ins_stmt = $conn->prepare("INSERT INTO tbl_users (fullname, faculty_id, email, backup_email, password, role, is_org_adviser, organization_id, allow_org_borrowing) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$ins_stmt) {
    $conn->rollback();
    error_log('[create-faculty-account] INSERT prepare failed: ' . $conn->error);
    send_json(500, 'error', 'Could not create account. Please try again.');
}
$ins_stmt->bind_param('ssssssiii', $fullname, $faculty_id, $pupsync_email, $backup_val, $password_hash, $role, $is_org_adviser, $organization_id, $allow_org_borrowing);
if (!$ins_stmt->execute()) {
    $conn->rollback();
    error_log('[create-faculty-account] INSERT execute failed: ' . $conn->error);
    send_json(500, 'error', 'Could not create account. Please try again.');
}
$ins_stmt->close();

$conn->commit();

// ── Success ──────────────────────────────────────────────────────────────────
http_response_code(201);
echo json_encode([
    'status' => 'success',
    'faculty_id' => $faculty_id,
    'message' => 'Faculty account created successfully.'
]);
exit;
