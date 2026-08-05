const CACHE_NAME = 'inventory-scanner-v1';
const urlsToCache = [
  '/admin/inventory/scanner',
  '/',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(urlsToCache).catch(() => {
        // Ignore cache errors, some URLs might not be cacheable
      });
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }

  // Skip cross-origin requests
  if (url.origin !== location.origin) {
    return;
  }

  // For inventory scanner page and assets, use cache first, fall back to network
  if (url.pathname.includes('/admin/inventory/scanner') ||
      url.pathname.endsWith('.js') ||
      url.pathname.endsWith('.css') ||
      url.pathname.endsWith('.png') ||
      url.pathname.endsWith('.jpg') ||
      url.pathname.endsWith('.svg')) {
    event.respondWith(
      caches.match(request).then((response) => {
        return response || fetch(request).then((fetchResponse) => {
          // Cache successful responses
          if (fetchResponse && fetchResponse.status === 200 && fetchResponse.type !== 'error') {
            const clonedResponse = fetchResponse.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(request, clonedResponse).catch(() => {});
            });
          }
          return fetchResponse;
        }).catch(() => {
          // If offline and no cache, return a basic offline response
          if (url.pathname.includes('/admin/inventory/scanner')) {
            return caches.match('/admin/inventory/scanner');
          }
          throw new Error('Network error and no cache');
        });
      })
    );
  }
});
