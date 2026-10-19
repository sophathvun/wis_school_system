<form method="POST" action="{{ route('western-scholarship.exams.examination-type',$editingExam) }}" class="scholarship-examination-type mb-4" data-examination-type>
    @csrf
    <input type="hidden" name="version" value="{{ $editingExam->version }}">
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <div class="scholarship-field flex-fill"><label for="scholarshipExaminationType">Examination Type</label><input id="scholarshipExaminationType" name="examination_type" class="form-control" required maxlength="120" value="{{ $editingExam->examinationTitle() }}" readonly><div class="invalid-feedback" data-examination-type-error></div></div>
        <button type="button" class="btn btn-outline-primary" data-edit-examination-type><i class="ti ti-edit me-1" aria-hidden="true"></i>Edit Title</button>
        <div class="d-flex gap-2" data-examination-type-actions hidden><button type="button" class="btn btn-outline-secondary" data-cancel-examination-type>Cancel</button><button type="submit" class="btn btn-primary">Save Title</button></div>
    </div>
    <p class="small text-secondary mt-2 mb-0">Report headings can be updated at any time.</p>
</form>
