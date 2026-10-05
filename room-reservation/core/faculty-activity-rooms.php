<?php
/**
 * room-reservation/core/faculty-activity-rooms.php
 *
 * The room-reservation half of the faculty "My Activity" screen.
 *
 * One renderer, two callers, so the markup can never drift apart:
 *   - faculty-dashboard.php               first paint of the panel
 *   - api/get-activity-rooms.php          live refresh (after a cancel / new booking)
 *
 * What counts as what (all times Asia/Manila):
 *   live       Approved, already started, not finished yet   -> "In progress"
 *   upcoming   Approved, starts in the future
 *   history    Approved + finished (shown as "Completed"), Declined, Cancelled
 *   waitlist   slots the faculty member asked to be told about (future only)
 * Reservations a faculty member's students made through their room code
 * (submitted_as = student) are not the faculty member's own activity and are
 * left out here, exactly like the 2-room limit leaves them out.
 */

require_once __DIR__ . '/faculty-room-quota.php';

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
        'live' => [], 'upcoming' => [], 'waitlist' => [], 'history' => [],
        'active' => 0, 'max' => FCTY_MAX_ACTIVE_ROOM_RESERVATIONS,
    ];
    $tz  = new DateTimeZone('Asia/Manila');
    $now = new DateTimeImmutable('now', $tz);

    try {
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
                } else {
                    $item['state'] = $status === 'Cancelled' ? 'cancelled' : 'declined';
                    $out['history'][] = $item;
                }
            }
            $stmt->close();
        }

        // Soonest first for what is coming up
        usort($out['upcoming'], static fn (array $a, array $b): int => fact_i($a, 'start_ts') <=> fact_i($b, 'start_ts'));

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

    $out['active'] = count($out['live']) + count($out['upcoming']);
    return $out;
}

/** Small pill: tone is one of live | today | soon | ok | wait | bad | muted. */
function fact_chip(string $text, string $tone, string $icon = ''): string
{
    return '<span class="fact-chip is-' . fact_h($tone) . '">'
        . ($icon !== '' ? fact_icon($icon) : '') . fact_h($text) . '</span>';
}

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

/**
 * One reservation row for the "Room reservations" card.
 *
 * @param array<string, mixed> $it
 */
function fact_render_room_item(array $it, string $kind): string
{
    $state   = fact_s($it, 'state');
    $room    = fact_s($it, 'room');
    $times   = fact_time(fact_s($it, 'start')) . ' – ' . fact_time(fact_s($it, 'end'));
    $meta    = $times . ' · ' . fact_s($it, 'where');
    $classes = 'fact-item fact-room' . ($kind === 'live' ? ' is-live' : '') . ($kind === 'waitlist' ? ' is-waitlist' : '');

    $html  = '<article class="' . $classes . '" data-rr-id="' . fact_i($it, 'id') . '">';
    $html .= fact_date_tile(fact_s($it, 'date'));
    $html .= '<div class="fact-item-main"><div class="fact-item-top"><h4 class="fact-item-title">' . fact_h($room) . '</h4>';
    $html .= $kind === 'waitlist'
        ? fact_chip('Waitlisted', 'wait', 'notifications')
        : fact_when_chip($state, fact_i($it, 'days'));
    $html .= '</div><p class="fact-item-meta">' . fact_h($meta) . '</p>';

    if ($kind !== 'waitlist') {
        $sub = '<span>' . fact_h(fact_s($it, 'purpose')) . '</span>';
        if (!empty($it['letter'])) {
            $sub .= '<span class="fact-tag" title="A supporting letter is attached">' . fact_icon('attach_file') . 'Letter</span>';
        }
        $html .= '<p class="fact-item-sub">' . $sub . '</p>';
    } else {
        $html .= '<p class="fact-item-sub"><span>We\'ll notify you if this slot opens up.</span></p>';
    }
    $html .= '</div>';

    // Actions: only upcoming reservations can be cancelled, and not within 1 hour of the start
    if ($kind === 'upcoming') {
        $html .= '<div class="fact-item-actions">';
        if (!empty($it['can_cancel'])) {
            $html .= '<button type="button" class="fact-btn fact-btn-ghost is-danger" data-action="cancel-reservation"'
                . ' data-rr-id="' . fact_i($it, 'id') . '" data-room-name="' . fact_h($room) . '"'
                . ' title="Cancel this reservation">' . fact_icon('cancel') . 'Cancel</button>';
        } else {
            $html .= '<span class="fact-lock" title="Reservations can\'t be cancelled within 1 hour of the start time">'
                . fact_icon('lock') . '</span>';
        }
        $html .= '</div>';
    }
    return $html . '</article>';
}

/**
 * Body of the "Room reservations" card.
 *
 * @param array<string, mixed> $d result of fact_load_rooms()
 */
function fact_render_rooms_body(array $d): string
{
    $live     = is_array($d['live'] ?? null) ? $d['live'] : [];
    $upcoming = is_array($d['upcoming'] ?? null) ? $d['upcoming'] : [];
    $waitlist = is_array($d['waitlist'] ?? null) ? $d['waitlist'] : [];
    $active   = fact_i($d, 'active');
    $max      = max(1, fact_i($d, 'max'));
    $left     = max(0, $max - $active);

    // Limit meter: one pip per allowed room
    $pips = '';
    for ($i = 0; $i < $max; $i++) {
        $pips .= '<i class="fact-pip' . ($i < $active ? ' is-on' : '') . '"></i>';
    }
    $html  = '<div class="fact-quota' . ($left === 0 ? ' is-full' : '') . '">';
    $html .= '<div class="fact-quota-text"><p class="fact-quota-main"><strong>' . $active . ' of ' . $max . '</strong> rooms reserved</p>'
        . '<span>' . ($left === 0
            ? 'Limit reached — cancel one or wait until it ends to reserve another.'
            : $left . ' more ' . ($left === 1 ? 'room' : 'rooms') . ' available to reserve.') . '</span></div>';
    $html .= '<div class="fact-pips" aria-hidden="true">' . $pips . '</div></div>';

    if (!$live && !$upcoming && !$waitlist) {
        return $html . '<div class="fact-empty">' . fact_icon('event_available')
            . '<p class="fact-empty-title">No room reservations right now</p>'
            . '<p class="fact-empty-sub">Reserve a room for your class, lab session or meeting.</p>'
            . '<button type="button" class="fact-btn fact-btn-primary" data-action="go-tab" data-tab="rooms">'
            . fact_icon('meeting_room') . 'Reserve a room</button></div>';
    }

    $groups = [
        ['Happening now', 'live', $live],
        ['Upcoming', 'upcoming', $upcoming],
        ['On the waitlist', 'waitlist', $waitlist],
    ];
    foreach ($groups as [$label, $kind, $items]) {
        if (!$items) {
            continue;
        }
        $html .= '<div class="fact-group"><h3 class="fact-group-label">' . fact_h($label) . '</h3><div class="fact-list">';
        foreach ($items as $it) {
            if (is_array($it)) {
                /** @var array<string, mixed> $it */
                $html .= fact_render_room_item($it, $kind);
            }
        }
        $html .= '</div></div>';
    }
    return $html;
}

/**
 * History rows (<tr>) for rooms. Merged into the shared history table.
 *
 * @param array<string, mixed> $d result of fact_load_rooms()
 */
function fact_render_room_history_rows(array $d): string
{
    $items = is_array($d['history'] ?? null) ? $d['history'] : [];
    $html  = '';

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
        $state = fact_s($it, 'state');
        $map = [
            'completed' => ['Completed', 'muted', 'task_alt'],
            'declined'  => ['Declined', 'bad', 'cancel'],
            'cancelled' => ['Cancelled', 'muted', 'event_busy'],
        ];
        [$label, $tone, $glyph] = $map[$state] ?? $map['completed'];

        $dt    = DateTimeImmutable::createFromFormat('Y-m-d', fact_s($it, 'date'));
        $day   = $dt !== false ? $dt->format('M j, Y') : fact_s($it, 'date');
        $times = fact_time(fact_s($it, 'start')) . ' – ' . fact_time(fact_s($it, 'end'));

        $sub = fact_h(fact_s($it, 'purpose'));
        if (fact_s($it, 'reason') !== '' && $state !== 'completed') {
            $sub .= ' · ' . fact_h(fact_s($it, 'reason'));
        }
        $slot_key = fact_i($it, 'room_id') . '|' . fact_s($it, 'date') . '|' . fact_s($it, 'start') . '|' . fact_s($it, 'end');
        if ($state === 'declined' && !empty($it['future']) && isset($waiting[$slot_key])) {
            $sub .= ' <span class="fact-linkbtn is-done">' . fact_icon('notifications_active') . 'On waitlist</span>';
        } elseif ($state === 'declined' && !empty($it['future'])) {
            $sub .= ' <button type="button" class="fact-linkbtn" data-action="join-waitlist"'
                . ' data-room-id="' . fact_i($it, 'room_id') . '" data-room-name="' . fact_h(fact_s($it, 'room')) . '"'
                . ' data-res-date="' . fact_h(fact_s($it, 'date')) . '"'
                . ' data-start-time="' . fact_h(fact_s($it, 'start')) . '" data-end-time="' . fact_h(fact_s($it, 'end')) . '">'
                . fact_icon('notifications') . 'Join waitlist</button>';
        }

        $html .= '<tr class="fact-row" data-kind="room" data-status="' . fact_h($state) . '" data-ts="' . fact_i($it, 'start_ts') . '">'
            . '<td data-label="Activity"><div class="fact-cell"><span class="fact-tile">' . fact_icon('meeting_room') . '</span>'
            . '<div class="fact-cell-text"><span class="fact-kind">Room</span><p class="fact-row-title">' . fact_h(fact_s($it, 'room')) . '</p>'
            . '<p class="fact-row-sub">' . $sub . '</p></div></div></td>'
            . '<td data-label="Location">' . fact_h(fact_s($it, 'where')) . '</td>'
            . '<td data-label="When"><span class="fact-when">' . fact_h($day) . '</span><span class="fact-row-sub">' . fact_h($times) . '</span></td>'
            . '<td data-label="Status" class="fact-col-status">' . fact_chip($label, $tone, $glyph) . '</td>'
            . '</tr>';
    }
    return $html;
}

/**
 * Values for the two room tiles in the stat strip.
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
