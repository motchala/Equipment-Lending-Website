<?php
// ════════════════════════════════════════════════════════════════
// ROOM LOCK — schema helper
// tbl_rooms.is_locked (TINYINT(1), default 0). Added automatically
// the first time this file runs (same self-healing approach as
// shared/core/activity-logger.php), and also shipped as
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

