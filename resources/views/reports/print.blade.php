@php
    $branding = \App\Models\BrandingSetting::current();
    $isPdfMode = $pdfMode ?? false;
    $fontPath = static fn ($file) => str_replace('\\', '/', public_path('fonts/khmer/' . $file));
@endphp
<!doctype html>
<html lang="{{ ($filters['print_format'] ?? 'internal') === 'moeys' ? 'km' : 'en' }}">
<head><meta charset="utf-8"><title>{{ $title }}</title>@unless($isPdfMode)@vite('resources/js/khmer-calendar.js')@endunless<style>
@font-face{font-family:'Khmer OS Siemreap';src:url('{{ $isPdfMode ? $fontPath('KhmerOSsiemreap.ttf') : asset('fonts/khmer/KhmerOSsiemreap.ttf') }}') format('truetype');font-weight:normal;font-style:normal}
@font-face{font-family:'Khmer OS Muol Light';src:url('{{ $isPdfMode ? $fontPath('KhmerOSmuollight.ttf') : asset('fonts/khmer/KhmerOSmuollight.ttf') }}') format('truetype');font-weight:normal;font-style:normal}
@font-face{font-family:'Tacteing';src:url('{{ $isPdfMode ? $fontPath('Tacteing.ttf') : asset('fonts/khmer/Tacteing.ttf') }}') format('truetype');font-weight:normal;font-style:normal}
@page{size:A4 portrait;margin:8mm}*{box-sizing:border-box}body{margin:0;color:#172b4d;font-family:Arial,sans-serif;font-size:11px}.toolbar{display:flex;gap:8px;margin-bottom:14px}.toolbar button{border:0;border-radius:4px;background:#206bc4;color:#fff;padding:8px 14px;cursor:pointer}.a4-page{min-height:270mm;page-break-after:always}.a4-page:last-child{page-break-after:auto}.report-header{position:relative;min-height:39mm;border-bottom:2px solid #206bc4;padding:0 20mm 7px;text-align:center}.report-header .logo{position:absolute;top:0;left:0;max-width:34mm;max-height:28mm;object-fit:contain}.report-header .motto{position:static;width:auto;text-align:center;font-family:"Khmer OS Muol Light","Khmer OS Muol",serif;font-size:11px;line-height:1.55;margin:0 auto 4px}.report-header .motto::after{content:"KINGDOM OF CAMBODIA\A NATION RELIGION KING";display:block;white-space:pre;font-family:Arial,sans-serif;font-size:9px;line-height:1.35;font-weight:700}.report-header h1{margin:4px 0;font-size:18px}.report-header h2{margin:2px 0;font-size:14px}.report-header p{margin:3px 0}.report-header .small{color:#52627a;font-size:9px}.report-table{width:100%;border-collapse:collapse;margin-top:10px}.report-table th,.report-table td{border:1px solid #9aa8ba;padding:5px 6px;text-align:center;vertical-align:middle}.report-table th{background:#f1f3f5;background-color:#f1f3f5;font-weight:700;-webkit-print-color-adjust:exact;print-color-adjust:exact}.report-table .left{text-align:left}.report-table .khmer{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif}.report-table .english{font-size:10px;margin-top:2px}.signature{display:flex;justify-content:space-between;margin-top:24px}.signature span{border-top:1px solid #65748b;padding-top:5px;width:160px;text-align:center}.empty{text-align:center;padding:20px}@media print{.toolbar{display:none}}
</style></head>
<body class="{{ $isPdfMode ? 'is-pdf-export' : '' }}" data-report-type="{{ $type }}" data-report-print-format="{{ $filters['print_format'] ?? 'internal' }}" data-report-date="{{ $filters['report_date'] ?? now()->format('Y-m-d') }}" data-report-academic-year="{{ $academicYear?->academic_year ?? '' }}" data-report-campus-kh="{{ $enrollments->first()?->campus?->campus_name_kh ?? $campus?->campus_name_kh ?? '' }}" data-report-campus-en="{{ $enrollments->first()?->campus?->campus_name_en ?? $campus?->campus_name_en ?? '' }}" data-report-campus-address="{{ $enrollments->first()?->campus?->address ?? $campus?->address ?? '' }}">
@unless($isPdfMode)<div class="toolbar"><button type="button" data-report-action="print">Print</button><button type="button" data-report-action="close">Close</button></div>@endunless
@if($type === 'student-list')
    @include('reports.print-student-list')
@elseif($type === 'student-contact-list')
    @include('reports.print-student-contact-list')
@elseif($type === 'student-statistics-detail')
<div class="statistics-detail-print-page">
    @include('reports._table')
</div>
<style>
    @page{size:A4 landscape;margin:6mm}
    body[data-report-type="student-statistics-detail"]{color:#000;font-family:Arial,sans-serif}
    body[data-report-type="student-statistics-detail"] .toolbar{margin-bottom:6px}
    .statistics-detail-print-page{width:100%;padding:0}
    .statistics-detail-print-page .statistics-detail-preview-heading{grid-template-columns:58mm 1fr 58mm;margin:0 0 3mm;align-items:end}
    .statistics-detail-print-page .statistics-detail-logo-wrap img{width:45mm;max-height:22mm}
    .statistics-detail-print-page .statistics-detail-title{font-size:24px}
    .statistics-detail-print-page .statistics-detail-subtitle{font-size:16px}
    .statistics-detail-print-page .statistics-detail-updated{font-size:13px}
    .statistics-detail-print-page .reports-statistics-detail-table{font-size:9px;table-layout:fixed;margin-top:0}
    .statistics-detail-print-page .reports-statistics-detail-table th,
    .statistics-detail-print-page .reports-statistics-detail-table td{border:0.75px solid #111 !important;padding:1.2px 1px !important;line-height:1.05;height:5.2mm}
    .statistics-detail-print-page .reports-statistics-detail-table thead th{background:#3b73c9 !important;color:#fff !important;font-size:8.5px;height:8mm;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .statistics-detail-print-page .reports-statistics-detail-table thead tr:nth-child(2) th{font-size:7px}
    .statistics-detail-print-page .reports-statistics-detail-table tbody tr:not(.statistics-detail-campus-row):not(.statistics-detail-total-row):not(.statistics-detail-grand-total-row) th:nth-child(2){color:#000 !important;background:#fff !important;}
    .statistics-detail-print-page .statistics-detail-campus-row th,
    .statistics-detail-print-page .statistics-detail-total-row th,
    .statistics-detail-print-page .statistics-detail-total-row td{background:#d9edf7 !important;color:#000 !important;height:6mm;font-size:10px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .statistics-detail-print-page .statistics-detail-grand-total-row th,
    .statistics-detail-print-page .statistics-detail-grand-total-row td{background:#3b73c9 !important;color:#fff !important;height:6mm;font-size:9px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    @media print{.statistics-detail-print-page{padding:0}.statistics-detail-print-page .statistics-detail-preview-heading{break-inside:avoid}.statistics-detail-print-page .reports-statistics-detail-table{break-inside:auto}.statistics-detail-print-page .reports-statistics-detail-table tr{break-inside:avoid;break-after:auto}}
</style>
@elseif($type === 'student-statistics')
@php
    $statisticsColumnLabel = static function ($column): string {
        $label = trim((string) $column);
        if (strcasecmp($label, 'Nursery') === 0) return 'N';
        if (preg_match('/^Grade\s*(\d+)$/i', $label, $matches)) return $matches[1];
        return $label;
    };
    $statisticsColumnGroup = static function ($label): string {
        if (in_array($label, ['N', 'K1', 'K2', 'K3'], true)) return 'early';
        if (is_numeric($label) && (int) $label >= 1 && (int) $label <= 6) return 'primary';
        if (is_numeric($label) && (int) $label >= 7 && (int) $label <= 12) return 'secondary';
        return 'other';
    };
    $statisticsLabels = collect($statistics['columns'] ?? [])->map($statisticsColumnLabel)->values();
    $statisticsGroups = $statisticsLabels->map($statisticsColumnGroup)->values();
    $statisticsGroupTotals = ['early' => 0, 'primary' => 0, 'secondary' => 0];
    $statisticsGroupNewTotals = ['early' => 0, 'primary' => 0, 'secondary' => 0];
    foreach (($statistics['columnTotals'] ?? []) as $index => $total) {
        $group = $statisticsGroups[$index] ?? 'other';
        if (isset($statisticsGroupTotals[$group])) $statisticsGroupTotals[$group] += (int) $total;
        if (isset($statisticsGroupNewTotals[$group])) $statisticsGroupNewTotals[$group] += (int) ($statistics['columnNewTotals'][$index] ?? 0);
    }
    $statisticsDate = \Carbon\Carbon::parse($filters['report_date'] ?? now()->format('Y-m-d'))->format('F j, Y');
    $statisticsLogo = $branding?->report_logo_1_path ? asset('storage/'.$branding->report_logo_1_path) : null;
    $statisticsCampusTitle = $campus?->campus_name_en ? $campus->campus_name_en : 'all Campuses';
@endphp
<div class="statistics-print-page">
    <div class="statistics-print-header">
        <div class="statistics-print-logo-wrap">
            @if($statisticsLogo)<img class="statistics-print-logo" src="{{ $statisticsLogo }}" alt="School Logo">@endif
        </div>
        <div class="statistics-print-title-wrap">
            <h1>Student Statistics Summary</h1>
            <div>Academic Year: {{ $academicYear?->academic_year ?? 'All Academic Years' }}</div>@if($statisticsCampusTitle !== 'all Campuses')<div class="statistics-print-campus-line">Campus: {{ $statisticsCampusTitle }}</div>@endif
        </div>
    </div>
    <table class="statistics-print-table">
        <thead>
            <tr>
                <th colspan="2" class="statistics-print-campus-head">Campus</th>
                @foreach($statisticsLabels as $index => $label)<th class="statistics-print-grade-{{ $statisticsGroups[$index] ?? 'other' }}">{{ $label }}</th>@endforeach
                <th class="statistics-print-total-head">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($statistics['rows'] as $index => $row)
                <tr>
                    <td class="statistics-print-no">{{ $index + 1 }}</td>
                    <th class="statistics-print-campus">{{ $row['campus'] }}</th>
                    @foreach($row['cells'] as $cellIndex => $cell)<td class="statistics-print-grade-{{ $statisticsGroups[$cellIndex] ?? 'other' }}">@if((int) $cell !== 0)<div>{{ $cell }}</div><div class="statistics-print-new-note">New: {{ (int) ($row['newCells'][$cellIndex] ?? 0) }}</div>@endif</td>@endforeach
                    <td class="statistics-print-total-cell"><div>{{ $row['total'] }}</div><div class="statistics-print-new-note">New: {{ (int) ($row['new_total'] ?? 0) }}</div></td>
                </tr>
            @empty
                <tr><td colspan="{{ count($statistics['columns']) + 3 }}">No students found.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" rowspan="2" class="statistics-print-grand-label">Grand<br>Total</th>
                @foreach($statistics['columnTotals'] as $totalIndex => $total)<th class="statistics-print-grade-{{ $statisticsGroups[$totalIndex] ?? 'other' }}"><div>{{ $total }}</div><div class="statistics-print-new-note statistics-print-new-footer">New: {{ (int) ($statistics['columnNewTotals'][$totalIndex] ?? 0) }}</div></th>@endforeach
                <th rowspan="2" class="statistics-print-grand-total"><div>{{ $statistics['grandTotal'] }}</div><div class="statistics-print-new-note statistics-print-new-footer">New: {{ (int) ($statistics['grandNewTotal'] ?? 0) }}</div></th>
            </tr>
            <tr>
                <th colspan="4" class="statistics-print-grade-early statistics-print-group-total"><div>{{ $statisticsGroupTotals['early'] }}</div><div class="statistics-print-new-note statistics-print-new-footer">New: {{ $statisticsGroupNewTotals['early'] }}</div></th>
                <th colspan="6" class="statistics-print-grade-primary statistics-print-group-total"><div>{{ $statisticsGroupTotals['primary'] }}</div><div class="statistics-print-new-note statistics-print-new-footer">New: {{ $statisticsGroupNewTotals['primary'] }}</div></th>
                <th colspan="6" class="statistics-print-grade-secondary statistics-print-group-total"><div>{{ $statisticsGroupTotals['secondary'] }}</div><div class="statistics-print-new-note statistics-print-new-footer">New: {{ $statisticsGroupNewTotals['secondary'] }}</div></th>
            </tr>
        </tfoot>
    </table>
    <div class="statistics-print-footer">
        <div class="statistics-print-office">Registrar's Office</div>
        <div class="statistics-print-date">{{ $statisticsDate }}</div>
    </div>
</div>
<style>
    body[data-report-type="student-statistics"] { color:#172b4d; font-family: Arial, sans-serif; }
    body[data-report-type="student-statistics"] .toolbar { margin-bottom: 6px; }
    .statistics-print-page { width: 100%; padding: 0 2mm; }
    .statistics-print-header { position: relative; min-height: 34mm; display: flex; align-items: flex-start; justify-content: center; padding-left: 0; }
    .statistics-print-logo-wrap { position: absolute; left: 0; top: 0; width: 60mm; text-align: left; }
    .statistics-print-logo { max-width: 58mm; max-height: 29mm; object-fit: contain; }
    .statistics-print-title-wrap { text-align: center; padding-top: 21mm; color: #203864; width: 100%; }
    .statistics-print-title-wrap h1 { margin: 0 0 1mm; font-size: 20px; line-height: 1.1; font-weight: 800; }
    .statistics-print-title-wrap div { font-size: 18px; line-height: 1.1; }.statistics-print-campus-line{font-size:14px !important;margin-top:1mm}
    .statistics-print-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 1mm; color: #1f324d; }
    .statistics-print-table th, .statistics-print-table td { border: 1.25px solid #222; padding: 2px 2px; text-align: center; vertical-align: middle; font-size: 13px; line-height: 1.05; height: 7.2mm; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .statistics-print-table thead th { font-size: 16px; font-weight: 800; height: 8mm; }
    .statistics-print-no { width: 4mm; font-size: 10px !important; padding-left:1px !important; padding-right:1px !important; }
    .statistics-print-campus { width: 14mm; font-weight: 400; font-size: 12px !important; }
    .statistics-print-campus-head, .statistics-print-grand-label { background: #d9edf2; font-weight: 800; }
    .statistics-print-grade-early { background: #d9d7e3; }
    .statistics-print-grade-primary { background: #e2f0d9; }
    .statistics-print-grade-secondary { background: #fce4d6; }
    .statistics-print-grade-other { background: #f2f2f2; }
    .statistics-print-table tbody td.statistics-print-grade-early,
    .statistics-print-table tbody td.statistics-print-grade-primary,
    .statistics-print-table tbody td.statistics-print-grade-secondary,
    .statistics-print-table tbody td.statistics-print-grade-other { background: #f2f2f2; font-weight: 400; }
    .statistics-print-total-head, .statistics-print-total-cell, .statistics-print-grand-total { background: #b4c7e7; font-weight: 800; font-size: 12px !important; }
    .statistics-print-group-total { font-weight: 800; font-size: 15px !important; }
    .statistics-print-new-note { margin-top: .3mm; color: inherit; font-size: 7px; font-weight: 500; line-height: 1; white-space: nowrap; }
    .statistics-print-new-footer { color: inherit; }
    .statistics-print-footer { width: 58mm; margin-top: 2mm; border: 1px solid #9aa8ba; text-align: center; color:#203864; }
    .statistics-print-office { background:#203864; color:#fff; font-size:17px; font-weight:800; padding:2px 4px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .statistics-print-date { font-size:15px; padding:4px 4px; }
    body.is-pdf-export[data-report-type="student-statistics"] .statistics-print-header { min-height: 28mm !important; }
    body.is-pdf-export[data-report-type="student-statistics"] .statistics-print-title-wrap { padding-top: 18mm !important; }
    body.is-pdf-export[data-report-type="student-statistics"] .statistics-print-title-wrap h1 { font-size: 18px !important; }
    body.is-pdf-export[data-report-type="student-statistics"] .statistics-print-title-wrap div { font-size: 14px !important; }
    body.is-pdf-export[data-report-type="student-statistics"] .statistics-print-logo { max-width: 44mm !important; max-height: 22mm !important; }
    body.is-pdf-export[data-report-type="student-statistics"] .statistics-print-table th,
    body.is-pdf-export[data-report-type="student-statistics"] .statistics-print-table td {
        border-width: .45px !important;
        font-size: 8.5px !important;
        padding: 1px 1px !important;
        height: 5.4mm !important;
        line-height: 1 !important;
    }
    body.is-pdf-export[data-report-type="student-statistics"] .statistics-print-table thead th { font-size: 10px !important; height: 6mm !important; }
    body.is-pdf-export[data-report-type="student-statistics"] .statistics-print-total-head,
    body.is-pdf-export[data-report-type="student-statistics"] .statistics-print-total-cell,
    body.is-pdf-export[data-report-type="student-statistics"] .statistics-print-grand-total { font-size: 8.5px !important; }
    body.is-pdf-export[data-report-type="student-statistics"] .statistics-print-new-note { font-size: 5px !important; margin-top: .2mm !important; }
    body.is-pdf-export[data-report-type="student-statistics"] .statistics-print-group-total { font-size: 9px !important; }
    body.is-pdf-export[data-report-type="student-statistics"] .statistics-print-footer { margin-top: 1.5mm !important; }
    @media print { .statistics-print-page { padding:0; } }
</style>
@elseif($type === 'attendance-list')
<div class="attendance-print-page">
    @include('reports._table', ['attendancePrintLayout' => true])
</div>
<style>
    @page{size:A4 landscape;margin:7mm}
    body[data-report-type="attendance-list"]{color:#000;font-family:Arial,sans-serif;background:#fff}
    body[data-report-type="attendance-list"] .toolbar{margin-bottom:6px}
    .attendance-print-page{width:100%;padding:0}
    .attendance-print-page .attendance-teacher-report{min-width:0;width:100%;overflow:visible}
    .attendance-print-page .attendance-teacher-heading{display:grid;grid-template-columns:52mm 1fr 72mm;align-items:end;gap:3mm;margin:0 0 2.2mm}
    .attendance-print-page .attendance-teacher-logo-img{width:72mm;height:auto;max-height:42mm;object-fit:contain}
    .attendance-print-page .attendance-school-kh{font-size:7px}
    .attendance-print-page .attendance-school-en{font-size:5px}
    .attendance-print-page .attendance-office{font-size:15px;margin-top:1mm}
    .attendance-print-page .attendance-campus{font-size:13px}
    .attendance-print-page .attendance-title-kh{font-family:'Khmer OS Muol Light','Khmer OS Muol',serif !important;font-size:24px;font-weight:400 !important}
    .attendance-print-page .attendance-title-en{font-size:20px}
    .attendance-print-page .attendance-motto-kh{font-family:'Khmer OS Muol Light','Khmer OS Muol',serif !important;font-size:15px;line-height:1.25;font-weight:300 !important;white-space:nowrap}
    .attendance-print-page .attendance-motto-kh-line{display:block;margin-top:1.6mm}
    .attendance-print-page .attendance-motto-en{font-size:14px;line-height:1.1;font-weight:400;white-space:nowrap}
    .attendance-print-page .attendance-tacteing{font-size:22px}
    .attendance-print-page .attendance-info-lines{margin:0 22mm .2mm;gap:3mm;font-size:16px;align-items:end}
    .attendance-print-page .attendance-info-item{display:flex;flex-direction:column;align-items:stretch;text-align:left;white-space:nowrap}
    .attendance-print-page .attendance-info-kh{display:block;width:100%;text-align:left;font-family:'Khmer OS Siemreap','Khmer OS Siem Reap',serif !important;font-size:17px;font-weight:400;line-height:1.05;margin-bottom:.6mm}
    .attendance-print-page .attendance-info-en{display:flex;align-items:flex-end;gap:1mm;font-family:'Times New Roman',serif;font-size:21px;font-weight:800;line-height:1}
    .attendance-print-page .attendance-info-en strong{font-weight:800}
    .attendance-print-page .attendance-session-value{font-weight:800;margin-left:2mm}
    .attendance-print-page .attendance-blank{flex:1 1 auto;min-width:45mm;width:auto;border-bottom:1.4px solid #000;transform:translateY(-.7mm)}
    .attendance-print-page .attendance-note-line{font-family:var(--tblr-font-sans-serif,Arial),Arial,sans-serif;font-size:12px;margin:.2mm 0 1mm}
    .attendance-print-page .attendance-note-kh{font-family:'Khmer OS Siemreap','Khmer OS Siem Reap',serif !important}
    .attendance-print-page .reports-attendance-teacher-table{min-width:0;width:100%;font-size:9px;table-layout:fixed}
    .attendance-print-page .reports-attendance-teacher-table th,
    .attendance-print-page .reports-attendance-teacher-table td{border:.65px solid #000 !important;padding:.6px .5px !important;height:5.1mm;line-height:1.02}
    .attendance-print-page .reports-attendance-teacher-table thead th{background:#ffff99 !important;color:#000 !important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .attendance-print-page .reports-attendance-teacher-table .attendance-no-col{width:8mm}
    .attendance-print-page .reports-attendance-teacher-table .attendance-name-col{width:45mm}.attendance-print-page .reports-attendance-teacher-table td.attendance-name-col{padding-top:1mm !important;padding-bottom:1mm !important}
    .attendance-print-page .reports-attendance-teacher-table .attendance-id-col{width:15mm}
    .attendance-print-page .reports-attendance-teacher-table .attendance-vertical-col{width:6mm}
    .attendance-print-page .reports-attendance-teacher-table .attendance-day-col{width:auto}
    .attendance-print-page .reports-attendance-teacher-table .attendance-total-col{width:9mm}
    .attendance-print-page .attendance-name-kh{font-family:'Khmer OS Siemreap','Khmer OS Siem Reap',serif !important;font-size:10px;line-height:1.18;margin-bottom:.35mm}
    .attendance-print-page .attendance-name-en{font-size:10px;line-height:1.18;margin-top:.25mm}
    .attendance-print-page .attendance-teacher-footer{margin-top:1mm}
    .attendance-print-page .attendance-bottom-notes{font-size:11px;line-height:1.35;font-style:normal}
    .attendance-print-page .attendance-footer-note-kh{font-family:'Khmer OS Siemreap','Khmer OS Siem Reap',serif !important}
    .attendance-print-page .attendance-session-legend{font-size:9px;width:58mm}
    .attendance-print-page .attendance-session-legend td{padding:.3mm 1mm}
    .attendance-print-page .attendance-print-meta{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;font-size:9px;margin-top:.5mm}.attendance-print-page .attendance-print-page-number{text-align:center}.attendance-print-page .attendance-print-date{text-align:right}
    @media print{.attendance-print-page{padding:0}.attendance-print-page .reports-attendance-teacher-table tr{break-inside:avoid;break-after:auto}.attendance-print-page .attendance-teacher-footer{break-inside:avoid}}
</style>
@else
@php
    $days = 0;
@endphp
<header class="report-header">@if($branding?->report_logo_2_path)<img class="logo" src="{{ asset('storage/'.$branding->report_logo_2_path) }}" alt="School Logo 2">@endif<div class="motto">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ</div><h1>{{ $title }}</h1><p>{{ $academicYear?->academic_year ?? 'All Academic Years' }}</p></header><table class="report-table"><thead><tr><th>#</th><th>Student ID</th><th class="left">Full-Name</th><th>Gender</th><th>Academic Year</th><th>Campus</th><th>Grade</th><th>Class</th><th>Group</th>@if($type === 'student-contact-list')<th>Contact / Phone</th><th>Address</th>@elseif($type === 'score-list')@for($i=1;$i<=$filters['score_columns'];$i++)<th>Score {{ $i }}</th>@endfor<th>Total</th><th>Remarks</th>@endif</tr></thead><tbody>@forelse($enrollments as $index => $row)<tr><td>{{ $index + 1 }}</td><td>{{ $row->student?->student_id ?? '-' }}</td><td class="left">{{ $row->student?->full_name_en ?: $row->student?->full_name_kh }}</td><td>{{ strtoupper(substr((string) $row->student?->gender, 0, 1)) === 'F' ? 'F' : 'M' }}</td><td>{{ $row->academicYear?->academic_year }}</td><td>{{ $row->campus?->campus_name_en }}</td><td>{{ $row->grade?->grade }}</td><td>{{ $row->schoolClass?->class_name }}</td><td>{{ $row->session?->session_short_name ?? '-' }}</td>@if($type === 'student-contact-list')<td>{{ $row->student?->contacts?->pluck('contact_value')->filter()->join(', ') ?: $row->student?->home_phone ?: '-' }}</td><td>{{ $row->student?->current_address_en ?: '-' }}</td>@elseif($type === 'score-list')@for($i=1;$i<=$filters['score_columns'];$i++)<td></td>@endfor<td></td><td></td>@endif</tr>@empty<tr><td colspan="20">No students found.</td></tr>@endforelse</tbody></table>@endif
<style>
    .moeys-date-footer {
        width: 92mm;
        margin: 12px 0 0 auto;
        text-align: center;
        font-family: "Khmer OS Siemreap", "Khmer OS Siem Reap", sans-serif;
        font-size: 12px;
        line-height: 1.6;
    }
    .moeys-date-footer div { margin: 0; }
</style>

<style>.report-header .motto{position:absolute;top:0;right:0;width:62mm;text-align:center;margin:0;font-size:13px}.report-header .motto::after{font-size:13px}.a4-page .report-header{padding-top:30mm}.a4-page .report-header h2,.a4-page .report-header .small{display:none}.report-table .khmer-name{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif}</style>
<style>.report-table th{text-align:center !important}.report-table .khmer-name{text-align:left}html[lang="km"] .report-table th{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif !important}</style>
<style>.a4-page .report-header{border-bottom:0}.a4-page .signature{display:none}</style>
<style>.a4-page .report-table thead th:nth-child(4){font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif}.a4-page .report-table .khmer-name{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif}</style>

<style>.a4-page .report-header{padding-top:48mm}.a4-page .report-header .logo{top:22mm;left:50%;transform:translateX(-50%);max-width:28mm;max-height:20mm}</style>
<style>.report-header .tacteing-number{font-family:Tacteing,"Tacteing Khmer",sans-serif;font-size:13px;line-height:1.2;margin-top:2px}</style>
<style>.report-header .tacteing-number{font-size:32px !important;line-height:.8;margin-top:4px}</style>

<style>.a4-page .report-header .logo{left:0;transform:none}</style>
<style>.a4-page .report-header .tacteing-number{position:absolute;top:27mm;right:0;width:62mm;text-align:center;margin:0}</style>
<style>.a4-page .report-header .tacteing-number{top:24mm;line-height:.55;margin-top:0}</style>
<style>.a4-page .report-header .logo{top:48mm;left:0;transform:none}.a4-page .report-header h1,.a4-page .report-header p{position:relative;z-index:1;text-align:center}</style>
<style>.a4-page .report-header{min-height:85mm;padding-top:38mm}.a4-page .report-header .logo{top:38mm;max-width:56mm;max-height:40mm}.a4-page .report-table{margin-top:14mm}</style>

<style>.a4-page .report-header{min-height:82mm;padding:38mm 10mm 7px}.a4-page .report-header .logo{top:6mm;left:4mm;max-width:38mm;max-height:27mm}.a4-page .report-header .logo-caption{position:absolute;top:33mm;left:0;width:48mm;text-align:center;font-family:Arial,sans-serif;font-size:10px;line-height:1.25;font-weight:700}.a4-page .report-header .logo-school{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif;font-size:11px;margin-bottom:3px}.a4-page .report-header h1{font-size:20px;margin:0 0 5px}.a4-page .report-header p{font-size:14px;margin:0}.a4-page .report-table{margin-top:10mm}</style>
<style>.a4-page .report-header .logo-caption{display:none}.a4-page .report-table{margin-top:5px}</style>
<style>.a4-page .report-header{min-height:0;padding-bottom:5px}.a4-page .report-table{margin-top:5px}</style>
<style>.print-office-footer{margin-top:10px;text-align:center;font-family:Arial,sans-serif;font-size:12px;line-height:1.45}</style>
<style>.print-office-footer{text-align:left}</style>


<style>body[data-report-type="student-list"] .a4-page .report-table thead th{background:#f1f3f5 !important;background-color:#f1f3f5 !important;-webkit-print-color-adjust:exact;print-color-adjust:exact}body[data-report-type="student-list"] .a4-page .report-table{border:1px solid #9aa8ba}body[data-report-type="student-list"] .a4-page .report-table th:last-child,body[data-report-type="student-list"] .a4-page .report-table td:last-child{border-right:1px solid #9aa8ba !important}</style><style>body[data-report-type="student-contact-list"] .a4-page .report-table thead th{background:#f1f3f5 !important;background-color:#f1f3f5 !important;-webkit-print-color-adjust:exact;print-color-adjust:exact}body[data-report-type="student-contact-list"] .a4-page .report-table{border:1px solid #9aa8ba}body[data-report-type="student-contact-list"] .a4-page .report-table th:last-child,body[data-report-type="student-contact-list"] .a4-page .report-table td:last-child{border-right:1px solid #9aa8ba !important}</style>


<style>.a4-page .report-table th,.a4-page .report-table td{font-size:12px !important}</style>
<style>.report-header .motto::after{display:none !important}</style>
<style>.a4-page .report-header .moeys-title{font-family:"Khmer OS Muol Light","Khmer OS Siemreap",sans-serif;font-weight:300}</style>
<style>.a4-page .report-header .moeys-title,.a4-page .report-header .moeys-academic-year,.a4-page .report-header .moeys-campus{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif}.a4-page .report-header .moeys-title{font-family:"Khmer OS Muol Light","Khmer OS Siemreap",sans-serif;font-weight:300}.a4-page .report-header .moeys-academic-year,.a4-page .report-header .moeys-campus{font-size:14px}</style>




<style>html:not([lang="km"]) .report-header .motto::after{display:block !important}</style>
<style>.moeys-date-footer{margin-top:12px;text-align:left;font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif;font-size:12px;line-height:1.6}.moeys-date-footer div{margin:0}</style>
<style>
    .moeys-date-footer {
        width: 118mm !important;
        margin: 12px 0 0 auto !important;
        text-align: center !important;
        white-space: nowrap !important;
    }
    .moeys-date-footer div:last-child {
        font-family: "Khmer OS Muol Light", "Khmer OS Muol", serif;
    }
    .moeys-date-footer div:not(:last-child) {
        font-family: "Khmer OS Siem Reap", "Khmer OS Siemreap", sans-serif;
        white-space: nowrap !important;
    }
</style>
    <style>html[lang="km"] .a4-page .report-header .tacteing-number{position:absolute !important;top:11mm !important;right:0 !important;width:62mm !important;text-align:center !important;margin:0 !important;line-height:.55 !important}</style>
    
    <style>html[lang="km"] .report-header .motto{font-size:16px !important;line-height:1.35 !important}html[lang="km"] .report-table th{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif !important}html[lang="km"] .a4-page .report-table th,html[lang="km"] .a4-page .report-table td,html[lang="km"] .moeys-date-footer{font-size:15.5px !important;line-height:1.4 !important}html[lang="km"] .a4-page .report-table thead th{font-size:15.5px !important;padding-top:9px !important;padding-bottom:9px !important}html[lang="km"] .moeys-date-footer{margin-top:12px !important}</style>
    <style>html[lang="km"] .a4-page .report-table th:nth-child(2),html[lang="km"] .a4-page .report-table td:nth-child(2){width:27mm !important;max-width:27mm !important;white-space:nowrap !important}</style>
    @unless($isPdfMode)@vite('resources/js/reportsPrint.js')@endunless
<style>body[data-report-type="student-contact-list"] .contact-print-table th,body[data-report-type="student-contact-list"] .contact-print-table td{font-size:9.5px !important;padding:4px 3px !important}body[data-report-type="student-contact-list"] .contact-print-table .english-name{font-size:9px !important}body[data-report-type="student-contact-list"] .contact-print-table .khmer-name,body[data-report-type="student-contact-list"] .contact-print-table .khmer-name-header{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif}</style>
<style>body[data-report-type="student-list"] .a4-page .report-header h1{margin-top:-8px !important;margin-bottom:2px !important}body[data-report-type="student-list"] .a4-page .report-header p{margin-top:0 !important}</style><style>body[data-report-type="student-contact-list"] .a4-page .report-header h1{margin-top:-8px !important;margin-bottom:2px !important}body[data-report-type="student-contact-list"] .a4-page .report-header p{margin-top:0 !important}</style>
<style>body[data-report-type="student-list"] .a4-page .report-table th,body[data-report-type="student-list"] .a4-page .report-table td{padding:5px 6px !important;line-height:1.28 !important;font-size:11.5px !important}body[data-report-type="student-list"] .a4-page .report-table .english{font-size:10.5px !important;margin-top:2px !important;line-height:1.18 !important}body[data-report-type="student-list"] .a4-page .report-table .khmer{line-height:1.18 !important}body[data-report-type="student-list"] .a4-page .report-header .report-date{display:none !important}</style><style>body[data-report-type="student-list"] .student-list-date-footer{margin-top:8px;text-align:right;font-size:12px;font-family:Arial,sans-serif;color:#172b4d}</style><style>body[data-report-type="student-list"] .a4-page .report-table thead th{padding-top:7px !important;padding-bottom:7px !important;line-height:1.3 !important}</style><style>html[lang="km"] body[data-report-type="student-list"] .a4-page .report-header .moeys-academic-year,html[lang="km"] body[data-report-type="student-list"] .a4-page .report-header .moeys-campus{font-size:16px !important;line-height:1.15 !important;margin:0 !important}html[lang="km"] body[data-report-type="student-list"] .a4-page .report-header .moeys-title{margin-bottom:0 !important}html[lang="km"] body[data-report-type="student-list"] .a4-page .report-table tbody td{font-size:12px !important;line-height:1.25 !important}html[lang="km"] body[data-report-type="student-list"] .a4-page .report-table thead th{font-size:14px !important;line-height:1.25 !important}</style><style>body[data-report-type="student-contact-list"] .a4-page .report-table th,body[data-report-type="student-contact-list"] .a4-page .report-table td{padding:5px 6px !important;line-height:1.28 !important;font-size:11.5px !important}body[data-report-type="student-contact-list"] .a4-page .report-table .english{font-size:10.5px !important;margin-top:2px !important;line-height:1.18 !important}body[data-report-type="student-contact-list"] .a4-page .report-table .khmer{line-height:1.18 !important}body[data-report-type="student-contact-list"] .a4-page .report-header .report-date{display:none !important}</style><style>body[data-report-type="student-contact-list"] .student-list-date-footer{margin-top:8px;text-align:right;font-size:12px;font-family:Arial,sans-serif;color:#172b4d}</style><style>body[data-report-type="student-contact-list"] .a4-page .report-table thead th{padding-top:7px !important;padding-bottom:7px !important;line-height:1.3 !important}</style><style>html[lang="km"] body[data-report-type="student-contact-list"] .a4-page .report-header .moeys-academic-year,html[lang="km"] body[data-report-type="student-contact-list"] .a4-page .report-header .moeys-campus{font-size:16px !important;line-height:1.15 !important;margin:0 !important}html[lang="km"] body[data-report-type="student-contact-list"] .a4-page .report-header .moeys-title{margin-bottom:0 !important}html[lang="km"] body[data-report-type="student-contact-list"] .a4-page .report-table tbody td{font-size:12px !important;line-height:1.25 !important}html[lang="km"] body[data-report-type="student-contact-list"] .a4-page .report-table thead th{font-size:14px !important;line-height:1.25 !important}</style><style>body.is-pdf-export .toolbar{display:none !important}body.is-pdf-export .a4-page{min-height:0 !important;page-break-after:always}body.is-pdf-export .a4-page:last-child{page-break-after:auto}body.is-pdf-export .report-header{page-break-inside:avoid}body.is-pdf-export .report-table{page-break-inside:auto;table-layout:fixed;width:100%}body.is-pdf-export .report-table tr{page-break-inside:avoid;page-break-after:auto}body.is-pdf-export .report-table th,body.is-pdf-export .report-table td{overflow-wrap:break-word;word-wrap:break-word}body.is-pdf-export[data-report-type="student-contact-list"] .report-header{min-height:32mm !important;padding:22mm 3mm 4px !important}body.is-pdf-export[data-report-type="student-contact-list"] .report-header .logo{top:3mm !important;left:0 !important;max-width:26mm !important;max-height:18mm !important}body.is-pdf-export[data-report-type="student-contact-list"] .report-header .motto{top:3mm !important;right:0 !important;width:58mm !important;font-size:11px !important;line-height:1.15 !important}body.is-pdf-export[data-report-type="student-contact-list"] .report-header .motto::after{font-size:10px !important;line-height:1.15 !important}body.is-pdf-export[data-report-type="student-contact-list"] .report-header h1{font-size:17px !important;margin:0 0 2px !important}body.is-pdf-export[data-report-type="student-contact-list"] .report-header p{font-size:12px !important}body.is-pdf-export[data-report-type="student-contact-list"] .report-table{margin-top:4px !important;table-layout:fixed !important}body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table th,body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table td{font-size:7.2px !important;padding:2px 2px !important;line-height:1.1 !important}body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table .english-name{font-size:6.8px !important}body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table th:nth-child(1),body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table td:nth-child(1){width:7mm !important}body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table th:nth-child(2),body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table td:nth-child(2){width:17mm !important}body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table th:nth-child(3),body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table td:nth-child(3){width:29mm !important}body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table th:nth-child(4),body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table td:nth-child(4){width:28mm !important}body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table th:nth-child(5),body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table td:nth-child(5){width:9mm !important}body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table th:nth-child(6),body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table td:nth-child(6){width:10mm !important}body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table th:nth-child(7),body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table td:nth-child(7),body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table th:nth-child(8),body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table td:nth-child(8),body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table th:nth-child(9),body.is-pdf-export[data-report-type="student-contact-list"] .contact-print-table td:nth-child(9){width:26mm !important}body.is-pdf-export[data-report-type="student-contact-list"][data-report-print-format="moeys"] .contact-print-table th:nth-child(1),body.is-pdf-export[data-report-type="student-contact-list"][data-report-print-format="moeys"] .contact-print-table td:nth-child(1){width:8mm !important}body.is-pdf-export[data-report-type="student-contact-list"][data-report-print-format="moeys"] .contact-print-table th:nth-child(2),body.is-pdf-export[data-report-type="student-contact-list"][data-report-print-format="moeys"] .contact-print-table td:nth-child(2){width:22mm !important}body.is-pdf-export[data-report-type="student-contact-list"][data-report-print-format="moeys"] .contact-print-table th:nth-child(3),body.is-pdf-export[data-report-type="student-contact-list"][data-report-print-format="moeys"] .contact-print-table td:nth-child(3){width:46mm !important}body.is-pdf-export[data-report-type="student-contact-list"][data-report-print-format="moeys"] .contact-print-table th:nth-child(4),body.is-pdf-export[data-report-type="student-contact-list"][data-report-print-format="moeys"] .contact-print-table td:nth-child(4),body.is-pdf-export[data-report-type="student-contact-list"][data-report-print-format="moeys"] .contact-print-table th:nth-child(5),body.is-pdf-export[data-report-type="student-contact-list"][data-report-print-format="moeys"] .contact-print-table td:nth-child(5){width:12mm !important}body.is-pdf-export[data-report-type="student-contact-list"][data-report-print-format="moeys"] .contact-print-table th:nth-child(6),body.is-pdf-export[data-report-type="student-contact-list"][data-report-print-format="moeys"] .contact-print-table td:nth-child(6),body.is-pdf-export[data-report-type="student-contact-list"][data-report-print-format="moeys"] .contact-print-table th:nth-child(7),body.is-pdf-export[data-report-type="student-contact-list"][data-report-print-format="moeys"] .contact-print-table td:nth-child(7),body.is-pdf-export[data-report-type="student-contact-list"][data-report-print-format="moeys"] .contact-print-table th:nth-child(8),body.is-pdf-export[data-report-type="student-contact-list"][data-report-print-format="moeys"] .contact-print-table td:nth-child(8){width:31mm !important}body[data-report-type="student-contact-list"] .report-header .moeys-title{font-family:"Khmer OS Muol Light","Khmer OS Muol",serif !important;font-weight:400 !important}body[data-report-type="student-contact-list"] .report-header .moeys-academic-year{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif !important;font-weight:400 !important}body[data-report-type="student-list"] .report-header .moeys-title{font-family:"Khmer OS Muol Light","Khmer OS Muol",serif !important;font-weight:400 !important}body[data-report-type="student-list"] .report-header .moeys-academic-year{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif !important;font-weight:400 !important}body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table{table-layout:fixed !important;width:100% !important}body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table th,body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table td{font-size:10.5px !important;padding:3px 3px !important;line-height:1.2 !important}body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table th:nth-child(1),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table td:nth-child(1){width:8mm !important}body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table th:nth-child(2),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table td:nth-child(2){width:24mm !important}body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table th:nth-child(3),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table td:nth-child(3){width:76mm !important}body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table th:nth-child(4),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table td:nth-child(4),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table th:nth-child(5),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table td:nth-child(5),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table th:nth-child(6),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table td:nth-child(6){width:12mm !important}body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table th:nth-child(7),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="moeys"] .student-list-print-table td:nth-child(7){width:20mm !important}body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table{table-layout:fixed !important;width:100% !important}body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table th,body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table td{font-size:10px !important;padding:3px 4px !important;line-height:1.18 !important}body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table th:nth-child(1),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table td:nth-child(1){width:9mm !important}body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table th:nth-child(2),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table td:nth-child(2){width:24mm !important}body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table th:nth-child(3),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table td:nth-child(3),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table th:nth-child(4),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table td:nth-child(4){width:48mm !important}body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table th:nth-child(5),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table td:nth-child(5),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table th:nth-child(6),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table td:nth-child(6),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table th:nth-child(7),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table td:nth-child(7){width:12mm !important}body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table th:nth-child(8),body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table td:nth-child(8){width:22mm !important}body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table .english-name,body.is-pdf-export[data-report-type="student-list"][data-report-print-format="internal"] .student-list-print-table .khmer-name{text-align:left !important;word-break:normal !important;overflow-wrap:normal !important}</style></body></html>

