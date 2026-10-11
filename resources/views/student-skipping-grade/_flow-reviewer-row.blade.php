@php
    $memberPrefix=$rowKey.'['.$rowIndex.'][reviewers]['.$memberIndex.']';
    $memberId=$rowKey.'-'.$rowIndex.'-member-'.$memberIndex;
    $assigned=($member['grade_scope']??'all')==='selected';
    $position=$memberPosition??($member['title']??$rowStep['title']??'');
@endphp
<div class="skipping-flow-member" data-flow-member>
    <div class="premium-form-field {{ $position?'has-value':'' }}"><label class="form-label" for="{{ $memberId }}-title">Position / Role</label><select id="{{ $memberId }}-title" name="{{ $memberPrefix }}[title]" class="form-select" data-member-field="title">
        <option value="">Select Position / Role</option>
        @if($position && !$reviewerPositions->contains($position))<option value="{{ $position }}" selected>{{ $position }}</option>@endif
        @foreach($reviewerPositions as $option)<option value="{{ $option }}" @selected($position===$option)>{{ $option }}</option>@endforeach
    </select></div>
    <div class="premium-form-field {{ !empty($member['user_id'])?'has-value':'' }}"><label class="form-label" for="{{ $memberId }}-user_id">Reviewer</label><select id="{{ $memberId }}-user_id" name="{{ $memberPrefix }}[user_id]" class="form-select" data-member-field="user_id">
        <option value="">Select User</option>@foreach($rowUsers as $user)<option value="{{ $user->id }}" @selected(($member['user_id']??null)===$user->id)>{{ $user->name }}</option>@endforeach
    </select></div>
    <div class="premium-form-field has-value"><label class="form-label" for="{{ $memberId }}-grade_scope">Current Grade Access</label><select id="{{ $memberId }}-grade_scope" name="{{ $memberPrefix }}[grade_scope]" class="form-select" data-member-field="grade_scope">
        <option value="all" @selected(!$assigned)>All Grades</option><option value="selected" @selected($assigned)>Assigned Grades</option>
    </select></div>
    <button type="button" class="btn btn-icon btn-outline-danger" data-flow-remove-member aria-label="Remove reviewer"><i class="ti ti-x"></i></button>
    <details class="skipping-flow-grade-selection" data-member-grades @if(!$assigned) hidden @endif>
        <summary class="fw-semibold">Select Current Grades <span class="text-secondary small" data-member-grade-summary></span></summary>
        <div class="skipping-flow-range mt-2">
            <div class="premium-form-field"><label class="form-label" for="{{ $memberId }}-from">From Grade</label><select id="{{ $memberId }}-from" class="form-select" data-member-range="from"><option value="">Select Grade</option>@foreach($reviewerGrades as $grade)<option value="{{ $grade->id }}">{{ $grade->grade_short_name ?: $grade->grade }}</option>@endforeach</select></div>
            <div class="premium-form-field"><label class="form-label" for="{{ $memberId }}-to">To Grade</label><select id="{{ $memberId }}-to" class="form-select" data-member-range="to"><option value="">Select Grade</option>@foreach($reviewerGrades as $grade)<option value="{{ $grade->id }}">{{ $grade->grade_short_name ?: $grade->grade }}</option>@endforeach</select></div>
            <button type="button" class="btn btn-outline-secondary" data-member-apply-range>Apply Range</button>
        </div>
        <div class="skipping-flow-grade-options mt-3">
            @foreach($reviewerGrades as $grade)
                <label class="form-check"><input class="form-check-input" type="checkbox" name="{{ $memberPrefix }}[grade_ids][]" value="{{ $grade->id }}" data-member-field="grade_ids" @checked(in_array($grade->id,$member['grade_ids']??[])) @disabled(!$assigned)><span class="form-check-label">{{ $grade->grade_short_name ?: $grade->grade }}</span></label>
            @endforeach
        </div>
    </details>
</div>
