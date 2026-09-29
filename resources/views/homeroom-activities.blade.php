@extends('layouts.app')

@section('title', 'Homeroom Activities')

@section('page-header')
    <div class="container-fluid pt-3">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Student Affairs</div>
                <h2 class="page-title">Homeroom Activities</h2>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <style>
        .homeroom-summary-card { border: 1px solid rgba(74, 116, 210, .18); border-radius: 1rem; }
        .homeroom-student-row:hover { background: rgba(65, 118, 210, .06); }
        .homeroom-record-text { white-space: pre-line; }
        .homeroom-chip { border: 1px solid var(--tblr-border-color); border-radius: 999px; padding: .25rem .65rem; font-size: .78rem; }
    </style>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            <i class="ti ti-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible" role="alert">
            <div class="fw-semibold mb-1">Please check the form.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div class="d-flex align-items-start gap-3">
                    <span class="avatar avatar-lg bg-primary-lt text-primary"><i class="ti ti-home-star fs-1"></i></span>
                    <div>
                        <h3 class="mb-1">Homeroom Activities</h3>
                        <p class="text-secondary mb-2">Assign homeroom teachers to classes, record student or parent problems, and review student history.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="homeroom-chip">1 teacher can control up to 2 classes</span>
                            <span class="homeroom-chip">Classes may be in different campuses</span>
                            <span class="homeroom-chip">Vice School Principal can view all by permission</span>
                        </div>
                    </div>
                </div>
                <div class="text-lg-end">
                    <div class="text-secondary small">Selected Assignment</div>
                    <div class="fw-bold">
                        @if ($selectedAssignment)
                            {{ $selectedAssignment->campus?->campus_name_en ?? '-' }} - {{ $selectedAssignment->schoolClass?->class_name ?? '-' }}
                        @else
                            No class selected
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Filters</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('homeroom-activities.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Academic Year</label>
                    <select name="academic_year_id" class="form-select">
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}" @selected($selectedAcademicYearId == $year->id)>{{ $year->academic_year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Campus</label>
                    <select name="campus_id" class="form-select">
                        <option value="">All Campuses</option>
                        @foreach ($campuses as $campus)
                            <option value="{{ $campus->id }}" @selected($selectedCampusId == $campus->id)>{{ $campus->campus_name_en }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($canViewAll || $canManageAssignments)
                    <div class="col-md-3">
                        <label class="form-label">Homeroom Teacher</label>
                        <select name="staff_id" class="form-select">
                            <option value="">All Teachers</option>
                            @foreach ($teachers as $teacher)
                                <option value="{{ $teacher->id }}" @selected($selectedStaffId == $teacher->id)>{{ $teacher->name_en }}{{ $teacher->staff_code ? ' - '.$teacher->staff_code : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-3">
                    <label class="form-label">Class Assignment</label>
                    <select name="assignment_id" class="form-select">
                        <option value="">First active class</option>
                        @foreach ($assignments as $assignment)
                            <option value="{{ $assignment->id }}" @selected($selectedAssignmentId == $assignment->id)>
                                {{ $assignment->campus?->campus_name_en }} - {{ $assignment->schoolClass?->class_name }} - {{ $assignment->staff?->name_en ?? $assignment->teacher?->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button class="btn btn-primary"><i class="ti ti-filter me-1"></i>Apply</button>
                </div>
            </form>
        </div>
    </div>

    @if ($canManageAssignments)
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Assign Homeroom Teacher</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('homeroom-activities.assignments.store') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label">Teacher</label>
                        <select name="staff_id" class="form-select" required>
                            <option value="">Select Teacher</option>
                            @foreach ($teachers as $teacher)
                                <option value="{{ $teacher->id }}" @disabled(!$teacher->user)>
                                    {{ $teacher->name_en }}{{ $teacher->staff_code ? ' - '.$teacher->staff_code : '' }}{{ $teacher->user ? '' : ' (No user account)' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-hint">Teachers come from HRM Staff Management. A linked user account is required for login.</div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Academic Year</label>
                        <select name="academic_year_id" class="form-select" required>
                            @foreach ($academicYears as $year)
                                <option value="{{ $year->id }}" @selected($selectedAcademicYearId == $year->id)>{{ $year->academic_year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Campus</label>
                        <select name="campus_id" class="form-select" required>
                            <option value="">Select Campus</option>
                            @foreach ($campuses as $campus)
                                <option value="{{ $campus->id }}">{{ $campus->campus_name_en }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Grade</label>
                        <select name="grade_id" class="form-select" required>
                            <option value="">Select Grade</option>
                            @foreach ($grades as $grade)
                                <option value="{{ $grade->id }}">{{ $grade->grade_short_name ?: $grade->grade }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Class</label>
                        <select name="class_id" class="form-select" required>
                            <option value="">Select Class</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}">{{ $class->class_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Active</label>
                        <select name="status" class="form-select">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div class="col-12 text-secondary small">
                        A teacher can have only 2 active homeroom classes in the same academic year. The classes may belong to different campuses.
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title">Homeroom Assignments</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Class</th>
                                <th>Records</th>
                                <th>Status</th>
                                @if ($canManageAssignments)<th></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($assignments as $assignment)
                                <tr class="{{ $selectedAssignmentId == $assignment->id ? 'table-primary' : '' }}">
                                    <td>
                                        <div class="fw-semibold">{{ $assignment->staff?->name_en ?? $assignment->teacher?->name ?? '-' }}</div>
                                        <div class="text-secondary small">{{ $assignment->staff?->staff_code ?? $assignment->teacher?->staff_id ?? '' }}</div>
                                    </td>
                                    <td>
                                        <a href="{{ route('homeroom-activities.index', ['academic_year_id' => $assignment->academic_year_id, 'campus_id' => $assignment->campus_id, 'assignment_id' => $assignment->id]) }}" class="fw-semibold">
                                            {{ $assignment->campus?->campus_name_en ?? '-' }} - {{ $assignment->schoolClass?->class_name ?? '-' }}
                                        </a>
                                        <div class="text-secondary small">{{ $assignment->academicYear?->academic_year ?? '-' }} · {{ $assignment->grade?->grade_short_name ?: $assignment->grade?->grade }}</div>
                                    </td>
                                    <td>{{ $assignment->records_count }}</td>
                                    <td><span class="badge {{ $assignment->status ? 'bg-success-lt' : 'bg-secondary-lt' }}">{{ $assignment->status ? 'Active' : 'Inactive' }}</span></td>
                                    @if ($canManageAssignments)
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('homeroom-activities.assignments.update', $assignment) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $assignment->status ? 0 : 1 }}">
                                                <button class="btn btn-sm btn-outline-primary">{{ $assignment->status ? 'Disable' : 'Enable' }}</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ $canManageAssignments ? 5 : 4 }}" class="text-center text-secondary py-4">No homeroom assignments found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title">Students in Selected Homeroom Class</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Group</th>
                                <th>History</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($selectedAssignment && $studentEnrollments instanceof \Illuminate\Contracts\Pagination\Paginator)
                                @forelse ($studentEnrollments as $enrollment)
                                    <tr class="homeroom-student-row">
                                        <td class="fw-semibold">{{ $enrollment->student?->student_id ?? '-' }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $enrollment->student?->full_name_en ?? '-' }}</div>
                                            <div class="text-secondary small">{{ $enrollment->student?->full_name_kh ?? '' }}</div>
                                        </td>
                                        <td>{{ $enrollment->campus?->campus_name_en }} - {{ $enrollment->schoolClass?->class_name }}</td>
                                        <td>{{ $enrollment->schoolGroup?->group_name ?? '-' }}</td>
                                        <td>
                                            <span class="badge bg-blue-lt">{{ $studentHistoryCounts->get($enrollment->student_id, 0) }} records</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-secondary py-4">No students found for this assignment.</td></tr>
                                @endforelse
                            @else
                                <tr><td colspan="5" class="text-center text-secondary py-4">Select a homeroom assignment to view students.</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                @if ($selectedAssignment && $studentEnrollments instanceof \Illuminate\Contracts\Pagination\Paginator)
                    <div class="card-footer">{{ $studentEnrollments->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    @if ($canCreateRecords && $selectedAssignment)
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Record Student / Parent Problem</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('homeroom-activities.records.store') }}" class="row g-3">
                    @csrf
                    <input type="hidden" name="assignment_id" value="{{ $selectedAssignment->id }}">
                    <div class="col-md-4">
                        <label class="form-label">Student</label>
                        <select name="student_id" class="form-select" required>
                            <option value="">Select Student</option>
                            @if ($recordStudentOptions->isNotEmpty())
                                @foreach ($recordStudentOptions as $enrollment)
                                    <option value="{{ $enrollment->student_id }}">{{ $enrollment->student?->student_id }} - {{ $enrollment->student?->full_name_en }}</option>
                                @endforeach
                            @endif
                        </select>
                        <div class="form-hint">Students are loaded from the selected homeroom class.</div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Record Type</label>
                        <select name="record_type" class="form-select" required>
                            <option value="student_meeting">Student Meeting</option>
                            <option value="parent_meeting">Parent Meeting</option>
                            <option value="phone_call">Phone Call</option>
                            <option value="follow_up">Follow Up</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Meeting With</label>
                        <select name="meeting_with" class="form-select" required>
                            <option value="student">Student</option>
                            <option value="parent">Parent</option>
                            <option value="student_parent">Student & Parent</option>
                            <option value="guardian">Guardian</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="open">Open</option>
                            <option value="follow_up">Follow Up</option>
                            <option value="resolved">Resolved</option>
                            <option value="escalated">Escalated</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Recorded On</label>
                        <input type="date" name="recorded_on" value="{{ now()->toDateString() }}" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Problem Type</label>
                        <input type="text" name="problem_type" class="form-control" placeholder="Attendance, behavior, study, parent concern...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Follow Up Date</label>
                        <input type="date" name="follow_up_on" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Problem Description</label>
                        <textarea name="problem_description" rows="3" class="form-control" required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Action Taken</label>
                        <textarea name="action_taken" rows="3" class="form-control"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Parent Feedback / Resolution Note</label>
                        <textarea name="parent_feedback" rows="3" class="form-control"></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save Record</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Student Problem History</h3>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Problem</th>
                        <th>Action / Follow Up</th>
                        <th>Status</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td>{{ optional($record->recorded_on)->format('d-M-Y') }}</td>
                            <td>
                                <div class="fw-semibold">{{ $record->student?->full_name_en ?? '-' }}</div>
                                <div class="text-secondary small">{{ $record->student?->student_id ?? '' }} {{ $record->student?->full_name_kh ? '· '.$record->student?->full_name_kh : '' }}</div>
                            </td>
                            <td>{{ $record->campus?->campus_name_en }} - {{ $record->schoolClass?->class_name }}{{ $record->schoolGroup?->group_name ? '-'.$record->schoolGroup?->group_name : '' }}</td>
                            <td>
                                <div class="fw-semibold">{{ $record->problem_type ?: ucwords(str_replace('_', ' ', $record->record_type)) }}</div>
                                <div class="text-secondary homeroom-record-text">{{ $record->problem_description }}</div>
                            </td>
                            <td>
                                <div class="homeroom-record-text">{{ $record->action_taken ?: '-' }}</div>
                                @if ($record->follow_up_on)
                                    <div class="text-warning small">Follow up: {{ $record->follow_up_on->format('d-M-Y') }}</div>
                                @endif
                            </td>
                            <td><span class="badge bg-primary-lt">{{ ucwords(str_replace('_', ' ', $record->status)) }}</span></td>
                            <td>{{ $record->creator?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-secondary py-4">No homeroom records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $records->links() }}</div>
    </div>
@endsection
