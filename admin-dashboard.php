<?php require_once __DIR__ . '/equipment-booking/core/admin-functions.php'; ?>
<?php require_once __DIR__ . '/room-reservation/core/admin-rooms-functions.php'; ?>
<?php require_once __DIR__ . '/equipment-booking/core/notif-functions.php'; ?>
<?php require_once __DIR__ . '/config/role-splash.php'; ?>
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
    $d['active']    = dash_scalar($conn, "SELECT COUNT(*) FROM tbl_requests WHERE status='Approved'");
    $d['due_today'] = dash_scalar($conn, "SELECT COUNT(*) FROM tbl_requests WHERE status='Approved' AND return_date = ?", 's', [$today]);
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

/** Echo the dashboard body. */
function dash_render(array $d): void
{
    $attn   = dash_attention($d);
    $alerts = $d['inv_out'] + $d['inv_low'];
    $tomorrow = date('Y-m-d', strtotime('+1 day', strtotime($d['today'])));

    $kpis = [
        [
            'tone' => $d['waiting'] > 0 ? 'warn' : 'ok',
            'icon' => 'assignment',
            'label' => 'Pending requests',
            'val' => $d['waiting'],
            'sub' => $d['waiting'] > 0 ? 'Awaiting a decision' : 'Queue is clear',
            'go' => 'inventory',
            'chip' => '',
            'loan_filter' => 'waiting',
        ],
        [
            'tone' => 'ok',
            'icon' => 'swap_horiz',
            'label' => 'Active loans',
            'val' => $d['active'],
            'sub' => $d['due_today'] > 0 ? dash_pl($d['due_today'], 'item') . ' due back today' : 'None due today',
            'go' => 'inventory',
            'chip' => '',
            'loan_filter' => 'active',
        ],
        [
            'tone' => $d['overdue'] > 0 ? 'danger' : 'ok',
            'icon' => 'schedule',
            'label' => 'Overdue returns',
            'val' => $d['overdue'],
            'sub' => $d['overdue'] > 0
                ? ($d['overdue_days'] > 0 ? 'Oldest ' . dash_pl($d['overdue_days'], 'day') . ' late' : 'Needs follow-up')
                : 'Nothing overdue',
            'go' => 'inventory',
            'chip' => '',
            'loan_filter' => 'overdue',
        ],
        [
            'tone' => 'info',
            'icon' => 'meeting_room',
            'label' => 'Facilities today',
            'val' => $d['rooms_today'],
            'sub' => $d['rooms_bookable'] > 0
                ? $d['rooms_in_use'] . ' of ' . $d['rooms_bookable'] . ' facilities in use (' . $d['rooms_pct'] . '%)'
                : 'No bookable facilities yet',
            'go' => 'rooms',
            'chip' => '',
        ],
        [
            'tone' => $d['inv_out'] > 0 ? 'danger' : ($alerts > 0 ? 'warn' : 'ok'),
            'icon' => 'inventory_2',
            'label' => 'Stock alerts',
            'val' => $alerts,
            'sub' => $alerts > 0
                ? $d['inv_out'] . ' out · ' . $d['inv_low'] . ' low · of ' . $d['inv_total'] . ' items'
                : 'All ' . $d['inv_total'] . ' items stocked',
            'go' => 'inventory',
            'chip' => '',
        ],
    ];
?>
    <!-- ── Stat cards ─────────────────────────────────────────────── -->
    <section class="dash-kpis" aria-label="Key figures">
        <?php foreach ($kpis as $k): ?>
            <button type="button" class="dash-kpi dash-tone-<?php echo dash_h($k['tone']); ?>"
                data-dash-go="<?php echo dash_h($k['go']); ?>"
                data-dash-loan-filter="<?php echo dash_h($k['loan_filter'] ?? ''); ?>">
                <span class="dash-kpi-icon" aria-hidden="true"><span class="material-symbols-outlined"><?php echo dash_h($k['icon']); ?></span></span>
                <span class="dash-kpi-body">
                    <span class="dash-kpi-label"><?php echo dash_h($k['label']); ?></span>
                    <span class="dash-kpi-val"><?php echo (int)$k['val']; ?></span>
                    <span class="dash-kpi-sub"><?php echo dash_h($k['sub']); ?></span>
                </span>
                <span class="material-symbols-outlined dash-kpi-go" aria-hidden="true">arrow_outward</span>
            </button>
        <?php endforeach; ?>
    </section>

    <div class="dash-grid">

        <!-- ── Activity chart ─────────────────────────────────────── -->
        <section class="dash-card dash-area-chart" aria-labelledby="dashChartTitle">
            <header class="dash-card-head">
                <div class="dash-card-title">
                    <h2 id="dashChartTitle">Demand &amp; decisions</h2>
                    <p>New requests per day, split by how they were decided</p>
                </div>
                <div class="dash-controls">
                    <div class="dash-seg" role="group" aria-label="Dataset">
                        <button type="button" class="dash-seg-btn active" data-dash-set="equipment" aria-pressed="true">Equipment</button>
                        <button type="button" class="dash-seg-btn" data-dash-set="rooms" aria-pressed="false">Facilities</button>
                    </div>
                    <div class="dash-seg" role="group" aria-label="Range">
                        <button type="button" class="dash-seg-btn" data-dash-range="7" aria-pressed="false">7D</button>
                        <button type="button" class="dash-seg-btn active" data-dash-range="14" aria-pressed="true">14D</button>
                        <button type="button" class="dash-seg-btn" data-dash-range="30" aria-pressed="false">30D</button>
                    </div>
                </div>
            </header>
            <div class="dash-chart-wrap" data-chart="<?php echo dash_h(json_encode($d['chart'], JSON_UNESCAPED_UNICODE)); ?>">
                <div class="dash-chart-sum" data-dash-summary></div>
                <div class="dash-chart" data-dash-chart role="img" aria-label="Bar chart of requests per day"></div>
                <div class="dash-insights" data-dash-insights></div>
            </div>
        </section>

        <!-- ── Needs attention ────────────────────────────────────── -->
        <section class="dash-card dash-area-attn" aria-labelledby="dashAttnTitle">
            <header class="dash-card-head">
                <div class="dash-card-title">
                    <h2 id="dashAttnTitle">Needs attention</h2>
                    <p>What to act on right now</p>
                </div>
                <?php if ($attn): ?><span class="dash-count-pill"><?php echo count($attn); ?></span><?php endif; ?>
            </header>
            <?php if ($attn): ?>
                <ul class="dash-attn">
                    <?php foreach ($attn as $a): ?>
                        <li>
                            <button type="button" class="dash-attn-item dash-tone-<?php echo dash_h($a['tone']); ?>"
                                data-dash-go="<?php echo dash_h($a['go']); ?>"
                                data-dash-loan-filter="<?php echo dash_h($a['loan_filter'] ?? ''); ?>">
                                <span class="dash-attn-icon" aria-hidden="true"><span class="material-symbols-outlined"><?php echo dash_h($a['icon']); ?></span></span>
                                <span class="dash-attn-text">
                                    <strong><?php echo dash_h($a['title']); ?></strong>
                                    <small><?php echo dash_h($a['sub']); ?></small>
                                </span>
                                <span class="material-symbols-outlined dash-attn-go" aria-hidden="true">chevron_right</span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="dash-empty dash-empty--ok">
                    <span class="material-symbols-outlined" aria-hidden="true">task_alt</span>
                    <strong>You're all caught up</strong>
                    <small>No pending requests, overdue returns, facility issues or stock problems.</small>
                </div>
            <?php endif; ?>
        </section>

        <!-- ── Rooms today ────────────────────────────────────────── -->
        <section class="dash-card dash-area-rooms" aria-labelledby="dashRoomsTitle">
            <header class="dash-card-head">
                <div class="dash-card-title">
                    <h2 id="dashRoomsTitle">Facilities</h2>
                    <p>Today's usage and what's coming up</p>
                </div>
                <button type="button" class="dash-link" data-dash-go="rooms">Manage</button>
            </header>
            <div class="dash-meter">
                <div class="dash-meter-top">
                    <span><strong><?php echo (int)$d['rooms_in_use']; ?></strong> of <?php echo (int)$d['rooms_bookable']; ?> facilities in use today</span>
                    <span class="dash-meter-pct"><?php echo (int)$d['rooms_pct']; ?>%</span>
                </div>
                <div class="dash-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int)$d['rooms_pct']; ?>" aria-label="Facility utilization today">
                    <span style="width:<?php echo max($d['rooms_pct'] > 0 ? 2 : 0, (int)$d['rooms_pct']); ?>%"></span>
                </div>
                <?php if ($d['rooms_issues'] > 0): ?>
                    <div class="dash-meter-note dash-tone-warn"><span class="material-symbols-outlined" aria-hidden="true">build</span><?php echo dash_pl($d['rooms_issues'], 'open issue'); ?> reported</div>
                <?php endif; ?>
            </div>
            <?php if ($d['upcoming']): ?>
                <ul class="dash-list">
                    <?php foreach ($d['upcoming'] as $u):
                        $dayLbl = $u['reservation_date'] === $d['today'] ? 'Today' : ($u['reservation_date'] === $tomorrow ? 'Tomorrow' : date('D, M j', strtotime($u['reservation_date'])));
                    ?>
                        <li class="dash-row">
                            <span class="dash-row-when"><strong><?php echo dash_h($dayLbl); ?></strong><small><?php echo dash_h(date('g:i A', strtotime($u['start_time']))); ?></small></span>
                            <span class="dash-row-main">
                                <strong><?php echo dash_h($u['room_name'] ?? 'Facility'); ?></strong>
                                <small><?php echo dash_h(trim(($u['purpose'] ?? '') . ' · ' . ($u['faculty_name'] ?? ''), ' ·')); ?></small>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="dash-empty"><small>No upcoming reservations.</small></div>
            <?php endif; ?>
        </section>

        <!-- ── Most requested equipment ───────────────────────────── -->
        <section class="dash-card dash-area-top" aria-labelledby="dashTopTitle">
            <header class="dash-card-head">
                <div class="dash-card-title">
                    <h2 id="dashTopTitle">Most requested</h2>
                    <p>Equipment demand, last 30 days</p>
                </div>
            </header>
            <?php if ($d['top']):
                $max = max(1, (int)$d['top'][0]['total']); ?>
                <ul class="dash-list dash-bars">
                    <?php foreach ($d['top'] as $t):
                        $tot = (int)$t['total'];
                        $ok  = (int)$t['ok']; ?>
                        <li>
                            <div class="dash-bars-top"><span><?php echo dash_h($t['equipment_name']); ?></span><strong><?php echo $tot; ?></strong></div>
                            <div class="dash-bar dash-bar--two" title="<?php echo $ok; ?> approved of <?php echo $tot; ?>">
                                <span class="dash-bar-all" style="width:<?php echo round($tot / $max * 100); ?>%"></span>
                                <span class="dash-bar-ok" style="width:<?php echo round($ok / $max * 100); ?>%"></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="dash-foot"><span class="dash-dot dash-dot--ok"></span>Approved <span class="dash-dot dash-dot--all"></span>All requests</p>
            <?php else: ?>
                <div class="dash-empty"><small>No requests in the last 30 days.</small></div>
            <?php endif; ?>
        </section>

        <!-- ── Stock watch ────────────────────────────────────────── -->
        <section class="dash-card dash-area-stock" aria-labelledby="dashStockTitle">
            <header class="dash-card-head">
                <div class="dash-card-title">
                    <h2 id="dashStockTitle">Stock watch</h2>
                    <p>Items with 2 or fewer units available</p>
                </div>
                <button type="button" class="dash-link" data-dash-go="inventory">Manage</button>
            </header>
            <?php if ($d['stock']): ?>
                <ul class="dash-list">
                    <?php foreach ($d['stock'] as $s):
                        $q = (int)$s['quantity']; ?>
                        <li class="dash-row">
                            <span class="dash-row-main">
                                <strong><?php echo dash_h($s['item_name']); ?></strong>
                                <small><?php echo dash_h($s['category']); ?></small>
                            </span>
                            <span class="dash-qty <?php echo $q <= 0 ? 'dash-qty--out' : 'dash-qty--low'; ?>"><?php echo $q <= 0 ? 'Out' : $q . ' left'; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="dash-empty dash-empty--ok">
                    <span class="material-symbols-outlined" aria-hidden="true">inventory</span>
                    <strong>Stock looks healthy</strong>
                    <small>Every item has 3 or more units.</small>
                </div>
            <?php endif; ?>
            <?php if ($d['inv_total'] > 0):
                $healthy = max(0, $d['inv_total'] - $d['inv_out'] - $d['inv_low']);
                $pct = fn(int $n): float => round($n / $d['inv_total'] * 100, 1); ?>
                <div class="dash-health">
                    <div class="dash-health-top"><span>Catalog health</span><strong><?php echo (int)round($healthy / $d['inv_total'] * 100); ?>% well stocked</strong></div>
                    <div class="dash-stack" role="img" aria-label="<?php echo $healthy; ?> well stocked, <?php echo (int)$d['inv_low']; ?> low, <?php echo (int)$d['inv_out']; ?> out of stock">
                        <span class="dash-stack-ok" style="width:<?php echo $pct($healthy); ?>%"></span>
                        <span class="dash-stack-low" style="width:<?php echo $pct($d['inv_low']); ?>%"></span>
                        <span class="dash-stack-out" style="width:<?php echo $pct($d['inv_out']); ?>%"></span>
                    </div>
                    <p class="dash-foot dash-foot--flat"><span class="dash-dot dash-dot--ok"></span><?php echo $healthy; ?> ok<span class="dash-dot dash-dot--low"></span><?php echo (int)$d['inv_low']; ?> low<span class="dash-dot dash-dot--out"></span><?php echo (int)$d['inv_out']; ?> out</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- ── Latest requests ────────────────────────────────────── -->
        <section class="dash-card dash-area-latest" aria-labelledby="dashLatestTitle">
            <header class="dash-card-head">
                <div class="dash-card-title">
                    <h2 id="dashLatestTitle">Latest requests</h2>
                    <p>Most recent equipment requests</p>
                </div>
                <button type="button" class="dash-link" data-dash-go="inventory" data-dash-loan-filter="all">View all</button>
            </header>
            <div class="ps-table-wrap">
                <table class="ps-table dash-table">
                    <thead>
                        <tr>
                            <th>Requester</th>
                            <th>Equipment</th>
                            <th>Borrow period</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($d['latest']):
                            foreach ($d['latest'] as $r):
                                $badge = match ($r['status']) {
                                    'Waiting'  => 'ps-badge--waiting',
                                    'Approved' => 'ps-badge--active',
                                    'Overdue'  => 'ps-badge--overdue',
                                    default    => 'ps-badge--returned',
                                };
                                $b = strtotime($r['borrow_date']);
                                $e = strtotime($r['return_date']);
                                $period = date('M j', $b) . ($r['borrow_date'] !== $r['return_date'] ? ' – ' . date('M j', $e) : '');
                        ?>
                                <tr>
                                    <td>
                                        <div class="dash-cell-name"><?php echo dash_h($r['faculty_name']); ?></div>
                                        <div class="dash-cell-sub"><?php echo dash_h(faculty_id_display($r['faculty_id'], 'Not set')); ?> · <?php echo dash_h(dash_ago($r['age_s'] ?? null, $r['request_date'])); ?></div>
                                    </td>
                                    <td><?php echo dash_h($r['equipment_name']); ?></td>
                                    <td class="dash-cell-muted"><?php echo dash_h($period); ?></td>
                                    <td><span class="ps-badge ps-badge--dot <?php echo $badge; ?>"><?php echo dash_h($r['status']); ?></span></td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="4" class="dash-cell-empty">No requests yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!--  Recent activity (same timeline as the Activity page)  -->
        <section class="dash-card dash-area-log" aria-labelledby="dashLogTitle">
            <header class="dash-card-head">
                <div class="dash-card-title">
                    <h2 id="dashLogTitle">Recent activity</h2>
                    <p>Decisions by admins and the rules engine</p>
                </div>
                <button type="button" class="dash-link" data-dash-go="activity">View all</button>
            </header>
            <?php if ($d['log']): ?>
                <ul class="dash-list dash-log">
                    <?php foreach ($d['log'] as $l):
                        $tone = ['ok' => 'ok', 'bad' => 'danger', 'warn' => 'warn'][$l['tone']] ?? 'info';
                        $who  = $l['actor_type'] === 'auto'
                            ? 'Automatic' . ($l['meta'] !== '' ? ' · ' . $l['meta'] : '')
                            : $l['actor_name'];
                    ?>
                        <li class="dash-row dash-tone-<?php echo $tone; ?>">
                            <span class="dash-log-icon" aria-hidden="true"><span class="material-symbols-outlined"><?php echo dash_h($l['icon']); ?></span></span>
                            <span class="dash-row-main">
                                <strong title="<?php echo dash_h($l['text']); ?>"><?php echo dash_h($l['text']); ?></strong>
                                <small><?php echo dash_h($who); ?></small>
                            </span>
                            <span class="dash-row-time"><?php echo dash_h(dash_ago(max(0, time() - (int)$l['ts']), date('Y-m-d H:i:s', (int)$l['ts']))); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="dash-empty"><small>No activity yet.</small></div>
            <?php endif; ?>
        </section>

    </div>
    <?php
}

// Live-refresh request from admin-dashboard.js: return just the dashboard body and stop.
// (admin-functions.php above has already enforced the admin session.)
if (($_GET['fragment'] ?? '') === 'dashboard') {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    dash_render(dash_collect($conn));
    exit;
}

// ── Inventory tbody fragment ────────────────────────────────────────────
// Returns JSON with rendered <tbody> HTML for active and archived inventory.
// Called by admin-dashboard.php inline scripts after any inventory AJAX mutation.
if (($_GET['fragment'] ?? '') === 'inventory-tbody') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    $inv_rows = [];
    $inv_result = $conn->query("SELECT * FROM tbl_inventory WHERE is_archived = 0 ORDER BY created_at DESC");
    if ($inv_result) {
        while ($r = $inv_result->fetch_assoc()) $inv_rows[] = $r;
    }

    $arch_rows = [];
    $arch_result = $conn->query("SELECT * FROM tbl_inventory WHERE is_archived = 1 ORDER BY item_name ASC");
    if ($arch_result) {
        while ($r = $arch_result->fetch_assoc()) $arch_rows[] = $r;
    }

    // Render active tbody
    ob_start();
    if (empty($inv_rows)): ?>
        <tr id="inv-empty-row">
            <td colspan="5" class="inv-empty-cell">
                <span class="material-symbols-outlined">inventory_2</span>
                No equipment yet. Add your first item using the form.
            </td>
        </tr>
        <?php else:
        foreach ($inv_rows as $item):
            $inv_qty       = (int)$item['quantity'];
            $inv_cond      = $item['condition'] ?? 'Good';
            $inv_condClass = ($inv_cond === 'For Repair') ? 'repair' : (($inv_cond === 'Fair') ? 'fair' : 'good');
            if ($inv_qty === 0) {
                $inv_stockClass = 'none';
                $inv_stockLabel = 'No stock';
            } elseif ($inv_qty <= 2) {
                $inv_stockClass = 'low';
                $inv_stockLabel = 'Low stock';
            } else {
                $inv_stockClass = 'ok';
                $inv_stockLabel = 'In stock';
            }
            $inv_addedTs = !empty($item['created_at']) ? strtotime($item['created_at']) : false;
        ?>
            <tr class="inv-row-item"
                data-item-id="<?php echo (int)$item['item_id']; ?>"
                data-item-name="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>"
                data-item-category="<?php echo htmlspecialchars($item['category'], ENT_QUOTES); ?>"
                data-item-quantity="<?php echo $inv_qty; ?>"
                data-item-condition="<?php echo htmlspecialchars($inv_cond, ENT_QUOTES); ?>"
                data-item-image="<?php echo htmlspecialchars($item['image_path'], ENT_QUOTES); ?>"
                data-item-image-full="<?php echo htmlspecialchars($root_url . $item['image_path'], ENT_QUOTES); ?>">
                <td>
                    <div class="fac-person">
                        <span class="inv-thumb">
                            <img src="<?php echo $root_url . htmlspecialchars($item['image_path']); ?>"
                                alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                loading="lazy">
                            <span class="material-symbols-outlined inv-thumb-ph">inventory_2</span>
                        </span>
                        <div class="fac-person-text">
                            <div class="fac-person-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                            <?php if ($inv_addedTs): ?><div class="fac-person-sub">Added <?php echo date('M j, Y', $inv_addedTs); ?></div><?php endif; ?>
                        </div>
                    </div>
                </td>
                <td><span class="inv-pill inv-pill--cat"><?php echo htmlspecialchars($item['category']); ?></span></td>
                <td><span class="inv-pill inv-pill--<?php echo $inv_condClass; ?>"><?php echo htmlspecialchars($inv_cond); ?></span></td>
                <td><span class="inv-pill inv-pill--stock inv-pill--<?php echo $inv_stockClass; ?>"><strong><?php echo $inv_qty; ?></strong> <?php echo $inv_stockLabel; ?></span></td>
                <td class="fac-actions">
                    <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm inv-edit-btn"
                        data-action="eq-open-edit" aria-label="Edit equipment">
                        <span class="material-symbols-outlined">edit</span>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        <tr id="inv-nomatch-row" class="inv-row-hidden">
            <td colspan="5" class="inv-empty-cell">
                <span class="material-symbols-outlined">search_off</span>
                No equipment matches your search.
            </td>
        </tr>
    <?php endif;
    $active_html = ob_get_clean();

    // Render archived tbody
    ob_start();
    if (empty($arch_rows)): ?>
        <tr>
            <td colspan="4" class="inv-empty-cell">No archived items.</td>
        </tr>
        <?php else:
        foreach ($arch_rows as $item): ?>
            <tr>
                <td><img src="<?php echo $root_url . htmlspecialchars($item['image_path']); ?>" class="item-img" onerror="this.src='../uploads/default.png'"></td>
                <td class="fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></td>
                <td><?php echo htmlspecialchars($item['category']); ?></td>
                <td class="action-cell">
                    <div class="action-btns">
                        <a href="admin-dashboard.php?restore_item=<?php echo $item['item_id']; ?>"
                            class="btn-action btn-restore" title="Restore">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                <polyline points="1 4 1 10 7 10" />
                                <path d="M3.51 15a9 9 0 1 0 .49-3.51" />
                            </svg>
                        </a>
                        <a href="admin-dashboard.php?force_delete=<?php echo $item['item_id']; ?>"
                            class="btn-action btn-force-del" title="Delete permanently">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                <polyline points="3 6 5 6 21 6" />
                                <path d="M19 6l-1 14H6L5 6" />
                                <path d="M10 11v6" />
                                <path d="M14 11v6" />
                                <path d="M9 6V4h6v2" />
                            </svg>
                        </a>
                    </div>
                </td>
            </tr>
        <?php endforeach;
    endif;
    $archive_html = ob_get_clean();

    echo json_encode([
        'status'        => 'success',
        'active_html'   => $active_html,
        'archive_html'  => $archive_html,
        'active_count'  => count($inv_rows),
        'archive_count' => count($arch_rows),
    ]);
    exit();
}

// ── Faculty list tbody fragment ─────────────────────────────────────────────
// Returns JSON with rendered <tbody> HTML for the faculty accounts list.
// Called by admin-dashboard.php inline scripts after a faculty account is created.
if (($_GET['fragment'] ?? '') === 'faculty-tbody') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    $fac_rows = [];
    $fac_result = $conn->query(
        "SELECT u.fullname, u.email, u.backup_email, u.role,"
            . " u.faculty_id, u.organization_id,"
            . " o.name AS org_name"
            . " FROM tbl_users u"
            . " LEFT JOIN tbl_organizations o ON u.organization_id = o.id"
            . " ORDER BY u.fullname ASC"
    );
    if ($fac_result) {
        while ($r = $fac_result->fetch_assoc()) $fac_rows[] = $r;
    }

    ob_start();
    if (empty($fac_rows)): ?>
        <tr id="fac-empty-row">
            <td colspan="4" style="text-align:center;padding:3rem;color:var(--text-light)">
                <span class="material-symbols-outlined" style="font-size:40px;display:block;margin:0 auto 10px;opacity:0.3">group</span>
                No faculty accounts yet.
            </td>
        </tr>
        <?php else:
        foreach ($fac_rows as $frow):
            $isAdviser = ($frow['role'] === 'Organization Adviser');
            $idUnset   = faculty_id_is_unset($frow['faculty_id']);
            $initFac   = strtoupper(substr(trim($frow['fullname'] ?? '') ?: 'F', 0, 1));
        ?>
            <tr
                data-fullname="<?= htmlspecialchars($frow['fullname']) ?>"
                data-email="<?= htmlspecialchars($frow['email']) ?>"
                data-backup-email="<?= htmlspecialchars($frow['backup_email'] ?? '') ?>"
                data-faculty-id="<?= htmlspecialchars($frow['faculty_id']) ?>"
                data-role="<?= htmlspecialchars($frow['role']) ?>"
                data-org="<?= htmlspecialchars($frow['org_name'] ?? '') ?>"
                data-org-id="<?= (int)($frow['organization_id'] ?? 0) ?>"
                data-init="<?= htmlspecialchars($initFac) ?>">
                <td>
                    <div class="fac-person">
                        <span class="fac-avatar"><?= htmlspecialchars($initFac) ?></span>
                        <div class="fac-person-text">
                            <div class="fac-person-name"><?= htmlspecialchars($frow['fullname']) ?></div>
                            <div class="fac-person-sub"><?= htmlspecialchars($frow['email']) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <?php if ($idUnset): ?>
                        <span class="fac-id-pending">Not set yet</span>
                    <?php else: ?>
                        <span class="fac-id"><?= htmlspecialchars($frow['faculty_id']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($isAdviser): ?>
                        <span class="fac-role fac-role--adviser">Org Adviser</span>
                        <?php if (!empty($frow['org_name'])): ?>
                            <div class="fac-person-sub"><?= htmlspecialchars($frow['org_name']) ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="fac-role">Faculty</span>
                    <?php endif; ?>
                </td>
                <td class="fac-actions">
                    <button class="ps-btn ps-btn--ghost ps-btn--sm fac-edit-btn" aria-label="Edit account">
                        <span class="material-symbols-outlined">edit</span>
                    </button>
                </td>
            </tr>
<?php endforeach;
    endif;
    $faculty_html = ob_get_clean();

    echo json_encode([
        'status'        => 'success',
        'faculty_html'  => $faculty_html,
        'faculty_count' => count($fac_rows),
    ]);
    exit();
}
?>

<?php
// ================= NOTIFICATIONS (live feed) =================
// Computed fresh on every load from tbl_requests / tbl_room_issues /
// tbl_inventory — see notif-functions.php. $notif_unread drives every
// badge on the page (bell, dropdown item, sidebar) so they always agree
// with what's inside the modal.
$notifications = notif_build_list($conn);
$notif_unread  = notif_count_unread($notifications);

// Live count per category — drives which filter chips are worth
// showing at all (no point offering a "Rooms" filter with 0 items).
$notif_cat_counts = ['request' => 0, 'overdue' => 0, 'room' => 0, 'system' => 0];
foreach ($notifications as $n) {
    if (isset($notif_cat_counts[$n['cat']])) $notif_cat_counts[$n['cat']]++;
}
?>
<?php
// ── APPROVALS UI HELPERS (display only — no DB access) ───────────────────────
// ps_doc_info(): turns a stored document_path into a safe, display-ready
// descriptor. Only paths inside the two known upload folders are accepted
// (no "..", no odd characters), so a bad value in the DB can never become a
// link to something else. Returns null when there is no usable document.
if (!function_exists('ps_doc_info')) {
    function ps_doc_info($path)
    {
        $path = trim((string)$path);
        if ($path === '' || strpos($path, '..') !== false) return null;
        if (!preg_match('#^uploads/(request_letters|room_documents)/([A-Za-z0-9._ \-]+)$#', $path, $m)) return null;
        $base = $m[2];
        $ext  = strtolower(pathinfo($base, PATHINFO_EXTENSION));
        $kind = $ext === 'pdf' ? 'pdf' : (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true) ? 'image' : 'file');
        return [
            'url'  => 'uploads/' . $m[1] . '/' . rawurlencode($base),
            'open_url' => 'views/document-viewer.php?file=' . rawurlencode($path),
            // hide the upload timestamp prefix (e.g. "1790900630_" or "20261002_081929_")
            'name' => preg_replace('/^(\d{8}_\d{6}_|\d{9,}_)/', '', $base),
            'kind' => $kind,
            'ext'  => strtoupper($ext),
        ];
    }
}
// ps_submitted_as_label(): human label for tbl_requests / tbl_room_reservations.submitted_as
if (!function_exists('ps_submitted_as_label')) {
    function ps_submitted_as_label($v)
    {
        switch ((string)$v) {
            case 'adviser':
                return 'Org Adviser';
            case 'student':
                return 'Student (via code)';
            default:
                return 'Faculty';
        }
    }
}
// ps_doc_icon(): Material Symbols icon name for a document kind
if (!function_exists('ps_doc_icon')) {
    function ps_doc_icon($kind)
    {
        return $kind === 'pdf' ? 'picture_as_pdf' : ($kind === 'image' ? 'image' : 'draft');
    }
}
?>
<?php
// ── CONFIRM RETURN ─────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'return_confirm' && isset($_GET['id'])) {
    $req_id = intval($_GET['id']);
    $is_ajax_rc = ($_GET['ajax'] ?? '') === '1';
    if ($is_ajax_rc) {
        header('Content-Type: application/json');
    }
    require_once __DIR__ . '/equipment-booking/core/booking-schedule.php';
    BookingSchedule::ensureSchema($conn);
    $res = $conn->query("SELECT equipment_name, borrow_qty, stock_applied FROM tbl_requests WHERE id = $req_id LIMIT 1");
    if ($res && $row_rc = $res->fetch_assoc()) {
        $conn->query("UPDATE tbl_requests SET status = 'Returned', return_token = NULL, returned_at = NOW() WHERE id = $req_id");
        $eq = $conn->real_escape_string($row_rc['equipment_name']);
        // only units that actually left the shelf come back (see BookingSchedule)
        $ret_qty = ((int)($row_rc['stock_applied'] ?? 1) === 1) ? max(1, (int)($row_rc['borrow_qty'] ?? 1)) : 0;
        if ($ret_qty > 0) {
            $conn->query("UPDATE tbl_inventory SET quantity = quantity + $ret_qty WHERE item_name = '$eq'");
        }
        if ($is_ajax_rc) {
            echo json_encode(['status' => 'success', 'message' => 'Return confirmed.']);
            exit();
        }
    } else {
        if ($is_ajax_rc) {
            echo json_encode(['status' => 'error', 'message' => 'Request not found.']);
            exit();
        }
    }
    header("Location: admin-dashboard.php?view=return-confirmation");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUP Sync | Admin Portal</title>
    <!-- Favicon: the PUPSync logo mark (maroon square + layers) -->
    <link rel="icon" href="assets/images/favicon.ico" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/images/favicon-192.png">
    <link rel="apple-touch-icon" href="assets/images/apple-touch-icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
        rel="stylesheet">
    <link rel="stylesheet" href="equipment-booking/assets/css/admin-dashboard.css?v=<?php echo filemtime('equipment-booking/assets/css/admin-dashboard.css'); ?>">
    <!-- Mobile/tablet adaptations; must load after admin-dashboard.css -->
    <link rel="stylesheet" href="equipment-booking/assets/css/admin-dashboard-responsive.css?v=<?php echo @filemtime('equipment-booking/assets/css/admin-dashboard-responsive.css'); ?>">
    <link rel="stylesheet" href="equipment-booking/assets/css/admin-student-registry.css?v=<?php echo @filemtime('equipment-booking/assets/css/admin-student-registry.css'); ?>">
    <!-- Shared logout confirmation dialog + loading state -->
    <link rel="stylesheet" href="assets/css/logout-modal.css?v=<?php echo @filemtime('assets/css/logout-modal.css'); ?>">
    <?php role_splash_head(); ?>
    <script>
        /* Keep the default dashboard from appearing while the restored tab loads. */
        (function() {
            var params = new URLSearchParams(window.location.search);
            var tab = null;

            if (params.has('edit_item')) {
                tab = 'inventory';
            } else {
                var view = params.get('view');
                if (['inventory', 'faculty', 'activity', 'admins'].indexOf(view) !== -1) {
                    tab = view;
                } else if (params.get('tab') === 'rooms') {
                    tab = 'rooms';
                } else {
                    var hash = window.location.hash;
                    if (hash.indexOf('#tab-') === 0) tab = hash.slice(5);
                }
            }

            if (['rooms', 'faculty', 'admins', 'activity', 'inventory', 'settings'].indexOf(tab) === -1) return;
            try {
                if (sessionStorage.getItem('adminHardRefresh') === '1') return;
            } catch (_) {}
            window._preJumpTab = tab;
            document.documentElement.classList.add('admin-tab-pending');
        })();
    </script>
    <style>
        html.admin-tab-pending #panel-dashboard {
            display: none !important;
        }
    </style>
</head>

<body>

    <?php render_role_splash(($_SESSION['admin_role'] ?? 'Admin') === 'Super Admin' ? 'Super Admin' : 'Admin'); ?>

    <!-- ================================================================
     MOBILE MENU BUTTON
     The top bar is gone — the sidebar is the only app chrome. On phones the
     sidebar is an off-canvas drawer, and this floating button opens it.
     (Hidden on tablet/desktop; see .sidebar-toggle-btn in the CSS.)
================================================================ -->
    <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Open menu"
        aria-expanded="false" aria-controls="adminSidebar">
        <span class="material-symbols-outlined">menu</span>
    </button>

    <!-- ================================================================
     APP BODY
================================================================ -->
    <div class="app-body">

        <!-- Backdrop behind the off-canvas sidebar on mobile — tapping it closes the menu -->
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <!-- ================================================================
     SIDEBAR
================================================================ -->
        <nav class="sidebar" id="adminSidebar">

            <!-- Brand row: [logo] [PUPSYNC / Admin Portal] ........ [collapse toggle].
                 On phones the same toggle closes the drawer (see admin-dashboard.js). -->
            <div class="sidebar-brand">
                <div class="sidebar-logo" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"
                        aria-hidden="true">
                        <polygon points="12 2 2 7 12 12 22 7 12 2" />
                        <polyline points="2 17 12 22 22 17" />
                        <polyline points="2 12 12 17 22 12" />
                    </svg>
                </div>
                <div class="sidebar-brand-text">
                    <span class="sidebar-title"><strong>PUP</strong><span class="snt-light">SYNC</span></span>
                    <span class="sidebar-sub">Admin Portal</span>
                </div>
                <button type="button" class="sidebar-toggle" id="sidebarCollapseBtn"
                    title="Collapse sidebar" aria-label="Collapse sidebar" aria-expanded="true"
                    aria-controls="adminSidebar">
                    <svg class="sn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <rect x="3" y="4" width="18" height="16" rx="3.5" />
                        <path d="M9.5 4v16" />
                        <rect class="sn-fill" x="4.6" y="5.6" width="3.3" height="12.8" rx="1.4"
                            fill="currentColor" stroke="none" />
                    </svg>
                </button>
            </div>

            <!-- Tabs are grouped by purpose: Overview (what's happening) · Operations (day-to-day work) ·
                 Accounts (Super Admin only). Each group title shares a row with its thin separator line. -->
            <div class="sidebar-links">
                <div class="nav-group" role="group" aria-labelledby="navg-overview">
                    <span class="nav-group-title" id="navg-overview">Overview</span>
                    <a class="nav-item active" data-tab="dashboard" href="#">
                        <span class="material-symbols-outlined">dashboard</span>
                        <span class="nav-label">Dashboard</span>
                    </a>
                    <a class="nav-item" data-tab="activity" id="snav-activity" href="#">
                        <span class="material-symbols-outlined">history</span>
                        <span class="nav-label">Activity</span>
                    </a>
                </div>
                <div class="nav-group" role="group" aria-labelledby="navg-operations">
                    <span class="nav-group-title" id="navg-operations">Operations</span>
                    <a class="nav-item" data-tab="inventory" id="snav-inventory" href="#">
                        <span class="material-symbols-outlined">inventory_2</span>
                        <span class="nav-label">Equipment</span>
                    </a>
                    <a class="nav-item" data-tab="rooms" href="#">
                        <span class="material-symbols-outlined">meeting_room</span>
                        <span class="nav-label">Facilities</span>
                    </a>
                </div>
                <?php if ($is_super_admin): ?>
                    <div class="nav-group" role="group" aria-labelledby="navg-accounts">
                        <span class="nav-group-title" id="navg-accounts">Accounts</span>
                        <a class="nav-item" data-tab="faculty" href="#">
                            <span class="material-symbols-outlined">group</span>
                            <span class="nav-label">Faculty</span>
                        </a>
                        <a class="nav-item" data-tab="admins" id="snav-admins" href="#">
                            <span class="material-symbols-outlined">admin_panel_settings</span>
                            <span class="nav-label">Admins</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="sidebar-footer">
                <!-- Signed-in admin: the name only. Not a button, no avatar. Notifications and Settings
                     live in the Dashboard's top-right bell / avatar; Scan Return is a Quick Action card
                     on the Dashboard. (.nav-account-name stays on the span so the live name update in
                     admin-dashboard.js keeps working.) -->
                <div class="nav-user" id="navAccountGroup" hidden aria-hidden="true" style="display:none;" title="<?php echo htmlspecialchars($admin_role); ?>">
                    <span class="nav-label nav-account-name"><?php echo htmlspecialchars($admin_name); ?></span>
                </div>
                <!-- Settings: just under the divider line, directly above Log Out. data-tab="settings" is picked
                     up by the generic .nav-item[data-tab] handler in admin-dashboard.js (same tab the Dashboard
                     avatar opens). -->
                <a class="nav-item" data-tab="settings" id="snav-settings" href="#">
                    <span class="material-symbols-outlined">settings</span>
                    <span class="nav-label">Settings</span>
                </a>
                <!-- Log Out — pinned to the very bottom of the sidebar -->
                <a class="nav-item nav-signout" id="snav-logout" data-action="logout" href="#">
                    <span class="material-symbols-outlined">logout</span>
                    <span class="nav-label">Log Out</span>
                </a>
            </div>

        </nav><!-- /sidebar -->

        <!-- ================================================================
     MAIN
================================================================ -->
        <main id="app-main">

            <!-- ============================================================
         TAB: DASHBOARD
    ============================================================ -->
            <div class="tab-panel active" id="panel-dashboard">

                <!-- Overdue + flash messages (item/room saved, archived, restored, error) are shown as
                     toasts: see #toast-queue near the bottom of this file and initToastQueue() in
                     admin-dashboard.js. -->

                <?php
                // dash_collect() / dash_render() are defined at the top of this file; the same markup is
                // returned by admin-dashboard.php?fragment=dashboard for the live refresh.
                $dash_data = dash_collect($conn);
                ?>
                <div class="dash dash-intro" id="dash">

                    <!-- Header: greeting + primary actions + bell/avatar.
                         Everything here is static (never swapped by the live refresh), so the ids that
                         admin-dashboard.js binds to — greetName, dashNotifBtn, dashNotifBadge, dashAvatarBtn,
                         openQrScannerBtn — stay put. -->
                    <header class="dash-head">
                        <div class="dash-head-text">
                            <h1>Good <?php
                                        $hour = (int)date('H');
                                        echo $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');
                                        ?>, <span id="greetName"><?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?></span>.</h1>
                            <p><?php echo date('l, F j, Y'); ?></p>
                        </div>
                        <div class="dash-head-tools">
                            <!-- One quick action left on purpose: confirming a return by QR code has no other entry point
                                 on this screen. "Review requests" and "Add equipment" were removed because the
                                 Pending requests card and the Needs attention list already lead to the same places.
                                 Keeps id="openQrScannerBtn": initQrScanner() in admin-dashboard.js binds to it. -->
                            <div class="dash-actions">
                                <button type="button" class="dash-btn dash-btn--primary" id="openQrScannerBtn">
                                    <span class="material-symbols-outlined" aria-hidden="true">qr_code_scanner</span>Scan return
                                </button>
                            </div>
                            <div class="dash-head-user">
                                <!-- .notif-btn-badge is kept in sync by _updateBadges() in admin-dashboard.js -->
                                <button type="button" class="notif-btn ps-dash-top-btn" id="dashNotifBtn"
                                    data-action="open-notif-modal" aria-label="Open notifications" title="Notifications">
                                    <span class="material-symbols-outlined">notifications</span>
                                    <span class="notif-btn-badge" id="dashNotifBadge"
                                        style="<?php echo $notif_unread > 0 ? '' : 'display:none;'; ?>"><?php echo (int)$notif_unread; ?></span>
                                </button>
                                <button type="button" class="avatar-btn" id="dashAvatarBtn"
                                    data-action="open-account-settings" aria-label="Open my account" title="My account"><?php echo htmlspecialchars($initials); ?></button>
                            </div>
                        </div>
                    </header>

                    <!-- Live region: stat cards + panels. Re-rendered by the dashboard block at the end of admin-dashboard.js. -->
                    <div id="dashLive">
                        <?php dash_render($dash_data); ?>
                    </div>

                </div><!-- /dash -->

            </div><!-- /panel-dashboard -->








            <!-- ============================================================
         TAB: ROOMS
    ============================================================ -->
            <div class="tab-panel" id="panel-rooms">

                <!-- ── Page header + Add Room button ──────────────────── -->
                <div class="pr-page-header-row">
                    <div>
                        <h1 class="pr-page-title">Facilities Management</h1>
                        <p class="pr-page-subtitle">Browse facilities, reservations and reported issues.</p>
                    </div>
                    <?php if ($is_super_admin): ?>
                        <button type="button" class="pr-btn pr-btn-primary" data-action="show-room-form" id="addRoomBtn">
                            <span class="material-symbols-outlined">add</span>
                            Add Facility
                        </button>
                    <?php endif; ?>
                </div>

                <!-- ── Add / Edit Room form (hidden by default) ────────── -->
                <!-- Shared by both Add and Edit — only hiding the Add Room
                     button above for regular Admins, not this whole block,
                     since Admins can still edit existing rooms via each
                     row's Edit icon, which reuses this same form. -->
                <div id="room-form-wrap" class="<?php echo $edit_room ? '' : 'hidden'; ?>">
                    <div class="eq-card form-card">

                        <!-- Modal header -->
                        <div class="form-card-header">
                            <div class="rmod-head-icon">
                                <span class="material-symbols-outlined"><?php echo $edit_room ? 'edit' : 'add_home_work'; ?></span>
                            </div>
                            <h2 id="room-form-title">
                                <?php echo $edit_room
                                    ? 'Edit Facility &mdash; ' . htmlspecialchars($edit_room['room_name'])
                                    : 'Add New Facility'; ?>
                            </h2>
                            <button type="button" class="rmod-close-btn" data-action="hide-room-form" aria-label="Close">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        </div>

                        <!-- Body wrapper — matches equipment form padding -->
                        <div class="form-card-body">
                            <form method="POST" id="roomForm">
                                <?= csrf_field() ?>
                                <input type="hidden" name="room_id" id="room-form-id"
                                    value="<?php echo $edit_room ? (int)$edit_room['room_id'] : ''; ?>">

                                <!-- Building | Room name -->
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="rf-building">Building <span class="req-star" aria-label="required">*</span></label>
                                        <select name="building_id" id="rf-building" class="form-control-custom" required>
                                            <option value="">Select building</option>
                                            <?php foreach ($rooms_buildings as $bid => $b): ?>
                                                <option value="<?php echo (int)$bid; ?>"
                                                    data-floors="<?php echo max(1, (int)$b['floor_count']); ?>"
                                                    <?php echo ($edit_room && (int)$edit_room['building_id'] === $bid) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($b['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="rf-name">Facility name <span class="req-star" aria-label="required">*</span></label>
                                        <input type="text" name="room_name" id="rf-name" class="form-control-custom"
                                            value="<?php echo $edit_room ? htmlspecialchars($edit_room['room_name']) : ''; ?>"
                                            placeholder="e.g. Computer Lab 1" required>
                                    </div>
                                </div>

                                <!-- Floor | Status -->
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="rf-floor">Floor <span class="req-star" aria-label="required">*</span></label>
                                        <input type="number" name="floor_number" id="rf-floor" class="form-control-custom" min="1" max="20"
                                            value="<?php echo $edit_room ? (int)$edit_room['floor_number'] : 1; ?>" required>
                                        <!-- Filled by psSyncRoomFloorLimit(): the max follows the selected building's floor count -->
                                        <p class="rf-hint rf-floor-hint" id="rf-floor-hint" aria-live="polite">Select a building to see how many floors it has.</p>
                                    </div>
                                    <div class="form-group">
                                        <label for="rf-status">Status</label>
                                        <select name="status" id="rf-status" class="form-control-custom">
                                            <?php foreach (['Available', 'Maintenance', 'Not Bookable'] as $s): ?>
                                                <option value="<?php echo $s; ?>"
                                                    <?php echo ($edit_room && $edit_room['status'] === $s) ? 'selected' : (!$edit_room && $s === 'Available' ? 'selected' : ''); ?>>
                                                    <?php echo htmlspecialchars($s); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <p class="rf-hint rf-hint-flush">Maintenance and Not Bookable facilities can&rsquo;t be reserved.</p>

                                <!-- Features -->
                                <?php
                                $preset_amenities = ['WiFi', 'A/C', 'Projector', 'PA System', 'Running Water', 'Safety Kit', 'Whiteboard', 'Smart TV'];
                                $checked_amenities = [];
                                if ($edit_room && !empty($edit_room['amenities'])) {
                                    $decoded = json_decode($edit_room['amenities'], true);
                                    $checked_amenities = is_array($decoded) ? $decoded : [];
                                }
                                ?>
                                <div class="form-group">
                                    <label>Features <small class="field-optional">(optional)</small></label>
                                    <div class="rf-chips">
                                        <?php foreach ($preset_amenities as $am): ?>
                                            <label class="rf-chip">
                                                <input type="checkbox" name="amenities[]" value="<?php echo htmlspecialchars($am); ?>"
                                                    <?php echo in_array($am, $checked_amenities, true) ? 'checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars($am); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Rarely needed -->
                                <details class="rf-more" id="room-more-opts"
                                    <?php echo ($edit_room && (!empty($edit_room['floor_label']) || (int)$edit_room['sort_order'] > 0)) ? 'open' : ''; ?>>
                                    <summary>More options</summary>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="rf-floor-label">Floor name</label>
                                            <input type="text" name="floor_label" id="rf-floor-label" class="form-control-custom"
                                                value="<?php echo $edit_room ? htmlspecialchars($edit_room['floor_label'] ?? '') : ''; ?>"
                                                placeholder="e.g. Ground Floor">
                                        </div>
                                        <div class="form-group">
                                            <label for="rf-sort">Display order</label>
                                            <input type="number" name="sort_order" id="rf-sort" class="form-control-custom" min="0"
                                                value="<?php echo $edit_room ? (int)$edit_room['sort_order'] : 0; ?>">
                                        </div>
                                    </div>
                                    <p class="rf-hint">Floor name replaces the number in lists. Lower display order appears first.</p>
                                </details>

                                <!-- Row 6: Submit button -->
                                <button type="submit" id="room-form-submit-inner"
                                    name="<?php echo $edit_room ? 'update_room' : 'add_room'; ?>"
                                    class="btn-submit-form">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    <span id="room-submit-inner-label"><?php echo $edit_room ? 'Update Facility' : 'Add Facility'; ?></span>
                                </button>
                            </form>

                            <!-- Room actions zone (edit mode only): always in DOM, shown/hidden by JS.
                                 Lock / Unlock: Admin + Super Admin.  Archive: Super Admin only
                                 (the link is not even rendered for a regular Admin; the server checks too). -->
                            <?php $edit_locked = $edit_room && !empty($edit_room['is_locked']); ?>
                            <div class="rmod-archive-zone<?php echo $edit_room ? '' : ' hidden'; ?>" id="room-archive-zone">
                                <button type="button" id="room-lock-btn"
                                    class="pr-btn pr-btn-ghost pr-btn-sm"
                                    data-action="toggle-room-lock"
                                    data-lock="<?php echo $edit_locked ? '0' : '1'; ?>">
                                    <span class="material-symbols-outlined" id="room-lock-icon" style="font-size:15px;"><?php echo $edit_locked ? 'lock_open' : 'lock'; ?></span>
                                    <span id="room-lock-label"><?php echo $edit_locked ? 'Unlock Facility' : 'Lock Facility'; ?></span>
                                </button>
                                <?php if ($is_super_admin): ?>
                                    <a href="admin-dashboard.php?archive_room=<?php echo $edit_room ? (int)$edit_room['room_id'] : ''; ?>"
                                        id="room-archive-link"
                                        class="pr-btn pr-btn-danger pr-btn-sm">
                                        <span class="material-symbols-outlined" style="font-size:15px;">archive</span>
                                        Archive Facility
                                    </a>
                                <?php endif; ?>
                            </div>

                        </div><!-- /.form-card-body -->

                        <!-- Modal footer -->
                        <div class="rmod-footer">
                            <button type="button" class="pr-btn pr-btn-ghost" data-action="hide-room-form">Cancel</button>
                            <button type="submit" form="roomForm" id="room-form-submit-foot"
                                name="<?php echo $edit_room ? 'update_room' : 'add_room'; ?>"
                                class="pr-btn pr-btn-primary">
                                <span class="material-symbols-outlined" style="font-size:15px;">save</span>
                                <span id="room-submit-foot-label"><?php echo $edit_room ? 'Save Changes' : 'Add Facility'; ?></span>
                            </button>
                        </div>

                    </div><!-- /.eq-card.form-card -->
                </div><!-- /#room-form-wrap -->

                <!-- Tabs: main tabs on the left, Archived in its own container on the far right -->
                <div class="rm-tabbar">
                    <div class="pr-sub-tabs" id="rooms-toggle-wrap">
                        <button type="button" class="pr-sub-tab active" data-rooms-tab="rooms-active">
                            <span class="material-symbols-outlined rm-tab-ico" aria-hidden="true">meeting_room</span> Facilities
                        </button>
                        <button type="button" class="pr-sub-tab" data-rooms-tab="rooms-reservations">
                            <span class="material-symbols-outlined rm-tab-ico" aria-hidden="true">event_available</span> Reservations
                            <?php
                            $rm_resv_approved = count(array_filter($admin_room_reservations ?? [], function ($r) {
                                return ($r['status'] ?? '') === 'Approved';
                            }));
                            if ($rm_resv_approved > 0): ?>
                                <span class="pr-tab-badge" style="background:var(--text-light);"><?php echo $rm_resv_approved; ?></span>
                            <?php endif; ?>
                        </button>
                        <button type="button" class="pr-sub-tab" data-rooms-tab="rooms-issues">
                            <span class="material-symbols-outlined rm-tab-ico" aria-hidden="true">report_problem</span> Issues
                            <?php if (!empty($admin_room_issues_open)): ?>
                                <span class="pr-tab-badge"><?php echo (int)$admin_room_issues_open; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                    <div class="pr-sub-tabs rm-tabs-solo" id="rooms-archived-tab-wrap">
                        <button type="button" class="pr-sub-tab" data-rooms-tab="rooms-archived">
                            <span class="material-symbols-outlined rm-tab-ico" aria-hidden="true">inventory_2</span> Archived
                            <span class="pr-tab-badge" id="roomsArchivedBadge" style="background:var(--text-light);<?php echo empty($rooms_archived) ? 'display:none;' : ''; ?>"><?php echo count($rooms_archived); ?></span>
                        </button>
                    </div>
                </div>

                <!-- ROOMS -->
                <div class="rooms-sub-panel active" id="rooms-active-panel">
                    <?php
                    // Group active rooms by building -> floor. Shared with the AJAX responses in
                    // admin-rooms-functions.php via ps_prepare_rooms_registry_view().
                    $rv = ps_prepare_rooms_registry_view($rooms_buildings, $rooms_list);
                    $rooms_grouped         = $rv['grouped'];
                    $floors_by_building_js = $rv['floors_by_building'];
                    $first_building_id     = $rv['first_building_id'];
                    $building_accent       = $rv['accent'];
                    ?>

                    <?php if (empty($rooms_buildings)): ?>
                        <div class="pr-card rm-rooms-card">
                            <div class="pr-empty">No facilities yet. Click <strong>Add Facility</strong> to get started.</div>
                        </div>
                    <?php else: ?>
                        <div class="pr-card rm-rooms-card">
                            <div class="pr-card-header">
                                <h3><span class="material-symbols-outlined">meeting_room</span> All Facilities</h3>
                                <div class="rm-head-tools">
                                    <select id="roomStatusSelect" class="pr-filter-select" aria-label="Status">
                                        <option value="all">All statuses</option>
                                        <option value="Available">Available</option>
                                        <option value="Maintenance">Maintenance</option>
                                        <option value="Not Bookable">Not bookable</option>
                                    </select>
                                    <div class="pr-search-wrap">
                                        <span class="material-symbols-outlined">search</span>
                                        <input type="text" id="roomSearchInput" placeholder="Search facilities" autocomplete="off" aria-label="Search facilities">
                                    </div>
                                </div>
                            </div>
                            <!-- Filters: building + floor pills (status and search live in the header) -->
                            <div class="rm-toolbar" id="rmToolbar">
                                <div class="rm-tb-row">
                                    <div class="rm-bld-pills" id="rmBuildingPills" role="group" aria-label="Building"></div>
                                </div>
                                <div class="rm-tb-row" id="rmFloorRow">
                                    <div class="rm-floor-chips" id="rmFloorChips" role="group" aria-label="Floor"></div>
                                </div>
                                <!-- Plain dropdowns: the pills above read and write these. Hidden once JS runs. -->
                                <div class="rm-native-filters">
                                    <select id="roomBuildingSelect" class="pr-filter-select" aria-label="Building">
                                        <option value="all">All buildings</option>
                                        <?php foreach ($rooms_buildings as $bid => $b): ?>
                                            <option value="<?php echo (int)$bid; ?>" <?php echo $bid === $first_building_id ? ' selected' : ''; ?>>
                                                <?php echo htmlspecialchars($b['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select id="roomFloorSelect" class="pr-filter-select" aria-label="Floor">
                                        <option value="all">All floors</option>
                                        <?php foreach (($floors_by_building_js[$first_building_id] ?? []) as $f): ?>
                                            <option value="<?php echo (int)$f['num']; ?>"><?php echo htmlspecialchars($f['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- One floor-grouped list for every building (drawn as tiles) -->
                            <div class="pr-tbl-wrap">
                                <table class="pr-rooms-table rm-grid" id="roomsRegistryTable" aria-label="Facilities">
                                    <colgroup>
                                        <col>
                                        <col style="width:150px;">
                                        <col style="width:132px;">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>Facility</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php echo ps_render_rooms_tbody($rooms_buildings, $rooms_grouped, $building_accent); ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <script nonce="<?php echo $csp_nonce; ?>">
                            /* ── PUPSync Room Registry filter module ─────────────────
                               Search + Building/Floor/Status dropdowns. Depends on
                               PHP-emitted floor data, so it lives inline (same pattern
                               as the Room Schedule module below). */
                            (function() {
                                'use strict';
                                let FLOORS_BY_BUILDING = <?php echo json_encode($floors_by_building_js); ?>;
                                const DEFAULT_BUILDING = '<?php echo (int)$first_building_id; ?>';

                                const table = document.getElementById('roomsRegistryTable');
                                const bldSel = document.getElementById('roomBuildingSelect');
                                const flrSel = document.getElementById('roomFloorSelect');
                                const stSel = document.getElementById('roomStatusSelect');
                                const search = document.getElementById('roomSearchInput');
                                if (!table || !bldSel || !flrSel || !stSel || !search) return;

                                function populateFloorSelect(buildingId) {
                                    flrSel.innerHTML = '';
                                    const allOpt = document.createElement('option');
                                    allOpt.value = 'all';
                                    allOpt.textContent = 'All Floors';
                                    flrSel.appendChild(allOpt);

                                    if (buildingId === 'all') {
                                        flrSel.disabled = true;
                                        return;
                                    }
                                    flrSel.disabled = false;
                                    (FLOORS_BY_BUILDING[buildingId] || []).forEach(function(f) {
                                        const opt = document.createElement('option');
                                        opt.value = String(f.num);
                                        opt.textContent = f.label;
                                        flrSel.appendChild(opt);
                                    });
                                }

                                function applyFilters() {
                                    const bId = bldSel.value;
                                    const fNum = flrSel.value;
                                    const status = stSel.value;
                                    const query = (search.value || '').trim().toLowerCase();
                                    const searching = query.length > 0;

                                    let anyVisible = false;
                                    let currentDivider = null;
                                    let dividerHasVisible = false;

                                    table.querySelectorAll('tbody tr').forEach(function(row) {
                                        if (row.classList.contains('pr-floor-divider')) {
                                            if (currentDivider) currentDivider.style.display = dividerHasVisible ? '' : 'none';
                                            const dB = row.dataset.buildingId;
                                            const dF = row.dataset.floorNum;
                                            const inScope = searching || ((bId === 'all' || dB === bId) && (fNum === 'all' || dF === fNum));
                                            currentDivider = inScope ? row : null;
                                            if (!inScope) row.style.display = 'none';
                                            dividerHasVisible = false;
                                            return;
                                        }
                                        if (row.classList.contains('pr-no-match-row')) return;
                                        if (!row.classList.contains('pr-room-row')) return;

                                        const rB = row.dataset.roomBuildingId;
                                        const rF = row.dataset.roomFloorNum;
                                        const inScope = searching || ((bId === 'all' || rB === bId) && (fNum === 'all' || rF === fNum));
                                        const matchesStatus = status === 'all' || row.dataset.roomStatus === status;
                                        const matchesQuery = !searching || (row.dataset.roomName || '').toLowerCase().indexOf(query) !== -1;
                                        const visible = inScope && matchesStatus && matchesQuery;

                                        row.style.display = visible ? '' : 'none';
                                        if (visible) {
                                            dividerHasVisible = true;
                                            anyVisible = true;
                                        }
                                    });
                                    if (currentDivider) currentDivider.style.display = dividerHasVisible ? '' : 'none';

                                    const noMatch = table.querySelector('.pr-no-match-row');
                                    if (noMatch) noMatch.style.display = anyVisible ? 'none' : '';
                                }

                                bldSel.addEventListener('change', function() {
                                    populateFloorSelect(this.value);
                                    applyFilters();
                                });
                                flrSel.addEventListener('change', applyFilters);
                                stSel.addEventListener('change', applyFilters);
                                search.addEventListener('input', applyFilters);

                                populateFloorSelect(DEFAULT_BUILDING);
                                applyFilters();

                                /* ── Exposed for the AJAX module below: after an add/
                                   update/archive succeeds, drop the fresh <tbody> HTML
                                   in, refresh the Floor dropdown's options for whichever
                                   Building is currently selected (a new floor number may
                                   now exist), and re-apply the current search/filter
                                   state so the admin's view doesn't reset. ──────────── */
                                window.psRefreshRoomsRegistry = function(html, freshFloorsByBuilding) {
                                    if (freshFloorsByBuilding) {
                                        FLOORS_BY_BUILDING = freshFloorsByBuilding;
                                    }
                                    if (typeof html === 'string') {
                                        const tbody = table.querySelector('tbody');
                                        if (tbody) tbody.innerHTML = html;
                                    }
                                    if (bldSel.value !== 'all') {
                                        const keepFloor = flrSel.value;
                                        populateFloorSelect(bldSel.value);
                                        if ([...flrSel.options].some(function(o) {
                                                return o.value === keepFloor;
                                            })) {
                                            flrSel.value = keepFloor;
                                        }
                                    }
                                    applyFilters();
                                };
                            })();
                        </script>

                        <script nonce="<?php echo $csp_nonce; ?>">
                            /* ── PUPSync Room Registry: AJAX add/update/archive ──────
                               Progressive enhancement — the <form> and the Archive
                               <a> links still have their real method="POST"/href, so
                               they keep working with JS disabled. When JS runs, this
                               intercepts both, sends an extra ajax=1 flag, and swaps
                               in the fresh table HTML the server sends back instead
                               of letting the browser navigate at all. */
                            (function() {
                                'use strict';
                                const roomForm = document.getElementById('roomForm');
                                if (!roomForm) return;

                                function closeRoomModal() {
                                    const rfw = document.getElementById('room-form-wrap');
                                    const addRoomBtn = document.getElementById('addRoomBtn');
                                    if (rfw) rfw.classList.add('hidden');
                                    if (addRoomBtn) addRoomBtn.style.display = '';
                                    if (window.psResetRoomForm) window.psResetRoomForm();
                                    // If the modal was opened via ?edit_room=ID, drop that
                                    // param now — otherwise a later refresh would silently
                                    // reopen the modal on a room that's already saved.
                                    const params = new URLSearchParams(window.location.search);
                                    if (params.get('edit_room')) {
                                        params.delete('edit_room');
                                        const qs = params.toString();
                                        window.history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : ''));
                                    }
                                }

                                function afterMutation(json) {
                                    json = normalizeReply(json);
                                    if (json && json.status === 'success') {
                                        if (window.psRefreshRoomsRegistry) {
                                            window.psRefreshRoomsRegistry(json.html, json.floors_by_building);
                                        }
                                        refreshArchived(json);
                                        closeRoomModal();
                                        if (typeof showToast === 'function') showToast(json.message || 'Saved.');
                                    } else {
                                        if (typeof showToast === 'function') {
                                            showToast((json && json.message) || 'Something went wrong. Please try again.');
                                        }
                                    }
                                }

                                function onNetworkError() {
                                    if (typeof showToast === 'function') showToast('Network error — please try again.');
                                }

                                /* Server replies for a bad CSRF token use {error:...}; everything else {message:...}. */
                                function normalizeReply(json) {
                                    if (json && json.status === 'error' && !json.message && json.error) json.message = json.error;
                                    return json;
                                }

                                /* Keep the Archived tab (rows + count badge) in step with the server. */
                                function refreshArchived(json) {
                                    if (!json || typeof json.archived_html !== 'string') return;
                                    const body = document.getElementById('rooms-archived-body');
                                    if (body) body.innerHTML = json.archived_html;
                                    const badge = document.getElementById('roomsArchivedBadge');
                                    if (badge) {
                                        const n = parseInt(json.archived_count, 10) || 0;
                                        badge.textContent = n;
                                        badge.style.display = n > 0 ? '' : 'none';
                                    }
                                }

                                /* Lock button in the Edit modal: label + icon + next action. */
                                window.psSetLockButton = function(isLocked) {
                                    const btn = document.getElementById('room-lock-btn');
                                    if (!btn) return;
                                    btn.dataset.lock = isLocked ? '0' : '1';
                                    const ico = document.getElementById('room-lock-icon');
                                    const lbl = document.getElementById('room-lock-label');
                                    if (ico) ico.textContent = isLocked ? 'lock_open' : 'lock';
                                    if (lbl) lbl.textContent = isLocked ? 'Unlock Facility' : 'Lock Facility';
                                };

                                const submitBtns = ['room-form-submit-inner', 'room-form-submit-foot']
                                    .map(function(id) {
                                        return document.getElementById(id);
                                    })
                                    .filter(Boolean);

                                roomForm.addEventListener('submit', function(e) {
                                    e.preventDefault();

                                    const fd = new FormData(roomForm);
                                    // A submit event's .submitter is the exact button that
                                    // was clicked — needed here because FormData(form) alone
                                    // does not include a submit button's name/value (the
                                    // browser only adds that for a real, non-JS submit).
                                    // Both buttons carry name="add_room" or "update_room"
                                    // depending on the modal's current mode, so this is how
                                    // the server knows which action to run.
                                    const clicked = e.submitter || submitBtns[0];
                                    if (clicked && clicked.name) fd.set(clicked.name, clicked.value || '1');
                                    fd.set('ajax', '1');

                                    submitBtns.forEach(function(b) {
                                        b.disabled = true;
                                    });

                                    fetch('admin-dashboard.php', {
                                            method: 'POST',
                                            body: fd,
                                            credentials: 'same-origin'
                                        })
                                        .then(function(res) {
                                            return res.json();
                                        })
                                        .then(function(json) {
                                            submitBtns.forEach(function(b) {
                                                b.disabled = false;
                                            });
                                            afterMutation(json);
                                        })
                                        .catch(function() {
                                            submitBtns.forEach(function(b) {
                                                b.disabled = false;
                                            });
                                            onNetworkError();
                                        });
                                });

                                /* Archive: both the per-row icon (in the table) and the
                                   modal's own Archive Room link share the same href
                                   pattern, so one delegated listener catches both. */
                                document.addEventListener('click', function(e) {
                                    const link = e.target.closest('a[href*="archive_room="]');
                                    if (!link) return;
                                    e.preventDefault();
                                    if (!confirm('Archive this facility? It will be hidden from the registry and can be restored later.')) return;

                                    const href = link.getAttribute('href');
                                    const url = href + (href.indexOf('?') !== -1 ? '&' : '?') + 'ajax=1';

                                    fetch(url, {
                                            credentials: 'same-origin'
                                        })
                                        .then(function(res) {
                                            return res.json();
                                        })
                                        .then(afterMutation)
                                        .catch(onNetworkError);
                                });

                                /* Restore: intercept restore_room= links in the Archived sub-panel. */
                                document.addEventListener('click', function(e) {
                                    const link = e.target.closest('a[href*="restore_room="]');
                                    if (!link) return;
                                    e.preventDefault();
                                    if (!confirm('Restore this facility? It will reappear in the active registry.')) return;

                                    const href = link.getAttribute('href');
                                    const url = href + (href.indexOf('?') !== -1 ? '&' : '?') + 'ajax=1';

                                    fetch(url, {
                                            credentials: 'same-origin'
                                        })
                                        .then(function(res) {
                                            return res.json();
                                        })
                                        .then(function(json) {
                                            json = normalizeReply(json);
                                            if (json && json.status === 'success') {
                                                if (window.psRefreshRoomsRegistry) {
                                                    window.psRefreshRoomsRegistry(json.html, json.floors_by_building);
                                                }
                                                refreshArchived(json);
                                                if (typeof showToast === 'function') showToast(json.message || 'Facility restored.');
                                                if (typeof switchRoomsTab === 'function') switchRoomsTab('rooms-active');
                                            } else {
                                                if (typeof showToast === 'function') showToast((json && json.message) || 'Could not restore facility.');
                                            }
                                        })
                                        .catch(onNetworkError);
                                });

                                /* Lock / Unlock (Edit modal). The modal stays open so unsaved edits
                                   are not lost; the tiles behind it refresh straight away. */
                                document.addEventListener('click', function(e) {
                                    const btn = e.target.closest('[data-action="toggle-room-lock"]');
                                    if (!btn) return;
                                    const idEl = document.getElementById('room-form-id');
                                    const roomId = idEl ? idEl.value : '';
                                    if (!roomId) return;
                                    const lock = btn.dataset.lock === '1' ? '1' : '0';
                                    const tok = roomForm.querySelector('input[name="csrf_token"]');

                                    const fd = new FormData();
                                    fd.set('toggle_room_lock', '1');
                                    fd.set('room_id', roomId);
                                    fd.set('lock', lock);
                                    fd.set('ajax', '1');
                                    if (tok) fd.set('csrf_token', tok.value);

                                    btn.disabled = true;
                                    fetch('admin-dashboard.php', {
                                            method: 'POST',
                                            body: fd,
                                            credentials: 'same-origin'
                                        })
                                        .then(function(res) {
                                            return res.json();
                                        })
                                        .then(function(json) {
                                            btn.disabled = false;
                                            json = normalizeReply(json);
                                            if (json && json.status === 'success') {
                                                if (window.psRefreshRoomsRegistry) {
                                                    window.psRefreshRoomsRegistry(json.html, json.floors_by_building);
                                                }
                                                refreshArchived(json);
                                                window.psSetLockButton(lock === '1');
                                                if (typeof showToast === 'function') showToast(json.message || (lock === '1' ? 'Facility locked.' : 'Facility unlocked.'));
                                            } else if (typeof showToast === 'function') {
                                                showToast((json && json.message) || 'Could not change the lock. Please try again.');
                                            }
                                        })
                                        .catch(function() {
                                            btn.disabled = false;
                                            onNetworkError();
                                        });
                                });

                                /* Permanent delete (Archived tab, Super Admin). The button only opens
                                   a confirmation dialog; nothing is sent until the box is ticked and
                                   "Delete permanently" is pressed. The server re-checks everything. */
                                /* The dialog lives at the end of <body> (after this script runs), so it
                                   is looked up when needed, never once at load. */
                                let delRoomId = '';
                                const delEl = function(id) {
                                    return document.getElementById(id);
                                };

                                function closeDeleteModal() {
                                    const m = delEl('roomDeleteConfirm');
                                    if (m) m.classList.add('hidden');
                                    delRoomId = '';
                                }

                                document.addEventListener('click', function(e) {
                                    const m = delEl('roomDeleteConfirm');
                                    if (!m) return; // regular Admins never get the dialog

                                    const openBtn = e.target.closest('[data-action="delete-room-permanent"]');
                                    if (openBtn) {
                                        delRoomId = openBtn.dataset.roomId || '';
                                        const n = parseInt(openBtn.dataset.reservations, 10) || 0;
                                        delEl('rdc-room-name').textContent = openBtn.dataset.roomName || '';
                                        delEl('rdc-resv').textContent =
                                            n === 0 ? 'No reservation records' : (n === 1 ? '1 reservation record' : n + ' reservation records');
                                        delEl('rdc-ack').checked = false;
                                        delEl('rdc-confirm').disabled = true;
                                        m.classList.remove('hidden');
                                        delEl('rdc-ack').focus();
                                        return;
                                    }
                                    if (e.target.closest('[data-action="close-room-delete"]') || e.target === m) {
                                        closeDeleteModal();
                                        return;
                                    }

                                    /* Confirm: only acts once the box is ticked. */
                                    const go = e.target.closest('#rdc-confirm');
                                    if (!go) return;
                                    const ack = delEl('rdc-ack');
                                    if (!ack.checked || !delRoomId || go.disabled) return;

                                    const tok = roomForm.querySelector('input[name="csrf_token"]');
                                    const fd = new FormData();
                                    fd.set('delete_room_permanently', '1');
                                    fd.set('room_id', delRoomId);
                                    fd.set('confirm', 'yes');
                                    fd.set('ajax', '1');
                                    if (tok) fd.set('csrf_token', tok.value);

                                    go.disabled = true;
                                    fetch('admin-dashboard.php', {
                                            method: 'POST',
                                            body: fd,
                                            credentials: 'same-origin'
                                        })
                                        .then(function(res) {
                                            return res.json();
                                        })
                                        .then(function(json) {
                                            json = normalizeReply(json);
                                            if (json && json.status === 'success') {
                                                if (window.psRefreshRoomsRegistry) {
                                                    window.psRefreshRoomsRegistry(json.html, json.floors_by_building);
                                                }
                                                refreshArchived(json);
                                                closeDeleteModal();
                                                if (typeof showToast === 'function') showToast(json.message || 'Facility permanently deleted.');
                                            } else {
                                                go.disabled = !ack.checked;
                                                if (typeof showToast === 'function') showToast((json && json.message) || 'Could not delete the facility.');
                                            }
                                        })
                                        .catch(function() {
                                            go.disabled = !ack.checked;
                                            onNetworkError();
                                        });
                                });

                                document.addEventListener('keydown', function(e) {
                                    const m = delEl('roomDeleteConfirm');
                                    if (e.key === 'Escape' && m && !m.classList.contains('hidden')) closeDeleteModal();
                                });

                                document.addEventListener('change', function(e) {
                                    if (e.target && e.target.id === 'rdc-ack') {
                                        const go = delEl('rdc-confirm');
                                        if (go) go.disabled = !e.target.checked;
                                    }
                                });
                            })();
                        </script>
                    <?php endif; ?>
                </div><!-- /#rooms-active-panel -->

                <!-- RESERVATIONS -->
                <div class="rooms-sub-panel" id="rooms-reservations-panel">
                    <?php
                    // Table rows start in "most recent request first" order (the sort dropdown re-orders them live).
                    $rr_rows = $admin_room_reservations ?? [];
                    usort($rr_rows, function ($a, $b) {
                        $ta = !empty($a['request_date']) ? (int)strtotime($a['request_date']) : 0;
                        $tb = !empty($b['request_date']) ? (int)strtotime($b['request_date']) : 0;
                        return ($tb <=> $ta) ?: ((int)$b['id'] <=> (int)$a['id']);
                    });
                    // Waiting = reservations for a later date (any admin decides them) plus adviser requests with an
                    // attached letter (Super Admin only - the query above never sends those to other admins).
                    $rr_is_super = !empty($ps_rooms_can_view_sensitive_adviser_requests);
                    ?>
                    <div class="pr-card">
                        <div class="pr-card-header">
                            <h3><span class="material-symbols-outlined">calendar_month</span> Reservations</h3>
                            <div class="pr-search-wrap">
                                <span class="material-symbols-outlined">search</span>
                                <input type="text" id="rrSearch" placeholder="Search facility or name" autocomplete="off" aria-label="Search reservations">
                            </div>
                        </div>
                        <div class="rr-status-bar">
                            <div class="rr-tabs" id="rrTabs" role="group" aria-label="Filter reservations by status">
                                <button type="button" class="rm-chip rr-tab" data-rr-tab="all" aria-pressed="true">
                                    All <span class="rr-tab-count"></span>
                                </button>
                                <button type="button" class="rm-chip rr-tab rr-active-chip" data-rr-tab="active" aria-pressed="false">
                                    <span class="material-symbols-outlined" aria-hidden="true">schedule</span> Active <span class="rr-tab-count"></span>
                                </button>
                                <button type="button" class="rm-chip rr-tab" data-rr-tab="Waiting" aria-pressed="false">
                                    Waiting <span class="rr-tab-count"></span>
                                </button>
                                <button type="button" class="rm-chip rr-tab" data-rr-tab="Approved" aria-pressed="false">
                                    Approved <span class="rr-tab-count"></span>
                                </button>
                                <button type="button" class="rm-chip rr-tab" data-rr-tab="Cancelled" aria-pressed="false">
                                    Cancelled <span class="rr-tab-count"></span>
                                </button>
                                <button type="button" class="rm-chip rr-tab" data-rr-tab="Declined" aria-pressed="false">
                                    Declined <span class="rr-tab-count"></span>
                                </button>
                            </div>
                            <div class="rr-sort">
                                <select id="rrSort" class="pr-filter-select" aria-label="Sort reservations by">
                                    <option value="recent" selected>Recent requests</option>
                                    <option value="date">Reservation date</option>
                                </select>
                                <button type="button" class="rr-sort-dir" id="rrSortDir" data-dir="desc"
                                    title="Newest requests first (click to reverse)"
                                    aria-label="Sort direction: newest requests first. Click to reverse.">
                                    <span class="material-symbols-outlined" aria-hidden="true">arrow_downward</span>
                                </button>
                            </div>
                        </div>
                        <div class="pr-tbl-wrap">
                            <table class="pr-table rm-table" id="rrTable">
                                <thead>
                                    <tr>
                                        <th>Facility</th>
                                        <th>When</th>
                                        <th>Requested by</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rr_rows)): ?>
                                        <tr>
                                            <td colspan="5" class="rm-empty-cell">No reservations yet.</td>
                                        </tr>
                                        <?php else: foreach ($rr_rows as $ar):
                                            $ar_badge = 'ps-badge--active';
                                            if ($ar['status'] === 'Declined')  $ar_badge = 'ps-badge--overdue';
                                            if ($ar['status'] === 'Cancelled') $ar_badge = 'ps-badge--returned';
                                            if ($ar['status'] === 'Waiting')   $ar_badge = 'ps-badge--waiting';
                                            // Every Waiting row this admin can see is theirs to decide: letter-based adviser
                                            // requests are only ever loaded for a Super Admin, and the API enforces the same rule.
                                            $ar_reviewable = $ar['status'] === 'Waiting';

                                            $ar_type    = ps_submitted_as_label($ar['submitted_as']);
                                            $ar_is_stud = $ar['submitted_as'] === 'student' && !empty($ar['submitted_by_name']);
                                            $ar_name    = $ar_is_stud ? $ar['submitted_by_name'] : $ar['faculty_name'];
                                            $ar_sub     = $ar_is_stud ? 'Student via ' . $ar['faculty_name'] : $ar_type;
                                            $ar_doc     = ps_doc_info($ar['document_path'] ?? '');
                                            $ar_where   = $ar['floor_label'] . ', ' . $ar['building_name'];
                                            $ar_time    = $ar['start_fmt'] . ' - ' . $ar['end_fmt'];
                                            // Cancellable: Approved AND the reservation end hasn't passed yet
                                            $ar_end_ts  = !empty($ar['end_time']) ? strtotime($ar['reservation_date'] . ' ' . $ar['end_time']) : 0;
                                            $ar_cancellable = $ar['status'] === 'Approved' && $ar_end_ts > time();
                                        ?>
                                            <tr class="ps-rr-row<?php echo $ar_doc ? ' has-doc' : ''; ?>"
                                                data-rr-id="<?php echo (int)$ar['id']; ?>"
                                                data-room="<?php echo htmlspecialchars($ar['room_name']); ?>"
                                                data-where="<?php echo htmlspecialchars($ar_where); ?>"
                                                data-date="<?php echo date('M d, Y', strtotime($ar['reservation_date'])); ?>"
                                                data-time="<?php echo htmlspecialchars($ar_time); ?>"
                                                data-who="<?php echo htmlspecialchars($ar_name); ?>"
                                                data-who-sub="<?php echo htmlspecialchars($ar_sub); ?>"
                                                data-type="<?php echo htmlspecialchars($ar_type); ?>"
                                                data-purpose="<?php echo htmlspecialchars($ar['purpose'] ?? ''); ?>"
                                                data-attendees="<?php echo (int)($ar['attendees'] ?? 0); ?>"
                                                data-status="<?php echo htmlspecialchars($ar['status']); ?>"
                                                data-reason="<?php echo htmlspecialchars($ar['reason'] ?? ''); ?>"
                                                data-notes="<?php echo htmlspecialchars($ar['notes'] ?? ''); ?>"
                                                data-submitted="<?php echo !empty($ar['request_date']) ? date('M d, Y g:i A', strtotime($ar['request_date'])) : ''; ?>"
                                                data-doc-url="<?php echo $ar_doc ? htmlspecialchars($ar_doc['url']) : ''; ?>"
                                                data-doc-open-url="<?php echo $ar_doc ? htmlspecialchars($ar_doc['open_url']) : ''; ?>"
                                                data-doc-name="<?php echo $ar_doc ? htmlspecialchars($ar_doc['name']) : ''; ?>"
                                                data-doc-kind="<?php echo $ar_doc ? $ar_doc['kind'] : ''; ?>"
                                                data-iso-date="<?php echo htmlspecialchars($ar['reservation_date']); ?>"
                                                data-start-time="<?php echo htmlspecialchars($ar['start_time']); ?>"
                                                data-end-time="<?php echo htmlspecialchars($ar['end_time']); ?>"
                                                data-arb-rule="<?php echo htmlspecialchars($ar['arb_rule'] ?? ''); ?>"
                                                data-arb-reason="<?php echo htmlspecialchars($ar['arb_reason'] ?? ''); ?>"
                                                data-submitted-ts="<?php echo !empty($ar['request_date']) ? (int)strtotime($ar['request_date']) : 0; ?>"
                                                data-can-review="<?php echo $ar_reviewable ? '1' : '0'; ?>"
                                                data-cancellable="<?php echo $ar_cancellable ? '1' : '0'; ?>">
                                                <td>
                                                    <div class="rm-main"><?php echo htmlspecialchars($ar['room_name']); ?></div>
                                                    <div class="rm-subline"><?php echo htmlspecialchars($ar_where); ?></div>
                                                </td>
                                                <td>
                                                    <div class="rm-main"><?php echo date('M d, Y', strtotime($ar['reservation_date'])); ?></div>
                                                    <div class="rm-subline rm-nowrap"><?php echo htmlspecialchars($ar_time); ?></div>
                                                </td>
                                                <td>
                                                    <div class="rm-main">
                                                        <?php echo htmlspecialchars($ar_name); ?>
                                                        <?php if ($ar_doc): ?>
                                                            <a class="rm-clip" href="<?php echo htmlspecialchars($ar_doc['open_url']); ?>" target="_blank" rel="noopener noreferrer"
                                                                title="Open attachment: <?php echo htmlspecialchars($ar_doc['name']); ?>">
                                                                <span class="material-symbols-outlined">attach_file</span>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="rm-subline"><?php echo htmlspecialchars($ar_sub); ?></div>
                                                </td>
                                                <td><span class="ps-badge ps-badge--dot <?php echo $ar_badge; ?>"><?php echo htmlspecialchars($ar['status']); ?></span></td>
                                                <td>
                                                    <div class="rm-actions">
                                                        <?php if ($ar_cancellable): ?>
                                                            <button type="button" class="pr-tbl-btn cancel-r btn-cancel-rr-admin"
                                                                data-action="admin-cancel-reservation"
                                                                data-rr-id="<?php echo (int)$ar['id']; ?>"
                                                                data-room-name="<?php echo htmlspecialchars($ar['room_name']); ?>"
                                                                data-faculty-name="<?php echo htmlspecialchars($ar['faculty_name']); ?>"
                                                                title="Cancel this reservation">Cancel</button>
                                                        <?php endif; ?>
                                                        <button type="button" class="pr-tbl-btn review" data-rr-detail><?php echo $ar_reviewable ? 'Review' : 'View'; ?></button>
                                                    </div>
                                                </td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                    <tr id="rrNoMatch" style="display:none;">
                                        <td colspan="5" class="rm-empty-cell">No reservations match.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="inv-pager" id="rrPager" hidden>
                            <span class="inv-pager-info" id="rrPagerInfo"></span>
                            <div class="inv-pager-ctl" id="rrPagerCtl">
                                <button type="button" class="inv-pager-btn" id="rrPgPrev" aria-label="Previous page">
                                    <span class="material-symbols-outlined">chevron_left</span>
                                </button>
                                <div class="inv-pager-nums" id="rrPgNums"></div>
                                <button type="button" class="inv-pager-btn" id="rrPgNext" aria-label="Next page">
                                    <span class="material-symbols-outlined">chevron_right</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <script nonce="<?php echo $csp_nonce; ?>">
                        (function() {
                            'use strict';
                            var tbl = document.getElementById('rrTable');
                            var q = document.getElementById('rrSearch');
                            var sortSel = document.getElementById('rrSort');
                            var dirBtn = document.getElementById('rrSortDir');
                            var tabsWrap = document.getElementById('rrTabs');
                            var none = document.getElementById('rrNoMatch');
                            var pager = document.getElementById('rrPager');
                            var pgInfo = document.getElementById('rrPagerInfo');
                            var pgCtl = document.getElementById('rrPagerCtl');
                            var pgPrev = document.getElementById('rrPgPrev');
                            var pgNext = document.getElementById('rrPgNext');
                            var pgNums = document.getElementById('rrPgNums');
                            if (!tbl || !q || !sortSel || !dirBtn || !tabsWrap || !pager) return;
                            var PAGE_SIZE = 15;
                            var tbody = tbl.tBodies[0];
                            var tab = 'all'; // all | active | Waiting | Approved | Cancelled | Declined
                            var dir = 'desc'; // desc = newest / latest first, asc = oldest / earliest first
                            var page = 1;

                            function manilaNow() {
                                try {
                                    var parts = new Intl.DateTimeFormat('en-CA', {
                                        timeZone: 'Asia/Manila',
                                        year: 'numeric',
                                        month: '2-digit',
                                        day: '2-digit',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        hourCycle: 'h23'
                                    }).formatToParts(new Date());
                                    var g = function(t) {
                                        return (parts.find(function(p) {
                                            return p.type === t;
                                        }) || {}).value;
                                    };
                                    return {
                                        iso: g('year') + '-' + g('month') + '-' + g('day'),
                                        min: (+g('hour')) * 60 + (+g('minute'))
                                    };
                                } catch (e) {
                                    var d = new Date();
                                    return {
                                        iso: d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2),
                                        min: d.getHours() * 60 + d.getMinutes()
                                    };
                                }
                            }

                            function parseMin(v) {
                                var m = /^\s*(\d{1,2}):(\d{2})(?::\d{2})?\s*([AaPp][Mm])?\s*$/.exec(v || '');
                                if (!m) return null;
                                var h = +m[1],
                                    mi = +m[2],
                                    ap = (m[3] || '').toUpperCase();
                                if (ap === 'PM' && h !== 12) h += 12;
                                if (ap === 'AM' && h === 12) h = 0;
                                return h * 60 + mi;
                            }

                            function rows() {
                                return Array.prototype.slice.call(tbody.querySelectorAll('tr.ps-rr-row'));
                            }

                            function isActiveNow(d, now) {
                                var sMin = parseMin(d.startTime);
                                var eMin = parseMin(d.endTime);
                                return d.status === 'Approved' && d.isoDate === now.iso &&
                                    sMin !== null && eMin !== null &&
                                    sMin <= now.min && eMin > now.min;
                            }

                            /* ── Sorting ─────────────────────────────────────────
                               Dropdown picks WHAT to sort by, the arrow button picks the direction:
                               recent : submission time of the request
                               date   : reservation date + start time */
                            function startKey(d) {
                                return (d.isoDate || '') + ' ' + (d.startTime || '');
                            }

                            function comparator() {
                                var sign = dir === 'asc' ? 1 : -1;
                                var byDate = sortSel.value === 'date';
                                return function(a, b) {
                                    var x = a.dataset,
                                        y = b.dataset,
                                        r;
                                    if (byDate) {
                                        var kx = startKey(x),
                                            ky = startKey(y);
                                        r = kx < ky ? -1 : (kx > ky ? 1 : 0);
                                    } else {
                                        r = (+x.submittedTs || 0) - (+y.submittedTs || 0);
                                    }
                                    if (!r) r = (+x.rrId || 0) - (+y.rrId || 0);
                                    return r * sign;
                                };
                            }

                            function sortRows() {
                                var list = rows().sort(comparator());
                                var frag = document.createDocumentFragment();
                                list.forEach(function(r) {
                                    frag.appendChild(r);
                                });
                                tbody.insertBefore(frag, none || null);
                            }

                            function syncDirBtn() {
                                var asc = dir === 'asc';
                                var label = sortSel.value === 'date' ?
                                    (asc ? 'Earliest first' : 'Latest first') :
                                    (asc ? 'Oldest requests first' : 'Newest requests first');
                                dirBtn.setAttribute('data-dir', dir);
                                dirBtn.title = label + ' (click to reverse)';
                                dirBtn.setAttribute('aria-label', 'Sort direction: ' + label.toLowerCase() + '. Click to reverse.');
                                var ico = dirBtn.querySelector('.material-symbols-outlined');
                                if (ico) ico.textContent = asc ? 'arrow_upward' : 'arrow_downward';
                            }

                            /* ── Tab counts (independent of the search box) ─────── */
                            function updateCounts() {
                                var now = manilaNow();
                                var c = {
                                    all: 0,
                                    active: 0,
                                    Waiting: 0,
                                    Approved: 0,
                                    Cancelled: 0,
                                    Declined: 0
                                };
                                rows().forEach(function(r) {
                                    var d = r.dataset;
                                    c.all++;
                                    if (typeof c[d.status] === 'number') c[d.status]++;
                                    if (isActiveNow(d, now)) c.active++;
                                });
                                tabsWrap.querySelectorAll('[data-rr-tab]').forEach(function(b) {
                                    var key = b.getAttribute('data-rr-tab');
                                    var span = b.querySelector('.rr-tab-count');
                                    if (!span) return;
                                    span.textContent = c[key];
                                    // Waiting is the to-do queue: highlight it while something needs a decision
                                    span.classList.toggle('has', key === 'Waiting' && c[key] > 0);
                                });
                            }

                            /* ── 1 … 4 5 6 … 12 : first, last and the current page ±1 ── */
                            function pageList(total, current) {
                                var keep = [1, total, current - 1, current, current + 1];
                                var list = [],
                                    last = 0;
                                keep.filter(function(n, i) {
                                        return n >= 1 && n <= total && keep.indexOf(n) === i;
                                    })
                                    .sort(function(x, y) {
                                        return x - y;
                                    })
                                    .forEach(function(n) {
                                        if (n - last > 1) list.push('gap');
                                        list.push(n);
                                        last = n;
                                    });
                                return list;
                            }

                            function renderPager(total, pages, start, end) {
                                pager.hidden = total === 0;
                                pgInfo.textContent = total ? 'Showing ' + (start + 1) + '\u2013' + end + ' of ' + total : 'No results';
                                pgCtl.hidden = pages <= 1;
                                pgPrev.disabled = page <= 1;
                                pgNext.disabled = page >= pages;
                                pgNums.textContent = '';
                                pageList(pages, page).forEach(function(n) {
                                    if (n === 'gap') {
                                        var gap = document.createElement('span');
                                        gap.className = 'inv-pager-gap';
                                        gap.textContent = '\u2026';
                                        pgNums.appendChild(gap);
                                        return;
                                    }
                                    var btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'inv-pager-btn' + (n === page ? ' is-active' : '');
                                    btn.dataset.page = String(n);
                                    btn.textContent = String(n);
                                    btn.setAttribute('aria-label', 'Page ' + n);
                                    if (n === page) btn.setAttribute('aria-current', 'page');
                                    pgNums.appendChild(btn);
                                });
                            }

                            /* ── Filtering (active tab + search), then 15 rows per page ── */
                            function applyFilter() {
                                var s = (q.value || '').trim().toLowerCase();
                                var now = manilaNow();
                                var all = rows();
                                var hits = all.filter(function(r) {
                                    var d = r.dataset;
                                    var hay = ((d.room || '') + ' ' + (d.who || '') + ' ' + (d.whoSub || '') + ' ' + (d.purpose || '')).toLowerCase();
                                    var show = tab === 'all' ? true : (tab === 'active' ? isActiveNow(d, now) : d.status === tab);
                                    return show && (!s || hay.indexOf(s) !== -1);
                                });
                                var total = hits.length;
                                var pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
                                page = Math.min(Math.max(page, 1), pages);
                                var start = (page - 1) * PAGE_SIZE;
                                var end = Math.min(start + PAGE_SIZE, total);
                                var visible = hits.slice(start, end);
                                all.forEach(function(r) {
                                    r.style.display = visible.indexOf(r) !== -1 ? '' : 'none';
                                });
                                var hasRows = all.length > 0;
                                if (none) {
                                    none.style.display = (hasRows && total === 0) ? '' : 'none';
                                    var cell = none.firstElementChild;
                                    if (cell) cell.textContent = (tab === 'Waiting' && !s) ? 'No reservations are waiting for review.' : 'No reservations match.';
                                }
                                renderPager(total, pages, start, end);
                            }

                            function goToPage(n) {
                                page = n;
                                applyFilter();
                                // keep the top of the table in view after jumping pages
                                var card = tbl.closest('.pr-card');
                                if (card && card.scrollIntoView && card.getBoundingClientRect().top < 0) card.scrollIntoView({
                                    block: 'start',
                                    behavior: 'smooth'
                                });
                            }

                            tabsWrap.addEventListener('click', function(e) {
                                var b = e.target.closest('[data-rr-tab]');
                                if (!b) return;
                                tab = b.getAttribute('data-rr-tab');
                                tabsWrap.querySelectorAll('[data-rr-tab]').forEach(function(x) {
                                    x.setAttribute('aria-pressed', x === b ? 'true' : 'false');
                                });
                                page = 1;
                                applyFilter();
                            });
                            q.addEventListener('input', function() {
                                page = 1;
                                applyFilter();
                            });
                            sortSel.addEventListener('change', function() {
                                page = 1;
                                syncDirBtn();
                                sortRows();
                                applyFilter();
                            });
                            dirBtn.addEventListener('click', function() {
                                dir = dir === 'asc' ? 'desc' : 'asc';
                                page = 1;
                                syncDirBtn();
                                sortRows();
                                applyFilter();
                            });
                            pgPrev.addEventListener('click', function() {
                                goToPage(page - 1);
                            });
                            pgNext.addEventListener('click', function() {
                                goToPage(page + 1);
                            });
                            pgNums.addEventListener('click', function(e) {
                                var b = e.target.closest('[data-page]');
                                if (b) goToPage(parseInt(b.getAttribute('data-page'), 10));
                            });
                            // Fired after a Waiting request is approved/declined from the drawer
                            document.addEventListener('rr:rows-changed', function() {
                                sortRows();
                                updateCounts();
                                applyFilter();
                            });

                            syncDirBtn();
                            sortRows(); // also honours a sort value the browser restored on refresh
                            updateCounts();
                            applyFilter();
                        })();
                    </script>
                </div><!-- /#rooms-reservations-panel -->

                <!-- ISSUES -->
                <div class="rooms-sub-panel" id="rooms-issues-panel">
                    <div class="pr-card">
                        <div class="pr-card-header">
                            <h3><span class="material-symbols-outlined">report_problem</span> Facility Issues</h3>
                        </div>
                        <div class="pr-tbl-wrap">
                            <table class="pr-table rm-table">
                                <thead>
                                    <tr>
                                        <th>Facility</th>
                                        <th>Report</th>
                                        <th>Status</th>
                                        <th><span class="rm-sr">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($admin_room_issues)): ?>
                                        <tr>
                                            <td colspan="4" class="rm-empty-cell">No issue reports yet.</td>
                                        </tr>
                                        <?php else: foreach ($admin_room_issues as $issue):
                                            $iss_pill = 'pr-pill-open';
                                            if ($issue['status'] === 'Resolved')  $iss_pill = 'pr-pill-resolved';
                                            if ($issue['status'] === 'Dismissed') $iss_pill = 'pr-pill-dismissed';
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="rm-main"><?php echo htmlspecialchars($issue['room_name']); ?></div>
                                                    <div class="rm-subline"><?php echo htmlspecialchars($issue['floor_label'] . ', ' . $issue['building_name']); ?></div>
                                                </td>
                                                <td>
                                                    <div class="rm-clamp" title="<?php echo htmlspecialchars($issue['description']); ?>"><?php echo htmlspecialchars($issue['description']); ?></div>
                                                    <div class="rm-subline"><?php echo htmlspecialchars($issue['reported_by_name']); ?> &middot; <?php echo date('M d, Y', strtotime($issue['created_at'])); ?></div>
                                                    <?php if (!empty($issue['admin_notes'])): ?>
                                                        <div class="rm-subline rm-note" title="<?php echo htmlspecialchars($issue['admin_notes']); ?>">Note: <?php echo htmlspecialchars(mb_substr($issue['admin_notes'], 0, 60)); ?><?php echo mb_strlen($issue['admin_notes']) > 60 ? '&hellip;' : ''; ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="pr-pill <?php echo $iss_pill; ?>"><?php echo htmlspecialchars($issue['status']); ?></span></td>
                                                <td>
                                                    <?php if ($issue['status'] === 'Open'): ?>
                                                        <div class="rm-actions">
                                                            <button type="button" class="pr-tbl-btn review"
                                                                data-action="open-issue-review"
                                                                data-issue-id="<?php echo (int)$issue['id']; ?>"
                                                                data-room-name="<?php echo htmlspecialchars($issue['room_name']); ?>"
                                                                data-reporter="<?php echo htmlspecialchars($issue['reported_by_name']); ?>"
                                                                data-description="<?php echo htmlspecialchars($issue['description']); ?>"
                                                                title="Review this issue report">Review</button>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /#rooms-issues-panel -->

                <!-- ARCHIVED -->
                <div class="rooms-sub-panel" id="rooms-archived-panel">
                    <div class="pr-card">
                        <div class="pr-card-header">
                            <h3><span class="material-symbols-outlined">archive</span> Archived Facilities</h3>
                        </div>
                        <div class="pr-tbl-wrap">
                            <table class="pr-table rm-table">
                                <thead>
                                    <tr>
                                        <th>Facility</th>
                                        <th><span class="rm-sr">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody id="rooms-archived-body">
                                    <?php echo ps_render_archived_tbody($rooms_archived, $is_super_admin); ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /#rooms-archived-panel -->

                <!-- /#rooms-issues-panel (end of sub-panels) -->




                <!-- ── Rooms: JSON data + schedule JS ──────────────────── -->
                <?php
                // Encode rooms list for schedule JS
                $ps_rooms_json = array_map(function ($r) {
                    return [
                        'room_id'    => (int)$r['room_id'],
                        'room_name'  => $r['room_name'],
                        'campus'     => $r['campus_name'],
                        'building'   => $r['building_name'],
                        'floor'      => !empty($r['floor_label']) ? $r['floor_label'] : ($r['floor_number'] . 'F'),
                    ];
                }, $rooms_list ?? []);

                // Approved reservations only (these appear on the schedule grid)
                $ps_rsvp_json = array_values(array_filter(
                    array_map(function ($ar) {
                        return [
                            'id'           => (int)$ar['id'],
                            'room_id'      => isset($ar['room_id']) ? (int)$ar['room_id'] : null,
                            'room_name'    => $ar['room_name'],
                            'date'         => $ar['reservation_date'],
                            'start_fmt'    => $ar['start_fmt'],
                            'end_fmt'      => $ar['end_fmt'],
                            'label'        => !empty($ar['purpose']) ? $ar['purpose'] : $ar['faculty_name'],
                            'purpose'      => (string)($ar['purpose'] ?? ''),
                            'faculty'      => $ar['faculty_name'],
                            'submitted_as' => (string)($ar['submitted_as'] ?? ''),
                            'submitted_by' => (string)($ar['submitted_by_name'] ?? ''),
                            'status'       => $ar['status'],
                        ];
                    }, $admin_room_reservations ?? []),
                    function ($ar) {
                        return $ar['status'] === 'Approved';
                    }
                ));
                ?>
                <script nonce="<?php echo $csp_nonce; ?>">
                    /* ── PUPSync Room Schedule module ──────────────────────── */
                    (function() {
                        'use strict';

                        /* — Data injected from PHP — */
                        const PS_ROOMS = <?php echo json_encode(array_values($ps_rooms_json)); ?>;
                        const PS_RSVP = <?php echo json_encode($ps_rsvp_json); ?>;

                        /* — Grid geometry —
                           Reservations run 7 AM – 8 PM, Monday to Saturday (Sunday is closed; see
                           submit-faculty-reserve.php). The time axis starts at those hours and only
                           grows if a reservation ever falls outside them, so nothing is ever hidden. */
                        const AXIS_START = 7 * 60;
                        const AXIS_END = 20 * 60;
                        const HOUR_PX = 42; // height of one hour on screen (13 h fits one screen)
                        const REFRESH_MS = 30000; // re-pull reservations while the modal is open
                        const DAY_NAMES = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                        const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                            'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
                        ];

                        /* — State — */
                        let _roomId = null;
                        let _roomName = '';
                        let _weekOff = 0; // 0 = current week
                        let _mobDay = 1; // 1 = Mon … 7 = Sun (mobile single-day view)
                        let _rows = []; // normalized approved reservations for the open room
                        let _timer = null;
                        let _reqToken = 0;
                        let _lastSig = '';

                        /* — Small helpers — */
                        function $(id) {
                            return document.getElementById(id);
                        }

                        function el(tag, cls, text) {
                            const n = document.createElement(tag);
                            if (cls) n.className = cls;
                            if (text != null) n.textContent = text;
                            return n;
                        }

                        function pad2(n) {
                            return String(n).padStart(2, '0');
                        }

                        /* Dates are plain 'YYYY-MM-DD' strings handled in UTC, so the browser's own
                           time zone can never shift a day. "Today" is always Manila time. */
                        function isoToMs(iso) {
                            const p = iso.split('-').map(Number);
                            return Date.UTC(p[0], p[1] - 1, p[2]);
                        }

                        function msToISO(ms) {
                            const d = new Date(ms);
                            return d.getUTCFullYear() + '-' + pad2(d.getUTCMonth() + 1) + '-' + pad2(d.getUTCDate());
                        }

                        function addDays(iso, n) {
                            return msToISO(isoToMs(iso) + n * 86400000);
                        }

                        function mondayOf(iso) {
                            const dow = new Date(isoToMs(iso)).getUTCDay(); // 0 = Sun
                            return addDays(iso, dow === 0 ? -6 : 1 - dow);
                        }

                        function weekOffsetOf(iso, todayISO) {
                            return Math.round((isoToMs(mondayOf(iso)) - isoToMs(mondayOf(todayISO))) / (7 * 86400000));
                        }

                        function manilaNow() {
                            try {
                                const parts = new Intl.DateTimeFormat('en-CA', {
                                    timeZone: 'Asia/Manila',
                                    year: 'numeric',
                                    month: '2-digit',
                                    day: '2-digit',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                    hourCycle: 'h23'
                                }).formatToParts(new Date());
                                const g = t => (parts.find(p => p.type === t) || {}).value;
                                return {
                                    iso: g('year') + '-' + g('month') + '-' + g('day'),
                                    min: (+g('hour')) * 60 + (+g('minute'))
                                };
                            } catch (e) {
                                const d = new Date();
                                return {
                                    iso: d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()),
                                    min: d.getHours() * 60 + d.getMinutes()
                                };
                            }
                        }

                        /* "09:30 AM", "09:30" or "09:30:00" → minutes since midnight */
                        function parseMin(v) {
                            const m = /^\s*(\d{1,2}):(\d{2})(?::\d{2})?\s*([AaPp][Mm])?\s*$/.exec(v || '');
                            if (!m) return null;
                            let h = +m[1];
                            const mi = +m[2];
                            const ap = (m[3] || '').toUpperCase();
                            if (ap === 'PM' && h !== 12) h += 12;
                            if (ap === 'AM' && h === 12) h = 0;
                            return h * 60 + mi;
                        }

                        function hm12(min) {
                            const h = Math.floor(min / 60) % 24;
                            return (h % 12 || 12) + ':' + pad2(min % 60);
                        }

                        function period(min) {
                            return Math.floor(min / 60) % 24 >= 12 ? 'PM' : 'AM';
                        }

                        function fmtRange(s, e) {
                            if (period(s) === period(e)) return hm12(s) + '–' + hm12(e) + ' ' + period(e);
                            return hm12(s) + ' ' + period(s) + '–' + hm12(e) + ' ' + period(e);
                        }

                        function hourLabel(min) {
                            const h = Math.floor(min / 60) % 24;
                            return (h % 12 || 12) + ' ' + (h >= 12 ? 'PM' : 'AM');
                        }

                        function monthDay(iso) {
                            return MONTHS[+iso.slice(5, 7) - 1] + ' ' + (+iso.slice(8, 10));
                        }

                        function makeRow(id, date, s, e, faculty, purpose, asType, by) {
                            if (!date || s == null || e == null || e <= s) return null;
                            return {
                                id: id,
                                date: date,
                                s: s,
                                e: e,
                                faculty: faculty || '',
                                purpose: purpose || '',
                                asType: asType || '',
                                by: by || ''
                            };
                        }

                        /* Rows embedded in the page when it loaded (instant first paint) */
                        function embeddedRows() {
                            return PS_RSVP
                                .filter(r => (_roomId && r.room_id != null) ? (r.room_id === _roomId) : (r.room_name === _roomName))
                                .map(r => makeRow(r.id, r.date, parseMin(r.start_fmt), parseMin(r.end_fmt),
                                    r.faculty, r.purpose, r.submitted_as, r.submitted_by))
                                .filter(Boolean);
                        }

                        /* Rows from the live endpoint (every approved reservation, all weeks) */
                        function apiRows(list) {
                            return list
                                .map(r => makeRow(r.id, r.date, parseMin(r.start), parseMin(r.end),
                                    r.faculty, r.purpose, r.submitted_as, r.submitted_by))
                                .filter(Boolean);
                        }

                        /* How far the week arrows may go: back to the earliest reservation, forward to
                           the latest one (always allowing at least next week). The system sets no
                           upper limit on how far ahead a room can be reserved, so the schedule follows
                           the actual bookings instead of a fixed number of weeks. */
                        function weekBounds(todayISO) {
                            let lo = 0,
                                hi = 1;
                            _rows.forEach(r => {
                                const o = weekOffsetOf(r.date, todayISO);
                                if (o < lo) lo = o;
                                if (o > hi) hi = o;
                            });
                            return {
                                lo: lo,
                                hi: hi
                            };
                        }

                        /* Place overlapping reservations side by side instead of on top of each other */
                        function layoutLanes(rows) {
                            let cluster = [],
                                clusterEnd = -1;

                            function flush() {
                                if (!cluster.length) return;
                                const laneEnds = [];
                                cluster.forEach(r => {
                                    let l = laneEnds.findIndex(end => end <= r.s);
                                    if (l === -1) {
                                        l = laneEnds.length;
                                        laneEnds.push(r.e);
                                    } else {
                                        laneEnds[l] = r.e;
                                    }
                                    r._lane = l;
                                });
                                cluster.forEach(r => {
                                    r._lanes = laneEnds.length;
                                });
                                cluster = [];
                            }
                            rows.forEach(r => {
                                if (cluster.length && r.s >= clusterEnd) flush();
                                clusterEnd = cluster.length ? Math.max(clusterEnd, r.e) : r.e;
                                cluster.push(r);
                            });
                            flush();
                        }

                        function whoLabel(r) {
                            return (r.asType === 'student' && r.by) ? (r.by + ' (via ' + r.faculty + ')') : r.faculty;
                        }

                        function buildBlock(r, axisStart, now) {
                            const top = (r.s - axisStart) / 60 * HOUR_PX;
                            const h = Math.max((r.e - r.s) / 60 * HOUR_PX, 22);
                            const title = r.purpose || r.faculty || 'Reserved';
                            const who = whoLabel(r);

                            const b = el('div', 'rsm-block');
                            b.tabIndex = 0;
                            b.style.top = (top + 1) + 'px';
                            b.style.height = (h - 2) + 'px';
                            b.style.minHeight = (h - 2) + 'px';
                            const lanes = r._lanes || 1,
                                lane = r._lane || 0;
                            b.style.left = 'calc(' + (lane * 100 / lanes) + '% + 2px)';
                            b.style.width = 'calc(' + (100 / lanes) + '% - 4px)';
                            if (h < 34) b.classList.add('rsm-block--xs');
                            else if (h < 52) b.classList.add('rsm-block--sm');
                            if (r.date < now.iso || (r.date === now.iso && r.e <= now.min)) b.classList.add('is-past');

                            b.appendChild(el('span', 'rsm-block-time', fmtRange(r.s, r.e)));
                            b.appendChild(el('span', 'rsm-block-title', title));
                            if (r.purpose && who) b.appendChild(el('span', 'rsm-block-sub', who));

                            b.title = [fmtRange(r.s, r.e), r.purpose, who].filter(Boolean).join('\n');
                            return b;
                        }

                        function weekRangeLabel(first, last) {
                            const sy = first.slice(0, 4),
                                ey = last.slice(0, 4);
                            if (first.slice(0, 7) === last.slice(0, 7))
                                return monthDay(first) + '–' + (+last.slice(8, 10)) + ', ' + ey;
                            if (sy === ey) return monthDay(first) + ' – ' + monthDay(last) + ', ' + ey;
                            return monthDay(first) + ', ' + sy + ' – ' + monthDay(last) + ', ' + ey;
                        }

                        function relativeWeek(off) {
                            if (off === 0) return 'This week';
                            if (off === 1) return 'Next week';
                            if (off === -1) return 'Last week';
                            return off > 0 ? 'In ' + off + ' weeks' : Math.abs(off) + ' weeks ago';
                        }

                        function todayColumn(now) {
                            const dow = new Date(isoToMs(now.iso)).getUTCDay(); // 0 = Sun
                            return dow >= 1 && dow <= 6 ? dow : 1; // Sunday is closed → Monday
                        }

                        function applyMobileDay(dayCount) {
                            if (_mobDay > dayCount) _mobDay = dayCount;
                            if (_mobDay < 1) _mobDay = 1;
                            const grid = $('rsm-grid');
                            if (grid) {
                                grid.querySelectorAll('[data-day]').forEach(n => {
                                    n.classList.toggle('is-mobile-active', +n.dataset.day === _mobDay);
                                });
                            }
                            document.querySelectorAll('#rsm-daytabs .rsm-daytab-btn').forEach(b => {
                                const on = +b.dataset.day === _mobDay;
                                b.classList.toggle('active', on);
                                b.setAttribute('aria-pressed', on ? 'true' : 'false');
                            });
                        }

                        /* — Render the weekly grid — */
                        function renderGrid(opts) {
                            opts = opts || {};
                            const head = $('rsm-grid-head'),
                                body = $('rsm-grid-body'),
                                grid = $('rsm-grid');
                            if (!head || !body || !grid) return;

                            const now = manilaNow();
                            const bounds = weekBounds(now.iso);
                            if (_weekOff < bounds.lo) _weekOff = bounds.lo;
                            if (_weekOff > bounds.hi) _weekOff = bounds.hi;

                            const monday = addDays(mondayOf(now.iso), _weekOff * 7);
                            const sunday = addDays(monday, 6);
                            const wk = _rows.filter(r => r.date >= monday && r.date <= sunday);

                            /* Mon–Sat always; Sunday only if a reservation somehow sits on it */
                            const dayCount = wk.some(r => r.date === sunday) ? 7 : 6;
                            const days = [];
                            for (let i = 0; i < dayCount; i++) {
                                const iso = addDays(monday, i);
                                days.push({
                                    idx: i + 1,
                                    iso: iso,
                                    name: DAY_NAMES[i],
                                    num: +iso.slice(8, 10)
                                });
                            }

                            /* Time axis */
                            let a0 = AXIS_START,
                                a1 = AXIS_END;
                            wk.forEach(r => {
                                a0 = Math.min(a0, Math.floor(r.s / 60) * 60);
                                a1 = Math.max(a1, Math.ceil(r.e / 60) * 60);
                            });
                            a1 = Math.min(a1, 24 * 60);
                            const totalPx = (a1 - a0) / 60 * HOUR_PX;

                            grid.style.setProperty('--rsm-days', String(dayCount));
                            grid.style.setProperty('--rsm-hour', HOUR_PX + 'px');
                            body.style.setProperty('--rsm-total', totalPx + 'px');

                            /* Header row */
                            head.textContent = '';
                            head.appendChild(el('div', 'rsm-grid-th rsm-grid-th--time', ''));
                            days.forEach(d => {
                                const th = el('div', 'rsm-grid-th');
                                th.dataset.day = d.idx;
                                if (d.iso === now.iso) th.classList.add('is-today');
                                th.appendChild(el('span', 'rsm-th-name', d.name));
                                th.appendChild(el('span', 'rsm-th-num', String(d.num)));
                                head.appendChild(th);
                            });

                            /* Body: hour labels + one column per day */
                            body.textContent = '';
                            const tcol = el('div', 'rsm-time-col');
                            for (let m = a0; m < a1; m += 60) {
                                const lbl = el('div', 'rsm-hour-lbl', hourLabel(m));
                                lbl.style.top = ((m - a0) / 60 * HOUR_PX) + 'px';
                                tcol.appendChild(lbl);
                            }
                            body.appendChild(tcol);

                            days.forEach(d => {
                                const col = el('div', 'rsm-day-col');
                                col.dataset.day = d.idx;
                                if (d.iso === now.iso) col.classList.add('is-today');
                                const dayRows = wk.filter(r => r.date === d.iso)
                                    .sort((x, y) => (x.s - y.s) || (x.e - y.e));
                                layoutLanes(dayRows);
                                dayRows.forEach(r => col.appendChild(buildBlock(r, a0, now)));
                                if (d.iso === now.iso && now.min >= a0 && now.min <= a1) {
                                    const nl = el('div', 'rsm-now');
                                    nl.style.top = ((now.min - a0) / 60 * HOUR_PX) + 'px';
                                    col.appendChild(nl);
                                }
                                body.appendChild(col);
                            });

                            if (!wk.length) {
                                const empty = el('div', 'rsm-empty');
                                empty.appendChild(el('span', '', 'No reservations this week'));
                                body.appendChild(empty);
                            }

                            /* Week label, counter and arrows */
                            const lbl = $('rsm-week-lbl');
                            if (lbl) lbl.textContent = weekRangeLabel(days[0].iso, days[days.length - 1].iso);
                            const sub = $('rsm-week-sub');
                            if (sub) {
                                sub.textContent = relativeWeek(_weekOff) + ' · ' +
                                    (wk.length ? wk.length + (wk.length === 1 ? ' reservation' : ' reservations') : 'none reserved');
                            }
                            const prev = $('rsm-prev'),
                                next = $('rsm-next');
                            if (prev) prev.disabled = _weekOff <= bounds.lo;
                            if (next) next.disabled = _weekOff >= bounds.hi;

                            /* Day tabs for the phone layout (one day at a time) */
                            const tabs = $('rsm-daytabs');
                            if (tabs) {
                                tabs.textContent = '';
                                days.forEach(d => {
                                    const b = el('button', 'rsm-daytab-btn');
                                    b.type = 'button';
                                    b.dataset.day = d.idx;
                                    b.appendChild(el('span', '', d.name + ' ' + d.num));
                                    const n = wk.filter(r => r.date === d.iso).length;
                                    if (n) b.appendChild(el('span', 'rsm-daytab-count', String(n)));
                                    tabs.appendChild(b);
                                });
                            }
                            applyMobileDay(dayCount);

                            /* Bring the first reservation of the week into view */
                            if (opts.scroll) {
                                const sc = $('rsm-body');
                                if (sc) {
                                    sc.scrollTop = wk.length ?
                                        Math.max(0, (Math.min.apply(null, wk.map(r => r.s)) - a0) / 60 * HOUR_PX - HOUR_PX / 2) :
                                        0;
                                }
                            }
                            _lastSig = signature(now);
                        }

                        function signature(now) {
                            return JSON.stringify(_rows) + '|' + _weekOff + '|' + now.iso + '|' + Math.floor(now.min / 5);
                        }

                        /* — Pull the latest reservations from the server — */
                        function setSyncNote(show) {
                            const n = $('rsm-sync-note');
                            if (n) n.classList.toggle('hidden', !show);
                        }

                        function fetchFresh() {
                            if (!_roomId) return;
                            const token = ++_reqToken;
                            const rid = _roomId;
                            fetch('room-reservation/api/admin-room-schedule.php?room_id=' + encodeURIComponent(rid), {
                                    credentials: 'same-origin',
                                    cache: 'no-store',
                                    headers: {
                                        'Accept': 'application/json'
                                    }
                                })
                                .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
                                .then(j => {
                                    if (token !== _reqToken || rid !== _roomId) return; // closed or switched room
                                    if (!j || !Array.isArray(j.reservations)) throw new Error('Bad response');
                                    _rows = apiRows(j.reservations);
                                    setSyncNote(false);
                                    if (signature(manilaNow()) !== _lastSig) renderGrid();
                                })
                                .catch(err => {
                                    if (token !== _reqToken) return;
                                    console.warn('Facility schedule refresh failed:', err);
                                    setSyncNote(true);
                                });
                        }

                        function stopRefresh() {
                            _reqToken++;
                            if (_timer) {
                                clearInterval(_timer);
                                _timer = null;
                            }
                        }

                        function startRefresh() {
                            stopRefresh();
                            _timer = setInterval(fetchFresh, REFRESH_MS);
                        }

                        /* — Public API — */
                        window.psOpenSchedule = function(btn) {
                            const card = btn.closest('.pr-room-row');
                            if (!card) return;
                            if (card.dataset.roomLocked === '1') return; // locked rooms never open their schedule
                            _roomId = parseInt(card.dataset.roomId) || null;
                            _roomName = card.dataset.roomName || '';
                            _weekOff = 0;
                            _mobDay = todayColumn(manilaNow());
                            _rows = embeddedRows();
                            const title = $('rsm-title');
                            if (title) title.textContent = 'Facility Schedule – ' + _roomName;
                            const meta = $('rsm-meta');
                            if (meta) {
                                const campus = card.dataset.roomCampus || '';
                                const floor = card.dataset.roomFloor || '';
                                meta.textContent = [campus, floor].filter(Boolean).join(' – ');
                            }
                            setSyncNote(false);
                            const modal = $('roomScheduleModal');
                            if (modal) modal.classList.remove('hidden');
                            try {
                                renderGrid({
                                    scroll: true
                                });
                            } catch (e) {
                                console.warn('renderGrid error:', e);
                            }
                            startRefresh();
                            fetchFresh(); // swap in the latest data as soon as it arrives
                        };

                        window.psCloseSchedule = function() {
                            const modal = $('roomScheduleModal');
                            if (modal) modal.classList.add('hidden');
                            stopRefresh();
                        };

                        window.psScheduleNav = function(dir) {
                            _weekOff += dir;
                            renderGrid({
                                scroll: true
                            });
                        };

                        document.addEventListener('click', function(e) {
                            const modal = $('roomScheduleModal');
                            if (!modal || modal.classList.contains('hidden')) return;
                            if (e.target.closest('[data-action="room-schedule-today"]')) {
                                _weekOff = 0;
                                _mobDay = todayColumn(manilaNow());
                                renderGrid({
                                    scroll: true
                                });
                                return;
                            }
                            const tab = e.target.closest('.rsm-daytab-btn');
                            if (tab) {
                                _mobDay = +tab.dataset.day || 1;
                                applyMobileDay(document.querySelectorAll('#rsm-daytabs .rsm-daytab-btn').length);
                            }
                        });

                        document.addEventListener('keydown', function(e) {
                            if (e.key !== 'Escape') return;
                            const modal = $('roomScheduleModal');
                            if (modal && !modal.classList.contains('hidden')) window.psCloseSchedule();
                        });

                        /* ── Whole tile opens the schedule ──────────────────────────
                           The tile itself is the "view schedule" control. Clicks inside
                           the actions cell (the Edit icon) are skipped so Edit keeps
                           working on its own. Delegated on document, so tiles swapped in
                           by the AJAX refresh work without re-binding. */
                        document.addEventListener('click', function(e) {
                            var tile = e.target.closest('#roomsRegistryTable tr.pr-room-row');
                            if (!tile) return;
                            if (e.target.closest('.pr-row-actions')) return;
                            if (tile.dataset.roomLocked === '1') return; // locked: schedule stays closed
                            window.psOpenSchedule(tile);
                        });
                        document.addEventListener('keydown', function(e) {
                            if (e.key !== 'Enter' && e.key !== ' ') return;
                            var tile = e.target.closest ? e.target.closest('#roomsRegistryTable tr.pr-room-row') : null;
                            if (!tile || e.target !== tile) return;
                            e.preventDefault();
                            if (tile.dataset.roomLocked === '1') return;
                            window.psOpenSchedule(tile);
                        });

                        /* ── edit-room-inline: populate & show form without page reload ── */
                        document.addEventListener('click', function(e) {
                            var btn = e.target.closest('[data-action="edit-room-inline"]');
                            if (!btn) return;
                            var card = btn.closest('.pr-room-row');
                            if (!card) return;
                            var d = card.dataset;
                            var form = document.getElementById('roomForm');
                            if (!form) return;

                            /* Populate all fields */
                            var el;
                            el = document.getElementById('room-form-id');
                            if (el) el.value = d.roomId || '';

                            el = form.querySelector('select[name="building_id"]');
                            if (el) el.value = d.roomBuildingId || '';

                            el = form.querySelector('input[name="room_name"]');
                            if (el) el.value = d.roomName || '';

                            el = form.querySelector('input[name="floor_number"]');
                            if (el) el.value = d.roomFloorNum || '1';
                            if (window.psSyncRoomFloorLimit) window.psSyncRoomFloorLimit(false);

                            el = form.querySelector('input[name="floor_label"]');
                            if (el) el.value = d.roomFloorLabel || '';

                            el = form.querySelector('select[name="status"]');
                            if (el) el.value = d.roomStatus || 'Available';

                            el = form.querySelector('input[name="sort_order"]');
                            if (el) el.value = d.roomSort || '0';

                            el = document.getElementById('room-more-opts');
                            if (el) el.open = !!(d.roomFloorLabel || (d.roomSort && d.roomSort !== '0'));

                            var amenities = [];
                            try {
                                amenities = JSON.parse(d.roomAmenities || '[]');
                            } catch (x) {}
                            form.querySelectorAll('input[name="amenities[]"]').forEach(function(cb) {
                                cb.checked = amenities.indexOf(cb.value) !== -1;
                            });

                            /* Update title + icon */
                            el = document.getElementById('room-form-title');
                            if (el) el.textContent = 'Edit Facility — ' + (d.roomName || '');
                            el = document.querySelector('#room-form-wrap .rmod-head-icon .material-symbols-outlined');
                            if (el) el.textContent = 'edit';

                            /* Switch submit buttons to update_room */
                            ['room-form-submit-inner', 'room-form-submit-foot'].forEach(function(id) {
                                var b = document.getElementById(id);
                                if (b) b.name = 'update_room';
                            });
                            el = document.getElementById('room-submit-inner-label');
                            if (el) el.textContent = 'Update Facility';
                            el = document.getElementById('room-submit-foot-label');
                            if (el) el.textContent = 'Save Changes';

                            /* Archive zone */
                            var az = document.getElementById('room-archive-zone');
                            if (az) az.classList.remove('hidden');
                            var al = document.getElementById('room-archive-link');
                            if (al) al.href = 'admin-dashboard.php?archive_room=' + encodeURIComponent(d.roomId || '');
                            if (window.psSetLockButton) window.psSetLockButton(d.roomLocked === '1');

                            /* Show form and scroll */
                            var rfw = document.getElementById('room-form-wrap');
                            if (rfw) {
                                rfw.classList.remove('hidden');
                                setTimeout(function() {
                                    rfw.scrollIntoView({
                                        behavior: 'smooth',
                                        block: 'start'
                                    });
                                }, 30);
                            }
                        });

                        /* Put the room form back into Add-mode with empty fields. Runs on Cancel/Close,
                           before "Add Room" opens it, and after a successful save -- so the form can
                           never reopen still holding the last room that was edited. */
                        window.psResetRoomForm = function() {
                            var form = document.getElementById('roomForm');
                            var el;
                            el = document.getElementById('room-form-title');
                            if (el) el.textContent = 'Add New Facility';
                            el = document.querySelector('#room-form-wrap .rmod-head-icon .material-symbols-outlined');
                            if (el) el.textContent = 'add_home_work';
                            ['room-form-submit-inner', 'room-form-submit-foot'].forEach(function(id) {
                                var b = document.getElementById(id);
                                if (b) b.name = 'add_room';
                            });
                            el = document.getElementById('room-submit-inner-label');
                            if (el) el.textContent = 'Add Facility';
                            el = document.getElementById('room-submit-foot-label');
                            if (el) el.textContent = 'Add Facility';
                            var az = document.getElementById('room-archive-zone');
                            if (az) az.classList.add('hidden');
                            el = document.getElementById('room-form-id');
                            if (el) el.value = '';
                            if (!form) return;
                            el = form.querySelector('select[name="building_id"]');
                            if (el) el.value = '';
                            el = form.querySelector('input[name="room_name"]');
                            if (el) el.value = '';
                            el = form.querySelector('input[name="floor_number"]');
                            if (el) el.value = '1';
                            if (window.psSyncRoomFloorLimit) window.psSyncRoomFloorLimit(false);
                            el = form.querySelector('input[name="floor_label"]');
                            if (el) el.value = '';
                            el = form.querySelector('select[name="status"]');
                            if (el) el.value = 'Available';
                            el = form.querySelector('input[name="sort_order"]');
                            if (el) el.value = '0';
                            form.querySelectorAll('input[name="amenities[]"]').forEach(function(cb) {
                                cb.checked = false;
                            });
                            el = document.getElementById('room-more-opts');
                            if (el) el.open = false;
                        };

                        /* Floor can't go past the selected building's number of floors (tbl_buildings.floor_count).
                           clamp = true only when the admin switches building, so editing never rewrites saved data. */
                        window.psSyncRoomFloorLimit = function(clamp) {
                            var sel = document.getElementById('rf-building');
                            var fl = document.getElementById('rf-floor');
                            var hint = document.getElementById('rf-floor-hint');
                            if (!sel || !fl) return;
                            var opt = sel.options[sel.selectedIndex];
                            var max = opt ? parseInt(opt.getAttribute('data-floors'), 10) : NaN;
                            if (!max || max < 1) {
                                fl.max = '20';
                                if (hint) hint.textContent = 'Select a building to see how many floors it has.';
                                return;
                            }
                            fl.max = String(max);
                            if (clamp && parseInt(fl.value, 10) > max) fl.value = String(max);
                            var name = (opt.textContent || '').replace(/\s+/g, ' ').trim();
                            if (hint) hint.textContent = name + ' has ' + max + (max === 1 ? ' floor' : ' floors') + ' (1\u2013' + max + ').';
                        };
                        document.addEventListener('change', function(e) {
                            if (e.target && e.target.id === 'rf-building') window.psSyncRoomFloorLimit(true);
                        });
                        window.psSyncRoomFloorLimit(false);

                        document.addEventListener('click', function(e) {
                            if (e.target.closest('[data-action="hide-room-form"]') ||
                                e.target.closest('[data-action="show-room-form"]')) {
                                window.psResetRoomForm();
                            }
                        });

                        document.addEventListener('click', function(e) {
                            const modal = document.getElementById('roomScheduleModal');
                            if (e.target.closest('[data-action="open-room-schedule"]')) {
                                window.psOpenSchedule(e.target.closest('[data-action="open-room-schedule"]'));
                                return;
                            }
                            if (e.target.closest('[data-action="close-room-schedule"]')) {
                                window.psCloseSchedule();
                                return;
                            }
                            const navBtn = e.target.closest('[data-action="room-schedule-nav"]');
                            if (navBtn) {
                                window.psScheduleNav(parseInt(navBtn.dataset.dir));
                                return;
                            }
                            if (modal && !modal.classList.contains('hidden') && e.target === modal)
                                window.psCloseSchedule();
                            const rfw = document.getElementById('room-form-wrap');
                            if (rfw && !rfw.classList.contains('hidden') && e.target === rfw)
                                rfw.classList.add('hidden');
                        });
                    })();
                </script>

            </div><!-- /panel-rooms -->


            <!-- ============================================================
         TAB: FACULTY  (Super Admin only)
    ============================================================ -->
            <?php if ($is_super_admin): ?>
                <div class="tab-panel" id="panel-faculty">

                    <div style="margin-bottom:1.5rem">
                        <h2 style="font-size:1.3rem;font-weight:700;color:var(--text-dark)">Faculty Management</h2>
                        <p style="color:var(--text-light);font-size:12.5px;margin-top:2px">Create, edit, and remove faculty accounts.</p>
                    </div>

                    <?php
                    // Registry status for the upload card
                    $reg_status = ['count' => 0, 'last_date' => '', 'last_by' => ''];
                    $reg_tbl_exists = $conn->query("SHOW TABLES LIKE 'tbl_faculty_registry'")->num_rows > 0;
                    if ($reg_tbl_exists) {
                        $rs = $conn->query("SELECT COUNT(*) AS cnt, MAX(uploaded_at) AS last_at, MAX(uploaded_by) AS last_by FROM tbl_faculty_registry");
                        if ($rs) {
                            $rrow = $rs->fetch_assoc();
                            $reg_status['count']     = (int)($rrow['cnt'] ?? 0);
                            $reg_status['last_date'] = (string)($rrow['last_at'] ?? '');
                            $reg_status['last_by']   = (string)($rrow['last_by'] ?? '');
                        }
                    }

                    // Original files kept for every import (equipment-booking/core/faculty-registry-files.php)
                    $reg_files = ['count' => 0, 'last_name' => ''];
                    if ($conn->query("SHOW TABLES LIKE 'tbl_faculty_registry_files'")->num_rows > 0) {
                        $frs = $conn->query("SELECT COUNT(*) AS cnt FROM tbl_faculty_registry_files");
                        if ($frs) {
                            $reg_files['count'] = (int)($frs->fetch_assoc()['cnt'] ?? 0);
                        }
                        $frs = $conn->query("SELECT original_name FROM tbl_faculty_registry_files WHERE status = 'imported' ORDER BY imported_at DESC, id DESC LIMIT 1");
                        if ($frs && ($frow = $frs->fetch_assoc())) {
                            $reg_files['last_name'] = (string)$frow['original_name'];
                        }
                    }
                    ?>

                    <div class="faculty-layout">

                        <!-- REGISTRY UPLOAD CARD -->
                        <div class="eq-card fac-registry-card" id="fac-registry-card" style="margin-bottom:1.25rem;grid-column:1/-1">
                            <div class="eq-card-header" id="fac-registry-header" style="cursor:pointer;user-select:none" role="button" aria-expanded="false" aria-controls="fac-registry-body" tabindex="0">
                                <h2>
                                    <span class="material-symbols-outlined" style="font-size:18px;color:var(--accent-maroon);margin-right:6px;vertical-align:middle">upload_file</span>
                                    Faculty Registry
                                    <?php if ($reg_status['count'] > 0): ?>
                                        <span class="fac-reg-badge fac-reg-badge--active"><?= $reg_status['count'] ?> entries</span>
                                    <?php else: ?>
                                        <span class="fac-reg-badge fac-reg-badge--empty">Not uploaded yet</span>
                                    <?php endif; ?>
                                </h2>
                                <span class="material-symbols-outlined fac-registry-chevron" id="fac-registry-chevron" style="color:var(--text-light);transition:transform .2s">expand_more</span>
                            </div>

                            <div id="fac-registry-body" style="display:none">
                                <div class="eq-card-body" style="padding-top:0">

                                    <?php if ($reg_status['count'] > 0 && $reg_status['last_date'] !== ''): ?>
                                        <p style="font-size:12px;color:var(--text-light);margin:0 0 1rem">
                                            Last imported:
                                            <strong style="color:var(--text-dark)"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($reg_status['last_date']))) ?></strong>
                                            by <?= htmlspecialchars($reg_status['last_by']) ?><?php if ($reg_files['last_name'] !== ''): ?> from <strong style="color:var(--text-dark)"><?= htmlspecialchars($reg_files['last_name']) ?></strong><?php endif; ?> &mdash;
                                        <?= $reg_status['count'] ?> total <?= $reg_status['count'] === 1 ? 'entry' : 'entries' ?>
                                        </p>
                                    <?php endif; ?>

                                    <!-- View the imported list, and the original files that were imported
                                         (both ask for the Super Admin password first) -->
                                    <div class="fac-reg-view-row" id="fac-reg-view-row">
                                        <button type="button" id="fac-reg-view-btn" class="ps-btn ps-btn--outline ps-btn--sm" <?= $reg_status['count'] > 0 ? '' : 'hidden' ?>>
                                            <span class="material-symbols-outlined">lock</span> View registry list
                                        </button>
                                        <button type="button" id="fac-reg-files-btn" class="ps-btn ps-btn--outline ps-btn--sm">
                                            <span class="material-symbols-outlined">folder_open</span> Imported files
                                            <span class="fac-reg-files-count" id="fac-reg-files-count" <?= $reg_files['count'] > 0 ? '' : 'hidden' ?>><?= (int)$reg_files['count'] ?></span>
                                        </button>
                                        <span class="fac-reg-view-hint">Asks for your password</span>
                                    </div>

                                    <div class="fac-reg-upload-row">
                                        <label for="fac-reg-file-input" class="fac-reg-file-label" id="fac-reg-file-label">
                                            <span class="material-symbols-outlined">attach_file</span>
                                            <span id="fac-reg-file-name">Choose file (PDF or XLSX)…</span>
                                        </label>
                                        <input type="file" id="fac-reg-file-input" accept=".pdf,.xlsx,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" style="display:none">
                                        <button type="button" id="fac-reg-import-btn" class="ps-btn ps-btn--primary" disabled>
                                            <span class="material-symbols-outlined">cloud_upload</span> Import
                                        </button>
                                    </div>

                                    <label class="fac-reg-replace-row" for="fac-reg-replace-all" style="margin-top:.5rem">
                                        <input type="checkbox" id="fac-reg-replace-all">
                                        <span style="font-size:12.5px;color:var(--text-light)">Replace entire registry (removes all existing entries before importing)</span>
                                    </label>

                                    <div id="fac-reg-alert" class="alert-banner hidden" role="alert" style="margin-top:.75rem"></div>

                                    <!-- Preview table — shown after parsing, before import -->
                                    <div id="fac-reg-preview" style="display:none;margin-top:1rem">
                                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
                                            <p style="font-size:12.5px;font-weight:600;color:var(--text-dark);margin:0" id="fac-reg-preview-count"></p>
                                            <button type="button" id="fac-reg-clear-btn" class="ps-btn ps-btn--ghost ps-btn--sm">
                                                <span class="material-symbols-outlined">close</span> Clear
                                            </button>
                                        </div>
                                        <div class="tbl-wrap" style="max-height:260px;overflow-y:auto">
                                            <table class="admin-table" style="font-size:12px">
                                                <thead>
                                                    <tr>
                                                        <th style="width:40px">#</th>
                                                        <th>Faculty ID</th>
                                                        <th>Name</th>
                                                        <th style="width:60px;text-align:center">New</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="fac-reg-preview-tbody"></tbody>
                                            </table>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div><!-- /registry card -->

                        <!-- CREATE FORM -->
                        <div class="eq-card faculty-form-card">
                            <div class="eq-card-header">
                                <h2>
                                    <span class="material-symbols-outlined" style="font-size:18px;color:var(--accent-maroon);margin-right:6px;vertical-align:middle">person_add</span>
                                    Create Faculty Account
                                </h2>
                            </div>
                            <div class="eq-card-body">
                                <?= csrf_field() ?>

                                <div class="fac-edit-section">Name</div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="fac-first">First name <span class="req-star">*</span></label>
                                        <input type="text" id="fac-first" name="first_name"
                                            class="form-control-custom" maxlength="100" required placeholder="First">
                                    </div>
                                    <div class="form-group">
                                        <label for="fac-last">Last name <span class="req-star">*</span></label>
                                        <input type="text" id="fac-last" name="last_name"
                                            class="form-control-custom" maxlength="100" required placeholder="Last">
                                    </div>
                                </div>
                                <small class="fac-field-hint" style="display:block;margin:-0.35rem 0 0.75rem">
                                    Type the whole surname under Last name, e.g. <strong>Dela Cruz</strong>. In the registry, everything after the middle initial
                                    (Edmundo B. <strong>Dela Cruz</strong>) is the surname; with no initial, the last word is.
                                </small>
                                <button type="button" id="fac-lookup-btn"
                                    class="ps-btn ps-btn--outline fac-lookup-btn">
                                    <span class="material-symbols-outlined">manage_search</span>
                                    Look up in Faculty Registry
                                </button>
                                <div id="fac-lookup-alert" class="alert-banner hidden" role="alert" style="margin-bottom:0.5rem"></div>

                                <!-- Disambiguation picker — shown only when multiple registry matches exist -->
                                <div id="fac-disambiguation" style="display:none;margin-bottom:0.75rem">
                                    <label for="fac-disambig-select" style="font-size:12px;color:var(--text-light);margin-bottom:4px;display:block">
                                        Multiple matches found — select the correct faculty member:
                                    </label>
                                    <select id="fac-disambig-select" class="form-control-custom"></select>
                                </div>

                                <div class="fac-edit-section">Account <span style="font-size:11px;font-weight:400;color:var(--text-light)">(auto-filled from registry)</span></div>
                                <div class="form-group">
                                    <label for="fac-email">PUPSync email</label>
                                    <input type="email" id="fac-email"
                                        class="form-control-custom" maxlength="254"
                                        autocomplete="off" spellcheck="false" placeholder="Auto-filled after lookup">
                                    <small class="fac-field-hint">Suggested from the registry name. You can edit it, for example when a surname has two words but no middle initial. It must end with @pupsync.edu.</small>
                                </div>
                                <div class="form-group">
                                    <label for="fac-faculty-id">Faculty ID</label>
                                    <input type="text" id="fac-faculty-id"
                                        class="form-control-custom" maxlength="30"
                                        autocomplete="off" spellcheck="false"
                                        placeholder="Auto-filled after lookup"
                                        readonly aria-readonly="true" tabindex="-1">
                                    <small class="fac-field-hint">From the Faculty Identification Records. Cannot be edited, and is permanent after account creation.</small>
                                </div>

                                <div class="fac-edit-section">Password</div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="fac-password">Password <span class="req-star">*</span></label>
                                        <div class="fac-pw-wrap">
                                            <input type="password" id="fac-password" name="password"
                                                class="form-control-custom" maxlength="128" required
                                                placeholder="Min. 8 characters">
                                            <button type="button" class="fac-pw-toggle" tabindex="-1"
                                                data-target="fac-password" aria-label="Toggle password">
                                                <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="fac-confirm">Confirm <span class="req-star">*</span></label>
                                        <div class="fac-pw-wrap">
                                            <input type="password" id="fac-confirm" name="confirm_password"
                                                class="form-control-custom" maxlength="128" required
                                                placeholder="Re-enter password">
                                            <button type="button" class="fac-pw-toggle" tabindex="-1"
                                                data-target="fac-confirm" aria-label="Toggle password">
                                                <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Organization adviser: org borrowing is automatic for advisers -->
                                <div class="fac-opt-card">
                                    <label class="fac-opt-row" for="fac-adviser">
                                        <span class="fac-opt-text">
                                            <span class="fac-opt-title">Organization adviser</span>
                                            <span class="fac-opt-sub">Gets organization borrowing automatically</span>
                                        </span>
                                        <input type="checkbox" id="fac-adviser" name="is_org_adviser"
                                            value="1" class="faculty-toggle-input">
                                        <span class="faculty-toggle-track"></span>
                                    </label>
                                    <div class="fac-opt-extra" id="fac-org-group" style="display:none;">
                                        <div class="form-group">
                                            <label for="fac-org">Organization</label>
                                            <?php
                                            // Organizations are grouped (Academic / Non-academic, then uncategorised);
                                            // one that already has an adviser is disabled and marked "(taken)".
                                            $orgs_grouped     = orgs_load_grouped($conn);
                                            $orgs_adviser_map = orgs_adviser_map($conn);
                                            $orgs_total = count($orgs_grouped[ORG_CATEGORY_ACADEMIC])
                                                + count($orgs_grouped[ORG_CATEGORY_NON_ACADEMIC])
                                                + count($orgs_grouped['']);
                                            if ($orgs_total > 0): ?>
                                                <select id="fac-org" name="organization_id"
                                                    class="form-control-custom">
                                                    <option value="">&#8212; Select Organization &#8212;</option>
                                                    <?= orgs_render_options($orgs_grouped, $orgs_adviser_map, true) ?>
                                                </select>
                                            <?php else: ?>
                                                <select id="fac-org" name="organization_id"
                                                    class="form-control-custom" disabled>
                                                    <option value="">&#8212; Organizations unavailable &#8212;</option>
                                                </select>
                                                <small class="faculty-field-error">
                                                    Could not load organizations.
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div id="fac-form-alert" class="alert-banner hidden" role="alert"></div>

                                <button type="button" id="fac-submit-btn"
                                    class="ps-btn ps-btn--primary fac-submit-btn">
                                    <span class="material-symbols-outlined">person_add</span>
                                    Create Account
                                </button>
                                <p class="fac-form-hint">Faculty ID and email are sourced from the Faculty Identification Records document.</p>
                            </div><!-- /eq-card-body -->
                        </div><!-- /faculty-form-card -->


                        <!-- FACULTY LIST -->
                        <div class="eq-card">
                            <div class="eq-card-header fac-list-head">
                                <h2>
                                    <span class="material-symbols-outlined" style="font-size:18px;color:var(--accent-maroon);margin-right:6px;vertical-align:middle">groups</span>
                                    Faculty List
                                    <span class="fac-count-badge" id="fac-count-badge">(<?php
                                                                                        $fac_count = $conn->query("SELECT COUNT(*) AS cnt FROM tbl_users");
                                                                                        echo ($fac_count) ? (int)$fac_count->fetch_assoc()['cnt'] : 0;
                                                                                        ?>)</span>
                                </h2>
                                <div class="fac-search">
                                    <span class="material-symbols-outlined">search</span>
                                    <input type="text" id="fac-search-input"
                                        class="form-control-custom"
                                        placeholder="Search name, email or ID">
                                </div>
                            </div>
                            <div class="tbl-wrap">
                                <table class="admin-table fac-table" id="fac-list-table">
                                    <thead>
                                        <tr>
                                            <th>Faculty</th>
                                            <th>Faculty ID</th>
                                            <th>Role</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="faculty-list-tbody">
                                        <?php
                                        $fac_res = $conn->query(
                                            "SELECT u.fullname, u.email, u.backup_email, u.role,"
                                                . " u.faculty_id, u.organization_id,"
                                                . " o.name AS org_name"
                                                . " FROM tbl_users u"
                                                . " LEFT JOIN tbl_organizations o ON u.organization_id = o.id"
                                                . " ORDER BY u.fullname ASC"
                                        );
                                        if ($fac_res && $fac_res->num_rows > 0):
                                            while ($frow = $fac_res->fetch_assoc()):
                                                $isAdviser = ($frow['role'] === 'Organization Adviser');
                                                $idUnset   = faculty_id_is_unset($frow['faculty_id']);
                                                $initFac   = strtoupper(substr(trim($frow['fullname'] ?? '') ?: 'F', 0, 1));
                                        ?>
                                                <tr
                                                    data-fullname="<?= htmlspecialchars($frow['fullname']) ?>"
                                                    data-email="<?= htmlspecialchars($frow['email']) ?>"
                                                    data-backup-email="<?= htmlspecialchars($frow['backup_email'] ?? '') ?>"
                                                    data-faculty-id="<?= htmlspecialchars($frow['faculty_id']) ?>"
                                                    data-role="<?= htmlspecialchars($frow['role']) ?>"
                                                    data-org="<?= htmlspecialchars($frow['org_name'] ?? '') ?>"
                                                    data-org-id="<?= (int)($frow['organization_id'] ?? 0) ?>"
                                                    data-init="<?= htmlspecialchars($initFac) ?>">
                                                    <td>
                                                        <div class="fac-person">
                                                            <span class="fac-avatar"><?= htmlspecialchars($initFac) ?></span>
                                                            <div class="fac-person-text">
                                                                <div class="fac-person-name"><?= htmlspecialchars($frow['fullname']) ?></div>
                                                                <div class="fac-person-sub"><?= htmlspecialchars($frow['email']) ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($idUnset): ?>
                                                            <span class="fac-id-pending">Not set yet</span>
                                                        <?php else: ?>
                                                            <span class="fac-id"><?= htmlspecialchars($frow['faculty_id']) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($isAdviser): ?>
                                                            <span class="fac-role fac-role--adviser">Org Adviser</span>
                                                            <?php if (!empty($frow['org_name'])): ?>
                                                                <div class="fac-person-sub"><?= htmlspecialchars($frow['org_name']) ?></div>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="fac-role">Faculty</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="fac-actions">
                                                        <button class="ps-btn ps-btn--ghost ps-btn--sm fac-edit-btn" aria-label="Edit account">
                                                            <span class="material-symbols-outlined">edit</span>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile;
                                        else: ?>
                                            <tr id="fac-empty-row">
                                                <td colspan="4"
                                                    style="text-align:center;padding:3rem;color:var(--text-light)">
                                                    <span class="material-symbols-outlined"
                                                        style="font-size:40px;display:block;margin:0 auto 10px;opacity:0.3">group</span>
                                                    No faculty accounts yet.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <div class="fac-nomatch" id="fac-nomatch" hidden>
                                    <span class="material-symbols-outlined">search_off</span>
                                    No faculty match your search.
                                </div>
                            </div>

                            <!-- Pagination (10 per page, client-side; works together with the search box) -->
                            <div class="inv-pager" id="fac-pager" hidden>
                                <span class="inv-pager-info" id="fac-pager-info"></span>
                                <div class="inv-pager-ctl" id="fac-pager-ctl" role="navigation" aria-label="Faculty list pages">
                                    <button type="button" class="inv-pager-btn" id="fac-pg-prev" aria-label="Previous page">
                                        <span class="material-symbols-outlined">chevron_left</span>
                                    </button>
                                    <div class="inv-pager-nums" id="fac-pg-nums"></div>
                                    <button type="button" class="inv-pager-btn" id="fac-pg-next" aria-label="Next page">
                                        <span class="material-symbols-outlined">chevron_right</span>
                                    </button>
                                </div>
                            </div>
                        </div><!-- /faculty-list-card -->

                    </div><!-- /faculty-layout -->
                </div><!-- /panel-faculty -->
            <?php endif; ?>

            <!-- ============================================================
         TAB: ADMINS  (Super Admin only)
         Left: everyone with an admin account. Right: the selected admin's
         details, actions and activity history. Driven by the "Admin Accounts
         panel" block in admin-dashboard.js.
    ============================================================ -->
            <?php if ($is_super_admin): ?>
                <div class="tab-panel" id="panel-admins">

                    <div class="am-head">
                        <div class="am-head-text">
                            <h2 class="am-title">Admin Accounts</h2>
                            <p class="am-sub">Control who can sign in and review what each admin has been doing.</p>
                        </div>
                        <div class="am-head-actions">
                            <span class="am-seats" id="am-seats" title="Admin accounts in use">
                                <span class="material-symbols-outlined">group</span>
                                <span id="am-seats-text"><strong><?php echo (int)$admin_accounts_count; ?> of 5</strong> seats used</span>
                            </span>
                            <button type="button" class="ps-btn ps-btn--primary" id="am-add-btn"
                                <?php echo $admin_accounts_remaining > 0 ? '' : 'disabled title="All 5 seats are in use. Delete an account to free one."'; ?>>
                                <span class="material-symbols-outlined">person_add</span> Add admin
                            </button>
                        </div>
                    </div>

                    <div class="am-layout">

                        <!-- ── Admin list ── -->
                        <section class="eq-card am-list-card" aria-label="Admin accounts">
                            <div class="am-search">
                                <span class="material-symbols-outlined">search</span>
                                <input type="text" id="am-search" class="form-control-custom"
                                    placeholder="Search admins" autocomplete="off" aria-label="Search admins">
                            </div>
                            <div class="am-list" id="am-list" role="listbox" aria-label="Admins">
                                <?php foreach ($admin_accounts_list as $acct):
                                    $am_you     = strtolower($acct['email'] ?? '') === strtolower($admin_email);
                                    $am_until   = admin_is_dormant($acct['dormant_until'] ?? null) ? (int)$acct['dormant_until'] : 0;
                                    $am_manage  = !$am_you && ($acct['role'] ?? '') === 'Admin';
                                    $am_parts   = preg_split('/\s+/', trim((string)($acct['fullName'] ?? '')));
                                    $am_ini     = strtoupper(substr($am_parts[0] ?? '?', 0, 1));
                                    if (count($am_parts) > 1) $am_ini .= strtoupper(substr(end($am_parts), 0, 1));
                                    $am_created = !empty($acct['created_at']) ? date('M j, Y', strtotime($acct['created_at'])) : '';
                                    $am_login   = !empty($acct['last_login']) ? date('M j, Y, g:i A', strtotime($acct['last_login'])) : '';
                                ?>
                                    <button type="button" class="am-item" role="option" aria-selected="false"
                                        data-id="<?php echo (int)($acct['id'] ?? 0); ?>"
                                        data-name="<?php echo htmlspecialchars($acct['fullName'] ?? '', ENT_QUOTES); ?>"
                                        data-email="<?php echo htmlspecialchars($acct['email'] ?? '', ENT_QUOTES); ?>"
                                        data-role="<?php echo htmlspecialchars($acct['role'] ?? 'Admin', ENT_QUOTES); ?>"
                                        data-created="<?php echo htmlspecialchars($am_created, ENT_QUOTES); ?>"
                                        data-last-login="<?php echo htmlspecialchars($am_login, ENT_QUOTES); ?>"
                                        data-locked-until="<?php echo $am_until; ?>"
                                        data-you="<?php echo $am_you ? '1' : '0'; ?>"
                                        data-manage="<?php echo $am_manage ? '1' : '0'; ?>">
                                        <span class="am-avatar"><?php echo htmlspecialchars($am_ini); ?></span>
                                        <span class="am-item-main">
                                            <span class="am-item-name"><?php echo htmlspecialchars($acct['fullName'] ?? '—'); ?><?php if ($am_you): ?> <span class="am-you">You</span><?php endif; ?></span>
                                            <span class="am-item-email"><?php echo htmlspecialchars($acct['email'] ?? '—'); ?></span>
                                        </span>
                                        <span class="material-symbols-outlined am-item-lock" <?php echo $am_until ? '' : 'hidden'; ?> title="Locked">lock</span>
                                    </button>
                                <?php endforeach; ?>
                                <p class="am-list-empty" id="am-list-empty" <?php echo empty($admin_accounts_list) ? '' : 'hidden'; ?>>
                                    <?php echo empty($admin_accounts_list) ? 'No accounts found.' : 'No admins match your search.'; ?>
                                </p>
                            </div>
                        </section>

                        <!-- ── Selected admin ── -->
                        <section class="eq-card am-detail-card" id="am-detail" aria-live="polite">

                            <div class="am-empty" id="am-detail-empty">
                                <span class="material-symbols-outlined">manage_accounts</span>
                                <p>Select an admin to see their details and activity.</p>
                            </div>

                            <div id="am-detail-body" hidden>
                                <header class="am-profile">
                                    <span class="am-avatar am-avatar--lg" id="am-d-avatar"></span>
                                    <div class="am-profile-main">
                                        <h3 class="am-d-name" id="am-d-name"></h3>
                                        <p class="am-d-email" id="am-d-email"></p>
                                        <div class="am-tags">
                                            <span class="am-tag" id="am-d-role"></span>
                                            <span class="am-tag" id="am-d-status"></span>
                                        </div>
                                    </div>
                                    <div class="am-actions" id="am-d-actions" hidden>
                                        <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm" data-am-action="edit">
                                            <span class="material-symbols-outlined">edit</span> Edit
                                        </button>
                                        <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm" data-am-action="lock" id="am-lock-btn">
                                            <span class="material-symbols-outlined" id="am-lock-icon">lock</span>
                                            <span id="am-lock-label">Lock</span>
                                        </button>
                                        <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm am-danger" data-am-action="delete">
                                            <span class="material-symbols-outlined">delete</span> Delete
                                        </button>
                                    </div>
                                </header>

                                <p class="am-note" id="am-d-note" hidden>
                                    <span class="material-symbols-outlined">shield</span>
                                    <span id="am-d-note-text"></span>
                                </p>

                                <dl class="am-facts">
                                    <div>
                                        <dt>Last sign-in</dt>
                                        <dd id="am-f-login">—</dd>
                                    </div>
                                    <div>
                                        <dt>Added</dt>
                                        <dd id="am-f-created">—</dd>
                                    </div>
                                    <div>
                                        <dt>Actions logged</dt>
                                        <dd id="am-f-total">—</dd>
                                    </div>
                                </dl>

                                <div class="am-activity">
                                    <div class="am-activity-head">
                                        <div class="am-activity-title">
                                            <h4>Activity</h4>
                                            <button type="button" class="act-open" id="am-open-activity">
                                                Open in Activity <span class="material-symbols-outlined">arrow_forward</span>
                                            </button>
                                        </div>
                                        <div class="act-chips" id="am-chips" role="group" aria-label="Filter activity">
                                            <button type="button" class="act-chip active" data-filter="all">All</button>
                                            <button type="button" class="act-chip" data-filter="requests">Requests</button>
                                            <button type="button" class="act-chip" data-filter="rooms">Facilities</button>
                                            <button type="button" class="act-chip" data-filter="equipment">Equipment</button>
                                            <button type="button" class="act-chip" data-filter="system">System</button>
                                            <button type="button" class="act-chip" data-filter="signins">Sign-ins</button>
                                        </div>
                                    </div>
                                    <div class="act-feed" id="am-feed"></div>
                                    <div class="act-pager" id="am-pager" hidden>
                                        <span class="act-pager-info"></span>
                                        <div class="act-pager-ctl">
                                            <button type="button" class="act-pager-btn" data-pg="prev" aria-label="Previous page">
                                                <span class="material-symbols-outlined">chevron_left</span>
                                            </button>
                                            <div class="act-pager-nums"></div>
                                            <button type="button" class="act-pager-btn" data-pg="next" aria-label="Next page">
                                                <span class="material-symbols-outlined">chevron_right</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </section>
                    </div>
                </div><!-- /panel-admins -->
            <?php endif; ?>

            <!-- ============================================================
         TAB: ACTIVITY  (every admin)
         One timeline for everything that happens: manual decisions (with the
         admin's name) and automatic ones from the rules engine. Filters are
         type chips + Who + When. Driven by the "Activity page" block in
         admin-dashboard.js and api/admin-activity.php.
    ============================================================ -->
            <div class="tab-panel" id="panel-activity">

                <div class="act-head">
                    <h2 class="act-title">Activity</h2>
                    <p class="act-sub">Every decision and change, and who made it. Automatic decisions come from the rules engine.</p>
                </div>

                <section class="eq-card act-card" aria-label="Activity timeline">
                    <div class="act-toolbar">
                        <div class="act-chips" id="act-chips" role="group" aria-label="Filter by type">
                            <button type="button" class="act-chip active" data-filter="all">All</button>
                            <button type="button" class="act-chip" data-filter="requests">Requests</button>
                            <button type="button" class="act-chip" data-filter="rooms">Facilities</button>
                            <button type="button" class="act-chip" data-filter="equipment">Equipment</button>
                            <button type="button" class="act-chip" data-filter="system">System</button>
                            <button type="button" class="act-chip" data-filter="signins">Sign-ins</button>
                        </div>
                        <div class="act-selects">
                            <select id="act-actor" class="form-control-custom" aria-label="Who">
                                <option value="all">Everyone</option>
                                <option value="auto">Automatic</option>
                                <option value="me">Me</option>
                                <?php foreach ($admin_accounts_list as $act_acct):
                                    if (strtolower($act_acct['email'] ?? '') === strtolower($admin_email)) continue; ?>
                                    <option value="<?php echo (int)($act_acct['id'] ?? 0); ?>"><?php echo htmlspecialchars($act_acct['fullName'] ?? ''); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="act-range" class="form-control-custom" aria-label="When">
                                <option value="today">Today</option>
                                <option value="7d">Last 7 days</option>
                                <option value="30d" selected>Last 30 days</option>
                                <option value="all">All time</option>
                            </select>
                            <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm act-export" id="act-export"
                                title="Download what you see here as a spreadsheet (CSV)">
                                <span class="material-symbols-outlined">download</span> Export
                            </button>
                        </div>
                    </div>

                    <div class="act-feed" id="act-feed"></div>
                    <div class="act-pager" id="act-pager" hidden>
                        <span class="act-pager-info"></span>
                        <div class="act-pager-ctl">
                            <button type="button" class="act-pager-btn" data-pg="prev" aria-label="Previous page">
                                <span class="material-symbols-outlined">chevron_left</span>
                            </button>
                            <div class="act-pager-nums"></div>
                            <button type="button" class="act-pager-btn" data-pg="next" aria-label="Next page">
                                <span class="material-symbols-outlined">chevron_right</span>
                            </button>
                        </div>
                    </div>
                </section>
            </div><!-- /panel-activity -->





            <!-- ============================================================
         TAB: INVENTORY
    ============================================================ -->
            <div class="tab-panel" id="panel-inventory">

                <!-- ── INVENTORY SCREEN (REDESIGNED) ─────────────────── -->
                <div id="lending-inventory">

                    <!-- Page Header -->
                    <div class="inv-redesign-header">
                        <div>
                            <h1 class="inv-page-title">Equipment Inventory</h1>
                            <p class="inv-page-sub">Manage the equipment catalog, stock quantities, and item details.</p>
                        </div>
                    </div>

                    <?php
                    // ── On Loan tab data (read-only; does not touch the shared $approved_result / $waiting_result) ──
                    require_once __DIR__ . '/equipment-booking/core/booking-schedule.php';
                    BookingSchedule::ensureSchema($conn);   // later-date booking columns (idempotent, same call the return handler uses)
                    $eq_today        = date('Y-m-d');
                    $eq_now          = date('Y-m-d H:i:s');   // Asia/Manila
                    $eq_loan_rows    = [];
                    $eq_loan_overdue = 0;
                    $eq_loan_q = mysqli_query($conn, "SELECT r.*,
                            (SELECT i.`condition` FROM tbl_inventory i WHERE i.item_name = r.equipment_name LIMIT 1) AS item_condition
                        FROM tbl_requests r
                        WHERE r.status IN ('Approved','Overdue')
                          AND NOT COALESCE((r.status = 'Approved'
                                AND COALESCE(r.booking_mode, '') = 'future'
                                AND TIMESTAMP(r.borrow_date, COALESCE(r.borrow_time, '00:00:00')) > '{$eq_now}'), 0)
                        ORDER BY r.return_date ASC, r.id ASC");
                    if ($eq_loan_q) {
                        while ($lr = mysqli_fetch_assoc($eq_loan_q)) {
                            $lr['_days']  = (int)floor((strtotime($eq_today) - strtotime($lr['return_date'])) / 86400);
                            $lr['_state'] = ($lr['status'] === 'Overdue' || $lr['_days'] > 0) ? 'overdue' : ($lr['_days'] === 0 ? 'due' : 'ok');
                            if ($lr['_state'] === 'overdue') $eq_loan_overdue++;
                            $eq_loan_rows[] = $lr;
                        }
                    }
                    $eq_loan_total  = count($eq_loan_rows);
                    $eq_loan_active = $eq_loan_total - $eq_loan_overdue;
                    // Returned history for the Returned / All tabs (newest first, last 100)
                    $eq_ret_rows = [];
                    $eq_ret_q = mysqli_query($conn, "SELECT r.*,
                            (SELECT i.`condition` FROM tbl_inventory i WHERE i.item_name = r.equipment_name LIMIT 1) AS item_condition
                        FROM tbl_requests r
                        WHERE r.status = 'Returned'
                        ORDER BY COALESCE(r.returned_at, r.return_date) DESC, r.id DESC
                        LIMIT 100");
                    if ($eq_ret_q) {
                        while ($rr = mysqli_fetch_assoc($eq_ret_q)) {
                            $rr['_days']  = 0;
                            $rr['_state'] = 'returned';
                            $eq_ret_rows[] = $rr;
                        }
                    }
                    $eq_ret_total  = count($eq_ret_rows);
                    // Waiting (Pending Review) rows — supporting-letter requests from
                    // Organization Advisers are visible to a Super Admin only. Rows with a document come first.
                    $eq_wait_sql = "SELECT tbl_requests.*,
                            (SELECT i.`condition` FROM tbl_inventory i WHERE i.item_name = tbl_requests.equipment_name LIMIT 1) AS item_condition
                        FROM tbl_requests
                        WHERE tbl_requests.status = 'Waiting'";
                    if (empty($can_view_sensitive_adviser_requests)) {
                        $eq_wait_sql .= " AND NOT {$sensitive_adviser_request_sql}";
                    }
                    $eq_wait_sql .= " ORDER BY tbl_requests.request_date DESC";
                    $eq_wait_with = [];
                    $eq_wait_without = [];
                    $eq_wait_q = mysqli_query($conn, $eq_wait_sql);
                    if ($eq_wait_q) {
                        while ($wr = mysqli_fetch_assoc($eq_wait_q)) {
                            $wr['_days']  = 0;
                            $wr['_state'] = 'waiting';
                            $wr['_doc']   = ps_doc_info($wr['document_path'] ?? '');
                            if ($wr['_doc']) $eq_wait_with[] = $wr;
                            else $eq_wait_without[] = $wr;
                        }
                    }
                    $eq_wait_rows  = array_merge($eq_wait_with, $eq_wait_without);
                    $eq_pend_total = count($eq_wait_rows);   // pending-review requests only
                    // Scheduled (later-date) bookings: approved by default but not started yet. They wait here
                    // until their start time (then they move to Active) and an admin can cancel / revoke them.
                    $eq_sched_rows = [];
                    $eq_sched_q = mysqli_query($conn, "SELECT tbl_requests.*,
                            (SELECT i.`condition` FROM tbl_inventory i WHERE i.item_name = tbl_requests.equipment_name LIMIT 1) AS item_condition
                        FROM tbl_requests
                        WHERE tbl_requests.status = 'Approved'
                          AND COALESCE(tbl_requests.booking_mode, '') = 'future'
                          AND TIMESTAMP(tbl_requests.borrow_date, COALESCE(tbl_requests.borrow_time, '00:00:00')) > '{$eq_now}'
                        ORDER BY tbl_requests.borrow_date ASC, tbl_requests.borrow_time ASC, tbl_requests.id ASC");
                    if ($eq_sched_q) {
                        while ($sr = mysqli_fetch_assoc($eq_sched_q)) {
                            $sr['_days']  = (int)floor((strtotime($sr['borrow_date']) - strtotime($eq_today)) / 86400);   // days until it starts
                            $sr['_state'] = 'scheduled';
                            $eq_sched_rows[] = $sr;
                        }
                    }
                    $eq_wait_rows  = array_merge($eq_wait_rows, $eq_sched_rows);
                    $eq_wait_total = count($eq_wait_rows);
                    $eq_view_rows  = array_merge($eq_wait_rows, $eq_loan_rows, $eq_ret_rows);
                    $eq_view_total = count($eq_view_rows);
                    $eq_loan_dec   = admin_request_decisions($conn, array_map(function ($x) {
                        return (int)$x['id'];
                    }, $eq_view_rows));
                    ?>

                    <!-- Equipment sub-tabs: Catalog (the existing screen, unchanged) | On Loan -->
                    <div class="rq-filter-chips eq-tabs" id="eqTabs">
                        <button type="button" class="rq-filter-chip active" data-eq-panel="eq-catalog">
                            <span class="material-symbols-outlined eq-tab-ico" aria-hidden="true">inventory_2</span> Catalog
                        </button>
                        <button type="button" class="rq-filter-chip" data-eq-panel="eq-onloan">
                            <span class="material-symbols-outlined eq-tab-ico" aria-hidden="true">assignment_return</span> Loans
                            <?php if ($eq_loan_total > 0): ?>
                                <span class="rq-chip-count<?php echo $eq_loan_overdue > 0 ? '' : ' rq-chip-count--ok'; ?>" id="eqLoanChipCount"><?php echo $eq_loan_total; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <!-- ── CATALOG (existing form + list + archived, wrapped as-is) ── -->
                    <div class="eq-sub-panel active" id="eq-catalog">

                        <!-- Equipment layout: Add form (left) + All Equipment list (right).
                         Same two-column pattern as the Faculty tab (.faculty-layout). -->
                        <div class="faculty-layout inv-layout">

                            <!-- ADD EQUIPMENT FORM -->
                            <div class="eq-card inv-form-card" id="item-form-wrap">
                                <div class="eq-card-header">
                                    <h2>
                                        <span class="material-symbols-outlined inv-head-icon">add_box</span>
                                        <span id="form-title"><?php echo $edit_item ? 'Edit Equipment' : 'Add Equipment'; ?></span>
                                    </h2>
                                </div>
                                <div class="eq-card-body">
                                    <form method="POST" enctype="multipart/form-data" id="itemForm">
                                        <?= csrf_field() ?>
                                        <?php if ($edit_item): ?>
                                            <input type="hidden" name="item_id"
                                                value="<?php echo $edit_item['item_id']; ?>">
                                            <input type="hidden" name="old_image"
                                                value="<?php echo htmlspecialchars($edit_item['image_path']); ?>">
                                        <?php endif; ?>

                                        <div class="fac-edit-section inv-section--first">Details</div>

                                        <div class="form-group">
                                            <label for="inv-item-name">Item name <span class="req-star">*</span></label>
                                            <input type="text" id="inv-item-name" name="item_name"
                                                class="form-control-custom" maxlength="150"
                                                value="<?php echo $edit_item ? htmlspecialchars($edit_item['item_name']) : ''; ?>"
                                                placeholder="e.g. Extension Cord" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="inv-category">Category <span class="req-star">*</span></label>
                                            <select id="inv-category" name="category"
                                                class="form-control-custom inv-category-select" required>
                                                <option value="">Select category...</option>
                                                <?php
                                                $cats = ['Audio/Visual', 'Cables & Connectors', 'Computing', 'Lab Equipment', 'Networking', 'Power', 'Tools', 'Others'];
                                                foreach ($cats as $c) {
                                                    $sel = ($edit_item && $edit_item['category'] === $c) ? 'selected' : '';
                                                    echo '<option value="' . htmlspecialchars($c) . '" ' . $sel . '>' . htmlspecialchars($c) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <div class="fac-edit-section">Stock</div>

                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="inv-quantity">Quantity <span class="req-star">*</span></label>
                                                <input type="number" id="inv-quantity" name="quantity"
                                                    class="form-control-custom" min="0"
                                                    value="<?php echo $edit_item ? (int)$edit_item['quantity'] : '1'; ?>"
                                                    required>
                                            </div>
                                            <div class="form-group">
                                                <label for="inv-condition">Condition</label>
                                                <select id="inv-condition" name="condition" class="form-control-custom">
                                                    <?php
                                                    $cur_condition = $edit_item['condition'] ?? 'Good';
                                                    foreach (['Good', 'Fair', 'For Repair'] as $condOpt) {
                                                        $sel = ($cur_condition === $condOpt) ? 'selected' : '';
                                                        echo "<option value=\"$condOpt\" $sel>$condOpt</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="fac-edit-section">Image <span class="inv-opt">(optional)</span></div>

                                        <div class="form-group">
                                            <div class="drop-zone inv-drop" id="dropZone">
                                                <span class="material-symbols-outlined">add_photo_alternate</span>
                                                <p>Click, drag &amp; drop, or paste an image</p>
                                                <p class="inv-drop-sub">JPG or PNG, up to 2 MB</p>
                                                <input type="file" name="item_image" id="itemImageInput"
                                                    accept="image/jpeg,image/png" hidden>
                                                <?php if ($edit_item && $edit_item['image_path'] !== 'uploads/default.png'): ?>
                                                    <img src="<?php echo $root_url . htmlspecialchars($edit_item['image_path']); ?>"
                                                        class="drop-zone-preview" id="imagePreview" style="display:block;" alt="">
                                                <?php else: ?>
                                                    <img id="imagePreview" class="drop-zone-preview" alt="">
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" id="removeImageBtn"
                                                class="inv-remove-img<?php echo ($edit_item && $edit_item['image_path'] !== 'uploads/default.png') ? '' : ' hidden'; ?>">
                                                &#x2715; Remove image
                                            </button>
                                        </div>

                                        <div class="inv-form-actions">
                                            <button type="submit"
                                                name="<?php echo $edit_item ? 'update_item' : 'add_item'; ?>"
                                                class="ps-btn ps-btn--primary inv-submit">
                                                <span class="material-symbols-outlined">save</span>
                                                <?php echo $edit_item ? 'Update Item' : 'Save Equipment'; ?>
                                            </button>
                                            <?php if ($edit_item): ?>
                                                <a href="admin-dashboard.php?delete_item=<?php echo $edit_item['item_id']; ?>"
                                                    class="btn-inv-delete" title="Archive item"> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                        fill="none" stroke="currentColor" stroke-width="2"
                                                        stroke-linecap="round" stroke-linejoin="round"
                                                        width="15" height="15">
                                                        <polyline points="3 6 5 6 21 6" />
                                                        <path d="M19 6l-1 14H6L5 6" />
                                                        <path d="M10 11v6" />
                                                        <path d="M14 11v6" />
                                                        <path d="M9 6V4h6v2" />
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                        </div>

                                    </form>
                                </div><!-- /eq-card-body -->
                            </div><!-- /inv-form-card -->


                            <!-- ALL EQUIPMENT LIST -->
                            <div class="eq-card inv-list-card" id="inv-table-card">
                                <div class="eq-card-header fac-list-head">
                                    <h2>
                                        <span class="material-symbols-outlined inv-head-icon">inventory_2</span>
                                        All Equipment
                                        <span class="fac-count-badge" id="inv-count-badge">(<?php echo (int)mysqli_num_rows($inventory_result); ?>)</span>
                                    </h2>
                                    <div class="fac-search">
                                        <span class="material-symbols-outlined">search</span>
                                        <input type="text" id="inventorySearch" class="form-control-custom"
                                            placeholder="Search name or category" autocomplete="off">
                                    </div>
                                </div>
                                <div class="tbl-wrap">
                                    <table class="admin-table fac-table inv-table" id="inv-list-table">
                                        <thead>
                                            <tr>
                                                <th>Equipment</th>
                                                <th>Category</th>
                                                <th>Condition</th>
                                                <th>Stock</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="inventory-body">
                                            <?php
                                            mysqli_data_seek($inventory_result, 0);
                                            if (mysqli_num_rows($inventory_result) === 0): ?>
                                                <tr id="inv-empty-row">
                                                    <td colspan="5" class="inv-empty-cell">
                                                        <span class="material-symbols-outlined">inventory_2</span>
                                                        No equipment yet. Add your first item using the form.
                                                    </td>
                                                </tr>
                                                <?php else:
                                                while ($item = mysqli_fetch_assoc($inventory_result)):
                                                    $inv_qty        = (int)$item['quantity'];
                                                    $inv_cond       = $item['condition'] ?? 'Good';
                                                    $inv_condClass  = ($inv_cond === 'For Repair') ? 'repair' : (($inv_cond === 'Fair') ? 'fair' : 'good');
                                                    if ($inv_qty === 0) {
                                                        $inv_stockClass = 'none';
                                                        $inv_stockLabel = 'No stock';
                                                    } elseif ($inv_qty <= 2) {
                                                        $inv_stockClass = 'low';
                                                        $inv_stockLabel = 'Low stock';
                                                    } else {
                                                        $inv_stockClass = 'ok';
                                                        $inv_stockLabel = 'In stock';
                                                    }
                                                    $inv_addedTs    = !empty($item['created_at']) ? strtotime($item['created_at']) : false;
                                                ?>
                                                    <tr class="inv-row-item"
                                                        data-item-id="<?php echo (int)$item['item_id']; ?>"
                                                        data-item-name="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>"
                                                        data-item-category="<?php echo htmlspecialchars($item['category'], ENT_QUOTES); ?>"
                                                        data-item-quantity="<?php echo $inv_qty; ?>"
                                                        data-item-condition="<?php echo htmlspecialchars($inv_cond, ENT_QUOTES); ?>"
                                                        data-item-image="<?php echo htmlspecialchars($item['image_path'], ENT_QUOTES); ?>"
                                                        data-item-image-full="<?php echo htmlspecialchars($root_url . $item['image_path'], ENT_QUOTES); ?>">
                                                        <td>
                                                            <div class="fac-person">
                                                                <span class="inv-thumb">
                                                                    <img src="<?php echo $root_url . htmlspecialchars($item['image_path']); ?>"
                                                                        alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                                        loading="lazy">
                                                                    <span class="material-symbols-outlined inv-thumb-ph">inventory_2</span>
                                                                </span>
                                                                <div class="fac-person-text">
                                                                    <div class="fac-person-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                                                    <?php if ($inv_addedTs): ?>
                                                                        <div class="fac-person-sub">Added <?php echo date('M j, Y', $inv_addedTs); ?></div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><span class="inv-pill inv-pill--cat"><?php echo htmlspecialchars($item['category']); ?></span></td>
                                                        <td><span class="inv-pill inv-pill--<?php echo $inv_condClass; ?>"><?php echo htmlspecialchars($inv_cond); ?></span></td>
                                                        <td>
                                                            <span class="inv-pill inv-pill--stock inv-pill--<?php echo $inv_stockClass; ?>"><strong><?php echo $inv_qty; ?></strong> <?php echo $inv_stockLabel; ?></span>
                                                        </td>
                                                        <td class="fac-actions">
                                                            <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm inv-edit-btn"
                                                                data-action="eq-open-edit" aria-label="Edit equipment">
                                                                <span class="material-symbols-outlined">edit</span>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                                <tr id="inv-nomatch-row" class="inv-row-hidden">
                                                    <td colspan="5" class="inv-empty-cell">
                                                        <span class="material-symbols-outlined">search_off</span>
                                                        No equipment matches your search.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination (10 per page, client-side; works together with the search box) -->
                                <div class="inv-pager" id="inv-pager" hidden>
                                    <span class="inv-pager-info" id="inv-pager-info"></span>
                                    <div class="inv-pager-ctl" id="inv-pager-ctl">
                                        <button type="button" class="inv-pager-btn" id="inv-pg-prev" aria-label="Previous page">
                                            <span class="material-symbols-outlined">chevron_left</span>
                                        </button>
                                        <div class="inv-pager-nums" id="inv-pg-nums"></div>
                                        <button type="button" class="inv-pager-btn" id="inv-pg-next" aria-label="Next page">
                                            <span class="material-symbols-outlined">chevron_right</span>
                                        </button>
                                    </div>
                                </div>
                            </div><!-- /inv-list-card -->

                        </div><!-- /faculty-layout inv-layout -->

                        <!-- Archived Items -->
                        <div class="inv-archived-wrap">
                            <div class="history-toggle-wrap inv-arch-toggle" id="registry-toggle-wrap">
                                <button class="history-toggle-btn" data-history-tab="reg-archived">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" width="14" height="14">
                                        <polyline points="21 8 21 21 3 21 3 8" />
                                        <rect x="1" y="3" width="22" height="5" />
                                        <line x1="10" y1="12" x2="14" y2="12" />
                                    </svg>
                                    Archived Items
                                </button>
                            </div>
                            <div class="history-panel" id="history-reg-archived">
                                <div class="eq-card">
                                    <div class="tbl-wrap">
                                        <table class="admin-table">
                                            <thead>
                                                <tr>
                                                    <th>Image</th>
                                                    <th>Item Name</th>
                                                    <th>Category</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="inv-archive-body">
                                                <?php if (mysqli_num_rows($archive_result) === 0): ?>
                                                    <tr>
                                                        <td colspan="4" class="text-muted"
                                                            style="text-align:center;padding:2.5rem;">
                                                            No archived items.
                                                        </td>
                                                    </tr>
                                                    <?php else: while ($item = mysqli_fetch_assoc($archive_result)): ?>
                                                        <tr>
                                                            <td>
                                                                <img src="<?php echo $root_url . htmlspecialchars($item['image_path']); ?>"
                                                                    class="item-img"
                                                                    onerror="this.src='../uploads/default.png'">
                                                            </td>
                                                            <td class="fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                                                            <td class="action-cell">
                                                                <div class="action-btns">
                                                                    <a href="admin-dashboard.php?restore_item=<?php echo $item['item_id']; ?>"
                                                                        class="btn-action btn-restore" title="Restore">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                            fill="none" stroke="currentColor" stroke-width="2"
                                                                            stroke-linecap="round" stroke-linejoin="round"
                                                                            width="14" height="14">
                                                                            <polyline points="1 4 1 10 7 10" />
                                                                            <path d="M3.51 15a9 9 0 1 0 .49-3.51" />
                                                                        </svg>
                                                                    </a>
                                                                    <a href="admin-dashboard.php?force_delete=<?php echo $item['item_id']; ?>"
                                                                        class="btn-action btn-force-del" title="Delete permanently"
                                                                        onclick="return confirm('Permanently delete? This cannot be undone.')">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                            fill="none" stroke="currentColor" stroke-width="2"
                                                                            stroke-linecap="round" stroke-linejoin="round"
                                                                            width="14" height="14">
                                                                            <polyline points="3 6 5 6 21 6" />
                                                                            <path d="M19 6l-1 14H6L5 6" />
                                                                            <path d="M10 11v6" />
                                                                            <path d="M14 11v6" />
                                                                            <path d="M9 6V4h6v2" />
                                                                        </svg>
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                <?php endwhile;
                                                endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div><!-- /history-reg-archived -->
                        </div><!-- /inv-archived-wrap -->

                    </div><!-- /eq-catalog -->

                    <!-- ── ON LOAN (equipment currently out: Approved + Overdue) ───────────── -->
                    <div class="eq-sub-panel" id="eq-onloan">
                        <!-- Overdue warning: no inline banner here any more. It pops up at the bottom-centre of the
                             screen when this tab is opened (announceOverdue() in admin-dashboard.js). -->
                        <div class="ps-card">
                            <div class="ps-card-header eq-loan-head">
                                <!-- Table title: text changes with the selected tab (see TITLES in admin-dashboard.js) -->
                                <h2 class="eq-loan-title" id="eqLoanTitle">All Loans</h2>
                                <!-- Loan status tabs live inside the table header -->
                                <div class="eq-loan-tabs" id="eqLoanFilter" role="tablist" aria-label="Filter loans by status">
                                    <button type="button" class="eq-loan-tab active" data-loan-filter="all" role="tab" aria-selected="true">All</button>
                                    <button type="button" class="eq-loan-tab" data-loan-filter="waiting" role="tab" aria-selected="false">Waiting <span class="eq-loan-tab-count<?php echo $eq_pend_total > 0 ? ' has' : ''; ?>"><?php echo (int)$eq_wait_total; ?></span></button>
                                    <button type="button" class="eq-loan-tab" data-loan-filter="active" role="tab" aria-selected="false">Active <span class="eq-loan-tab-count"><?php echo (int)$eq_loan_active; ?></span></button>
                                    <button type="button" class="eq-loan-tab" data-loan-filter="overdue" role="tab" aria-selected="false">Overdue <span class="eq-loan-tab-count<?php echo $eq_loan_overdue > 0 ? ' has' : ''; ?>"><?php echo (int)$eq_loan_overdue; ?></span></button>
                                    <button type="button" class="eq-loan-tab" data-loan-filter="returned" role="tab" aria-selected="false">Returned</button>
                                </div>
                                <div class="eq-loan-tools">
                                    <input class="ps-form-control" style="width:230px" placeholder="Search borrower or item..." id="eq-loan-search" autocomplete="off">
                                    <?php if ($is_super_admin): ?>
                                        <!-- Super Admin only: deletes the whole list after a password check (see the modal near the bottom of this file) -->
                                        <button type="button" class="ps-btn ps-btn--danger" id="eq-loan-wipe-btn"
                                            title="Permanently delete every record in this list" <?php echo $eq_view_total === 0 ? ' disabled' : ''; ?>>
                                            <span class="material-symbols-outlined">delete_forever</span> Delete All
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="ps-table-wrap">
                                <table class="ps-table" id="eq-loan-table">
                                    <thead>
                                        <tr>
                                            <th>Borrower</th>
                                            <th>Equipment</th>
                                            <th>Issued</th>
                                            <th>Due</th>
                                            <th>Status</th>
                                            <th>Handled by</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($eq_view_total > 0):
                                            foreach ($eq_view_rows as $r):
                                                $lqty  = max(1, (int)($r['borrow_qty'] ?? 1));
                                                $ltype = ps_submitted_as_label($r['submitted_as'] ?? '');
                                                $ldays = (int)$r['_days'];
                                                $lst   = $r['_state'];
                                                $ldec  = $lst === 'returned'
                                                    ? ($eq_loan_dec[(int)$r['id']]['returned'] ?? null)
                                                    : ($eq_loan_dec[(int)$r['id']]['approved'] ?? null);
                                                $lret  = !empty($r['returned_at']) ? $r['returned_at'] : $r['return_date'];
                                                $ldoc  = $r['_doc'] ?? null;
                                                $lbt   = !empty($r['borrow_time']) ? date('g:i A', strtotime($r['borrow_time'])) : '';
                                                $lrt   = !empty($r['return_time']) ? date('g:i A', strtotime($r['return_time'])) : '';
                                                $lstart_lbl = date('M d, Y', strtotime($r['borrow_date'])) . ($lbt !== '' ? ' · ' . $lbt : '');
                                                $ldue_lbl   = date('M d, Y', strtotime($r['return_date'])) . ($lrt !== '' ? ' · ' . $lrt : '');
                                                // Same confidentiality rule as admin-override.php: adviser requests with a supporting
                                                // document can only be changed by a Super Admin.
                                                $lsens = (string)($r['submitted_as'] ?? '') === 'adviser' && trim((string)($r['document_path'] ?? '')) !== '';
                                                $lcan_cancel = !$lsens || !empty($can_view_sensitive_adviser_requests);
                                        ?>
                                                <tr class="eq-loan-row is-<?php echo $lst; ?>"
                                                    data-id="<?php echo (int)$r['id']; ?>"
                                                    data-status="<?php echo $lst === 'scheduled' ? 'Scheduled' : htmlspecialchars($r['status']); ?>"
                                                    data-loan-state="<?php echo $lst; ?>"
                                                    data-start-label="<?php echo htmlspecialchars($lstart_lbl); ?>"
                                                    data-due-label="<?php echo htmlspecialchars($ldue_lbl); ?>"
                                                    data-days-overdue="<?php echo max(0, $ldays); ?>"
                                                    data-condition="<?php echo htmlspecialchars($r['item_condition'] ?? 'Good'); ?>"
                                                    data-borrower="<?php echo htmlspecialchars($r['faculty_name']); ?>"
                                                    data-id-number="<?php echo htmlspecialchars(faculty_id_display($r['faculty_id'], 'Not set')); ?>"
                                                    data-req-type="<?php echo htmlspecialchars($ltype); ?>"
                                                    data-equipment="<?php echo htmlspecialchars($r['equipment_name']); ?>"
                                                    data-qty="<?php echo $lqty; ?>"
                                                    data-instructor="<?php echo htmlspecialchars($r['instructor']); ?>"
                                                    data-room="<?php echo htmlspecialchars($r['room']); ?>"
                                                    data-submitted="<?php echo date('M d, Y g:i A', strtotime($r['request_date'])); ?>"
                                                    data-date-needed="<?php echo date('M d, Y', strtotime($r['borrow_date'])); ?>"
                                                    data-return-date="<?php echo htmlspecialchars($r['return_date']); ?>"
                                                    data-return-date-display="<?php echo date('M d, Y', strtotime($r['return_date'])); ?>"
                                                    data-arb-rule="<?php echo htmlspecialchars($r['arbitration_rule'] ?? ''); ?>"
                                                    data-note="<?php echo htmlspecialchars($r['reason'] ?? ''); ?>"
                                                    data-doc-url="<?php echo $ldoc ? htmlspecialchars($ldoc['url']) : ''; ?>"
                                                    data-doc-open-url="<?php echo $ldoc ? htmlspecialchars($ldoc['open_url']) : ''; ?>"
                                                    data-doc-name="<?php echo $ldoc ? htmlspecialchars($ldoc['name']) : ''; ?>"
                                                    data-doc-kind="<?php echo $ldoc ? $ldoc['kind'] : ''; ?>">
                                                    <td>
                                                        <div style="font-weight:600"><?php echo htmlspecialchars($r['faculty_name']); ?></div>
                                                        <div style="font-size:11px;color:var(--text-light)"><?php echo htmlspecialchars(faculty_id_display($r['faculty_id'], 'Not set')); ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="ps-appr-item">
                                                            <?php echo htmlspecialchars($r['equipment_name']); ?>
                                                            <?php if ($lqty > 1): ?><span class="ps-appr-qty">&times;<?php echo $lqty; ?></span><?php endif; ?>
                                                        </div>
                                                        <button type="button" class="ps-appr-id" data-action="ps-open-modal" data-modal="ps-req-detail-modal" title="View request details">#<?php echo str_pad($r['id'], 4, '0', STR_PAD_LEFT); ?></button>
                                                        <?php if ($lst === 'waiting'): ?>
                                                            <?php if ($ldoc): ?>
                                                                <a class="ps-doc-chip ps-doc-chip--<?php echo $ldoc['kind']; ?>"
                                                                    href="<?php echo htmlspecialchars($ldoc['open_url']); ?>" target="_blank" rel="noopener noreferrer"
                                                                    title="Open <?php echo htmlspecialchars($ldoc['name']); ?> in a new tab">
                                                                    <span class="material-symbols-outlined"><?php echo ps_doc_icon($ldoc['kind']); ?></span>
                                                                    <span class="ps-doc-chip__name"><?php echo htmlspecialchars($ldoc['name']); ?></span>
                                                                    <span class="material-symbols-outlined ps-doc-chip__open">open_in_new</span>
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="ps-doc-chip ps-doc-chip--none"><span class="material-symbols-outlined">block</span> No document</span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M d', strtotime($r['borrow_date'])); ?>
                                                        <?php if ($lbt !== ''): ?><div class="ps-appr-sub"><?php echo $lbt; ?></div><?php endif; ?>
                                                    </td>
                                                    <td style="<?php echo $lst === 'overdue' ? 'color:var(--danger);font-weight:600' : ''; ?>">
                                                        <?php echo date('M d', strtotime($r['return_date'])); ?>
                                                        <?php if ($lrt !== ''): ?><div class="ps-appr-sub" style="font-weight:400"><?php echo $lrt; ?></div><?php endif; ?>
                                                        <?php if ((int)($r['extension_count'] ?? 0) > 0):
                                                            // Extended by the faculty member after it was due (equipment-booking/core/borrow-extension.php)
                                                            $lext  = (int)$r['extension_count'];
                                                            $lorig = !empty($r['original_return_date'])
                                                                ? date('M d, Y', strtotime($r['original_return_date'])) . (!empty($r['original_return_time']) ? ' · ' . date('g:i A', strtotime($r['original_return_time'])) : '')
                                                                : '';
                                                        ?>
                                                            <div class="ps-appr-sub" style="font-weight:400" title="<?php echo htmlspecialchars('Extended ' . $lext . ' time' . ($lext === 1 ? '' : 's') . ($lorig !== '' ? '. Originally due ' . $lorig : '')); ?>">Extended &times;<?php echo $lext; ?><?php if (!empty($r['original_return_date'])): ?> &middot; was <?php echo htmlspecialchars(date('M d', strtotime($r['original_return_date']))); ?><?php endif; ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($lst === 'waiting'): ?>
                                                            <span class="ps-badge ps-badge--dot ps-badge--waiting">Pending Review</span>
                                                        <?php elseif ($lst === 'scheduled'): ?>
                                                            <span class="ps-badge ps-badge--scheduled">Scheduled</span>
                                                            <div class="ps-appr-sub"><?php echo $ldays > 0 ? 'Starts in ' . $ldays . ' day' . ($ldays === 1 ? '' : 's') : 'Starts today'; ?></div>
                                                        <?php elseif ($lst === 'overdue'): ?>
                                                            <span class="ps-badge ps-badge--overdue"><?php echo max(1, $ldays); ?> day<?php echo max(1, $ldays) === 1 ? '' : 's'; ?> overdue</span>
                                                        <?php elseif ($lst === 'due'): ?>
                                                            <span class="ps-badge ps-badge--waiting">Due today</span>
                                                        <?php elseif ($lst === 'returned'): ?>
                                                            <span class="ps-badge ps-badge--returned">Returned</span>
                                                            <div class="ps-appr-sub"><?php echo date('M d, Y', strtotime($lret)); ?></div>
                                                        <?php else: ?>
                                                            <span class="ps-badge ps-badge--active">Due in <?php echo abs($ldays); ?> day<?php echo abs($ldays) === 1 ? '' : 's'; ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo admin_decider_html($ldec); ?></td>
                                                    <td>
                                                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                                                            <?php if ($lst === 'waiting'): ?>
                                                                <button type="button" class="ps-btn ps-btn--success ps-btn--sm"
                                                                    data-action="ps-open-modal" data-modal="ps-approve-modal">
                                                                    <span class="material-symbols-outlined">check</span> Approve
                                                                </button>
                                                                <button type="button" class="ps-btn ps-btn--danger ps-btn--sm"
                                                                    data-action="ps-open-modal" data-modal="ps-decline-modal">
                                                                    <span class="material-symbols-outlined">close</span> Decline
                                                                </button>
                                                            <?php elseif ($lst === 'scheduled'): ?>
                                                                <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm"
                                                                    data-action="ps-open-modal" data-modal="ps-req-detail-modal">View</button>
                                                                <?php if ($lcan_cancel): ?>
                                                                    <button type="button" class="ps-btn ps-btn--danger ps-btn--sm"
                                                                        data-action="ps-open-modal" data-modal="ps-cancel-booking-modal">
                                                                        <span class="material-symbols-outlined">event_busy</span> Cancel Booking
                                                                    </button>
                                                                <?php else: ?>
                                                                    <span class="ps-appr-sub" title="Organization Adviser requests with a supporting document can only be changed by a Super Admin">Super Admin only</span>
                                                                <?php endif; ?>
                                                            <?php elseif ($lst === 'returned'): ?>
                                                                <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm"
                                                                    data-action="ps-open-modal" data-modal="ps-req-detail-modal">View</button>
                                                            <?php else: ?>
                                                                <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm"
                                                                    data-action="ps-open-modal" data-modal="ps-return-modal">Manual Return</button>
                                                                <?php if ($lst === 'overdue'): ?>
                                                                    <button type="button" class="ps-btn ps-btn--outline ps-btn--sm"
                                                                        data-action="ps-open-modal" data-modal="ps-notice-modal">Send Notice</button>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach;
                                        else: ?>
                                            <tr class="eq-loan-empty">
                                                <td colspan="7">
                                                    <div class="ps-empty-state">
                                                        <span class="material-symbols-outlined">inventory_2</span>
                                                        <p>No loan records yet.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        <tr class="eq-loan-nomatch" hidden>
                                            <td colspan="7">
                                                <div class="ps-empty-state">
                                                    <span class="material-symbols-outlined">search_off</span>
                                                    <p>No items match your filter.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination (20 per page, client-side; works with the tabs and the search box) -->
                            <div class="inv-pager" id="eq-loan-pager" hidden>
                                <span class="inv-pager-info" id="eq-loan-pager-info"></span>
                                <div class="inv-pager-ctl" id="eq-loan-pager-ctl">
                                    <button type="button" class="inv-pager-btn" id="eq-loan-pg-prev" aria-label="Previous page">
                                        <span class="material-symbols-outlined">chevron_left</span>
                                    </button>
                                    <div class="inv-pager-nums" id="eq-loan-pg-nums"></div>
                                    <button type="button" class="inv-pager-btn" id="eq-loan-pg-next" aria-label="Next page">
                                        <span class="material-symbols-outlined">chevron_right</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div><!-- /eq-onloan -->

                </div><!-- /lending-inventory -->

            </div><!-- /panel-inventory -->

            <!-- ============================================================
    <!-- ============================================================
         TAB: SETTINGS  (streamlined — consolidates the old Settings tab,
         Account overlay, Arbitration tab, and Help Center overlay into
         one screen with four sub-tabs)
    ============================================================ -->
            <div class="tab-panel" id="panel-settings">

                <!-- Page Header -->
                <div class="sett-page-header">
                    <h1 class="sett-page-title">Settings</h1>
                    <p class="sett-page-sub">Manage your account, preferences, borrowing rules, and get help.</p>
                </div>

                <!-- Settings mega sub-tabs -->
                <div class="rq-sub-tabs" id="settMainTabs">
                    <button class="rq-sub-tab active" data-sett-panel="sett-account">My Account</button>
                    <button class="rq-sub-tab" data-sett-panel="sett-prefs">Preferences</button>
                    <?php if ($is_super_admin): ?>
                        <button class="rq-sub-tab" data-sett-panel="sett-rules">Borrowing Rules</button>
                        <button class="rq-sub-tab" data-sett-panel="sett-students">Student List</button>
                    <?php endif; ?>
                    <button class="rq-sub-tab" data-sett-panel="sett-help">Help &amp; FAQ</button>
                </div>

                <!-- ── MY ACCOUNT ─────────────────────────────────────── -->
                <div class="rq-sub-panel active" id="sett-account">

                    <!-- Profile Hero -->
                    <div class="ov-profile-hero" id="acctHero">
                        <div class="ov-av-lg">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
                        <div class="ov-hero-body">
                            <div class="ov-hero-name"><?php echo htmlspecialchars($admin_name); ?></div>
                            <div class="ov-hero-role">Administrator &middot; PUPSync Biñan Campus</div>
                            <div class="ov-hero-badge">
                                <span class="material-symbols-outlined">verified</span>
                                Active &middot; Full Access
                            </div>
                        </div>
                        <div class="ov-hero-actions">
                            <button class="btn-edit-acc" id="editProfileBtn" data-action="profile-edit">
                                <span class="material-symbols-outlined">edit</span>
                                Edit Profile
                            </button>
                            <button class="btn-save-acc" id="saveProfileBtn" style="display:none;"
                                data-action="profile-save">
                                <span class="material-symbols-outlined">save</span>
                                Save Changes
                            </button>
                            <button class="btn-cancel-acc" id="cancelProfileBtn" style="display:none;"
                                data-action="profile-cancel">
                                Cancel
                            </button>
                        </div>
                    </div>

                    <div class="ps-two-col">
                        <div>
                            <!-- Personal Information -->
                            <div class="info-card" id="profileInfoCard">
                                <div class="info-card-head">
                                    <h3>
                                        <span class="material-symbols-outlined">person</span>
                                        Personal Information
                                    </h3>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Display Name</span>
                                    <span class="info-val <?php echo empty($admin_name) ? 'empty' : ''; ?>"
                                        data-field="admin_name">
                                        <?php echo !empty($admin_name) ? htmlspecialchars($admin_name) : '— Not provided'; ?>
                                    </span>
                                    <input class="info-input-f" data-input="admin_name"
                                        value="<?php echo htmlspecialchars($admin_name ?? ''); ?>"
                                        placeholder="Display Name" disabled style="display:none;">
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Role</span>
                                    <span class="info-val">Administrator</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Email Address</span>
                                    <span class="info-val <?php echo empty($admin_email) ? 'empty' : ''; ?>"
                                        data-field="admin_email">
                                        <?php echo !empty($admin_email) ? htmlspecialchars($admin_email) : '— Not provided'; ?>
                                    </span>
                                    <input class="info-input-f" data-input="admin_email" type="email"
                                        value="<?php echo htmlspecialchars($admin_email ?? ''); ?>"
                                        placeholder="admin@pup.edu.ph" disabled style="display:none;">
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Campus</span>
                                    <span class="info-val">PUPSync Biñan Campus</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Access Level</span>
                                    <span class="info-val">
                                        <span class="ov-access-badge">Full Access</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Activity Summary -->
                            <div class="info-card">
                                <div class="info-card-head">
                                    <h3>
                                        <span class="material-symbols-outlined">bar_chart</span>
                                        My Activity
                                    </h3>
                                </div>
                                <?php $my_sum = admin_activity_my_summary($conn, (int)(admin_current_id($conn) ?? 0)); ?>
                                <div class="info-row">
                                    <span class="info-lbl">Decisions (30 days)</span>
                                    <span class="info-val"><?php echo (int)$my_sum['approved']; ?> approved &middot; <?php echo (int)$my_sum['declined']; ?> declined</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Returns (30 days)</span>
                                    <span class="info-val"><?php echo (int)$my_sum['returns']; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Last Login</span>
                                    <span class="info-val">
                                        <?php
                                        $ll = '— Not available';
                                        if (!empty($_SESSION['admin_last_login'])) {
                                            $ts = strtotime($_SESSION['admin_last_login']);
                                            if ($ts !== false) $ll = date('M d, Y · g:i A', $ts);
                                        }
                                        echo htmlspecialchars($ll);
                                        ?>
                                    </span>
                                </div>
                                <div class="info-row info-row--link">
                                    <button type="button" class="act-open" data-act-open="me">
                                        View my activity <span class="material-symbols-outlined">arrow_forward</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div>
                            <!-- Password & Security -->
                            <div class="info-card">
                                <div class="info-card-head">
                                    <h3>
                                        <span class="material-symbols-outlined">lock</span>
                                        Password &amp; Security
                                    </h3>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Current Password</span>
                                    <span class="info-val">&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;</span>
                                    <button class="btn-inline-sm" data-action="open-change-pass">Change</button>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Last Changed</span>
                                    <span class="info-val" id="pwLastChangedVal" style="color:var(--text-light)">
                                        <?php
                                        $pwc = '— Not tracked';
                                        if (!empty($admin_last_pw_change)) {
                                            $ts_pwc = strtotime($admin_last_pw_change);
                                            if ($ts_pwc !== false) $pwc = date('M d, Y · g:i A', $ts_pwc);
                                        }
                                        echo htmlspecialchars($pwc);
                                        ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Session Status</span>
                                    <span class="info-val">
                                        <span class="ov-status-dot">Active</span>
                                    </span>
                                    <button class="btn-inline-danger" data-action="logout">Log Out All</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /sett-account -->


                <!-- ── PREFERENCES ────────────────────────────────────── -->
                <div class="rq-sub-panel" id="sett-prefs">
                    <div class="sett-grid">
                        <div class="sett-card">
                            <div class="sett-card-head">
                                <span class="material-symbols-outlined">palette</span>
                                <h3>Appearance</h3>
                            </div>
                            <div class="sett-card-body">
                                <div class="sett-field-lbl">Theme</div>
                                <div class="sett-theme-row">
                                    <div class="sett-theme-opt sett-theme-sel" id="tp-light" data-action="apply-theme" data-theme="light">
                                        &#9728;&#65039; Light
                                    </div>
                                    <div class="sett-theme-opt" id="tp-dark" data-action="apply-theme" data-theme="dark">
                                        &#127769; Dark
                                    </div>
                                    <div class="sett-theme-opt" id="tp-hc" data-action="apply-theme" data-theme="high-contrast">
                                        &#9889; High Contrast
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="sett-card">
                            <div class="sett-card-head">
                                <span class="material-symbols-outlined">notifications</span>
                                <h3>Notification Preferences</h3>
                            </div>
                            <div class="sett-card-body sett-card-body--notifs">
                                <div class="sett-notif-row">
                                    <label class="toggle-sw">
                                        <input type="checkbox" id="notifPrefRequestsToggle" checked>
                                        <span class="toggle-track"></span>
                                    </label>
                                    <div>
                                        <div class="sett-notif-title">New Requests</div>
                                        <div class="sett-notif-sub">Alert when faculty submits a request</div>
                                    </div>
                                </div>
                                <div class="sett-notif-row">
                                    <label class="toggle-sw">
                                        <input type="checkbox" id="notifPrefOverdueToggle" checked>
                                        <span class="toggle-track"></span>
                                    </label>
                                    <div>
                                        <div class="sett-notif-title">Overdue Alerts</div>
                                        <div class="sett-notif-sub">Alert when an item becomes overdue</div>
                                    </div>
                                </div>
                                <div class="sett-notif-row">
                                    <label class="toggle-sw">
                                        <input type="checkbox" id="notifPrefRoomToggle">
                                        <span class="toggle-track"></span>
                                    </label>
                                    <div>
                                        <div class="sett-notif-title">Facility Issues</div>
                                        <div class="sett-notif-sub">Alert when a facility issue is reported</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="sett-card">
                            <div class="sett-card-head">
                                <span class="material-symbols-outlined">accessibility</span>
                                <h3>Accessibility</h3>
                            </div>
                            <div class="sett-card-body">
                                <div class="form-group">
                                    <label class="sett-field-lbl">Text Size</label>
                                    <select class="form-control-custom" id="textSizeSelect">
                                        <option value="Normal">Normal</option>
                                        <option value="Large">Large</option>
                                        <option value="X-Large">X-Large</option>
                                    </select>
                                </div>
                                <div class="sett-notif-row">
                                    <label class="toggle-sw">
                                        <input type="checkbox" id="reduceMotionToggle" data-action="apply-reduce-motion">
                                        <span class="toggle-track"></span>
                                    </label>
                                    <div>
                                        <div class="sett-notif-title">Reduce animations</div>
                                    </div>
                                </div>
                                <div class="sett-notif-row">
                                    <label class="toggle-sw">
                                        <input type="checkbox" id="focusRingToggle" data-action="apply-focus-ring">
                                        <span class="toggle-track"></span>
                                    </label>
                                    <div>
                                        <div class="sett-notif-title">Enhanced Focus Ring</div>
                                        <div class="sett-notif-sub">Makes keyboard focus outlines more visible</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /sett-grid -->

                    <div class="sett-danger-wrap">
                        <div class="sett-danger-wrap">
                            <div class="sett-card sett-danger-bdr">
                                <div class="sett-card-head">
                                    <span class="material-symbols-outlined" style="color:var(--danger)">warning</span>
                                    <h3 style="color:var(--danger)">Advanced</h3>
                                </div>
                                <div class="sett-card-body">
                                    <div class="sett-notif-row">
                                        <label class="toggle-sw"><input type="checkbox" id="showAssetIdsToggle" checked><span class="toggle-track"></span></label>
                                        <div>
                                            <div class="sett-notif-title">Show Asset IDs</div>
                                            <div class="sett-notif-sub">Display equipment item IDs in tables</div>
                                        </div>
                                    </div>
                                    <div class="sett-notif-row">
                                        <label class="toggle-sw"><input type="checkbox" id="verboseErrorsToggle"><span class="toggle-track"></span></label>
                                        <div>
                                            <div class="sett-notif-title">Verbose Error Messages</div>
                                            <div class="sett-notif-sub">Show detailed database error info (not recommended in production)</div>
                                        </div>
                                    </div>
                                    <div class="sett-adv-reset-row">
                                        <div>
                                            <div class="sett-notif-title" style="color:var(--danger)">Reset All Settings</div>
                                            <div class="sett-notif-sub">Restore all appearance and accessibility defaults</div>
                                        </div>
                                        <button class="sett-reset-btn" data-action="reset-settings">
                                            <span class="material-symbols-outlined">restart_alt</span> Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div><!-- /sett-prefs -->

                <!-- ── BORROWING RULES (was the standalone Arbitration tab; Super Admin only) ── -->
                <?php if ($is_super_admin): ?>
                    <div class="rq-sub-panel" id="sett-rules">
                        <!-- Every automatic decision is recorded in the Activity timeline (Sidebar → Activity). -->
                        <div class="arb-note">
                            <span class="material-symbols-outlined" aria-hidden="true">history</span>
                            <p>Want to see what these rules decided? Every automatic decision is in the Activity timeline.</p>
                            <button type="button" class="act-open" data-act-open="auto">
                                View automatic decisions <span class="material-symbols-outlined">arrow_forward</span>
                            </button>
                        </div>

                        <!-- ── CONFIGURATION ─────────────────────────────────────── -->
                        <div class="arb-sub-panel active" id="arb-sub-config">
                            <div class="ps-two-col">
                                <!-- Left: Rules -->
                                <div class="ps-card">
                                    <div class="ps-card-header">
                                        <h3><span class="material-symbols-outlined">rule</span> Auto-Approval Rules</h3>
                                    </div>
                                    <div class="ps-card-body">
                                        <div class="arb-rule">
                                            <h4>
                                                <span class="material-symbols-outlined">check_circle</span>
                                                Auto-approve Faculty Requests
                                                <label class="ps-toggle" style="margin-left:auto">
                                                    <input type="checkbox" checked>
                                                    <span class="ps-toggle-track"></span>
                                                </label>
                                            </h4>
                                            <p>Automatically approve equipment requests from verified faculty with cleared status.</p>
                                        </div>
                                        <div class="arb-rule">
                                            <h4>
                                                <span class="material-symbols-outlined">block</span>
                                                Block Overdue Borrowers
                                                <label class="ps-toggle" style="margin-left:auto">
                                                    <input type="checkbox"
                                                        <?php echo (($arb_config['rule_overdue_block_enabled'] ?? '1') == '1') ? 'checked' : ''; ?>>
                                                    <span class="ps-toggle-track"></span>
                                                </label>
                                            </h4>
                                            <p>Automatically decline new requests from users with overdue items.</p>
                                        </div>
                                        <div class="arb-rule">
                                            <h4>
                                                <span class="material-symbols-outlined">inventory</span>
                                                Stock-Based Rejection
                                                <label class="ps-toggle" style="margin-left:auto">
                                                    <input type="checkbox"
                                                        <?php echo (($arb_config['rule_duplicate_block_enabled'] ?? '0') == '1') ? 'checked' : ''; ?>>
                                                    <span class="ps-toggle-track"></span>
                                                </label>
                                            </h4>
                                            <p>Automatically decline if available stock falls below minimum threshold.</p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Right: Thresholds -->
                                <div class="ps-card">
                                    <div class="ps-card-header">
                                        <h3><span class="material-symbols-outlined">tune</span> Thresholds &amp; Limits</h3>
                                    </div>
                                    <div class="ps-card-body">
                                        <div class="ps-form-group">
                                            <label class="ps-form-label">Maximum Borrow Days</label>
                                            <input class="ps-form-control" type="number" min="1" max="60"
                                                value="<?php echo htmlspecialchars($arb_config['max_borrow_days'] ?? 7); ?>">
                                            <div class="ps-form-hint">Items must be returned within this many days.</div>
                                        </div>
                                        <div class="ps-form-group">
                                            <label class="ps-form-label">Max Items Per Borrower</label>
                                            <input class="ps-form-control" type="number" min="1" max="20"
                                                value="<?php echo htmlspecialchars($arb_config['max_items_per_borrower'] ?? 3); ?>">
                                            <div class="ps-form-hint">Maximum number of items a user can have at once.</div>
                                        </div>
                                        <div class="ps-form-group">
                                            <label class="ps-form-label">Low Stock Threshold</label>
                                            <input class="ps-form-control" type="number" min="0" max="10"
                                                value="<?php echo htmlspecialchars($arb_config['low_stock_threshold'] ?? 2); ?>">
                                            <div class="ps-form-hint">Trigger low-stock alert when quantity drops below this.</div>
                                        </div>
                                        <button type="button" class="ps-btn ps-btn--primary"
                                            style="width:100%;justify-content:center;margin-top:0.25rem">
                                            <span class="material-symbols-outlined">save</span> Save Configuration
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div><!-- /arb-sub-config -->

                    </div><!-- /sett-rules -->
                <?php endif; ?>

                <!-- ── STUDENT LIST (Super Admin only) ───────────────────────
                     Import of the official class lists. The Student Portal checks every
                     student's ID + name against it (config/student-registry.php).
                     Script: equipment-booking/assets/js/admin-student-registry.js -->
                <?php if ($is_super_admin): ?>
                    <?php
                    require_once __DIR__ . '/config/student-registry.php';
                    $stu_status = student_registry_status($conn);
                    $stu_on     = $stu_status['count'] > 0;
                    $stu_line   = 'No student list has been imported yet.';
                    if ($stu_on) {
                        $stu_line = $stu_status['count'] . ' student' . ($stu_status['count'] === 1 ? '' : 's')
                            . ' across ' . $stu_status['sections'] . ' section' . ($stu_status['sections'] === 1 ? '' : 's');
                        if ($stu_status['last_at'] !== '') {
                            $stu_line .= ' · last imported ' . date('M j, Y g:i A', (int)strtotime($stu_status['last_at']));
                            if ($stu_status['last_by'] !== '') {
                                $stu_line .= ' by ' . $stu_status['last_by'];
                            }
                        }
                    }
                    ?>
                    <div class="rq-sub-panel" id="sett-students">
                        <div class="ps-card stu-reg-card" id="stu-reg-card">
                            <?= csrf_field() ?>
                            <div class="ps-card-header">
                                <h3>
                                    <span class="material-symbols-outlined">badge</span> Student List
                                    <?php if ($stu_on): ?>
                                        <span class="fac-reg-badge fac-reg-badge--active" id="stu-reg-badge"><?= (int)$stu_status['count'] ?> student<?= $stu_status['count'] === 1 ? '' : 's' ?></span>
                                    <?php else: ?>
                                        <span class="fac-reg-badge fac-reg-badge--empty" id="stu-reg-badge">Not imported yet</span>
                                    <?php endif; ?>
                                </h3>
                            </div>
                            <div class="ps-card-body">
                                <p class="stu-reg-lead">
                                    Import the official class lists (the PDFs exported from AIMS). The Student Portal uses this list to
                                    confirm a student is real: the Student ID they enter must be on the list, otherwise they cannot continue.
                                </p>
                                <p class="stu-reg-enforce<?= $stu_on ? ' stu-reg-enforce--on' : '' ?>" id="stu-reg-enforce">
                                    <?= $stu_on
                                        ? 'Active — students must match this list to continue.'
                                        : 'Not active yet — students are not checked until a list is imported.' ?>
                                </p>
                                <p class="stu-reg-status" id="stu-reg-status"><?= htmlspecialchars($stu_line, ENT_QUOTES, 'UTF-8') ?></p>

                                <div class="fac-reg-upload-row">
                                    <label for="stu-reg-file-input" class="fac-reg-file-label" id="stu-reg-file-label">
                                        <span class="material-symbols-outlined">attach_file</span>
                                        <span id="stu-reg-file-name">Choose class list PDF(s)…</span>
                                    </label>
                                    <input type="file" id="stu-reg-file-input" class="stu-reg-file-input" accept=".pdf,application/pdf" multiple>
                                    <button type="button" id="stu-reg-import-btn" class="ps-btn ps-btn--primary" disabled>
                                        <span class="material-symbols-outlined">cloud_upload</span> Import
                                    </button>
                                </div>

                                <label class="fac-reg-replace-row stu-reg-replace" for="stu-reg-replace-all">
                                    <input type="checkbox" id="stu-reg-replace-all">
                                    <span>Replace the whole list (removes every student currently listed before importing)</span>
                                </label>

                                <div id="stu-reg-alert" class="alert-banner hidden" role="alert"></div>

                                <div id="stu-reg-preview" class="stu-reg-preview hidden">
                                    <div class="stu-reg-preview-head">
                                        <p class="stu-reg-preview-count" id="stu-reg-preview-count"></p>
                                        <button type="button" id="stu-reg-clear-btn" class="ps-btn ps-btn--ghost ps-btn--sm">
                                            <span class="material-symbols-outlined">close</span> Clear
                                        </button>
                                    </div>
                                    <div class="tbl-wrap stu-reg-tbl-wrap">
                                        <table class="admin-table stu-reg-table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Student No.</th>
                                                    <th>Name</th>
                                                    <th>Section</th>
                                                    <th>Year Level</th>
                                                </tr>
                                            </thead>
                                            <tbody id="stu-reg-preview-tbody"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- /sett-students -->
                <?php endif; ?>

                <!-- ── HELP & FAQ (was the standalone Help Center overlay) ─── -->
                <div class="rq-sub-panel" id="sett-help">

                    <div class="ps-two-col">
                        <div>
                            <div class="info-card">
                                <div class="info-card-head">
                                    <h3>
                                        <span class="material-symbols-outlined">quiz</span>
                                        Common Questions
                                    </h3>
                                </div>
                                <div class="info-card-body" style="padding:0.5rem 1.25rem 1.25rem;">
                                    <div class="hc-faq-list">

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">How do I approve or decline a borrow request?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                Go to <strong>Equipment → Loans</strong> and select <strong>Waiting</strong>. Review the request, then approve or decline it. The faculty member will see the updated status on their portal.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">How do I add new equipment to the inventory?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                Go to <strong>Inventory</strong> and click <strong>+ Add Equipment</strong>. Fill in the item name, description, quantity, condition, and category. Items become available for borrowing immediately after saving. You can also set an item to "Not Available" if it's under repair.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">What happens when I archive a facility?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                Archiving a facility hides it from faculty when they submit new reservations. Existing reservation history is preserved. You can restore an archived facility at any time from <strong>Facilities → Archived</strong> tab by clicking <strong>Restore</strong>. Only a Super Admin can archive a facility, and only a Super Admin can permanently delete an archived facility (this removes it and its reservation history for good). To keep a facility visible but stop its schedule from opening, open the facility's <strong>Edit</strong> window and choose <strong>Lock Facility</strong> — Admins and Super Admins can lock and unlock.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">How do faculty members submit facility reservations?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                Faculty log in to their portal and go to the <strong>Facilities</strong> tab. They select the campus, building, facility, date, time slot, and purpose. The request appears in your <strong>Facilities → Reservations</strong> panel. The facility slot is not blocked until you confirm it.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">How do I manage or create faculty accounts?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                Go to <strong>Faculty</strong> in the sidebar. You can view all active faculty accounts, review their request history, and reset passwords. Click <strong>+ Add Faculty</strong> to create a new account. New accounts receive a default password that must be changed on first login.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">What is the Arbitration panel used for?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                Arbitration handles conflicts when two or more faculty members compete for the same resource — a facility or equipment — at the same time. You review both claims, see who has stronger grounds, and make a final ruling. The ruling overrides the normal approval flow and is logged.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">How do I handle a reported facility issue?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                In <strong>Facilities → Issues</strong>, you'll see all open reports submitted by faculty. Click <strong>Review</strong> on any open issue. You can mark it as <em>Resolved</em> (problem fixed, facility stays active) or <em>Dismissed</em> (not a valid concern). Note: facilities are <strong>not</strong> automatically set to Maintenance — you must edit the facility status separately if needed.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">Can I restore an archived facility or equipment item?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                Yes. For facilities, go to <strong>Facilities → Archived</strong> and click <strong>Restore</strong> next to the facility. For equipment, go to <strong>Inventory → Archived</strong> and click <strong>Restore</strong>. The item or facility returns to the active list immediately and is available again.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">How do I cancel an approved facility reservation?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                Go to <strong>Facilities → Reservations</strong>. Find the reservation with an <em>Approved</em> status and click <strong>Cancel</strong>. You can add a reason for the cancellation. Please note: you cannot cancel a reservation within 1 hour of its scheduled start time — this restriction is enforced to protect faculty planning.
                                            </div>
                                        </details>

                                        <details class="hc-faq-item">
                                            <summary class="hc-faq-q">
                                                <span class="hc-faq-q-text">What do the different request statuses mean?</span>
                                                <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                            </summary>
                                            <div class="hc-faq-a">
                                                <div class="hc-status-table">
                                                    <div class="hc-st-row"><span class="hc-st-pill pending">Pending</span><span>Awaiting admin review and action.</span></div>
                                                    <div class="hc-st-row"><span class="hc-st-pill approved">Approved</span><span>Admin confirmed — item loaned or facility reserved.</span></div>
                                                    <div class="hc-st-row"><span class="hc-st-pill declined">Declined</span><span>Admin rejected the request.</span></div>
                                                    <div class="hc-st-row"><span class="hc-st-pill cancelled">Cancelled</span><span>Cancelled by the admin or the faculty member.</span></div>
                                                    <div class="hc-st-row"><span class="hc-st-pill returned">Returned</span><span>Equipment was returned and processed (lending only).</span></div>
                                                    <div class="hc-st-row"><span class="hc-st-pill overdue">Overdue</span><span>Item not returned by the agreed return date.</span></div>
                                                </div>
                                            </div>
                                        </details>

                                    </div><!-- /.hc-faq-list -->
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="info-card">
                                <div class="info-card-head">
                                    <h3>
                                        <span class="material-symbols-outlined">contact_support</span>
                                        Contact Support
                                    </h3>
                                </div>
                                <div class="ps-empty-state" style="padding:2rem 1.25rem;">
                                    <span class="material-symbols-outlined">mail</span>
                                    <p>For system issues, contact the PUPSync development team at<br>
                                        <strong>pupsync.support@pup.edu.ph</strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /sett-help -->

            </div><!-- /panel-settings -->

        </main><!-- /app-main -->

    </div><!-- /app-body -->

    <!-- ================================================================
         REQUESTS PANEL MODALS — outside app-body for correct fixed positioning
    ================================================================ -->

    <!-- ================================================================
             MODALS — REQUESTS PANEL  (UI only — functions wired in Phase 4)
        ================================================================ -->

    <!-- MODAL: APPROVE REQUEST -->
    <div class="ps-modal-backdrop" id="ps-approve-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--success">
                    <span class="material-symbols-outlined">check_circle</span>
                </div>
                <h3>Approve Request</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-approve-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <input type="hidden" id="approve-request-id" value="">
                <div id="ps-approve-alert" class="ps-inline-alert" style="display:none;margin-bottom:.85rem;padding:.6rem .8rem;border-radius:8px;font-size:12.5px"></div>
                <div class="ps-req-summary">
                    <div class="ps-req-sum-row"><span class="ps-rsl">Request</span><span class="ps-rsv" id="ps-approve-reqno" style="font-weight:600;color:var(--accent-maroon)">#R-0000</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Borrower</span><span class="ps-rsv" id="approve-borrower">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Equipment</span><span class="ps-rsv" id="approve-equipment">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Date Needed</span><span class="ps-rsv" id="approve-date-needed">—</span></div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Return Due Date</label>
                    <input class="ps-form-control" type="date" id="approve-due-date" readonly disabled>
                    <div class="ps-form-hint">Set by the borrower when they submitted the request.</div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Reason / Note <span style="font-size:11px;color:var(--text-light)">(required, min. 5 characters — kept in the arbitration log)</span></label>
                    <textarea class="ps-form-control" id="approve-reason" rows="2" placeholder="e.g. Item available, request meets policy..."></textarea>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-approve-modal">Cancel</button>
                <button class="ps-btn ps-btn--success" id="ps-approve-confirm-btn">
                    <span class="material-symbols-outlined">check</span> Approve Request
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: DECLINE REQUEST -->
    <div class="ps-modal-backdrop" id="ps-decline-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--danger">
                    <span class="material-symbols-outlined">cancel</span>
                </div>
                <h3>Decline Request</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-decline-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <input type="hidden" id="decline-request-id" value="">
                <div id="ps-decline-alert" class="ps-inline-alert" style="display:none;margin-bottom:.85rem;padding:.6rem .8rem;border-radius:8px;font-size:12.5px"></div>
                <div class="ps-req-summary">
                    <div class="ps-req-sum-row"><span class="ps-rsl">Request</span><span class="ps-rsv" id="ps-decline-reqno" style="font-weight:600;color:var(--accent-maroon)">#R-0000</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Borrower</span><span class="ps-rsv" id="decline-borrower">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Equipment</span><span class="ps-rsv" id="decline-equipment">—</span></div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Reason for Declining <span style="font-size:11px;color:var(--text-light)">(required, min. 5 characters)</span></label>
                    <textarea class="ps-form-control" id="decline-reason" rows="3" placeholder="e.g. Item is currently in use by another borrower..."></textarea>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-decline-modal">Cancel</button>
                <button class="ps-btn ps-btn--danger" id="ps-decline-confirm-btn">
                    <span class="material-symbols-outlined">close</span> Decline Request
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: CANCEL SCHEDULED BOOKING (Equipment › On Loan › Waiting) -->
    <div class="ps-modal-backdrop" id="ps-cancel-booking-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--danger">
                    <span class="material-symbols-outlined">event_busy</span>
                </div>
                <h3>Cancel Booking</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-cancel-booking-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <input type="hidden" id="cancel-booking-id" value="">
                <div id="ps-cancel-booking-alert" class="ps-inline-alert" style="display:none;margin-bottom:.85rem;padding:.6rem .8rem;border-radius:8px;font-size:12.5px"></div>
                <div class="ps-req-summary">
                    <div class="ps-req-sum-row"><span class="ps-rsl">Request</span><span class="ps-rsv" id="ps-cancel-booking-reqno" style="font-weight:600;color:var(--accent-maroon)">#R-0000</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Borrower</span><span class="ps-rsv" id="cancel-booking-borrower">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Equipment</span><span class="ps-rsv" id="cancel-booking-equipment">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Starts</span><span class="ps-rsv" id="cancel-booking-start">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Return by</span><span class="ps-rsv" id="cancel-booking-due">—</span></div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Reason for Cancelling <span style="font-size:11px;color:var(--text-light)">(required, min. 5 characters)</span></label>
                    <textarea class="ps-form-control" id="cancel-booking-reason" rows="3" placeholder="e.g. Equipment is reserved for a university event on that date..."></textarea>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-cancel-booking-modal">Keep Booking</button>
                <button class="ps-btn ps-btn--danger" id="ps-cancel-booking-confirm-btn" disabled>
                    <span class="material-symbols-outlined">event_busy</span> Cancel Booking
                </button>
            </div>
        </div>
    </div>

    <?php if ($is_super_admin): ?>
        <!-- ═══ MANAGE ADMINS — edit / lock / delete (Super Admin only) ═══
         Direct children of <body>, same ps-modal system as the Requests modals.
         Driven by the "Admin Accounts panel" block in admin-dashboard.js. -->

        <!-- MODAL: ADD ADMIN -->
        <div class="ps-modal-backdrop" id="amAddModal">
            <div class="ps-modal">
                <div class="ps-modal-head">
                    <div class="ps-modal-head-icon ps-mhi--maroon">
                        <span class="material-symbols-outlined">person_add</span>
                    </div>
                    <h3>Add Admin</h3>
                    <button type="button" class="ps-modal-close" data-adm-close="amAddModal" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="ps-modal-body">
                    <div id="adm-form-alert" class="alert-banner hidden" role="alert"></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="adm-fullname">Full Name <span class="req-star">*</span></label>
                            <input type="text" id="adm-fullname" class="form-control-custom"
                                placeholder="e.g. Juan dela Cruz" maxlength="255" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="adm-email">Admin Email <span class="req-star">*</span></label>
                            <input type="email" id="adm-email" class="form-control-custom"
                                placeholder="name@admin.edu" maxlength="255" autocomplete="off">
                            <small class="adm-email-hint">Must end in <code>@admin.edu</code></small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="adm-role">Role <span class="req-star">*</span></label>
                        <select id="adm-role" class="form-control-custom">
                            <option value="Admin" selected>Admin</option>
                            <option value="Super Admin">Super Admin</option>
                        </select>
                        <small class="adm-email-hint">Super Admins have full control. Admins have limited access.</small>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="adm-password">Password <span class="req-star">*</span></label>
                            <div class="fac-pw-wrap">
                                <input type="password" id="adm-password" class="form-control-custom"
                                    placeholder="Min. 8 characters" autocomplete="new-password">
                                <button type="button" class="fac-pw-toggle" data-target="adm-password" title="Show/hide password">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="adm-confirm">Confirm Password <span class="req-star">*</span></label>
                            <div class="fac-pw-wrap">
                                <input type="password" id="adm-confirm" class="form-control-custom"
                                    placeholder="Re-enter password" autocomplete="new-password">
                                <button type="button" class="fac-pw-toggle" data-target="adm-confirm" title="Show/hide password">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ps-modal-foot">
                    <button type="button" class="ps-btn ps-btn--ghost" data-adm-close="amAddModal">Cancel</button>
                    <button type="button" class="ps-btn ps-btn--primary" id="adm-submit-btn">
                        <span class="material-symbols-outlined">person_add</span> Create Admin
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: EDIT ADMIN LOGIN DETAILS -->
        <div class="ps-modal-backdrop" id="admEditModal">
            <div class="ps-modal">
                <div class="ps-modal-head">
                    <div class="ps-modal-head-icon ps-mhi--maroon">
                        <span class="material-symbols-outlined">manage_accounts</span>
                    </div>
                    <h3>Edit Admin Login Details</h3>
                    <button type="button" class="ps-modal-close" data-adm-close="admEditModal" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="ps-modal-body">
                    <div id="adm-edit-alert" class="alert-banner hidden" role="alert"></div>
                    <div class="form-group">
                        <label for="adm-edit-name">Full Name <span class="req-star">*</span></label>
                        <input type="text" id="adm-edit-name" class="form-control-custom" maxlength="255" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="adm-edit-email">Admin Email <span class="req-star">*</span></label>
                        <input type="email" id="adm-edit-email" class="form-control-custom" maxlength="255" autocomplete="off">
                        <small class="adm-email-hint">Must end in <code>@admin.edu</code></small>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="adm-edit-pw">New Password</label>
                            <div class="fac-pw-wrap">
                                <input type="password" id="adm-edit-pw" class="form-control-custom"
                                    placeholder="Leave blank to keep" autocomplete="new-password">
                                <button type="button" class="fac-pw-toggle" data-target="adm-edit-pw,adm-edit-pw2" title="Show/hide password">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="adm-edit-pw2">Confirm New Password</label>
                            <div class="fac-pw-wrap">
                                <input type="password" id="adm-edit-pw2" class="form-control-custom"
                                    placeholder="Re-enter new password" autocomplete="new-password">
                                <button type="button" class="fac-pw-toggle" data-target="adm-edit-pw,adm-edit-pw2" title="Show/hide password">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <p class="adm-modal-note">
                        <span class="material-symbols-outlined">info</span>
                        Changing the email or password signs this admin out of every device.
                    </p>
                    <div class="form-group adm-reauth">
                        <label for="adm-edit-reauth">Your password <span class="req-star">*</span></label>
                        <div class="fac-pw-wrap">
                            <input type="password" id="adm-edit-reauth" class="form-control-custom"
                                placeholder="Confirm it's you" autocomplete="current-password">
                            <button type="button" class="fac-pw-toggle" data-target="adm-edit-reauth" title="Show/hide password">
                                <span class="material-symbols-outlined">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="ps-modal-foot">
                    <button type="button" class="ps-btn ps-btn--ghost" data-adm-close="admEditModal">Cancel</button>
                    <button type="button" class="ps-btn ps-btn--primary" id="adm-edit-save">
                        <span class="material-symbols-outlined">save</span> Save Changes
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: LOCK ADMIN ACCOUNT -->
        <div class="ps-modal-backdrop" id="admDormantModal">
            <div class="ps-modal ps-modal--sm">
                <div class="ps-modal-head">
                    <div class="ps-modal-head-icon ps-mhi--warning">
                        <span class="material-symbols-outlined">lock</span>
                    </div>
                    <h3>Lock Account</h3>
                    <button type="button" class="ps-modal-close" data-adm-close="admDormantModal" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="ps-modal-body">
                    <div id="adm-dormant-alert" class="alert-banner hidden" role="alert"></div>
                    <p class="adm-modal-text">
                        <strong id="adm-dormant-name">This admin</strong> keeps their account, but will be signed out and
                        cannot log in until the period ends. You can unlock it any time.
                    </p>
                    <div class="adm-days" role="radiogroup" aria-label="Locked for">
                        <?php foreach (ADMIN_DORMANT_CHOICES as $d): ?>
                            <label class="adm-day-chip">
                                <input type="radio" name="adm-dormant-days" value="<?php echo (int)$d; ?>" <?php echo $d === 7 ? 'checked' : ''; ?>>
                                <span><?php echo (int)$d; ?> day<?php echo $d === 1 ? '' : 's'; ?></span>
                            </label>
                        <?php endforeach; ?>
                        <label class="adm-day-chip">
                            <input type="radio" name="adm-dormant-days" value="custom">
                            <span>Custom</span>
                        </label>
                    </div>
                    <div class="form-group adm-custom-days" id="adm-custom-days" hidden>
                        <label for="adm-dormant-custom">Number of days (1–<?php echo (int)ADMIN_DORMANT_MAX_DAYS; ?>)</label>
                        <input type="number" id="adm-dormant-custom" class="form-control-custom"
                            min="1" max="<?php echo (int)ADMIN_DORMANT_MAX_DAYS; ?>" step="1" inputmode="numeric" placeholder="e.g. 10">
                    </div>
                    <p class="adm-modal-note" id="adm-dormant-until-note">
                        <span class="material-symbols-outlined">event</span>
                        <span id="adm-dormant-until-text"></span>
                    </p>
                </div>
                <div class="ps-modal-foot">
                    <button type="button" class="ps-btn ps-btn--ghost" data-adm-close="admDormantModal">Cancel</button>
                    <button type="button" class="ps-btn ps-btn--primary" id="adm-dormant-save">
                        <span class="material-symbols-outlined">lock</span> Lock Account
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: DELETE ADMIN -->
        <div class="ps-modal-backdrop" id="admDeleteModal">
            <div class="ps-modal ps-modal--sm">
                <div class="ps-modal-head">
                    <div class="ps-modal-head-icon ps-mhi--danger">
                        <span class="material-symbols-outlined">delete</span>
                    </div>
                    <h3>Delete Admin Account</h3>
                    <button type="button" class="ps-modal-close" data-adm-close="admDeleteModal" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="ps-modal-body">
                    <div id="adm-delete-alert" class="alert-banner hidden" role="alert"></div>
                    <p class="adm-modal-text">
                        Delete <strong id="adm-delete-name">this admin</strong>? The account is removed permanently and its
                        seat becomes free for a new admin. This can't be undone.
                    </p>
                    <div class="form-group adm-reauth">
                        <label for="adm-delete-reauth">Your password <span class="req-star">*</span></label>
                        <div class="fac-pw-wrap">
                            <input type="password" id="adm-delete-reauth" class="form-control-custom"
                                placeholder="Confirm it's you" autocomplete="current-password">
                            <button type="button" class="fac-pw-toggle" data-target="adm-delete-reauth" title="Show/hide password">
                                <span class="material-symbols-outlined">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="ps-modal-foot">
                    <button type="button" class="ps-btn ps-btn--ghost" data-adm-close="admDeleteModal">Cancel</button>
                    <button type="button" class="ps-btn ps-btn--danger" id="adm-delete-confirm">
                        <span class="material-symbols-outlined">delete</span> Delete Account
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- MODAL: MANUAL RETURN (does not require the borrower's QR code) -->
    <div class="ps-modal-backdrop" id="ps-return-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">assignment_return</span>
                </div>
                <h3>Manual Return</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-return-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <form id="manual-return-form" method="post" action="equipment-booking/api/manual-return.php">
                    <?= csrf_field() ?>
                    <input type="hidden" id="manual-return-request-id" name="request_id">
                    <div class="ps-req-summary">
                        <div class="ps-req-sum-row"><span class="ps-rsl">Borrower</span><span class="ps-rsv" style="font-weight:600">—</span></div>
                        <div class="ps-req-sum-row"><span class="ps-rsl">Student ID</span><span class="ps-rsv">—</span></div>
                        <div class="ps-req-sum-row"><span class="ps-rsl">Equipment</span><span class="ps-rsv">—</span></div>
                        <div class="ps-req-sum-row"><span class="ps-rsl">Due Date</span><span class="ps-rsv" style="color:var(--danger);font-weight:600">—</span></div>
                    </div>
                    <div class="ps-form-group">
                        <label class="ps-form-label">Return Condition</label>
                        <input type="hidden" id="manual-return-condition-value" name="return_condition" value="Good">
                        <select class="ps-form-control" id="manual-return-condition">
                            <option>Good — No visible damage</option>
                            <option>Fair — Minor wear</option>
                            <option>Damaged — Needs repair</option>
                            <option>Missing — Item not returned</option>
                        </select>
                    </div>
                    <div class="ps-form-group">
                        <label class="ps-form-label">Admin Notes <span style="font-size:11px;color:var(--text-light)">(optional)</span></label>
                        <textarea class="ps-form-control" id="manual-return-notes" name="admin_notes" rows="2" maxlength="2000" placeholder="Any notes about the return..."></textarea>
                    </div>
                    <div class="ps-alert ps-alert--info" style="margin:0">
                        <span class="material-symbols-outlined">info</span>
                        Recording this manual return updates the stock count and closes this request. No faculty QR code is required.
                    </div>
                </form>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-return-modal">Cancel</button>
                <button type="submit" form="manual-return-form" class="ps-btn ps-btn--primary" id="manual-return-confirm-btn">
                    <span class="material-symbols-outlined">check</span> Record Manual Return
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: SEND OVERDUE NOTICE -->
    <div class="ps-modal-backdrop" id="ps-notice-modal">
        <div class="ps-modal">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--warning">
                    <span class="material-symbols-outlined">mail</span>
                </div>
                <h3>Send Overdue Notice</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-notice-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <form id="overdue-notice-form" method="post" action="equipment-booking/api/send-overdue-notice.php">
                    <?= csrf_field() ?>
                    <input type="hidden" id="overdue-notice-request-id" name="request_id">
                </form>
                <div class="ps-req-summary" style="margin-bottom:1rem">
                    <div class="ps-req-sum-row"><span class="ps-rsl">Borrower</span><span class="ps-rsv" style="font-weight:600">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Student ID</span><span class="ps-rsv">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Equipment</span><span class="ps-rsv">—</span></div>
                    <div class="ps-req-sum-row"><span class="ps-rsl">Days Overdue</span><span class="ps-rsv" style="color:var(--danger);font-weight:600">—</span></div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Message Preview</label>
                    <div class="ps-notice-preview" id="overdue-notice-preview">Dear [Borrower Name],

                        This is a reminder that the following item borrowed from the PUPSync Equipment Lending System is now overdue:

                        • Item: [Equipment Name]
                        • Due Date: [Due Date]
                        • Days Overdue: [N] days

                        Please return the item as soon as possible to avoid further penalties.

                        Thank you,
                        PUPSync Admin — Biñan Campus</div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Delivery</label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;margin-top:6px">
                        <input type="checkbox" checked disabled> Send to the borrower’s PUPSync notification inbox
                    </label>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-notice-modal">Cancel</button>
                <button type="submit" form="overdue-notice-form" class="ps-btn ps-btn--primary" id="overdue-notice-send-btn">
                    <span class="material-symbols-outlined">send</span> Send Notice
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: REQUEST DETAIL -->
    <div class="ps-modal-backdrop" id="ps-req-detail-modal">
        <div class="ps-modal ps-modal--lg">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">assignment</span>
                </div>
                <h3>Request Details</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-req-detail-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <input type="hidden" id="detail-request-id" value="">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:1.25rem">
                    <span class="ps-badge ps-badge--waiting ps-badge--dot" id="detail-status-badge" style="font-size:12px;padding:4px 12px">Waiting for Approval</span>
                    <span style="font-size:11.5px;color:var(--text-light)">Submitted: <span id="detail-submitted">—</span></span>
                </div>
                <div class="ps-detail-grid" style="margin-bottom:1.25rem">
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Requester</div>
                        <div class="ps-detail-value" id="detail-requester" style="font-weight:600">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Student / Faculty ID</div>
                        <div class="ps-detail-value" id="detail-id-number">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Requester Type</div>
                        <div class="ps-detail-value" id="detail-req-type">Faculty</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Facility</div>
                        <div class="ps-detail-value" id="detail-room" style="font-size:12px">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Equipment</div>
                        <div class="ps-detail-value" id="detail-equipment" style="font-weight:600">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Quantity</div>
                        <div class="ps-detail-value">1</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Date Needed</div>
                        <div class="ps-detail-value" id="detail-date-needed" style="font-weight:600">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Return By</div>
                        <div class="ps-detail-value" id="detail-return-by">—</div>
                    </div>
                    <div class="ps-detail-item">
                        <div class="ps-detail-label">Condition</div>
                        <div class="ps-detail-value"><span class="ps-badge ps-badge--dot ps-badge--active" id="detail-condition-badge">Good</span></div>
                    </div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Instructor / Assigned To</label>
                    <div id="detail-instructor" style="background:var(--secondary-cream);border-radius:8px;padding:0.75rem;font-size:12.5px;color:var(--text-dark);border:1px solid var(--khaki-border)">—</div>
                </div>
                <div style="background:var(--secondary-cream);border-radius:10px;padding:0.85rem;font-size:12px;color:var(--text-light);border:1px solid var(--khaki-border)">
                    <strong style="color:var(--text-dark)">Arbitration Status:</strong> <span id="detail-arb-status">Pending review.</span>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-req-detail-modal">Close</button>
                <button class="ps-btn ps-btn--danger" id="ps-detail-decline-btn" data-action="ps-switch-modal" data-close="ps-req-detail-modal" data-open="ps-decline-modal">
                    <span class="material-symbols-outlined">close</span> Decline
                </button>
                <button class="ps-btn ps-btn--success" id="ps-detail-approve-btn" data-action="ps-switch-modal" data-close="ps-req-detail-modal" data-open="ps-approve-modal">
                    <span class="material-symbols-outlined">check</span> Approve
                </button>
            </div>
        </div>
    </div>

    <!-- DRAWER: REQUEST REVIEW (Equipment > On Loan). Reuses the ps-modal-backdrop open/close
         mechanism, so psOpenModal/psCloseModal, backdrop click and Esc all work unchanged.
         Approve / Decline here hand off to the existing approve/decline modals. -->
    <div class="ps-modal-backdrop ps-drawer-backdrop" id="ps-appr-drawer">
        <aside class="ps-drawer" role="dialog" aria-modal="true" aria-labelledby="ps-appr-title">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">fact_check</span>
                </div>
                <h3 id="ps-appr-title">Review <span id="ps-appr-reqno">#0000</span></h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-appr-drawer" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-drawer-body">
                <div class="ps-drawer-status">
                    <span class="ps-badge ps-badge--dot ps-badge--waiting">Pending Review</span>
                    <span class="ps-drawer-muted">Submitted <span id="ps-appr-submitted">—</span></span>
                </div>

                <section class="ps-drawer-sec">
                    <h4>Supporting document</h4>
                    <div class="ps-doc-card" id="ps-appr-doc">
                        <div class="ps-doc-card__icon"><span class="material-symbols-outlined" id="ps-appr-doc-icon">draft</span></div>
                        <div class="ps-doc-card__meta">
                            <div class="ps-doc-card__name" id="ps-appr-doc-name">—</div>
                            <div class="ps-drawer-muted" id="ps-appr-doc-kind">—</div>
                        </div>
                        <div class="ps-doc-card__btns">
                            <a class="ps-btn ps-btn--primary ps-btn--sm" id="ps-appr-doc-open" href="#" target="_blank" rel="noopener noreferrer">
                                <span class="material-symbols-outlined">open_in_new</span> Open
                            </a>
                            <a class="ps-btn ps-btn--outline ps-btn--sm" id="ps-appr-doc-dl" href="#" download>
                                <span class="material-symbols-outlined">download</span>
                            </a>
                        </div>
                    </div>
                    <div class="ps-doc-preview" id="ps-appr-doc-preview" hidden>
                        <img id="ps-appr-doc-img" alt="Attached document preview">
                    </div>
                    <div class="ps-doc-none" id="ps-appr-doc-none" hidden>
                        <span class="material-symbols-outlined">warning</span>
                        <span>No supporting document is attached. Standard requests are handled automatically, so check why this one needs a manual decision.</span>
                    </div>
                </section>

                <section class="ps-drawer-sec">
                    <h4>Requester</h4>
                    <div class="ps-kv">
                        <div><span>Name</span><strong id="ps-appr-who">—</strong></div>
                        <div><span>Type</span><strong id="ps-appr-type">—</strong></div>
                        <div><span>ID</span><strong id="ps-appr-idno">—</strong></div>
                    </div>
                </section>

                <section class="ps-drawer-sec">
                    <h4>Request</h4>
                    <div class="ps-kv">
                        <div><span>Item</span><strong id="ps-appr-item">—</strong></div>
                        <div><span>Quantity</span><strong id="ps-appr-qty">—</strong></div>
                        <div><span>Date needed</span><strong id="ps-appr-needed">—</strong></div>
                        <div><span>Return by</span><strong id="ps-appr-return">—</strong></div>
                        <div><span>Facility</span><strong id="ps-appr-room">—</strong></div>
                        <div><span>Instructor</span><strong id="ps-appr-instr">—</strong></div>
                    </div>
                </section>

                <section class="ps-drawer-sec" id="ps-appr-note-sec">
                    <h4>Notes</h4>
                    <div class="ps-drawer-note" id="ps-appr-note">—</div>
                </section>
            </div>
            <div class="ps-drawer-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-appr-drawer">Close</button>
                <button class="ps-btn ps-btn--danger" type="button" data-appr-decide="decline">
                    <span class="material-symbols-outlined">close</span> Decline
                </button>
                <button class="ps-btn ps-btn--success" type="button" data-appr-decide="approve">
                    <span class="material-symbols-outlined">check</span> Approve
                </button>
            </div>
        </aside>
    </div>

    <!-- DRAWER: ROOM RESERVATION DETAILS (Rooms > Reservations). Same shell/CSS as the
         approvals drawer; Cancel hands off to the existing admin cancel-reservation modal. -->
    <div class="ps-modal-backdrop ps-drawer-backdrop" id="ps-room-drawer">
        <aside class="ps-drawer" role="dialog" aria-modal="true" aria-labelledby="ps-rr-title">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">meeting_room</span>
                </div>
                <h3 id="ps-rr-title">Reservation <span id="ps-rr-no">#0</span></h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-room-drawer" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-drawer-body">
                <div class="ps-drawer-status">
                    <span class="ps-badge ps-badge--dot ps-badge--active" id="ps-rr-status">Approved</span>
                    <span class="ps-drawer-muted">Submitted <span id="ps-rr-submitted">—</span></span>
                </div>

                <section class="ps-drawer-sec" id="ps-rr-doc-sec">
                    <h4>Supporting document</h4>
                    <div class="ps-doc-card" id="ps-rr-doc">
                        <div class="ps-doc-card__icon"><span class="material-symbols-outlined" id="ps-rr-doc-icon">draft</span></div>
                        <div class="ps-doc-card__meta">
                            <div class="ps-doc-card__name" id="ps-rr-doc-name">—</div>
                            <div class="ps-drawer-muted" id="ps-rr-doc-kind">—</div>
                        </div>
                        <div class="ps-doc-card__btns">
                            <a class="ps-btn ps-btn--primary ps-btn--sm" id="ps-rr-doc-open" href="#" target="_blank" rel="noopener noreferrer">
                                <span class="material-symbols-outlined">open_in_new</span> Open
                            </a>
                            <a class="ps-btn ps-btn--outline ps-btn--sm" id="ps-rr-doc-dl" href="#" download>
                                <span class="material-symbols-outlined">download</span>
                            </a>
                        </div>
                    </div>
                    <div class="ps-doc-preview" id="ps-rr-doc-preview" hidden>
                        <img id="ps-rr-doc-img" alt="Attached document preview">
                    </div>
                </section>

                <section class="ps-drawer-sec">
                    <h4>Reservation</h4>
                    <div class="ps-kv">
                        <div><span>Facility</span><strong id="ps-rr-room">—</strong></div>
                        <div><span>Location</span><strong id="ps-rr-where">—</strong></div>
                        <div><span>Date</span><strong id="ps-rr-date">—</strong></div>
                        <div><span>Time</span><strong id="ps-rr-time">—</strong></div>
                        <div><span>Attendees</span><strong id="ps-rr-attendees">—</strong></div>
                        <div><span>Purpose</span><strong id="ps-rr-purpose">—</strong></div>
                    </div>
                </section>

                <section class="ps-drawer-sec">
                    <h4>Requested by</h4>
                    <div class="ps-kv">
                        <div><span>Name</span><strong id="ps-rr-who">—</strong></div>
                        <div><span>Type</span><strong id="ps-rr-type">—</strong></div>
                    </div>
                </section>

                <section class="ps-drawer-sec" id="ps-rr-decision-sec">
                    <h4>Decision</h4>
                    <div class="ps-kv">
                        <div><span>Method</span><strong id="ps-rr-dec-method">—</strong></div>
                        <div id="ps-rr-dec-rule-row"><span>Rule</span><strong id="ps-rr-dec-rule">—</strong></div>
                        <div id="ps-rr-dec-reason-row"><span>Reason</span><strong id="ps-rr-dec-reason">—</strong></div>
                    </div>
                </section>

                <section class="ps-drawer-sec">
                    <h4>Notes</h4>
                    <div class="ps-drawer-note" id="ps-rr-notes">—</div>
                </section>

                <?php if (!empty($ps_rooms_can_view_sensitive_adviser_requests)): ?>
                    <!-- Super Admin only: shown for Waiting requests. Open the attached letter above, then decide. -->
                    <section class="ps-drawer-sec" id="ps-rr-review-sec" hidden>
                        <h4>Your decision</h4>
                        <label class="ps-drawer-muted" for="ps-rr-review-reason">Reason (at least 5 characters). It is saved with the decision.</label>
                        <textarea class="ps-rr-reason" id="ps-rr-review-reason" rows="3" maxlength="500"></textarea>
                        <div class="ps-rr-review-err" id="ps-rr-review-err" role="alert" hidden></div>
                    </section>
                <?php endif; ?>
            </div>
            <div class="ps-drawer-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-room-drawer">Close</button>
                <button class="ps-btn ps-btn--danger" type="button" id="ps-rr-cancel-btn" data-rr-cancel hidden>
                    <span class="material-symbols-outlined">cancel</span> Cancel reservation
                </button>
                <?php if (!empty($ps_rooms_can_view_sensitive_adviser_requests)): ?>
                    <button class="ps-btn ps-btn--danger" type="button" data-rr-decide="Declined" hidden>
                        <span class="material-symbols-outlined">close</span> Decline
                    </button>
                    <button class="ps-btn ps-btn--success" type="button" data-rr-decide="Approved" hidden>
                        <span class="material-symbols-outlined">check</span> Approve
                    </button>
                <?php endif; ?>
            </div>
        </aside>
    </div>

    <!-- MODAL: EXPORT REQUESTS -->
    <div class="ps-modal-backdrop" id="ps-export-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--info">
                    <span class="material-symbols-outlined">download</span>
                </div>
                <h3>Export Requests</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="ps-export-modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <div class="ps-form-group">
                    <label class="ps-form-label">Date Range</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <div>
                            <div style="font-size:11px;color:var(--text-light);margin-bottom:3px">From</div>
                            <input class="ps-form-control" type="date" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div>
                            <div style="font-size:11px;color:var(--text-light);margin-bottom:3px">To</div>
                            <input class="ps-form-control" type="date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Filter by Status</label>
                    <select class="ps-form-control">
                        <option value="">All Statuses</option>
                        <option value="Waiting">Waiting</option>
                        <option value="Approved">Active Borrowings</option>
                        <option value="Overdue">Overdue</option>
                        <option value="Returned">Returned</option>
                        <option value="Declined">Declined</option>
                    </select>
                </div>
                <div class="ps-form-group">
                    <label class="ps-form-label">Export Format</label>
                    <div style="display:flex;gap:10px;margin-top:4px">
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                            <input type="radio" name="export-fmt" value="csv" checked> CSV
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                            <input type="radio" name="export-fmt" value="xlsx"> Excel (.xlsx)
                        </label>
                    </div>
                </div>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-export-modal">Cancel</button>
                <button class="ps-btn ps-btn--primary" data-action="ps-close-modal" data-modal="ps-export-modal">
                    <span class="material-symbols-outlined">download</span> Export
                </button>
            </div>
        </div>
    </div>




    <!-- ============================================================
         MODAL: NOTIFICATIONS
         Fully dynamic — every card below is rendered from $notifications
         (equipment-booking/core/notif-functions.php), which is built
         live from tbl_requests / tbl_room_issues / tbl_inventory. Read
         and deleted state persist server-side in tbl_notif_state via
         equipment-booking/api/notif-*.php. See admin-dashboard.js for
         the click handling (mark-read, delete, navigate, poll).
    ============================================================ -->
    <div class="ps-modal-backdrop" id="notifOverlay">
        <div class="ps-modal ps-modal--lg notif-modal anotif-box" role="dialog" aria-modal="true" aria-labelledby="notifModalTitle">

            <!-- Head: icon + title + unread line + close (same layout as the faculty notifications modal) -->
            <div class="anotif-head">
                <div class="anotif-head-icon">
                    <span class="material-symbols-outlined">notifications</span>
                </div>
                <div class="anotif-head-text">
                    <h3 class="anotif-head-title" id="notifModalTitle">Notifications</h3>
                    <p class="anotif-count-text">You have
                        <strong id="unreadCount"><?php echo $notif_unread; ?> unread</strong>
                        notification<span id="unreadPlural"><?php echo $notif_unread !== 1 ? 's' : ''; ?></span>.
                    </p>
                </div>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="notifOverlay" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- Filter pills (same chips as before; every pill is always present and a pill with no
                 items simply shows no number). Counts are kept live by admin-dashboard.js. -->
            <div class="rq-filter-chips notif-filter-chips" id="notifFilterChips">
                <?php
                $anotif_pills = [
                    'all'     => ['All',      count($notifications)],
                    'unread'  => ['Unread',   $notif_unread],
                    'request' => ['Requests', $notif_cat_counts['request']],
                    'overdue' => ['Overdue',  $notif_cat_counts['overdue']],
                    'room'    => ['Facilities',    $notif_cat_counts['room']],
                    'system'  => ['System',   $notif_cat_counts['system']],
                ];
                foreach ($anotif_pills as $pill_key => [$pill_label, $pill_n]): ?>
                    <button type="button" class="rq-filter-chip<?php echo $pill_key === 'all' ? ' active' : ''; ?>" data-notif-filter="<?php echo $pill_key; ?>"><?php echo $pill_label; ?><?php echo (int)$pill_n > 0 ? ' <span class="notif-chip-count">' . (int)$pill_n . '</span>' : ''; ?></button>
                <?php endforeach; ?>
            </div>

            <div class="ps-modal-body notif-modal-body anotif-body" id="notifList">

                <div class="ps-empty-state notif-empty-state" id="notifFilterEmptyState" style="display:none;">
                    <span class="material-symbols-outlined">filter_alt_off</span>
                    <p>Nothing in this filter right now.</p>
                </div>

                <?php if (empty($notifications)): ?>
                    <div class="ps-empty-state notif-empty-state" id="notifEmptyState">
                        <span class="material-symbols-outlined">notifications_off</span>
                        <p>You're all caught up. No notifications right now.</p>
                    </div>
                    <?php else:
                    $notif_last_group = null;
                    foreach ($notifications as $n):
                        if ($n['group_label'] !== $notif_last_group):
                            $notif_last_group = $n['group_label'];
                    ?>
                            <div class="notif-group-label<?php echo $n['group_danger'] ? ' notif-group-label--danger' : ''; ?>">
                                <span class="material-symbols-outlined"><?php echo $n['group_icon']; ?></span>
                                <?php echo htmlspecialchars($n['group_label']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="notif-item notif-card<?php echo $n['is_read'] ? '' : ' unread'; ?><?php echo $n['urgent'] ? ' notif-urgent' : ''; ?>"
                            data-cat="<?php echo htmlspecialchars($n['cat']); ?>"
                            data-notif-key="<?php echo htmlspecialchars($n['key']); ?>"
                            data-link-tab="<?php echo htmlspecialchars($n['link']['tab'] ?? ''); ?>"
                            data-link-chip="<?php echo htmlspecialchars($n['link']['chip'] ?? ''); ?>"
                            data-link-sub="<?php echo htmlspecialchars($n['link']['sub'] ?? ''); ?>"
                            data-link-request-id="<?php echo htmlspecialchars((string)($n['link']['request_id'] ?? '')); ?>"
                            data-link-issue-id="<?php echo htmlspecialchars((string)($n['link']['issue_id'] ?? '')); ?>"
                            data-link-item-id="<?php echo htmlspecialchars((string)($n['link']['item_id'] ?? '')); ?>">
                            <div class="notif-card-main" role="button" tabindex="0">
                                <span class="anotif-check" aria-hidden="true"><span class="material-symbols-outlined">check</span></span>
                                <div class="notif-icon <?php echo htmlspecialchars($n['icon_class']); ?>">
                                    <span class="material-symbols-outlined"><?php echo htmlspecialchars($n['icon']); ?></span>
                                </div>
                                <div class="notif-body-wrap">
                                    <h4><?php echo htmlspecialchars($n['title']); ?></h4>
                                    <p><?php echo $n['body']; /* pre-escaped in notif-functions.php */ ?></p>
                                </div>
                                <div class="notif-meta">
                                    <span class="notif-time"><?php echo htmlspecialchars($n['time_label']); ?></span>
                                    <div class="unread-dot"></div>
                                    <span class="material-symbols-outlined notif-chevron">expand_more</span>
                                </div>
                            </div>
                            <div class="notif-card-detail">
                                <div class="ps-detail-grid">
                                    <?php foreach ($n['detail'] as $d): ?>
                                        <div class="ps-detail-item">
                                            <div class="ps-detail-label"><?php echo htmlspecialchars($d['label']); ?></div>
                                            <div class="ps-detail-value" <?php echo !empty($d['danger']) ? ' style="color:var(--danger);font-weight:700;"' : ''; ?>><?php echo htmlspecialchars((string)$d['value']); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="notif-card-actions">
                                    <button type="button" class="ps-btn ps-btn--primary ps-btn--sm" data-notif-goto>
                                        <span class="material-symbols-outlined">visibility</span>
                                        <?php echo htmlspecialchars($n['view_label']); ?>
                                    </button>
                                    <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm notif-toggle-read-btn" data-notif-toggle-read>
                                        <span class="material-symbols-outlined"><?php echo $n['is_read'] ? 'mark_email_unread' : 'drafts'; ?></span>
                                        <span data-notif-toggle-label><?php echo $n['is_read'] ? 'Mark as unread' : 'Mark as read'; ?></span>
                                    </button>
                                    <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm notif-delete-btn" data-notif-delete title="Delete notification">
                                        <span class="material-symbols-outlined">delete</span>
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                <?php endforeach;
                endif; ?>

            </div><!-- /notif-modal-body -->

            <!-- Footer: Mark all as read | Select -- and, while selecting, Select all / Read / Unread / Delete selected / Delete all -->
            <div class="anotif-footer" id="notifFooter">

                <!-- Inline confirmation for bulk deletes -->
                <div class="anotif-confirm" id="notifConfirm" role="alertdialog" aria-live="assertive" hidden>
                    <span class="material-symbols-outlined">warning</span>
                    <span class="anotif-confirm-text" id="notifConfirmText"></span>
                    <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm" data-nbar="confirm-cancel">Cancel</button>
                    <button type="button" class="ps-btn ps-btn--danger ps-btn--sm" data-nbar="confirm-ok">Delete</button>
                </div>

                <!-- Selection bar -- only while selecting -->
                <div class="anotif-selbar" id="notifSelBar" hidden>
                    <label class="anotif-selall">
                        <input type="checkbox" id="notifSelectAll">
                        <span>Select all</span>
                    </label>
                    <span class="anotif-sel-count" id="notifSelCount" aria-live="polite">0 selected</span>
                    <div class="anotif-sel-actions">
                        <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm" data-nbar="mark-read" data-needs-sel>
                            <span class="material-symbols-outlined">drafts</span> Read
                        </button>
                        <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm" data-nbar="mark-unread" data-needs-sel>
                            <span class="material-symbols-outlined">mark_email_unread</span> Unread
                        </button>
                        <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm anotif-danger" data-nbar="delete-selected" data-needs-sel>
                            <span class="material-symbols-outlined">delete</span> Delete<span class="anotif-sel-word"> selected</span>
                        </button>
                    </div>
                </div>

                <div class="anotif-footer-main">
                    <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm" data-action="mark-all-read" <?php echo $notif_unread === 0 ? ' disabled' : ''; ?>>
                        <span class="material-symbols-outlined">done_all</span> Mark all as read
                    </button>
                    <div class="anotif-footer-right">
                        <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm anotif-danger" id="notifDeleteAllBtn" data-nbar="delete-all" hidden>
                            <span class="material-symbols-outlined">delete_sweep</span> Delete all
                        </button>
                        <button type="button" class="ps-btn ps-btn--primary ps-btn--sm" id="notifSelectBtn" data-nbar="select" aria-pressed="false" <?php echo empty($notifications) ? ' disabled' : ''; ?>>
                            <span class="material-symbols-outlined">checklist</span> <span id="notifSelectLbl">Select</span>
                        </button>
                    </div>
                </div>
            </div><!-- /anotif-footer -->
        </div>
    </div><!-- /notifOverlay -->


    <!-- Loading Overlay -->
    <div id="loading-overlay">
        <div class="spinner"></div>
        <p style="margin-top:1rem;font-weight:600;color:var(--text-dark);font-size:0.9rem;">Processing...</p>
    </div>

    <!-- QR Scanner Modal -->
    <div id="qrScannerModal" class="qr-scanner-backdrop">
        <div class="qr-scanner-card">
            <button id="closeQrScanner" class="qr-scanner-close" title="Close scanner">✕</button>
            <h3>Scan Return QR Code</h3>
            <p>Point the camera at the faculty member's QR code.</p>
            <div class="qr-video-wrap">
                <video id="qrVideo" playsinline autoplay></video>
                <!-- Scan guide overlay -->
                <div class="qr-scan-guide">
                    <div class="qr-scan-guide-box"></div>
                </div>
            </div>
            <p id="qrScanStatus">
                Initializing camera...
            </p>
        </div>
    </div>

    <!-- Toast -->
    <div id="app-toast"></div>

    <?php
    // Toast queue: messages the server wants shown once this page has loaded, in order.
    //   • After a real sign-in (landing-page.php arms the flag): a welcome line, then an overdue alert
    //     if anything is overdue. Refreshing / switching tabs finds no flag, so nothing repeats.
    //   • After a room save / archive / restore redirect (?room_added=1 …): that confirmation or error.
    //     (Equipment added/updated/error already toast through fixInventoryTab() further down.)
    $toast_queue = [];

    $show_login_toast = !empty($_SESSION['admin_login_toast']);
    unset($_SESSION['admin_login_toast']);
    if ($show_login_toast) {
        $lt_first = explode(' ', trim((string)$admin_name))[0] ?? '';
        $lt_msg   = 'Welcome back' . ($lt_first !== '' ? ', ' . $lt_first : '') . '.';
        $lt_n     = (int)$notif_unread;
        if ($lt_n > 0) {
            $lt_msg .= ' You have ' . $lt_n . ' unread notification' . ($lt_n === 1 ? '' : 's') . '.';
        }
        $toast_queue[] = $lt_msg;

        $ov_n = (int)($dash_data['overdue'] ?? 0);
        if ($ov_n > 0) {
            $toast_queue[] = 'Overdue alert: ' . $ov_n . ($ov_n === 1 ? ' item is' : ' items are')
                . ' currently overdue and need immediate attention.';
        }
    }

    $flash_toasts = [
        'notice_sent'   => 'Overdue notice sent to the borrower.',
        'loans_cleared' => 'The On Loan list was cleared. All loan records were deleted.',
        'room_added'    => 'Facility added to the registry.',
        'room_updated'  => 'Facility updated. Changes saved.',
        'room_archived' => 'Facility archived. It has been removed from the active registry.',
        'room_restored' => 'Facility restored. It is now back in the active registry.',
        'room_error'    => 'Error: Could not save facility. Please check required fields and try again.',
    ];
    $flash_strip = [];
    foreach ($flash_toasts as $flash_key => $flash_msg) {
        if (isset($_GET[$flash_key])) {
            $toast_queue[] = $flash_msg;
            $flash_strip[] = $flash_key;
        }
    }

    if ($toast_queue):
    ?>
        <div id="toast-queue" hidden data-strip="<?php echo htmlspecialchars(implode(',', $flash_strip), ENT_QUOTES); ?>">
            <?php foreach ($toast_queue as $tq_msg): ?>
                <span data-msg="<?php echo htmlspecialchars($tq_msg, ENT_QUOTES); ?>"></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>


    <div class="modal-overlay" id="changePassModal" style="display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.6); 
            z-index: 99999; 
            align-items: center; 
            justify-content: center;">
        <div class="modal-backdrop" data-action="close-change-pass" style="position: absolute; inset: 0;"></div>
        <div class="eq-card form-card"
            style="position: relative; width: 100%; max-width: 400px; margin: 20px; z-index: 100000;">
            <div class="form-card-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    Change Password
                </h2>
                <button type="button" class="btn-close-custom" data-action="close-change-pass">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="form-card-body">
                <div id="cp-alert"
                    style="display:none; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.85rem; font-weight: 500;">
                </div>

                <form id="changePasswordForm">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="cp-current-password">Current Password</label>
                        <div class="fac-pw-wrap">
                            <input type="password" id="cp-current-password" name="current_password"
                                class="form-control-custom" required>
                            <button type="button" class="fac-pw-toggle" data-target="cp-current-password"
                                aria-label="Toggle password">
                                <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="cp-new-password">New Password</label>
                        <div class="fac-pw-wrap">
                            <input type="password" id="cp-new-password" name="new_password"
                                class="form-control-custom" minlength="4" required>
                            <button type="button" class="fac-pw-toggle" data-target="cp-new-password,cp-confirm-password"
                                aria-label="Toggle password">
                                <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="cp-confirm-password">Confirm New Password</label>
                        <div class="fac-pw-wrap">
                            <input type="password" id="cp-confirm-password" name="confirm_password"
                                class="form-control-custom" minlength="4" required>
                            <button type="button" class="fac-pw-toggle" data-target="cp-new-password,cp-confirm-password"
                                aria-label="Toggle password">
                                <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                            </button>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                        <button type="button" class="btn-cancel-acc" data-action="close-change-pass"
                            style="padding: 8px 16px; width: auto;">Cancel</button>
                        <button type="submit" class="btn-submit-form"
                            style="margin-top: 0; width: auto; padding: 8px 16px;">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Admin Cancel Reservation Modal -->
    <div class="modal-overlay" id="adminCancelRrModal"
        style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:99999;align-items:center;justify-content:center;">
        <div class="modal-backdrop" id="adminCancelRrBackdrop" style="position:absolute;inset:0;"></div>
        <div class="eq-card form-card" style="position:relative;width:100%;max-width:420px;margin:20px;z-index:100000;">
            <div class="form-card-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="15" y1="9" x2="9" y2="15" />
                        <line x1="9" y1="9" x2="15" y2="15" />
                    </svg>
                    Cancel Reservation
                </h2>
                <button type="button" class="btn-close-custom" id="closeAdminCancelRrModal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="form-card-body">
                <div id="admin-cancel-rr-alert" style="display:none;padding:10px;border-radius:6px;margin-bottom:15px;font-size:.85rem;font-weight:500;"></div>
                <p id="adminCancelRrDesc" style="font-size:.85rem;color:var(--text-light);margin-bottom:1rem;"></p>
                <p style="font-size:.83rem;color:var(--text-light);margin-bottom:1rem;">
                    The faculty member will be notified by email. All faculty on the waitlist for this slot will also be notified that the slot is now available.
                </p>
                <input type="hidden" id="adminCancelRrId">
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:1rem;">
                    <button type="button" class="btn-cancel-acc" id="cancelAdminCancelRrBtn" style="padding:8px 16px;width:auto;">Go Back</button>
                    <button type="button" class="btn-submit-form" id="submitAdminCancelRrBtn"
                        style="margin-top:0;width:auto;padding:8px 16px;background:var(--danger);">
                        Confirm Cancellation
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Issue Review Modal -->
    <div class="modal-overlay" id="issueReviewModal"
        style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:99999;align-items:center;justify-content:center;">
        <div class="modal-backdrop" id="issueReviewModalBackdrop" style="position:absolute;inset:0;"></div>
        <div class="eq-card form-card" style="position:relative;width:100%;max-width:460px;margin:20px;z-index:100000;">
            <div class="form-card-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                    </svg>
                    Review Issue Report
                </h2>
                <button type="button" class="btn-close-custom" id="closeIssueReviewModal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="form-card-body">
                <div id="issue-review-alert" style="display:none;padding:10px;border-radius:6px;margin-bottom:15px;font-size:.85rem;font-weight:500;"></div>
                <p id="issueReviewDesc" style="font-size:.85rem;color:var(--text-light);margin-bottom:1rem;"></p>
                <div class="form-group">
                    <label>Admin Notes <span style="font-size:.75rem;font-weight:400;">(optional)</span></label>
                    <textarea id="issueAdminNotes" class="form-control-custom" rows="3"
                        placeholder="Internal notes about this report or action taken…"
                        style="resize:vertical;"></textarea>
                </div>
                <div class="form-group" style="margin-top:.75rem;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500;">
                        <input type="checkbox" id="issueSetMaintenance" style="width:16px;height:16px;">
                        Set facility to <strong>Maintenance</strong> mode
                    </label>
                    <small style="color:var(--text-light);font-size:.75rem;margin-top:4px;display:block;">
                        Only applies when resolving (not dismissing). You can change the facility status back any time from the Facilities registry.
                    </small>
                </div>
                <input type="hidden" id="issueReviewId">
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:1.25rem;">
                    <button type="button" class="btn-cancel-acc" id="cancelIssueReviewBtn" style="padding:8px 16px;width:auto;">Cancel</button>
                    <button type="button" class="btn-submit-form" id="dismissIssueBtn"
                        style="margin-top:0;width:auto;padding:8px 16px;background:#6b7280;">Dismiss</button>
                    <button type="button" class="btn-submit-form" id="resolveIssueBtn"
                        style="margin-top:0;width:auto;padding:8px 16px;">Mark Resolved</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Override Modal -->
    <div class="modal-overlay" id="overrideModal"
        style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:99999;align-items:center;justify-content:center;">
        <div class="modal-backdrop" id="overrideModalBackdrop" style="position:absolute;inset:0;"></div>
        <div class="eq-card form-card" style="position:relative;width:100%;max-width:440px;margin:20px;z-index:100000;">
            <div class="form-card-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                    </svg>
                    Override Request
                </h2>
                <button type="button" class="btn-close-custom" id="closeOverrideModal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="form-card-body">
                <div id="override-alert"
                    style="display:none;padding:10px;border-radius:6px;margin-bottom:15px;font-size:0.85rem;font-weight:500;">
                </div>
                <p id="overrideDesc" style="font-size:0.85rem;color:var(--text-light);margin-bottom:1rem;"></p>
                <!-- Context info shown when direction is fixed (Approved→Declined or Declined→Approved) -->
                <div id="overrideContextInfo"
                    style="display:none;padding:10px 12px;border-radius:6px;margin-bottom:1rem;font-size:0.83rem;font-weight:500;">
                </div>
                <div class="form-group" id="overrideStatusGroup" style="display:none;">
                    <label>New Status</label>
                    <select id="overrideNewStatus" class="form-control-custom">
                        <option value="Approved">Approved</option>
                        <option value="Declined">Declined</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Override Reason <span style="color:var(--danger);">*</span></label>
                    <textarea id="overrideReason" class="form-control-custom" rows="3"
                        placeholder="Enter mandatory reason for this override (min. 5 characters)..."
                        style="resize:vertical;"></textarea>
                    <small id="overrideReasonHint" style="color:var(--text-light);font-size:0.75rem;">Minimum 5
                        characters required.</small>
                </div>
                <input type="hidden" id="overrideRequestId">
                <input type="hidden" id="overrideCurrentStatus">
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:1rem;">
                    <button type="button" class="btn-cancel-acc" id="cancelOverrideBtn"
                        style="padding:8px 16px;width:auto;">Cancel</button>
                    <button type="button" class="btn-submit-form" id="submitOverrideBtn"
                        style="margin-top:0;width:auto;padding:8px 16px;" disabled>Apply Override</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/logout-modal.js?v=<?php echo @filemtime('assets/js/logout-modal.js'); ?>"></script>
    <script src="equipment-booking/assets/js/admin-dashboard.js"></script>
    <script src="equipment-booking/assets/js/admin-student-registry.js?v=<?php echo @filemtime('equipment-booking/assets/js/admin-student-registry.js'); ?>" defer></script>
    <script src="equipment-booking/assets/js/admin-live-render.js"></script>
    <script src="equipment-booking/assets/js/admin-dashboard-responsive.js?v=<?php echo @filemtime('equipment-booking/assets/js/admin-dashboard-responsive.js'); ?>" defer></script>

    <!-- Admin poll toast -->
    <div id="admin-poll-toast">
    </div>

    <!-- Modal helpers (inline for guaranteed global scope) -->
    <script nonce="<?php echo $csp_nonce; ?>">
        /* ── Modal open / close ─────────────────────────────────────── */
        function psOpenModal(id) {
            var el = document.getElementById(id);
            if (el) el.classList.add('ps-modal-open');
        }

        function psCloseModal(id) {
            var el = document.getElementById(id);
            if (el) el.classList.remove('ps-modal-open');
        }

        /* Close on backdrop click */
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.ps-modal-backdrop').forEach(function(bd) {
                bd.addEventListener('click', function(e) {
                    if (e.target === bd) bd.classList.remove('ps-modal-open');
                });
            });

        });
    </script>


    <!-- ── MODAL: EDIT EQUIPMENT ────────────────────────────────── -->
    <div class="ps-modal-backdrop" id="eq-edit-modal">
        <div class="ps-modal">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">inventory_2</span>
                </div>
                <h3>Edit Equipment</h3>
                <button class="ps-modal-close" data-action="ps-close-modal" data-modal="eq-edit-modal" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <form method="POST" enctype="multipart/form-data" id="eqEditForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="item_id" id="eqm-item-id">
                    <input type="hidden" name="old_image" id="eqm-old-image">
                    <input type="hidden" name="remove_image" id="eqm-remove-image-flag" value="0">

                    <div class="form-group">
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Item Name <span class="inv-req">*</span></label>
                        <input type="text" name="item_name" id="eqm-item-name" class="form-control-custom"
                            placeholder="e.g. Extension Cord" required>
                    </div>

                    <div class="form-group">
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Category <span class="inv-req">*</span></label>
                        <select name="category" id="eqm-category" class="form-control-custom" required>
                            <option value="">Select category...</option>
                            <?php
                            foreach (['Audio/Visual', 'Cables & Connectors', 'Computing', 'Lab Equipment', 'Networking', 'Power', 'Tools', 'Others'] as $c) {
                                echo "<option value=\"$c\">$c</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Quantity <span class="inv-req">*</span></label>
                            <input type="number" name="quantity" id="eqm-quantity" class="form-control-custom" min="0" required>
                        </div>
                        <div class="form-group">
                            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Condition</label>
                            <select name="condition" id="eqm-condition" class="form-control-custom">
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                                <option value="For Repair">For Repair</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Item Image</label>
                        <div class="drop-zone" id="eqm-dropZone">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="32" height="32"
                                style="color:var(--text-light)">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <circle cx="8.5" cy="8.5" r="1.5" />
                                <polyline points="21 15 16 10 5 21" />
                            </svg>
                            <p>Click to upload, drag &amp; drop, or paste an image</p>
                            <input type="file" name="item_image" id="eqm-itemImageInput" accept="image/jpeg,image/png" style="display:none;">
                            <img id="eqm-imagePreview" class="drop-zone-preview" style="display:none;">
                        </div>
                        <button type="button" id="eqm-removeImageBtn" class="hidden"
                            style="margin-top:6px;font-size:0.75rem;color:var(--danger);background:none;border:none;cursor:pointer;">
                            &#x2715; Remove image
                        </button>
                    </div>

                    <div style="border-top:1px solid var(--khaki-border);margin-top:1rem;padding-top:1rem">
                        <a href="#" id="eqm-archive-link" class="ps-btn ps-btn--danger" style="text-decoration:none;display:inline-flex;">
                            <span class="material-symbols-outlined">archive</span> Archive Item
                        </a>
                    </div>
                </form>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" type="button" data-action="ps-close-modal" data-modal="eq-edit-modal">Cancel</button>
                <button class="ps-btn ps-btn--primary" type="submit" form="eqEditForm" name="update_item">
                    <span class="material-symbols-outlined">save</span> Update Item
                </button>
            </div>
        </div>
    </div>

    <script nonce="<?php echo $csp_nonce; ?>">
        (function() {
            /* Dropdown "My Account" / "Settings" buttons are bound in
               admin-dashboard.js (routes to the correct Settings sub-tab). */

            /* Override initView() for ?edit_item= and ?view=inventory.
               admin-dashboard.js calls _switchTabDOM('lending') for those,
               but inventory is now its own #panel-inventory tab.

               IMPORTANT: admin-dashboard.js's own init() runs as soon as its
               <script> tag is reached — if document.readyState is no longer
               'loading' by then (typical, since that script sits near the
               end of the page), init() runs synchronously immediately,
               BEFORE any 'DOMContentLoaded' listener registered here would
               ever fire (the event has already passed). That's why this
               used to silently fail to land on the Equipment tab after a
               save. Matching admin-dashboard.js's own readyState check
               instead of blindly waiting for DOMContentLoaded fixes it. */
            function fixInventoryTab() {
                var params = new URLSearchParams(window.location.search);
                if (params.get('edit_item') || params.get('view') === 'inventory') {
                    document.querySelectorAll('.tab-panel').forEach(function(p) {
                        p.classList.remove('active');
                    });
                    var pInv = document.getElementById('panel-inventory');
                    if (pInv) pInv.classList.add('active');
                    document.querySelectorAll('.nav-item').forEach(function(n) {
                        n.classList.remove('active');
                    });
                    var snavInv = document.getElementById('snav-inventory');
                    if (snavInv) snavInv.classList.add('active');
                }

                /* Show a toast for the add/update/error redirects from
                   admin-functions.php, then strip the param so refreshing
                   or navigating away doesn't re-show it. */
                var msg = null;
                if (params.get('added') === '1') msg = 'Equipment added successfully.';
                else if (params.get('updated') === '1') msg = 'Equipment updated successfully.';
                else if (params.get('error') === 'filetype') msg = 'Only JPG and PNG images are allowed.';
                else if (params.get('error') === 'filesize') msg = 'Image too large. Maximum size is 2MB.';
                else if (params.get('error') === 'dberror') msg = 'Error saving to database. Please try again.';

                if (msg && typeof showToast === 'function') {
                    showToast(msg);
                    params.delete('added');
                    params.delete('updated');
                    params.delete('error');
                    var qs = params.toString();
                    var newUrl = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
                    window.history.replaceState({}, '', newUrl);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(fixInventoryTab, 0);
                });
            } else {
                setTimeout(fixInventoryTab, 0);
            }
        })();

        /* ── Edit Equipment modal: populate from the clicked row's
           data-* attributes, then open it. (ps-open-modal/close-modal
           are already handled by the main delegation in
           admin-dashboard.js — no need to duplicate that here.) ── */
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-action="eq-open-edit"]');
            if (!btn) return;
            var row = btn.closest('.inv-row-item');
            if (!row) return;
            var d = row.dataset;

            document.getElementById('eqm-item-id').value = d.itemId;
            document.getElementById('eqm-old-image').value = d.itemImage;
            document.getElementById('eqm-remove-image-flag').value = '0';
            document.getElementById('eqm-item-name').value = d.itemName;
            document.getElementById('eqm-category').value = d.itemCategory;
            document.getElementById('eqm-quantity').value = d.itemQuantity;
            document.getElementById('eqm-condition').value = d.itemCondition || 'Good';

            var preview = document.getElementById('eqm-imagePreview');
            var removeBtn = document.getElementById('eqm-removeImageBtn');
            var fileInput = document.getElementById('eqm-itemImageInput');
            if (fileInput) fileInput.value = '';
            if (d.itemImage && d.itemImage.indexOf('default.png') === -1) {
                preview.src = d.itemImageFull;
                preview.style.display = 'block';
                removeBtn.classList.remove('hidden');
            } else {
                preview.src = '';
                preview.style.display = 'none';
                removeBtn.classList.add('hidden');
            }

            var archiveLink = document.getElementById('eqm-archive-link');
            archiveLink.href = 'admin-dashboard.php?delete_item=' + d.itemId;
            archiveLink.onclick = null; // confirmation handled by delegated listener in inventory AJAX block

            psOpenModal('eq-edit-modal');
        });
    </script>

    <!-- ══════════════════════════════════════════════════════
         ROOM SCHEDULE MODAL
         ══════════════════════════════════════════════════════ -->
    <div id="roomScheduleModal" class="hidden" role="dialog" aria-modal="true" aria-labelledby="rsm-title">
        <div class="rsm-card">
            <div class="rsm-head">
                <div class="rsm-head-icon">
                    <span class="material-symbols-outlined">calendar_month</span>
                </div>
                <h3 id="rsm-title">Facility Schedule</h3>
                <button class="rsm-close-btn" data-action="close-room-schedule" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="rsm-info-row">
                <span class="rsm-room-meta" id="rsm-meta"></span>
                <div class="rsm-week-ctrl">
                    <button type="button" class="rsm-today-btn" data-action="room-schedule-today">Today</button>
                    <button class="rsm-week-nav-btn" id="rsm-prev" data-action="room-schedule-nav" data-dir="-1" title="Previous week" aria-label="Previous week">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </button>
                    <span class="rsm-week-lbl" aria-live="polite">
                        <b id="rsm-week-lbl"></b>
                        <small id="rsm-week-sub"></small>
                    </span>
                    <button class="rsm-week-nav-btn" id="rsm-next" data-action="room-schedule-nav" data-dir="1" title="Next week" aria-label="Next week">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </button>
                </div>
            </div>
            <div class="rsm-mobile-daytabs" id="rsm-daytabs" role="group" aria-label="Day of the week"></div>
            <div class="rsm-sync-note hidden" id="rsm-sync-note">Couldn't refresh just now — showing the reservations loaded with this page. Reopen the schedule or reload to retry.</div>
            <div class="rsm-body" id="rsm-body">
                <div class="rsm-grid" id="rsm-grid">
                    <div class="rsm-grid-head" id="rsm-grid-head"></div>
                    <div class="rsm-grid-body" id="rsm-grid-body"></div>
                </div>
            </div>
            <div class="rsm-foot">
                <div class="rsm-legend">
                    <div class="rsm-legend-item">
                        <div class="rsm-legend-dot reserved"></div> Reserved
                    </div>
                    <div class="rsm-legend-item">
                        <div class="rsm-legend-dot past"></div> Already finished
                    </div>
                    <div class="rsm-legend-item">
                        <div class="rsm-legend-dot avail"></div> Available
                    </div>
                    <div class="rsm-legend-item">
                        <div class="rsm-legend-line"></div> Now
                    </div>
                </div>
                <button type="button" class="btn-cancel-acc" data-action="close-room-schedule">Close</button>
            </div>
        </div><!-- /.rsm-card -->
    </div><!-- /#roomScheduleModal -->

    <?php if ($is_super_admin): ?>
        <!-- Permanent-delete confirmation (Super Admin only). Direct <body> child on purpose. -->
        <div id="roomDeleteConfirm" class="hidden" role="dialog" aria-modal="true" aria-labelledby="rdc-title">
            <div class="rdc-card">
                <div class="rdc-head">
                    <div class="rdc-icon"><span class="material-symbols-outlined">delete_forever</span></div>
                    <h3 id="rdc-title">Delete facility permanently?</h3>
                </div>
                <div class="rdc-body">
                    <p class="rdc-room" id="rdc-room-name"></p>
                    <p>This cannot be undone. The facility and the following will be removed for good:</p>
                    <ul class="rdc-list">
                        <li id="rdc-resv">No reservation records</li>
                        <li>Its issue reports and waitlist entries</li>
                    </ul>
                    <p class="rdc-note">Equipment requests that mention this facility are kept.</p>
                    <label class="rdc-check">
                        <input type="checkbox" id="rdc-ack">
                        <span>I understand this cannot be undone</span>
                    </label>
                </div>
                <div class="rdc-foot">
                    <button type="button" class="rdc-btn rdc-btn-ghost" data-action="close-room-delete">Cancel</button>
                    <button type="button" class="rdc-btn rdc-btn-danger" id="rdc-confirm" disabled>
                        <span class="material-symbols-outlined">delete_forever</span>
                        Delete permanently
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>



    <?php if ($is_super_admin): ?>
        <!-- ================================================================
         MODAL: DELETE THE WHOLE ON LOAN LIST  (Super Admin only)
         Shows exactly what will go (counted by the server), then asks for the
         Super Admin's own password. Driven by the "Delete All" block in
         admin-dashboard.js -> equipment-booking/api/delete-all-loans.php
        ================================================================ -->
        <div class="ps-modal-backdrop" id="eq-loan-wipe-modal">
            <div class="ps-modal ps-modal--sm">
                <div class="ps-modal-head">
                    <div class="ps-modal-head-icon ps-mhi--danger">
                        <span class="material-symbols-outlined">delete_forever</span>
                    </div>
                    <h3>Delete the entire loan list?</h3>
                    <button type="button" class="ps-modal-close" id="eq-loan-wipe-close" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="ps-modal-body">
                    <p class="fac-reg-auth-lead">Every record in <strong>All Loans</strong> will be permanently deleted. This cannot be undone.</p>
                    <ul class="eq-wipe-summary" id="eq-loan-wipe-summary" aria-live="polite">
                        <li class="eq-wipe-loading">Counting records…</li>
                    </ul>
                    <p class="eq-wipe-note hidden" id="eq-loan-wipe-note"></p>
                    <label class="fac-del-pw-label" for="eq-loan-wipe-pw">Your password</label>
                    <div class="fac-pw-wrap">
                        <input type="password" id="eq-loan-wipe-pw" class="form-control-custom"
                            maxlength="128" autocomplete="current-password" placeholder="Enter your password to confirm">
                        <button type="button" class="eq-wipe-pw-toggle" id="eq-loan-wipe-pw-toggle" aria-label="Show or hide password">
                            <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                        </button>
                    </div>
                    <p class="fac-del-error hidden" id="eq-loan-wipe-error" role="alert"></p>
                </div>
                <div class="ps-modal-foot">
                    <button type="button" class="ps-btn ps-btn--ghost" id="eq-loan-wipe-cancel">Cancel</button>
                    <button type="button" class="ps-btn ps-btn--danger" id="eq-loan-wipe-confirm" disabled>
                        <span class="material-symbols-outlined">delete_forever</span> Delete everything
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================================================================
     MODAL: EDIT FACULTY ACCOUNT
    ================================================================ -->
    <div class="ps-modal-backdrop" id="fac-edit-modal">
        <div class="ps-modal">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">manage_accounts</span>
                </div>
                <h3>Edit Faculty Account</h3>
                <button class="ps-modal-close" id="fac-edit-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">

                <!-- Who is being edited -->
                <div class="fac-edit-id">
                    <div class="fac-edit-avatar" id="fac-edit-avatar">F</div>
                    <div>
                        <div class="fac-edit-id-name" id="fac-edit-name-display">&#8212;</div>
                        <div class="fac-edit-id-meta" id="fac-edit-meta-display">&#8212;</div>
                    </div>
                </div>

                <!-- Account details -->
                <div class="fac-edit-section">Account details</div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="fac-edit-first">First name</label>
                        <input type="text" id="fac-edit-first" class="form-control-custom" placeholder="First">
                    </div>
                    <div class="form-group">
                        <label for="fac-edit-last">Last name</label>
                        <input type="text" id="fac-edit-last" class="form-control-custom" placeholder="Last">
                    </div>
                </div>
                <div class="form-group">
                    <label for="fac-edit-email">PUPSync email</label>
                    <input type="email" id="fac-edit-email" class="form-control-custom" placeholder="email@example.com">
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label for="fac-edit-backup">Backup email <span class="fac-edit-opt">(optional)</span></label>
                    <input type="email" id="fac-edit-backup" class="form-control-custom" placeholder="backup@gmail.com">
                </div>

                <!-- Organization: org picker + org borrowing only appear for advisers -->
                <div class="fac-opt-card">
                    <label class="fac-opt-row" for="fac-edit-adviser">
                        <span class="fac-opt-text">
                            <span class="fac-opt-title">Organization adviser</span>
                            <span class="fac-opt-sub">Gets organization borrowing automatically</span>
                        </span>
                        <input type="checkbox" id="fac-edit-adviser" class="faculty-toggle-input">
                        <span class="faculty-toggle-track"></span>
                    </label>

                    <div class="fac-opt-extra" id="fac-edit-org-group" style="display:none;">
                        <div class="form-group">
                            <label for="fac-edit-org">Organization</label>
                            <?php
                            $orgs_grouped     = orgs_load_grouped($conn);
                            $orgs_adviser_map = $orgs_adviser_map ?? orgs_adviser_map($conn);
                            $orgs_total = count($orgs_grouped[ORG_CATEGORY_ACADEMIC])
                                + count($orgs_grouped[ORG_CATEGORY_NON_ACADEMIC])
                                + count($orgs_grouped['']);
                            if ($orgs_total > 0): ?>
                                <select id="fac-edit-org" class="form-control-custom"
                                    data-advisers="<?= htmlspecialchars(json_encode($orgs_adviser_map, JSON_FORCE_OBJECT), ENT_QUOTES, 'UTF-8') ?>">
                                    <option value="">&#8212; Select Organization &#8212;</option>
                                    <?= orgs_render_options($orgs_grouped) ?>
                                </select>
                            <?php else: ?>
                                <select id="fac-edit-org" class="form-control-custom" disabled>
                                    <option value="">&#8212; Organizations unavailable &#8212;</option>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div id="fac-edit-alert" class="alert-banner hidden" role="alert" style="margin-bottom:0"></div>
            </div>
            <div class="ps-modal-foot fac-foot-split">
                <button class="ps-btn fac-btn-delete" id="fac-edit-delete-btn" type="button">
                    <span class="material-symbols-outlined">person_remove</span> Delete
                </button>
                <div class="fac-foot-actions">
                    <button class="ps-btn ps-btn--ghost" id="fac-edit-cancel">Cancel</button>
                    <button class="ps-btn ps-btn--primary" id="fac-edit-save">
                        <span class="material-symbols-outlined">save</span> Save
                    </button>
                </div>
            </div>
        </div>
    </div>


    <!-- ================================================================
     MODAL: DELETE FACULTY CONFIRM
     One message at a time: "checking" -> "confirm" OR "blocked" (override).
    ================================================================ -->
    <div class="ps-modal-backdrop" id="fac-delete-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--danger">
                    <span class="material-symbols-outlined">person_remove</span>
                </div>
                <h3>Delete Faculty Account</h3>
                <button class="ps-modal-close" id="fac-delete-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <p class="fac-del-lead">Permanently delete <strong id="fac-delete-name">this faculty member</strong>?</p>

                <!-- View: checking -->
                <p class="fac-del-checking" id="fac-delete-view-checking">
                    <span class="material-symbols-outlined">progress_activity</span> Checking account&hellip;
                </p>

                <!-- View: confirm (nothing blocking) -->
                <p class="fac-del-note hidden" id="fac-delete-view-confirm">This can&rsquo;t be undone. Borrowing history is kept.</p>

                <!-- View: blocked (open bookings) -> Super Admin may override with their password -->
                <div class="hidden" id="fac-delete-view-blocked">
                    <div class="fac-del-warn">
                        <div class="fac-del-warn-title">
                            <span class="material-symbols-outlined">warning</span> Open bookings found
                        </div>
                        <ul class="fac-del-list" id="fac-delete-list"></ul>
                    </div>
                    <label class="fac-del-pw-label" for="fac-delete-password">Enter your password to delete anyway</label>
                    <div class="fac-pw-wrap">
                        <input type="password" id="fac-delete-password" class="form-control-custom"
                            maxlength="128" autocomplete="current-password" placeholder="Your password">
                        <button type="button" class="fac-pw-toggle"
                            data-target="fac-delete-password" aria-label="Toggle password">
                            <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                        </button>
                    </div>
                </div>

                <p class="fac-del-error hidden" id="fac-delete-error" role="alert"></p>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" id="fac-delete-cancel">Cancel</button>
                <button class="ps-btn ps-btn--danger" id="fac-delete-confirm" disabled>
                    <span class="material-symbols-outlined">delete</span> <span id="fac-delete-confirm-label">Delete Account</span>
                </button>
            </div>
        </div>
    </div>


    <!-- ================================================================
     MODALS: VIEW FACULTY REGISTRY
     Step 1 (auth)  -> the Super Admin re-enters their own password
     Step 2 (list)  -> read-only list of the imported registry entries
    ================================================================ -->
    <div class="ps-modal-backdrop" id="fac-reg-auth-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">lock</span>
                </div>
                <h3 id="fac-reg-auth-title">View Faculty Registry</h3>
                <button class="ps-modal-close" id="fac-reg-auth-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <p class="fac-reg-auth-lead" id="fac-reg-auth-lead">The registry holds every faculty ID and name. Enter your password to open it.</p>
                <label class="fac-del-pw-label" for="fac-reg-auth-password">Your password</label>
                <div class="fac-pw-wrap">
                    <input type="password" id="fac-reg-auth-password" class="form-control-custom"
                        maxlength="128" autocomplete="current-password" placeholder="Your password">
                    <button type="button" class="fac-pw-toggle"
                        data-target="fac-reg-auth-password" aria-label="Toggle password">
                        <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                    </button>
                </div>
                <p class="fac-del-error hidden" id="fac-reg-auth-error" role="alert"></p>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" id="fac-reg-auth-cancel">Cancel</button>
                <button class="ps-btn ps-btn--primary" id="fac-reg-auth-confirm">
                    <span class="material-symbols-outlined">lock_open</span> Unlock
                </button>
            </div>
        </div>
    </div>

    <div class="ps-modal-backdrop" id="fac-reg-list-modal">
        <div class="ps-modal ps-modal--lg fac-reg-list-modal">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">badge</span>
                </div>
                <h3>Faculty Registry <span class="fac-count-badge" id="fac-reg-list-count"></span></h3>
                <button class="ps-modal-close" id="fac-reg-list-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body fac-reg-list-body">
                <div class="fac-search fac-reg-list-search">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text" id="fac-reg-list-search" class="form-control-custom"
                        placeholder="Search name, ID or department" autocomplete="off">
                </div>
                <div class="tbl-wrap fac-reg-list-wrap">
                    <table class="admin-table fac-reg-list-table">
                        <thead>
                            <tr>
                                <th style="width:44px">#</th>
                                <th>Faculty ID</th>
                                <th>Name</th>
                                <th>Department</th>
                            </tr>
                        </thead>
                        <tbody id="fac-reg-list-tbody"></tbody>
                    </table>
                    <p class="fac-reg-list-empty hidden" id="fac-reg-list-empty">No matching entries.</p>
                </div>
            </div>
            <div class="ps-modal-foot fac-reg-list-foot">
                <span class="fac-reg-list-meta" id="fac-reg-list-meta"></span>
                <button class="ps-btn ps-btn--ghost" id="fac-reg-list-done">Close</button>
            </div>
        </div>
    </div>

    <!-- ================================================================
     MODAL: IMPORTED FILES
     Opened after the password step above. Lists every file imported into the
     Faculty Registry (newest first) with View (PDF, in the browser) and Download.
     The files themselves come from get-faculty-registry-file.php.
    ================================================================ -->
    <div class="ps-modal-backdrop" id="fac-reg-files-modal">
        <div class="ps-modal ps-modal--lg fac-reg-files-modal">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">folder_open</span>
                </div>
                <h3>Imported Files <span class="fac-count-badge" id="fac-reg-files-total"></span></h3>
                <button class="ps-modal-close" id="fac-reg-files-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body fac-reg-files-body">
                <p class="fac-reg-files-note">Every file imported into the registry is kept here exactly as it was uploaded. Files imported before this list existed were not kept.</p>
                <p class="fac-del-error hidden" id="fac-reg-files-alert" role="alert"></p>
                <ul class="fac-files-list" id="fac-reg-files-list"></ul>
                <p class="fac-reg-list-empty hidden" id="fac-reg-files-empty">No files recorded yet. Files you import from now on will appear here.</p>
            </div>
            <div class="ps-modal-foot fac-reg-list-foot">
                <span class="fac-reg-list-meta" id="fac-reg-files-meta"></span>
                <button class="ps-btn ps-btn--ghost" id="fac-reg-files-done">Close</button>
            </div>
        </div>
    </div>


    <!-- Faculty Panel JS -->
    <script nonce="<?php echo $csp_nonce; ?>">
        (function() {
            function openFacModal(id) {
                var el = document.getElementById(id);
                if (el) el.classList.add('ps-modal-open');
            }

            function closeFacModal(id) {
                var el = document.getElementById(id);
                if (el) el.classList.remove('ps-modal-open');
                if (id === 'fac-delete-modal') {
                    _facDelCheckSeq++; /* ignore any check still in flight */
                    var pw = document.getElementById('fac-delete-password');
                    if (pw) {
                        pw.value = '';
                        pw.type = 'password';
                    }
                }
            }

            /* Search + 10-per-page pagination for this list live in admin-dashboard.js
               (setupFacultyList), together, so the two never fight over which rows show. */

            /* Edit modal: keep a reference to the row currently being edited,
               so Save Changes can identify + update it after a successful save. */
            var _facEditRow = null;

            function _syncFacEditOrgVisibility() {
                var chk = document.getElementById('fac-edit-adviser');
                var grp = document.getElementById('fac-edit-org-group');
                var sel = document.getElementById('fac-edit-org');
                if (!chk || !grp) return;
                grp.style.display = chk.checked ? '' : 'none';

                /* The organization picker only exists for advisers. Hiding it also
                   clears it, so a regular faculty account is never saved with an
                   organization. (Org borrowing itself is automatic for advisers.) */
                if (!chk.checked && sel) sel.value = '';
            }

            var _facEditAdviserChk = document.getElementById('fac-edit-adviser');
            if (_facEditAdviserChk) {
                _facEditAdviserChk.addEventListener('change', _syncFacEditOrgVisibility);
            }

            function _showFacEditAlert(msg, isError) {
                var box = document.getElementById('fac-edit-alert');
                if (!box) return;
                box.className = 'alert-banner ' + (isError ? 'alert-danger' : 'alert-success');
                box.textContent = msg;
                box.classList.remove('hidden');
            }

            function _clearFacEditAlert() {
                var box = document.getElementById('fac-edit-alert');
                if (!box) return;
                box.classList.add('hidden');
                box.textContent = '';
            }

            /* One adviser per organization. Which organization has which adviser
               (organization id -> {faculty_id, name}) is seeded by the server and kept in
               step with the edits and deletes made on this page. It only drives the UI:
               the server re-checks every save, so a stale page can never break the rule. */
            var _facAdvisers = {};
            (function() {
                var s = document.getElementById('fac-edit-org');
                if (!s) return;
                try {
                    _facAdvisers = JSON.parse(s.getAttribute('data-advisers') || '{}') || {};
                } catch (e) {
                    _facAdvisers = {};
                }
            })();

            /* Disable the organizations that already have an adviser (except the one belonging
               to exceptFacultyId, so an adviser can keep their own organization). */
            function _facApplyOrgAvailability(sel, exceptFacultyId) {
                if (!sel) return;
                Array.prototype.forEach.call(sel.options, function(opt) {
                    if (!opt.value) return;
                    var holder = _facAdvisers[opt.value];
                    var taken = !!holder && holder.faculty_id !== exceptFacultyId;
                    var name = opt.getAttribute('data-name') || opt.textContent;
                    opt.disabled = taken;
                    opt.textContent = taken ? name + ' (taken)' : name;
                });
            }

            function _facAdvisersForget(facultyId) {
                Object.keys(_facAdvisers).forEach(function(k) {
                    if (_facAdvisers[k].faculty_id === facultyId) delete _facAdvisers[k];
                });
                _facApplyOrgAvailability(document.getElementById('fac-org'), '');
            }

            /* Edit modal: open on row edit-button click */
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('#fac-list-table .fac-edit-btn');
                if (!btn) return;
                var row = btn.closest('tr');
                if (!row) return;

                _facEditRow = row;
                _clearFacEditAlert();

                var fullname = row.dataset.fullname || '';
                var email = row.dataset.email || '';
                var backupEmail = row.dataset.backupEmail || '';
                var facId = row.dataset.facultyId || '';
                var role = row.dataset.role || '';
                var org = row.dataset.org || '';
                var orgId = row.dataset.orgId || '';
                var init = row.dataset.init || (fullname.charAt(0).toUpperCase()) || 'F';
                var isAdviser = role === 'Organization Adviser';
                var idUnset = !facId || facId.indexOf('NOTSET-') === 0;
                var subLine = (idUnset ? 'Faculty ID not set yet' : facId) + ' \u00B7 ' + (isAdviser && org ? 'Org Adviser' : 'Active Faculty');

                var parts = fullname.trim().split(' ');
                var lastName = parts.length > 1 ? parts[parts.length - 1] : '';
                var firstName = parts.length > 1 ? parts.slice(0, -1).join(' ') : parts[0];

                document.getElementById('fac-edit-avatar').textContent = init;
                document.getElementById('fac-edit-name-display').textContent = fullname;
                document.getElementById('fac-edit-meta-display').textContent = subLine;
                document.getElementById('fac-edit-first').value = firstName;
                document.getElementById('fac-edit-last').value = lastName;
                document.getElementById('fac-edit-email').value = email;
                document.getElementById('fac-edit-backup').value = backupEmail;
                document.getElementById('fac-edit-adviser').checked = isAdviser;
                var orgSel = document.getElementById('fac-edit-org');
                _facApplyOrgAvailability(orgSel, facId);
                if (orgSel) orgSel.value = (isAdviser && orgId) ? orgId : '';
                document.getElementById('fac-delete-name').textContent = fullname;

                _syncFacEditOrgVisibility();
                openFacModal('fac-edit-modal');
            });

            /* Edit modal: close */
            ['fac-edit-close', 'fac-edit-cancel'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('click', function() {
                    closeFacModal('fac-edit-modal');
                });
            });

            /* ── Delete Account ──────────────────────────────────────────
               The confirm modal shows ONE state at a time:
                 checking -> confirm  (nothing blocking)
                          -> blocked  (open bookings: Super Admin can override
                                       by re-entering their own password) */
            var _facDelView = 'checking';
            var _facDelCheckSeq = 0;

            function _facDelEl(id) {
                return document.getElementById(id);
            }

            function _facDelClearError() {
                var box = _facDelEl('fac-delete-error');
                if (box) {
                    box.classList.add('hidden');
                    box.textContent = '';
                }
                /* the plain note comes back once an error is gone */
                var note = _facDelEl('fac-delete-view-confirm');
                if (note && _facDelView === 'confirm') note.classList.remove('hidden');
            }

            function _facDelShowError(msg) {
                var box = _facDelEl('fac-delete-error');
                if (!box) return;
                /* only one message at a time: an error replaces the plain note */
                var note = _facDelEl('fac-delete-view-confirm');
                if (note) note.classList.add('hidden');
                box.textContent = msg;
                box.classList.remove('hidden');
            }

            function _facDelSetView(view) {
                _facDelView = view;
                ['checking', 'confirm', 'blocked'].forEach(function(v) {
                    var el = _facDelEl('fac-delete-view-' + v);
                    if (el) el.classList.toggle('hidden', v !== view);
                });
                var btn = _facDelEl('fac-delete-confirm');
                var lbl = _facDelEl('fac-delete-confirm-label');
                if (btn) btn.disabled = (view === 'checking');
                if (lbl) lbl.textContent = (view === 'blocked') ? 'Delete Anyway' : 'Delete Account';
                var box = _facDelEl('fac-delete-error');
                if (box) {
                    box.classList.add('hidden');
                    box.textContent = '';
                }
                if (view === 'blocked') {
                    var pw = _facDelEl('fac-delete-password');
                    if (pw) {
                        pw.value = '';
                        setTimeout(function() {
                            pw.focus();
                        }, 50);
                    }
                }
            }

            function _facDelRenderBlocked(d) {
                var list = _facDelEl('fac-delete-list');
                if (!list) return;
                list.innerHTML = '';
                var rows = [
                    [d.waiting, 'pending request', 'pending requests', 'Will be declined'],
                    [d.out, 'item still borrowed', 'items still borrowed', 'Can still be returned'],
                    [d.rooms, 'upcoming facility booking', 'upcoming facility bookings', 'Will be cancelled']
                ];
                rows.forEach(function(r) {
                    var n = parseInt(r[0], 10) || 0;
                    if (n < 1) return;
                    var li = document.createElement('li');
                    var c = document.createElement('span');
                    c.className = 'fac-del-count';
                    c.textContent = n;
                    var l = document.createElement('span');
                    l.className = 'fac-del-label';
                    l.textContent = (n === 1) ? r[1] : r[2];
                    var o = document.createElement('span');
                    o.className = 'fac-del-outcome';
                    o.textContent = r[3];
                    li.appendChild(c);
                    li.appendChild(l);
                    li.appendChild(o);
                    list.appendChild(li);
                });
                _facDelSetView('blocked');
            }

            function _facCsrf() {
                var el = document.querySelector('input[name="csrf_token"]');
                return el ? el.value : '';
            }

            var facEditDelBtn = document.getElementById('fac-edit-delete-btn');
            if (facEditDelBtn) {
                facEditDelBtn.addEventListener('click', function() {
                    if (!_facEditRow) return;
                    var facultyId = _facEditRow.dataset.facultyId || '';
                    var nameEl = _facDelEl('fac-delete-name');
                    if (nameEl) nameEl.textContent = _facEditRow.dataset.fullname || 'this faculty member';

                    _facDelSetView('checking');
                    closeFacModal('fac-edit-modal');
                    openFacModal('fac-delete-modal');

                    /* Dry run: find out up front whether anything blocks the delete,
                       so the right message is the first (and only) one shown. */
                    var seq = ++_facDelCheckSeq;
                    fetch('equipment-booking/api/delete-faculty-account.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                csrf_token: _facCsrf(),
                                faculty_id: facultyId,
                                dry_run: '1'
                            }).toString()
                        })
                        .then(function(res) {
                            return res.json();
                        })
                        .then(function(data) {
                            if (seq !== _facDelCheckSeq) return;
                            if (data && data.status === 'success' && data.blocked && data.details) {
                                _facDelRenderBlocked(data.details);
                            } else {
                                /* clear, or the check itself failed: the real delete
                                   re-checks on the server and reports any problem */
                                _facDelSetView('confirm');
                            }
                        })
                        .catch(function() {
                            if (seq !== _facDelCheckSeq) return;
                            _facDelSetView('confirm');
                        });
                });
            }

            /* Keep the "Faculty List (N)" badge in step with the table, whether a
               row is added (create form) or removed (delete). */
            var _facTbody = document.getElementById('faculty-list-tbody');

            function _facRefreshCount() {
                var badge = document.getElementById('fac-count-badge');
                if (!badge || !_facTbody) return;
                badge.textContent = '(' + _facTbody.querySelectorAll('tr[data-faculty-id]').length + ')';
            }
            if (_facTbody && window.MutationObserver) {
                new MutationObserver(_facRefreshCount).observe(_facTbody, {
                    childList: true
                });
            }

            /* Refresh the faculty list tbody from the server fragment endpoint.
               Called after in-place account creation so the new row appears without
               a full page reload. Exposed globally so admin-dashboard.js can call it. */
            window.adminRefreshFacultyList = function() {
                fetch('admin-dashboard.php?fragment=faculty-tbody', {
                        credentials: 'same-origin'
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(json) {
                        if (json.status !== 'success') return;
                        var before = _facTbody ? Array.prototype.map.call(_facTbody.querySelectorAll('tr[data-faculty-id]'), function(r) {
                            return r.dataset.email;
                        }) : [];
                        if (_facTbody) _facTbody.innerHTML = json.faculty_html;
                        // Edit buttons are wired via delegated listener on #fac-list-table,
                        // so no per-row re-binding is needed after the innerHTML swap.
                        _facRefreshCount();
                        // The list is paged: show the page that holds the new account.
                        if (typeof window.psFacultyListRevealNew === 'function') window.psFacultyListRevealNew(before);
                    })
                    .catch(function() {
                        if (typeof showToast === 'function') showToast('Could not refresh the faculty list. Please reload the page.');
                    });
            };

            /* Save Changes */
            var facEditSaveBtn = document.getElementById('fac-edit-save');
            if (facEditSaveBtn) {
                facEditSaveBtn.addEventListener('click', function() {
                    _clearFacEditAlert();
                    if (!_facEditRow) return;

                    var facultyId = _facEditRow.dataset.facultyId || '';
                    var firstName = (document.getElementById('fac-edit-first').value || '').trim();
                    var lastName = (document.getElementById('fac-edit-last').value || '').trim();
                    var email = (document.getElementById('fac-edit-email').value || '').trim();
                    var backup = (document.getElementById('fac-edit-backup').value || '').trim();
                    var isAdviser = document.getElementById('fac-edit-adviser').checked ? '1' : '0';
                    var orgId = document.getElementById('fac-edit-org') ? document.getElementById('fac-edit-org').value : '';

                    if (!firstName) {
                        _showFacEditAlert('First name is required.', true);
                        return;
                    }
                    if (!lastName) {
                        _showFacEditAlert('Last name is required.', true);
                        return;
                    }
                    if (!email) {
                        _showFacEditAlert('PUPSync email is required.', true);
                        return;
                    }
                    if (isAdviser === '1' && !orgId) {
                        _showFacEditAlert('An organization must be selected for an adviser.', true);
                        return;
                    }
                    /* A new or changed email must end in @pupsync.edu (an unchanged older email is left alone). */
                    var originalEmail = (_facEditRow.dataset.email || '').toLowerCase();
                    if (email.toLowerCase() !== originalEmail && !/^[^\s@]+@pupsync\.edu$/i.test(email)) {
                        _showFacEditAlert('Use a PUPSync email ending in @pupsync.edu.', true);
                        return;
                    }
                    /* One adviser per organization (only when the organization is new for this person). */
                    var holder = (isAdviser === '1') ? _facAdvisers[orgId] : null;
                    var orgUnchanged = _facEditRow.dataset.role === 'Organization Adviser' &&
                        String(_facEditRow.dataset.orgId || '') === String(orgId);
                    if (holder && holder.faculty_id !== facultyId && !orgUnchanged) {
                        var heldOpt = document.getElementById('fac-edit-org').querySelector('option[value="' + orgId + '"]');
                        _showFacEditAlert(((heldOpt && heldOpt.getAttribute('data-name')) || 'That organization') +
                            ' already has an adviser: ' + holder.name + '.', true);
                        return;
                    }

                    var savedLabel = facEditSaveBtn.innerHTML;
                    facEditSaveBtn.disabled = true;
                    facEditSaveBtn.innerHTML = '<span class="material-symbols-outlined" style="animation:spin 0.8s linear infinite">progress_activity</span> Saving...';

                    var csrfEl = document.querySelector('input[name="csrf_token"]');
                    var body = new URLSearchParams({
                        csrf_token: csrfEl ? csrfEl.value : '',
                        faculty_id: facultyId,
                        pupsync_email: email,
                        backup_email: backup,
                        first_name: firstName,
                        last_name: lastName,
                        is_org_adviser: isAdviser,
                        organization_id: orgId
                    });

                    fetch('equipment-booking/api/update-faculty-account.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: body.toString()
                        })
                        .then(function(res) {
                            return res.json();
                        })
                        .then(function(data) {
                            if (data.status === 'success') {
                                var fullname = [firstName, lastName].join(' ').trim();
                                var role = isAdviser === '1' ? 'Organization Adviser' : 'Regular Faculty';
                                var orgSelEl = document.getElementById('fac-edit-org');
                                var selOpt = orgSelEl ? orgSelEl.options[orgSelEl.selectedIndex] : null;
                                var orgName = (isAdviser === '1' && selOpt) ?
                                    (selOpt.getAttribute('data-name') || selOpt.text || '') :
                                    '';
                                var orgNameSafe = (orgId && isAdviser === '1') ? orgName : '';

                                // Keep the "who advises what" map in step with this save
                                _facAdvisersForget(facultyId);
                                if (isAdviser === '1' && orgId) {
                                    _facAdvisers[orgId] = {
                                        faculty_id: facultyId,
                                        name: fullname
                                    };
                                    _facApplyOrgAvailability(document.getElementById('fac-org'), '');
                                }

                                // Re-render the row (name, email, role, organization) from the saved values
                                if (typeof window.psFacRender === 'function') {
                                    window.psFacRender(_facEditRow, {
                                        fullname: fullname,
                                        email: email,
                                        backup_email: backup,
                                        role: role,
                                        org_name: orgNameSafe,
                                        org_id: isAdviser === '1' ? orgId : '0',
                                        faculty_id: facultyId
                                    });
                                }

                                closeFacModal('fac-edit-modal');
                                if (typeof showToast === 'function') showToast('Faculty account updated successfully.');
                            } else {
                                _showFacEditAlert(data.message || 'Could not save changes.', true);
                            }
                        })
                        .catch(function() {
                            _showFacEditAlert('Network error. Please try again.', true);
                        })
                        .finally(function() {
                            facEditSaveBtn.disabled = false;
                            facEditSaveBtn.innerHTML = savedLabel;
                        });
                });
            }

            /* Delete modal: close */
            ['fac-delete-close', 'fac-delete-cancel'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('click', function() {
                    closeFacModal('fac-delete-modal');
                });
            });

            /* Delete modal: confirm */
            var facDelConf = document.getElementById('fac-delete-confirm');
            var facDelPw = document.getElementById('fac-delete-password');

            function _facDelSubmit() {
                if (!_facEditRow || _facDelView === 'checking') return;

                var override = (_facDelView === 'blocked');
                var password = facDelPw ? facDelPw.value : '';
                if (override && !password) {
                    _facDelShowError('Enter your password to continue.');
                    if (facDelPw) facDelPw.focus();
                    return;
                }
                _facDelClearError();

                var rowToRemove = _facEditRow;
                var params = {
                    csrf_token: _facCsrf(),
                    faculty_id: rowToRemove.dataset.facultyId || ''
                };
                if (override) {
                    params.override = '1';
                    params.current_password = password;
                }

                var savedLabel = facDelConf.innerHTML;
                facDelConf.disabled = true;
                facDelConf.innerHTML = '<span class="material-symbols-outlined" style="animation:spin 0.8s linear infinite">progress_activity</span> Deleting...';

                fetch('equipment-booking/api/delete-faculty-account.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams(params).toString()
                    })
                    .then(function(res) {
                        return res.json().catch(function() {
                            return {
                                status: 'error',
                                message: 'Unexpected response from the server.'
                            };
                        });
                    })
                    .then(function(data) {
                        if (data.status === 'success') {
                            rowToRemove.remove();
                            _facEditRow = null;
                            _facAdvisersForget(params.faculty_id);

                            /* Table is now empty -> show the empty state again */
                            if (_facTbody && !_facTbody.querySelector('tr[data-faculty-id]')) {
                                var tr = document.createElement('tr');
                                tr.id = 'fac-empty-row';
                                tr.innerHTML = '<td colspan="4" style="text-align:center;padding:3rem;color:var(--text-light)">' +
                                    '<span class="material-symbols-outlined" style="font-size:40px;display:block;margin:0 auto 10px;opacity:0.3">group</span>' +
                                    'No faculty accounts yet.</td>';
                                _facTbody.appendChild(tr);
                            }

                            closeFacModal('fac-delete-modal');
                            if (typeof showToast === 'function') showToast('Faculty account deleted.', 't-danger');
                        } else if (data.code === 'blocked' && data.details) {
                            /* bookings appeared since the check: switch to the override view */
                            _facDelRenderBlocked(data.details);
                        } else if (data.code === 'bad_password') {
                            _facDelShowError(data.message || 'Incorrect password.');
                            if (facDelPw) {
                                facDelPw.focus();
                                facDelPw.select();
                            }
                        } else {
                            _facDelShowError(data.message || data.error || 'Could not delete this account.');
                        }
                    })
                    .catch(function() {
                        _facDelShowError('Network error. Please try again.');
                    })
                    .finally(function() {
                        facDelConf.innerHTML = savedLabel;
                        facDelConf.disabled = (_facDelView === 'checking');
                    });
            }

            if (facDelConf) facDelConf.addEventListener('click', _facDelSubmit);
            if (facDelPw) {
                facDelPw.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        _facDelSubmit();
                    }
                });
                facDelPw.addEventListener('input', _facDelClearError);
            }

            /* Backdrop click to close */
            ['fac-edit-modal', 'fac-delete-modal'].forEach(function(id) {
                var bd = document.getElementById(id);
                if (bd) bd.addEventListener('click', function(e) {
                    if (e.target === bd) closeFacModal(id);
                });
            });

        }());
    </script>

    <!-- ── Inventory AJAX: intercept add/edit/archive/restore/force_delete without page reload ── -->
    <script nonce="<?php echo $csp_nonce; ?>">
        (function() {
            'use strict';

            /* ── Refresh inventory tbodies from the fragment endpoint ─────────── */
            function refreshInventoryTbody(onDone) {
                fetch('admin-dashboard.php?fragment=inventory-tbody', {
                        credentials: 'same-origin'
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(json) {
                        if (json.status !== 'success') return;

                        var activeBody = document.getElementById('inventory-body');
                        if (activeBody) activeBody.innerHTML = json.active_html;

                        var archBody = document.getElementById('inv-archive-body');
                        if (archBody) archBody.innerHTML = json.archive_html;

                        var badge = document.getElementById('inv-count-badge');
                        if (badge) badge.textContent = '(' + json.active_count + ')';

                        // Re-wire pagination/search since rows are brand new DOM nodes
                        if (typeof window._setupInventoryListFn === 'function') {
                            window._setupInventoryListFn();
                        }

                        if (typeof window.adminDoPoll === 'function') window.adminDoPoll();

                        if (typeof onDone === 'function') onDone();
                    })
                    .catch(function() {
                        if (typeof showToast === 'function') showToast('Could not refresh inventory. Please reload.');
                    });
            }

            /* ── Intercept itemForm (Add Equipment) ───────────────────────────── */
            var itemForm = document.getElementById('itemForm');
            if (itemForm) {
                itemForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var fd = new FormData(itemForm);
                    fd.set('ajax', '1');

                    var submitBtn = itemForm.querySelector('[name="add_item"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Saving\u2026';
                    }

                    fetch('admin-dashboard.php', {
                            method: 'POST',
                            body: fd,
                            credentials: 'same-origin'
                        })
                        .then(function(r) {
                            return r.json();
                        })
                        .then(function(json) {
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.textContent = 'Add Equipment';
                            }
                            if (json.status === 'success') {
                                if (typeof showToast === 'function') showToast(json.message || 'Saved.');
                                itemForm.reset();
                                var preview = document.getElementById('imagePreview');
                                if (preview) {
                                    preview.src = '';
                                    preview.style.display = 'none';
                                }
                                if (typeof window._exitEditModeFn === 'function') window._exitEditModeFn();
                                var fw = document.getElementById('item-form-wrap');
                                if (fw) fw.classList.add('hidden');
                                refreshInventoryTbody();
                            } else {
                                if (typeof showToast === 'function') showToast(json.message || 'Error saving equipment.');
                            }
                        })
                        .catch(function() {
                            if (submitBtn) submitBtn.disabled = false;
                            if (typeof showToast === 'function') showToast('Network error. Please try again.');
                        });
                });
            }

            /* ── Intercept eqEditForm (Edit Equipment modal) ──────────────────── */
            var eqEditForm = document.getElementById('eqEditForm');
            if (eqEditForm) {
                eqEditForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var fd = new FormData(eqEditForm);
                    fd.set('update_item', '1');
                    fd.set('ajax', '1');

                    var submitBtn = document.querySelector('button[form="eqEditForm"][name="update_item"]');
                    if (submitBtn) submitBtn.disabled = true;

                    fetch('admin-dashboard.php', {
                            method: 'POST',
                            body: fd,
                            credentials: 'same-origin'
                        })
                        .then(function(r) {
                            return r.json();
                        })
                        .then(function(json) {
                            if (submitBtn) submitBtn.disabled = false;
                            if (json.status === 'success') {
                                if (typeof psCloseModal === 'function') psCloseModal('eq-edit-modal');
                                if (typeof showToast === 'function') showToast(json.message || 'Equipment updated.');
                                refreshInventoryTbody();
                            } else {
                                if (typeof showToast === 'function') showToast(json.message || 'Could not update equipment.');
                            }
                        })
                        .catch(function() {
                            if (submitBtn) submitBtn.disabled = false;
                            if (typeof showToast === 'function') showToast('Network error. Please try again.');
                        });
                });
            }

            /* ── Intercept archive (delete_item) links ────────────────────────── */
            document.addEventListener('click', function(e) {
                var link = e.target.closest('a[href*="delete_item="]');
                if (!link) return;
                e.preventDefault();
                var msg = 'Archive this item? It will no longer be available for lending, but borrow history is preserved.';
                if (!confirm(msg)) return;

                var href = link.getAttribute('href');
                var url = href + (href.indexOf('?') !== -1 ? '&' : '?') + 'ajax=1';
                fetch(url, {
                        credentials: 'same-origin'
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(json) {
                        if (json.status === 'success') {
                            if (typeof psCloseModal === 'function') psCloseModal('eq-edit-modal');
                            if (typeof showToast === 'function') showToast(json.message || 'Item archived.');
                            refreshInventoryTbody();
                        } else {
                            if (typeof showToast === 'function') showToast(json.message || 'Could not archive item.');
                        }
                    })
                    .catch(function() {
                        if (typeof showToast === 'function') showToast('Network error. Please try again.');
                    });
            });

            /* ── Intercept restore_item links ─────────────────────────────────── */
            document.addEventListener('click', function(e) {
                var link = e.target.closest('a[href*="restore_item="]');
                if (!link) return;
                e.preventDefault();
                var href = link.getAttribute('href');
                var url = href + (href.indexOf('?') !== -1 ? '&' : '?') + 'ajax=1';
                fetch(url, {
                        credentials: 'same-origin'
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(json) {
                        if (json.status === 'success') {
                            if (typeof showToast === 'function') showToast(json.message || 'Item restored.');
                            refreshInventoryTbody();
                        } else {
                            if (typeof showToast === 'function') showToast(json.message || 'Could not restore item.');
                        }
                    })
                    .catch(function() {
                        if (typeof showToast === 'function') showToast('Network error. Please try again.');
                    });
            });

            /* ── Intercept force_delete links ─────────────────────────────────── */
            document.addEventListener('click', function(e) {
                var link = e.target.closest('a[href*="force_delete="]');
                if (!link) return;
                e.preventDefault();
                if (!confirm('Permanently delete? This cannot be undone.')) return;
                var href = link.getAttribute('href');
                var url = href + (href.indexOf('?') !== -1 ? '&' : '?') + 'ajax=1';
                fetch(url, {
                        credentials: 'same-origin'
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(json) {
                        if (json.status === 'success') {
                            if (typeof showToast === 'function') showToast(json.message || 'Item permanently deleted.');
                            refreshInventoryTbody();
                        } else {
                            if (typeof showToast === 'function') showToast(json.message || 'Could not delete item.');
                        }
                    })
                    .catch(function() {
                        if (typeof showToast === 'function') showToast('Network error. Please try again.');
                    });
            });

        })();
    </script>
    <script>
        /* Activate the restored panel before revealing the content area. */
        (function() {
            var tab = window._preJumpTab;
            if (tab) {
                var target = document.getElementById('panel-' + tab);
                if (target) {
                    var current = document.querySelector('.tab-panel.active');
                    if (current) current.classList.remove('active');
                    target.classList.add('active');

                    document.querySelectorAll('.nav-item[data-tab]').forEach(function(item) {
                        item.classList.toggle('active', item.dataset.tab === tab);
                    });
                    var accountToggle = document.getElementById('navAccountToggle');
                    if (accountToggle) accountToggle.classList.toggle('active', tab === 'settings');
                }
            }
            document.documentElement.classList.remove('admin-tab-pending');
        })();
    </script>
</body>

</html>