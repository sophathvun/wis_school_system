<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        return response()->json($this->availableUsers($request)->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'department' => $user->department?->name,
            'online' => $user->last_seen_at?->greaterThan(now()->subMinutes(5)) ?? false,
            'photo' => $user->photo_path ? asset('storage/'.$user->photo_path) : null,
        ])->values());
    }

    public function conversations(Request $request)
    {
        $this->touch($request);
        $conversations = $request->user()->chatConversations()
            ->with(['users.department', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('updated_at')->get();

        return response()->json($conversations->map(fn ($conversation) => $this->conversationData($conversation, $request->user()))->values());
    }

    public function unread(Request $request)
    {
        $this->touch($request);
        $total = 0;
        foreach ($request->user()->chatConversations()->get() as $conversation) {
            $readAt = $conversation->pivot->last_read_at;
            $total += $conversation->messages()->where('user_id', '!=', $request->user()->id)
                ->when($readAt, fn ($query) => $query->where('created_at', '>', $readAt))->count();
        }

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
                ->whereHas('users', fn ($q) => $q->whereKey($participantIds->all()))
                ->withCount('users')->having('users_count', 2)->first();
        }
        if (!$conversation) {
            $conversation = DB::transaction(function () use ($user, $participantIds, $type, $data) {
                $conversation = ChatConversation::create(['type' => $type, 'title' => $type === 'group' ? ($data['title'] ?? 'New Group Chat') : null, 'created_by' => $user->id]);
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
        return response()->json(['conversation' => $this->conversationData($conversation, $request->user()), 'messages' => $messages->map(fn ($message) => $this->messageData($message))]);
    }

    public function send(Request $request, ChatConversation $conversation)
    {
        $this->authorizeMember($request, $conversation);
        $data = $request->validate(['message' => ['required', 'string', 'max:5000']]);
        $message = $conversation->messages()->create(['user_id' => $request->user()->id, 'message' => trim($data['message'])]);
        $conversation->touch();
        return response()->json($this->messageData($message->load('user')));
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
            $query->where(function ($q) use ($campusIds, $user) {
                $q->whereHas('campuses', fn ($campus) => $campus->whereIn('tb_school_info.id', $campusIds));
                if ($user->department_id) $q->orWhere('department_id', $user->department_id);
            });
        }
        return $query->orderBy('name')->get();
    }

    private function authorizeMember(Request $request, ChatConversation $conversation): void
    {
        abort_unless($conversation->users()->whereKey($request->user()->id)->exists(), 403);
    }

    private function touch(Request $request): void
    {
        $request->user()->forceFill(['last_seen_at' => now()])->saveQuietly();
    }

    private function conversationData(ChatConversation $conversation, User $user): array
    {
        $otherUsers = $conversation->users->where('id', '!=', $user->id);
        $pivot = $conversation->users->firstWhere('id', $user->id)?->pivot;
        $unread = $conversation->messages()->where('user_id', '!=', $user->id)->when($pivot?->last_read_at, fn ($q, $date) => $q->where('created_at', '>', $date))->count();
        return ['id' => $conversation->id, 'type' => $conversation->type, 'title' => $conversation->type === 'group' ? $conversation->title : ($otherUsers->first()?->name ?? 'Conversation'), 'users' => $conversation->users->map(fn ($member) => ['id' => $member->id, 'name' => $member->name, 'online' => $member->last_seen_at?->greaterThan(now()->subMinutes(5)) ?? false]), 'last_message' => $conversation->messages->first()?->message, 'unread_messages' => $unread];
    }

    private function messageData(ChatMessage $message): array
    {
        return ['id' => $message->id, 'message' => $message->message, 'created_at' => $message->created_at?->format('d-m-Y H:i'), 'user_id' => $message->user_id, 'user_name' => $message->user?->name];
    }
}
