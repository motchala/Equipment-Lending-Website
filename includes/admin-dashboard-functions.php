<?php
// admin-dashboard-functions.php
session_start();
// Ensure server uses local timezone for displaying login timestamps
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: landing-page.php");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "lending_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// ================= AUTO-APPROVE HELPER FUNCTIONS =================

/**
 * Returns the current auto-approve toggle state from the database.
 * Queries the settings row (id=1) and returns 1 if enabled, 0 otherwise.
 *
 * @param  mysqli $conn  Active DB connection
 * @return int           1 if auto-approve is ON, 0 if OFF or row absent
 */
function getAutoApproveEnabled(mysqli $conn): int {
    $stmt = $conn->prepare("SELECT is_enabled FROM tbl_auto_approve_settings WHERE id = 1");
    if (!$stmt) {
        return 0;
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? (int) $row['is_enabled'] : 0;
}

/**
 * Returns the list of item names currently marked for auto-approval.
 * Queries all rows where is_auto_approved = 1 and returns their item_name values.
 *
 * @param  mysqli   $conn  Active DB connection
 * @return string[]        Flat array of item name strings; empty array if none
 */
function getAutoApproveItems(mysqli $conn): array {
    $stmt = $conn->prepare("SELECT item_name FROM tbl_auto_approve_settings WHERE is_auto_approved = 1");
    if (!$stmt) {
        return [];
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row['item_name'];
    }
    $stmt->close();
    return $items;
}


// ================= AUTO-APPROVAL ENGINE =================

/**
 * Evaluates a newly inserted borrow request for auto-approval.
 * Approves, declines, or leaves as Waiting based on toggle state,
 * item set membership, and current stock.
 *
 * Logic flow:
 *  1. Fetch request row — bail if not found or status !== 'Waiting'
 *  2. Read is_enabled from settings (id=1) — bail if OFF or row absent
 *  3. Read auto-approved item set — bail if equipment_name not in set
 *  4. Check is_archived on tbl_inventory — bail if archived or not found
 *  5. Read quantity — if 0, decline with out-of-stock reason and return
 *  6. Approve: UPDATE tbl_requests SET status='Approved', reason=NULL
 *  7. Decrement: UPDATE tbl_inventory SET quantity=quantity-1
 *  8. If new quantity = 0, cascade-decline all remaining Waiting requests for that item
 *
 * @param mysqli $conn        Active DB connection
 * @param int    $request_id  The id of the newly inserted tbl_requests row
 */
function processAutoApprove(mysqli $conn, int $request_id): void {

    // ── Step 1: Fetch the request row ────────────────────────────────────────
    $stmt = $conn->prepare("SELECT equipment_name, status FROM tbl_requests WHERE id = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $request = $result->fetch_assoc();
    $stmt->close();

    if (!$request || $request['status'] !== 'Waiting') {
        return;
    }

    $equipment_name = $request['equipment_name'];

    // ── Step 2: Read is_enabled from settings row (id=1) ─────────────────────
    $stmt = $conn->prepare("SELECT is_enabled FROM tbl_auto_approve_settings WHERE id = 1");
    if (!$stmt) {
        return;
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $settings = $result->fetch_assoc();
    $stmt->close();

    if (!$settings || (int) $settings['is_enabled'] === 0) {
        return;
    }

    // ── Step 3: Read auto-approved item set; bail if item not in set ──────────
    $stmt = $conn->prepare("SELECT item_name FROM tbl_auto_approve_settings WHERE is_auto_approved = 1");
    if (!$stmt) {
        return;
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $auto_approve_items = [];
    while ($row = $result->fetch_assoc()) {
        $auto_approve_items[] = $row['item_name'];
    }
    $stmt->close();

    if (!in_array($equipment_name, $auto_approve_items, true)) {
        return;
    }

    // ── Step 4: Check is_archived on tbl_inventory ────────────────────────────
    $stmt = $conn->prepare("SELECT is_archived, quantity FROM tbl_inventory WHERE item_name = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("s", $equipment_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $inventory = $result->fetch_assoc();
    $stmt->close();

    if (!$inventory || (int) $inventory['is_archived'] === 1) {
        return;
    }

    // ── Step 5: Check quantity; decline if out of stock ───────────────────────
    $quantity = (int) $inventory['quantity'];

    if ($quantity === 0) {
        $reason = 'Out of stock – maximum approved requests reached';
        $stmt = $conn->prepare("UPDATE tbl_requests SET status = 'Declined', reason = ? WHERE id = ?");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param("si", $reason, $request_id);
        $stmt->execute();
        $stmt->close();
        return;
    }

    // ── Step 6: Approve the request ───────────────────────────────────────────
    $stmt = $conn->prepare("UPDATE tbl_requests SET status = 'Approved', reason = NULL WHERE id = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $stmt->close();

    // ── Step 7: Decrement inventory quantity ──────────────────────────────────
    $stmt = $conn->prepare("UPDATE tbl_inventory SET quantity = quantity - 1 WHERE item_name = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("s", $equipment_name);
    $stmt->execute();
    $stmt->close();

    // ── Step 8: Cascade-decline remaining Waiting requests if stock is now 0 ──
    $new_quantity = $quantity - 1;

    if ($new_quantity === 0) {
        $reason = 'Out of stock – maximum approved requests reached';
        $stmt = $conn->prepare(
            "UPDATE tbl_requests SET status = 'Declined', reason = ? WHERE equipment_name = ? AND status = 'Waiting'"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param("ss", $reason, $equipment_name);
        $stmt->execute();
        $stmt->close();
    }
}


// ================= AJAX CHANGE PASSWORD =================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'change_password') {
    header('Content-Type: application/json');
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

    // 2/3. Verify and Update in DB
    // Special-case the local development shortcut admin stored in `tbl_accounts`.
    if ($email === 'main@admin.edu') {
        $stmt_acc = $conn->prepare("SELECT password FROM tbl_accounts WHERE email = ? LIMIT 1");
        $stmt_acc->bind_param("s", $email);
        $stmt_acc->execute();
        $res_acc = $stmt_acc->get_result();
        $acc_row = $res_acc->fetch_assoc();
        $stored_acc_pw = $acc_row['password'] ?? null;

        // Accept either the stored tbl_accounts password or the known dev shortcut 'admin123'
        if ($current === $stored_acc_pw || $current === 'admin123') {
            // Update the password in tbl_accounts (legacy table stores plain password)
            $update_acc = $conn->prepare("UPDATE tbl_accounts SET password = ? WHERE email = ?");
            $update_acc->bind_param("ss", $new, $email);
            if ($update_acc->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Password updated successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Incorrect current password.']);
        }
    } 
    exit();
}


// ================= AJAX TOGGLE AUTO APPROVE =================
if (isset($_POST['action']) && $_POST['action'] === 'toggle_auto_approve') {
    header('Content-Type: application/json');
    $enabled = isset($_POST['enabled']) ? intval($_POST['enabled']) : 0;
    $enabled = ($enabled >= 1) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE tbl_auto_approve_settings SET is_enabled = ? WHERE id = 1");
    $stmt->bind_param("i", $enabled);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'enabled' => $enabled]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
    }
    exit();
}


// ================= AJAX: UPDATE AUTO-APPROVE ITEMS =================
if (isset($_POST['action']) && $_POST['action'] === 'update_auto_approve_items') {
    header('Content-Type: application/json');

    // Clear the existing auto-approved item set
    $stmt_del = $conn->prepare("DELETE FROM tbl_auto_approve_settings WHERE is_auto_approved = 1");
    if (!$stmt_del->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
        exit();
    }
    $stmt_del->close();

    $saved_items = [];

    // Insert each submitted item name
    if (!empty($_POST['items']) && is_array($_POST['items'])) {
        $stmt_ins = $conn->prepare(
            "INSERT INTO tbl_auto_approve_settings (is_enabled, item_name, is_auto_approved) VALUES (0, ?, 1)"
        );
        foreach ($_POST['items'] as $raw_item) {
            $item_name = trim($raw_item);
            if ($item_name === '') {
                continue;
            }
            $stmt_ins->bind_param("s", $item_name);
            if (!$stmt_ins->execute()) {
                echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
                exit();
            }
            $saved_items[] = $item_name;
        }
        $stmt_ins->close();
    }

    echo json_encode(['status' => 'success', 'items' => $saved_items]);
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


// ================= HANDLE APPROVE / DECLINE ACTIONS =================

if (isset($_GET['action'], $_GET['id'])) {
    $request_id = (int) $_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve') {

        // Get equipment name
        $stmt_req = $conn->prepare("
            SELECT equipment_name
            FROM tbl_requests
            WHERE id = ?
        ");
        $stmt_req->bind_param("i", $request_id);
        $stmt_req->execute();
        $req_result = $stmt_req->get_result();
        $request = $req_result->fetch_assoc();

        if (!$request) {
            header("Location: admin-dashboard.php");
            exit();
        }

        $equipment_name = $request['equipment_name'];

        // Get current stock quantity
        $stmt_stock = $conn->prepare("
            SELECT quantity
            FROM tbl_inventory
            WHERE item_name = ?
        ");
        $stmt_stock->bind_param("s", $equipment_name);
        $stmt_stock->execute();
        $stock_result = $stmt_stock->get_result();
        $stock = $stock_result->fetch_assoc();

        if (!$stock) {
            header("Location: admin-dashboard.php");
            exit();
        }

        $current_quantity = (int) $stock['quantity'];

        if ($current_quantity > 0) {

            // Approve this request
            $stmt_approve = $conn->prepare("
                UPDATE tbl_requests
                SET status = 'Approved', reason = NULL
                WHERE id = ?
            ");
            $stmt_approve->bind_param("i", $request_id);
            $stmt_approve->execute();

            // Deduct stock
            $stmt_deduct = $conn->prepare("
                UPDATE tbl_inventory
                SET quantity = quantity - 1
                WHERE item_name = ?
            ");
            $stmt_deduct->bind_param("s", $equipment_name);
            $stmt_deduct->execute();

            // AUTO-DECLINE remaining WAITING requests if stock is now depleted
            if (($current_quantity - 1) <= 0) {

                $reason = "Out of stock – maximum approved requests reached";

                $stmt_auto_decline = $conn->prepare("
                    UPDATE tbl_requests
                    SET status = 'Declined', reason = ?
                    WHERE equipment_name = ?
                    AND status = 'Waiting'
                ");
                $stmt_auto_decline->bind_param("ss", $reason, $equipment_name);
                $stmt_auto_decline->execute();
            }

        } else {

            // No stock left → decline this request
            $reason = "Out of stock – maximum approved requests reached";

            $stmt_decline = $conn->prepare("
                UPDATE tbl_requests
                SET status = 'Declined', reason = ?
                WHERE id = ?
            ");
            $stmt_decline->bind_param("si", $reason, $request_id);
            $stmt_decline->execute();
        }

        header("Location: admin-dashboard.php#lending-waiting");
        exit();

    } elseif ($action === 'decline') {
        $stmt = $conn->prepare("UPDATE tbl_requests SET status = 'Declined' WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        header("Location: admin-dashboard.php#lending-declined");
        exit();
    }
}


// ================= ADD ITEM =================

if (isset($_POST['add_item'])) {

    // Sanitize and format basic inputs
    $name = trim($_POST['item_name']);
    $category = $_POST['category'];
    $qty = (int) $_POST['quantity'];

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
        $target = "uploads/" . $image_name;

        if (move_uploaded_file($_FILES['item_image']['tmp_name'], $target)) {
            $image_path = $target;
        }
    }

    $stmt = $conn->prepare("INSERT INTO tbl_inventory (item_name, category, quantity, image_path) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $name, $category, $qty, $image_path);

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: admin-dashboard.php?view=inventory&added=1");
        exit();
    } else {
        die("Error saving to database: " . $conn->error);
    }
}


// ================= EDIT ITEM =================

if (isset($_POST['update_item'])) {

    $item_id = intval($_POST['item_id']);
    $name = $_POST['item_name'];
    $category = $_POST['category'];
    $qty = intval($_POST['quantity']);

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
        $target = "uploads/" . $image_name;

        move_uploaded_file($_FILES['item_image']['tmp_name'], $target);
        $image_path = $target;
    }

    $sql = "UPDATE tbl_inventory
            SET item_name='$name',
                category='$category',
                quantity=$qty,
                image_path='$image_path'
            WHERE item_id=$item_id";

    mysqli_query($conn, $sql);

    header("Location: admin-dashboard.php?view=inventory&updated=1");
    exit();
}


// ================= DELETE ITEM (ARCHIVE) =================

if (isset($_GET['delete_item'])) {
    $id = intval($_GET['delete_item']);

    $res = mysqli_query($conn, "SELECT image_path FROM tbl_inventory WHERE item_id=$id");
    $row = mysqli_fetch_assoc($res);

    if ($row && $row['image_path'] !== 'uploads/default.png') {
        unlink($row['image_path']);
    }

    $stmt = mysqli_prepare($conn, "UPDATE tbl_inventory SET is_archived = 1 WHERE item_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    header("Location: admin-dashboard.php#sec-inventory");
    exit();
}


// ================= RESTORE ITEM =================

if (isset($_GET['restore_item'])) {
    $item_id = intval($_GET['restore_item']);

    $stmt = mysqli_prepare($conn, "UPDATE tbl_inventory SET is_archived = 0 WHERE item_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    mysqli_stmt_execute($stmt);

    header("Location: admin-dashboard.php?view=archive");
    exit();
}


// ================= PERMANENTLY DELETE =================

if (isset($_GET['force_delete'])) {
    $item_id = intval($_GET['force_delete']);

    $stmt = mysqli_prepare($conn, "DELETE FROM tbl_inventory WHERE item_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    mysqli_stmt_execute($stmt);

    header("Location: admin-dashboard.php?view=archive");
    exit();
}


// ================= FETCH ALL REQUESTS =================

$waiting_sql = "SELECT * FROM tbl_requests WHERE status='Waiting'";

if (!empty($_GET['waiting_search'])) {
    $search = "%" . $_GET['waiting_search'] . "%";
    $waiting_sql .= " AND (
        student_id LIKE ?
        OR student_name LIKE ?
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


$approved_sql = "SELECT * FROM tbl_requests WHERE status='Approved'";
if (!empty($_GET['approved_search'])) {
    $search = "%" . $_GET['approved_search'] . "%";
    $approved_sql .= " AND (
        student_id LIKE ?
        OR student_name LIKE ?
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
        student_id LIKE ?
        OR student_name LIKE ?
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


$raw_data_sql = "SELECT student_id, student_name, equipment_name, instructor, room, borrow_date, return_date, request_date FROM tbl_requests";

if (!empty($_GET['raw_search'])) {
    $search = "%" . $_GET['raw_search'] . "%";
    $raw_data_sql .= " WHERE student_name LIKE ? OR equipment_name LIKE ? OR student_id LIKE ? ORDER BY request_date DESC";
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

// Ensure we have the admin's previous last_login available in session.
// If not present (e.g., first login after this feature was added), try to read it from tbl_accounts.
if (empty($_SESSION['admin_last_login']) && $admin_email === 'main@admin.edu') {
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM tbl_accounts LIKE 'last_login'");
    if ($col_check && mysqli_num_rows($col_check) > 0) {
        $stmt = $conn->prepare("SELECT last_login FROM tbl_accounts WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $admin_email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                $_SESSION['admin_last_login'] = $row['last_login'] ?? null;
            }
            $stmt->close();
        }
    }
}

$init_view = $_GET['view'] ?? 'dashboard';

?>  

