<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolInfo;
use App\Models\Session;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Grade;
use App\Models\AcademicTrack;
use App\Models\Occupation;
use App\Models\Province;
use App\Models\District;
use App\Models\Commune;
use App\Models\Village;
use App\Services\FamilyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SummerSchoolController
{
    public function __construct(private readonly FamilyService $familyService) {}

    public function index() { return view('summer-school'); }

    public function options()
    {
        return response()->json([
            'academicYears' => AcademicYear::where('period_type', 'summer')->whereIn('lifecycle_status', ['pending', 'started'])->orderByDesc('id')->get(['id', 'academic_year', 'parent_academic_year_id', 'lifecycle_status']),
            'regularAcademicYears' => AcademicYear::where('period_type', 'regular')->whereIn('lifecycle_status', ['pending', 'started'])->orderByDesc('id')->get(['id', 'academic_year', 'lifecycle_status']),
            'campuses' => SchoolInfo::where('status', 1)->orderBy('campus_name_en')->get(['id', 'campus_name_en']),
            'grades' => Grade::where('status', 1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->get(['id', 'grade']),
            'classes' => SchoolClass::where('status', 1)->orderBy('class_name')->get(['id', 'class_name', 'grade_id']),
            'sessions' => Session::where('status', 1)->orderBy('session_order')->get(['id', 'session_short_name']),
            'countries' => \App\Models\Country::where('status', 1)->orderBy('country_name_en')->get(['id', 'country_name_en', 'country_name_kh', 'nationality_name_en', 'nationality_name_kh']),
            'academicTracks' => AcademicTrack::where('status', 1)->orderBy('name_en')->get(['id', 'grade_id', 'name_en', 'name_kh', 'code']),
            'occupations' => Occupation::where('status', 1)->orderBy('occupation_name_en')->get(['id', 'occupation_name_en', 'occupation_name_kh']),
            'studentEnrollments' => StudentEnrollment::with(['student:id,student_id,full_name_en,full_name_kh', 'academicYear:id,academic_year,period_type', 'campus:id,campus_name_en', 'grade:id,grade', 'schoolClass:id,class_name'])
                ->whereHas('academicYear', fn ($year) => $year->where('period_type', 'regular'))
                ->get(['id', 'student_id', 'academic_year_id', 'campus_id', 'grade_id', 'class_id']),
        ]);
    }

    public function locationOptions()
    {
        return response()->json([
            'provinces' => Province::where('status', 1)->orderBy('province_name_en')->get(['id', 'country_id', 'province_name_en', 'province_name_kh']),
            'districts' => District::where('status', 1)->orderBy('district_name_en')->get(['id', 'province_id', 'district_name_en', 'district_name_kh']),
            'communes' => Commune::where('status', 1)->orderBy('commune_name_en')->get(['id', 'district_id', 'commune_name_en', 'commune_name_kh']),
            'villages' => Village::where('status', 1)->orderBy('village_name_en')->get(['id', 'commune_id', 'village_name_en', 'village_name_kh']),
        ]);
    }

    public function fetch(Request $request)
    {
        $query = StudentEnrollment::with(['student:id,student_id,full_name_en,full_name_kh', 'academicYear:id,academic_year,lifecycle_status', 'campus:id,campus_name_en', 'grade:id,grade', 'schoolClass:id,class_name', 'session:id,session_short_name'])
            ->whereHas('academicYear', fn ($year) => $year->where('period_type', 'summer'))
            ->when($request->filled('academic_year_id'), fn ($q) => $q->where('academic_year_id', $request->integer('academic_year_id')))
            ->latest('id');
        return response()->json($query->paginate($request->integer('perPage', 15)));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => ['required', Rule::exists('tb_academic_year', 'id')->where(fn ($query) => $query->where('period_type', 'summer')->whereIn('lifecycle_status', ['pending', 'started']))],
            'enrollment_origin' => ['required', Rule::in(['internal', 'external'])],
            'student_record_id' => ['nullable', 'exists:tb_student,id', 'required_if:enrollment_origin,internal'],
            'external_student_id' => ['nullable', 'string', 'max:60'],
            'full_name_en' => ['required_if:enrollment_origin,external', 'nullable', 'string', 'max:160'],
            'full_name_kh' => ['nullable', 'string', 'max:160'],
            'gender' => ['nullable', 'string', 'max:30'],
            'gender_kh' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'nationality_country_id' => ['nullable', 'exists:tb_country,id'],
            'home_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:160'],
            'birth_country_id' => ['nullable', 'exists:tb_country,id'],
            'birth_province_id' => ['nullable', 'exists:tb_province,id'],
            'birth_district_id' => ['nullable', 'exists:tb_district,id'],
            'birth_commune_id' => ['nullable', 'exists:tb_commune,id'],
            'birth_village_id' => ['nullable', 'exists:tb_village,id'],
            'address_country_id' => ['nullable', 'exists:tb_country,id'],
            'address_province_id' => ['nullable', 'exists:tb_province,id'],
            'address_district_id' => ['nullable', 'exists:tb_district,id'],
            'address_commune_id' => ['nullable', 'exists:tb_commune,id'],
            'address_village_id' => ['nullable', 'exists:tb_village,id'],
            'address_house_no_en' => ['nullable', 'string', 'max:120'],
            'address_house_no_kh' => ['nullable', 'string', 'max:120'],
            'address_street_en' => ['nullable', 'string', 'max:160'],
            'address_street_kh' => ['nullable', 'string', 'max:160'],
            'current_address_en' => ['nullable', 'string', 'max:5000'],
            'current_address_kh' => ['nullable', 'string', 'max:5000'],
            'previous_school' => ['required_if:enrollment_origin,external', 'nullable', 'string', 'max:180'],
            'continue_at_western' => ['required_if:enrollment_origin,external', 'nullable', Rule::in(['yes', 'no', 'pending'])],
            'mother_name_en' => ['required_if:enrollment_origin,external', 'nullable', 'string', 'max:160'],
            'mother_name_kh' => ['nullable', 'string', 'max:160'],
            'mother_occupation_en' => ['nullable', 'string', 'max:120'],
            'mother_occupation_kh' => ['nullable', 'string', 'max:120'],
            'mother_nationality_en' => ['nullable', 'string', 'max:80'],
            'mother_nationality_kh' => ['nullable', 'string', 'max:80'],
            'mother_phone' => ['required_if:enrollment_origin,external', 'nullable', 'string', 'max:50'],
            'mother_workplace' => ['nullable', 'string', 'max:160'],
            'father_name_en' => ['required_if:enrollment_origin,external', 'nullable', 'string', 'max:160'],
            'father_name_kh' => ['nullable', 'string', 'max:160'],
            'father_occupation_en' => ['nullable', 'string', 'max:120'],
            'father_occupation_kh' => ['nullable', 'string', 'max:120'],
            'father_nationality_en' => ['nullable', 'string', 'max:80'],
            'father_nationality_kh' => ['nullable', 'string', 'max:80'],
            'father_phone' => ['required_if:enrollment_origin,external', 'nullable', 'string', 'max:50'],
            'father_workplace' => ['nullable', 'string', 'max:160'],
            'guardian_name_en' => ['nullable', 'string', 'max:160'],
            'guardian_name_kh' => ['nullable', 'string', 'max:160'],
            'guardian_occupation_id' => ['nullable', 'exists:tb_occupation,id'],
            'guardian_nationality_country_id' => ['nullable', 'exists:tb_country,id'],
            'guardian_phone' => ['nullable', 'string', 'max:50'],
            'guardian_workplace' => ['nullable', 'string', 'max:160'],
            'mother_occupation_id' => ['nullable', 'exists:tb_occupation,id'],
            'mother_nationality_country_id' => ['nullable', 'exists:tb_country,id'],
            'father_occupation_id' => ['nullable', 'exists:tb_occupation,id'],
            'father_nationality_country_id' => ['nullable', 'exists:tb_country,id'],
            'campus_id' => ['required', 'exists:tb_school_info,id'],
            'grade_id' => ['required', 'exists:tb_grade,id'],
            'class_id' => ['required', 'exists:tb_class,id'],
            'session_id' => ['nullable', 'exists:tb_session,id'],
            'academic_track_id' => ['nullable', 'exists:tb_academic_track,id'],
            'summer_remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $enrollment = DB::transaction(function () use ($data) {
            $academicYear = AcademicYear::findOrFail($data['academic_year_id']);
            if ($data['enrollment_origin'] === 'internal') {
                $student = Student::findOrFail($data['student_record_id']);
            } else {
                $token = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) ($data['external_student_id'] ?: 'EXT')), 0, 8));
                $student = Student::create([
                    'student_no' => 'SUM-' . $academicYear->id . '-' . strtoupper(substr(uniqid(), -6)),
                    'student_id' => 'SUM-' . $token . '-' . strtoupper(substr(uniqid(), -5)),
                    'full_name_en' => $data['full_name_en'],
                    'full_name_kh' => $data['full_name_kh'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'gender_kh' => $data['gender_kh'] ?? null,
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'nationality_country_id' => $data['nationality_country_id'] ?? null,
                    'home_phone' => $data['home_phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'birth_country_id' => $data['birth_country_id'] ?? null,
                    'birth_province_id' => $data['birth_province_id'] ?? null,
                    'birth_district_id' => $data['birth_district_id'] ?? null,
                    'birth_commune_id' => $data['birth_commune_id'] ?? null,
                    'birth_village_id' => $data['birth_village_id'] ?? null,
                    'address_country_id' => $data['address_country_id'] ?? null,
                    'address_province_id' => $data['address_province_id'] ?? null,
                    'address_district_id' => $data['address_district_id'] ?? null,
                    'address_commune_id' => $data['address_commune_id'] ?? null,
                    'address_village_id' => $data['address_village_id'] ?? null,
                    'address_house_no_en' => $data['address_house_no_en'] ?? null,
                    'address_house_no_kh' => $data['address_house_no_kh'] ?? null,
                    'address_street_en' => $data['address_street_en'] ?? null,
                    'address_street_kh' => $data['address_street_kh'] ?? null,
                    'current_address_en' => $data['current_address_en'] ?? null,
                    'current_address_kh' => $data['current_address_kh'] ?? null,
                    'family_number' => 'SUM-' . $academicYear->id . '-' . strtoupper(substr(uniqid(), -6)),
                    'status' => 1,
                ]);
                $family = $this->familyService->syncStudentFamily($student, $student->family_number);
                $this->familyService->syncEnrollmentMember($family, 'mother', [
                    'full_name_en' => $data['mother_name_en'], 'full_name_kh' => $data['mother_name_kh'] ?? null,
                    'occupation_id' => $data['mother_occupation_id'] ?? null, 'nationality_country_id' => $data['mother_nationality_country_id'] ?? null,
                    'occupation_en' => $data['mother_occupation_en'] ?? null, 'occupation_kh' => $data['mother_occupation_kh'] ?? null,
                    'nationality_en' => $data['mother_nationality_en'] ?? null, 'nationality_kh' => $data['mother_nationality_kh'] ?? null,
                    'phone' => $data['mother_phone'], 'workplace' => $data['mother_workplace'] ?? null,
                ]);
                $this->familyService->syncEnrollmentMember($family, 'father', [
                    'full_name_en' => $data['father_name_en'], 'full_name_kh' => $data['father_name_kh'] ?? null,
                    'occupation_id' => $data['father_occupation_id'] ?? null, 'nationality_country_id' => $data['father_nationality_country_id'] ?? null,
                    'occupation_en' => $data['father_occupation_en'] ?? null, 'occupation_kh' => $data['father_occupation_kh'] ?? null,
                    'nationality_en' => $data['father_nationality_en'] ?? null, 'nationality_kh' => $data['father_nationality_kh'] ?? null,
                    'phone' => $data['father_phone'], 'workplace' => $data['father_workplace'] ?? null,
                ]);
                $this->familyService->syncEnrollmentMember($family, 'guardian', [
                    'full_name_en' => $data['guardian_name_en'] ?? null, 'full_name_kh' => $data['guardian_name_kh'] ?? null,
                    'occupation_id' => $data['guardian_occupation_id'] ?? null, 'nationality_country_id' => $data['guardian_nationality_country_id'] ?? null,
                    'phone' => $data['guardian_phone'] ?? null, 'workplace' => $data['guardian_workplace'] ?? null,
                ]);
            }

            return StudentEnrollment::create([
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'campus_id' => $data['campus_id'],
                'grade_id' => $data['grade_id'],
                'class_id' => $data['class_id'],
                'session_id' => $data['session_id'] ?? null,
                'academic_track_id' => $data['academic_track_id'] ?? null,
                'status' => 1,
                'student_type' => $data['enrollment_origin'] === 'external' ? 'new' : 'old',
                'enrollment_origin' => $data['enrollment_origin'],
                'external_student_id' => $data['external_student_id'] ?? null,
                'previous_school' => $data['previous_school'] ?? null,
                'continue_at_western' => $data['continue_at_western'] ?? null,
                'summer_remarks' => $data['summer_remarks'] ?? null,
                'enrollment_status' => $academicYear->lifecycle_status === 'started' ? 'active' : 'pending',
                'enrolled_on' => now()->toDateString(),
            ]);
        });

        return response()->json(['status' => 'success', 'message' => 'Summer School student registered successfully.', 'data' => $enrollment->load(['student', 'academicYear', 'campus', 'grade', 'schoolClass', 'session'])], 201);
    }

    public function convertToWestern(Request $request, StudentEnrollment $enrollment)
    {
        $data = $request->validate([
            'academic_year_id' => ['required', Rule::exists('tb_academic_year', 'id')->where(fn ($query) => $query->where('period_type', 'regular')->whereIn('lifecycle_status', ['pending', 'started']))],
            'campus_id' => ['required', 'exists:tb_school_info,id'],
            'grade_id' => ['required', 'exists:tb_grade,id'],
            'class_id' => ['required', 'exists:tb_class,id'],
            'session_id' => ['nullable', 'exists:tb_session,id'],
        ]);

        abort_unless($enrollment->enrollment_origin === 'external' && $enrollment->continue_at_western === 'yes' && $enrollment->academicYear?->period_type === 'summer', 422, 'Only external Summer students marked to continue at Western can be converted.');

        $regularYear = AcademicYear::findOrFail($data['academic_year_id']);
        if (StudentEnrollment::where('student_id', $enrollment->student_id)->where('academic_year_id', $regularYear->id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'This student already has an enrollment in the selected academic year.'], 422);
        }

        $regularEnrollment = StudentEnrollment::create([
            'student_id' => $enrollment->student_id,
            'academic_year_id' => $regularYear->id,
            'campus_id' => $data['campus_id'],
            'grade_id' => $data['grade_id'],
            'class_id' => $data['class_id'],
            'session_id' => $data['session_id'] ?? null,
            'academic_track_id' => $data['academic_track_id'] ?? null,
            'status' => 1,
            'student_type' => 'new',
            'enrollment_origin' => 'internal',
            'continue_at_western' => 'yes',
            'previous_school' => $enrollment->previous_school,
            'enrollment_status' => $regularYear->lifecycle_status === 'started' ? 'active' : 'pending',
            'enrolled_on' => now()->toDateString(),
            'notes' => 'Converted from Summer School enrollment #' . $enrollment->id,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Student converted to Western enrollment successfully.', 'data' => $regularEnrollment->load(['student', 'academicYear', 'campus', 'grade', 'schoolClass', 'session'])], 201);
    }
}
