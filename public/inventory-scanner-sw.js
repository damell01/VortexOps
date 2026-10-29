/*
 * Retire the legacy inventory scanner service worker.
 * Previous versions cached authenticated scanner HTML cache-first, leaving
 * individual users stuck on obsolete screens even after deployment.
 * The root /sw.js handles the app's offline support.
 */
self.addEventListener('install', event => {
    event.waitUntil(self.skipWaiting());
});
self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        await Promise.all((await caches.keys())
            .filter(key => key.startsWith('inventory-scanner-'))
            .map(key => caches.delete(key)));
        await self.registration.unregister();
        const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        await Promise.all(clients.map(client => client.navigate(client.url).catch(() => {})));
    })());
});
self.addEventListener('fetch', () => {
    // No interception: scanner navigation and authenticated HTML use network.
});
