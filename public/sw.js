/**
 * VortexOps Service Worker
 *
 * Caching strategy:
 *  - Static assets (Vite build, fonts, icons, images): cache-first
 *  - HTML navigation: network-first with offline fallback
 *  - Livewire / JSON / POST: pass-through (no caching)
 */

const CACHE      = 'vortexops-v2';
const OFFLINE_URL = '/offline.html';

// ── Install: precache the offline page ───────────────────────────────────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE).then(cache => cache.add(OFFLINE_URL)).then(() => self.skipWaiting())
    );
});

// ── Activate: purge stale caches ─────────────────────────────────────────────
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// ── Fetch ─────────────────────────────────────────────────────────────────────
self.addEventListener('fetch', event => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // Only handle same-origin requests
    if (url.origin !== self.location.origin) return;

    // Pass through Livewire polling and JSON API requests
    const accept = req.headers.get('Accept') || '';
    if (accept.includes('application/json') || accept.includes('text/event-stream')) return;
    if (url.searchParams.has('livewire')) return;

    // Cache-first for immutable assets: Vite build output, fonts, icons, images
    if (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/fonts/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.startsWith('/media/') ||
        // Colosseum models: versioned URLs (?v=), kept on the device once warmed.
        url.pathname.startsWith('/models/') ||
        /\.(png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot|otf)$/i.test(url.pathname)
    ) {
        event.respondWith(cacheFirst(req));
        return;
    }

    // Network-first for HTML navigations — fall back to offline page
    if (accept.includes('text/html') || req.mode === 'navigate') {
        event.respondWith(networkFirstHtml(req));
        return;
    }

    // Everything else: network with stale fallback
    event.respondWith(networkWithStaleFallback(req));
});

async function cacheFirst(req) {
    const cache  = await caches.open(CACHE);
    const cached = await cache.match(req);
    if (cached) return cached;
    try {
        const fresh = await fetch(req);
        if (fresh.ok) cache.put(req, fresh.clone());
        return fresh;
    } catch {
        return new Response('Asset unavailable offline', { status: 503 });
    }
}

async function networkFirstHtml(req) {
    try {
        const fresh = await fetch(req);
        return fresh;
    } catch {
        const offline = await caches.match(OFFLINE_URL);
        return offline ?? new Response(
            '<!doctype html><html><body><h1>You are offline</h1></body></html>',
            { status: 503, headers: { 'Content-Type': 'text/html' } }
        );
    }
}

async function networkWithStaleFallback(req) {
    const cache = await caches.open(CACHE);
    try {
        const fresh = await fetch(req);
        if (fresh.ok) cache.put(req, fresh.clone());
        return fresh;
    } catch {
        return await cache.match(req) ?? new Response('Unavailable offline', { status: 503 });
    }
}

// Device push: shows the notification's own title and text (App\Jobs\SendDevicePush).
self.addEventListener('push', event => {
    let payload = {};
    try { payload = event.data?.json() || {}; } catch (_) {}
    const raw = typeof payload.url === 'string' ? payload.url : '/admin';
    let target;
    try { target = new URL(raw, self.location.origin); } catch (_) { target = new URL('/admin', self.location.origin); }
    if (target.origin !== self.location.origin) target = new URL('/admin', self.location.origin);
    const title = typeof payload.title === 'string' && payload.title.trim() ? payload.title.slice(0, 80) : 'Vortex Ops';
    event.waitUntil(self.registration.showNotification(title, {
        body: typeof payload.body === 'string' ? payload.body.slice(0, 180) : 'You have a new alert. Open Vortex Ops to view it.',
        icon: '/icons/icon-192.png', badge: '/icons/icon-192.png',
        tag: typeof payload.tag === 'string' ? payload.tag : 'vortex-alert',
        renotify: false, data: {url: target.href},
    }));
});
self.addEventListener('notificationclick', event => {
    event.notification.close();
    let target;
    try { target = new URL(event.notification.data?.url || '/admin', self.location.origin); } catch (_) { target = new URL('/admin', self.location.origin); }
    if (target.origin !== self.location.origin) target = new URL('/admin', self.location.origin);
    event.waitUntil(self.clients.matchAll({type:'window',includeUncontrolled:true}).then(async clients => {
        const client = clients.find(c => c.url === target.href);
        if (client) return client.focus();
        // A normal navigation rechecks the signed-in user's page permissions.
        return self.clients.openWindow(target.href);
    }));
});
