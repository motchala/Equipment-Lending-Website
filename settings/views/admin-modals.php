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

    <!-- ================================================================
     MODALS: SETTINGS -> IMPORTS
     imp-auth-modal   the password step (opened from the lock)
     imp-list-modal   read-only list of what was imported
     imp-files-modal  every original file that was imported (View / Download)
    ================================================================ -->
    <div class="ps-modal-backdrop" id="imp-auth-modal">
        <div class="ps-modal ps-modal--sm imp-auth">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">lock</span>
                </div>
                <h3>Unlock Imports</h3>
                <button class="ps-modal-close" id="imp-auth-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <p class="imp-auth-lead">Enter your password to view and make imports, and to open the files that were imported. It locks again after 10 minutes without use.</p>
                <label class="fac-del-pw-label" for="imp-auth-password">Your password</label>
                <div class="fac-pw-wrap">
                    <input type="password" id="imp-auth-password" class="form-control-custom"
                        maxlength="128" autocomplete="current-password" placeholder="Your password">
                    <button type="button" class="fac-pw-toggle"
                        data-target="imp-auth-password" aria-label="Toggle password">
                        <span class="material-symbols-outlined">visibility</span>
                    </button>
                </div>
                <p class="fac-del-error hidden" id="imp-auth-error" role="alert"></p>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" id="imp-auth-cancel">Cancel</button>
                <button class="ps-btn ps-btn--primary" id="imp-auth-confirm">
                    <span class="material-symbols-outlined">lock_open</span> Unlock
                </button>
            </div>
        </div>
    </div>

    <div class="ps-modal-backdrop" id="imp-list-modal">
        <div class="ps-modal ps-modal--lg imp-list-modal">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined" id="imp-list-icon">table_view</span>
                </div>
                <h3><span id="imp-list-title">Imported list</span> <span class="fac-count-badge" id="imp-list-count"></span></h3>
                <button class="ps-modal-close" id="imp-list-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body imp-list-body">
                <div class="imp-search">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text" id="imp-list-search" placeholder="Search by name or ID…" autocomplete="off">
                </div>
                <div class="imp-table-wrap imp-table-wrap--tall">
                    <table class="imp-table">
                        <thead id="imp-list-thead"></thead>
                        <tbody id="imp-list-tbody"></tbody>
                    </table>
                    <p class="imp-empty hidden" id="imp-list-empty">No matching entries.</p>
                </div>
            </div>
            <div class="ps-modal-foot imp-modal-foot">
                <span class="imp-modal-meta" id="imp-list-meta"></span>
                <button class="ps-btn ps-btn--ghost" id="imp-list-done">Close</button>
            </div>
        </div>
    </div>

    <div class="ps-modal-backdrop" id="imp-files-modal">
        <div class="ps-modal ps-modal--lg imp-files-modal">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">folder_open</span>
                </div>
                <h3><span id="imp-files-title">Original file</span> <span class="fac-count-badge" id="imp-files-total"></span></h3>
                <button class="ps-modal-close" id="imp-files-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body imp-files-body">
                <p class="imp-files-note">Only the file from the most recent import is kept here, exactly as it was uploaded. Importing a new file replaces it.</p>
                <p class="fac-del-error hidden" id="imp-files-alert" role="alert"></p>
                <ul class="imp-files-list" id="imp-files-list"></ul>
                <p class="imp-empty hidden" id="imp-files-empty">No file on record yet. The file you import will appear here.</p>
            </div>
            <div class="ps-modal-foot imp-modal-foot">
                <span class="imp-modal-meta" id="imp-files-meta"></span>
                <button class="ps-btn ps-btn--ghost" id="imp-files-done">Close</button>
            </div>
        </div>
    </div>
