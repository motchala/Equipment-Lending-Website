/* ================================================================
   admin-dashboard-mobile.js
   Phone app shell for the Super Admin / Admin portal.

   Active ONLY when <html> has the class "rx-mobile" (set by the small
   detection script in <head> of admin-shell-top.php: phones, also in
   landscape, or any window <= 600px wide). Tablets and desktops keep
   the layout they have today.

   Builds (all hidden by CSS unless rx-mobile is on):
     - top app bar     : title / back arrow, notification bell, avatar
     - bottom tab bar  : Dashboard . Activity . Equipment . Facilities
     - account sheet   : Settings, Help & FAQ, Notifications, "desktop-only"
                         info, Log out
     - desktop notice  : dismissible banner on the Dashboard + the sheet that
                         lists what is only available on a bigger screen

   It never re-implements app logic: every control here clicks an
   existing one (the hidden sidebar items, the bell, Log Out), so
   admin-dashboard.js stays the single source of truth.

   Desktop-only screens (Faculty, Admins, Imports, Reports and the
   Settings tabs Arbitration / Data / Service Ratings) are blocked at
   the source, in _switchTabDOM / switchSettMainTab (admin-dashboard.js).
   enforce() below is the safety net for when the window changes size.
================================================================ */
(function () {
    'use strict';

    var D = document, root = D.documentElement;
    var $ = function (s, r) { return (r || D).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || D).querySelectorAll(s)); };

    var TABS = [
        { tab: 'dashboard', label: 'Dashboard', icon: 'dashboard', title: 'PUPSync' },
        { tab: 'activity', label: 'Activity', icon: 'history', title: 'Activity' },
        { tab: 'inventory', label: 'Equipment', icon: 'inventory_2', title: 'Equipment' },
        { tab: 'rooms', label: 'Facilities', icon: 'meeting_room', title: 'Facilities' }
    ];
    var DESKTOP_TABS = ['faculty', 'admins', 'imports', 'reports'];
    var DESKTOP_SETT = ['sett-arbitration', 'sett-data', 'sett-ratings'];
    var LABELS = {
        faculty: 'Faculty accounts', admins: 'Admin accounts', imports: 'Imports', reports: 'Reports',
        'sett-arbitration': 'Arbitration rules', 'sett-data': 'Data tools', 'sett-ratings': 'Service ratings'
    };
    var NOTE_KEY = 'rxmAdminNoteDismissed';

    function icon(name, cls) {
        return '<span class="material-symbols-outlined' + (cls ? ' ' + cls : '') + '" aria-hidden="true">' + name + '</span>';
    }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function el(tag, cls, html) {
        var e = D.createElement(tag);
        if (cls) e.className = cls;
        if (html != null) e.innerHTML = html;
        return e;
    }
    function isMobile() { return root.classList.contains('rx-mobile'); }
    function isSuper() {
        var s = $('.sidebar-sub');
        return !!(s && /super/i.test(s.textContent));
    }
    function roleName() { return isSuper() ? 'Super Admin' : 'Admin'; }

    /* ------------------------------------------------------------
       Current screen
    ------------------------------------------------------------ */
    function currentTab() {
        var p = $('.tab-panel.active');
        return p && p.id ? p.id.replace('panel-', '') : 'dashboard';
    }
    function isMainTab(t) { return TABS.some(function (x) { return x.tab === t; }); }
    var lastMain = 'dashboard';

    /* Go to a screen by clicking its (hidden) sidebar item: the original handler does the switching. */
    function goTab(tab) {
        var nav = $('.nav-item[data-tab="' + tab + '"]');
        if (nav) nav.click();
    }

    /* ------------------------------------------------------------
       "Desktop only" wording (called by the guard in admin-dashboard.js)
    ------------------------------------------------------------ */
    window.psDesktopOnlyNotice = function (what) {
        if (!isMobile()) return;
        var label = LABELS[what] || 'That screen';
        var msg = label + ' is only available on a desktop or larger screen.';
        if (typeof window.showToast === 'function') window.showToast(msg, 'info', 4500);
    };

    /* ------------------------------------------------------------
       1. APP BAR
    ------------------------------------------------------------ */
    var bar, barBack, barTitle, barBadge, barAvatar;

    function buildBar() {
        bar = el('header', 'rxm-bar');
        bar.id = 'rxmBar';
        bar.innerHTML =
            '<button type="button" class="rxm-iconbtn rxm-back" id="rxmBack" aria-label="Back" hidden>' + icon('arrow_back') + '</button>' +
            '<span class="rxm-logo" aria-hidden="true">' + icon('layers') + '</span>' +
            '<h1 class="rxm-title" id="rxmTitle">PUPSync</h1>' +
            '<div class="rxm-actions">' +
            '<button type="button" class="rxm-iconbtn rxm-bell" id="rxmBell" aria-label="Notifications">' + icon('notifications') +
            '<b class="rxm-badge" id="rxmBadge" hidden></b></button>' +
            '<button type="button" class="rxm-avatar" id="rxmAvatar" aria-label="Account menu" aria-haspopup="dialog"></button>' +
            '</div>';
        D.body.appendChild(bar);
        barBack = $('#rxmBack', bar); barTitle = $('#rxmTitle', bar);
        barBadge = $('#rxmBadge', bar); barAvatar = $('#rxmAvatar', bar);

        $('#rxmBell', bar).addEventListener('click', openNotifications);
        barAvatar.addEventListener('click', openAccountSheet);
        barBack.addEventListener('click', function () { goTab(lastMain || 'dashboard'); });

        var main = $('#app-main');
        if (main) main.addEventListener('scroll', function () {
            bar.classList.toggle('is-scrolled', main.scrollTop > 4);
        }, { passive: true });
    }

    function openNotifications() {
        var orig = $('#dashNotifBtn') || $('[data-action="open-notif-modal"]');
        if (orig) orig.click();
    }

    function unreadCount() {
        var ob = $('#dashNotifBadge');
        if (!ob || ob.style.display === 'none') return 0;
        return parseInt(ob.textContent, 10) || 0;
    }

    var shownTab = null;
    function syncBar() {
        if (!bar) return;
        var t = currentTab();
        if (isMainTab(t)) lastMain = t;
        var def = TABS.filter(function (x) { return x.tab === t; })[0];
        var inSettings = t === 'settings';
        barTitle.textContent = inSettings ? 'Settings' : (def ? def.title : 'PUPSync');
        barBack.hidden = !inSettings;
        bar.classList.toggle('is-home', t === 'dashboard');
        bar.classList.toggle('has-back', inSettings);

        var n = unreadCount();
        barBadge.hidden = n <= 0;
        if (n > 0) barBadge.textContent = n > 99 ? '99+' : n;
        var av = $('#dashAvatarBtn');
        barAvatar.textContent = (av && av.textContent.trim()) || 'A';

        // a new screen starts at the top (all screens share one scroll area)
        if (shownTab !== null && shownTab !== t) {
            var main = $('#app-main'); if (main) main.scrollTop = 0;
        }
        shownTab = t;
        root.setAttribute('data-rxm-tab', t);
    }

    /* ------------------------------------------------------------
       2. TAB BAR
    ------------------------------------------------------------ */
    var tabbar;

    function buildTabbar() {
        tabbar = el('nav', 'rxm-tabbar');
        tabbar.id = 'rxmTabbar';
        tabbar.setAttribute('aria-label', 'Main');
        tabbar.innerHTML = TABS.map(function (t) {
            return '<button type="button" class="rxm-tab" data-rxm-tab="' + t.tab + '">' +
                '<span class="rxm-tab-pill">' + icon(t.icon) + '</span>' +
                '<span class="rxm-tab-label">' + t.label + '</span></button>';
        }).join('');
        D.body.appendChild(tabbar);

        tabbar.addEventListener('click', function (e) {
            var b = e.target.closest('.rxm-tab');
            if (!b) return;
            var tab = b.dataset.rxmTab;
            if (currentTab() === tab) {
                // tapping the tab you are on refreshes it in place and returns to the top
                if (typeof window.psRefreshActiveTab === 'function') window.psRefreshActiveTab(tab);
                var main = $('#app-main'); if (main) main.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                goTab(tab);
            }
        });
    }

    function syncTabbar() {
        if (!tabbar) return;
        var t = currentTab();
        $$('.rxm-tab', tabbar).forEach(function (b) {
            if (b.dataset.rxmTab === t) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current');
        });
    }

    /* ------------------------------------------------------------
       3. BOTTOM SHEET
    ------------------------------------------------------------ */
    var sheet, sheetBody, sheetTitle, sheetLast;

    function buildSheet() {
        sheet = el('div', 'rxm-sheet');
        sheet.id = 'rxmSheet';
        sheet.setAttribute('role', 'dialog');
        sheet.setAttribute('aria-modal', 'true');
        sheet.hidden = true;
        sheet.innerHTML =
            '<div class="rxm-sheet-scrim" data-rxm-close></div>' +
            '<div class="rxm-sheet-panel" role="document">' +
            '<span class="rxm-sheet-grip" aria-hidden="true"></span>' +
            '<div class="rxm-sheet-head"><h2 class="rxm-sheet-title" id="rxmSheetTitle"></h2>' +
            '<button type="button" class="rxm-iconbtn" data-rxm-close aria-label="Close">' + icon('close') + '</button></div>' +
            '<div class="rxm-sheet-body" id="rxmSheetBody"></div></div>';
        D.body.appendChild(sheet);
        sheetBody = $('#rxmSheetBody', sheet); sheetTitle = $('#rxmSheetTitle', sheet);

        sheet.addEventListener('click', function (e) {
            if (e.target.closest('[data-rxm-close]')) { closeSheet(); return; }
            var act = e.target.closest('[data-rxm-do]');
            if (act) runAction(act.getAttribute('data-rxm-do'));
        });
        D.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !sheet.hidden) closeSheet(); });
    }

    function openSheet(title, html, kind) {
        sheetTitle.textContent = title || '';
        sheet.dataset.kind = kind || '';
        sheetBody.innerHTML = html;
        sheetLast = D.activeElement;
        sheet.hidden = false;
        requestAnimationFrame(function () { sheet.classList.add('is-open'); });
        var first = $('button, [href]', sheetBody); if (first) first.focus({ preventScroll: true });
    }
    function closeSheet() {
        if (sheet.hidden) return;
        sheet.classList.remove('is-open');
        setTimeout(function () { if (!sheet.classList.contains('is-open')) { sheet.hidden = true; sheetBody.innerHTML = ''; } }, 240);
        if (sheetLast && sheetLast.focus) try { sheetLast.focus({ preventScroll: true }); } catch (e) { }
    }

    function row(ic, label, sub, doName, trailing) {
        return '<li><button type="button" class="rxm-row" data-rxm-do="' + doName + '">' +
            '<span class="rxm-row-icon">' + icon(ic) + '</span>' +
            '<span class="rxm-row-text"><span class="rxm-row-label">' + label + '</span>' + (sub ? '<span class="rxm-row-sub">' + sub + '</span>' : '') + '</span>' +
            (trailing || '') + icon('chevron_right', 'rxm-row-go') + '</button></li>';
    }

    function openAccountSheet() {
        var nameEl = $('.nav-account-name');
        var name = (nameEl && nameEl.textContent.trim()) || 'Admin';
        var n = unreadCount();
        var unread = n > 0 ? '<b class="rxm-row-badge">' + esc(n > 99 ? '99+' : n) + '</b>' : '';
        var html =
            '<div class="rxm-acct">' +
            '<span class="rxm-acct-avatar">' + esc(barAvatar.textContent) + '</span>' +
            '<div class="rxm-acct-text"><strong class="rxm-trunc">' + esc(name) + '</strong><span>PUPSync ' + roleName() + '</span></div></div>' +
            '<ul class="rxm-list">' +
            row('settings', 'Settings', 'Account, appearance, notifications', 'settings') +
            row('help', 'Help &amp; FAQ', '', 'help') +
            row('notifications', 'Notifications', '', 'notif', unread) +
            '</ul>' +
            '<ul class="rxm-list">' +
            row('desktop_windows', 'Best on a desktop', 'See what is not available on mobile', 'desktop') +
            '</ul>' +
            '<ul class="rxm-list rxm-list--danger">' +
            row('logout', 'Log out', '', 'logout') +
            '</ul>';
        openSheet('Account', html, 'account');
    }

    /* What is desktop-only, per role */
    function desktopItems() {
        var items = [
            ['summarize', 'Reports', 'Generate and download PDF or Excel'],
            ['star', 'Service ratings', 'Survey results and settings'],
            ['download', 'Activity export', 'Download the timeline as a spreadsheet'],
            ['add_box', 'Adding new equipment', 'You can still edit existing items']
        ];
        if (isSuper()) {
            items = [
                ['group', 'Faculty accounts', 'Create, edit and remove faculty'],
                ['admin_panel_settings', 'Admin accounts', 'Create and manage admins'],
                ['upload_file', 'Imports', 'Faculty registry and room schedules']
            ].concat(items, [
                ['gavel', 'Arbitration rules', 'Borrowing and booking rules'],
                ['database', 'Data tools', 'Clear equipment and room history'],
                ['add_business', 'Adding new facilities', 'You can still edit existing ones'],
                ['delete_forever', 'Delete all / permanent delete', 'Destructive bulk actions']
            ]);
        }
        return items;
    }

    function openDesktopSheet() {
        var html =
            '<p class="rxm-lead">PUPSync is best viewed on a desktop or larger screen. These features are not available on mobile:</p>' +
            '<ul class="rxm-list rxm-list--info">' + desktopItems().map(function (i) {
                return '<li class="rxm-info"><span class="rxm-row-icon">' + icon(i[0]) + '</span>' +
                    '<span class="rxm-row-text"><span class="rxm-row-label">' + i[1] + '</span><span class="rxm-row-sub rxm-wrap">' + i[2] + '</span></span></li>';
            }).join('') + '</ul>' +
            '<p class="rxm-tip">' + icon('lightbulb') + '<span>To use them, open PUPSync on a computer, or choose <b>Desktop site</b> in your browser menu.</span></p>';
        openSheet('Desktop only', html, 'desktop');
    }

    function runAction(name) {
        switch (name) {
            case 'settings': closeSheet(); goTab('settings'); break;
            case 'help':
                closeSheet(); goTab('settings');
                setTimeout(function () { var t = $('#settMainTabs [data-sett-panel="sett-help"]'); if (t) t.click(); }, 60);
                break;
            case 'notif': closeSheet(); setTimeout(openNotifications, 120); break;
            case 'desktop': closeSheet(); setTimeout(openDesktopSheet, 260); break;
            case 'logout':
                closeSheet();
                setTimeout(function () { var b = $('#snav-logout'); if (b) b.click(); }, 120);
                break;
        }
    }

    /* ------------------------------------------------------------
       4. DESKTOP NOTICE (banner on the Dashboard)
    ------------------------------------------------------------ */
    function noteDismissed() {
        try { return sessionStorage.getItem(NOTE_KEY) === '1'; } catch (e) { return false; }
    }
    function buildNote() {
        var panel = $('#panel-dashboard');
        if (!panel || $('#rxmNote') || noteDismissed()) return;
        var head = $('#dash > .dash-head');
        var n = el('aside', 'rxm-note',
            icon('desktop_windows', 'rxm-note-ico') +
            '<div class="rxm-note-text"><strong>Best viewed on a desktop</strong>' +
            '<span>Some features are not available on mobile. Switch to desktop view in your browser to use everything.</span>' +
            '<button type="button" class="rxm-note-link" data-rxm-note-more>See what is desktop-only</button></div>' +
            '<button type="button" class="rxm-iconbtn rxm-note-x" aria-label="Dismiss" data-rxm-note-x>' + icon('close') + '</button>');
        n.id = 'rxmNote';
        n.setAttribute('role', 'note');
        if (head && head.parentNode) head.parentNode.insertBefore(n, head.nextSibling);
        else panel.insertBefore(n, panel.firstChild);
        n.addEventListener('click', function (e) {
            if (e.target.closest('[data-rxm-note-more]')) { openDesktopSheet(); return; }
            if (e.target.closest('[data-rxm-note-x]')) {
                try { sessionStorage.setItem(NOTE_KEY, '1'); } catch (err) { }
                n.remove();
            }
        });
    }

    /* ------------------------------------------------------------
       4b. DASHBOARD "show more": recent activity, most requested and the trend chart
       start collapsed on a phone. The button sits OUTSIDE #dashLive (which the live refresh swaps),
       and the open/closed state is a class on #dash, so it survives every refresh.
    ------------------------------------------------------------ */
    function buildMore() {
        var dash = $('#dash'), live = $('#dashLive');
        if (!dash || !live || $('#rxmMore')) return;
        var b = el('button', 'rxm-more-btn');
        b.type = 'button'; b.id = 'rxmMore';
        b.setAttribute('aria-expanded', 'false');
        b.innerHTML = '<span class="rxm-more-label">Show trends &amp; recent activity</span>' + icon('expand_more');
        live.parentNode.insertBefore(b, live.nextSibling);
        b.addEventListener('click', function () {
            var open = dash.classList.toggle('rxm-more');
            b.setAttribute('aria-expanded', open ? 'true' : 'false');
            $('.rxm-more-label', b).textContent = open ? 'Show less' : 'Show trends & recent activity';
            $('.material-symbols-outlined', b).textContent = open ? 'expand_less' : 'expand_more';
        });
    }

    /* ------------------------------------------------------------
       5. SAFETY NET: never sit on a desktop-only screen
       (e.g. a desktop window shrunk below 600px while on Faculty)
    ------------------------------------------------------------ */
    function enforce(announce) {
        if (!isMobile()) return;
        var t = currentTab();
        if (DESKTOP_TABS.indexOf(t) !== -1) {
            if (announce) window.psDesktopOnlyNotice(t);
            goTab('dashboard');
            return;
        }
        if (t === 'settings') {
            var cur = $('#panel-settings > .rq-sub-panel.active');
            if (cur && DESKTOP_SETT.indexOf(cur.id) !== -1) {
                if (announce) window.psDesktopOnlyNotice(cur.id);
                var a = $('#settMainTabs [data-sett-panel="sett-account"]'); if (a) a.click();
            }
        }
    }

    /* ------------------------------------------------------------
       wiring
    ------------------------------------------------------------ */
    var queued = false;
    function refresh() {
        if (queued) return; queued = true;
        requestAnimationFrame(function () {
            queued = false;
            if (!isMobile()) return;
            enforce(false);
            syncBar(); syncTabbar();
        });
    }

    var built = false;
    function ensureBuilt() {
        if (built || !isMobile()) return;
        built = true;
        buildBar(); buildTabbar(); buildSheet(); buildNote(); buildMore();
        enforce(false);
        refresh();
    }

    function init() {
        ensureBuilt();

        if (window.MutationObserver) {
            var mo = new MutationObserver(refresh);
            $$('.tab-panel').forEach(function (p) { mo.observe(p, { attributes: true, attributeFilter: ['class'] }); });
            var sp = $('#panel-settings');
            if (sp) $$(':scope > .rq-sub-panel', sp).forEach(function (p) { mo.observe(p, { attributes: true, attributeFilter: ['class'] }); });
            var nb = $('#dashNotifBadge'); if (nb) mo.observe(nb, { childList: true, characterData: true, subtree: true, attributes: true });
            var av = $('#dashAvatarBtn'); if (av) mo.observe(av, { childList: true, characterData: true, subtree: true });

            // The window can cross the phone threshold either way (rotate, resize, desktop site toggle)
            new MutationObserver(function () {
                if (isMobile()) { ensureBuilt(); enforce(true); buildNote(); buildMore(); refresh(); }
            }).observe(root, { attributes: true, attributeFilter: ['class'] });
        }
        window.addEventListener('pageshow', refresh);
        window.addEventListener('popstate', function () { setTimeout(refresh, 0); });
    }

    if (D.readyState === 'loading') D.addEventListener('DOMContentLoaded', init); else init();
})();
