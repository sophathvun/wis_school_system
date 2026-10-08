@extends('layouts.app')
@section('title','Grade Skipping Request')
@section('page-header')
<div class="container-fluid"><div class="page-pretitle">Students · Student Skipping Grade</div><h2 class="page-title">Request #{{ $record->id }}</h2></div>
@endsection
@section('content')
@include('student-skipping-grade._workspace-start')
@php($s=$record->student_snapshot)
@php($requestedYear=$s['target_academic_year']??$s['academic_year'])
<div class="skipping-page" data-skipping-page data-success="{{ session('success') }}">
    <div class="card mb-3"><div class="card-header skipping-header skipping-record-header">
        <h3 class="card-title">{{ $s['name_en'] }} · {{ $s['student_id'] }}</h3>
        <span class="badge skipping-record-status skipping-status-{{ $record->status }}">{{ $record->status==='pending'?'Submitted to Central Office':ucfirst($record->status) }}</span>
        <div class="skipping-record-header-actions">
            @if($permissions['print'] && $record->status!=='rejected')
                <a class="btn" target="_blank" href="{{ route('student-skipping-grade.print',['skipping'=>$record,'form'=>'request']) }}"><i class="ti ti-printer me-1"></i>Print Request</a>
                @if($record->status==='approved')<a class="btn" target="_blank" href="{{ route('student-skipping-grade.print',['skipping'=>$record,'form'=>'approval']) }}"><i class="ti ti-printer me-1"></i>Print Approval</a>@endif
            @endif
        </div>
    </div><div class="card-body">
    <div class="skipping-details skipping-request-details">
        <div class="skipping-request-details-photo">
            @if($photoUrl)<img src="{{ $photoUrl }}" alt="Student photo: {{ $s['name_en'] }}">
            @else<span><i class="ti ti-user" aria-hidden="true"></i>No photo</span>@endif
        </div>
        <div class="skipping-request-details-name"><small>Student Name</small><strong class="skipping-kh">{{ $s['name_kh'] }}</strong><strong>{{ $s['name_en'] }}</strong></div>
        <div class="skipping-request-details-campus"><small>Campus / Academic Year</small><strong>{{ $s['campus_en'] }} / {{ $s['academic_year'] }}</strong></div>
        <div class="skipping-request-details-grade"><small>Current → Requested Grade</small><strong>{{ $s['source_grade'] }}{{ $s['source_class'] }} → {{ $s['target_grade'] }}{{ $s['target_class'] }} / {{ $requestedYear }}</strong></div>
        <div class="skipping-request-details-dob"><small>Date of Birth</small><strong>{{ $s['date_of_birth']?\Carbon\Carbon::parse($s['date_of_birth'])->format('d-M-Y'):'—' }}</strong></div>
        <div class="skipping-request-details-parent"><small>Parent / Guardian</small><strong>{{ $record->parent_name }}</strong><span>{{ $record->parent_phone }}</span></div>
        <div class="skipping-request-details-application"><small>Application Date</small><strong>{{ $record->application_date->format('d-M-Y') }}</strong></div>
    </div>
    @if($record->reason)<p class="mt-3 skipping-prewrap">{{ $record->reason }}</p>@endif
    <div class="skipping-criteria mt-3">
        @foreach($criteriaOptions as $key=>$criterion)
            <div class="skipping-criterion">
                <i class="ti {{ $record->criteria[$key]?'ti-checkbox text-success':'ti-square text-secondary' }}"></i>
                <div>
                    <span class="skipping-kh">{{ $criterion['kh'] }}</span>
                    <small>{{ $criterion['en'] }}</small>
                    @if($key==='average' && $record->average_score!==null)
                        <div class="mt-2">Previous Year Average: <strong>{{ $record->average_score }} / {{ $record->average_scale }}</strong></div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    @include('student-skipping-grade._saved-options',['optionsTitle'=>'Additional Request Options','savedOptions'=>$record->criteria['custom_options']??[]])
    @if(\App\Support\StudentSkippingGradePermissions::canEdit(auth()->user(),$record))<a class="btn btn-outline-primary mt-3 skipping-mobile-full-width" href="{{ route('student-skipping-grade.edit',$record) }}"><i class="ti ti-edit me-1"></i>{{ $record->status==='draft'?'Edit Draft':'Edit Request' }}</a>@endif
    </div></div>
    @if($record->status==='draft' && $permissions['submit'])
    <div class="card mb-3"><div class="card-header skipping-header"><h3 class="card-title">Submit Parent-signed Request</h3></div><div class="card-body"><form method="post" action="{{ route('student-skipping-grade.submit',$record) }}" enctype="multipart/form-data" data-skipping-form data-confirm="Submit this parent-signed request to Central Office?" novalidate>@csrf
        <div class="skipping-form-grid">@include('student-skipping-grade._date-field',['dateName'=>'parent_signed_date','dateLabel'=>'Parent Signed Date'])<label>Signed Request (Optional)<input class="form-control" type="file" name="signed_request" accept="application/pdf,image/png,image/jpeg,image/webp"><small class="text-secondary">PDF, JPG, PNG, or WEBP. Maximum 5 MB.</small></label></div>
        <label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="parent_signed" value="1"><span class="form-check-label">The parent/guardian has signed the printed request.</span></label>
        <button class="btn btn-primary mt-3" type="submit">Submit to Central Office</button>
    </form></div></div>
    @endif
    @if($record->submitted_at)
        <div class="card mb-3"><div class="card-body"><div class="skipping-details skipping-submission-details"><div><small>Parent Signed Date</small><strong>{{ $record->parent_signed_at?->format('d-M-Y') }}</strong></div><div><small>Submitted</small><strong><span class="skipping-submission-date">{{ $record->submitted_at->format('d-M-Y') }}</span> <span class="skipping-submission-time">{{ $record->submitted_at->format('H:i') }}</span></strong></div><div><small>Requested By</small><strong>{{ $record->creator?->name }}</strong></div></div>@if($record->signed_request_path)<a class="btn btn-outline-primary mt-3" target="_blank" href="{{ route('student-skipping-grade.signed-request',$record) }}">View Signed Request</a>@endif</div></div>
    @endif
    @if($record->status==='pending' && $permissions['approve'])
    <div class="card mb-3"><div class="card-header skipping-header"><h3 class="card-title">Central Office Approval</h3></div><div class="card-body"><form method="post" action="{{ route('student-skipping-grade.approve',$record) }}" data-skipping-form data-confirm="Approve this request for {{ $s['name_en'] }} in {{ $s['target_grade'] }}{{ $s['target_class'] }} for {{ $requestedYear }}?" novalidate>@csrf
        <div class="skipping-form-grid skipping-approval-date-grid">@foreach(['received_date'=>'Request Received Date','review_date'=>'Committee Review Date','approval_date'=>'VP Approval Date','effective_date'=>'Grade Change Effective Date'] as $key=>$label)
            @include('student-skipping-grade._date-field',['dateName'=>$key,'dateLabel'=>$label])
        @endforeach</div>
        <label class="d-block mt-3">Approval Notes<textarea class="form-control" name="approval_notes" rows="2" maxlength="1500"></textarea></label>
        <div class="skipping-form-grid mt-3"><label>Age Decision <span class="text-danger">*</span><select name="age_decision" class="form-select"><option value="standard">Meets the standard age requirement</option><option value="exception">Approve an age exception</option></select></label><label>Previous School <span class="text-danger">*</span><select name="school_decision" class="form-select"><option value="internal">Western International School</option><option value="external">Another school with supporting evidence</option></select></label></div>
        <div class="mt-3">Student Obligations on Approval Form</div>
        @foreach(['conduct'=>'Maintain good conduct','rules'=>'Follow school regulations','study'=>'Fulfil all study obligations'] as $key=>$label)<label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="obligations[{{ $key }}]" value="1" checked><span class="form-check-label">{{ $label }}</span></label>@endforeach
        @include('student-skipping-grade._custom-options',['customOptions'=>$approvalCustomOptions,'optionsTitle'=>'Additional Approval Options','selectedOptions'=>[],'useDefaults'=>true])
        <label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="vp_signed" value="1"><span class="form-check-label">The committee review is complete and the VP has signed the final request.</span></label>
        <p class="text-secondary mt-3">Approval prepares the student enrollment in {{ $s['campus_en'] }} for {{ $requestedYear }}.@if($requestedYear!==$s['academic_year']) The current-year enrollment stays unchanged.@endif The approval form includes the configured VP signature and stamp.</p>
        <button class="btn btn-success skipping-mobile-full-width" type="submit"><i class="ti ti-check me-1"></i>Approve and Move Grade</button>
    </form></div></div>
    @elseif($record->status==='pending')<div class="alert alert-info">Awaiting Central Office approval after committee review and the VP’s final signature.</div>@endif
    @if($record->status==='pending' && $permissions['reject'])
    <div class="card mb-3"><div class="card-body"><form method="post" action="{{ route('student-skipping-grade.reject',$record) }}" data-skipping-form data-confirm="Reject this request? The student will remain in the current grade." novalidate>@csrf<label class="d-block">Reason for Rejection <span class="text-danger">*</span><textarea class="form-control" name="rejection_reason" rows="2" maxlength="1500"></textarea></label><button class="btn btn-outline-danger mt-3 skipping-mobile-full-width" type="submit">Reject Request</button></form></div></div>
    @endif
    @if($record->status==='approved')
        @if($record->approval_snapshot['custom_options']??[])<div class="card mb-3"><div class="card-body">@include('student-skipping-grade._saved-options',['optionsTitle'=>'Additional Approval Options','savedOptions'=>$record->approval_snapshot['custom_options']])</div></div>@endif
        <div class="alert alert-success"><strong>{{ $record->reference_number }}</strong> · Approved by {{ $record->approver?->name }} on {{ $record->approved_at->format('d-M-Y H:i') }}. Enrollment prepared in {{ $s['target_grade'] }}{{ $s['target_class'] }} for {{ $requestedYear }} (effective {{ $record->effective_date->format('d-M-Y') }}).</div>
    @endif
    @if($record->status==='rejected')<div class="alert alert-danger"><strong>Rejected</strong><p class="mb-0 skipping-prewrap">{{ $record->rejection_reason }}</p></div>@endif
</div>
@include('student-skipping-grade._workspace-end')
@endsection
@push('styles') @vite('resources/css/pages/student-skipping-grade.css') @endpush
@push('scripts') @vite('resources/js/studentSkippingGrade.js') @endpush
