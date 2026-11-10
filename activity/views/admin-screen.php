            <!-- ============================================================
         TAB: ACTIVITY  (every admin)
         One timeline for everything that happens: manual decisions (with the
         admin's name) and automatic ones from the rules engine. Filters are
         type chips + Who + When. Driven by the "Activity page" block in
         admin-dashboard.js and api/admin-activity.php.
    ============================================================ -->
            <div class="tab-panel" id="panel-activity">

                <div class="act-head admin-screen-header">
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
                                    <option value="<?php echo (int)($act_acct['id'] ?? 0); ?>"><?php echo htmlspecialchars($act_acct['fullName'] ?? ''); ?><?php echo !empty($act_acct['admin_no']) ? ' (' . admin_no_label($act_acct['role'] ?? '', $act_acct['admin_no']) . ')' : ''; ?></option>
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




