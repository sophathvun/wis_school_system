<section class="my-4" aria-label="Scholarship winners">
    <h3>Scholarship Winners</h3>
    <p class="text-secondary">Choose one winner per award. Start with the three total-score places, then select the best remaining student in each subject, in the order shown. Tied scores allow you to choose any tied student. Four subjects provide seven distinct winners per grade.</p>
    @foreach($awardGrades as $grade=>$gradeRows)
    @php
        $slots=$workflow->awardSlots($exam,$grade);
        $saved=$savedAwards->get($grade,collect())->keyBy('slot');
        $ready=$exam->seats_locked_at && $gradeRows->every(fn($r)=>$r['complete'] && $r['candidate']->reviewed_at);
        $eligible=$gradeRows->filter(fn($r)=>$r['complete'] && $r['candidate']->reviewed_at && !$r['candidate']->absent);
        $useOld=(int)old('award_grade')===(int)$grade;
    @endphp
    <details class="scholarship-award-grade" @if($loop->first) open @endif>
        <summary class="fw-bold p-3">Grade {{ $grade }} <span class="badge bg-primary-lt ms-2">{{ $saved->whereNotNull('candidate_id')->count() }} / {{ $slots->count() }} winners</span></summary>
        @if(!$ready)<p class="text-secondary px-3">Complete and review every candidate in this grade before selecting winners.</p>@endif
        @if($eligible->count()<$slots->count() && $ready)<p class="text-secondary px-3">{{ $eligible->count() }} eligible student(s) for {{ $slots->count() }} awards. Remaining slots stay vacant after every eligible student has received one award.</p>@endif
        <form method="POST" action="{{ route('western-scholarship.awards.save',[$exam,$grade]) }}" data-award-form class="p-3 pt-0">
            @csrf<input type="hidden" name="version" value="{{ $exam->version }}"><input type="hidden" name="award_grade" value="{{ $grade }}">
            <fieldset @disabled(!$ready || $exam->published_at)>
                <div class="scholarship-award-grid">
                @foreach($slots as $slot)
                @php
                    $choice=$useOld?old('awards.'.$slot['key'],[]):($saved->get($slot['key'])?->toArray() ?? []);
                    $metric=fn($r)=>$slot['subject_id']?$r['candidate']->scores->firstWhere('subject_id',$slot['subject_id'])?->score:$r['total'];
                @endphp
                <div class="scholarship-award-slot" data-award-slot>
                    <h4>{{ $slot['label'] }}</h4>
                    <div class="scholarship-field mb-2">
                        <label for="award{{ $grade }}{{ $slot['key'] }}">Winner</label>
                        <select id="award{{ $grade }}{{ $slot['key'] }}" class="form-select" name="awards[{{ $slot['key'] }}][candidate_id]" data-scholarship-select data-award-winner @disabled(!$ready || $exam->published_at)>
                            <option value="">Select winner / No eligible student remaining</option>
                            @foreach($eligible->sortByDesc($metric) as $row)
                            <option value="{{ $row['candidate']->id }}" data-score="{{ $metric($row) }}" @selected(($choice['candidate_id']??null)==$row['candidate']->id)>{{ $row['candidate']->snapshot['name_en'] }} · {{ $row['candidate']->snapshot['name_kh'] }} · {{ $row['candidate']->snapshot['student_code'] }} · {{ $row['candidate']->snapshot['campus'] }} · Score: {{ $metric($row) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="small text-secondary mb-2" data-award-guidance></p>
                    <label class="form-label" for="percent{{ $grade }}{{ $slot['key'] }}">Scholarship (%)</label>
                    <input id="percent{{ $grade }}{{ $slot['key'] }}" class="form-control" type="number" min="0" max="100" step="0.01" required name="awards[{{ $slot['key'] }}][award_percent]" value="{{ $choice['award_percent']??$slot['percent'] }}">
                    <details class="mt-2" @if(!empty($choice['reason'])) open @endif><summary class="small">Exception reason (if selecting a lower score)</summary><textarea class="form-control mt-2" aria-label="Exception reason for {{ $slot['label'] }}" name="awards[{{ $slot['key'] }}][reason]" maxlength="1000" placeholder="Explain the committee's decision">{{ $choice['reason']??'' }}</textarea></details>
                </div>
                @endforeach
                </div>
                @if(!$exam->published_at)<button class="btn btn-primary mt-3" type="submit">Save Grade {{ $grade }} Winners</button>@endif
            </fieldset>
        </form>
    </details>
    @endforeach
    <p class="text-secondary small mt-2">Winners and scholarship percentages can be edited before publication. A score change clears all award selections for that grade. Reopen published results before making corrections.</p>
</section>
