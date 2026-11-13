<?php
// ================= STATUS HOUSEKEEPING =================
// One shared routine (see core/request-decision.php and core/booking-schedule.php):
//   - undecided requests past their deadline (12:01 AM on the borrow date) are declined
//   - loans that are out and past their return time become Overdue
//   - approved bookings nobody collected are closed as no-shows
$today = date('Y-m-d');   // kept: other code that includes this file may read it
require_once __DIR__ . '/auto-approve-tick.php';
equipment_auto_approve_tick($conn);
BookingSchedule::tick($conn);


// ================= EQUIPMENT IMAGE FILE HELPERS =================

if (!function_exists('equipment_delete_image_file')) {
    /**
     * Deletes an equipment photo from the /uploads folder, but only when that is safe.
     * It never touches:
     *   - the shared placeholder (uploads/default.png)
     *   - anything that is not a plain file directly inside /uploads
     *   - a photo that another inventory item is still using
     *
     * @param string|null $image_path     value as stored in tbl_inventory.image_path
     * @param int         $except_item_id item that is being edited/deleted (its own row is ignored
     *                                    when checking whether someone else still uses the file)
     * @return bool true if a file was actually removed
     */
    function equipment_delete_image_file(mysqli $conn, ?string $image_path, int $except_item_id = 0): bool
    {
        $image_path = trim((string)$image_path);
        if ($image_path === '' || $image_path === 'uploads/default.png') {
            return false;
        }
        // Must be exactly "uploads/<file name>" - no sub-folders, no "..", no hidden files.
        if (!preg_match('#^uploads/([^/\\\\]+)$#', $image_path, $m)) {
            return false;
        }
        $file = $m[1];
        if ($file[0] === '.' || $file === 'default.png') {
            return false;
        }

        try {
            // Another item still pointing at the same file? Then leave it alone.
            $stmt = $conn->prepare('SELECT COUNT(*) FROM tbl_inventory WHERE image_path = ? AND item_id <> ?');
            $stmt->bind_param('si', $image_path, $except_item_id);
            $stmt->execute();
            $stmt->bind_result($others);
            $stmt->fetch();
            $stmt->close();
            if ((int)$others > 0) {
                return false;
            }
        } catch (Throwable $e) {
            error_log('[PUPSync] equipment image in-use check failed: ' . $e->getMessage());
            return false;   // when unsure, keep the file
        }

        $uploads_dir = realpath(__DIR__ . '/../../uploads');
        $abs         = realpath(__DIR__ . '/../../uploads/' . $file);
        if ($uploads_dir === false || $abs === false || dirname($abs) !== $uploads_dir || !is_file($abs)) {
            return false;
        }
        if (!@unlink($abs)) {
            error_log('[PUPSync] could not delete equipment image: ' . $abs);
            return false;
        }
        return true;
    }
}


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

    $campus_id = max(1, (int)($_POST['campus_id'] ?? 1));
    $stmt = $conn->prepare("INSERT INTO tbl_inventory (campus_id, item_name, category, quantity, image_path, `condition`, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississs", $campus_id, $name, $category, $qty, $image_path, $condition, $description);

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

    $item_id   = intval($_POST['item_id']);
    $name      = $_POST['item_name'];
    $category  = $_POST['category'];
    $qty       = intval($_POST['quantity']);
    $campus_id = max(1, (int)($_POST['campus_id'] ?? 1));
    $condition = in_array($_POST['condition'] ?? '', ['Good', 'Fair', 'For Repair']) ? $_POST['condition'] : 'Good';

    // The photo currently on record comes from the database, not from the form's
    // hidden "old_image" field - so the file we may delete below can never be a
    // path the browser made up.
    $current_image = 'uploads/default.png';
    $cur_stmt = $conn->prepare("SELECT image_path FROM tbl_inventory WHERE item_id = ? LIMIT 1");
    $cur_stmt->bind_param("i", $item_id);
    $cur_stmt->execute();
    $cur_stmt->bind_result($cur_db_image);
    if ($cur_stmt->fetch() && !empty($cur_db_image)) {
        $current_image = $cur_db_image;
    }
    $cur_stmt->close();

    $image_path = $current_image;
    $old_image_to_delete = null;   // set only when the photo is really being replaced/removed
    $new_image_abs = null;         // the freshly uploaded file (removed again if the DB update fails)

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

        $image_name = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", $_FILES['item_image']['name']);
        $target = __DIR__ . '/../../uploads/' . $image_name;

        if (!move_uploaded_file($_FILES['item_image']['tmp_name'], $target)) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Could not save the image. Please try again.']);
                exit();
            }
            header("Location: {$base}/admin-dashboard.php?view=inventory&error=dberror");
            exit();
        }
        $new_image_abs = $target;
        $image_path = "uploads/" . $image_name;
        $old_image_to_delete = $current_image;   // the photo being replaced goes away once the save succeeds
    } elseif (($_POST['remove_image'] ?? '0') === '1') {
        // Explicit "Remove image" click — revert to the default placeholder and
        // (after the save succeeds) delete the old upload from disk.
        $old_image_to_delete = $current_image;
        $image_path = 'uploads/default.png';
    }

    // NOTE: description is intentionally not part of this UPDATE. The admin
    // forms no longer have a Description field, so existing values are left as-is.
    try {
        $stmt = $conn->prepare(
            "UPDATE tbl_inventory
             SET campus_id  = ?,
                 item_name  = ?,
                 category   = ?,
                 quantity   = ?,
                 image_path = ?,
                 `condition` = ?
             WHERE item_id = ?"
        );
        $stmt->bind_param("ississi", $campus_id, $name, $category, $qty, $image_path, $condition, $item_id);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('[PUPSync] admin update_item DB update failed: ' . $e->getMessage());
        // Don't leave the just-uploaded photo behind when the item wasn't saved.
        if ($new_image_abs !== null && is_file($new_image_abs)) {
            @unlink($new_image_abs);
        }
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Could not save to database. Please try again.']);
            exit();
        }
        header("Location: {$base}/admin-dashboard.php?view=inventory&error=dberror");
        exit();
    }

    // Saved. Now remove the photo that was replaced so no duplicate is kept.
    if ($old_image_to_delete !== null && $old_image_to_delete !== $image_path) {
        equipment_delete_image_file($conn, $old_image_to_delete, $item_id);
    }

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

    // Remember which photo belongs to this item BEFORE the row disappears.
    $force_image = null;
    $img_stmt = mysqli_prepare($conn, "SELECT image_path FROM tbl_inventory WHERE item_id = ? LIMIT 1");
    mysqli_stmt_bind_param($img_stmt, "i", $item_id);
    mysqli_stmt_execute($img_stmt);
    mysqli_stmt_bind_result($img_stmt, $force_image_db);
    if (mysqli_stmt_fetch($img_stmt)) {
        $force_image = $force_image_db;
    }
    mysqli_stmt_close($img_stmt);

    $stmt = mysqli_prepare($conn, "DELETE FROM tbl_inventory WHERE item_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    mysqli_stmt_execute($stmt);
    $row_deleted = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    // Only once the item is really gone: delete its photo from the uploads folder too
    // (skipped automatically for the default placeholder or a photo another item still uses).
    if ($row_deleted && $force_image !== null) {
        equipment_delete_image_file($conn, $force_image);
    }
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
