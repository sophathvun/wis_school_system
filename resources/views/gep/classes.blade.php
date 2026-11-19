@extends('layouts.app')
@section('title','GEP Classes')
@section('content')
<div id="gep-classes" data-url="{{ url('/gep/settings/classes') }}" data-permissions='@json($permissions)'>
    <div class="card shadow-sm">
        <div class="card-header gep-settings-header d-flex flex-wrap gap-3"><h1 class="card-title text-white mb-0"><i class="ti ti-door me-2"></i>GEP Classes</h1>@if($permissions['create'])<button type="button" id="gep-class-add" class="btn btn-light ms-auto"><i class="ti ti-plus me-1"></i>Add New</button>@endif</div>
        <div class="card-body border-bottom py-3"><div class="input-icon" style="max-width:360px"><span class="input-icon-addon"><i class="ti ti-search"></i></span><input id="gep-class-search" type="search" class="form-control" placeholder="Search class name" aria-label="Search GEP classes"></div></div>
        <div id="gep-class-list-error" class="alert alert-danger d-none m-3" role="alert"></div>
        <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th class="text-center">No.</th><th>Class Name</th><th>Order</th><th class="text-center">Status</th><th class="text-end">Actions</th></tr></thead><tbody id="gep-class-rows"><tr><td colspan="5" class="text-center py-4">Loading GEP classes…</td></tr></tbody></table></div>
        <div class="card-footer"><select id="gep-class-per-page" class="d-none" aria-hidden="true"><option>10</option><option>25</option><option>50</option><option>100</option></select><div id="gep-class-pagination"></div></div>
    </div>
    <div class="modal modal-blur fade" id="gep-class-modal" tabindex="-1" aria-labelledby="gep-class-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
        <form id="gep-class-form" novalidate>
            <div class="modal-header gep-settings-header"><h2 class="modal-title text-white" id="gep-class-title">Add GEP Class</h2><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div id="gep-class-error" class="alert alert-danger d-none" role="alert"></div>
                <div class="form-floating mb-3"><input id="gep-class-name" name="class_name" class="form-control" placeholder="Class Name" maxlength="100" required><label for="gep-class-name">Class Name <span class="text-danger">*</span></label><div class="invalid-feedback" data-error="class_name"></div></div>
                <div class="form-floating"><input id="gep-class-order" name="sort_order" type="number" min="0" max="2147483647" step="1" value="0" class="form-control" placeholder="Order" required><label for="gep-class-order">Order</label><div class="invalid-feedback" data-error="sort_order"></div></div>
                <div class="form-text mt-2">Lower order numbers appear first. For example: A, B, C, D or E. New classes are On by default.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button><button type="submit" id="gep-class-save" class="btn btn-primary">Save</button></div>
        </form>
    </div></div></div>
</div>
@vite('resources/js/gepClasses.js')
@endsection
