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
                        <div id="ps-rr-student-id-row" hidden><span>Student ID</span><strong id="ps-rr-student-id">—</strong></div>
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

                <!-- Shown for Waiting requests. Open the attached request letter (if any) above, then decide. -->
                <section class="ps-drawer-sec" id="ps-rr-review-sec" hidden>
                    <h4>Your decision</h4>
                    <label class="ps-drawer-muted" for="ps-rr-review-reason">Reason (at least 5 characters). It is saved with the decision.</label>
                    <textarea class="ps-rr-reason" id="ps-rr-review-reason" rows="3" maxlength="500"></textarea>
                    <div class="ps-rr-review-err" id="ps-rr-review-err" role="alert" hidden></div>
                </section>
            </div>
            <div class="ps-drawer-foot">
                <button class="ps-btn ps-btn--ghost" data-action="ps-close-modal" data-modal="ps-room-drawer">Close</button>
                <button class="ps-btn ps-btn--danger" type="button" id="ps-rr-cancel-btn" data-rr-cancel hidden>
                    <span class="material-symbols-outlined">cancel</span> Cancel reservation
                </button>
                <button class="ps-btn ps-btn--danger" type="button" data-rr-decide="Declined" hidden>
                    <span class="material-symbols-outlined">close</span> Decline
                </button>
                <button class="ps-btn ps-btn--success" type="button" data-rr-decide="Approved" hidden>
                    <span class="material-symbols-outlined">check</span> Approve
                </button>
            </div>
        </aside>
    </div>

<?php if ($is_super_admin): ?>
    <!-- ================================================================
         MODAL: DELETE ALL RESERVATIONS  (Super Admin only)
    ================================================================ -->
    <div class="ps-modal-backdrop" id="rr-wipe-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--danger">
                    <span class="material-symbols-outlined">delete_forever</span>
                </div>
                <h3>Delete all reservations?</h3>
                <button type="button" class="ps-modal-close" id="rr-wipe-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <p class="fac-reg-auth-lead">Every reservation record will be permanently deleted. This cannot be undone.</p>
                <ul class="eq-wipe-summary" id="rr-wipe-summary" aria-live="polite">
                    <li class="eq-wipe-loading">Counting records…</li>
                </ul>
                <label class="fac-del-pw-label" for="rr-wipe-pw">Your password</label>
                <div class="fac-pw-wrap">
                    <input type="password" id="rr-wipe-pw" class="form-control-custom"
                        maxlength="128" autocomplete="current-password" placeholder="Enter your password to confirm">
                    <button type="button" class="eq-wipe-pw-toggle" id="rr-wipe-pw-toggle" aria-label="Show or hide password">
                        <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                    </button>
                </div>
                <p class="fac-del-error hidden" id="rr-wipe-error" role="alert"></p>
            </div>
            <div class="ps-modal-foot">
                <button type="button" class="ps-btn ps-btn--ghost" id="rr-wipe-cancel">Cancel</button>
                <button type="button" class="ps-btn ps-btn--danger" id="rr-wipe-confirm" disabled>
                    <span class="material-symbols-outlined">delete_forever</span> Delete everything
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>
