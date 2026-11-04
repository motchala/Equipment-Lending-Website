            <!-- ============================================================
    <!-- ============================================================
         TAB: SETTINGS  (streamlined — consolidates the old Settings tab,
         Account overlay, Arbitration tab, and Help Center overlay into
         one screen with four sub-tabs)
    ============================================================ -->
            <div class="tab-panel" id="panel-settings">

                <!-- Page Header -->
                <div class="sett-page-header admin-screen-header">
                    <h1 class="sett-page-title">Settings</h1>
                    <p class="sett-page-sub">Manage your account, preferences, borrowing rules, and get help.</p>
                </div>

                <!-- Settings mega sub-tabs -->
                <div class="rq-sub-tabs" id="settMainTabs">
                    <button class="rq-sub-tab active" data-sett-panel="sett-account">My Account</button>
                    <button class="rq-sub-tab" data-sett-panel="sett-prefs">Preferences</button>
                    <?php if ($is_super_admin): ?>
                        <button class="rq-sub-tab" data-sett-panel="sett-rules">Borrowing Rules</button>
                        <button class="rq-sub-tab" data-sett-panel="sett-imports">Imports</button>
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
                            <div class="ov-hero-role"><?php echo $is_super_admin ? 'Super Admin' : 'Admin'; ?> &middot; PUPSync Biñan Campus</div>
                            <div class="ov-hero-badge">
                                <span class="material-symbols-outlined">verified</span>
                                Active &middot; <?php echo $is_super_admin ? 'Super Admin Access' : 'Admin Access'; ?>
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
                                    <div class="info-value-wrap">
                                        <span class="info-val <?php echo empty($admin_name) ? 'empty' : ''; ?>"
                                            data-field="admin_name">
                                            <?php echo !empty($admin_name) ? htmlspecialchars($admin_name) : '— Not provided'; ?>
                                        </span>
                                        <input class="info-input-f" data-input="admin_name"
                                            value="<?php echo htmlspecialchars($admin_name ?? ''); ?>"
                                            placeholder="Display Name" disabled>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Role</span>
                                    <span class="info-val"><?php echo $is_super_admin ? 'Super Admin' : 'Admin'; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Email Address</span>
                                    <div class="info-value-wrap">
                                        <span class="info-val <?php echo empty($admin_email) ? 'empty' : ''; ?>"
                                            data-field="admin_email">
                                            <?php echo !empty($admin_email) ? htmlspecialchars($admin_email) : '— Not provided'; ?>
                                        </span>
                                        <input class="info-input-f" data-input="admin_email" type="email"
                                            value="<?php echo htmlspecialchars($admin_email ?? ''); ?>"
                                            placeholder="admin@pup.edu.ph" disabled>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Backup Email</span>
                                    <div class="info-value-wrap">
                                        <span class="info-val <?php echo empty($admin_backup_email) ? 'empty' : ''; ?>"
                                            data-field="admin_backup_email">
                                            <?php echo !empty($admin_backup_email) ? htmlspecialchars($admin_backup_email) : '— Not provided'; ?>
                                        </span>
                                        <input class="info-input-f" data-input="admin_backup_email" type="email"
                                            value="<?php echo htmlspecialchars($admin_backup_email ?? ''); ?>"
                                            placeholder="backup@example.com" autocomplete="email" disabled>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Campus</span>
                                    <span class="info-val">PUPSync Biñan Campus</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Access Level</span>
                                    <span class="info-val">
                                        <span class="ov-access-badge"><?php echo $is_super_admin ? 'Super Admin Access' : 'Admin Access'; ?></span>
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
                                    <button class="btn-inline-sm" data-action="open-change-pass"
                                        data-profile-edit-gated disabled aria-hidden="true" tabindex="-1">Change</button>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Last Changed</span>
                                    <?php
                                    $ts_pwc = !empty($admin_last_pw_change) ? strtotime($admin_last_pw_change) : false;
                                    $pwc_untracked = $ts_pwc === false;
                                    ?>
                                    <span class="info-val<?php echo $pwc_untracked ? ' pw-change-untracked' : ''; ?>"
                                        id="pwLastChangedVal" style="color:var(--text-light)">
                                        <?php echo $pwc_untracked ? '— Not tracked' : htmlspecialchars(date('M d, Y · g:i A', $ts_pwc)); ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-lbl">Session Status</span>
                                    <span class="info-val">
                                        <span class="ov-status-dot">Active</span>
                                    </span>
                                    <button class="btn-inline-danger" data-action="logout-all">Log Out All</button>
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

                    </div><!-- /sett-grid -->

                    <div class="sett-reset-row">
                        <div>
                            <div class="sett-notif-title">Reset All Settings</div>
                            <div class="sett-notif-sub">Restore appearance and notification preferences to their default values.</div>
                        </div>
                        <button class="sett-reset-btn" data-action="reset-settings">
                            <span class="material-symbols-outlined">restart_alt</span> Reset
                        </button>
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

                <!-- ── IMPORTS (Super Admin only) ─────────────────────────────
                     Student List + Faculty Registry imports behind ONE password unlock.
                     The page only ever contains placeholders: real numbers, lists and
                     files are fetched after unlock (equipment-booking/api/imports.php).
                     Script: equipment-booking/assets/js/admin-imports.js
                     Styles: equipment-booking/assets/css/admin-imports.css -->
                <?php if ($is_super_admin): ?>
                    <?php
                    $imp_cards = [
                        'students' => [
                            'title'  => 'Student List',
                            'icon'   => 'school',
                            'sub'    => 'Official class lists exported from AIMS',
                            'types'  => 'PDF',
                            'accept' => '.pdf,application/pdf',
                            'stats'  => ['Students', 'Sections', 'Rows in file'],
                            'cols'   => ['#', 'Student No.', 'Name', 'Section', 'Year level'],
                            'what'   => 'Students on this list can use the Student Portal. Anyone else is turned away.',
                        ],
                        'faculty' => [
                            'title'  => 'Faculty Registry',
                            'icon'   => 'badge',
                            'sub'    => 'Faculty Identification Records',
                            'types'  => 'PDF or XLSX',
                            'accept' => '.pdf,.xlsx,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'stats'  => ['Faculty', 'Newly hired', 'Rows in file'],
                            'cols'   => ['#', 'Faculty ID', 'Name', 'Programs'],
                            'what'   => 'Used to confirm a faculty member when their account is created.',
                        ],
                    ];
                    ?>
                    <div class="rq-sub-panel" id="sett-imports">
                        <div class="imp-shell is-locked" id="imp-shell">

                            <div class="imp-content" id="imp-content" inert aria-hidden="true">
                                <?= csrf_field() ?>

                                <div class="imp-topbar">
                                    <div class="imp-topbar-text">
                                        <h3>Data imports</h3>
                                        <p>Bring in the official student and faculty lists. The original file of the latest import is kept on record.</p>
                                    </div>
                                    <div class="imp-session" id="imp-session">
                                        <span class="imp-session-dot"></span>
                                        <span class="imp-session-text" id="imp-session-text">Unlocked</span>
                                        <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm" id="imp-lock-now">
                                            <span class="material-symbols-outlined">lock</span> Lock
                                        </button>
                                    </div>
                                </div>

                                <div class="imp-grid">
                                    <?php foreach ($imp_cards as $kind => $c): ?>
                                        <section class="imp-card" data-kind="<?= $kind ?>">
                                            <header class="imp-card-head">
                                                <div class="imp-card-icon"><span class="material-symbols-outlined"><?= $c['icon'] ?></span></div>
                                                <div class="imp-card-titles">
                                                    <h4><?= htmlspecialchars($c['title']) ?></h4>
                                                    <p><?= htmlspecialchars($c['sub']) ?></p>
                                                </div>
                                                <span class="imp-chip imp-chip--muted" data-role="chip">•••</span>
                                            </header>

                                            <div class="imp-card-body">
                                                <p class="imp-what"><?= htmlspecialchars($c['what']) ?></p>

                                                <div class="imp-stats">
                                                    <div class="imp-stat"><span class="imp-stat-num" data-role="stat-count">•••</span><span class="imp-stat-label"><?= $c['stats'][0] ?></span></div>
                                                    <div class="imp-stat"><span class="imp-stat-num" data-role="stat-extra">••</span><span class="imp-stat-label"><?= $c['stats'][1] ?></span></div>
                                                    <div class="imp-stat"><span class="imp-stat-num" data-role="stat-rows">••</span><span class="imp-stat-label"><?= $c['stats'][2] ?></span></div>
                                                </div>
                                                <p class="imp-last" data-role="last"><span class="material-symbols-outlined">history</span><span data-role="last-text">Last import •••••••••</span></p>

                                                <div class="imp-drop" data-role="drop" tabindex="0" role="button" aria-label="Choose the <?= htmlspecialchars($c['title']) ?> file">
                                                    <input type="file" class="imp-file-input" data-role="input" accept="<?= $c['accept'] ?>">
                                                    <span class="material-symbols-outlined imp-drop-icon">upload_file</span>
                                                    <strong>Drop the file here</strong>
                                                    <span class="imp-drop-hint">or click to browse &middot; <?= $c['types'] ?> &middot; up to 10 MB</span>
                                                </div>

                                                <div class="imp-picked hidden" data-role="picked">
                                                    <span class="material-symbols-outlined imp-picked-icon" data-role="picked-icon">description</span>
                                                    <div class="imp-picked-info">
                                                        <strong data-role="picked-name"></strong>
                                                        <span data-role="picked-size"></span>
                                                    </div>
                                                    <button type="button" class="imp-icon-btn" data-role="clear" aria-label="Remove file"><span class="material-symbols-outlined">close</span></button>
                                                </div>

                                                <div class="imp-alert hidden" data-role="alert" role="alert"></div>

                                                <div class="imp-preview hidden" data-role="preview">
                                                    <div class="imp-preview-head"><span data-role="preview-count"></span></div>
                                                    <div class="imp-table-wrap">
                                                        <table class="imp-table">
                                                            <thead><tr><?php foreach ($c['cols'] as $col): ?><th><?= htmlspecialchars($col) ?></th><?php endforeach; ?></tr></thead>
                                                            <tbody data-role="preview-body"></tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <fieldset class="imp-mode" data-role="mode">
                                                    <legend>When it is imported</legend>
                                                <div class="imp-mode-row">
                                                    <label class="imp-mode-opt">
                                                        <input type="radio" name="imp-mode-<?= $kind ?>" value="merge" checked>
                                                        <span class="imp-mode-box">
                                                            <span class="material-symbols-outlined">merge_type</span>
                                                            <span><strong>Merge</strong><small>Add new entries, update existing ones</small></span>
                                                        </span>
                                                    </label>
                                                    <label class="imp-mode-opt">
                                                        <input type="radio" name="imp-mode-<?= $kind ?>" value="replace">
                                                        <span class="imp-mode-box imp-mode-box--warn">
                                                            <span class="material-symbols-outlined">sync_alt</span>
                                                            <span><strong>Replace</strong><small>Remove everyone listed first</small></span>
                                                        </span>
                                                    </label>
                                                </div>
                                                </fieldset>

                                                <button type="button" class="ps-btn ps-btn--primary imp-import-btn" data-role="import" disabled>
                                                    <span class="material-symbols-outlined">cloud_upload</span> Import
                                                </button>
                                            </div>

                                            <footer class="imp-card-foot">
                                                <button type="button" class="imp-foot-btn" data-role="view-list">
                                                    <span class="material-symbols-outlined">table_view</span> Imported list
                                                </button>
                                                <button type="button" class="imp-foot-btn" data-role="view-files">
                                                    <span class="material-symbols-outlined">folder_open</span> Original file
                                                </button>
                                            </footer>
                                        </section>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="imp-lock" id="imp-lock">
                                <div class="imp-lock-inner">
                                    <button type="button" class="imp-lock-btn" id="imp-lock-btn" aria-label="Unlock Imports">
                                        <span class="material-symbols-outlined">lock</span>
                                    </button>
                                    <h4>Imports are locked</h4>
                                    <p>Click the lock and enter your password to view or make imports, or to open the files that were imported.</p>
                                    <p class="imp-lock-msg hidden" id="imp-lock-msg"></p>
                                    <span class="imp-lock-note"><span class="material-symbols-outlined">shield_person</span> Super Admin only</span>
                                </div>
                            </div>

                        </div>
                    </div><!-- /sett-imports -->
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
