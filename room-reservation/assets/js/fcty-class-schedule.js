/* ================================================================
   PUPSYNC — FACILITIES TAB · "MY CLASS SCHEDULE"  (fcty-class-schedule.js)

   Under the three building panels: on the left, the faculty member's
   classes for the whole semester (their approved bookings, grouped by
   purpose + room + weekday + time); on the right, a Monday to Saturday
   week calendar of the same bookings: what it is (Lecture, Lab session
   etc.), when, for how long, and where. Clicking a class on the left
   jumps the calendar to the week of its next (or last) session.

   Data:  room-reservation/api/get-faculty-schedule.php  (one week per call)
   Markup: room-reservation/views/faculty-rooms-screen.php  (#fcty-class-schedule)
   Styles: room-reservation/assets/css/fcty-facilities.css  (".fcs-*")

   Wide screens get the calendar grid; narrow ones get a day-by-day list
   (the switch is a CSS container query, both are rendered).
   Refreshes by itself when a room is reserved or cancelled
   ("pupsync:reservation-submitted"), when the tab regains focus, and
   every few minutes.
================================================================ */
(function () {
    'use strict';

    var root = document.getElementById('fcty-class-schedule');
    if (!root) return;

    var HOUR_H = 40;                 /* px per hour in the grid (compact)                    */
    var PAD = 14;                    /* breathing room above 7 AM / below 9 PM (first and last
                                        hour labels sit centred on their lines)                */
    var CORE_START = 7 * 60;         /* the grid always covers 7 AM – 9 PM, and grows to fit */
    var CORE_END = 21 * 60;          /* anything booked earlier or later                     */
    var REFETCH_MS = 5 * 60 * 1000;
    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var DOW = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var TYPE = { 'lecture': 'lecture', 'lab session': 'lab', 'exam': 'exam', 'seminar': 'seminar', 'meeting': 'meeting' };

    var body = document.getElementById('fcs-body');
    var elList = document.getElementById('fcs-list');
    var elLabel = document.getElementById('fcs-range');
    var btnPrev = document.getElementById('fcs-prev');
    var btnNext = document.getElementById('fcs-next');
    var btnToday = document.getElementById('fcs-today');

    var data = null;        /* last response                                      */
    var weekStart = null;   /* Monday shown (Y-m-d); null = let the server pick   */
    var fetchedAt = 0;      /* Date.now() when `data` arrived (drives the now-line) */
    var seq = 0;            /* ignores out-of-order responses                     */

    /* ── Helpers ───────────────────────────────────────────── */
    function esc(str) {
        return String(str === null || str === undefined ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function pad(n) { return (n < 10 ? '0' : '') + n; }

    function apiBase() {
        var p = window.location.pathname;
        return p.substring(0, p.lastIndexOf('/') + 1);
    }

    function icon(name) {
        return '<span class="material-symbols-outlined" aria-hidden="true">' + name + '</span>';
    }

    function toMin(hm) {
        var p = String(hm).split(':');
        return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0);
    }

    function clock(min) {
        var h = Math.floor(min / 60) % 24, m = min % 60;
        return ((h % 12) || 12) + ':' + pad(m);
    }

    /* Philippine convention: exactly 12:00 is "NN" (noon), not "PM" */
    function meridiem(min) {
        if (min % (24 * 60) === 12 * 60) return 'NN';
        return (Math.floor(min / 60) % 24) >= 12 ? 'PM' : 'AM';
    }

    /* "9:30 – 11:30 AM"  or  "9:30 AM – 12:00 NN" */
    function range(a, b) {
        return meridiem(a) === meridiem(b)
            ? clock(a) + ' – ' + clock(b) + ' ' + meridiem(b)
            : clock(a) + ' ' + meridiem(a) + ' – ' + clock(b) + ' ' + meridiem(b);
    }

    function hourLabel(min) {
        var h = Math.floor(min / 60) % 24;
        if (h === 12) return '12 NN';
        return ((h % 12) || 12) + ' ' + (h >= 12 ? 'PM' : 'AM');
    }

    /* 30 min · 1 hr · 1.5 hrs · 3 hrs · 2 hr 15 min  (short enough to sit beside a title) */
    function duration(min) {
        var h = Math.floor(min / 60), m = min % 60;
        if (h === 0) return m + ' min';
        if (m === 0) return h + (h === 1 ? ' hr' : ' hrs');
        if (m === 30) return (h + 0.5) + ' hrs';
        return h + ' hr ' + m + ' min';
    }

    /* Y-m-d maths in UTC so daylight saving / time zones can never shift a day */
    function parseYmd(s) {
        var p = String(s).split('-');
        return new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]));
    }
    function fmtYmd(d) {
        return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate());
    }
    function addDays(ymd, n) {
        var d = parseYmd(ymd);
        d.setUTCDate(d.getUTCDate() + n);
        return fmtYmd(d);
    }

    function weekLabel(start, end) {
        var a = parseYmd(start), b = parseYmd(end);
        var ay = a.getUTCFullYear(), by = b.getUTCFullYear();
        var left = MONTHS[a.getUTCMonth()] + ' ' + a.getUTCDate();
        if (ay !== by) left += ', ' + ay;
        var right = (a.getUTCMonth() === b.getUTCMonth() && ay === by)
            ? String(b.getUTCDate())
            : MONTHS[b.getUTCMonth()] + ' ' + b.getUTCDate();
        return left + ' – ' + right + ', ' + by;
    }

    function typeClass(purpose) {
        return 'fcs-t-' + (TYPE[String(purpose).trim().toLowerCase()] || 'other');
    }

    /* Server clock moved forward by the time since the response arrived */
    function nowMinutes() {
        if (!data) return 0;
        return data.now_minutes + Math.floor((Date.now() - fetchedAt) / 60000);
    }

    function isPast(date, endMin) {
        if (!data) return false;
        if (date < data.today) return true;
        return date === data.today && endMin <= nowMinutes();
    }

    /* ── Layout ────────────────────────────────────────────── */

    /* Grid hours: 7 AM – 9 PM, stretched out to the hour for anything outside that */
    function hourWindow(days) {
        var lo = CORE_START, hi = CORE_END;
        days.forEach(function (d) {
            d.items.forEach(function (it) {
                lo = Math.min(lo, Math.floor(toMin(it.start) / 60) * 60);
                hi = Math.max(hi, Math.ceil(toMin(it.end) / 60) * 60);
            });
        });
        return { start: Math.max(0, lo), end: Math.min(24 * 60, hi) };
    }

    /* Side-by-side lanes for bookings that overlap in time */
    function lanes(items) {
        var evs = items.map(function (it) { return { it: it, s: toMin(it.start), e: toMin(it.end), lane: 0, lanes: 1 }; })
            .sort(function (a, b) { return a.s - b.s || a.e - b.e; });
        var out = [], cluster = [], clusterEnd = -1;

        function flush() {
            if (!cluster.length) return;
            var ends = [];
            cluster.forEach(function (ev) {
                var l = 0;
                while (l < ends.length && ends[l] > ev.s) l++;
                ends[l] = ev.e;
                ev.lane = l;
            });
            cluster.forEach(function (ev) { ev.lanes = ends.length; out.push(ev); });
            cluster = [];
            clusterEnd = -1;
        }

        evs.forEach(function (ev) {
            if (cluster.length && ev.s >= clusterEnd) flush();
            cluster.push(ev);
            clusterEnd = Math.max(clusterEnd, ev.e);
        });
        flush();
        return out;
    }

    /* ── Rendering ─────────────────────────────────────────── */
    function whereOf(it) {
        return [it.room, it.building].filter(Boolean).join(' · ');
    }

    /* "Building A (Old)" → "Bldg A": grid blocks are narrow, the full name stays in the tooltip */
    function whereShort(it) {
        var m = /^Building\s+([A-Za-z0-9]+)\b/.exec(it.building || '');
        return [it.room, m ? 'Bldg ' + m[1] : it.building].filter(Boolean).join(' · ');
    }

    function eventHtml(l, win, date) {
        var it = l.it;
        var top = PAD + (l.s - win.start) / 60 * HOUR_H;
        var h = Math.max((l.e - l.s) / 60 * HOUR_H, 24);
        var cls = 'fcs-ev ' + typeClass(it.purpose) +
            (l.lanes > 1 ? ' is-lane' : '') +
            (h < 60 ? ' is-short' : '') + (h < 44 ? ' is-tiny' : '') +
            (isPast(date, l.e) ? ' is-past' : '') +
            (it.student_name ? ' is-student' : '');
        var where = whereOf(it);
        var student = it.student_name
            ? 'Student: ' + it.student_name + (it.student_id ? ' (' + it.student_id + ')' : '')
            : '';
        var text = it.purpose + ', ' + range(l.s, l.e) + ', ' + duration(it.minutes) +
            (student ? ', ' + student : '') + (where ? ', ' + where : '');
        var style = 'top:' + top + 'px;height:' + (h - 2) + 'px;' +
            'left:calc(' + (l.lane * 100 / l.lanes) + '% + 3px);' +
            'width:calc(' + (100 / l.lanes) + '% - 6px)';

        return '<div class="' + cls + '" style="' + style + '" tabindex="0" role="group"' +
            ' data-date="' + esc(date) + '" data-end="' + l.e + '"' +
            ' aria-label="' + esc(text) + '" title="' + esc(text) + '">' +
            '<span class="fcs-ev-top">' +
            '<span class="fcs-ev-title">' + esc(it.purpose) + '</span>' +
            '<span class="fcs-ev-dur">' + esc(duration(it.minutes)) + '</span>' +
            '</span>' +
            '<span class="fcs-ev-time">' + esc(range(l.s, l.e)) + '</span>' +
            (student ? '<span class="fcs-ev-student">' + icon('school') + esc(student) + '</span>' : '') +
            (where ? '<span class="fcs-ev-where">' + icon('location_on') + '<span>' + esc(whereShort(it)) + '</span></span>' : '') +
            '</div>';
    }

    function gridHtml(d) {
        var win = hourWindow(d.days);
        var hours = (win.end - win.start) / 60;
        var gridH = hours * HOUR_H;
        var showNow = d.days.some(function (day) { return day.date === d.today; });
        var nowTop = PAD + (nowMinutes() - win.start) / 60 * HOUR_H;

        var heads = '<div class="fcs-corner"><span>Time</span></div>';
        var cols = '';
        var labels = '';
        var m;

        for (m = win.start; m <= win.end; m += 60) {
            labels += '<span class="fcs-hour" style="top:' + (PAD + (m - win.start) / 60 * HOUR_H) + 'px">' + esc(hourLabel(m)) + '</span>';
        }

        d.days.forEach(function (day, i) {
            var dt = parseYmd(day.date);
            var today = day.date === d.today;
            heads += '<div class="fcs-dayhead' + (today ? ' is-today' : '') + '"' + (today ? ' aria-current="date"' : '') + '>' +
                '<span class="fcs-dow">' + DOW[i] + '</span>' +
                '<span class="fcs-dom">' + dt.getUTCDate() + '</span></div>';

            var evs = lanes(day.items).map(function (l) { return eventHtml(l, win, day.date); }).join('');
            var now = (today && nowMinutes() >= win.start && nowMinutes() <= win.end)
                ? '<span class="fcs-now" data-win="' + win.start + '" style="top:' + nowTop + 'px"></span>' : '';
            cols += '<div class="fcs-col' + (today ? ' is-today' : '') + '">' + evs + now + '</div>';
        });

        var empty = d.total === 0
            ? '<div class="fcs-empty"><div class="fcs-empty-card">' + icon('event_available') +
            '<strong>No classes scheduled this week</strong>' +
            '<span>Rooms you reserve will show up here.</span></div></div>'
            : '';

        return '<div class="fcs-grid-wrap">' +
            '<div class="fcs-grid" role="group" aria-label="Weekly schedule, Monday to Saturday"' +
            ' style="--fcs-hh:' + HOUR_H + 'px;--fcs-h:' + gridH + 'px;--fcs-pad:' + PAD + 'px">' +
            heads + '<div class="fcs-gutter" aria-hidden="true">' + labels + '</div>' + cols +
            '</div>' + empty + '</div>';
    }

    function agendaHtml(d) {
        var out = '<div class="fcs-agenda">';
        d.days.forEach(function (day, i) {
            var dt = parseYmd(day.date);
            var today = day.date === d.today;
            out += '<div class="fcs-ag-day' + (today ? ' is-today' : '') + '">' +
                '<div class="fcs-ag-date"><span class="fcs-dow">' + DOW[i] + '</span><span class="fcs-dom">' + dt.getUTCDate() + '</span></div>' +
                '<div class="fcs-ag-list">';

            if (!day.items.length) {
                out += '<p class="fcs-ag-none">No classes</p>';
            } else {
                day.items.forEach(function (it) {
                    var s = toMin(it.start), e = toMin(it.end), where = whereOf(it);
                    out += '<div class="fcs-ag-ev ' + typeClass(it.purpose) + (isPast(day.date, e) ? ' is-past' : '') + '">' +
                        '<span class="fcs-ev-top"><span class="fcs-ev-title">' + esc(it.purpose) + '</span>' +
                        '<span class="fcs-ev-dur">' + esc(duration(it.minutes)) + '</span></span>' +
                        '<span class="fcs-ev-time">' + esc(range(s, e)) + '</span>' +
                        (where ? '<span class="fcs-ev-where">' + icon('location_on') + '<span>' + esc(where) + '</span></span>' : '') +
                        '</div>';
                });
            }
            out += '</div></div>';
        });
        return out + '</div>';
    }

    /*  Semester class list (left)  */
    function mondayOf(ymd) {
        var dow = (parseYmd(ymd).getUTCDay() + 6) % 7;   /* Mon = 0, Sun = 6 */
        return addDays(ymd, -dow);
    }

    function shortDate(ymd) {
        var d = parseYmd(ymd);
        return MONTHS[d.getUTCMonth()] + ' ' + d.getUTCDate();
    }

    function classHtml(c, d) {
        var s = toMin(c.start), e = toMin(c.end);
        var inWeek = (c.dates || []).some(function (x) { return x >= d.week_start && x <= d.week_end; });
        var target = c.next_date || c.last_date;
        var where = whereShort({ room: c.room, building: c.building });
        var count = c.sessions + (c.sessions === 1 ? ' session' : ' sessions');
        var state = c.next_date ? 'Next: ' + shortDate(c.next_date) : 'Done';
        var tip = c.purpose + ', ' + DOW[c.dow - 1] + ' ' + range(s, e) + ', ' + [c.room, c.building].filter(Boolean).join(', ') +
            ', ' + count + (c.student ? ', includes student bookings' : '');
        return '<button type="button" class="fcs-cls ' + typeClass(c.purpose) + (inWeek ? ' is-week' : '') +
            (c.next_date ? '' : ' is-done') + '" data-date="' + esc(target) + '" title="' + esc(tip) + '">' +
            '<span class="fcs-cls-top"><span class="fcs-cls-title">' + esc(c.purpose) + '</span>' +
            '<span class="fcs-cls-dur">' + esc(duration(c.minutes)) + '</span></span>' +
            '<span class="fcs-cls-when">' + esc(DOW[c.dow - 1] || '') + ' &middot; ' + esc(range(s, e)) + '</span>' +
            (where ? '<span class="fcs-cls-where">' + icon('location_on') + '<span>' + esc(where) + '</span></span>' : '') +
            '<span class="fcs-cls-meta">' + esc(count) + ' &middot; ' + esc(state) + '</span>' +
            '</button>';
    }

    function renderList() {
        if (!elList || !data || !data.semester) return;
        var sem = data.semester, list = data.classes || [];
        var items = list.length
            ? list.map(function (c) { return classHtml(c, data); }).join('')
            : '<p class="fcs-list-none">No approved bookings yet this semester.</p>';
        var foot = list.length
            ? '<p class="fcs-list-foot">' + sem.classes + (sem.classes === 1 ? ' class' : ' classes') + ' &middot; ' +
              sem.sessions + (sem.sessions === 1 ? ' session' : ' sessions') + '</p>'
            : '';
        elList.innerHTML = '<div class="fcs-list-in">' +
            '<div class="fcs-list-head"><span class="fcs-list-kicker">Semester classes</span>' +
            '<strong class="fcs-list-sem">' + esc(sem.name) + '</strong>' +
            '<span class="fcs-list-ay">' + esc(sem.ay) + '</span></div>' +
            '<div class="fcs-list-scroll">' + items + '</div>' + foot + '</div>';
    }

    function skeletonHtml() {
        return '<div class="fcs-skeleton" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span></div>';
    }

    function setNav() {
        var ok = !!data;
        btnPrev.disabled = !ok;
        btnNext.disabled = !ok;
        btnToday.disabled = !ok || data.is_current_week;
        elLabel.textContent = ok ? weekLabel(data.week_start, data.week_end) : '';
    }

    function render() {
        body.classList.remove('is-busy');
        body.removeAttribute('aria-busy');
        if (!data) return;
        body.innerHTML = gridHtml(data) + agendaHtml(data);
        renderList();
        setNav();
    }

    function showError(msg) {
        body.classList.remove('is-busy');
        body.removeAttribute('aria-busy');
        body.innerHTML = '<div class="fcs-error" role="alert">' + icon('error') +
            '<span>' + esc(msg || 'Couldn’t load your schedule.') + '</span>' +
            '<button type="button" class="fcs-retry" id="fcs-retry">Try again</button></div>';
        var retry = document.getElementById('fcs-retry');
        if (retry) retry.addEventListener('click', function () { load(weekStart, false); });
        setNav();
    }

    /* Moves the "now" line and greys finished bookings without rebuilding the grid */
    function refreshNow() {
        if (!data) return;
        var n = nowMinutes();
        var lines = body.querySelectorAll('.fcs-now');
        for (var i = 0; i < lines.length; i++) {
            var start = parseInt(lines[i].getAttribute('data-win'), 10);
            lines[i].style.top = (PAD + (n - start) / 60 * HOUR_H) + 'px';
        }
        var evs = body.querySelectorAll('.fcs-ev');
        for (var j = 0; j < evs.length; j++) {
            var past = isPast(evs[j].getAttribute('data-date'), parseInt(evs[j].getAttribute('data-end'), 10));
            evs[j].classList.toggle('is-past', past);
        }
    }

    /* ── Loading ───────────────────────────────────────────── */
    /* week: Y-m-d of a Monday (or null for the server's current week).
       silent: keep what is on screen instead of dimming it while refreshing. */
    function load(week, silent) {
        var mine = ++seq;
        if (!silent) {
            if (data) {
                body.classList.add('is-busy');
            } else {
                body.innerHTML = skeletonHtml();
            }
            body.setAttribute('aria-busy', 'true');
        }

        fetch(apiBase() + 'room-reservation/api/get-faculty-schedule.php' +
            (week ? '?week_start=' + encodeURIComponent(week) : ''), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) {
                return r.json().then(function (j) { return { ok: r.ok, json: j }; });
            })
            .then(function (res) {
                if (mine !== seq) return;               /* a newer request is already on its way */
                if (!res.ok || res.json.error) {
                    if (silent && data) return;         /* a failed background refresh keeps the old view */
                    showError(res.json && res.json.error);
                    return;
                }
                data = res.json;
                weekStart = data.week_start;
                fetchedAt = Date.now();
                render();
            })
            .catch(function () {
                if (mine !== seq) return;
                if (silent && data) return;
                showError();
            });
    }

    function shift(delta) {
        if (!data) return;
        load(addDays(data.week_start, delta * 7), false);
    }

    /* ── Init ──────────────────────────────────────────────── */
    /* A class on the left: show the week of its next (or last) session */
    if (elList) {
        elList.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.fcs-cls') : null;
            if (!btn) return;
            var date = btn.getAttribute('data-date');
            if (date) load(mondayOf(date), false);
        });
    }
    btnPrev.addEventListener('click', function () { shift(-1); });
    btnNext.addEventListener('click', function () { shift(1); });
    btnToday.addEventListener('click', function () { load(data ? data.default_week : null, false); });

    /* A room was reserved or cancelled anywhere in the dashboard */
    document.addEventListener('pupsync:reservation-submitted', function () { load(weekStart, true); });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) load(weekStart, true);
    });

    setInterval(function () { if (!document.hidden) refreshNow(); }, 60000);
    setInterval(function () { if (!document.hidden) load(weekStart, true); }, REFETCH_MS);

    load(null, false);
})();