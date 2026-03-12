(function () {
    'use strict';

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

    /* ── Theme DOM helpers ───────────────────────────────────── */
    function _applyThemeDOM(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.style.removeProperty('--section-tint-start');
        document.documentElement.style.removeProperty('--section-tint-end');
        const map = { light: 'light', dark: 'dark', 'high-contrast': 'hc' };
        ['light', 'dark', 'hc'].forEach(k => {
            const el = document.getElementById('tp-' + k);
            const ch = document.getElementById('tc-' + k);
            if (el) el.classList.remove('selected');
            if (ch) ch.style.display = 'none';
        });
        const key = map[theme] || theme;
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

        if (LS.get('compact') === 'true') {
            const ct = document.getElementById('compactToggle');
            if (ct) ct.checked = true;
            document.documentElement.style.setProperty('--radius', '9px');
        }

        const fs = LS.get('fontSize');
        if (fs && fs !== '100') {
            const fr = document.getElementById('fontSizeRange');
            if (fr) fr.value = fs;
            const lbl = document.getElementById('fontSizeLbl');
            if (lbl) lbl.textContent = fs + '%';
            document.documentElement.style.fontSize = (parseFloat(fs) / 100) + 'rem';
        }

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

        // Profile fields — only apply stored values when server did not provide a real name
        ['admin_name', 'admin_email'].forEach(key => {
            const val = LS.get('prof_' + key);
            if (!val) return;
            const span = document.querySelector('[data-field="' + key + '"]');
            const input = document.querySelector('[data-input="' + key + '"]');
            if (span) {
                const current = (span.textContent || '').trim();
                const isPlaceholder = !current || current === '— Not provided' || current === 'Administrator';
                if (isPlaceholder) {
                    span.textContent = val;
                    span.classList.remove('empty');
                } else {
                    // keep server-provided value; ensure the input mirrors it for edit mode
                    if (input) input.value = current;
                }
            }
            if (input && !input.value) input.value = val;
        });

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
    function _switchTabDOM(tabName) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.nav-tab').forEach(b => b.classList.remove('active'));
        const panel = document.getElementById('panel-' + tabName);
        const btn = document.querySelector('.nav-tab[data-tab="' + tabName + '"]');
        if (panel) panel.classList.add('active');
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

    /* ── Overlays ────────────────────────────────────────────── */
    function openOverlay(id) {
        closeDropdown();
        document.querySelectorAll('.overlay-page.active').forEach(o => o.classList.remove('active'));
        const el = document.getElementById(id);
        if (el) el.classList.add('active');
    }

    function closeOverlay(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('active');
    }

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

    /* ── Account sub-tabs ────────────────────────────────────── */
    function switchAccTab(panelId) {
        document.querySelectorAll('#accountOverlay .overlay-sub-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.acc-nav-btn').forEach(b => b.classList.remove('active'));
        const p = document.getElementById(panelId);
        const b = document.querySelector('.acc-nav-btn[data-acc-tab="' + panelId + '"]');
        if (p) p.classList.add('active');
        if (b) b.classList.add('active');
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

    /* ── Notification card expand & dismiss ──────────────────── */
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

    /* ── Settings sub-tabs ───────────────────────────────────── */
    function switchSettTab(panelId) {
        document.querySelectorAll('#settingsOverlay .overlay-sub-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.s-nav-item').forEach(b => b.classList.remove('active'));
        const p = document.getElementById(panelId);
        const b = document.querySelector('.s-nav-item[data-sett-tab="' + panelId + '"]');
        if (p) p.classList.add('active');
        if (b) b.classList.add('active');
    }

    /* ── Notifications ───────────────────────────────────────── */
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
        document.querySelectorAll('.notif-btn-badge,.notif-badge').forEach(b => b.style.display = 'none');
        LS.setJ('notifRead', readArr);
        showToast('All notifications marked as read.');
    }

    /* ── Settings ────────────────────────────────────────────── */
    function applyTheme(theme) { _applyThemeDOM(theme); LS.set('theme', theme); showToast('Theme: ' + theme.charAt(0).toUpperCase() + theme.slice(1)); }
    function applyAccent(color, light) { _applyAccentDOM(color, light); LS.set('accentColor', color); LS.set('accentLight', light); showToast('Accent color updated!'); }
    function applyCompact(on) { document.documentElement.style.setProperty('--radius', on ? '9px' : '16px'); LS.set('compact', on); showToast(on ? 'Compact mode enabled' : 'Compact mode disabled'); }
    function applyFontSize(val) {
        const lbl = document.getElementById('fontSizeLbl');
        if (lbl) lbl.textContent = val + '%';
        document.documentElement.style.fontSize = (val / 100) + 'rem';
        LS.set('fontSize', val);
    }
    function _setReduceMotion(on) {
        let s = document.getElementById('reduceMotionStyle');
        if (!s) { s = document.createElement('style'); s.id = 'reduceMotionStyle'; document.head.appendChild(s); }
        s.textContent = on ? '*, *::before, *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; }' : '';
    }
    function applyReduceMotion(on) { _setReduceMotion(on); LS.set('reduceMotion', on); showToast(on ? 'Animations disabled' : 'Animations re-enabled'); }
    function _setFocusRing(on) {
        let s = document.getElementById('focusRingStyle');
        if (!s) { s = document.createElement('style'); s.id = 'focusRingStyle'; document.head.appendChild(s); }
        s.textContent = on ? '*:focus { outline: 3px solid var(--accent-maroon) !important; outline-offset: 3px !important; }' : '';
    }
    function applyFocusRing(on) { _setFocusRing(on); LS.set('focusRing', on); showToast(on ? 'Focus rings enhanced' : 'Focus rings reset'); }

    function resetAllSettings() {
        applyTheme('light');
        const ct = document.getElementById('compactToggle'); if (ct) { ct.checked = false; applyCompact(false); }
        const fr = document.getElementById('fontSizeRange'); if (fr) { fr.value = 100; applyFontSize(100); }
        const rmt = document.getElementById('reduceMotionToggle'); if (rmt) { rmt.checked = false; applyReduceMotion(false); }
        const frt = document.getElementById('focusRingToggle'); if (frt) { frt.checked = false; applyFocusRing(false); }
        applyAccent('#600302', '#f3e5e6');
        ['theme', 'accentColor', 'accentLight', 'compact', 'fontSize', 'reduceMotion', 'focusRing'].forEach(k => LS.del(k));
        showToast('All settings reset to defaults.');
    }

    /* ── Profile edit ────────────────────────────────────────── */
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
        document.querySelectorAll('[data-input]').forEach(input => {
            const key = input.dataset.input;
            const span = document.querySelector('[data-field="' + key + '"]');
            if (!span) return;
            const val = input.value.trim();
            if (val) { span.textContent = val; span.classList.remove('empty'); LS.set('prof_' + key, val); }
            else { span.textContent = '— Not provided'; span.classList.add('empty'); LS.del('prof_' + key); }
        });
        cancelProfileEdit();
        showToast('Profile updated successfully!');
    }

    /* ── Image upload handlers ───────────────────────────────── */
    function initImageUpload() {
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('itemImageInput');
        const preview = document.getElementById('imagePreview');
        const removeBtn = document.getElementById('removeImageBtn');

        function handleFile(file) {
            if (!file.type.startsWith('image/')) { showToast('Only image files allowed.'); return; }
            const reader = new FileReader();
            reader.onload = e => { if (preview) { preview.src = e.target.result; preview.style.display = 'block'; } };
            reader.readAsDataURL(file);
            if (fileInput) { const dt = new DataTransfer(); dt.items.add(file); fileInput.files = dt.files; }
            if (removeBtn) removeBtn.classList.remove('hidden');
        }

        if (dropZone) {
            dropZone.addEventListener('click', () => fileInput && fileInput.click());
            ['dragenter', 'dragover'].forEach(ev => dropZone.addEventListener(ev, e => { e.preventDefault(); dropZone.style.borderColor = 'var(--accent-maroon)'; }));
            ['dragleave', 'drop'].forEach(ev => dropZone.addEventListener(ev, e => { e.preventDefault(); dropZone.style.borderColor = ''; }));
            dropZone.addEventListener('drop', e => { const f = e.dataTransfer.files[0]; if (f) handleFile(f); });
        }
        if (fileInput) fileInput.addEventListener('change', () => { if (fileInput.files[0]) handleFile(fileInput.files[0]); });
        document.addEventListener('paste', e => {
            const item = [...(e.clipboardData?.items || [])].find(i => i.type.startsWith('image'));
            if (item) handleFile(item.getAsFile());
        });
        if (removeBtn) {
            removeBtn.addEventListener('click', e => {
                e.stopPropagation();
                if (preview) { preview.src = ''; preview.style.display = 'none'; }
                if (fileInput) fileInput.value = '';
                removeBtn.classList.add('hidden');
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
            fetch(`ajax/live-search.php?q=${encodeURIComponent(q)}&section=${section}`)
                .then(r => r.text())
                .then(data => { tbody.innerHTML = data; })
                .catch(() => { tbody.innerHTML = "<tr><td colspan='10' class='text-muted' style='text-align:center;padding:1.5rem;'>Error fetching data.</td></tr>"; });
        });
    }

    /* ── Master event delegation ─────────────────────────────── */
    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-action]');
        if (!el) return;
        const action = el.dataset.action;
        try {
            switch (action) {
                case 'open-overlay':
                    openOverlay(el.dataset.target); break;
                case 'close-overlay':
                    closeOverlay(el.dataset.target); break;
                case 'dismiss-alert': {
                    const t = document.getElementById(el.dataset.target);
                    if (t) t.style.display = 'none'; break;
                }
                case 'go-lending': {
                    const dest = el.dataset.lending || 'waiting';
                    _switchTabDOM('lending');
                    if (dest === 'approved' || dest === 'declined') {
                        switchLendingSub('history');
                        switchHistoryTab(dest);
                    } else if (dest === 'archive') {
                        // archive is now inside inventory as "Archived Items"
                        switchLendingSub('inventory');
                        switchHistoryTab('reg-archived');
                    } else {
                        switchLendingSub(dest);
                    }
                    break;
                }
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
                    if (confirm('Confirm Logout?')) window.location.href = 'includes/logout.php';
                    break;
            }
        } catch (err) { console.warn('Action "' + action + '" failed:', err); }
    });

    /* ── Avatar button ───────────────────────────────────────── */
    const avatarBtn = document.getElementById('avatarBtn');
    if (avatarBtn) avatarBtn.addEventListener('click', e => { e.stopPropagation(); toggleDropdown(); });
    document.addEventListener('click', e => { if (!e.target.closest('.header-right')) closeDropdown(); });

    /* ── Nav tabs ─────────────────────────────────────────────── */
    document.querySelectorAll('.nav-tab').forEach(btn => {
        btn.addEventListener('click', function () { _switchTabDOM(this.dataset.tab); });
    });

    /* ── Lending sub-nav ─────────────────────────────────────── */
    document.querySelectorAll('.lending-nav-btn').forEach(btn => {
        btn.addEventListener('click', function () { switchLendingSub(this.dataset.lendingNav); });
    });

    /* ── Borrow History toggle ───────────────────────────────── */
    document.querySelectorAll('.history-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function () { switchHistoryTab(this.dataset.historyTab); });
    });

    /* ── Account sub-nav ─────────────────────────────────────── */
    document.querySelectorAll('.acc-nav-btn').forEach(btn => {
        btn.addEventListener('click', function () { switchAccTab(this.dataset.accTab); });
    });

    /* ── Settings sub-nav ────────────────────────────────────── */
    document.querySelectorAll('.s-nav-item').forEach(btn => {
        btn.addEventListener('click', function () { switchSettTab(this.dataset.settTab); });
    });

    /* ── Notification filter tabs ────────────────────────────── */
    document.querySelectorAll('.notif-tab').forEach(btn => {
        btn.addEventListener('click', function () { filterNotifs(this.dataset.notifFilter); });
    });

    /* ── Settings toggles ────────────────────────────────────── */
    const ct = document.getElementById('compactToggle'); if (ct) ct.addEventListener('change', function () { applyCompact(this.checked); });
    const fr = document.getElementById('fontSizeRange'); if (fr) fr.addEventListener('input', function () { applyFontSize(this.value); });
    const rmt = document.getElementById('reduceMotionToggle'); if (rmt) rmt.addEventListener('change', function () { applyReduceMotion(this.checked); });
    const frt = document.getElementById('focusRingToggle'); if (frt) frt.addEventListener('change', function () { applyFocusRing(this.checked); });

    /* ── Handle URL view param on load ──────────────────────── */
    function initView() {
        const params = new URLSearchParams(window.location.search);
        const view = params.get('view');
        const editItem = params.get('edit_item');
        const hash = window.location.hash.replace('#lending-', '');
        const validSubs = ['waiting', 'history', 'inventory', 'raw'];

        if (editItem) {
            _switchTabDOM('lending', false);
            switchLendingSub('inventory', false);
            _enterEditMode();
            history.replaceState({ tab: 'lending', sub: 'inventory', editItem: editItem }, '');
        } else if (view && validSubs.includes(view)) {
            _switchTabDOM('lending', false);
            switchLendingSub(view, false);
            history.replaceState({ tab: 'lending', sub: view }, '');
        } else if (view === 'approved') {
            _switchTabDOM('lending', false);
            switchLendingSub('history', false);
            switchHistoryTab('approved');
            history.replaceState({ tab: 'lending', sub: 'history' }, '');
        } else if (view === 'declined') {
            _switchTabDOM('lending', false);
            switchLendingSub('history', false);
            switchHistoryTab('declined');
            history.replaceState({ tab: 'lending', sub: 'history' }, '');
        } else if (hash && validSubs.includes(hash)) {
            _switchTabDOM('lending', false);
            switchLendingSub(hash, false);
            history.replaceState({ tab: 'lending', sub: hash }, '');
        } else {
            history.replaceState({ tab: 'dashboard' }, '');
        }

        // Auto-dismiss alerts after 5s
        setTimeout(() => {
            ['added-alert', 'updated-alert'].forEach(id => {
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
        initImageUpload();
        initNotifCards();

        // Live search
        setupLiveSearch('waitingSearch', 'waiting-body', 'waiting');
        setupLiveSearch('returnSearch', 'return-body', 'approved');
        setupLiveSearch('approvedSearch', 'approved-list', 'approved');
        setupLiveSearch('declinedSearch', 'declined-list', 'declined');
        setupLiveSearch('inventorySearch', 'inventory-body', 'inventory');
        setupLiveSearch('rawSearch', 'raw-data-body', 'raw');
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();