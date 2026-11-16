/**
 * push-init.js
 * OneSignal Web Push initialisation for PUPSync.
 *
 * Loaded on faculty-dashboard.php and admin-dashboard.php.
 * Requires window.PS_PUSH_APP_ID and window.PS_CSRF_TOKEN set before this runs.
 */

(function () {
    'use strict';

    var APP_ID       = window.PS_PUSH_APP_ID || '';
    var CSRF_TOKEN   = window.PS_CSRF_TOKEN  || '';
    var SUBSCRIBE_EP = 'notifications/api/push-subscribe.php';
    var UNSUB_EP     = 'notifications/api/push-unsubscribe.php';
    var DISMISS_KEY  = 'ps_push_dismissed';
    var DISMISS_DAYS = 7;

    var _oneSignal   = null;   // set once the SDK resolves

    // Wait for DOM before doing anything
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }

    function onReady() {
        // Wire up "Maybe later" immediately — no SDK needed
        var later = document.getElementById('psPushLater');
        if (later) {
            later.addEventListener('click', function () {
                localStorage.setItem(DISMISS_KEY, Date.now().toString());
                hideModal();
            });
        }

        // Wire up "Allow" immediately — calls SDK when clicked
        var allow = document.getElementById('psPushAllow');
        if (allow) {
            allow.addEventListener('click', function () {
                hideModal();
                if (_oneSignal) {
                    doSubscribe(_oneSignal);
                } else {
                    // SDK not ready yet — wait for it
                    window._psPushPendingAllow = true;
                }
            });
        }

        // Check if we should show the modal at all
        var permission = (typeof Notification !== 'undefined') ? Notification.permission : 'denied';
        if (permission === 'denied') {
            removeSub();
            return;
        }
        if (permission === 'default') {
            var dismissed = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
            var daysSince = (Date.now() - dismissed) / (1000 * 60 * 60 * 24);
            if (dismissed > 0 && daysSince < DISMISS_DAYS) return;
            showModal();
        }

        // Only load the SDK if push is supported
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !APP_ID) return;

        var script   = document.createElement('script');
        script.src   = 'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js';
        script.async = true;
        script.onload = function () {
            window.OneSignalDeferred = window.OneSignalDeferred || [];
            window.OneSignalDeferred.push(function (OneSignal) {
                OneSignal.init({
                    appId: APP_ID,
                    serviceWorkerParam: { scope: '/' },
                    notifyButton:  { enable: false },
                    promptOptions: { slidedown: { enabled: false } },
                }).then(function () {
                    _oneSignal = OneSignal;

                    // If the user already clicked Allow before the SDK loaded
                    if (window._psPushPendingAllow) {
                        window._psPushPendingAllow = false;
                        doSubscribe(OneSignal);
                        return;
                    }

                    // If already granted, subscribe silently
                    if (Notification.permission === 'granted') {
                        doSubscribe(OneSignal);
                    }
                }).catch(function (e) {
                    console.error('[PUPSync push] Init error:', e);
                });
            });
        };
        document.head.appendChild(script);
    }

    function showModal() {
        var modal = document.getElementById('psPushModal');
        if (!modal) return;
        modal.removeAttribute('hidden');
        modal.style.display = 'flex';
    }

    function hideModal() {
        var modal = document.getElementById('psPushModal');
        if (!modal) return;
        modal.classList.add('is-hiding');
        setTimeout(function () {
            modal.setAttribute('hidden', '');
            modal.style.display = '';
            modal.classList.remove('is-hiding');
        }, 160);
    }

    function doSubscribe(OneSignal) {
        OneSignal.Notifications.requestPermission().then(function (granted) {
            if (!granted) return;
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
        }).catch(function (e) {
            console.error('[PUPSync push] Permission request error:', e);
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
