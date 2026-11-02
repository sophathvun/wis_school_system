<!doctype html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>{!! file_get_contents(resource_path('css/pages/g12-grade-a-pdf.css')) !!}</style>
    <style>{!! file_get_contents(resource_path('css/pages/g12-result-list-pdf.css')) !!}</style>
</head>
<body>
    <table class="official-heading">
        <tr>
            <td class="authority muol">ក្រសួងអប់រំ យុវជន និងកីឡា<br>មន្ទីរអប់រំ យុវជន និងកីឡារាជធានីភ្នំពេញ<br>សាលាវេស្ទើនអន្តរជាតិ</td>
            <td class="motto muol">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ<div class="ornament">──────── ◇ ────────</div></td>
        </tr>
    </table>
    <h1 class="muol">បញ្ជីលទ្ធផលប្រឡងសញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិ</h1>
    @if($exam_session)
    <h2 class="muol">សម័យប្រឡង៖ {{ $exam_session }}</h2>
    @else
    <h3><span class="muol">ឆ្នាំសិក្សា</span>/Academic Year : {{ $filters['academic_year'] ?? ($rows[0]['academic_year'] ?? 'All') }}</h3>
    @endif
    <table class="candidates results">
        <thead><tr>
            @foreach(['លរ','គោត្តនាម និងនាមខ្លួន','ភេទ','ថ្ងៃខែឆ្នាំកំណើត','មណ្ឌលប្រឡង','ល.បន្ទប់','លេខតុ','និទ្ទេស','ថ្នាក់ជម្រើស'] as $label)
            <th class="khmer">{{ $label }}</th>
            @endforeach
        </tr></thead>
        <tbody>
            @foreach($groups as $school=>$students)
            <tr class="school-heading"><td colspan="9" class="muol">{{ $school }}</td></tr>
            @foreach($students as $index=>$student)
            <tr @class(['failed'=>$student['letter_grade']==='F'])>
                <td>{{ $index+1 }}</td>
                <td class="name khmer">{{ $student['name_kh'] }}</td>
                <td class="khmer">{{ $student['gender'] }}</td>
                <td>{{ $student['dob_display'] }}</td>
                <td class="name khmer" nowrap="nowrap">{{ $student['center'] }}</td>
                <td>{{ $student['room'] }}</td>
                <td>{{ $student['seat'] }}</td>
                <td>{{ $student['letter_grade'] }}</td>
                <td class="khmer" nowrap="nowrap">{{ $student['selected']==='Social Science'?'វិទ្យាសាស្ត្រសង្គម':'វិទ្យាសាស្ត្រ' }}</td>
            </tr>
            @endforeach
            @endforeach
            @if(!$rows)<tr><td colspan="9">No students match the selected filters.</td></tr>@endif
        </tbody>
    </table>
</body>
</html>
