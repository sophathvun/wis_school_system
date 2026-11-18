@if(\App\Support\WmiPermissions::current('view'))
<li class="nav-item dropdown {{ request()->routeIs('wmi-management.*') ? 'active' : '' }}">
    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ request()->routeIs('wmi-management.*') ? 'true' : 'false' }}">
        <span class="nav-link-icon d-md-none d-lg-inline-block"><img src="{{ $wmiMenuIcon }}" alt="WMI" style="display:block;width:1.25rem;height:1.25rem;object-fit:contain;"></span>
        <span class="nav-link-title">WMI Management</span>
    </a>
    <div class="dropdown-menu {{ request()->routeIs('wmi-management.*') ? 'show' : '' }}">
        @foreach(\App\Http\Controllers\WmiManagementController::SECTIONS as $key=>$item)
            @if(\App\Support\WmiPermissions::current($key))
            <a class="dropdown-item {{ request()->routeIs('wmi-management.section') && request()->route('section')===$key ? 'active' : '' }}" href="{{ route('wmi-management.section',$key) }}" @if(request()->route('section')===$key) aria-current="page" @endif><i class="ti {{ $item['icon'] }} me-2" aria-hidden="true"></i>{{ $item['label'] }}</a>
            @endif
        @endforeach
    </div>
</li>

@endif
