<?php
/**
 * room-reservation/api/get-faculty-schedule.php
 *
 * The logged-in faculty member's own and student-authorized schedule for ONE week, Monday to Saturday
 * (Sunday is closed), for the "My class schedule" calendar on the Facilities tab.
 *
 * Source: APPROVED room reservations associated with the faculty account (tbl_room_reservations),
 * plus the faculty member's weekly CLASS meetings (tbl_class_schedule, from the imported timetable),
 * which repeat every week and carry "kind": "class".
 * Student-submitted reservations are included and labeled with the student.
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
 *   total: 4,
 *   semester: { name: "1st Semester", ay: "A.Y. 2026-2027", start, end, classes: 3, sessions: 9 },
 *   classes: [ { purpose, room, floor, building, dow (1=Mon..7), start, end, minutes,
 *                sessions, upcoming, next_date|null, last_date, dates: [Y-m-d, ...], student }, ... ]
 * }
 *
 * "classes": this system has no subject / semester tables, so the semester list is this faculty
 * member's APPROVED bookings in the current semester, grouped by purpose + room + weekday + time.
 * The semester window is chosen by month (see $sem_* below); edit it if the campus calendar differs.
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
                r.purpose, r.submitted_as, r.submitted_by_name, r.submitted_by_id,
                rm.room_name,
                rm.floor_label,
                b.name AS building_name
           FROM tbl_room_reservations r
           JOIN tbl_rooms rm      ON rm.room_id = r.room_id
           LEFT JOIN tbl_buildings b ON b.building_id = rm.building_id
          WHERE r.faculty_id = ?
            AND r.status = 'Approved'
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
            'student_name' => (string)($row['submitted_as'] ?? '') === 'student' ? (string)($row['submitted_by_name'] ?? '') : '',
            'student_id' => (string)($row['submitted_as'] ?? '') === 'student' ? (string)($row['submitted_by_id'] ?? '') : '',
            'room'     => (string)$row['room_name'],
            'floor'    => (string)($row['floor_label'] ?? ''),
            'building' => (string)($row['building_name'] ?? ''),
        ];
        $total++;
    }
    $stmt->close();

    // Weekly class meetings (imported timetable): one item per meeting on its weekday of this week
    require_once __DIR__ . '/../core/class-schedule.php';
    $day_dates = array_keys($days);                 // Monday .. Saturday
    foreach (cs_faculty_week_rows($conn, $faculty_id) as $c) {
        $date = $day_dates[((int)$c['day_of_week']) - 1] ?? null;
        $minutes = (int)round(((int)$c['span_sec']) / 60);
        if ($date === null || !isset($days[$date]) || $minutes <= 0) {
            continue;
        }
        $code = trim((string)$c['subject_code']);
        $sect = trim((string)$c['section']);
        $days[$date]['items'][] = [
            'id'        => 'class-' . (int)$c['id'],
            'kind'      => 'class',
            'start'     => $c['start_hm'],
            'end'       => $c['end_hm'],
            'minutes'   => $minutes,
            'purpose'   => trim($code . ' · ' . $sect, ' ·') ?: 'Class',
            'subject'   => (string)$c['subject_name'],
            'section'   => $sect,
            'component' => (string)$c['component'],
            'group'     => (string)($c['group_label'] ?? ''),
            'student_name' => '',
            'student_id'   => '',
            'room'      => (string)($c['room_name'] ?? $c['room_code']),
            'floor'     => (string)($c['floor_label'] ?? ''),
            'building'  => (string)($c['building_name'] ?? ''),
        ];
        $total++;
    }
    foreach ($days as &$dayRef) {
        usort($dayRef['items'], static function (array $a, array $b): int {
            return strcmp($a['start'], $b['start']) ?: strcmp($a['end'], $b['end']);
        });
    }
    unset($dayRef);
} catch (Throwable $e) {
    error_log('[get-faculty-schedule] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not load your schedule.']);
    exit();
}

$now   = new DateTime('now');
$today = $now->format('Y-m-d');

//  Semester class list (left side of the schedule) 
// Aug-Dec = 1st Semester, Jan-May = 2nd Semester, Jun-Jul = Summer.
$cur_m = (int)$now->format('n');
$cur_y = (int)$now->format('Y');
if ($cur_m >= 8) {
    $sem_name = '1st Semester'; $sem_from = $cur_y . '-08-01'; $sem_to = $cur_y . '-12-31';
    $sem_ay = $cur_y . "\u{2013}" . ($cur_y + 1);
} elseif ($cur_m >= 6) {
    $sem_name = 'Summer'; $sem_from = $cur_y . '-06-01'; $sem_to = $cur_y . '-07-31';
    $sem_ay = ($cur_y - 1) . "\u{2013}" . $cur_y;
} else {
    $sem_name = '2nd Semester'; $sem_from = $cur_y . '-01-01'; $sem_to = $cur_y . '-05-31';
    $sem_ay = ($cur_y - 1) . "\u{2013}" . $cur_y;
}

$classes  = [];
$sessions = 0;
try {
    $now_hm = $now->format('H:i');
    $stmt = $conn->prepare(
        "SELECT r.reservation_date,
                TIME_FORMAT(r.start_time, '%H:%i') AS start_hm,
                TIME_FORMAT(r.end_time,   '%H:%i') AS end_hm,
                TIME_TO_SEC(TIMEDIFF(r.end_time, r.start_time)) AS span_sec,
                r.purpose, r.submitted_as,
                rm.room_id, rm.room_name, rm.floor_label,
                b.name AS building_name
           FROM tbl_room_reservations r
           JOIN tbl_rooms rm      ON rm.room_id = r.room_id
           LEFT JOIN tbl_buildings b ON b.building_id = rm.building_id
          WHERE r.faculty_id = ?
            AND r.status = 'Approved'
            AND r.reservation_date BETWEEN ? AND ?
          ORDER BY r.reservation_date ASC, r.start_time ASC"
    );
    $stmt->bind_param('sss', $faculty_id, $sem_from, $sem_to);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $minutes = (int)round(((int)$row['span_sec']) / 60);
        if ($minutes <= 0) {
            continue;
        }
        $date = $row['reservation_date'];
        $dow  = (int)(new DateTime($date))->format('N');
        $key  = mb_strtolower($row['purpose']) . '|' . $row['room_id'] . '|' . $dow . '|' . $row['start_hm'] . '|' . $row['end_hm'];
        if (!isset($classes[$key])) {
            $classes[$key] = [
                'purpose'   => (string)$row['purpose'],
                'room'      => (string)$row['room_name'],
                'floor'     => (string)($row['floor_label'] ?? ''),
                'building'  => (string)($row['building_name'] ?? ''),
                'dow'       => $dow,
                'start'     => $row['start_hm'],
                'end'       => $row['end_hm'],
                'minutes'   => $minutes,
                'sessions'  => 0,
                'upcoming'  => 0,
                'next_date' => null,
                'last_date' => $date,
                'dates'     => [],
                'student'   => false,
            ];
        }
        $c = &$classes[$key];
        $c['sessions']++;
        $c['dates'][]   = $date;
        $c['last_date'] = $date;
        if ((string)($row['submitted_as'] ?? '') === 'student') {
            $c['student'] = true;
        }
        if ($date > $today || ($date === $today && $row['end_hm'] > $now_hm)) {
            $c['upcoming']++;
            if ($c['next_date'] === null) {
                $c['next_date'] = $date;
            }
        }
        unset($c);
        $sessions++;
    }
    $stmt->close();

    // Weekly class meetings (imported timetable): each one repeats every week of the semester
    try {
        require_once __DIR__ . '/../core/class-schedule.php';
        $sem_end_dt = new DateTime($sem_to);
        foreach (cs_faculty_week_rows($conn, $faculty_id) as $cm) {
            $cm_minutes = (int)round(((int)$cm['span_sec']) / 60);
            if ($cm_minutes <= 0) {
                continue;
            }
            $cm_dow = (int)$cm['day_of_week'];
            $cm_d = new DateTime($sem_from);
            $cm_d->modify('+' . (($cm_dow - (int)$cm_d->format('N') + 7) % 7) . ' days');
            $cm_dates = [];
            while ($cm_d <= $sem_end_dt) {
                $cm_dates[] = $cm_d->format('Y-m-d');
                $cm_d->modify('+7 days');
            }
            if (!$cm_dates) {
                continue;
            }
            $cm_upcoming = 0;
            $cm_next = null;
            foreach ($cm_dates as $cm_date) {
                if ($cm_date > $today || ($cm_date === $today && $cm['end_hm'] > $now_hm)) {
                    $cm_upcoming++;
                    if ($cm_next === null) {
                        $cm_next = $cm_date;
                    }
                }
            }
            $classes[] = [
                'kind'      => 'class',
                'purpose'   => trim(trim((string)$cm['subject_code']) . ' · ' . trim((string)$cm['section']), ' ·') ?: 'Class',
                'subject'   => (string)$cm['subject_name'],
                'component' => (string)$cm['component'],
                'room'      => (string)($cm['room_name'] ?? $cm['room_code']),
                'floor'     => (string)($cm['floor_label'] ?? ''),
                'building'  => (string)($cm['building_name'] ?? ''),
                'dow'       => $cm_dow,
                'start'     => $cm['start_hm'],
                'end'       => $cm['end_hm'],
                'minutes'   => $cm_minutes,
                'sessions'  => count($cm_dates),
                'upcoming'  => $cm_upcoming,
                'next_date' => $cm_next,
                'last_date' => end($cm_dates),
                'dates'     => $cm_dates,
                'student'   => false,
            ];
            $sessions += count($cm_dates);
        }
    } catch (Throwable $e) {
        error_log('[get-faculty-schedule] class meetings in semester list: ' . $e->getMessage());
    }

    $classes = array_values($classes);
    usort($classes, static function ($a, $b) {
        return [$a['dow'], $a['start'], $a['purpose']] <=> [$b['dow'], $b['start'], $b['purpose']];
    });
} catch (Throwable $e) {
    error_log('[get-faculty-schedule] classes: ' . $e->getMessage());
    $classes  = [];
    $sessions = 0;
}

echo json_encode([
    'week_start'      => $week_start,
    'week_end'        => $week_end,
    'today'           => $today,
    'now_minutes'     => ((int)$now->format('G')) * 60 + (int)$now->format('i'),
    'default_week'    => $default_monday->format('Y-m-d'),   // what "This week" jumps back to
    'is_current_week' => ($week_start === $default_monday->format('Y-m-d')),
    'days'            => array_values($days),
    'total'           => $total,
    'semester'        => [
        'name'     => $sem_name,
        'ay'       => 'A.Y. ' . $sem_ay,
        'start'    => $sem_from,
        'end'      => $sem_to,
        'classes'  => count($classes),
        'sessions' => $sessions,
    ],
    'classes'         => $classes,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
