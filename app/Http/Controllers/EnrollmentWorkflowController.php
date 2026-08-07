<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\EnrollmentWorkflowAction;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolInfo;
use App\Models\Session;
use App\Models\StudentEnrollment;
use App\Services\EnrollmentWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnrollmentWorkflowController
{
    public function __construct(private readonly EnrollmentWorkflowService $service) {}

    public function index(string $mode = 'promotion')
    {
        abort_unless(in_array($mode, ['promotion', 'transfer'], true), 404);
        session(['student_workflow_mode' => $mode]);
        return view('enrollment-workflows', compact('mode'));
    }

    public function options()
    {
        return response()->json([
            'enrollments' => StudentEnrollment::with(['student:id,student_no,student_id,first_name_en,last_name_en', 'campus:id,campus_name_en', 'academicYear:id,academic_year', 'grade:id,grade', 'schoolClass:id,class_name', 'session:id,session_short_name'])->where('status', 1)->where('enrollment_status', 'active')->latest('id')->get(),
            'academicYears' => AcademicYear::where('status', 1)->orderByDesc('id')->get(['id', 'academic_year']),
            'campuses' => SchoolInfo::where('status', 1)->orderBy('campus_name_en')->get(['id', 'campus_name_en']),
            'grades' => Grade::where('status', 1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->get(['id', 'grade']),
            'classes' => SchoolClass::where('status', 1)->orderBy('class_name')->get(['id', 'class_name', 'grade_id']),
            'groups' => Session::where('status', 1)->orderBy('session_order')->get(['id', 'session_short_name']),
        ]);
    }

    public function fetch(Request $request)
    {
        $query = EnrollmentWorkflowAction::with(['student:id,student_no,first_name_en,last_name_en', 'toCampus:id,campus_name_en', 'toAcademicYear:id,academic_year', 'toGrade:id,grade', 'toClass:id,class_name', 'toSession:id,session_short_name'])
            ->when($request->filled('search'), fn ($q) => $q->whereHas('student', fn ($student) => $student->where('student_no', 'like', '%' . $request->search . '%')->orWhere('first_name_en', 'like', '%' . $request->search . '%')->orWhere('last_name_en', 'like', '%' . $request->search . '%')))
            ->when(session('student_workflow_mode') === 'promotion', fn ($q) => $q->whereIn('action_type', ['promotion', 'class_promotion', 'selected_promotion']))
            ->when(session('student_workflow_mode') === 'transfer', fn ($q) => $q->whereIn('action_type', ['transfer', 'class_transfer', 'selected_transfer']))
            ->latest('id');
        return response()->json($query->paginate($request->integer('perPage', 10)));
    }

    public function promote(Request $request)
    {
        $data = $this->validated($request, true);
        $source = StudentEnrollment::findOrFail($data['enrollment_id']);
        $target = $this->service->promote($source, $data);
        return response()->json(['status' => 'success', 'message' => 'Student promoted successfully.', 'data' => $target]);
    }

    public function transfer(Request $request)
    {
        $data = $this->validated($request, false);
        $source = StudentEnrollment::findOrFail($data['enrollment_id']);
        $target = $this->service->transfer($source, $data);
        return response()->json(['status' => 'success', 'message' => 'Student transferred successfully.', 'data' => $target]);
    }

    public function promoteClass(Request $request)
    {
        $data = $request->validate([
            'from_campus_id' => ['required', 'exists:tb_school_info,id'],
            'from_academic_year_id' => ['required', 'exists:tb_academic_year,id'],
            'from_grade_id' => ['required', 'exists:tb_grade,id'],
            'from_class_id' => ['required', 'exists:tb_class,id'],
            'to_campus_id' => ['nullable', 'exists:tb_school_info,id'],
            'to_academic_year_id' => ['required', 'exists:tb_academic_year,id'],
            'to_grade_id' => ['required', 'exists:tb_grade,id'],
            'to_class_id' => ['required', 'exists:tb_class,id'],
            'to_session_id' => ['nullable', 'exists:tb_session,id'],
            'effective_on' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $count = $this->service->promoteClass($data);
        return response()->json(['status' => 'success', 'message' => "{$count} students promoted successfully.", 'count' => $count]);
    }

    public function promoteSelected(Request $request)
    {
        $data = $request->validate([
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer', 'distinct', 'exists:tb_student_enrollment,id'],
            'from_campus_id' => ['required', 'exists:tb_school_info,id'],
            'from_academic_year_id' => ['required', 'exists:tb_academic_year,id'],
            'from_grade_id' => ['required', 'exists:tb_grade,id'],
            'from_class_id' => ['required', 'exists:tb_class,id'],
            'to_campus_id' => ['nullable', 'exists:tb_school_info,id'],
            'to_academic_year_id' => ['required', 'exists:tb_academic_year,id'],
            'to_grade_id' => ['required', 'exists:tb_grade,id'],
            'to_class_id' => ['required', 'exists:tb_class,id'],
            'to_session_id' => ['nullable', 'exists:tb_session,id'],
            'effective_on' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $count = $this->service->promoteSelected($data);
        return response()->json(['status' => 'success', 'message' => "{$count} selected students promoted successfully.", 'count' => $count]);
    }

    public function transferClass(Request $request)
    {
        $data = $request->validate([
            'from_campus_id' => ['required', 'exists:tb_school_info,id'],
            'from_academic_year_id' => ['required', 'exists:tb_academic_year,id'],
            'from_grade_id' => ['required', 'exists:tb_grade,id'],
            'from_class_id' => ['required', 'exists:tb_class,id'],
            'to_campus_id' => ['required', 'exists:tb_school_info,id'],
            'to_grade_id' => ['nullable', 'exists:tb_grade,id'],
            'to_class_id' => ['nullable', 'exists:tb_class,id'],
            'to_session_id' => ['nullable', 'exists:tb_session,id'],
            'effective_on' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $count = $this->service->transferClass($data);
        return response()->json(['status' => 'success', 'message' => "{$count} students transferred successfully.", 'count' => $count]);
    }

    public function transferSelected(Request $request)
    {
        $data = $request->validate([
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer', 'distinct', 'exists:tb_student_enrollment,id'],
            'from_campus_id' => ['required', 'exists:tb_school_info,id'],
            'from_academic_year_id' => ['required', 'exists:tb_academic_year,id'],
            'from_grade_id' => ['required', 'exists:tb_grade,id'],
            'from_class_id' => ['required', 'exists:tb_class,id'],
            'to_campus_id' => ['required', 'exists:tb_school_info,id'],
            'to_grade_id' => ['nullable', 'exists:tb_grade,id'],
            'to_class_id' => ['nullable', 'exists:tb_class,id'],
            'to_session_id' => ['nullable', 'exists:tb_session,id'],
            'effective_on' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $count = $this->service->transferSelected($data);
        return response()->json(['status' => 'success', 'message' => "{$count} selected students transferred successfully.", 'count' => $count]);
    }

    private function validated(Request $request, bool $promotion): array
    {
        return $request->validate([
            'enrollment_id' => ['required', 'exists:tb_student_enrollment,id'],
            'to_campus_id' => ['nullable', 'exists:tb_school_info,id'],
            'to_academic_year_id' => [$promotion ? 'required' : 'nullable', 'exists:tb_academic_year,id'],
            'to_grade_id' => [$promotion ? 'required' : 'nullable', 'exists:tb_grade,id'],
            'to_class_id' => [$promotion ? 'required' : 'nullable', 'exists:tb_class,id'],
            'to_session_id' => ['nullable', 'exists:tb_session,id'],
            'effective_on' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
