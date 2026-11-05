<?php
/**
 * room-reservation/api/get-faculty-schedule.php
 *
 * The logged-in faculty member's own schedule for ONE week, Monday to Saturday
 * (Sunday is closed), for the "My class schedule" calendar on the Facilities tab.
 *
 * Source: the faculty member's own APPROVED room reservations (tbl_room_reservations).
 * Reservations made by students through the faculty member's code are not theirs and are left out.
 * Declined / cancelled reservations never show.
 *
 * GET params:
 *   week_start  Y-m-d, optional. Any date inside the wanted week works (it is moved back to that
 *               week's Monday). Defaults to the current week.
 *
 * Response:
 * {
 *   week_start: "2026-11-02",  week_end: "2026-11-07",
 *   today: "2026-11-05",       now_minutes: 845,          // server clock (Asia/Manila)
 *   default_week: "2026-11-02", is_current_week: true,
 *   days: [ { key: "mon", date: "2026-11-02", items: [
 *            { id, start: "09:30", end: "12:30", minutes: 180,
 *              purpose, room, floor, building }, ... ] }, ... 6 days ... ],
 *   total: 4
 * }
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Faculty only: this is the signed-in faculty member's own schedule
if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
$faculty_id = (string)$_SESSION['faculty_id'];

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/db.php';

// ── Which week? (normalised to that week's Monday) ─────────────────────────
// The "current" week is today's Mon–Sat. Sunday is closed, so on a Sunday the calendar opens on
// the coming week instead of showing a week that is already over.
$default_anchor = new DateTime('today');
if ((int)$default_anchor->format('N') === 7) {
    $default_anchor->modify('+1 day');
}
$default_monday = clone $default_anchor;
$default_monday->modify('-' . ((int)$default_anchor->format('N') - 1) . ' days');

$requested = trim((string)($_GET['week_start'] ?? ''));
$anchor    = DateTime::createFromFormat('!Y-m-d', $requested);
if (!$anchor || $anchor->format('Y-m-d') !== $requested) {
    $anchor = clone $default_anchor;
}
$year = (int)$anchor->format('Y');
if ($year < 2000 || $year > 2100) {
    $anchor = clone $default_anchor;
}
$monday = clone $anchor;
$monday->modify('-' . ((int)$anchor->format('N') - 1) . ' days');
$saturday = clone $monday;
$saturday->modify('+5 days');

$week_start = $monday->format('Y-m-d');
$week_end   = $saturday->format('Y-m-d');

$day_keys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
$days = [];
for ($i = 0; $i < 6; $i++) {
    $d = clone $monday;
    $d->modify('+' . $i . ' days');
    $days[$d->format('Y-m-d')] = ['key' => $day_keys[$i], 'date' => $d->format('Y-m-d'), 'items' => []];
}

try {
    $conn = getDB();

    $stmt = $conn->prepare(
        "SELECT r.id,
                r.reservation_date,
                TIME_FORMAT(r.start_time, '%H:%i') AS start_hm,
                TIME_FORMAT(r.end_time,   '%H:%i') AS end_hm,
                TIME_TO_SEC(TIMEDIFF(r.end_time, r.start_time)) AS span_sec,
                r.purpose,
                rm.room_name,
                rm.floor_label,
                b.name AS building_name
           FROM tbl_room_reservations r
           JOIN tbl_rooms rm      ON rm.room_id = r.room_id
           LEFT JOIN tbl_buildings b ON b.building_id = rm.building_id
          WHERE r.faculty_id = ?
            AND r.status = 'Approved'
            AND r.submitted_as <> 'student'
            AND r.reservation_date BETWEEN ? AND ?
          ORDER BY r.reservation_date ASC, r.start_time ASC"
    );
    $stmt->bind_param('sss', $faculty_id, $week_start, $week_end);
    $stmt->execute();
    $res = $stmt->get_result();

    $total = 0;
    while ($row = $res->fetch_assoc()) {
        $date = $row['reservation_date'];
        if (!isset($days[$date])) {
            continue;
        }
        $minutes = (int)round(((int)$row['span_sec']) / 60);
        if ($minutes <= 0) {
            continue;
        }
        $days[$date]['items'][] = [
            'id'       => (int)$row['id'],
            'start'    => $row['start_hm'],
            'end'      => $row['end_hm'],
            'minutes'  => $minutes,
            'purpose'  => (string)$row['purpose'],
            'room'     => (string)$row['room_name'],
            'floor'    => (string)($row['floor_label'] ?? ''),
            'building' => (string)($row['building_name'] ?? ''),
        ];
        $total++;
    }
    $stmt->close();
} catch (Throwable $e) {
    error_log('[get-faculty-schedule] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not load your schedule.']);
    exit();
}

$now   = new DateTime('now');
$today = $now->format('Y-m-d');

echo json_encode([
    'week_start'      => $week_start,
    'week_end'        => $week_end,
    'today'           => $today,
    'now_minutes'     => ((int)$now->format('G')) * 60 + (int)$now->format('i'),
    'default_week'    => $default_monday->format('Y-m-d'),   // what "This week" jumps back to
    'is_current_week' => ($week_start === $default_monday->format('Y-m-d')),
    'days'            => array_values($days),
    'total'           => $total,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
