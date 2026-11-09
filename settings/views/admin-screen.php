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
            <button class="rq-sub-tab" data-sett-panel="sett-equipment">Equipment</button>
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
                        <?php /* Fixed, decorative mask: never built from the real password, so it says nothing about its length. */ ?>
                        <span class="info-val" role="img" aria-label="Password hidden">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
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
                            id="pwLastChangedVal">
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
                        <?php /* High Contrast theme removed (Light / Dark only):
                                    <div class="sett-theme-opt" id="tp-hc" data-action="apply-theme" data-theme="high-contrast">
                                        &#9889; High Contrast
                                    </div>
                                    */ ?>
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
                                            name="config[rule_overdue_block_enabled]" value="1"
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
                                            name="config[rule_duplicate_block_enabled]" value="1"
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
                            <form id="arbConfigForm" autocomplete="off">
                                <?= csrf_field() ?>
                                <!-- toggles from the left card are also submitted via JS -->
                                <div class="ps-form-group">
                                    <label class="ps-form-label">Maximum Borrow Days</label>
                                    <input class="ps-form-control" type="number" min="1" max="60"
                                        name="config[max_borrow_days]"
                                        value="<?php echo htmlspecialchars($arb_config['max_borrow_days'] ?? 7); ?>">
                                    <div class="ps-form-hint">Items must be returned within this many days.</div>
                                </div>
                                <div class="ps-form-group">
                                    <label class="ps-form-label">Max Items Per Borrower</label>
                                    <input class="ps-form-control" type="number" min="1" max="20"
                                        name="config[max_items_per_borrower]"
                                        value="<?php echo htmlspecialchars($arb_config['max_items_per_borrower'] ?? 3); ?>">
                                    <div class="ps-form-hint">Maximum number of items a user can have at once.</div>
                                </div>
                                <div class="ps-form-group">
                                    <label class="ps-form-label">Low Stock Threshold</label>
                                    <input class="ps-form-control" type="number" min="0" max="10"
                                        name="config[low_stock_threshold]"
                                        value="<?php echo htmlspecialchars($arb_config['low_stock_threshold'] ?? 2); ?>">
                                    <div class="ps-form-hint">Trigger low-stock alert when quantity drops below this.</div>
                                </div>
                                <div class="ps-form-group">
                                    <label class="ps-form-label">Buffer Time (Auto-Approve)</label>
                                    <input class="ps-form-control" type="number" min="0" max="1440"
                                        name="config[auto_approve_timeout_minutes]"
                                        value="<?php echo htmlspecialchars($arb_config['auto_approve_timeout_minutes'] ?? 30); ?>">
                                    <div class="ps-form-hint">Auto-approve Waiting requests after this many minutes. Set to 0 to disable.</div>
                                </div>
                                <button type="button" id="saveArbConfig" class="ps-btn ps-btn--primary"
                                    style="width:100%;justify-content:center;margin-top:0.25rem">
                                    <span class="material-symbols-outlined">save</span> Save Configuration
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div><!-- /arb-sub-config -->

        </div><!-- /sett-rules -->
    <?php endif; ?>

    <!-- ── IMPORTS (Super Admin only) ─────────────────────────────
                     Equipment Inventory + Rooms Assigned (placeholders: those imports are not built yet) +
                     Faculty Registry imports behind ONE password unlock.
                     The page only ever contains placeholders: real numbers, lists and
                     files are fetched after unlock (equipment-booking/api/imports.php).
                     Script: equipment-booking/assets/js/admin-imports.js
                     Styles: equipment-booking/assets/css/admin-imports.css -->
    <?php if ($is_super_admin): ?>
        <?php
        $imp_cards = [
            'equipment' => [
                'pending' => true,   // clickable, but nothing is read, uploaded or imported (see initPlaceholderCard)
                'title'   => 'Equipment Inventory',
                'icon'    => 'inventory_2',
                'sub'     => 'Equipment lists with item names and stock counts',
                'types'   => 'XLSX or CSV',
                'accept'  => '.xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'stats'   => ['Items', 'Categories', 'Rows in file'],
                'cols'    => ['#', 'Item name', 'Category', 'Quantity', 'Condition'],
                'what'    => 'Items added to equipment inventory for loan.',
                'replace' => 'Remove the current items first',
            ],
            'faculty' => [
                'title'  => 'Faculty Registry',
                'icon'   => 'badge',
                'sub'    => 'Faculty Identification Records',
                'types'  => 'PDF or XLSX',
                'accept' => '.pdf,.xlsx,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'stats'  => ['Faculty', 'Newly hired', 'Rows in file'],
                'cols'   => ['#', 'Faculty ID', 'Name', 'Programs'],
                'what'   => 'Used to confirm a faculty member when their account is created. A Faculty Rooms workbook also brings in each faculty member\'s weekly classes and rooms, and a Room Timetables workbook adds the classes that have no instructor yet. Both block those rooms for reservations.',
                'replace' => 'Remove everyone listed first',
            ],
            'rooms' => [
                'pending' => true,   // clickable, but nothing is read, uploaded or imported (see initPlaceholderCard)
                'title'   => 'Rooms Assigned',
                'icon'    => 'meeting_room',
                'sub'     => 'Rooms assigned to faculty and classes',
                'types'   => 'XLSX or CSV',
                'accept'  => '.xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'stats'   => ['Rooms', 'Faculty', 'Rows in file'],
                'cols'    => ['#', 'Room', 'Building', 'Floor', 'Assigned to'],
                'what'    => 'Each room on this list is assigned to the faculty or class that uses it.',
                'replace' => 'Remove the current assignments first',
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
                            <p>Bring in the equipment inventory, the room assignments and the official faculty list. The original file of the latest import is kept on record.</p>
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
                        <?php foreach ($imp_cards as $kind => $c): $soon = !empty($c['pending']); ?>
                            <section class="imp-card<?= $soon ? ' imp-card--soon' : '' ?>" data-kind="<?= $kind ?>">
                                <header class="imp-card-head">
                                    <div class="imp-card-icon"><span class="material-symbols-outlined"><?= $c['icon'] ?></span></div>
                                    <div class="imp-card-titles">
                                        <h4><?= htmlspecialchars($c['title']) ?></h4>
                                        <p><?= htmlspecialchars($c['sub']) ?></p>
                                    </div>
                                    <span class="imp-chip imp-chip--muted" data-role="chip"><?= $soon ? 'Coming soon' : '•••' ?></span>
                                </header>

                                <div class="imp-card-body">
                                    <p class="imp-what"><?= htmlspecialchars($c['what']) ?></p>
                                    <button type="button" class="imp-guide-link" data-guide="<?= $kind ?>"><span class="material-symbols-outlined">menu_book</span>How to prepare this file</button>

                                    <div class="imp-stats">
                                        <div class="imp-stat"><span class="imp-stat-num" data-role="stat-count"><?= $soon ? '—' : '•••' ?></span><span class="imp-stat-label"><?= $c['stats'][0] ?></span></div>
                                        <div class="imp-stat"><span class="imp-stat-num" data-role="stat-extra"><?= $soon ? '—' : '••' ?></span><span class="imp-stat-label"><?= $c['stats'][1] ?></span></div>
                                        <div class="imp-stat"><span class="imp-stat-num" data-role="stat-rows"><?= $soon ? '—' : '••' ?></span><span class="imp-stat-label"><?= $c['stats'][2] ?></span></div>
                                    </div>
                                    <p class="imp-last" data-role="last"><span class="material-symbols-outlined">history</span><span data-role="last-text"><?= $soon ? 'Nothing imported yet' : 'Last import •••••••••' ?></span></p>

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
                                                <thead>
                                                    <tr><?php foreach ($c['cols'] as $col): ?><th><?= htmlspecialchars($col) ?></th><?php endforeach; ?></tr>
                                                </thead>
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
                                                    <span><strong>Replace</strong><small><?= htmlspecialchars($c['replace']) ?></small></span>
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

                        <?php
                        // ── Import guide: what each file has to look like, column by column ──
                        // Faculty Registry is the real, working import (rules below come from its parser).
                        // Equipment Inventory and Rooms Assigned are the planned layouts for when those imports are built.
                        $gh = function ($s) {
                            return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
                        };
                        $imp_guide_default = 'rooms';
                        $imp_guide = [
                            'equipment' => [
                                'label'   => 'Equipment Inventory',
                                'planned' => true,
                                'formats' => [['XLSX', 'Best choice', 'ok'], ['CSV', 'Works too', 'alt'], ['PDF', 'Not for this list', 'no']],
                                'why'     => 'Excel keeps every value in its own cell, so quantities stay numbers and a comma in a description never shifts the columns. If you use CSV, save it as "CSV UTF-8" so special characters survive.',
                                'cols'    => [
                                    ['Item name',   true,  'The name students see when they borrow it. One row for each kind of item.', 'HDMI Cable'],
                                    ['Category',    true,  'Pick one: Audio/Visual, Cables & Connectors, Computing, Lab Equipment, Networking, Power, Tools or Others.', 'Cables & Connectors'],
                                    ['Quantity',    true,  'Total units you own. A whole number, 0 or more.', '5'],
                                    ['Condition',   false, 'Good, Fair or For Repair. Leave it blank for Good.', 'Good'],
                                    ['Description', false, 'A short note, up to 500 characters.', '1.5 m, braided'],
                                ],
                                'rows'    => [['HDMI Cable', 'Cables & Connectors', '5', 'Good', '1.5 m, braided'], ['Projector', 'Audio/Visual', '2', 'Fair', 'Acer, HDMI + VGA']],
                                'tips'    => [
                                    'Put the column names in row 1 and start your data in row 2. Nothing above the headers, no blank rows, no merged cells, no totals row.',
                                    'One row per kind of item. Put the total in Quantity instead of repeating the row for every unit.',
                                    'Spell each item the same way every time, because Merge will match items by name.',
                                    'Photos cannot come from the file. Add them afterwards on the Equipment page (JPG or PNG, up to 2 MB each).',
                                    'Keep each file under 5,000 rows and 10 MB, the same limits as the faculty import.',
                                ],
                            ],
                            'rooms' => [
                                'label'   => 'Rooms Assigned',
                                'planned' => true,
                                'formats' => [['XLSX', 'Best choice', 'ok'], ['CSV', 'Works too', 'alt'], ['PDF', 'Not for this list', 'no']],
                                'why'     => 'A spreadsheet keeps room names, floors and faculty names in separate cells, so nothing is guessed from a layout. If you use CSV, save it as "CSV UTF-8".',
                                'cols'    => [
                                    ['Room',            true,  'Exactly as it is named under Facilities.', 'Room 203'],
                                    ['Building',        true,  'Building A (Old), Building B (New) or Building C (CITE).', 'Building B (New)'],
                                    ['Floor',           true,  'The floor label, or just the floor number.', '2nd Floor'],
                                    ['Assigned to',     true,  'The faculty member, as written in the Faculty Registry (Faculty ID or full name).', 'Santos, Maria'],
                                    ['Section / class', false, 'The class or section that uses the room.', 'BSIT 3-1'],
                                ],
                                'rows'    => [['Room 203', 'Building B (New)', '2nd Floor', 'Santos, Maria', 'BSIT 3-1'], ['Audiovisual Room', 'Building C (CITE)', '1st Floor', 'Reyes, Jose', '']],
                                'tips'    => [
                                    'Room and building names must match Facilities exactly, spaces and spelling included ("Room 203", not "203"). A name that does not match cannot be linked to a room.',
                                    'Import the Faculty Registry first, so every name in "Assigned to" already exists.',
                                    'One assignment per row. If two faculty share a room, give each of them their own row.',
                                    'Put the column names in row 1 and start your data in row 2. No blank rows or merged cells.',
                                    'Keep each file under 5,000 rows and 10 MB.',
                                ],
                            ],
                            'faculty' => [
                                'label'   => 'Faculty Registry',
                                'planned' => false,
                                'formats' => [['XLSX', 'Best choice', 'ok'], ['PDF', 'Official document only', 'alt'], ['CSV', 'Not accepted', 'no']],
                                'why'     => 'XLSX is read exactly as typed. The PDF must be the official Faculty Identification Records document, and it has to be a text PDF (you can select the words), not a scan or a photo. For a CSV, open it in Excel and save it as .xlsx first.',
                                'cols'    => [
                                    ['Faculty ID',      true,  'Leave it blank or type NEWLY HIRED if the person has no ID yet. Otherwise 5 to 30 letters and numbers (hyphens allowed) with at least one digit.', 'FA104BN021'],
                                    ['Professor Name',  true,  'Full name. A row with no name is skipped.', 'Santos, Maria'],
                                    ['Programs Handled', false, 'The programs or departments, separated by commas.', 'BSIT, DIT'],
                                ],
                                'rows'    => [['FA104BN021', 'Santos, Maria', 'BSIT, DIT'], ['NEWLY HIRED', 'Cruz, Ana', 'BSBA']],
                                'tips'    => [
                                    'Only the first worksheet is read. Extra sheets are ignored.',
                                    'A header row is optional. If you add one, make it row 1 and use Faculty ID, Professor Name and Programs Handled (ID, Name and Program also work). No title or logo rows above it.',
                                    'Without a header row, the columns are read in this order: A = ID, B = name, C = programs.',
                                    'Format the ID column as Text so Excel does not change the IDs, and avoid merged cells.',
                                    'Up to 5,000 rows and 10 MB per file. Rows with an invalid ID are skipped, and you are shown which ones after the import.',
                                    'Merge updates the people already listed (matched by Faculty ID, new hires by name) and adds the rest. Replace removes everyone first.',
                                ],
                            ],
                        ];
                        $imp_guide_order = ['equipment', 'faculty', 'rooms'];
                        ?>
                        <aside class="imp-guide" id="imp-guide" aria-labelledby="imp-guide-title">
                            <header class="imp-guide-head">
                                <span class="imp-card-icon"><span class="material-symbols-outlined">menu_book</span></span>
                                <div class="imp-card-titles">
                                    <h4 id="imp-guide-title">Import guide</h4>
                                    <p>How to prepare each file before you import it</p>
                                </div>
                            </header>

                            <div class="imp-guide-tabs" role="tablist" aria-label="Choose an import">
                                <?php foreach ($imp_guide_order as $gk): $g = $imp_guide[$gk];
                                    $on = ($gk === $imp_guide_default); ?>
                                    <button type="button" class="imp-gtab" role="tab" id="imp-gtab-<?= $gk ?>" aria-controls="imp-gpanel-<?= $gk ?>" aria-selected="<?= $on ? 'true' : 'false' ?>" tabindex="<?= $on ? '0' : '-1' ?>" data-kind="<?= $gk ?>">
                                        <span class="imp-gdot"></span><?= $gh($g['label']) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>

                            <?php foreach ($imp_guide_order as $gk): $g = $imp_guide[$gk]; ?>
                                <div class="imp-guide-panel" role="tabpanel" id="imp-gpanel-<?= $gk ?>" aria-labelledby="imp-gtab-<?= $gk ?>" data-kind="<?= $gk ?>" <?= $gk === $imp_guide_default ? '' : ' hidden' ?>>
                                    <?php if (!empty($g['planned'])): ?>
                                        <div class="imp-g-planned">
                                            <span class="material-symbols-outlined">construction</span>
                                            <span><strong>Planned layout.</strong> This import is not built yet. Prepare your file this way so it is ready when the import is switched on.</span>
                                        </div>
                                    <?php endif; ?>

                                    <section class="imp-g-sec">
                                        <h5>Best file type</h5>
                                        <div class="imp-g-formats">
                                            <?php foreach ($g['formats'] as $f): ?>
                                                <span class="imp-fmt imp-fmt--<?= $gh($f[2]) ?>"><span class="material-symbols-outlined"><?= $f[2] === 'no' ? 'close' : 'check' ?></span><b><?= $gh($f[0]) ?></b><?= $gh($f[1]) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <p class="imp-g-why"><?= $gh($g['why']) ?></p>
                                    </section>

                                    <section class="imp-g-sec">
                                        <h5>Columns, left to right</h5>
                                        <ol class="imp-g-cols">
                                            <?php foreach ($g['cols'] as $ci => $col): ?>
                                                <li>
                                                    <span class="imp-g-letter" aria-hidden="true"><?= chr(65 + $ci) ?></span>
                                                    <div class="imp-g-col">
                                                        <div class="imp-g-colhead">
                                                            <strong><?= $gh($col[0]) ?></strong>
                                                            <span class="imp-req imp-req--<?= $col[1] ? 'yes' : 'no' ?>"><?= $col[1] ? 'Required' : 'Optional' ?></span>
                                                        </div>
                                                        <p><?= $gh($col[2]) ?></p>
                                                        <p class="imp-g-ex">e.g. <code><?= $gh($col[3]) ?></code></p>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    </section>

                                    <section class="imp-g-sec">
                                        <h5>What your sheet should look like</h5>
                                        <div class="imp-sheet-wrap">
                                            <table class="imp-sheet">
                                                <thead>
                                                    <tr class="is-letters">
                                                        <th></th><?php foreach ($g['cols'] as $ci => $col): ?><th><?= chr(65 + $ci) ?></th><?php endforeach; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="is-head">
                                                        <td class="is-gutter">1</td><?php foreach ($g['cols'] as $col): ?><td><?= $gh($col[0]) ?></td><?php endforeach; ?>
                                                    </tr>
                                                    <?php foreach ($g['rows'] as $ri => $row): ?>
                                                        <tr>
                                                            <td class="is-gutter"><?= $ri + 2 ?></td><?php foreach ($row as $cell): ?><td><?= $gh($cell) ?></td><?php endforeach; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </section>

                                    <section class="imp-g-sec">
                                        <h5>Before you import</h5>
                                        <ul class="imp-g-tips">
                                            <?php foreach ($g['tips'] as $tip): ?>
                                                <li><span class="material-symbols-outlined">task_alt</span><span><?= $gh($tip) ?></span></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </section>
                                </div>
                            <?php endforeach; ?>
                        </aside>
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
                        <span class="imp-lock-note"><span class="material-symbols-outlined">lock</span> Password required to unlock</span>
                    </div>
                </div>

            </div>
        </div><!-- /sett-imports -->

        <!-- ── EQUIPMENT SETTINGS (Super Admin only) ─────────────────── -->
        <div class="rq-sub-panel" id="sett-equipment">
            <div class="ps-card" style="max-width:720px;">
                <div class="ps-card-header">
                    <h3><span class="material-symbols-outlined">category</span> Equipment Categories</h3>
                </div>
                <div class="ps-card-body">
                    <p style="margin-bottom:16px;font-size:.875rem;color:var(--text-light);">
                        Categories are shown when adding or editing equipment. Each campus has its own list.
                        Items already assigned to a category are not affected when you add or delete categories here.
                    </p>

                    <!-- Campus selector -->
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:20px;">
                        <label for="catMgrCampus" style="font-size:.875rem;font-weight:600;white-space:nowrap;">Campus</label>
                        <select id="catMgrCampus" class="form-control-custom" style="width:220px;">
                            <?php
                            $cat_campuses = $conn->query("SELECT campus_id, campus_name FROM tbl_campuses ORDER BY campus_id ASC");
                            while ($cat_campuses && ($cc = $cat_campuses->fetch_assoc())):
                            ?>
                                <option value="<?php echo (int)$cc['campus_id']; ?>"><?php echo htmlspecialchars($cc['campus_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Category list -->
                    <div id="catMgrList" style="margin-bottom:20px;min-height:40px;">
                        <span style="color:var(--text-light);font-size:.875rem;">Loading…</span>
                    </div>

                    <!-- Add new category -->
                    <div style="display:flex;gap:8px;align-items:flex-start;">
                        <input type="text" id="catMgrInput" class="form-control-custom"
                            placeholder="New category name" maxlength="100"
                            style="flex:1;" autocomplete="off">
                        <button type="button" class="ps-btn ps-btn--primary" id="catMgrAdd">
                            <span class="material-symbols-outlined">add</span> Add
                        </button>
                    </div>
                    <p id="catMgrMsg" style="margin-top:8px;font-size:.8rem;display:none;"></p>
                </div>
            </div>

            <!-- Google Sheets Sync card -->
            <div class="ps-card" style="max-width:720px;margin-top:20px;">
                <div class="ps-card-header">
                    <h3><span class="material-symbols-outlined">sync</span> Google Sheets Sync</h3>
                </div>
                <div class="ps-card-body">
                    <p style="font-size:.875rem;color:var(--text-light);margin-bottom:16px;">
                        Connect your Google Sheets inventory. PUPSync will read the spreadsheet and
                        automatically update equipment items. The sheet must be set to
                        <strong>"Anyone with the link can view"</strong>.
                    </p>

                    <div class="form-group" style="margin-bottom:16px;">
                        <label for="gsheetUrl" style="font-size:.875rem;font-weight:600;">Google Sheets URL</label>
                        <div style="display:flex;gap:8px;margin-top:6px;">
                            <input type="url" id="gsheetUrl" class="form-control-custom" style="flex:1;"
                                placeholder="https://docs.google.com/spreadsheets/d/..."
                                value="<?php
                                $cfg_url_q = $conn->query("SELECT value FROM tbl_sync_config WHERE config_key='gsheet_url' LIMIT 1");
                                echo $cfg_url_q ? htmlspecialchars((string)($cfg_url_q->fetch_assoc()['value'] ?? '')) : '';
                                ?>">
                            <button type="button" class="ps-btn ps-btn--ghost" id="gsheetSaveUrl">Save URL</button>
                        </div>
                    </div>

                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <button type="button" class="ps-btn ps-btn--primary" id="gsheetSyncNow">
                            <span class="material-symbols-outlined">sync</span> Sync Now
                        </button>
                        <span id="gsheetLastSync" style="font-size:.8rem;color:var(--text-light);">
                            <?php
                            $ls_q = $conn->query("SELECT value FROM tbl_sync_config WHERE config_key='gsheet_last_sync' LIMIT 1");
                            $ls = $ls_q ? (string)($ls_q->fetch_assoc()['value'] ?? '') : '';
                            echo $ls ? 'Last synced: ' . htmlspecialchars(date('M j, Y g:i A', strtotime($ls))) : 'Never synced';
                            ?>
                        </span>
                    </div>

                    <div id="gsheetSyncResult" style="margin-top:12px;display:none;padding:12px;border-radius:8px;font-size:.85rem;"></div>

                    <!-- Pending deletions -->
                    <?php
                    $pd_q = $conn->query("SELECT pd.id, pd.item_name, c.campus_name FROM tbl_sync_pending_deletions pd LEFT JOIN tbl_campuses c ON c.campus_id = pd.campus_id ORDER BY pd.detected_at DESC");
                    $pd_rows = $pd_q ? $pd_q->fetch_all(MYSQLI_ASSOC) : [];
                    if ($pd_rows):
                    ?>
                    <div style="margin-top:20px;padding:14px;background:#fff8e1;border-radius:10px;border:1px solid #ffe082;">
                        <p style="font-weight:700;font-size:.875rem;margin-bottom:10px;">
                            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;color:#f57c00;">warning</span>
                            <?php echo count($pd_rows); ?> item(s) were removed from the Google Sheet
                        </p>
                        <p style="font-size:.8rem;color:var(--text-light);margin-bottom:12px;">
                            These items exist in PUPSync but are no longer in your spreadsheet. Decide what to do with each one.
                        </p>
                        <?php foreach ($pd_rows as $pd): ?>
                            <div class="pd-row" data-pd-id="<?php echo (int)$pd['id']; ?>" style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #ffecb3;">
                                <div>
                                    <strong style="font-size:.875rem;"><?php echo htmlspecialchars($pd['item_name']); ?></strong>
                                    <span style="font-size:.78rem;color:var(--text-light);margin-left:8px;"><?php echo htmlspecialchars($pd['campus_name'] ?? ''); ?></span>
                                </div>
                                <div style="display:flex;gap:8px;">
                                    <button type="button" class="ps-btn ps-btn--danger ps-btn--sm" data-pd-action="archive" data-pd-id="<?php echo (int)$pd['id']; ?>">Archive it</button>
                                    <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm" data-pd-action="keep" data-pd-id="<?php echo (int)$pd['id']; ?>">Keep it</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div><!-- /sett-equipment -->

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
                                    <span class="hc-faq-q-text">How do I review a borrow request?</span>
                                    <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                </summary>
                                <div class="hc-faq-a">
                                    Go to <strong>Equipment → Loans → Waiting</strong>, then click <strong>Approve</strong> or <strong>Decline</strong>. Both ask for a short note (at least 5 characters). When a faculty member requested several items at once, <strong>Approve all</strong> approves the whole bundle and gives them one Claim QR. Faculty see the result in their Notifications and My Activity.<br><br>
                                    A <strong>same-day</strong> request for a single item is decided by the system straight away, based on availability and the borrowing rules, so nothing waits for you. <strong>Later-date</strong> requests, and <strong>Borrow All</strong> requests for several items, wait here. If the faculty attached a request letter (recommended, not required), open it from the document chip before deciding. A request nobody decides before its borrow date has passed is declined automatically.<br><br>
                                    Students who borrow with a faculty code or item code do not need approval. Those loans go straight to <strong>Pickup</strong>.
                                </div>
                            </details>

                            <details class="hc-faq-item">
                                <summary class="hc-faq-q">
                                    <span class="hc-faq-q-text">How does equipment get from approved to returned?</span>
                                    <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                </summary>
                                <div class="hc-faq-a">
                                    <strong>1. Pickup.</strong> An approved loan sits under <strong>Pickup</strong> until the borrower arrives. On the <strong>Dashboard</strong>, click <strong>Scan return</strong> and scan their <strong>Claim QR</strong>. The loan becomes <strong>Active</strong>.<br>
                                    <strong>2. Return.</strong> When the item comes back, scan their <strong>Return QR</strong> with the same button. The loan becomes <strong>Returned</strong> and the stock goes back.<br>
                                    <strong>No QR code?</strong> Open the loan in <strong>Equipment → Loans</strong> and click <strong>Manual Return</strong>. Choose the condition (Good, Fair, Damaged or Missing) and add a note if needed.
                                </div>
                            </details>

                            <details class="hc-faq-item">
                                <summary class="hc-faq-q">
                                    <span class="hc-faq-q-text">What do the request statuses mean?</span>
                                    <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                </summary>
                                <div class="hc-faq-a">
                                    <div class="hc-status-table">
                                        <div class="hc-st-row"><span class="hc-st-pill pending">Pending</span><span>Waiting for an admin decision (Loans → Waiting).</span></div>
                                        <div class="hc-st-row"><span class="hc-st-pill approved">Scheduled</span><span>Approved for a later date and not started yet. It can still be cancelled.</span></div>
                                        <div class="hc-st-row"><span class="hc-st-pill approved">Pickup</span><span>Approved. Waiting for the borrower to show their Claim QR.</span></div>
                                        <div class="hc-st-row"><span class="hc-st-pill approved">Active</span><span>The item is with the borrower (Due today, or Due in N days).</span></div>
                                        <div class="hc-st-row"><span class="hc-st-pill overdue">Overdue</span><span>The return time has passed and the item is not back yet.</span></div>
                                        <div class="hc-st-row"><span class="hc-st-pill returned">Returned</span><span>The item was returned and the stock restored.</span></div>
                                        <div class="hc-st-row"><span class="hc-st-pill declined">Declined</span><span>Declined by an admin, or expired because nobody decided in time.</span></div>
                                        <div class="hc-st-row"><span class="hc-st-pill cancelled">Cancelled</span><span>Cancelled by an admin or by the faculty member.</span></div>
                                    </div>
                                </div>
                            </details>

                            <details class="hc-faq-item">
                                <summary class="hc-faq-q">
                                    <span class="hc-faq-q-text">What should I do about an overdue item?</span>
                                    <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                </summary>
                                <div class="hc-faq-a">
                                    Open <strong>Equipment → Loans → Overdue</strong>. Click <strong>Send Notice</strong> to remind the borrower in their PUPSync notification inbox, or <strong>Manual Return</strong> if the item is already back.<br><br>
                                    While equipment is overdue, that faculty member cannot borrow more equipment or issue student codes. Room reservations are not affected. Faculty can also extend an item themselves (up to 2 times, if nobody else needs it). Extended loans show <em>Extended ×N</em> under the due date, and the original lateness stays on record.
                                </div>
                            </details>

                            <details class="hc-faq-item">
                                <summary class="hc-faq-q">
                                    <span class="hc-faq-q-text">How do I add, edit or archive equipment?</span>
                                    <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                </summary>
                                <div class="hc-faq-a">
                                    Go to <strong>Equipment → Catalog</strong>. Fill in the item name, category, quantity and condition (Good, Fair or For Repair). A photo (JPG or PNG, up to 2 MB) is optional. Click <strong>Save Equipment</strong>.<br><br>
                                    To change an item, click its edit button. <strong>Archive Item</strong> in the edit window hides it from faculty and keeps its history. Archived items are listed at the bottom of the Catalog, where you can <strong>Restore</strong> them or delete them permanently (this cannot be undone). The Dashboard's <strong>Stock watch</strong> lists items with 2 or fewer units.
                                </div>
                            </details>

                            <details class="hc-faq-item">
                                <summary class="hc-faq-q">
                                    <span class="hc-faq-q-text">How are facility reservations approved?</span>
                                    <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                </summary>
                                <div class="hc-faq-a">
                                    Go to <strong>Facilities → Reservations</strong>. You can filter by All, Active, Waiting, Approved, Cancelled or Declined.<br><br>
                                    <strong>Same-day</strong> reservations are confirmed automatically when the slot is free, so there is nothing to approve. <strong>Later-date</strong> reservations wait for an admin. Open the reservation and click <strong>Approve</strong> or <strong>Decline</strong> with a reason (at least 5 characters). If nobody decides by 12:01 AM on the reservation date, it is declined automatically. A request letter, when the faculty attached one, opens from the reservation details. Any admin can decide these.<br><br>
                                    Faculty can book from 7:00 AM to 8:00 PM, Monday to Saturday, up to 3 weeks ahead, and can hold at most 2 active reservations at a time.
                                </div>
                            </details>

                            <details class="hc-faq-item">
                                <summary class="hc-faq-q">
                                    <span class="hc-faq-q-text">How do I cancel an approved reservation?</span>
                                    <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                </summary>
                                <div class="hc-faq-a">
                                    In <strong>Facilities → Reservations</strong>, open the approved reservation and click <strong>Cancel reservation</strong>. The faculty member is notified by email, and anyone on the waitlist for that slot is told it is free. A reservation cannot be cancelled within 1 hour of its start time.
                                </div>
                            </details>

                            <details class="hc-faq-item">
                                <summary class="hc-faq-q">
                                    <span class="hc-faq-q-text">How do I handle a reported facility issue?</span>
                                    <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                </summary>
                                <div class="hc-faq-a">
                                    Open <strong>Facilities → Issues</strong> and click <strong>Review</strong> on a report. Choose <strong>Mark Resolved</strong> or <strong>Dismiss</strong>, and add a note if you like. When resolving, you can tick <strong>Set facility to Maintenance mode</strong>. Maintenance facilities cannot be reserved until you set them back to Available.
                                </div>
                            </details>

                            <details class="hc-faq-item">
                                <summary class="hc-faq-q">
                                    <span class="hc-faq-q-text">How do I add, lock or archive a facility?</span>
                                    <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                </summary>
                                <div class="hc-faq-a">
                                    In <strong>Facilities</strong>, a Super Admin can click <strong>Add Facility</strong> (building, name, floor, status and features). Admins and Super Admins can edit a facility. The status is Available, Maintenance or Not bookable, and the last two cannot be reserved.<br><br>
                                    <strong>Lock Facility</strong> (in the Edit window) keeps a facility visible but stops its schedule from opening. Admins and Super Admins can lock and unlock.<br><br>
                                    Only a Super Admin can <strong>Archive</strong> a facility. Archived facilities are hidden from new reservations but their history is kept, and they can be restored from <strong>Facilities → Archived</strong>. Only a Super Admin can delete an archived facility permanently, which removes its reservation history for good.
                                </div>
                            </details>

                            <details class="hc-faq-item">
                                <summary class="hc-faq-q">
                                    <span class="hc-faq-q-text">Where can I see what has been happening?</span>
                                    <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                </summary>
                                <div class="hc-faq-a">
                                    The <strong>Dashboard</strong> shows what needs attention right now. The <strong>Activity</strong> tab is the full timeline of decisions and changes, and who made them. You can filter it by type (Requests, Facilities, Equipment, System, Sign-ins), by person, and by date, and export it. The bell on the Dashboard opens your Notifications. Choose which alerts you get (new requests, overdue items, facility issues) in <strong>Settings → Preferences</strong>.
                                </div>
                            </details>

                            <details class="hc-faq-item">
                                <summary class="hc-faq-q">
                                    <span class="hc-faq-q-text">How do I keep my account secure?</span>
                                    <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                </summary>
                                <div class="hc-faq-a">
                                    In <strong>Settings → My Account</strong> you can edit your profile. If you signed in on a shared or public computer, use <strong>Log Out All</strong> to sign out of every device.
                                </div>
                            </details>

                            <?php if ($is_super_admin): ?>
                                <details class="hc-faq-item">
                                    <summary class="hc-faq-q">
                                        <span class="hc-faq-q-text">How do I create and manage faculty accounts? (Super Admin)</span>
                                        <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                    </summary>
                                    <div class="hc-faq-a">
                                        Go to <strong>Faculty → Create Faculty Account</strong>. Enter the first and last name and click <strong>Look up in Faculty Registry</strong> to fill in the Faculty ID, which is permanent. The email must end with <em>@pupsync.edu</em>. Set a password of at least 8 characters (rated Fair or stronger).<br><br>
                                        Use the edit button on a faculty row to change the name, email or backup email. There is no password reset here; faculty change their own password in their Settings.<br><br>
                                        <strong>Delete Account</strong> is blocked while the faculty member still has pending requests, equipment that is not back, or upcoming reservations. You can override this by confirming with your own password. That declines their pending requests and cancels their upcoming reservations. Equipment already out can still be returned, and all history is kept.
                                    </div>
                                </details>

                                <details class="hc-faq-item">
                                    <summary class="hc-faq-q">
                                        <span class="hc-faq-q-text">How do I manage admin accounts? (Super Admin)</span>
                                        <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                    </summary>
                                    <div class="hc-faq-a">
                                        Open <strong>Admins</strong>. There are 5 admin seats in total. Click <strong>Add admin</strong> and enter the full name, an email ending in <em>@admin.edu</em>, the role (<strong>Admin</strong> or <strong>Super Admin</strong>), and a password of 8 to 72 characters with an uppercase letter, a lowercase letter, a number and a special character.<br><br>Select an admin to see their last sign-in and recent activity, or to <strong>Edit</strong>, <strong>Lock</strong> (for up to 30 days) or <strong>Delete</strong> them. Deleting frees a seat. Edit, Lock and Delete ask you to confirm with your password, and Super Admin accounts cannot be edited, locked or deleted from here.
                                    </div>
                                </details>

                                <details class="hc-faq-item">
                                    <summary class="hc-faq-q">
                                        <span class="hc-faq-q-text">What are Imports for? (Super Admin)</span>
                                        <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                    </summary>
                                    <div class="hc-faq-a">
                                        <strong>Settings → Imports</strong> is locked behind your password. Use it to bring in:<br>
                                        <strong>Equipment Inventory</strong> (XLSX or CSV): for importing equipment lists. This card is a placeholder and is not available yet.<br>
                                        <strong>Rooms Assigned</strong> (XLSX or CSV): for importing which rooms are assigned to which faculty or class. This card is a placeholder and is not available yet.<br>
                                        <strong>Faculty Registry</strong> (PDF or XLSX): used to confirm a faculty member when you create their account.<br><br>
                                        Choose <strong>Merge</strong> to add new entries and update existing ones, or <strong>Replace</strong> to remove everyone listed first. Files can be up to 10 MB, and the original file of the latest import is kept.
                                    </div>
                                </details>

                                <details class="hc-faq-item">
                                    <summary class="hc-faq-q">
                                        <span class="hc-faq-q-text">How do I clear all loans or reservations? (Super Admin)</span>
                                        <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                    </summary>
                                    <div class="hc-faq-a">
                                        Use <strong>Delete All</strong> in <strong>Equipment → Loans</strong> (the loans list) or in <strong>Facilities → Reservations</strong> (the reservations list). Each one first shows exactly how many records it will remove, then asks you to confirm with your own password. Too many wrong passwords lock the confirmation for 5 minutes.<br><br>
                                        This <strong>permanently deletes</strong> every record in that list and cannot be undone. For loans that means the Waiting, Approved (including scheduled), Overdue and Returned ones, along with their uploaded request letters. Declined and cancelled leftovers are not touched.
                                    </div>
                                </details>

                                <details class="hc-faq-item">
                                    <summary class="hc-faq-q">
                                        <span class="hc-faq-q-text">What is Borrowing Rules? (Super Admin)</span>
                                        <span class="material-symbols-outlined hc-faq-chevron">expand_more</span>
                                    </summary>
                                    <div class="hc-faq-a">
                                        <strong>Settings → Borrowing Rules</strong> holds the rule settings (toggles and limits) for the automatic decision engine. It decides <strong>same-day</strong> equipment requests and room reservations on the spot. <strong>Later-date</strong> equipment requests are not decided by it: they wait in <strong>Equipment → Loans → Waiting</strong> for an admin. Automatic decisions are listed in <strong>Activity</strong> under <em>Automatic</em>.
                                    </div>
                                </details>
                            <?php endif; ?>

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