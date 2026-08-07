@extends('layouts.app')

@section('title', 'Student Import / Export')
@section('page-header')
<div class="container-fluid"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">Students</div><h2 class="page-title">Student Data Import / Export</h2></div></div></div>
@endsection

@section('content')
<div class="alert alert-info"><i class="ti ti-info-circle me-2"></i>Use separate CSV files for Student Information, Enrollment History, and Family Members. Enrollment rows are linked by Student ID and Academic Year, allowing one student to have multiple academic-year records.</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row row-cards">
    @foreach([['students','Student Information','Student personal information and addresses.'],['enrollments','Student Enrollments','One row per student per academic year.'],['families','Mother, Father & Guardian','Shared family member records linked by Family Number.']] as [$type,$title,$description])
    <div class="col-md-4"><div class="card h-100"><div class="card-header"><h3 class="card-title">{{ $title }}</h3></div><div class="card-body"><p class="text-secondary small">{{ $description }}</p><div class="d-grid gap-2"><a class="btn btn-outline-primary" href="{{ route('student-data-transfer.template', $type) }}"><i class="ti ti-download me-1"></i>Download CSV Template</a><a class="btn btn-outline-secondary" href="{{ route('student-data-transfer.export', $type) }}"><i class="ti ti-file-download me-1"></i>Export Existing Data</a></div><form class="mt-3" method="POST" action="{{ route('student-data-transfer.import', $type) }}" enctype="multipart/form-data">@csrf<label class="form-label">Import CSV</label><input type="file" name="file" class="form-control" accept=".csv,.txt" required><button class="btn btn-primary w-100 mt-2"><i class="ti ti-upload me-1"></i>Import {{ $title }}</button></form></div></div></div>
    @endforeach
</div>
<div class="card mt-3"><div class="card-header"><h3 class="card-title">CSV Preparation Notes</h3></div><div class="card-body"><ul class="mb-0"><li>Use the exact column names from the downloaded template.</li><li>For linked fields, you may enter either the database ID or the exact English/Khmer name shown in the system. IDs are recommended when names may be duplicated.</li><li>Import Student Information before Enrollments.</li><li>Import Family Members using the same Family Number for siblings to share Mother and Father records.</li><li>Enrollment imports update the matching Student ID and Academic Year instead of creating duplicate yearly records.</li><li>Student photos use a storage path such as <code>students/STU-0001.jpg</code>; CSV import does not upload image files.</li></ul></div></div>
@endsection
