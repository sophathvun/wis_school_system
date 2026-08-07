<div class="alert alert-danger d-none" role="alert" data-form-alert></div>
<div class="row g-3">
    <input type="hidden" name="session_id" id="session_id">
    <div class="col-md-6">
        <label class="form-label required">Session Name</label>
        <input type="text" class="form-control" name="session_name" id="session_name" placeholder="Enter session name" required>
        <span class="invalid-feedback" data-error-for="session_name"></span>
    </div>
    <div class="col-md-6">
        <label class="form-label required">Group (Short Name)</label>
        <input type="text" class="form-control" name="session_short_name" id="session_short_name" placeholder="For example: A, M, III, 1, 2" required>
        <span class="invalid-feedback" data-error-for="session_short_name"></span>
    </div>
    <div class="col-md-6">
        <label class="form-label">Order</label>
        <input type="text" class="form-control" name="session_order" id="session_order" placeholder="Enter order">
        <span class="invalid-feedback" data-error-for="session_order"></span>
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
