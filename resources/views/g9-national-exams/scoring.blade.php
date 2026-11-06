@extends('layouts.app')
@section('title','G9 National Exams — Scoring')
@section('page-header','')
@section('content')
@vite(['resources/css/pages/g9-national-exams.css','resources/css/pages/g9-scoring.css','resources/js/g9Scoring.js'])
<div data-g9-scoring data-base="{{ url('/g9-national-exams/scoring') }}" data-subjects="{{ json_encode($subjects) }}" data-subject-maxima="{{ json_encode(collect(\App\Support\G9ScoringReport::SUBJECTS)->map(fn($subject) => (float)$subject[1])) }}">
    <section class="card mb-3">
        <div class="card-header g9-header"><h3 class="card-title"><i class="ti ti-filter me-2" aria-hidden="true"></i>Data Filters</h3><button type="button" class="btn btn-sm btn-outline-light ms-auto" data-scoring-filter-toggle aria-expanded="true" aria-controls="g9ScoringFilters" aria-label="Collapse filters"><i class="ti ti-chevron-up" aria-hidden="true"></i></button></div>
        <form id="g9ScoringFilters" class="card-body g9-candidate-filters">
            @foreach(['academic_year'=>'Academic Year','exam_center'=>'Exam Center','campus'=>'Campus','room'=>'Room No.','grade'=>'Grade','student'=>'Student Name'] as $name=>$label)
            <div class="premium-form-field staff-form-field"><label class="form-label" for="g9Scoring-{{ $name }}">{{ $label }}</label>
                <select class="form-select" name="{{ $name }}" id="g9Scoring-{{ $name }}" aria-label="{{ $label }}"><option value="">{{ $name==='academic_year'?'Select academic year':'All '.strtolower($label) }}</option>
                    @if($name==='academic_year')@foreach($years as $year)<option value="{{ $year->id }}">{{ $year->academic_year }}</option>@endforeach @endif
                </select>
            </div>
            @endforeach
        </form>
    </section>
    <section class="card g9-scoring-list">
        <div class="card-header g9-header flex-wrap gap-2"><h3 class="card-title"><i class="ti ti-pencil me-2" aria-hidden="true"></i>Candidate Scores</h3>
            <span class="ms-auto small" data-scoring-mode><i class="ti ti-lock me-1" aria-hidden="true"></i>Scores locked</span>
            @foreach(['print'=>['Print','ti-printer'],'pdf'=>['PDF','ti-file-type-pdf']] as $format=>[$label,$icon])
                @if(in_array($format,$reportFormats,true))
                <div class="dropdown"><button type="button" class="btn btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="ti {{ $icon }} me-1" aria-hidden="true"></i>{{ $label }}</button>
                    <div class="dropdown-menu dropdown-menu-end g9-report-menu" aria-label="{{ $label }} scoring reports">
                        <div class="g9-report-menu-heading"><span class="g9-report-format"><i class="ti {{ $icon }}" aria-hidden="true"></i>{{ $label }}</span><span>Choose a report</span></div>
                        <button type="button" class="dropdown-item g9-report-option" data-scoring-report="{{ $format }}">
                            <span class="g9-report-option-icon"><i class="ti ti-report-analytics" aria-hidden="true"></i></span>
                            <span class="g9-report-option-text"><span class="g9-report-option-title">តារាងផ្ទៀងផ្ទាត់ពិន្ទុ</span><small>Score verification by exam center and room · A4 landscape</small></span>
                            <i class="ti ti-chevron-right g9-report-option-arrow" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="dropdown-item g9-report-option" data-scoring-report="{{ $format }}" data-scoring-report-type="scores">
                            <span class="g9-report-option-icon"><i class="ti ti-table" aria-hidden="true"></i></span>
                            <span class="g9-report-option-text"><span class="g9-report-option-title">តារាងស្រង់ពិន្ទុ</span><small>Score collection sheet with absence remarks · A4 landscape</small></span>
                            <i class="ti ti-chevron-right g9-report-option-arrow" aria-hidden="true"></i>
                        </button>
                        <div class="g9-report-menu-footer"><i class="ti ti-filter" aria-hidden="true"></i>Selected filters · All matching candidates</div>
                    </div>
                </div>
                @endif
            @endforeach
            <form class="g9-scoring-entry" data-scoring-entry>
                <div class="premium-form-field staff-form-field"><label class="form-label" for="g9ScoringSubject">Subject to Enter</label>
                    <select class="form-select" id="g9ScoringSubject" name="subject" data-scoring-entry-subject aria-label="Subject to Enter"><option value="">Select subject</option>
                        @foreach($subjects as $field=>$label)<option value="{{ $field }}">{{ str_replace('Exam Score ','',$label) }}</option>@endforeach
                    </select>
                </div>
            </form>
            <button type="button" class="btn g9-eoy-edit" data-scoring-edit data-action-normalized="true" aria-pressed="false" hidden><i class="ti ti-pencil" aria-hidden="true"></i><span data-scoring-edit-label>Edit</span></button>
        </div>
        <div class="card-body py-2">
            <span class="text-secondary small">Select a subject and click Edit. Tab moves down to the next student; Shift+Tab moves up. Scores save automatically. Blank means not recorded; enter 0 for a zero score.</span>
            <div class="text-secondary small">Tick Absent only for absent candidates. Untick to clear. Changes save automatically while scores are locked.</div>
            <div class="text-danger small mt-1" data-scoring-error role="alert"></div><div class="text-success small" data-scoring-status role="status"></div>
        </div>
        <div class="table-responsive g9-scoring-scroll" tabindex="0" role="region" aria-label="Candidate exam scores; scroll horizontally to view all subjects">
            <table class="table table-vcenter mb-0 g9-scoring-table">
                <colgroup><col class="g9-scoring-number"><col class="g9-scoring-photo"><col class="g9-scoring-student"><col class="g9-scoring-examination"><col class="g9-scoring-summary-column">
                    @foreach($subjects as $field=>$label)<col class="g9-scoring-subject">@endforeach
                    <col class="g9-scoring-total-column"><col class="g9-scoring-attendance-column"><col class="g9-scoring-save">
                </colgroup>
                <thead><tr><th scope="col">No.</th><th scope="col">Photo</th><th scope="col">Student</th><th scope="col">Examination</th><th scope="col" class="g9-scoring-max-header"><span class="g9-scoring-subject-label">ពិន្ទុប្រចាំឆ្នាំ</span><span class="g9-scoring-subject-max" aria-label="Maximum annual score 100">Max: 100</span></th>
                @php($subjectHeaders=[
                    'score_khmer_essay'=>'តែងសេចក្តី','score_chemistry'=>'គីមីវិទ្យា',
                    'score_earth_science'=>'ផែនដី-បរិស្ថាន','score_physics'=>'រូបវិទ្យា',
                    'score_geography'=>'ភូមិវិទ្យា','score_values'=>'សីលធម៌-ពលរដ្ឋ',
                    'score_math'=>'គណិតវិទ្យា','score_khmer_dictation'=>'សរសេរតាមអាន',
                    'score_biology'=>'ជីវវិទ្យា','score_history'=>'ប្រវត្តិវិទ្យា','score_english'=>'ភាសាបរទេស',
                ])
                @foreach($subjects as $field=>$label)
                    <th scope="col" class="g9-scoring-max-header" data-scoring-subject="{{ $field }}">
                        <span class="g9-scoring-subject-label">{{ $subjectHeaders[$field] }}</span>
                        <span class="g9-scoring-subject-max" aria-label="Maximum score {{ \App\Support\G9ScoringReport::SUBJECTS[$field][1] }}">Max: {{ \App\Support\G9ScoringReport::SUBJECTS[$field][1] }}</span>
                    </th>
                @endforeach
                <th scope="col">Total Score</th><th scope="col">Attendance</th><th scope="col">Save Status</th>
            </tr></thead><tbody data-scoring-rows></tbody></table>
        </div>
        <div class="card-footer"><input type="hidden" id="g9-scoring-per-page" value="25"><div id="g9-scoring-pagination"></div></div>
    </section>
    @include('g9-national-exams.photo-viewer')
</div>
@endsection
