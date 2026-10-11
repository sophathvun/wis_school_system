@extends('layouts.app')
@section('title','Grade Skipping Approval Flow')
@section('page-header')
<div class="container-fluid"><div class="page-pretitle">Student Skipping Grade</div><h2 class="page-title">Approval Flow</h2></div>
@endsection
@section('content')
@include('student-skipping-grade._workspace-start',['activeTab'=>'approval-flow'])
<div class="skipping-page skipping-workflow-fields" data-skipping-page data-success="{{ session('success') }}">
    <div class="alert alert-info">The selected flow applies to newly created requests. Existing requests keep their flow. Reviewers are saved when a draft is submitted; later settings changes do not rewrite submitted requests or completed approvals.</div>
    <div class="card mb-3"><div class="card-header skipping-header"><h3 class="card-title">System Approval Flow</h3></div><div class="card-body">
        <form method="get" class="mb-3" action="{{ route('student-skipping-grade.approval-flow') }}">
            <div class="premium-form-field has-value"><label class="form-label" for="flowCampus">Campus to Configure</label><select id="flowCampus" class="form-select" name="campus_id" data-skipping-flow-campus>
                @foreach($campuses as $option)<option value="{{ $option->id }}" @selected($campus->id===$option->id)>{{ $option->campus_name_en }}{{ in_array($option->id,$configuredCampusIds)?' — Configured':' — Not configured' }}</option>@endforeach
            </select></div>
        </form>
        <form method="post" action="{{ route('student-skipping-grade.approval-flow.save') }}" data-skipping-form novalidate>
            @csrf
            <input type="hidden" name="revision" value="{{ $revision }}"><input type="hidden" name="campus_id" value="{{ $campus->id }}">
            <div class="premium-form-field has-value"><label class="form-label" for="flowMode">Flow for New Requests</label><select id="flowMode" name="mode" class="form-select">
                <option value="legacy" @selected($settings->approval_flow_mode!=='sequential')>Current Flow — Central Office Approval</option>
                <option value="sequential" @selected($settings->approval_flow_mode==='sequential')>Step-by-step Flow — Campus and Central Reviewers</option>
            </select></div>
            <p class="text-secondary mt-3">Save validates reviewers for the selected campus and the shared Central approval steps, including the final Vice President. You can configure other campuses separately. The flow setting applies to new requests across all campuses; step-by-step requests can be submitted only after their campus reviewers are configured. Each step can have several reviewers with different current grade assignments. All Grades includes future grades; Assigned Grades uses the grades you select.</p>
            <p class="text-secondary">For example, add a KH Coordinator step with one reviewer assigned N–K3 and another assigned Grades 1–12. You can also use N–Grade 4 and Grades 5–12, or overlapping assignments. One eligible reviewer completes each step. Ensure every grade that can submit a request has an eligible reviewer in every step.</p>
            @foreach(['campus_steps'=>['title'=>$campus->campus_name_en.' Campus Reviewers','steps'=>$campusSteps,'users'=>$campusReviewerOptions], 'central_steps'=>['title'=>'Central Office Reviewers','steps'=>$centralSteps,'users'=>$centralReviewerOptions]] as $key=>$section)
                <section class="skipping-flow-editor mb-4" data-flow-editor="{{ $key }}">
                    <h3>{{ $section['title'] }}</h3>
                    @if($section['users']->isEmpty())
                        <p class="text-secondary">No eligible reviewers are available. In <a href="{{ route('users.index') }}">User Management</a>, assign active users to {{ $key==='campus_steps'?$campus->campus_name_en:'Central (CEN)' }} and enable Students &gt; Skipping Grade access for that campus.</p>
                    @endif
                    <div data-flow-rows>
                        @foreach($section['steps'] as $index=>$step)
                            @include('student-skipping-grade._flow-editor-row',['rowKey'=>$key,'rowIndex'=>$index,'rowStep'=>$step,'rowUsers'=>$section['users']])
                        @endforeach
                    </div>
                    <template data-flow-template>@include('student-skipping-grade._flow-editor-row',['rowKey'=>$key,'rowIndex'=>'new','rowStep'=>[],'rowUsers'=>$section['users']])</template>
                    <button class="btn btn-outline-primary mt-2" type="button" data-flow-add><i class="ti ti-plus me-1"></i>Add Approval Step</button>
                </section>
            @endforeach
            <h3>Final Approval</h3>
            <div class="premium-form-field {{ $vicePresidentId?'has-value':'' }}"><label class="form-label" for="flowVP">Vice President</label><select id="flowVP" class="form-select" name="vice_president_id" data-skipping-searchable="Vice President">
                <option value="">Select Vice President</option>@foreach($centralReviewerOptions as $user)<option value="{{ $user->id }}" @selected($vicePresidentId===$user->id)>{{ $user->name }}</option>@endforeach
            </select></div>
            <p class="text-secondary mt-2">The Vice President always approves last. Configure the corresponding VP name, signature, and stamp in <a href="{{ route('student-skipping-grade.settings') }}">Approval Settings</a>. The signer currently configured is {{ $settings->signer_name_en ?: $settings->signer_name_kh ?: 'not set' }}. Submitted requests retain their saved signature and stamp.</p>
            <p class="text-secondary">Campus reviewers must be assigned to the selected campus. Central reviewers and the final Vice President must be assigned to Central (CEN). All reviewers need access to Students &gt; Skipping Grade. Assigned users can review their requests through My Approvals.</p>
            <p class="text-secondary">A campus can have multiple request creators. Give each staff member Create and Submit permissions and access to their campus in User / Access Management.</p>
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save Approval Flow</button>
        </form>
    </div></div>
</div>
@include('student-skipping-grade._workspace-end')
@endsection
@push('styles') @vite('resources/css/pages/student-skipping-grade.css') @endpush
@push('scripts') @vite('resources/js/studentSkippingGrade.js') @endpush
