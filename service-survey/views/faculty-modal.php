<?php

/**
 * service-survey/views/faculty-modal.php
 *
 * The anonymous "How was our service?" pop-up for the FACULTY portal.
 * Include-only. Required by faculty-dashboard.php AFTER </main> so the dialog is a direct child of <body>
 * (position: fixed breaks inside main#appMain).
 *
 * The dialog starts hidden. service-survey/assets/js/faculty-survey.js asks
 * service-survey/api/survey-status.php whether a completed transaction is waiting to be rated. The
 * transaction is claimed once before the questions are filled in and the dialog opens, so refreshing the
 * page cannot show the same transaction again. Nothing is rendered for the questions here, so a survey the
 * Super Admin has switched off or edited never needs a page change.
 *
 * Styles: service-survey/assets/css/faculty-survey.css  (all classes are svy-*)
 */
?>
    <link rel="stylesheet" href="service-survey/assets/css/faculty-survey.css?v=<?php echo @filemtime(PS_ROOT . '/service-survey/assets/css/faculty-survey.css'); ?>">

    <!-- ── Service survey (anonymous) ────────────────────────────────────────── -->
    <div class="modal-backdrop svy-backdrop" id="svyBackdrop" hidden>
        <div class="modal-box svy-box" role="dialog" aria-modal="true" aria-labelledby="svyTitle" aria-describedby="svySub" tabindex="-1">
            <div class="modal-header svy-head">
                <h3 id="svyTitle">
                    <span class="material-symbols-outlined svy-head-ic" aria-hidden="true">star_rate</span>
                    How was our service?
                </h3>
            </div>

            <div class="modal-body svy-body">
                <p class="svy-sub" id="svySub">Rate the admin service for your recent transaction.</p>
                <p class="svy-scale" aria-hidden="true">
                    <span>1 star = least satisfied</span>
                    <span>5 stars = most satisfied</span>
                </p>

                <div id="svyQuestions"></div>

                <div class="svy-comment" id="svyCommentWrap" hidden>
                    <label class="svy-comment-lbl" for="svyComment">Anything else you would like to say? <span>(optional)</span></label>
                    <textarea id="svyComment" maxlength="500" rows="3" placeholder="Share your thoughts in your own words…"></textarea>
                    <div class="svy-comment-foot">
                        <span>Please do not write your name.</span>
                        <span id="svyCount">0/500</span>
                    </div>
                </div>

                <p class="svy-anon">
                    <span class="material-symbols-outlined" aria-hidden="true">lock</span>
                    Anonymous. Your name and ID are never saved with your answers.
                </p>
                <p class="svy-error" id="svyError" role="alert" hidden></p>
            </div>

            <div class="modal-footer svy-foot">
                <button type="button" class="btn-save-acc" id="svySubmit" disabled>
                    <span class="material-symbols-outlined" aria-hidden="true">send</span>
                    Submit
                </button>
            </div>
        </div>
    </div><!-- /svyBackdrop -->

    <script src="service-survey/assets/js/faculty-survey.js?v=<?php echo @filemtime(PS_ROOT . '/service-survey/assets/js/faculty-survey.js'); ?>" defer></script>
