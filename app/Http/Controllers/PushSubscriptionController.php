<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\Request;

class PushSubscriptionController
{
    public function publicKey()
    {
        return response()->json(['public_key' => config('webpush.vapid.public_key')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:1000'],
            'keys.auth' => ['required', 'string', 'max:1000'],
            'expirationTime' => ['nullable'],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $request->user()->id,
                'public_key' => $data['keys']['p256dh'] ?? null,
                'auth_token' => $data['keys']['auth'] ?? null,
                'content_encoding' => 'aes128gcm',
                'user_agent' => (string) $request->userAgent(),
                'last_used_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function test(Request $request, WebPushService $push)
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);
        $subscription = PushSubscription::where('user_id', $request->user()->id)->where('endpoint', $data['endpoint'])->firstOrFail();
        // This diagnostic must never turn a stored URL into an arbitrary server request.
        $parts = parse_url($subscription->endpoint);
        $host = strtolower($parts['host'] ?? '');
        $knownPushService = in_array($host, ['fcm.googleapis.com', 'android.googleapis.com', 'web.push.apple.com'], true)
            || $host === 'push.services.mozilla.com' || str_ends_with($host, '.push.services.mozilla.com')
            || str_ends_with($host, '.notify.windows.com');
        abort_unless($knownPushService && ($parts['scheme'] ?? '') === 'https' && !isset($parts['user']) && !isset($parts['pass']) && (!isset($parts['port']) || $parts['port'] === 443), 422, 'This device test requires a supported browser push service. Re-enable notifications in Chrome, Safari, Firefox or Edge.');
        if (!$push->enabled()) return response()->json(['ok' => false, 'message' => 'Server push keys are not configured. Ask your administrator to run php artisan webpush:setup --check.'], 503);
        $result = $push->sendToSubscriptions(collect([$subscription]), [
            'title' => 'WIS School: Phone Notification Test',
            'body' => 'This test was requested from your device. Check the WIS School icon for a notification dot or badge.',
            'url' => route('notifications.index'), 'tag' => 'school-push-test',
            'badgeCount' => $push->unreadBadgeCountForUser((int) $request->user()->id),
        ]);
        if ($result['accepted'] > 0) return response()->json(['ok' => true]);
        $message = $result['expired'] > 0
            ? 'Your device subscription has expired. Tap Test Phone Notifications again to renew it and retry.'
            : ($result['status']
                ? 'The push service rejected the test (HTTP '.$result['status'].'). Ask your administrator to check the server push keys and Laravel log.'
                : 'The server could not deliver the test. Ask your administrator to check outbound HTTPS, PHP OpenSSL and the Laravel log.');
        return response()->json(['ok' => false, 'code' => $result['expired'] > 0 ? 'expired_subscription' : 'push_failed', 'message' => $message], 502);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);
        PushSubscription::where('user_id', $request->user()->id)->where('endpoint', $data['endpoint'])->delete();
        return response()->json(['ok' => true]);
    }
}
