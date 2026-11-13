<?php

declare(strict_types=1);

/**
 * room-week.php
 *
 * One room's week, built the same way for everyone who looks at it:
 *   - the weekly CLASSES imported through Settings -> Imports -> Rooms Assigned
 *     (they repeat on the same weekday every week), and
 *   - every APPROVED reservation of that room in the requested week.
 *
 * Both block the room, so together they are what is NOT available. Whatever is left in the
 * 7 AM - 8 PM day is what can still be reserved. The server enforces the same rule when a
 * reservation is submitted (ArbitrationEngine::processRoomReservation); this is what lets the
 * screens show it first.
 *
 * Used by:
 *   room-reservation/api/get-room-schedule.php      faculty / admin (names shown)
 *   room-reservation/api/student-room-schedule.php  students (no names: just "Class" / "Reserved")
 *
 * Each entry: start 'HH:MM', end 'HH:MM', kind 'class' | 'reservation',
 *   label  - one line of HTML-escaped text (what the schedule lists show),
 *   title  - plain text: what it is (class + section, or the purpose),
 *   who    - plain text: the instructor / the person who reserved it ('' for students).
 */

require_once __DIR__ . '/class-schedule.php';

/** Monday (Y-m-d) of the week that contains $date, or of this week when $date is empty / invalid. */
function rw_monday(string $date = ''): string
{
    $ts = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? strtotime($date) : false;
    if ($ts === false) {
        $ts = time();
    }
    return date('Y-m-d', strtotime('-' . ((int)date('N', $ts) - 1) . ' days', $ts));
}

/**
 * @param string $monday   Y-m-d of the Monday that starts the week
 * @param string $audience 'faculty' (instructor and reserver names shown) or 'student' (no names)
 * @return array<string, list<array<string, string>>>  mon..sun => entries sorted by start time
 */
function rw_week(mysqli $conn, int $room_id, string $monday, string $audience = 'faculty'): array
{
    $names  = $audience !== 'student';
    $sunday = date('Y-m-d', strtotime($monday . ' +6 days'));
    $week   = ['mon' => [], 'tue' => [], 'wed' => [], 'thu' => [], 'fri' => [], 'sat' => [], 'sun' => []];
    $by_dow = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'];

    // ── Approved reservations in that week ─────────────────────────────────
    $st = $conn->prepare(
        "SELECT reservation_date,
                TIME_FORMAT(start_time, '%H:%i') AS start,
                TIME_FORMAT(end_time,   '%H:%i') AS end,
                faculty_name, submitted_by_name, purpose, submitted_as
           FROM tbl_room_reservations
          WHERE room_id = ? AND reservation_date BETWEEN ? AND ? AND status = 'Approved'
          ORDER BY reservation_date ASC, start_time ASC"
    );
    $st->bind_param('iss', $room_id, $monday, $sunday);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $key = $by_dow[(int)date('N', strtotime((string)$row['reservation_date']))] ?? null;
        if ($key === null) {
            continue;
        }
        if (!$names) {
            $week[$key][] = ['start' => $row['start'], 'end' => $row['end'], 'kind' => 'reservation',
                'label' => 'Reserved', 'title' => 'Reserved', 'who' => ''];
            continue;
        }
        $student = $row['submitted_as'] === 'student' && !empty($row['submitted_by_name']);
        $who     = $student ? (string)$row['submitted_by_name'] . ' (via ' . (string)$row['faculty_name'] . ')' : (string)$row['faculty_name'];
        $week[$key][] = [
            'start' => $row['start'],
            'end'   => $row['end'],
            'kind'  => 'reservation',
            'label' => htmlspecialchars($who) . ' &mdash; ' . htmlspecialchars((string)$row['purpose']),
            'title' => (string)$row['purpose'],
            'who'   => $who,
        ];
    }
    $st->close();

    // ── Weekly classes: the same weekday, every week ───────────────────────
    foreach (cs_room_week_rows($conn, $room_id) as $c) {
        $key = $by_dow[(int)$c['day_of_week']] ?? null;
        if ($key === null) {
            continue;
        }
        $what = cs_class_label($c);
        $kind = trim((string)($c['component'] ?? ''));
        if ($kind !== '') {
            $what .= ' (' . $kind . ')';
        }
        $who = trim((string)($c['faculty_name'] ?? ''));
        $week[$key][] = [
            'start' => $c['start_hm'],
            'end'   => $c['end_hm'],
            'kind'  => 'class',
            'label' => $names
                ? htmlspecialchars(cs_class_label($c)) . ' &mdash; ' . ($who !== '' ? htmlspecialchars($who) : 'no instructor yet')
                : htmlspecialchars(cs_class_label($c)),
            'title' => $what,
            'who'   => $names ? ($who !== '' ? $who : 'No instructor yet') : '',
        ];
    }

    foreach ($week as &$list) {
        usort($list, static fn(array $a, array $b): int => strcmp($a['start'], $b['start']));
    }
    unset($list);
    return $week;
}
