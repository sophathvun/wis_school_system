@extends('layouts.app')

@section('title', 'Chat')

@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Communication</div>
                <h2 class="page-title">Chat</h2>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" id="new-chat">
                    <i class="ti ti-message-plus icon"></i> New Chat
                </button>
            </div>
        </div>
    </div>
@endsection

@section('content')


    <div class="col-12">
        <div class="card chat-shell d-flex flex-row overflow-hidden" id="chat-shell">
            <div class="chat-conversation-list" id="conversation-list">
                <div class="p-3 border-bottom">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h3 class="card-title mb-0">Conversations</h3>
                        <button type="button" class="btn btn-primary btn-sm" id="new-chat-sidebar"
                            title="Start a chat or group chat">
                            <i class="ti ti-message-plus me-1"></i> New Chat
                        </button>
                    </div>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input id="conversation-search" class="form-control" placeholder="Search conversations">
                    </div>
                </div>
                <div id="conversations">
                    <div class="text-secondary text-center p-4">Loading conversations...</div>
                </div>
            </div>

            <div class="chat-panel flex-fill d-flex flex-column" id="chat-panel">
                <div class="chat-empty" id="chat-empty">
                    <div class="text-center">
                        <i class="ti ti-messages fs-1"></i>
                        <div class="mt-2">Select a conversation to start chatting.</div>
                    </div>
                </div>
                <div class="d-none flex-column h-100" id="chat-content">
                    <div class="card-header d-flex align-items-center gap-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm d-md-none" id="chat-back">
                            <i class="ti ti-arrow-left"></i>
                        </button>
                        <div class="flex-fill min-w-0">
                            <h3 class="card-title mb-0 text-truncate" id="chat-title">Conversation</h3>
                            <div class="text-secondary small text-truncate" id="chat-members"></div>
                        </div>
                    </div>
                    <div class="chat-messages" id="chat-messages"></div>
                    <form class="border-top p-3 position-relative" id="message-form">
                        <div id="chat-attachment-preview" class="chat-attachment-preview d-none"></div>
                        <div id="chat-emoji-picker" class="chat-emoji-picker d-none">
                            <div class="small fw-semibold text-secondary mb-2">Emoji</div>
                            <div class="chat-emoji-grid" id="chat-emoji-grid"></div>
                        </div>
                        <div class="input-group">
                            <div class="chat-composer-actions">
                                <button type="button" id="chat-more-actions" class="btn btn-outline-secondary"
                                    title="More chat options" aria-label="More chat options" aria-expanded="false">
                                    <i class="ti ti-plus"></i>
                                </button>
                                <div id="chat-actions-menu" class="chat-composer-actions-menu d-none">
                                    <button type="button" id="chat-attach" class="btn btn-outline-secondary"
                                        title="Attach a file or photo">
                                        <i class="ti ti-paperclip"></i>
                                    </button>
                                    <button type="button" id="chat-record-voice" class="btn btn-outline-primary"
                                        title="Record voice message">
                                        <i class="ti ti-microphone"></i>
                                    </button>
                                    <button type="button" id="chat-emoji" class="btn btn-outline-secondary"
                                        title="Add emoji">
                                        <i class="ti ti-mood-smile"></i>
                                    </button>
                                </div>
                            </div>
                            <textarea id="message-input" class="form-control" rows="1" placeholder="Type a message..."></textarea>
                            <button class="btn btn-primary" type="submit"><i class="ti ti-send"></i></button>
                        </div>
                        <input type="file" id="chat-file" class="d-none"
                            accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="newChatModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ti ti-messages me-2"></i>Start New Chat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="new-chat-form">
                    <div class="modal-body">
                        <div class="text-secondary small mb-2">
                            <i class="ti ti-info-circle me-1"></i>Select one user for private chat or multiple users for a
                            group chat.
                        </div>
                        <input id="user-search" class="form-control mb-3" placeholder="Search staff">
                        <div id="chat-user-list" class="vstack gap-2" style="max-height:320px;overflow:auto"></div>
                        <input id="group-title" class="form-control mt-3 d-none" placeholder="Group name (optional)">
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary"><i class="ti ti-message-plus me-1"></i>Start
                            Chat</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@php
    $currentChatUserId = auth()->id();
    $currentChatUserPhoto = auth()->user()->photo_path ? asset('storage/' . auth()->user()->photo_path) : null;
@endphp

@vite('resources/css/pages/chat.css')
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const routes = {
                users: @json(route('chat.users')),
                conversations: @json(route('chat.conversations')),
                create: @json(route('chat.create')),
                heartbeat: @json(route('chat.heartbeat')),
                messagesBase: @json(url('/communication/chat')),
            };
            const currentUserId = @json($currentChatUserId);
            const currentUserPhoto = @json($currentChatUserPhoto);
            const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            } [ch]));

            const shell = document.getElementById('chat-shell');
            const conversationsBox = document.getElementById('conversations');
            const userList = document.getElementById('chat-user-list');
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('newChatModal'));
            const chatEmpty = document.getElementById('chat-empty');
            const chatContent = document.getElementById('chat-content');
            const chatMessages = document.getElementById('chat-messages');
            const messageInput = document.getElementById('message-input');
            const fileInput = document.getElementById('chat-file');
            const attachmentPreview = document.getElementById('chat-attachment-preview');
            const emojiPicker = document.getElementById('chat-emoji-picker');
            const emojiGrid = document.getElementById('chat-emoji-grid');
            const recordVoiceButton = document.getElementById('chat-record-voice');
            const moreActionsButton = document.getElementById('chat-more-actions');
            const actionsMenu = document.getElementById('chat-actions-menu');
            let chats = [];
            let users = [];
            let activeId = null;
            let activeConversation = null;
            let selectedAttachment = null;
            let mediaRecorder = null;
            let voiceChunks = [];
            let voiceStartedAt = null;
            let voiceTimer = null;
            let voiceMimeType = 'audio/webm';

            const preferredVoiceType = () => {
                const types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/ogg',
                    'audio/mp4'
                ];
                return types.find(type => MediaRecorder.isTypeSupported?.(type)) || '';
            };

            const voiceExtension = (mimeType) => {
                if (mimeType.includes('ogg')) return 'ogg';
                if (mimeType.includes('mp4')) return 'm4a';
                return 'webm';
            };

            const setVoiceButtonIdle = () => {
                window.clearInterval(voiceTimer);
                voiceTimer = null;
                recordVoiceButton.classList.remove('chat-recording', 'chat-sending-voice');
                recordVoiceButton.innerHTML = '<i class="ti ti-microphone"></i>';
                recordVoiceButton.title = 'Record voice message';
            };

            const setVoiceButtonRecording = () => {
                recordVoiceButton.classList.add('chat-recording');
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
                recordVoiceButton.classList.remove('chat-recording');
                recordVoiceButton.classList.add('chat-sending-voice');
                recordVoiceButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                recordVoiceButton.title = 'Sending voice message';
            };

            async function api(url, options = {}) {
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
                if (!response.ok) throw new Error(data.message || 'Unable to complete the request.');
                return data;
            }

            async function postForm(url, formData) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: formData,
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data.message || 'Unable to upload the file.');
                return data;
            }

            function avatar(user, className = 'chat-avatar') {
                const status = user?.online !== undefined ?
                    `<span class="chat-online-dot ${user.online ? 'online' : ''}" title="${user.online ? 'Online' : 'Offline'}"></span>` :
                    '';
                if (user?.photo)
                return `<span class="${className}" title="${esc(user.name || '')}"><img src="${esc(user.photo)}" alt="${esc(user.name || 'Staff')}">${status}</span>`;
                return `<span class="${className}" title="${esc(user?.name || '')}"><i class="ti ti-user"></i>${status}</span>`;
            }

            function renderUsers(term = '') {
                const q = term.toLowerCase();
                const selected = Array.from(userList.querySelectorAll('input:checked')).map(input => input.value);
                const filtered = users.filter(user => `${user.name} ${user.department || ''}`.toLowerCase()
                    .includes(q));
                userList.innerHTML = filtered.map(user => `
            <label class="form-check border rounded p-2 d-flex align-items-center gap-2">
                <input class="form-check-input m-0" type="checkbox" value="${user.id}" ${selected.includes(String(user.id)) ? 'checked' : ''}>
                ${avatar(user)}
                <span class="form-check-label flex-fill min-w-0">
                    <span class="fw-semibold d-block text-truncate">${esc(user.name)}</span>
                    <small class="text-secondary">${esc(user.department || 'Staff')} &middot; ${user.online ? 'Online' : 'Offline'}</small>
                </span>
            </label>
        `).join('') || '<div class="text-secondary">No users found.</div>';
                document.getElementById('group-title').classList.toggle('d-none', selected.length < 2);
            }

            function renderChats() {
                const term = document.getElementById('conversation-search').value.toLowerCase();
                conversationsBox.innerHTML = chats
                    .filter(chat => `${chat.title} ${chat.last_message || ''}`.toLowerCase().includes(term))
                    .map(chat => `
                <div class="chat-conversation-item p-3 ${chat.id === activeId ? 'active' : ''}" data-id="${chat.id}">
                    <div class="d-flex align-items-center gap-3">
                        ${avatar({ name: chat.title, photo: chat.photo, online: chat.online }, 'chat-avatar')}
                        <div class="flex-fill text-truncate">
                            <div class="fw-bold text-truncate">${esc(chat.title)}</div>
                            <div class="small text-secondary text-truncate">${chat.type === 'direct' ? (chat.online ? 'Online' : 'Offline') + ' &middot; ' : ''}${esc(chat.last_message || 'No messages yet')}</div>
                        </div>
                        ${chat.unread_messages ? `<span class="badge bg-primary rounded-pill">${chat.unread_messages > 99 ? '99+' : chat.unread_messages}</span>` : ''}
                    </div>
                </div>
            `).join('') || '<div class="text-secondary text-center p-4">No conversations yet.</div>';
                conversationsBox.querySelectorAll('[data-id]').forEach(item => item.onclick = () => openChat(Number(
                    item.dataset.id)));
            }

            function messageContent(message) {
                const text = message.message && !(message.message_type !== 'text' && message.message === message
                        .media_name) ?
                    `<div class="chat-message-text">${esc(message.message)}</div>` : '';
                const downloadUrl = message.media_download_url || message.media_url;
                if (message.message_type === 'voice' && message.media_url) {
                    return `<div class="fw-semibold mb-1"><i class="ti ti-wave-sine me-1"></i>Voice message</div><audio controls src="${esc(message.media_url)}"></audio>`;
                }
                if (message.message_type === 'image' && message.media_url) {
                    return `<a href="${esc(message.media_url)}" target="_blank" rel="noopener"><img src="${esc(message.media_url)}" alt="${esc(message.media_name || 'Attached image')}" class="chat-image"></a><div class="chat-attachment-actions"><a href="${esc(message.media_url)}" target="_blank" rel="noopener"><i class="ti ti-eye"></i> View</a><a href="${esc(downloadUrl)}"><i class="ti ti-download"></i> Download</a></div>${text}`;
                }
                if (message.message_type === 'file' && message.media_url) {
                    return `<div class="chat-file-card"><i class="ti ti-file-description fs-3"></i><a href="${esc(message.media_url)}" target="_blank" rel="noopener"><span class="file-name">${esc(message.media_name || 'Attached file')}</span><small>Open attachment</small></a><a class="chat-file-download" href="${esc(downloadUrl)}" title="Download attachment"><i class="ti ti-download"></i></a></div>${text}`;
                }
                return text;
            }

            function messageStatus(message) {
                if (message.user_id !== currentUserId)
                return '<div class="chat-message-meta"><i class="ti ti-check"></i> Read</div>';
                const read = (message.read_by || []).length > 0;
                return `<div class="chat-message-meta ${read ? 'read' : 'unread'}"><i class="ti ti-${read ? 'checks' : 'check'}"></i>${read ? 'Read' : 'Unread'}</div>`;
            }

            function detailRows(items, emptyText, icon) {
                if (!items.length)
                return `<div class="chat-message-detail-row text-secondary"><i class="ti ${icon}"></i><span>${emptyText}</span></div>`;
                return items.map(user => `
            <div class="chat-message-detail-row">
                ${avatar(user, 'chat-read-avatar')}
                <span class="flex-fill">${esc(user.name)}</span>
                ${user.read_at ? `<span class="text-secondary">${esc(user.read_at)}</span>` : ''}
            </div>
        `).join('');
            }

            function messageDetail(message) {
                const isMine = message.user_id === currentUserId;
                return `
            <div class="chat-message-detail ${isMine ? 'mine' : ''}" data-message-detail="${message.id}">
                <div class="chat-message-detail-title">${isMine ? 'Read by' : 'Message info'}</div>
                ${isMine ? detailRows(message.read_by || [], 'Not read yet', 'ti-eye-off') : '<div class="chat-message-detail-row text-secondary"><i class="ti ti-eye"></i><span>You have read this message.</span></div>'}
                ${isMine ? `<div class="chat-message-detail-title">Unread</div>${detailRows(message.unread_by || [], 'Everyone has read this message', 'ti-checks')}` : ''}
            </div>
        `;
            }

            function readReceipts(message) {
                if (message.user_id !== currentUserId) return '';
                const readBy = message.read_by || [];
                if (!readBy.length)
                return '<div class="chat-read-receipts"><span class="small text-secondary"><i class="ti ti-checks"></i> Sent</span></div>';
                return `<div class="chat-read-receipts">${readBy.slice(0, 5).map(user => avatar(user, 'chat-read-avatar')).join('')}</div>`;
            }

            function renderMessages() {
                if (!activeConversation) return;
                document.getElementById('chat-title').textContent = activeConversation.title || 'Conversation';
                document.getElementById('chat-members').textContent = (activeConversation.users || [])
                    .filter(user => user.id !== currentUserId)
                    .map(user => `${user.name} - ${user.online ? 'Online' : 'Offline'}`)
                    .join(', ');
                chatMessages.innerHTML = (activeConversation.messages || []).map(message => `
            <div class="chat-row ${message.user_id === currentUserId ? 'mine' : ''}">
                ${message.user_id === currentUserId ? '' : avatar({ name: message.user_name, photo: message.user_photo, online: message.user_online })}
                <div class="chat-message ${message.user_id === currentUserId ? 'mine' : ''}" data-message-id="${message.id}">
                    <div class="small opacity-75 mb-1">${esc(message.user_name)} &middot; ${esc(message.created_at)}</div>
                    ${messageContent(message)}
                    ${messageStatus(message)}
                </div>
                ${message.user_id === currentUserId ? avatar({ name: 'You', photo: currentUserPhoto, online: true }) : ''}
            </div>
            ${messageDetail(message)}
            ${readReceipts(message)}
        `).join('') || '<div class="text-secondary text-center mt-4">No messages yet. Start the conversation.</div>';
                chatMessages.querySelectorAll('[data-message-id]').forEach(bubble => {
                    bubble.addEventListener('click', event => {
                        if (event.target.closest('a, audio')) return;
                        const detail = chatMessages.querySelector(
                            `[data-message-detail="${bubble.dataset.messageId}"]`);
                        const wasOpen = detail?.classList.contains('show');
                        chatMessages.querySelectorAll('.chat-message-detail.show').forEach(item =>
                            item.classList.remove('show'));
                        if (detail && !wasOpen) detail.classList.add('show');
                    });
                });
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            async function loadUsers() {
                users = await api(routes.users);
                renderUsers(document.getElementById('user-search').value);
            }

            async function loadChats() {
                chats = await api(routes.conversations);
                renderChats();
            }

            async function openChat(id) {
                activeId = id;
                renderChats();
                const data = await api(`${routes.messagesBase}/${id}/messages`);
                activeConversation = data.conversation;
                activeConversation.messages = data.messages || [];
                chatEmpty.classList.add('d-none');
                chatContent.classList.remove('d-none');
                chatContent.classList.add('d-flex');
                shell.classList.add('has-conversation');
                renderMessages();
            }

            function clearAttachment() {
                selectedAttachment = null;
                fileInput.value = '';
                attachmentPreview.classList.add('d-none');
                attachmentPreview.innerHTML = '';
            }

            function renderAttachmentPreview(file) {
                const isImage = file.type.startsWith('image/');
                attachmentPreview.innerHTML =
                    `${isImage ? `<img src="${URL.createObjectURL(file)}" alt="">` : '<i class="ti ti-file-description fs-2 text-primary"></i>'}<span class="file-name text-truncate">${esc(file.name)}<small class="d-block text-secondary">${(file.size / 1024 / 1024).toFixed(2)} MB</small></span><button type="button" class="btn btn-sm btn-outline-secondary" id="chat-remove-file" title="Remove attachment"><i class="ti ti-x"></i></button>`;
                attachmentPreview.classList.remove('d-none');
                document.getElementById('chat-remove-file').addEventListener('click', clearAttachment);
            }

            async function uploadVoiceNote(blob, durationSeconds, mimeType = 'audio/webm') {
                if (!activeId) return;
                const formData = new FormData();
                formData.append('audio', blob, `voice-note-${Date.now()}.${voiceExtension(mimeType)}`);
                formData.append('duration_seconds', Math.max(1, Math.round(durationSeconds || 1)));
                await postForm(`${routes.messagesBase}/${activeId}/voice`, formData);
                await loadChats();
                await openChat(activeId);
            }

            async function toggleVoiceRecording() {
                if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
                    alert('Your browser does not support voice recording.');
                    return;
                }
                if (mediaRecorder && mediaRecorder.state === 'recording') {
                    mediaRecorder.stop();
                    return;
                }
                if (!activeId) return;
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
                mediaRecorder.ondataavailable = event => {
                    if (event.data?.size) voiceChunks.push(event.data);
                };
                mediaRecorder.onstop = async () => {
                    setVoiceButtonSending();
                    try {
                        stream.getTracks().forEach(track => track.stop());
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
            }

            const emojis = ['😀', '😃', '😄', '😁', '😂', '🤣', '😊', '😍', '🥰', '😘', '😎', '🤔', '😢', '😭',
                '😡', '👍', '👏', '🙏', '❤️', '💯', '🎉', '✅', '⭐', '🔥', '💡', '📚', '📎', '🙌', '👋', '✨',
                '🤝', '🙂'
            ];
            emojiGrid.innerHTML = emojis.map(emoji =>
                `<button type="button" class="chat-emoji" data-emoji="${emoji}">${emoji}</button>`).join('');
            emojiGrid.querySelectorAll('[data-emoji]').forEach(button => button.addEventListener('click', () => {
                const start = messageInput.selectionStart ?? messageInput.value.length;
                const end = messageInput.selectionEnd ?? messageInput.value.length;
                messageInput.value =
                    `${messageInput.value.slice(0, start)}${button.dataset.emoji}${messageInput.value.slice(end)}`;
                messageInput.focus();
                messageInput.selectionStart = messageInput.selectionEnd = start + button.dataset.emoji
                    .length;
            }));

            document.getElementById('new-chat')?.addEventListener('click', async () => {
                await loadUsers();
                modal.show();
            });
            document.getElementById('new-chat-sidebar')?.addEventListener('click', async () => {
                await loadUsers();
                modal.show();
            });
            document.getElementById('chat-back').addEventListener('click', () => {
                shell.classList.remove('has-conversation');
                activeId = null;
                activeConversation = null;
                chatContent.classList.add('d-none');
                chatContent.classList.remove('d-flex');
                chatEmpty.classList.remove('d-none');
                renderChats();
            });
            document.getElementById('conversation-search').addEventListener('input', renderChats);
            document.getElementById('user-search').addEventListener('input', event => renderUsers(event.target
                .value));
            userList.addEventListener('change', () => renderUsers(document.getElementById('user-search').value));
            moreActionsButton.addEventListener('click', () => {
                const isHidden = actionsMenu.classList.toggle('d-none');
                moreActionsButton.setAttribute('aria-expanded', String(!isHidden));
                moreActionsButton.querySelector('i').classList.toggle('ti-plus', isHidden);
                moreActionsButton.querySelector('i').classList.toggle('ti-x', !isHidden);
            });
            document.getElementById('chat-emoji').addEventListener('click', () => emojiPicker.classList.toggle(
                'd-none'));
            document.getElementById('chat-attach').addEventListener('click', () => fileInput.click());
            recordVoiceButton.addEventListener('click', () => toggleVoiceRecording().catch(error => alert(error
                .message || 'Unable to record voice message.')));
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

            document.getElementById('message-form').addEventListener('submit', async event => {
                event.preventDefault();
                const message = messageInput.value.trim();
                if ((!message && !selectedAttachment) || !activeId) return;
                const formData = new FormData();
                if (message) formData.append('message', message);
                if (selectedAttachment) formData.append('attachment', selectedAttachment,
                    selectedAttachment.name);
                await postForm(`${routes.messagesBase}/${activeId}/messages`, formData);
                messageInput.value = '';
                clearAttachment();
                emojiPicker.classList.add('d-none');
                await loadChats();
                await openChat(activeId);
            });

            document.getElementById('new-chat-form').addEventListener('submit', async event => {
                event.preventDefault();
                const ids = Array.from(userList.querySelectorAll('input:checked')).map(input => Number(
                    input.value));
                if (!ids.length) return;
                const data = await api(routes.create, {
                    method: 'POST',
                    body: JSON.stringify({
                        user_ids: ids,
                        title: document.getElementById('group-title').value
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
                    if (activeId) await openChat(activeId);
                } catch (error) {}
            }, 10000);
            window.setInterval(() => api(routes.heartbeat, {
                method: 'POST'
            }).catch(() => {}), 60000);

            loadUsers().catch(() => {});
            loadChats().catch(() => {});
        });
    </script>
@endpush
