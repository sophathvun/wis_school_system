@php
    $studentOptions = collect($transcriptStudentOptions ?? []);
    $studentLabel = static fn ($student) => collect([$student->full_name_kh, $student->full_name_en])
        ->map(fn ($name) => trim((string) $name))->filter()->join(' / ')
        . ($student->student_id ? ' (' . $student->student_id . ')' : '');
    $selectedStudent = $studentOptions->firstWhere('id', (int) ($filters['transcript_student_id'] ?? 0));
@endphp
<div class="col-md report-filter-field report-transcript-student-field">
    <label class="form-label" for="reportTranscriptStudentToggle">Student Name</label>
    <div class="report-filter-combobox" data-target="reportTranscriptStudentValue">
        <button type="button" id="reportTranscriptStudentToggle" class="report-filter-toggle" @disabled($studentOptions->isEmpty())>
            <span>{{ $selectedStudent ? $studentLabel($selectedStudent) : 'All Students' }}</span><i class="ti ti-chevron-down"></i>
        </button>
        <div class="report-filter-menu">
            <input type="search" class="form-control report-filter-search" placeholder="Search name or student ID" aria-label="Search student name or ID">
            <div class="report-filter-options">
                <button type="button" data-value="">All Students</button>
                @foreach($studentOptions as $studentOption)
                    <button type="button" data-value="{{ $studentOption->id }}">{{ $studentLabel($studentOption) }}</button>
                @endforeach
            </div>
        </div>
    </div>
    <input type="hidden" name="transcript_student_id" id="reportTranscriptStudentValue" value="{{ $filters['transcript_student_id'] ?? '' }}">
</div>
