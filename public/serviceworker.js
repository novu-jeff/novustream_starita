const CACHE_NAME = "starita-reader-v3";
const OFFLINE_URL = "/offline.html";

// list essential app shell assets to cache on install
const ASSETS_TO_CACHE = [
    "/",
    "/admin/reading",
    "/offline.html",
    "/manifest.json",
    "/favicon.ico",
    "/images/srwd_qr.png",
    "/images/client.png",
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

// FETCH — smart cache-first handling
self.addEventListener("fetch", (event) => {
    const { request } = event;
    if (request.method !== "GET") return;

    // HTML Navigation requests (refresh, new tab)
    if (request.mode === "navigate" || (request.headers.get("accept")?.includes("text/html"))) {
        event.respondWith(
            (async () => {
                try {
                    // ✅ Try network first (when online)
                    const networkResponse = await fetch(request);
                    const cache = await caches.open(CACHE_NAME);
                    cache.put(request, networkResponse.clone());
                    return networkResponse;
                } catch (err) {
                    console.warn("[SW] Offline fallback triggered:", err);
                    // ✅ Fallback to cached /admin/reading or offline.html
                    const cache = await caches.open(CACHE_NAME);
                    const cachedPage = await cache.match("/admin/reading") || await cache.match(OFFLINE_URL);
                    return cachedPage;
                }
            })()
        );
        return;
    }

    // Non-HTML requests — Cache-first, then network
    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) {
                // Update in background
                fetch(request).then((response) => {
                    if (response && response.status === 200) {
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, response));
                    }
                });
                return cached;
            }

            // Try network, then fallback to offline.html
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