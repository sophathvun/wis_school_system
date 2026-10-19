@forelse($results->groupBy(fn($row)=>$row['candidate']->grade_number) as $grade=>$gradeRows)
    @php
        $subjects=$workflow->applicableSubjects($exam,(int)$grade);
        $slots=$workflow->awardSlots($exam,(int)$grade);
        $saved=$savedAwards->get($grade,collect())->keyBy('slot');
        $pool=$awardGrades->get($grade,$gradeRows);
        $ready=$exam->seats_locked_at && $pool->every(fn($row)=>$row['complete'] && $row['candidate']->reviewed_at);
        $editable=$examPermissions['awards'] && !$exam->published_at && !$exam->isHistorical() && $ready;
        $choices=$slots->mapWithKeys(function($slot)use($saved,$grade){
            $choice=(int)old('award_grade')===(int)$grade?old('awards.'.$slot['key'],[]):($saved->get($slot['key'])?->toArray()??[]);
            return [$slot['key']=>['candidate_id'=>$choice['candidate_id']??null,'award_percent'=>$choice['award_percent']??$slot['percent'],'reason'=>$choice['reason']??'']];
        });
        $metrics=$pool->map(fn($row)=>['id'=>$row['candidate']->id,'eligible'=>$row['complete'] && $row['candidate']->reviewed_at && !$row['candidate']->absent,'total'=>$row['total'],'scores'=>$row['candidate']->scores->pluck('score','subject_id')])->values();
    @endphp
    <section class="scholarship-result-grade mb-4" data-result-grade="{{ $grade }}" aria-labelledby="resultGrade{{ $grade }}">
        <h4 id="resultGrade{{ $grade }}" class="mb-3">Grade {{ $grade }}</h4>
        @if($editable)
            <form id="resultAwards{{ $grade }}" method="POST" action="{{ route('western-scholarship.awards.save',[$exam,$grade]) }}" data-result-award-form>
                @csrf<input type="hidden" name="version" value="{{ $exam->version }}"><input type="hidden" name="award_grade" value="{{ $grade }}">
                @foreach($slots as $slot)
                    <div hidden data-result-award-slot="{{ $slot['key'] }}" data-slot-label="{{ str_replace(' — Total Score','',$slot['label']) }}" data-slot-subject="{{ $slot['subject_id'] }}" data-slot-percent="{{ $slot['percent'] }}">
                        <input type="hidden" name="awards[{{ $slot['key'] }}][candidate_id]" value="{{ $choices[$slot['key']]['candidate_id'] }}" data-slot-candidate>
                        <input type="hidden" name="awards[{{ $slot['key'] }}][award_percent]" value="{{ $choices[$slot['key']]['award_percent'] }}" data-slot-award>
                        <input type="hidden" name="awards[{{ $slot['key'] }}][reason]" value="{{ $choices[$slot['key']]['reason'] }}" data-slot-reason>
                    </div>
                @endforeach
                <script type="application/json" data-result-grade-metrics>@json($metrics)</script>
            </form>
        @endif
        <div class="table-responsive scholarship-scroll-table scholarship-result-overflow">
            <table class="table table-vcenter scholarship-result-table" style="--result-subject-count:{{ max(1,$subjects->count()) }}">
                <colgroup><col class="result-col-number"><col class="result-col-photo"><col class="result-col-student"><col class="result-col-exam">@foreach(range(1,max(1,$subjects->count())) as $column)<col class="result-col-subject">@endforeach<col class="result-col-total"><col class="result-col-rank"><col class="result-col-award"><col class="result-col-status"></colgroup>
                <thead>
                    <tr>
                        <th rowspan="2" class="text-center">No.</th><th rowspan="2" class="text-center">Photo</th><th rowspan="2">Student</th><th rowspan="2">Examinations</th>
                        <th colspan="{{ max(1,$subjects->count()) }}" class="text-center">Exam Scores</th>
                        <th rowspan="2" class="text-center" aria-sort="none"><button type="button" class="scholarship-score-sort" data-result-sort="total" data-sort-label="Total Score" aria-label="Sort Total Score ascending">Total Score <i class="ti ti-arrows-sort" aria-hidden="true"></i></button></th>
                        <th rowspan="2">Exam Rank</th><th rowspan="2" class="text-center">Award</th><th rowspan="2">Status</th>
                    </tr>
                    <tr>@forelse($subjects as $subject)<th class="text-center" aria-sort="none"><button type="button" class="scholarship-score-sort" data-result-sort="{{ $subject->id }}" data-sort-label="{{ $subject->name }}" aria-label="Sort {{ $subject->name }} ascending">{{ $subject->name }} <i class="ti ti-arrows-sort" aria-hidden="true"></i></button></th>@empty<th>No subjects configured</th>@endforelse</tr>
                </thead>
                <tbody>
                @foreach($gradeRows as $row)
                    @php
                        $candidate=$row['candidate'];$scores=$candidate->scores->keyBy('subject_id');
                        $selectedSlot=$choices->search(fn($choice)=>(string)($choice['candidate_id']??'')===(string)$candidate->id);
                        $choice=$selectedSlot!==false?$choices[$selectedSlot]:null;
                        $canSelect=$editable && $row['complete'] && $candidate->reviewed_at && !$candidate->absent;
                        $enrollment=$candidate->enrollment;$classParts=explode(' / ',(string)($candidate->snapshot['class']??''));
                        $gradeLabel=trim((string)($enrollment?->grade?->grade_short_name ?: $enrollment?->grade?->grade ?: $candidate->grade_number));
                        $gradeLabel=preg_replace('/^(?:grade\s*|g)(?=\d)/i','',$gradeLabel);
                        $class=trim((string)($enrollment?->schoolClass?->class_name ?? $classParts[0]));
                        $gradeClass=$gradeLabel!=='' && str_starts_with(strtolower($class),strtolower($gradeLabel))?$class:$gradeLabel.$class;
                        $group=$enrollment?->session?->session_short_name ?: $enrollment?->schoolGroup?->group_name ?: ($classParts[1]??null);
                        $placement=collect([$exam->academicYear?->academic_year,$candidate->snapshot['campus']??null,$gradeClass,$group])->filter(fn($part)=>filled($part))->implode('-');
                        $photo=$candidate->student?->photo_path;$name=$candidate->snapshot['name_en'] ?: $candidate->snapshot['name_kh'];
                    @endphp
                    <tr data-result-candidate="{{ $candidate->id }}">
                        <td class="text-center" data-result-number>{{ 1+$results->search(fn($result)=>$result['candidate']->id===$candidate->id) }}</td>
                        <td class="text-center">
                            @if($photo)<button type="button" class="scholarship-rank-photo scholarship-photo-trigger mx-auto" data-scholarship-photo="{{ asset('storage/'.$photo) }}" data-photo-name="{{ $name }}" aria-label="View photo of {{ $name }}"><img src="{{ asset('storage/'.$photo) }}" alt="Photo of {{ $name }}" width="600" height="800" loading="lazy"></button>
                            @else<div class="scholarship-rank-photo mx-auto"><i class="ti ti-user" aria-hidden="true"></i><span class="visually-hidden">No student photo</span></div>@endif
                        </td>
                        <td class="scholarship-result-student"><strong>{{ $candidate->snapshot['student_code'] }}</strong><div class="scholarship-khmer">{{ $candidate->snapshot['name_kh'] }}</div><div>{{ $candidate->snapshot['name_en'] }}</div><div class="small text-secondary mt-1">{{ $placement }}</div></td>
                        <td class="scholarship-result-exam"><div>{{ $candidate->exam_center_name ?: 'Main Exam Center' }}</div><div class="small text-secondary mt-1">Room: <strong>{{ $candidate->room_number ?? '—' }}</strong> | Seat: <strong>{{ $candidate->room_seat ?? '—' }}</strong></div><div class="small text-secondary mt-1">Code: <strong>{{ $candidate->candidate_code ?: '—' }}</strong></div></td>
                        @forelse($subjects as $subject)<td class="text-center" data-result-score="{{ $subject->id }}" data-sort-value="{{ $scores->get($subject->id)?->score }}">{{ $scores->get($subject->id)?->score ?? '—' }}<small class="d-block text-secondary mt-1 scholarship-subject-rank" data-result-subject-rank>Rank: <strong>{{ $subjectRanks[$candidate->id][$subject->id] ?? '—' }}</strong></small></td>@empty<td class="text-center">—</td>@endforelse
                        <td class="text-center" data-result-score="total" data-sort-value="{{ $row['total'] }}"><strong>{{ $row['total'] ?? '—' }}</strong><div class="small text-secondary mt-1">{{ $row['percentage']===null?'—':number_format($row['percentage'],2).'%' }}</div></td>
                        <td class="scholarship-result-rank">
                            @if(!$exam->isHistorical())
                                <label class="visually-hidden" for="resultRank{{ $candidate->id }}">Exam Rank for {{ $name }}</label>
                                <select id="resultRank{{ $candidate->id }}" class="form-select form-select-sm" data-result-award-select data-saved-slot="{{ $selectedSlot!==false?$selectedSlot:'' }}" @disabled(!$canSelect)>
                                    <option value="">No Award</option>@foreach($slots as $slot)<option value="{{ $slot['key'] }}" @selected($selectedSlot===$slot['key'])>{{ str_replace(' — Total Score','',$slot['label']) }}</option>@endforeach
                                </select>
                                <div class="invalid-feedback" data-result-rank-error></div>
                                @if($canSelect)<details class="small mt-2" data-result-exception @if(!$choice) hidden @elseif(filled($choice['reason'])) open @endif><summary>Exception reason</summary><textarea class="form-control form-control-sm mt-2" maxlength="1000" data-result-award-reason aria-label="Award exception reason for {{ $name }}" placeholder="Required for a lower-score selection">{{ $choice['reason']??'' }}</textarea></details>@endif
                            @else<div>{{ $row['award_label'] ?: 'No Award' }}</div>@endif
                            <div class="small text-secondary mt-1">Score rank: <strong>{{ $row['rank'] ?? '—' }}</strong></div>
                        </td>
                        <td class="scholarship-result-award text-center">
                            @if($canSelect)<div class="input-group input-group-sm" data-result-award-editor @if(!$choice) hidden @endif><input class="form-control" type="number" min="0" max="100" step="0.01" value="{{ $choice['award_percent']??'' }}" data-result-award-percent aria-label="Scholarship percentage for {{ $name }}"><span class="input-group-text">%</span></div><span data-result-no-award @if($choice) hidden @endif>—</span>
                            @else<span class="badge {{ $row['award']!==null?'bg-green-lt':'text-secondary' }}">{{ $row['award']!==null?number_format($row['award'],2).'%':'—' }}</span>@endif
                        </td>
                        <td><span class="badge {{ $candidate->reviewed_at?'bg-green-lt':'bg-orange-lt' }}">{{ $row['status'] }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($editable)<div class="d-flex justify-content-end align-items-center gap-3 mt-3"><span class="small text-warning" data-result-unsaved hidden>Unsaved winners</span><button type="submit" form="resultAwards{{ $grade }}" class="btn btn-primary" data-result-save><i class="ti ti-device-floppy me-2" aria-hidden="true"></i>Save Grade {{ $grade }} Winners</button></div>
        @elseif($examPermissions['awards'] && !$exam->published_at && !$exam->isHistorical())<p class="small text-secondary mt-3">Complete and review all Grade {{ $grade }} scores before selecting winners.</p>@endif
    </section>
@empty<div class="text-center text-secondary py-4">No results match these filters.</div>@endforelse
