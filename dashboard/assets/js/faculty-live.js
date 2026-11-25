/* ================================================================
   FACULTY LIVE HUB  --  faculty-live.js

   Keeps every faculty screen current without a page refresh:
     - My Activity (equipment requests and room reservations: a new request appears, and an
       approve / decline / cancel / return shows up on its own row)
     - the notification bell, its badge and the open notification list
     - the Home key figures, "Needs your attention" and "Recent activity"
     - stock levels on the Equipment screen

   How it works
     Every few seconds (only while this browser tab is visible) it asks sse/api/faculty-pulse.php for a few
     short "change signatures". When one differs from the previous answer, only that part is re-fetched,
     by the code that already draws it:
        requests   -> window._pupsyncRequestsPoll(true)  (refreshes data + fires "pupsync:requests-changed",
                                                          which My Activity and Home already listen to)
        rooms      -> "pupsync:reservation-submitted"     (My Activity rooms, Home, class schedule, toasts)
        inventory  -> window._pupsyncInventoryPoll()      (stock badges, Add buttons)
        any of the above or notifs -> window._sseNotifRefresh()  (the bell and its list)
     When a server-sent-event stream fires, the page calls nudge() and the check happens at once instead of
     waiting for the next tick; the streams are a speed-up, this timer is what makes it work on every host.

   Safe by design
     - The first answer only sets the baseline (nothing re-draws), so loading the page costs nothing extra.
     - If this file or the endpoint is unavailable, healthy() is false and the older timers in
       faculty-dashboard.js carry on exactly as before.
     - Pauses while the tab is hidden, checks again the moment it becomes visible / the network returns,
       backs off after repeated failures, and stops quietly if the session has ended (401).
     - No inline handlers (the page's CSP forbids them); a failed check never disturbs the screen.
================================================================ */
(function () {
    'use strict';
    if (window._pupsyncLive) return;

    var API = 'sse/api/faculty-pulse.php';
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

    function fire(name) {
        try { document.dispatchEvent(new CustomEvent(name)); } catch (e) { /* ignore */ }
    }

    function call(fnName, arg) {
        var fn = window[fnName];
        if (typeof fn === 'function') {
            try { return fn(arg); } catch (e) { /* a screen's own error must not stop the others */ }
        }
        return undefined;
    }

    function react(now, prev) {
        if (!prev) return;                                   // first answer = baseline only
        var req = now.requests !== prev.requests;
        var rooms = now.rooms !== prev.rooms;
        var notifs = now.notifs !== prev.notifs;
        var inv = now.inventory !== prev.inventory;

        if (req) {
            if (typeof window._pupsyncRequestsPoll === 'function') call('_pupsyncRequestsPoll', true);
            else fire('pupsync:requests-changed');
        }
        if (rooms) fire('pupsync:reservation-submitted');
        if (inv) call('_pupsyncInventoryPoll');
        if (req || rooms || notifs) call('_sseNotifRefresh');
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
        else nudge();
    });
    window.addEventListener('online', nudge);
    window.addEventListener('focus', function () { if (Date.now() - lastOkAt > FAST_MS) nudge(); });
    /* Back/forward cache: the page is shown again with old data */
    window.addEventListener('pageshow', function (e) { if (e.persisted) nudge(); });

    window._pupsyncLive = {
        nudge: nudge,
        /* true while answers keep arriving: the older timers in faculty-dashboard.js stay quiet meanwhile */
        healthy: function () { return !stopped && lastOkAt > 0 && (Date.now() - lastOkAt) < HEALTHY_MS; }
    };

    /* Start once the page's own scripts have set themselves up */
    function start() { pulse(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
