/**
 * settings/assets/js/admin-arbitration.js
 *
 * Settings → Arbitration tab (Super Admin only). UI BEHAVIOUR ONLY:
 * steppers, quick presets, the unsaved-changes bar and a couple of
 * show/hide touches. Nothing is sent to the server. Saving is a stub.
 *
 * Every field in the panel carries data-arb-key="<setting key>".
 */
(function () {
    'use strict';

    var root = document.getElementById('sett-arbitration');
    if (!root) return; // not a Super Admin, or the panel is not on the page

    /* ── Values the presets and "Restore" fill in ─────────────────
       DEFAULTS is every setting at its standard value; each preset
       only overrides what it changes. */
    var DEFAULTS = {
        pause_equipment: false, pause_rooms: false, pause_message: '', pause_until: '',

        rule_overdue_block_enabled: true, rule_duplicate_block_enabled: true, rule_missing_doc_block_enabled: false,
        max_items_per_borrower: 3, max_borrow_days: 1, max_extensions: 2,
        auto_approve_timeout_minutes: 1, low_stock_threshold: 2,

        equip_day_start: '07:00', equip_day_end: '21:00', equip_max_advance_days: 14,
        equip_immediate_grace_min: 15, equip_cancel_cutoff_min: 60,
        equip_dur_1: true, equip_dur_2: true, equip_dur_3: true, equip_dur_4: false, equip_dur_5: false, equip_dur_6: true,

        room_max_active: 2, room_max_advance_weeks: 3, room_cancel_cutoff_min: 60,
        room_same_day_auto: true, room_letter_required: false, room_waitlist_enabled: true, room_expiry_mode: 'day',

        room_day_start: '07:00', room_day_end: '20:00',
        room_day_mon: true, room_day_tue: true, room_day_wed: true, room_day_thu: true, room_day_fri: true, room_day_sat: true, room_day_sun: false,
        room_buffer_min: 0, room_max_hours: 13,

        tie_break_window_seconds: 5,
        role_priority_student: 1, role_priority_faculty: 2, role_priority_director: 4
    };

    var PRESETS = {
        standard: {},
        busy: {
            rule_overdue_block_enabled: true, rule_duplicate_block_enabled: true, rule_missing_doc_block_enabled: true,
            max_items_per_borrower: 2, max_borrow_days: 1, max_extensions: 1,
            auto_approve_timeout_minutes: 5, low_stock_threshold: 3,
            equip_max_advance_days: 7, equip_cancel_cutoff_min: 120,
            room_max_active: 1, room_max_advance_weeks: 2, room_cancel_cutoff_min: 120, room_letter_required: true
        },
        relaxed: {
            rule_overdue_block_enabled: false, rule_duplicate_block_enabled: false, rule_missing_doc_block_enabled: false,
            max_items_per_borrower: 5, max_borrow_days: 3, max_extensions: 3,
            auto_approve_timeout_minutes: 1, low_stock_threshold: 1,
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
        syncDerived();
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

    /* Things that depend on other fields: pause message box and counter. */
    function syncDerived() {
        var eq = document.getElementById('arb-pause_equipment');
        var rm = document.getElementById('arb-pause_rooms');
        var paused = !!((eq && eq.checked) || (rm && rm.checked));
        ['arb-pause_message', 'arb-pause_until'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.disabled = !paused;
        });
        var msg = document.getElementById('arb-pause_message');
        var count = document.getElementById('arbPauseCount');
        if (msg && count) count.textContent = String(msg.value.length);
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
        setPresetActive(null);
        syncDerived();
        showBar();
    }
    root.addEventListener('input', onEdit);
    root.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('arb-stepper__input')) clampInput(e.target);
        onEdit(e);
    });

    // Quick presets: only touch the rules the presets are about (limits and toggles),
    // never the pause settings, opening hours, open days or role weights.
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
            setPresetActive(null);
            showBar();
        });
    }

    // Discard: back to what the page loaded with
    var discardBtn = document.getElementById('arbDiscardBtn');
    if (discardBtn) {
        discardBtn.addEventListener('click', function () {
            fill(initial);
            setPresetActive(null);
            hideBar();
        });
    }

    // Save: NOT connected yet. This is the one place to plug the request in later.
    var saveBtn = document.getElementById('arbSaveBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            // TODO: collect values from [data-arb-key] and POST them to the settings API.
            if (!saveText) return;
            resetBarText();
            saveText.innerHTML = '<span class="material-symbols-outlined">info</span> Saving is not connected yet. This is a preview of the screen.';
            saveTimer = setTimeout(resetBarText, 3500);
        });
    }

    syncDerived();
})();
