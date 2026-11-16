<?php
/**
 * shared/views/push-permission-modal.php
 *
 * Custom "Allow notifications" prompt shown once before the browser
 * permission dialog fires. Included by faculty-dashboard.php and
 * admin-dashboard.php. Hidden by default; push-init.js shows it.
 *
 * Requires: $csp_nonce (set by the parent page)
 */
?>
<div class="ps-push-backdrop" id="psPushModal" hidden aria-modal="true" role="dialog" aria-labelledby="psPushTitle">
    <div class="ps-push-modal">
        <div class="ps-push-head">
            <div class="ps-push-head-icon">
                <span class="material-symbols-outlined" aria-hidden="true">notifications_active</span>
            </div>
            <div class="ps-push-head-text">
                <h3 id="psPushTitle">Stay updated on your requests</h3>
                <p>PUPSync &middot; Bi&ntilde;an Campus</p>
            </div>
        </div>
        <div class="ps-push-body">
            <p>Allow PUPSync to send you notifications even when the browser is closed &mdash; so you know right away when something needs your attention.</p>
            <ul class="ps-push-examples">
                <li>
                    <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
                    Your borrow request was approved or declined
                </li>
                <li>
                    <span class="material-symbols-outlined" aria-hidden="true">schedule</span>
                    A borrowed item is overdue
                </li>
                <li>
                    <span class="material-symbols-outlined" aria-hidden="true">meeting_room</span>
                    Your room reservation has an update
                </li>
            </ul>
            <p class="ps-push-note">You can turn this off anytime from your browser settings.</p>
        </div>
        <div class="ps-push-foot">
            <button type="button" class="ps-push-btn-allow" id="psPushAllow">
                <span class="material-symbols-outlined" aria-hidden="true">notifications_active</span>
                Allow Notifications
            </button>
            <button type="button" class="ps-push-btn-later" id="psPushLater">
                Maybe later
            </button>
        </div>
    </div>
</div>
