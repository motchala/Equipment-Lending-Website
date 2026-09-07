(function () {
    'use strict';

    /* ── CSRF — reads the token already embedded in the page (emitted by
       PHP's csrf_field()) for fetch() calls that don't submit a real <form> ── */
    function getCsrfToken() {
        const el = document.querySelector('input[name="csrf_token"]');
        return el ? el.value : '';
    }

    /* ── localStorage helper ─────────────────────────────────── */
    const LS = {
        get: k => { try { return localStorage.getItem('adm_' + k); } catch (e) { return null; } },
        set: (k, v) => { try { localStorage.setItem('adm_' + k, String(v)); } catch (e) { } },
        del: k => { try { localStorage.removeItem('adm_' + k); } catch (e) { } },
        getJ: k => { try { return JSON.parse(localStorage.getItem('adm_' + k) || 'null'); } catch (e) { return null; } },
        setJ: (k, v) => { try { localStorage.setItem('adm_' + k, JSON.stringify(v)); } catch (e) { } }
    };

    /* ── Toast ───────────────────────────────────────────────── */
    let toastTimer;
    function showToast(msg) {
        const t = document.getElementById('app-toast');
        if (!t) return;
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 2800);
    }
    window.showToast = showToast; // exposed globally — inline <script> blocks
    // elsewhere in admin-dashboard.php (which run outside this closure) need
    // to call this too, e.g. to report a failed upload after a redirect.

    /* ── Theme DOM helpers ───────────────────────────────────── */
    function _applyThemeDOM(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.style.removeProperty('--section-tint-start');
        document.documentElement.style.removeProperty('--section-tint-end');
        const map = { light: 'light', dark: 'dark', 'high-contrast': 'hc' };
        ['light', 'dark', 'hc'].forEach(k => {
            const el = document.getElementById('tp-' + k);
            if (el) el.classList.remove('sett-theme-sel');
        });
        const key = map[theme] || theme;
        const el = document.getElementById('tp-' + key);
        if (el) el.classList.add('sett-theme-sel');
    }

    function _applyAccentDOM(color, light) {
        document.querySelectorAll('.c-dot').forEach(d => d.classList.remove('selected'));
        const dot = document.querySelector('.c-dot[data-color="' + color + '"]');
        if (dot) dot.classList.add('selected');
        document.documentElement.style.setProperty('--accent-maroon', color);
        document.documentElement.style.setProperty('--accent-light', light);
        const r = parseInt(color.slice(1, 3), 16), g = parseInt(color.slice(3, 5), 16), b = parseInt(color.slice(5, 7), 16);
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const alpha = isDark ? 0.13 : 0.09;
        document.documentElement.style.setProperty('--section-tint-start', `rgba(${r},${g},${b},${alpha})`);
        document.documentElement.style.setProperty('--section-tint-end', `rgba(${r},${g},${b},0)`);
    }

    /* ── Restore persisted state ─────────────────────────────── */
    function restoreState() {
        const theme = LS.get('theme');
        if (theme && theme !== 'light') _applyThemeDOM(theme);

        const ac = LS.get('accentColor'), al = LS.get('accentLight');
        if (ac) _applyAccentDOM(ac, al || '#f3e5e6');

        const fs = LS.get('fontSize');
        if (fs && fs !== '100') {
            document.documentElement.style.fontSize = (parseFloat(fs) / 100) + 'rem';
        }
        const ts = document.getElementById('textSizeSelect');
        if (ts) ts.value = _pctToTextSize(fs || 100);

        if (LS.get('reduceMotion') === 'true') {
            const rmt = document.getElementById('reduceMotionToggle');
            if (rmt) rmt.checked = true;
            _setReduceMotion(true);
        }

        if (LS.get('focusRing') === 'true') {
            const frt = document.getElementById('focusRingToggle');
            if (frt) frt.checked = true;
            _setFocusRing(true);
        }

        // Notification Preferences — checkboxes default to checked/unchecked
        // in the markup itself, so only override when a stored value exists.
        [['notifPrefRequestsToggle', 'notifPrefRequests'],
        ['notifPrefOverdueToggle', 'notifPrefOverdue'],
        ['notifPrefRoomToggle', 'notifPrefRoom']].forEach(([id, key]) => {
            const el = document.getElementById(id);
            if (!el) return;
            const v = LS.get(key);
            if (v !== null) el.checked = (v === 'true');
        });
        _applyNotifPrefsDOM();

        // Advanced toggles
        const said = document.getElementById('showAssetIdsToggle');
        if (said) { const v = LS.get('showAssetIds'); if (v !== null) said.checked = (v === 'true'); }
        const verr = document.getElementById('verboseErrorsToggle');
        if (verr) { const v = LS.get('verboseErrors'); if (v !== null) verr.checked = (v === 'true'); }

        // Notification read state
        const readArr = LS.getJ('notifRead');
        if (readArr && readArr.length) {
            let unread = 0;
            document.querySelectorAll('.notif-item').forEach((item, i) => {
                if (readArr.includes(i)) {
                    item.classList.remove('unread');
                    const dot = item.querySelector('.unread-dot');
                    if (dot) dot.style.display = 'none';
                } else if (item.classList.contains('unread')) unread++;
            });
            const uc = document.getElementById('unreadCount');
            if (uc) uc.textContent = unread + ' unread';
            document.querySelectorAll('.notif-btn-badge,.notif-badge').forEach(b => {
                if (unread === 0) b.style.display = 'none';
                else { b.style.display = ''; b.textContent = unread; }
            });
        }
    }

    /* ── Tab switcher ────────────────────────────────────────── */
    function _switchTabDOM(tabName, clickedEl) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.nav-item[data-tab]').forEach(b => b.classList.remove('active'));
        const panel = document.getElementById('panel-' + tabName);
        if (panel) panel.classList.add('active');
        // Highlight the specific element that was clicked, or fall back to first match
        const btn = clickedEl || document.querySelector('.nav-item[data-tab="' + tabName + '"]');
        if (btn) btn.classList.add('active');
    }

    /* ── Lending sub-nav ─────────────────────────────────────── */
    function switchLendingSub(subName) {
        document.querySelectorAll('.lending-sub').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.lending-nav-btn').forEach(b => b.classList.remove('active'));
        const sub = document.getElementById('lending-' + subName);
        const btn = document.querySelector('.lending-nav-btn[data-lending-nav="' + subName + '"]');
        if (sub) sub.classList.add('active');
        if (btn) btn.classList.add('active');
    }

    /* openOverlay/closeOverlay removed — Notifications (their last use)
       is now a proper ps-modal (see psOpenModal/psCloseModal below and
       the 'open-notif-modal' case in the click delegation switch). */

    /* ── Edit mode helpers ───────────────────────────────────── */
    function _enterEditMode() {
        const tableCard = document.getElementById('inv-table-card');
        if (tableCard) tableCard.classList.add('hidden');
        const regToggle = document.getElementById('registry-toggle-wrap');
        if (regToggle) regToggle.style.display = 'none';
        // Hide active registry panel so form is the only thing visible
        const regActive = document.getElementById('history-reg-active');
        if (regActive) regActive.classList.remove('active');
        const regArchived = document.getElementById('history-reg-archived');
        if (regArchived) regArchived.classList.remove('active');
        const addBtn = document.querySelector('.btn-add-item');
        if (addBtn) addBtn.style.display = 'none';
        const formWrap = document.getElementById('item-form-wrap');
        if (formWrap) { formWrap.classList.remove('hidden'); formWrap.classList.add('edit-mode'); }
    }

    function _exitEditMode() {
        const tableCard = document.getElementById('inv-table-card');
        if (tableCard) tableCard.classList.remove('hidden');
        const regToggle = document.getElementById('registry-toggle-wrap');
        if (regToggle) regToggle.style.display = '';
        const regActive = document.getElementById('history-reg-active');
        if (regActive) regActive.classList.add('active');
        const addBtn = document.querySelector('.btn-add-item');
        if (addBtn) addBtn.style.display = '';
        const formWrap = document.getElementById('item-form-wrap');
        if (formWrap) { formWrap.classList.remove('edit-mode'); formWrap.classList.add('hidden'); }
    }

    /* ── Profile dropdown ────────────────────────────────────── */
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

    /* ── Account / Help sub-tabs (nested inside the Settings tab) ── */
    /* switchHcTab (Help & FAQ sub-nav) removed — Help & FAQ is now a
       flat view (Common Questions + Contact Support), no nested
       Start/FAQ/Guides/About navigation. */

    /* switchAccTab (My Account sub-nav) removed — the account tab is
       now a single flat view with no nested Overview/Security/Sessions
       navigation. */

    /* ── Settings mega sub-tabs (My Account / Preferences / Borrowing
       Rules / Help & FAQ) — the streamlined consolidation of what used
       to be the Account overlay, Arbitration tab, and Help Center
       overlay. Scoped to #panel-settings so it never touches other
       tabs' sub-panels. ── */
    function switchSettMainTab(panelId) {
        const settPanel = document.getElementById('panel-settings');
        if (!settPanel) return;
        settPanel.querySelectorAll(':scope > .rq-sub-panel').forEach(p => p.classList.remove('active'));
        const tabsWrap = document.getElementById('settMainTabs');
        if (tabsWrap) tabsWrap.querySelectorAll('.rq-sub-tab').forEach(t => t.classList.remove('active'));
        const panel = document.getElementById(panelId);
        if (panel) panel.classList.add('active');
        const btn = tabsWrap && tabsWrap.querySelector('[data-sett-panel="' + panelId + '"]');
        if (btn) btn.classList.add('active');
    }

    /* ── Borrow History toggle ───────────────────────────────── */
    function switchHistoryTab(tabName) {
        document.querySelectorAll('.history-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.history-toggle-btn').forEach(b => b.classList.remove('active'));
        const panel = document.getElementById('history-' + tabName);
        const btn = document.querySelector('.history-toggle-btn[data-history-tab="' + tabName + '"]');
        if (panel) panel.classList.add('active');
        if (btn) btn.classList.add('active');
    }

    /* ── Rooms Registry toggle (scoped to #panel-rooms) ─────────
       Separate from switchHistoryTab to avoid cross-tab interference —
       equipment and rooms live on different tabs so using global
       querySelectorAll('.history-panel') would deactivate the wrong panels. */
    function switchRoomsTab(tabName) {
        const roomsPanel = document.getElementById('panel-rooms');
        if (!roomsPanel) return;
        roomsPanel.querySelectorAll('.rooms-sub-panel').forEach(p => p.classList.remove('active'));
        roomsPanel.querySelectorAll('[data-rooms-tab]').forEach(b => b.classList.remove('active'));
        const activePanel = document.getElementById(tabName + '-panel');
        const activeBtn = roomsPanel.querySelector('[data-rooms-tab="' + tabName + '"]');
        if (activePanel) activePanel.classList.add('active');
        if (activeBtn) activeBtn.classList.add('active');
    }

    function _getUnreadCount() {
        return document.querySelectorAll('.notif-item.unread').length;
    }

    function _updateBadges(count) {
        const uc = document.getElementById('unreadCount');
        if (uc) uc.textContent = count + ' unread';
        document.querySelectorAll('.notif-btn-badge,.notif-badge').forEach(b => {
            if (count === 0) b.style.display = 'none';
            else { b.style.display = ''; b.textContent = count; }
        });
    }

    function _markCardRead(card) {
        if (!card.classList.contains('unread')) return;
        card.classList.remove('unread');
        const dot = card.querySelector('.unread-dot');
        if (dot) dot.style.display = 'none';
        _updateBadges(_getUnreadCount());
    }

    function initNotifCards() {
        document.querySelectorAll('.notif-card').forEach(card => {
            const mainRow = card.querySelector('.notif-card-main');
            if (mainRow) {
                mainRow.addEventListener('click', () => {
                    const isExpanded = card.classList.contains('expanded');
                    // Collapse all others
                    document.querySelectorAll('.notif-card.expanded').forEach(c => c.classList.remove('expanded'));
                    if (!isExpanded) {
                        card.classList.add('expanded');
                        _markCardRead(card);
                    }
                });
                mainRow.addEventListener('keydown', e => {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); mainRow.click(); }
                });
            }
            const dismissBtn = card.querySelector('[data-notif-dismiss]');
            if (dismissBtn) {
                dismissBtn.addEventListener('click', e => {
                    e.stopPropagation();
                    _markCardRead(card);
                    card.classList.remove('expanded');
                    showToast('Notification dismissed.');
                });
            }
        });
    }

    /* switchSettTab (legacy Settings-overlay tab helper) removed —
       the overlay it served no longer exists; Settings navigation
       now goes through switchSettMainTab(). */

    /* ── Notifications ───────────────────────────────────────── */
    function filterNotifs(cat) {
        document.querySelectorAll('.notif-filter-chips .rq-filter-chip').forEach(t => t.classList.remove('active'));
        const btn = document.querySelector('.notif-filter-chips .rq-filter-chip[data-notif-filter="' + cat + '"]');
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
        document.querySelectorAll('.notif-btn-badge,.notif-badge').forEach(b => b.style.display = 'none');
        LS.setJ('notifRead', readArr);
        showToast('All notifications marked as read.');
    }

    /* ── Settings ────────────────────────────────────────────── */
    function applyTheme(theme) { _applyThemeDOM(theme); LS.set('theme', theme); showToast('Theme: ' + theme.charAt(0).toUpperCase() + theme.slice(1)); }
    function applyAccent(color, light) { _applyAccentDOM(color, light); LS.set('accentColor', color); LS.set('accentLight', light); showToast('Accent color updated!'); }
    function applyFontSize(val) {
        const lbl = document.getElementById('fontSizeLbl');
        if (lbl) lbl.textContent = val + '%';
        document.documentElement.style.fontSize = (val / 100) + 'rem';
        LS.set('fontSize', val);
    }
    function _setReduceMotion(on) {
        let s = document.getElementById('reduceMotionStyle');
        if (!s) { s = document.createElement('style'); s.id = 'reduceMotionStyle'; document.head.appendChild(s); }
        // Neutralize both CSS animations AND transitions — most of this site's
        // motion (hover states, tab/panel switches, toasts) is transition-based,
        // so only silencing @keyframes animations (the old behavior) had almost
        // no visible effect.
        s.textContent = on
            ? '*, *::before, *::after { animation-duration: 0.001ms !important; animation-delay: -0.001ms !important; animation-iteration-count: 1 !important; transition-duration: 0.001ms !important; transition-delay: -0.001ms !important; } html { scroll-behavior: auto !important; }'
            : '';
    }
    function applyReduceMotion(on) { _setReduceMotion(on); LS.set('reduceMotion', on); showToast(on ? 'Animations disabled' : 'Animations re-enabled'); }
    function _setFocusRing(on) {
        let s = document.getElementById('focusRingStyle');
        if (!s) { s = document.createElement('style'); s.id = 'focusRingStyle'; document.head.appendChild(s); }
        // The extra rule covers this page's custom toggle switches, whose real
        // <input> is display:none — an outline drawn only on the hidden input
        // would never be visible, so also ring the switch's visible track.
        s.textContent = on
            ? '*:focus { outline: 3px solid var(--accent-maroon) !important; outline-offset: 3px !important; } .toggle-sw input:focus + .toggle-track { outline: 3px solid var(--accent-maroon) !important; outline-offset: 2px !important; }'
            : '';
    }
    function applyFocusRing(on) { _setFocusRing(on); LS.set('focusRing', on); showToast(on ? 'Focus rings enhanced' : 'Focus rings reset'); }

    /* Text Size (Preferences → Accessibility) — maps the Normal/Large/X-Large
       select to the same underlying percentage-based font scaling already
       used elsewhere, so it shares one source of truth (LS 'fontSize'). */
    function _textSizeToPct(size) { return size === 'Large' ? 115 : (size === 'X-Large' ? 130 : 100); }
    function _pctToTextSize(pct) {
        pct = parseFloat(pct);
        if (pct >= 125) return 'X-Large';
        if (pct >= 110) return 'Large';
        return 'Normal';
    }
    function applyTextSize(size) {
        applyFontSize(_textSizeToPct(size));
        showToast('Text size: ' + size);
    }

    /* Notification Preferences (Preferences → Notification Preferences) —
       mutes/unmutes categories of notification directly in the bell overlay,
       via an injected stylesheet so it stays in effect regardless of which
       overlay filter tab (All/Unread/etc.) is active. */
    function _applyNotifPrefsDOM() {
        let s = document.getElementById('notifPrefStyle');
        if (!s) { s = document.createElement('style'); s.id = 'notifPrefStyle'; document.head.appendChild(s); }
        const hiddenCats = [];
        if (LS.get('notifPrefRequests') === 'false') hiddenCats.push('request');
        if (LS.get('notifPrefOverdue') === 'false') hiddenCats.push('overdue');
        // "Room Issues" has no matching notif-item category yet — the
        // preference is saved and ready for when that category is added.
        s.textContent = hiddenCats.length
            ? hiddenCats.map(c => '.notif-item[data-cat="' + c + '"]').join(',') + '{display:none !important;}'
            : '';
    }
    function applyNotifPref(key, on) {
        LS.set(key, on);
        _applyNotifPrefsDOM();
        showToast(on ? 'Notifications enabled.' : 'Notifications muted.');
    }

    /* Advanced (Preferences → Advanced) — persisted browser-level flags.
       Verbose Error Messages intentionally never surfaces raw server/DB
       errors (that was the exact issue the OWASP error-disclosure fix
       addressed); it's kept as a saved preference only. */
    function applyShowAssetIds(on) { LS.set('showAssetIds', on); showToast(on ? 'Asset IDs will be shown where available.' : 'Asset IDs hidden.'); }
    function applyVerboseErrors(on) { LS.set('verboseErrors', on); showToast(on ? 'Verbose error messages enabled.' : 'Verbose error messages disabled.'); }

    function resetAllSettings() {
        applyTheme('light');

        const ts = document.getElementById('textSizeSelect'); if (ts) ts.value = 'Normal';
        applyFontSize(100);

        const rmt = document.getElementById('reduceMotionToggle'); if (rmt) rmt.checked = false;
        applyReduceMotion(false);

        const frt = document.getElementById('focusRingToggle'); if (frt) frt.checked = false;
        applyFocusRing(false);

        const nReq = document.getElementById('notifPrefRequestsToggle'); if (nReq) nReq.checked = true;
        const nOver = document.getElementById('notifPrefOverdueToggle'); if (nOver) nOver.checked = true;
        const nRoom = document.getElementById('notifPrefRoomToggle'); if (nRoom) nRoom.checked = false;
        LS.del('notifPrefRequests'); LS.del('notifPrefOverdue'); LS.del('notifPrefRoom');
        _applyNotifPrefsDOM();

        const said = document.getElementById('showAssetIdsToggle'); if (said) said.checked = true;
        const verr = document.getElementById('verboseErrorsToggle'); if (verr) verr.checked = false;
        LS.del('showAssetIds'); LS.del('verboseErrors');

        applyAccent('#600302', '#f3e5e6');
        ['theme', 'accentColor', 'accentLight', 'fontSize', 'reduceMotion', 'focusRing'].forEach(k => LS.del(k));
        showToast('All settings reset to defaults.');
    }

    /* ── Profile edit ────────────────────────────────────────── */
    function _computeInitials(name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '';
        let ini = parts[0].charAt(0).toUpperCase();
        if (parts.length > 1) ini += parts[parts.length - 1].charAt(0).toUpperCase();
        return ini;
    }

    function toggleProfileEdit() {
        const eb = document.getElementById('editProfileBtn');
        const sb = document.getElementById('saveProfileBtn');
        const cb = document.getElementById('cancelProfileBtn');
        if (eb) eb.style.display = 'none';
        if (sb) sb.style.display = 'flex';
        if (cb) cb.style.display = 'flex';
        document.querySelectorAll('[data-field]').forEach(span => {
            const key = span.dataset.field;
            const input = document.querySelector('[data-input="' + key + '"]');
            if (!input) return;
            span.style.display = 'none'; input.style.display = ''; input.disabled = false;
            if (span.classList.contains('empty')) input.value = '';
        });
    }

    function cancelProfileEdit() {
        const eb = document.getElementById('editProfileBtn');
        const sb = document.getElementById('saveProfileBtn');
        const cb = document.getElementById('cancelProfileBtn');
        if (eb) eb.style.display = 'flex';
        if (sb) sb.style.display = 'none';
        if (cb) cb.style.display = 'none';
        document.querySelectorAll('[data-input]').forEach(input => {
            const span = document.querySelector('[data-field="' + input.dataset.input + '"]');
            if (!span) return;
            span.style.display = ''; input.style.display = 'none'; input.disabled = true;
        });
    }

    function saveProfileEdit() {
        const nameInput = document.querySelector('[data-input="admin_name"]');
        const emailInput = document.querySelector('[data-input="admin_email"]');
        const saveBtn = document.getElementById('saveProfileBtn');

        const name = nameInput ? nameInput.value.trim() : '';
        const email = emailInput ? emailInput.value.trim() : '';

        if (!name) { showToast('Display name cannot be empty.'); return; }
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showToast('Please enter a valid email address.'); return; }

        if (saveBtn) saveBtn.disabled = true;

        const fd = new FormData();
        fd.append('ajax_action', 'update_profile');
        fd.append('admin_name', name);
        fd.append('admin_email', email);
        fd.append('csrf_token', getCsrfToken());

        fetch('admin-dashboard.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const nameSpan = document.querySelector('[data-field="admin_name"]');
                    if (nameSpan) { nameSpan.textContent = data.admin_name; nameSpan.classList.remove('empty'); }
                    const emailSpan = document.querySelector('[data-field="admin_email"]');
                    if (emailSpan) { emailSpan.textContent = data.admin_email; emailSpan.classList.remove('empty'); }

                    // Reflect the change everywhere else the admin's name/initials
                    // appear on this page (header, dropdown, hero, greeting) without
                    // requiring a reload.
                    document.querySelectorAll('.u-name, .dd-name, .ov-hero-name').forEach(el => { el.textContent = data.admin_name; });
                    const greetEl = document.getElementById('greetName');
                    if (greetEl) greetEl.textContent = (data.admin_name || '').split(' ')[0] || greetEl.textContent;
                    const initials = _computeInitials(data.admin_name);
                    if (initials) {
                        const avatarBtn = document.getElementById('avatarBtn');
                        if (avatarBtn) avatarBtn.textContent = initials;
                        document.querySelectorAll('.dd-avatar').forEach(el => { el.textContent = initials; });
                    }

                    // The database is now the source of truth for these fields —
                    // drop any stale locally-cached copy from before this fix.
                    LS.del('prof_admin_name');
                    LS.del('prof_admin_email');

                    cancelProfileEdit();
                    showToast('Profile updated successfully!');
                } else {
                    showToast(data.message || 'Could not save changes. Please try again.');
                }
            })
            .catch(() => showToast('Network error — please check your connection and try again.'))
            .finally(() => { if (saveBtn) saveBtn.disabled = false; });
    }

    /* ── Image upload handlers ───────────────────────────────── */
    function initImageUpload(ids) {
        ids = ids || {};
        const dropZone = document.getElementById(ids.dropZone || 'dropZone');
        const fileInput = document.getElementById(ids.fileInput || 'itemImageInput');
        const preview = document.getElementById(ids.preview || 'imagePreview');
        const removeBtn = document.getElementById(ids.removeBtn || 'removeImageBtn');
        // Optional — only present on the Edit modal, where there's a saved
        // image on the server that "Remove" needs to actually clear.
        const removeFlag = ids.removeFlag ? document.getElementById(ids.removeFlag) : null;

        function handleFile(file) {
            if (file.type !== 'image/jpeg' && file.type !== 'image/png') {
                showToast('Only JPG and PNG images are allowed.');
                return;
            }
            const reader = new FileReader();
            reader.onload = e => { if (preview) { preview.src = e.target.result; preview.style.display = 'block'; } };
            reader.readAsDataURL(file);
            if (fileInput) { const dt = new DataTransfer(); dt.items.add(file); fileInput.files = dt.files; }
            if (removeBtn) removeBtn.classList.remove('hidden');
            if (removeFlag) removeFlag.value = '0'; // picking a new file cancels any pending "remove"
        }

        if (dropZone) {
            dropZone.addEventListener('click', () => fileInput && fileInput.click());
            ['dragenter', 'dragover'].forEach(ev => dropZone.addEventListener(ev, e => { e.preventDefault(); dropZone.style.borderColor = 'var(--accent-maroon)'; }));
            ['dragleave', 'drop'].forEach(ev => dropZone.addEventListener(ev, e => { e.preventDefault(); dropZone.style.borderColor = ''; }));
            dropZone.addEventListener('drop', e => { const f = e.dataTransfer.files[0]; if (f) handleFile(f); });
        }
        if (fileInput) fileInput.addEventListener('change', () => { if (fileInput.files[0]) handleFile(fileInput.files[0]); });
        document.addEventListener('paste', e => {
            if (!dropZone || dropZone.offsetParent === null) return; // ignore paste unless this zone is visible
            const item = [...(e.clipboardData?.items || [])].find(i => i.type.startsWith('image'));
            if (item) handleFile(item.getAsFile());
        });
        if (removeBtn) {
            removeBtn.addEventListener('click', e => {
                e.stopPropagation();
                if (preview) { preview.src = ''; preview.style.display = 'none'; }
                if (fileInput) fileInput.value = '';
                removeBtn.classList.add('hidden');
                // Tell the backend the saved image should actually be
                // cleared (reverted to default) — just hiding the preview
                // client-side never reached the server before.
                if (removeFlag) removeFlag.value = '1';
            });
        }
    }

    /* ── Live Search ─────────────────────────────────────────── */
    function setupLiveSearch(inputId, tbodyId, section) {
        const input = document.getElementById(inputId);
        const tbody = document.getElementById(tbodyId);
        if (!input || !tbody) return;
        input.addEventListener('keyup', function () {
            const q = this.value.trim();
            fetch(`equipment-booking/api/live-search.php?q=${encodeURIComponent(q)}&section=${section}`)
                .then(r => r.text())
                .then(data => { tbody.innerHTML = data; })
                .catch(() => { tbody.innerHTML = "<tr><td colspan='10' class='text-muted' style='text-align:center;padding:1.5rem;'>Error fetching data.</td></tr>"; });
        });
    }

    /* ── Equipment search (client-side) ──────────────────────────
       NOTE: this intentionally does NOT use setupLiveSearch(). That
       helper replaces the container's innerHTML with whatever
       live-search.php's ?section=inventory case returns — old
       Bootstrap <tr>/<td> markup, meant for a <table>. #inventory-body
       is a plain <div> of .inv-row-item cards, not a table, so that
       swap produced invalid/garbled HTML (and it stayed garbled after
       clearing the search, since an empty query still replaces the
       real card markup with the old table-row markup). Filtering the
       cards that are already in the DOM avoids all of that — nothing
       is ever replaced, so there's nothing to garble. ── */
    function setupInventorySearch() {
        const input = document.getElementById('inventorySearch');
        const body = document.getElementById('inventory-body');
        if (!input || !body) return;
        input.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            body.querySelectorAll('.inv-row-item').forEach(function (row) {
                const name = (row.dataset.itemName || '').toLowerCase();
                const cat = (row.dataset.itemCategory || '').toLowerCase();
                row.style.display = (!q || name.includes(q) || cat.includes(q)) ? '' : 'none';
            });
        });
    }

    /* ── Master event delegation ─────────────────────────────── */
    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-action]');
        if (!el) return;
        const action = el.dataset.action;
        try {
            switch (action) {
                case 'open-notif-modal':
                    filterNotifs('all');
                    psOpenModal('notifOverlay');
                    closeDropdown();
                    break;

                case 'open-change-pass': {
                    const modal = document.getElementById('changePassModal');
                    if (modal) {
                        modal.style.display = 'flex';
                        const form = document.getElementById('changePasswordForm');
                        form.reset();
                        // Privacy: don't leave a password visible from a previous open.
                        form.querySelectorAll('input.form-control-custom').forEach(inp => { inp.type = 'password'; });
                        form.querySelectorAll('.fac-pw-toggle').forEach(btn => {
                            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:17px">visibility</span>';
                        });
                        document.getElementById('cp-alert').style.display = 'none';
                    }
                    break;
                }
                case 'close-change-pass': {
                    const modal = document.getElementById('changePassModal');
                    if (modal) modal.style.display = 'none';
                    break;
                }

                case 'dismiss-alert': {
                    const t = document.getElementById(el.dataset.target);
                    if (t) t.style.display = 'none'; break;
                }
                case 'hc-go': {
                    const hcTab = el.dataset.tab;
                    if (hcTab) {
                        _switchTabDOM(hcTab, true);
                    }
                    break;
                }
                case 'go-lending': {
                    // NOTE: this used to jump to a standalone "Lending" panel
                    // (Borrow Requests / Equipment Registry / Raw Data /
                    // Arbitration Log) that's since been removed — Requests
                    // and Inventory are now their own real top-level tabs.
                    const dest = el.dataset.lending || 'waiting';
                    if (dest === 'inventory') {
                        _switchTabDOM('inventory');
                        // Open the Add Equipment form directly, matching a
                        // click on the real "+ Add Equipment" button.
                        const fw = document.getElementById('item-form-wrap');
                        if (fw && fw.classList.contains('hidden')) {
                            const regToggle = document.getElementById('registry-toggle-wrap');
                            const regActive = document.getElementById('history-reg-active');
                            const regArchived = document.getElementById('history-reg-archived');
                            const addBtn = document.querySelector('.btn-add-item');
                            fw.classList.remove('hidden');
                            if (regToggle) regToggle.style.display = 'none';
                            if (regActive) regActive.classList.remove('active');
                            if (regArchived) regArchived.classList.remove('active');
                            if (addBtn) addBtn.style.display = 'none';
                        }
                        if (fw) fw.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } else {
                        // "waiting" (Review Requests / View All) — go to the
                        // Requests tab and make sure the waiting-for-approval
                        // filter is the one showing.
                        _switchTabDOM('requests');
                        const chip = document.querySelector('#rqTabs .rq-filter-chip[data-rq-panel="rq-waiting"]');
                        if (chip) chip.click();
                    }
                    break;
                }
                case 'go-rooms':
                    _switchTabDOM('rooms');
                    break;
                case 'go-faculty':
                    _switchTabDOM('faculty');
                    break;
                case 'show-add-form': {
                    const fw = document.getElementById('item-form-wrap');
                    const regToggle = document.getElementById('registry-toggle-wrap');
                    const regActive = document.getElementById('history-reg-active');
                    const regArchived = document.getElementById('history-reg-archived');
                    const addBtn = document.querySelector('.btn-add-item');
                    if (fw) {
                        const isHidden = fw.classList.contains('hidden');
                        fw.classList.toggle('hidden');
                        if (isHidden) {
                            // Showing form — hide table elements
                            if (regToggle) regToggle.style.display = 'none';
                            if (regActive) regActive.classList.remove('active');
                            if (regArchived) regArchived.classList.remove('active');
                            if (addBtn) addBtn.style.display = 'none';
                        } else {
                            // Hiding form — restore table elements
                            if (regToggle) regToggle.style.display = '';
                            if (regActive) regActive.classList.add('active');
                            if (addBtn) addBtn.style.display = '';
                        }
                    }
                    break;
                }
                case 'hide-item-form': {
                    const editParam = new URLSearchParams(window.location.search).get('edit_item');
                    if (editParam) {
                        window.location.href = 'admin-dashboard.php?view=inventory';
                    } else {
                        const fw = document.getElementById('item-form-wrap');
                        if (fw) fw.classList.add('hidden');
                    }
                    break;
                }
                case 'show-room-form': {
                    const rfw = document.getElementById('room-form-wrap');
                    const addRoomBtn = document.getElementById('addRoomBtn');
                    if (rfw) {
                        rfw.classList.remove('hidden');
                        if (addRoomBtn) addRoomBtn.style.display = 'none';
                        rfw.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                    break;
                }
                case 'hide-room-form': {
                    const editRoomParam = new URLSearchParams(window.location.search).get('edit_room');
                    if (editRoomParam) {
                        window.location.href = 'admin-dashboard.php?tab=rooms';
                    } else {
                        const rfw = document.getElementById('room-form-wrap');
                        const addRoomBtn = document.getElementById('addRoomBtn');
                        if (rfw) rfw.classList.add('hidden');
                        if (addRoomBtn) addRoomBtn.style.display = '';
                    }
                    break;
                }
                case 'apply-theme':
                    applyTheme(el.dataset.theme); break;
                case 'apply-accent':
                    applyAccent(el.dataset.color, el.dataset.light); break;
                case 'reset-settings':
                    resetAllSettings(); break;
                case 'profile-edit':
                    toggleProfileEdit(); break;
                case 'profile-save':
                    saveProfileEdit(); break;
                case 'profile-cancel':
                    cancelProfileEdit(); break;
                case 'mark-all-read':
                    markAllRead(); break;
                case 'toast':
                    showToast(el.dataset.msg || ''); break;
                case 'logout':
                    closeDropdown();
                    if (confirm('Confirm Logout?')) window.location.href = 'api/logout.php';
                    break;

                /* \u2500\u2500 ps-modal \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
                case 'ps-open-modal':
                    psOpenModal(el.dataset.modal);
                    break;
                case 'ps-close-modal':
                    psCloseModal(el.dataset.modal);
                    break;
                case 'ps-switch-modal':
                    psCloseModal(el.dataset.close);
                    psOpenModal(el.dataset.open);
                    break;
            }
        } catch (err) { console.warn('Action "' + action + '" failed:', err); }
    });

    /* ── Faculty: adviser toggle show/hide ───────────────────── */
    const facAdviserChk = document.getElementById('fac-adviser');
    const facOrgGroup = document.getElementById('fac-org-group');
    const facOrgSelect = document.getElementById('fac-org');

    function _syncAdviserToggle() {
        if (!facAdviserChk || !facOrgGroup) return;
        const on = facAdviserChk.checked;
        facOrgGroup.style.display = on ? '' : 'none';
        if (facOrgSelect) {
            if (on) {
                facOrgSelect.setAttribute('required', 'required');
            } else {
                facOrgSelect.removeAttribute('required');
                facOrgSelect.value = '';
            }
        }
    }

    if (facAdviserChk) {
        facAdviserChk.addEventListener('change', _syncAdviserToggle);
        _syncAdviserToggle(); // run once on page load to sync initial state
    }

    /* ── Faculty: XSS helper and row builder ─────────────────── */
    function _esc(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    function _buildFacultyRow(data) {
        // data = { fullname, email, role, org_name, faculty_id, allow_org_borrowing }
        const tr = document.createElement('tr');
        const isAdviser = data.role === 'Organization Adviser';
        const subLabel = isAdviser && data.org_name
            ? 'Org Adviser \u00B7 ' + data.org_name
            : 'Active Faculty';
        const init = (data.fullname || 'F').charAt(0).toUpperCase();

        tr.dataset.fullname = data.fullname || '';
        tr.dataset.email = data.email || '';
        tr.dataset.facultyId = data.faculty_id || '';
        tr.dataset.role = data.role || '';
        tr.dataset.org = data.org_name || '';
        tr.dataset.aob = data.allow_org_borrowing === 1 ? '1' : '0';
        tr.dataset.init = init;

        tr.innerHTML =
            '<td>' +
            '<div style="font-weight:600">' + _esc(data.fullname) + '</div>' +
            '<div style="font-size:11px;color:var(--text-light)">' + _esc(subLabel) + '</div>' +
            '</td>' +
            '<td style="font-size:12px;color:var(--text-light)">' + _esc(data.faculty_id || '') + '</td>' +
            '<td style="font-size:12px">' + _esc(data.email) + '</td>' +
            '<td><label class="faculty-toggle-label">' +
            '<input type="checkbox" class="faculty-toggle-input org-borrowing-toggle"' +
            ' data-faculty-id="' + _esc(data.faculty_id || '') + '"' +
            (data.allow_org_borrowing === 1 ? ' checked' : '') +
            '><span class="faculty-toggle-track"></span></label></td>' +
            '<td><button class="ps-btn ps-btn--ghost ps-btn--sm fac-edit-btn">' +
            '<span class="material-symbols-outlined">edit</span></button></td>';
        return tr;
    }

    /* ── Faculty: create-account submit ──────────────────────── */
    const facSubmitBtn = document.getElementById('fac-submit-btn');
    const facFormAlert = document.getElementById('fac-form-alert');

    function _showFacAlert(msg, isError) {
        if (!facFormAlert) return;
        facFormAlert.className = 'alert-banner ' +
            (isError ? 'alert-danger' : 'alert-success');
        facFormAlert.textContent = msg;
        facFormAlert.classList.remove('hidden');
    }

    function _clearFacAlert() {
        if (!facFormAlert) return;
        facFormAlert.classList.add('hidden');
        facFormAlert.textContent = '';
    }

    if (facSubmitBtn) {
        facSubmitBtn.addEventListener('click', function () {
            _clearFacAlert();

            const email = (document.getElementById('fac-email')?.value || '').trim();
            const backup = (document.getElementById('fac-backup')?.value || '').trim();
            const firstName = (document.getElementById('fac-first')?.value || '').trim();
            const middleName = (document.getElementById('fac-middle')?.value || '').trim();
            const lastName = (document.getElementById('fac-last')?.value || '').trim();
            const password = document.getElementById('fac-password')?.value || '';
            const confirm = document.getElementById('fac-confirm')?.value || '';
            const isAdviser = facAdviserChk?.checked ? '1' : '0';
            const orgId = facOrgSelect?.value || '';

            // Client-side pre-checks (mirror server validation for immediate UX feedback)
            if (!email) {
                _showFacAlert('PUPSync email is required.', true); return;
            }
            if (!firstName) {
                _showFacAlert('First name is required.', true); return;
            }
            if (!lastName) {
                _showFacAlert('Last name is required.', true); return;
            }
            if (!password) {
                _showFacAlert('Password is required.', true); return;
            }
            if (password.length < 8) {
                _showFacAlert('Password must be at least 8 characters.', true); return;
            }
            if (password !== confirm) {
                _showFacAlert('Passwords do not match.', true); return;
            }
            if (isAdviser === '1' && !orgId) {
                _showFacAlert('An organization must be selected for an adviser.', true);
                return;
            }

            facSubmitBtn.disabled = true;
            facSubmitBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="vertical-align:middle;margin-right:6px;animation:spin 0.8s linear infinite;"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg> Creating...';

            const body = new URLSearchParams({
                csrf_token: getCsrfToken(),
                pupsync_email: email,
                backup_email: backup,
                first_name: firstName,
                middle_name: middleName,
                last_name: lastName,
                password: password,
                confirm_password: confirm,
                is_org_adviser: isAdviser,
                organization_id: orgId
            });

            fetch('equipment-booking/api/create-faculty-account.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        _showFacAlert(
                            'Account created. Faculty ID: ' + data.faculty_id, false
                        );
                        showToast('Faculty account created successfully.');

                        // Build fullname for the new DOM row
                        const parts = [firstName, middleName, lastName].filter(Boolean);
                        const fullname = parts.join(' ');
                        const role = isAdviser === '1'
                            ? 'Organization Adviser'
                            : 'Regular Faculty';
                        const orgName = isAdviser === '1'
                            ? (facOrgSelect?.options[facOrgSelect.selectedIndex]?.text || '')
                            : '';

                        // DOM prepend: remove empty-state row if present, then prepend new row
                        const emptyRow = document.getElementById('fac-empty-row');
                        if (emptyRow) emptyRow.remove();

                        const tbody = document.getElementById('faculty-list-tbody');
                        if (tbody) {
                            const newRow = _buildFacultyRow({
                                fullname, email, role,
                                org_name: orgName,
                                faculty_id: data.faculty_id || '',
                                allow_org_borrowing: 0
                            });
                            tbody.prepend(newRow);
                        }

                        // Reset form
                        ['fac-email', 'fac-backup', 'fac-first', 'fac-middle', 'fac-last', 'fac-password', 'fac-confirm']
                            .forEach(id => {
                                const el = document.getElementById(id);
                                if (el) el.value = '';
                            });
                        if (facAdviserChk) facAdviserChk.checked = false;
                        _syncAdviserToggle();

                    } else {
                        _showFacAlert(data.message || 'An error occurred.', true);
                    }
                })
                .catch(() => {
                    _showFacAlert('Network error. Please try again.', true);
                })
                .finally(() => {
                    facSubmitBtn.disabled = false;
                    facSubmitBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" style="vertical-align:middle;margin-right:6px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg> Create Account';
                });
        });
    }

    /* ── Faculty: password show/hide toggles ────────────────── */
    /* Also powers Settings → Change Password. data-target may list more
       than one input id, comma-separated, so one button can reveal several
       linked fields at once (New Password + Confirm New Password share a
       single eye state); Current Password keeps its own independent target. */
    document.querySelectorAll('.fac-pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetIds = (this.dataset.target || '').split(',').map(s => s.trim()).filter(Boolean);
            if (!targetIds.length) return;
            const firstInput = document.getElementById(targetIds[0]);
            if (!firstInput) return;
            const isHidden = firstInput.type === 'password';
            const newType = isHidden ? 'text' : 'password';
            targetIds.forEach(function (id) {
                const inp = document.getElementById(id);
                if (inp) inp.type = newType;
            });

            const eyeOffSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
            const eyeSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
            const newIcon = isHidden ? eyeOffSvg : eyeSvg;

            // Keep this button's icon, and any other toggle button that shares
            // at least one of the same target ids, in sync with the new state.
            document.querySelectorAll('.fac-pw-toggle').forEach(function (otherBtn) {
                const otherIds = (otherBtn.dataset.target || '').split(',').map(s => s.trim()).filter(Boolean);
                const shared = otherIds.some(function (id) { return targetIds.includes(id); });
                if (shared) otherBtn.innerHTML = newIcon;
            });
        });
    });

    /* ── Avatar button ───────────────────────────────────────── */
    const avatarBtn = document.getElementById('avatarBtn');
    if (avatarBtn) avatarBtn.addEventListener('click', e => { e.stopPropagation(); toggleDropdown(); });
    document.addEventListener('click', e => { if (!e.target.closest('.header-right')) closeDropdown(); });

    /* ── Sidebar nav items ────────────────────────────────────── */
    document.querySelectorAll('.nav-item[data-tab]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            _switchTabDOM(this.dataset.tab, this);
        });
    });

    /* ── Lending sub-nav ─────────────────────────────────────── */
    document.querySelectorAll('.lending-nav-btn').forEach(btn => {
        btn.addEventListener('click', function () { switchLendingSub(this.dataset.lendingNav); });
    });

    /* ── Borrow History toggle ───────────────────────────────── */
    document.querySelectorAll('.history-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const tabName = this.dataset.historyTab;
            if (tabName === 'reg-archived') {
                // Single standalone button (not part of a mutually-exclusive
                // pair like Pending/Return or Approved/Declined) — so unlike
                // switchHistoryTab, this one should collapse on a second click.
                const panel = document.getElementById('history-' + tabName);
                const isOpen = this.classList.contains('active');
                if (isOpen) {
                    this.classList.remove('active');
                    if (panel) panel.classList.remove('active');
                } else {
                    this.classList.add('active');
                    if (panel) panel.classList.add('active');
                }
            } else {
                switchHistoryTab(tabName);
            }
        });
    });

    /* ── Rooms Registry toggle ───────────────────────────────── */
    document.querySelectorAll('[data-rooms-tab]').forEach(btn => {
        btn.addEventListener('click', function () { switchRoomsTab(this.dataset.roomsTab); });
    });

    /* ── Settings mega sub-tabs (My Account / Preferences /
       Borrowing Rules / Help & FAQ) ─────────────────────────── */
    document.querySelectorAll('#settMainTabs .rq-sub-tab').forEach(btn => {
        btn.addEventListener('click', function () { switchSettMainTab(this.dataset.settPanel); });
    });

    /* ── Header dropdown → Settings tab shortcuts ────────────── */
    const ddAccountBtn = document.getElementById('dd-account-btn');
    if (ddAccountBtn) {
        ddAccountBtn.addEventListener('click', function () {
            closeDropdown();
            _switchTabDOM('settings');
            switchSettMainTab('sett-account');
        });
    }
    const ddSettingsBtn = document.getElementById('dd-settings-btn');
    if (ddSettingsBtn) {
        ddSettingsBtn.addEventListener('click', function () {
            closeDropdown();
            _switchTabDOM('settings');
            switchSettMainTab('sett-prefs');
        });
    }

    /* ── Admin Cancel Reservation Modal ─────────────────────── */
    function openAdminCancelRrModal(rrId, roomName, facultyName) {
        const modal = document.getElementById('adminCancelRrModal');
        const desc = document.getElementById('adminCancelRrDesc');
        const alertEl = document.getElementById('admin-cancel-rr-alert');
        document.getElementById('adminCancelRrId').value = rrId;
        alertEl.style.display = 'none';
        desc.textContent = 'Cancel reservation for ' + facultyName + ' in ' + roomName + '?';
        modal.style.display = 'flex';
    }

    function closeAdminCancelRrModal() {
        const modal = document.getElementById('adminCancelRrModal');
        if (modal) modal.style.display = 'none';
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-action="admin-cancel-reservation"]');
        if (!btn) return;
        openAdminCancelRrModal(
            btn.dataset.rrId,
            btn.dataset.roomName,
            btn.dataset.facultyName
        );
    });

    const closeAdminCancelRrBtn = document.getElementById('closeAdminCancelRrModal');
    if (closeAdminCancelRrBtn) closeAdminCancelRrBtn.addEventListener('click', closeAdminCancelRrModal);
    const cancelAdminCancelRrBtn = document.getElementById('cancelAdminCancelRrBtn');
    if (cancelAdminCancelRrBtn) cancelAdminCancelRrBtn.addEventListener('click', closeAdminCancelRrModal);
    const adminCancelRrBackdrop = document.getElementById('adminCancelRrBackdrop');
    if (adminCancelRrBackdrop) adminCancelRrBackdrop.addEventListener('click', closeAdminCancelRrModal);

    const submitAdminCancelRrBtn = document.getElementById('submitAdminCancelRrBtn');
    if (submitAdminCancelRrBtn) {
        submitAdminCancelRrBtn.addEventListener('click', function () {
            const rrId = document.getElementById('adminCancelRrId').value;
            const alertEl = document.getElementById('admin-cancel-rr-alert');
            this.disabled = true;
            this.textContent = 'Cancelling…';

            fetch('room-reservation/api/cancel-reservation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    reservation_id: parseInt(rrId, 10),
                    csrf_token: getCsrfToken(),
                }),
            })
                .then(r => r.json())
                .then(data => {
                    this.disabled = false;
                    this.textContent = 'Confirm Cancellation';
                    if (data.error) {
                        alertEl.style.display = 'block';
                        alertEl.style.background = '#ffeaea';
                        alertEl.style.color = 'var(--danger)';
                        alertEl.textContent = data.error;
                        return;
                    }
                    closeAdminCancelRrModal();
                    showToast('Reservation cancelled. Faculty and waitlist notified.');
                    setTimeout(() => location.reload(), 1200);
                })
                .catch(() => {
                    this.disabled = false;
                    this.textContent = 'Confirm Cancellation';
                    alertEl.style.display = 'block';
                    alertEl.style.background = '#ffeaea';
                    alertEl.style.color = 'var(--danger)';
                    alertEl.textContent = 'Network error. Please try again.';
                });
        });
    }

    /* ── Issue Review Modal ─────────────────────────────────── */
    function openIssueReviewModal(issueId, roomName, reporter, description) {
        const modal = document.getElementById('issueReviewModal');
        const desc = document.getElementById('issueReviewDesc');
        const alertEl = document.getElementById('issue-review-alert');
        document.getElementById('issueReviewId').value = issueId;
        document.getElementById('issueAdminNotes').value = '';
        document.getElementById('issueSetMaintenance').checked = false;
        alertEl.style.display = 'none';
        desc.innerHTML = '<strong>' + _escAdm(roomName) + '</strong> — reported by '
            + _escAdm(reporter) + '<br>'
            + '<span style="font-size:.82rem;color:var(--text-light);">'
            + _escAdm(description) + '</span>';
        modal.style.display = 'flex';
    }

    function closeIssueReviewModal() {
        const modal = document.getElementById('issueReviewModal');
        if (modal) modal.style.display = 'none';
    }

    function _escAdm(str) {
        return String(str || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function _submitIssueResolution(resolution) {
        const issueId = document.getElementById('issueReviewId').value;
        const adminNotes = document.getElementById('issueAdminNotes').value.trim();
        const setMaint = document.getElementById('issueSetMaintenance').checked;
        const alertEl = document.getElementById('issue-review-alert');

        fetch('room-reservation/api/resolve-room-issue.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                issue_id: parseInt(issueId, 10),
                resolution: resolution,
                admin_notes: adminNotes,
                set_maintenance: setMaint,
                csrf_token: getCsrfToken(),
            }),
        })
            .then(r => r.json())
            .then(data => {
                document.getElementById('resolveIssueBtn').disabled = false;
                document.getElementById('resolveIssueBtn').textContent = 'Mark Resolved';
                document.getElementById('dismissIssueBtn').disabled = false;
                document.getElementById('dismissIssueBtn').textContent = 'Dismiss';
                if (data.error) {
                    alertEl.style.display = 'block';
                    alertEl.style.background = '#ffeaea';
                    alertEl.style.color = 'var(--danger)';
                    alertEl.textContent = data.error;
                    return;
                }
                closeIssueReviewModal();
                showToast('Issue report ' + resolution.toLowerCase() + '.');
                setTimeout(() => location.reload(), 1200);
            })
            .catch(() => {
                document.getElementById('resolveIssueBtn').disabled = false;
                document.getElementById('resolveIssueBtn').textContent = 'Mark Resolved';
                document.getElementById('dismissIssueBtn').disabled = false;
                document.getElementById('dismissIssueBtn').textContent = 'Dismiss';
                alertEl.style.display = 'block';
                alertEl.style.background = '#ffeaea';
                alertEl.style.color = 'var(--danger)';
                alertEl.textContent = 'Network error. Please try again.';
            });
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-action="open-issue-review"]');
        if (!btn) return;
        openIssueReviewModal(
            btn.dataset.issueId,
            btn.dataset.roomName,
            btn.dataset.reporter,
            btn.dataset.description
        );
    });

    const closeIssueReviewBtn = document.getElementById('closeIssueReviewModal');
    if (closeIssueReviewBtn) closeIssueReviewBtn.addEventListener('click', closeIssueReviewModal);
    const cancelIssueReviewBtn = document.getElementById('cancelIssueReviewBtn');
    if (cancelIssueReviewBtn) cancelIssueReviewBtn.addEventListener('click', closeIssueReviewModal);
    const issueReviewBackdrop = document.getElementById('issueReviewModalBackdrop');
    if (issueReviewBackdrop) issueReviewBackdrop.addEventListener('click', closeIssueReviewModal);

    const resolveIssueBtn = document.getElementById('resolveIssueBtn');
    if (resolveIssueBtn) {
        resolveIssueBtn.addEventListener('click', function () {
            this.disabled = true; this.textContent = 'Resolving…';
            document.getElementById('dismissIssueBtn').disabled = true;
            _submitIssueResolution('Resolved');
        });
    }
    const dismissIssueBtn = document.getElementById('dismissIssueBtn');
    if (dismissIssueBtn) {
        dismissIssueBtn.addEventListener('click', function () {
            this.disabled = true; this.textContent = 'Dismissing…';
            document.getElementById('resolveIssueBtn').disabled = true;
            _submitIssueResolution('Dismissed');
        });
    }

    /* ── Room form field-tip icons: toggle on click/tap ────────
       Hover is handled by pure CSS (:hover). This JS layer adds
       click/tap toggling for older users who can't hover precisely.
       One delegated listener on #room-form-wrap keeps it scoped.   */
    (function () {
        const wrap = document.getElementById('room-form-wrap');
        if (!wrap) return;

        wrap.addEventListener('click', function (e) {
            const tip = e.target.closest('.field-tip');

            if (tip) {
                e.stopPropagation();
                const isOpen = tip.classList.contains('tip-open');
                /* Close any other open tips first */
                wrap.querySelectorAll('.field-tip.tip-open').forEach(t => t.classList.remove('tip-open'));
                if (!isOpen) tip.classList.add('tip-open');
                return;
            }

            /* Click anywhere outside a tip closes all open tips */
            wrap.querySelectorAll('.field-tip.tip-open').forEach(t => t.classList.remove('tip-open'));
        });

        /* Keyboard support: Enter/Space toggles the tip; Escape closes all */
        wrap.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                const tip = e.target.closest('.field-tip');
                if (tip) {
                    e.preventDefault();
                    const isOpen = tip.classList.contains('tip-open');
                    wrap.querySelectorAll('.field-tip.tip-open').forEach(t => t.classList.remove('tip-open'));
                    if (!isOpen) tip.classList.add('tip-open');
                }
            }
            if (e.key === 'Escape') {
                wrap.querySelectorAll('.field-tip.tip-open').forEach(t => t.classList.remove('tip-open'));
            }
        });

        /* Close tips when clicking outside the form entirely */
        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) {
                wrap.querySelectorAll('.field-tip.tip-open').forEach(t => t.classList.remove('tip-open'));
            }
        });
    }());

    /* Account sub-nav (.acc-nav-btn) removed — My Account is now a
       single flat view. */

    /* Help Center sub-nav (.hc-nav-btn) removed — Help & FAQ is now a
       single flat view. */

    /* Legacy Settings-overlay sub-nav (.s-nav-item) removed with
       switchSettTab — the overlay no longer exists. */

    /* ── Notification filter chips ───────────────────────────── */
    document.querySelectorAll('.notif-filter-chips .rq-filter-chip').forEach(btn => {
        btn.addEventListener('click', function () { filterNotifs(this.dataset.notifFilter); });
    });

    /* ── Settings toggles ────────────────────────────────────── */
    const ts = document.getElementById('textSizeSelect'); if (ts) ts.addEventListener('change', function () { applyTextSize(this.value); });
    const rmt = document.getElementById('reduceMotionToggle'); if (rmt) rmt.addEventListener('change', function () { applyReduceMotion(this.checked); });
    const frt = document.getElementById('focusRingToggle'); if (frt) frt.addEventListener('change', function () { applyFocusRing(this.checked); });

    const nReqT = document.getElementById('notifPrefRequestsToggle'); if (nReqT) nReqT.addEventListener('change', function () { applyNotifPref('notifPrefRequests', this.checked); });
    const nOverT = document.getElementById('notifPrefOverdueToggle'); if (nOverT) nOverT.addEventListener('change', function () { applyNotifPref('notifPrefOverdue', this.checked); });
    const nRoomT = document.getElementById('notifPrefRoomToggle'); if (nRoomT) nRoomT.addEventListener('change', function () { applyNotifPref('notifPrefRoom', this.checked); });

    const saidT = document.getElementById('showAssetIdsToggle'); if (saidT) saidT.addEventListener('change', function () { applyShowAssetIds(this.checked); });
    const verrT = document.getElementById('verboseErrorsToggle'); if (verrT) verrT.addEventListener('change', function () { applyVerboseErrors(this.checked); });

    /* ── Change Password Form Handler ───────────────────────── */
    const cpForm = document.getElementById('changePasswordForm');
    if (cpForm) {
        cpForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const alertBox = document.getElementById('cp-alert');
            const submitBtn = this.querySelector('button[type="submit"]');

            const formData = new FormData(this);
            formData.append('ajax_action', 'change_password');

            // Quick Front-end check
            if (formData.get('new_password') !== formData.get('confirm_password')) {
                alertBox.style.display = 'block';
                alertBox.style.backgroundColor = '#ffeaea';
                alertBox.style.color = 'var(--danger)';
                alertBox.innerHTML = '⚠️ Passwords do not match.';
                return;
            }

            // UX: Disable button while processing
            submitBtn.disabled = true;
            submitBtn.textContent = 'Updating...';

            fetch('admin-dashboard.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    alertBox.style.display = 'block';
                    if (data.status === 'success') {
                        alertBox.style.backgroundColor = '#e3fcef';
                        alertBox.style.color = '#00875a';
                        alertBox.innerHTML = '✅ ' + data.message;

                        const lastChangedEl = document.getElementById('pwLastChangedVal');
                        if (lastChangedEl && data.last_pw_change) {
                            const d = new Date(data.last_pw_change.replace(' ', 'T'));
                            if (!isNaN(d.getTime())) {
                                lastChangedEl.textContent = d.toLocaleString('en-US', {
                                    month: 'short', day: '2-digit', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true
                                }).replace(',', ' ·');
                            }
                        }

                        setTimeout(() => {
                            document.getElementById('changePassModal').style.display = 'none';
                            showToast('Password updated successfully');
                            cpForm.reset();
                        }, 1500);
                    } else {
                        alertBox.style.backgroundColor = '#ffeaea';
                        alertBox.style.color = 'var(--danger)';
                        alertBox.innerHTML = '⚠️ ' + data.message;
                    }
                })
                .catch(err => {
                    alertBox.style.display = 'block';
                    alertBox.style.backgroundColor = '#ffeaea';
                    alertBox.style.color = 'var(--danger)';
                    alertBox.innerHTML = '⚠️ Network error. Please try again.';
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Update';
                });
        });
    }

    /* ── Handle URL view param on load ──────────────────────── */
    function initView() {
        const params = new URLSearchParams(window.location.search);
        const view = params.get('view');
        const editItem = params.get('edit_item');

        if (editItem) {
            _switchTabDOM('inventory', false);
            _enterEditMode();
            history.replaceState({ tab: 'inventory', editItem: editItem }, '');
        } else if (view === 'inventory') {
            _switchTabDOM('inventory', false);
            history.replaceState({ tab: 'inventory' }, '');
        } else if (view === 'faculty') {
            _switchTabDOM('faculty');
            history.replaceState({ tab: 'faculty' }, '');
        } else {
            // Check for rooms tab param
            const tab = params.get('tab');
            const editRoom = params.get('edit_room');
            if (tab === 'rooms') {
                _switchTabDOM('rooms', false);
                if (editRoom) {
                    const rfw = document.getElementById('room-form-wrap');
                    const addRoomBtn = document.getElementById('addRoomBtn');
                    if (rfw) { rfw.classList.remove('hidden'); }
                    if (addRoomBtn) addRoomBtn.style.display = 'none';
                }
                // Auto-open archived sub-panel when redirected back from archive/restore
                const roomArchived = params.get('room_archived');
                const roomRestored = params.get('room_restored');
                if (roomArchived || roomRestored) {
                    switchRoomsTab('rooms-archived');
                }
                history.replaceState({ tab: 'rooms' }, '');
            } else {
                history.replaceState({ tab: 'dashboard' }, '');
            }
        }

        // Auto-dismiss alerts after 5s
        setTimeout(() => {
            ['added-alert', 'updated-alert',
                'room-added-alert', 'room-updated-alert',
                'room-archived-alert', 'room-restored-alert'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.style.display = 'none';
                });
        }, 5000);
    }

    /* ── popstate: back/forward support ─────────────────────── */
    window.addEventListener('popstate', function (e) {
        const state = e.state;
        if (!state) { _switchTabDOM('dashboard', false); return; }
        if (state.tab) _switchTabDOM(state.tab, false);
        if (state.sub) {
            switchLendingSub(state.sub, false);
        } else if (state.tab === 'lending') {
            switchLendingSub('waiting', false);
        }
        if (!state.editItem) _exitEditMode();
    });

    /* ── Init ────────────────────────────────────────────────── */
    function init() {
        restoreState();

        initView();
        initImageUpload(); // Add-equipment form (right column)
        initImageUpload({ // Edit-equipment modal
            dropZone: 'eqm-dropZone',
            fileInput: 'eqm-itemImageInput',
            preview: 'eqm-imagePreview',
            removeBtn: 'eqm-removeImageBtn',
            removeFlag: 'eqm-remove-image-flag'
        });
        initNotifCards();

        // Live search
        setupLiveSearch('waitingSearch', 'waiting-body', 'waiting');
        setupLiveSearch('returnSearch', 'return-body', 'approved');
        setupLiveSearch('approvedSearch', 'approved-list', 'approved');
        setupLiveSearch('declinedSearch', 'declined-list', 'declined');
        setupInventorySearch(); // client-side filter — see note near its definition
        setupLiveSearch('rawSearch', 'raw-data-body', 'raw');

        // ── Arbitration log search (server-side, reload with query param) ──
        const arbLogSearchInput = document.getElementById('arbLogSearch');
        if (arbLogSearchInput) {
            let arbLogTimer;
            arbLogSearchInput.addEventListener('keyup', function () {
                clearTimeout(arbLogTimer);
                const q = this.value.trim();
                arbLogTimer = setTimeout(() => {
                    const url = new URL(window.location.href);
                    url.searchParams.set('arb_log_search', q);
                    window.location.href = url.toString();
                }, 600);
            });
            // Pre-fill from URL param
            const urlParams = new URLSearchParams(window.location.search);
            const existingSearch = urlParams.get('arb_log_search');
            if (existingSearch) arbLogSearchInput.value = existingSearch;
        }

        // ── Save Arbitration Config ───────────────────────────────────────
        const saveArbConfigBtn = document.getElementById('saveArbConfig');
        if (saveArbConfigBtn) {
            saveArbConfigBtn.addEventListener('click', function () {
                const form = document.getElementById('arbConfigForm');
                if (!form) return;
                const formData = new FormData(form);
                // Ensure unchecked checkboxes for rule toggles send '0'
                ['rule_overdue_block_enabled', 'rule_duplicate_block_enabled', 'rule_missing_doc_block_enabled'].forEach(key => {
                    if (!formData.has('config[' + key + ']')) {
                        formData.append('config[' + key + ']', '0');
                    }
                });
                saveArbConfigBtn.disabled = true;
                saveArbConfigBtn.textContent = 'Saving...';
                fetch('equipment-booking/api/save-arbitration-config.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            const msg = document.getElementById('arbConfigMsg');
                            if (msg) {
                                msg.style.display = 'flex';
                                setTimeout(() => { msg.style.display = 'none'; }, 3000);
                            }
                            showToast('Arbitration settings saved.');
                        } else {
                            showToast(data.message || 'Could not save settings.');
                        }
                    })
                    .catch(() => showToast('Network error. Please try again.'))
                    .finally(() => {
                        saveArbConfigBtn.disabled = false;
                        saveArbConfigBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><polyline points="20 6 9 17 4 12" /></svg> Save Arbitration Settings';
                    });
            });
        }
    }

    // ── Override Modal ────────────────────────────────────────────────────────
    function openOverrideModal(requestId, currentStatus, equipment, borrower) {
        const modal = document.getElementById('overrideModal');
        const desc = document.getElementById('overrideDesc');
        const statusGroup = document.getElementById('overrideStatusGroup');
        const contextInfo = document.getElementById('overrideContextInfo');
        const reasonInput = document.getElementById('overrideReason');
        const alertBox = document.getElementById('override-alert');
        const submitBtn = document.getElementById('submitOverrideBtn');

        document.getElementById('overrideRequestId').value = requestId;
        document.getElementById('overrideCurrentStatus').value = currentStatus;
        reasonInput.value = '';
        alertBox.style.display = 'none';
        submitBtn.disabled = true;

        desc.textContent = 'Override for: ' + borrower + ' \u2014 ' + equipment;

        // Configure modal based on current status direction rules
        if (currentStatus === 'Waiting') {
            // Admin can choose Approved or Declined — show dropdown
            statusGroup.style.display = '';
            document.getElementById('overrideNewStatus').value = 'Approved';
            contextInfo.style.display = 'none';
        } else if (currentStatus === 'Approved' || currentStatus === 'Overdue') {
            // Fixed direction: Approved/Overdue → Declined only (item is out on loan)
            statusGroup.style.display = 'none';
            contextInfo.style.display = 'block';
            contextInfo.style.background = '#fff3e0';
            contextInfo.style.color = '#b45309';
            contextInfo.style.border = '1px solid #fcd34d';
            contextInfo.textContent = 'This will change the status from ' + currentStatus + ' to Declined and return 1 unit to inventory.';
        } else if (currentStatus === 'Declined') {
            // Fixed direction: Declined → Approved only
            statusGroup.style.display = 'none';
            contextInfo.style.display = 'block';
            contextInfo.style.background = '#ecfdf5';
            contextInfo.style.color = '#065f46';
            contextInfo.style.border = '1px solid #6ee7b7';
            contextInfo.textContent = 'This will change the status from Declined to Approved and decrement 1 unit from inventory.';
        } else {
            statusGroup.style.display = 'none';
            contextInfo.style.display = 'none';
        }

        modal.style.display = 'flex';
    }

    function closeOverrideModal() {
        const modal = document.getElementById('overrideModal');
        if (modal) modal.style.display = 'none';
    }

    // Enable/disable submit based on reason length (min 10 chars)
    const overrideReasonInput = document.getElementById('overrideReason');
    if (overrideReasonInput) {
        overrideReasonInput.addEventListener('input', function () {
            const submitBtn = document.getElementById('submitOverrideBtn');
            if (submitBtn) submitBtn.disabled = this.value.trim().length < 5;
        });
    }

    // Wire open-override data-action
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-action="open-override"]');
        if (btn) {
            openOverrideModal(
                btn.dataset.requestId,
                btn.dataset.requestStatus,
                btn.dataset.equipment,
                btn.dataset.borrower
            );
        }
    });

    const closeOverrideBtn = document.getElementById('closeOverrideModal');
    if (closeOverrideBtn) closeOverrideBtn.addEventListener('click', closeOverrideModal);
    const cancelOverrideBtn = document.getElementById('cancelOverrideBtn');
    if (cancelOverrideBtn) cancelOverrideBtn.addEventListener('click', closeOverrideModal);
    const overrideModalBackdrop = document.getElementById('overrideModalBackdrop');
    if (overrideModalBackdrop) overrideModalBackdrop.addEventListener('click', closeOverrideModal);

    const submitOverrideBtn = document.getElementById('submitOverrideBtn');
    if (submitOverrideBtn) {
        submitOverrideBtn.addEventListener('click', function () {
            const requestId = document.getElementById('overrideRequestId').value;
            const currentStatus = document.getElementById('overrideCurrentStatus').value;
            const reason = document.getElementById('overrideReason').value.trim();
            const alertBox = document.getElementById('override-alert');

            // Determine new status based on direction rules
            let newStatus;
            if (currentStatus === 'Approved' || currentStatus === 'Overdue') {
                newStatus = 'Declined';
            } else if (currentStatus === 'Declined') {
                newStatus = 'Approved';
            } else {
                // Waiting — use dropdown selection
                newStatus = document.getElementById('overrideNewStatus').value;
            }

            if (reason.length < 5) {
                alertBox.style.display = 'block';
                alertBox.style.backgroundColor = '#ffeaea';
                alertBox.style.color = 'var(--danger)';
                alertBox.textContent = 'Override reason is required.';
                return;
            }

            submitOverrideBtn.disabled = true;
            submitOverrideBtn.textContent = 'Applying...';

            const formData = new FormData();
            formData.append('request_id', requestId);
            formData.append('new_status', newStatus);
            formData.append('override_reason', reason);
            formData.append('csrf_token', getCsrfToken());

            fetch('equipment-booking/api/admin-override.php', { method: 'POST', body: formData })
                .then(function (r) {
                    if (r.status === 409) {
                        return r.json().then(function (d) {
                            throw { status: 409, message: d.message || 'Cannot override: item is out of stock.' };
                        });
                    }
                    if (r.status === 422) {
                        return r.json().then(function (d) {
                            throw { status: 422, message: d.message || 'Invalid status transition.' };
                        });
                    }
                    if (r.status === 400) {
                        return r.json().then(function (d) {
                            throw { status: 400, message: d.message || 'Override reason is required.' };
                        });
                    }
                    return r.json();
                })
                .then(function (data) {
                    if (data.status === 'success') {
                        closeOverrideModal();
                        showToast('Override applied successfully.');
                    } else {
                        alertBox.style.display = 'block';
                        alertBox.style.backgroundColor = '#ffeaea';
                        alertBox.style.color = 'var(--danger)';
                        alertBox.textContent = data.message || 'Override failed.';
                    }
                })
                .catch(function (err) {
                    alertBox.style.display = 'block';
                    alertBox.style.backgroundColor = '#ffeaea';
                    alertBox.style.color = 'var(--danger)';
                    alertBox.textContent = (err && err.message) ? err.message : 'Network error. Please try again.';
                })
                .finally(function () {
                    submitOverrideBtn.disabled = reason.trim().length < 5;
                    submitOverrideBtn.textContent = 'Apply Override';
                });
        });
    }

    /* ════════════════════════════════════════════════════════════
       REQUESTS PANEL (redesigned) — Approve / Decline / Detail
       Populates the ps-approve-modal / ps-decline-modal /
       ps-req-detail-modal from the clicked row's data-* attributes
       and submits to the existing admin-override.php endpoint —
       the same one the Arbitration "Override" flow already uses.
       A manual Approve/Decline from this tab is logged as an admin
       override (requires a reason, min. 5 chars), same as it is
       everywhere else in the app.
    ════════════════════════════════════════════════════════════════ */
    (function () {
        let reqCtx = null;

        const approveConfirmBtn = document.getElementById('ps-approve-confirm-btn');
        const declineConfirmBtn = document.getElementById('ps-decline-confirm-btn');
        const approveReasonInput = document.getElementById('approve-reason');
        const declineReasonInput = document.getElementById('decline-reason');

        function rowCtx(el) {
            const tr = el.closest('tr[data-id]');
            if (!tr) return null;
            const d = tr.dataset;
            return {
                id: d.id, status: d.status, condition: d.condition, borrower: d.borrower, idNumber: d.idNumber,
                reqType: d.reqType, equipment: d.equipment, instructor: d.instructor,
                room: d.room, submitted: d.submitted, dateNeeded: d.dateNeeded,
                returnDate: d.returnDate, returnDateDisplay: d.returnDateDisplay,
                arbRule: d.arbRule
            };
        }

        function reqNo(id) { return '#R-' + String(id || 0).padStart(4, '0'); }

        function clearAlert(id) {
            const el = document.getElementById(id);
            if (el) { el.style.display = 'none'; el.textContent = ''; }
        }

        function showAlert(id, msg) {
            const el = document.getElementById(id);
            if (!el) return;
            el.style.display = 'block';
            el.style.background = '#ffeaea';
            el.style.color = 'var(--danger)';
            el.textContent = msg;
        }

        function fillApprove(ctx) {
            document.getElementById('approve-request-id').value = ctx.id;
            document.getElementById('ps-approve-reqno').textContent = reqNo(ctx.id);
            document.getElementById('approve-borrower').textContent = ctx.borrower || '—';
            document.getElementById('approve-equipment').textContent = ctx.equipment || '—';
            document.getElementById('approve-date-needed').textContent = ctx.dateNeeded || '—';
            const dd = document.getElementById('approve-due-date');
            if (dd) dd.value = ctx.returnDate || '';
            if (approveReasonInput) approveReasonInput.value = '';
            if (approveConfirmBtn) approveConfirmBtn.disabled = true;
            clearAlert('ps-approve-alert');
        }

        function fillDecline(ctx) {
            document.getElementById('decline-request-id').value = ctx.id;
            document.getElementById('ps-decline-reqno').textContent = reqNo(ctx.id);
            document.getElementById('decline-borrower').textContent = ctx.borrower || '—';
            document.getElementById('decline-equipment').textContent = ctx.equipment || '—';
            if (declineReasonInput) declineReasonInput.value = '';
            if (declineConfirmBtn) declineConfirmBtn.disabled = true;
            clearAlert('ps-decline-alert');
        }

        function fillDetail(ctx) {
            document.getElementById('detail-request-id').value = ctx.id;
            document.getElementById('detail-submitted').textContent = ctx.submitted || '—';
            document.getElementById('detail-requester').textContent = ctx.borrower || '—';
            document.getElementById('detail-id-number').textContent = ctx.idNumber || '—';
            document.getElementById('detail-req-type').textContent = ctx.reqType || 'Faculty';
            document.getElementById('detail-room').textContent = ctx.room || '—';
            document.getElementById('detail-equipment').textContent = ctx.equipment || '—';
            document.getElementById('detail-date-needed').textContent = ctx.dateNeeded || '—';
            document.getElementById('detail-return-by').textContent = ctx.returnDateDisplay || '—';
            document.getElementById('detail-instructor').textContent = ctx.instructor || '—';

            const badgeMap = {
                Waiting: ['ps-badge--waiting', 'Waiting for Approval'],
                Approved: ['ps-badge--active', 'Active / Approved'],
                Overdue: ['ps-badge--overdue', 'Overdue'],
                Returned: ['ps-badge--returned', 'Returned'],
                Declined: ['ps-badge--returned', 'Declined']
            };
            const info = badgeMap[ctx.status] || ['ps-badge--waiting', ctx.status || '—'];
            const badge = document.getElementById('detail-status-badge');
            if (badge) {
                badge.className = 'ps-badge ps-badge--dot ' + info[0];
                badge.style.fontSize = '12px';
                badge.style.padding = '4px 12px';
                badge.textContent = info[1];
            }

            const condMap = {
                Good: 'ps-badge--active',
                Fair: 'ps-badge--waiting',
                'For Repair': 'ps-badge--overdue'
            };
            const condBadge = document.getElementById('detail-condition-badge');
            if (condBadge) {
                const condClass = condMap[ctx.condition] || 'ps-badge--active';
                condBadge.className = 'ps-badge ps-badge--dot ' + condClass;
                condBadge.textContent = ctx.condition || 'Good';
            }

            const arbEl = document.getElementById('detail-arb-status');
            if (arbEl) {
                arbEl.textContent = ctx.arbRule === 'override'
                    ? 'Manually overridden by an admin.'
                    : (ctx.status === 'Waiting' ? 'Pending review.' : 'Processed — rule: ' + (ctx.arbRule || 'n/a'));
            }

            // Only a Waiting request can be approved/declined from the detail view
            const declineBtn = document.getElementById('ps-detail-decline-btn');
            const approveBtn = document.getElementById('ps-detail-approve-btn');
            const showActions = ctx.status === 'Waiting';
            if (declineBtn) declineBtn.style.display = showActions ? '' : 'none';
            if (approveBtn) approveBtn.style.display = showActions ? '' : 'none';
        }

        document.addEventListener('click', function (e) {
            const trigger = e.target.closest('[data-modal="ps-approve-modal"], [data-modal="ps-decline-modal"], [data-modal="ps-req-detail-modal"]');
            if (!trigger) return;

            // Row click carries fresh context; the detail modal's own footer
            // Approve/Decline buttons have no row, so fall back to what was
            // last loaded into the detail view.
            const ctx = rowCtx(trigger) || reqCtx;
            if (!ctx) return;
            reqCtx = ctx;

            const modalId = trigger.dataset.modal;
            if (modalId === 'ps-approve-modal') fillApprove(ctx);
            else if (modalId === 'ps-decline-modal') fillDecline(ctx);
            else if (modalId === 'ps-req-detail-modal') fillDetail(ctx);
        });

        if (approveReasonInput && approveConfirmBtn) {
            approveReasonInput.addEventListener('input', function () {
                approveConfirmBtn.disabled = this.value.trim().length < 5;
            });
        }
        if (declineReasonInput && declineConfirmBtn) {
            declineReasonInput.addEventListener('input', function () {
                declineConfirmBtn.disabled = this.value.trim().length < 5;
            });
        }

        function submitDecision(requestId, newStatus, reason, alertId, btn, busyLabel) {
            btn.disabled = true;
            btn.dataset.origLabel = btn.innerHTML;
            btn.textContent = busyLabel;
            clearAlert(alertId);

            const formData = new FormData();
            formData.append('request_id', requestId);
            formData.append('new_status', newStatus);
            formData.append('override_reason', reason);
            formData.append('csrf_token', getCsrfToken());

            fetch('equipment-booking/api/admin-override.php', { method: 'POST', body: formData })
                .then(function (r) { return r.json().then(function (d) { return d; }); })
                .then(function (data) {
                    if (data && data.status === 'success') {
                        showToast(newStatus === 'Approved' ? 'Request approved.' : 'Request declined.');
                        psCloseModal('ps-approve-modal');
                        psCloseModal('ps-decline-modal');
                        psCloseModal('ps-req-detail-modal');
                        setTimeout(function () { location.reload(); }, 900);
                    } else {
                        showAlert(alertId, (data && data.message) || 'Action failed. Please try again.');
                        btn.disabled = false;
                        btn.innerHTML = btn.dataset.origLabel;
                    }
                })
                .catch(function () {
                    showAlert(alertId, 'Network error. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = btn.dataset.origLabel;
                });
        }

        if (approveConfirmBtn) {
            approveConfirmBtn.addEventListener('click', function () {
                const id = document.getElementById('approve-request-id').value;
                const reason = (approveReasonInput.value || '').trim();
                if (reason.length < 5) {
                    showAlert('ps-approve-alert', 'Please enter a reason (min. 5 characters).');
                    return;
                }
                submitDecision(id, 'Approved', reason, 'ps-approve-alert', approveConfirmBtn, 'Approving…');
            });
        }
        if (declineConfirmBtn) {
            declineConfirmBtn.addEventListener('click', function () {
                const id = document.getElementById('decline-request-id').value;
                const reason = (declineReasonInput.value || '').trim();
                if (reason.length < 5) {
                    showAlert('ps-decline-alert', 'Please enter a reason (min. 5 characters).');
                    return;
                }
                submitDecision(id, 'Declined', reason, 'ps-decline-alert', declineConfirmBtn, 'Declining…');
            });
        }
    })();

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();


    /* ================================================================
   QR RETURN SCANNER
   Uses jsQR (loaded dynamically) + Camera API.
   Works on phones, laptops, and desktop PCs with a webcam.
================================================================ */
    (function initQrScanner() {
        const openBtn = document.getElementById('openQrScannerBtn');
        const modal = document.getElementById('qrScannerModal');
        const closeBtn = document.getElementById('closeQrScanner');
        const video = document.getElementById('qrVideo');
        const status = document.getElementById('qrScanStatus');
        if (!openBtn || !modal || !video) return;

        let stream = null;
        let animFrame = null;
        let scanning = false;
        let scannedCount = 0;

        function stopScanner() {
            scanning = false;
            if (animFrame) cancelAnimationFrame(animFrame);
            if (stream) stream.getTracks().forEach(t => t.stop());
            stream = null;
            modal.style.display = 'none';
            if (scannedCount > 0) {
                showToast(`✅ ${scannedCount} return${scannedCount > 1 ? 's' : ''} confirmed this session. Refresh to see updated tables.`);
            }
            scannedCount = 0;
        }

        function processFrame(canvas, ctx) {
            if (!scanning) return;
            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                // Downscale to max 640px — jsQR doesn't need full camera resolution
                const MAX_W = 640;
                const scale = Math.min(1, MAX_W / video.videoWidth);
                const w = Math.floor(video.videoWidth * scale);
                const h = Math.floor(video.videoHeight * scale);

                // Only reset canvas dimensions when they actually change (not every frame)
                if (canvas.width !== w || canvas.height !== h) {
                    canvas.width = w;
                    canvas.height = h;
                }

                ctx.drawImage(video, 0, 0, w, h);
                const imageData = ctx.getImageData(0, 0, w, h);
                const code = window.jsQR(imageData.data, w, h, { inversionAttempts: 'attemptBoth' });
                if (code) {
                    scanning = false;
                    status.textContent = '✅ QR detected — confirming return...';
                    status.style.color = '#22c55e';

                    // Extract token from the URL in the QR
                    let token = null;
                    try {
                        const url = new URL(code.data);
                        token = url.searchParams.get('token');
                    } catch (e) {
                        token = null;
                    }

                    if (!token) {
                        status.textContent = '❌ Invalid QR code. Not a PUPSync return token.';
                        status.style.color = '#e53e3e';
                        setTimeout(() => { scanning = true; tick(); }, 2500);
                        return;
                    }

                    // Hit return_confirm.php via fetch — no page reload
                    fetch('equipment-booking/return_confirm.php?token=' + encodeURIComponent(token), {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                scannedCount++;
                                status.textContent = '✅ Return confirmed!';
                                status.style.color = '#22c55e';
                                showToast('✅ Equipment return confirmed via QR.');
                                // Stay open and keep scanning — a return day usually
                                // means several faculty in a row, not just one, so
                                // don't force reopening the modal for every scan.
                                setTimeout(() => {
                                    status.textContent = scannedCount > 1
                                        ? `Point camera at the next QR code. (${scannedCount} confirmed this session)`
                                        : 'Point camera at the next QR code.';
                                    status.style.color = '#888';
                                    scanning = true;
                                    tick();
                                }, 1500);
                            } else {
                                status.textContent = '❌ ' + (data.message || 'Invalid or already-used QR token.');
                                status.style.color = '#e53e3e';
                                setTimeout(() => {
                                    status.textContent = 'Point camera at a valid QR code.';
                                    status.style.color = '#888';
                                    scanning = true;
                                    tick();
                                }, 2500);
                            }
                        })
                        .catch(() => {
                            status.textContent = '❌ Network error. Please try again.';
                            status.style.color = '#e53e3e';
                            setTimeout(() => {
                                status.textContent = 'Point camera at a valid QR code.';
                                status.style.color = '#888';
                                scanning = true;
                                tick();
                            }, 2500);
                        });
                    return;
                }
            }
            tick();
        }

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d', { willReadFrequently: true });

        function tick() {
            animFrame = requestAnimationFrame(() => processFrame(canvas, ctx));
        }

        function startScanner() {
            modal.style.display = 'flex';
            status.textContent = 'Initializing camera...';
            status.style.color = '#888';
            scanning = false;

            // Load jsQR once
            function beginScan() {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    status.textContent = '❌ Camera not available on HTTP. Open the admin dashboard via the ngrok HTTPS URL instead.';
                    status.style.color = '#e53e3e';
                    return;
                }
                navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' } // rear cam on phone, webcam on laptop
                })
                    .then(s => {
                        stream = s;
                        video.srcObject = s;
                        video.play();
                        scanning = true;
                        status.textContent = 'Point camera at the QR code.';
                        tick();
                    })
                    .catch(err => {
                        status.textContent = '❌ Camera access denied. Please allow camera permission.';
                        status.style.color = '#e53e3e';
                        console.warn('Camera error:', err);
                    });
            }

            if (window.jsQR) {
                beginScan();
            } else {
                const script = document.createElement('script');
                script.src = 'assets/js/vendor/jsQR.min.js';
                script.onload = beginScan;
                script.onerror = () => {
                    status.textContent = '❌ Failed to load QR library. Check your internet connection.';
                    status.style.color = '#e53e3e';
                };
                document.head.appendChild(script);
            }
        }

        openBtn.addEventListener('click', startScanner);
        closeBtn.addEventListener('click', stopScanner);
        modal.addEventListener('click', e => { if (e.target === modal) stopScanner(); });
    })();

    /* ── Faculty: org-borrowing toggle ───────────────────────────────── */
    document.addEventListener('change', function (e) {
        const toggle = e.target.closest('.org-borrowing-toggle');
        if (!toggle) return;

        const facultyId = toggle.dataset.facultyId;
        const newValue = toggle.checked ? 1 : 0;
        const previousChecked = !toggle.checked;   // save for revert on error

        const formData = new FormData();
        formData.append('csrf_token', getCsrfToken());
        formData.append('faculty_id', facultyId);
        formData.append('allow_org_borrowing', newValue);

        fetch('equipment-booking/api/toggle-org-borrowing.php', {
            method: 'POST',
            body: formData
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { status: res.status, data };
                });
            })
            .then(function ({ status, data }) {
                if (status === 200 && data.status === 'success') {
                    // Update checked state to the value confirmed by the server
                    toggle.checked = data.allow_org_borrowing === 1;
                    showToast(
                        data.allow_org_borrowing === 1
                            ? 'Org borrowing enabled.'
                            : 'Org borrowing disabled.'
                    );
                } else {
                    // Revert the toggle
                    toggle.checked = previousChecked;
                    showToast('Error: ' + (data.message || 'Could not update permission.'));
                }
            })
            .catch(function () {
                toggle.checked = previousChecked;
                showToast('Network error. Please try again.');
            });
    });
})();

/* ════════════════════════════════════════════════════════════════
   PHASE 3 — REQUESTS PANEL JS
   Modal system + sub-tab switcher
════════════════════════════════════════════════════════════════ */

/* ── ps-modal open / close ────────────────────────────────────── */
function psOpenModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('ps-modal-open');
}
function psCloseModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('ps-modal-open');
}

/* Close modal when clicking the backdrop */
document.querySelectorAll('.ps-modal-backdrop').forEach(function (bd) {
    bd.addEventListener('click', function (e) {
        if (e.target === bd) bd.classList.remove('ps-modal-open');
    });
});

/* ── Requests filter-chip switcher ────────────────────────────── */
(function () {
    var tabGroup = document.getElementById('rqTabs');
    if (!tabGroup) return;

    tabGroup.querySelectorAll('.rq-filter-chip').forEach(function (tab) {
        tab.addEventListener('click', function () {
            /* Deactivate all chips and panels */
            tabGroup.querySelectorAll('.rq-filter-chip').forEach(function (t) {
                t.classList.remove('active');
            });
            document.querySelectorAll('#panel-requests .rq-sub-panel').forEach(function (p) {
                p.classList.remove('active');
            });

            /* Activate clicked chip and its panel */
            tab.classList.add('active');
            var panelId = tab.dataset.rqPanel;
            var panel = document.getElementById(panelId);
            if (panel) panel.classList.add('active');
        });
    });
})();

/* ── Live search — Waiting table ─────────────────────────────── */
(function () {
    var input = document.getElementById('rq-waiting-search');
    var table = document.getElementById('rq-waiting-table');
    if (!input || !table) return;
    input.addEventListener('input', function () {
        var q = input.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(function (row) {
            var text = row.textContent.toLowerCase();
            row.style.display = text.includes(q) ? '' : 'none';
        });
    });
})();

/* ── Live search + return-date range filter — Returned Requests table ── */
(function () {
    var searchInput = document.getElementById('rq-all-search');
    var rangeSel = document.getElementById('rq-all-range');
    var table = document.getElementById('rq-all-table');
    if (!table) return;

    function inReturnRange(dateStr, range) {
        if (!dateStr) return true; // rows with no date (e.g. empty-state) always show
        var d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d.getTime())) return true;
        var now = new Date();
        var todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        if (range === 'today') {
            return d.getTime() === todayStart.getTime();
        }
        if (range === 'week') {
            var weekStart = new Date(todayStart);
            weekStart.setDate(todayStart.getDate() - todayStart.getDay());
            return d.getTime() >= weekStart.getTime() && d.getTime() <= todayStart.getTime();
        }
        if (range === 'month') {
            return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth();
        }
        if (range === 'year') {
            return d.getFullYear() === now.getFullYear();
        }
        return true;
    }

    function filterAll() {
        var q = searchInput ? searchInput.value.toLowerCase() : '';
        var range = rangeSel ? rangeSel.value : 'month';
        table.querySelectorAll('tbody tr').forEach(function (row) {
            var text = row.textContent.toLowerCase();
            var matchQ = !q || text.includes(q);
            var matchRange = inReturnRange(row.dataset.returnDate, range);
            row.style.display = (matchQ && matchRange) ? '' : 'none';
        });
    }
    if (searchInput) searchInput.addEventListener('input', filterAll);
    if (rangeSel) rangeSel.addEventListener('change', filterAll);
    filterAll(); // apply the default "This Month" range immediately
})();
/* ════════════════════════════════════════════════════════════════
   PHASE 3 — ARBITRATION + EXPORT HELPERS
════════════════════════════════════════════════════════════════ */

/* Arbitration sub-tab switcher */
(function () {
    var arbTabs = document.getElementById('arbSubTabs');
    if (!arbTabs) return;
    arbTabs.querySelectorAll('.rq-sub-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            arbTabs.querySelectorAll('.rq-sub-tab').forEach(function (t) { t.classList.remove('active'); });
            document.querySelectorAll('#sett-rules .arb-sub-panel').forEach(function (p) { p.classList.remove('active'); });
            tab.classList.add('active');
            var panel = document.getElementById(tab.dataset.arbPanel);
            if (panel) panel.classList.add('active');
        });
    });

    /* Decision log filter */
    var filterSel = document.getElementById('arb-log-filter');
    var logTable = document.getElementById('arb-log-new-table');
    if (filterSel && logTable) {
        filterSel.addEventListener('change', function () {
            var val = filterSel.value;
            logTable.querySelectorAll('tbody tr').forEach(function (row) {
                row.style.display = (!val || (row.dataset.decision || '') === val) ? '' : 'none';
            });
        });
    }
})();

/* Export format button selector */
function psSelectFmt(btn) {
    var row = btn.closest('.ps-export-fmt-row');
    if (!row) return;
    row.querySelectorAll('.ps-export-fmt-btn').forEach(function (b) { b.classList.remove('active'); });
    btn.classList.add('active');
}