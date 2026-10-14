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
    let targetUrl = event.notification.data?.url || "/";
    // Older chat alerts linked to the desktop list. Open those in the shared drawer too.
    try {
        const target = new URL(targetUrl, self.location.origin);
        if (target.origin === self.location.origin && target.pathname.replace(/\/$/, '') === '/communication/chat') {
            target.searchParams.set('widget', '1');
            const conversationId = /^school-chat-(\d+)$/.exec(event.notification.tag || '')?.[1];
            if (!target.searchParams.has('conversation') && conversationId) target.searchParams.set('conversation', conversationId);
            targetUrl = target.href;
        }
    } catch {}
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

    const work = [self.registration.showNotification(title, options)];
    const badgeCount = Number(payload.badgeCount);
    if (Number.isSafeInteger(badgeCount) && badgeCount >= 0 && typeof self.navigator?.setAppBadge === 'function') {
        // The Badging API belongs to WorkerNavigator, including on iOS Home Screen apps.
        work.push(Promise.resolve().then(() => self.navigator.setAppBadge(badgeCount)).catch(() => {}));
    }

    event.waitUntil(Promise.all(work));
});
