@php($report=\App\Support\G9ScoringReport::class)
@php($digits=\App\Support\G9CandidateReport::class)
@php($scores=\App\Support\G9ScoreSummary::class)
<table width="100%" class="verify-header collection-header"><tr>
    <td width="34%" class="verify-left">{{ $page['location'] }}<br>មណ្ឌលប្រឡង៖ {{ $page['center'] }}<br>បន្ទប់លេខ៖ {{ $digits::roomHeading((string)$page['room']) }}</td>
    <td width="32%" class="verify-motto">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ</td>
    <td width="34%" class="verify-pending">@if($page['summary']['pending'])* ពិន្ទុមិនទាន់ពេញលេញ៖ {{ $digits::digits($page['summary']['pending']) }} នាក់@endif</td>
</tr></table>
<div class="verify-ornament">5</div>
<div class="verify-title">{{ $report::HEADING }}</div>
<div class="verify-exam-date">សម័យប្រឡង៖ {{ $page['exam_date'] }}</div>
<table width="100%" class="verify-scores collection-scores"><thead><tr>
    <th width="10mm">លេខតុ</th><th width="34mm">នាមត្រកូល និងនាមខ្លួន</th><th width="18mm">ថ្ងៃខែឆ្នាំកំណើត</th><th width="37mm">មកពីសាលា</th><th width="10mm">ពិន្ទុប្រចាំឆ្នាំ</th>
    @foreach($report::SUBJECTS as $field=>[$label,$maximum])
        @if($field==='score_values')<th width="10mm"><span class="verify-header-word">សីលធម៌</span><br>ពលរដ្ឋ</th>
        @elseif($field==='score_khmer_dictation')<th width="10mm">សរសេរ<br><span class="verify-header-word">តាមអាន</span></th>
        @elseif($field==='score_english')<th width="10mm">ភាសា<br>បរទេស</th>
        @else<th width="10mm">{{ $label }}</th>@endif
    @endforeach
    <th width="13mm">ពិន្ទុសរុប</th><th width="12mm">លទ្ធផល</th><th width="15mm">និទ្ទេស</th><th width="22mm">ផ្សេងៗ</th>
</tr></thead><tbody>
@foreach($page['rows'] as $row)
    @php($summary=$row['summary'])
    @php($passed=!$row['absent']&&$summary['complete']&&$summary['total']>=260)
    @php($crossed=$row['absent']||($summary['complete']&&$summary['total']<260))
    <tr class="{{ $row['absent']?'collection-absent ':'' }}{{ $crossed?'collection-failed':'' }}">
        <td class="collection-seat">@if($pdfExport??false)<a name="{{ $page['marker_prefix'] }}-{{ $loop->index }}"></a>@endif<span class="{{ $passed&&!($pdfExport??false)?'collection-seat-circle':'' }}">{{ $digits::digits($row['seat']) }}</span></td>
        <td class="verify-name">{{ $row['name'] }}</td><td class="verify-number">{{ $row['dob'] }}</td><td class="verify-school">{{ $row['school'] }}</td>
        <td class="verify-number">{{ $scores::display($summary['eoy']) }}</td>
        @foreach($report::SUBJECTS as $field=>[$label,$maximum])<td class="verify-number">{{ $scores::display($field==='score_english'?$summary['english_contribution']:($row['scores'][$field]??null)) }}</td>@endforeach
        <td class="verify-number verify-total">{{ $scores::display($summary['total']) }}{{ !$summary['complete']&&$summary['total']!==null?'*':'' }}</td>
        <td class="verify-result">{{ $row['absent']?'':($summary['result']??'—') }}</td><td class="verify-result">{{ $row['absent']?'':($summary['grade']??'—') }}</td>
        <td class="collection-remarks">@if($row['absent'])<span class="collection-absent-label">អវត្តមាន</span>@endif</td>
    </tr>
@endforeach
@for($blank=count($page['rows']);$blank<25;$blank++)<tr>@for($column=0;$column<20;$column++)<td>&nbsp;</td>@endfor</tr>@endfor
</tbody></table>
<table width="100%" class="collection-footer"><tr>
    <td width="18%" class="collection-counts">ចំនួនបេក្ខជនសរុប៖ {{ $digits::digits($page['summary']['total']) }} នាក់<br>ស្រី៖ {{ $digits::digits($page['summary']['female']) }} នាក់<br>អវត្តមាន៖ {{ $digits::digits($page['summary']['absent']) }} នាក់<br>ស្រី៖ {{ $digits::digits($page['summary']['absent_female']) }} នាក់</td>
    @foreach([array_slice($report::SUBJECTS,0,4,true),array_slice($report::SUBJECTS,4,4,true),array_slice($report::SUBJECTS,8,3,true)] as $subjects)
        <td width="16%" class="collection-subject-signatures">@foreach($subjects as [$label,$maximum]){{ $label }}៖ ....................<br>@endforeach</td>
    @endforeach
    <td width="34%" class="collection-signature">
        <div>បេក្ខជនប្រឡងជាប់ {{ $digits::digits($page['summary']['passed']) }} នាក់ ស្រី {{ $digits::digits($page['summary']['passed_female']) }} នាក់</div>
        @if($page['lunar'])<div>{{ $page['lunar'] }}</div>@endif
        @if($page['solar'])<div>{{ $page['solar'] }}</div>@endif
        <div class="collection-signer-title">{{ $page['signer_title'] }}</div>
        <div class="collection-signer-name">{{ $page['signer_name'] }}</div>
    </td>
</tr></table>
@unless($pdfExport??false)<div class="verify-page-number collection-page-number">ទំព័រ {{ $digits::digits($page['page']) }} / {{ $digits::digits($page['pages']) }}</div>@endunless
