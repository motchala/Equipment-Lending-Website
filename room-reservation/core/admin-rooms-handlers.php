<?php
// ════════════════════════════════════════════════════════════════
// ADD ROOM
// ════════════════════════════════════════════════════════════════
if (isset($_POST['add_room'])) {
    csrf_verify();
    $is_ajax = ($_POST['ajax'] ?? '') === '1';

    $building_id      = intval($_POST['building_id'] ?? 0);
    $room_name        = trim($_POST['room_name'] ?? '');
    $floor_number     = intval($_POST['floor_number'] ?? 1);
    $floor_label      = trim($_POST['floor_label'] ?? '');
    $status           = $_POST['status'] ?? 'Available';
    $sort_order       = intval($_POST['sort_order'] ?? 0);

    // Validate status against allowed enum values
    $allowed_statuses = ['Available', 'Maintenance', 'Not Bookable'];
    if (!in_array($status, $allowed_statuses, true)) {
        $status = 'Available';
    }

    // Amenities — arrive as a JSON array of checkbox values
    $amenities_raw = $_POST['amenities'] ?? [];
    if (!is_array($amenities_raw)) {
        $amenities_raw = [];
    }
    // Sanitise each amenity label
    $amenities_clean = array_values(array_filter(array_map('trim', $amenities_raw)));
    $amenities_json  = !empty($amenities_clean) ? json_encode($amenities_clean) : null;

    if ($building_id <= 0 || $room_name === '') {
        error_log('[PUPSync] add_room: missing required fields');
        if ($is_ajax) ps_send_rooms_ajax_error('Please choose a building and enter a facility name.');
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_error=1");
        exit();
    }

    // The floor has to exist in the chosen building (e.g. a 4-floor building takes floors 1-4)
    $floor_err = ps_floor_limit_error($conn, $building_id, $floor_number);
    if ($floor_err !== null) {
        error_log('[PUPSync] add_room: floor out of range');
        if ($is_ajax) ps_send_rooms_ajax_error($floor_err);
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_error=1");
        exit();
    }

    $stmt = $conn->prepare(
        "INSERT INTO tbl_rooms
            (building_id, room_name, floor_number, floor_label,
             amenities, status, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'isisssi',
        $building_id,
        $room_name,
        $floor_number,
        $floor_label,
        $amenities_json,
        $status,
        $sort_order
    );

    if ($stmt->execute()) {
        $stmt->close();
        admin_log_activity($conn, 'room', 'Added facility: ' . $room_name);
        if ($is_ajax) ps_send_rooms_ajax_response($conn, 'Facility added.');
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_added=1");
    } else {
        error_log('[PUPSync] add_room DB insert failed: ' . $conn->error);
        $stmt->close();
        if ($is_ajax) ps_send_rooms_ajax_error('Could not save the facility. Please try again.');
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_error=1");
    }
    exit();
}


// ════════════════════════════════════════════════════════════════
// UPDATE ROOM
// ════════════════════════════════════════════════════════════════
if (isset($_POST['update_room'])) {
    csrf_verify();
    $is_ajax = ($_POST['ajax'] ?? '') === '1';

    $room_id          = intval($_POST['room_id'] ?? 0);
    $building_id      = intval($_POST['building_id'] ?? 0);
    $room_name        = trim($_POST['room_name'] ?? '');
    $floor_number     = intval($_POST['floor_number'] ?? 1);
    $floor_label      = trim($_POST['floor_label'] ?? '');
    $status           = $_POST['status'] ?? 'Available';
    $sort_order       = intval($_POST['sort_order'] ?? 0);

    $allowed_statuses = ['Available', 'Maintenance', 'Not Bookable'];
    if (!in_array($status, $allowed_statuses, true)) {
        $status = 'Available';
    }

    $amenities_raw   = $_POST['amenities'] ?? [];
    if (!is_array($amenities_raw)) {
        $amenities_raw = [];
    }
    $amenities_clean = array_values(array_filter(array_map('trim', $amenities_raw)));
    $amenities_json  = !empty($amenities_clean) ? json_encode($amenities_clean) : null;

    if ($room_id <= 0 || $building_id <= 0 || $room_name === '') {
        error_log('[PUPSync] update_room: missing required fields');
        if ($is_ajax) ps_send_rooms_ajax_error('Please choose a building and enter a facility name.');
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_error=1");
        exit();
    }

    $floor_err = ps_floor_limit_error($conn, $building_id, $floor_number);
    if ($floor_err !== null) {
        error_log('[PUPSync] update_room: floor out of range');
        if ($is_ajax) ps_send_rooms_ajax_error($floor_err);
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_error=1");
        exit();
    }

    $stmt = $conn->prepare(
        "UPDATE tbl_rooms
         SET building_id = ?, room_name = ?, floor_number = ?, floor_label = ?,
             amenities = ?, status = ?, sort_order = ?
         WHERE room_id = ? AND is_archived = 0"
    );
    $stmt->bind_param(
        'isisssii',
        $building_id,
        $room_name,
        $floor_number,
        $floor_label,
        $amenities_json,
        $status,
        $sort_order,
        $room_id
    );

    if ($stmt->execute()) {
        $stmt->close();
        admin_log_activity($conn, 'room', 'Updated facility: ' . $room_name);
        if ($is_ajax) ps_send_rooms_ajax_response($conn, 'Facility updated.');
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_updated=1");
    } else {
        error_log('[PUPSync] update_room DB update failed: ' . $conn->error);
        $stmt->close();
        if ($is_ajax) ps_send_rooms_ajax_error('Could not save the changes. Please try again.');
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_error=1");
    }
    exit();
}


// ════════════════════════════════════════════════════════════════
// LOCK / UNLOCK ROOM  (POST toggle_room_lock, room_id, lock=1|0)
// Admin + Super Admin (the page already requires an admin session).
// A locked room's tile no longer opens its schedule; Edit still works.
// ════════════════════════════════════════════════════════════════
if (isset($_POST['toggle_room_lock'])) {
    csrf_verify();
    $is_ajax = ($_POST['ajax'] ?? '') === '1';
    $room_id = intval($_POST['room_id'] ?? 0);
    $lock    = (($_POST['lock'] ?? '') === '1') ? 1 : 0;

    if ($room_id <= 0) {
        if ($is_ajax) ps_send_rooms_ajax_error('Facility not found.');
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_error=1");
        exit();
    }
    if (!ps_rooms_lock_column_ready($conn)) {
        if ($is_ajax) ps_send_rooms_ajax_error('Facility locking is not available yet. Please run the facility lock database migration.');
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_error=1");
        exit();
    }

    // Only active (non-archived) rooms can be locked / unlocked.
    $stmt = $conn->prepare("SELECT 1 FROM tbl_rooms WHERE room_id = ? AND is_archived = 0 LIMIT 1");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if (!$exists) {
        if ($is_ajax) ps_send_rooms_ajax_error('Facility not found.');
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_error=1");
        exit();
    }

    $stmt = $conn->prepare("UPDATE tbl_rooms SET is_locked = ? WHERE room_id = ? AND is_archived = 0");
    $stmt->bind_param('ii', $lock, $room_id);
    $stmt->execute();
    $stmt->close();
    admin_log_activity($conn, 'room', ($lock ? 'Locked facility: ' : 'Unlocked facility: ') . admin_activity_room_name($conn, $room_id));
    if ($is_ajax) ps_send_rooms_ajax_response($conn, $lock ? 'Facility locked.' : 'Facility unlocked.');
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header("Location: {$base}/admin-dashboard.php?tab=rooms&room_updated=1");
    exit();
}


// ════════════════════════════════════════════════════════════════
// ARCHIVE ROOM  (soft-delete, GET ?archive_room=id)
// Super Admin only — checked against the database, not just the session.
// ════════════════════════════════════════════════════════════════
if (isset($_GET['archive_room'])) {
    if (!admin_is_super_admin($conn)) {
        ps_rooms_forbidden(($_GET['ajax'] ?? '') === '1', 'Only a Super Admin can archive facilities.');
    }
    $room_id = intval($_GET['archive_room']);
    $stmt = $conn->prepare("UPDATE tbl_rooms SET is_archived = 1 WHERE room_id = ?");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $stmt->close();
    admin_log_activity($conn, 'room', 'Archived facility: ' . admin_activity_room_name($conn, $room_id));
    if (($_GET['ajax'] ?? '') === '1') ps_send_rooms_ajax_response($conn, 'Facility archived.');
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header("Location: {$base}/admin-dashboard.php?tab=rooms&room_archived=1");
    exit();
}


// ════════════════════════════════════════════════════════════════
// RESTORE ROOM  (GET ?restore_room=id)
// ════════════════════════════════════════════════════════════════
if (isset($_GET['restore_room'])) {
    $room_id = intval($_GET['restore_room']);
    $stmt = $conn->prepare("UPDATE tbl_rooms SET is_archived = 0 WHERE room_id = ?");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $stmt->close();
    admin_log_activity($conn, 'room', 'Restored facility: ' . admin_activity_room_name($conn, $room_id));
    if (($_GET['ajax'] ?? '') === '1') ps_send_rooms_ajax_response($conn, 'Facility restored.');
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header("Location: {$base}/admin-dashboard.php?tab=rooms&room_restored=1");
    exit();
}


// ════════════════════════════════════════════════════════════════
// PERMANENTLY DELETE ROOM  (POST delete_room_permanently)
// Super Admin only. Only works on a room that is ALREADY archived, and
// needs confirm=yes so a stray request can never remove a room.
// Everything pointing at the room is removed first (the database will not
// let a room go while reservations, issues or waitlist rows reference it);
// equipment requests that mention the room are kept and simply unlinked.
// All-or-nothing: one transaction, rolled back on any failure.
// Same order and rules as database/migration/2026_10_delete_archived_rooms.sql
// ════════════════════════════════════════════════════════════════
if (isset($_POST['delete_room_permanently'])) {
    csrf_verify();
    $is_ajax = ($_POST['ajax'] ?? '') === '1';

    if (!admin_is_super_admin($conn)) {
        ps_rooms_forbidden($is_ajax, 'Only a Super Admin can permanently delete facilities.');
    }

    $room_id = intval($_POST['room_id'] ?? 0);
    $confirm = ($_POST['confirm'] ?? '') === 'yes';
    $fail = function (string $msg) use ($is_ajax) {
        if ($is_ajax) ps_send_rooms_ajax_error($msg);
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_error=1");
        exit();
    };

    if ($room_id <= 0 || !$confirm) $fail('Deletion was not confirmed.');

    // Must exist AND already be archived
    $stmt = $conn->prepare("SELECT building_id, room_name FROM tbl_rooms WHERE room_id = ? AND is_archived = 1 LIMIT 1");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$target) $fail('Only archived facilities can be permanently deleted.');

    $room_name   = (string)$target['room_name'];
    $building_id = (int)$target['building_id'];

    try {
        $conn->begin_transaction();

        foreach (['tbl_room_waitlist', 'tbl_room_issues', 'tbl_room_reservations', 'tbl_room_arbitration_log'] as $child) {
            if (!ps_rooms_table_exists($conn, $child)) continue;
            $st = $conn->prepare("DELETE FROM {$child} WHERE room_id = ?");
            $st->bind_param('i', $room_id);
            $st->execute();
            $st->close();
        }

        // Equipment requests that were linked to this room are kept, just unlinked.
        $st = $conn->prepare("UPDATE tbl_requests SET room_id = NULL WHERE room_id = ?");
        $st->bind_param('i', $room_id);
        $st->execute();
        $st->close();

        $st = $conn->prepare("DELETE FROM tbl_rooms WHERE room_id = ? AND is_archived = 1");
        $st->bind_param('i', $room_id);
        $st->execute();
        $deleted = $st->affected_rows;
        $st->close();
        if ($deleted !== 1) throw new RuntimeException('Facility row was not deleted.');

        // A building's floor count is its real number of floors (it caps which floors a facility
        // can be added to), so deleting the top-floor facility must not lower it.

        $conn->commit();
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $e2) { /* nothing to roll back */
        }
        error_log('[PUPSync] delete_room_permanently failed: ' . $e->getMessage());
        $fail('Could not delete the facility. Nothing was changed.');
    }

    admin_log_activity($conn, 'room', 'Permanently deleted facility: ' . $room_name);
    if ($is_ajax) ps_send_rooms_ajax_response($conn, 'Facility permanently deleted.');
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header("Location: {$base}/admin-dashboard.php?tab=rooms&room_deleted=1");
    exit();
}


// ════════════════════════════════════════════════════════════════
// SET ROOM STATUS DIRECTLY  (GET ?set_room_status=id&status=...)
// Admin shortcut to toggle Maintenance / Not Bookable / Available
// without opening the full edit form.
// ════════════════════════════════════════════════════════════════
if (isset($_GET['set_room_status'], $_GET['room_status_id'])) {
    $room_id       = intval($_GET['room_status_id']);
    $new_status    = $_GET['set_room_status'];
    $allowed       = ['Available', 'Maintenance', 'Not Bookable'];
    if (in_array($new_status, $allowed, true) && $room_id > 0) {
        $stmt = $conn->prepare(
            "UPDATE tbl_rooms SET status = ? WHERE room_id = ? AND is_archived = 0"
        );
        $stmt->bind_param('si', $new_status, $room_id);
        $stmt->execute();
        $stmt->close();
        admin_log_activity($conn, 'room', 'Set ' . admin_activity_room_name($conn, $room_id) . ' to ' . $new_status);
    }
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header("Location: {$base}/admin-dashboard.php?tab=rooms&room_updated=1");
    exit();
}

