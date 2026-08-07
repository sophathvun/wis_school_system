@extends('layouts.app')

@section('title', 'Chat')

@section('page-header')
<div class="container-fluid">
    <div class="row g-2 align-items-center">
        <div class="col"><div class="page-pretitle">Communication</div><h2 class="page-title">Chat</h2></div>
        <div class="col-auto"><button class="btn btn-primary" id="new-chat"><i class="ti ti-plus icon"></i> New Chat</button></div>
    </div>
</div>
@endsection

@section('content')
<style>
    .chat-shell { height: calc(100vh - 205px); min-height: 520px; }
    .chat-conversation-list { width: 340px; border-right: 1px solid var(--tblr-border-color); overflow-y: auto; }
    .chat-conversation-item { cursor: pointer; border: 0; border-bottom: 1px solid var(--tblr-border-color); }
    .chat-conversation-item.active { background: var(--tblr-primary-lt); }
    .chat-conversation-item:hover { background: var(--tblr-bg-surface-secondary); }
    .chat-messages { flex: 1; overflow-y: auto; padding: 1.25rem; background: var(--tblr-bg-surface-secondary); }
    .chat-message { max-width: 75%; margin-bottom: .75rem; }
    .chat-message.mine { margin-left: auto; }
    .chat-message-bubble { padding: .65rem .85rem; border-radius: .6rem; background: var(--tblr-bg-surface); box-shadow: 0 1px 2px rgba(0,0,0,.06); white-space: pre-wrap; }
    .chat-message.mine .chat-message-bubble { background: var(--tblr-primary); color: #fff; }
    .chat-online-dot { width: 9px; height: 9px; border-radius: 50%; background: #adb5bd; display: inline-block; }
    .chat-online-dot.online { background: #2fb344; }
    .chat-empty { flex: 1; display: grid; place-items: center; color: var(--tblr-secondary); }
    @media (max-width: 767px) { .chat-conversation-list { width: 100%; border-right: 0; } .chat-panel.d-none-mobile { display: none!important; } .chat-shell.has-conversation .chat-conversation-list { display: none; } }
</style>
<div class="col-12">
    <div class="card chat-shell d-flex flex-row overflow-hidden">
        <div class="chat-conversation-list" id="conversation-list">
            <div class="p-3 border-bottom">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h3 class="card-title mb-0">Conversations</h3>
                    <button type="button" class="btn btn-primary btn-sm" id="new-chat-sidebar" title="Start a chat or group chat"><i class="ti ti-message-plus me-1"></i> New Chat</button>
                </div>
                <div class="input-icon"><span class="input-icon-addon"><i class="ti ti-search"></i></span><input id="conversation-search" class="form-control" placeholder="Search conversations"></div>
            </div>
            <div id="conversations"><div class="text-secondary text-center p-4">Loading conversations...</div></div>
        </div>
        <div class="chat-panel flex-fill d-flex flex-column" id="chat-panel">
            <div class="chat-empty" id="chat-empty"><div class="text-center"><i class="ti ti-messages fs-1"></i><div class="mt-2">Select a conversation to start chatting.</div></div></div>
            <div class="d-none flex-column h-100" id="chat-content">
                <div class="card-header"><div><h3 class="card-title mb-0" id="chat-title">Conversation</h3><div class="text-secondary small" id="chat-members"></div></div></div>
                <div class="chat-messages" id="chat-messages"></div>
                <form class="border-top p-3" id="message-form"><div class="input-group"><textarea id="message-input" class="form-control" rows="1" placeholder="Type a message..."></textarea><button class="btn btn-primary" type="submit"><i class="ti ti-send"></i></button></div></form>
            </div>
        </div>
    </div>
</div>
<div class="modal modal-blur fade" id="newChatModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="ti ti-messages me-2"></i>Start New Chat</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="new-chat-form"><div class="modal-body"><div class="text-secondary small mb-2"><i class="ti ti-info-circle me-1"></i>Select one user for private chat or multiple users for a group chat.</div><input id="user-search" class="form-control mb-3" placeholder="Search staff"><div id="chat-user-list" class="vstack gap-2" style="max-height:320px;overflow:auto"></div><input id="group-title" class="form-control mt-3 d-none" placeholder="Group name (optional)"></div><div class="modal-footer"><button type="submit" class="btn btn-primary"><i class="ti ti-message-plus me-1"></i>Start Chat</button></div></form></div></div></div>
@endsection

@php
    $chatUsers = $users->map(function ($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'department' => $user->department?->name,
            'online' => $user->last_seen_at?->greaterThan(now()->subMinutes(5)) ?? false,
        ];
    })->values();
    $currentChatUserId = auth()->id();
@endphp

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const conversations = document.getElementById('conversations'), userList = document.getElementById('chat-user-list'), modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('newChatModal'));
    let chats = [], activeId = null, users = @json($chatUsers);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
    async function api(url, options={}) { options.headers = Object.assign({'X-CSRF-TOKEN':csrf,'Accept':'application/json','Content-Type':'application/json'}, options.headers||{}); return fetch(url, options).then(r=>r.json()); }
    function renderUsers(term='') { const q=term.toLowerCase(); const selected=Array.from(userList.querySelectorAll('input:checked')).map(input=>input.value); const filtered=users.filter(user=>(user.name+' '+(user.department||'')).toLowerCase().includes(q)); userList.innerHTML=filtered.map(user=>'<label class="form-check"><input class="form-check-input" type="checkbox" value="'+user.id+'" '+(selected.includes(String(user.id))?'checked':'')+'><span class="form-check-label"><span class="chat-online-dot '+(user.online?'online':'')+' me-2"></span>'+esc(user.name)+' <small class="text-secondary">'+esc(user.department||'')+'</small></span></label>').join('') || '<div class="text-secondary">No users found.</div>'; document.getElementById('group-title').classList.toggle('d-none', selected.length < 2); }
    function renderChats() { const term=document.getElementById('conversation-search').value.toLowerCase(); conversations.innerHTML=chats.filter(chat=>(chat.title+' '+(chat.last_message||'')).toLowerCase().includes(term)).map(chat=>'<div class="chat-conversation-item p-3 '+(chat.id===activeId?'active':'')+'" data-id="'+chat.id+'"><div class="d-flex align-items-center"><span class="avatar avatar-sm me-2"><i class="ti ti-'+(chat.type==='group'?'users':'user')+'"></i></span><div class="flex-fill text-truncate"><div class="fw-bold text-truncate">'+esc(chat.title)+'</div><div class="small text-secondary text-truncate">'+esc(chat.last_message||'No messages yet')+'</div></div>'+(chat.unread_messages?'<span class="badge bg-primary">'+chat.unread_messages+'</span>':'')+'</div></div>').join('') || '<div class="text-secondary text-center p-4">No conversations yet.</div>'; conversations.querySelectorAll('[data-id]').forEach(item=>item.onclick=()=>openChat(Number(item.dataset.id))); }
    async function loadChats() { chats=await api('{{ route('chat.conversations') }}'); renderChats(); }
    async function openChat(id) { activeId=id; renderChats(); const data=await api('{{ url('/communication/chat') }}/'+id+'/messages'); document.getElementById('chat-empty').classList.add('d-none'); document.getElementById('chat-content').classList.remove('d-none'); document.getElementById('chat-content').classList.add('d-flex'); document.getElementById('chat-title').textContent=data.conversation.title; document.getElementById('chat-members').textContent=data.conversation.users.map(user=>user.name).join(', '); document.getElementById('chat-messages').innerHTML=data.messages.map(message=>'<div class="chat-message '+(message.user_id===@json($currentChatUserId)?'mine':'')+'"><div class="small text-secondary mb-1">'+esc(message.user_name)+' · '+esc(message.created_at)+'</div><div class="chat-message-bubble">'+esc(message.message)+'</div></div>').join('') || '<div class="text-secondary text-center mt-4">No messages yet. Start the conversation.</div>'; const box=document.getElementById('chat-messages'); box.scrollTop=box.scrollHeight; }
    document.getElementById('message-form').onsubmit=async function(event){ event.preventDefault(); const input=document.getElementById('message-input'), message=input.value.trim(); if(!message||!activeId)return; input.value=''; await api('{{ url('/communication/chat') }}/'+activeId+'/messages',{method:'POST',body:JSON.stringify({message:message})}); await loadChats(); await openChat(activeId); };
    const openNewChat = () => { renderUsers(); modal.show(); };
    document.getElementById('new-chat')?.addEventListener('click', openNewChat);
    document.getElementById('new-chat-sidebar')?.addEventListener('click', openNewChat);
    document.getElementById('user-search').oninput=event=>renderUsers(event.target.value);
    userList.onchange=()=>renderUsers(document.getElementById('user-search').value);
    document.getElementById('new-chat-form').onsubmit=async function(event){ event.preventDefault(); const ids=Array.from(userList.querySelectorAll('input:checked')).map(input=>Number(input.value)); if(!ids.length)return; const data=await api('{{ route('chat.create') }}',{method:'POST',body:JSON.stringify({user_ids:ids,title:document.getElementById('group-title').value})}); modal.hide(); await loadChats(); openChat(data.id); };
    document.getElementById('conversation-search').oninput=renderChats;
    setInterval(()=>api('{{ route('chat.heartbeat') }}',{method:'POST'}),60000);
    loadChats();
});
</script>
@endpush
