/*
 * Oasis service worker (docs/oasis-pwa.md). Deliberately small:
 * - pages always come from the network (they are personal and must never be served stale from a cache); only when
 *   the network is gone, the cached /offline page is shown;
 * - built assets (/build/…, content-hashed), fonts and icons are cached on first use;
 * - push messages (encrypted on the server, no content or names inside) become a notification that opens Oasis.
 */
const VERSION = 'oasis-v1';
const OFFLINE = '/offline';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll([OFFLINE, '/icons/icon-192.png'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys()
        .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
        .then(() => self.clients.claim()));
});

const isStaticAsset = (url) => url.origin === self.location.origin
    && (url.pathname.startsWith('/build/') || url.pathname.startsWith('/fonts/') || url.pathname.startsWith('/icons/'));

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE)));
        return;
    }
    const url = new URL(request.url);
    if (isStaticAsset(url)) {
        event.respondWith(caches.match(request).then((cached) => cached || fetch(request).then((response) => {
            if (response.ok) {
                const copy = response.clone();
                caches.open(VERSION).then((cache) => cache.put(request, copy));
            }
            return response;
        })));
    }
});

self.addEventListener('push', (event) => {
    let message = { title: 'Oasis', body: 'Es gibt Neues für dich.', url: '/app/hinweise' };
    try {
        message = { ...message, ...event.data.json() };
    } catch {
        // An empty or unreadable push still shows the neutral notice.
    }
    event.waitUntil(self.registration.showNotification(message.title, {
        body: message.body,
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        lang: 'de',
        tag: message.tag || 'oasis-hinweise',
        data: { url: message.url },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/app/hinweise', self.location.origin);
    if (target.origin !== self.location.origin) {
        return;
    }
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        const open = windows.find((client) => new URL(client.url).origin === target.origin);
        if (open) {
            return open.focus().then(() => open.navigate(target.href));
        }
        return self.clients.openWindow(target.href);
    }));
});
