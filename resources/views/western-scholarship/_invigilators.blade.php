@php
    $invigilatorLocked=(bool)($exam->published_at||$exam->isHistorical());
    $sessionCount=max(1,$invigilatorRooms->map(fn($r)=>count($r['subjects']))->max()??4,$invigilatorSessions->max('number')??0);
    $invigilatorStaffData=$invigilatorStaff->values()->all();
    $invigilatorRoomData=$invigilatorRooms->values()->all();
@endphp
<div data-invigilators data-person-create="{{ route('western-scholarship.invigilators.store',$exam) }}" data-person-update="{{ route('western-scholarship.invigilators.update',[$exam,'PERSON']) }}">
    <script type="application/json" data-invigilator-staff>@json($invigilatorStaffData)</script>
    <script type="application/json" data-invigilator-rooms>@json($invigilatorRoomData)</script>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div><h3 class="mb-1">Invigilator List <span class="badge bg-blue-lt">{{ $invigilators->count() }}</span></h3><p class="text-secondary small mb-0">Saved for {{ $exam->academicYear->academic_year }}. HRM changes do not alter these records.</p></div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary btn-sm" href="{{ route('western-scholarship.invigilators.download',[$exam,'sample'=>1]) }}"><i class="ti ti-file-download me-1" aria-hidden="true"></i>Sample Excel</a>
            @if($examPermissions['export'])<a class="btn btn-outline-success btn-sm" href="{{ route('western-scholarship.invigilators.download',$exam) }}"><i class="ti ti-file-spreadsheet me-1" aria-hidden="true"></i>Export Excel</a>@endif
            @if(!$invigilatorLocked)<button class="btn btn-primary btn-sm" type="button" data-invigilator-add><i class="ti ti-user-plus me-1" aria-hidden="true"></i>Add Invigilator</button>@endif
        </div>
    </div>
    <div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Code / Staff ID</th><th>Staff Name English</th><th>Gender</th><th>Campus / Position</th><th>Phone</th><th>Actions</th></tr></thead><tbody>
    @forelse($invigilators as $person)
        @php($invigilatorEdit=$person->only(['id','code','source','staff_code','name_en','gender','campus','position','phone','email','organization','notes']))
        <tr><td><strong>{{ $person->code }}</strong><div class="text-secondary small">{{ $person->staff_code?:'External' }}</div></td><td>{{ $person->name_en }}<div class="small text-secondary">{{ $person->organization }}</div></td><td>{{ $person->gender }}</td><td>{{ $person->campus }}<div class="text-secondary small">{{ $person->position }}</div></td><td>{{ $person->phone }}</td><td>
        @if(!$invigilatorLocked)<div class="d-flex gap-2"><button class="btn btn-outline-primary btn-icon btn-sm" type="button" aria-label="Edit {{ $person->name_en }}" data-invigilator-edit="{{ json_encode($invigilatorEdit) }}"><i class="ti ti-pencil" aria-hidden="true"></i></button>
        <form method="POST" action="{{ route('western-scholarship.invigilators.remove',[$exam,$person]) }}" data-invigilator-form data-invigilator-confirm="Remove this invigilator from this academic year?">@csrf<button class="btn btn-outline-danger btn-icon btn-sm" @disabled($person->duties_count>0) title="{{ $person->duties_count?'Remove room assignments first':'Delete invigilator' }}" aria-label="Delete {{ $person->name_en }}"><i class="ti ti-trash" aria-hidden="true"></i></button></form></div>@endif
        </td></tr>
    @empty<tr><td colspan="6" class="text-secondary text-center py-4">Add HRM staff, enter external invigilators, or import an Excel workbook.</td></tr>@endforelse
    </tbody></table></div>

    <details class="scholarship-invigilator-section mt-4"><summary class="fw-semibold"><i class="ti ti-calendar-time me-2" aria-hidden="true"></i>Exam Session Schedule</summary>
        <p class="small text-secondary mt-3">Dates and times are managed by subject in Scholarship Settings. Each grade follows its defaults or customized timetable.</p>
        <a class="btn btn-outline-primary btn-sm mb-3" href="{{ route('western-scholarship.index',['tab'=>'settings','exam_id'=>$exam->id]) }}">View Scholarship Settings</a>
        <div class="table-responsive"><table class="table"><thead><tr><th>Exam Center / Room</th><th>Grade / Session</th><th>Subject</th><th>Exam Date</th><th>Start / End Time</th></tr></thead><tbody>
        @foreach($invigilatorRooms as $room)@foreach($room['subjects'] as $index=>$subject)
            @php($timing=$room['schedules'][$index]??null)
            <tr><td>{{ $exam->centers->firstWhere('id',$room['center_id'])?->name }} · Room {{ $room['room'] }}</td><td>Grade {{ $room['grade'] }} · Session {{ $index+1 }}</td><td>{{ $subject }}</td><td>{{ $timing?\Illuminate\Support\Carbon::parse($timing['exam_date'])->format('d-M-Y'):'Not scheduled' }}</td><td>{{ $timing?$timing['starts_at'].'–'.$timing['ends_at']:'Set in Scholarship Settings' }}</td></tr>
        @endforeach
        @endforeach
        </tbody></table></div>
    </details>

    <section class="scholarship-invigilator-section mt-4" data-session-links><h3><i class="ti ti-link me-2" aria-hidden="true"></i>Linked Sessions</h3>
        <p class="small text-secondary">Linked sessions share invigilators and room assignments within one exam center. Changes in either session update the entire group. Subjects, dates and times remain separate.</p>
        @foreach($invigilatorSessionLinks as $link)
            <div class="d-flex flex-wrap align-items-center gap-3 border rounded p-3 mb-3">
                <div><strong>{{ $link->examCenter->name }}</strong><div class="text-primary small"><i class="ti ti-link me-1" aria-hidden="true"></i>Linked Sessions: {{ $invigilatorSessions->whereIn('id',$link->session_ids)->pluck('number')->implode(' & ') }}</div></div>
                @if(!$invigilatorLocked)<form class="ms-auto" method="POST" action="{{ route('western-scholarship.invigilators.unlink',[$exam,$link]) }}" data-invigilator-form data-invigilator-confirm="Unlink these sessions? Existing room assignments will be kept, and future changes will be independent.">@csrf<input type="hidden" name="version" value="{{ $exam->version }}"><button class="btn btn-outline-warning btn-sm" type="submit"><i class="ti ti-unlink me-1" aria-hidden="true"></i>Unlink Sessions</button></form>@endif
            </div>
        @endforeach
        @if(!$invigilatorLocked)
        <form method="POST" action="{{ route('western-scholarship.invigilators.link',$exam) }}" data-invigilator-form data-session-link-form data-invigilator-confirm="Link the selected sessions? Their current assignments will be replaced by the source session’s assignments in this exam center. Future additions, room changes and removals will update every linked session.">@csrf<input type="hidden" name="version" value="{{ $exam->version }}"><input type="hidden" name="confirm" value="1">
            <fieldset @disabled(!$exam->seats_locked_at)><div class="row g-3">
                <div class="col-md-6 scholarship-field"><label for="sessionLinkCenter">Exam Center <span class="text-danger">*</span></label><select id="sessionLinkCenter" name="exam_center_id" class="form-select" data-scholarship-select required><option value="">Select exam center</option>@foreach($exam->centers as $center)<option value="{{ $center->id }}">{{ $center->name }}</option>@endforeach</select></div>
                <div class="col-md-6 scholarship-field"><label for="sessionLinkSource">Use Assignments From <span class="text-danger">*</span></label><select id="sessionLinkSource" name="source_session_id" class="form-select" data-scholarship-select required><option value="">Select source session</option>@foreach($invigilatorSessions as $session)<option value="{{ $session->id }}">Session {{ $session->number }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label">Sessions to Link <span class="text-danger">*</span></label><div class="d-flex flex-wrap gap-3">
                    @forelse($invigilatorSessions as $session)<label class="form-check"><input class="form-check-input" type="checkbox" name="session_ids[]" value="{{ $session->id }}"><span class="form-check-label">Session {{ $session->number }}</span></label>@empty<span class="text-secondary small">Save the session schedule first.</span>@endforelse
                </div><p class="small text-secondary mb-0">Select at least two sessions, including the source. The source session’s assignments replace the others; an empty source clears their assignments. Unlink an existing group before changing its members.</p></div>
            </div><button class="btn btn-primary mt-3" type="submit"><i class="ti ti-link me-2" aria-hidden="true"></i>Link Sessions</button></fieldset>
        </form>
        @endif
    </section>

    <section class="scholarship-invigilator-section mt-4"><h3><i class="ti ti-door me-2" aria-hidden="true"></i>Room Assignments</h3>
    @if(!$invigilatorLocked)
    @if(!$exam->seats_locked_at)<div class="alert alert-info">Confirm the seat plan before assigning invigilators to rooms.</div>@endif
    <form method="POST" action="{{ route('western-scholarship.invigilators.assign',$exam) }}" data-invigilator-form data-invigilator-assignment>@csrf
    <fieldset @disabled(!$exam->seats_locked_at)><div class="row g-3">
        <div class="col-md-6 scholarship-field"><label for="invigilatorPerson">Invigilator <span class="text-danger">*</span></label><select id="invigilatorPerson" name="invigilator_id" class="form-select" data-scholarship-select required><option value="">Select invigilator</option>@foreach($invigilators as $p)<option value="{{ $p->id }}">{{ $p->code }} · {{ $p->name_en }}</option>@endforeach</select></div>
        <div class="col-md-6 scholarship-field"><label for="invigilatorCenter">Exam Center <span class="text-danger">*</span></label><select id="invigilatorCenter" name="exam_center_id" class="form-select" data-scholarship-select required><option value="">Select exam center</option>@foreach($exam->centers as $center)<option value="{{ $center->id }}">{{ $center->name }}</option>@endforeach</select></div>
        <div class="col-md-6 scholarship-field"><label for="invigilatorSession">Session <span class="text-danger">*</span></label><select id="invigilatorSession" name="session_id" class="form-select" data-scholarship-select required><option value="">Select session</option>@foreach($invigilatorSessions as $s)<option value="{{ $s->id }}" data-number="{{ $s->number }}">Session {{ $s->number }}</option>@endforeach</select></div>
        <div class="col-md-6 scholarship-field"><label for="invigilatorRoom">Room / Subject <span class="text-danger">*</span></label><select id="invigilatorRoom" name="room_number" class="form-select" data-scholarship-select required><option value="">Select exam center and session first</option></select></div>
    </div><label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="all_sessions" value="1"><span class="form-check-label">Apply this invigilator to all sessions for this room</span></label>
    <button class="btn btn-primary mt-3" type="submit"><i class="ti ti-user-check me-2" aria-hidden="true"></i>Assign Room</button></fieldset></form>
    <p class="small text-secondary mt-2">To change a room, select the invigilator, session and new room, then save. For linked sessions, this updates every session in the group.</p>
    @endif
    <div class="table-responsive mt-3"><table class="table table-vcenter"><thead><tr><th>Exam Center</th><th>Session</th><th>Room / Subject</th><th>Invigilator / Phone</th><th>Actions</th></tr></thead><tbody>
    @forelse($invigilatorDuties as $duty)
        @php($dutyRoom=$invigilatorRooms->first(fn($r)=>$r['center_id']===$duty->exam_center_id && $r['room']===$duty->room_number))
        @php($dutyTiming=$dutyRoom['schedules'][$duty->session->number-1]??null)
        @php($dutyLink=$invigilatorSessionLinks->first(fn($l)=>$l->exam_center_id===$duty->exam_center_id && in_array($duty->session_id,$l->session_ids,true)))
        <tr><td>{{ $duty->examCenter->name }}</td><td>Session {{ $duty->session->number }}<div class="small text-secondary">{{ $dutyTiming?\Illuminate\Support\Carbon::parse($dutyTiming['exam_date'])->format('d-M-Y').' - '.$dutyTiming['starts_at'].' - '.$dutyTiming['ends_at']:'Set subject schedule in Settings' }}</div>@if($dutyLink)<div class="small text-primary"><i class="ti ti-link me-1" aria-hidden="true"></i>Linked: {{ $invigilatorSessions->whereIn('id',$dutyLink->session_ids)->pluck('number')->implode(' & ') }}</div>@endif</td><td>Room {{ $duty->room_number }} · Grade {{ $dutyRoom['grade']??'—' }}<div class="small text-secondary">{{ $dutyRoom['subjects'][$duty->session->number-1]??'No subject — review assignment' }}</div></td><td>{{ $duty->invigilator->name_en }}<div class="small text-secondary">{{ $duty->invigilator->phone }}</div></td><td>@if(!$invigilatorLocked)<form method="POST" action="{{ route('western-scholarship.invigilators.unassign',[$exam,$duty]) }}" data-invigilator-form data-invigilator-confirm="{{ $dutyLink?'Remove this invigilator’s assignment from every session in this linked group?':'Remove this room assignment?' }}">@csrf<button class="btn btn-outline-danger btn-icon btn-sm" aria-label="Remove room assignment"><i class="ti ti-trash" aria-hidden="true"></i></button></form>@endif</td></tr>
    @empty<tr><td colspan="5" class="text-secondary text-center py-3">No session room assignments yet.</td></tr>@endforelse
    </tbody></table></div>
    </section>

    @if(!$invigilatorLocked)
    <details class="scholarship-invigilator-section mt-4"><summary class="fw-semibold"><i class="ti ti-file-import me-2" aria-hidden="true"></i>Import Invigilators and Room Assignments</summary><div class="mt-3">
        <p class="small text-secondary">Use the two-sheet Sample Excel workbook. Keep Staff IDs and phone numbers as text. Invigilator Codes connect people to session assignments. Save the subject schedules in Scholarship Settings first; leave Room Assignments empty to import people only.</p>
        <p class="small text-secondary">For linked sessions, importing one session updates every linked session in that center. Enter the same room for linked sessions included in the file. Preview includes the assignments that will be synchronized.</p>
        <form method="POST" action="{{ route('western-scholarship.invigilators.preview',$exam) }}" enctype="multipart/form-data" data-invigilator-upload>@csrf
            <label class="form-label" for="invigilatorFile">Excel Workbook (.xlsx)</label><input class="form-control" type="file" id="invigilatorFile" name="file" accept=".xlsx" required>
            <label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="replace" value="1"><span class="form-check-label">Replace Matching Records</span></label><p class="small text-secondary">Updates matching Invigilator Codes and replaces their matching session room assignments. Other records remain unchanged.</p>
            <button class="btn btn-outline-primary" type="submit"><i class="ti ti-file-search me-2" aria-hidden="true"></i>Validate & Preview</button>
        </form>
        <div data-invigilator-preview class="mt-3" hidden aria-live="polite"></div>
        <form method="POST" action="{{ route('western-scholarship.invigilators.import',$exam) }}" data-invigilator-form data-invigilator-import hidden>@csrf<input type="hidden" name="token">
            <label class="form-check my-3"><input type="checkbox" class="form-check-input" name="acknowledge" value="1" required><span class="form-check-label">I have reviewed the preview and confirm the import.</span></label><button class="btn btn-primary" type="submit">Import Validated Records</button>
        </form>
    </div></details>

    <div class="modal fade" id="scholarshipInvigilatorModal" tabindex="-1" aria-labelledby="invigilatorModalTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header scholarship-card-header"><h3 class="modal-title" id="invigilatorModalTitle">Add Invigilator</h3><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <form method="POST" action="{{ route('western-scholarship.invigilators.store',$exam) }}" data-invigilator-form data-invigilator-person>@csrf
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-4" data-invigilator-source><label class="form-label" for="invigilatorSource">Add From</label><select id="invigilatorSource" name="source" class="form-select"><option value="hrm">HRM Staff</option><option value="external">External Invigilator</option></select></div>
            <div class="col-md-8 scholarship-field" data-invigilator-hrm><label for="invigilatorStaff">Staff Member</label><select class="form-select" name="staff_id" id="invigilatorStaff" data-scholarship-select><option value="">Select HRM staff</option>@foreach($invigilatorStaff as $s)<option value="{{ $s['id'] }}">{{ $s['staff_code'] }} · {{ $s['name_en'] }} · {{ $s['campus'] }}</option>@endforeach</select></div>
            @foreach(['staff_code'=>'Staff ID','name_en'=>'Staff Name English','campus'=>'Campus','position'=>'Position','phone'=>'Phone Number','email'=>'Email','organization'=>'Organization'] as $field=>$label)
            <div class="col-md-6"><label class="form-label" for="invigilator_{{ $field }}">{{ $label }} @if(in_array($field,['name_en','campus','position','phone']))<span class="text-danger">*</span>@endif</label><input class="form-control" name="{{ $field }}" id="invigilator_{{ $field }}" type="{{ $field==='email'?'email':($field==='phone'?'tel':'text') }}" maxlength="{{ $field==='staff_code'?80:($field==='campus'?120:($field==='phone'?50:160)) }}" @required(in_array($field,['name_en','campus','position','phone']))></div>
            @endforeach
            <div class="col-md-6"><label class="form-label" for="invigilatorGender">Gender <span class="text-danger">*</span></label><select class="form-select" id="invigilatorGender" name="gender" required><option value="">Select gender</option>@foreach(['Male','Female','Other'] as $gender)<option>{{ $gender }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label" for="invigilatorNotes">Notes</label><textarea class="form-control" name="notes" id="invigilatorNotes" maxlength="2000" rows="2"></textarea></div>
        </div></div><div class="modal-footer"><button class="btn" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-2" aria-hidden="true"></i>Save Invigilator</button></div>
        </form>
    </div></div></div>
    @endif
</div>
