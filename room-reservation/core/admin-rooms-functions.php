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
// AJAX helpers — Rooms Registry
// Added so add/update/archive can respond with fresh table HTML
// instead of a full-page redirect, without duplicating the
// buildings/rooms queries or the row-rendering markup anywhere.
// The non-AJAX behavior below (header("Location: ...")) is
// completely unchanged and still runs whenever these aren't used —
// this is purely additive.
// ════════════════════════════════════════════════════════════════

/**
 * Re-fetch the buildings + active-rooms lists fresh from the DB.
 * Same two queries the page normally runs on load; pulled out here
 * so an AJAX handler can re-run them right after a mutation without
 * a page reload.
 */
function ps_load_rooms_and_buildings(mysqli $conn): array
{
    $buildings_result = $conn->query(
        "SELECT b.building_id, b.campus_id, b.building_key, b.name,
                b.wing, b.floor_count, b.icon, b.description, b.sort_order,
                c.campus_name
         FROM tbl_buildings b
         JOIN tbl_campuses c ON c.campus_id = b.campus_id
         ORDER BY b.campus_id ASC, b.sort_order ASC, b.building_id ASC"
    );
    $buildings = [];
    if ($buildings_result) {
        while ($row = $buildings_result->fetch_assoc()) {
            $buildings[$row['building_id']] = $row;
        }
    }

    $rooms_result = $conn->query(
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
    $rooms = [];
    if ($rooms_result) {
        while ($row = $rooms_result->fetch_assoc()) {
            $rooms[] = $row;
        }
    }

    return [$buildings, $rooms];
}

/**
 * Group $rooms_list by building → floor and assign each building a
 * display accent color. Identical logic to what admin-dashboard.php
 * used to do inline — kept here so both the normal page render and
 * the AJAX responses compute it the exact same way.
 */
function ps_prepare_rooms_registry_view(array $rooms_buildings, array $rooms_list): array
{
    $rooms_grouped = [];
    foreach ($rooms_list as $r) {
        $bId  = $r['building_id'];
        $fNum = $r['floor_number'];
        if (!isset($rooms_grouped[$bId])) $rooms_grouped[$bId] = [];
        if (!isset($rooms_grouped[$bId][$fNum])) {
            $rooms_grouped[$bId][$fNum] = [
                'label' => !empty($r['floor_label']) ? $r['floor_label'] : ($fNum . 'F'),
                'rooms' => [],
            ];
        }
        $rooms_grouped[$bId][$fNum]['rooms'][] = $r;
    }
    foreach ($rooms_grouped as $bId => $floorsTmp) {
        ksort($rooms_grouped[$bId]);
    }

    $floors_by_building_js = [];
    foreach ($rooms_buildings as $bid => $b) {
        $floors_by_building_js[$bid] = [];
        foreach (($rooms_grouped[$bid] ?? []) as $fNum => $fData) {
            $floors_by_building_js[$bid][] = ['num' => (int)$fNum, 'label' => $fData['label']];
        }
    }

    $building_palette = ['var(--building-color-1)', 'var(--building-color-2)', 'var(--building-color-3)', 'var(--building-color-4)'];
    $building_accent = [];
    $bi = 0;
    foreach ($rooms_buildings as $bid => $b) {
        $building_accent[$bid] = $building_palette[$bi % count($building_palette)];
        $bi++;
    }

    return [
        'grouped'            => $rooms_grouped,
        'floors_by_building' => $floors_by_building_js,
        'first_building_id'  => array_key_first($rooms_buildings),
        'accent'             => $building_accent,
    ];
}

/**
 * Render the #roomsRegistryTable <tbody> rows to a string — the single
 * source of truth for what a room row looks like. Called from exactly
 * two places: admin-dashboard.php echoes this directly on a normal page
 * load, and ps_send_rooms_ajax_response() below puts the same return
 * value into a JSON response after add/update/archive. Keeping it as
 * one function (rather than duplicating this markup at both call
 * sites) is the whole reason it's reusable — either page can call it
 * and get byte-identical rows.
 */
function ps_render_rooms_tbody(array $rooms_buildings, array $rooms_grouped, array $building_accent): string
{
    ob_start();
    foreach ($rooms_buildings as $bid => $b):
        $floors = $rooms_grouped[$bid] ?? [];
        $isFirstFloorOfBuilding = true;
        foreach ($floors as $fNum => $fData): ?>
            <tr class="pr-floor-divider<?php echo $isFirstFloorOfBuilding ? ' pr-floor-divider-first' : ''; ?>" data-building-id="<?php echo (int)$bid; ?>" data-floor-num="<?php echo (int)$fNum; ?>">
                <td colspan="4" style="--b-accent: <?php echo $building_accent[$bid]; ?>;">
                    <span class="material-symbols-outlined pr-building-icon"><?php echo htmlspecialchars($b['icon'] ?: 'domain'); ?></span>
                    <?php echo htmlspecialchars($b['name'] . ' — ' . $fData['label']); ?>
                    <span class="pr-floor-count"><?php echo count($fData['rooms']); ?> room<?php echo count($fData['rooms']) !== 1 ? 's' : ''; ?></span>
                </td>
            </tr>
            <?php $isFirstFloorOfBuilding = false; ?>
            <?php foreach ($fData['rooms'] as $room):
                $rc_status_cls = 'avail';
                if ($room['status'] === 'Maintenance')  $rc_status_cls = 'maint';
                if ($room['status'] === 'Not Bookable') $rc_status_cls = 'nobk';
                $fl = $fData['label'];
            ?>
                <tr class="pr-room-row pr-row-<?php echo $rc_status_cls; ?>"
                    data-room-id="<?php echo (int)$room['room_id']; ?>"
                    data-room-name="<?php echo htmlspecialchars($room['room_name'], ENT_QUOTES); ?>"
                    data-room-campus="<?php echo htmlspecialchars($room['campus_name'], ENT_QUOTES); ?>"
                    data-room-floor="<?php echo htmlspecialchars($fl, ENT_QUOTES); ?>"
                    data-room-building="<?php echo htmlspecialchars($room['building_name'], ENT_QUOTES); ?>"
                    data-room-capacity="<?php echo $room['seating_capacity'] !== null ? (int)$room['seating_capacity'] : ''; ?>"
                    data-room-building-id="<?php echo (int)$room['building_id']; ?>"
                    data-room-floor-num="<?php echo (int)$room['floor_number']; ?>"
                    data-room-floor-label="<?php echo htmlspecialchars($room['floor_label'] ?? '', ENT_QUOTES); ?>"
                    data-room-status="<?php echo htmlspecialchars($room['status'], ENT_QUOTES); ?>"
                    data-room-sort="<?php echo (int)$room['sort_order']; ?>"
                    data-room-amenities="<?php
                                            $am_raw = isset($room['amenities']) && $room['amenities'] ? $room['amenities'] : '[]';
                                            $am_arr = json_decode($am_raw, true);
                                            echo htmlspecialchars(json_encode(is_array($am_arr) ? $am_arr : []), ENT_QUOTES);
                                            ?>">
                    <td class="td-fw"><?php echo htmlspecialchars($room['room_name']); ?></td>
                    <td class="td-sm"><?php echo $room['seating_capacity'] !== null ? (int)$room['seating_capacity'] : '—'; ?></td>
                    <td><span class="rc-status <?php echo $rc_status_cls; ?>"><?php echo htmlspecialchars($room['status']); ?></span></td>
                    <td class="pr-row-actions">
                        <button type="button" class="pr-icon-btn" data-action="open-room-schedule" title="View schedule">
                            <span class="material-symbols-outlined">calendar_month</span>
                        </button>
                        <button type="button" class="pr-icon-btn" data-action="edit-room-inline" title="Edit room">
                            <span class="material-symbols-outlined">edit</span>
                        </button>
                        <a href="admin-dashboard.php?archive_room=<?php echo (int)$room['room_id']; ?>"
                            class="pr-icon-btn danger"
                            title="Archive room">
                            <span class="material-symbols-outlined">archive</span>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
    <?php endforeach;
    endforeach; ?>
    <tr class="pr-no-match-row" style="display:none;">
        <td colspan="4" style="text-align:center;padding:2.5rem;color:var(--text-light);">
            No rooms match your filters.
        </td>
    </tr>
<?php
    return ob_get_clean();
}

/**
 * Common AJAX success response for add/update/archive: re-fetch the
 * current room data, re-render the table body, and send it back as
 * JSON so the client can swap it in without a page reload.
 * Always exits — never returns.
 */
function ps_send_rooms_ajax_response(mysqli $conn, string $message): void
{
    [$buildings, $rooms] = ps_load_rooms_and_buildings($conn);
    $rv   = ps_prepare_rooms_registry_view($buildings, $rooms);
    $html = ps_render_rooms_tbody($buildings, $rv['grouped'], $rv['accent']);

    header('Content-Type: application/json');
    echo json_encode([
        'status'             => 'success',
        'message'            => $message,
        'html'               => $html,
        'floors_by_building' => $rv['floors_by_building'],
    ]);
    exit();
}

function ps_send_rooms_ajax_error(string $message): void
{
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}


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
        if ($is_ajax) ps_send_rooms_ajax_error('Please choose a building and enter a room name.');
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
        $building_id,
        $room_name,
        $floor_number,
        $floor_label,
        $seating_capacity,
        $amenities_json,
        $status,
        $sort_order
    );

    if ($stmt->execute()) {
        $stmt->close();
        if ($is_ajax) ps_send_rooms_ajax_response($conn, 'Room added.');
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        header("Location: {$base}/admin-dashboard.php?tab=rooms&room_added=1");
    } else {
        error_log('[PUPSync] add_room DB insert failed: ' . $conn->error);
        $stmt->close();
        if ($is_ajax) ps_send_rooms_ajax_error('Could not save the room. Please try again.');
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
        if ($is_ajax) ps_send_rooms_ajax_error('Please choose a building and enter a room name.');
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
        $building_id,
        $room_name,
        $floor_number,
        $floor_label,
        $seating_capacity,
        $amenities_json,
        $status,
        $sort_order,
        $room_id
    );

    if ($stmt->execute()) {
        $stmt->close();
        if ($is_ajax) ps_send_rooms_ajax_response($conn, 'Room updated.');
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
// ARCHIVE ROOM  (soft-delete, GET ?archive_room=id)
// ════════════════════════════════════════════════════════════════
if (isset($_GET['archive_room'])) {
    $room_id = intval($_GET['archive_room']);
    $stmt = $conn->prepare("UPDATE tbl_rooms SET is_archived = 1 WHERE room_id = ?");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $stmt->close();
    if (($_GET['ajax'] ?? '') === '1') ps_send_rooms_ajax_response($conn, 'Room archived.');
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

// ── All room reservations (admin read-only view) ──────────────────────────
$admin_room_reservations_result = $conn->query(
    "SELECT rr.id, rr.reservation_date,
            TIME_FORMAT(rr.start_time, '%h:%i %p') AS start_fmt,
            TIME_FORMAT(rr.end_time,   '%h:%i %p') AS end_fmt,
            rr.faculty_name, rr.submitted_as, rr.submitted_by_name,
            rr.purpose, rr.attendees, rr.status, rr.reason,
            rr.request_date,
            r.room_name,
            b.name AS building_name,
            COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label,
            c.campus_name
       FROM tbl_room_reservations rr
       JOIN tbl_rooms     r ON r.room_id     = rr.room_id
       JOIN tbl_buildings b ON b.building_id = r.building_id
       JOIN tbl_campuses  c ON c.campus_id   = b.campus_id
      ORDER BY rr.reservation_date DESC, rr.start_time ASC"
);
$admin_room_reservations = [];
if ($admin_room_reservations_result) {
    while ($row = $admin_room_reservations_result->fetch_assoc()) {
        $admin_room_reservations[] = $row;
    }
}

// ── Room Issues (admin review queue) ─────────────────────────────────────
// Guard: table may not exist yet if migration hasn't been run.
$_issues_tbl = $conn->query(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_room_issues' LIMIT 1"
);
$admin_room_issues       = [];
$admin_room_issues_open  = 0;

if ($_issues_tbl && $_issues_tbl->num_rows > 0) {
    $issues_result = $conn->query(
        "SELECT ri.id, ri.room_id, ri.reported_by_id, ri.reported_by_name,
                ri.description, ri.status, ri.admin_notes,
                ri.created_at, ri.resolved_at,
                r.room_name,
                b.name                                               AS building_name,
                COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label,
                c.campus_name
           FROM tbl_room_issues ri
           JOIN tbl_rooms     r ON r.room_id     = ri.room_id
           JOIN tbl_buildings b ON b.building_id = r.building_id
           JOIN tbl_campuses  c ON c.campus_id   = b.campus_id
          ORDER BY
                FIELD(ri.status, 'Open', 'Resolved', 'Dismissed'),
                ri.created_at DESC"
    );
    if ($issues_result) {
        while ($row = $issues_result->fetch_assoc()) {
            $admin_room_issues[] = $row;
            if ($row['status'] === 'Open') $admin_room_issues_open++;
        }
    }
}
