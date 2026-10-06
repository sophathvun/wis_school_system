<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Verify G9 Certificate | Western International School</title>
    @vite('resources/css/pages/g9-certificate-verification.css')
</head>
<body>
<main class="certificate-verification">
    <header><p>Western International School</p><h1>Certificate Verification</h1></header>
    <section aria-label="Verified certificate">
        <p class="verification-status">Verified G9 Certificate</p>
        <h2>Junior High School Diploma</h2>
        <dl>
            <div><dt>Certificate Number</dt><dd>{{ $certificate->certificate_number }}</dd></div>
            <div><dt>Student Name</dt><dd>{{ $certificate->full_name_en }}@if($certificate->full_name_kh)<span class="khmer-name" lang="km">{{ $certificate->full_name_kh }}</span>@endif</dd></div>
            <div><dt>Academic Year</dt><dd>{{ $certificate->academic_year }}</dd></div>
            <div><dt>Campus</dt><dd>{{ $certificate->campus_name_en }}</dd></div>
            <div><dt>Grade</dt><dd>9{{ ltrim($certificate->class_name, '-') }}</dd></div>
            <div><dt>Given Date</dt><dd>{{ \Carbon\Carbon::parse($certificate->given_date)->format('d M Y') }}</dd></div>
        </dl>
    </section>
</main>
</body>
</html>
