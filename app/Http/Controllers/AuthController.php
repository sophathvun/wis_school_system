<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

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

    public function profile() { return view('profile'); }

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
}
