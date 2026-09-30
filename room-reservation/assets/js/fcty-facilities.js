/* ================================================================
   PUPSYNC — FACILITIES TAB  (fcty-facilities.js)
   Browse Facilities:  Campus → Building → Rooms → Room dialog
                                                  → Reserve / Report

   Companion to fcty-facilities.php + fcty-facilities.css.
   Data:  room-reservation/api/get-facilities.php      (campus tree)
          room-reservation/api/get-room-schedule.php   (week schedule)
          room-reservation/api/poll-room-status.php    (live chip status)
          room-reservation/api/check-room-availability.php
          room-reservation/api/submit-faculty-reserve.php
          room-reservation/api/join-waitlist.php
          room-reservation/api/submit-room-issue.php
   Icons: Material Symbols Outlined only.
================================================================ */
(function () {
    'use strict';

    /* ── State ──────────────────────────────────────────────── */
    var CAMPUS_DATA = {};      /* campus_key  → { label, buildings:[…] }       */
    var BUILDINGS = {};        /* building_id → { name, campusKey, floors:[…] } */
    var ROOM_INDEX = {};       /* room_id     → room object (live-updated)      */
    var LIVE_STATUS = {};      /* room_id     → latest polled status (incl. Booked) */

    var activeCampusKey = null;
    var activeBuildingId = null;
    var activeFloorIdx = 0;
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

    /* status → card class + label */
    var STATUS_UI = {
        'Available': { cls: 'status-available', text: 'Available' },
        'Booked': { cls: 'status-booked', text: 'In use' },
        'Maintenance': { cls: 'status-maintenance', text: 'Maintenance' },
        'Not Bookable': { cls: 'status-static', text: 'Not bookable' }
    };

    /* ── DOM refs (resolved in init) ────────────────────────── */
    var $ = function (id) { return document.getElementById(id); };
    var campusView, buildingView, roomsView;
    var buildingGrid, floorTabs, roomGrid;
    var roomOverlay, formOverlay;

    /* ── Helpers ────────────────────────────────────────────── */
    function esc(str) {
        return String(str === null || str === undefined ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
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

    function timeToMin(t) {
        var p = String(t).split(':');
        return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
    }

    function minToLabel(mins) {
        var h = Math.floor(mins / 60), m = mins % 60;
        var h12 = h % 12 === 0 ? 12 : h % 12;
        return h12 + ':' + (m < 10 ? '0' : '') + m + ' ' + (h >= 12 ? 'PM' : 'AM');
    }

    function timeRange(start, end) {
        return minToLabel(timeToMin(start)) + ' \u2013 ' + minToLabel(timeToMin(end));
    }

    function sortByStart(list) {
        return (list || []).slice().sort(function (a, b) {
            return timeToMin(a.start) - timeToMin(b.start);
        });
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

    function ingest(campuses) {
        CAMPUS_DATA = {}; BUILDINGS = {}; ROOM_INDEX = {}; LIVE_STATUS = {};
        campuses.forEach(function (c) {
            CAMPUS_DATA[c.key] = { label: c.label, buildings: [] };
            c.buildings.forEach(function (b) {
                var roomCount = 0;
                b.floor_data.forEach(function (f) {
                    f.rooms.forEach(function (r) { ROOM_INDEX[r.room_id] = r; roomCount++; });
                });
                var entry = {
                    id: b.id, name: b.name, wing: b.wing, image: b.image,
                    rooms: roomCount, floors: b.floor_data, campusKey: c.key
                };
                CAMPUS_DATA[c.key].buildings.push(entry);
                BUILDINGS[b.id] = entry;
            });
        });
    }

    function start() {
        if (loaded) { startPolling(); return; }
        if (loading) return;
        loading = true;
        var err = $('fcty-load-error');
        if (err) err.hidden = true;
        loadFacilities(function (ok) {
            loading = false;
            if (!ok) { if (err) err.hidden = false; return; }
            loaded = true;
            renderCampusStats();
            startPolling();
        });
    }

    /* ══════════════════════════════════════════════════════════
       VIEW 1 — CAMPUS
    ══════════════════════════════════════════════════════════ */
    function renderCampusStats() {
        document.querySelectorAll('[data-fcty-campus-stats]').forEach(function (el) {
            var c = CAMPUS_DATA[el.dataset.fctyCampusStats];
            if (!c) return;
            var rooms = c.buildings.reduce(function (n, b) { return n + b.rooms; }, 0);
            el.innerHTML = '<span>' + plural(c.buildings.length, 'building') + '</span>' +
                '<span>' + plural(rooms, 'room') + '</span>';
        });
    }

    function hideAllViews() {
        campusView.style.display = 'none';
        buildingView.style.display = 'none';
        roomsView.style.display = 'none';
    }

    function showCampusView() {
        hideAllViews();
        campusView.style.display = '';
        activeCampusKey = null;
        activeBuildingId = null;
    }

    /* ══════════════════════════════════════════════════════════
       VIEW 2 — BUILDINGS
    ══════════════════════════════════════════════════════════ */
    function buildingCardHTML(b) {
        var media = b.image
            ? '<img class="fcty-building-media" src="' + esc(b.image) + '" alt="" loading="lazy">'
            : '<div class="fcty-building-fallback">' + icon('domain') + '</div>';
        return '<button type="button" class="fcty-building-card" data-building-id="' + esc(b.id) + '">' +
            '<div class="fcty-building-clip">' + media +
            (b.wing ? '<span class="fcty-wing">' + esc(b.wing) + '</span>' : '') +
            '</div>' +
            '<div class="fcty-building-body">' +
            '<div class="fcty-building-text">' +
            '<h3 class="fcty-building-name">' + esc(b.name) + '</h3>' +
            '<p class="fcty-meta"><span>' + plural(b.rooms, 'room') + '</span><span>' +
            plural(b.floors.length, 'floor') + '</span></p>' +
            '</div>' +
            '<span class="fcty-go" aria-hidden="true">' + icon('arrow_forward') + '</span>' +
            '</div></button>';
    }

    function showBuildingView(campusKey) {
        var campus = CAMPUS_DATA[campusKey];
        if (!campus) { start(); return; }
        activeCampusKey = campusKey;
        activeBuildingId = null;

        $('fcty-breadcrumb-campus').textContent = campus.label;
        $('fcty-building-title').textContent = 'Select a building';
        $('fcty-building-count').textContent = plural(campus.buildings.length, 'building');

        if (!campus.buildings.length) {
            buildingGrid.innerHTML = '<div class="fcty-empty">' + icon('construction') +
                '<strong>Buildings coming soon</strong>' +
                '<span>' + esc(campus.label) + ' is still being set up.</span></div>';
        } else {
            buildingGrid.innerHTML = campus.buildings.map(buildingCardHTML).join('');
            /* Fallback when a photo fails to load (inline handlers are blocked by CSP) */
            buildingGrid.querySelectorAll('img.fcty-building-media').forEach(function (img) {
                img.addEventListener('error', function () {
                    var f = document.createElement('div');
                    f.className = 'fcty-building-fallback';
                    f.innerHTML = icon('domain');
                    img.replaceWith(f);
                });
            });
        }

        hideAllViews();
        buildingView.style.display = '';
    }

    /* ══════════════════════════════════════════════════════════
       VIEW 3 — ROOMS
    ══════════════════════════════════════════════════════════ */
    function uiFor(status) { return STATUS_UI[status] || STATUS_UI.Available; }

    function displayStatus(room) { return LIVE_STATUS[room.room_id] || room.status; }

    function roomCardHTML(room) {
        var shown = displayStatus(room);
        var ui = uiFor(shown);
        var locked = shown === 'Not Bookable';
        return '<button type="button" class="fcty-room ' + ui.cls + '" data-room-id="' + room.room_id + '"' +
            (locked ? ' disabled aria-disabled="true"' : '') + '>' +
            '<span class="fcty-room-name">' + esc(room.name) + '</span>' +
            '<span class="fcty-room-status"><span class="fcty-dot"></span>' +
            '<span class="fcty-room-status-text">' + ui.text + '</span></span>' +
            '</button>';
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
        roomGrid.innerHTML = floor && floor.rooms.length
            ? floor.rooms.map(roomCardHTML).join('')
            : '<div class="fcty-empty">' + icon('meeting_room') + '<strong>No rooms on this floor</strong></div>';
    }

    function showRoomsView(buildingId) {
        var b = BUILDINGS[buildingId];
        if (!b) return;
        activeBuildingId = buildingId;
        activeCampusKey = b.campusKey;

        $('fcty-rooms-back-campus').textContent = CAMPUS_DATA[b.campusKey].label;
        $('fcty-rooms-breadcrumb-building').textContent = b.name;
        $('fcty-rooms-hero-title').textContent = b.name;
        $('fcty-rooms-summary').textContent = plural(b.rooms, 'room') + ' \u00b7 ' + plural(b.floors.length, 'floor');

        floorTabs.style.display = b.floors.length > 1 ? '' : 'none';
        floorTabs.innerHTML = b.floors.map(function (f, i) {
            return '<button type="button" class="fcty-floor-tab" role="tab" data-floor="' + i + '">' +
                '<span>' + esc(f.label) + '</span>' +
                '<span class="fcty-floor-count">' + f.rooms.length + '</span></button>';
        }).join('');

        var first = 0;
        b.floors.forEach(function (f, i) { if (f.expanded && first === 0) first = i; });
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
                roomGrid.querySelectorAll('.fcty-room[data-room-id]').forEach(function (card) {
                    var st = statuses[card.dataset.roomId];
                    if (st) applyStatus(card, st);
                });
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
        var badge = $('fcty-modal-availability');
        badge.className = 'fcty-status' + (kind ? ' is-' + kind : '');
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

    function openRoomModal(room, locationLabel) {
        currentRoom = { room: room, name: room.name, location: locationLabel };

        $('fcty-modal-room-name').textContent = room.name;
        $('fcty-modal-location').textContent = locationLabel;
        $('fcty-modal-capacity').textContent = room.seating_capacity !== null && room.seating_capacity !== undefined
            ? room.seating_capacity : '\u2014';

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

        fetch(apiBase() + 'room-reservation/api/get-room-schedule.php?room_id=' + encodeURIComponent(room.room_id), {
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!currentRoom || currentRoom.room !== room) return;   /* dialog moved on */
                if (data.error) { $('fcty-modal-daily-list').innerHTML = note('Couldn\u2019t load the schedule.'); return; }
                var todays = data.week[DAY_KEYS[today.getDay()]] || [];
                $('fcty-modal-daily-list').innerHTML = dailyHTML(todays);
                $('fcty-modal-weekly-grid').innerHTML = weeklyHTML(data.week);
                if (!blocked) {
                    if (freeNow(todays)) setStatusBadge('', 'Available');
                    else setStatusBadge('booked', 'In use now');
                }
            })
            .catch(function () {
                if (!currentRoom || currentRoom.room !== room) return;
                $('fcty-modal-daily-list').innerHTML = note('Schedule unavailable right now.');
                if (!blocked) setStatusBadge('', 'Available');
            });
    }

    /* ══════════════════════════════════════════════════════════
       RESERVE / REPORT  (shared dialog shell)
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

    /* ── Reserve ─────────────────────────────────────────────── */
    function openReservationForm(room) {
        var today = new Date().toISOString().split('T')[0];
        var availTimer = null, availCtl = null;

        dialogShell('event_available', 'Reserve room', room.name,
            '<div class="fcty-form">' +
            alertHTML('fcty-res-msg', 'error') +
            '<div class="fcty-field"><label class="fcty-label" for="fcty-res-date">Date</label>' +
            '<input type="date" id="fcty-res-date" class="fcty-input" min="' + today + '" value="' + today + '"></div>' +
            '<div class="fcty-row">' +
            '<div class="fcty-field"><label class="fcty-label" for="fcty-res-start">From</label>' +
            '<input type="time" id="fcty-res-start" class="fcty-input" min="07:00" max="20:00" value="08:00"></div>' +
            '<div class="fcty-field"><label class="fcty-label" for="fcty-res-end">To</label>' +
            '<input type="time" id="fcty-res-end" class="fcty-input" min="07:00" max="20:00" value="10:00"></div>' +
            '</div>' +
            alertHTML('fcty-res-avail', 'warn') +
            '<div class="fcty-field"><label class="fcty-label" for="fcty-res-purpose">Purpose</label>' +
            '<input type="text" id="fcty-res-purpose" class="fcty-input" placeholder="Lecture, lab session, meeting\u2026" maxlength="200"></div>' +
            '<div class="fcty-field"><label class="fcty-label" for="fcty-res-attendees">Attendees</label>' +
            '<input type="number" id="fcty-res-attendees" class="fcty-input" min="1" value="1"></div>' +
            '<div class="fcty-field"><label class="fcty-label" for="fcty-res-notes">Notes <small>(optional)</small></label>' +
            '<textarea id="fcty-res-notes" class="fcty-input" rows="2"></textarea></div>' +
            '</div>',
            '<button type="button" class="fcty-btn fcty-btn-ghost" id="fcty-res-back">' + icon('arrow_back') + 'Back</button>' +
            '<button type="button" class="fcty-btn fcty-btn-primary" id="fcty-res-submit">' + icon('event_available') + 'Confirm</button>'
        );

        closeOverlay(roomOverlay, true);
        openOverlay(formOverlay, '#fcty-res-date');

        var msg = $('fcty-res-msg'), avail = $('fcty-res-avail'), submit = $('fcty-res-submit');
        var confirmHTML = icon('event_available') + 'Confirm';

        function runAvailCheck() {
            var date = $('fcty-res-date').value, s = $('fcty-res-start').value, e = $('fcty-res-end').value;
            if (!date || !s || !e || e <= s) { avail.hidden = true; return; }
            if (availCtl) availCtl.abort();
            availCtl = new AbortController();
            setAlert(avail, 'info', 'schedule', 'Checking availability\u2026');
            fetch(apiBase() + 'room-reservation/api/check-room-availability.php?room_id=' + room.room_id +
                '&reservation_date=' + encodeURIComponent(date) +
                '&start_time=' + encodeURIComponent(s) + '&end_time=' + encodeURIComponent(e), {
                credentials: 'same-origin', signal: availCtl.signal
            })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
                .then(function (d) {
                    if (d.conflict) setAlert(avail, 'warn', 'warning', 'That time is already reserved. Try a different slot.');
                    else avail.hidden = true;
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') return;
                    setAlert(avail, 'info', 'info', 'Couldn\u2019t verify availability. Please double-check your time.');
                });
        }

        ['fcty-res-date', 'fcty-res-start', 'fcty-res-end'].forEach(function (id) {
            $(id).addEventListener('change', function () {
                clearTimeout(availTimer);
                availTimer = setTimeout(runAvailCheck, 400);
            });
        });

        $('fcty-res-back').addEventListener('click', backToRoom);

        submit.addEventListener('click', function () {
            var date = $('fcty-res-date').value.trim();
            var s = $('fcty-res-start').value.trim();
            var e = $('fcty-res-end').value.trim();
            var purpose = $('fcty-res-purpose').value.trim();
            var attendees = parseInt($('fcty-res-attendees').value || '1', 10);
            var notes = $('fcty-res-notes').value.trim();

            function fail(t) { setAlert(msg, 'error', 'error', esc(t)); }
            if (!date) return fail('Please pick a date.');
            if (!s || !e) return fail('Please set a start and end time.');
            if (e <= s) return fail('End time must be after the start time.');
            if (!purpose) return fail('Please add a purpose.');

            submit.disabled = true;
            submit.textContent = 'Submitting\u2026';

            fetch(apiBase() + 'room-reservation/api/submit-faculty-reserve.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    room_id: room.room_id, reservation_date: date, start_time: s, end_time: e,
                    purpose: purpose, attendees: attendees, notes: notes,
                    submitted_as: 'personal', csrf_token: csrf()
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    submit.disabled = false;
                    submit.innerHTML = confirmHTML;
                    if (data.error) { fail(data.error); return; }

                    var approved = data.status === 'Approved';
                    var html = '<strong>' + esc(data.status) + '</strong> \u00b7 ' + esc(data.room_name || room.name) +
                        (data.reason ? '<br>' + esc(data.reason) : '');
                    if (!approved) html += '<br><button type="button" class="fcty-btn fcty-btn-ghost" id="fcty-waitlist">' +
                        icon('notifications') + 'Notify me if it opens up</button>';
                    setAlert(msg, approved ? 'success' : 'error', approved ? 'check_circle' : 'cancel', html);

                    var wl = $('fcty-waitlist');
                    if (wl) wl.addEventListener('click', function () { joinWaitlist(wl, room, date, s, e); });

                    document.dispatchEvent(new CustomEvent('pupsync:reservation-submitted'));

                    if (approved) {
                        ['fcty-res-date', 'fcty-res-start', 'fcty-res-end', 'fcty-res-purpose',
                            'fcty-res-attendees', 'fcty-res-notes'].forEach(function (id) { $(id).disabled = true; });
                        submit.style.display = 'none';
                        avail.hidden = true;
                    }
                })
                .catch(function () {
                    submit.disabled = false;
                    submit.innerHTML = confirmHTML;
                    fail('Network error. Please try again.');
                });
        });
    }

    function joinWaitlist(btn, room, date, s, e) {
        var label = icon('notifications') + 'Notify me if it opens up';
        btn.disabled = true;
        btn.textContent = 'Joining\u2026';
        fetch(apiBase() + 'room-reservation/api/join-waitlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                room_id: room.room_id, reservation_date: date, start_time: s, end_time: e, csrf_token: csrf()
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.error) { btn.disabled = false; btn.innerHTML = label; return; }
                btn.innerHTML = icon('notifications_active') + (d.already ? 'Already on the waitlist' : 'You\u2019re on the waitlist');
            })
            .catch(function () { btn.disabled = false; btn.innerHTML = label; });
    }

    /* ── Report issue ────────────────────────────────────────── */
    function openReportIssueForm(room) {
        dialogShell('flag', 'Report an issue', room.name,
            '<div class="fcty-form">' +
            alertHTML('fcty-issue-msg', 'error') +
            '<div class="fcty-field"><label class="fcty-label" for="fcty-issue-desc">What\u2019s wrong?</label>' +
            '<textarea id="fcty-issue-desc" class="fcty-input" rows="4" maxlength="1000" ' +
            'placeholder="e.g. Air conditioning not working, broken chairs\u2026"></textarea></div>' +
            '<p class="fcty-hint">The admin will review your report. The room stays open for booking until they act.</p>' +
            '</div>',
            '<button type="button" class="fcty-btn fcty-btn-ghost" id="fcty-issue-back">' + icon('arrow_back') + 'Back</button>' +
            '<button type="button" class="fcty-btn fcty-btn-primary" id="fcty-issue-submit">' + icon('send') + 'Send report</button>'
        );

        closeOverlay(roomOverlay, true);
        openOverlay(formOverlay, '#fcty-issue-desc');

        var msg = $('fcty-issue-msg'), submit = $('fcty-issue-submit');
        var sendHTML = icon('send') + 'Send report';

        $('fcty-issue-back').addEventListener('click', backToRoom);

        submit.addEventListener('click', function () {
            var desc = $('fcty-issue-desc').value.trim();
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
                body: JSON.stringify({ room_id: room.room_id, description: desc, csrf_token: csrf() })
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    submit.disabled = false;
                    submit.innerHTML = sendHTML;
                    if (d.error) { setAlert(msg, 'error', 'error', esc(d.error)); return; }
                    setAlert(msg, 'success', 'check_circle', 'Report sent. The admin will review it shortly.');
                    $('fcty-issue-desc').disabled = true;
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
        campusView = $('fcty-campus-view');
        if (!campusView) return;   /* not on the faculty dashboard */

        buildingView = $('fcty-building-view');
        roomsView = $('fcty-rooms-view');
        buildingGrid = $('fcty-building-grid');
        floorTabs = $('fcty-floor-tabs');
        roomGrid = $('fcty-rooms-floors');
        roomOverlay = $('fcty-room-modal');
        formOverlay = $('fcty-reservation-panel');

        /* Dialogs live on <body> so the animated tab panel can never clip or offset them */
        document.body.appendChild(roomOverlay);
        document.body.appendChild(formOverlay);

        /* Public hook used by faculty-dashboard.js on first visit to the tab */
        window.PUPSyncFacilities = { start: start };

        /* Campus → buildings */
        campusView.addEventListener('click', function (e) {
            var card = e.target.closest('[data-fcty-campus]');
            if (!card) return;
            e.preventDefault();
            showBuildingView(card.dataset.fctyCampus);
        });
        $('fcty-load-retry').addEventListener('click', start);

        /* Breadcrumbs */
        $('fcty-breadcrumb-back').addEventListener('click', function (e) { e.preventDefault(); showCampusView(); });
        $('fcty-rooms-back-facilities').addEventListener('click', function (e) { e.preventDefault(); showCampusView(); });
        $('fcty-rooms-back-campus').addEventListener('click', function (e) {
            e.preventDefault();
            if (activeCampusKey) showBuildingView(activeCampusKey); else showCampusView();
        });

        /* Buildings → rooms */
        buildingGrid.addEventListener('click', function (e) {
            var card = e.target.closest('.fcty-building-card');
            if (card) showRoomsView(card.dataset.buildingId);
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
                showCampusView();
            }).observe(panel, { attributes: true, attributeFilter: ['class'] });
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
