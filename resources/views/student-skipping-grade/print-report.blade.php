<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>List of Grade Skipping Requests · {{ $reportYear?->academic_year??'All Years' }}</title>
@if($pdfExport)@include('student-skipping-grade._report-pdf-style')@else
@vite(['resources/css/pages/student-skipping-grade-report.css','resources/js/studentSkippingGradePrint.js'])@endif
</head><body class="skipping-report-print">
@unless($pdfExport)@include('student-skipping-grade._report-toolbar')@endunless
<main class="skipping-report-paper">
    @if($reportLogo)<div class="skipping-report-branding"><img src="{{ $reportLogo }}" alt="School Logo 1" class="skipping-report-logo"></div>@endif
    @php($reportStatusLabels=['draft'=>'Draft','pending'=>'Submitted','approved'=>'Approved','rejected'=>'Rejected'])
    <header class="skipping-report-title"><h1>List of Grade Skipping Requests</h1>
    @forelse($reportYearPairs as $years)<p>From Academic Year {{ $years['from'] }} to Academic Year {{ $years['to'] }}</p>
    @empty<p>Academic Year: {{ $reportYear?->academic_year??'All Years' }}</p>@endforelse
    <p>Campus: {{ $reportCampus?->campus_name_en??'All Campuses' }}</p>
    <p>Status: {{ $reportStatusLabels[$filters['status']??'']??'All Statuses' }}</p>
    @if(filled($filters['grade_class']??null))<p>Current Grade: {{ $gradeOptions->firstWhere('value',$filters['grade_class'])['label']??'—' }}</p>@endif
    @if(filled($filters['approval_number']??null))<p>Approved Number: {{ $filters['approval_number'] }}</p>@endif
    @if(filled($filters['search']??null))<p>Search: {{ $filters['search'] }}</p>@endif
    <p>Total Student: {{ $records->count() }}@if(blank($filters['status']??null) && filled($reportStatusSummary)) ( {{ $reportStatusSummary }} )@endif</p></header>
    @include('student-skipping-grade._report-table',['printReport'=>true])
    <footer class="skipping-report-footer">
        <div class="skipping-report-office">Registrar's Office</div>
        <div class="skipping-report-date">{{ $reportDate->format('F j, Y') }}</div>
    </footer>
</main></body></html>
