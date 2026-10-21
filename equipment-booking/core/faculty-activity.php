<?php

/**
 * equipment-booking/core/faculty-activity.php
 *
 * The EQUIPMENT half of the faculty "My Activity" screen. It mirrors the room half
 * (room-reservation/core/faculty-room-quota.php, the fact_* functions) and uses the
 * same helpers, status words and colours, so the two halves always look and read alike.
 *
 * One loader, one renderer, two callers, so the markup can never drift apart:
 *   - faculty-dashboard.php                       first paint of #panel-activity
 *   - api/poll-requests.php?view=activity         live refresh (admin returns an item,
 *                                                 a request is decided, an item goes overdue)
 *
 * RULES (all times Asia/Manila, same as the Home tab, the booking form and the notifications)
 *   Status lifecycle   Waiting (shown "Pending") -> Approved -> Overdue -> Returned
 *                                         \-> Declined
 *   In progress        Overdue, or Approved whose start time has already arrived
 *                      (a booking for later today is NOT "in progress" until it starts,
 *                      exactly when BookingSchedule::activateDue() takes its stock)
 *   Upcoming           Waiting, or Approved that has not started yet
 *   History            Returned or Declined
 *   Due moment         return_date + return_time (end of that day when no time was set)
 *   Counts             Active loans = Approved, Overdue = Overdue, Pending = Waiting.
 *                      These are the same three numbers the Home tab shows.
 *   Overdue            blocks new BORROW requests (arbitration "overdue borrower" rule),
 *                      never room reservations.
 *   Student requests   requests a student filed with this faculty member's code are the
 *                      faculty member's responsibility, so they are listed here (and count)
 *                      like everywhere else, and are marked with the student's name.
 *                      (Rooms are different: see faculty-room-quota.php.)
 */

require_once __DIR__ . '/booking-schedule.php';
require_once __DIR__ . '/faculty-notif-functions.php';
require_once __DIR__ . '/../../room-reservation/core/faculty-room-quota.php';

if (!defined('FACT_EQ_REASON_EXPIRED')) {
    define('FACT_EQ_REASON_EXPIRED', 'Request expired – borrow date has already passed');
}

/** Same icon fallback the Home tab uses when an item has no catalogue photo. */
if (!function_exists('actEquipIcon')) {
    function actEquipIcon(string $name): string
    {
        $n = strtolower($name);
        if (str_contains($n, 'projector'))                    return 'videocam';
        if (str_contains($n, 'remote') || str_contains($n, ' ac ') || $n === 'ac') return 'settings_remote';
        if (str_contains($n, 'cord') || str_contains($n, 'extension')) return 'power';
        if (str_contains($n, 'laptop') || str_contains($n, 'computer')) return 'laptop';
        if (str_contains($n, 'camera'))                       return 'photo_camera';
        if (str_contains($n, 'speaker') || str_contains($n, 'audio'))   return 'speaker';
        if (str_contains($n, 'mic'))                          return 'mic';
        if (str_contains($n, 'monitor') || str_contains($n, 'screen'))  return 'monitor';
        if (str_contains($n, 'tablet') || str_contains($n, 'ipad'))     return 'tablet';
        if (str_contains($n, 'printer'))                      return 'print';
        return 'inventory_2';
    }
}

/**
 * Housekeeping that has to run before the numbers are read: expired requests are
 * declined and overdue / start-of-booking states are brought up to date. The dashboard
 * runs it on every page load; the live refresh runs it so a state change shows up
 * without reloading the page.
 */
function fact_eq_housekeeping(mysqli $conn): void
{
    $today  = date('Y-m-d');
    $reason = FACT_EQ_REASON_EXPIRED;
    $stmt   = $conn->prepare("UPDATE tbl_requests SET status='Declined', reason=? WHERE status='Waiting' AND borrow_date < ?");
    if ($stmt) {
        $stmt->bind_param('ss', $reason, $today);
        $stmt->execute();
        $stmt->close();
    }
    BookingSchedule::tick($conn);
}

/** Catalogue photo per item name (tbl_requests only stores the name as text). */
function fact_eq_image_map(mysqli $conn): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [];
    $res = $conn->query('SELECT item_name, image_path FROM tbl_inventory');
    while ($res && ($row = $res->fetch_assoc())) {
        $path = (string)($row['image_path'] ?? '');
        if ($path !== '' && $path !== 'uploads/default.png') {
            $map[strtolower(trim((string)$row['item_name']))] = $path;
        }
    }
    return $map;
}

/**
 * Where the equipment is going. Built from the live room record, so it uses the same
 * building names as the Facilities tab and the room reservations; older requests only
 * have the text that was typed, which is shown as it is.
 *
 * @param array<string, mixed> $row
 */
function fact_eq_where(array $row): string
{
    $name = fact_s($row, 'room_name');
    if ($name !== '') {
        return $name . ' · ' . fact_building_label(fact_s($row, 'building_key'), fact_s($row, 'building_name'))
            . ' · ' . fact_s($row, 'floor_label');
    }
    $raw = trim(fact_s($row, 'room'));
    return $raw !== '' ? $raw : '—';
}

/**
 * Everything the equipment half of My Activity needs.
 *
 * @return array{
 *   live: list<array<string, mixed>>,
 *   upcoming: list<array<string, mixed>>,
 *   history: list<array<string, mixed>>,
 *   counts: array{active:int, overdue:int, pending:int}
 * }
 */
function fact_eq_load(mysqli $conn, string $faculty_id): array
{
    $out = [
        'live'     => [],
        'upcoming' => [],
        'history'  => [],
        'counts'   => ['active' => 0, 'overdue' => 0, 'pending' => 0],
    ];
    $tz     = new DateTimeZone('Asia/Manila');
    $now    = new DateTimeImmutable('now', $tz);
    $images = [];

    try {
        BookingSchedule::ensureSchema($conn);
        $images = fact_eq_image_map($conn);

        $cols = "r.id, r.equipment_name, r.borrow_qty, r.room, r.room_id, r.borrow_date, r.borrow_time,
                 r.return_date, r.return_time, r.returned_at, r.status, r.reason, r.arbitration_rule,
                 r.request_date, r.return_token, r.submitted_as, r.submitted_by_name,
                 rm.room_name, b.building_key, b.name AS building_name,
                 COALESCE(rm.floor_label, CONCAT(rm.floor_number, 'F')) AS floor_label
            FROM tbl_requests r
            LEFT JOIN tbl_rooms     rm ON rm.room_id     = r.room_id
            LEFT JOIN tbl_buildings b  ON b.building_id  = rm.building_id
           WHERE r.faculty_id = ?";

        // Open requests are never cut off; history is capped (the table pages 10 at a time).
        $queries = [
            "SELECT $cols AND r.status IN ('Waiting','Approved','Overdue') ORDER BY r.request_date DESC",
            "SELECT $cols AND r.status NOT IN ('Waiting','Approved','Overdue') ORDER BY r.request_date DESC LIMIT 200",
        ];

        foreach ($queries as $sql) {
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                error_log('[PUPSync] fact_eq_load prepare failed: ' . $conn->error);
                continue;
            }
            $stmt->bind_param('s', $faculty_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res !== false && ($row = $res->fetch_assoc())) {
                try {
                    $borrowTime = fact_s($row, 'borrow_time');
                    $returnTime = fact_s($row, 'return_time');
                    $start = new DateTimeImmutable(fact_s($row, 'borrow_date') . ' ' . ($borrowTime !== '' ? $borrowTime : '00:00:00'), $tz);
                    $due   = new DateTimeImmutable(fact_s($row, 'return_date') . ' ' . ($returnTime !== '' ? $returnTime : '23:59:59'), $tz);
                } catch (Throwable $e) {
                    continue;   // a row with an unreadable date is skipped, never fatal
                }

                $name   = fact_s($row, 'equipment_name');
                $status = trim(fact_s($row, 'status'));
                $returnedAt = fact_s($row, 'returned_at');
                $item = [
                    'id'          => fact_i($row, 'id'),
                    'name'        => $name,
                    'qty'         => max(1, fact_i($row, 'borrow_qty')),
                    'where'       => fact_eq_where($row),
                    'status'      => $status,
                    'reason'      => trim(fact_s($row, 'reason')),
                    'rule'        => fact_s($row, 'arbitration_rule'),
                    'token'       => fact_s($row, 'return_token'),
                    'student'     => fact_s($row, 'submitted_as') === 'student' ? trim(fact_s($row, 'submitted_by_name')) : '',
                    'image'       => $images[strtolower(trim($name))] ?? '',
                    'icon'        => actEquipIcon($name),
                    'start_date'  => $start->format('Y-m-d'),
                    'start_time'  => $borrowTime,
                    'due_date'    => $due->format('Y-m-d'),
                    'due_time'    => $returnTime,
                    'start_ts'    => $start->getTimestamp(),
                    'due_ts'      => $due->getTimestamp(),
                    'returned_ts' => $returnedAt !== '' ? (int)strtotime($returnedAt) : 0,
                ];

                switch ($status) {
                    case 'Overdue':
                        $out['counts']['overdue']++;
                        $out['live'][] = $item;
                        break;
                    case 'Approved':
                        $out['counts']['active']++;
                        if ($start <= $now) {
                            $out['live'][] = $item;
                        } else {
                            $out['upcoming'][] = $item;
                        }
                        break;
                    case 'Waiting':
                        $out['counts']['pending']++;
                        $out['upcoming'][] = $item;
                        break;
                    default:                       // Returned, Declined
                        $out['history'][] = $item;
                }
            }
            $stmt->close();
        }

        // Overdue first (longest overdue on top), then whatever is due soonest
        usort($out['live'], static function (array $a, array $b): int {
            $ao = $a['status'] === 'Overdue' ? 0 : 1;
            $bo = $b['status'] === 'Overdue' ? 0 : 1;
            return $ao <=> $bo ?: (int)$a['due_ts'] <=> (int)$b['due_ts'];
        });
        // Soonest start first
        usort($out['upcoming'], static fn(array $a, array $b): int => (int)$a['start_ts'] <=> (int)$b['start_ts']);
    } catch (Throwable $e) {
        // The rest of the dashboard must keep working even if this block cannot load.
        error_log('[PUPSync] fact_eq_load failed: ' . $e->getMessage());
    }

    return $out;
}

/**
 * Numbers for the summary strip.
 *
 * @param array<string, mixed> $d result of fact_eq_load()
 * @return array{loans:int, overdue:int, pending:int}
 */
function fact_eq_stats(array $d): array
{
    $c = is_array($d['counts'] ?? null) ? $d['counts'] : [];
    return [
        'loans'   => (int)($c['active'] ?? 0),
        'overdue' => (int)($c['overdue'] ?? 0),
        'pending' => (int)($c['pending'] ?? 0),
    ];
}

/** "Overdue by 3 hours" / "Overdue by 2 days" */
function fact_eq_late_label(int $seconds): string
{
    $minutes = intdiv(max(0, $seconds), 60);
    if ($minutes < 60) {
        $n = max(1, $minutes);
        return 'Overdue by ' . $n . ' ' . ($n === 1 ? 'minute' : 'minutes');
    }
    $hours = intdiv($minutes, 60);
    if ($hours < 24) {
        return 'Overdue by ' . $hours . ' ' . ($hours === 1 ? 'hour' : 'hours');
    }
    $days = intdiv($hours, 24);
    return 'Overdue by ' . $days . ' ' . ($days === 1 ? 'day' : 'days');
}

/** Item name, plus "×2" when more than one unit was requested (same as the Home tab). */
function fact_eq_title(array $it): string
{
    $qty = fact_i($it, 'qty');
    return fact_h(fact_s($it, 'name')) . ($qty > 1 ? ' <span class="fact-qty">×' . $qty . '</span>' : '');
}

/** Catalogue photo when there is one, otherwise the icon. */
function fact_eq_tile(array $it, string $rootUrl): string
{
    $image = fact_s($it, 'image');
    $inner = $image !== ''
        ? '<img src="' . fact_h($rootUrl . ltrim($image, '/')) . '" alt="">'
        : fact_icon(fact_s($it, 'icon'));
    return '<span class="fact-tile">' . $inner . '</span>';
}

/**
 * One equipment row for the "Equipment" card.
 * Same anatomy as a room row: title + status pill, when, where, actions.
 *
 * @param array<string, mixed> $it
 */
function fact_eq_render_item(array $it, string $kind, string $rootUrl, DateTimeImmutable $now): string
{
    $tz        = $now->getTimezone();
    $status    = fact_s($it, 'status');
    $isOverdue = $status === 'Overdue';
    $start     = new DateTimeImmutable(fact_s($it, 'start_date'), $tz);
    $due       = new DateTimeImmutable(fact_s($it, 'due_date'), $tz);

    // The "when" line: what the person most needs to know about this row
    if ($kind === 'live') {
        $dueAbs = fact_day_label($now, $due, false)
            . (fact_s($it, 'due_time') !== '' ? ', ' . fact_time(fact_s($it, 'due_time')) : '');
        if ($isOverdue) {
            $when = '<span class="fact-late">' . fact_h(fact_eq_late_label($now->getTimestamp() - fact_i($it, 'due_ts'))) . '</span>'
                . ' · Due ' . fact_h($dueAbs);
        } else {
            $day = fact_day_label($now, $due);
            $day = in_array($day, ['Today', 'Tomorrow'], true) ? strtolower($day) : $day;
            $when = 'Due ' . fact_h($day . (fact_s($it, 'due_time') !== '' ? ', ' . fact_time(fact_s($it, 'due_time')) : ''));
        }
    } else {
        $when = fact_h(fact_range_label($now, $start, fact_s($it, 'start_time'), $due, fact_s($it, 'due_time')));
    }

    $sub = [fact_h(fact_s($it, 'where'))];
    if (fact_s($it, 'student') !== '') {
        $sub[] = 'Student: ' . fact_h(fact_s($it, 'student'));
    }
    if ($status === 'Waiting' && fact_s($it, 'reason') !== '') {
        $sub[] = fact_h(fact_s($it, 'reason'));
    }

    $html  = '<article class="fact-item fact-loan' . ($isOverdue ? ' is-overdue' : '') . '" data-req-id="' . fact_i($it, 'id') . '">';
    $html .= fact_eq_tile($it, $rootUrl);
    $html .= '<div class="fact-item-main"><div class="fact-item-top"><h4 class="fact-item-title">' . fact_eq_title($it) . '</h4>'
        . fact_status_chip($status) . '</div>';
    $html .= '<p class="fact-item-meta">' . $when . '</p>';
    $html .= '<p class="fact-item-sub">' . implode(' · ', $sub) . '</p>';

    // Return QR is the only action: the admin scans it to confirm the return.
    // (The old "Report issue" button is gone: the AI chat is now the Room Assistant on the
    // Facilities tab and has no equipment flow. Damaged or lost items go to the Admin Office.)
    if ($kind === 'live' && fact_s($it, 'token') !== '') {
        $html .= '<div class="fact-item-actions"><button type="button" class="fact-btn fact-btn-ghost" data-action="show-return-qr"'
            . ' data-token="' . fact_h(fact_s($it, 'token')) . '"'
            . ' data-equipment="' . fact_h(fact_s($it, 'name')) . '">Return QR</button></div>';
    }
    return $html . '</div></article>';
}

/**
 * Body of the "Equipment" card.
 *
 * @param array<string, mixed> $d result of fact_eq_load()
 */
function fact_eq_render_body(array $d, string $rootUrl): string
{
    $live     = is_array($d['live'] ?? null) ? $d['live'] : [];
    $upcoming = is_array($d['upcoming'] ?? null) ? $d['upcoming'] : [];
    $overdue  = fact_eq_stats($d)['overdue'];
    $now      = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $html     = '';

    if ($overdue > 0) {
        // Same rule and wording as the "Borrowing Blocked" notice on the other screens
        $html .= '<div class="fact-notice" role="status">' . fact_icon('block')
            . '<div><strong>Borrowing blocked</strong><span>You have overdue equipment. Return it immediately before you can borrow again.</span></div></div>';
    }

    if (!$live && !$upcoming) {
        return $html . fact_render_empty('inventory_2', 'No equipment right now', 'Borrow equipment for your class, lab session or event.');
    }

    foreach ([['In progress', 'live', $live], ['Upcoming', 'upcoming', $upcoming]] as [$label, $kind, $items]) {
        $rows = [];
        foreach ($items as $it) {
            if (is_array($it)) {
                /** @var array<string, mixed> $it */
                $rows[] = fact_eq_render_item($it, $kind, $rootUrl, $now);
            }
        }
        $html .= fact_render_group($label, $rows);
    }
    return $html;
}

/**
 * History rows (<tr>) for equipment. Merged into the shared history table
 * (Activity | When | Status).
 *
 * @param array<string, mixed> $d result of fact_eq_load()
 */
function fact_eq_render_history_rows(array $d, string $rootUrl): string
{
    $items = is_array($d['history'] ?? null) ? $d['history'] : [];
    $tz    = new DateTimeZone('Asia/Manila');
    $now   = new DateTimeImmutable('now', $tz);
    $html  = '';

    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        /** @var array<string, mixed> $it */
        $status = fact_s($it, 'status');
        $start  = new DateTimeImmutable(fact_s($it, 'start_date'), $tz);
        $due    = new DateTimeImmutable(fact_s($it, 'due_date'), $tz);
        $range  = fact_range_label($now, $start, fact_s($it, 'start_time'), $due, fact_s($it, 'due_time'), false);

        // Second line under the date: when it actually came back, and whether that was on time
        $when2 = '';
        $note  = '';
        if ($status === 'Returned' && fact_i($it, 'returned_ts') > 0) {
            $ret   = (new DateTimeImmutable('@' . fact_i($it, 'returned_ts')))->setTimezone($tz);
            $when2 = 'Returned ' . fact_day_label($now, $ret, false) . ', ' . $ret->format('g:i A');
            if (fact_i($it, 'returned_ts') > fact_i($it, 'due_ts')) {
                $note = 'Returned late';
            }
        } elseif ($status === 'Declined') {
            $note = fact_s($it, 'reason');
            if ($note === '' && function_exists('fnotif_rule_label')) {
                $note = (string)(fnotif_rule_label(fact_s($it, 'rule')) ?? '');
            }
        }

        $sub = ['Equipment · ' . fact_h(fact_s($it, 'where'))];
        if (fact_s($it, 'student') !== '') {
            $sub[0] .= ' · Student: ' . fact_h(fact_s($it, 'student'));
        }

        $html .= '<tr class="fact-row" data-kind="equipment" data-status="' . fact_h(strtolower($status)) . '" data-ts="' . fact_i($it, 'start_ts') . '">'
            . '<td data-label="Activity"><div class="fact-cell">' . fact_eq_tile($it, $rootUrl)
            . '<div class="fact-cell-text"><p class="fact-row-title">' . fact_eq_title($it) . '</p>'
            . '<p class="fact-row-sub">' . $sub[0] . '</p>'
            . ($note !== '' ? '<p class="fact-row-sub">' . fact_h($note) . '</p>' : '')
            . '</div></div></td>'
            . '<td data-label="When"><span class="fact-when">' . fact_h($range) . '</span>'
            . ($when2 !== '' ? '<span class="fact-row-sub">' . fact_h($when2) . '</span>' : '') . '</td>'
            . '<td data-label="Status" class="fact-col-status">' . fact_status_chip($status) . '</td>'
            . '</tr>';
    }
    return $html;
}
