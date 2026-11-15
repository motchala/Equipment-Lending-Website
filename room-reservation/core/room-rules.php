<?php

declare(strict_types=1);

/**
 * room-reservation/core/room-rules.php
 *
 * The room reservation rules the Super Admin sets in Settings → Arbitration → Reservation rules.
 * Every room path (faculty form, student code form, chat assistant, availability check,
 * cancellation, the 12:01 AM auto-decline) asks this class instead of using its own number:
 *
 *   maxActive()           room_max_active          active reservations one faculty account may hold
 *   maxAdvanceDays()      room_max_advance_weeks   how far ahead a room can be reserved (weeks × 7)
 *   cancelCutoffSeconds() room_cancel_cutoff_min   faculty cannot cancel an approved booking this close to its start
 *   sameDayAuto()         room_same_day_auto       true  = a same-day reservation is decided at once
 *                                                  false = it waits for an admin like a later-date one
 *   letterRequired()      room_letter_required     a faculty member's own later-date reservation needs a letter
 *   expiryMode()          room_expiry_mode         what happens to a request nobody decides:
 *                                                  'day' (12:01 AM on the date), 'start' (at the start time), 'never'
 *   dayStart()/dayEnd()   room_day_start/_end      earliest start and latest end of a reservation
 *   openDays()            room_open_days           the days rooms can be reserved (ISO: 1 = Monday … 7 = Sunday)
 *   maxHours()            room_max_hours           the longest one reservation can be
 *
 * Values live in tbl_arbitration_config (see shared/core/arbitration-config.php). Before anything
 * is saved they equal what the system always did: 2 reservations, 3 weeks, 1 hour, same-day
 * auto-confirm, no letter required, undecided requests declined at 12:01 AM on the date.
 */

require_once __DIR__ . '/../../shared/core/arbitration-config.php';

final class RoomRules
{
    /** @var array<string,mixed>|null */
    private static ?array $rules = null;

    /** Reads the Arbitration settings once per request. Safe to call again. */
    public static function load(mysqli $conn): void
    {
        if (self::$rules !== null) {
            return;
        }
        self::$rules = self::build(ArbitrationConfig::all($conn));
    }

    /** @param array<string,mixed> $c typed ArbitrationConfig values */
    private static function build(array $c): array
    {
        return [
            'maxActive'       => (int)$c['room_max_active'],
            'maxAdvanceWeeks' => (int)$c['room_max_advance_weeks'],
            'cancelCutoffMin' => (int)$c['room_cancel_cutoff_min'],
            'sameDayAuto'     => (bool)$c['room_same_day_auto'],
            'letterRequired'  => (bool)$c['room_letter_required'],
            'expiryMode'      => (string)$c['room_expiry_mode'],
            'dayStart'        => (string)$c['room_day_start'],
            'dayEnd'          => (string)$c['room_day_end'],
            'openDays'        => array_values(array_map('intval', (array)$c['room_open_days'])),
            'maxHours'        => (int)$c['room_max_hours'],
        ];
    }

    /** @return array<string,mixed> */
    private static function live(): array
    {
        return self::$rules ?? self::build(ArbitrationConfig::defaults());
    }

    public static function maxActive(): int            { return (int)self::live()['maxActive']; }
    public static function maxAdvanceWeeks(): int      { return (int)self::live()['maxAdvanceWeeks']; }
    public static function maxAdvanceDays(): int       { return self::maxAdvanceWeeks() * 7; }
    public static function cancelCutoffSeconds(): int  { return (int)self::live()['cancelCutoffMin'] * 60; }
    public static function sameDayAuto(): bool         { return (bool)self::live()['sameDayAuto']; }
    public static function letterRequired(): bool      { return (bool)self::live()['letterRequired']; }
    public static function expiryMode(): string        { return (string)self::live()['expiryMode']; }
    public static function dayStart(): string          { return (string)self::live()['dayStart']; }
    public static function dayEnd(): string            { return (string)self::live()['dayEnd']; }
    public static function maxHours(): int             { return (int)self::live()['maxHours']; }
    /** @return list<int> ISO weekdays rooms can be reserved on (1 = Monday … 7 = Sunday) */
    public static function openDays(): array           { return self::live()['openDays']; }

    private const DAY_NAMES = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /** "7:00 AM" for an HH:MM string */
    public static function clock(string $hhmm): string
    {
        $ts = strtotime('2000-01-01 ' . substr($hhmm, 0, 5) . ':00');
        return $ts ? date('g:i A', $ts) : $hhmm;
    }

    /** "7:00 AM to 8:00 PM" */
    public static function hoursLabel(): string
    {
        return self::clock(self::dayStart()) . ' to ' . self::clock(self::dayEnd());
    }

    /** "Monday to Saturday", "Monday, Wednesday and Friday" … */
    public static function openDaysLabel(): string
    {
        $days = self::openDays();
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
                $parts[] = self::DAY_NAMES[$a];
            } elseif ($b === $a + 1) {
                $parts[] = self::DAY_NAMES[$a] . ' and ' . self::DAY_NAMES[$b];
            } else {
                $parts[] = self::DAY_NAMES[$a] . ' to ' . self::DAY_NAMES[$b];
            }
        }
        $last = array_pop($parts);
        return $parts ? implode(', ', $parts) . ' and ' . $last : (string)$last;
    }

    public static function isOpenDay(string $resDate): bool
    {
        $ts = strtotime($resDate);
        return $ts !== false && in_array((int)date('N', $ts), self::openDays(), true);
    }

    /** Message when rooms are not open on $resDate's weekday, else null. */
    public static function closedDayError(string $resDate): ?string
    {
        if (self::isOpenDay($resDate)) {
            return null;
        }
        $closed = [];
        foreach (self::DAY_NAMES as $n => $name) {
            if (!in_array($n, self::openDays(), true)) {
                $closed[] = $name . 's';
            }
        }
        $last = array_pop($closed);
        $list = $closed ? implode(', ', $closed) . ' and ' . $last : $last;
        return 'Reservations are not available on ' . $list . '. Please choose another day.';
    }

    /** Message when the times fall outside the reservation hours, else null. */
    public static function hoursError(string $start, string $end): ?string
    {
        $s = substr($start, 0, 5);
        $e = substr($end, 0, 5);
        if ($s < self::dayStart() || $e > self::dayEnd()) {
            return 'Reservations must be within operating hours: ' . self::hoursLabel() . '.';
        }
        return null;
    }

    /** Message when one reservation is longer than the Super Admin allows, else null. */
    public static function lengthError(string $start, string $end): ?string
    {
        $mins = (int)round((strtotime('2000-01-01 ' . substr($end, 0, 5) . ':00') - strtotime('2000-01-01 ' . substr($start, 0, 5) . ':00')) / 60);
        if ($mins > self::maxHours() * 60) {
            $h = self::maxHours();
            return 'A reservation can be at most ' . $h . ($h === 1 ? ' hour' : ' hours') . ' long.';
        }
        return null;
    }

    /** "1 hour" / "2 hours" / "30 minutes": the cancellation cutoff in words. */
    public static function cancelCutoffLabel(): string
    {
        $min = intdiv(self::cancelCutoffSeconds(), 60);
        if ($min >= 60 && $min % 60 === 0) {
            $h = intdiv($min, 60);
            return $h . ($h === 1 ? ' hour' : ' hours');
        }
        return $min . ($min === 1 ? ' minute' : ' minutes');
    }

    /** "3 weeks" / "1 week" */
    public static function maxAdvanceLabel(): string
    {
        $w = self::maxAdvanceWeeks();
        return $w . ($w === 1 ? ' week' : ' weeks');
    }

    /** True when the reservation has to wait for an admin: any later date, or today when same-day auto-confirm is off. */
    public static function holdsForReview(string $resDate): bool
    {
        $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
        return $resDate > $today || !self::sameDayAuto();
    }

    /** True when $resDate (Y-m-d) is after today in Asia/Manila. */
    public static function isLaterDate(string $resDate): bool
    {
        return $resDate > (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    }

    /** Message when $resDate (Y-m-d) is further ahead than allowed, else null. */
    public static function advanceError(string $resDate): ?string
    {
        $tz     = new DateTimeZone('Asia/Manila');
        $latest = (new DateTimeImmutable('now', $tz))->modify('+' . self::maxAdvanceDays() . ' days')->format('Y-m-d');
        if ($resDate > $latest) {
            return 'Rooms can only be reserved up to ' . self::maxAdvanceLabel() . ' ahead (latest date: ' . date('M j, Y', strtotime($latest)) . ').';
        }
        return null;
    }

    /** What the faculty Facilities screen needs to mirror the rules. */
    public static function meta(): array
    {
        return [
            'maxActive'       => self::maxActive(),
            'maxAdvanceDays'  => self::maxAdvanceDays(),
            'cancelCutoffMin' => intdiv(self::cancelCutoffSeconds(), 60),
            'sameDayAuto'     => self::sameDayAuto(),
            'letterRequired'  => self::letterRequired(),
            'dayStart'        => self::dayStart(),
            'dayEnd'          => self::dayEnd(),
            'openDays'        => self::openDays(),     // ISO: 1 = Monday … 7 = Sunday
            'maxHours'        => self::maxHours(),
        ];
    }
}
