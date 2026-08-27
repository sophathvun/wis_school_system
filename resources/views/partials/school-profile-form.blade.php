<div class="alert alert-danger d-none" role="alert" data-form-alert></div>
<div class="row g-3">
    <input type="hidden" name="school_id" id="school_id">
    <div class="col-12">
        <label class="form-label">School Logo</label>
        <div class="logo-dropzone" id="logoDropzone" tabindex="0">
            <div class="logo-dropzone-copy">
                <i class="ti ti-cloud-upload logo-dropzone-icon"></i>
                <div><strong>Drag and drop your logo here</strong></div>
                <div class="text-secondary">or click to choose a file</div>
                <input type="file" class="d-none" name="logo" id="logo"
                    accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="d-none logo-preview-wrap" id="logoPreviewContainer">
                <img id="logoPreview" src="#" alt="School logo preview" class="logo-preview-image">
            </div>
        </div>
        <small class="form-hint">JPG, PNG, or WEBP. Maximum size: 2 MB.</small>
        <span class="invalid-feedback" data-error-for="logo"></span>
    </div>
    <div class="col-md-6">
        <label class="form-label required">School Name (English)</label>
        <input type="text" class="form-control" name="school_name_en" id="school_name_en"
            placeholder="Enter school name in English" required>
        <span class="invalid-feedback" data-error-for="school_name_en"></span>
    </div>
    <div class="col-md-6">
        <label class="form-label required">School Name (Khmer)</label>
        <input type="text" class="form-control school-profile-khmer" name="school_name_kh" id="school_name_kh"
            placeholder="Enter school name in Khmer" required>
        <span class="invalid-feedback" data-error-for="school_name_kh"></span>
    </div>
    <div class="col-md-6">
        <label class="form-label required">Campus Name (English)</label>
        <input type="text" class="form-control" name="campus_name_en" id="campus_name_en"
            placeholder="Enter campus name in English" required>
        <span class="invalid-feedback" data-error-for="campus_name_en"></span>
    </div>
    <div class="col-md-6">
        <label class="form-label required">Campus Name (Khmer)</label>
        <input type="text" class="form-control school-profile-khmer" name="campus_name_kh" id="campus_name_kh"
            placeholder="Enter campus name in Khmer" required>
        <span class="invalid-feedback" data-error-for="campus_name_kh"></span>
    </div>
    <div class="col-12">
        <label class="form-label">Address</label>
        <textarea class="form-control" name="address" id="address" rows="2" placeholder="Enter address"></textarea>
        <span class="invalid-feedback" data-error-for="address"></span>
    </div>
    <div class="col-12">
        <label class="form-label">Google Map Link</label>
        <input type="url" class="form-control" name="google_map_url" id="google_map_url"
            placeholder="Paste Google Maps share link">
        <span class="invalid-feedback" data-error-for="google_map_url"></span>
    </div>
    <div class="col-md-6 school-profile-phone-field" style="position:relative;">
        <label class="form-label"
            style="position:absolute;z-index:5;top:.42rem;left:1rem;margin:0;padding:0 .45rem;background:var(--tblr-bg-surface,#fff);color:#5b4bd1;font-size:.72rem;font-weight:700;line-height:1.1;pointer-events:none;">Phone Number</label>
        <div class="phone-input-group">
            <input type="text" class="form-control" id="phone_number" placeholder=" ">
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
