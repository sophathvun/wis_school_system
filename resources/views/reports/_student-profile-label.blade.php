@php
    $labelRows = collect($enrollments ?? [])->values();
    $firstLabelRow = $labelRows->first();
    $labelGrade = isset($firstLabelRow) ? (trim((string) ($firstLabelRow->grade?->grade_short_name ?: $firstLabelRow->grade?->grade)) . trim((string) ($firstLabelRow->schoolClass?->class_name ?? ''))) : '';
    if (isset($firstLabelRow) && preg_match('/^[A-Za-z]/', trim((string) ($firstLabelRow->grade?->grade_short_name ?: $firstLabelRow->grade?->grade))) && filled($firstLabelRow->schoolClass?->class_name)) {
        $labelGrade = trim((string) ($firstLabelRow->grade?->grade_short_name ?: $firstLabelRow->grade?->grade)) . $firstLabelRow->schoolClass->class_name;
    }
    $labelCampus = $firstLabelRow?->campus?->campus_name_en ?: '';
@endphp
<div class="student-profile-label-report">
    @if($labelRows->isEmpty())
        <div class="empty text-center text-secondary py-4">No students found.</div>
    @else
        <div class="profile-label-header">
            <div><span>Grade</span><strong>: {{ $labelGrade ?: '-' }}</strong></div>
            <div><span>Campus</span><strong>: {{ $labelCampus ?: '-' }}</strong></div>
        </div>
        <div class="profile-label-grid">
            @foreach($labelRows as $row)
                @php
                    $labelName = strtoupper(trim((string) ($row->student?->full_name_en ?: $row->student?->full_name_kh ?: '-')));
                    $labelId = trim((string) ($row->student?->student_id ?: $row->student?->student_no ?: '-'));
                @endphp
                <div class="profile-label-item">
                    <div class="profile-label-name">{{ $labelName }}</div>
                    <div class="profile-label-id">{{ $labelId }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>
<style>
    .student-profile-label-report{min-width:700px;color:#000;background:#fff;padding:8mm 7mm 10mm;font-family:Arial,Helvetica,sans-serif}
    .profile-label-grid{display:grid;grid-template-columns:repeat(3,55mm);align-items:start;gap:3.4mm 7mm}
    .profile-label-item{width:55mm;height:18.5mm;border:1px solid #000;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:1.5mm 1.5mm;gap:1.5mm;break-inside:avoid;background:#fff;font-weight:600;line-height:1.08}
    .profile-label-name{font-size:14px;line-height:1.12;max-width:100%;word-break:break-word}
    .profile-label-id{font-size:14px;line-height:1.1}
    .profile-label-header{margin:0 0 5mm;font-family:"Times New Roman",Times,serif;font-size:16px;line-height:1.35;color:#000;text-align:left}
    .profile-label-header span{display:inline-block;min-width:30mm}.profile-label-header strong{font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:800}
    [data-bs-theme="dark"] .report-preview-body .student-profile-label-report,body.dark-mode .report-preview-body .student-profile-label-report{background:#fff;color:#000;border-radius:6px}
    @media screen and (max-width:767px){.student-profile-label-report{transform:scale(.48);transform-origin:top left;width:208%;margin-bottom:-45%;min-width:700px}}
</style>
