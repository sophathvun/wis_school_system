@extends('layouts.app')
@section('title','Reports')
@push('styles')
    @vite('resources/css/reports.css')
@endpush
@push('scripts')
    @vite('resources/js/reportsIndex.js')
@endpush
@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Management</div>
                <h2 class="page-title">Reports</h2>
            </div>
        </div>
    </div>
@endsection
@section('content')
@php
    $reportStubTypes = [];
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
        'student-profile-label' => 'ti-tags',
    ];
    $khmerReportTypeTabs = ['moeys-sikkhakarik-book'];
    $isReportStub = in_array($type, $reportStubTypes, true);
    $transcriptTotalStudents = $type === 'moeys-sikkhakarik-book' ? collect($enrollments ?? [])->count() : 0;
@endphp
<div class="row g-3 reports-workspace reports-workspace-{{ $type }}" data-report-type="{{ $type }}" data-report-date="{{ $filters['report_date'] ?? now()->format('Y-m-d') }}"><div class="col-lg-2 report-tabs-column"><div class="card reports-tabs-card"><div class="card-header reports-tabs-header"><h3 class="card-title">Report Types</h3><button type="button" class="btn btn-icon btn-outline-primary reports-tabs-toggle" aria-expanded="true" aria-controls="reportsTypeList" aria-label="Collapse report types" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-custom-class="report-tabs-tooltip" data-bs-title="Collapse report types"><i class="ti ti-layout-sidebar-left-collapse"></i></button></div><div class="list-group list-group-flush reports-tabs-list" id="reportsTypeList">@foreach($reportTypes as $key=>$label)<a href="{{ route('reports.index',['type'=>$key]) }}" class="list-group-item list-group-item-action {{ $type===$key?'active':'' }} {{ in_array($key, $khmerReportTypeTabs, true) ? 'report-tab-khmer' : '' }}" data-report-tab-label="{{ $label }}" aria-label="{{ $label }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-custom-class="report-tabs-tooltip" data-bs-title="{{ $label }}"><i class="ti {{ $reportTypeIcons[$key] ?? 'ti-file-text' }} me-2" aria-hidden="true"></i><span class="report-tab-label">{{ $label }}</span></a>@endforeach</div></div></div><div class="col-lg-10 report-content-column"><div class="card mb-3"><div class="card-header {{ in_array($type, ['attendance-list', 'student-list', 'student-contact-list', 'score-list', 'student-statistics', 'student-statistics-detail', 'withdrawn-students', 'student-id-books-moeys', 'moeys-sikkhakarik-book'], true) ? 'report-header-dark-blue' : '' }}"><h3 class="card-title {{ in_array($type, ['student-id-books-moeys', 'moeys-sikkhakarik-book'], true) ? 'student-id-book-title' : '' }}">@if($type === 'student-id-books-moeys')<img src="{{ asset('images/moeys_logo.png') }}" alt="MoEYS" class="student-id-book-title-logo"><span>សៀវភៅចុះអត្តលេខសិស្ស (MoEYS)</span>@elseif($type === 'moeys-sikkhakarik-book')<img src="{{ asset('images/moeys_logo.png') }}" alt="MoEYS" class="student-id-book-title-logo"><span>សៀវភៅសិក្ខាគារិក (MoEYS)</span>@else{{ $type === 'student-statistics-detail' ? 'STUDENT STATISTICS (Details)' : $reportTypes[$type] }}@endif</h3></div><form method="get" action="{{ route('reports.index') }}" class="report-filter-form report-filter-form-{{ $type }}"><input type="hidden" name="type" value="{{ $type }}"><div class="card-body"><div class="{{ $type === 'student-list' ? 'report-student-list-filter-row' : ($type === 'student-contact-list' ? 'report-student-contact-filter-row' : ($type === 'student-profile-label' ? 'report-student-profile-label-filter-row' : 'row g-3')) }}">
@if($type === 'student-list')
<div class="report-student-list-filter-field report-filter-field"><label class="form-label">Period</label><div class="report-native-select"><select name="period_type" class="form-select report-select-with-arrow" data-report-period-select><option value="all" @selected(($filters['period_type']??'all')==='all')>Regular + Summer</option><option value="regular" @selected(($filters['period_type']??'')==='regular')>Regular</option><option value="summer" @selected(($filters['period_type']??'')==='summer')>Summer</option></select><i class="ti ti-chevron-down report-native-select-arrow"></i></div></div>
<div class="report-student-list-filter-field report-filter-field"><label class="form-label">Academic Year</label><div class="report-filter-combobox" data-target="reportAcademicYearValue"><button type="button" class="report-filter-toggle"><span>{{ $academicYears->firstWhere('id',(int)($filters['academic_year_id']??0))?->academic_year ?: 'All Academic Years' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Academic Year"><div class="report-filter-options"><button type="button" data-value="">All Academic Years</button>@foreach($academicYears as $year)<button type="button" data-value="{{ $year->id }}">{{ $year->academic_year }}</button>@endforeach</div></div></div><input type="hidden" name="academic_year_id" id="reportAcademicYearValue" value="{{ $filters['academic_year_id']??'' }}"></div>
<div class="report-student-list-filter-field report-filter-field"><label class="form-label">Campus</label><div class="report-filter-combobox" data-target="reportCampusValue"><button type="button" class="report-filter-toggle"><span>{{ $campuses->firstWhere('id',(int)($filters['campus_id']??0))?->campus_name_en ?: '' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Campus"><div class="report-filter-options"><button type="button" data-value="">All Campuses</button>@foreach($campuses as $campus)<button type="button" data-value="{{ $campus->id }}">{{ $campus->campus_name_en }}</button>@endforeach</div></div></div><input type="hidden" name="campus_id" id="reportCampusValue" value="{{ $filters['campus_id']??'' }}"></div>
<div class="report-student-list-filter-field report-filter-field"><label class="form-label">Grade</label><div class="report-filter-combobox" data-target="reportGradeClassValue"><button type="button" class="report-filter-toggle"><span>{{ data_get(collect($gradeClassOptions)->firstWhere('value',$filters['grade_class']??''), 'label', '') }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Grade"><div class="report-filter-options"><button type="button" data-value="">All Grade</button>@foreach($gradeClassOptions as $option)<button type="button" data-value="{{ $option['value'] }}">{{ $option['label'] }}</button>@endforeach</div></div></div><input type="hidden" name="grade_class" id="reportGradeClassValue" value="{{ $filters['grade_class']??'' }}"></div>
<div class="report-student-list-filter-field report-filter-field"><label class="form-label">Group</label><div class="report-filter-combobox" data-target="reportGroupValue"><button type="button" class="report-filter-toggle"><span>{{ $groupOptions->firstWhere('session_id',(int)($filters['session_id']??0))?->session_short_name ?: '' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Group"><div class="report-filter-options"><button type="button" data-value="">All Groups</button>@foreach($groupOptions as $group)<button type="button" data-value="{{ $group->session_id }}">{{ $group->session_short_name }}</button>@endforeach</div></div></div><input type="hidden" name="session_id" id="reportGroupValue" value="{{ $filters['session_id']??'' }}"></div><div class="report-student-list-filter-actions report-filter-actions"><a class="btn btn-primary" href="{{ route('reports.index',['type'=>'student-list']) }}"><i class="ti ti-rotate-2 me-1"></i>Refresh</a></div>
@elseif($type === 'student-contact-list')
<div class="report-student-contact-filter-field report-filter-field"><label class="form-label">Period</label><div class="report-native-select"><select name="period_type" class="form-select report-select-with-arrow" data-report-period-select><option value="all" @selected(($filters['period_type']??'all')==='all')>Regular + Summer</option><option value="regular" @selected(($filters['period_type']??'')==='regular')>Regular</option><option value="summer" @selected(($filters['period_type']??'')==='summer')>Summer</option></select><i class="ti ti-chevron-down report-native-select-arrow"></i></div></div>
<div class="report-student-contact-filter-field report-filter-field"><label class="form-label">Academic Year</label><div class="report-filter-combobox" data-target="reportAcademicYearValue"><button type="button" class="report-filter-toggle"><span>{{ $academicYears->firstWhere('id',(int)($filters['academic_year_id']??0))?->academic_year ?: 'All Academic Years' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Academic Year"><div class="report-filter-options"><button type="button" data-value="">All Academic Years</button>@foreach($academicYears as $year)<button type="button" data-value="{{ $year->id }}">{{ $year->academic_year }}</button>@endforeach</div></div></div><input type="hidden" name="academic_year_id" id="reportAcademicYearValue" value="{{ $filters['academic_year_id']??'' }}"></div>
<div class="report-student-contact-filter-field report-filter-field"><label class="form-label">Campus</label><div class="report-filter-combobox" data-target="reportCampusValue"><button type="button" class="report-filter-toggle"><span>{{ $campuses->firstWhere('id',(int)($filters['campus_id']??0))?->campus_name_en ?: '' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Campus"><div class="report-filter-options"><button type="button" data-value="">All Campuses</button>@foreach($campuses as $campus)<button type="button" data-value="{{ $campus->id }}">{{ $campus->campus_name_en }}</button>@endforeach</div></div></div><input type="hidden" name="campus_id" id="reportCampusValue" value="{{ $filters['campus_id']??'' }}"></div>
<div class="report-student-contact-filter-field report-filter-field"><label class="form-label">Grade</label><div class="report-filter-combobox" data-target="reportGradeClassValue"><button type="button" class="report-filter-toggle"><span>{{ data_get(collect($gradeClassOptions)->firstWhere('value',$filters['grade_class']??''), 'label', '') }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Grade"><div class="report-filter-options"><button type="button" data-value="">All Grade</button>@foreach($gradeClassOptions as $option)<button type="button" data-value="{{ $option['value'] }}">{{ $option['label'] }}</button>@endforeach</div></div></div><input type="hidden" name="grade_class" id="reportGradeClassValue" value="{{ $filters['grade_class']??'' }}"></div>
<div class="report-student-contact-filter-field report-filter-field"><label class="form-label">Group</label><div class="report-filter-combobox" data-target="reportGroupValue"><button type="button" class="report-filter-toggle"><span>{{ $groupOptions->firstWhere('session_id',(int)($filters['session_id']??0))?->session_short_name ?: '' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Group"><div class="report-filter-options"><button type="button" data-value="">All Groups</button>@foreach($groupOptions as $group)<button type="button" data-value="{{ $group->session_id }}">{{ $group->session_short_name }}</button>@endforeach</div></div></div><input type="hidden" name="session_id" id="reportGroupValue" value="{{ $filters['session_id']??'' }}"></div><div class="report-student-contact-filter-actions report-filter-actions"><a class="btn btn-outline-secondary" href="{{ route('reports.index',['type'=>'student-contact-list']) }}"><i class="ti ti-rotate-2 me-1"></i>Refresh</a></div>
@elseif($type === 'student-profile-label')
<div class="report-student-profile-label-filter-field report-filter-field"><label class="form-label">Period</label><div class="report-native-select"><select name="period_type" class="form-select report-select-with-arrow" data-report-period-select><option value="all" @selected(($filters['period_type']??'all')==='all')>Regular + Summer</option><option value="regular" @selected(($filters['period_type']??'')==='regular')>Regular</option><option value="summer" @selected(($filters['period_type']??'')==='summer')>Summer</option></select><i class="ti ti-chevron-down report-native-select-arrow"></i></div></div>
<div class="report-student-profile-label-filter-field report-filter-field"><label class="form-label">Academic Year</label><div class="report-filter-combobox" data-target="reportAcademicYearValue"><button type="button" class="report-filter-toggle"><span>{{ $academicYears->firstWhere('id',(int)($filters['academic_year_id']??0))?->academic_year ?: 'All Academic Years' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Academic Year"><div class="report-filter-options"><button type="button" data-value="">All Academic Years</button>@foreach($academicYears as $year)<button type="button" data-value="{{ $year->id }}">{{ $year->academic_year }}</button>@endforeach</div></div></div><input type="hidden" name="academic_year_id" id="reportAcademicYearValue" value="{{ $filters['academic_year_id']??'' }}"></div>
<div class="report-student-profile-label-filter-field report-filter-field"><label class="form-label">Campus</label><div class="report-filter-combobox" data-target="reportCampusValue"><button type="button" class="report-filter-toggle"><span>{{ $campuses->firstWhere('id',(int)($filters['campus_id']??0))?->campus_name_en ?: '' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Campus"><div class="report-filter-options"><button type="button" data-value="">All Campuses</button>@foreach($campuses as $campus)<button type="button" data-value="{{ $campus->id }}">{{ $campus->campus_name_en }}</button>@endforeach</div></div></div><input type="hidden" name="campus_id" id="reportCampusValue" value="{{ $filters['campus_id']??'' }}"></div>
<div class="report-student-profile-label-filter-field report-filter-field"><label class="form-label">Grade</label><div class="report-filter-combobox" data-target="reportGradeClassValue"><button type="button" class="report-filter-toggle"><span>{{ data_get(collect($gradeClassOptions)->firstWhere('value',$filters['grade_class']??''), 'label', '') }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Grade"><div class="report-filter-options"><button type="button" data-value="">All Grade</button>@foreach($gradeClassOptions as $option)<button type="button" data-value="{{ $option['value'] }}">{{ $option['label'] }}</button>@endforeach</div></div></div><input type="hidden" name="grade_class" id="reportGradeClassValue" value="{{ $filters['grade_class']??'' }}"></div>
<div class="report-student-profile-label-filter-field report-filter-field"><label class="form-label">Group</label><div class="report-filter-combobox" data-target="reportGroupValue"><button type="button" class="report-filter-toggle"><span>{{ $groupOptions->firstWhere('session_id',(int)($filters['session_id']??0))?->session_short_name ?: '' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Group"><div class="report-filter-options"><button type="button" data-value="">All Groups</button>@foreach($groupOptions as $group)<button type="button" data-value="{{ $group->session_id }}">{{ $group->session_short_name }}</button>@endforeach</div></div></div><input type="hidden" name="session_id" id="reportGroupValue" value="{{ $filters['session_id']??'' }}"></div><div class="report-student-profile-label-filter-actions report-filter-actions"><a class="btn btn-outline-secondary" href="{{ route('reports.index',['type'=>'student-profile-label']) }}"><i class="ti ti-rotate-2 me-1"></i>Refresh</a></div>
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
<div class="col-md-4 report-filter-field"><label class="form-label">Month</label><input name="month" type="month" class="form-control" value="{{ $filters['month'] }}"></div>
@elseif($type === 'student-id-books-moeys')
<div class="col-md-3 report-filter-field"><label class="form-label">Period</label><div class="report-native-select"><select name="period_type" class="form-select report-select-with-arrow" data-report-period-select><option value="all" @selected(($filters['period_type']??'all')==='all')>Regular + Summer</option><option value="regular" @selected(($filters['period_type']??'')==='regular')>Regular</option><option value="summer" @selected(($filters['period_type']??'')==='summer')>Summer</option></select><i class="ti ti-chevron-down report-native-select-arrow"></i></div></div>
<div class="col-md-3 report-filter-field"><label class="form-label">Academic Year</label><div class="report-filter-combobox" data-target="reportAcademicYearValue"><button type="button" class="report-filter-toggle"><span>{{ $academicYears->firstWhere('id',(int)($filters['academic_year_id']??0))?->academic_year ?: 'All Academic Years' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Academic Year"><div class="report-filter-options"><button type="button" data-value="">All Academic Years</button>@foreach($academicYears as $year)<button type="button" data-value="{{ $year->id }}">{{ $year->academic_year }}</button>@endforeach</div></div></div><input type="hidden" name="academic_year_id" id="reportAcademicYearValue" value="{{ $filters['academic_year_id']??'' }}"></div>
<div class="col-md-3 report-filter-field"><label class="form-label">Book Level</label><div class="report-filter-combobox" data-target="reportIdBookLevelValue"><button type="button" class="report-filter-toggle"><span>{{ ['kindergarten'=>'Kindergarten', 'primary'=>'Primary', 'secondary'=>'Secondary'][$filters['id_book_level']??''] ?? 'Select Book Level' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Book Level"><div class="report-filter-options"><button type="button" data-value="">Select Book Level</button><button type="button" data-value="kindergarten">Kindergarten</button><button type="button" data-value="primary">Primary</button><button type="button" data-value="secondary">Secondary</button></div></div></div><input type="hidden" name="id_book_level" id="reportIdBookLevelValue" value="{{ $filters['id_book_level']??'' }}"></div>
<div class="col-md-3 report-filter-field"><label class="form-label">Campus</label><div class="report-filter-combobox" data-target="reportCampusValue"><button type="button" class="report-filter-toggle"><span>{{ $campuses->firstWhere('id',(int)($filters['campus_id']??0))?->campus_name_en ?: 'All Campuses' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Campus"><div class="report-filter-options"><button type="button" data-value="">All Campuses</button>@foreach($campuses as $campus)<button type="button" data-value="{{ $campus->id }}">{{ $campus->campus_name_en }}</button>@endforeach</div></div></div><input type="hidden" name="campus_id" id="reportCampusValue" value="{{ $filters['campus_id']??'' }}"></div>
<div class="col-md-4 report-filter-field id-book-list-code-field">
    <label class="form-label" for="idBookStartNumber">Start List Code</label>
    <div class="id-book-list-code-group">
        <input id="idBookStartNumber" type="number" min="1" max="99999" step="1" class="form-control" name="id_book_start_number" placeholder="00001" aria-describedby="idBookStartNumberHelp" data-id-book-start-number>
        <button type="button" class="btn btn-primary" data-id-book-generate data-generate-url="{{ route('reports.id-book-list-codes.generate', ['type' => $type]) }}"><i class="ti ti-number me-1"></i>Generate</button>
    </div>
    <div class="form-hint" id="idBookStartNumberHelp">Generates 5-digit codes for the selected Academic Year, Book Level, and Campus.</div>
</div>
@elseif($type === 'moeys-sikkhakarik-book')
@php($transcriptReportDate = \Carbon\Carbon::parse($filters['report_date'] ?? now()->format('Y-m-d')))
<div class="col-md report-filter-field"><label class="form-label">Academic Year</label><div class="report-filter-combobox" data-target="reportAcademicYearValue"><button type="button" class="report-filter-toggle"><span>{{ $academicYears->firstWhere('id',(int)($filters['academic_year_id']??0))?->academic_year ?: 'Select Academic Year' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Academic Year"><div class="report-filter-options"><button type="button" data-value="">Select Academic Year</button>@foreach($academicYears as $year)<button type="button" data-value="{{ $year->id }}">{{ $year->academic_year }}</button>@endforeach</div></div></div><input type="hidden" name="academic_year_id" id="reportAcademicYearValue" value="{{ $filters['academic_year_id']??'' }}"></div>
<div class="col-md report-filter-field"><label class="form-label">Transcript Level</label><div class="report-filter-combobox" data-target="reportTranscriptLevelValue"><button type="button" class="report-filter-toggle"><span>{{ ['primary'=>'Primary', 'secondary'=>'Secondary'][$filters['transcript_level']??''] ?? 'Select Level' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Level"><div class="report-filter-options"><button type="button" data-value="">Select Level</button><button type="button" data-value="primary">Primary</button><button type="button" data-value="secondary">Secondary</button></div></div></div><input type="hidden" name="transcript_level" id="reportTranscriptLevelValue" value="{{ $filters['transcript_level']??'' }}"></div>
<div class="col-md report-filter-field"><label class="form-label">Campus</label><div class="report-filter-combobox" data-target="reportCampusValue"><button type="button" class="report-filter-toggle"><span>{{ $campuses->firstWhere('id',(int)($filters['campus_id']??0))?->campus_name_en ?: 'All Campuses' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Campus"><div class="report-filter-options"><button type="button" data-value="">All Campuses</button>@foreach($campuses as $campus)<button type="button" data-value="{{ $campus->id }}">{{ $campus->campus_name_en }}</button>@endforeach</div></div></div><input type="hidden" name="campus_id" id="reportCampusValue" value="{{ $filters['campus_id']??'' }}"></div>
<div class="col-md report-filter-field"><label class="form-label">Grade</label><div class="report-filter-combobox" data-target="reportGradeClassValue"><button type="button" class="report-filter-toggle"><span>{{ data_get(collect($gradeClassOptions)->firstWhere('value',$filters['grade_class']??''), 'label', 'All Grades') }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Grade"><div class="report-filter-options"><button type="button" data-value="">All Grades</button>@foreach($gradeClassOptions as $option)<button type="button" data-value="{{ $option['value'] }}">{{ $option['label'] }}</button>@endforeach</div></div></div><input type="hidden" name="grade_class" id="reportGradeClassValue" value="{{ $filters['grade_class']??'' }}"></div>
<div class="col-md report-filter-field"><label class="form-label">Group</label><div class="report-filter-combobox" data-target="reportGroupValue"><button type="button" class="report-filter-toggle"><span>{{ $groupOptions->firstWhere('session_id',(int)($filters['session_id']??0))?->session_short_name ?: 'All Groups' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Group"><div class="report-filter-options"><button type="button" data-value="">All Groups</button>@foreach($groupOptions as $group)<button type="button" data-value="{{ $group->session_id }}">{{ $group->session_short_name }}</button>@endforeach</div></div></div><input type="hidden" name="session_id" id="reportGroupValue" value="{{ $filters['session_id']??'' }}"></div>
<div class="col-md report-filter-field report-print-date-field"><label class="form-label report-date-label">Print Date <span class="report-date-format-note">DD-MMM-YYYY</span></label><div class="date-picker report-print-date-picker" id="report_print_date_picker" data-report-date-picker><button type="button" id="report_print_date_trigger" class="date-picker-trigger date-picker-calendar-button" data-report-date-toggle aria-label="Open calendar"><i class="ti ti-calendar"></i><span id="report_print_date_display" class="date-picker-display">{{ $transcriptReportDate->format('d-M-Y') }}</span><i class="ti ti-chevron-down"></i></button><input type="hidden" id="report_print_date" name="report_date" data-report-date-value value="{{ $transcriptReportDate->format('Y-m-d') }}"><input type="text" id="report_print_date_direct" class="form-control" data-report-date-display value="{{ $transcriptReportDate->format('d-M-Y') }}" placeholder="DD-MMM-YYYY" title="Type date as DD-MMM-YYYY, for example 13-Sep-2026" aria-label="Enter print date directly in DD-MMM-YYYY format" autocomplete="off"><span id="report_print_date_format_help" class="date-format-help">DD-MMM-YYYY</span><div id="report_print_date_popup" class="date-picker-popup d-none" data-report-date-calendar><div class="date-picker-header"><button type="button" id="report_print_date_prev" class="date-picker-nav" aria-label="Previous month"><i class="ti ti-chevron-left"></i></button><button type="button" id="report_print_date_year_toggle" class="date-picker-year-toggle"><span id="report_print_date_month_label"></span></button><button type="button" id="report_print_date_next" class="date-picker-nav" aria-label="Next month"><i class="ti ti-chevron-right"></i></button></div><div id="report_print_date_year_popup" class="date-picker-year-popup d-none"><div id="report_print_date_years" class="date-picker-years"></div></div><div class="date-picker-grid"><div class="date-picker-weekdays"><span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span></div><div id="report_print_date_days" class="date-picker-days"></div></div></div></div></div>
@elseif($type === 'moeys-id-number-book')
<div class="col-md-3 report-filter-field"><label class="form-label">Period</label><div class="report-native-select"><select name="period_type" class="form-select report-select-with-arrow" data-report-period-select><option value="all" @selected(($filters['period_type']??'all')==='all')>Regular + Summer</option><option value="regular" @selected(($filters['period_type']??'')==='regular')>Regular</option><option value="summer" @selected(($filters['period_type']??'')==='summer')>Summer</option></select><i class="ti ti-chevron-down report-native-select-arrow"></i></div></div>
<div class="col-md-3 report-filter-field"><label class="form-label">Academic Year</label><div class="report-filter-combobox" data-target="reportAcademicYearValue"><button type="button" class="report-filter-toggle"><span>{{ $academicYears->firstWhere('id',(int)($filters['academic_year_id']??0))?->academic_year ?: 'Select Academic Year' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Academic Year"><div class="report-filter-options"><button type="button" data-value="">Select Academic Year</button>@foreach($academicYears as $year)<button type="button" data-value="{{ $year->id }}">{{ $year->academic_year }}</button>@endforeach</div></div></div><input type="hidden" name="academic_year_id" id="reportAcademicYearValue" value="{{ $filters['academic_year_id']??'' }}"></div>
<div class="col-md-3 report-filter-field"><label class="form-label">Campus</label><div class="report-filter-combobox" data-target="reportCampusValue"><button type="button" class="report-filter-toggle"><span>{{ $campuses->firstWhere('id',(int)($filters['campus_id']??0))?->campus_name_en ?: 'All Campuses' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Campus"><div class="report-filter-options"><button type="button" data-value="">All Campuses</button>@foreach($campuses as $campus)<button type="button" data-value="{{ $campus->id }}">{{ $campus->campus_name_en }}</button>@endforeach</div></div></div><input type="hidden" name="campus_id" id="reportCampusValue" value="{{ $filters['campus_id']??'' }}"></div>
<div class="col-md-3 report-filter-field"><label class="form-label">Grade</label><div class="report-filter-combobox" data-target="reportGradeClassValue"><button type="button" class="report-filter-toggle"><span>{{ data_get(collect($gradeClassOptions)->firstWhere('value',$filters['grade_class']??''), 'label', 'All Grades') }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Grade"><div class="report-filter-options"><button type="button" data-value="">All Grades</button>@foreach($gradeClassOptions as $option)<button type="button" data-value="{{ $option['value'] }}">{{ $option['label'] }}</button>@endforeach</div></div></div><input type="hidden" name="grade_class" id="reportGradeClassValue" value="{{ $filters['grade_class']??'' }}"></div>
<div class="col-12"><input type="hidden" name="selected_columns_submitted" value="1"><input type="hidden" name="preview_page_size" value="{{ $filters['preview_page_size'] ?? '25' }}"><input type="hidden" name="preview_page" value="1"><div class="get-student-list-fields"><div class="d-flex align-items-center justify-content-between gap-2 mb-2"><div><div class="fw-bold">Select information to display</div><div class="form-hint">Search and select multiple Student Information, Enrollment, and Family columns.</div></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('reports.index', ['type' => $type, 'selected_columns_submitted' => 1]) }}" data-get-student-list-clear><i class="ti ti-eraser me-1"></i>Clear</a><button type="submit" class="btn btn-primary"><i class="ti ti-refresh me-1"></i>Apply</button></div></div><div class="selected-column-order mb-3" data-selected-column-order><div class="d-flex align-items-center justify-content-between gap-2 mb-2"><div class="fw-bold">Selected Column Order</div><div class="form-hint">Drag columns to the 1st, 2nd, 3rd position.</div></div><div class="selected-column-order-list" data-selected-column-order-list></div></div>
@include('reports._column-selectors')
</div></div>
@elseif($type === 'withdrawn-students')
<div class="col-md-4 report-filter-field"><label class="form-label">Period</label><div class="report-native-select"><select name="period_type" class="form-select report-select-with-arrow" data-report-period-select><option value="all" @selected(($filters['period_type']??'all')==='all')>Regular + Summer</option><option value="regular" @selected(($filters['period_type']??'')==='regular')>Regular</option><option value="summer" @selected(($filters['period_type']??'')==='summer')>Summer</option></select><i class="ti ti-chevron-down report-native-select-arrow"></i></div></div>
<div class="col-md-4 report-filter-field"><label class="form-label">Academic Year</label><div class="report-filter-combobox" data-target="reportAcademicYearValue"><button type="button" class="report-filter-toggle"><span>{{ $academicYears->firstWhere('id',(int)($filters['academic_year_id']??0))?->academic_year ?: 'All Academic Years' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Academic Year"><div class="report-filter-options"><button type="button" data-value="">All Academic Years</button>@foreach($academicYears as $year)<button type="button" data-value="{{ $year->id }}">{{ $year->academic_year }}</button>@endforeach</div></div></div><input type="hidden" name="academic_year_id" id="reportAcademicYearValue" value="{{ $filters['academic_year_id']??'' }}"></div>
<div class="col-md-4 report-filter-field"><label class="form-label">Campus</label><div class="report-filter-combobox" data-target="reportCampusValue"><button type="button" class="report-filter-toggle"><span>{{ $campuses->firstWhere('id',(int)($filters['campus_id']??0))?->campus_name_en ?: 'All Campuses' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Campus"><div class="report-filter-options"><button type="button" data-value="">All Campuses</button>@foreach($campuses as $campus)<button type="button" data-value="{{ $campus->id }}">{{ $campus->campus_name_en }}</button>@endforeach</div></div></div><input type="hidden" name="campus_id" id="reportCampusValue" value="{{ $filters['campus_id']??'' }}"></div>
<div class="col-md-4 report-filter-field"><label class="form-label">Grade</label><div class="report-filter-combobox" data-target="reportGradeClassValue"><button type="button" class="report-filter-toggle"><span>{{ data_get(collect($gradeClassOptions)->firstWhere('value',$filters['grade_class']??''), 'label', 'All Grades') }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Grade"><div class="report-filter-options"><button type="button" data-value="">All Grades</button>@foreach($gradeClassOptions as $option)<button type="button" data-value="{{ $option['value'] }}">{{ $option['label'] }}</button>@endforeach</div></div></div><input type="hidden" name="grade_class" id="reportGradeClassValue" value="{{ $filters['grade_class']??'' }}"></div>
<div class="col-md-4 report-filter-field"><label class="form-label">Group</label><div class="report-filter-combobox" data-target="reportGroupValue"><button type="button" class="report-filter-toggle"><span>{{ $groupOptions->firstWhere('session_id',(int)($filters['session_id']??0))?->session_short_name ?: 'All Groups' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Group"><div class="report-filter-options"><button type="button" data-value="">All Groups</button>@foreach($groupOptions as $group)<button type="button" data-value="{{ $group->session_id }}">{{ $group->session_short_name }}</button>@endforeach</div></div></div><input type="hidden" name="session_id" id="reportGroupValue" value="{{ $filters['session_id']??'' }}"></div>
<div class="col-md-4 report-filter-field"><label class="form-label">Withdrawal Status</label><div class="report-native-select"><select name="withdrawal_status" class="form-select report-select-with-arrow"><option value="approved" @selected(($filters['withdrawal_status']??'approved')==='approved')>Approved</option><option value="pending" @selected(($filters['withdrawal_status']??'')==='pending')>Pending</option><option value="principal_approved" @selected(($filters['withdrawal_status']??'')==='principal_approved')>Principal Approved</option><option value="rejected" @selected(($filters['withdrawal_status']??'')==='rejected')>Rejected</option><option value="cancelled" @selected(($filters['withdrawal_status']??'')==='cancelled')>Cancelled</option><option value="all" @selected(($filters['withdrawal_status']??'')==='all')>All Statuses</option></select><i class="ti ti-chevron-down report-native-select-arrow"></i></div></div>
@elseif($isReportStub)
<div class="col-12">
    <div class="alert alert-info mb-0 report-placeholder-alert {{ in_array($type, $khmerReportTypeTabs, true) ? 'khmer-font-siemreap' : '' }}">
        {{ $reportTypes[$type] }} report tab is added. The independent report form and print layout can be configured next.
    </div>
</div>
@elseif($type === 'score-list')
<div class="col-xl-2 col-md-3 report-filter-field"><label class="form-label">Period</label><div class="report-native-select"><select name="period_type" class="form-select report-select-with-arrow" data-report-period-select><option value="all" @selected(($filters['period_type']??'all')==='all')>Regular + Summer</option><option value="regular" @selected(($filters['period_type']??'')==='regular')>Regular</option><option value="summer" @selected(($filters['period_type']??'')==='summer')>Summer</option></select><i class="ti ti-chevron-down report-native-select-arrow"></i></div></div>
<div class="col-xl-2 col-md-3 report-filter-field"><label class="form-label">Academic Year</label><div class="report-filter-combobox" data-target="reportAcademicYearValue"><button type="button" class="report-filter-toggle"><span>{{ $academicYears->firstWhere('id',(int)($filters['academic_year_id']??0))?->academic_year ?: 'All Academic Years' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Academic Year"><div class="report-filter-options"><button type="button" data-value="">All Academic Years</button>@foreach($academicYears as $year)<button type="button" data-value="{{ $year->id }}">{{ $year->academic_year }}</button>@endforeach</div></div></div><input type="hidden" name="academic_year_id" id="reportAcademicYearValue" value="{{ $filters['academic_year_id']??'' }}"></div>
<div class="col-xl-2 col-md-3 report-filter-field"><label class="form-label">Campus</label><div class="report-filter-combobox" data-target="reportCampusValue"><button type="button" class="report-filter-toggle"><span>{{ $campuses->firstWhere('id',(int)($filters['campus_id']??0))?->campus_name_en ?: 'All Campuses' }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Campus"><div class="report-filter-options"><button type="button" data-value="">All Campuses</button>@foreach($campuses as $campus)<button type="button" data-value="{{ $campus->id }}">{{ $campus->campus_name_en }}</button>@endforeach</div></div></div><input type="hidden" name="campus_id" id="reportCampusValue" value="{{ $filters['campus_id']??'' }}"></div>
<div class="col-xl-2 col-md-3 report-filter-field"><label class="form-label">Grade</label><div class="report-filter-combobox" data-target="reportGradeClassValue"><button type="button" class="report-filter-toggle"><span>{{ data_get(collect($gradeClassOptions)->firstWhere('value',$filters['grade_class']??''), 'label', 'All Grades') }}</span><i class="ti ti-chevron-down"></i></button><div class="report-filter-menu"><input type="search" class="form-control report-filter-search" placeholder="Search Grade"><div class="report-filter-options"><button type="button" data-value="">All Grades</button>@foreach($gradeClassOptions as $option)<button type="button" data-value="{{ $option['value'] }}">{{ $option['label'] }}</button>@endforeach</div></div></div><input type="hidden" name="grade_class" id="reportGradeClassValue" value="{{ $filters['grade_class']??'' }}"></div>
@else
<div class="col-md-4"><label class="form-label">Academic Year</label><select name="academic_year_id" class="form-select"><option value="">All Academic Years</option>@foreach($academicYears as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year_id']??'')==$year->id)>{{ $year->academic_year }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label">Campus</label><select name="campus_id" class="form-select"><option value="">All Campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected(($filters['campus_id']??'')==$campus->id)>{{ $campus->campus_name_en }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label">Grade</label><select name="grade_id" class="form-select"><option value="">All Grades</option>@foreach($grades as $grade)<option value="{{ $grade->id }}" @selected(($filters['grade_id']??'')==$grade->id)>{{ $grade->grade }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label">Class ID</label><input name="class_id" type="number" min="1" class="form-control" value="{{ $filters['class_id']??'' }}"></div>
@endif
@if(in_array($type, ['student-list', 'student-contact-list', 'student-profile-label', 'attendance-list', 'score-list'], true))
    <div class="row g-3 mt-2 report-print-options {{ $type === 'student-list' ? 'student-list-print-options' : ($type === 'student-contact-list' ? 'student-contact-list-print-options' : ($type === 'attendance-list' ? 'attendance-list-print-options' : ($type === 'score-list' ? 'score-list-print-options report-print-options-score-list' : 'student-profile-label-print-options'))) }}">
        @if($type === 'student-list')
            <div class="col-md-3 report-filter-field student-list-print-format-field">
                <label class="form-label">Print Format</label>
                <div class="report-native-select">
                    <select name="print_format" class="form-select report-select-with-arrow">
                        <option value="internal" @selected(($filters['print_format']??'internal')==='internal')>Internal Student List</option>
                        <option value="moeys" @selected(($filters['print_format']??'')==='moeys')>MoEYS Khmer List</option>
                    </select>
                    <i class="ti ti-chevron-down report-native-select-arrow"></i>
                </div>
            </div>
        @elseif($type === 'student-contact-list')
            <div class="col-md-3 report-filter-field student-contact-list-print-format-field">
                <label class="form-label">Print Format</label>
                <div class="report-native-select">
                    <select name="print_format" class="form-select report-select-with-arrow">
                        <option value="internal" @selected(($filters['print_format']??'internal')==='internal')>Internal Student Contact List</option>
                        <option value="moeys" @selected(($filters['print_format']??'')==='moeys')>MoEYS Khmer List</option>
                    </select>
                    <i class="ti ti-chevron-down report-native-select-arrow"></i>
                </div>
            </div>
        @endif
        <div class="col-md-3 report-filter-field {{ $type }}-print-scope-field">
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
        @if(in_array($type, ['score-list', 'attendance-list'], true))
            <div class="col-md-3 report-filter-field">
                <label class="form-label">Report Date</label>
                <input type="date" name="report_date" class="form-control" value="{{ $filters['report_date'] ?? now()->format('Y-m-d') }}">
            </div>
        @endif
        @if($type === 'score-list')
            <div class="col-md-3 report-filter-field">
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
        @if($type === 'student-list')
            <div class="col-md-3 report-filter-field student-list-report-date-field">
                <label class="form-label">Report Date</label>
                <input type="date" name="report_date" class="form-control" value="{{ $filters['report_date'] ?? now()->format('Y-m-d') }}">
            </div>
        @elseif($type === 'student-contact-list')
            <div class="col-md-3 report-filter-field student-contact-list-report-date-field">
                <label class="form-label">Report Date</label>
                <input type="date" name="report_date" class="form-control" value="{{ $filters['report_date'] ?? now()->format('Y-m-d') }}">
            </div>
        @endif
        <div class="col-md-3 report-filter-field report-classes-to-print-field">
            <label class="form-label">Classes to Print</label>
            <select name="print_grade_classes[]" class="form-select" multiple size="3">
                @foreach($gradeClassOptions as $option)
                    <option value="{{ $option['value'] }}" @selected(in_array($option['value'], $filters['print_grade_classes']??[], true))>{{ $option['label'] }}</option>
                @endforeach
            </select>
            <div class="form-hint">Search and tick one or more classes to print.</div>
        </div>
    </div>
@endif
</div></div></form></div>
<div class="card report-preview-card">
    <div class="card-header d-flex align-items-center justify-content-between gap-2 report-preview-header {{ in_array($type, ['attendance-list', 'student-list', 'student-contact-list', 'score-list', 'student-statistics', 'student-statistics-detail', 'withdrawn-students', 'student-id-books-moeys', 'moeys-sikkhakarik-book'], true) ? 'report-header-dark-blue' : '' }}">
        <div class="report-preview-title-wrap">
            <h3 class="card-title mb-0">REPORT PREVIEW</h3>
            @if($type === 'student-id-books-moeys')
                <div class="report-preview-note">Entry grade shows old and new students. Other grades show new students only.</div>
            @endif
        </div>
        @if(($type === 'student-list' || $type === 'student-contact-list' || $type === 'student-profile-label' || $type === 'student-profile-label'))
            <div class="report-summary-card">
                <div class="report-summary-item report-summary-total">
                    <div class="report-summary-label">Total Students</div>
                    <div class="report-summary-number">{{ number_format($studentSummary['total'] ?? 0) }}</div>
                    @if(!in_array($type, ['student-list', 'student-contact-list'], true))
                    <div class="report-summary-gender">F: {{ number_format($studentSummary['total_female'] ?? 0) }} <span>|</span> M: {{ number_format($studentSummary['total_male'] ?? 0) }}</div>
                    @endif
                </div>
                <div class="report-summary-divider"></div>
                <div class="report-summary-item report-summary-new">
                    <div class="report-summary-label">New Students</div>
                    <div class="report-summary-number">{{ number_format($studentSummary['new_total'] ?? 0) }}</div>
                    @if(!in_array($type, ['student-list', 'student-contact-list'], true))
                    <div class="report-summary-gender">F: {{ number_format($studentSummary['new_female'] ?? 0) }} <span>|</span> M: {{ number_format($studentSummary['new_male'] ?? 0) }}</div>
                    @endif
                </div>
            </div>
        @elseif($type === 'withdrawn-students')
            <div class="report-summary-card">
                <div class="report-summary-item report-summary-total">
                    <div class="report-summary-label">Total Students</div>
                    <div class="report-summary-number">{{ number_format(collect($withdrawals ?? [])->count()) }}</div>
                </div>
            </div>
        @elseif($type === 'moeys-sikkhakarik-book')
            <div class="report-summary-card report-transcript-summary-card">
                <div class="report-summary-item report-summary-total">
                    <div class="report-summary-label">Total Students</div>
                    <div class="report-summary-number">{{ number_format($transcriptTotalStudents) }}</div>
                </div>
            </div>
        @endif
        @if(!$isReportStub)
            <div class="report-preview-actions d-flex gap-2 ms-auto">
                @if($type === 'moeys-id-number-book')
                    <a class="btn btn-outline-success report-excel-link" href="{{ route('reports.excel',$type) . '?' . http_build_query($filters) }}"><i class="ti ti-file-spreadsheet me-1"></i>Export Excel</a>
                @elseif($type === 'student-id-books-moeys')
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
                @elseif($type === 'moeys-sikkhakarik-book')
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="ti ti-printer me-1"></i>Print</button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item report-print-link" target="_blank" data-report-print-mode="cover" href="{{ route('reports.show',$type) . '?' . http_build_query($filters + ['print_mode' => 'cover']) }}"><i class="ti ti-book-2 me-2"></i>Print Cover</a>
                            <a class="dropdown-item report-print-link" target="_blank" data-report-print-mode="content" href="{{ route('reports.show',$type) . '?' . http_build_query($filters + ['print_mode' => 'content']) }}"><i class="ti ti-files me-2"></i>Print Content</a>
                        </div>
                    </div>
                    <a class="btn btn-outline-success report-excel-link" href="{{ route('reports.excel',$type) . '?' . http_build_query($filters) }}"><i class="ti ti-file-spreadsheet me-1"></i>Excel</a>
                    <a class="btn btn-outline-danger report-pdf-link" href="{{ route('reports.pdf',$type) . '?' . http_build_query($filters + ['print_mode' => 'content']) }}"><i class="ti ti-file-type-pdf me-1"></i>PDF</a>
                @else
                    <a class="btn btn-outline-primary report-print-link" target="_blank" href="{{ route('reports.show',$type) . '?' . http_build_query($filters) }}"><i class="ti ti-printer me-1"></i>Print</a>
                    <a class="btn btn-outline-success report-excel-link" href="{{ route('reports.excel',$type) . '?' . http_build_query($filters) }}"><i class="ti ti-file-spreadsheet me-1"></i>Excel</a>
                    <a class="btn btn-outline-danger report-pdf-link" href="{{ route('reports.pdf',$type) . '?' . http_build_query($filters) }}"><i class="ti ti-file-type-pdf me-1"></i>PDF</a>
                @endif
            </div>
        @endif
    </div>
    <div class="card-body report-preview-body">
        @if(($type === 'student-list' || $type === 'student-contact-list' || $type === 'student-profile-label' || $type === 'student-profile-label') && ($hasMorePreviewRows ?? false))
            <div class="alert alert-info mb-3">Showing first {{ $previewLimit }} students for fast preview. Print and Excel include all matching students.</div>
        @endif
        @if($type === 'moeys-id-number-book' && ($hasMorePreviewRows ?? false))
            <div class="alert alert-info mb-3">Showing first {{ $previewLimit }} students for fast preview. Excel includes all matching students.</div>
        @endif
        <div class="table-responsive">@include('reports._table')</div>
    </div>
</div></div></div>






<script id="report-filter-critical-menu-script">
document.addEventListener('DOMContentLoaded', function () {
    var closeMenus = function () {
        document.querySelectorAll('.report-filter-combobox.is-open, .report-class-picker.is-open').forEach(function (box) {
            box.classList.remove('is-open');
        });
    };

    document.querySelectorAll('.report-filter-combobox').forEach(function (box) {
        var toggle = box.querySelector('.report-filter-toggle');
        var target = document.getElementById(box.dataset.target || '');
        var options = Array.prototype.slice.call(box.querySelectorAll('.report-filter-options button'));
        if (!toggle || !target) return;

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var wasOpen = box.classList.contains('is-open');
            closeMenus();
            if (!wasOpen) box.classList.add('is-open');
        });

        options.forEach(function (option) {
            option.addEventListener('click', function (event) {
                event.preventDefault();
                target.value = option.dataset.value || '';
                var label = toggle.querySelector('span');
                if (label) label.textContent = option.textContent.trim();
                target.dispatchEvent(new Event('change', { bubbles: true }));
                closeMenus();
            });
        });
    });

    document.addEventListener('click', closeMenus);
    closeMenus();
});
</script>
@endsection

