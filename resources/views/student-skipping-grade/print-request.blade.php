@include('student-skipping-grade._print-head',['printTitle'=>'Grade Skipping Application Form'])
<main class="skipping-paper skipping-request">
    <header class="request-heading">{!! $logoObject !!}
        <div><h1>{!! $text('heading-kh') !!}</h1><h2>{!! $text('heading-en') !!}</h2></div>
    </header>
    <div class="request-fields">
        <div class="field-wide"><span>{!! $text('label-name-kh') !!}<small>{!! $text('label-name-en') !!}</small></span><strong>{!! $text('student-name') !!}{!! $shape('line-student-name') !!}</strong></div><div class="field-narrow"><span>{!! $text('label-id-kh') !!}<small>{!! $text('label-id-en') !!}</small></span><strong>{!! $text('student-id') !!}{!! $shape('line-student-id') !!}</strong></div>
        <div class="field-third"><span>{!! $text('label-current-kh') !!}<small>{!! $text('label-current-en') !!}</small></span><strong>{!! $text('current-grade') !!}{!! $shape('line-current-grade') !!}</strong></div><div class="field-third"><span>{!! $text('label-target-kh') !!}<small>{!! $text('label-target-en') !!}</small></span><strong>{!! $text('target-grade') !!}{!! $shape('line-target-grade') !!}</strong></div><div class="field-third"><span>{!! $text('label-year-kh') !!}<small>{!! $text('label-year-en') !!}</small></span><strong>{!! $text('year') !!}{!! $shape('line-year') !!}</strong></div>
        <div><span>{!! $text('label-dob-kh') !!}<small>{!! $text('label-dob-en') !!}</small></span><strong>{!! $text('dob') !!}{!! $shape('line-dob') !!}</strong></div><div><span>{!! $text('label-campus-kh') !!}<small>{!! $text('label-campus-en') !!}</small></span><strong>{!! $text('campus') !!}{!! $shape('line-campus') !!}</strong></div>
        <div class="field-wide field-parent"><span>{!! $text('label-parent-kh') !!}<small>{!! $text('label-parent-en') !!}</small></span><strong>{!! $text('parent') !!}{!! $shape('line-parent') !!}</strong></div><div class="field-narrow field-signature"><span>{!! $text('label-signature-kh') !!}<small>{!! $text('label-signature-en') !!}</small></span><strong class="signature-blank"><span aria-hidden="true">&nbsp;</span>{!! $shape('line-parent-signature') !!}</strong></div>
        <div><span>{!! $text('label-phone-kh') !!}<small>{!! $text('label-phone-en') !!}</small></span><strong>{!! $text('phone') !!}{!! $shape('line-phone') !!}</strong></div><div><span>{!! $text('label-date-kh') !!}<small>{!! $text('label-date-en') !!}</small></span><strong>{!! $text('date') !!}{!! $shape('line-date') !!}</strong></div>
    </div>
    <section class="request-criteria"><h3>{!! $text('criteria-heading-kh') !!}</h3><h4>{!! $text('criteria-heading-en') !!}</h4>
        @foreach($criteriaOptions as $key=>$criterion)<div class="request-criterion">{!! $shape('criteria-check-'.$key,(bool)($record->criteria[$key]??false)) !!}<div>
        @if($key==='average')<p class="request-average-heading">{!! $text('criterion-average-kh') !!} <strong class="request-average-score">{!! $text('average') !!}</strong></p>
        @else<p>{!! $text('criterion-'.$key.'-kh') !!}</p>@endif
        <p class="english">{!! $text('criterion-'.$key.'-en') !!}</p></div></div>@endforeach
    </section>
    <section class="request-committee">{!! $shape('committee-divider') !!}<h4>{!! $text('committee-heading-en') !!}</h4>
        <table><thead><tr><th>{!! $text('column-position') !!}</th><th>{!! $text('column-name') !!}</th><th>{!! $text('column-signature') !!}</th><th>{!! $text('column-date') !!}</th></tr></thead><tbody>
        @foreach($committeeRoles as $index=>$role)<tr><td>{!! $text('committee-role-'.$index) !!}{!! $shape('committee-line-'.$index.'-position') !!}</td><td>{!! $text('committee-name-'.$index) !!}{!! $shape('committee-line-'.$index.'-name') !!}</td><td>{!! $shape('committee-line-'.$index.'-signature') !!}</td><td>{!! $shape('committee-line-'.$index.'-date') !!}</td></tr>@endforeach
        </tbody></table>
    </section>
    <footer class="request-footer">{!! $text('footer-version') !!} <span>{!! $text('footer-reference') !!}</span></footer>
{!! $extras !!}
</main></body></html>
