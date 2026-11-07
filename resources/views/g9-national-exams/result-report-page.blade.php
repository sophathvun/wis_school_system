@php($digits=\App\Support\G9CandidateReport::class)
@if($page['header'])
    <div class="result-motto">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ</div>
    <div class="result-ornament">5</div>
    <div class="result-heading-left">@if($type==='list'){{ $page['location'] }}<br>មណ្ឌលប្រឡង៖ {{ $page['center'] }}@else{{ $page['department'] }}@endif</div>
    <table width="100%" class="result-title-row"><tr>
        <td width="17%" class="result-room">@if($type==='list')បន្ទប់លេខ៖ {{ $digits::roomHeading((string)$page['room']) }}@endif</td>
        <td width="66%"><div class="result-title">{{ \App\Support\G9ResultReport::TITLES[$type] }}</div><div class="result-subtitle">ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ</div><div class="result-exam-date">សម័យប្រឡង៖ {{ $page['exam_date'] }} &nbsp; ឆ្នាំសិក្សា៖ {{ $page['year'] }}</div></td>
        <td width="17%"></td>
    </tr></table>
@endif
@foreach($page['tables'] as $table)
    <table width="100%" class="result-table {{ $type==='list'?'result-students':'' }}">
        <colgroup>@foreach($table['widths'] as $width)<col style="width:{{ $width/array_sum($table['widths'])*100 }}%">@endforeach</colgroup>
        <thead>
        @if(!empty($table['caption']))<tr><th colspan="{{ count($table['widths']) }}" class="result-section">{{ $table['caption'] }}</th></tr>@endif
        @foreach($table['headers'] as $header)
            @php($firstHeader=$loop->first)
            @php($column=0)
            <tr>@foreach($header as $cell)
                <th @if($firstHeader)width="{{ array_sum(array_slice($table['widths'],$column,$cell['colspan']))/array_sum($table['widths'])*100 }}%"@endif colspan="{{ $cell['colspan'] }}" rowspan="{{ $cell['rowspan'] }}" class="{{ $cell['style'] }}" @if($type==='list')style="background:#fff"@endif>{{ $cell['value'] }}</th>
                @php($column+=$cell['colspan'])
            @endforeach</tr>
        @endforeach
        </thead><tbody>
        @foreach($table['rows'] as $row)
            @if(isset($row['section']))<tr><td colspan="{{ count($table['widths']) }}" class="result-section">{{ $row['section'] }}</td></tr>
            @else<tr class="{{ !empty($row['total'])?'result-total ':'' }}{{ !($pdfExport??false)&&in_array($row['outcome']??'',['failed','absent'],true)?'result-crossed':'' }}">
                @php($rowIndex=$loop->index)
                @foreach($row['cells'] as $cell)<td class="{{ $cell['style'] }}" @if(isset($cell['font_size']))style="font-size:{{ $cell['font_size'] }}pt;white-space:nowrap"@endif>@if(($pdfExport??false)&&$type==='list'&&$loop->first)<a name="result-row-{{ $page['page'] }}-{{ $rowIndex }}"></a>@endif @if(str_contains($cell['style'],'percent')){{ $cell['value']===null?'—':number_format($cell['value']*100,2).'%' }}@elseif($cell['style']==='seat-passed'&&!($pdfExport??false))<span class="result-seat-circle">{{ $cell['value'] }}</span>@else{{ $cell['value'] }}@endif</td>@endforeach
            </tr>@endif
        @endforeach
        @if($type==='list')@for($i=count($table['rows']);$i<25;$i++)<tr>@for($j=0;$j<8;$j++)<td>&nbsp;</td>@endfor</tr>@endfor @endif
        </tbody>
    </table>
@endforeach
@foreach($page['notes'] as $note)<div class="result-note">{{ $note }}</div>@endforeach
@if($page['footer']||$page['signature'])
<table width="100%" class="result-footer"><tr>
    <td width="48%" class="result-left-signature">@if($type==='list')បានឃើញ និងឯកភាព<br><br><br>ប្រធានមន្ទីរអប់រំ យុវជន និងកីឡា@endif</td>
    <td width="52%">@foreach($page['footer'] as $line)<div>{{ $line }}</div>@endforeach
        @if($page['signature'])
            @if($page['lunar'])<div>{{ $page['lunar'] }}</div>@endif
            @if($page['solar'])<div>{{ $page['solar'] }}</div>@endif
            <div class="result-signer">{{ $page['signer'] }}</div><div class="result-signer-name">{{ $page['signer_name'] }}</div>
        @endif
    </td>
</tr></table>
@endif
<div class="result-page-number">ទំព័រ {{ $digits::digits($page['page']) }} / {{ $digits::digits($page['pages']) }}</div>
