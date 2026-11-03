@extends('layouts.app')
@section('title','Attendance Settings')
@section('page-header', '')
@section('content')
    @vite(['resources/css/pages/attendance.css','resources/js/attendanceCalendar.js'])
    <div class="att-settings-layout" data-att-settings>
        <aside class="att-settings-nav card" aria-label="Attendance settings sections">
            <div class="att-settings-nav-header"><strong>Settings</strong><button type="button" class="btn btn-sm btn-outline-light" data-att-settings-toggle aria-expanded="true" aria-controls="attSettingsTabs" aria-label="Collapse settings tabs"><i class="ti ti-layout-sidebar-left-collapse" aria-hidden="true"></i></button></div>
            <div class="att-settings-tabs" id="attSettingsTabs" role="tablist" aria-orientation="vertical">
                <button type="button" id="attWeekdayTab" role="tab" aria-controls="attWeekdayPanel" aria-selected="true" data-att-settings-tab="weekdays" title="School Week/Day"><i class="ti ti-calendar-week" aria-hidden="true"></i><span>School Week/Day</span></button>
                <button type="button" id="attHolidayTab" role="tab" aria-controls="attHolidayPanel" aria-selected="false" tabindex="-1" data-att-settings-tab="holidays" title="Public Holiday"><i class="ti ti-calendar-off" aria-hidden="true"></i><span>Public Holiday</span></button>
                <button type="button" id="attScheduleTab" role="tab" aria-controls="attSchedulePanel" aria-selected="false" tabindex="-1" data-att-settings-tab="schedules" title="Schedule-based Subject Attendance"><i class="ti ti-calendar-time" aria-hidden="true"></i><span>Schedule-based Subject Attendance</span></button>
            </div>
        </aside>
        <div class="att-settings-content">
    <section class="card mb-3" data-attendance-calendar data-base="{{ url('/attendance') }}">
        <div class="card-header att-header"><h3 class="card-title" data-att-settings-title>School Week/Day</h3></div>
        <form class="card-body" data-calendar-form>
            <p class="text-secondary">Approved leave follows this calendar for the selected academic year and campus. Default: Monday–Friday, with no holidays configured.</p>
            <div class="att-leave-fields mb-3">
                <div class="premium-form-field staff-form-field"><label class="form-label" for="calendarYear">Academic Year</label><select class="form-select" id="calendarYear" name="academic_year_id" required></select></div>
                <div class="premium-form-field staff-form-field"><label class="form-label" for="calendarCampus">Campus</label><select class="form-select" id="calendarCampus" name="campus_id" required></select></div>
            </div>
            <fieldset class="att-leave-periods mb-3" id="attWeekdayPanel" role="tabpanel" aria-labelledby="attWeekdayTab" data-att-settings-panel="weekdays"><legend>School Weekdays</legend><div class="d-flex flex-wrap gap-3">
                @foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day=>$label)
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="weekdays[]" value="{{ $day }}"><span class="form-check-label">{{ $label }}</span></label>
                @endforeach
            </div></fieldset>
            <fieldset class="att-holiday-list-panel mb-3" id="attHolidayPanel" role="tabpanel" aria-labelledby="attHolidayTab" data-att-settings-panel="holidays" hidden disabled>
                <div class="card">
                    <div class="card-header att-header"><h3 class="card-title">Public Holiday List</h3><button type="button" class="btn btn-light ms-auto" data-calendar-holiday-open><i class="ti ti-plus me-1"></i>Add Holiday</button></div>
                    <div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>Date</th><th>Description</th><th>Action</th></tr></thead><tbody data-calendar-holiday-list></tbody></table></div>
                    <div class="card-footer text-secondary small">Changes apply after clicking Save Calendar.</div>
                </div>
                <textarea name="holidays" id="calendarHolidays" hidden aria-label="Holiday dates"></textarea>
            </fieldset>
            <div class="d-flex justify-content-between align-items-center gap-2"><span data-calendar-message role="status"></span><button class="btn btn-primary" type="submit" data-calendar-save disabled>Save Calendar</button></div>
        </form>
    </section>
    <div class="card" id="attSchedulePanel" role="tabpanel" aria-labelledby="attScheduleTab" data-att-settings-panel="schedules" hidden>
        <div class="card-header att-header"><h3 class="card-title"><i class="ti ti-calendar-time me-2"></i>Schedule-based Subject Attendance</h3></div>
        <div class="card-body">
            <p>Manage teachers, classes, subjects, and session times in Student and Teacher Schedules. Published sessions appear automatically in subject attendance on their scheduled dates.</p>
            <p class="text-secondary">Use a dated revision when a published session changes. Existing attendance records stay linked to the original schedule. Admin-Daily Attendance works independently of teacher schedules.</p>
            <a class="btn btn-primary" href="{{ route('schedules.index') }}"><i class="ti ti-calendar-time me-2"></i>Student and Teacher Schedules</a>
        </div>
    </div>
        </div>
    </div>
    <div class="modal fade" id="calendarHolidayModal" tabindex="-1" aria-labelledby="calendarHolidayTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" data-holiday-form>
            <div class="modal-header att-header"><h3 class="modal-title" id="calendarHolidayTitle">Add Holiday</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="premium-form-field mb-3"><label class="form-label" for="calendarHolidayDate">Holiday Date</label><input type="date" class="form-control" id="calendarHolidayDate" name="holiday_date" aria-label="Holiday date" required></div>
                <div class="premium-form-field"><label class="form-label" for="calendarHolidayDescription">Holiday Description</label><input class="form-control" id="calendarHolidayDescription" name="holiday_description" maxlength="200" placeholder="Holiday name" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" data-calendar-add-holiday>Add Holiday</button></div>
        </form></div>
    </div>
@endsection
