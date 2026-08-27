@extends('layouts.app')

@section('title', 'Student Import / Export')
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
    @if (session('import_report'))
        @php($importReport = session('import_report'))
        <div class="card border-warning mb-3">
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
                        <div class="d-grid gap-2"><a class="btn btn-outline-primary"
                                href="{{ route('student-data-transfer.template', $type) }}"><i
                                    class="ti ti-download me-1"></i>Download CSV Template</a><a
                                class="btn btn-outline-secondary"
                                href="{{ route('student-data-transfer.export', $type) }}"><i
                                    class="ti ti-file-download me-1"></i>Export Existing Data</a></div>
                        <form class="mt-3" method="POST" action="{{ route('student-data-transfer.import', $type) }}"
                            enctype="multipart/form-data">@csrf<label class="form-label">Import CSV</label><input
                                type="file" name="file" class="form-control" accept=".csv,.txt" required><button
                                class="btn btn-primary w-100 mt-2"><i class="ti ti-upload me-1"></i>Import
                                {{ $title }}</button></form>
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
                <li>Use the exact column names from the downloaded template. System-managed fields such as Student Number and status are completed automatically and are not included in the import templates.</li>
                <li>For linked fields, you may enter either the database ID or the exact English/Khmer name shown in the
                    system. IDs are recommended when names may be duplicated.</li>
                <li>Import Student Information before Enrollments.</li>
                <li>Import Family Members using the same Family Number for siblings. Optionally include Student ID to assign different Mother, Father, or Guardian records to each sibling.</li>
                <li>Enrollment imports update the matching Student ID and Academic Year instead of creating duplicate yearly
                    records.</li>
                <li>Student photos use a storage path such as <code>students/STU-0001.jpg</code>; CSV import does not upload
                    image files.</li>
            </ul>
        </div>
    </div>
@endsection
