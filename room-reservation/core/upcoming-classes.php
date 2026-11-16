<?php

declare(strict_types=1);

/**
 * room-reservation/core/upcoming-classes.php
 *
 * "What does this faculty member teach next?" for the Dashboard's Upcoming Classes card.
 *
 * A class meeting (tbl_class_schedule, read through cs_faculty_week_rows) repeats every week. This turns the
 * weekly timetable into real, dated occurrences starting from the current moment, so the answer can be:
 *   - only classes that have NOT finished yet (a class that is over is never returned), and
 *   - in the order they will happen, today first and then the next days, crossing into next week.
 *
 * A class that is in session right now is returned (live = true) until its end time, because it has not
 * passed yet; the page drops it the minute it ends.
 *
 * Times are Asia/Manila. Nothing is written anywhere.
 */

require_once __DIR__ . '/class-schedule.php';

/**
 * @param int      $limit       most occurrences to return (the page shows 3 and keeps a few spare so it can
 *                              move on by itself as classes finish without asking the server again)
 * @param int      $days_ahead  how many days from today (today included) to look
 * @param int|null $now_ts      "now" as a Unix timestamp (tests); defaults to the real clock
 * @return array<int, array<string, mixed>>
 */
function cs_upcoming_classes(mysqli $conn, string $account_faculty_id, int $limit = 10, int $days_ahead = 14, ?int $now_ts = null): array
{
    $tz  = new DateTimeZone('Asia/Manila');
    $now = new DateTimeImmutable('@' . ($now_ts ?? time()));
    $now = $now->setTimezone($tz);
    $today = $now->setTime(0, 0, 0);

    $out = [];
    foreach (cs_faculty_week_rows($conn, $account_faculty_id) as $r) {
        $dow     = (int)$r['day_of_week'];                       // 1 = Monday .. 7 = Sunday
        $minutes = (int)round(((int)$r['span_sec']) / 60);
        if ($dow < 1 || $dow > 7 || $minutes <= 0) {
            continue;
        }
        $sp = explode(':', (string)$r['start_hm']);
        $ep = explode(':', (string)$r['end_hm']);

        for ($i = 0; $i < $days_ahead; $i++) {
            $day = $today->modify('+' . $i . ' days');
            if ((int)$day->format('N') !== $dow) {
                continue;
            }
            $start = $day->setTime((int)$sp[0], (int)($sp[1] ?? 0), 0);
            $end   = $day->setTime((int)$ep[0], (int)($ep[1] ?? 0), 0);
            if ($end->getTimestamp() <= $now->getTimestamp()) {
                continue;                                        // already over: never shown
            }
            $out[] = [
                'id'        => (int)$r['id'],
                'code'      => trim((string)$r['subject_code']),
                'subject'   => trim((string)$r['subject_name']),
                'section'   => trim((string)$r['section']),
                'component' => (string)$r['component'],
                'group'     => (string)($r['group_label'] ?? ''),
                'room'      => (string)(($r['room_name'] ?? '') !== '' ? $r['room_name'] : $r['room_code']),
                'building'  => (string)($r['building_name'] ?? ''),
                'floor'     => (string)($r['floor_label'] ?? ''),
                'date'      => $day->format('Y-m-d'),
                'dow'       => $dow,
                'start'     => (string)$r['start_hm'],
                'end'       => (string)$r['end_hm'],
                'minutes'   => $minutes,
                'start_ts'  => $start->getTimestamp(),
                'end_ts'    => $end->getTimestamp(),
                'live'      => $start->getTimestamp() <= $now->getTimestamp(),
            ];
        }
    }

    usort($out, static function (array $a, array $b): int {
        return [$a['start_ts'], $a['end_ts'], $a['id']] <=> [$b['start_ts'], $b['end_ts'], $b['id']];
    });

    return array_slice($out, 0, max(1, $limit));
}
