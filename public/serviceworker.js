const CACHE_NAME = "novustream-reader-v1";
const OFFLINE_URL = "/offline.html";

const ASSETS_TO_CACHE = [
    "/",                   // home route
    "/offline.html",       // fallback
    "/manifest.json",
    "/build/assets/app-6tlRuqkD.css",
    "/build/assets/app-B4Z_fLr2.js",
    "/build/assets/app-DZ9B2qu4.css",
    "/build/assets/dashboard-CXsZIX3a.css",
    "/build/assets/index-xsH4HHeE.js",
    "/favicon.ico",
    "/manifest.json",
    "/android-chrome-192x192.png",
    "/android-chrome-512x512.png",
];

// install — cache assets
self.addEventListener("install", (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(CACHE_NAME);
            console.log("[SW] Pre-caching essential assets...");
            try {
                await cache.addAll(ASSETS_TO_CACHE);
            } catch (err) {
                console.error("[SW] addAll failed:", err);
            }
        })()
    );
    self.skipWaiting();
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.map(key => key !== CACHE_NAME && caches.delete(key)))
        )
    );
    self.clients.claim();
});

self.addEventListener("fetch", (event) => {
    const { request } = event;
    if (request.method !== "GET") return;

    event.respondWith(
        caches.match(request).then((cached) => {
            return (
                fetch(request)
                    .then((response) => {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                        return response;
                    })
                    .catch(() => cached)
            );
        })
    );
});