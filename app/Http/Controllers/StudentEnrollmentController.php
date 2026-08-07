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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use App\Services\FamilyService;
use App\Models\Country;
use App\Models\Occupation;
use App\Models\StudentEnrollmentHistory;
use App\Models\Family;

class StudentEnrollmentController
{
    public function __construct(private readonly FamilyService $familyService) {}
    public function index()
    {
        return view('student-enrollment');
    }

    public function options()
    {
        return response()->json([
            'academicYears' => AcademicYear::where('status', 1)->latest('id')->get(['id', 'academic_year', 'period_type', 'parent_academic_year_id', 'start_date', 'end_date']),
            'grades' => Grade::where('status', 1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->get(['id', 'grade']),
            'classes' => SchoolClass::where('status', 1)->orderByRaw('CAST(class_order AS UNSIGNED)')->get(['id', 'class_name', 'grade_id']),
            'campuses' => SchoolInfo::orderBy('campus_name_en')->get(['id', 'campus_name_en', 'campus_name_kh', 'status']),
            'sessions' => Session::where('status', 1)->orderByRaw('CAST(session_order AS UNSIGNED)')->get(['id', 'session_name', 'session_short_name']),
            'families' => Student::query()
                ->whereNotNull('family_number')
                ->where('family_number', '!=', '')
                ->select('family_number')
                ->selectRaw('MIN(first_name_en) as first_name_en, MIN(last_name_en) as last_name_en')
                ->groupBy('family_number')
                ->orderBy('family_number')
                ->get(),
            'familyDetails' => Family::with(['members' => fn ($query) => $query->whereIn('relationship_type', ['mother', 'father', 'guardian'])])
                ->where('status', 1)
                ->get(['id', 'family_number'])
                ->mapWithKeys(fn ($family) => [$family->family_number => [
                    'members' => $family->members->map(fn ($member) => [
                        'relationship_type' => $member->relationship_type,
                        'name_en' => $member->full_name_en ?: ($member->name_en ?: trim(($member->first_name_en ?? '').' '.($member->last_name_en ?? ''))),
                        'name_kh' => $member->full_name_kh ?: ($member->name_kh ?: trim(($member->first_name_kh ?? '').' '.($member->last_name_kh ?? ''))),
                        'phone' => $member->phone,
                        'workplace' => $member->workplace,
                        'occupation_id' => $member->occupation_id,
                        'nationality_country_id' => $member->nationality_country_id,
                    ])->values(),
                ]])->all(),
            'nextStudentNo' => $this->nextStudentNumber(),
            'countries' => Country::where('status', 1)->orderBy('country_name_en')->get(['id', 'country_name_en', 'country_name_kh', 'nationality_name_en', 'nationality_name_kh', 'flag_path']),
            'occupations' => Occupation::where('status', 1)->orderBy('occupation_name_en')->get(['id', 'occupation_name_en', 'occupation_name_kh']),
        ]);
    }

    public function fetchData(Request $request)
    {
        $search = $request->query('search');
        $query = StudentEnrollment::with(['student.birthCountry', 'student.birthProvince', 'student.birthDistrict', 'student.birthCommune', 'student.birthVillage', 'student.nationalityCountry', 'student.addressCountry', 'student.addressProvince', 'student.addressDistrict', 'student.addressCommune', 'student.addressVillage', 'campus', 'academicYear', 'grade', 'schoolClass', 'schoolGroup', 'session'])
            ->when($search, function ($q, $term) {
                $q->where(function ($query) use ($term) {
                    $query->whereHas('student', function ($studentQuery) use ($term) {
                        $studentQuery->where('student_no', 'like', "%{$term}%")
                            ->orWhere('student_id', 'like', "%{$term}%")
                            ->orWhere('family_number', 'like', "%{$term}%")
                            ->orWhere('first_name_en', 'like', "%{$term}%")
                            ->orWhere('last_name_en', 'like', "%{$term}%")
                            ->orWhere('first_name_kh', 'like', "%{$term}%")
                            ->orWhere('last_name_kh', 'like', "%{$term}%");
                    })->orWhereHas('campus', function ($campusQuery) use ($term) {
                        $campusQuery->where('campus_name_en', 'like', "%{$term}%")
                            ->orWhere('campus_name_kh', 'like', "%{$term}%");
                    });
                });
            });

        return response()->json($query->latest('id')->paginate($request->query('perPage', 10)));
    }

    public function history(StudentEnrollment $enrollment)
    {
        return response()->json([
            'student' => $enrollment->student()->first(['id', 'student_no', 'student_id', 'first_name_en', 'last_name_en']),
            'history' => $enrollment->history()->with(['campus', 'academicYear', 'grade', 'schoolClass', 'session', 'changedBy:id,name'])->orderByDesc('updated_at')->orderByDesc('id')->get(),
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
            'academic_year_id' => ['required', 'exists:tb_academic_year,id'],
            'grade_id' => ['required', 'exists:tb_grade,id'],
            'class_id' => ['required', 'exists:tb_class,id'],
            'session_id' => ['required', 'exists:tb_session,id'],
            'status' => ['required', 'boolean'],
            'enrollment_status' => ['nullable', Rule::in(['active', 'completed', 'withdrawn', 'transferred', 'graduated', 'cancelled'])],
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
            $student->first_name_en = $validated['full_name_en'];
            $student->last_name_en = '';
            $student->first_name_kh = $validated['full_name_kh'] ?? null;
            $student->last_name_kh = null;
            if ($request->hasFile('photo')) {
                if ($student->photo_path) {
                    Storage::disk('public')->delete($student->photo_path);
                }
                $student->photo_path = $request->file('photo')->store('student_photos', 'public');
            }
            $student->status = $validated['status'];
            $student->save();

            $family = $this->familyService->syncStudentFamily($student, $validated['family_number']);
            $this->familyService->syncEnrollmentMember($family, 'mother', [
                'name_en' => $validated['mother_name_en'] ?? null,
                'name_kh' => $validated['mother_name_kh'] ?? null,
                'occupation_en' => $validated['mother_occupation_en'] ?? null,
                'occupation_kh' => $validated['mother_occupation_kh'] ?? null,
                'workplace' => $validated['mother_workplace'] ?? null,
                'nationality_country_id' => $validated['mother_nationality_country_id'] ?? null,
                'occupation_id' => $validated['mother_occupation_id'] ?? null,
                'phone' => $validated['mother_phone'] ?? null,
            ]);
            $this->familyService->syncEnrollmentMember($family, 'father', [
                'name_en' => $validated['father_name_en'] ?? null,
                'name_kh' => $validated['father_name_kh'] ?? null,
                'occupation_en' => $validated['father_occupation_en'] ?? null,
                'occupation_kh' => $validated['father_occupation_kh'] ?? null,
                'workplace' => $validated['father_workplace'] ?? null,
                'nationality_country_id' => $validated['father_nationality_country_id'] ?? null,
                'occupation_id' => $validated['father_occupation_id'] ?? null,
                'phone' => $validated['father_phone'] ?? null,
            ]);
            $this->familyService->syncEnrollmentMember($family, 'guardian', [
                'name_en' => $validated['guardian_name_en'] ?? null,
                'name_kh' => $validated['guardian_name_kh'] ?? null,
                'occupation_en' => $validated['guardian_occupation_en'] ?? null,
                'occupation_kh' => $validated['guardian_occupation_kh'] ?? null,
                'workplace' => $validated['guardian_workplace'] ?? null,
                'nationality_country_id' => $validated['guardian_nationality_country_id'] ?? null,
                'occupation_id' => $validated['guardian_occupation_id'] ?? null,
                'phone' => $validated['guardian_phone'] ?? null,
            ]);

            $enrollment = $id ? StudentEnrollment::findOrFail($id) : new StudentEnrollment();
            $wasExisting = $enrollment->exists;
            $oldAssignment = $wasExisting ? $enrollment->only(['campus_id', 'academic_year_id', 'grade_id', 'class_id', 'session_id']) : [];
            $enrollment->fill(collect($validated)->only(['campus_id', 'academic_year_id', 'grade_id', 'class_id', 'session_id', 'status'])->all());
            $enrollment->group_id = null;
            $enrollment->student_id = $student->id;
            $enrollment->enrollment_status = $validated['enrollment_status'] ?? ($wasExisting ? ($enrollment->enrollment_status ?: 'active') : 'active');
            $enrollment->student_type = StudentEnrollment::where('student_id', $student->id)
                ->when($id, fn ($query) => $query->where('id', '!=', $id))
                ->where('academic_year_id', '!=', $validated['academic_year_id'])
                ->exists() ? 'old' : 'new';
            $enrollment->enrolled_on = $validated['enrolled_on'] ?? ($enrollment->enrolled_on ?: now()->toDateString());
            $enrollment->ended_on = $validated['ended_on'] ?? null;
            $enrollment->exit_reason = $validated['exit_reason'] ?? null;
            $enrollment->notes = $validated['enrollment_notes'] ?? null;
            $enrollment->save();

            $newAssignment = $enrollment->only(['campus_id', 'academic_year_id', 'grade_id', 'class_id', 'session_id']);
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

            return $enrollment->load(['student', 'campus', 'academicYear', 'grade', 'schoolClass', 'schoolGroup', 'session']);
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
