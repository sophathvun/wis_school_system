<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Drop-Out Form</title>
    <style>
        {!! file_get_contents(resource_path('css/pages/student-withdrawal-form.css')) !!}
    </style>
    @php
        $branding = \App\Models\BrandingSetting::current();
        $student = $history->student;
        $studentNameEn = trim($student?->full_name_en ?? '');
        $studentNameKh = trim($student?->full_name_kh ?? '');
        $studentName = $studentNameEn ?: ($studentNameKh ?: '................................................');
        $studentId = $student?->student_id ?: ($student?->student_no ?: '........................');
        $gender = strtolower((string) ($student?->gender ?? ''));
        $genderLabel = str_starts_with($gender, 'm')
            ? 'Male'
            : (str_starts_with($gender, 'f')
                ? 'Female'
                : ($student?->gender ?:
                ''));
        $grade = trim((string) ($history->grade?->grade ?? ''));
        $class = trim((string) ($history->schoolClass?->class_name ?? ''));
        $gradeShort = trim(preg_replace('/^grade\s*/i', '', $grade));
        $classShort = trim(preg_replace('/^grade\s*/i', '', $class));
        $gradeClass =
            $grade && $class
                ? $gradeShort .
                    (stripos($classShort, $gradeShort) === 0 ? substr($classShort, strlen($gradeShort)) : $class)
                : trim($grade . ' ' . $class);
        $group =
            $history->session?->session_short_name ?:
            $history->session?->session_name ?? ($history->session?->name ?? '');
        $campus = $history->campus?->campus_name_en ?? '';
        $selectedReasons = $history->reasons ?? [];
        $withdrawalDate = optional($history->effective_on)->format('d-M-Y');
        $dropoutType = $history->dropout_type ?: 'official_leave';
        $parent =
            $familyMembers->firstWhere('relationship_type', 'guardian') ?:
            $familyMembers->firstWhere('relationship_type', 'father') ?:
            $familyMembers->firstWhere('relationship_type', 'mother') ?:
            $familyMembers->first();
        $parentName = $history->requested_by_name ?: ($parent ? trim($parent->full_name_en ?? '') : '');
        $parentPhone = $history->requested_by_phone ?: $parent?->phone ?? '';
        $logoPath = $branding->report_logo_1_path
            ? asset('storage/' . $branding->report_logo_1_path)
            : asset('storage/school_logo/student_profile_report_logo.png');
        $line = fn($value = '') => $value ? e($value) : '&nbsp;';
        $checked = fn($condition) => $condition ? 'checked' : '';
        $reasonChecked = fn($key) => in_array($key, $selectedReasons, true);
    @endphp

</head>

<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print Form</button>
        <button type="button" onclick="window.close()">Close</button>
    </div>

    <main class="sheet">
        @foreach (['Parent Copy', 'School Copy'] as $copyLabel)
            <section class="copy">
                <div class="copy-label">{{ $copyLabel }}</div>
                <header class="brand">
                    <img class="brand-logo" src="{{ $logoPath }}" alt="Western International School">
                    <div>
                        <div class="brand-kh">វេស្ទើនអន្តរជាតិ</div>
                        <div class="brand-en">WESTERN INTERNATIONAL SCHOOL</div>
                        <div class="brand-tagline">Start your future today!</div>
                    </div>
                </header>

                <div class="form-title">
                    <div class="form-title-kh">ពាក្យសុំបោះបង់ការសិក្សា</div>
                    <div class="form-title-en">Drop-Out Form</div>
                </div>

                <div class="row">
                    <span class="label">Student Name:</span>
                    <span class="fill">{!! $line($studentName) !!}</span>
                    <span class="label">Student ID :</span>
                    <span class="fill medium">{!! $line($studentId) !!}</span>
                </div>
                <div class="row compact">
                    <span class="label">Gender :</span>
                    <span class="fill short">{!! $line($genderLabel) !!}</span>
                    <span class="label">Grade:</span>
                    <span class="fill short">{!! $line($gradeClass) !!}</span>
                    <span class="label">Group:</span>
                    <span class="fill short">{!! $line($group) !!}</span>
                    <span class="label">Campus :</span>
                    <span class="fill short">{!! $line($campus) !!}</span>
                </div>
                <div class="row compact">
                    <span class="label">Parent/Guardian Name:</span>
                    <span class="fill">{!! $line($parentName) !!}</span>
                    <span class="label">Phone Number:</span>
                    <span class="fill medium">{!! $line($parentPhone) !!}</span>
                </div>

                <div class="reason-heading-kh">មូលហេតុដែលបណ្តាលឱ្យបោះបង់ការសិក្សា (អាចជ្រើសរើសបានច្រើនចំណុច)</div>
                <div class="reason-heading-en">Reason for Dropping out of school (check all that apply)</div>
                <div class="reasons">
                    @foreach ($reasons as $reason)
                        <div class="reason">
                            <span class="box {{ $reasonChecked($reason['key']) ? 'checked' : '' }}"></span>
                            <span>
                                <span class="reason-kh">{{ $reason['kh'] }}</span><span
                                    class="reason-en">{{ $reason['en'] }}</span>
                            </span>
                        </div>
                    @endforeach
                    <div class="reason full-line other-reason">
                        <span
                            class="box {{ $history->other_reason_en || $history->other_reason_kh ? 'checked' : '' }}"></span>
                        <span class="other-reason-text"><span class="reason-kh">ផ្សេងៗ</span><span
                                class="reason-en">/Other :</span> <span
                                class="leader">{{ $history->other_reason_en ?: $history->other_reason_kh }}</span></span>
                    </div>
                </div>

                <div class="optional-block">
                    <div class="row">
                        <span class="kh-label">ឈ្មោះសាលាដែលត្រូវផ្ទេរទៅ (ប្រសិនជាមាន) :</span>
                        <span class="fill">{!! $line($history->new_school) !!}</span>
                    </div>
                    <div class="en-sub">Name of new school (Optional)</div>
                    <div class="row">
                        <span class="kh-label">អាសយដ្ឋានសាលាដែលត្រូវផ្ទេរទៅ (ប្រសិនជាមាន) :</span>
                        <span class="fill">{!! $line($history->new_school_address) !!}</span>
                    </div>
                    <div class="en-sub">School Address (Optional)</div>
                </div>

                <div class="date-section">
                    <div class="date-title">កាលបរិច្ឆេទបោះបង់ការសិក្សា / <span>Drop-Out Date:</span></div>
                    @if ($dropoutType !== 'dropped_out')
                        <div class="date-row">
                            <span class="box checked"></span>
                            <span><span class="kh-label">នឹងបោះបង់ការសិក្សាចាប់ពីថ្ងៃទី ខែ ឆ្នាំ</span>/will officially
                                leave the school on :</span>
                            <span class="date-dots">{{ $withdrawalDate }}</span>
                        </div>
                    @else
                        <div class="date-row">
                            <span class="box checked"></span>
                            <span><span class="kh-label">បានបោះបង់ការសិក្សាតាំងពីថ្ងៃទី ខែ ឆ្នាំ</span>/has dropped out
                                of school since :</span>
                            <span class="date-dots">{{ $withdrawalDate }}</span>
                        </div>
                    @endif
                </div>

                <div class="comments">
                    <div class="comments-title">យោបល់បន្ថែម / <span>Additional Comments:</span></div>
                    <div class="comment-line">{{ $history->additional_comments ?: $history->notes }}</div>
                    <div class="comment-line"></div>
                </div>

                <div class="signatures">
                    <div class="signature-line">
                        Parent/Guardian Signature
                        <div class="date">Date :<span class="leader"></span>/<span class="leader"></span>/<span
                                class="leader"></span></div>
                    </div>
                    <div class="signature-line">
                        <span class="box"></span> SP / <span class="box"></span>VSP’s Name &amp; Signature
                        <div class="date">Date :<span class="leader"></span>/<span class="leader"></span>/<span
                                class="leader"></span></div>
                    </div>
                </div>
            </section>
        @endforeach
    </main>
</body>

</html>
