@extends('layouts.app')
@section('title', 'School Profile')

@section('page-header')
    <div class="container-fluid"><div class="row g-2 align-items-center">
        <div class="col"><div class="page-pretitle">Overview</div><h2 class="page-title">School Profile</h2></div>
        <div class="col-auto ms-auto d-print-none"><div class="btn-list">
            <a href="{{ route('schoolInfo.pdf') }}" target="_blank" class="btn btn-secondary btn-5 d-none d-sm-inline-block"><i class="ti ti-file-text icon"></i> Export PDF</a>
            <button id="btnNewSchoolProfile" type="button" data-bs-toggle="modal" data-bs-target="#schoolProfileModal" class="btn btn-primary btn-5 d-none d-sm-inline-block"><i class="ti ti-plus icon"></i> New School Profile</button>
            <button id="btnNewSchoolProfileMobile" type="button" data-bs-toggle="modal" data-bs-target="#schoolProfileModal" class="btn btn-primary btn-6 d-sm-none btn-icon"><i class="ti ti-plus icon"></i></button>
        </div></div>
    </div></div>
@endsection

@section('content')
<div class="col-12"><div class="card">
    <div class="card-header"><h3 class="card-title">School Profile Lists</h3></div>
    <div class="card-body border-bottom py-3"><div class="row col-12 g-2 align-items-center justify-content-between">
        <div class="col-auto text-secondary">Show <div class="mx-2 d-inline-block"><select class="form-control form-control-sm" id="school-profiles-per-page">
            <option value="10" selected>10 / page</option><option value="25">25 / page</option><option value="50">50 / page</option><option value="100">100 / page</option>
        </select></div> entries</div>
        <div class="col-auto"><div class="input-icon"><span class="input-icon-addon"><i class="ti ti-search icon"></i></span><input type="text" id="school-profiles-search" class="form-control form-control-sm" placeholder="Search school profiles"></div></div>
    </div></div>
    <div class="table-responsive table-vcenter text-nowrap"><table class="table card-table">
        <thead><tr><th>No.</th><th>Logo</th><th>School Name</th><th>Campus</th><th>Phone</th><th>Status</th><th class="text-center">Actions</th></tr></thead>
        <tbody id="schoolProfilesTable"><tr><td colspan="7" class="text-center">Loading school profiles...</td></tr></tbody>
    </table></div>
    <div class="card-footer"><div class="row g-2"><div class="col-12 d-flex justify-content-center" id="school-profiles-pagination-container"></div></div></div>
</div></div>

<div class="modal modal-blur fade" id="schoolProfileModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content"><form id="schoolProfileForm" novalidate>
    @csrf
    <div class="modal-header"><h5 class="modal-title" id="schoolProfileModalTitle">Create School Profile</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">@include('partials.school-profile-form')</div>
    <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button><button type="submit" id="schoolProfileSubmitBtn" class="btn btn-primary">Create</button></div>
</form></div></div></div>

<div class="modal modal-blur fade" id="schoolProfileViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">View School Profile</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="text-center mb-4"><img id="viewSchoolLogo" src="" alt="School Logo" class="d-none" style="width:110px;height:110px;object-fit:contain"></div>
            <div class="row g-3">
                <div class="col-md-6"><div class="text-secondary">School Name (Khmer)</div><div id="viewSchoolNameKh" class="school-profile-khmer fs-3"></div></div>
                <div class="col-md-6"><div class="text-secondary">School Name (English)</div><div id="viewSchoolNameEn" class="fs-3"></div></div>
                <div class="col-md-6"><div class="text-secondary">Campus (Khmer)</div><div id="viewCampusNameKh" class="school-profile-khmer fs-3"></div></div>
                <div class="col-md-6"><div class="text-secondary">Campus (English)</div><div id="viewCampusNameEn" class="fs-3"></div></div>
                <div class="col-md-6"><div class="text-secondary">Phone Number</div><div id="viewPhone" class="fs-3"></div></div>
                <div class="col-md-6"><div class="text-secondary">Status</div><div id="viewStatus"></div></div>
                <div class="col-12"><div class="text-secondary">Address</div><div id="viewAddress" class="text-wrap"></div></div>
                <div class="col-12"><div class="text-secondary">Description</div><div id="viewDescription" class="text-wrap"></div></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button><button type="button" id="viewSchoolPrintBtn" class="btn btn-primary"><i class="ti ti-printer icon"></i>Print</button></div>
    </div></div>
</div>
@vite('resources/js/schoolProfile.js')
@endsection
