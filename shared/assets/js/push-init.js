/**
 * push-init.js
 * OneSignal Web Push initialisation for PUPSync.
 *
 * Loaded on faculty-dashboard.php and admin-dashboard.php.
 * Requires window.PS_PUSH_APP_ID and window.PS_CSRF_TOKEN set before this runs.
 *
 * Flow:
 *  1. Load OneSignal SDK from CDN.
 *  2. If browser already granted permission — subscribe silently.
 *  3. If permission is 'default' and user has not dismissed the modal in
 *     the last 7 days — show the custom PUPSync modal.
 *  4. "Allow Notifications" — trigger OneSignal opt-in — save player ID to DB.
 *  5. "Maybe later" — set localStorage timestamp, hide modal, try again in 7 days.
 *  6. If permission is 'denied' — remove any stale subscription from DB.
 */

(function () {
    'use strict';

    var APP_ID       = window.PS_PUSH_APP_ID || '';
    var CSRF_TOKEN   = window.PS_CSRF_TOKEN  || '';
    var SUBSCRIBE_EP = 'notifications/api/push-subscribe.php';
    var UNSUB_EP     = 'notifications/api/push-unsubscribe.php';
    var DISMISS_KEY  = 'ps_push_dismissed';
    var DISMISS_DAYS = 7;

    if (!APP_ID) return;
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

    // ── Load OneSignal SDK from CDN ───────────────────────────────────────
    var script    = document.createElement('script');
    script.src    = 'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js';
    script.defer  = true;
    script.onload = function () { initOneSignal(); };
    document.head.appendChild(script);

    function initOneSignal() {
        window.OneSignalDeferred = window.OneSignalDeferred || [];
        window.OneSignalDeferred.push(function (OneSignal) {
            OneSignal.init({
                appId: APP_ID,
                serviceWorkerParam: { scope: '/' },
                notifyButton:  { enable: false },
                promptOptions: { slidedown: { enabled: false } },
            }).then(function () {
                handlePermission(OneSignal);
            }).catch(function (e) {
                console.error('[PUPSync push] Init error:', e);
            });
        });
    }

    function handlePermission(OneSignal) {
        var permission = Notification.permission;

        if (permission === 'granted') {
            subscribeAndSave(OneSignal);
            return;
        }

        if (permission === 'denied') {
            removeSub();
            return;
        }

        // 'default' — browser has never asked this user
        var dismissed  = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
        var daysSince  = (Date.now() - dismissed) / (1000 * 60 * 60 * 24);
        if (dismissed > 0 && daysSince < DISMISS_DAYS) return;

        showCustomModal(OneSignal);
    }

    function showCustomModal(OneSignal) {
        var modal = document.getElementById('psPushModal');
        var allow = document.getElementById('psPushAllow');
        var later = document.getElementById('psPushLater');
        if (!modal || !allow || !later) return;

        modal.hidden = false;

        allow.addEventListener('click', function () {
            hideModal(modal);
            OneSignal.Notifications.requestPermission().then(function (granted) {
                if (granted) subscribeAndSave(OneSignal);
            }).catch(function (e) {
                console.error('[PUPSync push] Permission request error:', e);
            });
        });

        later.addEventListener('click', function () {
            localStorage.setItem(DISMISS_KEY, Date.now().toString());
            hideModal(modal);
        });
    }

    function hideModal(modal) {
        modal.classList.add('is-hiding');
        setTimeout(function () {
            modal.hidden = true;
            modal.classList.remove('is-hiding');
        }, 160);
    }

    function subscribeAndSave(OneSignal) {
        OneSignal.User.PushSubscription.optIn().then(function () {
            var playerId = OneSignal.User.PushSubscription.id;
            if (!playerId) return;
            fetch(SUBSCRIBE_EP, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ player_id: playerId, csrf_token: CSRF_TOKEN }),
                credentials: 'same-origin',
            }).catch(function (e) {
                console.error('[PUPSync push] Subscribe save error:', e);
            });
        }).catch(function (e) {
            console.error('[PUPSync push] Opt-in error:', e);
        });
    }

    function removeSub() {
        if (!CSRF_TOKEN) return;
        fetch(UNSUB_EP, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: CSRF_TOKEN }),
            credentials: 'same-origin',
        }).catch(function () {});
    }

}());
