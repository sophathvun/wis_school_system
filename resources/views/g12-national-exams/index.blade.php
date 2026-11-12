@extends('layouts.app')
@section('title','G12 National Exams')
@section('page-header')<div class="container-fluid"><div class="page-pretitle">G12 National Exams</div><h2 class="page-title"><i class="ti {{ \App\Support\G12NationalExamPermissions::ICONS[$tab] }} me-2"></i>{{ \App\Support\G12NationalExamPermissions::submenus()[$tab][0] }}</h2></div>@endsection
@section('content')
@vite(['resources/css/pages/g12-national-exams.css','resources/js/g12NationalExams.js'])
<div data-g12-exams data-tab="{{ $tab }}" data-base="{{ url('/g12-national-exams') }}">
@if($tab==='reports')
<div class="g12-reports-layout is-collapsed" data-g12-report-layout>
    <aside class="card g12-report-navigation">
        <div class="card-header g12-header"><h3 class="card-title g12-report-nav-label">Reports</h3><button type="button" class="btn btn-icon btn-outline-light ms-auto" data-g12-report-toggle aria-expanded="false" aria-controls="g12ReportTabs" aria-label="Expand report tabs"><i class="ti ti-layout-sidebar-left-expand" aria-hidden="true"></i></button></div>
        <div class="nav flex-column p-2" id="g12ReportTabs" role="tablist" aria-orientation="vertical">
            @foreach(['certificate'=>['Grade A Certificate','ti-certificate'],'graph'=>['Passed/Failed Graph','ti-chart-bar'],'statistics'=>['Passed/Failed Statistics','ti-table']] as $key=>[$label,$icon])
            <button type="button" class="nav-link text-start @if($loop->first) active @endif" id="g12-report-tab-{{ $key }}" role="tab" aria-label="{{ $label }}" title="{{ $label }}" aria-selected="{{ $loop->first?'true':'false' }}" aria-controls="g12-report-panel-{{ $key }}" data-g12-report-tab="{{ $key }}"><i class="ti {{ $icon }} me-2" aria-hidden="true"></i><span class="g12-report-nav-label">{{ $label }}</span></button>
            @endforeach
        </div>
    </aside>
    <div class="g12-report-content">
@endif
@if(in_array($tab,['records','assign-centers','scoring','results','certificates','reports']))
<div class="card mb-3"><div class="card-header g12-header"><h3 class="card-title"><i class="ti ti-filter me-2"></i>Datas Filter</h3><button type="button" class="btn btn-icon btn-outline-primary g12-filter-toggle ms-auto" data-g12-filter-toggle aria-expanded="true" aria-controls="g12Filters" aria-label="Collapse data filters"><i class="ti ti-chevron-up" aria-hidden="true"></i></button></div>
<form id="g12Filters" @class(['card-body','g12-fields','g12-filter-fields','g12-record-filter-fields'=>$tab==='records','g12-assign-filter-fields'=>$tab==='assign-centers','g12-scoring-filter-fields'=>in_array($tab,['scoring','results'])]) data-g12-filters>
@php
$filterFields=$tab==='records'
    ? ['academic_year'=>'Academic Year','language_exam'=>'Exam Language','class_selected'=>'Selected Class','campus_id'=>'Campus','grade'=>'Grade']
    : ($tab==='assign-centers'
        ? ['academic_year'=>'Academic Year','campus_id'=>'Campus','language_exam'=>'Exam Language','class_selected'=>'Select','grade'=>'Grade']
        : ($tab==='scoring'
            ? ['academic_year'=>'Academic Year','campus_id'=>'Campus','class_selected'=>'Selected','language_exam'=>'Language Exam','center_id'=>'Exam Center','record_id'=>'Student Name']
            : ($tab==='results'
                ? ['academic_year'=>'Academic Year','campus_id'=>'Campus','class_selected'=>'Selected','language_exam'=>'Language Exam','center_id'=>'Exam Center','result_status'=>'Result','record_id'=>'Student Name']
                : ['academic_year'=>'Academic Year','campus_id'=>'Campus','grade'=>'Grade / Class','center_id'=>'Exam Center','result_status'=>'Result','certificate_type'=>'Certificate Type','certificate_status'=>'Certificate Status'])));
@endphp
@if($tab==='reports')
@php($filterFields=['academic_year'=>'Academic Year','campus_id'=>'Campus','record_id'=>'Student Name'])
@endif
@foreach($filterFields as $key=>$label)
<div class="scholarship-field premium-form-field"><label class="form-label" for="filter-{{ $key }}">{{ $label }}</label><select class="form-select" id="filter-{{ $key }}" name="{{ $key }}" data-placeholder="" data-empty-label="All {{ $label }}"><option value="">All {{ $label }}</option></select></div>
@endforeach
<div class="scholarship-field premium-form-field g12-filter-search-field"><label class="form-label" for="g12Search">Search</label><input class="form-control" type="search" id="g12Search" name="search" placeholder="Student ID or name" aria-label="Search by student ID or name" autocomplete="off" data-search-normalized="true"><i class="ti ti-search g12-filter-search-icon" aria-hidden="true"></i></div>
</form></div>
@if($tab==='certificates')
<div class="g12-tracking-summary-grid mb-3" data-g12-certificate-summary aria-live="polite">
<section class="card g12-tracking-summary-card g12-tracking-candidates" aria-label="Candidate totals">
    <div class="card-body">
        <div class="g12-tracking-summary-heading"><span class="g12-tracking-summary-icon"><i class="ti ti-users" aria-hidden="true"></i></span><h3>Total Candidates</h3></div>
        <div class="g12-tracking-summary-total"><span>Total</span><strong data-g12-certificate-total>0</strong></div>
        <div class="g12-tracking-summary-results"><span class="text-success">Passed: <strong data-g12-certificate-passed>0</strong> (<span data-g12-certificate-passed-percentage>0%</span>)</span><span class="g12-tracking-summary-divider" aria-hidden="true">|</span><span class="text-danger">Failed: <strong data-g12-certificate-failed>0</strong> (<span data-g12-certificate-failed-percentage>0%</span>)</span></div>
        <div class="g12-tracking-summary-progress" aria-hidden="true"><span class="bg-success" data-g12-candidate-passed-bar></span><span class="bg-danger" data-g12-candidate-failed-bar></span></div>
        <div class="g12-tracking-summary-notes" data-g12-candidate-pending hidden>Awaiting results: <strong data-g12-certificate-pending>0</strong></div>
    </div>
</section>
@foreach(['soft'=>['Provisional Certificates','ti-certificate'],'hard'=>['Diplomas','ti-school']] as $type=>[$title,$icon])
<section class="card g12-tracking-summary-card g12-tracking-{{ $type }}" data-g12-collection-card="{{ $type }}" aria-label="{{ $title }} collection totals">
    <div class="card-body">
        <div class="g12-tracking-summary-heading"><span class="g12-tracking-summary-icon"><i class="ti {{ $icon }}" aria-hidden="true"></i></span><h3>{{ $title }}</h3></div>
        <div class="g12-tracking-summary-total"><span>Total <small>Eligible candidates</small></span><strong data-collection-total>0</strong></div>
        <div class="g12-tracking-summary-results"><span class="text-success">Collected: <strong data-collection-collected>0</strong> (<span data-collection-collected-percentage>0%</span>)</span><span class="g12-tracking-summary-divider" aria-hidden="true">|</span><span class="g12-tracking-not-collected">Not Yet Collected: <strong data-collection-not-yet>0</strong> (<span data-collection-not-yet-percentage>0%</span>)</span></div>
        <div class="g12-tracking-summary-progress" aria-hidden="true"><span class="bg-success" data-collection-collected-bar></span><span class="g12-tracking-pending-bar" data-collection-not-yet-bar></span><span class="bg-danger" data-collection-review-bar></span></div>
        <div class="g12-tracking-summary-notes"><span>Awaiting certificate: <strong data-collection-awaiting>0</strong></span><span>Available at school: <strong data-collection-available>0</strong></span></div>
        <div class="g12-tracking-summary-exceptions text-danger" data-collection-review-note hidden>Needs review: <strong data-collection-review>0</strong> (<span data-collection-review-percentage>0%</span>)</div>
        <div class="g12-tracking-summary-exceptions text-secondary" data-collection-not-applicable-note hidden>Not applicable: <strong data-collection-not-applicable>0</strong></div>
    </div>
</section>
@endforeach
</div>
@endif
@if($tab==='assign-centers')
<section class="card g12-assignment-summary-card mb-3" data-g12-assignment-summary aria-label="Exam center assignment totals"><div class="card-body g12-assignment-summary-grid">
<article class="g12-assignment-summary-item"><div class="g12-assignment-summary-title">Total</div><strong class="g12-assignment-summary-total" data-g12-assignment-summary-total>0</strong><div class="g12-assignment-summary-notes"><span>Male: <strong data-g12-assignment-summary-male>0</strong></span><span>Female: <strong data-g12-assignment-summary-female>0</strong></span></div></article>
<article class="g12-assignment-summary-item"><div class="g12-assignment-summary-title">Exam Center</div><div class="g12-assignment-summary-notes"><span>Assigned: <strong data-g12-assignment-summary-center-assigned>0</strong></span><span>Blank: <strong data-g12-assignment-summary-center-blank>0</strong></span></div></article>
<article class="g12-assignment-summary-item"><div class="g12-assignment-summary-title">Room No.</div><div class="g12-assignment-summary-notes"><span>Assigned: <strong data-g12-assignment-summary-room-assigned>0</strong></span><span>Blank: <strong data-g12-assignment-summary-room-blank>0</strong></span></div></article>
<article class="g12-assignment-summary-item"><div class="g12-assignment-summary-title">Seat No.</div><div class="g12-assignment-summary-notes"><span>Assigned: <strong data-g12-assignment-summary-seat-assigned>0</strong></span><span>Blank: <strong data-g12-assignment-summary-seat-blank>0</strong></span></div></article>
</div></section>
<div class="card"><div class="card-header g12-header"><h3 class="card-title">Exam Center List</h3></div>
<div class="table-responsive g12-assignment-table-wrap"><table class="table table-vcenter card-table g12-assignment-table"><thead><tr><th class="text-center">No.</th><th>Photo</th><th>Student</th><th>Grade</th><th>Phone Number</th><th>Examinations</th><th>Room</th><th>Seat</th><th>Added by</th></tr></thead><tbody data-g12-records><tr><td colspan="9" class="text-center py-4">Select an Academic Year to display students.</td></tr></tbody></table></div>
<div class="card-footer"><select id="g12PerPage" hidden><option value="15">15</option><option value="25">25</option><option value="50">50</option><option value="100">100</option><option value="all">All</option></select><div id="g12Pagination"></div></div></div>
@elseif(in_array($tab,['scoring','results']))
<section class="card g12-scoring-summary-card mb-3" data-g12-scoring-summary aria-label="Scoring candidate totals">
<div class="card-body g12-scoring-summary-grid">
<article class="g12-scoring-summary-section g12-scoring-summary-candidates">
<span class="g12-scoring-summary-icon"><i class="ti ti-users" aria-hidden="true"></i></span>
<div><div class="g12-scoring-summary-title">Total Candidates</div><strong class="g12-scoring-summary-total" data-g12-scoring-summary-total>0</strong><div class="g12-scoring-summary-notes"><span>Male: <strong data-g12-scoring-summary-male>0</strong></span><span>Female: <strong data-g12-scoring-summary-female>0</strong></span></div></div>
</article>
<article class="g12-scoring-summary-section g12-scoring-summary-breakdown">
<div class="g12-scoring-summary-heading"><span class="g12-scoring-summary-icon"><i class="ti ti-chart-dots-3" aria-hidden="true"></i></span><div class="g12-scoring-summary-title">Candidate Breakdown</div></div>
<div class="g12-scoring-breakdown-rows">
<div class="g12-scoring-breakdown-total"><span>Total Khmer: <strong data-g12-scoring-summary-khmer>0</strong></span><span>Total English: <strong data-g12-scoring-summary-english>0</strong></span></div>
<div><span>Science-Khmer: <strong data-g12-scoring-summary-science-khmer>0</strong></span><span>Social Science-Khmer: <strong data-g12-scoring-summary-social-khmer>0</strong></span></div>
<div><span>Science-English: <strong data-g12-scoring-summary-science-english>0</strong></span><span>Social Science-English: <strong data-g12-scoring-summary-social-english>0</strong></span></div>
</div>
</article>
<article class="g12-scoring-summary-section g12-scoring-summary-grades">
<div class="g12-scoring-summary-heading"><span class="g12-scoring-summary-icon"><i class="ti ti-award" aria-hidden="true"></i></span><div><div class="g12-scoring-summary-title">Letter Grade Distribution</div><small>Percentage of filtered candidates</small></div></div>
<div class="g12-scoring-grade-grid" data-g12-scoring-grade-list>
@foreach(['A','B','C','D','E','F','pending'] as $grade)
<div class="g12-scoring-grade-stat" data-grade="{{ $grade }}"><span class="g12-scoring-grade-badge g12-grade-{{ strtolower($grade) }}">{{ $grade==='pending'?'Awaiting':$grade }}</span><strong data-grade-total>0</strong><span data-grade-percentage>0%</span><small>M: <b data-grade-male>0</b> <i>|</i> F: <b data-grade-female>0</b></small></div>
@endforeach
</div>
</article>
</div>
</section>
<div class="card g12-scoring-workspace"><div class="card-header g12-header flex-wrap"><h3 class="card-title"><i class="ti {{ $tab==='scoring'?'ti-calculator':'ti-award' }} me-2" aria-hidden="true"></i>{{ $tab==='scoring'?'Scoring List':'Result List' }}</h3>
@if($tab==='scoring')<div class="g12-scoring-workflows ms-auto">
<span class="g12-workflow-label"><i class="ti ti-keyboard me-1"></i>Manual Entry</span>
@if($permissions['scoring-csv-export'])<button type="button" class="btn btn-outline-light" data-g12-score-csv-export><i class="ti ti-file-download me-1"></i>Export CSV</button>@endif
@if($permissions['scoring-csv-import'])<button type="button" class="btn btn-outline-light" data-g12-score-csv-import><i class="ti ti-file-upload me-1"></i>Import CSV</button>@endif
@if($permissions['scoring-ocr-upload'])<button type="button" class="btn btn-outline-light" data-g12-score-ocr-open><i class="ti ti-scan me-1"></i>Scan Images</button>@endif
@if($permissions['scoring-ocr-settings'])<button type="button" class="btn btn-icon btn-outline-light" data-g12-score-ocr-settings aria-label="Image scanning settings"><i class="ti ti-settings"></i></button>@endif
</div>
@elseif($permissions['results-export'])
<div class="g12-result-report-actions ms-auto">
<div class="dropdown">
    <button type="button" class="btn btn-outline-light" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><i class="ti ti-settings me-1" aria-hidden="true"></i>Report Settings</button>
    <div class="dropdown-menu dropdown-menu-end p-3 g12-report-date-menu">
        <label for="g12ReportDate" class="form-label">Report Date</label>
        <input type="date" id="g12ReportDate" class="form-control" data-g12-report-date value="{{ now('Asia/Bangkok')->format('Y-m-d') }}">
        <label for="g12ReportLocation" class="form-label mt-3">Date Location</label>
        <input type="text" id="g12ReportLocation" class="form-control school-profile-khmer" data-g12-report-location list="g12ReportLocations" value="រាជធានីភ្នំពេញ" maxlength="150" autocomplete="off">
        <datalist id="g12ReportLocations">
            <option value="រាជធានីភ្នំពេញ"></option>
            @foreach(['បន្ទាយមានជ័យ','បាត់ដំបង','កំពង់ចាម','កំពង់ឆ្នាំង','កំពង់ស្ពឺ','កំពង់ធំ','កំពត','កណ្ដាល','កោះកុង','ក្រចេះ','មណ្ឌលគិរី','ព្រះវិហារ','ព្រៃវែង','ពោធិ៍សាត់','រតនគិរី','សៀមរាប','ព្រះសីហនុ','ស្ទឹងត្រែង','ស្វាយរៀង','តាកែវ','ឧត្តរមានជ័យ','កែប','ប៉ៃលិន','ត្បូងឃ្មុំ'] as $province)
            <option value="ខេត្ត{{ $province }}"></option>
            @endforeach
        </datalist>
        <small class="text-secondary d-block mt-2">The DoEYS report's Khmer lunar and solar dates are filled automatically.</small>
    </div>
</div>
@foreach(['print'=>['Print','ti-printer'],'xlsx'=>['Excel','ti-file-spreadsheet'],'pdf'=>['PDF','ti-file-type-pdf']] as $format=>[$action,$icon])
<div class="dropdown">
    <button type="button" class="btn btn-outline-light dropdown-toggle" id="g12ReportMenu-{{ $format }}" data-bs-toggle="dropdown" aria-expanded="false"><i class="ti {{ $icon }} me-1" aria-hidden="true"></i>{{ $action }}</button>
    <div class="dropdown-menu dropdown-menu-end g12-report-menu" aria-labelledby="g12ReportMenu-{{ $format }}">
        @foreach(\App\Services\G12ResultReports::TYPES as $value=>$label)
        <button type="button" class="dropdown-item g12-report-menu-item" data-g12-export="{{ $format }}" data-g12-report-type="{{ $value }}">
            <span class="g12-report-menu-icon"><i class="ti {{ match($value) {'grade-a'=>'ti-award','grade-a-campus'=>'ti-building','result-list'=>'ti-list-check','statistics-doeys'=>'ti-report-analytics',default=>'ti-chart-pie'} }}" aria-hidden="true"></i></span>
            <span>{{ $label }}</span>
        </button>
        @endforeach
    </div>
</div>
@endforeach
</div>
@endif</div>
<div class="table-responsive g12-scoring-table-wrap"><table class="table table-vcenter card-table g12-scoring-table @if($tab==='results') g12-results-table @endif"><thead><tr><th class="text-center">No.</th><th>Photo</th><th>Student</th><th>Grade</th><th>Examinations</th><th>{{ $tab==='scoring'?'Subject Scores':'Subject Results' }}</th><th>Score / Rank</th><th>Letter Grade</th><th>Result</th><th>Actions</th></tr></thead><tbody data-g12-records><tr><td colspan="10" class="text-center py-4">Select an Academic Year to display students.</td></tr></tbody></table></div>
<div class="card-footer"><select id="g12PerPage" hidden><option value="15">15</option><option value="25">25</option><option value="50">50</option><option value="100">100</option><option value="all">All</option></select><div id="g12Pagination"></div></div></div>
@else
@if($tab==='records')
<section class="g12-summary-grid mb-3" data-g12-summary aria-label="Student exam record totals">
<article class="card g12-summary-card"><div class="card-body g12-summary-columns"><div class="g12-summary-column"><div class="g12-summary-heading g12-summary-heading-total"><span class="g12-summary-icon"><i class="ti ti-users" aria-hidden="true"></i></span><div class="g12-summary-total-content"><div class="text-secondary">Total Students</div><strong class="g12-summary-total" data-g12-summary-total>0</strong><div class="g12-summary-notes"><div class="g12-summary-note-row"><span>Male: <strong data-g12-summary-male>0</strong></span><span>Female: <strong data-g12-summary-female>0</strong></span></div></div></div></div></div><div class="g12-summary-column g12-summary-class-column"><div class="g12-summary-heading"><span class="g12-summary-icon"><i class="ti ti-books" aria-hidden="true"></i></span><div class="g12-summary-notes g12-summary-class-notes"><div class="g12-summary-note-row school-profile-khmer"><span>វិទ្យាសាស្ត្រ (ខ្មែរ): <strong data-g12-summary-science-khmer>0</strong></span><span>វិទ្យាសាស្ត្រ (អង់គ្លេស): <strong data-g12-summary-science-english>0</strong></span></div><div class="g12-summary-note-row school-profile-khmer"><span>វិទ្យាសាស្ត្រសង្គម (ខ្មែរ): <strong data-g12-summary-social-khmer>0</strong></span><span>វិទ្យាសាស្ត្រសង្គម (អង់គ្លេស): <strong data-g12-summary-social-english>0</strong></span></div></div></div></div><div class="g12-summary-column g12-summary-campus-column"><div class="g12-summary-heading"><span class="g12-summary-icon"><i class="ti ti-building" aria-hidden="true"></i></span><div><div class="text-secondary">Student by Campus</div><div class="g12-summary-campuses" data-g12-summary-campuses><div class="g12-summary-empty">Select an Academic Year to display totals.</div></div></div></div></div></div></article>
</section>
@endif
<div class="card" @if($tab==='reports') id="g12-report-panel-certificate" role="tabpanel" aria-labelledby="g12-report-tab-certificate" data-g12-report-panel="certificate" @endif><div class="card-header g12-header"><h3 class="card-title">{{ $tab==='certificates'?'Certificate Tracking':($tab==='reports'?'Grade A Certificate':'List of Candidates') }}</h3>
<div class="ms-auto d-flex gap-2 flex-wrap">
@if($tab==='records'&&$permissions['export'])<button type="button" class="btn btn-outline-light" data-g12-export="print"><i class="ti ti-printer me-1"></i>Print</button><button type="button" class="btn btn-outline-light" data-g12-export="xlsx"><i class="ti ti-file-spreadsheet me-1"></i>Excel</button><button type="button" class="btn btn-outline-light" data-g12-export="pdf"><i class="ti ti-file-type-pdf me-1"></i>PDF</button>@endif
@if($tab==='records'&&$permissions['create'])<button type="button" class="btn btn-outline-light" data-g12-new><i class="ti ti-plus me-1"></i>Add New</button>@endif
</div></div>
@if($tab==='reports')
@include('g12-national-exams._grade-a-certificate-editor')
@endif
<div class="table-responsive @if($tab==='records') g12-record-table-wrap @elseif($tab==='certificates') g12-certificate-table-wrap @endif"><table class="table table-vcenter card-table @if($tab==='records') g12-record-list @elseif($tab==='certificates') g12-certificate-table @endif"><thead><tr>
@if($tab==='records')
<th class="text-center">No.</th><th>Photo</th><th>Student</th><th>Grade</th><th>Parents</th><th>G9 National Exams</th><th>G12 National Exams</th><th>Actions</th>
@else
<th>No.</th><th>Photo</th><th>Student</th><th>Grade</th><th>Examination</th><th>Provisional Certificate</th><th>Diploma</th><th>Actions</th>
@endif
</tr></thead><tbody data-g12-records><tr><td colspan="8" class="text-center py-4">Select an Academic Year to display records.</td></tr></tbody></table></div>
<div class="card-footer"><select id="g12PerPage" hidden><option value="15">15</option><option value="25">25</option><option value="50">50</option><option value="100">100</option><option value="all">All</option></select><div id="g12Pagination"></div></div></div>
@endif
@if($tab==='reports')
@foreach(['graph'=>'Passed/Failed Graph','statistics'=>'Passed/Failed Statistics'] as $key=>$label)
<section class="card" id="g12-report-panel-{{ $key }}" role="tabpanel" aria-labelledby="g12-report-tab-{{ $key }}" data-g12-report-panel="{{ $key }}" hidden>
    <div class="card-header g12-header flex-wrap gap-2"><h3 class="card-title">{{ $label }}</h3>
        @if($key==='graph' && $permissions['export'])<div class="ms-auto d-flex gap-2"><button type="button" class="btn btn-outline-light" data-g12-graph-export="pdf" disabled><i class="ti ti-file-type-pdf me-1" aria-hidden="true"></i>Export PDF</button><button type="button" class="btn btn-outline-light" data-g12-graph-export="image" disabled><i class="ti ti-photo me-1" aria-hidden="true"></i>Export Image</button></div>@endif
    </div>
    <div class="card-body" data-g12-report-summary="{{ $key }}">Select an Academic Year to display the report.</div>
</section>
@endforeach
</div></div>
@endif
@elseif($tab==='centers')
<div class="card"><div class="card-header g12-header flex-wrap"><h3 class="card-title">Exam Center Settings</h3><div class="g12-center-header-actions ms-auto"><div class="input-icon g12-center-search"><span class="input-icon-addon"><i class="ti ti-search" aria-hidden="true"></i></span><input type="search" class="form-control" data-g12-center-search placeholder="Search exam centers" aria-label="Search exam centers"></div>@if($permissions['centers-manage'])<button class="btn btn-outline-light" data-g12-center-new><i class="ti ti-plus me-1"></i>Add Exam Center</button>@endif</div></div><div class="card-body"><div class="table-responsive"><table class="table"><thead><tr><th class="text-center">No.</th><th aria-sort="ascending"><button type="button" class="g12-center-sort is-active" data-g12-center-sort="name" data-sort-label="Exam Center" aria-label="Sort Exam Center descending">Exam Center <i class="ti ti-arrow-up" aria-hidden="true" data-g12-center-sort-icon="name"></i></button></th><th>Status</th><th>Action</th></tr></thead><tbody data-g12-centers><tr><td colspan="4" class="text-center py-4">Loading exam centers&hellip;</td></tr></tbody></table></div></div><div class="card-footer"><select id="g12CentersPerPage" hidden><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select><div id="g12CentersPagination"></div></div></div>
@elseif($tab==='imports')
<div class="card mb-3"><div class="card-header g12-header flex-wrap gap-2"><h3 class="card-title"><i class="ti ti-file-import me-2"></i>Import MoEYS Excel</h3>@if($permissions['import'])<a class="btn btn-outline-light ms-auto" href="{{ route('g12-national-exams.template') }}" data-g12-template><i class="ti ti-file-download me-1"></i>Download Excel Template</a>@endif</div><div class="card-body">
<p>Sheet 1: student exam records. Sheet 2: exam center settings. Preview changes and resolve campus / center mappings before import. Existing documents and staff-recorded certificate collection are retained.</p>
@if($permissions['import'])<p class="text-secondary">New to importing? Download the template with a sample student and instructions. Replace the sample row with your data and keep the column headers.</p>@endif
@if($permissions['import'])
<form data-g12-excel-form>
    <div class="g12-excel-dropzone" data-g12-excel-drop tabindex="0" role="button" aria-label="Upload Excel workbook" aria-describedby="g12ExcelHint g12ExcelError">
        <span class="document-upload-icon"><i class="ti ti-cloud-upload" aria-hidden="true"></i></span>
        <div class="fw-bold fs-3">Drop Excel File Here</div>
        <div class="mt-1">or <span class="document-browse-link">Browse File</span></div>
        <div class="document-upload-hint mt-3" id="g12ExcelHint">Supports XLSX · Maximum 20 MB<br>You can also copy and paste an Excel file here (Ctrl+V / Command+V)</div>
        <input class="d-none" type="file" name="file" accept=".xlsx" aria-label="Choose Excel workbook" required>
    </div>
    <div class="g12-excel-file-list" data-g12-excel-files aria-live="polite"></div>
    <div class="invalid-feedback d-block" id="g12ExcelError" data-g12-excel-error role="alert" hidden></div>
    <button class="btn btn-primary mt-3" data-g12-excel-preview disabled><i class="ti ti-eye me-1"></i>Preview Import</button>
</form>
@endif
<div data-g12-import-preview hidden><h3 class="mt-4" data-g12-import-summary></h3><div class="alert alert-info">“Withdrew” imports as <strong>Collected</strong>. Other unrecognized statuses are retained for review. A signed receipt is required for new collection entries. Importing missing centers requires Manage Exam Centers permission.</div><div data-g12-mappings class="g12-fields"></div><div class="d-flex gap-2 my-3"><button class="btn btn-outline-primary" data-g12-reinspect>Refresh Preview</button><button class="btn btn-primary" data-g12-confirm>Confirm Import</button></div><div class="g12-preview-table"><table class="table table-sm"><thead><tr><th>Excel Row</th><th>Student</th><th>Year</th><th>Campus</th><th>Exam Center</th><th>Action</th><th>Issues</th></tr></thead><tbody data-g12-import-rows></tbody></table></div></div>
</div></div>
<div class="card"><div class="card-header"><h3 class="card-title">Your Import / Photo Upload History</h3></div><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>File / Batch</th><th>Type</th><th>Status</th><th>Summary</th></tr></thead><tbody data-g12-history></tbody></table></div></div>
@elseif($tab==='photos')
<div class="card"><div class="card-header g12-header"><h3 class="card-title"><i class="ti ti-photo-up me-2"></i>Bulk Upload Student Photos</h3></div><div class="card-body">
<p>Match the MoEYS photo filename first, then an exact Student ID in the filename. Choose the academic year. Unmatched or ambiguous photos can be assigned manually in the preview. Existing photos require replacement permission and confirmation.</p>
<p class="text-secondary">Student photos are automatically saved as WebP below 40 KB, with proportions preserved and quality optimized. The MoEYS filename is kept for matching; the saved file uses .webp.</p>
@if($permissions['photos-upload'])<form data-g12-photos-form>
<div class="g12-fields mb-3"><div class="scholarship-field"><label class="form-label" for="photoYear">Academic Year</label><select class="form-select" id="photoYear" name="academic_year" data-placeholder="" required disabled aria-describedby="g12PhotoYearError"><option value=""></option></select><div class="invalid-feedback d-block" id="g12PhotoYearError" hidden>Select an academic year before previewing photos.</div></div></div>
<p class="text-secondary" data-g12-photo-year-empty hidden>No student exam records are available for your campuses. Import student exam records first to make their academic years available here.</p>
<div class="g12-photo-upload-area">
    <input type="file" name="files[]" accept=".jpg,.jpeg,.png,.webp,.zip" multiple hidden>
    <button type="button" class="g12-excel-dropzone g12-photo-dropzone" data-g12-photo-drop aria-labelledby="g12PhotoUploadTitle" aria-describedby="g12PhotoUploadHint g12PhotoUploadLimits g12PhotoUploadError">
        <span class="document-upload-icon"><i class="ti ti-photo-up" aria-hidden="true"></i></span>
        <strong id="g12PhotoUploadTitle">Drag &amp; drop photos or ZIP files here</strong>
        <span>or <span class="document-browse-link">browse files</span></span>
        <span class="document-upload-hint" id="g12PhotoUploadHint">Copy files and paste with Ctrl+V (⌘V on Mac). Drop or paste again to add more.</span>
        <span class="g12-photo-upload-formats"><span>JPG / PNG / WebP</span><span>ZIP</span><span>Multiple files</span></span>
    </button>
    <div class="g12-photo-file-selection" data-g12-photo-file-selection hidden>
        <div class="g12-photo-file-summary"><span data-g12-photo-file-summary role="status" aria-live="polite"></span><button type="button" class="btn btn-sm btn-outline-secondary" data-g12-photo-files-clear>Clear All</button></div>
        <ul class="g12-excel-file-list g12-photo-file-list" data-g12-photo-files aria-label="Selected photos and ZIP files"></ul>
    </div>
    <p class="form-hint mt-3 mb-0" id="g12PhotoUploadLimits">Up to 20 MB per image, 200 MB per ZIP / 500 MB expanded, and 5,000 photos per batch. Select up to 100 files at a time; use ZIP files for larger collections.</p>
    <div class="text-danger small mt-2" id="g12PhotoUploadError" data-g12-photo-upload-error role="alert" hidden></div>
</div>
<button class="btn btn-primary mt-3" data-g12-photo-preview-button disabled><i class="ti ti-eye me-1"></i>Preview Photo Matches</button></form>@endif
<p class="form-hint mt-3" data-g12-photo-help role="status">Choose your photos or ZIP, then click Preview Photo Matches to see the photos and select students before uploading.</p>
<div data-g12-photo-preview class="d-none" hidden><div class="d-flex gap-3 align-items-center mt-4 mb-3"><button type="button" class="btn btn-primary" data-g12-photo-confirm disabled>Upload Selected Photos</button><span data-g12-photo-progress role="status"></span></div><progress class="w-100" data-g12-photo-bar value="0" max="1"></progress><div class="g12-preview-table"><table class="table"><thead><tr><th><label class="d-flex align-items-center gap-2 mb-0"><input type="checkbox" class="form-check-input" data-g12-photo-select-all aria-label="Select all photo rows">Select</label></th><th>Photo Filename</th><th>Match</th><th>Student</th><th><label class="d-flex align-items-center gap-2 mb-0 text-nowrap"><input type="checkbox" class="form-check-input" data-g12-photo-replace-all aria-label="Replace all pending photos" disabled>Replace All</label></th><th>Progress</th></tr></thead><tbody data-g12-photo-rows></tbody></table></div></div>
</div></div>
@endif

<div class="modal fade" id="g12EditModal" tabindex="-1" aria-labelledby="g12EditTitle"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" data-g12-edit-form><div class="modal-header"><h3 class="modal-title" id="g12EditTitle">Student Exam Record</h3><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="text-secondary">National exam information is kept separately from the school student profile. Student ID and academic year are fixed after creation.</p><div data-g12-edit-fields></div></div><div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Record</button></div></form></div></div>
<div class="modal fade" id="g12CenterModal" tabindex="-1" aria-labelledby="g12CenterTitle">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" data-g12-center-form>
            <div class="modal-header"><h3 class="modal-title" id="g12CenterTitle">Add Exam Center</h3><button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="premium-form-field mb-3 has-value">
                    <label class="form-label" for="g12CenterCode">Exam Center ID</label>
                    <input id="g12CenterCode" class="form-control" value="Assigned automatically" readonly aria-describedby="g12CenterCodeHint">
                </div>
                <p class="form-hint mb-3" id="g12CenterCodeHint">The system assigns an ID when the center is saved.</p>
                <div class="premium-form-field mb-3">
                    <label class="form-label" for="g12CenterName">Exam Center Name <span class="text-danger">*</span></label>
                    <input id="g12CenterName" name="name" class="form-control" maxlength="255" required autocomplete="off">
                </div>
                <div class="premium-form-field has-value">
                    <label class="form-label" for="g12CenterStatus">Status</label>
                    <select id="g12CenterStatus" name="active" class="form-select"><option value="1">Active</option><option value="0">Inactive</option></select>
                </div>
            </div>
            <div class="modal-footer"><button class="btn me-auto" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Center</button></div>
        </form>
    </div>
</div>
<div class="modal fade" id="g12ViewModal" tabindex="-1" aria-labelledby="g12ViewTitle"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h3 class="modal-title" id="g12ViewTitle">Student National Exam Record</h3><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body" data-g12-detail></div><div class="modal-footer"><button class="btn" data-bs-dismiss="modal">Close</button></div></div></div></div>
@if($tab==='scoring'&&$permissions['scoring-csv-import'])
<div class="modal fade" id="g12ScoreCsvModal" tabindex="-1" aria-labelledby="g12ScoreCsvTitle"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><form data-g12-score-csv-form><div class="modal-header"><div><h3 class="modal-title" id="g12ScoreCsvTitle">Import Scores from CSV</h3><div class="text-secondary">Export the current list first, enter A–F grades, then upload the same CSV for validation.</div></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><label class="g12-dropzone"><i class="ti ti-file-spreadsheet"></i><strong>Choose completed scoring CSV</strong><span>CSV · Maximum 20 MB · Student identifiers and versions must remain unchanged</span><input class="form-control" type="file" name="file" accept=".csv,text/csv" required></label><button class="btn btn-primary mt-3" type="submit"><i class="ti ti-eye me-1"></i>Validate & Preview</button><div data-g12-score-csv-preview hidden><div class="alert mt-3" data-g12-score-csv-summary></div><div class="g12-preview-table"><table class="table table-sm"><thead><tr><th>CSV Row</th><th>Student</th><th>Score / Rank</th><th>Letter Grade</th><th>Issues</th></tr></thead><tbody data-g12-score-csv-rows></tbody></table></div></div></div><div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" data-g12-score-csv-confirm disabled>Confirm Import</button></div></form></div></div></div>
@endif
@if($tab==='scoring'&&$permissions['scoring-ocr-upload'])
<div class="modal fade" id="g12ScoreOcrModal" tabindex="-1" aria-labelledby="g12ScoreOcrTitle"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><form data-g12-score-ocr-form><div class="modal-header"><div><h3 class="modal-title" id="g12ScoreOcrTitle">Scan MoEYS Result Images</h3><div class="text-secondary">One exam center per batch. Every OCR row is reviewed before it changes a student record.</div></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><div class="alert alert-info d-flex flex-wrap gap-3" data-g12-ocr-usage>Loading OCR availability…</div><div class="g12-fields mb-3"><div><label class="form-label">Academic Year</label><select class="form-select" name="academic_year" required></select></div><div><label class="form-label">Exam Center</label><select class="form-select" name="center_id" required></select></div><div><label class="form-label">Exam Language</label><select class="form-select" name="language_exam" required></select></div><div><label class="form-label">Selected Class</label><select class="form-select" name="class_selected" required></select></div><div><label class="form-label">Room (optional)</label><input class="form-control" name="room" maxlength="80"></div></div><label class="g12-dropzone"><i class="ti ti-scan"></i><strong>Choose MoEYS result images</strong><span>JPG, PNG or WebP · up to 250 images · 20 MB each</span><input class="form-control" type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple required></label><button class="btn btn-primary mt-3" type="submit" data-g12-ocr-start><i class="ti ti-scan me-1"></i>Upload & Scan</button><div class="mt-3" data-g12-ocr-progress hidden><div class="d-flex justify-content-between"><strong data-g12-ocr-progress-text></strong><span data-g12-ocr-failures></span></div><progress class="w-100 mt-2" data-g12-ocr-bar></progress></div><div data-g12-ocr-preview hidden><div class="alert alert-warning mt-3">Check every student and grade. Unmatched rows are skipped until a student is selected.</div><div class="g12-preview-table"><table class="table table-sm"><thead><tr><th>Seat</th><th>Matched Student</th><th>Subject Grades</th><th>Score / Rank</th><th>Letter Grade</th><th>Status</th></tr></thead><tbody data-g12-ocr-rows></tbody></table></div></div></div><div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Close</button>@if($permissions['scoring-ocr-review'])<button type="button" class="btn btn-primary" data-g12-ocr-confirm disabled>Confirm Scanned Scores</button>@endif</div></form></div></div></div>
@endif
@if($tab==='scoring'&&$permissions['scoring-ocr-settings'])
<div class="modal fade" id="g12ScoreOcrSettingsModal" tabindex="-1" aria-labelledby="g12ScoreOcrSettingsTitle"><div class="modal-dialog modal-dialog-centered g12-ocr-settings-dialog"><form class="modal-content g12-ocr-settings-modal" data-g12-ocr-settings-form><div class="modal-header"><div class="min-w-0"><h3 class="modal-title" id="g12ScoreOcrSettingsTitle">Result Image Scanning Settings</h3><div class="text-secondary g12-ocr-settings-subtitle">Configure Google Vision for MoEYS result images.</div></div><div class="g12-ocr-header-actions ms-auto"><button class="btn btn-icon btn-outline-primary g12-ocr-help-button" type="button" data-g12-ocr-help aria-expanded="false" aria-controls="g12OcrSetupHelp" aria-label="How to set up result image scanning" title="Setup help"><i class="ti ti-help-circle" aria-hidden="true"></i></button><button class="btn-close g12-ocr-settings-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div></div><div class="modal-body"><section class="g12-ocr-setup-help d-none" id="g12OcrSetupHelp" data-g12-ocr-help-panel aria-live="polite"><div class="d-flex gap-2"><i class="ti ti-bulb text-primary" aria-hidden="true"></i><div><strong>How to set it up</strong><ol><li>Create or select a Google Cloud project and connect billing.</li><li>Enable the <strong>Cloud Vision API</strong>.</li><li>Open <strong>IAM &amp; Admin → Service Accounts</strong> and create a service account.</li><li>Open its <strong>Keys</strong> tab, choose <strong>Add key → Create new key → JSON</strong>.</li><li>Upload the downloaded JSON file below, choose a monthly limit, enable scanning, and save.</li></ol><div class="g12-ocr-help-links"><a href="https://docs.cloud.google.com/vision/docs/setup" target="_blank" rel="noopener">Vision setup <i class="ti ti-external-link"></i></a><a href="https://docs.cloud.google.com/iam/docs/keys-create-delete" target="_blank" rel="noopener">Create JSON key <i class="ti ti-external-link"></i></a></div></div></div></section><div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="g12OcrEnabled" name="enabled" value="1"><label class="form-check-label" for="g12OcrEnabled">Enable Google Cloud Vision scanning</label></div><div class="mb-3"><label class="form-label">Monthly hard limit</label><input class="form-control" type="number" name="monthly_limit" min="1" max="999" value="999" required><div class="form-hint">New image batches stop before this limit is exceeded.</div></div><div class="mb-3"><label class="form-label">School name aliases <span class="text-secondary fw-normal">(one per line)</span></label><textarea class="form-control" name="school_aliases" rows="2" placeholder="Western International School&#10;សាលាវេស្ទើនអន្តរជាតិ"></textarea></div><div class="mb-3"><label class="form-label">Google service-account JSON key</label><input class="form-control" type="file" name="credentials" accept=".json,application/json"><div class="form-hint" data-g12-ocr-credential-status>No key configured.</div></div><div class="alert alert-secondary py-2 mb-0" data-g12-ocr-setting-usage></div></div><div class="modal-footer"><button class="btn" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Settings</button></div></form></div></div>
@endif
@if($tab==='certificates')
<div class="modal fade g12-certificate-modal" id="g12CertificateModal" tabindex="-1" aria-labelledby="g12CertificateModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" data-g12-certificate-form>
            <div class="modal-header g12-certificate-modal-header">
                <div>
                    <div class="g12-certificate-modal-kicker">Certificate Tracking</div>
                    <h3 class="modal-title" id="g12CertificateModalTitle" data-g12-certificate-title>Certificate Documents</h3>
                    <div class="g12-certificate-modal-student" data-g12-certificate-student></div>
                </div>
                <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="record_id">
                <input type="hidden" name="certificate_type">
                <input type="hidden" name="version">

                <div class="g12-certificate-upload-grid">
                    <section class="g12-certificate-upload-card">
                        <div class="g12-certificate-upload-heading"><span class="g12-certificate-upload-icon"><i class="ti ti-certificate" aria-hidden="true"></i></span><div><strong>MoEYS Copy</strong><small>Original certificate received by the school</small></div></div>
                        <div data-g12-certificate-current="source"></div>
                        <label class="g12-certificate-file-control" data-g12-certificate-file-label="source">
                            <i class="ti ti-cloud-upload" aria-hidden="true"></i>
                            <span><strong>Choose certificate image or PDF</strong><small>JPG, PNG and WebP images are optimized automatically</small></span>
                            <input type="file" name="source_file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
                        </label>
                    </section>
                    <section class="g12-certificate-upload-card">
                        <div class="g12-certificate-upload-heading"><span class="g12-certificate-upload-icon is-receipt"><i class="ti ti-signature" aria-hidden="true"></i></span><div><strong>Student-Signed Receipt</strong><small>Copy signed when the student receives it</small></div></div>
                        <div data-g12-certificate-current="receipt"></div>
                        <label class="g12-certificate-file-control" data-g12-certificate-file-label="receipt">
                            <i class="ti ti-cloud-upload" aria-hidden="true"></i>
                            <span><strong>Choose signed copy image or PDF</strong><small>Required before status can be marked Collected</small></span>
                            <input type="file" name="receipt_file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
                        </label>
                    </section>
                </div>

                <section class="g12-certificate-tracking-panel">
                    <div class="g12-certificate-panel-title"><i class="ti ti-progress-check" aria-hidden="true"></i><span>Collection Details</span></div>
                    <div class="g12-certificate-fields">
                        <div><label class="form-label" for="g12CertificateStatus">Status</label><select class="form-select" id="g12CertificateStatus" name="status" required><option value="awaiting">Awaiting from MoEYS</option><option value="available">Available at School</option><option value="collected">Collected by Student</option><option value="review">Needs Review</option><option value="not_applicable">Not Applicable</option></select></div>
                        <div><label class="form-label" for="g12CertificateNumber">Certificate Number</label><input class="form-control" id="g12CertificateNumber" name="number" maxlength="100"></div>
                        <div><label class="form-label" for="g12CertificateAvailableDate">School Received Date</label><input class="form-control" id="g12CertificateAvailableDate" name="available_date" type="date"></div>
                        <div><label class="form-label" for="g12CertificateCollectedDate">Student Received Date</label><input class="form-control" id="g12CertificateCollectedDate" name="collected_date" type="date"></div>
                        <div class="g12-certificate-field-wide"><label class="form-label" for="g12CertificateRecipient">Received By</label><input class="form-control" id="g12CertificateRecipient" name="recipient" maxlength="255" placeholder="Student or authorized recipient name"></div>
                        <div class="g12-certificate-field-wide" data-g12-certificate-reason-field hidden><label class="form-label" for="g12CertificateReason">Correction Reason</label><textarea class="form-control" id="g12CertificateReason" name="reason" rows="2" maxlength="1000" placeholder="Required when correcting a collected record"></textarea></div>
                    </div>
                </section>
                <div class="alert alert-info g12-certificate-storage-note mb-0"><i class="ti ti-photo-check" aria-hidden="true"></i><span>Images are stored as a clear WebP master with a thumbnail below 70 KB. Download creates a high-quality JPG from the master.</span></div>
            </div>
            <div class="modal-footer"><button class="btn" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit" data-g12-certificate-save><i class="ti ti-device-floppy me-1" aria-hidden="true"></i>Save Certificate</button></div>
        </form>
    </div>
</div>
@endif
<div class="modal modal-blur fade g12-photo-viewer" id="g12PhotoViewerModal" tabindex="-1" aria-hidden="true" aria-labelledby="g12PhotoViewerTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h3 class="modal-title" id="g12PhotoViewerTitle">Student Photo</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close photo"></button></div>
            <div class="modal-body p-0">
                <div class="g12-photo-viewer-stage" data-g12-photo-stage><div class="g12-photo-viewer-frame"><img data-g12-photo-image alt="Student photo" hidden></div></div>
                <div class="small text-secondary px-3 py-2" data-g12-photo-status role="status" aria-live="polite"></div>
                <div class="alert alert-danger mx-3 d-none" data-g12-photo-error role="alert"></div>
            </div>
            <div class="modal-footer gap-2">
                <div class="g12-photo-viewer-controls">
                    <button type="button" class="btn btn-icon btn-outline-secondary" data-g12-photo-out aria-label="Zoom out"><i class="ti ti-zoom-out" aria-hidden="true"></i></button>
                    <input type="range" class="form-range" min="0.5" max="3" step="0.1" value="1" data-g12-photo-zoom aria-label="Student photo zoom">
                    <button type="button" class="btn btn-icon btn-outline-secondary" data-g12-photo-in aria-label="Zoom in"><i class="ti ti-zoom-in" aria-hidden="true"></i></button>
                    <button type="button" class="btn btn-outline-secondary" data-g12-photo-reset>Reset</button>
                    <output data-g12-photo-percent>100%</output>
                </div>
                <div class="g12-photo-quality">
                    <label for="g12PhotoQuality">JPG Quality</label>
                    <select class="form-select" id="g12PhotoQuality" data-g12-photo-quality aria-label="JPG download quality">
                        <option value="1">Maximum (100%)</option>
                        <option value="0.95" selected>High (95%)</option>
                        <option value="0.85">Good (85%)</option>
                        <option value="0.7">Smaller file (70%)</option>
                    </select>
                </div>
                <a class="btn btn-primary disabled" data-g12-photo-download aria-disabled="true" tabindex="-1"><i class="ti ti-download me-1" aria-hidden="true"></i>Download JPG</a>
            </div>
        </div>
    </div>
</div>
</div>
<script type="application/json" id="g12Permissions">@json($permissions)</script>
@endsection
