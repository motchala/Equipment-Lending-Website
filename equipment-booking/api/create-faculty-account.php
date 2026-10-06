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
function send_json($code, $status, $message)
{
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// ── Super Admin only (the Faculty tab is hidden from plain Admins; enforce it here too)
if (!admin_is_super_admin($conn)) {
    send_json(403, 'error', 'Only a Super Admin can create faculty accounts.');
}

// -- Input collection --------------------------------------------------------
$pupsync_email_raw = (string)($_POST['pupsync_email'] ?? '');
$faculty_id_raw    = (string)($_POST['faculty_id'] ?? '');
$first_name    = trim($_POST['first_name'] ?? '');
$last_name     = trim($_POST['last_name'] ?? '');
$password_raw  = $_POST['password'] ?? '';
$confirm_raw   = $_POST['confirm_password'] ?? '';
$is_org_adviser = (($_POST['is_org_adviser'] ?? '0') === '1') ? 1 : 0;
$organization_id_raw = intval($_POST['organization_id'] ?? 0);

// -- Validation: PUPSync email (must end in @pupsync.edu) ---------------------
if (trim($pupsync_email_raw) === '') {
    send_json(422, 'error', 'PUPSync email is required.');
}
$pupsync_email = faculty_email_normalize($pupsync_email_raw);
if ($pupsync_email === null) {
    send_json(422, 'error', 'Use a PUPSync email ending in @' . FACULTY_EMAIL_DOMAIN . '.');
}

// -- Validation: Faculty ID (entered here; permanent, admins cannot edit it later) --
if (trim($faculty_id_raw) === '') {
    send_json(422, 'error', 'Faculty ID is required.');
}
$faculty_id = faculty_id_normalize($faculty_id_raw);
if ($faculty_id === null) {
    send_json(422, 'error', 'Enter a valid Faculty ID: letters, numbers and hyphens only (for example 2023-00123-BN-0).');
}

// -- Validation: first_name ---------------------------------------------------
if ($first_name === '' || strlen($first_name) > 100) {
    send_json(422, 'error', 'First name is required.');
}

// -- Validation: last_name ----------------------------------------------------
if ($last_name === '' || strlen($last_name) > 100) {
    send_json(422, 'error', 'Last name is required.');
}

// -- Validation: password -----------------------------------------------------
if ($password_raw === '') {
    send_json(422, 'error', 'Password is required.');
}
if (strlen($password_raw) < 8) {
    send_json(422, 'error', 'Password must be at least 8 characters.');
}
if ($password_raw !== $confirm_raw) {
    send_json(422, 'error', 'Passwords do not match.');
}

// -- Duplicate checks: email and Faculty ID -------------------------------------
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

$dup_stmt = $conn->prepare("SELECT 1 FROM tbl_users WHERE faculty_id = ? LIMIT 1");
if (!$dup_stmt) {
    error_log('[create-faculty-account] Faculty ID check prepare failed: ' . $conn->error);
    send_json(500, 'error', 'Could not create account. Please try again.');
}
$dup_stmt->bind_param('s', $faculty_id);
$dup_stmt->execute();
$dup_stmt->store_result();
if ($dup_stmt->num_rows > 0) {
    $dup_stmt->close();
    send_json(409, 'error', 'That Faculty ID is already registered to another account.');
}
$dup_stmt->close();

// -- Adviser + organization -----------------------------------------------------
// The organization must exist and (checked below, under a lock) must not already have an adviser.
$organization_id = null;
if ($is_org_adviser === 1) {
    if ($organization_id_raw <= 0) {
        send_json(422, 'error', 'An organization must be selected for an adviser.');
    }
    $organization_id = $organization_id_raw;
}
// If is_org_adviser is 0, organization_id remains NULL.

// -- Full name (first + last) ---------------------------------------------------
$fullname = $first_name . ' ' . $last_name;

// -- Password & role --------------------------------------------------------------
$password_hash = password_hash($password_raw, PASSWORD_BCRYPT);
$role = ($is_org_adviser === 1) ? 'Organization Adviser' : 'Regular Faculty';
// Org privileges are automatic, and identical for every organization: being an
// Organization Adviser is what grants them.
$allow_org_borrowing = $is_org_adviser;
$backup_val = null;   // the backup email is no longer collected here

// -- Insert -----------------------------------------------------------------------
$in_tx = false;
try {
    if ($is_org_adviser === 1) {
        // One adviser per organization. Lock the organization row first so two admins cannot
        // both pass the check and give the same organization two advisers.
        $conn->begin_transaction();
        $in_tx = true;
        if (!orgs_lock($conn, $organization_id)) {
            $conn->rollback();
            send_json(422, 'error', 'An organization must be selected for an adviser.');
        }
        $conflict = orgs_adviser_conflict($conn, $organization_id, '');
        if ($conflict) {
            $conn->rollback();
            send_json(409, 'error', orgs_adviser_conflict_message($conflict));
        }
    }

    $inserted = false;
    $errno = 0;
    $errmsg = '';
    $ins_stmt = $conn->prepare("INSERT INTO tbl_users (fullname, faculty_id, email, backup_email, password, role, is_org_adviser, organization_id, allow_org_borrowing) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$ins_stmt) {
        throw new RuntimeException('INSERT prepare failed: ' . $conn->error);
    }
    $ins_stmt->bind_param('ssssssiii', $fullname, $faculty_id, $pupsync_email, $backup_val, $password_hash, $role, $is_org_adviser, $organization_id, $allow_org_borrowing);
    if ($ins_stmt->execute()) {
        $inserted = true;
    } else {
        $errno  = (int)$ins_stmt->errno;
        $errmsg = (string)$ins_stmt->error;
    }
    $ins_stmt->close();

    if (!$inserted) {
        if ($in_tx) {
            $conn->rollback();
        }
        if ($errno === 1062) {   // lost a race with another admin on the unique email / Faculty ID
            if (stripos($errmsg, 'email') !== false) {
                send_json(409, 'error', 'A faculty account with this email already exists.');
            }
            send_json(409, 'error', 'That Faculty ID is already registered to another account.');
        }
        error_log('[create-faculty-account] INSERT failed: ' . $errmsg);
        send_json(500, 'error', 'Could not create account. Please try again.');
    }

    if ($in_tx) {
        $conn->commit();
    }
} catch (mysqli_sql_exception $e) {
    // mysqli throws instead of returning false on newer PHP versions
    if ($in_tx) {
        $conn->rollback();
    }
    if ((int)$e->getCode() === 1062) {
        if (stripos($e->getMessage(), 'email') !== false) {
            send_json(409, 'error', 'A faculty account with this email already exists.');
        }
        send_json(409, 'error', 'That Faculty ID is already registered to another account.');
    }
    error_log('[create-faculty-account] ' . $e->getMessage());
    send_json(500, 'error', 'Could not create account. Please try again.');
} catch (Throwable $e) {
    if ($in_tx) {
        $conn->rollback();
    }
    error_log('[create-faculty-account] ' . $e->getMessage());
    send_json(500, 'error', 'Could not create account. Please try again.');
}

// -- Success ------------------------------------------------------------------------
http_response_code(201);
echo json_encode([
    'status' => 'success',
    'faculty_id' => $faculty_id,
    'message' => 'Faculty account created successfully.'
]);
exit;
