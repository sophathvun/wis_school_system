<?php

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\TrackUserPresence::class);
    Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('name'); $table->timestamps(); });
    (require database_path('migrations/2026_09_17_123848_create_push_subscriptions_table.php'))->up();
    $this->user = User::forceCreate(['name' => 'Test Recipient']);
    $this->subscription = PushSubscription::create(['user_id' => $this->user->id, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-device', 'public_key' => 'test', 'auth_token' => 'test']);
    $this->actingAs($this->user);
});

it('sends a device test only to the current users requested subscription', function () {
    $this->mock(WebPushService::class, function ($mock) {
        $mock->shouldReceive('enabled')->once()->andReturnTrue();
        $mock->shouldReceive('unreadBadgeCountForUser')->with($this->user->id)->once()->andReturn(5);
        $mock->shouldReceive('sendToSubscriptions')->once()->withArgs(fn ($subscriptions, $payload) =>
            $subscriptions->count() === 1 && $subscriptions->first()->id === $this->subscription->id && $payload['badgeCount'] === 5
        )->andReturn(['accepted' => 1]);
    });
    $this->postJson('/push/test', ['endpoint' => $this->subscription->endpoint])->assertOk()->assertJsonPath('ok', true);
});

it('cannot use the device test to send a notification to someone elses subscription', function () {
    $other = User::forceCreate(['name' => 'Other Recipient']);
    $this->subscription->update(['user_id' => $other->id]);
    $this->mock(WebPushService::class, fn ($mock) => $mock->shouldNotReceive('sendToSubscriptions'));
    $this->postJson('/push/test', ['endpoint' => $this->subscription->endpoint])->assertNotFound();
});

it('reports push rejection and expired subscriptions without exposing private details', function ($status, $expired, $message) {
    $this->mock(WebPushService::class, function ($mock) use ($status, $expired) {
        $mock->shouldReceive('enabled')->andReturnTrue();
        $mock->shouldReceive('unreadBadgeCountForUser')->andReturn(0);
        $mock->shouldReceive('sendToSubscriptions')->andReturn(['accepted' => 0, 'expired' => $expired, 'status' => $status]);
    });
    $this->postJson('/push/test', ['endpoint' => $this->subscription->endpoint])
        ->assertStatus(502)->assertJsonPath('ok', false)->assertJsonStructure(['message'])->assertSee($message);
})->with([[403, 0, 'HTTP 403'], [410, 1, 'expired'], [null, 0, 'outbound HTTPS']]);

it('does not accept incomplete subscriptions that cannot receive encrypted push', function () {
    $this->postJson('/push/subscriptions', ['endpoint' => 'https://push.example.test/incomplete'])
        ->assertUnprocessable()->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
    expect(PushSubscription::count())->toBe(1);
});

it('limits how often a user can request a phone test', function () {
    $this->mock(WebPushService::class, fn ($mock) => $mock->shouldReceive('enabled')->andReturnFalse());
    for ($i = 0; $i < 3; $i++) $this->postJson('/push/test', ['endpoint' => $this->subscription->endpoint])->assertStatus(503);
    $this->postJson('/push/test', ['endpoint' => $this->subscription->endpoint])->assertStatus(429);
});

it('requires sign in for a device test and returns a JSON authentication error', function () {
    auth()->logout();
    $this->postJson('/push/test', ['endpoint' => $this->subscription->endpoint])->assertUnauthorized();
});

it('does not allow a stored URL to turn the test into an arbitrary server request', function () {
    $this->subscription->update(['endpoint' => 'https://127.0.0.1/internal']);
    $this->mock(WebPushService::class, fn ($mock) => $mock->shouldNotReceive('sendToSubscriptions'));
    $this->postJson('/push/test', ['endpoint' => $this->subscription->endpoint])->assertUnprocessable();
});
