<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// csp vulnerability fix: generate a nonce for inline scripts/styles (future-proofing,
// matches the pattern used on landing-page.php / faculty-dashboard.php / student-dashboard.php)
// and send the Content-Security-Policy header. admin-dashboard.php currently has no inline
// <script> or <style> blocks (only style="" attributes), so 'unsafe-inline' is included for
// style-src to avoid breaking the 150+ inline style attributes already in use.
$csp_nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self';");
header("X-Frame-Options: DENY");

// admin-dashboard-functions.php
require_once __DIR__ . '/../../config/session.php';
// Ensure server uses local timezone for displaying login timestamps
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: ../../landing-page.php");
    exit();
}

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

// Root URL: SCRIPT_NAME for admin-dashboard.php is now /Equipment-Lending-Website/admin-dashboard.php
// dirname() once gives /Equipment-Lending-Website — the project root.
// Used so upload image paths (stored as 'uploads/filename.jpg' in DB) resolve correctly.
$root_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
require_once __DIR__ . '/../../config/csrf.php';

// ================= AJAX CHANGE PASSWORD =================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'change_password') {
    header('Content-Type: application/json');
    csrf_verify();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $email   = $_SESSION['admin_email'] ?? '';

    // 1. Basic Validation
    if (empty($current) || empty($new) || empty($confirm)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
        exit();
    }
    if ($new !== $confirm) {
        echo json_encode(['status' => 'error', 'message' => 'New passwords do not match.']);
        exit();
    }
    if (strlen($new) < 4) {
        echo json_encode(['status' => 'error', 'message' => 'New password must be at least 4 characters.']);
        exit();
    }
    // tbl_accounts.password is varchar(16) — enforce so the UPDATE below
    // never silently truncates the new password.
    if (strlen($new) > 16) {
        echo json_encode(['status' => 'error', 'message' => 'New password must be 16 characters or fewer.']);
        exit();
    }
    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
        exit();
    }

    // 2/3. Verify current password and update it in tbl_accounts — the same
    // table landing-page.php authenticates admin logins against. Looked up
    // by the logged-in admin's own session email rather than a hardcoded
    // dev account, so this works for whatever email is on file.
    $stmt_acc = $conn->prepare("SELECT password FROM tbl_accounts WHERE email = ? LIMIT 1");
    $stmt_acc->bind_param("s", $email);
    $stmt_acc->execute();
    $res_acc = $stmt_acc->get_result();
    $acc_row = $res_acc->fetch_assoc();
    $stmt_acc->close();

    if (!$acc_row) {
        echo json_encode(['status' => 'error', 'message' => 'Account not found. Please log in again.']);
        exit();
    }

    if ($current !== $acc_row['password']) {
        echo json_encode(['status' => 'error', 'message' => 'Incorrect current password.']);
        exit();
    }

    // Self-healing schema (same pattern already used for last_login in
    // landing-page.php): add the tracking column the first time it's needed.
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM tbl_accounts LIKE 'last_password_change'");
    if ($col_check && mysqli_num_rows($col_check) === 0) {
        @mysqli_query($conn, "ALTER TABLE tbl_accounts ADD COLUMN last_password_change DATETIME NULL");
    }

    $now_dt     = date('Y-m-d H:i:s');
    $update_acc = $conn->prepare("UPDATE tbl_accounts SET password = ?, last_password_change = ? WHERE email = ?");
    $update_acc->bind_param("sss", $new, $now_dt, $email);
    if ($update_acc->execute()) {
        $_SESSION['admin_last_pw_change'] = $now_dt;
        echo json_encode([
            'status'          => 'success',
            'message'         => 'Password updated successfully.',
            'last_pw_change'  => $now_dt,
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
    }
    exit();
}

// ================= AJAX UPDATE PROFILE (Settings → My Account) =================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'update_profile') {
    header('Content-Type: application/json');
    csrf_verify();

    $new_name  = trim($_POST['admin_name'] ?? '');
    $new_email = trim($_POST['admin_email'] ?? '');
    $old_email = $_SESSION['admin_email'] ?? '';

    if ($new_name === '') {
        echo json_encode(['status' => 'error', 'message' => 'Display name cannot be empty.']);
        exit();
    }
    if (mb_strlen($new_name) > 100) {
        echo json_encode(['status' => 'error', 'message' => 'Display name is too long (max 100 characters).']);
        exit();
    }
    // Same allowed-character rule already used for faculty names in update-profile.php.
    if (!preg_match("/^[a-zA-ZÀ-ÖØ-öø-ÿ\s.\-']+$/u", $new_name)) {
        echo json_encode(['status' => 'error', 'message' => 'Display name contains invalid characters.']);
        exit();
    }
    if ($new_email === '' || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
        exit();
    }
    if (empty($old_email)) {
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
        exit();
    }

    // tbl_accounts.email carries a UNIQUE key — check for collisions ourselves
    // first so we can return a friendly message instead of a raw DB error.
    if (strcasecmp($new_email, $old_email) !== 0) {
        $stmt_dup = $conn->prepare("SELECT 1 FROM tbl_accounts WHERE email = ? LIMIT 1");
        $stmt_dup->bind_param("s", $new_email);
        $stmt_dup->execute();
        $dup_found = $stmt_dup->get_result()->num_rows > 0;
        $stmt_dup->close();
        if ($dup_found) {
            echo json_encode(['status' => 'error', 'message' => 'That email is already in use.']);
            exit();
        }
    }

    $stmt_upd = $conn->prepare("UPDATE tbl_accounts SET fullName = ?, email = ? WHERE email = ?");
    $stmt_upd->bind_param("sss", $new_name, $new_email, $old_email);

    if ($stmt_upd->execute()) {
        // Keep the session in sync so every part of the page (header, dropdown,
        // greeting, and subsequent requests like change-password) sees the
        // update immediately without requiring a fresh login.
        $_SESSION['admin_name']  = $new_name;
        $_SESSION['admin_email'] = $new_email;
        echo json_encode([
            'status'      => 'success',
            'message'     => 'Profile updated successfully.',
            'admin_name'  => $new_name,
            'admin_email' => $new_email,
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Could not save changes. Please try again.']);
    }
    exit();
}


// ================= AUTO DECLINE EXPIRED REQUESTS =================

$today = date('Y-m-d');
$reason_expired = "Request expired – borrow date has already passed";

$stmt_expired = $conn->prepare("
    UPDATE tbl_requests
    SET status = 'Declined', reason = ?
    WHERE status = 'Waiting'
    AND borrow_date < ?
");
$stmt_expired->bind_param("ss", $reason_expired, $today);
$stmt_expired->execute();


// ================= AUTO MARK OVERDUE =================

mysqli_query($conn, "
    UPDATE tbl_requests
    SET status = 'Overdue'
    WHERE status = 'Approved'
    AND return_date < '$today'
");


// ================= ADD ITEM =================

if (isset($_POST['add_item'])) {
    csrf_verify();
    // Sanitize and format basic inputs
    $name = trim($_POST['item_name']);
    $category = $_POST['category'];
    $qty = (int) $_POST['quantity'];
    $condition = in_array($_POST['condition'] ?? '', ['Good', 'Fair', 'For Repair']) ? $_POST['condition'] : 'Good';
    $description = trim($_POST['description'] ?? '');
    if ($description === '') $description = null;

    // Default image path
    $image_path = "uploads/default.png";

    // Handle image upload
    if (!empty($_FILES['item_image']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $file_type = $_FILES['item_image']['type'];

        if (!in_array($file_type, $allowed_types)) {
            die("Only JPG, PNG, and WEBP images are allowed.");
        }

        $max_size = 2 * 1024 * 1024;
        if ($_FILES['item_image']['size'] > $max_size) {
            die("Image too large. Maximum size is 2MB.");
        }

        $image_name = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", $_FILES['item_image']['name']);
        $target = __DIR__ . '/../../uploads/' . $image_name;

        if (move_uploaded_file($_FILES['item_image']['tmp_name'], $target)) {
            $image_path = "uploads/" . $image_name;
        }
    }

    $stmt = $conn->prepare("INSERT INTO tbl_inventory (item_name, category, quantity, image_path, `condition`, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssisss", $name, $category, $qty, $image_path, $condition, $description);

    if ($stmt->execute()) {
        $stmt->close();
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?view=inventory&added=1");
        exit();
    } else {
        error_log('[PUPSync] admin add_item DB insert failed: ' . $conn->error);
        die("Error saving to database. Please try again later.");
    }
}


// ================= EDIT ITEM =================

if (isset($_POST['update_item'])) {
    csrf_verify();

    $item_id = intval($_POST['item_id']);
    $name = $_POST['item_name'];
    $category = $_POST['category'];
    $qty = intval($_POST['quantity']);
    $condition = in_array($_POST['condition'] ?? '', ['Good', 'Fair', 'For Repair']) ? $_POST['condition'] : 'Good';
    $description = trim($_POST['description'] ?? '');

    $image_path = $_POST['old_image'];

    // Upload new image if provided
    if (!empty($_FILES['item_image']['name'])) {

        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $file_type = $_FILES['item_image']['type'];

        if (!in_array($file_type, $allowed_types)) {
            die("Only JPG, PNG, and WEBP images are allowed.");
        }

        $max_size = 2 * 1024 * 1024;
        if ($_FILES['item_image']['size'] > $max_size) {
            die("Image too large. Maximum size is 2MB.");
        }

        $image_name = time() . "_" . $_FILES['item_image']['name'];
        $target = __DIR__ . '/../../uploads/' . $image_name;

        move_uploaded_file($_FILES['item_image']['tmp_name'], $target);
        $image_path = "uploads/" . $image_name;
    }

    $description_escaped = mysqli_real_escape_string($conn, $description);
    $sql = "UPDATE tbl_inventory
            SET item_name='$name',
                category='$category',
                quantity=$qty,
                image_path='$image_path',
                `condition`='$condition',
                description=" . ($description_escaped === '' ? 'NULL' : "'$description_escaped'") . "
            WHERE item_id=$item_id";

    mysqli_query($conn, $sql);

    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header("Location: {$base}/admin-dashboard.php?view=inventory&updated=1");
    exit();
}


// ================= DELETE ITEM (ARCHIVE) =================

if (isset($_GET['delete_item'])) {
    $id = intval($_GET['delete_item']);

    // NOTE: this only archives the item (is_archived = 1) — it does NOT
    // delete the underlying image file. Archiving is reversible via
    // "Restore", so the uploaded photo must stay intact for that to work.
    $stmt = mysqli_prepare($conn, "UPDATE tbl_inventory SET is_archived = 1 WHERE item_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header("Location: {$base}/admin-dashboard.php?view=inventory");
    exit();
}


// ================= RESTORE ITEM =================

if (isset($_GET['restore_item'])) {
    $item_id = intval($_GET['restore_item']);

    $stmt = mysqli_prepare($conn, "UPDATE tbl_inventory SET is_archived = 0 WHERE item_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    mysqli_stmt_execute($stmt);

    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header("Location: {$base}/admin-dashboard.php?view=archive");
    exit();
}


// ================= PERMANENTLY DELETE =================

if (isset($_GET['force_delete'])) {
    $item_id = intval($_GET['force_delete']);

    $stmt = mysqli_prepare($conn, "DELETE FROM tbl_inventory WHERE item_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    mysqli_stmt_execute($stmt);

    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header("Location: {$base}/admin-dashboard.php?view=archive");
    exit();
}


// ================= FETCH ALL REQUESTS =================

$waiting_sql = "SELECT tbl_requests.*, tbl_inventory.`condition` AS item_condition
                FROM tbl_requests
                LEFT JOIN tbl_inventory ON tbl_inventory.item_name = tbl_requests.equipment_name
                WHERE tbl_requests.status='Waiting'";

if (!empty($_GET['waiting_search'])) {
    $search = "%" . $_GET['waiting_search'] . "%";
    $waiting_sql .= " AND (
        faculty_id LIKE ?
        OR faculty_name LIKE ?
        OR equipment_name LIKE ?
    ) ORDER BY request_date DESC";

    $stmt = $conn->prepare($waiting_sql);
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $waiting_result = $stmt->get_result();
} else {
    $waiting_sql .= " ORDER BY request_date DESC";
    $waiting_result = mysqli_query($conn, $waiting_sql);
}


$approved_sql = "SELECT tbl_requests.*, tbl_inventory.`condition` AS item_condition
                  FROM tbl_requests
                  LEFT JOIN tbl_inventory ON tbl_inventory.item_name = tbl_requests.equipment_name
                  WHERE tbl_requests.status IN ('Approved','Overdue')";
if (!empty($_GET['approved_search'])) {
    $search = "%" . $_GET['approved_search'] . "%";
    $approved_sql .= " AND (
        faculty_id LIKE ?
        OR faculty_name LIKE ?
        OR equipment_name LIKE ?
    ) ORDER BY request_date DESC";
    $stmt = $conn->prepare($approved_sql);
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $approved_result = $stmt->get_result();
} else {
    $approved_sql .= " ORDER BY request_date DESC";
    $approved_result = mysqli_query($conn, $approved_sql);
}


$declined_sql = "SELECT * FROM tbl_requests WHERE status='Declined'";
if (!empty($_GET['declined_search'])) {
    $search = "%" . $_GET['declined_search'] . "%";
    $declined_sql .= " AND (
        faculty_id LIKE ?
        OR faculty_name LIKE ?
        OR equipment_name LIKE ?
    ) ORDER BY request_date DESC";
    $stmt = $conn->prepare($declined_sql);
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $declined_result = $stmt->get_result();
} else {
    $declined_sql .= " ORDER BY request_date DESC";
    $declined_result = mysqli_query($conn, $declined_sql);
}


$overdue_sql = "SELECT * FROM tbl_requests WHERE status='Overdue'";
$overdue_sql .= " ORDER BY return_date ASC";
$overdue_result = mysqli_query($conn, $overdue_sql);


$inventory_sql = "SELECT * FROM tbl_inventory WHERE is_archived = 0";

if (!empty($_GET['inventory_search'])) {
    $search = "%" . $_GET['inventory_search'] . "%";
    $inventory_sql .= "
        AND (item_name LIKE ? OR category LIKE ?)
        ORDER BY created_at DESC
    ";
    $stmt = $conn->prepare($inventory_sql);
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    $inventory_result = $stmt->get_result();
} else {
    $inventory_sql .= " ORDER BY created_at DESC";
    $inventory_result = mysqli_query($conn, $inventory_sql);
}


$archive_sql = "
    SELECT * FROM tbl_inventory
    WHERE is_archived = 1
    ORDER BY item_name ASC
";
$archive_result = mysqli_query($conn, $archive_sql);


$raw_data_sql = "SELECT faculty_id, faculty_name, equipment_name, instructor, room, borrow_date, return_date, request_date FROM tbl_requests";

if (!empty($_GET['raw_search'])) {
    $search = "%" . $_GET['raw_search'] . "%";
    $raw_data_sql .= " WHERE faculty_name LIKE ? OR equipment_name LIKE ? OR faculty_id LIKE ? ORDER BY request_date DESC";
    $stmt = $conn->prepare($raw_data_sql);
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $raw_data_result = $stmt->get_result();
} else {
    $raw_data_sql .= " ORDER BY request_date DESC";
    $raw_data_result = mysqli_query($conn, $raw_data_sql);
}


// ================= STATS =================

$stat_waiting   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_requests WHERE status='Waiting'"))['c'];
$stat_approved  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_requests WHERE status='Approved'"))['c'];
$stat_declined  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_requests WHERE status='Declined'"))['c'];
$stat_overdue   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_requests WHERE status='Overdue'"))['c'];
$stat_inv_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_inventory WHERE is_archived=0"))['c'];
$stat_inv_low   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_inventory WHERE quantity<=2 AND is_archived=0"))['c'];
$stat_total_req = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_requests"))['c'];


// ================= EDIT ITEM FETCH =================

$edit_item = null;

if (isset($_GET['edit_item'])) {
    $edit_id = intval($_GET['edit_item']);
    $edit_query = mysqli_query($conn, "SELECT * FROM tbl_inventory WHERE item_id=$edit_id");
    $edit_item = mysqli_fetch_assoc($edit_query);
}


// ================= ADMIN INFO =================

$admin_name = $_SESSION['admin_name'] ?? 'Administrator';
$name_parts = explode(' ', trim($admin_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) $initials .= strtoupper(substr(end($name_parts), 0, 1));

$admin_email = $_SESSION['admin_email'] ?? '';

// Ensure we have the admin's previous last_login, and their last
// password-change date, available for display. If not already cached in
// session (e.g., first load after this feature was added, or right after
// a fresh login), read them from tbl_accounts. Generalized to work for
// whichever email is on file rather than a single hardcoded account.
$admin_last_pw_change = $_SESSION['admin_last_pw_change'] ?? null;
if (!empty($admin_email) && (empty($_SESSION['admin_last_login']) || $admin_last_pw_change === null)) {
    $col_login = mysqli_query($conn, "SHOW COLUMNS FROM tbl_accounts LIKE 'last_login'");
    $has_login_col = $col_login && mysqli_num_rows($col_login) > 0;

    $col_pw = mysqli_query($conn, "SHOW COLUMNS FROM tbl_accounts LIKE 'last_password_change'");
    $has_pw_col = $col_pw && mysqli_num_rows($col_pw) > 0;

    $select_cols = [];
    if ($has_login_col) $select_cols[] = 'last_login';
    if ($has_pw_col) $select_cols[] = 'last_password_change';

    if (!empty($select_cols)) {
        $stmt = $conn->prepare("SELECT " . implode(', ', $select_cols) . " FROM tbl_accounts WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $admin_email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                if ($has_login_col && empty($_SESSION['admin_last_login'])) {
                    $_SESSION['admin_last_login'] = $row['last_login'] ?? null;
                }
                if ($has_pw_col && $admin_last_pw_change === null) {
                    $admin_last_pw_change = $row['last_password_change'] ?? null;
                    $_SESSION['admin_last_pw_change'] = $admin_last_pw_change;
                }
            }
            $stmt->close();
        }
    }
}

// ================= FETCH ARBITRATION LOG =================

// Show all log entries joined with the current live request status.
// The auto-overdue transition never writes to this table, so no status
// filtering is needed — every row here is a genuine engine or override decision.
$arb_log_sql = "SELECT l.*, r.status AS current_request_status
                  FROM tbl_arbitration_log l
                  LEFT JOIN tbl_requests r ON r.id = l.request_id";

if (!empty($_GET['arb_log_search'])) {
    $search = "%" . $_GET['arb_log_search'] . "%";
    $arb_log_sql .= " WHERE (
        l.borrower_name LIKE ?
        OR l.borrower_id LIKE ?
        OR l.equipment_name LIKE ?
    ) ORDER BY l.created_at DESC";
    $stmt = $conn->prepare($arb_log_sql);
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $arb_log_result = $stmt->get_result();
} else {
    $arb_log_sql .= " ORDER BY l.created_at DESC";
    $arb_log_result = mysqli_query($conn, $arb_log_sql);
}


// ================= LOAD ARBITRATION CONFIG =================

$arb_config = [];
$arb_config_result = mysqli_query($conn, "SELECT config_key, config_value FROM tbl_arbitration_config");
if ($arb_config_result) {
    while ($row = mysqli_fetch_assoc($arb_config_result)) {
        $arb_config[$row['config_key']] = $row['config_value'];
    }
}


$init_view = $_GET['view'] ?? 'dashboard';
