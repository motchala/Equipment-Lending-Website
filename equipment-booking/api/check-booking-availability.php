<?php
/**
 * equipment-booking/api/check-booking-availability.php
 *
 * Read-only helper for the Borrow Request modal: tells the faculty member, before they
 * submit, whether the equipment is free for the schedule they picked.
 *
 * GET params:
 *   mode      today | future
 *   date      YYYY-MM-DD   (future only)
 *   time      HH:MM        start time
 *   duration  hours (today) or days (future)
 *   items     JSON [{"n":"Projector","q":2}, ...]
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['faculty_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/booking-schedule.php';
$conn = getDB();
BookingSchedule::ensureSchema($conn);

$stmt = $conn->prepare('SELECT role FROM tbl_users WHERE faculty_id = ? LIMIT 1');
$stmt->bind_param('s', $_SESSION['faculty_id']);
$stmt->execute();
$role = (string)($stmt->get_result()->fetch_assoc()['role'] ?? '');
$stmt->close();
$adviser = BookingSchedule::isAdviserRole($role);

$mode     = (string)($_GET['mode'] ?? '');
$date     = trim((string)($_GET['date'] ?? ''));
$time     = trim((string)($_GET['time'] ?? ''));
$duration = (int)($_GET['duration'] ?? 0);
$items    = json_decode((string)($_GET['items'] ?? '[]'), true);
if (!is_array($items)) {
    $items = [];
}
$items = array_slice($items, 0, 40);

$w = BookingSchedule::window($mode, $date, $time, $duration, $adviser);
if (!$w['ok']) {
    echo json_encode(['ok' => false, 'error' => $w['error'], 'results' => []]);
    exit();
}
$win = $w['data'];

$results = [];
foreach ($items as $it) {
    $name = trim((string)($it['n'] ?? ''));
    $qty  = max(1, (int)($it['q'] ?? 1));
    if ($name === '') {
        continue;
    }
    if ($mode === 'today') {
        $qty = 1;
    }
    $shelf = BookingSchedule::shelfQuantity($conn, $name);
    $total = BookingSchedule::totalStock($conn, $name);
    $row   = ['name' => $name, 'qty' => $qty, 'total' => $total, 'shelf' => $shelf, 'free' => 0,
              'ok' => false, 'message' => '', 'earliest' => null];

    if ($shelf === null) {
        $row['message'] = 'Not available for borrowing.';
    } elseif ($total < 1) {
        $row['message'] = 'No stock has been set for this equipment.';
    } else {
        $free = BookingSchedule::freeUnits($conn, $name, $win['start_ts'], $win['end_ts']);
        $row['free'] = $free;
        if ($mode === 'today' && $shelf < 1) {
            $row['message'] = 'Out right now — book it for a later date.';
        } elseif ($qty > $total) {
            $row['message'] = 'Only ' . $total . ' in stock.';
        } elseif ($free >= $qty) {
            $row['ok']      = true;
            $row['message'] = $mode === 'today'
                ? 'Available for that time.'
                : $free . ' of ' . $total . ' free for those dates.';
        } else {
            $row['message'] = $free <= 0
                ? 'Fully booked for that schedule.'
                : 'Only ' . $free . ' free for that schedule.';
            if ($mode === 'future') {
                $next = BookingSchedule::earliestFreeDate(
                    $conn, $name, $qty, substr($win['borrow_time'], 0, 5), $duration,
                    date('Y-m-d', strtotime($win['borrow_date'] . ' +1 day'))
                );
                if ($next) {
                    $row['earliest'] = $next;
                    $row['message'] .= ' Next free start: ' . date('D, M j', strtotime($next)) . '.';
                }
            }
        }
    }
    $results[] = $row;
}

echo json_encode([
    'ok'      => true,
    'window'  => [
        'start' => $win['start_ts'],
        'end'   => $win['end_ts'],
    ],
    'results' => $results,
]);
