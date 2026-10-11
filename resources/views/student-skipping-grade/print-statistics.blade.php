<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Statistics of Students Skipping Grades</title>
@if($pdfExport)@include('student-skipping-grade._report-pdf-style')@else
@vite(['resources/css/pages/student-skipping-grade-report.css','resources/js/studentSkippingGradePrint.js'])@endif
</head><body class="skipping-report-print skipping-statistics-print" data-statistics-filename="grade-skipping-statistics-{{ $reportDate->format('Ymd') }}.png">
@unless($pdfExport)
    @include('student-skipping-grade._report-toolbar')
    <form method="get" class="skipping-statistics-controls">
        @foreach($exportFilters as $key=>$value)
            @unless(in_array($key,['academic_year_id','campus_id'],true))
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endunless
        @endforeach
        <div class="skipping-report-control"><label for="statisticsYear">Academic Year</label><select id="statisticsYear" name="academic_year_id"><option value="">All Years</option>@foreach($academicYears as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year_id']??'')==$year->id)>{{ $year->academic_year }}</option>@endforeach</select></div>
        <div class="skipping-report-control"><label for="statisticsCampus">Campus</label><select id="statisticsCampus" name="campus_id"><option value="">All Campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected(($filters['campus_id']??'')==$campus->id)>{{ $campus->campus_name_en }}</option>@endforeach</select></div>
        <button type="submit">Update Preview</button>
    </form>
@endunless
<main class="skipping-report-paper skipping-statistics-paper">
    @if($reportLogo)<div class="skipping-report-branding"><img src="{{ $reportLogo }}" alt="School Logo 1" class="skipping-report-logo"></div>@endif
    <header class="skipping-statistics-header">
        <h1>Statistics of Students Skipping Grades</h1>
        <p>{{ $statisticsYearSubtitle }}</p>
        <p class="skipping-statistics-filter-summary">Campus: {{ $reportCampus?->campus_name_en??'All Campuses' }} | Status: Approved @if(filled($filters['grade_class']??null)) | Current Grade: {{ $gradeOptions->firstWhere('value',$filters['grade_class'])['label']??'—' }}@endif @if(filled($filters['approval_number']??null)) | Approved Number: {{ $filters['approval_number'] }}@endif @if(filled($filters['search']??null)) | Search: {{ $filters['search'] }}@endif</p>
    </header>
    @include('student-skipping-grade._statistics-table')
    <table class="skipping-statistics-footer">
        <colgroup><col style="width:28%"><col style="width:4mm"><col></colgroup>
        <thead><tr><th class="skipping-statistics-footer-heading skipping-statistics-office">Registrar's Office</th><th class="skipping-statistics-footer-gap" aria-hidden="true"></th><th class="skipping-statistics-footer-heading skipping-statistics-notes">Notes</th></tr></thead>
        <tbody><tr><td class="skipping-statistics-office"><div class="skipping-statistics-office-date">{{ $reportDate->format('F j, Y') }}</div></td>
            <td class="skipping-statistics-footer-gap" aria-hidden="true"></td>
            <td class="skipping-statistics-notes">
                @if($statistics['notes']->isNotEmpty())
                <table class="skipping-statistics-note-table"><tr>
                    @foreach($statistics['notes']->chunk(3) as $notes)
                        <td>@foreach($notes as $note)<div>{{ $note['source'] }} skipped to {{ $note['target'] }}</div>@endforeach</td>
                    @endforeach
                </tr></table>
                @endif
                <p>Stu. = total students; F = female students. Each student is counted once per academic year, using the latest matching request.</p>
            </td>
        </tr></tbody>
    </table>
</main></body></html>
