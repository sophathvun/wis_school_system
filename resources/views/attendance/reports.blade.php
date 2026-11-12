@extends('layouts.app')
@section('title', 'Attendance Reports')
@section('page-header', '')
@section('content')
    @php($attendancePermissions = \App\Support\AttendancePermissions::flags(auth()->user()))
    @vite(['resources/css/pages/attendance.css', 'resources/js/attendanceReports.js'])
    <div class="att-settings-layout" data-att-reports data-permissions="{{ json_encode($attendancePermissions) }}" data-base="{{ url('/attendance/reports') }}">
        <aside class="att-settings-nav card" aria-label="Attendance report sections">
            <div class="att-settings-nav-header">
                <strong>Reports</strong>
                <button type="button" class="btn btn-sm btn-outline-light" data-reports-toggle aria-expanded="true" aria-controls="attendanceReportTabs" aria-label="Collapse report tabs"><i class="ti ti-layout-sidebar-left-collapse" aria-hidden="true"></i></button>
            </div>
            <div class="att-settings-tabs" id="attendanceReportTabs" role="tablist" aria-orientation="vertical">
                <button type="button" id="attendanceDetailTab" role="tab" aria-controls="attendanceDetailPanel" aria-selected="true" data-report-tab="detail" @unless($attendancePermissions['detail']) hidden disabled @endunless title="Stu. Attendance Detail"><i class="ti ti-list-details" aria-hidden="true"></i><span>Stu. Attendance Detail</span></button>
                <button type="button" id="attendanceStatisticsTab" role="tab" aria-controls="attendanceStatisticsPanel" aria-selected="false" tabindex="-1" data-report-tab="statistics" @unless($attendancePermissions['statistics']) hidden disabled @endunless title="Attendance Statistics"><i class="ti ti-chart-bar" aria-hidden="true"></i><span>Attendance Statistics</span></button>
            </div>
        </aside>
        <div class="att-settings-content">@if(!$attendancePermissions['detail'] && !$attendancePermissions['statistics'])<div class="card card-body text-secondary">No attendance report tabs are enabled for your account.</div>@endif
            <section id="attendanceDetailPanel" role="tabpanel" aria-labelledby="attendanceDetailTab" data-report-panel="detail">
                <div class="card mb-3">
                    <div class="card-header att-header"><h3 class="card-title"><i class="ti ti-filter me-2"></i>Data Filters</h3><button type="button" class="btn btn-sm btn-outline-light ms-auto" data-report-filter-toggle aria-expanded="true" aria-controls="attendanceReportFilters" aria-label="Collapse data filters"><i class="ti ti-chevron-up" aria-hidden="true"></i></button></div>
                    <form class="card-body att-report-filters" id="attendanceReportFilters" data-report-filters><input type="hidden" name="report" value="detail">
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
                        <div class="premium-form-field att-report-date-range" data-report-date-range>
                            <label class="form-label" for="attendanceReportDateRange">Date Range</label>
                            <input class="form-control" id="attendanceReportDateRange" placeholder=" " autocomplete="off" aria-label="Date range, DD-MMM-YYYY to DD-MMM-YYYY">
                            <input type="hidden" name="date_from"><input type="hidden" name="date_to">
                            <button type="button" class="date-picker-trigger date-picker-calendar-button" data-range-toggle aria-label="Open date range calendar" aria-expanded="false" aria-controls="attendanceReportRangePopup"><i class="ti ti-calendar" aria-hidden="true"></i></button>
                            <div class="date-picker-popup att-range-popup d-none" id="attendanceReportRangePopup" role="dialog" aria-label="Select date range"></div>
                        </div>
                    </form>
                </div>
                <div class="card mb-3" data-student-profile hidden>
                    <div class="card-header att-header"><h3 class="card-title">Student Information</h3></div>
                    <div class="card-body att-report-student" data-student-profile-body></div>
                </div>
                <div class="card">
                    <div class="card-header att-header flex-wrap gap-2"><h3 class="card-title">Student Absent Record</h3><div class="ms-auto d-flex gap-2"><button type="button" class="btn btn-sm btn-outline-light" data-attendance-export="print" @unless($attendancePermissions['print']) hidden @endunless disabled><i class="ti ti-printer me-1"></i>Print</button><button type="button" class="btn btn-sm btn-outline-light" data-attendance-export="xlsx" @unless($attendancePermissions['excel']) hidden @endunless disabled><i class="ti ti-file-spreadsheet me-1"></i>Excel</button><button type="button" class="btn btn-sm btn-outline-light" data-attendance-export="pdf" @unless($attendancePermissions['pdf']) hidden @endunless disabled><i class="ti ti-file-type-pdf me-1"></i>PDF</button></div></div>
                    <div class="table-responsive">
                        <table class="table table-vcenter att-report-table mb-0">
                            <thead><tr><th>No.</th><th>Date</th><th>Attendance Status</th><th>Reason</th></tr></thead>
                            <tbody data-report-rows><tr><td colspan="4" class="text-center text-secondary py-4">Select a student to view their absent and late records.</td></tr></tbody>
                        </table>
                    </div>
                    <div class="card-footer"><input type="hidden" id="attendance-report-per-page" value="15"><div id="attendance-report-pagination"></div><div class="text-danger small" role="status" data-report-error></div></div>
                </div>
            </section>
            <section id="attendanceStatisticsPanel" role="tabpanel" aria-labelledby="attendanceStatisticsTab" data-report-panel="statistics" hidden>
                <div class="card mb-3">
                    <div class="card-header att-header"><h3 class="card-title"><i class="ti ti-filter me-2"></i>Data Filters</h3><button type="button" class="btn btn-sm btn-outline-light ms-auto" data-statistics-filter-toggle aria-expanded="true" aria-controls="attendanceStatisticsFilters" aria-label="Collapse data filters"><i class="ti ti-chevron-up" aria-hidden="true"></i></button></div>
                    <form class="card-body att-report-filters att-statistics-filters" id="attendanceStatisticsFilters" data-statistics-filters><input type="hidden" name="report" value="statistics">
                        @foreach(['academic_year_id' => 'Academic Year', 'campus_id' => 'Campus', 'grade_class' => 'Grade', 'student_id' => 'Student Name'] as $name => $label)
                            <div class="premium-form-field staff-form-field"><label class="form-label" for="statistics-{{ $name }}">{{ $label }}</label><select class="form-select" id="statistics-{{ $name }}" name="{{ $name }}" aria-label="{{ $label }}"></select></div>
                        @endforeach
                        <div class="premium-form-field att-report-date-range" data-statistics-date-range>
                            <label class="form-label" for="attendanceStatisticsDateRange">Date Range</label>
                            <input class="form-control" id="attendanceStatisticsDateRange" placeholder=" " autocomplete="off" aria-label="Date range, DD-MMM-YYYY to DD-MMM-YYYY">
                            <input type="hidden" name="date_from"><input type="hidden" name="date_to">
                            <button type="button" class="date-picker-trigger date-picker-calendar-button" data-range-toggle aria-label="Open date range calendar" aria-expanded="false" aria-controls="attendanceStatisticsRangePopup"><i class="ti ti-calendar" aria-hidden="true"></i></button>
                            <div class="date-picker-popup att-range-popup d-none" id="attendanceStatisticsRangePopup" role="dialog" aria-label="Select date range"></div>
                        </div>
                    </form>
                </div>
                <div class="card">
                    <div class="card-header att-header flex-wrap gap-2"><h3 class="card-title">Student Attendance Statistics</h3><div class="ms-auto d-flex gap-2"><button type="button" class="btn btn-sm btn-outline-light" data-statistics-export="print" @unless($attendancePermissions['print']) hidden @endunless disabled><i class="ti ti-printer me-1"></i>Print</button><button type="button" class="btn btn-sm btn-outline-light" data-statistics-export="xlsx" @unless($attendancePermissions['excel']) hidden @endunless disabled><i class="ti ti-file-spreadsheet me-1"></i>Excel</button><button type="button" class="btn btn-sm btn-outline-light" data-statistics-export="pdf" @unless($attendancePermissions['pdf']) hidden @endunless disabled><i class="ti ti-file-type-pdf me-1"></i>PDF</button></div></div>
                    <div class="table-responsive"><table class="table table-vcenter att-report-table mb-0"><thead><tr><th>No.</th><th>Photo</th><th>Student</th><th>Grade</th><th>Total Absent</th><th>Total E</th><th>Total U</th><th>Total Tardy</th><th>Action</th></tr></thead><tbody data-statistics-rows></tbody></table></div>
                    <div class="card-footer"><input type="hidden" id="attendance-statistics-per-page" value="15"><div id="attendance-statistics-pagination"></div><div class="text-danger small" role="status" data-statistics-error></div></div>
                </div>
            </section>
        </div>
    </div>
@endsection
