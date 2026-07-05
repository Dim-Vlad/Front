const CACHE_VERSION = 'vbo-v4.2.0';

const STATIC_ASSETS = [
    '/',
    '/offline.html',
    '/css/styles.css',
    '/js/main.js',
    '/commun/menu.html',
    '/images/logo-club/LogoVBO.png',
    '/images/logo-club/Logo-VBO-blanc.png',
    '/images/favicon-36x36.png',
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then(cache => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_VERSION).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== location.origin) return;

    // PHP = contenu dynamique et/ou authentifié — jamais mis en cache
    if (url.pathname.endsWith('.php')) {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html'))
        );
        return;
    }

    // Assets versionnés (?v=...) : cache HTTP du navigateur, pas le SW
    if (url.search.includes('v=')) return;

    // HTML statique et / : network-first avec fallback offline
    if (url.pathname === '/' || url.pathname.endsWith('.html')) {
        event.respondWith(
            fetch(request)
                .then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_VERSION).then(cache => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() =>
                    caches.match(request).then(cached => cached || caches.match('/offline.html'))
                )
        );
        return;
    }

    // CSS / JS / images : cache-first
    event.respondWith(
        caches.match(request).then(cached => {
            if (cached) return cached;
            return fetch(request).then(response => {
                if (response.ok) {
                    const clone = response.clone();
                    caches.open(CACHE_VERSION).then(cache => cache.put(request, clone));
                }
                return response;
            });
        })
    );
});
