<?php

declare(strict_types=1);

/**
 * room-reservation/api/student-room-schedule.php
 *
 * ONE DAY of a room's schedule, for the thin availability bar on the student room-reservation form
 * (student-dashboard.php). Students have no login, so this is public and read-only, and it never
 * returns a name: every entry is just a time range, whether it is a class or a reservation, and a
 * plain title ("Reserved", or the class code + section). The faculty version with names is
 * get-room-schedule.php.
 *
 * GET params:
 *   room_id - int, required (a room that students can book: not archived, status 'Available')
 *   date    - Y-m-d, required (today up to 120 days ahead)
 *
 * Response:
 *   { room_id: int, date: "Y-m-d",
 *     blocks: [ { start: "HH:MM", end: "HH:MM", kind: "class"|"reservation", title: string }, ... ] }
 *   blocks = the weekly classes + the APPROVED reservations that day (both block the room),
 *   sorted by start time. Whatever is left between 7 AM and 8 PM is open.
 */

require_once __DIR__ . '/../../config/security-headers.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../core/room-week.php';

const SRS_JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES;

$room_id = (int)($_GET['room_id'] ?? 0);
$date    = trim((string)($_GET['date'] ?? ''));
$ts      = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? strtotime($date) : false;

if ($room_id <= 0 || $ts === false || date('Y-m-d', $ts) !== $date) {
    http_response_code(400);
    echo json_encode(['error' => 'A room and a date (YYYY-MM-DD) are required.']);
    exit();
}
if ($date < date('Y-m-d') || $date > date('Y-m-d', strtotime('+120 days'))) {
    http_response_code(422);
    echo json_encode(['error' => 'That date is out of range.']);
    exit();
}

try {
    $conn = getDB();
    $conn->set_charset('utf8mb4');

    // Only rooms a student could actually book (same filter as the room lists students see)
    $st = $conn->prepare("SELECT 1 FROM tbl_rooms WHERE room_id = ? AND is_archived = 0 AND status = 'Available' LIMIT 1");
    $st->bind_param('i', $room_id);
    $st->execute();
    $found = $st->get_result()->fetch_row() !== null;
    $st->close();
    if (!$found) {
        $conn->close();
        http_response_code(404);
        echo json_encode(['error' => 'Room not found.']);
        exit();
    }

    $week = rw_week($conn, $room_id, rw_monday($date), 'student');   // 'student' = no names
    $conn->close();

    $day_keys = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    $blocks   = [];
    foreach ($week[$day_keys[(int)date('w', $ts)]] ?? [] as $b) {
        $blocks[] = [
            'start' => (string)$b['start'],
            'end'   => (string)$b['end'],
            'kind'  => $b['kind'] === 'class' ? 'class' : 'reservation',
            'title' => (string)($b['title'] ?? 'Reserved'),
        ];
    }

    echo json_encode(['room_id' => $room_id, 'date' => $date, 'blocks' => $blocks], SRS_JSON_FLAGS);
} catch (Throwable $e) {
    error_log('[student-room-schedule] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not load the room schedule. Please try again.']);
}
