/**
 * notifications/assets/js/notif-prefs.js
 * Notification Preferences toggles (Settings), used by BOTH portals.
 *
 * Each checkbox with data-notif-pref="<key>" inside #notifPrefsPanel is saved to
 * the server (notifications/api/notif-prefs.php) the moment it is flipped, and
 * the server applies it everywhere (bell feed, badge, polling, emails). This file
 * only has to: save, show the result, and tell the dashboard to re-pull its feed.
 *
 *   window.PSNotifPrefs.isOn('overdue')  -> true unless that family (or everything) is off.
 *                                           The dashboards use it to skip their own pop-up toasts.
 *   window.PSNotifPrefs.reset()          -> everything back on (used by admin "Reset All Settings").
 *   document event 'pupsync:notif-prefs-changed' (detail.prefs) fires after every change.
 */
(function () {
    'use strict';

    var API = 'notifications/api/notif-prefs.php';
    var panel = null;
    var audience = '';
    var state = {};
    var busy = 0;

    function csrf() {
        var el = document.querySelector('input[name="csrf_token"]');
        if (el && el.value) return el.value;
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.getAttribute('content') || '') : '';
    }

    function toast(msg, type) {
        if (typeof window.showToast === 'function') window.showToast(msg, type);
    }

    function readStateFromDom() {
        var s = {};
        panel.querySelectorAll('[data-notif-pref]').forEach(function (i) {
            s[i.getAttribute('data-notif-pref')] = i.checked;
        });
        return s;
    }

    /* Master off -> the category switches are greyed out and locked (they keep their own values). */
    function applyMasterState() {
        var master = panel.querySelector('[data-notif-pref="all"]');
        var off = !!(master && !master.checked);
        panel.classList.toggle('notif-prefs-off', off);
        panel.querySelectorAll('[data-notif-pref]').forEach(function (i) {
            if (i.getAttribute('data-notif-pref') !== 'all') i.disabled = off;
        });
    }

    function syncInputs(prefs) {
        panel.querySelectorAll('[data-notif-pref]').forEach(function (i) {
            var k = i.getAttribute('data-notif-pref');
            if (Object.prototype.hasOwnProperty.call(prefs, k)) i.checked = !!prefs[k];
        });
        applyMasterState();
    }

    function publish(prefs, key) {
        state = prefs;
        window.NOTIF_PREFS = prefs;
        try {
            document.dispatchEvent(new CustomEvent('pupsync:notif-prefs-changed', { detail: { prefs: prefs, key: key || null } }));
        } catch (e) { /* very old browser: dashboards still pick it up on next poll */ }
    }

    function post(fields) {
        var fd = new FormData();
        fd.append('csrf_token', csrf());
        fd.append('audience', audience);
        Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
        busy++;
        return fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (j) {
                    if (!r.ok || j.status !== 'success' || !j.prefs) throw new Error(j.message || j.error || 'Request failed');
                    return j.prefs;
                });
            })
            .then(function (p) { busy--; return p; }, function (e) { busy--; throw e; });
    }

    function onChange(e) {
        var input = e.target;
        if (!input || !input.matches || !input.matches('[data-notif-pref]')) return;
        var key = input.getAttribute('data-notif-pref');
        var label = input.getAttribute('data-notif-label') || 'Notifications';
        var on = input.checked;
        var fields = { action: 'save' };
        fields['prefs[' + key + ']'] = on ? '1' : '0';

        applyMasterState();   // instant feedback; rolled back below if the save fails
        post(fields).then(function (prefs) {
            syncInputs(prefs);
            publish(prefs, key);
            if (key === 'all') toast(on ? 'Notifications are on.' : 'All notifications are off.', on ? 'success' : 'info');
            else toast(label + (on ? ' notifications on.' : ' notifications off.'), on ? 'success' : 'info');
        }).catch(function () {
            input.checked = !on;
            applyMasterState();
            toast('Could not save that setting. Please try again.', 'error');
        });
    }

    function reset() {
        if (!panel) return Promise.resolve(false);
        return post({ action: 'reset' }).then(function (prefs) {
            syncInputs(prefs);
            publish(prefs, null);
            return true;
        }).catch(function () {
            toast('Could not reset notification preferences.', 'error');
            return false;
        });
    }

    function isOn(family) {
        var p = state;
        if (!p || p.all === undefined) return true;
        if (p.all === false) return false;
        return p[family] !== false;
    }

    function init() {
        panel = document.getElementById('notifPrefsPanel');
        if (!panel) return;
        audience = panel.getAttribute('data-notif-audience') || '';
        state = (window.NOTIF_PREFS && typeof window.NOTIF_PREFS === 'object') ? window.NOTIF_PREFS : readStateFromDom();
        window.NOTIF_PREFS = state;
        applyMasterState();
        panel.addEventListener('change', onChange);
    }

    window.PSNotifPrefs = {
        isOn: isOn,
        reset: reset,
        get: function () { return state; }
    };

    /* isOn() has to work before Settings is ever opened (the dashboards call it from their
       pop-up code), so seed it now: server value if the page embedded one, else the toggles. */
    function seed() {
        var el = document.getElementById('notifPrefsPanel');
        if (window.NOTIF_PREFS && typeof window.NOTIF_PREFS === 'object') { state = window.NOTIF_PREFS; return; }
        if (!el) return;
        var s = {};
        el.querySelectorAll('[data-notif-pref]').forEach(function (i) { s[i.getAttribute('data-notif-pref')] = i.checked; });
        state = s;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { seed(); init(); });
    } else { seed(); init(); }
})();
