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

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

// ── Local JSON helper ─────────────────────────────────────────────────────────
function send_json($code, $status, $message)
{
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// ── Input collection ──────────────────────────────────────────────────────────
$faculty_id     = trim($_POST['faculty_id'] ?? '');
$pupsync_email  = strtolower(trim($_POST['pupsync_email'] ?? ''));
$backup_email   = strtolower(trim($_POST['backup_email'] ?? ''));
$first_name     = trim($_POST['first_name'] ?? '');
$last_name      = trim($_POST['last_name'] ?? '');
$is_org_adviser = (($_POST['is_org_adviser'] ?? '0') === '1') ? 1 : 0;
$allow_org_borrowing = (($_POST['allow_org_borrowing'] ?? '0') === '1') ? 1 : 0;
$organization_id_raw = intval($_POST['organization_id'] ?? 0);

// ── Validation: faculty_id ────────────────────────────────────────────────────
if ($faculty_id === '' || strlen($faculty_id) > 255) {
    send_json(422, 'error', 'Faculty account not found.');
}

// ── Confirm the account exists ────────────────────────────────────────────────
$exists_stmt = $conn->prepare("SELECT faculty_id FROM tbl_users WHERE faculty_id = ? LIMIT 1");
if (!$exists_stmt) {
    error_log('[update-faculty-account] existence check prepare failed: ' . $conn->error);
    send_json(500, 'error', 'Could not save changes. Please try again.');
}
$exists_stmt->bind_param('s', $faculty_id);
$exists_stmt->execute();
$exists_stmt->store_result();
if ($exists_stmt->num_rows === 0) {
    $exists_stmt->close();
    send_json(404, 'error', 'Faculty account not found.');
}
$exists_stmt->close();

// ── Validation: pupsync_email ─────────────────────────────────────────────────
if ($pupsync_email === '' || strlen($pupsync_email) > 254 || !filter_var($pupsync_email, FILTER_VALIDATE_EMAIL)) {
    send_json(422, 'error', 'A valid PUPSync email is required.');
}

// ── Validation: backup_email (optional, but must be valid if provided) ───────
if ($backup_email !== '' && (strlen($backup_email) > 254 || !filter_var($backup_email, FILTER_VALIDATE_EMAIL))) {
    send_json(422, 'error', 'Backup email is not a valid email address.');
}

// ── Validation: first_name / last_name ────────────────────────────────────────
if ($first_name === '' || strlen($first_name) > 150) {
    send_json(422, 'error', 'First name is required.');
}
if ($last_name === '' || strlen($last_name) > 100) {
    send_json(422, 'error', 'Last name is required.');
}

// ── Duplicate email check — exclude this faculty's own current row ───────────
$dup_stmt = $conn->prepare("SELECT faculty_id FROM tbl_users WHERE email = ? AND faculty_id != ? LIMIT 1");
if (!$dup_stmt) {
    error_log('[update-faculty-account] Duplicate check prepare failed: ' . $conn->error);
    send_json(500, 'error', 'Could not save changes. Please try again.');
}
$dup_stmt->bind_param('ss', $pupsync_email, $faculty_id);
$dup_stmt->execute();
$dup_stmt->store_result();
if ($dup_stmt->num_rows > 0) {
    $dup_stmt->close();
    send_json(409, 'error', 'Another faculty account already uses this email.');
}
$dup_stmt->close();

// ── Adviser + organization validation ─────────────────────────────────────────
$organization_id = null;
if ($is_org_adviser === 1) {
    if ($organization_id_raw <= 0) {
        send_json(422, 'error', 'An organization must be selected for an adviser.');
    }
    $org_stmt = $conn->prepare("SELECT id FROM tbl_organizations WHERE id = ? LIMIT 1");
    if (!$org_stmt) {
        error_log('[update-faculty-account] Organization check prepare failed: ' . $conn->error);
        send_json(500, 'error', 'Could not save changes. Please try again.');
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
// If is_org_adviser is 0, organization_id remains NULL — same convention as create-faculty-account.php

// ── Fullname concatenation (first + last only — this edit form has no middle
//    name field; any existing middle name lives inside the row's "first name"
//    portion already, matching how the JS splits fullname on open: everything
//    but the last token is treated as first name) ─────────────────────────────
$fullname = trim($first_name . ' ' . $last_name);

$role = ($is_org_adviser === 1) ? 'Organization Adviser' : 'Regular Faculty';
$backup_val = ($backup_email === '') ? null : $backup_email;

// ── Update ───────────────────────────────────────────────────────────────────
$upd_stmt = $conn->prepare(
    "UPDATE tbl_users
        SET fullname = ?, email = ?, backup_email = ?, role = ?,
            is_org_adviser = ?, organization_id = ?, allow_org_borrowing = ?
      WHERE faculty_id = ?"
);
if (!$upd_stmt) {
    error_log('[update-faculty-account] UPDATE prepare failed: ' . $conn->error);
    send_json(500, 'error', 'Could not save changes. Please try again.');
}
$upd_stmt->bind_param(
    'ssssiiis',
    $fullname,
    $pupsync_email,
    $backup_val,
    $role,
    $is_org_adviser,
    $organization_id,
    $allow_org_borrowing,
    $faculty_id
);
if (!$upd_stmt->execute()) {
    error_log('[update-faculty-account] UPDATE execute failed: ' . $upd_stmt->error);
    $upd_stmt->close();
    send_json(500, 'error', 'Could not save changes. Please try again.');
}
$upd_stmt->close();

// ── Success ──────────────────────────────────────────────────────────────────
http_response_code(200);
echo json_encode([
    'status'  => 'success',
    'message' => 'Faculty account updated successfully.'
]);
exit;
