// Velora Service Worker - Offline capability
const CACHE_NAME = 'velora-v1';
const STATIC_CACHE = [
  '/frontend/transit.html',
  '/frontend/transit.js',
  '/frontend/api.config.js'
];

// Install - cache static assets (only same-origin to avoid CORS issues)
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_CACHE).catch((err) => {
        console.warn('SW cache failed:', err);
      });
    })
  );
  self.skipWaiting();
});

// Activate - clean old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
      );
    })
  );
  self.clients.claim();
});

// Fetch - network first, fallback to cache
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  
  // Skip chrome-extension and other non-http schemes
  if (!url.protocol.startsWith('http')) return;
  
  // Skip cross-origin requests (Leaflet, fonts, etc.) - let browser handle normally
  if (url.origin !== self.location.origin) return;
  
  // API requests: network first, cache fallback
  if (url.pathname.includes('/plan_trip.php') || 
      url.pathname.includes('/geocode.php') ||
      url.pathname.includes('/get_hotels.php')) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Cache successful API responses
          if (response.ok) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
          }
          return response;
        })
        .catch(() => {
          return caches.match(event.request).then(cached => {
            return cached || new Response(JSON.stringify({
              success: false,
              error: 'offline',
              message: 'You are offline. Cached data may be available.'
            }), {
              headers: { 'Content-Type': 'application/json' }
            });
          });
        })
    );
    return;
  }
  
  // Static assets: cache first
  event.respondWith(
    caches.match(event.request).then((cached) => {
      return cached || fetch(event.request).then((response) => {
        if (response.ok) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        }
        return response;
      });
    })
  );
});
