const CACHE_VERSION = 'vbo-v4';

const STATIC_ASSETS = [
    '/',
    '/offline.html',
    '/css/styles.css',
    '/js/main.js',
    '/commun/menu.html',
    '/commun/footer.html',
    '/images/logo-club/LogoVBO.png',
    '/images/logo-club/Logo-VBO-blanc.png',
    '/images/favicon-36x36.png',
];

// Précharge les assets statiques à l'installation
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then(cache => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

// Supprime les anciens caches à l'activation
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

    // Ignore les requêtes non-GET et hors domaine
    if (request.method !== 'GET' || url.origin !== location.origin) return;

    // Pages PHP et HTML : network-first, fallback offline
    if (url.pathname.endsWith('.php') || url.pathname === '/' || url.pathname.endsWith('.html')) {
        event.respondWith(
            fetch(request)
                .then(response => {
                    // Met en cache une copie fraîche si succès
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

    // Assets versionnés (?v=...) : toujours depuis le réseau, jamais mis en cache
    if (url.search.includes('v=')) {
        return; // laisse le navigateur gérer avec son propre cache HTTP
    }

    // Assets statiques sans version (CSS/JS/images) : cache-first
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
