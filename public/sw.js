/**
 * Service worker del POS: permite seguir vendiendo sin internet.
 * - El shell del POS (/pos) se sirve desde caché si no hay red.
 * - Los assets compilados (/build/*) se cachean al primer uso.
 * - El catálogo (/pos/catalogo) se actualiza con la red y queda
 *   la última copia como respaldo offline.
 */
const VERSION = 'cmoon-pos-v1';
const SHELL = ['/pos'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(VERSION).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((claves) => Promise.all(claves.filter((c) => c !== VERSION).map((c) => caches.delete(c))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Shell del POS y catálogo: red primero, caché como respaldo
    if (url.pathname === '/pos' || url.pathname === '/pos/catalogo') {
        event.respondWith(
            fetch(event.request)
                .then((respuesta) => {
                    const copia = respuesta.clone();
                    caches.open(VERSION).then((cache) => cache.put(event.request, copia));
                    return respuesta;
                })
                .catch(() => caches.match(event.request))
        );
        return;
    }

    // Assets versionados por Vite: caché primero (no cambian de contenido)
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(event.request).then((cacheado) => {
                if (cacheado) return cacheado;
                return fetch(event.request).then((respuesta) => {
                    const copia = respuesta.clone();
                    caches.open(VERSION).then((cache) => cache.put(event.request, copia));
                    return respuesta;
                });
            })
        );
    }
});
