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

    // Register the service worker first, then proceed
    // Determine the correct service worker path based on current location
    var swPath = '/OneSignalSDKWorker.js';
    var swScope = '/';
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        swPath = '/Equipment-Lending-Website/OneSignalSDKWorker.js';
        swScope = '/Equipment-Lending-Website/';
    }
    // Unregister any conflicting service worker first, then re-register cleanly
    navigator.serviceWorker.getRegistrations().then(function (registrations) {
        var unregPromises = [];
        for (var i = 0; i < registrations.length; i++) {
            if (registrations[i].scope !== window.location.origin + swScope) {
                unregPromises.push(registrations[i].unregister());
            }
        }
        return Promise.all(unregPromises);
    }).then(function () {
        return navigator.serviceWorker.register(swPath, { scope: swScope });
    }).then(function (reg) {
        console.log('[PUPSync push] SW registered, scope:', reg.scope);
    }).catch(function (e) {
        console.error('[PUPSync push] SW registration failed:', e);
    });

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
                // Mark as permanently handled so modal never reappears
                localStorage.setItem(DISMISS_KEY, (Date.now() + 1000 * 60 * 60 * 24 * 365).toString());
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
            console.log('[PUPSync push] SW ready, scope:', reg.scope);
            return reg.pushManager.getSubscription().then(function (existing) {
                if (existing) {
                    console.log('[PUPSync push] Existing subscription found, saving.');
                    saveSubscription(existing);
                    return;
                }
                console.log('[PUPSync push] No existing subscription, fetching VAPID key.');
                return fetch('notifications/api/push-vapid-key.php', {
                    credentials: 'same-origin'
                }).then(function (r) {
                    return r.json();
                }).then(function (data) {
                    console.log('[PUPSync push] VAPID key received:', data.key ? 'yes' : 'NO KEY');
                    if (!data.key) return Promise.reject('No VAPID key');
                    return reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(data.key)
                    });
                }).then(function (sub) {
                    if (sub) {
                        console.log('[PUPSync push] Subscribed, endpoint:', sub.endpoint.substring(0, 40) + '...');
                        saveSubscription(sub);
                    }
                }).catch(function (e) {
                    // If subscribe fails (e.g. another tab already holds the subscription),
                    // try to get the existing one and save it
                    console.warn('[PUPSync push] Subscribe error, checking for existing sub:', e.message || e);
                    return reg.pushManager.getSubscription().then(function (sub) {
                        if (sub) {
                            console.log('[PUPSync push] Recovered existing subscription, saving.');
                            saveSubscription(sub);
                        } else {
                            console.error('[PUPSync push] No subscription available:', e);
                        }
                    });
                });
            });
        }).catch(function (e) {
            console.error('[PUPSync push] SW ready error:', e);
        });
    }

    function saveSubscription(sub) {
        fetch(CSRF_EP, { credentials: 'same-origin' })
            .then(function (r) {
                console.log('[PUPSync push] CSRF status:', r.status);
                return r.json();
            })
            .then(function (data) {
                console.log('[PUPSync push] CSRF token received:', data.token ? 'yes' : 'NO TOKEN');
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
            .then(function (r) {
                if (!r) return;
                console.log('[PUPSync push] Subscribe status:', r.status);
                return r.json();
            })
            .then(function (data) {
                if (data) console.log('[PUPSync push] Subscribe result:', JSON.stringify(data));
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
