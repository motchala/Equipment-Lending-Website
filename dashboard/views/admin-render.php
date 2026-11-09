<?php

/** Echo the dashboard body. */
function dash_render(array $d): void
{
    $attn   = dash_attention($d);
    $alerts = $d['inv_out'] + $d['inv_low'];
    $tomorrow = date('Y-m-d', strtotime('+1 day', strtotime($d['today'])));

    // Overview strip: two groups inside ONE card (replaces the six separate stat cards).
    $kpi_groups = ['loans' => 'Equipment loans', 'places' => 'Facilities & stock'];

    $kpis = [
        [
            'tone' => $d['waiting'] > 0 ? 'warn' : 'ok',
            'icon' => 'assignment',
            'label' => 'Pending requests',
            'group' => 'loans',
            'val' => $d['waiting'],
            'sub' => $d['waiting'] > 0 ? 'Awaiting a decision' : 'Queue is clear',
            'go' => 'inventory',
            'chip' => '',
            'loan_filter' => 'waiting',
        ],
        [
            'tone' => $d['pickup'] > 0 ? 'warn' : 'ok',
            'icon' => 'move_to_inbox',
            'label' => 'Ready for pickup',
            'group' => 'loans',
            'val' => $d['pickup'],
            'sub' => $d['pickup'] > 0 ? 'Awaiting claim scan' : 'Nothing to hand over',
            'go' => 'inventory',
            'chip' => '',
            'loan_filter' => 'pickup',
        ],
        [
            'tone' => 'ok',
            'icon' => 'swap_horiz',
            'label' => 'Active loans',
            'group' => 'loans',
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
            'group' => 'loans',
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
            'group' => 'places',
            'val' => $d['rooms_today'],
            'sub' => $d['rooms_bookable'] > 0
                ? $d['rooms_in_use'] . ' of ' . $d['rooms_bookable'] . ' in use'
                : 'No bookable facilities yet',
            'go' => 'rooms',
            'rooms_tab' => 'rooms-reservations',
            'chip' => '',
        ],
        [
            'tone' => $d['inv_out'] > 0 ? 'danger' : ($alerts > 0 ? 'warn' : 'ok'),
            'icon' => 'inventory_2',
            'label' => 'Stock alerts',
            'group' => 'places',
            'val' => $alerts,
            'sub' => $alerts > 0
                ? $d['inv_out'] . ' out · ' . $d['inv_low'] . ' low'
                : 'All ' . $d['inv_total'] . ' items stocked',
            'go' => 'inventory',
            'chip' => '',
        ],
    ];
?>
    <!-- ── Overview: ONE card, two groups ─────────────────────────────
         Keeps the .dash-kpis class: admin-dashboard.js checks for it before swapping a live refresh in.
         Every stat is still a button with data-dash-go / data-dash-loan-filter, so the delegated click
         handler in admin-dashboard.js needs no change. -->
    <section class="dash-kpis dash-overview" aria-label="Key figures">
        <?php foreach ($kpi_groups as $gk => $gl): ?>
            <div class="dash-ov-group dash-ov-group--<?php echo dash_h($gk); ?>" role="group" aria-label="<?php echo dash_h($gl); ?>">
                <h2 class="dash-ov-title"><?php echo dash_h($gl); ?></h2>
                <div class="dash-ov-stats">
                    <?php foreach ($kpis as $k): if (($k['group'] ?? 'loans') !== $gk) continue; ?>
                        <button type="button" class="dash-kpi dash-tone-<?php echo dash_h($k['tone']); ?>"
                            data-dash-go="<?php echo dash_h($k['go']); ?>"
                            data-dash-loan-filter="<?php echo dash_h($k['loan_filter'] ?? ''); ?>"
                            <?php if (!empty($k['rooms_tab'])): ?>data-dash-rooms-tab="<?php echo dash_h($k['rooms_tab']); ?>"<?php endif; ?>>
                            <span class="dash-kpi-label"><span class="dash-kpi-dot" aria-hidden="true"></span><?php echo dash_h($k['label']); ?></span>
                            <span class="dash-kpi-val"><?php echo (int)$k['val']; ?></span>
                            <span class="dash-kpi-sub"><?php echo dash_h($k['sub']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
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
                                data-dash-loan-filter="<?php echo dash_h($a['loan_filter'] ?? ''); ?>"
                                <?php if (!empty($a['rooms_tab'])): ?>data-dash-rooms-tab="<?php echo dash_h($a['rooms_tab']); ?>"<?php endif; ?>>
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
