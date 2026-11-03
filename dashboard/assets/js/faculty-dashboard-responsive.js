/* ================================================================
   faculty-dashboard-responsive.js
   Companion to faculty-dashboard-responsive.css (Faculty portal only).

   What it does (all progressive enhancement — the page works without it):
   1. "Details" toggle  — on phones, long text in My Activity rows is
      clamped by CSS. This adds a small Details / Less button ONLY on
      rows where something was really cut off.
   2. Tooltips          — adds title="…" to ellipsised text so desktop
      and tablet users can hover to read the whole thing.
   3. Viewport helpers  — sets html.rx-touch / html.rx-phone classes and
      the --rx-vh variable (real visible height on mobile browsers).
   4. Stays in sync     — My Activity and the equipment list re-render
      live, so a MutationObserver re-applies 1–2 after each render.

   No dependencies. Safe to load with `defer`.
================================================================ */
(function () {
    'use strict';

    var PHONE_MQ = window.matchMedia('(max-width: 600px)');
    var root = document.documentElement;

    /* ---------- 3. viewport helpers ---------- */
    function setViewportVars() {
        var vv = window.visualViewport;
        var h = vv ? vv.height : window.innerHeight;
        root.style.setProperty('--rx-vh', (h * 0.01) + 'px');
        root.classList.toggle('rx-phone', PHONE_MQ.matches);
    }
    root.classList.toggle('rx-touch', window.matchMedia('(pointer: coarse)').matches);

    /* ---------- 1. Details toggle ---------- */
    // Each config: the row element, where to put the button, what can be clamped.
    var ROWS = [
        { row: '#panel-activity .fact-item', host: '.fact-item-main', clamp: '.fact-item-title, .fact-item-sub, .fact-note' },
        { row: '#panel-activity .fact-row', host: '.fact-cell-text', clamp: '.fact-row-title, .fact-row-sub' }
    ];

    function isCut(el) {
        // clamped by line-clamp or ellipsis: content is taller/wider than the box
        return el.scrollHeight > el.clientHeight + 1 || el.scrollWidth > el.clientWidth + 1;
    }

    function hiddenSecondLine(row) {
        // history rows hide their 2nd secondary line (the decline/cancel reason) entirely on phones
        var subs = row.querySelectorAll('.fact-row-sub');
        return subs.length > 1 && getComputedStyle(subs[1]).display === 'none';
    }

    function makeButton(row) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'rx-more';
        b.setAttribute('aria-expanded', row.classList.contains('rx-open') ? 'true' : 'false');
        b.innerHTML = '<span class="rx-more-label"></span>' +
            '<span class="material-symbols-outlined" aria-hidden="true">expand_more</span>';
        setLabel(b, row.classList.contains('rx-open'));
        return b;
    }

    function setLabel(btn, open) {
        var l = btn.querySelector('.rx-more-label');
        if (l) l.textContent = open ? 'Less' : 'Details';
    }

    function enhanceRows() {
        ROWS.forEach(function (cfg) {
            document.querySelectorAll(cfg.row).forEach(function (row) {
                var host = row.querySelector(cfg.host);
                if (!host) return;
                var existing = host.querySelector(':scope > .rx-more');
                var open = row.classList.contains('rx-open');

                // Only measure when collapsed (when open nothing is clamped)
                var needs = true;
                if (!open) {
                    needs = false;
                    row.querySelectorAll(cfg.clamp).forEach(function (el) { if (isCut(el)) needs = true; });
                    if (!needs && cfg.row.indexOf('fact-row') > -1 && hiddenSecondLine(row)) needs = true;
                }

                if (!PHONE_MQ.matches) needs = false;

                if (needs && !existing) {
                    host.appendChild(makeButton(row));
                } else if (!needs && existing && !open) {
                    existing.remove();
                }
            });
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.rx-more');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var row = btn.closest('.fact-item, .fact-row');
        if (!row) return;
        var open = row.classList.toggle('rx-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        setLabel(btn, open);
    });

    /* ---------- 2. tooltips on ellipsised text ---------- */
    var TITLE_SEL = [
        '#panel-lending .eq-item-name', '#panel-lending .eq-item-cat',
        '#panel-lending .feat-hero-title', '#panel-lending .feat-secondary-title',
        '#panel-activity .fact-item-title', '#panel-activity .fact-row-title',
        '#panel-activity .fact-item-sub', '#panel-activity .fact-row-sub'
    ].join(',');

    function addTitles() {
        document.querySelectorAll(TITLE_SEL).forEach(function (el) {
            var full = (el.textContent || '').replace(/\s+/g, ' ').trim();
            if (!full) return;
            if (isCut(el)) { if (el.getAttribute('title') !== full) el.setAttribute('title', full); }
            else if (el.hasAttribute('title') && el.getAttribute('title') === full) { el.removeAttribute('title'); }
        });
    }

    /* ---------- scheduling ---------- */
    var queued = false;
    function refresh() {
        if (queued) return;
        queued = true;
        (window.requestAnimationFrame || setTimeout)(function () {
            queued = false;
            setViewportVars();
            enhanceRows();
            addTitles();
        });
    }

    function init() {
        refresh();
        window.addEventListener('resize', refresh, { passive: true });
        window.addEventListener('orientationchange', refresh, { passive: true });
        if (window.visualViewport) window.visualViewport.addEventListener('resize', refresh, { passive: true });
        if (PHONE_MQ.addEventListener) PHONE_MQ.addEventListener('change', refresh);

        // Re-run after tab switches and live re-renders of the lists
        var targets = ['#panel-activity', '#panel-lending'];
        targets.forEach(function (sel) {
            var el = document.querySelector(sel);
            if (!el || !window.MutationObserver) return;
            new MutationObserver(function (muts) {
                // ignore our own button insertions to avoid a feedback loop
                for (var i = 0; i < muts.length; i++) {
                    var m = muts[i];
                    var own = Array.prototype.every.call(m.addedNodes, function (n) { return n.classList && n.classList.contains('rx-more'); }) &&
                        Array.prototype.every.call(m.removedNodes, function (n) { return n.classList && n.classList.contains('rx-more'); });
                    if (!(m.type === 'childList' && own && (m.addedNodes.length || m.removedNodes.length))) { refresh(); return; }
                }
            }).observe(el, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style'] });
        });
        document.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('.side-nav-item')) setTimeout(refresh, 350);
        });
        // fonts finishing late change text widths
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(refresh);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
