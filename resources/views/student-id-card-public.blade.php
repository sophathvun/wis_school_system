<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($student->full_name_en ?: $student->full_name_kh ?: 'Student') }} - Student ID Card</title>
    @vite(['resources/css/pages/student-id-card-qr.css'])
</head>
<body class="student-id-public-body">
    @php
        $logoPath = $branding?->report_logo_1_path ?: $branding?->report_logo_2_path ?: $branding?->login_logo_path ?: $branding?->sidebar_logo_path;
        $studentName = $student->full_name_en ?: $student->full_name_kh ?: 'Student Name';
        $studentCode = $student->student_id ?: $student->student_no ?: 'Student ID';
        $studentPhone = $student->home_phone ?: 'Home Phone';
    @endphp

    <main class="student-id-public-page">
        <section class="student-id-public-card-wrap" aria-label="Student ID card">
            <div class="student-id-card student-id-card-preview student-id-public-card">
                <div class="student-id-card-bg"></div>
                <div class="student-id-card-logo">
                    @if ($logoPath)
                        <img src="{{ asset('storage/' . $logoPath) }}" alt="School logo">
                    @else
                        <strong>Western International School</strong>
                    @endif
                </div>

                <div class="student-id-card-photo-wrap">
                    @if ($student->photo_path)
                        <img class="student-id-card-photo" src="{{ asset('storage/' . $student->photo_path) }}" alt="{{ $studentName }}">
                    @else
                        <div class="student-id-card-photo student-id-card-photo-empty">👤</div>
                    @endif
                </div>

                <div class="student-id-card-info">
                    <div class="student-id-card-name">{{ $studentName }}</div>
                    <div class="student-id-card-code">{{ $studentCode }}</div>
                    <div class="student-id-card-phone">☎ {{ $studentPhone }}</div>
                </div>

                <div class="student-id-card-qr">
                    <img src="{{ route('student-id-card-qr.qr', $student) }}?v=public-{{ urlencode($student->id_card_qr_code ?? 'qr') }}" alt="Student QR code">
                    <span>SCAN</span>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
