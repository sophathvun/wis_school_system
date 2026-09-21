<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\WebPushService;

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
            'users' => User::with(['department', 'position'])->where('status', 1)->orderBy('name')->get(['id', 'name', 'username', 'email', 'department_id', 'position_id', 'photo_path', 'last_seen_at']),
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
        $data = $request->validate(['type' => ['required', 'string', 'max:80'], 'title' => ['required', 'string', 'max:160'], 'message' => ['required', 'string', 'max:20000'], 'action_url' => ['nullable', 'url', 'max:500']]);
        $data['message'] = $this->cleanNotificationHtml($data['message']);
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
            'message' => ['required', 'string', 'max:20000'],
            'action_url' => ['nullable', 'url', 'max:500'],
        ]);
        $data['message'] = $this->cleanNotificationHtml($data['message']);
        $recipientIds = $request->boolean('send_to_all')
            ? User::where('status', 1)->pluck('id')
            : User::where('status', 1)->where(function ($query) use ($data) {
                $query->whereIn('id', $data['recipient_ids'] ?? [])
                    ->orWhereIn('department_id', $data['department_ids'] ?? []);
            })->pluck('id');
        if ($recipientIds->isEmpty()) return back()->withErrors(['recipient_ids' => 'Select at least one recipient or choose all active users.'])->withInput();
        $now = now();
        $recipientIds = $recipientIds->unique();
        UserNotification::insert($recipientIds->map(fn ($userId) => [
            'user_id' => $userId, 'type' => $data['type'], 'title' => $data['title'],
            'message' => $data['message'], 'action_url' => $data['action_url'] ?? null,
            'created_at' => $now, 'updated_at' => $now,
        ])->all());

        app(WebPushService::class)->sendToUsers($recipientIds, [
            'title' => $data['title'],
            'body' => Str::limit(strip_tags($data['message']), 120),
            'url' => $data['action_url'] ?? route('notifications.index'),
            'tag' => 'school-notification',
        ]);

        return redirect()->route('notifications.send')->with('success', 'Notification sent successfully.');
    }

    public function uploadImage(Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]);

        $file = $data['image'];
        $name = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('notifications/images', $name, 'public');

        return response()->json([
            'url' => '/storage/'.$path,
        ]);
    }

    public function index(Request $request)
    {
        $notifications = $request->user()->userNotifications()->latest()->paginate(20);
        return view('notifications', compact('notifications'));
    }

    public function unread(Request $request)
    {
        $unread = $request->user()->userNotifications()->whereNull('read_at')->count();
        $latest = $request->user()->userNotifications()->latest()->first();
        $items = $request->user()->userNotifications()
            ->when($unread > 0, fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'message' => Str::limit(strip_tags($item->message), 120),
                'url' => $item->action_url ?: route('notifications.index'),
                'created_at' => $item->created_at?->toIso8601String(),
                'time' => $item->created_at?->diffForHumans(),
                'read' => (bool) $item->read_at,
            ])
            ->values();

        return response()->json([
            'unread' => $unread,
            'latest' => $latest ? [
                'id' => $latest->id,
                'title' => $latest->title,
                'message' => Str::limit(strip_tags($latest->message), 120),
                'url' => $latest->action_url ?: route('notifications.index'),
                'created_at' => $latest->created_at?->toIso8601String(),
                'read' => (bool) $latest->read_at,
            ] : null,
            'items' => $items,
        ]);
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

    private function cleanNotificationHtml(string $html): string
    {
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><a><img><h3><h4><blockquote><div><span><table><thead><tbody><tr><th><td>');
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/(href|src)\s*=\s*([\'"])\s*javascript:[^\'"]*\2/i', '$1="#"', $html) ?? '';
        $html = preg_replace_callback('/<a\b[^>]*>/i', function ($match) {
            $tag = preg_replace('/\s+target\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $match[0]) ?? $match[0];
            $tag = preg_replace('/\s+rel\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $tag) ?? $tag;

            return rtrim($tag, '>').' target="_blank" rel="noopener noreferrer">';
        }, $html) ?? '';
        $html = preg_replace_callback('/<span\b[^>]*>/i', function ($match) {
            preg_match('/\sstyle\s*=\s*([\'"])(.*?)\1/i', $match[0], $style);
            $rules = [];
            if (!empty($style[2]) && preg_match('/font-family\s*:\s*([^;]+)/i', $style[2], $family)) {
                $allowedFonts = [
                    'khmer os siemreap' => 'Khmer OS Siemreap',
                    'khmer os battambang' => 'Khmer OS Battambang',
                    'khmer os muol light' => 'Khmer OS Muol Light',
                    'noto sans khmer' => 'Noto Sans Khmer',
                    'tacteing' => 'Tacteing',
                    'arial' => 'Arial',
                    'times new roman' => 'Times New Roman',
                ];
                $fontValue = strtolower(trim(str_replace(['"', "'"], '', $family[1])));
                if (isset($allowedFonts[$fontValue])) {
                    $rules[] = 'font-family: '.$allowedFonts[$fontValue];
                }
            }
            if (!empty($style[2]) && preg_match('/font-size\s*:\s*(12px|14px|16px|18px|20px)/i', $style[2], $size)) {
                $rules[] = 'font-size: '.strtolower($size[1]);
            }
            if (!empty($style[2]) && preg_match('/color\s*:\s*(#[0-9a-f]{6})/i', $style[2], $color)) {
                $rules[] = 'color:'.strtolower($color[1]);
            }
            if (!empty($style[2]) && preg_match('/color\s*:\s*(rgb\(\s*(?:[0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\s*,\s*(?:[0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\s*,\s*(?:[0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\s*\))/i', $style[2], $color)) {
                $rules[] = 'color:'.strtolower($color[1]);
            }

            return $rules ? '<span style="'.implode('; ', $rules).'">' : '<span>';
        }, $html) ?? '';
        $html = preg_replace_callback('/<img\b[^>]*>/i', function ($match) {
            $tag = $match[0];
            preg_match('/\ssrc\s*=\s*([\'"])(.*?)\1/i', $tag, $src);
            preg_match('/\salt\s*=\s*([\'"])(.*?)\1/i', $tag, $alt);
            preg_match('/\sstyle\s*=\s*([\'"])(.*?)\1/i', $tag, $style);
            $width = '';
            if (!empty($style[2]) && preg_match('/width\s*:\s*([^;]+);?/i', $style[2], $widthMatch)) {
                $widthValue = trim($widthMatch[1]);
                $width = ' style="width:'.e($widthValue).'; height:auto;"';
            }
            if (empty($src[2])) {
                return '';
            }

            return '<img src="'.e($src[2]).'" alt="'.e($alt[2] ?? 'Notification image').'"'.$width.'>';
        }, $html) ?? '';
        $html = preg_replace('/<img\b(?![^>]*\bsrc=)[^>]*>/i', '', $html) ?? '';

        return trim($html);
    }
}
