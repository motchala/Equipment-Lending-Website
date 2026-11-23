<?php

declare(strict_types=1);

/**
 * shared/core/operating-hours.php
 *
 * Read-only helper: "when is the office open for equipment, and when for rooms?"
 *
 * Equipment and rooms keep SEPARATE schedules in Settings → Arbitration (Super Admin):
 *   Equipment  equip_day_start / equip_day_end   (earliest pickup .. latest return)
 *              open days: Monday to Saturday - Sundays are never bookable (BookingSchedule)
 *   Rooms      room_day_start  / room_day_end    (earliest start .. latest end)
 *              room_open_days                    (any ISO weekdays, 1 = Monday .. 7 = Sunday)
 *
 * Screens that print those times in plain words (student home, Help text) ask this helper instead of
 * typing "7 AM to 5 PM" by hand, so the words always match what the Super Admin saved.
 * It only READS through ArbitrationConfig; it never writes. If the database cannot be reached it
 * falls back to the built-in defaults.
 *
 * USAGE:
 *   require_once PS_ROOT . '/shared/core/operating-hours.php';
 *   $oh = ps_operating_hours($conn);       // $conn is optional; the helper opens its own if omitted
 *   $oh['equip']['hoursTo']  -> "7:00 AM to 8:00 PM"      $oh['room']['hoursTo']  -> "7:00 AM to 8:00 PM"
 *   $oh['equip']['days']     -> "Mon – Sat"               $oh['room']['daysLong'] -> "Monday to Saturday"
 */

require_once __DIR__ . '/arbitration-config.php';

if (!function_exists('ps_clock_label')) {
    /** "07:00" -> "7:00 AM" */
    function ps_clock_label(string $hhmm): string
    {
        $ts = strtotime('2000-01-01 ' . substr($hhmm, 0, 5) . ':00');
        return $ts ? date('g:i A', $ts) : $hhmm;
    }
}

if (!function_exists('ps_days_label')) {
    /**
     * ISO weekdays (1 = Monday .. 7 = Sunday) in words.
     * Short: "Mon – Sat", "Mon – Fri", "Mon – Wed, Fri".  Long: "Monday to Saturday", "Monday to Wednesday and Friday".
     *
     * @param list<int> $isoDays
     */
    function ps_days_label(array $isoDays, bool $long = false): string
    {
        $short = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
        $full  = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        $names = $long ? $full : $short;

        $days = array_values(array_unique(array_map('intval', $isoDays)));
        sort($days);
        $runs = [];
        foreach ($days as $d) {
            if ($runs && $d === $runs[count($runs) - 1][1] + 1) {
                $runs[count($runs) - 1][1] = $d;
            } else {
                $runs[] = [$d, $d];
            }
        }
        $parts = [];
        foreach ($runs as [$a, $b]) {
            if ($a === $b) {
                $parts[] = $names[$a];
            } elseif ($b === $a + 1) {
                if ($long) {
                    array_push($parts, $names[$a], $names[$b]);   // joined by the final "and" below
                } else {
                    $parts[] = $names[$a] . ', ' . $names[$b];
                }
            } else {
                $parts[] = $names[$a] . ($long ? ' to ' : ' – ') . $names[$b];
            }
        }
        if (!$parts) {
            return '';
        }
        if ($long) {
            $last = array_pop($parts);
            return $parts ? implode(', ', $parts) . ' and ' . $last : (string)$last;
        }
        return implode(', ', $parts);
    }
}

if (!function_exists('ps_ahead_label')) {
    /** 14 -> "2 weeks", 21 -> "3 weeks", 10 -> "10 days" */
    function ps_ahead_label(int $days): string
    {
        if ($days >= 7 && $days % 7 === 0) {
            $w = intdiv($days, 7);
            return $w . ($w === 1 ? ' week' : ' weeks');
        }
        return $days . ($days === 1 ? ' day' : ' days');
    }
}

if (!function_exists('ps_operating_hours')) {
    /**
     * @param  mysqli|null $conn Existing connection (admin / faculty pages). Omit on pages that have none.
     * @return array{equip: array<string,mixed>, room: array<string,mixed>, same: bool}
     */
    function ps_operating_hours(?mysqli $conn = null): array
    {
        try {
            if ($conn === null) {
                require_once __DIR__ . '/../../config/db.php';
                $conn = getDB();
            }
            $c = ArbitrationConfig::all($conn);
        } catch (Throwable $e) {
            error_log('[operating-hours] ' . $e->getMessage());
            $c = ArbitrationConfig::defaults();
        }

        $equipDays = [1, 2, 3, 4, 5, 6];   // Sundays are never bookable for equipment (see BookingSchedule)
        $roomDays  = array_values(array_map('intval', (array)$c['room_open_days']));

        $equip = [
            'start'      => ps_clock_label((string)$c['equip_day_start']),
            'end'        => ps_clock_label((string)$c['equip_day_end']),
            'days'       => ps_days_label($equipDays),
            'daysLong'   => ps_days_label($equipDays, true),
            'aheadDays'  => (int)$c['equip_max_advance_days'],
            'aheadLabel' => ps_ahead_label((int)$c['equip_max_advance_days']),
            'durations'  => array_values(array_map('intval', (array)$c['equip_durations'])),
            'maxDays'    => (int)$c['max_borrow_days'],
        ];
        $room = [
            'start'      => ps_clock_label((string)$c['room_day_start']),
            'end'        => ps_clock_label((string)$c['room_day_end']),
            'days'       => ps_days_label($roomDays),
            'daysLong'   => ps_days_label($roomDays, true),
            'aheadDays'  => (int)$c['room_max_advance_weeks'] * 7,
            'aheadLabel' => ps_ahead_label((int)$c['room_max_advance_weeks'] * 7),
            'maxActive'  => (int)$c['room_max_active'],
        ];
        $equip['hours']   = $equip['start'] . ' – ' . $equip['end'];    // "7:00 AM – 8:00 PM"
        $equip['hoursTo'] = $equip['start'] . ' to ' . $equip['end'];   // "7:00 AM to 8:00 PM"
        $room['hours']    = $room['start'] . ' – ' . $room['end'];
        $room['hoursTo']  = $room['start'] . ' to ' . $room['end'];

        return [
            'equip' => $equip,
            'room'  => $room,
            // true when both services are open on the same days and hours
            'same'  => $equip['days'] === $room['days'] && $equip['hours'] === $room['hours'],
        ];
    }
}
