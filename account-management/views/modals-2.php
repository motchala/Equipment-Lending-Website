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
                <button class="ps-btn fac-btn-delete" id="fac-edit-delete-btn" type="button">
                    <span class="material-symbols-outlined">person_remove</span> Delete
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
     MODAL: DELETE FACULTY CONFIRM
     One message at a time: "checking" -> "confirm" OR "blocked" (override).
    ================================================================ -->
    <div class="ps-modal-backdrop" id="fac-delete-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--danger">
                    <span class="material-symbols-outlined">person_remove</span>
                </div>
                <h3>Delete Faculty Account</h3>
                <button class="ps-modal-close" id="fac-delete-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <p class="fac-del-lead">Permanently delete <strong id="fac-delete-name">this faculty member</strong>?</p>

                <!-- View: checking -->
                <p class="fac-del-checking" id="fac-delete-view-checking">
                    <span class="material-symbols-outlined">progress_activity</span> Checking account&hellip;
                </p>

                <!-- View: confirm (nothing blocking) -->
                <p class="fac-del-note hidden" id="fac-delete-view-confirm">This can&rsquo;t be undone. Borrowing history is kept.</p>

                <!-- View: blocked (open bookings) -> Super Admin may override with their password -->
                <div class="hidden" id="fac-delete-view-blocked">
                    <div class="fac-del-warn">
                        <div class="fac-del-warn-title">
                            <span class="material-symbols-outlined">warning</span> Open bookings found
                        </div>
                        <ul class="fac-del-list" id="fac-delete-list"></ul>
                    </div>
                    <label class="fac-del-pw-label" for="fac-delete-password">Enter your password to delete anyway</label>
                    <div class="fac-pw-wrap">
                        <input type="password" id="fac-delete-password" class="form-control-custom"
                            maxlength="128" autocomplete="current-password" placeholder="Your password">
                        <button type="button" class="fac-pw-toggle"
                            data-target="fac-delete-password" aria-label="Toggle password">
                            <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                        </button>
                    </div>
                </div>

                <p class="fac-del-error hidden" id="fac-delete-error" role="alert"></p>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" id="fac-delete-cancel">Cancel</button>
                <button class="ps-btn ps-btn--danger" id="fac-delete-confirm" disabled>
                    <span class="material-symbols-outlined">delete</span> <span id="fac-delete-confirm-label">Delete Account</span>
                </button>
            </div>
        </div>
    </div>
