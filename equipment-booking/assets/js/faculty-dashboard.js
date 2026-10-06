(function () {
    'use strict';

    const todayStr = new Date().toISOString().split('T')[0];

    /* ══════════════════════════════════════════════════════════════════
       CSRF — reads the token already embedded in the page (emitted by
       PHP's csrf_field()) so it can be attached to fetch() requests that
       build their own FormData instead of submitting an actual <form>.
    ══════════════════════════════════════════════════════════════════ */
    function getCsrfToken() {
        const el = document.querySelector('input[name="csrf_token"]');
        return el ? el.value : '';
    }

    /* ══════════════════════════════════════════════════════════════════
       CRITICAL: Close all overlays on page load to prevent stuck modals
    ══════════════════════════════════════════════════════════════════ */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.overlay-page.active').forEach(o => o.classList.remove('active'));
        });
    } else {
        document.querySelectorAll('.overlay-page.active').forEach(o => o.classList.remove('active'));
    }

    /* ══════════════════════════════════════════════════════════════════
       STATE PERSISTENCE — localStorage
       Saves settings, account edits, and notification read state so
       everything survives a page reload. Active tab is intentionally
       NOT restored (reload always lands on Home per UX contract).
    ══════════════════════════════════════════════════════════════════ */
    const LS = {
        get: k => {
            try {
                return localStorage.getItem('eq_' + k);
            } catch (e) {
                return null;
            }
        },
        set: (k, v) => {
            try {
                localStorage.setItem('eq_' + k, String(v));
            } catch (e) { }
        },
        del: k => {
            try {
                localStorage.removeItem('eq_' + k);
            } catch (e) { }
        },
        getJ: k => {
            try {
                return JSON.parse(localStorage.getItem('eq_' + k) || 'null');
            } catch (e) {
                return null;
            }
        },
        setJ: (k, v) => {
            try {
                localStorage.setItem('eq_' + k, JSON.stringify(v));
            } catch (e) { }
        }
    };

    /* ── Restore all persisted state on load ─────────────────────── */
    function restorePersistedState() {
        // 1. Theme
        const theme = LS.get('theme');
        if (theme && theme !== 'light') _applyThemeDOM(theme);
        // Sync unified theme dropdown
        const tsu = document.getElementById('themeSelectUnified');
        if (tsu && theme) tsu.value = theme;

        // 2. Accent color — removed from new settings design; kept for backwards compat
        // const ac = LS.get('accentColor'), al = LS.get('accentLight');
        // if (ac) _applyAccentDOM(ac, al || '#f3e5e6');

        // 3. Compact mode — removed from new settings design
        // if (LS.get('compact') === 'true') {
        //     const ct = document.getElementById('compactToggle');
        //     if (ct) ct.checked = true;
        //     document.documentElement.style.setProperty('--radius', '9px');
        // }

        // 4. Font size
        const fs = LS.get('fontSize');
        if (fs && fs !== '100') {
            const fr = document.getElementById('fontSizeRange');
            if (fr) fr.value = fs;
            const lbl = document.getElementById('fontSizeLbl');
            if (lbl) lbl.textContent = fs + '%';
            document.documentElement.style.fontSize = (parseFloat(fs) / 100) + 'rem';
            // Sync font scale buttons
            document.querySelectorAll('.u-font-btn').forEach(b => {
                b.classList.toggle('u-font-btn-active', b.dataset.scale === fs);
            });
        }

        // 5. Reduce motion — removed from new settings design
        // if (LS.get('reduceMotion') === 'true') { ... }

        // 6. Focus ring — removed from new settings design
        // if (LS.get('focusRing') === 'true') { ... }

        // 7. Account profile fields — now driven by DB on page load, NOT localStorage.
        //    (localStorage profile keys are intentionally skipped here so stale cached
        //     values do not override the fresh server-rendered data in the HTML.)

        // 8. Notification read state — now stored server-side per faculty
        //    (tbl_faculty_notif_state); nothing to restore from localStorage.
    }

    /* ── DOM-only helpers (no save, used by restore + public fns) ── */
    function _applyThemeDOM(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        // Remove any JS-set inline tint overrides so the new theme's
        // CSS variable values take over cleanly
        document.documentElement.style.removeProperty('--section-tint-start');
        document.documentElement.style.removeProperty('--section-tint-end');
        const tMap = {
            'light': 'light',
            'dark': 'dark',
            'high-contrast': 'hc'
        };
        ['light', 'dark', 'hc'].forEach(k => {
            const el = document.getElementById('tp-' + k);
            const ch = document.getElementById('tc-' + k);
            if (el) el.classList.remove('selected');
            if (ch) {
                ch.style.display = 'none';
                // Also remove theme-active from the parent sov-theme-option
                const opt = ch.closest('.sov-theme-option');
                if (opt) opt.classList.remove('theme-active');
            }
        });
        const key = tMap[theme] || theme;
        const el = document.getElementById('tp-' + key);
        const ch = document.getElementById('tc-' + key);
        if (el) el.classList.add('selected');
        if (ch) {
            ch.style.display = '';
            // Mark the parent sov-theme-option as active
            const opt = ch.closest('.sov-theme-option');
            if (opt) opt.classList.add('theme-active');
        }
        // Update current theme label
        const lbl = document.getElementById('currentThemeLabel');
        if (lbl) {
            const names = { light: 'Light', dark: 'Dark', 'high-contrast': 'High Contrast' };
            lbl.textContent = names[theme] || theme;
        }
    }

    function _applyAccentDOM(color, light) {
        document.querySelectorAll('.c-dot').forEach(d => d.classList.remove('selected'));
        const dot = document.querySelector('.c-dot[data-color="' + color + '"]');
        if (dot) dot.classList.add('selected');
        document.documentElement.style.setProperty('--accent-maroon', color);
        document.documentElement.style.setProperty('--accent-light', light);
        // Parse hex to rgb for the tint variables so the section header gradient
        // always uses the new accent color at the right opacity — never the old light pastel
        const r = parseInt(color.slice(1, 3), 16),
            g = parseInt(color.slice(3, 5), 16),
            b = parseInt(color.slice(5, 7), 16);
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const isHC = document.documentElement.getAttribute('data-theme') === 'high-contrast';
        const alpha = isHC ? 0.16 : isDark ? 0.13 : 0.09;
        document.documentElement.style.setProperty('--section-tint-start', `rgba(${r},${g},${b},${alpha})`);
        document.documentElement.style.setProperty('--section-tint-end', `rgba(${r},${g},${b},0)`);
    }

    /* ── Toast ─────────────────────────────────────────────────────────── */
    let toastTimer;

    function showToast(msg, type) {
        const t = document.getElementById('app-toast');
        if (!t) return;
        // Colours come from the theme (see "Toast / snackbar" in faculty-dashboard.css),
        // so the toast stays readable in Light, Dark and High Contrast.
        t.style.background = '';
        t.dataset.type = (type === 'error' || type === 'success') ? type : 'info';
        const icon = type === 'error' ? 'error' : (type === 'success' ? 'check_circle' : 'info');
        t.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">' + icon + '</span><span>' + msg + '</span>';
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 2800);
    }

    /* ── Browser Back/Forward Navigation ───────────────────────────────── */
    // We use a lightweight pushState approach: each tab switch or overlay open
    // pushes a state object onto the history stack. popstate restores the UI.
    // Reload always lands on Home because initPage() calls replaceState with
    // the home state and never reads the hash back on load.
    let _navSuppressed = false;

    function _pushNav(state) {
        if (_navSuppressed) return;
        const hash = '#' + state.type + '-' + state.value + (state.sub ? '-' + state.sub : '');
        history.pushState(state, '', hash);
    }

    function _restoreNav(state) {
        _navSuppressed = true;
        document.querySelectorAll('.overlay-page.active').forEach(o => o.classList.remove('active'));
        if (!state || state.type === 'tab') {
            const tab = (state && state.value) ? state.value : 'home';
            _switchTabDOM(tab);
            if (state && state.sub) {
                switchLendingSub(state.sub);
            } else if (tab === 'lending') {
                // No sub in state — default to 'browse' rather than leaving nothing active.
                // (The safety net in _switchTabDOM also catches this, but being explicit here
                // prevents any flash of unstyled sub-nav buttons.)
                switchLendingSub('browse');
            }
        } else if (state.type === 'overlay') {
            _switchTabDOM('home');
            _openOverlayDOM(state.value);
        }
        _navSuppressed = false;
    }


    window.addEventListener('popstate', function (e) {
        _restoreNav(e.state);
    });

    /* ── Account tab (sidebar footer): expands / collapses inline ─────────
       The submenu opens downward inside the sidebar, directly under the
       avatar tab and above Log Out (which always stays at the very bottom). */
    function setAccountMenuOpen(open) {
        const toggle = document.getElementById('navAccountToggle');
        const menu = document.getElementById('navAccountMenu');
        if (!toggle || !menu) return;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        menu.classList.toggle('open', open);
    }

    /* ── Overlays ──────────────────────────────────────────────────────── */

    function _openOverlayDOM(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('active');
        document.querySelectorAll('.overlay-page.active').forEach(o => {
            if (o !== el) o.classList.remove('active');
        });
        // Remove active from all sidebar items first
        document.querySelectorAll('.side-nav-item').forEach(b => b.classList.remove('active'));
        // Highlight the Settings sidebar item when settings overlay is open
        if (id === 'settingsOverlay') {
            const settingsNavItem = document.getElementById('nav-settings');
            if (settingsNavItem) settingsNavItem.classList.add('active');
            // Settings lives inside the account menu, so mark the account toggle as the current item too
            const acctToggle = document.getElementById('navAccountToggle');
            if (acctToggle) acctToggle.classList.add('active');
        }
    }

    function openOverlay(id) {
        _pushNav({
            type: 'overlay',
            value: id
        });
        _openOverlayDOM(id);
        // Close mobile nav if open
        const nav = document.getElementById('sideNav');
        const backdrop = document.getElementById('navBackdrop');
        if (nav) nav.classList.remove('open');
        if (backdrop) backdrop.classList.remove('open');
        document.body.style.overflow = '';
    }

    function closeOverlay(id) {
        // Use history.back() so the browser's forward button also works.
        // We also immediately remove the class for instant visual feedback.
        const el = document.getElementById(id);
        if (el) el.classList.remove('active');
        // Remove active state from all sidebar items
        document.querySelectorAll('.side-nav-item').forEach(b => b.classList.remove('active'));
        // Restore the active state to the current tab
        const activePanel = document.querySelector('.tab-panel.active');
        if (activePanel) {
            const tabName = activePanel.id.replace('panel-', '');
            const tabNavItem = document.querySelector('.side-nav-item[data-tab="' + tabName + '"]');
            if (tabNavItem) tabNavItem.classList.add('active');
        }
        history.back();
    }

    /* ── Main Tab Switcher ─────────────────────────────────────────────── */
    function _switchTabDOM(tabName) {
        const panel = document.getElementById('panel-' + tabName);
        if (panel) panel.classList.add('active');
        // Legacy nav-tab support (kept for compatibility)
        document.querySelectorAll('.nav-tab').forEach(b => b.classList.remove('active'));
        const btn = document.querySelector('.nav-tab[data-tab="' + tabName + '"]');
        if (btn) btn.classList.add('active');
        // New side-nav-item support
        document.querySelectorAll('.side-nav-item[data-tab]').forEach(b => b.classList.remove('active'));
        const sideBtn = document.querySelector('.side-nav-item[data-tab="' + tabName + '"]');
        if (sideBtn) sideBtn.classList.add('active');
        document.querySelectorAll('.tab-panel').forEach(p => {
            if (p !== panel) p.classList.remove('active');
        });

        if (tabName === 'rooms' && window.PUPSyncFacilities) {
            window.PUPSyncFacilities.start();
        }

        /* ── AI chatbot: only visible on the Dashboard tab ─────────────── */
        const aiFab = document.getElementById('actAiFab');
        const aiChat = document.getElementById('actAiChat');
        if (tabName === 'home') {
            if (aiFab) aiFab.style.display = '';
        } else {
            if (aiFab) aiFab.style.display = 'none';
            if (aiChat) {
                aiChat.classList.remove('open');
                aiChat.classList.remove('minimized');
            }
        }

        // SAFETY NET: if the lending panel is now active and no sub-tab has
        // the active class, default to 'browse' to prevent a blank content area.
        if (tabName === 'lending') {
            const hasActiveSub = document.querySelector('#panel-lending .lending-sub.active');
            if (!hasActiveSub) {
                switchLendingSub('browse');
            }
        }
    }

    function switchTab(tabName, sub) {
        _pushNav({
            type: 'tab',
            value: tabName,
            sub: sub || null
        });
        _switchTabDOM(tabName);
    }

    /* ── Lending Sub-Sections ──────────────────────────────────────────── */
    function switchLendingSub(subName) {
        /* Equipment now has a single view. An old bookmark, history entry or
           link that still names a removed section (e.g. "requests") falls back
           to it, so the panel can never end up blank. */
        if (subName !== 'browse') subName = 'browse';
        const sub = document.getElementById('lending-' + subName);
        if (sub) sub.classList.add('active');
        /* Scope to #panel-lending only — prevents wiping the active state of
           the Facilities sub-nav buttons/panes which share the same CSS classes. */
        document.querySelectorAll('#panel-lending .lending-nav-btn').forEach(b => b.classList.remove('active'));
        const btn = document.querySelector('#panel-lending .lending-nav-btn[data-lending-nav="' + subName + '"]');
        if (btn) btn.classList.add('active');
        document.querySelectorAll('#panel-lending .lending-sub').forEach(s => {
            if (s !== sub) s.classList.remove('active');
        });
    }

    /* ── Account Sub-Tabs — not used in unified settings card layout ───── */
    // function switchAccTab(panelId) {
    //     const panel = document.getElementById(panelId);
    //     if (panel) panel.classList.add('active');
    //     document.querySelectorAll('.acc-nav-btn').forEach(b => b.classList.remove('active'));
    //     const btn = document.querySelector('.acc-nav-btn[data-acc-tab="' + panelId + '"]');
    //     if (btn) btn.classList.add('active');
    //     document.querySelectorAll('#accountOverlay .overlay-sub-panel').forEach(p => {
    //         if (p !== panel) p.classList.remove('active');
    //     });
    // }

    /* ── Settings Sub-Tabs — not used in unified settings card layout ──── */
    // function switchSettTab(panelId) {
    //     const panel = document.getElementById(panelId);
    //     if (panel) panel.classList.add('active');
    //     document.querySelectorAll('.s-nav-item').forEach(b => b.classList.remove('active'));
    //     const btn = document.querySelector('.s-nav-item[data-sett-tab="' + panelId + '"]');
    //     if (btn) btn.classList.add('active');
    //     document.querySelectorAll('#settingsOverlay .overlay-sub-panel').forEach(p => {
    //         if (p !== panel) p.classList.remove('active');
    //     });
    // }

    /* ── Equipment Search/Filter + Pagination ──────────────────────────
       12 items per page. The 2 featured cards above the catalog are
       separate markup and are never counted here.
       Filtering always resets you to page 1 so you don't land on an
       empty page after narrowing the results.
    ─────────────────────────────────────────────────────────────────── */
    const EQ_PER_PAGE = 12;
    let eqCurrentPage = 1;

    function getMatchingEquipment() {
        const searchEl = document.getElementById('equipmentSearch');
        const catEl = document.getElementById('categoryFilter');
        const search = (searchEl ? searchEl.value : '').toLowerCase();
        const category = (catEl ? catEl.value : '').toLowerCase();
        return Array.from(document.querySelectorAll('.item-node')).filter(item => {
            const nameMatch = (item.dataset.name || '').includes(search);
            const catMatch = !category || item.dataset.category === category;
            return nameMatch && catMatch;
        });
    }

    function renderEquipmentPage() {
        const matches = getMatchingEquipment();
        const total = matches.length;
        const totalPages = Math.max(1, Math.ceil(total / EQ_PER_PAGE));
        if (eqCurrentPage > totalPages) eqCurrentPage = totalPages;
        if (eqCurrentPage < 1) eqCurrentPage = 1;

        // Hide everything, then reveal only this page's slice
        document.querySelectorAll('.item-node').forEach(el => { el.style.display = 'none'; });
        const start = (eqCurrentPage - 1) * EQ_PER_PAGE;
        matches.slice(start, start + EQ_PER_PAGE).forEach(el => { el.style.display = ''; });

        // Count chip reflects the filtered total, not the page size
        const chip = document.getElementById('equipCountChip');
        if (chip) chip.textContent = total + (total === 1 ? ' item' : ' items');

        // Empty state
        const noRes = document.getElementById('equipNoResults');
        if (noRes) noRes.style.display = total === 0 ? '' : 'none';

        // Pagination bar — hidden when everything fits on one page
        const pager = document.getElementById('equipPagination');
        if (pager) pager.style.display = totalPages > 1 ? '' : 'none';
        if (totalPages <= 1) return;

        const info = document.getElementById('equipPageInfo');
        if (info) {
            info.textContent = 'Showing ' + (start + 1) + '–' +
                Math.min(start + EQ_PER_PAGE, total) + ' of ' + total;
        }

        const prev = document.getElementById('equipPrevBtn');
        const next = document.getElementById('equipNextBtn');
        if (prev) prev.disabled = eqCurrentPage === 1;
        if (next) next.disabled = eqCurrentPage === totalPages;

        // Numbered buttons, windowed so long catalogs don't overflow
        const nums = document.getElementById('equipPageNumbers');
        if (nums) {
            nums.innerHTML = '';
            const pages = [];
            if (totalPages <= 7) {
                for (let i = 1; i <= totalPages; i++) pages.push(i);
            } else {
                pages.push(1);
                let lo = Math.max(2, eqCurrentPage - 1);
                let hi = Math.min(totalPages - 1, eqCurrentPage + 1);
                if (lo > 2) pages.push('…');
                for (let i = lo; i <= hi; i++) pages.push(i);
                if (hi < totalPages - 1) pages.push('…');
                pages.push(totalPages);
            }
            pages.forEach(p => {
                if (p === '…') {
                    const s = document.createElement('span');
                    s.className = 'eq-pg-ellipsis';
                    s.textContent = '…';
                    nums.appendChild(s);
                    return;
                }
                const b = document.createElement('button');
                b.className = 'eq-pg-num' + (p === eqCurrentPage ? ' active' : '');
                b.textContent = p;
                b.addEventListener('click', () => goToEquipmentPage(p));
                nums.appendChild(b);
            });
        }
    }

    function goToEquipmentPage(page) {
        eqCurrentPage = page;
        renderEquipmentPage();
        const card = document.querySelector('#lending-browse .catalog-card');
        if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function filterEquipment() {
        eqCurrentPage = 1;   // new filter => back to page 1
        renderEquipmentPage();
    }

    /* ── Borrow Form ───────────────────────────────────────────────────── */
    /* ================================================================
       BORROW SCHEDULE  (Borrow Request modal: when / how long / how many)
       ----------------------------------------------------------------
       Today  : start time + 1 / 2 / 3 / 5 hours (advisers: custom hours)
       Later  : date (tomorrow … 3 weeks ahead) + start time + 1 / 2 / 3 days
                (advisers: also 5) + quantity (up to the item's TOTAL stock)
       Items that are out right now can still be booked for a later date.
       The server re-checks every rule; this is the friendly front end.
    ================================================================ */
    const BorrowSchedule = (function () {
        const loadedAt = Date.now();
        const pad = n => String(n).padStart(2, '0');
        const meta = () => window.BOOKING_META || { items: {}, rules: {}, adviser: false, today: todayStr, nowMin: 0 };
        const rules = () => Object.assign({
            dayStart: '07:00', dayEnd: '21:00', slot: 30, maxAhead: 21, todayHours: [1, 2, 3, 5],
            adviserMaxHours: 12, futureDays: [1, 2, 3], adviserFutureDays: [1, 2, 3, 5], graceMin: 15
        }, meta().rules || {});
        const toMin = hhmm => { const p = String(hhmm).split(':'); return (+p[0]) * 60 + (+p[1]); };
        const hhmm = min => pad(Math.floor(min / 60)) + ':' + pad(min % 60);
        const nowMin = () => Math.floor((meta().nowMin || 0) + (Date.now() - loadedAt) / 60000);
        const parseYmd = s => { const p = String(s).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); };
        const fmtYmd = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        const addDays = (d, n) => { const x = new Date(d.getTime()); x.setDate(x.getDate() + n); return x; };
        const fmtClock = min => { const h = Math.floor(min / 60) % 24, m = min % 60; return ((h % 12) || 12) + ':' + pad(m) + ' ' + (h < 12 ? 'AM' : 'PM'); };
        const fmtDay = d => d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        const itemMeta = name => (meta().items || {})[name] || null;
        const q = (root, sel) => root.querySelector(sel);

        function setup(root) {
            const st = {
                root: root, mode: 'today', length: null, custom: false, item: null, timer: null, ok: null, forcedOff: false,
                multi: root.dataset.bmMulti === '1', adviser: !!meta().adviser,
                form: root.closest('form'),
                els: {
                    modeInput: q(root, '[data-bm-mode-input]'), durInput: q(root, '[data-bm-duration-input]'),
                    modeBtns: root.querySelectorAll('[data-bm-mode]'), outRow: q(root, '[data-bm-outnow]'),
                    outToggle: q(root, '[data-bm-outnow-toggle]'), fields: q(root, '[data-bm-fields]'),
                    date: q(root, '[data-bm-date]'), dateHint: q(root, '[data-bm-date-hint]'),
                    time: q(root, '[data-bm-time]'), chips: q(root, '[data-bm-chips]'), lengthLabel: q(root, '[data-bm-length-label]'),
                    custom: q(root, '[data-bm-custom]'), customInput: q(root, '[data-bm-custom-input]'),
                    qty: q(root, '[data-bm-qty]'), qtyHint: q(root, '[data-bm-qty-hint]'),
                    summary: q(root, '[data-bm-summary]'), avail: q(root, '[data-bm-avail]'),
                    futureOnly: root.querySelectorAll('[data-bm-future-only]')
                }
            };
            root._bm = st;

            st.els.modeBtns.forEach(b => b.addEventListener('click', () => { if (!b.disabled) setMode(st, b.dataset.bmMode); }));
            st.els.time.addEventListener('change', () => { buildChips(st); refresh(st); });
            if (st.els.date) st.els.date.addEventListener('change', () => refresh(st));
            st.els.chips.addEventListener('click', e => {
                const c = e.target.closest('[data-len]'); if (!c || c.disabled) return;
                if (c.dataset.len === 'custom') { st.custom = true; st.length = parseInt(st.els.customInput.value, 10) || null; if (!st.length) { st.els.customInput.value = Math.min(6, maxCustomHours(st)); st.length = parseInt(st.els.customInput.value, 10); } }
                else { st.custom = false; st.length = parseInt(c.dataset.len, 10); }
                buildChips(st); refresh(st);
            });
            if (st.els.customInput) st.els.customInput.addEventListener('input', () => { st.length = parseInt(st.els.customInput.value, 10) || null; refresh(st); });
            if (st.els.qty) {
                root.querySelector('[data-bm-qty-dec]').addEventListener('click', () => stepQty(st, -1));
                root.querySelector('[data-bm-qty-inc]').addEventListener('click', () => stepQty(st, 1));
                st.els.qty.addEventListener('input', () => { clampQty(st); refresh(st); });
            }
            if (st.els.outToggle) st.els.outToggle.addEventListener('change', () => {
                st.forcedOff = !st.els.outToggle.checked;
                st.els.fields.classList.toggle('is-off', st.forcedOff);
                st.els.fields.querySelectorAll('input,select,button').forEach(x => { if (x !== st.els.outToggle) x.disabled = st.forcedOff && !x.closest('[data-bm-chips]') ? true : x.disabled; });
                if (!st.forcedOff) { setMode(st, 'future'); }
                refresh(st);
            });
            if (st.multi && st.form) {
                st.form.addEventListener('change', e => {
                    if (e.target.matches('input[name="items[]"]')) { syncRows(st); refresh(st); }
                });
                st.form.addEventListener('click', e => {
                    const dec = e.target.closest('[data-bm-iq-dec]'), inc = e.target.closest('[data-bm-iq-inc]');
                    if (!dec && !inc) return;
                    e.preventDefault();
                    const inp = (dec || inc).closest('[data-bm-item-qty]').querySelector('input');
                    inp.value = Math.max(+inp.min || 1, Math.min(+inp.max || 1, (parseInt(inp.value, 10) || 1) + (inc ? 1 : -1)));
                    refresh(st);
                });
                st.form.addEventListener('input', e => {
                    if (e.target.closest('[data-bm-item-qty]')) { const i = e.target; i.value = Math.max(+i.min || 1, Math.min(+i.max || 1, parseInt(i.value, 10) || 1)); refresh(st); }
                });
            }
            if (st.form) st.form.addEventListener('submit', e => {
                const err = validate(st);
                if (err) { e.preventDefault(); e.stopImmediatePropagation(); showError(st, err); }
            }, true);
            reset(st);
        }

        /* ---- item + state helpers ------------------------------------ */
        function singleItem(st) { return st.item; }
        function selectedItems(st) {
            if (!st.multi) return st.item ? [{ name: st.item, qty: currentQty(st) }] : [];
            return Array.from(st.form.querySelectorAll('input[name="items[]"]:checked')).map(cb => {
                const row = cb.closest('[data-bm-item]'); const qi = row && row.querySelector('[data-bm-item-qty] input');
                return { name: cb.value, qty: st.mode === 'future' && qi ? (parseInt(qi.value, 10) || 1) : 1 };
            });
        }
        function currentQty(st) { return st.mode === 'future' && st.els.qty ? (parseInt(st.els.qty.value, 10) || 1) : 1; }
        function maxCustomHours(st) {
            const start = toMin(st.els.time.value || rules().dayStart);
            return Math.max(1, Math.min(rules().adviserMaxHours, Math.floor((toMin(rules().dayEnd) - start) / 60)));
        }

        /* ---- mode ----------------------------------------------------- */
        function setMode(st, mode) {
            st.mode = mode; st.length = null; st.custom = false; st.ok = null;
            st.els.modeInput.value = mode;
            st.els.modeBtns.forEach(b => { const on = b.dataset.bmMode === mode; b.classList.toggle('active', on); b.setAttribute('aria-selected', on ? 'true' : 'false'); });
            st.els.futureOnly.forEach(el => { el.hidden = mode !== 'future'; });
            const r = rules(), today = parseYmd(meta().today || todayStr);
            if (st.els.date) {
                st.els.date.min = fmtYmd(addDays(today, 1));
                st.els.date.max = fmtYmd(addDays(today, r.maxAhead));
                st.els.date.required = mode === 'future';
                if (mode === 'today') st.els.date.value = '';
                if (st.els.dateHint) st.els.dateHint.textContent = 'Tomorrow up to ' + Math.round(r.maxAhead / 7) + ' weeks ahead (latest ' + fmtDay(addDays(today, r.maxAhead)) + ').';
            }
            st.els.lengthLabel.textContent = mode === 'today' ? 'For how long?' : 'For how many days?';
            buildTimes(st); buildChips(st);
            if (st.multi) syncRows(st);
            if (st.els.qty) clampQty(st, true);
            refresh(st);
        }

        function buildTimes(st) {
            const r = rules(), sel = st.els.time, prev = sel.value;
            let start = toMin(r.dayStart); const end = toMin(r.dayEnd), step = +r.slot || 30;
            const opts = [];
            if (st.mode === 'today') {
                const now = nowMin();
                if (now >= start && now < end - 30) opts.push({ v: hhmm(now), t: 'Now (' + fmtClock(now) + ')' });
                start = Math.max(start, Math.ceil((now + 1) / step) * step);
            }
            for (let m = start; m < end; m += step) opts.push({ v: hhmm(m), t: fmtClock(m) });
            sel.innerHTML = '';
            if (!opts.length) { const o = document.createElement('option'); o.value = ''; o.textContent = 'No start times left today'; o.disabled = true; o.selected = true; sel.appendChild(o); return; }
            opts.forEach(o => { const e = document.createElement('option'); e.value = o.v; e.textContent = o.t; sel.appendChild(e); });
            const keep = opts.find(o => o.v === prev);
            sel.value = keep ? keep.v : (st.mode === 'future' ? (opts.find(o => o.v === '09:00') || opts[0]).v : opts[0].v);
        }

        function buildChips(st) {
            const r = rules(), box = st.els.chips; box.innerHTML = '';
            const startMin = st.els.time.value ? toMin(st.els.time.value) : toMin(r.dayStart);
            const list = st.mode === 'today' ? r.todayHours : (st.adviser ? r.adviserFutureDays : r.futureDays);
            list.forEach(n => {
                const b = document.createElement('button'); b.type = 'button'; b.className = 'bm-chip'; b.dataset.len = n;
                b.textContent = st.mode === 'today' ? n + (n === 1 ? ' hr' : ' hrs') : n + (n === 1 ? ' day' : ' days');
                const tooLong = st.mode === 'today' && (startMin + n * 60 > toMin(r.dayEnd));
                b.disabled = tooLong; if (tooLong && st.length === n && !st.custom) st.length = null;
                const on = !st.custom && st.length === n;
                b.classList.toggle('active', on); b.setAttribute('role', 'radio'); b.setAttribute('aria-checked', on ? 'true' : 'false');
                box.appendChild(b);
            });
            if (st.mode === 'today' && st.adviser) {
                const b = document.createElement('button'); b.type = 'button'; b.className = 'bm-chip bm-chip-custom'; b.dataset.len = 'custom'; b.textContent = 'Custom';
                b.classList.toggle('active', st.custom); b.setAttribute('role', 'radio'); b.setAttribute('aria-checked', st.custom ? 'true' : 'false');
                box.appendChild(b);
            }
            st.els.custom.hidden = !(st.custom && st.mode === 'today');
            if (st.custom) { st.els.customInput.max = maxCustomHours(st); if (st.length > maxCustomHours(st)) { st.length = maxCustomHours(st); st.els.customInput.value = st.length; } }
        }

        /* ---- quantity (Later bookings) -------------------------------- */
        function itemTotal(st) { const m = itemMeta(st.item); return m ? Math.max(0, m.total) : 0; }
        function clampQty(st, silent) {
            if (!st.els.qty) return;
            const max = Math.max(1, itemTotal(st));
            st.els.qty.max = max; if (!st.els.qty.value || +st.els.qty.value < 1) st.els.qty.value = 1;
            if (+st.els.qty.value > max) st.els.qty.value = max;
            if (st.els.qtyHint) st.els.qtyHint.textContent = st.item ? 'Up to ' + itemTotal(st) + ' in stock — counted from total stock, not what is on the shelf today.' : '';
            const wrap = st.root.querySelector('[data-bm-qty-wrap]');
            if (wrap) { wrap.querySelector('[data-bm-qty-dec]').disabled = +st.els.qty.value <= 1; wrap.querySelector('[data-bm-qty-inc]').disabled = +st.els.qty.value >= max; }
        }
        function stepQty(st, d) { st.els.qty.value = (parseInt(st.els.qty.value, 10) || 1) + d; clampQty(st); refresh(st); }

        /* ---- adviser checklist rows ----------------------------------- */
        function syncRows(st) {
            st.form.querySelectorAll('[data-bm-item]').forEach(row => {
                const cb = row.querySelector('input[name="items[]"]'), shelf = +row.dataset.shelf, total = +row.dataset.total;
                const blocked = (st.mode === 'today' && shelf < 1) || total < 1;
                if (blocked && cb.checked) cb.checked = false;
                cb.disabled = blocked; row.classList.toggle('is-out', blocked);
                const stat = row.querySelector('[data-bm-stock]');
                if (stat) stat.textContent = total < 1 ? '(no stock)' : (blocked ? '(out now – use Later date)' : (shelf > 0 ? '(' + shelf + ' available)' : '(out now)'));
                const qw = row.querySelector('[data-bm-item-qty]'), qi = qw && qw.querySelector('input');
                const show = cb.checked && st.mode === 'future';
                if (qw) { qw.hidden = !show; qi.disabled = !show; qi.max = Math.max(1, total); if (+qi.value > +qi.max) qi.value = qi.max; }
            });
        }

        /* ---- summary + live availability ------------------------------ */
        function windowOf(st) {
            if (!st.length || !st.els.time.value) return null;
            const startMin = toMin(st.els.time.value);
            if (st.mode === 'today') {
                const d = parseYmd(meta().today || todayStr);
                return { start: d, startMin: startMin, endDate: d, endMin: startMin + st.length * 60 };
            }
            if (!st.els.date.value) return null;
            const d = parseYmd(st.els.date.value);
            return { start: d, startMin: startMin, endDate: addDays(d, st.length), endMin: startMin };
        }
        function refresh(st) {
            const w = windowOf(st);
            st.els.durInput.value = st.length || '';
            if (st.els.qty) clampQty(st);
            if (!w) { st.els.summary.textContent = ''; st.els.summary.classList.remove('has'); setAvail(st, '', ''); st.ok = null; return; }
            const dayLabel = d => (st.mode === 'today' && d === w.start) ? 'Today' : fmtDay(d);
            st.els.summary.innerHTML = '<span class="material-symbols-outlined">schedule</span><span><strong>' + dayLabel(w.start) + ' · ' + fmtClock(w.startMin) + '</strong> → <strong>' + (st.mode === 'today' ? fmtClock(w.endMin) : fmtDay(w.endDate) + ' · ' + fmtClock(w.endMin)) + '</strong></span>';
            st.els.summary.classList.add('has');
            clearTimeout(st.timer); st.timer = setTimeout(() => check(st, w), 350);
        }
        function setAvail(st, msg, cls) { st.els.avail.textContent = msg; st.els.avail.className = 'bm-avail' + (cls ? ' ' + cls : ''); }

        function check(st, w) {
            const items = selectedItems(st); if (!items.length) { setAvail(st, '', ''); st.ok = null; return; }
            if (st.mode === 'future' && !st.els.date.value) return;
            const params = new URLSearchParams({ mode: st.mode, date: st.mode === 'future' ? st.els.date.value : '', time: st.els.time.value, duration: String(st.length), items: JSON.stringify(items.map(i => ({ n: i.name, q: i.qty }))) });
            setAvail(st, 'Checking availability…', 'busy');
            const token = st._tok = (st._tok || 0) + 1;
            fetch('equipment-booking/api/check-booking-availability.php?' + params.toString(), { credentials: 'same-origin' })
                .then(r => r.json()).then(j => {
                    if (token !== st._tok) return;
                    if (!j || j.ok !== true) { st.ok = false; setAvail(st, (j && j.error) || 'That schedule is not allowed.', 'bad'); return; }
                    const bad = (j.results || []).filter(x => !x.ok);
                    st.ok = bad.length === 0;
                    if (st.multi) {
                        (j.results || []).forEach(x => {
                            const row = Array.from(st.form.querySelectorAll('[data-bm-item]')).find(r => r.dataset.bmItem === x.name);
                            const s = row && row.querySelector('[data-bm-item-status]');
                            if (s) { s.textContent = x.ok ? '✓' : '✗'; s.title = x.message; s.className = 'bm-item-status ' + (x.ok ? 'ok' : 'bad'); }
                        });
                        setAvail(st, st.ok ? 'All selected items are free for that schedule.' : bad.map(x => x.name + ': ' + x.message).join('  '), st.ok ? 'ok' : 'bad');
                    } else {
                        setAvail(st, (j.results[0] || {}).message || '', st.ok ? 'ok' : 'bad');
                    }
                }).catch(() => { st.ok = null; setAvail(st, '', ''); });
        }

        /* ---- validation + errors -------------------------------------- */
        function validate(st) {
            const items = selectedItems(st);
            if (!items.length) return st.multi ? 'Please select at least one item.' : 'No equipment selected.';
            if (st.forcedOff) return 'Turn on “Out right now” to book this item for a later date.';
            if (st.mode === 'future') {
                if (!st.els.date.value) return 'Please choose a borrow date.';
                if (st.els.date.min && st.els.date.value < st.els.date.min) return 'Later bookings start tomorrow or after. Use “Today” for same-day use.';
                if (st.els.date.max && st.els.date.value > st.els.date.max) return 'You can only book up to ' + Math.round(rules().maxAhead / 7) + ' weeks ahead.';
            }
            if (!st.els.time.value) return 'Please choose a start time.';
            if (!st.length) return st.mode === 'today' ? 'Please choose how long you need it.' : 'Please choose how many days.';
            if (st.custom && (st.length < 1 || st.length > maxCustomHours(st))) return 'Custom length must be between 1 and ' + maxCustomHours(st) + ' hours.';
            if (st.ok === false) return st.els.avail.textContent || 'That schedule is not available.';
            return null;
        }
        function showError(st, msg) { setAvail(st, msg, 'bad'); if (typeof showToast === 'function') showToast(msg, 'error'); st.els.avail.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }

        /* ---- public --------------------------------------------------- */
        function reset(st) {
            st.length = null; st.custom = false; st.forcedOff = false; st.ok = null;
            if (st.els.customInput) st.els.customInput.value = '';
            if (st.els.qty) st.els.qty.value = 1;
            if (st.els.outToggle) st.els.outToggle.checked = true;
            st.els.fields.classList.remove('is-off');
            st.els.fields.querySelectorAll('input,select,button').forEach(x => { x.disabled = false; });
            if (st.els.date) st.els.date.value = '';
            const allowToday = !st.item || (itemMeta(st.item) || {}).shelf > 0 || st.multi;
            const outNow = !st.multi && st.item && !allowToday;
            if (st.els.outRow) st.els.outRow.hidden = !outNow;
            st.els.modeBtns.forEach(b => { if (b.dataset.bmMode === 'today') b.disabled = outNow; });
            if (st.multi) { st.form.querySelectorAll('input[name="items[]"]').forEach(cb => { cb.checked = false; }); st.form.querySelectorAll('[data-bm-item-status]').forEach(s => { s.textContent = ''; s.className = 'bm-item-status'; }); }
            setMode(st, outNow ? 'future' : 'today');
            setAvail(st, '', '');
        }

        return {
            init: function () { document.querySelectorAll('[data-bm-sched]').forEach(setup); },
            open: function (itemName) {
                document.querySelectorAll('[data-bm-sched]').forEach(root => { const st = root._bm; if (!st) return; if (!st.multi) st.item = itemName; reset(st); });
            },
            resetAll: function () { document.querySelectorAll('[data-bm-sched]').forEach(root => { const st = root._bm; if (st) reset(st); }); },
            setItemMeta: function () { document.querySelectorAll('[data-bm-sched]').forEach(root => { const st = root._bm; if (st && !st.multi) { clampQty(st); } }); }
        };
    })();
    window.BorrowSchedule = BorrowSchedule;

    function openBorrowForm(itemName) {
        document.getElementById('selectedItem').value = itemName;
        document.getElementById('selectedItemLabel').textContent = itemName;
        const modal = document.getElementById('borrowModal');
        if (modal) {
            modal.style.display = 'flex';
            // Reset the form fields each time the modal opens
            const form = document.getElementById('borrowForm');
            if (form) {
                const roomSel = form.querySelector('select[name="room_id"]');
                if (roomSel) roomSel.selectedIndex = 0;
                const fileInp = document.getElementById('request_document');
                if (fileInp) fileInp.value = '';
                syncBorrowDrops();
            }
            // when / how long / how many (also decides Today vs "Later date only" for items that are out)
            BorrowSchedule.open(itemName);
        }
    }

    function closeBorrowModal() {
        const modal = document.getElementById('borrowModal');
        if (modal) modal.style.display = 'none';
    }

    /* Borrow modal: show the chosen file's name inside the styled drop area */
    function syncBorrowDrops() {
        document.querySelectorAll('.bm-drop').forEach(function (drop) {
            const inp = drop.querySelector('input[type="file"]');
            const title = drop.querySelector('[data-bm-drop-title]');
            const icon = drop.querySelector('.bm-drop-icon');
            if (!inp || !title) return;
            if (!title.dataset.defaultText) title.dataset.defaultText = title.textContent;
            const file = inp.files && inp.files[0];
            title.textContent = file ? file.name : title.dataset.defaultText;
            drop.classList.toggle('has-file', !!file);
            if (icon) icon.textContent = file ? 'description' : 'upload_file';
        });
    }
    window.syncBorrowDrops = syncBorrowDrops;

    document.addEventListener('change', function (e) {
        const inp = e.target;
        if (!inp || !inp.matches || !inp.matches('.bm-drop input[type="file"]')) return;
        syncBorrowDrops();
        const group = inp.closest('.form-group');
        const err = group && group.querySelector('.bm-error');
        if (err && inp.files && inp.files.length) err.style.display = 'none';
    });

    /* ── Room Form ─────────────────────────────────────────────────────── */
    function openRoomForm(roomName) {
        document.getElementById('selectedRoomLabel').textContent = roomName;
        const sec = document.getElementById('room-form-section');
        if (sec) {
            sec.classList.remove('hidden');
            sec.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    }

    function closeRoomForm() {
        const sec = document.getElementById('room-form-section');
        if (sec) sec.classList.add('hidden');
    }

    /* ── Notifications (live, server-driven) ───────────────────────────
       The feed is built in PHP from this faculty member's own rows
       (equipment-booking/core/faculty-notif-functions.php) and embedded
       as window.FNOTIF_DATA. Read/deleted state is persisted per faculty
       via equipment-booking/api/faculty-notif.php. UI updates are
       optimistic; refreshNotifs() re-syncs with the server (on open, on a
       timer, and whenever an action fails).

       Card interactions:
         click card      → expand details + mark as read
         trash icon /    → delete that one notification
         Delete button
       Footer (Mark all as read · Select · Delete all):
         Select          → checkboxes (shift-click = range), then bulk
                           Read / Unread / Delete selected
         Delete all      → only appears once Select is on; deletes
                           everything in the current filter
    ─────────────────────────────────────────────────────────────────── */
    const NOTIF_PER_PAGE = 5;
    const NOTIF_API = 'equipment-booking/api/faculty-notif.php';
    const NOTIF_GROUP_LABELS = {
        overdue: 'Overdue — Immediate action needed',
        today: 'Today',
        yesterday: 'Yesterday',
        week: 'Earlier this week',
        earlier: 'Earlier'
    };
    const NOTIF_CAT_LABELS = { overdue: 'overdue', borrow: 'borrow', room: 'room', system: 'system' };

    let notifCurrentPage = 1;
    let notifActiveCat = 'all';
    let notifItems = (window.FNOTIF_DATA && Array.isArray(window.FNOTIF_DATA.notifications))
        ? window.FNOTIF_DATA.notifications.slice() : [];
    let notifSelectMode = false;
    let notifPendingConfirm = null;   // { keys: [...], text: '...' } while the delete strip is open
    let notifLastAnchor = null;       // last clicked key, for shift-click range select
    let notifBusy = 0;                // in-flight mutations (polling pauses while > 0)
    const notifExpanded = new Set();
    const notifSelected = new Set();

    function nEl(tag, cls, text) {
        const el = document.createElement(tag);
        if (cls) el.className = cls;
        if (text !== undefined && text !== null) el.textContent = text;
        return el;
    }
    function nIcon(name) { return nEl('span', 'material-symbols-outlined', name); }
    function nFind(key) { return notifItems.find(n => n.key === key); }
    function nPlural(n, word) { return n + ' ' + word + (n === 1 ? '' : 's'); }

    /* Server escapes every dynamic value already; this is defence in depth —
       only text and <strong> survive, anything else is unwrapped. */
    function nSetRichText(el, html) {
        const doc = new DOMParser().parseFromString('<body>' + (html || '') + '</body>', 'text/html');
        (function walk(src, dst) {
            src.childNodes.forEach(node => {
                if (node.nodeType === 3) {
                    dst.appendChild(document.createTextNode(node.nodeValue));
                } else if (node.nodeType === 1 && node.tagName === 'STRONG') {
                    const s = document.createElement('strong');
                    walk(node, s);
                    dst.appendChild(s);
                } else if (node.nodeType === 1) {
                    walk(node, dst);
                }
            });
        })(doc.body, el);
    }

    function getMatchingNotifs(cat) {
        return notifItems.filter(n => {
            if (cat === 'all') return true;
            if (cat === 'unread') return !n.is_read;
            return n.cat === cat;
        });
    }

    /* ── Server calls ─────────────────────────────────────────────── */
    function notifRequest(action, keys) {
        const fd = new FormData();
        fd.append('csrf_token', getCsrfToken());
        fd.append('action', action);
        (keys || []).forEach(k => fd.append('keys[]', k));
        notifBusy++;
        return fetch(NOTIF_API, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json().catch(() => ({})).then(j => {
                if (!r.ok || j.status !== 'success') throw new Error(j.message || j.error || 'Request failed');
                return j;
            }))
            .finally(() => { notifBusy--; });
    }

    function refreshNotifs() {
        if (notifBusy > 0) return Promise.resolve();
        return fetch(NOTIF_API + '?action=list', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : Promise.reject(new Error('list failed')))
            .then(j => {
                if (!j || j.status !== 'success' || !Array.isArray(j.notifications)) return;
                if (notifBusy > 0) return;   // an action started while this was in flight — its result is newer
                notifItems = j.notifications;
                const live = new Set(notifItems.map(n => n.key));
                Array.from(notifExpanded).forEach(k => { if (!live.has(k)) notifExpanded.delete(k); });
                Array.from(notifSelected).forEach(k => { if (!live.has(k)) notifSelected.delete(k); });
                renderNotifPage();
            })
            .catch(() => { /* offline / session expired — keep what we have */ });
    }

    /* ── Mutations (optimistic) ───────────────────────────────────── */
    function setNotifsRead(keys, read) {
        const changed = keys.filter(k => { const n = nFind(k); return n && n.is_read !== read; });
        if (!changed.length) return Promise.resolve(true);
        changed.forEach(k => { nFind(k).is_read = read; });
        renderNotifPage();
        return notifRequest(read ? 'mark_read' : 'mark_unread', changed)
            .then(() => true)
            .catch(() => {
                showToast('Could not update notifications. Please try again.');
                refreshNotifs();
                return false;
            });
    }

    function deleteNotifs(keys) {
        const gone = new Set(keys.filter(k => nFind(k)));
        if (!gone.size) return Promise.resolve(true);
        notifItems = notifItems.filter(n => !gone.has(n.key));
        gone.forEach(k => { notifExpanded.delete(k); notifSelected.delete(k); });
        renderNotifPage();
        return notifRequest('delete', Array.from(gone))
            .then(() => {
                showToast(gone.size === 1 ? 'Notification deleted.' : nPlural(gone.size, 'notification') + ' deleted.');
                return true;
            })
            .catch(() => {
                showToast('Could not delete. Please try again.');
                refreshNotifs();
                return false;
            });
    }

    function markAllRead() {
        const keys = notifItems.filter(n => !n.is_read).map(n => n.key);
        if (!keys.length) { showToast('You\u2019re all caught up.'); return; }
        setNotifsRead(keys, true).then(ok => { if (ok) showToast('All notifications marked as read.'); });
    }

    /* ── Expand / select ──────────────────────────────────────────── */
    function toggleNotifExpand(key) {
        if (notifExpanded.has(key)) notifExpanded.delete(key); else notifExpanded.add(key);
        const n = nFind(key);
        if (n && !n.is_read) { setNotifsRead([key], true); return; }   // re-renders
        renderNotifPage();
    }

    function toggleNotifSelect(key, range) {
        if (range && notifLastAnchor) {
            const list = getMatchingNotifs(notifActiveCat);
            const a = list.findIndex(n => n.key === notifLastAnchor);
            const b = list.findIndex(n => n.key === key);
            if (a > -1 && b > -1) {
                for (let i = Math.min(a, b); i <= Math.max(a, b); i++) notifSelected.add(list[i].key);
                renderNotifPage();
                return;
            }
        }
        if (notifSelected.has(key)) notifSelected.delete(key); else notifSelected.add(key);
        notifLastAnchor = key;
        renderNotifPage();
    }

    function enterNotifSelectMode() {
        notifSelectMode = true;
        cancelNotifConfirm(true);
        renderNotifPage();
    }
    function exitNotifSelectMode() {
        notifSelectMode = false;
        notifSelected.clear();
        notifLastAnchor = null;
        cancelNotifConfirm(true);
        renderNotifPage();
    }
    function toggleNotifSelectAll() {
        const list = getMatchingNotifs(notifActiveCat);
        const allSel = list.length > 0 && list.every(n => notifSelected.has(n.key));
        list.forEach(n => { if (allSel) notifSelected.delete(n.key); else notifSelected.add(n.key); });
        renderNotifPage();
    }

    /* ── Bulk delete confirmation strip ───────────────────────────── */
    function askNotifDelete(keys, text) {
        if (!keys.length) return;
        notifPendingConfirm = { keys: keys.slice(), text: text };
        renderNotifPage();
    }
    function cancelNotifConfirm(silent) {
        if (!notifPendingConfirm) return;
        notifPendingConfirm = null;
        if (!silent) renderNotifPage();
    }
    function confirmNotifDelete() {
        if (!notifPendingConfirm) return;
        const keys = notifPendingConfirm.keys;
        notifPendingConfirm = null;
        deleteNotifs(keys);
    }
    function resetNotifTransient() {
        notifSelectMode = false;
        notifSelected.clear();
        notifLastAnchor = null;
        notifPendingConfirm = null;
    }

    /* ── Navigate from an expanded card to the relevant screen ────── */
    function goToNotifLink(n) {
        const l = n && n.link;
        if (!l || !l.tab) return;
        let tab = l.tab;
        let sub = l.sub || null;
        // My Requests / My Reservations are no longer inner tabs; links that
        // used to open them now open My Activity.
        if ((tab === 'lending' && sub === 'requests') || (tab === 'rooms' && sub === 'history')) {
            tab = 'activity';
            sub = null;
        }
        closeNotifModal();
        switchTab(tab, tab === 'lending' ? sub : null);
        if (tab === 'lending' && sub) switchLendingSub(sub);
    }

    /* ── Rendering ────────────────────────────────────────────────── */
    function buildNotifCard(n) {
        const expanded = notifExpanded.has(n.key) && !notifSelectMode;
        const selected = notifSelected.has(n.key);
        const card = nEl('div', 'notif-card' + (n.is_read ? '' : ' unread') + (n.urgent ? ' notif-card-overdue' : '')
            + (expanded ? ' is-expanded' : '') + (selected ? ' is-selected' : ''));
        card.dataset.key = n.key;
        card.dataset.cat = n.cat;
        card.tabIndex = 0;
        if (notifSelectMode) {
            card.setAttribute('role', 'checkbox');
            card.setAttribute('aria-checked', selected ? 'true' : 'false');
        }

        const chk = nEl('span', 'fnotif-check');
        chk.setAttribute('aria-hidden', 'true');
        chk.appendChild(nIcon('check'));
        card.appendChild(chk);

        const ic = nEl('div', 'notif-card-icon ' + (n.icon_class || 'ni-alert'));
        ic.appendChild(nIcon(n.icon || 'notifications'));
        card.appendChild(ic);

        const body = nEl('div', 'notif-card-body');
        const titleRow = nEl('div', 'fnotif-title-row');
        if (!n.is_read) titleRow.appendChild(nEl('span', 'unread-dot'));
        titleRow.appendChild(nEl('div', 'notif-card-title', n.title));
        body.appendChild(titleRow);
        const sub = nEl('div', 'notif-card-sub');
        nSetRichText(sub, n.body);
        body.appendChild(sub);

        const det = nEl('div', 'fnotif-detail');
        const dl = nEl('dl', 'fnotif-dl');
        (n.detail || []).forEach(d => {
            const row = nEl('div', 'fnotif-dl-row');
            row.appendChild(nEl('dt', null, d.label));
            row.appendChild(nEl('dd', d.danger ? 'is-danger' : null, String(d.value)));
            dl.appendChild(row);
        });
        // Short notices (e.g. the static System ones) have no detail rows — just show the actions
        if ((n.detail || []).length) det.appendChild(dl); else det.classList.add('is-plain');

        const acts = nEl('div', 'fnotif-actions');
        if (n.link && n.link.tab) {
            const v = nEl('button', 'btn-save-acc fnotif-act-btn');
            v.type = 'button';
            v.dataset.nact = 'view';
            v.appendChild(nIcon('open_in_new'));
            v.appendChild(document.createTextNode(' ' + (n.link.label || 'View')));
            acts.appendChild(v);
        }
        const tr = nEl('button', 'btn-cancel-acc fnotif-act-btn');
        tr.type = 'button';
        tr.dataset.nact = 'toggle-read';
        tr.appendChild(nIcon(n.is_read ? 'mark_email_unread' : 'drafts'));
        tr.appendChild(document.createTextNode(n.is_read ? ' Mark as unread' : ' Mark as read'));
        acts.appendChild(tr);
        const del = nEl('button', 'btn-cancel-acc fnotif-act-btn fnotif-act-danger');
        del.type = 'button';
        del.dataset.nact = 'delete';
        del.appendChild(nIcon('delete'));
        del.appendChild(document.createTextNode(' Delete'));
        acts.appendChild(del);
        det.appendChild(acts);
        body.appendChild(det);
        card.appendChild(body);

        const meta = nEl('div', 'notif-card-meta');
        meta.appendChild(nEl('span', 'notif-time', n.time_label || ''));
        const row = nEl('div', 'fnotif-meta-row');
        const dbtn = nEl('button', 'fnotif-icon-btn fnotif-del');
        dbtn.type = 'button';
        dbtn.dataset.nact = 'delete';
        dbtn.setAttribute('aria-label', 'Delete notification');
        dbtn.appendChild(nIcon('delete'));
        row.appendChild(dbtn);
        const ex = nEl('button', 'fnotif-icon-btn fnotif-expand');
        ex.type = 'button';
        ex.dataset.nact = 'expand';
        ex.setAttribute('aria-label', expanded ? 'Collapse details' : 'Show details');
        ex.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        ex.appendChild(nIcon('expand_more'));
        row.appendChild(ex);
        meta.appendChild(row);
        card.appendChild(meta);
        return card;
    }

    function renderNotifPager(total, totalPages, start) {
        const pager = document.getElementById('notifPagination');
        if (pager) pager.style.display = totalPages > 1 ? '' : 'none';
        if (totalPages <= 1) return;

        const info = document.getElementById('notifPageInfo');
        if (info) {
            info.textContent = 'Showing ' + (start + 1) + '\u2013' +
                Math.min(start + NOTIF_PER_PAGE, total) + ' of ' + total;
        }
        const prev = document.getElementById('notifPrevBtn');
        const next = document.getElementById('notifNextBtn');
        if (prev) prev.disabled = notifCurrentPage === 1;
        if (next) next.disabled = notifCurrentPage === totalPages;

        const nums = document.getElementById('notifPageNumbers');
        if (!nums) return;
        nums.innerHTML = '';
        const pages = [];
        if (totalPages <= 7) {
            for (let i = 1; i <= totalPages; i++) pages.push(i);
        } else {
            pages.push(1);
            const lo = Math.max(2, notifCurrentPage - 1);
            const hi = Math.min(totalPages - 1, notifCurrentPage + 1);
            if (lo > 2) pages.push('\u2026');
            for (let i = lo; i <= hi; i++) pages.push(i);
            if (hi < totalPages - 1) pages.push('\u2026');
            pages.push(totalPages);
        }
        pages.forEach(p => {
            if (p === '\u2026') {
                nums.appendChild(nEl('span', 'fnotif-pg-ellipsis', '\u2026'));
                return;
            }
            const b = nEl('button', 'fnotif-pg-num' + (p === notifCurrentPage ? ' active' : ''), String(p));
            b.addEventListener('click', () => goToNotifPage(p));
            nums.appendChild(b);
        });
    }

    /* Everything outside the list that depends on the data: badge, counts,
       pills, toolbar state, confirm strip, empty state. */
    function updateNotifChrome(matches) {
        const unread = notifItems.filter(n => !n.is_read).length;

        const uc = document.getElementById('unreadCount');
        if (uc) uc.textContent = unread + ' unread';
        const up = document.getElementById('unreadPlural');
        if (up) up.textContent = unread === 1 ? '' : 's';

        const badge = document.getElementById('notifBadge');
        if (badge) {
            badge.textContent = unread > 99 ? '99+' : String(unread);
            badge.hidden = unread <= 0;
        }
        const navDot = document.getElementById('navNotifDot');
        if (navDot) navDot.hidden = unread <= 0;
        const unreadLabel = unread > 0 ? ' \u2014 ' + unread + ' unread' : '';
        const bell = document.querySelector('[data-action="open-notif-modal"]');
        if (bell) bell.setAttribute('aria-label', 'Open notifications' + unreadLabel);
        const acctToggleEl = document.getElementById('navAccountToggle');
        if (acctToggleEl) acctToggleEl.setAttribute('aria-label', 'Account menu' + unreadLabel);

        const markAll = document.querySelector('#notifModal [data-action="mark-all-read"]');
        if (markAll) markAll.disabled = unread === 0;

        // Pill counts: per-category, plus All / Unread
        document.querySelectorAll('#notifModal .fnotif-pill-count[data-pill]').forEach(p => {
            const k = p.dataset.pill;
            const c = k === 'all' ? notifItems.length
                : k === 'unread' ? unread
                    : notifItems.filter(n => n.cat === k).length;
            p.textContent = c;
            p.hidden = c === 0;
        });

        // Footer: Select toggles select mode; Delete all + the selection bar only exist while selecting
        const selectBtn = document.getElementById('notifSelectBtn');
        if (selectBtn) {
            selectBtn.disabled = !notifSelectMode && notifItems.length === 0;
            selectBtn.setAttribute('aria-pressed', notifSelectMode ? 'true' : 'false');
            const lbl = document.getElementById('notifSelectLbl');
            if (lbl) lbl.textContent = notifSelectMode ? 'Done' : 'Select';
            const ic = selectBtn.querySelector('.material-symbols-outlined');
            if (ic) ic.textContent = notifSelectMode ? 'close' : 'checklist';
        }
        const delAll = document.getElementById('notifDeleteAllBtn');
        if (delAll) {
            delAll.hidden = !notifSelectMode;
            delAll.disabled = matches.length === 0;
            const lbl = delAll.lastChild;
            if (lbl && lbl.nodeType === 3) lbl.nodeValue = ' Delete all' + (notifActiveCat === 'all' ? '' : ' (' + matches.length + ')');
        }
        const selBar = document.getElementById('notifSelBar');
        if (selBar) selBar.hidden = !notifSelectMode;

        const selCount = matches.filter(n => notifSelected.has(n.key)).length;
        const selCountEl = document.getElementById('notifSelCount');
        if (selCountEl) selCountEl.textContent = selCount + ' selected';
        const selAll = document.getElementById('notifSelectAll');
        if (selAll) {
            selAll.checked = matches.length > 0 && selCount === matches.length;
            selAll.indeterminate = selCount > 0 && selCount < matches.length;
            selAll.disabled = matches.length === 0;
        }
        document.querySelectorAll('#notifSelBar [data-needs-sel]').forEach(b => { b.disabled = selCount === 0; });

        // Confirm strip
        const conf = document.getElementById('notifConfirm');
        if (conf) {
            conf.hidden = !notifPendingConfirm;
            const ct = document.getElementById('notifConfirmText');
            if (ct && notifPendingConfirm) ct.textContent = notifPendingConfirm.text;
        }

        // Empty state
        const empty = document.getElementById('notifEmptyState');
        if (empty) {
            empty.style.display = matches.length === 0 ? '' : 'none';
            const et = document.getElementById('notifEmptyText');
            if (et) {
                et.textContent = notifItems.length === 0 ? 'No notifications yet.'
                    : notifActiveCat === 'unread' ? 'You\u2019re all caught up \u2014 nothing unread.'
                        : 'Nothing here right now.';
            }
        }
    }

    function renderNotifPage() {
        const list = document.getElementById('notifList');
        if (!list) return;

        if (notifSelectMode && notifItems.length === 0) { notifSelectMode = false; notifSelected.clear(); }
        const matches = getMatchingNotifs(notifActiveCat);
        const total = matches.length;
        const totalPages = Math.max(1, Math.ceil(total / NOTIF_PER_PAGE));
        if (notifCurrentPage > totalPages) notifCurrentPage = totalPages;
        if (notifCurrentPage < 1) notifCurrentPage = 1;
        const start = (notifCurrentPage - 1) * NOTIF_PER_PAGE;

        const scroller = document.querySelector('#notifModal .fnotif-body');
        const keepScroll = scroller ? scroller.scrollTop : 0;
        const focusKey = (document.activeElement && document.activeElement.closest)
            ? (document.activeElement.closest('.notif-card') || {}).dataset : null;
        const focusedKey = focusKey ? focusKey.key : null;

        list.textContent = '';
        list.classList.toggle('is-selecting', notifSelectMode);
        let lastGroup = null;
        matches.slice(start, start + NOTIF_PER_PAGE).forEach(n => {
            if (n.group !== lastGroup) {
                lastGroup = n.group;
                const isOver = n.group === 'overdue';
                const lab = nEl('div', 'notif-section-label' + (isOver ? ' notif-section-overdue' : ''));
                if (isOver) lab.appendChild(nIcon('warning'));
                lab.appendChild(document.createTextNode(NOTIF_GROUP_LABELS[n.group] || 'Earlier'));
                list.appendChild(lab);
            }
            list.appendChild(buildNotifCard(n));
        });

        if (scroller) scroller.scrollTop = keepScroll;
        if (focusedKey) {
            const again = list.querySelector('.notif-card[data-key="' + focusedKey + '"]');
            if (again && document.getElementById('notifModal').style.display === 'flex') again.focus({ preventScroll: true });
        }

        updateNotifChrome(matches);
        renderNotifPager(total, totalPages, start);
    }

    function goToNotifPage(page) {
        notifCurrentPage = page;
        renderNotifPage();
        const body = document.querySelector('#notifModal .fnotif-body');
        if (body) body.scrollTop = 0;
    }

    function filterNotifs(cat) {
        document.querySelectorAll('.notif-tab').forEach(t => t.classList.remove('active'));
        const btn = document.querySelector('.notif-tab[data-notif-filter="' + cat + '"]');
        if (btn) btn.classList.add('active');
        notifActiveCat = cat;
        notifCurrentPage = 1;
        // A selection / pending delete must never silently follow you to a different tab.
        notifSelected.clear();
        notifLastAnchor = null;
        notifPendingConfirm = null;
        renderNotifPage();
    }

    /* ── Event wiring ─────────────────────────────────────────────── */
    function handleNotifCardAction(act, key) {
        const n = nFind(key);
        if (!n) return;
        if (act === 'expand') toggleNotifExpand(key);
        else if (act === 'delete') deleteNotifs([key]);
        else if (act === 'toggle-read') setNotifsRead([key], !n.is_read);
        else if (act === 'view') {
            if (!n.is_read) setNotifsRead([key], true);
            goToNotifLink(n);
        }
    }

    const notifListEl = document.getElementById('notifList');
    if (notifListEl) {
        notifListEl.addEventListener('click', function (e) {
            const card = e.target.closest('.notif-card');
            if (!card) return;
            const actBtn = e.target.closest('[data-nact]');
            if (actBtn && !notifSelectMode) {
                e.stopPropagation();
                handleNotifCardAction(actBtn.dataset.nact, card.dataset.key);
                return;
            }
            if (notifSelectMode) toggleNotifSelect(card.dataset.key, e.shiftKey);
            else toggleNotifExpand(card.dataset.key);
        });
        notifListEl.addEventListener('keydown', function (e) {
            if ((e.key !== 'Enter' && e.key !== ' ') || !e.target.classList.contains('notif-card')) return;
            e.preventDefault();
            if (notifSelectMode) toggleNotifSelect(e.target.dataset.key, e.shiftKey);
            else toggleNotifExpand(e.target.dataset.key);
        });
        // Don't start a text-selection when shift-clicking a range
        notifListEl.addEventListener('mousedown', function (e) {
            if (e.shiftKey && notifSelectMode) e.preventDefault();
        });
    }

    const notifModalRoot = document.getElementById('notifModal');
    if (notifModalRoot) {
        notifModalRoot.addEventListener('click', function (e) {
            const b = e.target.closest('[data-nbar]');
            if (!b || b.disabled) return;
            const matches = getMatchingNotifs(notifActiveCat);
            const selKeys = matches.filter(n => notifSelected.has(n.key)).map(n => n.key);
            switch (b.dataset.nbar) {
                case 'select': if (notifSelectMode) exitNotifSelectMode(); else enterNotifSelectMode(); break;
                case 'mark-read': setNotifsRead(selKeys, true).then(ok => { if (ok) showToast(nPlural(selKeys.length, 'notification') + ' marked as read.'); }); break;
                case 'mark-unread': setNotifsRead(selKeys, false).then(ok => { if (ok) showToast(nPlural(selKeys.length, 'notification') + ' marked as unread.'); }); break;
                case 'delete-selected':
                    askNotifDelete(selKeys, 'Delete ' + (selKeys.length === 1 ? 'the selected notification' : selKeys.length + ' selected notifications') + '? This can\u2019t be undone.');
                    break;
                case 'delete-all': {
                    const keys = matches.map(n => n.key);
                    const scope = notifActiveCat === 'all' ? ''
                        : notifActiveCat === 'unread' ? ' unread'
                            : ' ' + (NOTIF_CAT_LABELS[notifActiveCat] || notifActiveCat);
                    askNotifDelete(keys, 'Delete all ' + (keys.length === 1 ? '1' + scope + ' notification' : keys.length + scope + ' notifications') + '? This can\u2019t be undone.');
                    break;
                }
                case 'confirm-cancel': cancelNotifConfirm(); break;
                case 'confirm-ok': confirmNotifDelete(); break;
            }
        });
        const selAllBox = document.getElementById('notifSelectAll');
        if (selAllBox) selAllBox.addEventListener('change', toggleNotifSelectAll);
    }

    // Keep the bell/badge honest while the page stays open
    setInterval(function () {
        if (document.visibilityState === 'visible' && !notifPendingConfirm) refreshNotifs();
    }, 60000);

    // First paint from the server-embedded feed (badge, pills, counts)
    renderNotifPage();

    /* ── Settings: Theme ───────────────────────────────────────────────── */
    function applyTheme(theme) {
        _applyThemeDOM(theme);
        LS.set('theme', theme);
        showToast('Theme: ' + theme.charAt(0).toUpperCase() + theme.slice(1));
    }

    /* ── Settings: Accent Color ────────────────────────────────────────── */
    function applyAccent(color, light) {
        _applyAccentDOM(color, light);
        LS.set('accentColor', color);
        LS.set('accentLight', light);
        showToast('Accent color updated!');
    }

    /* ── Settings: Compact ─────────────────────────────────────────────── */
    function applyCompact(on) {
        document.documentElement.style.setProperty('--radius', on ? '9px' : '16px');
        LS.set('compact', on);
        showToast(on ? 'Compact mode enabled' : 'Compact mode disabled');
    }

    /* ── Settings: Font Size ───────────────────────────────────────────── */
    function applyFontSize(val) {
        const lbl = document.getElementById('fontSizeLbl');
        if (lbl) lbl.textContent = val + '%';
        document.documentElement.style.fontSize = (val / 100) + 'rem';
        LS.set('fontSize', val);
    }

    /* ── Settings: Reduce Motion ───────────────────────────────────────── */
    /* IMPORTANT: We only kill animation-duration here, NEVER touch
       `transition` on `*` — doing so was the root cause of the freeze bug
       because it would also null-out pointer-event related repaint cycles. */
    function applyReduceMotion(on) {
        let s = document.getElementById('reduceMotionStyle');
        if (!s) {
            s = document.createElement('style');
            s.id = 'reduceMotionStyle';
            document.head.appendChild(s);
        }
        s.textContent = on ?
            '*, *::before, *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; }' :
            '';
        LS.set('reduceMotion', on);
        showToast(on ? 'Animations disabled' : 'Animations re-enabled');
    }

    /* ── Settings: Focus Ring ──────────────────────────────────────────── */
    function applyFocusRing(on) {
        let s = document.getElementById('focusRingStyle');
        if (!s) {
            s = document.createElement('style');
            s.id = 'focusRingStyle';
            document.head.appendChild(s);
        }
        s.textContent = on ?
            '*:focus { outline: 3px solid var(--accent-maroon) !important; outline-offset: 3px !important; }' :
            '';
        LS.set('focusRing', on);
        showToast(on ? 'Focus rings enhanced' : 'Focus rings reset');
    }

    /* ── Settings: Reset All ───────────────────────────────────────────── */
    function resetAllSettings() {
        applyTheme('light');
        const ct = document.getElementById('compactToggle');
        if (ct) {
            ct.checked = false;
            applyCompact(false);
        }
        const fr = document.getElementById('fontSizeRange');
        if (fr) {
            fr.value = 100;
            applyFontSize(100);
        }
        const rmt = document.getElementById('reduceMotionToggle');
        if (rmt) {
            rmt.checked = false;
            applyReduceMotion(false);
        }
        const frt = document.getElementById('focusRingToggle');
        if (frt) {
            frt.checked = false;
            applyFocusRing(false);
        }
        applyAccent('#600302', '#f3e5e6');
        // Clear persisted settings (but keep account + notif state)
        ['theme', 'accentColor', 'accentLight', 'compact', 'fontSize', 'reduceMotion', 'focusRing'].forEach(k => LS.del(k));
        showToast('All settings reset to defaults.');
    }

    /* ── Profile Edit ──────────────────────────────────────────────────── */
    // Locked fields: dob, gender, nationality cannot be changed once set.
    // The PHP template only renders their <input>/<select> elements when the
    // value is empty, so we simply skip any [data-input] that has no matching
    // element in the DOM.
    function toggleProfileEdit() {
        const editBtn = document.getElementById('editProfileBtn');
        const saveBtn = document.getElementById('saveProfileBtn');
        const cancelBtn = document.getElementById('cancelProfileBtn');
        if (editBtn) editBtn.style.display = 'none';
        if (saveBtn) saveBtn.style.display = 'flex';
        if (cancelBtn) cancelBtn.style.display = 'flex';

        document.querySelectorAll('[data-field]').forEach(span => {
            const key = span.dataset.field;
            const input = document.querySelector('[data-input="' + key + '"]');
            if (!input) return; // locked — no input rendered, skip
            span.style.display = 'none';
            input.style.display = '';
            input.disabled = false;
            if (span.classList.contains('empty')) input.value = '';
        });
    }

    function cancelProfileEdit() {
        const editBtn = document.getElementById('editProfileBtn');
        const saveBtn = document.getElementById('saveProfileBtn');
        const cancelBtn = document.getElementById('cancelProfileBtn');
        if (editBtn) editBtn.style.display = 'flex';
        if (saveBtn) saveBtn.style.display = 'none';
        if (cancelBtn) cancelBtn.style.display = 'none';
        document.querySelectorAll('[data-input]').forEach(input => {
            const key = input.dataset.input;
            const span = document.querySelector('[data-field="' + key + '"]');
            if (!span) return;
            span.style.display = '';
            input.style.display = 'none';
            input.disabled = true;
        });
    }

    function saveProfileEdit() {
        const saveBtn = document.getElementById('saveProfileBtn');
        const cancelBtn = document.getElementById('cancelProfileBtn');

        // Collect values from visible inputs
        const fd = new FormData();
        fd.append('action', 'save_profile');
        fd.append('csrf_token', getCsrfToken());

        document.querySelectorAll('[data-input]').forEach(input => {
            const key = input.dataset.input;
            const val = input.tagName === 'SELECT'
                ? input.options[input.selectedIndex].value
                : input.value.trim();
            fd.append(key, val);
        });

        // Optimistic UI: disable buttons while saving
        if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving…'; }
        if (cancelBtn) cancelBtn.disabled = true;

        fetch('equipment-booking/api/update-profile.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Update each span from the server-confirmed values
                    const serverVals = {
                        fullname: data.fullname || '',
                        dob: data.dob || '',
                        gender: data.gender || '',
                        nationality: data.nationality || ''
                    };

                    document.querySelectorAll('[data-input]').forEach(input => {
                        const key = input.dataset.input;
                        const span = document.querySelector('[data-field="' + key + '"]');
                        if (!span) return;

                        let displayVal = serverVals[key] || '';

                        // Format date of birth for display
                        if (key === 'dob' && displayVal) {
                            const d = new Date(displayVal + 'T00:00:00');
                            displayVal = d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                        }

                        if (displayVal) {
                            span.textContent = displayVal;
                            span.classList.remove('empty');
                            // If this field is now permanently locked, remove the input from DOM
                            const locks = window.USER_PROFILE_LOCKS || {};
                            if ((key === 'dob' || key === 'gender' || key === 'nationality') && !locks[key]) {
                                // Mark as locked for this session without full reload
                                input.parentElement.removeChild(input);
                            }
                        } else {
                            span.textContent = '— Not provided';
                            span.classList.add('empty');
                        }
                    });

                    // Update header name display
                    if (data.fullname) {
                        document.querySelectorAll('.side-nav-user-name, .u-name, .acc-hero-info h2').forEach(el => {
                            el.textContent = data.fullname;
                        });
                        // Update initials
                        const parts = data.fullname.trim().split(' ');
                        let ini = parts[0].charAt(0).toUpperCase();
                        if (parts.length > 1) ini += parts[parts.length - 1].charAt(0).toUpperCase();
                        document.querySelectorAll('.avatar-btn, .side-nav-avatar, .acc-avatar-large').forEach(el => {
                            // Replace only text nodes (preserve child elements like .cam-btn)
                            const textNode = [...el.childNodes].find(n => n.nodeType === Node.TEXT_NODE);
                            if (textNode) textNode.textContent = ini;
                            else if (!el.querySelector('.cam-btn')) el.textContent = ini;
                            else { el.insertBefore(document.createTextNode(ini), el.firstChild); }
                        });
                    }

                    cancelProfileEdit();
                    showToast(data.msg || 'Profile updated!');
                } else {
                    if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"><polyline points="20 6 9 17 4 12"/></svg> Save'; }
                    if (cancelBtn) cancelBtn.disabled = false;
                    showToast('Error: ' + (data.msg || 'Could not save profile.'));
                }
            })
            .catch(() => {
                if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"><polyline points="20 6 9 17 4 12"/></svg> Save'; }
                if (cancelBtn) cancelBtn.disabled = false;
                showToast('Network error. Please try again.');
            });
    }

    /* ── Change Password ───────────────────────────────────────────────── */
    function openPwModal() {
        const modal = document.getElementById('pwModal');
        if (!modal) return;
        // Clear previous values
        ['pwCurrent', 'pwNew', 'pwConfirm'].forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.value = ''; el.type = 'password'; }
        });
        const errEl = document.getElementById('pwModalError');
        if (errEl) errEl.style.display = 'none';
        const bar = document.getElementById('pwStrengthBar');
        const lbl = document.getElementById('pwStrengthLabel');
        if (bar) bar.style.display = 'none';
        if (lbl) lbl.textContent = '';
        const submitBtn = document.getElementById('pwSubmitBtn');
        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"><polyline points="20 6 9 17 4 12"/></svg> Update Password'; }
        modal.style.display = 'flex';
        setTimeout(() => { const el = document.getElementById('pwCurrent'); if (el) el.focus(); }, 80);
    }

    function closePwModal() {
        const modal = document.getElementById('pwModal');
        if (modal) modal.style.display = 'none';
    }

    function submitPasswordChange() {
        const current = (document.getElementById('pwCurrent') || {}).value || '';
        const newPw = (document.getElementById('pwNew') || {}).value || '';
        const confirm = (document.getElementById('pwConfirm') || {}).value || '';
        const errEl = document.getElementById('pwModalError');
        const submitBtn = document.getElementById('pwSubmitBtn');

        function showPwErr(msg) {
            if (errEl) { errEl.textContent = msg; errEl.style.display = 'block'; }
        }

        if (!current || !newPw || !confirm) { showPwErr('All fields are required.'); return; }
        if (newPw.length < 6) { showPwErr('New password must be at least 6 characters.'); return; }
        if (newPw !== confirm) { showPwErr('New passwords do not match.'); return; }
        if (newPw === current) { showPwErr('New password cannot be the same as your current one.'); return; }

        if (errEl) errEl.style.display = 'none';
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Updating…'; }

        const fd = new FormData();
        fd.append('action', 'change_password');
        fd.append('csrf_token', getCsrfToken());
        fd.append('current_password', current);
        fd.append('new_password', newPw);
        fd.append('confirm_password', confirm);

        fetch('equipment-booking/api/update-profile.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closePwModal();
                    showToast(data.msg || 'Password changed successfully!');
                } else {
                    showPwErr(data.msg || 'Failed to change password.');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"><polyline points="20 6 9 17 4 12"/></svg> Update Password'; }
                }
            })
            .catch(() => {
                showPwErr('Network error. Please try again.');
                if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"><polyline points="20 6 9 17 4 12"/></svg> Update Password'; }
            });
    }

    /* ── Password Strength Meter ───────────────────────────────────────── */
    function checkPwStrength(val) {
        const bar = document.getElementById('pwStrengthBar');
        const fill = document.getElementById('pwStrengthFill');
        const lbl = document.getElementById('pwStrengthLabel');
        if (!bar || !fill || !lbl) return;
        if (!val) { bar.style.display = 'none'; lbl.textContent = ''; return; }
        bar.style.display = '';
        let score = 0;
        if (val.length >= 8) score++;
        if (val.length >= 12) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        const levels = [
            { pct: 20, color: '#e53e3e', label: 'Very Weak' },
            { pct: 40, color: '#dd6b20', label: 'Weak' },
            { pct: 60, color: '#d69e2e', label: 'Fair' },
            { pct: 80, color: '#38a169', label: 'Strong' },
            { pct: 100, color: '#2b6cb0', label: 'Very Strong' },
        ];
        const lvl = levels[Math.min(score, levels.length - 1)];
        fill.style.width = lvl.pct + '%';
        fill.style.backgroundColor = lvl.color;
        lbl.textContent = lvl.label;
        lbl.style.color = lvl.color;
    }

    /* ── Email Verification (before password change) ─────────────────────── */
    function openEmailVerifyModal() {
        const modal = document.getElementById('emailVerifyModal');
        if (!modal) return;
        const input = document.getElementById('verifyEmailInput');
        if (input) input.value = '';
        const errEl = document.getElementById('emailVerifyError');
        if (errEl) errEl.style.display = 'none';
        const btn = document.getElementById('emailVerifyBtn');
        if (btn) { btn.disabled = false; btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"><polyline points="20 6 9 17 4 12"/></svg> Verify & Continue'; }
        modal.style.display = 'flex';
        setTimeout(() => { if (input) input.focus(); }, 80);
    }

    function closeEmailVerifyModal() {
        const modal = document.getElementById('emailVerifyModal');
        if (modal) modal.style.display = 'none';
    }

    function submitEmailVerify() {
        const input = document.getElementById('verifyEmailInput');
        const email = (input || {}).value || '';
        const errEl = document.getElementById('emailVerifyError');
        const btn = document.getElementById('emailVerifyBtn');

        function showErr(msg) {
            if (errEl) { errEl.textContent = msg; errEl.style.display = 'block'; }
        }

        if (!email) { showErr('Email is required.'); return; }
        if (errEl) errEl.style.display = 'none';
        if (btn) { btn.disabled = true; btn.textContent = 'Verifying…'; }

        const fd = new FormData();
        fd.append('action', 'verify_email_for_password');
        fd.append('csrf_token', getCsrfToken());
        fd.append('email', email);

        fetch('equipment-booking/api/update-profile.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeEmailVerifyModal();
                    openPwModal();
                } else {
                    showErr(data.msg || 'Email verification failed.');
                    if (btn) { btn.disabled = false; btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"><polyline points="20 6 9 17 4 12"/></svg> Verify & Continue'; }
                }
            })
            .catch(() => {
                showErr('Network error. Please try again.');
                if (btn) { btn.disabled = false; btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"><polyline points="20 6 9 17 4 12"/></svg> Verify & Continue'; }
            });
    }

    /* ── Backup Email Management ───────────────────────────────────────── */
    function openBackupEmailModal() {
        const modal = document.getElementById('backupEmailModal');
        if (!modal) return;
        const errEl = document.getElementById('backupEmailError');
        if (errEl) errEl.style.display = 'none';
        const btn = document.getElementById('backupEmailSaveBtn');
        if (btn) { btn.disabled = false; btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"><polyline points="20 6 9 17 4 12"/></svg> Save Backup Email'; }
        modal.style.display = 'flex';
        setTimeout(() => {
            const input = document.getElementById('backupEmailInput');
            if (input) input.focus();
        }, 80);
    }

    function closeBackupEmailModal() {
        const modal = document.getElementById('backupEmailModal');
        if (modal) modal.style.display = 'none';
    }

    function saveBackupEmail() {
        const input = document.getElementById('backupEmailInput');
        const email = (input || {}).value.trim() || '';
        const errEl = document.getElementById('backupEmailError');
        const btn = document.getElementById('backupEmailSaveBtn');

        function showErr(msg) {
            if (errEl) { errEl.textContent = msg; errEl.style.display = 'block'; }
        }

        // Allow empty to remove backup email
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showErr('Invalid email format.');
            return;
        }

        if (errEl) errEl.style.display = 'none';
        if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

        const fd = new FormData();
        fd.append('action', 'update_backup_email');
        fd.append('csrf_token', getCsrfToken());
        fd.append('backup_email', email);

        fetch('equipment-booking/api/update-profile.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Update display
                    const displayEl = document.querySelector('[data-field="backup_email"]');
                    if (displayEl) {
                        if (data.backup_email) {
                            const parts = data.backup_email.split('@');
                            const masked = parts[0].substring(0, 4) + '***@' + parts[1];
                            displayEl.textContent = masked;
                            displayEl.classList.remove('empty');
                        } else {
                            displayEl.textContent = '— Not provided';
                            displayEl.classList.add('empty');
                        }
                    }
                    closeBackupEmailModal();
                    showToast(data.msg || 'Backup email updated!');
                } else {
                    showErr(data.msg || 'Failed to update backup email.');
                    if (btn) { btn.disabled = false; btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"><polyline points="20 6 9 17 4 12"/></svg> Save Backup Email'; }
                }
            })
            .catch(() => {
                showErr('Network error. Please try again.');
                if (btn) { btn.disabled = false; btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;"><polyline points="20 6 9 17 4 12"/></svg> Save Backup Email'; }
            });
    }

    /* -- Faculty ID (set once, then permanent) ------------------------- */
    let _facultyIdPending = '';

    function facultyIdStep(step) {
        const enter = document.getElementById('facultyIdStepEnter');
        const confirm = document.getElementById('facultyIdStepConfirm');
        if (enter) enter.style.display = (step === 'enter') ? '' : 'none';
        if (confirm) confirm.style.display = (step === 'confirm') ? '' : 'none';
    }

    function facultyIdMessage(elId, msg) {
        const el = document.getElementById(elId);
        if (!el) return;
        el.textContent = msg || '';
        el.style.display = msg ? 'block' : 'none';
    }

    function openFacultyIdModal() {
        const modal = document.getElementById('facultyIdModal');
        if (!modal) return;
        const input = document.getElementById('facultyIdInput');
        if (input) input.value = '';
        _facultyIdPending = '';
        facultyIdMessage('facultyIdError', '');
        facultyIdMessage('facultyIdConfirmError', '');
        facultyIdStep('enter');
        modal.style.display = 'flex';
        setTimeout(() => { if (input) input.focus(); }, 80);
    }

    function closeFacultyIdModal() {
        const modal = document.getElementById('facultyIdModal');
        if (modal) modal.style.display = 'none';
        _facultyIdPending = '';
    }

    function facultyIdRequest(action, id) {
        const fd = new FormData();
        fd.append('action', action);
        fd.append('csrf_token', getCsrfToken());
        fd.append('faculty_id', id);
        return fetch('equipment-booking/api/update-profile.php', { method: 'POST', body: fd })
            .then(r => r.json());
    }

    /* Step 1 -> 2: validate + check availability (nothing is saved yet). */
    function facultyIdContinue() {
        const input = document.getElementById('facultyIdInput');
        const btn = document.getElementById('facultyIdContinueBtn');
        const raw = ((input || {}).value || '').trim();
        facultyIdMessage('facultyIdError', '');
        if (!raw) {
            facultyIdMessage('facultyIdError', 'Enter your Faculty ID.');
            return;
        }
        const label = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.textContent = 'Checking...'; }

        facultyIdRequest('check_faculty_id', raw)
            .then(data => {
                if (data.success) {
                    _facultyIdPending = data.faculty_id;
                    const v = document.getElementById('facultyIdConfirmValue');
                    if (v) v.textContent = data.faculty_id;
                    facultyIdMessage('facultyIdConfirmError', '');
                    facultyIdStep('confirm');
                } else if (data.locked) {
                    closeFacultyIdModal();
                    showToast(data.msg || 'Your Faculty ID is already set.', 'error');
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    facultyIdMessage('facultyIdError', data.msg || 'That Faculty ID cannot be used.');
                }
            })
            .catch(() => facultyIdMessage('facultyIdError', 'Network error. Please try again.'))
            .finally(() => { if (btn) { btn.disabled = false; btn.innerHTML = label; } });
    }

    /* Step 2: the permanent save. */
    function facultyIdConfirm() {
        const btn = document.getElementById('facultyIdConfirmBtn');
        if (!_facultyIdPending) { facultyIdStep('enter'); return; }
        facultyIdMessage('facultyIdConfirmError', '');
        const label = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }

        facultyIdRequest('set_faculty_id', _facultyIdPending)
            .then(data => {
                if (data.success) {
                    const id = data.faculty_id;
                    document.querySelectorAll('[data-fid-display]').forEach(el => {
                        el.textContent = id;
                        el.classList.remove('empty', 'acct-row-static-muted');
                    });
                    document.querySelectorAll('[data-action="open-faculty-id-modal"]').forEach(b => {
                        if (b.classList.contains('sov-btn-outline')) {
                            const lock = document.createElement('span');
                            lock.className = 'material-symbols-outlined fid-lock';
                            lock.title = 'Your Faculty ID is permanent';
                            lock.textContent = 'lock';
                            b.replaceWith(lock);
                        } else {
                            b.remove();
                        }
                    });
                    closeFacultyIdModal();
                    showToast(data.msg || 'Faculty ID saved.');
                } else if (data.locked) {
                    closeFacultyIdModal();
                    showToast(data.msg || 'Your Faculty ID is already set.', 'error');
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    facultyIdMessage('facultyIdConfirmError', data.msg || 'Your Faculty ID could not be saved.');
                }
            })
            .catch(() => facultyIdMessage('facultyIdConfirmError', 'Network error. Please try again.'))
            .finally(() => { if (btn) { btn.disabled = false; btn.innerHTML = label; } });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target && e.target.id === 'facultyIdInput') {
            e.preventDefault();
            facultyIdContinue();
        }
    });

    /* ── Profile Picture Management ────────────────────────────────────── */
    function togglePictureMenu() {
        const menu = document.getElementById('pictureMenu');
        if (!menu) return;
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    }

    function uploadPicture() {
        const input = document.getElementById('profilePicInput');
        if (!input) return;
        input.click();
    }

    function removePicture() {
        if (!confirm('Remove your profile picture? You will revert to your initials.')) return;
        togglePictureMenu();

        const fd = new FormData();
        fd.append('action', 'remove_profile_picture');
        fd.append('csrf_token', getCsrfToken());

        fetch('equipment-booking/api/update-profile.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Revert to initials everywhere
                    updateAvatarsToInitials();
                    showToast(data.msg || 'Profile picture removed!');
                } else {
                    showToast('Error: ' + (data.msg || 'Could not remove picture.'));
                }
            })
            .catch(() => showToast('Network error. Please try again.'));
    }

    function updateAvatarsToInitials() {
        const fullnameEl = document.querySelector('.acc-hero-info h2');
        const fullname = fullnameEl ? fullnameEl.textContent : (document.querySelector('.side-nav-user-name')?.textContent || '');
        const parts = fullname.trim().split(' ').filter(Boolean);
        let ini = parts.length ? parts[0].charAt(0).toUpperCase() : '';
        if (parts.length > 1) ini += parts[parts.length - 1].charAt(0).toUpperCase();

        // Strip any existing image, fallback span, or stray initials text,
        // then prepend fresh initials as a text node — this preserves other
        // element children (e.g. the notification badge) untouched.
        document.querySelectorAll('.side-nav-avatar, .avatar-btn, .acc-avatar-large, .acct-banner-avatar').forEach(el => {
            [...el.childNodes].forEach(n => {
                if (n.nodeType === Node.TEXT_NODE) n.remove();
                if (n.classList && (n.classList.contains('avatar-img') || n.classList.contains('avatar-initials-fallback'))) n.remove();
            });
            el.insertBefore(document.createTextNode(ini), el.firstChild);
        });
    }

    function updateAvatarsToImage(url) {
        // Figure out the initials once, so we have something to fall back to
        // if this image URL also fails to load (e.g. stale/broken file).
        const fullnameEl = document.querySelector('.acc-hero-info h2');
        const fullname = fullnameEl ? fullnameEl.textContent : (document.querySelector('.side-nav-user-name')?.textContent || '');
        const parts = fullname.trim().split(' ').filter(Boolean);
        let ini = parts.length ? parts[0].charAt(0).toUpperCase() : '';
        if (parts.length > 1) ini += parts[parts.length - 1].charAt(0).toUpperCase();

        document.querySelectorAll('.side-nav-avatar, .avatar-btn, .acc-avatar-large, .acct-banner-avatar').forEach(el => {
            // Remove text nodes and any existing image/fallback
            [...el.childNodes].forEach(n => {
                if (n.nodeType === Node.TEXT_NODE && n.textContent.trim()) n.remove();
                if (n.classList && (n.classList.contains('avatar-img') || n.classList.contains('avatar-initials-fallback'))) n.remove();
            });
            // Fallback initials, hidden unless the image below fails to load
            const fallback = document.createElement('span');
            fallback.className = 'avatar-initials-fallback';
            fallback.style.display = 'none';
            fallback.textContent = ini;
            el.insertBefore(fallback, el.firstChild);
            // Add new image
            const img = document.createElement('img');
            img.src = url;
            img.alt = 'Profile';
            img.className = 'avatar-img';
            img.onerror = function () {
                img.style.display = 'none';
                fallback.style.removeProperty('display');
            };
            el.insertBefore(img, el.firstChild);
        });
    }

    // Handle profile picture file selection
    const picInput = document.getElementById('profilePicInput');
    if (picInput) {
        picInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;

            // Validate file type
            if (!['image/jpeg', 'image/png', 'image/jpg', 'image/webp'].includes(file.type)) {
                showToast('Invalid file type. Only JPG, PNG, and WEBP are allowed.');
                return;
            }

            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                showToast('File too large. Maximum size is 5MB.');
                return;
            }

            togglePictureMenu();
            const loadingEl = document.getElementById('loading-overlay');
            if (loadingEl) loadingEl.classList.add('active');

            const fd = new FormData();
            fd.append('action', 'upload_profile_picture');
            fd.append('csrf_token', getCsrfToken());
            fd.append('profile_picture', file);

            fetch('equipment-booking/api/update-profile.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (loadingEl) loadingEl.classList.remove('active');
                    if (data.success) {
                        updateAvatarsToImage(window.SERVER_BASE_URL + data.profile_picture + '?t=' + Date.now());
                        showToast(data.msg || 'Profile picture updated!');
                    } else {
                        showToast('Error: ' + (data.msg || 'Upload failed.'));
                    }
                })
                .catch(() => {
                    if (loadingEl) loadingEl.classList.remove('active');
                    showToast('Network error. Please try again.');
                })
                .finally(() => {
                    picInput.value = ''; // Reset input
                });
        });
    }

    // Close picture menu when clicking outside
    document.addEventListener('click', function (e) {
        const menu = document.getElementById('pictureMenu');
        const avatar = document.getElementById('profileAvatarLarge');
        if (menu && !avatar?.contains(e.target)) {
            menu.style.display = 'none';
        }
    });

    function _escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    let _prevOverdueCount = null; // null = baseline not yet established

    function showOverdueToast() {
        const el = document.getElementById('overdue-alert');
        if (!el) return;
        el.style.display = ''; // undo any earlier manual dismiss (display:none)
        requestAnimationFrame(() => el.classList.add('is-visible'));
        clearTimeout(el._dismissTimer);
        el._dismissTimer = setTimeout(() => el.classList.remove('is-visible'), 8000);
    }

    function checkOverdueState() {
        const overdueCount = (typeof window.OVERDUE_COUNT !== 'undefined')
            ? window.OVERDUE_COUNT
            : (window.REQUESTS_DATA || []).filter(r => (r.status || '').trim() === 'Overdue').length;
        // Update overdue stat value
        const statEl = document.getElementById('statOverdueVal');
        if (statEl) statEl.textContent = overdueCount;
        // Show the toast only on a genuine transition from clear to blocked
        // detected during this session (e.g. an item newly goes overdue
        // while the tab is open) — not on every check. The very first call
        // just establishes the baseline; the initial-load toast (if any) is
        // handled server-side via the session-gated data-should-show flag.
        if (_prevOverdueCount !== null && overdueCount > 0 && _prevOverdueCount === 0) {
            showOverdueToast();
        }
        _prevOverdueCount = overdueCount;
        // The bell badge is driven by the real notification feed now — pull
        // a fresh copy whenever the overdue count changes.
        if (_prevOverdueCount !== null && _prevOverdueCount !== overdueCount) refreshNotifs();
    }

    /* ── Borrow Form Init ──────────────────────────────────────────────── */
    function initBorrowForm() {
        // Schedule rules (Today / Later, hours, days, quantity) are enforced by BorrowSchedule's own
        // submit listener, which runs first and stops the submit when something is wrong.
        BorrowSchedule.init();

        const form = document.getElementById('borrowForm');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            document.getElementById('loading-overlay').classList.add('active');
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'borrow_submit';
            hidden.value = '1';
            this.appendChild(hidden);
            setTimeout(() => this.submit(), 2000);
        });
    }

    /* ════════════════════════════════════════════════════════════════════
       MASTER EVENT DELEGATION
       All click-based interactions route through here. Each case is
       wrapped in a try-catch so one failing action can NEVER freeze
       the rest of the UI — this was the secondary cause of the freeze.
    ════════════════════════════════════════════════════════════════════ */
    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-action]');
        if (!el) return;
        if (el.tagName.toLowerCase() === 'a') {
            e.preventDefault();
        }
        const action = el.dataset.action;
        try {
            switch (action) {
                case 'open-overlay':
                    openOverlay(el.dataset.target);
                    break;
                case 'close-overlay':
                    closeOverlay(el.dataset.target);
                    break;
                case 'dismiss-alert': {
                    const t = document.getElementById(el.dataset.target);
                    if (t) t.style.display = 'none';
                    break;
                }
                case 'go-tab':
                    switchTab(el.dataset.tab, el.dataset.lending || null);
                    if (el.dataset.lending) switchLendingSub(el.dataset.lending);
                    break;
                case 'myact-report-issue': {
                    var actChat = document.getElementById('actAiChat');
                    var actInput = document.getElementById('actAiInput');
                    if (actChat) actChat.classList.add('open');
                    if (actInput) {
                        var equipName = el.dataset.equipment || 'this item';
                        var reqId = el.dataset.requestId || '';
                        actInput.value = 'I need to report an issue with my "' + equipName + '"'
                            + (reqId ? ' (Request #' + reqId + ')' : '') + '.';
                        actInput.focus();
                    }
                    break;
                }
                case 'hist-page-prev':
                    goToHistPage(histCurrentPage - 1);
                    break;
                case 'hist-page-next':
                    goToHistPage(histCurrentPage + 1);
                    break;

                /* ── AI Chatbot ──────────────────────────────────────────── */
                case 'ai-fab-toggle': {
                    const chat = document.getElementById('actAiChat');
                    if (!chat) break;
                    const opening = !chat.classList.contains('open');
                    chat.classList.toggle('open');
                    if (opening) chat.classList.remove('minimized');
                    break;
                }
                case 'ai-chat-close': {
                    const chat = document.getElementById('actAiChat');
                    if (chat) { chat.classList.remove('open'); chat.classList.remove('minimized'); }
                    break;
                }
                case 'ai-chat-minimize': {
                    const chat = document.getElementById('actAiChat');
                    if (chat) chat.classList.toggle('minimized');
                    break;
                }
                case 'ai-chat-send':
                    actAiSend();
                    break;
                case 'ai-chat-report-issue': {
                    const inp = document.getElementById('actAiInput');
                    if (inp) {
                        inp.value = 'I need to report a damaged or lost item.';
                        inp.focus();
                    }
                    break;
                }
                case 'open-borrow-form':
                    openBorrowForm(el.dataset.item);
                    break;
                case 'close-borrow-modal':
                    closeBorrowModal();
                    break;
                case 'borrow-subtab': {
                    var tab = el.dataset.tab;
                    var btnP = document.getElementById('subtab-personal-btn');
                    var btnA = document.getElementById('subtab-adviser-btn');
                    var panP = document.getElementById('subtab-personal');
                    var panA = document.getElementById('subtab-adviser');
                    if (!btnP || !btnA || !panP || !panA) break;
                    if (tab === 'personal') {
                        btnP.classList.add('active'); btnP.setAttribute('aria-selected', 'true');
                        btnA.classList.remove('active'); btnA.setAttribute('aria-selected', 'false');
                        panP.classList.add('active'); panA.classList.remove('active');
                    } else {
                        btnA.classList.add('active'); btnA.setAttribute('aria-selected', 'true');
                        btnP.classList.remove('active'); btnP.setAttribute('aria-selected', 'false');
                        panA.classList.add('active'); panP.classList.remove('active');
                    }
                    break;
                }
                case 'lending-back':
                    switchLendingSub('browse');
                    break;
                case 'open-room-form':
                    openRoomForm(el.dataset.room);
                    break;
                case 'close-room-form':
                    closeRoomForm();
                    break;
                case 'room-reserve-preview':
                    showToast('Room Reservation feature coming soon!');
                    break;
                case 'apply-theme':
                    applyTheme(el.dataset.theme);
                    break;
                // case 'apply-accent': — accent color picker removed from new settings design
                //     applyAccent(el.dataset.color, el.dataset.light);
                //     break;
                // case 'reset-settings': — reset button removed from new settings design
                //     resetAllSettings();
                //     break;
                case 'profile-edit':
                    toggleProfileEdit();
                    break;
                case 'profile-save':
                    saveProfileEdit();
                    break;
                case 'profile-cancel':
                    cancelProfileEdit();
                    break;
                case 'open-pw-modal':
                    openPwModal();
                    break;
                case 'close-pw-modal':
                    closePwModal();
                    break;
                case 'submit-pw-change':
                    submitPasswordChange();
                    break;
                case 'open-email-verify-modal':
                    openEmailVerifyModal();
                    break;
                case 'close-email-verify-modal':
                    closeEmailVerifyModal();
                    break;
                case 'submit-email-verify':
                    submitEmailVerify();
                    break;
                case 'open-backup-email-modal':
                    openBackupEmailModal();
                    break;
                case 'close-backup-email-modal':
                    closeBackupEmailModal();
                    break;
                case 'save-backup-email':
                    saveBackupEmail();
                    break;
                case 'open-faculty-id-modal':
                    openFacultyIdModal();
                    break;
                case 'close-faculty-id-modal':
                    closeFacultyIdModal();
                    break;
                case 'faculty-id-continue':
                    facultyIdContinue();
                    break;
                case 'faculty-id-back':
                    facultyIdStep('enter');
                    setTimeout(() => { const i = document.getElementById('facultyIdInput'); if (i) i.focus(); }, 50);
                    break;
                case 'faculty-id-confirm':
                    facultyIdConfirm();
                    break;
                case 'open-picture-menu':
                    togglePictureMenu();
                    break;
                case 'upload-picture':
                    uploadPicture();
                    break;
                case 'remove-picture':
                    removePicture();
                    break;
                case 'mark-all-read':
                    markAllRead();
                    break;
                case 'open-notif-modal':
                    openNotifModal();
                    break;
                case 'close-notif-modal':
                    closeNotifModal();
                    break;
                case 'toast':
                    showToast(el.dataset.msg || '');
                    break;
                case 'show-return-qr': {
                    const token = el.dataset.token;
                    const equipment = el.dataset.equipment;
                    // Use PHP-injected SERVER_BASE_URL so the QR always uses
                    // the real network IP, not localhost
                    const base = window.SERVER_BASE_URL
                        || window.location.href.substring(0, window.location.href.lastIndexOf('/') + 1);
                    const returnUrl = base + 'return_confirm.php?token=' + token;
                    _openReturnQrModal(equipment, returnUrl);
                    break;
                }
                case 'logout':
                    if (window.PSLogout) window.PSLogout.open(el);
                    else if (confirm('Confirm Logout?')) window.location.href = 'api/logout.php'; // fallback only if logout-modal.js failed to load
                    break;
            }
        } catch (err) {
            console.warn('Action "' + action + '" failed:', err);
        }
    });

    /* ── Account tab wiring ───────────────────────────────────────────── */
    const navAccountToggle = document.getElementById('navAccountToggle');
    if (navAccountToggle) {
        navAccountToggle.addEventListener('click', function () {
            setAccountMenuOpen(navAccountToggle.getAttribute('aria-expanded') !== 'true');
        });
    }

    /* ── Notifications modal ──────────────────────────────────────────
       Opened from the "Notifications" item inside the sidebar's account menu.
    ─────────────────────────────────────────────────────────────────── */
    function openNotifModal() {
        const modal = document.getElementById('notifModal');
        if (!modal) return;
        closeMobileNav();             // collapse the phone drawer if it is open
        resetNotifTransient();
        filterNotifs('all');          // always open on the All tab
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        refreshNotifs();              // pull anything new since the page loaded
    }

    function closeNotifModal() {
        const modal = document.getElementById('notifModal');
        if (modal) modal.style.display = 'none';
        document.body.style.overflow = '';
        resetNotifTransient();
        renderNotifPage();
    }

    // Backdrop click + Esc to dismiss
    const notifModalEl = document.getElementById('notifModal');
    if (notifModalEl) {
        notifModalEl.addEventListener('click', function (e) {
            if (e.target === notifModalEl) closeNotifModal();
        });
    }

    /* Direct bindings as a safety net: if anything ever stops the click
       from bubbling up to the delegated [data-action] handler, these
       still fire. Guarded so the modal can't open twice. */
    document.querySelectorAll('[data-action="open-notif-modal"]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openNotifModal();
        });
    });
    document.querySelectorAll('[data-action="close-notif-modal"]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeNotifModal();
        });
    });

    /* Notification pagination: prev/next */
    const notifPrev = document.getElementById('notifPrevBtn');
    const notifNext = document.getElementById('notifNextBtn');
    if (notifPrev) notifPrev.addEventListener('click', () => goToNotifPage(notifCurrentPage - 1));
    if (notifNext) notifNext.addEventListener('click', () => goToNotifPage(notifCurrentPage + 1));
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        const m = document.getElementById('notifModal');
        if (m && m.style.display === 'flex') {
            if (notifPendingConfirm) { cancelNotifConfirm(); return; }
            if (notifSelectMode) { exitNotifSelectMode(); return; }
            closeNotifModal();
        }
    });

    /* ── Mobile menu toggle ──────────────────────────────────────────── */
    function openMobileNav() {
        const nav = document.getElementById('sideNav');
        const backdrop = document.getElementById('navBackdrop');
        if (nav) nav.classList.add('open');
        if (backdrop) backdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileNav() {
        const nav = document.getElementById('sideNav');
        const backdrop = document.getElementById('navBackdrop');
        if (nav) nav.classList.remove('open');
        if (backdrop) backdrop.classList.remove('open');
        document.body.style.overflow = '';
    }

    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', openMobileNav);

    const navBackdrop = document.getElementById('navBackdrop');
    if (navBackdrop) navBackdrop.addEventListener('click', closeMobileNav);

    /* ── Sidebar toggle (#sidebarCollapseBtn, right side of the brand row).
       Desktop: collapses / expands the icon-rail and remembers the choice.
       Phones: the same button closes the slide-in drawer. Hidden on tablets,
       where the sidebar is always an icon rail. ── */
    const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
    function setSidebarCollapsed(collapsed) {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        if (sidebarCollapseBtn) {
            sidebarCollapseBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            sidebarCollapseBtn.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
        }
        LS.set('sidebarCollapsed', collapsed ? '1' : '0');
    }
    if (sidebarCollapseBtn) {
        sidebarCollapseBtn.addEventListener('click', function () {
            if (window.innerWidth <= 768) { closeMobileNav(); return; }
            setSidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'));
        });
        if (LS.get('sidebarCollapsed') === '1') setSidebarCollapsed(true);
    }

    /* ── Side nav clicks ──────────────────────────────────────────────── */
    document.querySelectorAll('.side-nav-item[data-tab]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            closeMobileNav();
            // Close any open overlays
            document.querySelectorAll('.overlay-page.active').forEach(o => o.classList.remove('active'));
            // Remove active from all sidebar items (including settings and help)
            document.querySelectorAll('.side-nav-item').forEach(b => b.classList.remove('active'));

            const tabName = this.dataset.tab;
            let sub = null;
            if (tabName === 'lending') {
                const activeSubBtn = document.querySelector('#panel-lending .lending-nav-btn.active');
                sub = activeSubBtn ? activeSubBtn.dataset.lendingNav : 'browse';
            }
            // Switch to the clicked tab (which will set its active state)
            switchTab(this.dataset.tab);
        });
    });

    /* ── Audit log link ───────────────────────────────────────────────── */
    document.querySelectorAll('.audit-view-all[data-tab]').forEach(a => {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            switchTab(this.dataset.tab);
        });
    });

    /* ── Nav tabs ─────────────────────────────────────────────────────── */
    document.querySelectorAll('.nav-tab').forEach(btn => {
        btn.addEventListener('click', function () {
            switchTab(this.dataset.tab);
        });
    });

    /* ── Account sub-nav — removed in unified settings card layout ──────── */
    // document.querySelectorAll('.acc-nav-btn').forEach(btn => {
    //     btn.addEventListener('click', function () { switchAccTab(this.dataset.accTab); });
    // });

    /* ── Settings sub-nav — removed in unified settings card layout ──────── */
    // document.querySelectorAll('.s-nav-item').forEach(btn => {
    //     btn.addEventListener('click', function () { switchSettTab(this.dataset.settTab); });
    // });

    /* ── Settings Overlay sidebar tabs (sov-nav-item) ────────────────── */
    document.querySelectorAll('.sov-nav-item[data-sov-tab]').forEach(function (navItem) {
        navItem.addEventListener('click', function (e) {
            e.preventDefault();
            var targetId = this.dataset.sovTab;
            // Update nav items
            document.querySelectorAll('.sov-nav-item').forEach(function (n) { n.classList.remove('active'); });
            this.classList.add('active');
            // Update tab panels
            document.querySelectorAll('.sov-tab-panel').forEach(function (p) { p.classList.remove('active'); });
            var panel = document.getElementById(targetId);
            if (panel) panel.classList.add('active');
        });
    });

    /* ── Settings font-size buttons (sov-font-btn mirroring font-scale-btn) */
    document.querySelectorAll('.sov-font-btn[data-scale]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var scale = this.dataset.scale;
            // Mirror onto the hidden font-scale-btn so the existing applyFontScale logic fires
            var legacyBtn = document.querySelector('.font-scale-btn[data-scale="' + scale + '"]');
            if (legacyBtn) legacyBtn.click();
            // Update active state on sov buttons
            document.querySelectorAll('.sov-font-btn').forEach(function (b) { b.classList.remove('font-scale-active'); });
            this.classList.add('font-scale-active');
        });
    });

    /* ── Notification filter tabs ─────────────────────────────────────── */
    document.querySelectorAll('.notif-tab').forEach(btn => {
        btn.addEventListener('click', function () {
            filterNotifs(this.dataset.notifFilter);
        });
    });

    /* ── Equipment search/filter ──────────────────────────────────────── */
    const eqSearch = document.getElementById('equipmentSearch');
    const eqCat = document.getElementById('categoryFilter');
    if (eqSearch) eqSearch.addEventListener('input', filterEquipment);
    if (eqCat) eqCat.addEventListener('change', filterEquipment);

    /* ── Global dashboard search ─────────────────────────────────────── */
    // DORMANT: global search is switched off for now (its markup was removed
    // together with the top bar). To bring it back: restore the markup in
    // faculty-dashboard.php and set this flag to true.
    const GLOBAL_SEARCH_ENABLED = false;
    const globalSearch = GLOBAL_SEARCH_ENABLED ? document.getElementById('globalSearch') : null;
    const globalSearchSelector = [
        '#panel-lending .item-node',
        '#panel-rooms .fcty-campus-card',
        '#panel-activity #myactHistList .myact-history-row'
    ].join(',');

    function filterGlobalDashboard() {
        const query = (globalSearch ? globalSearch.value : '').trim().toLowerCase();
        const activePanel = document.querySelector('.tab-panel.active');
        if (!activePanel) return;

        activePanel.querySelectorAll(globalSearchSelector).forEach(item => {
            item.style.display = !query || item.textContent.toLowerCase().includes(query) ? '' : 'none';
        });
    }

    if (globalSearch) globalSearch.addEventListener('input', filterGlobalDashboard);

    const globalSearchWrap = GLOBAL_SEARCH_ENABLED ? document.getElementById('globalSearchWrap') : null;
    const globalSearchToggle = GLOBAL_SEARCH_ENABLED ? document.getElementById('globalSearchToggle') : null;
    if (globalSearchWrap && globalSearchToggle && globalSearch) {
        globalSearchToggle.addEventListener('click', function () {
            const expanded = globalSearchWrap.classList.toggle('expanded');
            globalSearchToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            globalSearchToggle.setAttribute('aria-label', expanded ? 'Close dashboard search' : 'Open dashboard search');
            if (expanded) globalSearch.focus();
        });

    }

    /* Pagination: prev/next + first paint */
    const eqPrev = document.getElementById('equipPrevBtn');
    const eqNext = document.getElementById('equipNextBtn');
    if (eqPrev) eqPrev.addEventListener('click', () => goToEquipmentPage(eqCurrentPage - 1));
    if (eqNext) eqNext.addEventListener('click', () => goToEquipmentPage(eqCurrentPage + 1));
    if (document.getElementById('equipmentList')) renderEquipmentPage();

    /* ── Settings toggles — compact/reduceMotion/focusRing removed from new design */
    // const compactToggle = document.getElementById('compactToggle');
    // if (compactToggle) compactToggle.addEventListener('change', function () { applyCompact(this.checked); });

    const fontSizeRange = document.getElementById('fontSizeRange');
    if (fontSizeRange) fontSizeRange.addEventListener('input', function () {
        applyFontSize(this.value);
    });

    // const reduceMotionToggle = document.getElementById('reduceMotionToggle');
    // if (reduceMotionToggle) reduceMotionToggle.addEventListener('change', function () { applyReduceMotion(this.checked); });

    // const focusRingToggle = document.getElementById('focusRingToggle');
    // if (focusRingToggle) focusRingToggle.addEventListener('change', function () { applyFocusRing(this.checked); });

    /* ── Unified theme dropdown ───────────────────────────────────────── */
    const themeSelectUnified = document.getElementById('themeSelectUnified');
    if (themeSelectUnified) {
        themeSelectUnified.addEventListener('change', function () {
            applyTheme(this.value);
        });
    }

    /* ── Unified font-scale 3-button toggle ──────────────────────────── */
    document.querySelectorAll('.u-font-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.u-font-btn').forEach(b => b.classList.remove('u-font-btn-active'));
            this.classList.add('u-font-btn-active');
            applyFontSize(this.dataset.scale);
            const range = document.getElementById('fontSizeRange');
            if (range) range.value = this.dataset.scale;
        });
    });

    /* ── Page Init ────────────────────────────────────────────────────── */
    function initPage() {
        // Restore settings, account edits, and notification state from localStorage
        // (called before URL/slug logic so themes apply before first paint)
        restorePersistedState();

        // Ensure no overlay is visible by default on initial page load
        document.querySelectorAll('.overlay-page.active').forEach(o => o.classList.remove('active'));

        // URL slug
        // USER_SLUG is injected by PHP into window.USER_SLUG via the inline <script>
        // tag in user-dashboard.php — never embed PHP directly in a .js file.
        const userSlug = window.USER_SLUG || '';
        if (!window.location.search.includes(userSlug)) {
            const newUrl = window.location.protocol + '//' + window.location.host +
                window.location.pathname + '?u=' + userSlug;
            window.history.replaceState({
                type: 'tab',
                value: 'home',
                sub: null
            }, '', newUrl);
        } else {
            // Stamp the initial home state so popstate has something to land on
            window.history.replaceState({
                type: 'tab',
                value: 'home',
                sub: null
            }, '', window.location.href.split('#')[0]);
        }
        // Auto-hide success alert + clean URL param
        const sa = document.getElementById('success-alert');
        if (sa) {
            const url = new URL(window.location);
            url.searchParams.delete('success');
            window.history.replaceState({
                type: 'tab',
                value: 'home',
                sub: null
            }, document.title, url.pathname + (url.search || ''));
            setTimeout(() => {
                if (sa) sa.style.display = 'none';
            }, 5000);
        }
        initBorrowForm();
        checkOverdueState();
        const overdueToastEl = document.getElementById('overdue-alert');
        if (overdueToastEl && overdueToastEl.dataset.shouldShow === '1') showOverdueToast();

        // AI chatbot FAB: previously it stayed hidden (it starts with
        // display:none in the markup) until the user switched tabs away
        // from Dashboard and back, because the code that reveals it only
        // ran inside _switchTabDOM(). Sync it here too so it's already
        // visible on the very first load, matching whichever tab is
        // actually active server-side.
        const aiFabInit = document.getElementById('actAiFab');
        const initialActivePanel = document.querySelector('.tab-panel.active');
        if (aiFabInit && initialActivePanel && initialActivePanel.id === 'panel-home') {
            aiFabInit.style.display = '';
        }

        startRequestsPolling();
        initCodePanel();
        startInventoryPolling();
        startReservationsPolling();

        // Password strength meter
        const pwNewInput = document.getElementById('pwNew');
        if (pwNewInput) pwNewInput.addEventListener('input', function () { checkPwStrength(this.value); });

        // Close pw modal on backdrop click
        const pwModal = document.getElementById('pwModal');
        if (pwModal) {
            pwModal.addEventListener('click', function (e) {
                if (e.target === this) closePwModal();
            });
        }

        // Close borrow modal on backdrop click
        const borrowModal = document.getElementById('borrowModal');
        if (borrowModal) {
            borrowModal.addEventListener('click', function (e) {
                if (e.target === this) closeBorrowModal();
            });
        }

        // Password show/hide toggles
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.pw-toggle-btn');
            if (!btn) return;
            const targetId = btn.dataset.pwTarget;
            const inp = document.getElementById(targetId);
            if (!inp) return;
            inp.type = inp.type === 'password' ? 'text' : 'password';
            const svg = btn.querySelector('svg');
            if (svg) svg.style.opacity = inp.type === 'text' ? '1' : '0.5';
        });

        // Submit password on Enter key inside pw modal
        document.addEventListener('keydown', function (e) {
            const modal = document.getElementById('pwModal');
            if (modal && modal.style.display !== 'none' && e.key === 'Enter') {
                submitPasswordChange();
            }
            if (modal && modal.style.display !== 'none' && e.key === 'Escape') {
                closePwModal();
            }
            if (e.key === 'Escape') {
                const bm = document.getElementById('borrowModal');
                if (bm && bm.style.display !== 'none') closeBorrowModal();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPage);
    } else {
        initPage();
    }

    /* ══════════════════════════════════════════════════════════════════
       ACCOUNT COMPLETION PROGRESS BAR - INLINE VERSION
    ══════════════════════════════════════════════════════════════════ */
    function updateCompletionProgress() {
        fetch('equipment-booking/api/update-profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_completion_status&csrf_token=' + encodeURIComponent(getCsrfToken())
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const percentage = data.percentage;
                    const bar = document.getElementById('completionBar');
                    const label = document.getElementById('completionPercentage');
                    const container = document.getElementById('inlineProgressContainer');
                    const tooltipHint = document.getElementById('tooltipHint');

                    if (bar) bar.style.width = percentage + '%';
                    if (label) label.textContent = percentage + '%';

                    // Hide entire progress bar when 100% complete
                    if (container) {
                        if (percentage === 100) {
                            container.classList.add('hidden');
                        } else {
                            container.classList.remove('hidden');
                        }
                    }

                    if (tooltipHint) {
                        if (percentage === 100) {
                            tooltipHint.textContent = '✓ Profile complete! You have access to all features.';
                        } else {
                            const remaining = Math.ceil((100 - percentage) / 7.7);
                            tooltipHint.textContent = `${remaining} field${remaining > 1 ? 's' : ''} remaining to complete your profile`;
                        }
                    }
                }
            })
            .catch(err => console.error('Failed to fetch completion status:', err));
    }

    // Update progress on page load and after saves
    if (document.getElementById('inlineProgressContainer')) {
        updateCompletionProgress();
    }

    /* ══════════════════════════════════════════════════════════════════
       ACADEMIC INFO - EDIT/SAVE/CANCEL
    ══════════════════════════════════════════════════════════════════ */
    let academicOriginalValues = {};

    function initAcademicSection() {
        const editBtn = document.getElementById('editAcademicBtn');
        const saveBtn = document.getElementById('saveAcademicBtn');
        const cancelBtn = document.getElementById('cancelAcademicBtn');

        if (!editBtn) return;

        editBtn.addEventListener('click', function () {
            // Store original values
            academicOriginalValues = {
                program: document.querySelector('[data-input="program"]')?.value || '',
                year_level: document.querySelector('[data-input="year_level"]')?.value || ''
            };

            // Show inputs, hide displays
            document.querySelectorAll('#acc-academic .info-input-f').forEach(inp => {
                const field = inp.getAttribute('data-input');
                const display = document.querySelector(`#acc-academic [data-field="${field}"]`);
                if (display) {
                    // Set input value from display
                    if (inp.tagName === 'SELECT') {
                        const currentText = display.textContent.trim();
                        if (currentText !== '— Not provided') {
                            inp.value = currentText;
                        }
                    }
                    display.style.display = 'none';
                }
                inp.style.display = 'block';
                inp.disabled = false;
            });

            editBtn.style.display = 'none';
            saveBtn.style.display = 'inline-flex';
            cancelBtn.style.display = 'inline-flex';
        });

        cancelBtn.addEventListener('click', function () {
            // Restore original values and hide inputs
            document.querySelectorAll('#acc-academic .info-input-f').forEach(inp => {
                const field = inp.getAttribute('data-input');
                inp.value = academicOriginalValues[field] || '';
                inp.style.display = 'none';
                inp.disabled = true;
                const display = document.querySelector(`#acc-academic [data-field="${field}"]`);
                if (display) display.style.display = 'block';
            });

            editBtn.style.display = 'inline-flex';
            saveBtn.style.display = 'none';
            cancelBtn.style.display = 'none';
        });

        saveBtn.addEventListener('click', function () {
            const program = document.querySelector('[data-input="program"]')?.value || '';
            const year_level = document.querySelector('[data-input="year_level"]')?.value || '';

            const formData = new FormData();
            formData.append('action', 'save_academic');
            formData.append('csrf_token', getCsrfToken());
            formData.append('program', program);
            formData.append('year_level', year_level);

            fetch('equipment-booking/api/update-profile.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.needsConfirmation) {
                        // Show confirmation modal
                        showConfirmationModal(data.changes, 'academic', data.data);
                    } else if (data.success) {
                        showToast(data.msg, 'success');
                        updateCompletionProgress();
                    } else {
                        showToast(data.msg, 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showToast('Failed to save academic info', 'error');
                });
        });
    }

    /* ══════════════════════════════════════════════════════════════════
       CONTACT DETAILS - EDIT/SAVE/CANCEL
    ══════════════════════════════════════════════════════════════════ */
    let contactOriginalValues = {};

    function initContactSection() {
        const editBtn = document.getElementById('editContactBtn');
        const saveBtn = document.getElementById('saveContactBtn');
        const cancelBtn = document.getElementById('cancelContactBtn');

        if (!editBtn) return;

        editBtn.addEventListener('click', function () {
            // Store original values
            contactOriginalValues = {
                phone: document.querySelector('[data-input="phone"]')?.value || '',
                present_address: document.querySelector('[data-input="present_address"]')?.value || '',
                permanent_address: document.querySelector('[data-input="permanent_address"]')?.value || '',
                landline: document.querySelector('[data-input="landline"]')?.value || ''
            };

            // Show inputs, hide displays
            document.querySelectorAll('#acc-contact .info-input-f').forEach(inp => {
                const field = inp.getAttribute('data-input');
                const display = document.querySelector(`#acc-contact [data-field="${field}"]`);
                if (display) {
                    const currentText = display.textContent.trim();
                    if (currentText !== '— Not provided') {
                        inp.value = currentText;
                    }
                    display.style.display = 'none';
                }
                inp.style.display = 'block';
                inp.disabled = false;
            });

            editBtn.style.display = 'none';
            saveBtn.style.display = 'inline-flex';
            cancelBtn.style.display = 'inline-flex';
        });

        cancelBtn.addEventListener('click', function () {
            document.querySelectorAll('#acc-contact .info-input-f').forEach(inp => {
                const field = inp.getAttribute('data-input');
                inp.value = contactOriginalValues[field] || '';
                inp.style.display = 'none';
                inp.disabled = true;
                const display = document.querySelector(`#acc-contact [data-field="${field}"]`);
                if (display) display.style.display = 'block';
            });

            editBtn.style.display = 'inline-flex';
            saveBtn.style.display = 'none';
            cancelBtn.style.display = 'none';
        });

        saveBtn.addEventListener('click', function () {
            const formData = new FormData();
            formData.append('action', 'save_contact');
            formData.append('csrf_token', getCsrfToken());
            formData.append('phone', document.querySelector('[data-input="phone"]')?.value || '');
            formData.append('present_address', document.querySelector('[data-input="present_address"]')?.value || '');
            formData.append('permanent_address', document.querySelector('[data-input="permanent_address"]')?.value || '');
            formData.append('landline', document.querySelector('[data-input="landline"]')?.value || '');

            fetch('equipment-booking/api/update-profile.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.needsConfirmation) {
                        showConfirmationModal(data.changes, 'contact', data.data);
                    } else if (data.success) {
                        showToast(data.msg, 'success');
                        updateCompletionProgress();
                    } else {
                        showToast(data.msg, 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showToast('Failed to save contact details', 'error');
                });
        });
    }

    /* ══════════════════════════════════════════════════════════════════
       EMERGENCY CONTACT - EDIT/SAVE/CANCEL
    ══════════════════════════════════════════════════════════════════ */
    let emergencyOriginalValues = {};

    function initEmergencySection() {
        const editBtn = document.getElementById('editEmergencyBtn');
        const saveBtn = document.getElementById('saveEmergencyBtn');
        const cancelBtn = document.getElementById('cancelEmergencyBtn');

        if (!editBtn) return;

        editBtn.addEventListener('click', function () {
            // Store original values
            emergencyOriginalValues = {
                emergency_name: document.querySelector('[data-input="emergency_name"]')?.value || '',
                emergency_relationship: document.querySelector('[data-input="emergency_relationship"]')?.value || '',
                emergency_phone: document.querySelector('[data-input="emergency_phone"]')?.value || ''
            };

            // Show inputs, hide displays
            document.querySelectorAll('#acc-emergency .info-input-f').forEach(inp => {
                const field = inp.getAttribute('data-input');
                const display = document.querySelector(`#acc-emergency [data-field="${field}"]`);
                if (display) {
                    const currentText = display.textContent.trim();
                    if (currentText !== '— Not provided') {
                        inp.value = currentText;
                    }
                    display.style.display = 'none';
                }
                inp.style.display = 'block';
                inp.disabled = false;
            });

            editBtn.style.display = 'none';
            saveBtn.style.display = 'inline-flex';
            cancelBtn.style.display = 'inline-flex';
        });

        cancelBtn.addEventListener('click', function () {
            document.querySelectorAll('#acc-emergency .info-input-f').forEach(inp => {
                const field = inp.getAttribute('data-input');
                inp.value = emergencyOriginalValues[field] || '';
                inp.style.display = 'none';
                inp.disabled = true;
                const display = document.querySelector(`#acc-emergency [data-field="${field}"]`);
                if (display) display.style.display = 'block';
            });

            editBtn.style.display = 'inline-flex';
            saveBtn.style.display = 'none';
            cancelBtn.style.display = 'none';
        });

        saveBtn.addEventListener('click', function () {
            const formData = new FormData();
            formData.append('action', 'save_emergency');
            formData.append('csrf_token', getCsrfToken());
            formData.append('emergency_name', document.querySelector('[data-input="emergency_name"]')?.value || '');
            formData.append('emergency_relationship', document.querySelector('[data-input="emergency_relationship"]')?.value || '');
            formData.append('emergency_phone', document.querySelector('[data-input="emergency_phone"]')?.value || '');

            fetch('equipment-booking/api/update-profile.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.needsConfirmation) {
                        showConfirmationModal(data.changes, 'emergency', data.data);
                    } else if (data.success) {
                        showToast(data.msg, 'success');
                        updateCompletionProgress();
                    } else {
                        showToast(data.msg, 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showToast('Failed to save emergency contact', 'error');
                });
        });
    }

    /* ══════════════════════════════════════════════════════════════════
       CONFIRMATION MODAL
    ══════════════════════════════════════════════════════════════════ */
    let pendingConfirmation = null;

    function showConfirmationModal(changes, section, data) {
        const modal = document.getElementById('confirmationModal');
        const summary = document.getElementById('changesSummary');
        const warningMsg = document.getElementById('warningMessage');

        if (!modal || !summary) return;

        // Store pending confirmation
        pendingConfirmation = { section, data };

        // Build changes summary
        let html = '';
        let hasLockedFields = false;

        changes.forEach(change => {
            if (change.locked) hasLockedFields = true;

            html += `
            <div class="change-item">
                <div class="change-field">${change.field}</div>
                <div class="change-values">
                    <div class="change-from">From: ${change.from}</div>
                    <div class="change-to">To: ${change.to}</div>
                    ${change.locked ? '<span class="change-locked-badge">🔒 Cannot be changed later</span>' : ''}
                </div>
            </div>
        `;
        });

        summary.innerHTML = html;

        // Update warning message
        if (hasLockedFields) {
            warningMsg.innerHTML = '<strong>Locked fields:</strong> Some changes marked with 🔒 cannot be modified once saved. Please verify them carefully.';
        } else {
            warningMsg.textContent = 'Please verify all information before confirming.';
        }

        modal.style.display = 'flex';
    }

    function closeConfirmationModal() {
        const modal = document.getElementById('confirmationModal');
        if (modal) modal.style.display = 'none';
        pendingConfirmation = null;
    }

    function confirmChanges() {
        if (!pendingConfirmation) return;

        const { section, data } = pendingConfirmation;
        const formData = new FormData();
        formData.append('action', `confirm_save_${section}`);
        formData.append('csrf_token', getCsrfToken());

        // Add all data fields
        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });

        fetch('equipment-booking/api/update-profile.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                closeConfirmationModal();

                if (data.success) {
                    showToast(data.msg, 'success');

                    // Update UI with new values
                    Object.keys(data).forEach(key => {
                        if (key !== 'success' && key !== 'msg') {
                            const display = document.querySelector(`[data-field="${key}"]`);
                            if (display) {
                                display.textContent = data[key] || '— Not provided';
                                if (data[key]) {
                                    display.classList.remove('empty');
                                } else {
                                    display.classList.add('empty');
                                }
                            }
                        }
                    });

                    // Reset edit mode
                    const sectionId = section === 'profile' ? 'acc-overview' :
                        section === 'academic' ? 'acc-academic' :
                            section === 'contact' ? 'acc-contact' : 'acc-emergency';

                    document.querySelectorAll(`#${sectionId} .info-input-f`).forEach(inp => {
                        inp.style.display = 'none';
                        inp.disabled = true;
                    });

                    const editBtn = document.getElementById(`edit${section.charAt(0).toUpperCase() + section.slice(1)}Btn`);
                    const saveBtn = document.getElementById(`save${section.charAt(0).toUpperCase() + section.slice(1)}Btn`);
                    const cancelBtn = document.getElementById(`cancel${section.charAt(0).toUpperCase() + section.slice(1)}Btn`);

                    if (editBtn) editBtn.style.display = 'inline-flex';
                    if (saveBtn) saveBtn.style.display = 'none';
                    if (cancelBtn) cancelBtn.style.display = 'none';

                    updateCompletionProgress();
                } else {
                    showToast(data.msg, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                closeConfirmationModal();
                showToast('Failed to save changes', 'error');
            });
    }

    /* ══════════════════════════════════════════════════════════════════
       MODIFIED PROFILE SAVE TO USE CONFIRMATION
    ══════════════════════════════════════════════════════════════════ */
    // This function should be added to or replace the existing profile save handler
    function handleProfileSaveWithConfirmation() {
        const saveBtn = document.getElementById('saveProfileBtn');
        if (!saveBtn) return;

        // Remove existing listeners (if any) and add new one
        const newSaveBtn = saveBtn.cloneNode(true);
        saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);

        newSaveBtn.addEventListener('click', function () {
            const fullname = (document.querySelector('[data-input="fullname"]')?.value || '').trim();
            const dob = document.querySelector('[data-input="dob"]')?.value || '';
            const gender = document.querySelector('[data-input="gender"]')?.value || '';
            const nationality = document.querySelector('[data-input="nationality"]')?.value || '';

            const formData = new FormData();
            formData.append('action', 'save_profile');
            formData.append('csrf_token', getCsrfToken());
            formData.append('fullname', fullname);
            formData.append('dob', dob);
            formData.append('gender', gender);
            formData.append('nationality', nationality);

            fetch('equipment-booking/api/update-profile.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.needsConfirmation) {
                        showConfirmationModal(data.changes, 'profile', { fullname, dob, gender, nationality });
                    } else if (data.success) {
                        showToast(data.msg, 'success');
                        updateCompletionProgress();
                    } else {
                        showToast(data.msg, 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showToast('Failed to save profile', 'error');
                });
        });
    }

    /* ══════════════════════════════════════════════════════════════════
       EVENT LISTENERS FOR MODALS AND BUTTONS
    ══════════════════════════════════════════════════════════════════ */
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize sections
        initAcademicSection();
        initContactSection();
        initEmergencySection();
        handleProfileSaveWithConfirmation();

        // Confirmation modal buttons
        const confirmBtn = document.getElementById('confirmChangesBtn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', confirmChanges);
        }

        // Close confirmation modal buttons
        document.querySelectorAll('[data-action="close-confirmation-modal"]').forEach(btn => {
            btn.addEventListener('click', closeConfirmationModal);
        });

        // Update progress when account overlay opens
        const accountOverlay = document.getElementById('accountOverlay');
        if (accountOverlay) {
            const observer = new MutationObserver(mutations => {
                mutations.forEach(mutation => {
                    if (mutation.attributeName === 'class') {
                        if (accountOverlay.classList.contains('active')) {
                            updateCompletionProgress();
                        }
                    }
                });
            });
            observer.observe(accountOverlay, { attributes: true });
        }

        // ═══════════════════════════════════════════════════════════════
        // CHANGE PROFILE BUTTON FUNCTIONALITY
        // ═══════════════════════════════════════════════════════════════
        const changeProfileBtn = document.getElementById('changeProfileBtn');
        const pictureMenu = document.getElementById('pictureMenu');
        const profilePicInput = document.getElementById('profilePicInput');

        // Toggle picture menu
        if (changeProfileBtn && pictureMenu) {
            changeProfileBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                const isVisible = pictureMenu.style.display === 'block';
                pictureMenu.style.display = isVisible ? 'none' : 'block';
            });
        }

        // Close menu when clicking outside
        document.addEventListener('click', function (e) {
            if (pictureMenu && !pictureMenu.contains(e.target) && e.target !== changeProfileBtn) {
                pictureMenu.style.display = 'none';
            }
        });

        // Handle upload picture
        document.querySelectorAll('[data-action="upload-picture"]').forEach(btn => {
            btn.addEventListener('click', function () {
                if (profilePicInput) {
                    profilePicInput.click();
                }
                if (pictureMenu) pictureMenu.style.display = 'none';
            });
        });

        // Handle file selection
        if (profilePicInput) {
            profilePicInput.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (!file) return;

                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    showToast('Invalid file type. Please upload JPG, PNG, or WEBP.', 'error');
                    return;
                }

                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    showToast('File too large. Maximum size is 5MB.', 'error');
                    return;
                }

                // Upload file
                const formData = new FormData();
                formData.append('action', 'upload_profile_picture');
                formData.append('csrf_token', getCsrfToken());
                formData.append('profile_picture', file);

                fetch('equipment-booking/api/update-profile.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.msg, 'success');
                            // Update all avatar displays
                            const newPicUrl = window.SERVER_BASE_URL + data.profile_picture + '?t=' + Date.now();
                            document.querySelectorAll('#profileAvatarLarge, .side-nav-avatar, .avatar-btn').forEach(el => {
                                el.innerHTML = `<img src="${newPicUrl}" alt="Profile" class="avatar-img">`;
                            });
                            updateCompletionProgress();
                        } else {
                            showToast(data.msg, 'error');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showToast('Failed to upload profile picture', 'error');
                    });

                // Reset input
                profilePicInput.value = '';
            });
        }

        // Handle remove picture
        document.querySelectorAll('[data-action="remove-picture"]').forEach(btn => {
            btn.addEventListener('click', function () {
                if (!confirm('Are you sure you want to remove your profile picture?')) {
                    return;
                }

                fetch('equipment-booking/api/update-profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=remove_profile_picture&csrf_token=' + encodeURIComponent(getCsrfToken())
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.msg, 'success');
                            // Get initials from the page
                            const fullname = document.querySelector('.side-nav-user-name')?.textContent || 'U';
                            const parts = fullname.trim().split(' ');
                            let initials = parts[0].charAt(0).toUpperCase();
                            if (parts.length > 1) initials += parts[parts.length - 1].charAt(0).toUpperCase();

                            // Update all avatar displays to show initials
                            document.querySelectorAll('#profileAvatarLarge, .side-nav-avatar, .avatar-btn').forEach(el => {
                                el.innerHTML = initials;
                            });

                            // Reload page to update the UI completely
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showToast(data.msg, 'error');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showToast('Failed to remove profile picture', 'error');
                    });

                if (pictureMenu) pictureMenu.style.display = 'none';
            });
        });
    });

    /* ══════════════════════════════════════════════════════════════════
       HELPER FUNCTION - TOAST NOTIFICATIONS
       (second definition removed — using the #app-toast element above)
    ══════════════════════════════════════════════════════════════════ */
    // showToast is already defined above using #app-toast


    /* ── Return QR Modal ───────────────────────────────────────────────── */
    function _openReturnQrModal(equipment, url) {
        // Reuse or create the modal
        let modal = document.getElementById('returnQrModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'returnQrModal';
            modal.style.cssText = 'display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;';
            modal.innerHTML = `
                <div style="background:var(--color-surface,#fff);border-radius:20px;padding:2rem;max-width:360px;width:90%;text-align:center;position:relative;">
                    <button id="returnQrClose" style="position:absolute;top:12px;right:12px;background:none;border:none;cursor:pointer;font-size:20px;color:var(--color-on-surface-variant);">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                    <span class="material-symbols-outlined" style="font-size:36px;color:var(--accent-maroon,#600302);margin-bottom:8px;display:block;">qr_code_2</span>
                    <h3 id="returnQrTitle" style="font-size:1rem;font-weight:700;margin-bottom:4px;"></h3>
                    <p style="font-size:0.8rem;color:var(--color-on-surface-variant);margin-bottom:16px;">Show this QR code to the admin when returning the equipment.</p>
                    <div id="returnQrCanvas" style="display:flex;justify-content:center;margin-bottom:16px;"></div>
                    <p style="font-size:0.7rem;color:var(--color-on-surface-variant);word-break:break-all;">Token verified on scan</p>
                </div>`;
            document.body.appendChild(modal);
            document.getElementById('returnQrClose').addEventListener('click', () => {
                modal.style.display = 'none';
            });
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.style.display = 'none';
            });
        }
        document.getElementById('returnQrTitle').textContent = equipment;
        document.getElementById('returnQrCanvas').setAttribute('title', url);
        document.getElementById('returnQrCanvas').innerHTML = '';
        modal.style.display = 'flex';

        // Load QR library dynamically (only once)
        if (window._qrLoaded) {
            _renderQr(url);
        } else {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js';
            script.onload = () => { window._qrLoaded = true; _renderQr(url); };
            document.head.appendChild(script);
        }
    }

    function _renderQr(url) {
        const container = document.getElementById('returnQrCanvas');
        if (!container || typeof QRCode === 'undefined') return;
        container.innerHTML = '';
        const canvasEl = document.createElement('canvas');
        canvasEl.style.cssText = 'border-radius:8px;display:block;margin:0 auto;';
        container.appendChild(canvasEl);
        QRCode.toCanvas(canvasEl, url, {
            width: 300, margin: 2,
            color: { dark: '#600302', light: '#ffffff' }
        });
    }

    function _updateFacultyStatCards(data) {
        const counts = {
            approved: data.filter(r => r.status === 'Approved').length,
            overdue: data.filter(r => r.status === 'Overdue').length,
            total: data.length,
        };
        document.querySelectorAll('.stat-tile[data-stat]').forEach(tile => {
            const key = tile.dataset.stat;
            if (!(key in counts)) return;
            const val = tile.querySelector('.stat-tile-value');
            if (val && val.textContent.trim() !== String(counts[key])) val.textContent = counts[key];
        });
    }

    function startInventoryPolling() {
        const INTERVAL = 8000;

        function doPoll() {
            fetch('equipment-booking/api/poll-inventory.php', { method: 'GET' })
                .then(r => r.json())
                .then(items => {
                    if (!Array.isArray(items)) return;
                    items.forEach(function (item) {
                        const card = document.querySelector('.item-node[data-item-id="' + item.item_id + '"]');
                        if (!card) return;
                        const qty = parseInt(item.quantity, 10);
                        const total = parseInt(item.total, 10);
                        if (window.BOOKING_META && window.BOOKING_META.items && item.item_name) {
                            window.BOOKING_META.items[item.item_name] = { shelf: qty, total: isNaN(total) ? qty : total };
                        }

                        // Update availability badge
                        const badge = card.querySelector('.stock-badge');
                        if (badge) {
                            if (qty > 0) {
                                badge.className = 'stock-badge stock-avail';
                                badge.innerHTML = '<span class="material-symbols-outlined" style="font-size:12px;">check_circle</span> ' + qty + ' available';
                            } else {
                                badge.className = 'stock-badge stock-unavail';
                                badge.innerHTML = '<span class="material-symbols-outlined" style="font-size:12px;">cancel</span> Out of stock';
                            }
                        }

                        // Update borrow button
                        const btn = card.querySelector('.btn-borrow[data-action="open-borrow-form"]');
                        if (btn) {
                            // Out right now but it exists in stock: it can still be booked for a later date
                            const canBookAhead = qty <= 0 && (isNaN(total) ? false : total > 0);
                            btn.disabled = qty <= 0 && !canBookAhead;
                            btn.classList.toggle('btn-borrow-ahead', canBookAhead);
                            btn.textContent = qty > 0 ? 'Borrow' : (canBookAhead ? 'Book ahead' : 'Unavailable');
                        }
                    });
                })
                .catch(function () { });
        }

        doPoll();                        // run immediately on page load
        setInterval(doPoll, INTERVAL);   // then every 8 seconds
    }

    /* ── Real-time Requests Polling ────────────────────────────────────── */
    function startRequestsPolling() {
        const INTERVAL = 5000; // check every 5 seconds
        let lastStatuses = {};

        // Build initial status snapshot
        (window.REQUESTS_DATA || []).forEach(r => {
            lastStatuses[r.id] = r.status;
        });

        setInterval(function () {
            fetch('equipment-booking/api/poll-requests.php', { method: 'GET' })
                .then(r => r.json())
                .then(fresh => {
                    if (!Array.isArray(fresh)) return;

                    let changed = false;

                    // Check for any status changes
                    fresh.forEach(r => {
                        const prev = lastStatuses[r.id];

                        if (prev !== r.status) {
                            changed = true;
                            lastStatuses[r.id] = r.status;

                            // Only show a toast when the status genuinely transitioned
                            // from a previously-known value. If prev is undefined the
                            // request simply wasn't in the initial snapshot (e.g. it was
                            // already in a terminal state on page load) — no toast needed.
                            if (prev !== undefined) {
                                if (r.status === 'Returned') {
                                    showToast(r.equipment_name + ' has been marked as Returned.', 'success');
                                }
                            }
                        }
                    });

                    if (changed) {
                        // Update the global data and re-render
                        window.REQUESTS_DATA = fresh;
                        window.OVERDUE_COUNT = fresh.filter(r => r.status === 'Overdue').length; // keep checkOverdueState in sync
                        checkOverdueState();
                        _updateFacultyStatCards(fresh);
                    }
                })
                .catch(() => { }); // silently ignore network errors
        }, INTERVAL);
    }

    /* -- Room Reservation Notices ---------------------------------------- */
    /* Reservations are accepted on the spot, and My Activity refreshes its own
       room list (faculty-activity.js), so there is no table to keep in sync
       here any more. This only watches for one thing: an admin cancelling a
       reservation, which is reported with a toast. */
    function startReservationsPolling() {
        const INTERVAL = 10000; // 10 seconds
        var lastStatuses = {};
        var primed = false;

        function doPoll() {
            fetch('room-reservation/api/poll-reservations.php', {
                method: 'GET',
                credentials: 'same-origin',
            })
                .then(function (r) { if (!r.ok) return null; return r.json(); })
                .then(function (rows) {
                    if (!Array.isArray(rows)) return;
                    rows.forEach(function (rr) {
                        var prev = lastStatuses[rr.id];
                        if (primed && prev !== undefined && prev !== rr.status && rr.status === 'Cancelled') {
                            showToast('Your reservation for ' + rr.room_name + ' was cancelled by admin.', 'error');
                        }
                        lastStatuses[rr.id] = rr.status;
                    });
                    primed = true;
                })
                .catch(function () { /* silently ignore network errors */ });
        }

        doPoll();                       // establish the baseline immediately
        setInterval(doPoll, INTERVAL);  // then every 10 seconds

        /* Re-baseline right after the user submits or cancels a reservation so
           the change is never mistaken for an admin action. */
        document.addEventListener('pupsync:reservation-submitted', doPoll);
    }

    /* ── Cancel Reservation handler (event delegation on reservations table) */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-action="cancel-reservation"]');
        if (!btn) return;

        var rrId = btn.dataset.rrId;
        var roomName = btn.dataset.roomName || 'this reservation';

        if (!confirm('Cancel your reservation for ' + roomName + '?\n\nThis cannot be undone.')) return;

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

        btn.disabled = true;
        btn.textContent = 'Cancelling…';

        fetch('room-reservation/api/cancel-reservation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ reservation_id: parseInt(rrId, 10), csrf_token: csrfToken }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    showToast(data.error, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:15px;">cancel</span> Cancel';
                    return;
                }
                showToast('Reservation cancelled successfully.', 'success');
                // Trigger immediate poll refresh
                document.dispatchEvent(new CustomEvent('pupsync:reservation-submitted'));
            })
            .catch(function () {
                showToast('Network error. Please try again.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:15px;">cancel</span> Cancel';
            });
    });

    /* ── Join Waitlist handler (event delegation on reservations table) */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-action="join-waitlist"]');
        if (!btn) return;

        var roomId = btn.dataset.roomId;
        var roomName = btn.dataset.roomName || 'this room';
        var resDate = btn.dataset.resDate;
        var startTime = btn.dataset.startTime;
        var endTime = btn.dataset.endTime;

        if (!confirm('Join the waitlist for ' + roomName + ' on ' + resDate + '?\n\nYou\'ll be emailed if the slot becomes available.')) return;

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

        btn.disabled = true;
        btn.textContent = 'Joining…';

        fetch('room-reservation/api/join-waitlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                room_id: parseInt(roomId, 10),
                reservation_date: resDate,
                start_time: startTime,
                end_time: endTime,
                csrf_token: csrfToken,
            }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    showToast(data.error, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:15px;">notifications</span> Waitlist';
                    return;
                }
                if (data.already) {
                    showToast('You are already on the waitlist for this slot.', 'success');
                } else {
                    showToast('Added to waitlist! You\'ll be emailed if the slot opens up.', 'success');
                }
                btn.disabled = true;
                btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:15px;">notifications_active</span> On Waitlist';
                btn.title = 'You are on the waitlist for this slot';
            })
            .catch(function () {
                showToast('Network error. Please try again.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:15px;">notifications</span> Waitlist';
            });
    });

    /* ── Faculty Code Panel ─────────────────────────────────────────────── */

    function renderCodePanel(data) {
        const body = document.getElementById('fccBody');
        if (!body) return;

        if (!data || !data.has_code) {
            body.innerHTML = '<div class="fcc-no-code">No active code. Generate one below to let a student borrow equipment.</div>';
            window._fccLastUsed = null;
            return;
        }

        const isUsed = data.is_used;

        // Fire toast exactly once when we detect the transition active → used
        if (isUsed && window._fccLastUsed === false) {
            showToast('✅ Code used by ' + _fccEsc(data.used_by_name) + '. A borrow request was auto-approved.', 'success');
        }
        window._fccLastUsed = isUsed;

        const usedInfo = isUsed
            ? `<div class="fcc-used-info">Used by: <strong>${_fccEsc(data.used_by_name)}</strong> (${_fccEsc(data.used_by_id)}) &middot; ${_fccEsc(data.used_at)}</div>`
            : `<div class="fcc-used-info" style="color:var(--color-secondary,#555)">Generated: ${_fccEsc(data.created_at)}</div>`;

        body.innerHTML = `
            <div class="fcc-code-display ${isUsed ? 'fcc-code-used' : ''}">
                <span class="fcc-code-value" id="fccCodeText">${_fccEsc(data.code)}</span>
                ${!isUsed ? `<button class="fcc-copy-btn" id="fccCopyBtn" title="Copy code">
                    <span class="material-symbols-outlined" style="font-size:18px;">content_copy</span>
                </button>` : ''}
            </div>
            <div class="fcc-status-row">
                <span class="fcc-badge ${isUsed ? 'fcc-badge-used' : 'fcc-badge-active'}">
                    <span class="material-symbols-outlined" style="font-size:11px;">${isUsed ? 'lock' : 'check_circle'}</span>
                    ${isUsed ? 'Used' : 'Active'}
                </span>
            </div>
            ${usedInfo}`;

        const copyBtn = document.getElementById('fccCopyBtn');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                const code = document.getElementById('fccCodeText')?.textContent?.trim();
                if (!code) return;
                navigator.clipboard.writeText(code).then(() => {
                    copyBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;color:#2e7d32;">check</span>';
                    setTimeout(() => {
                        copyBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;">content_copy</span>';
                    }, 2000);
                }).catch(() => {
                    const el = document.createElement('textarea');
                    el.value = code;
                    document.body.appendChild(el);
                    el.select();
                    document.execCommand('copy');
                    document.body.removeChild(el);
                    showToast('Code copied!', 'success');
                });
            });
        }
    }

    function _fccEsc(str) {
        return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function initCodePanel() {
        // Load initial state
        fetch('equipment-booking/api/poll-faculty-codes.php', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => { renderCodePanel(data); })
            .catch(() => {
                const body = document.getElementById('fccBody');
                if (body) body.innerHTML = '<div class="fcc-no-code">Could not load code status.</div>';
            });

        // Generate button
        const btn = document.getElementById('btnGenerateCode');
        if (!btn) return;
        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;animation:spin 1s linear infinite;">sync</span> Generating...';

            const fd = new FormData();
            fd.append('csrf_token', getCsrfToken());

            fetch('equipment-booking/api/generate-faculty-code.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">add_circle</span> Generate New Code';

                    if (data.error) { showToast(data.error, 'error'); return; }

                    window._fccWasActive = false;
                    renderCodePanel({
                        has_code: true,
                        code: data.code,
                        is_used: false,
                        created_at: data.created_at,
                        used_by_name: null,
                        used_by_id: null,
                        used_at: null,
                    });
                    showToast('New code generated! Share it with your student.', 'success');
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">add_circle</span> Generate New Code';
                    showToast('Failed to generate code. Please try again.', 'error');
                });
        });
    }

    function startCodePolling() {
        function doPoll() {
            fetch('equipment-booking/api/poll-faculty-codes.php', { credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) return null; return r.json(); })
                .then(function (data) { if (data) renderCodePanel(data); })
                .catch(function () { });
        }

        doPoll();                        // fire immediately
        setInterval(doPoll, 5000);       // then every 5 seconds
    }
})();

/* ══════════════════════════════════════════════════════════════════
   MY ACTIVITY — History pagination + search/filter
   10 rows per page by default (rows already in the DOM, each with
   data-hist-idx="N" — JS slices them, anything outside the current
   page range gets display:none). While a search query or status
   filter is active, pagination is bypassed entirely and every
   matching row is shown at once instead (there's a 200-row ceiling
   server-side, so this stays reasonable).
══════════════════════════════════════════════════════════════════ */
(function () {
    var HIST_PER_PAGE = 10;
    var histCurrentPage = 1;
    var histSearchQuery = '';
    var histStatusFilter = 'all';

    function allHistRows() {
        return Array.from(document.querySelectorAll('#myactHistList .myact-history-row'));
    }

    function isFiltering() {
        return histSearchQuery !== '' || histStatusFilter !== 'all';
    }

    function rowMatches(row) {
        if (histStatusFilter !== 'all' && row.dataset.status !== histStatusFilter) return false;
        if (histSearchQuery && row.textContent.toLowerCase().indexOf(histSearchQuery) === -1) return false;
        return true;
    }

    function renderHistPage() {
        var rows = allHistRows();
        var total = rows.length;
        if (total === 0) return;

        var pg = document.getElementById('myactHistPg');
        var noResults = document.getElementById('myactHistNoResults');

        if (isFiltering()) {
            var matchCount = 0;
            rows.forEach(function (row) {
                var show = rowMatches(row);
                row.style.display = show ? '' : 'none';
                if (show) matchCount++;
            });

            if (pg) {
                var info = document.getElementById('myactHistPgInfo');
                if (info) info.textContent = matchCount + ' of ' + total + ' matching';
                var controls = document.getElementById('myactHistPgControls');
                if (controls) controls.style.display = 'none';
                pg.style.display = '';
            }
            if (noResults) noResults.style.display = matchCount === 0 ? '' : 'none';
            return;
        }

        if (noResults) noResults.style.display = 'none';
        if (pg) {
            var controlsRestore = document.getElementById('myactHistPgControls');
            if (controlsRestore) controlsRestore.style.display = '';
        }

        var totalPages = Math.max(1, Math.ceil(total / HIST_PER_PAGE));
        if (histCurrentPage > totalPages) histCurrentPage = totalPages;
        if (histCurrentPage < 1) histCurrentPage = 1;

        var start = (histCurrentPage - 1) * HIST_PER_PAGE;
        rows.forEach(function (row) {
            var idx = parseInt(row.dataset.histIdx, 10);
            row.style.display = (idx >= start && idx < start + HIST_PER_PAGE) ? '' : 'none';
        });

        /* Pagination bar */
        if (!pg) return;
        pg.style.display = totalPages > 1 ? '' : 'none';
        if (totalPages <= 1) return;

        var info = document.getElementById('myactHistPgInfo');
        if (info) {
            info.textContent = 'Showing ' + (start + 1) + '–' +
                Math.min(start + HIST_PER_PAGE, total) + ' of ' + total;
        }

        var prev = document.getElementById('myactHistPrev');
        var next = document.getElementById('myactHistNext');
        if (prev) prev.disabled = histCurrentPage === 1;
        if (next) next.disabled = histCurrentPage === totalPages;

        var nums = document.getElementById('myactHistNums');
        if (!nums) return;
        nums.innerHTML = '';
        var pages = [];
        if (totalPages <= 7) {
            for (var i = 1; i <= totalPages; i++) pages.push(i);
        } else {
            pages.push(1);
            var lo = Math.max(2, histCurrentPage - 1);
            var hi = Math.min(totalPages - 1, histCurrentPage + 1);
            if (lo > 2) pages.push('…');
            for (var i = lo; i <= hi; i++) pages.push(i);
            if (hi < totalPages - 1) pages.push('…');
            pages.push(totalPages);
        }
        pages.forEach(function (p) {
            if (p === '…') {
                var s = document.createElement('span');
                s.className = 'myact-hist-pg-ellipsis';
                s.textContent = '…';
                nums.appendChild(s);
                return;
            }
            var b = document.createElement('button');
            b.className = 'myact-hist-pg-num' + (p === histCurrentPage ? ' active' : '');
            b.textContent = p;
            b.addEventListener('click', function () { goToHistPage(p); });
            nums.appendChild(b);
        });
    }

    function goToHistPage(page) {
        histCurrentPage = page;
        renderHistPage();
        var list = document.getElementById('myactHistList');
        if (list) list.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /* Wire prev/next */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#myactHistPrev, #myactHistNext');
        if (!btn) return;
        if (btn.id === 'myactHistPrev') goToHistPage(histCurrentPage - 1);
        if (btn.id === 'myactHistNext') goToHistPage(histCurrentPage + 1);
    });

    /* Wire search box */
    document.addEventListener('input', function (e) {
        if (e.target.id !== 'myactHistSearch') return;
        histSearchQuery = e.target.value.trim().toLowerCase();
        renderHistPage();
    });

    /* Wire status filter pills */
    document.addEventListener('click', function (e) {
        var tab = e.target.closest('.myact-filter-tab');
        if (!tab) return;
        var group = tab.closest('.myact-filter-tabs');
        if (group) group.querySelectorAll('.myact-filter-tab').forEach(function (t) {
            t.classList.remove('active');
        });
        tab.classList.add('active');
        histStatusFilter = tab.dataset.statusFilter || 'all';
        renderHistPage();
    });

    /* Init on DOMContentLoaded */
    function init() { if (document.getElementById('myactHistList')) renderHistPage(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
/* ══════════════════════════════════════════════════════════════════
   AI CHAT — send function + Enter-key wiring
   (moved from inline <script> so we no longer need a nonce and CSP
   inline-script policy stays tight)
══════════════════════════════════════════════════════════════════ */
(function () {
    function actAiSend() {
        var input = document.getElementById('actAiInput');
        var msg = (input ? input.value : '').trim();
        if (!msg) return;
        var body = document.getElementById('actAiChatBody');
        if (!body) return;

        /* User bubble */
        var uDiv = document.createElement('div');
        uDiv.className = 'act-chat-msg act-chat-msg-user';
        uDiv.innerHTML = '<span class="act-chat-msg-time">You</span>' +
            '<div class="act-chat-bubble act-chat-bubble-user">' +
            msg.replace(/</g, '&lt;') + '</div>';
        body.appendChild(uDiv);
        input.value = '';
        body.scrollTop = body.scrollHeight;

        /* Stub AI reply — wire to real endpoint later */
        setTimeout(function () {
            var aDiv = document.createElement('div');
            aDiv.className = 'act-chat-msg';
            aDiv.innerHTML = '<span class="act-chat-msg-time">AI Assistant</span>' +
                '<div class="act-chat-bubble act-chat-bubble-ai">' +
                'Thanks for your message! AI response support coming soon.' +
                '</div>';
            body.appendChild(aDiv);
            body.scrollTop = body.scrollHeight;
        }, 600);
    }

    /* Expose so any other surface can call it (keeps parity with the
       old global function that lived in the inline script) */
    window.actAiSend = actAiSend;

    /* Enter key on the chat input */
    function init() {
        var inp = document.getElementById('actAiInput');
        if (inp) {
            inp.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') actAiSend();
            });
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();


/* ================================================================
   MY ACTIVITY  (faculty)  --  behaviour for #panel-activity

   1. History table   filter All / Equipment / Rooms, search, paging
   2. Show all        expands the Currently borrowing / Upcoming lists
   3. Download Report prints the whole record (CSS un-pages it)
   4. Rooms, live     re-fetches room-reservation/api/poll-reservations.php?view=activity
                      and swaps the Room reservations card, the room rows of
                      the history table and the two room numbers in the summary
                      strip. Runs after a booking / cancellation, when the tab
                      is opened, and every 30 s while visible.

   Cancel / Join waitlist / Report issue / go-tab buttons are handled by the
   delegated handlers above (data-action="..."). No inline event handlers are
   used (the page's CSP forbids them).
   The older "myactHist*" ledger code above is no longer wired to any markup.
================================================================ */
(function () {
    'use strict';

    function startMyActivity() {

        var PER_PAGE = 10;
        var REFRESH_MS = 30000;
        var API_ROOMS = 'room-reservation/api/poll-reservations.php?view=activity';

        var panel = document.getElementById('panel-activity');
        var body = document.getElementById('factHistBody');
        if (!panel || !body) return;

        var $ = function (id) { return document.getElementById(id); };
        var noResults = $('factNoResults');
        var histEmpty = $('factHistEmpty');
        var tableWrap = panel.querySelector('.fact-table-wrap');
        var pager = $('factPager');
        var pagerInfo = $('factPagerInfo');
        var pagerNums = $('factNums');
        var btnPrev = $('factPrev');
        var btnNext = $('factNext');
        var search = $('factSearch');

        var state = { kind: 'all', query: '', page: 1 };

        /* ── 1. History: sort, filter, page ──────────────────────── */
        function rows() {
            return Array.prototype.slice.call(body.querySelectorAll('tr.fact-row'));
        }

        /* Newest first, across equipment and rooms */
        function sortRows() {
            var list = rows();
            list.sort(function (a, b) {
                return (parseInt(b.dataset.ts, 10) || 0) - (parseInt(a.dataset.ts, 10) || 0);
            });
            list.forEach(function (tr) { body.appendChild(tr); });
        }

        function pageList(cur, total) {
            if (total <= 7) {
                var all = [];
                for (var i = 1; i <= total; i++) all.push(i);
                return all;
            }
            var keep = {};
            [1, total, cur - 1, cur, cur + 1].forEach(function (n) {
                if (n >= 1 && n <= total) keep[n] = true;
            });
            var nums = Object.keys(keep).map(Number).sort(function (a, b) { return a - b; });
            var out = [];
            nums.forEach(function (n, idx) {
                if (idx && n - nums[idx - 1] > 1) out.push('gap');
                out.push(n);
            });
            return out;
        }

        function renderPager(matched) {
            var pages = Math.max(1, Math.ceil(matched / PER_PAGE));
            if (matched <= PER_PAGE) { pager.hidden = true; return; }
            pager.hidden = false;

            var from = (state.page - 1) * PER_PAGE + 1;
            var to = Math.min(matched, state.page * PER_PAGE);
            pagerInfo.textContent = 'Showing ' + from + '\u2013' + to + ' of ' + matched;

            btnPrev.disabled = state.page <= 1;
            btnNext.disabled = state.page >= pages;

            pagerNums.innerHTML = '';
            pageList(state.page, pages).forEach(function (n) {
                if (n === 'gap') {
                    var gap = document.createElement('span');
                    gap.className = 'fact-pager-gap';
                    gap.textContent = '\u2026';
                    pagerNums.appendChild(gap);
                    return;
                }
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'fact-pager-btn' + (n === state.page ? ' is-active' : '');
                b.dataset.page = String(n);
                b.textContent = String(n);
                if (n === state.page) b.setAttribute('aria-current', 'page');
                pagerNums.appendChild(b);
            });
        }

        function apply() {
            var q = state.query.trim().toLowerCase();
            var list = rows();
            var matched = [];

            list.forEach(function (tr) {
                var okKind = state.kind === 'all' || tr.dataset.kind === state.kind;
                var okText = !q || tr.textContent.toLowerCase().indexOf(q) !== -1;
                var show = okKind && okText;
                tr.classList.toggle('is-filtered-out', !show);
                if (show) matched.push(tr);
            });

            var pages = Math.max(1, Math.ceil(matched.length / PER_PAGE));
            if (state.page > pages) state.page = pages;
            var start = (state.page - 1) * PER_PAGE;

            matched.forEach(function (tr, i) {
                tr.classList.toggle('is-paged-out', i < start || i >= start + PER_PAGE);
            });

            var none = list.length === 0;
            tableWrap.hidden = none;
            histEmpty.hidden = !none;
            noResults.hidden = none || matched.length > 0;
            if (none) pager.hidden = true; else renderPager(matched.length);
        }

        /* Kind tabs */
        $('factKindTabs').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-fact-kind]');
            if (!btn) return;
            state.kind = btn.dataset.factKind;
            state.page = 1;
            panel.querySelectorAll('[data-fact-kind]').forEach(function (b) {
                var on = b === btn;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            apply();
        });

        /* Search (debounced a little so typing stays smooth) */
        var searchTimer = null;
        search.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                state.query = search.value;
                state.page = 1;
                apply();
            }, 120);
        });

        /* Paging */
        btnPrev.addEventListener('click', function () { state.page--; apply(); });
        btnNext.addEventListener('click', function () { state.page++; apply(); });
        pagerNums.addEventListener('click', function (e) {
            var b = e.target.closest('[data-page]');
            if (!b) return;
            state.page = parseInt(b.dataset.page, 10) || 1;
            apply();
        });

        /* ── 2. Show all / Show less ─────────────────────────────── */
        panel.addEventListener('click', function (e) {
            var more = e.target.closest('[data-fact-more]');
            if (!more) return;
            var list = more.previousElementSibling;
            if (!list || !list.classList.contains('fact-list')) return;

            var collapsed = list.classList.toggle('is-collapsed');
            more.classList.toggle('is-open', !collapsed);
            more.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            var total = list.children.length;
            more.firstChild.nodeValue = collapsed ? 'Show all ' + total + ' ' : 'Show less ';
        });

        /* ── 3. Download Report ──────────────────────────────────── */
        panel.addEventListener('click', function (e) {
            if (e.target.closest('[data-fact-print]')) window.print();
        });

        /* ── 4. Rooms: live refresh ──────────────────────────────── */
        var inFlight = false;

        function setStat(name, value) {
            var el = panel.querySelector('[data-fact-stat="' + name + '"]');
            if (el) el.textContent = value;
        }

        function refreshRooms() {
            if (inFlight) return;
            inFlight = true;
            fetch(API_ROOMS, { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data || data.error) return;

                    var card = $('factRoomsBody');
                    if (card && typeof data.body === 'string') card.innerHTML = data.body;

                    if (typeof data.rows === 'string') {
                        body.querySelectorAll('tr.fact-row[data-kind="room"]').forEach(function (tr) { tr.remove(); });
                        body.insertAdjacentHTML('beforeend', data.rows);
                        sortRows();
                        apply();
                    }

                    if (data.stats) {
                        setStat('reserved', data.stats.reserved);
                        setStat('reserved_sub', data.stats.reserved_sub);
                        setStat('next', data.stats.next);
                        setStat('next_sub', data.stats.next_sub);
                    }
                })
                .catch(function () { /* a failed refresh must never disturb the screen */ })
                .then(function () { inFlight = false; });
        }

        function panelIsOpen() {
            return panel.classList.contains('active') && !document.hidden;
        }

        /* After booking or cancelling */
        document.addEventListener('pupsync:reservation-submitted', refreshRooms);

        /* Joining a waitlist does not fire that event — look again shortly after */
        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-action="join-waitlist"], [data-action="cancel-reservation"]')) {
                setTimeout(refreshRooms, 1500);
                setTimeout(refreshRooms, 4000);
            }
        });

        /* When the tab is opened, make sure it is current */
        var navActivity = $('nav-activity');
        if (navActivity) navActivity.addEventListener('click', function () { setTimeout(refreshRooms, 150); });

        setInterval(function () { if (panelIsOpen()) refreshRooms(); }, REFRESH_MS);
        document.addEventListener('visibilitychange', function () { if (panelIsOpen()) refreshRooms(); });

        /* ── Start ───────────────────────────────────────────────── */
        sortRows();
        apply();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startMyActivity);
    else startMyActivity();
})();
