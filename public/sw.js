const CACHE_NAME = "wis-school-shell-v1";

self.addEventListener("install", (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener("activate", (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener("fetch", (event) => {
    if (event.request.method !== "GET") return;
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request, { cacheName: CACHE_NAME }))
    );
});

self.addEventListener("notificationclick", (event) => {
    event.notification.close();
    const targetUrl = event.notification.data?.url || "/";
    event.waitUntil((async () => {
        const windowClients = await clients.matchAll({ type: "window", includeUncontrolled: true });
        for (const client of windowClients) {
            if ("focus" in client) {
                await client.focus();
                if ("navigate" in client) await client.navigate(targetUrl);
                return;
            }
        }
        if (clients.openWindow) await clients.openWindow(targetUrl);
    })());
});

self.addEventListener("push", (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch {
        payload = { title: "School System", body: event.data?.text() || "You have a new update." };
    }

    const title = payload.title || "School System";
    const options = {
        body: payload.body || "You have a new notification.",
        icon: payload.icon || "/app-icon.svg",
        badge: payload.badge || "/app-icon.svg",
        tag: payload.tag || "school-system",
        data: { url: payload.url || "/" },
    };

    if (Number(payload.badgeCount || 0) > 0 && self.registration.setAppBadge) {
        event.waitUntil(self.registration.setAppBadge(Number(payload.badgeCount)).catch(() => {}));
    }

    event.waitUntil(self.registration.showNotification(title, options));
});