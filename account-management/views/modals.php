    <?php if ($is_super_admin): ?>
        <!-- ═══ MANAGE ADMINS — edit / lock / delete (Super Admin only) ═══
         Direct children of <body>, same ps-modal system as the Requests modals.
         Driven by the "Admin Accounts panel" block in admin-dashboard.js. -->

        <!-- MODAL: ADD ADMIN -->
        <div class="ps-modal-backdrop" id="amAddModal">
            <div class="ps-modal">
                <div class="ps-modal-head">
                    <div class="ps-modal-head-icon ps-mhi--maroon">
                        <span class="material-symbols-outlined">person_add</span>
                    </div>
                    <h3>Add Admin</h3>
                    <button type="button" class="ps-modal-close" data-adm-close="amAddModal" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="ps-modal-body">
                    <div id="adm-form-alert" class="alert-banner hidden" role="alert"></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="adm-fullname">Full Name <span class="req-star">*</span></label>
                            <input type="text" id="adm-fullname" class="form-control-custom"
                                placeholder="e.g. Juan dela Cruz" maxlength="255" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="adm-email">Admin Email <span class="req-star">*</span></label>
                            <input type="email" id="adm-email" class="form-control-custom"
                                placeholder="name@admin.edu" maxlength="255" autocomplete="off">
                            <small class="adm-email-hint">Must end in <code>@admin.edu</code></small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="adm-role">Role <span class="req-star">*</span></label>
                        <select id="adm-role" class="form-control-custom">
                            <option value="Admin" selected>Admin</option>
                            <option value="Super Admin">Super Admin</option>
                        </select>
                        <small class="adm-email-hint">Super Admins have full control. Admins have limited access.</small>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="adm-password">Password <span class="req-star">*</span></label>
                            <div class="fac-pw-wrap">
                                <input type="password" id="adm-password" class="form-control-custom"
                                    placeholder="Min. 8 characters" autocomplete="new-password">
                                <button type="button" class="fac-pw-toggle" data-target="adm-password" title="Show/hide password">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="adm-confirm">Confirm Password <span class="req-star">*</span></label>
                            <div class="fac-pw-wrap">
                                <input type="password" id="adm-confirm" class="form-control-custom"
                                    placeholder="Re-enter password" autocomplete="new-password">
                                <button type="button" class="fac-pw-toggle" data-target="adm-confirm" title="Show/hide password">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ps-modal-foot">
                    <button type="button" class="ps-btn ps-btn--ghost" data-adm-close="amAddModal">Cancel</button>
                    <button type="button" class="ps-btn ps-btn--primary" id="adm-submit-btn">
                        <span class="material-symbols-outlined">person_add</span> Create Admin
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: EDIT ADMIN LOGIN DETAILS -->
        <div class="ps-modal-backdrop" id="admEditModal">
            <div class="ps-modal">
                <div class="ps-modal-head">
                    <div class="ps-modal-head-icon ps-mhi--maroon">
                        <span class="material-symbols-outlined">manage_accounts</span>
                    </div>
                    <h3>Edit Admin Login Details</h3>
                    <button type="button" class="ps-modal-close" data-adm-close="admEditModal" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="ps-modal-body">
                    <div id="adm-edit-alert" class="alert-banner hidden" role="alert"></div>
                    <div class="form-group">
                        <label for="adm-edit-name">Full Name <span class="req-star">*</span></label>
                        <input type="text" id="adm-edit-name" class="form-control-custom" maxlength="255" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="adm-edit-email">Admin Email <span class="req-star">*</span></label>
                        <input type="email" id="adm-edit-email" class="form-control-custom" maxlength="255" autocomplete="off">
                        <small class="adm-email-hint">Must end in <code>@admin.edu</code></small>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="adm-edit-pw">New Password</label>
                            <div class="fac-pw-wrap">
                                <input type="password" id="adm-edit-pw" class="form-control-custom"
                                    placeholder="Leave blank to keep" autocomplete="new-password">
                                <button type="button" class="fac-pw-toggle" data-target="adm-edit-pw,adm-edit-pw2" title="Show/hide password">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="adm-edit-pw2">Confirm New Password</label>
                            <div class="fac-pw-wrap">
                                <input type="password" id="adm-edit-pw2" class="form-control-custom"
                                    placeholder="Re-enter new password" autocomplete="new-password">
                                <button type="button" class="fac-pw-toggle" data-target="adm-edit-pw,adm-edit-pw2" title="Show/hide password">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <p class="adm-modal-note">
                        <span class="material-symbols-outlined">info</span>
                        Changing the email or password signs this admin out of every device.
                    </p>
                    <div class="form-group adm-reauth">
                        <label for="adm-edit-reauth">Your password <span class="req-star">*</span></label>
                        <div class="fac-pw-wrap">
                            <input type="password" id="adm-edit-reauth" class="form-control-custom"
                                placeholder="Confirm it's you" autocomplete="current-password">
                            <button type="button" class="fac-pw-toggle" data-target="adm-edit-reauth" title="Show/hide password">
                                <span class="material-symbols-outlined">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="ps-modal-foot">
                    <button type="button" class="ps-btn ps-btn--ghost" data-adm-close="admEditModal">Cancel</button>
                    <button type="button" class="ps-btn ps-btn--primary" id="adm-edit-save">
                        <span class="material-symbols-outlined">save</span> Save Changes
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: LOCK ADMIN ACCOUNT -->
        <div class="ps-modal-backdrop" id="admDormantModal">
            <div class="ps-modal ps-modal--sm">
                <div class="ps-modal-head">
                    <div class="ps-modal-head-icon ps-mhi--warning">
                        <span class="material-symbols-outlined">lock</span>
                    </div>
                    <h3>Lock Account</h3>
                    <button type="button" class="ps-modal-close" data-adm-close="admDormantModal" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="ps-modal-body">
                    <div id="adm-dormant-alert" class="alert-banner hidden" role="alert"></div>
                    <p class="adm-modal-text">
                        <strong id="adm-dormant-name">This admin</strong> keeps their account, but will be signed out and
                        cannot log in until the period ends. You can unlock it any time.
                    </p>
                    <div class="adm-days" role="radiogroup" aria-label="Locked for">
                        <?php foreach (ADMIN_DORMANT_CHOICES as $d): ?>
                            <label class="adm-day-chip">
                                <input type="radio" name="adm-dormant-days" value="<?php echo (int)$d; ?>" <?php echo $d === 7 ? 'checked' : ''; ?>>
                                <span><?php echo (int)$d; ?> day<?php echo $d === 1 ? '' : 's'; ?></span>
                            </label>
                        <?php endforeach; ?>
                        <label class="adm-day-chip">
                            <input type="radio" name="adm-dormant-days" value="custom">
                            <span>Custom</span>
                        </label>
                    </div>
                    <div class="form-group adm-custom-days" id="adm-custom-days" hidden>
                        <label for="adm-dormant-custom">Number of days (1–<?php echo (int)ADMIN_DORMANT_MAX_DAYS; ?>)</label>
                        <input type="number" id="adm-dormant-custom" class="form-control-custom"
                            min="1" max="<?php echo (int)ADMIN_DORMANT_MAX_DAYS; ?>" step="1" inputmode="numeric" placeholder="e.g. 10">
                    </div>
                    <p class="adm-modal-note" id="adm-dormant-until-note">
                        <span class="material-symbols-outlined">event</span>
                        <span id="adm-dormant-until-text"></span>
                    </p>
                </div>
                <div class="ps-modal-foot">
                    <button type="button" class="ps-btn ps-btn--ghost" data-adm-close="admDormantModal">Cancel</button>
                    <button type="button" class="ps-btn ps-btn--primary" id="adm-dormant-save">
                        <span class="material-symbols-outlined">lock</span> Lock Account
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: DELETE ADMIN -->
        <div class="ps-modal-backdrop" id="admDeleteModal">
            <div class="ps-modal ps-modal--sm">
                <div class="ps-modal-head">
                    <div class="ps-modal-head-icon ps-mhi--danger">
                        <span class="material-symbols-outlined">delete</span>
                    </div>
                    <h3>Delete Admin Account</h3>
                    <button type="button" class="ps-modal-close" data-adm-close="admDeleteModal" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="ps-modal-body">
                    <div id="adm-delete-alert" class="alert-banner hidden" role="alert"></div>
                    <p class="adm-modal-text">
                        Delete <strong id="adm-delete-name">this admin</strong>? The account is removed permanently and its
                        seat becomes free for a new admin. This can't be undone.
                    </p>
                    <div class="form-group adm-reauth">
                        <label for="adm-delete-reauth">Your password <span class="req-star">*</span></label>
                        <div class="fac-pw-wrap">
                            <input type="password" id="adm-delete-reauth" class="form-control-custom"
                                placeholder="Confirm it's you" autocomplete="current-password">
                            <button type="button" class="fac-pw-toggle" data-target="adm-delete-reauth" title="Show/hide password">
                                <span class="material-symbols-outlined">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="ps-modal-foot">
                    <button type="button" class="ps-btn ps-btn--ghost" data-adm-close="admDeleteModal">Cancel</button>
                    <button type="button" class="ps-btn ps-btn--danger" id="adm-delete-confirm">
                        <span class="material-symbols-outlined">delete</span> Delete Account
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

