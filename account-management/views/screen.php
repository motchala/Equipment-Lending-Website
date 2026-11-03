            <!-- ============================================================
         TAB: FACULTY  (Super Admin only)
    ============================================================ -->
            <?php if ($is_super_admin): ?>
                <div class="tab-panel" id="panel-faculty">

                    <div style="margin-bottom:1.5rem">
                        <h2 style="font-size:1.3rem;font-weight:700;color:var(--text-dark)">Faculty Management</h2>
                        <p style="color:var(--text-light);font-size:12.5px;margin-top:2px">Create, edit, and remove faculty accounts.</p>
                    </div>

                    <?php
                    // Registry status for the upload card
                    $reg_status = ['count' => 0, 'last_date' => '', 'last_by' => ''];
                    $reg_tbl_exists = $conn->query("SHOW TABLES LIKE 'tbl_faculty_registry'")->num_rows > 0;
                    if ($reg_tbl_exists) {
                        $rs = $conn->query("SELECT COUNT(*) AS cnt, MAX(uploaded_at) AS last_at, MAX(uploaded_by) AS last_by FROM tbl_faculty_registry");
                        if ($rs) {
                            $rrow = $rs->fetch_assoc();
                            $reg_status['count']     = (int)($rrow['cnt'] ?? 0);
                            $reg_status['last_date'] = (string)($rrow['last_at'] ?? '');
                            $reg_status['last_by']   = (string)($rrow['last_by'] ?? '');
                        }
                    }

                    // Original files kept for every import (account-management/core/faculty-registry-files.php)
                    $reg_files = ['count' => 0, 'last_name' => ''];
                    if ($conn->query("SHOW TABLES LIKE 'tbl_faculty_registry_files'")->num_rows > 0) {
                        $frs = $conn->query("SELECT COUNT(*) AS cnt FROM tbl_faculty_registry_files");
                        if ($frs) {
                            $reg_files['count'] = (int)($frs->fetch_assoc()['cnt'] ?? 0);
                        }
                        $frs = $conn->query("SELECT original_name FROM tbl_faculty_registry_files WHERE status = 'imported' ORDER BY imported_at DESC, id DESC LIMIT 1");
                        if ($frs && ($frow = $frs->fetch_assoc())) {
                            $reg_files['last_name'] = (string)$frow['original_name'];
                        }
                    }
                    ?>

                    <div class="faculty-layout">

                        <!-- REGISTRY UPLOAD CARD -->
                        <div class="eq-card fac-registry-card" id="fac-registry-card" style="margin-bottom:1.25rem;grid-column:1/-1">
                            <div class="eq-card-header" id="fac-registry-header" style="cursor:pointer;user-select:none" role="button" aria-expanded="false" aria-controls="fac-registry-body" tabindex="0">
                                <h2>
                                    <span class="material-symbols-outlined" style="font-size:18px;color:var(--accent-maroon);margin-right:6px;vertical-align:middle">upload_file</span>
                                    Faculty Registry
                                    <?php if ($reg_status['count'] > 0): ?>
                                        <span class="fac-reg-badge fac-reg-badge--active"><?= $reg_status['count'] ?> entries</span>
                                    <?php else: ?>
                                        <span class="fac-reg-badge fac-reg-badge--empty">Not uploaded yet</span>
                                    <?php endif; ?>
                                </h2>
                                <span class="material-symbols-outlined fac-registry-chevron" id="fac-registry-chevron" style="color:var(--text-light);transition:transform .2s">expand_more</span>
                            </div>

                            <div id="fac-registry-body" style="display:none">
                                <div class="eq-card-body" style="padding-top:0">

                                    <?php if ($reg_status['count'] > 0 && $reg_status['last_date'] !== ''): ?>
                                        <p style="font-size:12px;color:var(--text-light);margin:0 0 1rem">
                                            Last imported:
                                            <strong style="color:var(--text-dark)"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($reg_status['last_date']))) ?></strong>
                                            by <?= htmlspecialchars($reg_status['last_by']) ?><?php if ($reg_files['last_name'] !== ''): ?> from <strong style="color:var(--text-dark)"><?= htmlspecialchars($reg_files['last_name']) ?></strong><?php endif; ?> &mdash;
                                        <?= $reg_status['count'] ?> total <?= $reg_status['count'] === 1 ? 'entry' : 'entries' ?>
                                        </p>
                                    <?php endif; ?>

                                    <!-- View the imported list, and the original files that were imported
                                         (both ask for the Super Admin password first) -->
                                    <div class="fac-reg-view-row" id="fac-reg-view-row">
                                        <button type="button" id="fac-reg-view-btn" class="ps-btn ps-btn--outline ps-btn--sm" <?= $reg_status['count'] > 0 ? '' : 'hidden' ?>>
                                            <span class="material-symbols-outlined">lock</span> View registry list
                                        </button>
                                        <button type="button" id="fac-reg-files-btn" class="ps-btn ps-btn--outline ps-btn--sm">
                                            <span class="material-symbols-outlined">folder_open</span> Imported files
                                            <span class="fac-reg-files-count" id="fac-reg-files-count" <?= $reg_files['count'] > 0 ? '' : 'hidden' ?>><?= (int)$reg_files['count'] ?></span>
                                        </button>
                                        <span class="fac-reg-view-hint">Asks for your password</span>
                                    </div>

                                    <div class="fac-reg-upload-row">
                                        <label for="fac-reg-file-input" class="fac-reg-file-label" id="fac-reg-file-label">
                                            <span class="material-symbols-outlined">attach_file</span>
                                            <span id="fac-reg-file-name">Choose file (PDF or XLSX)…</span>
                                        </label>
                                        <input type="file" id="fac-reg-file-input" accept=".pdf,.xlsx,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" style="display:none">
                                        <button type="button" id="fac-reg-import-btn" class="ps-btn ps-btn--primary" disabled>
                                            <span class="material-symbols-outlined">cloud_upload</span> Import
                                        </button>
                                    </div>

                                    <label class="fac-reg-replace-row" for="fac-reg-replace-all" style="margin-top:.5rem">
                                        <input type="checkbox" id="fac-reg-replace-all">
                                        <span style="font-size:12.5px;color:var(--text-light)">Replace entire registry (removes all existing entries before importing)</span>
                                    </label>

                                    <div id="fac-reg-alert" class="alert-banner hidden" role="alert" style="margin-top:.75rem"></div>

                                    <!-- Preview table — shown after parsing, before import -->
                                    <div id="fac-reg-preview" style="display:none;margin-top:1rem">
                                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
                                            <p style="font-size:12.5px;font-weight:600;color:var(--text-dark);margin:0" id="fac-reg-preview-count"></p>
                                            <button type="button" id="fac-reg-clear-btn" class="ps-btn ps-btn--ghost ps-btn--sm">
                                                <span class="material-symbols-outlined">close</span> Clear
                                            </button>
                                        </div>
                                        <div class="tbl-wrap" style="max-height:260px;overflow-y:auto">
                                            <table class="admin-table" style="font-size:12px">
                                                <thead>
                                                    <tr>
                                                        <th style="width:40px">#</th>
                                                        <th>Faculty ID</th>
                                                        <th>Name</th>
                                                        <th style="width:60px;text-align:center">New</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="fac-reg-preview-tbody"></tbody>
                                            </table>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div><!-- /registry card -->

                        <!-- CREATE FORM -->
                        <div class="eq-card faculty-form-card">
                            <div class="eq-card-header">
                                <h2>
                                    <span class="material-symbols-outlined" style="font-size:18px;color:var(--accent-maroon);margin-right:6px;vertical-align:middle">person_add</span>
                                    Create Faculty Account
                                </h2>
                            </div>
                            <div class="eq-card-body">
                                <?= csrf_field() ?>

                                <div class="fac-edit-section">Name</div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="fac-first">First name <span class="req-star">*</span></label>
                                        <input type="text" id="fac-first" name="first_name"
                                            class="form-control-custom" maxlength="100" required placeholder="First">
                                    </div>
                                    <div class="form-group">
                                        <label for="fac-last">Last name <span class="req-star">*</span></label>
                                        <input type="text" id="fac-last" name="last_name"
                                            class="form-control-custom" maxlength="100" required placeholder="Last">
                                    </div>
                                </div>
                                <small class="fac-field-hint" style="display:block;margin:-0.35rem 0 0.75rem">
                                    Type the whole surname under Last name, e.g. <strong>Dela Cruz</strong>. In the registry, everything after the middle initial
                                    (Edmundo B. <strong>Dela Cruz</strong>) is the surname; with no initial, the last word is.
                                </small>
                                <button type="button" id="fac-lookup-btn"
                                    class="ps-btn ps-btn--outline fac-lookup-btn">
                                    <span class="material-symbols-outlined">manage_search</span>
                                    Look up in Faculty Registry
                                </button>
                                <div id="fac-lookup-alert" class="alert-banner hidden" role="alert" style="margin-bottom:0.5rem"></div>

                                <!-- Disambiguation picker — shown only when multiple registry matches exist -->
                                <div id="fac-disambiguation" style="display:none;margin-bottom:0.75rem">
                                    <label for="fac-disambig-select" style="font-size:12px;color:var(--text-light);margin-bottom:4px;display:block">
                                        Multiple matches found — select the correct faculty member:
                                    </label>
                                    <select id="fac-disambig-select" class="form-control-custom"></select>
                                </div>

                                <div class="fac-edit-section">Account <span style="font-size:11px;font-weight:400;color:var(--text-light)">(auto-filled from registry)</span></div>
                                <div class="form-group">
                                    <label for="fac-email">PUPSync email</label>
                                    <input type="email" id="fac-email"
                                        class="form-control-custom" maxlength="254"
                                        autocomplete="off" spellcheck="false" placeholder="Auto-filled after lookup">
                                    <small class="fac-field-hint">Suggested from the registry name. You can edit it, for example when a surname has two words but no middle initial. It must end with @pupsync.edu.</small>
                                </div>
                                <div class="form-group">
                                    <label for="fac-faculty-id">Faculty ID</label>
                                    <input type="text" id="fac-faculty-id"
                                        class="form-control-custom" maxlength="30"
                                        autocomplete="off" spellcheck="false"
                                        placeholder="Auto-filled after lookup"
                                        readonly aria-readonly="true" tabindex="-1">
                                    <small class="fac-field-hint">From the Faculty Identification Records. Cannot be edited, and is permanent after account creation.</small>
                                </div>

                                <div class="fac-edit-section">Password</div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="fac-password">Password <span class="req-star">*</span></label>
                                        <div class="fac-pw-wrap">
                                            <input type="password" id="fac-password" name="password"
                                                class="form-control-custom" maxlength="128" required
                                                placeholder="Min. 8 characters">
                                            <button type="button" class="fac-pw-toggle" tabindex="-1"
                                                data-target="fac-password" aria-label="Toggle password">
                                                <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="fac-confirm">Confirm <span class="req-star">*</span></label>
                                        <div class="fac-pw-wrap">
                                            <input type="password" id="fac-confirm" name="confirm_password"
                                                class="form-control-custom" maxlength="128" required
                                                placeholder="Re-enter password">
                                            <button type="button" class="fac-pw-toggle" tabindex="-1"
                                                data-target="fac-confirm" aria-label="Toggle password">
                                                <span class="material-symbols-outlined" style="font-size:17px">visibility</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Organization adviser: org borrowing is automatic for advisers -->
                                <div class="fac-opt-card">
                                    <label class="fac-opt-row" for="fac-adviser">
                                        <span class="fac-opt-text">
                                            <span class="fac-opt-title">Organization adviser</span>
                                            <span class="fac-opt-sub">Gets organization borrowing automatically</span>
                                        </span>
                                        <input type="checkbox" id="fac-adviser" name="is_org_adviser"
                                            value="1" class="faculty-toggle-input">
                                        <span class="faculty-toggle-track"></span>
                                    </label>
                                    <div class="fac-opt-extra" id="fac-org-group" style="display:none;">
                                        <div class="form-group">
                                            <label for="fac-org">Organization</label>
                                            <?php
                                            // Organizations are grouped (Academic / Non-academic, then uncategorised);
                                            // one that already has an adviser is disabled and marked "(taken)".
                                            $orgs_grouped     = orgs_load_grouped($conn);
                                            $orgs_adviser_map = orgs_adviser_map($conn);
                                            $orgs_total = count($orgs_grouped[ORG_CATEGORY_ACADEMIC])
                                                + count($orgs_grouped[ORG_CATEGORY_NON_ACADEMIC])
                                                + count($orgs_grouped['']);
                                            if ($orgs_total > 0): ?>
                                                <select id="fac-org" name="organization_id"
                                                    class="form-control-custom">
                                                    <option value="">&#8212; Select Organization &#8212;</option>
                                                    <?= orgs_render_options($orgs_grouped, $orgs_adviser_map, true) ?>
                                                </select>
                                            <?php else: ?>
                                                <select id="fac-org" name="organization_id"
                                                    class="form-control-custom" disabled>
                                                    <option value="">&#8212; Organizations unavailable &#8212;</option>
                                                </select>
                                                <small class="faculty-field-error">
                                                    Could not load organizations.
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div id="fac-form-alert" class="alert-banner hidden" role="alert"></div>

                                <button type="button" id="fac-submit-btn"
                                    class="ps-btn ps-btn--primary fac-submit-btn">
                                    <span class="material-symbols-outlined">person_add</span>
                                    Create Account
                                </button>
                                <p class="fac-form-hint">Faculty ID and email are sourced from the Faculty Identification Records document.</p>
                            </div><!-- /eq-card-body -->
                        </div><!-- /faculty-form-card -->


                        <!-- FACULTY LIST -->
                        <div class="eq-card">
                            <div class="eq-card-header fac-list-head">
                                <h2>
                                    <span class="material-symbols-outlined" style="font-size:18px;color:var(--accent-maroon);margin-right:6px;vertical-align:middle">groups</span>
                                    Faculty List
                                    <span class="fac-count-badge" id="fac-count-badge">(<?php
                                                                                        $fac_count = $conn->query("SELECT COUNT(*) AS cnt FROM tbl_users");
                                                                                        echo ($fac_count) ? (int)$fac_count->fetch_assoc()['cnt'] : 0;
                                                                                        ?>)</span>
                                </h2>
                                <div class="fac-search">
                                    <span class="material-symbols-outlined">search</span>
                                    <input type="text" id="fac-search-input"
                                        class="form-control-custom"
                                        placeholder="Search name, email or ID">
                                </div>
                            </div>
                            <div class="tbl-wrap">
                                <table class="admin-table fac-table" id="fac-list-table">
                                    <thead>
                                        <tr>
                                            <th>Faculty</th>
                                            <th>Faculty ID</th>
                                            <th>Role</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="faculty-list-tbody">
                                        <?php
                                        $fac_res = $conn->query(
                                            "SELECT u.fullname, u.email, u.backup_email, u.role,"
                                                . " u.faculty_id, u.organization_id,"
                                                . " o.name AS org_name"
                                                . " FROM tbl_users u"
                                                . " LEFT JOIN tbl_organizations o ON u.organization_id = o.id"
                                                . " ORDER BY u.fullname ASC"
                                        );
                                        if ($fac_res && $fac_res->num_rows > 0):
                                            while ($frow = $fac_res->fetch_assoc()):
                                                $isAdviser = ($frow['role'] === 'Organization Adviser');
                                                $idUnset   = faculty_id_is_unset($frow['faculty_id']);
                                                $initFac   = strtoupper(substr(trim($frow['fullname'] ?? '') ?: 'F', 0, 1));
                                        ?>
                                                <tr
                                                    data-fullname="<?= htmlspecialchars($frow['fullname']) ?>"
                                                    data-email="<?= htmlspecialchars($frow['email']) ?>"
                                                    data-backup-email="<?= htmlspecialchars($frow['backup_email'] ?? '') ?>"
                                                    data-faculty-id="<?= htmlspecialchars($frow['faculty_id']) ?>"
                                                    data-role="<?= htmlspecialchars($frow['role']) ?>"
                                                    data-org="<?= htmlspecialchars($frow['org_name'] ?? '') ?>"
                                                    data-org-id="<?= (int)($frow['organization_id'] ?? 0) ?>"
                                                    data-init="<?= htmlspecialchars($initFac) ?>">
                                                    <td>
                                                        <div class="fac-person">
                                                            <span class="fac-avatar"><?= htmlspecialchars($initFac) ?></span>
                                                            <div class="fac-person-text">
                                                                <div class="fac-person-name"><?= htmlspecialchars($frow['fullname']) ?></div>
                                                                <div class="fac-person-sub"><?= htmlspecialchars($frow['email']) ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($idUnset): ?>
                                                            <span class="fac-id-pending">Not set yet</span>
                                                        <?php else: ?>
                                                            <span class="fac-id"><?= htmlspecialchars($frow['faculty_id']) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($isAdviser): ?>
                                                            <span class="fac-role fac-role--adviser">Org Adviser</span>
                                                            <?php if (!empty($frow['org_name'])): ?>
                                                                <div class="fac-person-sub"><?= htmlspecialchars($frow['org_name']) ?></div>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="fac-role">Faculty</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="fac-actions">
                                                        <button class="ps-btn ps-btn--ghost ps-btn--sm fac-edit-btn" aria-label="Edit account">
                                                            <span class="material-symbols-outlined">edit</span>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile;
                                        else: ?>
                                            <tr id="fac-empty-row">
                                                <td colspan="4"
                                                    style="text-align:center;padding:3rem;color:var(--text-light)">
                                                    <span class="material-symbols-outlined"
                                                        style="font-size:40px;display:block;margin:0 auto 10px;opacity:0.3">group</span>
                                                    No faculty accounts yet.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <div class="fac-nomatch" id="fac-nomatch" hidden>
                                    <span class="material-symbols-outlined">search_off</span>
                                    No faculty match your search.
                                </div>
                            </div>

                            <!-- Pagination (10 per page, client-side; works together with the search box) -->
                            <div class="inv-pager" id="fac-pager" hidden>
                                <span class="inv-pager-info" id="fac-pager-info"></span>
                                <div class="inv-pager-ctl" id="fac-pager-ctl" role="navigation" aria-label="Faculty list pages">
                                    <button type="button" class="inv-pager-btn" id="fac-pg-prev" aria-label="Previous page">
                                        <span class="material-symbols-outlined">chevron_left</span>
                                    </button>
                                    <div class="inv-pager-nums" id="fac-pg-nums"></div>
                                    <button type="button" class="inv-pager-btn" id="fac-pg-next" aria-label="Next page">
                                        <span class="material-symbols-outlined">chevron_right</span>
                                    </button>
                                </div>
                            </div>
                        </div><!-- /faculty-list-card -->

                    </div><!-- /faculty-layout -->
                </div><!-- /panel-faculty -->
            <?php endif; ?>

            <!-- ============================================================
         TAB: ADMINS  (Super Admin only)
         Left: everyone with an admin account. Right: the selected admin's
         details, actions and activity history. Driven by the "Admin Accounts
         panel" block in admin-dashboard.js.
    ============================================================ -->
            <?php if ($is_super_admin): ?>
                <div class="tab-panel" id="panel-admins">

                    <div class="am-head">
                        <div class="am-head-text">
                            <h2 class="am-title">Admin Accounts</h2>
                            <p class="am-sub">Control who can sign in and review what each admin has been doing.</p>
                        </div>
                        <div class="am-head-actions">
                            <span class="am-seats" id="am-seats" title="Admin accounts in use">
                                <span class="material-symbols-outlined">group</span>
                                <span id="am-seats-text"><strong><?php echo (int)$admin_accounts_count; ?> of 5</strong> seats used</span>
                            </span>
                            <button type="button" class="ps-btn ps-btn--primary" id="am-add-btn"
                                <?php echo $admin_accounts_remaining > 0 ? '' : 'disabled title="All 5 seats are in use. Delete an account to free one."'; ?>>
                                <span class="material-symbols-outlined">person_add</span> Add admin
                            </button>
                        </div>
                    </div>

                    <div class="am-layout">

                        <!-- ── Admin list ── -->
                        <section class="eq-card am-list-card" aria-label="Admin accounts">
                            <div class="am-search">
                                <span class="material-symbols-outlined">search</span>
                                <input type="text" id="am-search" class="form-control-custom"
                                    placeholder="Search admins" autocomplete="off" aria-label="Search admins">
                            </div>
                            <div class="am-list" id="am-list" role="listbox" aria-label="Admins">
                                <?php foreach ($admin_accounts_list as $acct):
                                    $am_you     = strtolower($acct['email'] ?? '') === strtolower($admin_email);
                                    $am_until   = admin_is_dormant($acct['dormant_until'] ?? null) ? (int)$acct['dormant_until'] : 0;
                                    $am_manage  = !$am_you && ($acct['role'] ?? '') === 'Admin';
                                    $am_parts   = preg_split('/\s+/', trim((string)($acct['fullName'] ?? '')));
                                    $am_ini     = strtoupper(substr($am_parts[0] ?? '?', 0, 1));
                                    if (count($am_parts) > 1) $am_ini .= strtoupper(substr(end($am_parts), 0, 1));
                                    $am_created = !empty($acct['created_at']) ? date('M j, Y', strtotime($acct['created_at'])) : '';
                                    $am_login   = !empty($acct['last_login']) ? date('M j, Y, g:i A', strtotime($acct['last_login'])) : '';
                                ?>
                                    <button type="button" class="am-item" role="option" aria-selected="false"
                                        data-id="<?php echo (int)($acct['id'] ?? 0); ?>"
                                        data-name="<?php echo htmlspecialchars($acct['fullName'] ?? '', ENT_QUOTES); ?>"
                                        data-email="<?php echo htmlspecialchars($acct['email'] ?? '', ENT_QUOTES); ?>"
                                        data-role="<?php echo htmlspecialchars($acct['role'] ?? 'Admin', ENT_QUOTES); ?>"
                                        data-created="<?php echo htmlspecialchars($am_created, ENT_QUOTES); ?>"
                                        data-last-login="<?php echo htmlspecialchars($am_login, ENT_QUOTES); ?>"
                                        data-locked-until="<?php echo $am_until; ?>"
                                        data-you="<?php echo $am_you ? '1' : '0'; ?>"
                                        data-manage="<?php echo $am_manage ? '1' : '0'; ?>">
                                        <span class="am-avatar"><?php echo htmlspecialchars($am_ini); ?></span>
                                        <span class="am-item-main">
                                            <span class="am-item-name"><?php echo htmlspecialchars($acct['fullName'] ?? '—'); ?><?php if ($am_you): ?> <span class="am-you">You</span><?php endif; ?></span>
                                            <span class="am-item-email"><?php echo htmlspecialchars($acct['email'] ?? '—'); ?></span>
                                        </span>
                                        <span class="material-symbols-outlined am-item-lock" <?php echo $am_until ? '' : 'hidden'; ?> title="Locked">lock</span>
                                    </button>
                                <?php endforeach; ?>
                                <p class="am-list-empty" id="am-list-empty" <?php echo empty($admin_accounts_list) ? '' : 'hidden'; ?>>
                                    <?php echo empty($admin_accounts_list) ? 'No accounts found.' : 'No admins match your search.'; ?>
                                </p>
                            </div>
                        </section>

                        <!-- ── Selected admin ── -->
                        <section class="eq-card am-detail-card" id="am-detail" aria-live="polite">

                            <div class="am-empty" id="am-detail-empty">
                                <span class="material-symbols-outlined">manage_accounts</span>
                                <p>Select an admin to see their details and activity.</p>
                            </div>

                            <div id="am-detail-body" hidden>
                                <header class="am-profile">
                                    <span class="am-avatar am-avatar--lg" id="am-d-avatar"></span>
                                    <div class="am-profile-main">
                                        <h3 class="am-d-name" id="am-d-name"></h3>
                                        <p class="am-d-email" id="am-d-email"></p>
                                        <div class="am-tags">
                                            <span class="am-tag" id="am-d-role"></span>
                                            <span class="am-tag" id="am-d-status"></span>
                                        </div>
                                    </div>
                                    <div class="am-actions" id="am-d-actions" hidden>
                                        <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm" data-am-action="edit">
                                            <span class="material-symbols-outlined">edit</span> Edit
                                        </button>
                                        <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm" data-am-action="lock" id="am-lock-btn">
                                            <span class="material-symbols-outlined" id="am-lock-icon">lock</span>
                                            <span id="am-lock-label">Lock</span>
                                        </button>
                                        <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm am-danger" data-am-action="delete">
                                            <span class="material-symbols-outlined">delete</span> Delete
                                        </button>
                                    </div>
                                </header>

                                <p class="am-note" id="am-d-note" hidden>
                                    <span class="material-symbols-outlined">shield</span>
                                    <span id="am-d-note-text"></span>
                                </p>

                                <dl class="am-facts">
                                    <div>
                                        <dt>Last sign-in</dt>
                                        <dd id="am-f-login">—</dd>
                                    </div>
                                    <div>
                                        <dt>Added</dt>
                                        <dd id="am-f-created">—</dd>
                                    </div>
                                    <div>
                                        <dt>Actions logged</dt>
                                        <dd id="am-f-total">—</dd>
                                    </div>
                                </dl>

                                <div class="am-activity">
                                    <div class="am-activity-head">
                                        <div class="am-activity-title">
                                            <h4>Activity</h4>
                                            <button type="button" class="act-open" id="am-open-activity">
                                                Open in Activity <span class="material-symbols-outlined">arrow_forward</span>
                                            </button>
                                        </div>
                                        <div class="act-chips" id="am-chips" role="group" aria-label="Filter activity">
                                            <button type="button" class="act-chip active" data-filter="all">All</button>
                                            <button type="button" class="act-chip" data-filter="requests">Requests</button>
                                            <button type="button" class="act-chip" data-filter="rooms">Facilities</button>
                                            <button type="button" class="act-chip" data-filter="equipment">Equipment</button>
                                            <button type="button" class="act-chip" data-filter="system">System</button>
                                            <button type="button" class="act-chip" data-filter="signins">Sign-ins</button>
                                        </div>
                                    </div>
                                    <div class="act-feed" id="am-feed"></div>
                                    <div class="act-pager" id="am-pager" hidden>
                                        <span class="act-pager-info"></span>
                                        <div class="act-pager-ctl">
                                            <button type="button" class="act-pager-btn" data-pg="prev" aria-label="Previous page">
                                                <span class="material-symbols-outlined">chevron_left</span>
                                            </button>
                                            <div class="act-pager-nums"></div>
                                            <button type="button" class="act-pager-btn" data-pg="next" aria-label="Next page">
                                                <span class="material-symbols-outlined">chevron_right</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </section>
                    </div>
                </div><!-- /panel-admins -->
            <?php endif; ?>

