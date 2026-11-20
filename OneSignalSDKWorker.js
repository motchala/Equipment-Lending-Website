/**
 * OneSignalSDKWorker.js
 * PUPSync push notification service worker.
 * Handles incoming push events and notification clicks.
 * Named OneSignalSDKWorker.js so it registers at the site root scope.
 */

self.addEventListener('push', function (event) {
    if (!event.data) return;

    var data = {};
    try { data = event.data.json(); } catch (e) { data = { title: 'PUPSync', body: event.data.text() }; }

    var title   = data.title || 'PUPSync';
    var options = {
        body:    data.body  || '',
        icon:    data.icon  || '/shared/assets/images/favicon-192.png',
        badge:   data.badge || '/shared/assets/images/favicon-192.png',
        data:    { url: data.url || '/' },
        requireInteraction: false,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) ? event.notification.data.url : '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
            for (var i = 0; i < list.length; i++) {
                if (list[i].url === url && 'focus' in list[i]) {
                    return list[i].focus();
                }
            }
            if (clients.openWindow) return clients.openWindow(url);
        })
    );
});
