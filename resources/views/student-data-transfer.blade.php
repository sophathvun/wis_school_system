@extends('layouts.app')

@section('title', 'Student Import / Export')
@push('styles')
    @vite('resources/css/pages/student-data-transfer.css')
@endpush
@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Students</div>
                <h2 class="page-title">Student Data Import / Export</h2>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="alert alert-info"><i class="ti ti-info-circle me-2"></i>Use separate CSV files for Student Information,
        Enrollment History, and Family Members. Enrollment rows are linked by Student ID and Academic Year, allowing one
        student to have multiple academic-year records.</div>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    @if (session('failed_rows_download_error'))
        <div class="alert alert-warning">{{ session('failed_rows_download_error') }}</div>
    @endif
    @if (session('import_report'))
        @php
            $importReport = session('import_report');
        @endphp
        <div id="import-result" class="card border-warning mb-3" tabindex="-1">
            <div class="card-header bg-warning-lt">
                <h3 class="card-title"><i class="ti ti-alert-triangle me-2"></i>Import Result</h3>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-sm-4"><div class="text-secondary">Rows processed</div><strong>{{ $importReport['total'] }}</strong></div>
                    <div class="col-sm-4"><div class="text-secondary">Imported successfully</div><strong class="text-success">{{ $importReport['imported'] }}</strong></div>
                    <div class="col-sm-4"><div class="text-secondary">Not imported</div><strong class="text-danger">{{ count($importReport['failed']) }}</strong></div>
                </div>
                @if (count($importReport['failed']))
                    @if ($importReport['type'] === 'enrollments' && session('enrollment_failed_rows'))
                        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                            <a class="btn btn-primary" href="{{ route('student-data-transfer.failed-rows', 'enrollments') }}"><i class="ti ti-file-download me-2"></i>Download Failed Rows CSV</a>
                            <span class="text-secondary small">Correct the rejected rows and re-import this CSV. Error columns are ignored during import.</span>
                        </div>
                    @endif
                    <p class="mb-2">The following records were not imported. Please correct the data and register them later.</p>
                    <div class="table-responsive">
                        <table class="table table-vcenter table-bordered">
                            <thead><tr><th>CSV Row</th><th>Record</th><th>Missing Fields</th><th>Reason</th><th>Submitted Information</th></tr></thead>
                            <tbody>
                                @foreach ($importReport['failed'] as $failure)
                                    <tr>
                                        <td>{{ $failure['line'] }}</td>
                                        <td>{{ $failure['identifier'] }}</td>
                                        <td>{{ $failure['fields'] ? implode(', ', $failure['fields']) : '—' }}</td>
                                        <td>{{ $failure['reason'] }}</td>
                                        <td><code>{{ json_encode($failure['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-success">All non-empty rows were imported successfully.</div>
                @endif
            </div>
        </div>
    @endif
    <div class="row row-cards">
        @foreach ([['students', 'Student Information', 'Student personal information and addresses.'], ['enrollments', 'Student Enrollments', 'One row per student per academic year.'], ['families', 'Mother, Father & Guardian', 'Shared family member records linked by Family Number.']] as [$type, $title, $description])
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title">{{ $title }}</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary small">{{ $description }}</p>
                        @if ($type === 'enrollments' && session('enrollment_failed_rows'))
                            <a class="btn btn-outline-warning w-100 mb-3" href="{{ route('student-data-transfer.failed-rows', 'enrollments') }}"><i class="ti ti-file-download me-2"></i>Download Failed Rows CSV ({{ session('enrollment_failed_rows.count') }})</a>
                        @endif
                        <div class="d-grid gap-2"><a class="btn btn-outline-primary"
                                href="{{ route('student-data-transfer.template', $type) }}"><i
                                    class="ti ti-download me-1"></i>Download CSV Template</a><a
                                class="btn btn-outline-secondary"
                                href="{{ route('student-data-transfer.export', $type) }}"><i
                                    class="ti ti-file-download me-1"></i>Export Existing Data</a></div>
                        @php
                            $progressToken = (string) Illuminate\Support\Str::uuid();
                            $progressUrl = Illuminate\Support\Facades\URL::temporarySignedRoute('student-data-transfer.progress', now()->addHour(), ['token'=>$progressToken, 'user'=>auth()->id()]);
                        @endphp
                        <form class="mt-3 csv-import-form skipping-documents-upload" data-csv-import data-import-title="{{ $title }}" data-progress-url="{{ $progressUrl }}" method="POST" action="{{ route('student-data-transfer.import', $type) }}"
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="progress_token" value="{{ $progressToken }}">
                            <label class="form-label" for="csv-file-{{ $type }}">Import CSV</label>
                            <input id="csv-file-{{ $type }}" type="file" name="file" class="form-control" accept=".csv,.txt" required>
                            <div class="premium-document-dropzone" data-csv-dropzone tabindex="0" role="button" aria-label="Choose {{ $title }} CSV file" hidden>
                                <span class="document-upload-icon"><i class="ti ti-cloud-upload"></i></span>
                                <div class="fw-bold fs-3">Drop File Here</div>
                                <div class="mt-1">or <span class="document-browse-link">Browse File</span></div>
                                <div class="document-upload-hint mt-3">CSV or TXT &middot; Maximum 10 MB &middot; One file</div>
                                <div class="document-upload-hint mt-1">You can also copy and paste a file here</div>
                                <div class="premium-document-file-list" data-csv-file-list></div>
                            </div>
                            <div class="text-danger small mt-2" data-csv-error role="alert" hidden></div>
                            <button type="submit" class="btn btn-primary w-100 mt-3"><i class="ti ti-upload me-1"></i>Import {{ $title }}</button>
                            <div class="csv-import-progress mt-3" data-csv-progress hidden>
                                <div class="d-flex justify-content-between align-items-center gap-2"><strong data-csv-stage>Uploading file</strong><span class="csv-import-percent" data-csv-percent>0%</span></div>
                                <div class="progress mt-2" role="progressbar" aria-label="Import progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="progress-bar" data-csv-bar style="width: 0%"></div></div>
                                <div class="text-secondary small mt-2" data-csv-detail aria-live="polite">Preparing your file…</div>
                                <div class="csv-import-counts mt-3" data-csv-counts hidden><span>Imported <strong class="text-success" data-csv-imported>0</strong></span><span>Needs review <strong class="text-warning" data-csv-failed>0</strong></span></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">CSV Preparation Notes</h3>
        </div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Use the exact column names from the downloaded template. Student Number is generated automatically. Student Information and Family templates omit record status; the Enrollment template includes it.</li>
                <li>For linked fields, you may enter either the database ID or the exact English/Khmer name shown in the
                    system. IDs are recommended when names may be duplicated.</li>
                <li>Import Student Information before Enrollments.</li>
                <li>For Enrollment Class, use the class letter (<code>A</code>), grade/class label (<code>10A</code>, <code>K1-A</code>, or <code>N-A</code>), or class ID. The class must match the row's Academic Year, Grade, and Session.</li>
                <li>Session and Group are different fields. Group is optional; leave it blank unless a separate group is configured for that class.</li>
                <li>For <code>academic_track_id</code>, enter the configured Academic Track code, exact English/Khmer name, or ID. The track must match the selected grade. Leave it blank if not applicable or not known for a historical record. Omitting this column preserves an existing track when updating.</li>
                <li>For enrollment dates, use <code>YYYY-MM-DD</code>. Excel-style <code>M/D/YYYY</code> dates are also accepted.</li>
                <li>For Enrollment <code>enrollment_status</code>, use <code>active</code> for current enrollment, <code>completed</code> for a finished academic year, or <code>withdrawn</code> for withdrawal. For withdrawn rows, enter the withdrawal date in <code>ended_on</code> and the reason in <code>exit_reason</code> if known. Keep <code>status</code> as <code>1</code> to retain an enabled historical record. Blank or omitted status fields default to <code>active</code> and <code>1</code>.</li>
                <li>Import Family Members using the same Family Number for siblings. Optionally include Student ID to assign different Mother, Father, or Guardian records to each sibling.</li>
                <li>Enrollment imports update the matching Student ID and Academic Year instead of creating duplicate yearly
                    records.</li>
                <li>Student photos use a storage path such as <code>students/STU-0001.jpg</code>; CSV import does not upload
                    image files.</li>
            </ul>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/studentDataTransfer.js')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const Swal = window.Swal;
            if (!Swal) return;
            const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            const dialog = Swal.mixin({
                background: dark ? '#1f2937' : '#ffffff',
                color: dark ? '#f8fafc' : '#1f2937',
                buttonsStyling: false,
                customClass: { confirmButton: 'btn btn-primary' },
            });
            @if ($errors->any())
                dialog.fire({icon: 'error', title: 'Import Could Not Start', text: {{ Illuminate\Support\Js::from($errors->first()) }}, confirmButtonText: 'Review File'});
            @elseif (session('import_report'))
                @php
                    $report = session('import_report');
                    $failedCount = count($report['failed']);
                    $recordLabel = match ($report['type']) { 'students'=>'Student Information', 'families'=>'Family', default=>'Enrollment' };
                    $resultTitle = $report['total'] === 0 ? 'No Rows Found' : ($failedCount ? ($report['imported'] ? 'Import Needs Review' : 'No Records Imported') : $recordLabel.' Import Complete');
                    $resultIcon = $report['total'] === 0 || $failedCount ? 'warning' : 'success';
                    $resultMessage = "Rows processed: {$report['total']}. Imported successfully: {$report['imported']}. Not imported: {$failedCount}.";
                @endphp
                dialog.fire({
                    icon: {{ Illuminate\Support\Js::from($resultIcon) }},
                    title: {{ Illuminate\Support\Js::from($resultTitle) }},
                    text: {{ Illuminate\Support\Js::from($resultMessage) }},
                    confirmButtonText: {{ Illuminate\Support\Js::from($failedCount ? 'Review Results' : 'View Results') }},
                }).then(() => {
                    const result = document.getElementById('import-result');
                    result?.scrollIntoView({behavior: 'smooth', block: 'start'});
                    result?.focus({preventScroll: true});
                });
            @endif
        });
    </script>
@endpush
