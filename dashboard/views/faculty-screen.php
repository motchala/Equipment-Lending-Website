            <!-- ============================================================
         TAB: DASHBOARD (HOME)  — Redesigned v3
    ============================================================ -->
            <div class="tab-panel active" id="panel-home">
                <?php
                // ── Faculty Dashboard ──────────────────────────────────────────────────
                // Built from this faculty member's own loans ($requests_js), room reservations
                // ($room_reservations) and notification count, all already loaded by this page.
                $fdb_h        = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
                // Current semester (set by the Super Admin) -> small tag next to the date. Name only, no dates.
                require_once __DIR__ . '/../../shared/core/semester-label.php';
                $fdb_semester = ps_semester_label($conn);
                $fdb_pl       = fn(int $n, string $w) => $n . ' ' . $w . ($n === 1 ? '' : 's');
                $fdb_today    = date('Y-m-d');
                $fdb_now_hms  = date('H:i:s');
                $fdb_t12      = fn(?string $t) => $t ? date('g:i A', strtotime($t)) : '';
                $fdb_ago      = function (?string $dt): string {
                    $t = $dt ? strtotime($dt) : 0;
                    if (!$t) return '';
                    $d = time() - $t;
                    if ($d < 60)     return 'Just now';
                    if ($d < 3600)   return floor($d / 60) . ' min ago';
                    if ($d < 86400)  return floor($d / 3600) . ' hr ago';
                    if ($d < 604800) {
                        $n = (int)floor($d / 86400);
                        return $n . ' day' . ($n === 1 ? '' : 's') . ' ago';
                    }
                    return date('M j', $t);
                };

                // Loans + rooms: counts for the cards, today's schedule
                $fdb_due_today = 0;
                $fdb_rooms_n   = 0;
                $fdb_sched     = [];
                foreach ($requests_js as $fdb_v_r) {
                    $fdb_v_st = trim((string)($fdb_v_r['status'] ?? ''));
                    if (!in_array($fdb_v_st, ['Approved', 'On Loan'], true) || substr((string)$fdb_v_r['return_date'], 0, 10) !== $fdb_today) continue;
                    $fdb_due_today++;
                    $fdb_v_qty = max(1, (int)($fdb_v_r['borrow_qty'] ?? 1));
                    $fdb_sched[] = [
                        'sort'  => !empty($fdb_v_r['return_time']) ? $fdb_v_r['return_time'] : '99:99:99',
                        'time'  => !empty($fdb_v_r['return_time']) ? $fdb_t12($fdb_v_r['return_time']) : 'Due',
                        'icon'  => 'inventory_2',
                        'title' => $fdb_v_r['equipment_name'] . ($fdb_v_qty > 1 ? ' ×' . $fdb_v_qty : ''),
                        'meta'  => 'Return',
                        'past'  => false,
                    ];
                }
                foreach ($room_reservations as $fdb_v_rr) {
                    if (($fdb_v_rr['status'] ?? '') !== 'Approved') continue;
                    $fdb_v_d = (string)$fdb_v_rr['reservation_date'];
                    $fdb_v_over = ($fdb_v_d === $fdb_today && (string)$fdb_v_rr['end_time'] < $fdb_now_hms);
                    if ($fdb_v_d > $fdb_today || ($fdb_v_d === $fdb_today && !$fdb_v_over)) $fdb_rooms_n++;
                    if ($fdb_v_d !== $fdb_today) continue;
                    $fdb_sched[] = [
                        'sort'  => $fdb_v_rr['start_time'],
                        'time'  => $fdb_t12($fdb_v_rr['start_time']),
                        'icon'  => 'meeting_room',
                        'title' => $fdb_v_rr['room_name'],
                        'meta'  => 'Until ' . $fdb_t12($fdb_v_rr['end_time']),
                        'past'  => $fdb_v_over,
                    ];
                }
                usort($fdb_sched, fn($a, $b) => strcmp($a['sort'], $b['sort']));
                $fdb_sched = array_slice($fdb_sched, 0, 5);

                // Chart data: borrows (equipment requests) and room reservations, counted on the day
                // they are for (borrow date / reservation date). Declined and cancelled ones don't count.
                $fdb_by_day = [];
                foreach ($requests_js as $fdb_v_r) {
                    if (in_array(trim((string)($fdb_v_r['status'] ?? '')), ['Declined', 'Cancelled'], true)) continue;
                    $fdb_v_d = substr((string)($fdb_v_r['borrow_date'] ?? ''), 0, 10);
                    if ($fdb_v_d === '') continue;
                    $fdb_by_day[$fdb_v_d]['eq'] = ($fdb_by_day[$fdb_v_d]['eq'] ?? 0) + 1;
                }
                foreach ($room_reservations as $fdb_v_rr) {
                    if (in_array((string)($fdb_v_rr['status'] ?? ''), ['Declined', 'Cancelled'], true)) continue;
                    $fdb_v_d = substr((string)($fdb_v_rr['reservation_date'] ?? ''), 0, 10);
                    if ($fdb_v_d === '') continue;
                    $fdb_by_day[$fdb_v_d]['rm'] = ($fdb_by_day[$fdb_v_d]['rm'] ?? 0) + 1;
                }
                // Months view: last 6 months (this month included)
                $fdb_months = [];
                for ($fdb_v_i = 5; $fdb_v_i >= 0; $fdb_v_i--) {
                    $fdb_v_ts = strtotime(date('Y-m-01') . " -$fdb_v_i months");
                    $fdb_months[date('Y-m', $fdb_v_ts)] = [
                        'label' => date('M', $fdb_v_ts),
                        'sub' => '',
                        'long' => date('F Y', $fdb_v_ts),
                        'now' => date('Y-m', $fdb_v_ts) === date('Y-m'),
                        'eq' => 0,
                        'rm' => 0
                    ];
                }
                foreach ($fdb_by_day as $fdb_v_d => $fdb_v_c) {
                    $fdb_v_k = substr($fdb_v_d, 0, 7);
                    if (!isset($fdb_months[$fdb_v_k])) continue;
                    $fdb_months[$fdb_v_k]['eq'] += $fdb_v_c['eq'] ?? 0;
                    $fdb_months[$fdb_v_k]['rm'] += $fdb_v_c['rm'] ?? 0;
                }
                // Week view: Monday to Sunday of this week
                $fdb_week = [];
                $fdb_mon  = strtotime('-' . ((int)date('N') - 1) . ' days', strtotime($fdb_today));
                for ($fdb_v_i = 0; $fdb_v_i < 7; $fdb_v_i++) {
                    $fdb_v_ts = strtotime("+$fdb_v_i days", $fdb_mon);
                    $fdb_v_d  = date('Y-m-d', $fdb_v_ts);
                    $fdb_week[] = [
                        'label' => date('D', $fdb_v_ts),
                        'sub' => date('j', $fdb_v_ts),
                        'long' => date('l, F j', $fdb_v_ts),
                        'now' => $fdb_v_d === $fdb_today,
                        'eq' => (int)($fdb_by_day[$fdb_v_d]['eq'] ?? 0),
                        'rm' => (int)($fdb_by_day[$fdb_v_d]['rm'] ?? 0)
                    ];
                }
                $fdb_has_chart = array_sum(array_column($fdb_months, 'eq')) + array_sum(array_column($fdb_months, 'rm'))
                    + array_sum(array_column($fdb_week, 'eq')) + array_sum(array_column($fdb_week, 'rm')) > 0;

                // Vertical axis: whole numbers only, 2 or 4 even steps up to the biggest value
                $fdb_axis = function (array $rows): array {
                    $max = max(1, max(array_merge(array_column($rows, 'eq'), array_column($rows, 'rm'))));
                    if ($max <= 2) return [1, 2];
                    $step = (int)ceil($max / 4);
                    foreach ([1, 2, 5, 10, 20, 25, 50, 100, 200, 500, 1000] as $nice) {
                        if ($nice >= $step) {
                            $step = $nice;
                            break;
                        }
                    }
                    return [$step, 4];
                };
                // One chart (numbered vertical axis + two bars per column + hover card)
                $fdb_chart = function (array $rows, string $label) use ($fdb_axis, $fdb_h): string {
                    [$step, $n] = $fdb_axis($rows);
                    $top = $step * $n;
                    ob_start(); ?>
                    <div class="fdb-plot">
                        <div class="fdb-yaxis" aria-hidden="true">
                            <?php for ($i = 0; $i <= $n; $i++): ?><span style="bottom:<?php echo round($i / $n * 100, 2); ?>%"><?php echo $i * $step; ?></span><?php endfor; ?>
                        </div>
                        <div class="fdb-plotarea">
                            <div class="fdb-lines" aria-hidden="true">
                                <?php for ($i = 0; $i <= $n; $i++): ?><i style="bottom:<?php echo round($i / $n * 100, 2); ?>%"></i><?php endfor; ?>
                            </div>
                            <div class="fdb-chart" role="group" aria-label="<?php echo $fdb_h($label); ?>" style="grid-template-columns:repeat(<?php echo count($rows); ?>,minmax(0,1fr))">
                                <?php foreach ($rows as $c): ?>
                                    <div class="fdb-chart-col<?php echo $c['now'] ? ' is-now' : ''; ?>" tabindex="0"
                                        aria-label="<?php echo $fdb_h($c['long']); ?> - Borrows: <?php echo (int)$c['eq']; ?>, Reservations: <?php echo (int)$c['rm']; ?>">
                                        <div class="fdb-tip" role="presentation">
                                            <b><?php echo $fdb_h($c['long']); ?></b>
                                            <span><span><i class="fdb-dot fdb-dot--eq"></i>Borrows</span><b><?php echo (int)$c['eq']; ?></b></span>
                                            <span><span><i class="fdb-dot fdb-dot--rm"></i>Reservations</span><b><?php echo (int)$c['rm']; ?></b></span>
                                        </div>
                                        <div class="fdb-chart-bars">
                                            <span class="fdb-bar fdb-bar--eq<?php echo $c['eq'] ? '' : ' is-zero'; ?>" style="height:<?php echo $c['eq'] ? max(3, round($c['eq'] / $top * 100, 1)) : 0; ?>%"></span>
                                            <span class="fdb-bar fdb-bar--rm<?php echo $c['rm'] ? '' : ' is-zero'; ?>" style="height:<?php echo $c['rm'] ? max(3, round($c['rm'] / $top * 100, 1)) : 0; ?>%"></span>
                                        </div>
                                        <span class="fdb-chart-x"><?php echo $fdb_h($c['label']); ?><?php if ($c['sub'] !== '') echo '<small>' . $fdb_h($c['sub']) . '</small>'; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php return ob_get_clean();
                };

                // Recent activity (requests + reservations, newest first)
                $fdb_recent = [];
                $fdb_req_map = [
                    'Waiting' => ['Pending', 'warn'],
                    'Approved' => ['Approved', 'ok'],
                    'On Loan' => ['On loan', 'ok'],
                    'Overdue' => ['Overdue', 'danger'],
                    'Returned' => ['Returned', 'muted'],
                    'Cancelled' => ['Cancelled', 'muted'],
                    'Declined' => ['Declined', 'danger']
                ];
                foreach ($requests_js as $fdb_v_r) {
                    $fdb_v_st = trim((string)($fdb_v_r['status'] ?? ''));
                    [$fdb_v_lbl, $fdb_v_tone] = $fdb_req_map[$fdb_v_st] ?? [$fdb_v_st !== '' ? $fdb_v_st : 'Request', 'muted'];
                    $fdb_v_qty = max(1, (int)($fdb_v_r['borrow_qty'] ?? 1));
                    $fdb_recent[] = [
                        'ts' => strtotime((string)$fdb_v_r['request_date']),
                        'dt' => $fdb_v_r['request_date'],
                        'icon' => 'inventory_2',
                        'title' => $fdb_v_r['equipment_name'] . ($fdb_v_qty > 1 ? ' ×' . $fdb_v_qty : ''),
                        'lbl' => $fdb_v_lbl,
                        'tone' => $fdb_v_tone
                    ];
                }
                $fdb_room_map = ['Approved' => ['Reserved', 'info'], 'Cancelled' => ['Cancelled', 'muted'], 'Declined' => ['Declined', 'danger']];
                foreach ($room_reservations as $fdb_v_rr) {
                    $fdb_v_st = (string)($fdb_v_rr['status'] ?? '');
                    [$fdb_v_lbl, $fdb_v_tone] = $fdb_room_map[$fdb_v_st] ?? [$fdb_v_st, 'muted'];
                    $fdb_recent[] = [
                        'ts' => strtotime((string)$fdb_v_rr['request_date']),
                        'dt' => $fdb_v_rr['request_date'],
                        'icon' => 'meeting_room',
                        'title' => $fdb_v_rr['room_name'],
                        'lbl' => $fdb_v_lbl,
                        'tone' => $fdb_v_tone
                    ];
                }
                usort($fdb_recent, fn($a, $b) => $b['ts'] <=> $a['ts']);
                $fdb_recent = array_slice($fdb_recent, 0, 5);

                // Needs your attention (one short line each)
                $fdb_attn = [];
                if ($stat_overdue > 0)  $fdb_attn[] = ['tone' => 'danger', 'icon' => 'schedule',        'act' => 'activity', 'title' => $fdb_pl((int)$stat_overdue, 'overdue return')];
                if ($fdb_due_today > 0) $fdb_attn[] = ['tone' => 'warn',   'icon' => 'event_available', 'act' => 'activity', 'title' => $fdb_due_today . ' due back today'];
                if ($stat_waiting > 0)  $fdb_attn[] = ['tone' => 'warn',   'icon' => 'hourglass_top',   'act' => 'activity', 'title' => $fdb_pl((int)$stat_waiting, 'pending request')];
                if ((int)$notif_count > 0) $fdb_attn[] = ['tone' => 'info', 'icon' => 'notifications',  'act' => 'notif',    'title' => (int)$notif_count . ' unread'];

                $fdb_kpis = [
                    ['tone' => $stat_approved > 0 ? 'warn' : 'ok',  'icon' => 'move_to_inbox',  'label' => 'Ready for pickup', 'val' => (int)$stat_approved, 'stat' => 'approved', 'id' => ''],
                    ['tone' => 'ok',                                 'icon' => 'swap_horiz',     'label' => 'Active loans',     'val' => (int)$stat_on_loan,  'stat' => 'on_loan',  'id' => ''],
                    ['tone' => $stat_overdue > 0 ? 'danger' : 'ok',  'icon' => 'schedule',       'label' => 'Overdue',          'val' => (int)$stat_overdue,  'stat' => 'overdue',  'id' => 'statOverdueVal'],
                    ['tone' => $stat_waiting > 0 ? 'warn' : 'ok',    'icon' => 'hourglass_top',  'label' => 'Pending',          'val' => (int)$stat_waiting,  'stat' => 'waiting',  'id' => ''],
                    ['tone' => 'info',                               'icon' => 'meeting_room',   'label' => 'Reservations',     'val' => $fdb_rooms_n,        'stat' => '',         'id' => ''],
                ];
                ?>

                <div class="fdb" id="fdb">

                    <!-- Header: greeting, date, quick actions, bell + avatar -->
                    <div class="dash-flat-header fdb-head">
                        <div class="dash-flat-titles">
                            <h1 class="dash-flat-title">Good <?php
                                                                $fdb_v_h = (int)date('H');
                                                                echo $fdb_v_h < 12 ? 'morning' : ($fdb_v_h < 17 ? 'afternoon' : 'evening');
                                                                ?>, <span data-first-name><?php echo htmlspecialchars($firstname); ?></span>.</h1>
                            <p class="dash-flat-sub"><?php echo date('l, F j, Y'); ?>
                                <span class="ps-semester-tag<?php echo $fdb_semester['active'] ? '' : ' is-none'; ?>" id="psSemesterTag"><?php echo $fdb_h($fdb_semester['label']); ?></span>
                            </p>
                        </div>
                        <div class="fdb-head-tools">
                            <div class="fdb-actions">
                                <button type="button" class="fdb-btn fdb-btn--primary" data-action="go-tab" data-tab="lending" data-lending="browse">
                                    <span class="material-symbols-outlined" aria-hidden="true">add_shopping_cart</span>Borrow
                                </button>
                                <button type="button" class="fdb-btn" data-action="go-tab" data-tab="rooms">
                                    <span class="material-symbols-outlined" aria-hidden="true">meeting_room</span>Reserve a room
                                </button>
                            </div>
                            <!-- Dashboard-only shortcuts (same as the admin Dashboard). They live inside #panel-home, so
                             they only show on this tab. The badge keeps id="notifBadge": the notification code in
                             faculty-dashboard.js updates it. The avatar has .avatar-btn so name / photo changes reach it. -->
                            <div class="fd-dash-top-actions">
                                <button type="button" class="fd-top-btn" id="dashNotifBtn" data-action="open-notif-modal"
                                    aria-label="Open notifications<?php echo $notif_count > 0 ? ' — ' . $notif_count . ' unread' : ''; ?>"
                                    title="Notifications">
                                    <span class="material-symbols-outlined">notifications</span>
                                    <b class="fd-top-badge" id="notifBadge"
                                        <?php if ($notif_count <= 0) echo 'hidden'; ?>><?php echo $notif_count; ?></b>
                                </button>
                                <button type="button" class="avatar-btn fd-top-avatar" id="dashAvatarBtn"
                                    data-action="open-overlay" data-target="settingsOverlay"
                                    aria-label="Open my account" title="My account"><?php if ($profile_pic_url): ?><img src="<?php echo htmlspecialchars($profile_pic_url); ?>" alt="Profile" class="avatar-img" onerror="this.style.display='none'; this.nextElementSibling.style.removeProperty('display');"><span class="avatar-initials-fallback" style="display:none;"><?php echo htmlspecialchars($initials); ?></span><?php else: ?><?php echo htmlspecialchars($initials); ?><?php endif; ?></button>
                            </div>
                        </div>
                    </div>

                    <!-- Key figures -->
                    <section class="fdb-kpis" id="dashStats" aria-label="Key figures">
                        <?php foreach ($fdb_kpis as $fdb_v_k): ?>
                            <button type="button" class="fdb-kpi fdb-tone-<?php echo $fdb_h($fdb_v_k['tone']); ?>"
                                <?php if ($fdb_v_k['stat'] !== '') echo 'data-stat="' . $fdb_h($fdb_v_k['stat']) . '"'; ?>
                                data-action="go-tab" data-tab="activity">
                                <span class="fdb-kpi-icon" aria-hidden="true"><span class="material-symbols-outlined"><?php echo $fdb_h($fdb_v_k['icon']); ?></span></span>
                                <span class="fdb-kpi-body">
                                    <span class="fdb-kpi-val" <?php if ($fdb_v_k['id'] !== '') echo ' id="' . $fdb_h($fdb_v_k['id']) . '"'; ?>><?php echo (int)$fdb_v_k['val']; ?></span>
                                    <span class="fdb-kpi-label"><?php echo $fdb_h($fdb_v_k['label']); ?></span>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </section>

                    <div class="fdb-grid">

                        <!-- My activity: borrows + reservations, Months / Week (CSS-only toggle), hover for details -->
                        <section class="fdb-card fdb-area-chart" aria-labelledby="fdbChartTitle">
                            <?php if ($fdb_has_chart): ?>
                                <input type="radio" name="fdbRange" id="fdbRangeMonths" class="fdb-range-input" checked>
                                <input type="radio" name="fdbRange" id="fdbRangeWeek" class="fdb-range-input">
                            <?php endif; ?>
                            <header class="fdb-card-head">
                                <h2 id="fdbChartTitle">My activity</h2>
                                <?php if ($fdb_has_chart): ?>
                                    <div class="fdb-head-right">
                                        <div class="fdb-legend">
                                            <span><i class="fdb-dot fdb-dot--eq"></i>Borrows</span>
                                            <span><i class="fdb-dot fdb-dot--rm"></i>Reservations</span>
                                        </div>
                                        <div class="fdb-seg" role="radiogroup" aria-label="Chart range">
                                            <label for="fdbRangeMonths">Months</label>
                                            <label for="fdbRangeWeek">Week</label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </header>
                            <?php if ($fdb_has_chart): ?>
                                <div class="fdb-views">
                                    <div class="fdb-view fdb-view--months"><?php echo $fdb_chart(array_values($fdb_months), 'Borrows and reservations per month'); ?></div>
                                    <div class="fdb-view fdb-view--week"><?php echo $fdb_chart($fdb_week, 'Borrows and reservations per day this week'); ?></div>
                                </div>
                            <?php else: ?>
                                <div class="fdb-empty"><span class="material-symbols-outlined" aria-hidden="true">bar_chart</span><span>No activity yet</span></div>
                            <?php endif; ?>
                        </section>

                        <!-- Student Access Code (faculty-only; filled in by the existing code script) -->
                        <section class="fdb-area-code" aria-label="Student access code">
                            <div class="dash-code-card" id="facultyCodePanel">
                                <div class="dash-code-card-header">
                                    <span class="dash-code-card-title">Student Access Code</span>
                                    <span class="dash-code-active-badge" id="fccBadge">ACTIVE</span>
                                </div>
                                <div class="dash-code-body" id="fccBody">
                                    <div class="fcc-loading" style="padding:20px 0;">
                                        <span class="material-symbols-outlined" style="font-size:1.2rem;opacity:.4;animation:spin 1s linear infinite;">sync</span>
                                    </div>
                                </div>
                                <button class="dash-code-generate-btn" id="btnGenerateCode">
                                    Generate New Code
                                </button>
                            </div>
                        </section>

                        <!-- Needs your attention -->
                        <section class="fdb-card fdb-area-attn" aria-labelledby="fdbAttnTitle">
                            <header class="fdb-card-head">
                                <h2 id="fdbAttnTitle">Needs your attention</h2>
                            </header>
                            <?php if ($fdb_attn): ?>
                                <ul class="fdb-attn">
                                    <?php foreach ($fdb_attn as $fdb_v_a): ?>
                                        <li>
                                            <button type="button" class="fdb-attn-item fdb-tone-<?php echo $fdb_h($fdb_v_a['tone']); ?>"
                                                <?php echo $fdb_v_a['act'] === 'notif' ? 'data-action="open-notif-modal"' : 'data-action="go-tab" data-tab="activity"'; ?>>
                                                <span class="fdb-attn-icon" aria-hidden="true"><span class="material-symbols-outlined"><?php echo $fdb_h($fdb_v_a['icon']); ?></span></span>
                                                <span class="fdb-attn-text"><?php echo $fdb_h($fdb_v_a['title']); ?></span>
                                                <span class="material-symbols-outlined fdb-attn-go" aria-hidden="true">chevron_right</span>
                                            </button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="fdb-empty fdb-empty--ok"><span class="material-symbols-outlined" aria-hidden="true">task_alt</span><span>All caught up</span></div>
                            <?php endif; ?>
                        </section>

                        <!-- My schedule today -->
                        <section class="fdb-card fdb-area-third" aria-labelledby="fdbSchedTitle">
                            <header class="fdb-card-head">
                                <h2 id="fdbSchedTitle">My schedule today</h2>
                            </header>
                            <?php if ($fdb_sched): ?>
                                <ul class="fdb-list">
                                    <?php foreach ($fdb_sched as $fdb_v_s): ?>
                                        <li class="fdb-row<?php echo $fdb_v_s['past'] ? ' is-past' : ''; ?>">
                                            <span class="fdb-row-icon" aria-hidden="true"><span class="material-symbols-outlined"><?php echo $fdb_h($fdb_v_s['icon']); ?></span></span>
                                            <span class="fdb-row-main">
                                                <strong><?php echo $fdb_h($fdb_v_s['title']); ?></strong>
                                                <small><?php echo $fdb_h($fdb_v_s['meta']); ?></small>
                                            </span>
                                            <span class="fdb-time"><?php echo $fdb_h($fdb_v_s['time']); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="fdb-empty"><span class="material-symbols-outlined" aria-hidden="true">event_available</span><span>Nothing scheduled today</span></div>
                            <?php endif; ?>
                        </section>

                        <!-- Recent activity -->
                        <section class="fdb-card fdb-area-third" aria-labelledby="fdbRecentTitle">
                            <header class="fdb-card-head">
                                <h2 id="fdbRecentTitle">Recent activity</h2>
                                <button type="button" class="fdb-link" data-action="go-tab" data-tab="activity">View all</button>
                            </header>
                            <?php if ($fdb_recent): ?>
                                <ul class="fdb-list">
                                    <?php foreach ($fdb_recent as $fdb_v_ev): ?>
                                        <li class="fdb-row fdb-tone-<?php echo $fdb_h($fdb_v_ev['tone']); ?>">
                                            <span class="fdb-row-icon" aria-hidden="true"><span class="material-symbols-outlined"><?php echo $fdb_h($fdb_v_ev['icon']); ?></span></span>
                                            <span class="fdb-row-main">
                                                <strong><?php echo $fdb_h($fdb_v_ev['title']); ?></strong>
                                                <small><?php echo $fdb_h($fdb_ago($fdb_v_ev['dt'])); ?></small>
                                            </span>
                                            <span class="fdb-pill"><?php echo $fdb_h($fdb_v_ev['lbl']); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="fdb-empty"><span class="material-symbols-outlined" aria-hidden="true">history</span><span>No activity yet</span></div>
                            <?php endif; ?>
                        </section>

                    </div><!-- /fdb-grid -->
                </div><!-- /fdb -->

            </div><!-- /panel-home -->

