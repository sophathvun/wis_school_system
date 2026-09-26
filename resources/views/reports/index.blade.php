@extends('layouts.app')
@section('title','Reports')
@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Management</div>
                <h2 class="page-title">Reports</h2>
            </div>
        </div>
    </div>
    @vite('resources/js/reportsIndex.js')
@endsection
@section('content')
@php
    $reportStubTypes = ['withdrawn-students', 'moeys-sikkhakarik-book', 'moeys-id-number-book'];
    $reportTypeIcons = [
        'student-list' => 'ti-list-details',
        'student-contact-list' => 'ti-address-book',
        'score-list' => 'ti-notes',
        'attendance-list' => 'ti-calendar-check',
        'student-statistics' => 'ti-chart-bar',
        'student-statistics-detail' => 'ti-chart-dots-3',
        'withdrawn-students' => 'ti-user-minus',
        'student-id-books-moeys' => 'ti-id-badge-2',
        'moeys-sikkhakarik-book' => 'ti-book-2',
        'moeys-id-number-book' => 'ti-address-book',
    ];
    $khmerReportTypeTabs = ['moeys-sikkhakarik-book', 'moeys-id-number-book'];
    $isReportStub = in_array($type, $reportStubTypes, true);
@endphp
<div class="row g-3 reports-workspace" data-report-type="{{ $type }}" data-report-date="{{ $filters['report_date'] ?? now()->format('Y-m-d') }}"><div class="col-lg-3 report-tabs-column"><div class="card reports-tabs-card"><div class="card-header reports-tabs-header"><h3 class="card-title">Report Types</h3><button type="button" class="btn btn-icon btn-outline-primary reports-tabs-toggle" aria-expanded="true" aria-controls="reportsTypeList" title="Minimize report types"><i class="ti ti-layout-sidebar-left-collapse"></i></button></div><div class="list-group list-group-flush reports-tabs-list" id="reportsTypeList">@foreach($reportTypes as $key=>$label)<a href="{{ route('reports.index',['type'=>$key]) }}" class="list-group-item list-group-item-action {{ $type===$key?'active':'' }} {{ in_array($key, $khmerReportTypeTabs, true) ? 'report-tab-khmer' : '' }}" data-report-tab-label="{{ $label }}"><i class="ti {{ $reportTypeIcons[$key] ?? 'ti-file-text' }} me-2"></i>{{ $label }}</a>@endforeach</div></div></div><div class="col-lg-9 report-content-column"><div class="card mb-3"><div class="card-header {{ $type === 'student-contact-list' ? 'report-title-header-dark' : '' }}"><h3 class="card-title {{ $type === 'student-id-books-moeys' ? 'student-id-book-title' : '' }}">@if($type === 'student-id-books-moeys')<img src="{{ asset('images/moeys_logo.png') }}" alt="MoEYS" class="student-id-book-title-logo"><span>សៀវភៅចុះអត្តលេខសិស្ស (MoEYS)</span>@else{{ $type === 'student-statistics-detail' ? 'STUDENT STATISTICS (Details)' : $reportTypes[$type] }}@endif</h3></div><form method="get" action="{{ route('reports.index') }}"><input type="hidden" name="type" value="{{ $type }}"><div class="card-body"><div class="{{ ($type === 'student-list' || $type === 'student-contact-list') ? 'report-student-filter-row' : 'row g-3' }}">
@if(($type === 'student-list' || $type === 'student-contact-list'))
<div class="report-filter-field"><label class="form-label">Period</label><div class="report-native-select"><select name="period_type" class="form-select report-select-with-arrow" data-report-period-select><option value="all" @selected(($filters['period_type']??'all')==='all')>Regular + Summer</option><option value="regular" @selected(($filters['period_type']??'')==='regular')>Regular</option><option value="summer" @selected(($filters['period_type']??'')==='summer')>Summer</option></select><i class="ti ti-chevron-down report-native-select-arrow"></i></div></div>
<div class="report-filter-field"><label class="form-label">Academic Year</label><div class="report-filter-combobox" data-target="reportAcademicYearValue"><button type="button" class="report-filter-toggle"><span>{{ $academicYears->firstWhere('id',(int)($filters['academic_year_id']??0))?->academic_year ?: 'All Academic Years' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Academic Year"><div class="report-filter-options"><button type="button" data-value="">All Academic Years</button>@foreach($academicYears as $year)<button type="button" data-value="{{ $year->id }}">{{ $year->academic_year }}</button>@endforeach</div></div></div><input type="hidden" name="academic_year_id" id="reportAcademicYearValue" value="{{ $filters['academic_year_id']??'' }}"></div>
<div class="report-filter-field"><label class="form-label">Campus</label><div class="report-filter-combobox" data-target="reportCampusValue"><button type="button" class="report-filter-toggle"><span>{{ $campuses->firstWhere('id',(int)($filters['campus_id']??0))?->campus_name_en ?: '' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Campus"><div class="report-filter-options"><button type="button" data-value="">All Campuses</button>@foreach($campuses as $campus)<button type="button" data-value="{{ $campus->id }}">{{ $campus->campus_name_en }}</button>@endforeach</div></div></div><input type="hidden" name="campus_id" id="reportCampusValue" value="{{ $filters['campus_id']??'' }}"></div>
<div class="report-filter-field"><label class="form-label">Grade</label><div class="report-filter-combobox" data-target="reportGradeClassValue"><button type="button" class="report-filter-toggle"><span>{{ data_get(collect($gradeClassOptions)->firstWhere('value',$filters['grade_class']??''), 'label', '') }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Grade"><div class="report-filter-options"><button type="button" data-value="">All Grade</button>@foreach($gradeClassOptions as $option)<button type="button" data-value="{{ $option['value'] }}">{{ $option['label'] }}</button>@endforeach</div></div></div><input type="hidden" name="grade_class" id="reportGradeClassValue" value="{{ $filters['grade_class']??'' }}"></div>
<div class="report-filter-field"><label class="form-label">Group</label><div class="report-filter-combobox" data-target="reportGroupValue"><button type="button" class="report-filter-toggle"><span>{{ $groupOptions->firstWhere('session_id',(int)($filters['session_id']??0))?->session_short_name ?: '' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Group"><div class="report-filter-options"><button type="button" data-value="">All Groups</button>@foreach($groupOptions as $group)<button type="button" data-value="{{ $group->session_id }}">{{ $group->session_short_name }}</button>@endforeach</div></div></div><input type="hidden" name="session_id" id="reportGroupValue" value="{{ $filters['session_id']??'' }}"></div><div class="report-filter-actions"><a class="btn btn-outline-secondary" href="{{ route('reports.index',['type'=>$type]) }}"><i class="ti ti-rotate-2 me-1"></i>Refresh</a></div>
@elseif(in_array($type, ['student-statistics', 'student-statistics-detail'], true))
<div class="{{ $type === 'student-statistics-detail' ? 'col-md-3' : 'col-md-4' }} report-filter-field"><label class="form-label">Period</label><div class="report-native-select"><select name="period_type" class="form-select report-select-with-arrow" data-report-period-select><option value="all" @selected(($filters['period_type']??'all')==='all')>Regular + Summer</option><option value="regular" @selected(($filters['period_type']??'')==='regular')>Regular</option><option value="summer" @selected(($filters['period_type']??'')==='summer')>Summer</option></select><i class="ti ti-chevron-down report-native-select-arrow"></i></div></div>
<div class="{{ $type === 'student-statistics-detail' ? 'col-md-3' : 'col-md-4' }} report-filter-field"><label class="form-label">Academic Year</label><div class="report-filter-combobox" data-target="reportAcademicYearValue"><button type="button" class="report-filter-toggle"><span>{{ $academicYears->firstWhere('id',(int)($filters['academic_year_id']??0))?->academic_year ?: 'All Academic Years' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Academic Year"><div class="report-filter-options"><button type="button" data-value="">All Academic Years</button>@foreach($academicYears as $year)<button type="button" data-value="{{ $year->id }}">{{ $year->academic_year }}</button>@endforeach</div></div></div><input type="hidden" name="academic_year_id" id="reportAcademicYearValue" value="{{ $filters['academic_year_id']??'' }}"></div>
<div class="{{ $type === 'student-statistics-detail' ? 'col-md-3' : 'col-md-4' }} report-filter-field"><label class="form-label">Campus</label><div class="report-filter-combobox" data-target="reportCampusValue"><button type="button" class="report-filter-toggle"><span>{{ $campuses->firstWhere('id',(int)($filters['campus_id']??0))?->campus_name_en ?: 'All Campuses' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Campus"><div class="report-filter-options"><button type="button" data-value="">All Campuses</button>@foreach($campuses as $campus)<button type="button" data-value="{{ $campus->id }}">{{ $campus->campus_name_en }}</button>@endforeach</div></div></div><input type="hidden" name="campus_id" id="reportCampusValue" value="{{ $filters['campus_id']??'' }}"></div>
@if($type === 'student-statistics-detail')<div class="col-md-3 report-filter-field"><label class="form-label">Report Month</label><input type="month" name="month" class="form-control" value="{{ $filters['month'] ?? now()->format('Y-m') }}"></div>@endif
@elseif($type === 'attendance-list')
<div class="col-md-4 report-filter-field"><label class="form-label">Period</label><div class="report-native-select"><select name="period_type" class="form-select report-select-with-arrow" data-report-period-select><option value="all" @selected(($filters['period_type']??'all')==='all')>Regular + Summer</option><option value="regular" @selected(($filters['period_type']??'')==='regular')>Regular</option><option value="summer" @selected(($filters['period_type']??'')==='summer')>Summer</option></select><i class="ti ti-chevron-down report-native-select-arrow"></i></div></div>
<div class="col-md-4 report-filter-field"><label class="form-label">Academic Year</label><div class="report-filter-combobox" data-target="reportAcademicYearValue"><button type="button" class="report-filter-toggle"><span>{{ $academicYears->firstWhere('id',(int)($filters['academic_year_id']??0))?->academic_year ?: 'All Academic Years' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Academic Year"><div class="report-filter-options"><button type="button" data-value="">All Academic Years</button>@foreach($academicYears as $year)<button type="button" data-value="{{ $year->id }}">{{ $year->academic_year }}</button>@endforeach</div></div></div><input type="hidden" name="academic_year_id" id="reportAcademicYearValue" value="{{ $filters['academic_year_id']??'' }}"></div>
<div class="col-md-4 report-filter-field"><label class="form-label">Campus</label><div class="report-filter-combobox" data-target="reportCampusValue"><button type="button" class="report-filter-toggle"><span>{{ $campuses->firstWhere('id',(int)($filters['campus_id']??0))?->campus_name_en ?: 'All Campuses' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Campus"><div class="report-filter-options"><button type="button" data-value="">All Campuses</button>@foreach($campuses as $campus)<button type="button" data-value="{{ $campus->id }}">{{ $campus->campus_name_en }}</button>@endforeach</div></div></div><input type="hidden" name="campus_id" id="reportCampusValue" value="{{ $filters['campus_id']??'' }}"></div>
<div class="col-md-4 report-filter-field"><label class="form-label">Grade</label><div class="report-filter-combobox" data-target="reportGradeClassValue"><button type="button" class="report-filter-toggle"><span>{{ data_get(collect($gradeClassOptions)->firstWhere('value',$filters['grade_class']??''), 'label', 'All Grades') }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Grade"><div class="report-filter-options"><button type="button" data-value="">All Grades</button>@foreach($gradeClassOptions as $option)<button type="button" data-value="{{ $option['value'] }}">{{ $option['label'] }}</button>@endforeach</div></div></div><input type="hidden" name="grade_class" id="reportGradeClassValue" value="{{ $filters['grade_class']??'' }}"></div>
<div class="col-md-4"><label class="form-label">Month</label><input name="month" type="month" class="form-control" value="{{ $filters['month'] }}"></div>
@elseif($type === 'student-id-books-moeys')
<div class="col-md-3 report-filter-field"><label class="form-label">Period</label><div class="report-native-select"><select name="period_type" class="form-select report-select-with-arrow" data-report-period-select><option value="all" @selected(($filters['period_type']??'all')==='all')>Regular + Summer</option><option value="regular" @selected(($filters['period_type']??'')==='regular')>Regular</option><option value="summer" @selected(($filters['period_type']??'')==='summer')>Summer</option></select><i class="ti ti-chevron-down report-native-select-arrow"></i></div></div>
<div class="col-md-3 report-filter-field"><label class="form-label">Academic Year</label><div class="report-filter-combobox" data-target="reportAcademicYearValue"><button type="button" class="report-filter-toggle"><span>{{ $academicYears->firstWhere('id',(int)($filters['academic_year_id']??0))?->academic_year ?: 'All Academic Years' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Academic Year"><div class="report-filter-options"><button type="button" data-value="">All Academic Years</button>@foreach($academicYears as $year)<button type="button" data-value="{{ $year->id }}">{{ $year->academic_year }}</button>@endforeach</div></div></div><input type="hidden" name="academic_year_id" id="reportAcademicYearValue" value="{{ $filters['academic_year_id']??'' }}"></div>
<div class="col-md-3 report-filter-field"><label class="form-label">Book Level</label><div class="report-filter-combobox" data-target="reportIdBookLevelValue"><button type="button" class="report-filter-toggle"><span>{{ ['kindergarten'=>'Kindergarten', 'primary'=>'Primary', 'secondary'=>'Secondary'][$filters['id_book_level']??''] ?? 'Select Book Level' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Book Level"><div class="report-filter-options"><button type="button" data-value="">Select Book Level</button><button type="button" data-value="kindergarten">Kindergarten</button><button type="button" data-value="primary">Primary</button><button type="button" data-value="secondary">Secondary</button></div></div></div><input type="hidden" name="id_book_level" id="reportIdBookLevelValue" value="{{ $filters['id_book_level']??'' }}"></div>
<div class="col-md-3 report-filter-field"><label class="form-label">Campus</label><div class="report-filter-combobox" data-target="reportCampusValue"><button type="button" class="report-filter-toggle"><span>{{ $campuses->firstWhere('id',(int)($filters['campus_id']??0))?->campus_name_en ?: 'All Campuses' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Campus"><div class="report-filter-options"><button type="button" data-value="">All Campuses</button>@foreach($campuses as $campus)<button type="button" data-value="{{ $campus->id }}">{{ $campus->campus_name_en }}</button>@endforeach</div></div></div><input type="hidden" name="campus_id" id="reportCampusValue" value="{{ $filters['campus_id']??'' }}"></div>
<div class="col-md-4 report-filter-field"><div class="input-group id-book-list-code-group"><div class="id-book-list-code-input"><label class="form-label" for="idBookStartNumber">Start List Code</label><input id="idBookStartNumber" type="number" min="1" max="99999" step="1" class="form-control" name="id_book_start_number" placeholder="00001" data-id-book-start-number></div><button type="button" class="btn btn-primary" data-id-book-generate data-generate-url="{{ route('reports.id-book-list-codes.generate', ['type' => $type]) }}"><i class="ti ti-number me-1"></i>Generate</button></div><div class="form-hint">Generates 5-digit codes for the selected Academic Year, Book Level, and Campus.</div></div>
@elseif($isReportStub)
<div class="col-12">
    <div class="alert alert-info mb-0 report-placeholder-alert {{ in_array($type, $khmerReportTypeTabs, true) ? 'khmer-font-siemreap' : '' }}">
        {{ $reportTypes[$type] }} report tab is added. The independent report form and print layout can be configured next.
    </div>
</div>
@elseif($type === 'score-list')
<div class="col-md-3 report-filter-field"><label class="form-label">Period</label><div class="report-native-select"><select name="period_type" class="form-select report-select-with-arrow" data-report-period-select><option value="all" @selected(($filters['period_type']??'all')==='all')>Regular + Summer</option><option value="regular" @selected(($filters['period_type']??'')==='regular')>Regular</option><option value="summer" @selected(($filters['period_type']??'')==='summer')>Summer</option></select><i class="ti ti-chevron-down report-native-select-arrow"></i></div></div>
<div class="col-md-3 report-filter-field"><label class="form-label">Academic Year</label><div class="report-filter-combobox" data-target="reportAcademicYearValue"><button type="button" class="report-filter-toggle"><span>{{ $academicYears->firstWhere('id',(int)($filters['academic_year_id']??0))?->academic_year ?: 'All Academic Years' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Academic Year"><div class="report-filter-options"><button type="button" data-value="">All Academic Years</button>@foreach($academicYears as $year)<button type="button" data-value="{{ $year->id }}">{{ $year->academic_year }}</button>@endforeach</div></div></div><input type="hidden" name="academic_year_id" id="reportAcademicYearValue" value="{{ $filters['academic_year_id']??'' }}"></div>
<div class="col-md-3 report-filter-field"><label class="form-label">Campus</label><div class="report-filter-combobox" data-target="reportCampusValue"><button type="button" class="report-filter-toggle"><span>{{ $campuses->firstWhere('id',(int)($filters['campus_id']??0))?->campus_name_en ?: 'All Campuses' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Campus"><div class="report-filter-options"><button type="button" data-value="">All Campuses</button>@foreach($campuses as $campus)<button type="button" data-value="{{ $campus->id }}">{{ $campus->campus_name_en }}</button>@endforeach</div></div></div><input type="hidden" name="campus_id" id="reportCampusValue" value="{{ $filters['campus_id']??'' }}"></div>
<div class="col-md-3 report-filter-field"><label class="form-label">Grade</label><div class="report-filter-combobox" data-target="reportGradeClassValue"><button type="button" class="report-filter-toggle"><span>{{ data_get(collect($gradeClassOptions)->firstWhere('value',$filters['grade_class']??''), 'label', 'All Grades') }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Grade"><div class="report-filter-options"><button type="button" data-value="">All Grades</button>@foreach($gradeClassOptions as $option)<button type="button" data-value="{{ $option['value'] }}">{{ $option['label'] }}</button>@endforeach</div></div></div><input type="hidden" name="grade_class" id="reportGradeClassValue" value="{{ $filters['grade_class']??'' }}"></div>
@else
<div class="col-md-4"><label class="form-label">Academic Year</label><select name="academic_year_id" class="form-select"><option value="">All Academic Years</option>@foreach($academicYears as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year_id']??'')==$year->id)>{{ $year->academic_year }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label">Campus</label><select name="campus_id" class="form-select"><option value="">All Campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected(($filters['campus_id']??'')==$campus->id)>{{ $campus->campus_name_en }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label">Grade</label><select name="grade_id" class="form-select"><option value="">All Grades</option>@foreach($grades as $grade)<option value="{{ $grade->id }}" @selected(($filters['grade_id']??'')==$grade->id)>{{ $grade->grade }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label">Class ID</label><input name="class_id" type="number" min="1" class="form-control" value="{{ $filters['class_id']??'' }}"></div>
@endif
@if(in_array($type, ['student-list', 'student-contact-list', 'attendance-list', 'score-list'], true))
    <div class="row g-3 mt-2 report-print-options">
        @if(!in_array($type, ['attendance-list', 'score-list', 'student-id-books-moeys'], true))
            <div class="col-md-3">
                <label class="form-label">Print Format</label>
                <div class="report-native-select">
                    <select name="print_format" class="form-select report-select-with-arrow">
                        <option value="internal" @selected(($filters['print_format']??'internal')==='internal')>{{ $type === 'student-contact-list' ? 'Internal Student Contact List' : 'Internal Student List' }}</option>
                        <option value="moeys" @selected(($filters['print_format']??'')==='moeys')>MoEYS Khmer List</option>
                    </select>
                    <i class="ti ti-chevron-down report-native-select-arrow"></i>
                </div>
            </div>
        @endif
        <div class="col-md-3">
            <label class="form-label">Print Scope</label>
            <div class="report-native-select">
                <select name="print_scope" class="form-select report-select-with-arrow">
                    <option value="selected_class" @selected(($filters['print_scope']??'selected_class')==='selected_class')>Selected Class</option>
                    <option value="selected_classes" @selected(($filters['print_scope']??'')==='selected_classes')>Selected Classes</option>
                    <option value="all_classes" @selected(($filters['print_scope']??'')==='all_classes')>All Classes by Campus</option>
                </select>
                <i class="ti ti-chevron-down report-native-select-arrow"></i>
            </div>
        </div>
        @if($type === 'score-list')
            <div class="col-md-3">
                <label class="form-label">Print Type</label>
                <div class="report-native-select">
                    <select name="print_type" class="form-select report-select-with-arrow">
                        <option value="quarter_1" @selected(($filters['print_type']??'quarter_1')==='quarter_1')>Quarter 1</option>
                        <option value="quarter_2" @selected(($filters['print_type']??'')==='quarter_2')>Quarter 2</option>
                        <option value="quarter_3" @selected(($filters['print_type']??'')==='quarter_3')>Quarter 3</option>
                        <option value="quarter_4" @selected(($filters['print_type']??'')==='quarter_4')>Quarter 4</option>
                    </select>
                    <i class="ti ti-chevron-down report-native-select-arrow"></i>
                </div>
            </div>
        @endif
        @if(!in_array($type, ['attendance-list', 'score-list', 'student-id-books-moeys'], true))
            <div class="col-md-3">
                <label class="form-label">Report Date</label>
                <input type="date" name="report_date" class="form-control" value="{{ $filters['report_date'] ?? now()->format('Y-m-d') }}">
            </div>
        @endif
        <div class="col-md-3 report-classes-to-print-field">
            <label class="form-label">Classes to Print</label>
            <select name="print_grade_classes[]" class="form-select" multiple size="3">
                @foreach($gradeClassOptions as $option)
                    <option value="{{ $option['value'] }}" @selected(in_array($option['value'], $filters['print_grade_classes']??[], true))>{{ $option['label'] }}</option>
                @endforeach
            </select>
            <div class="form-hint">Use Ctrl/Cmd to select multiple classes.</div>
        </div>
    </div>
@endif
</div></div></form></div>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between gap-2 report-preview-header">
        <div class="report-preview-title-wrap">
            <h3 class="card-title mb-0">REPORT PREVIEW</h3>
            @if($type === 'student-id-books-moeys')
                <div class="report-preview-note">For new students only</div>
            @endif
        </div>
        @if(($type === 'student-list' || $type === 'student-contact-list'))
            <div class="report-summary-card">
                <div class="report-summary-item report-summary-total">
                    <div class="report-summary-label">Total Students</div>
                    <div class="report-summary-number">{{ number_format($studentSummary['total'] ?? 0) }}</div>
                    <div class="report-summary-gender">F: {{ number_format($studentSummary['total_female'] ?? 0) }} <span>|</span> M: {{ number_format($studentSummary['total_male'] ?? 0) }}</div>
                </div>
                <div class="report-summary-divider"></div>
                <div class="report-summary-item report-summary-new">
                    <div class="report-summary-label">New Students</div>
                    <div class="report-summary-number">{{ number_format($studentSummary['new_total'] ?? 0) }}</div>
                    <div class="report-summary-gender">F: {{ number_format($studentSummary['new_female'] ?? 0) }} <span>|</span> M: {{ number_format($studentSummary['new_male'] ?? 0) }}</div>
                </div>
            </div>
        @endif
        @if(!$isReportStub)
            <div class="report-preview-actions d-flex gap-2 ms-auto">
                @if($type === 'student-id-books-moeys')
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="ti ti-printer me-1"></i>Print</button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item report-print-link" target="_blank" href="{{ route('reports.show',$type) . '?' . http_build_query($filters) }}"><i class="ti ti-id-badge-2 me-2"></i>Print ID Book</a>
                            <a class="dropdown-item report-print-link" target="_blank" data-report-print-mode="cover" href="{{ route('reports.show',$type) . '?' . http_build_query($filters + ['print_mode' => 'cover']) }}"><i class="ti ti-book-2 me-2"></i>Print Cover</a>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="ti ti-file-spreadsheet me-1"></i>Excel</button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item report-excel-link" href="{{ route('reports.excel',$type) . '?' . http_build_query($filters) }}"><i class="ti ti-id-badge-2 me-2"></i>Excel ID Book</a>
                            <a class="dropdown-item report-excel-link" data-report-print-mode="cover" href="{{ route('reports.excel',$type) . '?' . http_build_query($filters + ['print_mode' => 'cover']) }}"><i class="ti ti-book-2 me-2"></i>Excel Cover</a>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-danger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="ti ti-file-type-pdf me-1"></i>PDF</button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item report-pdf-link" href="{{ route('reports.pdf',$type) . '?' . http_build_query($filters) }}"><i class="ti ti-id-badge-2 me-2"></i>PDF ID Book</a>
                            <a class="dropdown-item report-pdf-link" data-report-print-mode="cover" href="{{ route('reports.pdf',$type) . '?' . http_build_query($filters + ['print_mode' => 'cover']) }}"><i class="ti ti-book-2 me-2"></i>PDF Cover</a>
                        </div>
                    </div>
                @else
                    <a class="btn btn-outline-primary report-print-link" target="_blank" href="{{ route('reports.show',$type) . '?' . http_build_query($filters) }}"><i class="ti ti-printer me-1"></i>Print</a>
                    <a class="btn btn-outline-success report-excel-link" href="{{ route('reports.excel',$type) . '?' . http_build_query($filters) }}"><i class="ti ti-file-spreadsheet me-1"></i>Excel</a>
                    <a class="btn btn-outline-danger report-pdf-link" href="{{ route('reports.pdf',$type) . '?' . http_build_query($filters) }}"><i class="ti ti-file-type-pdf me-1"></i>PDF</a>
                @endif
            </div>
        @endif
    </div>
    <div class="card-body report-preview-body">
        @if(($type === 'student-list' || $type === 'student-contact-list') && ($hasMorePreviewRows ?? false))
            <div class="alert alert-info mb-3">Showing first {{ $previewLimit }} students for fast preview. Print and Excel include all matching students.</div>
        @endif
        <div class="table-responsive">@include('reports._table')</div>
    </div>
</div></div></div>
    @vite('resources/js/reportsIndex.js')
<style>
    .report-tab-khmer,
    .khmer-font-siemreap {
        font-family: var(--khmer-font-siemreap), 'Khmer OS Siemreap', 'Khmer OS Siem Reap', sans-serif;
    }
    .student-id-book-title {
        display: inline-flex;
        align-items: center;
        gap: .55rem;
        font-family: var(--khmer-font-muol-light), 'Khmer OS Muol Light', 'Khmer OS Muol', serif;
        font-weight: 400;
        letter-spacing: 0;
    }
    .student-id-book-title-logo {
        width: 26px;
        height: 26px;
        object-fit: contain;
        flex: 0 0 auto;
    }
    .report-placeholder-alert {
        border-style: dashed;
    }
    .reports-tabs-header {
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
    }
    .reports-workspace[data-report-type="attendance-list"] .report-print-options {
        margin-top: 1rem !important;
    }
    .reports-workspace[data-report-type="score-list"] .report-print-options {
        margin-top: 1.75rem !important;
    }
    @media (min-width: 992px) {
        .reports-workspace[data-report-type="attendance-list"] form .row.g-3 > .col-md-4,
        .reports-workspace[data-report-type="attendance-list"] form .row.g-3 > .report-filter-field {
            flex: 0 0 20%;
            max-width: 20%;
        }
    }    .reports-tabs-toggle {
        display: inline-flex;
        flex: 0 0 auto;
    }
    @media (min-width: 992px) {
        .reports-workspace .report-tabs-column {
            flex: 0 0 25%;
            max-width: 25%;
            width: 25%;
            transition: flex-basis .2s ease, max-width .2s ease, width .2s ease;
        }
        .reports-workspace .report-content-column {
            flex: 0 0 75%;
            max-width: 75%;
            width: 75%;
            transition: flex-basis .2s ease, max-width .2s ease, width .2s ease;
        }
        .reports-workspace.reports-tabs-collapsed .report-tabs-column {
            position: relative;
            z-index: 80;
            overflow: visible;
            flex: 0 0 72px;
            max-width: 72px;
            width: 72px;
        }
        .reports-workspace.reports-tabs-collapsed .report-content-column {
            position: relative;
            z-index: 1;
            flex: 1 1 0;
            max-width: calc(100% - 72px);
            width: calc(100% - 72px);
        }
        .reports-workspace.reports-tabs-collapsed .reports-tabs-card {
            overflow: visible;
        }
        .reports-workspace.reports-tabs-collapsed .reports-tabs-list {
            overflow: visible;
        }
        .reports-workspace.reports-tabs-collapsed .reports-tabs-card .card-title {
            display: none;
        }
        .reports-workspace.reports-tabs-collapsed .reports-tabs-card .card-header {
            justify-content: center;
            padding-left: .5rem;
            padding-right: .5rem;
        }
        .reports-workspace.reports-tabs-collapsed .reports-tabs-list .list-group-item {
            position: relative;
            justify-content: center;
            padding-left: .5rem;
            padding-right: .5rem;
            font-size: 0;
        }
        .reports-workspace.reports-tabs-collapsed .reports-tabs-list .list-group-item i {
            margin: 0 !important;
            font-size: 1.15rem;
        }

        .reports-workspace.reports-tabs-collapsed .reports-tabs-list .list-group-item::after {
            content: attr(data-report-tab-label);
            position: absolute;
            left: calc(100% + .65rem);
            top: 50%;
            z-index: 1080;
            transform: translateY(-50%);
            display: none;
            width: max-content;
            max-width: 260px;
            padding: .45rem .65rem;
            border-radius: .45rem;
            background: #111827;
            color: #fff;
            font-size: .82rem;
            font-weight: 400;
            white-space: nowrap;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .25);
        }
        .reports-workspace.reports-tabs-collapsed .reports-tabs-list .list-group-item::before {
            content: "";
            position: absolute;
            left: calc(100% + .25rem);
            top: 50%;
            z-index: 1081;
            transform: translateY(-50%);
            display: none;
            border-width: .35rem .4rem .35rem 0;
            border-style: solid;
            border-color: transparent #111827 transparent transparent;
        }
        .reports-workspace.reports-tabs-collapsed .reports-tabs-list .list-group-item:hover::after,
        .reports-workspace.reports-tabs-collapsed .reports-tabs-list .list-group-item:focus::after,
        .reports-workspace.reports-tabs-collapsed .reports-tabs-list .list-group-item:hover::before,
        .reports-workspace.reports-tabs-collapsed .reports-tabs-list .list-group-item:focus::before {
            display: block;
        }
    }
    @media (max-width: 991.98px) {
        .reports-tabs-card {
            position: relative;
            z-index: 30;
        }
        .reports-tabs-card .card-header {
            min-height: 48px;
        }
        .reports-tabs-list {
            display: none;
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 1050;
            max-height: min(70vh, 420px);
            overflow-y: auto;
            background: var(--tblr-bg-surface, #ffffff);
            border: 1px solid var(--tblr-border-color, #d9e1e8);
            border-radius: 0 0 12px 12px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .22);
        }
        .reports-tabs-card.is-open .reports-tabs-list {
            display: block;
        }
        .reports-tabs-list .list-group-item {
            padding: .72rem 1rem;
        }
        [data-bs-theme="dark"] .reports-tabs-list,
        body.dark-mode .reports-tabs-list {
            background: #111827;
            border-color: #263244;
            box-shadow: 0 14px 34px rgba(0, 0, 0, .45);
        }
    }
    .report-title-header-dark {
        background: #1f3b64;
        color: #ffffff;
        border-color: #1f3b64;
    }
    .report-title-header-dark .card-title {
        color: #ffffff;
    }
    .report-student-filter-row {
        display: grid !important;
        grid-template-columns: repeat(5, minmax(120px, 1fr)) minmax(120px, auto);
        column-gap: .75rem;
        row-gap: .35rem;
        align-items: end;
        overflow: visible;
    }
    .report-filter-field,
    .report-print-options > [class*="col-"] {
        position: relative;
        min-width: 0;
    }
    .report-filter-combobox {
        position: relative;
        width: 100%;
    }
    .report-filter-field > .form-label,
    .report-print-options > [class*="col-"] > .form-label {
        position: absolute !important;
        z-index: 5;
        top: .42rem;
        left: 1rem;
        margin: 0 !important;
        padding: 0 .45rem;
        color: #5b4bd1;
        background: var(--tblr-bg-surface,#fff);
        font-size: .72rem;
        font-weight: 700;
        line-height: 1.1 !important;
        pointer-events: none;
    }
    .report-filter-toggle,
    .report-class-picker-toggle,
    .report-select-with-arrow,
    .report-print-options .form-select {
        width: 100%;
        height: 52px;
        min-height: 52px;
        padding: 1.25rem 2.5rem .35rem 1rem;
        border: 1.5px solid #6c5ce7 !important;
        border-radius: 14px;
        background-color: var(--tblr-bg-surface,#fff);
        color: var(--tblr-body-color);
        box-shadow: 0 2px 7px rgba(31,41,55,.04);
        font-size: 1rem;
    }
    .report-filter-toggle,
    .report-class-picker-toggle {
        padding-right: 1rem;
    }
    .report-filter-toggle,
    .report-class-picker-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        text-align: left;
    }
    .report-filter-toggle span,
    .report-class-picker-toggle span {
        min-width: 0;
        flex: 1 1 auto;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .report-filter-toggle i,
    .report-class-picker-toggle i {
        flex: 0 0 auto;
        margin-left: .5rem;
        font-size: 1.1rem;
        line-height: 1;
    }
    .id-book-list-code-group {
        height: 52px;
        min-height: 52px;
        flex-wrap: nowrap;
        border-radius: 14px;
        box-shadow: 0 2px 7px rgba(31,41,55,.04);
    }
    .id-book-list-code-input {
        position: relative;
        flex: 1 1 auto;
        min-width: 0;
    }
    .id-book-list-code-input > .form-label {
        position: absolute !important;
        z-index: 6;
        top: .42rem;
        left: 1rem;
        margin: 0 !important;
        padding: 0 .35rem;
        color: #5b4bd1;
        background: transparent;
        font-size: .72rem;
        font-weight: 700;
        line-height: 1.1 !important;
        pointer-events: none;
    }
    .id-book-list-code-input > .form-control {
        width: 100%;
        height: 52px;
        min-height: 52px;
        padding: 1.25rem 1rem .35rem 1rem;
        border: 1.5px solid #d9e2ef !important;
        border-right: 0 !important;
        border-radius: 14px 0 0 14px !important;
        background-color: var(--tblr-bg-surface,#fff);
        color: var(--tblr-body-color);
        box-shadow: none !important;
        font-size: 1rem;
    }
    .id-book-list-code-group > .btn {
        height: 52px;
        min-height: 52px;
        border-radius: 0 10px 10px 0 !important;
        padding-inline: 1.35rem;
        white-space: nowrap;
        box-shadow: none;
    }
    .id-book-list-code-group:focus-within .id-book-list-code-input > .form-control {
        border-color: #6c5ce7 !important;
    }
    .report-select-with-arrow {
        appearance: none;
        -webkit-appearance: none;
        background-image: none !important;
        cursor: pointer;
    }
    .report-native-select {
        position: relative;
        width: 100%;
    }
    .report-native-select-arrow {
        position: absolute;
        right: 1rem;
        top: 50%;
        z-index: 6;
        transform: translateY(-50%);
        color: var(--tblr-body-color,#1e293b);
        font-size: 1.1rem;
        line-height: 1;
        pointer-events: none;
    }
    .report-filter-combobox.is-open .report-filter-toggle,
    .report-class-picker.is-open .report-class-picker-toggle {
        border-color: #6c5ce7 !important;
    }
    .report-filter-actions .btn {
        width: 100%;
        height: 52px;
        min-height: 52px;
        padding: .5rem 1rem;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
    }
    .report-filter-actions .btn-outline-secondary {
        background: #2fb344 !important;
        border-color: #2fb344 !important;
        color: #fff !important;
    }
    .report-filter-actions .btn-outline-secondary:hover {
        background: #269a3a !important;
        border-color: #269a3a !important;
        color: #fff !important;
    }
    .report-filter-menu,
    .report-class-picker-menu {
        position: absolute;
        z-index: 1060;
        top: calc(100% + 4px);
        left: 0;
        width: 100%;
        min-width: 220px;
        padding: .5rem;
        background: var(--tblr-bg-surface,#fff);
        border: 1px solid var(--tblr-border-color,#d9e1e8);
        border-radius: 6px;
        box-shadow: 0 8px 24px #0003;
        display: none;
    }
    .report-filter-combobox.is-open .report-filter-menu,
    .report-class-picker.is-open .report-class-picker-menu {
        display: block;
    }
    .report-filter-options,
    .report-class-picker-options {
        max-height: 220px;
        overflow-y: auto;
        margin-top: .4rem;
    }
    .report-filter-options button {
        display: block;
        width: 100%;
        border: 0;
        background: transparent;
        color: inherit;
        text-align: left;
        padding: .5rem;
        border-radius: 4px;
    }
    .report-filter-options button:hover,
    .report-filter-options button.is-selected,
    .report-class-picker-option:hover {
        background: var(--tblr-primary-lt,#e9f2ff);
    }
    .report-class-picker { position: relative; }
    .report-class-picker-option {
        display: flex;
        align-items: center;
        gap: .5rem;
        width: 100%;
        padding: .45rem .5rem;
        border-radius: 4px;
        cursor: pointer;
    }
    .report-class-picker-option input { margin: 0; }
    .report-print-options {
        grid-column: 1 / -1;
        flex: 0 0 100% !important;
        width: 100% !important;
        max-width: 100% !important;
        --tblr-gutter-y: 0 !important;
        --bs-gutter-y: 0 !important;
        margin-top: 0 !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        row-gap: .5rem !important;
    }
    .report-print-options > * {
        margin-top: 0 !important;
    }
    .report-print-options > [class*="col-"] {
        flex: 0 0 25% !important;
        max-width: 25% !important;
    }
    .report-print-options .form-hint { display: none; }
    [data-bs-theme="dark"] .report-select-with-arrow,
    body.dark-mode .report-select-with-arrow,
    [data-bs-theme="dark"] .report-print-options .form-select,
    body.dark-mode .report-print-options .form-select {
        border-color: #6c5ce7 !important;
    }
    .reports-student-mobile-list {
        display: none;
    }
    .report-preview-body {
        padding-top: .05rem !important;
    }
    .report-preview-body .table-responsive {
        margin-top: 0 !important;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .report-preview-body .table {
        margin-top: 0 !important;
        margin-bottom: 0;
    }
    .report-preview-body .table thead th {
        padding-top: .25rem;
        padding-bottom: .35rem;
    }
    .report-preview-header {
        flex-wrap: nowrap;
        display: grid !important;
        grid-template-columns: minmax(160px, 1fr) auto minmax(160px, 1fr);
        align-items: center;
    }
    .report-preview-title-wrap {
        min-width: 0;
    }
    .report-preview-note {
        margin-top: .2rem;
        color: var(--tblr-secondary-color);
        font-size: .82rem;
        line-height: 1.2;
    }
    .report-preview-header .card-title {
        justify-self: start;
    }
    .report-summary-card {
        justify-self: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .6rem;
        min-width: 320px;
        padding: .32rem .75rem;
        border: 1px solid #dbe6f5;
        border-radius: 12px;
        background: linear-gradient(135deg, #ffffff, #f8fbff);
        box-shadow: 0 3px 10px rgba(31,41,55,.05);
    }
    .report-summary-item {
        min-width: 0;
        text-align: center;
        white-space: nowrap;
    }
    .report-summary-label {
        color: #52627a;
        font-size: .62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .report-summary-number {
        font-size: 1rem;
        font-weight: 800;
        line-height: 1;
    }
    .report-summary-total .report-summary-number {
        color: #206bc4;
    }
    .report-summary-new .report-summary-number {
        color: #2fb344;
    }
    .report-summary-gender {
        color: #6b7280;
        font-size: .68rem;
        font-weight: 700;
    }
    .report-summary-gender span {
        color: #c2ccda;
        padding: 0 .25rem;
    }
    .report-summary-divider {
        width: 1px;
        align-self: stretch;
        background: #e5edf7;
    }
    [data-bs-theme="dark"] .report-summary-card,
    body.dark-mode .report-summary-card {
        border-color: rgba(148, 163, 184, .35);
        background: linear-gradient(135deg, #172033, #111827);
        box-shadow: 0 3px 12px rgba(0, 0, 0, .28);
    }
    [data-bs-theme="dark"] .report-summary-label,
    body.dark-mode .report-summary-label {
        color: #cbd5e1;
    }
    [data-bs-theme="dark"] .report-summary-gender,
    body.dark-mode .report-summary-gender {
        color: #d7dee9;
    }
    [data-bs-theme="dark"] .report-summary-gender span,
    body.dark-mode .report-summary-gender span {
        color: #64748b;
    }
    [data-bs-theme="dark"] .report-summary-divider,
    body.dark-mode .report-summary-divider {
        background: rgba(148, 163, 184, .32);
    }
    [data-bs-theme="dark"] .report-summary-total .report-summary-number,
    body.dark-mode .report-summary-total .report-summary-number {
        color: #72b7ff;
    }
    [data-bs-theme="dark"] .report-summary-new .report-summary-number,
    body.dark-mode .report-summary-new .report-summary-number {
        color: #63e58b;
    }
    .report-preview-actions {
        grid-column: 3;
        justify-self: end;
    }
    @media (max-width: 1199px) {
        .report-student-filter-row {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .report-preview-header {
            display: flex !important;
            flex-wrap: wrap;
        }
        .report-summary-card {
            order: 3;
            flex: 1 1 100%;
            min-width: 0;
        }
    }
    @media (max-width: 575px) {
        .reports-workspace {
            --tblr-gutter-y: .75rem;
        }
        .reports-tabs-card .card-header {
            padding: .75rem 1rem;
        }
        .reports-tabs-card .reports-tabs-list {
            padding: .4rem;
        }
        .reports-tabs-card .reports-tabs-list .list-group-item {
            border: 0 !important;
            border-radius: 8px !important;
            padding: .65rem .75rem;
            white-space: normal;
        }
        .report-student-filter-row {
            grid-template-columns: 1fr;
            gap: .8rem;
        }
        .report-filter-toggle,
        .report-class-picker-toggle,
        .report-select-with-arrow,
        .report-print-options .form-select,
        .report-filter-actions .btn {
            height: 56px;
            min-height: 56px;
        }
        .report-filter-menu,
        .report-class-picker-menu {
            min-width: 100%;
            max-width: calc(100vw - 2rem);
        }
        .report-print-options {
            margin-top: .25rem !important;
        }
        .report-print-options > [class*="col-"] {
            flex: 0 0 100% !important;
            max-width: 100% !important;
        }
        .card-header:has(.report-preview-actions) {
            flex-direction: column;
            align-items: stretch !important;
        }
        .report-summary-card {
            order: 0;
            width: 100%;
            gap: .5rem;
            padding: .45rem;
        }
        .report-summary-item {
            flex: 1 1 0;
        }
        .report-summary-label {
            font-size: .6rem;
        }
        .report-summary-number {
            font-size: .95rem;
        }
        .report-preview-actions {
            width: 100%;
            margin-left: 0 !important;
        }
        .report-preview-actions .btn {
            flex: 1 1 0;
            min-height: 44px;
            justify-content: center;
        }
        .reports-student-list-table {
            display: none;
        }
        .reports-student-mobile-list {
            display: grid;
            gap: .75rem;
        }
        .reports-student-mobile-card {
            position: relative;
            display: grid;
            grid-template-columns: 46px minmax(0, 1fr);
            gap: .75rem;
            min-height: 124px;
            border: 1px solid #d9e1f2;
            border-left: 4px solid #3777d8;
            border-radius: 14px;
            background: var(--tblr-bg-surface,#fff);
            box-shadow: 0 4px 14px rgba(31,41,55,.06);
            overflow: hidden;
        }
        .reports-student-mobile-number {
            display: flex;
            align-items: center;
            justify-content: center;
            align-self: start;
            width: 46px;
            height: 46px;
            background: #3777d8;
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
        }
        .reports-student-mobile-status {
            position: absolute;
            top: .75rem;
            right: .75rem;
            max-width: 42%;
            padding: .2rem .5rem;
            border-radius: 999px;
            background: #e9f7ef;
            color: #2fb344;
            font-size: .72rem;
            font-weight: 700;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .reports-student-mobile-body {
            min-width: 0;
            padding: .8rem .75rem .8rem 0;
            color: #1f3760;
            font-weight: 700;
        }
        .reports-student-mobile-id {
            padding-right: 44%;
            color: #58739b;
            font-size: .82rem;
            margin-bottom: .25rem;
        }
        .reports-student-mobile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            margin-top: .45rem;
        }
        .reports-student-mobile-meta span,
        .reports-student-mobile-class {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: #f1f5fb;
            color: #52627a;
            padding: .2rem .5rem;
            font-size: .76rem;
            font-weight: 600;
        }
        .reports-student-mobile-class {
            margin-top: .45rem;
            border-radius: 10px;
        }
        .reports-student-mobile-enrollment {
            white-space: nowrap;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .reports-student-mobile-empty {
            padding: 1rem;
            border: 1px dashed #c7d2e5;
            border-radius: 14px;
            color: #6b7280;
            text-align: center;
        }
    }
</style>
@endsection
