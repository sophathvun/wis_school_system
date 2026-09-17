@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
    @php($branding = \App\Models\BrandingSetting::current())


    <div class="auth-flip-page">
        <button class="auth-theme-toggle" type="button" id="authThemeToggle" aria-label="Switch theme" title="Switch theme"><i
                class="ti ti-moon" aria-hidden="true"></i><span>Night mode</span></button>
        <div class="auth-flip-card is-username" id="authFlipCard">
            <section class="auth-panel auth-form-panel">
                <div class="auth-form-content">
                    @php($loginLogo = $branding->login_logo_path ?: $branding->favicon_path)
                    @if ($loginLogo)
                        <div class="auth-brand-logo"><img src="{{ asset('storage/' . $loginLogo) }}" alt="School logo"></div>
                    @endif
                    <h1 id="authTitle">Username Login</h1>
                    <p id="authSubtitle">Sign in with your school system username.</p>
                    @if (session('success'))
                        <div class="alert alert-success" role="status"><i
                                class="ti ti-circle-check me-1"></i>{{ session('success') }}</div>
                    @endif
                    @if (session('error') || $errors->any())
                        <div class="alert alert-danger d-flex align-items-start gap-2" role="alert" aria-live="assertive">
                            <i class="ti ti-alert-circle mt-1"></i><span>
                                @if (session('error')){{ session('error') }}
                                @else
                                    <ul class="mb-0 ps-3">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </span></div>
                    @endif
                    <form method="POST" action="/login" id="authLoginForm"
                        data-login-mode="{{ old('login_by', 'username') === 'email' ? 'email' : 'username' }}">
                        @csrf
                        <input type="hidden" name="login_by" id="loginBy" value="username">
                        <input type="hidden" name="identifier" id="loginIdentifier">
                        <div class="mb-3" id="usernameInputGroup">
                            <label class="form-label" for="usernameIdentifier">Username</label>
                            <div class="auth-input-wrap"><i class="ti ti-user" aria-hidden="true"></i><input
                                    class="form-control" id="usernameIdentifier" type="text"
                                    value="{{ old('login_by') === 'username' ? old('identifier') : '' }}"
                                    autocomplete="username"></div>
                        </div>
                        <div class="mb-3" id="emailInputGroup">
                            <label class="form-label" for="emailIdentifier">Email address</label>
                            <div class="auth-input-wrap"><i class="ti ti-mail" aria-hidden="true"></i><input
                                    class="form-control" id="emailIdentifier" type="email"
                                    value="{{ old('login_by') === 'email' ? old('identifier') : '' }}" autocomplete="email">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="authPassword">Password</label>
                            <div class="auth-input-wrap"><i class="ti ti-lock" aria-hidden="true"></i><input
                                    class="form-control auth-password" id="authPassword" type="password" name="password"
                                    required autocomplete="current-password"><button class="auth-password-toggle"
                                    type="button" id="togglePassword" aria-label="Show password" title="Show password"><i
                                        class="ti ti-eye"></i></button></div>
                        </div>
                        <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remember"><span
                                class="form-check-label">Remember me</span></label>
                        <button class="auth-submit" type="submit">Sign in</button>
                        <div class="auth-mode-note"><a href="{{ route('password.request') }}">Forgot password?</a></div>
                    </form>
                </div>
            </section>
            <section class="auth-panel auth-switch-panel">
                <div class="auth-switch-content">
                    <i class="ti ti-arrows-left-right"></i>
                    <h2 id="switchTitle">Use email instead?</h2>
                    <p id="switchText">Flip the form and sign in with your email address.</p>
                    <button class="auth-switch-button" type="button" id="switchAuthMode">Email Login <i
                            class="ti ti-arrow-right ms-1"></i></button>
                </div>
            </section>
        </div>
    </div>

    @vite('resources/js/authLogin.js')
    @vite('resources/css/pages/auth-login.css')
@endsection
