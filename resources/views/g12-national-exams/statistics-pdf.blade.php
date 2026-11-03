<!doctype html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>{!! str_replace(['KhmerReport','KhmerMuolLight','Arial'],['g12siemreap','g12muol','dejavusans'],file_get_contents(resource_path('css/pages/g12-result-report.css'))) !!}</style>
    <style>{!! file_get_contents(resource_path('css/pages/g12-statistics-pdf.css')) !!}</style>
</head>
<body class="report-{{ $type }}">
    <table class="statistics-pdf-heading"><tr>
        <td class="statistics-pdf-authority khmer-muol">ក្រសួងអប់រំ យុវជន និងកីឡា<br>មន្ទីរអប់រំ យុវជន និងកីឡារាជធានីភ្នំពេញ<br>{{ $selectedSchoolName ?? 'វិ. សាលាវេស្ទើនអន្តរជាតិ' }}</td>
        <td class="statistics-pdf-motto khmer-muol">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ<div>──── ◇ ────</div></td>
    </tr></table>
    @include($type==='statistics-all' ? 'g12-national-exams._statistics-all' : 'g12-national-exams._statistics-doeys')
</body>
</html>
