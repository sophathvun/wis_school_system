document.addEventListener("DOMContentLoaded", () => {
    const shell = document.getElementById("chat-shell");
    const conversationsBox = document.getElementById("conversations");
    const userList = document.getElementById("chat-user-list");
    const newChatModalEl = document.getElementById("newChatModal");
    const modal = newChatModalEl ? bootstrap.Modal.getOrCreateInstance(newChatModalEl) : null;
    const chatEmpty = document.getElementById("chat-empty");
    const chatContent = document.getElementById("chat-content");
    const chatMessages = document.getElementById("chat-messages");
    const messageInput = document.getElementById("message-input");
    const fileInput = document.getElementById("chat-file");
    const attachmentPreview = document.getElementById("chat-attachment-preview");
    const emojiPicker = document.getElementById("chat-emoji-picker");
    const emojiGrid = document.getElementById("chat-emoji-grid");
    const recordVoiceButton = document.getElementById("chat-record-voice");
    const moreActionsButton = document.getElementById("chat-more-actions");
    const actionsMenu = document.getElementById("chat-actions-menu");
    const groupTitle = document.getElementById("group-title");
    const conversationSearch = document.getElementById("conversation-search");
    const userSearch = document.getElementById("user-search");
    const messageForm = document.getElementById("message-form");
    const newChatForm = document.getElementById("new-chat-form");
    const chatBack = document.getElementById("chat-back");
    const newChatButton = document.getElementById("new-chat");
    const newChatSidebarButton = document.getElementById("new-chat-sidebar");
    const chatAttachButton = document.getElementById("chat-attach");
    const chatEmojiButton = document.getElementById("chat-emoji");

    if (!shell || !conversationsBox || !userList || !modal || !chatEmpty || !chatContent || !chatMessages || !messageInput || !fileInput || !attachmentPreview || !emojiPicker || !emojiGrid || !recordVoiceButton || !moreActionsButton || !actionsMenu || !groupTitle || !conversationSearch || !userSearch || !messageForm || !newChatForm || !chatBack || !newChatButton || !newChatSidebarButton || !chatAttachButton || !chatEmojiButton) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
    const routes = {
        users: shell.dataset.chatUsersUrl,
        conversations: shell.dataset.chatConversationsUrl,
        create: shell.dataset.chatCreateUrl,
        heartbeat: shell.dataset.chatHeartbeatUrl,
        messagesBase: shell.dataset.chatMessagesBase,
    };
    const currentUserId = Number(shell.dataset.chatCurrentUserId || 0);
    const currentUserName = (shell.dataset.chatCurrentUserName || "").trim().toLowerCase();
    const currentUserPhoto = shell.dataset.chatCurrentUserPhoto || null;
    const esc = (value) =>
        String(value ?? "").replace(/[&<>"']/g, (ch) => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;",
        }[ch]));

    const api = async (url, options = {}) => {
        const response = await fetch(url, {
            headers: {
                "X-CSRF-TOKEN": csrf,
                Accept: "application/json",
                "Content-Type": "application/json",
                ...(options.headers || {}),
            },
            credentials: "same-origin",
            ...options,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || "Unable to complete the request.");
        return data;
    };

    const postForm = async (url, formData) => {
        const response = await fetch(url, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": csrf,
                Accept: "application/json",
            },
            credentials: "same-origin",
            body: formData,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || "Unable to upload the file.");
        return data;
    };

    const avatar = (user, className = "chat-avatar") => {
        const status = user?.online !== undefined
            ? `<span class="chat-online-dot ${user.online ? "online" : ""}" title="${user.online ? "Online" : "Offline"}"></span>`
            : "";
        if (user?.photo) {
            return `<span class="${className}" title="${esc(user.name || "")}"><img src="${esc(user.photo)}" alt="${esc(user.name || "Staff")}">${status}</span>`;
        }
        return `<span class="${className}" title="${esc(user?.name || "")}"><i class="ti ti-user"></i>${status}</span>`;
    };

    let chats = [];
    let users = [];
    let activeId = null;
    let activeConversation = null;
    let autoVoicePlayback = false;
    let selectedAttachment = null;
    let mediaRecorder = null;
    let voiceChunks = [];
    let voiceStartedAt = null;
    let voiceTimer = null;
    let voiceMimeType = "audio/webm";

    const unavailableVoiceTitle = window.isSecureContext
        ? "Voice recording is unavailable in this browser."
        : "Voice recording needs HTTPS, localhost, or a trusted secure address.";
    const supportsVoiceRecording = () => Boolean(window.isSecureContext && navigator.mediaDevices?.getUserMedia && window.MediaRecorder);

    const markVoiceRecordingUnavailable = () => {
        recordVoiceButton.disabled = true;
        recordVoiceButton.classList.add("chat-voice-unavailable");
        recordVoiceButton.title = unavailableVoiceTitle;
        recordVoiceButton.setAttribute("aria-disabled", "true");
    };

    if (!supportsVoiceRecording()) {
        markVoiceRecordingUnavailable();
    }

    const preferredVoiceType = () => {
        const types = ["audio/webm;codecs=opus", "audio/webm", "audio/ogg;codecs=opus", "audio/ogg", "audio/mp4"];
        return types.find((type) => MediaRecorder.isTypeSupported?.(type)) || "";
    };
    const voiceExtension = (mimeType) => {
        if (mimeType.includes("ogg")) return "ogg";
        if (mimeType.includes("mp4")) return "m4a";
        return "webm";
    };
    const setVoiceButtonIdle = () => {
        window.clearInterval(voiceTimer);
        voiceTimer = null;
        recordVoiceButton.classList.remove("chat-recording", "chat-sending-voice");
        recordVoiceButton.innerHTML = '<i class="ti ti-microphone"></i>';
        recordVoiceButton.title = "Record voice message";
    };
    const setVoiceButtonRecording = () => {
        recordVoiceButton.classList.add("chat-recording");
        recordVoiceButton.title = "Stop and send voice message";
        const render = () => {
            const seconds = Math.max(0, Math.floor((Date.now() - voiceStartedAt) / 1000));
            recordVoiceButton.innerHTML = `<i class="ti ti-player-stop"></i><span class="ms-1">${seconds}s</span>`;
        };
        render();
        voiceTimer = window.setInterval(render, 1000);
    };
    const setVoiceButtonSending = () => {
        window.clearInterval(voiceTimer);
        voiceTimer = null;
        recordVoiceButton.classList.remove("chat-recording");
        recordVoiceButton.classList.add("chat-sending-voice");
        recordVoiceButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        recordVoiceButton.title = "Sending voice message";
    };

    const renderUsers = (term = "") => {
        const q = term.toLowerCase();
        const selected = Array.from(userList.querySelectorAll("input:checked")).map((input) => input.value);
        const isSelf = (user) => Boolean(user.is_current_user) || Number(user.id) === currentUserId || (currentUserName && String(user.name || "").trim().toLowerCase() === currentUserName);
        const filtered = users
            .filter((user) => `${user.name} ${user.department || ""}`.toLowerCase().includes(q))
            .sort((a, b) => Number(isSelf(b)) - Number(isSelf(a)));
        userList.innerHTML = filtered.map((user) => {
            const isCurrentUser = isSelf(user);
            const isOnline = isCurrentUser || Boolean(user.online);
            return `
            <label class="form-check border rounded p-2 d-flex align-items-center gap-2 ${isCurrentUser ? "opacity-75" : ""}">
                <input class="form-check-input m-0" type="checkbox" value="${user.id}" ${selected.includes(String(user.id)) ? "checked" : ""} ${isCurrentUser ? "disabled" : ""}>
                ${avatar({ ...user, online: isOnline })}
                <span class="form-check-label flex-fill min-w-0">
                    <span class="fw-semibold d-block text-truncate">${esc(user.name)}${isCurrentUser ? " (You)" : ""}</span>
                    <small class="text-secondary">${esc(user.department || "Staff")} &middot; ${isOnline ? "Online" : "Offline"}</small>
                </span>
            </label>
        `;
        }).join("") || '<div class="text-secondary">No users found.</div>';
        groupTitle.classList.toggle("d-none", selected.length < 2);
    };

    const renderChats = () => {
        const term = conversationSearch.value.toLowerCase();
        conversationsBox.innerHTML = chats
            .filter((chat) => `${chat.title} ${chat.last_message || ""}`.toLowerCase().includes(term))
            .map((chat) => `
                <div class="chat-conversation-item p-3 ${chat.id === activeId ? "active" : ""}" data-id="${chat.id}">
                    <div class="d-flex align-items-center gap-3">
                        ${avatar({ name: chat.title, photo: chat.photo, online: chat.online }, "chat-avatar")}
                        <div class="flex-fill text-truncate">
                            <div class="fw-bold text-truncate">${esc(chat.title)}</div>
                            <div class="small text-secondary text-truncate">${chat.type === "direct" ? `${chat.online ? "Online" : "Offline"} &middot; ` : ""}${esc(chat.last_message || "No messages yet")}</div>
                        </div>
                        ${chat.unread_messages ? `<span class="badge bg-primary rounded-pill">${chat.unread_messages > 99 ? "99+" : chat.unread_messages}</span>` : ""}
                    </div>
                </div>
            `).join("") || '<div class="text-secondary text-center p-4">No conversations yet.</div>';
        conversationsBox.querySelectorAll("[data-id]").forEach((item) => {
            item.onclick = () => openChat(Number(item.dataset.id));
        });
    };

    const messageContent = (message) => {
        const text = message.message && !(message.message_type !== "text" && message.message === message.media_name)
            ? `<div class="chat-message-text">${esc(message.message)}</div>`
            : "";
        const downloadUrl = message.media_download_url || message.media_url;
        if (message.message_type === "voice" && message.media_url) {
            return `<div class="fw-semibold mb-1"><i class="ti ti-wave-sine me-1"></i>Voice message</div><audio controls src="${esc(message.media_url)}" data-voice-message-id="${message.id}"></audio>`;
        }
        if (message.message_type === "call") {
            const icon = /missed|declined/i.test(message.message || "") ? "ti-phone-off" : "ti-phone-call";
            return `<div class="chat-call-card"><i class="ti ${icon}"></i><span>${esc(message.message || "Voice call")}</span></div>`;
        }
        if (message.message_type === "image" && message.media_url) {
            return `<a href="${esc(message.media_url)}" target="_blank" rel="noopener"><img src="${esc(message.media_url)}" alt="${esc(message.media_name || "Attached image")}" class="chat-image"></a><div class="chat-attachment-actions"><a href="${esc(message.media_url)}" target="_blank" rel="noopener"><i class="ti ti-eye"></i> View</a><a href="${esc(downloadUrl)}"><i class="ti ti-download"></i> Download</a></div>${text}`;
        }
        if (message.message_type === "file" && message.media_url) {
            return `<div class="chat-file-card"><i class="ti ti-file-description fs-3"></i><a href="${esc(message.media_url)}" target="_blank" rel="noopener"><span class="file-name">${esc(message.media_name || "Attached file")}</span><small>Open attachment</small></a><a class="chat-file-download" href="${esc(downloadUrl)}" title="Download attachment"><i class="ti ti-download"></i></a></div>${text}`;
        }
        return text;
    };

    const messageStatus = (message) => {
        if (message.user_id !== currentUserId) return '<div class="chat-message-meta"><i class="ti ti-check"></i> Read</div>';
        const read = (message.read_by || []).length > 0;
        return `<div class="chat-message-meta ${read ? "read" : "unread"}"><i class="ti ti-${read ? "checks" : "check"}"></i>${read ? "Read" : "Unread"}</div>`;
    };

    const detailRows = (items, emptyText, icon) => {
        if (!items.length) return `<div class="chat-message-detail-row text-secondary"><i class="ti ${icon}"></i><span>${emptyText}</span></div>`;
        return items.map((user) => `
            <div class="chat-message-detail-row">
                ${avatar(user, "chat-read-avatar")}
                <span class="flex-fill">${esc(user.name)}</span>
                ${user.read_at ? `<span class="text-secondary">${esc(user.read_at)}</span>` : ""}
            </div>
        `).join("");
    };

    const messageDetail = (message) => {
        const isMine = message.user_id === currentUserId;
        return `
            <div class="chat-message-detail ${isMine ? "mine" : ""}" data-message-detail="${message.id}">
                <div class="chat-message-detail-title">${isMine ? "Read by" : "Message info"}</div>
                ${isMine ? detailRows(message.read_by || [], "Not read yet", "ti-eye-off") : '<div class="chat-message-detail-row text-secondary"><i class="ti ti-eye"></i><span>You have read this message.</span></div>'}
                ${isMine ? `<div class="chat-message-detail-title">Unread</div>${detailRows(message.unread_by || [], "Everyone has read this message", "ti-checks")}` : ""}
            </div>
        `;
    };

    // Keep the newest message in view after rendering. Image/file messages can
    // change the scroll height after the initial render, so re-apply after the
    // browser has laid out the content and whenever an image finishes loading.
    const scrollMessagesToLatest = () => {
        const scroll = () => {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        };
        scroll();
        requestAnimationFrame(scroll);
        setTimeout(scroll, 80);
    };

    const isMessagesNearBottom = () => chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight < 80;

    const readReceipts = (message) => {
        if (message.user_id !== currentUserId) return "";
        const readBy = message.read_by || [];
        if (!readBy.length) return '<div class="chat-read-receipts"><span class="small text-secondary"><i class="ti ti-checks"></i> Sent</span></div>';
        return `<div class="chat-read-receipts">${readBy.slice(0, 5).map((user) => avatar(user, "chat-read-avatar")).join("")}</div>`;
    };

    const isAudioPlaybackActive = () => Array.from(chatMessages.querySelectorAll("audio")).some((audio) => !audio.paused && !audio.ended);

    const refreshMessagesForVoicePlayback = async () => {
        if (!activeId) return;
        const data = await api(`${routes.messagesBase}/${activeId}/messages`);
        activeConversation = data.conversation;
        activeConversation.messages = data.messages || [];
        renderMessages({ scrollToLatest: true });
    };

    const nextVoiceMessageAfter = (messageId) => {
        const messages = activeConversation?.messages || [];
        const currentIndex = messages.findIndex((message) => Number(message.id) === Number(messageId));
        if (currentIndex < 0) return null;
        return messages.slice(currentIndex + 1).find((message) => message.message_type === "voice" && message.media_url) || null;
    };

    const playNextVoiceMessage = async (messageId) => {
        if (!autoVoicePlayback) return;
        let next = nextVoiceMessageAfter(messageId);
        if (!next) {
            await refreshMessagesForVoicePlayback().catch(() => {});
            next = nextVoiceMessageAfter(messageId);
        }
        if (!next) {
            autoVoicePlayback = false;
            return;
        }
        let nextAudio = chatMessages.querySelector(`audio[data-voice-message-id="${next.id}"]`);
        if (!nextAudio) {
            await refreshMessagesForVoicePlayback().catch(() => {});
            nextAudio = chatMessages.querySelector(`audio[data-voice-message-id="${next.id}"]`);
        }
        if (nextAudio) {
            nextAudio.play().catch(() => {
                autoVoicePlayback = false;
            });
        } else {
            autoVoicePlayback = false;
        }
    };

    const attachVoicePlaybackHandlers = () => {
        chatMessages.querySelectorAll("audio[data-voice-message-id]").forEach((audio) => {
            audio.addEventListener("play", () => {
                autoVoicePlayback = true;
                chatMessages.querySelectorAll("audio[data-voice-message-id]").forEach((other) => {
                    if (other !== audio && !other.paused) other.pause();
                });
            });
            audio.addEventListener("pause", () => {
                if (!audio.ended) autoVoicePlayback = false;
            });
            audio.addEventListener("ended", () => {
                playNextVoiceMessage(Number(audio.dataset.voiceMessageId)).catch(() => {
                    autoVoicePlayback = false;
                });
            });
        });
    };

    const renderMessages = (options = {}) => {
        if (!activeConversation) return;
        const shouldScrollToLatest = options.scrollToLatest ?? isMessagesNearBottom();
        const previousScrollTop = chatMessages.scrollTop;
        const previousScrollHeight = chatMessages.scrollHeight;
        document.getElementById("chat-title").textContent = activeConversation.title || "Conversation";
        document.getElementById("chat-members").textContent = (activeConversation.users || [])
            .filter((user) => user.id !== currentUserId)
            .map((user) => `${user.name} - ${user.online ? "Online" : "Offline"}`)
            .join(", ");
        chatMessages.innerHTML = (activeConversation.messages || []).map((message) => `
            <div class="chat-row ${message.user_id === currentUserId ? "mine" : ""}">
                ${message.user_id === currentUserId ? "" : avatar({ name: message.user_name, photo: message.user_photo, online: message.user_online })}
                <div class="chat-message ${message.user_id === currentUserId ? "mine" : ""}" data-message-id="${message.id}">
                    <div class="small opacity-75 mb-1">${esc(message.user_name)} &middot; ${esc(message.created_at)}</div>
                    ${messageContent(message)}
                    ${messageStatus(message)}
                </div>
                ${message.user_id === currentUserId ? avatar({ name: "You", photo: currentUserPhoto, online: true }) : ""}
            </div>
            ${messageDetail(message)}
            ${readReceipts(message)}
        `).join("") || '<div class="text-secondary text-center mt-4">No messages yet. Start the conversation.</div>';
        chatMessages.querySelectorAll("[data-message-id]").forEach((bubble) => {
            bubble.addEventListener("click", (event) => {
                if (event.target.closest("a, audio")) return;
                const detail = chatMessages.querySelector(`[data-message-detail="${bubble.dataset.messageId}"]`);
                const wasOpen = detail?.classList.contains("show");
                chatMessages.querySelectorAll(".chat-message-detail.show").forEach((item) => item.classList.remove("show"));
                if (detail && !wasOpen) detail.classList.add("show");
            });
        });
        attachVoicePlaybackHandlers();
        chatMessages.querySelectorAll("img").forEach((image) => {
            if (!image.complete && shouldScrollToLatest) image.addEventListener("load", scrollMessagesToLatest, { once: true });
        });
        if (shouldScrollToLatest) {
            scrollMessagesToLatest();
        } else {
            chatMessages.scrollTop = previousScrollTop + (chatMessages.scrollHeight - previousScrollHeight);
        }
    };

    const loadUsers = async () => {
        users = await api(routes.users);
        renderUsers(userSearch.value);
    };
    const loadChats = async () => {
        chats = await api(routes.conversations);
        renderChats();
    };
    const openChat = async (id, options = {}) => {
        activeId = id;
        renderChats();
        const data = await api(`${routes.messagesBase}/${id}/messages`);
        const nextMessages = data.messages || [];
        const previousCount = activeConversation?.messages?.length || 0;
        const latestChanged = (activeConversation?.messages?.[previousCount - 1]?.id || null) !== (nextMessages[nextMessages.length - 1]?.id || null);
        const skipRender = options.quiet && isAudioPlaybackActive();
        activeConversation = data.conversation;
        activeConversation.messages = nextMessages;
        chatEmpty.classList.add("d-none");
        chatContent.classList.remove("d-none");
        chatContent.classList.add("d-flex");
        shell.classList.add("has-conversation");
        if (!skipRender) renderMessages({ scrollToLatest: options.quiet ? isMessagesNearBottom() : true });
    };
    const clearAttachment = () => {
        selectedAttachment = null;
        fileInput.value = "";
        attachmentPreview.classList.add("d-none");
        attachmentPreview.innerHTML = "";
    };
    const renderAttachmentPreview = (file) => {
        const isImage = file.type.startsWith("image/");
        attachmentPreview.innerHTML = `${isImage ? `<img src="${URL.createObjectURL(file)}" alt="">` : '<i class="ti ti-file-description fs-2 text-primary"></i>'}<span class="file-name text-truncate">${esc(file.name)}<small class="d-block text-secondary">${(file.size / 1024 / 1024).toFixed(2)} MB</small></span><button type="button" class="btn btn-sm btn-outline-secondary" id="chat-remove-file" title="Remove attachment"><i class="ti ti-x"></i></button>`;
        attachmentPreview.classList.remove("d-none");
        document.getElementById("chat-remove-file").addEventListener("click", clearAttachment);
    };
    const uploadVoiceNote = async (blob, durationSeconds, mimeType = "audio/webm") => {
        if (!activeId) return;
        const formData = new FormData();
        formData.append("audio", blob, `voice-note-${Date.now()}.${voiceExtension(mimeType)}`);
        formData.append("duration_seconds", Math.max(1, Math.round(durationSeconds || 1)));
        await postForm(`${routes.messagesBase}/${activeId}/voice`, formData);
        await loadChats();
        await openChat(activeId);
    };
    const toggleVoiceRecording = async () => {
        if (!supportsVoiceRecording()) {
            markVoiceRecordingUnavailable();
            return;
        }
        if (mediaRecorder && mediaRecorder.state === "recording") {
            mediaRecorder.stop();
            return;
        }
        if (!activeId) return;
        voiceChunks = [];
        voiceStartedAt = Date.now();
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
        voiceMimeType = preferredVoiceType();
        mediaRecorder = new MediaRecorder(stream, voiceMimeType ? { mimeType: voiceMimeType } : undefined);
        voiceMimeType = mediaRecorder.mimeType || voiceMimeType || "audio/webm";
        mediaRecorder.ondataavailable = (event) => {
            if (event.data?.size) voiceChunks.push(event.data);
        };
        mediaRecorder.onstop = async () => {
            setVoiceButtonSending();
            try {
                stream.getTracks().forEach((track) => track.stop());
                const blob = new Blob(voiceChunks, { type: voiceMimeType });
                const duration = (Date.now() - voiceStartedAt) / 1000;
                if (!blob.size) throw new Error("No audio was recorded. Please allow microphone access and try again.");
                await uploadVoiceNote(blob, duration, voiceMimeType);
            } catch (error) {
                alert(error.message || "Unable to send voice message.");
            } finally {
                setVoiceButtonIdle();
            }
        };
        mediaRecorder.start();
        setVoiceButtonRecording();
    };

    const emojis = ["😀", "😃", "😄", "😁", "😂", "🤣", "😊", "😍", "🥰", "😘", "😎", "🤔", "😢", "😭", "😡", "👍", "👏", "🙏", "❤️", "💯", "🎉", "✅", "⭐", "🔥", "💡", "📚", "📎", "🙌", "👋", "✨", "🤝", "🙂"];
    emojiGrid.innerHTML = emojis.map((emoji) => `<button type="button" class="chat-emoji" data-emoji="${emoji}">${emoji}</button>`).join("");
    emojiGrid.querySelectorAll("[data-emoji]").forEach((button) => button.addEventListener("click", () => {
        const start = messageInput.selectionStart ?? messageInput.value.length;
        const end = messageInput.selectionEnd ?? messageInput.value.length;
        messageInput.value = `${messageInput.value.slice(0, start)}${button.dataset.emoji}${messageInput.value.slice(end)}`;
        messageInput.focus();
        messageInput.selectionStart = messageInput.selectionEnd = start + button.dataset.emoji.length;
    }));

    newChatButton.addEventListener("click", async () => { await loadUsers(); modal.show(); });
    newChatSidebarButton.addEventListener("click", async () => { await loadUsers(); modal.show(); });
    chatBack.addEventListener("click", () => {
        shell.classList.remove("has-conversation");
        activeId = null;
        activeConversation = null;
        chatContent.classList.add("d-none");
        chatContent.classList.remove("d-flex");
        chatEmpty.classList.remove("d-none");
        renderChats();
    });
    conversationSearch.addEventListener("input", renderChats);
    userSearch.addEventListener("input", (event) => renderUsers(event.target.value));
    userList.addEventListener("change", () => renderUsers(userSearch.value));
    moreActionsButton.addEventListener("click", () => {
        const isHidden = actionsMenu.classList.toggle("d-none");
        moreActionsButton.setAttribute("aria-expanded", String(!isHidden));
        moreActionsButton.querySelector("i").classList.toggle("ti-plus", isHidden);
        moreActionsButton.querySelector("i").classList.toggle("ti-x", !isHidden);
    });
    chatEmojiButton.addEventListener("click", () => emojiPicker.classList.toggle("d-none"));
    chatAttachButton.addEventListener("click", () => fileInput.click());
    recordVoiceButton.addEventListener("click", () => toggleVoiceRecording().catch((error) => {
        console.warn(error?.message || "Unable to record voice message.");
        setVoiceButtonIdle();
    }));
    fileInput.addEventListener("change", () => {
        const file = fileInput.files?.[0];
        if (!file) return;
        if (file.size > 20 * 1024 * 1024) {
            alert("Attachments must be 20 MB or smaller.");
            clearAttachment();
            return;
        }
        selectedAttachment = file;
        renderAttachmentPreview(file);
    });

    messageForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        const message = messageInput.value.trim();
        if ((!message && !selectedAttachment) || !activeId) return;
        const formData = new FormData();
        if (message) formData.append("message", message);
        if (selectedAttachment) formData.append("attachment", selectedAttachment, selectedAttachment.name);
        await postForm(`${routes.messagesBase}/${activeId}/messages`, formData);
        messageInput.value = "";
        clearAttachment();
        emojiPicker.classList.add("d-none");
        await loadChats();
        await openChat(activeId);
    });

    newChatForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        const ids = Array.from(userList.querySelectorAll("input:checked")).map((input) => Number(input.value));
        if (!ids.length) return;
        const data = await api(routes.create, {
            method: "POST",
            body: JSON.stringify({
                user_ids: ids,
                title: groupTitle.value,
            }),
        });
        modal.hide();
        await loadChats();
        await openChat(data.id);
    });

    window.setInterval(async () => {
        try {
            await loadChats();
            users = await api(routes.users);
            if (activeId) await openChat(activeId, { quiet: true });
        } catch {}
    }, 10000);
    api(routes.heartbeat, { method: "POST" }).catch(() => {});
    window.setInterval(() => api(routes.heartbeat, { method: "POST" }).catch(() => {}), 60000);

    loadUsers().catch(() => {});
    loadChats().catch(() => {});
});
