/* ================================================================
   PUPSYNC — STUDENT PORTAL  (student-dashboard.js)

   Flow (one page, with a building/floor/room picker for equipment):
     Home ─ pick a service
       └─ Details ─ faculty code + name + student ID + course   (verify)
            ├─ Borrow, faculty code → photo grid → room picker → submit → receipt + QR
            ├─ Borrow, item code    → item, qty / time, room picker → submit → receipt + QR
            └─ Facility             → building → floor → room  → reserve → result

   API contracts are unchanged:
     equipment-booking/api/verify-faculty-code.php   verify-item-code.php
     equipment-booking/api/student-room-options.php
     equipment-booking/api/submit-student-borrow.php submit-item-borrow.php
     room-reservation/api/verify-room-code.php       submit-student-reserve.php
================================================================ */
(function () {
    'use strict';

    /* ── Constants ───────────────────────────────────────────── */
    /* Must match STUDENT_COURSES in shared/core/student-courses.php */
    var COURSES = ['BSIT', 'BSCpE', 'BSIE', 'BSBA', 'BSEEd', 'BSEd', 'BSPSY', 'DIT', 'DCET'];
    var OPEN_MIN = 7 * 60;       /* room reservations open at 7:00 AM */
    var CLOSE_MIN = 21 * 60;     /* … 9:00 PM (server enforces the same) */
    var BORROW_OPEN_MIN = 7 * 60;
    var BORROW_LAST_START_MIN = 20 * 60;
    var STEP_MIN = 60;
    var BORROW_HOURS = [1, 2, 3, 6];
    var BORROW_ADVANCE_DAYS = 14;
    var BORROW_CLOSE_MIN = 21 * 60;   /* borrowing closes (equipment only; rooms use CLOSE_MIN) */
    /* The six values above are only defaults: loadBorrowRules() replaces them with the booking
       window from Settings → Arbitration, so the pickers match what the server accepts. */
    var EQ_PAGE_SIZE = 20;
    var PURPOSES = ['Lecture', 'Lab session', 'Meeting', 'Exam', 'Seminar'];
    var QR_SRC = 'https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js';
    var RECEIPT_KEY = 'pup_last_receipt';
    var URLS = {
        verifyFaculty: 'equipment-booking/api/verify-faculty-code.php',
        verifyItem: 'equipment-booking/api/verify-item-code.php',
        verifyRoom: 'room-reservation/api/verify-room-code.php',
        studentRooms: 'equipment-booking/api/student-room-options.php',
        borrowRules: 'equipment-booking/api/borrow-rules.php',
        borrow: 'equipment-booking/api/submit-student-borrow.php',
        itemBorrow: 'equipment-booking/api/submit-item-borrow.php',
        reserve: 'room-reservation/api/submit-student-reserve.php'
    };

    /* ── Small helpers ───────────────────────────────────────── */
    function $(id) { return document.getElementById(id); }
    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function plural(n, one, many) { return n + ' ' + (n === 1 ? one : (many || one + 's')); }
    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    /* Dates are built from LOCAL parts — toISOString() is UTC and gives "yesterday" in the Philippines
       between midnight and 8 AM, which the server then rejects as a past date. */
    function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function todayStr() { return ymd(new Date()); }
    function addDays(d, n) { var x = new Date(d.getTime()); x.setDate(x.getDate() + n); return x; }
    function parseYmd(s) { var p = String(s).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function niceDate(s) {
        var d = parseYmd(s);
        return isNaN(d.getTime()) ? s : d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    }
    function minToHHMM(m) { return pad(Math.floor(m / 60)) + ':' + pad(m % 60); }
    function minToLabel(m) {
        var h = Math.floor(m / 60), mm = m % 60;
        return ((h % 12) || 12) + ':' + pad(mm) + ' ' + (h >= 12 ? 'PM' : 'AM');
    }
    function hhmmToMin(t) { var p = String(t).split(':'); return (+p[0]) * 60 + (+p[1]); }
    function nowMin() { var n = new Date(); return n.getHours() * 60 + n.getMinutes(); }
    function icon(name) { return '<span class="material-symbols-outlined" aria-hidden="true">' + name + '</span>'; }
    function toInt(v, d) { var n = parseInt(v, 10); return isNaN(n) ? (d || 0) : n; }
    function isSunday(dateStr) { return parseYmd(dateStr).getDay() === 0; }
    function borrowClockLabel(min) {
        var h = Math.floor(min / 60) % 24, m = min % 60;
        return ((h % 12) || 12) + ':' + pad(m) + ' ' + (h < 12 ? 'AM' : 'PM');
    }
    function borrowClosedMsg() {
        return 'Same-day borrowing is unavailable after ' + borrowClockLabel(BORROW_LAST_START_MIN) + '. Choose a later date.';
    }
    function borrowAheadMsg() {
        return 'Choose today or a non-Sunday date within the next ' + BORROW_ADVANCE_DAYS + ' days.';
    }
    function loadBorrowRules() {
        if (!window.fetch) return;
        fetch(URLS.borrowRules, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
            if (!j || !j.ok || !j.rules) return;
            var r = j.rules;
            BORROW_OPEN_MIN = hhmmToMin(r.dayStart);
            BORROW_LAST_START_MIN = hhmmToMin(r.lastStart);
            BORROW_CLOSE_MIN = hhmmToMin(r.dayEnd);
            if (r.durationHours && r.durationHours.length) BORROW_HOURS = r.durationHours.map(Number);
            if (r.maxAhead) BORROW_ADVANCE_DAYS = +r.maxAhead;
        }).catch(function () { /* keep the defaults */ });
    }
    function borrowDates() {
        var dates = [];
        for (var offset = 0; offset <= BORROW_ADVANCE_DAYS; offset++) {
            var date = ymd(addDays(new Date(), offset));
            if (offset > 0 && isSunday(date)) continue;
            var available = offset !== 0 || (!isSunday(date) && nowMin() <= BORROW_LAST_START_MIN);
            dates.push({
                value: date,
                available: available,
                label: offset === 0 ? 'Today · ' + niceDate(date) : niceDate(date)
            });
        }
        return dates;
    }
    function studentStartTimes(dateStr, duration) {
        var isToday = dateStr === todayStr();
        var start = isToday ? Math.max(BORROW_OPEN_MIN, Math.ceil(nowMin() / STEP_MIN) * STEP_MIN) : BORROW_OPEN_MIN;
        var times = [];
        for (var minute = start; minute <= BORROW_LAST_START_MIN && minute + duration * 60 <= BORROW_CLOSE_MIN; minute += STEP_MIN) {
            times.push(minToHHMM(minute));
        }
        return times;
    }
    function studentDateOptions() {
        var dates = borrowDates();
        var firstAvailable = dates.find(function (date) { return date.available; });
        return '<option value="" disabled' + (!firstAvailable ? ' selected' : '') + '>Choose a borrowing date</option>' +
            dates.map(function (date) {
                return '<option value="' + date.value + '"' + (date.available ? '' : ' disabled') +
                    (firstAvailable === date ? ' selected' : '') + '>' + esc(date.label) + '</option>';
            }).join('');
    }
    function studentTimeOptions(dateStr, duration, selected) {
        var times = dateStr ? studentStartTimes(dateStr, duration) : [];
        var chosen = times.indexOf(selected) !== -1 ? selected : (times[0] || '');
        var html = '<option value="" disabled' + (!chosen ? ' selected' : '') + '>Choose a start time</option>';
        return html + times.map(function (time) {
            return '<option value="' + time + '"' + (chosen === time ? ' selected' : '') + '>' + esc(minToLabel(hhmmToMin(time))) + '</option>';
        }).join('');
    }
    function studentDurationOptions(dateStr, time, selected) {
        var start = time ? hhmmToMin(time) : OPEN_MIN;
        return '<option value="" disabled' + (!selected ? ' selected' : '') + '>Choose duration</option>' +
            BORROW_HOURS.map(function (hours) {
                var disabled = !dateStr || start + hours * 60 > BORROW_CLOSE_MIN;
                return '<option value="' + hours + '"' + (selected === hours ? ' selected' : '') +
                    (disabled ? ' disabled' : '') + '>' + hours + ' ' + (hours === 1 ? 'hour' : 'hours') + '</option>';
            }).join('');
    }
    function scheduleFields(prefix) {
        return '<div class="st-row st-borrow-schedule">' +
            '<div class="st-field"><label for="' + prefix + 'Date">Borrow date <span class="st-star">*</span>' +
            ' <span class="material-symbols-outlined st-borrow-info" id="' + prefix + 'TodayInfo" title="" aria-label="" tabindex="0" hidden>error</span>' +
            '</label><select class="st-input" id="' + prefix + 'Date">' + studentDateOptions() + '</select></div>' +
            '<div class="st-field"><label for="' + prefix + 'StartTime">Start time <span class="st-star">*</span></label>' +
            '<select class="st-input" id="' + prefix + 'StartTime"></select></div>' +
            '<div class="st-field"><label for="' + prefix + 'Duration">Duration <span class="st-star">*</span></label>' +
            '<select class="st-input" id="' + prefix + 'Duration">' + studentDurationOptions(todayStr(), '', 1) + '</select></div>' +
            '</div>';
    }
    function syncStudentSchedule(prefix) {
        var date = $(prefix + 'Date');
        if (!date) return;
        var today = borrowDates()[0];
        var reason = isSunday(today.value) ? 'sunday' : nowMin() > BORROW_LAST_START_MIN ? 'closed' : '';
        var state = today.value + ':' + reason;
        if (date.getAttribute('data-schedule-state') === state) {
            refreshStudentSchedule(prefix);
            return;
        }
        var selected = date.value;
        date.innerHTML = studentDateOptions();
        var selectedDate = borrowDates().find(function (entry) { return entry.value === selected; });
        if (selectedDate && selectedDate.available) date.value = selected;
        date.setAttribute('data-schedule-state', state);
        var info = $(prefix + 'TodayInfo');
        if (info) {
            var message = reason === 'sunday'
                ? 'Same-day borrowing is unavailable on Sundays. Choose a later date.'
                : borrowClosedMsg();
            info.hidden = !reason;
            info.title = reason ? message : '';
            info.setAttribute('aria-label', reason ? message : '');
        }
        refreshStudentSchedule(prefix);
    }
    function refreshStudentSchedule(prefix) {
        var date = $(prefix + 'Date');
        var time = $(prefix + 'StartTime');
        var duration = $(prefix + 'Duration');
        if (!date || !time || !duration) return;
        var previousTime = time.value;
        var previousDuration = toInt(duration.value);
        time.innerHTML = studentTimeOptions(date.value, previousDuration || 1, previousTime);
        var selectedTime = time.value;
        duration.innerHTML = studentDurationOptions(date.value, selectedTime, previousDuration);
        if (duration.value === '' && duration.options.length > 1) {
            duration.value = duration.options[1].value;
        }
    }
    function scheduleReturn(date, time, duration) {
        var endMinutes = hhmmToMin(time) + duration * 60;
        return date + ' ' + minToHHMM(endMinutes);
    }
    function scheduleMode(date) { return date === todayStr() ? 'today' : 'future'; }

    /* Image paths come from the database as "uploads/…" or "assets/…". Anything else is ignored. */
    function safeImg(p) {
        p = String(p == null ? '' : p).trim();
        if (!p || p.charAt(0) === '/' || p.indexOf('\\') !== -1 || p.indexOf('..') !== -1 ||
            /^[a-z][a-z0-9+.\-]*:/i.test(p)) return '';
        return encodeURI(p);
    }
    /* Photo layered over a placeholder icon: if the file is missing the <img> removes itself
       (see the capture-phase error listener in init) and the placeholder shows through. */
    function photo(path, alt, ph) {
        /* An equipment item with no photo (e.g. imported from the sheet) shows the shared default picture */
        var src = safeImg(path) || ((!ph || ph === 'inventory_2') ? safeImg('uploads/default.png') : '');
        return '<span class="st-ph">' + icon(ph || 'inventory_2') + '</span>' +
            (src ? '<img src="' + esc(src) + '" alt="' + esc(alt || '') + '" loading="lazy" data-fallback="1">' : '');
    }

    function readReply(r) {
        return r.text().then(function (t) {
            try { return JSON.parse(t); }
            catch (e) { return { error: 'The server sent an unexpected reply. Please try again.' }; }
        });
    }
    function netError() {
        return { error: 'Network error. Please check your connection and try again.' };
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
            credentials: 'same-origin'
        }).then(readReply).catch(netError);
    }

    /* Same as post(), but sends the optional request letter along (multipart) when there is one. */
    function postWithLetter(url, body, file) {
        if (!file) return post(url, body);
        var fd = new FormData();
        Object.keys(body).forEach(function (k) { fd.append(k, String(body[k])); });
        fd.append('document', file, file.name);
        return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(readReply).catch(netError);
    }

    /* Request letter: optional, recommended only for a later date. Returns { file, error }. */
    var LETTER_HTML = function (id, text) {
        return '<div class="st-field" id="' + id + 'Wrap" hidden>' +
            '<label for="' + id + '">Request letter <span class="st-hint" style="display:inline">(recommended)</span></label>' +
            '<input type="file" class="st-input" id="' + id + '" accept=".pdf,.jpg,.jpeg,.png,.webp">' +
            '<button type="button" class="pslc-trigger" data-letter-camera="' + id + '"><span class="material-symbols-outlined" aria-hidden="true">photo_camera</span> Take a photo of the letter</button>' +
            '<p class="st-hint">' + text + ' PDF, JPG, PNG or WEBP, up to 5 MB.</p></div>';
    };
    function syncLetter(id, dateStr) {
        var wrap = $(id + 'Wrap'), input = $(id);
        if (!wrap) return;
        var later = !!dateStr && dateStr > todayStr();
        wrap.hidden = !later;
        if (!later && input) input.value = '';   /* a same-day request never carries a letter */
    }
    function pickLetter(id, dateStr) {
        var input = $(id);
        if (!input || !input.files || !input.files[0] || !(dateStr > todayStr())) return { file: null, error: '' };
        var f = input.files[0];
        if (!/\.(pdf|jpe?g|png|webp)$/i.test(f.name)) return { file: null, error: 'The request letter must be a PDF, JPG, PNG or WEBP file.' };
        if (f.size > 5 * 1024 * 1024) return { file: null, error: 'The request letter is too large. Maximum size is 5 MB.' };
        return { file: f, error: '' };
    }

    function showAlert(id, msg) {
        var el = $(id);
        if (!el) return;
        el.innerHTML = icon('error') + '<span></span>';
        el.lastChild.textContent = msg;
        el.hidden = false;
        if (el.scrollIntoView) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
    function hideAlert(id) { var el = $(id); if (el) el.hidden = true; }

    function setBusy(btn, busy, label) {
        if (!btn) return;
        if (busy) {
            if (btn.getAttribute('data-html') == null) btn.setAttribute('data-html', btn.innerHTML);
            btn.disabled = true;
            btn.innerHTML = '<span class="st-spin"></span>' + esc(label);
        } else {
            btn.disabled = false;
            if (btn.getAttribute('data-html') != null) {
                btn.innerHTML = btn.getAttribute('data-html');
                btn.removeAttribute('data-html');
            }
        }
    }

    /* ══════════════════════════════════════════════════════════
       STATE
    ══════════════════════════════════════════════════════════ */
    var st = fresh();

    function fresh() {
        return {
            service: null,      /* 'borrow' | 'facility'                          */
            kind: null,         /* 'faculty' | 'item' | 'room' (what the code was) */
            verified: null,     /* verify response + the student's own details     */
            vKey: '',           /* identifies the inputs that produced `verified`  */
            inv: [],            /* equipment list (faculty-code borrow)            */
            invMap: {},         /* item_name → item                                */
            cart: {},           /* item_name → quantity                            */
            eqPage: 1,          /* current equipment-grid page                       */
            done: [],           /* requests already accepted by the server          */
            where: null,        /* room / dates locked in after the first accept   */
            query: '',
            cat: '',
            fac: { buildings: [], bid: null, floor: 0, room: null },
            roomPicker: { rooms: null, buildings: [], bid: null, floor: 0, target: '' },
            receipt: null
        };
    }

    /* ══════════════════════════════════════════════════════════
       VIEWS + STEPS
    ══════════════════════════════════════════════════════════ */
    var VIEWS = ['home', 'details', 'flow'];

    function setStep(n) {
        var ol = $('steps');
        if (!ol) return;
        ol.hidden = !n;
        for (var i = 0; i < ol.children.length; i++) {
            var li = ol.children[i];
            li.classList.toggle('is-active', i === n - 1);
            li.classList.toggle('is-done', i < n - 1);
            if (i === n - 1) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
        }
    }

    function show(name, step) {
        VIEWS.forEach(function (v) { $('view-' + v).hidden = (v !== name); });
        setStep(step || 0);
        window.scrollTo(0, 0);
        var h = $('view-' + name).querySelector('h1, h2');
        if (h) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); }
    }

    function flow(html, step) {
        $('view-flow').innerHTML = html;
        show('flow', step);
    }

    function goHome() {
        st = fresh();
        $('detailsForm').reset();
        hideAlert('detailsError');
        show('home', 0);
        loadReceiptBanner();
    }

    /* ══════════════════════════════════════════════════════════
       HOME + DETAILS
    ══════════════════════════════════════════════════════════ */
    function startService(service) {
        st = fresh();
        st.service = service;
        var borrow = service === 'borrow';
        $('detailsTitle').textContent = borrow ? 'Borrow equipment' : 'Reserve a facility';
        $('detailsIcon').textContent = borrow ? 'inventory_2' : 'meeting_room';
        $('codeLabel').textContent = borrow ? 'Faculty or item code' : 'Faculty code';
        $('codeHint').textContent = borrow
            ? 'Ask your faculty for their one-time code (faculty code or item code).'
            : 'Ask your faculty for their one-time code.';
        $('stepChooseLabel').textContent = borrow ? 'Equipment' : 'Facility';
        hideAlert('detailsError');
        show('details', 1);
        var code = $('facultyCode');
        if (code) setTimeout(function () { code.focus({ preventScroll: true }); }, 60);
    }

    function onVerify(e) {
        e.preventDefault();
        hideAlert('detailsError');
        var code = $('facultyCode').value.trim();
        var name = $('studentName').value.trim();
        var id = '';
        var course = $('studentCourse').value.trim();

        if (!code) return showAlert('detailsError', 'Please enter the code.');
        if (!name) return showAlert('detailsError', 'Please enter your name.');
        if (!course) return showAlert('detailsError', 'Please select your course.');

        /* Code type: item codes are exactly 12 letters/digits (no hyphens); faculty codes are not.
           Facilities only accept a faculty code. */
        var kind = st.service === 'facility' ? 'room' : (/^[A-Za-z0-9]{12}$/.test(code) ? 'item' : 'faculty');
        var key = [kind, code, name, id, course].join('|');

        /* Coming back to this form and pressing Verify again with the same details re-uses the earlier
           result, so the faculty is not emailed a second "student is about to borrow" notice. */
        if (st.verified && st.vKey === key) { enterChoose(); return; }

        var url = kind === 'room' ? URLS.verifyRoom : (kind === 'item' ? URLS.verifyItem : URLS.verifyFaculty);
        var btn = $('btnVerifyCode');
        setBusy(btn, true, 'Verifying…');

        post(url, { code: code, student_name: name, student_id: id, student_course: course }).then(function (data) {
            setBusy(btn, false);
            if (data.error) return showAlert('detailsError', data.error);
            st.kind = kind;
            st.vKey = key;
            st.verified = {};
            Object.keys(data).forEach(function (k) { st.verified[k] = data[k]; });
            st.verified.student_name = name;
            st.verified.student_id = id;
            st.verified.student_course = course;
            enterChoose();
        });
    }

    function enterChoose() {
        if (st.kind === 'room') renderFacility();
        else if (st.kind === 'item') renderItem();
        else renderBorrow();
    }

    /* ══════════════════════════════════════════════════════════
       BORROW — faculty code → photo grid
    ══════════════════════════════════════════════════════════ */
    function renderBorrow() {
        var v = st.verified;
        st.inv = Array.isArray(v.inventory) ? v.inventory : [];
        st.invMap = {};
        st.inv.forEach(function (i) { st.invMap[i.item_name] = i; });
        st.query = '';
        st.cat = '';
        st.eqPage = 1;

        var cats = [];
        st.inv.forEach(function (i) { if (i.category && cats.indexOf(i.category) === -1) cats.push(i.category); });
        cats.sort();

        var tools = '';
        if (st.inv.length) {
            tools = '<div class="st-tools">' +
                '<label class="st-search">' + icon('search') +
                '<input type="search" id="eqSearch" placeholder="Search equipment" autocomplete="off" aria-label="Search equipment"></label>' +
                (cats.length > 1
                    ? '<div class="st-chips" id="eqCats" role="group" aria-label="Categories">' +
                    '<button type="button" class="st-chip" data-action="eq-cat" data-cat="" aria-pressed="true">All</button>' +
                    cats.map(function (c) {
                        return '<button type="button" class="st-chip" data-action="eq-cat" data-cat="' + esc(c) + '" aria-pressed="false">' + esc(c) + '</button>';
                    }).join('') + '</div>'
                    : '') +
                '</div>';
        }

        flow(
            '<div class="st-pagehead">' +
            '<button type="button" class="st-link" data-action="back-details">' + icon('arrow_back') + 'Back</button>' +
            '<h2 class="st-h2">Choose equipment</h2>' +
            '<p class="st-sub">Authorized by <strong>' + esc(v.faculty_name) + '</strong> &middot; tap <em>Add</em> on what you need.</p>' +
            '</div>' +
            tools +
            '<div class="st-eq-grid" id="eqGrid"></div>' +
            '<div class="st-pagination" id="eqPagination" hidden>' +
            '<div class="st-pagination-info" id="eqPageInfo" aria-live="polite"></div>' +
            '<nav class="st-pagination-controls" aria-label="Equipment pages">' +
            '<button type="button" class="st-pagination-btn" data-action="eq-page-prev" aria-label="Previous page">' + icon('chevron_left') + '</button>' +
            '<div class="st-pagination-numbers" id="eqPageNumbers"></div>' +
            '<button type="button" class="st-pagination-btn" data-action="eq-page-next" aria-label="Next page">' + icon('chevron_right') + '</button>' +
            '</nav></div>' +
            '<form class="st-card" id="borrowForm" novalidate>' +
            '<div class="st-card-head"><span class="st-tile" aria-hidden="true">' + icon('event') + '</span>' +
            '<div><h3 class="st-h2">Where and when</h3><p class="st-sub">Tell us where you will use it and for how long.</p></div></div>' +
            '<div class="st-alert is-error" id="flowError" role="alert" hidden></div>' +
            '<div class="st-field"><label for="borrowRoomPicker">Room / location <span class="st-star">*</span></label>' +
            '<input type="hidden" id="borrowRoomId"><input type="hidden" id="borrowRoom">' +
            '<button type="button" class="st-room-choice" id="borrowRoomPicker" data-action="open-student-room-picker" data-target="borrow">' +
            icon('location_on') + '<span id="borrowRoomLabel">Choose a building and room</span>' + icon('expand_more') + '</button></div>' +
            scheduleFields('borrow') +
            LETTER_HTML('borrowLetter', 'Recommended when borrowing on a later date. You can still submit without one.') +
            '<div class="st-alert is-note">' + icon('info') + '<span>Each item creates its own request. Your faculty will be notified.</span></div>' +
            roomPickerHTML() +
            '</form>' +
            '<div class="st-submitbar">' +
            '<div class="st-submit-sum" id="eqSummary"></div>' +
            '<button type="button" class="st-btn st-btn-primary" id="btnSubmitBorrow" data-action="submit-borrow">' + icon('send') + 'Submit request</button>' +
            '</div>', 2);

        refreshStudentSchedule('borrow');
        syncStudentSchedule('borrow');
        renderEqGrid();
        updateSummary();
    }

    function eqCardHTML(item) {
        var name = item.item_name;
        var max = toInt(item.quantity);
        var isDone = isDoneName(name);
        var qty = st.cart[name] || 0;
        var selected = isDone || qty > 0;
        var foot;
        if (isDone) {
            foot = '<span class="st-eq-done">' + icon('check_circle') + 'Submitted</span>';
        } else if (qty > 0) {
            foot = '<div class="st-stepper">' +
                '<button type="button" class="st-step-btn" data-action="eq-dec" aria-label="One less"' + (qty <= 1 ? ' disabled' : '') + '>&minus;</button>' +
                '<input type="number" class="st-step-input" data-eq-qty min="1" max="' + max + '" value="' + qty + '" inputmode="numeric" aria-label="Quantity of ' + esc(name) + '">' +
                '<button type="button" class="st-step-btn" data-action="eq-inc" aria-label="One more"' + (qty >= max ? ' disabled' : '') + '>+</button>' +
                '</div>' +
                '<button type="button" class="st-icon-btn" data-action="eq-remove" aria-label="Remove ' + esc(name) + '">' + icon('delete') + '</button>';
        } else {
            foot = '<button type="button" class="st-btn st-btn-soft st-btn-block st-btn-sm" data-action="eq-add">' + icon('add') + 'Add</button>';
        }
        return '<article class="st-eq' + (selected ? ' is-selected' : '') + '" data-name="' + esc(name) + '">' +
            '<div class="st-eq-img">' + photo(item.image_path, name, 'inventory_2') +
            '<span class="st-eq-stock">' + icon('check_circle') + max + ' available</span>' +
            '<span class="st-eq-tick">' + icon('check') + '</span></div>' +
            '<div class="st-eq-body"><h3 class="st-eq-name">' + esc(name) + '</h3>' +
            '<p class="st-eq-cat">' + esc(item.category || 'Equipment') + '</p></div>' +
            '<div class="st-eq-foot">' + foot + '</div></article>';
    }

    function renderEqGrid() {
        var grid = $('eqGrid');
        if (!grid) return;
        var q = st.query.trim().toLowerCase();
        var list = st.inv.filter(function (i) {
            if (st.cat && i.category !== st.cat) return false;
            return !q || String(i.item_name).toLowerCase().indexOf(q) !== -1 ||
                String(i.category || '').toLowerCase().indexOf(q) !== -1;
        });
        if (!list.length) {
            grid.innerHTML = '<div class="st-empty">' + icon('inventory_2') +
                '<strong>' + (st.inv.length ? 'No equipment matches your search' : 'No equipment available right now') + '</strong>' +
                '<span>' + (st.inv.length ? 'Try a different word or category.' : 'Please check with the Admin Office.') + '</span></div>';
            renderEqPagination(0, 0, 1);
            return;
        }
        var pages = Math.ceil(list.length / EQ_PAGE_SIZE);
        st.eqPage = Math.min(Math.max(st.eqPage, 1), pages);
        var start = (st.eqPage - 1) * EQ_PAGE_SIZE;
        var end = Math.min(start + EQ_PAGE_SIZE, list.length);
        grid.innerHTML = list.slice(start, end).map(eqCardHTML).join('');
        renderEqPagination(list.length, start, end);
    }

    function renderEqPagination(total, start, end) {
        var pager = $('eqPagination');
        var info = $('eqPageInfo');
        var numbers = $('eqPageNumbers');
        var previous = document.querySelector('[data-action="eq-page-prev"]');
        var next = document.querySelector('[data-action="eq-page-next"]');
        if (!pager || !numbers) return;
        var pages = Math.max(1, Math.ceil(total / EQ_PAGE_SIZE));
        pager.hidden = total <= 10;
        if (info) info.textContent = total
            ? 'Showing ' + (start + 1) + '\u2013' + end + ' of ' + total
            : 'No results';
        if (previous) previous.disabled = st.eqPage <= 1;
        if (next) next.disabled = st.eqPage >= pages;
        numbers.textContent = '';
        var pageList = [1, pages, st.eqPage - 1, st.eqPage, st.eqPage + 1]
            .filter(function (page, index, list) {
                return page >= 1 && page <= pages && list.indexOf(page) === index;
            })
            .sort(function (a, b) { return a - b; });
        var last = 0;
        pageList.forEach(function (page) {
            if (page - last > 1) {
                var gap = document.createElement('span');
                gap.className = 'st-pagination-gap';
                gap.textContent = '\u2026';
                numbers.appendChild(gap);
            }
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'st-pagination-number' + (page === st.eqPage ? ' is-active' : '');
            button.setAttribute('data-action', 'eq-page');
            button.setAttribute('data-page', String(page));
            button.setAttribute('aria-label', 'Page ' + page);
            button.textContent = String(page);
            if (page === st.eqPage) {
                button.setAttribute('aria-current', 'page');
            }
            numbers.appendChild(button);
            last = page;
        });
    }

    function findCard(name) {
        var grid = $('eqGrid');
        if (!grid) return null;
        var cards = grid.querySelectorAll('.st-eq');
        for (var i = 0; i < cards.length; i++) {
            if (cards[i].getAttribute('data-name') === name) return cards[i];
        }
        return null;
    }

    /* Re-draw ONE card (the other photos and the scroll position stay untouched) and, if asked,
       put keyboard focus back on a control inside the new card. */
    function refreshCard(name, focusSel) {
        var grid = $('eqGrid'), item = st.invMap[name], old = findCard(name);
        if (!grid || !item || !old) { updateSummary(); return; }
        var tmp = document.createElement('div');
        tmp.innerHTML = eqCardHTML(item);
        var fresh = tmp.firstChild;
        if (old.parentNode === grid) grid.replaceChild(fresh, old);
        updateSummary();
        if (focusSel) {
            var f = fresh.querySelector(focusSel);
            if (f) f.focus({ preventScroll: true });
        }
    }

    function setQty(name, qty) {
        var item = st.invMap[name];
        if (!item || isDoneName(name)) return;
        var max = toInt(item.quantity, 1);
        qty = Math.max(1, Math.min(max, toInt(qty, 1)));
        var had = !!st.cart[name];
        st.cart[name] = qty;
        var card = findCard(name);
        var input = card && card.querySelector('[data-eq-qty]');
        if (had && input) {
            /* already in the cart: adjust the stepper in place (no re-render, focus is kept) */
            input.value = qty;
            card.querySelector('[data-action="eq-dec"]').disabled = qty <= 1;
            card.querySelector('[data-action="eq-inc"]').disabled = qty >= max;
            updateSummary();
        } else {
            refreshCard(name, '[data-eq-qty]');
        }
    }

    function pendingItems() {
        var out = [];
        Object.keys(st.cart).forEach(function (n) { if (!isDoneName(n)) out.push({ name: n, qty: st.cart[n] }); });
        return out;
    }
    function isDoneName(n) { return st.done.some(function (d) { return d.equipment === n; }); }

    function updateSummary() {
        var el = $('eqSummary'), btn = $('btnSubmitBorrow');
        if (!el) return;
        var items = pendingItems();
        var units = items.reduce(function (s, i) { return s + i.qty; }, 0);
        el.innerHTML = items.length
            ? '<strong>' + plural(items.length, 'item') + ' selected</strong><span>' + plural(units, 'unit') + ' in total</span>'
            : '<strong>No items selected</strong><span>Add at least one item to continue.</span>';
        if (btn && btn.getAttribute('data-html') == null) btn.disabled = !items.length;
    }

    function lockWhere(lock) {
        ['borrowRoomId', 'borrowRoom', 'borrowRoomPicker', 'borrowDate', 'borrowStartTime', 'borrowDuration', 'borrowLetter'].forEach(function (id) { var e = $(id); if (e) e.disabled = lock; });
    }

    function submitBorrow() {
        hideAlert('flowError');
        var v = st.verified;
        if (!v) return showAlert('flowError', 'Session expired. Please go back and verify again.');

        var items = pendingItems();
        if (!items.length) return showAlert('flowError', 'Please add at least one item.');
        for (var k = 0; k < items.length; k++) {
            var it = st.invMap[items[k].name];
            var max = it ? toInt(it.quantity) : 0;
            if (!items[k].qty || items[k].qty < 1 || items[k].qty > max) {
                return showAlert('flowError', items[k].name + ': please enter a quantity between 1 and ' + max + '.');
            }
        }

        var room = st.where ? st.where.room : $('borrowRoom').value.trim();
        var roomId = st.where ? st.where.roomId : toInt($('borrowRoomId').value);
        var borrow = st.where ? st.where.borrow : $('borrowDate').value;
        var startTime = st.where ? st.where.startTime : $('borrowStartTime').value;
        var duration = st.where ? st.where.duration : toInt($('borrowDuration').value);
        var ret = st.where ? st.where.ret : scheduleReturn(borrow, startTime, duration);
        if (!roomId || !room) return showAlert('flowError', 'Please choose a building and room.');
        if (!borrow) return showAlert('flowError', 'Please select a borrow date.');
        if (!startTime || !duration) return showAlert('flowError', 'Please choose a start time and a duration.');
        if (borrow < todayStr() || !borrowDates().some(function (date) { return date.value === borrow; })) {
            return showAlert('flowError', borrowAheadMsg());
        }
        var selectedDate = borrowDates().find(function (date) { return date.value === borrow; });
        if (selectedDate && !selectedDate.available) {
            return showAlert('flowError', isSunday(borrow)
                ? 'Same-day borrowing is unavailable on Sundays. Choose a later date.'
                : borrowClosedMsg());
        }
        if (isSunday(borrow)) return showAlert('flowError', 'Equipment borrowing is unavailable on Sundays.');
        if (!studentStartTimes(borrow, duration).includes(startTime)) {
            return showAlert('flowError', 'Choose a start time that allows the selected borrowing duration.');
        }
        var letter = pickLetter('borrowLetter', borrow);
        if (letter.error) return showAlert('flowError', letter.error);

        var btn = $('btnSubmitBorrow');
        setBusy(btn, true, 'Submitting…');
        lockWhere(true);

        /* One request per item. The faculty code is only marked used when the LAST item goes through
           (is_last_item). Items the server already accepted are remembered in st.done, so a retry after
           a failure never books the same item twice. */
        var i = 0;
        (function next() {
            if (i >= items.length) return finishBorrow(room, borrow, ret);
            var cur = items[i];
            var last = (i === items.length - 1);
            postWithLetter(URLS.borrow, {
                code_db_id: v.code_db_id,
                faculty_id: v.faculty_id,
                faculty_name: v.faculty_name,
                student_name: v.student_name,
                student_id: v.student_id,
                equipment_name: cur.name,
                quantity: cur.qty,
                room_id: roomId,
                booking_mode: scheduleMode(borrow),
                borrow_date: borrow,
                start_time: startTime,
                duration: duration,
                is_last_item: last
            }, letter.file).then(function (d) {
                if (d.error) {
                    setBusy(btn, false);
                    updateSummary();
                    if (st.done.length) {
                        st.where = { room: room, roomId: roomId, borrow: borrow, startTime: startTime, duration: duration, ret: ret };
                    } else {
                        lockWhere(false);
                    }
                    pendingNamesRefresh();
                    showAlert('flowError', cur.name + ': ' + d.error +
                        (st.done.length ? ' Items already submitted are kept — fix this and submit again.' : ''));
                    return;
                }
                st.done.push({
                    equipment: cur.name, qty: cur.qty, request_id: d.request_id,
                    claim_token: d.claim_token || '', return_token: d.return_token || ''
                });
                st.where = { room: room, roomId: roomId, borrow: borrow, startTime: startTime, duration: duration, ret: ret };
                i++;
                next();
            });
        })();
    }

    function pendingNamesRefresh() {
        st.done.forEach(function (d) { refreshCard(d.equipment); });
    }

    function finishBorrow(room, borrow, ret) {
        var v = st.verified;
        var receipt = {
            kind: 'borrow',
            student_name: v.student_name, student_id: v.student_id, faculty_name: v.faculty_name,
            room: room, borrow_date: borrow, return_date: ret,
            items: st.done.slice()
        };
        st.verified = null; st.vKey = '';
        saveReceipt(receipt);
        showReceipt(receipt);
    }

    /* ══════════════════════════════════════════════════════════
       BORROW — item code (one item, chosen by the faculty)
    ══════════════════════════════════════════════════════════ */
    function renderItem() {
        var v = st.verified;
        var avail = toInt(v.quantity);
        flow(
            '<div class="st-pagehead st-narrow">' +
            '<button type="button" class="st-link" data-action="back-details">' + icon('arrow_back') + 'Back</button>' +
            '<h2 class="st-h2">Borrow equipment</h2>' +
            '<p class="st-sub">Authorized by <strong>' + esc(v.faculty_name) + '</strong></p>' +
            '</div>' +
            '<form class="st-card st-narrow" id="itemForm" novalidate>' +
            '<div class="st-item">' +
            '<div class="st-eq-img">' + photo(v.image_path, v.item_name, 'inventory_2') + '</div>' +
            '<div><p class="st-item-name">' + esc(v.item_name) + '</p>' +
            '<p class="st-item-meta">' + esc(v.category || 'Equipment') + ' &middot; ' + avail + ' available</p>' +
            '<p class="st-hint">Pre-selected by your faculty.</p></div></div>' +
            '<div class="st-alert is-error" id="flowError" role="alert" hidden></div>' +
            '<div class="st-field"><label for="itemQty">Quantity <span class="st-star">*</span></label>' +
            '<input type="number" class="st-input" id="itemQty" min="1" max="' + avail + '" value="1" inputmode="numeric">' +
            '<p class="st-hint">Maximum: ' + avail + '</p></div>' +
            scheduleFields('item') +
            '<div class="st-field"><label for="itemRoomPicker">Room / location <span class="st-star">*</span></label>' +
            '<input type="hidden" id="itemRoomId"><input type="hidden" id="itemRoom">' +
            '<button type="button" class="st-room-choice" id="itemRoomPicker" data-action="open-student-room-picker" data-target="item">' +
            icon('location_on') + '<span id="itemRoomLabel">Choose a building and room</span>' + icon('expand_more') + '</button></div>' +
            '<div class="st-actions">' +
            '<button type="button" class="st-btn st-btn-ghost" data-action="back-details">Back</button>' +
            '<button type="submit" class="st-btn st-btn-primary" id="btnSubmitItem">' + icon('send') + 'Submit request</button>' +
            '</div>' + roomPickerHTML() + '</form>', 2);
        refreshStudentSchedule('item');
        syncStudentSchedule('item');
    }

    function submitItem() {
        hideAlert('flowError');
        var v = st.verified;
        if (!v) return showAlert('flowError', 'Session expired. Please go back and verify again.');
        var avail = toInt(v.quantity);
        var qty = toInt($('itemQty').value, 0);
        var borrow = $('itemDate').value;
        var startTime = $('itemStartTime').value;
        var duration = toInt($('itemDuration').value);
        var room = $('itemRoom').value.trim();
        var roomId = toInt($('itemRoomId').value);

        if (!qty || qty < 1) return showAlert('flowError', 'Please enter a valid quantity.');
        if (qty > avail) return showAlert('flowError', 'Quantity exceeds available stock (' + avail + ').');
        if (!borrow || !startTime || !duration) return showAlert('flowError', 'Please choose a borrowing date, start time, and duration.');
        if (!borrowDates().some(function (date) { return date.value === borrow; })) {
            return showAlert('flowError', borrowAheadMsg());
        }
        var selectedDate = borrowDates().find(function (date) { return date.value === borrow; });
        if (selectedDate && !selectedDate.available) {
            return showAlert('flowError', isSunday(borrow)
                ? 'Same-day borrowing is unavailable on Sundays. Choose a later date.'
                : borrowClosedMsg());
        }
        if (!studentStartTimes(borrow, duration).includes(startTime)) {
            return showAlert('flowError', 'Choose a start time that allows the selected borrowing duration.');
        }
        if (!roomId || !room) return showAlert('flowError', 'Please choose a building and room.');
        var returnDate = scheduleReturn(borrow, startTime, duration);

        var btn = $('btnSubmitItem');
        setBusy(btn, true, 'Submitting…');
        post(URLS.itemBorrow, {
            code_db_id: v.code_db_id,
            faculty_id: v.faculty_id,
            faculty_name: v.faculty_name,
            student_name: v.student_name,
            student_id: v.student_id,
            item_name: v.item_name,
            item_id: v.item_id,
            quantity: qty,
            room_id: roomId,
            booking_mode: scheduleMode(borrow),
            borrow_date: borrow,
            start_time: startTime,
            duration: duration
        }).then(function (d) {
            if (d.error) { setBusy(btn, false); return showAlert('flowError', d.error); }
            var receipt = {
                kind: 'borrow',
                student_name: v.student_name, student_id: v.student_id, faculty_name: v.faculty_name,
                room: room, borrow_date: borrow, return_date: returnDate,
                items: [{
                    equipment: v.item_name, qty: qty, request_id: d.request_id,
                    claim_token: d.claim_token || '', return_token: d.return_token || ''
                }]
            };
            st.verified = null; st.vKey = '';
            saveReceipt(receipt);
            showReceipt(receipt);
        });
    }

    /* ══════════════════════════════════════════════════════════
       FACILITY — building → floor → room → reserve
    ══════════════════════════════════════════════════════════ */
    function buildBuildings(rooms) {
        var map = {}, list = [];
        rooms.forEach(function (r) {
            var key = r.building_key || r.building_name || 'building';
            var b = map[key];
            if (!b) {
                var name = r.building_name || 'Building';
                var m = /^(.*?)\s*\((.+)\)\s*$/.exec(name);
                var base = m ? m[1] : name;
                var lm = /Building\s+([A-Za-z])/.exec(base);
                b = map[key] = {
                    id: key, name: name, base: base, tag: m ? m[2] : '',
                    letter: ((lm ? lm[1] : base.charAt(0)) || 'B').toUpperCase(),
                    image: r.building_image || '', floors: {}, rooms: 0
                };
                list.push(b);
            }
            var fk = r.floor_number != null ? 'n' + r.floor_number : 'l' + r.floor_label;
            var f = b.floors[fk];
            if (!f) {
                f = b.floors[fk] = {
                    num: r.floor_number != null ? toInt(r.floor_number) : 999,
                    label: r.floor_label || (r.floor_number != null ? r.floor_number + 'F' : 'Floor'),
                    rooms: []
                };
            }
            f.rooms.push(r);
            b.rooms++;
        });
        list.forEach(function (b) {
            b.floorList = Object.keys(b.floors).map(function (k) { return b.floors[k]; })
                .sort(function (a, c) { return a.num - c.num; });
        });
        list.sort(function (a, c) { return a.name.localeCompare(c.name); });
        return list;
    }

    function renderFacility() {
        var v = st.verified;
        st.fac = { buildings: buildBuildings(Array.isArray(v.rooms) ? v.rooms : []), bid: null, floor: 0, room: null };
        if (st.fac.buildings.length === 1) { openBuilding(st.fac.buildings[0].id); return; }
        renderBuildings();
    }

    function renderBuildings() {
        var v = st.verified;
        var bs = st.fac.buildings;
        var total = bs.reduce(function (s, b) { return s + b.rooms; }, 0);
        var body;
        if (!bs.length) {
            body = '<div class="st-empty">' + icon('construction') +
                '<strong>No rooms are open for booking</strong><span>Please check with the Admin Office.</span></div>';
        } else {
            body = '<div class="st-panels">' + bs.map(function (b) {
                var src = safeImg(b.image);
                return '<button type="button" class="st-panel" data-action="building" data-id="' + esc(b.id) + '"' +
                    ' aria-label="' + esc(b.name) + ', ' + plural(b.rooms, 'room') + ' available">' +
                    (src ? '<img class="st-panel-img" src="' + esc(src) + '" alt="" data-fallback="panel">' : '') +
                    '<span class="st-panel-shade"></span>' +
                    '<span class="st-panel-letter" aria-hidden="true">' + esc(b.letter) + '</span>' +
                    '<span class="st-panel-info"><span class="st-panel-name">' + esc(b.base) +
                    (b.tag ? '<em class="st-tag">' + esc(b.tag) + '</em>' : '') + '</span>' +
                    '<span class="st-panel-meta">' + plural(b.rooms, 'room') + ' available &middot; ' + plural(b.floorList.length, 'floor') + '</span></span>' +
                    '<span class="st-panel-go" aria-hidden="true">' + icon('arrow_forward') + '</span></button>';
            }).join('') + '</div>';
        }
        flow(
            '<div class="st-pagehead"><button type="button" class="st-link" data-action="back-details">' + icon('arrow_back') + 'Back</button>' +
            '<div class="st-headrow"><div><h2 class="st-h2">Choose a building</h2>' +
            '<p class="st-sub">Authorized by <strong>' + esc(v.faculty_name) + '</strong></p></div>' +
            (total ? '<span class="st-pill"><span class="st-dot"></span>' + plural(total, 'room') + ' available</span>' : '') +
            '</div></div>' + body, 2);
    }

    function currentBuilding() {
        var bs = st.fac.buildings;
        for (var i = 0; i < bs.length; i++) if (bs[i].id === st.fac.bid) return bs[i];
        return null;
    }

    function openBuilding(id) {
        st.fac.bid = id;
        var b = currentBuilding();
        if (!b) return;
        /* open on the first floor that has rooms */
        st.fac.floor = 0;
        renderRooms();
    }

    function renderRooms() {
        var b = currentBuilding();
        if (!b) return renderBuildings();
        var floor = b.floorList[st.fac.floor] || b.floorList[0];
        var src = safeImg(b.image);
        var single = st.fac.buildings.length === 1;

        var tabs = b.floorList.length > 1
            ? '<div class="st-floors" role="tablist" aria-label="Floors">' + b.floorList.map(function (f, i) {
                return '<button type="button" class="st-floor" role="tab" data-action="floor" data-i="' + i + '" aria-selected="' + (i === st.fac.floor ? 'true' : 'false') + '">' +
                    '<span>' + esc(f.label) + '</span></button>';
            }).join('') + '</div>'
            : '';

        var tiles = floor && floor.rooms.length
            ? '<div class="st-rooms" role="tabpanel" aria-label="Rooms on this floor">' + floor.rooms.map(function (r) {
                var seats = r.seating_capacity != null && toInt(r.seating_capacity) > 0 ? ' &middot; ' + plural(toInt(r.seating_capacity), 'seat') : '';
                return '<button type="button" class="st-room" data-action="room" data-id="' + toInt(r.room_id) + '">' +
                    '<span class="st-room-name">' + esc(r.room_name) + '</span>' +
                    '<span class="st-room-meta"><span class="st-dot"></span>Available' + seats + '</span></button>';
            }).join('') + '</div>'
            : '<div class="st-empty">' + icon('meeting_room') + '<strong>No rooms on this floor</strong></div>';

        flow(
            '<nav class="st-crumbs" aria-label="Breadcrumb">' +
            (single
                ? '<button type="button" data-action="back-details">' + icon('arrow_back') + 'Back</button>'
                : '<button type="button" data-action="back-buildings">Buildings</button>' + icon('chevron_right')) +
            '<span aria-current="page">' + esc(b.name) + '</span></nav>' +
            '<header class="st-hero-b">' +
            (src ? '<img class="st-panel-img" src="' + esc(src) + '" alt="" data-fallback="panel">' : '') +
            '<span class="st-panel-shade"></span>' +
            '<div class="st-hero-b-text"><h2>' + esc(b.base) + (b.tag ? '<em class="st-tag">' + esc(b.tag) + '</em>' : '') + '</h2>' +
            '<p>' + plural(b.rooms, 'room') + ' available &middot; ' + plural(b.floorList.length, 'floor') + '</p></div></header>' +
            tabs + tiles, 2);
    }

    function findRoom(id) {
        var b = currentBuilding();
        if (!b) return null;
        for (var f = 0; f < b.floorList.length; f++) {
            for (var r = 0; r < b.floorList[f].rooms.length; r++) {
                if (toInt(b.floorList[f].rooms[r].room_id) === id) return { room: b.floorList[f].rooms[r], floor: b.floorList[f] };
            }
        }
        return null;
    }

    function roomPickerHTML() {
        return '<div class="st-room-picker" id="studentRoomPicker" role="dialog" aria-modal="true" aria-labelledby="studentRoomPickerTitle" hidden>' +
            '<div class="st-room-picker-card">' +
            '<div class="st-room-picker-head"><div><h2 id="studentRoomPickerTitle">Choose a room</h2>' +
            '<p class="st-sub">Select a building, floor, and room.</p></div>' +
            '<button type="button" class="st-icon-btn" data-action="close-student-room-picker" aria-label="Close room selector">' + icon('close') + '</button></div>' +
            '<div class="st-room-picker-content" id="studentRoomPickerContent" aria-live="polite"></div>' +
            '</div></div>';
    }

    function openStudentRoomPicker(target) {
        if (target !== 'borrow' && target !== 'item') return;
        st.roomPicker.target = target;
        st.roomPicker.bid = null;
        st.roomPicker.floor = 0;
        st.roomPicker.rooms = null;
        var modal = $('studentRoomPicker');
        if (!modal) return;
        modal.hidden = false;
        document.body.classList.add('st-room-picker-open');
        modal.querySelector('[data-action="close-student-room-picker"]').focus();
        $('studentRoomPickerContent').innerHTML = '<div class="st-room-picker-status"><span class="st-spin"></span><span>Loading available rooms…</span></div>';
        fetch(URLS.studentRooms, { credentials: 'same-origin' })
            .then(readReply)
            .then(function (data) {
                if (data.error) throw new Error(data.error);
                st.roomPicker.rooms = Array.isArray(data.rooms) ? data.rooms : [];
                st.roomPicker.buildings = buildBuildings(st.roomPicker.rooms);
                renderStudentRoomPicker();
            })
            .catch(function (error) {
                $('studentRoomPickerContent').innerHTML =
                    '<div class="st-room-picker-status is-error"><span>' + esc(error.message || 'Could not load available rooms.') + '</span>' +
                    '<button type="button" class="st-btn st-btn-soft st-btn-sm" data-action="retry-student-room-picker">' + icon('refresh') + 'Try again</button></div>';
            });
    }

    function renderStudentRoomPicker() {
        var content = $('studentRoomPickerContent');
        if (!content) return;
        var bs = st.roomPicker.buildings;
        if (!bs.length) {
            content.innerHTML = '<div class="st-empty">' + icon('meeting_room') +
                '<strong>No rooms are currently available</strong><span>Please check with the Admin Office.</span></div>';
            return;
        }
        var building = null;
        for (var i = 0; i < bs.length; i++) {
            if (bs[i].id === st.roomPicker.bid) { building = bs[i]; break; }
        }
        if (!building) {
            content.innerHTML = '<p class="st-room-picker-label">Choose a building</p><div class="st-panels">' + bs.map(function (b) {
                var src = safeImg(b.image);
                return '<button type="button" class="st-panel" data-action="student-room-building" data-id="' + esc(b.id) + '">' +
                    (src ? '<img class="st-panel-img" src="' + esc(src) + '" alt="" data-fallback="panel">' : '') +
                    '<span class="st-panel-shade"></span><span class="st-panel-letter" aria-hidden="true">' + esc(b.letter) + '</span>' +
                    '<span class="st-panel-info"><span class="st-panel-name">' + esc(b.base) +
                    (b.tag ? '<em class="st-tag">' + esc(b.tag) + '</em>' : '') + '</span>' +
                    '<span class="st-panel-meta">' + plural(b.rooms, 'room') + ' available · ' + plural(b.floorList.length, 'floor') + '</span></span>' +
                    '<span class="st-panel-go" aria-hidden="true">' + icon('arrow_forward') + '</span></button>';
            }).join('') + '</div>';
            return;
        }

        var floor = building.floorList[st.roomPicker.floor] || building.floorList[0];
        var tabs = building.floorList.length > 1
            ? '<div class="st-floors" role="tablist" aria-label="Floors">' + building.floorList.map(function (f, index) {
                return '<button type="button" class="st-floor" role="tab" data-action="student-room-floor" data-i="' + index +
                    '" aria-selected="' + (index === st.roomPicker.floor ? 'true' : 'false') + '">' +
                    '<span>' + esc(f.label) + '</span></button>';
            }).join('') + '</div>'
            : '';
        content.innerHTML = '<button type="button" class="st-link st-room-picker-back" data-action="student-room-back">' +
            icon('arrow_back') + 'Buildings</button><h3 class="st-room-picker-label">' + esc(building.name) + '</h3>' +
            tabs + '<div class="st-rooms" role="tabpanel" aria-label="Rooms on this floor">' +
            (floor ? floor.rooms.map(function (room) {
                var seats = room.seating_capacity != null && toInt(room.seating_capacity) > 0
                    ? ' · ' + plural(toInt(room.seating_capacity), 'seat') : '';
                return '<button type="button" class="st-room" data-action="student-room-select" data-id="' + toInt(room.room_id) + '">' +
                    '<span class="st-room-name">' + esc(room.room_name) + '</span>' +
                    '<span class="st-room-meta"><span class="st-dot"></span>Available' + seats + '</span></button>';
            }).join('') : '') + '</div>';
    }

    function selectStudentRoom(roomId) {
        var room = null;
        for (var i = 0; i < st.roomPicker.rooms.length; i++) {
            if (toInt(st.roomPicker.rooms[i].room_id) === roomId) { room = st.roomPicker.rooms[i]; break; }
        }
        if (!room) return;
        var prefix = st.roomPicker.target === 'borrow' ? 'borrow' : 'item';
        $(prefix + 'RoomId').value = String(room.room_id);
        $(prefix + 'Room').value = room.room_name;
        $(prefix + 'RoomPicker').querySelector('span:nth-child(2)').textContent =
            room.building_name + ' · ' + room.room_name;
        closeStudentRoomPicker();
    }

    function closeStudentRoomPicker() {
        var modal = $('studentRoomPicker');
        if (modal) modal.hidden = true;
        document.body.classList.remove('st-room-picker-open');
        var target = st.roomPicker.target === 'borrow' ? 'borrow' : 'item';
        var trigger = $(target + 'RoomPicker');
        if (trigger) trigger.focus();
    }

    function startOptions(dateStr) {
        var isToday = dateStr === todayStr();
        var cut = nowMin();
        var html = '<option value="" selected disabled>Select</option>';
        for (var m = OPEN_MIN; m <= CLOSE_MIN - STEP_MIN; m += STEP_MIN) {
            html += '<option value="' + minToHHMM(m) + '"' + (isToday && m <= cut ? ' disabled' : '') + '>' + minToLabel(m) + '</option>';
        }
        return html;
    }
    function endOptions(startVal, selected) {
        var s = startVal ? hhmmToMin(startVal) : null;
        var html = '<option value=""' + (selected ? '' : ' selected') + ' disabled>Select</option>';
        if (s == null) return html;
        for (var m = s + STEP_MIN; m <= CLOSE_MIN; m += STEP_MIN) {
            html += '<option value="' + minToHHMM(m) + '"' + (minToHHMM(m) === selected ? ' selected' : '') + '>' + minToLabel(m) + '</option>';
        }
        return html;
    }

    function renderReserve(roomId) {
        var hit = findRoom(roomId);
        if (!hit) return renderRooms();
        var b = currentBuilding();
        st.fac.room = hit.room;
        var r = hit.room;
        var t0 = todayStr();
        var seats = r.seating_capacity != null && toInt(r.seating_capacity) > 0 ? ' &middot; ' + plural(toInt(r.seating_capacity), 'seat') : '';

        flow(
            '<div class="st-pagehead st-narrow"><button type="button" class="st-link" data-action="back-rooms">' + icon('arrow_back') + 'Back to rooms</button>' +
            '<h2 class="st-h2">Reserve a room</h2>' +
            '<p class="st-sub">Authorized by <strong>' + esc(st.verified.faculty_name) + '</strong></p></div>' +
            '<form class="st-card st-narrow" id="reserveForm" novalidate>' +
            '<div class="st-picked"><span class="st-tile" aria-hidden="true">' + icon('meeting_room') + '</span>' +
            '<div class="st-picked-text"><p class="st-picked-name">' + esc(r.room_name) + '</p>' +
            '<p class="st-picked-sub">' + esc(b.name) + ' &middot; ' + esc(hit.floor.label) + seats + '</p></div>' +
            '<button type="button" class="st-btn st-btn-ghost st-btn-sm" data-action="back-rooms">Change</button></div>' +
            '<div class="st-alert is-error" id="flowError" role="alert" hidden></div>' +
            '<div class="st-field"><label for="reserveDate">Date <span class="st-star">*</span></label>' +
            '<input type="date" class="st-input" id="reserveDate" min="' + t0 + '" value="' + t0 + '"></div>' +
            '<div class="st-row">' +
            '<div class="st-field"><label for="reserveStart">Start time <span class="st-star">*</span></label>' +
            '<select class="st-input" id="reserveStart">' + startOptions(t0) + '</select></div>' +
            '<div class="st-field"><label for="reserveEnd">End time <span class="st-star">*</span></label>' +
            '<select class="st-input" id="reserveEnd">' + endOptions('', '') + '</select></div>' +
            '</div>' +
            '<p class="st-hint" style="margin:-8px 0 16px">Open 7:00 AM &ndash; 8:00 PM, Monday to Saturday.</p>' +
            '<div class="st-field"><label for="reservePurpose">Purpose <span class="st-star">*</span></label>' +
            '<input type="text" class="st-input" id="reservePurpose" placeholder="e.g. Club meeting, review session" autocomplete="off" maxlength="200">' +
            '<div class="st-quick">' + PURPOSES.map(function (p) {
                return '<button type="button" class="st-chip" data-action="purpose" data-v="' + esc(p) + '">' + esc(p) + '</button>';
            }).join('') + '</div></div>' +
            '<div class="st-field" style="max-width:200px"><label for="reserveAttendees">Attendees</label>' +
            '<input type="number" class="st-input" id="reserveAttendees" value="1" min="1" inputmode="numeric">' +
            (r.seating_capacity != null && toInt(r.seating_capacity) > 0 ? '<p class="st-hint">This room seats ' + toInt(r.seating_capacity) + '.</p>' : '') + '</div>' +
            LETTER_HTML('reserveLetter', 'Recommended for reservations on a later date. You can still submit without one.') +
            '<div class="st-actions">' +
            '<button type="button" class="st-btn st-btn-ghost" data-action="back-rooms">Back</button>' +
            '<button type="submit" class="st-btn st-btn-primary" id="btnSubmitRoom">' + icon('event_available') + 'Reserve room</button>' +
            '</div></form>', 2);
    }

    function onReserveFieldChange(target) {
        if (target.id === 'reserveDate') {
            var cur = $('reserveStart').value, curEnd = $('reserveEnd').value;
            $('reserveStart').innerHTML = startOptions(target.value);
            var keep = cur && $('reserveStart').querySelector('option[value="' + cur + '"]:not([disabled])');
            if (keep) $('reserveStart').value = cur;
            var startNow = $('reserveStart').value;
            var endOk = startNow && curEnd && hhmmToMin(curEnd) > hhmmToMin(startNow);
            $('reserveEnd').innerHTML = endOptions(startNow, endOk ? curEnd : '');
            syncLetter('reserveLetter', target.value);
            var d = target.value ? parseYmd(target.value) : null;
            if (d && d.getDay() === 0) showAlert('flowError', 'Reservations are not available on Sundays. Please choose another day.');
            else hideAlert('flowError');
        } else if (target.id === 'reserveStart') {
            var prevEnd = $('reserveEnd').value;
            var startM = hhmmToMin(target.value);
            /* keep the old end if it is still after the new start, otherwise default to one hour */
            var want = prevEnd && hhmmToMin(prevEnd) > startM ? prevEnd : (startM + 60 <= CLOSE_MIN ? minToHHMM(startM + 60) : minToHHMM(startM + STEP_MIN));
            $('reserveEnd').innerHTML = endOptions(target.value, want);
        }
    }

    function submitReserve() {
        hideAlert('flowError');
        var v = st.verified, room = st.fac.room;
        if (!v || !room) return showAlert('flowError', 'Session expired. Please go back and verify again.');

        var date = $('reserveDate').value.trim();
        var start = $('reserveStart').value.trim();
        var end = $('reserveEnd').value.trim();
        var purpose = $('reservePurpose').value.trim();
        var attendees = Math.max(1, toInt($('reserveAttendees').value, 1));

        if (!date) return showAlert('flowError', 'Please select a date.');
        if (date < todayStr()) return showAlert('flowError', 'Reservation date and time cannot be in the past.');
        if (parseYmd(date).getDay() === 0) return showAlert('flowError', 'Reservations are not available on Sundays. Please choose another day.');
        if (!start) return showAlert('flowError', 'Please select a start time.');
        if (!end) return showAlert('flowError', 'Please select an end time.');
        if (end <= start) return showAlert('flowError', 'End time must be after start time.');
        if (!purpose) return showAlert('flowError', 'Please enter the purpose.');
        var letter = pickLetter('reserveLetter', date);
        if (letter.error) return showAlert('flowError', letter.error);

        var btn = $('btnSubmitRoom');
        setBusy(btn, true, 'Submitting…');

        var form = {
            student_name: v.student_name, student_id: v.student_id, faculty_name: v.faculty_name,
            reservation_date: date, start_time: start, end_time: end, purpose: purpose,
            building: currentBuilding() ? currentBuilding().name : ''
        };

        postWithLetter(URLS.reserve, {
            code_db_id: v.code_db_id,
            faculty_id: v.faculty_id,
            faculty_name: v.faculty_name,
            student_name: v.student_name,
            student_id: v.student_id,
            room_id: toInt(room.room_id),
            reservation_date: date,
            start_time: start,
            end_time: end,
            purpose: purpose,
            attendees: attendees
        }, letter.file).then(function (d) {
            setBusy(btn, false);
            /* A conflict (room taken, past time, …) comes back as an error and the code is NOT used,
               so the student can simply pick another slot with the same code. */
            if (d.error) return showAlert('flowError', d.error);
            st.verified = null; st.vKey = '';
            showRoomResult(d, form);
        });
    }

    function showRoomResult(res, f) {
        var approved = res.status === 'Approved';
        var waiting = res.status === 'Waiting';   /* a later date waits for an admin's decision */
        var title = approved ? 'Reservation approved' : (waiting ? 'Request sent — pending admin review' : 'Reservation ' + esc(res.status || 'received'));
        var note = waiting
            ? 'This reservation is for a later date, so an admin has to approve it. If it is not reviewed in time, it is automatically declined at 12:01 AM on the reservation date.'
            : (res.reason ? res.reason : '');
        flow(
            '<div class="st-result"><div class="st-result-top">' +
            '<span class="st-result-icon' + (waiting ? ' is-wait' : '') + '">' + icon(approved ? 'check_circle' : (waiting ? 'hourglass_top' : 'info')) + '</span>' +
            '<h2 class="st-h2">' + (approved ? 'Reservation approved' : (waiting ? 'Request sent — pending admin review' : esc(title))) + '</h2>' +
            '<p>Room reservation #' + esc(String(res.reservation_id)) + '</p>' +
            (note ? '<p>' + esc(note) + '</p>' : '') + '</div>' +
            '<div class="st-card"><dl class="st-summary">' +
            row('Student', f.student_name + (f.student_id ? ' · ' + f.student_id : '')) +
            row('Authorized by', f.faculty_name) +
            row('Room', (res.room_name || '') + (f.building ? ' — ' + f.building : '')) +
            row('Date', niceDate(f.reservation_date)) +
            row('Time', minToLabel(hhmmToMin(f.start_time)) + ' – ' + minToLabel(hhmmToMin(f.end_time))) +
            row('Purpose', f.purpose) +
            '</dl></div>' +
            '<div class="st-actions"><button type="button" class="st-btn st-btn-primary st-btn-block" data-action="home">Done</button></div></div>', 3);
    }

    function row(k, v) { return '<div><dt>' + esc(k) + '</dt><dd>' + esc(v) + '</dd></div>'; }

    /* ══════════════════════════════════════════════════════════
       RECEIPT (borrow) — summary + a claim QR and a return QR per request
    ══════════════════════════════════════════════════════════ */
    function normReceipt(r) {
        if (!r || typeof r !== 'object') return null;
        if (Array.isArray(r.items) && r.items.length) return r;
        var items = [];
        if (Array.isArray(r.all_results) && r.all_results.length) {            /* older multi-item shape */
            items = r.all_results.map(function (x) {
                return { equipment: x.equipment, qty: x.qty || 1, request_id: x.request_id, claim_token: x.claim_token || '', return_token: x.return_token || '' };
            });
        } else if (r.request_id) {                                              /* older single-item shape */
            items = [{ equipment: r.equipment, qty: 1, request_id: r.request_id, claim_token: r.claim_token || '', return_token: r.return_token || '' }];
        }
        if (!items.length) return null;
        r.items = items;
        return r;
    }

    function saveReceipt(r) {
        st.receipt = r;
        try { sessionStorage.setItem(RECEIPT_KEY, JSON.stringify(r)); } catch (e) { }
    }

    function loadReceiptBanner() {
        var banner = $('receiptBanner');
        if (!banner) return;
        var r = null;
        try { r = normReceipt(JSON.parse(sessionStorage.getItem(RECEIPT_KEY) || 'null')); } catch (e) { }
        if (!r) { banner.hidden = true; return; }
        st.receipt = r;
        var names = r.items.map(function (i) { return (i.qty > 1 ? i.qty + '× ' : '') + i.equipment; }).join(', ');
        $('receiptBannerSub').textContent = names + ' · Return by ' + fmtWhen(r.return_date);
        banner.hidden = false;
    }

    /* "2026-11-07" -> "Sat, Nov 7, 2026"   |   "2026-11-05 15:30" -> "Thu, Nov 5, 2026 · 3:30 PM" */
    function fmtWhen(s) {
        s = String(s == null ? '' : s);
        var m = /^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}:\d{2}))?/.exec(s);
        if (!m) return s;
        return m[2] ? niceDate(m[1]) + ' · ' + minToLabel(hhmmToMin(m[2])) : niceDate(m[1]);
    }

    function claimUrl(token) { return new URL('equipment-booking/claim_confirm.php?token=' + encodeURIComponent(token), window.location.href).href; }
    function returnUrl(token) { return new URL('equipment-booking/return_confirm.php?token=' + encodeURIComponent(token), window.location.href).href; }

    function showReceipt(r) {
        var many = r.items.length > 1;
        var reqs = r.items.map(function (it) {
            var qrs = '';
            if (it.claim_token) {
                qrs += '<div class="st-qr-box"><p class="st-qr-title">Claim QR<span>Show this to pick it up</span></p>' +
                    '<div class="st-qr" data-qr="' + esc(claimUrl(it.claim_token)) + '" data-color="#800000"></div></div>';
            }
            if (it.return_token) {
                qrs += '<div class="st-qr-box"><p class="st-qr-title">Return QR<span>Show this when returning</span></p>' +
                    '<div class="st-qr is-return" data-qr="' + esc(returnUrl(it.return_token)) + '" data-color="#444444"></div></div>';
            }
            return '<div class="st-card st-req"><div class="st-req-head"><span class="st-req-name">' +
                (it.qty > 1 ? esc(it.qty) + '× ' : '') + esc(it.equipment) + '</span>' +
                '<span class="st-req-id">Request #' + esc(String(it.request_id)) + '</span></div>' +
                (qrs ? '<div class="st-qrs">' + qrs + '</div>' : '') + '</div>';
        }).join('');

        flow(
            '<div class="st-result"><div class="st-result-top">' +
            '<span class="st-result-icon">' + icon('check_circle') + '</span>' +
            '<h2 class="st-h2">' + (many ? 'Requests approved' : 'Request approved') + '</h2>' +
            '<p>' + (many ? plural(r.items.length, 'item') + ' approved via faculty authorization.' : 'Approved via faculty authorization.') +
            ' Show the QR codes below to the admin.</p></div>' +
            '<div class="st-card"><dl class="st-summary">' +
            row('Student', r.student_name + (r.student_id ? ' · ' + r.student_id : '')) +
            row('Authorized by', r.faculty_name) +
            row('Room', r.room) +
            row('Borrow', fmtWhen(r.borrow_date)) +
            row('Return by', fmtWhen(r.return_date)) +
            '</dl></div>' +
            '<div class="st-req-list">' + reqs + '</div>' +
            '<div class="st-actions"><button type="button" class="st-btn st-btn-primary st-btn-block" data-action="done">Done</button></div></div>', 3);

        drawQrs($('view-flow'));
    }

    /* ── QR codes (library loaded on demand) ───────────────────── */
    var qrState = 'idle', qrQueue = [];
    function withQr(cb) {
        if (window.QRCode) return cb(true);
        qrQueue.push(cb);
        if (qrState === 'loading') return;
        qrState = 'loading';
        var s = document.createElement('script');
        s.src = QR_SRC;
        s.onload = function () { qrState = 'ready'; var q = qrQueue; qrQueue = []; q.forEach(function (f) { f(!!window.QRCode); }); };
        s.onerror = function () { qrState = 'idle'; var q = qrQueue; qrQueue = []; q.forEach(function (f) { f(false); }); };
        document.head.appendChild(s);
    }
    function drawQrs(root) {
        var holders = root.querySelectorAll('[data-qr]');
        if (!holders.length) return;
        withQr(function (ok) {
            Array.prototype.forEach.call(holders, function (h) {
                if (!ok) { h.innerHTML = '<span class="st-qr-fail">QR could not load.<br>Show the request number instead.</span>'; return; }
                h.innerHTML = '';
                var c = document.createElement('canvas');
                h.appendChild(c);
                try {
                    var p = window.QRCode.toCanvas(c, h.getAttribute('data-qr'), {
                        width: 160, margin: 2, color: { dark: h.getAttribute('data-color') || '#800000', light: '#ffffff' }
                    });
                    if (p && p.catch) p.catch(function () { h.innerHTML = '<span class="st-qr-fail">QR could not be drawn.</span>'; });
                } catch (e) { h.innerHTML = '<span class="st-qr-fail">QR could not be drawn.</span>'; }
            });
        });
    }

    /* ══════════════════════════════════════════════════════════
       EVENTS
    ══════════════════════════════════════════════════════════ */
    function nameOfCard(el) {
        var card = el.closest('.st-eq');
        return card ? card.getAttribute('data-name') : null;
    }

    function onClick(e) {
        if (e.target.id === 'studentRoomPicker') return closeStudentRoomPicker();
        var t = e.target.closest('[data-action]');
        if (!t) return;
        var a = t.getAttribute('data-action');
        var name;

        switch (a) {
            case 'service': return startService(t.getAttribute('data-service'));
            case 'home': case 'done': return goHome();
            case 'back-details':
                hideAlert('detailsError');
                return show('details', 1);
            case 'open-receipt':
                if (st.receipt) showReceipt(st.receipt);
                return;

            /* equipment */
            case 'eq-cat':
                st.cat = t.getAttribute('data-cat') || '';
                st.eqPage = 1;
                Array.prototype.forEach.call($('eqCats').querySelectorAll('.st-chip'), function (c) {
                    c.setAttribute('aria-pressed', c === t ? 'true' : 'false');
                });
                return renderEqGrid();
            case 'eq-add': name = nameOfCard(t); if (name) setQty(name, 1); return;
            case 'eq-inc': name = nameOfCard(t); if (name) setQty(name, (st.cart[name] || 1) + 1); return;
            case 'eq-dec': name = nameOfCard(t); if (name) setQty(name, (st.cart[name] || 1) - 1); return;
            case 'eq-remove':
                name = nameOfCard(t);
                if (name) { delete st.cart[name]; refreshCard(name, '[data-action="eq-add"]'); }
                return;
            case 'submit-borrow': return submitBorrow();
            case 'eq-page-prev':
                st.eqPage = Math.max(1, st.eqPage - 1);
                return renderEqGrid();
            case 'eq-page-next':
                st.eqPage++;
                return renderEqGrid();
            case 'eq-page':
                st.eqPage = toInt(t.getAttribute('data-page'), 1);
                return renderEqGrid();
            case 'open-student-room-picker': return openStudentRoomPicker(t.getAttribute('data-target'));
            case 'close-student-room-picker': return closeStudentRoomPicker();
            case 'retry-student-room-picker': return openStudentRoomPicker(st.roomPicker.target);
            case 'student-room-building':
                st.roomPicker.bid = t.getAttribute('data-id');
                st.roomPicker.floor = 0;
                return renderStudentRoomPicker();
            case 'student-room-back':
                st.roomPicker.bid = null;
                return renderStudentRoomPicker();
            case 'student-room-floor':
                st.roomPicker.floor = toInt(t.getAttribute('data-i'));
                return renderStudentRoomPicker();
            case 'student-room-select': return selectStudentRoom(toInt(t.getAttribute('data-id')));

            /* facility */
            case 'building': return openBuilding(t.getAttribute('data-id'));
            case 'back-buildings': return renderBuildings();
            case 'floor': st.fac.floor = toInt(t.getAttribute('data-i')); return renderRooms();
            case 'room': return renderReserve(toInt(t.getAttribute('data-id')));
            case 'back-rooms': return renderRooms();
            case 'purpose':
                $('reservePurpose').value = t.getAttribute('data-v');
                $('reservePurpose').focus();
                return;
        }
    }

    function onInput(e) {
        var t = e.target;
        if (t.id === 'eqSearch') { st.query = t.value; st.eqPage = 1; renderEqGrid(); }
    }

    function onChange(e) {
        var t = e.target;
        if (t.hasAttribute && t.hasAttribute('data-eq-qty')) {
            var name = nameOfCard(t);
            if (name) setQty(name, t.value);
        } else if (t.id === 'borrowDate') {
            syncLetter('borrowLetter', t.value);
            syncStudentSchedule('borrow');
        } else if (t.id === 'borrowStartTime' || t.id === 'borrowDuration') {
            syncStudentSchedule('borrow');
        } else if (t.id === 'itemDate' || t.id === 'itemStartTime' || t.id === 'itemDuration') {
            syncStudentSchedule('item');
        } else if (t.id === 'reserveDate' || t.id === 'reserveStart') {
            onReserveFieldChange(t);
        }
    }

    function onSubmit(e) {
        var f = e.target;
        if (!f || !f.id) return;
        if (f.id === 'borrowForm') { e.preventDefault(); submitBorrow(); }
        else if (f.id === 'itemForm') { e.preventDefault(); submitItem(); }
        else if (f.id === 'reserveForm') { e.preventDefault(); submitReserve(); }
    }

    function onKeyDown(e) {
        if (e.key === 'Escape' && $('studentRoomPicker') && !$('studentRoomPicker').hidden) {
            closeStudentRoomPicker();
        }
    }

    /* ══════════════════════════════════════════════════════════
       INIT
    ══════════════════════════════════════════════════════════ */
    function init() {
        loadBorrowRules();
        var course = $('studentCourse');
        COURSES.forEach(function (c) {
            var o = document.createElement('option');
            o.value = c; o.textContent = c;
            course.appendChild(o);
        });

        $('detailsForm').addEventListener('submit', onVerify);
        document.addEventListener('click', onClick);
        document.addEventListener('input', onInput);
        document.addEventListener('change', onChange);
        document.addEventListener('submit', onSubmit);
        document.addEventListener('keydown', onKeyDown);
        window.setInterval(function () {
            syncStudentSchedule('borrow');
            syncStudentSchedule('item');
        }, 30000);

        /* A photo that fails to load removes itself; the placeholder icon underneath shows instead.
           (error events do not bubble, so this listens in the capture phase.) */
        document.addEventListener('error', function (e) {
            var t = e.target;
            if (t && t.tagName === 'IMG' && t.hasAttribute('data-fallback')) t.remove();
        }, true);

        loadReceiptBanner();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();