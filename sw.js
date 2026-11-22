/**
 * AsabTech - Advanced Progressive Web App Service Worker
 * Version: 3.0.0
 *
 * Hardening notes:
 * - Only same-origin GET requests are intercepted. Third-party scripts,
 *   analytics and ad frames must never break the worker.
 * - Every respondWith() path resolves to a real Response (never undefined).
 * - Cache name is versioned so a deploy can retire stale shells cleanly.
 */

const CACHE_NAME = 'tech-news-pwa-v3';
const OFFLINE_URL = '/offline';

// Core assets to pre-cache immediately on install
const PRECACHE_ASSETS = [
  '/',
  '/offline',
  '/manifest.json',
  '/assets/css/site.css',
  '/assets/js/tech-platform.js',
  '/assets/images/icons/icon-192x192.png',
  '/assets/images/icons/icon-512x512.png',
  '/assets/images/icons/favicon-32x32.png'
];

function offlineResponse() {
  return new Response('Offline - No connection', {
    status: 503,
    headers: { 'Content-Type': 'text/plain; charset=utf-8' }
  });
}

// 1. Install Event: Pre-cache core shell & activate immediately.
// Each asset is cached independently so one 404 cannot empty the whole shell.
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return Promise.all(
        PRECACHE_ASSETS.map((asset) =>
          cache.add(new Request(asset, { cache: 'reload' })).catch((err) => {
            console.warn('[PWA SW] Skipped asset:', asset, err && err.message);
          })
        )
      );
    }).then(() => self.skipWaiting())
  );
});

// 2. Activate Event: Clean up legacy caches & take control of clients
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            console.log('[PWA SW] Removing old cache:', key);
            return caches.delete(key);
          }
          return Promise.resolve();
        })
      );
    }).then(() => self.clients.claim())
  );
});

// 3. Fetch Event: Smart routing & caching strategies
self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Only handle same-origin GET requests
  if (req.method !== 'GET') return;

  let url;
  try {
    url = new URL(req.url);
  } catch (e) {
    return;
  }

  if (url.origin !== self.location.origin) return;

  // Do not intercept or cache Admin panel or API mutation routes
  if (url.pathname.startsWith('/admin') || url.pathname.startsWith('/api/')) {
    return;
  }

  // Strategy A: HTML Navigation (Network-First with Offline Fallback)
  if (req.mode === 'navigate' || (req.headers.get('accept') && req.headers.get('accept').includes('text/html'))) {
    event.respondWith(
      fetch(req)
        .then((networkRes) => {
          if (networkRes && networkRes.status === 200) {
            const resCopy = networkRes.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, resCopy)).catch(() => {});
          }
          return networkRes;
        })
        .catch(async () => {
          const cachedRes = await caches.match(req);
          if (cachedRes) return cachedRes;
          const offlineFallback = await caches.match(OFFLINE_URL);
          if (offlineFallback) return offlineFallback;
          return offlineResponse();
        })
    );
    return;
  }

  // Strategy B: Static Assets (CSS, JS, Images, Fonts) - Stale-While-Revalidate
  if (
    url.pathname.match(/\.(css|js|woff2?|ttf|eot|svg|png|jpe?g|webp|avif|ico|gif)$/i) ||
    url.pathname.startsWith('/assets/') ||
    url.pathname.startsWith('/uploads/')
  ) {
    event.respondWith(
      caches.match(req).then((cachedRes) => {
        const fetchPromise = fetch(req)
          .then((networkRes) => {
            if (networkRes && networkRes.status === 200) {
              const resCopy = networkRes.clone();
              caches.open(CACHE_NAME).then((cache) => cache.put(req, resCopy)).catch(() => {});
            }
            return networkRes;
          })
          .catch(() => cachedRes || offlineResponse());

        return cachedRes || fetchPromise;
      })
    );
    return;
  }

  // Strategy C: Default Network with Cache Fallback
  event.respondWith(
    fetch(req)
      .then((networkRes) => {
        if (networkRes && networkRes.status === 200) {
          const resCopy = networkRes.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(req, resCopy)).catch(() => {});
        }
        return networkRes;
      })
      .catch(async () => {
        const cachedRes = await caches.match(req);
        return cachedRes || offlineResponse();
      })
  );
});

// 4. Push Notification Event
self.addEventListener('push', (event) => {
  let data = {
    title: '{SITE_NAME}',
    body: 'لديك خبر تقني عاجل جديد!',
    url: '/',
    icon: '/assets/images/icons/icon-192x192.png',
    badge: '/assets/images/icons/favicon-32x32.png'
  };

  try {
    if (event.data) {
      data = Object.assign(data, event.data.json());
    }
  } catch (e) {
    if (event.data) {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: data.icon || '/assets/images/icons/icon-192x192.png',
    badge: data.badge || '/assets/images/icons/favicon-32x32.png',
    dir: 'rtl',
    lang: 'ar',
    vibrate: [100, 50, 100],
    data: {
      url: data.url || '/'
    },
    actions: [
      { action: 'open', title: 'قراءة الخبر 📖' },
      { action: 'close', title: 'إغلاق ✕' }
    ]
  };

  event.waitUntil(self.registration.showNotification(data.title, options));
});

// 5. Notification Click Event
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  if (event.action === 'close') return;

  const targetUrl = (event.notification.data && event.notification.data.url) || '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      for (let client of windowClients) {
        if (client.url === targetUrl && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
