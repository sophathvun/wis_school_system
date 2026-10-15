@php
    $editingExam=$exam ?? null;
    $canSettings=$editingExam ? ($examPermissions['settings'] ?? false) : \App\Support\ScholarshipPermissions::allows(auth()->user(),'settings');
    $locked=$editingExam?->seats_locked_at || $editingExam?->published_at;
    $subjectRows=old('subjects',$editingExam?->subjects->map->only(['name','grade_from','grade_to','max_score','weight'])->all());
    if(!$subjectRows) $subjectRows=collect([1,7])->flatMap(fn($from)=>array_fill(0,4,['name'=>'','grade_from'=>$from,'grade_to'=>$from===1?6:12,'max_score'=>100,'weight'=>1]))->all();
    $awardScheme=old('award_scheme',$editingExam?->awardScheme() ?? ['total_1'=>100,'total_2'=>75,'total_3'=>50,'subject'=>25]);
    $centerCampuses=$examCenterCampuses??collect();
    $savedCenters=$editingExam?->centers??collect();
    $centerRows=old('exam_centers',$savedCenters->map(fn($center)=>['id'=>$center->id,'key'=>'center-'.$center->id,'name'=>$center->name])->all());
    if(!$centerRows && !$locked)$centerRows=[['id'=>null,'key'=>'new-main','name'=>'Main Exam Center']];
    $savedAssignments=[];
    foreach($savedCenters as $center)foreach($center->campuses as $campus)$savedAssignments[$campus->id]='center-'.$center->id;
    $centerAssignments=old('center_assignments',$savedAssignments);
    if(!$savedCenters->count() && !session()->hasOldInput('exam_centers') && !$locked)$centerAssignments=$centerCampuses->mapWithKeys(fn($campus)=>[$campus['id']=>'new-main'])->all();
@endphp
@if(!$canSettings)<div class="alert alert-info mb-0">You need Scholarship Settings permission for every participating campus to configure this exam.</div>
@else
@if($locked)<div class="alert alert-info">Settings are locked because seats are confirmed. You can reopen an unscored seat plan from Seat Plan. Scored exams keep their subjects and weighting unchanged.</div>@endif
<form method="POST" action="{{ route('western-scholarship.exams.save') }}" data-exam-settings data-center-campuses-url="{{ route('western-scholarship.exam-center-campuses') }}">
@csrf
<input type="hidden" name="exam_id" value="{{ $editingExam?->id }}"><input type="hidden" name="version" value="{{ $editingExam?->version ?? 1 }}">
<fieldset @disabled($locked)>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label" for="examYear">Regular Academic Year</label>@if($editingExam)<input type="hidden" name="academic_year_id" value="{{ $editingExam->academic_year_id }}">@endif<select id="examYear" class="form-select" @if(!$editingExam) name="academic_year_id" required @else disabled @endif><option value="">Select academic year</option>@foreach($academicYears as $year)@php $configured=($exams??collect())->contains('academic_year_id',$year->id); @endphp<option value="{{ $year->id }}" @disabled(!$editingExam && $configured) @selected(($editingExam || !$configured) && old('academic_year_id',$editingExam?->academic_year_id ?? $filters['academic_year_id'])==$year->id)>{{ $year->academic_year }}{{ !$editingExam && $configured?' (Already configured)':'' }}</option>@endforeach</select><small class="text-secondary">The academic year is the scholarship exam session name. Summer periods are excluded.</small></div>
        @include('western-scholarship._exam-date-picker')
        <div class="col-md-4"><label class="form-label" for="examCapacity">Students per Room</label><input id="examCapacity" type="number" class="form-control" name="room_capacity" min="1" max="200" required value="{{ old('room_capacity',$editingExam?->room_capacity ?? 25) }}"></div>
    </div>
    <div class="alert alert-info mt-3">Every student with an assigned class rank in this academic year is automatically an exam candidate. Their enrollment records determine the participating campuses. No separate candidate selection is required.</div>
    <section class="scholarship-center-settings mt-4" aria-labelledby="examCentersTitle" data-exam-centers>
        <div class="d-flex align-items-center justify-content-between gap-2 mb-2"><h4 id="examCentersTitle" class="mb-0">Exam Centers</h4><button type="button" class="btn btn-outline-primary btn-sm" data-add-center><i class="ti ti-plus me-1"></i>Add Center</button></div>
        <p class="small text-secondary">Create the exam locations, then assign each campus to one center. Each grade starts a new room within its center.</p>
        @error('exam_centers')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        <div data-center-rows>@foreach($centerRows??[] as $i=>$center)@include('western-scholarship._center-row',['i'=>$i,'center'=>$center])@endforeach</div>
        @if($locked && !$savedCenters->count())<div class="alert alert-secondary">This confirmed seat plan was created before exam centers were configured.</div>@endif
        <h5 class="mt-3 mb-2">Campus Assignments</h5>
        <p class="small text-secondary">Only campuses with assigned student ranks in the selected academic year appear here.</p>
        <div class="alert alert-danger" role="alert" data-center-load-error hidden></div>
        @error('center_assignments')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        <div data-center-campus-rows aria-live="polite">
            @foreach($centerCampuses as $campus)
                <div class="scholarship-center-campus-row" data-center-campus="{{ $campus['id'] }}">
                    <div><label for="centerCampus{{ $campus['id'] }}" class="fw-semibold mb-0">{{ $campus['label'] }}</label><div class="small text-secondary">{{ $campus['count'] }} ranked student(s)</div></div>
                    <div><select id="centerCampus{{ $campus['id'] }}" name="center_assignments[{{ $campus['id'] }}]" class="form-select @error('center_assignments.'.$campus['id']) is-invalid @enderror" required data-campus-center><option value="">Select exam center</option>@foreach($centerRows??[] as $center)<option value="{{ $center['key'] }}" @selected(($centerAssignments[$campus['id']]??'')===$center['key'])>{{ $center['name'] }}</option>@endforeach</select>@error('center_assignments.'.$campus['id'])<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                </div>
            @endforeach
            @if($centerCampuses->isEmpty())<p class="text-secondary small mb-0">No ranked students in this academic year. Campuses will appear after student ranks are assigned.</p>@endif
        </div>
    </section>
    <div class="d-flex align-items-center justify-content-between mt-4 gap-2"><h4 class="mb-0">Exam Subjects</h4><button class="btn btn-outline-primary btn-sm" type="button" data-add-subject><i class="ti ti-plus me-1"></i>Add Subject</button></div>
    <p class="small text-secondary mt-2">Four rows per grade band are provided. Enter the actual subject names, add more rows, or remove unused rows. Set the same From/To grade for a grade-specific subject. Percentage = weighted average of each subject's score percentage.</p>
    <div class="table-responsive"><table class="table scholarship-config-table"><thead><tr><th>Subject</th><th>From Grade</th><th>To Grade</th><th>Maximum Score</th><th>Weight</th><th></th></tr></thead><tbody data-subject-rows>@foreach($subjectRows as $i=>$subject)@include('western-scholarship._subject-row',['i'=>$i,'subject'=>$subject])@endforeach</tbody></table></div>
    <h4 class="mt-4">Scholarship Award Rules</h4>
    <p class="small text-secondary">Select one student for each total-score place and one per subject in Result. Four subjects provide seven distinct winners per grade. Ties require a selection; students do not receive multiple awards.</p>
    <div class="row g-3">@foreach(['total_1'=>'1st Place — Total Score','total_2'=>'2nd Place — Total Score','total_3'=>'3rd Place — Total Score','subject'=>'Best in Each Subject'] as $key=>$label)<div class="col-sm-6 col-xl-3"><label class="form-label" for="awardScheme{{ $key }}">{{ $label }} (%)</label><input id="awardScheme{{ $key }}" class="form-control" type="number" name="award_scheme[{{ $key }}]" min="0" max="100" step="0.01" required value="{{ $awardScheme[$key] ?? 0 }}"></div>@endforeach</div>
    <button class="btn btn-primary mt-3" type="submit"><i class="ti ti-device-floppy me-2"></i>{{ $editingExam ? 'Save Exam Settings':'Set Up Academic Year' }}</button>
</fieldset>
<template data-subject-template>@include('western-scholarship._subject-row',['i'=>'__INDEX__','subject'=>['name'=>'','grade_from'=>1,'grade_to'=>6,'max_score'=>100,'weight'=>1]])</template>
<template data-center-template>@include('western-scholarship._center-row',['i'=>'__INDEX__','center'=>['id'=>null,'key'=>'__KEY__','name'=>'']])</template>
</form>
@if($editingExam && ($events ?? collect())->isNotEmpty())<h4 class="mt-4">Recent Exam Activity</h4><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Action</th><th>By</th><th>Reason</th></tr></thead><tbody>@foreach($events as $event)<tr><td>{{ $event->created_at->format('d-M-Y H:i') }}</td><td>{{ \Illuminate\Support\Str::headline($event->action) }}</td><td>{{ $event->user?->name ?? 'Deleted user' }}</td><td>{{ $event->details['reason'] ?? '—' }}</td></tr>@endforeach</tbody></table></div>@endif
@endif
