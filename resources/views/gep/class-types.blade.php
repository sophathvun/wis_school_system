@extends('layouts.app')
@section('title','GEP Class Types')
@section('content')
<div id="gep-class-types" data-url="{{ url('/gep/settings/class-types') }}" data-permissions='@json($permissions)'>
    <div class="card shadow-sm">
        <div class="card-header gep-settings-header d-flex flex-wrap gap-3"><h1 class="card-title text-white mb-0"><i class="ti ti-category me-2"></i>GEP Class Types</h1>@if($permissions['create'])<button type="button" id="gep-class-type-add" class="btn btn-light ms-auto"><i class="ti ti-plus me-1"></i>Add New</button>@endif</div>
        <div class="card-body border-bottom py-3"><div class="input-icon" style="max-width:360px"><span class="input-icon-addon"><i class="ti ti-search"></i></span><input id="gep-class-type-search" type="search" class="form-control" placeholder="Search class type" aria-label="Search GEP class types"></div></div>
        <div id="gep-class-type-list-error" class="alert alert-danger d-none m-3" role="alert"></div>
        <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th class="text-center">No.</th><th>Class Type</th><th>Order</th><th class="text-center">Status</th><th class="text-end">Actions</th></tr></thead><tbody id="gep-class-type-rows"><tr><td colspan="5" class="text-center py-4">Loading GEP class types…</td></tr></tbody></table></div>
        <div class="card-footer"><select id="gep-class-type-per-page" class="d-none" aria-hidden="true"><option>10</option><option>25</option><option>50</option><option>100</option></select><div id="gep-class-type-pagination"></div></div>
    </div>
    <div class="modal modal-blur fade" id="gep-class-type-modal" tabindex="-1" aria-labelledby="gep-class-type-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
        <form id="gep-class-type-form" novalidate>
            <div class="modal-header gep-settings-header"><h2 class="modal-title text-white" id="gep-class-type-title">Add GEP Class Type</h2><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div id="gep-class-type-error" class="alert alert-danger d-none" role="alert"></div>
                <div class="form-floating mb-3"><input id="gep-class-type-name" name="type_name" class="form-control" placeholder="Class Type" maxlength="100" required><label for="gep-class-type-name">Class Type <span class="text-danger">*</span></label><div class="invalid-feedback" data-error="type_name"></div></div>
                <div class="form-floating"><input id="gep-class-type-order" name="sort_order" type="number" min="0" max="2147483647" step="1" value="0" class="form-control" placeholder="Order" required><label for="gep-class-type-order">Order</label><div class="invalid-feedback" data-error="sort_order"></div></div>
                <div class="form-text mt-2">Lower order numbers appear first. For example: Full-Time or Part-Time. New class types are On by default.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button><button type="submit" id="gep-class-type-save" class="btn btn-primary">Save</button></div>
        </form>
    </div></div></div>
</div>
@vite('resources/js/gepClassTypes.js')
@endsection
