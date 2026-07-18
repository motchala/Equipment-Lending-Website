<?php
/**
 * admin-rooms-functions.php
 * Room Registry — admin-side CRUD handlers for tbl_campuses, tbl_buildings, tbl_rooms.
 * Included by admin-dashboard.php immediately after admin-functions.php.
 *
 * DEPENDENCY: equipment-booking/core/admin-functions.php MUST be included first.
 *   That file sets up $conn (mysqli), $root_url, csrf_verify(), and the session guard.
 *   This file does NOT call session_start(), getDB(), or any config bootstrapping itself.
 *
 * Conventions match equipment-booking/core/admin-functions.php exactly:
 *   - ini_set / error_reporting set at top
 *   - Session guard already run by admin-functions.php (included first)
 *   - $conn already available from admin-functions.php
 *   - All POST handlers call csrf_verify() before touching input
 *   - All user-input DB access uses prepared statements with bind_param
 *   - Redirect after POST with ?room_added=1 / ?room_updated=1 etc.
 *   - Soft-delete (is_archived = 1) — no permanent deletes
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// ── Dependency guard ─────────────────────────────────────────────────────
// $conn must already be set by equipment-booking/core/admin-functions.php.
// If it is not, the include order is wrong — fail loudly rather than
// producing a silent "Call to a member function prepare() on null" later.
if (empty($conn) || !($conn instanceof mysqli)) {
    error_log('[PUPSync] admin-rooms-functions.php included before admin-functions.php — $conn not available.');
    http_response_code(500);
    exit('Server configuration error. Please contact the administrator.');
}

// $conn and $root_url are already set by admin-functions.php


// ════════════════════════════════════════════════════════════════
// ADD ROOM
// ════════════════════════════════════════════════════════════════
if (isset($_POST['add_room'])) {
    csrf_verify();

    $building_id      = intval($_POST['building_id'] ?? 0);
    $room_name        = trim($_POST['room_name'] ?? '');
    $floor_number     = intval($_POST['floor_number'] ?? 1);
    $floor_label      = trim($_POST['floor_label'] ?? '');
    $seating_capacity = $_POST['seating_capacity'] !== '' ? intval($_POST['seating_capacity']) : null;
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
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_error=1");
        exit();
    }

    $stmt = $conn->prepare(
        "INSERT INTO tbl_rooms
            (building_id, room_name, floor_number, floor_label,
             seating_capacity, amenities, status, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'isisisis',
        $building_id, $room_name, $floor_number, $floor_label,
        $seating_capacity, $amenities_json, $status, $sort_order
    );

    if ($stmt->execute()) {
        $stmt->close();
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_added=1");
    } else {
        error_log('[PUPSync] add_room DB insert failed: ' . $conn->error);
        $stmt->close();
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

    $room_id          = intval($_POST['room_id'] ?? 0);
    $building_id      = intval($_POST['building_id'] ?? 0);
    $room_name        = trim($_POST['room_name'] ?? '');
    $floor_number     = intval($_POST['floor_number'] ?? 1);
    $floor_label      = trim($_POST['floor_label'] ?? '');
    $seating_capacity = ($_POST['seating_capacity'] ?? '') !== '' ? intval($_POST['seating_capacity']) : null;
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
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_error=1");
        exit();
    }

    $stmt = $conn->prepare(
        "UPDATE tbl_rooms
         SET building_id = ?, room_name = ?, floor_number = ?, floor_label = ?,
             seating_capacity = ?, amenities = ?, status = ?, sort_order = ?
         WHERE room_id = ? AND is_archived = 0"
    );
    $stmt->bind_param(
        'isisissii',
        $building_id, $room_name, $floor_number, $floor_label,
        $seating_capacity, $amenities_json, $status, $sort_order,
        $room_id
    );

    if ($stmt->execute()) {
        $stmt->close();
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_updated=1");
    } else {
        error_log('[PUPSync] update_room DB update failed: ' . $conn->error);
        $stmt->close();
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_error=1");
    }
    exit();
}


// ════════════════════════════════════════════════════════════════
// ARCHIVE ROOM  (soft-delete, GET ?archive_room=id)
// ════════════════════════════════════════════════════════════════
if (isset($_GET['archive_room'])) {
    $room_id = intval($_GET['archive_room']);
    $stmt = $conn->prepare("UPDATE tbl_rooms SET is_archived = 1 WHERE room_id = ?");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $stmt->close();
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
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header("Location: {$base}/admin-dashboard.php?tab=rooms&room_restored=1");
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
    }
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header("Location: {$base}/admin-dashboard.php?tab=rooms&room_updated=1");
    exit();
}


// ════════════════════════════════════════════════════════════════
// FETCH DATA FOR ROOMS PANEL
// Loaded unconditionally so the panel always has data ready.
// ════════════════════════════════════════════════════════════════

// All campuses
$rooms_campuses_result = $conn->query(
    "SELECT campus_id, campus_key, campus_name
     FROM tbl_campuses
     ORDER BY campus_id ASC"
);
$rooms_campuses = [];
if ($rooms_campuses_result) {
    while ($row = $rooms_campuses_result->fetch_assoc()) {
        $rooms_campuses[$row['campus_id']] = $row;
    }
}

// All buildings (with campus name for display)
$rooms_buildings_result = $conn->query(
    "SELECT b.building_id, b.campus_id, b.building_key, b.name,
            b.wing, b.floor_count, b.icon, b.description, b.sort_order,
            c.campus_name
     FROM tbl_buildings b
     JOIN tbl_campuses c ON c.campus_id = b.campus_id
     ORDER BY b.campus_id ASC, b.sort_order ASC, b.building_id ASC"
);
$rooms_buildings = [];
if ($rooms_buildings_result) {
    while ($row = $rooms_buildings_result->fetch_assoc()) {
        $rooms_buildings[$row['building_id']] = $row;
    }
}

// All active rooms joined to building + campus
$rooms_list_result = $conn->query(
    "SELECT r.room_id, r.building_id, r.room_name, r.floor_number,
            r.floor_label, r.seating_capacity, r.amenities, r.status,
            r.sort_order, r.is_archived,
            b.name AS building_name, b.campus_id,
            c.campus_name
     FROM tbl_rooms r
     JOIN tbl_buildings b ON b.building_id = r.building_id
     JOIN tbl_campuses  c ON c.campus_id  = b.campus_id
     WHERE r.is_archived = 0
     ORDER BY b.campus_id ASC, r.building_id ASC,
              r.floor_number ASC, r.sort_order ASC, r.room_id ASC"
);
$rooms_list = [];
if ($rooms_list_result) {
    while ($row = $rooms_list_result->fetch_assoc()) {
        $rooms_list[] = $row;
    }
}

// Room counts per building (for stats display)
$rooms_count_result = $conn->query(
    "SELECT building_id, COUNT(*) AS room_count
     FROM tbl_rooms
     WHERE is_archived = 0
     GROUP BY building_id"
);
$rooms_count_by_building = [];
if ($rooms_count_result) {
    while ($row = $rooms_count_result->fetch_assoc()) {
        $rooms_count_by_building[$row['building_id']] = (int) $row['room_count'];
    }
}

// Pre-fetch room being edited (if ?edit_room=id is set)
$edit_room = null;
if (isset($_GET['edit_room'])) {
    $edit_room_id = intval($_GET['edit_room']);
    $stmt_er = $conn->prepare(
        "SELECT r.*, b.campus_id
         FROM tbl_rooms r
         JOIN tbl_buildings b ON b.building_id = r.building_id
         WHERE r.room_id = ? AND r.is_archived = 0 LIMIT 1"
    );
    $stmt_er->bind_param('i', $edit_room_id);
    $stmt_er->execute();
    $edit_room = $stmt_er->get_result()->fetch_assoc();
    $stmt_er->close();
}

// Stats for the rooms panel header
$stat_rooms_total       = count($rooms_list);
$stat_rooms_available   = 0;
$stat_rooms_maintenance = 0;
$stat_rooms_notbookable = 0;
foreach ($rooms_list as $r) {
    if ($r['status'] === 'Available')    $stat_rooms_available++;
    if ($r['status'] === 'Maintenance')  $stat_rooms_maintenance++;
    if ($r['status'] === 'Not Bookable') $stat_rooms_notbookable++;
}

// Archived rooms (for the Archived sub-panel)
$rooms_archived_result = $conn->query(
    "SELECT r.room_id, r.room_name, r.floor_number, r.floor_label,
            r.status, b.name AS building_name, c.campus_name
     FROM tbl_rooms r
     JOIN tbl_buildings b ON b.building_id = r.building_id
     JOIN tbl_campuses  c ON c.campus_id  = b.campus_id
     WHERE r.is_archived = 1
     ORDER BY c.campus_name ASC, b.name ASC,
              r.floor_number ASC, r.room_name ASC"
);
$rooms_archived = [];
if ($rooms_archived_result) {
    while ($row = $rooms_archived_result->fetch_assoc()) {
        $rooms_archived[] = $row;
    }
}
