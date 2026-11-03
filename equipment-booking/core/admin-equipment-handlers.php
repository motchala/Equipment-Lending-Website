<?php
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
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $is_ajax = ($_POST['ajax'] ?? '') === '1';

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
        $allowed_types = ['image/jpeg', 'image/png'];
        $file_type = $_FILES['item_image']['type'];

        if (!in_array($file_type, $allowed_types)) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Only JPG and PNG images are allowed.']);
                exit();
            }
            header("Location: {$base}/admin-dashboard.php?view=inventory&error=filetype");
            exit();
        }

        $max_size = 2 * 1024 * 1024;
        if ($_FILES['item_image']['size'] > $max_size) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Image too large. Maximum size is 2 MB.']);
                exit();
            }
            header("Location: {$base}/admin-dashboard.php?view=inventory&error=filesize");
            exit();
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
        admin_log_activity($conn, 'equipment', 'Added equipment: ' . $name);
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Equipment added successfully.']);
            exit();
        }
        header("Location: {$base}/admin-dashboard.php?view=inventory&added=1");
        exit();
    } else {
        error_log('[PUPSync] admin add_item DB insert failed: ' . $conn->error);
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Could not save to database. Please try again.']);
            exit();
        }
        header("Location: {$base}/admin-dashboard.php?view=inventory&error=dberror");
        exit();
    }
}


// ================= EDIT ITEM =================

if (isset($_POST['update_item'])) {
    csrf_verify();
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $is_ajax = ($_POST['ajax'] ?? '') === '1';

    $item_id = intval($_POST['item_id']);
    $name = $_POST['item_name'];
    $category = $_POST['category'];
    $qty = intval($_POST['quantity']);
    $condition = in_array($_POST['condition'] ?? '', ['Good', 'Fair', 'For Repair']) ? $_POST['condition'] : 'Good';

    $image_path = $_POST['old_image'];

    // Upload new image if provided
    if (!empty($_FILES['item_image']['name'])) {

        $allowed_types = ['image/jpeg', 'image/png'];
        $file_type = $_FILES['item_image']['type'];

        if (!in_array($file_type, $allowed_types)) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Only JPG and PNG images are allowed.']);
                exit();
            }
            header("Location: {$base}/admin-dashboard.php?view=inventory&error=filetype");
            exit();
        }

        $max_size = 2 * 1024 * 1024;
        if ($_FILES['item_image']['size'] > $max_size) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Image too large. Maximum size is 2 MB.']);
                exit();
            }
            header("Location: {$base}/admin-dashboard.php?view=inventory&error=filesize");
            exit();
        }

        $image_name = time() . "_" . $_FILES['item_image']['name'];
        $target = __DIR__ . '/../../uploads/' . $image_name;

        move_uploaded_file($_FILES['item_image']['tmp_name'], $target);
        $image_path = "uploads/" . $image_name;
    } elseif (($_POST['remove_image'] ?? '0') === '1') {
        // Explicit "Remove image" click — revert to the default placeholder.
        // Unlike archiving (which must never touch the file), this really is
        // a deliberate request to get rid of this specific image, so it's
        // safe — and expected — to also delete the old upload from disk.
        if ($image_path !== 'uploads/default.png') {
            $old_file = __DIR__ . '/../../' . $image_path;
            if (is_file($old_file)) {
                @unlink($old_file);
            }
        }
        $image_path = 'uploads/default.png';
    }

    // NOTE: description is intentionally not part of this UPDATE. The admin
    // forms no longer have a Description field, so existing values are left as-is.
    $stmt = $conn->prepare(
        "UPDATE tbl_inventory
         SET item_name = ?,
             category = ?,
             quantity = ?,
             image_path = ?,
             `condition` = ?
         WHERE item_id = ?"
    );
    $stmt->bind_param("ssissi", $name, $category, $qty, $image_path, $condition, $item_id);
    $stmt->execute();
    $stmt->close();

    admin_log_activity($conn, 'equipment', 'Updated equipment: ' . $name);
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Equipment updated successfully.']);
        exit();
    }
    header("Location: {$base}/admin-dashboard.php?view=inventory&updated=1");
    exit();
}


// ================= DELETE ITEM (ARCHIVE) =================

if (isset($_GET['delete_item'])) {
    $id = intval($_GET['delete_item']);
    $is_ajax = ($_GET['ajax'] ?? '') === '1';

    // NOTE: this only archives the item (is_archived = 1) — it does NOT
    // delete the underlying image file. Archiving is reversible via
    // "Restore", so the uploaded photo must stay intact for that to work.
    $stmt = mysqli_prepare($conn, "UPDATE tbl_inventory SET is_archived = 1 WHERE item_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    admin_log_activity($conn, 'equipment', 'Archived equipment: ' . admin_activity_item_name($conn, $id));
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Item archived.']);
        exit();
    }
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header("Location: {$base}/admin-dashboard.php?view=inventory");
    exit();
}


// ================= RESTORE ITEM =================

if (isset($_GET['restore_item'])) {
    $item_id = intval($_GET['restore_item']);
    $is_ajax = ($_GET['ajax'] ?? '') === '1';

    $stmt = mysqli_prepare($conn, "UPDATE tbl_inventory SET is_archived = 0 WHERE item_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    mysqli_stmt_execute($stmt);
    admin_log_activity($conn, 'equipment', 'Restored equipment: ' . admin_activity_item_name($conn, $item_id));

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Item restored.']);
        exit();
    }
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header("Location: {$base}/admin-dashboard.php?view=archive");
    exit();
}


// ================= PERMANENTLY DELETE =================

if (isset($_GET['force_delete'])) {
    $item_id = intval($_GET['force_delete']);
    $is_ajax = ($_GET['ajax'] ?? '') === '1';
    $force_name = admin_activity_item_name($conn, $item_id);

    $stmt = mysqli_prepare($conn, "DELETE FROM tbl_inventory WHERE item_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    mysqli_stmt_execute($stmt);
    admin_log_activity($conn, 'equipment', 'Permanently deleted equipment: ' . $force_name);

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Item permanently deleted.']);
        exit();
    }
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header("Location: {$base}/admin-dashboard.php?view=archive");
    exit();
}

