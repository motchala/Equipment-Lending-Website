/* ==========================================================================
   faculty-first-login.js - behaviour of faculty-setup.php (the one-time
   "set up your account" screen).

   Step 1 checks the new password in the browser (same rules as the server,
   see faculty_fl_password_level() in account-management/core/faculty-first-login.php),
   step 2 collects the essential details, and ONE request to
   account-management/api/first-login-setup.php saves everything. The server
   re-checks every rule; the checks here are only there to be helpful.
   ========================================================================== */
(function () {
    'use strict';

    var form = document.getElementById('fsForm');
    if (!form) return;

    var DASHBOARD_URL = '../faculty-dashboard.php';
    var LANDING_URL = '../landing-page.php';
    var REDIRECT_DELAY_MS = 1700;   // keep in step with the .fs-done-bar animation (1.7s)
    var STEP_NAMES = { 1: 'Secure your account', 2: 'Your details' };
    var STEP1_FIELDS = ['new_password', 'confirm_password'];

    var $ = function (id) { return document.getElementById(id); };
    var el = {
        newPw: $('fsNewPassword'),
        confirmPw: $('fsConfirmPassword'),
        dept: $('fsDepartment'),
        backup: $('fsBackupEmail'),
        meter: $('fsMeter'),
        meterLabel: $('fsMeterLabel'),
        rules: $('fsPwHelp'),
        confirmHint: $('fsConfirmHint'),
        alert: $('fsAlert'),
        back: $('fsBack'),
        next: $('fsNext'),
        submit: $('fsSubmit'),
        progress: $('fsProgress'),
        progressLabel: $('fsProgressLabel'),
        progressName: $('fsProgressName'),
        progressFill: $('fsProgressFill'),
        done: $('fsDone'),
        doneLink: $('fsDoneLink')
    };

    var step = 1;
    var submitting = false;

    /* ── Password strength: a copy of the server's scoring ──────────────── */
    var WEAK_PATTERNS = [
        '0123', '1234', '2345', '3456', '4567', '5678', '6789', '7890',
        '9876', '8765', '7654', '6543', '5432', '4321', '3210',
        'abcd', 'bcde', 'cdef', 'defg', 'efgh', 'fghi', 'ghij', 'hijk',
        'ijkl', 'jklm', 'klmn', 'lmno', 'mnop', 'nopq', 'opqr', 'pqrs',
        'qrst', 'rstu', 'stuv', 'tuvw', 'uvwx', 'vwxy', 'wxyz',
        'dcba', 'edcb', 'fedc', 'gfed', 'hgfe', 'ihgf', 'jihg', 'kjih',
        'lkji', 'mlkj', 'nmlk', 'onml', 'ponm', 'qpon', 'rqpo', 'srqp',
        'tsrq', 'utsr', 'vuts', 'wvut', 'xwvu', 'yxwv', 'zyxw',
        'qwer', 'wert', 'erty', 'asdf', 'sdfg', 'dfgh', 'zxcv', 'xcvb',
        'password', 'passw0rd', 'qwerty', 'welcome', 'letmein', 'admin',
        'iloveyou', 'monkey', 'dragon', 'football', 'baseball', 'master'
    ];
    var LEVEL_LABELS = ['Very weak', 'Weak', 'Fair', 'Strong', 'Very strong'];

    function byteLength(s) {
        try { return new TextEncoder().encode(s).length; } catch (e) { return s.length; }
    }

    /* → { bytes, lenOk, easyOk, mixOk, level } ; level -1 = too short, else 0..4 like the server */
    function evaluate(pw) {
        var bytes = byteLength(pw);
        var res = { bytes: bytes, lenOk: bytes >= 8, easyOk: false, mixOk: false, level: -1 };
        if (!res.lenOk) return res;

        var lower = pw.toLowerCase();
        var weak = /(.)\1{3,}/i.test(pw) || WEAK_PATTERNS.some(function (p) { return lower.indexOf(p) !== -1; });
        var classes = 0;
        if (/[a-z]/.test(pw)) classes++;
        if (/[A-Z]/.test(pw)) classes++;
        if (/[0-9]/.test(pw)) classes++;
        if (/[^A-Za-z0-9]/.test(pw)) classes++;

        res.easyOk = !weak;
        res.mixOk = classes >= 2 || bytes >= 12;

        if (weak) { res.level = 1; return res; }
        var score = (bytes >= 16 ? 3 : (bytes >= 12 ? 2 : 1)) + classes;
        res.level = score <= 1 ? 0 : (score <= 2 ? 1 : (score === 3 ? 2 : (score <= 5 ? 3 : 4)));
        return res;
    }

    function renderStrength() {
        var pw = el.newPw.value;
        var r = evaluate(pw);

        el.meter.setAttribute('data-level', String(pw ? r.level : -1));
        el.meterLabel.textContent = !pw || r.level < 0
            ? 'Enter at least 8 characters'
            : LEVEL_LABELS[r.level];

        var met = { len: r.lenOk, easy: r.lenOk && r.easyOk, mix: r.lenOk && r.mixOk };
        Array.prototype.forEach.call(el.rules.querySelectorAll('li'), function (li) {
            var ok = !!met[li.getAttribute('data-rule')];
            li.classList.toggle('is-met', ok);
            var icon = li.querySelector('.material-symbols-outlined');
            if (icon) icon.textContent = ok ? 'check_circle' : 'radio_button_unchecked';
        });
    }

    function renderConfirmHint() {
        var a = el.newPw.value, b = el.confirmPw.value;
        el.confirmHint.classList.remove('is-ok', 'is-bad');
        if (!b) { el.confirmHint.textContent = ''; return; }
        if (a === b) {
            el.confirmHint.textContent = 'Passwords match';
            el.confirmHint.classList.add('is-ok');
        } else {
            el.confirmHint.textContent = 'Passwords don\u2019t match yet';
            el.confirmHint.classList.add('is-bad');
        }
    }

    /* ── Errors ─────────────────────────────────────────────────────────── */
    function fieldBox(name) { return form.querySelector('.fs-field[data-field="' + name + '"]'); }

    function setFieldError(name, msg) {
        var box = fieldBox(name);
        if (!box) return false;
        var err = box.querySelector('.fs-err');
        box.classList.add('has-error');
        var input = box.querySelector('input, select');
        if (input) input.setAttribute('aria-invalid', 'true');
        if (err) { err.textContent = msg; err.hidden = false; }
        return true;
    }

    function clearFieldError(name) {
        var box = fieldBox(name);
        if (!box) return;
        box.classList.remove('has-error');
        var input = box.querySelector('input, select');
        if (input) input.removeAttribute('aria-invalid');
        var err = box.querySelector('.fs-err');
        if (err) { err.hidden = true; err.textContent = ''; }
    }

    function clearAllErrors() {
        Array.prototype.forEach.call(form.querySelectorAll('.fs-field'), function (box) {
            clearFieldError(box.getAttribute('data-field'));
        });
        hideAlert();
    }

    function showAlert(msg) { el.alert.textContent = msg; el.alert.hidden = false; }
    function hideAlert() { el.alert.hidden = true; el.alert.textContent = ''; }

    function focusField(name) {
        var box = fieldBox(name);
        var input = box && box.querySelector('input, select');
        if (input) input.focus();
    }

    /* ── Steps ──────────────────────────────────────────────────────────── */
    function goTo(n, isBack, moveFocus) {
        step = n;
        Array.prototype.forEach.call(form.querySelectorAll('.fs-step'), function (s) {
            var on = Number(s.getAttribute('data-step')) === n;
            s.hidden = !on;
            s.classList.toggle('is-active', on);
            s.classList.toggle('is-back', on && !!isBack);
        });
        Array.prototype.forEach.call(document.querySelectorAll('[data-step-indicator]'), function (li) {
            var k = Number(li.getAttribute('data-step-indicator'));
            li.classList.toggle('is-current', k === n);
            li.classList.toggle('is-done', k < n);
            if (k === n) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
        });
        el.progressLabel.textContent = 'Step ' + n + ' of 2';
        el.progressName.textContent = STEP_NAMES[n];
        el.progressFill.setAttribute('data-step', String(n));

        el.back.hidden = n === 1;
        el.next.hidden = n !== 1;
        el.submit.hidden = n !== 2;
        hideAlert();

        if (moveFocus) {
            var title = $(n === 1 ? 'fsStep1Title' : 'fsStep2Title');
            if (title) title.focus({ preventScroll: false });
            try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (e) { /* older browsers */ }
        }
    }

    /* ── Validation (the server repeats all of it) ──────────────────────── */
    function validateStep1() {
        clearFieldError('new_password');
        clearFieldError('confirm_password');
        var pw = el.newPw.value, cf = el.confirmPw.value;
        var r = evaluate(pw);

        if (!pw) return fail('new_password', 'Choose a new password.');
        if (!r.lenOk) return fail('new_password', 'Your password must be at least 8 characters.');
        if (r.bytes > 72) return fail('new_password', 'Your password is too long (72 characters at most).');
        if (r.level < 2) {
            return fail('new_password', 'That password is too easy to guess. Avoid common words, repeated characters and sequences like 1234, and mix letters with numbers or symbols.');
        }
        if (!cf) return fail('confirm_password', 'Re-enter your new password to confirm it.');
        if (pw !== cf) return fail('confirm_password', 'The two passwords do not match.');
        return true;
    }

    function fail(field, msg) {
        setFieldError(field, msg);
        focusField(field);
        return false;
    }

    function validateStep2() {
        ['department', 'backup_email'].forEach(clearFieldError);

        if (el.dept && el.dept.tagName === 'SELECT' && !el.dept.value) {
            return fail('department', 'Select your department.');
        }

        if (el.backup && el.backup.name === 'backup_email') {
            var be = el.backup.value.trim();
            if (be && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(be)) {
                return fail('backup_email', 'Enter a valid backup email address, or leave it empty.');
            }
        }
        return true;
    }

    /* ── Submit ─────────────────────────────────────────────────────────── */
    function setBusy(busy) {
        submitting = busy;
        el.submit.classList.toggle('is-loading', busy);
        el.submit.disabled = busy;
        el.back.disabled = busy;
        el.submit.querySelector('.fs-btn-label').textContent = busy ? 'Saving\u2026' : 'Save & open my portal';
    }

    function showSuccess() {
        form.hidden = true;
        el.progress.hidden = true;
        el.done.hidden = false;
        Array.prototype.forEach.call(document.querySelectorAll('[data-step-indicator]'), function (li) {
            li.classList.remove('is-current');
            li.classList.add('is-done');
        });
        window.setTimeout(function () { window.location.replace(DASHBOARD_URL); }, REDIRECT_DELAY_MS);
        if (el.doneLink) el.doneLink.focus();
    }

    function save() {
        if (submitting) return;
        if (!validateStep1()) { goTo(1, true, false); validateStep1(); return; }
        if (!validateStep2()) return;

        hideAlert();
        setBusy(true);

        var body = new URLSearchParams(new FormData(form));
        fetch(form.getAttribute('data-endpoint'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body.toString()
        })
            .then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    return { status: res.status, data: data };
                });
            })
            .then(function (r) {
                var d = r.data || {};
                if (d.success) { showSuccess(); return; }

                if (r.status === 401) { window.location.replace(LANDING_URL); return; }

                setBusy(false);
                var msg = d.msg || d.message || d.error || 'We could not save your details. Please try again.';
                var field = d.field || '';
                if (field && STEP1_FIELDS.indexOf(field) !== -1) {
                    goTo(1, true, false);
                    setFieldError(field, msg);
                    focusField(field);
                } else if (field && setFieldError(field, msg)) {
                    focusField(field);
                } else {
                    showAlert(msg);
                }
            })
            .catch(function () {
                setBusy(false);
                showAlert('We couldn\u2019t reach the server. Check your connection and try again.');
            });
    }

    /* ── Wiring ─────────────────────────────────────────────────────────── */
    el.next.addEventListener('click', function () {
        if (validateStep1()) { goTo(2, false, true); }
    });

    el.back.addEventListener('click', function () {
        if (!submitting) goTo(1, true, true);
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();                      // Enter on step 1 goes to step 2, on step 2 it saves
        if (step === 1) { if (validateStep1()) goTo(2, false, true); }
        else save();
    });

    el.newPw.addEventListener('input', function () {
        renderStrength();
        renderConfirmHint();
        clearFieldError('new_password');
        if (el.confirmPw.value) clearFieldError('confirm_password');
    });
    el.confirmPw.addEventListener('input', function () {
        renderConfirmHint();
        clearFieldError('confirm_password');
    });
    if (el.dept) el.dept.addEventListener('change', function () { clearFieldError('department'); });
    if (el.backup) el.backup.addEventListener('input', function () { clearFieldError('backup_email'); });

    // show / hide password
    Array.prototype.forEach.call(document.querySelectorAll('[data-eye-for]'), function (btn) {
        btn.addEventListener('click', function () {
            var input = $(btn.getAttribute('data-eye-for'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.querySelector('.material-symbols-outlined').textContent = show ? 'visibility_off' : 'visibility';
        });
    });

    renderStrength();
    renderConfirmHint();
    goTo(1, false, false);
    el.newPw.focus();
})();
