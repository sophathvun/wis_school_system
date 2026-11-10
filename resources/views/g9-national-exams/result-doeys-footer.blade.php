<table width="100%" class="result-doeys-footer"><tr>
    <td width="48%" class="result-doeys-note"><b>សម្គាល់៖</b><br>ភាគរយបេក្ខជនប្រឡងជាប់ត្រូវធៀបជាមួយបេក្ខជនមកប្រឡង។</td>
    <td width="52%" class="result-doeys-signature">
        @if($page['lunar'])<div>{{ $page['lunar'] }}</div>@endif
        @if($page['solar'])<div>{{ $page['solar'] }}</div>@endif
        <div class="result-doeys-role">@foreach($page['doeys_signature_titles'] as $title)@if(!$loop->first)<br>@endif{{ $title }}@endforeach</div>
        @if($page['signer_name'])<div class="result-doeys-signer-name">{{ $page['signer_name'] }}</div>@endif
    </td>
</tr></table>
