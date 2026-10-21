<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $title }}</title>
<style>
@if(!$pdfExport)@page{size:A4 landscape;margin:8mm}@endif
*{box-sizing:border-box}body{margin:0;background:#e5e7eb;color:#000;font-family:dejavusans,Arial,sans-serif;font-size:10pt}
.result-page{width:281mm;min-height:194mm;margin:8mm auto;background:white;page-break-after:always}.result-page:last-child{page-break-after:auto}
.heading{width:100%;border-collapse:collapse;table-layout:fixed}.heading td{border:0;vertical-align:middle;text-align:center;padding:0}
.heading .logo{vertical-align:top;text-align:left;padding-bottom:3mm}.logo img{display:block;width:70mm;height:auto;max-width:100%}
h1{font-size:22pt;color:#203c65;line-height:1.15;margin:0 0 3mm}.meta{font-size:14pt;color:#203c65;margin:1.5mm 0;line-height:1.15}.grade{font-size:16pt;color:#203c65;font-weight:bold;text-align:center;margin:3mm 0}
.results-frame{padding:0 .5mm}.results{width:100%;border:.35mm solid #000;border-collapse:collapse;table-layout:fixed}.results th,.results td{border:.25mm solid #000;font-size:12pt;line-height:1.15;text-align:center;vertical-align:middle;padding:.6mm .7mm;overflow-wrap:anywhere}.results th{background:#d9e2f3;font-weight:normal;height:10mm}.results td{height:6.5mm}.results .name{text-align:left}.results tr.alt td{background:#f3f4f6}
.total{font-size:9pt;margin:1.5mm 0}.signatures{width:100%;border-collapse:collapse;table-layout:fixed;margin-top:5mm}.signatures td{width:33.333%;text-align:center;vertical-align:top;padding:0 1mm;font-size:11pt}.signed-date{margin:0 0 1.5mm}.signature-label{margin:0}.signature-space{height:17mm}.signer-name{font-weight:bold;margin:0 0 1mm}.position{margin:0}
.heading .draft{text-align:right;vertical-align:top}.draft{color:#b8c0cc;font-size:24pt;font-weight:bold;text-align:right}.toolbar{position:sticky;top:0;background:#293959;color:white;padding:12px 20px;display:flex;gap:16px;align-items:center}.toolbar button{border:0;border-radius:6px;padding:9px 20px;background:white;cursor:pointer}
thead{display:table-header-group}tr{page-break-inside:avoid}th{-webkit-print-color-adjust:exact;print-color-adjust:exact}
@media print{body{background:white}.toolbar{display:none}.result-page{width:100%;min-height:0;margin:0}}
@if($pdfExport)body{background:white}.result-page{width:100%;min-height:0;margin:0;page-break-after:auto}@endif
</style></head><body>
@if(!$pdfExport)<div class="toolbar"><button type="button" onclick="window.print()">Print</button><span>{{ $title }}</span></div>@endif
@foreach($pages as $page)
<section class="result-page" data-result-report-grade="{{ $page['grade'] }}">
    <table class="heading" style="width:100%"><tr><td class="logo" style="width:241mm">@if($logoSrc)<img src="{{ $logoSrc }}" alt="School Logo 1" style="width:70mm;height:auto">@endif</td><td class="draft" style="width:40mm">@if(!$exam->published_at&&!$exam->isHistorical())<span>DRAFT</span>@endif</td></tr><tr><td colspan="2"><h1>{{ $title }}</h1><p class="meta">Academic Year: {{ $page['year'] }}</p><p class="meta">Exam Date: {{ $page['exam_date'] }}</p></td></tr></table>
    <div class="grade">Grade: {{ $page['grade'] }}@if($page['part']>1) · Continued @endif</div>
    <div class="results-frame"><table class="results"><thead><tr>@foreach($headers as $index=>$header)<th style="width:{{ $widths[$index]/60*100 }}%">{{ $header }}</th>@endforeach</tr></thead><tbody>
        @foreach($page['students'] as $student)<tr class="{{ $student['number']%2?'':'alt' }}">@foreach($student as $key=>$value)<td class="{{ $key==='name'?'name':'' }}">{{ $value }}</td>@endforeach</tr>@endforeach
    </tbody></table></div>
    <p class="total">Grade {{ $page['grade'] }}, Total Students: {{ $page['total'] }} | Female: {{ $page['female'] }}</p>
    @if($page['signers'])<table class="signatures">
        <tr>@foreach($page['signers'] as $signer)<td><p class="signed-date">{{ $signer['date'] }}</p></td>@endforeach</tr>
        <tr>@foreach($page['signers'] as $signer)<td><p class="signature-label">{{ $signer['label'] }}</p></td>@endforeach</tr>
        <tr>@foreach($page['signers'] as $signer)<td style="height:17mm;font-size:1pt">&nbsp;</td>@endforeach</tr>
        <tr>@foreach($page['signers'] as $signer)<td><p class="signer-name">{{ $signer['name'] }}</p></td>@endforeach</tr>
        <tr>@foreach($page['signers'] as $signer)<td><p class="position">{{ $signer['position'] }}</p></td>@endforeach</tr>
    </table>@endif
</section>
@endforeach
@if(!$pdfExport&&count($pages))<script>window.addEventListener('load',async()=>{if(document.fonts)await document.fonts.ready;await Promise.all(Array.from(document.images,image=>image.decode?image.decode().catch(()=>{}):Promise.resolve()));requestAnimationFrame(()=>requestAnimationFrame(()=>window.print()));},{once:true});</script>@endif
</body></html>
