/* ================================================================
   PUPSYNC — FACILITIES TAB  (fcty-facilities.js)
   Browse Facilities:  Buildings A · B · C → Rooms → Room dialog
                                                     → Reserve / Report

   Companion to fcty-facilities.php + fcty-facilities.css.
   Data:  room-reservation/api/get-facilities.php      (building tree)
          room-reservation/api/get-room-schedule.php   (week schedule)
          room-reservation/api/poll-room-status.php    (live status)
          room-reservation/api/check-room-availability.php
          room-reservation/api/submit-faculty-reserve.php
          room-reservation/api/submit-room-issue.php
   Icons: Material Symbols Outlined only.
================================================================ */
(function () {
    'use strict';

    /* ── Display names ──────────────────────────────────────────
       The three PUP buildings, in display order. Names are applied
       here (UI only) so the database and admin side stay untouched. */
    var BUILDING_LABELS = {
        'main-building-a': 'Building A (Old)',
        'main-building-b': 'Building B (New)',
        'cite-main': 'Building C (CITE)'
    };
    var BUILDING_ORDER = ['main-building-a', 'main-building-b', 'cite-main'];

    /* ── State ──────────────────────────────────────────────── */
    var BUILDING_LIST = [];    /* ordered building entries                      */
    var BUILDINGS = {};        /* building key → entry                          */
    var ROOM_INDEX = {};       /* room_id → room object (live-updated)          */
    var LIVE_STATUS = {};      /* room_id → latest polled status (incl. Booked) */

    var activeBuildingId = null;
    var activeFloorIdx = 0;
    var onlyFree = false;
    var currentRoom = null;    /* { room, name, location } for the open dialog  */

    var loaded = false;
    var loading = false;
    var pollTimer = null;
    var POLL_MS = 30000;

    var SCHOOL_START_MIN = 7 * 60;
    var SCHOOL_END_MIN = 20 * 60;
    var DAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    var DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    var WEEK_ORDER = [1, 2, 3, 4, 5, 6, 0];   /* Mon → Sun */

    var STATUS_UI = {
        'Available': { cls: 'status-available', text: 'Available' },
        'Booked': { cls: 'status-booked', text: 'In use' },
        'Maintenance': { cls: 'status-maintenance', text: 'Maintenance' },
        'Not Bookable': { cls: 'status-static', text: 'Not bookable' }
    };

    var PURPOSES = ['Lecture', 'Lab session', 'Meeting', 'Exam', 'Seminar'];

    var ISSUE_TYPES = ['Air conditioning', 'Projector / AV', 'Furniture', 'Lighting', 'Cleanliness', 'Other'];

    /* ── DOM refs (resolved in init) ────────────────────────── */
    var $ = function (id) { return document.getElementById(id); };
    var buildingsView, roomsView, panelsEl, floorTabs, roomGrid, switcherEl;
    var roomOverlay, formOverlay;

    /* ── Helpers ────────────────────────────────────────────── */
    function esc(str) {
        return String(str === null || str === undefined ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function decodeHtml(str) {
        var t = document.createElement('textarea');
        t.innerHTML = str;
        return t.value;
    }

    function plural(n, one, many) { return n + ' ' + (n === 1 ? one : (many || one + 's')); }

    function apiBase() {
        var p = window.location.pathname;
        return p.substring(0, p.lastIndexOf('/') + 1);
    }

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function icon(name) {
        return '<span class="material-symbols-outlined" aria-hidden="true">' + name + '</span>';
    }

    /* Dates are handled in local time (toISOString would shift the day in UTC+8) */
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function parseYmd(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function addDays(d, n) { var x = new Date(d.getTime()); x.setDate(x.getDate() + n); return x; }
    function mondayOf(d) { return addDays(d, -((d.getDay() + 6) % 7)); }
    function longDate(d) { return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }); }

    function timeToMin(t) {
        var p = String(t).split(':');
        return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
    }

    function minToHHMM(m) { return pad(Math.floor(m / 60)) + ':' + pad(m % 60); }

    function minToLabel(mins) {
        var h = Math.floor(mins / 60), m = mins % 60;
        var h12 = h % 12 === 0 ? 12 : h % 12;
        return h12 + ':' + pad(m) + ' ' + (h >= 12 ? 'PM' : 'AM');
    }

    function timeRange(start, end) {
        return minToLabel(timeToMin(start)) + ' \u2013 ' + minToLabel(timeToMin(end));
    }

    function durLabel(mins) {
        var h = Math.floor(mins / 60), m = mins % 60;
        if (!h) return m + ' min';
        return h + (h === 1 ? ' hour' : ' hours') + (m ? ' ' + m + ' min' : '');
    }

    function sortByStart(list) {
        return (list || []).slice().sort(function (a, b) {
            return timeToMin(a.start) - timeToMin(b.start);
        });
    }

    function uiFor(status) { return STATUS_UI[status] || STATUS_UI.Available; }
    function displayStatus(room) { return LIVE_STATUS[room.room_id] || room.status; }

    function freeCount(b) {
        var n = 0;
        b.floors.forEach(function (f) {
            f.rooms.forEach(function (r) { if (displayStatus(r) === 'Available') n++; });
        });
        return n;
    }

    /* ══════════════════════════════════════════════════════════
       DATA LOADING
    ══════════════════════════════════════════════════════════ */
    function loadFacilities(done) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'room-reservation/api/get-facilities.php', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            if (xhr.status !== 200) { done(false); return; }
            try {
                ingest(JSON.parse(xhr.responseText));
                done(true);
            } catch (e) {
                console.error('[PUPSync Facilities] parse error', e);
                done(false);
            }
        };
        xhr.send();
    }

    /* Flatten campuses → one ordered list of buildings */
    function ingest(campuses) {
        BUILDING_LIST = []; BUILDINGS = {}; ROOM_INDEX = {}; LIVE_STATUS = {};
        var seq = 0;
        campuses.forEach(function (c) {
            c.buildings.forEach(function (b) {
                var rooms = 0;
                b.floor_data.forEach(function (f) {
                    f.rooms.forEach(function (r) { ROOM_INDEX[r.room_id] = r; rooms++; });
                });

                var name = BUILDING_LABELS[b.id] || b.name;
                var m = /^(.*?)\s*\((.+)\)\s*$/.exec(name);
                var base = m ? m[1] : name;
                var letter = (/Building\s+([A-Za-z])/.exec(base) || [null, base.charAt(0)])[1].toUpperCase();
                var rank = BUILDING_ORDER.indexOf(b.id);

                var entry = {
                    id: b.id, name: name, base: base, tag: m ? m[2] : '', letter: letter,
                    image: b.image, rooms: rooms, floors: b.floor_data,
                    _rank: rank === -1 ? 100 + (seq++) : rank
                };
                BUILDING_LIST.push(entry);
                BUILDINGS[b.id] = entry;
            });
        });
        BUILDING_LIST.sort(function (a, b) { return a._rank - b._rank; });
    }

    function start() {
        if (loaded) { startPolling(); return; }
        if (loading) return;
        loading = true;
        var err = $('fcty-load-error');
        if (err) err.hidden = true;
        loadFacilities(function (ok) {
            loading = false;
            if (!ok) {
                panelsEl.innerHTML = '';
                if (err) err.hidden = false;
                return;
            }
            loaded = true;
            renderPanels();
            startPolling();
        });
    }

    /* ══════════════════════════════════════════════════════════
       STEP 1 — THREE BUILDING PANELS
    ══════════════════════════════════════════════════════════ */
    function panelHTML(b) {
        var free = freeCount(b);
        return '<button type="button" class="fcty-panel" data-building-id="' + esc(b.id) + '" ' +
            'aria-label="' + esc(b.name) + ', ' + plural(b.rooms, 'room') + '">' +
            (b.image ? '<img class="fcty-panel-img" src="' + esc(b.image) + '" alt="">' : '') +
            '<span class="fcty-panel-shade"></span>' +
            '<span class="fcty-panel-letter" aria-hidden="true">' + esc(b.letter) + '</span>' +
            '<span class="fcty-panel-live"><span class="fcty-dot"></span><span data-live-count>' + free + ' free now</span></span>' +
            '<span class="fcty-panel-info">' +
            '<span class="fcty-panel-name">' + esc(b.base) + (b.tag ? '<em class="fcty-tag">' + esc(b.tag) + '</em>' : '') + '</span>' +
            '<span class="fcty-panel-meta">' + plural(b.rooms, 'room') + ' \u00b7 ' + plural(b.floors.length, 'floor') + '</span>' +
            '<span class="fcty-panel-reveal"><span class="fcty-panel-floors">' +
            b.floors.map(function (f) { return '<span>' + esc(f.label) + '</span>'; }).join('') +
            '</span></span>' +
            '</span>' +
            '<span class="fcty-go" aria-hidden="true">' + icon('arrow_forward') + '</span>' +
            '</button>';
    }

    function renderPanels() {
        if (!BUILDING_LIST.length) {
            panelsEl.innerHTML = '<div class="fcty-empty" style="flex:1">' + icon('construction') +
                '<strong>No buildings yet</strong><span>Facilities are still being set up.</span></div>';
            $('fcty-landing-summary').textContent = '';
            return;
        }
        panelsEl.innerHTML = BUILDING_LIST.map(panelHTML).join('');
        panelsEl.querySelectorAll('img.fcty-panel-img').forEach(function (img) {
            img.addEventListener('error', function () { img.remove(); });
        });
        updateLiveCounts();
    }

    function updateLiveCounts() {
        var total = 0;
        BUILDING_LIST.forEach(function (b) {
            var n = freeCount(b);
            total += n;
            var el = panelsEl.querySelector('[data-building-id="' + b.id + '"] [data-live-count]');
            if (el) el.textContent = n + ' free now';
        });
        if (BUILDING_LIST.length) {
            $('fcty-landing-summary').textContent = plural(total, 'room') + ' free now';
        }
        if (activeBuildingId && roomsView.style.display !== 'none') renderHeroSummary();
    }

    function hideAllViews() {
        buildingsView.style.display = 'none';
        roomsView.style.display = 'none';
    }

    function showBuildingsView() {
        hideAllViews();
        buildingsView.style.display = '';
        activeBuildingId = null;
    }

    /* ══════════════════════════════════════════════════════════
       STEP 2 — ROOMS
    ══════════════════════════════════════════════════════════ */
    function roomCardHTML(room) {
        var shown = displayStatus(room);
        var ui = uiFor(shown);
        var locked = shown === 'Not Bookable';
        var seats = room.seating_capacity !== null && room.seating_capacity !== undefined
            ? '<span class="fcty-room-seats">' + icon('chair') + room.seating_capacity + '</span>' : '';
        return '<button type="button" class="fcty-room ' + ui.cls + '" data-room-id="' + room.room_id + '"' +
            (locked ? ' disabled aria-disabled="true"' : '') + '>' +
            '<span class="fcty-room-name">' + esc(room.name) + '</span>' +
            '<span class="fcty-room-foot">' +
            '<span class="fcty-room-status"><span class="fcty-dot"></span>' +
            '<span class="fcty-room-status-text">' + ui.text + '</span></span>' + seats +
            '</span></button>';
    }

    function renderFloor(idx) {
        var b = BUILDINGS[activeBuildingId];
        if (!b) return;
        activeFloorIdx = idx;

        floorTabs.querySelectorAll('.fcty-floor-tab').forEach(function (tab, i) {
            tab.setAttribute('aria-selected', i === idx ? 'true' : 'false');
            tab.tabIndex = i === idx ? 0 : -1;
        });

        var floor = b.floors[idx];
        var rooms = floor ? floor.rooms.filter(function (r) {
            return !onlyFree || displayStatus(r) === 'Available';
        }) : [];

        if (rooms.length) {
            roomGrid.innerHTML = rooms.map(roomCardHTML).join('');
        } else {
            roomGrid.innerHTML = '<div class="fcty-empty">' + icon('meeting_room') +
                '<strong>' + (onlyFree ? 'Nothing available on this floor' : 'No rooms on this floor') + '</strong>' +
                (onlyFree ? '<span>Try another floor or turn off the filter.</span>' : '') + '</div>';
        }
    }

    function renderHeroSummary() {
        var b = BUILDINGS[activeBuildingId];
        if (!b) return;
        $('fcty-rooms-summary').innerHTML =
            '<span>' + plural(b.rooms, 'room') + '</span>' +
            '<span>' + plural(b.floors.length, 'floor') + '</span>' +
            '<span class="fcty-live"><span class="fcty-dot"></span>' + freeCount(b) + ' free now</span>';
    }

    function renderSwitcher() {
        switcherEl.innerHTML = BUILDING_LIST.map(function (b) {
            return '<button type="button" class="fcty-sw-btn" role="tab" data-building-id="' + esc(b.id) + '" ' +
                'aria-label="' + esc(b.name) + '" aria-selected="' + (b.id === activeBuildingId ? 'true' : 'false') + '">' +
                '<span class="fcty-sw-letter">' + esc(b.letter) + '</span>' +
                '<span>' + esc(b.tag || b.base) + '</span></button>';
        }).join('');
    }

    function showRoomsView(buildingId) {
        var b = BUILDINGS[buildingId];
        if (!b) return;
        activeBuildingId = buildingId;

        $('fcty-rooms-breadcrumb-building').textContent = b.name;
        $('fcty-rooms-hero-title').innerHTML = esc(b.base) + (b.tag ? '<em class="fcty-tag">' + esc(b.tag) + '</em>' : '');
        $('fcty-hero-img').style.backgroundImage = b.image ? 'url("' + encodeURI(b.image) + '")' : 'none';
        renderHeroSummary();
        renderSwitcher();

        floorTabs.style.display = b.floors.length > 1 ? '' : 'none';
        floorTabs.innerHTML = b.floors.map(function (f, i) {
            return '<button type="button" class="fcty-floor-tab" role="tab" data-floor="' + i + '">' +
                '<span>' + esc(f.label) + '</span>' +
                '<span class="fcty-floor-count">' + f.rooms.length + '</span></button>';
        }).join('');

        /* Open on the first floor that actually has something to book */
        var first = 0;
        for (var i = 0; i < b.floors.length; i++) {
            if (b.floors[i].rooms.some(function (r) { return displayStatus(r) !== 'Not Bookable'; })) { first = i; break; }
        }
        renderFloor(first);

        hideAllViews();
        roomsView.style.display = '';
    }

    /* ══════════════════════════════════════════════════════════
       LIVE STATUS POLLING  (visual only — the arbitration engine
       always checks the database itself)
    ══════════════════════════════════════════════════════════ */
    function applyStatus(card, status) {
        var ui = uiFor(status);
        card.className = 'fcty-room ' + ui.cls;
        var txt = card.querySelector('.fcty-room-status-text');
        if (txt) txt.textContent = ui.text;
        var locked = status === 'Not Bookable';
        card.disabled = locked;
        if (locked) card.setAttribute('aria-disabled', 'true'); else card.removeAttribute('aria-disabled');
    }

    function pollOnce() {
        fetch(apiBase() + 'room-reservation/api/poll-room-status.php', { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (statuses) {
                if (!statuses) return;
                Object.keys(statuses).forEach(function (rid) {
                    var room = ROOM_INDEX[rid];
                    var st = statuses[rid];
                    LIVE_STATUS[rid] = st;
                    /* "Booked" is a right-now display state, never stored as the admin status */
                    if (room && st !== 'Booked') room.status = st;
                });
                if (activeBuildingId && roomsView.style.display !== 'none') {
                    if (onlyFree) renderFloor(activeFloorIdx);
                    else roomGrid.querySelectorAll('.fcty-room[data-room-id]').forEach(function (card) {
                        var st = statuses[card.dataset.roomId];
                        if (st) applyStatus(card, st);
                    });
                }
                updateLiveCounts();
            })
            .catch(function () { /* polling must never break the UI */ });
    }

    function startPolling() {
        if (pollTimer) return;
        pollOnce();
        pollTimer = setInterval(pollOnce, POLL_MS);
    }

    function stopPolling() {
        clearInterval(pollTimer);
        pollTimer = null;
    }

    /* ══════════════════════════════════════════════════════════
       DIALOG PLUMBING  (focus trap, scroll lock, Escape)
    ══════════════════════════════════════════════════════════ */
    var activeOverlay = null;
    var lastFocus = null;
    var FOCUSABLE = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function openOverlay(el, focusSel) {
        if (document.body.style.overflow !== 'hidden') lastFocus = document.activeElement;
        activeOverlay = el;
        el.classList.add('open');
        el.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        var target = focusSel ? el.querySelector(focusSel) : null;
        (target || el.querySelector('.fcty-icon-btn') || el).focus();
    }

    function closeOverlay(el, keepFocusState) {
        if (!el || !el.classList.contains('open')) return;
        el.classList.remove('open');
        el.setAttribute('aria-hidden', 'true');
        if (activeOverlay === el) activeOverlay = null;
        if (!activeOverlay && !keepFocusState) {
            document.body.style.overflow = '';
            if (lastFocus && lastFocus.focus) lastFocus.focus();
            lastFocus = null;
        }
    }

    function onDocKey(e) {
        if (!activeOverlay) return;
        if (e.key === 'Escape') { closeOverlay(activeOverlay); return; }
        if (e.key !== 'Tab') return;
        var items = Array.prototype.filter.call(activeOverlay.querySelectorAll(FOCUSABLE), function (n) {
            return n.offsetParent !== null;
        });
        if (!items.length) { e.preventDefault(); return; }
        var first = items[0], last = items[items.length - 1];
        if (e.shiftKey && (document.activeElement === first || !activeOverlay.contains(document.activeElement))) {
            e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault(); first.focus();
        }
    }

    /* ══════════════════════════════════════════════════════════
       ROOM DETAILS DIALOG
    ══════════════════════════════════════════════════════════ */
    function setStatusBadge(kind, text) {
        $('fcty-modal-availability').className = 'fcty-status' + (kind ? ' is-' + kind : '');
        $('fcty-modal-availability-text').textContent = text;
    }

    function setScheduleTab(name) {
        roomOverlay.querySelectorAll('.fcty-seg-btn').forEach(function (t) {
            var on = t.dataset.scheduleTab === name;
            t.classList.toggle('active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        roomOverlay.querySelectorAll('.fcty-schedule-panel').forEach(function (p) {
            p.classList.toggle('active', p.dataset.schedulePanel === name);
        });
    }

    function note(text) {
        return '<div class="fcty-slot vacant is-note"><span class="fcty-slot-what">' + text + '</span></div>';
    }

    /* Only real bookings are listed — no filler "Vacant" rows */
    function dailyHTML(daySchedule) {
        var list = sortByStart(daySchedule);
        var hours = minToLabel(SCHOOL_START_MIN) + ' \u2013 ' + minToLabel(SCHOOL_END_MIN);
        if (!list.length) return note('No bookings today \u00b7 open ' + hours);
        return list.map(function (s) {
            return '<div class="fcty-slot occupied"><span class="fcty-slot-time">' + timeRange(s.start, s.end) +
                '</span><span class="fcty-slot-what">' + s.label + '</span></div>';
        }).join('') + note('Open ' + hours);
    }

    function weeklyHTML(week) {
        var today = new Date().getDay();
        return WEEK_ORDER.map(function (d) {
            var items = sortByStart((week || {})[DAY_KEYS[d]]);
            var body = items.length
                ? items.map(function (s) {
                    return '<div><time>' + timeRange(s.start, s.end) + '</time>' + s.label + '</div>';
                }).join('')
                : '<span class="is-free">No bookings</span>';
            return '<div class="fcty-week-row' + (d === today ? ' is-today' : '') + '">' +
                '<span class="fcty-week-day">' + DAY_NAMES[d].slice(0, 3) + '</span>' +
                '<div class="fcty-week-items">' + body + '</div></div>';
        }).join('');
    }

    function freeNow(daySchedule) {
        var now = new Date(), m = now.getHours() * 60 + now.getMinutes();
        return !(daySchedule || []).some(function (s) {
            return m >= timeToMin(s.start) && m < timeToMin(s.end);
        });
    }

    /* "Free until 2:00 PM" / "Busy until 10:30 AM" */
    function availabilityHint(daySchedule) {
        var now = new Date(), m = now.getHours() * 60 + now.getMinutes();
        if (m < SCHOOL_START_MIN) return 'Opens at ' + minToLabel(SCHOOL_START_MIN);
        if (m >= SCHOOL_END_MIN) return 'Closed for today';
        var list = sortByStart(daySchedule), i;
        for (i = 0; i < list.length; i++) {
            if (m >= timeToMin(list[i].start) && m < timeToMin(list[i].end)) return 'Busy until ' + minToLabel(timeToMin(list[i].end));
        }
        for (i = 0; i < list.length; i++) {
            if (timeToMin(list[i].start) > m) return 'Free until ' + minToLabel(timeToMin(list[i].start));
        }
        return 'Free until ' + minToLabel(SCHOOL_END_MIN);
    }

    function openRoomModal(room, locationLabel) {
        currentRoom = { room: room, name: room.name, location: locationLabel };

        $('fcty-modal-room-name').textContent = room.name;
        $('fcty-modal-location').textContent = locationLabel;
        $('fcty-modal-capacity').textContent = room.seating_capacity !== null && room.seating_capacity !== undefined
            ? room.seating_capacity : '\u2014';
        $('fcty-modal-hint').hidden = true;

        var blocked = room.status === 'Maintenance' || room.status === 'Not Bookable';
        if (room.status === 'Maintenance') setStatusBadge('maintenance', 'Maintenance');
        else if (room.status === 'Not Bookable') setStatusBadge('static', 'Not bookable');
        else setStatusBadge('', 'Checking\u2026');

        var today = new Date();
        $('fcty-modal-day-label').textContent = DAY_NAMES[today.getDay()];
        $('fcty-modal-daily-list').innerHTML = note('Loading schedule\u2026');
        $('fcty-modal-weekly-grid').innerHTML = '';
        setScheduleTab('daily');

        var reserve = $('fcty-modal-reserve');
        reserve.disabled = blocked;
        reserve.title = blocked ? 'This room cannot be reserved' : '';

        closeOverlay(formOverlay, true);
        openOverlay(roomOverlay);

        fetchWeek(room.room_id, ymd(mondayOf(today)))
            .then(function (week) {
                if (!currentRoom || currentRoom.room !== room) return;   /* dialog moved on */
                var todays = week[DAY_KEYS[today.getDay()]] || [];
                $('fcty-modal-daily-list').innerHTML = dailyHTML(todays);
                $('fcty-modal-weekly-grid').innerHTML = weeklyHTML(week);
                if (!blocked) {
                    if (freeNow(todays)) setStatusBadge('', 'Available');
                    else setStatusBadge('booked', 'In use now');
                    $('fcty-modal-hint-text').textContent = availabilityHint(todays);
                    $('fcty-modal-hint').hidden = false;
                }
            })
            .catch(function () {
                if (!currentRoom || currentRoom.room !== room) return;
                $('fcty-modal-daily-list').innerHTML = note('Couldn\u2019t load the schedule right now.');
                if (!blocked) setStatusBadge('', 'Available');
            });
    }

    /* Week schedule with a tiny per-room cache (cleared whenever a booking is made) */
    var weekCache = {};
    function fetchWeek(roomId, weekStart) {
        var key = roomId + '|' + weekStart;
        if (weekCache[key]) return Promise.resolve(weekCache[key]);
        return fetch(apiBase() + 'room-reservation/api/get-room-schedule.php?room_id=' + encodeURIComponent(roomId) +
            '&week_start=' + encodeURIComponent(weekStart), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error || !data.week) throw new Error(data.error || 'no data');
                weekCache[key] = data.week;
                return data.week;
            });
    }

    /* ══════════════════════════════════════════════════════════
       SHARED DIALOG SHELL + ALERT HELPERS
    ══════════════════════════════════════════════════════════ */
    function dialogShell(iconName, title, roomName, bodyHTML, footHTML) {
        formOverlay.innerHTML =
            '<div class="fcty-dialog" role="dialog" aria-modal="true" aria-labelledby="fcty-form-title">' +
            '<header class="fcty-dialog-head">' +
            '<span class="fcty-tile-icon" aria-hidden="true">' + icon(iconName) + '</span>' +
            '<div class="fcty-dialog-heading"><h3 class="fcty-dialog-title" id="fcty-form-title">' + title + '</h3>' +
            '<p class="fcty-dialog-sub">' + esc(roomName) + '</p></div>' +
            '<button type="button" class="fcty-icon-btn" id="fcty-form-close" aria-label="Close">' + icon('close') + '</button>' +
            '</header>' +
            '<div class="fcty-dialog-body">' + bodyHTML + '</div>' +
            '<footer class="fcty-dialog-foot">' + footHTML + '</footer></div>';
        $('fcty-form-close').addEventListener('click', function () { closeOverlay(formOverlay); });
    }

    function backToRoom() {
        closeOverlay(formOverlay, true);
        if (currentRoom) openRoomModal(currentRoom.room, currentRoom.location);
        else closeOverlay(formOverlay);
    }

    function alertHTML(id, kind) {
        return '<div class="fcty-alert is-' + kind + '" id="' + id + '" role="alert" hidden></div>';
    }

    function setAlert(el, kind, iconName, html) {
        if (!el) return;
        el.className = 'fcty-alert is-' + kind;
        el.innerHTML = icon(iconName) + '<div class="fcty-alert-text">' + html + '</div>';
        el.hidden = false;
    }

    function chipRow(id, labels) {
        return '<div class="fcty-chips" id="' + id + '">' + labels.map(function (l) {
            return '<button type="button" class="fcty-chip" aria-pressed="false" data-val="' + esc(l) + '">' + esc(l) + '</button>';
        }).join('') + '</div>';
    }

    function setPressed(container, test) {
        container.querySelectorAll('.fcty-chip').forEach(function (c) {
            c.setAttribute('aria-pressed', test(c) ? 'true' : 'false');
        });
    }

    /* ══════════════════════════════════════════════════════════
       RESERVE — two-pane booking experience
       Left: live summary + day timeline.  Right: guided form.
    ══════════════════════════════════════════════════════════ */
    function timeOptions(from, to, selected) {
        var html = '';
        for (var m = from; m <= to; m += 30) {
            html += '<option value="' + minToHHMM(m) + '"' + (m === selected ? ' selected' : '') + '>' + minToLabel(m) + '</option>';
        }
        return html;
    }

    function openReservationForm(room) {
        var now = new Date();
        var nowMin = now.getHours() * 60 + now.getMinutes();

        /* Smart defaults: next half-hour today, or 8 AM tomorrow if it's too late */
        var st = { date: ymd(now), start: 0, end: 0, purpose: '' };
        if (nowMin >= SCHOOL_END_MIN - 60) {
            st.date = ymd(addDays(now, 1)); st.start = SCHOOL_START_MIN + 60;
        } else {
            st.start = Math.max(SCHOOL_START_MIN, Math.ceil((nowMin + 1) / 30) * 30);
        }
        st.end = Math.min(SCHOOL_END_MIN, st.start + 60);

        var todayStr = ymd(now);
        var seats = room.seating_capacity !== null && room.seating_capacity !== undefined ? room.seating_capacity : null;
        var weekData = null;          /* schedule for the week of st.date */
        var serverClash = false;
        var availTimer = null, availCtl = null;

        formOverlay.innerHTML =
            '<div class="fcty-dialog fcty-dialog-wide" role="dialog" aria-modal="true" aria-labelledby="fcty-form-title">' +
            '<div class="fcty-res">' +

            /* ── Left: summary + timeline ── */
            '<aside class="fcty-res-side">' +
            '<div>' +
            '<span class="fcty-kicker">' + icon('event_available') + 'Reserve a room</span>' +
            '<h3 class="fcty-res-room">' + esc(room.name) + '</h3>' +
            '<p class="fcty-res-where">' + esc(currentRoom ? currentRoom.location : '') + '</p>' +
            '<div class="fcty-chip-row">' +
            (seats !== null ? '<span class="fcty-seats">' + icon('chair') + plural(seats, 'seat') + '</span>' : '') +
            '<span class="fcty-seats" id="fcty-quota" hidden></span>' +
            '</div>' +
            '</div>' +
            '<ul class="fcty-ticket" aria-live="polite">' +
            '<li>' + icon('event') + '<div><small>Date</small><strong id="fcty-tk-date"></strong></div></li>' +
            '<li>' + icon('schedule') + '<div><small>Time</small><strong id="fcty-tk-time"></strong></div></li>' +
            '<li>' + icon('timer') + '<div><small>Duration</small><strong id="fcty-tk-dur"></strong></div></li>' +
            '</ul>' +
            '<div class="fcty-tl">' +
            '<div class="fcty-tl-head"><span>Room schedule</span><span id="fcty-tl-day"></span></div>' +
            '<div class="fcty-tl-track" id="fcty-tl-track"><div class="fcty-tl-pick" id="fcty-tl-pick"></div></div>' +
            '<div class="fcty-tl-ticks" aria-hidden="true">' +
            [[7, '7 AM'], [10, '10 AM'], [13, '1 PM'], [16, '4 PM'], [20, '8 PM']].map(function (t) {
                return '<span style="left:' + ((t[0] * 60 - SCHOOL_START_MIN) / (SCHOOL_END_MIN - SCHOOL_START_MIN) * 100) + '%">' + t[1] + '</span>';
            }).join('') +
            '</div>' +
            '<div class="fcty-tl-note" id="fcty-tl-note"></div>' +
            '<ul class="fcty-tl-list" id="fcty-tl-list"></ul>' +
            '</div>' +
            '</aside>' +

            /* ── Right: form ── */
            '<section class="fcty-res-main" id="fcty-res-main">' +
            '<div class="fcty-res-top"><h4 class="fcty-res-heading" id="fcty-form-title">Plan your booking</h4>' +
            '<button type="button" class="fcty-icon-btn" id="fcty-form-close" aria-label="Close">' + icon('close') + '</button></div>' +
            '<div class="fcty-res-scroll">' +
            alertHTML('fcty-res-msg', 'error') +
            alertHTML('fcty-res-limit', 'warn') +

            '<div class="fcty-step"><div class="fcty-step-head"><span class="fcty-step-num">1</span>When</div>' +
            '<div class="fcty-sub"><span class="fcty-sublabel">Date</span>' +
            '<div class="fcty-chips" id="fcty-date-chips">' +
            '<button type="button" class="fcty-chip" aria-pressed="false" data-day="0">Today</button>' +
            '<button type="button" class="fcty-chip" aria-pressed="false" data-day="1">Tomorrow</button>' +
            '<input type="date" id="fcty-res-date" class="fcty-input" aria-label="Pick a date" min="' + todayStr + '" value="' + st.date + '">' +
            '</div></div>' +
            '<div class="fcty-row">' +
            '<div class="fcty-sub"><label class="fcty-sublabel" for="fcty-res-start">From</label>' +
            '<select id="fcty-res-start" class="fcty-input"></select></div>' +
            '<div class="fcty-sub"><label class="fcty-sublabel" for="fcty-res-end">To</label>' +
            '<select id="fcty-res-end" class="fcty-input"></select></div>' +
            '</div>' +
            '<div class="fcty-sub"><span class="fcty-sublabel">Quick duration</span>' +
            '<div class="fcty-chips" id="fcty-dur-chips">' +
            [60, 180, 300].map(function (m) {
                return '<button type="button" class="fcty-chip" aria-pressed="false" data-min="' + m + '">' + durLabel(m) + '</button>';
            }).join('') +
            '</div></div>' +
            alertHTML('fcty-res-avail', 'warn') +
            '</div>' +

            '<div class="fcty-step"><div class="fcty-step-head"><span class="fcty-step-num">2</span>Details</div>' +
            '<div class="fcty-sub"><label class="fcty-sublabel" for="fcty-res-purpose">Purpose</label>' +
            chipRow('fcty-purpose-chips', PURPOSES) +
            '<input type="text" id="fcty-res-purpose" class="fcty-input" placeholder="Or describe it in your own words\u2026" maxlength="200"></div>' +
            '<button type="button" class="fcty-link-btn" id="fcty-note-toggle">' + icon('add') + 'Add a note</button>' +
            '<textarea id="fcty-res-notes" class="fcty-input" rows="2" placeholder="Anything the admin should know?" hidden></textarea>' +
            '<div class="fcty-sub" id="fcty-doc-block" hidden>' +
            '<span class="fcty-sublabel">Supporting letter <small>(optional)</small></span>' +
            '<label class="fcty-drop" id="fcty-drop" for="fcty-res-doc">' + icon('upload_file') +
            '<span><strong>Drop a file or browse</strong><small>JPG, PNG or PDF \u00b7 up to 5 MB</small></span></label>' +
            '<input type="file" id="fcty-res-doc" class="fcty-sr-only" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">' +
            '<div class="fcty-file" id="fcty-file" hidden></div>' +
            '<p class="fcty-hint" id="fcty-doc-hint"></p>' +
            '</div>' +
            '</div>' +

            '</div>' +
            '<footer class="fcty-res-foot">' +
            '<button type="button" class="fcty-btn fcty-btn-ghost" id="fcty-res-back">' + icon('arrow_back') + 'Back</button>' +
            '<button type="button" class="fcty-btn fcty-btn-primary" id="fcty-res-submit">' + icon('event_available') + 'Confirm reservation</button>' +
            '</footer>' +
            '</section>' +
            '</div></div>';

        closeOverlay(roomOverlay, true);
        openOverlay(formOverlay, '#fcty-date-chips .fcty-chip');
        $('fcty-form-close').addEventListener('click', function () { closeOverlay(formOverlay); });

        var elDate = $('fcty-res-date'), elStart = $('fcty-res-start'), elEnd = $('fcty-res-end');
        var elMsg = $('fcty-res-msg'), elAvail = $('fcty-res-avail'), btnSubmit = $('fcty-res-submit');
        var elPurpose = $('fcty-res-purpose');
        var confirmHTML = icon('event_available') + 'Confirm reservation';

        /* ── Room limit (max 2 active reservations per account) ── */
        var quota = null;
        function isFull() { return !!quota && quota.remaining <= 0; }

        function renderQuota() {
            if (!quota) return;
            var chip = $('fcty-quota'), lim = $('fcty-res-limit'), docBlock = $('fcty-doc-block');
            if (chip) {
                chip.innerHTML = icon('meeting_room') + quota.active + ' of ' + quota.max + ' rooms reserved';
                chip.hidden = false;
            }
            if (docBlock) docBlock.hidden = !quota.is_adviser;
            if (!lim) return;

            if (!isFull()) { lim.hidden = true; btnSubmit.disabled = false; btnSubmit.title = ''; return; }

            btnSubmit.disabled = true;
            btnSubmit.title = 'Room limit reached';
            setAlert(lim, 'warn', 'warning',
                '<strong>Room limit reached</strong><br>You already have ' + quota.max +
                ' active room reservations. Wait until one ends to reserve another room.' +
                '<ul class="fcty-limit-list">' + (quota.reservations || []).map(function (r) {
                    return '<li>' + esc(r.room_name) + ' \u00b7 ' + longDate(parseYmd(r.date)) + ' \u00b7 ' +
                        minToLabel(timeToMin(r.start)) + ' \u2013 ' + minToLabel(timeToMin(r.end)) + '</li>';
                }).join('') + '</ul>');
        }

        function loadQuota() {
            return fetch(apiBase() + 'room-reservation/api/get-room-quota.php', { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (q) { if (q && !q.error) { quota = q; renderQuota(); } })
                .catch(function () { /* informational only — the server enforces the limit */ });
        }

        /* ── Optional supporting letter (Organization Advisers) ── */
        var docFile = null;
        var DOC_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];
        var DOC_MAX = 5 * 1024 * 1024;
        var DOC_HINT = 'Optional. Attached letters are kept with your request and may be used to prioritise it.';
        var elDoc = $('fcty-res-doc'), elDrop = $('fcty-drop'), elFile = $('fcty-file'), elDocHint = $('fcty-doc-hint');

        function fileKind(f) {
            var n = (f.name || '').toLowerCase();
            if (f.type) return f.type;
            if (/\.pdf$/.test(n)) return 'application/pdf';
            if (/\.png$/.test(n)) return 'image/png';
            if (/\.jpe?g$/.test(n)) return 'image/jpeg';
            return '';
        }
        function fmtSize(b) { return b < 1048576 ? Math.max(1, Math.round(b / 1024)) + ' KB' : (b / 1048576).toFixed(1) + ' MB'; }
        function docMessage(text) {
            elDocHint.textContent = text || DOC_HINT;
            elDocHint.classList.toggle('is-over', !!text);
        }
        function setDoc(f) {
            if (!f) {
                docFile = null; elDoc.value = '';
                elFile.hidden = true; elDrop.hidden = false; docMessage('');
                return;
            }
            var kind = fileKind(f);
            if (DOC_TYPES.indexOf(kind) === -1) { elDoc.value = ''; docMessage('Please choose a JPG, PNG or PDF file.'); return; }
            if (f.size > DOC_MAX) { elDoc.value = ''; docMessage('That file is ' + fmtSize(f.size) + '. The limit is 5 MB.'); return; }
            docFile = f; docMessage('');
            elFile.innerHTML = icon(kind === 'application/pdf' ? 'picture_as_pdf' : 'image') +
                '<span class="fcty-file-name">' + esc(f.name) + '</span>' +
                '<span class="fcty-file-size">' + fmtSize(f.size) + '</span>' +
                '<button type="button" class="fcty-icon-btn" id="fcty-file-remove" aria-label="Remove file">' + icon('close') + '</button>';
            elFile.hidden = false; elDrop.hidden = true;
            $('fcty-file-remove').addEventListener('click', function () { setDoc(null); });
        }
        docMessage('');
        elDoc.addEventListener('change', function () { setDoc(elDoc.files && elDoc.files[0] ? elDoc.files[0] : null); });
        ['dragenter', 'dragover'].forEach(function (ev) {
            elDrop.addEventListener(ev, function (e) { e.preventDefault(); elDrop.classList.add('is-drag'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            elDrop.addEventListener(ev, function (e) { e.preventDefault(); elDrop.classList.remove('is-drag'); });
        });
        elDrop.addEventListener('drop', function (e) {
            var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (f) setDoc(f);
        });

        /* ── Derived helpers ── */
        function dayBookings() {
            if (!weekData) return [];
            return sortByStart(weekData[DAY_KEYS[parseYmd(st.date).getDay()]] || []);
        }

        function clashes() {
            return dayBookings().filter(function (b) {
                return st.start < timeToMin(b.end) && st.end > timeToMin(b.start);
            });
        }

        function pct(min) {
            var c = Math.min(SCHOOL_END_MIN, Math.max(SCHOOL_START_MIN, min));
            return (c - SCHOOL_START_MIN) / (SCHOOL_END_MIN - SCHOOL_START_MIN) * 100;
        }

        /* ── Rendering ── */
        function syncControls() {
            /* Start / end selects (end must be after start) */
            elStart.innerHTML = timeOptions(SCHOOL_START_MIN, SCHOOL_END_MIN - 30, st.start);
            if (st.end <= st.start) st.end = Math.min(SCHOOL_END_MIN, st.start + 60);
            elEnd.innerHTML = timeOptions(st.start + 30, SCHOOL_END_MIN, st.end);

            /* Date chips */
            var dateChips = $('fcty-date-chips');
            setPressed(dateChips, function (c) { return c.dataset.day !== undefined && st.date === ymd(addDays(now, +c.dataset.day)); });

            /* Duration chips */
            var dur = st.end - st.start;
            setPressed($('fcty-dur-chips'), function (c) { return +c.dataset.min === dur; });
            $('fcty-dur-chips').querySelectorAll('.fcty-chip').forEach(function (c) {
                c.disabled = st.start + (+c.dataset.min) > SCHOOL_END_MIN;
            });

            /* Purpose chips */
            setPressed($('fcty-purpose-chips'), function (c) { return c.dataset.val === st.purpose; });

        }

        function renderSummary() {
            var d = parseYmd(st.date);
            $('fcty-tk-date').textContent = longDate(d);
            $('fcty-tk-time').textContent = minToLabel(st.start) + ' \u2013 ' + minToLabel(st.end);
            $('fcty-tk-dur').textContent = durLabel(st.end - st.start);
            $('fcty-tl-day').textContent = DAY_NAMES[d.getDay()];

            /* Timeline */
            var track = $('fcty-tl-track');
            track.querySelectorAll('.fcty-tl-block').forEach(function (n) { n.remove(); });
            var bookings = dayBookings();
            bookings.forEach(function (b) {
                var s = pct(timeToMin(b.start)), e = pct(timeToMin(b.end));
                var block = document.createElement('div');
                block.className = 'fcty-tl-block';
                block.style.left = s + '%';
                block.style.width = Math.max(0, e - s) + '%';
                block.title = timeRange(b.start, b.end) + ' \u00b7 ' + decodeHtml(b.label);
                track.insertBefore(block, $('fcty-tl-pick'));
            });

            var clash = clashes();
            var pick = $('fcty-tl-pick');
            pick.style.left = pct(st.start) + '%';
            pick.style.width = Math.max(0, pct(st.end) - pct(st.start)) + '%';
            pick.classList.toggle('is-clash', clash.length > 0);

            var noteEl = $('fcty-tl-note');
            if (!weekData) {
                noteEl.className = 'fcty-tl-note';
                noteEl.innerHTML = icon('schedule') + 'Checking the schedule\u2026';
            } else if (clash.length) {
                noteEl.className = 'fcty-tl-note is-clash';
                noteEl.innerHTML = icon('warning') + 'Overlaps ' + timeRange(clash[0].start, clash[0].end) + '. Pick another time.';
            } else {
                noteEl.className = 'fcty-tl-note';
                noteEl.innerHTML = icon('check_circle') + 'This time is free.';
            }

            var list = $('fcty-tl-list');
            list.innerHTML = bookings.slice(0, 3).map(function (b) {
                return '<li><b>' + timeRange(b.start, b.end) + '</b> \u00b7 ' + b.label + '</li>';
            }).join('') + (bookings.length > 3 ? '<li>+' + (bookings.length - 3) + ' more</li>' : '');

            /* Server-side conflict notice (only if the local check didn't already flag it) */
            if (serverClash && !clash.length) {
                setAlert(elAvail, 'warn', 'warning', 'Another reservation overlaps this time. Try a different slot.');
            } else {
                elAvail.hidden = true;
            }
        }

        function refresh() { syncControls(); renderSummary(); }

        function loadWeek() {
            weekData = null;
            renderSummary();
            var wk = ymd(mondayOf(parseYmd(st.date)));
            fetchWeek(room.room_id, wk)
                .then(function (w) {
                    if (ymd(mondayOf(parseYmd(st.date))) !== wk) return;   /* date changed meanwhile */
                    weekData = w;
                    renderSummary();
                })
                .catch(function () {
                    weekData = {};
                    renderSummary();
                });
        }

        function serverCheck() {
            if (availCtl) availCtl.abort();
            availCtl = new AbortController();
            fetch(apiBase() + 'room-reservation/api/check-room-availability.php?room_id=' + room.room_id +
                '&reservation_date=' + encodeURIComponent(st.date) +
                '&start_time=' + encodeURIComponent(minToHHMM(st.start)) +
                '&end_time=' + encodeURIComponent(minToHHMM(st.end)), {
                credentials: 'same-origin', signal: availCtl.signal
            })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
                .then(function (d) { serverClash = !!d.conflict; renderSummary(); })
                .catch(function (err) { if (!err || err.name !== 'AbortError') { serverClash = false; } });
        }

        function changed(dateChanged) {
            serverClash = false;
            elMsg.hidden = true;
            if (dateChanged) { refresh(); loadWeek(); } else refresh();
            clearTimeout(availTimer);
            availTimer = setTimeout(serverCheck, 450);
        }

        /* ── Wiring ── */
        $('fcty-date-chips').addEventListener('click', function (e) {
            var c = e.target.closest('.fcty-chip');
            if (!c) return;
            st.date = ymd(addDays(now, +c.dataset.day));
            elDate.value = st.date;
            changed(true);
        });
        elDate.addEventListener('change', function () {
            if (!elDate.value) return;
            st.date = elDate.value < todayStr ? todayStr : elDate.value;
            elDate.value = st.date;
            changed(true);
        });
        elStart.addEventListener('change', function () {
            var keep = st.end - st.start;
            st.start = timeToMin(elStart.value);
            st.end = Math.min(SCHOOL_END_MIN, st.start + Math.max(30, keep));
            changed(false);
        });
        elEnd.addEventListener('change', function () { st.end = timeToMin(elEnd.value); changed(false); });
        $('fcty-dur-chips').addEventListener('click', function (e) {
            var c = e.target.closest('.fcty-chip');
            if (!c || c.disabled) return;
            st.end = Math.min(SCHOOL_END_MIN, st.start + (+c.dataset.min));
            changed(false);
        });
        $('fcty-purpose-chips').addEventListener('click', function (e) {
            var c = e.target.closest('.fcty-chip');
            if (!c) return;
            st.purpose = c.dataset.val;
            elPurpose.value = st.purpose;
            elMsg.hidden = true;
            syncControls();
        });
        elPurpose.addEventListener('input', function () {
            st.purpose = elPurpose.value;
            setPressed($('fcty-purpose-chips'), function (c) { return c.dataset.val === st.purpose; });
        });
        $('fcty-note-toggle').addEventListener('click', function () {
            var ta = $('fcty-res-notes');
            ta.hidden = !ta.hidden;
            this.innerHTML = ta.hidden ? icon('add') + 'Add a note' : icon('remove') + 'Hide note';
            if (!ta.hidden) ta.focus();
        });
        $('fcty-res-back').addEventListener('click', backToRoom);

        btnSubmit.addEventListener('click', function () {
            function fail(t) { setAlert(elMsg, 'error', 'error', esc(t)); elMsg.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
            var purpose = elPurpose.value.trim();
            if (!purpose) return fail('Add a purpose \u2014 pick one above or type your own.');
            if (st.end <= st.start) return fail('End time must be after the start time.');

            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Submitting\u2026';

            var fd = new FormData();
            fd.append('room_id', room.room_id);
            fd.append('reservation_date', st.date);
            fd.append('start_time', minToHHMM(st.start));
            fd.append('end_time', minToHHMM(st.end));
            fd.append('purpose', purpose);
            fd.append('notes', $('fcty-res-notes').value.trim());
            fd.append('csrf_token', csrf());
            if (docFile && quota && quota.is_adviser) fd.append('document', docFile, docFile.name);

            fetch(apiBase() + 'room-reservation/api/submit-faculty-reserve.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    btnSubmit.disabled = isFull();
                    btnSubmit.innerHTML = confirmHTML;
                    if (data.error) {
                        fail(data.error);
                        if (data.limit_reached) loadQuota();   /* sync the chip + lock the button */
                        return;
                    }

                    weekCache = {};
                    document.dispatchEvent(new CustomEvent('pupsync:reservation-submitted'));

                    if (data.status === 'Declined') {
                        /* Nothing is queued or declined any more; if the server still
                           refuses a slot, just say why and refresh the week view. */
                        fail(data.reason || 'This room could not be reserved for that time.');
                        loadWeek();
                        return;
                    }
                    showDone(data);
                })
                .catch(function () {
                    btnSubmit.disabled = isFull();
                    btnSubmit.innerHTML = confirmHTML;
                    fail('Network error. Please try again.');
                });
        });

        function showDone(data) {
            /* Every reservation is accepted immediately -- no "waiting" state. */
            $('fcty-tl-pick').classList.remove('is-clash');
            $('fcty-tl-note').className = 'fcty-tl-note';
            $('fcty-tl-note').innerHTML = icon('check_circle') + 'Booked for you.';
            $('fcty-res-main').innerHTML =
                '<div class="fcty-done">' +
                '<div class="fcty-done-badge">' + icon('check_circle') + '</div>' +
                '<h4>You\u2019re all set</h4>' +
                '<p>' + esc(data.room_name || room.name) + ' \u00b7 ' + longDate(parseYmd(st.date)) + '<br>' +
                minToLabel(st.start) + ' \u2013 ' + minToLabel(st.end) + '</p>' +
                (data.reason ? '<p>' + esc(data.reason) + '</p>' : '') +
                (data.attached ? '<p class="fcty-done-note">' + icon('attach_file') + 'Supporting letter attached</p>' : '') +
                (data.max ? '<p class="fcty-done-note">' + icon('meeting_room') + data.active + ' of ' + data.max + ' rooms reserved</p>' : '') +
                '<div class="fcty-done-actions">' +
                '<button type="button" class="fcty-btn fcty-btn-primary" id="fcty-done-close">Done</button>' +
                '</div></div>';
            $('fcty-done-close').addEventListener('click', function () { closeOverlay(formOverlay); });
            $('fcty-done-close').focus();
            if (quota && data.max) { quota.active = data.active; quota.remaining = Math.max(0, data.max - data.active); renderQuota(); }
        }

        refresh();
        loadWeek();
        serverCheck();
        loadQuota();
    }

    /* ══════════════════════════════════════════════════════════
       REPORT AN ISSUE
    ══════════════════════════════════════════════════════════ */
    function openReportIssueForm(room) {
        var type = '';
        dialogShell('flag', 'Report an issue', room.name,
            '<div class="fcty-form">' +
            alertHTML('fcty-issue-msg', 'error') +
            '<div class="fcty-sub"><span class="fcty-sublabel">What kind of problem?</span>' + chipRow('fcty-issue-types', ISSUE_TYPES) + '</div>' +
            '<div class="fcty-field"><label class="fcty-label" for="fcty-issue-desc">Tell us more</label>' +
            '<textarea id="fcty-issue-desc" class="fcty-input" rows="4" maxlength="950" ' +
            'placeholder="e.g. The projector won\u2019t turn on\u2026"></textarea>' +
            '<span class="fcty-count" id="fcty-issue-count">0 / 950</span></div>' +
            '<p class="fcty-hint">The admin will review your report. The room stays open for booking until they act.</p>' +
            '</div>',
            '<button type="button" class="fcty-btn fcty-btn-ghost" id="fcty-issue-back">' + icon('arrow_back') + 'Back</button>' +
            '<button type="button" class="fcty-btn fcty-btn-primary" id="fcty-issue-submit">' + icon('send') + 'Send report</button>'
        );

        closeOverlay(roomOverlay, true);
        openOverlay(formOverlay, '#fcty-issue-types .fcty-chip');

        var msg = $('fcty-issue-msg'), submit = $('fcty-issue-submit'), ta = $('fcty-issue-desc');
        var sendHTML = icon('send') + 'Send report';

        $('fcty-issue-back').addEventListener('click', backToRoom);
        $('fcty-issue-types').addEventListener('click', function (e) {
            var c = e.target.closest('.fcty-chip');
            if (!c) return;
            type = type === c.dataset.val ? '' : c.dataset.val;   /* tap again to clear */
            setPressed($('fcty-issue-types'), function (x) { return x.dataset.val === type; });
        });
        ta.addEventListener('input', function () { $('fcty-issue-count').textContent = ta.value.length + ' / 950'; });

        submit.addEventListener('click', function () {
            var desc = ta.value.trim();
            if (desc.length < 10) {
                setAlert(msg, 'error', 'error', 'Please describe the issue in at least 10 characters.');
                return;
            }
            submit.disabled = true;
            submit.textContent = 'Sending\u2026';

            fetch(apiBase() + 'room-reservation/api/submit-room-issue.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    room_id: room.room_id,
                    description: (type ? '[' + type + '] ' : '') + desc,
                    csrf_token: csrf()
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    submit.disabled = false;
                    submit.innerHTML = sendHTML;
                    if (d.error) { setAlert(msg, 'error', 'error', esc(d.error)); return; }
                    setAlert(msg, 'success', 'check_circle', 'Report sent. The admin will review it shortly.');
                    ta.disabled = true;
                    submit.style.display = 'none';
                })
                .catch(function () {
                    submit.disabled = false;
                    submit.innerHTML = sendHTML;
                    setAlert(msg, 'error', 'error', 'Network error. Please try again.');
                });
        });
    }

    /* ══════════════════════════════════════════════════════════
       INIT
    ══════════════════════════════════════════════════════════ */
    function init() {
        buildingsView = $('fcty-buildings-view');
        if (!buildingsView) return;   /* not on the faculty dashboard */

        roomsView = $('fcty-rooms-view');
        panelsEl = $('fcty-panels');
        floorTabs = $('fcty-floor-tabs');
        roomGrid = $('fcty-rooms-floors');
        switcherEl = $('fcty-switcher');
        roomOverlay = $('fcty-room-modal');
        formOverlay = $('fcty-reservation-panel');

        /* Dialogs live on <body> so the animated tab panel can never clip or offset them */
        document.body.appendChild(roomOverlay);
        document.body.appendChild(formOverlay);

        /* Public hook used by faculty-dashboard.js on first visit to the tab */
        window.PUPSyncFacilities = { start: start };

        /* Buildings → rooms */
        panelsEl.addEventListener('click', function (e) {
            var p = e.target.closest('.fcty-panel[data-building-id]');
            if (p) showRoomsView(p.dataset.buildingId);
        });
        $('fcty-load-retry').addEventListener('click', function () {
            panelsEl.innerHTML = '<div class="fcty-panel is-skeleton"></div><div class="fcty-panel is-skeleton"></div><div class="fcty-panel is-skeleton"></div>';
            start();
        });

        /* Breadcrumb + building switcher */
        $('fcty-rooms-back-facilities').addEventListener('click', function (e) { e.preventDefault(); showBuildingsView(); });
        switcherEl.addEventListener('click', function (e) {
            var b = e.target.closest('.fcty-sw-btn');
            if (b && b.dataset.buildingId !== activeBuildingId) showRoomsView(b.dataset.buildingId);
        });

        /* Floors */
        floorTabs.addEventListener('click', function (e) {
            var tab = e.target.closest('.fcty-floor-tab');
            if (tab) renderFloor(parseInt(tab.dataset.floor, 10));
        });
        floorTabs.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
            var tabs = floorTabs.querySelectorAll('.fcty-floor-tab');
            var next = (activeFloorIdx + (e.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            renderFloor(next);
            tabs[next].focus();
        });
        $('fcty-only-free').addEventListener('change', function () {
            onlyFree = this.checked;
            renderFloor(activeFloorIdx);
        });

        /* Rooms → details */
        roomGrid.addEventListener('click', function (e) {
            var card = e.target.closest('.fcty-room');
            if (!card || card.disabled) return;
            var room = ROOM_INDEX[card.dataset.roomId];
            if (!room) return;
            var b = BUILDINGS[activeBuildingId];
            var floor = b && b.floors[activeFloorIdx];
            openRoomModal(room, b.name + (floor ? ' \u00b7 ' + floor.label : ''));
        });

        /* Room dialog */
        $('fcty-modal-close').addEventListener('click', function () { closeOverlay(roomOverlay); });
        roomOverlay.querySelectorAll('.fcty-seg-btn').forEach(function (t) {
            t.addEventListener('click', function () { setScheduleTab(this.dataset.scheduleTab); });
        });
        $('fcty-modal-reserve').addEventListener('click', function () {
            if (!currentRoom || this.disabled) return;
            openReservationForm(currentRoom.room);
        });
        $('fcty-modal-report').addEventListener('click', function () {
            if (currentRoom) openReportIssueForm(currentRoom.room);
        });

        document.addEventListener('keydown', onDocKey);

        /* Leaving the Facilities tab resets everything */
        var panel = $('panel-rooms');
        if (panel && typeof MutationObserver !== 'undefined') {
            new MutationObserver(function () {
                if (panel.classList.contains('active')) return;
                stopPolling();
                closeOverlay(roomOverlay, true);
                closeOverlay(formOverlay, true);
                activeOverlay = null;
                document.body.style.overflow = '';
                showBuildingsView();
            }).observe(panel, { attributes: true, attributeFilter: ['class'] });
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
