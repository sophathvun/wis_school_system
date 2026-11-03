@extends('layouts.app')
@section('title','Student and Teacher Schedules')
@section('page-header')
    <div class="container-fluid"><div class="page-pretitle">Academics</div><h2 class="page-title">Student and Teacher Schedules</h2></div>
@endsection
@section('content')
    @vite(['resources/css/pages/schedules.css','resources/js/teachingSchedules.js'])
    <div data-teaching-schedules data-base="{{ url('/schedules') }}">
        <div class="card mb-3">
            <div class="card-header schedule-header"><h3 class="card-title"><i class="ti ti-filter me-2"></i>Data Filters</h3></div>
            <form class="card-body schedule-filters" data-schedule-filters>
                @foreach(['academic_year_id'=>'Academic Year','campus_id'=>'Campus','class_key'=>'Grade','teacher_id'=>'Teacher'] as $name=>$label)
                    <div class="premium-form-field staff-form-field"><label class="form-label" for="scheduleFilter-{{ $name }}">{{ $label }}</label><select class="form-select" id="scheduleFilter-{{ $name }}" name="{{ $name }}"></select></div>
                @endforeach
                <div class="premium-form-field"><label class="form-label" for="scheduleSearch">Search</label><input type="search" class="form-control" id="scheduleSearch" name="search" placeholder="Subject or teacher"></div>
            </form>
        </div>
        <section class="card">
            <div class="card-header schedule-header flex-wrap gap-2"><div><h3 class="card-title">Weekly Teaching Sessions</h3><small>Publish a session to make it available automatically in subject attendance.</small></div><button type="button" class="btn btn-light ms-auto" data-schedule-add hidden><i class="ti ti-plus me-1"></i>Add Session</button></div>
            <div class="card-body">
                <div class="schedule-summary mb-3" data-schedule-summary role="status"></div>
                <div class="table-responsive"><table class="table table-vcenter schedule-table"><thead><tr><th>Day / Time</th><th>Campus / Grade</th><th>Subject / Teacher</th><th>Effective Dates</th><th>Status</th><th>Action</th></tr></thead><tbody data-schedule-list><tr><td colspan="6">Loading schedules…</td></tr></tbody></table></div>
                <p class="form-hint mt-3">Drafts are visible to schedule managers only. Published sessions are protected; use Create Revision to change a session from a new effective date. Teachers need Attendance permissions to record attendance.</p>
            </div>
        </section>
        <div class="modal fade" id="teachingScheduleModal" tabindex="-1" aria-labelledby="teachingScheduleTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><form class="modal-content" data-schedule-editor>
                <div class="modal-header schedule-header"><h3 class="modal-title" id="teachingScheduleTitle">Add Teaching Session</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="alert alert-info" data-schedule-revision hidden>Publish this revision to replace the original session from the new effective date. Earlier attendance is retained.</div>
                    <input type="hidden" name="id"><input type="hidden" name="version" value="0"><input type="hidden" name="replaces_id">
                    <div class="schedule-editor-fields">
                        @foreach(['academic_year_id'=>'Academic Year','campus_id'=>'Campus','class_key'=>'Grade','teacher_id'=>'Subject Teacher'] as $name=>$label)
                            <div class="premium-form-field staff-form-field"><label class="form-label" for="scheduleEdit-{{ $name }}">{{ $label }}</label><select class="form-select" id="scheduleEdit-{{ $name }}" name="{{ $name }}" required></select></div>
                        @endforeach
                        <div class="premium-form-field"><label class="form-label" for="scheduleSubject">Subject</label><input type="text" class="form-control" id="scheduleSubject" name="subject" maxlength="100" placeholder="Subject" required></div>
                        <div class="premium-form-field staff-form-field"><label class="form-label" for="scheduleWeekday">Day</label><select class="form-select" id="scheduleWeekday" name="weekday">@foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $index=>$day)<option value="{{ $index }}">{{ $day }}</option>@endforeach</select></div>
                        <div class="premium-form-field staff-form-field"><label class="form-label" for="schedulePeriod">Session Period</label><select class="form-select" id="schedulePeriod" name="period_id" required></select></div>
                        <div class="schedule-period-preview"><small class="text-secondary">Session Time</small><strong class="d-block" data-schedule-period-time>Select a session period</strong></div>
                        <div class="premium-form-field"><label class="form-label" for="scheduleFrom">Effective From</label><input type="date" class="form-control" id="scheduleFrom" name="effective_from" required></div>
                        <div class="premium-form-field"><label class="form-label" for="scheduleUntil">Effective Until</label><input type="date" class="form-control" id="scheduleUntil" name="effective_until" required></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" data-schedule-save>Save Draft</button></div>
            </form></div>
        </div>
    </div>
@endsection
