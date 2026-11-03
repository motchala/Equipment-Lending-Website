    <!-- ================================================================
     MODAL: NOTIFICATIONS  (faculty)
     Same structural pattern as the admin notification modal — opened
     from the account flyout in the sidebar instead of living on its own page.
     Content is scoped to what a faculty member actually gets — live from
     the DB: borrow-request updates, overdue/return reminders, room
     reservations, waitlist, reported room issues and account activity.
================================================================ -->
    <div class="modal-backdrop fnotif-backdrop" id="notifModal" style="display:none;" role="dialog" aria-modal="true"
        aria-labelledby="notifModalTitle">
        <div class="fnotif-box">

            <!-- Head: icon + title + close -->
            <div class="fnotif-head">
                <div class="fnotif-head-icon">
                    <span class="material-symbols-outlined">notifications</span>
                </div>
                <div class="fnotif-head-text">
                    <h3 class="fnotif-head-title" id="notifModalTitle">Notifications</h3>
                    <p class="fnotif-count-text">
                        You have <strong id="unreadCount"><?php echo (int)$notif_count; ?> unread</strong>
                        notification<span id="unreadPlural"><?php echo $notif_count !== 1 ? 's' : ''; ?></span>.
                    </p>
                </div>
                <button class="modal-close-btn" data-action="close-notif-modal" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- Filter pills (counts are filled in live by faculty-dashboard.js) -->
            <div class="fnotif-tabs">
                <button class="notif-tab active" data-notif-filter="all">
                    All <span class="fnotif-pill-count" data-pill="all" hidden>0</span>
                </button>
                <button class="notif-tab" data-notif-filter="unread">
                    Unread <span class="fnotif-pill-count" data-pill="unread" hidden>0</span>
                </button>
                <button class="notif-tab" data-notif-filter="overdue">
                    Overdue <span class="fnotif-pill-count" data-pill="overdue" hidden>0</span>
                </button>
                <button class="notif-tab" data-notif-filter="borrow">
                    Borrow <span class="fnotif-pill-count" data-pill="borrow" hidden>0</span>
                </button>
                <button class="notif-tab" data-notif-filter="room">
                    Rooms <span class="fnotif-pill-count" data-pill="room" hidden>0</span>
                </button>
                <button class="notif-tab" data-notif-filter="system">
                    System <span class="fnotif-pill-count" data-pill="system" hidden>0</span>
                </button>
            </div>

            <!-- Scrollable list — cards are rendered by faculty-dashboard.js -->
            <div class="fnotif-body">
                <div class="notif-card-list" id="notifList"></div>

                <div class="fnotif-empty" id="notifEmptyState" style="display:none;">
                    <span class="material-symbols-outlined">notifications_off</span>
                    <p id="notifEmptyText">Nothing here right now.</p>
                </div>
            </div><!-- /fnotif-body -->

            <!-- Pagination — client-side only for now; keeps the list from
                 rendering everything at once as real notifications grow. -->
            <div class="fnotif-pagination" id="notifPagination" style="display:none;">
                <div class="fnotif-pg-info" id="notifPageInfo"></div>
                <div class="fnotif-pg-controls">
                    <button class="fnotif-pg-btn" id="notifPrevBtn" data-action="notif-page-prev"
                        aria-label="Previous page">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </button>
                    <div class="fnotif-pg-numbers" id="notifPageNumbers"></div>
                    <button class="fnotif-pg-btn" id="notifNextBtn" data-action="notif-page-next"
                        aria-label="Next page">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </button>
                </div>
            </div>
            <!-- Footer — Mark all as read · Select · Delete all (Delete all only shows in select mode) -->
            <div class="fnotif-footer" id="notifFooter">

                <!-- Inline confirmation for bulk deletes -->
                <div class="fnotif-confirm" id="notifConfirm" role="alertdialog" aria-live="assertive" hidden>
                    <span class="material-symbols-outlined">warning</span>
                    <span class="fnotif-confirm-text" id="notifConfirmText"></span>
                    <button type="button" class="btn-cancel-acc fnotif-foot-btn" data-nbar="confirm-cancel">Cancel</button>
                    <button type="button" class="btn-save-acc fnotif-foot-btn fnotif-foot-solid-danger" data-nbar="confirm-ok">Delete</button>
                </div>

                <!-- Selection bar — only while selecting -->
                <div class="fnotif-selbar" id="notifSelBar" hidden>
                    <label class="fnotif-selall">
                        <input type="checkbox" id="notifSelectAll">
                        <span>Select all</span>
                    </label>
                    <span class="fnotif-sel-count" id="notifSelCount" aria-live="polite">0 selected</span>
                    <div class="fnotif-sel-actions">
                        <button type="button" class="btn-cancel-acc fnotif-foot-btn" data-nbar="mark-read" data-needs-sel>
                            <span class="material-symbols-outlined">drafts</span> Read
                        </button>
                        <button type="button" class="btn-cancel-acc fnotif-foot-btn" data-nbar="mark-unread" data-needs-sel>
                            <span class="material-symbols-outlined">mark_email_unread</span> Unread
                        </button>
                        <button type="button" class="btn-cancel-acc fnotif-foot-btn fnotif-foot-danger" data-nbar="delete-selected" data-needs-sel>
                            <span class="material-symbols-outlined">delete</span> Delete<span class="fnotif-sel-word"> selected</span>
                        </button>
                    </div>
                </div>

                <div class="fnotif-footer-main">
                    <button type="button" class="btn-cancel-acc fnotif-foot-btn" data-action="mark-all-read">
                        <span class="material-symbols-outlined">done_all</span> Mark all as read
                    </button>
                    <div class="fnotif-footer-right">
                        <button type="button" class="btn-cancel-acc fnotif-foot-btn fnotif-foot-danger" id="notifDeleteAllBtn" data-nbar="delete-all" hidden>
                            <span class="material-symbols-outlined">delete_sweep</span> Delete all
                        </button>
                        <button type="button" class="btn-save-acc fnotif-foot-btn" id="notifSelectBtn" data-nbar="select" aria-pressed="false">
                            <span class="material-symbols-outlined">checklist</span> <span id="notifSelectLbl">Select</span>
                        </button>
                    </div>
                </div>
            </div><!-- /fnotif-footer -->
        </div><!-- /fnotif-box -->
    </div><!-- /notifModal -->



