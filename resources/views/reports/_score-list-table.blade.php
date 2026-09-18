@php
    $scorePrintLayout = $scorePrintLayout ?? false;
    $scoreRows = collect($enrollments ?? []);
    $firstScoreRow = $scoreRows->first();
    $scoreGradeText = trim((string) ($firstScoreRow?->grade?->grade_short_name ?: $firstScoreRow?->grade?->grade ?: ''));
    $scoreClassText = trim((string) ($firstScoreRow?->schoolClass?->class_name ?? ''));
    $scoreGradeLabel = $firstScoreRow ? trim($scoreGradeText . $scoreClassText) : data_get(collect($gradeClassOptions ?? [])->firstWhere('value', $filters['grade_class'] ?? ''), 'label', '');
    $scoreQuarterLabels = ['quarter_1' => 'Quarter 1', 'quarter_2' => 'Quarter 2', 'quarter_3' => 'Quarter 3', 'quarter_4' => 'Quarter 4'];
    $scorePrintType = $scoreQuarterLabels[$filters['print_type'] ?? 'quarter_1'] ?? 'Quarter 1';
    $scoreCampusNameFromContext = isset($campus) ? ($campus?->campus_name_en ?? '') : '';
    $scoreCampusName = $firstScoreRow?->campus?->campus_name_en ?: $scoreCampusNameFromContext;
    $scoreBranding = $branding ?? null;
    $scoreLogo = $scoreBranding?->report_logo_1_path ? asset('storage/'.ltrim($scoreBranding->report_logo_1_path, '/')) : ($scoreBranding?->report_logo_2_path ? asset('storage/'.ltrim($scoreBranding->report_logo_2_path, '/')) : asset('storage/school_logo/wis_logo.png'));
    $scorePrintedDate = now('Asia/Phnom_Penh')->format('d-M-y');
    $scoreMinRows = $scorePrintLayout ? 25 : 0;
@endphp
<div class="score-list-report {{ $scorePrintLayout ? 'score-list-print-layout' : '' }}">
    @if($scorePrintLayout)
        <div class="score-list-heading">
            <div class="score-list-left">
                <img src="{{ $scoreLogo }}" alt="School Logo" class="score-list-logo">
                <div class="score-list-office">Registrar's Office</div>
                <div class="score-list-campus">{{ $scoreCampusName ?: '-' }}</div>
            </div>
            <div class="score-list-right">
                <div class="score-list-title"><span>&#x179F;&#x17C0;&#x179C;&#x1797;&#x17C5;&#x1796;&#x17B7;&#x1793;&#x17D2;&#x1791;&#x17BB;</span> / Score List</div>
                <div class="score-list-info-row"><strong>Teacher Name</strong><span></span></div>
                <div class="score-list-info-row"><strong>Subject</strong><span></span></div>
                <div class="score-list-info-row score-list-grade-row"><strong>Grade</strong><span class="score-list-grade-value">{{ $scoreGradeLabel ?: '-' }}</span><strong>For :</strong><span>{{ $scorePrintType }}</span></div>
            </div>
        </div>
    @else
        <div class="score-list-preview-title"><div class="score-list-preview-title-kh">&#x178F;&#x17B6;&#x179A;&#x17B6;&#x1784;&#x179F;&#x1798;&#x17D2;&#x179A;&#x1784;&#x17CB;&#x1796;&#x17B7;&#x1793;&#x17D2;&#x1791;&#x17BB;</div><div class="score-list-preview-title-en">Score List</div></div>
    @endif
    <table class="score-list-table">
        <thead>
            <tr>
                <th rowspan="2" class="score-no-col">N&ordm;</th>
                <th rowspan="2" class="score-student-name-col"><span class="score-kh-label">&#x1788;&#x17D2;&#x1798;&#x17C4;&#x17C7;</span><span>Name</span></th>
                <th rowspan="2" class="score-gender-col"><span class="score-kh-label">&#x1797;&#x17C1;&#x1791;</span><span>Gender</span></th>
                <th rowspan="2" class="score-group-col"><span class="score-kh-label">&#x1780;&#x17D2;&#x179A;&#x17BB;&#x1798;</span><span>Group</span></th>
                <th rowspan="2" class="score-conduct-col">Conduct</th>
                <th rowspan="2" class="score-cp-col">C.P.</th>
                <th colspan="6" class="score-section-col">&#x1780;&#x17B7;&#x1785;&#x17D2;&#x1785;&#x1780;&#x17B6;&#x179A;&#x1795;&#x17D2;&#x1791;&#x17C7; / Homework</th>
                <th colspan="6" class="score-section-col">&#x178F;&#x17C1;&#x179F;&#x17D2;&#x178F;&#x1781;&#x17D2;&#x179B;&#x17B8; / Quizzews</th>
                <th colspan="3" class="score-section-col"><span>&#x178F;&#x17C1;&#x179F;&#x17D2;&#x178F;&#x1794;&#x17D2;&#x179A;&#x1785;&#x17B6;&#x17C6;&#x1781;&#x17C2;</span>Monthly Test</th>
            </tr>
            <tr>
                @for($i = 1; $i <= 6; $i++)<th class="score-mark-col">{{ $i }}</th>@endfor
                @for($i = 1; $i <= 6; $i++)<th class="score-mark-col">{{ $i }}</th>@endfor
                @for($i = 1; $i <= 3; $i++)<th class="score-mark-col">{{ $i }}</th>@endfor
            </tr>
        </thead>
        <tbody>
            @foreach($scoreRows as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="score-student-name"><div class="score-name-kh">{{ $row->student?->full_name_kh ?: '-' }}</div><div class="score-name-en">{{ $row->student?->full_name_en ?: '-' }}</div></td>
                    <td>{{ strtoupper(substr((string) $row->student?->gender, 0, 1)) === 'F' ? 'F' : 'M' }}</td>
                    <td>{{ $row->session?->session_short_name ?: '-' }}</td>
                    <td></td><td></td>
                    @for($i = 1; $i <= 15; $i++)<td></td>@endfor
                </tr>
            @endforeach
            @for($empty = $scoreRows->count(); $empty < $scoreMinRows; $empty++)
                <tr><td></td><td></td><td></td><td></td><td></td><td></td>@for($i = 1; $i <= 15; $i++)<td></td>@endfor</tr>
            @endfor
            @if($scoreRows->isEmpty() && !$scorePrintLayout)
                <tr><td colspan="21" class="text-center text-secondary">No students found.</td></tr>
            @endif
        </tbody>
    </table>
    @if($scorePrintLayout)
        <div class="score-list-footer">
            <div class="score-list-note-kh">&#x1785;&#x17C6;&#x178E;&#x17B6;&#x17C6;&#x17D6; &#x1780;&#x17B6;&#x179A;&#x179A;&#x17C0;&#x1794;&#x1785;&#x17C6;&#x1794;&#x1789;&#x17D2;&#x1787;&#x17B8;&#x1796;&#x17B7;&#x1793;&#x17D2;&#x1791;&#x17BB;&#x1793;&#x17C1;&#x17C7;&#x1782;&#x17BA;&#x179F;&#x1798;&#x17D2;&#x179A;&#x17B6;&#x1794;&#x17CB;&#x179B;&#x17C4;&#x1780;&#x1782;&#x17D2;&#x179A;&#x17BC;&#x17A2;&#x17D2;&#x1793;&#x1780;&#x1782;&#x17D2;&#x179A;&#x17BC;&#x1780;&#x178F;&#x17CB;&#x178F;&#x17D2;&#x179A;&#x17B6;&#x1791;&#x17BB;&#x1780; &#x178A;&#x17BE;&#x1798;&#x17D2;&#x1794;&#x17B8;&#x1794;&#x1789;&#x17D2;&#x1785;&#x17BC;&#x179B;&#x1780;&#x17D2;&#x1793;&#x17BB;&#x1784;&#x179F;&#x17C0;&#x179C;&#x1797;&#x17C5;&#x1796;&#x17B7;&#x1793;&#x17D2;&#x1791;&#x17BB;&#x17A2;&#x17C1;&#x17A1;&#x17B7;&#x1785;&#x178F;&#x17D2;&#x179A;&#x17BC;&#x1793;&#x17B7;&#x1780; (E-Gradebook)&#x17D4;</div>
            <div class="score-list-note-en">* NOTE: This list is for teachers to keep record of all kinds of scores which is served as hard copies for egrade-book input.<br>The list is for teacher personal use. It is not required by the office.</div>
            <div class="score-list-date">Date: {{ $scorePrintedDate }}</div>
        </div>
    @endif
</div>
<style>
@font-face{font-family:'Khmer OS Siemreap';src:url('{{ asset("fonts/khmer/KhmerOSsiemreap.ttf") }}') format('truetype');font-weight:400;font-style:normal;font-display:swap}
@font-face{font-family:'Khmer OS Muol Light';src:url('{{ asset("fonts/khmer/KhmerOSmuollight.ttf") }}') format('truetype');font-weight:400;font-style:normal;font-display:swap}

.score-list-report{color:#000;font-family:Arial,Helvetica,sans-serif;min-width:1180px;overflow-x:auto}.score-list-heading{display:grid;grid-template-columns:36% 34%;justify-content:space-between;align-items:start;margin:2mm 0 4mm}.score-list-left{text-align:center;width:52mm}.score-list-logo{width:42mm;height:auto;object-fit:contain}.score-list-office{font-family:'Times New Roman',serif;font-size:18px;font-weight:800;line-height:1}.score-list-campus{font-family:'Times New Roman',serif;font-size:18px;font-weight:800}.score-list-right{padding-top:12mm}.score-list-title{background:#173b66;color:#fff;font-size:22px;font-weight:800;text-align:center;padding:2mm 4mm;margin-bottom:3mm;-webkit-print-color-adjust:exact;print-color-adjust:exact}.score-list-title span{font-family:'Khmer OS Muol Light','Khmer OS Muol',serif;font-weight:400}.score-list-info-row{display:grid;grid-template-columns:38mm 1fr;gap:4mm;align-items:end;font-size:19px;margin:1.6mm 0}.score-list-info-row span{border-bottom:1px dotted #000;min-height:6mm}.score-list-grade-row{grid-template-columns:38mm 25mm 14mm 1fr}.score-list-grade-value{text-align:center;border-bottom:1px dotted #000}.score-list-preview-title{text-align:center;margin:0 0 12px;color:#000}.score-list-preview-title-kh{font-family:'Khmer OS Muol Light','Khmer OS Muol',serif;font-size:24px;font-weight:400;line-height:1.45;white-space:nowrap;letter-spacing:.01em}.score-list-preview-title-en{font-size:18px;font-weight:700;line-height:1.25;margin-top:2px}.score-list-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:15px}.score-list-table th,.score-list-table td{border:1px solid #707782;text-align:center;vertical-align:middle;height:9mm;padding:2px 3px;line-height:1.08}.score-list-table thead th{background:var(--tblr-primary,#206bc4);color:#fff;font-weight:500;-webkit-print-color-adjust:exact;print-color-adjust:exact}.score-list-table tbody tr:nth-child(even) td{background:#ddebf7;-webkit-print-color-adjust:exact;print-color-adjust:exact}.score-no-col{width:9mm}.score-student-name-col{width:72mm}.score-gender-col{width:16mm}.score-group-col{width:16mm}.score-conduct-col{width:16mm}.score-cp-col{width:11mm}.score-mark-col{width:11.4mm}.score-kh-label,.score-section-col span{display:block;font-family:'Khmer OS Siemreap','Khmer OS Siem Reap',Arial,sans-serif;font-weight:500}.score-section-col{font-family:'Khmer OS Siemreap','Khmer OS Siem Reap',Arial,sans-serif}.score-student-name{text-align:left!important;padding:3px 5px!important}.score-name-en{font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.score-name-kh{font-family:'Khmer OS Siemreap','Khmer OS Siem Reap',serif;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px}.score-list-footer{display:grid;grid-template-columns:1fr 42mm;gap:4mm;margin-top:3mm;font-size:16px;line-height:1.25}.score-list-note-kh{font-family:'Khmer OS Siemreap','Khmer OS Siem Reap',serif}.score-list-note-en{grid-column:1;font-size:15px}.score-list-date{grid-column:2;grid-row:1 / span 2;align-self:start;text-align:right;font-size:18px}@media screen and (max-width:767px){.score-list-report{min-width:1180px;transform:scale(.34);transform-origin:top left;width:294%;margin-bottom:-60%}.score-list-table{font-size:12px}.score-name-en,.score-name-kh{font-size:12px}}
</style>


