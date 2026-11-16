/* ═══════════════════════════════════════════════════════════════════
   Archived tables (Equipment + Facilities): 15 rows per page, page buttons at the bottom.
   Same look and behaviour as the other lists (".inv-pager" footer: "Showing 1–15 of 32", prev / 1 … 4 5 6 … 12 / next).

   Markup (one per table, placed right under the table):
     <div class="inv-pager" data-arch-pager="<tbody id>" hidden> …info, prev, numbers, next… </div>

   The table bodies are re-rendered by AJAX after Restore / Delete / Archive (innerHTML swap), so this watches the
   <tbody> and re-paginates by itself: no change needed in the code that swaps the rows. Empty-state rows (a
   single cell with colspan, e.g. "No archived items.") are never counted or hidden.
   ═══════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var PAGE_SIZE = 15;

    /* 1 … 4 5 6 … 12 : first, last and the current page ±1 */
    function pageList(total, current) {
        var keep = [1, total, current - 1, current, current + 1];
        var list = [], last = 0;
        keep.filter(function (n, i) { return n >= 1 && n <= total && keep.indexOf(n) === i; })
            .sort(function (a, b) { return a - b; })
            .forEach(function (n) {
                if (n - last > 1) list.push('gap');
                list.push(n);
                last = n;
            });
        return list;
    }

    function setup(pager) {
        var body = document.getElementById(pager.getAttribute('data-arch-pager'));
        if (!body) return;
        var info = pager.querySelector('.inv-pager-info');
        var ctl = pager.querySelector('.inv-pager-ctl');
        var prevBtn = pager.querySelector('[data-pg="prev"]');
        var nextBtn = pager.querySelector('[data-pg="next"]');
        var nums = pager.querySelector('.inv-pager-nums');
        var size = parseInt(pager.getAttribute('data-page-size'), 10) || PAGE_SIZE;
        var page = 1;

        function dataRows() {
            return Array.prototype.filter.call(body.children, function (tr) {
                return tr.tagName === 'TR' && !tr.querySelector('td[colspan]');
            });
        }

        function render() {
            var rows = dataRows();
            var total = rows.length;
            var pages = Math.max(1, Math.ceil(total / size));
            page = Math.min(Math.max(page, 1), pages);
            var start = (page - 1) * size;
            var end = Math.min(start + size, total);

            rows.forEach(function (tr, i) {
                tr.classList.toggle('inv-row-hidden', i < start || i >= end);
            });

            pager.hidden = total === 0;
            if (info) info.textContent = total ? 'Showing ' + (start + 1) + '\u2013' + end + ' of ' + total : 'No results';
            if (ctl) ctl.hidden = pages <= 1;
            if (prevBtn) prevBtn.disabled = page <= 1;
            if (nextBtn) nextBtn.disabled = page >= pages;
            if (nums) {
                nums.textContent = '';
                pageList(pages, page).forEach(function (n) {
                    if (n === 'gap') {
                        var gap = document.createElement('span');
                        gap.className = 'inv-pager-gap';
                        gap.textContent = '\u2026';
                        nums.appendChild(gap);
                        return;
                    }
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'inv-pager-btn' + (n === page ? ' is-active' : '');
                    btn.dataset.page = String(n);
                    btn.textContent = String(n);
                    btn.setAttribute('aria-label', 'Page ' + n);
                    if (n === page) btn.setAttribute('aria-current', 'page');
                    nums.appendChild(btn);
                });
            }
        }

        function goTo(n) {
            page = n;
            render();
            /* keep the top of the table in view after jumping pages */
            var card = body.closest('.eq-card, .pr-card');
            if (card && card.scrollIntoView && card.getBoundingClientRect().top < 0) {
                card.scrollIntoView({ block: 'start', behavior: 'smooth' });
            }
        }

        if (prevBtn) prevBtn.addEventListener('click', function () { goTo(page - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { goTo(page + 1); });
        if (nums) nums.addEventListener('click', function (e) {
            var b = e.target.closest('[data-page]');
            if (b) goTo(parseInt(b.dataset.page, 10) || 1);
        });

        /* Rows swapped in by AJAX (restore / delete / archive): paginate the new rows. Render only toggles
           classes, which is not a child-list change, so this cannot loop. */
        new MutationObserver(render).observe(body, { childList: true });

        render();
    }

    function init() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-arch-pager]'), setup);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
