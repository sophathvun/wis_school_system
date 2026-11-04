<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Absent Record</title>
    @unless($pdfExport ?? false)
        @vite(['resources/css/pages/attendance-record-print.css', 'resources/js/printPage.js'])
    @endunless
</head>
<body data-print-mode="1">
    <header class="report-header">
        @if($logo)<div class="school-logo-row"><img class="school-logo" src="{{ $logo }}" alt="School Logo 1"></div>@endif
        <h1>Student Absent Record</h1>
        <p>{{ $student['academic_year'] }} · {{ $dateRange }}</p>
    </header>
    <table class="student-information">
        <tr><td><strong>Academic Year:</strong> {{ $student['academic_year'] }}</td><td class="khmer"><strong>Gender:</strong> {{ $student['gender'] ?: '-' }}</td></tr>
        <tr><td><strong>Student ID:</strong> {{ $student['student_code'] }}</td><td><strong>Grade:</strong> {{ $student['class'] }}</td></tr>
        <tr><td class="khmer"><strong>Student Name:</strong> {{ $student['name_kh'] }}</td><td><strong>Group:</strong> {{ $student['group'] ?: '-' }}</td></tr>
        <tr><td>{{ $student['name_en'] }}</td><td><strong>Campus:</strong> {{ $student['campus'] }}</td></tr>
    </table>
    <table class="totals"><tr>
        @foreach(['absent' => 'Total Absent', 'EX' => 'Total E', 'UX' => 'Total U', 'L' => 'Total L'] as $status => $label)
            <td class="status-{{ $status === 'absent' ? 'p' : strtolower($status) }}">{{ $label }}: <strong>{{ $status === 'absent' ? $totals['EX'] + $totals['UX'] : $totals[$status] }}</strong></td>
        @endforeach
    </tr></table>
    <table class="records">
        <thead><tr><th class="number">No.</th><th class="date">Date</th><th class="status">Attendance Status</th><th>Reason</th></tr></thead>
        <tbody>
            @forelse($data as $index => $record)
                <tr><td class="center">{{ $index + 1 }}</td><td class="center">{{ \Carbon\CarbonImmutable::parse($record['date'])->format('d-M-Y') }}</td><td class="center">{{ ['EX' => 'E', 'UX' => 'U'][$record['status']] ?? $record['status'] }}</td><td class="khmer">{{ $record['reason'] ?: '-' }}</td></tr>
            @empty
                <tr><td colspan="4" class="center">No absent or late records found for the selected date range.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="report-note">Only absent and late records are listed. Totals include all recorded attendance in the selected date range.</p>
</body>
</html>
