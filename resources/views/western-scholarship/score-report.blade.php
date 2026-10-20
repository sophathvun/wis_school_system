<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $title }}</title>
<style>
@if(!$pdfExport)@page{size:A4 landscape;margin:4mm 8mm 8mm}@endif
*{box-sizing:border-box}body{margin:0;background:#e5e7eb;color:#000;font-family:dejavusans,Arial,sans-serif;font-size:9pt}
.score-page{width:281mm;min-height:198mm;margin:8mm auto;background:white;page-break-after:always}.score-page:last-child{page-break-after:auto}
.heading{width:100%;border-collapse:collapse;table-layout:fixed}.heading td{border:0;vertical-align:middle;text-align:center;padding:0}.heading .logo{vertical-align:top;text-align:left;padding-bottom:.5mm}.logo img{display:block;width:65mm;height:auto;object-fit:contain}
.draft-watermark{font-size:28pt;line-height:1;font-weight:bold;letter-spacing:2pt;color:#b8c0cc}
h1{font-size:{{ $titleSize }}pt;line-height:1.15;color:#203c65;margin:0 0 .5mm}p{font-size:14pt;margin:.5mm 0;line-height:1.15}h1+p{margin-top:0}
.room{font-size:12pt;font-weight:bold;color:#203c65;margin:1mm 0 1mm}.room strong{font-size:16pt;color:#ed1c24}
.score-table{width:100%;border-collapse:collapse;table-layout:fixed}.score-table th,.score-table td{border:.25mm solid #000;padding:.6mm .7mm;font-size:8pt;line-height:1.15;text-align:center;overflow-wrap:anywhere;vertical-align:middle}
.score-table th{background:#d9e2f3;font-weight:normal;height:6mm}.score-table th.group{background:#ffdf6b;font-weight:bold;font-size:10pt}.score-table th.subject{background:#c6e0b4}.score-table th.total{background:#548235;color:#fff;font-weight:bold}
.score-table td{height:4.6mm;padding:.45mm .7mm}.score-table td.name{text-align:left}.score-table tr.alt td{background:#f2f2f2}.score-table .absent{color:#c00000}.score-table td.total{font-weight:bold}
.footer{font-size:8pt;margin:.5mm 0}.toolbar{position:sticky;top:0;z-index:5;background:#293959;color:#fff;padding:12px 20px;display:flex;gap:16px;align-items:center}.toolbar button{border:0;border-radius:6px;background:#fff;padding:9px 20px;cursor:pointer}
thead{display:table-header-group}tr{page-break-inside:avoid}th{-webkit-print-color-adjust:exact;print-color-adjust:exact}
@media print{body{background:#fff}.toolbar{display:none}.score-page{width:100%;margin:0;min-height:0}}
@if($pdfExport)body{background:#fff}.score-page{width:100%;margin:0;min-height:0;page-break-after:auto}@endif
</style></head><body>
@if(!$pdfExport)<div class="toolbar"><button type="button" onclick="window.print()">Print</button><span>{{ $title }}</span></div>@endif
@forelse($pages as $page)
@if($pdfExport&&!$loop->first)<pagebreak />@endif
<section class="score-page" data-score-report-room="{{ $page['room'] }}" data-score-report-center="{{ $page['center'] }}">
    <table class="heading" style="width:100%"><tr><td class="logo" style="width:241mm;vertical-align:top;text-align:left;padding-bottom:.5mm">@if($logoSrc)<img src="{{ $logoSrc }}" style="width:65mm;height:auto" alt="School Logo 1">@endif</td><td style="width:40mm;vertical-align:top;text-align:right">@if(!$exam->published_at)<span class="draft-watermark">DRAFT</span>@endif</td></tr><tr><td colspan="2" style="text-align:center"><h1>{{ $title }}</h1><p>Academic Year: {{ $page['year'] }}</p><p>Exam Date: {{ $page['exam_date'] }}</p><p>Exam Center: {{ $page['center'] }}</p></td></tr></table>
    <div class="room">Room: <strong>{{ $page['room'] }}</strong> &nbsp; | &nbsp; Grade: {{ $page['grade'] }}@if($page['part']>1) &nbsp; | &nbsp; Continued @endif</div>
    @php
        $widths=[3,4,4,13,4,4,4];$count=$page['subjects']->count()+1;$base=intdiv(24,$count);$remainder=24%$count;
    @endphp
    <table class="score-table"><colgroup>@foreach($widths as $width)<col style="width:{{ $width/60*100 }}%">@endforeach @foreach(range(0,$count-1) as $index)<col style="width:{{ ($base+($index<$remainder?1:0))/60*100 }}%">@endforeach</colgroup>
        <thead><tr>@foreach(['NO.','ROOM NO.','SEAT NO.','NAME','GENDER','GRADE','CAMPUS'] as $index=>$label)<th rowspan="2" style="width:{{ $widths[$index]/60*100 }}%">{{ $label }}</th>@endforeach<th colspan="{{ $count }}" class="group" style="width:40%">SCORES</th></tr>
        <tr>@foreach($page['subjects'] as $index=>$subject)<th class="subject" style="width:{{ ($base+($index<$remainder?1:0))/60*100 }}%">{{ $subject->name }}</th>@endforeach<th class="total" style="width:{{ ($base+($count-1<$remainder?1:0))/60*100 }}%">Total</th></tr></thead>
        <tbody>@foreach($page['students'] as $student)<tr class="{{ $student['number']%2?'':'alt' }}">
            <td>{{ $student['number'] }}</td><td>{{ $student['room'] }}</td><td>{{ $student['seat'] }}</td><td class="name">{{ $student['name'] }}</td><td>{{ $student['gender'] }}</td><td>{{ $student['grade'] }}</td><td>{{ $student['campus'] }}</td>
            @foreach($page['subjects'] as $subject)@php($score=$student['scores'][$subject->id])<td class="{{ $score['absent']?'absent':'' }}">{{ $score['absent']?'0 (A)':($score['value']===null?'—':number_format($score['value'],2)) }}</td>@endforeach
            <td class="total">{{ $student['total']===null?'—':number_format($student['total'],2) }}</td>
        </tr>@endforeach</tbody>
    </table>
    <div class="footer">Total Students: {{ $page['total'] }} &nbsp; | &nbsp; Female: {{ $page['female'] }}</div>
    <div class="footer">A = Absent (counts as 0) &nbsp; | &nbsp; — = Not entered &nbsp; | &nbsp; {{ $page['reviewed']?'Reviewed scores':'Draft / pending review' }}</div>
</section>
@empty<section class="score-page"><p>No saved scores match these filters.</p></section>@endforelse
@if(!$pdfExport&&count($pages))<script>window.addEventListener('load',async()=>{if(document.fonts)await document.fonts.ready;await Promise.all(Array.from(document.images,image=>image.decode?image.decode().catch(()=>{}):Promise.resolve()));requestAnimationFrame(()=>requestAnimationFrame(()=>window.print()));},{once:true});</script>@endif
</body></html>
