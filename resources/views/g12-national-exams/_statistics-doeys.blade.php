<header class="doeys-heading">
    <div class="doeys-authority">
        <div class="khmer-muol">ក្រសួងអប់រំ យុវជន និងកីឡា<br>មន្ទីរអប់រំ យុវជន និងកីឡារាជធានីភ្នំពេញ</div>
        <div class="khmer">{{ ($selectedSchoolName ?? null) ?: (collect($rows)->pluck('school_name_kh')->unique()->implode(' / ') ?: 'សាលាវេស្ទើនអន្តរជាតិ') }}</div>
    </div>
    <div class="doeys-motto khmer-muol">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ<div class="grade-a-ornament" aria-hidden="true"><span></span></div></div>
</header>
<div class="doeys-title khmer-muol">
    <h1>ស្ថិតិលទ្ធផលប្រឡងសញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិ</h1>
    @if($exam_session)<h2>សម័យប្រឡង៖ {{ $exam_session }}</h2>
    @else<h2>ឆ្នាំសិក្សា៖ {{ $filters['academic_year'] ?? ($rows[0]['academic_year'] ?? 'All') }}</h2>@endif
</div>
<table class="doeys-table">
    <thead class="khmer">
        <tr>
            <th rowspan="3" class="doeys-number">ល.រ</th><th rowspan="3" class="doeys-class">ថ្នាក់</th>
            @foreach(['បេក្ខជនចុះឈ្មោះ','បេក្ខជនមកប្រឡង','បេក្ខជនប្រឡងជាប់','% បេក្ខជនប្រឡងជាប់'] as $label)<th colspan="2" rowspan="2" class="doeys-fit-heading"><span data-result-fit data-fit-max-pt="10">{{ $label }}</span></th>@endforeach
            <th colspan="10">និទ្ទេស</th>
        </tr>
        <tr>@foreach(['A','B','C','D','E'] as $grade)<th colspan="2" class="doeys-letter">{{ $grade }}</th>@endforeach</tr>
        <tr>@for($pair=0;$pair<9;$pair++)<th>សរុប</th><th>ស្រី</th>@endfor</tr>
    </thead>
    <tbody>
        @php
            $labels=['Science - English'=>'វិទ្យាសាស្ត្រ (អង់គ្លេស)','Science - Khmer'=>'វិទ្យាសាស្ត្រ (ខ្មែរ)','Social Science - English'=>'វិទ្យាសាស្ត្រសង្គម (អង់គ្លេស)','Social Science - Khmer'=>'វិទ្យាសាស្ត្រសង្គម (ខ្មែរ)'];
            $number=0;
        @endphp
        @foreach($statistics['rows'] as $stat)
        @continue(!$stat['total'] && $stat['registered']['male']+$stat['registered']['female']===0)
        <tr @class(['doeys-total'=>$stat['total']])>
            @if($stat['total'])<td colspan="2" class="khmer doeys-label">សរុប</td>
            @else<td>{{ ++$number }}</td><td class="khmer doeys-label">{{ $labels[$stat['label']] ?? $stat['label'] }}</td>@endif
            @foreach(['registered','examined','passed'] as $metric)<td>{{ $stat[$metric]['male']+$stat[$metric]['female'] }}</td><td>{{ $stat[$metric]['female'] }}</td>@endforeach
            <td>{{ $stat['examined']['male']+$stat['examined']['female'] ? round(($stat['passed']['male']+$stat['passed']['female'])*100/($stat['examined']['male']+$stat['examined']['female'])) : 0 }}%</td>
            <td>{{ $stat['examined']['female'] ? round($stat['passed']['female']*100/$stat['examined']['female']) : 0 }}%</td>
            @foreach(['A','B','C','D','E'] as $grade)<td>{{ $stat['grades'][$grade]['male']+$stat['grades'][$grade]['female'] }}</td><td>{{ $stat['grades'][$grade]['female'] }}</td>@endforeach
        </tr>
        @endforeach
    </tbody>
</table>
<div class="doeys-signature khmer">
    <div>{{ $khmerLunarDate ?? '' }}</div>
    <div>{{ $khmerSolarDate ?? '' }}</div>
    <div class="khmer-muol">នាយកសាលា</div>
</div>
