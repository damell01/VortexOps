/**
 * VortexOps Service Worker
 *
 * Caching strategy:
 *  - Static assets (Vite build, fonts, icons, images): cache-first
 *  - HTML navigation: network-first with offline fallback
 *  - Livewire / JSON / POST: pass-through (no caching)
 */

const CACHE      = 'vortexops-v1';
const OFFLINE_URL = '/offline.html';

// ── Install: precache the offline page ───────────────────────────────────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE).then(cache => cache.add(OFFLINE_URL))
    );
    self.skipWaiting();
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
        return cached ?? new Response('Asset unavailable offline', { status: 503 });
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
