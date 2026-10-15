<div class="signature-staff-picker scholarship-print-field" data-signature-role="{{ $role }}">
    <label class="form-label" for="{{ $inputId }}">Staff member <span class="text-danger">*</span></label>
    <input class="form-control" id="{{ $inputId }}" name="{{ $role }}_by_name" maxlength="255" required autocomplete="off" placeholder="Select staff" aria-label="{{ $label }} staff member" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $inputId }}Options" data-search-normalized="1" data-staff-input>
    <input type="hidden" name="{{ $role }}_staff_id">
    <div class="signature-staff-results" id="{{ $inputId }}Options" role="listbox" aria-label="{{ $label }} staff" data-staff-results hidden></div>
</div>
<div class="signature-staff-position text-secondary" data-staff-position="{{ $role }}" role="status">Position: —</div>
