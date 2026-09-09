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
        <label class="form-label">Google Map Link</label>
        <input type="url" class="form-control" name="google_map_url" id="google_map_url"
            placeholder="Paste Google Maps share link">
        <span class="invalid-feedback" data-error-for="google_map_url"></span>
    </div>
    <div class="col-12">
        <h4 class="mb-3 school-profile-form-subheading">School Address</h4>
        <div class="school-profile-address-grid">
            <div class="premium-floating-field"><label class="form-label">Country</label><select id="address_country_id"
                    name="address_country_id" class="form-select"></select></div>
            <div class="premium-floating-field"><label class="form-label">Province/City</label><select
                    id="address_province_id" name="address_province_id" class="form-select"></select></div>
            <div class="premium-floating-field"><label class="form-label">District/Khan</label><select
                    id="address_district_id" name="address_district_id" class="form-select"></select></div>
            <div class="premium-floating-field"><label class="form-label">Commune</label><select id="address_commune_id"
                    name="address_commune_id" class="form-select"></select></div>
            <div class="premium-floating-field"><label class="form-label">Village</label><select id="address_village_id"
                    name="address_village_id" class="form-select"></select></div>
        </div>
    </div>
    <div class="col-md-3">
        <label class="form-label">House No. English</label>
        <input class="form-control" name="address_house_no_en" id="address_house_no_en">
        <span class="invalid-feedback" data-error-for="address_house_no_en"></span>
    </div>
    <div class="col-md-3">
        <label class="form-label">House No. Khmer</label>
        <input class="form-control school-profile-khmer" name="address_house_no_kh" id="address_house_no_kh">
        <span class="invalid-feedback" data-error-for="address_house_no_kh"></span>
    </div>
    <div class="col-md-3">
        <label class="form-label">Street No. English</label>
        <input class="form-control" name="address_street_en" id="address_street_en">
        <span class="invalid-feedback" data-error-for="address_street_en"></span>
    </div>
    <div class="col-md-3">
        <label class="form-label">Street No. Khmer</label>
        <input class="form-control school-profile-khmer" name="address_street_kh" id="address_street_kh">
        <span class="invalid-feedback" data-error-for="address_street_kh"></span>
    </div>
    <div class="col-12">
        <label class="form-label">Address (English)</label>
        <textarea class="form-control" name="address_en" id="address_en" rows="1" readonly></textarea>
        <input type="hidden" name="address" id="address">
        <span class="invalid-feedback" data-error-for="address_en"></span>
    </div>
    <div class="col-12">
        <label class="form-label">Address (Khmer)</label>
        <textarea class="form-control school-profile-khmer" name="address_kh" id="address_kh" rows="1" readonly></textarea>
        <span class="invalid-feedback" data-error-for="address_kh"></span>
    </div>
    <div class="col-12">
        <label class="form-label">Description</label>
        <textarea class="form-control" name="description" id="description" placeholder="Enter description"></textarea>
        <span class="invalid-feedback" data-error-for="description"></span>
    </div>
    <div class="col-12">
        <label class="form-label">Status</label>
        <input type="hidden" name="status" id="status" value="1">
        <button type="button" class="status-toggle is-active" id="schoolProfileStatusToggle"
            data-status="1" aria-pressed="true">
            <span class="status-toggle-label">ON</span><span class="status-toggle-knob"></span>
        </button>
    </div>
</div>
