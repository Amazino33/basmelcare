const CACHE = 'basmelcare-staff-v1';

const STATIC_ASSETS = [
    '/',
    '/login',
    '/offline',
];

self.addEventListener('install', e => {
    self.skipWaiting();
    e.waitUntil(
        caches.open(CACHE).then(cache => cache.addAll(STATIC_ASSETS).catch(() => {}))
    );
});

self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', e => {
    // Only handle GET requests for same-origin pages
    if (e.request.method !== 'GET') return;
    if (!e.request.url.startsWith(self.location.origin)) return;

    const url = new URL(e.request.url);

    // Static assets (build/): cache-first
    if (url.pathname.startsWith('/build/')) {
        e.respondWith(
            caches.match(e.request).then(cached => cached || fetch(e.request).then(res => {
                const clone = res.clone();
                caches.open(CACHE).then(c => c.put(e.request, clone));
                return res;
            }))
        );
        return;
    }

    // Images and icons: cache-first
    if (/\.(png|svg|ico|jpg|webp|woff2?)$/.test(url.pathname)) {
        e.respondWith(
            caches.match(e.request).then(cached => cached || fetch(e.request).then(res => {
                const clone = res.clone();
                caches.open(CACHE).then(c => c.put(e.request, clone));
                return res;
            }))
        );
        return;
    }

    // Navigation (HTML pages): network-first, fall back to cache
    if (e.request.mode === 'navigate') {
        e.respondWith(
            fetch(e.request).catch(() => caches.match(e.request) || caches.match('/offline'))
        );
        return;
    }
});
