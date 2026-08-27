<?php

namespace App\Http\Controllers;

use App\Models\BrandingSetting;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController
{
    public function setupForm()
    {
        abort_if(User::query()->exists(), 404);
        return view('auth.setup-admin');
    }

    public function setupAdmin(Request $request)
    {
        abort_if(User::query()->exists(), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:80', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);
        DB::transaction(function () use ($data) {
            $user = User::create($data + ['status' => 1, 'login_identifier' => 'both', 'is_global' => true]);
            $role = Role::where('code', 'super-admin')->firstOrFail();
            DB::table('access_user_roles')->insert([
                'user_id' => $user->id, 'role_id' => $role->id, 'campus_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return redirect()->route('login')->with('success', 'Super Administrator account created. You can now sign in.');
    }

    public function loginForm() { return view('auth.login'); }

    public function login(Request $request)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'login_by' => ['required', 'in:username,email'],
        ]);
        $user = User::where($data['login_by'], $data['identifier'])->where('status', 1)->first();
        $loginErrors = [];
        if (!$user || !in_array($user->login_identifier, [$data['login_by'], 'both'], true)) {
            $loginErrors['identifier'] = $data['login_by'] === 'email'
                ? 'The email address is incorrect or is not registered.'
                : 'The username is incorrect or is not registered.';
        }
        if (!$user || !Hash::check($data['password'], $user->password)) {
            $loginErrors['password'] = 'The password is incorrect.';
        }
        if (!$loginErrors) {
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();
            return $user->must_change_password
                ? redirect()->route('profile')->with('warning', 'Please change your temporary password before continuing.')
                : redirect()->intended(route('dashboard'));
        }
        return back()->withErrors($loginErrors)->withInput();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function forgotForm() { return view('auth.forgot-password'); }

    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($data);
        return back()->with($status === Password::RESET_LINK_SENT ? 'success' : 'error', __($status));
    }

    public function resetForm(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'min:8', 'confirmed']]);
        $status = Password::reset($data, function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => null, 'must_change_password' => false])->save();
        });
        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password reset successfully. You can now sign in.')
            : back()->withErrors(['email' => __($status)]);
    }

    public function profile(Request $request)
    {
        $user = $request->user()->load(['department', 'position', 'campuses', 'roles']);
        $this->ensurePublicCardToken($user);

        return view('profile', [
            'profileUser' => $user,
            'branding' => BrandingSetting::current(),
            'publicCardUrl' => route('staff-card.public', $user->public_card_token),
            'publicCardQrUrl' => route('staff-card.qr', $user->public_card_token),
            'publicCardVcardUrl' => route('staff-card.vcard', $user->public_card_token),
        ]);
    }

    public function status(Request $request) { return view('profile-status'); }

    public function feedbackForm() { return view('auth.feedback'); }

    public function feedback(Request $request)
    {
        $request->validate(['subject' => ['required', 'string', 'max:120'], 'message' => ['required', 'string', 'max:2000']]);
        return back()->with('success', 'Thank you. Your feedback has been submitted.');
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'username' => ['required', 'string', 'max:80', 'unique:users,username,'.$user->id], 'email' => ['required', 'email', 'unique:users,email,'.$user->id], 'password' => ['nullable', 'min:8', 'confirmed'], 'photo' => ['nullable', 'image', 'max:2048']]);
        $user->fill(collect($data)->except(['password', 'photo'])->toArray());
        if (!empty($data['password'])) {
            $user->password = $data['password'];
            $user->must_change_password = false;
        }
        if ($request->hasFile('photo')) $user->photo_path = $request->file('photo')->store('users', 'public');
        $user->save();
        return back()->with('success', 'Profile updated successfully.');
    }

    public function updateNameCard(Request $request)
    {
        $user = $request->user();
        $this->ensurePublicCardToken($user);

        $user->forceFill([
            'public_card_enabled' => $request->has('public_card_enabled')
                ? $request->boolean('public_card_enabled')
                : (bool) $user->public_card_enabled,
            'public_card_orientation' => $request->validate([
                'public_card_orientation' => ['nullable', 'in:portrait,landscape'],
                'public_card_background' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ])['public_card_orientation'] ?? ($user->public_card_orientation ?: 'landscape'),
            'public_card_background' => $request->input('public_card_background', $user->public_card_background ?: '#206bc4'),
        ])->save();

        return redirect()->to(route('profile') . '#name-card')
            ->with('success', 'Name card setting updated successfully.');
    }

    public function regenerateNameCard(Request $request)
    {
        $request->user()->forceFill([
            'public_card_token' => $this->newPublicCardToken(),
            'public_card_enabled' => true,
            'public_card_scan_count' => 0,
            'public_card_last_viewed_at' => null,
        ])->save();

        return back()->with('success', 'Name card QR code regenerated successfully.');
    }

    public function publicStaffCard(string $token)
    {
        $staff = $this->publicStaffByToken($token);

        $staff->forceFill([
            'public_card_scan_count' => ((int) $staff->public_card_scan_count) + 1,
            'public_card_last_viewed_at' => now(),
        ])->save();

        return view('staff-card-public', [
            'staff' => $staff,
            'branding' => BrandingSetting::current(),
            'publicCardUrl' => route('staff-card.public', $staff->public_card_token),
            'publicCardVcardUrl' => route('staff-card.vcard', $staff->public_card_token),
            'publicCardQrUrl' => route('staff-card.qr', $staff->public_card_token),
        ]);
    }

    public function staffCardQr(string $token)
    {
        $staff = $this->publicStaffByToken($token);

        return response($this->qrSvg(route('staff-card.public', $staff->public_card_token)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function staffCardVcard(string $token)
    {
        $staff = $this->publicStaffByToken($token);
        $filename = Str::slug($staff->name ?: 'staff-contact') . '.vcf';

        return response($this->staffVcard($staff), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function ensurePublicCardToken(User $user): void
    {
        if ($user->public_card_token) {
            return;
        }

        $user->forceFill([
            'public_card_token' => $this->newPublicCardToken(),
            'public_card_enabled' => true,
        ])->save();
    }

    private function newPublicCardToken(): string
    {
        do {
            $token = Str::random(40);
        } while (User::where('public_card_token', $token)->exists());

        return $token;
    }

    private function publicStaffByToken(string $token): User
    {
        return User::with(['department', 'position', 'campuses'])
            ->where('public_card_token', $token)
            ->where('public_card_enabled', true)
            ->where('status', 1)
            ->firstOrFail();
    }

    private function qrSvg(string $value): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(360),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($value);
    }

    private function staffVcard(User $staff): string
    {
        $campus = $staff->campuses->pluck('campus_name_en')->filter()->join(', ');
        $organization = trim('Western International School' . ($campus ? ' - ' . $campus : ''));

        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:' . $this->vcardValue($staff->name),
            'ORG:' . $this->vcardValue($organization),
            'TITLE:' . $this->vcardValue($staff->position?->name ?: $staff->department?->name),
        ];

        if ($staff->phone) {
            $lines[] = 'TEL;TYPE=WORK,VOICE:' . $this->vcardValue($staff->phone);
        }

        if ($staff->email) {
            $lines[] = 'EMAIL;TYPE=WORK:' . $this->vcardValue($staff->email);
        }

        $lines[] = 'URL:' . route('staff-card.public', $staff->public_card_token);
        $lines[] = 'NOTE:' . $this->vcardValue(trim(($staff->department?->name ?: '') . ($campus ? ' | ' . $campus : '')));
        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function vcardValue(?string $value): string
    {
        return str_replace(["\\", "\n", "\r", ',', ';'], ['\\\\', '\\n', '', '\\,', '\\;'], trim((string) $value));
    }
}
