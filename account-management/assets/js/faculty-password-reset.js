/* ================================================================
   faculty-password-reset.js
   Super Admin > Faculty > Edit > "Reset password", plus the
   "Reset requested" mark on faculty rows.

   The server decides everything (account-management/api/*reset*.php):
     - faculty-reset-status.php    -> may this account be reset yet? (needs 5 wrong sign-in attempts)
     - reset-faculty-password.php  -> checks YOUR password, generates + saves the new one
     - faculty-reset-requests.php  -> which faculty are asking right now (a correct sign-in withdraws it)
   This file only shows those answers.
================================================================ */
(function () {
    'use strict';

    var API = 'account-management/api/';
    var POLL_MS = 25000;

    var modal = document.getElementById('fac-edit-modal');
    var table = document.getElementById('fac-list-table');
    if (!modal) return;

    function $(id) { return document.getElementById(id); }
    function show(id) { var e = $(id); if (e) e.classList.remove('hidden'); }
    function hide(id) { var e = $(id); if (e) e.classList.add('hidden'); }
    function csrf() {
        var el = document.querySelector('input[name="csrf_token"]');
        return el ? el.value : '';
    }

    var openBtn = $('fac-reset-open');
    var pwInput = $('fac-reset-password');
    var genBtn = $('fac-reset-generate');
    var errBox = $('fac-reset-error');
    var VIEWS = ['checking', 'blocked', 'ready', 'done'];

    var row = null;            // the <tr> being edited
    var openSeq = 0;           // guards against a slow answer for a previously opened faculty
    var autoOpen = false;      // opened from a notification: go straight to the reset panel
    var requests = {};         // email (lower-case) -> "requested at" label
    var busy = false;

    /* ---------- the panel inside the modal ---------- */

    function setView(name) {
        VIEWS.forEach(function (v) {
            if (v === name) show('fac-reset-view-' + v); else hide('fac-reset-view-' + v);
        });
    }
    function showError(msg) {
        if (!errBox) return;
        errBox.textContent = msg || '';
        errBox.classList.toggle('hidden', !msg);
    }
    function name() { return (row && row.dataset.fullname) || 'This faculty member'; }

    function resetPanel() {
        busy = false;
        hide('fac-reset-section');         // hidden unless this faculty has an open reset request
        hide('fac-reset-panel');
        VIEWS.forEach(function (v) { hide('fac-reset-view-' + v); });
        showError('');
        if (pwInput) pwInput.value = '';
        if (genBtn) genBtn.disabled = true;
        var out = $('fac-reset-result');
        if (out) out.value = '';          // the generated password never lingers
        var lbl = $('fac-reset-copy-label');
        if (lbl) lbl.textContent = 'Copy';
        if (openBtn) openBtn.disabled = false;
    }

    // Show the Password section only if the server says this faculty used all attempts AND pressed Notify.
    function revealIfRequested() {
        if (!row) return;
        var seq = ++openSeq;
        var want = autoOpen;
        autoOpen = false;
        fetch(API + 'faculty-reset-status.php?faculty_id=' + encodeURIComponent(row.dataset.facultyId || ''), {
            credentials: 'same-origin', cache: 'no-store'
        })
            .then(function (r) { return r.json().catch(function () { return {}; }); })
            .then(function (d) {
                if (seq !== openSeq || !modal.classList.contains('ps-modal-open')) return;
                if (!d || d.status !== 'success' || !d.requested) return;
                show('fac-reset-section');
                if (want) checkStatus();
            })
            .catch(function () { /* stay hidden */ });
    }

    function drawDots(n, max) {
        var box = $('fac-reset-dots');
        if (!box) return;
        box.textContent = '';
        for (var i = 0; i < max; i++) {
            var d = document.createElement('span');
            d.className = 'fac-reset-dot' + (i < n ? ' is-on' : '');
            box.appendChild(d);
        }
    }

    function checkStatus() {
        if (!row) return;
        showError('');
        show('fac-reset-panel');
        setView('checking');
        fetch(API + 'faculty-reset-status.php?faculty_id=' + encodeURIComponent(row.dataset.facultyId || ''), {
            credentials: 'same-origin', cache: 'no-store'
        })
            .then(function (r) { return r.json().catch(function () { return {}; }); })
            .then(function (d) {
                if (!d || d.status !== 'success') {
                    setView('');
                    showError((d && d.message) || 'Could not check this account. Please try again.');
                    return;
                }
                if (!d.eligible) {
                    var t = $('fac-reset-blocked-text');
                    if (t) {
                        t.textContent = d.fail_count > 0
                            ? name() + ' has used ' + d.fail_count + ' of ' + d.max + ' wrong sign-in attempts. A reset unlocks only after all ' + d.max + '.'
                            : name() + ' has no failed sign-in attempts. A reset unlocks only after ' + d.max + ' wrong passwords in a row.';
                    }
                    drawDots(d.fail_count, d.max);
                    setView('blocked');
                    return;
                }
                var r = $('fac-reset-ready-text');
                if (r) {
                    r.textContent = name() + ' used all ' + d.max + ' attempts'
                        + (d.notified_at ? ' and asked for a reset on ' + d.notified_at : '')
                        + '. The new password will replace their current one.';
                }
                setView('ready');
                if (pwInput) { pwInput.value = ''; pwInput.focus(); }
                if (genBtn) genBtn.disabled = true;
            })
            .catch(function () {
                setView('');
                showError('Network error. Please try again.');
            });
    }

    function generate() {
        if (busy || !row || !pwInput || !pwInput.value) return;
        busy = true;
        genBtn.disabled = true;
        showError('');
        var body = new URLSearchParams({
            csrf_token: csrf(),
            faculty_id: row.dataset.facultyId || '',
            current_password: pwInput.value
        });
        fetch(API + 'reset-faculty-password.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body.toString()
        })
            .then(function (r) { return r.json().catch(function () { return {}; }); })
            .then(function (d) {
                busy = false;
                if (d && d.status === 'success' && d.password) {
                    $('fac-reset-result').value = d.password;
                    pwInput.value = '';
                    setView('done');
                    if (openBtn) openBtn.disabled = true;      // never re-check (and wipe) the shown password
                    var email = ((row && row.dataset.email) || '').toLowerCase();
                    delete requests[email];
                    applyMarks();
                    if (typeof showToast === 'function') showToast('New password generated.');
                    return;
                }
                showError((d && d.message) || 'Could not reset this password. Please try again.');
                if (d && d.code === 'not_eligible') { checkStatus(); return; }
                pwInput.value = '';
                pwInput.focus();
                genBtn.disabled = true;
            })
            .catch(function () {
                busy = false;
                genBtn.disabled = !pwInput.value;
                showError('Network error. Please try again.');
            });
    }

    function copyPassword() {
        var out = $('fac-reset-result');
        if (!out || !out.value) return;
        function done() {
            var lbl = $('fac-reset-copy-label');
            if (lbl) {
                lbl.textContent = 'Copied';
                setTimeout(function () { lbl.textContent = 'Copy'; }, 1800);
            }
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(out.value).then(done, function () { fallbackCopy(out); done(); });
        } else {
            fallbackCopy(out); done();
        }
    }
    function fallbackCopy(input) {
        input.focus();
        input.select();
        try { document.execCommand('copy'); } catch (e) { /* the field stays selected for Ctrl+C */ }
    }

    /* ---------- wiring ---------- */

    document.addEventListener('click', function (e) {
        var edit = e.target.closest('#fac-list-table .fac-edit-btn');
        if (edit) {                                   // a faculty row's Edit button opened the modal
            row = edit.closest('tr');
            resetPanel();
            revealIfRequested();
        }
    });

    if (openBtn) openBtn.addEventListener('click', checkStatus);
    if (pwInput) {
        pwInput.addEventListener('input', function () { genBtn.disabled = busy || !pwInput.value; });
        pwInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); if (!genBtn.disabled) generate(); }
        });
    }
    if (genBtn) genBtn.addEventListener('click', generate);
    var copyBtn = $('fac-reset-copy');
    if (copyBtn) copyBtn.addEventListener('click', copyPassword);

    // Closing the modal (any way) wipes the panel, including a shown password.
    new MutationObserver(function () {
        if (!modal.classList.contains('ps-modal-open')) resetPanel();
    }).observe(modal, { attributes: true, attributeFilter: ['class'] });

    /* ---------- "Reset requested" mark on faculty rows ---------- */

    var marksObserver = null;

    function applyMarks() {
        if (!table) return;
        if (marksObserver) marksObserver.disconnect();
        table.querySelectorAll('tbody tr[data-email]').forEach(function (tr) {
            var label = requests[(tr.dataset.email || '').toLowerCase()];
            var flag = tr.querySelector('.fac-reset-flag');
            if (label) {
                tr.classList.add('fac-row--reset-request');
                if (!flag) {
                    var host = tr.querySelector('.fac-person-name');
                    if (host) {
                        flag = document.createElement('span');
                        flag.className = 'fac-reset-flag';
                        var ic = document.createElement('span');
                        ic.className = 'material-symbols-outlined';
                        ic.textContent = 'lock_reset';
                        flag.appendChild(ic);
                        flag.appendChild(document.createTextNode('Reset requested'));
                        host.appendChild(flag);
                    }
                }
                if (flag) flag.title = 'Asked for a password reset on ' + label;
            } else {
                tr.classList.remove('fac-row--reset-request');
                if (flag) flag.remove();
            }
        });
        if (marksObserver) marksObserver.observe(table, { childList: true, subtree: true });
    }

    var pollTimer = null;
    function loadRequests() {
        fetch(API + 'faculty-reset-requests.php', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) {
                if (r.status === 401 || r.status === 403) { stopPolling(); return null; }
                return r.json().catch(function () { return null; });
            })
            .then(function (d) {
                if (!d || d.status !== 'success') return;
                var next = {};
                (d.requests || []).forEach(function (q) { next[q.email] = q.requested_at; });
                requests = next;
                applyMarks();
            })
            .catch(function () { /* try again on the next tick */ });
    }
    function stopPolling() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

    if (table) {
        marksObserver = new MutationObserver(applyMarks);
        marksObserver.observe(table, { childList: true, subtree: true });
        loadRequests();
        pollTimer = setInterval(function () {
            if (document.visibilityState === 'visible') loadRequests();
        }, POLL_MS);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible' && pollTimer) loadRequests();
        });
    }

    /* ---------- from a notification: open that faculty's account on the reset panel ---------- */

    window.psFacOpenResetFor = function (email) {
        if (!table || !email) return;
        var want = String(email).toLowerCase();
        var target = null;
        table.querySelectorAll('tbody tr[data-email]').forEach(function (tr) {
            if ((tr.dataset.email || '').toLowerCase() === want) target = tr;
        });
        var btn = target && target.querySelector('.fac-edit-btn');
        if (!btn) return;
        autoOpen = true;                   // revealIfRequested() opens the panel once the section is allowed
        btn.click();
    };
}());
