document.addEventListener("DOMContentLoaded", () => {
    const notificationMenus = [...document.querySelectorAll("[data-navbar-notification-menu]")];
    const notificationLists = [...document.querySelectorAll("[data-navbar-notification-list]")];
    const notificationBadges = [...document.querySelectorAll("[data-navbar-notification-badge], [data-navbar-mobile-notification-badge]")];
    const notificationToggles = [...document.querySelectorAll("[data-navbar-notification-toggle]")];
    const notificationCloses = [...document.querySelectorAll("[data-navbar-notification-close]")];
    const notificationFilterButtons = [...document.querySelectorAll("[data-navbar-notification-filter]")];
    const notificationReadAllButtons = [...document.querySelectorAll("[data-navbar-notification-read-all]")];
    const notificationMenu = notificationMenus[0] || null;
    const chatBadge = document.getElementById("chat-unread-badge");
    const chatLauncherBadge = document.getElementById("school-chat-unread-badge");
    const appIconUrl = document.querySelector('link[rel="apple-touch-icon"]')?.href || "/app-icon.svg";

    let notificationItems = notificationMenu ? JSON.parse(notificationMenu.dataset.navbarNotificationPayload || "[]") : [];
    const initialNotificationUnread = notificationMenu ? Number(notificationMenu.dataset.navbarNotificationCount || 0) : 0;
    let latestNotificationId = notificationItems[0]?.id || null;
    let chatUnread = 0;
    let notificationUnread = initialNotificationUnread;
    let notificationFilter = "all";

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
        notificationBadges.forEach((badge) => {
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

    const renderNotificationList = (list = notificationItems) => {
        notificationItems = Array.isArray(list) ? list : [];
        const visibleItems = notificationFilter === "unread"
            ? notificationItems.filter((item) => !item.read)
            : notificationItems;
        const markup = visibleItems.length
            ? visibleItems
                  .map(
                      (item, index) => `
                        <a class="navbar-notification-card ${item.read ? "" : "is-unread"}" href="${escapeAttribute(item.url || "/notifications")}" target="_blank" rel="noopener noreferrer"
                            data-navbar-notification-id="${escapeAttribute(item.id || "")}" data-navbar-notification-read="${item.read ? "1" : "0"}">
                            <span class="navbar-notification-icon navbar-notification-icon-tone-${(index % 4) + 1}">
                                <i class="ti ${item.read ? "ti-bell" : "ti-bell-ringing"}"></i>
                            </span>
                            <span class="navbar-notification-content">
                                <span class="navbar-notification-title">${escapeHtml(item.title || "Notification")}</span>
                                <span class="navbar-notification-message">${escapeHtml(item.message || "")}</span>
                                <span class="navbar-notification-time">${escapeHtml(item.time || "")}</span>
                            </span>
                            <span class="navbar-notification-card-end">
                                ${item.read ? "" : '<span class="navbar-notification-unread-dot" aria-label="Unread"></span>'}
                                <i class="ti ti-chevron-right" aria-hidden="true"></i>
                            </span>
                        </a>`,
                  )
                  .join("")
            : `<div class="premium-notification-empty">
                    <span><i class="ti ${notificationFilter === "unread" ? "ti-checks" : "ti-bell-off"}"></i></span>
                    <strong>${notificationFilter === "unread" ? "You're all caught up" : "No notifications yet"}</strong>
                    <small>${notificationFilter === "unread" ? "There are no unread notifications." : "New notifications will appear here."}</small>
                </div>`;

        notificationLists.forEach((notificationList) => {
            notificationList.innerHTML = markup;
        });
        document.querySelectorAll("[data-navbar-notification-summary]").forEach((summary) => {
            summary.textContent = `${notificationUnread} unread`;
        });
        document.querySelectorAll("[data-navbar-notification-unread-count]").forEach((count) => {
            count.textContent = notificationUnread > 99 ? "99+" : notificationUnread;
            count.classList.toggle("d-none", notificationUnread === 0);
        });
        notificationFilterButtons.forEach((button) => {
            const active = button.dataset.navbarNotificationFilter === notificationFilter;
            button.classList.toggle("active", active);
            button.setAttribute("aria-selected", active ? "true" : "false");
        });
        notificationReadAllButtons.forEach((button) => {
            button.disabled = notificationUnread === 0;
        });
    };

    renderNotificationBadge(notificationUnread);
    renderNotificationList(notificationItems);

    const refreshChatUnread = async () => {
        if (!chatBadge && !chatLauncherBadge) return;
        try {
            const response = await fetch("/communication/chat/unread", {
                headers: { Accept: "application/json" },
            });
            if (!response.ok) return;
            const data = await response.json();
            const unreadCount = Number(data.unread || 0);
            [chatBadge, chatLauncherBadge].forEach((badge) => {
                if (!badge) return;
                badge.textContent = unreadCount > 99 ? "99+" : unreadCount;
                badge.classList.toggle("d-none", unreadCount === 0);
            });
            if (refreshChatUnread.previousUnread !== null && unreadCount > refreshChatUnread.previousUnread) {
                const chatUrl = new URL('/communication/chat', window.location.origin);
                chatUrl.searchParams.set('widget', '1');
                const conversationId = Number(data.latest_conversation_id);
                if (Number.isSafeInteger(conversationId) && conversationId > 0) chatUrl.searchParams.set('conversation', String(conversationId));
                showBrowserNotification("New chat message", {
                    body: "You have a new unread chat message.",
                    tag: "school-chat",
                    data: { url: chatUrl.pathname + chatUrl.search },
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
            notificationUnread = unreadCount;
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
    notificationToggles.forEach((notificationToggle) => {
        notificationToggle.addEventListener("click", () => {
            refreshNotificationsUnread();
        });
    });
    notificationCloses.forEach((notificationClose) => {
        notificationClose.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();
            const menu = notificationClose.closest("[data-navbar-notification-menu]");
            const notificationToggle = menu?.parentElement?.querySelector("[data-navbar-notification-toggle]");
            if (window.bootstrap?.Dropdown && notificationToggle) {
                window.bootstrap.Dropdown.getOrCreateInstance(notificationToggle).hide();
                return;
            }
            menu?.classList.remove("show");
            notificationToggle?.classList.remove("show");
            notificationToggle?.setAttribute("aria-expanded", "false");
        });
    });
    notificationFilterButtons.forEach((button) => {
        button.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();
            notificationFilter = button.dataset.navbarNotificationFilter === "unread" ? "unread" : "all";
            renderNotificationList();
        });
    });
    notificationLists.forEach((notificationList) => {
        notificationList.addEventListener("click", (event) => {
            const card = event.target.closest("[data-navbar-notification-id]");
            if (!card || card.dataset.navbarNotificationRead === "1") return;
            const notificationId = String(card.dataset.navbarNotificationId || "");
            window.setTimeout(() => {
                notificationItems = notificationItems.map((item) =>
                    String(item.id) === notificationId ? { ...item, read: true } : item,
                );
                notificationUnread = Math.max(0, notificationUnread - 1);
                renderNotificationBadge(notificationUnread);
                renderNotificationList();
                updateAppBadge();
            }, 0);
        });
    });
    notificationReadAllButtons.forEach((button) => {
        button.addEventListener("click", async (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (button.disabled || notificationUnread === 0) return;
            notificationReadAllButtons.forEach((item) => { item.disabled = true; });
            try {
                const response = await fetch(button.dataset.url || "/notifications/read-all", {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || "",
                        Accept: "application/json",
                    },
                });
                if (!response.ok) throw new Error("Unable to mark notifications as read.");
                notificationItems = notificationItems.map((item) => ({ ...item, read: true }));
                notificationUnread = 0;
                renderNotificationBadge(0);
                renderNotificationList();
                updateAppBadge();
            } catch {
                refreshNotificationsUnread();
            } finally {
                notificationReadAllButtons.forEach((item) => { item.disabled = false; });
            }
        });
    });

    document.querySelectorAll("[data-clear-dashboard-hero]").forEach((form) => {
        form.addEventListener("submit", () => {
            try {
                sessionStorage.removeItem("dashboardHeroStartedAt");
            } catch {}
        });
    });
});
