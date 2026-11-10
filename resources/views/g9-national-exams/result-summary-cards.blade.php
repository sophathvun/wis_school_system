<table width="100%" class="result-summary-cards" aria-label="Exam result summary"><tr>
    @foreach($page['summary_cards'] as $card)
    <td width="33.33%" class="result-summary-slot">
        <table width="100%" class="result-summary-card result-summary-{{ $card['key'] }}"><tr><td>
            <div class="result-summary-label" style="font-weight:bold;color:{{ ['total'=>'#215d9d','passed'=>'#187447','failed'=>'#bd3045'][$card['key']] }}">{{ $card['label'] }}</div>
            <div class="result-summary-value" style="color:{{ ['total'=>'#215d9d','passed'=>'#187447','failed'=>'#bd3045'][$card['key']] }}">{{ number_format($card['total']) }}</div>
            <div class="result-summary-languages">
                @foreach($card['languages'] as $language)
                    @if(!$loop->first)<span class="result-summary-divider"> | </span>@endif
                    {{ $language['label'] }}: <b>{{ number_format($language['total']) }}</b>
                    @if($card['key']!=='total')
                        <span>({{ $language['percentage']===null?'—':number_format($language['percentage']*100,2).'%' }})</span>
                    @endif
                @endforeach
            </div>
        </td></tr></table>
    </td>
    @endforeach
</tr></table>
