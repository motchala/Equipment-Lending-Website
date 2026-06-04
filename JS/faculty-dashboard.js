(function () {
    'use strict';

    const todayStr = new Date().toISOString().split('T')[0];

    /* ══════════════════════════════════════════════════════════════════
       CRITICAL: Close all overlays on page load to prevent stuck modals
    ══════════════════════════════════════════════════════════════════ */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
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

        // 8. Notification read state
        const readIdxArr = LS.getJ('notifRead');
        if (readIdxArr && readIdxArr.length) {
            const items = document.querySelectorAll('.notif-item, .notif-card');
            let unread = 0;
            items.forEach((item, i) => {
                if (readIdxArr.includes(i)) {
                    item.classList.remove('unread');
                    const dot = item.querySelector('.unread-dot');
                    if (dot) dot.style.display = 'none';
                } else if (item.classList.contains('unread')) {
                    unread++;
                }
            });
            const uc = document.getElementById('unreadCount');
            if (uc) uc.textContent = unread + ' unread';
            if (unread === 0) document.querySelectorAll('.notif-badge').forEach(b => b.style.display = 'none');
            else document.querySelectorAll('.notif-badge').forEach(b => {
                b.style.display = '';
                b.textContent = unread;
            });
        }
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
            if (ch) ch.style.display = 'none';
        });
        const key = tMap[theme] || theme;
        const el = document.getElementById('tp-' + key);
        const ch = document.getElementById('tc-' + key);
        if (el) el.classList.add('selected');
        if (ch) ch.style.display = '';
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
        // Colour the toast based on type
        if (type === 'error') {
            t.style.background = 'var(--color-error, #ba1a1a)';
        } else if (type === 'success') {
            t.style.background = 'var(--color-primary-container, #570000)';
        } else {
            t.style.background = '';
        }
        t.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:6px;">check_circle</span> ' + msg;
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
        // Always close all overlays first
        document.querySelectorAll('.overlay-page.active').forEach(o => o.classList.remove('active'));
        if (!state || state.type === 'tab') {
            const tab = (state && state.value) ? state.value : 'home';
            _switchTabDOM(tab);
            if (state && state.sub) switchLendingSub(state.sub);
        } else if (state.type === 'overlay') {
            _switchTabDOM('home'); // ensure a tab is active behind overlay
            _openOverlayDOM(state.value);
        }
        _navSuppressed = false;
    }

    window.addEventListener('popstate', function (e) {
        _restoreNav(e.state);
    });

    /* ── Profile Dropdown ──────────────────────────────────────────────── */
    function openDropdown() {
        const dd = document.getElementById('profileDropdown');
        const btn = document.getElementById('avatarBtn');
        if (dd) dd.classList.add('open');
        if (btn) btn.setAttribute('aria-expanded', 'true');
    }

    function closeDropdown() {
        const dd = document.getElementById('profileDropdown');
        const btn = document.getElementById('avatarBtn');
        if (dd) dd.classList.remove('open');
        if (btn) btn.setAttribute('aria-expanded', 'false');
    }

    function toggleDropdown() {
        const dd = document.getElementById('profileDropdown');
        if (dd && dd.classList.contains('open')) closeDropdown();
        else openDropdown();
    }

    /* ── Overlays ──────────────────────────────────────────────────────── */

    function _openOverlayDOM(id) {
        closeDropdown();
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
        }
        // Highlight Help Center sidebar item when help overlay is open
        if (id === 'helpOverlay') {
            const helpNavItem = document.querySelector('.side-nav-item[data-target="helpOverlay"]');
            if (helpNavItem) helpNavItem.classList.add('active');
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
        const sub = document.getElementById('lending-' + subName);
        if (sub) sub.classList.add('active');
        document.querySelectorAll('.lending-nav-btn').forEach(b => b.classList.remove('active'));
        const btn = document.querySelector('.lending-nav-btn[data-lending-nav="' + subName + '"]');
        if (btn) btn.classList.add('active');
        document.querySelectorAll('.lending-sub').forEach(s => {
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

    /* ── Equipment Search/Filter ───────────────────────────────────────── */
    function filterEquipment() {
        const search = (document.getElementById('equipmentSearch').value || '').toLowerCase();
        const category = (document.getElementById('categoryFilter').value || '').toLowerCase();
        document.querySelectorAll('.item-node').forEach(item => {
            const nameMatch = item.dataset.name.includes(search);
            const catMatch = !category || item.dataset.category === category;
            item.style.display = (nameMatch && catMatch) ? '' : 'none';
        });
    }

    /* ── Borrow Form ───────────────────────────────────────────────────── */
    function openBorrowForm(itemName) {
        document.getElementById('selectedItem').value = itemName;
        document.getElementById('selectedItemLabel').textContent = itemName;
        switchTab('lending', 'form');
        switchLendingSub('form');
    }

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

    /* ── Notifications ─────────────────────────────────────────────────── */
    function filterNotifs(cat) {
        document.querySelectorAll('.notif-tab').forEach(t => t.classList.remove('active'));
        const btn = document.querySelector('.notif-tab[data-notif-filter="' + cat + '"]');
        if (btn) btn.classList.add('active');
        document.querySelectorAll('.notif-card').forEach(item => {
            if (cat === 'all') item.style.display = '';
            else if (cat === 'unread') item.style.display = item.classList.contains('unread') ? '' : 'none';
            else item.style.display = item.dataset.cat === cat ? '' : 'none';
        });
        document.querySelectorAll('.notif-section-label').forEach(label => {
            let next = label.nextElementSibling;
            let hasVisible = false;
            while (next && !next.classList.contains('notif-section-label')) {
                if (next.style.display !== 'none') hasVisible = true;
                next = next.nextElementSibling;
            }
            label.style.display = hasVisible ? '' : 'none';
        });
    }

    function markAllRead() {
        const readArr = [];
        document.querySelectorAll('.notif-item, .notif-card').forEach((item, i) => {
            item.classList.remove('unread');
            const dot = item.querySelector('.unread-dot');
            if (dot) dot.style.display = 'none';
            readArr.push(i);
        });
        const uc = document.getElementById('unreadCount');
        if (uc) uc.textContent = '0 unread';
        document.querySelectorAll('.notif-badge').forEach(b => b.style.display = 'none');
        LS.setJ('notifRead', readArr);
        showToast('All notifications marked as read.');
    }

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

        fetch('includes/update-profile.php', { method: 'POST', body: fd })
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
                        document.querySelectorAll('.dd-name, .u-name, .acc-hero-info h2').forEach(el => {
                            el.textContent = data.fullname;
                        });
                        // Update initials
                        const parts = data.fullname.trim().split(' ');
                        let ini = parts[0].charAt(0).toUpperCase();
                        if (parts.length > 1) ini += parts[parts.length - 1].charAt(0).toUpperCase();
                        document.querySelectorAll('.avatar-btn, .dd-avatar, .acc-avatar-large').forEach(el => {
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
        fd.append('current_password', current);
        fd.append('new_password', newPw);
        fd.append('confirm_password', confirm);

        fetch('includes/update-profile.php', { method: 'POST', body: fd })
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
        fd.append('email', email);

        fetch('includes/update-profile.php', { method: 'POST', body: fd })
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
        fd.append('backup_email', email);

        fetch('includes/update-profile.php', { method: 'POST', body: fd })
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

        fetch('includes/update-profile.php', { method: 'POST', body: fd })
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
        const fullname = fullnameEl ? fullnameEl.textContent : '';
        const parts = fullname.trim().split(' ');
        let ini = parts[0].charAt(0).toUpperCase();
        if (parts.length > 1) ini += parts[parts.length - 1].charAt(0).toUpperCase();

        // Remove images and set initials
        document.querySelectorAll('#avatarBtn, .dd-avatar, .acc-avatar-large').forEach(el => {
            const img = el.querySelector('.avatar-img');
            if (img) img.remove();
            // Set text content (preserve cam-btn if present)
            const textNode = [...el.childNodes].find(n => n.nodeType === Node.TEXT_NODE);
            if (textNode) {
                textNode.textContent = ini;
            } else if (!el.querySelector('.cam-btn')) {
                el.textContent = ini;
            } else {
                el.insertBefore(document.createTextNode(ini), el.firstChild);
            }
        });
    }

    function updateAvatarsToImage(url) {
        document.querySelectorAll('#avatarBtn, .dd-avatar, .acc-avatar-large').forEach(el => {
            // Remove text nodes and existing images
            [...el.childNodes].forEach(n => {
                if (n.nodeType === Node.TEXT_NODE && n.textContent.trim()) n.remove();
                if (n.classList && n.classList.contains('avatar-img')) n.remove();
            });
            // Add new image
            const img = document.createElement('img');
            img.src = url;
            img.alt = 'Profile';
            img.className = 'avatar-img';
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
            fd.append('profile_picture', file);

            fetch('includes/update-profile.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (loadingEl) loadingEl.classList.remove('active');
                    if (data.success) {
                        updateAvatarsToImage('/Equipment-Lending-Website/' + data.profile_picture + '?t=' + Date.now());
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

    /* ── Requests Table — Client-Side Render ───────────────────────────── */
    let _reqCurrentFilter = 'All';
    let _reqSortOrder = 'desc'; // desc = latest first

    function _escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function _statusPill(status) {
        const map = {
            'Waiting': { cls: 'status-waiting', icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>', label: 'Pending' },
            'Approved': { cls: 'status-approved', icon: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>', label: 'Approved' },
            'Declined': { cls: 'status-declined', icon: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>', label: 'Declined' },
            'Overdue': { cls: 'status-overdue', icon: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>', label: 'Overdue' },
            'Returned': { cls: 'status-returned', icon: '<polyline points="20 6 9 17 4 12"/>', label: 'Returned' },
        };
        const d = map[status] || map['Waiting'];
        const sa = `xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px;vertical-align:middle;"`;
        return `<span class="status-pill ${d.cls}"><svg ${sa}>${d.icon}</svg>${d.label}</span>`;
    }

    function renderRequestsTable() {
        const tbody = document.getElementById('requestsTbody');
        if (!tbody) return;
        const data = (window.REQUESTS_DATA || []).slice();

        // Sort
        data.sort((a, b) => {
            const da = new Date(a.request_date || a.borrow_date || '2000-01-01');
            const db = new Date(b.request_date || b.borrow_date || '2000-01-01');
            return _reqSortOrder === 'desc' ? db - da : da - db;
        });

        // Filter
        const filtered = _reqCurrentFilter === 'All' ? data : data.filter(r => {
            const s = (r.status || '').trim();
            if (_reqCurrentFilter === 'Waiting') return s === 'Waiting';
            return s === _reqCurrentFilter;
        });

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7"><div class="table-empty"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="36" height="36" style="width:36px;height:36px;display:block;margin:0 auto 8px;opacity:0.7;"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>No requests found for this filter.</div></td></tr>`;
            return;
        }

        tbody.innerHTML = filtered.map(r => {
            const noteCol = r.status === 'Declined'
                ? `<span style="font-size:0.8rem;color:var(--text-light);">${_escHtml(r.reason)}</span>`
                : r.status === 'Overdue'
                    ? `<span style="font-size:0.8rem;color:#e65100;font-weight:600;">Past due: ${_escHtml(r.return_date)}</span>`
                    : '—';

            // Show QR button for Approved and Overdue rows that have a return token
            const qrBtn = (r.status === 'Approved' || r.status === 'Overdue') && r.return_token
                ? `<button class="btn-show-qr" data-action="show-return-qr"
                       data-token="${_escHtml(r.return_token)}"
                       data-equipment="${_escHtml(r.equipment_name)}"
                       title="Show return QR code">
                       <span class="material-symbols-outlined" style="font-size:15px;vertical-align:middle;margin-right:4px;">qr_code_2</span>Return QR
                   </button>`
                : '';

            return `<tr class="${r.status === 'Overdue' ? 'row-overdue' : ''}">
                        <td><strong>${_escHtml(r.equipment_name)}</strong></td>
                        <td>${_escHtml(r.instructor)}</td>
                        <td>${_escHtml(r.room)}</td>
                        <td>${_escHtml(r.borrow_date)}</td>
                        <td>${_escHtml(r.return_date)}</td>
                        <td>${_statusPill(r.status)}</td>
                        <td>${noteCol}${qrBtn}</td>
                    </tr>`;
        }).join('');
    }

    function setRequestsFilter(status) {
        _reqCurrentFilter = status;
        const dd = document.getElementById('reqStatusFilter');
        if (dd) dd.value = status === 'Waiting' ? 'Waiting' : status;
        renderRequestsTable();
    }

    function toggleReqSort() {
        _reqSortOrder = _reqSortOrder === 'desc' ? 'asc' : 'desc';
        const lbl = document.getElementById('reqSortLabel');
        const btn = document.getElementById('reqSortBtn');
        if (lbl) lbl.textContent = _reqSortOrder === 'desc' ? 'Latest First' : 'Oldest First';
        if (btn) {
            const svg = btn.querySelector('svg');
            if (svg) svg.style.transform = _reqSortOrder === 'asc' ? 'rotate(180deg)' : '';
        }
        renderRequestsTable();
    }

    function checkOverdueState() {
        const overdueCount = (typeof window.OVERDUE_COUNT !== 'undefined')
            ? window.OVERDUE_COUNT
            : (window.REQUESTS_DATA || []).filter(r => (r.status || '').trim() === 'Overdue').length;
        // Update overdue stat value
        const statEl = document.getElementById('statOverdueVal');
        if (statEl) statEl.textContent = overdueCount;
        // Show/hide overdue alert
        const alertEl = document.getElementById('overdue-alert');
        if (alertEl) alertEl.style.display = overdueCount > 0 ? '' : 'none';
        // Update notification badges
        const baseUnread = 3 + overdueCount;
        document.querySelectorAll('.notif-badge').forEach(b => {
            if (overdueCount > 0) { b.style.display = ''; b.textContent = baseUnread; }
        });
    }

    /* ── Borrow Form Init ──────────────────────────────────────────────── */
    function initBorrowForm() {
        const form = document.getElementById('borrowForm');
        const borrowInp = document.getElementById('borrow_date');
        const returnInp = document.getElementById('return_date');
        if (!form || !borrowInp || !returnInp) return;

        borrowInp.min = todayStr;
        returnInp.min = todayStr;

        borrowInp.addEventListener('change', function () {
            returnInp.min = this.value;
            if (returnInp.value && returnInp.value < this.value) returnInp.value = this.value;
        });

        form.addEventListener('submit', function (e) {
            const bv = borrowInp.value;
            const rv = returnInp.value;
            if (bv < todayStr) {
                e.preventDefault();
                alert('The borrow date cannot be in the past.');
                return;
            }
            if (rv < bv) {
                e.preventDefault();
                alert('The return date cannot be earlier than the borrow date.');
                return;
            }
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
                case 'filter-requests':
                    // From stat card click — go to My Requests tab with filter
                    switchTab('lending', 'requests');
                    switchLendingSub('requests');
                    setRequestsFilter(el.dataset.status);
                    break;
                case 'filter-requests-dd':
                    setRequestsFilter(el.value);
                    break;
                case 'toggle-sort':
                    toggleReqSort();
                    break;
                case 'go-tab':
                    switchTab(el.dataset.tab, el.dataset.lending || null);
                    if (el.dataset.lending) switchLendingSub(el.dataset.lending);
                    break;
                case 'open-borrow-form':
                    openBorrowForm(el.dataset.item);
                    break;
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
                    closeDropdown();
                    if (confirm('Confirm Logout?')) window.location.href = 'includes/logout.php';
                    break;
            }
        } catch (err) {
            console.warn('Action "' + action + '" failed:', err);
        }
    });

    /* ── Avatar button ────────────────────────────────────────────────── */
    const avatarBtn = document.getElementById('avatarBtn');
    if (avatarBtn) {
        avatarBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            closeNotifPopover();
            toggleDropdown();
        });
    }

    /* ── Close dropdown on outside click ─────────────────────────────── */
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#avatarWrap')) closeDropdown();
        if (!e.target.closest('#notifWrap')) closeNotifPopover();
    });

    /* ── Notification bell popover ────────────────────────────────────── */
    function openNotifPopover() {
        const pop = document.getElementById('notifPopover');
        const btn = document.getElementById('notifBtn');
        if (pop) pop.classList.add('open');
        if (btn) btn.setAttribute('aria-expanded', 'true');
    }
    function closeNotifPopover() {
        const pop = document.getElementById('notifPopover');
        const btn = document.getElementById('notifBtn');
        if (pop) pop.classList.remove('open');
        if (btn) btn.setAttribute('aria-expanded', 'false');
    }
    const notifBtn = document.getElementById('notifBtn');
    if (notifBtn) {
        notifBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            closeDropdown();
            const pop = document.getElementById('notifPopover');
            if (pop && pop.classList.contains('open')) closeNotifPopover();
            else openNotifPopover();
        });
    }

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

    /* ── Side nav clicks ──────────────────────────────────────────────── */
    document.querySelectorAll('.side-nav-item[data-tab]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            closeMobileNav();
            // Close any open overlays
            document.querySelectorAll('.overlay-page.active').forEach(o => o.classList.remove('active'));
            // Remove active from all sidebar items (including settings and help)
            document.querySelectorAll('.side-nav-item').forEach(b => b.classList.remove('active'));
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

    /* ── Lending sub-nav ──────────────────────────────────────────────── */
    document.querySelectorAll('.lending-nav-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            switchLendingSub(this.dataset.lendingNav);
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

    /* ── Notification filter tabs ─────────────────────────────────────── */
    document.querySelectorAll('.notif-tab').forEach(btn => {
        btn.addEventListener('click', function () {
            filterNotifs(this.dataset.notifFilter);
        });
    });

    /* ── Requests status filter dropdown ──────────────────────────────── */
    const reqStatusFilter = document.getElementById('reqStatusFilter');
    if (reqStatusFilter) reqStatusFilter.addEventListener('change', function () {
        setRequestsFilter(this.value);
    });

    /* ── Equipment search/filter ──────────────────────────────────────── */
    const eqSearch = document.getElementById('equipmentSearch');
    const eqCat = document.getElementById('categoryFilter');
    if (eqSearch) eqSearch.addEventListener('input', filterEquipment);
    if (eqCat) eqCat.addEventListener('change', filterEquipment);

    /* ── Global Live Search ───────────────────────────────────────────── */
    (function initLiveSearch() {
        const input = document.getElementById('globalSearch');
        const dropdown = document.getElementById('liveSearchDropdown');
        if (!input || !dropdown) return;

        let debounceTimer = null;
        let currentXhr = null; // cancel in-flight fetch when user keeps typing

        function toTitleCase(str) {
            return str.replace(/\b\w/g, c => c.toUpperCase());
        }
        function escHtml(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        /* Build and render dropdown from combined results */
        function renderDropdown(q, inventoryItems) {
            const ql = q.trim().toLowerCase();
            if (ql.length < 2) { dropdown.style.display = 'none'; return; }

            // Equipment results — from live-search.php JSON (always fresh, works on any tab)
            const eqResults = (inventoryItems || []).slice(0, 5);

            // Also search DOM .item-node elements as a fallback / supplement
            // (these are rendered when the user visits the Equipment tab)
            const domItemNames = new Set(eqResults.map(i => (i.name || '').toLowerCase()));
            document.querySelectorAll('.item-node').forEach(el => {
                const name = (el.dataset.name || '').toLowerCase();
                const cat = (el.dataset.category || '').toLowerCase();
                if ((name.includes(ql) || cat.includes(ql)) && !domItemNames.has(name)) {
                    eqResults.push({
                        id: null,
                        name: el.dataset.name || '',
                        category: el.dataset.category || '',
                        quantity: null,
                        available: true,
                        image: ''
                    });
                    domItemNames.add(name);
                }
            });

            // Requests — from pre-loaded window.REQUESTS_DATA (user's own requests)
            const requests = window.REQUESTS_DATA || [];
            const rqResults = [];
            requests.forEach(r => {
                const haystack = ((r.equipment_name || '') + ' ' + (r.status || '') + ' ' + (r.room || '')).toLowerCase();
                if (haystack.includes(ql)) rqResults.push(r);
            });
            const rqSlice = rqResults.slice(0, 4);

            if (!eqResults.length && !rqSlice.length) {
                dropdown.innerHTML = '<div class="ls-empty"><span class="material-symbols-outlined">search_off</span> No results for "<strong>' + escHtml(q) + '</strong>"</div>';
                dropdown.style.display = 'block';
                attachClickHandlers();
                return;
            }

            let html = '';

            if (eqResults.length) {
                html += '<div class="ls-group-label"><span class="material-symbols-outlined" style="font-size:14px">inventory_2</span> Equipment</div>';
                eqResults.forEach(item => {
                    const availBadge = item.quantity !== null
                        ? (item.available
                            ? '<span class="status-chip chip-success" style="font-size:11px;padding:2px 8px;margin-left:auto"><span class="chip-dot"></span>Available</span>'
                            : '<span class="status-chip chip-error"   style="font-size:11px;padding:2px 8px;margin-left:auto"><span class="chip-dot"></span>No Stock</span>')
                        : '';
                    html += '<div class="ls-item" data-ls-type="equipment" data-ls-name="' + escHtml(item.name) + '">' +
                        '<span class="material-symbols-outlined ls-item-icon">inventory_2</span>' +
                        '<div style="flex:1"><div class="ls-item-title">' + escHtml(toTitleCase(item.name)) + '</div>' +
                        '<div class="ls-item-sub">' + escHtml(toTitleCase(item.category)) + '</div></div>' +
                        availBadge +
                        '</div>';
                });
            }

            if (rqSlice.length) {
                html += '<div class="ls-group-label"><span class="material-symbols-outlined" style="font-size:14px">receipt_long</span> My Requests</div>';
                rqSlice.forEach(r => {
                    const chipClass = r.status === 'Approved' ? 'chip-success'
                        : r.status === 'Overdue' ? 'chip-error'
                            : r.status === 'Waiting' ? 'chip-warning'
                                : 'chip-muted';
                    html += '<div class="ls-item" data-ls-type="request" data-ls-status="' + escHtml(r.status) + '">' +
                        '<span class="material-symbols-outlined ls-item-icon">receipt_long</span>' +
                        '<div style="flex:1"><div class="ls-item-title">' + escHtml(r.equipment_name) + '</div>' +
                        '<div class="ls-item-sub">' + escHtml(r.borrow_date) + ' → ' + escHtml(r.return_date) + '</div></div>' +
                        '<span class="status-chip ' + chipClass + '" style="font-size:11px;padding:2px 8px;"><span class="chip-dot"></span>' + escHtml(r.status) + '</span>' +
                        '</div>';
                });
            }

            dropdown.innerHTML = html;
            dropdown.style.display = 'block';
            attachClickHandlers();
        }

        /* Wire up click/mousedown on rendered results */
        function attachClickHandlers() {
            dropdown.querySelectorAll('.ls-item').forEach(item => {
                item.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    if (this.dataset.lsType === 'equipment') {
                        switchTab('lending');
                        switchLendingSub('browse');
                        const es = document.getElementById('equipmentSearch');
                        if (es) { es.value = this.dataset.lsName; filterEquipment(); }
                    } else {
                        switchTab('lending');
                        switchLendingSub('requests');
                        const sf = document.getElementById('reqStatusFilter');
                        if (sf) { sf.value = this.dataset.lsStatus || 'All'; setRequestsFilter(sf.value); }
                    }
                    input.value = '';
                    dropdown.style.display = 'none';
                });
            });
        }

        /* Main entry: show spinner, fetch from PHP, then render */
        function buildDropdown(q) {
            q = (q || '').trim();
            if (q.length < 2) { dropdown.style.display = 'none'; return; }

            // Show loading state immediately so the search feels responsive
            dropdown.innerHTML = '<div class="ls-empty" style="padding:10px 14px;">' +
                '<span class="material-symbols-outlined" style="animation:spin 1s linear infinite;display:inline-block;font-size:18px;vertical-align:middle;margin-right:6px">progress_activity</span>' +
                'Searching…</div>';
            dropdown.style.display = 'block';

            // Cancel any in-flight request
            if (currentXhr) { currentXhr.abort(); currentXhr = null; }

            const ctrl = new AbortController();
            currentXhr = ctrl;

            fetch('live-search.php?section=user_inventory&q=' + encodeURIComponent(q), {
                signal: ctrl.signal
            })
                .then(res => {
                    if (!res.ok) throw new Error('Network error ' + res.status);
                    return res.json();
                })
                .then(items => {
                    currentXhr = null;
                    renderDropdown(q, items);
                })
                .catch(err => {
                    if (err.name === 'AbortError') return; // user typed again — ignore
                    currentXhr = null;
                    // Fall back to DOM-only search if PHP is unreachable
                    renderDropdown(q, []);
                });
        }

        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            const val = this.value;
            if (!val.trim() || val.trim().length < 2) { dropdown.style.display = 'none'; return; }
            debounceTimer = setTimeout(() => buildDropdown(val), 220);
        });

        input.addEventListener('focus', function () {
            dropdown.style.display = 'none';
        });

        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { dropdown.style.display = 'none'; input.blur(); }
        });
    })();

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
        renderRequestsTable();
        checkOverdueState();
        startRequestsPolling();
        initCodePanel();
        startInventoryPolling();

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
        fetch('includes/update-profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_completion_status'
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
            formData.append('program', program);
            formData.append('year_level', year_level);

            fetch('includes/update-profile.php', {
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
            formData.append('phone', document.querySelector('[data-input="phone"]')?.value || '');
            formData.append('present_address', document.querySelector('[data-input="present_address"]')?.value || '');
            formData.append('permanent_address', document.querySelector('[data-input="permanent_address"]')?.value || '');
            formData.append('landline', document.querySelector('[data-input="landline"]')?.value || '');

            fetch('includes/update-profile.php', {
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
            formData.append('emergency_name', document.querySelector('[data-input="emergency_name"]')?.value || '');
            formData.append('emergency_relationship', document.querySelector('[data-input="emergency_relationship"]')?.value || '');
            formData.append('emergency_phone', document.querySelector('[data-input="emergency_phone"]')?.value || '');

            fetch('includes/update-profile.php', {
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

        // Add all data fields
        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });

        fetch('includes/update-profile.php', {
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
            formData.append('fullname', fullname);
            formData.append('dob', dob);
            formData.append('gender', gender);
            formData.append('nationality', nationality);

            fetch('includes/update-profile.php', {
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
                formData.append('profile_picture', file);

                fetch('includes/update-profile.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.msg, 'success');
                            // Update all avatar displays
                            const newPicUrl = '/Equipment-Lending-Website/' + data.profile_picture + '?t=' + Date.now();
                            document.querySelectorAll('#profileAvatarLarge, .dd-avatar, .avatar-btn, .top-bar-avatar').forEach(el => {
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

                fetch('includes/update-profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=remove_profile_picture'
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.msg, 'success');
                            // Get initials from the page
                            const fullname = document.querySelector('.dd-name')?.textContent || 'U';
                            const parts = fullname.trim().split(' ');
                            let initials = parts[0].charAt(0).toUpperCase();
                            if (parts.length > 1) initials += parts[parts.length - 1].charAt(0).toUpperCase();

                            // Update all avatar displays to show initials
                            document.querySelectorAll('#profileAvatarLarge, .dd-avatar, .avatar-btn').forEach(el => {
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
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
            script.onload = () => { window._qrLoaded = true; _renderQr(url); };
            document.head.appendChild(script);
        }
    }

    function _renderQr(url) {
        const container = document.getElementById('returnQrCanvas');
        if (!container || typeof QRCode === 'undefined') return;
        new QRCode(container, {
            text: url,
            width: 200,
            height: 200,
            colorDark: '#600302',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    }

    function _updateFacultyStatCards(data) {
        const counts = {
            'Active Borrowings': data.filter(r => r.status === 'Approved').length,
            'Pending Requests': data.filter(r => r.status === 'Waiting').length,
            'Total Requests': data.length,
        };
        document.querySelectorAll('.stat-card').forEach(card => {
            const label = (card.querySelector('.stat-card-label') || {}).textContent?.trim();
            const val = card.querySelector('.stat-card-value');
            if (!val || !(label in counts)) return;
            if (val.textContent.trim() !== String(counts[label])) {
                val.textContent = counts[label];
            }
        });
    }

    function startInventoryPolling() {
        const INTERVAL = 8000;

        function doPoll() {
            fetch('includes/poll-inventory.php', { method: 'GET' })
                .then(r => r.json())
                .then(items => {
                    if (!Array.isArray(items)) return;
                    items.forEach(function (item) {
                        const card = document.querySelector('.item-node[data-item-id="' + item.item_id + '"]');
                        if (!card) return;
                        const qty = parseInt(item.quantity, 10);

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
                            btn.disabled = qty <= 0;
                            btn.textContent = qty > 0 ? 'Borrow' : 'Unavailable';
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
            fetch('includes/poll-requests.php', { method: 'GET' })
                .then(r => r.json())
                .then(fresh => {
                    if (!Array.isArray(fresh)) return;

                    let changed = false;

                    // Check for any status changes
                    fresh.forEach(r => {
                        if (lastStatuses[r.id] !== r.status) {
                            changed = true;
                            lastStatuses[r.id] = r.status;

                            // Show toast for specific transitions
                            if (r.status === 'Returned') {
                                showToast(r.equipment_name + ' has been marked as Returned.', 'success');
                            } else if (r.status === 'Approved') {
                                showToast(r.equipment_name + ' request has been Approved!', 'success');
                            } else if (r.status === 'Declined') {
                                showToast(r.equipment_name + ' request was Declined.', 'error');
                            }
                        }
                    });

                    if (changed) {
                        // Update the global data and re-render
                        window.REQUESTS_DATA = fresh;
                        window.OVERDUE_COUNT = fresh.filter(r => r.status === 'Overdue').length; // keep checkOverdueState in sync
                        renderRequestsTable();
                        checkOverdueState();
                        _updateFacultyStatCards(fresh);
                    }
                })
                .catch(() => { }); // silently ignore network errors
        }, INTERVAL);
    }

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
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function initCodePanel() {
        // Load initial state
        fetch('includes/poll-faculty-codes.php', { credentials: 'same-origin' })
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

            fetch('includes/generate-faculty-code.php', {
                method: 'POST',
                credentials: 'same-origin'
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
            fetch('includes/poll-faculty-codes.php', { credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) return null; return r.json(); })
                .then(function (data) { if (data) renderCodePanel(data); })
                .catch(function () {});
        }

        doPoll();                        // fire immediately
        setInterval(doPoll, 5000);       // then every 5 seconds
    }
})();