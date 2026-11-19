<aside class="navbar navbar-vertical navbar-expand-lg app-sidebar d-print-none" id="appSidebar" data-bs-theme="dark" data-user-id="{{ auth()->id() }}">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
        <a href="{{ route('gep.dashboard') }}" class="navbar-brand text-white text-decoration-none"><i class="ti ti-books me-2"></i>GEP Management</a>
        <div class="d-lg-none">@include('layouts.partials.program-switcher')</div>
        <button type="button" class="sidebar-collapse-toggle d-none d-lg-inline-flex" id="sidebarCollapseToggle" aria-label="Collapse sidebar" aria-expanded="true" title="Collapse sidebar"><i class="ti ti-layout-sidebar-left-collapse" aria-hidden="true"></i></button>
        <div class="collapse navbar-collapse" id="sidebar-menu">
            <ul class="navbar-nav pt-lg-3">
                <li class="nav-item {{ request()->routeIs('gep.dashboard')?'active':'' }}"><a class="nav-link" href="{{ route('gep.dashboard') }}"><span class="nav-link-icon"><i class="ti ti-dashboard"></i></span><span class="nav-link-title">GEP Dashboard</span></a></li>
                @php($gepSettingsSections=array_filter(\App\Support\GepSettingsPermissions::SECTIONS,fn($key)=>\App\Support\GepSettingsPermissions::allows(auth()->user(),$key),ARRAY_FILTER_USE_KEY))
                @if($gepSettingsSections)
                    <li class="nav-item dropdown {{ request()->routeIs('gep.settings.*')?'active':'' }}">
                        <a class="nav-link dropdown-toggle" href="#gep-settings-menu" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ request()->routeIs('gep.settings.*')?'true':'false' }}"><span class="nav-link-icon"><i class="ti ti-settings"></i></span><span class="nav-link-title">GEP Settings</span></a>
                        <div class="dropdown-menu {{ request()->routeIs('gep.settings.*')?'show':'' }}" id="gep-settings-menu">
                            @foreach($gepSettingsSections as $key=>$item)
                                <a class="dropdown-item {{ request()->route('section')===$key?'active':'' }}" href="{{ route('gep.settings.section',['section'=>$key]) }}" @if(request()->route('section')===$key) aria-current="page" @endif><i class="ti {{ $item['icon'] }} me-2"></i>{{ $item['label'] }}</a>
                            @endforeach
                        </div>
                    </li>
                @endif
                <li class="nav-item"><a class="nav-link" href="{{ route('profile') }}"><span class="nav-link-icon"><i class="ti ti-user"></i></span><span class="nav-link-title">My Profile</span></a></li>
                <li class="nav-item"><form method="post" action="{{ route('logout') }}">@csrf<button class="nav-link border-0 bg-transparent w-100" type="submit"><span class="nav-link-icon"><i class="ti ti-logout"></i></span><span class="nav-link-title">Log out</span></button></form></li>
            </ul>
        </div>
    </div>
</aside>
