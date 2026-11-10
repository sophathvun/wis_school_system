@extends('layouts.app')
@section('title','G9 National Exams — Exam Result')
@section('page-header','')
@section('content')
@vite(['resources/css/pages/g9-national-exams.css','resources/css/pages/g9-results.css','resources/js/g9Results.js'])
<div class="g9-results-layout" data-g9-results data-base="{{ url('/g9-national-exams/results') }}">
    <aside class="card g9-results-nav" aria-label="Exam result sections">
        <div class="g9-results-nav-header g9-header"><strong>Results</strong><button type="button" class="btn btn-sm btn-outline-light" data-results-nav-toggle aria-expanded="true" aria-controls="g9ResultTabs" aria-label="Collapse result tabs"><i class="ti ti-layout-sidebar-left-collapse" aria-hidden="true"></i></button></div>
        <div class="g9-results-tabs" id="g9ResultTabs" role="tablist" aria-label="G9 result reports" aria-orientation="vertical">
            @foreach(['results'=>['Exam Result','ti-list-details'],'graph'=>['Passed/Failed Graph','ti-chart-donut'],'statistics'=>['Passed/Failed Statistics','ti-chart-bar']] as $key=>[$label,$icon])
                @if($tabs[$key])<button type="button" id="g9ResultTab-{{ $key }}" role="tab" data-results-tab="{{ $key }}" aria-selected="{{ $key==='results'?'true':'false' }}" aria-controls="g9ResultPanel-{{ $key }}" tabindex="{{ $key==='results'?'0':'-1' }}" data-bs-toggle="tooltip" title="{{ $label }}"><i class="ti {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></button>@endif
            @endforeach
        </div>
    </aside>
    <main class="g9-results-content">
        <section class="card mb-3">
            <div class="card-header g9-header"><h3 class="card-title"><i class="ti ti-filter me-2" aria-hidden="true"></i>Data Filters</h3><button type="button" class="btn btn-sm btn-outline-light ms-auto" data-results-filter-toggle aria-expanded="true" aria-controls="g9ResultFilters" aria-label="Collapse filters"><i class="ti ti-chevron-up" aria-hidden="true"></i></button></div>
            <form class="card-body g9-candidate-filters g9-result-filters" id="g9ResultFilters">
                @foreach(['academic_year'=>'Academic Year','exam_center'=>'Exam Center','campus'=>'Campus','room'=>'Room No.','grade'=>'Grade','student'=>'Student Name','result'=>'Result'] as $name=>$label)
                    <div class="premium-form-field staff-form-field"><label class="form-label" for="g9Result-{{ $name }}">{{ $label }}</label><select class="form-select" id="g9Result-{{ $name }}" name="{{ $name }}" aria-label="{{ $label }}"><option value="">{{ $name==='academic_year'?'Select academic year':'All '.strtolower($label) }}</option>
                        @if($name==='academic_year')@foreach($years as $year)<option value="{{ $year->id }}">{{ $year->academic_year }}</option>@endforeach @endif
                        @if($name==='result')@foreach(['passed'=>'Passed','failed'=>'Failed','pending'=>'Pending','absent'=>'Absent'] as $value=>$text)<option value="{{ $value }}">{{ $text }}</option>@endforeach @endif
                    </select></div>
                @endforeach
                <div class="premium-form-field staff-form-field g9-result-search-field"><label class="form-label" for="g9ResultSearch">Search</label><input id="g9ResultSearch" type="search" class="form-control" data-results-search data-search-normalized="true" placeholder="Student ID or name" aria-label="Search student ID or name"><i class="ti ti-search g9-result-search-icon" aria-hidden="true"></i></div>
            </form>
        </section>
        <div class="alert alert-danger" data-results-error role="alert" hidden></div>
        <div class="g9-results-summary mb-3" data-results-summary hidden aria-label="Filtered result summary" aria-live="polite">
            @foreach(['total'=>['Candidates','ti-users','blue'],'passed'=>['Passed','ti-circle-check','green'],'failed'=>['Failed','ti-circle-x','red'],'absent'=>['Absent','ti-user-off','orange']] as $key=>[$label,$icon,$color])
                <article class="card g9-result-metric g9-result-metric-{{ $color }}"><div class="g9-result-metric-top"><span>{{ $label }}</span><i class="ti {{ $icon }} text-{{ $color }}" aria-hidden="true"></i></div>
                    @if($key==='passed')
                        <div class="g9-result-total-line"><span class="text-secondary">Total:</span><strong class="text-{{ $color }}" data-result-metric="{{ $key }}">—</strong><small class="text-secondary" data-result-passed-percentage aria-label="Passed percentage of filtered candidates, excluding absent students">(—)</small></div>
                    @elseif($key==='failed')
                        <div class="g9-result-total-line"><strong class="text-{{ $color }}" data-result-metric="{{ $key }}">—</strong><small class="text-secondary" data-result-failed-percentage aria-label="Failed percentage of filtered candidates, excluding absent students">(—)</small></div>
                    @else
                        <strong class="text-{{ $color }}" data-result-metric="{{ $key }}">—</strong>
                    @endif
                    @if($key==='total')
                        <div class="g9-result-language-breakdown text-secondary" aria-label="Candidates by exam language">
                            @foreach(['english'=>'English','khmer'=>'Khmer'] as $language=>$text)
                                <span>{{ $text }}: <strong data-result-candidate-language="{{ $language }}">0</strong> <small data-result-candidate-language-percentage="{{ $language }}">(—)</small></span>
                            @endforeach
                        </div>
                    @elseif($key==='passed')
                        <div class="g9-result-language-breakdown text-secondary" aria-label="Passed candidates by exam language">
                            @foreach(['english'=>'English','khmer'=>'Khmer'] as $language=>$text)
                                <span>{{ $text }}: <strong data-result-passed-language="{{ $language }}">0</strong> <small data-result-language-percentage="{{ $language }}">(—)</small></span>
                            @endforeach
                        </div>
                    @elseif($key==='failed')
                        <div class="g9-result-language-breakdown text-secondary" aria-label="Failed candidates by exam language">
                            @foreach(['english'=>'English','khmer'=>'Khmer'] as $language=>$text)
                                <span>{{ $text }}: <strong data-result-failed-language="{{ $language }}">0</strong> <small data-result-failed-language-percentage="{{ $language }}">(—)</small></span>
                            @endforeach
                        </div>
                    @elseif($key==='absent')
                        <div class="g9-result-language-breakdown text-secondary" aria-label="Absent candidates by exam language">
                            @foreach(['english'=>'English','khmer'=>'Khmer'] as $language=>$text)
                                <span>{{ $text }}: <strong data-result-absent-language="{{ $language }}">0</strong></span>
                            @endforeach
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
        <div class="text-secondary small mb-3" data-results-context role="status">Select an academic year to view exam results.</div>
        <section class="card" id="g9ResultPanel-results" role="tabpanel" aria-labelledby="g9ResultTab-results" data-results-panel="results">
            <div class="card-header g9-header flex-wrap gap-2"><h3 class="card-title"><i class="ti ti-list-details me-2" aria-hidden="true"></i>Exam Result</h3>
                <div class="d-flex flex-wrap gap-2 ms-auto g9-result-exports" aria-label="Export result reports">
                    @foreach(['print'=>['Print','ti-printer'],'excel'=>['Excel','ti-file-spreadsheet'],'pdf'=>['PDF','ti-file-type-pdf']] as $format=>[$label,$icon])
                        <div class="dropdown"><button type="button" class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" data-results-export-toggle disabled aria-expanded="false"><i class="ti {{ $icon }} me-1" aria-hidden="true"></i>{{ $label }}</button>
                            <div class="dropdown-menu dropdown-menu-end g9-report-menu" aria-label="{{ $label }} result reports">
                                <div class="g9-report-menu-heading"><span class="g9-report-format"><i class="ti {{ $icon }}" aria-hidden="true"></i>{{ $label }}</span><span>Choose a report</span></div>
                                @foreach(\App\Support\G9ResultReport::TITLES as $type=>$title)
                                    @if($type==='list'||$tabs['statistics'])
                                        @php([$reportIcon,$description]=match($type){
                                            'list'=>['ti-list-details','Student results by exam center and room'],
                                            'room'=>['ti-door','Attendance and letter grades by room'],
                                            'subject'=>['ti-books','Pass/fail totals for each subject'],
                                            'doeys'=>['ti-building-community','Exam center overview for DoEYS'],
                                            'branch'=>['ti-school','Class results by campus'],
                                            'wis'=>['ti-chart-bar','Campus and exam language overview'],
                                        })
                                        <button type="button" class="dropdown-item g9-report-option" data-results-export="{{ $type }}" data-format="{{ $format }}">
                                            <span class="g9-report-option-icon"><i class="ti {{ $reportIcon }}" aria-hidden="true"></i></span>
                                            <span class="g9-report-option-text"><span class="g9-report-option-title">{{ $title }}</span><small>{{ $description }}</small></span>
                                            <i class="ti ti-chevron-right g9-report-option-arrow" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                @endforeach
                                <div class="g9-report-menu-footer"><i class="ti ti-filter" aria-hidden="true"></i>Selected filters · All matching candidates</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="table-responsive"><table class="table table-vcenter g9-results-table mb-0"><thead><tr><th>No.</th><th>Photo</th><th>Student</th><th>Grade / Campus</th><th>Examination</th><th>EOY-Avg</th><th>Total Score</th><th>Result</th><th>Letter Grade</th></tr></thead><tbody data-results-rows><tr><td colspan="9" class="text-center text-secondary py-5">Select an academic year to view exam results.</td></tr></tbody></table></div>
            <div class="card-footer"><input type="hidden" id="g9-results-per-page" value="25"><div id="g9-results-pagination"></div></div>
        </section>
        @if($tabs['graph'])
        <section id="g9ResultPanel-graph" role="tabpanel" aria-labelledby="g9ResultTab-graph" data-results-panel="graph" hidden>
            <div class="g9-results-charts">
                <article class="card"><div class="card-header g9-header"><h3 class="card-title">Result Overview</h3></div><div class="card-body g9-result-overview"><div class="g9-result-donut" data-results-donut role="img" aria-label="No exam result data"><div><strong data-results-rate>—</strong><small>Pass rate</small></div></div><div data-results-legend class="g9-result-legend"></div></div></article>
                <article class="card"><div class="card-header g9-header flex-wrap gap-2"><h3 class="card-title">Result Comparison</h3><select class="form-select form-select-sm ms-auto g9-result-group" data-results-group aria-label="Compare results by"><option value="campuses">By campus</option><option value="centers">By exam center</option></select></div><div class="card-body"><p class="text-secondary small">Share of candidates in each outcome. Hover or focus a segment for details.</p><div class="g9-result-bars" data-results-bars></div></div></article>
            </div>
            <div class="card mt-3"><div class="card-body g9-result-grade-summary"><strong>Letter Grade Distribution</strong><div data-results-grades></div></div></div>
            <article class="card mt-3">
                <div class="card-header g9-header"><h3 class="card-title"><i class="ti ti-books me-2" aria-hidden="true"></i>Passed / Failed by Subject</h3></div>
                <div class="card-body"><p class="text-secondary small">Pass mark: 50% of each subject’s maximum score. Includes present candidates with an entered score; foreign language applies to Khmer examinations.</p><div class="g9-center-grade-grid" data-results-subjects aria-live="polite"></div></div>
            </article>
            <article class="card mt-3">
                <div class="card-header g9-header"><h3 class="card-title"><i class="ti ti-chart-bar me-2" aria-hidden="true"></i>Letter Grade by Exam Center</h3></div>
                <div class="card-body"><p class="text-secondary small">Count and percentage of candidates who attended each exam center. Absent students are excluded; pending results remain in the attendance total.</p><div class="g9-center-grade-grid" data-results-center-grades aria-live="polite"></div></div>
            </article>
        </section>
        @endif
        @if($tabs['statistics'])
        <section class="card" id="g9ResultPanel-statistics" role="tabpanel" aria-labelledby="g9ResultTab-statistics" data-results-panel="statistics" hidden>
            <div class="card-header g9-header flex-wrap gap-2"><h3 class="card-title">Passed/Failed Statistics</h3><select class="form-select form-select-sm ms-auto g9-result-group" data-results-stat-group aria-label="Group statistics by"><option value="campuses">By campus</option><option value="centers">By exam center</option></select></div>
            <div class="card-body py-2 text-secondary small">Female counts appear below each total. Pass rate = Passed ÷ (Passed + Failed). Pending and absent candidates are excluded from the pass rate.</div>
            <div class="table-responsive"><table class="table table-vcenter g9-results-table mb-0"><thead><tr><th>No.</th><th data-results-stat-heading>Campus</th><th>Candidates</th><th>Passed</th><th>Failed</th><th>Pending</th><th>Absent</th><th>Pass Rate</th></tr></thead><tbody data-results-statistics></tbody><tfoot data-results-stat-total></tfoot></table></div>
        </section>
        @endif
    </main>
    @include('g9-national-exams.photo-viewer')
</div>
@endsection
