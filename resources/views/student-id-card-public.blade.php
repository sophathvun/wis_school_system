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
        $logoPath = $branding?->report_logo_2_path ?: $branding?->report_logo_1_path ?: $branding?->login_logo_path ?: $branding?->sidebar_logo_path;
        $studentName = $student->full_name_en ?: $student->full_name_kh ?: 'Student Name';
        $studentCode = $student->student_id ?: $student->student_no ?: 'Student ID';
        $homePhone = $student->home_phone ?: '—';
        $isInactive = $isInactive ?? false;
        $cardStatusClass = $isWithdrawn ? 'is-withdrawn' : ($isInactive ? 'is-inactive' : '');
    @endphp

    <main class="student-id-public-page">
        <section class="student-id-public-card-wrap" aria-label="Student ID card information">
            <div class="student-id-public-info-card {{ $cardStatusClass }}">
                @if ($isWithdrawn)
                    <div class="student-id-public-stamp" aria-label="Withdrawn status">
                        <strong>WITHDRAWN</strong>
                        <span>{{ $withdrawnDateText }}</span>
                    </div>
                @elseif ($isInactive)
                    <div class="student-id-public-stamp" aria-label="Inactive status">
                        <strong>NOT ACTIVE</strong>
                        <span>Not enrolled in active academic year</span>
                    </div>
                @endif

                <div class="student-id-public-logo">
                    @if ($logoPath)
                        <img src="{{ asset('storage/' . $logoPath) }}" alt="School logo">
                    @else
                        <strong>Western International School</strong>
                    @endif
                </div>

                <div class="student-id-public-photo-wrap">
                    @if ($student->photo_path)
                        <img class="student-id-public-photo" src="{{ asset('storage/' . $student->photo_path) }}" alt="{{ $studentName }}">
                    @else
                        <div class="student-id-public-photo student-id-public-photo-empty">&#128100;</div>
                    @endif
                </div>

                <h1 class="student-id-public-name">{{ $studentName }}</h1>

                <div class="student-id-public-details">
                    <div class="student-id-public-row">
                        <span class="student-id-public-label">Student ID</span>
                        <span class="student-id-public-value">{{ $studentCode }}</span>
                    </div>

                    @if (! $isWithdrawn && ! $isInactive)
                        <div class="student-id-public-row">
                            <span class="student-id-public-label">Home Phone</span>
                            <span class="student-id-public-value">{{ $homePhone }}</span>
                        </div>
                        <div class="student-id-public-row">
                            <span class="student-id-public-label">Mother Phone</span>
                            <span class="student-id-public-value">{{ $motherPhone }}</span>
                        </div>
                        <div class="student-id-public-row">
                            <span class="student-id-public-label">Father Phone</span>
                            <span class="student-id-public-value">{{ $fatherPhone }}</span>
                        </div>
                    @endif

                    <div class="student-id-public-row">
                        <span class="student-id-public-label">Academic Year</span>
                        <span class="student-id-public-value">{{ $academicYearText }}</span>
                    </div>
                    <div class="student-id-public-row">
                        <span class="student-id-public-label">Campus</span>
                        <span class="student-id-public-value">{{ $campusText }}</span>
                    </div>
                    <div class="student-id-public-row">
                        <span class="student-id-public-label">Grade</span>
                        <span class="student-id-public-value">{{ $gradeText }}</span>
                    </div>

                    @if ($isWithdrawn)
                        <div class="student-id-public-status-note">
                            This student ID card is no longer active. Please contact Western International School for verification.
                        </div>
                    @elseif ($isInactive)
                        <div class="student-id-public-status-note">
                            This student is not enrolled in the active academic year. Please contact Western International School for verification.
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </main>
</body>
</html>
