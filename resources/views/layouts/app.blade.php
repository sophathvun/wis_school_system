<?php $branding = \App\Models\BrandingSetting::current(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if ($branding->favicon_path)
        <link rel="icon" href="{{ asset('storage/' . $branding->favicon_path) }}">
    @endif
    <title>@yield('title', 'School System')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body data-user-permissions='@json($permissionCodes ?? [])'
    data-current-user-id="{{ $permissionUser->id ?? '' }}">
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
        @php
            $permissionUser = auth()->user();
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
        @endphp
    @endif
    @if (!auth()->check())
        @yield('content')
    @endif
    @stack('scripts')
</body>

</html>
