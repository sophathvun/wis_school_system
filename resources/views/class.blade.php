@extends('layouts.app')
@section('title', 'Classes')

@section('page-header')
    <div class="container-fluid"><div class="row g-2 align-items-center">
        <div class="col"><div class="page-pretitle">Overview</div><h2 class="page-title">Classes</h2></div>
        <div class="col-auto ms-auto d-print-none"><div class="btn-list">
            <a href="{{ route('classes.pdf') }}" target="_blank" class="btn btn-secondary btn-5 d-none d-sm-inline-block"><i class="ti ti-file-text icon"></i> Export PDF</a>
            <button id="btnNewClass" type="button" class="btn btn-primary btn-5 d-none d-sm-inline-block"><i class="ti ti-plus icon"></i> New Class</button>
            <button id="btnNewClassMobile" type="button" class="btn btn-primary btn-6 d-sm-none btn-icon"><i class="ti ti-plus icon"></i></button>
        </div></div>
    </div></div>
@endsection

@section('content')
<div class="col-12"><div class="card">
    <div class="card-header"><h3 class="card-title">Class Lists</h3></div>
    <div class="card-body border-bottom py-3"><div class="row col-12 g-2 align-items-center justify-content-between">
        <div class="col-auto text-secondary">Show <div class="mx-2 d-inline-block"><select class="form-control form-control-sm" id="classes-per-page">
            <option value="10" selected>10 / page</option><option value="25">25 / page</option><option value="50">50 / page</option><option value="100">100 / page</option>
        </select></div> entries</div>
        <div class="col-auto"><div class="input-icon"><span class="input-icon-addon"><i class="ti ti-search icon"></i></span><input type="text" id="classes-search" class="form-control form-control-sm" placeholder="Search classes"></div></div>
    </div></div>
    <div class="table-responsive table-vcenter text-nowrap"><table class="table card-table">
        <thead><tr><th>No.</th><th><button type="button" class="table-sort" data-sort="class_name">Class Name</button></th><th><button type="button" class="table-sort" data-sort="class_order">Order</button></th><th><button type="button" class="table-sort" data-sort="status">Status</button></th><th class="text-center">Actions</th></tr></thead>
        <tbody id="classesTable"><tr><td colspan="5" class="text-center">Loading classes...</td></tr></tbody>
    </table></div>
    <div class="card-footer"><div class="row g-2"><div class="col-12 d-flex justify-content-center" id="classes-pagination-container"></div></div></div>
</div></div>

<div class="modal modal-blur fade" id="classModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-md"><div class="modal-content"><form id="classForm" novalidate>
    @csrf
    <div class="modal-header"><h5 class="modal-title" id="classModalTitle">Create Class</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">@include('partials.class-form')</div>
    <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button><button type="submit" id="classSubmitBtn" class="btn btn-primary">Create</button></div>
</form></div></div></div>
@vite('resources/js/class.js')
@endsection
