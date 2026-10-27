/* ================================================================
   faculty-dashboard-mobile.js
   Phone app shell for the Faculty portal.

   Active ONLY when <html> has the class "rx-mobile" (set by the tiny
   detection script in <head>: phones, or any window ≤ 600px wide).
   Tablets and desktops keep the responsive layout untouched.

   Builds (all hidden by CSS unless rx-mobile is on):
     • top app bar      – title / back arrow, notification bell, avatar
     • bottom tab bar   – Home · Activity · Equipment · Facilities
     • account sheet    – opened from the avatar: Settings, Help,
                          Notifications, Log out
     • back bars        – a "‹ Settings" header on full-screen overlays
     • category chips   – replaces the Equipment category <select>
     • floor picker     – replaces the Facilities floor tabs

   It never re-implements app logic: every button here either carries
   an existing data-action (go-tab, open-overlay, open-notif-modal,
   logout…) or clicks the original control programmatically, so
   faculty-dashboard.js and fcty-facilities.js stay the single source
   of truth.
================================================================ */
(function () {
    'use strict';

    var D = document, root = D.documentElement;
    var $ = function (s, r) { return (r || D).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || D).querySelectorAll(s)); };

    var TABS = [
        { tab: 'home', label: 'Home', icon: 'home', title: 'PUPSync' },
        { tab: 'activity', label: 'Activity', icon: 'history', title: 'Activity' },
        { tab: 'lending', label: 'Equipment', icon: 'inventory_2', title: 'Equipment' },
        { tab: 'rooms', label: 'Facilities', icon: 'meeting_room', title: 'Facilities' }
    ];

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

    /* ------------------------------------------------------------
       Current tab / rooms drill-down state
    ------------------------------------------------------------ */
    function currentTab() {
        var p = $('.tab-panel.active');
        return p && p.id ? p.id.replace('panel-', '') : 'home';
    }
    function roomsViewOpen() {
        var v = $('#fcty-rooms-view');
        if (!v) return false;
        return !v.hidden && getComputedStyle(v).display !== 'none';
    }

    /* ------------------------------------------------------------
       1. APP BAR
    ------------------------------------------------------------ */
    var bar, barBack, barTitle, barBrand, barBell, barBadge, barAvatar;

    function buildBar() {
        bar = el('header', 'rxm-bar');
        bar.id = 'rxmBar';
        bar.innerHTML =
            '<button type="button" class="rxm-iconbtn rxm-back" id="rxmBack" aria-label="Back" hidden>' + icon('arrow_back') + '</button>' +
            '<span class="rxm-logo" aria-hidden="true">' + icon('layers') + '</span>' +
            '<h1 class="rxm-title" id="rxmTitle">PUPSync</h1>' +
            '<div class="rxm-actions">' +
            '<button type="button" class="rxm-iconbtn rxm-dl" id="rxmDl" aria-label="Download report" hidden>' + icon('download') + '</button>' +
            '<button type="button" class="rxm-iconbtn rxm-bell" id="rxmBell" aria-label="Notifications">' + icon('notifications') +
            '<b class="rxm-badge" id="rxmBadge" hidden></b></button>' +
            '<button type="button" class="rxm-avatar" id="rxmAvatar" aria-label="Account menu" aria-haspopup="dialog"></button>' +
            '</div>';
        D.body.appendChild(bar);
        barBack = $('#rxmBack', bar); barTitle = $('#rxmTitle', bar); barBrand = $('.rxm-logo', bar);
        barBell = $('#rxmBell', bar); barBadge = $('#rxmBadge', bar); barAvatar = $('#rxmAvatar', bar);

        $('#rxmDl', bar).addEventListener('click', function () {
            var orig = $('#panel-activity .fact-btn-ghost');
            if (orig) orig.click();
        });
        barBell.addEventListener('click', function () {
            var orig = $('#dashNotifBtn') || $('[data-action="open-notif-modal"]');
            if (orig) orig.click();
        });
        barAvatar.addEventListener('click', openAccountSheet);
        barBack.addEventListener('click', function () {
            var back = $('#fcty-rooms-back-facilities');
            if (back) back.click();
        });

        var main = $('#appMain');
        if (main) main.addEventListener('scroll', function () {
            bar.classList.toggle('is-scrolled', main.scrollTop > 4);
        }, { passive: true });
    }

    function syncBar() {
        if (!bar) return;
        var t = currentTab();
        var def = TABS.filter(function (x) { return x.tab === t; })[0] || TABS[0];
        var inRoom = t === 'rooms' && roomsViewOpen();
        var title = def.title;
        if (inRoom) {
            var b = $('#fcty-rooms-breadcrumb-building');
            title = (b && b.textContent.trim()) || 'Building';
        }
        barTitle.textContent = title;
        $('#rxmDl', bar).hidden = t !== 'activity';
        barBack.hidden = !inRoom;
        bar.classList.toggle('is-home', t === 'home' && !inRoom);
        bar.classList.toggle('has-back', inRoom);

        // badge + avatar mirror the originals (they update live)
        var ob = $('#notifBadge'), n = ob ? ob.textContent.trim() : '';
        var show = ob && !ob.hidden && n && n !== '0' && getComputedStyle(ob).display !== 'none' || (ob && n && n !== '0');
        barBadge.hidden = !show;
        if (show) barBadge.textContent = n;
        var av = $('#dashAvatarBtn');
        barAvatar.textContent = (av && av.textContent.trim()) || 'F';
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
            return '<button type="button" class="rxm-tab" data-action="go-tab" data-tab="' + t.tab + '" data-rxm-tab="' + t.tab + '">' +
                '<span class="rxm-tab-pill">' + icon(t.icon) + '<i class="rxm-tab-dot" hidden></i></span>' +
                '<span class="rxm-tab-label">' + t.label + '</span></button>';
        }).join('');
        D.body.appendChild(tabbar);

        // Re-tapping the active tab: scroll to top, or leave a building view
        tabbar.addEventListener('click', function (e) {
            var b = e.target.closest('.rxm-tab');
            if (!b) return;
            if (b.getAttribute('aria-current') === 'page') {
                e.stopPropagation();
                if (b.dataset.rxmTab === 'rooms' && roomsViewOpen()) {
                    var back = $('#fcty-rooms-back-facilities'); if (back) back.click();
                }
                var main = $('#appMain'); if (main) main.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    }

    function syncTabbar() {
        if (!tabbar) return;
        var t = currentTab();
        $$('.rxm-tab', tabbar).forEach(function (b) {
            var on = b.dataset.rxmTab === t;
            if (on) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current');
        });
        // red dot on Activity when something is overdue
        var od = $('.fdb-kpi.fdb-tone-danger .fdb-kpi-val');
        var n = od ? parseInt(od.textContent, 10) || 0 : 0;
        var dot = $('.rxm-tab[data-rxm-tab="activity"] .rxm-tab-dot', tabbar);
        if (dot) dot.hidden = n <= 0;
    }

    /* ------------------------------------------------------------
       3. BOTTOM SHEET (account menu, floor picker)
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
            if (e.target.closest('[data-rxm-close]')) closeSheet();
        });
        D.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !sheet.hidden) closeSheet(); });
    }

    function openSheet(title, html, kind) {
        sheetTitle.textContent = title || '';
        sheet.dataset.kind = kind || '';
        sheetBody.innerHTML = html;
        sheetLast = D.activeElement;
        sheet.hidden = false;
        // next frame so the transition runs
        requestAnimationFrame(function () { sheet.classList.add('is-open'); });
        var first = $('button, [href]', sheetBody); if (first) first.focus({ preventScroll: true });
    }
    function closeSheet() {
        if (sheet.hidden) return;
        sheet.classList.remove('is-open');
        setTimeout(function () { sheet.hidden = true; sheetBody.innerHTML = ''; }, 220);
        if (sheetLast && sheetLast.focus) try { sheetLast.focus({ preventScroll: true }); } catch (e) { }
    }

    function openAccountSheet() {
        var nameEl = $('.side-nav-user-name');
        var name = (nameEl && nameEl.textContent.trim()) || 'Faculty';
        var initials = (barAvatar && barAvatar.textContent) || 'F';
        var ob = $('#notifBadge'), n = ob ? ob.textContent.trim() : '';
        var unread = n && n !== '0' ? '<b class="rxm-row-badge">' + esc(n) + '</b>' : '';
        var html =
            '<div class="rxm-acct">' +
            '<span class="rxm-acct-avatar">' + esc(initials) + '</span>' +
            '<div class="rxm-acct-text"><strong class="rxm-trunc">' + esc(name) + '</strong><span>PUPSync Faculty</span></div></div>' +
            '<ul class="rxm-list">' +
            row('settings', 'Settings', 'Profile, security, appearance', 'open-overlay', 'data-target="settingsOverlay"') +
            row('help', 'Help &amp; Support', '', 'open-overlay', 'data-target="settingsOverlay" data-rxm-help="1"') +
            row('notifications', 'Notifications', '', 'open-notif-modal', '', unread) +
            '</ul>' +
            '<ul class="rxm-list rxm-list--danger">' +
            row('logout', 'Log out', '', 'logout', '') +
            '</ul>';
        openSheet('Account', html, 'account');
    }
    function row(ic, label, sub, action, attrs, trailing) {
        return '<li><button type="button" class="rxm-row" data-action="' + action + '" ' + (attrs || '') + ' data-rxm-close>' +
            '<span class="rxm-row-icon">' + icon(ic) + '</span>' +
            '<span class="rxm-row-text"><span class="rxm-row-label">' + label + '</span>' + (sub ? '<span class="rxm-row-sub">' + sub + '</span>' : '') + '</span>' +
            (trailing || '') + icon('chevron_right', 'rxm-row-go') + '</button></li>';
    }

    // "Help & Support" opens Settings, then flips to its Help view
    D.addEventListener('click', function (e) {
        var h = e.target.closest && e.target.closest('[data-rxm-help]');
        if (!h) return;
        setTimeout(function () {
            var t = $('[data-sov-tab="sov-tab-help"]'); if (t) t.click();
        }, 380);
    });

    /* ------------------------------------------------------------
       4. BACK BARS on full-screen overlays (Settings)
    ------------------------------------------------------------ */
    function buildOverlayBars() {
        $$('#settingsOverlay, #helpOverlay').forEach(function (ov) {
            if ($('.rxm-ovbar', ov)) return;
            var title = ov.id === 'helpOverlay' ? 'Help &amp; Support' : 'Settings';
            var b = el('div', 'rxm-ovbar',
                '<button type="button" class="rxm-iconbtn" data-action="close-overlay" data-target="' + ov.id + '" aria-label="Back">' + icon('arrow_back') + '</button>' +
                '<span class="rxm-ovtitle">' + title + '</span>');
            ov.insertBefore(b, ov.firstChild);
        });
    }

    /* ------------------------------------------------------------
       5. EQUIPMENT — category chips instead of a <select>
    ------------------------------------------------------------ */
    function buildCategoryChips() {
        var sel = $('#categoryFilter');
        if (!sel || $('.rxm-chips')) return;
        var wrap = el('div', 'rxm-chips');
        wrap.setAttribute('role', 'tablist');
        wrap.setAttribute('aria-label', 'Category');
        $$('option', sel).forEach(function (o) {
            var label = o.value === '' ? 'All' : o.textContent.trim();
            var b = el('button', 'rxm-chip');
            b.type = 'button'; b.setAttribute('role', 'tab'); b.dataset.value = o.value;
            b.textContent = label;
            wrap.appendChild(b);
        });
        sel.parentNode.insertBefore(wrap, sel);
        function sync() {
            $$('.rxm-chip', wrap).forEach(function (c) {
                var on = c.dataset.value === sel.value;
                c.classList.toggle('is-on', on); c.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        }
        wrap.addEventListener('click', function (e) {
            var c = e.target.closest('.rxm-chip'); if (!c) return;
            sel.value = c.dataset.value;
            sel.dispatchEvent(new Event('input', { bubbles: true }));
            sel.dispatchEvent(new Event('change', { bubbles: true }));
            sync();
            c.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
        });
        sel.addEventListener('change', sync);
        sync();
    }

    /* ------------------------------------------------------------
       6. FACILITIES — floor picker instead of scrolling floor tabs
    ------------------------------------------------------------ */
    var floorBtn;

    function floorTabs() { return $$('#fcty-floor-tabs .fcty-floor-tab'); }

    function buildFloorPicker() {
        var bar2 = $('.fcty-toolbar');
        if (!bar2 || $('.rxm-floorbtn', bar2)) return;
        floorBtn = el('button', 'rxm-floorbtn', '');
        floorBtn.type = 'button';
        floorBtn.setAttribute('aria-haspopup', 'dialog');
        bar2.insertBefore(floorBtn, bar2.firstChild);
        floorBtn.addEventListener('click', openFloorSheet);
        syncFloor();
        var tabs = $('#fcty-floor-tabs');
        if (tabs && window.MutationObserver) {
            new MutationObserver(syncFloor).observe(tabs, { childList: true, subtree: true, attributes: true, attributeFilter: ['aria-selected', 'class'] });
        }
    }
    function selectedFloor() {
        var t = floorTabs();
        return t.filter(function (b) { return b.getAttribute('aria-selected') === 'true' || b.classList.contains('active'); })[0] || t[0];
    }
    function tabParts(b) {
        var sp = b ? b.querySelectorAll('span') : [];
        return { label: sp[0] ? sp[0].textContent.trim() : (b ? b.textContent.trim() : ''), count: sp[1] ? sp[1].textContent.trim() : '' };
    }
    function syncFloor() {
        if (!floorBtn) return;
        var s = selectedFloor();
        if (!s) { floorBtn.hidden = true; return; }
        floorBtn.hidden = false;
        var p = tabParts(s);
        floorBtn.innerHTML = icon('layers') + '<span class="rxm-floorbtn-text"><b>' + esc(p.label) + '</b>' +
            (p.count ? '<small>' + esc(p.count) + ' rooms</small>' : '') + '</span>' + icon('expand_more', 'rxm-floorbtn-caret');
    }
    function openFloorSheet() {
        var tabs = floorTabs(); if (!tabs.length) return;
        var cur = selectedFloor();
        var html = '<ul class="rxm-list rxm-list--pick" role="listbox">' + tabs.map(function (b, i) {
            var p = tabParts(b), on = b === cur;
            return '<li><button type="button" class="rxm-row rxm-pick' + (on ? ' is-on' : '') + '" role="option" aria-selected="' + on + '" data-rxm-floor="' + i + '">' +
                '<span class="rxm-row-text"><span class="rxm-row-label">' + esc(p.label) + '</span>' +
                (p.count ? '<span class="rxm-row-sub">' + esc(p.count) + ' rooms</span>' : '') + '</span>' +
                (on ? icon('check', 'rxm-row-go rxm-check') : '') + '</button></li>';
        }).join('') + '</ul>';
        openSheet('Choose floor', html, 'floor');
    }
    D.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-rxm-floor]');
        if (!b) return;
        var tabs = floorTabs(), t = tabs[parseInt(b.dataset.rxmFloor, 10)];
        closeSheet();
        if (t) setTimeout(function () { t.click(); }, 60);
    });

    /* ------------------------------------------------------------
       wiring
    ------------------------------------------------------------ */
    var queued = false;
    function refresh() {
        if (queued) return; queued = true;
        requestAnimationFrame(function () {
            queued = false;
            syncBar(); syncTabbar();
        });
    }

    function init() {
        buildBar(); buildTabbar(); buildSheet(); buildOverlayBars();
        buildCategoryChips(); buildFloorPicker();
        refresh();

        if (window.MutationObserver) {
            var mo = new MutationObserver(refresh);
            $$('.tab-panel').forEach(function (p) { mo.observe(p, { attributes: true, attributeFilter: ['class'] }); });
            var rv = $('#fcty-rooms-view'); if (rv) mo.observe(rv, { attributes: true, attributeFilter: ['hidden', 'class', 'style'] });
            var bc = $('#fcty-rooms-breadcrumb-building'); if (bc) mo.observe(bc, { childList: true, characterData: true, subtree: true });
            var top = $('.fd-dash-top-actions'); if (top) mo.observe(top, { childList: true, characterData: true, subtree: true, attributes: true });
            var kp = $('#dashStats'); if (kp) mo.observe(kp, { childList: true, characterData: true, subtree: true });
            // building views swap their content in place
            var rootRooms = $('#fcty-root') || $('#panel-rooms');
            if (rootRooms) mo.observe(rootRooms, { attributes: true, attributeFilter: ['hidden'], subtree: true });
        }
        D.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('[data-action="go-tab"], .fcty-panel, .fcty-room')) setTimeout(refresh, 120);
        });
        window.addEventListener('pageshow', refresh);
    }

    if (D.readyState === 'loading') D.addEventListener('DOMContentLoaded', init); else init();
})();
