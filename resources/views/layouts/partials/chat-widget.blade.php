@if (auth()->check() && !request()->routeIs('chat.index'))


    <div id="school-chat-widget" class="school-chat-widget"
        data-users-url="{{ route('chat.users') }}"
        data-conversations-url="{{ route('chat.conversations') }}"
        data-unread-url="{{ route('chat.unread') }}"
        data-create-url="{{ route('chat.create') }}"
        data-heartbeat-url="{{ route('chat.heartbeat') }}"
        data-chat-base-url="{{ url('/communication/chat') }}"
        data-messages-base="{{ url('/communication/chat') }}"
        data-calls-pending-url="{{ route('chat.calls.pending') }}"
        data-calls-base="{{ url('/communication/chat/calls') }}"
        data-current-user-id="{{ auth()->id() }}"
        data-current-user-name="{{ auth()->user()->name }}"
        data-current-user-photo="{{ auth()->user()->photo_path ? asset('storage/' . auth()->user()->photo_path) : '' }}">
        <button type="button" id="school-chat-launcher" class="btn btn-primary school-chat-launcher position-relative"
            aria-label="Open chat" aria-expanded="false"
            style="position:fixed;right:1.25rem;bottom:1.75rem;left:auto;top:auto;z-index:2147483646;">
            <i class="ti ti-messages fs-3"></i>
            <span id="school-chat-unread-badge"
                class="badge bg-red position-absolute top-0 start-100 translate-middle d-none">0</span>
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
                        <a href="{{ route('chat.index') }}" class="btn btn-outline-primary btn-sm chat-widget-open-full">Open full page</a>
                        <button type="button" id="school-chat-minimize" class="btn btn-outline-secondary btn-sm"
                            aria-label="Minimize chat">
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
                    <input type="search" id="school-chat-search" class="form-control"
                        placeholder="Search chats or staff">
                </div>
                <button type="button" id="school-chat-new-group" class="btn btn-outline-primary btn-sm w-100 mt-2">
                    <i class="ti ti-users-plus me-1"></i> New Group Chat
                </button>
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
                            <div class="fw-semibold text-truncate" id="school-chat-conversation-title">Conversation
                            </div>
                            <div class="small text-secondary text-truncate" id="school-chat-conversation-members"></div>
                            <div id="school-chat-group-controls" class="chat-mini-group-controls d-none">
                                <button type="button" id="school-chat-rename-group" class="btn btn-outline-primary btn-sm">
                                    <i class="ti ti-edit me-1"></i> Rename
                                </button>
                                <button type="button" id="school-chat-add-group-members" class="btn btn-outline-primary btn-sm">
                                    <i class="ti ti-user-plus me-1"></i> Add members
                                </button>
                                <button type="button" id="school-chat-group-photo" class="btn btn-outline-primary btn-sm">
                                    <i class="ti ti-photo me-1"></i> Photo
                                </button>
                                <button type="button" id="school-chat-group-admins" class="btn btn-outline-primary btn-sm">
                                    <i class="ti ti-shield-star me-1"></i> Admins
                                </button>
                                <input type="file" id="school-chat-group-photo-file" class="d-none" accept="image/*">
                            </div>
                        </div>
                        <button type="button" id="school-chat-call-start" class="btn btn-outline-success btn-sm"
                            title="Start voice call">
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
                            <div class="chat-composer-actions">
                                <button type="button" id="school-chat-more-actions"
                                    class="btn btn-outline-secondary" title="More chat options"
                                    aria-label="More chat options" aria-expanded="false">
                                    <i class="ti ti-plus"></i>
                                </button>
                                <div id="school-chat-actions-menu" class="chat-composer-actions-menu d-none">
                                    <button type="button" id="school-chat-attach" class="btn btn-outline-secondary"
                                        title="Attach a file or photo">
                                        <i class="ti ti-paperclip"></i>
                                    </button>
                                    <button type="button" id="school-chat-record-voice"
                                        class="btn btn-outline-primary" title="Record voice message">
                                        <i class="ti ti-microphone"></i>
                                    </button>
                                    <button type="button" id="school-chat-emoji" class="btn btn-outline-secondary"
                                        title="Add emoji">
                                        <i class="ti ti-mood-smile"></i>
                                    </button>
                                </div>
                            </div>
                            <textarea id="school-chat-input" class="form-control" rows="1" placeholder="Type a message..."></textarea>
                            <button class="btn btn-primary" type="submit"><i class="ti ti-send"></i></button>
                        </div>
                        <input type="file" id="school-chat-file" class="d-none"
                            accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
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
@endif
