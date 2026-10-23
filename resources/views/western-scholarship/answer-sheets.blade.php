@extends('layouts.app')
@section('title','Scholarship Answer Sheets')
@section('content')
<div class="scholarship-workspace scholarship-tabs-collapsed" data-scholarship-page data-answer-sheets>
    <aside class="card scholarship-navigation">
        <div class="card-header scholarship-card-header scholarship-tabs-header"><h3 class="card-title">WIS Scholarship</h3><button type="button" class="btn btn-icon btn-outline-primary scholarship-tabs-toggle" aria-expanded="false" aria-controls="scholarshipTabList" aria-label="Expand scholarship tabs"><i class="ti ti-layout-sidebar-left-expand"></i></button></div>
        <nav class="list-group list-group-flush" id="scholarshipTabList" aria-label="WIS Scholarship sections">
            @foreach($tabs as $key=>[$label,$icon])<a href="{{ route('western-scholarship.index',array_filter(['academic_year_id'=>$year,'tab'=>$key])) }}" class="list-group-item list-group-item-action {{ $key===$tab?'active':'' }}" aria-label="{{ $label }}" data-scholarship-tab-label="{{ $label }}"><i class="ti {{ $icon }}" aria-hidden="true"></i><span class="scholarship-tab-label">{{ $label }}</span></a>@endforeach
        </nav>
    </aside>
    <main class="scholarship-forms">
        <section class="card mb-3" data-answer-filter-card>
            <div class="card-header scholarship-card-header"><h3 class="card-title"><i class="ti ti-filter me-2"></i>Datas Filter</h3><button type="button" class="btn btn-icon btn-outline-primary scholarship-filter-toggle ms-auto" data-answer-filter-toggle aria-expanded="true" aria-controls="answerFilters" aria-label="Collapse answer sheet filters"><i class="ti ti-chevron-up"></i></button></div>
            <div class="card-body" id="answerFilters"><form method="GET" data-answer-filters action="{{ route('western-scholarship.index') }}">
                <input type="hidden" name="tab" value="answer-sheets">
                <input type="hidden" id="answerScansPerPage" name="scan_per_page" value="{{ $scans->perPage() }}">
                <div class="row g-3">
                    <div class="col-md-3 scholarship-field"><label class="form-label" for="answerYear">Academic Year</label><select class="form-select" id="answerYear" name="academic_year_id"><option value="">Select year</option>@foreach($years as $y)<option value="{{ $y->id }}" @selected($year==$y->id)>{{ $y->academic_year }}</option>@endforeach</select></div>
                    <div class="col-md-3 scholarship-field"><label class="form-label" for="answerPaper">Grade / Subject / Paper</label><select class="form-select" id="answerPaper" name="paper_id"><option value="">Select paper</option>@foreach($papers as $p)<option value="{{ $p->id }}" @selected($paper?->id===$p->id)>Grade {{ $p->grade_number }} · {{ $p->subject->name }} · {{ $p->paper_version }}</option>@endforeach</select></div>
                    <div class="col-md-3 scholarship-field"><label class="form-label" for="answerCenter">Exam Center</label><select class="form-select" id="answerCenter" name="center"><option value="">All Exam Centers</option>@foreach($centers as $center)<option value="{{ $center['id'] }}" @selected(isset($input['center'])&&$input['center']==$center['id'])>{{ $center['name'] }}</option>@endforeach</select></div>
                    <div class="col-md-3 scholarship-field"><label class="form-label" for="answerRoom">Room</label><select class="form-select" id="answerRoom" name="room"><option value="">All Rooms</option>@foreach($rooms as $room)<option value="{{ $room }}" @selected(($input['room']??null)==$room)>Room {{ $room }}</option>@endforeach</select></div>
                </div><noscript><button class="btn btn-primary mt-2">Filter</button></noscript>
            </form></div>
        </section>
        <section class="card mb-3">
            <div class="card-header scholarship-card-header"><h3 class="card-title"><i class="ti ti-file-check me-2"></i>Answer Sheets</h3><div class="d-flex flex-wrap gap-2 ms-auto">
                @if($exam&&$permissions['answer-paper'])<button class="btn btn-sm btn-outline-light" type="button" data-answer-setup><i class="ti ti-settings me-1"></i>Paper Setup</button>@endif
                @if($paper&&($permissions['answer-key']||$permissions['answer-key-approve']))<button class="btn btn-sm btn-outline-light" type="button" data-answer-key><i class="ti ti-key me-1"></i>Answer Key</button>@endif
                @if($paper&&$permissions['answer-key-print'])<button class="btn btn-sm btn-outline-light" type="button" data-teacher-key-print @disabled(!$paper->question_count||$exam->published_at||$exam->isHistorical())><i class="ti ti-printer me-1"></i>Print Teacher Key</button>@endif
                @if($paper&&$permissions['answer-print'])<button class="btn btn-sm btn-outline-light" type="button" data-answer-print @disabled(!$exam->seats_locked_at||!$paper->latestKey)><i class="ti ti-printer me-1"></i>Print Sheets</button>@endif
            </div></div>
            <div class="card-body">
                @if(!$exam)<p class="text-secondary mb-0">Configure a scholarship exam in Scholarship Settings first.</p>
                @elseif(!$paper)<p class="text-secondary mb-0">No papers yet. Staff with Paper Setup permission can create a paper for each grade and subject.</p>
                @else
                    <div class="d-flex flex-wrap gap-3"><span><strong>Grade {{ $paper->grade_number }} · {{ $paper->subject->name }}</strong> · Paper {{ $paper->paper_version }}</span><span>{{ $paper->question_count??'Not configured' }} questions</span><span>Choices: {{ implode(' / ',$paper->answer_choices??[]) }}</span><span>Maximum score: {{ $paper->subject->max_score }}</span><span>Scores set per question in Answer Key</span><span class="badge {{ $paper->latestKey?'bg-green-lt':'bg-yellow-lt' }}">{{ $paper->latestKey?'Approved key · Revision '.$paper->latestKey->revision:'Answer key not approved' }}</span></div>
                    <p class="text-secondary mt-3 mb-0">Confirm seats and approve a key before printing. Use A4 at 100% / Actual size. Every scanned sheet requires verification before its draft score is saved.</p>
                @endif
            </div>
        </section>
        <section class="card mb-3" id="answerSheetListPanel" data-answer-sheet-list aria-labelledby="answerSheetListTitle"><div class="card-header scholarship-card-header d-flex flex-wrap gap-2"><h3 class="card-title" id="answerSheetListTitle"><i class="ti ti-list-details me-2"></i>Answer Sheet List</h3><div class="ms-auto d-flex flex-wrap gap-2">@if($exam)@include('western-scholarship._reseating-reset-button',['canReset'=>$permissions['reset-for-reseating'],'resetClass'=>'btn-outline-light'])@endif<button type="button" class="btn btn-sm btn-outline-light" data-answer-list-fullscreen aria-controls="answerSheetListPanel" aria-pressed="false"><i class="ti ti-maximize me-2"></i><span>Full Screen</span></button></div></div>
            <div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>No.</th><th>Grade / Subject</th><th>Paper</th><th>Questions / Choices</th><th>Key Status</th><th>Student Links</th><th>Actions</th></tr></thead><tbody>
                @forelse($papers as $p)
                    @php($linked=$p->linked_students_count>0||$p->scores_exists||$p->has_scan_links)
                    @php($locked=$exam->published_at||$exam->isHistorical())
                    @php($recorded=$p->scores_exists||$p->has_scan_links||$p->has_sheet_scan_links||$p->has_subject_scores)
                    <tr data-answer-paper-row="{{ $p->id }}" class="{{ $paper?->id===$p->id?'bg-azure-lt':'' }}">
                        <td>{{ $loop->iteration }}</td><td><strong>Grade {{ $p->grade_number }}</strong><div>{{ $p->subject->name }}</div></td><td>{{ $p->paper_version }}</td>
                        <td>{{ $p->question_count??'Not configured' }} questions<div class="small text-secondary">{{ implode(', ',$p->answer_choices??[]) }}</div></td>
                        <td><span class="badge {{ $p->latestKey?'bg-green-lt':'bg-yellow-lt' }}">{{ $p->latestKey?'Approved · Rev. '.$p->latestKey->revision:($p->draft_answers?'Draft key':'No answer key') }}</span></td>
                        <td>@if($linked)<span class="badge bg-blue-lt">Linked</span><div class="small">{{ $p->linked_students_count }} student(s)</div>@else<span class="badge bg-secondary-lt">Not linked</span>@endif</td>
                        <td><div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-sm btn-outline-primary" type="button" data-answer-paper-view="{{ $p->id }}" aria-label="View answer sheet"><i class="ti ti-eye me-1"></i>View</button>
                            @if($permissions['answer-key']||$permissions['answer-key-approve'])<button class="btn btn-sm btn-outline-primary" type="button" data-answer-paper-key="{{ $p->id }}" aria-label="Answer key for Grade {{ $p->grade_number }} {{ $p->subject->name }} Paper {{ $p->paper_version }}"><i class="ti ti-key me-1"></i>Answer Key</button>@endif
                            @if($permissions['answer-link'])<button class="btn btn-sm btn-outline-primary" type="button" data-answer-paper-link="{{ $p->id }}" aria-label="Link Grade {{ $p->grade_number }} students to {{ $p->subject->name }} Paper {{ $p->paper_version }}" @disabled($locked||!$exam->seats_locked_at||!$p->latestKey) title="{{ $locked?'This exam is locked.':(!$exam->seats_locked_at||!$p->latestKey?'Confirm seats and approve this paper’s answer key first.':'Link students in this grade without printing. Existing links are kept.') }}"><i class="ti ti-user-plus me-1"></i>Link Students</button>@endif
                            @if($permissions['answer-unlink'])<button class="btn btn-sm btn-outline-warning" type="button" data-answer-paper-unlink="{{ $p->id }}" aria-label="Unlink students from Grade {{ $p->grade_number }} {{ $p->subject->name }} Paper {{ $p->paper_version }}" @disabled($locked||!$p->linked_students_count||$recorded) title="{{ $locked?'This exam is locked.':($recorded?'A scan or recorded subject score prevents unlinking.':(!$p->linked_students_count?'No students are linked.':'Unlink unscanned students in your permitted campuses. Old printed sheets will become invalid.')) }}"><i class="ti ti-user-minus me-1"></i>Unlink Students</button>@endif
                            @if($permissions['answer-paper'])<button class="btn btn-sm btn-outline-primary" type="button" data-answer-paper-edit="{{ $p->id }}" aria-label="Edit answer sheet" @disabled($linked||$locked) title="{{ $linked?'Student-linked setup is protected. Use a key revision or create a new paper version.':($locked?'This exam is locked.':'Edit this unlinked paper') }}"><i class="ti ti-edit me-1"></i>Edit</button>@endif
                            @if($permissions['answer-delete'])<button class="btn btn-sm btn-outline-danger" type="button" data-answer-paper-delete="{{ $p->id }}" aria-label="Delete answer sheet" @disabled($linked||$locked) title="{{ $linked?'This answer sheet is already linked to students, scans or scores and cannot be deleted.':($locked?'This exam is locked.':'Delete this unlinked paper') }}"><i class="ti ti-trash me-1"></i>Delete</button>@endif
                        </div></td>
                    </tr>
                @empty<tr><td colspan="7" class="text-center text-secondary py-4">No answer-sheet papers for this academic year.</td></tr>@endforelse
            </tbody></table></div>
            <div class="card-footer small text-secondary">Link Students links each paper’s grade without printing. Before any scan or score, use Unlink Students to remove links before editing the paper setup. Link again and print new sheets afterward. Shared setup stays protected while any student links remain.</div>
        </section>
        @if($exam&&$paper&&$permissions['answer-scan'])
        <section class="card mb-3"><div class="card-header scholarship-card-header"><h3 class="card-title"><i class="ti ti-camera me-2"></i>Scan &amp; Upload</h3></div><div class="card-body">
            <p>Add up to 25 sheet photos for the selected grade, subject and paper. Sheets upload one at a time and require staff verification before scores are saved.</p>
            @php($scanDisabled=count($scanBlockers)>0)
            @if($scanDisabled)
            <div class="alert alert-warning d-block" id="answerScanUnavailable" role="status" data-answer-scan-unavailable>
                <div class="fw-semibold mb-1"><i class="ti ti-alert-triangle me-1" aria-hidden="true"></i>Uploads are currently unavailable</div>
                <div class="small mb-2">Academic Year: {{ $exam->academicYear->academic_year }} &middot; Grade {{ $paper->grade_number }} &middot; {{ $paper->subject->name }} &middot; Paper {{ $paper->paper_version }}</div>
                <ul class="mb-0 ps-3">@foreach($scanBlockers as $reason=>$message)<li data-answer-scan-blocker="{{ $reason }}">{{ $message }}</li>@endforeach</ul>
            </div>
            @endif
            <div class="form-label" id="answerScanLabel">Upload Sheet Photos</div>
            <div class="answer-sheet-dropzone mb-3" data-answer-dropzone role="button" tabindex="{{ $scanDisabled?-1:0 }}" aria-disabled="{{ $scanDisabled?'true':'false' }}" aria-labelledby="answerScanLabel" aria-describedby="answerScanHint answerScanStatus answerScanError{{ $scanDisabled?' answerScanUnavailable':'' }}" @if($scanDisabled)title="{{ implode(' ',$scanBlockers) }}"@endif>
                <span class="document-upload-icon"><i class="ti ti-cloud-upload" aria-hidden="true"></i></span>
                <div class="fw-bold fs-3">Drop Photos Here</div>
                <div class="mt-1">or <span class="document-browse-link">Browse Files</span></div>
                <div class="document-upload-hint mt-3" id="answerScanHint">Up to 25 photos &middot; JPG, PNG, WebP &middot; Maximum 10 MB / 24 megapixels per photo</div>
                <div class="document-upload-hint mt-1">You can also copy and paste a photo here (Ctrl+V / Command+V)</div>
            </div>
            <input type="file" id="answerScanFile" accept="image/jpeg,image/png,image/webp" aria-labelledby="answerScanLabel" multiple hidden @disabled($scanDisabled)>
            <div class="answer-batch mb-3" data-answer-batch hidden>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <button type="button" class="btn btn-primary" data-answer-batch-start><i class="ti ti-cloud-upload me-1"></i><span data-answer-batch-start-label>Upload Batch</span></button>
                    <button type="button" class="btn btn-outline-secondary" data-answer-batch-pause hidden><i class="ti ti-player-pause me-1"></i><span data-answer-batch-pause-label>Pause</span></button>
                    <button type="button" class="btn btn-outline-primary" data-answer-batch-retry><i class="ti ti-refresh me-1"></i>Retry Failed</button>
                    <button type="button" class="btn btn-outline-secondary" data-answer-batch-clear><i class="ti ti-list-details me-1"></i>Clear Queue</button>
                    <button type="button" class="btn btn-outline-primary" data-answer-batch-history><i class="ti ti-history me-1"></i>Refresh Scan History</button>
                </div>
                <p class="small mb-2" role="status" aria-live="polite" data-answer-batch-summary></p>
                <progress class="answer-batch-progress mb-3" data-answer-batch-progress value="0" max="25" aria-label="Photos uploaded in this queue"></progress>
                <div class="answer-batch-list" data-answer-batch-list aria-label="Answer sheet upload queue"></div>
                <p class="small text-secondary mt-2 mb-0">Photos waiting to upload stay on this page only. Uploaded sheets remain in Scan History when you clear the queue or leave the page.</p>
            </div>
            <div class="answer-sheet-file mb-3" data-answer-file hidden>
                <span class="document-file-icon"><i class="ti ti-photo" aria-hidden="true"></i></span>
                <div class="flex-fill min-w-0"><div class="fw-semibold text-break" data-answer-file-name></div><div class="small text-secondary" data-answer-file-size></div></div>
                <button type="button" class="btn btn-icon btn-ghost-danger" data-answer-file-remove aria-label="Remove selected sheet photo"><i class="ti ti-x" aria-hidden="true"></i></button>
            </div>
            <div id="answerScanStatus" class="small text-secondary mb-2" role="status" aria-live="polite"></div>
            <div id="answerScanError" class="text-danger small mb-3" role="alert" hidden></div>
            <button class="btn btn-outline-primary mb-3" type="button" data-answer-camera @disabled($scanDisabled)><i class="ti ti-camera me-1"></i>Take Photo</button><input type="file" id="answerCameraFile" accept="image/jpeg,image/png,image/webp" capture="environment" hidden @disabled($scanDisabled)>
            <div data-answer-alignment hidden>
                <h4 class="mb-2" data-answer-alignment-heading>Selected sheet alignment</h4>
                <div class="d-flex flex-wrap gap-2 mb-2"><button class="btn btn-outline-primary" type="button" data-answer-auto><i class="ti ti-focus-2 me-1"></i>Auto Align</button><button class="btn btn-outline-secondary" type="button" data-answer-corners>Set Corners</button><button class="btn btn-primary" type="button" data-answer-upload>Upload for Verification</button></div>
                <p role="status" aria-live="polite" data-answer-align-status>Tap marker centers: top left, top right, bottom right, bottom left.</p>
                <div class="answer-alignment-grid"><canvas id="answerAlignment" aria-label="Tap the four corner marker centers" tabindex="0"></canvas><div><strong>Aligned preview</strong><img data-answer-aligned alt="Aligned sheet preview" class="mt-2" hidden></div></div>
            </div>
        </div></section>
        @endif
        <section class="card" id="answerScanHistory"><div class="card-header scholarship-card-header d-flex flex-wrap gap-2"><h3 class="card-title">Scan History</h3>
            <div class="ms-auto d-flex flex-wrap gap-2">@if($permissions['answer-accept-all']&&$paper)<button type="button" class="btn btn-sm btn-outline-light" data-answer-accept-all @disabled($data['locked']||!$exam->seats_locked_at)><i class="ti ti-checks me-1"></i>Accept All</button>@endif
            @if($permissions['answer-clear-scans']&&$paper)<button type="button" class="btn btn-sm btn-outline-light" data-answer-clear-scans @disabled($data['locked']) title="Clear test scans for the selected paper, exam center and room"><i class="ti ti-trash me-1"></i>Clear Test Scans</button>@endif</div>
        </div>
            @include('western-scholarship._scan-history-list')
        </section>
        <script type="application/json" id="answerSheetData">{!! json_encode($data,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    </main>
</div>
@if($exam&&$permissions['answer-paper'])
<div class="modal fade" id="answerSetupModal" tabindex="-1" aria-labelledby="answerSetupTitle"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" data-answer-setup-form>
    <div class="modal-header"><h3 class="modal-title" id="answerSetupTitle">Paper Setup</h3><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><div class="premium-form-field answer-existing-paper-field mb-3"><label class="form-label" for="answerExistingPaper">Existing paper or new version</label><select class="form-select" id="answerExistingPaper" name="existing"><option value="">New paper</option>@foreach($papers as $p)<option value="{{ $p->id }}">Grade {{ $p->grade_number }} · {{ $p->subject->name }} · {{ $p->paper_version }}</option>@endforeach</select></div>
        <p class="alert alert-warning d-none" data-answer-edit-warning>Saving setup changes clears this paper’s draft answer key and approvals. Review and approve a new key before printing.</p>
        <div class="row g-3"><div class="col-12"><label class="form-label">Subject</label><select class="form-select" name="subject_id" required>@foreach($exam->subjects as $s)<option value="{{ $s->id }}">{{ $s->name }} · Grades {{ $s->grade_from }}–{{ $s->grade_to }} · Max {{ $s->max_score }}</option>@endforeach</select></div>
            <div class="col-sm-6"><label class="form-label">Grade</label><input class="form-control" type="number" name="grade" min="1" max="12" value="1" required></div><div class="col-sm-6"><label class="form-label">Paper Version</label><input class="form-control" name="paper_version" value="A" pattern="[A-Z0-9_-]{1,20}" maxlength="20" required></div>
            <div class="col-sm-6"><label class="form-label">Questions</label><input class="form-control" type="number" name="question_count" min="1" max="100" value="20" required></div><div class="col-sm-6"><label class="form-label">Default Question Score</label><input class="form-control" type="number" name="points" min="0.0001" step="0.0001" value="5" required></div>
            <div class="col-sm-6"><label class="form-label">Choice Labels</label><select class="form-select" name="choice_style"><option value="letters">Letters (A, B, C…)</option><option value="numbers">Numbers (1, 2, 3…)</option></select></div><div class="col-sm-6"><label class="form-label">Choice Count</label><input class="form-control" type="number" name="choice_count" min="2" max="26" value="5" step="1" required><div class="form-hint" data-answer-choice-preview>A, B, C, D, E</div></div>
        </div><p class="form-hint mt-3">This paper and its answer key are shared across campuses for this grade and subject. Set the score for each question in Answer Key. The approved scores must total the subject maximum. Unlink students before changing a linked paper setup. Once scans or scores exist, use a new paper version for setup changes.</p>
    </div><div class="modal-footer"><button class="btn btn-primary" type="submit">Save Paper</button></div>
</form></div></div>
@endif
<div class="modal fade" id="answerPaperViewModal" tabindex="-1" aria-labelledby="answerPaperViewTitle"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h3 class="modal-title" id="answerPaperViewTitle">View Answer Sheet</h3><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">
    <p class="fw-semibold" data-answer-paper-view-heading></p><dl class="row" data-answer-paper-view-details></dl>
    <div data-answer-paper-view-key hidden><h4 data-answer-paper-view-key-title>Answer Key</h4><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Question</th><th>Correct Answer</th><th>Score</th></tr></thead><tbody data-answer-paper-view-rows></tbody></table></div></div>
</div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button><a class="btn btn-primary" data-answer-paper-use>Open This Paper</a></div></div></div></div>
<div class="modal fade" id="answerKeyModal" tabindex="-1" aria-labelledby="answerKeyTitle"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h3 id="answerKeyTitle" class="modal-title">Answer Key</h3><button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button></div><div class="modal-body"><h4 class="answer-key-subject mb-1" data-answer-key-subject></h4><p class="text-secondary mb-3" data-answer-key-status></p>
<div class="d-flex flex-wrap gap-2 mb-3">
    @if($permissions['answer-key-print'])<button type="button" class="btn btn-sm btn-outline-primary" data-teacher-key-print><i class="ti ti-printer me-1"></i>Print Teacher Key</button>@endif
    @if($permissions['answer-key-scan']&&$permissions['answer-key'])<button type="button" class="btn btn-sm btn-outline-primary" data-teacher-key-open><i class="ti ti-scan me-1"></i>Scan / Upload Teacher Key</button>@endif
</div>
<div class="alert alert-info" data-teacher-key-import-note hidden></div>
<details class="mb-3" data-teacher-key-import-preview hidden><summary>View scanned teacher answer key</summary><img class="w-100 mt-2" data-teacher-key-import-image alt="Imported teacher answer key"></details>
<div class="d-flex flex-wrap gap-2 align-items-end mb-3" data-answer-score-controls>
    <div><label class="form-label" for="answerBulkScore">Fill Question Scores</label><select class="form-select" id="answerBulkScore"><option value="1">1 point</option><option value="2">2 points</option><option value="3">3 points</option><option value="4">4 points</option><option value="5">5 points</option><option value="custom">Customize</option></select></div>
    <div data-answer-custom-score hidden><label class="form-label" for="answerCustomScore">Custom Score</label><input class="form-control" id="answerCustomScore" type="number" min="0.0001" step="0.0001" value="1"></div>
    <button type="button" class="btn btn-outline-primary" data-answer-fill-scores>Apply to All Questions</button>
</div>
<p class="small fw-semibold" data-answer-key-total role="status" aria-live="polite"></p>
<datalist id="answerQuestionScoreOptions">@foreach(range(1,5) as $score)<option value="{{ $score }}"></option>@endforeach</datalist>
<div class="alert alert-danger" role="alert" data-answer-key-errors hidden></div>
<div class="table-responsive"><table class="table table-vcenter answer-key-table"><thead><tr><th>Question</th><th>Correct Answer</th><th>Score</th></tr></thead><tbody data-answer-key-grid></tbody></table></div><p class="form-hint mt-3">Save the draft before approving it. A revised key requires an explicit recheck of affected scans.</p></div><div class="modal-footer">@if($permissions['answer-key'])<button type="button" class="btn btn-outline-primary" data-answer-key-save>Save Draft</button>@endif @if($permissions['answer-key-approve'])<button type="button" class="btn btn-primary" data-answer-key-approve>Approve Key</button>@endif</div></div></div></div>
<div class="modal fade" id="answerPrintModal" tabindex="-1" aria-labelledby="answerPrintTitle"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" data-answer-print-form novalidate><div class="modal-header"><h3 class="modal-title" id="answerPrintTitle">Print Answer Sheets</h3><button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button></div><div class="modal-body">
    <div class="scholarship-field mb-3"><label class="form-label" for="answerPrintScope">Print Scope</label><select class="form-select" id="answerPrintScope" name="scope" data-placeholder="Select print scope"><option value="all">All students</option><option value="rooms">Selected rooms</option><option value="students">Selected students</option></select></div>
    <div class="scholarship-field mb-3"><label class="form-label" for="answerPrintCenter">Exam Center</label><select class="form-select" id="answerPrintCenter" name="center" data-placeholder="All Exam Centers" aria-describedby="answerPrintCenterError"><option value="">All Exam Centers</option>@foreach($centers as $c)<option value="{{ $c['id'] }}" @selected(isset($input['center'])&&$input['center']==$c['id'])>{{ $c['name'] }}</option>@endforeach</select><div class="invalid-feedback" id="answerPrintCenterError" data-answer-print-error="center" role="alert" hidden>Select an exam center to choose rooms.</div></div>
    <div data-answer-print-rooms hidden><div class="scholarship-field"><label class="form-label" for="answerPrintRooms">Select Rooms</label><select class="form-select" id="answerPrintRooms" name="rooms" data-placeholder="Select one or more rooms" aria-describedby="answerPrintRoomsError" multiple disabled></select><div class="invalid-feedback" id="answerPrintRoomsError" data-answer-print-error="rooms" role="alert" hidden>Select at least one room.</div></div><p class="form-hint mt-2" data-answer-print-room-hint>Search and select one or more rooms.</p></div>
    <div data-answer-print-students hidden><div class="scholarship-field"><label class="form-label" for="answerPrintStudents">Select Students</label><select class="form-select" id="answerPrintStudents" name="candidates" data-placeholder="Select one or more students" aria-describedby="answerPrintStudentsError" multiple disabled></select><div class="invalid-feedback" id="answerPrintStudentsError" data-answer-print-error="candidates" role="alert" hidden>Select at least one student.</div></div><p class="form-hint mt-2">Search by student name, room or seat. Select multiple students; sheets print in seat order.</p></div>
    <p class="form-hint mt-3 mb-0">Reprinting keeps the same sheet ID. You can also save as PDF in the browser print preview.</p>
</div><div class="modal-footer"><button class="btn btn-primary" type="submit"><i class="ti ti-printer me-1"></i>Open A4 Print Preview</button></div></form></div></div>
<div class="modal fade" id="teacherKeyScanModal" tabindex="-1" aria-labelledby="teacherKeyScanTitle"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h3 class="modal-title" id="teacherKeyScanTitle"><i class="ti ti-scan me-2"></i>Scan Teacher Answer Key</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
        <p class="fw-semibold" data-teacher-key-heading></p><p class="text-secondary">Upload the teacher's completed ANSWER KEY sheet. Match the four black corner squares. Student sheets and old paper setups are rejected. Detected answers need review before saving and approval.</p>
        <div class="form-label" id="teacherKeyPhotoLabel">Teacher key photo / scan</div>
        <div class="answer-sheet-dropzone mb-3" data-teacher-key-dropzone role="button" tabindex="0" aria-disabled="false" aria-labelledby="teacherKeyPhotoLabel" aria-describedby="teacherKeyPhotoHint teacherKeyPhotoStatus teacherKeyPhotoError">
            <span class="document-upload-icon"><i class="ti ti-cloud-upload" aria-hidden="true"></i></span>
            <div class="fw-bold fs-3">Drop Photo Here</div>
            <div class="mt-1">or <span class="document-browse-link">Browse File</span></div>
            <div class="document-upload-hint mt-3" id="teacherKeyPhotoHint">One teacher answer key &middot; JPG, PNG, WebP &middot; Maximum 10 MB / 24 megapixels</div>
            <div class="document-upload-hint mt-1">You can also copy and paste a photo here (Ctrl+V / Command+V)</div>
        </div>
        <input type="file" id="teacherKeyFile" accept="image/jpeg,image/png,image/webp" aria-labelledby="teacherKeyPhotoLabel" hidden>
        <div class="answer-sheet-file mb-3" data-teacher-key-file hidden>
            <span class="document-file-icon"><i class="ti ti-photo" aria-hidden="true"></i></span>
            <div class="flex-fill min-w-0"><div class="fw-semibold text-break" data-teacher-key-file-name></div><div class="small text-secondary" data-teacher-key-file-size></div></div>
            <button type="button" class="btn btn-icon btn-ghost-danger" data-teacher-key-file-remove aria-label="Remove selected teacher key photo"><i class="ti ti-x" aria-hidden="true"></i></button>
        </div>
        <div id="teacherKeyPhotoStatus" class="small text-secondary mb-2" role="status" aria-live="polite"></div>
        <div id="teacherKeyPhotoError" class="text-danger small mb-3" role="alert" hidden></div>
        <button type="button" class="btn btn-outline-primary mb-3" data-teacher-key-camera><i class="ti ti-camera me-1"></i>Take Photo</button><input type="file" id="teacherKeyCamera" accept="image/jpeg,image/png,image/webp" capture="environment" hidden>
        <div class="alert alert-info" role="status" aria-live="polite" data-teacher-detected-status hidden></div>
        <div data-teacher-alignment hidden>
            <div class="d-flex flex-wrap gap-2 mb-2"><button type="button" class="btn btn-outline-primary" data-teacher-key-auto><i class="ti ti-focus-2 me-1"></i>Auto Align</button><button type="button" class="btn btn-outline-secondary" data-teacher-key-corners>Set Corners</button></div>
            <p role="status" aria-live="polite" data-teacher-align-status></p>
            <div class="answer-alignment-grid"><canvas id="teacherKeyAlignment" aria-label="Tap teacher key marker centers: top left, top right, bottom right, bottom left"></canvas><div><strong>Aligned teacher key</strong><img data-teacher-aligned alt="Aligned teacher answer key" class="mt-2" hidden></div></div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Back to Answer Key</button><button type="button" class="btn btn-outline-primary" data-teacher-key-upload disabled><i class="ti ti-upload me-1"></i>Upload &amp; Detect</button><button type="button" class="btn btn-primary" data-teacher-key-use disabled>Use Detected Answers</button></div>
</div></div></div>
<div class="modal fade" id="answerReviewModal" tabindex="-1" aria-labelledby="answerReviewTitle"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" data-answer-review-form><div class="modal-header"><h3 id="answerReviewTitle" class="modal-title">Verify Answer Sheet</h3><button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button></div><div class="modal-body">
    <p data-answer-review-heading></p><div class="answer-review-grid"><div><div class="d-flex gap-2 mb-2"><button class="btn btn-sm btn-outline-secondary" type="button" data-answer-image-zoom="-0.25" aria-label="Zoom out sheet"><i class="ti ti-zoom-out"></i></button><button class="btn btn-sm btn-outline-secondary" type="button" data-answer-image-zoom="0.25" aria-label="Zoom in sheet"><i class="ti ti-zoom-in"></i></button><a class="btn btn-sm btn-outline-secondary" data-answer-original target="_blank" rel="noopener">View Original</a></div><div class="answer-review-image"><img data-answer-review-image alt="Scanned answer sheet"></div></div><div><div class="answer-response-grid" data-answer-response-grid></div></div></div>
    <label class="form-check mt-3"><input class="form-check-input" name="confirmed" type="checkbox" required><span class="form-check-label">I checked the sheet image, all flagged questions, and the selected responses.</span></label>
    <label class="form-check mt-3"><input class="form-check-input" name="replace" type="checkbox"><span class="form-check-label">Replace the existing subject score (requires replacement permission).</span></label>
    <label class="form-label mt-3">Reason for corrections, replacement or recheck</label><textarea class="form-control" name="reason" maxlength="1000" rows="2"></textarea>
</div><div class="modal-footer">@if($permissions['answer-replace'])<button class="btn btn-outline-secondary" type="button" data-answer-recheck>Recheck with Current Key</button>@endif<button class="btn btn-primary" type="submit" data-answer-verify>Verify &amp; Save Draft Score</button></div></form></div></div>
@if($exam&&$permissions['answer-marked-view'])
<div class="modal fade" id="answerMarkedModal" tabindex="-1" aria-labelledby="answerMarkedTitle"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h3 id="answerMarkedTitle" class="modal-title"><i class="ti ti-eye-check me-2"></i>Marked Answer Sheet</h3><button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button></div>
    <div class="modal-body">
        <p class="fw-bold mb-1" data-answer-marked-heading></p><p class="small text-secondary" data-answer-marked-note></p>
        <p class="alert alert-info py-2" data-answer-marked-summary></p>
        <div class="answer-marked-grid">
            <section aria-label="Marked scan image">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-answer-marked-zoom="-0.25" aria-label="Zoom out marked sheet"><i class="ti ti-zoom-out"></i></button>
                    <span class="small" data-answer-marked-zoom-label>100%</span>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-answer-marked-zoom="0.25" aria-label="Zoom in marked sheet"><i class="ti ti-zoom-in"></i></button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-answer-marked-zoom="reset">Fit</button>
                    <label class="form-check m-0 ms-sm-auto"><input class="form-check-input" type="checkbox" data-answer-marked-toggle checked><span class="form-check-label">Show borders</span></label>
                </div>
                <div class="answer-marked-legend small mb-2"><span><i class="answer-marked-dot answer-marked-correct"></i>Green: correct answer</span><span><i class="answer-marked-dot answer-marked-incorrect"></i>Red: wrong selection</span><span><i class="answer-marked-dot answer-marked-warning"></i>Orange: ambiguous mark</span></div>
                <p class="alert alert-warning" data-answer-marked-image-error hidden>The scan image could not be loaded. The answer comparison is still shown.</p>
                <div class="answer-marked-viewport" data-answer-marked-viewport><div class="answer-marked-stage" data-answer-marked-stage><img data-answer-marked-image alt="Student answer sheet with correct and incorrect answer borders"><svg class="answer-marked-overlay" data-answer-marked-overlay preserveAspectRatio="none" role="img" aria-label="Green correct-answer borders, red incorrect-response borders and orange ambiguous marks"></svg></div></div>
            </section>
            <section aria-label="Answer comparison"><h4 class="mb-2">Answer Comparison</h4><div class="answer-marked-table table-responsive"><table class="table table-vcenter table-sm"><thead><tr><th>Question</th><th>Student Answer</th><th>Correct Answer</th><th>Result</th><th>Points</th></tr></thead><tbody data-answer-marked-rows></tbody></table></div></section>
        </div>
    </div><div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button></div>
</div></div></div>
@endif
@vite(['resources/css/pages/western-scholarship.css','resources/css/pages/scholarship-answer-sheets.css','resources/js/westernScholarship.js','resources/js/scholarshipAnswerSheets.js'])
@endsection
