<?php

/**
 * activity/core/faculty-data.php
 *
 * The EQUIPMENT half of the faculty "My Activity" screen. It mirrors the room half
 * (room-reservation/core/room-quota.php, the fact_* functions) and uses the
 * same helpers, status words and colours, so the two halves always look and read alike.
 *
 * One loader, one renderer, two callers, so the markup can never drift apart:
 *   - faculty-dashboard.php                       first paint of #panel-activity
 *   - api/poll-requests.php?view=activity         live refresh (admin returns an item,
 *                                                 a request is decided, an item goes overdue)
 *
 * RULES (all times Asia/Manila, same as the Home tab, the booking form and the notifications)
 *   Status lifecycle   Waiting (shown "Pending") -> Approved -> On Loan -> Overdue -> Returned
 *                                         \-> Declined
 *                      (On Loan = Claim QR scanned, item physically handed over)
 *   In progress        Overdue, or Approved whose start time has already arrived
 *                      (a booking for later today is NOT "in progress" until it starts,
 *                      exactly when BookingSchedule::activateDue() takes its stock)
 *   Upcoming           Waiting, or Approved that has not started yet
 *   History            Returned, Declined or Cancelled
 *   Due moment         return_date + return_time (end of that day when no time was set)
 *   Counts             Ready for pickup = Approved, Active loans = On Loan, Overdue = Overdue,
 *                      Pending = Waiting. These are the numbers the Home tab shows; this screen
 *                      no longer repeats them as cards (see "Next steps" below).
 *   Next steps         fact_eq_todo(): the short list at the top of the screen, built from the rules
 *                      above (overdue, due today, ready to collect, waiting for a decision).
 *   Overdue            blocks new BORROW requests (arbitration "overdue borrower" rule),
 *                      never room reservations.
 *   Cancel             offered on Upcoming requests that have not started (Pending, or Approved more than
 *                      1 hour before the start). Rules and side effects: core/borrow-cancellation.php.
 *                      A cancelled request moves to History as "Cancelled".
 *   Extend             offered on loans whose return moment has been reached (Overdue, or Approved and
 *                      due), up to BorrowExtension::maxExtensions() times per loan (Settings → Arbitration). Rules and availability
 *                      check: core/borrow-extension.php. A granted extension moves the return moment, puts an
 *                      Overdue loan back to Approved and shows as "Extended once / N times, originally due ..."
 *                      on the row. Lateness is judged against the ORIGINAL return moment, so extending never
 *                      makes a late loan look on time (history says "Returned late").
 *   Student requests   requests a student filed with this faculty member's code are the
 *                      faculty member's responsibility, so they are listed here (and count)
 *                      like everywhere else, and are marked with the student's name.
 *                      (Rooms are different: see faculty-room-quota.php.)
 */

require_once __DIR__ . '/../../equipment-booking/core/booking-schedule.php';
require_once __DIR__ . '/../../notifications/core/faculty-data.php';
require_once __DIR__ . '/../../equipment-booking/core/borrow-extension.php';
require_once __DIR__ . '/../../equipment-booking/core/borrow-cancellation.php';
require_once __DIR__ . '/../../room-reservation/core/room-quota.php';
require_once __DIR__ . '/../../shared/core/equipment-image.php';

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
 * Housekeeping that has to run before the numbers are read: undecided requests past
 * their deadline are declined and overdue / start-of-booking states are brought up to date. The dashboard
 * runs it on every page load; the live refresh runs it so a state change shows up
 * without reloading the page.
 */
function fact_eq_housekeeping(mysqli $conn): void
{
    // Undecided requests past their deadline are declined; missed same-day requests are decided
    require_once __DIR__ . '/../../equipment-booking/core/auto-approve-tick.php';
    equipment_auto_approve_tick($conn);
    // On Loan past the return time -> Overdue; uncollected bookings -> no-show; start-of-booking stock
    BookingSchedule::tick($conn);
}

/**
 * Picture per item name (tbl_requests only stores the name as text). An item with no photo, or whose
 * photo file is gone, gets the shared default picture (uploads/default.png) instead of an icon tile.
 */
function fact_eq_image_map(mysqli $conn): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [];
    $res = $conn->query('SELECT item_name, image_path FROM tbl_inventory');
    while ($res && ($row = $res->fetch_assoc())) {
        $map[strtolower(trim((string)$row['item_name']))] = equipment_image_path((string)($row['image_path'] ?? ''));
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
        'counts'   => ['pickup' => 0, 'active' => 0, 'overdue' => 0, 'pending' => 0],
    ];
    $tz     = new DateTimeZone('Asia/Manila');
    $now    = new DateTimeImmutable('now', $tz);
    $images = [];

    try {
        BookingSchedule::ensureSchema($conn);
        $extCol = BorrowExtension::ensureSchema($conn)
            ? 'r.extension_count, r.original_return_date, r.original_return_time'
            : '0 AS extension_count, NULL AS original_return_date, NULL AS original_return_time';
        $images = fact_eq_image_map($conn);

        $cols = "r.id, r.equipment_name, r.borrow_qty, r.room, r.room_id, r.batch_id, r.bundle_claim_token, r.borrow_date, r.borrow_time, $extCol,
                 r.return_date, r.return_time, r.returned_at, r.status, r.reason, r.arbitration_rule,
                 r.request_date, r.return_token, r.claim_token, r.submitted_as, r.submitted_by_name, r.submitted_by_id,
                 rm.room_name, b.building_key, b.name AS building_name,
                 COALESCE(rm.floor_label, CONCAT(rm.floor_number, 'F')) AS floor_label
            FROM tbl_requests r
            LEFT JOIN tbl_rooms     rm ON rm.room_id     = r.room_id
            LEFT JOIN tbl_buildings b  ON b.building_id  = rm.building_id
           WHERE r.faculty_id = ?";

        // Open requests are never cut off; history is capped (the table pages 10 at a time).
        $queries = [
            "SELECT $cols AND r.status IN ('Waiting','Approved','On Loan','Overdue') ORDER BY r.request_date DESC",
            "SELECT $cols AND r.status NOT IN ('Waiting','Approved','On Loan','Overdue') ORDER BY r.request_date DESC LIMIT 200",
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
                    'batch_id'    => fact_s($row, 'batch_id'),
                    'bundle_claim_token' => fact_s($row, 'bundle_claim_token'),
                    'where'       => fact_eq_where($row),
                    'status'      => $status,
                    'reason'      => trim(fact_s($row, 'reason')),
                    'rule'        => fact_s($row, 'arbitration_rule'),
                    'token'       => fact_s($row, 'return_token'),
                    'claim_token' => fact_s($row, 'claim_token'),
                    'student'     => fact_s($row, 'submitted_as') === 'student' ? trim(fact_s($row, 'submitted_by_name')) : '',
                    'student_id'  => fact_s($row, 'submitted_as') === 'student' ? trim(fact_s($row, 'submitted_by_id')) : '',
                    'image'       => $images[strtolower(trim($name))] ?? EQUIPMENT_DEFAULT_IMAGE,
                    'icon'        => actEquipIcon($name),
                    'start_date'  => $start->format('Y-m-d'),
                    'start_time'  => $borrowTime,
                    'due_date'    => $due->format('Y-m-d'),
                    'due_time'    => $returnTime,
                    'start_ts'    => $start->getTimestamp(),
                    'due_ts'      => $due->getTimestamp(),
                    'returned_ts' => $returnedAt !== '' ? (int)strtotime($returnedAt) : 0,
                    'extensions'  => max(0, fact_i($row, 'extension_count')),
                    // Return moment reached while the item is still out: it can be extended, until the limit
                    'can_extend'  => BorrowExtension::isDue($row, $now) && !BorrowExtension::atLimit($row),
                    'extend_limit' => BorrowExtension::isDue($row, $now) && BorrowExtension::atLimit($row),
                    // When it was really due, before any extension moved it (0 / '' when never extended)
                    'late_base_ts'   => $due->getTimestamp(),
                    'orig_due_label' => '',
                ];
                if (fact_s($row, 'original_return_date') !== '') {
                    try {
                        $origTime = fact_s($row, 'original_return_time');
                        $orig     = new DateTimeImmutable(fact_s($row, 'original_return_date') . ' ' . ($origTime !== '' ? $origTime : '23:59:59'), $tz);
                        $item['late_base_ts']   = $orig->getTimestamp();
                        $item['orig_due_label'] = fact_day_label($now, $orig, false) . ($origTime !== '' ? ', ' . fact_time($origTime) : '');
                    } catch (Throwable $e) {
                        // an unreadable original date just means we fall back to the current one
                    }
                }
                // Upcoming requests can be cancelled until 1 hour before they start (Pending: until they start).
                // 'cancel_note' says why not, only for the "too late" case (same wording as room reservations).
                $cancelWhy = in_array($status, ['Waiting', 'Approved'], true) ? BorrowCancellation::blocker($row, $now) : 'n/a';
                $item['can_cancel']  = $cancelWhy === null;
                $item['cancel_note'] = $cancelWhy !== null && str_contains($cancelWhy, 'Cancellations are not allowed within')
                    ? 'Can\'t be cancelled within ' . BorrowCancellation::cutoffLabel() . ' of the start' : '';

                switch ($status) {
                    case 'Overdue':
                        $out['counts']['overdue']++;
                        $out['live'][] = $item;
                        break;
                    case 'On Loan':
                        // Claimed — item physically out. Counts as active loan.
                        $out['counts']['active']++;
                        $out['live'][] = $item;
                        break;
                    case 'Approved':
                        // Approved but not yet physically collected — counts as "ready for pickup".
                        $out['counts']['pickup']++;
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

        $batchIds = [];
        $claimGroups = [];
        // returnGroups: batch_id => ['ids' => [], 'leader_token' => '']
        // Groups On Loan / Overdue items by batch_id so only the lowest-id row
        // shows a Return QR button. All items in the batch share that one QR.
        $returnGroups = [];
        foreach (['live', 'upcoming', 'history'] as $bucket) {
            foreach ($out[$bucket] as $item) {
                if ($item['batch_id'] !== '') {
                    $batchIds[$item['batch_id']] = true;
                }
                if ($item['bundle_claim_token'] !== '') {
                    $token = $item['bundle_claim_token'];
                    $claimGroups[$token]['ids'][] = (int)$item['id'];
                    $claimGroups[$token]['names'][$item['name']] = true;
                }
                // Group active (On Loan / Overdue) bundle items by batch_id
                // so we can designate one leader Return QR per bundle.
                $bid = $item['batch_id'];
                if ($bid !== '' && in_array($item['status'], ['On Loan', 'Overdue'], true) && $item['token'] !== '') {
                    if (!isset($returnGroups[$bid])) {
                        $returnGroups[$bid] = ['ids' => [], 'tokens' => []];
                    }
                    $returnGroups[$bid]['ids'][]    = (int)$item['id'];
                    $returnGroups[$bid]['tokens'][(int)$item['id']] = $item['token'];
                }
            }
        }
        // Compute leader (lowest id) per return group
        foreach ($returnGroups as &$rg) {
            $rg['leader_id']    = min($rg['ids']);
            $rg['leader_token'] = $rg['tokens'][$rg['leader_id']] ?? '';
        }
        unset($rg);

        $batchCounts = [];
        foreach (['live', 'upcoming', 'history'] as $bucket) {
            foreach ($out[$bucket] as $item) {
                if ($item['batch_id'] !== '') {
                    $batchCounts[$item['batch_id']] = ($batchCounts[$item['batch_id']] ?? 0) + 1;
                }
            }
        }
        if ($batchIds) {
            $batchList = implode(',', array_map(function (string $batchId) use ($conn): string {
                return "'" . $conn->real_escape_string($batchId) . "'";
            }, array_keys($batchIds)));
            $facultyIdSql = $conn->real_escape_string($faculty_id);
            $batchCountResult = $conn->query(
                "SELECT batch_id, COUNT(*) AS item_count
                   FROM tbl_requests
                  WHERE faculty_id = '{$facultyIdSql}' AND batch_id IN ({$batchList})
                  GROUP BY batch_id"
            );
            if ($batchCountResult) {
                while ($countRow = $batchCountResult->fetch_assoc()) {
                    $batchCounts[(string)$countRow['batch_id']] = (int)$countRow['item_count'];
                }
            } else {
                error_log('[PUPSync] fact_eq_load bundle count failed: ' . $conn->error);
            }
        }

        foreach ($claimGroups as &$claimGroup) {
            $claimGroup['leader_id'] = min($claimGroup['ids']);
            $claimGroup['count'] = count($claimGroup['ids']);
        }
        unset($claimGroup);

        foreach (['live', 'upcoming', 'history'] as $bucket) {
            foreach ($out[$bucket] as &$item) {
                $batchId = $item['batch_id'];
                $item['bundle_count'] = $batchId !== '' ? ($batchCounts[$batchId] ?? 0) : 0;
                $token = $item['bundle_claim_token'];
                $claimGroup = $token !== '' ? ($claimGroups[$token] ?? null) : null;
                $item['bundle_claim_leader'] = $claimGroup !== null
                    && (int)$item['id'] === $claimGroup['leader_id'];
                $item['bundle_claim_label'] = $claimGroup !== null
                    ? 'Bundle (' . $claimGroup['count'] . ' ' . ($claimGroup['count'] === 1 ? 'item' : 'items') . ')'
                    : '';
                // Return QR bundle grouping
                $rg = $batchId !== '' ? ($returnGroups[$batchId] ?? null) : null;
                $item['bundle_return_leader'] = $rg !== null && (int)$item['id'] === $rg['leader_id'];
                $item['bundle_return_token']  = $rg !== null ? $rg['leader_token'] : '';
            }
            unset($item);
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
 * The four equipment numbers (the Home tab's key figures). My Activity no longer draws them as
 * cards; fact_eq_render_status() still reads the overdue count, and the live-refresh endpoint
 * still returns them.
 *
 * @param array<string, mixed> $d result of fact_eq_load()
 * @return array{loans:int, overdue:int, pending:int}
 */
function fact_eq_stats(array $d): array
{
    $c = is_array($d['counts'] ?? null) ? $d['counts'] : [];
    return [
        'pickup'  => (int)($c['pickup'] ?? 0),
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

/**
 * "Extended once, originally due Oct 22, 5:00 PM" (empty when the loan was never extended).
 * The original date stays visible so an extension never hides that the loan was late.
 */
function fact_eq_extended_label(array $it): string
{
    $n = fact_i($it, 'extensions');
    if ($n <= 0) {
        return '';
    }
    $label = $n === 1 ? 'Extended once' : 'Extended ' . $n . ' times';
    return fact_s($it, 'orig_due_label') !== '' ? $label . ', originally due ' . fact_s($it, 'orig_due_label') : $label;
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

    $sub = [];
    if (fact_i($it, 'bundle_count') > 1) {
        $sub[] = 'Part of a bundle · ' . fact_i($it, 'bundle_count') . ' items';
    }
    $sub[] = fact_h(fact_s($it, 'where'));
    if (fact_s($it, 'student') !== '') {
        $student = 'Student: ' . fact_s($it, 'student');
        if (fact_s($it, 'student_id') !== '') {
            $student .= ' (' . fact_s($it, 'student_id') . ')';
        }
        $sub[] = fact_h($student);
    }
    if (fact_eq_extended_label($it) !== '') {
        $sub[] = fact_h(fact_eq_extended_label($it));
    }
    if ($status === 'Waiting' && fact_s($it, 'reason') !== '') {
        $sub[] = fact_h(fact_s($it, 'reason'));
    }
    // Why a button is greyed out is said here, in the detail line (and on hover over the button)
    if ($kind === 'upcoming' && empty($it['can_cancel']) && fact_s($it, 'cancel_note') !== '') {
        $sub[] = fact_h(fact_s($it, 'cancel_note'));
    }
    if ($kind === 'live' && !empty($it['extend_limit'])) {
        $sub[] = BorrowExtension::maxExtensions() === 0
            ? 'Extensions are turned off'
            : 'Extension limit reached (' . BorrowExtension::maxExtensions() . ' of ' . BorrowExtension::maxExtensions() . ')';
    }

    $isBundle = fact_i($it, 'bundle_count') > 1;
    $html  = '<article class="fact-item fact-loan' . ($isOverdue ? ' is-overdue' : '') . ($isBundle ? ' is-bundle' : '') . '" data-req-id="' . fact_i($it, 'id') . '">';
    $html .= fact_eq_tile($it, $rootUrl);
    $html .= '<div class="fact-item-main"><div class="fact-item-top"><h4 class="fact-item-title">' . fact_eq_title($it) . '</h4>'
        . fact_status_chip($status) . '</div>';
    $html .= '<p class="fact-item-meta">' . $when . '</p>';
    $html .= '<p class="fact-item-sub">' . implode(' · ', $sub) . '</p>';
    $html .= fact_render_actions(fact_eq_action_slots($it, $kind, $status));
    return $html . '</div></article>';
}

/**
 * The buttons an equipment row can have, each in its own slot (see "Where every button lives"
 * in core/room-quota.php):
 *   claim   Claim QR    the request is Approved and the item has not been handed over yet
 *   return  Return QR   the item is out (or about to be): shown while there is a return code
 *   extend  Extend      the return time has been reached (greyed out at the extension limit)
 *   end     Cancel      Upcoming requests only (greyed out within 1 hour of the start)
 *
 * @param array<string, mixed> $it
 * @return array{claim: string, return: string, extend: string, end: string}
 */
function fact_eq_action_slots(array $it, string $kind, string $status): array
{
    $slots = ['claim' => '', 'return' => '', 'extend' => '', 'end' => ''];

    // Claim QR: Approved and not yet physically collected (a bundle has one QR, on its first row)
    if ($status === 'Approved' && !empty($it['bundle_claim_leader']) && fact_s($it, 'bundle_claim_token') !== '') {
        $slots['claim'] = fact_action_btn('Claim QR', 'qr_code_2', [
            'action' => 'show-claim-qr', 'token' => fact_s($it, 'bundle_claim_token'),
            'bundle-claim' => 1, 'equipment' => fact_s($it, 'bundle_claim_label'),
        ], ['primary' => true, 'title' => 'Show the claim QR for the whole bundle', 'aria' => 'Claim bundle QR']);
    } elseif ($status === 'Approved' && fact_s($it, 'claim_token') !== '') {
        $slots['claim'] = fact_action_btn('Claim QR', 'qr_code_2', [
            'action' => 'show-claim-qr', 'token' => fact_s($it, 'claim_token'), 'equipment' => fact_s($it, 'name'),
        ], ['primary' => true, 'title' => 'Show the claim QR for this item']);
    }

    if ($kind === 'live') {
        // Return QR only when the item is physically out (On Loan or Overdue).
        // For bundle items: only the leader row shows the Return QR (one QR covers all
        // remaining On Loan / Overdue items in that bundle).
        // For solo items (no batch_id): each item shows its own Return QR as before.
        if (in_array($status, ['On Loan', 'Overdue'], true)) {
            $isBundleItem = fact_s($it, 'bundle_return_token') !== '';
            if ($isBundleItem && !empty($it['bundle_return_leader'])) {
                // Bundle leader: show one Return QR for the whole bundle
                $slots['return'] = fact_action_btn('Return QR', 'qr_code_scanner', [
                    'action'        => 'show-return-qr',
                    'token'         => fact_s($it, 'bundle_return_token'),
                    'equipment'     => 'Bundle (' . fact_i($it, 'bundle_count') . ' items)',
                    'bundle-return' => 1,
                ], ['primary' => true, 'title' => 'Show the return QR for this bundle']);
            } elseif (!$isBundleItem && fact_s($it, 'token') !== '') {
                // Solo item: show its own Return QR
                $slots['return'] = fact_action_btn('Return QR', 'qr_code_scanner', [
                    'action'    => 'show-return-qr',
                    'token'     => fact_s($it, 'token'),
                    'equipment' => fact_s($it, 'name'),
                ], ['primary' => true, 'title' => 'Show the return QR for this item']);
            }
            // Non-leader bundle items: no Return QR button (leader covers them)
        }

        if (!empty($it['can_extend'])) {
            $slots['extend'] = fact_action_btn('Extend', 'update', [
                'action' => 'extend-borrow', 'request-id' => fact_i($it, 'id'), 'equipment' => fact_s($it, 'name'),
            ], ['title' => 'Ask for more time']);
        } elseif (!empty($it['extend_limit'])) {
            $slots['extend'] = fact_action_btn('Extend', 'lock', [], [
                'disabled' => true, 'title' => 'This loan has been extended the most times allowed',
            ]);
        }
    }

    if ($kind === 'upcoming') {
        if (!empty($it['can_cancel'])) {
            $slots['end'] = fact_action_btn('Cancel', 'close', [
                'action' => 'cancel-borrow', 'request-id' => fact_i($it, 'id'), 'equipment' => fact_s($it, 'name'),
            ], ['danger' => true, 'title' => 'Cancel this request']);
        } elseif (fact_s($it, 'cancel_note') !== '') {
            $slots['end'] = fact_action_btn('Cancel', 'lock', [], [
                'danger' => true, 'disabled' => true,
                'title'  => 'Requests can\'t be cancelled within ' . BorrowCancellation::cutoffLabel() . ' of the start time',
            ]);
        }
    }
    return $slots;
}

/**
 * The line under the "Equipment" title: how many requests there are, or, when something is
 * overdue, why new borrowing is blocked (same rule and wording as the "Borrowing Blocked"
 * notice on the other screens). Always one line, always in the same place.
 *
 * @param array<string, mixed> $d result of fact_eq_load()
 */
function fact_eq_render_status(array $d): string
{
    $live     = is_array($d['live'] ?? null) ? count($d['live']) : 0;
    $upcoming = is_array($d['upcoming'] ?? null) ? count($d['upcoming']) : 0;

    if (fact_eq_stats($d)['overdue'] > 0) {
        return '<span class="fact-sub-alert" title="You have overdue equipment. Return it immediately before you can borrow again.">'
            . fact_icon('block') . '<span>Borrowing blocked &middot; return your overdue equipment first</span></span>';
    }
    if ($live + $upcoming === 0) {
        return 'Nothing borrowed right now';
    }
    return fact_h($live . ' in progress · ' . $upcoming . ' upcoming');
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
    $now      = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $html     = '';

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
            // Judged against when it was ORIGINALLY due: an extension never makes a late return look on time
            if (fact_i($it, 'returned_ts') > fact_i($it, 'late_base_ts')) {
                $note = 'Returned late';
            }
        } elseif ($status === 'Cancelled') {
            $note = fact_s($it, 'reason') !== '' ? fact_s($it, 'reason') : 'Cancelled';
        } elseif ($status === 'Declined') {
            $note = fact_s($it, 'reason');
            if ($note === '' && function_exists('fnotif_rule_label')) {
                $note = (string)(fnotif_rule_label(fact_s($it, 'rule')) ?? '');
            }
        }

        $sub = ['Equipment · ' . fact_h(fact_s($it, 'where'))];
        if (fact_i($it, 'bundle_count') > 1) {
            $sub[] = '<span class="fact-bundle-chip"><span class="material-symbols-outlined" aria-hidden="true">link</span>'
                . 'Bundle · ' . fact_i($it, 'bundle_count') . ' items</span>';
        }
        if (fact_s($it, 'student') !== '') {
            $student = 'Student: ' . fact_s($it, 'student');
            if (fact_s($it, 'student_id') !== '') {
                $student .= ' (' . fact_s($it, 'student_id') . ')';
            }
            $sub[0] .= ' · ' . fact_h($student);
        }
        if (fact_eq_extended_label($it) !== '') {
            $sub[0] .= ' · ' . fact_h(fact_eq_extended_label($it));
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
            . '<td data-label="Action" class="fact-col-action"></td>'
            . '</tr>';
    }
    return $html;
}

/**
 * The equipment half of the "Next steps" list at the top of My Activity (the list itself, its
 * rules and the room half are described in core/room-quota.php).
 *
 * Rows that belong together are one entry, not several: a bundle is one entry (it has one Claim QR /
 * one Return QR), and all requests still waiting are one entry. Each entry points at the row that
 * carries the real button (the bundle's first row, same as the QR buttons).
 *
 * @param array<string, mixed> $d result of fact_eq_load()
 * @return list<array<string, mixed>>
 */
function fact_eq_todo(array $d): array
{
    $tz     = new DateTimeZone('Asia/Manila');
    $now    = new DateTimeImmutable('now', $tz);
    $nowTs  = $now->getTimestamp();
    $today  = $now->format('Y-m-d');
    $open   = array_merge(
        is_array($d['live'] ?? null) ? $d['live'] : [],
        is_array($d['upcoming'] ?? null) ? $d['upcoming'] : []
    );

    $overdue = $due = $collect = $pending = [];
    foreach ($open as $it) {
        if (!is_array($it)) {
            continue;
        }
        /** @var array<string, mixed> $it */
        $status  = fact_s($it, 'status');
        $batch   = fact_s($it, 'batch_id');
        $bundle  = fact_i($it, 'bundle_count') > 1 && $batch !== '';
        $retKey  = $bundle ? 'b' . $batch : 'i' . fact_i($it, 'id');
        $claim   = fact_s($it, 'bundle_claim_token');
        $claimKey = $claim !== '' ? 'c' . $claim : 'i' . fact_i($it, 'id');

        if ($status === 'Overdue') {
            $overdue[$retKey][] = $it;
        } elseif ($status === 'On Loan' && fact_s($it, 'due_date') <= $today) {
            $due[$retKey][] = $it;
        } elseif ($status === 'Approved' && fact_i($it, 'start_ts') - $nowTs <= 86400) {
            $collect[$claimKey][] = $it;
        } elseif ($status === 'Waiting') {
            $pending[$bundle ? 'b' . $batch : 'i' . fact_i($it, 'id')][] = $it;
        }
    }

    /** The row that carries the group's QR button: the bundle leader when there is one, else the first. */
    $leader = static function (array $rows, string $flag): int {
        foreach ($rows as $r) {
            if (!empty($r[$flag])) {
                return fact_i($r, 'id');
            }
        }
        return fact_i($rows[0], 'id');
    };
    $names = static fn(array $rows): string => fact_todo_names(array_column($rows, 'name'));

    $out = [];

    // 1 · Overdue: the one thing that blocks new borrowing
    foreach ($overdue as $rows) {
        $late    = max(array_map(static fn(array $r): int => $nowTs - fact_i($r, 'due_ts'), $rows));
        $canExt  = (bool)array_filter($rows, static fn(array $r): bool => !empty($r['can_extend']));
        $atLimit = !$canExt && (bool)array_filter($rows, static fn(array $r): bool => !empty($r['extend_limit']));
        $text    = [fact_eq_late_label($late), 'Still out · return it to the admin'];
        if ($canExt) {
            $text[] = 'You can also ask for more time';
        } elseif ($atLimit) {
            $text[] = 'Extension limit reached';
        }
        $out[] = fact_todo_item(
            'equipment', 1, 'bad', 'schedule',
            'Return ' . $names($rows) . ' now',
            implode(' · ', $text),
            'eq:' . $leader($rows, 'bundle_return_leader'),
            min(array_map(static fn(array $r): int => fact_i($r, 'due_ts'), $rows))
        );
    }

    // 2 · On loan and due back today
    foreach ($due as $rows) {
        $dueTs   = min(array_map(static fn(array $r): int => fact_i($r, 'due_ts'), $rows));
        $dueTime = fact_s($rows[0], 'due_time');
        foreach ($rows as $r) {
            if (fact_i($r, 'due_ts') === $dueTs) {
                $dueTime = fact_s($r, 'due_time');
            }
        }
        $out[] = fact_todo_item(
            'equipment', 2, 'wait', 'assignment_return',
            'Return ' . $names($rows) . ($dueTime !== '' ? ' by ' . fact_time($dueTime) : ' today'),
            'Due today · Show your Return QR to the admin when you hand it back',
            'eq:' . $leader($rows, 'bundle_return_leader'),
            $dueTs
        );
    }

    // 3 · Approved, not collected yet (already started, or starting within a day)
    foreach ($collect as $rows) {
        $startTs  = min(array_map(static fn(array $r): int => fact_i($r, 'start_ts'), $rows));
        $started  = $startTs <= $nowTs;
        $when     = '';
        if (!$started) {
            $first = $rows[0];
            foreach ($rows as $r) {
                if (fact_i($r, 'start_ts') === $startTs) {
                    $first = $r;
                }
            }
            $day  = fact_day_label($now, new DateTimeImmutable(fact_s($first, 'start_date'), $tz));
            $day  = in_array($day, ['Today', 'Tomorrow'], true) ? strtolower($day) : $day;
            $when = 'starts ' . $day . (fact_s($first, 'start_time') !== '' ? ', ' . fact_time(fact_s($first, 'start_time')) : '');
        }
        $out[] = fact_todo_item(
            'equipment', 3, 'ok', 'qr_code_2',
            'Collect ' . $names($rows),
            'Approved' . ($started ? ' and ready' : ' · ' . $when) . ' · Show your Claim QR to the admin',
            'eq:' . $leader($rows, 'bundle_claim_leader'),
            $startTs
        );
    }

    // 4 · Still waiting for a decision (one entry for all of them)
    if ($pending) {
        $groups = array_values($pending);
        usort($groups, static fn(array $a, array $b): int => fact_i($a[0], 'start_ts') <=> fact_i($b[0], 'start_ts'));
        $n      = count($groups);
        $firstR = $groups[0][0];
        // Real rule (core/request-decision.php): a later-date request waits for an admin and is declined
        // automatically at 12:01 AM on its borrow date if nobody decides.
        $startD = DateTimeImmutable::createFromFormat('Y-m-d', fact_s($firstR, 'start_date'), $tz);
        $by     = $startD !== false && fact_s($firstR, 'start_date') > $today
            ? 'before ' . fact_day_label($now, $startD, false)
            : 'before it starts';
        $out[]  = fact_todo_item(
            'equipment', 4, 'wait', 'hourglass_top',
            $n === 1 ? $names($groups[0]) . ' is waiting for review' : $n . ' equipment requests are waiting for review',
            'An admin has to decide ' . $by . ', otherwise ' . ($n === 1 ? 'it is' : 'they are') . ' declined automatically · You can cancel ' . ($n === 1 ? 'it' : 'them') . ' until ' . ($n === 1 ? 'it starts' : 'they start'),
            'eq:' . fact_i($firstR, 'id'),
            fact_i($firstR, 'start_ts')
        );
    }

    return $out;
}
