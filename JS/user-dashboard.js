(function () {
    'use strict';

    const todayStr = new Date().toISOString().split('T')[0];

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

        // 2. Accent color
        const ac = LS.get('accentColor'),
            al = LS.get('accentLight');
        if (ac) _applyAccentDOM(ac, al || '#f3e5e6');

        // 3. Compact mode
        if (LS.get('compact') === 'true') {
            const ct = document.getElementById('compactToggle');
            if (ct) ct.checked = true;
            document.documentElement.style.setProperty('--radius', '9px');
        }

        // 4. Font size
        const fs = LS.get('fontSize');
        if (fs && fs !== '100') {
            const fr = document.getElementById('fontSizeRange');
            if (fr) fr.value = fs;
            const lbl = document.getElementById('fontSizeLbl');
            if (lbl) lbl.textContent = fs + '%';
            document.documentElement.style.fontSize = (parseFloat(fs) / 100) + 'rem';
        }

        // 5. Reduce motion
        if (LS.get('reduceMotion') === 'true') {
            const rmt = document.getElementById('reduceMotionToggle');
            if (rmt) rmt.checked = true;
            let s = document.getElementById('reduceMotionStyle');
            if (!s) {
                s = document.createElement('style');
                s.id = 'reduceMotionStyle';
                document.head.appendChild(s);
            }
            s.textContent = '*, *::before, *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; }';
        }

        // 6. Focus ring
        if (LS.get('focusRing') === 'true') {
            const frt = document.getElementById('focusRingToggle');
            if (frt) frt.checked = true;
            let s = document.getElementById('focusRingStyle');
            if (!s) {
                s = document.createElement('style');
                s.id = 'focusRingStyle';
                document.head.appendChild(s);
            }
            s.textContent = '*:focus { outline: 3px solid var(--accent-maroon) !important; outline-offset: 3px !important; }';
        }

        // 7. Account profile fields — now driven by DB on page load, NOT localStorage.
        //    (localStorage profile keys are intentionally skipped here so stale cached
        //     values do not override the fresh server-rendered data in the HTML.)

        // 8. Notification read state
        const readIdxArr = LS.getJ('notifRead');
        if (readIdxArr && readIdxArr.length) {
            const items = document.querySelectorAll('.notif-item');
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

    function showToast(msg) {
        const t = document.getElementById('app-toast');
        if (!t) return;
        t.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg> ' + msg;
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
        document.getElementById('profileDropdown').classList.add('open');
        document.getElementById('avatarBtn').setAttribute('aria-expanded', 'true');
    }

    function closeDropdown() {
        document.getElementById('profileDropdown').classList.remove('open');
        document.getElementById('avatarBtn').setAttribute('aria-expanded', 'false');
    }

    function toggleDropdown() {
        document.getElementById('profileDropdown').classList.contains('open') ? closeDropdown() : openDropdown();
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
    }

    function openOverlay(id) {
        _pushNav({
            type: 'overlay',
            value: id
        });
        _openOverlayDOM(id);
    }

    function closeOverlay(id) {
        // Use history.back() so the browser's forward button also works.
        // We also immediately remove the class for instant visual feedback.
        const el = document.getElementById(id);
        if (el) el.classList.remove('active');
        history.back();
    }

    /* ── Main Tab Switcher ─────────────────────────────────────────────── */
    function _switchTabDOM(tabName) {
        const panel = document.getElementById('panel-' + tabName);
        if (panel) panel.classList.add('active');
        document.querySelectorAll('.nav-tab').forEach(b => b.classList.remove('active'));
        const btn = document.querySelector('.nav-tab[data-tab="' + tabName + '"]');
        if (btn) btn.classList.add('active');
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

    /* ── Account Sub-Tabs ──────────────────────────────────────────────── */
    function switchAccTab(panelId) {
        const panel = document.getElementById(panelId);
        if (panel) panel.classList.add('active');
        document.querySelectorAll('.acc-nav-btn').forEach(b => b.classList.remove('active'));
        const btn = document.querySelector('.acc-nav-btn[data-acc-tab="' + panelId + '"]');
        if (btn) btn.classList.add('active');
        document.querySelectorAll('#accountOverlay .overlay-sub-panel').forEach(p => {
            if (p !== panel) p.classList.remove('active');
        });
    }

    /* ── Settings Sub-Tabs ─────────────────────────────────────────────── */
    function switchSettTab(panelId) {
        const panel = document.getElementById(panelId);
        if (panel) panel.classList.add('active');
        document.querySelectorAll('.s-nav-item').forEach(b => b.classList.remove('active'));
        const btn = document.querySelector('.s-nav-item[data-sett-tab="' + panelId + '"]');
        if (btn) btn.classList.add('active');
        document.querySelectorAll('#settingsOverlay .overlay-sub-panel').forEach(p => {
            if (p !== panel) p.classList.remove('active');
        });
    }

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
        document.querySelectorAll('.notif-item').forEach(item => {
            if (cat === 'all') item.style.display = '';
            else if (cat === 'unread') item.style.display = item.classList.contains('unread') ? '' : 'none';
            else item.style.display = item.dataset.cat === cat ? '' : 'none';
        });
    }

    function markAllRead() {
        const readArr = [];
        document.querySelectorAll('.notif-item').forEach((item, i) => {
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
        document.querySelectorAll('.avatar-btn, .dd-avatar, .acc-avatar-large').forEach(el => {
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
        document.querySelectorAll('.avatar-btn, .dd-avatar, .acc-avatar-large').forEach(el => {
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
                        updateAvatarsToImage(data.profile_picture);
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
            if (_reqCurrentFilter === 'Waiting') return r.status === 'Waiting';
            return r.status === _reqCurrentFilter;
        });

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8"><div class="table-empty"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="36" height="36" style="width:36px;height:36px;display:block;margin:0 auto 8px;opacity:0.7;"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>No requests found for this filter.</div></td></tr>`;
            return;
        }

        tbody.innerHTML = filtered.map(r => {
            const canReturn = r.status === 'Approved' || r.status === 'Overdue';
            const returnBtn = canReturn
                ? `<button class="btn-return-item" data-action="return-item" data-id="${_escHtml(r.id)}" data-name="${_escHtml(r.equipment_name)}" title="Return this item">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" style="width:13px;height:13px;margin-right:4px;vertical-align:middle;"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg>Return
                           </button>`
                : '—';
            const noteCol = r.status === 'Declined' ? `<span style="font-size:0.8rem;color:var(--text-light);">${_escHtml(r.reason)}</span>`
                : r.status === 'Overdue' ? `<span style="font-size:0.8rem;color:#e65100;font-weight:600;">Past due: ${_escHtml(r.return_date)}</span>`
                    : '—';
            return `<tr class="${r.status === 'Overdue' ? 'row-overdue' : ''}">
                        <td><strong>${_escHtml(r.equipment_name)}</strong></td>
                        <td>${_escHtml(r.instructor)}</td>
                        <td>${_escHtml(r.room)}</td>
                        <td>${_escHtml(r.borrow_date)}</td>
                        <td>${_escHtml(r.return_date)}</td>
                        <td>${_statusPill(r.status)}</td>
                        <td>${noteCol}</td>
                        <td>${returnBtn}</td>
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

    function returnItem(reqId, itemName) {
        if (!confirm('Confirm return of "' + itemName + '"? This will update the inventory.')) return;
        const fd = new FormData();
        fd.append('action', 'return_item');
        fd.append('request_id', reqId);
        fetch(window.location.pathname, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Update local data
                    const req = (window.REQUESTS_DATA || []).find(r => String(r.id) === String(reqId));
                    if (req) req.status = 'Returned';
                    renderRequestsTable();
                    showToast(data.msg || 'Item returned successfully!');
                    // Update overdue count display if needed
                    checkOverdueState();
                } else {
                    showToast('Error: ' + (data.msg || 'Could not return item.'));
                }
            })
            .catch(() => showToast('Network error. Please try again.'));
    }

    function checkOverdueState() {
        const overdueCount = (window.REQUESTS_DATA || []).filter(r => r.status === 'Overdue').length;
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
        const instrInp = document.getElementById('instructorField');
        if (!form || !borrowInp || !returnInp) return;

        borrowInp.min = todayStr;
        returnInp.min = todayStr;

        borrowInp.addEventListener('change', function () {
            returnInp.min = this.value;
            if (returnInp.value && returnInp.value < this.value) returnInp.value = this.value;
        });

        if (instrInp) {
            instrInp.addEventListener('input', function () {
                this.value = this.value.replace(/[^a-zA-Z\s.']/g, '');
            });
        }

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
                case 'return-item':
                    returnItem(el.dataset.id, el.dataset.name);
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
                case 'apply-accent':
                    applyAccent(el.dataset.color, el.dataset.light);
                    break;
                case 'reset-settings':
                    resetAllSettings();
                    break;
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
            toggleDropdown();
        });
    }

    /* ── Close dropdown on outside click ─────────────────────────────── */
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.header-right')) closeDropdown();
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

    /* ── Account sub-nav ──────────────────────────────────────────────── */
    document.querySelectorAll('.acc-nav-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            switchAccTab(this.dataset.accTab);
        });
    });

    /* ── Settings sub-nav ─────────────────────────────────────────────── */
    document.querySelectorAll('.s-nav-item').forEach(btn => {
        btn.addEventListener('click', function () {
            switchSettTab(this.dataset.settTab);
        });
    });

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

    /* ── Settings toggles — use 'change' event (reliable, no delegation conflict) */
    const compactToggle = document.getElementById('compactToggle');
    if (compactToggle) compactToggle.addEventListener('change', function () {
        applyCompact(this.checked);
    });

    const fontSizeRange = document.getElementById('fontSizeRange');
    if (fontSizeRange) fontSizeRange.addEventListener('input', function () {
        applyFontSize(this.value);
    });

    const reduceMotionToggle = document.getElementById('reduceMotionToggle');
    if (reduceMotionToggle) reduceMotionToggle.addEventListener('change', function () {
        applyReduceMotion(this.checked);
    });

    const focusRingToggle = document.getElementById('focusRingToggle');
    if (focusRingToggle) focusRingToggle.addEventListener('change', function () {
        applyFocusRing(this.checked);
    });

    /* ── Page Init ────────────────────────────────────────────────────── */
    function initPage() {
        // Restore settings, account edits, and notification state from localStorage
        // (called before URL/slug logic so themes apply before first paint)
        restorePersistedState();

        // URL slug
        const userSlug = '<?php echo $user_slug; ?>';
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
                            const newPicUrl = data.profile_picture + '?t=' + Date.now();
                            document.querySelectorAll('#profileAvatarLarge, .dd-avatar, .avatar-btn').forEach(el => {
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
    ══════════════════════════════════════════════════════════════════ */
    function showToast(message, type = 'info') {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.style.cssText = `
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        background: ${type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--danger)' : 'var(--accent-maroon)'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: var(--radius);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        max-width: 400px;
        animation: slideIn 0.3s ease;
    `;
        toast.textContent = message;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Add animation styles
    if (!document.getElementById('toastAnimations')) {
        const style = document.createElement('style');
        style.id = 'toastAnimations';
        style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
        document.head.appendChild(style);
    }

})();