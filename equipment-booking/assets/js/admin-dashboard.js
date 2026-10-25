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

    /* ── Toast / pop-up alert ────────────────────────────────────
       Every admin snackbar is this one alert: a card at the bottom-centre of the
       screen (same design as the faculty "Borrowing Blocked" alert) with an icon,
       the message and a close button. It fades up, stays ~4.5 s, then disappears.
         showToast(msg)                        → type is worked out from the message
         showToast(msg, 'success' | 'error' | 'warning' | 'info')
         showToast(msg, ms) / showToast(msg, type, ms)   → custom time on screen
       A leading ✅ ⚠️ ❌ 🔔 in the message picks the type and is dropped (the alert
       draws its own icon). */
    let toastTimer;
    const TOAST_MS = 4500;
    const TOAST_ICONS = { info: 'info', success: 'check_circle', error: 'error', warning: 'warning' };

    function hideToast() {
        const t = document.getElementById('app-toast');
        if (!t) return;
        clearTimeout(toastTimer);
        t.classList.remove('show');
    }

    function showToast(msg, a, b) {
        const t = document.getElementById('app-toast');
        if (!t) return;

        let type = '', ms = TOAST_MS;
        [a, b].forEach(function (v) {
            if (typeof v === 'number') ms = v;
            else if (typeof v === 'string') type = v.toLowerCase();
        });
        // Older callers pass labels like 't-danger'
        if (type === 't-danger' || type === 'danger') type = 'error';
        else if (type === 'warn') type = 'warning';
        if (!TOAST_ICONS[type]) type = '';

        let text = String(msg == null ? '' : msg);
        const lead = text.match(/^\s*(✅|⚠️?|❌|🔔|ℹ️?)\s*/);
        if (lead) {
            text = text.slice(lead[0].length);
            if (!type) type = lead[1] === '✅' ? 'success' : (lead[1] === '❌' ? 'error' : (lead[1][0] === '⚠' ? 'warning' : 'info'));
        }
        if (!type) {
            if (/^(overdue alert|warning)\b/i.test(text)) type = 'warning';
            else if (/^error\b|^(could not|couldn[’']t|failed|network error|invalid|something went wrong)|\b(cannot be empty|is required|are allowed|please try again|please reload)\b/i.test(text)) type = 'error';
            else if (/\bsuccessfully\b/i.test(text)) type = 'success';
            else type = 'info';
        }

        t.textContent = '';
        t.dataset.type = type;
        t.setAttribute('role', (type === 'error' || type === 'warning') ? 'alert' : 'status');

        const ico = document.createElement('span');
        ico.className = 'material-symbols-outlined app-toast__icon';
        ico.setAttribute('aria-hidden', 'true');
        ico.textContent = TOAST_ICONS[type];

        const txt = document.createElement('span');
        txt.className = 'app-toast__text';
        txt.textContent = text;

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'app-toast__close';
        close.setAttribute('aria-label', 'Close');
        const closeIco = document.createElement('span');
        closeIco.className = 'material-symbols-outlined';
        closeIco.setAttribute('aria-hidden', 'true');
        closeIco.textContent = 'close';
        close.appendChild(closeIco);
        close.addEventListener('click', hideToast);

        t.appendChild(ico);
        t.appendChild(txt);
        t.appendChild(close);

        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(hideToast, ms);
    }
    /* -- Toast queue (welcome, overdue alert, room save/archive/restore results) --
       The server renders #toast-queue only when there is something to say:
       once after a real sign-in, or after a room redirect (?room_added=1 …).
       Messages are shown one after another through the normal toast, and the
       login splash (~4s) is waited out first. */
    (function initToastQueue() {
        const src = document.getElementById('toast-queue');
        if (!src) return;
        const msgs = Array.from(src.querySelectorAll('[data-msg]'))
            .map(function (n) { return n.getAttribute('data-msg'); })
            .filter(Boolean);
        const strip = (src.getAttribute('data-strip') || '').split(',').filter(Boolean);
        src.remove();

        // init() below (initView) still needs ?room_archived / ?room_restored to open the
        // right sub-panel, so only drop the params AFTER it has run — otherwise a refresh
        // would repeat the message. (Same timing trick as fixInventoryTab() in the PHP.)
        function afterInit(fn) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () { setTimeout(fn, 0); });
            } else {
                setTimeout(fn, 0);
            }
        }
        if (strip.length) {
            afterInit(function () {
                const p = new URLSearchParams(window.location.search);
                let changed = false;
                strip.forEach(function (k) { if (p.has(k)) { p.delete(k); changed = true; } });
                if (!changed) return;
                const qs = p.toString();
                window.history.replaceState(window.history.state, '',
                    window.location.pathname + (qs ? '?' + qs : '') + window.location.hash);
            });
        }

        if (!msgs.length) return;
        let i = 0;
        function next() {
            if (i >= msgs.length) return;
            const m = msgs[i++];
            const ms = Math.min(5000, Math.max(4000, m.length * 45)); // 4–5 s; longer text a bit more
            showToast(m, ms);
            setTimeout(next, ms + 650); // let it fade out before the next one
        }
        let waited = 0;
        (function whenSplashGone() {
            if (document.getElementById('roleSplash') && waited < 7000) {
                waited += 150;
                setTimeout(whenSplashGone, 150);
                return;
            }
            setTimeout(next, 350);
        })();
    })();

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

        // Notification read/unread state is no longer a client-side guess —
        // it's rendered straight from tbl_notif_state on page load (see
        // notif-functions.php) and kept in sync via the notif-*.php AJAX
        // endpoints from here on. See the "Notifications (server-backed)"
        // section further down for markRead/delete/poll wiring.
    }

    /* ── Mobile sidebar drawer ───────────────────────────────── */
    function _closeMobileSidebar() {
        document.body.classList.remove('sidebar-open');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
    }

    /* ── Tab switcher ────────────────────────────────────────── */
    function _switchTabDOM(tabName, clickedEl) {
        const panel = document.getElementById('panel-' + tabName);
        if (!panel) return;
        // If the pre-paint script already activated this panel (no flash), skip
        // the remove-all/re-add cycle so the slideIn animation doesn't re-fire.
        const alreadyActive = panel && panel.classList.contains('active');
        if (!alreadyActive || clickedEl !== false) {
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            if (panel) panel.classList.add('active');
        }
        document.querySelectorAll('.nav-item[data-tab]').forEach(b => b.classList.remove('active'));
        // Highlight the specific element that was clicked, or fall back to first match
        const btn = clickedEl || document.querySelector('.nav-item[data-tab="' + tabName + '"]');
        if (btn) btn.classList.add('active');
        // Settings lives inside the account menu, so mark the account toggle as the
        // current item too (it stays visually quiet while the menu is open).
        const acctToggle = document.getElementById('navAccountToggle');
        if (acctToggle) acctToggle.classList.toggle('active', tabName === 'settings');
        _closeMobileSidebar();
        // Write hash for F5 persistence. clickedEl === false signals a popstate / initView
        // restore — skip rewriting so we don't redundantly overwrite the hash or trigger loops.
        if (clickedEl !== false) {
            var newHash = '#tab-' + tabName;
            if (window.location.hash !== newHash) {
                history.replaceState(history.state, '', window.location.pathname + window.location.search + newHash);
            }
        }
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

    /* ── Account menu (sidebar footer) ───────────────────────────
       The avatar + name tab expands Scan Return / Notifications /
       Settings inline, directly under itself and above Log Out. */
    function setAccountMenuOpen(open) {
        const toggle = document.getElementById('navAccountToggle');
        const menu = document.getElementById('navAccountMenu');
        if (!toggle || !menu) return;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        menu.classList.toggle('open', open);
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

    /* switchSettTab (legacy Settings-overlay tab helper) removed —
       the overlay it served no longer exists; Settings navigation
       now goes through switchSettMainTab(). */

    /* ════════════════════════════════════════════════════════════
       NOTIFICATIONS (server-backed)
       The feed itself (which notifications exist) is computed live
       server-side from tbl_requests / tbl_room_issues / tbl_inventory
       — see notif-functions.php. This section only handles: marking
       read/deleted (persisted via equipment-booking/api/notif-*.php),
       filtering the visible list, navigating to the right screen when
       a notification is opened, and short-polling for new ones.
    ════════════════════════════════════════════════════════════════ */
    /* Select / bulk-action state for the Notifications modal (see the
       NOTIF-SELECT region below). Declared up here so every helper in this
       section can safely read it. */
    let _anSelectMode = false;   // "Select" mode on: rows toggle a checkbox instead of expanding
    let _anPending = null;       // { keys, text } while the inline delete confirmation is open
    let _anLastAnchor = null;    // last row clicked, for shift-click range select
    let _anBusy = 0;             // in-flight bulk requests (polling pauses while > 0)
    const _anSelected = new Set();

    function _getUnreadCount() {
        return document.querySelectorAll('#notifList .notif-item.unread').length;
    }

    function _updateBadges(count) {
        const uc = document.getElementById('unreadCount');
        if (uc) uc.textContent = count + ' unread';
        const up = document.getElementById('unreadPlural');
        if (up) up.textContent = count === 1 ? '' : 's';
        const markAllBtn = document.querySelector('[data-action="mark-all-read"]');
        if (markAllBtn) markAllBtn.disabled = (count === 0);
        const navDot = document.getElementById('navNotifDot');
        if (navDot) navDot.hidden = count <= 0;
        const unreadLabel = count > 0 ? ' \u2014 ' + count + ' unread' : '';
        const acctToggleEl = document.getElementById('navAccountToggle');
        if (acctToggleEl) acctToggleEl.setAttribute('aria-label', 'Account menu' + unreadLabel);
        document.querySelectorAll('.notif-btn-badge,.notif-badge').forEach(b => {
            const prev = parseInt(b.textContent, 10) || 0;
            if (count === 0) {
                b.style.display = 'none';
            } else {
                b.style.display = '';
                b.textContent = count;
                if (count > prev) {
                    b.classList.remove('notif-badge-pulse');
                    void b.offsetWidth; // restart animation
                    b.classList.add('notif-badge-pulse');
                }
            }
        });
    }

    function _notifPost(url, key) {
        const body = new URLSearchParams();
        if (key) body.set('key', key);
        body.set('csrf_token', getCsrfToken());
        return fetch(url, { method: 'POST', body })
            .then(r => r.json())
            .catch(() => null);
    }

    /* Cheap, local recount of the filter-pill numbers (no network round-trip).
       Called after any local action (read / unread / delete / mark-all-read) and
       after every poll, so the numbers never lag behind what is in the list.
       Cards that are mid-removal, or in a category muted under Settings ->
       Notification Preferences, are not counted. */
    function _anMutedCats() {
        const hidden = [];
        if (LS.get('notifPrefRequests') === 'false') hidden.push('request');
        if (LS.get('notifPrefOverdue') === 'false') hidden.push('overdue');
        return hidden;
    }

    function _updateChipCounts() {
        const bar = document.getElementById('notifFilterChips');
        if (!bar) return;
        const muted = _anMutedCats();
        const counts = { all: 0, unread: 0, request: 0, overdue: 0, room: 0, system: 0 };
        document.querySelectorAll('#notifList .notif-item[data-notif-key]:not(.notif-removing)').forEach(item => {
            if (muted.indexOf(item.dataset.cat) !== -1) return;
            counts.all++;
            if (item.classList.contains('unread')) counts.unread++;
            if (counts.hasOwnProperty(item.dataset.cat)) counts[item.dataset.cat]++;
        });
        const labels = { all: 'All', unread: 'Unread', request: 'Requests', overdue: 'Overdue', room: 'Facilities', system: 'System' };
        Object.keys(labels).forEach(cat => {
            const chip = bar.querySelector('.rq-filter-chip[data-notif-filter="' + cat + '"]');
            if (chip) chip.innerHTML = _esc(labels[cat]) + (counts[cat] > 0 ? ' <span class="notif-chip-count">' + counts[cat] + '</span>' : '');
        });
        _anRefreshChrome();
    }

    function _markCardRead(card) {
        if (!card.classList.contains('unread')) return;
        _anApplyReadState(card, true);   // class + dot + the "Mark as read/unread" button label
        _updateBadges(_getUnreadCount());
        _updateChipCounts();
        const key = card.dataset.notifKey;
        if (key) _notifPost('equipment-booking/api/notif-mark-read.php', key);
    }

    function _deleteCard(card) {
        const key = card.dataset.notifKey;
        const wasUnread = card.classList.contains('unread');
        _anSelected.delete(key);
        card.classList.add('notif-removing');
        setTimeout(() => {
            const group = card.previousElementSibling && card.previousElementSibling.classList.contains('notif-group-label')
                ? card.previousElementSibling : null;
            card.remove();
            // If that was the last card in its group, drop the now-empty group label too.
            if (group) {
                const next = group.nextElementSibling;
                if (!next || !next.classList.contains('notif-item')) group.remove();
            }
            if (!document.querySelector('#notifList .notif-item')) _showEmptyState();
            _updateChipCounts();
        }, 220);
        _updateChipCounts();
        if (wasUnread) _updateBadges(Math.max(0, _getUnreadCount() - 1));
        if (key) _notifPost('equipment-booking/api/notif-delete.php', key);
        showToast('Notification deleted.');
    }

    function _showEmptyState() {
        const list = document.getElementById('notifList');
        if (!list || document.getElementById('notifEmptyState')) return;
        const div = document.createElement('div');
        div.className = 'ps-empty-state notif-empty-state';
        div.id = 'notifEmptyState';
        div.innerHTML = '<span class="material-symbols-outlined">notifications_off</span><p>You\'re all caught up. No notifications right now.</p>';
        list.appendChild(div);
    }

    /* ── Navigate to the screen a notification is about ─────────
       Different categories go different places on purpose:
         overdue  → Equipment > On Loan, Overdue filter, flash the row
         request  → Equipment > On Loan, Waiting filter, opens the exact
                     request's detail modal (same one the table uses)
         room     → Rooms tab, Issues sub-tab, opens the exact
                     issue's review modal
         system   → Inventory tab, scrolls to + flashes that item  */
    function _notifGoto(card) {
        const tab = card.dataset.linkTab;
        if (!tab) return;
        _markCardRead(card);
        psCloseModal('notifOverlay');

        if (tab === 'rooms') {
            _switchTabDOM('rooms');
            if (card.dataset.linkSub) switchRoomsTab(card.dataset.linkSub);

            const issueId = card.dataset.linkIssueId;
            if (!issueId) return;
            setTimeout(() => {
                const reviewBtn = document.querySelector('[data-action="open-issue-review"][data-issue-id="' + issueId + '"]');
                if (reviewBtn) {
                    reviewBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const row = reviewBtn.closest('tr');
                    if (row) {
                        row.classList.add('notif-target-flash');
                        setTimeout(() => row.classList.remove('notif-target-flash'), 1800);
                    }
                    setTimeout(() => reviewBtn.click(), 280);
                }
            }, 120);
        } else if (tab === 'inventory') {
            _switchTabDOM('inventory');
            if (card.dataset.linkSub === 'eq-onloan') {
                setTimeout(() => {
                    const loanPanel = document.querySelector('#eqTabs .rq-filter-chip[data-eq-panel="eq-onloan"]');
                    if (loanPanel) loanPanel.click();
                    const filter = document.querySelector('#eqLoanFilter [data-loan-filter="' + card.dataset.linkChip + '"]');
                    if (filter) filter.click();

                    const requestId = card.dataset.linkRequestId;
                    if (!requestId) return;
                    const row = document.querySelector('#eq-loan-table tr.eq-loan-row[data-id="' + requestId + '"]');
                    if (!row) return;
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.classList.add('notif-target-flash');
                    setTimeout(() => row.classList.remove('notif-target-flash'), 1800);
                    const trigger = row.querySelector('[data-modal="ps-req-detail-modal"]');
                    if (card.dataset.linkChip === 'waiting' && trigger) setTimeout(() => trigger.click(), 280);
                }, 120);
                return;
            }
            const itemId = card.dataset.linkItemId;
            if (!itemId) return;
            setTimeout(() => {
                if (typeof window.psInventoryRevealRow === 'function') window.psInventoryRevealRow(itemId);
                const row = document.querySelector('.inv-row-item[data-item-id="' + itemId + '"]');
                if (!row) return;
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                row.classList.add('notif-target-flash');
                setTimeout(() => row.classList.remove('notif-target-flash'), 1800);
            }, 120);
        }
    }

    function initNotifCards() {
        document.querySelectorAll('.notif-card').forEach(card => {
            if (card.dataset.notifWired) return;
            card.dataset.notifWired = '1';

            const mainRow = card.querySelector('.notif-card-main');
            if (mainRow) {
                mainRow.addEventListener('click', (e) => {
                    if (_anSelectMode) { _anToggleSelect(card, e.shiftKey); return; }
                    const isExpanded = card.classList.contains('expanded');
                    document.querySelectorAll('#notifList .notif-card.expanded').forEach(c => c.classList.remove('expanded'));
                    if (!isExpanded) {
                        card.classList.add('expanded');
                        _markCardRead(card);
                    }
                });
                mainRow.addEventListener('keydown', e => {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); mainRow.click(); }
                });
            }
            const gotoBtn = card.querySelector('[data-notif-goto]');
            if (gotoBtn) {
                gotoBtn.addEventListener('click', e => { e.stopPropagation(); _notifGoto(card); });
            }
            const toggleBtn = card.querySelector('[data-notif-toggle-read]');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', e => {
                    e.stopPropagation();
                    // currently unread -> mark read; currently read -> mark unread
                    _anSetRead([card.dataset.notifKey], card.classList.contains('unread'), true);
                });
            }
            const deleteBtn = card.querySelector('[data-notif-delete]');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', e => { e.stopPropagation(); _deleteCard(card); });
            }
        });
    }

    function filterNotifs(cat, keepSelection) {
        // A selection / pending delete must never silently follow you to a different pill.
        if (!keepSelection) {
            _anSelected.clear();
            _anLastAnchor = null;
            _anPending = null;
        }
        document.querySelectorAll('.notif-filter-chips .rq-filter-chip').forEach(t => t.classList.remove('active'));
        const btn = document.querySelector('.notif-filter-chips .rq-filter-chip[data-notif-filter="' + cat + '"]');
        if (btn) btn.classList.add('active');
        else {
            // The active filter's chip vanished (its category emptied out
            // since it was picked) — fall back to "All" rather than showing
            // a stuck, unlabeled filter.
            cat = 'all';
            const allBtn = document.querySelector('.notif-filter-chips .rq-filter-chip[data-notif-filter="all"]');
            if (allBtn) allBtn.classList.add('active');
        }

        let anyCardVisible = false;
        document.querySelectorAll('#notifList .notif-item').forEach(item => {
            let show;
            if (cat === 'all') show = true;
            else if (cat === 'unread') show = item.classList.contains('unread');
            else show = item.dataset.cat === cat;
            item.style.display = show ? '' : 'none';
            if (show) anyCardVisible = true;
        });
        document.querySelectorAll('#notifList .notif-group-label').forEach(label => {
            let anyVisible = false;
            let sib = label.nextElementSibling;
            while (sib && sib.classList.contains('notif-item')) {
                if (sib.style.display !== 'none') anyVisible = true;
                sib = sib.nextElementSibling;
            }
            label.style.display = anyVisible ? '' : 'none';
        });

        const filterEmpty = document.getElementById('notifFilterEmptyState');
        const hasAnyCardAtAll = !!document.querySelector('#notifList .notif-item');
        if (filterEmpty) filterEmpty.style.display = (hasAnyCardAtAll && !anyCardVisible) ? '' : 'none';
        _anRefreshChrome();
    }

    /* Keep the filter pills honest against live data after a poll. (Every pill
       is always rendered now, so this only has to refresh the numbers.) */
    function _syncFilterChips() {
        _updateChipCounts();
    }

    function markAllRead() {
        document.querySelectorAll('#notifList .notif-item.unread').forEach(item => _anApplyReadState(item, true));
        _updateBadges(0);
        _updateChipCounts();
        _anReapplyFilter();
        _notifPost('equipment-booking/api/notif-mark-all-read.php');
        showToast('All notifications marked as read.');
    }

    /* ▼ NOTIF-SELECT-BEGIN ───────────────────────────────────────────────────
       Select · Select all · Mark read / unread · Delete selected · Delete all
       Same behaviour as the faculty Notifications modal, working on the
       server-rendered cards above. Bulk writes go to api/notif-bulk.php. */
    function _anPlural(n, word) { return n + ' ' + word + (n === 1 ? '' : 's'); }

    function _anCards() {
        return Array.from(document.querySelectorAll('#notifList .notif-item[data-notif-key]:not(.notif-removing)'));
    }

    function _anActiveFilter() {
        const a = document.querySelector('.notif-filter-chips .rq-filter-chip.active');
        return a ? a.dataset.notifFilter : 'all';
    }

    /* The cards the current pill is showing (muted categories never count). */
    function _anMatches() {
        const f = _anActiveFilter();
        const muted = _anMutedCats();
        return _anCards().filter(c => {
            if (muted.indexOf(c.dataset.cat) !== -1) return false;
            if (f === 'all') return true;
            if (f === 'unread') return c.classList.contains('unread');
            return c.dataset.cat === f;
        });
    }

    function _anReapplyFilter() { filterNotifs(_anActiveFilter(), true); }

    function _anBulk(action, keys) {
        const body = new URLSearchParams();
        body.set('action', action);
        body.set('csrf_token', getCsrfToken());
        keys.forEach(k => body.append('keys[]', k));
        _anBusy++;
        return fetch('equipment-booking/api/notif-bulk.php', { method: 'POST', body: body, credentials: 'same-origin' })
            .then(r => r.json().catch(() => ({})).then(j => {
                if (!r.ok || j.status !== 'success') throw new Error(j.message || j.error || 'Request failed');
                return j;
            }))
            .finally(() => { _anBusy--; });
    }

    function _anApplyReadState(card, read) {
        card.classList.toggle('unread', !read);
        const dot = card.querySelector('.unread-dot');
        if (dot) dot.style.display = '';
        const btn = card.querySelector('[data-notif-toggle-read]');
        if (btn) {
            const ic = btn.querySelector('.material-symbols-outlined');
            if (ic) ic.textContent = read ? 'mark_email_unread' : 'drafts';
            const lb = btn.querySelector('[data-notif-toggle-label]');
            if (lb) lb.textContent = read ? 'Mark as unread' : 'Mark as read';
        }
    }

    /* Optimistic: the list updates instantly, the server follows. */
    function _anSetRead(keys, read) {
        const cards = _anCards().filter(c => keys.indexOf(c.dataset.notifKey) !== -1 && c.classList.contains('unread') === read);
        if (!cards.length) return Promise.resolve(true);
        cards.forEach(c => _anApplyReadState(c, read));
        _updateBadges(_getUnreadCount());
        _updateChipCounts();
        _anReapplyFilter();
        return _anBulk(read ? 'mark_read' : 'mark_unread', cards.map(c => c.dataset.notifKey))
            .then(() => true)
            .catch(() => {
                showToast('Could not update notifications. Please try again.');
                _notifPoll();
                return false;
            });
    }

    function _anDelete(keys) {
        const doomed = _anCards().filter(c => keys.indexOf(c.dataset.notifKey) !== -1);
        if (!doomed.length) return Promise.resolve(true);
        const gone = new Set(doomed.map(c => c.dataset.notifKey));
        const unreadLeft = _anCards().filter(c => !gone.has(c.dataset.notifKey) && c.classList.contains('unread')).length;
        gone.forEach(k => _anSelected.delete(k));
        doomed.forEach(c => _deleteCardSilent(c));
        _updateBadges(unreadLeft);
        _updateChipCounts();
        return _anBulk('delete', Array.from(gone))
            .then(() => {
                showToast(gone.size === 1 ? 'Notification deleted.' : _anPlural(gone.size, 'notification') + ' deleted.');
                return true;
            })
            .catch(() => {
                showToast('Could not delete. Please try again.');
                _notifPoll();
                return false;
            });
    }

    function _anToggleSelect(card, range) {
        const key = card.dataset.notifKey;
        if (range && _anLastAnchor) {
            const list = _anMatches();
            const a = list.findIndex(c => c.dataset.notifKey === _anLastAnchor);
            const b = list.findIndex(c => c.dataset.notifKey === key);
            if (a > -1 && b > -1) {
                for (let i = Math.min(a, b); i <= Math.max(a, b); i++) _anSelected.add(list[i].dataset.notifKey);
                _anRefreshChrome();
                return;
            }
        }
        if (_anSelected.has(key)) _anSelected.delete(key); else _anSelected.add(key);
        _anLastAnchor = key;
        _anRefreshChrome();
    }

    function _anEnterSelect() {
        _anSelectMode = true;
        _anPending = null;
        document.querySelectorAll('#notifList .notif-card.expanded').forEach(c => c.classList.remove('expanded'));
        _anRefreshChrome();
    }

    function _anExitSelect() {
        _anSelectMode = false;
        _anSelected.clear();
        _anLastAnchor = null;
        _anPending = null;
        _anRefreshChrome();
    }

    function _anSelectAll() {
        const list = _anMatches();
        const all = list.length > 0 && list.every(c => _anSelected.has(c.dataset.notifKey));
        list.forEach(c => { if (all) _anSelected.delete(c.dataset.notifKey); else _anSelected.add(c.dataset.notifKey); });
        _anRefreshChrome();
    }

    function _anAskDelete(keys, text) {
        if (!keys.length) return;
        _anPending = { keys: keys.slice(), text: text };
        _anRefreshChrome();
    }

    /* Everything outside the cards that depends on the data: footer buttons,
       selection bar, confirm strip, row checkboxes. Cheap -- safe to call often. */
    function _anRefreshChrome() {
        const list = document.getElementById('notifList');
        if (!list) return;
        const muted = _anMutedCats();
        const cards = _anCards().filter(c => muted.indexOf(c.dataset.cat) === -1);

        if (_anSelectMode && cards.length === 0) { _anSelectMode = false; _anSelected.clear(); _anPending = null; }
        const live = new Set(_anCards().map(c => c.dataset.notifKey));
        Array.from(_anSelected).forEach(k => { if (!live.has(k)) _anSelected.delete(k); });

        const matches = _anMatches();
        const matchKeys = new Set(matches.map(c => c.dataset.notifKey));
        const selCount = matches.filter(c => _anSelected.has(c.dataset.notifKey)).length;

        list.classList.toggle('is-selecting', _anSelectMode);
        _anCards().forEach(c => {
            const on = _anSelectMode && _anSelected.has(c.dataset.notifKey) && matchKeys.has(c.dataset.notifKey);
            c.classList.toggle('is-selected', on);
            const row = c.querySelector('.notif-card-main');
            if (row) {
                if (_anSelectMode) {
                    row.setAttribute('role', 'checkbox');
                    row.setAttribute('aria-checked', on ? 'true' : 'false');
                } else {
                    row.setAttribute('role', 'button');
                    row.removeAttribute('aria-checked');
                }
            }
        });

        const selectBtn = document.getElementById('notifSelectBtn');
        if (selectBtn) {
            selectBtn.disabled = !_anSelectMode && cards.length === 0;
            selectBtn.setAttribute('aria-pressed', _anSelectMode ? 'true' : 'false');
            const lbl = document.getElementById('notifSelectLbl');
            if (lbl) lbl.textContent = _anSelectMode ? 'Done' : 'Select';
            const ic = selectBtn.querySelector('.material-symbols-outlined');
            if (ic) ic.textContent = _anSelectMode ? 'close' : 'checklist';
        }
        const delAll = document.getElementById('notifDeleteAllBtn');
        if (delAll) {
            delAll.hidden = !_anSelectMode;
            delAll.disabled = matches.length === 0;
            const tail = delAll.lastChild;
            if (tail && tail.nodeType === 3) tail.nodeValue = ' Delete all' + (_anActiveFilter() === 'all' ? '' : ' (' + matches.length + ')');
        }
        const selBar = document.getElementById('notifSelBar');
        if (selBar) selBar.hidden = !_anSelectMode;
        const selCountEl = document.getElementById('notifSelCount');
        if (selCountEl) selCountEl.textContent = selCount + ' selected';
        const selAll = document.getElementById('notifSelectAll');
        if (selAll) {
            selAll.checked = matches.length > 0 && selCount === matches.length;
            selAll.indeterminate = selCount > 0 && selCount < matches.length;
            selAll.disabled = matches.length === 0;
        }
        document.querySelectorAll('#notifSelBar [data-needs-sel]').forEach(b => { b.disabled = selCount === 0; });

        const conf = document.getElementById('notifConfirm');
        if (conf) {
            conf.hidden = !_anPending;
            const ct = document.getElementById('notifConfirmText');
            if (ct && _anPending) ct.textContent = _anPending.text;
        }
    }

    (function initNotifSelect() {
        const overlay = document.getElementById('notifOverlay');
        if (!overlay) return;

        overlay.addEventListener('click', function (e) {
            const b = e.target.closest('[data-nbar]');
            if (!b || b.disabled) return;
            const matches = _anMatches();
            const selKeys = matches.filter(c => _anSelected.has(c.dataset.notifKey)).map(c => c.dataset.notifKey);
            switch (b.dataset.nbar) {
                case 'select':
                    if (_anSelectMode) _anExitSelect(); else _anEnterSelect();
                    break;
                case 'mark-read':
                    _anSetRead(selKeys, true).then(ok => { if (ok) showToast(_anPlural(selKeys.length, 'notification') + ' marked as read.'); });
                    break;
                case 'mark-unread':
                    _anSetRead(selKeys, false).then(ok => { if (ok) showToast(_anPlural(selKeys.length, 'notification') + ' marked as unread.'); });
                    break;
                case 'delete-selected':
                    _anAskDelete(selKeys, 'Delete ' + (selKeys.length === 1 ? 'the selected notification' : selKeys.length + ' selected notifications') + '? This can\u2019t be undone.');
                    break;
                case 'delete-all': {
                    const keys = matches.map(c => c.dataset.notifKey);
                    const f = _anActiveFilter();
                    const scope = f === 'all' ? '' : ' ' + f;
                    _anAskDelete(keys, 'Delete all ' + (keys.length === 1 ? '1' + scope + ' notification' : keys.length + scope + ' notifications') + '? This can\u2019t be undone.');
                    break;
                }
                case 'confirm-cancel':
                    _anPending = null;
                    _anRefreshChrome();
                    break;
                case 'confirm-ok': {
                    if (!_anPending) break;
                    const keys = _anPending.keys;
                    _anPending = null;
                    _anRefreshChrome();
                    _anDelete(keys);
                    break;
                }
            }
        });

        const selAllBox = document.getElementById('notifSelectAll');
        if (selAllBox) selAllBox.addEventListener('change', _anSelectAll);

        // Shift-click selects a range -- don't start a text selection while doing it
        const listEl = document.getElementById('notifList');
        if (listEl) {
            listEl.addEventListener('mousedown', function (e) { if (e.shiftKey && _anSelectMode) e.preventDefault(); });
        }

        // Esc: close the delete confirmation first, then leave Select mode, before anything else sees it
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || !overlay.classList.contains('ps-modal-open')) return;
            if (_anPending) { e.preventDefault(); e.stopPropagation(); _anPending = null; _anRefreshChrome(); }
            else if (_anSelectMode) { e.preventDefault(); e.stopPropagation(); _anExitSelect(); }
        }, true);

        // However the modal gets closed (X, backdrop, navigating to a notification), leave Select mode behind
        if (typeof MutationObserver === 'function') {
            new MutationObserver(function () {
                if (!overlay.classList.contains('ps-modal-open') && (_anSelectMode || _anPending || _anSelected.size)) _anExitSelect();
            }).observe(overlay, { attributes: true, attributeFilter: ['class'] });
        }

        _anRefreshChrome();
    })();
    /* ▲ NOTIF-SELECT-END ──────────────────────────────────────────────────── */

    /* ── Poll for genuinely new notifications (new request submitted,
       item went overdue, room issue reported, stock dropped, etc.)
       without a full page reload. Only adds/removes cards + updates
       badges; never disturbs a card the admin has expanded. ── */
    function _buildNotifCardEl(n) {
        const wrap = document.createElement('div');
        wrap.className = 'notif-item notif-card notif-fresh' + (n.urgent ? ' notif-urgent' : '') + (n.is_read ? '' : ' unread');
        wrap.dataset.cat = n.cat;
        wrap.dataset.notifKey = n.key;
        wrap.dataset.linkTab = (n.link && n.link.tab) || '';
        wrap.dataset.linkChip = (n.link && n.link.chip) || '';
        wrap.dataset.linkSub = (n.link && n.link.sub) || '';
        wrap.dataset.linkRequestId = (n.link && n.link.request_id) || '';
        wrap.dataset.linkIssueId = (n.link && n.link.issue_id) || '';
        wrap.dataset.linkItemId = (n.link && n.link.item_id) || '';

        const detailRows = (n.detail || []).map(d =>
            '<div class="ps-detail-item"><div class="ps-detail-label">' + _esc(d.label) +
            '</div><div class="ps-detail-value"' + (d.danger ? ' style="color:var(--danger);font-weight:700;"' : '') +
            '>' + _esc(String(d.value)) + '</div></div>'
        ).join('');

        wrap.innerHTML =
            '<div class="notif-card-main" role="button" tabindex="0">' +
            '<span class="anotif-check" aria-hidden="true"><span class="material-symbols-outlined">check</span></span>' +
            '<div class="notif-icon ' + _esc(n.icon_class) + '"><span class="material-symbols-outlined">' + _esc(n.icon) + '</span></div>' +
            '<div class="notif-body-wrap"><h4>' + _esc(n.title) + '</h4><p>' + n.body + '</p></div>' +
            '<div class="notif-meta"><span class="notif-time">' + _esc(n.time_label) + '</span><div class="unread-dot"></div>' +
            '<span class="material-symbols-outlined notif-chevron">expand_more</span></div>' +
            '</div>' +
            '<div class="notif-card-detail"><div class="ps-detail-grid">' + detailRows + '</div>' +
            '<div class="notif-card-actions">' +
            '<button type="button" class="ps-btn ps-btn--primary ps-btn--sm" data-notif-goto><span class="material-symbols-outlined">visibility</span>' + _esc(n.view_label) + '</button>' +
            '<button type="button" class="ps-btn ps-btn--ghost ps-btn--sm notif-toggle-read-btn" data-notif-toggle-read><span class="material-symbols-outlined">' + (n.is_read ? 'mark_email_unread' : 'drafts') + '</span><span data-notif-toggle-label>' + (n.is_read ? 'Mark as unread' : 'Mark as read') + '</span></button>' +
            '<button type="button" class="ps-btn ps-btn--ghost ps-btn--sm notif-delete-btn" data-notif-delete title="Delete notification"><span class="material-symbols-outlined">delete</span>Delete</button>' +
            '</div></div>';
        return wrap;
    }

    function _esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : s;
        return d.innerHTML;
    }

    function _notifPoll() {
        // Don't reshuffle the list under the admin's hands: pause while a bulk
        // action is in flight or a delete confirmation is open.
        if (_anBusy > 0 || _anPending) return;
        fetch('equipment-booking/api/notif-list.php')
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data || data.status !== 'success') return;
                const list = document.getElementById('notifList');
                if (!list) return;

                const serverKeys = new Set(data.notifications.map(n => n.key));
                const domCards = list.querySelectorAll('.notif-item[data-notif-key]');

                // Remove cards for notifications that no longer exist server-side
                // (e.g. the request was approved/declined by another admin tab,
                // or the item's stock was replenished) — but never touch one the
                // admin currently has expanded, mid-review.
                domCards.forEach(card => {
                    if (!serverKeys.has(card.dataset.notifKey) && !card.classList.contains('expanded')) {
                        _deleteCardSilent(card);
                    }
                });

                // Add cards for genuinely new notifications, newest group-appropriate spot.
                const existingKeys = new Set(Array.from(list.querySelectorAll('.notif-item[data-notif-key]')).map(c => c.dataset.notifKey));
                let addedAny = false;
                data.notifications.forEach(n => {
                    if (existingKeys.has(n.key)) return;
                    addedAny = true;
                    const el = _buildNotifCardEl(n);
                    const groupLabel = Array.from(list.querySelectorAll('.notif-group-label')).find(g => g.textContent.trim().indexOf(n.group_label) === 0);
                    if (groupLabel) {
                        groupLabel.insertAdjacentElement('afterend', el);
                    } else {
                        const div = document.createElement('div');
                        div.className = 'notif-group-label' + (n.group_danger ? ' notif-group-label--danger' : '');
                        div.innerHTML = '<span class="material-symbols-outlined">' + _esc(n.group_icon) + '</span>' + _esc(n.group_label);
                        list.insertBefore(div, list.firstChild);
                        div.insertAdjacentElement('afterend', el);
                    }
                });
                _syncFilterChips(data.notifications);
                if (addedAny) {
                    const empty = document.getElementById('notifEmptyState');
                    if (empty) empty.remove();
                    initNotifCards();
                }
                const activeFilter = document.querySelector('.notif-filter-chips .rq-filter-chip.active');
                filterNotifs(activeFilter ? activeFilter.dataset.notifFilter : 'all', true);

                _updateBadges(data.unread_count);
            })
            .catch(() => { /* silent — polling shouldn't be noisy on network hiccups */ });
    }

    function _deleteCardSilent(card) {
        card.classList.add('notif-removing');
        setTimeout(() => {
            const group = card.previousElementSibling && card.previousElementSibling.classList.contains('notif-group-label')
                ? card.previousElementSibling : null;
            card.remove();
            if (group) {
                const next = group.nextElementSibling;
                if (!next || !next.classList.contains('notif-item')) group.remove();
            }
            if (!document.querySelector('#notifList .notif-item')) _showEmptyState();
            _updateChipCounts();
        }, 220);
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
                    // appear on this page (sidebar, hero, greeting) without
                    // requiring a reload.
                    document.querySelectorAll('.u-name, .dd-name, .nav-account-name, .ov-hero-name').forEach(el => { el.textContent = data.admin_name; });
                    const greetEl = document.getElementById('greetName');
                    if (greetEl) greetEl.textContent = (data.admin_name || '').split(' ')[0] || greetEl.textContent;
                    const initials = _computeInitials(data.admin_name);
                    if (initials) {
                        document.querySelectorAll('.nav-account-avatar, .dd-avatar, #dashAvatarBtn').forEach(el => { el.textContent = initials; });
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

    /* ── Equipment list: client-side search + pagination ─────────
       NOTE: this intentionally does NOT use setupLiveSearch(). That
       helper replaces the container's innerHTML with whatever
       live-search.php's ?section=inventory case returns (old
       Bootstrap markup), which garbled the list. Instead every
       <tr.inv-row-item> is rendered once by PHP and this function only
       shows/hides them: filter by the search box, then show 10 rows
       per page. Nothing is ever replaced, so there's nothing to garble. */
    function setupInventoryList() {
        const PAGE_SIZE = 10;
        const body = document.getElementById('inventory-body');
        if (!body) return;

        const input = document.getElementById('inventorySearch');
        const rows = Array.from(body.querySelectorAll('tr.inv-row-item'));
        const noMatch = document.getElementById('inv-nomatch-row');
        const pager = document.getElementById('inv-pager');
        const info = document.getElementById('inv-pager-info');
        const ctl = document.getElementById('inv-pager-ctl');
        const nums = document.getElementById('inv-pg-nums');
        const prevBtn = document.getElementById('inv-pg-prev');
        const nextBtn = document.getElementById('inv-pg-next');
        let query = '';
        let page = 1;

        // Thumbnail fallback (replaces the old inline onerror handler).
        rows.forEach(function (row) {
            const img = row.querySelector('.inv-thumb img');
            if (!img) return;
            const thumb = img.closest('.inv-thumb');
            const broken = function () { if (thumb) thumb.classList.add('is-broken'); };
            img.addEventListener('error', broken);
            if (img.complete && img.naturalWidth === 0) broken();
        });

        function matches(row) {
            if (!query) return true;
            const d = row.dataset;
            return [d.itemName, d.itemCategory, d.itemCondition].some(function (v) {
                return (v || '').toLowerCase().includes(query);
            });
        }

        // 1 … 4 5 6 … 12 — always first, last and the current page ±1.
        function pageList(total, current) {
            const keep = new Set([1, total, current - 1, current, current + 1]);
            const list = [];
            let last = 0;
            Array.from(keep).filter(function (n) { return n >= 1 && n <= total; })
                .sort(function (x, y) { return x - y; })
                .forEach(function (n) {
                    if (n - last > 1) list.push('gap');
                    list.push(n);
                    last = n;
                });
            return list;
        }

        function render() {
            const hits = rows.filter(matches);
            const total = hits.length;
            const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
            page = Math.min(Math.max(page, 1), pages);
            const start = (page - 1) * PAGE_SIZE;
            const end = Math.min(start + PAGE_SIZE, total);
            const visible = new Set(hits.slice(start, end));

            rows.forEach(function (row) {
                row.classList.toggle('inv-row-hidden', !visible.has(row));
            });
            if (noMatch) noMatch.classList.toggle('inv-row-hidden', !(rows.length > 0 && total === 0));

            if (!pager) return;
            pager.hidden = rows.length === 0;
            if (info) {
                info.textContent = total
                    ? 'Showing ' + (start + 1) + '\u2013' + end + ' of ' + total
                    : 'No results';
            }
            if (ctl) ctl.hidden = pages <= 1;
            if (prevBtn) prevBtn.disabled = page <= 1;
            if (nextBtn) nextBtn.disabled = page >= pages;
            if (nums) {
                nums.textContent = '';
                pageList(pages, page).forEach(function (n) {
                    if (n === 'gap') {
                        const gap = document.createElement('span');
                        gap.className = 'inv-pager-gap';
                        gap.textContent = '\u2026';
                        nums.appendChild(gap);
                        return;
                    }
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'inv-pager-btn' + (n === page ? ' is-active' : '');
                    btn.dataset.page = String(n);
                    btn.textContent = String(n);
                    btn.setAttribute('aria-label', 'Page ' + n);
                    if (n === page) btn.setAttribute('aria-current', 'page');
                    nums.appendChild(btn);
                });
            }
        }

        if (input) {
            input.addEventListener('input', function () {
                query = this.value.trim().toLowerCase();
                page = 1;
                render();
            });
        }
        if (prevBtn) prevBtn.addEventListener('click', function () { page--; render(); });
        if (nextBtn) nextBtn.addEventListener('click', function () { page++; render(); });
        if (nums) {
            nums.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-page]');
                if (!btn) return;
                page = parseInt(btn.dataset.page, 10) || 1;
                render();
            });
        }

        // Lets notification deep-links open the page that holds a given item
        // (clears any search so the row is guaranteed to be listed).
        window.psInventoryRevealRow = function (itemId) {
            query = '';
            if (input) input.value = '';
            const pos = rows.findIndex(function (r) { return r.dataset.itemId === String(itemId); });
            if (pos < 0) return null;
            page = Math.floor(pos / PAGE_SIZE) + 1;
            render();
            return rows[pos];
        };

        render();
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
                    _closeMobileSidebar();
                    break;

                case 'open-account-settings': {
                    // Dashboard header avatar → Settings (My Account). The sidebar no longer has a
                    // Settings item, so switch the tab directly.
                    _switchTabDOM('settings', null);
                    break;
                }

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
                    // Arbitration Log) that's since been removed. Request
                    // handling now lives under Equipment > On Loan.
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
                        // "waiting" (Review Requests / View All) — show pending
                        // requests in Equipment > On Loan.
                        _switchTabDOM('inventory');
                        const eqTab = document.querySelector('#eqTabs .rq-filter-chip[data-eq-panel="eq-onloan"]');
                        if (eqTab) eqTab.click();
                        const filter = document.querySelector('#eqLoanFilter [data-loan-filter="waiting"]');
                        if (filter) filter.click();
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
                    // Drop any ?edit_item= param from the URL without reloading.
                    const editParam = new URLSearchParams(window.location.search).get('edit_item');
                    if (editParam) {
                        const p2 = new URLSearchParams(window.location.search);
                        p2.delete('edit_item');
                        const qs2 = p2.toString();
                        history.replaceState({ tab: 'inventory' }, '',
                            window.location.pathname + (qs2 ? '?' + qs2 : '') + window.location.hash);
                    }
                    _exitEditMode();
                    const fw = document.getElementById('item-form-wrap');
                    if (fw) fw.classList.add('hidden');
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
                        const p3 = new URLSearchParams(window.location.search);
                        p3.delete('edit_room');
                        const qs3 = p3.toString();
                        history.replaceState({ tab: 'rooms' }, '',
                            window.location.pathname + (qs3 ? '?' + qs3 : '') + window.location.hash);
                    }
                    const rfw = document.getElementById('room-form-wrap');
                    const addRoomBtn = document.getElementById('addRoomBtn');
                    if (rfw) rfw.classList.add('hidden');
                    if (addRoomBtn) addRoomBtn.style.display = '';
                    if (window.psResetRoomForm) window.psResetRoomForm();
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
                    e.preventDefault();
                    _closeMobileSidebar();
                    if (window.PSLogout) window.PSLogout.open(el);
                    else if (confirm('Confirm Logout?')) window.location.href = 'api/logout.php'; // fallback only if logout-modal.js failed to load
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

    /* The faculty-list row is rendered in ONE place so the create form and the edit
       modal produce rows identical to the ones the server prints (admin-dashboard.php).
       An id starting with NOTSET- is the internal placeholder for "no Faculty ID yet"
       (see config/faculty-id.php) and is shown as "Not set yet". */
    window.psFacRender = function (tr, d) {
        const isAdviser = d.role === 'Organization Adviser';
        const idUnset = !d.faculty_id || String(d.faculty_id).indexOf('NOTSET-') === 0;
        const init = ((d.fullname || 'F').trim().charAt(0) || 'F').toUpperCase();

        tr.dataset.fullname = d.fullname || '';
        tr.dataset.email = d.email || '';
        tr.dataset.backupEmail = d.backup_email || '';
        tr.dataset.facultyId = d.faculty_id || '';
        tr.dataset.role = d.role || '';
        tr.dataset.org = d.org_name || '';
        tr.dataset.orgId = String(d.org_id || '0');
        tr.dataset.init = init;

        tr.innerHTML =
            '<td><div class="fac-person">' +
            '<span class="fac-avatar">' + _esc(init) + '</span>' +
            '<div class="fac-person-text">' +
            '<div class="fac-person-name">' + _esc(d.fullname) + '</div>' +
            '<div class="fac-person-sub">' + _esc(d.email) + '</div>' +
            '</div></div></td>' +
            '<td>' + (idUnset
                ? '<span class="fac-id-pending">Not set yet</span>'
                : '<span class="fac-id">' + _esc(d.faculty_id) + '</span>') + '</td>' +
            '<td>' + (isAdviser
                ? '<span class="fac-role fac-role--adviser">Org Adviser</span>' +
                (d.org_name ? '<div class="fac-person-sub">' + _esc(d.org_name) + '</div>' : '')
                : '<span class="fac-role">Faculty</span>') + '</td>' +
            '<td class="fac-actions"><button class="ps-btn ps-btn--ghost ps-btn--sm fac-edit-btn" aria-label="Edit account">' +
            '<span class="material-symbols-outlined">edit</span></button></td>';
        return tr;
    };

    /* ── Faculty: registry lookup ────────────────────────────── */
    const facLookupBtn = document.getElementById('fac-lookup-btn');
    const facLookupAlert = document.getElementById('fac-lookup-alert');
    const facDisambig = document.getElementById('fac-disambiguation');
    const facDisambigSel = document.getElementById('fac-disambig-select');

    /* "juan  dela cruz" -> "Juan Dela Cruz": every space- or hyphen-separated word starts with a capital
       (the server applies the same rule when the account is saved). */
    function _facCapitalizeName(v) {
        return String(v || '').replace(/\s+/g, ' ').trim()
            .replace(/(^|[\s-])(\p{L})/gu, function (m, sep, ch) { return sep + ch.toLocaleUpperCase(); });
    }
    // Reads both name fields, shows them capitalised in the form, and returns them.
    function _facReadNames() {
        var fEl = document.getElementById('fac-first');
        var lEl = document.getElementById('fac-last');
        var first = _facCapitalizeName(fEl ? fEl.value : '');
        var last = _facCapitalizeName(lEl ? lEl.value : '');
        if (fEl) fEl.value = first;
        if (lEl) lEl.value = last;
        return { first: first, last: last };
    }

    // Stores the currently confirmed registry match {faculty_id, email_preview, full_name}
    // null means no successful lookup yet — submit is blocked until set.
    let _facRegistryMatch = null;

    function _showLookupAlert(msg, isError) {
        if (!facLookupAlert) return;
        facLookupAlert.className = 'alert-banner ' + (isError ? 'alert-danger' : 'alert-success');
        facLookupAlert.textContent = msg;
        facLookupAlert.classList.remove('hidden');
    }
    function _clearLookupAlert() {
        if (!facLookupAlert) return;
        facLookupAlert.classList.add('hidden');
        facLookupAlert.textContent = '';
    }

    function _applyRegistryMatch(match) {
        _facRegistryMatch = match;
        const emailEl = document.getElementById('fac-email');
        const idEl = document.getElementById('fac-faculty-id');
        if (emailEl) emailEl.value = match.email_preview || '';
        if (idEl) idEl.value = match.faculty_id || '';
    }

    function _clearRegistryPreview() {
        _facRegistryMatch = null;
        const emailEl = document.getElementById('fac-email');
        const idEl = document.getElementById('fac-faculty-id');
        if (emailEl) emailEl.value = '';
        if (idEl) idEl.value = '';
        if (facDisambig) facDisambig.style.display = 'none';
        if (facDisambigSel) facDisambigSel.innerHTML = '';
        _clearLookupAlert();
    }

    // Clear the preview whenever name fields change
    ['fac-first', 'fac-last'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', _clearRegistryPreview);
        el.addEventListener('blur', _facReadNames); // tidy the capitals as soon as the admin leaves the field
    });

    // Disambiguation select → apply the chosen match
    if (facDisambigSel) {
        facDisambigSel.addEventListener('change', function () {
            try {
                const data = JSON.parse(this.value || 'null');
                if (data) _applyRegistryMatch(data);
            } catch (e) { }
        });
    }

    if (facLookupBtn) {
        facLookupBtn.addEventListener('click', function () {
            _clearRegistryPreview();
            const _nm = _facReadNames();
            const firstName = _nm.first;
            const lastName = _nm.last;
            if (!firstName || !lastName) {
                _showLookupAlert('Enter both first name and last name before looking up.', true);
                return;
            }

            facLookupBtn.disabled = true;
            facLookupBtn.innerHTML = '<span class="material-symbols-outlined" style="animation:spin 0.8s linear infinite">progress_activity</span> Looking up...';

            const body = new URLSearchParams({
                csrf_token: getCsrfToken(),
                first_name: firstName,
                last_name: lastName,
            });

            fetch('equipment-booking/api/lookup-faculty-registry.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'no_registry') {
                        // Registry not yet uploaded — show a soft notice but allow creation
                        _showLookupAlert(
                            'No faculty registry has been uploaded yet. Account will be created without ID validation.',
                            false
                        );
                        // Allow submit without a registry match
                        _facRegistryMatch = { faculty_id: '', email_preview: '' };
                        return;
                    }
                    if (data.status === 'not_found') {
                        _showLookupAlert(
                            firstName + ' ' + lastName + ' was not found in the faculty registry. ' +
                            'Check the spelling or upload an updated registry file.',
                            true
                        );
                        return;
                    }
                    if (data.status === 'found' && data.matches && data.matches.length === 1) {
                        const m = data.matches[0];
                        if (m.is_newly_hired) {
                            _showLookupAlert(
                                firstName + ' ' + lastName + ' is listed as newly hired and does not have a Faculty ID yet. ' +
                                'Upload an updated registry once their ID is assigned.',
                                true
                            );
                            return;
                        }
                        _applyRegistryMatch(m);
                        _showLookupAlert('Registry match found. Review the details below, then complete the form.', false);
                        return;
                    }
                    if (data.status === 'found' && data.matches && data.matches.length > 1) {
                        // Show disambiguation picker
                        if (facDisambig && facDisambigSel) {
                            facDisambigSel.innerHTML =
                                '<option value="">— Select the correct faculty member —</option>' +
                                data.matches.map(m =>
                                    '<option value=\'' + JSON.stringify(m).replace(/'/g, '&#39;') + '\'>' +
                                    m.full_name + (m.faculty_id ? ' (' + m.faculty_id + ')' : ' — Newly Hired') +
                                    '</option>'
                                ).join('');
                            facDisambig.style.display = '';
                            _showLookupAlert('Multiple matches found. Select the correct faculty member above.', false);
                        }
                        return;
                    }
                    _showLookupAlert('Unexpected response. Please try again.', true);
                })
                .catch(() => {
                    _showLookupAlert('Network error. Please try again.', true);
                })
                .finally(() => {
                    facLookupBtn.disabled = false;
                    facLookupBtn.innerHTML = '<span class="material-symbols-outlined">manage_search</span> Look up in Faculty Registry';
                });
        });
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

    /* After a successful create: show a small "Account created" overlay for a moment, then
       load the Faculty tab fresh so the form, the list and its count are all back to a clean
       state (the new account is simply part of the server-printed list).
       FAC_CREATED_OVERLAY_MS must match the .fac-created-bar animation (2s) in admin-dashboard.css. */
    const FAC_CREATED_OVERLAY_MS = 2000;
    let _facReloading = false;

    // Put the Create Account button back to its normal, clickable state.
    function _facResetSubmitBtn() {
        if (!facSubmitBtn) return;
        facSubmitBtn.disabled = false;
        facSubmitBtn.innerHTML = '<span class="material-symbols-outlined">person_add</span> Create Account';
    }

    function _facAccountCreated(fullname) {
        _facReloading = true;

        // Empty the form right away so no browser can restore typed values (the password
        // included) after the reload.
        ['fac-email', 'fac-faculty-id', 'fac-first', 'fac-last', 'fac-password', 'fac-confirm']
            .forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
        if (facAdviserChk) facAdviserChk.checked = false;
        _syncAdviserToggle();
        _clearFacAlert();
        _clearRegistryPreview();

        const ov = document.createElement('div');
        ov.className = 'fac-created-overlay';
        ov.setAttribute('role', 'status');
        ov.setAttribute('aria-live', 'polite');
        ov.innerHTML =
            '<div class="fac-created-card">' +
            '<span class="fac-created-icon"><span class="material-symbols-outlined">check</span></span>' +
            '<div class="fac-created-title">Account created</div>' +
            '<div class="fac-created-sub"></div>' +
            '<span class="fac-created-bar"></span>' +
            '</div>';
        ov.querySelector('.fac-created-sub').textContent = fullname; // text, never parsed as HTML
        document.body.appendChild(ov);

        setTimeout(function () {
            // Unlock the button first: the click handler's .finally() skips its reset while
            // _facReloading is true, so nothing else ever re-enables it. Doing it up front
            // also means an error further down can never leave it stuck on "Creating...".
            _facReloading = false;
            _facResetSubmitBtn();
            // Switch to Faculty tab in-place — no page reload needed.
            document.body.removeChild(ov);
            _switchTabDOM('faculty');
            // Reset the create-account form so it's blank for the next account.
            var form = document.getElementById('fac-create-form') || document.getElementById('facCreateForm');
            if (form) form.reset();
            // Refresh the faculty list from the server so the new account appears immediately.
            if (typeof window.adminRefreshFacultyList === 'function') window.adminRefreshFacultyList();
        }, FAC_CREATED_OVERLAY_MS);
    }

    if (facSubmitBtn) {
        facSubmitBtn.addEventListener('click', function () {
            _clearFacAlert();

            const email = (document.getElementById('fac-email')?.value || '').trim();
            const facultyId = (document.getElementById('fac-faculty-id')?.value || '').trim();
            const _nm = _facReadNames();
            const firstName = _nm.first;
            const lastName = _nm.last;
            const password = document.getElementById('fac-password')?.value || '';
            const confirm = document.getElementById('fac-confirm')?.value || '';
            const isAdviser = facAdviserChk?.checked ? '1' : '0';
            const orgId = facOrgSelect?.value || '';

            // Client-side pre-checks
            if (!_facRegistryMatch) {
                _showFacAlert('Please look up the faculty member in the registry first.', true); return;
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
                first_name: firstName,
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
                        _facAccountCreated([firstName, lastName].filter(Boolean).join(' '));
                    } else {
                        _showFacAlert(data.message || 'An error occurred.', true);
                    }
                })
                .catch(() => {
                    _showFacAlert('Network error. Please try again.', true);
                })
                .finally(() => {
                    if (_facReloading) return; // stay locked until the "Account created" overlay finishes
                    _facResetSubmitBtn();
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

    /* ── Account tab (sidebar footer): expands / collapses inline ── */
    const navAccountToggle = document.getElementById('navAccountToggle');
    if (navAccountToggle) {
        navAccountToggle.addEventListener('click', function () {
            setAccountMenuOpen(navAccountToggle.getAttribute('aria-expanded') !== 'true');
        });
    }

    /* ── Sidebar nav items ────────────────────────────────────── */
    document.querySelectorAll('.nav-item[data-tab]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            _switchTabDOM(this.dataset.tab, this);
        });
    });

    /* ── Mobile sidebar drawer: hamburger toggle + backdrop + Esc ── */
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = document.body.classList.toggle('sidebar-open');
            sidebarToggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', _closeMobileSidebar);
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') _closeMobileSidebar();
    });

    /* ── Desktop sidebar collapse (icon-rail): the toggle in the sidebar's
       brand row. Independent of the mobile drawer above — this
       only resizes the always-inline desktop sidebar; #app-main
       (flex:1) naturally expands into the freed width. Preference
       persists across reloads via localStorage. ── */
    const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
    function _setSidebarCollapsed(collapsed) {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        if (sidebarCollapseBtn) {
            sidebarCollapseBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            sidebarCollapseBtn.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
        }
        LS.set('sidebarCollapsed', collapsed ? '1' : '0');
    }
    if (sidebarCollapseBtn) {
        sidebarCollapseBtn.addEventListener('click', function () {
            if (window.innerWidth <= 768) { _closeMobileSidebar(); return; }   // phones: close the drawer
            if (window.innerWidth <= 1024) return;                             // tablets: always an icon rail
            _setSidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'));
        });
        // Restore the person's last preference on load.
        if (LS.get('sidebarCollapsed') === '1') _setSidebarCollapsed(true);
    }

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

    /* ▼ ADMIN-ACCOUNTS-PANEL-BEGIN ───────────────────────────────────────────
       Sidebar → Admins (Super Admin only).
       Left: list of admin accounts. Right: the selected admin's details,
       actions (edit · lock · unlock · delete) and activity history.
       Talks to api/manage-admin-account.php, api/create-admin-account.php and
       api/admin-activity.php; the server re-checks every rule. */

    const ADM_API = 'equipment-booking/api/manage-admin-account.php';
    const ADM_CREATE_API = 'equipment-booking/api/create-admin-account.php';
    const ADM_ACTIVITY_API = 'equipment-booking/api/admin-activity.php';
    const ADM_MAX_DAYS = 30;
    const ADM_TZ = 'Asia/Manila';

    function admEl(tag, cls, text) {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined) n.textContent = text;
        return n;
    }

    /* Hide every password field inside `root` again and put the eye buttons back to
       their initial icon, so a revealed password never stays on screen the next time
       a dialog is shown. */
    function admResetPwEyes(root) {
        if (!root) return;
        root.querySelectorAll('.fac-pw-toggle').forEach(function (btn) {
            (btn.dataset.target || '').split(',').forEach(function (id) {
                const inp = document.getElementById(id.trim());
                if (inp) inp.type = 'password';
            });
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:17px">visibility</span>';
        });
    }

    function admIcon(name) {
        const s = admEl('span', 'material-symbols-outlined', name);
        s.setAttribute('aria-hidden', 'true');
        return s;
    }

    /* "Oct 7" — day shown in the status tag (campus timezone) */
    function admShortDate(ts) {
        return new Date(ts * 1000).toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: ADM_TZ });
    }

    /* "Oct 7, 2026, 3:00 PM" */
    function admLongDate(ts) {
        return new Date(ts * 1000).toLocaleString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', timeZone: ADM_TZ
        });
    }

    /* ── Shared activity timeline ─────────────────────────────────
       One renderer for the Activity page and the per-admin timeline in the
       Admins panel. Both read api/admin-activity.php, so they always agree. */
    function actDayKey(ts) {
        return new Date(ts * 1000).toLocaleDateString('en-CA', { timeZone: ADM_TZ }); // YYYY-MM-DD
    }
    function actDayLabel(ts) {
        const key = actDayKey(ts);
        const nowSec = Date.now() / 1000;
        if (key === actDayKey(nowSec)) return 'Today';
        if (key === actDayKey(nowSec - 86400)) return 'Yesterday';
        return new Date(ts * 1000).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric', timeZone: ADM_TZ });
    }
    function actTimeLabel(ts) {
        return new Date(ts * 1000).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', timeZone: ADM_TZ });
    }

    /* cfg: { feed, pager, params(): {actor, filter, range}, showActor: bool, onTotals(n) }
       Shows ONE page (15 events) at a time; the server counts the total and caps paging at the
       3,000 most recent events, so a huge log can never slow the page down. */
    function createActivityFeed(cfg) {
        let page = 1;
        let token = 0;        // ignores answers that arrive after the filters changed
        let lastKey = '';
        let lastList = null;

        const pg = cfg.pager;
        const infoEl = pg.querySelector('.act-pager-info');
        const ctlEl = pg.querySelector('.act-pager-ctl');
        const numsEl = pg.querySelector('.act-pager-nums');
        const prevBtn = pg.querySelector('[data-pg="prev"]');
        const nextBtn = pg.querySelector('[data-pg="next"]');

        function message(icon, text, retry) {
            cfg.feed.textContent = '';
            const box = admEl('div', 'act-feed-msg');
            box.appendChild(admIcon(icon));
            box.appendChild(admEl('p', '', text));
            if (retry) {
                const b = admEl('button', 'ps-btn ps-btn--ghost ps-btn--sm', 'Try again');
                b.type = 'button';
                b.addEventListener('click', function () { fetchPage(); });
                box.appendChild(b);
            }
            cfg.feed.appendChild(box);
        }

        function appendEvents(events) {
            events.forEach(function (ev) {
                const key = actDayKey(ev.ts);
                if (key !== lastKey) {
                    cfg.feed.appendChild(admEl('h5', 'act-day', actDayLabel(ev.ts)));
                    lastList = admEl('ul', 'act-events');
                    cfg.feed.appendChild(lastList);
                    lastKey = key;
                }
                const li = admEl('li', 'act-event act-event--' + (ev.tone || 'neutral'));
                const ico = admEl('span', 'act-event-icon');
                ico.appendChild(admIcon(ev.icon || 'history'));
                li.appendChild(ico);

                const body = admEl('div', 'act-event-body');
                body.appendChild(admEl('div', 'act-event-text', ev.text));
                if (ev.meta) body.appendChild(admEl('div', 'act-event-meta', ev.meta)); // e.g. the rule that applied
                li.appendChild(body);
                if (cfg.showActor) {
                    // "Who" gets its own column so a wide timeline stays easy to scan.
                    li.classList.add('act-event--who');
                    const who = admEl('div', 'act-event-who');
                    if (ev.actor_type === 'auto') who.appendChild(admEl('span', 'act-pill', 'Automatic'));
                    else who.textContent = ev.actor_name;
                    li.appendChild(who);
                }
                li.appendChild(admEl('time', 'act-event-time', actTimeLabel(ev.ts)));
                lastList.appendChild(li);
            });
        }

        /* 1 … 4 5 6 … 12 — always first, last and the current page ±1 */
        function pageList(total, current) {
            const keep = new Set([1, total, current - 1, current, current + 1]);
            const list = [];
            let last = 0;
            Array.from(keep).filter(function (n) { return n >= 1 && n <= total; })
                .sort(function (x, y) { return x - y; })
                .forEach(function (n) {
                    if (n - last > 1) list.push('gap');
                    list.push(n);
                    last = n;
                });
            return list;
        }

        function renderPager(d) {
            if (!d.total) { pg.hidden = true; return; }
            pg.hidden = false;
            const start = (d.page - 1) * d.per_page + 1;
            const end = Math.min(d.page * d.per_page, d.total);
            infoEl.textContent = 'Showing ' + start.toLocaleString() + '\u2013' + end.toLocaleString() + ' of ' + d.total.toLocaleString() +
                (d.capped ? ' most recent \u2014 narrow the filters or Export for older activity' : '');
            ctlEl.hidden = d.pages <= 1;
            prevBtn.disabled = d.page <= 1;
            nextBtn.disabled = d.page >= d.pages;
            numsEl.textContent = '';
            pageList(d.pages, d.page).forEach(function (n) {
                if (n === 'gap') { numsEl.appendChild(admEl('span', 'act-pager-gap', '\u2026')); return; }
                const btn = admEl('button', 'act-pager-btn' + (n === d.page ? ' is-active' : ''), String(n));
                btn.type = 'button';
                btn.setAttribute('data-page', String(n));
                btn.setAttribute('aria-label', 'Page ' + n);
                if (n === d.page) btn.setAttribute('aria-current', 'page');
                numsEl.appendChild(btn);
            });
        }

        function fetchPage(scroll, showLoading) {
            const p = cfg.params();
            const myToken = ++token;
            lastKey = '';
            lastList = null;
            if (showLoading !== false) message('hourglass_top', 'Loading activity\u2026');
            const url = ADM_ACTIVITY_API + '?actor=' + encodeURIComponent(p.actor) +
                '&filter=' + encodeURIComponent(p.filter) + '&range=' + encodeURIComponent(p.range) + '&page=' + page;
            fetch(url, { credentials: 'same-origin' })
                .then(function (res) { return res.json().catch(function () { return {}; }).then(function (j) { return { ok: res.ok, data: j }; }); })
                .then(function (r) {
                    if (myToken !== token) return; // the user moved on
                    if (!r.ok || r.data.status !== 'success') {
                        pg.hidden = true;
                        message('error', (r.data && r.data.message) || 'Something went wrong. Please try again.', true);
                        return;
                    }
                    const d = r.data;
                    page = d.page; // the server clamps out-of-range pages
                    cfg.feed.textContent = '';
                    if (cfg.onTotals && d.totals && typeof d.totals.all === 'number') cfg.onTotals(d.totals.all);
                    if (!d.items.length) {
                        pg.hidden = true;
                        message('history', p.filter === 'all' ? 'No activity for this selection yet.' : 'Nothing here for this filter yet.');
                        return;
                    }
                    appendEvents(d.items);
                    renderPager(d);
                    if (scroll && cfg.feed.getBoundingClientRect().top < 0) cfg.feed.scrollIntoView({ behavior: 'smooth', block: 'start' });
                })
                .catch(function () {
                    if (myToken !== token) return;
                    pg.hidden = true;
                    message('wifi_off', 'Couldn\u2019t load activity. Check your connection.', true);
                });
        }

        function go(n) { page = n; fetchPage(true); }

        prevBtn.addEventListener('click', function () { go(page - 1); });
        nextBtn.addEventListener('click', function () { go(page + 1); });
        numsEl.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-page]');
            if (btn) go(parseInt(btn.getAttribute('data-page'), 10) || 1);
        });

        /* load(true) = filters changed → back to page 1 */
        function load(resetPage, showLoading) {
            if (resetPage) page = 1;
            fetchPage(false, showLoading);
        }

        return { load: load };
    }

    (function initAdminAccountsPanel() {
        const list = document.getElementById('am-list');
        const editModal = document.getElementById('admEditModal');
        if (!list || !editModal) return; // not a Super Admin — nothing rendered

        const addModal = document.getElementById('amAddModal');
        const dormantModal = document.getElementById('admDormantModal'); // the "Lock account" dialog
        const deleteModal = document.getElementById('admDeleteModal');
        const detail = document.getElementById('am-detail');
        const detailBody = document.getElementById('am-detail-body');
        const detailEmpty = document.getElementById('am-detail-empty');
        const feed = document.getElementById('am-feed');
        const pager = document.getElementById('am-pager');
        const listEmpty = document.getElementById('am-list-empty');
        const addBtn = document.getElementById('am-add-btn');

        let current = null;   // the selected .am-item
        let busy = false;
        let filter = 'all';

        const items = function () { return Array.from(list.querySelectorAll('.am-item')); };

        /* ── modal plumbing ───────────────────────────────────── */
        function openModal(m) { m.classList.add('ps-modal-open'); }
        function closeModal(m) { m.classList.remove('ps-modal-open'); busy = false; }

        function showAlert(id, msg) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = msg;
            el.className = 'alert-banner alert-error';
        }
        function clearAlert(id) {
            const el = document.getElementById(id);
            if (el) { el.textContent = ''; el.className = 'alert-banner hidden'; }
        }
        function resetPasswordFields(ids, modal) {
            ids.forEach(function (i) {
                const f = document.getElementById(i);
                if (!f) return;
                f.value = '';
                f.type = 'password';
            });
            admResetPwEyes(modal);
        }

        document.querySelectorAll('[data-adm-close]').forEach(function (b) {
            b.addEventListener('click', function () {
                closeModal(document.getElementById(b.getAttribute('data-adm-close')));
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            [addModal, editModal, dormantModal, deleteModal].forEach(function (m) {
                if (m && m.classList.contains('ps-modal-open')) closeModal(m);
            });
        });

        /* One POST helper for edit / lock / unlock / delete */
        function call(fields) {
            const fd = new FormData();
            Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
            fd.append('csrf_token', getCsrfToken());
            return fetch(ADM_API, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (j) {
                        return { ok: res.ok, data: j };
                    });
                });
        }
        function msgOf(r) {
            return (r.data && (r.data.message || r.data.error)) || 'Something went wrong. Please try again.';
        }
        function setBusy(btn, on, html) {
            btn.disabled = on;
            if (html !== undefined) btn.innerHTML = html;
        }

        /* ── seats (header counter + Add button) ──────────────── */
        /* The Activity page's "Who" list mirrors the admin accounts, so changes here show up there at once. */
        function syncActorOption(id, name) {
            const sel = document.getElementById('act-actor');
            if (!sel || !id) return;
            let opt = Array.from(sel.options).find(function (o) { return o.value === String(id); });
            if (name === null) { if (opt) opt.remove(); return; }
            if (!opt) { opt = document.createElement('option'); opt.value = String(id); sel.appendChild(opt); }
            opt.textContent = name;
        }

        function updateSeats(count, remaining) {
            const text = document.getElementById('am-seats-text');
            if (text) {
                text.textContent = '';
                text.appendChild(admEl('strong', '', count + ' of 5'));
                text.appendChild(document.createTextNode(' seats used'));
            }
            if (addBtn) {
                addBtn.disabled = remaining <= 0;
                if (remaining <= 0) addBtn.title = 'All 5 seats are in use. Delete an account to free one.';
                else addBtn.removeAttribute('title');
            }
        }

        /* ── list ─────────────────────────────────────────────── */
        function initials(name) {
            const parts = String(name || '').trim().split(/\s+/);
            let out = (parts[0] || '?').charAt(0).toUpperCase();
            if (parts.length > 1) out += parts[parts.length - 1].charAt(0).toUpperCase();
            return out;
        }

        function buildItem(a) {
            const b = admEl('button', 'am-item');
            b.type = 'button';
            b.setAttribute('role', 'option');
            b.setAttribute('aria-selected', 'false');
            b.setAttribute('data-id', String(a.id || ''));
            b.setAttribute('data-name', a.name);
            b.setAttribute('data-email', a.email);
            b.setAttribute('data-role', a.role);
            b.setAttribute('data-created', a.created || '');
            b.setAttribute('data-last-login', '');
            b.setAttribute('data-locked-until', '0');
            b.setAttribute('data-you', '0');
            b.setAttribute('data-manage', a.role === 'Admin' ? '1' : '0');
            b.appendChild(admEl('span', 'am-avatar', initials(a.name)));
            const main = admEl('span', 'am-item-main');
            main.appendChild(admEl('span', 'am-item-name', a.name));
            main.appendChild(admEl('span', 'am-item-email', a.email));
            b.appendChild(main);
            const lock = admEl('span', 'material-symbols-outlined am-item-lock', 'lock');
            lock.hidden = true;
            lock.title = 'Locked';
            b.appendChild(lock);
            return b;
        }

        function syncItemLock(item) {
            const lock = item.querySelector('.am-item-lock');
            if (lock) lock.hidden = !(parseInt(item.getAttribute('data-locked-until'), 10) > 0);
        }

        function refreshListEmpty() {
            const visible = items().filter(function (i) { return !i.hidden; }).length;
            listEmpty.hidden = visible > 0;
            listEmpty.textContent = items().length ? 'No admins match your search.' : 'No accounts found.';
        }

        document.getElementById('am-search').addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            items().forEach(function (i) {
                const hay = (i.getAttribute('data-name') + ' ' + i.getAttribute('data-email')).toLowerCase();
                i.hidden = q !== '' && hay.indexOf(q) === -1;
            });
            refreshListEmpty();
        });

        list.addEventListener('click', function (e) {
            const item = e.target.closest('.am-item');
            if (item) select(item, true);
        });

        /* ↑ / ↓ move through the list */
        list.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
            const shown = items().filter(function (i) { return !i.hidden; });
            const idx = shown.indexOf(document.activeElement);
            if (idx === -1) return;
            const next = shown[idx + (e.key === 'ArrowDown' ? 1 : -1)];
            if (next) { e.preventDefault(); next.focus(); select(next, false); }
        });

        /* ── detail ───────────────────────────────────────────── */
        function setTag(el, text, kind, icon) {
            el.className = 'am-tag' + (kind ? ' am-tag--' + kind : '');
            el.textContent = '';
            if (icon) el.appendChild(admIcon(icon));
            el.appendChild(document.createTextNode(text));
        }

        function renderDetail(item) {
            const name = item.getAttribute('data-name');
            const role = item.getAttribute('data-role');
            const until = parseInt(item.getAttribute('data-locked-until'), 10) || 0;
            const you = item.getAttribute('data-you') === '1';
            const manage = item.getAttribute('data-manage') === '1';

            document.getElementById('am-d-avatar').textContent = initials(name);
            document.getElementById('am-d-name').textContent = name;
            document.getElementById('am-d-email').textContent = item.getAttribute('data-email');
            setTag(document.getElementById('am-d-role'), role, role === 'Super Admin' ? 'super' : '');
            if (until) setTag(document.getElementById('am-d-status'), 'Locked until ' + admShortDate(until), 'locked', 'lock');
            else setTag(document.getElementById('am-d-status'), 'Active', 'active');

            document.getElementById('am-f-login').textContent = item.getAttribute('data-last-login') || 'Never';
            document.getElementById('am-f-created').textContent = item.getAttribute('data-created') || '—';
            document.getElementById('am-f-total').textContent = '—';

            const actions = document.getElementById('am-d-actions');
            const note = document.getElementById('am-d-note');
            actions.hidden = !manage;
            note.hidden = manage;
            if (!manage) {
                document.getElementById('am-d-note-text').textContent = you
                    ? 'This is your account. Change your own details from Settings → My Account.'
                    : 'Super Admin accounts are protected and can\u2019t be edited, locked or deleted here.';
            }
            document.getElementById('am-lock-icon').textContent = until ? 'lock_open' : 'lock';
            document.getElementById('am-lock-label').textContent = until ? 'Unlock' : 'Lock';
        }

        function select(item, scrollOnMobile) {
            items().forEach(function (i) {
                const on = i === item;
                i.classList.toggle('is-selected', on);
                i.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            current = item;
            detailEmpty.hidden = true;
            detailBody.hidden = false;
            renderDetail(item);
            resetFeedFilter();
            adminFeed.load(true);
            if (scrollOnMobile && window.matchMedia('(max-width: 900px)').matches) {
                detail.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function clearSelection() {
            current = null;
            items().forEach(function (i) { i.classList.remove('is-selected'); i.setAttribute('aria-selected', 'false'); });
            detailBody.hidden = true;
            detailEmpty.hidden = false;
        }

        /* Open the panel with someone already showing (first manageable admin, else the first row). */
        function ensureSelected() {
            if (current && document.body.contains(current)) return;
            const all = items();
            if (!all.length) return;
            const first = all.find(function (i) { return i.getAttribute('data-manage') === '1'; }) || all[0];
            select(first, false);
        }

        const navAdmins = document.querySelector('.nav-item[data-tab="admins"]');
        if (navAdmins) navAdmins.addEventListener('click', ensureSelected);
        if (document.getElementById('panel-admins').classList.contains('active')) ensureSelected();
        window.psAdminsPanelReady = ensureSelected; // lets ?view=admins open it with someone selected

        /* ── activity timeline (shared renderer) ──────────────── */
        const adminFeed = createActivityFeed({
            feed: feed,
            pager: pager,
            showActor: false, // the whole timeline belongs to the selected admin
            params: function () {
                return { actor: current ? current.getAttribute('data-id') : '0', filter: filter, range: 'all' };
            },
            onTotals: function (n) { document.getElementById('am-f-total').textContent = String(n); }
        });

        function resetFeedFilter() {
            filter = 'all';
            document.querySelectorAll('#am-chips .act-chip').forEach(function (c) {
                c.classList.toggle('active', c.getAttribute('data-filter') === 'all');
            });
        }

        document.getElementById('am-chips').addEventListener('click', function (e) {
            const chip = e.target.closest('.act-chip');
            if (!chip || !current) return;
            filter = chip.getAttribute('data-filter');
            document.querySelectorAll('#am-chips .act-chip').forEach(function (c) { c.classList.toggle('active', c === chip); });
            adminFeed.load(true, false);
        });

        document.getElementById('am-open-activity').addEventListener('click', function () {
            if (current && typeof window.psActivityOpen === 'function') window.psActivityOpen({ actor: current.getAttribute('data-id') });
        });

        /* ── actions on the selected admin ────────────────────── */
        document.getElementById('am-d-actions').addEventListener('click', function (e) {
            const btn = e.target.closest('[data-am-action]');
            if (!btn || busy || !current) return;
            const action = btn.getAttribute('data-am-action');
            if (action === 'edit') openEdit(current);
            else if (action === 'delete') openDelete(current);
            else if (action === 'lock') {
                if (parseInt(current.getAttribute('data-locked-until'), 10) > 0) doUnlock(current, btn);
                else openLock(current);
            }
        });

        function setLocked(item, until) {
            item.setAttribute('data-locked-until', String(until || 0));
            syncItemLock(item);
            if (item === current) renderDetail(item);
        }

        /* ── ADD ──────────────────────────────────────────────── */
        const submitBtn = document.getElementById('adm-submit-btn');

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                if (addBtn.disabled) return;
                clearAlert('adm-form-alert');
                ['adm-fullname', 'adm-email'].forEach(function (id) { document.getElementById(id).value = ''; });
                document.getElementById('adm-role').value = 'Admin';
                resetPasswordFields(['adm-password', 'adm-confirm'], addModal);
                openModal(addModal);
                document.getElementById('adm-fullname').focus();
            });
        }

        submitBtn.addEventListener('click', function () {
            if (busy) return;
            clearAlert('adm-form-alert');

            const fullName = document.getElementById('adm-fullname').value.trim();
            const email = document.getElementById('adm-email').value.trim().toLowerCase();
            const role = document.getElementById('adm-role').value || 'Admin';
            const password = document.getElementById('adm-password').value;
            const confirm = document.getElementById('adm-confirm').value;

            /* Client-side pre-flight (mirrors the server checks) */
            if (!fullName) return showAlert('adm-form-alert', 'Full name is required.');
            if (!email) return showAlert('adm-form-alert', 'Admin email is required.');
            if (!email.endsWith('@admin.edu')) return showAlert('adm-form-alert', 'Admin emails must end in @admin.edu (e.g. name@admin.edu).');
            if (!password) return showAlert('adm-form-alert', 'Password is required.');
            if (password.length < 8) return showAlert('adm-form-alert', 'Password must be at least 8 characters.');
            if (password !== confirm) return showAlert('adm-form-alert', 'Passwords do not match.');

            busy = true;
            const orig = submitBtn.innerHTML;
            setBusy(submitBtn, true, '<span class="material-symbols-outlined">hourglass_top</span> Creating…');

            const fd = new FormData();
            fd.append('csrf_token', getCsrfToken());
            fd.append('full_name', fullName);
            fd.append('email', email);
            fd.append('role', role);
            fd.append('password', password);
            fd.append('confirm_password', confirm);

            fetch(ADM_CREATE_API, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    busy = false;
                    setBusy(submitBtn, false, orig);
                    if (data.status !== 'success') {
                        return showAlert('adm-form-alert', data.message || 'Something went wrong. Please try again.');
                    }
                    const item = buildItem({
                        id: data.id, name: fullName, email: email, role: role,
                        created: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: ADM_TZ })
                    });
                    list.insertBefore(item, listEmpty);
                    syncActorOption(data.id, fullName);
                    refreshListEmpty();
                    if (typeof data.accounts_remaining === 'number') updateSeats(5 - data.accounts_remaining, data.accounts_remaining);
                    closeModal(addModal);
                    showToast('✅ ' + data.message);
                    select(item, true);
                })
                .catch(function () {
                    busy = false;
                    setBusy(submitBtn, false, orig);
                    showAlert('adm-form-alert', 'Network error — please check your connection and try again.');
                });
        });

        /* ── EDIT ─────────────────────────────────────────────── */
        function openEdit(item) {
            clearAlert('adm-edit-alert');
            document.getElementById('adm-edit-name').value = item.getAttribute('data-name') || '';
            document.getElementById('adm-edit-email').value = item.getAttribute('data-email') || '';
            resetPasswordFields(['adm-edit-pw', 'adm-edit-pw2', 'adm-edit-reauth'], editModal);
            openModal(editModal);
            document.getElementById('adm-edit-name').focus();
        }

        document.getElementById('adm-edit-save').addEventListener('click', function () {
            if (busy || !current) return;
            const btn = this;
            const item = current;
            const name = document.getElementById('adm-edit-name').value.trim();
            const email = document.getElementById('adm-edit-email').value.trim().toLowerCase();
            const pw = document.getElementById('adm-edit-pw').value;
            const pw2 = document.getElementById('adm-edit-pw2').value;
            const reauth = document.getElementById('adm-edit-reauth').value;

            clearAlert('adm-edit-alert');
            if (!name) return showAlert('adm-edit-alert', 'Full name is required.');
            if (!/^[^\s@]+@admin\.edu$/i.test(email)) return showAlert('adm-edit-alert', 'Admin emails must end in @admin.edu.');
            if (pw && pw.length < 8) return showAlert('adm-edit-alert', 'New password must be at least 8 characters.');
            if (pw !== pw2) return showAlert('adm-edit-alert', 'New passwords do not match.');
            if (!reauth) return showAlert('adm-edit-alert', 'Enter your own password to confirm.');

            busy = true;
            const orig = btn.innerHTML;
            setBusy(btn, true, '<span class="material-symbols-outlined">hourglass_top</span> Saving…');
            call({
                action: 'update', id: item.getAttribute('data-id'),
                full_name: name, email: email,
                new_password: pw, confirm_password: pw2, current_password: reauth
            }).then(function (r) {
                busy = false;
                setBusy(btn, false, orig);
                if (!r.ok || r.data.status !== 'success') {
                    document.getElementById('adm-edit-reauth').value = '';
                    return showAlert('adm-edit-alert', msgOf(r));
                }
                item.setAttribute('data-name', r.data.full_name);
                item.setAttribute('data-email', r.data.email);
                item.querySelector('.am-avatar').textContent = initials(r.data.full_name);
                item.querySelector('.am-item-name').firstChild.textContent = r.data.full_name;
                item.querySelector('.am-item-email').textContent = r.data.email;
                syncActorOption(item.getAttribute('data-id'), r.data.full_name);
                if (item === current) renderDetail(item);
                closeModal(editModal);
                showToast(r.data.signed_out ? 'Saved. They have been signed out everywhere.' : 'Admin details saved.', 'success');
            }).catch(function () {
                busy = false;
                setBusy(btn, false, orig);
                showAlert('adm-edit-alert', 'Network error — please check your connection and try again.');
            });
        });

        /* ── LOCK ─────────────────────────────────────────────── */
        const customWrap = document.getElementById('adm-custom-days');
        const customInput = document.getElementById('adm-dormant-custom');
        const untilText = document.getElementById('adm-dormant-until-text');

        function selectedDays() {
            const chosen = document.querySelector('input[name="adm-dormant-days"]:checked');
            if (!chosen) return null;
            const raw = chosen.value === 'custom' ? customInput.value.trim() : chosen.value;
            if (!/^\d+$/.test(raw)) return null;
            const n = parseInt(raw, 10);
            return (n >= 1 && n <= ADM_MAX_DAYS) ? n : null;
        }

        function refreshUntil() {
            const chosen = document.querySelector('input[name="adm-dormant-days"]:checked');
            customWrap.hidden = !(chosen && chosen.value === 'custom');
            const n = selectedDays();
            untilText.textContent = n
                ? 'Locked until ' + admLongDate(Math.floor(Date.now() / 1000) + n * 86400)
                : 'Pick 1 to ' + ADM_MAX_DAYS + ' days (maximum ' + ADM_MAX_DAYS + ').';
        }

        document.querySelectorAll('input[name="adm-dormant-days"]').forEach(function (r) {
            r.addEventListener('change', function () {
                refreshUntil();
                if (r.value === 'custom' && r.checked) customInput.focus();
            });
        });
        customInput.addEventListener('input', refreshUntil);

        function openLock(item) {
            clearAlert('adm-dormant-alert');
            document.getElementById('adm-dormant-name').textContent = item.getAttribute('data-name') || 'This admin';
            const def = document.querySelector('input[name="adm-dormant-days"][value="7"]');
            if (def) def.checked = true;
            customInput.value = '';
            refreshUntil();
            openModal(dormantModal);
        }

        document.getElementById('adm-dormant-save').addEventListener('click', function () {
            if (busy || !current) return;
            const btn = this;
            const item = current;
            const days = selectedDays();
            clearAlert('adm-dormant-alert');
            if (!days) return showAlert('adm-dormant-alert', 'Choose between 1 and ' + ADM_MAX_DAYS + ' days.');

            busy = true;
            const orig = btn.innerHTML;
            setBusy(btn, true, '<span class="material-symbols-outlined">hourglass_top</span> Saving…');
            call({ action: 'dormant', id: item.getAttribute('data-id'), days: days }).then(function (r) {
                busy = false;
                setBusy(btn, false, orig);
                if (!r.ok || r.data.status !== 'success') return showAlert('adm-dormant-alert', msgOf(r));
                setLocked(item, r.data.dormant_until);
                closeModal(dormantModal);
                showToast(r.data.message, 'success');
            }).catch(function () {
                busy = false;
                setBusy(btn, false, orig);
                showAlert('adm-dormant-alert', 'Network error — please check your connection and try again.');
            });
        });

        /* ── UNLOCK (reversible, so no confirmation dialog) ───── */
        function doUnlock(item, btn) {
            busy = true;
            btn.disabled = true;
            call({ action: 'reactivate', id: item.getAttribute('data-id') }).then(function (r) {
                busy = false;
                btn.disabled = false;
                if (!r.ok || r.data.status !== 'success') return showToast(msgOf(r), 'error');
                setLocked(item, 0);
                showToast('Account unlocked.', 'success');
            }).catch(function () {
                busy = false;
                btn.disabled = false;
                showToast('Network error — please try again.', 'error');
            });
        }

        /* ── DELETE ───────────────────────────────────────────── */
        function openDelete(item) {
            clearAlert('adm-delete-alert');
            document.getElementById('adm-delete-name').textContent = item.getAttribute('data-name') || 'this admin';
            resetPasswordFields(['adm-delete-reauth'], deleteModal);
            openModal(deleteModal);
            document.getElementById('adm-delete-reauth').focus();
        }

        document.getElementById('adm-delete-confirm').addEventListener('click', function () {
            if (busy || !current) return;
            const btn = this;
            const item = current;
            const reauth = document.getElementById('adm-delete-reauth').value;
            clearAlert('adm-delete-alert');
            if (!reauth) return showAlert('adm-delete-alert', 'Enter your own password to confirm.');

            busy = true;
            const orig = btn.innerHTML;
            setBusy(btn, true, '<span class="material-symbols-outlined">hourglass_top</span> Deleting…');
            call({ action: 'delete', id: item.getAttribute('data-id'), current_password: reauth }).then(function (r) {
                busy = false;
                setBusy(btn, false, orig);
                if (!r.ok || r.data.status !== 'success') {
                    document.getElementById('adm-delete-reauth').value = '';
                    return showAlert('adm-delete-alert', msgOf(r));
                }
                const all = items();
                const pos = all.indexOf(item);
                syncActorOption(item.getAttribute('data-id'), null);
                item.remove();
                if (typeof r.data.accounts_count === 'number') updateSeats(r.data.accounts_count, r.data.accounts_remaining);
                closeModal(deleteModal);
                refreshListEmpty();
                const left = items();
                if (left.length) select(left[Math.min(pos, left.length - 1)], false);
                else clearSelection();
                showToast('Admin account deleted. A seat is free for a new admin.', 'success');
            }).catch(function () {
                busy = false;
                setBusy(btn, false, orig);
                showAlert('adm-delete-alert', 'Network error — please check your connection and try again.');
            });
        });
    })();
    /* ▲ ADMIN-ACCOUNTS-PANEL-END ───────────────────────────────────────────── */

    /* ▼ ACTIVITY-PAGE-BEGIN ──────────────────────────────────────────────────
       Sidebar → Activity (every admin). Filter chips (type) + Who + When,
       all served by api/admin-activity.php. */
    (function initActivityPage() {
        const feedEl = document.getElementById('act-feed');
        const panel = document.getElementById('panel-activity');
        if (!feedEl || !panel) return;

        const actorSel = document.getElementById('act-actor');
        const rangeSel = document.getElementById('act-range');
        const chipsEl = document.getElementById('act-chips');
        let filter = 'all';
        let loaded = false;

        const feed = createActivityFeed({
            feed: feedEl,
            pager: document.getElementById('act-pager'),
            showActor: true,
            params: function () { return { actor: actorSel.value, filter: filter, range: rangeSel.value }; }
        });

        function setChip(value) {
            filter = value;
            chipsEl.querySelectorAll('.act-chip').forEach(function (c) {
                c.classList.toggle('active', c.getAttribute('data-filter') === value);
            });
        }

        chipsEl.addEventListener('click', function (e) {
            const chip = e.target.closest('.act-chip');
            if (!chip) return;
            setChip(chip.getAttribute('data-filter'));
            feed.load(true, false);
        });
        actorSel.addEventListener('change', function () { feed.load(true); });
        rangeSel.addEventListener('change', function () { feed.load(true); });

        function ensureLoaded() {
            if (loaded) return;
            loaded = true;
            feed.load(true);
        }

        const nav = document.querySelector('.nav-item[data-tab="activity"]');
        if (nav) nav.addEventListener('click', ensureLoaded);
        if (panel.classList.contains('active')) ensureLoaded();
        window.psActivityReady = ensureLoaded; // lets ?view=activity open it with data

        /* Open Activity with a preset — used by the Admins panel, Borrowing Rules and My Account.
           opts: { actor: 'all' | 'auto' | 'me' | <admin id>, filter: chip key, range: today | 7d | 30d | all } */
        window.psActivityOpen = function (opts) {
            opts = opts || {};
            const actor = opts.actor ? String(opts.actor) : 'all';
            actorSel.value = Array.from(actorSel.options).some(function (o) { return o.value === actor; }) ? actor : 'all';
            rangeSel.value = opts.range || 'all';
            setChip(opts.filter || 'all');
            loaded = true;
            if (nav) nav.click();
            feed.load(true, false);
        };

        /* Links elsewhere in the portal: <button data-act-open="auto|me"> */
        document.addEventListener('click', function (e) {
            const link = e.target.closest('[data-act-open]');
            if (!link) return;
            const which = link.getAttribute('data-act-open');
            if (which === 'auto') window.psActivityOpen({ actor: 'auto', filter: 'requests', range: '30d' });
            else if (which === 'me') window.psActivityOpen({ actor: 'me', range: '30d' });
        });

        /* Export exactly what the filters are showing (the server applies the same visibility rules). */
        document.getElementById('act-export').addEventListener('click', function () {
            const p = { actor: actorSel.value, filter: filter, range: rangeSel.value };
            window.location.href = ADM_ACTIVITY_API + '?format=csv&actor=' + encodeURIComponent(p.actor) +
                '&filter=' + encodeURIComponent(p.filter) + '&range=' + encodeURIComponent(p.range);
        });
    })();
    /* ▲ ACTIVITY-PAGE-END ───────────────────────────────────────────────────── */


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
                    if (typeof window.adminDoPoll === 'function') window.adminDoPoll();
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
                if (typeof window.adminDoPoll === 'function') window.adminDoPoll();
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
        // Hard-refresh flag: Ctrl+Shift+R / Ctrl+F5 sets this before the page unloads.
        // On load we honour it by skipping hash restore, effectively landing on Dashboard.
        try {
            if (sessionStorage.getItem('adminHardRefresh') === '1') {
                sessionStorage.removeItem('adminHardRefresh');
                history.replaceState({ tab: 'dashboard' }, '');
                return; // leave the default dashboard panel active
            }
        } catch (_) { }

        const params = new URLSearchParams(window.location.search);
        const view = params.get('view');
        const editItem = params.get('edit_item');

        // ── Hash-based restore (F5 / normal reload) ───────────────────────
        const hash = window.location.hash; // e.g. '#tab-inventory'
        const hashTab = hash.startsWith('#tab-') ? hash.slice(5) : null;

        // URL params take priority over hash (they come from PHP redirects/links
        // and carry more context, e.g. edit_item). Hash is the F5 restore path.
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
        } else if (view === 'activity') {
            _switchTabDOM('activity');
            if (typeof window.psActivityReady === 'function') window.psActivityReady();
            history.replaceState({ tab: 'activity' }, '');
        } else if (view === 'admins') {
            _switchTabDOM('admins');
            if (typeof window.psAdminsPanelReady === 'function') window.psAdminsPanelReady();
            history.replaceState({ tab: 'admins' }, '');
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
            } else if (hashTab && hashTab !== 'dashboard') {
                // Hash restore: covers rooms, inventory, faculty, activity, admins, settings, lending, requests, etc.
                _switchTabDOM(hashTab, false);
                if (hashTab === 'activity' && typeof window.psActivityReady === 'function') window.psActivityReady();
                if (hashTab === 'admins' && typeof window.psAdminsPanelReady === 'function') window.psAdminsPanelReady();
                history.replaceState({ tab: hashTab }, '');
            } else {
                history.replaceState({ tab: 'dashboard' }, '');
            }
        }
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
        // Expose helpers for inline AJAX scripts in admin-dashboard.php
        window._setupInventoryListFn = setupInventoryList;
        window._exitEditModeFn = _exitEditMode;

        // Hard-refresh detection: set flag on Ctrl+Shift+R / Ctrl+F5 so initView
        // can skip hash restore and land on Dashboard (mimics a true fresh load).
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && (
                (e.shiftKey && (e.key === 'r' || e.key === 'R')) ||
                e.key === 'F5'
            )) {
                try { sessionStorage.setItem('adminHardRefresh', '1'); } catch (_) { }
            }
        });

        restoreState();

        initView();
        initImageUpload(); // Add-equipment form (left column)
        initImageUpload({ // Edit-equipment modal
            dropZone: 'eqm-dropZone',
            fileInput: 'eqm-itemImageInput',
            preview: 'eqm-imagePreview',
            removeBtn: 'eqm-removeImageBtn',
            removeFlag: 'eqm-remove-image-flag'
        });
        initNotifCards();
        // Poll for new notifications (new requests, overdue items, room
        // issues, stock drops) every 25s without a full page reload.
        setInterval(_notifPoll, 25000);
        // Timers are throttled in background tabs, so also refresh the bell
        // the moment the admin comes back to this tab or window.
        // (Throttled: returning to a tab fires both events at once.)
        let _lastResumePoll = 0;
        function _pollOnResume() {
            const now = Date.now();
            if (document.hidden || now - _lastResumePoll < 2000) return;
            _lastResumePoll = now;
            _notifPoll();
        }
        document.addEventListener('visibilitychange', _pollOnResume);
        window.addEventListener('focus', _pollOnResume);

        // Live search
        setupLiveSearch('waitingSearch', 'waiting-body', 'waiting');
        setupLiveSearch('returnSearch', 'return-body', 'approved');
        setupLiveSearch('approvedSearch', 'approved-list', 'approved');
        setupLiveSearch('declinedSearch', 'declined-list', 'declined');
        setupInventoryList(); // client-side search + 10-per-page pagination — see note near its definition
        setupLiveSearch('rawSearch', 'raw-data-body', 'raw');

        // ── Arbitration log search (client-side filter) ──────────────────
        const arbLogSearchInput = document.getElementById('arbLogSearch');
        if (arbLogSearchInput) {
            // Client-side filter — avoids a full page reload for every keystroke.
            let arbLogTimer;
            arbLogSearchInput.addEventListener('keyup', function () {
                clearTimeout(arbLogTimer);
                const q = this.value.trim().toLowerCase();
                arbLogTimer = setTimeout(function () {
                    const tbody = document.getElementById('arb-log-body');
                    if (!tbody) return;
                    tbody.querySelectorAll('tr').forEach(function (row) {
                        const text = row.textContent.toLowerCase();
                        row.style.display = (q === '' || text.includes(q)) ? '' : 'none';
                    });
                    // Update URL param silently so a refresh re-applies the filter.
                    const url = new URL(window.location.href);
                    if (q) { url.searchParams.set('arb_log_search', q); }
                    else { url.searchParams.delete('arb_log_search'); }
                    history.replaceState(history.state, '', url.toString());
                }, 300);
            });
            // Pre-fill from URL param and apply immediately
            const urlParams2 = new URLSearchParams(window.location.search);
            const existingSearch = urlParams2.get('arb_log_search');
            if (existingSearch) {
                arbLogSearchInput.value = existingSearch;
                arbLogSearchInput.dispatchEvent(new Event('keyup'));
            }
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
                Scheduled: ['ps-badge--scheduled', 'Scheduled (approved)'],
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
                        if (typeof window.adminDoPoll === 'function') window.adminDoPoll();
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

        openBtn.addEventListener('click', function () {
            _closeMobileSidebar();
            startScanner();
        });
        closeBtn.addEventListener('click', stopScanner);
        modal.addEventListener('click', e => { if (e.target === modal) stopScanner(); });
    })();
})();

/* ════════════════════════════════════════════════════════════════
   PHASE 3 — ARBITRATION + EXPORT HELPERS
════════════════════════════════════════════════════════════════ */

/* Export format button selector */
function psSelectFmt(btn) {
    var row = btn.closest('.ps-export-fmt-row');
    if (!row) return;
    row.querySelectorAll('.ps-export-fmt-btn').forEach(function (b) { b.classList.remove('active'); });
    btn.classList.add('active');
}


/* ═══════════════════════════════════════════════════════════════
   APPROVALS — review drawer for the Equipment > On Loan table
   Additive block. Opens from the request ID in each row (and from the
   notification deep link, which clicks the same ID element). Approve /
   Decline in the drawer hand off to the EXISTING approve / decline
   modals by clicking the row's own buttons, so the confirm flow and the
   admin-override.php call are untouched.
   ═══════════════════════════════════════════════════════════════ */
(function () {
    var drawer = document.getElementById('ps-appr-drawer');
    if (!drawer) return;
    var currentId = null;

    function el(id) { return document.getElementById(id); }
    function txt(id, v) {
        var n = el(id);
        if (n) n.textContent = (v !== undefined && v !== null && String(v).trim() !== '') ? v : '\u2014';
    }

    function openFor(row) {
        var d = row.dataset;
        currentId = d.id;

        txt('ps-appr-reqno', '#' + String(d.id || 0).padStart(4, '0'));
        txt('ps-appr-submitted', d.submitted);
        txt('ps-appr-who', d.borrower);
        txt('ps-appr-type', d.reqType);
        txt('ps-appr-idno', d.idNumber);
        txt('ps-appr-item', d.equipment);
        txt('ps-appr-qty', d.qty);
        txt('ps-appr-needed', d.dateNeeded);
        txt('ps-appr-return', d.returnDateDisplay);
        txt('ps-appr-room', d.room);
        txt('ps-appr-instr', d.instructor);
        txt('ps-appr-note', d.note);

        var hasDoc = !!d.docUrl;
        var card = el('ps-appr-doc'), none = el('ps-appr-doc-none'), prev = el('ps-appr-doc-preview');
        if (card) card.hidden = !hasDoc;
        if (none) none.hidden = hasDoc;
        if (prev) prev.hidden = true;

        if (hasDoc) {
            var kind = d.docKind || 'file';
            var icon = kind === 'pdf' ? 'picture_as_pdf' : (kind === 'image' ? 'image' : 'draft');
            var ext = (d.docName.split('.').pop() || '').toUpperCase();
            txt('ps-appr-doc-name', d.docName);
            txt('ps-appr-doc-kind', (kind === 'pdf' ? 'PDF document' : kind === 'image' ? 'Image' : 'File') + (ext ? ' \u00b7 ' + ext : ''));
            el('ps-appr-doc-icon').textContent = icon;
            el('ps-appr-doc-open').setAttribute('href', d.docOpenUrl);
            var dl = el('ps-appr-doc-dl');
            dl.setAttribute('href', d.docUrl);
            dl.setAttribute('download', d.docName);
            // Keep inline previews and downloads on the original file URL; the
            // Open link uses the branded document viewer.
            if (kind === 'image' && prev) {
                el('ps-appr-doc-img').setAttribute('src', d.docUrl);
                prev.hidden = false;
            }
        }
        if (typeof psOpenModal === 'function') psOpenModal('ps-appr-drawer');
    }

    // Capture phase: runs before the generic data-action handler, so the ID
    // in the waiting list opens this drawer instead of the old detail modal.
    document.addEventListener('click', function (e) {
        var t = e.target.closest('#eq-loan-table tr[data-loan-state="waiting"] [data-modal="ps-req-detail-modal"]');
        if (!t) return;
        var row = t.closest('tr.eq-loan-row');
        if (!row) return;
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        openFor(row);
    }, true);

    drawer.addEventListener('click', function (e) {
        var b = e.target.closest('[data-appr-decide]');
        if (!b || !currentId) return;
        var row = document.querySelector('#eq-loan-table tr.eq-loan-row[data-loan-state="waiting"][data-id="' + currentId + '"]');
        if (!row) return;
        var sel = b.getAttribute('data-appr-decide') === 'approve'
            ? '[data-modal="ps-approve-modal"]'
            : '[data-modal="ps-decline-modal"]';
        var btn = row.querySelector(sel);
        if (!btn) return;
        if (typeof psCloseModal === 'function') psCloseModal('ps-appr-drawer');
        btn.click();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('ps-modal-open') && typeof psCloseModal === 'function') {
            psCloseModal('ps-appr-drawer');
        }
    });
})();


/* ═══════════════════════════════════════════════════════════════
   ROOMS — reservation details drawer (Rooms > Reservations table)
   Additive block. "Cancel reservation" in the drawer clicks the row's
   own Cancel button, so the existing cancel modal + API are reused.
   Waiting requests (later-date reservations, and Org Adviser requests with an
   attached letter) show a reason box and Approve / Decline buttons; the
   decision goes to room-reservation/api/review-adviser-reservation.php (any
   admin for later-date requests, Super Admin only for the adviser letters).
   ═══════════════════════════════════════════════════════════════ */
(function () {
    var drawer = document.getElementById('ps-room-drawer');
    if (!drawer) return;
    var currentRow = null;

    function el(id) { return document.getElementById(id); }
    function txt(id, v) {
        var n = el(id);
        if (n) n.textContent = (v !== undefined && v !== null && String(v).trim() !== '') ? v : '\u2014';
    }

    var reviewSec = el('ps-rr-review-sec');
    var reasonBox = el('ps-rr-review-reason');
    var reviewErr = el('ps-rr-review-err');
    var decideBtns = Array.prototype.slice.call(drawer.querySelectorAll('[data-rr-decide]'));

    function showReviewError(msg) {
        if (!reviewErr) return;
        reviewErr.textContent = msg;
        reviewErr.hidden = false;
    }

    function setReviewUI(on) {
        if (reviewSec) reviewSec.hidden = !on;
        decideBtns.forEach(function (b) { b.hidden = !on; b.disabled = false; });
        if (reasonBox) reasonBox.value = '';
        if (reviewErr) { reviewErr.hidden = true; reviewErr.textContent = ''; }
    }

    function openFor(row) {
        var d = row.dataset;
        currentRow = row;
        txt('ps-rr-no', '#' + d.rrId);
        txt('ps-rr-submitted', d.submitted);
        txt('ps-rr-room', d.room);
        txt('ps-rr-where', d.where);
        txt('ps-rr-date', d.date);
        txt('ps-rr-time', d.time);
        txt('ps-rr-attendees', d.attendees && d.attendees !== '0' ? d.attendees : '');
        txt('ps-rr-purpose', d.purpose);
        txt('ps-rr-who', d.whoSub && d.whoSub.indexOf('Student via') === 0 ? d.who + ' (' + d.whoSub + ')' : d.who);
        txt('ps-rr-type', d.type);

        var badge = el('ps-rr-status');
        var cls = { Approved: 'ps-badge--active', Declined: 'ps-badge--overdue', Cancelled: 'ps-badge--returned', Waiting: 'ps-badge--waiting' };
        badge.className = 'ps-badge ps-badge--dot ' + (cls[d.status] || 'ps-badge--returned');
        badge.textContent = d.status;

        var notes = [];
        if (d.notes) notes.push(d.notes);
        if (d.reason) notes.push((d.status === 'Declined' ? 'Declined: ' : d.status === 'Cancelled' ? 'Cancelled: ' : 'Reason: ') + d.reason);
        txt('ps-rr-notes', notes.join('\n\n'));

        var hasDoc = !!d.docUrl;
        el('ps-rr-doc-sec').hidden = !hasDoc;
        var prev = el('ps-rr-doc-preview');
        if (prev) prev.hidden = true;
        if (hasDoc) {
            var kind = d.docKind || 'file';
            var ext = (d.docName.split('.').pop() || '').toUpperCase();
            txt('ps-rr-doc-name', d.docName);
            txt('ps-rr-doc-kind', (kind === 'pdf' ? 'PDF document' : kind === 'image' ? 'Image' : 'File') + (ext ? ' \u00b7 ' + ext : ''));
            el('ps-rr-doc-icon').textContent = kind === 'pdf' ? 'picture_as_pdf' : (kind === 'image' ? 'image' : 'draft');
            el('ps-rr-doc-open').setAttribute('href', d.docOpenUrl);
            var dl = el('ps-rr-doc-dl');
            dl.setAttribute('href', d.docUrl);
            dl.setAttribute('download', d.docName);
            if (kind === 'image' && prev) {
                el('ps-rr-doc-img').setAttribute('src', d.docUrl);
                prev.hidden = false;
            }
        }

        // Cancel is only offered for Approved reservations that haven't ended yet (same rule as the table).
        el('ps-rr-cancel-btn').hidden = row.dataset.cancellable !== '1';

        // Review controls: Waiting requests (the table only flags the rows this admin may decide).
        setReviewUI(d.status === 'Waiting' && d.canReview === '1');

        // Decision context. The room arbitration log records the rule used for
        // automatic decisions; an override/manual value denotes an admin decision.
        var arbRule = d.arbRule || '';
        var arbReason = d.arbReason || d.reason || '';
        var hasDoc = !!d.docUrl;
        var decSec = el('ps-rr-decision-sec');
        if (decSec) {
            var ruleMap = {
                'rule_1_fifo': 'FIFO (first submitted)',
                'rule_2_signatory': 'Request-letter/signatory priority',
                'rule_3_role': 'Submitter role priority',
                'rule_4_return_history': 'Return-history priority',
                'rule_5_id_order': 'ID order tie-breaker',
                'rule_past_datetime': 'Past date or time',
                'rule_archived': 'Facility availability policy',
                'rule_missing_doc_hold': 'Supporting-document policy',
                'rule_schedule_conflict': 'Schedule conflict',
                'rule_expired_unreviewed': 'Not reviewed in time (12:01 AM on the day)',
                'override': 'Admin override',
                'manual': 'Manual admin decision'
            };
            var ruleLabel = ruleMap[arbRule] || (arbRule
                ? arbRule.replace(/^rule_\d+_/, '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); })
                : 'Not recorded');
            var isManual = arbRule === 'override' || arbRule === 'manual';
            var isAuto = arbRule !== '' && !isManual;
            var methodLabel;
            if (isManual) {
                methodLabel = 'Manual (admin decision)';
            } else if (isAuto) {
                methodLabel = arbRule === 'rule_1_fifo' ? 'Automatic (FIFO)' : (arbRule === 'rule_expired_unreviewed' ? 'Automatic (not reviewed in time)' : 'Automatic (priority rules)');
            } else {
                methodLabel = 'Not recorded';
            }
            var methodEl = el('ps-rr-dec-method');
            if (methodEl) methodEl.textContent = methodLabel;

            var ruleRow = el('ps-rr-dec-rule-row');
            var ruleEl = el('ps-rr-dec-rule');
            if (ruleRow && ruleEl) {
                ruleRow.hidden = false;
                ruleEl.textContent = ruleLabel;
            }

            // Reason row
            var reasonRow = el('ps-rr-dec-reason-row');
            var reasonEl = el('ps-rr-dec-reason');
            if (reasonRow && reasonEl) {
                reasonRow.hidden = false;
                reasonEl.textContent = arbReason || (hasDoc ? 'Supporting document attached to this reservation.' : 'No decision reason recorded.');
            }
            // Waiting: nothing has been decided yet
            if (d.status === 'Waiting') {
                if (methodEl) methodEl.textContent = hasDoc ? 'Awaiting Super Admin review' : 'Awaiting admin review';
                if (ruleRow) ruleRow.hidden = true;
                if (reasonRow) reasonRow.hidden = true;
            }
            decSec.hidden = false;
        }

        if (typeof psOpenModal === 'function') psOpenModal('ps-room-drawer');
    }

    /* Reflect an approved/declined decision in the table row without a reload */
    function applyDecision(row, decision, reason) {
        var d = row.dataset;
        d.status = decision;
        d.reason = reason;
        d.arbRule = 'override';
        d.arbReason = reason;
        d.canReview = '0';

        var badge = row.querySelector('.ps-badge');
        if (badge) {
            badge.className = 'ps-badge ps-badge--dot ' + (decision === 'Approved' ? 'ps-badge--active' : 'ps-badge--overdue');
            badge.textContent = decision;
        }
        var viewBtn = row.querySelector('[data-rr-detail]');
        if (viewBtn) viewBtn.textContent = 'View';

        // An approved reservation that has not ended yet can be cancelled, like any other
        var endMs = Date.parse((d.isoDate || '') + 'T' + (d.endTime || '') + '+08:00'); // Asia/Manila
        var cancellable = decision === 'Approved' && endMs > Date.now();
        d.cancellable = cancellable ? '1' : '0';
        if (cancellable && viewBtn && !row.querySelector('.btn-cancel-rr-admin')) {
            var cb = document.createElement('button');
            cb.type = 'button';
            cb.className = 'pr-tbl-btn cancel-r btn-cancel-rr-admin';
            cb.setAttribute('data-action', 'admin-cancel-reservation');
            cb.setAttribute('data-rr-id', d.rrId);
            cb.setAttribute('data-room-name', d.room || '');
            cb.setAttribute('data-faculty-name', d.who || '');
            cb.title = 'Cancel this reservation';
            cb.textContent = 'Cancel';
            viewBtn.parentNode.insertBefore(cb, viewBtn);
        }
        // Lets the table script re-sort, refresh the tab counts and re-apply the filter
        document.dispatchEvent(new CustomEvent('rr:rows-changed'));
    }

    function submitDecision(decision, btn) {
        if (!currentRow) return;
        var row = currentRow;
        var reason = reasonBox ? reasonBox.value.trim() : '';
        if (reason.length < 5) {
            showReviewError('Please enter a reason of at least 5 characters.');
            if (reasonBox) reasonBox.focus();
            return;
        }
        var tokenEl = document.querySelector('input[name="csrf_token"]');
        var idleHtml = btn.innerHTML;
        if (reviewErr) reviewErr.hidden = true;
        decideBtns.forEach(function (b) { b.disabled = true; });
        btn.textContent = decision === 'Approved' ? 'Approving\u2026' : 'Declining\u2026';

        fetch('room-reservation/api/review-adviser-reservation.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                reservation_id: parseInt(row.dataset.rrId, 10),
                decision: decision,
                reason: reason,
                csrf_token: tokenEl ? tokenEl.value : ''
            })
        })
            .then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, j: j }; });
            })
            .then(function (res) {
                if (!res.ok || res.j.status !== 'success') {
                    var err = new Error(res.j.message || 'Could not save the decision.');
                    err.fromServer = true;
                    throw err;
                }
                applyDecision(row, decision, reason);
                if (typeof psCloseModal === 'function') psCloseModal('ps-room-drawer');
                if (typeof window.showToast === 'function') window.showToast(res.j.message || (decision === 'Approved' ? 'Reservation approved.' : 'Reservation declined.'));
            })
            .catch(function (err) {
                showReviewError(err && err.fromServer ? err.message : 'Network error. Please try again.');
                decideBtns.forEach(function (b) { b.disabled = false; });
                btn.innerHTML = idleHtml;
            });
    }

    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-rr-detail]');
        if (!t) return;
        var row = t.closest('tr.ps-rr-row');
        if (!row) return;
        e.preventDefault();
        openFor(row);
    });

    drawer.addEventListener('click', function (e) {
        var decideBtn = e.target.closest('[data-rr-decide]');
        if (decideBtn) {
            submitDecision(decideBtn.getAttribute('data-rr-decide'), decideBtn);
            return;
        }
        if (!e.target.closest('[data-rr-cancel]') || !currentRow) return;
        var btn = currentRow.querySelector('.btn-cancel-rr-admin');
        if (!btn) return;
        if (typeof psCloseModal === 'function') psCloseModal('ps-room-drawer');
        btn.click();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('ps-modal-open') && typeof psCloseModal === 'function') {
            psCloseModal('ps-room-drawer');
        }
    });
})();

/* ================================================================
   PUPSYNC ADMIN — Dashboard tab behaviour
   ----------------------------------------------------------------
   1. Draws the "Demand & decisions" bar chart (plain SVG, no library
      — the page's CSP only allows scripts from this site + jsDelivr).
   2. Makes stat cards / "needs attention" rows / "Manage" links jump
      to the right tab (and filter chip) by clicking the real sidebar
      item, so the tab switch, drawer-close and history logic that
      already exist in admin-dashboard.js are reused, not copied.
   3. Keeps the panel live: every 15 s it fetches
      admin-dashboard.php?fragment=dashboard (same markup PHP
      rendered) and swaps it in, pausing while the tab is hidden or
      the Dashboard tab isn't the one being viewed.

   The header (greeting, action buttons, bell, avatar) is outside the
   swapped region on purpose: admin-dashboard.js binds to those ids.
================================================================ */
(function () {
    'use strict';

    var root = document.getElementById('dash');
    var live = document.getElementById('dashLive');
    if (!root || !live) return;

    var POLL_MS = 15000;
    var state = { set: 'equipment', range: 14, more: false };   // more = "See more" panel open
    var lastHtml = null;
    var inflight = false;
    var stopped = false;

    // The entrance animation is first-paint only.
    setTimeout(function () { root.classList.remove('dash-intro'); }, 900);

    /* ── helpers ─────────────────────────────────────────────── */
    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function parseYmd(s) {
        var p = s.split('-');
        return new Date(+p[0], +p[1] - 1, +p[2]);
    }
    var MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var DOW = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var DOWF = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    function shortDate(s) { var d = parseYmd(s); return MON[d.getMonth()] + ' ' + d.getDate(); }
    function longDate(s) { var d = parseYmd(s); return DOW[d.getDay()] + ', ' + MON[d.getMonth()] + ' ' + d.getDate(); }
    function sum(a) { return a.reduce(function (x, y) { return x + y; }, 0); }

    /* ── context under the chart ─────────────────────────────── */
    function plural(n, w) { return n + ' ' + w + (n === 1 ? '' : 's'); }
    function share(a, b) { return b ? Math.round(a / b * 100) : 0; }
    function rangeSum(vals, a, b) { var t = 0; for (var i = a; i < b; i++) t += vals[i] || 0; return t; }
    function span(from, to) {
        var a = parseYmd(from), b = parseYmd(to);
        return shortDate(from) + ' – ' + (a.getMonth() === b.getMonth() ? b.getDate() : shortDate(to));
    }
    function trendChip(cur, prev, unitWord) {
        // cur / prev: numbers; returns '' when there is nothing to compare
        if (prev == null) return '';
        if (prev === 0) return cur > 0 ? { dir: 'up', text: 'new' } : '';
        var d = Math.round((cur - prev) / prev * 100);
        return { dir: d > 0 ? 'up' : (d < 0 ? 'down' : 'flat'), text: d === 0 ? 'no change' : Math.abs(d) + '%' };
    }
    function chipHtml(t, tail) {
        if (!t) return '';
        var icon = t.dir === 'up' ? 'trending_up' : (t.dir === 'down' ? 'trending_down' : 'trending_flat');
        return '<em class="dash-delta is-' + t.dir + '"><span class="material-symbols-outlined" aria-hidden="true">' + icon + '</span>' +
            esc(t.text) + (tail ? ' <small>' + esc(tail) + '</small>' : '') + '</em>';
    }

    /* Busiest day(s) for 7D / 14D, busiest week for 30D (also returned as "bands" so the chart can
       mark them), and a short "See more" pair of facts. */
    function insightsHtml(c) {
        var unit = c.set.unit, units = unit + 's', n = c.n, N = c.N;
        var lead = '', rows = '', heading = '', hint = '', bands = [];

        /* one horizontal bar as SVG (no inline styles) */
        function bar(pct) {
            return '<span class="dash-rank-bar"><svg viewBox="0 0 100 1" preserveAspectRatio="none" aria-hidden="true" focusable="false">' +
                '<rect x="0" y="0" width="' + pct + '" height="1"/></svg></span>';
        }
        function rankRow(name, small, t, base, total, isTop) {
            return '<li' + (isTop ? ' class="is-top"' : '') + '><span class="dash-rank-name">' + esc(name) +
                (small ? '<small>' + esc(small) + '</small>' : '') + '</span>' +
                bar(base ? Math.round(t / base * 100) : 0) +
                '<span class="dash-rank-val"><b>' + t + '</b><small>' + share(t, total) + '%</small></span></li>';
        }

        if (n <= 14) {
            heading = 'Busiest days';
            var order = [];
            c.totals.forEach(function (t, i) { if (t > 0) order.push({ i: i, t: t }); });
            order.sort(function (a, b) { return b.t - a.t || b.i - a.i; });   // ties: most recent first
            var top = order[0].t;
            var tied = order.filter(function (x) { return x.t === top; });
            // 7D: plain weekday names ("Tuesday"); 14D: "Tue, Oct 14" (a weekday alone would be ambiguous)
            var nm = function (i) {
                var d = parseYmd(c.labels[i]);
                var base = n <= 7 ? DOWF[d.getDay()] : longDate(c.labels[i]);
                return i === n - 1 ? 'Today (' + base + ')' : base;
            };
            if (top < 2) {
                lead = 'Activity was light: no day had more than one ' + unit + '.';
            } else if (tied.length > 3) {
                // too many to name: say so, and mark every tied day on the chart instead
                lead = '<b>' + tied.length + ' days</b> tied for busiest, with ' + plural(top, unit) + ' each (marked on the chart).';
                tied.forEach(function (p) { bands.push({ from: p.i, to: p.i + 1, labels: ['Busiest'], top: true }); });
            } else {
                var picks;
                if (tied.length >= 2) {
                    picks = tied.slice();            // an exact tie for first place: name every tied day (2 or 3)
                } else {
                    picks = [order[0]];
                    // a lone leader gets a runner-up only if it is close (at least 60% of it, and 2+)
                    if (order[1] && order[1].t >= Math.max(2, Math.ceil(top * 0.6))) picks.push(order[1]);
                }
                var pt = picks.reduce(function (a, p) { return a + p.t; }, 0);
                var chrono = picks.slice().sort(function (a, b) { return a.i - b.i; });
                var names = chrono.map(function (p) { return '<b>' + esc(nm(p.i)) + '</b>'; });
                lead = (names.length > 1 ? names.slice(0, -1).join(', ') + ' and ' + names[names.length - 1] : names[0]) +
                    (picks.length > 1 ? ' were the busiest days: ' + plural(pt, unit) + ' together' : ' was the busiest day: ' + plural(pt, unit)) +
                    ', ' + share(pt, c.grand) + '% of all ' + units + '.';
                rows = chrono.map(function (p) {
                    var d = parseYmd(c.labels[p.i]);
                    return rankRow(n <= 7 ? DOWF[d.getDay()] : longDate(c.labels[p.i]),
                        (n <= 7 ? shortDate(c.labels[p.i]) : '') + (p.i === n - 1 ? (n <= 7 ? ' · today' : 'today') : ''),
                        p.t, top, c.grand, true);
                }).join('');
                chrono.forEach(function (p) { bands.push({ from: p.i, to: p.i + 1, labels: ['Busiest'], top: true }); });
            }
        } else {
            heading = 'Busiest week';
            hint = 'Last 28 days, in 7-day blocks';
            var wk = [], wsum = 0, wmax = 0;
            for (var w = 0; w < 4; w++) {
                var st = n - 28 + w * 7, en = st + 7, tot = rangeSum(c.totals, st, en);
                wk.push({ w: w + 1, t: tot, st: st, en: en, from: c.labels[st], to: c.labels[en - 1] });
                wsum += tot; if (tot > wmax) wmax = tot;
            }
            var best = wk.filter(function (x) { return x.t === wmax; });
            var emphasise = wsum > 0 && best.length <= 2;     // 3+ way tie = nothing to single out
            if (wsum === 0) {
                lead = 'No ' + units + ' in the last 4 weeks.';
            } else if (best.length === 1) {
                lead = '<b>Week ' + best[0].w + '</b> (' + esc(span(best[0].from, best[0].to)) + ') was the busiest: ' +
                    plural(wmax, unit) + ', ' + share(wmax, wsum) + '% of the last 4 weeks.';
            } else if (best.length === 2) {
                lead = '<b>Weeks ' + best[0].w + ' and ' + best[1].w + '</b> tied for busiest, with ' + plural(wmax, unit) + ' each.';
            } else {
                lead = 'Activity was spread evenly across the weeks.';
            }
            if (wsum > 0) {
                rows = wk.map(function (x) {
                    return rankRow('Week ' + x.w, span(x.from, x.to) + (x.w === 4 ? ' · latest' : ''), x.t, wmax, wsum, emphasise && x.t === wmax);
                }).join('');
                wk.forEach(function (x) {
                    var isTop = emphasise && x.t === wmax;
                    bands.push({
                        from: x.st, to: x.en, top: isTop, sep: x.st > 0,   // a boundary wherever a week doesn't start at the left edge
                        labels: isTop ? ['Week ' + x.w + ' · busiest', 'Week ' + x.w, 'W' + x.w] : ['Week ' + x.w, 'W' + x.w]
                    });
                });
            }
        }

        /* "See more": two facts only */
        var ap = 0, de = 0;
        c.set.series.forEach(function (s) {
            if (s.key === 'approved') ap = rangeSum(s.values, N - n, N);
            if (s.key === 'declined') de = rangeSum(s.values, N - n, N);
        });
        var rate = (ap + de) > 0 ? Math.round(ap / (ap + de) * 100) : null;
        var facts = '';
        function fact(label, value, sub, isText) {
            facts += '<div class="dash-fact"><dt>' + esc(label) + '</dt><dd><span class="dash-fact-val' + (isText ? ' is-text' : '') + '">' +
                esc(value) + '</span>' + (sub ? '<span class="dash-fact-sub">' + esc(sub) + '</span>' : '') + '</dd></div>';
        }
        fact('Approval rate', rate == null ? '—' : rate + '%', rate == null ? 'Nothing decided yet' : ap + ' of ' + (ap + de) + ' decided');

        var ex = c.data.extras && c.data.extras[state.set];
        if (ex) {
            var bestRow = null, bestSum = 0;
            (ex.rows || []).forEach(function (r) {
                var t = rangeSum(r.values, N - n, N);
                if (t > bestSum) { bestSum = t; bestRow = r; }
            });
            if (state.set === 'equipment') {
                if (de === 0) fact(ex.title, 'None', 'Nothing was declined', true);
                else if (bestRow) fact(ex.title, bestRow.label, bestSum + ' of ' + de + ' declined', true);
            } else if (bestRow) {
                fact(ex.title, bestRow.label, bestSum + ' of ' + plural(ap, 'booking') + (bestRow.sub ? ' · ' + bestRow.sub : ''), true);
            }
        }

        var open = !!state.more;
        return {
            bands: bands,
            html: '<section class="dash-ins-main"><div class="dash-ins-head"><h3>' + esc(heading) + '</h3>' +
                (hint ? '<span class="dash-ins-hint">' + esc(hint) + '</span>' : '') + '</div>' +
                '<p class="dash-ins-lead">' + lead + '</p>' +
                (rows ? '<ul class="dash-rank">' + rows + '</ul>' : '') + '</section>' +
                '<div class="dash-ins-more" id="dashInsMore" aria-expanded="' + open + '"><dl class="dash-facts">' + facts + '</dl></div>' +
                '<div class="dash-ins-foot"><button type="button" class="dash-ins-toggle" aria-expanded="' + open + '" aria-controls="dashInsMore">' +
                '<span class="dash-ins-toggle-label">' + (open ? 'See less' : 'See more') + '</span>' +
                '<span class="material-symbols-outlined" aria-hidden="true">expand_more</span></button></div>'
        };
    }

    /* ── chart ───────────────────────────────────────────────── */
    function drawChart(wrap) {
        var data = wrap._data;
        if (!data) return;
        var set = data.sets[state.set] || data.sets.equipment;
        var n = state.range;
        var labels = data.labels.slice(-n);
        var series = set.series.map(function (s) {
            return { key: s.key, label: s.label, values: s.values.slice(-n) };
        });
        var totals = labels.map(function (_, i) {
            return series.reduce(function (t, s) { return t + (s.values[i] || 0); }, 0);
        });
        var grand = sum(totals);

        var chartEl = wrap.querySelector('[data-dash-chart]');
        var sumEl = wrap.querySelector('[data-dash-summary]');
        var insEl = wrap.querySelector('[data-dash-insights]');
        var N = data.labels.length;

        /* tallest day: only used to pick a sensible (invisible) scale for the bars */
        var peak = 0;
        totals.forEach(function (t) { if (t > peak) peak = t; });

        /* summary: ONE headline number, then how it was decided.
           (The old approval-rate / daily-average / busiest-day tiles and the
           separate legend are gone; per-day detail is still on hover.) */
        var parts = series.map(function (s) {
            return { key: s.key, label: s.label.toLowerCase(), n: sum(s.values) };
        });
        var unit = set.unit + (grand === 1 ? '' : 's');
        var splitHtml = '';
        if (grand > 0) {
            var sx = 0;
            splitHtml = '<div class="dash-split"><svg viewBox="0 0 100 1" preserveAspectRatio="none" aria-hidden="true" focusable="false">';
            parts.forEach(function (p) {
                if (!p.n) return;
                var w = p.n / grand * 100;
                splitHtml += '<rect class="dash-s-' + esc(p.key) + '" x="' + sx + '" y="0" width="' + w + '" height="1"/>';
                sx += w;
            });
            splitHtml += '</svg></div>';
        }
        /* how the headline compares with the period just before it (needs 2n days of history) */
        var prevTotal = null;
        if (N >= 2 * n) {
            prevTotal = 0;
            set.series.forEach(function (s) { prevTotal += rangeSum(s.values, N - 2 * n, N - n); });
        }
        sumEl.innerHTML =
            '<div class="dash-sum-head"><strong>' + grand + '</strong><span>' + esc(unit) + ' <small>in the last ' + n + ' days</small></span>' +
            chipHtml(trendChip(grand, prevTotal), 'vs previous ' + n + ' days') + '</div>' +
            splitHtml +
            (grand > 0
                ? '<ul class="dash-sum-legend">' + parts.map(function (p) {
                    return '<li' + (p.n ? '' : ' class="is-zero"') + '><i class="k-' + esc(p.key) + '"></i><b>' + p.n + '</b> ' + esc(p.label) + '</li>';
                }).join('') + '</ul>'
                : '');

        chartEl.setAttribute('aria-label', set.title + ', last ' + n + ' days: ' + grand + ' total' +
            (grand > 0 ? ' (' + parts.map(function (p) { return p.n + ' ' + p.label; }).join(', ') + ')' : '') + '.');

        if (grand === 0) {
            chartEl.innerHTML = '<div class="dash-chart-empty">No ' + esc(set.unit) + 's in the last ' + n + ' days.</div>';
            if (insEl) insEl.innerHTML = '';
            return;
        }

        var ins = insightsHtml({ data: data, set: set, n: n, N: N, labels: labels, totals: totals, grand: grand });
        if (insEl) insEl.innerHTML = ins.html;

        /* geometry */
        var W = Math.max(240, chartEl.clientWidth || 600);
        var H = Math.max(200, chartEl.clientHeight || 230);
        var m = { l: 4, r: 4, t: ins.bands.length ? 26 : 8, b: 24 };   // extra room on top for the band labels
        var pw = W - m.l - m.r, ph = H - m.t - m.b;
        var step = Math.max(1, Math.ceil(peak / 4));
        var top = step * 4;
        var slot = pw / n;
        var bw = Math.max(4, Math.min(30, slot * 0.64));
        var every = n <= 7 ? 1 : (n <= 14 ? 7 : 10);   // 7D: every day · 14D / 30D: just a few dates

        var out = '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" aria-hidden="true" focusable="false">';

        /* just a baseline: bar heights are relative, exact counts show on hover */
        out += '<line class="dash-baseline" x1="' + m.l + '" x2="' + (W - m.r) + '" y1="' + (m.t + ph) + '" y2="' + (m.t + ph) + '"/>';

        /* context bands: the busiest day(s), or the four weeks with the busiest one highlighted */
        ins.bands.forEach(function (bd) {
            var bx = m.l + slot * bd.from, bwid = slot * (bd.to - bd.from);
            out += '<g class="dash-band' + (bd.top ? ' is-top' : '') + '">';
            if (bd.top) out += '<rect class="dash-band-fill" x="' + (bx + 1) + '" y="2" width="' + (bwid - 2) + '" height="' + (m.t + ph - 2) + '" rx="8"/>';
            if (bd.sep) out += '<line class="dash-band-sep" x1="' + bx + '" x2="' + bx + '" y1="6" y2="' + (m.t + ph) + '"/>';
            var lab = '';                                   // longest label that fits the band's width
            for (var li = 0; li < bd.labels.length; li++) {
                if (bd.labels[li].length * 6.4 <= bwid - 4) { lab = bd.labels[li]; break; }
            }
            if (lab) out += '<text class="dash-band-label" x="' + (bx + bwid / 2) + '" y="16" text-anchor="middle">' + esc(lab) + '</text>';
            out += '</g>';
        });

        labels.forEach(function (lab, i) {
            var gx = m.l + slot * i;
            var x = gx + (slot - bw) / 2;
            var yCursor = m.t + ph;
            out += '<g class="dash-bar-group" data-i="' + i + '"><rect class="dash-bar-hit" x="' + gx + '" y="' + m.t + '" width="' + slot + '" height="' + ph + '" rx="6"/>';
            series.forEach(function (s) {
                var v = s.values[i] || 0;
                if (!v) return;
                var h = Math.max(2, v / top * ph);
                yCursor -= h;
                out += '<rect class="dash-s-' + esc(s.key) + '" x="' + x + '" y="' + yCursor + '" width="' + bw + '" height="' + h + '" rx="2"/>';
            });
            out += '</g>';
            if ((n - 1 - i) % every === 0) {
                var isToday = i === n - 1;
                out += '<text class="dash-axis-text' + (isToday ? ' dash-today-text' : '') + '" x="' + (gx + slot / 2) + '" y="' + (H - 6) + '" text-anchor="middle">' + esc(isToday ? 'Today' : (n <= 7 ? DOW[parseYmd(lab).getDay()] : shortDate(lab))) + '</text>';
            }
        });
        out += '</svg><div class="dash-tip" role="presentation"></div>';
        chartEl.innerHTML = out;

        /* tooltip */
        var tip = chartEl.querySelector('.dash-tip');
        function showTip(i, groupEl) {
            var rows = series.map(function (s) {
                return '<span><span><i class="k-' + esc(s.key) + '"></i>' + esc(s.label) + '</span><b>' + (s.values[i] || 0) + '</b></span>';
            }).join('');
            tip.innerHTML = '<b>' + esc(longDate(labels[i])) + '</b>' + rows;
            var bx = (m.l + slot * i + slot / 2) / W * chartEl.clientWidth;
            var tw = tip.offsetWidth || 140;
            var left = Math.min(Math.max(4, bx - tw / 2), chartEl.clientWidth - tw - 4);
            tip.style.left = left + 'px';
            tip.style.top = '4px';
            tip.classList.add('on');
            Array.prototype.forEach.call(chartEl.querySelectorAll('.dash-bar-group.is-active'), function (el) { el.classList.remove('is-active'); });
            if (groupEl) groupEl.classList.add('is-active');
        }
        function hideTip() {
            tip.classList.remove('on');
            Array.prototype.forEach.call(chartEl.querySelectorAll('.dash-bar-group.is-active'), function (el) { el.classList.remove('is-active'); });
        }
        chartEl.onpointermove = function (e) {
            var gEl = e.target.closest && e.target.closest('.dash-bar-group');
            if (gEl) showTip(+gEl.getAttribute('data-i'), gEl); else hideTip();
        };
        chartEl.onpointerleave = hideTip;
    }

    function initCharts() {
        Array.prototype.forEach.call(live.querySelectorAll('.dash-chart-wrap'), function (wrap) {
            try { wrap._data = JSON.parse(wrap.getAttribute('data-chart')); } catch (e) { wrap._data = null; }
            syncControls();
            drawChart(wrap);
        });
    }
    function syncControls() {
        Array.prototype.forEach.call(live.querySelectorAll('[data-dash-set]'), function (b) {
            var on = b.getAttribute('data-dash-set') === state.set;
            b.classList.toggle('active', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        Array.prototype.forEach.call(live.querySelectorAll('[data-dash-range]'), function (b) {
            var on = +b.getAttribute('data-dash-range') === state.range;
            b.classList.toggle('active', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    /* toggle "See more" in the insights panel (remembered, so redraws / live refreshes keep it open) */
    root.addEventListener('click', function (e) {
        var btn = e.target.closest('.dash-ins-toggle');
        if (!btn) return;
        state.more = btn.getAttribute('aria-expanded') !== 'true';
        var box = btn.closest('.dash-insights');
        var more = box && box.querySelector('.dash-ins-more');
        btn.setAttribute('aria-expanded', state.more ? 'true' : 'false');
        if (more) more.setAttribute('aria-expanded', state.more ? 'true' : 'false');
        btn.querySelector('.dash-ins-toggle-label').textContent = state.more ? 'See less' : 'See more';
    });

    /* redraw when the card's width changes (sidebar collapse, rotate, resize) */
    if (window.ResizeObserver) {
        var lastW = 0;
        new ResizeObserver(function () {
            var el = live.querySelector('[data-dash-chart]');
            if (!el) return;
            var w = el.clientWidth;
            if (Math.abs(w - lastW) < 2) return;
            lastW = w;
            var wrap = el.closest('.dash-chart-wrap');
            if (wrap) drawChart(wrap);
        }).observe(root);
    }

    /* ── clicks (delegated, so they survive live swaps) ──────── */
    root.addEventListener('click', function (e) {
        var go = e.target.closest('[data-dash-go]');
        if (go) {
            var tab = go.getAttribute('data-dash-go');
            var nav = document.querySelector('.nav-item[data-tab="' + tab + '"]');
            if (nav) {
                nav.click();
                var loanFilter = go.getAttribute('data-dash-loan-filter');
                if (loanFilter) {
                    var loanPanel = document.querySelector('#eqTabs .rq-filter-chip[data-eq-panel="eq-onloan"]');
                    if (loanPanel) loanPanel.click();
                    var loanTab = document.querySelector('#eqLoanFilter [data-loan-filter="' + loanFilter + '"]');
                    if (loanTab) loanTab.click();
                }
            }
            return;
        }
        var setBtn = e.target.closest('[data-dash-set]');
        if (setBtn) {
            state.set = setBtn.getAttribute('data-dash-set');
            syncControls();
            var w1 = live.querySelector('.dash-chart-wrap'); if (w1) drawChart(w1);
            return;
        }
        var rangeBtn = e.target.closest('[data-dash-range]');
        if (rangeBtn) {
            state.range = +rangeBtn.getAttribute('data-dash-range') || 14;
            syncControls();
            var w2 = live.querySelector('.dash-chart-wrap'); if (w2) drawChart(w2);
        }
    });

    /* ── live refresh ────────────────────────────────────────── */
    function dashboardVisible() {
        var panel = document.getElementById('panel-dashboard');
        return !!panel && panel.classList.contains('active') && !document.hidden;
    }
    function refresh() {
        if (stopped || inflight || !dashboardVisible()) return;
        inflight = true;
        fetch(window.location.pathname + '?fragment=dashboard', {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'fetch' }
        }).then(function (r) {
            // session ended -> the server redirects to the login page: stop quietly instead of swapping it in
            if (r.redirected || r.status === 401) { stopped = true; return null; }
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.text();
        }).then(function (html) {
            if (html == null) return;
            if (html.indexOf('dash-kpis') === -1) { stopped = true; return; }
            if (html === lastHtml) return;
            lastHtml = html;

            // keep keyboard focus on a chart toggle across the swap
            var ae = document.activeElement, refocus = '';
            if (ae && live.contains(ae)) {
                if (ae.hasAttribute('data-dash-set')) refocus = '[data-dash-set="' + ae.getAttribute('data-dash-set') + '"]';
                else if (ae.hasAttribute('data-dash-range')) refocus = '[data-dash-range="' + ae.getAttribute('data-dash-range') + '"]';
            }
            live.innerHTML = html;
            initCharts();
            if (refocus) { var f = live.querySelector(refocus); if (f) f.focus({ preventScroll: true }); }
        }).catch(function () { /* transient network error: try again next tick */ })
            .then(function () { inflight = false; });
    }

    setInterval(refresh, POLL_MS);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
    // coming back to the Dashboard tab -> refresh right away (after admin-dashboard.js flips the panel)
    document.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('.nav-item[data-tab="dashboard"]')) setTimeout(refresh, 80);
    });

    initCharts();
})();


/* ───────────────────────────────────────────────────────────────
   ROOMS -- building / floor pills + clickable tiles
   Additive block. The original Building / Floor / Status <select>s
   and the filter module in admin-dashboard.php stay the single
   source of truth: the pills below only READ their options and
   WRITE their value (then fire "change"), so searching, filtering,
   the live refresh after add / edit / archive, and the schedule
   modal all keep working exactly as before. Without JS the plain
   dropdowns stay visible. Status is a plain dropdown (no pills).
   ─────────────────────────────────────────────────────────────── */
(function () {
    function install() {
        var tb = document.getElementById('rmToolbar');
        var table = document.getElementById('roomsRegistryTable');
        var bld = document.getElementById('roomBuildingSelect');
        var flr = document.getElementById('roomFloorSelect');
        var st = document.getElementById('roomStatusSelect');
        var search = document.getElementById('roomSearchInput');
        var pillsEl = document.getElementById('rmBuildingPills');
        var floorsEl = document.getElementById('rmFloorChips');
        if (!tb || !table || !bld || !flr || !st || !search || !pillsEl || !floorsEl) return;

        var lastPillSig = '';

        function setSelect(sel, value) {
            if (sel.value === value) return;
            sel.value = value;
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function roomRows() { return table.querySelectorAll('tbody tr.pr-room-row'); }

        function el(tag, cls, text) {
            var n = document.createElement(tag);
            if (cls) n.className = cls;
            if (text !== undefined) n.textContent = text;
            return n;
        }

        function pill(cls, value, label, pressed) {
            var b = el('button', cls, label);
            b.type = 'button';
            b.dataset.value = value;
            b.setAttribute('aria-pressed', pressed ? 'true' : 'false');
            return b;
        }

        function buildPills() {
            var opts = Array.prototype.map.call(bld.options, function (o) {
                return [o.value, o.value === 'all' ? 'All' : o.textContent.replace(/\s+/g, ' ').trim()];
            });
            var sig = opts.map(function (o) { return o.join('|'); }).join('~');
            if (sig !== lastPillSig) {
                pillsEl.textContent = '';
                opts.forEach(function (o) { pillsEl.appendChild(pill('rm-bld', o[0], o[1], false)); });
                lastPillSig = sig;
            }
            Array.prototype.forEach.call(pillsEl.children, function (b) {
                b.setAttribute('aria-pressed', b.dataset.value === bld.value ? 'true' : 'false');
            });
        }

        function buildFloors() {
            floorsEl.textContent = '';
            var floorRow = document.getElementById('rmFloorRow');
            /* only worth showing when a building is picked and it has 2+ floors */
            if (flr.disabled || flr.options.length < 3) {
                if (floorRow) floorRow.hidden = true;
                return;
            }
            if (floorRow) floorRow.hidden = false;
            Array.prototype.forEach.call(flr.options, function (o) {
                floorsEl.appendChild(pill('rm-chip rm-chip--sm', o.value,
                    o.value === 'all' ? 'All floors' : o.textContent.trim(), flr.value === o.value));
            });
        }

        function sync() {
            var rows = roomRows(), shown = 0;
            rows.forEach(function (r) { if (r.style.display !== 'none') shown++; });
            buildPills();
            buildFloors();

            var searching = (search.value || '').trim().length > 0;
            tb.classList.toggle('rm-searching', searching);
            /* one building on screen -> its name is dropped from every floor heading */
            table.classList.toggle('rm-one-bld', bld.value !== 'all' && !searching);
            updateFloorCounts();
            tb.classList.add('rm-enhanced');
        }

        /* "6 rooms" / "2 of 6 rooms" after each floor name, counted from the rows the filters left visible */
        function updateFloorCounts() {
            var cur = null, shown = 0, total = 0;
            function flush() {
                if (!cur) return;
                var c = cur.querySelector('.pr-floor-count');
                if (!c) return;
                var noun = total === 1 ? ' facility' : ' facilities';
                c.textContent = shown === total ? total + noun : shown + ' of ' + total + noun;
            }
            table.querySelectorAll('tbody tr.pr-floor-divider, tbody tr.pr-room-row').forEach(function (r) {
                if (r.classList.contains('pr-floor-divider')) { flush(); cur = r; shown = 0; total = 0; return; }
                total++;
                if (r.style.display !== 'none') shown++;
            });
            flush();
        }

        /* pills / chips -> write to the real selects */
        tb.addEventListener('click', function (e) {
            var c = e.target.closest('.rm-chip, .rm-bld');
            if (!c || !tb.contains(c)) return;
            if (c.closest('#rmBuildingPills')) setSelect(bld, c.dataset.value);
            else if (c.closest('#rmFloorChips')) setSelect(flr, c.dataset.value);
        });

        /* keep in step with every change the filter module reacts to
           (our listeners are added later, so they run after its own) */
        bld.addEventListener('change', sync);
        flr.addEventListener('change', sync);
        st.addEventListener('change', sync);
        search.addEventListener('input', sync);

        /* the live refresh after add / edit / archive swaps in new rows */
        var orig = window.psRefreshRoomsRegistry;
        if (typeof orig === 'function' && !orig.__rmWrapped) {
            var wrapped = function () { var r = orig.apply(this, arguments); sync(); return r; };
            wrapped.__rmWrapped = true;
            window.psRefreshRoomsRegistry = wrapped;
        }

        /* a click anywhere on a tile (except its own buttons) opens that room's schedule */
        table.addEventListener('click', function (e) {
            var row = e.target.closest('tr.pr-room-row');
            if (!row || e.target.closest('a, button, .pr-row-actions')) return;
            var btn = row.querySelector('[data-action="open-room-schedule"]');
            if (btn) btn.click();
        });

        sync();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', install);
    else install();
})();


/* ═══════════════════════════════════════════════════════════════
   EQUIPMENT › sub-tabs (Catalog | On Loan) + On Loan filter/search
   Additive block. Scoped to #eqTabs / #eq-onloan; nothing else is touched.
   window.psOpenEquipmentTab('eq-onloan') can be called from deep links.
   ═══════════════════════════════════════════════════════════════ */
(function () {
    function install() {
        var tabs = document.getElementById('eqTabs');
        if (!tabs) return;

        /* Overdue warning for the On Loan tab. This used to be a banner at the top of the
           tab; it is now the bottom-centre pop-up alert and goes away on its own. */
        function announceOverdue() {
            var n = document.querySelectorAll('#eq-loan-table tbody tr.eq-loan-row[data-loan-state="overdue"]').length;
            if (n > 0 && typeof window.showToast === 'function') {
                window.showToast(n + ' item(s) are overdue. Contact the borrowers.', 'warning');
            }
        }

        function openPanel(panelId) {
            var chip = null;
            var loanPanel = document.getElementById('eq-onloan');
            var wasOnLoan = !!(loanPanel && loanPanel.classList.contains('active'));
            tabs.querySelectorAll('.rq-filter-chip').forEach(function (c) {
                var on = c.getAttribute('data-eq-panel') === panelId;
                c.classList.toggle('active', on);
                if (on) chip = c;
            });
            if (!chip) return;
            document.querySelectorAll('#panel-inventory .eq-sub-panel').forEach(function (p) {
                p.classList.toggle('active', p.id === panelId);
            });
            if (panelId === 'eq-onloan' && !wasOnLoan) announceOverdue();
        }
        window.psOpenEquipmentTab = openPanel;

        tabs.addEventListener('click', function (e) {
            var chip = e.target.closest('.rq-filter-chip[data-eq-panel]');
            if (chip) openPanel(chip.getAttribute('data-eq-panel'));
        });

        /* On Loan: status tabs + live search + pagination (20 per page) */
        var table = document.getElementById('eq-loan-table');
        var search = document.getElementById('eq-loan-search');
        var tabBar = document.getElementById('eqLoanFilter');
        var titleEl = document.getElementById('eqLoanTitle');
        var pager = document.getElementById('eq-loan-pager');
        var pgInfo = document.getElementById('eq-loan-pager-info');
        var pgCtl = document.getElementById('eq-loan-pager-ctl');
        var pgNums = document.getElementById('eq-loan-pg-nums');
        var pgPrev = document.getElementById('eq-loan-pg-prev');
        var pgNext = document.getElementById('eq-loan-pg-next');
        if (!table) return;

        var PAGE_SIZE = 20;
        var TITLES = {
            all: 'All Loans',
            waiting: 'Pending & Scheduled',
            active: 'Active Borrowings',
            overdue: 'Overdue Items',
            returned: 'Returned Equipment'
        };
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr.eq-loan-row'));
        var noMatch = table.querySelector('tr.eq-loan-nomatch');
        var mode = 'all';
        var query = '';
        var page = 1;
        var removedAny = false;   /* a row was removed this session (e.g. a cancelled booking) */

        function matchesMode(state) {
            if (mode === 'all') return true;
            if (mode === 'active') return state === 'ok' || state === 'due';
            if (mode === 'waiting') return state === 'waiting' || state === 'scheduled';
            return state === mode; /* overdue | returned */
        }

        function matchesQuery(row) {
            if (!query) return true;
            var d = row.dataset;
            var hay = [d.borrower, d.equipment, d.idNumber, '#' + String(d.id || '').padStart(4, '0')].join(' ').toLowerCase();
            return hay.indexOf(query) !== -1;
        }

        /* 1 … 4 5 6 … 12 — always first, last and the current page ±1 (same as the Equipment list) */
        function pageList(total, current) {
            var keep = [1, total, current - 1, current, current + 1];
            var list = [], last = 0;
            keep.filter(function (n, i) { return n >= 1 && n <= total && keep.indexOf(n) === i; })
                .sort(function (x, y) { return x - y; })
                .forEach(function (n) {
                    if (n - last > 1) list.push('gap');
                    list.push(n);
                    last = n;
                });
            return list;
        }

        function render() {
            var hits = rows.filter(function (r) { return matchesMode(r.dataset.loanState) && matchesQuery(r); });
            var total = hits.length;
            var pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
            page = Math.min(Math.max(page, 1), pages);
            var start = (page - 1) * PAGE_SIZE;
            var end = Math.min(start + PAGE_SIZE, total);
            var visible = hits.slice(start, end);

            rows.forEach(function (r) { r.style.display = visible.indexOf(r) !== -1 ? '' : 'none'; });
            if (noMatch) noMatch.hidden = !((rows.length > 0 || removedAny) && total === 0);

            if (!pager) return;
            pager.hidden = rows.length === 0;
            if (pgInfo) pgInfo.textContent = total ? 'Showing ' + (start + 1) + '\u2013' + end + ' of ' + total : 'No results';
            if (pgCtl) pgCtl.hidden = pages <= 1;
            if (pgPrev) pgPrev.disabled = page <= 1;
            if (pgNext) pgNext.disabled = page >= pages;
            if (pgNums) {
                pgNums.textContent = '';
                pageList(pages, page).forEach(function (n) {
                    if (n === 'gap') {
                        var gap = document.createElement('span');
                        gap.className = 'inv-pager-gap';
                        gap.textContent = '\u2026';
                        pgNums.appendChild(gap);
                        return;
                    }
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'inv-pager-btn' + (n === page ? ' is-active' : '');
                    btn.dataset.page = String(n);
                    btn.textContent = String(n);
                    btn.setAttribute('aria-label', 'Page ' + n);
                    if (n === page) btn.setAttribute('aria-current', 'page');
                    pgNums.appendChild(btn);
                });
            }
        }

        if (tabBar) {
            tabBar.addEventListener('click', function (e) {
                var b = e.target.closest('.eq-loan-tab');
                if (!b) return;
                mode = b.getAttribute('data-loan-filter') || 'all';
                tabBar.querySelectorAll('.eq-loan-tab').forEach(function (t) {
                    var on = t === b;
                    t.classList.toggle('active', on);
                    t.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                if (titleEl) titleEl.textContent = TITLES[mode] || TITLES.all;
                page = 1;
                render();
            });
        }
        if (search) {
            search.addEventListener('input', function () {
                query = this.value.trim().toLowerCase();
                page = 1;
                render();
            });
        }
        if (pgPrev) pgPrev.addEventListener('click', function () { page--; render(); });
        if (pgNext) pgNext.addEventListener('click', function () { page++; render(); });
        if (pgNums) {
            pgNums.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-page]');
                if (!btn) return;
                page = parseInt(btn.dataset.page, 10) || 1;
                render();
            });
        }

        /* ── Scheduled (later-date) bookings: cancel / revoke before they start ──
           They are approved by default, so an admin can still revoke one here. Uses the existing
           admin-override endpoint (Approved → Declined, reason required). A booking that has not
           started never took stock off the shelf, so nothing has to be given back. */
        var cModal = document.getElementById('ps-cancel-booking-modal');
        var cId = document.getElementById('cancel-booking-id');
        var cReason = document.getElementById('cancel-booking-reason');
        var cBtn = document.getElementById('ps-cancel-booking-confirm-btn');
        var cAlert = document.getElementById('ps-cancel-booking-alert');

        function cText(id, v) {
            var el = document.getElementById(id);
            if (el) el.textContent = v || '\u2014';
        }
        function cClearAlert() {
            if (cAlert) { cAlert.style.display = 'none'; cAlert.textContent = ''; }
        }
        function cShowAlert(msg) {
            if (!cAlert) return;
            cAlert.style.display = 'block';
            cAlert.style.background = '#ffeaea';
            cAlert.style.color = 'var(--danger)';
            cAlert.textContent = msg;
        }

        if (cModal && cBtn) {
            /* fill the modal from the clicked row (the generic ps-open-modal handler opens it) */
            document.addEventListener('click', function (e) {
                var trigger = e.target.closest('#eq-loan-table [data-modal="ps-cancel-booking-modal"]');
                if (!trigger) return;
                var tr = trigger.closest('tr[data-id]');
                if (!tr) return;
                var d = tr.dataset;
                if (cId) cId.value = d.id || '';
                cText('ps-cancel-booking-reqno', '#R-' + String(d.id || 0).padStart(4, '0'));
                cText('cancel-booking-borrower', d.borrower);
                cText('cancel-booking-equipment', d.equipment);
                cText('cancel-booking-start', d.startLabel);
                cText('cancel-booking-due', d.dueLabel);
                if (cReason) cReason.value = '';
                cBtn.disabled = true;
                cClearAlert();
            });

            if (cReason) {
                cReason.addEventListener('input', function () {
                    cBtn.disabled = this.value.trim().length < 5;
                });
            }

            cBtn.addEventListener('click', function () {
                var id = cId ? cId.value : '';
                var reason = cReason ? cReason.value.trim() : '';
                if (!id) return;
                if (reason.length < 5) {
                    cShowAlert('Please enter a reason (min. 5 characters).');
                    return;
                }
                var orig = cBtn.innerHTML;
                cBtn.disabled = true;
                cBtn.textContent = 'Cancelling\u2026';
                cClearAlert();

                var tok = document.querySelector('input[name="csrf_token"]');
                var fd = new FormData();
                fd.append('request_id', id);
                fd.append('new_status', 'Declined');
                fd.append('override_reason', reason);
                fd.append('csrf_token', tok ? tok.value : '');

                fetch('equipment-booking/api/admin-override.php', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.status === 'success') {
                            if (typeof window.showToast === 'function') window.showToast('Booking cancelled.');
                            cModal.classList.remove('ps-modal-open');
                            cBtn.innerHTML = orig;

                            /* take the row out of the table and update the Waiting count */
                            var tr = table.querySelector('tr.eq-loan-row[data-id="' + id + '"]');
                            var idx = rows.indexOf(tr);
                            if (idx !== -1) rows.splice(idx, 1);
                            if (tr && tr.parentNode) tr.parentNode.removeChild(tr);
                            removedAny = true;
                            var cnt = tabBar ? tabBar.querySelector('[data-loan-filter="waiting"] .eq-loan-tab-count') : null;
                            if (cnt) cnt.textContent = String(Math.max(0, (parseInt(cnt.textContent, 10) || 0) - 1));
                            render();
                        } else {
                            cShowAlert((data && data.message) || 'Action failed. Please try again.');
                            cBtn.disabled = false;
                            cBtn.innerHTML = orig;
                        }
                    })
                    .catch(function () {
                        cShowAlert('Network error. Please try again.');
                        cBtn.disabled = false;
                        cBtn.innerHTML = orig;
                    });
            });
        }

        /* Manual Return records a return verified by an administrator. It is
           deliberately independent of the faculty member's QR return token. */
        var returnModal = document.getElementById('ps-return-modal');
        var returnId = document.getElementById('manual-return-request-id');
        var returnCondition = document.getElementById('manual-return-condition');
        var returnConditionValue = document.getElementById('manual-return-condition-value');
        var returnNotes = document.getElementById('manual-return-notes');
        var returnBtn = document.getElementById('manual-return-confirm-btn');
        var returnForm = document.getElementById('manual-return-form');

        function setReturnSummary(index, value) {
            var cells = returnModal ? returnModal.querySelectorAll('.ps-req-summary .ps-rsv') : [];
            if (cells[index]) cells[index].textContent = value || '\u2014';
        }

        if (returnModal && returnBtn && returnForm) {
            document.addEventListener('click', function (e) {
                var trigger = e.target.closest('#eq-loan-table [data-modal="ps-return-modal"]');
                if (!trigger) return;
                var tr = trigger.closest('tr.eq-loan-row');
                if (!tr) return;
                var d = tr.dataset;
                if (returnId) returnId.value = d.id || '';
                setReturnSummary(0, d.borrower);
                setReturnSummary(1, d.idNumber);
                setReturnSummary(2, d.equipment + (d.qty && d.qty !== '1' ? ' \u00d7' + d.qty : ''));
                setReturnSummary(3, d.dueLabel);
                if (returnCondition) returnCondition.selectedIndex = 0;
                if (returnNotes) returnNotes.value = '';
            });

            returnForm.addEventListener('submit', function (e) {
                var id = returnId ? returnId.value : '';
                if (!id) {
                    e.preventDefault();
                    if (typeof window.showToast === 'function') window.showToast('Select a loan to return first.');
                    return;
                }
                var selected = returnCondition && returnCondition.options[returnCondition.selectedIndex];
                if (returnConditionValue) {
                    returnConditionValue.value = selected ? selected.text.split(/\s+/)[0] : 'Good';
                }
                returnBtn.disabled = true;
                returnBtn.textContent = 'Recording\u2026';
                try { sessionStorage.setItem('openManualReturnLoanTab', '1'); } catch (_) { }
            });
        }

        /* Send Notice: delivers an administrator-authored message to the
           borrower’s PUPSync notification inbox. */
        var noticeModal = document.getElementById('ps-notice-modal');
        var noticeForm = document.getElementById('overdue-notice-form');
        var noticeId = document.getElementById('overdue-notice-request-id');
        var noticePreview = document.getElementById('overdue-notice-preview');
        var noticeBtn = document.getElementById('overdue-notice-send-btn');

        function setNoticeSummary(index, value) {
            var cells = noticeModal ? noticeModal.querySelectorAll('.ps-req-summary .ps-rsv') : [];
            if (cells[index]) cells[index].textContent = value || '\u2014';
        }

        if (noticeModal && noticeForm && noticeBtn) {
            document.addEventListener('click', function (e) {
                var trigger = e.target.closest('#eq-loan-table [data-modal="ps-notice-modal"]');
                if (!trigger) return;
                var tr = trigger.closest('tr.eq-loan-row');
                if (!tr) return;
                var d = tr.dataset;
                var days = Math.max(1, parseInt(d.daysOverdue || '0', 10) || 1);
                var due = d.dueLabel || d.returnDateDisplay || 'the due date';
                if (noticeId) noticeId.value = d.id || '';
                setNoticeSummary(0, d.borrower);
                setNoticeSummary(1, d.idNumber);
                setNoticeSummary(2, d.equipment + (d.qty && d.qty !== '1' ? ' \u00d7' + d.qty : ''));
                setNoticeSummary(3, days + ' day' + (days === 1 ? '' : 's'));
                if (noticePreview) {
                    noticePreview.textContent = 'Dear ' + (d.borrower || 'Borrower') + ',\n\n'
                        + 'This is an official overdue-return notice from PUPSync Admin. '
                        + (d.equipment || 'The borrowed equipment') + ' was due on ' + due + ' and is now '
                        + days + ' day' + (days === 1 ? '' : 's') + ' overdue.\n\n'
                        + 'Please return this equipment to the Admin Office immediately. '
                        + 'Your borrowing access remains blocked until the return is recorded.\n\n'
                        + 'PUPSync Admin';
                }
            });

            noticeForm.addEventListener('submit', function (e) {
                if (!noticeId || !noticeId.value) {
                    e.preventDefault();
                    if (typeof window.showToast === 'function') window.showToast('Select an overdue loan first.');
                    return;
                }
                noticeBtn.disabled = true;
                noticeBtn.textContent = 'Sending\u2026';
                try { sessionStorage.setItem('openOverdueLoanTab', '1'); } catch (_) { }
            });
        }

        render();
        try {
            if (sessionStorage.getItem('openManualReturnLoanTab') === '1' || sessionStorage.getItem('openOverdueLoanTab') === '1') {
                sessionStorage.removeItem('openManualReturnLoanTab');
                sessionStorage.removeItem('openOverdueLoanTab');
                var onLoanTab = document.querySelector('#eqTabs .rq-filter-chip[data-eq-panel="eq-onloan"]');
                if (onLoanTab) onLoanTab.click();
                var overdueTab = tabBar ? tabBar.querySelector('[data-loan-filter="overdue"]') : null;
                if (overdueTab) overdueTab.click();
            }
        } catch (_) { }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', install);
    else install();
})();

/* ================================================================
   EQUIPMENT › ON LOAN › "Delete All"  (Super Admin only)
   1) the button opens a dialog; the server counts what is in the list
      (equipment-booking/api/delete-all-loans.php, dry_run=1) and the dialog
      lists exactly that
   2) the Super Admin types their own password and confirms
   3) the same endpoint re-checks role + password, puts back the stock of
      loans still out, deletes every record, then the page reloads on the
      On Loan tab. The password is wiped from the field as soon as it is sent
      or the dialog closes.
   ================================================================ */
(function () {
    'use strict';

    function $(id) { return document.getElementById(id); }
    function csrfToken() {
        var el = document.querySelector('input[name="csrf_token"]');
        return el ? el.value : '';
    }
    function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }

    function init() {
        var btn = $('eq-loan-wipe-btn');
        var modal = $('eq-loan-wipe-modal');
        if (!btn || !modal) return;   // not a Super Admin

        var summary = $('eq-loan-wipe-summary');
        var note = $('eq-loan-wipe-note');
        var pw = $('eq-loan-wipe-pw');
        var toggle = $('eq-loan-wipe-pw-toggle');
        var errBox = $('eq-loan-wipe-error');
        var okBtn = $('eq-loan-wipe-confirm');
        var okHtml = okBtn.innerHTML;

        var counted = false;      // the server has told us there is something to delete
        var busy = false;
        var leaving = false;      // success: the page is about to reload
        var loadSeq = 0;          // ignores a late "count" reply after the dialog was closed/reopened

        function isOpen() { return modal.classList.contains('ps-modal-open'); }
        function closeModal() { modal.classList.remove('ps-modal-open'); }
        function setError(msg) { errBox.textContent = msg; errBox.classList.remove('hidden'); }
        function clearError() { errBox.textContent = ''; errBox.classList.add('hidden'); }

        function resetForm() {
            pw.value = '';
            pw.type = 'password';
            toggle.querySelector('.material-symbols-outlined').textContent = 'visibility';
            clearError();
            note.textContent = '';
            note.classList.add('hidden');
            counted = false;
            busy = false;
            okBtn.disabled = true;
            okBtn.innerHTML = okHtml;
        }

        function line(text, num, cls) {
            var li = document.createElement('li');
            if (cls) li.className = cls;
            var a = document.createElement('span');
            a.textContent = text;
            var b = document.createElement('span');
            b.className = 'eq-wipe-num';
            b.textContent = String(num);
            li.appendChild(a);
            li.appendChild(b);
            return li;
        }

        function renderCounts(c) {
            summary.textContent = '';
            if (!c || !c.total) {
                var li = document.createElement('li');
                li.className = 'eq-wipe-loading';
                li.textContent = 'The loan list is already empty — there is nothing to delete.';
                summary.appendChild(li);
                counted = false;
                okBtn.disabled = true;
                return;
            }
            if (c.waiting) summary.appendChild(line('Pending requests (waiting for review)', c.waiting));
            if (c.scheduled) summary.appendChild(line('Scheduled (later-date) bookings', c.scheduled));
            if (c.out) summary.appendChild(line('Loans currently out (active or overdue)', c.out));
            if (c.returned) summary.appendChild(line('Returned loans (history)', c.returned));
            summary.appendChild(line('Total records to delete', c.total, 'is-total'));

            if (c.restore_units > 0) {
                note.textContent = plural(c.restore_units, 'unit', 'units') +
                    ' of equipment that are still out will be added back to the Catalog stock.';
                note.classList.remove('hidden');
            }
            counted = true;
            okBtn.disabled = false;
        }

        function open() {
            resetForm();
            summary.textContent = '';
            var loading = document.createElement('li');
            loading.className = 'eq-wipe-loading';
            loading.textContent = 'Counting records…';
            summary.appendChild(loading);
            modal.classList.add('ps-modal-open');

            var seq = ++loadSeq;
            fetch('equipment-booking/api/delete-all-loans.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ csrf_token: csrfToken(), dry_run: '1' }).toString()
            })
                .then(function (res) {
                    return res.json().catch(function () { return { status: 'error', message: 'Unexpected response from the server.' }; });
                })
                .then(function (data) {
                    if (seq !== loadSeq || !isOpen()) return;
                    if (data && data.status === 'success' && data.counts) {
                        renderCounts(data.counts);
                    } else {
                        summary.textContent = '';
                        setError((data && data.message) || 'Could not count the loan records. Please try again.');
                    }
                })
                .catch(function () {
                    if (seq !== loadSeq || !isOpen()) return;
                    summary.textContent = '';
                    setError('Network error. Please try again.');
                });

            setTimeout(function () { pw.focus(); }, 50);
        }

        function confirm() {
            if (busy || !counted) return;
            var pass = pw.value;
            if (pass === '') { setError('Enter your password to continue.'); pw.focus(); return; }

            busy = true;
            okBtn.disabled = true;
            okBtn.innerHTML = '<span class="material-symbols-outlined" style="animation:spin 0.8s linear infinite">progress_activity</span> Deleting…';
            clearError();

            fetch('equipment-booking/api/delete-all-loans.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ csrf_token: csrfToken(), current_password: pass }).toString()
            })
                .then(function (res) {
                    return res.json().catch(function () { return { status: 'error', message: 'Unexpected response from the server. Please try again.' }; });
                })
                .then(function (data) {
                    if (data && data.status === 'success') {
                        leaving = true;
                        pw.value = '';                // wipe the password straight away
                        closeModal();
                        try { sessionStorage.setItem('openLoanListTab', '1'); } catch (_) { }
                        // Reload so the table, tab counts, stock and dashboard figures all come from the server.
                        window.location.href = window.location.pathname + '?view=inventory&loans_cleared=1#tab-inventory';
                        return;
                    }
                    setError((data && data.message) || 'Could not delete the loan list. Please try again.');
                    if (data && data.code === 'bad_password') { pw.value = ''; pw.focus(); }
                })
                .catch(function () {
                    setError('Network error. Nothing was deleted. Please try again.');
                })
                .finally(function () {
                    if (leaving) return;
                    busy = false;
                    okBtn.innerHTML = okHtml;
                    okBtn.disabled = !counted;
                });
        }

        btn.addEventListener('click', open);
        okBtn.addEventListener('click', confirm);
        [$('eq-loan-wipe-close'), $('eq-loan-wipe-cancel')].forEach(function (el) {
            if (el) el.addEventListener('click', closeModal);
        });
        pw.addEventListener('input', clearError);
        pw.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); okBtn.click(); }
        });
        toggle.addEventListener('click', function () {
            var hidden = pw.type === 'password';
            pw.type = hidden ? 'text' : 'password';
            toggle.querySelector('.material-symbols-outlined').textContent = hidden ? 'visibility_off' : 'visibility';
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen() && !busy) closeModal();
        });

        // However the dialog gets closed (buttons, Esc, backdrop click) nothing stays in the field.
        new MutationObserver(function () {
            if (!isOpen()) { loadSeq++; pw.value = ''; pw.type = 'password'; }
        }).observe(modal, { attributes: true, attributeFilter: ['class'] });

        // After the reload, land back on the On Loan tab.
        try {
            if (sessionStorage.getItem('openLoanListTab') === '1') {
                sessionStorage.removeItem('openLoanListTab');
                setTimeout(function () {
                    if (typeof window.psOpenEquipmentTab === 'function') window.psOpenEquipmentTab('eq-onloan');
                }, 0);
            }
        } catch (_) { }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

/* ================================================================
   FACULTY REGISTRY UPLOAD
   Self-contained block. Uses PDF.js (loaded on demand) for PDF
   parsing. Falls back to a server-side parse notice for XLSX
   (SimpleXLSX is not vendored yet — the user is guided to use PDF).
   Calls upload-faculty-registry.php with the parsed rows as JSON.
================================================================ */
(function () {
    'use strict';

    var header = document.getElementById('fac-registry-header');
    var body = document.getElementById('fac-registry-body');
    var chevron = document.getElementById('fac-registry-chevron');
    var fileInput = document.getElementById('fac-reg-file-input');
    var fileLabel = document.getElementById('fac-reg-file-label');
    var fileNameEl = document.getElementById('fac-reg-file-name');
    var importBtn = document.getElementById('fac-reg-import-btn');
    var replaceChk = document.getElementById('fac-reg-replace-all');
    var alertEl = document.getElementById('fac-reg-alert');
    var previewWrap = document.getElementById('fac-reg-preview');
    var previewTbody = document.getElementById('fac-reg-preview-tbody');
    var previewCount = document.getElementById('fac-reg-preview-count');
    var clearBtn = document.getElementById('fac-reg-clear-btn');

    if (!header) return; // Faculty tab not rendered for this admin role

    // ── Collapse / expand ──────────────────────────────────────────
    function setOpen(open) {
        if (!body || !chevron || !header) return;
        body.style.display = open ? '' : 'none';
        chevron.style.transform = open ? 'rotate(180deg)' : '';
        header.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    header.addEventListener('click', function () {
        setOpen(body.style.display === 'none');
    });
    header.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setOpen(body.style.display === 'none'); }
    });

    // ── Alert helpers ──────────────────────────────────────────────
    function showRegAlert(msg, isError) {
        if (!alertEl) return;
        alertEl.className = 'alert-banner ' + (isError ? 'alert-danger' : 'alert-success');
        alertEl.textContent = msg;
        alertEl.classList.remove('hidden');
    }
    function clearRegAlert() {
        if (!alertEl) return;
        alertEl.classList.add('hidden');
        alertEl.textContent = '';
    }

    // ── Parsed rows store ──────────────────────────────────────────
    var _parsedRows = [];

    function resetParsed() {
        _parsedRows = [];
        if (previewWrap) previewWrap.style.display = 'none';
        if (previewTbody) previewTbody.innerHTML = '';
        if (fileInput) fileInput.value = '';
        if (fileNameEl) fileNameEl.textContent = 'Choose file (PDF or XLSX)\u2026';
        if (importBtn) importBtn.disabled = true;
        clearRegAlert();
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', resetParsed);
    }

    // ── Render preview table ───────────────────────────────────────
    function renderPreview(rows) {
        if (!previewTbody || !previewWrap || !previewCount) return;
        var html = '';
        var newlyHiredCount = 0;
        rows.forEach(function (r, i) {
            var isNew = r.faculty_id === '' || r.faculty_id.toUpperCase() === 'NEWLY HIRED';
            if (isNew) newlyHiredCount++;
            html +=
                '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + _esc(isNew ? 'NEWLY HIRED' : r.faculty_id) + '</td>' +
                '<td>' + _esc(r.full_name) + '</td>' +
                '<td style="text-align:center">' + (isNew ? '<span style="color:var(--warning,#e08000)">&#9679;</span>' : '') + '</td>' +
                '</tr>';
        });
        previewTbody.innerHTML = html;
        previewCount.textContent = rows.length + ' rows parsed' + (newlyHiredCount > 0 ? ' · ' + newlyHiredCount + ' newly hired' : '');
        previewWrap.style.display = '';
        if (importBtn) importBtn.disabled = rows.length === 0;
    }

    // XSS helper (re-uses the one in the outer IIFE if available, otherwise local)
    function _esc(str) {
        var d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    // ── PDF parsing via PDF.js ─────────────────────────────────────
    var _pdfJsReady = false;
    var _pdfJsLoading = false;

    function loadPdfJs(callback) {
        if (_pdfJsReady) { callback(); return; }
        if (_pdfJsLoading) { setTimeout(function () { loadPdfJs(callback); }, 100); return; }
        _pdfJsLoading = true;

        // Self-hosted — no CDN dependency, no CSP worker-src issues.
        var script = document.createElement('script');
        script.src = 'assets/js/vendor/pdf.min.js';
        script.onload = function () {
            if (window.pdfjsLib) {
                // Point the worker at the self-hosted copy using an absolute path
                // so it resolves correctly regardless of which tab is active.
                var base = window.location.origin +
                    window.location.pathname.replace(/\/[^/]*$/, '/');
                window.pdfjsLib.GlobalWorkerOptions.workerSrc =
                    base + 'assets/js/vendor/pdf.worker.min.js';
                window._pdfJsLib = window.pdfjsLib;
            }
            _pdfJsReady = true;
            _pdfJsLoading = false;
            callback();
        };
        script.onerror = function () {
            _pdfJsLoading = false;
            callback();
        };
        document.head.appendChild(script);
    }

    /**
     * Parse the Faculty Identification Records PDF.
     * Expected table format (from the document in the screenshot):
     *   Col 0: Faculty ID  (or "NEWLY HIRED")
     *   Col 1: Professor Name
     *   Col 2: Programs Handled  (stored as department)
     *
     * Strategy: extract all text items per page with their x/y positions,
     * cluster them into rows by y-coordinate, then columns by x-coordinate.
     * Rows whose first cell is a Faculty ID pattern or "NEWLY HIRED" are kept.
     */
    function parsePdf(file, onDone, onError) {
        loadPdfJs(function () {
            var pdfjsLib = window._pdfJsLib;
            if (!pdfjsLib) { onError('The PDF reader could not be loaded. Please check your internet connection and try again.'); return; }

            var reader = new FileReader();
            reader.onload = function (e) {
                var typedArray = new Uint8Array(e.target.result);
                pdfjsLib.getDocument({ data: typedArray }).promise.then(function (pdf) {
                    var allRows = [];
                    var pagePromises = [];
                    for (var p = 1; p <= pdf.numPages; p++) {
                        pagePromises.push(pdf.getPage(p).then(function (page) {
                            return page.getTextContent().then(function (tc) {
                                return tc.items;
                            });
                        }));
                    }

                    Promise.all(pagePromises).then(function (pages) {
                        pages.forEach(function (items) {
                            // Group items into lines by rounded y-coordinate
                            var lineMap = {};
                            items.forEach(function (item) {
                                if (!item.str || !item.str.trim()) return;
                                var y = Math.round(item.transform[5]);
                                if (!lineMap[y]) lineMap[y] = [];
                                lineMap[y].push({ x: item.transform[4], text: item.str.trim() });
                            });

                            // Sort lines top-to-bottom (descending y in PDF coords)
                            var yKeys = Object.keys(lineMap).map(Number).sort(function (a, b) { return b - a; });

                            yKeys.forEach(function (y) {
                                var cells = lineMap[y].sort(function (a, b) { return a.x - b.x; });
                                var col0 = (cells[0] && cells[0].text) || '';
                                var col1 = (cells[1] && cells[1].text) || '';
                                var col2 = cells.slice(2).map(function (c) { return c.text; }).join(' ');

                                // Skip header rows and empty rows
                                var c0up = col0.toUpperCase();
                                if (!col0 || c0up === 'FACULTY ID' || c0up === 'PROFESSOR NAME') return;

                                // Accept rows where col0 is a Faculty ID pattern OR "NEWLY HIRED"
                                var isFacultyId = /^FA\d+[A-Z]{2}\d+$/i.test(col0.replace(/\s/g, ''));
                                var isNewlyHired = c0up.replace(/\s+/g, ' ') === 'NEWLY HIRED';

                                if (!isFacultyId && !isNewlyHired) return;

                                // col1 may be split across cells — rejoin if needed
                                var name = col1;
                                if (!name) return;

                                allRows.push({
                                    faculty_id: isNewlyHired ? 'NEWLY HIRED' : col0.replace(/\s/g, '').toUpperCase(),
                                    full_name: name,
                                    department: col2
                                });
                            });
                        });

                        if (allRows.length === 0) {
                            onError(
                                'No faculty rows were found in this PDF. ' +
                                'Make sure it is the Faculty Identification Records document ' +
                                'and not a scanned image PDF.'
                            );
                            return;
                        }
                        onDone(allRows);
                    }).catch(function (err) {
                        onError('Could not read PDF pages: ' + err.message);
                    });
                }).catch(function (err) {
                    onError('Could not open PDF: ' + err.message);
                });
            };
            reader.onerror = function () { onError('Could not read the file.'); };
            reader.readAsArrayBuffer(file);
        });
    }

    // ── XLSX parsing — placeholder ─────────────────────────────────
    // SimpleXLSX is not yet vendored. Guide the user to PDF for now.
    function parseXlsx(file, onDone, onError) {
        onError('XLSX import is not yet available. Please export the Faculty Identification Records as a PDF and upload that instead.');
    }

    // ── File input change ──────────────────────────────────────────
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            var file = this.files && this.files[0];
            if (!file) return;
            resetParsed();
            fileNameEl.textContent = file.name;
            clearRegAlert();

            // Size guard: 10 MB
            if (file.size > 10 * 1024 * 1024) {
                showRegAlert('File is too large. Maximum size is 10 MB.', true);
                fileInput.value = '';
                fileNameEl.textContent = 'Choose file (PDF or XLSX)\u2026';
                return;
            }

            var ext = file.name.split('.').pop().toLowerCase();

            if (ext === 'pdf') {
                alertEl.className = 'alert-banner alert-info';
                alertEl.innerHTML = '<span class="fac-reg-parsing">Parsing PDF, this may take a moment</span>';
                alertEl.classList.remove('hidden');
                parsePdf(file,
                    function (rows) {
                        _parsedRows = rows;
                        renderPreview(rows);
                        clearRegAlert();
                    },
                    function (err) {
                        showRegAlert(err, true);
                        fileInput.value = '';
                        fileNameEl.textContent = 'Choose file (PDF or XLSX)\u2026';
                    }
                );
            } else if (ext === 'xlsx') {
                parseXlsx(file,
                    function (rows) {
                        _parsedRows = rows;
                        renderPreview(rows);
                        clearRegAlert();
                    },
                    function (err) {
                        showRegAlert(err, true);
                        fileInput.value = '';
                        fileNameEl.textContent = 'Choose file (PDF or XLSX)\u2026';
                    }
                );
            } else {
                showRegAlert('Unsupported file type. Please choose a PDF or XLSX file.', true);
                fileInput.value = '';
                fileNameEl.textContent = 'Choose file (PDF or XLSX)\u2026';
            }
        });
    }

    // ── Import button ──────────────────────────────────────────────
    if (importBtn) {
        importBtn.addEventListener('click', function () {
            if (_parsedRows.length === 0) {
                showRegAlert('No rows to import. Please choose a file first.', true);
                return;
            }

            var csrfEl = document.querySelector('input[name="csrf_token"]');
            var csrfToken = csrfEl ? csrfEl.value : '';
            if (!csrfToken) {
                showRegAlert('Session expired. Please refresh the page.', true);
                return;
            }

            importBtn.disabled = true;
            importBtn.innerHTML = '<span class="material-symbols-outlined" style="animation:spin 0.8s linear infinite">progress_activity</span> Importing\u2026';

            fetch('equipment-booking/api/upload-faculty-registry.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    rows: _parsedRows,
                    replace_all: replaceChk ? replaceChk.checked : false
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.status === 'success') {
                        var msg = 'Import complete: ' + data.inserted + ' added, ' + data.updated + ' updated';
                        if (data.skipped > 0) msg += ', ' + data.skipped + ' skipped';
                        if (data.errors && data.errors.length > 0) msg += '. Some rows had errors — check the page.';
                        showRegAlert(msg, false);

                        // Show row-level errors if any
                        if (data.errors && data.errors.length > 0) {
                            var errHtml = data.errors.map(function (e) { return '<li>' + _esc(e) + '</li>'; }).join('');
                            alertEl.innerHTML = alertEl.textContent + '<ul style="margin:.5rem 0 0;padding-left:1.2rem;font-size:11px">' + errHtml + '</ul>';
                        }

                        // Reset file picker; keep the preview visible so Super Admin can see what was imported
                        if (fileInput) fileInput.value = '';
                        if (fileNameEl) fileNameEl.textContent = 'Choose file (PDF or XLSX)\u2026';
                        _parsedRows = [];
                        if (importBtn) importBtn.disabled = true;

                        // Update the badge count without a full page reload
                        var badge = document.querySelector('#fac-registry-card .fac-reg-badge');
                        if (badge && typeof data.total_after !== 'undefined') {
                            badge.textContent = data.total_after + ' entries';
                            badge.className = 'fac-reg-badge fac-reg-badge--active';
                        }

                        // Show the "View registry list" button once the registry has entries
                        var viewRow = document.getElementById('fac-reg-view-row');
                        if (viewRow && typeof data.total_after !== 'undefined') viewRow.hidden = !(data.total_after > 0);
                    } else {
                        showRegAlert(data.error || 'Import failed. Please try again.', true);
                        if (importBtn) importBtn.disabled = false;
                    }
                })
                .catch(function () {
                    showRegAlert('Network error. Please try again.', true);
                    if (importBtn) importBtn.disabled = false;
                })
                .finally(function () {
                    importBtn.innerHTML = '<span class="material-symbols-outlined">cloud_upload</span> Import';
                });
        });
    }

    // ── File label click → trigger hidden input ────────────────────
    if (fileLabel) {
        fileLabel.addEventListener('click', function () {
            if (fileInput) fileInput.click();
        });
    }

})();


/* ================================================================
   FACULTY REGISTRY — "View registry list" (password-gated)
   Faculty tab -> Faculty Registry -> View registry list.
   1) button opens a small modal asking for the Super Admin's own password
   2) equipment-booking/api/view-faculty-registry.php checks it server-side and
      only then returns the entries (read-only)
   3) the list opens in a searchable modal; the data (and the password field)
      are wiped from memory as soon as either modal closes
   ================================================================ */
(function () {
    'use strict';

    var RENDER_LIMIT = 200;   // rows drawn at once; the search box narrows the rest
    var _rows = [];           // entries held in memory only while the list modal is open

    function $(id) { return document.getElementById(id); }
    function csrfToken() {
        var el = document.querySelector('input[name="csrf_token"]');
        return el ? el.value : '';
    }
    function openModal(id) { var m = $(id); if (m) m.classList.add('ps-modal-open'); }
    function closeModal(id) { var m = $(id); if (m) m.classList.remove('ps-modal-open'); }
    function isOpen(id) { var m = $(id); return !!(m && m.classList.contains('ps-modal-open')); }

    function init() {
        var viewBtn = $('fac-reg-view-btn');
        var pwInput = $('fac-reg-auth-password');
        var errBox = $('fac-reg-auth-error');
        var okBtn = $('fac-reg-auth-confirm');
        var tbody = $('fac-reg-list-tbody');
        var searchEl = $('fac-reg-list-search');
        var emptyEl = $('fac-reg-list-empty');
        var metaEl = $('fac-reg-list-meta');
        var countEl = $('fac-reg-list-count');
        if (!viewBtn || !pwInput || !okBtn || !tbody) return; // not the Super Admin Faculty tab

        var okBtnHtml = okBtn.innerHTML;

        function showAuthError(msg) {
            errBox.textContent = msg;
            errBox.classList.remove('hidden');
        }
        function clearAuthError() {
            errBox.textContent = '';
            errBox.classList.add('hidden');
        }
        function resetAuth() {
            pwInput.value = '';
            pwInput.type = 'password';
            clearAuthError();
            okBtn.disabled = false;
            okBtn.innerHTML = okBtnHtml;
        }

        /* ── Step 1: password prompt ───────────────────────────── */
        viewBtn.addEventListener('click', function () {
            resetAuth();
            openModal('fac-reg-auth-modal');
            setTimeout(function () { pwInput.focus(); }, 50);
        });

        ['fac-reg-auth-close', 'fac-reg-auth-cancel'].forEach(function (id) {
            var el = $(id);
            if (el) el.addEventListener('click', function () { closeModal('fac-reg-auth-modal'); });
        });

        pwInput.addEventListener('input', clearAuthError);
        pwInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); okBtn.click(); }
        });

        okBtn.addEventListener('click', function () {
            if (okBtn.disabled) return;
            var pw = pwInput.value;
            if (pw === '') { showAuthError('Enter your password to continue.'); pwInput.focus(); return; }

            okBtn.disabled = true;
            okBtn.innerHTML = '<span class="material-symbols-outlined" style="animation:spin 0.8s linear infinite">progress_activity</span> Checking\u2026';
            clearAuthError();

            fetch('equipment-booking/api/view-faculty-registry.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    csrf_token: csrfToken(),
                    current_password: pw
                }).toString()
            })
                .then(function (res) {
                    return res.json().catch(function () {
                        return { status: 'error', error: 'Unexpected response from the server. Please try again.' };
                    });
                })
                .then(function (data) {
                    if (data && data.status === 'success' && Array.isArray(data.entries)) {
                        _rows = data.entries;
                        resetAuth();                       // wipe the password straight away
                        closeModal('fac-reg-auth-modal');
                        openList();
                    } else {
                        showAuthError((data && data.error) || 'Could not open the registry. Please try again.');
                        if (data && data.code === 'bad_password') { pwInput.value = ''; pwInput.focus(); }
                    }
                })
                .catch(function () {
                    showAuthError('Network error. Please try again.');
                })
                .finally(function () {
                    okBtn.disabled = false;
                    okBtn.innerHTML = okBtnHtml;
                });
        });

        /* ── Step 2: the list ──────────────────────────────────── */
        function td(text, cls) {
            var c = document.createElement('td');
            if (cls) c.className = cls;
            c.textContent = text;
            return c;
        }

        function renderList() {
            var q = (searchEl ? searchEl.value : '').trim().toLowerCase();
            var matches = !q ? _rows : _rows.filter(function (r) {
                return (r.faculty_id || '').toLowerCase().indexOf(q) !== -1 ||
                    (r.full_name || '').toLowerCase().indexOf(q) !== -1 ||
                    (r.department || '').toLowerCase().indexOf(q) !== -1;
            });

            tbody.textContent = '';
            matches.slice(0, RENDER_LIMIT).forEach(function (r, i) {
                var tr = document.createElement('tr');
                tr.appendChild(td(String(i + 1)));
                if (r.faculty_id) tr.appendChild(td(r.faculty_id));
                else tr.appendChild(td('Not assigned yet', 'fac-reg-list-pending'));
                tr.appendChild(td(r.full_name || ''));
                tr.appendChild(td(r.department || '\u2014'));
                tbody.appendChild(tr);
            });

            if (emptyEl) emptyEl.classList.toggle('hidden', matches.length > 0);
            if (metaEl) {
                if (matches.length > RENDER_LIMIT) {
                    metaEl.textContent = 'Showing first ' + RENDER_LIMIT + ' of ' + matches.length +
                        (q ? ' matches' : ' entries') + ' \u2014 search to narrow down';
                } else {
                    metaEl.textContent = q
                        ? matches.length + (matches.length === 1 ? ' match' : ' matches') + ' of ' + _rows.length
                        : _rows.length + (_rows.length === 1 ? ' entry' : ' entries');
                }
            }
        }

        function openList() {
            if (searchEl) searchEl.value = '';
            if (countEl) countEl.textContent = '(' + _rows.length + ')';
            renderList();
            openModal('fac-reg-list-modal');
            if (searchEl) setTimeout(function () { searchEl.focus(); }, 50);
        }

        if (searchEl) searchEl.addEventListener('input', renderList);

        ['fac-reg-list-close', 'fac-reg-list-done'].forEach(function (id) {
            var el = $(id);
            if (el) el.addEventListener('click', function () { closeModal('fac-reg-list-modal'); });
        });

        /* ── Clean-up: however a modal gets closed (buttons, backdrop click,
              Esc), drop the registry data / password from the page ── */
        function watch(id, onClosed) {
            var m = $(id);
            if (!m || typeof MutationObserver === 'undefined') return;
            new MutationObserver(function () {
                if (!m.classList.contains('ps-modal-open')) onClosed();
            }).observe(m, { attributes: true, attributeFilter: ['class'] });
        }
        watch('fac-reg-auth-modal', resetAuth);
        watch('fac-reg-list-modal', function () {
            _rows = [];
            tbody.textContent = '';
            if (searchEl) searchEl.value = '';
            if (viewBtn) viewBtn.focus();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (isOpen('fac-reg-list-modal')) closeModal('fac-reg-list-modal');
            else if (isOpen('fac-reg-auth-modal')) closeModal('fac-reg-auth-modal');
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();