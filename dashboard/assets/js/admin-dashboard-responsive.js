/* ================================================================
   PUPSYNC ADMIN DASHBOARD — Responsive Supplement (behaviour)
   ----------------------------------------------------------------
   Companion to admin-dashboard-responsive.css. Adds no behaviour on
   desktop; everything here only prepares markup that the CSS turns
   into mobile layouts:

     A. Table -> card labels. Copies each column's header text onto
        every <td> as data-label, and tags the title / thumbnail /
        actions cells (see CSS §3).
     B. (Room Schedule day tabs moved into the schedule module itself,
        in admin-dashboard.php, so they follow the days it renders —
        Mon-Sat. See CSS §4.)

   Loaded with `defer`, so the whole page is already parsed when this
   runs. Nothing in admin-dashboard.js, admin-live-render.js or the
   inline modules is modified — this file only observes the DOM.
================================================================ */
(function () {
    'use strict';

    /* ------------------------------------------------------------
       A. TABLES -> CARD LABELS
    ------------------------------------------------------------ */
    var TABLE_SELECTOR = 'table.ps-table, table.pr-table, table.admin-table';
    var THUMB_HEADER = /^(image|photo|picture)$/i;
    var ID_HEADER = /(^#$|^no\.?$|\bid\b)/i;   // "#", "ID", "Request ID"…
    var ACTIONS_HEADER = /^actions?$/i;

    function labelTable(table) {
        var headRow = table.querySelector('thead tr');
        if (!headRow) return;

        var headers = Array.prototype.map.call(headRow.children, function (th) {
            return (th.textContent || '').replace(/\s+/g, ' ').trim();
        });
        if (!headers.length) return;

        // Card headline = first meaningful column: skip a leading
        // thumbnail or row-number/ID column (a name reads better as
        // the title than "#0004").
        var thumbCol = THUMB_HEADER.test(headers[0]) ? 0 : -1;
        var titleCol = 0;
        if ((thumbCol === 0 || ID_HEADER.test(headers[0])) && headers.length > 1) {
            titleCol = 1;
        }

        var actionsCol = -1;
        headers.forEach(function (h, i) {
            if (ACTIONS_HEADER.test(h)) actionsCol = i;
        });

        var rows = table.querySelectorAll('tbody > tr');
        Array.prototype.forEach.call(rows, function (row) {
            Array.prototype.forEach.call(row.children, function (td, i) {
                if (td.tagName !== 'TD' || td.hasAttribute('colspan')) return; // banner rows stay plain

                if (i === thumbCol) { td.classList.add('rc-thumb'); return; }
                if (i === titleCol) { td.classList.add('rc-title'); return; }
                if (i === actionsCol) { td.classList.add('rc-actions'); return; }
                if (headers[i]) td.setAttribute('data-label', headers[i]);
            });
        });
    }

    function labelAllTables() {
        Array.prototype.forEach.call(document.querySelectorAll(TABLE_SELECTOR), labelTable);
    }

    // Rows are swapped in live by admin-live-render.js (every 8s) and by
    // the Rooms Registry AJAX filter, so re-label whenever the DOM under
    // #app-main changes. Coalesce bursts into one pass per frame.
    var scheduled = false;
    function scheduleLabel() {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(function () {
            scheduled = false;
            labelAllTables();
        });
    }

    labelAllTables();
    var main = document.getElementById('app-main') || document.body;
    new MutationObserver(scheduleLabel).observe(main, { childList: true, subtree: true });


    /* ------------------------------------------------------------
       B. ROOM SCHEDULE — day tabs
       Handled by the schedule module in admin-dashboard.php (it builds
       #rsm-daytabs and marks the selected day with .is-mobile-active).
       Nothing to do here any more.
    ------------------------------------------------------------ */

    /* ------------------------------------------------------------
       C. SETTINGS — size class from the panel's own width
       Viewport media queries can't know whether the sidebar is
       expanded or collapsed, so Settings measures itself instead and
       exposes data-sett-size="wide|narrow|tiny" for the CSS (§ 9).
    ------------------------------------------------------------ */
    var settPanel = document.getElementById('panel-settings');

    function settSizeFor(width) {
        if (width < 480) return 'tiny';
        if (width < 760) return 'narrow';
        return 'wide';
    }

    function applySettSize(width) {
        // A hidden panel measures 0 — keep the last size until it is shown again.
        if (!settPanel || !width) return;
        var size = settSizeFor(width);
        if (settPanel.getAttribute('data-sett-size') !== size) {
            settPanel.setAttribute('data-sett-size', size);
        }
    }

    if (settPanel) {
        applySettSize(settPanel.clientWidth);
        if (typeof ResizeObserver === 'function') {
            new ResizeObserver(function (entries) {
                applySettSize(entries[0].contentRect.width);
            }).observe(settPanel);
        } else {
            window.addEventListener('resize', function () {
                applySettSize(settPanel.clientWidth);
            });
        }
    }
}());