
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
                    <header class="dash-head admin-screen-header">
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







