    <!-- DRAWER: EQUIPMENT LOAN DETAILS (Equipment > On Loan) -->
    <div class="ps-modal-backdrop ps-drawer-backdrop" id="ps-loan-detail-drawer">
        <aside class="ps-drawer" role="dialog" aria-modal="true" aria-labelledby="ps-loan-detail-title">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">assignment</span>
                </div>
                <h3 id="ps-loan-detail-title">Loan <span id="ps-loan-detail-number">#0000</span></h3>
                <button type="button" class="ps-modal-close" data-action="ps-close-modal"
                    data-modal="ps-loan-detail-drawer" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-drawer-body">
                <div class="ps-drawer-status">
                    <span class="ps-badge ps-badge--dot ps-badge--active" id="ps-loan-detail-status">Active</span>
                    <span class="ps-drawer-muted">Submitted <span id="ps-loan-detail-submitted">—</span></span>
                </div>

                <section class="ps-drawer-sec">
                    <h4>Loan</h4>
                    <div class="ps-kv">
                        <div><span>Equipment</span><strong id="ps-loan-detail-equipment">—</strong></div>
                        <div><span>Quantity</span><strong id="ps-loan-detail-quantity">—</strong></div>
                        <div><span>Issued</span><strong id="ps-loan-detail-issued">—</strong></div>
                        <div><span>Due</span><strong id="ps-loan-detail-due">—</strong></div>
                        <div id="ps-loan-detail-returned-section" hidden>
                            <span>Returned</span><strong id="ps-loan-detail-returned">—</strong>
                        </div>
                        <div><span>Condition</span><strong id="ps-loan-detail-condition">—</strong></div>
                        <div><span>Facility</span><strong id="ps-loan-detail-room">—</strong></div>
                        <div><span>Instructor / Assigned to</span><strong id="ps-loan-detail-instructor">—</strong></div>
                    </div>
                </section>

                <section class="ps-drawer-sec">
                    <h4>Borrower</h4>
                    <div class="ps-kv">
                        <div><span>Name</span><strong id="ps-loan-detail-borrower">—</strong></div>
                        <div><span>Type</span><strong id="ps-loan-detail-type">—</strong></div>
                        <div><span>ID</span><strong id="ps-loan-detail-id-number">—</strong></div>
                    </div>
                </section>

                <section class="ps-drawer-sec">
                    <h4>Notes</h4>
                    <div class="ps-drawer-note" id="ps-loan-detail-note">—</div>
                </section>

                <section class="ps-drawer-sec">
                    <h4>Handled by</h4>
                    <div class="ps-drawer-note" id="ps-loan-detail-handled-by">—</div>
                </section>
            </div>
            <div class="ps-drawer-foot">
                <button type="button" class="ps-btn ps-btn--ghost" data-action="ps-close-modal"
                    data-modal="ps-loan-detail-drawer">Close</button>
            </div>
        </aside>
    </div>

    <?php if ($is_super_admin): ?>
        <!-- Permanent-delete confirmation (Super Admin only). Direct <body> child on purpose. -->
        <div id="roomDeleteConfirm" class="hidden" role="dialog" aria-modal="true" aria-labelledby="rdc-title">
            <div class="rdc-card">
                <div class="rdc-head">
                    <div class="rdc-icon"><span class="material-symbols-outlined">delete_forever</span></div>
                    <h3 id="rdc-title">Delete facility permanently?</h3>
                </div>
                <div class="rdc-body">
                    <p class="rdc-room" id="rdc-room-name"></p>
                    <p>This cannot be undone. The facility and the following will be removed for good:</p>
                    <ul class="rdc-list">
                        <li id="rdc-resv">No reservation records</li>
                        <li>Its issue reports and waitlist entries</li>
                    </ul>
                    <p class="rdc-note">Equipment requests that mention this facility are kept.</p>
                    <label class="rdc-check">
                        <input type="checkbox" id="rdc-ack">
                        <span>I understand this cannot be undone</span>
                    </label>
                </div>
                <div class="rdc-foot">
                    <button type="button" class="rdc-btn rdc-btn-ghost" data-action="close-room-delete">Cancel</button>
                    <button type="button" class="rdc-btn rdc-btn-danger" id="rdc-confirm" disabled>
                        <span class="material-symbols-outlined">delete_forever</span>
                        Delete permanently
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($is_super_admin): ?>
        <!-- ================================================================
         MODAL: DELETE THE WHOLE ON LOAN LIST  (Super Admin only)
         Shows exactly what will go (counted by the server), then asks for the
         Super Admin's own password. Driven by the "Delete All" block in
         admin-dashboard.js -> equipment-booking/api/delete-all-loans.php
        ================================================================ -->
        <div class="ps-modal-backdrop" id="eq-loan-wipe-modal">
            <div class="ps-modal ps-modal--sm">
                <div class="ps-modal-head">
                    <div class="ps-modal-head-icon ps-mhi--danger">
                        <span class="material-symbols-outlined">delete_forever</span>
                    </div>
                    <h3>Delete the entire loan list?</h3>
                    <button type="button" class="ps-modal-close" id="eq-loan-wipe-close" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="ps-modal-body">
                    <p class="fac-reg-auth-lead">Every record in <strong>All Loans</strong> will be permanently deleted. This cannot be undone.</p>
                    <ul class="eq-wipe-summary" id="eq-loan-wipe-summary" aria-live="polite">
                        <li class="eq-wipe-loading">Counting records…</li>
                    </ul>
                    <p class="eq-wipe-note hidden" id="eq-loan-wipe-note"></p>
                    <label class="fac-del-pw-label" for="eq-loan-wipe-pw">Your password</label>
                    <div class="fac-pw-wrap">
                        <input type="password" id="eq-loan-wipe-pw" class="form-control-custom"
                            maxlength="128" autocomplete="current-password" placeholder="Enter your password to confirm">
                        <button type="button" class="eq-wipe-pw-toggle" id="eq-loan-wipe-pw-toggle" aria-label="Show or hide password">
                            <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                        </button>
                    </div>
                    <p class="fac-del-error hidden" id="eq-loan-wipe-error" role="alert"></p>
                </div>
                <div class="ps-modal-foot">
                    <button type="button" class="ps-btn ps-btn--ghost" id="eq-loan-wipe-cancel">Cancel</button>
                    <button type="button" class="ps-btn ps-btn--danger" id="eq-loan-wipe-confirm" disabled>
                        <span class="material-symbols-outlined">delete_forever</span> Delete everything
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
