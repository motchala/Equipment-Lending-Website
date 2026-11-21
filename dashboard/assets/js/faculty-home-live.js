/* ================================================================
   FACULTY HOME  --  live refresh  (faculty-home-live.js)

   Keeps the Home tab (key figures, "My activity" chart, "Needs your attention",
   "My schedule today", "Recent activity") current without reloading the page.

   Data:  dashboard/api/faculty-home.php  (renders the same view the page does)

   It only ever swaps those five blocks, and only the ones whose content really
   changed, so nothing flickers and nothing else on the page is touched (the
   header, the Student Access Code card, the bell, every other tab and every open
   dialog keep running exactly as before). The chart's Months / Week choice is kept.

   Refreshes happen:
     - right after you borrow, reserve, cancel or extend (the page's own events)
     - when an admin decision or a timer changes a request / reservation
     - when the Home tab is opened or the browser tab becomes visible again
     - every 20 s while Home is on screen
   No inline handlers (the page's CSP forbids them); a failed refresh is silent.
================================================================ */
(function () {
    'use strict';

    var API = 'dashboard/api/faculty-home.php';
    var EVERY_MS = 20000;
    var panel = document.getElementById('panel-home');
    if (!panel) return;

    var BLOCKS = ['dashStats', 'fdbChartCard', 'fdbAttnCard', 'fdbSchedCard', 'fdbRecentCard'];
    var busy = false;
    var dirty = false;      /* something changed while Home was hidden or a refresh was running */
    var debounce = null;

    function isOpen() {
        return panel.classList.contains('active') && !document.hidden;
    }

    function apply(html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        BLOCKS.forEach(function (id) {
            var cur = document.getElementById(id);
            var next = doc.getElementById(id);
            if (!cur || !next) return;
            if (cur.innerHTML === next.innerHTML && cur.className === next.className) return;

            /* keep the chart's Months / Week choice across the swap */
            var weekWas = false;
            if (id === 'fdbChartCard') {
                var w = document.getElementById('fdbRangeWeek');
                weekWas = !!(w && w.checked);
            }
            cur.className = next.className;
            cur.innerHTML = next.innerHTML;
            if (weekWas) {
                var wk = document.getElementById('fdbRangeWeek');
                var mo = document.getElementById('fdbRangeMonths');
                if (wk) wk.checked = true;
                if (mo) mo.checked = false;
            }
        });
    }

    function refresh() {
        if (busy) { dirty = true; return; }
        busy = true;
        dirty = false;
        fetch(API, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (data && data.ok && typeof data.html === 'string') apply(data.html);
            })
            .catch(function () { /* a failed refresh must never disturb the screen */ })
            .then(function () {
                busy = false;
                if (dirty && isOpen()) schedule(400);
            });
    }

    /* Something changed: refresh now if Home is showing, otherwise remember and refresh when it opens */
    function schedule(ms) {
        dirty = true;
        if (!isOpen()) return;
        clearTimeout(debounce);
        debounce = setTimeout(refresh, ms == null ? 250 : ms);
    }

    document.addEventListener('pupsync:requests-changed', function () { schedule(); });
    document.addEventListener('pupsync:reservation-submitted', function () { schedule(); });

    /* Home was opened (sidebar, "go-tab" buttons, back button) */
    if (typeof MutationObserver === 'function') {
        new MutationObserver(function () { if (panel.classList.contains('active')) schedule(0); })
            .observe(panel, { attributes: true, attributeFilter: ['class'] });
    }

    document.addEventListener('visibilitychange', function () { if (!document.hidden) schedule(0); });
    window.addEventListener('online', function () { schedule(0); });

    setInterval(function () { if (isOpen()) refresh(); }, EVERY_MS);
})();
