@extends('layouts.app')
@section('title', 'G9 Imports/Export')
@section('page-header', '')
@section('content')
    @vite(['resources/css/pages/g9-national-exams.css','resources/js/g9Imports.js'])
    <div data-g9-imports data-base="{{ url('/g9-national-exams/imports-exports') }}">
        <section class="card mb-3">
            <div class="card-header g9-header"><h3 class="card-title"><i class="ti ti-file-spreadsheet me-2"></i>G9 Imports/Export</h3></div>
            <div class="card-body">
                <div class="g9-transfer-actions">
                    @if($canExport)
                        <a class="btn btn-outline-primary" href="{{ route('g9-national-exams.imports-exports.download','template') }}"><i class="ti ti-download me-2"></i>Download Sample CSV</a>
                        <a class="btn btn-primary" href="{{ route('g9-national-exams.imports-exports.download','all') }}"><i class="ti ti-file-export me-2"></i>Export All Data — CSV</a>
                    @endif
                </div>
                <p class="text-secondary mt-3 mb-2">Import a MoEYS workbook, the old-system workbook, or a UTF-8 CSV. The sample CSV has 38 MoEYS columns; Export All Data also includes school certificate tracking and academic-year settings.</p>
                <ul class="text-secondary small mb-0">
                    <li>Keep the column headers unchanged. CSV files must use UTF-8 encoding.</li>
                    <li>Required: academic_year, stu_id, full_name_English, and campus. Use existing academic year and campus names.</li>
                    <li>Dates: YYYY-MM-DD or DD/MM/YYYY. Khmer examination dates from MoEYS are also supported.</li>
                    <li>Student IDs, phone numbers, rooms, and seats should be text in Excel to preserve leading zeros.</li>
                    <li>Records match by academic year + Student ID. Matching records are updated; new records are added. Blank cells clear the corresponding field; omitted optional columns keep existing values.</li>
                    <li>imageName stores the MoEYS photo filename for bulk photo matching. Photos are not included in CSV files.</li>
                    <li>Old campus names map as BTB → BTB3 and CKD → CKD1. MoEYS photo filenames are kept unchanged.</li>
                    <li>Old certificate numbers, collection records, document filenames, and signing staff are preserved. Shared dates and signers are stored once per academic year.</li>
                    <li>Existing G9 Setting dates and signers are kept when the workbook contains different values. These differences appear as review warnings and do not block candidate imports. Conflicting shared values within the same file must still be corrected.</li>
                    <li>Incomplete collection records and failed candidates with historical certificate numbers are flagged for review. No missing dates are invented.</li>
                </ul>
            </div>
        </section>
        @if($canImport)
            <section class="card mb-3">
                <div class="card-header g9-header"><h3 class="card-title"><i class="ti ti-upload me-2"></i>Import Candidate Data</h3></div>
                <form class="card-body" data-g9-import-form>
                    <div class="form-label" id="g9ImportFileLabel">MoEYS Excel / CSV File</div>
                    <input type="file" name="file" id="g9ImportFile" accept=".csv,.xlsx" hidden>
                    <button type="button" class="g9-import-dropzone" data-g9-import-dropzone aria-labelledby="g9ImportFileLabel g9ImportUploadTitle" aria-describedby="g9ImportUploadHint g9ImportLimits g9ImportError">
                        <span class="document-upload-icon"><i class="ti ti-cloud-upload" aria-hidden="true"></i></span>
                        <strong id="g9ImportUploadTitle">Drag &amp; drop your file here</strong>
                        <span>or <span class="document-browse-link">browse files</span></span>
                        <span class="document-upload-hint" id="g9ImportUploadHint">Copy a file and paste it here with Ctrl+V (⌘V on Mac).</span>
                        <span class="g9-import-formats"><span>XLSX</span><span>CSV</span><span>Up to 10 MB</span></span>
                    </button>
                    <div class="g9-import-file" data-g9-import-file hidden role="status" aria-live="polite">
                        <span class="document-file-icon"><i class="ti ti-file-spreadsheet" aria-hidden="true"></i></span>
                        <div class="g9-import-file-details"><div class="document-file-name" data-g9-import-filename></div><div class="document-file-meta" data-g9-import-filemeta></div></div>
                        <button type="button" class="document-file-remove" data-g9-import-remove aria-label="Remove selected file"><i class="ti ti-x" aria-hidden="true"></i></button>
                    </div>
                    @include('g9-national-exams.partials.upload-progress')
                    <div class="g9-import-upload-footer">
                        <p class="form-hint mb-0" id="g9ImportLimits">Up to 5,000 candidates. Preview your file before confirming the import.</p>
                        <button type="submit" class="btn btn-primary" data-g9-preview disabled><i class="ti ti-eye me-2" aria-hidden="true"></i>Preview Import</button>
                    </div>
                    <div class="text-danger mt-2" id="g9ImportError" data-g9-import-error role="alert"></div>
                    <div class="text-success" data-g9-import-success role="status"></div>
                </form>
            </section>
            <section class="card mb-3" data-g9-preview-panel hidden>
                <div class="card-header g9-header"><h3 class="card-title">Import Preview</h3><button type="button" class="btn g9-confirm-import ms-auto" data-g9-confirm disabled>Confirm Import</button></div>
                <div class="card-body pb-0" data-g9-confirm-progress-area hidden>@include('g9-national-exams.partials.upload-progress')</div>
                <div class="card-body"><div class="g9-import-summary" data-g9-summary></div><div class="mt-3" data-g9-preview-errors></div><p class="form-hint mt-3 mb-0">First 20 valid rows shown. Confirmation imports the entire validated file. All errors must be corrected first.</p></div>
                <div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>Row</th><th>Action</th><th>Student ID</th><th>Name Khmer</th><th>Name English</th></tr></thead><tbody data-g9-preview-rows></tbody></table></div>
            </section>
        @endif
        @if($canHistory)
            <section class="card">
                <div class="card-header g9-header"><h3 class="card-title">Your Import History</h3></div>
                <div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>File</th><th>Date</th><th>Status</th><th>New</th><th>Updated</th><th>Errors</th><th>Review</th></tr></thead><tbody data-g9-import-history></tbody></table></div><div class="text-danger small p-2" data-g9-history-error role="alert"></div>
            </section>
        @endif
    </div>
@endsection
