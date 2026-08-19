@if(auth()->check() && !request()->routeIs('chat.index'))
<style>
    .school-chat-launcher {
        position: fixed;
        right: 1.25rem;
        bottom: 1.75rem;
        z-index: 2147483646;
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 999px;
        box-shadow: 0 16px 34px rgba(91, 75, 202, .28);
        touch-action: none;
        user-select: none;
        cursor: grab;
    }

    .school-chat-launcher.dragging {
        cursor: grabbing;
        transition: none !important;
    }

    .school-chat-launcher .badge {
        min-width: 1.15rem;
        height: 1.15rem;
        font-size: .68rem;
        line-height: 1.15rem;
        padding: 0 .18rem;
    }

    .school-chat-drawer {
        position: fixed;
        right: 1.25rem;
        bottom: 6rem;
        z-index: 2147483645;
        width: 380px;
        max-width: calc(100vw - 2rem);
        height: 620px;
        max-height: calc(100vh - 8rem);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid rgba(91, 75, 202, .18);
        border-radius: 1.1rem;
        background: var(--tblr-body-bg);
        box-shadow: 0 24px 60px rgba(27, 46, 76, .22);
    }

    .school-chat-drawer.d-none {
        display: none !important;
    }

    .school-chat-drawer .nav-tabs .nav-link {
        border: 0;
        border-bottom: 2px solid transparent;
        margin-bottom: 0;
    }

    .school-chat-drawer .nav-tabs .nav-link.active {
        color: var(--tblr-primary);
        border-bottom-color: var(--tblr-primary);
        background: transparent;
    }

    .school-chat-drawer .chat-mini-list {
        overflow: auto;
        flex: 1 1 auto;
        min-height: 0;
    }

    .school-chat-drawer #school-chat-conversation-pane {
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
    }

    .school-chat-drawer .chat-mini-item {
        cursor: pointer;
        transition: background-color .15s ease, transform .15s ease;
    }

    .school-chat-drawer .chat-mini-item:hover {
        background: var(--tblr-bg-surface-secondary);
    }

    .school-chat-drawer .chat-mini-item.active {
        background: var(--tblr-primary-lt);
    }

    .school-chat-drawer .chat-mini-message {
        max-width: 78%;
        padding: .65rem .8rem;
        border-radius: 1rem 1rem 1rem .25rem;
        background: var(--tblr-bg-surface-secondary);
        word-wrap: break-word;
        white-space: normal;
        line-height: 1.35;
        width: fit-content;
        min-height: 0;
    }

    .school-chat-drawer .chat-mini-message.mine {
        margin-left: auto;
        border-radius: 1rem 1rem .25rem 1rem;
        background: var(--tblr-primary);
        color: #fff;
    }

    .chat-mini-row {
        display: flex;
        align-items: flex-end;
        gap: .45rem;
        margin-bottom: .2rem;
    }

    .chat-mini-row.mine {
        justify-content: flex-end;
    }

    .chat-mini-row .chat-mini-message-avatar {
        position: relative;
        width: 1.85rem;
        height: 1.85rem;
        flex: 0 0 auto;
        border-radius: 15%;
        background-color: var(--tblr-primary-lt);
        background-position: center;
        background-size: cover;
        color: var(--tblr-primary);
        font-size: .72rem;
        font-weight: 700;
        display: grid;
        place-items: center;
    }

    .chat-mini-photo-wrap {
        position: relative;
        display: grid;
        place-items: center;
    }

    .chat-mini-presence-dot {
        position: absolute;
        right: -1px;
        bottom: -1px;
        width: .62rem;
        height: .62rem;
        border: 2px solid var(--tblr-body-bg);
        border-radius: 50%;
        background: #adb5bd;
    }

    .chat-mini-presence-dot.online {
        background: #2fb344;
    }

    .chat-mini-read-receipts {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: .15rem;
        min-height: 1.05rem;
        margin: -.1rem .25rem .45rem 0;
    }

    .chat-mini-read-avatar {
        width: 1rem;
        height: 1rem;
        border-radius: 15%;
        display: grid;
        place-items: center;
        overflow: hidden;
        border: 1px solid var(--tblr-body-bg);
        background-color: var(--tblr-primary-lt);
        background-position: center;
        background-size: cover;
        color: var(--tblr-primary);
        font-size: .48rem;
        font-weight: 700;
        line-height: 1;
    }

    .chat-mini-photo {
        width: 100%;
        height: 100%;
        border-radius: 15%;
        object-fit: cover;
        display: block;
    }

    .chat-mini-message-meta {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .3rem;
        margin-top: .25rem;
        font-size: .66rem;
        opacity: .72;
        white-space: nowrap;
    }

    .chat-mini-message-status.read { color: #2fb344; }
    .chat-mini-message-status.unread { color: var(--tblr-secondary); }

    .chat-mini-delivered {
        color: var(--tblr-secondary);
        font-size: .7rem;
    }

    .chat-mini-message {
        cursor: pointer;
    }

    .chat-mini-message-text {
        white-space: pre-wrap;
    }

    .chat-mini-message-detail {
        display: none;
        width: min(82%, 270px);
        margin: .15rem 0 .6rem 2.3rem;
        padding: .55rem .7rem;
        border: 1px solid rgba(91, 75, 202, .13);
        border-radius: .85rem;
        background: var(--tblr-bg-surface);
        box-shadow: 0 8px 24px rgba(27, 46, 76, .08);
    }

    .chat-mini-message-detail.mine {
        margin-left: auto;
        margin-right: .25rem;
    }

    .chat-mini-message-detail.show {
        display: block;
    }

    .chat-mini-message-detail-row {
        display: flex;
        align-items: center;
        gap: .45rem;
        padding: .22rem 0;
        font-size: .78rem;
    }

    .chat-mini-message-detail-title {
        margin-top: .15rem;
        color: var(--tblr-secondary);
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .035em;
    }

    .school-chat-drawer .chat-mini-messages {
        flex: 1 1 auto;
        overflow: auto;
        min-height: 0;
        overscroll-behavior: contain;
        background: linear-gradient(180deg, rgba(91, 75, 202, .03), rgba(91, 75, 202, .01));
    }

    .school-chat-drawer .chat-mini-status {
        width: .75rem;
        height: .75rem;
        border-radius: 999px;
        background: #adb5bd;
        border: 2px solid var(--tblr-body-bg);
        box-shadow: 0 0 0 1px rgba(0,0,0,.04);
    }

    .school-chat-drawer .chat-mini-status.online {
        background: #2fb344;
    }

    .school-chat-drawer .chat-mini-avatar {
        width: 2.25rem;
        height: 2.25rem;
        flex: 0 0 auto;
        border-radius: 15%;
        overflow: visible;
    }

    .school-chat-drawer .chat-mini-empty {
        display: grid;
        place-items: center;
        min-height: 220px;
        color: var(--tblr-secondary);
        text-align: center;
        padding: 1rem;
    }

    .school-chat-call-panel {
        position: fixed;
        right: 1.25rem;
        bottom: 6rem;
        z-index: 2147483647;
        width: 380px;
        max-width: calc(100vw - 2rem);
        border-radius: 1.1rem;
        background: var(--tblr-body-bg);
        border: 1px solid rgba(91, 75, 202, .2);
        box-shadow: 0 24px 60px rgba(27, 46, 76, .26);
        overflow: hidden;
    }

    .school-chat-call-glow {
        width: 4.25rem;
        height: 4.25rem;
        border-radius: 999px;
        display: grid;
        place-items: center;
        margin: 0 auto;
        color: #fff;
        background: linear-gradient(135deg, var(--tblr-primary), #7c5cff);
        box-shadow: 0 0 0 .8rem rgba(91, 75, 202, .08);
    }

    .school-chat-recording {
        color: #d63939 !important;
        border-color: #d63939 !important;
        animation: school-chat-pulse 1.15s infinite;
    }

    .school-chat-sending-voice {
        opacity: .72;
        pointer-events: none;
    }

    .school-chat-drawer audio {
        width: 230px;
        max-width: 100%;
        height: 34px;
    }

    .chat-mini-composer-tools { display: flex; gap: .35rem; align-items: center; }
    .chat-mini-emoji-picker {
        position: absolute; right: 1rem; bottom: 4.7rem; z-index: 3; width: 260px;
        padding: .65rem; border: 1px solid rgba(91,75,202,.18); border-radius: .9rem;
        background: var(--tblr-body-bg); box-shadow: 0 14px 35px rgba(27,46,76,.2);
    }
    .chat-mini-emoji-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: .2rem; }
    .chat-mini-emoji { border: 0; background: transparent; border-radius: .45rem; padding: .35rem; font-size: 1.25rem; }
    .chat-mini-emoji:hover { background: var(--tblr-primary-lt); }
    .chat-mini-attachment-preview { display: flex; align-items: center; gap: .55rem; padding: .5rem .75rem; border: 1px solid rgba(91,75,202,.16); border-radius: .7rem; margin-bottom: .55rem; background: var(--tblr-bg-surface-secondary); }
    .chat-mini-attachment-preview img { width: 2.5rem; height: 2.5rem; object-fit: cover; border-radius: .45rem; }
    .chat-mini-attachment-preview .file-name { min-width: 0; flex: 1; font-size: .8rem; }
    .chat-mini-message-image { display: block; max-width: 220px; max-height: 260px; border-radius: .7rem; object-fit: contain; margin-bottom: .35rem; }
    .chat-mini-attachment-actions { display: flex; gap: .7rem; margin: .1rem 0 .25rem; font-size: .72rem; }
    .chat-mini-attachment-actions a, .chat-mini-file-download { color: var(--tblr-primary); text-decoration: none; }
    .chat-mini-message.mine .chat-mini-attachment-actions a, .chat-mini-message.mine .chat-mini-file-download { color: inherit; }
    .chat-mini-file-card { display: flex; align-items: center; gap: .55rem; padding: .55rem .65rem; border-radius: .65rem; background: rgba(255,255,255,.18); }
    .chat-mini-file-card a { color: inherit; text-decoration: none; min-width: 0; }
    .chat-mini-file-card .file-name { display: block; max-width: 190px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .chat-mini-file-download { flex: 0 0 auto; font-size: 1.1rem; }

    @keyframes school-chat-pulse {
        0% { box-shadow: 0 0 0 0 rgba(214, 57, 57, .32); }
        100% { box-shadow: 0 0 0 .55rem rgba(214, 57, 57, 0); }
    }

    @media (max-width: 767px) {
        .school-chat-drawer {
            right: .75rem;
            left: .75rem;
            bottom: 5.75rem;
            width: auto;
            height: min(72vh, 640px);
        }

        .school-chat-call-panel {
            right: .75rem;
            left: .75rem;
            width: auto;
        }

    }
</style>

<div id="school-chat-widget" class="school-chat-widget">
    <button type="button" id="school-chat-launcher" class="btn btn-primary school-chat-launcher position-relative" aria-label="Open chat" aria-expanded="false" style="position:fixed;right:1.25rem;bottom:1.75rem;left:auto;top:auto;z-index:2147483646;">
        <i class="ti ti-messages fs-3"></i>
        <span id="school-chat-unread-badge" class="badge bg-red position-absolute top-0 start-100 translate-middle d-none">0</span>
    </button>

    <div id="school-chat-drawer" class="school-chat-drawer d-none">
        <div class="card-header py-3 px-4">
            <div class="d-flex align-items-center gap-3 w-100">
                <div class="avatar avatar-md bg-primary-lt text-primary">
                    <i class="ti ti-messages fs-4"></i>
                </div>
                <div class="flex-fill min-w-0">
                    <div class="fw-bold lh-1">Messages</div>
                    <div class="small text-secondary">Chat with online and offline staff</div>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('chat.index') }}" class="btn btn-outline-primary btn-sm">Open full page</a>
                    <button type="button" id="school-chat-minimize" class="btn btn-outline-secondary btn-sm" aria-label="Minimize chat">
                        <i class="ti ti-minus"></i>
                    </button>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs px-3 pt-2">
            <li class="nav-item">
                <button type="button" class="nav-link active" data-school-chat-tab="conversations">Chats</button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-school-chat-tab="people">People</button>
            </li>
        </ul>

        <div class="p-3 border-bottom">
            <div class="input-icon">
                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                <input type="search" id="school-chat-search" class="form-control" placeholder="Search chats or staff">
            </div>
        </div>

        <div class="flex-fill d-flex flex-column overflow-hidden">
            <div id="school-chat-conversations-pane" class="chat-mini-list d-flex flex-column"></div>

            <div id="school-chat-people-pane" class="chat-mini-list d-none flex-column"></div>

            <div id="school-chat-conversation-pane" class="d-none flex-column h-100">
                <div class="border-bottom p-3 d-flex align-items-center gap-3">
                    <button type="button" id="school-chat-back" class="btn btn-outline-secondary btn-sm">
                        <i class="ti ti-arrow-left"></i>
                    </button>
                    <div class="flex-fill min-w-0">
                        <div class="fw-semibold text-truncate" id="school-chat-conversation-title">Conversation</div>
                        <div class="small text-secondary text-truncate" id="school-chat-conversation-members"></div>
                    </div>
                    <button type="button" id="school-chat-call-start" class="btn btn-outline-success btn-sm" title="Start voice call">
                        <i class="ti ti-phone"></i>
                    </button>
                </div>

                <div id="school-chat-messages" class="chat-mini-messages p-3"></div>

                <form id="school-chat-form" class="border-top p-3 position-relative">
                    <div id="school-chat-attachment-preview" class="chat-mini-attachment-preview d-none"></div>
                    <div id="school-chat-emoji-picker" class="chat-mini-emoji-picker d-none">
                        <div class="small fw-semibold text-secondary mb-2">Emoji</div>
                        <div class="chat-mini-emoji-grid" id="school-chat-emoji-grid"></div>
                    </div>
                    <div class="input-group">
                        <button type="button" id="school-chat-attach" class="btn btn-outline-secondary" title="Attach a file or photo">
                            <i class="ti ti-paperclip"></i>
                        </button>
                        <button type="button" id="school-chat-record-voice" class="btn btn-outline-primary" title="Record voice message">
                            <i class="ti ti-microphone"></i>
                        </button>
                        <button type="button" id="school-chat-emoji" class="btn btn-outline-secondary" title="Add emoji">
                            <i class="ti ti-mood-smile"></i>
                        </button>
                        <textarea id="school-chat-input" class="form-control" rows="1" placeholder="Type a message..."></textarea>
                        <button class="btn btn-primary" type="submit"><i class="ti ti-send"></i></button>
                    </div>
                    <input type="file" id="school-chat-file" class="d-none" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                </form>
            </div>
        </div>
    </div>

    <div id="school-chat-call-panel" class="school-chat-call-panel d-none">
        <div class="p-4 text-center">
            <div class="school-chat-call-glow mb-3">
                <i class="ti ti-phone fs-1"></i>
            </div>
            <h3 class="mb-1" id="school-chat-call-title">Voice Call</h3>
            <div class="text-secondary mb-3" id="school-chat-call-status">Calling...</div>
            <div class="d-flex justify-content-center gap-2">
                <button type="button" id="school-chat-call-accept" class="btn btn-success d-none">
                    <i class="ti ti-phone-call me-1"></i> Accept
                </button>
                <button type="button" id="school-chat-call-mute" class="btn btn-outline-secondary d-none">
                    <i class="ti ti-microphone me-1"></i> Mute
                </button>
                <button type="button" id="school-chat-call-end" class="btn btn-danger">
                    <i class="ti ti-phone-off me-1"></i> End
                </button>
            </div>
            <div class="small text-secondary mt-3">Use HTTPS or localhost for browser microphone access.</div>
        </div>
    </div>

    <audio id="school-chat-remote-audio" autoplay playsinline></audio>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const routes = {
        users: @json(route('chat.users')),
        conversations: @json(route('chat.conversations')),
        unread: @json(route('chat.unread')),
        create: @json(route('chat.create')),
        heartbeat: @json(route('chat.heartbeat')),
        messagesBase: @json(url('/communication/chat')),
        callsPending: @json(route('chat.calls.pending')),
        callsBase: @json(url('/communication/chat/calls')),
    };

    const widget = document.getElementById('school-chat-widget');
    if (!widget) return;

    const launcher = document.getElementById('school-chat-launcher');
    const drawer = document.getElementById('school-chat-drawer');
    const minimize = document.getElementById('school-chat-minimize');
    const unreadBadge = document.getElementById('school-chat-unread-badge');
    const search = document.getElementById('school-chat-search');
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
    const currentUserId = @json(auth()->id());
    const currentUserPhoto = @json(auth()->user()->photo_path ? asset('storage/'.auth()->user()->photo_path) : null);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
    let conversations = [];
    let users = [];
    let activeConversationId = null;
    let activeTab = 'conversations';
    let activeConversation = null;
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
    let launcherDrag = null;
    let suppressLauncherClick = false;
    let selectedAttachment = null;
    let voiceMimeType = 'audio/webm';

    const preferredVoiceType = () => {
        const types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/ogg', 'audio/mp4'];
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
            recordVoiceButton.innerHTML = `<i class="ti ti-player-stop"></i><span class="ms-1">${seconds}s</span>`;
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
        tabButtons.forEach((button) => button.classList.toggle('active', button.dataset.schoolChatTab === tab));
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
            const content = conversation.photo
                ? `<img src="${esc(conversation.photo)}" alt="${esc(conversation.title || 'Staff')}" class="chat-mini-photo">`
                : `<i class="ti ti-${conversation.type === 'group' ? 'users' : 'user'}"></i>`;
            const statusDot = conversation.type === 'direct'
                ? `<span class="chat-mini-presence-dot ${conversation.online ? 'online' : ''}" title="${conversation.online ? 'Online' : 'Offline'}"></span>`
                : '';

            return `<span class="chat-mini-photo-wrap w-100 h-100">${content}${statusDot}</span>`;
        };
        const html = conversations
            .filter((conversation) => {
                const text = `${conversation.title} ${conversation.last_message || ''}`.toLowerCase();
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
                            <div class="small text-secondary text-truncate">${conversation.type === 'direct' ? (conversation.online ? 'Online' : 'Offline') + ' · ' : ''}${esc(conversation.last_message || 'No messages yet')}</div>
                        </div>
                        ${conversation.unread_messages ? `<span class="badge bg-primary">${conversation.unread_messages}</span>` : ''}
                    </div>
                </div>
            `).join('');

        conversationsPane.innerHTML = html || '<div class="chat-mini-empty"><div><i class="ti ti-messages fs-1"></i><div class="mt-2">No conversations yet.</div></div></div>';
        conversationsPane.querySelectorAll('[data-conversation-id]').forEach((item) => {
            item.addEventListener('click', () => openConversation(Number(item.dataset.conversationId)));
        });
    };

    const renderPeople = () => {
        const term = search.value.trim().toLowerCase();
        const html = users
            .filter((user) => `${user.name} ${user.department || ''}`.toLowerCase().includes(term))
            .map((user) => `
                <div class="chat-mini-item px-3 py-3 border-bottom" data-user-id="${user.id}">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar avatar-sm chat-mini-avatar ${user.photo ? '' : 'bg-primary-lt text-primary'}">
                            ${user.photo ? `<img src="${esc(user.photo)}" alt="${esc(user.name)}" class="chat-mini-photo">` : '<i class="ti ti-user"></i>'}
                        </div>
                        <div class="flex-fill min-w-0">
                            <div class="d-flex align-items-center gap-2">
                                <div class="fw-semibold text-truncate">${esc(user.name)}</div>
                                <span class="chat-mini-status ${user.online ? 'online' : ''}" title="${user.online ? 'Online' : 'Offline'}"></span>
                            </div>
                            <div class="small text-secondary text-truncate">${esc(user.department || 'Staff')} &middot; ${user.online ? 'Online now' : 'Offline'}</div>
                        </div>
                        ${user.unread_messages ? `<span class="badge bg-primary rounded-pill">${user.unread_messages > 99 ? '99+' : user.unread_messages}</span>` : ''}
                        <button type="button" class="btn btn-outline-primary btn-sm" data-user-chat="${user.id}">Chat</button>
                    </div>
                </div>
            `).join('');

        peoplePane.innerHTML = html || '<div class="chat-mini-empty"><div><i class="ti ti-user-search fs-1"></i><div class="mt-2">No staff found.</div></div></div>';
        peoplePane.querySelectorAll('[data-user-id]').forEach((item) => {
            item.addEventListener('click', (event) => {
                if (event.target.closest('button[data-user-chat]')) return;
                startDirectChat(Number(item.dataset.userId)).catch((error) => alert(error.message || 'Unable to start chat.'));
            });
        });
        peoplePane.querySelectorAll('[data-user-chat]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                startDirectChat(Number(button.dataset.userChat)).catch((error) => alert(error.message || 'Unable to start chat.'));
            });
        });
    };

    const renderConversationView = () => {
        if (!activeConversation) return;
        conversationTitle.textContent = activeConversation.title || 'Conversation';
        conversationMembers.textContent = (activeConversation.users || [])
            .filter((user) => user.id !== currentUserId)
            .map((user) => `${user.name} · ${user.online ? 'Online' : 'Offline'}`)
            .join(', ');
        callStartButton.classList.toggle('d-none', activeConversation.type !== 'direct');
        const avatar = (user, className = 'chat-mini-message-avatar') => {
            const statusDot = user?.online !== undefined ? `<span class="chat-mini-presence-dot ${user.online ? 'online' : ''}" title="${user.online ? 'Online' : 'Offline'}"></span>` : '';
            if (user?.photo) {
                return `<span class="${className}" title="${esc(user.name || '')}"><img src="${esc(user.photo)}" alt="${esc(user.name || 'Staff')}" class="chat-mini-photo">${statusDot}</span>`;
            }

            return `<span class="${className}" title="${esc(user?.name || '')}"><i class="ti ti-user"></i>${statusDot}</span>`;
        };
        const messageContent = (message) => {
            if (message.message_type === 'voice' && message.media_url) {
                return `<div class="fw-semibold mb-1"><i class="ti ti-wave-sine me-1"></i>Voice message</div><audio controls src="${esc(message.media_url)}"></audio>`;
            }

            const text = message.message && !(message.message_type !== 'text' && message.message === message.media_name)
                ? `<div class="chat-mini-message-text">${esc(message.message)}</div>` : '';
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
                const detail = messagesBox.querySelector(`[data-message-detail="${bubble.dataset.messageId}"]`);
                const wasOpen = detail?.classList.contains('show');
                messagesBox.querySelectorAll('.chat-mini-message-detail.show').forEach((item) => item.classList.remove('show'));
                if (detail && !wasOpen) detail.classList.add('show');
            });
        });
        messagesBox.scrollTop = messagesBox.scrollHeight;
    };

    const openConversation = async (id) => {
        if (loadingConversation) return;
        loadingConversation = true;
        try {
            activeConversationId = id;
            const data = await api(`${routes.messagesBase}/${id}/messages`);
            activeConversation = data.conversation;
            activeConversation.messages = data.messages || [];
            setTab(activeTab);
            conversationsPane.classList.add('d-none');
            peoplePane.classList.add('d-none');
            conversationPane.classList.remove('d-none');
            conversationPane.classList.add('d-flex');
            renderConversations();
            renderConversationView();
            drawer.classList.remove('d-none');
            launcher.setAttribute('aria-expanded', 'true');
        } finally {
            loadingConversation = false;
        }
    };

    const startDirectChat = async (userId) => {
        const data = await api(routes.create, {
            method: 'POST',
            body: JSON.stringify({ user_ids: [userId] }),
        });

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
        if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
            alert('Your browser does not support voice recording.');
            return;
        }

        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
            return;
        }

        if (!activeConversationId) return;
        voiceChunks = [];
        voiceStartedAt = Date.now();
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
        voiceMimeType = preferredVoiceType();
        mediaRecorder = new MediaRecorder(stream, voiceMimeType ? { mimeType: voiceMimeType } : undefined);
        voiceMimeType = mediaRecorder.mimeType || voiceMimeType || 'audio/webm';
        mediaRecorder.ondataavailable = (event) => {
            if (event.data?.size) voiceChunks.push(event.data);
        };
        mediaRecorder.onstop = async () => {
            setVoiceButtonSending();
            try {
                stream.getTracks().forEach((track) => track.stop());
                const blob = new Blob(voiceChunks, { type: voiceMimeType });
                const duration = (Date.now() - voiceStartedAt) / 1000;
                if (!blob.size) throw new Error('No audio was recorded. Please allow microphone access and try again.');
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
            body: JSON.stringify({ signal_type: signalType, payload }),
        });
    };

    const resetCallState = () => {
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
    };

    const showCallPanel = (call, mode = 'calling') => {
        activeCall = call;
        callAcceptedBySelf = mode !== 'incoming';
        callPanel.classList.remove('d-none');
        callTitle.textContent = call.title || 'Voice Call';
        callStatus.textContent = mode === 'incoming'
            ? 'Incoming voice call...'
            : (call.status === 'active' ? 'Connected' : 'Calling...');
        callAcceptButton.classList.toggle('d-none', mode !== 'incoming');
        callMuteButton.classList.toggle('d-none', mode === 'incoming');
        callEndButton.innerHTML = mode === 'incoming'
            ? '<i class="ti ti-phone-off me-1"></i> Decline'
            : '<i class="ti ti-phone-off me-1"></i> End';
    };

    const setupPeerConnection = async () => {
        if (peerConnection) return peerConnection;
        localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
        peerConnection = new RTCPeerConnection({
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
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
        if (!activeCall || processedSignalIds.has(signal.id) || signal.user_id === currentUserId) return;
        if (signal.signal_type === 'offer' && !callAcceptedBySelf) return;
        processedSignalIds.add(signal.id);

        if (signal.signal_type === 'offer') {
            await setupPeerConnection();
            await peerConnection.setRemoteDescription(new RTCSessionDescription(signal.payload));
            const answer = await peerConnection.createAnswer();
            await peerConnection.setLocalDescription(answer);
            await sendCallSignal('answer', answer);
            await flushQueuedIce();
        }

        if (signal.signal_type === 'answer' && peerConnection && !peerConnection.currentRemoteDescription) {
            await peerConnection.setRemoteDescription(new RTCSessionDescription(signal.payload));
            await flushQueuedIce();
            callStatus.textContent = 'Connected';
            callMuteButton.classList.remove('d-none');
        }

        if (signal.signal_type === 'ice') {
            if (!peerConnection?.remoteDescription) {
                queuedIceCandidates.push(signal.payload);
                return;
            }
            await peerConnection.addIceCandidate(new RTCIceCandidate(signal.payload)).catch(() => {});
        }

        if (['reject', 'hangup', 'leave'].includes(signal.signal_type)) {
            resetCallState();
        }
    };

    const refreshActiveCall = async () => {
        if (!activeCall) return;
        const data = await api(`${routes.callsBase}/${activeCall.id}/signals`);
        activeCall = data.call;
        if (['ended', 'declined'].includes(activeCall.status)) {
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

        if (!navigator.mediaDevices?.getUserMedia || !window.RTCPeerConnection) {
            alert('Your browser does not support live voice calls.');
            return;
        }

        const call = await api(`${routes.messagesBase}/${activeConversationId}/call`, {
            method: 'POST',
            body: JSON.stringify({ call_type: 'audio' }),
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
            await sendCallSignal(callAcceptButton.classList.contains('d-none') ? 'hangup' : 'reject').catch(() => {});
        }
        resetCallState();
    };

    const toggleMute = () => {
        if (!localStream) return;
        callMuted = !callMuted;
        localStream.getAudioTracks().forEach((track) => {
            track.enabled = !callMuted;
        });
        callMuteButton.innerHTML = callMuted
            ? '<i class="ti ti-microphone-off me-1"></i> Unmute'
            : '<i class="ti ti-microphone me-1"></i> Mute';
    };

    const checkIncomingCalls = async () => {
        if (activeCall) return;
        const data = await api(routes.callsPending);
        const incoming = (data.calls || []).find((call) => call.status === 'ringing' && !call.is_caller);
        if (incoming) {
            showCallPanel(incoming, 'incoming');
        }
    };

    const refreshData = async () => {
        try {
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
        launcher.setAttribute('aria-expanded', 'true');
        await refreshData();
        if (activeConversationId && !conversationPane.classList.contains('d-none')) {
            await openConversation(activeConversationId);
        }
    };

    const closeDrawer = () => {
        drawer.classList.add('d-none');
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
        const left = clamp(event.clientX - launcherDrag.offsetX, 12, window.innerWidth - width - 12);
        const top = clamp(event.clientY - launcherDrag.offsetY, 12, window.innerHeight - height - 12);
        launcher.style.setProperty('position', 'fixed', 'important');
        launcher.style.setProperty('left', `${left}px`, 'important');
        launcher.style.setProperty('top', `${top}px`, 'important');
        launcher.style.setProperty('right', 'auto', 'important');
        launcher.style.setProperty('bottom', 'auto', 'important');
    });

    launcher.addEventListener('pointerup', (event) => {
        if (!launcherDrag || launcherDrag.pointerId !== event.pointerId) return;
        launcher.classList.remove('dragging');
        try { launcher.releasePointerCapture(event.pointerId); } catch (error) {}
        if (launcherDrag.moved) {
            suppressLauncherClick = true;
            window.setTimeout(() => { suppressLauncherClick = false; }, 0);
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
        attachmentPreview.innerHTML = `${isImage ? `<img src="${URL.createObjectURL(file)}" alt="">` : '<i class="ti ti-file-description fs-2 text-primary"></i>'}<span class="file-name text-truncate">${esc(file.name)}<small class="d-block text-secondary">${(file.size / 1024 / 1024).toFixed(2)} MB</small></span><button type="button" class="btn btn-sm btn-outline-secondary" id="school-chat-remove-file" title="Remove attachment"><i class="ti ti-x"></i></button>`;
        attachmentPreview.classList.remove('d-none');
        document.getElementById('school-chat-remove-file').addEventListener('click', clearAttachment);
    };

    const emojis = ['😀','😃','😄','😁','😂','🤣','😊','😍','🥰','😘','😎','🤔','😢','😭','😡','👍','👏','🙏','❤️','💯','🎉','✅','⭐','🔥','💡','📚','📎','😊','🙌','👋','✨','🤝'];
    emojiGrid.innerHTML = emojis.map((emoji) => `<button type="button" class="chat-mini-emoji" data-emoji="${emoji}">${emoji}</button>`).join('');
    emojiGrid.querySelectorAll('[data-emoji]').forEach((button) => button.addEventListener('click', () => {
        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? input.value.length;
        input.value = `${input.value.slice(0, start)}${button.dataset.emoji}${input.value.slice(end)}`;
        input.focus();
        input.selectionStart = input.selectionEnd = start + button.dataset.emoji.length;
    }));
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
        if (selectedAttachment) formData.append('attachment', selectedAttachment, selectedAttachment.name);
        await postForm(`${routes.messagesBase}/${activeConversationId}/messages`, formData);
        input.value = '';
        clearAttachment();
        emojiPicker.classList.add('d-none');

        await openConversation(activeConversationId);
        await refreshData();
    });

    callStartButton.addEventListener('click', () => startVoiceCall().catch((error) => alert(error.message || 'Unable to start the voice call.')));
    recordVoiceButton.addEventListener('click', () => toggleVoiceRecording().catch((error) => alert(error.message || 'Unable to record voice message.')));
    callAcceptButton.addEventListener('click', () => acceptIncomingCall().catch((error) => alert(error.message || 'Unable to accept the call.')));
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
                const current = conversations.find((conversation) => conversation.id === activeConversationId);
                if (current) {
                    const data = await api(`${routes.messagesBase}/${activeConversationId}/messages`);
                    activeConversation = data.conversation;
                    activeConversation.messages = data.messages || [];
                    renderConversationView();
                }
            }
        } catch (error) {
            // Keep the drawer quiet if the connection blips.
        }
    }, 10000);

    window.setInterval(() => refreshActiveCall().catch(() => {}), 2000);
    window.setInterval(() => checkIncomingCalls().catch(() => {}), 5000);
    window.setInterval(() => api(routes.heartbeat, { method: 'POST' }).catch(() => {}), 60000);
    window.addEventListener('resize', dockLauncher);

    setTab('conversations');
});
</script>
@endpush
@endif
