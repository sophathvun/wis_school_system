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
<section class="wmi-registration-card"><h3 class="wmi-registration-card-title">Registration Information</h3><div class="wmi-registration-card-body">        <div class="wmi-form-grid wmi-registration-details-grid">
            <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiRegYear">WMI Year <span class="text-danger">*</span></label><select class="form-select" id="wmiRegYear" name="wmi_year_id" required data-registration-picker="WMI Year"><option value="">Select WMI Year</option>@foreach($registrationYears as $year)<option value="{{ $year->id }}">WMI {{ $year->year }}</option>@endforeach</select></div>
            <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiRegCenter">Exam Center <span class="text-danger">*</span></label><select class="form-select" id="wmiRegCenter" name="wmi_exam_center_id" required data-registration-picker="Exam Center"><option value="">Select Exam Center</option>@foreach($registrationCenters as $center)<option value="{{ $center->id }}" data-year="{{ $center->wmi_year_id }}">{{ $center->name }}</option>@endforeach</select></div>

            <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiRegRegistrar">Campus Registered <span class="text-danger">*</span></label><select class="form-select" id="wmiRegRegistrar" required data-registration-picker="Campus Registered"><option value="online">Online</option>@foreach($registrationCampuses as $campus)<option value="{{ $campus->id }}">{{ $campus->campus_name_en }}</option>@endforeach</select></div>
            <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiRegContestGrade">Contest Grade <span class="text-danger">*</span></label><select class="form-select" id="wmiRegContestGrade" name="grade_contest" required data-registration-picker="Contest Grade"><option value="">Select Contest Grade</option>@foreach(\App\Http\Controllers\WmiRegistrationController::GRADES as $grade)<option value="{{ $grade }}">{{ $grade==='K'?'Kindergarten (K)':'Grade '.$grade }}</option>@endforeach</select></div>
        </div>
</div></section>
<section class="wmi-registration-card"><h3 class="wmi-registration-card-title">Student Information</h3><div class="wmi-registration-card-body">        <div data-other-fields class="mt-3" hidden><div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiRegSchool">School <span class="text-danger">*</span></label><select class="form-select" id="wmiRegSchool" name="wmi_school_id" data-registration-picker="School"><option value="">Select School</option>@foreach($registrationSchools as $school)<option value="{{ $school->id }}">{{ $school->name_en }}</option>@endforeach</select><div class="form-hint mt-2">Manage participating schools in WMI Settings → Assign Schools.</div></div></div>
        <div class="wmi-form-grid">
            @foreach([['student_name','Student Name (English)','text',255],['student_name_kh','Student Name (Khmer)','text',255],['date_of_birth','Date of Birth','date',null],['current_grade','Current Grade / Class','text',100],['campus_study','Campus Study','text',255]] as [$name,$label,$type,$max])
                <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiReg_{{ $name }}">{{ $label }}</label><input class="form-control" id="wmiReg_{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if($max) maxlength="{{ $max }}" @endif @if($type==='date') max="{{ now()->format('Y-m-d') }}" @endif @if($name==='campus_study') readonly @endif data-snapshot></div>
            @endforeach
            <div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiRegGender">Gender</label><select class="form-select" id="wmiRegGender" name="gender" data-registration-picker="Gender"><option value="">Select Gender</option><option value="M">Male</option><option value="F">Female</option></select></div>
        </div>
</div></section>
<section class="wmi-registration-card"><h3 class="wmi-registration-card-title">Contact Information</h3><div class="wmi-registration-card-body">        <div class="wmi-form-grid">
            @foreach([['phone_number','Phone Number','tel',40],['telegram_number','Telegram Number','tel',40],['email_address','Email Address','email',255]] as [$name,$label,$type,$max])<div class="wmi-modal-field premium-form-field"><label class="form-label" for="wmiReg_{{ $name }}">{{ $label }}</label><input class="form-control" id="wmiReg_{{ $name }}" name="{{ $name }}" type="{{ $type }}" maxlength="{{ $max }}"></div>@endforeach
        </div>
        <details class="wmi-seat-details mt-3"><summary>Additional Information <span class="text-secondary small">Optional at registration</span></summary><div class="wmi-form-grid mt-3">
            <div class="wmi-modal-field premium-form-field wmi-form-wide"><label class="form-label" for="wmiRegRemark">Remark</label><textarea class="form-control" id="wmiRegRemark" name="remark" rows="2" maxlength="2000"></textarea></div>
        </div></details>
</div></section>
        <p class="form-hint mt-3 mb-0">The WMI Committee assigns the WMI ID later. Exam dates, issue dates, certificates, and awarding details are managed in Settings.</p>
            </div>
        </div>
    </div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit" data-registration-save><i class="ti ti-check me-2" aria-hidden="true"></i><span>Register Candidate</span></button></div>
</form></div></div>

