@extends('layouts.app')
@section('title','GEP Levels')
@section('content')
<div id="gep-levels" data-url="{{ url('/gep/settings/levels') }}" data-permissions='@json($permissions)'>
    <div class="card shadow-sm">
        <div class="card-header gep-settings-header d-flex flex-wrap gap-3"><h1 class="card-title text-white mb-0"><i class="ti ti-stairs-up me-2"></i>GEP Levels</h1>@if($permissions['create'])<button type="button" id="gep-level-add" class="btn btn-light ms-auto"><i class="ti ti-plus me-1"></i>Add New</button>@endif</div>
        <div class="card-body border-bottom py-3"><div class="input-icon" style="max-width:360px"><span class="input-icon-addon"><i class="ti ti-search"></i></span><input id="gep-level-search" type="search" class="form-control" placeholder="Search type, level, abbreviation or program" aria-label="Search GEP levels"></div></div>
        <div id="gep-level-list-error" class="alert alert-danger d-none m-3" role="alert"></div>
        <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th class="text-center">No.</th><th>Type</th><th>Level Name</th><th>Abbreviation</th><th>Program</th><th>Order</th><th class="text-center">Status</th><th class="text-end">Actions</th></tr></thead><tbody id="gep-level-rows"><tr><td colspan="8" class="text-center py-4">Loading GEP levels…</td></tr></tbody></table></div>
        <div class="card-footer"><select id="gep-level-per-page" class="d-none" aria-hidden="true"><option>10</option><option>25</option><option>50</option><option>100</option></select><div id="gep-level-pagination"></div></div>
    </div>
    <div class="modal modal-blur fade" id="gep-level-modal" tabindex="-1" aria-labelledby="gep-level-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
        <form id="gep-level-form" novalidate>
            <div class="modal-header gep-settings-header"><h2 class="modal-title text-white" id="gep-level-title">Add GEP Level</h2><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div id="gep-level-error" class="alert alert-danger d-none" role="alert"></div>
                <div class="form-floating mb-3"><input id="gep-level-type" name="type" class="form-control" placeholder="Type" maxlength="100"><label for="gep-level-type">Type</label><div class="invalid-feedback" data-error="type"></div></div>
                <div class="form-floating mb-3"><input id="gep-level-name" name="level_name" class="form-control" placeholder="Level Name" maxlength="100" required><label for="gep-level-name">Level Name <span class="text-danger">*</span></label><div class="invalid-feedback" data-error="level_name"></div></div>
                <div class="form-floating mb-3"><input id="gep-level-short" name="short_name" class="form-control" placeholder="Abbreviation" maxlength="30" required><label for="gep-level-short">Abbreviation <span class="text-danger">*</span></label><div class="invalid-feedback" data-error="short_name"></div></div>
                <div class="form-floating mb-3"><input id="gep-level-program" name="program" class="form-control" placeholder="Program" maxlength="100"><label for="gep-level-program">Program</label><div class="invalid-feedback" data-error="program"></div></div>
                <div class="form-floating mb-3"><input id="gep-level-order" name="sort_order" type="number" min="0" max="2147483647" step="1" value="0" class="form-control" placeholder="Order" required><label for="gep-level-order">Order</label><div class="invalid-feedback" data-error="sort_order"></div></div>
                <div class="form-text mt-2">Lower order numbers appear first. For example: Level 1 / L1. New levels are On by default.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button><button type="submit" id="gep-level-save" class="btn btn-primary">Save</button></div>
        </form>
    </div></div></div>
</div>
@vite('resources/js/gepLevels.js')
@endsection
