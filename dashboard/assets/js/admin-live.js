/* ================================================================
   ADMIN LIVE HUB  --  admin-live.js

   Keeps every admin screen current without a page refresh (the admin twin of faculty-live.js):
     - Equipment > Loans, its tab counts, the waiting / return lists and the Dashboard figures
       (a new faculty or student request shows up, an approve / decline / return moves it)
     - Facilities > Reservations (a new room reservation appears, a decision or cancellation updates its row,
       the Pending badge follows)
     - Equipment > Catalog (stock levels, new / edited / archived items)
     - the Activity feed

   How it works
     Every few seconds (only while this browser tab is visible) it asks sse/api/admin-pulse.php for a few short
     "change signatures". When one differs from the previous answer, only that part is re-fetched, by the code
     that already draws it:
        requests   -> window.adminDoPoll()               (stats, lists, toasts, and the Loans table)
        rooms      -> window.psRefreshRoomReservations() (Facilities > Reservations, merged in place)
        inventory  -> window.psRefreshInventoryTbody()   (Catalog)
        activity   -> window.psActivityRefresh()         (Activity feed; also after requests / rooms change)
     A screen that is not open is only marked "out of date" and is refreshed the moment the admin opens it.
     When the server-sent-event stream fires, the page calls nudge() and the check happens at once instead of
     waiting for the next tick; the stream is a speed-up, this timer is what makes it work on every host.

   Safe by design
     - The first answer only sets the baseline (nothing re-draws), so loading the page costs nothing extra.
     - If this file or the endpoint is unavailable, healthy() is false and the older timers in
       admin-live-render.js carry on exactly as before.
     - Pauses while the tab is hidden, checks again the moment it becomes visible / the network returns,
       backs off after repeated failures, and stops quietly if the session has ended (401).
     - No inline handlers (the page's CSP forbids them); a failed check never disturbs the screen.
================================================================ */
(function () {
    'use strict';
    if (window._pupsyncAdminLive) return;

    var API = 'sse/api/admin-pulse.php';
    var FAST_MS = 4000;          // normal rhythm
    var CALM_MS = 8000;          // a server-sent-event stream is connected as well, so check less often
    var SLOW_MS = 20000;         // after several failures in a row
    var HEALTHY_MS = 25000;      // an answer newer than this counts as "live"
    var MIN_GAP_MS = 700;        // never hit the server twice within this gap

    var last = null;             // the previous answer's parts (null until the first one: that one is the baseline)
    var lastOkAt = 0;
    var lastStartAt = 0;
    var timer = null;
    var busy = false;
    var again = false;
    var fails = 0;
    var stopped = false;

    /* part -> the screen (tab panel) it belongs to and the function that re-draws it */
    var JOBS = {
        rooms: { panel: 'rooms', run: function () { return call('psRefreshRoomReservations'); } },
        inventory: { panel: 'inventory', run: function () { return call('psRefreshInventoryTbody'); } },
        activity: { panel: 'activity', run: function () { return call('psActivityRefresh'); } }
    };
    var dirty = {};              // screens that changed while another screen was open

    function call(fnName, arg) {
        var fn = window[fnName];
        if (typeof fn === 'function') {
            try { return fn(arg); } catch (e) { /* a screen's own error must not stop the others */ }
        }
        return undefined;
    }

    function panelActive(name) {
        var p = document.getElementById('panel-' + name);
        return !!p && p.classList.contains('active');
    }

    /* Refresh a screen now if it is open, otherwise remember to do it when it is opened */
    function refreshOrMark(part) {
        var job = JOBS[part];
        if (!job) return;
        if (panelActive(job.panel)) { delete dirty[part]; job.run(); }
        else dirty[part] = true;
    }

    function flushDirty() {
        Object.keys(dirty).forEach(function (part) {
            var job = JOBS[part];
            if (job && panelActive(job.panel)) { delete dirty[part]; job.run(); }
        });
    }

    function react(now, prev) {
        if (!prev) return;                                   // first answer = baseline only
        var req = now.requests !== prev.requests;
        var rooms = now.rooms !== prev.rooms;
        var inv = now.inventory !== prev.inventory;
        var act = now.activity !== prev.activity;

        if (req) call('adminDoPoll');                        // stats, lists, "new request" toast, Loans table
        if (rooms) refreshOrMark('rooms');
        if (inv) refreshOrMark('inventory');
        if (act || req || rooms) refreshOrMark('activity');
        if (req || rooms) call('psRefreshDashboard');        // the Dashboard only redraws while it is open
    }

    function interval() {
        if (fails >= 3) return SLOW_MS;
        var sse = window.PupSyncSSE;
        return (sse && sse.active && !sse.fallback) ? CALM_MS : FAST_MS;
    }

    function schedule(ms) {
        clearTimeout(timer);
        timer = null;
        if (stopped || document.hidden) return;
        timer = setTimeout(pulse, ms == null ? interval() : ms);
    }

    function pulse() {
        if (stopped) return;
        if (busy) { again = true; return; }
        var wait = MIN_GAP_MS - (Date.now() - lastStartAt);
        if (wait > 0) { schedule(wait); return; }

        busy = true;
        lastStartAt = Date.now();
        fetch(API, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (r.status === 401) { stopped = true; return null; }      // signed out: nothing more to watch
                return r.ok ? r.json() : Promise.reject(new Error('pulse ' + r.status));
            })
            .then(function (j) {
                if (!j) return;
                if (!j.ok || !j.parts) throw new Error('bad pulse');
                fails = 0;
                lastOkAt = Date.now();
                var prev = last;
                last = j.parts;
                react(j.parts, prev);
            })
            .catch(function () { fails++; })
            .then(function () {
                busy = false;
                if (again) { again = false; schedule(300); } else schedule();
            });
    }

    /* Check right now (a stream event, the tab coming back, the network returning) */
    function nudge() {
        if (stopped) return;
        if (busy) { again = true; return; }
        clearTimeout(timer);
        pulse();
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { clearTimeout(timer); timer = null; }
        else { nudge(); flushDirty(); }
    });
    window.addEventListener('online', nudge);
    window.addEventListener('focus', function () { if (Date.now() - lastOkAt > FAST_MS) nudge(); });
    /* Back/forward cache: the page is shown again with old data */
    window.addEventListener('pageshow', function (e) { if (e.persisted) nudge(); });

    /* Opening a screen that changed while it was closed: bring it up to date (any way of switching tabs) */
    function watchTabs() {
        var root = document.getElementById('app-main') || document.body;
        if (!window.MutationObserver) return;
        new MutationObserver(function () { flushDirty(); })
            .observe(root, { subtree: true, attributes: true, attributeFilter: ['class'] });
    }

    window._pupsyncAdminLive = {
        nudge: nudge,
        /* true while answers keep arriving: the older timers in admin-live-render.js slow down meanwhile */
        healthy: function () { return !stopped && lastOkAt > 0 && (Date.now() - lastOkAt) < HEALTHY_MS; }
    };

    /* Start once the page's own scripts have set themselves up */
    function start() { watchTabs(); pulse(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
