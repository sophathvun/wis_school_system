@if($exam->isHistorical())<div class="alert alert-info">Historical scores are preserved. View them in Result or use Import / Export for a validated correction.</div>@endif
@if(!($scoreListReady??false))<div class="alert alert-info">Select Academic Year, Exam Center, and Room to display the score list.</div>
@elseif(!$exam->seats_locked_at)<div class="alert alert-info">Confirm the seat plan before entering scores.</div>
@else
@if($exam->published_at)<div class="alert alert-success">Results are published. Scores are locked.</div>@endif
<div data-scholarship-scoring-list>
@php
    $context=request()->only(['score_center','score_room','score_student']);
    $editableCandidates=$scoringCandidates->filter(fn($candidate)=>!$exam->published_at && $workflow->canScore(auth()->user(),$exam,$candidate));
    $editableCount=$editableCandidates->count();
    $reviewCandidates=$scoringCandidates->filter(fn($candidate)=>!$exam->published_at && !$candidate->reviewed_at && $workflow->canScore(auth()->user(),$exam,$candidate,true));
    $reviewReady=$reviewCandidates->every(fn($candidate)=>$workflow->calculate($exam,$candidate)['complete']);
@endphp
@if($editableCount)
    <form id="scholarshipScoreDrafts" method="POST" action="{{ route('western-scholarship.scores.save-drafts',array_merge([$exam],$context)) }}" data-unsaved-restored="{{ collect(old('rows',[]))->contains(fn($row)=>is_array($row)&&array_key_exists('scores',$row))?'true':'false' }}">@csrf</form>
@endif
@forelse($scoringCandidates->groupBy('grade_number') as $grade=>$gradeCandidates)
    @php
        $subjects=$workflow->applicableSubjects($exam,(int)$grade);
    @endphp
    <section class="scholarship-scoring-grade mb-4" aria-labelledby="scoringGrade{{ $grade }}">
        <h4 id="scoringGrade{{ $grade }}" class="visually-hidden">Grade {{ $grade }}</h4>
        <div class="table-responsive scholarship-scoring-overflow">
            <table class="table table-vcenter scholarship-scoring-table">
                <thead>
                    <tr><th rowspan="2" class="text-center">No.</th><th rowspan="2" class="text-center">Photo</th><th rowspan="2">Students</th><th rowspan="2">Exams</th><th colspan="{{ max(1,$subjects->count()) }}" class="text-center">Scores</th><th rowspan="2" class="text-center">Total Score</th><th rowspan="2" class="text-center">Attendance</th><th rowspan="2" class="text-center">Action</th></tr>
                    <tr>@forelse($subjects as $subject)<th class="text-center" data-score-subject-heading="{{ $subject->id }}">{{ $subject->name }}</th>@empty<th class="text-center">No subjects configured</th>@endforelse</tr>
                </thead>
                <tbody>
                @foreach($gradeCandidates as $candidate)
                    @php
                        $scores=$candidate->scores->keyBy('subject_id');
                        $calculation=$workflow->calculate($exam,$candidate);
                        $canEnter=$workflow->canScore(auth()->user(),$exam,$candidate) && !$exam->published_at;
                        $canReview=$workflow->canScore(auth()->user(),$exam,$candidate,true) && !$exam->published_at;
                        $scoreForm='scholarshipScoreDrafts';
                        $enrollment=$candidate->enrollment;
                        $classParts=explode(' / ',(string)($candidate->snapshot['class']??''));
                        $gradeLabel=trim((string)($enrollment?->grade?->grade_short_name ?: $enrollment?->grade?->grade ?: $candidate->grade_number));
                        $gradeLabel=preg_replace('/^(?:grade\s*|g)(?=\d)/i','',$gradeLabel);
                        $class=trim((string)($enrollment?->schoolClass?->class_name ?? $classParts[0]));
                        $gradeClass=$gradeLabel!=='' && str_starts_with(strtolower($class),strtolower($gradeLabel))?$class:$gradeLabel.$class;
                        $group=$enrollment?->session?->session_short_name ?: $enrollment?->schoolGroup?->group_name ?: ($classParts[1]??null);
                        $placement=collect([$exam->academicYear?->academic_year,$candidate->snapshot['campus']??null,$gradeClass,$group])->filter(fn($part)=>filled($part))->implode('-');
                        $photo=$candidate->student?->photo_path;
                        $name=$candidate->snapshot['name_en'] ?: $candidate->snapshot['name_kh'];
                        $sequence=1+$scoringCandidates->search(fn($row)=>$row->id===$candidate->id);
                        $restoring=old('rows.'.$candidate->id.'.scores')!==null;
                    @endphp
                    <tr data-scoring-candidate="{{ $candidate->id }}">
                        <td class="text-center">{{ $sequence }}</td>
                        <td class="text-center">
                            @if($photo)
                                <button type="button" class="scholarship-rank-photo scholarship-photo-trigger mx-auto" data-scholarship-photo="{{ asset('storage/'.$photo) }}" data-photo-name="{{ $name }}" aria-label="View photo of {{ $name }}"><img src="{{ asset('storage/'.$photo) }}" alt="Photo of {{ $name }}" width="600" height="800" loading="lazy"></button>
                            @else
                                <div class="scholarship-rank-photo mx-auto"><i class="ti ti-user" aria-hidden="true"></i><span class="visually-hidden">No student photo</span></div>
                            @endif
                        </td>
                        <td class="scholarship-scoring-student"><strong>{{ $candidate->snapshot['student_code'] }}</strong><div class="scholarship-khmer">{{ $candidate->snapshot['name_kh'] }}</div><div>{{ $candidate->snapshot['name_en'] }}</div><div class="small text-secondary mt-1">{{ $placement }}</div></td>
                        <td class="scholarship-scoring-exam"><div>{{ $candidate->exam_center_name ?: 'Main Exam Center' }}</div><div class="small text-secondary mt-1">Room: <strong>{{ $candidate->room_number }}</strong> | Seat: <strong>{{ $candidate->room_seat }}</strong></div></td>
                        @forelse($subjects as $subject)
                            <td class="scholarship-score-input-cell"><label class="visually-hidden" for="score{{ $candidate->id }}_{{ $subject->id }}">{{ $name }} — {{ $subject->name }} (maximum {{ $subject->max_score }})</label><input id="score{{ $candidate->id }}_{{ $subject->id }}" form="{{ $scoreForm }}" class="form-control" name="rows[{{ $candidate->id }}][scores][{{ $subject->id }}]" data-score-input="{{ $subject->id }}" data-can-score="{{ $canEnter?'true':'false' }}" type="number" min="0" max="{{ $subject->max_score }}" step="0.01" inputmode="decimal" enterkeyhint="next" tabindex="-1" aria-readonly="true" value="{{ $restoring?old('rows.'.$candidate->id.'.scores.'.$subject->id):$scores->get($subject->id)?->score }}" placeholder="Not Entered" readonly @disabled(!$canEnter)></td>
                        @empty<td class="text-secondary text-center">—</td>@endforelse
                        <td class="scholarship-scoring-total">
                            <strong>{{ $calculation['total'] ?? '—' }}</strong><span class="text-secondary"> / {{ $calculation['max_total'] ?? '—' }}</span>
                            <div class="small text-secondary mt-1">{{ $calculation['percentage']===null?'—':number_format($calculation['percentage'],2).'%' }}</div>
                            <span class="badge {{ $candidate->reviewed_at?'bg-green-lt':'bg-orange-lt' }} my-2">{{ $calculation['status'] }}</span>
                        </td>
                        <td class="scholarship-scoring-attendance">
                            <label class="form-check d-inline-flex align-items-center gap-2 mb-0"><input class="form-check-input m-0" form="{{ $scoreForm }}" type="checkbox" name="rows[{{ $candidate->id }}][absent]" value="1" @checked($restoring?old('rows.'.$candidate->id.'.absent'):$candidate->absent) @disabled(!$canEnter)><span class="form-check-label small">Absent</span></label>
                        </td>
                        <td class="scholarship-scoring-actions">
                            @if($canEnter)<input form="{{ $scoreForm }}" type="hidden" name="rows[{{ $candidate->id }}][version]" value="{{ $candidate->version }}">@endif
                            @if($candidate->reviewed_at)<span class="text-success small"><i class="ti ti-circle-check me-1" aria-hidden="true"></i>Reviewed</span>
                            @elseif($canReview)<span class="text-secondary small">Pending review</span>
                            @else<span class="text-secondary">—</span>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@empty
    <div class="alert alert-info">No scoring assignments match these filters. A committee manager can assign Score Entry or Score Reviewer access for a grade or room.</div>
@endforelse
@if($editableCount || $reviewCandidates->isNotEmpty())
    <div class="d-flex align-items-end justify-content-between flex-wrap gap-3 mb-3">
        <div class="small text-secondary">
            @if($editableCount)<div>Save drafts for all {{ $editableCount }} editable students in this list.</div>@endif
            @if($reviewCandidates->isNotEmpty())<div>Review saved scores and attendance for {{ $reviewCandidates->count() }} pending students in this list.</div>@endif
            @if(!$reviewReady)<div class="text-warning mt-1">Complete and save every subject score, or mark the student absent, before reviewing this list.</div>@endif
            <div class="text-warning mt-1" data-unsaved-score-review hidden>Save your score and attendance changes before marking reviewed.</div>
        </div>
        <div class="d-flex align-items-end flex-wrap gap-3">
            @if($editableCount)<button class="btn btn-primary" type="submit" form="scholarshipScoreDrafts" data-save-score-drafts><i class="ti ti-device-floppy me-2" aria-hidden="true"></i>Save Draft</button>@endif
            @if($reviewCandidates->isNotEmpty())
                <form method="POST" action="{{ route('western-scholarship.scores.review-all',array_merge([$exam],$context)) }}" data-score-review-form>
                    @csrf
                    @foreach($reviewCandidates as $candidate)<input type="hidden" name="rows[{{ $candidate->id }}][version]" value="{{ $candidate->version }}">@endforeach
                    <button class="btn btn-outline-success" type="submit" data-review-scores @disabled(!$reviewReady)><i class="ti ti-checks me-2" aria-hidden="true"></i>Mark Reviewed</button>
                </form>
            @endif
        </div>
    </div>
@endif
</div>
@endif
