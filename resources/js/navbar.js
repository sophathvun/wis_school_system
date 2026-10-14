document.addEventListener("DOMContentLoaded", () => {
    const notificationMenu = document.querySelector("[data-navbar-notification-menu]");
    const notificationList = document.querySelector("[data-navbar-notification-list]");
    const notificationBadge = document.querySelector("[data-navbar-notification-badge]");
    const notificationToggle = document.querySelector("[data-navbar-notification-toggle]");
    const notificationClose = document.querySelector("[data-navbar-notification-close]");
    const chatBadge = document.getElementById("chat-unread-badge");
    const appIconUrl = document.querySelector('link[rel="apple-touch-icon"]')?.href || "/app-icon.svg";

    const items = notificationMenu ? JSON.parse(notificationMenu.dataset.navbarNotificationPayload || "[]") : [];
    const initialNotificationUnread = notificationMenu ? Number(notificationMenu.dataset.navbarNotificationCount || 0) : 0;
    let latestNotificationId = items[0]?.id || null;
    let chatUnread = 0;
    let notificationUnread = initialNotificationUnread;

    const showBrowserNotification = async (title, options = {}) => {
        if (!("Notification" in window) || Notification.permission !== "granted") return;
        const notificationOptions = {
            icon: appIconUrl,
            badge: appIconUrl,
            ...options,
        };
        try {
            // Mobile browsers require persistent notifications through the service worker.
            const registration = await navigator.serviceWorker?.getRegistration();
            if (registration?.active) {
                await registration.showNotification(title, notificationOptions);
                return;
            }
            const notification = new Notification(title, notificationOptions);
            if (options.data?.url) {
                notification.onclick = () => {
                    window.focus();
                    window.location.href = options.data.url;
                    notification.close();
                };
            }
        } catch {
            // Notification display must not interrupt unread counts or icon badging.
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
        [notificationBadge, document.querySelector('[data-navbar-mobile-notification-badge]')].forEach((badge) => {
            if (!badge) return;
            badge.textContent = count > 99 ? "99+" : count || "";
            badge.classList.toggle("d-none", !count);
        });
    };

    const escapeHtml = (value = "") =>
        String(value ?? "").replace(/[&<>"']/g, (char) => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;",
        })[char]);

    const escapeAttribute = (value = "") => escapeHtml(value);

    const renderNotificationList = (list) => {
        if (!notificationList) return;
        notificationList.innerHTML = list.length
            ? `${list
                  .map(
                      (item) => `
                        <a class="list-group-item navbar-notification-card" href="${escapeAttribute(item.url || "/notifications")}" target="_blank" rel="noopener noreferrer">
                            <div class="row align-items-center">
                                <div class="col-auto"><span class="status-dot ${item.read ? "" : "status-dot-animated bg-red"} d-block"></span></div>
                                <div class="col text-truncate">
                                    <div class="navbar-notification-title">${escapeHtml(item.title || "Notification")}</div>
                                    <div class="navbar-notification-message text-truncate mt-n1">${escapeHtml(item.message || "")}</div>
                                    <small class="navbar-notification-time">${escapeHtml(item.time || "")}</small>
                                </div>
                            </div>
                        </a>`,
                  )
                  .join("")}
                <div class="list-group-item navbar-notification-footer"><a class="btn btn-primary w-100" href="/notifications">View all notifications</a></div>`
            : '<div class="list-group-item text-center text-secondary py-4">No notifications.</div>';
    };

    renderNotificationBadge(notificationUnread);
    renderNotificationList(items);

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
            const skippingPending = Number(data.skipping_pending || 0);
            document.querySelectorAll('[data-skipping-pending-badge]').forEach((badge) => {
                badge.textContent = skippingPending;
                badge.classList.toggle('d-none', skippingPending === 0);
            });
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
    window.addEventListener('school:notifications-enabled', () => {
        refreshChatUnread();
        refreshNotificationsUnread();
    });
    refreshChatUnread();
    refreshNotificationsUnread();
    window.setInterval(refreshChatUnread, 5000);
    window.setInterval(refreshNotificationsUnread, 10000);
    notificationToggle?.addEventListener("click", () => {
        refreshNotificationsUnread();
    });
    notificationClose?.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (window.bootstrap?.Dropdown && notificationToggle) {
            window.bootstrap.Dropdown.getOrCreateInstance(notificationToggle).hide();
            return;
        }
        notificationMenu?.classList.remove("show");
        notificationToggle?.classList.remove("show");
        notificationToggle?.setAttribute("aria-expanded", "false");
    });

    document.querySelectorAll("[data-clear-dashboard-hero]").forEach((form) => {
        form.addEventListener("submit", () => {
            try {
                sessionStorage.removeItem("dashboardHeroStartedAt");
            } catch {}
        });
    });
});
