<!-- ============================================================
    <!-- ============================================================
         TAB: SETTINGS  (account, preferences, equipment, and help)
    ============================================================ -->
<div class="tab-panel" id="panel-settings">

    <!-- Page Header -->
    <div class="sett-page-header admin-screen-header">
        <h1 class="sett-page-title">Settings</h1>
        <p class="sett-page-sub">Manage your account, preferences, and get help.</p>
    </div>

    <!-- Settings mega sub-tabs -->
    <div class="rq-sub-tabs" id="settMainTabs">
        <button class="rq-sub-tab active" data-sett-panel="sett-account">My Account</button>
        <button class="rq-sub-tab" data-sett-panel="sett-prefs">Preferences</button>
        <?php if ($is_super_admin): ?>
            <button class="rq-sub-tab" data-sett-panel="sett-arbitration">Arbitration</button>
        <?php endif; ?>
        <button class="rq-sub-tab" data-sett-panel="sett-ratings">Service Ratings</button>
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
                <?php
                // "Super Admin 1" / "Admin 2": this account's seat number from the admin list
                $my_role_label = $is_super_admin ? 'Super Admin' : 'Admin';
                foreach ($admin_accounts_list as $_me) {
                    if (strtolower($_me['email'] ?? '') === strtolower((string)$admin_email)) {
                        $my_role_label = admin_no_label($admin_role, $_me['admin_no'] ?? 0) ?: $my_role_label;
                        break;
                    }
                }
                ?>
                <div class="ov-hero-role"><?php echo htmlspecialchars($my_role_label); ?> &middot; PUPSync Biñan Campus</div>
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
                <?php
                // Notification Preferences are saved per admin account on the server
                // (notifications/api/notif-prefs.php) and applied there, so a muted
                // category is never listed, counted, popped up or emailed.
                require_once PS_ROOT . '/notifications/core/preferences.php';
                $np_defs  = notif_pref_definitions('admin');
                $np_state = notif_prefs_get($conn, 'admin', notif_prefs_current_key($conn, 'admin'));
                ?>
                <div class="sett-card-body sett-card-body--notifs<?php echo empty($np_state['all']) ? ' notif-prefs-off' : ''; ?>" id="notifPrefsPanel" data-notif-audience="admin">
                    <div class="sett-notif-row sett-notif-row--master">
                        <label class="toggle-sw">
                            <input type="checkbox" data-notif-pref="all" data-notif-label="All notifications" aria-label="All notifications"<?php echo !empty($np_state['all']) ? ' checked' : ''; ?>>
                            <span class="toggle-track"></span>
                        </label>
                        <div>
                            <div class="sett-notif-title">All notifications</div>
                            <div class="sett-notif-sub">Turn this off to stop every alert, pop-up and email listed below</div>
                        </div>
                    </div>
                    <?php foreach ($np_defs as $np_key => $np_def): ?>
                    <div class="sett-notif-row sett-notif-row--child">
                        <label class="toggle-sw">
                            <input type="checkbox" data-notif-pref="<?php echo htmlspecialchars($np_key); ?>" data-notif-label="<?php echo htmlspecialchars($np_def['label']); ?>" aria-label="<?php echo htmlspecialchars($np_def['label']); ?>"<?php echo !empty($np_state[$np_key]) ? ' checked' : ''; ?><?php echo empty($np_state['all']) ? ' disabled' : ''; ?>>
                            <span class="toggle-track"></span>
                        </label>
                        <div>
                            <div class="sett-notif-title"><?php echo htmlspecialchars($np_def['label']); ?></div>
                            <div class="sett-notif-sub"><?php echo htmlspecialchars($np_def['desc']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
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

    <?php if ($is_super_admin): ?>

        <?php require __DIR__ . '/admin-arbitration-panel.php'; /* Arbitration rules (Super Admin only, UI only) */ ?>

    <?php endif; ?>

    <!-- ── HELP & FAQ (was the standalone Help Center overlay) ─── -->
    <?php require __DIR__ . '/../../service-survey/views/admin-ratings-panel.php'; /* Service Ratings (Admin + Super Admin) */ ?>

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
                                    The <strong>Dashboard</strong> shows what needs attention right now. The <strong>Activity</strong> tab is the full timeline of decisions and changes, and who made them. You can filter it by type (Requests, Facilities, Equipment, System, Sign-ins), by person, and by date, and export it. The bell on the Dashboard opens your Notifications. Choose which alerts you get (new requests, overdue items, stock alerts, facility issues and security emails), or switch them all off, in <strong>Settings → Preferences</strong>.
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
                                        <strong>Imports</strong> (in the sidebar, under <strong>Data</strong>) is locked behind your password. Use it to bring in:<br>
                                        <strong>Equipment Inventory</strong> (Google Sheet): paste the link of your equipment sheet (shared as &ldquo;Anyone with the link can view&rdquo;), press <strong>Sync now</strong>, and manage the equipment categories on the same card.<br>
                                        <strong>Rooms Assigned</strong> (XLSX or CSV): for importing which rooms are assigned to which faculty or class. This card is a placeholder and is not available yet.<br>
                                        <strong>Faculty Registry</strong> (PDF or XLSX): used to confirm a faculty member when you create their account.<br><br>
                                        For the Faculty Registry and Rooms Assigned, choose <strong>Merge</strong> to add new entries and update existing ones, or <strong>Replace</strong> to remove everyone listed first. Files can be up to 10 MB, and the original file of the latest import is kept.
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