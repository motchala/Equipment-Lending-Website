<?php

/**
 * service-survey/views/admin-ratings-panel.php
 *
 * Settings → "Service Ratings" tab (Admin + Super Admin).
 * Include-only. Required from settings/views/admin-screen.php as a DIRECT child of #panel-settings
 * (the settings sub-tab switcher only toggles ":scope > .rq-sub-panel").
 *
 * Everyone with an admin account sees the SAME collective, anonymous summary: overall rating, per-question
 * average + 1–5 star spread, and anonymous comments. Individual answers are never stored, so none can be shown.
 * The "Survey settings" editor is rendered for Super Admins only (and the API refuses everyone else too).
 *
 * Data:   service-survey/api/survey-results.php (GET)  ·  service-survey/api/survey-config-save.php (POST, Super Admin)
 * Styles: service-survey/assets/css/admin-ratings.css  (svr-*)
 * Script: service-survey/assets/js/admin-ratings.js
 *
 * Expects $is_super_admin from the settings screen (defaults to false when missing).
 */
require_once PS_ROOT . '/config/csrf.php';
$svr_super = !empty($is_super_admin);
?>
<link rel="stylesheet" href="service-survey/assets/css/admin-ratings.css?v=<?php echo @filemtime(PS_ROOT . '/service-survey/assets/css/admin-ratings.css'); ?>">

<!-- ── SERVICE RATINGS (Admin + Super Admin) ────────────────────────────── -->
<div class="rq-sub-panel" id="sett-ratings" data-csrf="<?php echo htmlspecialchars(csrf_token()); ?>" data-super="<?php echo $svr_super ? '1' : '0'; ?>">

    <!-- Intro -->
    <div class="svr-intro">
        <div class="svr-intro__icon"><span class="material-symbols-outlined">reviews</span></div>
        <div class="svr-intro__body">
            <h2 class="svr-intro__title">Service Ratings</h2>
            <p class="svr-intro__sub">
                How faculty rate the admin service after a finished borrow or room reservation.
                Fully anonymous: only combined results are kept. Reporting uses 14-day windows within the configured semester.
            </p>
        </div>
        <button type="button" class="btn-cancel-acc svr-refresh" id="svrRefresh">
            <span class="material-symbols-outlined">refresh</span> Refresh
        </button>
    </div>

    <div class="svr-banner" id="svrBanner" hidden></div>

    <!-- Overall -->
    <div class="info-card" id="svrOverallCard">
        <div class="info-card-head">
            <h3><span class="material-symbols-outlined">star</span> <span id="svrCurrentHeading">Current two-week rating</span></h3>
            <span class="svr-period-range" id="svrCurrentPeriodRange"></span>
        </div>
        <div class="svr-overall" id="svrOverall">
            <p class="svr-muted">Loading…</p>
        </div>
    </div>

    <!-- Semester roll-up -->
    <div class="info-card" id="svrSemesterCard">
        <div class="info-card-head">
            <h3><span class="material-symbols-outlined">calendar_month</span> Semester-to-date rating</h3>
            <span class="svr-period-range" id="svrSemesterRange"></span>
        </div>
        <div class="svr-overall" id="svrSemester">
            <p class="svr-muted">Loading…</p>
        </div>
    </div>

    <!-- Per question -->
    <div class="info-card" id="svrQuestionsCard">
        <div class="info-card-head">
            <h3><span class="material-symbols-outlined">format_list_numbered</span> <span id="svrQuestionsHeading">Current period by question</span></h3>
        </div>
        <div class="svr-questions" id="svrQuestions"></div>
    </div>

    <!-- Comments -->
    <div class="info-card" id="svrCommentsCard">
        <div class="info-card-head">
            <h3><span class="material-symbols-outlined">chat</span> Anonymous comments</h3>
        </div>
        <div class="svr-comments" id="svrComments"></div>
        <div class="svr-more" id="svrMoreWrap" hidden>
            <button type="button" class="btn-cancel-acc" id="svrMore">Show older comments</button>
        </div>
    </div>

    <?php if ($svr_super): ?>
    <!-- Survey settings (Super Admin only) -->
    <div class="info-card svr-editor" id="svrEditor" hidden>
        <div class="info-card-head svr-ed-head">
            <h3><span class="material-symbols-outlined">tune</span> Survey settings</h3>
            <span class="svr-pill"><span class="material-symbols-outlined">shield_person</span> Super Admin only</span>
        </div>

        <div class="svr-ed-body">
            <div class="svr-semester-settings">
                <div>
                    <div class="svr-switch-title">Semester reporting dates</div>
                    <div class="svr-switch-sub">Set both dates to start 14-day reporting windows anchored to the semester start. Leave both blank to use unperiodized totals.</div>
                </div>
                <div class="svr-date-fields">
                    <label class="svr-date-field" for="svrSemesterStart">
                        Semester starts
                        <input type="date" id="svrSemesterStart">
                    </label>
                    <label class="svr-date-field" for="svrSemesterEnd">
                        Semester ends
                        <input type="date" id="svrSemesterEnd">
                    </label>
                </div>
                <p class="svr-switch-sub">Ratings already collected before period reporting began remain in the legacy totals and cannot be assigned to exact two-week windows.</p>
            </div>

            <div class="svr-switch-row">
                <label class="toggle-sw">
                    <input type="checkbox" id="svrEnabled">
                    <span class="toggle-track"></span>
                </label>
                <div>
                    <div class="svr-switch-title">Ask faculty to rate the service</div>
                    <div class="svr-switch-sub">When off, the pop-up never appears. Results already collected stay here.</div>
                </div>
            </div>

            <div class="svr-switch-row">
                <label class="toggle-sw">
                    <input type="checkbox" id="svrCommentOn">
                    <span class="toggle-track"></span>
                </label>
                <div>
                    <div class="svr-switch-title">Show the optional comment box</div>
                    <div class="svr-switch-sub">The last, optional question where faculty can type their thoughts.</div>
                </div>
            </div>

            <div class="svr-ed-listhead">
                <div>
                    <div class="svr-switch-title">Star-rating questions</div>
                    <div class="svr-switch-sub">Faculty answer each one with 1 to 5 stars (5 = most satisfied).</div>
                </div>
                <span class="svr-count" id="svrEdCount"></span>
            </div>

            <div class="svr-ed-list" id="svrEdList"></div>

            <button type="button" class="btn-cancel-acc svr-add" id="svrAdd">
                <span class="material-symbols-outlined">add</span> Add question
            </button>

            <div class="svr-confirm" id="svrConfirm" hidden>
                <div class="svr-confirm__title"><span class="material-symbols-outlined">warning</span> These ratings will be cleared</div>
                <p class="svr-confirm__text">
                    Changing or removing a question resets its collected ratings, because the old numbers would
                    answer a different question. Ratings for the other questions are kept.
                </p>
                <ul class="svr-confirm__list" id="svrConfirmList"></ul>
                <div class="svr-confirm__actions">
                    <button type="button" class="btn-cancel-acc" id="svrConfirmCancel">Keep editing</button>
                    <button type="button" class="btn-save-acc" id="svrConfirmGo">Clear them and save</button>
                </div>
            </div>

            <p class="svr-ed-msg" id="svrEdMsg" role="status" hidden></p>

            <div class="svr-ed-foot">
                <button type="button" class="btn-cancel-acc" id="svrDiscard" disabled>Discard changes</button>
                <button type="button" class="btn-save-acc" id="svrSave" disabled>
                    <span class="material-symbols-outlined">save</span> Save changes
                </button>
            </div>
        </div>
    </div><!-- /svrEditor -->
    <?php endif; ?>

    <script src="service-survey/assets/js/admin-ratings.js?v=<?php echo @filemtime(PS_ROOT . '/service-survey/assets/js/admin-ratings.js'); ?>" defer></script>
</div><!-- /sett-ratings -->
