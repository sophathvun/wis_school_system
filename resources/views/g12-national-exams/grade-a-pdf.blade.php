<!doctype html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>{!! file_get_contents(resource_path('css/pages/g12-grade-a-pdf.css')) !!}</style>
</head>
<body>
    <table class="official-heading">
        <tr>
            <td class="authority muol">
                ក្រសួងអប់រំ យុវជន និងកីឡា<br>
                មន្ទីរអប់រំ យុវជន និងកីឡារាជធានីភ្នំពេញ<br>
                សាលាវេស្ទើនអន្តរជាតិ
            </td>
            <td class="motto muol">
                ព្រះរាជាណាចក្រកម្ពុជា<br>
                ជាតិ សាសនា ព្រះមហាក្សត្រ
                <div class="ornament">──────── ◇ ────────</div>
            </td>
        </tr>
    </table>
    <h1 class="muol">បញ្ជីឈ្មោះសិស្សនិទ្ទេស <span class="english">A</span> ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិ</h1>
    <h2>{{ $type==='grade-a-campus' ? 'List of Grade A Students for Grade 12 National Exams' : 'List of Students Who Achieved Grade A in the Upper Secondary School Diploma Examination' }}</h2>
    <h3><span class="muol">ឆ្នាំសិក្សា</span>/Academic Year : {{ $filters['academic_year'] ?? ($rows[0]['academic_year'] ?? 'All') }}</h3>
    <table class="candidates">
        <thead>
            <tr><th width="5%">No.</th><th width="11%">Cert. Number</th><th width="20%">Name in English</th><th width="20%">Name in Khmer</th><th width="6%">Gender</th><th width="11%">Student ID</th><th width="6%">Grade</th><th width="8%">Letter Grade</th><th width="13%">Phone</th></tr>
        </thead>
        <tbody>
            @php
                $reportGroups = $type==='grade-a-campus' ? $groups : [''=>$rows];
            @endphp
            @foreach($reportGroups as $campus=>$students)
            @if($type==='grade-a-campus')
            <tr class="campus-heading"><td colspan="9">{{ $campus }} Campus</td></tr>
            @endif
            @forelse($students as $index=>$row)
            <tr>
                <td>{{ $type==='grade-a-campus' ? $index+1 : $row['number'] }}</td>
                <td>{{ $row['certificate_number'] }}</td>
                <td class="name">{{ $row['name_en'] }}</td>
                <td class="name khmer">{{ $row['name_kh'] }}</td>
                <td class="khmer">{{ $row['gender'] }}</td>
                <td>{{ $row['student_code'] }}</td>
                <td>{{ $row['grade'] }}<br>{{ $row['campus'] }}</td>
                <td><b>A</b>@if($row['score_rank']!=='')<br>{{ $row['score_rank'] }}@endif</td>
                <td>{{ $row['phone'] }}</td>
            </tr>
            @empty
            <tr><td colspan="9">No Grade A students match the selected filters.</td></tr>
            @endforelse
            @endforeach
            @if(!$reportGroups)
            <tr><td colspan="9">No Grade A students match the selected filters.</td></tr>
            @endif
        </tbody>
    </table>
    <div class="summary">
        <div class="khmer">ចំនួនសិស្សសរុប៖ {{ $summary['total'] }}នាក់ / ស្រី {{ $summary['female'] }}នាក់</div>
        <b>Registrar's Office</b><br>{{ $reportDate->format('F d, Y') }}
    </div>
</body>
</html>
