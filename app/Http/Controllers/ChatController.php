<?php

namespace App\Http\Controllers;

use App\Models\ChatCall;
use App\Models\ChatCallSignal;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatController
{
    public function index(Request $request)
    {
        $this->touch($request);
        return view('chat', ['users' => $this->availableUsers($request)]);
    }

    public function users(Request $request)
    {
        $this->touch($request);

        $directUnread = [];
        $directConversations = $request->user()->chatConversations()
            ->where('type', 'direct')
            ->with('users')
            ->get();

        foreach ($directConversations as $conversation) {
            $other = $conversation->users->firstWhere('id', '!=', $request->user()->id);
            if (!$other) continue;

            $pivot = $conversation->users->firstWhere('id', $request->user()->id)?->pivot;
            $directUnread[$other->id] = $conversation->messages()
                ->where('user_id', $other->id)
                ->when($pivot?->last_read_at, fn ($query, $date) => $query->where('created_at', '>', $date))
                ->count();
        }

        return response()->json($this->availableUsers($request)->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'department' => $user->department?->name,
            'online' => $user->last_seen_at?->greaterThan(now()->subMinutes(5)) ?? false,
            'photo' => $user->photo_path ? asset('storage/'.$user->photo_path) : null,
            'unread_messages' => $directUnread[$user->id] ?? 0,
        ])->values());
    }

    public function conversations(Request $request)
    {
        $this->touch($request);

        $conversations = $request->user()->chatConversations()
            ->with(['users.department', 'messages' => fn ($query) => $query->latest()->limit(1)])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($conversations->map(fn ($conversation) => $this->conversationData($conversation, $request->user()))->values());
    }

    public function unread(Request $request)
    {
        $this->touch($request);

        $userId = (int) $request->user()->id;
        $total = DB::table('chat_messages as messages')
            ->join('chat_conversation_users as readers', function ($join) use ($userId) {
                $join->on('readers.conversation_id', '=', 'messages.conversation_id')
                    ->where('readers.user_id', '=', $userId);
            })
            ->where('messages.user_id', '!=', $userId)
            ->where(function ($query) {
                $query->whereNull('readers.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'readers.last_read_at');
            })
            ->count('messages.id');

        return response()->json(['unread' => $total]);
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $allowed = $this->availableUsers($request)->pluck('id');
        $ids = collect($data['user_ids'])->unique()->filter(fn ($id) => $allowed->contains((int) $id))->values();
        abort_if($ids->isEmpty(), 422, 'Select at least one available user.');

        $participantIds = $ids->push($user->id)->unique()->sort()->values();
        $type = $participantIds->count() > 2 ? 'group' : 'direct';
        $conversation = null;

        if ($type === 'direct') {
            $conversation = ChatConversation::where('type', 'direct')
                ->whereHas('users', fn ($query) => $query->whereKey($user->id))
                ->whereHas('users', fn ($query) => $query->whereKey($ids->first()))
                ->withCount('users')
                ->having('users_count', 2)
                ->first();
        }

        if (!$conversation) {
            $conversation = DB::transaction(function () use ($user, $participantIds, $type, $data) {
                $conversation = ChatConversation::create([
                    'type' => $type,
                    'title' => $type === 'group' ? ($data['title'] ?? 'New Group Chat') : null,
                    'created_by' => $user->id,
                ]);

                $conversation->users()->attach($participantIds->all());

                return $conversation;
            });
        }

        return response()->json(['id' => $conversation->id]);
    }

    public function messages(Request $request, ChatConversation $conversation)
    {
        $this->authorizeMember($request, $conversation);
        $request->user()->chatConversations()->updateExistingPivot($conversation->id, ['last_read_at' => now()]);
        $conversation->load('users.department');
        $messages = $conversation->messages()->with('user')->oldest()->limit(200)->get();

        return response()->json([
            'conversation' => $this->conversationData($conversation, $request->user()),
            'messages' => $messages->map(fn ($message) => $this->messageData($message, $conversation, $request->user())),
        ]);
    }

    public function send(Request $request, ChatConversation $conversation)
    {
        $this->authorizeMember($request, $conversation);
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar', 'max:20480'],
        ]);
        abort_if(blank($data['message'] ?? null) && !$request->hasFile('attachment'), 422, 'Write a message or attach a file.');

        $file = $request->file('attachment');
        $path = $file?->storeAs('chat/attachments/'.now()->format('Y/m'), Str::uuid().'.'.($file->getClientOriginalExtension() ?: 'bin'), 'public');
        $type = $file ? (str_starts_with($file->getMimeType() ?? '', 'image/') ? 'image' : 'file') : 'text';

        $message = $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'message' => trim($data['message'] ?? '') ?: ($file?->getClientOriginalName() ?? ''),
            'message_type' => $type,
            'media_path' => $path,
            'media_name' => $file?->getClientOriginalName(),
            'media_mime' => $file?->getMimeType(),
        ]);

        $conversation->touch();

        return response()->json($this->messageData($message->load('user')));
    }

    public function sendVoice(Request $request, ChatConversation $conversation)
    {
        $this->authorizeMember($request, $conversation);
        $data = $request->validate([
            'audio' => ['required', 'file', 'mimetypes:audio/webm,video/webm,audio/ogg,video/ogg,audio/mp3,audio/mpeg,audio/wav,audio/mp4,audio/x-m4a,video/mp4,application/octet-stream', 'max:10240'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:3600'],
        ]);

        $file = $data['audio'];
        $extension = $file->getClientOriginalExtension() ?: match (true) {
            str_contains($file->getMimeType() ?? '', 'ogg') => 'ogg',
            str_contains($file->getMimeType() ?? '', 'webm') => 'webm',
            str_contains($file->getMimeType() ?? '', 'mpeg') || str_contains($file->getMimeType() ?? '', 'mp3') => 'mp3',
            str_contains($file->getMimeType() ?? '', 'mp4') || str_contains($file->getMimeType() ?? '', 'm4a') => 'm4a',
            str_contains($file->getMimeType() ?? '', 'wav') => 'wav',
            default => 'webm',
        };

        $path = $file->storeAs(
            'chat/voice-notes/'.now()->format('Y/m'),
            Str::uuid().'.'.$extension,
            'public'
        );

        $message = $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'message' => 'Voice message',
            'message_type' => 'voice',
            'media_path' => $path,
            'media_name' => $file->getClientOriginalName(),
            'media_mime' => $file->getMimeType(),
            'media_duration_seconds' => $data['duration_seconds'] ?? null,
        ]);

        $conversation->touch();

        return response()->json($this->messageData($message->load('user')));
    }

    public function download(Request $request, ChatMessage $message)
    {
        $conversation = $message->conversation;
        abort_unless($conversation, 404);
        $this->authorizeMember($request, $conversation);
        abort_unless($message->media_path && Storage::disk('public')->exists($message->media_path), 404);

        return Storage::disk('public')->download(
            $message->media_path,
            $message->media_name ?: basename($message->media_path)
        );
    }

    public function callStart(Request $request, ChatConversation $conversation)
    {
        $this->authorizeMember($request, $conversation);
        abort_if($conversation->type !== 'direct' || $conversation->users()->count() !== 2, 422, 'Voice calls currently start from a direct chat.');

        $request->validate([
            'call_type' => ['nullable', 'in:audio'],
        ]);

        $call = DB::transaction(function () use ($request, $conversation) {
            $call = ChatCall::create([
                'conversation_id' => $conversation->id,
                'created_by' => $request->user()->id,
                'call_type' => 'audio',
                'status' => 'ringing',
                'meta' => ['started_from' => 'widget'],
            ]);

            $participantIds = $conversation->users()->pluck('users.id')->values();
            foreach ($participantIds as $participantId) {
                $call->participants()->create([
                    'user_id' => $participantId,
                    'role' => $participantId === $request->user()->id ? 'caller' : 'participant',
                ]);
            }

            $call->signals()->create([
                'user_id' => $request->user()->id,
                'signal_type' => 'start',
                'payload' => ['call_type' => 'audio'],
            ]);

            return $call;
        });

        return response()->json($this->callData($call->load(['conversation.users.department', 'participants.user.department', 'signals.user']), $request->user()));
    }

    public function callShow(Request $request, ChatCall $call)
    {
        $this->authorizeCallMember($request, $call);

        return response()->json($this->callData($call->load(['conversation.users.department', 'participants.user.department', 'signals.user']), $request->user()));
    }

    public function pendingCalls(Request $request)
    {
        $this->touch($request);

        $calls = ChatCall::query()
            ->whereIn('status', ['ringing', 'active'])
            ->whereHas('participants', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['conversation.users.department', 'participants.user.department', 'signals.user'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ChatCall $call) => $this->callData($call, $request->user()))
            ->values();

        return response()->json(['calls' => $calls]);
    }

    public function callSignals(Request $request, ChatCall $call)
    {
        $this->authorizeCallMember($request, $call);

        return response()->json([
            'call' => $this->callData($call->load(['conversation.users.department', 'participants.user.department', 'signals.user']), $request->user()),
            'signals' => $call->signals()->with('user')->oldest()->get()->map(fn ($signal) => $this->callSignalData($signal))->values(),
        ]);
    }

    public function callSignal(Request $request, ChatCall $call)
    {
        $this->authorizeCallMember($request, $call);
        $data = $request->validate([
            'signal_type' => ['required', 'string', 'in:start,offer,answer,ice,accept,reject,hangup,join,leave,mute,unmute'],
            'payload' => ['nullable', 'array'],
        ]);

        $signal = $call->signals()->create([
            'user_id' => $request->user()->id,
            'signal_type' => $data['signal_type'],
            'payload' => $data['payload'] ?? null,
        ]);

        if (in_array($data['signal_type'], ['accept', 'join'], true)) {
            $call->forceFill([
                'status' => 'active',
                'started_at' => $call->started_at ?? now(),
            ])->save();
            $participant = $call->participants()->where('user_id', $request->user()->id)->first();
            if ($participant) {
                $participant->forceFill([
                    'joined_at' => $participant->joined_at ?? now(),
                    'last_seen_at' => now(),
                ])->save();
            }
        }

        if ($data['signal_type'] === 'reject') {
            $call->forceFill([
                'status' => 'declined',
                'ended_at' => now(),
            ])->save();
            $call->participants()->where('user_id', $request->user()->id)->update(['left_at' => now()]);
        }

        if ($data['signal_type'] === 'hangup') {
            $call->forceFill([
                'status' => 'ended',
                'ended_at' => now(),
            ])->save();
            $call->participants()->where('user_id', $request->user()->id)->update(['left_at' => now()]);
        }

        return response()->json([
            'signal' => $this->callSignalData($signal->load('user')),
            'call' => $this->callData($call->load(['conversation.users.department', 'participants.user.department', 'signals.user']), $request->user()),
        ]);
    }

    public function heartbeat(Request $request)
    {
        $this->touch($request);
        return response()->json(['ok' => true]);
    }

    private function availableUsers(Request $request)
    {
        $user = $request->user();
        $query = User::query()->where('status', 1)->where('id', '!=', $user->id)->with('department');

        if (!$user->isSuperAdmin()) {
            $campusIds = $user->accessibleCampuses()->pluck('tb_school_info.id');
            $query->where(function ($query) use ($campusIds, $user) {
                $query->whereHas('campuses', fn ($campus) => $campus->whereIn('tb_school_info.id', $campusIds));
                if ($user->department_id) {
                    $query->orWhere('department_id', $user->department_id);
                }
            });
        }

        return $query->orderBy('name')->get();
    }

    private function authorizeMember(Request $request, ChatConversation $conversation): void
    {
        abort_unless($conversation->users()->whereKey($request->user()->id)->exists(), 403);
    }

    private function authorizeCallMember(Request $request, ChatCall $call): void
    {
        abort_unless($call->participants()->where('user_id', $request->user()->id)->exists(), 403);
    }

    private function touch(Request $request): void
    {
        $request->user()->forceFill(['last_seen_at' => now()])->saveQuietly();
    }

    private function conversationData(ChatConversation $conversation, User $user): array
    {
        $otherUsers = $conversation->users->where('id', '!=', $user->id);
        $pivot = $conversation->users->firstWhere('id', $user->id)?->pivot;
        $unread = $conversation->messages()
            ->where('user_id', '!=', $user->id)
            ->when($pivot?->last_read_at, fn ($query, $date) => $query->where('created_at', '>', $date))
            ->count();

        $latestCall = $conversation->calls()->latest()->first();

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'title' => $conversation->type === 'group' ? $conversation->title : ($otherUsers->first()?->name ?? 'Conversation'),
            'photo' => $conversation->type === 'direct' ? ($otherUsers->first()?->photo_path ? asset('storage/'.$otherUsers->first()->photo_path) : null) : null,
            'online' => $conversation->type === 'direct' ? ($otherUsers->first()?->last_seen_at?->greaterThan(now()->subMinutes(5)) ?? false) : $otherUsers->contains(fn ($member) => $member->last_seen_at?->greaterThan(now()->subMinutes(5)) ?? false),
            'users' => $conversation->users->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'online' => $member->last_seen_at?->greaterThan(now()->subMinutes(5)) ?? false,
                'photo' => $member->photo_path ? asset('storage/'.$member->photo_path) : null,
            ]),
            'last_message' => $conversation->messages->first()?->message,
            'unread_messages' => $unread,
            'latest_call_status' => $latestCall?->status,
        ];
    }

    private function messageData(ChatMessage $message, ?ChatConversation $conversation = null, ?User $viewer = null): array
    {
        $readBy = collect();
        $unreadBy = collect();
        if ($conversation && $viewer) {
            $readBy = $conversation->users
                ->filter(fn ($member) => $member->id !== $viewer->id && $member->pivot?->last_read_at && $member->pivot->last_read_at >= $message->created_at)
                ->map(function ($member) {
                    $readAt = $member->pivot?->last_read_at ? Carbon::parse($member->pivot->last_read_at) : null;

                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'photo' => $member->photo_path ? asset('storage/'.$member->photo_path) : null,
                        'read_at' => $readAt?->format('d-m-Y H:i'),
                    ];
                })
                ->values();

            $unreadBy = $conversation->users
                ->filter(fn ($member) => $member->id !== $viewer->id && $member->id !== $message->user_id && (!$member->pivot?->last_read_at || $member->pivot->last_read_at < $message->created_at))
                ->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'photo' => $member->photo_path ? asset('storage/'.$member->photo_path) : null,
                ])
                ->values();
        }

        return [
            'id' => $message->id,
            'message' => $message->message,
            'message_type' => $message->message_type ?? 'text',
            'media_url' => $message->media_path ? asset('storage/'.$message->media_path) : null,
            'media_download_url' => $message->media_path ? route('chat.messages.download', $message) : null,
            'media_name' => $message->media_name,
            'media_mime' => $message->media_mime,
            'media_duration_seconds' => $message->media_duration_seconds,
            'created_at' => $message->created_at?->format('d-m-Y H:i'),
            'user_id' => $message->user_id,
            'user_name' => $message->user?->name,
            'user_photo' => $message->user?->photo_path ? asset('storage/'.$message->user->photo_path) : null,
            'user_online' => $message->user?->last_seen_at?->greaterThan(now()->subMinutes(5)) ?? false,
            'read_by' => $readBy,
            'unread_by' => $unreadBy,
        ];
    }

    private function callData(ChatCall $call, User $viewer): array
    {
        $participants = $call->participants->map(function ($participant) use ($viewer) {
            $member = $participant->user;

            return [
                'id' => $member?->id,
                'name' => $member?->name,
                'online' => $member?->last_seen_at?->greaterThan(now()->subMinutes(5)) ?? false,
                'role' => $participant->role,
                'joined_at' => $participant->joined_at?->format('d-m-Y H:i'),
                'is_self' => $member?->id === $viewer->id,
            ];
        })->values();

        return [
            'id' => $call->id,
            'conversation_id' => $call->conversation_id,
            'title' => $call->conversation?->type === 'group'
                ? ($call->conversation?->title ?? 'Voice Call')
                : ($call->conversation?->users->firstWhere('id', '!=', $viewer->id)?->name ?? 'Voice Call'),
            'status' => $call->status,
            'call_type' => $call->call_type,
            'created_by' => $call->created_by,
            'started_at' => $call->started_at?->format('d-m-Y H:i'),
            'ended_at' => $call->ended_at?->format('d-m-Y H:i'),
            'participants' => $participants,
            'signals' => $call->signals->map(fn ($signal) => $this->callSignalData($signal))->values(),
            'is_caller' => $call->created_by === $viewer->id,
        ];
    }

    private function callSignalData(ChatCallSignal $signal): array
    {
        return [
            'id' => $signal->id,
            'user_id' => $signal->user_id,
            'signal_type' => $signal->signal_type,
            'payload' => $signal->payload,
            'created_at' => $signal->created_at?->format('d-m-Y H:i:s'),
        ];
    }
}
