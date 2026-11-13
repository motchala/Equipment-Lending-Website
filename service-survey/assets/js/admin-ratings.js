/**
 * service-survey/assets/js/admin-ratings.js
 *
 * Admin dashboard → Settings → "Service Ratings" tab.
 *
 *  - Everyone: loads the collective, anonymous summary from service-survey/api/survey-results.php and draws the
 *    overall rating, the per-question averages + 1–5 star spread, and the anonymous comments. It refreshes
 *    every time the tab is opened (and with the Refresh button). There is no per-person data to show.
 *  - Super Admin only: the "Survey settings" editor (on/off, comment box, edit / add / remove / reorder
 *    questions) which saves through service-survey/api/survey-config-save.php. Rewording or removing a question
 *    that already has ratings asks for confirmation first because its counters are cleared.
 *
 * All text is inserted with textContent (never innerHTML).
 */
(function () {
    'use strict';

    var root = document.getElementById('sett-ratings');
    if (!root) return;

    var API = 'service-survey/api/';
    var CSRF = root.getAttribute('data-csrf') || '';

    var elBanner = document.getElementById('svrBanner');
    var elOverall = document.getElementById('svrOverall');
    var elCurrentHeading = document.getElementById('svrCurrentHeading');
    var elCurrentPeriodRange = document.getElementById('svrCurrentPeriodRange');
    var elSemesterRange = document.getElementById('svrSemesterRange');
    var elSemester = document.getElementById('svrSemester');
    var elQuestionsHeading = document.getElementById('svrQuestionsHeading');
    var elQuestions = document.getElementById('svrQuestions');
    var elComments = document.getElementById('svrComments');
    var elMoreWrap = document.getElementById('svrMoreWrap');
    var btnMore = document.getElementById('svrMore');
    var btnRefresh = document.getElementById('svrRefresh');

    // Editor (only present for Super Admins)
    var ed = document.getElementById('svrEditor');
    var edEnabled = document.getElementById('svrEnabled');
    var edCommentOn = document.getElementById('svrCommentOn');
    var edSemesterStart = document.getElementById('svrSemesterStart');
    var edSemesterEnd = document.getElementById('svrSemesterEnd');
    var edList = document.getElementById('svrEdList');
    var edCount = document.getElementById('svrEdCount');
    var btnAdd = document.getElementById('svrAdd');
    var btnSave = document.getElementById('svrSave');
    var btnDiscard = document.getElementById('svrDiscard');
    var edMsg = document.getElementById('svrEdMsg');
    var edConfirm = document.getElementById('svrConfirm');
    var edConfirmList = document.getElementById('svrConfirmList');
    var btnConfirmGo = document.getElementById('svrConfirmGo');
    var btnConfirmCancel = document.getElementById('svrConfirmCancel');

    var state = {
        loading: false,
        results: null,
        isSuper: false,
        comments: [],
        editor: null,        // { enabled, commentOn, items:[{id,text,responses}] }
        snapshot: '',
        saving: false
    };

    /* ── helpers ─────────────────────────────────────────────── */
    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
    }

    function icon(name, cls) {
        var s = el('span', 'material-symbols-outlined' + (cls ? ' ' + cls : ''), name);
        s.setAttribute('aria-hidden', 'true');
        return s;
    }

    function toast(msg, type) {
        if (typeof window.showToast === 'function') window.showToast(msg, type);
    }

    function monthLabel(ym) {
        var m = /^(\d{4})-(\d{2})$/.exec(ym || '');
        if (!m) return '';
        return new Date(parseInt(m[1], 10), parseInt(m[2], 10) - 1, 1).toLocaleString('en-US', { month: 'short', year: 'numeric' });
    }

    function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }

    function clear(node) { while (node.firstChild) node.removeChild(node.firstChild); }

    function starsDisplay(avg) {
        var wrap = el('span', 'svr-stars');
        wrap.setAttribute('role', 'img');
        wrap.setAttribute('aria-label', avg.toFixed(1) + ' out of 5 stars');
        var base = el('span', 'svr-stars__base');
        var fill = el('span', 'svr-stars__fill');
        for (var i = 0; i < 5; i++) { base.appendChild(icon('star')); fill.appendChild(icon('star')); }
        fill.style.width = Math.max(0, Math.min(100, (avg / 5) * 100)) + '%';
        wrap.appendChild(base);
        wrap.appendChild(fill);
        return wrap;
    }

    /* ── load ────────────────────────────────────────────────── */
    function load(offset, append) {
        if (state.loading) return;
        state.loading = true;
        btnRefresh.disabled = true;
        fetch(API + 'survey-results.php?offset=' + (offset || 0), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { j.httpOk = r.ok; return j; }); })
            .then(function (j) {
                state.loading = false;
                btnRefresh.disabled = false;
                if (!j.httpOk || j.status !== 'success') {
                    showBanner(j.error || 'Could not load the ratings.', 'error');
                    return;
                }
                state.results = j.results;
                state.isSuper = !!j.is_super_admin;
                state.comments = append ? state.comments.concat(j.results.comments) : j.results.comments.slice();
                renderAll(!append);
            })
            .catch(function () {
                state.loading = false;
                btnRefresh.disabled = false;
                showBanner('Could not reach the server.', 'error');
            });
    }

    /* ── render: summary ─────────────────────────────────────── */
    function showBanner(msg, type) {
        if (!msg) { elBanner.hidden = true; return; }
        clear(elBanner);
        elBanner.setAttribute('data-type', type || 'info');
        elBanner.appendChild(icon(type === 'error' ? 'error' : 'info'));
        elBanner.appendChild(el('span', '', msg));
        elBanner.hidden = false;
    }

    function renderAggregate(target, summary) {
        clear(target);
        if (summary.ready && summary.overall !== null) {
            var left = el('div', 'svr-overall__score');
            left.appendChild(el('span', 'svr-overall__num', summary.overall.toFixed(1)));
            left.appendChild(el('span', 'svr-overall__of', '/ 5'));
            target.appendChild(left);

            var right = el('div', 'svr-overall__meta');
            right.appendChild(starsDisplay(summary.overall));
            right.appendChild(el('p', 'svr-muted', 'Combined average of every star rating, from ' + plural(summary.responses, 'response', 'responses') + '.'));
            target.appendChild(right);
            return;
        }
        var box = el('div', 'svr-collecting');
        box.appendChild(icon('hourglass_top'));
        var t = el('div', '');
        t.appendChild(el('strong', '', 'Collecting responses'));
        t.appendChild(el('p', 'svr-muted',
            summary.responses + ' of ' + state.results.min_responses + ' responses so far. Averages appear after ' + state.results.min_responses +
            ' so a single person’s answer can never be picked out.'));
        box.appendChild(t);
        target.appendChild(box);
    }

    function renderOverall(r) {
        var period = r.current_period || {};
        if (!period.configured) {
            elCurrentHeading.textContent = 'Legacy overall rating';
            elCurrentPeriodRange.textContent = 'Semester dates not configured';
            elQuestionsHeading.textContent = 'Legacy all-time question ratings';
        } else if (!period.active) {
            elCurrentHeading.textContent = 'Current two-week rating';
            elCurrentPeriodRange.textContent = 'Outside the configured semester';
            elQuestionsHeading.textContent = 'Current period by question';
        } else {
            elCurrentHeading.textContent = 'Current two-week rating';
            elCurrentPeriodRange.textContent = period.start + ' – ' + period.end;
            elQuestionsHeading.textContent = 'Current period by question';
        }

        if (period.configured && !period.active) {
            clear(elOverall);
            elOverall.appendChild(el('p', 'svr-muted svr-pad', 'There is no active two-week period today. Check the semester dates in Survey settings.'));
            return;
        }
        renderAggregate(elOverall, r);
    }

    function renderSemester(summary) {
        clear(elSemester);
        if (!summary || !summary.configured) {
            elSemesterRange.textContent = '';
            elSemester.appendChild(el('p', 'svr-muted svr-pad', 'Set semester start and end dates in Survey settings to begin period reporting.'));
            return;
        }
        elSemesterRange.textContent = summary.start + ' – ' + summary.end;
        renderAggregate(elSemester, summary);
        if (summary.legacy_responses > 0) {
            elSemester.appendChild(el(
                'p',
                'svr-muted svr-pad',
                plural(summary.legacy_responses, 'unperiodized response is', 'unperiodized responses are') +
                    ' retained outside this semester total.'
            ));
        }
    }

    function renderQuestions(r) {
        clear(elQuestions);
        if (!r.questions.length) {
            elQuestions.appendChild(el('p', 'svr-muted svr-pad', 'No questions are set up.'));
            return;
        }
        r.questions.forEach(function (q, i) {
            var item = el('div', 'svr-q');
            var head = el('div', 'svr-q__head');
            head.appendChild(el('span', 'svr-q__num', String(i + 1)));
            head.appendChild(el('span', 'svr-q__text', q.text));
            if (typeof q.average === 'number') {
                var avg = el('span', 'svr-q__avg');
                avg.appendChild(el('strong', '', q.average.toFixed(1)));
                avg.appendChild(document.createTextNode(' / 5'));
                head.appendChild(avg);
            }
            item.appendChild(head);

            if (q.counts) {
                var bars = el('div', 'svr-bars');
                for (var s = 5; s >= 1; s--) {
                    var n = q.counts[s - 1];
                    var pct = q.responses ? Math.round((n / q.responses) * 100) : 0;
                    var row = el('div', 'svr-bar');
                    var lab = el('span', 'svr-bar__lbl');
                    lab.appendChild(document.createTextNode(String(s)));
                    lab.appendChild(icon('star'));
                    row.appendChild(lab);
                    var track = el('span', 'svr-bar__track');
                    var fill = el('span', 'svr-bar__fill');
                    fill.style.width = pct + '%';
                    track.appendChild(fill);
                    row.appendChild(track);
                    row.appendChild(el('span', 'svr-bar__n', n + ' (' + pct + '%)'));
                    bars.appendChild(row);
                }
                item.appendChild(bars);
            } else {
                item.appendChild(el('p', 'svr-muted', plural(q.responses, 'response', 'responses') + ' so far. The breakdown appears after ' + r.min_responses + '.'));
            }
            elQuestions.appendChild(item);
        });
    }

    function renderComments(r) {
        clear(elComments);
        if (!state.comments.length) {
            var message = r.comments_pending
                ? 'Comments appear after ' + r.min_responses + ' survey responses to protect anonymity.'
                : (r.comment_enabled ? 'No comments yet.' : 'The comment box is turned off. Earlier comments appear here.');
            elComments.appendChild(el('p', 'svr-muted svr-pad', message));
        }
        state.comments.forEach(function (c) {
            var card = el('div', 'svr-comment');
            card.appendChild(el('p', 'svr-comment__text', c.text));
            card.appendChild(el('span', 'svr-comment__date', monthLabel(c.month)));
            elComments.appendChild(card);
        });
        elMoreWrap.hidden = !(state.comments.length < r.comment_total);
    }

    function renderAll(resetEditor) {
        var r = state.results;
        if (!r) return;
        showBanner(r.enabled ? '' : 'The survey is turned off, so faculty are not being asked right now. Results already collected are shown below.', 'warning');
        renderOverall(r);
        renderSemester(r.semester);
        renderQuestions(r);
        renderComments(r);
        if (ed && state.isSuper) {
            ed.hidden = false;
            if (resetEditor || !state.editor) resetEditorFromResults();
        }
    }

    /* ── Super Admin editor ──────────────────────────────────── */
    function snapshotOf(e) {
        return JSON.stringify({
            enabled: e.enabled,
            commentOn: e.commentOn,
            semesterStart: e.semesterStart,
            semesterEnd: e.semesterEnd,
            items: e.items.map(function (i) { return [i.id, i.text]; })
        });
    }

    function resetEditorFromResults() {
        var r = state.results;
        state.editor = {
            enabled: !!r.enabled,
            commentOn: !!r.comment_enabled,
            semesterStart: r.semester && r.semester.start ? r.semester.start : '',
            semesterEnd: r.semester && r.semester.end ? r.semester.end : '',
            items: (r.editor_questions || r.questions).map(function (q) { return { id: q.id, text: q.text, responses: q.responses || 0 }; })
        };
        state.snapshot = snapshotOf(state.editor);
        edEnabled.checked = state.editor.enabled;
        edCommentOn.checked = state.editor.commentOn;
        edSemesterStart.value = state.editor.semesterStart;
        edSemesterEnd.value = state.editor.semesterEnd;
        edConfirm.hidden = true;
        setMsg('');
        renderEditorList();
        refreshEditorButtons();
    }

    function limits() { return (state.results && state.results.limits) || { min_questions: 1, max_questions: 5, question_min: 8, question_max: 160 }; }

    function setMsg(text, type) {
        if (!text) { edMsg.hidden = true; edMsg.textContent = ''; return; }
        edMsg.textContent = text;
        edMsg.setAttribute('data-type', type || 'info');
        edMsg.hidden = false;
    }

    function refreshEditorButtons() {
        var e = state.editor, L = limits();
        var dirty = snapshotOf(e) !== state.snapshot;
        btnSave.disabled = !dirty || state.saving;
        btnDiscard.disabled = !dirty || state.saving;
        btnAdd.disabled = e.items.length >= L.max_questions || state.saving;
        edCount.textContent = e.items.length + ' of ' + L.max_questions + ' questions';
        var delBtns = edList.querySelectorAll('[data-act="del"]');
        for (var i = 0; i < delBtns.length; i++) delBtns[i].disabled = e.items.length <= L.min_questions || state.saving;
    }

    function iconBtn(act, i, name, label, disabled) {
        var b = el('button', 'svr-icon-btn');
        b.type = 'button';
        b.setAttribute('data-act', act);
        b.setAttribute('data-i', String(i));
        b.setAttribute('aria-label', label);
        b.title = label;
        b.disabled = !!disabled;
        b.appendChild(icon(name));
        return b;
    }

    function renderEditorList(focusIndex) {
        var e = state.editor, L = limits();
        clear(edList);
        e.items.forEach(function (it, i) {
            var row = el('div', 'svr-ed-row');
            row.appendChild(el('span', 'svr-q__num', String(i + 1)));

            var field = el('div', 'svr-ed-field');
            var input = el('input', 'svr-ed-input');
            input.type = 'text';
            input.value = it.text;
            input.maxLength = L.question_max;
            input.setAttribute('data-i', String(i));
            input.setAttribute('aria-label', 'Question ' + (i + 1));
            input.placeholder = 'Type the question faculty will rate…';
            field.appendChild(input);
            if (it.id !== null && it.id !== undefined && it.responses > 0) {
                field.appendChild(el('span', 'svr-ed-note', plural(it.responses, 'rating', 'ratings') + ' collected. Changing the wording clears them.'));
            }
            row.appendChild(field);

            var btns = el('div', 'svr-ed-btns');
            btns.appendChild(iconBtn('up', i, 'arrow_upward', 'Move up', i === 0));
            btns.appendChild(iconBtn('down', i, 'arrow_downward', 'Move down', i === e.items.length - 1));
            btns.appendChild(iconBtn('del', i, 'delete', 'Remove question', false));
            row.appendChild(btns);

            edList.appendChild(row);
        });
        if (typeof focusIndex === 'number') {
            var target = edList.querySelector('.svr-ed-input[data-i="' + focusIndex + '"]');
            if (target) target.focus();
        }
        refreshEditorButtons();
    }

    function payload(confirmReset) {
        var e = state.editor;
        return {
            csrf_token: CSRF,
            enabled: e.enabled,
            comment_enabled: e.commentOn,
            semester_start: e.semesterStart,
            semester_end: e.semesterEnd,
            confirm_reset: !!confirmReset,
            questions: e.items.map(function (i) { return { id: i.id === undefined ? null : i.id, text: i.text }; })
        };
    }

    function validate() {
        var L = limits();
        if ((state.editor.semesterStart === '') !== (state.editor.semesterEnd === '')) {
            return 'Enter both semester dates, or leave both blank to disable period reporting.';
        }
        if (state.editor.semesterStart && state.editor.semesterStart > state.editor.semesterEnd) {
            return 'The semester end date must be on or after the start date.';
        }
        for (var i = 0; i < state.editor.items.length; i++) {
            var t = state.editor.items[i].text.replace(/\s+/g, ' ').trim();
            if (t.length < L.question_min) return 'Question ' + (i + 1) + ' is too short (at least ' + L.question_min + ' characters).';
            if (t.length > L.question_max) return 'Question ' + (i + 1) + ' is too long (at most ' + L.question_max + ' characters).';
        }
        return '';
    }

    function save(confirmReset) {
        if (state.saving) return;
        var bad = validate();
        if (bad) { setMsg(bad, 'error'); return; }
        state.saving = true;
        setMsg('Saving…', 'info');
        refreshEditorButtons();
        fetch(API + 'survey-config-save.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload(confirmReset))
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) { j.httpStatus = r.status; return j; });
        }).then(function (j) {
            state.saving = false;
            if (j.status === 'success') {
                state.results = j.results;
                state.comments = j.results.comments.slice();
                edConfirm.hidden = true;
                renderAll(true);
                setMsg('Saved.', 'success');
                toast('Survey settings saved.', 'success');
                return;
            }
            if (j.status === 'confirm') {
                showConfirm(j.affected || []);
                setMsg('');
                refreshEditorButtons();
                return;
            }
            setMsg(j.error || 'Could not save the survey settings.', 'error');
            refreshEditorButtons();
        }).catch(function () {
            state.saving = false;
            setMsg('Could not reach the server.', 'error');
            refreshEditorButtons();
        });
    }

    function showConfirm(affected) {
        clear(edConfirmList);
        affected.forEach(function (a) {
            var li = el('li', '');
            li.appendChild(el('strong', '', '“' + a.text + '”'));
            li.appendChild(document.createTextNode(' — ' + plural(a.responses, 'rating', 'ratings') + ' cleared (' + (a.change === 'removed' ? 'question removed' : 'wording changed') + ')'));
            edConfirmList.appendChild(li);
        });
        edConfirm.hidden = false;
        edConfirm.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function bindEditor() {
        if (!ed) return;

        edEnabled.addEventListener('change', function () { state.editor.enabled = edEnabled.checked; refreshEditorButtons(); });
        edCommentOn.addEventListener('change', function () { state.editor.commentOn = edCommentOn.checked; refreshEditorButtons(); });
        edSemesterStart.addEventListener('change', function () { state.editor.semesterStart = edSemesterStart.value; refreshEditorButtons(); });
        edSemesterEnd.addEventListener('change', function () { state.editor.semesterEnd = edSemesterEnd.value; refreshEditorButtons(); });

        edList.addEventListener('input', function (ev) {
            var t = ev.target;
            if (!t.classList.contains('svr-ed-input')) return;
            state.editor.items[parseInt(t.getAttribute('data-i'), 10)].text = t.value;
            edConfirm.hidden = true;
            setMsg('');
            refreshEditorButtons();
        });

        edList.addEventListener('click', function (ev) {
            var b = ev.target.closest('[data-act]');
            if (!b || b.disabled) return;
            var i = parseInt(b.getAttribute('data-i'), 10), items = state.editor.items, act = b.getAttribute('data-act');
            if (act === 'up' && i > 0) { var a = items[i - 1]; items[i - 1] = items[i]; items[i] = a; renderEditorList(i - 1); }
            else if (act === 'down' && i < items.length - 1) { var c = items[i + 1]; items[i + 1] = items[i]; items[i] = c; renderEditorList(i + 1); }
            else if (act === 'del' && items.length > limits().min_questions) { items.splice(i, 1); renderEditorList(Math.max(0, i - 1)); }
            edConfirm.hidden = true;
            setMsg('');
        });

        btnAdd.addEventListener('click', function () {
            if (state.editor.items.length >= limits().max_questions) return;
            state.editor.items.push({ id: null, text: '', responses: 0 });
            renderEditorList(state.editor.items.length - 1);
        });

        btnSave.addEventListener('click', function () { save(false); });
        btnConfirmGo.addEventListener('click', function () { save(true); });
        btnConfirmCancel.addEventListener('click', function () { edConfirm.hidden = true; });
        btnDiscard.addEventListener('click', function () { resetEditorFromResults(); });
    }

    /* ── wiring ──────────────────────────────────────────────── */
    btnRefresh.addEventListener('click', function () { load(0, false); });
    btnMore.addEventListener('click', function () { load(state.comments.length, true); });
    bindEditor();

    // Load every time the tab is opened (by click, or restored after a soft refresh).
    var wasActive = false;
    function onClassChange() {
        var active = root.classList.contains('active');
        if (active && !wasActive) {
            // Keep unsaved editor changes if the Super Admin only switched tabs and came back.
            var keep = state.editor && snapshotOf(state.editor) !== state.snapshot;
            if (!keep) state.editor = null;
            load(0, false);
        }
        wasActive = active;
    }
    new MutationObserver(onClassChange).observe(root, { attributes: true, attributeFilter: ['class'] });
    onClassChange();
})();
