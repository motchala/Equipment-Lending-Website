/* ════════════════════════════════════════════════════════════════════
   logout-modal.js
   Shared by: admin-dashboard.php, faculty-dashboard.php
   Pairs with: assets/css/logout-modal.css

   Public API (window.PSLogout):
     PSLogout.open(triggerEl?)  – show the "Log out?" confirmation dialog
     PSLogout.close()           – dismiss it (ignored while logging out)
     PSLogout.isOpen()          – boolean

   Flow:
     1. open()    → themed confirmation dialog (replaces window.confirm)
     2. confirm   → dialog switches to a loading state; the session is
                    ended on the server IMMEDIATELY (api/logout.php), while
                    the animation plays for a minimum time so the change
                    from dashboard → landing page is never abrupt
     3. finished  → brief "logged out" check, then redirect to the landing page

   Security notes:
     • DOM is built with createElement / textContent only (no innerHTML).
     • The session is destroyed server-side as soon as the user confirms;
       closing the tab mid-animation cannot leave the session alive.
     • If the background request fails, we fall back to a normal navigation
       to api/logout.php, so the user can never be stuck "logged in".
     • window.location.replace() removes the dashboard from history so the
       Back button doesn't return to it.

   Optional override (set BEFORE this file loads):
     window.PS_LOGOUT_CONFIG = { logoutUrl, landingUrl, minLoadingMs, successMs };
   ════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    if (window.PSLogout) return; // never initialise twice

    var CFG = {
        logoutUrl: 'api/logout.php',
        landingUrl: 'landing-page.php',
        minLoadingMs: 1600,   // minimum time the loading state is shown
        successMs: 650        // how long the "logged out" check is shown
    };
    if (window.PS_LOGOUT_CONFIG && typeof window.PS_LOGOUT_CONFIG === 'object') {
        Object.keys(window.PS_LOGOUT_CONFIG).forEach(function (k) {
            if (k in CFG) CFG[k] = window.PS_LOGOUT_CONFIG[k];
        });
    }

    var root = null;      // overlay element
    var card = null;      // dialog element
    var els = {};         // cached child elements
    var state = 'closed'; // closed | confirm | loading | done
    var lastFocus = null;
    var closeTimer = null;

    /* ── tiny DOM helper ─────────────────────────────────────────── */
    function h(tag, props, kids) {
        var node = document.createElement(tag);
        if (props) {
            Object.keys(props).forEach(function (k) {
                if (k === 'class') node.className = props[k];
                else if (k === 'text') node.textContent = props[k];
                else node.setAttribute(k, props[k]);
            });
        }
        (kids || []).forEach(function (kid) { if (kid) node.appendChild(kid); });
        return node;
    }

    function icon(name) {
        var s = h('span', { class: 'material-symbols-outlined', 'aria-hidden': 'true', text: name });
        return s;
    }

    /* ── Build the dialog once, lazily ───────────────────────────── */
    function build() {
        if (root) return;

        els.title = h('h2', { class: 'ps-lo__title', id: 'ps-lo-title', text: 'Log out?' });
        els.desc = h('p', {
            class: 'ps-lo__desc', id: 'ps-lo-desc',
            text: 'You\u2019ll be signed out of your account.'
        });
        els.cancel = h('button', { class: 'ps-lo__btn ps-lo__btn--ghost', type: 'button', 'data-lo': 'cancel', text: 'Cancel' });
        els.confirm = h('button', { class: 'ps-lo__btn ps-lo__btn--primary', type: 'button', 'data-lo': 'confirm' },
            [icon('logout'), h('span', { text: 'Log out' })]);

        var confirmView = h('div', { class: 'ps-lo__view ps-lo__view--confirm' }, [
            h('div', { class: 'ps-lo__badge' }, [icon('logout')]),
            els.title,
            els.desc,
            h('div', { class: 'ps-lo__actions' }, [els.cancel, els.confirm])
        ]);

        els.busyTitle = h('h2', { class: 'ps-lo__title', id: 'ps-lo-busy-title', text: 'Logging you out\u2026' });
        els.busyDesc = h('p', { class: 'ps-lo__desc', text: 'Securely ending your session.' });

        var busyView = h('div', { class: 'ps-lo__view ps-lo__view--busy', role: 'status', 'aria-live': 'polite' }, [
            h('div', { class: 'ps-lo__badge' }, [
                h('div', { class: 'ps-lo__spin' }, [icon('lock')]),
                h('div', { class: 'ps-lo__check' }, [icon('check_circle')])
            ]),
            els.busyTitle,
            els.busyDesc,
            h('div', { class: 'ps-lo__bar', 'aria-hidden': 'true' })
        ]);

        card = h('div', {
            class: 'ps-lo__card', role: 'alertdialog', 'aria-modal': 'true',
            'aria-labelledby': 'ps-lo-title', 'aria-describedby': 'ps-lo-desc',
            tabindex: '-1', 'data-state': 'confirm'
        }, [confirmView, busyView]);

        root = h('div', { class: 'ps-lo', id: 'ps-logout-root', hidden: '' }, [card]);

        // Direct child of <body> — never inside a positioned/transformed container
        document.body.appendChild(root);

        root.addEventListener('click', onRootClick);
    }

    function setState(next) {
        state = next;
        card.setAttribute('data-state', next);
        if (next === 'confirm') {
            card.setAttribute('aria-labelledby', 'ps-lo-title');
            card.setAttribute('aria-describedby', 'ps-lo-desc');
        } else {
            card.setAttribute('aria-labelledby', 'ps-lo-busy-title');
            card.removeAttribute('aria-describedby');
        }
    }

    /* ── Open / close ────────────────────────────────────────────── */
    function open(trigger) {
        if (state !== 'closed') return;
        build();

        clearTimeout(closeTimer);
        lastFocus = trigger || document.activeElement;
        setState('confirm');

        root.hidden = false;
        void root.offsetWidth; // force reflow so the transition runs
        root.classList.add('is-open');

        document.addEventListener('keydown', onKeydown, true);
        els.cancel.focus(); // safest default: the non-destructive choice
    }

    function close() {
        if (state !== 'confirm') return; // cannot be dismissed while logging out
        state = 'closed';
        root.classList.remove('is-open');
        document.removeEventListener('keydown', onKeydown, true);

        closeTimer = setTimeout(function () { root.hidden = true; }, 240);

        // Return focus to where the user was (the trigger may have been
        // inside a dropdown that has since closed — fall back to the account tab)
        var target = lastFocus;
        if (!target || !document.contains(target) || target.offsetParent === null) {
            target = document.getElementById('navAccountToggle') || document.getElementById('avatarBtn');
        }
        if (target && typeof target.focus === 'function') target.focus();
        lastFocus = null;
    }

    /* ── Events ──────────────────────────────────────────────────── */
    function onRootClick(e) {
        if (state !== 'confirm') return;
        if (e.target === root) { close(); return; }              // backdrop click
        var btn = e.target.closest('[data-lo]');
        if (!btn) return;
        if (btn.getAttribute('data-lo') === 'cancel') close();
        else if (btn.getAttribute('data-lo') === 'confirm') startLogout();
    }

    function onKeydown(e) {
        if (state === 'closed') return;

        if (e.key === 'Escape') {
            if (state === 'confirm') { e.preventDefault(); e.stopPropagation(); close(); }
            return;
        }

        if (e.key === 'Tab') {
            // Focus trap: keep keyboard focus inside the dialog
            if (state !== 'confirm') { e.preventDefault(); card.focus(); return; }
            var first = els.cancel, last = els.confirm;
            var active = document.activeElement;
            if (e.shiftKey && (active === first || !card.contains(active))) {
                e.preventDefault(); last.focus();
            } else if (!e.shiftKey && (active === last || !card.contains(active))) {
                e.preventDefault(); first.focus();
            }
        }
    }

    /* ── Logout sequence ─────────────────────────────────────────── */
    function wait(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    // Ends the session server-side right away. Resolves true on success,
    // false if the request could not be completed.
    function endSession() {
        if (!window.fetch) return Promise.resolve(false);
        return fetch(CFG.logoutUrl, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            redirect: 'manual' // we do our own redirect after the animation
        }).then(function (res) {
            // logout.php answers with a redirect → "opaqueredirect" in fetch
            return res.type === 'opaqueredirect' || res.ok;
        }).catch(function () {
            return false;
        });
    }

    function startLogout() {
        if (state !== 'confirm') return;
        setState('loading');
        card.focus(); // move focus off the (now hidden) buttons

        Promise.all([endSession(), wait(CFG.minLoadingMs)]).then(function (out) {
            if (!out[0]) {
                // Background request failed → fall back to the classic navigation
                window.location.href = CFG.logoutUrl;
                return;
            }
            setState('done');
            els.busyTitle.textContent = 'You\u2019ve been logged out';
            els.busyDesc.textContent = 'Redirecting you to the landing page\u2026';
            return wait(CFG.successMs).then(function () {
                window.location.replace(CFG.landingUrl);
            });
        });
    }

    window.PSLogout = {
        open: open,
        close: close,
        isOpen: function () { return state !== 'closed'; }
    };
})();