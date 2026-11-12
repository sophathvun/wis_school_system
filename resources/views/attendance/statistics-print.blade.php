<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @unless($pdfExport ?? false)
        @vite(['resources/css/pages/attendance-record-print.css', 'resources/css/pages/attendance-statistics-print.css', 'resources/js/printPage.js'])
    @endunless
</head>
<body data-print-mode="1">
    <header class="report-header">
        @if($logo)<div class="school-logo-row"><img class="school-logo" src="{{ $logo }}" alt="School Logo 1"></div>@endif
        <h1>{{ $title }}</h1>
        <div class="statistics-subheaders">
            <p>{{ $dateRange }}</p>
            <p>Academic Year: {{ $academicYear }}</p>
            <p>Campus: {{ $campus }}</p>
            <p>Grade: {{ $selectedGrade }}</p>
        </div>
    </header>
    <table class="records statistics-records">
        <thead><tr><th>No.</th><th>Student Name Khmer</th><th>Student Name English</th><th>Student ID</th><th>Gender</th><th>Grade</th><th>Group</th><th>Total Tardy</th><th>Total Excused</th><th>Total Unexcused</th><th>Total Absent</th></tr></thead>
        <tbody>
            @forelse($data as $index => $student)
                @if($groupByGrade && ($index === 0 || $data[$index - 1]['class'] !== $student['class']))
                    <tr class="statistics-grade-header"><td colspan="11">Grade: {{ $student['class'] ?: '-' }}</td></tr>
                @endif
                <tr><td class="center">{{ $index + 1 }}</td><td class="khmer">{{ $student['name_kh'] }}</td><td>{{ $student['name_en'] }}</td><td class="center">{{ $student['student_code'] }}</td><td class="khmer center">{{ $student['gender'] ?: '-' }}</td><td class="center">{{ $student['class'] ?: '-' }}</td><td class="center">{{ $student['group'] ?: '-' }}</td><td class="center">{{ $student['T'] }}</td><td class="center">{{ $student['EX'] }}</td><td class="center">{{ $student['UX'] }}</td><td class="center"><strong>{{ $student['total_absent'] }}</strong></td></tr>
            @empty
                <tr><td colspan="11" class="center">No students found for the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="report-note">Total Tardy = T; Total Excused = E; Total Unexcused = U; Total Absent = E + U. Each recorded attendance day is counted once.</p>
</body>
</html>
