@extends('layouts.app')
@section('title','GEP Academic Year')
@section('content')
    <div id="gep-years" data-url="{{ url('/gep/settings/academic-years') }}" data-permissions='@json($permissions)'>
        <div class="card shadow-sm">
            <div class="card-header gep-settings-header d-flex gap-3 flex-wrap">
                <h1 class="card-title text-white mb-0"><i class="ti ti-calendar me-2"></i>GEP Academic Year</h1>
                @if($permissions['create'])<button type="button" class="btn btn-light ms-auto" id="gep-year-add"><i class="ti ti-plus me-1"></i>Add New</button>@endif
            </div>
            <div class="card-body border-bottom py-3"><div class="input-icon" style="max-width:360px"><span class="input-icon-addon"><i class="ti ti-search"></i></span><input type="search" id="gep-year-search" class="form-control" placeholder="Search academic year" aria-label="Search academic year"></div></div>
            <div id="gep-year-list-error" class="alert alert-danger m-3 d-none" role="alert"></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table"><thead><tr><th class="text-center">No.</th><th>Academic Year</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody id="gep-year-rows"><tr><td colspan="4" class="text-center py-4">Loading academic years…</td></tr></tbody></table>
            </div>
            <div class="card-footer"><select id="gep-year-per-page" class="d-none" aria-hidden="true"><option>10</option><option>25</option><option>50</option><option>100</option></select><div id="gep-year-pagination"></div></div>
        </div>
        <div class="modal modal-blur fade" id="gep-year-modal" tabindex="-1" aria-labelledby="gep-year-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
                <form id="gep-year-form" novalidate>
                    <div class="modal-header gep-settings-header"><h2 class="modal-title text-white" id="gep-year-title">Add GEP Academic Year</h2><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <div id="gep-year-error" class="alert alert-danger d-none" role="alert"></div>
                        <div class="form-floating mb-3"><input id="gep-year-name" name="academic_year" class="form-control" placeholder="2026" inputmode="numeric" pattern="[1-9][0-9]{3}" maxlength="4" required><label for="gep-year-name">Academic Year <span class="text-danger">*</span></label><div class="invalid-feedback" data-error="academic_year"></div></div>
                        <div class="form-text mb-3">Enter a four-digit year, for example 2023, 2024 or 2025.</div>
                        <div class="text-secondary small">New academic years are Active. Use the list toggle to activate or deactivate a year. Start and finish terms in Assign Terms.</div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit" id="gep-year-save">Save</button></div>
                </form>
            </div></div>
        </div>
    </div>
    @vite('resources/js/gepAcademicYears.js')
@endsection
