<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
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
            'keys.p256dh' => ['nullable', 'string', 'max:1000'],
            'keys.auth' => ['nullable', 'string', 'max:1000'],
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

    public function destroy(Request $request)
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);
        PushSubscription::where('user_id', $request->user()->id)->where('endpoint', $data['endpoint'])->delete();
        return response()->json(['ok' => true]);
    }
}
