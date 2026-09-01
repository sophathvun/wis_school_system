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

    public function options(Request $request)
    {
        $mode = $request->input('mode', session('student_workflow_mode', 'promotion'));
        $historyActionTypes = $mode === 'transfer'
            ? ['transfer', 'class_transfer', 'selected_transfer']
            : ['promotion', 'class_promotion', 'selected_promotion', 're_promotion'];
        $academicYears = AcademicYear::where('status', 1)
            ->when($mode !== 'transfer', fn ($query) => $query->where('period_type', 'regular'))
            ->whereIn('lifecycle_status', ['started', 'pending'])
            ->orderByDesc('academic_year')
            ->get(['id', 'academic_year', 'period_type', 'lifecycle_status', 'start_date', 'end_date']);
        $currentAcademicYear = $academicYears
            ->first(fn ($year) => $year->lifecycle_status === 'started')
            ?: $academicYears->first(fn ($year) => $year->period_type === 'regular' && $year->start_date && $year->end_date && now()->toDateString() >= $year->start_date->toDateString() && now()->toDateString() <= $year->end_date->toDateString())
            ?: AcademicYear::where('status', 1)
                ->when($mode !== 'transfer', fn ($query) => $query->where('period_type', 'regular'))
                ->whereIn('lifecycle_status', ['started', 'pending'])
                ->whereHas('enrollments', fn ($query) => $query->where('status', 1)->where('enrollment_status', 'active'))
                ->orderByDesc('id')
                ->first(['id', 'academic_year', 'lifecycle_status']);
        $nextAcademicYear = $currentAcademicYear
            ? $academicYears
                ->where('period_type', 'regular')
                ->filter(fn ($year) => $this->academicYearSortValue($year->academic_year) > $this->academicYearSortValue($currentAcademicYear->academic_year))
                ->sortBy(fn ($year) => $this->academicYearSortValue($year->academic_year))
                ->first()
            : null;

        return response()->json([
            'academicYears' => $academicYears,
            'currentAcademicYearId' => $currentAcademicYear?->id,
            'nextAcademicYearId' => $nextAcademicYear?->id,
            'campuses' => SchoolInfo::where('status', 1)->orderBy('campus_name_en')->get(['id', 'campus_name_en']),
            'grades' => Grade::where('status', 1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->get(['id', 'grade', 'grade_order']),
            'classes' => SchoolClass::where('status', 1)->orderBy('class_name')->get(['id', 'class_name', 'grade_id']),
            'sourceClassFilters' => StudentEnrollment::query()
                ->join('tb_academic_year', 'tb_academic_year.id', '=', 'tb_student_enrollment.academic_year_id')
                ->join('tb_school_info', 'tb_school_info.id', '=', 'tb_student_enrollment.campus_id')
                ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
                ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
                ->where('tb_student_enrollment.status', 1)
                ->where('tb_student_enrollment.enrollment_status', 'active')
                ->select([
                    'tb_student_enrollment.academic_year_id',
                    'tb_student_enrollment.campus_id',
                    'tb_student_enrollment.grade_id',
                    'tb_student_enrollment.class_id',
                    'tb_academic_year.academic_year',
                    'tb_school_info.campus_name_en',
                    'tb_grade.grade',
                    'tb_grade.grade_order',
                    'tb_class.class_name',
                ])
                ->distinct()
                ->orderByDesc('tb_academic_year.academic_year')
                ->orderBy('tb_school_info.campus_name_en')
                ->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')
                ->orderBy('tb_class.class_name')
                ->get(),
            'workflowHistoryFilters' => EnrollmentWorkflowAction::query()
                ->join('tb_academic_year', 'tb_academic_year.id', '=', 'tb_student_enrollment_workflow.to_academic_year_id')
                ->join('tb_school_info', 'tb_school_info.id', '=', 'tb_student_enrollment_workflow.to_campus_id')
                ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment_workflow.to_grade_id')
                ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment_workflow.to_class_id')
                ->whereIn('tb_student_enrollment_workflow.action_type', $historyActionTypes)
                ->select([
                    'tb_student_enrollment_workflow.to_academic_year_id as academic_year_id',
                    'tb_student_enrollment_workflow.to_campus_id as campus_id',
                    'tb_student_enrollment_workflow.to_grade_id as grade_id',
                    'tb_student_enrollment_workflow.to_class_id as class_id',
                    'tb_academic_year.academic_year',
                    'tb_school_info.campus_name_en',
                    'tb_grade.grade',
                    'tb_grade.grade_order',
                    'tb_class.class_name',
                ])
                ->distinct()
                ->orderByDesc('tb_academic_year.academic_year')
                ->orderBy('tb_school_info.campus_name_en')
                ->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')
                ->orderBy('tb_class.class_name')
                ->get(),
            'groups' => Session::where('status', 1)->orderBy('session_order')->get(['id', 'session_short_name']),
        ]);
    }

    private function academicYearSortValue(string $academicYear): int
    {
        preg_match_all('/\d{4}/', $academicYear, $matches);
        $years = array_map('intval', $matches[0] ?? []);

        return $years ? max($years) : 0;
    }

    public function enrollmentOptions(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => ['nullable', 'exists:tb_academic_year,id'],
            'campus_id' => ['nullable', 'exists:tb_school_info,id'],
            'grade_id' => ['nullable', 'exists:tb_grade,id'],
            'class_id' => ['nullable', 'exists:tb_class,id'],
            'target_academic_year_id' => ['nullable', 'exists:tb_academic_year,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        $enrollments = StudentEnrollment::with([
                'student:id,student_no,student_id,full_name_en,full_name_kh',
                'campus:id,campus_name_en',
                'academicYear:id,academic_year',
                'grade:id,grade,grade_order',
                'schoolClass:id,class_name',
                'session:id,session_short_name',
            ])
            ->where('status', 1)
            ->where(function ($query) use ($data) {
                $query->where('enrollment_status', 'active')
                    ->orWhereExists(function ($cancelled) use ($data) {
                        $cancelled->selectRaw('1')
                            ->from('tb_student_enrollment_workflow')
                            ->whereColumn('tb_student_enrollment_workflow.source_enrollment_id', 'tb_student_enrollment.id')
                            ->where('tb_student_enrollment_workflow.to_academic_year_id', $data['target_academic_year_id'] ?? 0)
                            ->where('tb_student_enrollment_workflow.status', 'cancelled')
                            ->whereIn('tb_student_enrollment_workflow.action_type', ['promotion', 'class_promotion', 'selected_promotion', 're_promotion']);
                    });
            })
            ->when(!empty($data['academic_year_id']), fn ($query) => $query->where('academic_year_id', $data['academic_year_id']))
            ->when(!empty($data['campus_id']), fn ($query) => $query->where('campus_id', $data['campus_id']))
            ->when(!empty($data['grade_id']), fn ($query) => $query->where('grade_id', $data['grade_id']))
            ->when(!empty($data['class_id']), fn ($query) => $query->where('class_id', $data['class_id']))
            ->when(!empty($data['target_academic_year_id']), fn ($query) => $query->whereNotIn(
                'student_id',
                StudentEnrollment::select('student_id')
                    ->where('academic_year_id', $data['target_academic_year_id'])
                    ->whereNotIn('enrollment_status', ['withdrawn', 'cancelled'])
            ))
            ->when(filled($data['search'] ?? null), function ($query) use ($data) {
                $search = $data['search'];
                $query->whereHas('student', fn ($student) => $student
                    ->where('student_no', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('full_name_en', 'like', "%{$search}%")
                    ->orWhere('full_name_en', 'like', "%{$search}%")
                    ->orWhere('full_name_kh', 'like', "%{$search}%"));
            })
            ->orderByDesc('id')
            ->limit($data['limit'] ?? 50)
            ->get();

        return response()->json($enrollments);
    }

    public function fetch(Request $request)
    {
        $mode = $request->input('mode', session('student_workflow_mode', 'promotion'));
        $gradeClass = (string) $request->query('grade_class', '');
        [$gradeId, $classId] = array_pad(explode(':', $gradeClass, 2), 2, null);
        $sortBy = (string) $request->query('sortBy', 'promoted_date');
        $sortDir = $request->query('sortDir') === 'asc' ? 'asc' : 'desc';
        $query = EnrollmentWorkflowAction::query()
            ->select('tb_student_enrollment_workflow.*')
            ->leftJoin('tb_student', 'tb_student.id', '=', 'tb_student_enrollment_workflow.student_id')
            ->leftJoin('tb_academic_year', 'tb_academic_year.id', '=', 'tb_student_enrollment_workflow.to_academic_year_id')
            ->leftJoin('tb_school_info', 'tb_school_info.id', '=', 'tb_student_enrollment_workflow.to_campus_id')
            ->leftJoin('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment_workflow.to_grade_id')
            ->leftJoin('tb_class', 'tb_class.id', '=', 'tb_student_enrollment_workflow.to_class_id')
            ->leftJoin('tb_session', 'tb_session.id', '=', 'tb_student_enrollment_workflow.to_session_id')
            ->leftJoin('users', 'users.id', '=', 'tb_student_enrollment_workflow.changed_by')
            ->with(['student:id,student_no,student_id,photo_path,full_name_en,full_name_kh', 'toCampus:id,campus_name_en', 'toAcademicYear:id,academic_year', 'toGrade:id,grade', 'toClass:id,class_name', 'toSession:id,session_short_name', 'changedBy:id,name'])
            ->when($request->filled('search'), fn ($q) => $q->whereHas('student', fn ($student) => $student->where('student_no', 'like', '%' . $request->search . '%')->orWhere('student_id', 'like', '%' . $request->search . '%')->orWhere('full_name_en', 'like', '%' . $request->search . '%')->orWhere('full_name_kh', 'like', '%' . $request->search . '%')))
            ->when($request->filled('academic_year_id'), fn ($q) => $q->where('to_academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('campus_id'), fn ($q) => $q->where('to_campus_id', $request->integer('campus_id')))
            ->when($gradeId && $classId, fn ($q) => $q->where('to_grade_id', (int) $gradeId)->where('to_class_id', (int) $classId))
            ->when($mode === 'promotion', fn ($q) => $q->whereIn('action_type', ['promotion', 'class_promotion', 'selected_promotion', 're_promotion']))
            ->when($mode === 'transfer', fn ($q) => $q->whereIn('action_type', ['transfer', 'class_transfer', 'selected_transfer']));

        match ($sortBy) {
            'student_id' => $query->orderBy('tb_student.student_id', $sortDir),
            'student_name' => $query->orderByRaw("COALESCE(NULLIF(tb_student.full_name_en, ''), tb_student.full_name_kh, tb_student.student_id) {$sortDir}"),
            'academic_year' => $query->orderBy('tb_academic_year.academic_year', $sortDir),
            'grade' => $query->orderByRaw("CAST(tb_grade.grade_order AS UNSIGNED) {$sortDir}")->orderBy('tb_class.class_name', $sortDir),
            'group' => $query->orderBy('tb_session.session_short_name', $sortDir),
            'campus' => $query->orderBy('tb_school_info.campus_name_en', $sortDir),
            'action' => $query->orderBy('tb_student_enrollment_workflow.action_type', $sortDir),
            'promoted_by' => $query->orderBy('users.name', $sortDir),
            default => $query->orderBy('tb_student_enrollment_workflow.updated_at', $sortDir)->orderBy('tb_student_enrollment_workflow.id', $sortDir),
        };
        return response()->json($query->paginate($request->integer('perPage', 10)));
    }

    public function promote(Request $request)
    {
        $data = $this->validated($request, true);
        $source = StudentEnrollment::findOrFail($data['enrollment_id']);
        $target = $this->service->promote($source, $data);
        return response()->json(['status' => 'success', 'message' => 'Student promoted successfully.', 'data' => $target]);
    }

    public function cancelPromotion(Request $request, EnrollmentWorkflowAction $workflow)
    {
        $data = $request->validate([
            'effective_on' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $this->service->cancelPromotion($workflow, $data);

        return response()->json(['status' => 'success', 'message' => 'Promotion cancelled and kept in the student history.']);
    }

    public function repromote(Request $request, EnrollmentWorkflowAction $workflow)
    {
        $data = $request->validate([
            'effective_on' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $target = $this->service->repromote($workflow, $data);

        return response()->json(['status' => 'success', 'message' => 'Student promoted again successfully.', 'data' => $target]);
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
            'from_academic_year_id' => ['required', Rule::exists('tb_academic_year', 'id')->where('period_type', 'regular')->whereIn('lifecycle_status', ['started', 'pending'])],
            'from_grade_id' => ['required', 'exists:tb_grade,id'],
            'from_class_id' => ['required', 'exists:tb_class,id'],
            'to_campus_id' => ['nullable', 'exists:tb_school_info,id'],
            'to_academic_year_id' => ['required', Rule::exists('tb_academic_year', 'id')->where('period_type', 'regular')->whereIn('lifecycle_status', ['started', 'pending'])],
            'to_grade_id' => ['required', 'exists:tb_grade,id'],
            'to_class_id' => ['required', 'exists:tb_class,id'],
            'to_session_id' => ['nullable', 'exists:tb_session,id'],
            'effective_on' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $sourceStudentIds = StudentEnrollment::where('campus_id', $data['from_campus_id'])
            ->where('academic_year_id', $data['from_academic_year_id'])
            ->where('grade_id', $data['from_grade_id'])
            ->where('class_id', $data['from_class_id'])
            ->pluck('student_id');

        if ($sourceStudentIds->isNotEmpty() && StudentEnrollment::whereIn('student_id', $sourceStudentIds)
            ->where('academic_year_id', $data['to_academic_year_id'])
            ->exists()) {
            return response()->json([
                'status' => 'warning',
                'message' => 'Students in this class have already been promoted to the selected academic year.',
            ], 422);
        }

        $count = $this->service->promoteClass($data);
        return response()->json(['status' => 'success', 'message' => "{$count} students promoted successfully.", 'count' => $count]);
    }

    public function promoteSelected(Request $request)
    {
        $data = $request->validate([
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer', 'distinct', 'exists:tb_student_enrollment,id'],
            'from_campus_id' => ['required', 'exists:tb_school_info,id'],
            'from_academic_year_id' => ['required', Rule::exists('tb_academic_year', 'id')->where('period_type', 'regular')->whereIn('lifecycle_status', ['started', 'pending'])],
            'from_grade_id' => ['required', 'exists:tb_grade,id'],
            'from_class_id' => ['required', 'exists:tb_class,id'],
            'to_campus_id' => ['nullable', 'exists:tb_school_info,id'],
            'to_academic_year_id' => ['required', Rule::exists('tb_academic_year', 'id')->where('period_type', 'regular')->whereIn('lifecycle_status', ['started', 'pending'])],
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
            'to_academic_year_id' => [$promotion ? 'required' : 'nullable', Rule::exists('tb_academic_year', 'id')->whereIn('period_type', ['regular', 'summer'])->whereIn('lifecycle_status', ['started', 'pending'])],
            'to_grade_id' => [$promotion ? 'required' : 'nullable', 'exists:tb_grade,id'],
            'to_class_id' => [$promotion ? 'required' : 'nullable', 'exists:tb_class,id'],
            'to_session_id' => ['nullable', 'exists:tb_session,id'],
            'effective_on' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
