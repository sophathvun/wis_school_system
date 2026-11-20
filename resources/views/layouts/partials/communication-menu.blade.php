@php
    $communicationPermissionCodes = $permissionCodes ?? [];
    $canViewCommunication = fn(string $permission) => in_array('*', $communicationPermissionCodes, true) || in_array($permission, $communicationPermissionCodes, true);
    $communicationActive = request()->routeIs('chat.*', 'notifications.send*', 'notifications.manage', 'notifications.update', 'notifications.delete', 'feedback.admin');
@endphp
@if ($canViewCommunication('communication.view'))
    <li class="nav-item dropdown {{ $communicationActive ? 'active' : '' }}">
        <a class="nav-link dropdown-toggle" href="#communication-menu" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ $communicationActive ? 'true' : 'false' }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-messages icon"></i></span>
            <span class="nav-link-title">Communication</span>
        </a>
        <div class="dropdown-menu {{ $communicationActive ? 'show' : '' }}" id="communication-menu">
            @if ($canViewCommunication('chat.view'))
                <a class="dropdown-item {{ request()->routeIs('chat.*') ? 'active' : '' }}" href="{{ route('chat.index') }}"><i class="ti ti-messages me-2"></i> Chat</a>
            @endif
            @if ($canViewCommunication('notifications.view'))
                <a class="dropdown-item {{ request()->routeIs('notifications.send*') ? 'active' : '' }}" href="{{ route('notifications.send') }}"><i class="ti ti-bell me-2"></i> Send Notifications</a>
                <a class="dropdown-item {{ request()->routeIs('notifications.manage', 'notifications.update', 'notifications.delete') ? 'active' : '' }}" href="{{ route('notifications.manage') }}"><i class="ti ti-list me-2"></i> Notification Management</a>
            @endif
            <a class="dropdown-item {{ request()->routeIs('feedback.admin') ? 'active' : '' }}" href="{{ route('feedback.admin') }}"><i class="ti ti-message-report me-2"></i> Feedback List</a>
        </div>
    </li>
@endif
