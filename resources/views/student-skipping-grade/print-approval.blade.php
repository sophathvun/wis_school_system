@include('student-skipping-grade._print-head',['printTitle'=>'Grade Skipping Approval · '.$record->reference_number])
<main class="skipping-paper skipping-approval">
    <header class="approval-heading"><div class="national-motto"><h2>{!! $text('motto-country') !!}</h2><h3>{!! $text('motto-national') !!}</h3><p class="motto-english">{!! $text('motto-country-en') !!}</p><p class="motto-english">{!! $text('motto-national-en') !!}</p></div>
    {!! $logoObject !!}
    <div class="approval-reference">{!! $text('reference') !!}</div>
    <h1>{!! $text('heading') !!}</h1>
    <h2 class="approval-heading-en">{!! $text('heading-en') !!}</h2></header>
    <div class="approval-student">
        <div class="approval-student-field"><div class="approval-student-value-row">{!! $text('label-name') !!}<strong class="english">{!! $text('student-name') !!}</strong></div><small class="approval-student-label-en">{!! $text('label-name-en') !!}</small></div>
        <div class="approval-student-field"><div class="approval-student-value-row">{!! $text('label-id') !!}<strong>{!! $text('student-id') !!}</strong></div><small class="approval-student-label-en">{!! $text('label-id-en') !!}</small></div>
        <div class="approval-student-field"><div class="approval-student-value-row">{!! $text('label-target') !!}<strong>{!! $text('target-grade') !!}</strong></div><small class="approval-student-label-en">{!! $text('label-target-en') !!}</small></div>
    </div>
    <h2 class="approval-center approval-committee-heading">{!! $text('committee-heading') !!}</h2>
    <div class="approval-references">
        <h3 class="approval-references-heading">{!! $text('references-heading-kh') !!}<strong class="approval-references-heading-en">{!! $text('references-heading-en') !!}</strong></h3>
        <p class="approval-reference-kh"><span class="approval-reference-bullet" aria-hidden="true">●</span>{!! $text('reference-rules') !!}</p>
        <p class="approval-reference-en">{!! $text('reference-rules-en') !!}</p>
        <p class="approval-reference-kh"><span class="approval-reference-bullet" aria-hidden="true">●</span>{!! $text('reference-reviewed') !!}</p>
        <p class="approval-reference-en">{!! $text('reference-reviewed-en') !!}</p>
        <p class="approval-reference-kh"><span class="approval-reference-bullet" aria-hidden="true">●</span>{!! $text('reference-received') !!}</p>
        <p class="approval-reference-en">{!! $text('reference-received-en') !!}</p>
    </div>
    <h2 class="approval-center approval-decision-heading">{!! $text('decision-heading') !!}</h2>
    <section class="approval-article"><h3 class="approval-article-heading-kh approval-article-heading-inline">{!! $text('article-1-heading') !!} / <strong class="approval-article-heading-en">{!! $text('article-1-heading-en') !!}</strong></h3>
        <div class="approval-condition approval-decision-option" data-decision-option="approved" @if($record->status!=='approved') hidden @endif>{!! $shape('check-age-standard',$record->status==='approved') !!}<div class="approval-condition-text"><div class="approval-condition-kh">{!! $text('age-standard') !!}</div><div class="approval-condition-en">{!! $text('age-standard-en') !!}</div></div></div>
        <div class="approval-condition approval-decision-option" data-decision-option="rejected" @if($record->status!=='rejected') hidden @endif>{!! $shape('check-age-exception',$record->status==='rejected') !!}<div class="approval-condition-text"><div class="approval-condition-kh">{!! $text('age-exception') !!}</div><div class="approval-condition-en">{!! $text('age-exception-en') !!}</div></div></div>
    </section>
    <section class="approval-article"><h3 class="approval-article-heading-kh approval-article-heading-bilingual approval-article-two-heading">{!! $text('article-2-heading') !!} / <strong class="approval-article-heading-en">{!! $text('article-2-heading-en') !!}</strong></h3>
        <div class="approval-condition approval-fulfilled-criteria-option">{!! $shape('check-school-internal',($record->approval_snapshot['school_decision']??'internal')==='internal') !!}<div class="approval-condition-text"><div class="approval-condition-kh">{!! $text('school-internal') !!}</div><div class="approval-condition-en">{!! $text('school-internal-en') !!}</div></div></div>
    </section>
    <section class="approval-article"><h3 class="approval-article-heading-kh approval-article-heading-bilingual approval-article-three-heading">{!! $text('article-3-heading') !!} / <strong class="approval-article-heading-en">{!! $text('article-3-heading-en') !!}</strong></h3>
        <div class="approval-obligations">
            @foreach(['conduct','rules','study'] as $key)
                <div class="approval-condition">{!! $shape('check-obligation-'.$key,(bool)($record->approval_snapshot['obligations'][$key]??false)) !!}
                    <div class="approval-condition-text"><div class="approval-condition-kh">{!! $text('obligation-'.$key) !!}</div><div class="approval-condition-en">{!! $text('obligation-'.$key.'-en') !!}</div></div>
                </div>
            @endforeach
        </div>
    </section>
    <section class="approval-article"><h3 class="approval-article-heading-kh approval-article-heading-bilingual">{!! $text('article-4-heading') !!} / <strong class="approval-article-heading-en">{!! $text('article-4-heading-en') !!}</strong></h3><p>{!! $text('implementation') !!}</p><p class="approval-condition-en approval-implementation-en">{!! $text('implementation-en') !!}</p></section>
    <div class="approval-signoff"><p>{!! $text('signoff-date') !!}</p><p class="approval-condition-en approval-signoff-date-en">{!! $text('signoff-date-en') !!}</p><h3>{!! $text('signoff-title') !!}</h3><div class="approval-images">@if($stamp)<img class="approval-stamp" src="{{ $stamp }}" alt="Approval stamp">@endif @if($signature)<img class="approval-signature" src="{{ $signature }}" alt="VP digital signature">@endif</div><strong>{!! $text('signoff-name') !!}</strong></div>
{!! $extras !!}
</main></body></html>
