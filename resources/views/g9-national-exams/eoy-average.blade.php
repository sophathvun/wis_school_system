@extends('layouts.app')
@section('title','Assign EOY Average')
@section('page-header','')
@section('content')
@vite(['resources/css/pages/g9-national-exams.css','resources/js/g9EoyAverage.js'])
<div data-g9-eoy data-base="{{ url('/g9-national-exams/eoy-average') }}">
    <section class="card mb-3">
        <div class="card-header g9-header"><h3 class="card-title"><i class="ti ti-filter me-2"></i>Data Filters</h3><button type="button" class="btn btn-sm btn-outline-light ms-auto" data-eoy-filter-toggle aria-expanded="true" aria-controls="g9EoyFilters" aria-label="Collapse filters"><i class="ti ti-chevron-up"></i></button></div>
        <form id="g9EoyFilters" class="card-body g9-candidate-filters g9-eoy-filters">
            @foreach(['academic_year'=>'Academic Year','campus'=>'Campus','grade'=>'Grade','student'=>'Student Name'] as $name=>$label)
                <div class="premium-form-field staff-form-field"><label class="form-label" for="g9Eoy-{{ $name }}">{{ $label }}</label>
                    <select class="form-select" name="{{ $name }}" id="g9Eoy-{{ $name }}" aria-label="{{ $label }}"><option value="">{{ $name === 'academic_year' ? 'Select academic year' : 'All '.strtolower($label) }}</option>
                    @if($name==='academic_year')@foreach($years as $year)<option value="{{ $year->id }}">{{ $year->academic_year }}</option>@endforeach @endif
                    </select>
                </div>
            @endforeach
        </form>
    </section>
    <section class="card g9-eoy-list">
        <div class="card-header g9-header flex-wrap gap-2"><h3 class="card-title"><i class="ti ti-calculator me-2"></i>List of Candidates</h3><span class="ms-auto g9-eoy-formula">EOY Average = SEM1 + SEM2 · Rounded to a whole number</span>
            @if(count($reportTypes))@foreach(['print'=>['Print','ti-printer'],'pdf'=>['PDF','ti-file-type-pdf']] as $format=>[$label,$icon])
                @if(in_array($format,$reportFormats,true))
                <div class="dropdown"><button type="button" class="btn btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="ti {{ $icon }} me-1" aria-hidden="true"></i>{{ $label }}</button>
                    <div class="dropdown-menu dropdown-menu-end g9-report-menu" aria-label="{{ $label }} annual score reports">
                        <div class="g9-report-menu-heading"><span class="g9-report-format"><i class="ti {{ $icon }}" aria-hidden="true"></i>{{ $label }}</span><span>Choose a report</span></div>
                        @foreach(\App\Support\G9EoyReport::TITLES as $report=>$title)
                            @continue(!in_array($report,$reportTypes,true))
                            <button type="button" class="dropdown-item g9-report-option" data-eoy-report="{{ $report }}" data-format="{{ $format }}">
                                <span class="g9-report-option-icon"><i class="ti {{ $report==='cover'?'ti-book-2':'ti-report-analytics' }}" aria-hidden="true"></i></span>
                                <span class="g9-report-option-text"><span class="g9-report-option-title">{{ $title }}</span><small>{{ $report==='cover'?'Annual score card cover by exam center and room':'Student annual score card' }} · 2 cards per A4 sheet</small></span>
                                <i class="ti ti-chevron-right g9-report-option-arrow" aria-hidden="true"></i>
                            </button>
                        @endforeach
                        <div class="g9-report-menu-footer"><i class="ti ti-filter" aria-hidden="true"></i>Uses your selected filters · All matching students</div>
                    </div>
                </div>
                @endif
            @endforeach @endif
            <button type="button" class="btn g9-eoy-edit" data-eoy-edit data-action-normalized="true" aria-pressed="false" hidden><i class="ti ti-pencil" aria-hidden="true"></i><span data-eoy-edit-label>Edit</span></button>
        </div>
        <div class="card-body py-2"><span class="text-secondary small">Click Edit to unlock all permitted students' scores on this page. Changes save automatically. Click Lock to finish and lock the scores.</span><div class="text-danger small mt-1" data-eoy-error role="alert"></div><div class="text-success small" data-eoy-status role="status"></div></div>
        <div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>No.</th><th>Photo</th><th>Student Name</th><th>Examination</th><th>Score SEM1</th><th>Score SEM2</th><th>EOY Average</th><th>Save Status</th></tr></thead><tbody data-eoy-rows></tbody></table></div>
        <div class="card-footer"><input type="hidden" id="g9-eoy-per-page" value="15"><div id="g9-eoy-pagination"></div></div>
    </section>
    @include('g9-national-exams.photo-viewer')
</div>
@endsection
