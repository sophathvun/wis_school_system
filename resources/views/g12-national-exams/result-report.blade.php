<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
@if($type==='result-list' && !$pdfExport)
@vite('resources/js/g12ResultReport.js')
@endif
<style>{!! file_get_contents(resource_path('css/pages/g12-result-report.css')) !!}</style>
<style>@if($khmerFontData)@font-face{font-family:KhmerReport;src:url('{{ $khmerFontData }}') format('truetype');font-weight:400}@endif @if($khmerMuolFontData)@font-face{font-family:KhmerMuolLight;src:url('{{ $khmerMuolFontData }}') format('truetype');font-weight:400}@endif @page{size:A4 {{ $orientation }};margin:7mm}</style>
</head>
<body class="report-{{ $type }} orientation-{{ $orientation }} @if($pdfExport) pdf-export @endif">
@unless($pdfExport)<div class="report-toolbar"><button type="button" onclick="window.print()">Print Report</button><button type="button" onclick="window.close()">Close</button></div>@endunless
<main>
@if($type==='grade-a')
@foreach(($pdfExport ? ['print'=>$pages] : ['preview'=>$previewPages,'browser-print'=>[$rows]]) as $pageMode=>$reportPages)
<div class="grade-a-page-set grade-a-page-set-{{ $pageMode }}">
@foreach($reportPages as $pageIndex=>$pageRows)
<section class="grade-a-print-page">
@if($pageIndex===0)
<header class="grade-a-official-heading">
    <div class="grade-a-authority khmer-muol">ក្រសួងអប់រំ យុវជន និងកីឡា<br>មន្ទីរអប់រំ យុវជន និងកីឡារាជធានីភ្នំពេញ<br>សាលាវេស្ទើនអន្តរជាតិ</div>
    <div class="grade-a-kingdom khmer-muol">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ<div class="grade-a-ornament" aria-hidden="true"><span></span></div></div>
</header>
<div class="grade-a-title">
    <h1 class="khmer-muol">បញ្ជីឈ្មោះសិស្សនិទ្ទេស <b>A</b> ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិ</h1>
    <h2>List of Students Who Achieved Grade A in the Upper Secondary School Diploma Examination</h2>
    <h3><span class="khmer-muol">ឆ្នាំសិក្សា</span>/Academic Year : {{ $filters['academic_year'] ?? ($pageRows[0]['academic_year'] ?? 'All') }}</h3>
</div>
@endif
<table class="report-table grade-a-table"><thead><tr><th>No.</th><th>Cert. Number</th><th>Name in English</th><th>Name in Khmer</th><th>Gender</th><th>Student ID</th><th>Grade</th><th>Letter Grade</th><th>Phone</th></tr></thead><tbody>
@forelse($pageRows as $row)<tr><td>{{ $row['number'] }}</td><td>{{ $row['certificate_number'] }}</td><td class="grade-a-name-en">{{ $row['name_en'] }}</td><td class="grade-a-name-kh khmer">{{ $row['name_kh'] }}</td><td class="khmer">{{ $row['gender'] }}</td><td>{{ $row['student_code'] }}</td><td class="grade-a-class"><strong>{{ $row['grade'] }}</strong><span>{{ $row['campus'] }}</span></td><td class="grade-a-letter"><strong>A</strong>@if($row['score_rank']!=='')<span>{{ $row['score_rank'] }}</span>@endif</td><td>{{ $row['phone'] }}</td></tr>
@empty<tr><td colspan="9" class="empty-report-row">No Grade A students match the selected filters.</td></tr>@endforelse
</tbody></table>
@if($loop->last)
<div class="grade-a-final-summary"><div class="khmer">ចំនួនសិស្សសរុប៖ <strong>{{ $summary['total'] }}</strong>នាក់ / ស្រី <strong>{{ $summary['female'] }}</strong>នាក់</div><strong>Registrar's Office</strong><span>{{ $reportDate->format('F d, Y') }}</span></div>
@endif
<div class="grade-a-page-number">Page {{ $pageIndex+1 }} of {{ count($reportPages) }}</div>
</section>
@endforeach
</div>
@endforeach
@else
@if(in_array($type,['grade-a-campus','result-list'],true))
<header class="grade-a-official-heading">
    <div class="grade-a-authority khmer-muol">ក្រសួងអប់រំ យុវជន និងកីឡា<br>មន្ទីរអប់រំ យុវជន និងកីឡារាជធានីភ្នំពេញ<br>សាលាវេស្ទើនអន្តរជាតិ</div>
    <div class="grade-a-kingdom khmer-muol">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ<div class="grade-a-ornament" aria-hidden="true"><span></span></div></div>
</header>
<div class="grade-a-title">
@if($type==='result-list')
    <h1 class="khmer-muol">បញ្ជីលទ្ធផលប្រឡងសញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិ</h1>
    @php
        $examDates = collect($rows)->pluck('exam_date')->filter()->unique()->map(function ($date) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) return $date;
            try {
                $parsed = \Carbon\CarbonImmutable::parse($date);
                $months = ['មករា','កុម្ភៈ','មីនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'];
                return strtr($parsed->format('d').' '.$months[$parsed->month-1].' '.$parsed->year, array_combine(range(0,9), ['០','១','២','៣','៤','៥','៦','៧','៨','៩']));
            } catch (\Throwable) { return $date; }
        });
    @endphp
    @if($examDates->isNotEmpty())<h2 class="khmer-muol">សម័យប្រឡង៖ {{ $examDates->implode(' / ') }}</h2>
    @else<h3><span class="khmer-muol">ឆ្នាំសិក្សា</span>/Academic Year : {{ $filters['academic_year'] ?? ($rows[0]['academic_year'] ?? 'All') }}</h3>@endif
@else
    <h1 class="khmer-muol">បញ្ជីឈ្មោះសិស្សនិទ្ទេស <b>A</b> ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិ</h1>
    <h2>List of Grade A Students for Grade 12 National Exams</h2>
    <h3><span class="khmer-muol">ឆ្នាំសិក្សា</span>/Academic Year : {{ $filters['academic_year'] ?? ($rows[0]['academic_year'] ?? 'All') }}</h3>
@endif
</div>
@else
<header class="report-heading">
    <div class="report-brand">@if($reportLogo)<img src="{{ $reportLogo }}" alt="School logo">@endif</div>
    <div class="report-heading-copy"><div class="report-school">WESTERN INTERNATIONAL SCHOOL</div><h1>{{ $title }}</h1><div class="report-meta">Academic Year: {{ $filters['academic_year'] ?? 'All' }}@if(filled($filters['campus'] ?? null)) &nbsp; | &nbsp; Campus: {{ $filters['campus'] }}@endif</div></div>
    <div class="report-date">Generated<br><strong>{{ $reportDate->format('d M Y') }}</strong></div>
</header>
@endif

@if($type==='grade-a-campus')
@php $displayRows=$groups; @endphp
<table class="report-table grade-a-table"><thead><tr><th>No.</th><th>Cert. Number</th><th>Name in English</th><th>Name in Khmer</th><th>Gender</th><th>Student ID</th><th>Grade</th><th>Letter Grade</th><th>Phone</th></tr></thead><tbody>
@foreach($displayRows as $campus=>$items)
<tr class="campus-row"><td colspan="9">{{ $campus }} Campus</td></tr>
@foreach($items as $index=>$row)<tr><td>{{ $index+1 }}</td><td>{{ $row['certificate_number'] }}</td><td class="grade-a-name-en">{{ $row['name_en'] }}</td><td class="grade-a-name-kh khmer">{{ $row['name_kh'] }}</td><td class="khmer">{{ $row['gender'] }}</td><td>{{ $row['student_code'] }}</td><td class="grade-a-class"><strong>{{ $row['grade'] }}</strong><span>{{ $campus }}</span></td><td class="grade-a-letter"><strong>A</strong>@if($row['score_rank']!=='')<span>{{ $row['score_rank'] }}</span>@endif</td><td>{{ $row['phone'] }}</td></tr>@endforeach
@endforeach
</tbody></table>
@elseif($type==='result-list')
<table class="report-table result-table"><thead><tr><th>លរ</th><th>គោត្តនាម និងនាមខ្លួន</th><th>ភេទ</th><th>ថ្ងៃខែឆ្នាំកំណើត</th><th>មណ្ឌលប្រឡង</th><th>ល.បន្ទប់</th><th>លេខតុ</th><th>និទ្ទេស</th><th>ថ្នាក់ជម្រើស</th></tr></thead><tbody>
@foreach($groups as $school=>$items)<tr class="campus-row"><td colspan="9" class="khmer-muol">{{ $school }}</td></tr>
@foreach($items as $index=>$row)<tr @class(['failed-row'=>$row['letter_grade']==='F'])><td>{{ $index+1 }}</td><td class="student-name khmer">{{ $row['name_kh'] }}</td><td>{{ $row['gender'] }}</td><td>{{ $row['dob_display'] }}</td><td class="khmer result-fit-cell"><span data-result-fit>{{ $row['center'] }}</span></td><td>{{ $row['room'] }}</td><td>{{ $row['seat'] }}</td><td><span class="grade-badge grade-{{ strtolower($row['letter_grade']?:'pending') }}">{{ $row['letter_grade']?:'—' }}</span></td><td class="khmer result-fit-cell"><span data-result-fit>{{ $row['selected']==='Social Science' ? 'វិទ្យាសាស្ត្រសង្គម' : ($row['selected']==='Science' ? 'វិទ្យាសាស្ត្រ' : $row['selected']) }}</span></td></tr>@endforeach
@endforeach
</tbody></table>
@else
<table class="report-table statistics-table"><thead><tr><th rowspan="2">{{ $type==='statistics-all'?'Campus':'Examination' }}</th>@if($type==='statistics-all')<th rowspan="2">Examination</th>@endif<th colspan="2">Registered</th><th colspan="2">Examined</th><th colspan="2">Passed</th><th colspan="2">Pass %</th>@foreach(['A','B','C','D','E','F'] as $grade)<th colspan="2" class="grade-head grade-{{ strtolower($grade) }}">{{ $grade }}</th>@endforeach</tr><tr>@for($i=0;$i<10;$i++)<th>{{ $i%2===0?'M':'F' }}</th>@endfor()@foreach(['A','B','C','D','E','F'] as $grade)<th>M</th><th>F</th>@endforeach</tr></thead><tbody>
@foreach($statistics['rows'] as $stat)<tr @class(['total-row'=>$stat['total']])><td>{{ $type==='statistics-all'?$stat['campus']:$stat['label'] }}</td>@if($type==='statistics-all')<td>{{ $stat['label'] }}</td>@endif<td>{{ $stat['registered']['male'] }}</td><td>{{ $stat['registered']['female'] }}</td><td>{{ $stat['examined']['male'] }}</td><td>{{ $stat['examined']['female'] }}</td><td>{{ $stat['passed']['male'] }}</td><td>{{ $stat['passed']['female'] }}</td><td>{{ $stat['pass_percentage']['male'] }}%</td><td>{{ $stat['pass_percentage']['female'] }}%</td>@foreach(['A','B','C','D','E','F'] as $grade)<td>{{ $stat['grades'][$grade]['male'] }}@if($type==='statistics-all')<small>{{ $stat['grade_percentages'][$grade]['male'] }}%</small>@endif</td><td>{{ $stat['grades'][$grade]['female'] }}@if($type==='statistics-all')<small>{{ $stat['grade_percentages'][$grade]['female'] }}%</small>@endif</td>@endforeach</tr>@endforeach
</tbody></table>
<section class="report-signatures"><div>Prepared by<br><br><br>________________________</div><div>Checked by<br><br><br>________________________</div><div>Approved by<br><br><br>________________________</div></section>
@endif
<footer>Western International School &nbsp; • &nbsp; {{ $title }}</footer>
@endif
</main>
</body>
</html>
