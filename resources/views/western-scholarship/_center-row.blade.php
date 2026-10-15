<div class="scholarship-center-row" data-exam-center-row>
    <input type="hidden" name="exam_centers[{{ $i }}][id]" value="{{ $center['id']??'' }}">
    <input type="hidden" name="exam_centers[{{ $i }}][key]" value="{{ $center['key'] }}" data-center-key>
    <div class="flex-fill"><label for="examCenter{{ $i }}" class="form-label">Exam Center Name <span class="text-danger">*</span></label><input id="examCenter{{ $i }}" name="exam_centers[{{ $i }}][name]" value="{{ $center['name'] }}" class="form-control @error('exam_centers.'.$i.'.name') is-invalid @enderror" maxlength="160" placeholder="e.g. BCH Exam Center" required data-center-name>@error('exam_centers.'.$i.'.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <button type="button" class="btn btn-outline-danger btn-icon" aria-label="Remove exam center" data-remove-center><i class="ti ti-trash"></i></button>
</div>
