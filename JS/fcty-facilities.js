/* ================================================================
   PUPSYNC — FACILITIES TAB  (fcty-facilities.js)
   Handles: campus selection, building carousel, building rooms view.
   Companion to fcty-facilities.php + fcty-facilities.css.

   To add a building: push a new object into CAMPUS_DATA[key].buildings
                      and add a matching entry in BUILDING_ROOMS.
   To add a campus:   add a new key to CAMPUS_DATA and a card in the PHP.
================================================================ */
(function () {
    'use strict';

    /* ══════════════════════════════════════════════════════════════
       CAMPUS + BUILDING DATA
       Each building entry references a key in BUILDING_ROOMS below.
    ══════════════════════════════════════════════════════════════ */
    var CAMPUS_DATA = {

        main: {
            label: 'PUP MAIN',
            buildings: [
                {
                    id: 'main-building-a',
                    wing: 'South Wing',
                    icon: 'domain',
                    name: 'Building A (Old)',
                    desc: 'Administrative offices, lecture halls, organization rooms, and specialized laboratories spread across 5 floors.',
                    rooms: 22,
                    floors: 5,
                    image: 'css/images-design-faculty/pup-main-building-a-image.jpg'
                },
                {
                    id: 'main-building-b',
                    wing: 'North Wing',
                    icon: 'business',
                    name: 'Building B (New)',
                    desc: 'Modern laboratories, smart classrooms, and collaborative study spaces.',
                    rooms: 68,
                    floors: 6,
                    image: 'css/images-design-faculty/pup-main-building-b-image.jpg'
                }
                /* ── Add Building C / D / etc. below when ready ──
                ,{
                    id:     'main-building-c',
                    wing:   'East Wing',
                    icon:   'school',
                    name:   'Building C',
                    desc:   'Description here.',
                    rooms:  0,
                    floors: 0,
                    image:  'assets/images/building-c.jpg'
                }
                */
            ]
        },

        cite: {
            label: 'PUP CITE',
            buildings: [
                {
                    id: 'cite-main',
                    wing: 'Main Block',
                    icon: 'engineering',
                    name: 'PUP CITE Building',
                    desc: 'Technical laboratories, computer labs, and specialized engineering facilities spread across 4 floors.',
                    rooms: 48,
                    floors: 4,
                    image: 'https://lh3.googleusercontent.com/aida-public/AB6AXuDtmIfzajhkJcIVcLVAYPDyA499CI1o5QU8mNgrJN3N_7x6y-FDAz_IJ3WceWqelnR3N-2SPabLr_roNxvEt7_x7jCp8oqrqfasU0yRX8YhaTPzRRt2t8Xi-mjIkGsK-MPBqrdlZNZQ33UKvUCyyU8iHiD4622bQ1BeGiA6wExvcX_QXyzJvP3HoqLK8pzmTphoc-ZFxnFIHbvO0HE17vuxuO3fgVq2xBhQ6f79V8NYMAZxwmt5icsYW71bVKYSZHOXLi2FLYpcYg'
                }
                /* ── Add more CITE buildings here when ready ──
                ,{
                    id:     'cite-annex',
                    wing:   'Annex',
                    icon:   'science',
                    name:   'CITE Annex',
                    desc:   'Description here.',
                    rooms:  0,
                    floors: 0,
                    image:  'assets/images/cite-annex.jpg'
                }
                */
            ]
        }

    };

    /* ══════════════════════════════════════════════════════════════
       BUILDING ROOMS DATA
       Keyed by building id (must match CAMPUS_DATA building.id).
       metrics.total  — total room count
       metrics.occupied   — rooms currently in use  (0 = none / TBD)
       metrics.maintenance — rooms under maintenance (0 = none / TBD)
       floors[].expanded  — true = accordion open on first load
    ══════════════════════════════════════════════════════════════ */
    var BUILDING_ROOMS = {

        'cite-main': {
            name: 'PUP CITE Building',
            metrics: {
                total: 48,
                occupied: 0,
                maintenance: 0
            },
            floors: [
                {
                    label: '1st Floor',
                    expanded: true,
                    rooms: [
                        'Prayer Room',
                        'Audiovisual Room',
                        'Testing Area',
                        'Student Organization Room',
                        'Clinic Room',
                        'Industrial Engineering Room',
                        "Director's Office",
                        'Basketball Court',
                        'Room 103',
                        'Room 105',
                        'Room 106',
                        'Room 116',
                        'Room 118',
                        'Room 119'
                    ]
                },
                {
                    label: '2nd Floor',
                    expanded: false,
                    rooms: [
                        'Admin Office',
                        'Faculty Lounge',
                        'AutoCAD & Multimedia Laboratory',
                        'Computer Laboratory 1',
                        'Computer Laboratory 2',
                        'Computer Laboratory 3',
                        'Ergonomics Room',
                        'Digital Laboratory Room',
                        'Dispensing Room',
                        'Microprocessing Laboratory Room',
                        'Room 203',
                        'Room 210',
                        'Room 212',
                        'Room 218'
                    ]
                },
                {
                    label: '3rd Floor',
                    expanded: false,
                    rooms: [
                        'Library Room',
                        'Library Extension Room',
                        'Physics Room',
                        'Room 301',
                        'Room 302',
                        'Room 303',
                        'Room 304',
                        'Room 305',
                        'Room 307',
                        'Room 308',
                        'Room 309',
                        'Room 310'
                    ]
                },
                {
                    label: '4th Floor',
                    expanded: false,
                    rooms: [
                        'Chemistry Laboratory Room',
                        'Student Lounge',
                        'Room 401',
                        'Room 402',
                        'Room 403',
                        'Room 405',
                        'Room 406',
                        'Room 415'
                    ]
                }
            ]
        }

        ,

        'main-building-a': {
            name: 'Building A (Old)',
            metrics: {
                total: 22,
                occupied: 0,
                maintenance: 0
            },
            floors: [
                {
                    label: '1st Floor',
                    expanded: true,
                    rooms: [
                        'Admin Office',
                        'Registration Office',
                        'OSAS Office',
                        'Office 1',
                        'Clinic',
                        'Staff Room'
                    ]
                },
                {
                    label: '2nd Floor',
                    expanded: false,
                    rooms: [
                        'Room 201',
                        'Room 202',
                        'Room 203',
                        'Room 204',
                        'Room 205'
                    ]
                },
                {
                    label: '3rd Floor',
                    expanded: false,
                    rooms: [
                        'Room 301',
                        'Room 302',
                        'Room 303',
                        'Room 304',
                        'Room 305'
                    ]
                },
                {
                    label: '4th Floor',
                    expanded: false,
                    rooms: [
                        'Org Room',
                        'CSC Room',
                        'AVR 2',
                        'Computer Laboratory 1',
                        'Computer Laboratory 2'
                    ]
                },
                {
                    label: '5th Floor',
                    expanded: false,
                    rooms: [
                        'Chemistry Laboratory'
                    ]
                }
            ]
        }

        ,

        'main-building-b': {
            name: 'Building A (Old)',
            metrics: {
                total: 17,
                occupied: 0,
                maintenance: 0
            },
            floors: [
                {
                    label: '1st Floor',
                    expanded: true,
                    rooms: [
                        'Library',
                        'Directors Office',
                        'Faculty Room',
                        'DO Office',
                        'Guidance Office'
                    ]
                },
                {
                    label: '2nd Floor',
                    expanded: false,
                    rooms: [
                        'Research Room',
                        'Room 202',
                        'Room 203',
                        'Room 204',
                        'Room 205'
                    ]
                },
                {
                    label: '3rd Floor',
                    expanded: false,
                    rooms: [
                        'Room 301',
                        'Room 302',
                        'Room 303',
                        'Room 304',
                        'Room 305'
                    ]
                },
                {
                    label: '4th Floor',
                    expanded: false,
                    rooms: [
                        'Room 401',
                        'Room 402',
                        'AVR 1',
                        'Room 405'
                    ]
                },
            ]
        }

    };

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

    /* DEMO-ONLY status pattern — for visual reference only.
       Cycles through the five statuses from the Status Guide so every
       floor shows a mix of colors. Replace with real reservation data
       later by giving each room object in BUILDING_ROOMS a real
       `status` field and reading it here instead of the cycle. */
    var DEMO_ROOM_STATUS_CYCLE = [
        'status-available',
        'status-available',
        'status-unavailable',
        'status-pending',
        'status-maintenance',
        'status-static'
    ];

    function buildFloorAccordionHTML(floor) {
        var chipsHTML = floor.rooms.map(function (room, index) {
            var statusClass = DEMO_ROOM_STATUS_CYCLE[index % DEMO_ROOM_STATUS_CYCLE.length];
            return '<span class="fcty-room-chip ' + statusClass + '">' + room + '</span>';
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
    function openRoomModal(roomName, locationLabel) {
        var data = getRoomData(roomName);
        var today = new Date();
        var dayKey = DAY_KEYS[today.getDay()];
        var daySchedule = data.week[dayKey] || [];

        /* Header */
        modalLocation.textContent = locationLabel;
        modalTitle.textContent = roomName;

        /* Availability indicator */
        var available = isRoomAvailableNow(daySchedule);
        modalAvailabilityBadge.className = 'fcty-availability-badge ' + (available ? 'available' : 'occupied');
        modalAvailabilityText.textContent = available ? 'Available' : 'Occupied';

        /* Capacity */
        modalCapacityValue.textContent = (data.capacity !== null && data.capacity !== undefined)
            ? data.capacity
            : '\u2014'; /* em dash placeholder */

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

        /* ── Campus card clicks → VIEW 2 ─────────────────────── */
        document.querySelectorAll('[data-fcty-campus]').forEach(function (card) {
            card.addEventListener('click', function (e) {
                e.preventDefault();
                showBuildingView(this.dataset.fctyCampus);
            });
        });

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

                openRoomModal(chip.textContent.trim(), locationLabel);
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