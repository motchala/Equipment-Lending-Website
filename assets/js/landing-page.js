/* ================================================================
   PANEL VIEWS — the right-hand gateway panel swaps its own content
   in place (role picker <-> login form). There is no popup/modal;
   everything below just toggles which .panel-view is active inside
   the same panel container.
================================================================ */

/**
 * Shows one panel view and hides the others, with a scroll reset so
 * the newly-shown view always starts at the top of the panel.
 * @param {string} viewId  'accessView' | 'facultyView' | 'adminView'
 */
function showPanelView(viewId) {
    document.querySelectorAll('.panel-view').forEach(view => {
        view.classList.toggle('active', view.id === viewId);
    });
    resetPanelScroll();
}

function showAccessView() {
    showPanelView('accessView');
}

function showFacultyView() {
    showPanelView('facultyView');
}

function showAdminView() {
    showPanelView('adminView');
}

function resetPanelScroll() {
    const panel = document.querySelector('.gateway-panel');
    if (panel) panel.scrollTop = 0;
}

// Esc backs out of whichever login form is open, back to the role picker.
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        const openAuthView = document.querySelector('.panel-view-auth.active');
        if (openAuthView) showAccessView();
    }
});


/* ================================================================
   AUTO-DISMISS ALERTS
================================================================ */
setTimeout(() => {
    document.querySelectorAll('.auth-alert').forEach(el => {
        // Skip banners that are showing an active lockout countdown —
        // those must stay visible until the timer expires and reloads.
        if (el.dataset.lockoutActive === '1') return;
        el.style.transition = 'opacity 0.5s, max-height 0.4s, margin 0.4s';
        el.style.opacity = '0';
        el.style.maxHeight = '0';
        el.style.overflow = 'hidden';
        el.style.marginBottom = '0';
        setTimeout(() => el.remove(), 500);
    });
}, 5000);


/* ================================================================
   PASSWORD VISIBILITY TOGGLE
================================================================ */
function toggleEye(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        /* Currently hidden → reveal */
        input.type = 'text';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
        btn.setAttribute('aria-label', 'Hide password');
    } else {
        /* Currently visible → hide */
        input.type = 'password';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
        btn.setAttribute('aria-label', 'Show password');
    }
}

/* ================================================================
   EVENT LISTENER WIRING
   Replaces all inline onclick/oninput removed from HTML to comply
   with Content-Security-Policy (no unsafe-inline).
================================================================ */
document.addEventListener('DOMContentLoaded', function () {

    // Gateway — role access cards swap the panel's own content in place
    document.getElementById('gwStudentBtn')?.addEventListener('click', () => {
        window.location.href = 'student-dashboard.php';
    });
    document.getElementById('gwFacultyBtn')?.addEventListener('click', () => showFacultyView());
    document.getElementById('gwAdminBtn')?.addEventListener('click', () => showAdminView());

    // Back buttons — return to the role picker within the same panel
    document.getElementById('facultyBackBtn')?.addEventListener('click', () => showAccessView());
    document.getElementById('adminBackBtn')?.addEventListener('click', () => showAccessView());

    // Password eye-toggles — wired via data-target attribute
    document.querySelectorAll('.eye-toggle[data-target]').forEach(btn => {
        btn.addEventListener('click', function () {
            toggleEye(this.dataset.target, this);
        });
    });
});

/* ================================================================
   LOGIN RATE LIMITING — lockout countdown timer
   Runs when the page loads with an active lockout on either the
   Faculty or Admin login form. Counts down from the server-supplied
   seconds value (data-lockout-seconds on the disabled submit button),
   updates the <span> in the banner every second, and reloads when
   the lockout expires so the form is re-enabled without a manual
   refresh.

   Also suppresses the generic 5-second auto-dismiss on whichever
   banner is showing a lockout — that banner must stay visible for
   the full lockout duration.
================================================================ */
(function () {
    'use strict';

    function initLockoutCountdown(btnId, spanId) {
        const btn = document.getElementById(btnId);
        const span = document.getElementById(spanId);

        if (!btn || !span || !btn.hasAttribute('data-lockout-seconds')) return;

        let secondsLeft = parseInt(btn.getAttribute('data-lockout-seconds'), 10);
        if (isNaN(secondsLeft) || secondsLeft <= 0) return;

        const banner = span.closest('.auth-alert');
        if (banner) banner.dataset.lockoutActive = '1';

        function formatTime(s) {
            const m = Math.floor(s / 60);
            const sec = s % 60;
            return String(m).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
        }

        span.textContent = formatTime(secondsLeft);

        const interval = setInterval(function () {
            secondsLeft -= 1;

            if (secondsLeft <= 0) {
                clearInterval(interval);
                window.location.reload();
                return;
            }

            span.textContent = formatTime(secondsLeft);
        }, 1000);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initLockoutCountdown('facultySubmitBtn', 'lockout-countdown-faculty');
        initLockoutCountdown('adminSubmitBtn', 'lockout-countdown-admin');
    });

}());
