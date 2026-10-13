<section class="card hrm-settings-list mb-3">
    <div class="card-header hrm-settings-header"><h3 class="card-title"><i class="ti ti-file-export me-2" aria-hidden="true"></i>Staff Data Downloads</h3></div>
    <div class="card-body hrm-staff-data-downloads">
        <div><span class="hrm-staff-data-icon"><i class="ti ti-file-type-csv" aria-hidden="true"></i></span><h4>CSV Import Template</h4><p class="text-secondary">Download the column headings and two sample staff records. Replace the sample records with your staff information.</p>
            @if($canTemplateStaffData)<a class="btn btn-outline-primary" href="{{ route('hrm.settings.staff-data.template') }}"><i class="ti ti-download me-2" aria-hidden="true"></i>Download CSV Template</a>@endif
        </div>
        <div><span class="hrm-staff-data-icon"><i class="ti ti-file-spreadsheet" aria-hidden="true"></i></span><h4>Export Staff Information</h4><p class="text-secondary">Export all saved staff profiles to Excel, including education, previous employment, working schedules, and readable location names.</p>
            @if($canExportStaffData)<a class="btn btn-outline-success" href="{{ route('hrm.settings.staff-data.excel') }}"><i class="ti ti-file-spreadsheet me-2" aria-hidden="true"></i>Export Excel</a>@endif
        </div>
    </div>
</section>
<section class="card hrm-settings-list">
    <div class="card-header hrm-settings-header"><h3 class="card-title"><i class="ti ti-file-import me-2" aria-hidden="true"></i>Import Staff from CSV</h3></div>
    <div class="card-body">
        <div class="hrm-staff-import-guide">
            <p>One staff member per row. <strong>Staff ID</strong> and <strong>Staff Name (English)</strong> are required. Imports create new profiles; existing Staff IDs are reported and never overwritten.</p>
            <ul class="mb-0">
                <li>Use names for countries, provinces/cities, addresses, departments, positions, and campuses. Database IDs are not needed. Khmer or English location names are accepted.</li>
                <li>Dates: <strong>YYYY-MM-DD</strong> or <strong>DD-MMM-YYYY</strong>. Times: <strong>HH:MM</strong>. Separate working campus names with <strong>|</strong>.</li>
                <li>Supervisor Staff ID uses the staff's own Staff ID. A supervisor may be listed elsewhere in the same CSV.</li>
                <li>Gender (Khmer), Date of Birth (Khmer), Marital Status (Khmer), and Nationality Note are calculated automatically.</li>
                <li>Keep column headings. Optional columns may be left blank or removed. Education and previous employment use numbered column groups.</li>
                <li>Save as <strong>CSV UTF-8</strong>. Up to <strong>5,000 staff / 10 MB</strong> per file. Valid rows are imported; rows with errors are skipped and listed below. Download the failed records, correct them, and import that CSV again.</li>
                <li>The error report includes <strong>Suggested Correction</strong> for linked fields: locations, nationality, Department, Position, Primary/Working Campuses, Supervisor Staff ID, and dropdown choices. Review each suggestion before changing the failed-records CSV; suggestions are not applied automatically.</li>
            </ul>
        </div>
        @if($canImportStaffData)
        <form method="POST" action="{{ route('hrm.settings.staff-data.import') }}" enctype="multipart/form-data" class="hrm-staff-import-form mt-3" data-staff-csv-import>
            @csrf
            <label class="form-label" for="staffCsvFile">Staff CSV File <span class="text-danger">*</span></label>
            <input type="file" id="staffCsvFile" name="file" accept=".csv,text/csv" class="form-control @error('file') is-invalid @enderror" required>
            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="d-flex align-items-center flex-wrap gap-3 mt-3"><button type="submit" class="btn btn-primary" data-staff-import-submit><i class="ti ti-file-import me-2" aria-hidden="true"></i><span>Import Staff</span></button><span class="text-secondary small" data-staff-import-progress hidden role="status">Importing staff. Please keep this page open.</span></div>
        </form>
        @else
            <p class="text-secondary mt-3 mb-0">Staff Create and CSV Import permissions are required to import staff.</p>
        @endif
    </div>
</section>
@if($result=session('staff_import_result'))
<section class="card hrm-settings-list mt-3">
    <div class="card-header hrm-settings-header"><h3 class="card-title"><i class="ti ti-report me-2" aria-hidden="true"></i>Last Import Result</h3>
        @if($result['error_count'] && $canImportStaffData)<div class="d-flex flex-wrap gap-2"><a href="{{ route('hrm.settings.staff-data.errors') }}" class="btn btn-sm btn-outline-light"><i class="ti ti-download me-1" aria-hidden="true"></i>Download Error Report</a>@if(isset($result['report_id']))<a href="{{ route('hrm.settings.staff-data.failed-records') }}" class="btn btn-sm btn-outline-light"><i class="ti ti-file-type-csv me-1" aria-hidden="true"></i>Download Failed Records</a>@endif</div>@endif
    </div>
    <div class="card-body"><div class="alert {{ $result['error_count']?'alert-warning':'alert-success' }} mb-0" role="status">
        @if($result['error_count'])<strong>{{ number_format($result['created']) }} staff profile(s) imported.</strong> {{ number_format($result['skipped']??0) }} row(s) skipped with {{ number_format($result['error_count']) }} issue(s). Correct the failed records and import them again. Successfully imported records do not need to be uploaded again.
        @else<strong>{{ number_format($result['created']) }} staff profiles imported successfully.</strong>@endif
    </div></div>
    @if($result['errors'])<div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>CSV Row</th><th>Staff ID</th><th>Field</th><th>Problem</th><th>Suggested Correction</th></tr></thead><tbody>
        @foreach($importIssues as $error)<tr><td>{{ $error['row'] }}</td><td>{{ $error['code'] }}</td><td>{{ $error['field'] }}</td><td>{{ $error['message'] }}</td><td>{{ $error['suggestion']??'—' }}</td></tr>@endforeach
    </tbody></table></div><div class="card-footer"><span class="text-secondary small">{{ $importIssues->firstItem()??0 }}–{{ $importIssues->lastItem()??0 }} of {{ number_format($importIssues->total()) }} issues</span>{{ $importIssues->links('pagination::bootstrap-5') }}</div>@endif
</section>
@endif
