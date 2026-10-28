@php
    $notificationMenuMobile = (bool) ($mobile ?? false);
@endphp

<div class="dropdown-menu dropdown-menu-arrow dropdown-menu-end premium-notification-menu {{ $notificationMenuMobile ? 'mobile-notification-menu' : '' }}"
    data-navbar-notification-menu
    data-navbar-notification-payload='@json($headerNotificationPayload ?? [])'
    data-navbar-notification-count="{{ $headerUnreadNotifications ?? 0 }}">
    <div class="premium-notification-header">
        <div class="premium-notification-heading">
            <span class="premium-notification-heading-icon"><i class="ti ti-bell"></i></span>
            <div>
                <strong>Notifications</strong>
                <small data-navbar-notification-summary>{{ (int) ($headerUnreadNotifications ?? 0) }} unread</small>
            </div>
        </div>
        <div class="premium-notification-header-actions">
            <button type="button" class="premium-notification-read-all"
                data-navbar-notification-read-all data-url="{{ route('notifications.read-all') }}">
                <i class="ti ti-checks"></i><span>Mark all read</span>
            </button>
            <button type="button" class="premium-notification-close" aria-label="Close notifications"
                data-navbar-notification-close><i class="ti ti-x"></i></button>
        </div>
    </div>

    <div class="premium-notification-tabs" role="tablist" aria-label="Notification filter">
        <button type="button" class="premium-notification-tab active" role="tab" aria-selected="true"
            data-navbar-notification-filter="all">All</button>
        <button type="button" class="premium-notification-tab" role="tab" aria-selected="false"
            data-navbar-notification-filter="unread">
            Unread <span data-navbar-notification-unread-count>{{ (int) ($headerUnreadNotifications ?? 0) }}</span>
        </button>
    </div>

    <div class="premium-notification-list" data-navbar-notification-list></div>

    <a class="premium-notification-view-all" href="{{ route('notifications.index') }}">
        <span>View all notifications</span><i class="ti ti-arrow-right"></i>
    </a>
</div>
