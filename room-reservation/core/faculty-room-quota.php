<?php

/**
 * room-reservation/core/faculty-room-quota.php
 *
 * Shared rules for faculty room reservations. Used by:
 *   - api/submit-faculty-reserve.php  (enforces the rules)
 *   - api/get-room-quota.php          (tells the UI where the faculty stands)
 *
 * RULES
 *   1. Equipment never blocks a room: a faculty member may reserve a room even
 *      while equipment is out or overdue (the arbitration engine no longer
 *      applies the overdue block to rooms).
 *   2. One faculty account may hold at most FCTY_MAX_ACTIVE_ROOM_RESERVATIONS
 *      active room reservations at the same time (2 is fine, 3 is not).
 *        "Active" = status Approved AND not finished yet (the end time is still
 *        ahead). Declined / Cancelled / already-finished ones do not count, so
 *        a slot is freed by cancelling or simply by waiting for it to end.
 *        Reservations made by a faculty member's students through their room
 *        code (submitted_as = student) are not the faculty member's own
 *        bookings and do not count.
 *   3. Only Organization Advisers may attach a supporting document (JPG, PNG or
 *      PDF, max 5 MB). It is optional. Attaching one files the reservation as
 *      submitted_as = adviser and stores the path in document_path, which the
 *      arbitration engine already reads for priority.
 */

require_once __DIR__ . '/room-expiry.php';   // 12:01 AM auto-decline of undecided Waiting reservations

if (!defined('FCTY_MAX_ACTIVE_ROOM_RESERVATIONS')) {
    define('FCTY_MAX_ACTIVE_ROOM_RESERVATIONS', 2);
}
if (!defined('FCTY_ROOM_DOC_MAX_BYTES')) {
    define('FCTY_ROOM_DOC_MAX_BYTES', 5 * 1024 * 1024);
}

/**
 * The faculty member's own active room reservations, soonest first.
 *
 * @return array<int, array{id:int, room_name:string, date:string, start:string, end:string}>
 */
function fcty_active_room_reservations(mysqli $conn, string $faculty_id): array
{
    // An undecided request that has run out of time must stop counting before we count.
    ps_room_expire_unreviewed($conn);

    $now   = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $today = $now->format('Y-m-d');
    $time  = $now->format('H:i:s');

    // Approved AND Waiting both count: a request waiting for an admin is still a booking the
    // member has asked for, otherwise the limit could be dodged by filing many future requests.
    $stmt = $conn->prepare(
        "SELECT rr.id, r.room_name, rr.reservation_date, rr.start_time, rr.end_time
           FROM tbl_room_reservations rr
           JOIN tbl_rooms r ON r.room_id = rr.room_id
          WHERE rr.faculty_id   = ?
            AND rr.status       IN ('Approved', 'Waiting')
            AND rr.submitted_as <> 'student'
            AND (rr.reservation_date > ?
                 OR (rr.reservation_date = ? AND rr.end_time > ?))
          ORDER BY rr.reservation_date ASC, rr.start_time ASC"
    );
    if ($stmt === false) {
        error_log('[PUPSync] fcty_active_room_reservations prepare failed: ' . $conn->error);
        return [];
    }
    $stmt->bind_param('ssss', $faculty_id, $today, $today, $time);
    $stmt->execute();
    $res  = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id'        => (int)$row['id'],
            'room_name' => (string)$row['room_name'],
            'date'      => (string)$row['reservation_date'],
            'start'     => substr((string)$row['start_time'], 0, 5),
            'end'       => substr((string)$row['end_time'], 0, 5),
        ];
    }
    $stmt->close();
    return $rows;
}

/** True when the account is an Organization Adviser (read fresh from the DB). */
function fcty_is_org_adviser(mysqli $conn, string $faculty_id): bool
{
    $stmt = $conn->prepare('SELECT role FROM tbl_users WHERE faculty_id = ? LIMIT 1');
    if ($stmt === false) {
        return false;
    }
    $stmt->bind_param('s', $faculty_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($row['role'] ?? '') === 'Organization Adviser';
}

/** Per-faculty advisory lock so two simultaneous submissions cannot both slip past the limit. */
function fcty_quota_lock(mysqli $conn, string $faculty_id): void
{
    $key  = 'pup_room_quota_' . md5($faculty_id);
    $stmt = $conn->prepare('SELECT GET_LOCK(?, 5)');
    if ($stmt) {
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->close();
    }
}

function fcty_quota_unlock(mysqli $conn, string $faculty_id): void
{
    $key  = 'pup_room_quota_' . md5($faculty_id);
    $stmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
    if ($stmt) {
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->close();
    }
}

/** Human-friendly "limit reached" message. */
function fcty_quota_message(): string
{
    return 'You already have ' . FCTY_MAX_ACTIVE_ROOM_RESERVATIONS
        . ' active room reservations. Cancel one or wait until it ends to reserve another room.';
}


/* ============================================================================
 * MY ACTIVITY  --  the room-reservation half of the faculty "My Activity" screen
 *
 * One renderer, two callers, so the markup can never drift apart:
 *   - faculty-dashboard.php                       first paint of #panel-activity
 *   - api/poll-reservations.php?view=activity     live refresh (after a cancel / booking)
 *
 * What counts as what (all times Asia/Manila):
 *   live       Approved, already started, not finished yet   -> "In progress"
 *   upcoming   Approved, starts in the future                -> "Upcoming"
 *   history    Approved + finished (shown as "Completed"), Declined, Cancelled
 *   waitlist   slots the faculty member asked to be told about (future only)
 *
 * The equipment half lives in equipment-booking/core/faculty-activity.php and
 * uses the same helpers, status words and colours defined below.
 * Reservations a faculty member's students made through their room code
 * (submitted_as = student) are not the faculty member's own activity and are
 * left out here, exactly like the room limit above leaves them out.
 * ========================================================================== */

/** Escape for HTML. */
function fact_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/** Material Symbols glyph (the one icon set used across the dashboard). */
function fact_icon(string $name): string
{
    return '<span class="material-symbols-outlined" aria-hidden="true">' . fact_h($name) . '</span>';
}

/**
 * @param array<string, mixed> $row
 */
function fact_s(array $row, string $key): string
{
    $value = $row[$key] ?? '';
    return is_scalar($value) ? (string)$value : '';
}

/**
 * @param array<string, mixed> $row
 */
function fact_i(array $row, string $key): int
{
    $value = $row[$key] ?? 0;
    return is_numeric($value) ? (int)$value : 0;
}

/** The three PUP buildings use the same display names as the Facilities tab. */
function fact_building_label(string $key, string $name): string
{
    $labels = [
        'main-building-a' => 'Building A (Old)',
        'main-building-b' => 'Building B (New)',
        'cite-main'       => 'Building C (CITE)',
    ];
    return $labels[$key] ?? $name;
}

/** '08:00:00' or '08:00' -> '8:00 AM' */
function fact_time(string $time): string
{
    $hhmm = substr($time, 0, 5);
    $dt   = DateTimeImmutable::createFromFormat('H:i', $hhmm);
    return $dt !== false ? $dt->format('g:i A') : $hhmm;
}

/** Calendar days from today to $date (0 = today, 1 = tomorrow, negative = past). */
function fact_days_away(DateTimeImmutable $now, DateTimeImmutable $date): int
{
    return (int)$now->setTime(0, 0)->diff($date->setTime(0, 0))->format('%r%a');
}

/**
 * Load everything the room half of My Activity needs.
 *
 * @return array{
 *   live: list<array<string, mixed>>,
 *   upcoming: list<array<string, mixed>>,
 *   waitlist: list<array<string, mixed>>,
 *   history: list<array<string, mixed>>,
 *   active: int,
 *   max: int
 * }
 */
function fact_load_rooms(mysqli $conn, string $faculty_id): array
{
    $out = [
        'live' => [],
        'pending' => [],
        'upcoming' => [],
        'waitlist' => [],
        'history' => [],
        'active' => 0,
        'max' => FCTY_MAX_ACTIVE_ROOM_RESERVATIONS,
    ];
    $tz  = new DateTimeZone('Asia/Manila');
    $now = new DateTimeImmutable('now', $tz);

    try {
        ps_room_expire_unreviewed($conn);   // so an overdue request shows as Declined, not Pending

        $stmt = $conn->prepare(
            "SELECT rr.id, rr.room_id, rr.reservation_date, rr.start_time, rr.end_time,
                    rr.purpose, rr.submitted_as, rr.document_path, rr.status, rr.reason,
                    r.room_name, b.building_key, b.name AS building_name,
                    COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label
               FROM tbl_room_reservations rr
               JOIN tbl_rooms     r ON r.room_id     = rr.room_id
               JOIN tbl_buildings b ON b.building_id = r.building_id
              WHERE rr.faculty_id = ? AND rr.submitted_as <> 'student'
              ORDER BY rr.reservation_date DESC, rr.start_time DESC
              LIMIT 300"
        );
        if ($stmt !== false) {
            $stmt->bind_param('s', $faculty_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res !== false && ($row = $res->fetch_assoc())) {
                $start = new DateTimeImmutable(fact_s($row, 'reservation_date') . ' ' . fact_s($row, 'start_time'), $tz);
                $end   = new DateTimeImmutable(fact_s($row, 'reservation_date') . ' ' . fact_s($row, 'end_time'), $tz);

                $item = [
                    'id'        => fact_i($row, 'id'),
                    'room_id'   => fact_i($row, 'room_id'),
                    'room'      => fact_s($row, 'room_name'),
                    'where'     => fact_building_label(fact_s($row, 'building_key'), fact_s($row, 'building_name'))
                        . ' · ' . fact_s($row, 'floor_label'),
                    'date'      => fact_s($row, 'reservation_date'),
                    'start'     => substr(fact_s($row, 'start_time'), 0, 5),
                    'end'       => substr(fact_s($row, 'end_time'), 0, 5),
                    'purpose'   => fact_s($row, 'purpose'),
                    'reason'    => fact_s($row, 'reason'),
                    'letter'    => fact_s($row, 'document_path') !== '',
                    'start_ts'  => $start->getTimestamp(),
                    'days'      => fact_days_away($now, $start),
                    'can_cancel' => ($start->getTimestamp() - $now->getTimestamp()) > 3600,
                    'future'    => $start > $now,
                    'state'     => 'upcoming',
                ];

                $status = fact_s($row, 'status');
                if ($status === 'Approved') {
                    if ($end <= $now) {
                        $item['state'] = 'completed';
                        $out['history'][] = $item;
                    } elseif ($start <= $now) {
                        $item['state'] = 'live';
                        $out['live'][] = $item;
                    } else {
                        $out['upcoming'][] = $item;
                    }
                } elseif ($status === 'Waiting') {
                    // Waiting for an admin (later date, or an adviser letter). Holds no slot yet, so it can be
                    // withdrawn at any time; it is never shown as Declined.
                    $item['state']      = 'pending';
                    $item['can_cancel'] = true;
                    $out['pending'][]   = $item;
                } else {
                    $item['state'] = $status === 'Cancelled' ? 'cancelled' : 'declined';
                    $out['history'][] = $item;
                }
            }
            $stmt->close();
        }

        // Soonest first for what is coming up
        usort($out['upcoming'], static fn(array $a, array $b): int => fact_i($a, 'start_ts') <=> fact_i($b, 'start_ts'));

        $today = $now->format('Y-m-d');
        $time  = $now->format('H:i:s');
        $wl = $conn->prepare(
            "SELECT w.room_id, w.reservation_date, w.start_time, w.end_time,
                    r.room_name, b.building_key, b.name AS building_name,
                    COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label
               FROM tbl_room_waitlist w
               JOIN tbl_rooms     r ON r.room_id     = w.room_id
               JOIN tbl_buildings b ON b.building_id = r.building_id
              WHERE w.faculty_id = ?
                AND (w.reservation_date > ? OR (w.reservation_date = ? AND w.end_time > ?))
              ORDER BY w.reservation_date ASC, w.start_time ASC
              LIMIT 20"
        );
        if ($wl !== false) {
            $wl->bind_param('ssss', $faculty_id, $today, $today, $time);
            $wl->execute();
            $res = $wl->get_result();
            while ($res !== false && ($row = $res->fetch_assoc())) {
                $start = new DateTimeImmutable(fact_s($row, 'reservation_date') . ' ' . fact_s($row, 'start_time'), $tz);
                $out['waitlist'][] = [
                    'room_id'  => fact_i($row, 'room_id'),
                    'room'     => fact_s($row, 'room_name'),
                    'where'    => fact_building_label(fact_s($row, 'building_key'), fact_s($row, 'building_name'))
                        . ' · ' . fact_s($row, 'floor_label'),
                    'date'     => fact_s($row, 'reservation_date'),
                    'start'    => substr(fact_s($row, 'start_time'), 0, 5),
                    'end'      => substr(fact_s($row, 'end_time'), 0, 5),
                    'days'     => fact_days_away($now, $start),
                    'start_ts' => $start->getTimestamp(),
                ];
            }
            $wl->close();
        }
    } catch (Throwable $e) {
        // The rest of the dashboard must keep working even if this block cannot load.
        error_log('[PUPSync] fact_load_rooms failed: ' . $e->getMessage());
    }

    $out['active'] = count($out['live']) + count($out['upcoming']) + count($out['pending']);
    return $out;
}

/* ----------------------------------------------------------------------------
 * Shared look-and-wording helpers (used by the room AND the equipment half)
 * -------------------------------------------------------------------------- */

/**
 * Small status pill. Four tones only, the same colours the Home tab, the admin
 * portal and the Help Center use for the same words:
 *   ok    green   Approved
 *   wait  amber   Pending, Waitlisted
 *   bad   red     Overdue, Declined
 *   muted grey    Returned, Completed, Cancelled
 * ($icon is kept so older callers still work; a status is words + colour only.)
 */
function fact_chip(string $text, string $tone, string $icon = ''): string
{
    return '<span class="fact-chip is-' . fact_h($tone) . '">' . fact_h($text) . '</span>';
}

/** One place decides how a status is worded and coloured on the My Activity screen. */
function fact_status_chip(string $status): string
{
    static $map = [
        'Waiting'    => ['Pending',    'wait'],
        'Waitlisted' => ['Waitlisted', 'wait'],
        'Approved'   => ['Approved',   'ok'],
        'Overdue'    => ['Overdue',    'bad'],
        'Declined'   => ['Declined',   'bad'],
        'Returned'   => ['Returned',   'muted'],
        'Completed'  => ['Completed',  'muted'],
        'Cancelled'  => ['Cancelled',  'muted'],
    ];
    [$label, $tone] = $map[$status] ?? [$status !== '' ? $status : 'Unknown', 'muted'];
    return fact_chip($label, $tone);
}

/** "Today", "Tomorrow", "Oct 20" (year added only when it is not the current year). */
function fact_day_label(DateTimeImmutable $now, DateTimeImmutable $date, bool $relative = true): string
{
    if ($relative) {
        $days = fact_days_away($now, $date);
        if ($days === 0) {
            return 'Today';
        }
        if ($days === 1) {
            return 'Tomorrow';
        }
    }
    return $date->format($date->format('Y') === $now->format('Y') ? 'M j' : 'M j, Y');
}

/**
 * "Today, 1:00 PM – 3:00 PM"  /  "Oct 20, 8:00 AM – Oct 22, 5:00 PM"  /  "Oct 20 – Oct 22".
 * Times are optional (older requests have none).
 */
function fact_range_label(
    DateTimeImmutable $now,
    DateTimeImmutable $from,
    ?string $fromTime,
    DateTimeImmutable $to,
    ?string $toTime,
    bool $relative = true
): string {
    $t1 = ($fromTime ?? '') !== '' ? fact_time((string)$fromTime) : '';
    $t2 = ($toTime ?? '') !== '' ? fact_time((string)$toTime) : '';
    $d1 = fact_day_label($now, $from, $relative);
    $d2 = fact_day_label($now, $to, $relative);

    if ($from->format('Y-m-d') === $to->format('Y-m-d')) {
        if ($t1 !== '' && $t2 !== '') {
            return $d1 . ', ' . $t1 . ' – ' . $t2;
        }
        return $t1 !== '' ? $d1 . ', ' . $t1 : $d1;
    }
    return $d1 . ($t1 !== '' ? ', ' . $t1 : '') . ' – ' . $d2 . ($t2 !== '' ? ', ' . $t2 : '');
}

/** A labelled group of rows; more than three collapse behind "Show all". */
function fact_render_group(string $label, array $itemsHtml): string
{
    if (!$itemsHtml) {
        return '';
    }
    $total     = count($itemsHtml);
    $collapsed = $total > 3;
    $html  = '<div class="fact-group"><h3 class="fact-group-label">' . fact_h($label) . '</h3>';
    $html .= '<div class="fact-list' . ($collapsed ? ' is-collapsed' : '') . '">' . implode('', $itemsHtml) . '</div>';
    if ($collapsed) {
        $html .= '<button type="button" class="fact-more" data-fact-more aria-expanded="false">Show all ' . $total
            . ' <span class="material-symbols-outlined" aria-hidden="true">expand_more</span></button>';
    }
    return $html . '</div>';
}

/** Empty state: one icon, one title, one short line. */
function fact_render_empty(string $icon, string $title, string $sub): string
{
    return '<div class="fact-empty">' . fact_icon($icon)
        . '<p class="fact-empty-title">' . fact_h($title) . '</p>'
        . '<p class="fact-empty-sub">' . fact_h($sub) . '</p></div>';
}

/*
 * No longer used by My Activity (rooms now show their date in the text line, and
 * status is a plain pill). Kept so nothing else that may call them breaks.
 */

/** "In progress", "Today", "Tomorrow", "In 3 days" */
function fact_when_chip(string $state, int $days): string
{
    if ($state === 'live') {
        return fact_chip('In progress', 'live');
    }
    if ($days <= 0) {
        return fact_chip('Today', 'today');
    }
    if ($days === 1) {
        return fact_chip('Tomorrow', 'soon');
    }
    return fact_chip('In ' . $days . ' days', 'soon');
}

/** Calendar-style date tile. */
function fact_date_tile(string $date): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if ($dt === false) {
        return '';
    }
    return '<div class="fact-date" aria-hidden="true"><span class="fact-date-day">' . $dt->format('j')
        . '</span><span class="fact-date-mon">' . $dt->format('M') . '</span></div>';
}

/* ----------------------------------------------------------------------------
 * Rooms: rows, card body, history rows, summary numbers
 * -------------------------------------------------------------------------- */

/**
 * One reservation row for the "Room reservations" card.
 * Same anatomy as an equipment row: title + status pill, when, where, actions.
 *
 * @param array<string, mixed> $it
 */
function fact_render_room_item(array $it, string $kind): string
{
    $tz    = new DateTimeZone('Asia/Manila');
    $now   = new DateTimeImmutable('now', $tz);
    $date  = DateTimeImmutable::createFromFormat('Y-m-d', fact_s($it, 'date'), $tz);
    $when  = $date !== false
        ? fact_range_label($now, $date, fact_s($it, 'start'), $date, fact_s($it, 'end'))
        : fact_time(fact_s($it, 'start')) . ' – ' . fact_time(fact_s($it, 'end'));
    $room  = fact_s($it, 'room');

    $sub = [fact_h(fact_s($it, 'where'))];
    if ($kind === 'waitlist') {
        $sub[] = 'We\'ll notify you if this slot opens up';
    } else {
        if (fact_s($it, 'purpose') !== '') {
            $sub[] = fact_h(fact_s($it, 'purpose'));
        }
        if (!empty($it['letter'])) {
            $sub[] = '<span class="fact-attach" title="A supporting letter is attached">' . fact_icon('attach_file') . 'Letter</span>';
        }
        if ($kind === 'pending') {
            $sub[] = 'Waiting for admin approval';
        }
    }

    $html  = '<article class="fact-item fact-room" data-rr-id="' . fact_i($it, 'id') . '">';
    $html .= '<span class="fact-tile">' . fact_icon('meeting_room') . '</span>';
    $html .= '<div class="fact-item-main"><div class="fact-item-top"><h4 class="fact-item-title">' . fact_h($room) . '</h4>';
    $html .= fact_status_chip($kind === 'waitlist' ? 'Waitlisted' : ($kind === 'pending' ? 'Waiting' : 'Approved')) . '</div>';
    $html .= '<p class="fact-item-meta">' . fact_h($when) . '</p>';
    $html .= '<p class="fact-item-sub">' . implode(' · ', $sub) . '</p>';

    // A waitlist entry can be dropped at any time
    if ($kind === 'waitlist') {
        $html .= '<div class="fact-item-actions"><button type="button" class="fact-btn fact-btn-ghost" data-action="leave-waitlist"'
            . ' data-room-id="' . fact_i($it, 'room_id') . '" data-room-name="' . fact_h($room) . '"'
            . ' data-res-date="' . fact_h(fact_s($it, 'date')) . '"'
            . ' data-start-time="' . fact_h(fact_s($it, 'start')) . '" data-end-time="' . fact_h(fact_s($it, 'end')) . '"'
            . ' title="Stop waiting for this slot">Leave waitlist</button></div>';
    }

    // Only upcoming reservations can be cancelled, and not within 1 hour of the start
    if ($kind === 'upcoming') {
        $html .= '<div class="fact-item-actions">';
        if (!empty($it['can_cancel'])) {
            $html .= '<button type="button" class="fact-btn fact-btn-ghost is-danger" data-action="cancel-reservation"'
                . ' data-rr-id="' . fact_i($it, 'id') . '" data-room-name="' . fact_h($room) . '"'
                . ' title="Cancel this reservation">Cancel</button>';
        } else {
            $html .= '<span class="fact-note" title="Reservations can\'t be cancelled within 1 hour of the start time">'
                . fact_icon('lock') . 'Can\'t be cancelled within 1 hour of the start</span>';
        }
        $html .= '</div>';
    }
    // A request that is still waiting holds no slot, so it can be withdrawn at any time
    if ($kind === 'pending') {
        $html .= '<div class="fact-item-actions"><button type="button" class="fact-btn fact-btn-ghost is-danger" data-action="cancel-reservation"'
            . ' data-rr-id="' . fact_i($it, 'id') . '" data-room-name="' . fact_h($room) . '"'
            . ' title="Withdraw this request">Withdraw</button></div>';
    }
    return $html . '</div></article>';
}

/**
 * Body of the "Room reservations" card.
 *
 * @param array<string, mixed> $d result of fact_load_rooms()
 */
function fact_render_rooms_body(array $d): string
{
    $live     = is_array($d['live'] ?? null) ? $d['live'] : [];
    $pending  = is_array($d['pending'] ?? null) ? $d['pending'] : [];
    $upcoming = is_array($d['upcoming'] ?? null) ? $d['upcoming'] : [];
    $waitlist = is_array($d['waitlist'] ?? null) ? $d['waitlist'] : [];
    $active   = fact_i($d, 'active');
    $max      = max(1, fact_i($d, 'max'));
    $full     = $active >= $max;

    // The room limit is a real rule (see the top of this file): say it once, in one quiet line.
    $html = '<p class="fact-limit' . ($full ? ' is-full' : '') . '"><strong>' . $active . ' of ' . $max . '</strong> active reservations'
        . ($full ? ' &middot; Limit reached. Cancel one or wait until it ends to reserve another.' : '') . '</p>';

    if (!$live && !$pending && !$upcoming && !$waitlist) {
        return $html . fact_render_empty('event_available', 'No room reservations', 'Reserve a room for your class, lab session or meeting.');
    }

    $groups = [
        ['In progress', 'live', $live],
        ['Waiting for approval', 'pending', $pending],
        ['Upcoming', 'upcoming', $upcoming],
        ['Waitlist', 'waitlist', $waitlist],
    ];
    foreach ($groups as [$label, $kind, $items]) {
        $rows = [];
        foreach ($items as $it) {
            if (is_array($it)) {
                /** @var array<string, mixed> $it */
                $rows[] = fact_render_room_item($it, $kind);
            }
        }
        $html .= fact_render_group($label, $rows);
    }
    return $html;
}

/**
 * History rows (<tr>) for rooms. Merged into the shared history table
 * (Activity | When | Status).
 *
 * @param array<string, mixed> $d result of fact_load_rooms()
 */
function fact_render_room_history_rows(array $d): string
{
    $items = is_array($d['history'] ?? null) ? $d['history'] : [];
    $html  = '';
    $now   = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));

    // Slots the faculty member is already waiting on (so we don't offer to join twice)
    $waiting = [];
    $wl_items = is_array($d['waitlist'] ?? null) ? $d['waitlist'] : [];
    foreach ($wl_items as $w) {
        if (is_array($w)) {
            /** @var array<string, mixed> $w */
            $waiting[fact_i($w, 'room_id') . '|' . fact_s($w, 'date') . '|' . fact_s($w, 'start') . '|' . fact_s($w, 'end')] = true;
        }
    }

    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        /** @var array<string, mixed> $it */
        $state  = fact_s($it, 'state');
        $status = ['completed' => 'Completed', 'declined' => 'Declined', 'cancelled' => 'Cancelled'][$state] ?? 'Completed';

        $dt    = DateTimeImmutable::createFromFormat('Y-m-d', fact_s($it, 'date'));
        $day   = $dt !== false ? fact_day_label($now, $dt, false) : fact_s($it, 'date');
        $times = fact_time(fact_s($it, 'start')) . ' – ' . fact_time(fact_s($it, 'end'));

        // Second line: why it ended this way (or what it was for)
        $note = fact_h(fact_s($it, 'purpose'));
        if (fact_s($it, 'reason') !== '' && $state !== 'completed') {
            $note = fact_h(fact_s($it, 'reason'));
        }
        $slot_key = fact_i($it, 'room_id') . '|' . fact_s($it, 'date') . '|' . fact_s($it, 'start') . '|' . fact_s($it, 'end');
        if ($state === 'declined' && !empty($it['future']) && isset($waiting[$slot_key])) {
            $note .= ' <span class="fact-linkbtn is-done">On the waitlist</span>';
        } elseif ($state === 'declined' && !empty($it['future'])) {
            $note .= ' <button type="button" class="fact-linkbtn" data-action="join-waitlist"'
                . ' data-room-id="' . fact_i($it, 'room_id') . '" data-room-name="' . fact_h(fact_s($it, 'room')) . '"'
                . ' data-res-date="' . fact_h(fact_s($it, 'date')) . '"'
                . ' data-start-time="' . fact_h(fact_s($it, 'start')) . '" data-end-time="' . fact_h(fact_s($it, 'end')) . '">'
                . 'Join waitlist</button>';
        }

        $html .= '<tr class="fact-row" data-kind="room" data-status="' . fact_h($state) . '" data-ts="' . fact_i($it, 'start_ts') . '">'
            . '<td data-label="Activity"><div class="fact-cell"><span class="fact-tile">' . fact_icon('meeting_room') . '</span>'
            . '<div class="fact-cell-text"><p class="fact-row-title">' . fact_h(fact_s($it, 'room')) . '</p>'
            . '<p class="fact-row-sub">Room · ' . fact_h(fact_s($it, 'where')) . '</p>'
            . ($note !== '' ? '<p class="fact-row-sub">' . $note . '</p>' : '')
            . '</div></div></td>'
            . '<td data-label="When"><span class="fact-when">' . fact_h($day) . '</span><span class="fact-row-sub">' . fact_h($times) . '</span></td>'
            . '<td data-label="Status" class="fact-col-status">' . fact_status_chip($status) . '</td>'
            . '</tr>';
    }
    return $html;
}

/**
 * Values for the room tile in the summary strip (plus the older "next booking" pair,
 * which the live-refresh endpoint still returns).
 *
 * @param array<string, mixed> $d result of fact_load_rooms()
 * @return array{reserved: string, reserved_sub: string, next: string, next_sub: string, active: int}
 */
function fact_room_stats(array $d): array
{
    $live     = is_array($d['live'] ?? null) ? $d['live'] : [];
    $upcoming = is_array($d['upcoming'] ?? null) ? $d['upcoming'] : [];
    $active   = fact_i($d, 'active');
    $max      = max(1, fact_i($d, 'max'));
    $left     = max(0, $max - $active);

    $next = $live[0] ?? $upcoming[0] ?? null;
    $next_value = '—';
    $next_sub   = 'No upcoming booking';
    if (is_array($next)) {
        /** @var array<string, mixed> $next */
        $days = fact_i($next, 'days');
        $dt   = DateTimeImmutable::createFromFormat('Y-m-d', fact_s($next, 'date'));
        if (fact_s($next, 'state') === 'live') {
            $next_value = 'Now';
        } elseif ($days <= 0) {
            $next_value = 'Today';
        } elseif ($days === 1) {
            $next_value = 'Tomorrow';
        } else {
            $next_value = $dt !== false ? $dt->format('M j') : '—';
        }
        $next_sub = fact_s($next, 'room') . ' · ' . fact_time(fact_s($next, 'start'));
    }

    return [
        'reserved'     => $active . ' / ' . $max,
        'reserved_sub' => $left === 0 ? 'Limit reached' : $left . ' more available',
        'next'         => $next_value,
        'next_sub'     => $next_sub,
        'active'       => $active,
    ];
}
