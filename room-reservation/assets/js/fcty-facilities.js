/* ================================================================
   PUPSYNC — FACILITIES TAB  (fcty-facilities.js)
   Handles: campus selection, building carousel, building rooms view.
   Companion to fcty-facilities.php + fcty-facilities.css.

   Data source: room-reservation/api/get-facilities.php
   CAMPUS_DATA and BUILDING_ROOMS are built from the API response
   at runtime instead of being hardcoded.
================================================================ */
(function () {
    'use strict';

    /* ══════════════════════════════════════════════════════════════
       CAMPUS + BUILDING DATA  — populated from API in loadFacilities()
    ══════════════════════════════════════════════════════════════ */
    var CAMPUS_DATA   = {};   // keyed by campus_key, e.g. 'main', 'cite'
    var BUILDING_ROOMS = {};  // keyed by building_key, e.g. 'main-building-a'

    /* ══════════════════════════════════════════════════════════════
       ROOM SCHEDULE CONSTANTS
       School operating hours used to fill "Vacant" gaps in the
       daily/weekly schedule views.
    ══════════════════════════════════════════════════════════════ */
    var SCHOOL_START_MIN = 7 * 60;   /* 7:00 AM */
    var SCHOOL_END_MIN = 20 * 60;   /* 8:00 PM */

    var DAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    var DAY_LABELS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /* ══════════════════════════════════════════════════════════════
       ROOM SCHEDULE DATA
       Keyed by the exact room name as it appears in BUILDING_ROOMS.

       capacity — seating capacity (use null until real data is set)
       image    — URL for the modal photo (null = show placeholder)
       week     — { mon:[...], tue:[...], wed:[...], thu:[...],
                     fri:[...], sat:[...], sun:[...] }
                  Each entry: { start: 'HH:MM', end: 'HH:MM', label: '...' }
                  Times are 24-hour "HH:MM", within 07:00–20:00.

       Leave a day's array empty ([]) — or omit the day/room entirely —
       and the schedule views will automatically show "Vacant" /
       "No schedule yet" placeholders, ready for real data later.
    ══════════════════════════════════════════════════════════════ */
    var ROOM_SCHEDULES = {

        'Room 203': {
            capacity: 45,
            image: null,
            week: {
                mon: [
                    { start: '08:30', end: '10:30', label: 'Sir Dennis &mdash; Data Structures' },
                    { start: '14:00', end: '17:00', label: "Ma&rsquo;am Marge &mdash; Networking" }
                ]
                /* tue, wed, thu, fri, sat, sun left blank — ready for weekly data */
            }
        }

        /* ── Add more rooms here as schedule data becomes available ──
        ,
        'Computer Laboratory 1': {
            capacity: 40,
            image:    'assets/images/comlab1.jpg',
            week: {
                mon: [ { start: '07:00', end: '09:00', label: 'Sir Reyes &mdash; CC 102' } ],
                tue: [],
                wed: [],
                thu: [],
                fri: [],
                sat: [],
                sun: []
            }
        }
        */
    };

    /* Fallback used for any room without an entry above */
    var DEFAULT_ROOM_DATA = { capacity: null, image: null, week: {} };

    /* ── Resolve a room's schedule data (with safe fallback) ──────── */
    function getRoomData(roomName) {
        var data = ROOM_SCHEDULES[roomName];
        if (!data) return DEFAULT_ROOM_DATA;
        if (!data.week) data.week = {};
        return data;
    }


    /* ══════════════════════════════════════════════════════════════
       DOM REFERENCES  (resolved in init())
    ══════════════════════════════════════════════════════════════ */

    /* VIEW 1 & 2 */
    var campusView, buildingView;
    var carouselInner, dotsWrap, prevBtn, nextBtn, carouselWrap;
    var breadcrumbBack, breadcrumbCampusLabel, buildingTitle;

    /* VIEW 3 */
    var roomsView;
    var roomsHeroTitle, roomsBreadcrumbBuilding;
    var roomsBackFacilities, roomsBackCampus;
    var roomsMetricsContainer, roomsFloorsContainer;

    /* ROOM DETAILS MODAL */
    var roomModalOverlay, roomModalClose;
    var modalLocation, modalTitle;
    var modalAvailabilityBadge, modalAvailabilityText;
    var modalCapacityValue;
    var modalDayLabel, modalDailyList, modalWeeklyGrid;
    var modalScheduleTabs, modalSchedulePanels;

    /* ══════════════════════════════════════════════════════════════
       CAROUSEL STATE
    ══════════════════════════════════════════════════════════════ */
    var currentSlide = 0;
    var totalSlides = 0;
    var autoSlideTimer = null;
    var activeCampusKey = null;
    var activeBuildingKey = null;   /* tracks which building is shown in VIEW 3 */
    var AUTO_SLIDE_MS = 5000;

    /* ── Build slide HTML string ─────────────────────────────────── */
    function buildSlideHTML(building, index) {
        return [
            '<div class="fcty-carousel-slide">',
            '<img',
            ' src="' + building.image + '"',
            ' alt="' + building.name + '"',
            ' loading="' + (index === 0 ? 'eager' : 'lazy') + '"',
            '>',
            '<div class="fcty-slide-overlay"></div>',
            '<div class="fcty-slide-content">',
            '<div class="fcty-slide-badge">',
            '<span class="material-symbols-outlined">' + building.icon + '</span>',
            '<span class="fcty-slide-badge-text">' + building.wing + '</span>',
            '</div>',
            '<h3 class="fcty-slide-title">' + building.name + '</h3>',
            '<p class="fcty-slide-desc">' + building.desc + '</p>',
            '<div class="fcty-slide-meta">',
            '<span class="fcty-meta-chip">' + building.rooms + ' Rooms</span>',
            '<span class="fcty-meta-chip">' + building.floors + ' Floors</span>',
            '</div>',
            '<button class="fcty-slide-btn" data-building-id="' + building.id + '">',
            'Select Building',
            '<span class="material-symbols-outlined">arrow_forward</span>',
            '</button>',
            '</div>',
            '</div>'
        ].join('');
    }

    /* ── Build coming-soon placeholder slide ─────────────────────── */
    function buildPlaceholderSlide(campusLabel) {
        return [
            '<div class="fcty-carousel-slide">',
            '<div class="fcty-slide-placeholder">',
            '<span class="material-symbols-outlined">construction</span>',
            '<h3>Buildings Coming Soon</h3>',
            '<p>Floor plans and room reservations for <strong>',
            campusLabel,
            '</strong> are currently being prepared. Check back soon!</p>',
            '</div>',
            '</div>'
        ].join('');
    }

    /* ══════════════════════════════════════════════════════════════
       VIEW 3 — ROOMS VIEW RENDERING
    ══════════════════════════════════════════════════════════════ */

    /* ── Single metric card HTML ─────────────────────────────────── */
    function buildMetricCardHTML(icon, value, label) {
        return [
            '<div class="fcty-metric-card">',
            '<div class="fcty-metric-icon">',
            '<span class="material-symbols-outlined">' + icon + '</span>',
            '</div>',
            '<div>',
            '<div class="fcty-metric-value">' + value + '</div>',
            '<div class="fcty-metric-label">' + label + '</div>',
            '</div>',
            '</div>'
        ].join('');
    }

    /* ── Single floor accordion HTML ────────────────────────────── */

    /* Map DB status values to CSS chip classes */
    var STATUS_CLASS_MAP = {
        'Available':    'status-available',
        'Maintenance':  'status-maintenance',
        'Not Bookable': 'status-static'
    };

    function buildFloorAccordionHTML(floor) {
        var chipsHTML = floor.rooms.map(function (room) {
            /* room is an object: { room_id, name, status, seating_capacity } */
            var statusClass = STATUS_CLASS_MAP[room.status] || 'status-available';
            return '<span class="fcty-room-chip ' + statusClass + '" data-room-id="' + room.room_id + '">' + room.name + '</span>';
        }).join('');

        var bodyClass = 'fcty-floor-body' + (floor.expanded ? ' open' : '');
        var chevronClass = 'material-symbols-outlined fcty-floor-chevron' + (floor.expanded ? ' open' : '');

        return [
            '<div class="fcty-floor-accordion" role="listitem">',
            '<button class="fcty-floor-toggle"',
            ' aria-expanded="' + (floor.expanded ? 'true' : 'false') + '"',
            ' aria-controls="fcty-floor-' + floor.label.replace(/\s+/g, '-').toLowerCase() + '">',
            '<span class="fcty-floor-label">' + floor.label + '</span>',
            '<span class="' + chevronClass + '">expand_more</span>',
            '</button>',
            '<div class="' + bodyClass + '"',
            ' id="fcty-floor-' + floor.label.replace(/\s+/g, '-').toLowerCase() + '">',
            '<div class="fcty-floor-body-inner">',
            chipsHTML,
            '</div>',
            '</div>',
            '</div>'
        ].join('');
    }

    /* ── Render metrics into the metrics container ───────────────── */
    function renderMetrics(metrics) {
        roomsMetricsContainer.innerHTML = [
            buildMetricCardHTML('meeting_room', metrics.total, 'Total Rooms'),
            buildMetricCardHTML('group', metrics.occupied, 'Occupied'),
            buildMetricCardHTML('build', metrics.maintenance, 'Maintenance')
        ].join('');
    }

    /* ── Render floor accordions ─────────────────────────────────── */
    function renderFloors(floors) {
        roomsFloorsContainer.innerHTML = floors.map(buildFloorAccordionHTML).join('');
    }

    /* ══════════════════════════════════════════════════════════════
       ROOM DETAILS MODAL
    ══════════════════════════════════════════════════════════════ */

    /* ── Time helpers ─────────────────────────────────────────────
       "HH:MM" (24h) <-> minutes since midnight <-> "h:mm AM/PM"     */
    function timeToMinutes(timeStr) {
        var parts = timeStr.split(':');
        return (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
    }

    function minutesToLabel(mins) {
        var h = Math.floor(mins / 60);
        var m = mins % 60;
        var period = h >= 12 ? 'PM' : 'AM';
        var h12 = h % 12;
        if (h12 === 0) h12 = 12;
        var mm = (m < 10 ? '0' : '') + m;
        return h12 + ':' + mm + ' ' + period;
    }

    /* ── Fill a day's schedule with "Vacant" gaps across school hours ──
       Input:  [{ start:'08:30', end:'10:30', label:'...' }, ...]
       Output: full list of slots (vacant + occupied) covering
               SCHOOL_START_MIN → SCHOOL_END_MIN, sorted by time.      */
    function buildDaySlots(daySchedule) {
        var sorted = (daySchedule || []).slice().sort(function (a, b) {
            return timeToMinutes(a.start) - timeToMinutes(b.start);
        });

        var slots = [];
        var cursor = SCHOOL_START_MIN;

        sorted.forEach(function (entry) {
            var start = timeToMinutes(entry.start);
            var end = timeToMinutes(entry.end);

            if (start > cursor) {
                slots.push({ start: cursor, end: start, vacant: true });
            }
            slots.push({ start: start, end: end, vacant: false, label: entry.label });
            cursor = Math.max(cursor, end);
        });

        if (cursor < SCHOOL_END_MIN) {
            slots.push({ start: cursor, end: SCHOOL_END_MIN, vacant: true });
        }

        /* No entries at all for this day → single all-day vacant slot */
        if (slots.length === 0) {
            slots.push({ start: SCHOOL_START_MIN, end: SCHOOL_END_MIN, vacant: true });
        }

        return slots;
    }

    /* ── Build HTML for one schedule row (daily list) ─────────────── */
    function buildScheduleSlotHTML(slot) {
        var timeLabel = minutesToLabel(slot.start) + ' &ndash; ' + minutesToLabel(slot.end);
        var rowClass = 'fcty-schedule-slot ' + (slot.vacant ? 'vacant' : 'occupied');
        var occupant = slot.vacant ? 'Vacant' : slot.label;

        return [
            '<div class="' + rowClass + '">',
            '<span class="fcty-slot-time">' + timeLabel + '</span>',
            '<span class="fcty-slot-occupant">' + occupant + '</span>',
            '</div>'
        ].join('');
    }

    /* ── Render the "Today's Schedule" list ───────────────────────── */
    function renderDailySchedule(daySchedule) {
        return buildDaySlots(daySchedule).map(buildScheduleSlotHTML).join('');
    }

    /* ── Build one day-column for the weekly grid ─────────────────── */
    function buildWeeklyDayHTML(dayLabel, daySchedule) {
        var bodyHTML;

        if (daySchedule && daySchedule.length) {
            var occupiedSlots = buildDaySlots(daySchedule).filter(function (s) {
                return !s.vacant;
            });

            if (occupiedSlots.length) {
                bodyHTML = occupiedSlots.map(function (slot) {
                    return '<div class="fcty-weekly-slot">' +
                        minutesToLabel(slot.start) + '&ndash;' + minutesToLabel(slot.end) +
                        '<br>' + slot.label +
                        '</div>';
                }).join('');
            } else {
                bodyHTML = '<div class="fcty-weekly-empty">Vacant all day</div>';
            }
        } else {
            /* No data for this day yet — placeholder, ready for future data */
            bodyHTML = '<div class="fcty-weekly-empty">No schedule yet</div>';
        }

        return [
            '<div class="fcty-weekly-day">',
            '<div class="fcty-weekly-day-header">' + dayLabel + '</div>',
            '<div class="fcty-weekly-day-body">' + bodyHTML + '</div>',
            '</div>'
        ].join('');
    }

    /* ── Render the "Weekly Schedule" grid (Sun → Sat) ────────────── */
    function renderWeeklySchedule(weekData) {
        return DAY_KEYS.map(function (key, idx) {
            return buildWeeklyDayHTML(DAY_LABELS[idx].slice(0, 3), weekData[key]);
        }).join('');
    }

    /* ── Availability: is the room free right now? ────────────────
       Compares the current time against today's occupied slots.
       Outside school hours / no entries → always Available.        */
    function isRoomAvailableNow(daySchedule) {
        var now = new Date();
        var nowMin = (now.getHours() * 60) + now.getMinutes();

        var occupiedNow = (daySchedule || []).some(function (entry) {
            return nowMin >= timeToMinutes(entry.start) && nowMin < timeToMinutes(entry.end);
        });

        return !occupiedNow;
    }

    /* ── Switch between "Today" / "Weekly" tabs ───────────────────── */
    function setScheduleTab(tabName) {
        modalScheduleTabs.forEach(function (tab) {
            var isActive = tab.dataset.scheduleTab === tabName;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        modalSchedulePanels.forEach(function (panel) {
            panel.classList.toggle('active', panel.dataset.schedulePanel === tabName);
        });
    }

    /* ── Open the modal for a given room ──────────────────────────── */
    function openRoomModal(roomName, locationLabel, roomObj) {
        var data = getRoomData(roomName);
        var today = new Date();
        var dayKey = DAY_KEYS[today.getDay()];
        var daySchedule = data.week[dayKey] || [];

        /* Header */
        modalLocation.textContent = locationLabel;
        modalTitle.textContent = roomName;

        /* Availability indicator — use DB status when schedule data is absent */
        var available = isRoomAvailableNow(daySchedule);
        if (roomObj && roomObj.status === 'Maintenance') {
            modalAvailabilityBadge.className = 'fcty-availability-badge occupied';
            modalAvailabilityText.textContent = 'Maintenance';
        } else if (roomObj && roomObj.status === 'Not Bookable') {
            modalAvailabilityBadge.className = 'fcty-availability-badge occupied';
            modalAvailabilityText.textContent = 'Not Bookable';
        } else {
            modalAvailabilityBadge.className = 'fcty-availability-badge ' + (available ? 'available' : 'occupied');
            modalAvailabilityText.textContent = available ? 'Available' : 'Occupied';
        }

        /* Capacity — prefer DB value over ROOM_SCHEDULES */
        var cap = (roomObj && roomObj.seating_capacity !== null && roomObj.seating_capacity !== undefined)
            ? roomObj.seating_capacity
            : (data.capacity !== null && data.capacity !== undefined ? data.capacity : null);
        modalCapacityValue.textContent = cap !== null ? cap : '\u2014';

        /* Daily schedule */
        modalDayLabel.textContent = 'Today \u2014 ' + DAY_LABELS[today.getDay()];
        modalDailyList.innerHTML = renderDailySchedule(daySchedule);

        /* Weekly schedule (placeholder-ready) */
        modalWeeklyGrid.innerHTML = renderWeeklySchedule(data.week);

        /* Always open on the "Today" tab */
        setScheduleTab('daily');

        /* Show modal + lock everything else out */
        roomModalOverlay.classList.add('open');
        roomModalOverlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        setupFocusTrap();
    }

    /* ── Close the modal ───────────────────────────────────────────── */
    function closeRoomModal() {
        if (!roomModalOverlay.classList.contains('open')) return;
        roomModalOverlay.classList.remove('open');
        roomModalOverlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        teardownFocusTrap();
    }

    /* ── Focus trap — keeps keyboard navigation inside the modal ───── */
    var _focusTrapHandler = null;
    var _focusTrapLastActiveEl = null; /* restore focus on close */

    var FOCUSABLE_SELECTORS = [
        'a[href]',
        'button:not([disabled])',
        'textarea:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(', ');

    function setupFocusTrap() {
        /* Remember what had focus before the modal opened */
        _focusTrapLastActiveEl = document.activeElement;

        _focusTrapHandler = function (e) {
            if (e.key !== 'Tab') return;

            var modal = roomModalOverlay.querySelector('.fcty-modal');
            var focusable = Array.prototype.slice.call(
                modal.querySelectorAll(FOCUSABLE_SELECTORS)
            ).filter(function (el) {
                return !el.closest('[hidden]') && el.offsetParent !== null;
            });

            if (!focusable.length) { e.preventDefault(); return; }

            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            var active = document.activeElement;

            if (e.shiftKey) {
                /* Shift+Tab — going backwards */
                if (active === first || !modal.contains(active)) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                /* Tab — going forwards */
                if (active === last || !modal.contains(active)) {
                    e.preventDefault();
                    first.focus();
                }
            }
        };

        document.addEventListener('keydown', _focusTrapHandler);

        /* Move initial focus to the close button */
        roomModalClose.focus();
    }

    function teardownFocusTrap() {
        if (_focusTrapHandler) {
            document.removeEventListener('keydown', _focusTrapHandler);
            _focusTrapHandler = null;
        }
        /* Return focus to wherever the user was before */
        if (_focusTrapLastActiveEl && typeof _focusTrapLastActiveEl.focus === 'function') {
            _focusTrapLastActiveEl.focus();
            _focusTrapLastActiveEl = null;
        }
    }


    /* ══════════════════════════════════════════════════════════════
       CAROUSEL RENDERING
    ══════════════════════════════════════════════════════════════ */
    function renderCarousel(campusKey) {
        var campus = CAMPUS_DATA[campusKey];
        var buildings = campus.buildings;

        /* Clear previous slides + dots */
        carouselInner.innerHTML = '';
        dotsWrap.innerHTML = '';
        currentSlide = 0;

        /* Snap to position 0 without animation */
        carouselInner.style.transition = 'none';
        carouselInner.style.transform = 'translateX(0%)';
        /* Re-enable CSS transition after the style flush */
        requestAnimationFrame(function () {
            carouselInner.style.transition = '';
        });

        if (!buildings || buildings.length === 0) {
            /* No buildings yet — placeholder slide */
            carouselInner.innerHTML = buildPlaceholderSlide(campus.label);
            dotsWrap.innerHTML = '<button class="fcty-dot active" aria-label="Slide 1" role="tab"></button>';
            totalSlides = 1;
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
            return;
        }

        /* Render each building as a slide + matching dot */
        var slidesHTML = '';
        buildings.forEach(function (building, idx) {
            slidesHTML += buildSlideHTML(building, idx);

            var dot = document.createElement('button');
            dot.className = 'fcty-dot' + (idx === 0 ? ' active' : '');
            dot.setAttribute('aria-label', 'Slide ' + (idx + 1) + ': ' + building.name);
            dot.setAttribute('role', 'tab');
            dot.dataset.idx = idx;
            dot.addEventListener('click', function () {
                goToSlide(parseInt(this.dataset.idx, 10));
                resetAutoSlide();
            });
            dotsWrap.appendChild(dot);
        });

        carouselInner.innerHTML = slidesHTML;
        totalSlides = buildings.length;

        /* Show arrows only when there is more than one slide */
        prevBtn.style.display = totalSlides > 1 ? '' : 'none';
        nextBtn.style.display = totalSlides > 1 ? '' : 'none';
    }

    /* ══════════════════════════════════════════════════════════════
       CAROUSEL NAVIGATION
    ══════════════════════════════════════════════════════════════ */
    function goToSlide(idx) {
        if (totalSlides <= 1) return;
        currentSlide = ((idx % totalSlides) + totalSlides) % totalSlides;
        carouselInner.style.transform = 'translateX(-' + (currentSlide * 100) + '%)';

        dotsWrap.querySelectorAll('.fcty-dot').forEach(function (dot, i) {
            dot.classList.toggle('active', i === currentSlide);
            dot.setAttribute('aria-selected', i === currentSlide ? 'true' : 'false');
        });
    }

    function nextSlide() { goToSlide(currentSlide + 1); }
    function prevSlide() { goToSlide(currentSlide - 1); }

    function startAutoSlide() {
        if (totalSlides <= 1) return;
        clearInterval(autoSlideTimer);
        autoSlideTimer = setInterval(nextSlide, AUTO_SLIDE_MS);
    }

    function resetAutoSlide() {
        clearInterval(autoSlideTimer);
        startAutoSlide();
    }

    function stopAutoSlide() {
        clearInterval(autoSlideTimer);
        autoSlideTimer = null;
    }

    /* ══════════════════════════════════════════════════════════════
       VIEW SWITCHING  — helpers hide all three views first
    ══════════════════════════════════════════════════════════════ */
    function hideAllViews() {
        campusView.style.display = 'none';
        buildingView.style.display = 'none';
        roomsView.style.display = 'none';
    }

    /* VIEW 1 — Campus selection */
    function showCampusView() {
        stopAutoSlide();
        hideAllViews();
        campusView.style.display = '';
        activeCampusKey = null;
    }

    /* VIEW 2 — Building carousel */
    function showBuildingView(campusKey) {
        activeCampusKey = campusKey;
        var campus = CAMPUS_DATA[campusKey];

        /* Update breadcrumb + title */
        breadcrumbCampusLabel.textContent = campus.label;
        buildingTitle.innerHTML = campus.label + ' &mdash; Select Building';

        /* Populate carousel for this campus */
        renderCarousel(campusKey);

        /* Swap views */
        hideAllViews();
        buildingView.style.display = '';

        /* Start auto-advance */
        startAutoSlide();
    }

    /* VIEW 3 — Floor + rooms view */
    function showRoomsView(buildingId, campusKey) {
        activeBuildingKey = buildingId;
        var buildingData = BUILDING_ROOMS[buildingId];

        /* If no room data exists yet, fall back gracefully */
        if (!buildingData) {
            console.warn('[PUPSync Facilities] No room data for building:', buildingId);
            return;
        }

        /* Update breadcrumbs */
        roomsBackCampus.textContent = CAMPUS_DATA[campusKey].label;
        roomsBreadcrumbBuilding.textContent = buildingData.name;
        roomsHeroTitle.textContent = buildingData.name;

        /* Populate metrics + floors */
        renderMetrics(buildingData.metrics);
        renderFloors(buildingData.floors);

        /* Swap views */
        stopAutoSlide();
        hideAllViews();
        roomsView.style.display = '';

        /* Scroll the panel back to top smoothly */
        roomsView.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /* ══════════════════════════════════════════════════════════════
       FLOOR ACCORDION TOGGLE  (event delegation on the floors list)
    ══════════════════════════════════════════════════════════════ */
    function toggleFloorAccordion(toggleBtn) {
        var body = toggleBtn.nextElementSibling;
        var chevron = toggleBtn.querySelector('.fcty-floor-chevron');
        var isOpen = body.classList.contains('open');

        body.classList.toggle('open', !isOpen);
        chevron.classList.toggle('open', !isOpen);
        toggleBtn.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
    }

    /* ══════════════════════════════════════════════════════════════
       BUILDING SELECTION CALLBACK
       Called when the user clicks "Select Building" in the carousel.
    ══════════════════════════════════════════════════════════════ */
    function onBuildingSelected(buildingId, campusKey) {
        showRoomsView(buildingId, campusKey);
    }

    /* ══════════════════════════════════════════════════════════════
       LOAD FACILITIES FROM API
       Fetches campus/building/room data from get-facilities.php and
       populates CAMPUS_DATA and BUILDING_ROOMS in the same shape the
       rendering functions already expect.
    ══════════════════════════════════════════════════════════════ */
    function loadFacilities(callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'room-reservation/api/get-facilities.php', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            if (xhr.status !== 200) {
                console.error('[PUPSync Facilities] API error ' + xhr.status);
                if (callback) callback(false);
                return;
            }
            try {
                var campuses = JSON.parse(xhr.responseText);
                campuses.forEach(function (campus) {
                    CAMPUS_DATA[campus.key] = {
                        label:     campus.label,
                        buildings: []
                    };

                    campus.buildings.forEach(function (b) {
                        /* Shape expected by buildSlideHTML */
                        CAMPUS_DATA[campus.key].buildings.push({
                            id:     b.id,
                            wing:   b.wing,
                            icon:   b.icon,
                            name:   b.name,
                            desc:   b.desc,
                            rooms:  b.rooms,
                            floors: b.floors,
                            image:  b.image
                        });

                        /* Shape expected by showRoomsView / renderMetrics / renderFloors */
                        var maintenanceCount = 0;
                        b.floor_data.forEach(function (f) {
                            f.rooms.forEach(function (r) {
                                if (r.status === 'Maintenance') maintenanceCount++;
                            });
                        });

                        BUILDING_ROOMS[b.id] = {
                            name: b.name,
                            metrics: {
                                total:       b.rooms,
                                occupied:    0,          /* Phase 2: computed from reservations */
                                maintenance: maintenanceCount
                            },
                            floors: b.floor_data   /* already { label, expanded, rooms:[{room_id,name,status,seating_capacity}] } */
                        };
                    });
                });
                if (callback) callback(true);
            } catch (e) {
                console.error('[PUPSync Facilities] JSON parse error', e);
                if (callback) callback(false);
            }
        };
        xhr.send();
    }

    /* ══════════════════════════════════════════════════════════════
       INIT
    ══════════════════════════════════════════════════════════════ */
    function init() {
        /* ── Resolve VIEW 1 & 2 DOM refs ───────────────────────── */
        campusView = document.getElementById('fcty-campus-view');
        buildingView = document.getElementById('fcty-building-view');
        carouselInner = document.getElementById('fcty-carousel-inner');
        dotsWrap = document.getElementById('fcty-carousel-dots');
        prevBtn = document.getElementById('fcty-prev');
        nextBtn = document.getElementById('fcty-next');
        carouselWrap = document.getElementById('fcty-carousel-wrap');
        breadcrumbBack = document.getElementById('fcty-breadcrumb-back');
        breadcrumbCampusLabel = document.getElementById('fcty-breadcrumb-campus');
        buildingTitle = document.getElementById('fcty-building-title');

        /* ── Resolve VIEW 3 DOM refs ────────────────────────────── */
        roomsView = document.getElementById('fcty-rooms-view');
        roomsHeroTitle = document.getElementById('fcty-rooms-hero-title');
        roomsBreadcrumbBuilding = document.getElementById('fcty-rooms-breadcrumb-building');
        roomsBackFacilities = document.getElementById('fcty-rooms-back-facilities');
        roomsBackCampus = document.getElementById('fcty-rooms-back-campus');
        roomsMetricsContainer = document.getElementById('fcty-rooms-metrics');
        roomsFloorsContainer = document.getElementById('fcty-rooms-floors');

        /* ── Resolve ROOM DETAILS MODAL DOM refs ─────────────────── */
        roomModalOverlay = document.getElementById('fcty-room-modal');
        roomModalClose = document.getElementById('fcty-modal-close');
        modalLocation = document.getElementById('fcty-modal-location');
        modalTitle = document.getElementById('fcty-modal-room-name');
        modalAvailabilityBadge = document.getElementById('fcty-modal-availability');
        modalAvailabilityText = document.getElementById('fcty-modal-availability-text');
        modalCapacityValue = document.getElementById('fcty-modal-capacity');
        modalDayLabel = document.getElementById('fcty-modal-day-label');
        modalDailyList = document.getElementById('fcty-modal-daily-list');
        modalWeeklyGrid = document.getElementById('fcty-modal-weekly-grid');
        modalScheduleTabs = roomModalOverlay.querySelectorAll('.fcty-schedule-tab');
        modalSchedulePanels = roomModalOverlay.querySelectorAll('.fcty-schedule-panel');


        /* Guard — element not found means we're on a different page */
        if (!campusView) return;

        /* ── Load live data from API, then wire campus card clicks ── */
        loadFacilities(function () {
        /* ── Campus card clicks → VIEW 2 ─────────────────────── */
        document.querySelectorAll('[data-fcty-campus]').forEach(function (card) {
            card.addEventListener('click', function (e) {
                e.preventDefault();
                showBuildingView(this.dataset.fctyCampus);
            });
        });
        }); /* end loadFacilities callback */

        /* ── Breadcrumb back (VIEW 2) → VIEW 1 ──────────────── */
        breadcrumbBack.addEventListener('click', function (e) {
            e.preventDefault();
            showCampusView();
        });

        /* ── VIEW 3 breadcrumb: "Facilities" → VIEW 1 ─────────── */
        roomsBackFacilities.addEventListener('click', function (e) {
            e.preventDefault();
            showCampusView();
        });

        /* ── VIEW 3 breadcrumb: campus label → VIEW 2 ─────────── */
        roomsBackCampus.addEventListener('click', function (e) {
            e.preventDefault();
            if (activeCampusKey) {
                showBuildingView(activeCampusKey);
            } else {
                showCampusView();
            }
        });

        /* ── Carousel arrows ─────────────────────────────────── */
        prevBtn.addEventListener('click', function () {
            prevSlide();
            resetAutoSlide();
        });
        nextBtn.addEventListener('click', function () {
            nextSlide();
            resetAutoSlide();
        });

        /* ── Keyboard nav (arrow keys when carousel is focused) ── */
        carouselWrap.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') { prevSlide(); resetAutoSlide(); }
            if (e.key === 'ArrowRight') { nextSlide(); resetAutoSlide(); }
        });

        /* ── Pause auto-slide on hover ───────────────────────── */
        carouselWrap.addEventListener('mouseenter', stopAutoSlide);
        carouselWrap.addEventListener('mouseleave', function () {
            if (activeCampusKey) startAutoSlide();
        });

        /* ── "Select Building" button clicks (event delegation) ─ */
        carouselInner.addEventListener('click', function (e) {
            var btn = e.target.closest('.fcty-slide-btn');
            if (!btn) return;
            onBuildingSelected(btn.dataset.buildingId, activeCampusKey);
        });

        /* ── Floor accordion toggles + room chip clicks (event delegation)
           Handles any click inside #fcty-rooms-floors                       */
        roomsFloorsContainer.addEventListener('click', function (e) {
            /* Room chip → open room details modal */
            var chip = e.target.closest('.fcty-room-chip');
            if (chip) {
                /* "Not Bookable" rooms aren't reservable — no modal */
                if (chip.classList.contains('status-static')) return;

                var floorEl = chip.closest('.fcty-floor-accordion');
                var floorLabel = floorEl ? floorEl.querySelector('.fcty-floor-label').textContent : '';
                var buildingName = roomsHeroTitle.textContent.trim();
                var locationLabel = buildingName + (floorLabel ? ' \u00b7 ' + floorLabel : '');

                /* Resolve room object from BUILDING_ROOMS for DB-sourced capacity/status */
                var roomObj = null;
                var roomId = parseInt(chip.dataset.roomId, 10);
                if (activeBuildingKey && BUILDING_ROOMS[activeBuildingKey]) {
                    var bData = BUILDING_ROOMS[activeBuildingKey];
                    bData.floors.forEach(function (f) {
                        f.rooms.forEach(function (r) {
                            if (r.room_id === roomId) roomObj = r;
                        });
                    });
                }

                openRoomModal(chip.textContent.trim(), locationLabel, roomObj);
                return;
            }

            /* Otherwise, toggle floor accordion */
            var toggle = e.target.closest('.fcty-floor-toggle');
            if (!toggle) return;
            toggleFloorAccordion(toggle);
        });

        /* ── Room details modal: close interactions ─────────────────── */
        /* Backdrop click intentionally does NOT close the modal.
           The user must click ✕, Reserve, or Report to proceed.  */
        roomModalClose.addEventListener('click', closeRoomModal);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && roomModalOverlay.classList.contains('open')) {
                closeRoomModal();
            }
        });

        /* ── Room details modal: schedule tab switching ─────────────── */
        modalScheduleTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                setScheduleTab(this.dataset.scheduleTab);
            });
        });

        /* ── Reserve / Report buttons — UI only, not wired up yet ────── */
        /* modalReserveBtn / modalReportBtn intentionally have no
           click handlers for now. */

        /* ── Reset to campus view when Facilities tab loses focus ── */
        var panelRooms = document.getElementById('panel-rooms');
        if (panelRooms && typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    if (m.type === 'attributes' && m.attributeName === 'class') {
                        if (!panelRooms.classList.contains('active')) {
                            /* Panel deactivated — silently reset without animation */
                            stopAutoSlide();
                            hideAllViews();
                            closeRoomModal();
                            campusView.style.display = '';
                            activeCampusKey = null;
                        }
                    }
                });
            });
            observer.observe(panelRooms, { attributes: true });
        }
    }

    /* Run after DOM is ready */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());