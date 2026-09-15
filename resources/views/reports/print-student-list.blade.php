@php
    $printFormat = $filters['print_format'] ?? 'internal';
    $reportDateFormatted = \Carbon\Carbon::parse($filters['report_date'] ?? now())->format('F j, Y');
    $classGroups = $enrollments->groupBy(function ($row) {
        return $row->grade_id . ':' . $row->class_id;
    });
@endphp

@forelse($classGroups as $classRows)
    @php
        $first = $classRows->first();
    @endphp
    <section class="a4-page">
        <header class="report-header">
            @if($branding?->report_logo_2_path)
                <img class="logo" src="{{ ($pdfMode ?? false) && !empty($pdfLogoSrc) ? $pdfLogoSrc : asset('storage/'.$branding->report_logo_2_path) }}" alt="School Logo 2">
            @endif
            <div class="motto">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ</div>
            <h2>{{ $first->campus?->school_name_kh ?: $first->campus?->campus_name_kh ?: '' }}</h2>
            @if($printFormat === 'moeys')
                <h1 class="khmer moeys-title">បញ្ជីឈ្មោះសិស្ស</h1>
                <p class="khmer moeys-academic-year">ឆ្នាំសិក្សា {{ $academicYear?->academic_year ?? '' }} | ថ្នាក់ {{ trim(($first->grade?->grade_short_name ?: $first->grade?->grade) . ($first->schoolClass?->class_name ?? '')) }}</p>
            @else
                <h1>Internal Student List</h1>
                <p>{{ $academicYear?->academic_year ?? 'All Academic Years' }} | {{ $first->campus?->campus_name_en }} | Grade {{ trim(($first->grade?->grade_short_name ?: $first->grade?->grade) . ($first->schoolClass?->class_name ?? '')) }}</p>
            @endif
            <p class="report-date">Date: {{ $reportDateFormatted }}</p>
            <p class="small">{{ $first->campus?->campus_name_en }} · {{ now('Asia/Phnom_Penh')->format('d M Y H:i') }}</p>
        </header>

        <table class="report-table student-list-print-table">
            <thead>
                <tr>
                    <th>{{ $printFormat === 'moeys' ? 'ល.រ' : 'No.' }}</th>
                    <th>{{ $printFormat === 'moeys' ? 'អត្តលេខសិស្ស' : 'Student ID' }}</th>
                    <th class="left">{{ $printFormat === 'moeys' ? 'នាមត្រកូល-នាមខ្លួន' : 'Full-Name' }}</th>
                    @if($printFormat !== 'moeys')
                        <th class="left khmer-name-header">នាមត្រកូល និងនាមខ្លួន</th>
                    @endif
                    <th>{{ $printFormat === 'moeys' ? 'ភេទ' : 'Gender' }}</th>
                    <th>{{ $printFormat === 'moeys' ? 'ថ្នាក់ទី' : 'Grade' }}</th>
                    <th>{{ $printFormat === 'moeys' ? 'ក្រុម' : 'Group' }}</th>
                    <th>{{ $printFormat === 'moeys' ? 'ផ្សេងៗ' : 'Remarks' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($classRows as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $row->student?->student_id ?? '-' }}</td>
                        @if($printFormat === 'moeys')
                            <td class="left khmer-name">{{ $row->student?->full_name_kh ?: '-' }}</td>
                        @else
                            <td class="left english-name">{{ $row->student?->full_name_en ?: '-' }}</td>
                            <td class="left khmer-name">{{ $row->student?->full_name_kh ?: '-' }}</td>
                        @endif
                        @if($printFormat === 'moeys')
                            <td class="khmer">{{ strtoupper(substr((string) $row->student?->gender, 0, 1)) === 'F' ? 'ស្រី' : 'ប្រុស' }}</td>
                        @else
                            <td>{{ strtoupper(substr((string) $row->student?->gender, 0, 1)) === 'F' ? 'F' : 'M' }}</td>
                        @endif
                        <td>{{ trim(($row->grade?->grade_short_name ?: $row->grade?->grade) . ($row->schoolClass?->class_name ?? '')) ?: '-' }}</td>
                        <td>{{ $row->session?->session_short_name ?: '-' }}</td>
                        <td></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($printFormat === 'moeys' && ($pdfMode ?? false))
            <div class="moeys-date-footer"><div>{{ $pdfKhmerLunarDate ?? '' }}</div><div>{{ $pdfKhmerSolarDate ?? '' }}</div><div>នាយកសាលា</div></div>
        @elseif($printFormat !== 'moeys')
            <div class="student-list-date-footer">{{ $reportDateFormatted }}</div>
        @endif
    </section>
@empty
    <section class="a4-page">
        <header class="report-header">
            <div class="motto">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ</div>
            <h1>{{ $printFormat === 'moeys' ? 'បញ្ជីឈ្មោះសិស្ស' : 'Internal Student List' }}</h1>
        </header>
        <div class="empty">No students found.</div>
    </section>
@endforelse