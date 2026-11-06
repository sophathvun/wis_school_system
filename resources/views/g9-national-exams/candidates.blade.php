@extends('layouts.app')
@section('title', 'G9 National Exams — List of Candidate')
@section('page-header', '')
@section('content')
    @vite(['resources/css/pages/g9-national-exams.css', 'resources/js/g9Candidates.js'])
    <div data-g9-candidates data-base="{{ url('/g9-national-exams') }}" data-create-campuses="{{ json_encode($createCampuses) }}" data-edit-campuses="{{ json_encode($editCampuses) }}">
        <section class="card mb-3">
            <div class="card-header g9-header">
                <h3 class="card-title"><i class="ti ti-filter me-2" aria-hidden="true"></i>Datas Filter</h3>
                <button type="button" class="btn btn-sm btn-outline-light ms-auto" data-g9-filter-toggle aria-controls="g9CandidateFilters" aria-expanded="true" aria-label="Collapse filters"><i class="ti ti-chevron-up" aria-hidden="true"></i></button>
            </div>
            <form class="card-body g9-candidate-filters" id="g9CandidateFilters" data-g9-filters>
                @foreach(['academic_year' => 'Academic Year', 'exam_center' => 'Exam Center', 'campus' => 'Campus', 'room' => 'Room No.', 'seat' => 'Seat No.', 'student' => 'Student Name'] as $name => $label)
                    <div class="premium-form-field staff-form-field">
                        <label class="form-label" for="g9-{{ $name }}">{{ $label }}</label>
                        <select class="form-select" id="g9-{{ $name }}" name="{{ $name }}" aria-label="{{ $label }}">
                            <option value="">{{ $name === 'academic_year' ? 'Select academic year' : 'All '.strtolower($label) }}</option>
                            @if($name === 'academic_year')
                                @foreach($filterYears as $year)<option value="{{ $year->id }}">{{ $year->academic_year }}</option>@endforeach
                            @endif
                        </select>
                    </div>
                @endforeach
            </form>
        </section>
        <section class="mb-3 g9-candidate-summary" data-g9-summary hidden aria-label="Candidate summary">
            <div class="g9-summary-grid">
                <div class="card card-body g9-summary-tile g9-summary-blue"><h3 class="g9-summary-title">បេក្ខជនសរុប</h3><strong class="g9-summary-total" data-g9-total>0</strong><div class="g9-summary-genders"><span data-g9-male></span><span data-g9-female></span></div></div>
                <div class="g9-language-totals" data-g9-languages></div>
                <div class="card card-body g9-summary-tile g9-campus-tile g9-summary-purple"><h3 class="g9-summary-title">មកពីសាខា</h3><div class="g9-summary-campuses" data-g9-campuses></div></div>
            </div>
        </section>
        <section class="card g9-candidate-list">
            <div class="card-header g9-header flex-wrap gap-2"><h3 class="card-title"><i class="ti ti-users me-2" aria-hidden="true"></i>Candidate List</h3>
                <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                    <div class="g9-header-search">
                        <label class="visually-hidden" for="g9CandidateSearch">Search candidates</label>
                        <input type="search" class="form-control" name="search" form="g9CandidateFilters" id="g9CandidateSearch" placeholder="Search candidates…" autocomplete="off" data-search-normalized="true">
                        <i class="ti ti-search" aria-hidden="true"></i>
                    </div>
                    @if(count($reportFormats) && count($reportTypes))
                        @foreach(['print'=>['Print','ti-printer'],'excel'=>['Excel','ti-file-spreadsheet'],'pdf'=>['PDF','ti-file-type-pdf']] as $format=>[$label,$icon])
                            @continue(!in_array($format,$reportFormats,true))
                            @continue($format==='excel' && !count(array_diff($reportTypes,\App\Support\G9CandidateReport::PRINT_ONLY_TYPES)))
                            <div class="dropdown"><button type="button" class="btn btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="ti {{ $icon }} me-1"></i>{{ $label }}</button>
                                <div class="dropdown-menu dropdown-menu-end g9-report-menu" aria-label="{{ $label }} reports">
                                    <div class="g9-report-menu-heading"><span class="g9-report-format"><i class="ti {{ $icon }}" aria-hidden="true"></i>{{ $label }}</span><span>Choose a report</span></div>
                                    @foreach(\App\Support\G9CandidateReport::TITLES as $report=>$title)
                                        @continue(!in_array($report,$reportTypes,true))
                                        @continue(in_array($report,\App\Support\G9CandidateReport::PRINT_ONLY_TYPES) && $format === 'excel')
                                        @php([$reportIcon,$description] = match($report) {
                                            'cover'=>['ti-book-2','Room attendance cover'],
                                            'attendance-1'=>['ti-clipboard-list','Subject attendance · Page 1'],
                                            'attendance-2'=>['ti-clipboard-check','Subject attendance · Page 2'],
                                            'profile'=>['ti-id','Student card · 2 per A4 sheet'],
                                            'profile-cover'=>['ti-book-2','Student card cover by room'],
                                            'seat-stickers'=>['ti-tags','Seat labels · 18 per A4 sheet'],
                                            'room-seat-plan'=>['ti-layout-grid','Room seating · 25 seats per page'],
                                            default=>['ti-users','Candidates by exam center and room'],
                                        })
                                        <button type="button" class="dropdown-item g9-report-option" data-g9-report="{{ $report }}" data-format="{{ $format }}">
                                            <span class="g9-report-option-icon"><i class="ti {{ $reportIcon }}" aria-hidden="true"></i></span>
                                            <span class="g9-report-option-text"><span class="g9-report-option-title">{{ $title }}</span><small>{{ $description }}</small></span>
                                            <i class="ti ti-chevron-right g9-report-option-arrow" aria-hidden="true"></i>
                                        </button>
                                    @endforeach
                                    <div class="g9-report-menu-footer"><i class="ti ti-filter" aria-hidden="true"></i>Uses your selected filters</div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                    @if(count($createCampuses))<button type="button" class="btn g9-add-candidate" data-g9-add><i class="ti ti-user-plus me-1" aria-hidden="true"></i>Add Candidate</button>@endif
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter mb-0">
                    <thead><tr><th>ល.រ</th><th>រូបថត</th><th>នាមត្រកូល និងនាមខ្លួន</th><th>ព័ត៌មានអាណាព្យាបាល</th><th>មណ្ឌលប្រឡង</th><th>មកពីសាលា</th><th>Action</th></tr></thead>
                    <tbody data-g9-rows></tbody>
                </table>
            </div>
            <div class="card-footer"><input type="hidden" id="g9-per-page" value="15"><div id="g9-pagination"></div><div class="text-danger small" role="status" data-g9-error></div></div>
        </section>
    </div>
    @include('g9-national-exams.photo-viewer')
    <div class="modal fade" id="g9CandidateModal" tabindex="-1" aria-labelledby="g9CandidateModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content" data-g9-editor enctype="multipart/form-data" novalidate>
                <div class="modal-header g9-header"><h3 class="modal-title" id="g9CandidateModalTitle">Add Candidate</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="version">
                    <div class="alert alert-danger" data-g9-editor-error role="alert" hidden></div>
                    @foreach($fields as $section => $items)
                        <fieldset class="g9-editor-section"><legend>{{ $section }}</legend><div class="g9-editor-grid">
                            @foreach($items as $name => $label)
                                @php($required = in_array($name, ['academic_year_id','campus_id','student_code','name_en']))
                                <div class="premium-form-field {{ in_array($name,['academic_year_id','campus_id','gender','exam_language']) ? 'staff-form-field' : '' }}">
                                    <label class="form-label" for="g9Field-{{ $name }}">{{ $label }}{{ $required ? ' *' : '' }}</label>
                                    @if(in_array($name,['academic_year_id','campus_id','gender','exam_language']))
                                        <select class="form-select" id="g9Field-{{ $name }}" name="{{ $name }}" @required($required)>
                                            <option value="">Select {{ strtolower($label) }}</option>
                                            @if($name === 'academic_year_id')
                                                @foreach($years as $year)<option value="{{ $year->id }}">{{ $year->academic_year }}</option>@endforeach
                                            @elseif($name === 'campus_id')
                                                @foreach($campuses as $campus)<option value="{{ $campus->id }}">{{ $campus->campus_name_en }}</option>@endforeach
                                            @else
                                                @foreach($name === 'gender' ? ['Male','Female'] : ['Khmer','English'] as $option)<option>{{ $option }}</option>@endforeach
                                            @endif
                                        </select>
                                    @else
                                        @php($numeric = str_starts_with($name, 'score_') || in_array($name,['semester_1','semester_2','eoy_average']))
                                        <input class="form-control" id="g9Field-{{ $name }}" name="{{ $name }}" type="{{ $numeric ? 'number' : (in_array($name,['date_of_birth','exam_date']) ? 'date' : ($name === 'phone_number' ? 'tel' : 'text')) }}" placeholder=" " @required($required) @if($numeric) min="0" max="{{ $name === 'eoy_average' ? '20000' : (in_array($name,['semester_1','semester_2']) ? '10000' : '9999.999') }}" step="0.001" @elseif(!in_array($name,['date_of_birth','exam_date'])) maxlength="{{ $name === 'student_code' ? 80 : 255 }}" @endif aria-label="{{ $label }}">
                                    @endif
                                </div>
                            @endforeach
                        </div></fieldset>
                    @endforeach
                    <fieldset class="g9-editor-section"><legend>Student Info Address Link</legend>
                        <div class="premium-form-field staff-form-field"><label class="form-label" for="g9StudentInfoLink">Student Info Record</label><select class="form-select" name="sis_student_id" id="g9StudentInfoLink"><option value="">Automatic match by exact Student ID</option></select></div>
                        <p class="form-hint mt-2">The card reads the latest Khmer address from Student Info. Select a student here only if their SIS and MoEYS IDs differ. Only students enrolled in this year and campus are listed; ambiguous or unmatched automatic links stay blank.</p>
                    </fieldset>
                    <fieldset class="g9-editor-section"><legend>Student Photo</legend><label class="form-label" for="g9StudentPhoto">Student Photo</label><input type="file" id="g9StudentPhoto" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp"><p class="form-hint mt-2">JPG, PNG or WebP, up to 5 MB. Optimized to WebP while preserving the photo ratio and organized by academic year.</p></fieldset>
                </div>
                <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" data-g9-save>Save Candidate</button></div>
            </form>
        </div>
    </div>
@endsection
