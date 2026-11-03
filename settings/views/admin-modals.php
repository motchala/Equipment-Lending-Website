    <div class="modal-overlay" id="changePassModal" style="display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.6); 
            z-index: 99999; 
            align-items: center; 
            justify-content: center;">
        <div class="modal-backdrop" data-action="close-change-pass" style="position: absolute; inset: 0;"></div>
        <div class="eq-card form-card"
            style="position: relative; width: 100%; max-width: 400px; margin: 20px; z-index: 100000;">
            <div class="form-card-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    Change Password
                </h2>
                <button type="button" class="btn-close-custom" data-action="close-change-pass">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="form-card-body">
                <div id="cp-alert"
                    style="display:none; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.85rem; font-weight: 500;">
                </div>

                <form id="changePasswordForm">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="cp-current-password">Current Password</label>
                        <div class="fac-pw-wrap">
                            <input type="password" id="cp-current-password" name="current_password"
                                class="form-control-custom" required>
                            <button type="button" class="fac-pw-toggle" data-target="cp-current-password"
                                aria-label="Toggle password">
                                <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="cp-new-password">New Password</label>
                        <div class="fac-pw-wrap">
                            <input type="password" id="cp-new-password" name="new_password"
                                class="form-control-custom" minlength="4" required>
                            <button type="button" class="fac-pw-toggle" data-target="cp-new-password,cp-confirm-password"
                                aria-label="Toggle password">
                                <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="cp-confirm-password">Confirm New Password</label>
                        <div class="fac-pw-wrap">
                            <input type="password" id="cp-confirm-password" name="confirm_password"
                                class="form-control-custom" minlength="4" required>
                            <button type="button" class="fac-pw-toggle" data-target="cp-new-password,cp-confirm-password"
                                aria-label="Toggle password">
                                <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                            </button>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                        <button type="button" class="btn-cancel-acc" data-action="close-change-pass"
                            style="padding: 8px 16px; width: auto;">Cancel</button>
                        <button type="submit" class="btn-submit-form"
                            style="margin-top: 0; width: auto; padding: 8px 16px;">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

