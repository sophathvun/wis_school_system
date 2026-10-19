@php
    $scoringSubjects=($exam?->seats_locked_at && !$exam->published_at)
        ? $scoringCandidates->filter(fn($candidate)=>$workflow->canScore(auth()->user(),$exam,$candidate))
            ->flatMap(fn($candidate)=>$workflow->applicableSubjects($exam,$candidate->grade_number))->unique('id')->values()
        : collect();
@endphp
@if($scoringSubjects->isNotEmpty())
    <div class="scholarship-scoring-mode mt-3">
        <div class="scholarship-field">
            <label for="scholarshipScoringSubject">Scoring Subject</label>
            <select id="scholarshipScoringSubject" class="form-select" data-scoring-subject data-placeholder="Select a subject to enter scores" aria-describedby="scholarshipScoringHelp">
                <option value="">Select a subject to enter scores</option>
                @foreach($scoringSubjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }} ({{ $subject->grade_from===$subject->grade_to?'Grade '.$subject->grade_from:'Grades '.$subject->grade_from.'–'.$subject->grade_to }})</option>@endforeach
            </select>
        </div>
        <button type="button" class="btn btn-outline-secondary" data-lock-score-inputs disabled><i class="ti ti-lock me-2" aria-hidden="true"></i>Lock Scores</button>
        <p class="small text-secondary mb-0" id="scholarshipScoringHelp">Select a subject to unlock its score column. Press Tab to enter the next student's score in that subject.</p>
    </div>
@endif
