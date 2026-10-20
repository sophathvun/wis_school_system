@extends('layouts.app')

@section('title', 'Staff Management')

@section('content')
@php
    $categoryLabels=['staff'=>'Staff','teacher'=>'Teacher','staff_teacher'=>'Staff & Teacher'];
    $typeLabels=['PT'=>'Part-Time','SFT'=>'Semi Full-Time','FT'=>'Full-Time'];
    $statusLabels=\App\Support\StaffProfile::STATUS;
    $maritalLabels=\App\Support\StaffProfile::MARITAL;
    $days=\App\Support\StaffProfile::DAYS;
    $periods=\App\Support\StaffProfile::PERIODS;
    $contractLabels=\App\Support\StaffProfile::CONTRACTS;
    $identityLabels=\App\Support\StaffProfile::IDENTITY_DOCUMENTS;
    $changeLabels=\App\Support\StaffProfile::CHANGES+['hired'=>'Initial Work Assignment','baseline'=>'Existing Work Record','employment_status'=>'Employment Status Change'];
    $experienceRows=old('experiences',old('experiences_present')?[]:($editStaff?->experiences?->map(fn($item)=>[
        'company_name'=>$item->company_name,'position'=>$item->position,'employment_type'=>$item->employment_type,
        'started_on'=>$item->started_on?->format('Y-m-d'),'ended_on'=>$item->ended_on?->format('Y-m-d'),
        'is_current'=>$item->is_current?'1':'0','responsibilities'=>$item->responsibilities,
    ])->all()??[[]]));
    $educationRows=old('educations',old('educations_present')?[]:($editStaff?->educations?->map(fn($item)=>[
        'institution_name'=>$item->institution_name,'degree'=>$item->degree,'field_of_study'=>$item->field_of_study,
        'country'=>$item->country,'graduated_year'=>$item->graduated_year??$item->ended_on?->year,'notes'=>$item->notes,
        'started_on'=>$item->started_on?->format('Y-m-d'),'ended_on'=>$item->ended_on?->format('Y-m-d'),'grade_or_result'=>$item->grade_or_result,
    ])->all()??[[]]));
    $schedule=old('work_schedule',$editStaff?->work_schedule??[]);
@endphp


    @if (session('success'))
        <div hidden data-staff-success="{{ session('success') }}"></div>
        <noscript><div class="alert alert-success" role="alert">{{ session('success') }}</div></noscript>
    @endif
    @if ($errors->has('staff') && !old('educations_present'))
        <div hidden data-staff-action-error="{{ $errors->first('staff') }}"></div>
        <noscript><div class="alert alert-danger" role="alert">{{ $errors->first('staff') }}</div></noscript>
    @elseif ($errors->any())
        <div class="alert alert-danger alert-dismissible" role="alert"><div class="fw-semibold mb-1">Please check the form.</div><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="staff-metrics mb-3" aria-label="Staff directory summary">
        @foreach(['total'=>['Staff Profiles','ti-users','All matching profiles'], 'active'=>['Active Staff','ti-user-check','Currently employed'], 'teachers'=>['Teaching Staff','ti-school','Teachers and staff teachers'], 'contracts'=>['Contracts Ending','ti-calendar-clock','Within the next 30 days']] as $key=>[$label,$icon,$metricHint])
            <div class="staff-metric"><span class="staff-metric-icon {{ $key==='contracts'?'staff-metric-warning':'' }}"><i class="ti {{ $icon }}" aria-hidden="true"></i></span><div><div class="staff-detail-label">{{ $label }}</div><strong>{{ number_format($staffMetrics[$key]) }}</strong><small>{{ $metricHint }}</small></div></div>
        @endforeach
    </div>
    <div class="card staff-directory mb-3" id="staffDataFilters">
        <div class="staff-panel-header">
            <div><h3><i class="ti ti-adjustments-horizontal me-2" aria-hidden="true"></i>Data Filters</h3></div>
            <div class="d-flex align-items-center gap-2">
                <a class="btn btn-sm btn-outline-light" href="{{ route('staff-management.index') }}"><i class="ti ti-refresh me-1"></i>Reset</a>
                <button type="button" class="btn btn-icon btn-outline-light" data-staff-filters-toggle aria-controls="staffFilterBody" aria-expanded="true" aria-label="Collapse data filters"><i class="ti ti-chevron-up" aria-hidden="true"></i></button>
            </div>
        </div>
        <form id="staffFilterBody" method="GET" action="{{ route('staff-management.index') }}" class="staff-directory-filters" data-staff-filters>
            <input type="hidden" name="per_page" value="{{ $pageSize }}">
            <div class="premium-form-field {{ request('search')?'has-value':'' }} staff-search-field"><label class="form-label" for="staffSearch">Search staff</label><i class="ti ti-search" aria-hidden="true"></i><input id="staffSearch" type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search staff" autocomplete="off" data-search-normalized="true"></div>
            <div class="premium-form-field {{ request('campus_id')?'has-value':'' }}"><label class="form-label" for="staffCampusFilter">Campus</label><select id="staffCampusFilter" name="campus_id" class="form-select"><option value="">All Campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected(request('campus_id')==$campus->id)>{{ $campus->campus_name_en }}</option>@endforeach</select></div>
            <div class="premium-form-field {{ request('employment_status')?'has-value':'' }}"><label class="form-label" for="staffStatusFilter">Employment Status</label><select id="staffStatusFilter" name="employment_status" class="form-select"><option value="">All Statuses</option>@foreach($statusLabels as $key=>$label)<option value="{{ $key }}" @selected(request('employment_status')===$key)>{{ $label }}</option>@endforeach</select></div>
            <div class="premium-form-field {{ request('staff_category')?'has-value':'' }}"><label class="form-label" for="staffCategoryFilter">Staff Category</label><select id="staffCategoryFilter" name="staff_category" class="form-select"><option value="">All Categories</option>@foreach($categoryLabels as $key=>$label)<option value="{{ $key }}" @selected(request('staff_category')===$key)>{{ $label }}</option>@endforeach</select></div>
            <noscript><button type="submit" class="btn btn-primary">Apply Filters</button></noscript>
        </form>
    </div>
    <div class="card staff-directory" id="staffDirectoryCard">
        <div class="staff-panel-header"><div><h3>Staff Directory <span class="staff-header-count">{{ number_format($staff->total()) }}</span></h3></div>@if($canCreateStaff)<a class="btn btn-primary flex-shrink-0 d-print-none" href="{{ route('staff-management.index',['create'=>1]) }}"><i class="ti ti-plus me-1"></i>New Staff</a>@endif</div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table staff-directory-table">
                <thead><tr><th>Staff</th><th>Work Assignment</th><th>Employment</th><th>Campuses</th><th>Contact</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($staff as $person)
                        <tr>
                            <td data-label="Staff"><div class="staff-person">@if($person->photo_path)<button type="button" class="avatar avatar-lg staff-person-avatar staff-directory-photo-trigger" style="background-image:url('{{ asset('storage/'.$person->photo_path) }}')" data-staff-directory-photo="{{ asset('storage/'.$person->photo_path) }}" data-staff-photo-title="{{ $person->name_en }}" aria-label="View photo of {{ $person->name_en }}"></button>@else<span class="avatar avatar-lg staff-person-avatar">{{ mb_strtoupper(mb_substr($person->name_en, 0, 1)) }}</span>@endif<div>@if($person->name_kh)<div class="staff-khmer staff-person-khmer mb-1">{{ $person->name_kh }}</div>@endif<a class="staff-person-name" href="{{ route('staff-management.index',['view'=>$person->id]) }}">{{ $person->name_en }}</a><div class="staff-code mt-1">{{ $person->staff_code }}</div></div></div></td>
                            <td data-label="Work Assignment"><div class="fw-semibold">{{ $person->position?->name ?? '—' }}</div><div class="text-secondary small mt-1">{{ $person->department?->hierarchy_name ?? '—' }}</div><div class="text-secondary small mt-1">{{ $categoryLabels[$person->staff_category] ?? $person->staff_category }}</div></td>
                            <td data-label="Employment"><div>{{ $typeLabels[$person->employment_type] ?? $person->employment_type }}</div>@if($person->joined_on)<div class="text-secondary small mt-1">Since {{ $person->joined_on->format('d-M-Y') }}</div>@endif @if($person->contract_ends_on)<div class="small mt-1 {{ $person->contract_ends_on->lt(today())?'text-danger':($person->contract_ends_on->lte(today()->addDays(30))?'text-warning':'text-secondary') }}">Contract ends {{ $person->contract_ends_on->format('d-M-Y') }}</div>@endif</td>
                            <td data-label="Campuses"><div class="staff-campus-chips">@forelse($person->campuses as $campus)<span class="badge bg-secondary-lt">{{ $campus->campus_name_en }}</span>@empty<span class="text-secondary">—</span>@endforelse</div></td>
                            <td data-label="Contact"><div class="staff-contact">@if($person->phone)<a href="tel:{{ $person->phone }}"><i class="ti ti-phone me-1"></i>{{ $person->phone }}</a>@endif @if($person->work_email || $person->email)<a href="mailto:{{ $person->work_email?:$person->email }}"><i class="ti ti-mail me-1"></i>{{ $person->work_email?:$person->email }}</a>@endif @unless($person->phone || $person->work_email || $person->email)<span class="text-secondary">—</span>@endunless</div></td>
                            <td data-label="Status"><span class="badge {{ $person->employment_status === 'active' ? 'bg-success-lt' : (in_array($person->employment_status,['resigned','terminated','retired'])?'bg-secondary-lt':'bg-warning-lt') }}">{{ $statusLabels[$person->employment_status] ?? $person->employment_status }}</span><div class="text-secondary small mt-2">@if($person->user)<i class="ti ti-link me-1"></i>{{ $person->user->username }}@else No login linked @endif</div>@unless($person->status)<span class="badge bg-secondary-lt mt-1">Profile Inactive</span>@endunless</td>
                            <td class="text-end" data-label="Actions"><div class="staff-action-group"><a class="btn btn-sm btn-outline-primary" href="{{ route('staff-management.index', ['view' => $person->id]) }}"><i class="ti ti-eye me-1"></i>View</a>@if ($canEditStaff)<a class="btn btn-sm btn-outline-secondary" data-action-normalized="true" data-staff-edit="{{ $person->name_en }}" href="{{ route('staff-management.index', ['edit' => $person->id]) }}" aria-label="Edit {{ $person->name_en }}" title="Edit Staff"><i class="ti ti-edit me-1"></i>Edit</a>@endif @if ($canDeleteStaff)<form method="POST" action="{{ route('staff-management.delete', $person) }}" data-staff-delete="{{ $person->name_en }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" data-action-normalized="true" aria-label="Delete {{ $person->name_en }}" title="Delete Staff"><i class="ti ti-trash me-1"></i>Delete</button></form>@endif</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="staff-empty"><i class="ti ti-users" aria-hidden="true"></i><h3>No staff profiles found</h3><p>Try another search or reset the filters.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">@include('staff-management._pagination')</div>
    </div>

    <button type="button" class="btn btn-primary btn-icon staff-back-to-top d-print-none" data-staff-back-to-top aria-label="Back to top" title="Back to top" hidden><i class="ti ti-arrow-up" aria-hidden="true"></i></button>
    @include('staff-management._profile')
    @include('staff-management._form')
    @include('staff-management._photo-crop')
    @include('staff-management._photo-viewer')
@endsection
@push('styles') @vite('resources/css/pages/staff-management.css') @endpush
@push('scripts') @vite('resources/js/staffManagement.js') @endpush
