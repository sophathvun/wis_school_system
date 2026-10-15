<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>List of Outstanding Students</title>
    <style>
        @if(!$pdfExport)
        @font-face { font-family: scholarshipsiemreap; src: url('{{ asset('fonts/khmer/KhmerOSsiemreap.ttf') }}'); }
        @font-face { font-family: "Khmer OS Muol Light"; src: url('{{ asset('fonts/khmer/KhmerOSmuollight.ttf') }}'); font-weight: 300; }
        @endif
        @if(!$pdfExport) @page { size: 210mm 297mm; margin: 8mm; } @endif
        * { box-sizing: border-box; }
        body { margin: 0; font-family: dejavusans,Arial,sans-serif; font-size: 9pt; color: #000; }
        .khmer { font-family: scholarshipsiemreap,sans-serif; font-size: 11pt; }
        .national-title,.national-motto { @if($pdfExport) font-family: scholarshipmuol,sans-serif; @else font-family: "Khmer OS Muol Light",sans-serif; @endif font-weight: 300; }
        .national-title { font-size: 11pt; }
        .national-motto { font-size: 10pt; }
        .report-heading { width: 100%; border-collapse: collapse; margin-bottom: 1mm; }
        .report-heading td { padding: 0; border: 0; vertical-align: middle; }
        .school-logo { width: 65mm; max-height: 36mm; object-fit: contain; }
        .national-heading { width: 45%; text-align: center; font-size: 11pt; line-height: 1.45; }
        .report-heading td.national-heading { padding-right: 1.5mm; }
        .heading-spacer { width: 19%; }
        .heading-logo { width: 36%; vertical-align: bottom !important; }
        h1 { margin: 0; text-align: center; font-size: 16pt; line-height: 1.3; }
        .academic-year { text-align: center; font-size: 12pt; margin: 1mm 0; }
        .report-campus { text-align: center; font-size: 11pt; margin: 0 0 2mm; }
        .student-list { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .student-list th,.student-list td { border: .2mm solid #000; padding: 1mm .8mm; vertical-align: middle; line-height: 1.15; }
        .student-list th { background: #ffff99; text-align: center; font-weight: normal; height: 8.5mm; font-size: 9pt; }
        .student-list td { height: 8.4mm; overflow-wrap: anywhere; }
        .student-list .center { text-align: center; }
        .student-list .rank { font-weight: bold; }
        .student-list .name { font-size: 8.5pt; }
        .student-list th.khmer { font-size: 11pt; }
        .student-list .campus-group td { height: 7mm; background: #e8eef8; font-weight: bold; text-align: left; font-size: 10pt; }
        .student-list thead { display: table-header-group; }
        .student-list tr { page-break-inside: avoid; }
        .total-students { margin-top: 1.5mm; font-size: 10pt; }
        .signatures { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 3mm; page-break-inside: avoid; font-size: 10pt; }
        .signatures td { width: 33.333%; text-align: center; padding: 0 1mm; border: 0; vertical-align: top; }
        .signatures-campus td { width: 50%; }
        .signatures .signature-name { height: 24mm; vertical-align: bottom; font-weight: bold; font-family: scholarshipsiemreap,dejavusans,sans-serif; }
        .signature-position { display: block; font-size: 9pt; font-weight: normal; margin-top: 1mm; }
        @if(!$pdfExport)
        body { background: #e7e9ed; }
        .outstanding-page { position: relative; width: 210mm; height: 297mm; padding: 8mm; margin: 12px auto; background: #fff; }
        .report-page-number { position: absolute; bottom: 4mm; right: 8mm; font-size: 8pt; }
        .print-toolbar { padding: 12px; text-align: center; }
        .print-toolbar button { padding: 9px 24px; cursor: pointer; }
        @media print {
            body { background: #fff; }
            .print-toolbar { display: none; }
            .outstanding-page { width: 194mm; height: 280mm; padding: 0; margin: 0; break-after: page; page-break-after: always; }
            .outstanding-page:last-child { break-after: auto; page-break-after: auto; }
            .report-page-number { bottom: 0; right: 0; }
            .student-list th,.campus-group td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        @endif
    </style>
</head>
<body>
    @if(!$pdfExport)<div class="print-toolbar"><button type="button" onclick="window.print()">Print</button></div>@endif
    @php
        $sections=$reportSections??[['pages'=>$pages,'total'=>$rankSummary['total'],'campus'=>'','preparedBy'=>$preparedBy,'preparedDate'=>$reportDate,'approvedBy'=>'________________________','approvedDate'=>$reportDate]];
        $pageCount=array_sum(array_map(fn($section)=>count($section['pages']),$sections));
        $pageNumber=0;
    @endphp
    @foreach($sections as $section)
    @foreach($section['pages'] as $pageRows)
        @if($pdfExport && $pageNumber>0)<pagebreak />@endif
        @php($pageNumber++)
        <div class="outstanding-page">
            @if($loop->first)
                <table class="report-heading"><tr>
                    <td class="heading-logo">@if($logoSrc)<img src="{{ $logoSrc }}" alt="School Logo 1" class="school-logo">@endif</td>
                    <td class="heading-spacer"></td>
                    <td class="national-heading"><div class="national-title" lang="km">ព្រះរាជាណាចក្រកម្ពុជា</div><div class="national-motto" lang="km">ជាតិ សាសនា ព្រះមហាក្សត្រ</div><strong>KINGDOM OF CAMBODIA<br>NATION RELIGION KING</strong></td>
                </tr></table>
                <h1>List of Outstanding Students</h1>
                <div class="academic-year">Academic Year: {{ $year?:'—' }}</div>
                <div class="report-campus">{{ $campusLabel??'All Campuses' }}</div>
            @endif
            <table class="student-list">
                <colgroup><col style="width:4%"><col style="width:6%"><col style="width:24%"><col style="width:21.5%"><col style="width:12%"><col style="width:5.5%"><col style="width:6.5%"><col style="width:6.5%"><col style="width:7%"><col style="width:7%"></colgroup>
                <thead><tr><th colspan="2" style="width:10%">No.</th><th style="width:24%">Name</th><th style="width:21.5%" class="khmer" lang="km">គោត្តនាម-នាម</th><th style="width:12%">ID</th><th style="width:5.5%;font-size:7.5pt">Gender</th><th style="width:6.5%">Grade</th><th style="width:6.5%">Group</th><th style="width:7%">Rank</th><th style="width:7%">Campus</th></tr></thead>
                <tbody>
                    @forelse($pageRows as $row)
                        @if($row['show_campus_header']??false)<tr class="campus-group"><td colspan="10">Campus: {{ $row['campus'] }}</td></tr>@endif
                        <tr><td class="center">{{ $row['number'] }}</td><td class="center">{{ $row['campus_number']??$row['number'] }}</td><td class="name">{{ $row['name_en'] }}</td><td class="khmer" lang="km">{{ $row['name_kh'] }}</td><td class="center">{{ $row['id'] }}</td><td class="center">{{ $row['gender'] }}</td><td class="center">{{ $row['grade'] }}</td><td class="center">{{ $row['group']?:'—' }}</td><td class="center rank">{{ $row['rank'] }}</td><td class="center">{{ $row['campus'] }}</td></tr>
                    @empty
                        <tr><td colspan="10" class="center">No student ranks assigned for these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($loop->last)
                <div class="total-students">Total students: {{ number_format($section['total']) }}</div>
                @php($signatureRoles=($section['requiresVerifier']??false)?['prepared'=>'Prepared by','checked'=>'Checked and Verified by','approved'=>'Approved by']:['prepared'=>'Prepared by','approved'=>'Approved by'])
                <table class="signatures{{ count($signatureRoles)===2?' signatures-campus':'' }}">
                    <tr>@foreach($signatureRoles as $role=>$label)<td>Phnom Penh, {{ $section[$role.'Date']??$reportDate }}<br>{{ $label }}</td>@endforeach</tr>
                    <tr>@foreach($signatureRoles as $role=>$label)<td class="signature-name">{{ $section[$role.'By']??'________________________' }}<div class="signature-position">{{ $section[$role.'Position']??'' }}</div></td>@endforeach</tr>
                </table>
            @endif
            @if(!$pdfExport)<div class="report-page-number">Page {{ $pageNumber }} of {{ $pageCount }}</div>@endif
        </div>
    @endforeach
    @endforeach
</body>
</html>
