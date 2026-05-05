const SLAMS_STATIC_CACHE = "slams-mobile-static-v4";
const SLAMS_PAGE_CACHE = "slams-mobile-pages-v1";
const SLAMS_IMAGE_CACHE = "slams-mobile-images-v1";
const SLAMS_SCOPE = new URL(self.registration.scope);
const STATIC_ASSETS = [
    "css/theme.css",
    "css/mobile-app.css",
    "js/theme.js",
    "js/mobile-app.js",
    "manifest.webmanifest",
    "icons/slams-mobile.svg",
    "offline.html"
].map((path) => new URL(path, SLAMS_SCOPE).href);
const PUBLIC_PATHS = ["/", "/laboratories", "/assets", "/contact"];

function cacheKeyFor(request) {
    const url = new URL(request.url);
    url.search = "";
    url.hash = "";

    return url.href;
}

function isStaticAsset(request) {
    const requestKey = cacheKeyFor(request);

    return STATIC_ASSETS.includes(requestKey);
}

self.addEventListener("message", (event) => {
    if (event.data && event.data.type === "SKIP_WAITING") {
        self.skipWaiting();
    }
});

function isSameOrigin(request) {
    return new URL(request.url).origin === SLAMS_SCOPE.origin;
}

function isPublicNavigation(request) {
    const url = new URL(request.url);
    if (url.origin !== SLAMS_SCOPE.origin) {
        return false;
    }

    return PUBLIC_PATHS.some((path) => url.pathname === path || url.pathname.startsWith(path + "/"));
}

async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    const fetchPromise = fetch(request).then((response) => {
        if (response) {
            cache.put(request, response.clone());
        }

        return response;
    }).catch(() => cached || Response.error());

    return cached || fetchPromise;
}

async function networkFirstPage(request) {
    const cache = await caches.open(SLAMS_PAGE_CACHE);

    try {
        const response = await fetch(request);
        if (response && response.ok && isPublicNavigation(request)) {
            cache.put(request, response.clone());
        }

        return response;
    } catch (_error) {
        if (isPublicNavigation(request)) {
            const cached = await cache.match(request);
            if (cached) {
                return cached;
            }
        }

        const offlinePage = await caches.match(new URL("offline.html", SLAMS_SCOPE).href);
        return offlinePage || Response.error();
    }
}

self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open(SLAMS_STATIC_CACHE)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => ![SLAMS_STATIC_CACHE, SLAMS_PAGE_CACHE, SLAMS_IMAGE_CACHE].includes(key)).map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener("fetch", (event) => {
    const request = event.request;

    if (request.method !== "GET") {
        return;
    }

    if (request.mode === "navigate" && isSameOrigin(request)) {
        event.respondWith(networkFirstPage(request));
        return;
    }

    if (isStaticAsset(request)) {
        const cacheKey = cacheKeyFor(request);

        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response && response.ok) {
                        const copy = response.clone();
                        caches.open(SLAMS_STATIC_CACHE).then((cache) => cache.put(cacheKey, copy));
                    }

                    return response;
                })
                .catch(() => caches.match(cacheKey).then((cached) => cached || Response.error()))
        );
        return;
    }

    if (request.destination === "image" && isSameOrigin(request)) {
        event.respondWith(staleWhileRevalidate(request, SLAMS_IMAGE_CACHE));
        return;
    }

    if ((request.destination === "style" || request.destination === "script" || request.destination === "font") && (isSameOrigin(request) || request.url.startsWith("https://"))) {
        event.respondWith(staleWhileRevalidate(request, SLAMS_STATIC_CACHE));
    }
});

self.addEventListener("push", (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (_error) {
        payload = {
            title: "SLAMS Notification",
            body: event.data ? event.data.text() : "You have a new update."
        };
    }

    const title = payload.title || "SLAMS Notification";
    const options = {
        body: payload.body || "",
        icon: payload.icon || new URL("icons/slams-mobile.svg", SLAMS_SCOPE).href,
        badge: payload.badge || new URL("icons/slams-mobile.svg", SLAMS_SCOPE).href,
        tag: payload.tag || "slams-notification",
        requireInteraction: !!payload.requireInteraction,
        data: {
            url: payload.url || new URL("dashboard/notifications", SLAMS_SCOPE).href
        }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener("notificationclick", (event) => {
    event.notification.close();

    const targetUrl = event.notification.data && event.notification.data.url
        ? new URL(event.notification.data.url, SLAMS_SCOPE).href
        : new URL("dashboard/notifications", SLAMS_SCOPE).href;

    event.waitUntil(
        self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if ("focus" in client && client.url === targetUrl) {
                    return client.focus();
                }
            }

            for (const client of clients) {
                if ("focus" in client && client.url.startsWith(SLAMS_SCOPE.href)) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl);
            }

            return undefined;
        })
    );
});
