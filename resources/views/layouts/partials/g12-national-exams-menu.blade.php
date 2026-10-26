@if(\App\Support\G12NationalExamPermissions::allows(auth()->user(),'view'))
@php
    $g12Sections=collect(\App\Support\G12NationalExamPermissions::submenus())->filter(fn($group,$section)=>\App\Support\G12NationalExamPermissions::allows(auth()->user(),$section));
    $g12Active=request()->routeIs('g12-national-exams.*');
    $g12Current=request()->route('section')??request()->input('section',request()->input('tab',$g12Sections->keys()->first()));
@endphp
<li class="nav-item dropdown {{ $g12Active ? 'active' : '' }}">
    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ $g12Active?'true':'false' }}" data-sidebar-label="G12 National Exams">
        <span class="nav-link-icon d-md-none d-lg-inline-block">
            <img src="{{ $moeysMenuIcon }}" alt="MoEYS" style="display:block;width:1.25rem;height:1.25rem;object-fit:contain;background:transparent;border:0;border-radius:0;box-shadow:none;">
        </span>
        <span class="nav-link-title">G12 National Exams</span>
    </a>
    <div class="dropdown-menu {{ $g12Active?'show':'' }}" data-g12-submenus>
        <div class="dropdown-menu-columns"><div class="dropdown-menu-column">
            @foreach($g12Sections as $section=>$group)
            <a class="dropdown-item {{ $g12Active&&$g12Current===$section?'active':'' }}" href="{{ route('g12-national-exams.'.$section) }}" @if($g12Active&&$g12Current===$section) aria-current="page" @endif><i class="ti {{ \App\Support\G12NationalExamPermissions::ICONS[$section] }} me-2"></i>{{ $group[0] }}</a>
            @endforeach
        </div></div>
    </div>
</li>
@endif
