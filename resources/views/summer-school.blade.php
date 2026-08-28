@extends('layouts.app')
@section('title', 'Summer School')
@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Students</div>
                <h2 class="page-title">Summer School</h2>
            </div>
            <div class="col-auto"><button class="btn btn-primary" id="summerSchoolNew"><i class="ti ti-plus me-1"></i>Register
                    Summer Student</button></div>
        </div>
    </div>
@endsection
@section('content')

    <div class="card">
        <div class="card-header">
            <h3 class="card-title text-uppercase">SUMMER SCHOOL STUDENTS</h3>
        </div>
        <div class="card-body border-bottom py-3">
            <div class="d-flex align-items-center gap-3 flex-wrap summer-list-filter-group">
                <select id="summerFilterYear" class="form-select form-select-sm summer-list-filter"><option value="">All Academic Year</option></select>
                <select id="summerFilterCampus" class="form-select form-select-sm summer-list-filter"><option value="">All Campus</option></select>
                <select id="summerFilterGrade" class="form-select form-select-sm summer-list-filter"><option value="">All Grade</option></select>
                <select id="summerFilterGroup" class="form-select form-select-sm summer-list-filter"><option value="">All Group</option></select>
                <select id="summerFilterStudent" class="form-select form-select-sm summer-list-filter summer-list-student-filter"><option value="">All Student Name</option></select>
                <select id="summerFilterStatus" class="form-select form-select-sm summer-list-filter"><option value="">All Status</option><option value="active">Active</option><option value="pending">Pending</option><option value="withdrawn">Withdrawn</option><option value="completed">Completed</option></select>
                <div class="ms-auto flex-shrink-0"><input id="summerFilterSearch" class="form-control form-control-sm summer-list-search" placeholder="Search student"></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Photo</th><th><button type="button" class="table-sort" data-summer-sort="student_id">Student ID</button></th><th><button type="button" class="table-sort" data-summer-sort="student_name">Student Name</button></th><th><button type="button" class="table-sort" data-summer-sort="enrollment_origin">Type</button></th>
                        <th><button type="button" class="table-sort" data-summer-sort="academic_year">Academic Year</button></th><th><button type="button" class="table-sort" data-summer-sort="campus">Campus</button></th><th><button type="button" class="table-sort" data-summer-sort="grade">Grade</button></th><th><button type="button" class="table-sort" data-summer-sort="track">Track</button></th><th><button type="button" class="table-sort" data-summer-sort="group">Group</button></th>
                        <th><button type="button" class="table-sort" data-summer-sort="enrollment_status">Status</button></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="summerSchoolRows">
                    <tr>
                        <td colspan="11" class="text-center text-secondary">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer"><select id="summer-per-page" class="d-none" aria-hidden="true"><option value="15" selected>15</option></select><div id="summer-pagination-container"></div></div>
    </div>
    <div class="modal modal-blur fade" id="summerSchoolModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form id="summerSchoolForm" novalidate>
                    <div class="modal-header">
                        <h3 class="modal-title">Register Summer Student</h3><button type="button" class="btn-close"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="summer-registration-layout">
                            <div class="summer-registration-nav" role="tablist" aria-label="Student type">
                                <div class="summer-registration-nav-title">Register Student</div>
                                <button type="button" class="summer-registration-nav-item is-active" data-summer-origin="internal">
                                    <span class="summer-registration-nav-label"><i class="ti ti-school"></i>Western Student</span><i class="ti ti-chevron-right"></i>
                                </button>
                                <button type="button" class="summer-registration-nav-item" data-summer-origin="external">
                                    <span class="summer-registration-nav-label"><i class="ti ti-user-plus"></i>New Student</span><i class="ti ti-chevron-right"></i>
                                </button>
                            </div>
                            <div class="summer-registration-content">
                        <div class="alert alert-danger d-none" id="summerSchoolError"></div>
                        <div class="row g-3">
                            <div class="col-md-6 d-none"><label class="form-label">Student Type *</label><select
                                    name="enrollment_origin" id="summerOrigin" class="form-select" required>
                                    <option value="internal">Western Student</option>
                                    <option value="external">New Student from Other School</option>
                                </select></div>
                            <div class="col-12" id="summerWesternFilters">
                                <div class="border rounded p-3">
                                    <div class="summer-western-filter-header">FIND WESTERN STUDENT</div>
                                    <div class="row g-2">
                                        <div class="col-md-3"><label class="form-label small">Academic Year</label><select
                                                id="summerStudentYearFilter" class="form-select">
                                                <option value=""></option>
                                            </select></div>
                                        <div class="col-md-3"><label class="form-label small">Campus</label><select
                                                id="summerStudentCampusFilter" class="form-select">
                                                <option value=""></option>
                                            </select></div>
                                        <div class="col-md-3"><label class="form-label small">Grade</label><select
                                                id="summerStudentGradeClassFilter" class="form-select">
                                                <option value=""></option>
                                            </select></div>
                                        <div class="col-md-3" id="summerInternalField"><label class="form-label small">Select Student *</label><select
                                                name="student_record_id" id="summerInternalStudent"
                                                class="form-select"></select></div>
                                    </div>
                                    <div id="summerWesternStudentInfo" class="summer-western-student-info d-none"></div>
                                </div>
                            </div>
                            <div class="col-12 summer-external-field d-none"><h4 class="summer-form-section-title">Student Information</h4></div>
                            <div class="col-12 summer-external-field d-none">
                                <label class="form-label summer-photo-label">Student Photo</label>
                                <div class="summer-student-photo-upload">
                                    <div class="logo-dropzone summer-external-photo-dropzone" id="summerExternalPhotoDropzone" tabindex="0">
                                        <i class="ti ti-cloud-upload logo-dropzone-icon"></i>
                                        <div><strong>Drag and drop student photo here</strong></div>
                                        <div class="text-secondary">or click, paste, or upload a file</div>
                                        <input type="file" name="photo" id="summerExternalPhoto" class="d-none"
                                            accept="image/jpeg,image/png,image/webp">
                                        <div class="d-none summer-external-photo-preview-wrap" id="summerExternalPhotoPreviewWrap">
                                            <img id="summerExternalPhotoPreview" class="summer-external-photo-preview" alt="Student photo preview">
                                        </div>
                                    </div>
                                    <small class="form-hint">JPG, PNG, or WEBP. Maximum size: 2 MB.</small>
                                </div>
                            </div>
                            <div class="col-md-6 summer-external-field d-none"><label class="form-label">Other School
                                    Student ID</label><input name="external_student_id" class="form-control"></div>
                            <div class="col-md-6 summer-external-field d-none"><label class="form-label">Previous School
                                    *</label><input name="previous_school" class="form-control"></div>
                            <div class="col-md-6 summer-external-field d-none"><label class="form-label">Full Name
                                    (English) *</label><input name="full_name_en" class="form-control"></div>
                            <div class="col-md-6 summer-external-field d-none"><label class="form-label">Full Name
                                    (Khmer)</label><input name="full_name_kh" class="form-control school-profile-khmer">
                            </div>
                            <div class="col-md-4 summer-external-field d-none"><label
                                    class="form-label">Gender</label><select name="gender" class="form-select">
                                    <option value=""></option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select></div>
                            <div class="col-md-4 summer-external-field d-none"><label class="form-label">Gender
                                    (Khmer)</label><input name="gender_kh" class="form-control school-profile-khmer">
                            </div>
                            <div class="col-md-4 summer-external-field d-none"><label class="form-label">Date of
                                    Birth</label><input type="date" name="date_of_birth" class="form-control"></div>
                            <div class="col-md-6 summer-external-field d-none"><label
                                    class="form-label">Nationality</label><select name="nationality_country_id"
                                    id="summerNationality" class="form-select">
                                    <option value=""></option>
                                </select></div>
                            <div class="col-md-6 summer-external-field d-none"><label
                                    class="form-label">Phone</label><input name="home_phone" class="form-control"></div>
                            <div class="col-md-6 summer-external-field d-none"><label
                                    class="form-label">Email</label><input type="email" name="email"
                                    class="form-control"></div>
                            <div class="col-12 summer-external-field d-none"><label
                                    class="form-label">Remarks</label><textarea name="summer_remarks" class="form-control"
                                    rows="3"></textarea></div>
                            <div class="col-md-6 summer-external-field d-none"><label class="form-label">Continue at
                                    Western?</label><select name="continue_at_western" class="form-select">
                                    <option value="pending">Pending Decision</option>
                                    <option value="yes">Yes</option>
                                    <option value="no">No</option>
                                </select></div>
                            <div class="col-12 summer-external-field d-none"><h4 class="summer-form-section-title">Previous School and Assessment</h4></div>
                            <div class="col-md-3 summer-external-field d-none"><label class="form-label">Previous School</label><input name="previous_school" class="form-control"></div>
                            <div class="col-md-3 summer-external-field d-none"><label class="form-label">Tested By</label><input name="tested_by" class="form-control"></div>
                            <div class="col-md-3 summer-external-field d-none"><label class="form-label">Experienced English</label><textarea name="experienced_english" class="form-control" rows="1"></textarea></div>
                            <div class="col-md-3 summer-external-field d-none"><label class="form-label">Test Result</label><textarea name="test_result" class="form-control" rows="1"></textarea></div>
                            <div class="col-12"><div class="border rounded p-3 summer-enrollment-section"><h4 class="mb-3">Enrollment Information</h4><div class="row g-3">
                            <div class="col-md-4"><label class="form-label">Academic Year *</label><select
                                    name="academic_year_id" id="summerAcademicYear" class="form-select" required></select></div>
                            <div class="col-md-4"><label class="form-label">Campus *</label><select name="campus_id"
                                    id="summerCampus" class="form-select" required></select></div>
                            <div class="col-md-4"><label class="form-label">Grade *</label><select name="grade_id"
                                    id="summerGrade" class="form-select" required></select></div>
                            <div class="col-md-4"><label class="form-label">Class *</label><select name="class_id"
                                    id="summerClass" class="form-select" required></select></div>
                            <div class="col-md-4"><label class="form-label">Group *</label><select name="session_id" 
                                    id="summerSession" class="form-select"></select></div>
                            <div class="col-md-4"><label class="form-label">Enrollment Status</label><select name="enrollment_status" id="summerEnrollmentStatus" class="form-select"><option value="active">Active</option><option value="pending">Pending</option><option value="completed">Completed</option><option value="withdrawn">Withdrawn</option></select></div>
                            <div class="col-md-4"><label class="form-label">Enrolled On</label><input type="date" name="enrolled_on" class="form-control"></div>
                            </div></div></div>
                            <div class="col-12 summer-external-field d-none">
                                <h4 class="summer-form-section-title">Family Information</h4>
                            </div>
                            <div class="col-12 summer-external-field d-none">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <h5 class="mb-0">Mother</h5>
                                    </div>
                                    <div class="col-md-6"><label class="form-label">Mother Name (English) *</label><input
                                            name="mother_name_en" class="form-control"></div>
                                    <div class="col-md-6"><label class="form-label">Mother Name (Khmer)</label><input
                                            name="mother_name_kh" class="form-control school-profile-khmer"></div>
                                    <div class="col-md-3"><label class="form-label">Occupation</label><input
                                            name="mother_occupation_en" class="form-control"></div>
                                    <div class="col-md-3"><label class="form-label">Occupation (Khmer)</label><input
                                            name="mother_occupation_kh" class="form-control school-profile-khmer"></div>
                                    <div class="col-md-3"><label class="form-label">Nationality</label><input
                                            name="mother_nationality_en" class="form-control"></div>
                                    <div class="col-md-3"><label class="form-label">Nationality (Khmer)</label><input
                                            name="mother_nationality_kh" class="form-control school-profile-khmer"></div>
                                    <div class="col-md-6"><label class="form-label">Phone *</label><input
                                            name="mother_phone" class="form-control"></div>
                                    <div class="col-md-6"><label class="form-label">Workplace</label><input
                                            name="mother_workplace" class="form-control"></div>
                                    <div class="col-12">
                                        <h5 class="mb-0 mt-2">Father</h5>
                                    </div>
                                    <div class="col-md-6"><label class="form-label">Father Name (English) *</label><input
                                            name="father_name_en" class="form-control"></div>
                                    <div class="col-md-6"><label class="form-label">Father Name (Khmer)</label><input
                                            name="father_name_kh" class="form-control school-profile-khmer"></div>
                                    <div class="col-md-3"><label class="form-label">Occupation</label><input
                                            name="father_occupation_en" class="form-control"></div>
                                    <div class="col-md-3"><label class="form-label">Occupation (Khmer)</label><input
                                            name="father_occupation_kh" class="form-control school-profile-khmer"></div>
                                    <div class="col-md-3"><label class="form-label">Nationality</label><input
                                            name="father_nationality_en" class="form-control"></div>
                                    <div class="col-md-3"><label class="form-label">Nationality (Khmer)</label><input
                                            name="father_nationality_kh" class="form-control school-profile-khmer"></div>
                                    <div class="col-md-6"><label class="form-label">Phone *</label><input
                                            name="father_phone" class="form-control"></div>
                                    <div class="col-md-6"><label class="form-label">Workplace</label><input
                                            name="father_workplace" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-12 summer-external-field d-none">
                                <div class="enrollment-document-card">
                                    <h4 class="mb-3">Student Documents <span class="text-secondary fw-normal fs-5">(Optional)</span></h4>
                                    <div class="row g-3">
                                        <div class="col-md-3 premium-floating-field"><label class="form-label">Document Type</label><select name="document_type_id" id="summerDocumentType" class="form-select"></select></div>
                                        <div class="col-md-3 premium-floating-field"><label class="form-label">Document Title</label><input name="document_title" class="form-control"></div>
                                        <div class="col-md-3 premium-floating-field"><label class="form-label">Document Number</label><input name="document_number" class="form-control"></div>
                                        <div class="col-md-3 premium-floating-field"><label class="form-label">Document File</label><input type="file" name="document_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></div>
                                        <div class="col-12 premium-floating-field"><label class="form-label">Description</label><textarea name="document_description" class="form-control" rows="2"></textarea></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn me-auto"
                            data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"
                            type="submit">Register</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal modal-blur fade" id="summerConvertModal" tabindex="-1">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <form id="summerConvertForm">
                    <div class="modal-header">
                        <h3 class="modal-title">Continue at Western</h3><button type="button" class="btn-close"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="summerConvertError"></div><input type="hidden"
                            id="summerConvertEnrollmentId">
                        <div class="mb-3"><label class="form-label">New Academic Year *</label><select
                                name="academic_year_id" id="summerConvertYear" class="form-select" required></select>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Campus *</label><select name="campus_id"
                                    id="summerConvertCampus" class="form-select" required></select></div>
                            <div class="col-md-6"><label class="form-label">Grade *</label><select name="grade_id"
                                    id="summerConvertGrade" class="form-select" required></select></div>
                            <div class="col-md-6"><label class="form-label">Class *</label><select name="class_id"
                                    id="summerConvertClass" class="form-select" required></select></div>
                            <div class="col-md-6"><label class="form-label">Group</label><select name="session_id"
                                    id="summerConvertSession" class="form-select"></select></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn me-auto"
                            data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Create
                            Western Enrollment</button></div>
                </form>
            </div>
        </div>
    </div>
    @vite('resources/js/summerSchool.js')
    @vite('resources/css/pages/summer-school.css')
    <style>
        /* Keep populated student identifiers neutral when a stale CSS bundle is served. */
        #summerSchoolModal .premium-floating-field > input[name="student_no"],
        #summerSchoolModal .premium-floating-field > input[name="student_id"] {
            background-color: #fff !important;
            -webkit-box-shadow: inset 0 0 0 1000px #fff !important;
            box-shadow: 0 2px 7px rgba(31, 41, 55, .04) !important;
        }
        #summerSchoolModal .premium-floating-field > input[name="student_id"]:-webkit-autofill,
        #summerSchoolModal .premium-floating-field > input[name="student_id"]:-webkit-autofill:hover,
        #summerSchoolModal .premium-floating-field > input[name="student_id"]:-webkit-autofill:focus,
        #summerSchoolModal .premium-floating-field > input[name="student_id"]:-webkit-autofill:active {
            -webkit-box-shadow: inset 0 0 0 1000px #fff !important;
            -webkit-text-fill-color: var(--tblr-body-color) !important;
            background-color: #fff !important;
            background-image: none !important;
        }
        #summerSchoolModal .premium-floating-field > input[name="full_name_en"] {
            text-transform: uppercase;
        }
        #summerSchoolModal .premium-floating-field > input[name="mother_name_en"],
        #summerSchoolModal .premium-floating-field > input[name="father_name_en"],
        #summerSchoolModal .premium-floating-field > input[name="guardian_name_en"] {
            text-transform: uppercase;
        }
        #summerSchoolModal .premium-floating-field > input[name="address_house_no_en"],
        #summerSchoolModal .premium-floating-field > input[name="address_street_en"] {
            background-color: #fff !important;
            -webkit-box-shadow: inset 0 0 0 1000px #fff !important;
            box-shadow: 0 2px 7px rgba(31, 41, 55, .04) !important;
        }
        #summerSchoolModal .premium-floating-field > input[name="address_house_no_en"]:-webkit-autofill,
        #summerSchoolModal .premium-floating-field > input[name="address_house_no_en"]:-webkit-autofill:hover,
        #summerSchoolModal .premium-floating-field > input[name="address_house_no_en"]:-webkit-autofill:focus,
        #summerSchoolModal .premium-floating-field > input[name="address_street_en"]:-webkit-autofill,
        #summerSchoolModal .premium-floating-field > input[name="address_street_en"]:-webkit-autofill:hover,
        #summerSchoolModal .premium-floating-field > input[name="address_street_en"]:-webkit-autofill:focus {
            -webkit-box-shadow: inset 0 0 0 1000px #fff !important;
            -webkit-text-fill-color: var(--tblr-body-color) !important;
            background-color: #fff !important;
            background-image: none !important;
        }
        #summerSchoolModal .summer-family-grid input,
        #summerSchoolModal .summer-family-grid input:-webkit-autofill,
        #summerSchoolModal .summer-family-grid input:-webkit-autofill:hover,
        #summerSchoolModal .summer-family-grid input:-webkit-autofill:focus,
        #summerSchoolModal .summer-family-grid input:-webkit-autofill:active {
            background-color: #fff !important;
            background: #fff !important;
            background-image: none !important;
            -webkit-box-shadow: inset 0 0 0 1000px #fff !important;
            box-shadow: inset 0 0 0 1000px #fff !important;
            -webkit-text-fill-color: var(--tblr-body-color) !important;
            transition: background-color 9999s ease-out !important;
        }
        #summerSchoolModal .premium-document-file-card { display:flex; align-items:center; gap:.9rem; margin-top:1.5rem; padding:.85rem 1rem; border:1.5px solid #ddd6fe; border-radius:18px; background:#fff; text-align:left; }
        #summerSchoolModal .premium-document-file-icon { display:grid; flex:0 0 3.4rem; width:3.4rem; height:3.4rem; place-items:center; border-radius:16px; color:#6657d9; background:#f0edff; font-size:1.7rem; }
        #summerSchoolModal .premium-document-file-meta { min-width:0; flex:1 1 auto; }
        #summerSchoolModal .premium-document-file-name { overflow:hidden; color:#344054; font-size:1rem; font-weight:700; text-overflow:ellipsis; white-space:nowrap; }
        #summerSchoolModal .premium-document-file-size { margin-top:.2rem; color:#667085; font-size:.9rem; }
        #summerSchoolModal .premium-document-file-remove { flex:0 0 auto; width:2rem; height:2rem; border:0; color:#667085; background:transparent; font-size:1.35rem; }
        #summerSchoolModal .premium-document-dropzone .document-upload-icon { display:grid; width:7rem; height:7rem; margin:0 auto 1rem; place-items:center; border-radius:1.6rem; color:#6657d9; background:#f0edff; }
        #summerSchoolModal .premium-document-dropzone .document-upload-icon i { font-size:4.2rem; line-height:1; }
        #summerSchoolModal .premium-floating-field > .phone-input-group { display:flex; align-items:center; overflow:hidden; }
        #summerSchoolModal .phone-input-group .iti { display:flex; align-items:center; width:100%; }
        #summerSchoolModal .phone-input-group .iti__tel-input { flex:1 1 auto; min-width:0; padding-top:.7rem; }
    </style>
    <script>
        // Decorate the file list when an older cached Summer-school bundle is served.
        (() => {
            const decorate = () => {
                const list = document.getElementById('summerDocumentFileList');
                const input = document.getElementById('summerDocumentFile');
                const file = input?.files?.[0];
                if (!list || !file || list.querySelector('.premium-document-file-card')) return;
                const size = file.size < 1048576 ? `${Math.max(1, Math.round(file.size / 1024))} KB` : `${(file.size / 1048576).toFixed(1)} MB`;
                const row = document.createElement('div'); row.className = 'premium-document-file-card';
                row.innerHTML = `<span class="premium-document-file-icon"><i class="ti ti-photo"></i></span><div class="premium-document-file-meta"><div class="premium-document-file-name"></div><div class="premium-document-file-size">${size} <span>• Ready to upload</span></div></div><button type="button" class="premium-document-file-remove" aria-label="Remove file"><i class="ti ti-x"></i></button>`;
                row.querySelector('.premium-document-file-name').textContent = file.name;
                list.replaceChildren(row);
            };
            new MutationObserver(decorate).observe(document.body, { childList: true, subtree: true });
            document.addEventListener('change', (event) => { if (event.target?.id === 'summerDocumentFile') setTimeout(decorate, 0); });
        })();
    </script>
@endsection
