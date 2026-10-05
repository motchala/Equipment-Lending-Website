/* ================================================================
   PUPSYNC — FACULTY "MY ACTIVITY"  (faculty-activity.js)

   Behaviour for the My Activity screen. Companion to the panel in
   faculty-dashboard.php and to faculty-activity.css.

   1. History table   filter All / Equipment / Rooms, search, paging
   2. Show all        expands the Currently borrowing / Upcoming lists
   3. Download Report prints the whole record (CSS un-pages it)
   4. Rooms, live     re-fetches room-reservation/api/get-activity-rooms.php
                      and swaps the Room reservations card, the room rows
                      of the history table and the two room numbers in the
                      summary strip. Runs after a booking / cancellation,
                      when the tab is opened, and every 30 s while visible.

   Cancel / Join waitlist / Report issue / go-tab buttons are handled by the
   shared delegated handlers in faculty-dashboard.js (data-action="...").
   No inline event handlers are used (the page's CSP forbids them).
================================================================ */
(function () {
    'use strict';

    var PER_PAGE = 10;
    var REFRESH_MS = 30000;
    var API_ROOMS = 'room-reservation/api/get-activity-rooms.php';

    var panel = document.getElementById('panel-activity');
    var body = document.getElementById('factHistBody');
    if (!panel || !body) return;

    var $ = function (id) { return document.getElementById(id); };
    var noResults = $('factNoResults');
    var histEmpty = $('factHistEmpty');
    var tableWrap = panel.querySelector('.fact-table-wrap');
    var pager = $('factPager');
    var pagerInfo = $('factPagerInfo');
    var pagerNums = $('factNums');
    var btnPrev = $('factPrev');
    var btnNext = $('factNext');
    var search = $('factSearch');

    var state = { kind: 'all', query: '', page: 1 };

    /* ── 1. History: sort, filter, page ──────────────────────── */
    function rows() {
        return Array.prototype.slice.call(body.querySelectorAll('tr.fact-row'));
    }

    /* Newest first, across equipment and rooms */
    function sortRows() {
        var list = rows();
        list.sort(function (a, b) {
            return (parseInt(b.dataset.ts, 10) || 0) - (parseInt(a.dataset.ts, 10) || 0);
        });
        list.forEach(function (tr) { body.appendChild(tr); });
    }

    function pageList(cur, total) {
        if (total <= 7) {
            var all = [];
            for (var i = 1; i <= total; i++) all.push(i);
            return all;
        }
        var keep = {};
        [1, total, cur - 1, cur, cur + 1].forEach(function (n) {
            if (n >= 1 && n <= total) keep[n] = true;
        });
        var nums = Object.keys(keep).map(Number).sort(function (a, b) { return a - b; });
        var out = [];
        nums.forEach(function (n, idx) {
            if (idx && n - nums[idx - 1] > 1) out.push('gap');
            out.push(n);
        });
        return out;
    }

    function renderPager(matched) {
        var pages = Math.max(1, Math.ceil(matched / PER_PAGE));
        if (matched <= PER_PAGE) { pager.hidden = true; return; }
        pager.hidden = false;

        var from = (state.page - 1) * PER_PAGE + 1;
        var to = Math.min(matched, state.page * PER_PAGE);
        pagerInfo.textContent = 'Showing ' + from + '\u2013' + to + ' of ' + matched;

        btnPrev.disabled = state.page <= 1;
        btnNext.disabled = state.page >= pages;

        pagerNums.innerHTML = '';
        pageList(state.page, pages).forEach(function (n) {
            if (n === 'gap') {
                var gap = document.createElement('span');
                gap.className = 'fact-pager-gap';
                gap.textContent = '\u2026';
                pagerNums.appendChild(gap);
                return;
            }
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'fact-pager-btn' + (n === state.page ? ' is-active' : '');
            b.dataset.page = String(n);
            b.textContent = String(n);
            if (n === state.page) b.setAttribute('aria-current', 'page');
            pagerNums.appendChild(b);
        });
    }

    function apply() {
        var q = state.query.trim().toLowerCase();
        var list = rows();
        var matched = [];

        list.forEach(function (tr) {
            var okKind = state.kind === 'all' || tr.dataset.kind === state.kind;
            var okText = !q || tr.textContent.toLowerCase().indexOf(q) !== -1;
            var show = okKind && okText;
            tr.classList.toggle('is-filtered-out', !show);
            if (show) matched.push(tr);
        });

        var pages = Math.max(1, Math.ceil(matched.length / PER_PAGE));
        if (state.page > pages) state.page = pages;
        var start = (state.page - 1) * PER_PAGE;

        matched.forEach(function (tr, i) {
            tr.classList.toggle('is-paged-out', i < start || i >= start + PER_PAGE);
        });

        var none = list.length === 0;
        tableWrap.hidden = none;
        histEmpty.hidden = !none;
        noResults.hidden = none || matched.length > 0;
        if (none) pager.hidden = true; else renderPager(matched.length);
    }

    /* Kind tabs */
    $('factKindTabs').addEventListener('click', function (e) {
        var btn = e.target.closest('[data-fact-kind]');
        if (!btn) return;
        state.kind = btn.dataset.factKind;
        state.page = 1;
        panel.querySelectorAll('[data-fact-kind]').forEach(function (b) {
            var on = b === btn;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        apply();
    });

    /* Search (debounced a little so typing stays smooth) */
    var searchTimer = null;
    search.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            state.query = search.value;
            state.page = 1;
            apply();
        }, 120);
    });

    /* Paging */
    btnPrev.addEventListener('click', function () { state.page--; apply(); });
    btnNext.addEventListener('click', function () { state.page++; apply(); });
    pagerNums.addEventListener('click', function (e) {
        var b = e.target.closest('[data-page]');
        if (!b) return;
        state.page = parseInt(b.dataset.page, 10) || 1;
        apply();
    });

    /* ── 2. Show all / Show less ─────────────────────────────── */
    panel.addEventListener('click', function (e) {
        var more = e.target.closest('[data-fact-more]');
        if (!more) return;
        var list = more.previousElementSibling;
        if (!list || !list.classList.contains('fact-list')) return;

        var collapsed = list.classList.toggle('is-collapsed');
        more.classList.toggle('is-open', !collapsed);
        more.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        var total = list.children.length;
        more.firstChild.nodeValue = collapsed ? 'Show all ' + total + ' ' : 'Show less ';
    });

    /* ── 3. Download Report ──────────────────────────────────── */
    panel.addEventListener('click', function (e) {
        if (e.target.closest('[data-fact-print]')) window.print();
    });

    /* ── 4. Rooms: live refresh ──────────────────────────────── */
    var inFlight = false;

    function setStat(name, value) {
        var el = panel.querySelector('[data-fact-stat="' + name + '"]');
        if (el) el.textContent = value;
    }

    function refreshRooms() {
        if (inFlight) return;
        inFlight = true;
        fetch(API_ROOMS, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || data.error) return;

                var card = $('factRoomsBody');
                if (card && typeof data.body === 'string') card.innerHTML = data.body;

                if (typeof data.rows === 'string') {
                    body.querySelectorAll('tr.fact-row[data-kind="room"]').forEach(function (tr) { tr.remove(); });
                    body.insertAdjacentHTML('beforeend', data.rows);
                    sortRows();
                    apply();
                }

                if (data.stats) {
                    setStat('reserved', data.stats.reserved);
                    setStat('reserved_sub', data.stats.reserved_sub);
                    setStat('next', data.stats.next);
                    setStat('next_sub', data.stats.next_sub);
                }
            })
            .catch(function () { /* a failed refresh must never disturb the screen */ })
            .then(function () { inFlight = false; });
    }

    function panelIsOpen() {
        return panel.classList.contains('active') && !document.hidden;
    }

    /* After booking or cancelling */
    document.addEventListener('pupsync:reservation-submitted', refreshRooms);

    /* Joining a waitlist does not fire that event — look again shortly after */
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-action="join-waitlist"], [data-action="cancel-reservation"]')) {
            setTimeout(refreshRooms, 1500);
            setTimeout(refreshRooms, 4000);
        }
    });

    /* When the tab is opened, make sure it is current */
    var navActivity = $('nav-activity');
    if (navActivity) navActivity.addEventListener('click', function () { setTimeout(refreshRooms, 150); });

    setInterval(function () { if (panelIsOpen()) refreshRooms(); }, REFRESH_MS);
    document.addEventListener('visibilitychange', function () { if (panelIsOpen()) refreshRooms(); });

    /* ── Start ───────────────────────────────────────────────── */
    sortRows();
    apply();
})();
