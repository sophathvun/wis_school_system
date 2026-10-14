<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\UserNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function enabled(): bool
    {
        return filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key'));
    }

    public function sendToUsers(iterable $userIds, array $payload): void
    {
        if (!$this->enabled()) return;

        $ids = collect($userIds)->filter()->unique()->values();
        if ($ids->isEmpty()) return;

        $subscriptions = PushSubscription::whereIn('user_id', $ids)
            ->whereNotNull('public_key')
            ->whereNotNull('auth_token')
            ->get()
            ->groupBy('user_id');

        if ($subscriptions->isEmpty()) return;

        $badgeCounts = $this->badgeCountsForUsers($subscriptions->keys());

        foreach ($subscriptions as $userId => $userSubscriptions) {
            $this->sendToSubscriptions($userSubscriptions, [
                ...$payload,
                'badgeCount' => $badgeCounts[(int) $userId] ?? 0,
            ]);
        }
    }

    public function unreadBadgeCountForUser(int $userId): int
    {
        return $this->badgeCountsForUsers([$userId])[$userId] ?? 0;
    }

    public function sendToSubscriptions(Collection $subscriptions, array $payload): array
    {
        $result = ['accepted' => 0, 'failed' => 0, 'expired' => 0, 'status' => null, 'error' => null];
        if (!$this->enabled()) return [...$result, 'error' => 'not_configured'];
        if ($subscriptions->isEmpty()) return [...$result, 'error' => 'no_subscription'];

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('webpush.vapid.subject'),
                    'publicKey' => config('webpush.vapid.public_key'),
                    'privateKey' => config('webpush.vapid.private_key'),
                ],
            ], [
                'TTL' => 60 * 60 * 24,
                'urgency' => 'normal',
            ]);

            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'keys' => [
                            'p256dh' => $subscription->public_key,
                            'auth' => $subscription->auth_token,
                        ],
                        'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
                    ]),
                    $body
                );
            }

            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                $subscription = $subscriptions->firstWhere('endpoint', $endpoint);
                if (!$subscription) continue;

                if ($report->isSuccess()) {
                    $result['accepted']++;
                    $subscription->forceFill(['last_used_at' => now()])->saveQuietly();
                    continue;
                }

                $result['failed']++;
                $result['status'] = $report->getResponse()?->getStatusCode();
                if ($report->isSubscriptionExpired()) {
                    $result['expired']++;
                    $subscription->delete();
                    continue;
                }

                Log::warning('Web push notification failed.', [
                    'endpoint' => $endpoint,
                    'reason' => $report->getReason(),
                ]);
            }
        } catch (\Throwable $e) {
            $result['error'] = 'dispatch_failed';
            Log::warning('Web push notification dispatch failed.', [
                'message' => $e->getMessage(),
            ]);
        }
        return $result;
    }

    private function badgeCountsForUsers(iterable $userIds): array
    {
        $ids = collect($userIds)->map(fn ($id) => (int) $id)->filter()->values();
        if ($ids->isEmpty()) return [];

        $notificationCounts = UserNotification::query()
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->whereIn('user_id', $ids)
            ->whereNull('read_at')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $chatCounts = DB::table('chat_conversation_users as ccu')
            ->join('chat_messages as cm', 'cm.conversation_id', '=', 'ccu.conversation_id')
            ->select('ccu.user_id', DB::raw('COUNT(*) as total'))
            ->whereIn('ccu.user_id', $ids)
            ->whereColumn('cm.user_id', '!=', 'ccu.user_id')
            ->whereNull('cm.deleted_at')
            ->where(function ($query) {
                $query->whereNull('ccu.last_read_at')->orWhereColumn('cm.created_at', '>', 'ccu.last_read_at');
            })
            ->groupBy('ccu.user_id')
            ->pluck('total', 'ccu.user_id');

        return $ids->mapWithKeys(fn ($id) => [
            $id => (int) ($notificationCounts[$id] ?? 0) + (int) ($chatCounts[$id] ?? 0),
        ])->all();
    }
}
