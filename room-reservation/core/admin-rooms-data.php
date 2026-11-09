<?php
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

// ── All room reservations (admin read-only view) ──────────────────────────
require_once __DIR__ . '/room-expiry.php';
$rooms_expired_count = ps_room_expire_unreviewed($conn);   // undecided Waiting reservations past 12:01 AM of their day become Declined

$admin_room_reservations_result = $conn->query(
    "SELECT rr.id, rr.room_id, rr.reservation_date,
            rr.start_time, rr.end_time,
            TIME_FORMAT(rr.start_time, '%h:%i %p') AS start_fmt,
            TIME_FORMAT(rr.end_time,   '%h:%i %p') AS end_fmt,
            rr.faculty_name, rr.submitted_as, rr.submitted_by_name, rr.submitted_by_id,
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
      ORDER BY rr.reservation_date DESC, rr.start_time ASC"
);
$admin_room_reservations = [];
if ($admin_room_reservations_result) {
    while ($row = $admin_room_reservations_result->fetch_assoc()) {
        $admin_room_reservations[] = $row;
    }
}

// ── Waitlist counts for Approved reservations (Suggestion 9) ─────────────
// Build a map: "room_id|date|start|end" => count so the view can render a
// data-waitlist-count attribute on each cancellable row without N extra queries.
$admin_room_waitlist_counts = [];
$_wl_tbl = $conn->query(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_room_waitlist' LIMIT 1"
);
if ($_wl_tbl && $_wl_tbl->num_rows > 0) {
    $wl_res = $conn->query(
        "SELECT room_id, reservation_date, start_time, end_time, COUNT(*) AS cnt
           FROM tbl_room_waitlist
          GROUP BY room_id, reservation_date, start_time, end_time"
    );
    if ($wl_res) {
        while ($wr = $wl_res->fetch_assoc()) {
            $wl_key = $wr['room_id'] . '|' . $wr['reservation_date'] . '|' . $wr['start_time'] . '|' . $wr['end_time'];
            $admin_room_waitlist_counts[$wl_key] = (int)$wr['cnt'];
        }
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
