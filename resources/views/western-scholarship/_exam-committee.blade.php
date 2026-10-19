@if(!$examPermissions['committee'])<div class="alert alert-info mb-0">Committee assignments are managed centrally across the participating campuses.</div>
@else
@include('western-scholarship._invigilators')
<details class="scholarship-invigilator-section mt-4"><summary class="fw-semibold"><i class="ti ti-shield-lock me-2" aria-hidden="true"></i>System Access Assignments</summary><div class="mt-3">
<p class="text-secondary">Assign staff duties by exam center, grade, room, or a combination. Blank scopes cover all centers / grades / rooms. Choose a center before selecting a room. Score Entry and Score Reviewer also require their matching permissions. Final Approver covers the entire exam.</p>
@php
    $committeeRooms=$candidates->whereNotNull('room_number')->map(fn($c)=>['center'=>$c->exam_center_id,'room'=>$c->room_number,'grade'=>$c->grade_number])->unique(fn($r)=>($r['center']??0).':'.$r['room'])->values();
@endphp
@if(!$exam->published_at)
<form method="POST" action="{{ route('western-scholarship.committee.add',$exam) }}" class="row g-3 mb-4" data-committee-form data-committee-rooms="{{ $committeeRooms->toJson() }}">
    @csrf
    <div class="col-md-3 scholarship-field"><label for="committeeUser">Staff Member</label><select id="committeeUser" class="form-select" name="user_id" data-scholarship-select required><option value="">Select staff member</option>@foreach($staffOptions as $staff)<option value="{{ $staff->id }}">{{ $staff->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label" for="committeeRole">Role</label><select id="committeeRole" class="form-select" name="role" required>@foreach($committeeRoles as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label" for="committeeGrade">Grade</label><select id="committeeGrade" class="form-select" name="grade_number"><option value="">All grades</option>@foreach(range(1,12) as $g)<option value="{{ $g }}">{{ $g }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label" for="committeeCenter">Exam Center</label><select id="committeeCenter" class="form-select" name="exam_center_id"><option value="">All centers</option>@foreach($exam->centers as $center)<option value="{{ $center->id }}">{{ $center->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label" for="committeeRoom">Room</label><select id="committeeRoom" class="form-select" name="room_number"><option value="">All rooms</option>@foreach($committeeRooms as $room)<option value="{{ $room['room'] }}">{{ $room['room'] }}</option>@endforeach</select></div>
    <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary">Add</button></div>
</form>
@endif
<div class="table-responsive"><table class="table"><thead><tr><th>Staff Member</th><th>Role</th><th>Exam Center</th><th>Grade</th><th>Room</th><th></th></tr></thead><tbody>@forelse($exam->committee as $member)<tr><td>{{ $member->user?->name }}</td><td>{{ $committeeRoles[$member->role] }}</td><td>{{ $member->examCenter?->name ?? ($member->room_number?'Legacy plan':'All') }}</td><td>{{ $member->grade_number ?? 'All' }}</td><td>{{ $member->room_number ?? 'All' }}</td><td>@if(!$exam->published_at)<form method="POST" action="{{ route('western-scholarship.committee.remove',[$exam,$member]) }}" data-scholarship-confirm="Remove this committee assignment?">@csrf<button class="btn btn-outline-danger btn-sm">Remove</button></form>@endif</td></tr>@empty<tr><td colspan="6" class="text-secondary">No committee assignments yet.</td></tr>@endforelse</tbody></table></div>
</div></details>
@endif
