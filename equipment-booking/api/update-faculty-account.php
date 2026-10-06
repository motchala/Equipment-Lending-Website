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

// ── Super Admin only (the Faculty tab is hidden from plain Admins; enforce it here too)
if (!admin_is_super_admin($conn)) {
    send_json(403, 'error', 'Only a Super Admin can edit faculty accounts.');
}

// ── Input collection ──────────────────────────────────────────────────────────
$faculty_id     = trim($_POST['faculty_id'] ?? '');
$pupsync_email  = strtolower(trim($_POST['pupsync_email'] ?? ''));
$backup_email   = strtolower(trim($_POST['backup_email'] ?? ''));
$first_name     = trim($_POST['first_name'] ?? '');
$last_name      = trim($_POST['last_name'] ?? '');
$is_org_adviser = (($_POST['is_org_adviser'] ?? '0') === '1') ? 1 : 0;
// Org privileges are automatic: being an Organization Adviser is what grants them
// (any posted allow_org_borrowing value is ignored). The Faculty ID is never
// changed here - faculty set it themselves and admins cannot edit it.
$allow_org_borrowing = $is_org_adviser;
$organization_id_raw = intval($_POST['organization_id'] ?? 0);

// ── Validation: faculty_id ────────────────────────────────────────────────────
if ($faculty_id === '' || strlen($faculty_id) > 255) {
    send_json(422, 'error', 'Faculty account not found.');
}

// -- Confirm the account exists (and remember what it is today) ---------------
$exists_stmt = $conn->prepare("SELECT email, role, organization_id FROM tbl_users WHERE faculty_id = ? LIMIT 1");
if (!$exists_stmt) {
    error_log('[update-faculty-account] existence check prepare failed: ' . $conn->error);
    send_json(500, 'error', 'Could not save changes. Please try again.');
}
$exists_stmt->bind_param('s', $faculty_id);
$exists_stmt->execute();
$exists_res = $exists_stmt->get_result();
$current = $exists_res ? $exists_res->fetch_assoc() : null;
$exists_stmt->close();
if (!$current) {
    send_json(404, 'error', 'Faculty account not found.');
}
$current_email = strtolower((string)$current['email']);
$was_adviser_of = ($current['role'] === 'Organization Adviser' && $current['organization_id'] !== null)
    ? (int)$current['organization_id'] : 0;

// ── Validation: pupsync_email ─────────────────────────────────────────────────
if ($pupsync_email === '' || strlen($pupsync_email) > 254 || !filter_var($pupsync_email, FILTER_VALIDATE_EMAIL)) {
    send_json(422, 'error', 'A valid PUPSync email is required.');
}
// A new or changed email must end in @pupsync.edu. An account whose existing email was set
// before this rule keeps working: it is only checked when someone actually changes it.
if ($pupsync_email !== $current_email && faculty_email_normalize($pupsync_email) === null) {
    send_json(422, 'error', 'Use a PUPSync email ending in @' . FACULTY_EMAIL_DOMAIN . '.');
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

// -- Adviser + organization -----------------------------------------------------
// One adviser per organization (checked below under a lock). If this account is already the
// adviser of the organization it keeps, nothing is re-checked, so an older duplicate never
// blocks an unrelated edit.
$organization_id = null;
if ($is_org_adviser === 1) {
    if ($organization_id_raw <= 0) {
        send_json(422, 'error', 'An organization must be selected for an adviser.');
    }
    $organization_id = $organization_id_raw;
}
// If is_org_adviser is 0, organization_id remains NULL (same convention as create-faculty-account.php)

// ── Fullname concatenation (first + last only — this edit form has no middle
//    name field; any existing middle name lives inside the row's "first name"
//    portion already, matching how the JS splits fullname on open: everything
//    but the last token is treated as first name) ─────────────────────────────
$fullname = trim($first_name . ' ' . $last_name);

$role = ($is_org_adviser === 1) ? 'Organization Adviser' : 'Regular Faculty';
$backup_val = ($backup_email === '') ? null : $backup_email;

// -- Update -----------------------------------------------------------------------
$in_tx = false;
try {
    if ($is_org_adviser === 1) {
        $conn->begin_transaction();
        $in_tx = true;
        if (!orgs_lock($conn, $organization_id)) {
            $conn->rollback();
            send_json(422, 'error', 'An organization must be selected for an adviser.');
        }
        if ($was_adviser_of !== $organization_id) {
            $conflict = orgs_adviser_conflict($conn, $organization_id, $faculty_id);
            if ($conflict) {
                $conn->rollback();
                send_json(409, 'error', orgs_adviser_conflict_message($conflict));
            }
        }
    }

    $upd_stmt = $conn->prepare(
        "UPDATE tbl_users
            SET fullname = ?, email = ?, backup_email = ?, role = ?,
                is_org_adviser = ?, organization_id = ?, allow_org_borrowing = ?
          WHERE faculty_id = ?"
    );
    if (!$upd_stmt) {
        throw new RuntimeException('UPDATE prepare failed: ' . $conn->error);
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
        $err = $upd_stmt->error;
        $upd_stmt->close();
        throw new RuntimeException('UPDATE execute failed: ' . $err);
    }
    $upd_stmt->close();

    if ($in_tx) {
        $conn->commit();
    }
} catch (Throwable $e) {
    if ($in_tx) {
        $conn->rollback();
    }
    error_log('[update-faculty-account] ' . $e->getMessage());
    send_json(500, 'error', 'Could not save changes. Please try again.');
}

// ── Success ──────────────────────────────────────────────────────────────────
http_response_code(200);
echo json_encode([
    'status'  => 'success',
    'message' => 'Faculty account updated successfully.'
]);
exit;
