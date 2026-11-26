<div class="modal modal-blur fade wmi-registration-modal" id="wmiRegistrationModal" tabindex="-1" aria-labelledby="wmiRegistrationTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content wmi-year-modal" data-registration-form>
    @csrf
    <div class="modal-header"><span class="wmi-content-icon me-3"><i class="ti ti-user-plus" aria-hidden="true"></i></span><h2 class="modal-title" id="wmiRegistrationTitle">Register WMI Student</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
        <div class="wmi-registration-layout">
            <aside class="wmi-registration-sidebar" aria-label="Student type">
                <div class="wmi-registration-sidebar-title">Register Student</div>
                <div class="wmi-source-options" role="radiogroup" aria-label="Student source">
                    <label><input class="visually-hidden" type="radio" name="student_source" value="western" checked><i class="ti ti-school" aria-hidden="true"></i><span>Western Student</span><i class="ti ti-chevron-right ms-auto" aria-hidden="true"></i></label>
                    <label><input class="visually-hidden" type="radio" name="student_source" value="other"><i class="ti ti-user-plus" aria-hidden="true"></i><span>Other School Student</span><i class="ti ti-chevron-right ms-auto" aria-hidden="true"></i></label>
                </div>
            </aside>
            <div class="wmi-registration-main">
                <div class="alert alert-danger" role="alert" data-registration-error hidden></div>
        <section data-western-fields class="wmi-registration-card">
            <h3 class="wmi-registration-card-title">Find Western Student</h3><div class="wmi-registration-card-body"><div class="wmi-form-grid wmi-lookup-grid">
                <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiRegAcademicYear">Enrollment Academic Year</label><select class="form-select" id="wmiRegAcademicYear" data-registration-picker="Academic Year"><option value="">Select Academic Year</option>@foreach($registrationAcademicYears as $year)<option value="{{ $year->id }}">{{ $year->academic_year }}</option>@endforeach</select></div>
                <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiRegLookupCampus">Campus</label><select class="form-select" id="wmiRegLookupCampus" data-registration-picker="Campus"><option value="">All Campuses</option>@foreach($registrationCampuses as $campus)<option value="{{ $campus->id }}">{{ $campus->campus_name_en }}</option>@endforeach</select></div>
                <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiRegLookupGrade">Grade</label><select class="form-select" id="wmiRegLookupGrade" data-registration-picker="Grade"><option value="">All Grades</option>@foreach($registrationGrades as $grade)<option value="{{ $grade->id }}">{{ $grade->grade }}</option>@endforeach</select></div>
                <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiRegStudentSearch">Select Student</label><input type="search" class="form-control" id="wmiRegStudentSearch" placeholder="Search student name or ID" autocomplete="off" maxlength="100"></div>
            </div>
            <div class="wmi-enrollment-results mt-2" data-enrollment-results aria-live="polite"></div><div class="small text-primary mt-2" data-enrollment-selected></div>
            <input type="hidden" name="enrollment_id">
        </div></section>
<section class="wmi-registration-card" data-student-information><h3 class="wmi-registration-card-title">Student Information</h3><div class="wmi-registration-card-body"><div class="wmi-form-grid">
            <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiReg_student_name">Full Name (English) <span class="text-danger" data-other-fields hidden>*</span></label><input class="form-control" id="wmiReg_student_name" name="student_name" type="text" maxlength="255" data-snapshot></div>
            <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiRegGender">Gender</label><select class="form-select" id="wmiRegGender" name="gender" data-registration-picker="Gender"><option value="">Select Gender</option><option value="M">Male</option><option value="F">Female</option></select></div>
            <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiReg_date_of_birth">Date of Birth</label><input class="form-control" id="wmiReg_date_of_birth" name="date_of_birth" type="date" max="{{ now()->format('Y-m-d') }}" data-snapshot></div>
            <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiReg_phone_number">Phone Number</label><input class="form-control" id="wmiReg_phone_number" name="phone_number" type="tel" maxlength="40" autocomplete="tel"></div>
            <div class="wmi-telegram-field"><div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiReg_telegram_number">Telegram Number</label><input class="form-control" id="wmiReg_telegram_number" name="telegram_number" type="tel" maxlength="40"></div><label class="form-check mt-2 mb-0"><input class="form-check-input" type="checkbox" id="wmiRegTelegramSame"><span class="form-check-label">Same as Phone Number</span></label></div>
            <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiReg_email_address">Email</label><input class="form-control" id="wmiReg_email_address" name="email_address" type="email" maxlength="255" autocomplete="email"></div>
            <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiReg_current_grade">Current Grade</label><input class="form-control" id="wmiReg_current_grade" name="current_grade" type="text" maxlength="100" placeholder="e.g. 1A, 1, 2A, K3, K" data-snapshot></div>
            <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiReg_campus_study">Campus Study</label><input class="form-control" id="wmiReg_campus_study" name="campus_study" type="text" maxlength="255" readonly data-snapshot></div>
            <div class="wmi-modal-field premium-form-field" data-other-fields hidden><label class="form-label" for="wmiRegSchool">School Name <span class="text-danger">*</span></label><select class="form-select" id="wmiRegSchool" name="wmi_school_id" data-registration-picker="School Name"><option value="">Select School</option>@foreach($registrationSchools as $school)<option value="{{ $school->id }}" data-school-type="{{ $school->school_type }}">{{ $school->name_en }}</option>@endforeach</select><div class="form-hint mt-2">Manage participating schools in WMI Settings → Assign Schools.</div></div>
            <div class="wmi-modal-field premium-form-field" data-other-fields hidden><label class="form-label" for="wmiRegSchoolType">School Type</label><input class="form-control" id="wmiRegSchoolType" type="text" readonly aria-readonly="true"></div>
            <div class="wmi-modal-field premium-form-field" data-western-only><label class="form-label" for="wmiReg_student_name_kh">Full Name (Khmer)</label><input class="form-control" id="wmiReg_student_name_kh" name="student_name_kh" type="text" maxlength="255" data-snapshot></div>
        </div></div></section>
<section class="wmi-registration-card" data-registration-information><h3 class="wmi-registration-card-title">Registration Information</h3><div class="wmi-registration-card-body">        <div class="wmi-form-grid wmi-registration-details-grid">
            <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiRegYear">WMI Year <span class="text-danger">*</span></label><select class="form-select" id="wmiRegYear" name="wmi_year_id" required data-registration-picker="WMI Year"><option value="">Select WMI Year</option>@foreach($registrationYears as $year)<option value="{{ $year->id }}">WMI {{ $year->year }}</option>@endforeach</select></div>
            <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiRegCenter">Exam Center <span class="text-danger">*</span></label><select class="form-select" id="wmiRegCenter" name="wmi_exam_center_id" required data-registration-picker="Exam Center"><option value="">Select Exam Center</option>@foreach($registrationCenters as $center)<option value="{{ $center->id }}" data-year="{{ $center->wmi_year_id }}">{{ $center->name }}</option>@endforeach</select></div>

            <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiRegRegistrar">Campus Registered <span class="text-danger">*</span></label><select class="form-select" id="wmiRegRegistrar" required data-registration-picker="Campus Registered"><option value="online">Online</option>@foreach($registrationCampuses as $campus)<option value="{{ $campus->id }}">{{ $campus->campus_name_en }}</option>@endforeach</select></div>
            <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiRegContestGrade">Contest Grade <span class="text-danger">*</span></label><select class="form-select" id="wmiRegContestGrade" name="grade_contest" required data-registration-picker="Contest Grade"><option value="">Select Contest Grade</option>@foreach(\App\Http\Controllers\WmiRegistrationController::GRADES as $grade)<option value="{{ $grade }}">{{ $grade==='K'?'Kindergarten (K)':'Grade '.$grade }}</option>@endforeach</select></div>
        </div>
</div></section>
<details class="wmi-seat-details mb-3"><summary>Additional Information <span class="text-secondary small">Optional at registration</span></summary><div class="wmi-form-grid mt-3">
            <div class="wmi-modal-field premium-form-field wmi-form-wide"><label class="form-label" for="wmiRegRemark">Remark</label><textarea class="form-control" id="wmiRegRemark" name="remark" rows="2" maxlength="2000"></textarea></div>
        </div></details>
        <p class="form-hint mt-3 mb-0">The WMI Committee assigns the WMI ID later. Exam dates, issue dates, certificates, and awarding details are managed in Settings.</p>
            </div>
        </div>
    </div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit" data-registration-save><i class="ti ti-check me-2" aria-hidden="true"></i><span>Register Candidate</span></button></div>
</form></div></div>

