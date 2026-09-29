@extends('layouts.app')

@section('title', 'Staff Management')

@section('page-header')
    <div class="container-fluid pt-3">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">HRM</div>
                <h2 class="page-title">Staff Management</h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <form method="GET" class="d-flex gap-2 align-items-center">
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search staff">
                    </div>
                    @if ($canManageStaff)
                        <a class="btn btn-primary" href="{{ route('staff-management.index', ['create' => 1]) }}"><i class="ti ti-plus me-1"></i>New Staff</a>
                    @endif
                </form>
            </div>
        </div>
    </div>
@endsection

@section('content')
    @php
        $categoryLabels = ['staff' => 'Staff', 'teacher' => 'Teacher', 'staff_teacher' => 'Staff & Teacher'];
        $typeLabels = ['PT' => 'Part-Time', 'SFT' => 'Semi Full-Time', 'FT' => 'Full-Time'];
        $statusLabels = ['active' => 'Active', 'resigned' => 'Resigned', 'suspended' => 'Suspended', 'on_leave' => 'On Leave'];
        $maritalLabels = ['single' => 'Single', 'married' => 'Married', 'divorced' => 'Divorced', 'widowed' => 'Widowed'];
        $experienceRows = old('experiences', $editStaff?->experiences?->map(fn ($item) => [
            'company_name' => $item->company_name,
            'position' => $item->position,
            'employment_type' => $item->employment_type,
            'started_on' => $item->started_on?->format('Y-m-d'),
            'ended_on' => $item->ended_on?->format('Y-m-d'),
            'is_current' => $item->is_current ? '1' : '0',
            'responsibilities' => $item->responsibilities,
        ])->toArray() ?? []);
        $educationRows = old('educations', $editStaff?->educations?->map(fn ($item) => [
            'institution_name' => $item->institution_name,
            'degree' => $item->degree,
            'field_of_study' => $item->field_of_study,
            'started_on' => $item->started_on?->format('Y-m-d'),
            'ended_on' => $item->ended_on?->format('Y-m-d'),
            'grade_or_result' => $item->grade_or_result,
            'notes' => $item->notes,
        ])->toArray() ?? []);
        $experienceRows = count($experienceRows) ? $experienceRows : [['company_name' => '', 'position' => '', 'employment_type' => '', 'started_on' => '', 'ended_on' => '', 'is_current' => '0', 'responsibilities' => '']];
        $educationRows = count($educationRows) ? $educationRows : [['institution_name' => '', 'degree' => '', 'field_of_study' => '', 'started_on' => '', 'ended_on' => '', 'grade_or_result' => '', 'notes' => '']];
    @endphp

    <style>
        .staff-action-group { display: inline-flex; gap: .35rem; justify-content: flex-end; flex-wrap: wrap; }
        .staff-repeat-card { border: 1px solid var(--tblr-border-color); border-radius: .85rem; padding: 1rem; background: rgba(74, 116, 210, .035); }
        .staff-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .85rem; }
        .staff-detail-item { border: 1px solid var(--tblr-border-color); border-radius: .75rem; padding: .75rem; }
        .staff-detail-label { color: var(--tblr-secondary); font-size: .75rem; text-transform: uppercase; }
    </style>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible" role="alert"><i class="ti ti-circle-check me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible" role="alert"><div class="fw-semibold mb-1">Please check the form.</div><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card mb-3"><div class="card-body"><div class="d-flex align-items-start gap-3"><span class="avatar avatar-lg bg-primary-lt text-primary"><i class="ti ti-id-badge-2 fs-1"></i></span><div><h3 class="mb-1">Staff Information</h3><p class="text-secondary mb-0">Manage staff profiles, teachers, working experience, education, campus assignment, and login linkage.</p></div></div></div></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>Staff</th><th>Category</th><th>Type</th><th>Position</th><th>Campuses</th><th>User Account</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                    @forelse ($staff as $person)
                        <tr>
                            <td><div class="d-flex align-items-center gap-2"><span class="avatar" @if($person->photo_path) style="background-image:url('{{ asset('storage/'.$person->photo_path) }}')" @endif>@unless($person->photo_path){{ strtoupper(substr($person->name_en, 0, 1)) }}@endunless</span><div><div class="fw-semibold">{{ $person->name_en }}</div><div class="text-secondary small">{{ $person->staff_code }}{{ $person->name_kh ? ' - '.$person->name_kh : '' }}</div></div></div></td>
                            <td>{{ $categoryLabels[$person->staff_category] ?? $person->staff_category }}</td>
                            <td>{{ $typeLabels[$person->employment_type] ?? $person->employment_type }}</td>
                            <td><div>{{ $person->position?->name ?? '-' }}</div><div class="text-secondary small">{{ $person->department?->name ?? '' }}</div></td>
                            <td>{{ $person->campuses->pluck('campus_name_en')->join(', ') ?: '-' }}</td>
                            <td>@if ($person->user)<span class="badge bg-success-lt">Linked: {{ $person->user->username }}</span>@else<span class="badge bg-secondary-lt">No login</span>@endif</td>
                            <td><span class="badge {{ $person->employment_status === 'active' ? 'bg-success-lt' : 'bg-warning-lt' }}">{{ $statusLabels[$person->employment_status] ?? $person->employment_status }}</span></td>
                            <td class="text-end"><div class="staff-action-group"><a class="btn btn-sm btn-outline-info" href="{{ route('staff-management.index', ['view' => $person->id]) }}"><i class="ti ti-eye"></i> View</a>@if ($canManageStaff)<a class="btn btn-sm btn-outline-primary" href="{{ route('staff-management.index', ['edit' => $person->id]) }}"><i class="ti ti-edit"></i> Edit</a>@endif @if ($canDeleteStaff)<form method="POST" action="{{ route('staff-management.delete', $person) }}" onsubmit="return confirm('Delete this staff profile?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash"></i> Delete</button></form>@endif</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-secondary py-4">No staff profiles found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $staff->links() }}</div>
    </div>

    <div class="modal modal-blur fade" id="staffViewModal" tabindex="-1" aria-hidden="true" data-open-on-load="{{ $viewStaff ? '1' : '0' }}">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Staff Profile</h5><a class="btn-close" href="{{ route('staff-management.index') }}"></a></div>
            @if ($viewStaff)
                <div class="modal-body">
                    <div class="d-flex align-items-center gap-3 mb-4"><span class="avatar avatar-xl" @if($viewStaff->photo_path) style="background-image:url('{{ asset('storage/'.$viewStaff->photo_path) }}')" @endif>@unless($viewStaff->photo_path){{ strtoupper(substr($viewStaff->name_en, 0, 1)) }}@endunless</span><div><h2 class="mb-1">{{ $viewStaff->name_en }}</h2><div class="text-secondary">{{ $viewStaff->staff_code }}{{ $viewStaff->name_kh ? ' - '.$viewStaff->name_kh : '' }}</div></div></div>
                    <div class="staff-detail-grid mb-4">
                        <div class="staff-detail-item"><div class="staff-detail-label">Category</div><div>{{ $categoryLabels[$viewStaff->staff_category] ?? $viewStaff->staff_category }}</div></div>
                        <div class="staff-detail-item"><div class="staff-detail-label">Type</div><div>{{ $typeLabels[$viewStaff->employment_type] ?? $viewStaff->employment_type }}</div></div>
                        <div class="staff-detail-item"><div class="staff-detail-label">Position</div><div>{{ $viewStaff->position?->name ?? '-' }}</div></div>
                        <div class="staff-detail-item"><div class="staff-detail-label">Department</div><div>{{ $viewStaff->department?->name ?? '-' }}</div></div>
                        <div class="staff-detail-item"><div class="staff-detail-label">Phone</div><div>{{ $viewStaff->phone ?? '-' }}</div></div>
                        <div class="staff-detail-item"><div class="staff-detail-label">Email</div><div>{{ $viewStaff->email ?? '-' }}</div></div>
                        <div class="staff-detail-item"><div class="staff-detail-label">Campuses</div><div>{{ $viewStaff->campuses->pluck('campus_name_en')->join(', ') ?: '-' }}</div></div>
                        <div class="staff-detail-item"><div class="staff-detail-label">Emergency Contact</div><div>{{ $viewStaff->emergency_contact_name ?: '-' }}{{ $viewStaff->emergency_contact_phone ? ' - '.$viewStaff->emergency_contact_phone : '' }}</div></div>
                    </div>
                    <h3><i class="ti ti-briefcase me-1"></i>Working Experience</h3>
                    <div class="table-responsive mb-4"><table class="table table-sm"><thead><tr><th>Company</th><th>Position</th><th>Period</th><th>Responsibilities</th></tr></thead><tbody>@forelse ($viewStaff->experiences as $experience)<tr><td>{{ $experience->company_name }}</td><td>{{ $experience->position ?: '-' }}</td><td>{{ $experience->started_on?->format('d-M-Y') ?: '-' }} - {{ $experience->is_current ? 'Present' : ($experience->ended_on?->format('d-M-Y') ?: '-') }}</td><td>{{ $experience->responsibilities ?: '-' }}</td></tr>@empty<tr><td colspan="4" class="text-center text-secondary">No working experience recorded.</td></tr>@endforelse</tbody></table></div>
                    <h3><i class="ti ti-school me-1"></i>Education</h3>
                    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Institution</th><th>Degree</th><th>Field</th><th>Period</th><th>Result</th></tr></thead><tbody>@forelse ($viewStaff->educations as $education)<tr><td>{{ $education->institution_name }}</td><td>{{ $education->degree ?: '-' }}</td><td>{{ $education->field_of_study ?: '-' }}</td><td>{{ $education->started_on?->format('d-M-Y') ?: '-' }} - {{ $education->ended_on?->format('d-M-Y') ?: '-' }}</td><td>{{ $education->grade_or_result ?: '-' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-secondary">No education recorded.</td></tr>@endforelse</tbody></table></div>
                </div>
                <div class="modal-footer">@if ($canManageStaff)<a class="btn btn-primary" href="{{ route('staff-management.index', ['edit' => $viewStaff->id]) }}"><i class="ti ti-edit me-1"></i>Edit Staff</a>@endif<a class="btn" href="{{ route('staff-management.index') }}">Close</a></div>
            @endif
        </div></div>
    </div>

    <div class="modal modal-blur fade" id="staffModal" tabindex="-1" aria-hidden="true" data-open-on-load="{{ $editStaff || ($createStaff ?? false) ? '1' : '0' }}">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">{{ $editStaff ? 'Edit Staff' : 'Create Staff' }}</h5><a class="btn-close" href="{{ route('staff-management.index') }}"></a></div>
            <form method="POST" action="{{ route('staff-management.save') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="staff_id" value="{{ $editStaff?->id }}">
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#staff-info-tab" type="button">Staff Information</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#staff-experience-tab" type="button">Working Experience</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#staff-education-tab" type="button">Education</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#staff-other-tab" type="button">Other</button></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="staff-info-tab"><div class="row g-3">
                            <div class="col-md-3"><label class="form-label">Staff ID</label><input class="form-control" name="staff_code" value="{{ old('staff_code', $editStaff?->staff_code) }}" required></div>
                            <div class="col-md-3"><label class="form-label">Staff Name English</label><input class="form-control" name="name_en" value="{{ old('name_en', $editStaff?->name_en) }}" required></div>
                            <div class="col-md-3"><label class="form-label">Staff Name Khmer</label><input class="form-control" name="name_kh" value="{{ old('name_kh', $editStaff?->name_kh) }}"></div>
                            <div class="col-md-3"><label class="form-label">Photo</label><input class="form-control" type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div>
                            <div class="col-md-3"><label class="form-label">Gender</label><select class="form-select" name="gender"><option value=""></option>@foreach (['Male', 'Female', 'Other'] as $gender)<option value="{{ $gender }}" @selected(old('gender', $editStaff?->gender) === $gender)>{{ $gender }}</option>@endforeach</select></div>
                            <div class="col-md-3"><label class="form-label">Nationality</label><input class="form-control" name="nationality" value="{{ old('nationality', $editStaff?->nationality) }}"></div>
                            <div class="col-md-3"><label class="form-label">Date of Birth</label><input class="form-control" type="date" name="date_of_birth" value="{{ old('date_of_birth', $editStaff?->date_of_birth?->format('Y-m-d')) }}"></div>
                            <div class="col-md-3"><label class="form-label">Marital Status</label><select class="form-select" name="marital_status"><option value=""></option>@foreach ($maritalLabels as $value => $label)<option value="{{ $value }}" @selected(old('marital_status', $editStaff?->marital_status) === $value)>{{ $label }}</option>@endforeach</select></div>
                            <div class="col-md-3"><label class="form-label">Phone</label><input class="form-control" name="phone" value="{{ old('phone', $editStaff?->phone) }}"></div>
                            <div class="col-md-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email', $editStaff?->email) }}"></div>
                            <div class="col-md-6"><label class="form-label">Current Address</label><input class="form-control" name="current_address" value="{{ old('current_address', $editStaff?->current_address) }}"></div>
                            <div class="col-md-3"><label class="form-label">Staff Category</label><select class="form-select" name="staff_category" required>@foreach ($categoryLabels as $value => $label)<option value="{{ $value }}" @selected(old('staff_category', $editStaff?->staff_category ?? 'staff') === $value)>{{ $label }}</option>@endforeach</select></div>
                            <div class="col-md-3"><label class="form-label">Staff Type</label><select class="form-select" name="employment_type" required>@foreach ($typeLabels as $value => $label)<option value="{{ $value }}" @selected(old('employment_type', $editStaff?->employment_type ?? 'FT') === $value)>{{ $label }} ({{ $value }})</option>@endforeach</select></div>
                            <div class="col-md-3"><label class="form-label">Employment Status</label><select class="form-select" name="employment_status" required>@foreach ($statusLabels as $value => $label)<option value="{{ $value }}" @selected(old('employment_status', $editStaff?->employment_status ?? 'active') === $value)>{{ $label }}</option>@endforeach</select></div>
                            <div class="col-md-3"><label class="form-label">System Status</label><select class="form-select" name="status" required><option value="1" @selected(old('status', $editStaff?->status ?? 1) == 1)>Active</option><option value="0" @selected(old('status', $editStaff?->status) == 0)>Inactive</option></select></div>
                            <div class="col-md-3"><label class="form-label">Department</label><select class="form-select" name="department_id"><option value=""></option>@foreach ($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id', $editStaff?->department_id) == $department->id)>{{ $department->name }}</option>@endforeach</select></div>
                            <div class="col-md-3"><label class="form-label">Position</label><select class="form-select" name="position_id"><option value=""></option>@foreach ($positions as $position)<option value="{{ $position->id }}" @selected(old('position_id', $editStaff?->position_id) == $position->id)>{{ $position->name }}</option>@endforeach</select></div>
                            <div class="col-md-3"><label class="form-label">Joined Date</label><input class="form-control" type="date" name="joined_on" value="{{ old('joined_on', $editStaff?->joined_on?->format('Y-m-d')) }}"></div>
                            <div class="col-md-3"><label class="form-label">End Date</label><input class="form-control" type="date" name="ended_on" value="{{ old('ended_on', $editStaff?->ended_on?->format('Y-m-d')) }}"></div>
                            <div class="col-md-4"><label class="form-label">Primary Campus</label><select class="form-select" name="primary_campus_id"><option value=""></option>@foreach ($campuses as $campus)<option value="{{ $campus->id }}" @selected(old('primary_campus_id', $editStaff?->primary_campus_id) == $campus->id)>{{ $campus->campus_name_en }}</option>@endforeach</select></div>
                            <div class="col-md-8"><label class="form-label">Working Campuses</label><select class="form-select" name="campuses[]" multiple size="5">@foreach ($campuses as $campus)<option value="{{ $campus->id }}" @selected(collect(old('campuses', $editStaff?->campuses->pluck('id')->all() ?? []))->contains($campus->id))>{{ $campus->campus_name_en }}</option>@endforeach</select><div class="form-hint">A teacher can work in more than one campus.</div></div>
                        </div></div>

                        <div class="tab-pane" id="staff-experience-tab"><div id="staffExperienceRows" class="vstack gap-3">
                            @foreach ($experienceRows as $index => $experience)
                                <div class="staff-repeat-card" data-repeat-row><div class="row g-3">
                                    <div class="col-md-4"><label class="form-label">Company / School</label><input class="form-control" name="experiences[{{ $index }}][company_name]" value="{{ $experience['company_name'] ?? '' }}"></div>
                                    <div class="col-md-3"><label class="form-label">Position</label><input class="form-control" name="experiences[{{ $index }}][position]" value="{{ $experience['position'] ?? '' }}"></div>
                                    <div class="col-md-2"><label class="form-label">Type</label><input class="form-control" name="experiences[{{ $index }}][employment_type]" value="{{ $experience['employment_type'] ?? '' }}"></div>
                                    <div class="col-md-3 d-flex align-items-end"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="experiences[{{ $index }}][is_current]" value="1" @checked(!empty($experience['is_current']) && $experience['is_current'] !== '0')><span class="form-check-label">Current job</span></label></div>
                                    <div class="col-md-3"><label class="form-label">Start Date</label><input class="form-control" type="date" name="experiences[{{ $index }}][started_on]" value="{{ $experience['started_on'] ?? '' }}"></div>
                                    <div class="col-md-3"><label class="form-label">End Date</label><input class="form-control" type="date" name="experiences[{{ $index }}][ended_on]" value="{{ $experience['ended_on'] ?? '' }}"></div>
                                    <div class="col-md-6"><label class="form-label">Responsibilities</label><input class="form-control" name="experiences[{{ $index }}][responsibilities]" value="{{ $experience['responsibilities'] ?? '' }}"></div>
                                </div></div>
                            @endforeach
                        </div><button type="button" class="btn btn-outline-primary mt-3" data-add-experience><i class="ti ti-plus me-1"></i>Add Experience</button></div>

                        <div class="tab-pane" id="staff-education-tab"><div id="staffEducationRows" class="vstack gap-3">
                            @foreach ($educationRows as $index => $education)
                                <div class="staff-repeat-card" data-repeat-row><div class="row g-3">
                                    <div class="col-md-4"><label class="form-label">School / University</label><input class="form-control" name="educations[{{ $index }}][institution_name]" value="{{ $education['institution_name'] ?? '' }}"></div>
                                    <div class="col-md-3"><label class="form-label">Degree</label><input class="form-control" name="educations[{{ $index }}][degree]" value="{{ $education['degree'] ?? '' }}"></div>
                                    <div class="col-md-3"><label class="form-label">Field of Study</label><input class="form-control" name="educations[{{ $index }}][field_of_study]" value="{{ $education['field_of_study'] ?? '' }}"></div>
                                    <div class="col-md-2"><label class="form-label">Result</label><input class="form-control" name="educations[{{ $index }}][grade_or_result]" value="{{ $education['grade_or_result'] ?? '' }}"></div>
                                    <div class="col-md-3"><label class="form-label">Start Date</label><input class="form-control" type="date" name="educations[{{ $index }}][started_on]" value="{{ $education['started_on'] ?? '' }}"></div>
                                    <div class="col-md-3"><label class="form-label">End Date</label><input class="form-control" type="date" name="educations[{{ $index }}][ended_on]" value="{{ $education['ended_on'] ?? '' }}"></div>
                                    <div class="col-md-6"><label class="form-label">Notes</label><input class="form-control" name="educations[{{ $index }}][notes]" value="{{ $education['notes'] ?? '' }}"></div>
                                </div></div>
                            @endforeach
                        </div><button type="button" class="btn btn-outline-primary mt-3" data-add-education><i class="ti ti-plus me-1"></i>Add Education</button></div>

                        <div class="tab-pane" id="staff-other-tab"><div class="row g-3">
                            <div class="col-md-4"><label class="form-label">Emergency Contact Name</label><input class="form-control" name="emergency_contact_name" value="{{ old('emergency_contact_name', $editStaff?->emergency_contact_name) }}"></div>
                            <div class="col-md-4"><label class="form-label">Emergency Contact Phone</label><input class="form-control" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $editStaff?->emergency_contact_phone) }}"></div>
                            <div class="col-md-4"><label class="form-label">Relationship</label><input class="form-control" name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $editStaff?->emergency_contact_relationship) }}"></div>
                            <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="4">{{ old('notes', $editStaff?->notes) }}</textarea></div>
                        </div></div>
                    </div>
                </div>
                <div class="modal-footer"><a class="btn me-auto" href="{{ route('staff-management.index') }}">Cancel</a><button class="btn btn-primary">{{ $editStaff ? 'Update Staff' : 'Create Staff' }}</button></div>
            </form>
        </div></div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    ['staffModal', 'staffViewModal'].forEach((id) => {
        const modal = document.getElementById(id);
        if (modal && modal.dataset.openOnLoad === '1' && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(modal).show();
    });
    const experienceRows = document.getElementById('staffExperienceRows');
    const educationRows = document.getElementById('staffEducationRows');
    document.querySelector('[data-add-experience]')?.addEventListener('click', () => {
        const index = experienceRows.querySelectorAll('[data-repeat-row]').length;
        experienceRows.insertAdjacentHTML('beforeend', `<div class="staff-repeat-card" data-repeat-row><div class="row g-3"><div class="col-md-4"><label class="form-label">Company / School</label><input class="form-control" name="experiences[${index}][company_name]"></div><div class="col-md-3"><label class="form-label">Position</label><input class="form-control" name="experiences[${index}][position]"></div><div class="col-md-2"><label class="form-label">Type</label><input class="form-control" name="experiences[${index}][employment_type]"></div><div class="col-md-3 d-flex align-items-end"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="experiences[${index}][is_current]" value="1"><span class="form-check-label">Current job</span></label></div><div class="col-md-3"><label class="form-label">Start Date</label><input class="form-control" type="date" name="experiences[${index}][started_on]"></div><div class="col-md-3"><label class="form-label">End Date</label><input class="form-control" type="date" name="experiences[${index}][ended_on]"></div><div class="col-md-6"><label class="form-label">Responsibilities</label><input class="form-control" name="experiences[${index}][responsibilities]"></div></div></div>`);
    });
    document.querySelector('[data-add-education]')?.addEventListener('click', () => {
        const index = educationRows.querySelectorAll('[data-repeat-row]').length;
        educationRows.insertAdjacentHTML('beforeend', `<div class="staff-repeat-card" data-repeat-row><div class="row g-3"><div class="col-md-4"><label class="form-label">School / University</label><input class="form-control" name="educations[${index}][institution_name]"></div><div class="col-md-3"><label class="form-label">Degree</label><input class="form-control" name="educations[${index}][degree]"></div><div class="col-md-3"><label class="form-label">Field of Study</label><input class="form-control" name="educations[${index}][field_of_study]"></div><div class="col-md-2"><label class="form-label">Result</label><input class="form-control" name="educations[${index}][grade_or_result]"></div><div class="col-md-3"><label class="form-label">Start Date</label><input class="form-control" type="date" name="educations[${index}][started_on]"></div><div class="col-md-3"><label class="form-label">End Date</label><input class="form-control" type="date" name="educations[${index}][ended_on]"></div><div class="col-md-6"><label class="form-label">Notes</label><input class="form-control" name="educations[${index}][notes]"></div></div></div>`);
    });
});
</script>
@endpush
