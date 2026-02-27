const CACHE = 'teamwizard-v3';
const STATIC = ['index.html', 'login-token.html', 'create.html', 'teams.html', 'planning.html', 'manifest.json', 'icon.svg'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(STATIC)));
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', e => {
  // Skip cross-origin and API/proxy requests — always network
  if (!e.request.url.startsWith(self.location.origin) ||
      e.request.url.includes('proxy.php') ||
      e.request.url.includes('website-files.com')) {
    return;
  }
  // Network-first for HTML pages so code updates are always picked up
  const isHtml = e.request.destination === 'document' ||
                 e.request.url.endsWith('.html') ||
                 e.request.url.endsWith('/');
  if (isHtml) {
    e.respondWith(
      fetch(e.request)
        .then(res => {
          if (res && res.ok) {
            const clone = res.clone();
            caches.open(CACHE).then(c => c.put(e.request, clone));
          }
          return res;
        })
        .catch(() => caches.match(e.request).then(c => c || caches.match('index.html')))
    );
    return;
  }
  // Cache-first for static assets (icons, manifest, etc.)
  e.respondWith(
    caches.match(e.request).then(cached => {
      if (cached) return cached;
      return fetch(e.request).then(res => {
        if (res && res.ok) {
          const clone = res.clone();
          caches.open(CACHE).then(c => c.put(e.request, clone));
        }
        return res;
      }).catch(() => caches.match('index.html'));
    })
  );
});
