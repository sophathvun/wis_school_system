<div class="alert alert-danger d-none" role="alert" data-form-alert></div>
<div class="row g-3">
    <input type="hidden" name="class_id" id="class_id">
    <div class="col-md-8">
        <label class="form-label required">Class Name</label>
        <input type="text" class="form-control" name="class_name" id="class_name" placeholder="Enter class name"
            required>
        <span class="invalid-feedback" data-error-for="class_name"></span>
    </div>
    <div class="col-md-4">
        <label class="form-label">Order</label>
        <input type="text" class="form-control" name="class_order" id="class_order" placeholder="Enter order">
        <span class="invalid-feedback" data-error-for="class_order"></span>
    </div>
    <div class="col-12">
        <label class="form-label">Status</label>
        <input type="hidden" name="status" id="status" value="1">
        <button type="button" class="status-toggle is-active" id="classStatusToggle" data-form-status-toggle="status" aria-pressed="true"><span class="status-toggle-label">ON</span><span class="status-toggle-knob"></span></button>
    </div>
</div>
