@php($rowMembers=app(\App\Services\SkippingGradeWorkflow::class)->membersOf($rowStep) ?: [[]])
<div class="skipping-flow-row" data-flow-row>
    <input type="hidden" name="{{ $rowKey }}[{{ $rowIndex }}][title]" value="{{ $rowStep['title']??'' }}" data-flow-field="title">
    <div class="skipping-flow-step-fields">
        <div class="skipping-flow-step-header">
            <span class="skipping-flow-order" data-flow-order>{{ is_numeric($rowIndex)?$rowIndex+1:'' }}</span>
        </div>
        <div class="skipping-flow-members" data-flow-members>
            @foreach($rowMembers as $memberIndex=>$member)
                @include('student-skipping-grade._flow-reviewer-row',['memberIndex'=>$memberIndex,'member'=>$member])
            @endforeach
        </div>
        <div class="skipping-flow-row-actions"><button type="button" class="btn btn-icon btn-outline-secondary" data-flow-up aria-label="Move step up"><i class="ti ti-arrow-up"></i></button><button type="button" class="btn btn-icon btn-outline-secondary" data-flow-down aria-label="Move step down"><i class="ti ti-arrow-down"></i></button><button type="button" class="btn btn-icon btn-outline-danger" data-flow-remove aria-label="Remove approval step"><i class="ti ti-x"></i></button></div>
    </div>
    <template data-flow-member-template>@include('student-skipping-grade._flow-reviewer-row',['memberIndex'=>'new','member'=>[],'memberPosition'=>''])</template>
    <div class="skipping-flow-step-footer mt-2">
        <button class="btn btn-sm btn-outline-primary" type="button" data-flow-add-member><i class="ti ti-plus me-1"></i>Add Reviewer to This Step</button>
    </div>
    <small class="text-secondary d-block mt-2">One eligible reviewer's approval completes this step.</small>
</div>
