<?php
require_once __DIR__ . '/../../config/admin-activity.php'; // activity log helpers

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
 *   - Soft-delete (is_archived = 1) first; permanent delete is a separate,
 *     Super-Admin-only action that only works on rooms already archived
 *   - Room lock (is_locked): Admin + Super Admin; archive / permanent delete: Super Admin only
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
// ROOM LOCK — schema helper
// tbl_rooms.is_locked (TINYINT(1), default 0). Added automatically
// the first time this file runs (same self-healing approach as
// config/admin-activity.php), and also shipped as
// database/migration/2026_10_room_lock.sql for manual use.
// If the column cannot be added (e.g. the DB user has no ALTER
// right) every query below falls back to "not locked" and the lock
// action returns a clear error — nothing else in Rooms is affected.
// ════════════════════════════════════════════════════════════════
function ps_rooms_lock_column_ready(mysqli $conn): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;

    $has = function () use ($conn): bool {
        $res = $conn->query(
            "SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_rooms'
                AND COLUMN_NAME = 'is_locked' LIMIT 1"
        );
        return $res && $res->num_rows > 0;
    };

    $ready = $has();
    if (!$ready) {
        try {
            $conn->query("ALTER TABLE tbl_rooms ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER is_archived");
        } catch (Throwable $e) {
            error_log('[PUPSync] could not add tbl_rooms.is_locked: ' . $e->getMessage());
        }
        $ready = $has();
    }
    return $ready;
}

/** SELECT fragment for the lock flag — a real column, or a constant 0 if the column is missing. */
function ps_rooms_lock_select(mysqli $conn): string
{
    return ps_rooms_lock_column_ready($conn) ? 'r.is_locked' : '0 AS is_locked';
}

/** Does a table exist? (used to skip optional child tables during permanent delete) */
function ps_rooms_table_exists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare(
        "SELECT 1 FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
    );
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $found;
}

/** Shared "not allowed" reply for the Super-Admin-only actions below. Always exits. */
function ps_rooms_forbidden(bool $is_ajax, string $message, string $tab_query = 'room_error=1'): void
{
    http_response_code(403);
    if ($is_ajax) ps_send_rooms_ajax_error($message);
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header("Location: {$base}/admin-dashboard.php?tab=rooms&{$tab_query}");
    exit();
}


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

    $lock_sel = ps_rooms_lock_select($conn);
    $rooms_result = $conn->query(
        "SELECT r.room_id, r.building_id, r.room_name, r.floor_number,
                r.floor_label, r.amenities, r.status,
                r.sort_order, r.is_archived, {$lock_sel},
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
 * Display helper for the room tiles (presentation only — no DB access).
 * ps_room_status_icon(): the icon shown on a tile's badge. The SHAPE differs
 * per status (not just the colour) so status is readable without colour.
 */
if (!function_exists('ps_room_status_icon')) {
    function ps_room_status_icon($status): string
    {
        if ($status === 'Maintenance')  return 'construction';
        if ($status === 'Not Bookable') return 'block'; // not 'lock' — the lock icon is reserved for Locked rooms
        return 'meeting_room';
    }
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
                <td colspan="3">
                    <span class="rm-div-bld"><?php echo htmlspecialchars($b['name']); ?><span class="rm-div-sep">&middot;</span></span>
                    <span class="rm-div-floor"><?php echo htmlspecialchars($fData['label']); ?></span>
                    <span class="pr-floor-count" aria-live="polite"><?php $fc = count($fData['rooms']);
                                                                    echo $fc . ($fc === 1 ? ' facility' : ' facilities'); ?></span>
                </td>
            </tr>
            <?php $isFirstFloorOfBuilding = false; ?>
            <?php foreach ($fData['rooms'] as $room):
                $rc_status_cls = 'avail';
                if ($room['status'] === 'Maintenance')  $rc_status_cls = 'maint';
                if ($room['status'] === 'Not Bookable') $rc_status_cls = 'nobk';
                $fl = $fData['label'];
                $rc_locked = !empty($room['is_locked']);
            ?>
                <tr class="pr-room-row pr-row-<?php echo $rc_status_cls; ?><?php echo $rc_locked ? ' pr-row-locked' : ''; ?>"
                    tabindex="<?php echo $rc_locked ? '-1' : '0'; ?>"
                    title="<?php echo $rc_locked ? 'Locked' : 'View schedule'; ?>"
                    data-room-locked="<?php echo $rc_locked ? '1' : '0'; ?>"
                    data-room-id="<?php echo (int)$room['room_id']; ?>"
                    data-room-name="<?php echo htmlspecialchars($room['room_name'], ENT_QUOTES); ?>"
                    data-room-campus="<?php echo htmlspecialchars($room['campus_name'], ENT_QUOTES); ?>"
                    data-room-floor="<?php echo htmlspecialchars($fl, ENT_QUOTES); ?>"
                    data-room-building="<?php echo htmlspecialchars($room['building_name'], ENT_QUOTES); ?>"
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
                    <td class="rm-ico" title="<?php echo htmlspecialchars($room['status']); ?>">
                        <span class="material-symbols-outlined"><?php echo ps_room_status_icon($room['status']); ?></span>
                    </td>
                    <td class="td-fw rm-name" title="<?php echo htmlspecialchars($room['room_name']); ?>"><?php echo htmlspecialchars($room['room_name']); ?></td>
                    <td class="rm-stat"><span class="rc-status <?php echo $rc_status_cls; ?>"><?php echo htmlspecialchars($room['status']); ?></span><?php if ($rc_locked): ?><span class="rc-lock" title="Locked: the schedule can't be opened"><span class="material-symbols-outlined">lock</span>Locked</span><?php endif; ?></td>
                    <td class="pr-row-actions">
                        <button type="button" class="pr-icon-btn" data-action="edit-room-inline" title="Edit facility" aria-label="Edit facility">
                            <span class="material-symbols-outlined">edit</span>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
    <?php endforeach;
    endforeach; ?>
    <tr class="pr-no-match-row" style="display:none;">
        <td colspan="4" class="rm-nomatch">
            <span class="material-symbols-outlined">search_off</span>
            No facilities match your filters.
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

/**
 * Archived rooms (newest grouping: campus → building → floor → name), with how
 * many reservations each one holds (shown in the permanent-delete confirmation).
 */
function ps_load_archived_rooms(mysqli $conn): array
{
    $result = $conn->query(
        "SELECT r.room_id, r.room_name, r.floor_number, r.floor_label,
                r.status, b.name AS building_name, c.campus_name,
                (SELECT COUNT(*) FROM tbl_room_reservations x WHERE x.room_id = r.room_id) AS reservation_count
         FROM tbl_rooms r
         JOIN tbl_buildings b ON b.building_id = r.building_id
         JOIN tbl_campuses  c ON c.campus_id  = b.campus_id
         WHERE r.is_archived = 1
         ORDER BY c.campus_name ASC, b.name ASC,
                  r.floor_number ASC, r.room_name ASC"
    );
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * Render the Archived Rooms <tbody> rows. Single source of truth, like
 * ps_render_rooms_tbody(): echoed on page load and returned in AJAX responses so
 * the Archived tab is always current right after an archive / restore / delete.
 * $can_delete = Super Admin only (shows the permanent-delete button).
 */
function ps_render_archived_tbody(array $rooms_archived, bool $can_delete): string
{
    ob_start();
    if (empty($rooms_archived)): ?>
        <tr>
            <td colspan="2" class="rm-empty-cell">No archived facilities.</td>
        </tr>
        <?php else: foreach ($rooms_archived as $ar):
            $ar_floor = !empty($ar['floor_label']) ? $ar['floor_label'] : $ar['floor_number'] . 'F';
        ?>
            <tr>
                <td>
                    <div class="rm-main"><?php echo htmlspecialchars($ar['room_name']); ?></div>
                    <div class="rm-subline"><?php echo htmlspecialchars($ar_floor . ', ' . $ar['building_name']); ?></div>
                </td>
                <td>
                    <div class="rm-actions">
                        <a href="admin-dashboard.php?restore_room=<?php echo (int)$ar['room_id']; ?>" class="pr-tbl-btn restore">
                            <span class="material-symbols-outlined" style="font-size:14px;">unarchive</span>
                            Restore
                        </a>
                        <?php if ($can_delete): ?>
                            <button type="button" class="pr-tbl-btn danger" data-action="delete-room-permanent"
                                data-room-id="<?php echo (int)$ar['room_id']; ?>"
                                data-room-name="<?php echo htmlspecialchars($ar['room_name'], ENT_QUOTES); ?>"
                                data-reservations="<?php echo (int)($ar['reservation_count'] ?? 0); ?>">
                                <span class="material-symbols-outlined" style="font-size:14px;">delete_forever</span>
                                Delete permanently
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
<?php endforeach;
    endif;
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

    $archived = ps_load_archived_rooms($conn);

    header('Content-Type: application/json');
    echo json_encode([
        'status'             => 'success',
        'message'            => $message,
        'html'               => $html,
        'floors_by_building' => $rv['floors_by_building'],
        'archived_html'      => ps_render_archived_tbody($archived, admin_is_super_admin($conn)),
        'archived_count'     => count($archived),
    ]);
    exit();
}

function ps_send_rooms_ajax_error(string $message): void
{
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

/**
 * How many floors a building has (tbl_buildings.floor_count); 0 when the building does not exist.
 * A facility's floor must be between 1 and this number.
 */
function ps_building_floor_limit(mysqli $conn, int $building_id): int
{
    $st = $conn->prepare('SELECT floor_count FROM tbl_buildings WHERE building_id = ?');
    if (!$st) return 0;
    $st->bind_param('i', $building_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ? (int)$row['floor_count'] : 0;
}

/** Error text when $floor is outside the building's floors, or null when it is fine. */
function ps_floor_limit_error(mysqli $conn, int $building_id, int $floor): ?string
{
    $max = ps_building_floor_limit($conn, $building_id);
    if ($max < 1) return 'Please choose a valid building.';
    if ($floor < 1 || $floor > $max) {
        return 'This building only has ' . ($max === 1 ? 'floor 1' : 'floors 1 to ' . $max) . '. Please choose a floor within that range.';
    }
    return null;
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
            r.floor_label, r.amenities, r.status,
            r.sort_order, r.is_archived, " . ps_rooms_lock_select($conn) . ",
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
$rooms_archived = ps_load_archived_rooms($conn);

// Attached Organization Adviser reservations are confidential review items.
// Other admins must not receive them in the Reservations table at all.
$ps_rooms_can_view_sensitive_adviser_requests = admin_is_super_admin($conn);
$ps_rooms_sensitive_reservation_sql = "(rr.submitted_as = 'adviser' AND COALESCE(rr.document_path, '') <> '')";

// ── All room reservations (admin read-only view) ──────────────────────────
require_once __DIR__ . '/room-expiry.php';
ps_room_expire_unreviewed($conn);   // undecided Waiting reservations past 12:01 AM of their day become Declined

$admin_room_reservations_result = $conn->query(
    "SELECT rr.id, rr.room_id, rr.reservation_date,
            rr.start_time, rr.end_time,
            TIME_FORMAT(rr.start_time, '%h:%i %p') AS start_fmt,
            TIME_FORMAT(rr.end_time,   '%h:%i %p') AS end_fmt,
            rr.faculty_name, rr.submitted_as, rr.submitted_by_name,
            rr.purpose, rr.attendees, rr.status, rr.reason,
            rr.request_date, rr.document_path, rr.notes,
            r.room_name,
            b.name AS building_name,
            COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label,
            c.campus_name,
            ral.rule_applied AS arb_rule,
            ral.reason       AS arb_reason
       FROM tbl_room_reservations rr
       JOIN tbl_rooms     r ON r.room_id     = rr.room_id
       JOIN tbl_buildings b ON b.building_id = r.building_id
       JOIN tbl_campuses  c ON c.campus_id   = b.campus_id
       /* A reservation may have an earlier automatic approval followed by a
          later arbitration decision.  The drawer must show its latest one. */
       LEFT JOIN tbl_room_arbitration_log ral ON ral.id = (
           SELECT latest.id
             FROM tbl_room_arbitration_log latest
            WHERE latest.reservation_id = rr.id
            ORDER BY latest.id DESC
            LIMIT 1
       )
      " . ($ps_rooms_can_view_sensitive_adviser_requests ? '' : "WHERE NOT {$ps_rooms_sensitive_reservation_sql}") . "
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
