<header class="doeys-heading">
    <div class="doeys-authority khmer-muol">ក្រសួងអប់រំ យុវជន និងកីឡា<br>មន្ទីរអប់រំ យុវជន និងកីឡារាជធានីភ្នំពេញ<br>{{ $selectedSchoolName ?? 'សាលាវេស្ទើនអន្តរជាតិ' }}</div>
    <div class="doeys-motto khmer-muol">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ<div class="grade-a-ornament" aria-hidden="true"><span></span></div></div>
</header>
<div class="doeys-title khmer-muol">
    <h1>លទ្ធផលប្រឡងសញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិចំណេះទូទៅ និងបំពេញវិជ្ជា (ថ្នាក់ទី១២)</h1>
    @if($exam_session)<h2>សម័យប្រឡង៖ {{ $exam_session }}</h2>
    @else<h2>ឆ្នាំសិក្សា៖ {{ $filters['academic_year'] ?? ($rows[0]['academic_year'] ?? 'All') }}</h2>@endif
</div>
<table class="all-statistics-table">
    <thead class="khmer">
        <tr><th rowspan="3" width="4.5%" class="all-stat-number">ល.រ</th><th rowspan="3" width="18.5%" class="all-stat-label">បេក្ខជនប្រឡង</th>
            @foreach(['បេក្ខជនចុះឈ្មោះ','បេក្ខជនមកប្រឡង','បេក្ខជនប្រឡងជាប់'] as $label)<th colspan="2" rowspan="2" width="8.55%" @class(['all-stat-passed'=>$loop->last])>{{ $label }}</th>@endforeach
            <th colspan="12" width="51.35%" class="all-stat-grade">និទ្ទេស</th>
        </tr>
        <tr>@foreach(['A','B','C','D','E','F'] as $grade)<th colspan="2" class="all-stat-grade @if($grade==='F') all-stat-failed @endif">{{ $grade }}</th>@endforeach</tr>
        <tr>@for($i=0;$i<9;$i++)<th @class(['all-stat-passed'=>$i===2,'all-stat-grade'=>$i>2,'all-stat-failed'=>$i===8])>សរុប</th><th @class(['all-stat-passed'=>$i===2,'all-stat-grade'=>$i>2,'all-stat-failed'=>$i===8])>ស្រី</th>@endfor</tr>
    </thead>
    <tbody>
        @php
            $labels=['Science - English'=>['ប្រឡងជាភាសាអង់គ្លេស','វិទ្យាសាស្ត្រ'],'Science - Khmer'=>['ប្រឡងជាភាសាខ្មែរ','វិទ្យាសាស្ត្រ'],'Social Science - English'=>['ប្រឡងជាភាសាអង់គ្លេស','វិទ្យាសាស្ត្រសង្គម'],'Social Science - Khmer'=>['ប្រឡងជាភាសាខ្មែរ','វិទ្យាសាស្ត្រសង្គម']];
            $previousCampus=null;
            $number=0;
            $percentage=fn($count,$base)=>number_format($base ? $count*100/$base : 0,2).'%';
        @endphp
        @foreach($statistics['rows'] as $stat)
        @continue(!$stat['total'] && $stat['registered']['male']+$stat['registered']['female']===0)
        @if($stat['label']!=='Grand Total' && $previousCampus!==$stat['campus'])
        <tr class="all-stat-campus"><td colspan="20">{{ $stat['campus'] }} Campus</td></tr>
        @php
            $previousCampus=$stat['campus'];
            $number=0;
        @endphp
        @endif
        <tr @class(['all-stat-subtotal'=>$stat['total'],'all-stat-grand'=>$stat['label']==='Grand Total'])>
            @if($stat['total'])<td colspan="2" class="khmer all-stat-text">{{ $stat['label']==='Grand Total'?'សរុបរួម':'សរុប' }}</td>
            @else<td>{{ ++$number }}</td><td class="khmer all-stat-text">{{ $labels[$stat['label']][0] ?? $stat['label'] }}<br><span>({{ $labels[$stat['label']][1] ?? '' }})</span></td>@endif
            @foreach(['registered','examined','passed'] as $metric)
                @php
                    $total=$stat[$metric]['male']+$stat[$metric]['female'];
                    $base=$metric==='passed'?'examined':'registered';
                @endphp
                <td @class(['all-stat-pass-value'=>$metric==='passed','all-stat-passed'=>$metric==='passed'&&$stat['label']==='Grand Total'])>{{ $total }}@if($metric!=='registered')<small>{{ $percentage($total,$stat[$base]['male']+$stat[$base]['female']) }}</small>@endif</td>
                <td @class(['all-stat-pass-value'=>$metric==='passed','all-stat-passed'=>$metric==='passed'&&$stat['label']==='Grand Total'])>{{ $stat[$metric]['female'] }}@if($metric!=='registered')<small>{{ $percentage($stat[$metric]['female'],$stat[$base]['female']) }}</small>@endif</td>
            @endforeach
            @foreach(['A','B','C','D','E','F'] as $grade)
            <td @class(['all-stat-f-value'=>$grade==='F'])>{{ $stat['grades'][$grade]['male']+$stat['grades'][$grade]['female'] }}<small>{{ $percentage($stat['grades'][$grade]['male']+$stat['grades'][$grade]['female'],$stat['examined']['male']+$stat['examined']['female']) }}</small></td>
            <td @class(['all-stat-f-value'=>$grade==='F'])>{{ $stat['grades'][$grade]['female'] }}<small>{{ $percentage($stat['grades'][$grade]['female'],$stat['examined']['female']) }}</small></td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>
<div class="all-stat-footer"><div class="khmer-muol">ការិយាល័យសិក្សា</div>{{ $reportDate->format('F d, Y') }}</div>
