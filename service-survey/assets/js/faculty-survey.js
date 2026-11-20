/**
 * service-survey/assets/js/faculty-survey.js
 *
 * Faculty portal: the anonymous "How was our service?" pop-up.
 *
 *  1. A couple of seconds after the page loads (and every couple of minutes while the tab is visible) it asks
 *     service-survey/api/survey-status.php whether one of the faculty's own completed transactions is waiting
 *     to be rated (transactions a student made with the faculty's code never count).
 *  2. If one is, it builds one star row per question (1–5, 5 = most satisfied) plus the optional comment box.
 *  3. Submit goes to service-survey/api/survey-submit.php. Nothing identifying is ever added to the answer.
 *
 * The pop-up CANNOT be skipped: no Skip / X button, Escape and clicks outside it do nothing, focus stays inside
 * it, and nothing hides it again once it is open. A refresh does not get rid of it either: the transaction is only
 * used up when an answer is submitted, so the pop-up is shown again a couple of seconds after the page reloads.
 * It closes only after an answer is saved (or if the server says the survey is switched off / no longer applies).
 *
 * It stays out of the way: it never opens over another dialog and never while the tab is hidden. After an
 * answer it waits COOLDOWN_MS before checking for another transaction.
 *
 * All text is inserted with textContent (never innerHTML).
 */
(function () {
    'use strict';

    var backdrop = document.getElementById('svyBackdrop');
    if (!backdrop) return;

    var API = 'service-survey/api/';
    var FIRST_CHECK_MS = 2500;
    var RETRY_MS = 3000;
    var POLL_MS = 120000;
    var COOLDOWN_MS = 10 * 60 * 1000;
    var WORDS = ['', 'Poor', 'Fair', 'Good', 'Very good', 'Excellent'];

    var box = backdrop.querySelector('.svy-box');
    var elSub = document.getElementById('svySub');
    var elQs = document.getElementById('svyQuestions');
    var elCommentWrap = document.getElementById('svyCommentWrap');
    var elComment = document.getElementById('svyComment');
    var elCount = document.getElementById('svyCount');
    var elError = document.getElementById('svyError');
    var btnSubmit = document.getElementById('svySubmit');

    var state = {
        open: false,
        busy: false,
        pending: null,      // { key, type, label }
        questions: [],      // [{ id, text }]
        commentOn: false,
        cooldownUntil: 0,
        retryTimer: null,
        lastFocus: null
    };

    /* ── helpers ─────────────────────────────────────────────── */
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') || '' : '';
    }

    function toast(msg, type) {
        if (typeof window.showToast === 'function') window.showToast(msg, type);
    }

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
    }

    function showError(msg) {
        if (!msg) { elError.hidden = true; elError.textContent = ''; return; }
        elError.textContent = msg;
        elError.hidden = false;
    }

    function otherDialogOpen() {
        var list = document.querySelectorAll('.modal-backdrop, .bf-rp-backdrop');
        for (var i = 0; i < list.length; i++) {
            var n = list[i];
            if (n === backdrop || n.hidden) continue;
            if (window.getComputedStyle(n).display !== 'none') return true;
        }
        return false;
    }

    /* ── status check ────────────────────────────────────────── */
    function fetchStatus() {
        return fetch(API + 'survey-status.php', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .catch(function () { return null; });
    }

    // Another dialog is open right now: look again shortly (the survey waits for it, it never covers it).
    function retrySoon() {
        if (state.retryTimer) return;
        state.retryTimer = setTimeout(function () {
            state.retryTimer = null;
            check();
        }, RETRY_MS);
    }

    function check() {
        if (state.open || state.busy) return;
        if (document.hidden) return;
        if (Date.now() < state.cooldownUntil) return;
        if (otherDialogOpen()) { retrySoon(); return; }

        fetchStatus().then(function (data) {
            if (state.open) return;
            if (!data || data.status !== 'success' || !data.enabled || !data.pending) return;
            if (otherDialogOpen()) { retrySoon(); return; }
            openSurvey(data);
        });
    }

    /* ── build the form ──────────────────────────────────────── */
    function buildQuestion(q, index) {
        var fs = el('fieldset', 'svy-q');
        fs.appendChild(el('legend', '', (index + 1) + '. ' + q.text));

        var row = el('div', 'svy-stars-row');
        var stars = el('div', 'svy-stars');
        var hint = el('span', 'svy-hint', '');
        hint.setAttribute('aria-live', 'polite');

        for (var n = 5; n >= 1; n--) {
            var id = 'svy_' + q.id + '_' + n;
            var input = el('input', 'svy-radio');
            input.type = 'radio';
            input.name = 'svy_q' + q.id;
            input.id = id;
            input.value = String(n);

            var label = el('label', 'svy-star');
            label.setAttribute('for', id);
            label.title = n + (n === 1 ? ' star' : ' stars') + ' (' + WORDS[n] + ')';
            var icon = el('span', 'material-symbols-outlined', 'star');
            icon.setAttribute('aria-hidden', 'true');
            label.appendChild(icon);
            var sr = el('span', 'svy-sr', n + (n === 1 ? ' star' : ' stars'));
            label.appendChild(sr);

            stars.appendChild(input);
            stars.appendChild(label);
        }

        stars.addEventListener('change', function (e) {
            var t = e.target;
            if (t && t.classList.contains('svy-radio')) {
                hint.textContent = WORDS[parseInt(t.value, 10)] || '';
                refreshSubmit();
                showError('');
            }
        });

        row.appendChild(stars);
        row.appendChild(hint);
        fs.appendChild(row);
        return fs;
    }

    function answers() {
        var out = {};
        for (var i = 0; i < state.questions.length; i++) {
            var q = state.questions[i];
            var c = elQs.querySelector('input[name="svy_q' + q.id + '"]:checked');
            if (c) out[q.id] = parseInt(c.value, 10);
        }
        return out;
    }

    function allAnswered() {
        var a = answers();
        for (var i = 0; i < state.questions.length; i++) {
            if (!a[state.questions[i].id]) return false;
        }
        return state.questions.length > 0;
    }

    function refreshSubmit() {
        btnSubmit.disabled = state.busy || !allAnswered();
    }

    function describe(p) {
        if (p.type === 'room') return 'your room reservation';
        return 'your equipment borrowing';
    }

    function openSurvey(data) {
        state.pending = data.pending;
        state.questions = data.questions || [];
        state.commentOn = !!data.comment_on || !!data.comment_enabled;

        // Intro line: what finished (the label is shown as text only)
        elSub.textContent = '';
        elSub.appendChild(document.createTextNode('Thanks for using PUPSync. Please rate the admin service for ' + describe(state.pending) + ' '));
        elSub.appendChild(el('strong', '', '(' + state.pending.label + ')'));
        elSub.appendChild(document.createTextNode('. It takes about 30 seconds.'));

        elQs.textContent = '';
        for (var i = 0; i < state.questions.length; i++) {
            elQs.appendChild(buildQuestion(state.questions[i], i));
        }

        elCommentWrap.hidden = !state.commentOn;
        elComment.value = '';
        elComment.maxLength = data.comment_max || 500;
        elCount.textContent = '0/' + elComment.maxLength;
        showError('');
        state.busy = false;
        refreshSubmit();

        if (!state.open) state.lastFocus = document.activeElement;
        backdrop.hidden = false;
        state.open = true;
        box.focus();
    }

    /* ── close ───────────────────────────────────────────────── */
    // Only called once an answer is saved, or when the server says the survey is off / no longer applies.
    // There is deliberately no way for the person to close the pop-up any other way.
    function closeSurvey() {
        if (!state.open) return;
        state.open = false;
        backdrop.hidden = true;
        state.pending = null;
        state.cooldownUntil = Date.now() + COOLDOWN_MS;
        if (state.lastFocus && typeof state.lastFocus.focus === 'function') {
            try { state.lastFocus.focus(); } catch (e) { /* element may be gone */ }
        }
    }

    /* ── send ────────────────────────────────────────────────── */
    function send(payload) {
        payload.csrf_token = csrf();
        return fetch(API + 'survey-submit.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) { j.httpOk = r.ok; return j; });
        });
    }

    function setBusy(b) {
        state.busy = b;
        refreshSubmit();
    }

    function submit() {
        if (state.busy || !state.pending || !allAnswered()) return;
        showError('');
        setBusy(true);
        send({
            key: state.pending.key,
            ratings: answers(),
            comment: state.commentOn ? elComment.value : ''
        }).then(function (res) {
            setBusy(false);
            if (res.status === 'success') {
                closeSurvey();
                toast('Thank you! Your feedback was sent anonymously.', 'success');
                return;
            }
            if (res.code === 'stale') {
                // The Super Admin changed the questions while this was open: reload them.
                showError('The survey was just updated. Please answer the new questions.');
                fetchStatus().then(function (d) {
                    if (d && d.status === 'success' && d.pending) { openSurvey(d); showError('The survey was just updated. Please answer the new questions.'); }
                    else closeSurvey();
                });
                return;
            }
            if (res.code === 'already' || res.code === 'not_eligible' || res.code === 'disabled') {
                closeSurvey();
                return;
            }
            showError(res.error || 'Could not send your answer. Please try again.');
        }).catch(function () {
            setBusy(false);
            showError('Could not reach the server. Please try again.');
        });
    }

    /* ── events ──────────────────────────────────────────────── */
    btnSubmit.addEventListener('click', submit);

    elComment.addEventListener('input', function () {
        elCount.textContent = elComment.value.length + '/' + elComment.maxLength;
    });

    // Clicking the dim background does nothing: the pop-up cannot be dismissed.
    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) {
            e.preventDefault();
            e.stopPropagation();
            box.focus();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (!state.open) return;
        if (e.key === 'Escape') {
            // Escape must not close the survey (or anything else behind it).
            e.preventDefault();
            e.stopPropagation();
            return;
        }
        if (e.key === 'Tab') {
            var f = box.querySelectorAll('button:not([disabled]), textarea, input:not([disabled]):not(.svy-radio), .svy-radio');
            var list = [];
            for (var i = 0; i < f.length; i++) {
                if (f[i].offsetParent !== null || f[i].classList.contains('svy-radio')) list.push(f[i]);
            }
            if (!list.length) { e.preventDefault(); box.focus(); return; }
            var first = list[0], last = list[list.length - 1];
            if (e.shiftKey && (document.activeElement === first || document.activeElement === box)) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    }, true);

    // If focus ever lands behind the pop-up (a click on the dim area, a screen reader), bring it back inside.
    document.addEventListener('focusin', function (e) {
        if (state.open && !backdrop.contains(e.target)) box.focus();
    });

    // Nothing else on the page may hide the pop-up while it is open: if something does, show it again.
    if (typeof MutationObserver === 'function') {
        new MutationObserver(function () {
            if (state.open && backdrop.hidden) backdrop.hidden = false;
        }).observe(backdrop, { attributes: true, attributeFilter: ['hidden'] });
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) setTimeout(check, 1200);
    });

    setTimeout(check, FIRST_CHECK_MS);
    setInterval(check, POLL_MS);
})();
