@extends('layouts.app')
@section('title',request()->route('workspace') === 'settings' ? 'Attendance Settings' : 'Student Attendance')
@section('page-header')
<div class="container-fluid"><div class="page-pretitle">Academics</div><h2 class="page-title"><i class="ti ti-calendar-check me-2"></i>{{ request()->route('workspace') === 'settings' ? 'Attendance Settings' : 'Student Attendance' }}</h2></div>
@endsection
@section('content')
@vite(['resources/css/pages/attendance.css','resources/js/attendance.js'])
<div data-attendance data-base="{{ url('/attendance') }}" data-workspace="{{ request()->route('workspace') ?? '' }}">
    <div class="card mb-3"><div class="card-header att-header"><h3 class="card-title" data-att-filter-title>Attendance Filters</h3></div>
        <form class="card-body att-filters" data-att-filters>
            <div><label class="form-label" for="attYear">Academic Year</label><select class="form-select" id="attYear" name="academic_year_id" required></select></div>
            <div><label class="form-label" for="attCampus">Campus</label><select class="form-select" id="attCampus" name="campus_id"><option value="">All accessible campuses</option></select></div>
            <div @if(in_array(request()->route('workspace'), ['daily', 'settings'], true)) hidden @endif>
                <label class="form-label" for="attDate" data-att-date-label>Attendance Date</label>
                <div class="date-picker" data-att-date-picker data-premium-date-portal="true" data-premium-date-placement="auto">
                    <input class="form-control" type="text" id="attDate" data-premium-date-input placeholder="DD-MM-YYYY" autocomplete="off" required>
                    <input type="hidden" name="date" data-premium-date-value value="{{ now('Asia/Bangkok')->format('Y-m-d') }}">
                    <button type="button" class="date-picker-trigger date-picker-calendar-button" data-premium-date-toggle aria-label="Open attendance calendar" aria-expanded="false" aria-controls="attDateCalendar"><i class="ti ti-calendar" aria-hidden="true"></i></button>
                    <div class="date-picker-popup d-none" id="attDateCalendar" role="dialog" aria-label="Choose attendance date">
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
                        <div class="att-calendar-footer"><button type="button" class="btn btn-sm btn-primary" data-premium-date-today>Today</button></div>
                    </div>
                </div>
            </div>
            <div @if(in_array(request()->route('workspace'), ['daily', 'settings'], true)) hidden @endif><label class="form-label" for="attMode">Workspace</label><select class="form-select" id="attMode" name="mode"><option value="mine">My Subject Sessions</option></select></div>
            <div data-att-daily-class hidden><label class="form-label" for="attDailyClass">Grade</label><select class="form-select" id="attDailyClass" name="class_id"><option value="">Select grade</option></select></div>
            <div data-att-daily-filter hidden><label class="form-label" for="attDailyStudent">Student Name</label><select class="form-select" id="attDailyStudent" data-daily-student-filter><option value="">All students</option></select></div>
            <div data-att-daily-filter hidden><label class="form-label" for="attDailySearch">Search</label><div class="input-icon"><input type="search" id="attDailySearch" class="form-control" data-daily-search placeholder="Name or student ID"><span class="input-icon-addon"><i class="ti ti-search" aria-hidden="true"></i></span></div></div>
        </form>
    </div>
    <section class="card mb-3" data-att-daily hidden>
        <div class="card-header att-header flex-wrap gap-2"><h3 class="card-title">Daily Class Attendance</h3><button type="button" class="btn btn-success ms-auto" data-daily-present disabled><i class="ti ti-checks me-1"></i>P — Apply Present to All</button></div>
        <div class="card-body">
            <p class="text-secondary">Select a campus, grade/class, and date. Missing one, two, or more subject sessions still counts as one attendance day. Add the session count and remarks for context.</p>
            <nav class="att-date-navigation" data-att-date-navigation aria-label="Daily attendance dates">
                <div class="att-date-navigation-heading"><strong>Attendance by date</strong><div class="att-counts" data-daily-counts></div><span data-att-date-month></span></div>
                <div class="att-date-navigation-controls">
                    <button type="button" class="btn btn-outline-secondary" data-att-date-offset="-1" aria-label="Previous day"><i class="ti ti-chevron-left" aria-hidden="true"></i></button>
                    <div class="att-date-days" data-att-date-days></div>
                    <button type="button" class="btn btn-outline-secondary" data-att-date-offset="1" aria-label="Next day"><i class="ti ti-chevron-right" aria-hidden="true"></i></button>
                    <button type="button" class="btn btn-outline-primary" data-att-date-today>Today</button>
                </div>
            </nav>
            <form data-daily-form><div class="table-responsive"><table class="table table-vcenter att-table att-daily-table"><thead><tr><th>No.</th><th>Photo</th><th>Student</th><th>Family</th><th>Daily Status</th><th>Reason</th><th>Added by</th></tr></thead><tbody data-daily-students><tr><td colspan="7">Select Academic Year, Campus, and Grade/Class.</td></tr></tbody></table></div>
                <div class="att-save-bar"><span data-daily-message role="status" aria-live="polite">Changes save automatically.</span><button type="submit" class="btn btn-outline-primary" data-daily-save hidden disabled>Retry Save</button></div>
            </form>
            <div class="form-hint mt-3">P: Present · EX: Excused absence · UX: Unexcused absence · L: Late. EX and UX count as one absence day each; missed sessions are not added to the daily total. Select EX, UX, or L to choose absent periods and enter a reason.</div>
            <details class="att-history mt-3"><summary>Daily Register History</summary><div data-daily-history class="mt-2"></div></details>
        </div>
    </section>
    <div class="att-summary" data-att-summary></div>
    <section class="card mb-3"><div class="card-header att-header flex-wrap gap-2"><div><h3 class="card-title">Subject Sessions</h3><small>Open a class to take attendance for its scheduled subject period.</small></div></div><div class="card-body att-sessions" data-att-sessions><div class="text-secondary">Loading your sessions…</div></div></section>
    <details class="card mb-3" data-att-history-panel><summary class="card-header att-header">Attendance History &amp; Reports</summary><div class="card-body">
        <form class="att-history-filters" data-att-history-filters>
            <div><label class="form-label" for="attFrom">From</label><input type="date" class="form-control" id="attFrom" name="from" value="{{ now('Asia/Bangkok')->startOfMonth()->format('Y-m-d') }}" required></div>
            <div><label class="form-label" for="attTo">To</label><input type="date" class="form-control" id="attTo" name="to" value="{{ now('Asia/Bangkok')->format('Y-m-d') }}" required></div>
            <button class="btn btn-primary" type="submit">View History</button><button class="btn" type="button" data-att-csv>Export CSV</button>
        </form><p class="form-hint mt-2">Uses the selected academic year and campus. Draft counts are provisional; these are subject-session marks, not unique daily students.</p>
        <div data-att-history-results></div>
    </div></details>
    <section class="card" data-att-register hidden>
        <div class="card-header att-header flex-wrap gap-2"><div><h3 class="card-title" data-att-register-title>Class Register</h3><small data-att-register-subtitle></small></div><button type="button" class="btn btn-outline-light ms-auto" data-att-close aria-label="Close register"><i class="ti ti-x"></i></button></div>
        <div class="card-body">
            <div class="att-register-toolbar"><div class="input-icon"><span class="input-icon-addon"><i class="ti ti-search"></i></span><input type="search" class="form-control" data-att-search placeholder="Find student by name or ID" aria-label="Search register"></div><button type="button" class="btn btn-outline-success" data-att-all>Mark Unmarked Present</button><span class="badge bg-blue-lt" data-att-register-status></span></div>
            <p class="form-hint">Choose a status for every student. Unmarked students are never counted as present. Late minutes and notes are optional.</p>
            <div class="att-counts" data-att-counts></div>
            <form data-att-mark-form><div class="table-responsive"><table class="table table-vcenter att-table"><thead><tr><th>No.</th><th>Student</th><th>Attendance</th><th>Late (min)</th><th>Note</th></tr></thead><tbody data-att-students></tbody></table></div>
                <div class="att-save-bar"><span class="text-secondary" data-att-save-message>Changes are not saved automatically.</span><button class="btn" type="button" data-att-draft>Save Draft</button><button class="btn btn-primary" type="submit" data-att-submit>Submit Attendance</button><button class="btn btn-outline-warning" type="button" data-att-reopen hidden>Reopen for Correction</button></div>
            </form>
            <details class="att-history mt-3"><summary>Attendance History</summary><div data-att-history class="mt-2"></div></details>
        </div>
    </section>
</div>
@endsection
