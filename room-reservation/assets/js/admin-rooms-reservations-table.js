/* ============================================================================
   Facilities > Reservations table   (room-reservation/assets/js/admin-rooms-reservations-table.js)

   Every reservation lives in exactly ONE tab, following its life:

        Pending  ->  Upcoming  ->  In use now  ->  Completed
           \                \________________\___ or it ends early as  Cancelled / Declined

   The stage ("phase") is worked out here from the reservation's status plus the
   Asia/Manila clock, so a reservation moves between tabs by itself as time passes
   (checked every 30 s). The six stage counts always add up to "All".

     phase      database status   what it means                                        row actions
     ---------  ----------------  ---------------------------------------------------  ---------------------
     pending    Waiting           later-date request waiting for an admin decision      Review (Approve/Decline)
     upcoming   Approved          approved, not started yet                             Reschedule / Cancel / View
     live       Approved          approved, started, not ended (in use right now)       Reschedule / Cancel / View
     completed  Approved          approved and already ended                            View
     cancelled  Cancelled         withdrawn by the requester or cancelled by an admin   View
     declined   Declined          turned down by an admin, or auto-declined at 12:01 AM View

   The Status cell is drawn from the row's data-* attributes, so the same code
   paints it on first load and after every approve / decline / cancel / reschedule
   (the drawer and the cancel / reschedule dialogs only update the row's data-*
   attributes and fire "rr:rows-changed" - or "rr:cancelled" - and this file does the rest).
   ========================================================================== */
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
    var hintEl = document.getElementById('rrHint');
    var navBadge = document.getElementById('rrNavBadge');
    if (!tbl || !q || !sortSel || !dirBtn || !tabsWrap || !pager) return;

    var PAGE_SIZE = 20;
    var tbody = tbl.tBodies[0];
    var tab = 'all'; // all | Waiting | upcoming | active | completed | Cancelled | Declined
    var dir = 'desc'; // desc = newest / latest first, asc = oldest / earliest first
    var page = 1;
    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /* phase -> tab key (the keys "Waiting" and "active" are kept because the Dashboard cards link to them) */
    var PHASES = {
        pending: { tab: 'Waiting', label: 'Pending', badge: 'ps-badge--waiting' },
        upcoming: { tab: 'upcoming', label: 'Upcoming', badge: 'ps-badge--scheduled' },
        live: { tab: 'active', label: 'In use now', badge: 'ps-badge--active' },
        completed: { tab: 'completed', label: 'Completed', badge: 'ps-badge--returned' },
        cancelled: { tab: 'Cancelled', label: 'Cancelled', badge: 'ps-badge--cancelled' },
        declined: { tab: 'Declined', label: 'Declined', badge: 'ps-badge--overdue' }
    };
    var PHASE_OF_TAB = {};
    Object.keys(PHASES).forEach(function(k) {
        PHASE_OF_TAB[PHASES[k].tab] = k;
    });

    /* What each tab is for, how it opens (sort), and what an empty tab says. */
    var TAB_INFO = {
        all: {
            hint: 'Every reservation, newest requests first.',
            sort: 'recent',
            dir: 'desc',
            empty: 'No reservations yet.'
        },
        Waiting: {
            hint: 'Later-date requests waiting for an admin decision, soonest first. Anything undecided is declined automatically at 12:01 AM on the reservation day.',
            sort: 'date',
            dir: 'asc',
            empty: 'Nothing is waiting for review.'
        },
        upcoming: {
            hint: 'Approved and not started yet, soonest first. You can reschedule or cancel these.',
            sort: 'date',
            dir: 'asc',
            empty: 'No upcoming reservations.'
        },
        active: {
            hint: 'Approved and happening right now.',
            sort: 'date',
            dir: 'asc',
            empty: 'No facility is in use right now.'
        },
        completed: {
            hint: 'Approved reservations that have already ended, latest first. Read-only.',
            sort: 'date',
            dir: 'desc',
            empty: 'No completed reservations yet.'
        },
        Cancelled: {
            hint: 'Withdrawn by the requester or cancelled by an admin, latest first. Read-only.',
            sort: 'date',
            dir: 'desc',
            empty: 'No cancelled reservations.'
        },
        Declined: {
            hint: 'Turned down by an admin, or automatically because nobody reviewed them in time. Read-only.',
            sort: 'date',
            dir: 'desc',
            empty: 'No declined reservations.'
        }
    };

    /* ── Time helpers (all Asia/Manila) ─────────────────────────────── */
    function pad(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function manilaNow() {
        var ms = Date.now();
        try {
            var parts = new Intl.DateTimeFormat('en-CA', {
                timeZone: 'Asia/Manila',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hourCycle: 'h23'
            }).formatToParts(new Date(ms));
            var g = function(t) {
                return (parts.find(function(p) {
                    return p.type === t;
                }) || {}).value;
            };
            return {
                iso: g('year') + '-' + g('month') + '-' + g('day'),
                min: (+g('hour')) * 60 + (+g('minute')),
                ms: ms
            };
        } catch (e) {
            var d = new Date(ms);
            return {
                iso: d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()),
                min: d.getHours() * 60 + d.getMinutes(),
                ms: ms
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

    function fmtTime(v) {
        var m = parseMin(v);
        if (m === null) return '';
        var h = Math.floor(m / 60),
            mi = m % 60,
            ap = h >= 12 ? 'PM' : 'AM';
        h = h % 12;
        if (h === 0) h = 12;
        return h + ':' + pad(mi) + ' ' + ap;
    }

    function fmtDay(iso) {
        var p = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
        return p ? MONTHS[+p[2] - 1] + ' ' + (+p[3]) : (iso || '');
    }

    function dayDiff(fromIso, toIso) { // whole days from -> to (can be negative)
        var a = /^(\d{4})-(\d{2})-(\d{2})$/.exec(fromIso || ''),
            b = /^(\d{4})-(\d{2})-(\d{2})$/.exec(toIso || '');
        if (!a || !b) return 0;
        return Math.round((Date.UTC(+b[1], +b[2] - 1, +b[3]) - Date.UTC(+a[1], +a[2] - 1, +a[3])) / 86400000);
    }

    function humanDur(ms) {
        var m = Math.max(1, Math.ceil(ms / 60000));
        if (m < 60) return m + ' min';
        var h = Math.floor(m / 60),
            mm = m % 60;
        if (h < 24) return h + ' h' + (mm ? ' ' + mm + ' min' : '');
        var d = Math.floor(h / 24);
        return d + (d === 1 ? ' day' : ' days');
    }

    function plural(n, w) {
        return n + ' ' + w + (n === 1 ? '' : 's');
    }

    function nowLabel() { // "Nov 10, 2026 3:04 PM" - same shape the server prints for cancelled_at
        try {
            var parts = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Manila',
                month: 'short',
                day: '2-digit',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            }).formatToParts(new Date());
            var g = function(t) {
                return (parts.find(function(p) {
                    return p.type === t;
                }) || {}).value || '';
            };
            return g('month') + ' ' + g('day') + ', ' + g('year') + ' ' + g('hour') + ':' + g('minute') + ' ' + g('dayPeriod');
        } catch (e) {
            return '';
        }
    }

    /* ── Stage of one reservation ───────────────────────────────────── */
    function phaseOf(d, now) {
        if (d.status === 'Waiting') return 'pending';
        if (d.status === 'Cancelled') return 'cancelled';
        if (d.status === 'Declined') return 'declined';
        if (d.status !== 'Approved') return 'completed';
        var s = parseMin(d.startTime),
            e = parseMin(d.endTime);
        if (!d.isoDate || s === null || e === null) return 'upcoming';
        if (d.isoDate > now.iso) return 'upcoming';
        if (d.isoDate < now.iso) return 'completed';
        if (s > now.min) return 'upcoming';
        if (e <= now.min) return 'completed';
        return 'live';
    }

    function rows() {
        return Array.prototype.slice.call(tbody.querySelectorAll('tr.ps-rr-row'));
    }

    /* ── Small DOM helpers (textContent only - nothing from the data is ever parsed as HTML) ── */
    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined && text !== null) n.textContent = text;
        return n;
    }

    function ctx(text, tone, title) {
        var n = el('div', 'rr-ctx' + (tone ? ' rr-ctx--' + tone : ''), text);
        if (title) n.title = title;
        return n;
    }

    function docChip(d) {
        if (!d.docOpenUrl) return el('div', 'rr-ctx rr-ctx--muted', 'No letter attached');
        var kind = d.docKind || 'file';
        var a = el('a', 'ps-doc-chip ps-doc-chip--' + kind);
        a.href = d.docOpenUrl;
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.title = d.docName || 'Request letter';
        a.appendChild(el('span', 'material-symbols-outlined', kind === 'pdf' ? 'picture_as_pdf' : (kind === 'image' ? 'image' : 'draft')));
        a.appendChild(el('span', 'ps-doc-chip__name', d.docName || 'Request letter'));
        a.appendChild(el('span', 'material-symbols-outlined ps-doc-chip__open', 'open_in_new'));
        return a;
    }

    /* Pending requests: the same deadline rule the server uses (core/room-expiry.php).
       Submitted before 12:01 AM of the reservation day -> decide by 12:01 AM that day;
       submitted on the day itself -> decide before it starts. */
    function deadlineOf(d) {
        var onDay = !!d.submittedRaw && d.submittedRaw >= (d.isoDate + ' 00:01:00');
        var hhmm = onDay ? (d.startTime || '00:01:00') : '00:01:00';
        if (/^\d{1,2}:\d{2}$/.test(hhmm)) hhmm += ':00';
        if (/^\d:/.test(hhmm)) hhmm = '0' + hhmm;
        return {
            onDay: onDay,
            ms: Date.parse(d.isoDate + 'T' + hhmm + '+08:00')
        };
    }

    /* Other reservations on the same facility + day that overlap this one (adjacent times do not overlap). */
    function buildOverlapIndex(list) {
        var idx = {};
        list.forEach(function(r) {
            var d = r.dataset,
                key = (d.roomId || '') + '|' + (d.isoDate || '');
            (idx[key] = idx[key] || []).push(r);
        });
        return idx;
    }

    function overlapsFor(row, idx) {
        var d = row.dataset,
            s = parseMin(d.startTime),
            e = parseMin(d.endTime),
            out = {
                approved: 0,
                pending: 0
            };
        if (s === null || e === null) return out;
        (idx[(d.roomId || '') + '|' + (d.isoDate || '')] || []).forEach(function(o) {
            if (o === row) return;
            var od = o.dataset,
                os = parseMin(od.startTime),
                oe = parseMin(od.endTime);
            if (os === null || oe === null || !(s < oe && e > os)) return;
            if (od.status === 'Approved') out.approved++;
            else if (od.status === 'Waiting') out.pending++;
        });
        return out;
    }

    /* One line (or two) of "what matters about this reservation right now" per stage. */
    function contextFor(phase, d, now, ov) {
        var out = [];
        var s = parseMin(d.startTime),
            e = parseMin(d.endTime);
        if (phase === 'pending') {
            var dl = deadlineOf(d);
            var left = dl.ms - now.ms;
            var text = dl.onDay ? 'Decide before it starts (' + fmtTime(d.startTime) + ')' : 'Decide by ' + fmtDay(d.isoDate) + ', 12:01 AM';
            if (isNaN(left)) {
                out.push(ctx(text));
            } else if (left <= 0) {
                out.push(ctx('Deadline passed \u00b7 declines automatically on refresh', 'danger'));
            } else if (left < 86400000) {
                out.push(ctx(text + ' \u00b7 ' + humanDur(left) + ' left', 'warn'));
            } else {
                out.push(ctx(text));
            }
            if (ov && ov.approved > 0) out.push(ctx('Overlaps an approved booking \u2013 it cannot be approved as is', 'danger'));
            else if (ov && ov.pending > 0) out.push(ctx(plural(ov.pending, 'other request') + ' for this time', 'muted'));
            out.push(docChip(d));
        } else if (phase === 'upcoming') {
            var dd = dayDiff(now.iso, d.isoDate);
            if (dd >= 2) out.push(ctx('Starts in ' + dd + ' days'));
            else if (dd === 1) out.push(ctx('Starts tomorrow, ' + fmtTime(d.startTime)));
            else out.push(ctx('Starts in ' + humanDur(((s === null ? now.min : s) - now.min) * 60000), 'warn'));
        } else if (phase === 'live') {
            out.push(ctx('Ends ' + fmtTime(d.endTime) + ' \u00b7 ' + humanDur(((e === null ? now.min : e) - now.min) * 60000) + ' left', 'ok'));
        } else if (phase === 'completed') {
            var ago = dayDiff(d.isoDate, now.iso);
            out.push(ctx(ago <= 0 ? 'Ended today, ' + fmtTime(d.endTime) : (ago === 1 ? 'Ended yesterday' : 'Ended ' + ago + ' days ago')));
        } else if (phase === 'cancelled') {
            var by = d.cancelledBy === 'requester' ? 'by the requester' : (d.cancelledBy === 'admin' ? 'by an admin' : '');
            out.push(ctx(['Cancelled', d.cancelledAt, by ? '\u00b7 ' + by : ''].filter(Boolean).join(' ')));
        } else if (phase === 'declined') {
            if (d.reason) out.push(ctx(d.reason, 'danger rr-ctx--clamp', d.reason));
            var rule = d.arbRule || '';
            if (rule) out.push(ctx((rule === 'override' || rule === 'manual') ? 'Declined by an admin' : 'Declined automatically', 'muted'));
        }
        return out;
    }

    /* ── Draw one row ───────────────────────────────────────────────── */
    function renderRow(row, now, idx) {
        var d = row.dataset;
        var phase = phaseOf(d, now);
        var P = PHASES[phase];
        d.phase = phase;
        d.phaseLabel = P.label;
        Object.keys(PHASES).forEach(function(k) {
            row.classList.toggle('is-' + k, k === phase);
        });

        // Reschedule / Cancel only make sense while the booking still holds the facility (upcoming or in use).
        var holds = phase === 'upcoming' || phase === 'live';
        d.cancellable = holds ? '1' : '0';
        var rb = row.querySelector('.btn-reschedule-rr-admin');
        var cb = row.querySelector('.btn-cancel-rr-admin');
        if (rb) rb.hidden = !holds;
        if (cb) cb.hidden = !holds;
        var vb = row.querySelector('[data-rr-detail]');
        if (vb) {
            vb.textContent = phase === 'pending' ? 'Review' : 'View';
            vb.classList.toggle('is-primary', phase === 'pending');
        }

        var cell = row.querySelector('[data-col="status"]');
        if (!cell) return;
        while (cell.firstChild) cell.removeChild(cell.firstChild);
        cell.appendChild(el('span', 'ps-badge ps-badge--dot ' + P.badge, P.label));
        var ov = phase === 'pending' ? overlapsFor(row, idx) : null;
        contextFor(phase, d, now, ov).forEach(function(n) {
            cell.appendChild(n);
        });
    }

    function renderAll() {
        var now = manilaNow();
        var list = rows();
        var idx = buildOverlapIndex(list);
        list.forEach(function(r) {
            renderRow(r, now, idx);
        });
    }

    /* ── Sorting ─────────────────────────────────────────
       Dropdown picks WHAT to sort by, the arrow button picks the direction:
       recent : submission time of the request
       date   : reservation date + start time
       Each tab opens with the order that suits it (see TAB_INFO); the admin can still change it. */
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
    var counts = {};

    function updateCounts() {
        counts = {
            all: 0,
            Waiting: 0,
            upcoming: 0,
            active: 0,
            completed: 0,
            Cancelled: 0,
            Declined: 0
        };
        rows().forEach(function(r) {
            counts.all++;
            var p = PHASES[r.dataset.phase];
            if (p) counts[p.tab]++;
        });
        tabsWrap.querySelectorAll('[data-rr-tab]').forEach(function(b) {
            var key = b.getAttribute('data-rr-tab');
            var span = b.querySelector('.rr-tab-count');
            if (!span) return;
            span.textContent = counts[key];
            // Pending is the to-do queue: red while something needs a decision. In use now: green while something is.
            span.classList.toggle('has', key === 'Waiting' && counts[key] > 0);
            span.classList.toggle('has-live', key === 'active' && counts[key] > 0);
        });
        // The Facilities > Reservations sub-tab badge shows the same queue.
        if (navBadge) {
            navBadge.textContent = counts.Waiting;
            navBadge.hidden = counts.Waiting === 0;
        }
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
        var all = rows();
        var wantPhase = tab === 'all' ? null : PHASE_OF_TAB[tab];
        var hits = all.filter(function(r) {
            var d = r.dataset;
            var hay = ((d.room || '') + ' ' + (d.who || '') + ' ' + (d.whoSub || '') + ' ' + (d.purpose || '')).toLowerCase();
            var show = !wantPhase || d.phase === wantPhase;
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
            if (cell) cell.textContent = s ? 'No reservations match.' : (TAB_INFO[tab] || TAB_INFO.all).empty;
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

    /* ── Switching tabs ──────────────────────────────────── */
    function setTab(key) {
        if (!TAB_INFO[key]) key = 'all';
        tab = key;
        tabsWrap.querySelectorAll('[data-rr-tab]').forEach(function(x) {
            x.setAttribute('aria-pressed', x.getAttribute('data-rr-tab') === key ? 'true' : 'false');
        });
        var info = TAB_INFO[key];
        sortSel.value = info.sort;
        dir = info.dir;
        if (hintEl) hintEl.textContent = info.hint;
        page = 1;
        syncDirBtn();
        sortRows();
        applyFilter();
    }

    /* Re-evaluate every row against the clock and the latest data-* values. */
    function refreshAll() {
        renderAll();
        updateCounts();
        applyFilter();
    }

    tabsWrap.addEventListener('click', function(e) {
        var b = e.target.closest('[data-rr-tab]');
        if (!b) return;
        setTab(b.getAttribute('data-rr-tab'));
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

    // Fired after a Pending request is approved / declined from the drawer, or a reservation is rescheduled
    document.addEventListener('rr:rows-changed', function() {
        sortRows();
        refreshAll();
    });

    // Fired by the cancel dialog once the server accepted the cancellation: the row becomes Cancelled right away.
    document.addEventListener('rr:cancelled', function(e) {
        var id = e && e.detail ? e.detail.id : null;
        if (id === null || id === undefined) return;
        var row = tbody.querySelector('tr.ps-rr-row[data-rr-id="' + String(id).replace(/[^0-9]/g, '') + '"]');
        if (!row) return;
        var d = row.dataset;
        d.status = 'Cancelled';
        d.cancelledBy = 'admin';
        d.cancelledAt = nowLabel();
        d.reason = '';
        d.arbReason = '';
        d.canReview = '0';
        refreshAll();
    });

    // Time passes: Upcoming -> In use now -> Completed, deadlines tick down. Re-check twice a minute and on return to the tab.
    setInterval(function() {
        if (!document.hidden) refreshAll();
    }, 30000);
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) refreshAll();
    });

    /* ── First paint ─────────────────────────────────────── */
    renderAll();
    updateCounts();
    // Open on what needs the admin first: the review queue, else what's coming up, else what's live, else everything.
    setTab(counts.Waiting > 0 ? 'Waiting' : (counts.upcoming > 0 ? 'upcoming' : (counts.active > 0 ? 'active' : 'all')));
})();
