<section class="card" data-wmi-registration-tools data-import-url="{{ route('wmi-management.registrations.import-ids',[],false) }}">
    <div class="card-header gap-3"><span class="wmi-content-icon"><i class="ti ti-file-import" aria-hidden="true"></i></span><div><div class="page-pretitle">WMI Management</div><h2 class="card-title mb-0">Import/Export Data</h2></div></div>
    <div class="card-body">
        <h3>Export Candidates</h3><p class="text-secondary">Download an Excel-compatible CSV for the WMI Committee. Fill in WMI IDs and keep each registration reference unchanged.</p>
        <form method="GET" action="{{ route('wmi-management.registrations.export') }}" class="wmi-registration-filters mb-4" data-tools-export>
            <div><label class="visually-hidden" for="wmiExportYear">WMI Year</label><select class="form-select" id="wmiExportYear" name="wmi_year_id" data-tool-picker="WMI Year"><option value="">All WMI Years</option>@foreach($registrationYears as $year)<option value="{{ $year->id }}">WMI {{ $year->year }}</option>@endforeach</select></div>
            <div><label class="visually-hidden" for="wmiExportCenter">Exam Center</label><select class="form-select" id="wmiExportCenter" name="wmi_exam_center_id" data-tool-picker="Exam Center"><option value="">All Exam Centers</option>@foreach($registrationCenters as $center)<option value="{{ $center->id }}" data-year="{{ $center->wmi_year_id }}">{{ $center->name }} · WMI {{ $registrationYears->firstWhere('id',$center->wmi_year_id)?->year }}</option>@endforeach</select></div>
            <button class="btn btn-primary" type="submit"><i class="ti ti-download me-2" aria-hidden="true"></i>Export Candidates</button>
        </form>
        <hr><h3>Import Committee WMI IDs</h3><p class="text-secondary">Upload the completed CSV or XLSX file to update committee IDs. The import validates every row before saving and protects existing IDs.</p>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#wmiIdImportModal" @disabled($registrationYears->isEmpty())><i class="ti ti-upload me-2" aria-hidden="true"></i>Import WMI IDs</button>
    </div>
</section>
