@extends('layouts.app')
@section('title','GEP Group')
@section('content')
<div id="gep-groups" data-url="{{ url('/gep/settings/groups') }}" data-permissions='@json($permissions)'>
    <div class="card shadow-sm">
        <div class="card-header gep-settings-header d-flex flex-wrap gap-3"><h1 class="card-title text-white mb-0"><i class="ti ti-users-group me-2"></i>GEP Group</h1>@if($permissions['create'])<button type="button" id="gep-group-add" class="btn btn-light ms-auto"><i class="ti ti-plus me-1"></i>Add New</button>@endif</div>
        <div class="card-body border-bottom py-3"><div class="input-icon" style="max-width:360px"><span class="input-icon-addon"><i class="ti ti-search"></i></span><input id="gep-group-search" type="search" class="form-control" placeholder="Search session or group" aria-label="Search GEP groups"></div></div>
        <div id="gep-group-list-error" class="alert alert-danger d-none m-3" role="alert"></div>
        <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th class="text-center">No.</th><th>Session</th><th>Group</th><th>Order</th><th class="text-center">Status</th><th class="text-end">Actions</th></tr></thead><tbody id="gep-group-rows"><tr><td colspan="6" class="text-center py-4">Loading GEP groups…</td></tr></tbody></table></div>
        <div class="card-footer"><select id="gep-group-per-page" class="d-none" aria-hidden="true"><option>10</option><option>25</option><option>50</option><option>100</option></select><div id="gep-group-pagination"></div></div>
    </div>
    <div class="modal modal-blur fade" id="gep-group-modal" tabindex="-1" aria-labelledby="gep-group-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
        <form id="gep-group-form" novalidate>
            <div class="modal-header gep-settings-header"><h2 class="modal-title text-white" id="gep-group-title">Add GEP Group</h2><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div id="gep-group-error" class="alert alert-danger d-none" role="alert"></div>
                <div class="form-floating mb-3"><select id="gep-group-name" name="session_name" class="form-select" required><option value="">Select Session</option><option>Morning</option><option>Afternoon</option><option>Evening</option></select><label for="gep-group-name">Session <span class="text-danger">*</span></label><div class="invalid-feedback" data-error="session_name"></div></div>
                <div class="form-floating mb-3"><input id="gep-group-code" class="form-control" placeholder="Group" readonly><label for="gep-group-code">Group</label></div>
                <div class="form-floating"><input id="gep-group-order" name="sort_order" type="number" min="0" max="2147483647" step="1" value="0" class="form-control" placeholder="Order" required><label for="gep-group-order">Order</label><div class="invalid-feedback" data-error="sort_order"></div></div>
                <div class="form-text mt-2">Lower order numbers appear first. Morning: M, Afternoon: A, Evening: E. New groups are On by default.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button><button type="submit" id="gep-group-save" class="btn btn-primary">Save</button></div>
        </form>
    </div></div></div>
</div>
@vite('resources/js/gepGroups.js')
@endsection
