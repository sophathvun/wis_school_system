@extends('layouts.app')
@section('title', 'Student Enrollment')
@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Students</div>
                <h2 class="page-title">Student Enrollment</h2>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" id="newEnrollment"><i class="ti ti-plus icon"></i> New Enrollment</button>
            </div>
        </div>
    </div>
@endsection
@section('content')
    @php($reportBranding = \App\Models\BrandingSetting::current())
    @php($reportLogo1Path = $reportBranding->report_logo_1_path ?: 'school_logo/student_profile_report_logo.png')
    @php($reportLogo1File = storage_path('app/public/' . $reportLogo1Path))
    @php($reportLogo1Version = is_file($reportLogo1File) ? filemtime($reportLogo1File) : time())
    {{-- Use a same-origin storage URL so the image also loads in the report's new window. --}}
    @php($reportLogo1Url = '/storage/' . ltrim($reportLogo1Path, '/') . '?v=' . $reportLogo1Version)
    @php($reportLogo2Url = $reportBranding->report_logo_2_path ? '/storage/' . ltrim($reportBranding->report_logo_2_path, '/') : '')
    @php($reportLogo1Mime = is_file($reportLogo1File) ? (mime_content_type($reportLogo1File) ?: 'image/png') : 'image/png')
    @php($reportLogo1Data = is_file($reportLogo1File) ? 'data:' . $reportLogo1Mime . ';base64,' . base64_encode(file_get_contents($reportLogo1File)) : $reportLogo1Url)
    <div id="student-report-branding" data-report-logo-1="{{ $reportLogo1Data }}" data-report-logo-2="{{ $reportLogo2Url }}"
        class="d-none"></div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Student Enrollment Lists</h3>
            </div>
            <div class="card-body border-bottom py-3">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <select id="enrollments-per-page" class="d-none" aria-hidden="true">
                        <option selected>10</option>
                        <option>25</option>
                        <option>50</option>
                    </select>
                    <div class="d-flex align-items-center gap-2 flex-wrap enrollment-list-filter-group">
                        <select id="enrollments-filter-academic-year"
                            class="form-select form-select-sm enrollment-list-filter">
                            <option value="">Academic Year</option>
                        </select>
                        <select id="enrollments-filter-campus" class="form-select form-select-sm enrollment-list-filter">
                            <option value="">Campus</option>
                        </select>
                        <select id="enrollments-filter-grade-class"
                            class="form-select form-select-sm enrollment-list-filter">
                            <option value="">Grade</option>
                        </select>
                        <select id="enrollments-filter-group" class="form-select form-select-sm enrollment-list-filter">
                            <option value="">Group</option>
                        </select>
                        <select id="enrollments-filter-student" class="form-select form-select-sm enrollment-list-filter">
                            <option value="">Student Name</option>
                        </select>
                        <select id="enrollments-filter-status" class="form-select form-select-sm enrollment-list-filter">
                            <option value="">Status</option>
                        </select>
                    </div>
                    <div class="ms-auto flex-shrink-0">
                        <input id="enrollments-search" class="form-control form-control-sm"
                            style="width:260px; max-width:100%;" placeholder="Search student">
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table card-table">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th><button type="button" class="table-sort" data-sort="student_id">Student ID <span
                                        data-sort-icon="student_id"></span></button></th>
                            <th><button type="button" class="table-sort" data-sort="student_name">Student Name <span
                                        data-sort-icon="student_name"></span></button></th>
                            <th data-student-type-column="true"><button type="button" class="table-sort"
                                    data-sort="student_type">Type <span data-sort-icon="student_type"></span></button></th>
                            <th><button type="button" class="table-sort" data-sort="academic_year">Academic Year <span
                                        data-sort-icon="academic_year"></span></button></th>
                            <th><button type="button" class="table-sort" data-sort="campus">Campus <span
                                        data-sort-icon="campus"></span></button></th>
                            <th><button type="button" class="table-sort" data-sort="grade">Grade <span
                                        data-sort-icon="grade"></span></button></th>
                            <th><button type="button" class="table-sort" data-sort="academic_track">Track <span
                                        data-sort-icon="academic_track"></span></button></th>
                            <th><button type="button" class="table-sort" data-sort="group">Group <span
                                        data-sort-icon="group"></span></button></th>
                            <th><button type="button" class="table-sort" data-sort="status">Status <span
                                        data-sort-icon="status"></span></button></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="enrollmentsTable"></tbody>
                </table>
            </div>
            <div class="card-footer">
                <div id="enrollments-pagination-container"></div>
            </div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="enrollmentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
            <div class="modal-content">
                <form id="enrollmentForm" novalidate>
                    @csrf
                    <input type="hidden" id="enrollment_id" name="enrollment_id">
                    <input type="hidden" id="student_record_id" name="student_record_id">
                    <div class="modal-header">
                        <h5 id="enrollmentModalTitle" class="modal-title">Create Student Enrollment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" data-alert></div>

                        <div class="enrollment-profile-card">
                            <h4 class="mb-3">Student Information</h4>
                            <div class="row g-3 mb-4" id="studentInformationFields">
                                <div class="col-12">
                                    <label class="form-label">Student Photo</label>
                                    <div class="student-photo-upload-row">
                                        <div class="logo-dropzone" id="studentPhotoDropzone" tabindex="0">
                                            <i class="ti ti-cloud-upload logo-dropzone-icon"></i>
                                            <div><strong>Drag and drop student photo here</strong></div>
                                            <div class="text-secondary">or click, paste, or upload a file</div>
                                            <input type="file" class="d-none" name="photo" id="student_photo"
                                                accept="image/jpeg,image/png,image/webp">
                                            <div class="d-none student-photo-preview-wrap"
                                                id="studentPhotoPreviewContainer">
                                                <img id="studentPhotoPreview" src="#" alt="Student photo preview"
                                                    class="student-photo-preview">
                                            </div>
                                        </div>
                                    </div>
                                    <small class="form-hint">JPG, PNG, or WEBP. Maximum size: 2 MB.</small>
                                </div>
                                <div class="col-md-4 premium-floating-field">
                                    <label class="form-label">Student No. (Auto)</label>
                                    <input id="student_no" name="student_no" class="form-control" readonly
                                        placeholder="Auto generated">
                                </div>
                                <div class="col-md-4 premium-floating-field">
                                    <label class="form-label">Student ID <span class="text-danger">*</span></label>
                                    <input id="student_id" name="student_id" class="form-control" placeholder=" ">
                                </div>
                                <div class="col-md-4 premium-floating-field">
                                    <label class="form-label">Existing Family / Sibling</label>
                                    <select id="existing_family_number" name="existing_family_number"
                                        class="form-select"></select>
                                </div>
                                <div class="col-md-4 premium-floating-field">
                                    <label class="form-label">Family Number</label>
                                    <input id="family_number" name="family_number" class="form-control" readonly
                                        placeholder="Auto from Student ID">
                                </div>
                                <div class="col-md-4 premium-floating-field">
                                    <label class="form-label">Full Name (English) <span
                                            class="text-danger">*</span></label>
                                    <input id="full_name_en" name="full_name_en" class="form-control" placeholder=" ">
                                </div>
                                <div class="col-md-4 premium-floating-field">
                                    <label class="form-label">Date of Birth</label>
                                    <div class="date-picker" id="date_of_birth_picker">
                                        <button type="button" id="date_of_birth_trigger"
                                            class="date-picker-trigger date-picker-calendar-button">
                                            <i class="ti ti-calendar"></i>
                                            <span id="date_of_birth_display" class="date-picker-display">Choose your
                                                date</span>
                                            <i class="ti ti-chevron-down"></i>
                                        </button>
                                        <input type="hidden" id="date_of_birth" name="date_of_birth">
                                        <input type="text" id="date_of_birth_direct" class="form-control"
                                            inputmode="numeric" placeholder="DD-MM-YYYY"
                                            aria-label="Enter date of birth directly">
                                        <div id="date_of_birth_popup" class="date-picker-popup d-none">
                                            <div class="date-picker-header">
                                                <button type="button" id="date_of_birth_prev" class="date-picker-nav"
                                                    aria-label="Previous month">
                                                    <i class="ti ti-chevron-left"></i>
                                                </button>
                                                <button type="button" id="date_of_birth_year_toggle"
                                                    class="date-picker-year-toggle">
                                                    <span id="date_of_birth_month_label">July 2026</span>
                                                </button>
                                                <button type="button" id="date_of_birth_next" class="date-picker-nav"
                                                    aria-label="Next month">
                                                    <i class="ti ti-chevron-right"></i>
                                                </button>
                                            </div>
                                            <div id="date_of_birth_year_popup" class="date-picker-year-popup d-none">
                                                <div id="date_of_birth_years" class="date-picker-years"></div>
                                            </div>
                                            <div class="date-picker-grid">
                                                <div class="date-picker-weekdays">
                                                    <span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span>
                                                </div>
                                                <div id="date_of_birth_days" class="date-picker-days"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 premium-floating-field">
                                    <label class="form-label">Full Name (Khmer)</label>
                                    <input id="full_name_kh" name="full_name_kh"
                                        class="form-control school-profile-khmer" placeholder=" ">
                                </div>
                                <div class="col-md-4 premium-floating-field">
                                    <label class="form-label school-profile-khmer">ថ្ងៃខែឆ្នាំកំណើត (Date of Birth)</label>
                                    <input id="date_of_birth_kh" class="form-control school-profile-khmer" readonly
                                        placeholder="ថ្ងៃ-ខែ-ឆ្នាំ">
                                </div>
                            </div>

                            <h4 class="mb-3" id="contactInformationHeading">Contact and Nationality</h4>
                            <div class="row g-3 mb-4" id="contactInformationFields">
                                <div class="col-md-4 premium-floating-field">
                                    <label class="form-label">Nationality (Khmer / English)</label>
                                    <select id="nationality_country_id" name="nationality_country_id"
                                        class="form-select d-none"></select>
                                    <div id="student-nationality-combobox" class="location-combobox">
                                        <button type="button" id="student-nationality-toggle"
                                            class="location-combobox-toggle">
                                            <span id="student-nationality-selected"
                                                class="location-combobox-selected"></span>
                                            <i class="ti ti-chevron-down"></i>
                                        </button>
                                        <div id="student-nationality-menu" class="location-combobox-menu d-none">
                                            <input id="student-nationality-search" type="search"
                                                class="form-control location-combobox-search"
                                                placeholder="Search Nationality">
                                            <div id="student-nationality-results" class="location-combobox-results"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 premium-floating-field">
                                    <label class="form-label">Gender (Khmer / English)</label>
                                    <select id="gender" name="gender" class="form-select d-none"></select>
                                    <div id="student-gender-combobox" class="location-combobox">
                                        <button type="button" id="student-gender-toggle"
                                            class="location-combobox-toggle">
                                            <span id="student-gender-selected" class="location-combobox-selected"></span>
                                            <i class="ti ti-chevron-down"></i>
                                        </button>
                                        <div id="student-gender-menu" class="location-combobox-menu d-none">
                                            <div id="student-gender-results" class="location-combobox-results"></div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="gender_kh" id="gender_kh">
                                </div>
                                <div class="col-md-4 premium-floating-field"><label class="form-label">Home Phone</label>
                                    <div class="phone-input-group"><input type="text" id="home_phone_number"
                                            class="form-control" placeholder=" "><input type="hidden" name="home_phone"
                                            id="home_phone"></div>
                                </div>
                                <div class="col-md-4 premium-floating-field"><label class="form-label">E-mail</label>
                                    <div class="input-icon"><span class="input-icon-addon"><i
                                                class="ti ti-mail"></i></span><input type="email" id="email"
                                            name="email" class="form-control" placeholder=" "></div>
                                </div>
                                <div class="col-md-4 premium-floating-field"><label class="form-label">Remarks</label>
                                    <textarea id="remarks" name="remarks" class="form-control" rows="1" placeholder=" "></textarea>
                                </div>
                            </div>

                            <h4 class="mb-3 enrollment-profile-subheading">Place of Birth</h4>
                            <div class="row row-cols-1 row-cols-md-5 g-3 mb-4" id="birthPlaceFields">
                                <div class="col premium-floating-field">
                                    <label class="form-label">Country</label>
                                    <select id="birth_country_id" name="birth_country_id"
                                        class="form-select d-none"></select>
                                    <div id="birth-country-combobox" class="location-combobox">
                                        <button type="button" id="birth-country-toggle"
                                            class="location-combobox-toggle">
                                            <span id="birth-country-selected" class="location-combobox-selected"></span>
                                            <i class="ti ti-chevron-down"></i>
                                        </button>
                                        <div id="birth-country-menu" class="location-combobox-menu d-none">
                                            <input id="birth-country-search" type="text"
                                                class="form-control location-combobox-search"
                                                placeholder="Search Country">
                                            <div id="birth-country-results" class="location-combobox-results"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col premium-floating-field">
                                    <label class="form-label">Province / City</label>
                                    <select id="birth_province_id" name="birth_province_id"
                                        class="form-select d-none"></select>
                                    <div id="birth-province-combobox" class="location-combobox">
                                        <button type="button" id="birth-province-toggle"
                                            class="location-combobox-toggle">
                                            <span id="birth-province-selected" class="location-combobox-selected"></span>
                                            <i class="ti ti-chevron-down"></i>
                                        </button>
                                        <div id="birth-province-menu" class="location-combobox-menu d-none">
                                            <input id="birth-province-search" type="text"
                                                class="form-control location-combobox-search"
                                                placeholder="Search Province / City">
                                            <div id="birth-province-results" class="location-combobox-results"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col premium-floating-field">
                                    <label class="form-label">District / Khan</label>
                                    <select id="birth_district_id" name="birth_district_id"
                                        class="form-select d-none"></select>
                                    <div id="birth-district-combobox" class="location-combobox">
                                        <button type="button" id="birth-district-toggle"
                                            class="location-combobox-toggle">
                                            <span id="birth-district-selected" class="location-combobox-selected"></span>
                                            <i class="ti ti-chevron-down"></i>
                                        </button>
                                        <div id="birth-district-menu" class="location-combobox-menu d-none">
                                            <input id="birth-district-search" type="text"
                                                class="form-control location-combobox-search"
                                                placeholder="Search District / Khan">
                                            <div id="birth-district-results" class="location-combobox-results"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col premium-floating-field">
                                    <label class="form-label">Commune</label>
                                    <select id="birth_commune_id" name="birth_commune_id"
                                        class="form-select d-none"></select>
                                    <div id="birth-commune-combobox" class="location-combobox">
                                        <button type="button" id="birth-commune-toggle"
                                            class="location-combobox-toggle">
                                            <span id="birth-commune-selected" class="location-combobox-selected"></span>
                                            <i class="ti ti-chevron-down"></i>
                                        </button>
                                        <div id="birth-commune-menu" class="location-combobox-menu d-none">
                                            <input id="birth-commune-search" type="text"
                                                class="form-control location-combobox-search"
                                                placeholder="Search Commune">
                                            <div id="birth-commune-results" class="location-combobox-results"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col premium-floating-field">
                                    <label class="form-label">Village</label>
                                    <select id="birth_village_id" name="birth_village_id"
                                        class="form-select d-none"></select>
                                    <div id="birth-village-combobox" class="location-combobox">
                                        <button type="button" id="birth-village-toggle"
                                            class="location-combobox-toggle">
                                            <span id="birth-village-selected" class="location-combobox-selected"></span>
                                            <i class="ti ti-chevron-down"></i>
                                        </button>
                                        <div id="birth-village-menu" class="location-combobox-menu d-none">
                                            <input id="birth-village-search" type="text"
                                                class="form-control location-combobox-search"
                                                placeholder="Search Village">
                                            <div id="birth-village-results" class="location-combobox-results"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h4 class="mb-3 enrollment-profile-subheading">Home Address</h4>
                            <div class="row g-3 mb-4" id="addressFields">
                                <div class="col-md-4 premium-floating-field"><label
                                        class="form-label">Country</label><select id="address_country_id"
                                        name="address_country_id" class="form-select"></select></div>
                                <div class="col-md-4 premium-floating-field"><label
                                        class="form-label">Province/City</label><select id="address_province_id"
                                        name="address_province_id" class="form-select"></select></div>
                                <div class="col-md-4 premium-floating-field"><label
                                        class="form-label">District/Khan</label><select id="address_district_id"
                                        name="address_district_id" class="form-select"></select></div>
                                <div class="col-md-4 premium-floating-field"><label
                                        class="form-label">Commune</label><select id="address_commune_id"
                                        name="address_commune_id" class="form-select"></select></div>
                                <div class="col-md-4 premium-floating-field"><label
                                        class="form-label">Village</label><select id="address_village_id"
                                        name="address_village_id" class="form-select"></select></div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">House No.
                                        (English)</label><input id="address_house_no_en" name="address_house_no_en"
                                        class="form-control" placeholder=" "></div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">House No.
                                        (Khmer)</label><input id="address_house_no_kh" name="address_house_no_kh"
                                        class="form-control school-profile-khmer" placeholder=" "></div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Street
                                        (English)</label><input id="address_street_en" name="address_street_en"
                                        class="form-control" placeholder=" "></div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Street
                                        (Khmer)</label><input id="address_street_kh" name="address_street_kh"
                                        class="form-control school-profile-khmer" placeholder=" "></div>
                                <div class="col-md-6 premium-floating-field"><label class="form-label">Current Address
                                        (English)</label>
                                    <textarea id="current_address_en" name="current_address_en" class="form-control" rows="1" readonly></textarea>
                                </div>
                                <div class="col-md-6 premium-floating-field"><label class="form-label">Current Address
                                        (Khmer)</label>
                                    <textarea id="current_address_kh" name="current_address_kh" class="form-control school-profile-khmer" rows="1"
                                        readonly></textarea>
                                </div>
                            </div>
                        </div>

                        <h4 class="mb-3">Previous School and Assessment</h4>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3 premium-floating-field"><label class="form-label">Previous
                                    School</label><input id="previous_school" name="previous_school" class="form-control"
                                    placeholder=" "></div>
                            <div class="col-md-3 premium-floating-field"><label class="form-label">Tested By</label><input
                                    id="tested_by" name="tested_by" class="form-control" placeholder=" "></div>
                            <div class="col-md-3 premium-floating-field"><label class="form-label">Experienced
                                    English</label>
                                <textarea id="experienced_english" name="experienced_english" class="form-control" rows="1" placeholder=" "></textarea>
                            </div>
                            <div class="col-md-3 premium-floating-field"><label class="form-label">Test Result</label>
                                <textarea id="test_result" name="test_result" class="form-control" rows="1" placeholder=" "></textarea>
                            </div>
                        </div>

                        <h4 class="mb-3">Enrollment Information</h4>
                        <div class="row g-3" id="enrollmentInformationFields">
                            <div class="col-md-4 premium-floating-field">
                                <label class="form-label">Academic Year *</label>
                                <select id="academic_year_id" name="academic_year_id" class="form-select"></select>
                            </div>
                            <div class="col-md-4 premium-floating-field">
                                <label class="form-label">Campus *</label>
                                <select id="campus_id" name="campus_id" class="form-select d-none"></select>
                                <div id="campus-combobox" class="location-combobox">
                                    <button type="button" id="campus-toggle" class="location-combobox-toggle">
                                        <span id="campus-selected" class="location-combobox-selected"></span>
                                        <i class="ti ti-chevron-down"></i>
                                    </button>
                                    <div id="campus-menu" class="location-combobox-menu d-none">
                                        <input type="search" id="campus-search"
                                            class="form-control location-combobox-search" placeholder="Search Campus">
                                        <div id="campus-results" class="location-combobox-results"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 premium-floating-field">
                                <label class="form-label">Grade *</label>
                                <select id="grade_id" name="grade_id" class="form-select"></select>
                            </div>
                            <div class="col-md-4 premium-floating-field">
                                <label class="form-label">Class *</label>
                                <select id="class_id" name="class_id" class="form-select"></select>
                            </div>
                            <div class="col-md-4 premium-floating-field d-none" id="academic_track_field">
                                <label class="form-label">Academic Track <span class="text-danger">*</span></label>
                                <select id="academic_track_id" name="academic_track_id" class="form-select"></select>
                            </div>
                            <div class="col-md-4 premium-floating-field">
                                <label class="form-label">Group <span class="text-danger">*</span></label>
                                <select id="session_id" name="session_id" class="form-select" required></select>
                            </div>
                            <div class="col-md-4 premium-floating-field">
                                <label class="form-label">Enrollment Status</label>
                                <select id="enrollment_status" name="enrollment_status" class="form-select">
                                    <option value="active">Active</option>
                                    <option value="pending">Pending</option>
                                    <option value="completed">Completed</option>
                                    <option value="withdrawn">Withdrawn</option>
                                    <option value="transferred">Transferred</option>
                                    <option value="graduated">Graduated</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-4 premium-floating-field">
                                <label class="form-label">Enrolled On</label>
                                <div class="date-picker" id="enrolled_on_picker">
                                    <div class="date-picker-input-row">
                                        <input type="text" id="enrolled_on_direct" class="form-control"
                                            inputmode="numeric" placeholder="DD-MM-YYYY"
                                            aria-label="Enter enrollment date directly">
                                        <button type="button" id="enrolled_on_trigger"
                                            class="date-picker-trigger date-picker-calendar-button"><i
                                                class="ti ti-calendar"></i><span
                                                class="date-picker-display d-none"></span><i
                                                class="ti ti-chevron-down d-none"></i></button>
                                    </div>
                                    <input type="hidden" id="enrolled_on" name="enrolled_on">
                                    <div id="enrolled_on_popup" class="date-picker-popup d-none">
                                        <div class="date-picker-header"><button type="button" id="enrolled_on_prev"
                                                class="date-picker-nav"><i class="ti ti-chevron-left"></i></button><button
                                                type="button" id="enrolled_on_year_toggle"
                                                class="date-picker-year-toggle"><span
                                                    id="enrolled_on_month_label"></span></button><button type="button"
                                                id="enrolled_on_next" class="date-picker-nav"><i
                                                    class="ti ti-chevron-right"></i></button></div>
                                        <div id="enrolled_on_year_popup" class="date-picker-year-popup d-none">
                                            <div id="enrolled_on_years" class="date-picker-years"></div>
                                        </div>
                                        <div class="date-picker-grid">
                                            <div class="date-picker-weekdays">
                                                <span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span>
                                            </div>
                                            <div id="enrolled_on_days" class="date-picker-days"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="status" name="status" value="1">
                        </div>
                        <h4 class="mb-3 mt-4">Family Information <span
                                class="text-secondary fw-normal fs-5">(Optional)</span></h4>
                        <div class="row g-3 mb-4 family-information" id="familyInformationFields">
                            <div class="family-member-row">
                                <div class="family-member-heading">Mother</div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Mother Full Name
                                        (English) <span class="text-danger">*</span></label><input name="mother_name_en"
                                        class="form-control" placeholder=" " required></div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Mother Full Name
                                        (Khmer)</label><input name="mother_name_kh"
                                        class="form-control school-profile-khmer" placeholder=" "></div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Occupation
                                        (English)</label><select name="mother_occupation_id" id="mother_occupation_id"
                                        class="form-select d-none">
                                        <option value="">Occupation (English)</option>
                                    </select>
                                    <div id="mother-occupation-combobox" class="location-combobox"><button type="button"
                                            id="mother-occupation-toggle" class="location-combobox-toggle"><span
                                                id="mother-occupation-selected"
                                                class="location-combobox-selected"></span><i
                                                class="ti ti-chevron-down"></i></button>
                                        <div id="mother-occupation-menu" class="location-combobox-menu d-none"><input
                                                id="mother-occupation-search" type="search"
                                                class="form-control location-combobox-search"
                                                placeholder="Search Occupation">
                                            <div id="mother-occupation-results" class="location-combobox-results"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Nationality
                                        (English)</label><select name="mother_nationality_country_id"
                                        id="mother_nationality_country_id" class="form-select d-none">
                                        <option value="">Nationality (English)</option>
                                    </select>
                                    <div id="mother-nationality-combobox" class="location-combobox"><button
                                            type="button" id="mother-nationality-toggle"
                                            class="location-combobox-toggle"><span id="mother-nationality-selected"
                                                class="location-combobox-selected"></span><i
                                                class="ti ti-chevron-down"></i></button>
                                        <div id="mother-nationality-menu" class="location-combobox-menu d-none"><input
                                                id="mother-nationality-search" type="search"
                                                class="form-control location-combobox-search"
                                                placeholder="Search Nationality">
                                            <div id="mother-nationality-results" class="location-combobox-results"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Phone Number <span
                                            class="text-danger">*</span></label>
                                    <div class="phone-input-group"><input type="text" id="mother_phone_number"
                                            class="form-control" placeholder=" " required><input type="hidden"
                                            name="mother_phone" id="mother_phone"></div>
                                </div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Work
                                        Place</label><input name="mother_workplace" class="form-control" placeholder=" ">
                                </div>
                            </div>
                            <div class="family-member-row">
                                <div class="family-member-heading">Father</div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Father Full Name
                                        (English) <span class="text-danger">*</span></label><input name="father_name_en"
                                        class="form-control" placeholder=" " required></div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Father Full Name
                                        (Khmer)</label><input name="father_name_kh"
                                        class="form-control school-profile-khmer" placeholder=" "></div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Occupation
                                        (English)</label><select name="father_occupation_id" id="father_occupation_id"
                                        class="form-select d-none">
                                        <option value="">Occupation (English)</option>
                                    </select>
                                    <div id="father-occupation-combobox" class="location-combobox"><button type="button"
                                            id="father-occupation-toggle" class="location-combobox-toggle"><span
                                                id="father-occupation-selected"
                                                class="location-combobox-selected"></span><i
                                                class="ti ti-chevron-down"></i></button>
                                        <div id="father-occupation-menu" class="location-combobox-menu d-none"><input
                                                id="father-occupation-search" type="search"
                                                class="form-control location-combobox-search"
                                                placeholder="Search Occupation">
                                            <div id="father-occupation-results" class="location-combobox-results"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Nationality
                                        (English)</label><select name="father_nationality_country_id"
                                        id="father_nationality_country_id" class="form-select d-none">
                                        <option value="">Nationality (English)</option>
                                    </select>
                                    <div id="father-nationality-combobox" class="location-combobox"><button
                                            type="button" id="father-nationality-toggle"
                                            class="location-combobox-toggle"><span id="father-nationality-selected"
                                                class="location-combobox-selected"></span><i
                                                class="ti ti-chevron-down"></i></button>
                                        <div id="father-nationality-menu" class="location-combobox-menu d-none"><input
                                                id="father-nationality-search" type="search"
                                                class="form-control location-combobox-search"
                                                placeholder="Search Nationality">
                                            <div id="father-nationality-results" class="location-combobox-results"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Phone Number <span
                                            class="text-danger">*</span></label>
                                    <div class="phone-input-group"><input type="text" id="father_phone_number"
                                            class="form-control" placeholder=" " required><input type="hidden"
                                            name="father_phone" id="father_phone"></div>
                                </div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Work
                                        Place</label><input name="father_workplace" class="form-control" placeholder=" ">
                                </div>
                            </div>
                            <div class="family-member-row">
                                <div class="family-member-heading">Guardian</div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Guardian Full Name
                                        (English)</label><input name="guardian_name_en" class="form-control"
                                        placeholder=" "></div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Guardian Full Name
                                        (Khmer)</label><input name="guardian_name_kh"
                                        class="form-control school-profile-khmer" placeholder=" "></div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Occupation
                                        (English)</label><select name="guardian_occupation_id" id="guardian_occupation_id"
                                        class="form-select d-none">
                                        <option value="">Occupation (English)</option>
                                    </select>
                                    <div id="guardian-occupation-combobox" class="location-combobox"><button
                                            type="button" id="guardian-occupation-toggle"
                                            class="location-combobox-toggle"><span id="guardian-occupation-selected"
                                                class="location-combobox-selected"></span><i
                                                class="ti ti-chevron-down"></i></button>
                                        <div id="guardian-occupation-menu" class="location-combobox-menu d-none"><input
                                                id="guardian-occupation-search" type="search"
                                                class="form-control location-combobox-search"
                                                placeholder="Search Occupation">
                                            <div id="guardian-occupation-results" class="location-combobox-results"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Nationality
                                        (English)</label><select name="guardian_nationality_country_id"
                                        id="guardian_nationality_country_id" class="form-select d-none">
                                        <option value="">Nationality (English)</option>
                                    </select>
                                    <div id="guardian-nationality-combobox" class="location-combobox"><button
                                            type="button" id="guardian-nationality-toggle"
                                            class="location-combobox-toggle"><span id="guardian-nationality-selected"
                                                class="location-combobox-selected"></span><i
                                                class="ti ti-chevron-down"></i></button>
                                        <div id="guardian-nationality-menu" class="location-combobox-menu d-none"><input
                                                id="guardian-nationality-search" type="search"
                                                class="form-control location-combobox-search"
                                                placeholder="Search Nationality">
                                            <div id="guardian-nationality-results" class="location-combobox-results">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Phone
                                        Number</label>
                                    <div class="phone-input-group"><input type="text" id="guardian_phone_number"
                                            class="form-control" placeholder=" "><input type="hidden"
                                            name="guardian_phone" id="guardian_phone"></div>
                                </div>
                                <div class="col-md-3 premium-floating-field"><label class="form-label">Work
                                        Place</label><input name="guardian_workplace" class="form-control"
                                        placeholder=" "></div>
                            </div>
                        </div>

                        <div class="enrollment-document-card">
                            <h4 class="mb-3">Student Documents <span
                                    class="text-secondary fw-normal fs-5">(Optional)</span></h4>
                            <ul class="nav nav-tabs mb-3" role="tablist">
                                <li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="tab"
                                        data-bs-target="#enrollment-document-upload-tab">Upload Document</button></li>
                                <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab"
                                        data-bs-target="#enrollment-document-list-tab"
                                        id="enrollment-document-list-tab-button">Submitted Documents</button></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="enrollment-document-upload-tab">
                                    <div class="student-document-upload-layout mb-4">
                                        <div class="student-document-upload-fields" id="studentDocumentsFields">
                                            <div><label class="form-label">Document Type</label><select
                                                    id="enrollment-document-type" class="form-select"></select></div>
                                            <div><label class="form-label">Document Title</label><input
                                                    id="enrollment-document-title" class="form-control"></div>
                                            <div><label class="form-label">Document Number</label><input
                                                    id="enrollment-document-number" class="form-control"></div>
                                            <div class="document-description-field"><label
                                                    class="form-label">Description</label>
                                                <textarea id="enrollment-document-description" class="form-control" rows="2"></textarea>
                                                <div class="form-hint">Documents are saved after the enrollment is saved.
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <div id="enrollmentDocumentDropzone" class="premium-document-dropzone"
                                                tabindex="0"><span class="document-upload-icon"><i
                                                        class="ti ti-cloud-upload"></i></span>
                                                <div class="fw-bold fs-3">Drop Files Here</div>
                                                <div class="mt-1">or <span class="document-browse-link">Browse
                                                        File</span></div>
                                                <div class="document-upload-hint mt-3">Supports PDF, JPG, PNG, DOC, DOCX ·
                                                    Maximum 2 MB per file</div>
                                                <div id="enrollmentDocumentFileList" class="premium-document-file-list">
                                                </div><input type="file" id="enrollment-document-file" class="d-none"
                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="enrollment-document-list-tab">
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead>
                                                <tr>
                                                    <th>Document Type</th>
                                                    <th>Title</th>
                                                    <th>Number</th>
                                                    <th>File</th>
                                                </tr>
                                            </thead>
                                            <tbody id="enrollment-document-list">
                                                <tr>
                                                    <td colspan="4" class="text-center text-secondary">Save or select a
                                                        student to view submitted documents.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button>
                        <button class="btn btn-primary" id="enrollmentSubmit">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal modal-blur fade" id="studentProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Student Profile</h3>
                    <div class="d-flex align-items-center gap-2 ms-auto"><button type="button"
                            class="btn btn-primary btn-sm" id="studentProfilePrint"><i
                                class="ti ti-printer me-1"></i>Print Report</button><button type="button"
                            class="btn btn-outline-primary btn-sm" id="studentProfilePdf"><i
                                class="ti ti-file-type-pdf me-1"></i>PDF</button><button type="button" class="btn-close"
                            data-bs-dismiss="modal" aria-label="Close"></button></div>
                </div>
                <div class="modal-body" id="studentProfileContent"></div>
            </div>
        </div>
    </div>
    <div class="modal modal-blur fade" id="studentPhotoCropModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Crop Student Photo</h3><button type="button" class="btn-close"
                        data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary small">Adjust the photo inside the locked 3:4 frame. The final image will be
                        exactly 600 x 800 px.</p>
                    <div class="student-photo-crop-stage"><canvas id="studentPhotoCropCanvas" width="600"
                            height="800"></canvas></div>
                    <div class="row g-2 align-items-center mt-3">
                        <div class="col-auto"><button type="button" class="btn btn-outline-secondary"
                                id="studentPhotoZoomOut"><i class="ti ti-zoom-out"></i></button></div>
                        <div class="col"><input type="range" class="form-range" id="studentPhotoZoom"
                                min="1" max="3" step="0.01" value="1" aria-label="Zoom photo">
                        </div>
                        <div class="col-auto"><button type="button" class="btn btn-outline-secondary"
                                id="studentPhotoZoomIn"><i class="ti ti-zoom-in"></i></button></div>
                        <div class="col-auto"><button type="button" class="btn btn-outline-secondary"
                                id="studentPhotoRotateLeft"><i class="ti ti-rotate-2"></i> Left</button></div>
                        <div class="col-auto"><button type="button" class="btn btn-outline-secondary"
                                id="studentPhotoRotateRight"><i class="ti ti-rotate-clockwise-2"></i> Right</button></div>
                        <div class="col-auto"><button type="button" class="btn btn-outline-secondary"
                                id="studentPhotoReset">Reset</button></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-link"
                        data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary"
                        id="studentPhotoCropUpload"><i class="ti ti-crop me-1"></i>Crop and Upload</button></div>
            </div>
        </div>
    </div>
    <div class="modal modal-blur fade" id="studentPhotoViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="studentPhotoViewTitle">Student Photo</h3><button type="button"
                        class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center overflow-hidden"><img id="studentPhotoViewImage" src="#"
                        alt="Student photo"></div>
                <div class="modal-footer justify-content-between">
                    <div class="d-flex align-items-center gap-2"><button type="button"
                            class="btn btn-outline-secondary" id="studentPhotoViewZoomOut"><i
                                class="ti ti-zoom-out"></i></button><input type="range" id="studentPhotoViewZoom"
                            min="1" max="3" step=".05" value="1" style="width:150px"
                            aria-label="Zoom student photo"><button type="button" class="btn btn-outline-secondary"
                            id="studentPhotoViewZoomIn"><i class="ti ti-zoom-in"></i></button><button type="button"
                            class="btn btn-outline-secondary" id="studentPhotoViewZoomReset">Reset</button></div>
                    <a class="btn btn-primary" id="studentPhotoViewDownload" href="#" download><i
                            class="ti ti-download me-1"></i>Download</a>
                </div>
            </div>
        </div>
    </div>
    <div class="modal modal-blur fade" id="enrollmentHistoryModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="enrollmentHistoryTitle">Enrollment History</h3><button type="button"
                        class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-vcenter">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Action</th>
                                    <th>Academic Year</th>
                                    <th>Campus</th>
                                    <th>Grade</th>
                                    <th>Class</th>
                                    <th>Track</th>
                                    <th>Group</th>
                                    <th>Status</th>
                                    <th>Updated Date &amp; Time</th>
                                    <th>Updated By</th>
                                </tr>
                            </thead>
                            <tbody id="enrollmentHistoryTable">
                                <tr>
                                    <td colspan="11" class="text-center">No history found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @vite('resources/js/studentEnrollment.js')
    @vite('resources/css/pages/student-enrollment.css')
@endsection
