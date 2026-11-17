/**
 * push-init.js
 * PUPSync Web Push — no external SDK dependency.
 * Uses the browser's native Push API directly.
 * Requires window.PS_PUSH_APP_ID and window.PS_CSRF_TOKEN.
 */
(function () {
    'use strict';

    var SUBSCRIBE_EP = 'notifications/api/push-subscribe.php';
    var UNSUB_EP     = 'notifications/api/push-unsubscribe.php';
    var VAPID_EP     = 'notifications/api/push-vapid-key.php';
    var CSRF_EP      = 'notifications/api/push-csrf.php';
    var DISMISS_KEY  = 'ps_push_dismissed';
    var DISMISS_DAYS = 7;

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }

    function onReady() {
        var later = document.getElementById('psPushLater');
        if (later) {
            later.addEventListener('click', function () {
                localStorage.setItem(DISMISS_KEY, Date.now().toString());
                hideModal();
            });
        }

        var allow = document.getElementById('psPushAllow');
        if (allow) {
            allow.addEventListener('click', function () {
                hideModal();
                requestAndSubscribe();
            });
        }

        var permission = (typeof Notification !== 'undefined') ? Notification.permission : 'denied';

        if (permission === 'denied') {
            removeSub();
            return;
        }

        if (permission === 'granted') {
            silentSubscribe();
            return;
        }

        // permission === 'default'
        var dismissed = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
        var daysSince = (Date.now() - dismissed) / (1000 * 60 * 60 * 24);
        if (dismissed > 0 && daysSince < DISMISS_DAYS) return;

        showModal();
    }

    function requestAndSubscribe() {
        Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') return;
            silentSubscribe();
        }).catch(function (e) {
            console.error('[PUPSync push] Permission error:', e);
        });
    }

    function silentSubscribe() {
        navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.getSubscription().then(function (existing) {
                if (existing) {
                    saveSubscription(existing);
                    return;
                }
                // Get VAPID public key from our server
                fetch('notifications/api/push-vapid-key.php', {
                    credentials: 'same-origin'
                }).then(function (r) {
                    return r.json();
                }).then(function (data) {
                    if (!data.key) return;
                    return reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(data.key)
                    });
                }).then(function (sub) {
                    if (sub) saveSubscription(sub);
                }).catch(function (e) {
                    console.error('[PUPSync push] Subscribe error:', e);
                });
            });
        }).catch(function (e) {
            console.error('[PUPSync push] SW ready error:', e);
        });
    }

    function saveSubscription(sub) {
        // Fetch CSRF token fresh from server — never rely on a cached page variable
        fetch(CSRF_EP, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.token) return;
                return fetch(SUBSCRIBE_EP, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        subscription: sub.toJSON(),
                        csrf_token: data.token
                    }),
                    credentials: 'same-origin',
                });
            })
            .catch(function (e) {
                console.error('[PUPSync push] Save error:', e);
            });
    }

    function removeSub() {
        fetch(CSRF_EP, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.token) return;
                fetch(UNSUB_EP, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ csrf_token: data.token }),
                    credentials: 'same-origin',
                });
            })
            .catch(function () {});
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

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; i++) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

}());
