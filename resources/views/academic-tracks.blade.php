@extends('layouts.app')
@section('title', 'Academic Tracks')
@section('page-header')
<div class="container-fluid">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Settings</div>
            <h2 class="page-title">Academic Tracks</h2>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" id="newAcademicTrack"><i class="ti ti-plus icon"></i> New Academic Track</button>
        </div>
    </div>
</div>
@endsection
@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Academic Track List</h3></div>
    <div class="card-body border-bottom py-3">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div class="pagination-box">Show <select id="academic-tracks-per-page" class="form-control form-control-sm d-inline-block w-auto"><option selected>10</option><option>25</option><option>50</option><option>100</option></select> entries</div>
            <div class="input-icon" style="min-width: 280px;">
                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                <input id="academic-tracks-search" class="form-control form-control-sm" placeholder="Search academic track">
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead>
                <tr>
                    <th>Track (Khmer / English)</th>
                    <th>Code</th>
                    <th>Grade</th>
                    <th>Stream</th>
                    <th>Language</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="academicTracksTable"></tbody>
        </table>
    </div>
    <div class="card-footer"><div id="academic-tracks-pagination-container"></div></div>
</div>

<div class="modal modal-blur fade" id="academicTrackModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="academicTrackForm">
                @csrf
                <input type="hidden" name="academic_track_id" id="academic_track_id">
                <div class="modal-header">
                    <h5 id="academicTrackModalTitle" class="modal-title">New Academic Track</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" data-alert></div>
                    <div class="mb-3"><label class="form-label">Track Name (English) *</label><input name="name_en" id="academic_track_name_en" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Track Name (Khmer)</label><input name="name_kh" id="academic_track_name_kh" class="form-control school-profile-khmer"></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Code</label><input name="code" id="academic_track_code" class="form-control" placeholder="SCI-KH"></div>
                        <div class="col-md-6"><label class="form-label">Grade</label><select name="grade_id" id="academic_track_grade_id" class="form-select"><option value="">All / Not assigned</option>@foreach($grades as $grade)<option value="{{ $grade->id }}">{{ $grade->grade }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">Stream *</label><select name="stream_type" id="academic_track_stream_type" class="form-select" required><option value="science">Science</option><option value="social_science">Social Science</option></select></div>
                        <div class="col-md-6"><label class="form-label">Language *</label><select name="language" id="academic_track_language" class="form-select" required><option value="khmer">Khmer</option><option value="english">English</option></select></div>
                        <div class="col-md-12"><label class="form-label">Status</label><select name="status" id="academic_track_status" class="form-select"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-primary">Save Academic Track</button>
                </div>
            </form>
        </div>
    </div>
</div>
@vite('resources/js/academicTracks.js')
@endsection
