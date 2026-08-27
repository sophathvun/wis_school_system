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
                    <form method="POST" action="/login" id="authLoginForm">
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
                        <div class="mb-3 d-none" id="emailInputGroup">
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

    <script>
        (() => {
            const card = document.getElementById('authFlipCard');
            const loginBy = document.getElementById('loginBy');
            const identifier = document.getElementById('loginIdentifier');
            const usernameGroup = document.getElementById('usernameInputGroup');
            const emailGroup = document.getElementById('emailInputGroup');
            const usernameInput = document.getElementById('usernameIdentifier');
            const emailInput = document.getElementById('emailIdentifier');
            const title = document.getElementById('authTitle');
            const subtitle = document.getElementById('authSubtitle');
            const switchTitle = document.getElementById('switchTitle');
            const switchText = document.getElementById('switchText');
            const switchButton = document.getElementById('switchAuthMode');
            const form = document.getElementById('authLoginForm');
            const password = document.getElementById('authPassword');
            const togglePassword = document.getElementById('togglePassword');
            const themeToggle = document.getElementById('authThemeToggle');
            const updateThemeToggle = () => {
                const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
                themeToggle.innerHTML =
                    `<i class="ti ti-${dark ? 'sun' : 'moon'}" aria-hidden="true"></i><span>${dark ? 'Light mode' : 'Night mode'}</span>`;
                themeToggle.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to night mode');
                themeToggle.title = dark ? 'Switch to light mode' : 'Switch to night mode';
            };
            let mode = @json(old('login_by', 'username')) === 'email' ? 'email' : 'username';
            const render = () => {
                const email = mode === 'email';
                card.classList.toggle('is-email', email);
                card.classList.toggle('is-username', !email);
                usernameGroup.classList.toggle('d-none', email);
                emailGroup.classList.toggle('d-none', !email);
                title.textContent = email ? 'Email Login' : 'Username Login';
                subtitle.textContent = email ? 'Sign in with your school system email.' :
                    'Sign in with your school system username.';
                switchTitle.textContent = email ? 'Use username instead?' : 'Use email instead?';
                switchText.textContent = email ? 'Flip the form and sign in with your username.' :
                    'Flip the form and sign in with your email address.';
                switchButton.innerHTML = email ? 'Username Login <i class="ti ti-arrow-left ms-1"></i>' :
                    'Email Login <i class="ti ti-arrow-right ms-1"></i>';
                (email ? emailInput : usernameInput).focus({
                    preventScroll: true
                });
            };
            switchButton.addEventListener('click', () => {
                mode = mode === 'email' ? 'username' : 'email';
                render();
            });
            togglePassword.addEventListener('click', () => {
                const visible = password.type === 'text';
                password.type = visible ? 'password' : 'text';
                togglePassword.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
                togglePassword.title = visible ? 'Show password' : 'Hide password';
                togglePassword.innerHTML = `<i class="ti ti-${visible ? 'eye' : 'eye-off'}"></i>`;
            });
            themeToggle.addEventListener('click', () => {
                const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
                const next = dark ? 'light' : 'dark';
                document.documentElement.setAttribute('data-bs-theme', next);
                window.localStorage.setItem('tabler-theme', next);
                updateThemeToggle();
            });
            form.addEventListener('submit', () => {
                loginBy.value = mode;
                identifier.value = mode === 'email' ? emailInput.value : usernameInput.value;
            });
            updateThemeToggle();
            render();
        })();
    </script>
    @vite('resources/css/pages/auth-login.css')
@endsection
