/* ==========================================================================
   faculty-borrow-flow.js  —  Faculty Equipment tab: the combined borrow flow

   Same process as the student portal, with the form in a modal:

     1. Add items on the screen. "Add" turns into a quantity stepper; one item or many,
        there is no separate Borrow button and no checkbox.
     2. "Continue" (selection bar) opens the Borrow modal.
     3. In the modal: where (building with its photo > floor > room), when (date,
        start time, duration), an optional request letter for a later date, and who
        will collect (faculty or a student).
     4. One submit files one request per item (all of them share the batch).

   Role differences from the student flow (on purpose):
     - rooms another faculty has reserved for the chosen window are shown but locked
     - "For Personal" is offered, and the faculty's own room reservations are suggested
     - a later date may ask for more than is on the shelf today (up to total stock),
       and an item that is out right now can be booked ahead
     - the request is filed under the faculty's own account; a student may collect it

   Server: equipment-booking/api/submit-cart-borrow.php (re-checks every rule)
           equipment-booking/api/check-booking-availability.php (live checks)
   Data  : window.BOOKING_META (items, rooms, mine, rules), kept fresh by the
           inventory poll in faculty-dashboard.js through BorrowFlow.refreshStock().
   ========================================================================== */
(function () {
    'use strict';

    var URL_SUBMIT = 'equipment-booking/api/submit-cart-borrow.php';
    var URL_CHECK = 'equipment-booking/api/check-booking-availability.php';

    /* ── helpers ─────────────────────────────────────────────── */
    function $(id) { return document.getElementById(id); }
    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function toInt(v, d) { var n = parseInt(v, 10); return isNaN(n) ? (d || 0) : n; }
    function plural(n, one, many) { return n + ' ' + (n === 1 ? one : (many || one + 's')); }
    function icon(name) { return '<span class="material-symbols-outlined" aria-hidden="true">' + name + '</span>'; }
    function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }   /* LOCAL parts: toISOString() is UTC */
    function addDays(d, n) { var x = new Date(d.getTime()); x.setDate(x.getDate() + n); return x; }
    function parseYmd(s) { var p = String(s).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function todayStr() { return ymd(new Date()); }
    function isSunday(s) { return parseYmd(s).getDay() === 0; }
    function nowMin() { var n = new Date(); return n.getHours() * 60 + n.getMinutes(); }
    function hhmm(m) { return pad(Math.floor(m / 60)) + ':' + pad(m % 60); }
    function toMin(t) { var p = String(t).split(':'); return (+p[0]) * 60 + (+p[1]); }
    function clock(m) { var h = Math.floor(m / 60) % 24; return ((h % 12) || 12) + ':' + pad(m % 60) + ' ' + (h < 12 ? 'AM' : 'PM'); }
    function niceDate(s) {
        var d = parseYmd(s);
        return isNaN(d.getTime()) ? s : d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    }
    function toast(msg, type) { if (typeof window.showToast === 'function') window.showToast(msg, type); }
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        if (m && m.content) return m.content;
        var i = document.querySelector('input[name="csrf_token"]');
        return i ? i.value : '';
    }
    /* Image paths come from the database as "uploads/…" or "assets/…". Anything else is ignored. */
    function safeImg(p) {
        p = String(p == null ? '' : p).trim();
        if (!p || p.charAt(0) === '/' || p.indexOf('\\') !== -1 || p.indexOf('..') !== -1 || /^[a-z][a-z0-9+.\-]*:/i.test(p)) return '';
        return encodeURI(p);
    }

    /* ── rules + stock (from the server, kept fresh by the inventory poll) ── */
    function meta() {
        if (window.BOOKING_META) return window.BOOKING_META;

        var data = $('bookingMetaData');
        if (!data) return {};
        try {
            window.BOOKING_META = JSON.parse(data.textContent || '{}');
        } catch (error) {
            console.error('Unable to parse faculty booking metadata.', error);
            return {};
        }
        return window.BOOKING_META;
    }
    function rules() {
        var r = meta().rules || {};
        return {
            open: toMin(r.dayStart || '07:00'), lastStart: toMin(r.lastStart || '20:00'), close: toMin(r.dayEnd || '21:00'),
            step: +r.slot || 60, ahead: +r.maxAhead || 14,
            hours: (r.durationHours && r.durationHours.length) ? r.durationHours : [1, 2, 3, 6],
            maxDays: Math.max(1, +r.maxBorrowDays || 1), letterRequired: !!r.letterRequired
        };
    }
    /* Duration picker values: "3" = 3 hours, "d2" = 2 days (1 … the longest loan set in Settings → Arbitration). */
    function durParse(v) { v = String(v == null ? '' : v); return v.charAt(0) === 'd' ? { unit: 'days', n: toInt(v.slice(1)) } : { unit: 'hours', n: toInt(v) }; }
    /* the hours a start time must leave room for: a day loan only needs a bookable start */
    function durHours(v) { var d = durParse(v); return d.unit === 'days' ? 1 : (d.n || 1); }
    /* a day loan ends at closing time on its Nth OPEN day (Sundays are skipped), same as the server */
    function dayLoanEnd(dateStr, n) {
        var d = parseYmd(dateStr), left = Math.max(1, n) - 1;
        while (left > 0) { d = addDays(d, 1); if (d.getDay() !== 0) left--; }
        return ymd(d);
    }
    function hasStock(name) { return !!(meta().items || {})[name]; }
    function stock(name) {
        var m = (meta().items || {})[name] || {};
        var shelf = Math.max(0, toInt(m.shelf)), total = Math.max(0, toInt(m.total, shelf));
        return { shelf: shelf, total: Math.max(total, shelf) };
    }

    /* ══════════════════════════════════════════════════════════
       SELECTION  (item name → { id, name, qty })
    ══════════════════════════════════════════════════════════ */
    var sel = {};
    var order = [];

    function picked(name) { return Object.prototype.hasOwnProperty.call(sel, name); }
    function count() { return order.length; }
    function units() { return order.reduce(function (s, n) { return s + sel[n].qty; }, 0); }

    /* the most one card's stepper allows: what is on the shelf now (or total stock for a "book ahead" item) */
    function cardCap(name) { var s = stock(name); return Math.max(1, s.shelf > 0 ? s.shelf : s.total); }

    function slotsFor(name) {
        return Array.prototype.filter.call(document.querySelectorAll('[data-bf-slot]'), function (s) { return s.getAttribute('data-item-name') === name; });
    }

    function slotHTML(slot, name) {
        var s = stock(name), item = sel[name], hero = slot.classList.contains('bf-slot--hero');
        var btn = hero ? 'btn-borrow-primary' : 'btn-borrow';
        var ahead = s.shelf <= 0 && s.total > 0;
        if (s.shelf <= 0 && !ahead) {
            return '<button type="button" class="' + btn + ' bf-add" disabled>' + icon('block') + '<span class="btn-borrow-label">Unavailable</span></button>';
        }
        if (!item) {
            return '<button type="button" class="' + btn + ' bf-add' + (ahead ? ' btn-borrow-ahead' : '') + '" data-bf="add">' +
                icon(ahead ? 'event' : 'add') + '<span class="btn-borrow-label">' + (ahead ? 'Book ahead' : (hero ? 'Add to request' : (slot.classList.contains('bf-slot--block') ? 'Add Item' : 'Add'))) + '</span></button>';
        }
        return stepperHTML(name, item.qty, cardCap(name));
    }
    function stepperHTML(name, qty, max) {
        return '<div class="bf-stepper-row"><div class="bf-stepper">' +
            '<button type="button" class="bf-step-btn" data-bf="dec" aria-label="One less"' + (qty <= 1 ? ' disabled' : '') + '>&minus;</button>' +
            '<input type="number" class="bf-step-input" data-bf-qty min="1" max="' + max + '" value="' + qty + '" inputmode="numeric" aria-label="Quantity of ' + esc(name) + '">' +
            '<button type="button" class="bf-step-btn" data-bf="inc" aria-label="One more"' + (qty >= max ? ' disabled' : '') + '>+</button>' +
            '</div><button type="button" class="bf-icon-btn" data-bf="remove" aria-label="Remove ' + esc(name) + '">' + icon('delete') + '</button></div>';
    }

    /* what a slot currently shows, so the 8-second stock poll only redraws a slot when something changed
       (a redraw would steal the keyboard focus from a quantity box someone is typing in) */
    function slotSig(name) {
        var s = stock(name), item = sel[name];
        return [item ? item.qty : '-', s.shelf > 0 ? 'now' : (s.total > 0 ? 'ahead' : 'none'), cardCap(name)].join('|');
    }

    function paintSlot(slot) {
        var name = slot.getAttribute('data-item-name');
        var card = slot.closest('.item-node, .feat-hero, .feat-secondary');
        var on = picked(name);
        if (card) {
            card.classList.toggle(card.classList.contains('item-node') ? 'eq-item-card--in-cart' : 'is-picked', on);
        }
        if (slot.getAttribute('data-blocked') === '1') return;   /* overdue: stays as the server drew it */
        /* no stock figures for this item (the page data did not include it): leave the button as the
           server drew it instead of guessing "Unavailable" */
        if (!hasStock(name) && !on) return;
        var sig = slotSig(name);
        if (slot.getAttribute('data-sig') === sig && slot.firstElementChild) return;
        slot.setAttribute('data-sig', sig);
        slot.innerHTML = slotHTML(slot, name);
    }
    function paintItem(name, focusSel) {
        var list = slotsFor(name);
        list.forEach(paintSlot);
        updateBar();
        if (focusSel && list[0]) {
            var f = list[0].querySelector(focusSel);
            if (f) f.focus({ preventScroll: true });
        }
    }
    function paintAll() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-bf-slot]'), paintSlot);
        updateBar();
    }

    /* adjust steppers in place (no re-render: keyboard focus and the typed value are kept) */
    function syncQty(name, force) {
        var item = sel[name]; if (!item) return;
        var max = cardCap(name);
        slotsFor(name).forEach(function (slot) {
            var input = slot.querySelector('[data-bf-qty]'); if (!input) return;
            input.max = max;
            if (force || document.activeElement !== input) input.value = item.qty;
            var dec = slot.querySelector('[data-bf="dec"]'), inc = slot.querySelector('[data-bf="inc"]');
            if (dec) dec.disabled = item.qty <= 1;
            if (inc) inc.disabled = item.qty >= max;
            slot.setAttribute('data-sig', slotSig(name));
        });
        updateBar();
        syncRow(name, force);
    }

    function addItem(name, id) {
        if (!name || picked(name)) return;
        var s = stock(name);
        if (s.shelf <= 0 && s.total <= 0) return;
        sel[name] = { id: id, name: name, qty: 1 };
        order.push(name);
        paintItem(name, '[data-bf-qty]');
    }
    function removeItem(name, quiet) {
        if (!picked(name)) return;
        delete sel[name];
        order = order.filter(function (n) { return n !== name; });
        paintItem(name);
        if (!quiet && isOpen('bfModal')) {
            if (!count()) closeModal(); else { renderRows(); onWindowChange(); }
        }
    }
    function setQty(name, qty, cap) {
        var item = sel[name]; if (!item) return;
        var max = cap || cardCap(name);
        item.qty = Math.max(1, Math.min(max, toInt(qty, 1)));
        syncQty(name, true);
    }
    function clearAll() {
        order.slice().forEach(function (n) { removeItem(n, true); });
        sel = {}; order = [];
        paintAll();
        if (isOpen('bfModal')) closeModal();
    }

    function updateBar() {
        var bar = $('eqCartBar'), label = $('eqCartBarLabel'), sub = $('eqCartBarSub');
        if (!bar) return;
        var n = count();
        bar.style.display = n > 0 ? '' : 'none';
        if (label) label.textContent = n === 1 ? '1 item selected' : n + ' items selected';
        if (sub) sub.textContent = n ? plural(units(), 'unit') + ' in total' : '';
    }

    /* The inventory poll changed the numbers: redraw, and tidy up what can no longer be borrowed */
    function refreshStock() {
        var removed = false;
        order.slice().forEach(function (name) {
            var s = stock(name);
            if (s.shelf <= 0 && s.total <= 0) { removed = true; removeItem(name, true); toast(name + ' is no longer available and was removed from your request.', 'error'); return; }
            var cap = cardCap(name);
            if (sel[name] && sel[name].qty > cap) sel[name].qty = cap;
        });
        paintAll();
        order.forEach(function (n) { syncQty(n); });
        if (isOpen('bfModal')) {
            if (!count()) closeModal(); else if (removed) { renderRows(); onWindowChange(); } else order.forEach(function (n) { syncRow(n); });   /* in place: keeps focus in a quantity box */
        }
    }

    /* ══════════════════════════════════════════════════════════
       BORROW MODAL
    ══════════════════════════════════════════════════════════ */
    var mstate = { busy: false, tok: 0, timer: null, ok: null, mode: 'today', todayReason: '', lastQuery: '' };

    function isOpen(id) { var e = $(id); return !!e && e.style.display === 'flex'; }

    function openModal() {
        if (!count()) return;
        var modal = $('bfModal'); if (!modal) return;
        setError('');
        syncSchedule();
        renderRows();
        modal.style.display = 'flex';
        onWindowChange();
        var b = $('bfRoomBtn'); if (b) b.focus({ preventScroll: true });
    }
    function closeModal() {
        closePicker(false);
        var modal = $('bfModal'); if (modal) modal.style.display = 'none';
    }

    function setError(msg) {
        var el = $('bfError'); if (!el) return;
        el.hidden = !msg;
        el.textContent = msg || '';
        if (msg && el.scrollIntoView) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    /* ---- the items inside the modal ------------------------------------ */
    function modeNow() {
        var d = $('bfDate'); return (d && d.value && d.value !== todayStr()) ? 'future' : 'today';
    }
    function rowCap(name) {
        var s = stock(name);
        return Math.max(1, modeNow() === 'future' ? s.total : s.shelf);
    }
    function rowNote(name) {
        var s = stock(name), item = sel[name];
        if (modeNow() === 'future') return 'Up to ' + s.total + ' in stock for a later date';
        if (s.shelf <= 0) return 'Out right now — choose a later date';
        return s.shelf + ' available now' + (item && item.qty > s.shelf ? ' — lower the quantity' : '');
    }
    function itemImage(id) {
        var img = document.querySelector('.item-node[data-item-id="' + id + '"] img.eq-item-img');
        var src = img ? img.getAttribute('src') : '';
        return src ? '<img src="' + esc(src) + '" alt="" loading="lazy" data-fallback="1">' : '';
    }
    function renderRows() {
        var box = $('bfItems'); if (!box) return;
        box.innerHTML = order.map(function (name) {
            var item = sel[name], s = stock(name);
            var warn = modeNow() === 'today' && s.shelf <= 0;
            return '<div class="bf-item' + (warn ? ' is-warn' : '') + '" data-bf-row data-name="' + esc(name) + '">' +
                '<span class="bf-item-img"><span class="bf-item-ph">' + icon('inventory_2') + '</span>' + itemImage(item.id) + '</span>' +
                '<span class="bf-item-text"><strong>' + esc(name) + '</strong><small data-bf-note>' + esc(rowNote(name)) + '</small></span>' +
                stepperHTML(name, item.qty, rowCap(name)) + '</div>';
        }).join('');
    }
    function rowEl(name) {
        var rows = document.querySelectorAll('#bfItems [data-bf-row]');
        for (var i = 0; i < rows.length; i++) if (rows[i].getAttribute('data-name') === name) return rows[i];
        return null;
    }
    function syncRow(name, force) {
        var row = rowEl(name), item = sel[name]; if (!row || !item) return;
        var max = rowCap(name);
        var input = row.querySelector('[data-bf-qty]');
        if (input) { input.max = max; if (force || document.activeElement !== input) input.value = item.qty; }
        var dec = row.querySelector('[data-bf="dec"]'), inc = row.querySelector('[data-bf="inc"]');
        if (dec) dec.disabled = item.qty <= 1;
        if (inc) inc.disabled = item.qty >= max;
        var note = row.querySelector('[data-bf-note]'); if (note) note.textContent = rowNote(name);
        row.classList.toggle('is-warn', modeNow() === 'today' && stock(name).shelf <= 0);
    }

    /* ---- schedule (date / start time / duration), same rules as the student portal ---- */
    function borrowDates() {
        var r = rules(), out = [];
        for (var o = 0; o <= r.ahead; o++) {
            var d = ymd(addDays(new Date(), o));
            if (o > 0 && isSunday(d)) continue;
            var avail = o !== 0 || (!isSunday(d) && nowMin() <= r.lastStart);
            out.push({ value: d, available: avail, label: o === 0 ? 'Today · ' + niceDate(d) : niceDate(d) });
        }
        return out;
    }
    function startTimes(dateStr, dur) {
        var r = rules(), isToday = dateStr === todayStr();
        var start = isToday ? Math.max(r.open, Math.ceil(nowMin() / r.step) * r.step) : r.open;
        var out = [];
        for (var m = start; m <= r.lastStart && m + dur * 60 <= r.close; m += r.step) out.push(hhmm(m));
        return out;
    }
    function dateOptions() {
        var dates = borrowDates();
        var first = dates.filter(function (d) { return d.available; })[0];
        return '<option value=""' + ' disabled' + (!first ? ' selected' : '') + '>Choose a borrowing date</option>' +
            dates.map(function (d) {
                return '<option value="' + d.value + '"' + (d.available ? '' : ' disabled') + (first === d ? ' selected' : '') + '>' + esc(d.label) + '</option>';
            }).join('');
    }
    function timeOptions(dateStr, dur, selected) {
        var times = dateStr ? startTimes(dateStr, dur) : [];
        var chosen = times.indexOf(selected) !== -1 ? selected : (times[0] || '');
        return '<option value="" disabled' + (!chosen ? ' selected' : '') + '>' + (dateStr && !times.length ? 'No start times left' : 'Choose a start time') + '</option>' +
            times.map(function (t) { return '<option value="' + t + '"' + (chosen === t ? ' selected' : '') + '>' + esc(clock(toMin(t))) + '</option>'; }).join('');
    }
    function durationOptions(dateStr, time, selected) {
        var r = rules(), start = time ? toMin(time) : r.open, cur = String(selected == null ? '' : selected);
        var html = '<option value="" disabled' + (!cur ? ' selected' : '') + '>Choose duration</option><optgroup label="Hours">';
        r.hours.forEach(function (h) {
            var off = !dateStr || start + h * 60 > r.close;
            html += '<option value="' + h + '"' + (cur === String(h) ? ' selected' : '') + (off ? ' disabled' : '') + '>' + h + ' ' + (h === 1 ? 'hour' : 'hours') + '</option>';
        });
        html += '</optgroup><optgroup label="Days">';
        for (var n = 1; n <= r.maxDays; n++) {
            html += '<option value="d' + n + '"' + (cur === 'd' + n ? ' selected' : '') + (!dateStr ? ' disabled' : '') + '>' + n + ' ' + (n === 1 ? 'day' : 'days') + '</option>';
        }
        return html + '</optgroup>';
    }
    function refreshTimeAndDuration() {
        var date = $('bfDate'), time = $('bfTime'), dur = $('bfDuration');
        if (!date || !time || !dur) return;
        var prevTime = time.value, prevDur = dur.value;
        time.innerHTML = timeOptions(date.value, durHours(prevDur), prevTime);
        dur.innerHTML = durationOptions(date.value, time.value, prevDur);
        if (dur.value === '' && dur.options.length > 1) {
            for (var i = 1; i < dur.options.length; i++) if (!dur.options[i].disabled) { dur.value = dur.options[i].value; break; }
        }
    }
    /* Rebuilds the date list when "today" opens or closes (Sunday / after 8 PM) while the page stays open */
    function syncSchedule() {
        var date = $('bfDate'); if (!date) return;
        var r = rules(), today = borrowDates()[0];
        var reason = isSunday(today.value) ? 'sunday' : nowMin() > r.lastStart ? 'closed' : '';
        var state = today.value + ':' + reason;
        if (date.getAttribute('data-state') !== state || !date.options.length) {
            var keep = date.value;
            date.innerHTML = dateOptions();
            var found = borrowDates().filter(function (e) { return e.value === keep && e.available; })[0];
            if (found) date.value = keep;
            date.setAttribute('data-state', state);
            mstate.todayReason = reason;
            var info = $('bfTodayInfo');
            if (info) {
                var msg = reason === 'sunday' ? 'Same-day borrowing is unavailable on Sundays. Choose a later date.'
                    : 'Same-day borrowing is unavailable after ' + clock(r.lastStart) + '. Choose a later date.';
                info.hidden = !reason; info.title = reason ? msg : ''; info.setAttribute('aria-label', reason ? msg : '');
            }
        }
        refreshTimeAndDuration();
        syncLetter();
    }
    function syncLetter() {
        var wrap = $('bfLetter'), file = $('request_document'), date = $('bfDate');
        if (!wrap) return;
        var later = !!date && !!date.value && date.value > todayStr();
        wrap.hidden = !later;
        var req = rules().letterRequired, opt = wrap.querySelector('.bm-opt'), hint = wrap.querySelector('.bm-hint');
        if (opt) opt.textContent = req ? 'Required' : 'Recommended';
        if (hint) hint.textContent = req ? 'Required for bookings on a later date.' : 'Recommended for bookings on a later date. You can still submit without one.';
        if (!later && file && file.value) { file.value = ''; if (typeof window.syncBorrowDrops === 'function') window.syncBorrowDrops(); }
    }

    function windowOf() {
        var date = $('bfDate'), time = $('bfTime'), dur = $('bfDuration');
        if (!date || !date.value || !time.value) return null;
        var dv = durParse(dur.value);
        if (!dv.n) return null;
        var s = toMin(time.value), mode = date.value === todayStr() ? 'today' : 'future';
        if (dv.unit === 'days') {
            return { date: date.value, time: time.value, dur: dv.n, unit: 'days', startMin: s, endDate: dayLoanEnd(date.value, dv.n), endMin: rules().close, mode: mode };
        }
        return { date: date.value, time: time.value, dur: dv.n, unit: 'hours', startMin: s, endDate: date.value, endMin: s + dv.n * 60, mode: mode };
    }
    function windowQuery(w) {
        return 'mode=' + w.mode + '&date=' + encodeURIComponent(w.mode === 'future' ? w.date : '') +
            '&time=' + encodeURIComponent(w.time) + '&duration=' + w.dur + '&unit=' + w.unit;
    }
    function windowLabel(w) {
        return (w.mode === 'today' ? 'Today' : parseYmd(w.date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })) +
            ' · ' + clock(w.startMin) + ' → ' + (w.endDate !== w.date ? niceDate(w.endDate) + ' · ' : '') + clock(w.endMin);
    }

    /* ---- live check: item availability + which rooms are taken ---------- */
    var avail = { rooms: null, tok: 0, rtok: 0 };   /* rooms: { reserved:Set, mine:Set, info:{} } for the current window */

    function setAvail(msg, cls) {
        var el = $('bfAvail'); if (!el) return;
        el.textContent = msg || ''; el.className = 'bm-avail' + (cls ? ' ' + cls : '');
    }

    function onWindowChange() {
        syncLetter();
        order.forEach(function (n) { syncRow(n); });
        var w = windowOf(), sum = $('bfSummary');
        mstate.mode = w ? w.mode : modeNow();
        if (!w) {
            if (sum) { sum.innerHTML = ''; sum.classList.remove('has'); }
            setAvail('', ''); mstate.ok = null; avail.rooms = null; mstate.lastQuery = '';
            clampRoomIfTaken();
            return;
        }
        if (sum) {
            sum.innerHTML = icon('schedule') + '<span><strong>' + esc(w.mode === 'today' ? 'Today' : niceDate(w.date)) + ' · ' + clock(w.startMin) + '</strong> → <strong>' + (w.endDate !== w.date ? esc(niceDate(w.endDate)) + ' · ' : '') + clock(w.endMin) + '</strong></span>';
            sum.classList.add('has');
        }
        clearTimeout(mstate.timer);
        mstate.timer = setTimeout(function () { check(w); }, 300);
    }

    function itemsParam(w) {
        return JSON.stringify(order.map(function (n) { return { n: n, q: w.mode === 'today' ? 1 : sel[n].qty }; }));
    }
    function check(w) {
        var token = ++avail.tok;
        setAvail('Checking availability…', 'busy');
        var q = windowQuery(w) + '&items=' + encodeURIComponent(itemsParam(w)) + '&rooms=1';
        mstate.lastQuery = windowQuery(w);
        fetch(URL_CHECK + '?' + q, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (token !== avail.tok) return;
                if (!j || j.ok !== true) { mstate.ok = false; setAvail((j && j.error) || 'That schedule is not allowed.', 'bad'); return; }
                var bad = (j.results || []).filter(function (x) { return !x.ok; });
                mstate.ok = bad.length === 0;
                if (!bad.length) setAvail(order.length > 1 ? 'All items are available for that time.' : ((j.results[0] || {}).message || ''), 'ok');
                else setAvail(bad.slice(0, 2).map(function (x) { return (order.length > 1 ? x.name + ': ' : '') + x.message; }).join(' '), 'bad');
                if (j.rooms) applyRooms(j.rooms);
            })
            .catch(function () { if (token === avail.tok) { mstate.ok = null; setAvail('', ''); } });
    }
    function applyRooms(rooms) {
        var toSet = function (a) { return new Set((a || []).map(Number)); };
        avail.rooms = { reserved: toSet(rooms.reserved), mine: toSet(rooms.mine), info: rooms.info || {} };
        var cur = $('bfRoomId').value;
        if (cur && cur !== 'outside' && avail.rooms.reserved.has(+cur)) {
            clearRoom(); setRoomHint('That room is reserved for the new time. Pick another room.', 'bad');
        } else if (!cur && avail.rooms.mine.size === 1) {
            setRoom(String(Array.from(avail.rooms.mine)[0]), true);
        } else if (cur && /bad/.test($('bfRoomHint').className)) {
            setRoomHint('');
        }
        if (isOpen('bfRoomPicker')) paintStates();
    }
    function clampRoomIfTaken() { /* no window → nothing is known to be taken */ if (avail.rooms) avail.rooms = null; }

    /* ══════════════════════════════════════════════════════════
       ROOM PICKER  (building with photo → floor → room)
    ══════════════════════════════════════════════════════════ */
    var rp = { bid: null, floor: 0, buildings: null, index: null, timer: null, lastFocus: null };

    function buildBuildings() {
        var list = [], idx = {};
        (meta().rooms || []).forEach(function (b) {
            var floors = (b.floors || []).map(function (f) {
                var rooms = (f.rooms || []).filter(function (r) { return !r.status || r.status === 'Available'; });
                return { num: toInt(f.floor), label: f.label || (f.floor + 'F'), rooms: rooms };
            }).filter(function (f) { return f.rooms.length; }).sort(function (a, c) { return a.num - c.num; });
            if (!floors.length) return;
            var name = b.name || 'Building';
            var m = /^(.*?)\s*\((.+)\)\s*$/.exec(name);
            var base = m ? m[1] : name;
            var lm = /Building\s+([A-Za-z])/.exec(base);
            var bld = {
                id: String(b.building_id), name: name, base: base, tag: m ? m[2] : '',
                letter: ((lm ? lm[1] : base.charAt(0)) || 'B').toUpperCase(),
                image: b.image || '', floors: floors,
                rooms: floors.reduce(function (s, f) { return s + f.rooms.length; }, 0)
            };
            floors.forEach(function (f) { f.rooms.forEach(function (r) { idx[r.room_id] = { id: r.room_id, name: r.name, building: name, floor: f.label }; }); });
            list.push(bld);
        });
        rp.buildings = list; rp.index = idx;
    }
    function buildingById(id) {
        var bs = rp.buildings || [];
        for (var i = 0; i < bs.length; i++) if (bs[i].id === id) return bs[i];
        return null;
    }

    function openPicker() {
        var modal = $('bfRoomPicker'); if (!modal) return;
        buildBuildings();
        rp.bid = null; rp.floor = 0; rp.lastFocus = document.activeElement;
        $('bfRpSub').textContent = 'Select a building, floor and room.';
        renderPicker();
        modal.style.display = 'flex';
        var body = $('bfRpBody'); if (body) body.scrollTop = 0;
        var c = modal.querySelector('[data-bf="rp-close"]'); if (c) c.focus({ preventScroll: true });
        refreshRooms();
        clearInterval(rp.timer);
        rp.timer = setInterval(refreshRooms, 10000);   /* somebody else may reserve a room while this is open */
    }
    function closePicker(restoreFocus) {
        var modal = $('bfRoomPicker');
        clearInterval(rp.timer); rp.timer = null;
        if (!modal || modal.style.display !== 'flex') return;
        modal.style.display = 'none';
        if (restoreFocus !== false) { var b = $('bfRoomBtn'); if (b) b.focus({ preventScroll: true }); }
    }
    /* which rooms are taken for the current window (light call: no items) */
    function refreshRooms() {
        var w = windowOf();
        var sub = $('bfRpSub');
        if (!w) { avail.rooms = null; if (sub) sub.textContent = 'Choose a valid date and time to see which rooms are free.'; paintStates(); return; }
        if (sub && !rp.bid) sub.textContent = windowLabel(w);
        var token = ++avail.rtok;
        fetch(URL_CHECK + '?' + windowQuery(w) + '&rooms=1&items=%5B%5D', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (token !== avail.rtok || !j || j.ok !== true || !j.rooms) return;
                applyRooms(j.rooms);
            }).catch(function () { });
    }

    function panelHTML(b, action) {
        var src = safeImg(b.image);
        return '<button type="button" class="bf-panel" data-bf="' + action + '" data-id="' + esc(b.id) + '" aria-label="' + esc(b.name) + ', ' + plural(b.rooms, 'room') + '">' +
            (src ? '<img class="bf-panel-img" src="' + esc(src) + '" alt="" data-fallback="panel">' : '') +
            '<span class="bf-panel-shade"></span><span class="bf-panel-letter" aria-hidden="true">' + esc(b.letter) + '</span>' +
            '<span class="bf-panel-info"><span class="bf-panel-name">' + esc(b.base) + (b.tag ? '<em class="bf-tag">' + esc(b.tag) + '</em>' : '') + '</span>' +
            '<span class="bf-panel-meta">' + plural(b.rooms, 'room') + ' &middot; ' + plural(b.floors.length, 'floor') + '</span></span>' +
            '<span class="bf-panel-go" aria-hidden="true">' + icon('arrow_forward') + '</span></button>';
    }

    function renderPicker() {
        var body = $('bfRpBody'); if (!body) return;
        var bs = rp.buildings || [];
        var b = rp.bid ? buildingById(rp.bid) : null;

        if (!b) {   /* step 1: building (+ outside, + my own reservations) */
            var html = '';
            var mine = (meta().mine || []).filter(function (r) { return rp.index[r.room_id]; });
            if (mine.length) {
                html += '<p class="bf-rp-label">Recommended for you</p><div class="bf-quick-grid">' + mine.map(function (r) {
                    var a = parseTs(r.start), z = parseTs(r.end);
                    var now = new Date();
                    var grp = (a && z && a <= now && now < z) ? 'Using now' : (a && ymd(a) === todayStr() ? 'Later today' : (a ? dayLabel(a) : ''));
                    var sub = (a && z ? rangeLabel(a, z) + ' · ' : '') + esc(r.building || '');
                    return '<button type="button" class="bf-quick" data-bf="rp-room" data-id="' + toInt(r.room_id) + '">' + icon('event_available') +
                        '<span class="bf-quick-txt"><strong>' + esc(r.name) + '</strong><small>' + sub + '</small></span>' +
                        '<span class="bf-quick-tag" data-bf-tag>' + esc(grp) + '</span></button>';
                }).join('') + '</div>';
            }
            html += '<p class="bf-rp-label">Choose a building</p>';
            html += bs.length
                ? '<div class="bf-panels">' + bs.map(function (x) { return panelHTML(x, 'rp-building'); }).join('') + '</div>'
                : '<div class="bf-empty">' + icon('meeting_room') + '<strong>No rooms are open for equipment right now</strong><span>You can still choose For Personal.</span></div>';
            html += '<div class="bf-quick-grid bf-quick-grid--one"><button type="button" class="bf-quick" data-bf="rp-room" data-id="outside">' + icon('park') +
                '<span class="bf-quick-txt"><strong>For Personal</strong><small>Personal use / off-campus</small></span><span class="bf-quick-tag" data-bf-tag></span></button></div>';
            body.innerHTML = html;
            var sub = $('bfRpSub'), w = windowOf();
            if (sub) sub.textContent = w ? windowLabel(w) : 'Select a building, floor and room.';
            paintStates();
            return;
        }

        /* step 2: floors + rooms of one building */
        var floor = b.floors[rp.floor] || b.floors[0];
        var src = safeImg(b.image);
        var tabs = b.floors.length > 1
            ? '<div class="bf-floors" role="tablist" aria-label="Floors">' + b.floors.map(function (f, i) {
                return '<button type="button" class="bf-floor" role="tab" data-bf="rp-floor" data-i="' + i + '" aria-selected="' + (i === rp.floor ? 'true' : 'false') + '"><span>' + esc(f.label) + '</span></button>';
            }).join('') + '</div>' : '';
        body.innerHTML =
            '<nav class="bf-crumbs" aria-label="Breadcrumb"><button type="button" data-bf="rp-back">' + icon('arrow_back') + 'Buildings</button></nav>' +
            '<header class="bf-hero-b">' + (src ? '<img class="bf-panel-img" src="' + esc(src) + '" alt="" data-fallback="panel">' : '') +
            '<span class="bf-panel-shade"></span><div class="bf-hero-b-text"><h4>' + esc(b.base) + (b.tag ? '<em class="bf-tag">' + esc(b.tag) + '</em>' : '') + '</h4>' +
            '<p>' + plural(b.rooms, 'room') + ' &middot; ' + plural(b.floors.length, 'floor') + '</p></div></header>' +
            tabs +
            '<div class="bf-rooms" role="tabpanel" aria-label="Rooms on this floor">' + floor.rooms.map(function (r) {
                return '<button type="button" class="bf-room" data-bf="rp-room" data-id="' + toInt(r.room_id) + '">' +
                    '<span class="bf-room-name">' + esc(r.name) + '</span>' +
                    '<span class="bf-room-meta"><span class="bf-dot" data-bf-dot></span><span data-bf-status>Free</span></span>' +
                    '<span class="bf-room-when" data-bf-when></span></button>';
            }).join('') + '</div>';
        var sub2 = $('bfRpSub'); if (sub2) sub2.textContent = b.name;
        paintStates();
    }

    /* free / reserved / selected state of every room tile (cheap; runs on each refresh) */
    function paintStates() {
        var body = $('bfRpBody'); if (!body) return;
        var cur = $('bfRoomId').value;
        var reserved = avail.rooms ? avail.rooms.reserved : new Set();
        var mine = avail.rooms ? avail.rooms.mine : new Set();
        var info = avail.rooms ? avail.rooms.info : {};
        Array.prototype.forEach.call(body.querySelectorAll('[data-bf="rp-room"]'), function (el) {
            var id = el.getAttribute('data-id'), isOut = id === 'outside';
            var taken = !isOut && reserved.has(+id), isMine = !isOut && mine.has(+id), on = cur === id && !taken;
            var when = taken ? whenTaken(info, id) : '';
            el.classList.toggle('is-taken', taken);
            el.classList.toggle('is-mine', isMine && !taken);
            el.classList.toggle('is-selected', on);
            el.setAttribute('aria-pressed', on ? 'true' : 'false');
            if (taken) el.setAttribute('aria-disabled', 'true'); else el.removeAttribute('aria-disabled');
            el.title = taken ? 'Reserved' + (when ? ' ' + when : '') : '';
            var st = el.querySelector('[data-bf-status]');
            if (st) st.textContent = taken ? 'Reserved' : (isMine ? 'Your reservation' : 'Free');
            var wh = el.querySelector('[data-bf-when]'); if (wh) wh.textContent = when;
            var dot = el.querySelector('[data-bf-dot]');
            if (dot) { dot.classList.toggle('bf-dot--taken', taken); dot.classList.toggle('bf-dot--free', !taken); }
            var tag = el.querySelector('[data-bf-tag]');
            if (tag && !isOut) {
                if (!tag.hasAttribute('data-orig')) tag.setAttribute('data-orig', tag.textContent);
                tag.classList.toggle('is-taken', taken);
                tag.textContent = taken ? 'Reserved' : (isMine ? 'Fits your time' : tag.getAttribute('data-orig'));
            }
        });
    }
    function whenTaken(info, id) {
        var list = (info && info[id]) || [];
        return list.slice(0, 2).map(function (x) {
            var a = parseTs(x.start), z = parseTs(x.end);
            return (a && z) ? dayLabel(a) + ' · ' + rangeLabel(a, z) : '';
        }).filter(Boolean).join(', ') + (list.length > 2 ? ' +' + (list.length - 2) : '');
    }

    /* time helpers for the labels above */
    function parseTs(s) {
        var m = String(s || '').match(/(\d+)-(\d+)-(\d+)[ T](\d+):(\d+)/);
        return m ? new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]) : null;
    }
    function hm(d) { return ((d.getHours() % 12) || 12) + (d.getMinutes() ? ':' + pad(d.getMinutes()) : ''); }
    function mer(d) { return d.getHours() < 12 ? 'AM' : 'PM'; }
    function rangeLabel(a, b) { return mer(a) === mer(b) ? hm(a) + '–' + hm(b) + ' ' + mer(b) : hm(a) + ' ' + mer(a) + ' – ' + hm(b) + ' ' + mer(b); }
    function dayLabel(d) {
        var diff = Math.round((new Date(d.getFullYear(), d.getMonth(), d.getDate()) - parseYmd(todayStr())) / 86400000);
        return diff === 0 ? 'Today' : diff === 1 ? 'Tomorrow' : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    /* ---- choosing a room ------------------------------------------------ */
    function setRoomHint(msg, cls) {
        var h = $('bfRoomHint'); if (!h) return;
        h.hidden = !msg; h.textContent = msg || ''; h.className = 'bm-hint' + (cls ? ' ' + cls : '');
    }
    function roomLabelHTML(value) {
        if (!value) return '<span class="bf-room-ph">Choose a building and room</span>';
        if (value === 'outside') return '<strong>For Personal</strong><span>Personal use / off-campus</span>';
        var r = rp.index && rp.index[value];
        if (!r) { buildBuildings(); r = rp.index[value]; }
        return r ? '<strong>' + esc(r.name) + '</strong><span>' + esc(r.building) + ' · ' + esc(r.floor) + '</span>' : '<span class="bf-room-ph">Choose a building and room</span>';
    }
    function setRoom(value, auto) {
        $('bfRoomId').value = value;
        $('bfRoomLabel').innerHTML = roomLabelHTML(value);
        setRoomHint(auto ? 'Suggested from your reservation.' : '', auto ? 'ok' : '');
    }
    function clearRoom() { $('bfRoomId').value = ''; $('bfRoomLabel').innerHTML = roomLabelHTML(''); }

    function pickRoom(id) {
        if (id !== 'outside') {
            if (avail.rooms && avail.rooms.reserved.has(+id)) return;   /* reserved: hoverable, never selectable */
            if (!rp.index || !rp.index[id]) return;
        }
        setRoom(id, false);
        closePicker(true);
    }

    /* ---- submit --------------------------------------------------------- */
    function validate(w) {
        if (!count()) return 'Please add at least one item.';
        var r = rules(), date = $('bfDate').value;
        if (!date) return 'Please select a borrow date.';
        var d = borrowDates().filter(function (e) { return e.value === date; })[0];
        if (!d) return 'Choose today or a non-Sunday date within the next ' + (r.ahead === 14 ? 'two weeks' : r.ahead + ' days') + '.';
        if (!d.available) return isSunday(date) ? 'Same-day borrowing is unavailable on Sundays. Choose a later date.' : 'Same-day borrowing is unavailable after ' + clock(r.lastStart) + '. Choose a later date.';
        if (isSunday(date)) return 'Equipment borrowing is unavailable on Sundays.';
        if (!w) return 'Please choose a start time and a duration.';
        if (startTimes(date, w.unit === 'days' ? 1 : w.dur).indexOf(w.time) === -1) return 'Choose a start time that allows the selected borrowing duration.';
        if (!$('bfRoomId').value) return 'Please choose a building and room.';
        for (var i = 0; i < order.length; i++) {
            var name = order[i], s = stock(name), q = sel[name].qty;
            if (w.mode === 'today') {
                if (s.shelf <= 0) return name + ' is out right now. Choose a later date for it, or remove it.';
                if (q > s.shelf) return name + ': only ' + s.shelf + ' available right now.';
            } else if (q > s.total) {
                return name + ': only ' + s.total + ' in stock.';
            }
        }
        var type = (document.querySelector('input[name="bf_collector_type"]:checked') || {}).value || 'faculty';
        if (type === 'student' && !$('bfStudentName').value.trim()) return 'Please enter the student\u2019s name before submitting.';
        if (w.mode === 'future') {
            var f = $('request_document') && $('request_document').files && $('request_document').files[0];
            if (!f && r.letterRequired) return 'A request letter is required for later-date requests. Please attach one.';
            if (f) {
                if (!/\.(pdf|jpe?g|png|webp)$/i.test(f.name)) return 'The request letter must be a PDF, JPG, PNG or WEBP file.';
                if (f.size > 5 * 1024 * 1024) return 'The request letter is too large. Maximum size is 5 MB.';
            }
        }
        if (mstate.ok === false) return ($('bfAvail').textContent || 'That schedule is not available.');
        return '';
    }

    function setBusy(on) {
        mstate.busy = on;
        var b = $('bfSubmit'); if (!b) return;
        if (on) {
            if (b.getAttribute('data-html') == null) b.setAttribute('data-html', b.innerHTML);
            b.disabled = true; b.innerHTML = icon('progress_activity') + ' Submitting…';
        } else {
            b.disabled = false;
            if (b.getAttribute('data-html') != null) { b.innerHTML = b.getAttribute('data-html'); b.removeAttribute('data-html'); }
        }
    }

    function submit() {
        if (mstate.busy) return;
        setError('');
        var w = windowOf();
        var err = validate(w);
        if (err) { setError(err); toast(err, 'error'); return; }

        var type = (document.querySelector('input[name="bf_collector_type"]:checked') || {}).value || 'faculty';
        var payload = {
            csrf_token: csrf(),
            room_id: $('bfRoomId').value,
            items: order.map(function (n) { return { name: n, qty: sel[n].qty }; }),
            booking_mode: w.mode,
            borrow_date: w.date,
            time_slot: w.time,
            duration: w.dur,
            duration_unit: w.unit,
            collector_type: type,
            collector_name: type === 'student' ? $('bfStudentName').value.trim() : ''
        };
        var file = (w.mode === 'future' && $('request_document') && $('request_document').files) ? $('request_document').files[0] : null;
        var init;
        if (file) {
            var fd = new FormData();
            fd.append('payload', JSON.stringify(payload));
            fd.append('request_document', file, file.name);
            init = { method: 'POST', body: fd, credentials: 'same-origin' };
        } else {
            init = { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload), credentials: 'same-origin' };
        }

        setBusy(true);
        fetch(URL_SUBMIT, init)
            .then(function (r) { return r.text(); })
            .then(function (t) { try { return JSON.parse(t); } catch (e) { return { ok: false, error: 'The server sent an unexpected reply. Please try again.' }; } })
            .catch(function () { return { ok: false, error: 'Network error. Please check your connection and try again.' }; })
            .then(function (data) {
                setBusy(false);
                if (!data.ok) { setError(data.error || 'Something went wrong. Please try again.'); return; }
                var done = data.submitted || [], failed = data.failed || [];
                /* items the server accepted leave the selection, so a retry never files them twice */
                done.forEach(function (d) { removeItem(d.name, true); });
                if (!failed.length) {
                    /* say what actually happened: a same-day request is decided on the spot, a later-date one waits for an admin */
                    var nApproved = done.filter(function (d) { return d.status === 'Approved'; }).length;
                    var nWaiting = done.length - nApproved;
                    var parts = [];
                    if (nApproved) parts.push(nApproved + (nApproved === 1 ? ' request' : ' requests') + ' approved \u2013 show your Claim QR to the admin');
                    if (nWaiting) parts.push(nWaiting + ' waiting for admin review');
                    toast(parts.join(' \u00b7 ') || 'Borrow request submitted.');
                    resetForm();
                    closeModal();
                } else {
                    var lines = failed.map(function (f) { return f.name + ': ' + f.error; }).join('\n');
                    if (done.length) {
                        toast(done.length + ' submitted. ' + failed.length + ' could not be filed.', 'error');
                        renderRows(); onWindowChange();
                        setError(lines + '\nThe items that went through were removed — fix the rest and submit again.');
                    } else {
                        setError(lines);
                    }
                }
                if (done.length) document.dispatchEvent(new CustomEvent('pupsync:requests-changed'));
            });
    }

    function resetForm() {
        clearRoom(); setRoomHint('');
        var date = $('bfDate'); if (date) { date.removeAttribute('data-state'); }
        var dur = $('bfDuration'); if (dur) dur.innerHTML = '';
        var f = $('request_document'); if (f) f.value = '';
        if (typeof window.syncBorrowDrops === 'function') window.syncBorrowDrops();
        var fac = document.querySelector('input[name="bf_collector_type"][value="faculty"]'); if (fac) fac.checked = true;
        var n = $('bfStudentName'); if (n) n.value = '';
        var sf = $('bfStudentFields'); if (sf) sf.hidden = true;
        setError(''); setAvail('', ''); mstate.ok = null; avail.rooms = null;
    }

    /* ══════════════════════════════════════════════════════════
       EVENTS
    ══════════════════════════════════════════════════════════ */
    function slotOf(el) { return el.closest('[data-bf-slot]'); }
    function rowOf(el) { return el.closest('[data-bf-row]'); }
    function nameOf(el) {
        var s = slotOf(el); if (s) return s.getAttribute('data-item-name');
        var r = rowOf(el); return r ? r.getAttribute('data-name') : '';
    }
    function capFor(el, name) { return rowOf(el) ? rowCap(name) : cardCap(name); }

    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('[data-bf]') : null;
        if (!t) {
            /* backdrop clicks */
            if (e.target.id === 'bfRoomPicker') closePicker(true);
            else if (e.target.id === 'bfModal') closeModal();
            return;
        }
        var act = t.getAttribute('data-bf'), name;
        switch (act) {
            case 'add': {
                var slot = slotOf(t); if (!slot) return;
                addItem(slot.getAttribute('data-item-name'), slot.getAttribute('data-item-id'));
                return;
            }
            case 'inc': case 'dec': {
                name = nameOf(t); if (!picked(name)) return;
                setQty(name, sel[name].qty + (act === 'inc' ? 1 : -1), capFor(t, name));
                return;
            }
            case 'remove': name = nameOf(t); removeItem(name); return;
            case 'clear': clearAll(); return;
            case 'continue': openModal(); return;
            case 'close': closeModal(); return;
            case 'open-room': openPicker(); return;
            case 'rp-close': closePicker(true); return;
            case 'rp-building': rp.bid = t.getAttribute('data-id'); rp.floor = 0; renderPicker(); var bd = $('bfRpBody'); if (bd) bd.scrollTop = 0; return;
            case 'rp-back': rp.bid = null; rp.floor = 0; renderPicker(); return;
            case 'rp-floor': rp.floor = toInt(t.getAttribute('data-i')); renderPicker(); return;
            case 'rp-room': if (t.getAttribute('aria-disabled') === 'true') return; pickRoom(t.getAttribute('data-id')); return;
        }
    });

    /* typing a quantity */
    document.addEventListener('input', function (e) {
        var inp = e.target; if (!inp.matches || !inp.matches('[data-bf-qty]')) return;
        var name = nameOf(inp); if (!picked(name)) return;
        var max = capFor(inp, name), v = toInt(inp.value, 1);
        if (inp.value === '') return;                 /* let them clear the box before typing a new number */
        if (v < 1) v = 1; if (v > max) v = max;
        sel[name].qty = v; inp.value = v;
        syncQty(name);
        if (isOpen('bfModal')) { if (rowOf(inp)) { clearTimeout(mstate.timer); mstate.timer = setTimeout(onWindowChange, 400); } }
    });
    document.addEventListener('change', function (e) {
        var inp = e.target; if (!inp.matches) return;
        if (inp.matches('[data-bf-qty]')) {
            var name = nameOf(inp); if (!picked(name)) return;
            setQty(name, inp.value, capFor(inp, name)); inp.value = sel[name].qty;
            if (isOpen('bfModal')) onWindowChange();
            return;
        }
        if (inp.id === 'bfDate') { refreshTimeAndDuration(); onWindowChange(); return; }
        if (inp.id === 'bfTime') {
            var dur = $('bfDuration'), d = dur.value;
            dur.innerHTML = durationOptions($('bfDate').value, inp.value, d);
            onWindowChange(); return;
        }
        if (inp.id === 'bfDuration') {
            var time = $('bfTime'), prev = time.value;
            time.innerHTML = timeOptions($('bfDate').value, durHours(inp.value), prev);
            onWindowChange(); return;
        }
        if (inp.name === 'bf_collector_type') {
            var box = $('bfStudentFields'); if (box) box.hidden = inp.value !== 'student';
            if (inp.value === 'student') { var n = $('bfStudentName'); if (n) n.focus(); }
        }
    });
    document.addEventListener('submit', function (e) {
        if (e.target && e.target.id === 'bfForm') { e.preventDefault(); submit(); }
    });

    /* Enter inside a quantity box applies it instead of submitting the form */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.matches && e.target.matches('[data-bf-qty]')) { e.preventDefault(); e.target.blur(); }
    });

    /* Escape closes the room picker first, then the modal; Tab stays inside whichever is on top */
    document.addEventListener('keydown', function (e) {
        var picker = isOpen('bfRoomPicker'), modal = isOpen('bfModal');
        if (!picker && !modal) return;
        if (e.key === 'Escape') { e.stopImmediatePropagation(); e.preventDefault(); if (picker) closePicker(true); else closeModal(); return; }
        if (e.key !== 'Tab') return;
        var root = $(picker ? 'bfRoomPicker' : 'bfModal');
        var f = Array.prototype.filter.call(root.querySelectorAll('button, input, select, textarea, [href]'), function (x) {
            return !x.disabled && !x.closest('[hidden]') && x.offsetParent !== null;
        });
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }, true);

    /* A missing photo removes itself so the placeholder underneath shows through */
    document.addEventListener('error', function (e) {
        var t = e.target;
        if (t && t.tagName === 'IMG' && t.hasAttribute('data-fallback')) t.remove();
    }, true);

    /* "today" can close (Sunday / after 8 PM) while the modal is open */
    setInterval(function () { if (isOpen('bfModal')) { syncSchedule(); onWindowChange(); } }, 60000);

    function init() { paintAll(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();

    window.BorrowFlow = { refreshStock: refreshStock, clear: clearAll, open: openModal };
})();
