<?php

use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('webauthn_credentials');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('username')->nullable();
        $table->string('email')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->boolean('status')->default(true);
        $table->boolean('must_change_password')->default(false);
        $table->timestamp('last_seen_at')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('webauthn_credentials', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('name', 120);
        $table->string('credential_id_hash', 64)->unique();
        $table->text('credential_id');
        $table->longText('credential_record');
        $table->timestamp('last_used_at')->nullable();
        $table->timestamps();
    });
});

function passkeyUser(array $attributes = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Passkey User',
        'username' => 'passkey.user',
        'email' => 'passkey@example.test',
        'password' => Hash::make('CurrentPassword123!'),
        'status' => 1,
    ], $attributes));
}

it('returns discoverable passkey login options that require user verification', function () {
    $this->postJson(route('passkeys.login.options'))
        ->assertOk()
        ->assertJsonPath('publicKey.rpId', 'localhost')
        ->assertJsonPath('publicKey.userVerification', 'required')
        ->assertJsonPath('publicKey.allowCredentials', [])
        ->assertJsonStructure(['publicKey' => ['challenge', 'timeout']]);

    expect(session('passkeys.authentication'))->toBeArray()
        ->and(session('passkeys.authentication.rp_id'))->toBe('localhost')
        ->and(session('passkeys.authentication.origin'))->toBe('http://localhost');
});

it('requires the current password before creating registration options', function () {
    $user = passkeyUser();

    $this->actingAs($user)->postJson(route('profile.passkeys.options'), [
        'name' => 'Office laptop',
        'current_password' => 'wrong-password',
    ])->assertUnprocessable()->assertJsonStructure(['errors' => ['current_password']]);

    $this->actingAs($user)->postJson(route('profile.passkeys.options'), [
        'name' => 'Office laptop',
        'current_password' => 'CurrentPassword123!',
    ])->assertOk()
        ->assertJsonPath('publicKey.rp.id', 'localhost')
        ->assertJsonPath('publicKey.user.name', 'passkey.user')
        ->assertJsonPath('publicKey.authenticatorSelection.residentKey', 'required')
        ->assertJsonPath('publicKey.authenticatorSelection.userVerification', 'required');

    expect(session('passkeys.registration.name'))->toBe('Office laptop');
});

it('stores credential records encrypted and limits removal to their owner', function () {
    $owner = passkeyUser();
    $other = passkeyUser([
        'username' => 'another.user',
        'email' => 'another@example.test',
    ]);
    $credential = $owner->webauthnCredentials()->create([
        'name' => 'Windows Hello',
        'credential_id_hash' => hash('sha256', 'credential-id'),
        'credential_id' => 'credential-id',
        'credential_record' => '{"public":"credential"}',
    ]);

    expect(DB::table('webauthn_credentials')->whereKey($credential->id)->value('credential_record'))
        ->not->toBe('{"public":"credential"}');

    $this->actingAs($other)
        ->deleteJson(route('profile.passkeys.destroy', $credential))
        ->assertNotFound();
    expect(WebauthnCredential::query()->whereKey($credential->id)->exists())->toBeTrue();

    $this->actingAs($owner)
        ->deleteJson(route('profile.passkeys.destroy', $credential))
        ->assertOk()
        ->assertJsonPath('message', 'Passkey removed successfully.');
    expect(WebauthnCredential::query()->whereKey($credential->id)->exists())->toBeFalse();
});

it('rejects a passkey response when its ceremony has expired', function () {
    $this->withSession([
        'passkeys.authentication' => [
            'options' => '{}',
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'expires_at' => now()->subSecond()->getTimestamp(),
        ],
    ])->postJson(route('passkeys.login'), [
        'credential' => ['id' => 'expired'],
    ])->assertUnprocessable()->assertJsonStructure(['errors' => ['passkey']]);
});
