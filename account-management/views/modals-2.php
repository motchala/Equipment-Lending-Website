    <!-- ================================================================
     MODAL: EDIT FACULTY ACCOUNT
    ================================================================ -->
    <div class="ps-modal-backdrop" id="fac-edit-modal">
        <div class="ps-modal">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">manage_accounts</span>
                </div>
                <h3>Edit Faculty Account</h3>
                <button class="ps-modal-close" id="fac-edit-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">

                <!-- Who is being edited -->
                <div class="fac-edit-id">
                    <div class="fac-edit-avatar" id="fac-edit-avatar">F</div>
                    <div>
                        <div class="fac-edit-id-name" id="fac-edit-name-display">&#8212;</div>
                        <div class="fac-edit-id-meta" id="fac-edit-meta-display">&#8212;</div>
                    </div>
                </div>

                <!-- Account details -->
                <div class="fac-edit-section">Account details</div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="fac-edit-first">First name</label>
                        <input type="text" id="fac-edit-first" class="form-control-custom" placeholder="First">
                    </div>
                    <div class="form-group">
                        <label for="fac-edit-last">Last name</label>
                        <input type="text" id="fac-edit-last" class="form-control-custom" placeholder="Last">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label for="fac-edit-email">PUPSync email</label>
                    <input type="email" id="fac-edit-email" class="form-control-custom" placeholder="email@example.com">
                </div>

                <div id="fac-edit-alert" class="alert-banner hidden" role="alert" style="margin-bottom:0"></div>
            </div>
            <div class="ps-modal-foot fac-foot-split">
                <!-- One of these two shows, depending on whether the account is active (JS toggles .hidden) -->
                <button class="ps-btn fac-btn-deactivate" id="fac-edit-deactivate-btn" type="button">
                    <span class="material-symbols-outlined">person_off</span> Deactivate
                </button>
                <button class="ps-btn fac-btn-reactivate hidden" id="fac-edit-reactivate-btn" type="button">
                    <span class="material-symbols-outlined">how_to_reg</span> <span id="fac-edit-reactivate-label">Reactivate</span>
                </button>
                <div class="fac-foot-actions">
                    <button class="ps-btn ps-btn--ghost" id="fac-edit-cancel">Cancel</button>
                    <button class="ps-btn ps-btn--primary" id="fac-edit-save">
                        <span class="material-symbols-outlined">save</span> Save
                    </button>
                </div>
            </div>
        </div>
    </div>


    <!-- ================================================================
     MODAL: DEACTIVATE FACULTY CONFIRM
     One message at a time: "checking" -> "confirm" OR "blocked" (override).
    ================================================================ -->
    <div class="ps-modal-backdrop" id="fac-deactivate-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--danger">
                    <span class="material-symbols-outlined">person_off</span>
                </div>
                <h3>Deactivate Faculty Account</h3>
                <button class="ps-modal-close" id="fac-deactivate-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <p class="fac-del-lead">Deactivate <strong id="fac-deactivate-name">this faculty member</strong>?</p>

                <!-- View: checking -->
                <p class="fac-del-checking" id="fac-deactivate-view-checking">
                    <span class="material-symbols-outlined">progress_activity</span> Checking account&hellip;
                </p>

                <!-- View: confirm (nothing blocking) -->
                <p class="fac-del-note hidden" id="fac-deactivate-view-confirm">They won&rsquo;t be able to sign in. Their account, data and borrowing history are all kept, and you can reactivate the account at any time.</p>

                <!-- View: blocked (open bookings) -> Super Admin may override with their password -->
                <div class="hidden" id="fac-deactivate-view-blocked">
                    <div class="fac-del-warn">
                        <div class="fac-del-warn-title">
                            <span class="material-symbols-outlined">warning</span> Open bookings found
                        </div>
                        <ul class="fac-del-list" id="fac-deactivate-list"></ul>
                    </div>
                    <label class="fac-del-pw-label" for="fac-deactivate-password">Enter your password to deactivate anyway</label>
                    <div class="fac-pw-wrap">
                        <input type="password" id="fac-deactivate-password" class="form-control-custom"
                            maxlength="128" autocomplete="current-password" placeholder="Your password">
                        <button type="button" class="fac-pw-toggle"
                            data-target="fac-deactivate-password" aria-label="Toggle password">
                            <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                        </button>
                    </div>
                </div>

                <p class="fac-del-error hidden" id="fac-deactivate-error" role="alert"></p>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" id="fac-deactivate-cancel">Cancel</button>
                <button class="ps-btn ps-btn--danger" id="fac-deactivate-confirm" disabled>
                    <span class="material-symbols-outlined">person_off</span> <span id="fac-deactivate-confirm-label">Deactivate Account</span>
                </button>
            </div>
        </div>
    </div>
