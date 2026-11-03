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
                <div class="form-group">
                    <label for="fac-edit-email">PUPSync email</label>
                    <input type="email" id="fac-edit-email" class="form-control-custom" placeholder="email@example.com">
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label for="fac-edit-backup">Backup email <span class="fac-edit-opt">(optional)</span></label>
                    <input type="email" id="fac-edit-backup" class="form-control-custom" placeholder="backup@gmail.com">
                </div>

                <!-- Organization: org picker + org borrowing only appear for advisers -->
                <div class="fac-opt-card">
                    <label class="fac-opt-row" for="fac-edit-adviser">
                        <span class="fac-opt-text">
                            <span class="fac-opt-title">Organization adviser</span>
                            <span class="fac-opt-sub">Gets organization borrowing automatically</span>
                        </span>
                        <input type="checkbox" id="fac-edit-adviser" class="faculty-toggle-input">
                        <span class="faculty-toggle-track"></span>
                    </label>

                    <div class="fac-opt-extra" id="fac-edit-org-group" style="display:none;">
                        <div class="form-group">
                            <label for="fac-edit-org">Organization</label>
                            <?php
                            $orgs_grouped     = orgs_load_grouped($conn);
                            $orgs_adviser_map = $orgs_adviser_map ?? orgs_adviser_map($conn);
                            $orgs_total = count($orgs_grouped[ORG_CATEGORY_ACADEMIC])
                                + count($orgs_grouped[ORG_CATEGORY_NON_ACADEMIC])
                                + count($orgs_grouped['']);
                            if ($orgs_total > 0): ?>
                                <select id="fac-edit-org" class="form-control-custom"
                                    data-advisers="<?= htmlspecialchars(json_encode($orgs_adviser_map, JSON_FORCE_OBJECT), ENT_QUOTES, 'UTF-8') ?>">
                                    <option value="">&#8212; Select Organization &#8212;</option>
                                    <?= orgs_render_options($orgs_grouped) ?>
                                </select>
                            <?php else: ?>
                                <select id="fac-edit-org" class="form-control-custom" disabled>
                                    <option value="">&#8212; Organizations unavailable &#8212;</option>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
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


    <!-- ================================================================
     MODALS: VIEW FACULTY REGISTRY
     Step 1 (auth)  -> the Super Admin re-enters their own password
     Step 2 (list)  -> read-only list of the imported registry entries
    ================================================================ -->
    <div class="ps-modal-backdrop" id="fac-reg-auth-modal">
        <div class="ps-modal ps-modal--sm">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">lock</span>
                </div>
                <h3 id="fac-reg-auth-title">View Faculty Registry</h3>
                <button class="ps-modal-close" id="fac-reg-auth-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body">
                <p class="fac-reg-auth-lead" id="fac-reg-auth-lead">The registry holds every faculty ID and name. Enter your password to open it.</p>
                <label class="fac-del-pw-label" for="fac-reg-auth-password">Your password</label>
                <div class="fac-pw-wrap">
                    <input type="password" id="fac-reg-auth-password" class="form-control-custom"
                        maxlength="128" autocomplete="current-password" placeholder="Your password">
                    <button type="button" class="fac-pw-toggle"
                        data-target="fac-reg-auth-password" aria-label="Toggle password">
                        <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                    </button>
                </div>
                <p class="fac-del-error hidden" id="fac-reg-auth-error" role="alert"></p>
            </div>
            <div class="ps-modal-foot">
                <button class="ps-btn ps-btn--ghost" id="fac-reg-auth-cancel">Cancel</button>
                <button class="ps-btn ps-btn--primary" id="fac-reg-auth-confirm">
                    <span class="material-symbols-outlined">lock_open</span> Unlock
                </button>
            </div>
        </div>
    </div>

    <div class="ps-modal-backdrop" id="fac-reg-list-modal">
        <div class="ps-modal ps-modal--lg fac-reg-list-modal">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">badge</span>
                </div>
                <h3>Faculty Registry <span class="fac-count-badge" id="fac-reg-list-count"></span></h3>
                <button class="ps-modal-close" id="fac-reg-list-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body fac-reg-list-body">
                <div class="fac-search fac-reg-list-search">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text" id="fac-reg-list-search" class="form-control-custom"
                        placeholder="Search name, ID or department" autocomplete="off">
                </div>
                <div class="tbl-wrap fac-reg-list-wrap">
                    <table class="admin-table fac-reg-list-table">
                        <thead>
                            <tr>
                                <th style="width:44px">#</th>
                                <th>Faculty ID</th>
                                <th>Name</th>
                                <th>Department</th>
                            </tr>
                        </thead>
                        <tbody id="fac-reg-list-tbody"></tbody>
                    </table>
                    <p class="fac-reg-list-empty hidden" id="fac-reg-list-empty">No matching entries.</p>
                </div>
            </div>
            <div class="ps-modal-foot fac-reg-list-foot">
                <span class="fac-reg-list-meta" id="fac-reg-list-meta"></span>
                <button class="ps-btn ps-btn--ghost" id="fac-reg-list-done">Close</button>
            </div>
        </div>
    </div>

    <!-- ================================================================
     MODAL: IMPORTED FILES
     Opened after the password step above. Shows the file of the latest import into the
     Faculty Registry (older ones are replaced) with View (PDF, in the browser) and Download.
     The files themselves come from get-faculty-registry-file.php.
    ================================================================ -->
    <div class="ps-modal-backdrop" id="fac-reg-files-modal">
        <div class="ps-modal ps-modal--lg fac-reg-files-modal">
            <div class="ps-modal-head">
                <div class="ps-modal-head-icon ps-mhi--maroon">
                    <span class="material-symbols-outlined">folder_open</span>
                </div>
                <h3>Imported Files <span class="fac-count-badge" id="fac-reg-files-total"></span></h3>
                <button class="ps-modal-close" id="fac-reg-files-close" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="ps-modal-body fac-reg-files-body">
                <p class="fac-reg-files-note">Only the file from the most recent import is kept here, exactly as it was uploaded. Importing a new file replaces the previous one.</p>
                <p class="fac-del-error hidden" id="fac-reg-files-alert" role="alert"></p>
                <ul class="fac-files-list" id="fac-reg-files-list"></ul>
                <p class="fac-reg-list-empty hidden" id="fac-reg-files-empty">No file on record yet. The file you import will appear here.</p>
            </div>
            <div class="ps-modal-foot fac-reg-list-foot">
                <span class="fac-reg-list-meta" id="fac-reg-files-meta"></span>
                <button class="ps-btn ps-btn--ghost" id="fac-reg-files-done">Close</button>
            </div>
        </div>
    </div>