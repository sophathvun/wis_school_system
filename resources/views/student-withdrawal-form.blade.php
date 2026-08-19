<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Drop-Out Form</title>
    @php
        $branding = \App\Models\BrandingSetting::current();
        $student = $history->student;
        $studentNameEn = trim($student?->full_name_en ?? '');
        $studentNameKh = trim($student?->full_name_kh ?? '');
        $studentName = $studentNameEn ?: ($studentNameKh ?: '................................................');
        $studentId = $student?->student_id ?: ($student?->student_no ?: '........................');
        $gender = strtolower((string) ($student?->gender ?? ''));
        $genderLabel = str_starts_with($gender, 'm') ? 'Male' : (str_starts_with($gender, 'f') ? 'Female' : ($student?->gender ?: ''));
        $grade = trim((string) ($history->grade?->grade ?? ''));
        $class = trim((string) ($history->schoolClass?->class_name ?? ''));
        $gradeShort = trim(preg_replace('/^grade\s*/i', '', $grade));
        $classShort = trim(preg_replace('/^grade\s*/i', '', $class));
        $gradeClass = $grade && $class
            ? ($gradeShort.(stripos($classShort, $gradeShort) === 0 ? substr($classShort, strlen($gradeShort)) : $class))
            : trim($grade.' '.$class);
        $group = $history->session?->session_short_name ?: ($history->session?->session_name ?? $history->session?->name ?? '');
        $campus = $history->campus?->campus_name_en ?? '';
        $selectedReasons = $history->reasons ?? [];
        $withdrawalDate = optional($history->effective_on)->format('d-M-Y');
        $dropoutType = $history->dropout_type ?: 'official_leave';
        $parent = $familyMembers->firstWhere('relationship_type', 'guardian')
            ?: $familyMembers->firstWhere('relationship_type', 'father')
            ?: $familyMembers->firstWhere('relationship_type', 'mother')
            ?: $familyMembers->first();
        $parentName = $history->requested_by_name ?: ($parent ? trim($parent->full_name_en ?? '') : '');
        $parentPhone = $history->requested_by_phone ?: ($parent?->phone ?? '');
        $logoPath = $branding->report_logo_1_path
            ? asset('storage/'.$branding->report_logo_1_path)
            : asset('storage/school_logo/student_profile_report_logo.png');
        $line = fn ($value = '') => $value ? e($value) : '&nbsp;';
        $checked = fn ($condition) => $condition ? 'checked' : '';
        $reasonChecked = fn ($key) => in_array($key, $selectedReasons, true);
    @endphp
    <style>
        @font-face { font-family: 'Khmer Muol'; src: url('{{ asset('fonts/khmer/KhmerOSmuollight.ttf') }}') format('truetype'); font-weight: 400; }
        @font-face { font-family: 'Khmer Body'; src: url('{{ asset('fonts/khmer/KhmerOSsiemreap.ttf') }}') format('truetype'); font-weight: 400; }
        @font-face { font-family: 'Khmer Battambang'; src: url('{{ asset('fonts/khmer/KhmerOSbattambang.ttf') }}') format('truetype'); font-weight: 700; }
        :root {
            --ink: #070707;
            --blue: #1714a8;
            --red: #d30a0a;
            --muted: #d7d7d7;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2f7; color: var(--ink); font-family: "Times New Roman", "Khmer Body", serif; }
        .toolbar { width: 280mm; max-width: calc(100vw - 24px); margin: 12px auto; display: flex; justify-content: flex-end; gap: 8px; }
        .toolbar button { border: 0; border-radius: 6px; padding: 9px 16px; background: #206bc4; color: #fff; cursor: pointer; font-family: Arial, sans-serif; }
        .sheet {
            width: 280mm;
            height: 198mm;
            margin: 0 auto 16px;
            background: white;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .16);
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            position: relative;
            overflow: hidden;
        }
        .sheet::after {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50%;
            border-left: 1px dashed #111;
        }
        .copy { padding: 4mm 5mm 4mm; position: relative; min-width: 0; overflow: hidden; }
        .copy-label { position: absolute; top: 5mm; right: 8mm; font: 10px Arial, sans-serif; color: #777; letter-spacing: .04em; text-transform: uppercase; }
        .brand { display: grid; grid-template-columns: 14mm minmax(0, 1fr); align-items: center; column-gap: 1.5mm; min-height: 15mm; }
        .brand-logo { width: 14mm; height: 14mm; object-fit: contain; justify-self: center; }
        .brand > div { min-width: 0; width: 100%; }
        .brand-kh, .brand-en { display: block; width: 100%; white-space: nowrap; }
        .brand-kh { font-family: "Khmer Muol", "Khmer Body", serif; color: var(--blue); font-size: 16px; line-height: 1.05; margin-bottom: .6mm; font-weight: 400; }
        .brand-en { color: var(--red); font-weight: 700; font-size: 7px; letter-spacing: .02em; }
        .brand-tagline { font-family: "Brush Script MT", cursive; font-size: 10px; font-style: italic; margin-top: .5mm; }
        .form-title { text-align: center; margin: -1mm 0 4mm; }
        .form-title-kh { font-family: "Khmer Muol", "Khmer Body", serif; font-size: 16px; line-height: 1.15; font-weight: 400; }
        .form-title-en { font-size: 17px; line-height: 1; }
        .row { display: flex; align-items: baseline; gap: 2mm; margin: 1.25mm 0; font-size: 11.5px; line-height: 1.08; min-width: 0; }
        .row.compact { gap: 1.4mm; flex-wrap: nowrap; }
        .label { white-space: nowrap; font-size: 11.5px; }
        .fill { flex: 1 1 0; min-width: 0; border-bottom: 1px dotted #111; min-height: 4.8mm; padding: 0 1mm .3mm; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .fill.short { flex: 0 1 14mm; }
        .fill.medium { flex: 0 1 26mm; }
        .checkbox { display: inline-flex; align-items: center; gap: .7mm; white-space: nowrap; }
        .box { width: 3.5mm; height: 3.5mm; border: 1px solid #111; border-radius: .7mm; display: inline-flex; align-items: center; justify-content: center; font-size: 9px; line-height: 1; flex: 0 0 auto; }
        .box.checked::before { content: "✓"; font-family: Arial, sans-serif; font-weight: 700; }
        .reason-heading-kh { font-family: "Khmer Battambang", "Khmer Body", serif; font-size: 12.5px; font-weight: 700; margin: 4mm 0 0; }
        .reason-heading-en { font-weight: 700; font-size: 12px; margin-bottom: .8mm; }
        .reasons { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); column-gap: 4mm; row-gap: .8mm; font-size: 9.4px; line-height: 1.05; }
        .reason { display: grid; grid-template-columns: 6.2mm minmax(0, 1fr); align-items: start; break-inside: avoid; min-height: 5.7mm; }
        .other-reason { align-items: center; }
        .other-reason-text { display: flex; align-items: baseline; gap: .8mm; min-width: 0; white-space: nowrap; }
        .other-reason-text .leader { flex: 1 1 auto; min-width: 0; }
        .reason-kh { font-family: "Khmer Battambang", "Khmer Body", serif; font-size: 10.4px; }
        .reason-en { display: block; font-size: 10.2px; }
        .full-line { grid-column: 1 / -1; }
        .optional-block { margin-top: 2.4mm; }
        .optional-block .row { margin-bottom: .15mm; }
        .kh-label { font-family: "Khmer Battambang", "Khmer Body", serif; font-size: 10.8px; }
        .en-sub { font-size: 10.4px; line-height: 1; margin-top: -1.5mm; margin-bottom: .45mm; }
        .date-section { margin-top: 2.8mm; }
        .date-title { font-family: "Khmer Battambang", "Khmer Body", serif; font-size: 12.2px; font-weight: 700; }
        .date-title span { font-family: "Times New Roman", serif; font-size: 11.8px; font-weight: 700; }
        .date-row { display: grid; grid-template-columns: 4.2mm minmax(0, 1fr) 30mm; align-items: center; gap: 1.4mm; margin-top: 1.3mm; font-size: 10.3px; }
        .date-dots { border-bottom: 1px dotted #111; text-align: center; min-height: 5mm; color: #111; }
        .placeholder { color: var(--muted); font-size: 12px; letter-spacing: .03em; }
        .comments { margin-top: 3mm; }
        .comments-title { font-family: "Khmer Battambang", "Khmer Body", serif; font-size: 12.2px; font-weight: 700; }
        .comments-title span { font-family: "Times New Roman", serif; font-size: 11.8px; font-weight: 700; }
        .comment-line { border-bottom: 1px dotted #111; min-height: 5.8mm; padding-top: .8mm; font-size: 10px; overflow: hidden; }
        .signatures { position: absolute; left: 5mm; right: 5mm; bottom: 4mm; display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 10mm; }
        .signature-line { border-top: 1px solid #111; padding-top: 1mm; font-size: 11.5px; }
        .signature-line .date { margin-top: 1.7mm; font-size: 11.5px; white-space: nowrap; }
        .leader { border-bottom: 1px dotted #111; min-width: 18mm; display: inline-block; height: 4mm; vertical-align: baseline; text-align: center; }
        @media print {
            @page { size: A4 landscape; margin: 0; }
            html, body { width: 297mm; height: 210mm; background: white; overflow: hidden; }
            .toolbar { display: none; }
            .sheet { margin: 6mm 8.5mm; box-shadow: none; width: 280mm; height: 198mm; page-break-after: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print Form</button>
        <button type="button" onclick="window.close()">Close</button>
    </div>

    <main class="sheet">
        @foreach(['Parent Copy', 'School Copy'] as $copyLabel)
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
                    @foreach($reasons as $reason)
                        <div class="reason">
                            <span class="box {{ $reasonChecked($reason['key']) ? 'checked' : '' }}"></span>
                            <span>
                                <span class="reason-kh">{{ $reason['kh'] }}</span><span class="reason-en">{{ $reason['en'] }}</span>
                            </span>
                        </div>
                    @endforeach
                    <div class="reason full-line other-reason">
                        <span class="box {{ $history->other_reason_en || $history->other_reason_kh ? 'checked' : '' }}"></span>
                        <span class="other-reason-text"><span class="reason-kh">ផ្សេងៗ</span><span class="reason-en">/Other :</span> <span class="leader">{{ $history->other_reason_en ?: $history->other_reason_kh }}</span></span>
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
                    @if($dropoutType !== 'dropped_out')
                        <div class="date-row">
                            <span class="box checked"></span>
                            <span><span class="kh-label">នឹងបោះបង់ការសិក្សាចាប់ពីថ្ងៃទី ខែ ឆ្នាំ</span>/will officially leave the school on :</span>
                            <span class="date-dots">{{ $withdrawalDate }}</span>
                        </div>
                    @else
                        <div class="date-row">
                            <span class="box checked"></span>
                            <span><span class="kh-label">បានបោះបង់ការសិក្សាតាំងពីថ្ងៃទី ខែ ឆ្នាំ</span>/has dropped out of school since :</span>
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
                        <div class="date">Date :<span class="leader"></span>/<span class="leader"></span>/<span class="leader"></span></div>
                    </div>
                    <div class="signature-line">
                        <span class="box"></span> SP / <span class="box"></span>VSP’s Name &amp; Signature
                        <div class="date">Date :<span class="leader"></span>/<span class="leader"></span>/<span class="leader"></span></div>
                    </div>
                </div>
            </section>
        @endforeach
    </main>
</body>
</html>
