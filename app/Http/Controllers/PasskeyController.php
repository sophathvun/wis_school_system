<?php

namespace App\Http\Controllers;

use App\Models\WebauthnCredential;
use App\Services\WebauthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PasskeyController
{
    private const REGISTRATION_SESSION_KEY = 'passkeys.registration';

    private const AUTHENTICATION_SESSION_KEY = 'passkeys.authentication';

    public function registrationOptions(Request $request, WebauthnService $webauthn): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'current_password' => ['required', 'current_password'],
        ]);
        $result = $webauthn->registrationOptions($request->user()->load('webauthnCredentials'), $request);
        $request->session()->put(self::REGISTRATION_SESSION_KEY, $result['ceremony'] + [
            'name' => trim($data['name']),
            'expires_at' => $this->ceremonyExpiresAt(),
        ]);

        return response()->json(['publicKey' => $result['publicKey']]);
    }

    public function register(Request $request, WebauthnService $webauthn): JsonResponse
    {
        $data = $request->validate(['credential' => ['required', 'array']]);
        $ceremony = $request->session()->pull(self::REGISTRATION_SESSION_KEY);
        if (! $this->ceremonyIsValid($ceremony)) {
            throw ValidationException::withMessages(['passkey' => 'The passkey request expired. Please start again.']);
        }

        try {
            $credential = $webauthn->register($request->user(), $data['credential'], $ceremony, $ceremony['name']);
        } catch (\Throwable $exception) {
            Log::warning('Passkey registration failed.', ['user_id' => $request->user()->id, 'exception' => $exception]);
            throw ValidationException::withMessages(['passkey' => 'The passkey could not be verified. Please try again.']);
        }

        return response()->json([
            'message' => 'Face or passkey login was added successfully.',
            'passkey' => [
                'id' => $credential->id,
                'name' => $credential->name,
                'created_at' => $credential->created_at?->format('d-M-Y H:i'),
            ],
        ]);
    }

    public function authenticationOptions(Request $request, WebauthnService $webauthn): JsonResponse
    {
        $result = $webauthn->authenticationOptions($request);
        $request->session()->put(
            self::AUTHENTICATION_SESSION_KEY,
            $result['ceremony'] + ['expires_at' => $this->ceremonyExpiresAt()],
        );

        return response()->json(['publicKey' => $result['publicKey']]);
    }

    public function authenticate(Request $request, WebauthnService $webauthn): JsonResponse
    {
        $data = $request->validate([
            'credential' => ['required', 'array'],
            'remember' => ['nullable', 'boolean'],
        ]);
        $ceremony = $request->session()->pull(self::AUTHENTICATION_SESSION_KEY);
        if (! $this->ceremonyIsValid($ceremony)) {
            throw ValidationException::withMessages(['passkey' => 'The passkey request expired. Please start again.']);
        }

        try {
            $credential = $webauthn->authenticate($data['credential'], $ceremony);
        } catch (\Throwable $exception) {
            Log::warning('Passkey authentication failed.', ['exception' => $exception]);
            throw ValidationException::withMessages(['passkey' => 'The passkey was not recognized. Try again or use your password.']);
        }

        $user = $credential->user;
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return response()->json([
            'message' => 'Signed in successfully.',
            'redirect' => $user->must_change_password
                ? route('profile').'#profile-edit'
                : route('dashboard'),
        ]);
    }

    public function destroy(Request $request, WebauthnCredential $passkey): JsonResponse
    {
        abort_unless((int) $passkey->user_id === (int) $request->user()->id, 404);
        $passkey->delete();

        return response()->json(['message' => 'Passkey removed successfully.']);
    }

    private function ceremonyExpiresAt(): int
    {
        $timeout = max(15_000, min(120_000, (int) config('passkeys.timeout', 60_000)));

        return now()->addMilliseconds($timeout + 30_000)->getTimestamp();
    }

    private function ceremonyIsValid(mixed $ceremony): bool
    {
        return is_array($ceremony)
            && isset($ceremony['expires_at'])
            && (int) $ceremony['expires_at'] >= now()->getTimestamp();
    }
}
