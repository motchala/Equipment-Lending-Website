<?php
// ================= DASHBOARD TAB (home screen) =================
// Data + markup for the Dashboard tab. Read-only: nothing here writes to the database
// (the overdue / auto-decline housekeeping stays in admin-functions.php and
// poll-requests-data.php).
//   dash_collect($conn)  -> every number / list the dashboard shows
//   dash_render($d)      -> echoes the stat cards + panels
// Used twice so first paint and live refresh can never disagree:
//   • the Dashboard panel further down this file
//   • "admin-dashboard.php?fragment=dashboard" (handled right after the functions), which
//     returns the same markup; admin-dashboard.js swaps it into #dashLive every 15 s.
// Every query is wrapped, so a missing table shows zeros instead of breaking the page.

if (!function_exists('dash_h')) {
    function dash_h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/** Run a SELECT and return all rows ([] on any failure). */
if (!function_exists('dash_rows')) {
    function dash_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
    {
        try {
            if ($types === '') {
                $res = $conn->query($sql);
            } else {
                $st = $conn->prepare($sql);
                $st->bind_param($types, ...$params);
                $st->execute();
                $res = $st->get_result();
            }
            $rows = [];
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $rows[] = $r;
                }
            }
            return $rows;
        } catch (Throwable $e) {
            error_log('[PUPSync] dashboard query failed: ' . $e->getMessage());
            return [];
        }
    }
}

/** First column of the first row as an int (0 on failure). */
if (!function_exists('dash_scalar')) {
    function dash_scalar(mysqli $conn, string $sql, string $types = '', array $params = []): int
    {
        $rows = dash_rows($conn, $sql, $types, $params);
        return $rows ? (int)array_values($rows[0])[0] : 0;
    }
}

if (!function_exists('dash_pl')) {
    /** "1 request" / "3 requests" */
    function dash_pl(int $n, string $word, ?string $plural = null): string
    {
        return $n . ' ' . ($n === 1 ? $word : ($plural ?? $word . 's'));
    }
}

/**
 * "just now", "12 min ago", "3 h ago", "Yesterday", "Oct 8".
 * $ageSeconds comes from the database (TIMESTAMPDIFF(SECOND, col, NOW())) so it
 * is right whatever timezone the DB server runs in; $datetime is only used to
 * print the calendar date once something is older than two days.
 */
if (!function_exists('dash_ago')) {
    function dash_ago($ageSeconds, ?string $datetime = null): string
    {
        if ($ageSeconds === null || $ageSeconds === '') {
            return $datetime ? date('M j', strtotime($datetime)) : '';
        }
        $diff = (int)$ageSeconds;
        if ($diff < 60)      return 'just now';
        if ($diff < 3600)    return floor($diff / 60) . ' min ago';
        if ($diff < 86400)   return floor($diff / 3600) . ' h ago';
        if ($diff < 172800)  return 'Yesterday';
        return $datetime ? date('M j', strtotime($datetime)) : floor($diff / 86400) . ' d ago';
    }
}

/** Admin-facing wording for the reason code stored on a declined request. */
if (!function_exists('dash_decline_label')) {
    function dash_decline_label(?string $rule): string
    {
        $rule = trim((string)$rule);
        if ($rule === '' || $rule === 'other') return 'Other / unspecified';
        static $map = [
            'rule_out_of_stock'          => 'Out of stock',
            'rule_archived'              => 'Item no longer available',
            'rule_overdue_block'         => 'Borrower has an overdue item',
            'rule_overdue_block_enabled' => 'Borrower has an overdue item',
            'rule_duplicate_block'       => 'Duplicate request',
            'rule_duplicate_block_enabled' => 'Duplicate request',
            'rule_missing_doc_hold'      => 'Supporting document missing',
            'rule_past_datetime'         => 'Date/time already passed',
            'rule_expired_unreviewed'    => 'Not reviewed in time',
            'rule_schedule_conflict'     => 'Schedule conflict',
            'override'                   => 'Declined manually by an admin',
        ];
        if (isset($map[$rule])) return $map[$rule];
        // rule_1_fifo … rule_5_id_order = the request lost a priority contest
        if (preg_match('/^rule_[1-5]_/', $rule)) return 'Lost to a higher-priority request';
        return ucfirst(trim(str_replace('_', ' ', preg_replace('/^rule_(\d+_)?/', '', $rule))));
    }
}

/** Collect everything the dashboard shows. */
function dash_collect(mysqli $conn): array
{
    $today = date('Y-m-d');
    $nowT  = date('H:i:s');
    $d     = ['today' => $today];

    // ── Equipment requests ───────────────────────────────────────────────
    $d['waiting']   = dash_scalar($conn, "SELECT COUNT(*) FROM tbl_requests WHERE status='Waiting'");
    $d['pickup']    = dash_scalar($conn, "SELECT COUNT(*) FROM tbl_requests WHERE status='Approved'");
    $d['active']    = dash_scalar($conn, "SELECT COUNT(*) FROM tbl_requests WHERE status='On Loan'");
    $d['due_today'] = dash_scalar($conn, "SELECT COUNT(*) FROM tbl_requests WHERE status IN ('Approved','On Loan') AND return_date = ?", 's', [$today]);
    $d['overdue']   = dash_scalar($conn, "SELECT COUNT(*) FROM tbl_requests WHERE status='Overdue'");

    $d['overdue_days'] = 0;
    $old = dash_rows($conn, "SELECT MIN(return_date) AS m FROM tbl_requests WHERE status='Overdue'");
    if ($old && !empty($old[0]['m'])) {
        $d['overdue_days'] = max(0, (int)floor((strtotime($today) - strtotime($old[0]['m'])) / 86400));
    }

    // ── Inventory ────────────────────────────────────────────────────────
    $d['inv_total'] = dash_scalar($conn, "SELECT COUNT(*) FROM tbl_inventory WHERE is_archived=0");
    $d['inv_out']   = dash_scalar($conn, "SELECT COUNT(*) FROM tbl_inventory WHERE is_archived=0 AND quantity<=0");
    $d['inv_low']   = dash_scalar($conn, "SELECT COUNT(*) FROM tbl_inventory WHERE is_archived=0 AND quantity>0 AND quantity<=2");
    $d['stock']     = dash_rows($conn, "SELECT item_name, category, quantity FROM tbl_inventory WHERE is_archived=0 AND quantity<=2 ORDER BY quantity ASC, item_name ASC LIMIT 5");

    // ── Rooms ────────────────────────────────────────────────────────────
    $d['rooms_bookable'] = dash_scalar($conn, "SELECT COUNT(*) FROM tbl_rooms WHERE is_archived=0 AND status='Available'");
    $d['rooms_today']    = dash_scalar($conn, "SELECT COUNT(*) FROM tbl_room_reservations WHERE reservation_date = ? AND status='Approved'", 's', [$today]);
    $d['rooms_in_use']   = dash_scalar($conn, "SELECT COUNT(DISTINCT room_id) FROM tbl_room_reservations WHERE reservation_date = ? AND status='Approved'", 's', [$today]);
    $d['rooms_issues']   = dash_scalar($conn, "SELECT COUNT(*) FROM tbl_room_issues WHERE status='Open'");
    $d['rooms_pct']      = $d['rooms_bookable'] > 0
        ? (int)min(100, round($d['rooms_in_use'] / $d['rooms_bookable'] * 100))
        : 0;
    $d['upcoming'] = dash_rows(
        $conn,
        "SELECT r.reservation_date, r.start_time, r.end_time, r.purpose, r.faculty_name,
                rm.room_name, b.name AS building
           FROM tbl_room_reservations r
      LEFT JOIN tbl_rooms rm     ON rm.room_id = r.room_id
      LEFT JOIN tbl_buildings b  ON b.building_id = rm.building_id
          WHERE r.status='Approved'
            AND (r.reservation_date > ? OR (r.reservation_date = ? AND r.end_time >= ?))
       ORDER BY r.reservation_date ASC, r.start_time ASC
          LIMIT 4",
        'sss',
        [$today, $today, $nowT]
    );

    // ── Activity chart: 60 days of history, both datasets. The 7D / 14D / 30D toggle is client-side;
    //    the extra days let each range be compared with the period right before it. ──
    $labels = [];
    for ($i = 59; $i >= 0; $i--) {
        $labels[] = date('Y-m-d', strtotime("-$i day", strtotime($today)));
    }
    $idx   = array_flip($labels);
    $zeros = array_fill(0, 60, 0);
    $eq    = ['approved' => $zeros, 'declined' => $zeros, 'waiting' => $zeros, 'cancelled' => $zeros];
    $rm    = ['approved' => $zeros, 'declined' => $zeros, 'cancelled' => $zeros];
    $from  = $labels[0] . ' 00:00:00';
    $from30 = $labels[30] . ' 00:00:00';   // the 30-day panels below (Most requested) keep a 30-day window

    foreach (dash_rows($conn, "SELECT DATE(request_date) AS d, status, COUNT(*) AS c FROM tbl_requests WHERE request_date >= ? GROUP BY DATE(request_date), status", 's', [$from]) as $r) {
        if (!isset($idx[$r['d']])) continue;
        // Approved / Overdue / Returned all started life as an approval.
        $k = in_array($r['status'], ['Approved', 'Overdue', 'Returned'], true) ? 'approved'
            : ($r['status'] === 'Declined' ? 'declined' : ($r['status'] === 'Waiting' ? 'waiting' : ($r['status'] === 'Cancelled' ? 'cancelled' : null)));
        if ($k) $eq[$k][$idx[$r['d']]] += (int)$r['c'];
    }
    foreach (dash_rows($conn, "SELECT DATE(request_date) AS d, status, COUNT(*) AS c FROM tbl_room_reservations WHERE request_date >= ? GROUP BY DATE(request_date), status", 's', [$from]) as $r) {
        if (!isset($idx[$r['d']])) continue;
        $k = strtolower($r['status']);
        if (isset($rm[$k])) $rm[$k][$idx[$r['d']]] += (int)$r['c'];
    }
    // Context behind the chart: why requests were declined, and which rooms are booked most.
    // Each row carries one count per day (same 60-day axis) so the browser can total it for any range.
    $reasons = [];
    foreach (dash_rows($conn, "SELECT DATE(request_date) AS d, COALESCE(NULLIF(arbitration_rule,''),'other') AS r, COUNT(*) AS c FROM tbl_requests WHERE status='Declined' AND request_date >= ? GROUP BY DATE(request_date), COALESCE(NULLIF(arbitration_rule,''),'other')", 's', [$from]) as $r) {
        if (!isset($idx[$r['d']])) continue;
        $lab = dash_decline_label($r['r']);
        if (!isset($reasons[$lab])) $reasons[$lab] = ['label' => $lab, 'values' => $zeros];
        $reasons[$lab]['values'][$idx[$r['d']]] += (int)$r['c'];
    }
    $roomsTop = [];
    foreach (
        dash_rows($conn, "SELECT DATE(r.request_date) AS d, r.room_id, rm.room_name, b.name AS building, COUNT(*) AS c
                                 FROM tbl_room_reservations r
                            LEFT JOIN tbl_rooms rm    ON rm.room_id = r.room_id
                            LEFT JOIN tbl_buildings b ON b.building_id = rm.building_id
                                WHERE r.status='Approved' AND r.request_date >= ?
                             GROUP BY DATE(r.request_date), r.room_id, rm.room_name, b.name", 's', [$from]) as $r
    ) {
        if (!isset($idx[$r['d']])) continue;
        $key = (string)$r['room_id'];
        if (!isset($roomsTop[$key])) {
            $roomsTop[$key] = ['label' => $r['room_name'] ?: ('Facility #' . $r['room_id']), 'sub' => (string)($r['building'] ?? ''), 'values' => $zeros];
        }
        $roomsTop[$key]['values'][$idx[$r['d']]] += (int)$r['c'];
    }
    $byTotal = static function (array $rows, int $keep): array {
        usort($rows, static fn($a, $b) => array_sum($b['values']) <=> array_sum($a['values']));
        return array_values(array_slice($rows, 0, $keep));
    };

    $d['chart'] = [
        'labels' => $labels,
        'sets'   => [
            'equipment' => ['title' => 'Equipment requests', 'unit' => 'request', 'series' => [
                ['key' => 'approved', 'label' => 'Approved', 'values' => $eq['approved']],
                ['key' => 'declined', 'label' => 'Declined', 'values' => $eq['declined']],
                ['key' => 'waiting',  'label' => 'Waiting',  'values' => $eq['waiting']],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'values' => $eq['cancelled']],
            ]],
            'rooms' => ['title' => 'Facility bookings', 'unit' => 'booking', 'series' => [
                ['key' => 'approved',  'label' => 'Approved',  'values' => $rm['approved']],
                ['key' => 'declined',  'label' => 'Declined',  'values' => $rm['declined']],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'values' => $rm['cancelled']],
            ]],
        ],
        'extras' => [
            'equipment' => ['title' => 'Top decline reason', 'rows' => $byTotal($reasons, 4)],
            'rooms'     => ['title' => 'Most booked facility',   'rows' => $byTotal($roomsTop, 5)],
        ],
    ];

    // ── Most requested equipment (30 days) ───────────────────────────────
    $d['top'] = dash_rows(
        $conn,
        "SELECT equipment_name, COUNT(*) AS total,
                SUM(status IN ('Approved','Overdue','Returned')) AS ok
           FROM tbl_requests
          WHERE request_date >= ? AND status <> 'Cancelled'
       GROUP BY equipment_name
       ORDER BY total DESC, equipment_name ASC
          LIMIT 5",
        's',
        [$from30]
    );

    // ── Latest requests + decision log ───────────────────────────────────
    // Same visibility rule the rest of the admin portal uses: auto-declined
    // requests are hidden; only manual overrides of a decline show.
    $d['latest'] = dash_rows($conn, "SELECT faculty_name, faculty_id, equipment_name, borrow_date, return_date, status, request_date,
                                            TIMESTAMPDIFF(SECOND, request_date, NOW()) AS age_s
                                       FROM tbl_requests
                                      WHERE NOT (status='Declined' AND COALESCE(arbitration_rule,'')<>'override')
                                   ORDER BY request_date DESC, id DESC LIMIT 6");
    // Same timeline as the Activity page: decisions by admins AND by the rules engine, with who made them.
    $d['log'] = [];
    try {
        $d['log'] = admin_activity_feed($conn, [
            'viewer_id'  => admin_current_id($conn) ?? 0,
            'is_super'   => ($_SESSION['admin_role'] ?? '') === 'Super Admin',
            'actor_mode' => 'all',
            'filter'     => 'decisions',
            'since'      => null,
            'offset'     => 0,
            'limit'      => 6,
        ])['items'];
    } catch (Throwable $e) {
        error_log('[dashboard] activity card: ' . $e->getMessage());
    }

    return $d;
}

/** Items for the "Needs attention" panel, most urgent first. */
function dash_attention(array $d): array
{
    $a = [];
    if ($d['overdue'] > 0) {
        $a[] = [
            'tone' => 'danger',
            'icon' => 'schedule',
            'title' => dash_pl($d['overdue'], 'overdue return'),
            'sub'   => $d['overdue_days'] > 0 ? 'Oldest is ' . dash_pl($d['overdue_days'], 'day') . ' past due' : 'Past its return date',
            'go' => 'inventory',
            'chip' => '',
            'loan_filter' => 'overdue'
        ];
    }
    if ($d['waiting'] > 0) {
        $a[] = [
            'tone' => 'warn',
            'icon' => 'assignment',
            'title' => dash_pl($d['waiting'], 'pending request'),
            'sub'   => 'Awaiting a decision',
            'go' => 'inventory',
            'chip' => '',
            'loan_filter' => 'waiting'
        ];
    }
    if ($d['due_today'] > 0) {
        $a[] = [
            'tone' => 'info',
            'icon' => 'event_available',
            'title' => dash_pl($d['due_today'], 'item') . ' due back today',
            'sub'   => 'Expect returns by end of day',
            'go' => 'inventory',
            'chip' => '',
            'loan_filter' => 'active'
        ];
    }
    if ($d['rooms_issues'] > 0) {
        $a[] = [
            'tone' => 'warn',
            'icon' => 'build',
            'title' => dash_pl($d['rooms_issues'], 'open facility issue'),
            'sub'   => 'Reported by faculty, not yet resolved',
            'go' => 'rooms',
            'chip' => ''
        ];
    }
    if ($d['inv_out'] > 0) {
        $names = [];
        foreach ($d['stock'] as $s) {
            if ((int)$s['quantity'] <= 0 && count($names) < 2) $names[] = $s['item_name'];
        }
        $a[] = [
            'tone' => 'danger',
            'icon' => 'inventory_2',
            'title' => dash_pl($d['inv_out'], 'item') . ' out of stock',
            'sub'   => $names ? implode(', ', $names) . ($d['inv_out'] > count($names) ? ' and more' : '') : 'Cannot be lent until restocked',
            'go' => 'inventory',
            'chip' => ''
        ];
    }
    if ($d['inv_low'] > 0) {
        $a[] = [
            'tone' => 'warn',
            'icon' => 'production_quantity_limits',
            'title' => dash_pl($d['inv_low'], 'item') . ' running low',
            'sub'   => '2 units or fewer remaining',
            'go' => 'inventory',
            'chip' => ''
        ];
    }
    return $a;
}

