                        (function() {
                            'use strict';
                            var tbl = document.getElementById('rrTable');
                            var q = document.getElementById('rrSearch');
                            var sortSel = document.getElementById('rrSort');
                            var dirBtn = document.getElementById('rrSortDir');
                            var tabsWrap = document.getElementById('rrTabs');
                            var none = document.getElementById('rrNoMatch');
                            var pager = document.getElementById('rrPager');
                            var pgInfo = document.getElementById('rrPagerInfo');
                            var pgCtl = document.getElementById('rrPagerCtl');
                            var pgPrev = document.getElementById('rrPgPrev');
                            var pgNext = document.getElementById('rrPgNext');
                            var pgNums = document.getElementById('rrPgNums');
                            if (!tbl || !q || !sortSel || !dirBtn || !tabsWrap || !pager) return;
                            var PAGE_SIZE = 20;
                            var tbody = tbl.tBodies[0];
                            var tab = 'all'; // all | active | Waiting | Approved | Cancelled | Declined
                            var dir = 'desc'; // desc = newest / latest first, asc = oldest / earliest first
                            var page = 1;
                            function manilaNow() {
                                try {
                                    var parts = new Intl.DateTimeFormat('en-CA', {
                                        timeZone: 'Asia/Manila',
                                        year: 'numeric',
                                        month: '2-digit',
                                        day: '2-digit',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        hourCycle: 'h23'
                                    }).formatToParts(new Date());
                                    var g = function(t) {
                                        return (parts.find(function(p) {
                                            return p.type === t;
                                        }) || {}).value;
                                    };
                                    return {
                                        iso: g('year') + '-' + g('month') + '-' + g('day'),
                                        min: (+g('hour')) * 60 + (+g('minute'))
                                    };
                                } catch (e) {
                                    var d = new Date();
                                    return {
                                        iso: d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2),
                                        min: d.getHours() * 60 + d.getMinutes()
                                    };
                                }
                            }
                            function parseMin(v) {
                                var m = /^\s*(\d{1,2}):(\d{2})(?::\d{2})?\s*([AaPp][Mm])?\s*$/.exec(v || '');
                                if (!m) return null;
                                var h = +m[1],
                                    mi = +m[2],
                                    ap = (m[3] || '').toUpperCase();
                                if (ap === 'PM' && h !== 12) h += 12;
                                if (ap === 'AM' && h === 12) h = 0;
                                return h * 60 + mi;
                            }
                            function rows() {
                                return Array.prototype.slice.call(tbody.querySelectorAll('tr.ps-rr-row'));
                            }
                            function isActiveNow(d, now) {
                                var sMin = parseMin(d.startTime);
                                var eMin = parseMin(d.endTime);
                                return d.status === 'Approved' && d.isoDate === now.iso &&
                                    sMin !== null && eMin !== null &&
                                    sMin <= now.min && eMin > now.min;
                            }
                            /* ── Sorting ─────────────────────────────────────────
                               Dropdown picks WHAT to sort by, the arrow button picks the direction:
                               recent : submission time of the request
                               date   : reservation date + start time */
                            function startKey(d) {
                                return (d.isoDate || '') + ' ' + (d.startTime || '');
                            }
                            function comparator() {
                                var sign = dir === 'asc' ? 1 : -1;
                                var byDate = sortSel.value === 'date';
                                return function(a, b) {
                                    var x = a.dataset,
                                        y = b.dataset,
                                        r;
                                    if (byDate) {
                                        var kx = startKey(x),
                                            ky = startKey(y);
                                        r = kx < ky ? -1 : (kx > ky ? 1 : 0);
                                    } else {
                                        r = (+x.submittedTs || 0) - (+y.submittedTs || 0);
                                    }
                                    if (!r) r = (+x.rrId || 0) - (+y.rrId || 0);
                                    return r * sign;
                                };
                            }
                            function sortRows() {
                                var list = rows().sort(comparator());
                                var frag = document.createDocumentFragment();
                                list.forEach(function(r) {
                                    frag.appendChild(r);
                                });
                                tbody.insertBefore(frag, none || null);
                            }
                            function syncDirBtn() {
                                var asc = dir === 'asc';
                                var label = sortSel.value === 'date' ?
                                    (asc ? 'Earliest first' : 'Latest first') :
                                    (asc ? 'Oldest requests first' : 'Newest requests first');
                                dirBtn.setAttribute('data-dir', dir);
                                dirBtn.title = label + ' (click to reverse)';
                                dirBtn.setAttribute('aria-label', 'Sort direction: ' + label.toLowerCase() + '. Click to reverse.');
                                var ico = dirBtn.querySelector('.material-symbols-outlined');
                                if (ico) ico.textContent = asc ? 'arrow_upward' : 'arrow_downward';
                            }
                            /* ── Tab counts (independent of the search box) ─────── */
                            function updateCounts() {
                                var now = manilaNow();
                                var c = {
                                    all: 0,
                                    active: 0,
                                    Waiting: 0,
                                    Approved: 0,
                                    Cancelled: 0,
                                    Declined: 0
                                };
                                rows().forEach(function(r) {
                                    var d = r.dataset;
                                    c.all++;
                                    if (typeof c[d.status] === 'number') c[d.status]++;
                                    if (isActiveNow(d, now)) c.active++;
                                });
                                tabsWrap.querySelectorAll('[data-rr-tab]').forEach(function(b) {
                                    var key = b.getAttribute('data-rr-tab');
                                    var span = b.querySelector('.rr-tab-count');
                                    if (!span) return;
                                    span.textContent = c[key];
                                    // Waiting is the to-do queue: highlight it while something needs a decision
                                    span.classList.toggle('has', key === 'Waiting' && c[key] > 0);
                                });
                            }
                            /* ── 1 … 4 5 6 … 12 : first, last and the current page ±1 ── */
                            function pageList(total, current) {
                                var keep = [1, total, current - 1, current, current + 1];
                                var list = [],
                                    last = 0;
                                keep.filter(function(n, i) {
                                        return n >= 1 && n <= total && keep.indexOf(n) === i;
                                    })
                                    .sort(function(x, y) {
                                        return x - y;
                                    })
                                    .forEach(function(n) {
                                        if (n - last > 1) list.push('gap');
                                        list.push(n);
                                        last = n;
                                    });
                                return list;
                            }
                            function renderPager(total, pages, start, end) {
                                pager.hidden = false;
                                pgInfo.textContent = total ? 'Showing ' + (start + 1) + '\u2013' + end + ' of ' + total : 'No results';
                                pgCtl.hidden = false;
                                pgPrev.disabled = page <= 1;
                                pgNext.disabled = page >= pages;
                                pgNums.textContent = '';
                                pageList(pages, page).forEach(function(n) {
                                    if (n === 'gap') {
                                        var gap = document.createElement('span');
                                        gap.className = 'inv-pager-gap';
                                        gap.textContent = '\u2026';
                                        pgNums.appendChild(gap);
                                        return;
                                    }
                                    var btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'inv-pager-btn' + (n === page ? ' is-active' : '');
                                    btn.dataset.page = String(n);
                                    btn.textContent = String(n);
                                    btn.setAttribute('aria-label', 'Page ' + n);
                                    if (n === page) {
                                        btn.setAttribute('aria-current', 'page');
                                        btn.disabled = pages <= 1;
                                    }
                                    pgNums.appendChild(btn);
                                });
                            }
                            /* ── Filtering (active tab + search), then 20 rows per page ── */
                            function applyFilter() {
                                var s = (q.value || '').trim().toLowerCase();
                                var now = manilaNow();
                                var all = rows();
                                var hits = all.filter(function(r) {
                                    var d = r.dataset;
                                    var hay = ((d.room || '') + ' ' + (d.who || '') + ' ' + (d.whoSub || '') + ' ' + (d.purpose || '')).toLowerCase();
                                    var show = tab === 'all' ? true : (tab === 'active' ? isActiveNow(d, now) : d.status === tab);
                                    return show && (!s || hay.indexOf(s) !== -1);
                                });
                                var total = hits.length;
                                var pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
                                page = Math.min(Math.max(page, 1), pages);
                                var start = (page - 1) * PAGE_SIZE;
                                var end = Math.min(start + PAGE_SIZE, total);
                                var visible = hits.slice(start, end);
                                all.forEach(function(r) {
                                    r.style.display = visible.indexOf(r) !== -1 ? '' : 'none';
                                });
                                var hasRows = all.length > 0;
                                if (none) {
                                    none.style.display = (hasRows && total === 0) ? '' : 'none';
                                    var cell = none.firstElementChild;
                                    if (cell) cell.textContent = (tab === 'Waiting' && !s) ? 'No reservations are waiting for review.' : 'No reservations match.';
                                }
                                renderPager(total, pages, start, end);
                            }
                            function goToPage(n) {
                                page = n;
                                applyFilter();
                                // keep the top of the table in view after jumping pages
                                var card = tbl.closest('.pr-card');
                                if (card && card.scrollIntoView && card.getBoundingClientRect().top < 0) card.scrollIntoView({
                                    block: 'start',
                                    behavior: 'smooth'
                                });
                            }
                            tabsWrap.addEventListener('click', function(e) {
                                var b = e.target.closest('[data-rr-tab]');
                                if (!b) return;
                                tab = b.getAttribute('data-rr-tab');
                                tabsWrap.querySelectorAll('[data-rr-tab]').forEach(function(x) {
                                    x.setAttribute('aria-pressed', x === b ? 'true' : 'false');
                                });
                                page = 1;
                                applyFilter();
                            });
                            q.addEventListener('input', function() {
                                page = 1;
                                applyFilter();
                            });
                            sortSel.addEventListener('change', function() {
                                page = 1;
                                syncDirBtn();
                                sortRows();
                                applyFilter();
                            });
                            dirBtn.addEventListener('click', function() {
                                dir = dir === 'asc' ? 'desc' : 'asc';
                                page = 1;
                                syncDirBtn();
                                sortRows();
                                applyFilter();
                            });
                            pgPrev.addEventListener('click', function() {
                                goToPage(page - 1);
                            });
                            pgNext.addEventListener('click', function() {
                                goToPage(page + 1);
                            });
                            pgNums.addEventListener('click', function(e) {
                                var b = e.target.closest('[data-page]');
                                if (b) goToPage(parseInt(b.getAttribute('data-page'), 10));
                            });
                            // Fired after a Waiting request is approved/declined from the drawer
                            document.addEventListener('rr:rows-changed', function() {
                                sortRows();
                                updateCounts();
                                applyFilter();
                            });
                            syncDirBtn();
                            sortRows(); // also honours a sort value the browser restored on refresh
                            updateCounts();
                            applyFilter();
                        })();
