@if(\App\Support\G9NationalExamPermissions::allows(auth()->user(),'view'))
@php($g9Active=request()->routeIs('g9-national-exams.*'))
<li class="nav-item dropdown {{ $g9Active?'active':'' }}">
    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ $g9Active?'true':'false' }}">
        <span class="nav-link-icon d-md-none d-lg-inline-block"><img src="{{ $moeysMenuIcon }}" alt="MoEYS" style="display:block;width:1.25rem;height:1.25rem;object-fit:contain;background:transparent;border:0;border-radius:0;box-shadow:none;"></span>
        <span class="nav-link-title">G9 National Exams</span>
    </a>
    <div class="dropdown-menu {{ $g9Active?'show':'' }}">
        @foreach(\App\Support\G9NationalExamPermissions::sections() as $section=>$group)
            @if(\App\Support\G9NationalExamPermissions::allows(auth()->user(),$section))
                @php($g9Route=\App\Support\G9NationalExamPermissions::PATHS[$section])
                <a class="dropdown-item {{ request()->routeIs('g9-national-exams.'.$g9Route.'*')?'active':'' }}" href="{{ route('g9-national-exams.'.$g9Route) }}"><i class="ti {{ \App\Support\G9NationalExamPermissions::ICONS[$section] }} me-2"></i>{{ $group[0] }}</a>
            @endif
        @endforeach
    </div>
</li>
@endif
