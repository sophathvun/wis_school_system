@extends('layouts.app')
@section('title', 'Sessions')

@section('page-header')
    <div class="container-fluid"><div class="row g-2 align-items-center">
        <div class="col"><div class="page-pretitle">Overview</div><h2 class="page-title">Sessions</h2></div>
        <div class="col-auto ms-auto d-print-none"><div class="btn-list">
            <a href="{{ route('sessions.pdf') }}" target="_blank" class="btn btn-secondary btn-5 d-none d-sm-inline-block"><i class="ti ti-file-text icon"></i> Export PDF</a>
            <button id="btnNewSession" type="button" class="btn btn-primary btn-5 d-none d-sm-inline-block"><i class="ti ti-plus icon"></i> New Session</button>
            <button id="btnNewSessionMobile" type="button" class="btn btn-primary btn-6 d-sm-none btn-icon"><i class="ti ti-plus icon"></i></button>
        </div></div>
    </div></div>
@endsection

@section('content')
<div class="col-12"><div class="card">
    <div class="card-header"><h3 class="card-title">Session Lists</h3></div>
    <div class="card-body border-bottom py-3"><div class="row col-12 g-2 align-items-center justify-content-between">
        <div class="col-auto text-secondary">Show <div class="mx-2 d-inline-block"><select class="form-control form-control-sm" id="sessions-per-page">
            <option value="10" selected>10 / page</option><option value="25">25 / page</option><option value="50">50 / page</option><option value="100">100 / page</option>
        </select></div> entries</div>
        <div class="col-auto"><div class="input-icon"><span class="input-icon-addon"><i class="ti ti-search icon"></i></span><input type="text" id="sessions-search" class="form-control form-control-sm" placeholder="Search sessions"></div></div>
    </div></div>
    <div class="table-responsive table-vcenter text-nowrap"><table class="table card-table">
            <thead><tr><th>No.</th><th>Session Name</th><th>Group</th><th>Order</th><th>Description</th><th>Status</th><th class="text-center">Actions</th></tr></thead>
        <tbody id="sessionsTable"><tr><td colspan="7" class="text-center">Loading sessions...</td></tr></tbody>
    </table></div>
    <div class="card-footer"><div class="row g-2"><div class="col-12 d-flex justify-content-center" id="sessions-pagination-container"></div></div></div>
</div></div>

<div class="modal modal-blur fade" id="sessionModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><form id="sessionForm" novalidate>
    @csrf
    <div class="modal-header"><h5 class="modal-title" id="sessionModalTitle">Create Session</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">@include('partials.session-form')</div>
    <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button><button type="submit" id="sessionSubmitBtn" class="btn btn-primary">Create</button></div>
</form></div></div></div>
@vite('resources/js/session.js')
@endsection
