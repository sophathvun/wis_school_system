<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;

class NotificationController
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
    }

    public function sendForm(Request $request)
    {
        $this->authorizeAdmin($request);
        return view('notification-send', [
            'users' => User::with('department')->where('status', 1)->orderBy('name')->get(['id', 'name', 'username', 'email', 'department_id']),
            'departments' => Department::where('status', 1)->orderBy('name')->get(),
        ]);
    }

    public function manage(Request $request)
    {
        $this->authorizeAdmin($request);
        $search = trim((string) $request->query('search'));
        $perPage = in_array((int) $request->query('per_page', 10), [10, 25, 50, 100], true) ? (int) $request->query('per_page', 10) : 10;
        $notifications = UserNotification::with('user')->when($search, fn ($q) => $q->where(fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('message', 'like', "%{$search}%")))
            ->latest()->paginate($perPage)->withQueryString();
        $editNotification = $request->integer('edit') ? UserNotification::find($request->integer('edit')) : null;
        return view('notification-management', compact('notifications', 'editNotification'));
    }

    public function update(Request $request, UserNotification $notification)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['type' => ['required', 'string', 'max:80'], 'title' => ['required', 'string', 'max:160'], 'message' => ['required', 'string', 'max:2000'], 'action_url' => ['nullable', 'url', 'max:500']]);
        $notification->update($data);
        return back()->with('success', 'Notification updated successfully.');
    }

    public function delete(Request $request, UserNotification $notification)
    {
        $this->authorizeAdmin($request);
        $notification->delete();
        return back()->with('success', 'Notification deleted successfully.');
    }

    public function send(Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'recipient_ids' => ['array'],
            'recipient_ids.*' => ['integer', 'exists:users,id'],
            'department_ids' => ['array'],
            'department_ids.*' => ['integer', 'exists:access_departments,id'],
            'send_to_all' => ['nullable', 'boolean'],
            'type' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:2000'],
            'action_url' => ['nullable', 'url', 'max:500'],
        ]);
        $recipientIds = $request->boolean('send_to_all')
            ? User::where('status', 1)->pluck('id')
            : User::where('status', 1)->where(function ($query) use ($data) {
                $query->whereIn('id', $data['recipient_ids'] ?? [])
                    ->orWhereIn('department_id', $data['department_ids'] ?? []);
            })->pluck('id');
        if ($recipientIds->isEmpty()) return back()->withErrors(['recipient_ids' => 'Select at least one recipient or choose all active users.'])->withInput();
        $now = now();
        UserNotification::insert($recipientIds->unique()->map(fn ($userId) => [
            'user_id' => $userId, 'type' => $data['type'], 'title' => $data['title'],
            'message' => $data['message'], 'action_url' => $data['action_url'] ?? null,
            'created_at' => $now, 'updated_at' => $now,
        ])->all());
        return redirect()->route('notifications.send')->with('success', 'Notification sent successfully.');
    }

    public function index(Request $request)
    {
        $notifications = $request->user()->userNotifications()->latest()->paginate(20);
        return view('notifications', compact('notifications'));
    }

    public function read(Request $request, UserNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);
        return $notification->action_url ? redirect($notification->action_url) : back();
    }

    public function readAll(Request $request)
    {
        $request->user()->userNotifications()->whereNull('read_at')->update(['read_at' => now()]);
        return back()->with('success', 'All notifications marked as read.');
    }
}
