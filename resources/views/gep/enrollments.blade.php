@extends('layouts.app')
@section('title','GEP Student Enrollment')
@section('content')
<div id="gep-enrollments" data-url="{{ url('/gep/students/enrollment') }}" data-options='@json($options)' data-permissions='@json($permissions)'>
    <div class="card shadow-sm mb-3 gep-enrollment-filters">
        <div class="card-header gep-settings-header py-2"><h2 class="card-title text-white mb-0">Data Filters</h2><button class="btn btn-sm btn-outline-light ms-auto" type="button" id="gep-filter-toggle" aria-expanded="true" aria-controls="gep-filter-body"><i class="ti ti-adjustments-horizontal me-1"></i><span>Collapse</span></button></div>
        <div class="card-body" id="gep-filter-body">
            <div class="row g-2">
                @foreach(['academic_year_id'=>'Academic Year','term_assignment_id'=>'Term','campus_id'=>'Campus','level_id'=>'Level','class_id'=>'Class','class_type_id'=>'Class Type','group_id'=>'Group','enrollment_status'=>'Status'] as $name=>$label)
                    <div class="col-6 col-md-4 col-xl-3"><div class="gep-field"><select id="gep-filter-{{ $name }}" data-filter="{{ $name }}" aria-label="{{ $label }}"></select><label for="gep-filter-{{ $name }}">{{ $label }}</label></div></div>
                @endforeach
                <div class="col-12 col-md-9"><div class="gep-field"><input type="search" id="gep-enrollment-search" class="form-control" placeholder=" " maxlength="150" data-search-normalized="true"><label for="gep-enrollment-search">Search name, Student ID or GEP number</label></div></div>
                <div class="col-12 col-md-3"><button class="btn btn-outline-secondary h-100 w-100" type="button" id="gep-filter-reset"><i class="ti ti-refresh me-1"></i>Reset Filters</button></div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-3" aria-live="polite">
        @foreach(['total'=>['Total Enrollments','users','primary'],'active'=>['Active Students','user-check','green'],'pending'=>['Pending Students','clock','orange'],'completed'=>['Completed Students','certificate','azure']] as $key=>$card)
            <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body py-3 d-flex align-items-center gap-3"><span class="avatar bg-{{ $card[2] }}-lt"><i class="ti ti-{{ $card[1] }} fs-2"></i></span><div><div class="text-secondary small">{{ $card[0] }}</div><strong class="fs-1" id="gep-total-{{ $key }}">0</strong></div></div></div></div>
        @endforeach
    </div>
    <div class="card shadow-sm">
        <div class="card-header gep-settings-header py-3 gap-2"><div><h1 class="card-title text-white mb-0">GEP Student Enrollment</h1><div class="small text-white-50">Manage students by academic year and term</div></div>
            @if($permissions['create'])<button class="btn btn-light ms-auto" type="button" id="gep-enrollment-add"><i class="ti ti-user-plus me-2"></i>New Enrollment</button>@endif
        </div>
        <div id="gep-list-error" class="alert alert-danger m-3 d-none" role="alert"></div>
        <div class="table-responsive gep-enrollment-table"><table class="table table-vcenter card-table mb-0"><thead><tr><th>No.</th><th>Student</th><th>Contact</th><th>Academic Year / Term</th><th>Study Information</th><th>Enrollment</th><th class="text-end">Actions</th></tr></thead><tbody id="gep-enrollment-rows"><tr><td colspan="7" class="text-center py-5">Loading enrollments…</td></tr></tbody></table></div>
        <div class="card-footer"><select id="gep-enrollment-per-page" class="d-none" aria-label="Records per page"><option>10</option><option>25</option><option>50</option><option>100</option><option>all</option></select><div id="gep-enrollment-pagination"></div></div>
    </div>
    <div class="modal modal-blur fade gep-compact-registration" id="gep-enrollment-modal" tabindex="-1" aria-labelledby="gep-enrollment-title" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" id="gep-enrollment-form">
            <div class="modal-header gep-settings-header"><h2 class="modal-title text-white" id="gep-enrollment-title">Register GEP Student</h2><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="gep-form-error" role="alert"></div>
                @if(!$options['assignments']->contains(fn($assignment)=>$assignment->academicYear?->status && $assignment->term?->status && $assignment->lifecycle_status!=='finished'))
                    <div class="alert alert-info">Set up an active academic year and assign a term in GEP Settings before enrolling students. Start the term for Active enrollment, or use Pending for a future term.</div>
                @endif
                <div class="gep-registration-layout" id="gep-registration-layout">
                <nav class="gep-registration-modes" id="gep-source-modes" aria-label="Student registration type">
                    <div class="gep-registration-nav-title">Register Student</div>
                    <button type="button" class="btn active" data-source="existing" aria-pressed="true"><span><i class="ti ti-school me-2"></i>Western / Existing Student</span><i class="ti ti-chevron-right"></i></button>
                    <button type="button" class="btn" data-source="new" aria-pressed="false"><span><i class="ti ti-user-plus me-2"></i>New Student</span><i class="ti ti-chevron-right"></i></button>
                </nav>
                <div class="gep-registration-content">
                <section class="gep-enrollment-section mb-3" id="gep-existing-panel"><h3>Find Western / Existing Student</h3><div class="p-3">
                    <div class="row g-3">
                        @foreach(['academic_year_id'=>'Academic Year','campus_id'=>'Campus','class_id'=>'Grade'] as $name=>$label)
                            <div class="col-12 col-sm-6 col-xl-3"><div class="gep-field"><select id="gep-lookup-{{ $name }}" aria-label="{{ $label }}" data-filter="lookup"></select><label for="gep-lookup-{{ $name }}">{{ $label }}</label></div></div>
                        @endforeach
                        <div class="col-12 col-sm-6 col-xl-3 gep-student-lookup"><div class="gep-field has-value"><input type="search" id="gep-student-search" class="form-control" placeholder="Student name or ID" autocomplete="off" maxlength="100" data-search-normalized="true" aria-expanded="false" aria-controls="gep-student-results"><label for="gep-student-search">Select Student <span class="text-danger">*</span></label></div>
                            <div id="gep-student-results" class="gep-student-results d-none" role="group" aria-label="Matching students"></div>
                        </div>
                    </div>
                    <input type="hidden" name="student_id" id="gep-selected-student-id">
                    <input type="hidden" name="gep_student_id" id="gep-selected-gep-student-id">
                    <div id="gep-selected-student" class="alert alert-primary mt-3 mb-0 d-none" aria-live="polite"></div>
                </div></section>
                <section class="gep-enrollment-section mb-3" id="gep-student-information"><h3>Student Information</h3><div class="p-3">
                    <div class="gep-photo-upload mb-3">
                        <input type="file" id="gep-student-photo" name="photo" accept="image/jpeg,image/png,image/webp" class="visually-hidden" tabindex="-1" aria-label="Upload student photo">
                        <div id="gep-photo-dropzone" class="gep-photo-dropzone" role="button" tabindex="0" aria-label="Upload or drop a student photo">
                            <i class="ti ti-cloud-upload fs-1 text-primary"></i><strong>Student Photo</strong><span>Drag and drop, click to upload, or paste a photo</span><small>JPG, PNG or WebP · Up to 10 MB. Crop to 600 × 800 pixels; saved as WebP under 40 KB.</small>
                        </div>
                        <div id="gep-photo-preview-container" class="gep-photo-preview d-none"><img id="gep-photo-preview" alt="Student photo preview"><button type="button" id="gep-photo-edit" class="btn btn-sm btn-outline-primary d-none"><i class="ti ti-crop me-1"></i>Edit Photo</button></div>
                    </div>
                <div id="gep-new-panel">@include('gep._student-profile-fields')</div>
                <div id="gep-edit-student" class="alert alert-primary d-none"></div>
                </div></section>
                <section class="gep-enrollment-section mb-3"><h3>Enrollment Information</h3><div class="p-3"><div class="row g-3 gep-assignment-grid">
                    @foreach(['academic_year_id'=>'Academic Year','term_assignment_id'=>'Term','campus_id'=>'Campus','level_id'=>'Level','class_id'=>'Class','class_type_id'=>'Class Type','group_id'=>'Group','enrollment_status'=>'Enrollment Status'] as $name=>$label)
                        <div class="col-12 col-sm-6 col-lg-3"><div class="gep-field"><select id="gep-form-{{ $name }}" name="{{ $name }}" required aria-label="{{ $label }}"></select><label for="gep-form-{{ $name }}">{{ $label }} <span class="text-danger">*</span></label></div></div>
                    @endforeach
                    <div class="col-12 col-lg-6 gep-assignment-date">@include('gep._birth-date', ['dateField'=>'enrolled_on'])</div>
                    <div class="col-12 gep-assignment-notes"><div class="gep-field"><textarea class="form-control" id="gep-form-notes" name="notes" placeholder=" " maxlength="2000"></textarea><label for="gep-form-notes">Notes</label></div></div>
                </div></div></section>
                <section class="gep-enrollment-section"><h3>Parent Information <span class="fw-normal small">(Optional)</span></h3><div class="p-3">
                    @foreach(['mother'=>'Mother','father'=>'Father','guardian'=>'Guardian'] as $parent=>$parentLabel)
                        <fieldset class="gep-parent-section"><legend>{{ $parentLabel }}</legend><div class="row g-3">
                            @foreach(['name_en'=>'Full Name (English)','name_kh'=>'Full Name (Khmer)','occupation_id'=>'Occupation (Khmer / English)','nationality_country_id'=>'Nationality (Khmer / English)','phone'=>'Phone Number','workplace'=>'Workplace'] as $field=>$label)
                                <div class="col-12 col-sm-6 col-xl-3"><div class="gep-field @if($field==='phone') gep-home-phone-field @endif">
                                    @if(in_array($field,['occupation_id','nationality_country_id']))
                                        <select id="gep-form-{{ $parent }}-{{ $field }}" name="{{ $parent }}_{{ $field }}" @if($field==='nationality_country_id') data-country-select="nationality" @else data-occupation-select @endif></select>
                                    @elseif($field==='phone')
                                        <input class="form-control" id="gep-{{ $parent }}-phone-display" type="tel" autocomplete="tel" maxlength="50" aria-label="{{ $parentLabel }} Phone Number">
                                        <input type="hidden" id="gep-form-{{ $parent }}-phone" name="{{ $parent }}_phone">
                                    @else
                                        <input class="form-control" id="gep-form-{{ $parent }}-{{ $field }}" name="{{ $parent }}_{{ $field }}" type="text" placeholder=" " maxlength="150">
                                    @endif
                                    <label for="{{ $field==='phone'?'gep-'.$parent.'-phone-display':'gep-form-'.$parent.'-'.$field }}">{{ $label }}</label>
                                </div></div>
                            @endforeach
                        </div></fieldset>
                    @endforeach
                </div></section>
                </div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="gep-enrollment-save" type="submit">Register Student</button></div>
        </form></div>
    </div>
    <div class="modal modal-blur fade" id="gep-history-modal" tabindex="-1" aria-labelledby="gep-history-title" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header gep-settings-header"><h2 class="modal-title text-white" id="gep-history-title">Student Activity History</h2><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body" id="gep-history-body"></div></div></div></div>
    <button type="button" id="gep-back-top" class="btn btn-primary app-back-to-top" hidden aria-label="Go to top"><i class="ti ti-arrow-up"></i></button>
</div>
@include('photos._editor', ['backgroundUrl'=>route('gep.enrollments.photo.background', [], false), 'sizeUrl'=>route('gep.enrollments.photo.size', [], false)])
@vite('resources/js/gepEnrollment.js')
@endsection
