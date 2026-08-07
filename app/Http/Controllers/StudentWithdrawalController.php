<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\FamilyMember;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolInfo;
use App\Models\StudentEnrollment;
use App\Models\StudentEnrollmentHistory;
use App\Models\WithdrawalReason;
use App\Services\StudentWithdrawalService;
use Illuminate\Http\Request;

class StudentWithdrawalController
{
    public const REASONS = [
        ['key' => 'transfer', 'en' => 'Transfer to another school', 'kh' => 'ផ្ទេរទៅសាលាផ្សេង'],
        ['key' => 'study_abroad', 'en' => 'Study abroad', 'kh' => 'ទៅសិក្សានៅបរទេស'],
        ['key' => 'change_residence', 'en' => 'Change of residence', 'kh' => 'ផ្លាស់ប្តូរទីលំនៅ'],
        ['key' => 'academic_difficulty', 'en' => 'Academic difficulty', 'kh' => 'ការលំបាកក្នុងការសិក្សា'],
        ['key' => 'behavior_difficulty', 'en' => 'Behavior difficulty', 'kh' => 'បញ្ហាអាកប្បកិរិយា'],
        ['key' => 'dislike_school', 'en' => 'Dislike of the school experience', 'kh' => 'មិនពេញចិត្តបទពិសោធន៍នៅសាលា'],
        ['key' => 'economic', 'en' => 'Economic reasons', 'kh' => 'ហេតុផលសេដ្ឋកិច្ច'],
        ['key' => 'employment', 'en' => 'Employment status', 'kh' => 'ស្ថានភាពការងារ'],
        ['key' => 'transportation', 'en' => 'Transportation difficulties', 'kh' => 'ការលំបាកក្នុងការធ្វើដំណើរ'],
        ['key' => 'lack_interest', 'en' => 'Lack of interest or motivation', 'kh' => 'ខ្វះចំណាប់អារម្មណ៍ ឬការលើកទឹកចិត្ត'],
        ['key' => 'physical', 'en' => 'Physical illness or disability', 'kh' => 'ជំងឺរាងកាយ ឬពិការភាព'],
        ['key' => 'staff_relationship', 'en' => 'Poor student/staff relationship', 'kh' => 'ទំនាក់ទំនងមិនល្អជាមួយបុគ្គលិក'],
        ['key' => 'student_relationship', 'en' => 'Poor relationship with fellow students', 'kh' => 'ទំនាក់ទំនងមិនល្អជាមួយសិស្សដទៃ'],
        ['key' => 'expelled', 'en' => 'Expelled', 'kh' => 'ត្រូវបានបណ្តេញចេញ'],
    ];
    public function __construct(private readonly StudentWithdrawalService $service) {}

    public function index() { return view('student-withdrawal', ['reasons' => WithdrawalReason::where('status', true)->orderBy('sort_order')->orderBy('name_en')->get(['reason_key as key', 'name_en as en', 'name_kh as kh'])]); }

    public function options(Request $request)
    {
        $yearId = $request->integer('academic_year_id') ?: null;
        $campusId = $request->integer('campus_id') ?: null;
        $campusQuery = SchoolInfo::where('status', 1)->orderBy('campus_name_en');
        if ($yearId) $campusQuery->whereIn('id', StudentEnrollment::where('academic_year_id', $yearId)->where('status', 1)->where('enrollment_status', 'active')->select('campus_id'));
        $gradeClassQuery = StudentEnrollment::query()
            ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
            ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
            ->where('tb_student_enrollment.status', 1)->where('tb_student_enrollment.enrollment_status', 'active')
            ->when($yearId, fn ($query) => $query->where('tb_student_enrollment.academic_year_id', $yearId))
            ->when($campusId, fn ($query) => $query->where('tb_student_enrollment.campus_id', $campusId));
        return response()->json([
            'academicYears' => AcademicYear::where('status', 1)->orderByDesc('academic_year')->orderByDesc('id')->get(['id', 'academic_year']),
            'campuses' => $campusQuery->get(['id', 'campus_name_en']),
            'grades' => Grade::where('status', 1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->get(['id', 'grade']),
            'reasons' => WithdrawalReason::where('status', true)->orderBy('sort_order')->orderBy('name_en')->get(['reason_key as key', 'name_en as en', 'name_kh as kh']),
            'classes' => SchoolClass::where('status', 1)->orderBy('class_name')->get(['id', 'class_name', 'grade_id']),
            'gradeClasses' => $gradeClassQuery->select('tb_student_enrollment.grade_id', 'tb_student_enrollment.class_id', 'tb_grade.grade', 'tb_grade.grade_order', 'tb_class.class_name', 'tb_class.class_order')->distinct()->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')->orderByRaw('CAST(tb_class.class_order AS UNSIGNED)')->orderBy('tb_class.class_name')->get(),
        ]);
    }

    public function students(Request $request)
    {
        $students = StudentEnrollment::with(['student:id,student_no,student_id,first_name_en,last_name_en'])
            ->where('status', 1)->where('enrollment_status', 'active')
            ->when($request->filled('campus_id'), fn ($query) => $query->where('campus_id', $request->integer('campus_id')))
            ->when($request->filled('academic_year_id'), fn ($query) => $query->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('grade_id'), fn ($query) => $query->where('grade_id', $request->integer('grade_id')))
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->integer('class_id')))
            ->when($request->filled('search'), function ($query) use ($request) { $term = trim((string) $request->input('search')); $query->whereHas('student', fn ($student) => $student->where('student_id', 'like', "%{$term}%")->orWhere('student_no', 'like', "%{$term}%")->orWhere('first_name_en', 'like', "%{$term}%")->orWhere('last_name_en', 'like', "%{$term}%")); })
            ->orderByRaw('(select lower(first_name_en) from tb_student where tb_student.id = tb_student_enrollment.student_id) asc')
            ->orderBy('student_id')->limit(500)->get();
        return response()->json(['enrollments' => $students]);
    }

    public function fetch(Request $request)
    {
        $history = StudentEnrollmentHistory::with(['student:id,student_no,student_id,first_name_en,last_name_en', 'campus:id,campus_name_en', 'academicYear:id,academic_year', 'grade:id,grade', 'schoolClass:id,class_name', 'changedBy:id,name'])
            ->where('action_type', 'withdrawal')->latest('id');
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $history->where(function ($query) use ($search) {
                $query->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($student) => $student->where('student_id', 'like', "%{$search}%")->orWhere('student_no', 'like', "%{$search}%")->orWhere('first_name_en', 'like', "%{$search}%")->orWhere('last_name_en', 'like', "%{$search}%"))
                    ->orWhereHas('campus', fn ($campus) => $campus->where('campus_name_en', 'like', "%{$search}%"))
                    ->orWhereHas('academicYear', fn ($year) => $year->where('academic_year', 'like', "%{$search}%"));
            });
        }
        $result = $history->paginate($request->integer('perPage', 10));
        $result->getCollection()->transform(fn ($item) => $item->setAttribute('form_url', route('student-withdrawals.form', $item)));
        return response()->json($result);
    }

    public function form(StudentEnrollmentHistory $history)
    {
        abort_unless($history->action_type === 'withdrawal', 404);
        $history->load(['student', 'campus', 'academicYear', 'grade', 'schoolClass', 'session', 'changedBy']);
        $familyMembers = $history->student?->family_number
            ? FamilyMember::whereHas('family', fn ($query) => $query->where('family_number', $history->student->family_number))->get()
            : collect();
        return view('student-withdrawal-form', ['history' => $history, 'familyMembers' => $familyMembers, 'reasons' => WithdrawalReason::orderBy('sort_order')->orderBy('name_en')->get(['reason_key as key', 'name_en as en', 'name_kh as kh'])]);
    }

    public function withdraw(Request $request)
    {
        $data = $request->validate([
            'enrollment_id' => ['required', 'exists:tb_student_enrollment,id'],
            'withdrawal_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reasons' => ['required', 'array', 'min:1'], 'reasons.*' => ['string'], 'reason_kh' => ['nullable', 'string', 'max:5000'],
            'other_reason_en' => ['nullable', 'string', 'max:500'], 'other_reason_kh' => ['nullable', 'string', 'max:500'], 'new_school' => ['nullable', 'string', 'max:200'], 'new_school_address' => ['nullable', 'string', 'max:500'], 'dropout_type' => ['nullable', 'in:official_leave,dropped_out'], 'additional_comments' => ['nullable', 'string', 'max:5000'],
        ]);
        $enrollment = $this->service->withdraw(StudentEnrollment::findOrFail($data['enrollment_id']), $data);
        $history = StudentEnrollmentHistory::where('enrollment_id', $enrollment->id)->where('action_type', 'withdrawal')->latest('id')->first();
        return response()->json(['status' => 'success', 'message' => 'Student withdrawn successfully.', 'data' => $enrollment, 'form_url' => $history ? route('student-withdrawals.form', $history) : null]);
    }

    public function withdrawSelected(Request $request)
    {
        $data = $request->validate([
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer', 'distinct', 'exists:tb_student_enrollment,id'],
            'from_campus_id' => ['required', 'exists:tb_school_info,id'],
            'from_academic_year_id' => ['required', 'exists:tb_academic_year,id'],
            'from_grade_id' => ['required', 'exists:tb_grade,id'],
            'from_class_id' => ['required', 'exists:tb_class,id'],
            'withdrawal_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reasons' => ['required', 'array', 'min:1'], 'reasons.*' => ['string'], 'reason_kh' => ['nullable', 'string', 'max:5000'], 'other_reason_en' => ['nullable', 'string', 'max:500'], 'other_reason_kh' => ['nullable', 'string', 'max:500'], 'new_school' => ['nullable', 'string', 'max:200'], 'new_school_address' => ['nullable', 'string', 'max:500'], 'dropout_type' => ['nullable', 'in:official_leave,dropped_out'], 'additional_comments' => ['nullable', 'string', 'max:5000'],
        ]);
        $count = $this->service->withdrawMany($data['enrollment_ids'], $data, ['campus_id' => $data['from_campus_id'], 'academic_year_id' => $data['from_academic_year_id'], 'grade_id' => $data['from_grade_id'], 'class_id' => $data['from_class_id']]);
        return response()->json(['status' => 'success', 'message' => "{$count} selected students withdrawn successfully.", 'count' => $count]);
    }

    public function withdrawClass(Request $request)
    {
        $data = $request->validate([
            'from_campus_id' => ['required', 'exists:tb_school_info,id'],
            'from_academic_year_id' => ['required', 'exists:tb_academic_year,id'],
            'from_grade_id' => ['required', 'exists:tb_grade,id'],
            'from_class_id' => ['required', 'exists:tb_class,id'],
            'withdrawal_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reasons' => ['required', 'array', 'min:1'], 'reasons.*' => ['string'], 'reason_kh' => ['nullable', 'string', 'max:5000'], 'other_reason_en' => ['nullable', 'string', 'max:500'], 'other_reason_kh' => ['nullable', 'string', 'max:500'], 'new_school' => ['nullable', 'string', 'max:200'], 'new_school_address' => ['nullable', 'string', 'max:500'], 'dropout_type' => ['nullable', 'in:official_leave,dropped_out'], 'additional_comments' => ['nullable', 'string', 'max:5000'],
        ]);
        $ids = StudentEnrollment::where('campus_id', $data['from_campus_id'])->where('academic_year_id', $data['from_academic_year_id'])->where('grade_id', $data['from_grade_id'])->where('class_id', $data['from_class_id'])->where('status', 1)->where('enrollment_status', 'active')->pluck('id')->all();
        if (!$ids) return response()->json(['message' => 'No active students were found in the selected class.'], 422);
        $count = $this->service->withdrawMany($ids, $data);
        return response()->json(['status' => 'success', 'message' => "{$count} students withdrawn successfully.", 'count' => $count]);
    }
}
