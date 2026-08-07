<div class="alert alert-danger d-none" role="alert" data-form-alert></div>
<div class="row g-3">
    <input type="hidden" name="grade_id" id="grade_id">
    <div class="col-md-6">
        <label class="form-label required">Grade</label>
        <input type="text" class="form-control" name="grade" id="grade" placeholder="Enter grade" required>
        <span class="invalid-feedback" data-error-for="grade"></span>
    </div>
    <div class="col-md-6">
        <label class="form-label required">Short Name</label>
        <input type="text" class="form-control" name="grade_short_name" id="grade_short_name" placeholder="Enter short name" required>
        <span class="invalid-feedback" data-error-for="grade_short_name"></span>
    </div>
    <div class="col-md-6">
        <label class="form-label">Order</label>
        <input type="text" class="form-control" name="grade_order" id="grade_order" placeholder="Enter order">
        <span class="invalid-feedback" data-error-for="grade_order"></span>
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
