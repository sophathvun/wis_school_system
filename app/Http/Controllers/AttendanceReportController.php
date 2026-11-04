<?php

namespace App\Http\Controllers;

use App\Models\{AcademicYear, SchoolInfo, StudentEnrollment};
use App\Support\AttendanceStudyGroup;
use App\Support\AttendancePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceReportController
{
    private function campusIds(Request $request, string $tab = 'detail'): array
    {
        return AttendancePermissions::reportCampuses($request->user(), $tab);
    }

    private function filters(Request $request, ?string $tab = null): array
    {
        $input = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:tb_academic_year,id'],
            'campus_id' => ['nullable', 'integer'],
            'grade_class' => ['nullable', 'regex:/^\d+:\d+$/'],
            'student_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:200'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'per_page' => ['nullable', Rule::in(['all', '15', '25', '50', '75', '100'])],
        ]);
        $tab ??= $request->input('report', 'detail');
        abort_unless(in_array($tab, ['detail','statistics'], true), 422);
        $ids = $this->campusIds($request, $tab);
        abort_unless($ids, 403);
        if (!empty($input['campus_id'])) abort_unless(in_array((int) $input['campus_id'], $ids, true), 403);
        return $input;
    }

    private function enrollments(Request $request, array $input)
    {
        return StudentEnrollment::query()->whereIn('campus_id', SchoolInfo::where('status', true)->select('id'))
            ->where('academic_year_id', $input['academic_year_id'])
            ->whereNotIn('enrollment_status', ['pending', 'cancelled', 'promotion_cancelled'])
            ->when($input['campus_id'] ?? null, fn ($q, $id) => $q->where('campus_id', $id));
    }

    private function gradeClass(StudentEnrollment $enrollment): string
    {
        $grade = trim((string) ($enrollment->grade?->grade_short_name ?: $enrollment->grade?->grade));
        $class = trim((string) $enrollment->schoolClass?->class_name);
        return $grade !== '' && !str_starts_with($class, $grade) ? $grade.$class : $class;
    }

    private function applyGrade($query, array $input): void
    {
        if (!empty($input['grade_class'])) {
            [$grade, $class] = explode(':', $input['grade_class']);
            $query->where('class_id', $class)->whereRaw('COALESCE(grade_id, 0) = ?', [(int) $grade]);
        }
    }

    public function years(Request $request)
    {
        abort_unless(AttendancePermissions::allows($request->user(), 'reports'), 403);
        return response()->json(AcademicYear::orderByDesc('academic_year')->get(['id', 'academic_year']));
    }

    public function options(Request $request)
    {
        $input = $this->filters($request);
        $base = $this->enrollments($request, ['academic_year_id' => $input['academic_year_id']]);
        $campuses = SchoolInfo::whereIn('id', (clone $base)->select('campus_id')->distinct())
            ->orderBy('campus_name_en')->get(['id', 'campus_name_en']);
        $query = $this->enrollments($request, $input);
        $grades = (clone $query)->with(['grade', 'schoolClass'])->get()->unique(fn ($e) => ($e->grade_id ?? 0).':'.$e->class_id)
            ->map(fn ($e) => ['id' => ($e->grade_id ?? 0).':'.$e->class_id, 'label' => $this->gradeClass($e)])
            ->sortBy('label', SORT_NATURAL)->values();
        $this->applyGrade($query, $input);
        $students = $query->with('student')->get()->unique('student_id')->map(fn ($e) => [
            'id' => $e->student_id,
            'label' => trim(($e->student?->student_id ?? '').' · '.($e->student?->full_name_kh ?? '').' · '.($e->student?->full_name_en ?? '')),
        ])->sortBy('label')->values();
        return response()->json(['campuses' => $campuses, 'grades' => $grades, 'students' => $students,
            'years' => AcademicYear::orderByDesc('academic_year')->get(['id', 'academic_year'])]);
    }

    public function details(Request $request)
    {
        $input = $this->filters($request, 'detail');
        $request->validate(['student_id' => ['required', 'integer']]);
        $enrollments = $this->enrollments($request, $input);
        $this->applyGrade($enrollments, $input);
        $enrollments->when($input['student_id'] ?? null, fn ($q, $id) => $q->where('student_id', $id));
        $profile = (clone $enrollments)->with(['student', 'grade', 'schoolClass', 'academicYear', 'campus', 'session', 'schoolGroup'])->orderByDesc('id')->firstOrFail();
        $query = DB::table('attendance_daily_marks')->whereIn('enrollment_id', $enrollments->select('id'))
            ->when($input['date_from'] ?? null, fn ($q, $date) => $q->where('attendance_date', '>=', $date))
            ->when($input['date_to'] ?? null, fn ($q, $date) => $q->where('attendance_date', '<=', $date));
        $counts = (clone $query)->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $totals = collect(['P', 'EX', 'UX', 'L'])->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all();
        $query->whereIn('status', ['EX', 'UX', 'L'])
            ->when($input['search'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('remarks', 'like', '%'.$term.'%')->orWhere('status', 'like', '%'.$term.'%')))
            ->orderByDesc('attendance_date')->orderBy('id');
        $size = ($input['per_page'] ?? '15') === 'all' ? max(1, (clone $query)->count()) : (int) ($input['per_page'] ?? 15);
        $result = $query->paginate($size, ['*'], 'page', max(1, $request->integer('page', 1)));
        $records = StudentEnrollment::with(['student', 'grade', 'schoolClass', 'academicYear', 'campus', 'session', 'schoolGroup'])
            ->whereIn('id', $result->getCollection()->pluck('enrollment_id'))->get()->keyBy('id');
        $result->setCollection($result->getCollection()->map(function ($mark) use ($records) {
            $enrollment = $records[$mark->enrollment_id];
            $student = $enrollment->student;
            return ['id' => $mark->id, 'student_code' => $student?->student_id, 'name_kh' => $student?->full_name_kh,
                'name_en' => $student?->full_name_en, 'photo_url' => $student?->photo_path ? asset('storage/'.ltrim($student->photo_path, '/')) : null,
                'academic_year' => $enrollment->academicYear?->academic_year,
                'grade' => implode('-', array_filter([$enrollment->campus?->campus_name_en, $this->gradeClass($enrollment), AttendanceStudyGroup::code($enrollment)])),
                'date' => $mark->attendance_date, 'status' => $mark->status, 'reason' => $mark->remarks];
        }));
        return response()->json(array_merge($result->toArray(), ['totals' => $totals, 'student' => [
            'student_code' => $profile->student?->student_id,
            'name_kh' => $profile->student?->full_name_kh,
            'name_en' => $profile->student?->full_name_en,
            'gender' => $profile->student?->gender ?: $profile->student?->gender_kh,
            'class' => $this->gradeClass($profile),
            'group' => AttendanceStudyGroup::code($profile),
            'campus' => $profile->campus?->campus_name_en,
            'photo_url' => $profile->student?->photo_path ? asset('storage/'.ltrim($profile->student->photo_path, '/')) : null,
            'academic_year' => $profile->academicYear?->academic_year,
            'grade' => implode('-', array_filter([$profile->campus?->campus_name_en, $this->gradeClass($profile), AttendanceStudyGroup::code($profile)])),
        ]]));
    }

    public function statistics(Request $request)
    {
        $input = $this->filters($request, 'statistics');
        $scope = $this->enrollments($request, $input);
        $this->applyGrade($scope, $input);
        $scope->when($input['student_id'] ?? null, fn ($q, $id) => $q->where('student_id', $id));
        // One row per student, including students with no recorded absences.
        $latest = (clone $scope)->selectRaw('MAX(id)')->groupBy('student_id');
        $query = StudentEnrollment::with(['student', 'grade', 'schoolClass', 'campus', 'session', 'schoolGroup'])
            ->whereIn('id', $latest)->orderBy('student_id');
        $size = ($input['per_page'] ?? '15') === 'all' ? max(1, (clone $query)->count()) : (int) ($input['per_page'] ?? 15);
        $result = $query->paginate($size, ['*'], 'page', max(1, $request->integer('page', 1)));
        $counts = DB::table('attendance_daily_marks')->whereIn('enrollment_id', (clone $scope)->select('id'))
            ->whereIn('student_id', $result->getCollection()->pluck('student_id'))
            ->when($input['date_from'] ?? null, fn ($q, $date) => $q->where('attendance_date', '>=', $date))
            ->when($input['date_to'] ?? null, fn ($q, $date) => $q->where('attendance_date', '<=', $date))
            ->whereIn('status', ['EX', 'UX', 'L'])->select('student_id', 'status')->selectRaw('COUNT(*) as total')
            ->groupBy('student_id', 'status')->get()->groupBy('student_id');
        $result->setCollection($result->getCollection()->map(function ($enrollment) use ($counts) {
            $totals = ($counts->get($enrollment->student_id) ?? collect())->pluck('total', 'status');
            $ex = (int) ($totals['EX'] ?? 0); $ux = (int) ($totals['UX'] ?? 0);
            return [
                'student_id' => $enrollment->student_id, 'student_code' => $enrollment->student?->student_id,
                'name_kh' => $enrollment->student?->full_name_kh, 'name_en' => $enrollment->student?->full_name_en,
                'gender' => $enrollment->student?->gender ?: $enrollment->student?->gender_kh,
                'group' => AttendanceStudyGroup::code($enrollment),
                'class' => $this->gradeClass($enrollment),
                'photo_url' => $enrollment->student?->photo_path ? asset('storage/'.ltrim($enrollment->student->photo_path, '/')) : null,
                'grade' => implode('-', array_filter([$enrollment->campus?->campus_name_en, $this->gradeClass($enrollment), AttendanceStudyGroup::code($enrollment)])),
                'total_absent' => $ex + $ux, 'EX' => $ex, 'UX' => $ux, 'L' => (int) ($totals['L'] ?? 0),
                'academic_year_id' => $enrollment->academic_year_id, 'campus_id' => $enrollment->campus_id,
                'grade_class' => ($enrollment->grade_id ?? 0).':'.$enrollment->class_id,
            ];
        }));
        return response()->json($result);
    }

    public function statisticsExport(Request $request, string $format)
    {
        abort_unless(in_array($format, ['print', 'xlsx', 'pdf'], true), 404);
        abort_unless(AttendancePermissions::allows($request->user(), $format === 'xlsx' ? 'excel' : $format), 403);
        $exportRequest = $request->duplicate(array_merge($request->query->all(), ['per_page' => 'all', 'page' => 1]));
        $data = $this->statistics($exportRequest)->getData(true);
        $input = $this->filters($request, 'statistics');
        $grade = '';
        if (!empty($input['grade_class'])) {
            [$gradeId, $classId] = explode(':', $input['grade_class']);
            $grade = $this->gradeClass(new StudentEnrollment(['grade_id' => (int) $gradeId, 'class_id' => (int) $classId]));
        }
        $data['title'] = 'Student Absence Report'.($grade ? ' - Grade '.$grade : '');
        $data['selectedGrade'] = $grade ?: 'All Grades';
        $data['groupByGrade'] = $grade === '';
        if ($data['groupByGrade']) $data['data'] = collect($data['data'])->sortBy('class', SORT_NATURAL)->values()->all();
        $data['academicYear'] = AcademicYear::findOrFail($input['academic_year_id'])->academic_year;
        $data['campus'] = !empty($input['campus_id']) ? SchoolInfo::findOrFail($input['campus_id'])->campus_name_en : 'All selected campuses';
        $data['dateRange'] = ($request->filled('date_from') ? \Carbon\CarbonImmutable::parse($request->date_from)->format('d-M-Y') : 'Start of academic year')
            .' to '.($request->filled('date_to') ? \Carbon\CarbonImmutable::parse($request->date_to)->format('d-M-Y') : 'Latest recorded date');
        $data['logo'] = app(\App\Support\ScholarshipExport::class)->reportLogo(1);
        if ($format === 'print') return response()->view('attendance.statistics-print', $data)->header('Cache-Control', 'private, no-store');
        $export = app(\App\Support\AttendanceRecordExport::class);
        return response($format === 'pdf' ? $export->pdf($data, true) : $export->statisticsXlsx($data), 200, [
            'Content-Type' => $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="student-absence-statistics.'.$format.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function export(Request $request, string $format)
    {
        abort_unless(in_array($format, ['print', 'xlsx', 'pdf'], true), 404);
        abort_unless(AttendancePermissions::allows($request->user(), $format === 'xlsx' ? 'excel' : $format), 403);
        $exportRequest = $request->duplicate(array_merge($request->query->all(), ['per_page' => 'all', 'page' => 1]));
        $data = $this->details($exportRequest)->getData(true);
        $data['logo'] = app(\App\Support\ScholarshipExport::class)->reportLogo(1);
        $data['dateRange'] = ($request->filled('date_from') ? \Carbon\CarbonImmutable::parse($request->date_from)->format('d-M-Y') : 'Start of academic year')
            .' to '.($request->filled('date_to') ? \Carbon\CarbonImmutable::parse($request->date_to)->format('d-M-Y') : 'Latest recorded date');
        if ($format === 'print') return response()->view('attendance.record-print', $data)->header('Cache-Control', 'private, no-store');
        $export = app(\App\Support\AttendanceRecordExport::class);
        $filename = 'student-absent-record-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $data['student']['student_code'] ?? 'student');
        return response($format === 'pdf' ? $export->pdf($data) : $export->xlsx($data), 200, [
            'Content-Type' => $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.'.$format.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
