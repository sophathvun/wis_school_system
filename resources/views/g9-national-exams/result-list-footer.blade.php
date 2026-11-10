@php
    $totals=$page['list_totals'];
    $blankLunar='ថ្ងៃ........................ ខែ................ ឆ្នាំ........................ ព.ស.២៥........';
    $blankSolar=preg_replace('/[:៖]/u','',$page['location']).' ថ្ងៃទី........ ខែ................ ឆ្នាំ២០........';
@endphp
<table width="100%" class="result-list-footer"><tr>
    <td width="51%" class="result-list-approval">
        <table width="100%" class="result-list-compilers"><tr>
            <td width="50%">ហត្ថលេខា និងឈ្មោះអ្នកត្រួតពិនិត្យលទ្ធផលចុងក្រោយ</td>
            <td width="50%">ហត្ថលេខា និងឈ្មោះអ្នកចុះជាប់ធ្លាក់ចុងក្រោយ</td>
        </tr><tr class="result-compiler-signatures"><td>{{ $page['prepared_by']?:"\u{00A0}" }}</td><td>{{ $page['verified_by']?:"\u{00A0}" }}</td></tr></table>
        <div class="result-approval-title">បានឃើញ និងឯកភាព</div>
        <div>{{ $page['approval_lunar']?:$blankLunar }}</div>
        <div>{{ $page['approval_solar']?:$blankSolar }}</div>
        <div class="result-approval-role">ប្រធាន{{ $page['department'] }}{{ preg_match('/ខេត្ត|រាជធានី/u',$page['department'])?'':(str_starts_with($page['location'],'ខេត្ត')?'ខេត្ត':'រាជធានី') }}</div>
    </td>
    <td width="49%" class="result-list-center-signature">
        <table width="100%" class="result-list-totals">
            <tr><td style="text-align:right">បញ្ឈប់បញ្ជីត្រឹមចំនួនបេក្ខជន: {{ $digits::digits($totals['total']) }} នាក់ ក្នុងនោះស្រី: {{ $digits::digits($totals['female']) }} នាក់</td></tr>
            <tr><td style="text-align:right">បានប្រឡងជាប់សញ្ញាបត្រសរុបចំនួន: <span class="result-red">{{ $digits::digits($totals['passed']) }}</span> នាក់ ក្នុងនោះស្រី: <span class="result-red">{{ $digits::digits($totals['passed_female']) }}</span> នាក់</td></tr>
            <tr><td style="text-align:right">អវត្តមាន៖ {{ $digits::digits($totals['absent']) }} នាក់ &nbsp; ធ្លាក់៖ {{ $digits::digits($totals['failed']) }} នាក់@if($totals['pending']) &nbsp; មិនទាន់ពេញលេញ៖ {{ $digits::digits($totals['pending']) }} នាក់@endif</td></tr>
        </table>
        <div>{{ $page['lunar']?:$blankLunar }}</div>
        <div>{{ $page['solar']?:$blankSolar }}</div>
        <div class="result-signer">{{ $page['signer'] }}</div>
        <div class="result-signer-name result-red">{{ $page['signer_name'] }}</div>
    </td>
</tr></table>
