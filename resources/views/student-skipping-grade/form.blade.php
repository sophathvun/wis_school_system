@extends('layouts.app')
@section('title',$record->exists?'Edit Grade Skipping Request':'New Grade Skipping Request')
@section('page-header')
<div class="container-fluid"><div class="page-pretitle">Students · Student Skipping Grade</div><h2 class="page-title">{{ $record->exists?'Edit Request':'New Grade Skipping Request' }}</h2></div>
@endsection
@section('content')
@include('student-skipping-grade._workspace-start')
<div class="skipping-page" data-skipping-page data-students-url="{{ route('student-skipping-grade.students') }}" data-source-classes-url="{{ route('student-skipping-grade.source-classes') }}" data-enrollment-url="{{ route('student-skipping-grade.enrollment',['enrollment'=>'__id__']) }}" data-classes-url="{{ route('student-skipping-grade.classes') }}">
<form class="skipping-request-form" method="post" action="{{ $record->exists?route('student-skipping-grade.update',$record):route('student-skipping-grade.store') }}" data-skipping-form data-request-action="{{ $record->exists?'update':'create' }}" novalidate>@csrf
    <div class="alert alert-info" data-request-campus-note hidden>You can view this student, but you do not have permission to save this request for their campus.</div>
    <div class="card mb-3"><div class="card-header skipping-header"><h3 class="card-title">Student Information</h3></div><div class="card-body">
    @if(!$record->exists)
        <h4 class="mb-3">Filter Students</h4>
        <div class="skipping-student-filter-grid mb-3">
            <div class="premium-form-field"><label class="form-label" for="skippingFilterYear">Academic Year</label><select id="skippingFilterYear" class="form-select" aria-label="Academic Year" data-student-year data-skipping-searchable="Academic Year"><option value="">All Academic Year</option>@foreach($academicYears->whereIn('lifecycle_status',['pending','started','finished']) as $year)<option value="{{ $year->id }}">{{ $year->academic_year }}</option>@endforeach</select></div>
            <div class="premium-form-field"><label class="form-label" for="skippingFilterCampus">Campus</label><select id="skippingFilterCampus" class="form-select" aria-label="Campus" data-student-campus data-skipping-searchable="Campus"><option value="">All Campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected(auth()->user()->active_campus_id==$campus->id)>{{ $campus->campus_name_en }}</option>@endforeach</select></div>
            <div class="premium-form-field"><label class="form-label" for="skippingFilterGrade">Grade</label><select id="skippingFilterGrade" class="form-select" aria-label="Grade" data-student-grade data-skipping-searchable="Grade"><option value="">All Grades</option></select></div>
            <div class="premium-form-field"><label class="form-label" for="skippingFilterStudent">Student Name</label><select id="skippingFilterStudent" class="form-select" aria-label="Student Name" data-student-name data-skipping-searchable="Student Name"><option value="">Select Student</option></select></div>
            <div class="skipping-student-picker premium-form-field"><label class="form-label" for="skippingStudentSearch">Search Student Name / ID</label><input id="skippingStudentSearch" class="form-control" type="search" aria-label="Search Student Name / ID" data-student-search autocomplete="off" placeholder="Search by student ID or name"><div class="skipping-student-results" data-student-results hidden></div></div>
        </div>
        <small class="text-secondary d-block mb-3" data-student-hint>Select a filter, or type at least 2 characters to find a student.</small>
    @endif
    <input type="hidden" name="enrollment_id" value="{{ old('enrollment_id',$record->enrollment_id) }}">
    <div class="skipping-student-summary" data-student-summary aria-live="polite">
        <span data-student-empty @if($record->exists) hidden @endif>No student selected.</span>
        <div class="skipping-selected-student" data-selected-student @unless($record->exists) hidden @endunless>
            <div class="skipping-student-photo"><img data-student-photo @if($previewPhotoUrl) src="{{ $previewPhotoUrl }}" @else hidden @endif alt="Student photo"><span data-student-no-photo @if($previewPhotoUrl) hidden @endif><i class="ti ti-user"></i>No photo</span></div>
            <div class="skipping-student-info">
                <strong class="skipping-student-name-kh skipping-kh" data-student-name-kh>{{ $record->student_snapshot['name_kh']??'' }}</strong>
                <div class="skipping-student-name-en" data-student-name-en>{{ $record->student_snapshot['name_en']??'' }}</div>
                <div class="skipping-student-meta">
                    <div><small>Student ID</small><span data-student-id>{{ $record->student_snapshot['student_id']??'' }}</span></div>
                    <div><small>Campus</small><span data-student-campus-label>{{ $record->student_snapshot['campus_en']??'' }}</span></div>
                    <div><small>Current Grade</small><span data-student-grade-label>@if($record->exists){{ $record->student_snapshot['source_grade'] }}{{ $record->student_snapshot['source_class'] }}@endif</span></div>
                    <div><small>Academic Year</small><span data-student-year-label>{{ $record->student_snapshot['academic_year']??'' }}</span></div>
                    <div><small>Date of Birth</small><span data-student-dob>@if($record->student_snapshot['date_of_birth']??null){{ \Carbon\Carbon::parse($record->student_snapshot['date_of_birth'])->format('d-M-Y') }}@endif</span></div>
                </div>
            </div>
        </div>
    </div>
    @php
        $existingPromotion=$record->student_snapshot['existing_promotion']??null;
    @endphp
    <div class="alert alert-info mt-3" data-existing-promotion @unless($existingPromotion) hidden @endunless>
        @if($existingPromotion)Already promoted to {{ $existingPromotion['grade'] }} / {{ $existingPromotion['academic_year'] }}. Approval will update this existing enrollment. It stays unchanged while the request is pending or rejected.@endif
    </div>
    <div class="skipping-form-grid skipping-request-grid mt-3">
        <div class="premium-form-field"><label class="form-label" for="skippingRequestedYear">Requested Academic Year <span class="text-danger">*</span></label><select id="skippingRequestedYear" name="target_academic_year_id" class="form-select" data-target-year><option value="">Select Academic Year</option>@foreach($requestedAcademicYears as $year)<option value="{{ $year->id }}" @selected(old('target_academic_year_id',$record->target_academic_year_id??$record->academic_year_id)==$year->id)>{{ $year->academic_year }}</option>@endforeach</select></div>
        <div class="premium-form-field"><label class="form-label" for="skippingRequestedGrade">Requested Grade <span class="text-danger">*</span></label><select id="skippingRequestedGrade" name="target_grade_id" class="form-select" data-target-grade><option value="">Select Grade</option>@foreach($grades as $grade)<option value="{{ $grade->id }}" data-order="{{ (int)$grade->grade_order }}" @selected(old('target_grade_id',$record->target_grade_id)==$grade->id)>{{ $grade->grade_short_name ?: $grade->grade }}</option>@endforeach</select></div>
        <div class="premium-form-field"><label class="form-label" for="skippingRequestedClass">Requested Class <span class="text-danger">*</span></label><select id="skippingRequestedClass" name="target_class_id" class="form-select" data-target-class><option value="">Select Class</option>@if($targetClass)<option value="{{ $targetClass->id }}" data-session="{{ $targetClass->session_id }}" selected>{{ $targetClass->class_name }}</option>@endif</select></div>
        <div class="premium-form-field"><label class="form-label" for="skippingRequestedGroup">Group</label><select id="skippingRequestedGroup" name="target_session_id" class="form-select"><option value="">No Group</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected(old('target_session_id',$record->target_session_id)==$group->id)>{{ $group->session_short_name ?: $group->session_name }}</option>@endforeach</select></div>
        <div class="premium-form-field">
            <label class="form-label" for="skippingApplicationDateDirect">Application Date <span class="text-danger">*</span></label>
            @php
                $applicationDateValue = old('application_date', $record->application_date?->format('Y-m-d'));
                $applicationDateParsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $applicationDateValue ?: '');
                $applicationDateDisplay = $applicationDateParsed && $applicationDateParsed->format('Y-m-d') === $applicationDateValue ? $applicationDateParsed->format('d-M-Y') : $applicationDateValue;
            @endphp
            <div class="date-picker skipping-application-date-picker" data-premium-date-picker data-premium-date-format="DD-MMM-YYYY">
                <input type="hidden" name="application_date" data-premium-date-value value="{{ $applicationDateValue }}">
                <input id="skippingApplicationDateDirect" class="form-control" type="text" data-premium-date-input value="{{ $applicationDateDisplay }}" placeholder="DD-MMM-YYYY" autocomplete="off" aria-label="Application Date in DD-MMM-YYYY format" aria-required="true">
                <button type="button" class="date-picker-trigger date-picker-calendar-button" data-premium-date-toggle aria-label="Open Application Date calendar" aria-expanded="false" aria-controls="skippingApplicationDateCalendar"><i class="ti ti-calendar"></i></button>
                <div id="skippingApplicationDateCalendar" class="date-picker-popup d-none" role="dialog" aria-label="Choose Application Date">
                    <div class="date-picker-header">
                        <button type="button" class="date-picker-nav" data-premium-date-prev aria-label="Previous month"><i class="ti ti-chevron-left"></i></button>
                        <button type="button" class="date-picker-year-toggle" aria-label="Choose year"></button>
                        <button type="button" class="date-picker-nav" data-premium-date-next aria-label="Next month"><i class="ti ti-chevron-right"></i></button>
                    </div>
                    <div class="date-picker-year-popup d-none"><div class="date-picker-years"></div></div>
                    <div class="date-picker-grid">
                        <div class="date-picker-weekdays"><span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span></div>
                        <div class="date-picker-days"></div>
                    </div>
                    <div class="skipping-calendar-footer"><button type="button" class="btn btn-sm btn-primary" data-premium-date-today>Today</button></div>
                </div>
            </div>
        </div>
    </div>
    </div></div>
    <div class="card mb-3"><div class="card-header skipping-header"><h3 class="card-title">Parent / Guardian Information</h3></div><div class="card-body"><div class="skipping-form-grid">
        <div class="premium-form-field"><label class="form-label" for="skippingParentPicker">Existing Parent / Guardian</label><select id="skippingParentPicker" class="form-select" data-parent-picker><option value="">Enter parent information below</option><option value="guardian">Guardian</option></select></div>
        <div class="premium-form-field"><label class="form-label" for="skippingParentName">Name <span class="text-danger">*</span></label><input id="skippingParentName" class="form-control" name="parent_name" value="{{ old('parent_name',$record->parent_name) }}" maxlength="200" autocapitalize="characters" spellcheck="false"></div>
        <div class="premium-form-field"><label class="form-label" for="skippingParentPhone">Telephone</label><input id="skippingParentPhone" class="form-control" name="parent_phone" value="{{ old('parent_phone',$record->parent_phone) }}" maxlength="50"></div>
    </div></div></div>
    <div class="card mb-3"><div class="card-header skipping-header"><h3 class="card-title">Criteria and Additional Information</h3></div><div class="card-body">
        <div class="skipping-criteria">@foreach($criteriaOptions as $key=>$criterion)<label class="skipping-criterion"><input class="form-check-input" type="checkbox" name="criteria[{{ $key }}]" value="1" @checked(old('criteria.'.$key,$record->criteria[$key]??false))><span><span class="skipping-kh">{{ $criterion['kh'] }}</span><small>{{ $criterion['en'] }}</small></span></label>@endforeach</div>
        @include('student-skipping-grade._custom-options',['optionsTitle'=>'Additional Request Options','selectedOptions'=>$record->criteria['custom_options']??[],'useDefaults'=>!$record->exists])
        <div class="skipping-form-grid mt-3"><div class="premium-form-field"><label class="form-label" for="skippingAverageScore">Previous Year Average</label><input id="skippingAverageScore" class="form-control" type="number" name="average_score" min="0" max="100" step="0.01" value="{{ old('average_score',$record->average_score) }}"></div><div class="premium-form-field skipping-score-scale-field"><label class="form-label" for="skippingAverageScale">Score Scale</label><select id="skippingAverageScale" class="form-select" name="average_scale"><option value="100" @selected(old('average_scale',$record->average_scale)==100)>Out of 100</option><option value="10" @selected(old('average_scale',$record->average_scale)==10)>Out of 10</option></select><i class="ti ti-chevron-down skipping-score-scale-arrow" aria-hidden="true"></i></div></div>
        <div class="premium-form-field mt-3"><label class="form-label" for="skippingReason">Reason / Additional Information</label><textarea id="skippingReason" class="form-control" name="reason" rows="3" maxlength="1500">{{ old('reason',$record->reason) }}</textarea></div>
    </div></div>
    <div class="card mb-3"><div class="card-header skipping-header"><h3 class="card-title">Committee Names</h3></div><div class="card-body"><div class="skipping-form-grid skipping-campus-committee-grid">@foreach($campusCommitteeRoles as $index=>$role)<div class="premium-form-field"><label class="form-label" for="skippingCommittee{{ $index }}">{{ $index+1 }}. {{ $role }}</label><input id="skippingCommittee{{ $index }}" class="form-control" name="committee_names[{{ $index }}]" data-campus-committee-name="{{ $index }}" value="{{ old('committee_names.'.$index,$record->committee_names[$index]??'') }}" maxlength="200"></div>@endforeach</div></div></div>
    <div class="skipping-save-bar"><a class="btn" href="{{ route('student-skipping-grade.index') }}">Cancel</a><button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>{{ $record->status==='pending'?'Save Changes':'Save Draft Request' }}</button></div>
</form></div>
@include('student-skipping-grade._workspace-end')
@endsection
@push('styles') @vite('resources/css/pages/student-skipping-grade.css') @endpush
@push('scripts') @vite('resources/js/studentSkippingGrade.js') @endpush
