@php
    $canImport=$exam && $workflow->central(auth()->user(),$exam,'import-history');
    $canTransferExport=$exam && \App\Support\ScholarshipPermissions::allows(auth()->user(),'export');
    $importBlocked=$exam && !$exam->isHistorical() && ($exam->seats_locked_at || $exam->published_at || $exam->candidates()->whereHas('scores')->exists() || $exam->awards()->exists());
    $hasFourSampleSubjects=!$exam || collect(range(1,12))->contains(fn($grade)=>$workflow->applicableSubjects($exam,$grade)->unique('name')->count()>=4);
@endphp
<div data-scholarship-transfer data-preview-url="{{ route('western-scholarship.transfer.preview') }}" data-import-url="{{ route('western-scholarship.transfer.import') }}">
    <div class="scholarship-transfer-intro"><div><h3 class="mb-1">Scholarship Records</h3><p class="text-secondary mb-0">Import outstanding students, exam scores, and original awards from your previous system.</p></div><i class="ti ti-file-spreadsheet" aria-hidden="true"></i></div>
    <form method="GET" action="{{ route('western-scholarship.index') }}" class="scholarship-transfer-toolbar mt-3">
        <input type="hidden" name="tab" value="data-transfer">
        <div class="scholarship-field"><label for="transferExamYear">Academic Year</label><select id="transferExamYear" name="exam_id" class="form-select" data-scholarship-select data-transfer-year><option value="">Select a configured year</option>@foreach($exams as $item)<option value="{{ $item->id }}" @selected($exam?->id===$item->id)>{{ $item->academicYear->academic_year }}{{ $item->isHistorical()?' — Historical':'' }}</option>@endforeach</select></div>
        <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" download="scholarship-sample-8-records.csv" href="{{ route('western-scholarship.transfer.sample',$exam?['exam_id'=>$exam->id]:[]) }}"><i class="ti ti-download me-1" aria-hidden="true"></i>Sample CSV · 8 Records</a><a class="btn btn-outline-success" download="scholarship-sample-8-records.xlsx" href="{{ route('western-scholarship.transfer.sample',['format'=>'xlsx']+($exam?['exam_id'=>$exam->id]:[])) }}"><i class="ti ti-file-spreadsheet me-1" aria-hidden="true"></i>Sample Excel · 8 Records</a>@if($canTransferExport)<a class="btn btn-primary" href="{{ route('western-scholarship.transfer.export',$exam) }}"><i class="ti ti-file-export me-1" aria-hidden="true"></i>Export CSV</a>@endif</div>
    </form>
    <p class="small text-secondary mt-2 mb-0">Open the Excel sample to edit in Microsoft Excel. Save as <strong>CSV UTF-8 (Comma delimited)</strong> before uploading. To open a CSV manually, use Excel → Data → From Text/CSV.</p>
    @if(!$hasFourSampleSubjects)<div class="alert alert-info mt-3">The selected year has fewer than four subjects for any single grade. The eight-record sample uses the available subjects; configure four subjects for a grade to see all four subject-award examples.</div>@endif
    @if(!$exams->count())<div class="alert alert-info mt-3">Set up the old regular academic year and its subjects in Scholarship Settings first. Then return here to import its students.</div>@endif
    @if($exam?->isHistorical())<div class="alert alert-success mt-3">Historical records: original totals, exam ranks, and awards are preserved. Corrections require another validated import; the normal exam flow cannot overwrite them.</div>@endif
    @if($importBlocked)<div class="alert alert-warning mt-3">This year already has confirmed seats, scores, or awards. You can export its records. Historical import cannot replace an active or published exam.</div>@endif
    @if($exam && !$canImport)<div class="alert alert-info mt-3">Historical import requires Import / Export tab access and Import Historical Scholarship Records permission for every participating campus.</div>@endif
    <details class="scholarship-transfer-guide mt-3"><summary class="fw-semibold">CSV preparation guide</summary><ul class="mt-2 mb-0">
        <li>Configure each old year with its actual exam subjects, maximum scores, and weights. Download that year’s sample, then replace the eight examples with your real records. Save as <strong>CSV UTF-8</strong>.</li>
        <li><code>academic_year</code>, <code>student_id</code>, and <code>class_rank</code> (1, 2, or 3) are required. Each student must already have a matching enrollment in that old year. Names and placement come from that enrollment. Optional campus, grade, class, and group values must match it.</li>
        <li>Use <code>score:Subject Name</code> columns for any configured subjects. Use <code>A</code> for a subject absence (counts as zero). Blank means unknown; zero means an actual score of zero. Complete scores are checked against total and percentage. Totals-only and awards-only records are supported.</li>
        <li>Award categories: <code>total_1</code>, <code>total_2</code>, <code>total_3</code>, <code>subject</code>, or the original award name. Subject awards require <code>award_subject</code>. Enter percentages as numbers, such as 100 or 25.</li>
        <li>The eight-record example shows 1st Place (<code>total_1</code>, blank subject, 100%), 2nd Place (<code>total_2</code>, blank subject, 75%), 3rd Place (<code>total_3</code>, blank subject, 50%), four Best in Subject awards (<code>subject</code>, the subject name, 25%), and one student with no award (leave category, subject, and percentage blank).</li>
        <li>Exam Code, Exam Center, Room No., Seat No., EOY average, and results may be blank if unavailable. Supply Room and Seat together. Use <code>absent</code> = 1 for absence from all subjects (all scores count as zero), otherwise 0. Exports retain individual absences as <code>A</code>.</li>
        <li><code>exam_center_location</code> is the city or province used before signature dates, such as Phnom Penh or Battambang. Supply <code>exam_center</code> with it. Repeat the same location for every row for that center. Blank or missing location keeps the saved location; new centers default to Phnom Penh.</li>
        <li><code>subject_order</code> preserves exam sessions, independently of the score column order. Copy the JSON from the sample or export: <code>{"band":["English","Mathematics","Science","Khmer"],"grade":null}</code>. The band list contains every subject for Grades 1–6 or 7–12. Set <code>grade</code> to null to follow that band, or to an ordered list of every applicable subject for this student's grade for a custom order. Subject names must match Scholarship Settings. If a name occurs in multiple grade ranges, keep the name/grade_from/grade_to objects provided by the sample.</li>
        <li>Blank or missing <code>subject_order</code> keeps current exam orders. Older CSV files remain supported. Rows for the same band must agree on its default order, and rows for the same grade must agree on its override. Changing an existing center or grade's report settings requires Replace Matching Records; review the shared settings changes before confirming. Scores, weights, and subject definitions are not changed by these columns.</li>
    </ul></details>
    @if($canImport && !$importBlocked)
    <form class="scholarship-transfer-upload mt-3" data-transfer-upload enctype="multipart/form-data" novalidate>
        @csrf<input type="hidden" name="exam_id" value="{{ $exam->id }}">
        <label for="scholarshipImportCsv" class="form-label fw-semibold">Historical Scholarship CSV</label>
        <input id="scholarshipImportCsv" type="file" class="form-control" name="file" accept=".csv,text/csv" required aria-describedby="scholarshipImportFileError"><div id="scholarshipImportFileError" class="invalid-feedback" data-transfer-file-error></div>
        <label class="form-check mt-3"><input type="checkbox" class="form-check-input" name="replace" value="1"><span class="form-check-label">Replace matching records after preview</span></label>
        <p class="small text-secondary">Default: add new students only. Replacement replaces the selected students’ rank, scores, seats, and awards with the CSV values, including blanks. Other students and other academic years are retained. Maximum 5 MB / 5,000 students.</p>
        <button type="submit" class="btn btn-primary" data-transfer-preview-button><i class="ti ti-eye me-1" aria-hidden="true"></i>Validate & Preview</button>
    </form>
    <section class="mt-4" data-transfer-preview hidden aria-live="polite">
        <h3>Import Preview</h3><p data-transfer-summary class="fw-semibold"></p>
        <div class="alert alert-danger" data-transfer-errors hidden></div>
        <section class="scholarship-transfer-guide mb-3" data-transfer-settings-preview hidden><h4 class="mb-2">Shared Report Settings Changes</h4><p class="small text-secondary">These changes apply to every report for the listed center or grade, including students outside this file.</p><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Setting</th><th>Current</th><th>After Import</th></tr></thead><tbody data-transfer-settings-rows></tbody></table></div></section>
        <div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Line</th><th>Student</th><th>Grade / Rank</th><th>Total</th><th>Award %</th><th>Action</th></tr></thead><tbody data-transfer-rows></tbody></table></div>
        <p class="text-secondary small">Preview shows the first 100 valid records and first 100 errors. No records are imported until every error is corrected.</p>
        <form data-transfer-confirm hidden>
            @csrf<input type="hidden" name="token">
            <label class="form-check mt-3"><input type="checkbox" class="form-check-input" name="acknowledge" value="1" required><span class="form-check-label">I verified these records and any shared report settings changes. Import this year as a protected historical archive, preserving original ranks and awards.</span></label>
            <button type="submit" class="btn btn-success mt-2" data-transfer-import-button><i class="ti ti-file-import me-1" aria-hidden="true"></i>Confirm Import</button>
        </form>
    </section>
    @endif
</div>
