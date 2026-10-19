<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $title }}</title>
<style>
@if(!$pdfExport)
@font-face{font-family:scholarshipmuol;src:url('{{ asset('fonts/khmer/KhmerOSmuollight.ttf') }}')}
@font-face{font-family:scholarshipsiemreap;src:url('{{ asset('fonts/khmer/KhmerOSsiemreap.ttf') }}')}
@page{size:A4 portrait;margin:8mm}

@endif

*{box-sizing:border-box}body{margin:0;color:#000;font-family:dejavusans,Arial,sans-serif;background:#e5e7eb;font-size:10pt}
.report-page{width:194mm;min-height:277mm;background:white;margin:8mm auto;padding:0;page-break-after:always}.report-page:last-child{page-break-after:auto}
.report-grid{width:100%;border-collapse:collapse;table-layout:fixed;margin-bottom:.35mm}.report-grid td{vertical-align:middle;padding:.7mm;line-height:1.15;overflow-wrap:anywhere}
.report-grid .line{margin:0;line-height:1.15}.report-grid img{object-fit:contain}.report-grid .label{padding:1.2mm}.report-grid .desk{padding:1mm}
.report-grid .candidate-table-head{-webkit-print-color-adjust:exact;print-color-adjust:exact}
.report-page.cover{border:1mm solid #203c65;padding:4mm}.report-page.room-plan{border:.8mm solid #203c65;padding:2mm}
/* A4 has 281 mm between its 8 mm margins; mPDF adds the 8 mm vertical padding to height. */
.report-page.attendance-cover{border-radius:15px;height:{{ $pdfExport?273:281 }}mm}
.report-toolbar{position:sticky;top:0;padding:12px 20px;background:#293959;color:white;display:flex;gap:16px;align-items:center;z-index:10}.report-toolbar button{background:white;border:0;border-radius:6px;padding:9px 20px;cursor:pointer}
.page-number{text-align:right;font-size:8pt;padding:2mm 0}.empty{padding:30mm;text-align:center}
@media print{body{background:white}.report-toolbar{display:none}.report-page{margin:0;min-height:0;width:100%}.page-number{display:none}}
@if($pdfExport)body{background:white}.report-page{margin:0;width:100%;min-height:0;page-break-after:auto}.page-number{display:none}
@endif

</style></head><body>
@if(!$pdfExport)<div class="report-toolbar"><button onclick="window.print()" type="button">Print</button><span>{{ $title }}</span></div>
@endif

@php
    $styles=\App\Support\ScholarshipSeatReports::styles();
@endphp
@forelse($pages as $page)
@if($pdfExport && !$loop->first)<pagebreak />
@endif

<section class="report-page {{ in_array($document,['attendance-cover','envelope-cover'],true)?'cover':($document==='room-plan'?'room-plan':'') }} {{ $document==='attendance-cover'?'attendance-cover':'' }}" data-report-room="{{ $page['room'] }}" data-report-center="{{ $page['center'] }}">
@php
    $tables=[];$profile=null;
    foreach($page['rows'] as $reportRow){
        $widths=array_column($reportRow['cells'],'span');
        if($widths!==$profile){$tables[]=[];$profile=$widths;}
        $tables[array_key_last($tables)][]=$reportRow;
    }
@endphp
@foreach($tables as $table)
<table class="report-grid">
    <colgroup>
    @foreach($table[0]['cells'] as $cell)
        <col style="width:{{ $cell['span']/60*100 }}%">
    @endforeach
    </colgroup>
    <tbody>
    @foreach($table as $row)
    <tr>
        @foreach($row['cells'] as $cell)
        @php
            $style=$styles[$cell['style']];
            $border=isset($style['border'])?(($cell['style']==='label'?'.6mm':'.25mm').' solid '.$style['border']):'0';
            $alignment=($cell['logo']??false) && $cell['style']==='body'?'left':$style['align'];
        @endphp
        <td class="{{ $cell['style'] }}" @if(isset($cell['role'])) data-report-role="{{ $cell['role'] }}" @endif style="width:{{ $cell['span']/60*100 }}%;height:{{ $row['height'] }}mm;text-align:{{ $alignment }};font-size:{{ $style['size'] }}pt;font-weight:{{ ($style['bold']??false)?'bold':'normal' }};color:{{ $style['color']??'#000' }};background:{{ $style['fill']??'#fff' }};border:{{ $border }};@if(isset($cell['padding_y']))padding-top:{{ $cell['padding_y'] }}mm;padding-bottom:{{ $cell['padding_y'] }}mm;@endif @if(isset($cell['offset_y']))padding-top:{{ max(0,$cell['offset_y']*2) }}mm;padding-bottom:{{ max(0,-$cell['offset_y']*2) }}mm;@endif">
            @if(($cell['logo']??false) && $logoSrc)
                <img src="{{ $pdfExport?$logoSrc:($logoUrl??$logoSrc) }}" style="width:{{ $cell['logo_width'] }}mm;max-height:{{ max(6,$row['height']-2) }}mm;@if(isset($cell['logo_offset_y']))margin-top:{{ $cell['logo_offset_y'] }}mm;margin-bottom:-{{ $cell['logo_offset_y'] }}mm;@endif" alt="Western International School">
            @endif
            @foreach($cell['lines'] as $line)
                @if($line['text']!=='')
                @php
                    $lineFont=($line['font']??'')==='muol'?'scholarshipmuol':(preg_match('/\p{Khmer}/u',(string)$line['text'])?'scholarshipsiemreap':null);
                @endphp
                <div class="line" style="font-size:{{ $line['size']??$style['size'] }}pt;font-weight:{{ ($line['bold']??$style['bold']??false)?'bold':'normal' }};color:{{ $line['color']??$style['color']??'#000' }};{{ $lineFont?'font-family:'.$lineFont.',sans-serif;':'' }}@if(isset($line['space_after']))margin-bottom:{{ $line['space_after'] }}mm;@endif">{{ $line['text'] }}</div>
                @endif
            @endforeach
        </td>
        @endforeach
    </tr>
    @endforeach
    </tbody>
</table>
@endforeach
@if(!$pdfExport)<div class="page-number">Page {{ $loop->iteration }} of {{ count($pages) }}</div>
@endif

</section>
@empty<div class="report-page empty">No confirmed candidates available.</div>@endforelse
@if(!$pdfExport && in_array($document,['candidates','attendance'],true) && count($pages))
<script>
window.addEventListener('load',async()=>{
    if(document.fonts)await document.fonts.ready;
    await Promise.all(Array.from(document.images,image=>image.decode?image.decode().catch(()=>{}):Promise.resolve()));
    requestAnimationFrame(()=>requestAnimationFrame(()=>window.print()));
},{once:true});
</script>
@endif
</body></html>
