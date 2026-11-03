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

