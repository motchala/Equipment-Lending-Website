<?php
/**
 * room-reservation/api/get-room-schedule.php
 * Returns approved reservations for a given room, grouped by day of week,
 * in the format expected by fcty-facilities.js schedule rendering.
 *
 * GET params:
 *   room_id     — int, required
 *   week_start  — Y-m-d of Monday for the requested week (optional,
 *                 defaults to current week's Monday)
 *
 * Response:
 * {
 *   room_id: int,
 *   room_name: string,
 *   seating_capacity: int|null,
 *   status: string,
 *   week: {
 *     mon: [ { start: "HH:MM", end: "HH:MM", label: "Faculty Name — Purpose" }, ... ],
 *     tue: [...], wed: [...], thu: [...], fri: [...], sat: [...], sun: [...]
 *   }
 * }
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/security-headers.php';
require_once __DIR__ . '/../../config/session.php';

// Must be authenticated (faculty or admin)
if (!isset($_SESSION['faculty_id']) && (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/db.php';
$conn = getDB();

$room_id = intval($_GET['room_id'] ?? 0);
if ($room_id <= 0) {
    echo json_encode(['error' => 'room_id is required.']);
    exit();
}

// Determine week range (Mon–Sun)
$week_start_raw = trim($_GET['week_start'] ?? '');
if ($week_start_raw && preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start_raw)) {
    $week_monday = $week_start_raw;
} else {
    // Default to this week's Monday
    $day_of_week = (int)date('N'); // 1=Mon … 7=Sun
    $week_monday = date('Y-m-d', strtotime('-' . ($day_of_week - 1) . ' days'));
}
$week_sunday = date('Y-m-d', strtotime($week_monday . ' +6 days'));

// ── Fetch room info ────────────────────────────────────────────────────────
$room_stmt = $conn->prepare(
    "SELECT room_name, seating_capacity, status
       FROM tbl_rooms WHERE room_id = ? AND is_archived = 0 LIMIT 1"
);
$room_stmt->bind_param('i', $room_id);
$room_stmt->execute();
$room_info = $room_stmt->get_result()->fetch_assoc();
$room_stmt->close();

if (!$room_info) {
    echo json_encode(['error' => 'Room not found.']);
    exit();
}

// ── Fetch approved reservations for this room in the requested week ────────
$res_stmt = $conn->prepare(
    "SELECT reservation_date,
            TIME_FORMAT(start_time, '%H:%i') AS start,
            TIME_FORMAT(end_time,   '%H:%i') AS end,
            faculty_name, submitted_by_name, purpose, submitted_as
       FROM tbl_room_reservations
      WHERE room_id = ?
        AND reservation_date BETWEEN ? AND ?
        AND status = 'Approved'
      ORDER BY reservation_date ASC, start_time ASC"
);
$res_stmt->bind_param('iss', $room_id, $week_monday, $week_sunday);
$res_stmt->execute();
$res_result = $res_stmt->get_result();

// Map PHP date('D') → JS day key
$day_map = [
    'Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed',
    'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat', 'Sun' => 'sun',
];

$week = ['mon'=>[],'tue'=>[],'wed'=>[],'thu'=>[],'fri'=>[],'sat'=>[],'sun'=>[]];

while ($row = $res_result->fetch_assoc()) {
    $day_abbr = date('D', strtotime($row['reservation_date']));
    $day_key  = $day_map[$day_abbr] ?? null;
    if (!$day_key) continue;

    // Label: show faculty name and purpose (student reservations show student via faculty)
    if ($row['submitted_as'] === 'student' && !empty($row['submitted_by_name'])) {
        $label = htmlspecialchars($row['submitted_by_name'])
               . ' (via ' . htmlspecialchars($row['faculty_name']) . ')'
               . ' &mdash; ' . htmlspecialchars($row['purpose']);
    } else {
        $label = htmlspecialchars($row['faculty_name'])
               . ' &mdash; ' . htmlspecialchars($row['purpose']);
    }

    $week[$day_key][] = [
        'start' => $row['start'],
        'end'   => $row['end'],
        'label' => $label,
    ];
}
$res_stmt->close();

echo json_encode([
    'room_id'          => $room_id,
    'room_name'        => $room_info['room_name'],
    'seating_capacity' => $room_info['seating_capacity'] !== null ? (int)$room_info['seating_capacity'] : null,
    'status'           => $room_info['status'],
    'week_monday'      => $week_monday,
    'week_sunday'      => $week_sunday,
    'week'             => $week,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
