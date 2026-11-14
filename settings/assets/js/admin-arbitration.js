/**
 * settings/assets/js/admin-arbitration.js
 *
 * Settings → Arbitration tab (Super Admin only).
 *
 * Every field in the panel carries data-arb-key="<setting key>".
 * Fields inside a card marked data-arb-live are SAVED to settings/api/save-arbitration.php.
 * The other cards are still UI only (steppers, presets and the Priority picker work on screen,
 * but nothing is sent to the server for them).
 */
(function () {
    'use strict';

    var root = document.getElementById('sett-arbitration');
    if (!root) return; // not a Super Admin, or the panel is not on the page

    /* ── Values the presets and "Restore" fill in ─────────────────
       DEFAULTS is every setting at its standard value; each preset
       only overrides what it changes. */
    var DEFAULTS = {
        rule_overdue_block_enabled: false, rule_missing_doc_block_enabled: false,
        max_items_per_borrower: 3, max_borrow_days: 3, max_extensions: 2,
        low_stock_threshold: 2,

        equip_day_start: '07:00', equip_day_end: '21:00', equip_max_advance_days: 14,
        equip_immediate_grace_min: 15, equip_cancel_cutoff_min: 60,
        equip_dur_1: true, equip_dur_2: true, equip_dur_3: true, equip_dur_4: false, equip_dur_5: false, equip_dur_6: true,

        room_max_active: 2, room_max_advance_weeks: 3, room_cancel_cutoff_min: 60,
        room_same_day_auto: true, room_letter_required: false, room_expiry_mode: 'day',

        room_day_start: '07:00', room_day_end: '20:00',
        room_day_mon: true, room_day_tue: true, room_day_wed: true, room_day_thu: true, room_day_fri: true, room_day_sat: true, room_day_sun: false,
        room_max_hours: 13,

        role_priority_student: 1, role_priority_faculty: 2, role_priority_director: 4
    };

    var PRESETS = {
        standard: { rule_overdue_block_enabled: true },
        busy: {
            rule_overdue_block_enabled: true, rule_missing_doc_block_enabled: true,
            max_items_per_borrower: 2, max_borrow_days: 1, max_extensions: 1,
            low_stock_threshold: 3,
            equip_max_advance_days: 7, equip_cancel_cutoff_min: 120,
            room_max_active: 1, room_max_advance_weeks: 2, room_cancel_cutoff_min: 120, room_letter_required: true
        },
        relaxed: {
            rule_overdue_block_enabled: false, rule_missing_doc_block_enabled: false,
            max_items_per_borrower: 5, max_borrow_days: 3, max_extensions: 3,
            low_stock_threshold: 1,
            equip_max_advance_days: 21, equip_cancel_cutoff_min: 30,
            room_max_active: 3, room_max_advance_weeks: 4, room_cancel_cutoff_min: 30
        }
    };

    var saveBar = document.getElementById('arbSaveBar');
    var saveText = document.getElementById('arbSaveText');
    var saveTextHtml = saveText ? saveText.innerHTML : '';
    var saveTimer = null;
    var presetBtns = root.querySelectorAll('[data-arb-preset]');
    var applying = false;   // true while a preset / restore / discard fills the form

    function fields() { return root.querySelectorAll('[data-arb-key]'); }

    function readField(el) { return el.type === 'checkbox' ? el.checked : el.value; }

    function writeField(el, val) {
        if (el.type === 'checkbox') el.checked = !!val;
        else el.value = val;
    }

    /* Fields that are saved to the server (inside a data-arb-live card) */
    function isLive(el) { return !!el.closest('[data-arb-live]'); }
    function liveFields() {
        return Array.prototype.filter.call(fields(), isLive);
    }
    function csrfToken() {
        var el = document.querySelector('input[name="csrf_token"]');
        return el ? el.value : '';
    }
    function clearErrors() {
        root.querySelectorAll('.arb-row.has-error').forEach(function (r) { r.classList.remove('has-error'); });
    }
    function markError(key) {
        var el = document.getElementById('arb-' + (key === 'equip_durations' ? 'equip_dur_1' : key));
        var row = el ? el.closest('.arb-row') : null;
        if (row) { row.classList.add('has-error'); }
        return row;
    }

    /* Snapshot of what the page loaded with, so Discard can go back to it. */
    var initial = {};
    fields().forEach(function (el) { initial[el.dataset.arbKey] = readField(el); });

    /* ── Helpers ─────────────────────────────────────────────── */
    function clampInput(input) {
        if (input.type !== 'number') return;
        var min = input.min !== '' ? Number(input.min) : -Infinity;
        var max = input.max !== '' ? Number(input.max) : Infinity;
        var n = parseInt(input.value, 10);
        if (isNaN(n)) n = isFinite(min) ? min : 0;
        input.value = Math.min(max, Math.max(min, n));
    }

    function fill(map) {
        applying = true;
        fields().forEach(function (el) {
            var k = el.dataset.arbKey;
            if (Object.prototype.hasOwnProperty.call(map, k)) writeField(el, map[k]);
        });
        applying = false;
    }

    function setPresetActive(name) {
        presetBtns.forEach(function (b) {
            b.classList.toggle('is-active', b.dataset.arbPreset === name);
        });
    }

    function showBar() {
        if (!saveBar) return;
        resetBarText();
        saveBar.classList.add('is-visible');
    }

    function hideBar() {
        if (saveBar) saveBar.classList.remove('is-visible');
    }

    function resetBarText() {
        if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
        if (saveText) saveText.innerHTML = saveTextHtml;
    }

    /* ── Priority faculty (the Super Admin's own pick, max 10) ──── */
    var prioCard = document.getElementById('arbPriorityCard');
    var prio = { max: 10, chosen: [], initial: [], info: {} };
    var renderPrio = function () {};   // replaced below once the card exists

    if (prioCard) {
        var prioSearch = document.getElementById('arbPrioSearch');
        var prioOptions = document.getElementById('arbPrioOptions');
        var prioNone = document.getElementById('arbPrioNone');
        var prioList = document.getElementById('arbPrioChosen');
        var prioEmpty = document.getElementById('arbPrioEmpty');
        var prioLimit = document.getElementById('arbPrioLimit');
        var prioCount = document.getElementById('arbPrioCount');
        var optBtns = prioOptions ? prioOptions.querySelectorAll('.arb-pick__opt') : [];
        prio.max = parseInt(prioCard.dataset.arbMax, 10) || 10;

        optBtns.forEach(function (b) {
            prio.info[b.dataset.id] = { name: b.dataset.name, dept: b.dataset.dept };
        });

        var initials = function (name) {
            var parts = String(name).trim().split(/\s+/).filter(Boolean);
            if (!parts.length) return '?';
            return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
        };

        renderPrio = function () {
            // chosen list
            prioList.textContent = '';
            prio.chosen.forEach(function (id) {
                var info = prio.info[id] || { name: id, dept: '' };
                var li = document.createElement('li');
                li.className = 'arb-prio__item';

                var av = document.createElement('span');
                av.className = 'arb-prio__av';
                av.textContent = initials(info.name);

                var who = document.createElement('div');
                who.className = 'arb-prio__who';
                var nm = document.createElement('div');
                nm.className = 'arb-prio__name';
                nm.textContent = info.name;
                var meta = document.createElement('div');
                meta.className = 'arb-prio__meta';
                meta.textContent = id + (info.dept ? ' \u00b7 ' + info.dept : '');
                who.appendChild(nm);
                who.appendChild(meta);

                var rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'arb-prio__remove';
                rm.dataset.arbPrioRemove = id;
                rm.setAttribute('aria-label', 'Remove ' + info.name + ' from priority');
                var ico = document.createElement('span');
                ico.className = 'material-symbols-outlined';
                ico.textContent = 'close';
                rm.appendChild(ico);

                li.appendChild(av);
                li.appendChild(who);
                li.appendChild(rm);
                prioList.appendChild(li);
            });

            var full = prio.chosen.length >= prio.max;
            prioCount.textContent = String(prio.chosen.length);
            prioEmpty.hidden = prio.chosen.length > 0;
            prioLimit.hidden = !full;
            prioSearch.disabled = full;
            if (full) prioOptions.hidden = true;
            filterPrio();
        };

        var filterPrio = function () {
            var q = prioSearch.value.trim().toLowerCase();
            var shown = 0;
            optBtns.forEach(function (b) {
                var hide = prio.chosen.indexOf(b.dataset.id) !== -1 || (q && b.dataset.search.toLowerCase().indexOf(q) === -1);
                b.parentNode.hidden = hide;
                if (!hide) shown++;
            });
            if (prioNone) prioNone.hidden = shown > 0;
        };

        var openPicker = function () {
            if (prioSearch.disabled) return;
            prioOptions.hidden = false;
            filterPrio();
        };

        prioSearch.addEventListener('focus', openPicker);
        prioSearch.addEventListener('input', openPicker);
        document.addEventListener('click', function (e) {
            if (!prioCard.contains(e.target)) prioOptions.hidden = true;
        });

        prioOptions.addEventListener('click', function (e) {
            var b = e.target.closest('.arb-pick__opt');
            if (!b || prio.chosen.length >= prio.max || prio.chosen.indexOf(b.dataset.id) !== -1) return;
            prio.chosen.push(b.dataset.id);
            prioSearch.value = '';
            renderPrio();
            prioOptions.hidden = true;
            setPresetActive(null);
            showBar();
        });

        prioList.addEventListener('click', function (e) {
            var rm = e.target.closest('[data-arb-prio-remove]');
            if (!rm) return;
            prio.chosen = prio.chosen.filter(function (id) { return id !== rm.dataset.arbPrioRemove; });
            renderPrio();
            setPresetActive(null);
            showBar();
        });

        renderPrio();
    }

    function setPriority(ids) {
        if (!prioCard) return;
        prio.chosen = ids.slice(0, prio.max);
        prioSearch.value = '';
        prioOptions.hidden = true;
        renderPrio();
    }

    /* ── Events ──────────────────────────────────────────────── */
    // + / − buttons on the steppers
    root.addEventListener('click', function (e) {
        var btn = e.target.closest('.arb-stepper__btn');
        if (!btn || !root.contains(btn)) return;
        var input = btn.parentNode.querySelector('.arb-stepper__input');
        if (!input) return;
        var step = Number(input.step) || 1;
        var n = parseInt(input.value, 10);
        if (isNaN(n)) n = Number(input.min) || 0;
        input.value = n + step * Number(btn.dataset.arbStep);
        clampInput(input);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });

    // Any edit marks the form as changed
    function onEdit(e) {
        if (applying || !e.target.matches('[data-arb-key]')) return;
        clearErrors();
        setPresetActive(null);
        showBar();
    }
    root.addEventListener('input', onEdit);
    root.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('arb-stepper__input')) clampInput(e.target);
        onEdit(e);
    });

    // Quick presets: only touch the rules the presets are about (limits and toggles),
    // never opening hours, open days, role weights or the priority list.
    var PRESET_KEYS = {};
    Object.keys(PRESETS).forEach(function (n) {
        Object.keys(PRESETS[n]).forEach(function (k) { PRESET_KEYS[k] = true; });
    });

    presetBtns.forEach(function (b) {
        b.addEventListener('click', function () {
            var name = b.dataset.arbPreset;
            var map = {};
            Object.keys(PRESET_KEYS).forEach(function (k) { map[k] = DEFAULTS[k]; });
            Object.keys(PRESETS[name] || {}).forEach(function (k) { map[k] = PRESETS[name][k]; });
            fill(map);
            setPresetActive(name);
            showBar();
        });
    });

    // Restore default rules
    var restoreBtn = document.getElementById('arbRestoreBtn');
    if (restoreBtn) {
        restoreBtn.addEventListener('click', function () {
            fill(DEFAULTS);
            setPriority([]);
            setPresetActive(null);
            showBar();
        });
    }

    // Discard: back to what the page loaded with
    var discardBtn = document.getElementById('arbDiscardBtn');
    if (discardBtn) {
        discardBtn.addEventListener('click', function () {
            fill(initial);
            setPriority(prio.initial);
            setPresetActive(null);
            hideBar();
        });
    }

    // Save: sends the fields of the connected cards (data-arb-live) to the server.
    var saveBtn = document.getElementById('arbSaveBtn');
    var saveBtnHtml = saveBtn ? saveBtn.innerHTML : '';

    function say(html, ms) {
        if (!saveText) return;
        resetBarText();
        saveText.innerHTML = html;
        if (ms) saveTimer = setTimeout(function () { resetBarText(); hideBar(); }, ms);
    }

    function collectLive() {
        var settings = {}, durations = [];
        liveFields().forEach(function (el) {
            var k = el.dataset.arbKey;
            if (k.indexOf('equip_dur_') === 0) {
                if (el.checked) durations.push(parseInt(k.slice(10), 10));
                return;
            }
            if (el.type === 'checkbox') settings[k] = el.checked;
            else if (el.type === 'number') settings[k] = parseInt(el.value, 10);
            else settings[k] = el.value;
        });
        settings.equip_durations = durations;
        return settings;
    }

    function applySaved(values) {
        // Show exactly what was stored (for example a value the server adjusted)
        var map = {};
        Object.keys(values).forEach(function (k) {
            if (k === 'equip_durations') {
                [1, 2, 3, 4, 5, 6].forEach(function (n) { map['equip_dur_' + n] = values.equip_durations.indexOf(n) !== -1; });
            } else {
                map[k] = values[k];
            }
        });
        fill(map);
        liveFields().forEach(function (el) { initial[el.dataset.arbKey] = readField(el); });
    }

    function nonLiveChanged() {
        return Array.prototype.some.call(fields(), function (el) {
            return !isLive(el) && readField(el) !== initial[el.dataset.arbKey];
        });
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            if (saveBtn.disabled) return;
            clearErrors();
            var otherEdits = nonLiveChanged() || prio.chosen.length !== prio.initial.length;
            saveBtn.disabled = true;
            saveBtn.innerHTML = 'Saving\u2026';

            fetch('settings/api/save-arbitration.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                body: JSON.stringify({ settings: collectLive() })
            }).then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, body: j }; });
            }).then(function (res) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = saveBtnHtml;
                var j = res.body || {};
                if (res.ok && j.status === 'success') {
                    applySaved(j.values || {});
                    var msg = '<span class="material-symbols-outlined">check_circle</span> ' +
                        (j.changed && j.changed.length ? 'Saved. New requests use these rules now.' : 'Nothing changed.');
                    if (otherEdits) msg += ' Room, role and priority edits are not connected yet, so they were not saved.';
                    say(msg, otherEdits ? 7000 : 3500);
                    return;
                }
                var errs = j.errors || {};
                var firstRow = null;
                Object.keys(errs).forEach(function (k) { var row = markError(k); if (row && !firstRow) firstRow = row; });
                say('<span class="material-symbols-outlined">error</span> ' + (j.error || 'Could not save. Please try again.'), 0);
                if (firstRow && firstRow.scrollIntoView) firstRow.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }).catch(function () {
                saveBtn.disabled = false;
                saveBtn.innerHTML = saveBtnHtml;
                say('<span class="material-symbols-outlined">error</span> Could not reach the server. Please try again.', 0);
            });
        });
    }

})();
