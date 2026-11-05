@php($report=\App\Support\G9ScoringReport::class)
@php($digits=\App\Support\G9CandidateReport::class)
@php($scores=\App\Support\G9ScoreSummary::class)
<table width="100%" class="verify-header"><tr>
    <td width="34%" class="verify-left">{{ $page['location'] }}<br>មណ្ឌលប្រឡង៖ {{ $page['center'] }}<br>បន្ទប់លេខ៖ {{ $digits::roomHeading((string)$page['room']) }}</td>
    <td width="32%" class="verify-motto">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ</td>
    <td width="34%" class="verify-right">សម្រាប់ផ្ទៀងផ្ទាត់</td>
</tr></table>
<div class="verify-ornament">5</div>
<div class="verify-title">{{ $report::HEADING }}</div>
<div class="verify-exam-date">សម័យប្រឡង៖ {{ $page['exam_date'] }}</div>
<table width="100%" class="verify-scores"><thead><tr>
    <th width="10mm" rowspan="2">លេខតុ</th><th width="34mm" rowspan="2">នាមត្រកូល និងនាមខ្លួន</th><th width="18mm" rowspan="2">ថ្ងៃខែឆ្នាំកំណើត</th><th width="41mm" rowspan="2">មកពីសាលា</th><th width="10mm" rowspan="2">ពិន្ទុប្រចាំឆ្នាំ</th>
    @foreach($report::SUBJECTS as $field=>[$label,$maximum])
        @if($field==='score_english')<th width="20mm" colspan="2">ភាសាបរទេស</th>
        @elseif($field==='score_values')<th width="10mm"><span class="verify-header-word">សីលធម៌</span><br>ពលរដ្ឋ</th>
        @elseif($field==='score_khmer_dictation')<th width="10mm">សរសេរ<br><span class="verify-header-word">តាមអាន</span></th>
        @else<th width="10mm">{{ $label }}</th>@endif
    @endforeach
    <th width="14mm" rowspan="2">ពិន្ទុសរុប</th><th width="14mm" rowspan="2">លទ្ធផល</th><th width="20mm" rowspan="2">និទ្ទេស</th>
</tr><tr class="verify-max">@foreach($report::SUBJECTS as $field=>[$label,$maximum])<th>{{ $maximum }}</th>@endforeach<th>-25</th></tr></thead><tbody>
@foreach($page['rows'] as $row)
    @php($summary=$row['summary'])
    <tr><td class="verify-number">{{ $row['seat'] }}</td><td class="verify-name">{{ $row['name'] }}</td><td class="verify-number">{{ $row['dob'] }}</td><td class="verify-school">{{ $row['school'] }}</td><td class="verify-number">{{ $scores::display($summary['eoy']) }}</td>
        @foreach($report::SUBJECTS as $field=>[$label,$maximum])<td class="verify-number">{{ $scores::display($row['scores'][$field]??null) }}</td>@endforeach
        <td class="verify-number">{{ $scores::display($summary['english_contribution']) }}</td>
        <td class="verify-number verify-total {{ $summary['total']!==null&&$summary['total']<260?'verify-failed':'' }}">{{ $scores::display($summary['total']) }}{{ !$summary['complete']&&$summary['total']!==null?'*':'' }}</td>
        <td class="verify-result">{{ $summary['result']??'—' }}</td><td class="verify-result">{{ $summary['grade']??'—' }}</td>
    </tr>
@endforeach
@for($blank=count($page['rows']);$blank<25;$blank++)<tr>@for($column=0;$column<20;$column++)<td>&nbsp;</td>@endfor</tr>@endfor
</tbody></table>
<div class="verify-footer">
    <span>ចំនួនបេក្ខជនក្នុងបន្ទប់សរុប៖ <b>{{ $page['summary']['total'] }}</b> នាក់ ស្រី៖ <b>{{ $page['summary']['female'] }}</b> នាក់</span>
    <span>ជាប់សរុប៖ <b>{{ $page['summary']['passed'] }}</b> នាក់ ស្រី៖ <b>{{ $page['summary']['passed_female'] }}</b> នាក់</span>
    <span>ធ្លាក់សរុប៖ <b>{{ $page['summary']['failed'] }}</b> នាក់ ស្រី៖ <b>{{ $page['summary']['failed_female'] }}</b> នាក់</span>
    @if($page['summary']['pending'])<span>មិនទាន់ពេញលេញ៖ <b>{{ $page['summary']['pending'] }}</b> នាក់</span>@endif
</div>
@if($page['summary']['pending'])<div class="verify-pending">* ពិន្ទុមិនទាន់ពេញលេញ។ លទ្ធផល និងនិទ្ទេសបង្ហាញតែនៅពេលពិន្ទុពេញលេញ។</div>@endif
<div class="verify-page-number">ទំព័រ {{ $digits::digits($page['page']) }} / {{ $digits::digits($page['pages']) }}</div>
