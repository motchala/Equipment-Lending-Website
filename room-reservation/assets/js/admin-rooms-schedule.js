                    /* ── PUPSync Room Schedule module ──────────────────────── */
                    (function() {
                        'use strict';

                        /* — Data injected from PHP — */
                        const PS_ROOMS = window.PS_ROOMS_SCHEDULE_BOOT.rooms;
                        const PS_RSVP = window.PS_ROOMS_SCHEDULE_BOOT.reservations;

                        /* — Grid geometry —
                           Reservations run 7 AM – 8 PM, Monday to Saturday (Sunday is closed; see
                           submit-faculty-reserve.php). The time axis starts at those hours and only
                           grows if a reservation ever falls outside them, so nothing is ever hidden. */
                        const AXIS_START = 7 * 60;
                        const AXIS_END = 20 * 60;
                        const HOUR_PX = 42; // height of one hour on screen (13 h fits one screen)
                        const REFRESH_MS = 30000; // re-pull reservations while the modal is open
                        const DAY_NAMES = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                        const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                            'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
                        ];

                        /* — State — */
                        let _roomId = null;
                        let _roomName = '';
                        let _weekOff = 0; // 0 = current week
                        let _mobDay = 1; // 1 = Mon … 7 = Sun (mobile single-day view)
                        let _rows = []; // normalized approved reservations for the open room
                        let _classes = []; // weekly classes of the room (Rooms Assigned import): they repeat every week
                        let _timer = null;
                        let _reqToken = 0;
                        let _lastSig = '';

                        /* — Small helpers — */
                        function $(id) {
                            return document.getElementById(id);
                        }

                        function el(tag, cls, text) {
                            const n = document.createElement(tag);
                            if (cls) n.className = cls;
                            if (text != null) n.textContent = text;
                            return n;
                        }

                        function pad2(n) {
                            return String(n).padStart(2, '0');
                        }

                        /* Dates are plain 'YYYY-MM-DD' strings handled in UTC, so the browser's own
                           time zone can never shift a day. "Today" is always Manila time. */
                        function isoToMs(iso) {
                            const p = iso.split('-').map(Number);
                            return Date.UTC(p[0], p[1] - 1, p[2]);
                        }

                        function msToISO(ms) {
                            const d = new Date(ms);
                            return d.getUTCFullYear() + '-' + pad2(d.getUTCMonth() + 1) + '-' + pad2(d.getUTCDate());
                        }

                        function addDays(iso, n) {
                            return msToISO(isoToMs(iso) + n * 86400000);
                        }

                        function mondayOf(iso) {
                            const dow = new Date(isoToMs(iso)).getUTCDay(); // 0 = Sun
                            return addDays(iso, dow === 0 ? -6 : 1 - dow);
                        }

                        function weekOffsetOf(iso, todayISO) {
                            return Math.round((isoToMs(mondayOf(iso)) - isoToMs(mondayOf(todayISO))) / (7 * 86400000));
                        }

                        function manilaNow() {
                            try {
                                const parts = new Intl.DateTimeFormat('en-CA', {
                                    timeZone: 'Asia/Manila',
                                    year: 'numeric',
                                    month: '2-digit',
                                    day: '2-digit',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                    hourCycle: 'h23'
                                }).formatToParts(new Date());
                                const g = t => (parts.find(p => p.type === t) || {}).value;
                                return {
                                    iso: g('year') + '-' + g('month') + '-' + g('day'),
                                    min: (+g('hour')) * 60 + (+g('minute'))
                                };
                            } catch (e) {
                                const d = new Date();
                                return {
                                    iso: d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()),
                                    min: d.getHours() * 60 + d.getMinutes()
                                };
                            }
                        }

                        /* "09:30 AM", "09:30" or "09:30:00" → minutes since midnight */
                        function parseMin(v) {
                            const m = /^\s*(\d{1,2}):(\d{2})(?::\d{2})?\s*([AaPp][Mm])?\s*$/.exec(v || '');
                            if (!m) return null;
                            let h = +m[1];
                            const mi = +m[2];
                            const ap = (m[3] || '').toUpperCase();
                            if (ap === 'PM' && h !== 12) h += 12;
                            if (ap === 'AM' && h === 12) h = 0;
                            return h * 60 + mi;
                        }

                        function hm12(min) {
                            const h = Math.floor(min / 60) % 24;
                            return (h % 12 || 12) + ':' + pad2(min % 60);
                        }

                        function period(min) {
                            return Math.floor(min / 60) % 24 >= 12 ? 'PM' : 'AM';
                        }

                        function fmtRange(s, e) {
                            if (period(s) === period(e)) return hm12(s) + '–' + hm12(e) + ' ' + period(e);
                            return hm12(s) + ' ' + period(s) + '–' + hm12(e) + ' ' + period(e);
                        }

                        function hourLabel(min) {
                            const h = Math.floor(min / 60) % 24;
                            return (h % 12 || 12) + ' ' + (h >= 12 ? 'PM' : 'AM');
                        }

                        function monthDay(iso) {
                            return MONTHS[+iso.slice(5, 7) - 1] + ' ' + (+iso.slice(8, 10));
                        }

                        function makeRow(id, date, s, e, faculty, purpose, asType, by) {
                            if (!date || s == null || e == null || e <= s) return null;
                            return {
                                id: id,
                                date: date,
                                s: s,
                                e: e,
                                faculty: faculty || '',
                                purpose: purpose || '',
                                asType: asType || '',
                                by: by || ''
                            };
                        }

                        /* Rows embedded in the page when it loaded (instant first paint) */
                        function embeddedRows() {
                            return PS_RSVP
                                .filter(r => (_roomId && r.room_id != null) ? (r.room_id === _roomId) : (r.room_name === _roomName))
                                .map(r => makeRow(r.id, r.date, parseMin(r.start_fmt), parseMin(r.end_fmt),
                                    r.faculty, r.purpose, r.submitted_as, r.submitted_by))
                                .filter(Boolean);
                        }

                        /* Rows from the live endpoint (every approved reservation, all weeks) */
                        function apiRows(list) {
                            return list
                                .map(r => makeRow(r.id, r.date, parseMin(r.start), parseMin(r.end),
                                    r.faculty, r.purpose, r.submitted_as, r.submitted_by))
                                .filter(Boolean);
                        }

                        /* Weekly classes (imported Rooms Assigned list). "day" is 1 = Monday ... 6 = Saturday and
                           repeats every week, so each shown week gets its own dated copy (see classRowsFor). */
                        function apiClasses(list) {
                            return (list || []).map(c => {
                                const s = parseMin(c.start),
                                    e = parseMin(c.end);
                                if (!(c.day >= 1 && c.day <= 7) || s == null || e == null || e <= s) return null;
                                return {
                                    id: c.id,
                                    day: +c.day,
                                    s: s,
                                    e: e,
                                    label: c.class || [c.subject, c.section].filter(Boolean).join(' · ') || 'Class',
                                    component: c.component || '',
                                    instructor: c.instructor || ''
                                };
                            }).filter(Boolean);
                        }

                        function classRowsFor(monday) {
                            return _classes.map(c => ({
                                id: 'class-' + c.id,
                                kind: 'class',
                                date: addDays(monday, c.day - 1),
                                s: c.s,
                                e: c.e,
                                faculty: c.instructor,
                                purpose: c.label,
                                component: c.component,
                                asType: '',
                                by: ''
                            }));
                        }

                        /* How far the week arrows may go: back to the earliest reservation, forward to
                           the latest one (always allowing at least next week). The system sets no
                           upper limit on how far ahead a room can be reserved, so the schedule follows
                           the actual bookings instead of a fixed number of weeks. */
                        function weekBounds(todayISO) {
                            let lo = 0,
                                hi = 1;
                            _rows.forEach(r => {
                                const o = weekOffsetOf(r.date, todayISO);
                                if (o < lo) lo = o;
                                if (o > hi) hi = o;
                            });
                            return {
                                lo: lo,
                                hi: hi
                            };
                        }

                        /* Place overlapping reservations side by side instead of on top of each other */
                        function layoutLanes(rows) {
                            let cluster = [],
                                clusterEnd = -1;

                            function flush() {
                                if (!cluster.length) return;
                                const laneEnds = [];
                                cluster.forEach(r => {
                                    let l = laneEnds.findIndex(end => end <= r.s);
                                    if (l === -1) {
                                        l = laneEnds.length;
                                        laneEnds.push(r.e);
                                    } else {
                                        laneEnds[l] = r.e;
                                    }
                                    r._lane = l;
                                });
                                cluster.forEach(r => {
                                    r._lanes = laneEnds.length;
                                });
                                cluster = [];
                            }
                            rows.forEach(r => {
                                if (cluster.length && r.s >= clusterEnd) flush();
                                clusterEnd = cluster.length ? Math.max(clusterEnd, r.e) : r.e;
                                cluster.push(r);
                            });
                            flush();
                        }

                        function whoLabel(r) {
                            return (r.asType === 'student' && r.by) ? (r.by + ' (via ' + r.faculty + ')') : r.faculty;
                        }

                        function buildBlock(r, axisStart, now) {
                            const top = (r.s - axisStart) / 60 * HOUR_PX;
                            const h = Math.max((r.e - r.s) / 60 * HOUR_PX, 22);
                            const isClass = r.kind === 'class';
                            const title = isClass ? (r.purpose + (r.component ? ' (' + r.component + ')' : '')) : (r.purpose || r.faculty || 'Reserved');
                            const who = isClass ? (r.faculty || 'No instructor yet') : whoLabel(r);

                            const b = el('div', 'rsm-block');
                            b.tabIndex = 0;
                            b.style.top = (top + 1) + 'px';
                            b.style.height = (h - 2) + 'px';
                            b.style.minHeight = (h - 2) + 'px';
                            const lanes = r._lanes || 1,
                                lane = r._lane || 0;
                            b.style.left = 'calc(' + (lane * 100 / lanes) + '% + 2px)';
                            b.style.width = 'calc(' + (100 / lanes) + '% - 4px)';
                            if (h < 34) b.classList.add('rsm-block--xs');
                            else if (h < 52) b.classList.add('rsm-block--sm');
                            if (isClass) b.classList.add('rsm-block--class');
                            if (r.date < now.iso || (r.date === now.iso && r.e <= now.min)) b.classList.add('is-past');
                            else if (r.date === now.iso && r.s <= now.min && now.min < r.e) b.classList.add('is-live'); // in use right now

                            b.appendChild(el('span', 'rsm-block-time', fmtRange(r.s, r.e)));
                            b.appendChild(el('span', 'rsm-block-title', title));
                            if (who && (isClass || r.purpose)) b.appendChild(el('span', 'rsm-block-sub', who));

                            b.title = [fmtRange(r.s, r.e), isClass ? 'Class' : 'Reservation', title, who].filter(Boolean).join('\n');
                            return b;
                        }

                        function weekRangeLabel(first, last) {
                            const sy = first.slice(0, 4),
                                ey = last.slice(0, 4);
                            if (first.slice(0, 7) === last.slice(0, 7))
                                return monthDay(first) + '–' + (+last.slice(8, 10)) + ', ' + ey;
                            if (sy === ey) return monthDay(first) + ' – ' + monthDay(last) + ', ' + ey;
                            return monthDay(first) + ', ' + sy + ' – ' + monthDay(last) + ', ' + ey;
                        }

                        function relativeWeek(off) {
                            if (off === 0) return 'This week';
                            if (off === 1) return 'Next week';
                            if (off === -1) return 'Last week';
                            return off > 0 ? 'In ' + off + ' weeks' : Math.abs(off) + ' weeks ago';
                        }

                        function todayColumn(now) {
                            const dow = new Date(isoToMs(now.iso)).getUTCDay(); // 0 = Sun
                            return dow >= 1 && dow <= 6 ? dow : 1; // Sunday is closed → Monday
                        }

                        function applyMobileDay(dayCount) {
                            if (_mobDay > dayCount) _mobDay = dayCount;
                            if (_mobDay < 1) _mobDay = 1;
                            const grid = $('rsm-grid');
                            if (grid) {
                                grid.querySelectorAll('[data-day]').forEach(n => {
                                    n.classList.toggle('is-mobile-active', +n.dataset.day === _mobDay);
                                });
                            }
                            document.querySelectorAll('#rsm-daytabs .rsm-daytab-btn').forEach(b => {
                                const on = +b.dataset.day === _mobDay;
                                b.classList.toggle('active', on);
                                b.setAttribute('aria-pressed', on ? 'true' : 'false');
                            });
                        }

                        /* — Render the weekly grid — */
                        function renderGrid(opts) {
                            opts = opts || {};
                            const head = $('rsm-grid-head'),
                                body = $('rsm-grid-body'),
                                grid = $('rsm-grid');
                            if (!head || !body || !grid) return;

                            const now = manilaNow();
                            const bounds = weekBounds(now.iso);
                            if (_weekOff < bounds.lo) _weekOff = bounds.lo;
                            if (_weekOff > bounds.hi) _weekOff = bounds.hi;

                            const monday = addDays(mondayOf(now.iso), _weekOff * 7);
                            const sunday = addDays(monday, 6);
                            const wk = _rows.filter(r => r.date >= monday && r.date <= sunday).concat(classRowsFor(monday));

                            /* Mon–Sat always; Sunday only if a reservation somehow sits on it */
                            const dayCount = wk.some(r => r.date === sunday) ? 7 : 6;
                            const days = [];
                            for (let i = 0; i < dayCount; i++) {
                                const iso = addDays(monday, i);
                                days.push({
                                    idx: i + 1,
                                    iso: iso,
                                    name: DAY_NAMES[i],
                                    num: +iso.slice(8, 10)
                                });
                            }

                            /* Time axis */
                            let a0 = AXIS_START,
                                a1 = AXIS_END;
                            wk.forEach(r => {
                                a0 = Math.min(a0, Math.floor(r.s / 60) * 60);
                                a1 = Math.max(a1, Math.ceil(r.e / 60) * 60);
                            });
                            a1 = Math.min(a1, 24 * 60);
                            const totalPx = (a1 - a0) / 60 * HOUR_PX;

                            grid.style.setProperty('--rsm-days', String(dayCount));
                            grid.style.setProperty('--rsm-hour', HOUR_PX + 'px');
                            body.style.setProperty('--rsm-total', totalPx + 'px');

                            /* Header row */
                            head.textContent = '';
                            head.appendChild(el('div', 'rsm-grid-th rsm-grid-th--time', ''));
                            days.forEach(d => {
                                const th = el('div', 'rsm-grid-th');
                                th.dataset.day = d.idx;
                                if (d.iso === now.iso) th.classList.add('is-today');
                                th.appendChild(el('span', 'rsm-th-name', d.name));
                                th.appendChild(el('span', 'rsm-th-num', String(d.num)));
                                head.appendChild(th);
                            });

                            /* Body: hour labels + one column per day */
                            body.textContent = '';
                            const tcol = el('div', 'rsm-time-col');
                            for (let m = a0; m < a1; m += 60) {
                                const lbl = el('div', 'rsm-hour-lbl', hourLabel(m));
                                lbl.style.top = ((m - a0) / 60 * HOUR_PX) + 'px';
                                tcol.appendChild(lbl);
                            }
                            body.appendChild(tcol);

                            days.forEach(d => {
                                const col = el('div', 'rsm-day-col');
                                col.dataset.day = d.idx;
                                if (d.iso === now.iso) col.classList.add('is-today');
                                const dayRows = wk.filter(r => r.date === d.iso)
                                    .sort((x, y) => (x.s - y.s) || (x.e - y.e));
                                layoutLanes(dayRows);
                                dayRows.forEach(r => col.appendChild(buildBlock(r, a0, now)));
                                if (d.iso === now.iso && now.min >= a0 && now.min <= a1) {
                                    const nl = el('div', 'rsm-now');
                                    nl.style.top = ((now.min - a0) / 60 * HOUR_PX) + 'px';
                                    col.appendChild(nl);
                                }
                                body.appendChild(col);
                            });

                            if (!wk.length) {
                                const empty = el('div', 'rsm-empty');
                                empty.appendChild(el('span', '', 'No classes or reservations this week'));
                                body.appendChild(empty);
                            }

                            /* Week label, counter and arrows */
                            const lbl = $('rsm-week-lbl');
                            if (lbl) lbl.textContent = weekRangeLabel(days[0].iso, days[days.length - 1].iso);
                            const sub = $('rsm-week-sub');
                            if (sub) {
                                const nc = wk.filter(r => r.kind === 'class').length,
                                    nr = wk.length - nc;
                                const parts = [];
                                if (nc) parts.push(nc + (nc === 1 ? ' class' : ' classes'));
                                if (nr) parts.push(nr + (nr === 1 ? ' reservation' : ' reservations'));
                                sub.textContent = relativeWeek(_weekOff) + ' · ' + (parts.length ? parts.join(' · ') : 'nothing scheduled');
                            }
                            const prev = $('rsm-prev'),
                                next = $('rsm-next');
                            if (prev) prev.disabled = _weekOff <= bounds.lo;
                            if (next) next.disabled = _weekOff >= bounds.hi;

                            /* Day tabs for the phone layout (one day at a time) */
                            const tabs = $('rsm-daytabs');
                            if (tabs) {
                                tabs.textContent = '';
                                days.forEach(d => {
                                    const b = el('button', 'rsm-daytab-btn');
                                    b.type = 'button';
                                    b.dataset.day = d.idx;
                                    b.appendChild(el('span', '', d.name + ' ' + d.num));
                                    const n = wk.filter(r => r.date === d.iso).length;
                                    if (n) b.appendChild(el('span', 'rsm-daytab-count', String(n)));
                                    tabs.appendChild(b);
                                });
                            }
                            applyMobileDay(dayCount);

                            /* Bring the first reservation of the week into view */
                            if (opts.scroll) {
                                const sc = $('rsm-body');
                                if (sc) {
                                    sc.scrollTop = wk.length ?
                                        Math.max(0, (Math.min.apply(null, wk.map(r => r.s)) - a0) / 60 * HOUR_PX - HOUR_PX / 2) :
                                        0;
                                }
                            }
                            _lastSig = signature(now);
                        }

                        function signature(now) {
                            return JSON.stringify(_rows) + JSON.stringify(_classes) + '|' + _weekOff + '|' + now.iso + '|' + Math.floor(now.min / 5);
                        }

                        /* — Pull the latest reservations from the server — */
                        function setSyncNote(show) {
                            const n = $('rsm-sync-note');
                            if (n) n.classList.toggle('hidden', !show);
                        }

                        function fetchFresh() {
                            if (!_roomId) return;
                            const token = ++_reqToken;
                            const rid = _roomId;
                            fetch('room-reservation/api/admin-room-schedule.php?room_id=' + encodeURIComponent(rid), {
                                    credentials: 'same-origin',
                                    cache: 'no-store',
                                    headers: {
                                        'Accept': 'application/json'
                                    }
                                })
                                .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
                                .then(j => {
                                    if (token !== _reqToken || rid !== _roomId) return; // closed or switched room
                                    if (!j || !Array.isArray(j.reservations)) throw new Error('Bad response');
                                    _rows = apiRows(j.reservations);
                                    _classes = apiClasses(j.classes);
                                    setSyncNote(false);
                                    if (signature(manilaNow()) !== _lastSig) renderGrid();
                                })
                                .catch(err => {
                                    if (token !== _reqToken) return;
                                    console.warn('Facility schedule refresh failed:', err);
                                    setSyncNote(true);
                                });
                        }

                        function stopRefresh() {
                            _reqToken++;
                            if (_timer) {
                                clearInterval(_timer);
                                _timer = null;
                            }
                        }

                        function startRefresh() {
                            stopRefresh();
                            _timer = setInterval(fetchFresh, REFRESH_MS);
                        }

                        /* — Public API — */
                        window.psOpenSchedule = function(btn) {
                            const card = btn.closest('.pr-room-row');
                            if (!card) return;
                            if (card.dataset.roomLocked === '1') return; // locked rooms never open their schedule
                            _roomId = parseInt(card.dataset.roomId) || null;
                            _roomName = card.dataset.roomName || '';
                            _weekOff = 0;
                            _mobDay = todayColumn(manilaNow());
                            _rows = embeddedRows();
                            _classes = [];
                            const title = $('rsm-title');
                            if (title) title.textContent = 'Facility Schedule – ' + _roomName;
                            const meta = $('rsm-meta');
                            if (meta) {
                                const campus = card.dataset.roomCampus || '';
                                const floor = card.dataset.roomFloor || '';
                                meta.textContent = [campus, floor].filter(Boolean).join(' – ');
                            }
                            setSyncNote(false);
                            const modal = $('roomScheduleModal');
                            if (modal) modal.classList.remove('hidden');
                            try {
                                renderGrid({
                                    scroll: true
                                });
                            } catch (e) {
                                console.warn('renderGrid error:', e);
                            }
                            startRefresh();
                            fetchFresh(); // swap in the latest data as soon as it arrives
                        };

                        window.psCloseSchedule = function() {
                            const modal = $('roomScheduleModal');
                            if (modal) modal.classList.add('hidden');
                            stopRefresh();
                        };

                        window.psScheduleNav = function(dir) {
                            _weekOff += dir;
                            renderGrid({
                                scroll: true
                            });
                        };

                        document.addEventListener('click', function(e) {
                            const modal = $('roomScheduleModal');
                            if (!modal || modal.classList.contains('hidden')) return;
                            const tab = e.target.closest('.rsm-daytab-btn');
                            if (tab) {
                                _mobDay = +tab.dataset.day || 1;
                                applyMobileDay(document.querySelectorAll('#rsm-daytabs .rsm-daytab-btn').length);
                            }
                        });

                        document.addEventListener('keydown', function(e) {
                            if (e.key !== 'Escape') return;
                            const modal = $('roomScheduleModal');
                            if (modal && !modal.classList.contains('hidden')) window.psCloseSchedule();
                        });

                        /* ── Whole tile opens the schedule ──────────────────────────
                           The tile itself is the "view schedule" control. Clicks inside
                           the actions cell (the Edit icon) are skipped so Edit keeps
                           working on its own. Delegated on document, so tiles swapped in
                           by the AJAX refresh work without re-binding. */
                        document.addEventListener('click', function(e) {
                            var tile = e.target.closest('#roomsRegistryTable tr.pr-room-row');
                            if (!tile) return;
                            if (e.target.closest('.pr-row-actions')) return;
                            if (tile.dataset.roomLocked === '1') return; // locked: schedule stays closed
                            window.psOpenSchedule(tile);
                        });
                        document.addEventListener('keydown', function(e) {
                            if (e.key !== 'Enter' && e.key !== ' ') return;
                            var tile = e.target.closest ? e.target.closest('#roomsRegistryTable tr.pr-room-row') : null;
                            if (!tile || e.target !== tile) return;
                            e.preventDefault();
                            if (tile.dataset.roomLocked === '1') return;
                            window.psOpenSchedule(tile);
                        });

                        /* ── edit-room-inline: populate & show form without page reload ── */
                        document.addEventListener('click', function(e) {
                            var btn = e.target.closest('[data-action="edit-room-inline"]');
                            if (!btn) return;
                            var card = btn.closest('.pr-room-row');
                            if (!card) return;
                            var d = card.dataset;
                            var form = document.getElementById('roomForm');
                            if (!form) return;

                            /* Populate all fields */
                            var el;
                            el = document.getElementById('room-form-id');
                            if (el) el.value = d.roomId || '';

                            el = form.querySelector('select[name="building_id"]');
                            if (el) el.value = d.roomBuildingId || '';

                            el = form.querySelector('input[name="room_name"]');
                            if (el) el.value = d.roomName || '';

                            el = form.querySelector('input[name="floor_number"]');
                            if (el) el.value = d.roomFloorNum || '1';
                            if (window.psSyncRoomFloorLimit) window.psSyncRoomFloorLimit(false);

                            /* Keep hidden floor_label + sort_order in sync with row data */
                            el = document.getElementById('rf-floor-label');
                            if (el) el.value = d.roomFloorLabel || '';

                            el = document.getElementById('rf-sort');
                            if (el) el.value = d.roomSort || '0';

                            el = form.querySelector('select[name="status"]');
                            if (el) el.value = d.roomStatus || 'Available';

                            var amenities = [];
                            try {
                                amenities = JSON.parse(d.roomAmenities || '[]');
                            } catch (x) {}
                            form.querySelectorAll('input[name="amenities[]"]').forEach(function(cb) {
                                cb.checked = amenities.indexOf(cb.value) !== -1;
                            });

                            /* Update title + icon */
                            el = document.getElementById('room-form-title');
                            if (el) el.textContent = 'Edit Facility — ' + (d.roomName || '');
                            el = document.querySelector('#room-form-wrap .rmod-head-icon .material-symbols-outlined');
                            if (el) el.textContent = 'edit';

                            /* Switch submit buttons to update_room */
                            ['room-form-submit-inner', 'room-form-submit-foot'].forEach(function(id) {
                                var b = document.getElementById(id);
                                if (b) b.name = 'update_room';
                            });
                            el = document.getElementById('room-submit-inner-label');
                            if (el) el.textContent = 'Update Facility';
                            el = document.getElementById('room-submit-foot-label');
                            if (el) el.textContent = 'Save Changes';

                            /* Archive zone */
                            var az = document.getElementById('room-archive-zone');
                            if (az) az.classList.remove('hidden');
                            var al = document.getElementById('room-archive-link');
                            if (al) al.href = 'admin-dashboard.php?archive_room=' + encodeURIComponent(d.roomId || '');
                            if (window.psSetLockButton) window.psSetLockButton(d.roomLocked === '1');

                            /* Show form and scroll */
                            var rfw = document.getElementById('room-form-wrap');
                            if (rfw) {
                                rfw.classList.remove('hidden');
                                setTimeout(function() {
                                    rfw.scrollIntoView({
                                        behavior: 'smooth',
                                        block: 'start'
                                    });
                                }, 30);
                            }
                        });

                        /* Put the room form back into Add-mode with empty fields. Runs on Cancel/Close,
                           before "Add Room" opens it, and after a successful save -- so the form can
                           never reopen still holding the last room that was edited. */
                        window.psResetRoomForm = function() {
                            var form = document.getElementById('roomForm');
                            var el;
                            el = document.getElementById('room-form-title');
                            if (el) el.textContent = 'Add New Facility';
                            el = document.querySelector('#room-form-wrap .rmod-head-icon .material-symbols-outlined');
                            if (el) el.textContent = 'add_home_work';
                            ['room-form-submit-inner', 'room-form-submit-foot'].forEach(function(id) {
                                var b = document.getElementById(id);
                                if (b) b.name = 'add_room';
                            });
                            el = document.getElementById('room-submit-inner-label');
                            if (el) el.textContent = 'Add Facility';
                            el = document.getElementById('room-submit-foot-label');
                            if (el) el.textContent = 'Add Facility';
                            var az = document.getElementById('room-archive-zone');
                            if (az) az.classList.add('hidden');
                            el = document.getElementById('room-form-id');
                            if (el) el.value = '';
                            if (!form) return;
                            el = form.querySelector('select[name="building_id"]');
                            if (el) el.value = '';
                            el = form.querySelector('input[name="room_name"]');
                            if (el) el.value = '';
                            el = form.querySelector('input[name="floor_number"]');
                            if (el) el.value = '1';
                            if (window.psSyncRoomFloorLimit) window.psSyncRoomFloorLimit(false);
                            el = document.getElementById('rf-floor-label');
                            if (el) el.value = '';
                            el = document.getElementById('rf-sort');
                            if (el) el.value = '0';
                            el = form.querySelector('select[name="status"]');
                            if (el) el.value = 'Available';
                            el = form.querySelector('input[name="sort_order"]');
                            if (el) el.value = '0';
                            form.querySelectorAll('input[name="amenities[]"]').forEach(function(cb) {
                                cb.checked = false;
                            });
                            el = document.getElementById('room-more-opts');
                            if (el) el.open = false;
                        };

                        /* Floor can't go past the selected building's number of floors (tbl_buildings.floor_count).
                           clamp = true only when the admin switches building, so editing never rewrites saved data. */
                        window.psSyncRoomFloorLimit = function(clamp) {
                            var sel = document.getElementById('rf-building');
                            var fl = document.getElementById('rf-floor');
                            var hint = document.getElementById('rf-floor-hint');
                            if (!sel || !fl) return;
                            var opt = sel.options[sel.selectedIndex];
                            var max = opt ? parseInt(opt.getAttribute('data-floors'), 10) : NaN;
                            if (!max || max < 1) {
                                fl.max = '20';
                                if (hint) hint.textContent = 'Select a building to see how many floors it has.';
                                return;
                            }
                            fl.max = String(max);
                            if (clamp && parseInt(fl.value, 10) > max) fl.value = String(max);
                            var name = (opt.textContent || '').replace(/\s+/g, ' ').trim();
                            if (hint) hint.textContent = name + ' has ' + max + (max === 1 ? ' floor' : ' floors') + ' (1\u2013' + max + ').';
                        };
                        document.addEventListener('change', function(e) {
                            if (e.target && e.target.id === 'rf-building') window.psSyncRoomFloorLimit(true);
                        });
                        window.psSyncRoomFloorLimit(false);

                        document.addEventListener('click', function(e) {
                            if (e.target.closest('[data-action="hide-room-form"]') ||
                                e.target.closest('[data-action="show-room-form"]')) {
                                window.psResetRoomForm();
                            }
                        });

                        document.addEventListener('click', function(e) {
                            const modal = document.getElementById('roomScheduleModal');
                            if (e.target.closest('[data-action="open-room-schedule"]')) {
                                window.psOpenSchedule(e.target.closest('[data-action="open-room-schedule"]'));
                                return;
                            }
                            if (e.target.closest('[data-action="close-room-schedule"]')) {
                                window.psCloseSchedule();
                                return;
                            }
                            const navBtn = e.target.closest('[data-action="room-schedule-nav"]');
                            if (navBtn) {
                                window.psScheduleNav(parseInt(navBtn.dataset.dir));
                                return;
                            }
                            if (modal && !modal.classList.contains('hidden') && e.target === modal)
                                window.psCloseSchedule();
                            const rfw = document.getElementById('room-form-wrap');
                            if (rfw && !rfw.classList.contains('hidden') && e.target === rfw)
                                rfw.classList.add('hidden');
                        });
                    })();
