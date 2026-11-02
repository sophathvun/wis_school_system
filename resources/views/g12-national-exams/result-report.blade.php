<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<style>{!! file_get_contents(resource_path('css/pages/g12-result-report.css')) !!}</style>
<style>@if($khmerFontData)@font-face{font-family:KhmerReport;src:url('{{ $khmerFontData }}') format('truetype')}@endif @page{size:A4 {{ $orientation }};margin:7mm}</style>
</head>
<body class="report-{{ $type }} orientation-{{ $orientation }}">
@unless($pdfExport)<div class="report-toolbar"><button type="button" onclick="window.print()">Print Report</button><button type="button" onclick="window.close()">Close</button></div>@endunless
<main>
<header class="report-heading">
    <div class="report-brand">@if($reportLogo)<img src="{{ $reportLogo }}" alt="School logo">@endif</div>
    <div class="report-heading-copy"><div class="report-school">WESTERN INTERNATIONAL SCHOOL</div><h1>{{ $title }}</h1><div class="report-meta">Academic Year: {{ $filters['academic_year'] ?? 'All' }}@if(filled($filters['campus'] ?? null)) &nbsp; | &nbsp; Campus: {{ $filters['campus'] }}@endif</div></div>
    <div class="report-date">Generated<br><strong>{{ $reportDate->format('d M Y') }}</strong></div>
</header>

@if(in_array($type,['grade-a','grade-a-campus'],true))
@php $displayRows=$type==='grade-a-campus'?$groups:['All Campuses'=>$rows]; @endphp
<table class="report-table grade-a-table"><thead><tr><th>No.</th><th>Certificate No.</th><th>Student Name</th><th>Gender</th><th>Student ID</th><th>Grade</th><th>Letter Grade</th><th>Phone</th></tr></thead><tbody>
@foreach($displayRows as $campus=>$items)
@if($type==='grade-a-campus')<tr class="campus-row"><td colspan="8">{{ $campus }} Campus</td></tr>@endif
@foreach($items as $index=>$row)<tr><td>{{ $index+1 }}</td><td>{{ $row['certificate_number'] }}</td><td class="student-name"><strong>{{ $row['name_en'] }}</strong><span class="khmer">{{ $row['name_kh'] }}</span></td><td>{{ $row['gender'] }}</td><td>{{ $row['student_code'] }}</td><td>{{ $row['grade_line'] }}</td><td><span class="grade-badge grade-a">A</span>@if($row['score_rank']!=='')<small>{{ $row['score_rank'] }}</small>@endif</td><td>{{ $row['phone'] }}</td></tr>@endforeach
@endforeach
</tbody></table>
@elseif($type==='result-list')
<table class="report-table result-table"><thead><tr><th>No.</th><th>Student Name (Khmer)</th><th>Gender</th><th>Date of Birth</th><th>Exam Center</th><th>Room</th><th>Seat</th><th>Grade</th><th>Selected</th></tr></thead><tbody>
@foreach($groups as $campus=>$items)<tr class="campus-row"><td colspan="9">{{ $campus }} Campus</td></tr>
@foreach($items as $index=>$row)<tr @class(['failed-row'=>$row['letter_grade']==='F'])><td>{{ $index+1 }}</td><td class="student-name"><strong class="khmer">{{ $row['name_kh'] }}</strong><span>{{ $row['name_en'] }}</span></td><td>{{ $row['gender'] }}</td><td class="khmer">{{ $row['dob'] }}</td><td class="khmer">{{ $row['center'] }}</td><td>{{ $row['room'] }}</td><td>{{ $row['seat'] }}</td><td><span class="grade-badge grade-{{ strtolower($row['letter_grade']?:'pending') }}">{{ $row['letter_grade']?:'—' }}</span></td><td>{{ $row['selected'] }}</td></tr>@endforeach
@endforeach
</tbody></table>
@else
<table class="report-table statistics-table"><thead><tr><th rowspan="2">{{ $type==='statistics-all'?'Campus':'Examination' }}</th>@if($type==='statistics-all')<th rowspan="2">Examination</th>@endif<th colspan="2">Registered</th><th colspan="2">Examined</th><th colspan="2">Passed</th><th colspan="2">Pass %</th>@foreach(['A','B','C','D','E','F'] as $grade)<th colspan="2" class="grade-head grade-{{ strtolower($grade) }}">{{ $grade }}</th>@endforeach</tr><tr>@for($i=0;$i<10;$i++)<th>{{ $i%2===0?'M':'F' }}</th>@endfor()@foreach(['A','B','C','D','E','F'] as $grade)<th>M</th><th>F</th>@endforeach</tr></thead><tbody>
@foreach($statistics['rows'] as $stat)<tr @class(['total-row'=>$stat['total']])><td>{{ $type==='statistics-all'?$stat['campus']:$stat['label'] }}</td>@if($type==='statistics-all')<td>{{ $stat['label'] }}</td>@endif<td>{{ $stat['registered']['male'] }}</td><td>{{ $stat['registered']['female'] }}</td><td>{{ $stat['examined']['male'] }}</td><td>{{ $stat['examined']['female'] }}</td><td>{{ $stat['passed']['male'] }}</td><td>{{ $stat['passed']['female'] }}</td><td>{{ $stat['pass_percentage']['male'] }}%</td><td>{{ $stat['pass_percentage']['female'] }}%</td>@foreach(['A','B','C','D','E','F'] as $grade)<td>{{ $stat['grades'][$grade]['male'] }}@if($type==='statistics-all')<small>{{ $stat['grade_percentages'][$grade]['male'] }}%</small>@endif</td><td>{{ $stat['grades'][$grade]['female'] }}@if($type==='statistics-all')<small>{{ $stat['grade_percentages'][$grade]['female'] }}%</small>@endif</td>@endforeach</tr>@endforeach
</tbody></table>
<section class="report-signatures"><div>Prepared by<br><br><br>________________________</div><div>Checked by<br><br><br>________________________</div><div>Approved by<br><br><br>________________________</div></section>
@endif
<footer>Western International School &nbsp; • &nbsp; {{ $title }}</footer>
</main>
</body>
</html>
