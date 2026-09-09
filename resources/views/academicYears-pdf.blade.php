<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Academic Year List</title>

    <style>
        {!! file_get_contents(resource_path('css/pages/academicyears-pdf.css')) !!}
    </style>
</head>

<body data-pdf-print-mode="{{ ($printMode ?? false) ? '1' : '0' }}" data-pdf-redirect-url="{{ route('academic-years.index') }}">
    <div class="header">
        <div class="school-logo-row">
            @if ($logoSrc)
                <img src="{{ $logoSrc }}" alt="School Logo 1" class="school-logo">
            @endif
        </div>
        <h3 class="khmer-title text-center" lang="km">តារាងឆ្នាំសិក្សា</h3>
        <h3 class="text-center english-title">Academic Year List</h3>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>No.</th>
                <th>Academic Year</th>
                <th>Type</th>
                <th>AY Code</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($academicYears as $index => $year)
                @php($status = strtolower($year->lifecycle_status ?? ($year->status ? 'started' : 'finished')))
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $year->academic_year }}</td>
                    <td>{{ $year->isSummer() ? 'Summer School' : 'Regular' }}</td>
                    <td>{{ $year->ay_code }}</td>
                    <td>{{ $year->start_date?->format('Y-m-d') ?: '' }}</td>
                    <td>{{ $year->end_date?->format('Y-m-d') ?: '' }}</td>
                    <td><span class="status-badge status-{{ $status }}">{{ ucfirst($status) }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p>Generated: {{ now()->format('F j, Y H:i') }}</p>
    @vite('resources/js/pdfPrint.js')
</body>

</html>
