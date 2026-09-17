            document.addEventListener('DOMContentLoaded', () => {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const widget = document.getElementById('school-chat-widget');
                if (!widget) return;

                const routes = {
                    users: widget.dataset.usersUrl || '',
                    conversations: widget.dataset.conversationsUrl || '',
                    unread: widget.dataset.unreadUrl || '',
                    create: widget.dataset.createUrl || '',
                    heartbeat: widget.dataset.heartbeatUrl || '',
                    messagesBase: widget.dataset.messagesBase || '',
                    callsPending: widget.dataset.callsPendingUrl || '',
                    callsBase: widget.dataset.callsBase || '',
                };

                const launcher = document.getElementById('school-chat-launcher');
                const drawer = document.getElementById('school-chat-drawer');
                const minimize = document.getElementById('school-chat-minimize');
                const unreadBadge = document.getElementById('school-chat-unread-badge');
                const search = document.getElementById('school-chat-search');
                const newGroupButton = document.getElementById('school-chat-new-group');
                const conversationsPane = document.getElementById('school-chat-conversations-pane');
                const peoplePane = document.getElementById('school-chat-people-pane');
                const conversationPane = document.getElementById('school-chat-conversation-pane');
                const backButton = document.getElementById('school-chat-back');
                const conversationTitle = document.getElementById('school-chat-conversation-title');
                const conversationMembers = document.getElementById('school-chat-conversation-members');
                const messagesBox = document.getElementById('school-chat-messages');
                const form = document.getElementById('school-chat-form');
                const input = document.getElementById('school-chat-input');
                const callStartButton = document.getElementById('school-chat-call-start');
                const recordVoiceButton = document.getElementById('school-chat-record-voice');
                const attachButton = document.getElementById('school-chat-attach');
                const moreActionsButton = document.getElementById('school-chat-more-actions');
                const actionsMenu = document.getElementById('school-chat-actions-menu');
                const fileInput = document.getElementById('school-chat-file');
                const attachmentPreview = document.getElementById('school-chat-attachment-preview');
                const emojiButton = document.getElementById('school-chat-emoji');
                const emojiPicker = document.getElementById('school-chat-emoji-picker');
                const emojiGrid = document.getElementById('school-chat-emoji-grid');
                const callPanel = document.getElementById('school-chat-call-panel');
                const callTitle = document.getElementById('school-chat-call-title');
                const callStatus = document.getElementById('school-chat-call-status');
                const callAcceptButton = document.getElementById('school-chat-call-accept');
                const callMuteButton = document.getElementById('school-chat-call-mute');
                const callEndButton = document.getElementById('school-chat-call-end');
                const remoteAudio = document.getElementById('school-chat-remote-audio');
                const tabButtons = widget.querySelectorAll('[data-school-chat-tab]');
                const currentUserId = Number(widget.dataset.currentUserId || 0);
                const currentUserName = (widget.dataset.currentUserName || '').trim().toLowerCase();
                const currentUserPhoto = widget.dataset.currentUserPhoto || null;
                const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                } [ch]));
                let conversations = [];
                let users = [];
                let activeConversationId = null;
                let activeTab = 'conversations';
                let activeConversation = null;
                let autoVoicePlayback = false;
                let groupMode = false;
                let groupSelectedUserIds = new Set();
                let groupDraftTitle = '';
                let loadingConversation = false;
                let mediaRecorder = null;
                let voiceChunks = [];
                let voiceStartedAt = null;
                let voiceTimer = null;
                let localStream = null;
                let peerConnection = null;
                let activeCall = null;
                let processedSignalIds = new Set();
                let queuedIceCandidates = [];
                let callMuted = false;
                let callAcceptedBySelf = false;
                let callResetting = false;
                let launcherDrag = null;
                let suppressLauncherClick = false;
                let selectedAttachment = null;
                let voiceMimeType = 'audio/webm';

                const unavailableVoiceTitle = window.isSecureContext
                    ? 'Voice is unavailable in this browser.'
                    : 'Voice and call need HTTPS, localhost, or a trusted secure address.';
                const supportsVoiceRecording = () => Boolean(window.isSecureContext && navigator.mediaDevices?.getUserMedia && window.MediaRecorder);
                const supportsLiveCall = () => Boolean(window.isSecureContext && navigator.mediaDevices?.getUserMedia && window.RTCPeerConnection);

                const markVoiceRecordingUnavailable = () => {
                    if (!recordVoiceButton) return;
                    recordVoiceButton.disabled = true;
                    recordVoiceButton.classList.add('school-chat-voice-unavailable');
                    recordVoiceButton.title = unavailableVoiceTitle;
                    recordVoiceButton.setAttribute('aria-disabled', 'true');
                };

                const markLiveCallUnavailable = () => {
                    if (!callStartButton) return;
                    callStartButton.disabled = true;
                    callStartButton.classList.add('school-chat-voice-unavailable');
                    callStartButton.title = unavailableVoiceTitle;
                    callStartButton.setAttribute('aria-disabled', 'true');
                };

                if (!supportsVoiceRecording()) {
                    markVoiceRecordingUnavailable();
                }
                if (!supportsLiveCall()) {
                    markLiveCallUnavailable();
                }

                const preferredVoiceType = () => {
                    const types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/ogg',
                        'audio/mp4'
                    ];
                    return types.find((type) => MediaRecorder.isTypeSupported?.(type)) || '';
                };

                const voiceExtension = (mimeType) => {
                    if (mimeType.includes('ogg')) return 'ogg';
                    if (mimeType.includes('mp4')) return 'm4a';
                    return 'webm';
                };

                const setVoiceButtonIdle = () => {
                    window.clearInterval(voiceTimer);
                    voiceTimer = null;
                    recordVoiceButton.classList.remove('school-chat-recording', 'school-chat-sending-voice');
                    recordVoiceButton.innerHTML = '<i class="ti ti-microphone"></i>';
                    recordVoiceButton.title = 'Record voice message';
                };

                const setVoiceButtonRecording = () => {
                    recordVoiceButton.classList.add('school-chat-recording');
                    recordVoiceButton.title = 'Stop and send voice message';
                    const render = () => {
                        const seconds = Math.max(0, Math.floor((Date.now() - voiceStartedAt) / 1000));
                        recordVoiceButton.innerHTML =
                            `<i class="ti ti-player-stop"></i><span class="ms-1">${seconds}s</span>`;
                    };
                    render();
                    voiceTimer = window.setInterval(render, 1000);
                };

                const setVoiceButtonSending = () => {
                    window.clearInterval(voiceTimer);
                    voiceTimer = null;
                    recordVoiceButton.classList.remove('school-chat-recording');
                    recordVoiceButton.classList.add('school-chat-sending-voice');
                    recordVoiceButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                    recordVoiceButton.title = 'Sending voice message';
                };

                const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

                const dockLauncher = () => {
                    if (launcher.parentElement !== document.body) {
                        document.body.appendChild(launcher);
                    }

                    launcher.style.setProperty('position', 'fixed', 'important');
                    launcher.style.setProperty('right', '1.25rem', 'important');
                    launcher.style.setProperty('bottom', '1.75rem', 'important');
                    launcher.style.setProperty('left', 'auto', 'important');
                    launcher.style.setProperty('top', 'auto', 'important');
                    launcher.style.setProperty('z-index', '2147483646', 'important');
                    launcher.style.setProperty('transform', 'none', 'important');
                };

                dockLauncher();

                const api = async (url, options = {}) => {
                    const response = await fetch(url, {
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            ...(options.headers || {}),
                        },
                        credentials: 'same-origin',
                        ...options,
                    });

                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(data.message || 'Unable to complete the request.');
                    }

                    return data;
                };

                const postForm = async (url, formData) => {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: formData,
                    });

                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(data.message || 'Unable to upload the audio.');
                    }

                    return data;
                };

                const setTab = (tab) => {
                    activeTab = tab;
                    tabButtons.forEach((button) => button.classList.toggle('active', button.dataset
                        .schoolChatTab === tab));
                    conversationsPane.classList.toggle('d-none', tab !== 'conversations' || !!activeConversationId);
                    peoplePane.classList.toggle('d-none', tab !== 'people' || !!activeConversationId);
                };

                const renderLauncherBadge = (count) => {
                    const unread = Number(count || 0);
                    unreadBadge.textContent = unread > 99 ? '99+' : unread;
                    unreadBadge.classList.toggle('d-none', unread === 0);
                };

                const renderConversations = () => {
                    const term = search.value.trim().toLowerCase();
                    const listPhoto = (conversation) => {
                        const content = conversation.photo ?
                            `<img src="${esc(conversation.photo)}" alt="${esc(conversation.title || 'Staff')}" class="chat-mini-photo">` :
                            `<i class="ti ti-${conversation.type === 'group' ? 'users' : 'user'}"></i>`;
                        const statusDot = conversation.type === 'direct' ?
                            `<span class="chat-mini-presence-dot ${conversation.online ? 'online' : ''}" title="${conversation.online ? 'Online' : 'Offline'}"></span>` :
                            '';

                        return `<span class="chat-mini-photo-wrap w-100 h-100">${content}${statusDot}</span>`;
                    };
                    const html = conversations
                        .filter((conversation) => {
                            const text = `${conversation.title} ${conversation.last_message || ''}`
                            .toLowerCase();
                            return text.includes(term);
                        })
                        .map((conversation) => `
                <div class="chat-mini-item px-3 py-3 border-bottom ${conversation.id === activeConversationId ? 'active' : ''}" data-conversation-id="${conversation.id}">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar avatar-sm chat-mini-avatar ${conversation.type === 'group' ? 'bg-primary-lt text-primary' : 'bg-secondary-lt text-secondary'}">
                            ${listPhoto(conversation)}
                        </div>
                        <div class="flex-fill min-w-0">
                            <div class="fw-semibold text-truncate">${esc(conversation.title)}</div>
                            <div class="small text-secondary text-truncate">${conversation.type === 'direct' ? (conversation.online ? 'Online' : 'Offline') + ' &bull; ' : ''}${esc(conversation.last_message || 'No messages yet')}</div>
                        </div>
                        ${conversation.unread_messages ? `<span class="badge bg-primary">${conversation.unread_messages}</span>` : ''}
                    </div>
                </div>
            `).join('');

                    conversationsPane.innerHTML = html ||
                        '<div class="chat-mini-empty"><div><i class="ti ti-messages fs-1"></i><div class="mt-2">No conversations yet.</div></div></div>';
                    conversationsPane.querySelectorAll('[data-conversation-id]').forEach((item) => {
                        item.addEventListener('click', () => openConversation(Number(item.dataset
                            .conversationId)));
                    });
                };

                const renderPeople = () => {
                    const term = search.value.trim().toLowerCase();
                    const isSelf = (user) => Boolean(user.is_current_user) || Number(user.id) === currentUserId || (currentUserName && String(user.name || '').trim().toLowerCase() === currentUserName);
                    const visibleUsers = users
                        .filter((user) => `${user.name} ${user.department || ''}`.toLowerCase().includes(term))
                        .sort((a, b) => Number(isSelf(b)) - Number(isSelf(a)));
                    const composer = groupMode ? `
                <div class="chat-mini-group-composer border-bottom p-3">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <div class="fw-semibold"><i class="ti ti-users-plus me-1"></i>New Group Chat</div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-group-cancel>Cancel</button>
                    </div>
                    <input type="text" class="form-control form-control-sm mb-2" data-group-title placeholder="Group name (optional)" value="${esc(groupDraftTitle)}">
                    <div class="small text-secondary mb-2">Select at least 2 staff members.</div>
                    <button type="button" class="btn btn-primary btn-sm w-100" data-group-create ${groupSelectedUserIds.size < 2 ? 'disabled' : ''}>
                        Create Group (${groupSelectedUserIds.size})
                    </button>
                </div>
            ` : '';
                    const html = visibleUsers.map((user) => {
                            const isCurrentUser = isSelf(user);
                            const isOnline = isCurrentUser || Boolean(user.online);
                            const selected = groupSelectedUserIds.has(Number(user.id));
                            const action = groupMode
                                ? (isCurrentUser
                                    ? '<span class="badge bg-primary-lt text-primary rounded-pill">You</span>'
                                    : `<label class="form-check m-0"><input class="form-check-input" type="checkbox" data-group-user="${user.id}" ${selected ? 'checked' : ''}></label>`)
                                : (isCurrentUser
                                    ? '<span class="badge bg-primary-lt text-primary rounded-pill">You</span>'
                                    : `<button type="button" class="btn btn-outline-primary btn-sm" data-user-chat="${user.id}">Chat</button>`);
                            return `
                <div class="chat-mini-item px-3 py-3 border-bottom ${isCurrentUser ? 'chat-mini-item-current' : ''} ${selected ? 'active' : ''}" data-user-id="${user.id}" data-current-user="${isCurrentUser ? 'true' : 'false'}">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar avatar-sm chat-mini-avatar ${user.photo ? '' : 'bg-primary-lt text-primary'}">
                            ${user.photo ? `<img src="${esc(user.photo)}" alt="${esc(user.name)}" class="chat-mini-photo">` : '<i class="ti ti-user"></i>'}
                        </div>
                        <div class="flex-fill min-w-0">
                            <div class="d-flex align-items-center gap-2">
                                <div class="fw-semibold text-truncate">${esc(user.name)}</div>
                                <span class="chat-mini-status ${isOnline ? 'online' : ''}" title="${isOnline ? 'Online' : 'Offline'}"></span>
                            </div>
                            <div class="small text-secondary text-truncate">${esc(user.department || 'Staff')} &middot; ${isOnline ? 'Online now' : 'Offline'}</div>
                        </div>
                        ${!isCurrentUser && !groupMode && user.unread_messages ? `<span class="badge bg-primary rounded-pill">${user.unread_messages > 99 ? '99+' : user.unread_messages}</span>` : ''}
                        ${action}
                    </div>
                </div>
            `;
                        }).join('');

                    peoplePane.innerHTML = composer + (html || '<div class="chat-mini-empty"><div><i class="ti ti-user-search fs-1"></i><div class="mt-2">No staff found.</div></div></div>');
                    peoplePane.querySelector('[data-group-cancel]')?.addEventListener('click', () => {
                        groupMode = false;
                        groupSelectedUserIds = new Set();
                        renderPeople();
                    });
                    peoplePane.querySelector('[data-group-title]')?.addEventListener('input', (event) => {
                        groupDraftTitle = event.target.value;
                    });
                    peoplePane.querySelector('[data-group-create]')?.addEventListener('click', () => {
                        const title = peoplePane.querySelector('[data-group-title]')?.value || groupDraftTitle;
                        startGroupChat(Array.from(groupSelectedUserIds), title).catch((error) => alert(error.message || 'Unable to create group chat.'));
                    });
                    peoplePane.querySelectorAll('[data-group-user]').forEach((checkbox) => {
                        checkbox.addEventListener('change', (event) => {
                            const id = Number(checkbox.dataset.groupUser);
                            if (event.target.checked) groupSelectedUserIds.add(id);
                            else groupSelectedUserIds.delete(id);
                            renderPeople();
                        });
                    });
                    peoplePane.querySelectorAll('[data-user-id]').forEach((item) => {
                        item.addEventListener('click', (event) => {
                            if (item.dataset.currentUser === 'true' || event.target.closest('button[data-user-chat], input, label, button')) return;
                            if (groupMode) {
                                const id = Number(item.dataset.userId);
                                if (groupSelectedUserIds.has(id)) groupSelectedUserIds.delete(id);
                                else groupSelectedUserIds.add(id);
                                renderPeople();
                                return;
                            }
                            startDirectChat(Number(item.dataset.userId)).catch((error) => alert(
                                error.message || 'Unable to start chat.'));
                        });
                    });
                    peoplePane.querySelectorAll('[data-user-chat]').forEach((button) => {
                        button.addEventListener('click', (event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            startDirectChat(Number(button.dataset.userChat)).catch((error) => alert(
                                error.message || 'Unable to start chat.'));
                        });
                    });
                };
                const isAudioPlaybackActive = () => Array.from(messagesBox.querySelectorAll('audio')).some((audio) => !audio.paused && !audio.ended);

                const refreshMessagesForVoicePlayback = async () => {
                    if (!activeConversationId) return;
                    const data = await api(`${routes.messagesBase}/${activeConversationId}/messages`);
                    activeConversation = data.conversation;
                    activeConversation.messages = data.messages || [];
                    renderConversationView();
                };

                const nextVoiceMessageAfter = (messageId) => {
                    const messages = activeConversation?.messages || [];
                    const currentIndex = messages.findIndex((message) => Number(message.id) === Number(messageId));
                    if (currentIndex < 0) return null;
                    return messages.slice(currentIndex + 1).find((message) => message.message_type === 'voice' && message.media_url) || null;
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
                    let nextAudio = messagesBox.querySelector(`audio[data-voice-message-id="${next.id}"]`);
                    if (!nextAudio) {
                        await refreshMessagesForVoicePlayback().catch(() => {});
                        nextAudio = messagesBox.querySelector(`audio[data-voice-message-id="${next.id}"]`);
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
                    messagesBox.querySelectorAll('audio[data-voice-message-id]').forEach((audio) => {
                        audio.addEventListener('play', () => {
                            autoVoicePlayback = true;
                            messagesBox.querySelectorAll('audio[data-voice-message-id]').forEach((other) => {
                                if (other !== audio && !other.paused) other.pause();
                            });
                        });
                        audio.addEventListener('pause', () => {
                            if (!audio.ended) autoVoicePlayback = false;
                        });
                        audio.addEventListener('ended', () => {
                            playNextVoiceMessage(Number(audio.dataset.voiceMessageId)).catch(() => {
                                autoVoicePlayback = false;
                            });
                        });
                    });
                };

                const scrollMessagesToLatest = () => {
                    const scroll = () => {
                        messagesBox.scrollTop = messagesBox.scrollHeight;
                    };
                    scroll();
                    requestAnimationFrame(scroll);
                    setTimeout(scroll, 80);
                };

                const renderConversationView = () => {
                    if (!activeConversation) return;
                    conversationTitle.textContent = activeConversation.title || 'Conversation';
                    conversationMembers.textContent = (activeConversation.users || [])
                        .filter((user) => user.id !== currentUserId)
                        .map((user) => `${user.name} &bull; ${user.online ? 'Online' : 'Offline'}`)
                        .join(', ');
                    callStartButton.classList.toggle('d-none', activeConversation.type !== 'direct');
                    const avatar = (user, className = 'chat-mini-message-avatar') => {
                        const statusDot = user?.online !== undefined ?
                            `<span class="chat-mini-presence-dot ${user.online ? 'online' : ''}" title="${user.online ? 'Online' : 'Offline'}"></span>` :
                            '';
                        if (user?.photo) {
                            return `<span class="${className}" title="${esc(user.name || '')}"><img src="${esc(user.photo)}" alt="${esc(user.name || 'Staff')}" class="chat-mini-photo">${statusDot}</span>`;
                        }

                        return `<span class="${className}" title="${esc(user?.name || '')}"><i class="ti ti-user"></i>${statusDot}</span>`;
                    };
                    const messageContent = (message) => {
                        if (message.message_type === 'voice' && message.media_url) {
                            return `<div class="fw-semibold mb-1"><i class="ti ti-wave-sine me-1"></i>Voice message</div><audio controls src="${esc(message.media_url)}" data-voice-message-id="${message.id}"></audio>`;
                        }
                        if (message.message_type === 'call') {
                            const icon = /missed|declined/i.test(message.message || '') ? 'ti-phone-off' : 'ti-phone-call';
                            return `<div class="chat-mini-call-card"><i class="ti ${icon}"></i><span>${esc(message.message || 'Voice call')}</span></div>`;
                        }

                        const text = message.message && !(message.message_type !== 'text' && message.message ===
                                message.media_name) ?
                            `<div class="chat-mini-message-text">${esc(message.message)}</div>` : '';
                        const downloadUrl = message.media_download_url || message.media_url;
                        if (message.message_type === 'image' && message.media_url) {
                            return `<a href="${esc(message.media_url)}" target="_blank" rel="noopener"><img src="${esc(message.media_url)}" alt="${esc(message.media_name || 'Attached image')}" class="chat-mini-message-image"></a><div class="chat-mini-attachment-actions"><a href="${esc(message.media_url)}" target="_blank" rel="noopener"><i class="ti ti-eye"></i> View</a><a href="${esc(downloadUrl)}" rel="noopener"><i class="ti ti-download"></i> Download</a></div>${text}`;
                        }
                        if (message.message_type === 'file' && message.media_url) {
                            return `<div class="chat-mini-file-card"><i class="ti ti-file-description fs-3"></i><a href="${esc(message.media_url)}" target="_blank" rel="noopener"><span class="file-name">${esc(message.media_name || 'Attached file')}</span><small>Open attachment</small></a><a class="chat-mini-file-download" href="${esc(downloadUrl)}" rel="noopener" title="Download attachment"><i class="ti ti-download"></i></a></div>${text}`;
                        }

                        return text;
                    };
                    const messageStatus = (message) => {
                        const isMine = message.user_id === currentUserId;
                        if (isMine) {
                            const isRead = (message.read_by || []).length > 0;
                            return `<div class="chat-mini-message-meta chat-mini-message-status ${isRead ? 'read' : 'unread'}"><i class="ti ti-${isRead ? 'checks' : 'check'}"></i>${isRead ? 'Read' : 'Unread'}</div>`;
                        }

                        return '<div class="chat-mini-message-meta"><i class="ti ti-check"></i> Read</div>';
                    };
                    const receiptContent = (message) => {
                        if (message.user_id !== currentUserId) return '';
                        const readBy = message.read_by || [];
                        if (readBy.length) {
                            return `<div class="chat-mini-read-receipts">${readBy.slice(0, 5).map((user) => avatar(user, 'chat-mini-read-avatar')).join('')}</div>`;
                        }

                        return '<div class="chat-mini-read-receipts"><span class="chat-mini-delivered"><i class="ti ti-checks"></i> Sent</span></div>';
                    };
                    const detailRows = (users, emptyText, icon) => {
                        if (!users.length) {
                            return `<div class="chat-mini-message-detail-row text-secondary"><i class="ti ${icon}"></i><span>${emptyText}</span></div>`;
                        }

                        return users.map((user) => `
                <div class="chat-mini-message-detail-row">
                    ${avatar(user, 'chat-mini-read-avatar')}
                    <span class="flex-fill">${esc(user.name)}</span>
                    ${user.read_at ? `<span class="text-secondary">${esc(user.read_at)}</span>` : ''}
                </div>
            `).join('');
                    };
                    const messageDetail = (message) => {
                        const readBy = message.read_by || [];
                        const unreadBy = message.unread_by || [];
                        const isMine = message.user_id === currentUserId;
                        const readTitle = isMine ? 'Read by' : 'Message info';
                        return `
                <div class="chat-mini-message-detail ${isMine ? 'mine' : ''}" data-message-detail="${message.id}">
                    <div class="chat-mini-message-detail-title">${readTitle}</div>
                    ${isMine ? detailRows(readBy, 'Not read yet', 'ti-eye-off') : `<div class="chat-mini-message-detail-row text-secondary"><i class="ti ti-eye"></i><span>You have read this message.</span></div>`}
                    ${isMine ? `<div class="chat-mini-message-detail-title">Unread</div>${detailRows(unreadBy, 'Everyone has read this message', 'ti-checks')}` : ''}
                </div>
            `;
                    };

                    messagesBox.innerHTML = (activeConversation.messages || []).map((message) => `
            <div class="chat-mini-row ${message.user_id === currentUserId ? 'mine' : ''}">
                ${message.user_id === currentUserId ? '' : avatar({ name: message.user_name, photo: message.user_photo, online: message.user_online })}
                <div class="chat-mini-message ${message.user_id === currentUserId ? 'mine' : ''}" data-message-id="${message.id}">
                    <div class="small opacity-75 mb-1">${esc(message.user_name)} &middot; ${esc(message.created_at)}</div>
                    ${messageContent(message)}
                    ${messageStatus(message)}
                </div>
                ${message.user_id === currentUserId ? avatar({ name: 'You', photo: currentUserPhoto, online: true }) : ''}
            </div>
            ${messageDetail(message)}
            ${receiptContent(message)}
        `).join('') || '<div class="chat-mini-empty"><div><i class="ti ti-message-dots fs-1"></i><div class="mt-2">No messages yet. Start the conversation.</div></div></div>';
                    messagesBox.querySelectorAll('[data-message-id]').forEach((bubble) => {
                        bubble.addEventListener('click', (event) => {
                            if (event.target.closest('audio')) return;
                            const detail = messagesBox.querySelector(
                                `[data-message-detail="${bubble.dataset.messageId}"]`);
                            const wasOpen = detail?.classList.contains('show');
                            messagesBox.querySelectorAll('.chat-mini-message-detail.show').forEach((
                                item) => item.classList.remove('show'));
                            if (detail && !wasOpen) detail.classList.add('show');
                        });
                    });
                    attachVoicePlaybackHandlers();
                    messagesBox.querySelectorAll('img').forEach((image) => {
                        if (!image.complete) image.addEventListener('load', scrollMessagesToLatest, { once: true });
                    });
                    scrollMessagesToLatest();
                };

                const openConversation = async (id, options = {}) => {
                    if (loadingConversation) return;
                    loadingConversation = true;
                    try {
                        activeConversationId = id;
                        const data = await api(`${routes.messagesBase}/${id}/messages`);
                        const nextMessages = data.messages || [];
                        const previousCount = activeConversation?.messages?.length || 0;
                        const latestChanged = (activeConversation?.messages?.[previousCount - 1]?.id || null) !== (nextMessages[nextMessages.length - 1]?.id || null);
                        const skipRender = options.quiet && isAudioPlaybackActive();
                        activeConversation = data.conversation;
                        activeConversation.messages = nextMessages;
                        setTab(activeTab);
                        conversationsPane.classList.add('d-none');
                        peoplePane.classList.add('d-none');
                        conversationPane.classList.remove('d-none');
                        conversationPane.classList.add('d-flex');
                        renderConversations();
                        if (!skipRender) renderConversationView();
                        drawer.classList.remove('d-none');
                        document.body.classList.add('school-chat-drawer-open');
                        launcher.setAttribute('aria-expanded', 'true');
                    } finally {
                        loadingConversation = false;
                    }
                };

                const startDirectChat = async (userId) => {
                    const data = await api(routes.create, {
                        method: 'POST',
                        body: JSON.stringify({
                            user_ids: [userId]
                        }),
                    });

                    await refreshData();
                    await openConversation(data.id);
                };

                const startGroupChat = async (userIds, title = '') => {
                    if (userIds.length < 2) {
                        alert('Please select at least 2 staff members for a group chat.');
                        return;
                    }

                    const data = await api(routes.create, {
                        method: 'POST',
                        body: JSON.stringify({
                            user_ids: userIds,
                            title
                        }),
                    });

                    groupMode = false;
                    groupSelectedUserIds = new Set();
                    groupDraftTitle = '';
                    await refreshData();
                    await openConversation(data.id);
                };

                const uploadVoiceNote = async (blob, durationSeconds, mimeType = 'audio/webm') => {
                    if (!activeConversationId) return;
                    const formData = new FormData();
                    formData.append('audio', blob, `voice-note-${Date.now()}.${voiceExtension(mimeType)}`);
                    formData.append('duration_seconds', Math.max(1, Math.round(durationSeconds || 1)));
                    await postForm(`${routes.messagesBase}/${activeConversationId}/voice`, formData);
                    await openConversation(activeConversationId);
                    await refreshData();
                };

                const toggleVoiceRecording = async () => {
                    if (!supportsVoiceRecording()) {
                        markVoiceRecordingUnavailable();
                        return;
                    }

                    if (mediaRecorder && mediaRecorder.state === 'recording') {
                        mediaRecorder.stop();
                        return;
                    }

                    if (!activeConversationId) return;
                    voiceChunks = [];
                    voiceStartedAt = Date.now();
                    const stream = await navigator.mediaDevices.getUserMedia({
                        audio: true,
                        video: false
                    });
                    voiceMimeType = preferredVoiceType();
                    mediaRecorder = new MediaRecorder(stream, voiceMimeType ? {
                        mimeType: voiceMimeType
                    } : undefined);
                    voiceMimeType = mediaRecorder.mimeType || voiceMimeType || 'audio/webm';
                    mediaRecorder.ondataavailable = (event) => {
                        if (event.data?.size) voiceChunks.push(event.data);
                    };
                    mediaRecorder.onstop = async () => {
                        setVoiceButtonSending();
                        try {
                            stream.getTracks().forEach((track) => track.stop());
                            const blob = new Blob(voiceChunks, {
                                type: voiceMimeType
                            });
                            const duration = (Date.now() - voiceStartedAt) / 1000;
                            if (!blob.size) throw new Error(
                                'No audio was recorded. Please allow microphone access and try again.'
                                );
                            await uploadVoiceNote(blob, duration, voiceMimeType);
                        } catch (error) {
                            alert(error.message || 'Unable to send voice message.');
                        } finally {
                            setVoiceButtonIdle();
                        }
                    };
                    mediaRecorder.start();
                    setVoiceButtonRecording();
                };

                const sendCallSignal = async (signalType, payload = {}) => {
                    if (!activeCall) return null;
                    return api(`${routes.callsBase}/${activeCall.id}/signals`, {
                        method: 'POST',
                        body: JSON.stringify({
                            signal_type: signalType,
                            payload
                        }),
                    });
                };

                const resetCallState = () => {
                    if (callResetting) return;
                    callResetting = true;
                    if (peerConnection) {
                        peerConnection.onicecandidate = null;
                        peerConnection.ontrack = null;
                        peerConnection.close();
                    }
                    if (localStream) {
                        localStream.getTracks().forEach((track) => track.stop());
                    }
                    peerConnection = null;
                    localStream = null;
                    activeCall = null;
                    processedSignalIds = new Set();
                    queuedIceCandidates = [];
                    callMuted = false;
                    callAcceptedBySelf = false;
                    remoteAudio.srcObject = null;
                    callPanel.classList.add('d-none');
                    callAcceptButton.classList.add('d-none');
                    callMuteButton.classList.add('d-none');
                    callMuteButton.innerHTML = '<i class="ti ti-microphone me-1"></i> Mute';
                    callResetting = false;
                };

                const showCallPanel = (call, mode = 'calling') => {
                    activeCall = call;
                    callAcceptedBySelf = mode !== 'incoming';
                    callPanel.classList.remove('d-none');
                    callTitle.textContent = call.title || 'Voice Call';
                    callStatus.textContent = mode === 'incoming' ?
                        'Incoming voice call...' :
                        (call.status === 'active' ? 'Connected' : 'Calling...');
                    callAcceptButton.classList.toggle('d-none', mode !== 'incoming');
                    callMuteButton.classList.toggle('d-none', mode === 'incoming');
                    callEndButton.innerHTML = mode === 'incoming' ?
                        '<i class="ti ti-phone-off me-1"></i> Decline' :
                        '<i class="ti ti-phone-off me-1"></i> End';
                };

                const setupPeerConnection = async () => {
                    if (peerConnection) return peerConnection;
                    localStream = await navigator.mediaDevices.getUserMedia({
                        audio: true,
                        video: false
                    });
                    peerConnection = new RTCPeerConnection({
                        iceServers: [{
                                urls: 'stun:stun.l.google.com:19302'
                            },
                            {
                                urls: 'stun:stun1.l.google.com:19302'
                            },
                        ],
                    });
                    localStream.getTracks().forEach((track) => peerConnection.addTrack(track, localStream));
                    peerConnection.ontrack = (event) => {
                        remoteAudio.srcObject = event.streams[0];
                    };
                    peerConnection.onicecandidate = (event) => {
                        if (event.candidate) {
                            sendCallSignal('ice', event.candidate.toJSON()).catch(() => {});
                        }
                    };
                    peerConnection.onconnectionstatechange = () => {
                        if (!peerConnection || !activeCall) return;
                        if (peerConnection.connectionState === 'connected') {
                            callStatus.textContent = 'Connected';
                            callMuteButton.classList.remove('d-none');
                            remoteAudio.play?.().catch(() => {});
                        }
                        if (peerConnection.connectionState === 'connecting') {
                            callStatus.textContent = 'Connecting...';
                        }
                        if (peerConnection.connectionState === 'failed') {
                            callStatus.textContent = 'Connection failed. Please end and call again.';
                        }
                    };
                    peerConnection.oniceconnectionstatechange = () => {
                        if (!peerConnection || !activeCall) return;
                        if (peerConnection.iceConnectionState === 'connected' || peerConnection.iceConnectionState === 'completed') {
                            callStatus.textContent = 'Connected';
                            callMuteButton.classList.remove('d-none');
                            remoteAudio.play?.().catch(() => {});
                        }
                        if (peerConnection.iceConnectionState === 'disconnected') {
                            callStatus.textContent = 'Reconnecting...';
                        }
                    };
                    return peerConnection;
                };

                const flushQueuedIce = async () => {
                    if (!peerConnection?.remoteDescription) return;
                    while (queuedIceCandidates.length) {
                        const candidate = queuedIceCandidates.shift();
                        try {
                            await peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
                        } catch (error) {
                            // Ignore candidates that arrive too early for a particular browser.
                        }
                    }
                };

                const processCallSignal = async (signal) => {
                    if (!activeCall || processedSignalIds.has(signal.id) || signal.user_id === currentUserId)
                        return;
                    if (signal.signal_type === 'offer' && !callAcceptedBySelf) return;
                    processedSignalIds.add(signal.id);

                    if (signal.signal_type === 'accept') {
                        callStatus.textContent = 'Connecting...';
                        callMuteButton.classList.remove('d-none');
                    }

                    if (signal.signal_type === 'offer') {
                        await setupPeerConnection();
                        await peerConnection.setRemoteDescription(new RTCSessionDescription(signal.payload));
                        const answer = await peerConnection.createAnswer();
                        await peerConnection.setLocalDescription(answer);
                        await sendCallSignal('answer', answer);
                        await flushQueuedIce();
                    }

                    if (signal.signal_type === 'answer' && peerConnection && !peerConnection
                        .currentRemoteDescription) {
                        await peerConnection.setRemoteDescription(new RTCSessionDescription(signal.payload));
                        await flushQueuedIce();
                        callStatus.textContent = 'Connected';
                        callMuteButton.classList.remove('d-none');
                        remoteAudio.play?.().catch(() => {});
                    }

                    if (signal.signal_type === 'ice') {
                        if (!peerConnection?.remoteDescription) {
                            queuedIceCandidates.push(signal.payload);
                            return;
                        }
                        await peerConnection.addIceCandidate(new RTCIceCandidate(signal.payload)).catch(
                        () => {});
                    }

                    if (['reject', 'hangup', 'leave'].includes(signal.signal_type)) {
                        resetCallState();
                    }
                };

                const refreshActiveCall = async () => {
                    if (!activeCall) return;
                    const data = await api(`${routes.callsBase}/${activeCall.id}/signals`);
                    activeCall = data.call;
                    if (['ended', 'declined', 'missed'].includes(activeCall.status)) {
                        resetCallState();
                        return;
                    }
                    for (const signal of (data.signals || [])) {
                        await processCallSignal(signal);
                    }
                };

                const startVoiceCall = async () => {
                    if (!activeConversationId || activeConversation?.type !== 'direct') {
                        alert('Live voice call is available for direct staff chat first.');
                        return;
                    }

                    if (!supportsLiveCall()) {
                        markLiveCallUnavailable();
                        return;
                    }

                    const call = await api(`${routes.messagesBase}/${activeConversationId}/call`, {
                        method: 'POST',
                        body: JSON.stringify({
                            call_type: 'audio'
                        }),
                    });
                    showCallPanel(call, 'calling');
                    await setupPeerConnection();
                    const offer = await peerConnection.createOffer();
                    await peerConnection.setLocalDescription(offer);
                    await sendCallSignal('offer', offer);
                };

                const acceptIncomingCall = async () => {
                    if (!activeCall) return;
                    callAcceptedBySelf = true;
                    showCallPanel(activeCall, 'active');
                    await sendCallSignal('accept');
                    await setupPeerConnection();
                    await refreshActiveCall();
                    callStatus.textContent = 'Connecting...';
                };

                const endCurrentCall = async () => {
                    if (activeCall) {
                        await sendCallSignal(callAcceptButton.classList.contains('d-none') ? 'hangup' :
                            'reject').catch(() => {});
                    }
                    resetCallState();
                };

                const toggleMute = () => {
                    if (!localStream) return;
                    callMuted = !callMuted;
                    localStream.getAudioTracks().forEach((track) => {
                        track.enabled = !callMuted;
                    });
                    callMuteButton.innerHTML = callMuted ?
                        '<i class="ti ti-microphone-off me-1"></i> Unmute' :
                        '<i class="ti ti-microphone me-1"></i> Mute';
                };

                const checkIncomingCalls = async () => {
                    if (activeCall) return;
                    const data = await api(routes.callsPending);
                    const incoming = (data.calls || []).find((call) => call.status === 'ringing' && !call
                        .is_caller);
                    if (incoming) {
                        showCallPanel(incoming, 'incoming');
                    }
                };

                const refreshData = async () => {
                    try {
                        await api(routes.heartbeat, { method: 'POST' }).catch(() => {});
                        const [conversationData, usersData, unreadData] = await Promise.all([
                            api(routes.conversations),
                            api(routes.users),
                            api(routes.unread),
                        ]);

                        conversations = conversationData || [];
                        users = usersData || [];
                        renderLauncherBadge(unreadData.unread || 0);
                        if (!activeConversationId) {
                            renderConversations();
                            renderPeople();
                        } else if (activeTab === 'conversations') {
                            renderConversations();
                        } else {
                            renderPeople();
                        }
                    } catch (error) {
                        // Keep the widget usable even if one request fails briefly.
                    }
                };

                const openDrawer = async () => {
                    drawer.classList.remove('d-none');
                    document.body.classList.add('school-chat-drawer-open');
                    launcher.setAttribute('aria-expanded', 'true');
                    await refreshData();
                    if (activeConversationId && !conversationPane.classList.contains('d-none')) {
                        await openConversation(activeConversationId);
                    }
                };

                const closeDrawer = () => {
                    drawer.classList.add('d-none');
                    document.body.classList.remove('school-chat-drawer-open');
                    launcher.setAttribute('aria-expanded', 'false');
                };

                launcher.addEventListener('pointerdown', (event) => {
                    if (event.button !== undefined && event.button !== 0) return;
                    const rect = launcher.getBoundingClientRect();
                    launcherDrag = {
                        pointerId: event.pointerId,
                        offsetX: event.clientX - rect.left,
                        offsetY: event.clientY - rect.top,
                        startX: event.clientX,
                        startY: event.clientY,
                        moved: false,
                    };
                    launcher.classList.add('dragging');
                    launcher.setPointerCapture(event.pointerId);
                });

                launcher.addEventListener('pointermove', (event) => {
                    if (!launcherDrag || launcherDrag.pointerId !== event.pointerId) return;
                    const deltaX = Math.abs(event.clientX - launcherDrag.startX);
                    const deltaY = Math.abs(event.clientY - launcherDrag.startY);
                    if (deltaX > 4 || deltaY > 4) launcherDrag.moved = true;
                    if (!launcherDrag.moved) return;

                    const width = launcher.offsetWidth || 56;
                    const height = launcher.offsetHeight || 56;
                    const left = clamp(event.clientX - launcherDrag.offsetX, 12, window.innerWidth - width -
                    12);
                    const top = clamp(event.clientY - launcherDrag.offsetY, 12, window.innerHeight - height -
                        12);
                    launcher.style.setProperty('position', 'fixed', 'important');
                    launcher.style.setProperty('left', `${left}px`, 'important');
                    launcher.style.setProperty('top', `${top}px`, 'important');
                    launcher.style.setProperty('right', 'auto', 'important');
                    launcher.style.setProperty('bottom', 'auto', 'important');
                });

                launcher.addEventListener('pointerup', (event) => {
                    if (!launcherDrag || launcherDrag.pointerId !== event.pointerId) return;
                    launcher.classList.remove('dragging');
                    try {
                        launcher.releasePointerCapture(event.pointerId);
                    } catch (error) {}
                    if (launcherDrag.moved) {
                        suppressLauncherClick = true;
                        window.setTimeout(() => {
                            suppressLauncherClick = false;
                        }, 0);
                    } else {
                        suppressLauncherClick = false;
                    }
                    launcherDrag = null;
                });

                launcher.addEventListener('pointercancel', () => {
                    launcher.classList.remove('dragging');
                    launcherDrag = null;
                });

                launcher.addEventListener('click', async () => {
                    if (suppressLauncherClick) return;
                    if (drawer.classList.contains('d-none')) {
                        await openDrawer();
                    } else {
                        closeDrawer();
                    }
                });

                minimize.addEventListener('click', closeDrawer);

                backButton.addEventListener('click', () => {
                    activeConversationId = null;
                    activeConversation = null;
                    conversationPane.classList.add('d-none');
                    conversationPane.classList.remove('d-flex');
                    setTab(activeTab);
                    if (activeTab === 'conversations') {
                        renderConversations();
                    } else {
                        renderPeople();
                    }
                });

                newGroupButton?.addEventListener('click', () => {
                    groupMode = true;
                    groupSelectedUserIds = new Set();
                    groupDraftTitle = '';
                    activeConversationId = null;
                    activeConversation = null;
                    conversationPane.classList.add('d-none');
                    conversationPane.classList.remove('d-flex');
                    setTab('people');
                    renderPeople();
                });

                tabButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        setTab(button.dataset.schoolChatTab);
                        if (activeConversationId) return;
                        if (activeTab === 'conversations') {
                            renderConversations();
                        } else {
                            renderPeople();
                        }
                    });
                });

                search.addEventListener('input', () => {
                    if (activeConversationId) return;
                    if (activeTab === 'conversations') {
                        renderConversations();
                    } else {
                        renderPeople();
                    }
                });

                const clearAttachment = () => {
                    selectedAttachment = null;
                    fileInput.value = '';
                    attachmentPreview.classList.add('d-none');
                    attachmentPreview.innerHTML = '';
                };

                const renderAttachmentPreview = (file) => {
                    const isImage = file.type.startsWith('image/');
                    attachmentPreview.innerHTML =
                        `${isImage ? `<img src="${URL.createObjectURL(file)}" alt="">` : '<i class="ti ti-file-description fs-2 text-primary"></i>'}<span class="file-name text-truncate">${esc(file.name)}<small class="d-block text-secondary">${(file.size / 1024 / 1024).toFixed(2)} MB</small></span><button type="button" class="btn btn-sm btn-outline-secondary" id="school-chat-remove-file" title="Remove attachment"><i class="ti ti-x"></i></button>`;
                    attachmentPreview.classList.remove('d-none');
                    document.getElementById('school-chat-remove-file').addEventListener('click', clearAttachment);
                };

                // Use Unicode escapes so emoji remain valid regardless of the
                // source-file encoding used by the server/editor.
                const emojis = [
                    0x1F600, 0x1F603, 0x1F604, 0x1F601, 0x1F602, 0x1F923,
                    0x1F60A, 0x1F60D, 0x1F970, 0x1F618, 0x1F60E, 0x1F914,
                    0x1F622, 0x1F62D, 0x1F621, 0x1F44D, 0x1F44F, 0x1F64F,
                    0x2764, 0x1F4AF, 0x1F389, 0x2705, 0x2B50, 0x1F525,
                    0x1F4A1, 0x1F4DA, 0x1F4CE, 0x1F609, 0x1F44C, 0x1F44B,
                    0x2728, 0x1F91D,
                ].map((code) => String.fromCodePoint(code));
                emojiGrid.innerHTML = emojis.map((emoji) =>
                    `<button type="button" class="chat-mini-emoji" data-emoji="${emoji}">${emoji}</button>`).join(
                    '');
                emojiGrid.querySelectorAll('[data-emoji]').forEach((button) => button.addEventListener('click', () => {
                    const start = input.selectionStart ?? input.value.length;
                    const end = input.selectionEnd ?? input.value.length;
                    input.value =
                        `${input.value.slice(0, start)}${button.dataset.emoji}${input.value.slice(end)}`;
                    input.focus();
                    input.selectionStart = input.selectionEnd = start + button.dataset.emoji.length;
                }));
                moreActionsButton.addEventListener('click', () => {
                    const isHidden = actionsMenu.classList.toggle('d-none');
                    moreActionsButton.setAttribute('aria-expanded', String(!isHidden));
                    moreActionsButton.querySelector('i').classList.toggle('ti-plus', isHidden);
                    moreActionsButton.querySelector('i').classList.toggle('ti-x', !isHidden);
                });
                emojiButton.addEventListener('click', () => emojiPicker.classList.toggle('d-none'));
                attachButton.addEventListener('click', () => fileInput.click());
                fileInput.addEventListener('change', () => {
                    const file = fileInput.files?.[0];
                    if (!file) return;
                    if (file.size > 20 * 1024 * 1024) {
                        alert('Attachments must be 20 MB or smaller.');
                        clearAttachment();
                        return;
                    }
                    selectedAttachment = file;
                    renderAttachmentPreview(file);
                });

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const message = input.value.trim();
                    if ((!message && !selectedAttachment) || !activeConversationId) return;
                    const formData = new FormData();
                    if (message) formData.append('message', message);
                    if (selectedAttachment) formData.append('attachment', selectedAttachment,
                        selectedAttachment.name);
                    await postForm(`${routes.messagesBase}/${activeConversationId}/messages`, formData);
                    input.value = '';
                    clearAttachment();
                    emojiPicker.classList.add('d-none');

                    await openConversation(activeConversationId);
                    await refreshData();
                });

                callStartButton.addEventListener('click', () => startVoiceCall().catch((error) => alert(error.message ||
                    'Unable to start the voice call.')));
                recordVoiceButton.addEventListener('click', () => toggleVoiceRecording().catch((error) => {
                    console.warn(error?.message || 'Unable to record voice message.');
                    setVoiceButtonIdle();
                }));
                callAcceptButton.addEventListener('click', () => acceptIncomingCall().catch((error) => alert(error
                    .message || 'Unable to accept the call.')));
                callEndButton.addEventListener('click', () => endCurrentCall().catch(() => resetCallState()));
                callMuteButton.addEventListener('click', toggleMute);

                window.setInterval(async () => {
                    if (drawer.classList.contains('d-none')) return;
                    try {
                        const unreadData = await api(routes.unread);
                        renderLauncherBadge(unreadData.unread || 0);
                        const conversationsData = await api(routes.conversations);
                        conversations = conversationsData || [];
                        users = await api(routes.users);

                        if (!activeConversationId) {
                            renderConversations();
                            if (activeTab === 'people') renderPeople();
                        } else {
                            const current = conversations.find((conversation) => conversation.id ===
                                activeConversationId);
                            if (current) {
                                const data = await api(
                                    `${routes.messagesBase}/${activeConversationId}/messages`);
                                const nextMessages = data.messages || [];
                                const previousCount = activeConversation?.messages?.length || 0;
                                const latestChanged = (activeConversation?.messages?.[previousCount - 1]?.id || null) !== (nextMessages[nextMessages.length - 1]?.id || null);
                                const skipRender = isAudioPlaybackActive();
                                activeConversation = data.conversation;
                                activeConversation.messages = nextMessages;
                                if (!skipRender) renderConversationView();
                            }
                        }
                    } catch (error) {
                        // Keep the drawer quiet if the connection blips.
                    }
                }, 10000);

                window.setInterval(() => refreshActiveCall().catch(() => {}), 2000);
                window.setInterval(() => checkIncomingCalls().catch(() => {}), 5000);
                api(routes.heartbeat, { method: 'POST' }).catch(() => {});
                window.setInterval(() => api(routes.heartbeat, {
                    method: 'POST'
                }).catch(() => {}), 60000);
                window.addEventListener('resize', dockLauncher);

                setTab('conversations');
            });
