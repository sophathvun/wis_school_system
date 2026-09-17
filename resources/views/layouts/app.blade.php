@php
    $branding = \App\Models\BrandingSetting::current();
    $permissionUser = auth()->user();
    $permissionCodes = [];

    if ($permissionUser) {
        $permissionCodes = $permissionUser->isSuperAdmin()
            ? ['*']
            : collect()
                ->merge($permissionUser->permissionOverrides()->wherePivot('allowed', true)->pluck('code'))
                ->merge(
                    $permissionUser
                        ->roles()
                        ->with('permissions')
                        ->get()
                        ->flatMap(fn($role) => $role->permissions->pluck('code')),
                )
                ->merge($permissionUser->department?->permissions?->pluck('code') ?? [])
                ->unique()
                ->values()
                ->all();
    }
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#206bc4">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="WIS School">
    <link rel="manifest" href="{{ route('app.manifest') }}">
    @php($shortcutIcon = $branding->shortcut_icon_path ?: $branding->login_logo_path ?: $branding->favicon_path ?: $branding->sidebar_logo_path)
    @if ($branding->favicon_path)
        <link rel="icon" href="{{ asset('storage/' . $branding->favicon_path) }}">
    @endif
    <link rel="apple-touch-icon" href="{{ route('app.icon', ['v' => $shortcutIcon ? substr(md5($shortcutIcon), 0, 10) : 'default']) }}">
    <title>@yield('title', 'School System')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body data-user-permissions='@json($permissionCodes ?? [])'
    data-current-user-id="{{ $permissionUser->id ?? '' }}"
    data-web-push-public-key="{{ config('webpush.vapid.public_key') }}">
    @if (auth()->check())
        <div class="page">
            @include('layouts.partials.sidebar')
            @include('layouts.partials.navbar')
            <div class="page-wrapper">
                @hasSection('page-header')
                    <div class="page-header d-print-none" aria-label="Page header">
                        @yield('page-header')
                    </div>
                @endif
                <div class="page-body">
                    <div class="container-fluid">
                        @yield('content')
                    </div>
                </div>
                @include('layouts.partials.footer')
            </div>
        </div>
        @include('layouts.partials.chat-widget')
        <button type="button" id="installAppShortcut" class="install-app-shortcut d-none">
            <i class="ti ti-device-mobile-plus"></i>
            <span>Add Shortcut</span>
        </button>
    @endif
    @if (!auth()->check())
        @yield('content')
    @endif
    @stack('scripts')
</body>

</html>
