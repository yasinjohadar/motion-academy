/* Service worker for Web Push notifications. */

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let payload;

    try {
        payload = event.data.json();
    } catch (e) {
        payload = { title: 'إشعار جديد', body: event.data.text() };
    }

    const title = payload.title || 'إشعار جديد';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: payload.body || '',
            icon: payload.icon || '/favicon.ico',
            badge: payload.badge || undefined,
            tag: payload.tag || undefined,
            dir: 'rtl',
            lang: 'ar',
            data: payload.data || {},
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data && event.notification.data.url;

    if (!url) {
        return;
    }

    // Focus an existing tab on that URL if one is open, otherwise open a new one.
    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                for (const client of clientList) {
                    if (client.url === url && 'focus' in client) {
                        return client.focus();
                    }
                }

                return self.clients.openWindow(url);
            }),
    );
});
