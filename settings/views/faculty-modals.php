    <!-- Email Verify Modal -->
    <div class="modal-backdrop" id="emailVerifyModal" style="display:none;" role="dialog" aria-modal="true">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined"
                        style="font-size:18px;vertical-align:middle;margin-right:8px;">mail</span>Verify Your Email</h3>
                <button class="modal-close-btn" data-action="close-email-verify-modal" aria-label="Close"><span
                        class="material-symbols-outlined">close</span></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:1rem;color:var(--color-on-surface-variant);font-size:0.9rem;">For security,
                    verify your email address before changing your password.</p>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" id="verifyEmailInput" class="form-input"
                        placeholder="Enter your registered email" autocomplete="email">
                </div>
                <p class="modal-error" id="emailVerifyError" style="display:none;"></p>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel-acc" data-action="close-email-verify-modal">Cancel</button>
                <button class="btn-save-acc" id="emailVerifyBtn" data-action="submit-email-verify">
                    <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Verify
                    &amp; Continue
                </button>
            </div>
        </div>
    </div>

    <!-- Backup Email Modal -->
    <div class="modal-backdrop" id="backupEmailModal" style="display:none;" role="dialog" aria-modal="true">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined"
                        style="font-size:18px;vertical-align:middle;margin-right:8px;">alternate_email</span>Backup
                    Email</h3>
                <button class="modal-close-btn" data-action="close-backup-email-modal" aria-label="Close"><span
                        class="material-symbols-outlined">close</span></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:1rem;color:var(--color-on-surface-variant);font-size:0.9rem;">Add a backup email
                    for account recovery and important notifications.</p>
                <div class="form-group">
                    <label class="form-label">Backup Email Address</label>
                    <input type="email" id="backupEmailInput" class="form-input" placeholder="Enter backup email"
                        autocomplete="email" value="<?php echo htmlspecialchars($db_backup_email); ?>">
                </div>
                <p class="modal-error" id="backupEmailError" style="display:none;"></p>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel-acc" data-action="close-backup-email-modal">Cancel</button>
                <button class="btn-save-acc" id="backupEmailSaveBtn" data-action="save-backup-email">
                    <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Save
                    Backup Email
                </button>
            </div>
        </div>
    </div>

    <!-- Faculty ID Modal: set once, permanent -->
    <div class="modal-backdrop" id="facultyIdModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="facultyIdTitle">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="facultyIdTitle"><span class="material-symbols-outlined"
                        style="font-size:18px;vertical-align:middle;margin-right:8px;">badge</span>Faculty ID</h3>
                <button class="modal-close-btn" data-action="close-faculty-id-modal" aria-label="Close"><span
                        class="material-symbols-outlined">close</span></button>
            </div>

            <!-- Step 1: enter -->
            <div id="facultyIdStepEnter">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="facultyIdInput">Your Faculty ID</label>
                        <input type="text" id="facultyIdInput" class="form-input" placeholder="e.g. 2023-00123-BN-0"
                            maxlength="30" autocomplete="off" autocapitalize="characters" spellcheck="false">
                    </div>
                    <p class="modal-error" id="facultyIdError" style="display:none;"></p>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel-acc" data-action="close-faculty-id-modal">Cancel</button>
                    <button class="btn-save-acc" id="facultyIdContinueBtn" data-action="faculty-id-continue">Continue</button>
                </div>
            </div>

            <!-- Step 2: confirm (permanent) -->
            <div id="facultyIdStepConfirm" style="display:none;">
                <div class="modal-body">
                    <div class="fid-confirm-id" id="facultyIdConfirmValue"></div>
                    <div class="warning-box">
                        <span class="material-symbols-outlined" style="color:#856404;flex-shrink:0;">lock</span>
                        <div><strong style="color:#856404;display:block;margin-bottom:2px;">This is permanent</strong>
                            <p style="color:#856404;margin:0;font-size:0.875rem;">You can&rsquo;t change your Faculty ID after saving it, and neither can an admin.</p>
                        </div>
                    </div>
                    <p class="modal-error" id="facultyIdConfirmError" style="display:none;"></p>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel-acc" data-action="faculty-id-back">Go Back</button>
                    <button class="btn-save-acc" id="facultyIdConfirmBtn" data-action="faculty-id-confirm">
                        <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">lock</span>Confirm &amp; Lock ID
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal-backdrop" id="pwModal" style="display:none;" role="dialog" aria-modal="true">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined"
                        style="font-size:18px;vertical-align:middle;margin-right:8px;">lock</span>Change Password</h3>
                <button class="modal-close-btn" data-action="close-pw-modal" aria-label="Close"><span
                        class="material-symbols-outlined">close</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="pwCurrent" class="form-input"
                            placeholder="Enter your current password" autocomplete="current-password">
                        <button type="button" class="pw-toggle-btn" data-pw-target="pwCurrent"
                            aria-label="Toggle visibility">
                            <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="pwNew" class="form-input" placeholder="At least 6 characters"
                            autocomplete="new-password">
                        <button type="button" class="pw-toggle-btn" data-pw-target="pwNew"
                            aria-label="Toggle visibility">
                            <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                        </button>
                    </div>
                    <div class="pw-strength-bar" id="pwStrengthBar" style="display:none;">
                        <div class="pw-strength-fill" id="pwStrengthFill"></div>
                    </div>
                    <span class="pw-strength-label" id="pwStrengthLabel"></span>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="pwConfirm" class="form-input" placeholder="Repeat your new password"
                            autocomplete="new-password">
                        <button type="button" class="pw-toggle-btn" data-pw-target="pwConfirm"
                            aria-label="Toggle visibility">
                            <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
                        </button>
                    </div>
                </div>
                <p class="modal-error" id="pwModalError" style="display:none;"></p>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel-acc" data-action="close-pw-modal">Cancel</button>
                <button class="btn-save-acc" id="pwSubmitBtn" data-action="submit-pw-change">
                    <span class="material-symbols-outlined" style="font-size:14px;margin-right:4px;">check</span>Update
                    Password
                </button>
            </div>
        </div>
    </div>

