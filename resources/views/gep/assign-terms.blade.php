@extends('layouts.app')
@section('title','Assign Terms')
@section('content')
<div id="gep-term-assignments" data-url="{{ url('/gep/settings/assign-terms') }}" data-permissions='@json($permissions)'>
    <div class="card shadow-sm">
        <div class="card-header gep-settings-header d-flex flex-wrap gap-3"><h1 class="card-title text-white mb-0"><i class="ti ti-calendar-plus me-2"></i>Assign Terms</h1>@if($permissions['create'])<button type="button" id="assignment-add" class="btn btn-light ms-auto"><i class="ti ti-plus me-1"></i>Add New</button>@endif</div>
        <div class="card-body border-bottom">
            <div class="row g-3 assignment-filter-row">
                <div class="col-md-4"><div class="form-floating assignment-filter-field assignment-year-filter-field"><select id="assignment-year-filter" class="form-select" aria-label="Filter academic year"><option value="">All Academic Years</option></select><label for="assignment-year-filter">GEP Academic Year</label></div></div>
                <div class="col-md-5"><div class="form-floating assignment-filter-field assignment-search-field"><i class="ti ti-search assignment-search-icon" aria-hidden="true"></i><input type="search" id="assignment-search" class="form-control" placeholder="Search year or term"><label for="assignment-search">Search year or term</label></div></div>
            </div>
            <div id="assignment-summary" class="alert alert-info mt-3 mb-0" aria-live="polite">Loading term schedule…</div>
            <p class="text-secondary small mt-2 mb-0">Dates provide reminders. Staff start and finish terms manually. Student enrollments stay unchanged.</p>
        </div>
        <div id="assignment-list-error" class="alert alert-danger m-3 d-none" role="alert"></div>
        <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>No.</th><th>Academic Year</th><th>Term</th><th>Order</th><th>Start Date</th><th>End Date</th><th>Status</th><th>Schedule</th><th class="text-end">Actions</th></tr></thead><tbody id="assignment-rows"><tr><td colspan="9" class="text-center py-4">Loading term assignments…</td></tr></tbody></table></div>
        <div class="card-footer"><select id="assignment-per-page" class="d-none" aria-hidden="true"><option>10</option><option>25</option><option>50</option><option>100</option></select><div id="assignment-pagination"></div></div>
    </div>
    <div class="modal modal-blur fade" id="assignment-modal" tabindex="-1" aria-labelledby="assignment-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" style="max-width:460px"><div class="modal-content"><form id="assignment-form" novalidate>
        <div class="modal-header gep-settings-header"><h2 id="assignment-title" class="modal-title text-white">Assign GEP Term</h2><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div id="assignment-error" class="alert alert-danger d-none" role="alert"></div>
            <div class="form-floating mb-3"><select id="assignment-year" name="academic_year_id" class="form-select" required></select><label for="assignment-year">Academic Year *</label><div class="invalid-feedback" data-error="academic_year_id"></div></div>
            <div class="form-floating mb-3"><select id="assignment-term" name="term_id" class="form-select" required></select><label for="assignment-term">Term *</label><div class="invalid-feedback" data-error="term_id"></div></div>
            <div class="form-floating mb-3"><input id="assignment-order" name="sort_order" type="number" min="1" max="2147483647" step="1" class="form-control" placeholder="Order" required><label for="assignment-order">Order *</label><div class="invalid-feedback" data-error="sort_order"></div></div>
            <div class="row g-3"><div class="col-6"><div class="form-floating"><input id="assignment-start" name="start_date" type="date" class="form-control" required><label for="assignment-start">Start Date *</label><div class="invalid-feedback" data-error="start_date"></div></div></div><div class="col-6"><div class="form-floating"><input id="assignment-end" name="end_date" type="date" class="form-control" required><label for="assignment-end">End Date *</label><div class="invalid-feedback" data-error="end_date"></div></div></div></div>
            <div class="form-text mt-3">New assignments are Pending. Lower order numbers start first. Year, term and order are locked after starting.</div>
        </div><div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button><button type="submit" id="assignment-save" class="btn btn-primary">Save</button></div>
    </form></div></div></div>
</div>
@vite('resources/js/gepTermAssignments.js')
@endsection
