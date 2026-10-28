<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>G12 National Exam - List of Candidates</title>
    <style>
        @if($khmerFontData)
        @font-face{font-family:G12ReportKhmer;src:url('{{ $khmerFontData }}') format('truetype');font-weight:400;font-style:normal}
        @endif
        @page{size:A4 landscape;margin:9mm}
        *{box-sizing:border-box}
        body{margin:0;color:#172033;background:#eef2f7;font-family:Arial,G12ReportKhmer,sans-serif;font-size:7.2pt;-webkit-print-color-adjust:exact;print-color-adjust:exact}
        .report-toolbar{position:sticky;top:0;z-index:2;padding:10px 16px;background:#27395b;box-shadow:0 2px 10px #0002}
        .report-toolbar button{padding:8px 18px;border:1px solid #fff;border-radius:7px;color:#27395b;background:#fff;font-weight:700;cursor:pointer}
        .report-paper{width:279mm;min-height:190mm;margin:14px auto;padding:0;background:#fff}
        .report-heading{position:relative;min-height:24mm;margin-bottom:3mm;text-align:center}
        .report-logo{position:absolute;top:0;left:0;width:57mm;max-height:22mm;object-fit:contain;object-position:left top}
        .report-title{margin:0;padding-top:2mm;font-size:17pt;line-height:1.15;color:#203864}
        .report-subtitle{margin:1mm 0 0;font-size:10pt;font-weight:600}
        .report-meta{display:flex;justify-content:center;flex-wrap:wrap;gap:1mm 5mm;margin-top:2mm;color:#475569;font-size:7.3pt}
        .report-meta strong{color:#172033}
        .candidate-table{width:100%;border-collapse:collapse;table-layout:fixed}
        .candidate-table th,.candidate-table td{border:.2mm solid #9aa6b2;padding:1.2mm;vertical-align:top;overflow-wrap:anywhere}
        .candidate-table th{color:#fff;background:#3f78cc;text-align:center;font-size:7pt;font-weight:700}
        .candidate-table thead{display:table-header-group}
        .candidate-table tr{page-break-inside:avoid}
        .candidate-table .col-number{width:4%;text-align:center}
        .candidate-table .col-photo{width:7%;text-align:center}
        .candidate-table .col-student{width:18%}
        .candidate-table .col-grade{width:11%}
        .candidate-table .col-parent{width:18%}
        .candidate-table .col-g9{width:18%}
        .candidate-table .col-g12{width:24%}
        .candidate-photo{width:11mm;height:14.67mm;border-radius:1mm;object-fit:cover}
        .kh{font-family:G12ReportKhmer,Arial,sans-serif}
        .student-kh{font-size:8.5pt;line-height:1.4}
        .student-en{margin-top:.4mm;font-size:7pt}
        .secondary{margin-top:.5mm;color:#4b5563;font-size:6.5pt;line-height:1.35}
        .detail-line{margin-bottom:.45mm;line-height:1.4}
        .detail-line:last-child{margin-bottom:0}
        .detail-pair{white-space:nowrap}
        .detail-pair+.detail-pair{margin-left:2mm;padding-left:2mm;border-left:.2mm solid #c7ced6}
        .status-badge,.grade-badge{display:inline-block;padding:.35mm 1.2mm;border-radius:2mm;font-size:6.3pt;font-weight:700}
        .status-passed,.grade-a{color:#166534;background:#dcfce7}
        .status-failed,.grade-f{color:#991b1b;background:#fee2e2}
        .status-pending,.grade-pending{color:#475569;background:#e2e8f0}
        .grade-b{color:#6d28d9;background:#ede9fe}.grade-c{color:#1d4ed8;background:#dbeafe}.grade-d{color:#b45309;background:#fef3c7}.grade-e{color:#c2410c;background:#ffedd5}
        .report-empty{padding:8mm!important;text-align:center;color:#64748b}
        .report-footer{display:flex;justify-content:space-between;margin-top:2.5mm;color:#64748b;font-size:6.5pt}
        @media print{body{background:#fff}.report-toolbar{display:none}.report-paper{width:auto;min-height:0;margin:0}}
    </style>
</head>
<body>
@unless($pdfExport)
<div class="report-toolbar"><button type="button" onclick="window.print()">Print A4</button></div>
@endunless
<main class="report-paper">
    <header class="report-heading">
        @if($reportLogo)<img class="report-logo" src="{{ $reportLogo }}" alt="School Logo 1">@endif
        <h1 class="report-title">List of Candidates</h1>
        <p class="report-subtitle">G12 National Exam</p>
        <div class="report-meta">
            <span>Academic Year: <strong>{{ $filters['academic_year']??'All' }}</strong></span>
            <span>Campus: <strong>{{ $reportCampus?->campus_name_en??'All Campuses' }}</strong></span>
            @if(filled($filters['grade']??null))<span>Grade: <strong>{{ $filters['grade'] }}</strong></span>@endif
            @if(filled($filters['language_exam']??null))<span>Exam Language: <strong>{{ $filters['language_exam'] }}</strong></span>@endif
            @if(filled($filters['class_selected']??null))<span>Selected Class: <strong>{{ $filters['class_selected'] }}</strong></span>@endif
            <span>Total: <strong>{{ $records->count() }}</strong></span>
        </div>
    </header>
    <table class="candidate-table">
        <thead><tr><th class="col-number">No.</th><th class="col-photo">Photo</th><th class="col-student">Student</th><th class="col-grade">Grade</th><th class="col-parent">Parents</th><th class="col-g9">G9 National Exams</th><th class="col-g12">G12 National Exams</th></tr></thead>
        <tbody>
        @forelse($records as $record)
            @php
                $data=$record->data??[];
                $campus=$record->campus?->campus_name_en??'';
                $grade=(string)($record->grade??'');
                $class=$campus&&$grade&&!str_starts_with(mb_strtolower($grade),mb_strtolower($campus).'-')?$campus.'-'.$grade:($grade?:$campus);
                $result=mb_strtolower((string)$record->result_status);
                $letter=mb_strtoupper(trim((string)$record->overall_grade));
                $letterKey=mb_strtolower(mb_substr($letter,0,1));
                $score=(string)($data['g12_score_rank']??'');
                $score=preg_replace_callback('/-?\d+(?:\.\d+)?/',fn($match)=>str_contains($match[0],'.')?number_format((float)$match[0],3,'.',''):$match[0],$score);
            @endphp
            <tr>
                <td class="col-number">{{ $loop->iteration }}</td>
                <td class="col-photo">@if($photoUrls[$record->id]??null)<img class="candidate-photo" src="{{ $photoUrls[$record->id] }}" alt="Student photo">@else &mdash; @endif</td>
                <td class="col-student"><div class="student-kh kh">{{ $record->name_kh?:'—' }}</div><div class="student-en">{{ $record->name_en?:'—' }}</div><div class="secondary">ID: {{ $record->student_code }}<br>DOB: {{ $data['dob']??'—' }}</div></td>
                <td class="col-grade"><strong>{{ $record->academic_year }}</strong><div class="secondary">{{ $class?:'—' }}</div></td>
                <td class="col-parent kh"><div class="detail-line">ម្តាយ៖ {{ $data['mother_name']??'—' }}</div><div class="secondary">{{ $data['mother_occupation']??'—' }}</div><div class="detail-line" style="margin-top:1mm">ឪពុក៖ {{ $data['father_name']??'—' }}</div><div class="secondary">{{ $data['father_occupation']??'—' }}</div></td>
                <td class="col-g9 kh"><div class="detail-line">{{ $data['g9_exam_center']??'—' }}</div><div class="secondary">{{ $data['g9_exam_date']??'—' }}</div><div class="secondary"><span class="detail-pair">បន្ទប់លេខៈ {{ $data['g9_exam_room']??'—' }}</span><span class="detail-pair">តុលេខៈ {{ $data['g9_exam_seat']??'—' }}</span></div></td>
                <td class="col-g12 kh"><div class="detail-line"><span class="detail-pair">ភាសាប្រឡង៖ {{ $data['language_exam']??'—' }}</span><span class="detail-pair">ថ្នាក់៖ {{ $data['class_sellected']??$data['class_selected']??'—' }}</span></div><div class="detail-line">មណ្ឌល៖ {{ $record->center?->name??'—' }}</div><div class="secondary">សម័យប្រឡង៖ {{ $data['g12_exam_date']??'—' }}</div><div class="secondary"><span class="detail-pair">លេខបន្ទប់៖ {{ $data['g12_exam_room']??'—' }}</span><span class="detail-pair">លេខតុ៖ {{ $data['g12_exam_seat']??'—' }}</span></div><div style="margin-top:1mm"><span class="status-badge status-{{ in_array($result,['passed','failed'],true)?$result:'pending' }}">{{ ucfirst($result?:'pending') }}</span> <span class="grade-badge grade-{{ in_array($letterKey,['a','b','c','d','e','f'],true)?$letterKey:'pending' }}">{{ $letter?:'—' }}</span>@if(filled($score)) <span class="grade-badge grade-c">{{ $score }}</span>@endif</div></td>
            </tr>
        @empty
            <tr><td class="report-empty" colspan="7">No candidates match the selected filters.</td></tr>
        @endforelse
        </tbody>
    </table>
    <footer class="report-footer"><span>School Logo 1 · G12 National Exam</span><span>Generated {{ $reportDate->format('d M Y, H:i') }}</span></footer>
</main>
@unless($pdfExport)<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),150));</script>@endunless
</body>
</html>
