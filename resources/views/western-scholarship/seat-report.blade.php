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
.report-grid.seat-stickers{border-collapse:separate;border-spacing:2mm 0}
.seat-stickers .label{padding:.8mm}
.seat-stickers .label .line{line-height:1}
.seat-stickers .label img{display:block;margin:0 auto}
.sticker-heading{width:100%;border-collapse:collapse;table-layout:fixed;margin:0}
.sticker-heading td{border:0;padding:0;vertical-align:top;height:10mm}
.sticker-heading .sticker-logo img{margin:0 auto}
.seat-sticker-page [data-report-role="school-logo"]{vertical-align:bottom;padding-left:.7mm}
.seat-sticker-page [data-report-role="school-logo"] img{display:block;object-position:left bottom}
.seat-sticker-page [data-report-role="title"]{vertical-align:top}
.seat-sticker-page [data-report-role="sticker-subtitle"] .line,.seat-sticker-page [data-report-role="sticker-subtitle"] .line span{white-space:nowrap!important;overflow-wrap:normal}
.sticker-header-card{border:1px solid #000;border-radius:10px;padding:3mm;margin:0 3mm;background:#fff}
.sticker-header-card>.report-grid{margin-bottom:0}
.report-grid.envelope-contacts{border-collapse:separate;border-spacing:0}
.envelope-contacts .contact-left{border-right-width:0!important}
.envelope-contacts .cover-table-body{border-top-width:0!important}
.envelope-contacts .cover-table-head.contact-left{border-top-left-radius:15px}
.envelope-contacts .cover-table-head.contact-right{border-top-right-radius:15px}
.envelope-contacts tr:last-child .cover-table-body.contact-left{border-bottom-left-radius:15px}
.envelope-contacts tr:last-child .cover-table-body.contact-right{border-bottom-right-radius:15px}
.report-page.cover{border:1mm solid #203c65;padding:4mm}
.classroom-plan{border:.8mm solid #203c65;border-radius:15px;padding:2mm}
.room-plan .seat-plan-heading{margin-bottom:0}
.room-plan [data-report-role="seat-plan-label"]{padding:0!important;vertical-align:bottom}
.room-plan [data-report-role="seat-plan-label"] .line{width:150px;margin-left:auto;margin-right:auto;border:.6mm solid #203c65;border-radius:10px 10px 0 0;padding:2mm 0;font-size:14pt!important;white-space:nowrap}
.classroom-plan .report-grid{border-collapse:separate;border-spacing:0}
.classroom-plan .desk,.classroom-plan .empty-desk{border-radius:15px}
.classroom-plan .classroom-symbol img{display:block;margin:0 auto 1mm}
.classroom-plan [data-report-role="classroom-whiteboard"] img{object-fit:fill;max-width:100%}
/* A4 has 281 mm between its 8 mm margins; mPDF adds the 8 mm vertical padding to height. */
.report-page.attendance-cover,.report-page.envelope-cover{border-radius:15px;height:{{ $pdfExport?273:281 }}mm}
.report-page.envelope-cover{padding-left:0;padding-right:0}
.envelope-cover>.report-grid{width:{{ $pdfExport?'96%':'calc(100% - 8mm)' }};margin-left:auto;margin-right:auto}
.envelope-cover>.envelope-committee{width:100%}
.envelope-committee .committee .line{margin-left:0;text-align:center}
.envelope-cover .cover-detail .line{margin-left:10mm}
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
    $roomSymbols=[];
    if($document==='room-plan')foreach(['whiteboard'] as $symbol){
        $roomSymbols[$symbol]='data:image/svg+xml;base64,'.base64_encode(file_get_contents(public_path('images/scholarship/'.$symbol.'.svg')));
    }
@endphp
@forelse($pages as $page)
@if($pdfExport && !$loop->first)<pagebreak />
@endif

<section class="report-page {{ in_array($document,['attendance-cover','envelope-cover'],true)?'cover':($document==='room-plan'?'room-plan':'') }} {{ in_array($document,['attendance-cover','envelope-cover'],true)?$document:($document==='seat-labels'?'seat-sticker-page':'') }}" data-report-room="{{ $page['room'] }}" data-report-center="{{ $page['center'] }}">
@php
    $tables=[];$profile=null;$lastCommittee=false;$lastArea=null;
    foreach($page['rows'] as $reportRow){
        $widths=array_column($reportRow['cells'],'span');
        if($document==='seat-labels' && ($reportRow['cells'][0]['role']??'')==='sticker-subtitle')$widths=[48,12];
        $committee=$document==='envelope-cover' && collect($reportRow['cells'])->contains('style','committee');
        $area=$reportRow['area']??null;
        if($widths!==$profile || $committee || $lastCommittee || $area!==$lastArea){$tables[]=[];$profile=$widths;}
        $tables[array_key_last($tables)][]=$reportRow;
        $lastCommittee=$committee;
        $lastArea=$area;
    }
@endphp
@foreach($tables as $table)
@php
    $contactTable=$document==='envelope-cover' && collect($table[0]['cells'])->contains('style','cover-table-head');
    $committeeTable=$document==='envelope-cover' && collect($table[0]['cells'])->contains('style','committee');
    $stickerTable=$document==='seat-labels' && collect($table[0]['cells'])->contains('style','label');
    $stickerHeader=$document==='seat-labels' && ($table[0]['cells'][0]['role']??'')==='title';
    $seatPlanHeading=$document==='room-plan' && collect($table)->contains(fn($row)=>collect($row['cells'])->contains('role','seat-plan-label'));
    $classroomStart=$document==='room-plan' && ($table[0]['area']??'')==='classroom' && !($tables[$loop->index-1][0]['area']??null);
@endphp
@if($classroomStart)<div class="classroom-plan">@endif
@if($stickerHeader)<div class="sticker-header-card">@endif
<table class="report-grid{{ $contactTable?' envelope-contacts':'' }}{{ $committeeTable?' envelope-committee':'' }}{{ $stickerTable?' seat-stickers':'' }}{{ $seatPlanHeading?' seat-plan-heading':'' }}">
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
            $alignment=(($cell['logo']??false) && $cell['style']==='body')?'left':$style['align'];
            $contactClass=$contactTable && in_array($cell['style'],['cover-table-head','cover-table-body'],true)?($loop->index===1?' contact-left':' contact-right'):'';
            $headerRoom=($cell['role']??'')==='sticker-header-room';
            $cellWidth=($cell['role']??'')==='sticker-subtitle'?48:$cell['span'];
            $cellHeight=$headerRoom?12:$row['height'];
        @endphp
        <td class="{{ $cell['style'].$contactClass }}" @if($headerRoom)rowspan="2"@endif @if(isset($cell['role'])) data-report-role="{{ $cell['role'] }}" @endif style="@if($cell['style']==='label')line-height:1;padding:.2mm .6mm .6mm;vertical-align:top;@endif width:{{ $cellWidth/60*100 }}%;height:{{ $cellHeight }}mm;text-align:{{ $alignment }};font-size:{{ $style['size'] }}pt;font-weight:{{ ($style['bold']??false)?'bold':'normal' }};color:{{ $style['color']??'#000' }};background:{{ $style['fill']??'#fff' }};border:{{ $border }};@if(isset($cell['padding_y']))padding-top:{{ $cell['padding_y'] }}mm;padding-bottom:{{ $cell['padding_y'] }}mm;@endif @if(isset($cell['offset_y']))padding-top:{{ max(0,$cell['offset_y']*2) }}mm;padding-bottom:{{ max(0,-$cell['offset_y']*2) }}mm;@endif">
            @if(isset($cell['sticker_title']))
                <table class="sticker-heading"><tr>
                    <td class="sticker-logo" style="width:100%;height:{{ ($cell['logo_height']??14)-1 }}mm;padding:0;border:0;text-align:center;vertical-align:top">
                        @if($logoSrc)<img src="{{ $pdfExport?$logoSrc:($logoUrl??$logoSrc) }}" style="width:{{ $cell['logo_width'] }}mm;max-height:{{ $cell['logo_height']??14 }}mm;margin:-.5mm auto -1mm" alt="{{ $logoLabel??'School Logo 1' }}">@endif
                    </td>
                </tr><tr><td class="sticker-title" style="height:auto;padding:0 0 1mm;border:0;text-align:center;font-size:{{ $cell['lines'][0]['size'] }}pt;font-weight:bold;line-height:1">{{ $cell['lines'][0]['text'] }}</td></tr>
                <tr><td style="height:auto;padding:0;border:0;text-align:center;line-height:1">
            @elseif(($cell['logo']??false) && $logoSrc)
                <img src="{{ $pdfExport?$logoSrc:($logoUrl??$logoSrc) }}" style="width:{{ $cell['logo_width'] }}mm;max-height:{{ max(6,$row['height']-2) }}mm;@if(isset($cell['logo_offset_y']))margin-top:{{ $cell['logo_offset_y'] }}mm;margin-bottom:-{{ $cell['logo_offset_y'] }}mm;@endif" alt="Western International School">
            @endif
            @if(isset($cell['illustration'],$roomSymbols[$cell['illustration']]))
                <img src="{{ $roomSymbols[$cell['illustration']] }}" style="width:{{ $cell['illustration_width'] }}mm;height:{{ $cell['illustration_height'] }}mm;display:block;margin:0 auto 1mm" alt="{{ $cell['lines'][0]['text'] }}">
            @endif
            @foreach($cell['lines'] as $line)
                @if($line['text']!=='' && !(isset($cell['sticker_title']) && $loop->first))
                @if(($line['role']??'')==='sticker-seat')
                @php $numberWidth=min(64,4+12*mb_strlen((string)$line['runs'][1]['text']));$sideWidth=(100-$numberWidth)/2; @endphp
                <table class="sticker-seat" style="width:100%;table-layout:fixed;border-collapse:collapse;margin:0 0 1mm"><tr>
                    <td style="width:{{ $sideWidth }}%;height:auto;padding:0 0 1mm;border:0;text-align:right;vertical-align:bottom;font-size:6pt;line-height:1">Seat No.</td>
                    <td class="sticker-seat-number" style="width:{{ $numberWidth }}%;height:auto;padding:0;border:0;text-align:center;vertical-align:bottom;font-size:26pt;font-weight:bold;color:#ed1c24;line-height:1">{{ $line['runs'][1]['text'] }}</td>
                    <td style="width:{{ $sideWidth }}%;height:auto;padding:0;border:0"></td>
                </tr></table>
                @else
                @php
                    $lineFont=($line['font']??'')==='muol'?'scholarshipmuol':(preg_match('/\p{Khmer}/u',(string)$line['text'])?'scholarshipsiemreap':null);
                @endphp
                <div class="line" style="@if($cell['style']==='label')line-height:100%;@endif font-size:{{ $line['size']??$style['size'] }}pt;font-weight:{{ ($line['bold']??$style['bold']??false)?'bold':'normal' }};color:{{ $line['color']??$style['color']??'#000' }};{{ $lineFont?'font-family:'.$lineFont.',sans-serif;':'' }}@if(isset($line['space_after']))margin-bottom:{{ $line['space_after'] }}mm;@endif">@if(isset($line['runs']))@foreach($line['runs'] as $run)<span style="font-size:{{ $run['size']??$line['size']??$style['size'] }}pt;font-weight:{{ ($run['bold']??false)?'bold':'normal' }};color:{{ $run['color']??'#000' }};white-space:pre-wrap">{{ $run['text'] }}</span>@endforeach @else{{ $line['text'] }}@endif</div>
                @endif
                @endif
            @endforeach
            @if(isset($cell['sticker_title']))</td></tr></table>@endif
        </td>
        @endforeach
    </tr>
    @endforeach
    </tbody>
</table>
@if($stickerHeader)</div>@endif
@endforeach
@if($document==='room-plan')</div>@endif
@if(!$pdfExport && !in_array($document,['attendance-cover','envelope-cover','room-plan'],true))<div class="page-number">Page {{ $loop->iteration }} of {{ count($pages) }}</div>
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
