document.addEventListener("DOMContentLoaded", () => {
    const notificationMenu = document.querySelector("[data-navbar-notification-menu]");
    const notificationList = document.querySelector("[data-navbar-notification-list]");
    const notificationBadge = document.querySelector("[data-navbar-notification-badge]");
    const chatBadge = document.getElementById("chat-unread-badge");
    const chatLink = document.querySelector("[data-navbar-chat-link]");
    const appIconUrl = document.querySelector('link[rel="apple-touch-icon"]')?.href || "/app-icon.svg";

    const items = notificationMenu ? JSON.parse(notificationMenu.dataset.navbarNotificationPayload || "[]") : [];
    const initialNotificationUnread = notificationMenu ? Number(notificationMenu.dataset.navbarNotificationCount || 0) : 0;
    let latestNotificationId = items[0]?.id || null;
    let chatUnread = 0;
    let notificationUnread = initialNotificationUnread;

    const requestNotificationPermission = () => {
        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission().then((permission) => {
                if (permission === "granted") window.schoolEnsurePushSubscription?.();
            }).catch(() => null);
        }
    };

    const showBrowserNotification = (title, options = {}) => {
        if (!("Notification" in window) || Notification.permission !== "granted") return;
        const notification = new Notification(title, {
            icon: appIconUrl,
            badge: appIconUrl,
            ...options,
        });
        if (options.data?.url) {
            notification.onclick = () => {
                window.focus();
                window.location.href = options.data.url;
                notification.close();
            };
        }
    };

    const updateAppBadge = async () => {
        const total = Number(chatUnread || 0) + Number(notificationUnread || 0);
        try {
            if ("setAppBadge" in navigator) {
                if (total > 0) await navigator.setAppBadge(total);
                else if ("clearAppBadge" in navigator) await navigator.clearAppBadge();
            } else if ("setExperimentalAppBadge" in navigator) {
                if (total > 0) await navigator.setExperimentalAppBadge(total);
                else if ("clearExperimentalAppBadge" in navigator) await navigator.clearExperimentalAppBadge();
            }
        } catch {}
        window.dispatchEvent(new CustomEvent("school:app-badge-updated", { detail: { total, chat: chatUnread, notifications: notificationUnread } }));
    };

    const renderNotificationBadge = (count) => {
        if (!notificationBadge) return;
        notificationBadge.textContent = count || "";
        notificationBadge.classList.toggle("d-none", !count);
    };

    const renderNotificationList = (list) => {
        if (!notificationList) return;
        notificationList.innerHTML = list.length
            ? `${list
                  .map(
                      (item) => `
                        <div class="list-group-item">
                            <div class="row align-items-center">
                                <div class="col-auto"><span class="status-dot ${item.read ? "" : "status-dot-animated bg-red"} d-block"></span></div>
                                <div class="col text-truncate">
                                    <a href="${item.url}" class="text-body d-block" target="_blank" rel="noopener noreferrer">${item.title}</a>
                                    <div class="d-block text-secondary text-truncate mt-n1">${item.message || ""}</div>
                                    <small class="text-secondary">${item.time || ""}</small>
                                </div>
                            </div>
                        </div>`,
                  )
                  .join("")}
                <div class="list-group-item text-center"><a href="/notifications">View all notifications</a></div>`
            : '<div class="list-group-item text-center text-secondary py-4">No notifications.</div>';
    };

    renderNotificationBadge(notificationUnread);
    renderNotificationList(items);

    [chatLink, document.querySelector("[data-navbar-notification-toggle]"), document.getElementById("installAppShortcut")].forEach((element) => {
        element?.addEventListener("click", requestNotificationPermission, { once: true });
    });

    const refreshChatUnread = async () => {
        if (!chatBadge) return;
        try {
            const response = await fetch("/communication/chat/unread", {
                headers: { Accept: "application/json" },
            });
            if (!response.ok) return;
            const data = await response.json();
            const unreadCount = Number(data.unread || 0);
            chatBadge.textContent = unreadCount > 99 ? "99+" : unreadCount;
            chatBadge.classList.toggle("d-none", unreadCount === 0);
            if (refreshChatUnread.previousUnread !== null && unreadCount > refreshChatUnread.previousUnread) {
                showBrowserNotification("New chat message", {
                    body: "You have a new unread chat message.",
                    tag: "school-chat",
                    data: { url: "/communication/chat" },
                });
            }
            refreshChatUnread.previousUnread = unreadCount;
            chatUnread = unreadCount;
            updateAppBadge();
        } catch {}
    };

    const refreshNotificationsUnread = async () => {
        try {
            const response = await fetch("/notifications/unread", {
                headers: { Accept: "application/json" },
            });
            if (!response.ok) return;
            const data = await response.json();
            const unreadCount = Number(data.unread || 0);
            const latest = data.latest || null;
            const refreshedItems = Array.isArray(data.items) ? data.items : [];
            renderNotificationBadge(unreadCount);
            renderNotificationList(refreshedItems);
            if (refreshNotificationsUnread.previousUnread !== null && unreadCount > refreshNotificationsUnread.previousUnread && latest && latest.id !== latestNotificationId) {
                showBrowserNotification(latest.title || "New notification", {
                    body: latest.message || "You have a new notification.",
                    tag: `school-notification-${latest.id}`,
                    data: { url: latest.url || "/notifications" },
                });
            }
            latestNotificationId = latest?.id || latestNotificationId;
            refreshNotificationsUnread.previousUnread = unreadCount;
            notificationUnread = unreadCount;
            updateAppBadge();
        } catch {}
    };

    refreshChatUnread.previousUnread = null;
    refreshNotificationsUnread.previousUnread = null;
    refreshChatUnread();
    refreshNotificationsUnread();
    window.setInterval(refreshChatUnread, 5000);
    window.setInterval(refreshNotificationsUnread, 10000);
    document.querySelector("[data-navbar-notification-toggle]")?.addEventListener("click", () => {
        refreshNotificationsUnread();
    });

    document.querySelectorAll("[data-clear-dashboard-hero]").forEach((form) => {
        form.addEventListener("submit", () => {
            try {
                sessionStorage.removeItem("dashboardHeroStartedAt");
            } catch {}
        });
    });
});
