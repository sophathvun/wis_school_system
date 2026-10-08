@php
    $activeTab=$activeTab??'requests';
    $skippingTabs=collect([
        'requests'=>['permission'=>'requests','label'=>'Skipping Grade Requests','icon'=>'ti-stairs-up','url'=>route('student-skipping-grade.index')],
        'campus-settings'=>['permission'=>'campus-settings','label'=>'Campus Settings','icon'=>'ti-building-cog','url'=>route('student-skipping-grade.campus-settings')],
        'settings'=>['permission'=>'settings','label'=>'Approval Settings','icon'=>'ti-settings','url'=>route('student-skipping-grade.settings')],
        'request'=>['permission'=>'request-template','label'=>'Customize Request Form','icon'=>'ti-file-pencil','url'=>route('student-skipping-grade.template','request')],
        'approval'=>['permission'=>'approval-template','label'=>'Customize Approval Form','icon'=>'ti-certificate','url'=>route('student-skipping-grade.template','approval')],
    ])->filter(fn($tab)=>\App\Support\StudentSkippingGradePermissions::allows(auth()->user(),$tab['permission']));
@endphp
<div class="skipping-workspace skipping-tabs-collapsed" data-skipping-workspace>
    <aside class="card skipping-tabs-card">
        <div class="card-header skipping-tabs-header"><h3 class="card-title">Skipping Grade</h3><button class="btn btn-icon btn-outline-light" type="button" data-skipping-tabs-toggle data-skipping-tab-tooltip aria-label="Expand skipping grade tabs" aria-expanded="false" aria-controls="skippingTabList"><i class="ti ti-layout-sidebar-left-expand"></i></button></div>
        <nav class="list-group list-group-flush skipping-tabs-list" id="skippingTabList" aria-label="Skipping Grade sections">
            @foreach($skippingTabs as $key=>$tab)
                <a class="list-group-item list-group-item-action {{ $activeTab===$key?'active':'' }}" href="{{ $tab['url'] }}" aria-label="{{ $tab['label'] }}" data-skipping-tab-tooltip data-bs-title="{{ $tab['label'] }}" @if($activeTab===$key) aria-current="page" @endif><i class="ti {{ $tab['icon'] }}" aria-hidden="true"></i><span>{{ $tab['label'] }}</span></a>
            @endforeach
        </nav>
    </aside>
    <div class="skipping-workspace-content">
