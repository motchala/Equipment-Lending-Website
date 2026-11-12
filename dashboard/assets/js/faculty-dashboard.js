(function () {
    'use strict';

    const todayStr = new Date().toISOString().split('T')[0];

    /* ══════════════════════════════════════════════════════════════════
       CSRF — reads the token already embedded in the page (emitted by
       PHP's csrf_field()) so it can be attached to fetch() requests that
       build their own FormData instead of submitting an actual <form>.
    ══════════════════════════════════════════════════════════════════ */
    function getCsrfToken() {
        // The faculty pages carry the token in <meta name="csrf-token"> (faculty-shell-top.php); the hidden
        // input only exists on admin screens, so without this fallback every call that uses it sent ''.
        const el = document.querySelector('input[name="csrf_token"]');
        if (el && el.value) return el.value;
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.getAttribute('content') || '') : '';
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
        let theme = LS.get('theme');
        // High Contrast was removed (Light / Dark only): anyone who had it saved falls back to Light.
        if (theme && theme !== 'light' && theme !== 'dark') { theme = 'light'; LS.set('theme', theme); }
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

        // 4. Font size: the Text Size setting was removed from Settings. Clear any size saved earlier
        //    so nobody is left stuck on a custom size with no way to change it back.
        if (LS.get('fontSize') !== null) LS.del('fontSize');
        document.documentElement.style.fontSize = '';

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
        if (theme !== 'light' && theme !== 'dark') theme = 'light';   // Light / Dark only
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
    window.showToast = window.showToast || showToast;   // also used by the My Activity block

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
            sovCancelEdit(true);   // Settings always opens in read-only mode
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
        // New side-nav-item support.
        // Clear the highlight on EVERY sidebar item, not just the ones with data-tab: Settings
        // (#nav-settings) has no data-tab, so it used to stay lit after Back/Forward closed the
        // Settings screen. If an overlay is somehow still open, its own item keeps its highlight.
        const overlayStillOpen = !!document.querySelector('.overlay-page.active');
        document.querySelectorAll('.side-nav-item').forEach(b => {
            if (b.hasAttribute('data-tab') || !overlayStillOpen) b.classList.remove('active');
        });
        if (!overlayStillOpen) {
            const acctToggle = document.getElementById('navAccountToggle');
            if (acctToggle) acctToggle.classList.remove('active');
        }
        const sideBtn = document.querySelector('.side-nav-item[data-tab="' + tabName + '"]');
        if (sideBtn) sideBtn.classList.add('active');
        document.querySelectorAll('.tab-panel').forEach(p => {
            if (p !== panel) p.classList.remove('active');
        });

        if (tabName === 'rooms' && window.PUPSyncFacilities) {
            window.PUPSyncFacilities.start();

            // Deep-link: read params stored by the AI mini-form in sessionStorage
            (function () {
                var raw = null;
                try { raw = sessionStorage.getItem('ai_room_deeplink'); } catch (e) { }
                if (!raw) return;

                var params = null;
                try { params = JSON.parse(raw); } catch (e) { }
                // Consume immediately — one-shot, never re-opens on back/forward
                try { sessionStorage.removeItem('ai_room_deeplink'); } catch (e) { }
                if (!params || !params.room) return;

                var roomId = params.room;
                var date = params.date || null;
                var start = params.start || null;
                var end = params.end || null;

                // Hard-reload path: sessionStorage was set, page reloaded,
                // now we land on Facilities and open the room
                if (window.PUPSyncFacilities && window.PUPSyncFacilities.openRoom) {
                    window.PUPSyncFacilities.openRoom(roomId, date, start, end);
                }
            })();
        }

        /* ── AI chatbot: only visible on the Facilities tab ─────────────── */
        const aiFab = document.getElementById('actAiFab');
        const aiChat = document.getElementById('actAiChat');
        if (tabName === 'rooms') {
            if (aiFab) aiFab.style.display = '';
            _aiTooltipMaybeShow();
            // Refresh the rate-limit counter from the server every time the
            // Rooms tab becomes active — covers hard resets, tab switches, and
            // FAB opens, so the displayed count is always accurate.
            fetch('room-reservation/api/chat-quota.php')
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (typeof d.remaining === 'number') {
                        var el = document.getElementById('actAiCounter');
                        var bar = document.getElementById('actAiCounterBar');
                        if (el) el.textContent = d.remaining;
                        if (bar) {
                            bar.classList.remove('is-low', 'is-empty');
                            if (d.remaining === 0) bar.classList.add('is-empty');
                            else if (d.remaining <= 5) bar.classList.add('is-low');
                        }
                    }
                })
                .catch(function () { /* silently ignore */ });
        } else {
            if (aiFab) aiFab.style.display = 'none';
            _aiTooltipHide();
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
        const catEl    = document.getElementById('categoryFilter');
        const search   = (searchEl ? searchEl.value : '').toLowerCase();
        const category = (catEl ? catEl.value : '').toLowerCase();
        const activeCampusBtn = document.querySelector('.eq-campus-tab.active');
        const campus = activeCampusBtn ? (activeCampusBtn.dataset.campus || '') : '';

        return Array.from(document.querySelectorAll('.item-node')).filter(item => {
            const nameMatch   = (item.dataset.name || '').includes(search);
            const catMatch    = !category || item.dataset.category === category;
            const campusMatch = !campus   || String(item.dataset.campus) === campus;
            return nameMatch && catMatch && campusMatch;
        });
    }

    function renderEquipmentPage() {
        const matches = getMatchingEquipment();
        const total = matches.length;
        const totalPages = Math.max(1, Math.ceil(total / EQ_PER_PAGE));
        if (eqCurrentPage > totalPages) eqCurrentPage = totalPages;
        if (eqCurrentPage < 1) eqCurrentPage = 1;

        // Hide everything, then reveal only this page's slice
        var grid = document.getElementById('equipmentList');
        document.querySelectorAll('.item-node').forEach(function (el) { el.style.display = 'none'; });
        // Force reflow so the browser paints the hidden state before revealing matches
        if (grid) void grid.offsetHeight;
        var start = (eqCurrentPage - 1) * EQ_PER_PAGE;
        matches.slice(start, start + EQ_PER_PAGE).forEach(function (el) { el.style.display = ''; });

        // Count chip reflects the filtered total, not the page size
        const chip = document.getElementById('equipCountChip');
        if (chip) chip.textContent = total + (total === 1 ? ' item' : ' items');

        // Empty state — message depends on context
        const noRes = document.getElementById('equipNoResults');
        if (noRes) {
            noRes.style.display = total === 0 ? '' : 'none';
            if (total === 0) {
                const activeCampusTabBtn = document.querySelector('.eq-campus-tab.active');
                const activeCampus = activeCampusTabBtn ? (activeCampusTabBtn.dataset.campus || '') : '';
                const searchEl2 = document.getElementById('equipmentSearch');
                const catEl2    = document.getElementById('categoryFilter');
                const hasSearch = searchEl2 && searchEl2.value.trim() !== '';
                const hasCat    = catEl2 && catEl2.value !== '';
                const msgEl  = document.getElementById('equipNoResultsMsg');
                const iconEl = document.getElementById('equipNoResultsIcon');
                if (activeCampus && !hasSearch && !hasCat) {
                    if (msgEl)  msgEl.textContent  = 'No equipment available in this campus yet.';
                    if (iconEl) iconEl.textContent = 'location_off';
                } else {
                    if (msgEl)  msgEl.textContent  = 'No equipment matches your search.';
                    if (iconEl) iconEl.textContent = 'search_off';
                }
            }
        }

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

    /* ── Borrow flow ───────────────────────────────────────────────────────
       Adding items, the Borrow modal and the building > floor > room picker live in
       equipment-booking/assets/js/faculty-borrow-flow.js (loaded right after this file). */

    /* Borrow modal: show the chosen file's name inside the styled drop area (also used by faculty-borrow-flow.js) */
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

    // Letter drop area: show the chosen file's name
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
       (notifications/core/faculty-data.php) and embedded
       as window.FNOTIF_DATA. Read/deleted state is persisted per faculty
       via notifications/api/faculty-notif.php. UI updates are
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
    const NOTIF_API = 'notifications/api/faculty-notif.php';
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

        fetch('settings/api/update-profile.php', { method: 'POST', body: fd })
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

    /* ══════════════════════════════════════════════════════════════════
       SETTINGS > PERSONAL INFORMATION  ("Edit Account" -> Save / Cancel)
       The card is read-only until Edit Account is clicked. Save only becomes
       available once something actually changed. Email is never editable here.
    ══════════════════════════════════════════════════════════════════ */
    function _sovEsc(s) {
        const d = document.createElement('div');
        d.textContent = String(s == null ? '' : s);
        return d.innerHTML;
    }

    function _sovCard() { return document.getElementById('sovPersonalCard'); }

    function _sovFields() {
        const c = _sovCard();
        return c ? Array.from(c.querySelectorAll('[data-edit-field]')) : [];
    }

    function _sovClearErrors() {
        const c = _sovCard();
        if (!c) return;
        c.querySelectorAll('[data-error-for]').forEach(p => { p.hidden = true; p.textContent = ''; });
        c.querySelectorAll('.is-invalid').forEach(el => { el.classList.remove('is-invalid'); el.removeAttribute('aria-invalid'); });
    }

    function _sovShowError(field, msg) {
        const c = _sovCard();
        if (!c) return;
        const p = c.querySelector('[data-error-for="' + field + '"]');
        const el = c.querySelector('[data-edit-field="' + field + '"]');
        if (p) { p.textContent = msg; p.hidden = false; }
        if (el) { el.classList.add('is-invalid'); el.setAttribute('aria-invalid', 'true'); el.focus(); }
    }

    function _sovIsDirty() {
        return _sovFields().some(el => el.value.trim() !== (el.dataset.original || ''));
    }

    function _sovSyncSave() {
        const btn = document.getElementById('sovSaveBtn');
        if (btn && !btn.dataset.busy) btn.disabled = !_sovIsDirty();
    }

    function _sovSetEditActions(editing) {
        const editBtn = document.getElementById('sovEditBtn');
        const saveBtn = document.getElementById('sovSaveBtn');
        const cancelBtn = document.getElementById('sovCancelBtn');
        if (editBtn) editBtn.hidden = editing;
        if (saveBtn) saveBtn.hidden = !editing;
        if (cancelBtn) cancelBtn.hidden = !editing;
        document.querySelectorAll('#sov-tab-settings [data-sov-edit-gated]').forEach(btn => {
            btn.hidden = !editing;
        });
    }

    function _sovIsEditingAccount() {
        const card = _sovCard();
        return Boolean(card && card.dataset.editing === 'true');
    }

    // Same rules as the server: 09XXXXXXXXX, +639XXXXXXXXX, 639XXXXXXXXX or 9XXXXXXXXX (spaces / dashes allowed)
    function _sovPhoneOk(v) {
        return /^(?:\+?63|0)?9\d{9}$/.test(String(v).replace(/[\s\-().]/g, ''));
    }

    function sovStartEdit() {
        const c = _sovCard();
        if (!c) return;
        _sovFields().forEach(el => { el.dataset.original = el.value.trim(); el.disabled = false; });
        c.dataset.editing = 'true';
        _sovSetEditActions(true);
        _sovClearErrors();
        _sovSyncSave();
        const first = c.querySelector('[data-edit-field="fullname"]');
        if (first) { first.focus(); if (first.select) first.select(); }
    }

    function _sovLeaveEdit() {
        const c = _sovCard();
        if (!c) return;
        _sovFields().forEach(el => { el.disabled = true; });
        c.dataset.editing = 'false';
        _sovSetEditActions(false);
        _sovClearErrors();
    }

    function sovCancelEdit(silent) {
        const c = _sovCard();
        if (!c || c.dataset.editing !== 'true') return;
        _sovFields().forEach(el => { el.value = el.dataset.original || ''; });
        _sovLeaveEdit();
        if (!silent) {
            const b = document.getElementById('sovEditBtn');
            if (b) b.focus();
        }
    }

    function _sovInitials(name) {
        const parts = String(name).trim().split(/\s+/).filter(Boolean);
        let ini = parts.length ? parts[0].charAt(0).toUpperCase() : '';
        if (parts.length > 1) ini += parts[parts.length - 1].charAt(0).toUpperCase();
        return ini;
    }

    // Update the avatar initials without ever touching a profile picture.
    function _sovSetInitials(ini) {
        document.querySelectorAll('.side-nav-avatar, .avatar-btn, .acc-avatar-large, .acct-banner-avatar').forEach(el => {
            const fb = el.querySelector('.avatar-initials-fallback');
            if (fb) { fb.textContent = ini; return; }
            if (el.querySelector('img.avatar-img')) return;
            const tn = Array.from(el.childNodes).find(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim());
            if (tn) tn.textContent = ini;
            else el.insertBefore(document.createTextNode(ini), el.firstChild);
        });
    }

    function _sovApplySaved(d) {
        const c = _sovCard();
        if (!c) return;
        const oldName = (document.querySelector('.acct-banner-name') || {}).textContent || '';

        // inputs + read-only values
        const nameIn = c.querySelector('[data-edit-field="fullname"]');
        if (nameIn) nameIn.value = d.fullname;
        const nameView = document.getElementById('sovFullNameView');
        if (nameView) nameView.textContent = d.fullname;

        const phoneIn = c.querySelector('[data-edit-field="phone"]');
        if (phoneIn) phoneIn.value = d.phone_display || '';
        const phoneView = document.getElementById('sovPhoneView');
        if (phoneView) {
            phoneView.textContent = d.phone_display || '— Not provided';
            phoneView.classList.toggle('acct-row-static-muted', !d.phone_display);
            phoneView.classList.toggle('acct-row-not-provided', !d.phone_display);
        }

        // department: once chosen it becomes a permanent, read-only value
        const deptSel = c.querySelector('[data-edit-field="department"]');
        const deptView = document.getElementById('sovDepartmentView');
        if (d.department && deptSel && deptView) {
            deptView.textContent = d.department;
            deptView.classList.remove('acct-row-static-muted');
            deptView.removeAttribute('data-view');
            const row = deptSel.closest('.acct-row');
            deptSel.remove();
            const infoButton = row.querySelector('.sov-field-info');
            if (infoButton) infoButton.remove();
            if (row && !row.querySelector('.fid-lock')) {
                const lock = document.createElement('span');
                lock.className = 'material-symbols-outlined fid-lock';
                lock.title = 'Your department is permanent once set';
                lock.textContent = 'lock';
                row.appendChild(lock);
            }
        }

        // name shown elsewhere on the page
        document.querySelectorAll('.side-nav-user-name, .acct-banner-name, .u-name, .acc-hero-info h2').forEach(el => {
            el.textContent = d.fullname;
        });
        document.querySelectorAll('[data-first-name]').forEach(el => { el.textContent = d.first_name; });
        document.querySelectorAll('input[type="hidden"][name="instructor"]').forEach(el => {
            if (oldName && el.value === oldName) el.value = d.fullname;
        });
        _sovSetInitials(d.initials || _sovInitials(d.fullname));

        // banner line: "<department> · ID: …"
        const meta = document.querySelector('.acct-banner-meta');
        if (meta && meta.firstChild && meta.firstChild.nodeType === Node.TEXT_NODE) {
            meta.firstChild.textContent = (d.department || 'PUPSync Faculty') + ' \u00B7 ID: ';
        }
    }

    function sovSaveAccount() {
        const c = _sovCard();
        const btn = document.getElementById('sovSaveBtn');
        if (!c || !btn || btn.disabled) return;
        _sovClearErrors();

        const vals = {};
        _sovFields().forEach(el => { vals[el.dataset.editField] = el.value.trim(); });
        if (!vals.fullname) { _sovShowError('fullname', 'Full name cannot be empty.'); return; }
        const phoneEl = c.querySelector('[data-edit-field="phone"]');
        if (vals.phone && phoneEl && vals.phone !== (phoneEl.dataset.original || '') && !_sovPhoneOk(vals.phone)) {
            _sovShowError('phone', 'Enter a valid mobile number, like 09XX XXX XXXX.');
            return;
        }

        const fd = new FormData();
        fd.append('action', 'save_account');
        fd.append('csrf_token', getCsrfToken());
        Object.keys(vals).forEach(k => fd.append(k, vals[k]));

        const label = btn.querySelector('.sov-profile-save-label');
        btn.dataset.busy = '1';
        btn.disabled = true;
        if (label) label.textContent = 'Saving\u2026';
        const cancelBtn = document.getElementById('sovCancelBtn');
        if (cancelBtn) cancelBtn.disabled = true;

        fetch('settings/api/update-profile.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    if (data.changed === false) {
                        _sovLeaveEdit();
                        showToast('No changes to save.');
                    } else {
                        _sovApplySaved(data.data || {});
                        _sovLeaveEdit();
                        showToast(_sovEsc(data.msg || 'Your account details were updated.'), 'success');
                    }
                    const eb = document.getElementById('sovEditBtn');
                    if (eb) eb.focus();
                } else if (data && data.field) {
                    _sovShowError(data.field, data.msg || 'Please check this field.');
                } else {
                    showToast(_sovEsc((data && (data.msg || data.error)) || 'Could not save your changes.'), 'error');
                }
            })
            .catch(() => showToast('Network error. Please try again.', 'error'))
            .finally(() => {
                delete btn.dataset.busy;
                if (label) label.textContent = 'Save Changes';
                if (cancelBtn) cancelBtn.disabled = false;
                _sovSyncSave();
            });
    }

    // Live "is there anything to save?" + clear the error on a field once the user edits it
    document.addEventListener('input', function (e) {
        const card = _sovCard();
        if (!card || !card.contains(e.target)) return;
        const f = e.target.closest('[data-edit-field]');
        if (f) {
            const p = card.querySelector('[data-error-for="' + f.dataset.editField + '"]');
            if (p) { p.hidden = true; p.textContent = ''; }
            f.classList.remove('is-invalid');
            f.removeAttribute('aria-invalid');
        }
        _sovSyncSave();
    });
    document.addEventListener('change', function (e) {
        const card = _sovCard();
        if (card && card.contains(e.target)) _sovSyncSave();
    });
    document.addEventListener('keydown', function (e) {
        const card = _sovCard();
        if (!card || card.dataset.editing !== 'true' || !card.contains(e.target)) return;
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); sovCancelEdit(); }
        else if (e.key === 'Enter' && e.target.matches('input')) { e.preventDefault(); sovSaveAccount(); }
    });

    /* ══════════════════════════════════════════════════════════════════
       SETTINGS > DATA MANAGEMENT > EXPORT DATA
       Downloads one CSV with the account info + PAST activity (server side).
    ══════════════════════════════════════════════════════════════════ */
    function exportMyData() {
        const btn = document.getElementById('sovExportBtn');
        if (!btn || btn.disabled) return;
        const label = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Preparing\u2026';

        const fd = new FormData();
        fd.append('csrf_token', getCsrfToken());

        fetch('settings/api/export-faculty-data.php', { method: 'POST', body: fd })
            .then(r => {
                const type = r.headers.get('Content-Type') || '';
                if (r.ok && type.indexOf('application/pdf') !== -1) {
                    const cd = r.headers.get('Content-Disposition') || '';
                    const m = /filename="?([^";]+)"?/.exec(cd);
                    return r.blob().then(b => ({ blob: b, name: m ? m[1] : 'pupsync-activity-report.pdf' }));
                }
                return r.json().catch(() => ({})).then(j => {
                    throw new Error(j.msg || j.error || 'Could not export your data. Please try again.');
                });
            })
            .then(res => {
                const url = URL.createObjectURL(res.blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = res.name;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(url), 2000);
                showToast('Your activity report was downloaded.', 'success');
            })
            .catch(err => showToast(_sovEsc(err && err.message ? err.message : 'Could not export your data.'), 'error'))
            .finally(() => {
                btn.disabled = false;
                btn.textContent = label;
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

        fetch('settings/api/update-profile.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const lastChangedEl = document.getElementById('sovPasswordLastChanged');
                    if (lastChangedEl && data.last_pw_change) {
                        const changedAt = new Date(data.last_pw_change.replace(' ', 'T'));
                        if (!Number.isNaN(changedAt.getTime())) {
                            lastChangedEl.textContent = changedAt.toLocaleString('en-US', {
                                month: 'short',
                                day: '2-digit',
                                year: 'numeric',
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            }).replace(',', ' ·');
                            lastChangedEl.classList.remove('pw-change-untracked');
                        }
                    }
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

        fetch('settings/api/update-profile.php', { method: 'POST', body: fd })
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

        fetch('settings/api/update-profile.php', { method: 'POST', body: fd })
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
        return fetch('settings/api/update-profile.php', { method: 'POST', body: fd })
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

        fetch('settings/api/update-profile.php', { method: 'POST', body: fd })
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

            fetch('settings/api/update-profile.php', { method: 'POST', body: fd })
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
                case 'ai-chat-report-room-issue': {
                    const inp = document.getElementById('actAiInput');
                    if (inp) {
                        inp.value = 'I need to report a room issue.';
                        inp.focus();
                    }
                    break;
                }
                case 'ai-confirm-reservation':
                    if (typeof window.actAiConfirm === 'function') window.actAiConfirm();
                    break;
                case 'ai-cancel-reservation':
                    if (typeof window.actAiCancel === 'function') window.actAiCancel();
                    break;
                case 'equip-search-clear': {
                    var eqIn = document.getElementById('equipmentSearch');
                    if (eqIn) { eqIn.value = ''; eqIn.dispatchEvent(new Event('input', { bubbles: true })); eqIn.focus(); }
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
                    if (el.hasAttribute('data-sov-edit-gated') && !_sovIsEditingAccount()) break;
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
                    if (el.hasAttribute('data-sov-edit-gated') && !_sovIsEditingAccount()) break;
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
                case 'account-edit':
                    sovStartEdit();
                    break;
                case 'account-cancel':
                    sovCancelEdit();
                    break;
                case 'account-save':
                    sovSaveAccount();
                    break;
                case 'export-my-data':
                    exportMyData();
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
                    const returnUrl = base + 'equipment-booking/return_confirm.php?token=' + token;
                    _openReturnQrModal(equipment, returnUrl, 'Return QR', 'Show this to the admin when returning the equipment.');
                    break;
                }
                case 'show-claim-qr': {
                    const token = el.dataset.token;
                    const equipment = el.dataset.equipment;
                    const isBundle = el.dataset.bundleClaim === '1';
                    const base = window.SERVER_BASE_URL
                        || window.location.href.substring(0, window.location.href.lastIndexOf('/') + 1);
                    const claimUrl = base + 'equipment-booking/claim_confirm.php?token=' + token;
                    _openReturnQrModal(
                        equipment,
                        claimUrl,
                        isBundle ? 'Claim Bundle QR' : 'Claim QR',
                        isBundle ? 'Show this one QR to the admin to collect all approved bundled items.' : 'Show this to the admin to collect your equipment.'
                    );
                    break;
                }
                case 'logout':
                    if (window.PSLogout) window.PSLogout.open(el);
                    else if (confirm('Confirm Logout?')) window.location.href = 'auth/api/logout.php'; // fallback only if logout-modal.js failed to load
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
            // Edit Profile / Save / Cancel only belong to the Settings tab: fade out / in
            var profileActions = document.getElementById('sovProfileActions');
            if (profileActions) {
                clearTimeout(profileActions._leaveTimer);
                if (targetId !== 'sov-tab-settings') {
                    if (!profileActions.hidden) {
                        profileActions.classList.add('is-leaving');
                        profileActions._leaveTimer = setTimeout(function () { profileActions.hidden = true; }, 230);
                    }
                } else {
                    var wasHidden = profileActions.hidden;
                    profileActions.hidden = false;
                    if (wasHidden) {
                        void profileActions.offsetWidth;   // let the browser paint the faded state first
                    }
                    profileActions.classList.remove('is-leaving');
                }
            }
            // Update tab panels
            document.querySelectorAll('.sov-tab-panel').forEach(function (p) { p.classList.remove('active'); });
            var panel = document.getElementById(targetId);
            if (panel) panel.classList.add('active');
        });
    });

    /* ── Notification filter tabs ─────────────────────────────────────── */
    document.querySelectorAll('.notif-tab').forEach(btn => {
        btn.addEventListener('click', function () {
            filterNotifs(this.dataset.notifFilter);
        });
    });

    /* ── Equipment campus tabs ────────────────────────────────────────── */
    document.querySelectorAll('.eq-campus-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.eq-campus-tab').forEach(function (b) {
                b.classList.remove('active');
                b.setAttribute('aria-selected', 'false');
            });
            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');

            // Narrow the category dropdown to only show this campus's options
            const campus = btn.dataset.campus || '';

            // Hide featured cards that don't belong to the selected campus
            var featHero = document.querySelector('.feat-hero[data-campus]');
            var featSec  = document.querySelector('.feat-secondary[data-campus]');
            var featSection = document.getElementById('featuredSection');
            if (featSection) {
                featSection.style.display = '';
                if (featHero) featHero.style.display = '';
                if (featSec)  featSec.style.display  = '';

                if (!campus) {
                    featSection.classList.remove('eq-campus-hidden');
                    if (featHero) featHero.classList.remove('eq-campus-hidden');
                    if (featSec)  featSec.classList.remove('eq-campus-hidden');
                } else {
                    var heroMatch = featHero && String(featHero.dataset.campus) === campus;
                    var secMatch  = featSec  && String(featSec.dataset.campus)  === campus;
                    if (featHero) featHero.classList.toggle('eq-campus-hidden', !heroMatch);
                    if (featSec)  featSec.classList.toggle('eq-campus-hidden', !secMatch);
                    featSection.classList.toggle('eq-campus-hidden', !heroMatch && !secMatch);
                }
            }

            const catEl  = document.getElementById('categoryFilter');
            if (catEl) {
                catEl.value = ''; // reset selection when switching campus
                Array.from(catEl.options).forEach(function (opt) {
                    if (!opt.value) { opt.hidden = false; return; } // "All Categories"
                    opt.hidden = campus !== '' && String(opt.dataset.campusId || '') !== campus;
                });
                Array.from(catEl.querySelectorAll('optgroup')).forEach(function (grp) {
                    grp.hidden = campus !== '' && String(grp.dataset.campusId || '') !== campus;
                });
            }
            filterEquipment();
        });
    });

    /* ── Equipment search/filter ──────────────────────────────────────── */
    const eqSearch = document.getElementById('equipmentSearch');
    const eqCat = document.getElementById('categoryFilter');
    if (eqSearch) eqSearch.addEventListener('input', filterEquipment);
    if (eqSearch) eqSearch.addEventListener('input', function () {
        const clr = document.getElementById('equipmentSearchClear');
        if (clr) clr.hidden = !eqSearch.value;
    });
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
        if (aiFabInit && initialActivePanel && initialActivePanel.id === 'panel-rooms') {
            aiFabInit.style.display = '';
            _aiTooltipMaybeShow();
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
        fetch('settings/api/update-profile.php', {
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

            fetch('settings/api/update-profile.php', {
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

            fetch('settings/api/update-profile.php', {
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

            fetch('settings/api/update-profile.php', {
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

        fetch('settings/api/update-profile.php', {
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

            fetch('settings/api/update-profile.php', {
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

                fetch('settings/api/update-profile.php', {
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

                fetch('settings/api/update-profile.php', {
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


    /* ── Coach mark: "Your Claim QR is in My Activity" ────────────────── */
    function _showClaimHint(equipmentName) {
        // Remove any existing hint first
        const old = document.getElementById('claimHintBubble');
        if (old) old.remove();

        const activityBtn = document.querySelector('[data-tab="activity"], [href="#activity"], #nav-activity');
        const bubble = document.createElement('div');
        bubble.id = 'claimHintBubble';
        bubble.setAttribute('role', 'status');
        bubble.setAttribute('aria-live', 'polite');
        bubble.style.cssText = [
            'position:fixed',
            'bottom:24px',
            'left:50%',
            'transform:translateX(-50%)',
            'z-index:10000',
            'background:#600302',
            'color:#fff',
            'padding:12px 20px 12px 16px',
            'border-radius:14px',
            'box-shadow:0 4px 20px rgba(0,0,0,0.25)',
            'font-size:.85rem',
            'max-width:320px',
            'width:max-content',
            'display:flex',
            'align-items:flex-start',
            'gap:10px',
            'cursor:pointer',
        ].join(';');
        bubble.innerHTML = `
            <span class="material-symbols-outlined" style="font-size:22px;flex-shrink:0;margin-top:1px;">qr_code_2</span>
            <div>
                <strong style="display:block;margin-bottom:2px;">${equipmentName} approved!</strong>
                <span style="opacity:.9;">Your Claim QR is saved in <strong>My Activity</strong>. Show it to Sir Redg to collect the equipment.</span>
            </div>
            <button style="background:none;border:none;color:rgba(255,255,255,.7);cursor:pointer;padding:0;margin-left:6px;flex-shrink:0;font-size:18px;line-height:1;" aria-label="Dismiss" onclick="this.closest('#claimHintBubble').remove()">×</button>`;

        // Clicking the bubble navigates to My Activity and dismisses
        bubble.addEventListener('click', function (e) {
            if (e.target.tagName === 'BUTTON') return;
            bubble.remove();
            // Try to activate the My Activity tab
            const tab = document.querySelector('[data-tab="activity"]')
                || document.querySelector('.nav-link[href="#panel-activity"]')
                || document.getElementById('nav-activity');
            if (tab) tab.click();
        });

        document.body.appendChild(bubble);
        // Auto-dismiss after 12 seconds
        setTimeout(() => { if (bubble.parentNode) bubble.remove(); }, 12000);
    }

    /* ── Return / Claim QR Modal ───────────────────────────────────────── */
    function _openReturnQrModal(equipment, url, modalTitle, modalDesc) {
        const title = modalTitle || 'Return QR';
        const desc  = modalDesc  || 'Show this QR code to the admin when returning the equipment.';
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
                    <h3 id="returnQrModalLabel" style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--color-on-surface-variant);margin-bottom:2px;"></h3>
                    <h4 id="returnQrTitle" style="font-size:1rem;font-weight:700;margin-bottom:4px;"></h4>
                    <p id="returnQrDesc" style="font-size:0.8rem;color:var(--color-on-surface-variant);margin-bottom:16px;"></p>
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
        document.getElementById('returnQrModalLabel').textContent = title;
        document.getElementById('returnQrTitle').textContent = equipment;
        document.getElementById('returnQrDesc').textContent = desc;
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
            on_loan:  data.filter(r => r.status === 'On Loan').length,
            overdue:  data.filter(r => r.status === 'Overdue').length,
            waiting:  data.filter(r => r.status === 'Waiting').length,
            total:    data.length,
        };
        // .fdb-kpi = the Dashboard key-figure cards; .stat-tile = the older tiles (kept working)
        document.querySelectorAll('.fdb-kpi[data-stat], .stat-tile[data-stat]').forEach(tile => {
            const key = tile.dataset.stat;
            if (!(key in counts)) return;
            const val = tile.querySelector('.fdb-kpi-val, .stat-tile-value');
            if (val && val.textContent.trim() !== String(counts[key])) val.textContent = counts[key];
            // Ready for pickup card: amber tone when there are items waiting, neutral when none
            if (key === 'approved') {
                tile.className = tile.className.replace(/fdb-tone-\w+/, counts[key] > 0 ? 'fdb-tone-warn' : 'fdb-tone-ok');
            }
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
                    });
                    // Add buttons / quantity steppers / the open Borrow modal follow the new numbers
                    if (window.BorrowFlow) window.BorrowFlow.refreshStock();
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
                                // Coach mark: first time a Waiting request becomes Approved,
                                // point faculty to My Activity where the Claim QR lives.
                                if (r.status === 'Approved' && prev === 'Waiting') {
                                    const _claimKey = 'eq_claim_hint_shown_' + r.id;
                                    if (!localStorage.getItem(_claimKey)) {
                                        localStorage.setItem(_claimKey, '1');
                                        _showClaimHint(r.equipment_name);
                                    }
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
                        // My Activity (equipment) re-draws itself when this fires
                        document.dispatchEvent(new CustomEvent('pupsync:requests-changed'));
                    }
                })
                .catch(() => { }); // silently ignore network errors
        }, INTERVAL);
    }

    /* -- Room Reservation Notices ---------------------------------------- */
    /* Reservations are accepted on the spot, and My Activity refreshes its own
       room list (the My Activity block at the end of this file), so there is no table to keep in sync
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
/* ================================================================
   AI Chatbot — tooltip / popover helpers
   Shows a speech-bubble popover above the FAB the first time the
   Facilities tab opens each day (resets at midnight via localStorage
   timestamp). Dismissed by the × button or by opening the chat.
================================================================ */
(function () {
    var STORAGE_KEY = 'eq_ai_tooltip_seen';
    var RESET_MS = 24 * 60 * 60 * 1000; // 24 hours

    function _shouldShow() {
        try {
            var last = parseInt(localStorage.getItem(STORAGE_KEY) || '0', 10);
            return (Date.now() - last) > RESET_MS;
        } catch (e) { return true; }
    }

    function _markSeen() {
        try { localStorage.setItem(STORAGE_KEY, String(Date.now())); } catch (e) { }
    }

    window._aiTooltipMaybeShow = function () {
        if (!_shouldShow()) return;
        var popover = document.getElementById('actAiFabPopover');
        if (!popover) return;
        setTimeout(function () {
            popover.classList.add('visible');
            _markSeen();
        }, 400);
    };

    window._aiTooltipHide = function () {
        var popover = document.getElementById('actAiFabPopover');
        if (popover) popover.classList.remove('visible');
    };

    function init() {
        var closeBtn = document.getElementById('actAiFabPopoverClose');
        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                window._aiTooltipHide();
            });
        }
        var fab = document.getElementById('actAiFab');
        if (fab) {
            fab.addEventListener('click', function () {
                window._aiTooltipHide();
            });
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

/* ══════════════════════════════════════════════════════════════════
   AI CHAT — send function, campus chips, reservation mini-form
══════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    /* ── Helpers ─────────────────────────────────────────────────── */
    function escH(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '<').replace(/>/g, '>');
    }
    function timeNow() {
        var d = new Date(), h = d.getHours(), m = d.getMinutes();
        var ap = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return h + ':' + (m < 10 ? '0' : '') + m + ' ' + ap;
    }
    function mdToHtml(s) {
        var t = escH(String(s));
        t = t.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        t = t.replace(/\*(.*?)\*/g, '<em>$1</em>');
        t = t.replace(/^[-•]\s+(.+)$/gm, '<li>$1</li>');
        t = t.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
        t = t.replace(/\n/g, '<br>');
        return t;
    }
    function removeChips() {
        var c = document.getElementById('actAiChips');
        if (c) c.remove();
        document.querySelectorAll('.act-campus-chips').forEach(function (el) { el.remove(); });
    }
    function setSending(on) {
        var btn = document.getElementById('actAiSendBtn');
        var inp = document.getElementById('actAiInput');
        if (btn) btn.disabled = on;
        if (inp) inp.disabled = on;
    }
    function updateCounter(remaining) {
        var el = document.getElementById('actAiCounter');
        var bar = document.getElementById('actAiCounterBar');
        if (el) el.textContent = remaining;
        if (!bar) return;
        bar.classList.remove('is-low', 'is-empty');
        if (remaining === 0) bar.classList.add('is-empty');
        else if (remaining <= 5) bar.classList.add('is-low');
    }
    function appendUserBubble(body, text) {
        var row = document.createElement('div');
        row.className = 'act-chat-row act-chat-row-user';
        row.innerHTML =
            '<div class="act-chat-col act-chat-col-user">' +
            '<div class="act-chat-bubble act-chat-bubble-user">' + escH(text) + '</div>' +
            '<div class="act-chat-meta act-chat-meta-user">' +
            '<span>' + timeNow() + '</span>' +
            '<span class="material-symbols-outlined act-chat-tick">done_all</span>' +
            '</div>' +
            '</div>';
        body.appendChild(row);
        body.scrollTop = body.scrollHeight;
    }
    function appendTyping(body) {
        var row = document.createElement('div');
        row.className = 'act-chat-row act-chat-row-ai';
        row.innerHTML =
            '<div class="act-chat-ai-avatar">' +
            '<span class="material-symbols-outlined">smart_toy</span>' +
            '</div>' +
            '<div class="act-chat-typing-dots">' +
            '<span></span><span></span><span></span>' +
            '</div>';
        body.appendChild(row);
        body.scrollTop = body.scrollHeight;
        return row;
    }
    function appendAiBubble(body, text) {
        var row = document.createElement('div');
        row.className = 'act-chat-row act-chat-row-ai';
        row.innerHTML =
            '<div class="act-chat-ai-avatar">' +
            '<span class="material-symbols-outlined">smart_toy</span>' +
            '</div>' +
            '<div class="act-chat-col">' +
            '<div class="act-chat-bubble act-chat-bubble-ai">' + mdToHtml(text) + '</div>' +
            '<div class="act-chat-meta"><span>' + timeNow() + '</span></div>' +
            '</div>';
        body.appendChild(row);
        body.scrollTop = body.scrollHeight;
        return row;
    }

    /* ── AI reservation mini-form ───────────────────────────────── */
    //
    // Shown when the AI detects reservation intent (intent_reserve: true).
    // Collects 4 inputs: campus, date, start time, end time.
    // On slot selection, fetches available rooms from Component C.
    // Room field is a searchable combobox, not a flat select.
    // "Find & Reserve" navigates to the Facilities tab and opens
    // the Reserve modal for the selected room pre-filled.
    //
    // This form does NOT submit any reservation itself.
    // It only collects context and hands off to the existing Facilities UI.

    function showMiniForm(lastRow) {
        var col = lastRow && lastRow.querySelector('.act-chat-col');
        if (!col) return;

        var now = new Date();
        var today = now.getFullYear() + '-' +
            String(now.getMonth() + 1).padStart(2, '0') + '-' +
            String(now.getDate()).padStart(2, '0');
        var maxDt = new Date(now.getTime() + 21 * 86400000);
        var maxDay = maxDt.getFullYear() + '-' +
            String(maxDt.getMonth() + 1).padStart(2, '0') + '-' +
            String(maxDt.getDate()).padStart(2, '0');

        var form = document.createElement('div');
        form.className = 'act-mini-form';
        form.id = 'actMiniForm';
        form.innerHTML =
            '<div class="act-mini-form-title">' +
            '<span class="material-symbols-outlined">calendar_month</span>' +
            'Find an Available Room' +
            '</div>' +
            '<div class="act-mini-field">' +
            '<label for="actMfCampus">Campus <span class="act-mini-req">*</span></label>' +
            '<select id="actMfCampus">' +
            '<option value="" disabled selected>Select campus…</option>' +
            '<option value="main">PUP Main (Buildings A &amp; B)</option>' +
            '<option value="cite">PUP CITE (Building C)</option>' +
            '</select>' +
            '</div>' +
            '<div class="act-mini-field">' +
            '<label for="actMfDate">Date <span class="act-mini-req">*</span></label>' +
            '<input type="date" id="actMfDate" min="' + today + '" max="' + maxDay + '">' +
            '</div>' +
            '<div class="act-mini-row">' +
            '<div class="act-mini-field">' +
            '<label for="actMfStart">Start <span class="act-mini-req">*</span></label>' +
            '<input type="time" id="actMfStart" min="07:00" max="20:00">' +
            '</div>' +
            '<div class="act-mini-field">' +
            '<label for="actMfEnd">End <span class="act-mini-req">*</span></label>' +
            '<input type="time" id="actMfEnd" min="07:00" max="20:00">' +
            '</div>' +
            '</div>' +
            '<div class="act-mini-field">' +
            '<label for="actMfRoomSearch">Room <span class="act-mini-req">*</span></label>' +
            '<div class="act-mini-combobox" id="actMfComboWrap">' +
            '<input type="text" id="actMfRoomSearch" ' +
            'placeholder="Fill campus, date & time first\u2026" ' +
            'autocomplete="off" disabled>' +
            '<input type="hidden" id="actMfRoomId">' +
            '<div class="act-mini-dropdown" id="actMfDropdown" style="display:none;"></div>' +
            '</div>' +
            '</div>' +
            '<div id="actMfError" class="act-mini-error" style="display:none;"></div>' +
            '<div class="act-mini-actions">' +
            '<button type="button" class="act-mini-btn act-mini-cancel" ' +
            'id="actMfCancelBtn">Cancel</button>' +
            '<button type="button" class="act-mini-btn act-mini-submit" ' +
            'id="actMfSubmitBtn" disabled>Find &amp; Reserve</button>' +
            '</div>';

        col.appendChild(form);
        _wireMiniForm();
    }

    var _miniFormRooms = [];
    var _miniFormRoomSelected = false;

    function _wireMiniForm() {
        var campusSel = document.getElementById('actMfCampus');
        var dateInp = document.getElementById('actMfDate');
        var startInp = document.getElementById('actMfStart');
        var endInp = document.getElementById('actMfEnd');
        var roomSearch = document.getElementById('actMfRoomSearch');
        var roomIdInp = document.getElementById('actMfRoomId');
        var dropdown = document.getElementById('actMfDropdown');
        var submitBtn = document.getElementById('actMfSubmitBtn');
        var errEl = document.getElementById('actMfError');
        var cancelBtn = document.getElementById('actMfCancelBtn');

        if (!campusSel || !dateInp || !startInp || !endInp ||
            !roomSearch || !roomIdInp || !dropdown || !submitBtn) return;

        function showErr(msg) {
            if (errEl) { errEl.textContent = msg; errEl.style.display = 'block'; }
        }
        function clearErr() {
            if (errEl) errEl.style.display = 'none';
        }

        function onSlotChange() {
            var campus = campusSel.value;
            var date = dateInp.value;
            var start = startInp.value;
            var end = endInp.value;

            _miniFormRooms = [];
            _miniFormRoomSelected = false;
            roomSearch.value = '';
            roomIdInp.value = '';
            dropdown.style.display = 'none';
            submitBtn.disabled = true;
            clearErr();

            if (!campus || !date || !start || !end) {
                roomSearch.disabled = true;
                roomSearch.placeholder = 'Fill campus, date & time first\u2026';
                return;
            }
            if (end <= start) {
                showErr('End time must be after start time.');
                roomSearch.disabled = true;
                roomSearch.placeholder = 'Fix the time range first\u2026';
                return;
            }

            // Past time check — only applies when the selected date is today
            var todayStr = (function () {
                var n = new Date();
                return n.getFullYear() + '-' +
                    String(n.getMonth() + 1).padStart(2, '0') + '-' +
                    String(n.getDate()).padStart(2, '0');
            })();
            if (date === todayStr) {
                var nowH = new Date().getHours();
                var nowM = new Date().getMinutes();
                var startH = parseInt(start.split(':')[0], 10);
                var startM = parseInt(start.split(':')[1], 10);
                if (startH < nowH || (startH === nowH && startM <= nowM)) {
                    showErr('Start time cannot be in the past. Please choose a future time.');
                    roomSearch.disabled = true;
                    roomSearch.placeholder = 'Fix the start time first\u2026';
                    return;
                }
            }

            // Maximum booking duration: 5 hours (300 minutes)
            var startMin = parseInt(start.split(':')[0], 10) * 60 + parseInt(start.split(':')[1], 10);
            var endMin = parseInt(end.split(':')[0], 10) * 60 + parseInt(end.split(':')[1], 10);
            if (endMin - startMin > 300) {
                showErr('Maximum booking duration is 5 hours.');
                roomSearch.disabled = true;
                roomSearch.placeholder = 'Fix the duration first\u2026';
                return;
            }

            roomSearch.disabled = true;
            roomSearch.placeholder = 'Loading rooms\u2026';

            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            fetch('room-reservation/api/check-slot-availability.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    campus: campus,
                    date: date,
                    start_time: start,
                    end_time: end,
                }),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.error) {
                        showErr(data.error);
                        roomSearch.placeholder = 'No rooms available.';
                        return;
                    }
                    _miniFormRooms = data.rooms || [];
                    if (_miniFormRooms.length === 0) {
                        roomSearch.disabled = true;
                        roomSearch.placeholder = 'No rooms available for this slot.';
                        showErr('No rooms are free for this campus, date and time. Try a different slot.');
                        return;
                    }
                    roomSearch.disabled = false;
                    roomSearch.placeholder = 'Type to search ' + _miniFormRooms.length +
                        ' available room' + (_miniFormRooms.length === 1 ? '' : 's') + '\u2026';
                })
                .catch(function () {
                    showErr('Could not load rooms. Please try again.');
                    roomSearch.placeholder = 'Error loading rooms.';
                });
        }

        campusSel.addEventListener('change', onSlotChange);
        dateInp.addEventListener('change', onSlotChange);
        startInp.addEventListener('change', onSlotChange);
        endInp.addEventListener('change', onSlotChange);

        function renderDropdown(query) {
            var q = query.trim().toLowerCase();
            var filtered = q === ''
                ? _miniFormRooms
                : _miniFormRooms.filter(function (r) {
                    return r.room_name.toLowerCase().includes(q) ||
                        r.building_name.toLowerCase().includes(q) ||
                        r.floor_label.toLowerCase().includes(q);
                });

            if (filtered.length === 0) {
                dropdown.innerHTML =
                    '<div class="act-mini-dd-empty">No rooms match "' + escH(query) + '"</div>';
            } else {
                dropdown.innerHTML = filtered.map(function (r) {
                    return '<div class="act-mini-dd-item" ' +
                        'data-id="' + r.room_id + '" ' +
                        'data-label="' + escH(r.room_name + ' \u00b7 ' +
                            r.building_name + ' \u00b7 ' + r.floor_label) + '">' +
                        '<strong>' + escH(r.room_name) + '</strong>' +
                        '<span class="act-mini-dd-meta">' +
                        escH(r.building_name) + ' \u00b7 ' + escH(r.floor_label) +
                        '</span>' +
                        '</div>';
                }).join('');
            }
            dropdown.style.display = 'block';
        }

        roomSearch.addEventListener('input', function () {
            roomIdInp.value = '';
            _miniFormRoomSelected = false;
            submitBtn.disabled = true;
            if (_miniFormRooms.length > 0) renderDropdown(this.value);
        });

        roomSearch.addEventListener('focus', function () {
            if (_miniFormRooms.length > 0) renderDropdown(this.value);
        });

        dropdown.addEventListener('mousedown', function (e) {
            var item = e.target.closest('.act-mini-dd-item');
            if (!item) return;
            e.preventDefault();
            roomIdInp.value = item.dataset.id;
            roomSearch.value = item.dataset.label;
            _miniFormRoomSelected = true;
            dropdown.style.display = 'none';
            submitBtn.disabled = false;
            clearErr();
        });

        document.addEventListener('click', function hideDd(e) {
            var f = document.getElementById('actMiniForm');
            if (!f) { document.removeEventListener('click', hideDd, true); return; }
            if (!f.contains(e.target)) {
                dropdown.style.display = 'none';
                if (!_miniFormRoomSelected) {
                    roomSearch.value = '';
                    roomIdInp.value = '';
                }
            }
        }, true);

        cancelBtn.addEventListener('click', function () {
            var f = document.getElementById('actMiniForm');
            if (f) f.remove();
            setSending(false);
            var chatBody = document.getElementById('actAiChatBody');
            if (chatBody) appendAiBubble(chatBody,
                'No problem! You can ask me anything else, or head to the ' +
                'Facilities tab to browse and reserve directly.');
        });

        submitBtn.addEventListener('click', function () {
            var roomId = roomIdInp.value;
            var date = dateInp.value;
            var start = startInp.value;
            var end = endInp.value;

            if (!roomId) { showErr('Please select a room from the list.'); return; }
            if (!date) { showErr('Please select a date.'); return; }
            if (!start) { showErr('Please enter a start time.'); return; }
            if (!end) { showErr('Please enter an end time.'); return; }
            clearErr();

            // Close chat
            var chatEl = document.getElementById('actAiChat');
            if (chatEl) { chatEl.classList.remove('open'); chatEl.classList.remove('minimized'); }

            // Remove mini-form
            var f = document.getElementById('actMiniForm');
            if (f) f.remove();
            setSending(false);

            // Store params for hard-reload fallback only
            try {
                sessionStorage.setItem('ai_room_deeplink', JSON.stringify({
                    room: roomId, date: date, start: start, end: end
                }));
            } catch (e) { }

            // Switch to Facilities tab by clicking the sidebar nav item —
            // this uses the existing public delegation system and is accessible
            // from any IIFE scope, unlike _switchTabDOM which is private
            var facilitiesNavBtn = document.querySelector('.side-nav-item[data-tab="rooms"]');
            if (facilitiesNavBtn) facilitiesNavBtn.click();

            // openRoom handles its own loading wait via setInterval
            if (window.PUPSyncFacilities && window.PUPSyncFacilities.openRoom) {
                window.PUPSyncFacilities.openRoom(roomId, date, start, end);
            }
        });
    }

    /* ── Main send function ──────────────────────────────────────── */
    function actAiSend(prefill) {

        var input = document.getElementById('actAiInput');
        var msg = (prefill || (input ? input.value : '')).trim();
        if (!msg) return;

        var body = document.getElementById('actAiChatBody');
        if (!body) return;

        if (document.getElementById('actAiSendBtn') &&
            document.getElementById('actAiSendBtn').disabled) return;

        removeChips();
        appendUserBubble(body, msg);
        if (input) input.value = '';
        setSending(true);

        var typing = appendTyping(body);
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

        fetch('room-reservation/api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: msg, csrf_token: csrfToken }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                typing.remove();

                if (data.status === 'rate_limited') {
                    appendAiBubble(body, data.message);
                    updateCounter(0);
                    setSending(false);
                    return;
                }
                if (data.status !== 'success') {
                    appendAiBubble(body, data.message || 'Something went wrong. Please try again.');
                    setSending(false);
                    return;
                }

                var lastRow = appendAiBubble(body, data.reply);
                if (typeof data.remaining === 'number') updateCounter(data.remaining);

                // Campus disambiguation chips (availability queries)
                if (data.ask_campus) {
                    var col = lastRow && lastRow.querySelector('.act-chat-col');
                    if (col) {
                        var chips = document.createElement('div');
                        chips.className = 'act-chat-chips act-campus-chips';
                        chips.innerHTML =
                            '<button class="act-chat-chip" data-chip="PUP Main">🏫 PUP Main</button>' +
                            '<button class="act-chat-chip" data-chip="PUP CITE">🏛 PUP CITE</button>';
                        col.appendChild(chips);
                    }
                }

                // Reservation intent — show 4-field mini-form
                if (data.intent_reserve) {
                    showMiniForm(lastRow);
                    // Keep send disabled until form is cancelled or submitted
                    return;
                }

                // Cancel intent — single reservation confirmation
                if (data.intent_cancel_confirm) {
                    var c = data.intent_cancel_confirm;
                    showCancelConfirmChips(lastRow, [c], false);
                    return;
                }

                // Cancel intent — multi-reservation confirmation
                if (data.intent_cancel_confirm_multi && data.intent_cancel_confirm_multi.length >= 2) {
                    showCancelConfirmChips(lastRow, data.intent_cancel_confirm_multi, true);
                    return;
                }

                // Cancel intent — faculty needs to pick which reservation
                if (data.intent_cancel_pick && data.cancel_pick_options && data.cancel_pick_options.length > 0) {
                    showCancelPickChips(lastRow, data.cancel_pick_options);
                    return;
                }

                setSending(false);
            })
            .catch(function () {
                typing.remove();
                appendAiBubble(body, 'Network error. Please check your connection and try again.');
                setSending(false);
            });
    }

    // ── Cancel chips: confirm cancelling specific reservations ────────────────
    // items = array of { id, room, date, time }
    // isMulti = true means "cancel all" wording, false means single
    function showCancelConfirmChips(lastRow, items, isMulti) {
        var col = lastRow && lastRow.querySelector('.act-chat-col');
        if (!col) { setSending(false); return; }

        var chips = document.createElement('div');
        chips.className = 'act-chat-chips act-cancel-chips';

        var confirmLabel = isMulti
            ? 'Yes, cancel all (' + items.length + ')'
            : 'Yes, cancel it';

        var confirmBtn = document.createElement('button');
        confirmBtn.className = 'act-chat-chip act-cancel-confirm-btn';
        confirmBtn.textContent = confirmLabel;
        chips.appendChild(confirmBtn);

        var neverMindBtn = document.createElement('button');
        neverMindBtn.className = 'act-chat-chip act-cancel-nevermind-btn';
        neverMindBtn.textContent = 'Never mind';
        chips.appendChild(neverMindBtn);

        col.appendChild(chips);

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');

        confirmBtn.addEventListener('click', function () {
            chips.remove();
            var body = document.getElementById('actAiChatBody');

            // Submit cancellations sequentially
            var ids = items.map(function (i) { return i.id; });
            var cancelled = [];
            var failed = [];

            function cancelNext(idx) {
                if (idx >= ids.length) {
                    // All done — show result bubble
                    var resultMsg = '';
                    if (cancelled.length > 0 && failed.length === 0) {
                        resultMsg = cancelled.length === 1
                            ? 'Done! Your reservation for ' + items[0].room + ' on ' + items[0].date + ' has been cancelled.'
                            : 'Done! All ' + cancelled.length + ' reservations have been cancelled.';
                    } else if (cancelled.length > 0 && failed.length > 0) {
                        resultMsg = cancelled.length + ' reservation(s) cancelled. ' + failed.length + ' could not be cancelled: ' + failed.join(', ') + '.';
                    } else {
                        resultMsg = 'Could not cancel the reservation(s). ' + failed.join(' ');
                    }
                    appendAiBubble(body, resultMsg);
                    setSending(false);
                    return;
                }

                fetch('room-reservation/api/cancel-reservation.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        reservation_id: ids[idx],
                        csrf_token: csrfMeta ? csrfMeta.getAttribute('content') : '',
                    }),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d.success) {
                            cancelled.push(items[idx].room);
                        } else {
                            failed.push(items[idx].room + ': ' + (d.error || 'error'));
                        }
                        cancelNext(idx + 1);
                    })
                    .catch(function () {
                        failed.push(items[idx].room + ': network error');
                        cancelNext(idx + 1);
                    });
            }

            setSending(true);
            cancelNext(0);
        });

        neverMindBtn.addEventListener('click', function () {
            chips.remove();
            var chatBody = document.getElementById('actAiChatBody');
            appendAiBubble(chatBody, 'No problem! Your reservation(s) are unchanged.');
            setSending(false);
        });
    }

    // ── Cancel chips: faculty picks which reservation to cancel ──────────────
    // options = array of { id, room, date, time }
    function showCancelPickChips(lastRow, options) {
        var col = lastRow && lastRow.querySelector('.act-chat-col');
        if (!col) { setSending(false); return; }

        var chips = document.createElement('div');
        chips.className = 'act-chat-chips act-cancel-chips';

        options.forEach(function (opt) {
            var btn = document.createElement('button');
            btn.className = 'act-chat-chip';
            btn.textContent = opt.room + ' · ' + opt.date;
            btn.addEventListener('click', function () {
                chips.remove();
                // Show confirm step for the chosen one
                var body = document.getElementById('actAiChatBody');
                var confirmRow = appendAiBubble(body, 'Got it. Cancel your reservation for ' + opt.room + ' on ' + opt.date + ' (' + opt.time + ')?');
                showCancelConfirmChips(confirmRow, [opt], false);
            });
            chips.appendChild(btn);
        });

        var neverMindBtn = document.createElement('button');
        neverMindBtn.className = 'act-chat-chip act-cancel-nevermind-btn';
        neverMindBtn.textContent = 'Never mind';
        neverMindBtn.addEventListener('click', function () {
            chips.remove();
            var body = document.getElementById('actAiChatBody');
            appendAiBubble(body, 'No problem! Your reservations are unchanged.');
            setSending(false);
        });
        chips.appendChild(neverMindBtn);

        col.appendChild(chips);
    }

    window.actAiSend = actAiSend;

    /* ── Init ────────────────────────────────────────────────────── */
    function init() {
        var inp = document.getElementById('actAiInput');
        if (inp) {
            inp.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) actAiSend();
            });
        }
        document.addEventListener('click', function (e) {
            var chip = e.target.closest('.act-chat-chip');
            if (!chip) return;
            actAiSend(chip.dataset.chip);
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();





/* ================================================================
   MY ACTIVITY  (faculty)  --  behaviour for #panel-activity

   1. History table   filter All / Equipment / Rooms, search, paging
   2. Cards           Equipment and Room reservations show 5 rows per page
   3. Download Report prints the whole record (CSS un-pages it)
   4. Live refresh    Rooms:     room-reservation/api/poll-reservations.php?view=activity
                      Equipment: equipment-booking/api/poll-requests.php?view=activity
                      Each swaps its card, its rows in the history table and its own entries in
                      the Next steps list (same renderers as the first page load).
   4b. Next steps     the list at the top: both halves' entries are merged, sorted (most urgent
                      first) and capped at data-max; clicking an entry scrolls to the row that has
                      the real button (paging the card to it) and flashes it.
                      Runs when the tab is opened, after a booking / cancellation,
                      the moment the 5 s requests poll sees a status change (e.g. admin
                      confirms a return), and every 30 s while the tab is visible.
   5. Extend          the Extend button on a loan whose return time has been reached opens
                      a dialog (equipment-booking/api/extend-borrow.php): it lists each
                      extension length with live availability, and the server denies it when
                      other bookings (today or later) need the units. See core/borrow-extension.php.

   6. Cancel / leave  Cancel on an Upcoming equipment request (equipment-booking/api/cancel-borrow.php,
                      rules in core/borrow-cancellation.php) and Leave waitlist on a room waitlist entry
                      (room-reservation/api/leave-waitlist.php). Cancelling a room reservation keeps its own
                      handler near the room code. All of them refresh this screen and the Home key figures.

   7. Paging         the Equipment and Room reservations cards are a fixed size and show 5 rows
                      per page (groups such as In progress / Upcoming flow across the pages);
                      the page navigation sits in each card's footer, like the History table's.

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
        var API_EQUIP = 'equipment-booking/api/poll-requests.php?view=activity';

        var panel = document.getElementById('panel-activity');
        var body = document.getElementById('factHistBody');
        if (!panel || !body) return;

        var $ = function (id) { return document.getElementById(id); };
        var noResults = $('factNoResults');
        var histEmpty = $('factHistEmpty');
        var tableWrap = panel.querySelector('.fact-table-wrap');
        var pager = $('factPager');
        var pagerFoot = $('factHistFoot');
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

        /* The page navigation lives in the History header, next to the filters:
           "Showing 1-10 of 24" + previous / numbers / next. With one page only the count shows. */
        function renderPager(matched) {
            var pages = Math.max(1, Math.ceil(matched / PER_PAGE));
            var ctl = pager.querySelector('.fact-pager-ctl');
            if (matched === 0) { pager.hidden = true; if (pagerFoot) pagerFoot.hidden = true; return; }
            pager.hidden = false;
            if (pagerFoot) pagerFoot.hidden = false;

            var from = (state.page - 1) * PER_PAGE + 1;
            var to = Math.min(matched, state.page * PER_PAGE);
            pagerInfo.textContent = pages > 1
                ? 'Showing ' + from + '\u2013' + to + ' of ' + matched
                : matched + (matched === 1 ? ' record' : ' records');
            if (ctl) ctl.hidden = pages <= 1;

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
            if (none) { pager.hidden = true; if (pagerFoot) pagerFoot.hidden = true; } else renderPager(matched.length);
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

        /* ── 2. Equipment / Room cards: 5 rows per page ─────────────
           The server draws every row; this script shows 5 at a time (a group such as
           "In progress" or "Upcoming" is hidden on pages where none of its rows show) and
           draws the page navigation in the card footer. The page is kept across live
           refreshes, and pulled back when rows disappear. */
        var CARD_PER_PAGE = 5;
        var cards = {
            equipment: { body: $('factEquipBody'), status: $('factEquipStatus'), pager: $('factEquipPager'), page: 1 },
            rooms: { body: $('factRoomsBody'), status: $('factRoomsStatus'), pager: $('factRoomsPager'), page: 1 }
        };

        function renderCardPager(card, total, pages) {
            var nav = card.pager;
            if (!nav) return;
            if (total === 0) { nav.innerHTML = ''; return; }

            var noun = nav.dataset.noun || 'item';
            var from = (card.page - 1) * CARD_PER_PAGE + 1;
            var to = Math.min(total, card.page * CARD_PER_PAGE);
            var html = '<span class="fact-pager-info">' + (pages > 1
                ? 'Showing ' + from + '\u2013' + to + ' of ' + total
                : total + ' ' + noun + (total === 1 ? '' : 's')) + '</span>';

            if (pages > 1) {
                html += '<div class="fact-pager-ctl">'
                    + '<button type="button" class="fact-pager-btn" data-fact-pg="prev" aria-label="Previous page"'
                    + (card.page <= 1 ? ' disabled' : '') + '><span class="material-symbols-outlined">chevron_left</span></button>'
                    + '<div class="fact-pager-nums">';
                pageList(card.page, pages).forEach(function (n) {
                    html += n === 'gap'
                        ? '<span class="fact-pager-gap">\u2026</span>'
                        : '<button type="button" class="fact-pager-btn' + (n === card.page ? ' is-active' : '') + '" data-fact-pg="' + n + '"'
                        + (n === card.page ? ' aria-current="page"' : '') + '>' + n + '</button>';
                });
                html += '</div><button type="button" class="fact-pager-btn" data-fact-pg="next" aria-label="Next page"'
                    + (card.page >= pages ? ' disabled' : '') + '><span class="material-symbols-outlined">chevron_right</span></button></div>';
            }
            nav.innerHTML = html;
        }

        function paginateCard(card) {
            if (!card.body) return;
            var items = Array.prototype.slice.call(card.body.querySelectorAll('.fact-item'));
            var pages = Math.max(1, Math.ceil(items.length / CARD_PER_PAGE));
            card.page = Math.min(Math.max(1, card.page), pages);
            var start = (card.page - 1) * CARD_PER_PAGE;

            items.forEach(function (el, i) {
                el.classList.toggle('is-paged-out', i < start || i >= start + CARD_PER_PAGE);
            });
            card.body.querySelectorAll('.fact-group').forEach(function (g) {
                g.hidden = !g.querySelector('.fact-item:not(.is-paged-out)');
            });
            renderCardPager(card, items.length, pages);
        }

        panel.addEventListener('click', function (e) {
            var b = e.target.closest('[data-fact-pg]');
            if (!b) return;
            var nav = b.closest('[data-fact-card]');
            var card = nav ? cards[nav.dataset.factCard] : null;
            if (!card) return;
            var v = b.dataset.factPg;
            card.page = v === 'prev' ? card.page - 1 : v === 'next' ? card.page + 1 : (parseInt(v, 10) || 1);
            paginateCard(card);
        });

        /* ── 3. Download Report ──────────────────────────────────── */
        panel.addEventListener('click', function (e) {
            if (e.target.closest('[data-fact-print]')) window.print();
        });

        /* ── 4. Live refresh (equipment + rooms) ─────────────────── */
        var inFlight = { rooms: false, equipment: false };

        /* ── Next steps: merge both halves, most urgent first ─────
           Each half owns its own entries (data-src), so a refresh of one never loses the other's.
           All of them stay in the list; only the first data-max are shown. */
        var todoList = $('factTodoList');
        var todoClear = $('factTodoClear');
        var todoMore = $('factTodoMore');
        var todoMoreBtn = $('factTodoMoreBtn');
        var TODO_MAX = todoList ? (parseInt(todoList.dataset.max, 10) || 5) : 5;
        var todoExpanded = false;

        function renderTodo(src, html) {
            if (!todoList) return;
            if (typeof html === 'string') {
                todoList.querySelectorAll('li.fact-todo[data-src="' + src + '"]').forEach(function (li) { li.remove(); });
                todoList.insertAdjacentHTML('beforeend', html);
            }
            var items = Array.prototype.slice.call(todoList.querySelectorAll('li.fact-todo'));
            items.sort(function (a, b) {
                return ((parseInt(a.dataset.prio, 10) || 9) - (parseInt(b.dataset.prio, 10) || 9))
                    || ((parseInt(a.dataset.ts, 10) || 0) - (parseInt(b.dataset.ts, 10) || 0));
            });
            items.forEach(function (li, i) {
                li.hidden = !todoExpanded && i >= TODO_MAX;
                todoList.appendChild(li);
            });
            todoList.hidden = items.length === 0;
            if (todoClear) todoClear.hidden = items.length > 0;
            /* Never cut the list off silently: say how many more there are */
            var extra = items.length - TODO_MAX;
            if (todoMore) todoMore.hidden = extra <= 0;
            if (todoMoreBtn) {
                todoMoreBtn.textContent = todoExpanded ? 'Show fewer' : 'Show ' + Math.max(0, extra) + ' more';
                todoMoreBtn.setAttribute('aria-expanded', todoExpanded ? 'true' : 'false');
            }
        }

        if (todoMoreBtn) {
            todoMoreBtn.addEventListener('click', function () {
                todoExpanded = !todoExpanded;
                renderTodo('', undefined);
            });
        }

        /* An entry points at the row that has the button: page the card to it, scroll, flash */
        function goToRow(target) {
            var m = /^(eq|rr):(\d+)$/.exec(target || '');
            var el = null;
            if (m) {
                var card = m[1] === 'eq' ? cards.equipment : cards.rooms;
                var sel = m[1] === 'eq' ? '.fact-item[data-req-id="' + m[2] + '"]' : '.fact-item[data-rr-id="' + m[2] + '"]';
                el = card.body ? card.body.querySelector(sel) : null;
                if (el) {
                    var all = Array.prototype.slice.call(card.body.querySelectorAll('.fact-item'));
                    card.page = Math.floor(all.indexOf(el) / CARD_PER_PAGE) + 1;
                    paginateCard(card);
                } else {
                    el = $(m[1] === 'eq' ? 'factEquipment' : 'factRooms');
                }
            } else if (target === 'history-room') {
                var roomTab = panel.querySelector('[data-fact-kind="room"]');
                if (roomTab) roomTab.click();
                el = $('factHistory');
            } else {
                el = $('factRooms');
            }
            if (!el) return;
            var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            el.scrollIntoView({ behavior: calm ? 'auto' : 'smooth', block: 'center' });
            el.classList.add('is-flash');
            setTimeout(function () { el.classList.remove('is-flash'); }, 1800);
        }

        panel.addEventListener('click', function (e) {
            var go = e.target.closest('[data-fact-goto]');
            if (go) goToRow(go.dataset.factGoto);
        });

        /* Swap a card's status line and rows, then page them again (the current page is kept) */
        function swapCard(card, data) {
            if (!card.body) return;
            if (card.status && typeof data.status === 'string') card.status.innerHTML = data.status;
            if (typeof data.body === 'string') card.body.innerHTML = data.body;
            paginateCard(card);
        }

        /* Replace one kind of history row ("room" / "equipment") with fresh ones */
        function swapHistory(kind, html) {
            body.querySelectorAll('tr.fact-row[data-kind="' + kind + '"]').forEach(function (tr) { tr.remove(); });
            body.insertAdjacentHTML('beforeend', html);
            sortRows();
            apply();
        }

        function load(url, key, done) {
            if (inFlight[key]) return;
            inFlight[key] = true;
            fetch(url, { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data || data.error || Array.isArray(data)) return;
                    done(data);
                })
                .catch(function () { /* a failed refresh must never disturb the screen */ })
                .then(function () { inFlight[key] = false; });
        }

        function refreshRooms() {
            load(API_ROOMS, 'rooms', function (data) {
                swapCard(cards.rooms, data);
                if (typeof data.rows === 'string') swapHistory('room', data.rows);
                renderTodo('rooms', data.todo);
            });
        }

        function refreshEquipment() {
            load(API_EQUIP, 'equipment', function (data) {
                swapCard(cards.equipment, data);
                if (typeof data.rows === 'string') swapHistory('equipment', data.rows);
                renderTodo('equipment', data.todo);
            });
        }

        function refreshAll() { refreshEquipment(); refreshRooms(); }

        function panelIsOpen() {
            return panel.classList.contains('active') && !document.hidden;
        }

        /* After booking or cancelling a room */
        document.addEventListener('pupsync:reservation-submitted', refreshRooms);

        /* The 5 s requests poll saw a status change (approved, returned, overdue ...) */
        document.addEventListener('pupsync:requests-changed', function () { if (panelIsOpen()) refreshEquipment(); });

        /* Joining a waitlist does not fire that event — look again shortly after */
        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-action="join-waitlist"], [data-action="cancel-reservation"]')) {
                setTimeout(refreshRooms, 1500);
                setTimeout(refreshRooms, 4000);
            }
        });

        /* Whenever the tab is opened (sidebar, Home key figures, "View all"), make sure it is current */
        if (typeof MutationObserver === 'function') {
            new MutationObserver(function () { if (panel.classList.contains('active')) refreshAll(); })
                .observe(panel, { attributes: true, attributeFilter: ['class'] });
        }

        setInterval(function () { if (panelIsOpen()) refreshAll(); }, REFRESH_MS);
        document.addEventListener('visibilitychange', function () { if (panelIsOpen()) refreshAll(); });

        /* ── 5. Extend borrowing ─────────────────────────────────── */
        var API_EXTEND = 'equipment-booking/api/extend-borrow.php';
        var extModal = $('factExtendModal');
        var extBody = $('factExtendBody');
        var extSubmit = $('factExtendSubmit');
        var ext = { id: 0, equipment: '', busy: false, lastFocus: null };

        /* Moved to <body> so no tab container can clip it or sit above it */
        if (extModal && extModal.parentNode !== document.body) document.body.appendChild(extModal);

        function esc(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function csrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (meta && meta.content) return meta.content;
            var input = document.querySelector('input[name="csrf_token"]');
            return input ? input.value : '';
        }

        function toast(msg, type) {
            if (typeof window.showToast === 'function') window.showToast(esc(msg), type);
        }

        function closeExtend() {
            if (!extModal || ext.busy) return;
            extModal.hidden = true;
            extSubmit.disabled = true;
            if (ext.lastFocus && ext.lastFocus.focus) ext.lastFocus.focus();
        }

        function selectedLength() {
            var picked = extBody.querySelector('input[name="factExtLen"]:checked');
            return picked ? picked.value : '';
        }

        function renderExtend(data, banner) {
            var html = '';
            if (banner) html += '<div class="fact-ext-banner is-error" role="alert">' + esc(banner) + '</div>';

            if (!data || !data.ok) {
                html += '<div class="fact-ext-banner is-error" role="alert">' + esc((data && data.error) || 'We could not check this item. Please try again.') + '</div>';
                extBody.innerHTML = html;
                extSubmit.disabled = true;
                return;
            }

            var late = data.status === 'Overdue' ? '<span class="fact-ext-late">Overdue</span> \u00b7 ' : '';
            var which = data.max ? ' \u00b7 Extension ' + (Number(data.used || 0) + 1) + ' of ' + data.max : '';
            html += '<div class="fact-ext-item"><strong>' + esc(data.item) + (data.qty > 1 ? ' <span class="fact-qty">\u00d7' + data.qty + '</span>' : '') + '</strong>'
                + '<span>' + late + 'Due ' + esc(data.due) + esc(which) + '</span></div>';

            if (!data.options || !data.options.length) {
                html += '<div class="fact-ext-banner is-error" role="alert">' + esc(data.error || 'No extension is available for this item.') + '</div>';
                extBody.innerHTML = html;
                extSubmit.disabled = true;
                return;
            }

            var anyAvailable = data.options.some(function (o) { return o.available; });
            html += '<p class="fact-ext-lead">Extend until</p><div class="fact-ext-opts" role="radiogroup" aria-label="Extension length">';
            var firstFree = true;
            data.options.forEach(function (o) {
                var checked = o.available && firstFree;
                if (checked) firstFree = false;
                html += '<label class="fact-ext-opt' + (o.available ? '' : ' is-disabled') + '">'
                    + '<input type="radio" name="factExtLen" value="' + o.length + '"' + (o.available ? '' : ' disabled') + (checked ? ' checked' : '') + '>'
                    + '<span class="fact-ext-opt-main"><span class="fact-ext-opt-label">' + esc(o.label) + '</span>'
                    + '<span class="fact-ext-opt-until">' + esc(o.until) + '</span></span>'
                    + (o.available ? '' : '<span class="fact-ext-opt-why">' + esc(o.reason) + '</span>')
                    + '</label>';
            });
            html += '</div>';
            if (!anyAvailable) {
                html += '<p class="fact-ext-hint">Other bookings need this equipment, so it cannot be extended right now. Please return it to the Admin Office.</p>';
            } else {
                html += '<p class="fact-ext-hint">Counted from now. Your request is checked against other bookings, today and later, before it is approved.</p>';
            }
            extBody.innerHTML = html;
            extSubmit.disabled = !anyAvailable;
        }

        function loadExtend(banner) {
            extBody.innerHTML = '<p class="fact-ext-loading">Checking availability\u2026</p>';
            extSubmit.disabled = true;
            fetch(API_EXTEND + '?id=' + encodeURIComponent(ext.id), { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (data) { renderExtend(data, banner); })
                .catch(function () { renderExtend({ ok: false, error: 'We could not reach the server. Please try again.' }, banner); });
        }

        function openExtend(id, equipment, trigger) {
            if (!extModal) return;
            ext.id = id;
            ext.equipment = equipment;
            ext.lastFocus = trigger || null;
            extModal.hidden = false;
            loadExtend('');
            var close = extModal.querySelector('.fact-modal-close');
            if (close) close.focus();
        }

        function submitExtend() {
            var length = selectedLength();
            if (!length || ext.busy) return;
            ext.busy = true;
            extSubmit.disabled = true;
            extSubmit.textContent = 'Extending\u2026';

            var fd = new FormData();
            fd.append('id', ext.id);
            fd.append('length', length);
            fd.append('csrf_token', csrfToken());

            fetch(API_EXTEND, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    ext.busy = false;
                    extSubmit.textContent = 'Extend';
                    if (data && data.ok) {
                        closeExtend();
                        toast(data.message || 'Borrowing extended.', 'success');
                        refreshAll();
                        /* Home key figures and the overdue banner follow on the next 5 s poll */
                        document.dispatchEvent(new CustomEvent('pupsync:requests-changed'));
                    } else {
                        /* Denied: say why, and re-check what is still possible */
                        loadExtend((data && data.error) || 'The extension could not be approved.');
                        refreshAll();
                    }
                })
                .catch(function () {
                    ext.busy = false;
                    extSubmit.textContent = 'Extend';
                    loadExtend('We could not reach the server. Please try again.');
                });
        }

        panel.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-action="extend-borrow"]');
            if (!btn) return;
            e.preventDefault();
            openExtend(parseInt(btn.dataset.requestId, 10) || 0, btn.dataset.equipment || '', btn);
        });

        if (extModal) {
            extModal.addEventListener('click', function (e) {
                if (e.target === extModal || e.target.closest('[data-fact-extend-close]')) closeExtend();
            });
            extBody.addEventListener('change', function () { extSubmit.disabled = !selectedLength(); });
            extSubmit.addEventListener('click', submitExtend);
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !extModal.hidden) closeExtend();
            });
        }

        /* ── 6. Cancel an upcoming request / leave a waitlist ─────── */
        var API_CANCEL = 'equipment-booking/api/cancel-borrow.php';
        var API_LEAVE = 'room-reservation/api/leave-waitlist.php';

        function changed() {
            refreshAll();
            /* Home key figures and the overdue banner follow on the next 5 s poll */
            document.dispatchEvent(new CustomEvent('pupsync:requests-changed'));
        }

        panel.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-action="cancel-borrow"]');
            if (!btn) return;
            e.preventDefault();
            var name = btn.dataset.equipment || 'this request';
            if (!confirm('Cancel your request for ' + name + '?\n\nThis cannot be undone.')) return;

            var label = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Cancelling\u2026';

            var fd = new FormData();
            fd.append('id', btn.dataset.requestId);
            fd.append('csrf_token', csrfToken());

            fetch(API_CANCEL, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.ok) {
                        toast(data.message || 'Request cancelled.', 'success');
                    } else {
                        toast((data && data.error) || 'This request could not be cancelled.', 'error');
                        btn.disabled = false;
                        btn.textContent = label;
                    }
                    changed();
                })
                .catch(function () {
                    toast('Network error. Please try again.', 'error');
                    btn.disabled = false;
                    btn.textContent = label;
                });
        });

        panel.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-action="leave-waitlist"]');
            if (!btn) return;
            e.preventDefault();
            var room = btn.dataset.roomName || 'this room';
            if (!confirm('Leave the waitlist for ' + room + '?\n\nYou will no longer be emailed if this slot opens up.')) return;

            var label = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Leaving\u2026';

            fetch(API_LEAVE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    room_id: parseInt(btn.dataset.roomId, 10),
                    reservation_date: btn.dataset.resDate,
                    start_time: btn.dataset.startTime,
                    end_time: btn.dataset.endTime,
                    csrf_token: csrfToken(),
                }),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        toast('You left the waitlist.', 'success');
                    } else {
                        toast((data && data.error) || 'Could not leave the waitlist.', 'error');
                        btn.disabled = false;
                        btn.textContent = label;
                    }
                    refreshRooms();
                })
                .catch(function () {
                    toast('Network error. Please try again.', 'error');
                    btn.disabled = false;
                    btn.textContent = label;
                });
        });

        /* ── Start ───────────────────────────────────────────────── */
        sortRows();
        apply();
        paginateCard(cards.equipment);
        paginateCard(cards.rooms);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startMyActivity);
    else startMyActivity();
})();

/* ================================================================
   ITEM CODE — chevron reveal, copy, regenerate
   Self-contained IIFE. No changes to any existing code above.
================================================================ */
(function () {
    'use strict';

    // The faculty pages carry the token in <meta name="csrf-token"> (faculty-shell-top.php). The hidden
    // csrf_token <input> that this used to read only exists on admin screens, so the code request was sent
    // with an empty token and rejected (403 "Invalid or expired session").
    var CSRF = function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;
        var el = document.querySelector('input[name="csrf_token"]');
        return el ? el.value : '';
    };

    // Per-panel state: { loaded, code, revealed }
    var _state = {};

    function getPanel(itemId) {
        return document.getElementById('eq-ic-panel-' + itemId);
    }

    function setState(itemId, patch) {
        _state[itemId] = Object.assign(_state[itemId] || {}, patch);
    }

    function getState(itemId) {
        return _state[itemId] || {};
    }

    function showLoading(panel) {
        panel.querySelector('.eq-ic-loading').style.display = '';
        panel.querySelector('.eq-ic-content').style.display = 'none';
        panel.querySelector('.eq-ic-error').style.display = 'none';
    }

    function showContent(panel, code, isUsed, revealed) {
        panel.querySelector('.eq-ic-loading').style.display = 'none';
        panel.querySelector('.eq-ic-content').style.display = '';
        panel.querySelector('.eq-ic-error').style.display = 'none';
        var codeEl = panel.querySelector('.eq-ic-code');
        codeEl.textContent = revealed ? code : '••••••••••••';
        codeEl.dataset.code = code;
        var revealBtn = panel.querySelector('.eq-ic-reveal-btn');
        var revealIcon = revealBtn.querySelector('.material-symbols-outlined');
        revealIcon.textContent = revealed ? 'visibility_off' : 'visibility';
        var usedNotice = panel.querySelector('.eq-ic-used-notice');
        if (usedNotice) usedNotice.style.display = isUsed ? '' : 'none';
    }

    function showError(panel, msg) {
        panel.querySelector('.eq-ic-loading').style.display = 'none';
        panel.querySelector('.eq-ic-content').style.display = 'none';
        var errEl = panel.querySelector('.eq-ic-error');
        errEl.textContent = msg;
        errEl.style.display = '';
    }

    /* The code panel floats over its card (see faculty-dashboard.css). Only one is open at a time, and it
       closes when you click anywhere else or press Esc. */
    function closeCodePanel(card) {
        var p = card.querySelector('.eq-ic-panel');
        if (p) p.style.display = 'none';
        var b = card.querySelector('[data-action="toggle-item-code"]');
        if (b) {
            b.setAttribute('aria-expanded', 'false');
            var ch = b.querySelector('.eq-ic-chevron');
            if (ch) ch.style.transform = '';
        }
        card.classList.remove('eq-ic-open');
    }

    function closeAllCodePanels(exceptCard) {
        document.querySelectorAll('.eq-item-card.eq-ic-open').forEach(function (c) {
            if (c !== exceptCard) closeCodePanel(c);
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAllCodePanels(null);
    });

    function loadCode(itemId, panel) {
        showLoading(panel);
        var body = new URLSearchParams({ csrf_token: CSRF(), item_id: itemId });
        fetch('equipment-booking/api/get-item-code.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status !== 'success') {
                    showError(panel, data.error || 'Could not load code.');
                    return;
                }
                setState(itemId, { loaded: true, code: data.code, isUsed: data.is_used, revealed: false });
                showContent(panel, data.code, data.is_used, false);
            })
            .catch(function () {
                showError(panel, 'Network error. Please try again.');
            });
    }

    document.addEventListener('click', function (e) {
        // Click anywhere outside the code layer closes it
        if (!e.target.closest('.eq-ic-panel, [data-action="toggle-item-code"]')) closeAllCodePanels(null);
        // ── Toggle chevron ──────────────────────────────────────────
        var toggleBtn = e.target.closest('[data-action="toggle-item-code"]');
        if (toggleBtn && !toggleBtn.disabled) {
            var itemId = toggleBtn.dataset.itemId;
            var panel = getPanel(itemId);
            if (!panel) return;

            var card = toggleBtn.closest('.eq-item-card');
            var isOpen = panel.style.display !== 'none';
            if (isOpen) {
                panel.style.display = 'none';
                toggleBtn.setAttribute('aria-expanded', 'false');
                toggleBtn.querySelector('.eq-ic-chevron').style.transform = '';
                if (card) card.classList.remove('eq-ic-open');
            } else {
                closeAllCodePanels(card);
                if (card) card.classList.add('eq-ic-open');
                panel.style.display = '';
                toggleBtn.setAttribute('aria-expanded', 'true');
                toggleBtn.querySelector('.eq-ic-chevron').style.transform = 'rotate(180deg)';
                // Load code on first open, or if not loaded yet
                if (!getState(itemId).loaded) {
                    loadCode(itemId, panel);
                } else {
                    var s = getState(itemId);
                    showContent(panel, s.code, s.isUsed, s.revealed);
                }
            }
            return;
        }

        // ── Reveal / hide code ──────────────────────────────────────
        var revealBtn = e.target.closest('[data-action="reveal-item-code"]');
        if (revealBtn) {
            var panel = revealBtn.closest('.eq-ic-panel');
            if (!panel) return;
            var itemId = panel.id.replace('eq-ic-panel-', '');
            var s = getState(itemId);
            if (!s.code) return;
            s.revealed = !s.revealed;
            setState(itemId, { revealed: s.revealed });
            showContent(panel, s.code, s.isUsed, s.revealed);
            return;
        }

        // ── Copy code ───────────────────────────────────────────────
        var copyBtn = e.target.closest('[data-action="copy-item-code"]');
        if (copyBtn) {
            var panel = copyBtn.closest('.eq-ic-panel');
            if (!panel) return;
            var itemId = panel.id.replace('eq-ic-panel-', '');
            var code = getState(itemId).code;
            if (!code) return;
            navigator.clipboard.writeText(code).then(function () {
                var icon = copyBtn.querySelector('.material-symbols-outlined');
                icon.textContent = 'check';
                setTimeout(function () { icon.textContent = 'content_copy'; }, 1500);
            }).catch(function () {
                // Fallback for older browsers
                var ta = document.createElement('textarea');
                ta.value = code;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            });
            return;
        }

        // ── Regenerate code ─────────────────────────────────────────
        var regenBtn = e.target.closest('[data-action="regen-item-code"]');
        if (regenBtn) {
            if (!confirm('This will invalidate the current code. Any student who has it can no longer use it. Generate a new code?')) return;
            var itemId = regenBtn.dataset.itemId;
            var panel = getPanel(itemId);
            if (!panel) return;
            showLoading(panel);

            var body = new URLSearchParams({ csrf_token: CSRF(), item_id: itemId });
            fetch('equipment-booking/api/regenerate-item-code.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.status !== 'success') {
                        showError(panel, data.error || 'Could not regenerate code.');
                        return;
                    }
                    setState(itemId, { loaded: true, code: data.code, isUsed: false, revealed: true });
                    showContent(panel, data.code, false, true);
                })
                .catch(function () {
                    showError(panel, 'Network error. Please try again.');
                });
        }
    });
})();
