/* TV+A OS — Service Worker (PWA)
 * Stratégies :
 *  - Navigations : réseau d'abord, page hors-ligne en secours (jamais de HTML mis en cache,
 *    pour ne jamais servir une page authentifiée à un autre utilisateur).
 *  - Ressources statiques (/build, /css, /js, /fonts, /pwa-icons, /images…) : cache-first pour
 *    les fichiers versionnés de Vite, stale-while-revalidate pour les autres.
 *  - Tout le reste (API, JSON, uploads /storage, POST…) : non intercepté.
 *
 * Note : les URLs de préchargement sont versionnées (?v=…) afin de contourner un éventuel
 * cache CDN ayant mémorisé un 404 lors du premier déploiement.
 */
'use strict';

const VERSION = 'tvaos-pwa-v4';
const PRECACHE = VERSION + '-precache';
const RUNTIME = VERSION + '-assets';

const OFFLINE_URL = '/offline.html?v=2';
const PRECACHE_URLS = [
    OFFLINE_URL,
    '/manifest.webmanifest?v=2',
    '/favicon.svg?v=2',
    '/favicon.ico?v=2',
    '/apple-touch-icon.png?v=2',
    '/pwa-icons/icon-192.png?v=2',
    '/pwa-icons/icon-512.png?v=2',
    '/pwa-icons/icon-maskable-512.png?v=2'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(PRECACHE)
            .then(function (cache) {
                // Chaque ressource est préchargée indépendamment : un échec isolé
                // ne bloque pas l'installation du service worker.
                return Promise.allSettled(PRECACHE_URLS.map(function (url) {
                    return cache.add(new Request(url, { cache: 'reload' }));
                }));
            })
            .then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys()
            .then(function (keys) {
                return Promise.all(keys.map(function (key) {
                    if (key !== PRECACHE && key !== RUNTIME) {
                        return caches.delete(key);
                    }
                    return undefined;
                }));
            })
            .then(function () { return self.clients.claim(); })
    );
});

/** Ressources statiques éligibles au cache (jamais les contenus utilisateurs). */
function isCacheableAsset(url) {
    const path = url.pathname;

    if (path.startsWith('/storage/') || path.startsWith('/media/')) {
        return false;
    }

    return path.startsWith('/build/')
        || path.startsWith('/css/')
        || path.startsWith('/js/')
        || path.startsWith('/fonts/')
        || path.startsWith('/pwa-icons/')
        || path.startsWith('/images/')
        || /^\/(?:favicon(?:-\d+x\d+)?\.(?:svg|ico|png)|apple-touch-icon\.png|manifest\.webmanifest)$/.test(path);
}

/** Fichiers versionnés par Vite : le contenu ne change jamais pour une même URL. */
function isImmutableAsset(url) {
    return url.pathname.startsWith('/build/');
}

self.addEventListener('fetch', function (event) {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname === '/sw.js' || url.pathname.startsWith('/api/') || request.headers.has('range')) {
        return;
    }

    // Navigations : réseau d'abord, page hors-ligne en secours.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(function () {
                return caches.open(PRECACHE).then(function (cache) {
                    return cache.match(OFFLINE_URL);
                }).then(function (response) {
                    return response || Response.error();
                });
            })
        );
        return;
    }

    if (!isCacheableAsset(url)) {
        return;
    }

    // Fichiers versionnés : cache d'abord, réseau en secours.
    if (isImmutableAsset(url)) {
        event.respondWith(
            caches.open(RUNTIME).then(function (cache) {
                return cache.match(request).then(function (cached) {
                    if (cached) {
                        return cached;
                    }
                    return fetch(request).then(function (response) {
                        if (response && response.ok && response.type === 'basic') {
                            cache.put(request, response.clone());
                        }
                        return response;
                    });
                });
            })
        );
        return;
    }

    // Autres statiques : stale-while-revalidate.
    event.respondWith(
        caches.open(RUNTIME).then(function (cache) {
            return cache.match(request).then(function (cached) {
                const network = fetch(request).then(function (response) {
                    if (response && response.ok && response.type === 'basic') {
                        cache.put(request, response.clone());
                    }
                    return response;
                }).catch(function () {
                    return cached;
                });

                return cached || network;
            });
        })
    );
});
