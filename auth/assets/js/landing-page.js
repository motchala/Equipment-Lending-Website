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
    // Password eye-toggle — wired via data-target attribute
    document.querySelectorAll('.eye-toggle[data-target]').forEach(btn => {
        btn.addEventListener('click', function () {
            toggleEye(this.dataset.target, this);
        });
    });
});

/* ================================================================
   LOGIN RATE LIMITING — lockout countdown timer
   Runs when the page loads with an active lockout on the login
   form. Counts down from the server-supplied seconds value
   (data-lockout-seconds on the disabled submit button), updates the
   <span> in the banner every second, and reloads when the lockout
   expires so the form is re-enabled without a manual refresh.

   Also suppresses the generic 5-second auto-dismiss on the banner
   while it's showing a lockout — that banner must stay visible for
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
        initLockoutCountdown('loginSubmitBtn', 'lockout-countdown');
    });

}());

/* ================================================================
   SIGN-IN DEPARTURE ANIMATION
   Fires on submit, in parallel with the real POST — never calls
   preventDefault(), so the form still submits normally. Swaps the
   button's icon/label to a "signing in" state and fades the gateway
   panel out, so the transition into the destination dashboard's own
   role-splash overlay reads as one continuous handoff. If login
   fails, the PRG redirect back to this same page simply reloads a
   fresh, un-faded gateway — nothing to reset manually.
================================================================ */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('loginForm');
    const gateway = document.getElementById('gateway');
    const icon = document.getElementById('loginSubmitIcon');
    const label = document.getElementById('loginSubmitLabel');

    if (!form || !gateway) return;

    form.addEventListener('submit', function (e) {
        const submitBtn = document.getElementById('loginSubmitBtn');
        if (submitBtn && submitBtn.hasAttribute('disabled')) return; // locked out

        if (icon) {
            icon.classList.remove('fa-arrow-right-to-bracket');
            icon.classList.add('fa-circle-notch', 'is-spinning');
        }
        if (label) label.textContent = 'Signing in\u2026';

        gateway.classList.add('is-leaving');
    });

    const studentLink = document.getElementById('studentLink');
    if (studentLink) {
        studentLink.addEventListener('click', function () {
            gateway.classList.add('is-leaving');
        });
    }
});
