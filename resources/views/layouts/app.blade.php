<?php $branding = \App\Models\BrandingSetting::current(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if($branding->favicon_path)<link rel="icon" href="{{ asset('storage/'.$branding->favicon_path) }}">@endif
    <title>@yield('title', 'School System')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    @if(auth()->check())
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
    @include('layouts.partials.setting')
    @php
        $permissionUser = auth()->user();
        $permissionCodes = $permissionUser->isSuperAdmin() ? ['*'] : collect()
            ->merge($permissionUser->permissionOverrides()->wherePivot('allowed', true)->pluck('code'))
            ->merge($permissionUser->roles()->with('permissions')->get()->flatMap(fn ($role) => $role->permissions->pluck('code')))
            ->merge($permissionUser->department?->permissions?->pluck('code') ?? [])
            ->unique()->values()->all();
    @endphp
    <script>window.userPermissions = @json($permissionCodes);</script>
    @endif
    @if(!auth()->check())
        @yield('content')
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const pageHeader = document.querySelector('.page-header');
            const card = document.querySelector('.page-body .card');
            const cardHeader = card?.querySelector('.card-header');
            const actions = pageHeader?.querySelector('.col-auto');

            if (pageHeader && cardHeader) {
                cardHeader.classList.add('d-flex', 'align-items-center');
                if (actions) {
                    actions.classList.add('ms-auto');
                    cardHeader.appendChild(actions);
                }
                pageHeader.remove();
            }
        });
    </script>
    <script>
        (() => {
            const normalizeActions = () => document.querySelectorAll('button, a').forEach((element) => {
                if (element.dataset.actionNormalized) return;
                const text = element.textContent.trim().replace(/\s+/g, ' ');
                const action = text === 'Edit' ? 'edit' : text === 'Delete' ? 'delete' : null;
                if (!action) return;
                element.dataset.actionNormalized = 'true';
                element.classList.remove('btn-primary', 'btn-danger');
                element.classList.add('btn-outline-' + (action === 'edit' ? 'primary' : 'danger'), 'btn-sm');
                element.setAttribute('title', action === 'edit' ? 'Edit' : 'Delete');
                element.setAttribute('aria-label', action === 'edit' ? 'Edit' : 'Delete');
                element.innerHTML = '<i class="ti ti-' + (action === 'edit' ? 'edit' : 'trash') + '"></i>';
            });
            document.addEventListener('DOMContentLoaded', () => {
                normalizeActions();
                const normalizeSearches = () => document.querySelectorAll('input').forEach((input) => {
                    if (input.dataset.searchNormalized || input.closest('.input-icon') || input.closest('.location-combobox')) return;
                    const isSearch = input.id.toLowerCase().includes('search') || (input.placeholder || '').toLowerCase().includes('search');
                    if (!isSearch) return;
                    input.dataset.searchNormalized = 'true';
                    input.classList.add('form-control-sm');
                    const wrapper = document.createElement('div');
                    wrapper.className = 'input-icon';
                    wrapper.innerHTML = '<span class="input-icon-addon"><i class="ti ti-search icon"></i></span>';
                    input.parentElement.insertBefore(wrapper, input);
                    wrapper.appendChild(input);
                });
                normalizeSearches();
                new MutationObserver(() => { normalizeActions(); normalizeSearches(); }).observe(document.body, { childList: true, subtree: true });
            });
        })();
    </script>
    @stack('scripts')
</body>

</html>
