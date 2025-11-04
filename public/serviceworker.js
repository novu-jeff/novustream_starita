const CACHE_NAME = "starita-reader-v2";
const OFFLINE_URL = "/offline.html";

// list essential app shell assets to cache on install
const ASSETS_TO_CACHE = [
    "/",
    "/admin/reading",
    "/manifest.json",
    "/favicon.ico",
    "/android-chrome-192x192.png",
    "/android-chrome-512x512.png",
    "/build/assets/app.css",
    "/build/assets/app.js"
];

// INSTALL — cache essential assets
self.addEventListener("install", (event) => {
    console.log("[SW] Installing and caching assets...");
    event.waitUntil(
        (async () => {
            const cache = await caches.open(CACHE_NAME);
            try {
                await cache.addAll(ASSETS_TO_CACHE);
                console.log("[SW] Cached successfully ✅");
            } catch (err) {
                console.warn("[SW] Some assets failed to cache:", err);
            }
        })()
    );
    self.skipWaiting();
});

// ACTIVATE — cleanup old caches
self.addEventListener("activate", (event) => {
    console.log("[SW] Activating new version:", CACHE_NAME);
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys.map(key => {
                    if (key !== CACHE_NAME) {
                        console.log("[SW] Deleting old cache:", key);
                        return caches.delete(key);
                    }
                })
            )
        )
    );
    self.clients.claim();
});

// FETCH — serve from cache first, then network, then fallback
self.addEventListener("fetch", (event) => {
    const { request } = event;
    if (request.method !== "GET") return;

    // If navigation (HTML page) and offline — show fallback
    if (request.mode === "navigate") {
        event.respondWith(
            (async () => {
                try {
                    const preload = await event.preloadResponse;
                    if (preload) return preload;
                    const networkResponse = await fetch(request);
                    return networkResponse;
                } catch (err) {
                    console.warn("[SW] Offline fallback triggered:", err);
                    const cache = await caches.open(CACHE_NAME);
                    const cachedOffline = await cache.match(OFFLINE_URL);
                    return cachedOffline;
                }
            })()
        );
        return;
    }

    // For assets or API requests — cache-first, then network fallback
    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) {
                // return cached immediately, update in background
                fetch(request).then((response) => {
                    if (response && response.status === 200) {
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, response));
                    }
                });
                return cached;
            }

            // otherwise, try network and cache the response
            return fetch(request)
                .then((response) => {
                    if (!response || response.status !== 200) return response;
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    return response;
                })
                .catch(() => caches.match(OFFLINE_URL));
        })
    );
});