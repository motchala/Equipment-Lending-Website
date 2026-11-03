    <!-- ============================================================
         MODAL: NOTIFICATIONS
         Fully dynamic — every card below is rendered from $notifications
         (notifications/core/admin-data.php), which is built
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


