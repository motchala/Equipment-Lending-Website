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
require_once __DIR__ . '/../../shared/core/cloudinary.php';

if (!function_exists('equipment_condition_from_qty')) {
    /**
     * Derive item condition from quantity.
     *   0        → For Repair
     *   1–3      → Fair
     *   4+       → Good
     */
    function equipment_condition_from_qty(int $qty): string
    {
        if ($qty === 0)  return 'For Repair';
        if ($qty <= 3)   return 'Fair';
        return 'Good';
    }
}

if (!function_exists('equipment_name_normalized')) {
    /** Strip all spaces and lowercase — used for duplicate detection. */
    function equipment_name_normalized(string $name): string
    {
        return strtolower(str_replace(' ', '', $name));
    }
}

if (!function_exists('equipment_name_exists')) {
    /**
     * Returns true if another active (non-archived) item has the same
     * normalized name (spaces stripped, case-insensitive).
     * Pass $exclude_id when editing so the item being edited does not
     * match itself.
     */
    function equipment_name_exists(mysqli $conn, string $name, int $exclude_id = 0): bool
    {
        $stmt = $conn->prepare(
            "SELECT item_id FROM tbl_inventory
              WHERE REPLACE(LOWER(item_name), ' ', '') = ?
                AND is_archived = 0
                AND item_id <> ?
              LIMIT 1"
        );
        if (!$stmt) return false;
        $normalized = equipment_name_normalized($name);
        $stmt->bind_param('si', $normalized, $exclude_id);
        $stmt->execute();
        $stmt->store_result();
        $found = $stmt->num_rows > 0;
        $stmt->close();
        return $found;
    }
}


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

        // Cloudinary image — delete via API
        if (str_starts_with($image_path, 'https://res.cloudinary.com/')) {
            try {
                // Check if another item still uses this URL
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
                error_log('[PUPSync] Cloudinary in-use check failed: ' . $e->getMessage());
                return false;
            }
            return cloudinary_delete($image_path);
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


// ================= EQUIPMENT IMAGE UPLOAD HELPER =================

if (!function_exists('equipment_store_uploaded_image')) {
    /**
     * Validates and stores an uploaded equipment photo (Cloudinary first, local /uploads as fallback).
     *
     * The file type is checked from the file's real contents, not from the browser-supplied
     * type, and PHP's own upload error code is reported as a readable message.
     *
     * @param array $file one entry of $_FILES
     * @return array{ok:bool, path:string, abs:?string, message:string}
     *         path = value for tbl_inventory.image_path; abs = absolute path when stored locally
     */
    function equipment_store_uploaded_image(array $file): array
    {
        $fail = static fn(string $m): array => ['ok' => false, 'path' => '', 'abs' => null, 'message' => $m];

        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            return $fail(match ($err) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Image too large. Maximum size is 2 MB.',
                UPLOAD_ERR_PARTIAL                        => 'The image upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not receive the image (no temporary storage). Please try again later.',
                default                                   => 'The image could not be uploaded. Please try again.',
            });
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return $fail('The image could not be uploaded. Please try again.');
        }
        if ((int)($file['size'] ?? 0) > 2 * 1024 * 1024) {
            return $fail('Image too large. Maximum size is 2 MB.');
        }

        // Real type check (JPG / PNG only)
        $info = @getimagesize($tmp);
        $ext_by_type = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
        if ($info === false || !isset($ext_by_type[$info[2]])) {
            return $fail('Only JPG and PNG images are allowed.');
        }
        $ext = $ext_by_type[$info[2]];

        // Cloudinary — the only supported upload path on this deployment
        $orig = (string)($file['name'] ?? 'image.' . $ext);
        $cloud_url = cloudinary_upload($tmp, $orig);
        if ($cloud_url !== '') {
            return ['ok' => true, 'path' => $cloud_url, 'abs' => null, 'message' => ''];
        }

        return $fail('Image upload failed. Cloudinary is not configured or the upload was rejected. Please check the server .env file has valid CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET values.');
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
    $condition = equipment_condition_from_qty($qty);
    $description = trim($_POST['remarks'] ?? $_POST['description'] ?? '');
    if ($description === '') $description = null;

    // Block quantity = 0 on new items — a new item must have at least 1 unit
    if ($qty < 1) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Quantity must be at least 1 when adding a new item.']);
            exit();
        }
        header("Location: {$base}/admin-dashboard.php?view=inventory&error=qty");
        exit();
    }

    // Duplicate name check (space-stripped, case-insensitive)
    if (equipment_name_exists($conn, $name)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'An item with this name already exists. Check the Equipment Inventory List on the right.']);
            exit();
        }
        header("Location: {$base}/admin-dashboard.php?view=inventory&error=duplicate");
        exit();
    }

    // Default image path
    $image_path = "uploads/default.png";

    // Handle image upload
    $new_image_abs = null;   // locally stored file (removed again if the DB insert fails)
    if (!empty($_FILES['item_image']['name'])) {
        $up = equipment_store_uploaded_image($_FILES['item_image']);
        if (!$up['ok']) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => $up['message']]);
                exit();
            }
            header("Location: {$base}/admin-dashboard.php?view=inventory&error=dberror");
            exit();
        }
        $image_path = $up['path'];
        $new_image_abs = $up['abs'];
    }

    $campus_id = max(1, (int)($_POST['campus_id'] ?? 1));
    $stmt = $conn->prepare("INSERT INTO tbl_inventory (campus_id, item_name, category, quantity, image_path, `condition`, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississs", $campus_id, $name, $category, $qty, $image_path, $condition, $description);

    if ($stmt->execute()) {
        $new_item_id = (int)$conn->insert_id;
        $stmt->close();
        admin_log_activity($conn, 'equipment', 'Added equipment: ' . $name);
        // Register sheet sync to run after response is fully sent
        require_once __DIR__ . '/../../google-sheets/core/writer.php';
        register_shutdown_function(function () use ($conn, $new_item_id) {
            gs_sync_item($conn, $new_item_id, 'upsert');
        });
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Equipment added successfully.']);
            exit();
        }
        header("Location: {$base}/admin-dashboard.php?view=inventory&added=1");
        exit();
    } else {
        error_log('[PUPSync] admin add_item DB insert failed: ' . $conn->error);
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
}


// ================= EDIT ITEM =================

if (isset($_POST['update_item'])) {
    csrf_verify();
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $is_ajax = ($_POST['ajax'] ?? '') === '1';

    $item_id   = intval($_POST['item_id']);
    $name      = trim($_POST['item_name']);
    $category  = $_POST['category'];
    $qty       = intval($_POST['quantity']);
    $campus_id = max(1, (int)($_POST['campus_id'] ?? 1));
    $condition = equipment_condition_from_qty($qty);
    $remarks   = trim($_POST['remarks'] ?? '');

    // Duplicate name check — exclude the item being edited
    if (equipment_name_exists($conn, $name, $item_id)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Another item with this name already exists. Use a different name (e.g. "' . htmlspecialchars($name) . ' 2").']);
            exit();
        }
        header("Location: {$base}/admin-dashboard.php?view=inventory&error=duplicate");
        exit();
    }

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
        $up = equipment_store_uploaded_image($_FILES['item_image']);
        if (!$up['ok']) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => $up['message']]);
                exit();
            }
            header("Location: {$base}/admin-dashboard.php?view=inventory&error=dberror");
            exit();
        }
        $image_path = $up['path'];
        $new_image_abs = $up['abs'];
        $old_image_to_delete = $current_image;
    } elseif (($_POST['remove_image'] ?? '0') === '1') {
        // Explicit "Remove image" click — revert to the default placeholder and
        // (after the save succeeds) delete the old upload from disk.
        $old_image_to_delete = $current_image;
        $image_path = 'uploads/default.png';
    }

    try {
        $stmt = $conn->prepare(
            "UPDATE tbl_inventory
             SET campus_id   = ?,
                 item_name   = ?,
                 category    = ?,
                 quantity    = ?,
                 image_path  = ?,
                 `condition` = ?,
                 description = ?
             WHERE item_id = ?"
        );
        $remarks_val = $remarks === '' ? null : $remarks;
        $stmt->bind_param("ississsi", $campus_id, $name, $category, $qty, $image_path, $condition, $remarks_val, $item_id);
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
    require_once __DIR__ . '/../../google-sheets/core/writer.php';
    register_shutdown_function(function () use ($conn, $item_id) {
        gs_sync_item($conn, $item_id, 'upsert');
    });
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
    // Mirror to Google Sheet (silent on failure — DB is the master)
    require_once __DIR__ . '/../../google-sheets/core/writer.php';
    gs_sync_item($conn, $id, 'archive');
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
