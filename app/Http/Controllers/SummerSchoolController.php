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
use App\Models\StudentDocumentType;
use App\Models\Occupation;
use App\Models\Family;
use App\Models\Province;
use App\Models\District;
use App\Models\Commune;
use App\Models\Village;
use App\Services\FamilyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SummerSchoolController
{
    public function __construct(private readonly FamilyService $familyService) {}

    public function index() { return view('summer-school'); }

    public function options()
    {
        return response()->json([
            'nextStudentNo' => $this->nextStudentNumber(),
            'academicYears' => AcademicYear::where('period_type', 'summer')->where('lifecycle_status', 'started')->orderByDesc('id')->get(['id', 'academic_year', 'parent_academic_year_id', 'lifecycle_status']),
            'allSummerAcademicYears' => AcademicYear::withTrashed()->where('period_type', 'summer')->orderByDesc('id')->get(['id', 'academic_year', 'parent_academic_year_id', 'lifecycle_status']),
            'regularAcademicYears' => AcademicYear::where('period_type', 'regular')->whereIn('lifecycle_status', ['finished', 'started'])->orderByDesc('academic_year')->get(['id', 'academic_year', 'lifecycle_status']),
            'campuses' => SchoolInfo::where('status', 1)->orderBy('campus_name_en')->get(['id', 'campus_name_en']),
            'grades' => Grade::where('status', 1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->get(['id', 'grade']),
            'classes' => SchoolClass::where('status', 1)->orderBy('class_name')->get(['id', 'class_name', 'grade_id']),
            'sessions' => Session::where('status', 1)->orderBy('session_order')->get(['id', 'session_short_name']),
            'countries' => \App\Models\Country::where('status', 1)->orderBy('country_name_en')->get(['id', 'country_name_en', 'country_name_kh', 'nationality_name_en', 'nationality_name_kh', 'flag_path']),
            'academicTracks' => AcademicTrack::where('status', 1)->orderBy('name_en')->get(['id', 'grade_id', 'name_en', 'name_kh', 'code']),
            // Keep the English field display-ready so older cached Summer-school
            // bundles still render the Khmer/English label.  Existing records
            // created by the original migration may have an empty name_kh, so
            // provide the standard Khmer labels as a safe fallback.
            'documentTypes' => StudentDocumentType::where('status', 1)->orderBy('sort_order')->get(['id', 'type_key', 'name_en', 'name_kh'])
                ->map(function ($type) {
                    $fallbackKhmer = [
                        'birth-certificate' => 'សំបុត្រកំណើត',
                        'passport' => 'លិខិតឆ្លងដែន',
                        'identity-card' => 'អត្តសញ្ញាណប័ណ្ណ',
                        'medical-record' => 'កំណត់ត្រាវេជ្ជសាស្ត្រ',
                        'previous-school-record' => 'កំណត់ត្រាសាលាចាស់',
                        'family-record' => 'ឯកសារគ្រួសារ',
                        'other' => 'ផ្សេងៗ',
                        'enrollment-letter' => 'លិខិតចុះឈ្មោះចូលរៀន',
                    ][$type->type_key] ?? null;
                    $khmer = trim((string) ($type->name_kh ?: $fallbackKhmer));
                    $english = trim((string) $type->name_en);

                    return [
                        'id' => $type->id,
                        // Combined value keeps compatibility with cached JS.
                        'name_en' => $khmer !== '' ? "{$khmer} / {$english}" : $english,
                        'name_kh' => $khmer !== '' ? $khmer : null,
                    ];
                }),
            'occupations' => Occupation::where('status', 1)->orderBy('occupation_name_en')->get(['id', 'occupation_name_en', 'occupation_name_kh']),
        ]);
    }

    private function nextStudentNumber(): string
    {
        $max = (int) Student::query()->lockForUpdate()->selectRaw('MAX(CAST(student_no AS UNSIGNED)) as max_no')->value('max_no');
        return str_pad((string) ($max + 1), 8, '0', STR_PAD_LEFT);
    }

    public function studentOptions()
    {
        return response()->json(StudentEnrollment::with([
            'student:id,student_id,full_name_en,full_name_kh,gender,gender_kh,date_of_birth,photo_path',
            'student.familyMembers:id,full_name_en,full_name_kh,relationship_type,phone',
            'academicYear' => fn ($year) => $year->withTrashed()->select(['id', 'academic_year', 'period_type', 'lifecycle_status']),
            'campus:id,campus_name_en',
            'grade:id,grade',
            'schoolClass:id,class_name',
            'schoolGroup:id,group_name',
            'session:id,session_short_name',
        ])->when(request()->filled('academic_year_id'), fn ($q) => $q->where('academic_year_id', request()->integer('academic_year_id')))
            ->when(request()->filled('campus_id'), fn ($q) => $q->where('campus_id', request()->integer('campus_id')))
            ->when(request()->filled('grade_id'), fn ($q) => $q->where('grade_id', request()->integer('grade_id')))
            ->whereHas('academicYear', fn ($year) => $year->withTrashed()
                ->where('period_type', 'regular')
                ->whereIn('lifecycle_status', ['finished', 'started']))
            ->get(['id', 'student_id', 'academic_year_id', 'campus_id', 'grade_id', 'class_id', 'group_id', 'session_id', 'enrollment_status', 'status']));
    }

    public function westernFilterOptions(Request $request)
    {
        $yearId = $request->integer('academic_year_id');
        return response()->json(SchoolInfo::where('status', 1)
            ->whereIn('id', StudentEnrollment::where('academic_year_id', $yearId)->select('campus_id'))
            ->orderBy('campus_name_en')->get(['id', 'campus_name_en']));
    }

    public function westernGradeOptions(Request $request)
    {
        return response()->json(DB::table('tb_student_enrollment as e')
            ->join('tb_grade as g', 'g.id', '=', 'e.grade_id')
            ->join('tb_class as c', 'c.id', '=', 'e.class_id')
            ->where('e.academic_year_id', $request->integer('academic_year_id'))
            ->where('e.campus_id', $request->integer('campus_id'))
            ->select('e.grade_id', 'e.class_id', 'g.grade', 'c.class_name')
            ->distinct()->orderBy('g.grade')->orderBy('c.class_name')->get());
    }

    public function familyOptions()
    {
        return response()->json(
            Family::with(['members' => fn ($query) => $query
                ->whereIn('relationship_type', ['mother', 'father', 'guardian'])
                ->select(['id', 'family_id', 'relationship_type', 'full_name_en', 'full_name_kh', 'occupation_en', 'occupation_kh', 'nationality_en', 'nationality_kh', 'phone', 'workplace'])])
                ->get(['id', 'family_number'])
                ->mapWithKeys(fn ($family) => [$family->family_number => $family->members->keyBy('relationship_type')->map(fn ($member) => $member->only([
                    'full_name_en', 'full_name_kh', 'occupation_en', 'occupation_kh', 'nationality_en', 'nationality_kh', 'phone', 'workplace',
                ]))->all()])->all(),
        );
    }

    public function periodOptions()
    {
        return response()->json(AcademicYear::where('period_type', 'summer')
            ->where('lifecycle_status', 'started')
            ->orderByDesc('id')
            ->get(['id', 'academic_year', 'parent_academic_year_id', 'lifecycle_status']));
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
        $query = StudentEnrollment::with(['student:id,student_id,full_name_en,full_name_kh,photo_path', 'academicYear' => fn ($year) => $year->withTrashed()->select(['id', 'academic_year', 'lifecycle_status', 'period_type']), 'campus:id,campus_name_en', 'grade:id,grade', 'schoolClass:id,class_name', 'academicTrack:id,name_en', 'session:id,session_short_name'])
            ->whereIn('academic_year_id', AcademicYear::withTrashed()
                ->where(fn ($year) => $year->where('period_type', 'summer')->orWhere('academic_year', 'like', 'Summer %'))
                ->select('id'))
            ->when($request->filled('academic_year_id'), fn ($q) => $q->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('campus_id'), fn ($q) => $q->where('campus_id', $request->integer('campus_id')))
            ->when($request->filled('grade_id'), fn ($q) => $q->where('grade_id', $request->integer('grade_id')))
            ->when($request->filled('session_id'), fn ($q) => $q->where('session_id', $request->integer('session_id')))
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->integer('student_id')))
            ->when($request->filled('enrollment_status'), fn ($q) => $q->where('enrollment_status', $request->string('enrollment_status')->toString()))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->string('search')->toString() . '%';
                $q->whereHas('student', fn ($student) => $student->where('student_id', 'like', $term)->orWhere('full_name_en', 'like', $term)->orWhere('full_name_kh', 'like', $term));
            })
            ->latest('id');
        return response()->json($query->paginate($request->integer('perPage', 15)));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => ['required', Rule::exists('tb_academic_year', 'id')->where(fn ($query) => $query->where('period_type', 'summer')->where('lifecycle_status', 'started'))],
            'enrollment_origin' => ['required', Rule::in(['internal', 'external'])],
            'student_record_id' => ['nullable', 'exists:tb_student,id', 'required_if:enrollment_origin,internal'],
            'external_student_id' => ['nullable', 'string', 'max:60'],
            'student_no' => ['nullable', 'string', 'max:60'],
            'student_id' => ['required_if:enrollment_origin,external', 'nullable', 'string', 'max:60', Rule::unique('tb_student', 'student_id')],
            'existing_family_number' => ['nullable', 'string', 'max:80'],
            'family_number' => ['nullable', 'string', 'max:80'],
            'full_name_en' => ['required_if:enrollment_origin,external', 'nullable', 'string', 'max:160'],
            'full_name_kh' => ['nullable', 'string', 'max:160'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
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
            'previous_school' => ['nullable', 'string', 'max:180'],
            'tested_by' => ['nullable', 'string', 'max:160'],
            'experienced_english' => ['nullable', 'string', 'max:5000'],
            'test_result' => ['nullable', 'string', 'max:5000'],
            'document_type_id' => ['nullable', 'exists:tb_student_document_type,id'],
            'document_title' => ['nullable', 'string', 'max:160'],
            'document_number' => ['nullable', 'string', 'max:120'],
            'document_description' => ['nullable', 'string', 'max:5000'],
            'document_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:2048'],
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
            'session_id' => ['required', 'exists:tb_session,id'],
            'academic_track_id' => ['nullable', 'exists:tb_academic_track,id'],
            'enrollment_status' => ['nullable', Rule::in(['active', 'pending', 'completed', 'withdrawn'])],
            'enrolled_on' => ['nullable', 'date'],
            'summer_remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $enrollment = DB::transaction(function () use ($data, $request) {
            $academicYear = AcademicYear::findOrFail($data['academic_year_id']);
            if ($data['enrollment_origin'] === 'internal') {
                $student = Student::findOrFail($data['student_record_id']);
            } else {
                $student = Student::create([
                    'student_no' => $this->nextStudentNumber(),
                    'student_id' => $data['student_id'],
                    'full_name_en' => $data['full_name_en'],
                    'full_name_kh' => $data['full_name_kh'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'gender_kh' => $data['gender_kh'] ?? null,
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'nationality_country_id' => $data['nationality_country_id'] ?? null,
                    'home_phone' => $data['home_phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'tested_by' => $data['tested_by'] ?? null,
                    'experienced_english' => $data['experienced_english'] ?? null,
                    'test_result' => $data['test_result'] ?? null,
                    'remarks' => $data['summer_remarks'] ?? null,
                    'tested_by' => $data['tested_by'] ?? null,
                    'experienced_english' => $data['experienced_english'] ?? null,
                    'test_result' => $data['test_result'] ?? null,
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
                    'family_number' => $data['existing_family_number'] ?: ($data['family_number'] ?: 'F' . $data['student_id']),
                    'status' => 1,
                ]);
                if ($request->hasFile('photo')) {
                    $photoName = preg_replace('/[^A-Za-z0-9]+/', '_', trim((string) $student->full_name_en)) ?: 'Student';
                    $student->photo_path = $request->file('photo')->storeAs(
                        'student_photos',
                        trim($photoName, '_') . '_' . $student->student_id . '.' . strtolower($request->file('photo')->extension()),
                        'public',
                    );
                    $student->save();
                }
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
                if ($request->hasFile('document_file')) {
                    $file = $request->file('document_file');
                    $student->documents()->create([
                        'document_type_id' => $data['document_type_id'] ?? null,
                        'title' => $data['document_title'] ?? $file->getClientOriginalName(),
                        'document_number' => $data['document_number'] ?? null,
                        'description' => $data['document_description'] ?? null,
                        'file_path' => $file->store('student_documents', 'public'),
                        'original_filename' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'status' => 'active',
                        'uploaded_by' => auth()->id(),
                    ]);
                }
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
                'enrollment_status' => $data['enrollment_status'] ?? ($academicYear->lifecycle_status === 'started' ? 'active' : 'pending'),
                'enrolled_on' => $data['enrolled_on'] ?? now()->toDateString(),
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
