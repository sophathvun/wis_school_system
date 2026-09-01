document.addEventListener("DOMContentLoaded", () => {
    const notificationMenu = document.querySelector("[data-navbar-notification-menu]");
    const notificationList = document.querySelector("[data-navbar-notification-list]");
    const notificationBadge = document.querySelector("[data-navbar-notification-badge]");
    const chatBadge = document.getElementById("chat-unread-badge");
    const chatLink = document.querySelector("[data-navbar-chat-link]");

    const items = notificationMenu ? JSON.parse(notificationMenu.dataset.navbarNotificationPayload || "[]") : [];
    const unread = notificationMenu ? Number(notificationMenu.dataset.navbarNotificationCount || 0) : 0;

    if (notificationBadge) {
        notificationBadge.textContent = unread || "";
        notificationBadge.classList.toggle("d-none", !unread);
    }
    if (notificationList) {
        notificationList.innerHTML = items.length
            ? `${items
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
    }

    chatLink?.addEventListener("click", () => {
        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission();
        }
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
            if (refreshChatUnread.previousUnread !== null && unreadCount > refreshChatUnread.previousUnread && "Notification" in window && Notification.permission === "granted") {
                new Notification("New chat message", {
                    body: "You have a new unread chat message.",
                    tag: "school-chat",
                });
            }
            refreshChatUnread.previousUnread = unreadCount;
        } catch {}
    };

    refreshChatUnread();
    window.setInterval(refreshChatUnread, 5000);

    document.querySelectorAll("[data-clear-dashboard-hero]").forEach((form) => {
        form.addEventListener("submit", () => {
            try {
                sessionStorage.removeItem("dashboardHeroStartedAt");
            } catch {}
        });
    });
});
