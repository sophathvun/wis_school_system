@extends('layouts.app')
@section('title', 'Attendance Reports')
@section('page-header', '')
@section('content')
    @vite(['resources/css/pages/attendance.css', 'resources/js/attendanceReports.js'])
    <div class="att-settings-layout" data-att-reports data-base="{{ url('/attendance/reports') }}">
        <aside class="att-settings-nav card" aria-label="Attendance report sections">
            <div class="att-settings-nav-header">
                <strong>Reports</strong>
                <button type="button" class="btn btn-sm btn-outline-light" data-reports-toggle aria-expanded="true" aria-controls="attendanceReportTabs" aria-label="Collapse report tabs"><i class="ti ti-layout-sidebar-left-collapse" aria-hidden="true"></i></button>
            </div>
            <div class="att-settings-tabs" id="attendanceReportTabs" role="tablist" aria-orientation="vertical">
                <button type="button" id="attendanceDetailTab" role="tab" aria-controls="attendanceDetailPanel" aria-selected="true" data-report-tab="detail" title="Stu. Attendance Detail"><i class="ti ti-list-details" aria-hidden="true"></i><span>Stu. Attendance Detail</span></button>
                <button type="button" id="attendanceStatisticsTab" role="tab" aria-controls="attendanceStatisticsPanel" aria-selected="false" tabindex="-1" data-report-tab="statistics" title="Attendance Statistics"><i class="ti ti-chart-bar" aria-hidden="true"></i><span>Attendance Statistics</span></button>
            </div>
        </aside>
        <div class="att-settings-content">
            <section id="attendanceDetailPanel" role="tabpanel" aria-labelledby="attendanceDetailTab" data-report-panel="detail">
                <div class="card mb-3">
                    <div class="card-header att-header"><h3 class="card-title"><i class="ti ti-filter me-2"></i>Data Filters</h3></div>
                    <form class="card-body att-report-filters" data-report-filters>
                        @foreach(['academic_year_id' => 'Academic Year', 'campus_id' => 'Campus', 'grade_class' => 'Grade', 'student_id' => 'Student Name'] as $name => $label)
                            <div class="premium-form-field staff-form-field">
                                <label class="form-label" for="report-{{ $name }}">{{ $label }}</label>
                                <select class="form-select" id="report-{{ $name }}" name="{{ $name }}" aria-label="{{ $label }}"></select>
                            </div>
                        @endforeach
                        <div class="premium-form-field">
                            <label class="form-label" for="attendanceReportSearch">Search</label>
                            <input type="search" class="form-control" id="attendanceReportSearch" name="search" placeholder=" " autocomplete="off">
                        </div>
                        <div class="att-report-date-range">
                            @foreach(['date_from' => 'From Date', 'date_to' => 'To Date'] as $name => $label)
                                <div class="premium-form-field"><label class="form-label" for="report-{{ $name }}">{{ $label }}</label><input class="form-control" type="date" name="{{ $name }}" id="report-{{ $name }}" aria-label="{{ $label }}"></div>
                            @endforeach
                        </div>
                    </form>
                </div>
                <div class="card mb-3" data-student-profile hidden>
                    <div class="card-header att-header"><h3 class="card-title">Student Information</h3></div>
                    <div class="card-body att-report-student" data-student-profile-body></div>
                </div>
                <div class="card">
                    <div class="card-header att-header"><h3 class="card-title">Student Absent Record</h3></div>
                    <div class="table-responsive">
                        <table class="table table-vcenter att-report-table mb-0">
                            <thead><tr><th>No.</th><th>Date</th><th>Attendance Status</th><th>Reason</th></tr></thead>
                            <tbody data-report-rows><tr><td colspan="4" class="text-center text-secondary py-4">Select a student to view their absent and late records.</td></tr></tbody>
                        </table>
                    </div>
                    <div class="card-footer"><input type="hidden" id="attendance-report-per-page" value="15"><div id="attendance-report-pagination"></div><div class="text-danger small" role="status" data-report-error></div></div>
                </div>
            </section>
            <section class="card" id="attendanceStatisticsPanel" role="tabpanel" aria-labelledby="attendanceStatisticsTab" data-report-panel="statistics" hidden>
                <div class="card-header att-header"><h3 class="card-title">Attendance Statistics</h3></div>
                <div class="card-body"><p class="text-secondary mb-0">Attendance statistics reports.</p></div>
            </section>
        </div>
    </div>
@endsection
