@extends('layouts.app')

@section('title', 'Chat')

@section('page-header')
    <div class="container-fluid chat-page-header">
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
        <div class="card chat-shell d-flex flex-row overflow-hidden" id="chat-shell"
            data-chat-users-url="{{ route('chat.users') }}"
            data-chat-conversations-url="{{ route('chat.conversations') }}"
            data-chat-create-url="{{ route('chat.create') }}"
            data-chat-heartbeat-url="{{ route('chat.heartbeat') }}"
            data-chat-messages-base="{{ url('/communication/chat') }}"
            data-chat-current-user-id="{{ $currentChatUserId }}"
            data-chat-current-user-name="{{ auth()->user()->name }}"
            data-chat-current-user-photo="{{ $currentChatUserPhoto }}">
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

@vite('resources/css/pages/chat.css')
    @vite('resources/js/chat.js')
