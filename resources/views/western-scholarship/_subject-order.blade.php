@if($editingExam)
@php
    $examSubjectOrder=app(\App\Support\ScholarshipSubjectOrder::class)->state($editingExam);
    $examSubjectOrder['schedules']=app(\App\Support\ScholarshipSubjectSchedule::class)->state($editingExam);
    $examSubjectOrder['schedule_readonly']=(bool)($editingExam->published_at||$editingExam->isHistorical());
    $examSubjectOrder['subjects']=$editingExam->subjects->map->only(['id','name','grade_from','grade_to'])->values()->all();
@endphp
<section class="scholarship-subject-order mt-3 mb-4" data-subject-order data-url="{{ route('western-scholarship.exams.subject-order',$editingExam) }}" data-version="{{ $editingExam->version }}">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2"><h4 class="mb-0"><i class="ti ti-list-numbers me-1" aria-hidden="true"></i>Subject Exam Order & Schedule</h4><button type="button" class="btn btn-sm btn-outline-primary" data-edit-subject-order><i class="ti ti-edit me-1" aria-hidden="true"></i>Edit Order & Schedule</button></div>
    <p class="small text-secondary mt-2 mb-3">Arrange subjects by exam session. Grades follow their grade band's default order unless customized below. Attendance reports use each grade's order. Set default subject dates and times below, then expand a grade to customize its times independently.</p>
    <p class="small text-secondary" data-order-settings-note hidden>Save Exam Settings before editing the exam order.</p>
    <div class="alert alert-danger" role="alert" data-order-error hidden></div>
    <fieldset data-order-fields disabled>
        <div class="scholarship-order-bands">
            @foreach(['primary'=>'Grades 1–6','secondary'=>'Grades 7–12'] as $band=>$label)
            <section class="scholarship-order-band"><h5>{{ $label }} <span class="badge bg-primary-lt">Default Order</span></h5><ol class="scholarship-order-list" data-order-band="{{ $band }}" aria-label="{{ $label }} default exam order"></ol></section>
            @endforeach
        </div>
        <details class="scholarship-grade-orders mt-3"><summary><i class="ti ti-adjustments-horizontal me-1" aria-hidden="true"></i>Customize by Grade <span class="small text-secondary">Expand to customize</span></summary><div class="scholarship-order-grades" data-order-grades></div></details>
    </fieldset>
    <div class="d-flex gap-2 justify-content-end mt-3" data-order-actions hidden><button type="button" class="btn btn-outline-secondary" data-cancel-subject-order>Cancel</button><button type="button" class="btn btn-primary" data-save-subject-order><i class="ti ti-device-floppy me-1" aria-hidden="true"></i>Save Order & Schedule</button></div>
    <p class="small text-secondary mb-0 mt-2" role="status" aria-live="polite" data-order-status></p>
    <script type="application/json" data-subject-order-state>@json($examSubjectOrder)</script>
</section>
@else
<p class="small text-secondary mt-2 mb-4">Move subject rows up or down to set the default exam order. Save Exam Settings to customize the order for individual grades.</p>
@endif
