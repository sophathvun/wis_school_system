@extends('layouts.app')
@section('title','Attendance Import / Export')
@section('page-header')
<div class="container-fluid"><div class="page-pretitle">Attendance</div><h2 class="page-title">Import / Export</h2></div>
@endsection
@section('content')
@vite(['resources/css/pages/attendance-transfer.css','resources/js/attendanceTransfer.js'])
<div class="attendance-transfer" data-attendance-transfer data-base="{{ route('attendance.transfer.index') }}">
    <div class="card mb-3"><div class="card-body d-flex flex-wrap align-items-center gap-3"><span class="att-transfer-icon"><i class="ti ti-history"></i></span><div class="flex-fill"><h2 class="mb-1">Bring attendance history together</h2><p class="text-secondary mb-0">Match by Student ID and enrollment date. Review first, then import without overwriting existing attendance.</p></div><a class="btn btn-outline-primary" href="{{ route('help.show','attendance-transfer') }}"><i class="ti ti-lifebuoy me-2"></i>Help &amp; guides</a></div></div>
    <div class="alert alert-info"><i class="ti ti-info-circle me-2"></i>Historical absence-only records do not prove other students were present. Unrecorded students stay unmarked. E = Excused Absence · U = Unexcused Absence · T = Tardy.</div>
    <div class="row g-3 mb-3">
        @if($canImport)
        <div class="col-lg-6"><section class="card h-100"><div class="card-header att-transfer-header flex-wrap gap-2"><h3 class="card-title">Import attendance history</h3><a class="btn btn-sm btn-outline-light ms-auto" href="{{ route('attendance.transfer.template') }}">Download template</a></div><div class="card-body">
            <form class="skipping-documents-upload" data-transfer-upload enctype="multipart/form-data">@csrf
                <input type="file" name="file" accept=".csv" aria-label="Attendance CSV file" class="form-control" required>
                <div class="premium-document-dropzone" data-transfer-drop tabindex="0" role="button" aria-label="Choose attendance CSV file" hidden><span class="document-upload-icon"><i class="ti ti-cloud-upload"></i></span><div class="fw-bold fs-3">Drop File Here</div><div class="mt-1">or <span class="document-browse-link">Browse File</span></div><div class="document-upload-hint mt-3">CSV UTF-8 · Maximum 12 MB · 50,000 rows</div><div class="document-upload-hint">You can also paste a file here</div></div>
                <div class="d-flex align-items-center gap-2 mt-2" data-selected-file hidden><span class="flex-fill text-break" data-file-name></span><button type="button" class="btn btn-sm btn-outline-secondary" data-clear-file aria-label="Remove selected file">Remove</button></div>
                <button class="btn btn-primary w-100 mt-3" type="submit"><i class="ti ti-eye me-2"></i>Upload &amp; preview</button>
            </form>
            <p class="form-hint mt-3 mb-0">Use one row per student, date and period/status. Different non-overlapping periods on the same date may contain T, E or U. Leave campus blank to match the enrollment. For E / U / T, leave missed_sessions blank to calculate it from time_period (for example 08:00-08:50 AM). Complete configured periods must match the student’s study group and grade; unmatched or ambiguous times need review. Tardy remains T while its matched periods count toward missed sessions. Repeated T records in different periods are allowed and counted separately in attendance reports. An explicit missed_sessions value, including 0, takes priority.</p>
            <p class="form-hint mt-2 mb-0">Use YYYY-MM-DD dates; old M/D/YYYY and DD-Mon-YYYY dates are also supported. Keep Student IDs as text to preserve leading zeros. Different records for the same period, or records without uniquely matched periods, require review.</p>
        </div></section></div>
        @endif
        @if($canExport)
        <div class="col-lg-6"><section class="card h-100"><div class="card-header att-transfer-header"><h3 class="card-title">Export daily attendance</h3></div><form class="card-body" method="GET" action="{{ route('attendance.transfer.export') }}">
            <div class="row g-3"><div class="col-sm-6"><label class="form-label" for="transfer-year">Academic Year</label><select class="form-select" name="academic_year_id" id="transfer-year" required><option value="">Select academic year</option>@foreach($years as $year)<option value="{{ $year->id }}">{{ $year->academic_year }}</option>@endforeach</select></div>
            <div class="col-sm-6"><label class="form-label" for="transfer-campus">Campus</label><select class="form-select" name="campus_id" id="transfer-campus"><option value="">All permitted campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}">{{ $campus->campus_name_en }}</option>@endforeach</select></div>
            <div class="col-sm-6"><label class="form-label" for="transfer-from">From Date</label><input class="form-control" type="date" name="date_from" id="transfer-from"></div><div class="col-sm-6"><label class="form-label" for="transfer-to">To Date</label><input class="form-control" type="date" name="date_to" id="transfer-to"></div>
            <div class="col-12"><label class="form-label" for="transfer-status">Attendance</label><select class="form-select" name="attendance" id="transfer-status"><option value="">All recorded statuses</option><option value="P">Present</option><option value="EX">Excused Absence</option><option value="UX">Unexcused Absence</option><option value="T">Tardy</option></select></div></div>
            <button class="btn btn-outline-primary w-100 mt-3" type="submit"><i class="ti ti-download me-2"></i>Export CSV</button><p class="form-hint mt-3 mb-0">Exports recorded daily marks only. Schedule-period snapshots are included for reference. Use system backups for a complete restore.</p>
        </form></section></div>
        @endif
    </div>
    <div class="alert alert-danger" data-transfer-error role="alert" hidden></div>
    <section class="card mb-3" data-transfer-progress hidden><div class="card-body"><div class="d-flex justify-content-between gap-2 mb-2"><strong data-progress-title>Uploading file</strong><strong data-progress-value>0%</strong></div><div class="progress" role="progressbar" aria-label="Attendance import progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="progress-bar" style="width:0%"></div></div><div class="text-secondary mt-2" data-progress-detail aria-live="polite"></div></div></section>
    <section class="card mb-3" data-transfer-preview hidden><div class="card-header att-transfer-header flex-wrap gap-2"><h3 class="card-title">Review your import</h3><span class="ms-auto text-break" data-batch-name></span></div><div class="card-body">
        <div class="att-transfer-counts mb-3" data-transfer-counts></div><div class="d-flex flex-wrap gap-2 mb-3"><button class="btn btn-primary" type="button" data-confirm-import @unless($canImport) hidden @endunless>Import valid rows</button><a class="btn btn-outline-secondary" data-review-download>Download review CSV</a></div>
        <p class="form-hint">Only Ready rows are imported. Review rows remain unchanged. Duplicate rows are skipped. You may leave and resume a saved preview from Import History.</p>
        <div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>CSV Row</th><th>Student ID</th><th>Academic Year</th><th>Date</th><th>Attendance</th><th>Missed sessions</th><th>Result</th><th>Details</th></tr></thead><tbody data-preview-rows></tbody></table></div><div class="d-flex gap-2 align-items-center"><button class="btn btn-sm" type="button" data-preview-prev>Previous</button><span class="text-secondary" data-preview-page></span><button class="btn btn-sm" type="button" data-preview-next>Next</button></div>
    </div></section>
    <section class="card"><div class="card-header att-transfer-header"><h3 class="card-title">Your import history</h3></div><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>File</th><th>Created</th><th>Status</th><th></th></tr></thead><tbody data-transfer-history></tbody></table></div><div class="card-footer d-flex gap-2 align-items-center"><button class="btn btn-sm" type="button" data-history-prev>Previous</button><span class="text-secondary" data-history-page></span><button class="btn btn-sm" type="button" data-history-next>Next</button></div></section>
</div>
@endsection
