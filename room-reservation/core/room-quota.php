<?php

/**
 * room-reservation/core/room-quota.php
 *
 * Shared rules for faculty room reservations. Used by:
 *   - api/submit-faculty-reserve.php  (enforces the rules)
 *   - api/get-room-quota.php          (tells the UI where the faculty stands)
 *
 * RULES
 *   1. Equipment never blocks a room: a faculty member may reserve a room even
 *      while equipment is out or overdue (the arbitration engine no longer
 *      applies the overdue block to rooms).
 *   2. One faculty account may hold at most RoomRules::maxActive() active room reservations
 *      at the same time (Settings → Arbitration → Reservation rules; 2 unless the Super Admin changed it).
 *        "Active" = status Approved or Waiting AND not finished yet (the end
 *        time is still ahead). Declined / Cancelled / already-finished ones do
 *        not count, so a slot is freed by cancelling or simply by waiting for it to end.
 *        Reservations made by students through the faculty member's code count
 *        toward the same faculty account limit.
 *   3. A request letter (JPG, PNG or PDF, max 5 MB) may be attached to a reservation for a
 *      LATER DATE. It is optional but recommended, and its path is stored in document_path
 *      for the reviewing admin. Same-day reservations never need one.
 */

require_once __DIR__ . '/room-rules.php';    // Settings → Arbitration values (limit, advance, cutoff, same-day, letter, expiry)
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
 * @return array<int, array{id:int, room_name:string, date:string, start:string, end:string, submitted_as:string, student_name:string, student_id:string}>
 */
function fcty_active_room_reservations(mysqli $conn, string $faculty_id): array
{
    RoomRules::load($conn);

    // An undecided request that has run out of time must stop counting before we count.
    ps_room_expire_unreviewed($conn);

    $now   = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $today = $now->format('Y-m-d');
    $time  = $now->format('H:i:s');

    // Approved AND Waiting both count: a request waiting for an admin is still a booking the
    // member has asked for, otherwise the limit could be dodged by filing many future requests.
    $stmt = $conn->prepare(
        "SELECT rr.id, r.room_name, rr.reservation_date, rr.start_time, rr.end_time,
                rr.submitted_as, rr.submitted_by_name, rr.submitted_by_id
           FROM tbl_room_reservations rr
           JOIN tbl_rooms r ON r.room_id = rr.room_id
          WHERE rr.faculty_id   = ?
            AND rr.status       IN ('Approved', 'Waiting')
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
            'submitted_as' => (string)($row['submitted_as'] ?? ''),
            'student_name' => (string)($row['submitted_as'] ?? '') === 'student' ? (string)($row['submitted_by_name'] ?? '') : '',
            'student_id' => (string)($row['submitted_as'] ?? '') === 'student' ? (string)($row['submitted_by_id'] ?? '') : '',
        ];
    }
    $stmt->close();
    return $rows;
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
    $max = RoomRules::maxActive();
    return 'You already have ' . $max . ' active room ' . ($max === 1 ? 'reservation' : 'reservations')
        . '. Cancel one or wait until it ends to reserve another room.';
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
 * The equipment half lives in activity/core/faculty-data.php and
 * uses the same helpers, status words and colours defined below.
 * Reservations made through the faculty member's student authorization code
 * are included and labeled with the student who made them.
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
    RoomRules::load($conn);
    $out = [
        'live' => [],
        'pending' => [],
        'upcoming' => [],
        'waitlist' => [],
        'history' => [],
        'active' => 0,
        'student_active' => 0,
        'max' => RoomRules::maxActive(),
    ];
    $tz  = new DateTimeZone('Asia/Manila');
    $now = new DateTimeImmutable('now', $tz);

    try {
        ps_room_expire_unreviewed($conn);   // so an overdue request shows as Declined, not Pending

        $stmt = $conn->prepare(
            "SELECT rr.id, rr.room_id, rr.reservation_date, rr.start_time, rr.end_time,
                    rr.purpose, rr.submitted_as, rr.submitted_by_name, rr.submitted_by_id,
                    rr.document_path, rr.status, rr.reason,
                    r.room_name, b.building_key, b.name AS building_name,
                    COALESCE(r.floor_label, CONCAT(r.floor_number, 'F')) AS floor_label
               FROM tbl_room_reservations rr
               JOIN tbl_rooms     r ON r.room_id     = rr.room_id
               JOIN tbl_buildings b ON b.building_id = r.building_id
              WHERE rr.faculty_id = ?
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
                    'student_name' => fact_s($row, 'submitted_as') === 'student' ? fact_s($row, 'submitted_by_name') : '',
                    'student_id' => fact_s($row, 'submitted_as') === 'student' ? fact_s($row, 'submitted_by_id') : '',
                    'reason'    => fact_s($row, 'reason'),
                    'letter'    => fact_s($row, 'document_path') !== '',
                    'start_ts'  => $start->getTimestamp(),
                    'days'      => fact_days_away($now, $start),
                    'can_cancel' => ($start->getTimestamp() - $now->getTimestamp()) > RoomRules::cancelCutoffSeconds(),
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
                    // Waiting for an admin (a reservation for a later date). Holds no slot yet, so it can be
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
    foreach (['live', 'upcoming', 'pending'] as $bucket) {
        foreach ($out[$bucket] as $item) {
            if (fact_s($item, 'student_name') !== '') {
                $out['student_active']++;
            }
        }
    }
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
        'On Loan'    => ['On Loan',    'ok'],
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

/**
 * A labelled group of rows. Long lists are not collapsed here: each card pages its rows
 * (5 per page, page navigation in the card footer) in the My Activity script.
 */
function fact_render_group(string $label, array $itemsHtml): string
{
    if (!$itemsHtml) {
        return '';
    }
    return '<div class="fact-group"><h3 class="fact-group-label">' . fact_h($label) . '</h3>'
        . '<div class="fact-list">' . implode('', $itemsHtml) . '</div></div>';
}

/*
 * ── Where every button on My Activity lives ────────────────────────────────
 *   Page header, top right        Download Report                        (nothing else)
 *   Next steps list               no action buttons: each entry only points at the row below
 *                                 where the real button is (it scrolls to it and flashes it)
 *   Card / table header           filters and search                     (nothing else)
 *   Row action bar, bottom of     the buttons that apply to this row, packed to the left in this
 *   every Equipment / Room row    order, with the way OUT always last, pushed to the far right:
 *                                   claim   Claim QR            (main action, tinted)
 *                                   return  Return QR           (main action, tinted)
 *                                   extend  Extend
 *                                   end     Cancel / Withdraw / Leave waitlist
 *   History table, last column    Action: Join waitlist
 *   Card / table footer           page navigation (previous, numbers, next)
 * A slot that does not apply to a row is simply not drawn (no empty gaps). A button that is not
 * allowed right now (too late to cancel, extension limit reached) stays on the row, greyed out,
 * with the reason on hover.
 */

/**
 * One action button. Same look everywhere: icon + label. The label is plain text (the icon is
 * drawn by CSS from data-icon) so the page scripts can swap it for "Cancelling…" and back.
 *
 * @param array<string, string|int> $data  data-* attributes the page scripts read
 * @param array{danger?: bool, primary?: bool, disabled?: bool, title?: string, aria?: string} $opt
 */
function fact_action_btn(string $label, string $icon, array $data = [], array $opt = []): string
{
    $attrs = '';
    foreach ($data as $key => $value) {
        $attrs .= ' data-' . $key . '="' . fact_h((string)$value) . '"';
    }
    $disabled = !empty($opt['disabled']);
    $title    = (string)($opt['title'] ?? '');
    $aria     = (string)($opt['aria'] ?? '');
    return '<button type="button" class="fact-btn fact-btn-ghost' . (!empty($opt['danger']) ? ' is-danger' : '')
        . (!empty($opt['primary']) ? ' is-primary' : '') . '"'
        . ' data-icon="' . fact_h($icon) . '"' . $attrs
        . ($disabled ? ' disabled aria-disabled="true"' : '')
        . ($title !== '' ? ' title="' . fact_h($title) . '"' : '')
        . ($aria !== '' ? ' aria-label="' . fact_h($aria) . '"' : '')
        . '>' . fact_h($label) . '</button>';
}

/**
 * The action bar at the bottom of a row: the slots above, always in the same order. A slot with no
 * button is left out, so the bar is only as wide as the buttons the row really has.
 *
 * @param array{claim?: string, return?: string, extend?: string, end?: string} $slots ready-made buttons
 */
function fact_render_actions(array $slots): string
{
    $html = '<div class="fact-item-actions" role="group" aria-label="Actions">';
    foreach (['claim', 'return', 'extend', 'end'] as $slot) {
        if (($slots[$slot] ?? '') === '') {
            continue;
        }
        $html .= '<span class="fact-slot fact-slot-' . $slot . '">' . $slots[$slot] . '</span>';
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
        if (fact_s($it, 'student_name') !== '') {
            $student = 'Student reservation by ' . fact_s($it, 'student_name');
            if (fact_s($it, 'student_id') !== '') {
                $student .= ' (' . fact_s($it, 'student_id') . ')';
            }
            $sub[] = fact_h($student . ' · authorized through your faculty code');
        }
        if (fact_s($it, 'purpose') !== '') {
            $sub[] = fact_h(fact_s($it, 'purpose'));
        }
        if (!empty($it['letter'])) {
            $sub[] = '<span class="fact-attach" title="A supporting letter is attached">' . fact_icon('attach_file') . 'Letter</span>';
        }
        if ($kind === 'pending') {
            $sub[] = 'Waiting for admin approval';
        }
        if ($kind === 'upcoming' && empty($it['can_cancel'])) {
            $sub[] = 'Can\'t be cancelled within ' . RoomRules::cancelCutoffLabel() . ' of the start';
        }
    }

    // Rooms only have the "end" slot: leave the waitlist / withdraw a request / cancel a reservation
    $end = '';
    if ($kind === 'waitlist') {
        $end = fact_action_btn('Leave waitlist', 'close', [
            'action' => 'leave-waitlist', 'room-id' => fact_i($it, 'room_id'), 'room-name' => $room,
            'res-date' => fact_s($it, 'date'), 'start-time' => fact_s($it, 'start'), 'end-time' => fact_s($it, 'end'),
        ], ['danger' => true, 'title' => 'Stop waiting for this slot']);
    } elseif ($kind === 'pending') {
        // A request that is still waiting holds no slot, so it can be withdrawn at any time
        $end = fact_action_btn('Withdraw', 'close', [
            'action' => 'cancel-reservation', 'rr-id' => fact_i($it, 'id'), 'room-name' => $room,
        ], ['danger' => true, 'title' => 'Withdraw this request']);
    } elseif ($kind === 'upcoming') {
        // Only upcoming reservations can be cancelled, and not within the cancellation cutoff of the start
        $end = !empty($it['can_cancel'])
            ? fact_action_btn('Cancel', 'close', [
                'action' => 'cancel-reservation', 'rr-id' => fact_i($it, 'id'), 'room-name' => $room,
            ], ['danger' => true, 'title' => 'Cancel this reservation'])
            : fact_action_btn('Cancel', 'lock', [], [
                'danger' => true, 'disabled' => true,
                'title'  => 'Reservations can\'t be cancelled within ' . RoomRules::cancelCutoffLabel() . ' of the start time',
            ]);
    }

    $html  = '<article class="fact-item fact-room" data-rr-id="' . fact_i($it, 'id') . '">';
    $html .= '<span class="fact-tile">' . fact_icon('meeting_room') . '</span>';
    $html .= '<div class="fact-item-main"><div class="fact-item-top"><h4 class="fact-item-title">' . fact_h($room) . '</h4>';
    $html .= fact_status_chip($kind === 'waitlist' ? 'Waitlisted' : ($kind === 'pending' ? 'Waiting' : 'Approved')) . '</div>';
    $html .= '<p class="fact-item-meta">' . fact_h($when) . '</p>';
    $html .= '<p class="fact-item-sub">' . implode(' · ', $sub) . '</p>';
    $html .= fact_render_actions(['end' => $end]);
    return $html . '</div></article>';
}

/**
 * The line under the "Room reservations" title. The room limit is a real rule (see the top of
 * this file): it is said once, here, in one quiet line that is always in the same place.
 *
 * @param array<string, mixed> $d result of fact_load_rooms()
 */
function fact_render_rooms_status(array $d): string
{
    $active = fact_i($d, 'active');
    $max    = max(1, fact_i($d, 'max'));
    $full   = $active >= $max;
    return '<span class="fact-limit' . ($full ? ' is-full' : '') . '"'
        . ($full ? ' title="Limit reached. Cancel one or wait until it ends to reserve another."' : '') . '>'
        . '<strong>' . $active . ' of ' . $max . '</strong> active reservations'
        . ($full ? ' &middot; Limit reached' : '') . '</span>';
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
    $html     = '';

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
        if (fact_s($it, 'student_name') !== '') {
            $student = 'Student reservation by ' . fact_s($it, 'student_name');
            if (fact_s($it, 'student_id') !== '') {
                $student .= ' (' . fact_s($it, 'student_id') . ')';
            }
            $note = fact_h($student . ' · ' . fact_s($it, 'purpose'));
        }
        if (fact_s($it, 'reason') !== '' && $state !== 'completed') {
            $note = fact_h(fact_s($it, 'reason'));
        }
        // The only action a history row can have lives in its own "Action" column
        $action   = '';
        $slot_key = fact_i($it, 'room_id') . '|' . fact_s($it, 'date') . '|' . fact_s($it, 'start') . '|' . fact_s($it, 'end');
        if ($state === 'declined' && !empty($it['future']) && isset($waiting[$slot_key])) {
            $action = '<span class="fact-note">' . fact_icon('check') . 'On the waitlist</span>';
        } elseif ($state === 'declined' && !empty($it['future'])) {
            $action = fact_action_btn('Join waitlist', 'notifications', [
                'action' => 'join-waitlist', 'room-id' => fact_i($it, 'room_id'), 'room-name' => fact_s($it, 'room'),
                'res-date' => fact_s($it, 'date'), 'start-time' => fact_s($it, 'start'), 'end-time' => fact_s($it, 'end'),
            ]);
        }

        $html .= '<tr class="fact-row" data-kind="room" data-status="' . fact_h($state) . '" data-ts="' . fact_i($it, 'start_ts') . '">'
            . '<td data-label="Activity"><div class="fact-cell"><span class="fact-tile">' . fact_icon('meeting_room') . '</span>'
            . '<div class="fact-cell-text"><p class="fact-row-title">' . fact_h(fact_s($it, 'room')) . '</p>'
            . '<p class="fact-row-sub">Room · ' . fact_h(fact_s($it, 'where')) . '</p>'
            . ($note !== '' ? '<p class="fact-row-sub">' . $note . '</p>' : '')
            . '</div></div></td>'
            . '<td data-label="When"><span class="fact-when">' . fact_h($day) . '</span><span class="fact-row-sub">' . fact_h($times) . '</span></td>'
            . '<td data-label="Status" class="fact-col-status">' . fact_status_chip($status) . '</td>'
            . '<td data-label="Action" class="fact-col-action">' . $action . '</td>'
            . '</tr>';
    }
    return $html;
}

/**
 * Room numbers (the summary cards were removed from My Activity because the Home tab already shows
 * them; the live-refresh endpoint still returns these, so other callers keep working).
 *
 * @param array<string, mixed> $d result of fact_load_rooms()
 * @return array{reserved: string, reserved_sub: string, next: string, next_sub: string, active: int, student_active: int}
 */
function fact_room_stats(array $d): array
{
    $live     = is_array($d['live'] ?? null) ? $d['live'] : [];
    $upcoming = is_array($d['upcoming'] ?? null) ? $d['upcoming'] : [];
    $active   = fact_i($d, 'active');
    $max      = max(1, fact_i($d, 'max'));
    $left     = max(0, $max - $active);
    $studentActive = fact_i($d, 'student_active');

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
        'reserved_sub' => ($left === 0 ? 'Limit reached' : $left . ' more available')
            . ($studentActive > 0 ? ' · includes ' . $studentActive . ' student reservation' . ($studentActive === 1 ? '' : 's') : ''),
        'next'         => $next_value,
        'next_sub'     => $next_sub,
        'active'       => $active,
        'student_active' => $studentActive,
    ];
}

/* ----------------------------------------------------------------------------
 * Next steps: the short "what needs you right now" list at the top of My Activity
 *
 * It replaces the four summary cards (the Home tab already shows those numbers). Every entry is built
 * from the faculty member's real requests and says what is going on AND what they can do about it,
 * in the system's own rules and words. Most urgent first:
 *   1  Overdue equipment        return it (borrowing is blocked until then); extend if allowed
 *   2  Loan due back today      return it with the Return QR
 *   3  Approved equipment       collect it with the Claim QR (started, or starting within a day)
 *   4  Waiting for a decision   later-date equipment AND room requests: an admin must decide before
 *                               12:01 AM on the date, otherwise it is declined automatically
 *   5  Good to know             a room in use now, waitlist entries, declined slots you can still waitlist
 * The equipment half is built in activity/core/faculty-data.php (fact_eq_todo), the room half below
 * (fact_room_todo). Both feed the same list: first paint in activity/views/faculty-screen.php, live
 * refresh through the two ?view=activity endpoints (each returns its own entries as "todo").
 * An entry has no action button of its own: it points at the row (data-fact-goto) that has the button.
 * -------------------------------------------------------------------------- */

if (!defined('FACT_TODO_MAX')) {
    /** How many entries show at once (the page script reads the same number from the list's data-max). */
    define('FACT_TODO_MAX', 5);
}

/**
 * One entry.
 *
 * @param string $src    'equipment' or 'rooms' (which half owns it, so a refresh can swap just its own)
 * @param int    $prio   1 (most urgent) to 5 (good to know)
 * @param string $tone   bad | wait | ok | info  (same colours as the status pills)
 * @param string $target 'eq:12' request row, 'rr:5' reservation row, 'rooms' the room card, 'history-room'
 * @return array{src:string, prio:int, tone:string, icon:string, title:string, text:string, target:string, ts:int}
 */
function fact_todo_item(string $src, int $prio, string $tone, string $icon, string $title, string $text, string $target, int $ts = 0): array
{
    return ['src' => $src, 'prio' => $prio, 'tone' => $tone, 'icon' => $icon,
            'title' => $title, 'text' => $text, 'target' => $target, 'ts' => $ts];
}

/** "Projector", "Projector and HDMI Cable", "Projector and 2 more" */
function fact_todo_names(array $names): string
{
    $clean = [];
    foreach ($names as $name) {
        $name = trim(is_scalar($name) ? (string)$name : '');
        if ($name !== '' && !in_array($name, $clean, true)) {
            $clean[] = $name;
        }
    }
    $n = count($clean);
    if ($n === 0) {
        return 'your equipment';
    }
    if ($n === 1) {
        return $clean[0];
    }
    return $n === 2 ? $clean[0] . ' and ' . $clean[1] : $clean[0] . ' and ' . ($n - 1) . ' more';
}

/**
 * Most urgent first; entries of the same urgency by whichever is soonest.
 *
 * @param list<array<string, mixed>> $items
 * @return list<array<string, mixed>>
 */
function fact_todo_sort(array $items): array
{
    usort($items, static fn(array $a, array $b): int => [fact_i($a, 'prio'), fact_i($a, 'ts')] <=> [fact_i($b, 'prio'), fact_i($b, 'ts')]);
    return $items;
}

/**
 * The <li> markup for a list of entries. Entries from index $visible on are drawn hidden (the page
 * script applies the same rule after a live refresh).
 *
 * @param list<array<string, mixed>> $items
 */
function fact_todo_render_items(array $items, int $visible = PHP_INT_MAX): string
{
    $html = '';
    foreach (array_values($items) as $i => $it) {
        $html .= '<li class="fact-todo is-' . fact_h(fact_s($it, 'tone')) . '"'
            . ' data-src="' . fact_h(fact_s($it, 'src')) . '"'
            . ' data-prio="' . fact_i($it, 'prio') . '" data-ts="' . fact_i($it, 'ts') . '"'
            . ($i >= $visible ? ' hidden' : '') . '>'
            . '<button type="button" class="fact-todo-btn" data-fact-goto="' . fact_h(fact_s($it, 'target')) . '"'
            . ' title="Show it in the list below">'
            . '<span class="fact-todo-icon">' . fact_icon(fact_s($it, 'icon')) . '</span>'
            . '<span class="fact-todo-text"><strong class="fact-todo-title">' . fact_h(fact_s($it, 'title')) . '</strong>'
            . '<span class="fact-todo-sub">' . fact_h(fact_s($it, 'text')) . '</span></span>'
            . '<span class="fact-todo-go" aria-hidden="true">' . fact_icon('chevron_right') . '</span>'
            . '</button></li>';
    }
    return $html;
}

/**
 * First paint of the Next steps list: both halves merged, sorted, and capped at FACT_TODO_MAX.
 *
 * @param list<array<string, mixed>> $items
 */
function fact_todo_render(array $items): string
{
    return fact_todo_render_items(fact_todo_sort($items), FACT_TODO_MAX);
}

/**
 * Next steps that come from room reservations.
 *
 * @param array<string, mixed> $d result of fact_load_rooms()
 * @return list<array<string, mixed>>
 */
function fact_room_todo(array $d): array
{
    $tz    = new DateTimeZone('Asia/Manila');
    $now   = new DateTimeImmutable('now', $tz);
    $today = $now->format('Y-m-d');
    $out   = [];

    $live     = is_array($d['live'] ?? null) ? $d['live'] : [];
    $pending  = is_array($d['pending'] ?? null) ? $d['pending'] : [];
    $waitlist = is_array($d['waitlist'] ?? null) ? $d['waitlist'] : [];
    $history  = is_array($d['history'] ?? null) ? $d['history'] : [];

    // 4 · Requests for a later date wait for an admin. The deadline is real: 12:01 AM on the reservation
    // date (room-expiry.php), after which an undecided request is declined automatically.
    $pending = array_values(array_filter($pending, 'is_array'));
    usort($pending, static fn(array $a, array $b): int => fact_i($a, 'start_ts') <=> fact_i($b, 'start_ts'));
    if ($pending) {
        $first = $pending[0];
        $n     = count($pending);
        $date  = DateTimeImmutable::createFromFormat('Y-m-d', fact_s($first, 'date'), $tz);
        $by    = $date !== false && fact_s($first, 'date') > $today
            ? 'before ' . fact_day_label($now, $date, false)
            : 'before it starts';
        $out[] = fact_todo_item(
            'rooms', 4, 'wait', 'hourglass_top',
            $n === 1 ? fact_s($first, 'room') . ' is waiting for approval' : $n . ' room requests are waiting for approval',
            'An admin has to decide ' . $by . ', otherwise it is declined automatically · You can withdraw ' . ($n === 1 ? 'it' : 'them') . ' any time',
            'rr:' . fact_i($first, 'id'),
            fact_i($first, 'start_ts')
        );
    }

    // 5 · A room in use right now
    $live = array_values(array_filter($live, 'is_array'));
    if ($live) {
        $it = $live[0];
        $out[] = fact_todo_item(
            'rooms', 5, 'ok', 'meeting_room',
            fact_s($it, 'room') . ' is yours until ' . fact_time(fact_s($it, 'end')),
            'In use now · ' . fact_s($it, 'where'),
            'rr:' . fact_i($it, 'id'),
            fact_i($it, 'start_ts')
        );
    }

    // 5 · Slots the faculty member is waiting on
    $waitlist = array_values(array_filter($waitlist, 'is_array'));
    $waiting  = [];
    foreach ($waitlist as $w) {
        $waiting[fact_i($w, 'room_id') . '|' . fact_s($w, 'date') . '|' . fact_s($w, 'start') . '|' . fact_s($w, 'end')] = true;
    }
    if ($waitlist) {
        $w     = $waitlist[0];
        $n     = count($waitlist);
        $date  = DateTimeImmutable::createFromFormat('Y-m-d', fact_s($w, 'date'), $tz);
        $slot  = ($date !== false ? fact_day_label($now, $date) . ', ' : '') . fact_time(fact_s($w, 'start')) . ' – ' . fact_time(fact_s($w, 'end'));
        $out[] = fact_todo_item(
            'rooms', 5, 'info', 'notifications',
            $n === 1 ? 'On the waitlist for ' . fact_s($w, 'room') : 'On the waitlist for ' . $n . ' room slots',
            ($n === 1 ? $slot . ' · ' : '') . 'We will notify you if ' . ($n === 1 ? 'the slot' : 'one') . ' opens up',
            'rooms',
            fact_i($w, 'start_ts')
        );
    }

    // 5 · Declined reservations that are still ahead and not on the waitlist yet (History has "Join waitlist")
    $open = [];
    foreach ($history as $it) {
        if (!is_array($it) || fact_s($it, 'state') !== 'declined' || empty($it['future'])) {
            continue;
        }
        $key = fact_i($it, 'room_id') . '|' . fact_s($it, 'date') . '|' . fact_s($it, 'start') . '|' . fact_s($it, 'end');
        if (!isset($waiting[$key])) {
            $open[] = $it;
        }
    }
    if ($open) {
        usort($open, static fn(array $a, array $b): int => fact_i($a, 'start_ts') <=> fact_i($b, 'start_ts'));
        $it   = $open[0];
        $n    = count($open);
        $date = DateTimeImmutable::createFromFormat('Y-m-d', fact_s($it, 'date'), $tz);
        $out[] = fact_todo_item(
            'rooms', 5, 'info', 'event_busy',
            $n === 1
                ? fact_s($it, 'room') . ($date !== false ? ' on ' . fact_day_label($now, $date, false) : '') . ' was declined'
                : $n . ' room reservations were declined for dates still ahead',
            'Join the waitlist in History to be told if the slot opens up',
            'history-room',
            fact_i($it, 'start_ts')
        );
    }

    return $out;
}
