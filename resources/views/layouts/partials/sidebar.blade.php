<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
    @php
    $sidebarPermissionCodes = $permissionCodes ?? [];
    $canView = fn(string $permission) => in_array('*', $sidebarPermissionCodes, true) || in_array($permission, $sidebarPermissionCodes, true);
@endphp
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
            aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="navbar-brand">
            @if (!empty($branding?->sidebar_logo_path))
                <img src="{{ asset('storage/' . $branding->sidebar_logo_path) }}" alt="School logo"
                    class="navbar-brand-image">
            @endif
        </div>

        <div class="navbar-nav flex-row d-lg-none">
            <div class="nav-item">
                <a href="{{ request()->fullUrlWithQuery(['theme' => 'dark']) }}" class="nav-link px-0 hide-theme-dark" title="Enable dark mode"
                    data-bs-toggle="tooltip" data-bs-placement="bottom">
                    <i class="ti ti-moon icon"></i>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['theme' => 'light']) }}" class="nav-link px-0 hide-theme-light" title="Enable light mode"
                    data-bs-toggle="tooltip" data-bs-placement="bottom">
                    <i class="ti ti-sun icon"></i>
                </a>
            </div>
            <div class="nav-item ms-3">
                <a href="#" class="nav-link px-0" title="Notifications" data-bs-toggle="tooltip"
                    data-bs-placement="bottom" aria-label="Notifications">
                    <i class="ti ti-bell icon"></i>
                </a>
            </div>
            @auth
                <div class="nav-item dropdown ms-3">
                    <a href="#" class="nav-link d-flex align-items-center p-0" data-bs-toggle="dropdown"
                        aria-label="Open profile menu">
                        <span class="avatar avatar-sm"
                            @if (auth()->user()->photo_path) style="background-image: url('{{ asset('storage/' . auth()->user()->photo_path) }}')" @endif></span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow mobile-profile-menu">
                        <div class="dropdown-header">{{ auth()->user()->name }}</div>
                        <a href="{{ route('profile') }}" class="dropdown-item"><i class="ti ti-user me-2"></i>My Profile</a>
                        <a href="{{ route('profile.status') }}" class="dropdown-item"><i class="ti ti-adjustments me-2"></i>Status</a>
                        <a href="{{ route('feedback') }}" class="dropdown-item"><i class="ti ti-message me-2"></i>Feedback</a>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item" type="submit"><i class="ti ti-logout me-2"></i>Logout</button></form>
                    </div>
                </div>
            @endauth
        </div>
        <div class="collapse navbar-collapse" id="sidebar-menu">
            {{-- search sidebar --}}
            <div class="input-icon sidebar-search-box">
                <span class="input-icon-addon">
                    <i class="ti ti-search icon"></i>
                </span>
                <input type="text" id="sidebar-search" class="form-control form-control-sm" placeholder="Search Menu"
                    aria-label="Search Menu">
            </div>
            {{-- end search sidebar --}}
            <div class="sidebar-favorites mt-2 px-2">
                <div class="d-flex align-items-center mb-2">
                    <i class="ti ti-heart text-warning me-2"></i>
                    <span class="text-muted small mb-0">Favorites</span>
                </div>
                <ul id="sidebar-favorites-list" class="list-unstyled mb-0"></ul>
                <div id="sidebar-no-favorites" class="text-muted small mb-0">Click a menu item's heart icon to add it to
                    favorites.</div>
            </div>
            <ul class="navbar-nav pt-lg-3">
                @if ($canView('dashboard.view'))
                    <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('dashboard') }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-dashboard icon"></i>
                            </span>
                            <span class="nav-link-title">Dashboard</span>
                        </a>
                    </li>
                @endif
                @if ($canView('administrator.view'))
                    <li
                        class="nav-item dropdown {{ request()->routeIs('access-management.*') ||
                        request()->routeIs('users.*') ||
                        request()->routeIs('departments.*') ||
                        request()->routeIs('positions.*') ||
                        request()->routeIs('roles.*') ||
                        request()->routeIs('dashboard-templates.*') ||
                        request()->routeIs('branding-settings.*') ||
                        request()->routeIs('database-backups.*')
                            ? 'active'
                            : '' }}">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"
                            data-bs-auto-close="false" role="button" aria-expanded="true">
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-user-shield icon"></i>
                            </span>
                            <span class="nav-link-title">Administrator</span>
                        </a>
                        <div
                            class="dropdown-menu {{ request()->routeIs('access-management.*') ||
                            request()->routeIs('users.*') ||
                            request()->routeIs('departments.*') ||
                            request()->routeIs('positions.*') ||
                            request()->routeIs('roles.*') ||
                            request()->routeIs('dashboard-templates.*') ||
                            request()->routeIs('branding-settings.*') ||
                            request()->routeIs('database-backups.*')
                                ? 'show'
                                : '' }}">
                            <div class="dropdown-menu-columns">
                                <div class="dropdown-menu-column">
                                    @if ($canView('users.view'))
                                        <a class="dropdown-item {{ request()->routeIs('users.*') ? 'active' : '' }}"
                                            href="{{ route('users.index') }}">
                                            <i class="ti ti-users me-2"></i> Users
                                        </a>
                                    @endif
                                    @if ($canView('settings.manage'))
                                        <a class="dropdown-item {{ request()->routeIs('access-management.*') ? 'active' : '' }}"
                                            href="{{ route('access-management.index') }}">
                                            <i class="ti ti-shield-lock me-2"></i> Roles &amp; Permissions
                                        </a>
                                    @endif
                                    @if ($canView('departments.view'))
                                        <a class="dropdown-item {{ request()->routeIs('departments.*') ? 'active' : '' }}"
                                            href="{{ route('departments.index') }}">
                                            <i class="ti ti-building-community me-2"></i> Departments
                                        </a>
                                    @endif
                                    @if ($canView('positions.view'))
                                        <a class="dropdown-item {{ request()->routeIs('positions.*') ? 'active' : '' }}"
                                            href="{{ route('positions.index') }}">
                                            <i class="ti ti-id-badge me-2"></i> Positions
                                        </a>
                                    @endif
                                    @if ($canView('roles.view'))
                                        <a class="dropdown-item {{ request()->routeIs('roles.*') ? 'active' : '' }}"
                                            href="{{ route('roles.index') }}">
                                            <i class="ti ti-badge me-2"></i> Roles
                                        </a>
                                    @endif
                                    @if ($canView('dashboard-templates.view'))
                                        <a class="dropdown-item {{ request()->routeIs('dashboard-templates.*') ? 'active' : '' }}"
                                            href="{{ route('dashboard-templates.index') }}">
                                            <i class="ti ti-layout-dashboard me-2"></i> Dashboard Templates
                                        </a>
                                    @endif
                                    @if ($canView('branding.view'))
                                        <a class="dropdown-item {{ request()->routeIs('branding-settings.*') ? 'active' : '' }}"
                                            href="{{ route('branding-settings.index') }}">
                                            <i class="ti ti-palette me-2"></i> Branding
                                        </a>
                                    @endif
                                    @if (auth()->user()->isSuperAdmin() ||
                                            auth()->user()->hasPermission('settings.manage') ||
                                            auth()->user()->hasPermission('database-backups.view'))
                                        <a class="dropdown-item {{ request()->routeIs('database-backups.*') ? 'active' : '' }}"
                                            href="{{ route('database-backups.index') }}">
                                            <i class="ti ti-database-export me-2"></i> Database Backups
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </li>
                @endif
                @if ($canView('settings.view'))
                    <li
                        class="nav-item dropdown {{ request()->routeIs('academic-years.*') ||
                        request()->routeIs('grades.*') ||
                        request()->routeIs('classes.*') ||
                        request()->routeIs('sessions.*') ||
                        request()->routeIs('education-levels.*') ||
                        request()->routeIs('programs.*') ||
                        request()->routeIs('schoolInfo.*') ||
                        request()->routeIs('locations.*') ||
                        request()->routeIs('occupations.*') ||
                        request()->routeIs('academic-tracks.*') ||
                        request()->routeIs('withdrawal-reasons.*') ||
                        request()->routeIs('student-document-types.*')
                            ? 'active'
                            : '' }}">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"
                            data-bs-auto-close="false" role="button" aria-expanded="true">
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-settings icon"></i>
                            </span>
                            <span class="nav-link-title">Settings</span>
                        </a>
                        <div
                            class="dropdown-menu {{ request()->routeIs('academic-years.*') ||
                            request()->routeIs('grades.*') ||
                            request()->routeIs('classes.*') ||
                            request()->routeIs('sessions.*') ||
                            request()->routeIs('education-levels.*') ||
                            request()->routeIs('programs.*') ||
                            request()->routeIs('schoolInfo.*') ||
                            request()->routeIs('locations.*') ||
                            request()->routeIs('occupations.*') ||
                            request()->routeIs('academic-tracks.*') ||
                            request()->routeIs('withdrawal-reasons.*') ||
                            request()->routeIs('student-document-types.*')
                                ? 'show'
                                : '' }} ">
                            <div class="dropdown-menu-columns">
                                <div class="dropdown-menu-column">
                                    @if ($canView('academic-years.view'))
                                        <a class="dropdown-item {{ request()->routeIs('academic-years.*') ? 'active' : '' }}"
                                            href="{{ route('academic-years.index') }}">
                                            <i class="ti ti-calendar me-2"></i> Academic Years
                                        </a>
                                    @endif
                                    @if ($canView('grades.view'))
                                        <a class="dropdown-item {{ request()->routeIs('grades.*') ? 'active' : '' }}"
                                            href="{{ route('grades.index') }}">
                                            <i class="ti ti-school me-2"></i> Grades
                                        </a>
                                    @endif
                                    @if ($canView('classes.view'))
                                        <a class="dropdown-item {{ request()->routeIs('classes.*') ? 'active' : '' }}"
                                            href="{{ route('classes.index') }}">
                                            <i class="ti ti-door me-2"></i> Classes
                                        </a>
                                    @endif
                                    @if ($canView('sessions.view'))
                                        <a class="dropdown-item {{ request()->routeIs('sessions.*') ? 'active' : '' }}"
                                            href="{{ route('sessions.index') }}">
                                            <i class="ti ti-clock me-2"></i> Sessions
                                        </a>
                                    @endif
                                    @if ($canView('education-levels.view'))
                                        <a class="dropdown-item {{ request()->routeIs('education-levels.*') ? 'active' : '' }}"
                                            href="{{ route('education-levels.index') }}"><i
                                                class="ti ti-school me-2"></i> Education Levels</a>
                                    @endif
                                    @if ($canView('programs.view'))
                                        <a class="dropdown-item {{ request()->routeIs('programs.*') ? 'active' : '' }}"
                                            href="{{ route('programs.index') }}"><i class="ti ti-books me-2"></i>
                                            Programs</a>
                                    @endif
                                    @if ($canView('school-info.view'))
                                        <a class="dropdown-item {{ request()->routeIs('schoolInfo.*') ? 'active' : '' }}"
                                            href="{{ route('schoolInfo.index') }}">
                                            <i class="ti ti-building-community me-2"></i> School Information
                                        </a>
                                    @endif
                                    @if ($canView('locations.view'))
                                        <a class="dropdown-item {{ request()->routeIs('locations.*') ? 'active' : '' }}"
                                            href="{{ route('locations.index') }}">
                                            <i class="ti ti-map-pin me-2"></i> Locations
                                        </a>
                                    @endif
                                    @if ($canView('occupations.view'))
                                        <a class="dropdown-item {{ request()->routeIs('occupations.*') ? 'active' : '' }}"
                                            href="{{ route('occupations.index') }}">
                                            <i class="ti ti-briefcase me-2"></i> Occupations
                                        </a>
                                    @endif
                                    @if ($canView('academic-tracks.view'))
                                        <a class="dropdown-item {{ request()->routeIs('academic-tracks.*') ? 'active' : '' }}"
                                            href="{{ route('academic-tracks.index') }}">
                                            <i class="ti ti-route-alt-left me-2"></i> Academic Tracks
                                        </a>
                                    @endif
                                    @if ($canView('withdrawal-reasons.view'))
                                        <a class="dropdown-item {{ request()->routeIs('withdrawal-reasons.*') ? 'active' : '' }}"
                                            href="{{ route('withdrawal-reasons.index') }}">
                                            <i class="ti ti-clipboard-list me-2"></i> Withdrawal Reasons
                                        </a>
                                    @endif
                                    @if ($canView('student-document-types.view'))
                                        <a class="dropdown-item {{ request()->routeIs('student-document-types.*') ? 'active' : '' }}"
                                            href="{{ route('student-document-types.index') }}">
                                            <i class="ti ti-file-description me-2"></i> Document Types
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </li>
                @endif
                @if ($canView('students.view'))
                    <li
                        class="nav-item dropdown {{ request()->routeIs('searchStudent.*') ||
                        request()->routeIs('studentEnrollment.*') ||
                        request()->routeIs('summer-school.*') ||
                        request()->routeIs('families.*') ||
                        request()->routeIs('studentPromotion.*') ||
                        request()->routeIs('studentTransfer.*') ||
                        request()->routeIs('studentGraduation.*') ||
                        request()->routeIs('withdrawStudent.*') ||
                        request()->routeIs('student-reentry.*') ||
                        request()->routeIs('student-documents.*') ||
                        request()->routeIs('student-data-transfer.*')
                            ? 'active'
                            : '' }}">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"
                            data-bs-auto-close="false" role="button" aria-expanded="true">
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-user icon"></i>
                            </span>
                            <span class="nav-link-title">Students</span>
                        </a>
                        <div
                            class="dropdown-menu {{ request()->routeIs('searchStudent.*') ||
                            request()->routeIs('studentEnrollment.*') ||
                            request()->routeIs('summer-school.*') ||
                            request()->routeIs('families.*') ||
                            request()->routeIs('studentPromotion.*') ||
                            request()->routeIs('studentTransfer.*') ||
                            request()->routeIs('studentGraduation.*') ||
                            request()->routeIs('withdrawStudent.*') ||
                            request()->routeIs('student-reentry.*') ||
                            request()->routeIs('student-documents.*') ||
                            request()->routeIs('student-data-transfer.*')
                                ? 'show'
                                : '' }}">
                            <div class="dropdown-menu-columns">
                                <div class="dropdown-menu-column">
                                    @if ($canView('students.search.view'))
                                        <a class="dropdown-item {{ request()->routeIs('searchStudent.*') ? 'active' : '' }}"
                                            href="{{ route('searchStudent.index') }}">
                                            <i class="ti ti-search me-2"></i> Search Students
                                        </a>
                                    @endif
                                    @if ($canView('students.enrollment.view'))
                                        <a class="dropdown-item {{ request()->routeIs('studentEnrollment.*') ? 'active' : '' }}"
                                            href="{{ route('studentEnrollment.index') }}">
                                            <i class="ti ti-user-plus me-2"></i> Student Enrollment
                                        </a>
                                    @endif
                                    @if ($canView('students.enrollment.view'))
                                        <a class="dropdown-item {{ request()->routeIs('summer-school.*') ? 'active' : '' }}"
                                            href="{{ route('summer-school.index') }}">
                                            <i class="ti ti-sun me-2"></i> Summer School
                                        </a>
                                    @endif
                                    @if ($canView('families.view'))
                                        <a class="dropdown-item {{ request()->routeIs('families.*') ? 'active' : '' }}"
                                            href="{{ route('families.index') }}">
                                            <i class="ti ti-users me-2"></i> Family Management
                                        </a>
                                    @endif
                                    @if ($canView('students.promotion.view'))
                                        <a class="dropdown-item {{ request()->routeIs('studentPromotion.*') ? 'active' : '' }}"
                                            href="{{ route('studentPromotion.index') }}">
                                            <i class="ti ti-arrows-transfer-up me-2"></i> Student Promotion
                                        </a>
                                    @endif
                                    @if ($canView('students.transfer.view'))
                                        <a class="dropdown-item {{ request()->routeIs('studentTransfer.*') ? 'active' : '' }}"
                                            href="{{ route('studentTransfer.index') }}">
                                            <i class="ti ti-arrows-left-right me-2"></i> Student Transfer
                                        </a>
                                    @endif
                                    @if ($canView('students.graduation.view'))
                                        <a class="dropdown-item {{ request()->routeIs('studentGraduation.*') ? 'active' : '' }}"
                                            href="{{ route('studentGraduation.index') }}">
                                            <i class="ti ti-certificate me-2"></i> Student Graduation
                                        </a>
                                    @endif
                                    @if ($canView('students.view'))
                                        <a class="dropdown-item {{ request()->routeIs('withdrawStudent.*') ? 'active' : '' }}"
                                            href="{{ route('withdrawStudent.index') }}">
                                            <i class="ti ti-user-minus me-2"></i> Withdraw Student
                                        </a>
                                    @endif
                                    @if ($canView('student-reentry.view'))
                                        <a class="dropdown-item {{ request()->routeIs('student-reentry.*') ? 'active' : '' }}"
                                            href="{{ route('student-reentry.index') }}">
                                            <i class="ti ti-user-check me-2"></i> Student Re-entry
                                        </a>
                                    @endif
                                    @if ($canView('student-documents.view'))
                                        <a class="dropdown-item {{ request()->routeIs('student-documents.*') ? 'active' : '' }}"
                                            href="{{ route('student-documents.index') }}">
                                            <i class="ti ti-files me-2"></i> Student Documents
                                        </a>
                                    @endif
                                    @if ($canView('student-data-transfer.view'))
                                        <a class="dropdown-item {{ request()->routeIs('student-data-transfer.*') ? 'active' : '' }}"
                                            href="{{ route('student-data-transfer.index') }}">
                                            <i class="ti ti-file-import me-2"></i> Import / Export Data
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </li>
                @endif
                @if ($canView('attendance.view'))
                    <li class="nav-item {{ request()->routeIs('attendance.*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('attendance.index') }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-calendar-check icon"></i>
                            </span>
                            <span class="nav-link-title">Attendance</span>
                        </a>
                    </li>
                @endif
                @if ($canView('schedules.view'))
                    <li class="nav-item {{ request()->routeIs('schedules.*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('schedules.index') }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-calendar-time icon"></i>
                            </span>
                            <span class="nav-link-title">Schedules</span>
                        </a>
                    </li>
                @endif
                @if ($canView('grading-system.view'))
                    <li class="nav-item {{ request()->routeIs('grading-system.*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('grading-system.index') }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                <i class="ti ti-report icon"></i>
                            </span>
                            <span class="nav-link-title">Grading System</span>
                        </a>
                    </li>
                @endif
                @if ($canView('reports.view'))
                    <li class="nav-item dropdown {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"
                            data-bs-auto-close="false" role="button" aria-expanded="{{ request()->routeIs('reports.*') ? 'true' : 'false' }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-report-analytics icon"></i></span>
                            <span class="nav-link-title">Reports</span>
                        </a>
                        <div class="dropdown-menu {{ request()->routeIs('reports.*') ? 'show' : '' }}">
                            <a class="dropdown-item {{ request()->routeIs('reports.*') ? 'active' : '' }}"
                                href="{{ route('reports.index') }}">
                                <i class="ti ti-school me-2"></i> Academic Reports
                            </a>
                        </div>
                    </li>
                @endif
                @if ($canView('communication.view'))
                    <li class="nav-item dropdown {{ request()->routeIs('chat.*') || request()->routeIs('notifications.send*') || request()->routeIs('notifications.manage') ? 'active' : '' }}">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"
                            data-bs-auto-close="false" role="button" aria-expanded="{{ request()->routeIs('chat.*') || request()->routeIs('notifications.send*') || request()->routeIs('notifications.manage') ? 'true' : 'false' }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-messages icon"></i></span>
                            <span class="nav-link-title">Communication</span>
                        </a>
                        <div class="dropdown-menu {{ request()->routeIs('chat.*') || request()->routeIs('notifications.send*') || request()->routeIs('notifications.manage') ? 'show' : '' }}">
                            @if ($canView('chat.view'))
                                <a class="dropdown-item {{ request()->routeIs('chat.*') ? 'active' : '' }}" href="{{ route('chat.index') }}">
                                    <i class="ti ti-messages me-2"></i> Chat
                                </a>
                            @endif
                            @if ($canView('notifications.view'))
                                <a class="dropdown-item {{ request()->routeIs('notifications.send*') ? 'active' : '' }}" href="{{ route('notifications.send') }}">
                                    <i class="ti ti-bell me-2"></i> Send Notifications
                                </a>
                                <a class="dropdown-item {{ request()->routeIs('notifications.manage') ? 'active' : '' }}" href="{{ route('notifications.manage') }}">
                                    <i class="ti ti-list me-2"></i> Notification Management
                                </a>
                            @endif
                        </div>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</aside>

