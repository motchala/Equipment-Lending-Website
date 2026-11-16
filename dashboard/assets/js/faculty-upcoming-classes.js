/* ============================================================
   dashboard/assets/js/faculty-upcoming-classes.js
   Dashboard -> "Upcoming Classes": the next 3 subjects this faculty member teaches.

   - The server (room-reservation/api/get-faculty-upcoming-classes.php) sends the next few dated class
     meetings that have NOT finished yet, soonest first, plus its own clock.
   - This page then keeps itself correct without asking again: every few seconds it drops any class whose
     end time has passed and shows the first 3 that are left, so the list moves on by itself as the day
     goes (and into the next day, and the next week). A class that is in session right now is marked
     "In session now" until it ends; a class that is over is never shown.
   - It uses the server's clock (not the visitor's), and Asia/Manila dates, so a wrong clock or time zone on
     the visitor's computer cannot show a past class or hide an upcoming one.
   - It asks the server again every 10 minutes, when the tab comes back to the front, when the day changes,
     and when it runs low on spare classes.
   Everything shown is escaped; nothing is written to the server.
   ============================================================ */
(function () {
    'use strict';

    var body = document.getElementById('fdbClassesBody');
    if (!body) return;

    var SHOW = 3;                       // how many classes the card shows
    var TICK_MS = 15000;                // how often the list is re-checked against the clock
    var REFRESH_MS = 10 * 60 * 1000;    // how often the server is asked again
    var MIN_GAP_MS = 60 * 1000;         // never ask more often than this (unless the day changed)
    var RETRY_MS = 30 * 1000;           // after a failed load
    var MANILA = 8 * 3600;              // Asia/Manila is UTC+8 all year (no daylight saving)

    var DOW = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    var MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    var items = [];          // dated class meetings from the server, soonest first
    var truncated = false;   // the server may have more beyond what it sent
    var skew = 0;            // server clock minus this computer's clock, in seconds
    var lastFetch = 0;       // ms
    var lastDay = '';        // Manila date at the last fetch
    var inflight = false;
    var loaded = false;
    var lastSig = null;

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function apiBase() {
        var p = window.location.pathname;
        return p.substring(0, p.lastIndexOf('/') + 1);
    }

    /* the current moment, by the server's clock (Unix seconds) */
    function nowTs() { return Date.now() / 1000 + skew; }

    /* Y-m-d in Manila for a Unix timestamp */
    function ymd(ts) {
        var d = new Date((ts + MANILA) * 1000);
        return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate());
    }
    function parseYmd(s) {
        var p = String(s).split('-');
        return new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]));
    }

    /* ── time and duration, written the way the rest of the system writes them ── */
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
    function range(a, b) {
        return meridiem(a) === meridiem(b)
            ? clock(a) + ' \u2013 ' + clock(b) + ' ' + meridiem(b)
            : clock(a) + ' ' + meridiem(a) + ' \u2013 ' + clock(b) + ' ' + meridiem(b);
    }
    function duration(min) {
        var h = Math.floor(min / 60), m = min % 60;
        if (h === 0) return m + ' min';
        if (m === 0) return h + (h === 1 ? ' hr' : ' hrs');
        if (m === 30) return (h + 0.5) + ' hrs';
        return h + ' hr ' + m + ' min';
    }

    /* ── what is shown ── */
    function upcoming(now) {
        var out = [];
        for (var i = 0; i < items.length && out.length < SHOW; i++) {
            if (items[i].end_ts > now) out.push(items[i]);      // a class that has ended is never shown
        }
        return out;
    }
    function remaining(now) {
        var n = 0;
        for (var i = 0; i < items.length; i++) if (items[i].end_ts > now) n++;
        return n;
    }

    function whenLabel(it, now) {
        if (it.start_ts <= now) return 'In session now';
        var today = ymd(now);
        if (it.date === today) return 'Today';
        if (it.date === ymd(now + 86400)) return 'Tomorrow';
        var d = parseYmd(it.date);
        return DOW[d.getUTCDay()] + ', ' + MON[d.getUTCMonth()] + ' ' + d.getUTCDate();
    }

    function itemHtml(it, now) {
        var live = it.start_ts <= now;
        var isToday = it.date === ymd(now);
        var title = it.subject || it.code || 'Class';
        if (it.subject && it.component === 'Laboratory') title += ' (Lab)';
        var codeLine = [it.code, it.section].filter(Boolean).join(' \u00b7 ');
        var where = [it.room, it.building].filter(Boolean).join(' \u00b7 ');
        return '<li class="fdbc-item' + (live ? ' is-live' : '') + (isToday ? ' is-today' : '') + '">' +
            '<span class="fdbc-when">' + (live ? '<span class="fdbc-dot" aria-hidden="true"></span>' : '') + esc(whenLabel(it, now)) + '</span>' +
            '<strong class="fdbc-subject">' + esc(title) + '</strong>' +
            (codeLine ? '<span class="fdbc-code">' + esc(codeLine) + '</span>' : '') +
            '<span class="fdbc-time">' + esc(range(toMin(it.start), toMin(it.end))) +
            '<span class="fdbc-dur">' + esc(duration(it.minutes)) + '</span></span>' +
            (where ? '<span class="fdbc-room">' + esc(where) + '</span>' : '') +
            '</li>';
    }

    function emptyHtml(icon, text, retry) {
        return '<div class="fdbc-empty"><span class="material-symbols-outlined" aria-hidden="true">' + icon + '</span>' +
            '<span>' + esc(text) + '</span>' + (retry ? '<button type="button" data-fdbc-retry>Try again</button>' : '') + '</div>';
    }

    function paint(html, sig) {
        if (sig === lastSig) return;          // nothing changed: leave the page alone
        lastSig = sig;
        body.innerHTML = html;
        body.setAttribute('aria-busy', 'false');
    }

    function render() {
        var now = nowTs();
        var list = upcoming(now);
        if (!list.length) {
            paint(emptyHtml('event_available', 'No upcoming classes. Your timetable has nothing scheduled for the next two weeks.'), 'empty');
            return;
        }
        var sig = ymd(now) + '|' + list.map(function (it) { return it.id + '@' + it.date + (it.start_ts <= now ? 'L' : ''); }).join(',');
        paint('<ol class="fdbc-list">' + list.map(function (it) { return itemHtml(it, now); }).join('') + '</ol>', sig);
    }

    /* ── talking to the server ── */
    function load(force) {
        if (inflight) return;
        if (!force && Date.now() - lastFetch < MIN_GAP_MS) return;
        inflight = true;
        lastFetch = Date.now();
        var xhr = new XMLHttpRequest();
        xhr.open('GET', apiBase() + 'room-reservation/api/get-faculty-upcoming-classes.php', true);
        xhr.timeout = 15000;
        xhr.onload = function () {
            inflight = false;
            var data = null;
            try { data = JSON.parse(xhr.responseText); } catch (e) { data = null; }
            if (xhr.status !== 200 || !data || data.status !== 'success' || !Array.isArray(data.items)) { failed(); return; }
            items = data.items;
            truncated = items.length >= 10;
            skew = (+data.now_ts || Math.floor(Date.now() / 1000)) - Date.now() / 1000;
            lastDay = ymd(nowTs());
            loaded = true;
            lastSig = null;
            render();
        };
        xhr.onerror = xhr.ontimeout = function () { inflight = false; failed(); };
        xhr.send();
    }
    function failed() {
        if (!loaded) {     // keep showing what we have if a later refresh fails
            paint(emptyHtml('cloud_off', 'Could not load your upcoming classes.', true), 'error');
            setTimeout(function () { load(true); }, RETRY_MS);
        }
    }

    /* ── keeping it correct by itself ── */
    function tick() {
        if (!loaded) return;
        var now = nowTs();
        render();                                                         // drops any class that has just ended
        if (ymd(now) !== lastDay) { load(true); return; }                 // a new day: look again
        if (truncated && remaining(now) < SHOW + 1) load(false);          // running low on spare classes
    }

    body.addEventListener('click', function (e) {
        if (e.target && e.target.closest && e.target.closest('[data-fdbc-retry]')) load(true);
    });
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) return;
        tick();
        load(false);
    });
    setInterval(function () { if (!document.hidden) tick(); }, TICK_MS);
    setInterval(function () { if (!document.hidden) load(true); }, REFRESH_MS);

    load(true);
})();
