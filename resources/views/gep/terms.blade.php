@extends('layouts.app')
@section('title','GEP Terms')
@section('content')
<div id="gep-terms" data-url="{{ url('/gep/settings/terms') }}" data-permissions='@json($permissions)'>
    <div class="card shadow-sm">
        <div class="card-header gep-settings-header d-flex flex-wrap gap-3"><h1 class="card-title text-white mb-0"><i class="ti ti-calendar-month me-2"></i>GEP Terms</h1>@if($permissions['create'])<button type="button" id="gep-term-add" class="btn btn-light ms-auto"><i class="ti ti-plus me-1"></i>Add New</button>@endif</div>
        <div class="card-body border-bottom py-3"><div class="input-icon" style="max-width:360px"><span class="input-icon-addon"><i class="ti ti-search"></i></span><input id="gep-term-search" type="search" class="form-control" placeholder="Search term name or abbreviation" aria-label="Search GEP terms"></div></div>
        <div id="gep-term-list-error" class="alert alert-danger d-none m-3" role="alert"></div>
        <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th class="text-center">No.</th><th>Term Name</th><th>Abbreviation</th><th class="text-center">Status</th><th class="text-end">Actions</th></tr></thead><tbody id="gep-term-rows"><tr><td colspan="5" class="text-center py-4">Loading GEP terms…</td></tr></tbody></table></div>
        <div class="card-footer"><select id="gep-term-per-page" class="d-none" aria-hidden="true"><option>10</option><option>25</option><option>50</option><option>100</option></select><div id="gep-term-pagination"></div></div>
    </div>
    <div class="modal modal-blur fade" id="gep-term-modal" tabindex="-1" aria-labelledby="gep-term-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
        <form id="gep-term-form" novalidate>
            <div class="modal-header gep-settings-header"><h2 class="modal-title text-white" id="gep-term-title">Add GEP Term</h2><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div id="gep-term-error" class="alert alert-danger d-none" role="alert"></div>
                <div class="form-floating mb-3"><input id="gep-term-name" name="term_name" class="form-control" placeholder="Term Name" maxlength="100" required><label for="gep-term-name">Term Name <span class="text-danger">*</span></label><div class="invalid-feedback" data-error="term_name"></div></div>
                <div class="form-floating"><input id="gep-term-short" name="short_name" class="form-control" placeholder="Abbreviation" maxlength="30" required><label for="gep-term-short">Abbreviation <span class="text-danger">*</span></label><div class="invalid-feedback" data-error="short_name"></div></div>
                <div class="form-text mt-2">For example: Term 1 / T1. New terms are On by default.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button><button type="submit" id="gep-term-save" class="btn btn-primary">Save</button></div>
        </form>
    </div></div></div>
</div>
@vite('resources/js/gepTerms.js')
@endsection
