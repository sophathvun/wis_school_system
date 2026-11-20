<aside class="navbar navbar-vertical navbar-expand-lg app-sidebar d-print-none" id="appSidebar" data-bs-theme="dark" data-user-id="{{ auth()->id() }}">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
        <a href="{{ route('gep.dashboard') }}" class="navbar-brand text-white text-decoration-none {{ $branding?->gep_light_sidebar_logo_path ? 'has-light-sidebar-logo' : '' }}" data-sidebar-brand aria-label="GEP Management">
            @if ($branding?->gep_sidebar_logo_path ?: $branding?->gep_light_sidebar_logo_path)
                <img src="{{ asset('storage/' . ($branding->gep_sidebar_logo_path ?: $branding->gep_light_sidebar_logo_path)) }}" alt="General English Program logo" class="navbar-brand-image sidebar-logo-default">
                @if ($branding?->gep_light_sidebar_logo_path)
                    <img src="{{ asset('storage/' . $branding->gep_light_sidebar_logo_path) }}" alt="GEP logo for light sidebar" class="navbar-brand-image sidebar-logo-light">
                @endif
            @else
                <i class="ti ti-books me-2" aria-hidden="true"></i>GEP Management
            @endif
        </a>
        <div class="program-mobile-navbar-switch d-lg-none">@include('layouts.partials.program-switcher')</div>
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
                @php($gepStudentSections=array_filter(\App\Support\GepStudentPermissions::SECTIONS,fn($key)=>\App\Support\GepStudentPermissions::allows(auth()->user(),$key),ARRAY_FILTER_USE_KEY))
                @if($gepStudentSections)
                    <li class="nav-item dropdown {{ request()->routeIs('gep.students.*')?'active':'' }}">
                        <a class="nav-link dropdown-toggle" href="#gep-students-menu" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ request()->routeIs('gep.students.*')?'true':'false' }}"><span class="nav-link-icon"><i class="ti ti-users"></i></span><span class="nav-link-title">GEP Students</span></a>
                        <div class="dropdown-menu {{ request()->routeIs('gep.students.*')?'show':'' }}" id="gep-students-menu">
                            @foreach($gepStudentSections as $key=>$item)
                                <a class="dropdown-item {{ request()->route('studentSection')===$key?'active':'' }}" href="{{ route('gep.students.section',['studentSection'=>$key]) }}" @if(request()->route('studentSection')===$key) aria-current="page" @endif><i class="ti {{ $item['icon'] }} me-2"></i>{{ $item['label'] }}</a>
                            @endforeach
                        </div>
                    </li>
                @endif
                @php($gepReportSections=array_filter(\App\Support\GepReportPermissions::SECTIONS,fn($key)=>\App\Support\GepReportPermissions::allows(auth()->user(),$key),ARRAY_FILTER_USE_KEY))
                @foreach(\App\Support\GepAcademicPermissions::MENUS as $key=>$item)
                    @if(\App\Support\GepAcademicPermissions::allows(auth()->user(),$key))
                        @php($gepAcademicActive=request()->routeIs('gep.academic.*') && request()->route('academicMenu')===$key)
                        <li class="nav-item {{ $gepAcademicActive?'active':'' }}"><a class="nav-link" href="{{ route('gep.academic.index',['academicMenu'=>$key]) }}" @if($gepAcademicActive) aria-current="page" @endif><span class="nav-link-icon"><i class="ti {{ $item['icon'] }}" aria-hidden="true"></i></span><span class="nav-link-title">{{ $item['label'] }}</span></a></li>
                    @endif
                @endforeach
                @if($gepReportSections)
                    <li class="nav-item dropdown {{ request()->routeIs('gep.reports.*')?'active':'' }}">
                        <a class="nav-link dropdown-toggle" href="#gep-reports-menu" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ request()->routeIs('gep.reports.*')?'true':'false' }}"><span class="nav-link-icon"><i class="ti ti-report-analytics"></i></span><span class="nav-link-title">GEP Reports</span></a>
                        <div class="dropdown-menu {{ request()->routeIs('gep.reports.*')?'show':'' }}" id="gep-reports-menu">
                            @foreach($gepReportSections as $key=>$item)
                                <a class="dropdown-item {{ request()->route('reportSection')===$key?'active':'' }}" href="{{ route('gep.reports.section',['reportSection'=>$key]) }}" @if(request()->route('reportSection')===$key) aria-current="page" @endif><i class="ti {{ $item['icon'] }} me-2"></i>{{ $item['label'] }}</a>
                            @endforeach
                        </div>
                    </li>
                @endif
                @include('layouts.partials.communication-menu')
                <li class="nav-item"><a class="nav-link" href="{{ route('profile') }}"><span class="nav-link-icon"><i class="ti ti-user"></i></span><span class="nav-link-title">My Profile</span></a></li>
                <li class="nav-item"><form method="post" action="{{ route('logout') }}">@csrf<button class="nav-link border-0 bg-transparent w-100" type="submit"><span class="nav-link-icon"><i class="ti ti-logout"></i></span><span class="nav-link-title">Log out</span></button></form></li>
            </ul>
        </div>
    </div>
</aside>
