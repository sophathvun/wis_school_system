<header class="navbar navbar-expand-md sticky-top d-none d-lg-flex d-print-none">
    <div class="container-fluid">
        <!-- BEGIN NAVBAR TOGGLER -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu"
            aria-controls="navbar-menu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <!-- END NAVBAR TOGGLER -->
        <div class="navbar-nav flex-row order-md-last">
            <div class="d-none d-md-flex">
                <div class="nav-item">
                    <a href="{{ request()->fullUrlWithQuery(['theme' => 'dark']) }}" class="nav-link px-0 hide-theme-dark" title="Enable dark mode"
                        data-bs-toggle="tooltip" data-bs-placement="bottom">
                        <!-- Download SVG icon from http://tabler.io/icons/icon/moon -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" class="icon icon-1">
                            <path d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1 -8.313 -12.454z" />
                        </svg>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['theme' => 'light']) }}" class="nav-link px-0 hide-theme-light" title="Enable light mode"
                        data-bs-toggle="tooltip" data-bs-placement="bottom">
                        <!-- Download SVG icon from http://tabler.io/icons/icon/sun -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" class="icon icon-1">
                            <path d="M12 12m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" />
                            <path
                                d="M3 12h1m8 -9v1m8 8h1m-9 8v1m-6.4 -15.4l.7 .7m12.1 -.7l-.7 .7m0 11.4l.7 .7m-12.1 -.7l-.7 .7" />
                        </svg>
                    </a>
                </div>
                <div class="nav-item dropdown d-none d-md-flex">
                    <a href="#" class="nav-link px-0" data-bs-toggle="dropdown" tabindex="-1"
                        aria-label="Show notifications" data-bs-auto-close="outside" aria-expanded="false"
                        data-navbar-notification-toggle>
                        <!-- Download SVG icon from http://tabler.io/icons/icon/bell -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" class="icon icon-1">
                            <path
                                d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" />
                            <path d="M9 17v1a3 3 0 0 0 6 0v-1" />
                        </svg>
                        <span class="navbar-notification-badge" data-navbar-notification-badge></span>
                    </a>
                    @include('layouts.partials.notification-menu')
                </div>
            </div>
            <div class="nav-item ms-3">
                <a href="{{ route('chat.index') }}" class="nav-link px-0 position-relative" aria-label="Open chat"
                    title="Chat" data-navbar-chat-link>
                    <i class="ti ti-messages icon"></i>
                    <span id="chat-unread-badge"
                        class="navbar-notification-badge navbar-chat-badge d-none">0</span>
                </a>
            </div>
            <div class="nav-item dropdown">
                <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown"
                    aria-label="Open user menu">
                    <span class="avatar avatar-sm"
                        @auth @if (auth()->user()->photo_path) style="background-image: url('{{ asset('storage/' . auth()->user()->photo_path) }}')" @endif @endauth>
                    </span>
                    <div class="d-none d-xl-block ps-2">
                        <div>@auth{{ auth()->user()->name }}
                        @else
                        Guest @endauth
                    </div>
                    <div class="mt-1 small text-secondary">@auth{{ auth()->user()->position?->name ?? 'Staff' }}
                    @else
                    Sign in @endauth
                </div>
            </div>
        </a>
        @include('layouts.partials.profile-menu')
    </div>
</div>
<div class="collapse navbar-collapse" id="navbar-menu">
    <!-- BEGIN NAVBAR MENU -->
    @php
        $nav = match (true) {
            request()->routeIs('photo-editor.*') => [
                'parent' => null, 'label' => 'Photo Editor', 'icon' => 'ti-photo-edit',
                'url' => route('photo-editor.index'),
            ],
            request()->routeIs('dashboard') => [
                'parent' => null,
                'label' => 'Dashboard',
                'icon' => 'ti-dashboard',
                'url' => route('dashboard'),
            ],
            request()->routeIs('studentGraduation.*') => [
                'parent' => 'Student',
                'parentIcon' => 'ti-user',
                'parentUrl' => route('studentEnrollment.index'),
                'label' => 'Student Graduation',
                'icon' => 'ti-certificate',
                'url' => route('studentGraduation.index'),
            ],
            request()->routeIs('studentPromotion.*', 'student-enrollment-workflows.*') => [
                'parent' => 'Student',
                'parentIcon' => 'ti-user',
                'parentUrl' => route('studentEnrollment.index'),
                'label' => 'Promotion / Transfer',
                'icon' => 'ti-arrows-transfer-up',
                'url' => route('studentPromotion.index'),
            ],
            request()->routeIs('studentEnrollment.*') => [
                'parent' => 'Student',
                'parentIcon' => 'ti-user',
                'parentUrl' => route('studentEnrollment.index'),
                'label' => 'Student Enrollment',
                'icon' => 'ti-user-plus',
                'url' => route('studentEnrollment.index'),
            ],
            request()->routeIs('summer-school.*') => [
                'parent' => 'Student',
                'parentIcon' => 'ti-user',
                'parentUrl' => route('studentEnrollment.index'),
                'label' => 'Summer School',
                'icon' => 'ti-sun',
                'url' => route('summer-school.index'),
            ],
            request()->routeIs('student-reentry.*') => [
                'parent' => 'Student',
                'parentIcon' => 'ti-user',
                'parentUrl' => route('studentEnrollment.index'),
                'label' => 'Student Re-entry',
                'icon' => 'ti-user-check',
                'url' => route('student-reentry.index'),
            ],
            request()->routeIs('student-id-card-qr.*') => [
                'parent' => 'Student',
                'parentIcon' => 'ti-user',
                'parentUrl' => route('studentEnrollment.index'),
                'label' => 'Stu. ID Card (QR)',
                'icon' => 'ti-qrcode',
                'url' => route('student-id-card-qr.index'),
            ],
            request()->routeIs('families.*') => [
                'parent' => 'Student',
                'parentIcon' => 'ti-user',
                'parentUrl' => route('studentEnrollment.index'),
                'label' => 'Family Management',
                'icon' => 'ti-users',
                'url' => route('families.index'),
            ],
            request()->routeIs('searchStudent.*') => [
                'parent' => 'Student',
                'parentIcon' => 'ti-user',
                'parentUrl' => route('studentEnrollment.index'),
                'label' => 'Search Students',
                'icon' => 'ti-search',
                'url' => route('searchStudent.index'),
            ],
            request()->routeIs('transferStudent.*') => [
                'parent' => 'Student',
                'parentIcon' => 'ti-user',
                'parentUrl' => route('studentEnrollment.index'),
                'label' => 'Transfer Student',
                'icon' => 'ti-arrows-left-right',
                'url' => route('transferStudent.index'),
            ],
            request()->routeIs('withdrawStudent.*') => [
                'parent' => 'Student',
                'parentIcon' => 'ti-user',
                'parentUrl' => route('studentEnrollment.index'),
                'label' => 'Withdraw Student',
                'icon' => 'ti-user-minus',
                'url' => route('withdrawStudent.index'),
            ],
            request()->routeIs('student-data-transfer.*') => [
                'parent' => 'Student',
                'parentIcon' => 'ti-user',
                'parentUrl' => route('studentEnrollment.index'),
                'label' => 'Import / Export Data',
                'icon' => 'ti-file-import',
                'url' => route('student-data-transfer.index'),
            ],
            request()->routeIs('academic-years.*') => [
                'parent' => 'Settings',
                'parentIcon' => 'ti-settings',
                'parentUrl' => route('schoolInfo.index'),
                'label' => 'Academic Years',
                'icon' => 'ti-calendar',
                'url' => route('academic-years.index'),
            ],
            request()->routeIs('grades.*') => [
                'parent' => 'Settings',
                'parentIcon' => 'ti-settings',
                'parentUrl' => route('schoolInfo.index'),
                'label' => 'Grades',
                'icon' => 'ti-school',
                'url' => route('grades.index'),
            ],
            request()->routeIs('classes.*') => [
                'parent' => 'Settings',
                'parentIcon' => 'ti-settings',
                'parentUrl' => route('schoolInfo.index'),
                'label' => 'Classes',
                'icon' => 'ti-door',
                'url' => route('classes.index'),
            ],
            request()->routeIs('sessions.*') => [
                'parent' => 'Settings',
                'parentIcon' => 'ti-settings',
                'parentUrl' => route('schoolInfo.index'),
                'label' => 'Sessions',
                'icon' => 'ti-clock',
                'url' => route('sessions.index'),
            ],
            request()->routeIs('education-levels.*') => [
                'parent' => 'Settings',
                'parentIcon' => 'ti-settings',
                'parentUrl' => route('schoolInfo.index'),
                'label' => 'Education Levels',
                'icon' => 'ti-school',
                'url' => route('education-levels.index'),
            ],
            request()->routeIs('programs.*') => [
                'parent' => 'Settings',
                'parentIcon' => 'ti-settings',
                'parentUrl' => route('schoolInfo.index'),
                'label' => 'Programs',
                'icon' => 'ti-books',
                'url' => route('programs.index'),
            ],
            request()->routeIs('terms.*') => [
                'parent' => 'Settings',
                'parentIcon' => 'ti-settings',
                'parentUrl' => route('schoolInfo.index'),
                'label' => 'Terms / Quarters',
                'icon' => 'ti-calendar-event',
                'url' => route('terms.index'),
            ],
            request()->routeIs('schoolInfo.*') => [
                'parent' => 'Settings',
                'parentIcon' => 'ti-settings',
                'parentUrl' => route('schoolInfo.index'),
                'label' => 'School Information',
                'icon' => 'ti-building-community',
                'url' => route('schoolInfo.index'),
            ],
            request()->routeIs('campuses.*') => [
                'parent' => 'Settings',
                'parentIcon' => 'ti-settings',
                'parentUrl' => route('schoolInfo.index'),
                'label' => 'Campuses',
                'icon' => 'ti-building',
                'url' => route('campuses.index'),
            ],
            request()->routeIs('locations.*') => [
                'parent' => 'Settings',
                'parentIcon' => 'ti-settings',
                'parentUrl' => route('schoolInfo.index'),
                'label' => 'Locations',
                'icon' => 'ti-map-pin',
                'url' => route('locations.index'),
            ],
            request()->routeIs('occupations.*') => [
                'parent' => 'Settings',
                'parentIcon' => 'ti-settings',
                'parentUrl' => route('schoolInfo.index'),
                'label' => 'Occupations',
                'icon' => 'ti-briefcase',
                'url' => route('occupations.index'),
            ],
            request()->routeIs('academic-tracks.*') => [
                'parent' => 'Settings',
                'parentIcon' => 'ti-settings',
                'parentUrl' => route('schoolInfo.index'),
                'label' => 'Academic Tracks',
                'icon' => 'ti-route-alt-left',
                'url' => route('academic-tracks.index'),
            ],
            request()->routeIs('withdrawal-reasons.*') => [
                'parent' => 'Settings',
                'parentIcon' => 'ti-settings',
                'parentUrl' => route('schoolInfo.index'),
                'label' => 'Withdrawal Reasons',
                'icon' => 'ti-clipboard-list',
                'url' => route('withdrawal-reasons.index'),
            ],
            request()->routeIs('branding-settings.*') => [
                'parent' => 'Administrator',
                'parentIcon' => 'ti-user-shield',
                'parentUrl' => route('access-management.index'),
                'label' => 'Branding',
                'icon' => 'ti-palette',
                'url' => route('branding-settings.index'),
            ],
            request()->routeIs('database-backups.*') => [
                'parent' => 'Administrator',
                'parentIcon' => 'ti-user-shield',
                'parentUrl' => route('access-management.index'),
                'label' => 'Database Backups',
                'icon' => 'ti-database-export',
                'url' => route('database-backups.index'),
            ],
            request()->routeIs('dashboard-templates.*') => [
                'parent' => 'Administrator',
                'parentIcon' => 'ti-user-shield',
                'parentUrl' => route('access-management.index'),
                'label' => 'Dashboard Templates',
                'icon' => 'ti-layout-dashboard',
                'url' => route('dashboard-templates.index'),
            ],
            request()->routeIs('access-management.*') => [
                'parent' => 'Administrator',
                'parentIcon' => 'ti-user-shield',
                'parentUrl' => route('access-management.index'),
                'label' => 'Roles & Permissions',
                'icon' => 'ti-shield-lock',
                'url' => route('access-management.index'),
            ],
            request()->routeIs('notifications.send*') => [
                'parent' => 'Communication',
                'parentIcon' => 'ti-messages',
                'parentUrl' => route('chat.index'),
                'label' => 'Send Notifications',
                'icon' => 'ti-bell',
                'url' => route('notifications.send'),
            ],
            request()->routeIs('notifications.manage') || request()->routeIs('notifications.update') || request()->routeIs('notifications.delete') => [
                'parent' => 'Communication',
                'parentIcon' => 'ti-messages',
                'parentUrl' => route('chat.index'),
                'label' => 'Notification Management',
                'icon' => 'ti-list',
                'url' => route('notifications.manage'),
            ],
            request()->routeIs('nationalities.*') => [
                'parent' => 'Settings',
                'parentIcon' => 'ti-settings',
                'parentUrl' => route('schoolInfo.index'),
                'label' => 'Nationalities',
                'icon' => 'ti-flag',
                'url' => route('nationalities.index'),
            ],
            request()->routeIs('groups.*') => [
                'parent' => 'Settings',
                'parentIcon' => 'ti-settings',
                'parentUrl' => route('schoolInfo.index'),
                'label' => 'Groups',
                'icon' => 'ti-users-group',
                'url' => route('groups.index'),
            ],
            request()->routeIs('settings.*') => [
                'parent' => 'Settings',
                'parentIcon' => 'ti-settings',
                'parentUrl' => route('schoolInfo.index'),
                'label' => 'Settings',
                'icon' => 'ti-settings',
                'url' => '#',
            ],
            default => [
                'parent' => null,
                'label' => trim($__env->yieldContent('title')) ?: 'Page',
                'icon' => 'ti-dashboard',
                'url' => '#',
            ],
        };
    @endphp
    <ul class="navbar-nav">
        @if ($nav['parent'])
            <li class="nav-item"><a class="nav-link" href="{{ $nav['parentUrl'] }}"><span
                        class="nav-link-icon d-md-none d-lg-inline-block"><i
                            class="ti {{ $nav['parentIcon'] }} icon"></i></span><span
                        class="nav-link-title">{{ $nav['parent'] }}</span></a></li>
            <li class="nav-item"><span class="nav-link text-secondary px-2">&gt;</span></li>
        @endif
        <li class="nav-item"><a class="nav-link" href="{{ $nav['url'] }}"><span
                    class="nav-link-icon d-md-none d-lg-inline-block"><i
                        class="ti {{ $nav['icon'] }} icon"></i></span><span
                    class="nav-link-title">{{ $nav['label'] }}</span></a></li>
    </ul>
    <!-- END NAVBAR MENU -->
</div>
</div>
</header>

