<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolInfo;
use App\Models\Session;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use App\Services\FamilyService;
use App\Models\Country;
use App\Models\Occupation;
use App\Models\StudentEnrollmentHistory;
use App\Models\Family;
use App\Models\AcademicTrack;
use App\Models\EnrollmentWorkflowAction;

class StudentEnrollmentController
{
    public function __construct(private readonly FamilyService $familyService) {}
    public function index()
    {
        return view('student-enrollment', [
            'listAcademicYears' => AcademicYear::orderByDesc('academic_year')
                ->get(['id', 'academic_year', 'period_type', 'parent_academic_year_id', 'lifecycle_status', 'start_date', 'end_date']),
        ]);
    }

    public function listOptions()
    {
        return response()->json($this->enrollmentListOptions());
    }

    public function quickOptions()
    {
        return response()->json([
            'nextStudentNo' => $this->nextStudentNumber(),
            'families' => Student::query()
                ->whereNotNull('family_number')
                ->where('family_number', '!=', '')
                ->select('family_number')
                ->selectRaw('MIN(full_name_en) as full_name_en')
                ->groupBy('family_number')
                ->orderBy('family_number')
                ->get(),
        ]);
    }

    public function options()
    {
        return response()->json([
            'academicYears' => AcademicYear::whereIn('lifecycle_status', ['started', 'pending'])
                ->latest('id')
                ->get(['id', 'academic_year', 'period_type', 'parent_academic_year_id', 'lifecycle_status', 'start_date', 'end_date']),
            // All-year list filters are supplied by /list-options.
            'grades' => Grade::where('status', 1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->get(['id', 'grade', 'grade_short_name', 'grade_order']),
            'classes' => SchoolClass::where('status', 1)->orderByRaw('CAST(class_order AS UNSIGNED)')->get(['id', 'class_name', 'grade_id']),
            'enrollmentGradeClasses' => StudentEnrollment::query()
                ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
                ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
                ->select('tb_student_enrollment.grade_id', 'tb_student_enrollment.class_id', 'tb_grade.grade', 'tb_grade.grade_order', 'tb_class.class_name', 'tb_class.class_order')
                ->distinct()
                ->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')
                ->orderByRaw('CAST(tb_class.class_order AS UNSIGNED)')
                ->orderBy('tb_class.class_name')
                ->get(),
            'academicTracks' => AcademicTrack::where('status', 1)->orderBy('stream_type')->orderBy('language')->get(['id', 'grade_id', 'name_en', 'name_kh', 'code', 'stream_type', 'language']),
            'campuses' => SchoolInfo::orderBy('campus_name_en')->get(['id', 'campus_name_en', 'campus_name_kh', 'status']),
            'sessions' => Session::where('status', 1)->orderByRaw('CAST(session_order AS UNSIGNED)')->get(['id', 'session_name', 'session_short_name']),
            'enrollmentStudents' => Student::whereHas('enrollments')
                ->orderByRaw("COALESCE(NULLIF(full_name_en, ''), full_name_kh) asc")
                ->get(['id', 'student_id', 'full_name_en', 'full_name_kh']),
            // The list filter rows are loaded by /list-options separately;
            // avoid duplicating this potentially very large payload here.
            'families' => Student::query()
                ->whereNotNull('family_number')
                ->where('family_number', '!=', '')
                ->select('family_number')
                ->selectRaw('MIN(full_name_en) as full_name_en')
                ->groupBy('family_number')
                ->orderBy('family_number')
                ->get(),
            'nextStudentNo' => $this->nextStudentNumber(),
            'countries' => Country::where('status', 1)->orderBy('country_name_en')->get(['id', 'country_name_en', 'country_name_kh', 'nationality_name_en', 'nationality_name_kh', 'flag_path']),
            'occupations' => Occupation::where('status', 1)->orderBy('occupation_name_en')->get(['id', 'occupation_name_en', 'occupation_name_kh']),
        ]);
    }

    public function fetchData(Request $request)
    {
        $search = $request->query('search');
        $sortBy = (string) $request->query('sortBy', 'student_name');
        $sortDir = $request->query('sortDir', 'asc') === 'desc' ? 'desc' : 'asc';
        $gradeClass = (string) $request->query('grade_class', '');
        [$filterGradeId, $filterClassId] = array_pad(explode(':', $gradeClass, 2), 2, null);
        $query = StudentEnrollment::query()
            ->select('tb_student_enrollment.*')
            ->addSelect([
                'was_transferred_from_other_campus' => EnrollmentWorkflowAction::query()
                    ->selectRaw('1')
                    ->whereColumn('target_enrollment_id', 'tb_student_enrollment.id')
                    ->whereIn('action_type', ['transfer', 'class_transfer', 'selected_transfer'])
                    ->whereColumn('from_campus_id', '!=', 'to_campus_id')
                    ->limit(1),
                'transfer_from_campus_name' => EnrollmentWorkflowAction::query()
                    ->join('tb_school_info as transfer_from_campus', 'transfer_from_campus.id', '=', 'tb_student_enrollment_workflow.from_campus_id')
                    ->select('transfer_from_campus.campus_name_en')
                    ->whereColumn('target_enrollment_id', 'tb_student_enrollment.id')
                    ->whereIn('action_type', ['transfer', 'class_transfer', 'selected_transfer'])
                    ->whereColumn('from_campus_id', '!=', 'to_campus_id')
                    ->latest('tb_student_enrollment_workflow.id')
                    ->limit(1),
            ])
            ->leftJoin('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
            ->leftJoin('tb_academic_year', 'tb_academic_year.id', '=', 'tb_student_enrollment.academic_year_id')
            ->leftJoin('tb_school_info', 'tb_school_info.id', '=', 'tb_student_enrollment.campus_id')
            ->leftJoin('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
            ->leftJoin('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
            ->leftJoin('tb_academic_track', 'tb_academic_track.id', '=', 'tb_student_enrollment.academic_track_id')
            ->leftJoin('tb_session', 'tb_session.id', '=', 'tb_student_enrollment.session_id')
            // Edit only needs the student's stored location IDs. Loading every
            // location relationship for every enrollment makes the list and
            // Edit action unnecessarily slow; family members remain eager-loaded.
            ->with([
                // Family contacts are loaded only when the profile or edit
                // form is opened. Loading every member for every table row
                // makes the initial list request unnecessarily expensive.
                'student.families',
                'campus',
                'academicYear' => fn ($year) => $year->withTrashed(),
                'grade',
                'schoolClass',
                'academicTrack',
                'schoolGroup',
                'session',
            ])
            ->when($request->filled('academic_year_id'), fn ($q) => $q->where('tb_student_enrollment.academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('campus_id'), fn ($q) => $q->where('tb_student_enrollment.campus_id', $request->integer('campus_id')))
            ->when($filterGradeId && $filterClassId, fn ($q) => $q->where('tb_student_enrollment.grade_id', (int) $filterGradeId)->where('tb_student_enrollment.class_id', (int) $filterClassId))
            ->when($request->filled('group_id'), fn ($q) => $q->where('tb_student_enrollment.session_id', $request->integer('group_id')))
            ->when($request->filled('student_id'), fn ($q) => $q->where('tb_student_enrollment.student_id', $request->integer('student_id')))
            ->when($request->filled('enrollment_status'), fn ($q) => $q->where('tb_student_enrollment.enrollment_status', $request->string('enrollment_status')->toString()))
            ->when($search, function ($q, $term) {
                $q->where(function ($query) use ($term) {
                    $query->whereHas('student', function ($studentQuery) use ($term) {
                        $studentQuery->where('student_no', 'like', "%{$term}%")
                            ->orWhere('student_id', 'like', "%{$term}%")
                            ->orWhere('family_number', 'like', "%{$term}%")
                            ->orWhere('full_name_en', 'like', "%{$term}%")
                            ->orWhere('full_name_kh', 'like', "%{$term}%");
                    })->orWhereHas('campus', function ($campusQuery) use ($term) {
                        $campusQuery->where('campus_name_en', 'like', "%{$term}%")
                            ->orWhere('campus_name_kh', 'like', "%{$term}%");
                    });
                });
            });

        match ($sortBy) {
            'student_id' => $query->orderBy('tb_student.student_id', $sortDir),
            'student_name' => $query->orderByRaw("LOWER(NULLIF(tb_student.full_name_en, '')) {$sortDir}")
                ->orderBy('tb_student.student_id', $sortDir),
            'student_type' => $query->orderBy('tb_student_enrollment.student_type', $sortDir),
            'academic_year' => $query->orderBy('tb_academic_year.academic_year', $sortDir),
            'campus' => $query->orderBy('tb_school_info.campus_name_en', $sortDir),
            'grade' => $query->orderByRaw("CAST(tb_grade.grade_order AS UNSIGNED) {$sortDir}")->orderByRaw("CAST(tb_class.class_order AS UNSIGNED) {$sortDir}")->orderBy('tb_class.class_name', $sortDir),
            'academic_track' => $query->orderBy('tb_academic_track.name_en', $sortDir),
            'group' => $query->orderByRaw("CAST(tb_session.session_order AS UNSIGNED) {$sortDir}")->orderBy('tb_session.session_short_name', $sortDir),
            'status' => $query->orderBy('tb_student_enrollment.enrollment_status', $sortDir),
            default => $query->orderByDesc('tb_student_enrollment.id'),
        };

        return response()->json($query->paginate($request->query('perPage', 10)));
    }

    public function filterOptions(Request $request)
    {
        return response()->json($this->enrollmentDependentFilterOptions($request));
    }

    public function stats(Request $request)
    {
        return response()->json($this->enrollmentListStats($request));
    }

    public function studentAcademicYears(Student $student)
    {
        return response()->json([
            'student' => $student->only(['id', 'student_id', 'full_name_en', 'full_name_kh']),
            'enrollments' => StudentEnrollment::query()
                ->select('tb_student_enrollment.*')
                ->leftJoin('tb_academic_year', 'tb_academic_year.id', '=', 'tb_student_enrollment.academic_year_id')
                ->with([
                    'academicYear' => fn ($year) => $year->withTrashed(),
                    'campus',
                    'grade',
                    'schoolClass',
                    'academicTrack',
                    'session',
                ])
                ->where('tb_student_enrollment.student_id', $student->id)
                ->orderByDesc('tb_academic_year.academic_year')
                ->orderByDesc('tb_student_enrollment.id')
                ->get(),
        ]);
    }

    public function familyDetails(Request $request)
    {
        $familyNumber = trim((string) $request->query('family_number'));
        abort_if($familyNumber === '', 422, 'Family number is required.');

        $family = Family::with(['members' => fn ($query) => $query
            ->whereIn('relationship_type', ['mother', 'father', 'guardian'])])
            ->where('family_number', $familyNumber)
            ->first();

        return response()->json([
            'family_number' => $familyNumber,
            'members' => $family?->members->map(fn ($member) => [
                'relationship_type' => $member->relationship_type,
                'full_name_en' => $member->full_name_en,
                'full_name_kh' => $member->full_name_kh,
                'phone' => $member->phone,
                'workplace' => $member->workplace,
                'occupation_id' => $member->occupation_id,
                'nationality_country_id' => $member->nationality_country_id,
            ])->values() ?? collect(),
        ]);
    }

    public function siblings(Student $student)
    {
        if (!filled($student->family_number)) {
            return response()->json(['siblings' => []]);
        }

        $siblings = Student::query()
            ->where('family_number', $student->family_number)
            ->where('id', '!=', $student->id)
            ->with(['enrollments' => function ($query) {
                $query->with(['academicYear', 'campus', 'grade', 'schoolClass', 'session'])
                    ->orderByDesc('academic_year_id')
                    ->orderByDesc('id');
            }])
            ->orderBy('full_name_en')
            ->get(['id', 'student_no', 'student_id', 'photo_path', 'full_name_en', 'full_name_kh', 'status'])
            ->map(function (Student $sibling) {
                $currentEnrollment = $sibling->enrollments->first(fn ($enrollment) => $enrollment->enrollment_status === 'active')
                    ?? $sibling->enrollments->first();

                return [
                    'id' => $sibling->id,
                    'student_no' => $sibling->student_no,
                    'student_id' => $sibling->student_id,
                    'photo_path' => $sibling->photo_path,
                    'full_name_en' => $sibling->full_name_en,
                    'full_name_kh' => $sibling->full_name_kh,
                    'status' => $sibling->status,
                    'current_enrollment' => $currentEnrollment,
                ];
            })
            ->values();

        return response()->json(['siblings' => $siblings]);
    }

    public function history(StudentEnrollment $enrollment)
    {
        return response()->json([
            'student' => $enrollment->student()->first(['id', 'student_no', 'student_id', 'full_name_en', 'full_name_kh']),
            'history' => $enrollment->history()->with(['campus', 'academicYear', 'grade', 'schoolClass', 'academicTrack', 'session', 'changedBy:id,name'])->orderByDesc('updated_at')->orderByDesc('id')->get(),
        ]);
    }

    public function save(Request $request)
    {
        $id = $request->input('enrollment_id');
        $validated = $request->validate([
            'student_no' => ['nullable', 'string', 'max:30'],
            'student_id' => ['required', 'string', 'max:30', Rule::unique('tb_student', 'student_id')->ignore($request->input('student_record_id'))],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:width=600,height=800', 'max:2048'],
            'family_number' => ['nullable', 'string', 'max:30'],
            'existing_family_number' => ['nullable', 'string', 'max:30'],
            'full_name_en' => ['required', 'string', 'max:160'],
            'full_name_kh' => ['nullable', 'string', 'max:160'],
            'gender' => ['nullable', 'string', 'max:20'],
            'gender_kh' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'nationality_country_id' => ['nullable', 'exists:tb_country,id'],
            'home_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
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
            'address_house_no_en' => ['nullable', 'string', 'max:100'],
            'address_house_no_kh' => ['nullable', 'string', 'max:100'],
            'address_street_en' => ['nullable', 'string', 'max:150'],
            'address_street_kh' => ['nullable', 'string', 'max:150'],
            'current_address_en' => ['nullable', 'string', 'max:2000'],
            'current_address_kh' => ['nullable', 'string', 'max:2000'],
            'previous_school' => ['nullable', 'string', 'max:200'],
            'experienced_english' => ['nullable', 'string', 'max:5000'],
            'test_result' => ['nullable', 'string', 'max:5000'],
            'tested_by' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'campus_id' => ['required', 'exists:tb_school_info,id'],
            'academic_year_id' => ['required', Rule::exists('tb_academic_year', 'id')->whereIn('lifecycle_status', ['started', 'pending'])],
            'grade_id' => ['required', 'exists:tb_grade,id'],
            'class_id' => ['required', 'exists:tb_class,id'],
            'academic_track_id' => ['nullable', 'exists:tb_academic_track,id'],
            'session_id' => ['required', 'exists:tb_session,id'],
            'status' => ['required', 'boolean'],
            'enrollment_status' => ['nullable', Rule::in(['active', 'pending', 'completed', 'withdrawn', 'transferred', 'graduated', 'cancelled', 'promotion_cancelled'])],
            'enrolled_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:enrolled_on'],
            'exit_reason' => ['nullable', 'string', 'max:255'],
            'enrollment_notes' => ['nullable', 'string', 'max:5000'],
            'mother_name_en' => ['required', 'string', 'max:160'],
            'mother_name_kh' => ['nullable', 'string', 'max:160'],
            'mother_occupation_en' => ['nullable', 'string', 'max:120'],
            'mother_occupation_kh' => ['nullable', 'string', 'max:120'],
            'mother_workplace' => ['nullable', 'string', 'max:160'],
            'mother_nationality_country_id' => ['nullable', 'exists:tb_country,id'],
            'mother_occupation_id' => ['nullable', 'exists:tb_occupation,id'],
            'mother_phone' => ['required', 'string', 'max:50'],
            'father_name_en' => ['required', 'string', 'max:160'],
            'father_name_kh' => ['nullable', 'string', 'max:160'],
            'father_occupation_en' => ['nullable', 'string', 'max:120'],
            'father_occupation_kh' => ['nullable', 'string', 'max:120'],
            'father_workplace' => ['nullable', 'string', 'max:160'],
            'father_nationality_country_id' => ['nullable', 'exists:tb_country,id'],
            'father_occupation_id' => ['nullable', 'exists:tb_occupation,id'],
            'father_phone' => ['required', 'string', 'max:50'],
            'guardian_name_en' => ['nullable', 'string', 'max:160', 'required_with:guardian_name_kh,guardian_occupation_id,guardian_nationality_country_id,guardian_workplace,guardian_phone'],
            'guardian_name_kh' => ['nullable', 'string', 'max:160'],
            'guardian_occupation_en' => ['nullable', 'string', 'max:120'],
            'guardian_occupation_kh' => ['nullable', 'string', 'max:120'],
            'guardian_workplace' => ['nullable', 'string', 'max:160'],
            'guardian_nationality_country_id' => ['nullable', 'exists:tb_country,id'],
            'guardian_occupation_id' => ['nullable', 'exists:tb_occupation,id'],
            'guardian_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $grade = Grade::find($validated['grade_id']);
        $isGrade12 = $this->isGrade12($grade);
        if ($isGrade12 && empty($validated['academic_track_id'])) {
            throw ValidationException::withMessages(['academic_track_id' => 'Academic Track is required for Grade 12 students.']);
        }
        if ($isGrade12 && !empty($validated['academic_track_id'])) {
            $track = AcademicTrack::find($validated['academic_track_id']);
            if (!$track || ((int) $track->status !== 1) || ($track->grade_id && (int) $track->grade_id !== (int) $validated['grade_id'])) {
                throw ValidationException::withMessages(['academic_track_id' => 'Please select a valid active Academic Track for the selected Grade 12 class.']);
            }
        }
        if (!$isGrade12) {
            $validated['academic_track_id'] = null;
        }

        $enrollment = DB::transaction(function () use ($validated, $id, $request) {
            $student = $request->input('student_record_id') ? Student::findOrFail($request->input('student_record_id')) : new Student();
            if ($student->exists) {
                $validated['student_no'] = $student->student_no;
            } else {
                $validated['student_no'] = $this->nextStudentNumber();
            }
            $validated['family_number'] = $validated['existing_family_number']
                ?: ($validated['family_number'] ?: ('F' . $validated['student_id']));

            $duplicate = StudentEnrollment::where('academic_year_id', $validated['academic_year_id'])
                ->whereHas('student', fn ($q) => $q->where('student_no', $validated['student_no']))
                ->when($id, fn ($q) => $q->where('id', '!=', $id))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'student_no' => "Unable to save Student Enrollment. Student '{$validated['student_no']}' is already enrolled for this academic year.",
                ]);
            }

            $student->fill(collect($validated)->only(['student_no', 'student_id', 'family_number', 'full_name_en', 'full_name_kh', 'gender', 'gender_kh', 'date_of_birth', 'nationality_country_id', 'home_phone', 'email', 'birth_country_id', 'birth_province_id', 'birth_district_id', 'birth_commune_id', 'birth_village_id', 'address_country_id', 'address_province_id', 'address_district_id', 'address_commune_id', 'address_village_id', 'address_house_no_en', 'address_house_no_kh', 'address_street_en', 'address_street_kh', 'current_address_en', 'current_address_kh', 'previous_school', 'experienced_english', 'test_result', 'tested_by', 'remarks'])->all());
            if ($request->hasFile('photo')) {
                if ($student->photo_path) {
                    Storage::disk('public')->delete($student->photo_path);
                }
                $photo = $request->file('photo');
                $photoName = $this->studentPhotoFilename($student, $photo->extension());
                $student->photo_path = $photo->storeAs('student_photos', $photoName, 'public');
            }
            $student->status = $validated['status'];
            $student->save();

            $family = $this->familyService->syncStudentFamily($student, $validated['family_number']);
            $this->familyService->syncEnrollmentMember($family, 'mother', [
                'full_name_en' => $validated['mother_name_en'] ?? null,
                'full_name_kh' => $validated['mother_name_kh'] ?? null,
                'occupation_en' => $validated['mother_occupation_en'] ?? null,
                'occupation_kh' => $validated['mother_occupation_kh'] ?? null,
                'workplace' => $validated['mother_workplace'] ?? null,
                'nationality_country_id' => $validated['mother_nationality_country_id'] ?? null,
                'occupation_id' => $validated['mother_occupation_id'] ?? null,
                'phone' => $validated['mother_phone'] ?? null,
            ], $student);
            $this->familyService->syncEnrollmentMember($family, 'father', [
                'full_name_en' => $validated['father_name_en'] ?? null,
                'full_name_kh' => $validated['father_name_kh'] ?? null,
                'occupation_en' => $validated['father_occupation_en'] ?? null,
                'occupation_kh' => $validated['father_occupation_kh'] ?? null,
                'workplace' => $validated['father_workplace'] ?? null,
                'nationality_country_id' => $validated['father_nationality_country_id'] ?? null,
                'occupation_id' => $validated['father_occupation_id'] ?? null,
                'phone' => $validated['father_phone'] ?? null,
            ], $student);
            $this->familyService->syncEnrollmentMember($family, 'guardian', [
                'full_name_en' => $validated['guardian_name_en'] ?? null,
                'full_name_kh' => $validated['guardian_name_kh'] ?? null,
                'occupation_en' => $validated['guardian_occupation_en'] ?? null,
                'occupation_kh' => $validated['guardian_occupation_kh'] ?? null,
                'workplace' => $validated['guardian_workplace'] ?? null,
                'nationality_country_id' => $validated['guardian_nationality_country_id'] ?? null,
                'occupation_id' => $validated['guardian_occupation_id'] ?? null,
                'phone' => $validated['guardian_phone'] ?? null,
            ], $student);

            $enrollment = $id ? StudentEnrollment::findOrFail($id) : new StudentEnrollment();
            $wasExisting = $enrollment->exists;
            $oldAssignment = $wasExisting ? $enrollment->only(['campus_id', 'academic_year_id', 'grade_id', 'class_id', 'academic_track_id', 'session_id']) : [];
            if ($wasExisting) {
                $newAssignment = collect($validated)->only(['campus_id', 'academic_year_id', 'grade_id', 'class_id', 'academic_track_id', 'session_id'])
                    ->map(fn ($value) => $value === null || $value === '' ? null : (int) $value)
                    ->all();
                $oldAssignment = collect($oldAssignment)
                    ->map(fn ($value) => $value === null || $value === '' ? null : (int) $value)
                    ->all();
                if ($oldAssignment !== $newAssignment) {
                    throw ValidationException::withMessages([
                        'enrollment_id' => 'Enrollment assignment cannot be changed here. Use Transfer Student to change the campus, grade, class, academic year, track, or group.',
                    ]);
                }
            }
            $enrollment->fill(collect($validated)->only(['campus_id', 'academic_year_id', 'grade_id', 'class_id', 'academic_track_id', 'session_id', 'status'])->all());
            $enrollment->group_id = null;
            $enrollment->student_id = $student->id;
            $academicYear = AcademicYear::findOrFail($validated['academic_year_id']);
            $existingStatus = $wasExisting ? $enrollment->enrollment_status : null;
            $requestedStatus = $validated['enrollment_status'] ?? null;
            $terminalStatuses = ['withdrawn', 'transferred', 'graduated', 'cancelled'];
            $enrollment->enrollment_status = in_array($requestedStatus, $terminalStatuses, true)
                ? $requestedStatus
                : (in_array($existingStatus, $terminalStatuses, true) && !$requestedStatus
                    ? $existingStatus
                    : match ($academicYear->lifecycle_status) {
                        'started' => 'active',
                        'pending' => 'pending',
                        'finished' => 'completed',
                        default => $requestedStatus ?: ($existingStatus ?: 'pending'),
                    });
            $enrollment->student_type = StudentEnrollment::where('student_id', $student->id)
                ->when($id, fn ($query) => $query->where('id', '!=', $id))
                ->where('academic_year_id', '!=', $validated['academic_year_id'])
                ->exists() ? 'old' : 'new';
            $enrollment->enrolled_on = $validated['enrolled_on'] ?? ($enrollment->enrolled_on ?: now()->toDateString());
            $enrollment->ended_on = $validated['ended_on'] ?? null;
            $enrollment->exit_reason = $validated['exit_reason'] ?? null;
            $enrollment->notes = $validated['enrollment_notes'] ?? null;
            $enrollment->save();

            $newAssignment = $enrollment->only(['campus_id', 'academic_year_id', 'grade_id', 'class_id', 'academic_track_id', 'session_id']);
            $action = !$wasExisting ? 'enrolled' : ($oldAssignment !== $newAssignment ? 'assignment_changed' : 'updated');
            StudentEnrollmentHistory::create([
                'enrollment_id' => $enrollment->id,
                'student_id' => $student->id,
                'action_type' => $action,
                ...$newAssignment,
                'enrollment_status' => $enrollment->enrollment_status,
                'student_type' => $enrollment->student_type,
                'effective_on' => $enrollment->ended_on ?: $enrollment->enrolled_on,
                'reason' => $enrollment->exit_reason,
                'notes' => $enrollment->notes,
                'changed_by' => auth()->id(),
            ]);

            return $enrollment->load(['student', 'campus', 'academicYear', 'grade', 'schoolClass', 'academicTrack', 'schoolGroup', 'session']);
        });

        return response()->json(['status' => 'success', 'message' => $id ? 'Student enrollment updated successfully.' : 'Student enrollment created successfully.', 'data' => $enrollment], $id ? 200 : 201);
    }

    private function nextStudentNumber(): string
    {
        $max = (int) Student::query()->selectRaw('MAX(CAST(student_no AS UNSIGNED)) as max_no')->value('max_no');
        $next = $max + 1;

        if ($next > 99999999) {
            throw ValidationException::withMessages([
                'student_no' => 'Unable to generate Student Number. The 8-digit limit has been reached.',
            ]);
        }

        return str_pad((string) $next, 8, '0', STR_PAD_LEFT);
    }

    private function studentPhotoFilename(Student $student, string $extension): string
    {
        $name = trim((string) ($student->full_name_en ?: $student->full_name_kh ?: 'Student'));
        $name = preg_replace('/[^\pL\pN]+/u', '_', $name) ?: 'Student';
        $name = trim($name, '_');
        $studentId = preg_replace('/[^\pL\pN]+/u', '_', trim((string) $student->student_id)) ?: $student->id;

        return $name . '_' . trim($studentId, '_') . '.' . strtolower($extension);
    }

    private function filteredEnrollmentStatsQuery(Request $request)
    {
        $search = $request->query('search');
        $gradeClass = (string) $request->query('grade_class', '');
        [$filterGradeId, $filterClassId] = array_pad(explode(':', $gradeClass, 2), 2, null);

        return StudentEnrollment::query()
            ->leftJoin('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
            ->when($request->filled('academic_year_id'), fn ($q) => $q->where('tb_student_enrollment.academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('campus_id'), fn ($q) => $q->where('tb_student_enrollment.campus_id', $request->integer('campus_id')))
            ->when($filterGradeId && $filterClassId, fn ($q) => $q->where('tb_student_enrollment.grade_id', (int) $filterGradeId)->where('tb_student_enrollment.class_id', (int) $filterClassId))
            ->when($request->filled('group_id'), fn ($q) => $q->where('tb_student_enrollment.session_id', $request->integer('group_id')))
            ->when($request->filled('student_id'), fn ($q) => $q->where('tb_student_enrollment.student_id', $request->integer('student_id')))
            ->when($request->filled('enrollment_status'), fn ($q) => $q->where('tb_student_enrollment.enrollment_status', $request->string('enrollment_status')->toString()))
            ->when($search, function ($q, $term) {
                $q->where(function ($query) use ($term) {
                    $query->where('tb_student.student_no', 'like', "%{$term}%")
                        ->orWhere('tb_student.student_id', 'like', "%{$term}%")
                        ->orWhere('tb_student.family_number', 'like', "%{$term}%")
                        ->orWhere('tb_student.full_name_en', 'like', "%{$term}%")
                        ->orWhere('tb_student.full_name_kh', 'like', "%{$term}%");
                });
            });
    }

    private function enrollmentListStats(Request $request): array
    {
        $row = $this->filteredEnrollmentStatsQuery($request)
            ->selectRaw("
                COUNT(DISTINCT tb_student_enrollment.student_id) as total_students,
                COUNT(DISTINCT CASE WHEN LOWER(tb_student.gender) IN ('male', 'm') THEN tb_student_enrollment.student_id END) as total_male,
                COUNT(DISTINCT CASE WHEN LOWER(tb_student.gender) IN ('female', 'f') THEN tb_student_enrollment.student_id END) as total_female,
                COUNT(DISTINCT CASE WHEN tb_student_enrollment.enrollment_status = 'active' THEN tb_student_enrollment.student_id END) as active_students,
                COUNT(DISTINCT CASE WHEN tb_student_enrollment.enrollment_status = 'active' AND LOWER(tb_student.gender) IN ('male', 'm') THEN tb_student_enrollment.student_id END) as active_male,
                COUNT(DISTINCT CASE WHEN tb_student_enrollment.enrollment_status = 'active' AND LOWER(tb_student.gender) IN ('female', 'f') THEN tb_student_enrollment.student_id END) as active_female,
                COUNT(DISTINCT CASE WHEN tb_student_enrollment.enrollment_status = 'withdrawn' THEN tb_student_enrollment.student_id END) as withdrawn_students,
                COUNT(DISTINCT CASE WHEN tb_student_enrollment.enrollment_status = 'withdrawn' AND LOWER(tb_student.gender) IN ('male', 'm') THEN tb_student_enrollment.student_id END) as withdrawn_male,
                COUNT(DISTINCT CASE WHEN tb_student_enrollment.enrollment_status = 'withdrawn' AND LOWER(tb_student.gender) IN ('female', 'f') THEN tb_student_enrollment.student_id END) as withdrawn_female,
                COUNT(DISTINCT CASE WHEN tb_student_enrollment.student_type = 'new' THEN tb_student_enrollment.student_id END) as new_students,
                COUNT(DISTINCT CASE WHEN tb_student_enrollment.student_type = 'new' AND LOWER(tb_student.gender) IN ('male', 'm') THEN tb_student_enrollment.student_id END) as new_male,
                COUNT(DISTINCT CASE WHEN tb_student_enrollment.student_type = 'new' AND LOWER(tb_student.gender) IN ('female', 'f') THEN tb_student_enrollment.student_id END) as new_female
            ")
            ->first();

        return [
            'total' => [
                'count' => (int) ($row->total_students ?? 0),
                'male' => (int) ($row->total_male ?? 0),
                'female' => (int) ($row->total_female ?? 0),
            ],
            'active' => [
                'count' => (int) ($row->active_students ?? 0),
                'male' => (int) ($row->active_male ?? 0),
                'female' => (int) ($row->active_female ?? 0),
            ],
            'withdrawn' => [
                'count' => (int) ($row->withdrawn_students ?? 0),
                'male' => (int) ($row->withdrawn_male ?? 0),
                'female' => (int) ($row->withdrawn_female ?? 0),
            ],
            'new' => [
                'count' => (int) ($row->new_students ?? 0),
                'male' => (int) ($row->new_male ?? 0),
                'female' => (int) ($row->new_female ?? 0),
            ],
        ];
    }

    private function enrollmentDependentFilterOptions(Request $request): array
    {
        $yearId = $request->integer('academic_year_id') ?: null;
        $campusId = $request->integer('campus_id') ?: null;
        $gradeClass = (string) $request->query('grade_class', '');
        [$gradeId, $classId] = array_pad(explode(':', $gradeClass, 2), 2, null);
        $groupId = $request->integer('group_id') ?: null;
        $studentId = $request->integer('student_id') ?: null;

        $base = fn () => StudentEnrollment::query();
        $applyYear = fn ($query) => $query->when($yearId, fn ($q) => $q->where('tb_student_enrollment.academic_year_id', $yearId));
        $applyCampus = fn ($query) => $applyYear($query)->when($campusId, fn ($q) => $q->where('tb_student_enrollment.campus_id', $campusId));
        $applyGrade = fn ($query) => $applyCampus($query)->when($gradeId && $classId, fn ($q) => $q->where('tb_student_enrollment.grade_id', (int) $gradeId)->where('tb_student_enrollment.class_id', (int) $classId));
        $applyGroup = fn ($query) => $applyGrade($query)->when($groupId, fn ($q) => $q->where('tb_student_enrollment.session_id', $groupId));
        $applyStudent = fn ($query) => $applyGroup($query)->when($studentId, fn ($q) => $q->where('tb_student_enrollment.student_id', $studentId));

        return [
            'campuses' => $applyYear($base())
                ->join('tb_school_info', 'tb_school_info.id', '=', 'tb_student_enrollment.campus_id')
                ->selectRaw('tb_student_enrollment.campus_id, MAX(tb_school_info.campus_name_en) as campus_name_en')
                ->groupBy('tb_student_enrollment.campus_id')
                ->orderBy('tb_school_info.campus_name_en')
                ->get(),
            'grades' => $applyCampus($base())
                ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
                ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
                ->selectRaw('tb_student_enrollment.grade_id, tb_student_enrollment.class_id, MAX(tb_grade.grade) as grade, MAX(tb_grade.grade_order) as grade_order, MAX(tb_class.class_name) as class_name, MAX(tb_class.class_order) as class_order')
                ->groupBy('tb_student_enrollment.grade_id', 'tb_student_enrollment.class_id')
                ->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')
                ->orderByRaw('CAST(tb_class.class_order AS UNSIGNED)')
                ->orderBy('tb_class.class_name')
                ->get(),
            'groups' => $applyGrade($base())
                ->join('tb_session', 'tb_session.id', '=', 'tb_student_enrollment.session_id')
                ->selectRaw('tb_student_enrollment.session_id as group_id, MAX(tb_session.session_short_name) as session_short_name, MAX(tb_session.session_order) as session_order')
                ->whereNotNull('tb_student_enrollment.session_id')
                ->groupBy('tb_student_enrollment.session_id')
                ->orderByRaw('CAST(tb_session.session_order AS UNSIGNED)')
                ->orderBy('tb_session.session_short_name')
                ->get(),
            'students' => $applyGroup($base())
                ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
                ->selectRaw('tb_student_enrollment.student_id as student_record_id, MAX(tb_student.student_id) as student_code, MAX(tb_student.full_name_en) as full_name_en, MAX(tb_student.full_name_kh) as full_name_kh')
                ->groupBy('tb_student_enrollment.student_id')
                ->orderByRaw("LOWER(COALESCE(NULLIF(tb_student.full_name_en, ''), tb_student.full_name_kh, tb_student.student_id))")
                ->limit(1000)
                ->get(),
            'statuses' => $applyStudent($base())
                ->select('tb_student_enrollment.enrollment_status')
                ->whereNotNull('tb_student_enrollment.enrollment_status')
                ->distinct()
                ->orderBy('tb_student_enrollment.enrollment_status')
                ->get(),
        ];
    }

    private function enrollmentListOptions(): array
    {
        return Cache::remember('student-enrollment:list-options', now()->addSeconds(30), function (): array {
            return [
                'allAcademicYears' => AcademicYear::orderByDesc('academic_year')
                    ->get(['id', 'academic_year', 'period_type', 'parent_academic_year_id', 'lifecycle_status', 'start_date', 'end_date']),
                // Filter rows are normalized in the browser. Avoid DISTINCT and
                // server-side sorting here; both require a full temporary sort.
                'enrollmentFilterRows' => StudentEnrollment::query()
                    ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
                    ->join('tb_academic_year', 'tb_academic_year.id', '=', 'tb_student_enrollment.academic_year_id')
                    ->join('tb_school_info', 'tb_school_info.id', '=', 'tb_student_enrollment.campus_id')
                    ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
                    ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
                    ->leftJoin('tb_session', 'tb_session.id', '=', 'tb_student_enrollment.session_id')
                    ->select([
                        'tb_student_enrollment.academic_year_id',
                        'tb_student_enrollment.campus_id',
                        'tb_student_enrollment.grade_id',
                        'tb_student_enrollment.class_id',
                        'tb_student_enrollment.session_id as group_id',
                        'tb_student_enrollment.enrollment_status',
                        'tb_student_enrollment.student_id as student_record_id',
                        'tb_academic_year.academic_year',
                        'tb_school_info.campus_name_en',
                        'tb_grade.grade',
                        'tb_grade.grade_order',
                        'tb_class.class_name',
                        'tb_class.class_order',
                        'tb_session.session_short_name',
                        'tb_student.student_id as student_code',
                        'tb_student.full_name_en',
                        'tb_student.full_name_kh',
                    ])
                    ->get(),
            ];
        });
    }

    private function isGrade12(?Grade $grade): bool
    {
        if (!$grade) {
            return false;
        }

        $values = [
            (string) $grade->grade,
            (string) $grade->grade_short_name,
            (string) $grade->grade_order,
        ];

        foreach ($values as $value) {
            if (preg_match('/(^|[^0-9])12([^0-9]|$)/', $value)) {
                return true;
            }
        }

        return false;
    }

    public function delete($id)
    {
        $enrollment = StudentEnrollment::find($id);
        if (!$enrollment) {
            return response()->json(['status' => 'error', 'message' => 'Student enrollment not found.'], 404);
        }

        $enrollment->delete();
        return response()->json(['status' => 'success', 'message' => 'Student enrollment deleted successfully.']);
    }
}
