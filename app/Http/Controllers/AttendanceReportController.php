<?php

namespace App\Http\Controllers;

use App\Models\{AcademicYear, SchoolInfo, StudentEnrollment};
use App\Support\AttendanceStudyGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceReportController
{
    private function campusIds(Request $request): array
    {
        return $request->user()->accessibleCampuses()->get()->filter(fn ($campus) =>
            $request->user()->hasPermission('attendance.view', $campus->id)
            && $request->user()->hasPermission('attendance.manage', $campus->id)
        )->modelKeys();
    }

    private function filters(Request $request): array
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
        $ids = $this->campusIds($request);
        abort_unless($ids, 403);
        if (!empty($input['campus_id'])) abort_unless(in_array((int) $input['campus_id'], $ids, true), 403);
        return $input;
    }

    private function enrollments(Request $request, array $input)
    {
        return StudentEnrollment::query()->whereIn('campus_id', $this->campusIds($request))
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
        abort_unless($this->campusIds($request), 403);
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
        $input = $this->filters($request);
        $request->validate(['student_id' => ['required', 'integer']]);
        $enrollments = $this->enrollments($request, $input);
        $this->applyGrade($enrollments, $input);
        $enrollments->when($input['student_id'] ?? null, fn ($q, $id) => $q->where('student_id', $id));
        $profile = (clone $enrollments)->with(['student', 'grade', 'schoolClass', 'academicYear', 'campus', 'session', 'schoolGroup'])->orderByDesc('id')->firstOrFail();
        $query = DB::table('attendance_daily_marks')->whereIn('enrollment_id', $enrollments->select('id'))
            ->whereIn('status', ['EX', 'UX', 'L'])
            ->when($input['date_from'] ?? null, fn ($q, $date) => $q->where('attendance_date', '>=', $date))
            ->when($input['date_to'] ?? null, fn ($q, $date) => $q->where('attendance_date', '<=', $date))
            ->when($input['search'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('remarks', 'like', '%'.$term.'%')->orWhere('status', 'like', '%'.$term.'%')))
            ->orderByDesc('attendance_date')->orderBy('id');
        $size = ($input['per_page'] ?? '15') === 'all' ? max(1, (clone $query)->count()) : (int) ($input['per_page'] ?? 15);
        $result = $query->paginate($size);
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
        return response()->json(array_merge($result->toArray(), ['student' => [
            'student_code' => $profile->student?->student_id,
            'name_kh' => $profile->student?->full_name_kh,
            'name_en' => $profile->student?->full_name_en,
            'photo_url' => $profile->student?->photo_path ? asset('storage/'.ltrim($profile->student->photo_path, '/')) : null,
            'academic_year' => $profile->academicYear?->academic_year,
            'grade' => implode('-', array_filter([$profile->campus?->campus_name_en, $this->gradeClass($profile), AttendanceStudyGroup::code($profile)])),
        ]]));
    }
}
