@php
    $workflowReviewerCount=1+$record->approvalSteps->sum(fn($step)=>count($step->trackingEntries()));
@endphp
<div class="card mb-3 skipping-approval-workflow"><div class="card-header skipping-header"><h3 class="card-title">Approval Workflow</h3><span class="badge">Step-by-step Flow</span></div><div class="card-body">
    @if($record->status==='draft')<p class="mb-0 text-secondary">This request uses the step-by-step flow. The campus and central reviewer sequence will be saved when the request is submitted.</p>
    @else
    <ol class="skipping-workflow-timeline {{ $workflowReviewerCount>8?'is-vertical':'' }}" style="--skipping-reviewer-count: {{ $workflowReviewerCount }}" data-skipping-workflow-timeline>
        <li class="skipping-workflow-node skipping-workflow-submitted">
            <div class="skipping-workflow-avatar">@if($requester['photo']??null)<img src="{{ $requester['photo'] }}" alt="{{ $requester['name'] }}" width="64" height="64">@else<i class="ti ti-user" aria-hidden="true"></i>@endif</div>
            <div class="skipping-workflow-node-details">
                <strong>{{ $requester['name']??'Unavailable' }}</strong><span>Requester</span>
                <span class="badge skipping-workflow-badge-submitted">Submitted</span>
                @if($record->submitted_at)<small>{{ $record->submitted_at->format('d-M-Y H:i') }}</small>@endif
                @if($submitter && ($submitter['user_id']??null)!==($requester['user_id']??null))<small>Submitted by {{ $submitter['name'] }}</small>@endif
            </div>
        </li>
        @foreach($record->approvalSteps as $step)
            @foreach($step->trackingEntries() as $entry)
                @php
                    $person=$entry['person'];
                @endphp
                <li class="skipping-workflow-node skipping-workflow-{{ $entry['status'] }}">
                    <div class="skipping-workflow-avatar">@if($person['photo']??null)<img src="{{ $person['photo'] }}" alt="{{ $entry['name'] }}" width="64" height="64">@else<i class="ti ti-user" aria-hidden="true"></i>@endif</div>
                    <div class="skipping-workflow-node-details">
                    <strong>{{ $entry['name'] }}</strong><span>{{ $person['title']??$step->title }}</span>
                    @if(!$entry['individual'] && $entry['reviewer_count']>1)<small>One of {{ $entry['reviewer_count'] }} eligible reviewers</small>@endif
                    @if(!$entry['individual'] && $step->actor_snapshot && $entry['reviewer_count']>1)<small>Assigned: {{ $step->reviewerNames() }}</small>@endif
                    <span class="badge skipping-workflow-badge-{{ $entry['status'] }}">{{ $entry['label'] }}</span>
                    @if($entry['acted_at'])<small>{{ $entry['acted_at']->format('d-M-Y H:i') }}</small>@endif
                    @if($entry['comments'])<p class="small skipping-prewrap">{{ $entry['comments'] }}</p>@endif
                    </div>
                </li>
            @endforeach
        @endforeach
    </ol>
    @endif
    @if(auth()->user()->isSuperAdmin() && $record->status==='pending')
        <details class="mt-3"><summary class="fw-semibold">Reassign an Unfinished Reviewer</summary><p class="text-secondary mt-2">Completed decisions cannot be reassigned. For a new Vice President, update Approval Settings first; reassigning the final step saves the currently configured signature and stamp for this request.</p>
            @foreach($record->approvalSteps->whereIn('status',['pending','waiting']) as $step)
                @foreach($step->reviewers->isNotEmpty()?$step->reviewers:[null] as $member)
                    @php
                        $previousPerson=$member?->reviewer_snapshot??$step->reviewer_snapshot;
                        $fieldId=$step->id.'-'.($member?->id??'original');
                    @endphp
                <form method="post" action="{{ route('student-skipping-grade.reviewer.reassign',['skipping'=>$record,'step'=>$step->id]) }}" class="skipping-reassignment-form" data-skipping-form data-confirm="Replace {{ $previousPerson['name'] }} for this unfinished step?" novalidate>
                    @csrf<input type="hidden" name="revision" value="{{ $step->revision }}">@if($member)<input type="hidden" name="member_id" value="{{ $member->id }}">@endif<strong>{{ $step->sequence }}. {{ $step->title }} — {{ $previousPerson['name'] }}</strong>
                    <small class="d-block text-secondary">{{ implode(', ',$previousPerson['grades']??['All Grades']) }}. The replacement retains this saved grade assignment.</small>
                    <div class="skipping-form-grid mt-2"><div class="premium-form-field"><label class="form-label" for="replacement{{ $fieldId }}">Replacement Reviewer</label><select id="replacement{{ $fieldId }}" name="reviewer_id" class="form-select" data-skipping-searchable="Replacement Reviewer"><option value="">Select User</option>
                        @foreach($reviewerOptions as $option)
                            @if(app(\App\Services\SkippingGradeWorkflow::class)->reviewerIsEligible($option,$step->scope==='campus'?$record->campus_id:null))<option value="{{ $option->id }}">{{ $option->name }}</option>@endif
                        @endforeach
                    </select></div><div class="premium-form-field"><label class="form-label" for="reason{{ $fieldId }}">Reason for Reassignment</label><input id="reason{{ $fieldId }}" name="reason" class="form-control" maxlength="1500"></div></div><button class="btn btn-outline-primary mt-2" type="submit">Reassign Reviewer</button>
                </form>
                @endforeach
            @endforeach
        </details>
    @endif
    @if($record->approvalSteps->contains(fn($step)=>$step->reassignments->isNotEmpty()))
        <details class="mt-3"><summary class="fw-semibold">Reviewer Change History</summary>
            @foreach($record->approvalSteps as $step)
                @foreach($step->reassignments as $change)
                    <div class="skipping-reassignment-history mt-2"><strong>{{ $step->title }}</strong>: {{ $change->previous_reviewer['name'] }} → {{ $change->replacement_reviewer['name'] }}<div class="small text-secondary">Changed by {{ $change->changed_by_name }} · {{ $change->created_at->format('d-M-Y H:i') }}</div><p class="mb-0 skipping-prewrap">{{ $change->reason }}</p></div>
                @endforeach
            @endforeach
        </details>
    @endif
</div></div>
