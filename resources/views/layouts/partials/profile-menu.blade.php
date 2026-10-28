@php
    $profileMenuMobile = (bool) ($mobile ?? false);
    $profileMenuUser = auth()->user();
    $profileMenuName = $profileMenuUser?->name ?: 'School System User';
    $profileMenuEmail = $profileMenuUser?->email ?: 'No email address';
    $profileMenuPosition = $profileMenuUser?->position?->name ?? 'Staff';
    $profileMenuPhoto = $profileMenuUser?->photo_path
        ? asset('storage/' . $profileMenuUser->photo_path)
        : null;
    $profileMenuInitial = Illuminate\Support\Str::upper(Illuminate\Support\Str::substr($profileMenuName, 0, 1));
@endphp

<div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow premium-profile-menu {{ $profileMenuMobile ? 'mobile-profile-menu' : '' }}">
    @auth
        <div class="premium-profile-summary">
            <div class="premium-profile-identity">
                <strong class="premium-profile-name">{{ $profileMenuName }}</strong>
                <span class="premium-profile-email">{{ $profileMenuEmail }}</span>
                <span class="premium-profile-position">{{ $profileMenuPosition }}</span>
            </div>
            <a href="{{ route('profile') }}" class="premium-profile-avatar-ring" aria-label="Open my profile">
                @if ($profileMenuPhoto)
                    <img src="{{ $profileMenuPhoto }}" alt="{{ $profileMenuName }}"
                        class="premium-profile-avatar-image">
                @else
                    <span class="premium-profile-avatar-image premium-profile-avatar-initials">{{ $profileMenuInitial }}</span>
                @endif
            </a>
        </div>

        <div class="premium-profile-actions">
            <a href="{{ route('profile') }}" class="dropdown-item premium-profile-action is-featured">
                <span class="premium-profile-action-icon"><i class="ti ti-user-check"></i></span>
                <span class="premium-profile-action-label">My Profile</span>
                <i class="ti ti-chevron-right premium-profile-action-end" aria-hidden="true"></i>
            </a>
            <button type="button" class="dropdown-item premium-profile-action" data-enable-phone-notifications>
                <span class="premium-profile-action-icon"><i class="ti ti-bell-ringing"></i></span>
                <span class="premium-profile-action-label">Phone Notifications</span>
                <span class="premium-profile-menu-badge">Enable</span>
            </button>
            <a href="{{ route('profile.status') }}" class="dropdown-item premium-profile-action">
                <span class="premium-profile-action-icon"><i class="ti ti-adjustments-horizontal"></i></span>
                <span class="premium-profile-action-label">Status</span>
                <i class="ti ti-chevron-right premium-profile-action-end" aria-hidden="true"></i>
            </a>
        </div>

        <div class="premium-profile-divider"></div>

        <div class="premium-profile-actions premium-profile-actions-secondary">
            <a href="{{ route('feedback') }}" class="dropdown-item premium-profile-action">
                <span class="premium-profile-action-icon"><i class="ti ti-help-circle"></i></span>
                <span class="premium-profile-action-label">Feedback &amp; Help</span>
                <i class="ti ti-chevron-right premium-profile-action-end" aria-hidden="true"></i>
            </a>
            <form method="POST" action="{{ route('logout') }}" data-clear-dashboard-hero>
                @csrf
                <button class="dropdown-item premium-profile-action premium-profile-signout" type="submit">
                    <span class="premium-profile-action-icon"><i class="ti ti-logout"></i></span>
                    <span class="premium-profile-action-label">Sign out</span>
                </button>
            </form>
        </div>
    @else
        <a href="{{ route('login') }}" class="dropdown-item premium-profile-action">
            <span class="premium-profile-action-icon"><i class="ti ti-login"></i></span>
            <span class="premium-profile-action-label">Sign in</span>
        </a>
    @endauth
</div>
