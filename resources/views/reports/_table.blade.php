@if($type === 'score-list')
    @include('reports._score-list-table')
@else
@if($type === 'student-statistics-detail')
@php
    $detailGroups = $statistics['groups'] ?? [];
    $detailValue = static fn ($value) => (int) $value === 0 ? '' : number_format((int) $value);
    $detailTotalValue = static fn ($value) => number_format((int) $value);
    $detailBranding = \App\Models\BrandingSetting::current();
    $detailLogoPath = $detailBranding?->report_logo_1_path ?: $detailBranding?->report_logo_2_path;
    $detailLogoUrl = $detailLogoPath ? asset('storage/' . ltrim($detailLogoPath, '/')) : asset('storage/school_logo/wis_logo.png');
    $detailAcademicYear = $academicYears->firstWhere('id', (int) ($filters['academic_year_id'] ?? 0))?->academic_year ?: 'All Academic Years';
    $detailReportMonth = $filters['month'] ?? now('Asia/Phnom_Penh')->format('Y-m');
    $detailReportDate = \Carbon\Carbon::createFromFormat('Y-m-d', $detailReportMonth . '-01')->endOfMonth();
    $detailColumnCount = 9 + (count($detailGroups) * 2);
    $detailTotalLabelColspan = 2;
@endphp
<div class="statistics-detail-preview-heading">
    <div class="statistics-detail-logo-wrap"><img src="{{ $detailLogoUrl }}" alt="School Logo"></div>
    <div class="statistics-detail-title-wrap">
        <div class="statistics-detail-title">Student Statistics for {{ $detailReportDate->format('F Y') }}</div>
        <div class="statistics-detail-subtitle">Academic Year: {{ $detailAcademicYear }}</div>
    </div>
</div>
<table class="table table-vcenter reports-statistics-detail-table">
    <thead>
        <tr>
            <th rowspan="2">No.</th>
            <th rowspan="2">Grade</th>
            @foreach($detailGroups as $group)<th colspan="2">Group {{ $group }}</th>@endforeach
            <th colspan="2">Total</th>
            <th rowspan="2">Old</th>
            <th rowspan="2">New</th>
            <th rowspan="2">Monthly<br>Dropout</th>
            <th rowspan="2">Cumulative<br>Dropout</th>
            <th rowspan="2">Campus</th>
        </tr>
        <tr>
            @foreach($detailGroups as $group)<th>Total</th><th>Female</th>@endforeach
            <th>Total</th><th>Female</th>
        </tr>
    </thead>
    <tbody>
        @forelse(($statistics['campuses'] ?? []) as $campus)
            <tr class="statistics-detail-campus-row"><th colspan="{{ $detailColumnCount }}">{{ $campus['campus'] }}</th></tr>
            @foreach($campus['rows'] as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <th>{{ $row['grade'] }}</th>
                    @foreach($detailGroups as $group)
                        <td>{{ $detailValue($row['groups'][$group]['total'] ?? 0) }}</td>
                        <td>{{ $detailValue($row['groups'][$group]['female'] ?? 0) }}</td>
                    @endforeach
                    <td>{{ $detailValue($row['total'] ?? 0) }}</td>
                    <td>{{ $detailValue($row['female'] ?? 0) }}</td>
                    <td>{{ $detailValue($row['old'] ?? 0) }}</td>
                    <td>{{ $detailValue($row['new'] ?? 0) }}</td>
                    <td>{{ $detailValue($row['monthly_dropout'] ?? 0) }}</td>
                    <td>{{ $detailValue($row['cumulative_dropout'] ?? 0) }}</td>
                    <td>{{ $row['campus'] }}</td>
                </tr>
            @endforeach
            <tr class="statistics-detail-total-row">
                <th colspan="{{ $detailTotalLabelColspan }}">{{ $campus['campus'] }} Total</th>
                @foreach($detailGroups as $group)
                    <th>{{ $detailTotalValue($campus['totals']['groups'][$group]['total'] ?? 0) }}</th>
                    <th>{{ $detailTotalValue($campus['totals']['groups'][$group]['female'] ?? 0) }}</th>
                @endforeach
                <th>{{ $detailTotalValue($campus['totals']['total'] ?? 0) }}</th>
                <th>{{ $detailTotalValue($campus['totals']['female'] ?? 0) }}</th>
                <th>{{ $detailTotalValue($campus['totals']['old'] ?? 0) }}</th>
                <th>{{ $detailTotalValue($campus['totals']['new'] ?? 0) }}</th>
                <th>{{ $detailTotalValue($campus['totals']['monthly_dropout'] ?? 0) }}</th>
                <th>{{ $detailTotalValue($campus['totals']['cumulative_dropout'] ?? 0) }}</th>
                <th>{{ $campus['campus'] }}</th>
            </tr>
        @empty
            <tr><td colspan="{{ $detailColumnCount }}" class="text-center text-secondary">No statistics found.</td></tr>
        @endforelse
        @if(!empty($statistics['campuses'] ?? []))
            <tr class="statistics-detail-grand-total-row">
                <th colspan="{{ $detailTotalLabelColspan }}">Grand Total</th>
                @foreach($detailGroups as $group)
                    <th>{{ $detailTotalValue($statistics['totals']['groups'][$group]['total'] ?? 0) }}</th>
                    <th>{{ $detailTotalValue($statistics['totals']['groups'][$group]['female'] ?? 0) }}</th>
                @endforeach
                <th>{{ $detailTotalValue($statistics['totals']['total'] ?? 0) }}</th>
                <th>{{ $detailTotalValue($statistics['totals']['female'] ?? 0) }}</th>
                <th>{{ $detailTotalValue($statistics['totals']['old'] ?? 0) }}</th>
                <th>{{ $detailTotalValue($statistics['totals']['new'] ?? 0) }}</th>
                <th>{{ $detailTotalValue($statistics['totals']['monthly_dropout'] ?? 0) }}</th>
                <th>{{ $detailTotalValue($statistics['totals']['cumulative_dropout'] ?? 0) }}</th>
                <th>All</th>
            </tr>
        @endif
    </tbody>
</table>
<div class="statistics-detail-footer">
    <div>Date: {{ now('Asia/Phnom_Penh')->format('F j, Y') }}</div>
    <div class="statistics-detail-registrar">Registrar's Office</div>
</div>
<style>
    .statistics-detail-preview-heading {
        display: grid;
        grid-template-columns: 220px 1fr 300px;
        align-items: end;
        gap: 1rem;
        margin: .25rem 0 .8rem;
        color: #000;
    }
    .statistics-detail-logo-wrap img {
        width: 145px;
        max-height: 100px;
        object-fit: contain;
    }
    .statistics-detail-title-wrap {
        text-align: center;
    }
    .statistics-detail-title {
        font-family: Georgia, 'Times New Roman', serif;
        font-size: 1.55rem;
        font-weight: 700;
        line-height: 1.15;
    }
    .statistics-detail-subtitle {
        font-family: Georgia, 'Times New Roman', serif;
        font-size: 1.05rem;
        line-height: 1.2;
    }
    .statistics-detail-footer {
        margin-top: .75rem;
        text-align: right;
        color: #000;
        font-size: .95rem;
        line-height: 1.35;
        font-weight: 600;
    }
    .statistics-detail-registrar {
        margin-top: .15rem;
        font-weight: 700;
    }
    .statistics-detail-updated {
        justify-self: end;
        font-size: .95rem;
        white-space: nowrap;
    }
    .reports-statistics-detail-table {
        border-collapse: collapse !important;
        width: 100%;
        table-layout: fixed;
        font-size: clamp(.46rem, .72vw, .7rem);
        color: #000;
    }
    .reports-statistics-detail-table th,
    .reports-statistics-detail-table td {
        border: 1.6px solid #111 !important;
        padding: .12rem .08rem !important;
        text-align: center !important;
        vertical-align: middle !important;
        line-height: 1.12;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: clip;
    }
    .reports-statistics-detail-table thead th {
        background: #3b73c9 !important;
        color: #fff !important;
        font-weight: 800;
        font-size: clamp(.48rem, .76vw, .68rem);
    }
    .reports-statistics-detail-table tbody th {
        color: #fff !important;
    }
    .reports-statistics-detail-table th:first-child,
    .reports-statistics-detail-table td:first-child {
        width: 3.2%;
    }
    .reports-statistics-detail-table th:nth-child(2),
    .reports-statistics-detail-table td:nth-child(2) {
        width: 5.2%;
    }
    .reports-statistics-detail-table thead tr:nth-child(2) th {
        font-size: clamp(.43rem, .66vw, .58rem);
        padding-left: .04rem !important;
        padding-right: .04rem !important;
    }
    .reports-statistics-detail-table tbody td:not(:first-child):not(:last-child) {
        min-width: 0;
    }
    .reports-statistics-detail-table th:nth-last-child(5),
    .reports-statistics-detail-table td:nth-last-child(5),
    .reports-statistics-detail-table th:nth-last-child(4),
    .reports-statistics-detail-table td:nth-last-child(4) {
        width: 4.7%;
    }
    .reports-statistics-detail-table th:nth-last-child(3),
    .reports-statistics-detail-table td:nth-last-child(3),
    .reports-statistics-detail-table th:nth-last-child(2),
    .reports-statistics-detail-table td:nth-last-child(2) {
        width: 7.2%;
        white-space: normal;
        overflow-wrap: normal;
        word-break: normal;
        font-size: clamp(.46rem, .68vw, .62rem);
    }
    .reports-statistics-detail-table th:last-child,
    .reports-statistics-detail-table td:last-child {
        width: 4.4%;
    }
    .statistics-detail-campus-row th {
        background: #d9edf7 !important;
        text-align: left !important;
        font-size: .82rem;
        height: 28px;
    }
    .statistics-detail-total-row th,
    .statistics-detail-total-row td {
        background: #d9edf7 !important;
        height: 28px;
    }
    .statistics-detail-grand-total-row th,
    .statistics-detail-grand-total-row td {
        background: #3b73c9 !important;
        color: #ffffff !important;
        height: 30px;
        font-weight: 800;
    }
    [data-bs-theme="dark"] .statistics-detail-footer,
    body.dark-mode .statistics-detail-footer {
        color: #eaf2ff;
    }
    [data-bs-theme="dark"] .reports-statistics-detail-table,
    body.dark-mode .reports-statistics-detail-table {
        color: #eaf2ff;
    }
    [data-bs-theme="dark"] .reports-statistics-detail-table th,
    [data-bs-theme="dark"] .reports-statistics-detail-table td,
    body.dark-mode .reports-statistics-detail-table th,
    body.dark-mode .reports-statistics-detail-table td {
        border-color: #52627a !important;
        color: #eaf2ff !important;
    }
    [data-bs-theme="dark"] .reports-statistics-detail-table tbody tr:not(.statistics-detail-campus-row):not(.statistics-detail-total-row) th,
    [data-bs-theme="dark"] .reports-statistics-detail-table tbody tr:not(.statistics-detail-campus-row):not(.statistics-detail-total-row) td,
    body.dark-mode .reports-statistics-detail-table tbody tr:not(.statistics-detail-campus-row):not(.statistics-detail-total-row) th,
    body.dark-mode .reports-statistics-detail-table tbody tr:not(.statistics-detail-campus-row):not(.statistics-detail-total-row) td {
        background: #182235 !important;
    }
    [data-bs-theme="dark"] .reports-statistics-detail-table thead th,
    body.dark-mode .reports-statistics-detail-table thead th {
        background: #3b73c9 !important;
        color: #ffffff !important;
    }
    [data-bs-theme="dark"] .statistics-detail-campus-row th,
    [data-bs-theme="dark"] .statistics-detail-total-row th,
    [data-bs-theme="dark"] .statistics-detail-total-row td,
    body.dark-mode .statistics-detail-campus-row th,
    body.dark-mode .statistics-detail-total-row th,
    body.dark-mode .statistics-detail-total-row td {
        background: rgba(59, 115, 201, .28) !important;
        color: #ffffff !important;
    }
    [data-bs-theme="dark"] .statistics-detail-preview-heading,
    body.dark-mode .statistics-detail-preview-heading {
        color: #f8fafc;
    }
    @media screen and (max-width: 767px) {
        .statistics-detail-preview-heading {
            grid-template-columns: 1fr;
            align-items: center;
            text-align: center;
            gap: .35rem;
            margin: 0 0 .65rem;
        }
        .statistics-detail-logo-wrap img {
            width: 82px;
            max-height: 52px;
        }
        .statistics-detail-title {
            font-size: .78rem;
            line-height: 1.15;
        }
        .statistics-detail-subtitle {
            font-size: .55rem;
        }
        .reports-statistics-detail-table {
            width: 100%;
            min-width: 0;
            table-layout: fixed;
            font-size: clamp(.24rem, 1.22vw, .36rem);
        }
        .reports-statistics-detail-table th,
        .reports-statistics-detail-table td {
            border-width: .55px !important;
            padding: .045rem .025rem !important;
            line-height: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: clip;
        }
        .reports-statistics-detail-table thead th {
            font-size: clamp(.22rem, 1.05vw, .32rem);
            line-height: .95;
        }
        .reports-statistics-detail-table thead tr:nth-child(2) th {
            font-size: clamp(.2rem, .95vw, .28rem);
        }
        .reports-statistics-detail-table th:first-child,
        .reports-statistics-detail-table td:first-child {
            width: 6.5%;
        }
        .reports-statistics-detail-table th:nth-child(2),
        .reports-statistics-detail-table td:nth-child(2) {
            width: 10.5%;
        }
        .reports-statistics-detail-table th:nth-last-child(5),
        .reports-statistics-detail-table td:nth-last-child(5),
        .reports-statistics-detail-table th:nth-last-child(4),
        .reports-statistics-detail-table td:nth-last-child(4) {
            width: 5.5%;
        }
        .reports-statistics-detail-table th:nth-last-child(3),
        .reports-statistics-detail-table td:nth-last-child(3),
        .reports-statistics-detail-table th:nth-last-child(2),
        .reports-statistics-detail-table td:nth-last-child(2) {
            width: 8.5%;
            white-space: normal;
            font-size: clamp(.2rem, .9vw, .28rem);
        }
        .reports-statistics-detail-table th:last-child,
        .reports-statistics-detail-table td:last-child {
            width: 6.2%;
        }
        .statistics-detail-campus-row th,
        .statistics-detail-total-row th,
        .statistics-detail-total-row td,
        .statistics-detail-grand-total-row th,
        .statistics-detail-grand-total-row td {
            height: 24px;
        }
        .statistics-detail-footer {
            margin-top: .55rem;
            text-align: center;
            font-size: .78rem;
        }
    }
</style>
@elseif($type === 'student-statistics')
@php
    $statisticsColumnLabel = static function ($column): string {
        $label = trim((string) $column);
        if (strcasecmp($label, 'Nursery') === 0) {
            return 'N';
        }
        if (preg_match('/^Grade\s*(\d+)$/i', $label, $matches)) {
            return 'G' . $matches[1];
        }
        return $label;
    };
@endphp
<table class="table table-vcenter reports-statistics-table">
    <thead>
        <tr>
            <th>No.</th><th>Campus</th>
            @foreach($statistics['columns'] as $column)<th>{{ $statisticsColumnLabel($column) }}</th>@endforeach
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($statistics['rows'] as $index => $row)
            <tr>
                <td>{{ $index + 1 }}</td>
                <th>{{ $row['campus'] }}</th>
                @foreach($row['cells'] as $cellIndex => $cell)<td>@if((int) $cell !== 0)<div>{{ $cell }}</div><div class="statistics-new-note">New: {{ (int) ($row['newCells'][$cellIndex] ?? 0) }}</div>@endif</td>@endforeach
                <td><div>{{ $row['total'] }}</div><div class="statistics-new-note">New: {{ (int) ($row['new_total'] ?? 0) }}</div></td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th colspan="2">Grand Total</th>
            @foreach($statistics['columnTotals'] as $totalIndex => $total)<th><div>{{ $total }}</div><div class="statistics-new-note statistics-new-note-footer">New: {{ (int) ($statistics['columnNewTotals'][$totalIndex] ?? 0) }}</div></th>@endforeach
            <th><div>{{ $statistics['grandTotal'] }}</div><div class="statistics-new-note statistics-new-note-footer">New: {{ (int) ($statistics['grandNewTotal'] ?? 0) }}</div></th>
        </tr>
    </tfoot>
</table>

<style>
    .reports-statistics-table thead th,
    .reports-statistics-table tfoot th {
        --tblr-table-bg: var(--tblr-primary, #3b73c9);
        --tblr-table-color: #ffffff;
        background-color: var(--tblr-primary, #3b73c9) !important;
        color: #ffffff !important;
        font-weight: 700;
    }
    .reports-statistics-table tbody td:last-child {
        background-color: #e9f2ff !important;
        font-weight: 700;
        text-align: center !important;
        vertical-align: middle !important;
    }
    .reports-statistics-table thead th:last-child,
    .reports-statistics-table tfoot th:last-child {
        text-align: center !important;
    }    .reports-statistics-table {
        border-collapse: collapse !important;
        border: 1px solid #dbe5f1 !important;
    }
    .reports-statistics-table th,
    .reports-statistics-table td {
        border: 1px solid #dbe5f1 !important;
        padding-left: .22rem !important;
        padding-right: .22rem !important;
    }    .reports-statistics-table thead th,
    .reports-statistics-table tbody td,
    .reports-statistics-table tfoot th {
        text-align: center !important;
        vertical-align: middle !important;
    }
    .reports-statistics-table tbody th {
        text-align: left !important;
        vertical-align: middle !important;
    }    .statistics-new-note {
        display: block;
        margin-top: .08rem;
        color: #2fb344;
        font-size: clamp(.46rem, .56vw, .54rem);
        font-weight: 500;
        line-height: 1.05;
        white-space: nowrap;
    }
    .statistics-new-note-footer {
        color: #d7f7df;
    }
    [data-bs-theme="dark"] .reports-statistics-table tbody td:last-child,
    body.dark-mode .reports-statistics-table tbody td:last-child {
        background-color: rgba(59, 115, 201, .22) !important;
    }
</style>
@elseif($type === 'attendance-list')
@php
    $attendanceMonth = $filters['month'] ?? now('Asia/Phnom_Penh')->format('Y-m');
    try {
        $attendanceDate = \Carbon\Carbon::createFromFormat('Y-m-d', $attendanceMonth . '-01');
    } catch (\Throwable $e) {
        $attendanceDate = now('Asia/Phnom_Penh')->startOfMonth();
    }
    $attendanceDays = $attendanceDate->daysInMonth;
    $attendanceFirst = $enrollments->first();
    $attendanceGradeClass = $attendanceFirst
        ? trim(($attendanceFirst?->grade?->grade_short_name ?: $attendanceFirst?->grade?->grade ?: '') . ($attendanceFirst?->schoolClass?->class_name ?? ''))
        : 'Selected Class';
    $attendanceCampus = $attendanceFirst?->campus?->campus_name_en
        ?: ($campuses->firstWhere('id', (int) ($filters['campus_id'] ?? 0))?->campus_name_en ?: '');
    $attendanceAcademicYear = $attendanceFirst?->academicYear?->academic_year
        ?: ($academicYears->firstWhere('id', (int) ($filters['academic_year_id'] ?? 0))?->academic_year ?: 'All Academic Years');
    $attendanceSession = $attendanceFirst?->session?->session_name
        ?: ($attendanceFirst?->session?->session_short_name ?: '');
    $attendanceSessionShort = $attendanceFirst?->session?->session_short_name ?: '';
    $attendanceBranding = \App\Models\BrandingSetting::current();
    $attendanceLogoPath = $attendanceBranding?->report_logo_1_path ?: $attendanceBranding?->report_logo_2_path;
    $attendanceLogoUrl = isset($pdfLogoSrc) && $pdfLogoSrc ? $pdfLogoSrc : ($attendanceLogoPath ? asset('storage/' . ltrim($attendanceLogoPath, '/')) : asset('storage/school_logo/wis_logo.png'));
    $attendanceRows = $enrollments->values();
    $attendancePrintedAt = now('Asia/Phnom_Penh')->format('n/j/Y g:i:s A');
    $attendancePrintLayout = (bool) ($attendancePrintLayout ?? false);
@endphp
@php
    $attendanceClassGroups = $attendanceRows->isEmpty()
        ? collect([collect()])
        : $attendanceRows->groupBy(fn ($row) => ($row->grade_id ?: 0) . ':' . ($row->class_id ?: 0))->sortBy(function ($rows) {
            $first = $rows->first();
            return sprintf('%06d-%06d-%s', (int) ($first?->grade?->grade_order ?? 999999), (int) ($first?->schoolClass?->class_order ?? 999999), trim((string) (($first?->grade?->grade_short_name ?: $first?->grade?->grade) . ($first?->schoolClass?->class_name ?? ''))));
        });
@endphp
@foreach($attendanceClassGroups as $attendanceRows)
@php
    $attendanceFirst = $attendanceRows->first();
    $attendanceGradeClass = $attendanceFirst
        ? trim(($attendanceFirst?->grade?->grade_short_name ?: $attendanceFirst?->grade?->grade ?: '') . ($attendanceFirst?->schoolClass?->class_name ?? ''))
        : 'Selected Class';
    $attendanceCampus = $attendanceFirst?->campus?->campus_name_en
        ?: ($filters['campus_id'] ?? null ? \App\Models\SchoolInfo::whereKey($filters['campus_id'])->value('campus_name_en') : '');
    $attendanceAcademicYear = $attendanceFirst?->academicYear?->academic_year
        ?: ($filters['academic_year_id'] ?? null ? \App\Models\AcademicYear::whereKey($filters['academic_year_id'])->value('academic_year') : '');
    $attendanceSession = $attendanceFirst?->session?->session_name
        ?: ($attendanceFirst?->session?->session_short_name ?: '');
    $attendanceSessionShort = $attendanceFirst?->session?->session_short_name ?: '';
@endphp
<div class="attendance-teacher-report {{ $attendancePrintLayout ? 'attendance-print-layout' : 'attendance-preview-layout' }}">
    <div class="attendance-teacher-heading">
        @if($attendancePrintLayout)
            <div class="attendance-teacher-left">
                <img class="attendance-teacher-logo-img" src="{{ $attendanceLogoUrl }}" alt="School Logo">

            </div>
        @endif
        <div class="attendance-teacher-center">
            <div class="attendance-title-kh">បញ្ជីសម្រង់វត្តមានសិស្ស</div>
            <div class="attendance-title-en">Class Attendance List Grade&nbsp;&nbsp; {{ $attendanceGradeClass }} &nbsp;&nbsp; for&nbsp; {{ $attendanceDate->format('F') }}</div>
        </div>
        @if($attendancePrintLayout)
            <div class="attendance-teacher-right">
                <div class="attendance-motto-kh"><div>ព្រះរាជាណាចក្រកម្ពុជា</div><div class="attendance-motto-kh-line">ជាតិ សាសនា ព្រះមហាក្សត្រ</div></div>
                <div class="attendance-motto-en">KINGDOM OF CAMBODIA<br>NATION&nbsp;&nbsp;&nbsp; RELIGION&nbsp;&nbsp;&nbsp; KING</div>
                <div class="attendance-tacteing">5</div>
            </div>
        @endif
    </div>
    @if($attendancePrintLayout)
        <div class="attendance-info-lines">
            <div class="attendance-info-item">
                <div class="attendance-info-kh">ឈ្មោះគ្រូ</div>
                <div class="attendance-info-en"><strong>Teacher :</strong> <span class="attendance-blank"></span></div>
            </div>
            <div class="attendance-info-item">
                <div class="attendance-info-kh">មុខវិជ្ជា</div>
                <div class="attendance-info-en"><strong>Subject :</strong> <span class="attendance-blank"></span></div>
            </div>
            <div class="attendance-info-item">
                <div class="attendance-info-kh">បន្ទប់</div>
                <div class="attendance-info-en"><strong>Room :</strong> <span class="attendance-blank"></span></div>
            </div>
            <div class="attendance-info-item">
                <div class="attendance-info-kh">ពេលសិក្សា</div>
                <div class="attendance-info-en"><strong>Session :</strong> <span class="attendance-session-value">{{ $attendanceSession ?: ($attendanceSessionShort ?: '-') }}</span></div>
            </div>
        </div>
    @endif
    <div class="attendance-note-line"><span class="attendance-note-kh">*ចំណាំ</span>/Note : <span class="attendance-check">✓</span> = <span class="attendance-note-kh">វត្តមាន</span>/Present , T = <span class="attendance-note-kh">មកយឺត</span>/Tardy , E = <span class="attendance-note-kh">អវត្តមានមានច្បាប់</span>/Excused Absence , U = <span class="attendance-note-kh">អវត្តមានគ្មានច្បាប់</span>/Unexcused Absence</div>
    <table class="reports-attendance-teacher-table">
        <thead>
            <tr>
                <th rowspan="2" class="attendance-no-col">Nº</th>
                <th rowspan="2" class="attendance-name-col">Name</th>
                <th rowspan="2" class="attendance-id-col">ID</th>
                <th rowspan="2" class="attendance-vertical-col"><span>Gender</span></th>
                <th rowspan="2" class="attendance-vertical-col"><span>Group</span></th>
                <th colspan="{{ $attendanceDays }}" class="attendance-date-head">Date</th>
                <th colspan="2" class="attendance-total-head">Total</th>
            </tr>
            <tr>
                @for($day = 1; $day <= $attendanceDays; $day++)
                    <th class="attendance-day-col">{{ $day }}</th>
                @endfor
                <th class="attendance-total-col">T</th>
                <th class="attendance-total-col">E+U</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendanceRows as $index => $row)
                <tr>
                    <td class="attendance-no-col">{{ $index + 1 }}</td>
                    <td class="attendance-name-col"><div class="attendance-name-kh">{{ $row->student?->full_name_kh ?: '' }}</div><div class="attendance-name-en">{{ $row->student?->full_name_en ?: '-' }}</div></td>
                    <td class="attendance-id-col">{{ $row->student?->student_id ?: '-' }}</td>
                    <td class="attendance-vertical-col">{{ strtoupper(substr((string) $row->student?->gender, 0, 1)) === 'F' ? 'F' : 'M' }}</td>
                    <td class="attendance-vertical-col">{{ $row->session?->session_short_name ?: ($attendanceSessionShort ?: '-') }}</td>
                    @for($day = 1; $day <= $attendanceDays; $day++)
                        <td class="attendance-day-col"></td>
                    @endfor
                    <td class="attendance-total-col"></td>
                    <td class="attendance-total-col"></td>
                </tr>
            @empty
                <tr><td colspan="{{ $attendanceDays + 7 }}" class="attendance-empty-cell">No students found.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($attendancePrintLayout)
        <div class="attendance-teacher-footer">
            <div class="attendance-bottom-notes">
                <div class="attendance-footer-note-kh">* ប្រសិនបើសិស្សគ្មានឈ្មោះក្នុងបញ្ជី ឬគ្មានក្រដាសអនុញ្ញាតឱ្យចូលថ្នាក់ ឬរៀនខុសក្រុម ឬថ្នាក់ មិនអនុញ្ញាតឱ្យចូលក្នុងថ្នាក់ឡើយ ត្រូវបញ្ជូនសិស្សទាំងនោះមកករិយាល័យសិក្សា។</div>
                <div>* If student's name is not listed, he/she doesn't have an Admission Slip or he/she is in the wrong group, he/she must not be admitted to class.</div>
            </div>
        </div>
        <div class="attendance-print-meta"><span></span><span class="attendance-print-page-number">Page 1 of 1</span><span class="attendance-print-date">{{ $attendancePrintedAt }}</span></div>
    @endif
</div>
@endforeach
<style>
    .attendance-teacher-report { color: #000; font-family: Arial, Helvetica, sans-serif; min-width: 1180px; overflow-x: auto; margin-bottom: 1rem; }
    .attendance-print-layout { break-after: page; page-break-after: always; }
    .attendance-teacher-report:last-of-type { break-after: auto; page-break-after: auto; margin-bottom: 0; }
    .attendance-teacher-heading { display: block; margin: 1rem 0 .85rem; }
    .attendance-print-layout .attendance-teacher-heading { display: grid; grid-template-columns: 260px 1fr 360px; gap: 1rem; align-items: end; margin: .2rem 0 .85rem; }
    .attendance-teacher-left { text-align: center; }
    .attendance-teacher-right { text-align: center; align-self: start; }
    .attendance-teacher-logo-img { width: 258px; height: auto; object-fit: contain; }
    .attendance-school-kh { font-family: var(--khmer-font-siemreap), 'Khmer OS Siemreap', 'Khmer OS Siem Reap', serif; font-size: .52rem; line-height: 1.1; }
    .attendance-school-en { font-size: .38rem; line-height: 1.1; }
    .attendance-office { font-family: 'Times New Roman', serif; font-size: 1.25rem; font-weight: 800; margin-top: .25rem; line-height: 1.05; }
    .attendance-campus { font-family: 'Times New Roman', serif; font-size: 1.1rem; line-height: 1.05; }
    .attendance-teacher-center { text-align: center; padding-bottom: .25rem; }
    .attendance-title-kh { font-family: 'Khmer OS Muol Light', 'Khmer OS Muol', var(--khmer-font-siemreap), 'Khmer OS Siemreap', 'Khmer OS Siem Reap', serif !important; font-size: 1.65rem; font-weight: 400; line-height: 1.2; }
    .attendance-title-en { font-family: 'Times New Roman', serif; font-size: 1.45rem; font-weight: 800; line-height: 1.05; }
    .attendance-motto-kh { font-family: 'Khmer OS Muol Light', 'Khmer OS Muol', var(--khmer-font-siemreap), 'Khmer OS Siemreap', 'Khmer OS Siem Reap', serif !important; font-size: 1.22rem; font-weight: 400; line-height: 1.55; }
    .attendance-motto-kh { white-space: nowrap; }
    .attendance-motto-kh-line { display: block; white-space: nowrap; margin-top: .35rem; }
    .attendance-motto-en { margin-top: .1rem; font-size: 1.05rem; font-weight: 400; line-height: 1.2; }
    .attendance-tacteing { font-family: Tacteing, serif; font-size: 1.8rem; line-height: .55; }
    .attendance-info-lines { display: grid; grid-template-columns: 1fr 1fr 1fr 1.2fr; gap: .5rem; align-items: end; margin: .2rem 6.8% .05rem; font-family: 'Times New Roman', var(--khmer-font-siemreap), 'Khmer OS Siemreap', 'Khmer OS Siem Reap', serif; font-size: 1.15rem; }
    .attendance-info-item { display: flex; flex-direction: column; align-items: stretch; text-align: left; white-space: nowrap; }
    .attendance-info-kh { display: block; width: 100%; text-align: left; font-family: 'Khmer OS Siemreap', 'Khmer OS Siem Reap', var(--khmer-font-siemreap), serif !important; font-size: 1.25rem; font-weight: 400; line-height: 1.05; margin-bottom: .05rem; }
    .attendance-info-en { display: flex; align-items: flex-end; gap: .25rem; font-family: 'Times New Roman', serif; font-size: 1.35rem; font-weight: 800; line-height: 1; }
    .attendance-info-en strong { font-weight: 800; }
    .attendance-info-en { display: flex; align-items: flex-end; gap: .2rem; line-height: 1.05; }
    .attendance-blank { display: inline-block; flex: 1 1 auto; min-width: 110px; border-bottom: 2px solid #000; transform: translateY(-2px); }
    .attendance-session-value { margin-left: .35rem; font-weight: 800; }
    .attendance-note-line { text-align: center; font-size: .86rem; margin: .1rem 0 .35rem; font-family: var(--tblr-font-sans-serif, Arial), Arial, sans-serif; }
    .attendance-note-kh { font-family: 'Khmer OS Siemreap', 'Khmer OS Siem Reap', var(--khmer-font-siemreap), sans-serif !important; }
    .attendance-check { font-size: 1.25rem; font-weight: 800; }
    .reports-attendance-teacher-table { width: 100%; border-collapse: collapse !important; table-layout: fixed; color: #000; font-size: .72rem; font-family: Arial, var(--khmer-font-siemreap), 'Khmer OS Siemreap', 'Khmer OS Siem Reap', sans-serif; }
    .reports-attendance-teacher-table th, .reports-attendance-teacher-table td { border: 1px solid #000 !important; padding: .1rem .08rem !important; text-align: center; vertical-align: middle; height: 31px; line-height: 1.05; }
    .reports-attendance-teacher-table thead th { background: var(--tblr-primary, #206bc4) !important; color: #fff !important; font-weight: 500; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .reports-attendance-teacher-table tbody tr:nth-child(even) td { background: #e9e9e9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .reports-attendance-teacher-table .attendance-no-col { width: 36px; }
    .reports-attendance-teacher-table .attendance-name-col { width: 205px; text-align: left; padding-top: .28rem !important; padding-bottom: .28rem !important; }
    .reports-attendance-teacher-table th.attendance-name-col { text-align: center; }
    .reports-attendance-teacher-table .attendance-id-col { width: 70px; }
    .reports-attendance-teacher-table .attendance-vertical-col { width: 28px; }
    .reports-attendance-teacher-table th.attendance-vertical-col span { writing-mode: vertical-rl; transform: rotate(180deg); display: inline-block; line-height: 1; }
    .reports-attendance-teacher-table .attendance-day-col { width: 23px; }
    .reports-attendance-teacher-table .attendance-total-col { width: 42px; }
    .attendance-name-kh { font-family: var(--khmer-font-siemreap), 'Khmer OS Siemreap', 'Khmer OS Siem Reap', serif; font-size: .78rem; min-height: 1.05em; line-height: 1.25; margin-bottom: .16rem; }
    .attendance-name-en { font-size: .78rem; text-transform: uppercase; margin-top: .12rem; line-height: 1.18; }
    .attendance-empty-cell { text-align: center !important; color: #6b7280; height: 40px !important; }
    .attendance-print-layout .reports-attendance-teacher-table thead th { background: #ffff99 !important; color: #000 !important; font-weight: 500 !important; }
    .attendance-teacher-footer { display: block; margin-top: .35rem; color: #000; }
    .attendance-bottom-notes { font-family: Arial, var(--khmer-font-siemreap), 'Khmer OS Siemreap', 'Khmer OS Siem Reap', sans-serif; font-size: .78rem; line-height: 1.35; font-style: normal; }
    .attendance-footer-note-kh { font-family: 'Khmer OS Siemreap', 'Khmer OS Siem Reap', var(--khmer-font-siemreap), sans-serif !important; }
    .attendance-session-legend { border-collapse: collapse; width: 100%; font-size: .76rem; text-align: center; color: #000; }
    .attendance-session-legend td { border: 1px solid #000; padding: .08rem .2rem; }
    .attendance-print-meta { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; margin-top: .25rem; font-size: .72rem; color: #000; }`r`n    .attendance-print-page-number { text-align: center; }`r`n    .attendance-print-date { text-align: right; }
    [data-bs-theme="dark"] .attendance-teacher-report, body.dark-mode .attendance-teacher-report { color: #eaf2ff; }
    [data-bs-theme="dark"] .attendance-teacher-report .attendance-teacher-heading, body.dark-mode .attendance-teacher-report .attendance-teacher-heading { color: #eaf2ff; }
    [data-bs-theme="dark"] .reports-attendance-teacher-table, body.dark-mode .reports-attendance-teacher-table { color: #eaf2ff; }
    [data-bs-theme="dark"] .reports-attendance-teacher-table th, body.dark-mode .reports-attendance-teacher-table th { background: var(--tblr-primary, #206bc4) !important; color: #fff !important; }
    [data-bs-theme="dark"] .reports-attendance-teacher-table td, body.dark-mode .reports-attendance-teacher-table td { border-color: #52627a !important; color: #eaf2ff; }
    [data-bs-theme="dark"] .reports-attendance-teacher-table tbody td, body.dark-mode .reports-attendance-teacher-table tbody td { background: #182235 !important; }
    [data-bs-theme="dark"] .reports-attendance-teacher-table tbody tr:nth-child(even) td, body.dark-mode .reports-attendance-teacher-table tbody tr:nth-child(even) td { background: #202d44 !important; }
    @media screen and (max-width: 767px) {
        .attendance-teacher-report { min-width: 1020px; transform: scale(.34); transform-origin: top left; width: 294%; margin-bottom: -65%; }
        .attendance-title-kh { font-size: 1.25rem; font-weight: 400; }
        .attendance-title-en { font-size: 1.05rem; }
        .attendance-motto-kh { font-size: .9rem; }
        .attendance-motto-en { font-size: .78rem; }
        .attendance-info-lines { font-size: .85rem; margin-left: 2%; margin-right: 2%; }
        .attendance-note-line { font-size: .62rem; }
        .reports-attendance-teacher-table { font-size: .52rem; }
        .reports-attendance-teacher-table .attendance-name-col { width: 160px; }
        .reports-attendance-teacher-table .attendance-day-col { width: 18px; }
    }
</style>@else
@php
    $isContactList = $type === 'student-contact-list';
    $familyPhone = static function ($student, string $relationship): string {
        $member = $student?->familyMembers?->first(function ($familyMember) use ($relationship) {
            return ($familyMember->relationship_type ?? null) === $relationship
                || ($familyMember->pivot?->relationship_type ?? null) === $relationship;
        });

        return $member?->phone ?: '-';
    };
@endphp
<table class="table table-vcenter reports-student-list-table {{ $isContactList ? 'reports-contact-list-table' : '' }}">
    <thead>
        <tr>
            <th>#</th>
            <th>Student ID</th>
            <th>Student Name</th>
            <th>Gender</th>
            <th>Academic Year</th>
            @if($isContactList)
                <th>Grade</th>
                <th>Home Phone</th>
                <th>Mother's Phone</th>
                <th>Father's Phone</th>
            @else
                <th>No.</th><th>Campus</th>
                <th>Grade</th>
                <th>Group</th>
            @endif
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($enrollments as $index=>$row)
            <tr>
                <td>{{ $index+1 }}</td>
                <td>{{ $row->student?->student_id }}</td>
                <td><div class="student-name-kh">{{ $row->student?->full_name_kh ?: '-' }}</div><div class="student-name-en">{{ $row->student?->full_name_en ?: '-' }}</div></td>
                <td>{{ strtoupper(substr((string) $row->student?->gender, 0, 1)) === 'F' ? 'F' : 'M' }}</td>
                <td>{{ $row->academicYear?->academic_year }}</td>
                @if($isContactList)
                    <td>{{ collect([$row->campus?->campus_name_en, trim(($row->grade?->grade_short_name ?: $row->grade?->grade) . ($row->schoolClass?->class_name ?? '')), $row->session?->session_short_name])->filter()->join('-') ?: '-' }}</td>
                    <td>{{ $row->student?->home_phone ?: '-' }}</td>
                    <td>{{ $familyPhone($row->student, 'mother') }}</td>
                    <td>{{ $familyPhone($row->student, 'father') }}</td>
                @else
                    <td>{{ $row->campus?->campus_name_en }}</td>
                    <td>{{ trim(($row->grade?->grade_short_name ?: $row->grade?->grade) . ($row->schoolClass?->class_name ?? '')) ?: '-' }}</td>
                    <td>{{ $row->session?->session_short_name ?: '-' }}</td>
                @endif
                <td>{{ $row->enrollment_status }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $isContactList ? 10 : 9 }}" class="text-center text-secondary">No students found.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="reports-student-mobile-list">
    @forelse($enrollments as $index=>$row)
        @php
            $mobileGrade = trim(($row->grade?->grade_short_name ?: $row->grade?->grade) . ($row->schoolClass?->class_name ?? ''));
            $mobileClassLine = collect([$row->campus?->campus_name_en, $mobileGrade, $row->session?->session_short_name])->filter()->join('-');
            $mobileEnrollmentLine = collect([$row->academicYear?->academic_year, $mobileClassLine])->filter()->join(' ');
        @endphp
        <div class="reports-student-mobile-card">
            <div class="reports-student-mobile-number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</div>
            <div class="reports-student-mobile-status">{{ ucfirst($row->enrollment_status ?: '-') }}</div>
            <div class="reports-student-mobile-body">
                <div class="reports-student-mobile-id">{{ $row->student?->student_id ?: '-' }}</div>
                <div class="student-name-kh">{{ $row->student?->full_name_kh ?: '-' }}</div>
                <div class="student-name-en">{{ $row->student?->full_name_en ?: '-' }}</div>
                <div class="reports-student-mobile-meta"><span>{{ strtoupper(substr((string) $row->student?->gender, 0, 1)) === 'F' ? 'F' : 'M' }}</span><span class="reports-student-mobile-enrollment">{{ $mobileEnrollmentLine ?: '-' }}</span></div>
                @if($isContactList)
                    <div class="reports-student-mobile-contact"><span>Home: {{ $row->student?->home_phone ?: '-' }}</span><span>Mother: {{ $familyPhone($row->student, 'mother') }}</span><span>Father: {{ $familyPhone($row->student, 'father') }}</span></div>
                @endif
            </div>
        </div>
    @empty
        <div class="reports-student-mobile-empty">No students found.</div>
    @endforelse
</div>
<style>.student-name-kh{line-height:1.35}.student-name-en{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif;line-height:1.35}.reports-contact-list-table{min-width:900px}.reports-contact-list-table th,.reports-contact-list-table td{white-space:nowrap}.reports-contact-list-table th:nth-child(2),.reports-contact-list-table td:nth-child(2){min-width:74px;max-width:86px}.reports-contact-list-table th:nth-child(3),.reports-contact-list-table td:nth-child(3){white-space:normal;min-width:150px;max-width:175px}.reports-contact-list-table th:nth-child(3) .student-name-en,.reports-contact-list-table td:nth-child(3) .student-name-en{font-size:.75rem;line-height:1.25}.reports-contact-list-table th:nth-child(4),.reports-contact-list-table td:nth-child(4){min-width:54px;max-width:62px;text-align:center}.reports-contact-list-table th:nth-child(6),.reports-contact-list-table td:nth-child(6){min-width:92px;max-width:110px}.reports-contact-list-table th:nth-child(7),.reports-contact-list-table td:nth-child(7),.reports-contact-list-table th:nth-child(8),.reports-contact-list-table td:nth-child(8),.reports-contact-list-table th:nth-child(9),.reports-contact-list-table td:nth-child(9){min-width:105px;max-width:118px}.reports-student-mobile-contact{display:grid;gap:.2rem;margin-top:.55rem;color:#52627a;font-size:.82rem}</style>
@endif
<style>.reports-student-list-table .student-name-kh{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif}.reports-student-list-table .student-name-en{font-family:inherit}</style>

@endif
