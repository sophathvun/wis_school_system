<div class="alert alert-danger d-none" role="alert" data-form-alert></div>
<div class="row g-3">
    <input type="hidden" name="academic_year_id" id="academic_year_id">
    <div class="col-12">
        <label class="form-label required">Period Type</label>
        <select class="form-select" name="period_type" id="period_type">
            <option value="regular">Regular Academic Year</option>
            <option value="summer">Summer School</option>
        </select>
        <span class="invalid-feedback" data-error-for="period_type"></span>
    </div>
    <div class="col-12 d-none" id="summerPeriodFields">
        <div class="row g-3">
            <div class="col-md-6 academic-year-date-field">
                <label class="form-label required">Summer Start Date</label>
                <input type="date" class="form-control" name="start_date" id="start_date">
                <span class="invalid-feedback" data-error-for="start_date"></span>
            </div>
            <div class="col-md-6 academic-year-date-field">
                <label class="form-label required">Summer End Date</label>
                <input type="date" class="form-control" name="end_date" id="end_date">
                <span class="invalid-feedback" data-error-for="end_date"></span>
            </div>
        </div>
        <div class="form-text">Enter the Summer School dates according to your school schedule.</div>
    </div>
    <div class="col-12 academic-year-input-field">
         <label class="form-label required">Academic Year</label>
        <div class="input-icon">
                <span class="input-icon-addon">
                    <i class="ti ti-calendar icon"></i>
                </span>
            <input type="text" class="form-control" name="academic_year" id="academic_year" placeholder="Enter academic year" required>
            <span class="invalid-feedback" data-error-for="academic_year"></span>
        </div>
    </div>
    <div class="col-12 academic-year-input-field">
        <label class="form-label">AY Code</label>
        <div class="input-icon">
                <span class="input-icon-addon">
                    <i class="ti ti-hash icon"></i>
                </span>
            <input type="text" class="form-control" name="ay_code" id="ay_code" placeholder="Enter AY code">
            <span class="invalid-feedback" data-error-for="ay_code"></span>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Description</label>
        <textarea class="form-control" name="description" id="description" placeholder="Enter description"></textarea>
        <span class="invalid-feedback" data-error-for="description"></span>
    </div>
    <div class="col-12">
        <label class="form-label">Status</label>
        <select class="form-select" name="status" id="status">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
    </div>
</div>
