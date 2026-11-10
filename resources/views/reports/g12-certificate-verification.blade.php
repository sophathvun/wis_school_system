<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Verify G12 Certificate | Western International School</title>
    @vite('resources/css/pages/g12-certificate-verification.css')
</head>
<body>
<main class="certificate-verification">
    <header><p>Western International School</p><h1>Certificate Verification</h1></header>
    <section aria-label="G12 certificate status">
        <p class="verification-status {{ $certificate->status === 'voided' ? 'is-voided' : (in_array($certificate->status,['pending_result','printed_pending']) ? 'is-pending' : '') }}">{{ match ($certificate->status) { 'voided'=>'VOIDED — This certificate is not valid', 'pending_result','printed_pending'=>'Pending Exam Result — Not valid for release', default=>'Verified G12 Certificate' } }}</p>
        <h2>High School Diploma</h2>
        <dl>
            <div><dt>Certificate Number</dt><dd>{{ $certificate->certificate_number }}</dd></div>
            <div><dt>Student Name</dt><dd>{{ $certificate->full_name_en }}@if($certificate->full_name_kh)<span class="khmer-name" lang="km">{{ $certificate->full_name_kh }}</span>@endif</dd></div>
            <div><dt>Academic Year</dt><dd>{{ $certificate->academic_year }}</dd></div>
            <div><dt>Campus</dt><dd>{{ $certificate->campus_name_en }}</dd></div>
            <div><dt>Grade</dt><dd>12{{ ltrim($certificate->class_name, '-') }}</dd></div>
            <div><dt>Given Date</dt><dd>{{ \Carbon\Carbon::parse($certificate->given_date)->format('d M Y') }}</dd></div>
        </dl>
    </section>
</main>
</body>
</html>
