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
        ];
    }
}
