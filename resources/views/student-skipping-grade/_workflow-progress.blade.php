@if($record->usesSequentialFlow())
    @php
        $progressRequester=$record->student_snapshot['requester']??($record->creator?app(\App\Services\SkippingGradeWorkflow::class)->snapshot($record->creator,'Requester','campus',$record->campus_id):null);
        $requesterName=$progressRequester['name']??'Unavailable';
        $submissionLabel=$record->submitted_at?'Submitted':'Draft';
    @endphp
    <div class="skipping-workflow-progress" aria-label="Approval progress">
        <div class="skipping-workflow-reviewer" title="{{ $requesterName }} · Requester · {{ $submissionLabel }}{{ $record->submitted_at?' · '.$record->submitted_at->format('d-M-Y H:i'):'' }}">
            <span class="skipping-workflow-person skipping-workflow-{{ $record->submitted_at?'submitted':'draft' }}">
                @if($progressRequester['photo']??null)<img src="{{ $progressRequester['photo'] }}" alt="{{ $requesterName }}" width="30" height="30">@else<i class="ti ti-user" aria-hidden="true"></i>@endif
                <span class="visually-hidden">{{ $requesterName }}: Requester — {{ $submissionLabel }}</span>
            </span>
            @if($record->submitted_at)<time class="skipping-workflow-review-date text-secondary" datetime="{{ $record->submitted_at->toIso8601String() }}" aria-label="Request submitted on {{ $record->submitted_at->format('d-M-Y H:i') }}">{{ $record->submitted_at->format('d-M-Y') }}</time>@endif
        </div>
        @foreach($record->approvalSteps as $step)
            @foreach($step->trackingEntries() as $entry)
                @php
                    $person=$entry['person'];
                @endphp
                <div class="skipping-workflow-reviewer" title="{{ $entry['name'] }} · {{ $person['title']??$step->title }} · {{ $entry['label'] }}{{ $entry['acted_at']?' · '.$entry['acted_at']->format('d-M-Y H:i'):'' }}">
                    <span class="skipping-workflow-person skipping-workflow-{{ $entry['status'] }}">
                        @if($person['photo']??null)<img src="{{ $person['photo'] }}" alt="{{ $entry['name'] }}" width="30" height="30">@else<i class="ti ti-user" aria-hidden="true"></i>@endif
                        <span class="visually-hidden">{{ $entry['name'] }}: {{ $person['title']??$step->title }} — {{ $entry['label'] }}</span>
                    </span>
                    @if($entry['acted_at'])<time class="skipping-workflow-review-date text-secondary" datetime="{{ $entry['acted_at']->toIso8601String() }}" aria-label="{{ $entry['name'] }}: {{ $entry['label'] }} on {{ $entry['acted_at']->format('d-M-Y') }}">{{ $entry['acted_at']->format('d-M-Y') }}</time>@endif
                    @if(!$entry['individual'] && !$step->actor_snapshot && $entry['reviewer_count']>1)<small class="text-secondary">×{{ $entry['reviewer_count'] }}</small>@endif
                </div>
            @endforeach
        @endforeach
    </div>
    @if($record->status==='draft')<small class="text-secondary">Step-by-step · Reviewers assigned on submission</small>@endif
@else<small class="text-secondary">Current Flow</small>@endif
