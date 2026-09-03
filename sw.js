const CACHE_NAME = 'formatura-pm-v3';


const ASSETS_TO_CACHE = [
  './',
  './index.html',
  './manifest.json',
  './img/brasao_dp.png',
  './css/style.css',
  './js/app.js',
  './js/api.js',
  './js/auth.js',
  './js/views/dashboard.js',
  './js/views/agraciados.js',
  './js/views/checkin.js',
  './js/views/rsvp.js',
  './js/views/checklist.js',
  './js/views/usuarios.js'
];


self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    }).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  // Ignora requisições de API para não empacancar dados em tempo real
  if (event.request.url.includes('/api/')) {
    return;
  }

  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }
      return fetch(event.request).catch(() => {
        if (event.request.mode === 'navigate') {
          return caches.match('./index.html');
        }
      });
    })
  );
});
