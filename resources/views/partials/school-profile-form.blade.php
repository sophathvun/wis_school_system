<div class="alert alert-danger d-none" role="alert" data-form-alert></div>
<div class="row g-3">
    <input type="hidden" name="school_id" id="school_id">
    <div class="col-12">
        <label class="form-label">School Logo</label>
        <div class="logo-upload-row" style="display:flex;flex-wrap:nowrap;align-items:stretch;gap:1rem;">
            <div class="logo-dropzone" id="logoDropzone" tabindex="0" style="flex:1 1 auto;">
                <i class="ti ti-cloud-upload logo-dropzone-icon"></i>
                <div><strong>Drag and drop your logo here</strong></div>
                <div class="text-secondary">or click to choose a file</div>
                <input type="file" class="d-none" name="logo" id="logo" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="d-none" id="logoPreviewContainer" style="flex:0 0 96px;width:96px;">
                <img id="logoPreview" src="#" alt="School logo preview" class="logo-preview-image" style="display:block;width:96px;height:96px;max-width:96px;max-height:96px;object-fit:contain;border:1px solid var(--tblr-border-color);border-radius:.5rem;background:var(--tblr-bg-surface);">
            </div>
        </div>
        <small class="form-hint">JPG, PNG, or WEBP. Maximum size: 2 MB.</small>
        <span class="invalid-feedback" data-error-for="logo"></span>
    </div>
    <div class="col-md-6">
        <label class="form-label required">School Name (English)</label>
        <input type="text" class="form-control" name="school_name_en" id="school_name_en" placeholder="Enter school name in English" required>
        <span class="invalid-feedback" data-error-for="school_name_en"></span>
    </div>
    <div class="col-md-6">
        <label class="form-label required">School Name (Khmer)</label>
        <input type="text" class="form-control school-profile-khmer" name="school_name_kh" id="school_name_kh" placeholder="Enter school name in Khmer" required>
        <span class="invalid-feedback" data-error-for="school_name_kh"></span>
    </div>
    <div class="col-md-6">
        <label class="form-label required">Campus Name (English)</label>
        <input type="text" class="form-control" name="campus_name_en" id="campus_name_en" placeholder="Enter campus name in English" required>
        <span class="invalid-feedback" data-error-for="campus_name_en"></span>
    </div>
    <div class="col-md-6">
        <label class="form-label required">Campus Name (Khmer)</label>
        <input type="text" class="form-control school-profile-khmer" name="campus_name_kh" id="campus_name_kh" placeholder="Enter campus name in Khmer" required>
        <span class="invalid-feedback" data-error-for="campus_name_kh"></span>
    </div>
    <div class="col-12">
        <label class="form-label">Address</label>
        <textarea class="form-control" name="address" id="address" rows="2" placeholder="Enter address"></textarea>
        <span class="invalid-feedback" data-error-for="address"></span>
    </div>
    <div class="col-md-6">
        <label class="form-label">Phone</label>
        <div class="phone-input-group">
            <input type="text" class="form-control" id="phone_number" placeholder="Enter phone number">
            <input type="hidden" name="phone" id="phone">
        </div>
        <span class="invalid-feedback" data-error-for="phone"></span>
    </div>
    <div class="col-md-6">
        <label class="form-label">Status</label>
        <select class="form-select" name="status" id="status">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">Description</label>
        <textarea class="form-control" name="description" id="description" placeholder="Enter description"></textarea>
        <span class="invalid-feedback" data-error-for="description"></span>
    </div>
</div>
