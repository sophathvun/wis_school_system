<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolInfo;
use App\Models\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportsController
{
    private const TYPES = [
        'student-list' => 'Student List',
        'student-contact-list' => 'Student Contact List',
        'score-list' => 'Score List',
        'attendance-list' => 'Attendance List',
        'student-statistics' => 'Student Statistics by Grade and Campus',
    ];

    public function index(Request $request)
    {
        $type = $request->query('type', 'student-list');
        abort_unless(isset(self::TYPES[$type]), 404);
        $payload = $this->reportPayload($request, $type);

        return view('reports.index', $payload + [
            'type' => $type,
            'reportTypes' => self::TYPES,
            'academicYears' => $this->academicYears($payload['filters']),
            'campuses' => $this->campuses($request, $payload['filters']),
            'grades' => Grade::where('status', 1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->get(['id', 'grade']),
            'gradeClassOptions' => $type === 'student-list' ? $this->gradeClassOptions($request, $payload['filters']) : collect(),
            'groupOptions' => $type === 'student-list' ? $this->groupOptions($request, $payload['filters']) : collect(),
        ]);
    }

    public function excel(Request $request, string $type)
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $payload = $this->reportPayload($request, $type);
        $filename = str_replace(' ', '-', strtolower(self::TYPES[$type])) . '.csv';
        return response()->streamDownload(function () use ($payload, $type) {
            $output = fopen('php://output', 'w');
            if ($type === 'student-statistics') {
                fputcsv($output, array_merge(['Campus'], $payload['statistics']['columns'], ['Total']));
                foreach ($payload['statistics']['rows'] as $row) fputcsv($output, array_merge([$row['campus']], $row['cells'], [$row['total']]));
                fputcsv($output, array_merge(['Grand Total'], $payload['statistics']['columnTotals'], [$payload['statistics']['grandTotal']]));
            } else {
                fputcsv($output, ['Student ID', 'Student Name (Khmer / English)', 'Gender', 'Academic Year', 'Campus', 'Class', 'Group', 'Status']);
                foreach ($payload['enrollments'] as $row) fputcsv($output, [$row->student?->student_id, ($row->student?->full_name_kh ?: '') . ' / ' . ($row->student?->full_name_en ?: ''), strtoupper(substr((string) $row->student?->gender, 0, 1)) === 'F' ? 'F' : 'M', $row->academicYear?->academic_year, $row->campus?->campus_name_en, ($row->grade?->grade ?? '') . ($row->schoolClass?->class_name ?? ''), $row->session?->session_short_name, $row->enrollment_status]);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Request $request, string $type)
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        return view('reports.print', $this->reportPayload($request, $type) + [
            'type' => $type,
            'title' => self::TYPES[$type],
            'academicYear' => $request->filled('academic_year_id') ? AcademicYear::find($request->integer('academic_year_id')) : null,
            'campus' => $request->filled('campus_id') ? SchoolInfo::find($request->integer('campus_id')) : null,
        ]);
    }

    private function reportPayload(Request $request, string $type): array
    {
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer'],
            'period_type' => ['nullable', 'in:all,regular,summer'],
            'campus_id' => ['nullable', 'integer'],
            'grade_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'grade_class' => ['nullable', 'string'],
            'session_id' => ['nullable', 'integer'],
            'print_scope' => ['nullable', 'in:selected_class,selected_classes,all_classes'],
            'print_format' => ['nullable', 'in:internal,moeys'],
            'print_grade_classes' => ['nullable', 'array'],
            'print_grade_classes.*' => ['string'],
            'report_date' => ['nullable', 'date'],
            'month' => ['nullable', 'date_format:Y-m'],
            'score_columns' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);
        $filters['month'] = $filters['month'] ?? now()->format('Y-m');
        $filters['period_type'] = $filters['period_type'] ?? 'all';
        $filters['score_columns'] = (int) ($filters['score_columns'] ?? 5);
        $filters['report_date'] = $filters['report_date'] ?? now()->format('Y-m-d');
        if (!empty($filters['grade_class']) && str_contains($filters['grade_class'], ':')) {
            [$filters['grade_id'], $filters['class_id']] = array_map('intval', explode(':', $filters['grade_class'], 2));
        }
        if ($type === 'student-list' && !empty($filters['print_grade_classes'])) {
            unset($filters['grade_id'], $filters['class_id'], $filters['grade_class']);
        } elseif ($type === 'student-list' && ($filters['print_scope'] ?? null) === 'all_classes') {
            unset($filters['grade_id'], $filters['class_id'], $filters['grade_class']);
        }
        $hasDataFilter = collect(['academic_year_id', 'campus_id', 'grade_id', 'class_id'])
            ->contains(fn ($key) => filled($filters[$key] ?? null));
        $hasDataFilter = $hasDataFilter || ($filters['period_type'] ?? 'all') !== 'all';
        $enrollments = $hasDataFilter ? $this->enrollments($request, $filters)->get() : collect();

        return [
            'filters' => $filters,
            'enrollments' => $enrollments,
            'statistics' => $type === 'student-statistics' ? ($hasDataFilter ? $this->statistics($request, $filters) : $this->emptyStatistics()) : null,
            'hasDataFilter' => $hasDataFilter,
        ];
    }

    private function academicYears(array $filters): Collection
    {
        return AcademicYear::query()
            ->when(($filters['period_type'] ?? 'all') !== 'all', fn ($q) => $q->where('period_type', $filters['period_type']))
            ->orderByDesc('academic_year')
            ->get(['id', 'academic_year', 'period_type', 'parent_academic_year_id']);
    }

    private function emptyStatistics(): array
    {
        $columns = Grade::where('status', 1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->pluck('grade')->all();
        return ['columns' => $columns, 'rows' => [], 'columnTotals' => array_fill(0, count($columns), 0), 'grandTotal' => 0];
    }

    private function campuses(Request $request, array $filters = []): Collection
    {
        $campuses = $request->user()->isSuperAdmin()
            ? SchoolInfo::where('status', 1)->orderBy('campus_name_en')->get(['id', 'campus_name_en'])
            : $request->user()->accessibleCampuses()->where('tb_school_info.status', 1)->orderBy('campus_name_en')->get(['tb_school_info.id', 'campus_name_en']);

        if (!empty($filters['academic_year_id'])) {
            $ids = StudentEnrollment::where('academic_year_id', $filters['academic_year_id'])->pluck('campus_id')->unique();
            $campuses = $campuses->whereIn('id', $ids)->values();
        }
        return $campuses;
    }

    private function enrollments(Request $request, array $filters)
    {
        return StudentEnrollment::with(['student.contacts', 'academicYear', 'campus', 'grade', 'schoolClass', 'academicTrack', 'session'])
            ->where('tb_student_enrollment.status', 1)
            ->whereIn('tb_student_enrollment.enrollment_status', ['active', 'completed'])
            ->whereHas('student', fn ($q) => $q->where('tb_student.status', 1))
            ->when(($filters['period_type'] ?? 'all') !== 'all', fn ($q) => $q->whereHas('academicYear', fn ($year) => $year->where('period_type', $filters['period_type'])))
            ->when(!$request->user()->isSuperAdmin(), fn ($q) => $q->whereIn('campus_id', $request->user()->accessibleCampuses()->pluck('tb_school_info.id')))
            ->when($filters['academic_year_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.academic_year_id', $id))
            ->when($filters['campus_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.campus_id', $id))
            ->when($filters['grade_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.grade_id', $id))
            ->when($filters['class_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.class_id', $id))
            ->when($filters['session_id'] ?? null, fn ($q, $id) => $q->where('session_id', $id))
            ->when($filters['print_grade_classes'] ?? null, function ($q, $values) {
                $pairs = collect($values)->map(fn ($value) => array_map('intval', explode(':', $value, 2)))->filter(fn ($pair) => count($pair) === 2)->values();
                $q->where(function ($nested) use ($pairs) {
                    foreach ($pairs as [$gradeId, $classId]) $nested->orWhere(fn ($pairQuery) => $pairQuery->where('tb_student_enrollment.grade_id', $gradeId)->where('tb_student_enrollment.class_id', $classId));
                });
            })
            ->orderByRaw("LOWER(COALESCE((SELECT full_name_en FROM tb_student WHERE tb_student.id = tb_student_enrollment.student_id), '')) ASC")
            ->orderBy('tb_student_enrollment.student_id');
    }

    private function reportOptionEnrollments(Request $request, array $filters)
    {
        return StudentEnrollment::query()
            ->when(($filters['period_type'] ?? 'all') !== 'all', fn ($q) => $q->whereHas('academicYear', fn ($year) => $year->where('period_type', $filters['period_type'])))
            ->when(!$request->user()->isSuperAdmin(), fn ($q) => $q->whereIn('campus_id', $request->user()->accessibleCampuses()->pluck('tb_school_info.id')))
            ->when($filters['academic_year_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.academic_year_id', $id))
            ->when($filters['campus_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.campus_id', $id))
            ->when($filters['grade_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.grade_id', $id))
            ->when($filters['class_id'] ?? null, fn ($q, $id) => $q->where('tb_student_enrollment.class_id', $id));
    }

    private function gradeClassOptions(Request $request, array $filters): Collection
    {
        $filters = array_diff_key($filters, array_flip(['grade_id', 'class_id', 'grade_class']));
        return $this->reportOptionEnrollments($request, $filters)
            ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
            ->join('tb_class', 'tb_class.id', '=', 'tb_student_enrollment.class_id')
            ->select('tb_student_enrollment.grade_id', 'tb_student_enrollment.class_id', 'tb_grade.grade', 'tb_class.class_name', 'tb_grade.grade_order', 'tb_class.class_order')
            ->distinct()->orderByRaw('CAST(tb_grade.grade_order AS UNSIGNED)')->orderByRaw('CAST(tb_class.class_order AS UNSIGNED)')->get()
            ->map(fn ($row) => ['value' => $row->grade_id . ':' . $row->class_id, 'label' => $row->grade . $row->class_name]);
    }

    private function groupOptions(Request $request, array $filters): Collection
    {
        return $this->reportOptionEnrollments($request, $filters)
            ->whereNotNull('tb_student_enrollment.session_id')->join('tb_session', 'tb_session.id', '=', 'tb_student_enrollment.session_id')
            ->select('tb_student_enrollment.session_id', 'tb_session.session_short_name', 'tb_session.session_order')->distinct()->orderBy('tb_session.session_order')->get();
    }

    private function statistics(Request $request, array $filters): array
    {
        $query = $this->enrollments($request, $filters)
            ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
            ->join('tb_school_info', 'tb_school_info.id', '=', 'tb_student_enrollment.campus_id')
            ->join('tb_grade', 'tb_grade.id', '=', 'tb_student_enrollment.grade_id')
            ->reorder()
            ->select('tb_student_enrollment.campus_id', 'tb_school_info.campus_name_en', 'tb_student_enrollment.grade_id', 'tb_grade.grade', DB::raw('COUNT(DISTINCT tb_student_enrollment.student_id) as total'))
            ->groupBy('tb_student_enrollment.campus_id', 'tb_school_info.campus_name_en', 'tb_student_enrollment.grade_id', 'tb_grade.grade')
            ->orderBy('tb_school_info.campus_name_en')
            ->orderByRaw('CAST(tb_grade.grade AS UNSIGNED)')
            ->get();
        $grades = Grade::where('status', 1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->get(['id', 'grade']);
        $campusRows = $query->groupBy('campus_id')->map(function ($items) use ($grades) {
            $byGrade = $items->keyBy('grade_id');
            $cells = $grades->map(fn ($grade) => (int) ($byGrade->get($grade->id)->total ?? 0))->all();
            return ['campus' => $items->first()->campus_name_en, 'cells' => $cells, 'total' => array_sum($cells)];
        })->values()->all();
        $columns = $grades->pluck('grade')->all();
        $columnTotals = array_map(fn ($i) => array_sum(array_column($campusRows, 'cells')[$i] ?? []), array_keys($columns));
        return ['columns' => $columns, 'rows' => $campusRows, 'columnTotals' => $columnTotals, 'grandTotal' => array_sum($columnTotals)];
    }
}
