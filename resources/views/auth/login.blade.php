@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
@php($branding = \App\Models\BrandingSetting::current())
<style>
    .auth-flip-page {
        min-height: 100vh;
        display: grid;
        place-items: center;
        padding: 2rem 1rem;
        background: radial-gradient(circle at 12% 12%, rgba(65, 105, 190, .32), transparent 30rem), linear-gradient(135deg, #071a3d 0%, #0b2d62 55%, #061733 100%);
        perspective: 1600px;
    }
    .auth-flip-card {
        position: relative;
        width: min(100%, 860px);
        min-height: 500px;
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        overflow: hidden;
        border-radius: 24px;
        background: #fff;
        box-shadow: 0 22px 60px rgba(30, 41, 59, .18);
    }
    .auth-panel {
        min-width: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2.5rem;
    }
    .auth-form-panel { background: #fff; }
    .auth-form-content { width: min(100%, 340px); }
    .auth-brand-logo { display: flex; justify-content: center; margin-bottom: 1rem; }
    .auth-brand-logo img { width: auto; height: 76px; max-width: min(220px, 75%); object-fit: contain; }
    .auth-form-content h1 { margin-bottom: .5rem; font-size: clamp(1.8rem, 3vw, 2.35rem); font-weight: 800; text-align: center; }
    .auth-form-content > p { margin-bottom: 1.5rem; color: #667085; text-align: center; }
    .auth-form-panel .form-control { min-height: 50px; border: 0; border-radius: 10px; background: #f0f2f5; }
    .auth-input-wrap { position: relative; }
    .auth-input-wrap > .ti { position: absolute; z-index: 1; top: 50%; left: 1rem; color: #98a2b3; font-size: 1.1rem; transform: translateY(-50%); pointer-events: none; }
    .auth-input-wrap .form-control { padding-left: 2.8rem; }
    .auth-input-wrap .auth-password-toggle { position: absolute; z-index: 2; top: 50%; right: .7rem; display: grid; width: 2rem; height: 2rem; padding: 0; place-items: center; border: 0; color: #98a2b3; background: transparent; transform: translateY(-50%); }
    .auth-input-wrap .auth-password-toggle:hover { color: #ff4b2b; background: transparent; }
    .auth-input-wrap .auth-password { padding-right: 3rem; }
    .auth-form-panel .form-control:focus { background: #fff; box-shadow: 0 0 0 3px rgba(255, 75, 43, .16); }
    .auth-submit { width: 100%; margin-top: .5rem; padding: .8rem 1rem; border: 1px solid #ff4b2b; border-radius: 999px; color: #fff; background: #ff4b2b; font-weight: 700; letter-spacing: .04em; transition: transform .15s ease, box-shadow .15s ease; }
    .auth-submit:hover { color: #fff; box-shadow: 0 8px 18px rgba(255, 75, 43, .24); transform: translateY(-1px); }
    .auth-switch-panel { position: relative; overflow: hidden; color: #fff; background: linear-gradient(135deg, #fc4f4f, #ffcf00); }
    .auth-switch-panel::before, .auth-switch-panel::after { content: ''; position: absolute; border-radius: 50%; background: rgba(255, 255, 255, .12); }
    .auth-switch-panel::before { width: 280px; height: 280px; top: -130px; right: -110px; }
    .auth-switch-panel::after { width: 180px; height: 180px; bottom: -85px; left: -70px; }
    .auth-switch-content { position: relative; z-index: 1; width: min(100%, 300px); text-align: center; }
    .auth-switch-content .ti { display: block; margin-bottom: 1rem; font-size: 2rem; }
    .auth-switch-content h2 { font-weight: 800; }
    .auth-switch-content p { margin: 1rem 0 1.75rem; color: rgba(255, 255, 255, .9); }
    .auth-switch-button { padding: .75rem 1.7rem; border: 1px solid rgba(255, 255, 255, .9); border-radius: 999px; color: #fff; background: transparent; font-weight: 700; }
    .auth-switch-button:hover { color: #fc4f4f; background: #fff; }
    .auth-flip-card.is-email .auth-form-panel { animation: auth-form-to-email .55s ease both; }
    .auth-flip-card.is-username .auth-form-panel { animation: auth-form-to-username .55s ease both; }
    @keyframes auth-form-to-email { 0% { transform: rotateY(0); opacity: 1; } 45% { transform: rotateY(88deg); opacity: .15; } 55% { transform: rotateY(-88deg); opacity: .15; } 100% { transform: rotateY(0); opacity: 1; } }
    @keyframes auth-form-to-username { 0% { transform: rotateY(0); opacity: 1; } 45% { transform: rotateY(-88deg); opacity: .15; } 55% { transform: rotateY(88deg); opacity: .15; } 100% { transform: rotateY(0); opacity: 1; } }
    .auth-mode-note { min-height: 1.25rem; margin-top: .75rem; color: #667085; font-size: .82rem; text-align: center; }
    @media (max-width: 767.98px) {
        .auth-flip-page { min-height: 100vh; padding: 1rem .5rem; }
        .auth-flip-card { display: block; min-height: 0; border-radius: 20px; }
        .auth-panel { padding: 2rem 1.25rem; }
        .auth-switch-panel { min-height: 210px; }
        .auth-switch-content p { margin: .65rem 0 1rem; }
    }
</style>

<div class="auth-flip-page">
    <div class="auth-flip-card is-username" id="authFlipCard">
        <section class="auth-panel auth-form-panel">
            <div class="auth-form-content">
                @php($loginLogo = $branding->login_logo_path ?: $branding->favicon_path)
                @if($loginLogo)<div class="auth-brand-logo"><img src="{{ asset('storage/'.$loginLogo) }}" alt="School logo"></div>@endif
                <h1 id="authTitle">Username Login</h1>
                <p id="authSubtitle">Sign in with your school system username.</p>
                @if(session('success'))<div class="alert alert-success" role="status"><i class="ti ti-circle-check me-1"></i>{{ session('success') }}</div>@endif
                @if(session('error') || $errors->any())<div class="alert alert-danger d-flex align-items-start gap-2" role="alert" aria-live="assertive"><i class="ti ti-alert-circle mt-1"></i><span>@if(session('error')){{ session('error') }}@else<ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif</span></div>@endif
                <form method="POST" action="/login" id="authLoginForm">
                    @csrf
                    <input type="hidden" name="login_by" id="loginBy" value="username">
                    <input type="hidden" name="identifier" id="loginIdentifier">
                    <div class="mb-3" id="usernameInputGroup">
                        <label class="form-label" for="usernameIdentifier">Username</label>
                        <div class="auth-input-wrap"><i class="ti ti-user" aria-hidden="true"></i><input class="form-control" id="usernameIdentifier" type="text" value="{{ old('login_by') === 'username' ? old('identifier') : '' }}" autocomplete="username"></div>
                    </div>
                    <div class="mb-3 d-none" id="emailInputGroup">
                        <label class="form-label" for="emailIdentifier">Email address</label>
                        <div class="auth-input-wrap"><i class="ti ti-mail" aria-hidden="true"></i><input class="form-control" id="emailIdentifier" type="email" value="{{ old('login_by') === 'email' ? old('identifier') : '' }}" autocomplete="email"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="authPassword">Password</label>
                        <div class="auth-input-wrap"><i class="ti ti-lock" aria-hidden="true"></i><input class="form-control auth-password" id="authPassword" type="password" name="password" required autocomplete="current-password"><button class="auth-password-toggle" type="button" id="togglePassword" aria-label="Show password" title="Show password"><i class="ti ti-eye"></i></button></div>
                    </div>
                    <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remember"><span class="form-check-label">Remember me</span></label>
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
                <button class="auth-switch-button" type="button" id="switchAuthMode">Email Login <i class="ti ti-arrow-right ms-1"></i></button>
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
        let mode = @json(old('login_by', 'username')) === 'email' ? 'email' : 'username';
        const render = () => {
            const email = mode === 'email';
            card.classList.toggle('is-email', email);
            card.classList.toggle('is-username', !email);
            usernameGroup.classList.toggle('d-none', email);
            emailGroup.classList.toggle('d-none', !email);
            title.textContent = email ? 'Email Login' : 'Username Login';
            subtitle.textContent = email ? 'Sign in with your school system email.' : 'Sign in with your school system username.';
            switchTitle.textContent = email ? 'Use username instead?' : 'Use email instead?';
            switchText.textContent = email ? 'Flip the form and sign in with your username.' : 'Flip the form and sign in with your email address.';
            switchButton.innerHTML = email ? 'Username Login <i class="ti ti-arrow-left ms-1"></i>' : 'Email Login <i class="ti ti-arrow-right ms-1"></i>';
            (email ? emailInput : usernameInput).focus({ preventScroll: true });
        };
        switchButton.addEventListener('click', () => { mode = mode === 'email' ? 'username' : 'email'; render(); });
        togglePassword.addEventListener('click', () => {
            const visible = password.type === 'text';
            password.type = visible ? 'password' : 'text';
            togglePassword.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
            togglePassword.title = visible ? 'Show password' : 'Hide password';
            togglePassword.innerHTML = `<i class="ti ti-${visible ? 'eye' : 'eye-off'}"></i>`;
        });
        form.addEventListener('submit', () => { loginBy.value = mode; identifier.value = mode === 'email' ? emailInput.value : usernameInput.value; });
        render();
    })();
</script>
@endsection
