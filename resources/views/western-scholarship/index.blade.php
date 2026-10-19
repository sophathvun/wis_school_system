@extends('layouts.app')
@section('title', 'WIS Scholarship')
@section('page-header')
    <div class="container-fluid"><div class="page-pretitle">Students</div><h2 class="page-title"><i class="ti ti-award me-2" aria-hidden="true"></i>WIS Scholarship</h2></div>
@endsection

@section('content')
<div class="scholarship-workspace scholarship-tabs-collapsed" data-scholarship-page data-result-success="{{ $tab==='result' ? session('success','') : '' }}" data-scoring-success="{{ $tab==='scoring' ? session('success','') : '' }}" data-students-url="{{ route('western-scholarship.students') }}" data-rank-filters-url="{{ route('western-scholarship.rank-filters') }}" data-rank-success="{{ $tab==='assign-rank' ? session('success','') : '' }}" data-committee-success="{{ $tab==='exam-committee' ? session('success','') : '' }}" data-seat-success="{{ $tab==='seat-plan' ? session('success','') : '' }}" data-settings-success="{{ $tab==='settings' ? session('success','') : '' }}" data-rank-errors="{{ $tab==='assign-rank' ? json_encode($errors->messages()) : '{}' }}">
    <aside class="card scholarship-navigation">
        <div class="card-header scholarship-card-header scholarship-tabs-header"><h3 class="card-title"><i class="ti ti-award me-2" aria-hidden="true"></i>WIS Scholarship</h3><button type="button" class="btn btn-icon btn-outline-primary scholarship-tabs-toggle" aria-expanded="false" aria-controls="scholarshipTabList" aria-label="Expand scholarship tabs" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-custom-class="scholarship-tabs-tooltip" data-bs-title="Expand scholarship tabs"><i class="ti ti-layout-sidebar-left-expand" aria-hidden="true"></i></button></div>
        <nav class="list-group list-group-flush" id="scholarshipTabList" aria-label="WIS Scholarship sections">
            @foreach($tabs as $key => [$label, $icon])
                <a href="{{ route('western-scholarship.index', array_filter($filters) + ['tab' => $key]) }}" class="list-group-item list-group-item-action {{ $tab === $key ? 'active' : '' }}" aria-label="{{ $label }}" data-scholarship-tab-label="{{ $label }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-custom-class="scholarship-tabs-tooltip" data-bs-title="{{ $label }}" @if($tab === $key) aria-current="page" @endif><i class="ti {{ $icon }}" aria-hidden="true"></i><span class="scholarship-tab-label">{{ $label }}</span></a>
            @endforeach
        </nav>
    </aside>
    <main class="scholarship-forms">
        @if(!in_array($tab,['assign-rank','seat-plan','scoring','result','data-transfer'],true))@include('western-scholarship._exam-selector')@endif
        @if(session('success') && !in_array($tab,['assign-rank','settings','seat-plan','exam-committee','scoring','result'],true))<div class="alert alert-success" role="status"><i class="ti ti-circle-check me-2" aria-hidden="true"></i>{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if($tab==='seat-plan')@include('western-scholarship._seat-filters')@endif
        @if($tab==='scoring')@include('western-scholarship._scoring-filters')@endif
        @if($tab==='result')@include('western-scholarship._result-filters')@endif
        <section class="card {{ $tab==='assign-rank'?'scholarship-rank-card':'' }}" @if($tab==='scoring') data-scoring-card @elseif($tab==='result') data-result-card @endif>
            <div class="card-header scholarship-card-header scholarship-form-header {{ $tab==='seat-plan'?'scholarship-seat-header':'' }}">
                <h3 class="card-title"><i class="ti {{ $tabs[$tab][1] }} me-2" aria-hidden="true"></i>{{ $tabs[$tab][0] }}</h3>
                @if($tab==='seat-plan')@include('western-scholarship._seat-summary')@endif
                @if($tab==='assign-rank')<button type="button" class="btn btn-icon btn-outline-primary scholarship-filter-toggle ms-auto" data-rank-filters-toggle aria-expanded="true" aria-controls="scholarshipRankFilters" aria-label="Collapse rank filters"><i class="ti ti-chevron-up" aria-hidden="true"></i></button>
                @elseif($tab==='seat-plan' && ($exam?->seats_locked_at) && $examPermissions['export'])@include('western-scholarship._seat-exports')
                @elseif($tab==='scoring')<button type="button" class="btn btn-sm btn-outline-light ms-auto" data-scoring-fullscreen aria-pressed="false"><i class="ti ti-maximize me-2" aria-hidden="true"></i><span>Full Screen</span></button>
                @elseif($tab==='result')
                    <div class="d-flex flex-wrap gap-2 ms-auto" role="group" aria-label="Result actions">
                        @if($exam && $examPermissions['export'])
                        <a class="btn btn-sm btn-outline-light" target="_blank" rel="noopener" href="{{ route('western-scholarship.report',[$exam,'results','print']) }}"><i class="ti ti-printer me-1" aria-hidden="true"></i>Print Results</a>
                        <a class="btn btn-sm btn-outline-light" href="{{ route('western-scholarship.report',[$exam,'results','xlsx']) }}"><i class="ti ti-file-spreadsheet me-1" aria-hidden="true"></i>Excel</a>
                        <a class="btn btn-sm btn-outline-light" href="{{ route('western-scholarship.report',[$exam,'results','csv']) }}"><i class="ti ti-file-type-csv me-1" aria-hidden="true"></i>CSV</a>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-light" data-result-fullscreen aria-pressed="false"><i class="ti ti-maximize me-2" aria-hidden="true"></i><span>Full Screen</span></button>
                    </div>
                @else<span class="badge bg-white text-dark ms-auto">Forms</span>@endif
            </div>
            <div class="card-body" @if($tab==='assign-rank') id="scholarshipRankFilters" @endif>
            @if($tab === 'assign-rank')
                @php($clearRankInputs = $selected && (int)session('scholarship_rank_saved') === (int)$selected->id)
                <form method="POST" action="{{ route('western-scholarship.ranks.save') }}" data-scholarship-rank-form data-exam-year="{{ $filters['academic_year_id']??'' }}" data-ranks-locked="{{ $ranksLocked?'true':'false' }}">
                    @csrf
                    <input type="hidden" name="rank_id" value="{{ $selectedRankId??'' }}">
                    <input type="hidden" id="scholarshipRankPageSize" name="per_page" value="{{ $filters['per_page']??10 }}">
                    @if($exam ?? null)<input type="hidden" name="exam_id" value="{{ $exam->id }}">@endif
                    <div class="scholarship-filters">
                        <div class="scholarship-field"><label for="scholarshipYear">Academic Year <span class="text-danger">*</span></label><select class="form-select" name="academic_year_id" id="scholarshipYear" data-scholarship-select required><option value="">Select academic year</option>@foreach($academicYears as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year_id'] ?? '') == $year->id)>{{ $year->academic_year }}</option>@endforeach</select></div>
                        <div class="scholarship-field"><label for="scholarshipCampus">Campus <span class="text-danger">*</span></label><select class="form-select" name="campus_id" id="scholarshipCampus" data-scholarship-select required @disabled(empty($filters['academic_year_id']))><option value="">All campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" data-can-assign="{{ \App\Support\ScholarshipPermissions::allows(auth()->user(), 'assign-rank', $campus->id) ? 'true' : 'false' }}" @selected(($filters['campus_id'] ?? '') == $campus->id)>{{ $campus->campus_name_en ?: $campus->campus_name_kh }}</option>@endforeach</select></div>
                        <div class="scholarship-field"><label for="scholarshipGrade">Grade <span class="text-danger">*</span></label><select class="form-select" name="grade_class" id="scholarshipGrade" data-scholarship-select required @disabled(empty($filters['campus_id']))><option value="">All grades</option>@foreach($grades as $grade)<option value="{{ $grade['id'] }}" @selected(($filters['grade_class'] ?? '') == $grade['id'])>{{ $grade['label'] }}</option>@endforeach</select></div>
                        <div class="scholarship-field"><label for="scholarshipStudent">Student <span class="text-danger">*</span></label><select class="form-select" name="enrollment_id" id="scholarshipStudent" data-scholarship-select data-remote-students required><option value="">Select student</option>@if($selected)<option value="{{ $selected->id }}" selected data-rank="{{ $selectedRank }}" data-eoy-average="{{ $selectedEoyAverage??'' }}">{{ $selected->student->student_id ?: $selected->student->student_no }} · {{ $selected->student->full_name_en }}</option>@endif</select></div>
                    <div class="scholarship-rank-form">
                        <div class="scholarship-field"><label for="scholarshipRank">Rank <span class="text-danger">*</span></label><select class="form-select" id="scholarshipRank" name="rank" required @disabled(!$canAssign || $ranksLocked)><option value="">Select rank</option>@foreach([1,2,3] as $rankValue)<option value="{{ $rankValue }}" @selected(old('rank',$clearRankInputs?null:$selectedRank)==$rankValue)>{{ $rankValue }}</option>@endforeach</select></div>
                        <div class="scholarship-field"><label for="scholarshipEoyAverage" title="End-of-Year Average Score">EOY Avg. <span class="text-danger">*</span></label><input class="form-control" id="scholarshipEoyAverage" name="eoy_average" required type="number" inputmode="decimal" min="0" max="100" step="0.01" value="{{ old('eoy_average',$clearRankInputs?null:($selectedEoyAverage??null)) }}" placeholder="0–100" @disabled(!$canAssign || $ranksLocked)></div>
                        <button type="submit" class="btn btn-primary" @disabled(!$canAssign || $ranksLocked)><i class="ti ti-device-floppy me-2" aria-hidden="true"></i>Save Rank</button>
                    </div>
                    </div>
                    <p class="text-secondary small mt-2 mb-0" data-scholarship-search-status aria-live="polite">Search students by ID, Khmer name, or English name.</p>
                    <p class="text-warning small mt-2 mb-0" data-scholarship-ranks-locked @if(!$ranksLocked) hidden @endif>Class ranks and candidates are locked because seats are confirmed. Reopen an unscored seat plan before making changes.</p>
                    <p class="text-warning small mt-2 mb-0" data-scholarship-rank-permission @if($canAssign) hidden @endif>You need the Assign Student Rank permission for this campus to save ranks.</p>
                </form>
            @elseif($tab === 'settings')
                @include('western-scholarship._settings')
            @elseif($tab === 'data-transfer')
                @include('western-scholarship._data-transfer')
            @elseif($exam ?? null)
                @include('western-scholarship._'.$tab)
            @else
                <div class="scholarship-empty"><i class="ti ti-calendar-plus fs-1 text-primary"></i><h3 class="mt-3">Select a scholarship academic year</h3><p class="text-secondary">Set up a regular academic year in Scholarship Settings, or select an existing year above.</p><a class="btn btn-primary" href="{{ route('western-scholarship.index',['tab'=>'settings']) }}">Scholarship Settings</a></div>
            @endif
            </div>
        </section>
        @if($tab === 'assign-rank')
        <p class="text-secondary small mt-3 mb-0" data-scholarship-list-status role="status" aria-live="polite"></p>
        @include('western-scholarship._ranks')
        @endif
    </main>
    @if(in_array($tab,['assign-rank','seat-plan','scoring','result'],true))@include('western-scholarship._photo-viewer')@endif
    @if($tab === 'assign-rank')@include('western-scholarship._print-settings')@endif
    @if($tab==='seat-plan' && ($exam?->seats_locked_at) && $examPermissions['export'])
        @include('western-scholarship._seat-report-scope')
        @include('western-scholarship._print-settings',['seatReportSettings'=>true])
    @endif
</div>
@vite(['resources/css/pages/western-scholarship.css', 'resources/js/westernScholarship.js'])
@endsection
