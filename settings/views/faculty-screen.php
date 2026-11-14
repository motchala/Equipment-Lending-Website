<!-- ================================================================
     OVERLAY: ACCOUNT — merged into settingsOverlay below
     (kept as hidden anchor for legacy data-target references)
================================================================ -->
<div class="overlay-page" id="accountOverlay">
    <div class="overlay-topbar">
        <button class="overlay-back-btn" data-action="close-overlay" data-target="accountOverlay">
            <span class="material-symbols-outlined">arrow_back</span> Back
        </button>
        <span class="overlay-topbar-title">My Account</span>
        <div class="overlay-topbar-brand"><strong>PUP</strong>SYNC</div>
    </div>
    <div class="account-layout">
        <div class="account-sidebar">
            <span class="account-sidebar-label">My Account</span>
            <button class="acc-nav-btn active" data-acc-tab="acc-overview">
                <span class="material-symbols-outlined">badge</span> Overview
            </button>
            <button class="acc-nav-btn" data-acc-tab="acc-academic">
                <span class="material-symbols-outlined">school</span> Department Info
            </button>
        </div>
        <div class="account-content">

            <!-- Overview -->
            <div id="acc-overview" class="overlay-sub-panel active">
                <div class="overlay-section-header">
                    <span class="section-eyebrow">My Account ║ Overview</span>
                    <h2>Profile &amp; Identity</h2>
                    <p>Your personal details and login information.</p>
                </div>
                <div class="account-hero-card">
                    <div class="acc-avatar-section">
                        <div class="acc-avatar-large" id="accOverlayAvatar">
                            <?php if ($profile_pic_url): ?>
                                <img src="<?php echo htmlspecialchars($profile_pic_url); ?>" alt="Profile"
                                    class="avatar-img"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.removeProperty('display');">
                                <span class="avatar-initials-fallback" style="display:none;"><?php echo htmlspecialchars($initials); ?></span>
                            <?php else: ?>
                                <?php echo htmlspecialchars($initials); ?>
                            <?php endif; ?>
                        </div>
                        <div style="position:relative;">
                            <button class="btn-change-profile" id="accOverlayChangePhotoBtn"
                                data-action="open-picture-menu">Change Photo</button>
                            <div class="picture-menu" id="accOverlayPictureMenu" style="display:none;">
                                <button class="pic-menu-item" data-action="upload-picture">
                                    <span class="material-symbols-outlined"
                                        style="font-size:15px;margin-right:8px;">upload</span>Upload Photo
                                </button>
                                <?php if ($profile_pic_url): ?>
                                    <button class="pic-menu-item pic-menu-danger" data-action="remove-picture">
                                        <span class="material-symbols-outlined"
                                            style="font-size:15px;margin-right:8px;">delete</span>Remove Photo
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <input type="file" id="accOverlayPicInput" accept="image/jpeg,image/png,image/jpg,image/webp"
                            style="display:none;">
                    </div>
                    <div class="acc-hero-info">
                        <h2>
                            <?php echo htmlspecialchars($fullname); ?>
                        </h2>
                        <p>ID: <span data-fid-display><?php echo htmlspecialchars($fid_label); ?></span></p>
                        <span class="acc-badge">
                            <span
                                style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;margin-right:6px;vertical-align:middle;"></span>
                            Active Faculty
                        </span>
                    </div>
                    <div class="acc-action-wrap">
                        <button class="btn-edit-acc" id="accOverlayEditBtn" data-action="profile-edit">Edit
                            Profile</button>
                        <button class="btn-save-acc" id="accOverlaySaveBtn" style="display:none;"
                            data-action="profile-save">
                            <span class="material-symbols-outlined"
                                style="font-size:14px;margin-right:4px;">check</span>Save
                        </button>
                        <button class="btn-cancel-acc" id="accOverlayCancelBtn" style="display:none;"
                            data-action="profile-cancel">Cancel</button>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-card-head">
                        <h3>Personal Information</h3>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl">Full Name</span>
                        <span class="info-val" data-field="fullname">
                            <?php echo htmlspecialchars($fullname); ?>
                        </span>
                        <input class="info-input-f" data-input="fullname"
                            value="<?php echo htmlspecialchars($fullname); ?>" disabled style="display:none;">
                    </div>
                    <div class="info-row">
                        <span class="info-lbl">Faculty ID</span>
                        <span class="info-val <?php echo $fid_unset ? 'empty' : ''; ?>" data-fid-display><?php echo htmlspecialchars($fid_label); ?></span>
                        <?php if ($fid_unset): ?>
                            <button class="btn-inline-action" data-action="open-faculty-id-modal">Add</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-card-head">
                        <h3>Login &amp; Security</h3>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl">Email Address</span>
                        <span class="info-val <?php echo $masked_email ? '' : 'empty'; ?>">
                            <?php echo $masked_email ?: '— Not provided'; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl">Password</span>
                        <span class="info-val">••••••••••</span>
                        <button class="btn-inline-action" data-action="open-email-verify-modal">Change</button>
                    </div>
                </div>
            </div><!-- /acc-overview -->

            <!-- Department -->
            <div id="acc-academic" class="overlay-sub-panel">
                <div class="overlay-section-header">
                    <span class="section-eyebrow">My Account ║ Department</span>
                    <h2>Department Information</h2>
                    <p>Your faculty department and assignment details.</p>
                </div>
                <div class="info-card">
                    <div class="info-card-head">
                        <h3>Department Assignment</h3>
                        <button class="btn-edit-acc" id="editAcademicBtn" data-action="academic-edit"
                            style="display:inline-flex;">Edit</button>
                        <button class="btn-save-acc" id="saveAcademicBtn" style="display:none;"
                            data-action="academic-save">Save Changes</button>
                        <button class="btn-cancel-acc" id="cancelAcademicBtn" style="display:none;"
                            data-action="academic-cancel">Cancel</button>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl">Faculty ID</span>
                        <span class="info-val <?php echo $fid_unset ? 'empty' : ''; ?>" data-fid-display><?php echo htmlspecialchars($fid_label); ?></span>
                        <?php if ($fid_unset): ?>
                            <button class="btn-inline-action" data-action="open-faculty-id-modal">Add</button>
                        <?php endif; ?>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl">Full Name</span>
                        <span class="info-val">
                            <?php echo htmlspecialchars($fullname); ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl">Department</span>
                        <span class="info-val <?php echo $department_locked ? '' : 'empty'; ?>" data-field="department">
                            <?php echo $department_locked ? htmlspecialchars($db_department) : '— Not provided'; ?>
                        </span>
                        <?php if (!$department_locked): ?>
                            <select class="info-input-f" data-input="department" disabled style="display:none;">
                                <option value="">Select Department...</option>
                                <?php foreach (['BEED', 'BSBA-HRM', 'BSCpE', 'BSED', 'BSIE', 'BSIT', 'BSPSY', 'DCET', 'DIT'] as $p): ?>
                                    <option value="<?php echo $p; ?>">
                                        <?php echo $p; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl">Status</span>
                        <span class="info-val"><span class="stock-badge stock-avail">Active / Regular</span></span>
                    </div>
                </div>
            </div><!-- /acc-academic -->

        </div>
    </div>
</div><!-- /accountOverlay -->

<!-- ================================================================
     OVERLAY: SETTINGS
================================================================ -->
<div class="overlay-page" id="settingsOverlay">

    <!-- Shell -->
    <div class="sov-shell">

        <!-- -- Page heading + view switch ------------------------ -->
        <div class="sov-pagehead sx-head">
            <div class="sx-head-text">
                <h1 class="sov-pagehead-title">Settings</h1>
                <p class="sov-pagehead-sub">Manage your profile, security, appearance and notifications in one place.</p>
            </div>
            <!-- Two views only: the unified Settings screen, and Help &amp; Support kept on its own.
                     (Same .sov-nav-item / data-sov-tab hooks the existing tab script already handles.) -->
            <nav class="acct-tabs sx-switch" id="sovSidenav" aria-label="Settings views">
                <button type="button" class="sov-nav-item active" data-sov-tab="sov-tab-settings">
                    <span class="material-symbols-outlined">tune</span>
                    Settings
                </button>
                <button type="button" class="sov-nav-item" data-sov-tab="sov-tab-help">
                    <span class="material-symbols-outlined">help_outline</span>
                    Help &amp; Support
                </button>
            </nav>
        </div>

        <!-- -- Body ------------------------------------------- -->
        <div class="sov-body">

            <?php
            $acct_meta_parts = array_filter([$db_department]);
            $acct_meta = $acct_meta_parts ? implode(' &middot; ', array_map('htmlspecialchars', $acct_meta_parts)) : 'PUPSync Faculty';
            ?>

            <!-- Identity banner -->
            <div class="acct-banner">
                <div class="acct-banner-left">
                    <div class="acct-banner-avatar"><?php echo htmlspecialchars($initials); ?></div>
                    <div class="acct-banner-text">
                        <h2 class="acct-banner-name"><?php echo htmlspecialchars($fullname); ?></h2>
                        <p class="acct-banner-meta"><?php echo $acct_meta; ?> &middot; ID: <span data-fid-display><?php echo htmlspecialchars($fid_label); ?></span></p>
                        <span class="acct-banner-badge">
                            <span class="material-symbols-outlined">check_circle</span>
                            Active Faculty Account
                        </span>
                    </div>
                </div>
                <div class="sov-profile-actions" id="sovProfileActions">
                    <button type="button" class="sov-btn-outline sov-profile-edit-btn" id="sovEditBtn"
                        data-action="account-edit">
                        <span class="material-symbols-outlined" aria-hidden="true">edit</span>
                        Edit Profile
                    </button>
                    <button type="button" class="sov-btn-primary sov-profile-save-btn" id="sovSaveBtn"
                        data-action="account-save" disabled hidden>
                        <span class="material-symbols-outlined" aria-hidden="true">save</span>
                        <span class="sov-profile-save-label">Save Changes</span>
                    </button>
                    <button type="button" class="sov-btn-outline sov-profile-cancel-btn" id="sovCancelBtn"
                        data-action="account-cancel" hidden>
                        Cancel
                    </button>
                </div>
            </div>

            <!-- Views -->
            <div class="sov-content">

                <!-- VIEW: Settings -- Account, Appearance and Notifications & Data on ONE screen -->
                <div class="sov-tab-panel active" id="sov-tab-settings">

                    <section class="sx-section" aria-labelledby="sxAccountTitle">
                        <header class="sx-section-head">
                            <span class="sx-section-icon material-symbols-outlined">person</span>
                            <div>
                                <h2 class="sx-section-title" id="sxAccountTitle">Account</h2>
                                <p class="sx-section-sub">Your profile details and sign-in security.</p>
                            </div>
                        </header>
                        <div class="sx-grid sx-grid--lead">

                            <!-- Personal Information: read-only until the profile-bar Edit Profile action is clicked -->
                            <?php
                            $sov_email_masked = faculty_mask_email($db_email);
                            ?>
                            <div class="acct-card sov-edit-card" id="sovPersonalCard" data-editing="false">
                                <div class="acct-card-head">
                                    <span class="material-symbols-outlined">badge</span>
                                    <h3>Personal Information</h3>
                                </div>
                                <div class="acct-rows">
                                    <div class="acct-row">
                                        <label class="acct-row-label" for="sovFullName">Full Name</label>
                                        <div class="acct-row-value">
                                            <span class="acct-row-static" id="sovFullNameView" data-view="fullname"><?php echo htmlspecialchars($fullname); ?></span>
                                            <input class="acct-row-input" id="sovFullName" name="fullname" type="text" maxlength="120"
                                                autocomplete="name" value="<?php echo htmlspecialchars($fullname); ?>"
                                                data-edit-field="fullname" disabled>
                                            <p class="acct-field-error" data-error-for="fullname" role="alert" hidden></p>
                                        </div>
                                    </div>
                                    <div class="acct-row">
                                        <span class="acct-row-label">Faculty ID</span>
                                        <div class="acct-row-value">
                                            <span class="acct-row-static <?php echo $fid_unset ? 'acct-row-static-muted' : ''; ?>" id="sovFacultyId" data-fid-display><?php echo htmlspecialchars($fid_label); ?></span>
                                        </div>
                                        <?php if ($fid_unset): ?>
                                            <button type="button" class="sov-btn-outline sov-fid-add" data-action="open-faculty-id-modal">Add</button>
                                        <?php else: ?>
                                            <span class="material-symbols-outlined fid-lock" title="Your Faculty ID is permanent">lock</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="acct-row">
                                        <span class="acct-row-label">Email Address</span>
                                        <div class="acct-row-value">
                                            <span class="acct-row-static <?php echo $sov_email_masked === '' ? 'acct-row-static-muted acct-row-not-provided' : 'acct-row-email'; ?>" id="sovEmailMasked"
                                                <?php echo $sov_email_masked === '' ? '' : 'aria-label="Email address, partially hidden for privacy"'; ?>><?php echo $sov_email_masked === '' ? '— Not provided' : htmlspecialchars($sov_email_masked); ?></span>
                                        </div>
                                        <span class="material-symbols-outlined fid-lock" title="Your email is your sign-in ID and is managed by the administrator">lock</span>
                                    </div>
                                    <div class="acct-row">
                                        <label class="acct-row-label" for="sovDepartment">Department</label>
                                        <div class="acct-row-value">
                                            <?php if ($department_locked): ?>
                                                <span class="acct-row-static" id="sovDepartmentView"><?php echo htmlspecialchars($db_department); ?></span>
                                            <?php else: ?>
                                                <span class="acct-row-static acct-row-static-muted acct-row-not-provided" id="sovDepartmentView" data-view="department">— Not provided</span>
                                                <select class="acct-row-input" id="sovDepartment" name="department" data-edit-field="department" disabled>
                                                    <option value="">Select your department&hellip;</option>
                                                    <?php foreach (faculty_departments() as $sov_dept): ?>
                                                        <option value="<?php echo htmlspecialchars($sov_dept); ?>"><?php echo htmlspecialchars($sov_dept); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="button" class="sov-field-info" data-sov-edit-gated hidden
                                                    aria-label="Department selection information" aria-describedby="sovDepartmentHint">
                                                    <span class="material-symbols-outlined" aria-hidden="true">info</span>
                                                    <span class="sov-field-info-tip" id="sovDepartmentHint" role="tooltip">You can choose your department once; it can&rsquo;t be changed afterwards.</span>
                                                </button>
                                            <?php endif; ?>
                                            <p class="acct-field-error" data-error-for="department" role="alert" hidden></p>
                                        </div>
                                        <?php if ($department_locked): ?>
                                            <span class="material-symbols-outlined fid-lock" title="Your department is permanent once set">lock</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>


                            <!-- Password & Security -->
                            <div class="acct-card">
                                <div class="acct-card-head">
                                    <span class="material-symbols-outlined">lock</span>
                                    <h3>Password &amp; Security</h3>
                                </div>
                                <div class="acct-rows">
                                    <div class="acct-row">
                                        <span class="acct-row-label">Password</span>
                                        <div class="acct-row-value">
                                            <?php /* Fixed, decorative mask: never built from the real password, so it says nothing about its length. */ ?>
                                            <span class="acct-row-static" role="img" aria-label="Password hidden">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
                                        </div>
                                        <span class="sov-gated-action-slot">
                                            <button type="button" class="sov-btn-outline" data-action="open-pw-modal"
                                                data-sov-edit-gated hidden>Change</button>
                                        </span>
                                    </div>
                                    <?php
                                    $sov_pw_changed_ts = !empty($db_last_pw_change) ? strtotime($db_last_pw_change) : false;
                                    $sov_pw_untracked = $sov_pw_changed_ts === false;
                                    ?>
                                    <div class="acct-row">
                                        <span class="acct-row-label">Password Last Changed</span>
                                        <div class="acct-row-value">
                                            <span class="acct-row-static acct-row-password-change<?php echo $sov_pw_untracked ? ' pw-change-untracked' : ''; ?>"
                                                id="sovPasswordLastChanged">
                                                <?php echo $sov_pw_untracked ? '— Not tracked' : htmlspecialchars(date('M d, Y · g:i A', $sov_pw_changed_ts)); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php
                                    $sov_dev      = faculty_device_info($_SERVER['HTTP_USER_AGENT'] ?? '');
                                    $sov_dev_icon = ['phone' => 'smartphone', 'tablet' => 'tablet_mac', 'desktop' => 'computer'][$sov_dev['kind']] ?? 'devices';
                                    ?>
                                    <div class="acct-row">
                                        <span class="acct-row-label">This Device</span>
                                        <div class="acct-row-value">
                                            <span class="acct-row-static acct-device">
                                                <span class="material-symbols-outlined" aria-hidden="true"><?php echo $sov_dev_icon; ?></span>
                                                <span><?php echo htmlspecialchars($sov_dev['label']); ?></span>
                                            </span>
                                            <span class="acct-device-note">The device you are signed in on right now</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </section>

                    <section class="sx-section" aria-labelledby="sxPrefsTitle">
                        <header class="sx-section-head">
                            <span class="sx-section-icon material-symbols-outlined">tune</span>
                            <div>
                                <h2 class="sx-section-title" id="sxPrefsTitle">Preferences</h2>
                                <p class="sx-section-sub">How PUPSync looks, what you hear about, and your data.</p>
                            </div>
                        </header>
                        <div class="sx-grid">

                            <!-- Theme -->
                            <div class="acct-card">
                                <div class="acct-card-head">
                                    <span class="material-symbols-outlined">palette</span>
                                    <h3>Theme</h3>
                                </div>
                                <div class="sov-theme-row sov-theme-row--pills">
                                    <div class="sov-theme-option sov-theme-opt theme-active" data-action="apply-theme" data-theme="light">
                                        <span class="sov-theme-opt-emoji" aria-hidden="true">&#9728;&#65039;</span>
                                        <span class="sov-theme-opt-label">Light</span>
                                        <div class="sov-theme-check" id="tc-light">
                                            <span class="material-symbols-outlined">check</span>
                                        </div>
                                    </div>
                                    <div class="sov-theme-option sov-theme-opt" data-action="apply-theme" data-theme="dark">
                                        <span class="sov-theme-opt-emoji" aria-hidden="true">&#127769;</span>
                                        <span class="sov-theme-opt-label">Dark</span>
                                        <div class="sov-theme-check" id="tc-dark">
                                            <span class="material-symbols-outlined">check</span>
                                        </div>
                                    </div>
                                    <?php /* High Contrast theme removed (Light / Dark only):
                                        <div class="sov-theme-option" data-action="apply-theme" data-theme="high-contrast">
                                            <div class="sov-theme-swatch sov-swatch-hc">
                                                <div class="sov-swatch-topbar"></div>
                                                <div class="sov-swatch-body">
                                                    <div class="sov-swatch-sidebar"></div>
                                                    <div class="sov-swatch-content"></div>
                                                </div>
                                            </div>
                                            <div class="sov-theme-check" id="tc-hc">
                                                <span class="material-symbols-outlined">check</span>
                                            </div>
                                            <span class="sov-theme-name">High Contrast</span>
                                        </div>
                                        */ ?>
                                </div>
                                <!-- Hidden legacy elements kept for JS compatibility -->
                                <select id="themeSelectUnified" style="display:none;">
                                    <option value="light">Light</option>
                                    <option value="dark">Dark</option>
                                </select>
                                <div style="display:none;">
                                    <div id="tp-light"></div>
                                    <div id="tp-dark"></div>
                                </div>
                                <p class="sov-desc-small">Current theme: <strong id="currentThemeLabel">Light</strong></p>
                            </div>

                            <!-- Data Management -->
                            <div class="acct-card">
                                <div class="acct-card-head">
                                    <span class="material-symbols-outlined">database</span>
                                    <h3>Data Management</h3>
                                </div>
                                <div class="acct-rows">
                                    <div class="acct-row">
                                        <span class="acct-row-label">Export Data</span>
                                        <div class="acct-row-value">
                                            <span class="acct-row-static acct-row-static-muted">A PDF report of your information, equipment and room history, weekly schedule and subjects</span>
                                        </div>
                                        <button type="button" class="sov-btn-outline" id="sovExportBtn" data-action="export-my-data">Export</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Notification Preferences: full-width row at the bottom (it is the tall one) -->
                            <div class="acct-card acct-card--wide">
                                <div class="acct-card-head">
                                    <span class="material-symbols-outlined">notifications</span>
                                    <h3>Notification Preferences</h3>
                                </div>
                                <?php
                                // Saved per faculty account on the server (notifications/api/notif-prefs.php) and
                                // applied there: a muted category is never listed, counted, popped up or emailed.
                                require_once PS_ROOT . '/notifications/core/preferences.php';
                                $np_defs  = notif_pref_definitions('faculty');
                                $np_state = notif_prefs_get($conn, 'faculty', (string)$_SESSION['faculty_id']);
                                ?>
                                <div class="acct-rows<?php echo empty($np_state['all']) ? ' notif-prefs-off' : ''; ?>" id="notifPrefsPanel" data-notif-audience="faculty">
                                    <div class="acct-row acct-row--master">
                                        <span class="acct-row-label">All notifications</span>
                                        <div class="acct-row-value">
                                            <span class="acct-row-static acct-row-static-muted">Turn off to stop every alert, pop-up and email below</span>
                                        </div>
                                        <label class="toggle-sw"><input type="checkbox" data-notif-pref="all" data-notif-label="All notifications" aria-label="All notifications"<?php echo !empty($np_state['all']) ? ' checked' : ''; ?>><span class="toggle-track"></span></label>
                                    </div>
                                    <?php foreach ($np_defs as $np_key => $np_def): ?>
                                    <div class="acct-row acct-row--child">
                                        <span class="acct-row-label"><?php echo htmlspecialchars($np_def['label']); ?></span>
                                        <div class="acct-row-value">
                                            <span class="acct-row-static acct-row-static-muted"><?php echo htmlspecialchars($np_def['desc']); ?></span>
                                        </div>
                                        <label class="toggle-sw"><input type="checkbox" data-notif-pref="<?php echo htmlspecialchars($np_key); ?>" data-notif-label="<?php echo htmlspecialchars($np_def['label']); ?>" aria-label="<?php echo htmlspecialchars($np_def['label']); ?>"<?php echo !empty($np_state[$np_key]) ? ' checked' : ''; ?><?php echo empty($np_state['all']) ? ' disabled' : ''; ?>><span class="toggle-track"></span></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>



                        </div>
                    </section>
                </div><!-- /sov-tab-settings -->

                <div class="sov-tab-panel" id="sov-tab-help">
                    <header class="sx-section-head">
                        <span class="sx-section-icon material-symbols-outlined">support_agent</span>
                        <div>
                            <h2 class="sx-section-title">Help &amp; Support</h2>
                            <p class="sx-section-sub">Answers to common questions, and how to reach the administrator.</p>
                        </div>
                    </header>
                    <div class="acct-card acct-card-full">
                        <div class="acct-card-head">
                            <span class="material-symbols-outlined">help_outline</span>
                            <h3>Frequently Asked Questions</h3>
                        </div>

                        <div class="sov-help-list">

                            <details class="help-item">
                                <summary class="help-item-q">
                                    <span class="material-symbols-outlined">help_outline</span>
                                    How do I borrow equipment?
                                    <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                </summary>
                                <div class="help-item-a">
                                    Open <strong>Equipment</strong>, find an item and click <strong>Borrow</strong>. To borrow several items at once, tick them and click <strong>Borrow All</strong> (these are always borrowed today).<br><br>Then choose where the equipment is going (a room from Facilities, or <em>Outside</em>) and when you need it: <strong>Today</strong> (pick a start time and 1, 2, 3 or 5 hours) or <strong>Later date</strong> (up to 3 weeks ahead, for 1, 2 or 3 days). Bookings run from 7:00 AM to 9:00 PM, and an item that is out right now can still be booked for later. Finally, choose who will collect it: you or a student.<br><br>For a <strong>later date</strong> you can attach a request letter (PDF, JPG, PNG or WEBP, up to 5 MB). It is recommended but not required, and you do not need one for a same-day request.
                                </div>
                            </details>

                            <details class="help-item">
                                <summary class="help-item-q">
                                    <span class="material-symbols-outlined">help_outline</span>
                                    What happens after I submit a request?
                                    <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                </summary>
                                <div class="help-item-a">
                                    A <strong>same-day</strong> request for a single item is decided by the system right away: you will see it approved at once, or the reason it could not be accepted. A <strong>later-date</strong> request, or a <strong>Borrow All</strong> request for several items, goes to the admin as <strong>Pending</strong>. You will see the result in your <strong>Notifications</strong> and in <strong>My Activity</strong>.<br><br><strong>Pending</strong>: waiting for the admin.<br><strong>Approved / Ready for pickup</strong>: approved, but not collected yet.<br><strong>Active</strong>: the item is with you.<br><strong>Overdue</strong>: the return time has passed.<br><strong>Returned</strong>: the item is back.<br><strong>Declined</strong>: the admin declined it (the reason is shown), or nobody decided before the borrow date passed.<br><strong>Cancelled</strong>: you or an admin cancelled it.
                                </div>
                            </details>

                            <details class="help-item">
                                <summary class="help-item-q">
                                    <span class="material-symbols-outlined">help_outline</span>
                                    How do I collect an approved item?
                                    <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                </summary>
                                <div class="help-item-a">
                                    Open <strong>My Activity</strong> and find the item under <em>Ready for pickup</em>. Tap its <strong>Claim QR</strong> (borrowing several items together gives you one Claim Bundle QR). Show it to the admin at the Admin Office, who scans it. The item then shows as <strong>Active</strong>. If a student is collecting for you, they show the same QR.
                                </div>
                            </details>

                            <details class="help-item">
                                <summary class="help-item-q">
                                    <span class="material-symbols-outlined">help_outline</span>
                                    How do I return a borrowed item?
                                    <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                </summary>
                                <div class="help-item-a">
                                    Open <strong>My Activity</strong>, find the item under <em>Active loans</em>, and open its <strong>Return QR</strong>. Bring the item to the Admin Office and show the QR so the admin can scan it. The item then shows as <strong>Returned</strong>. If you cannot show the QR, the admin can record the return manually.
                                </div>
                            </details>

                            <details class="help-item">
                                <summary class="help-item-q">
                                    <span class="material-symbols-outlined">help_outline</span>
                                    Why is my item Overdue, and can I extend it?
                                    <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                </summary>
                                <div class="help-item-a">
                                    An item is <strong>Overdue</strong> when its return time has passed and it has not been returned. While you have an overdue item you cannot borrow more equipment or generate student codes (room reservations are not affected). Return it to the Admin Office, or use <strong>Extend</strong> in My Activity.<br><br>Extending adds 1, 2, 3 or 5 hours to an hourly loan, or 1, 2 or 3 days to a day loan. You can extend a loan at most 2 times. It is not possible if someone else has booked the item, another faculty member has reserved the room, or it is too late in the day (the office closes at 9:00 PM). The original lateness stays on record.
                                </div>
                            </details>

                            <details class="help-item">
                                <summary class="help-item-q">
                                    <span class="material-symbols-outlined">help_outline</span>
                                    Can I cancel a request?
                                    <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                </summary>
                                <div class="help-item-a">
                                    Yes, from <strong>My Activity</strong>. A pending request can be cancelled any time before it would start. An approved booking that has not started can be cancelled up to 1 hour before its start time. Items already with you cannot be cancelled; return them with the Return QR instead.
                                </div>
                            </details>

                            <details class="help-item">
                                <summary class="help-item-q">
                                    <span class="material-symbols-outlined">help_outline</span>
                                    Why was my request declined, or why can't I book?
                                    <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                </summary>
                                <div class="help-item-a">
                                    Common reasons: you have an overdue item; the item is already booked or out for that time (the form suggests the next free start); you asked for more than the stock; another faculty member has reserved that room for an overlapping time; the time is outside 7:00 AM to 9:00 PM or more than 3 weeks ahead; or nobody decided before the borrow date passed. The admin's reason, if any, appears in your Notifications and My Activity.
                                </div>
                            </details>

                            <details class="help-item">
                                <summary class="help-item-q">
                                    <span class="material-symbols-outlined">help_outline</span>
                                    How can my students borrow or reserve with my code?
                                    <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                </summary>
                                <div class="help-item-a">
                                    Your <strong>Dashboard</strong> shows your <strong>Student Access Code</strong>, and every equipment card has its own <strong>Item Code</strong> (open the small arrow on the card). A student opens the Student Portal, enters a code with their name, student ID and course, and then borrows equipment or reserves a facility.<br><br>Student borrows are approved right away and the student receives a Claim QR. Item codes work once, and a new one is created automatically. <strong>Generate New Code</strong> replaces your Student Access Code. Student room reservations count toward your limit of 2 active reservations.
                                </div>
                            </details>

                            <details class="help-item">
                                <summary class="help-item-q">
                                    <span class="material-symbols-outlined">help_outline</span>
                                    How do I reserve a room?
                                    <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                </summary>
                                <div class="help-item-a">
                                    Open <strong>Facilities</strong>, choose a building, then a floor and a room, and click <strong>Reserve</strong> (or ask the room assistant). Pick the date, start and end time, purpose and number of attendees. Rooms can be booked from 7:00 AM to 8:00 PM, Monday to Saturday, up to 3 weeks ahead. The room's dialog lists today's and this week's reservations so you can spot free times.<br><br>A <strong>same-day</strong> reservation is confirmed immediately if the slot is free. A <strong>later-date</strong> reservation stays Pending until an admin decides. If nobody decides by 12:01 AM on that date, it is declined automatically, and you will be notified. Overdue equipment does not stop you from reserving a room.
                                </div>
                            </details>

                            <details class="help-item">
                                <summary class="help-item-q">
                                    <span class="material-symbols-outlined">help_outline</span>
                                    How many rooms can I hold, and how do I cancel or join a waitlist?
                                    <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                </summary>
                                <div class="help-item-a">
                                    You can have at most <strong>2 active room reservations</strong> at once (pending or approved, and not finished yet, including ones students made with your code). A slot frees up when a reservation ends or is cancelled.<br><br>Cancel from <strong>My Activity</strong>; a reservation cannot be cancelled within 1 hour of its start time. If a slot you want is taken, you can join its waitlist and you will be notified if it becomes free.<br><br>For a reservation on a <strong>later date</strong> you can attach a request letter (JPG, PNG or PDF, up to 5 MB). It is recommended but not required, and a same-day reservation does not need one.
                                </div>
                            </details>

                            <details class="help-item">
                                <summary class="help-item-q">
                                    <span class="material-symbols-outlined">help_outline</span>
                                    How do I report a room problem, or use the room assistant?
                                    <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                </summary>
                                <div class="help-item-a">
                                    In the room's dialog under <strong>Facilities</strong>, click <strong>Report issue</strong>. The admin reviews it and may set the room to Maintenance. The <strong>Ask about rooms</strong> button on the Facilities tab opens the room assistant. It answers questions about availability and rules and can help you reserve. You can send up to 20 messages per hour.
                                </div>
                            </details>

                            <details class="help-item">
                                <summary class="help-item-q">
                                    <span class="material-symbols-outlined">help_outline</span>
                                    Where can I see my activity and notifications?
                                    <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                </summary>
                                <div class="help-item-a">
                                    <strong>My Activity</strong> shows your loans and room reservations: ready for pickup, active, overdue, pending, and a searchable history (use <strong>Download Report</strong> to save it). Your <strong>Dashboard</strong> shows what needs your attention and your schedule today. The bell on the Dashboard opens your Notifications.
                                </div>
                            </details>

                            <details class="help-item">
                                <summary class="help-item-q">
                                    <span class="material-symbols-outlined">help_outline</span>
                                    How do I update my profile or change my password?
                                    <span class="material-symbols-outlined help-item-chevron">expand_more</span>
                                </summary>
                                <div class="help-item-a">
                                    Open <strong>Settings</strong> from the sidebar. Click <strong>Edit Profile</strong> to change your name, department and mobile number. Your department can only be chosen once, your Faculty ID is permanent, and your email is managed by the administrator. You can also change your profile photo.<br><br>To change your password, click <strong>Change</strong> under Password &amp; Security. You will need your current password, the new one must be at least 6 characters, and it can be changed once every 7 days. Settings also lets you choose a Light or Dark theme, pick your email alerts, and <strong>Export Data</strong>. If you cannot sign in, contact the administrator.
                                </div>
                            </details>

                        </div>

                        <div class="help-contact-card">
                            <span class="material-symbols-outlined help-contact-icon">support_agent</span>
                            <h4>Still need help?</h4>
                            <p>Contact the PUPSync system administrator, or visit the Admin Office, for technical issues or escalations.</p>
                            <a href="mailto:admin@pupsync.edu.ph" class="sov-btn-primary help-contact-btn">
                                <span class="material-symbols-outlined">mail</span>
                                Email Administrator
                            </a>
                        </div>
                    </div>
                </div><!-- /sov-tab-help -->

            </div><!-- /sov-content -->
        </div><!-- /sov-body -->
    </div><!-- /sov-shell -->
</div><!-- /settingsOverlay -->