<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\SchoolInfo;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnrollmentListSelection
{
    public function activeYear(): ?AcademicYear
    {
        return AcademicYear::regular()->where('lifecycle_status', 'started')
            ->orderByDesc('academic_year')->orderByDesc('id')->first();
    }

    public function apply($query, Request $request, bool $includeLatest = false)
    {
        $yearId = $request->integer('academic_year_id');
        $activeYearId = $this->requestActiveYearId($request);
        $searching = trim((string) $request->query('search', '')) !== '' || $request->filled('student_id');
        // Materialize the small matching ID set first. Otherwise MySQL can run
        // the preferred-year lookup for every enrollment before testing names.
        if (trim((string) $request->query('search', '')) !== '') {
            $query->whereIntegerInRaw('tb_student_enrollment.student_id', $this->matchingStudentIds($request));
        }
        if ($request->filled('student_id')) $query->where('tb_student_enrollment.student_id', $request->integer('student_id'));
        $preferred = DB::table('tb_student_enrollment as preferred_enrollment')
            ->join('tb_academic_year as preferred_year', 'preferred_year.id', '=', 'preferred_enrollment.academic_year_id')
            ->whereColumn('preferred_enrollment.student_id', 'tb_student_enrollment.student_id')
            ->select('preferred_enrollment.id');

        if ($yearId) {
            $query->where('tb_student_enrollment.academic_year_id', $yearId);
            $preferred->where('preferred_enrollment.academic_year_id', $yearId);
        } elseif (! $searching && ! $includeLatest) {
            $query->where('tb_student_enrollment.academic_year_id', $activeYearId ?? 0);
            $preferred->where('preferred_enrollment.academic_year_id', $activeYearId ?? 0);
        } elseif ($activeYearId) {
            $preferred->orderByRaw('CASE WHEN preferred_enrollment.academic_year_id = ? THEN 0 ELSE 1 END', [$activeYearId]);
        }

        // Choose a single placement before pagination and other list filters.
        // Prefer the current active placement when transfers left multiple rows.
        $preferred->orderByDesc('preferred_year.academic_year')
            ->orderByRaw("CASE WHEN preferred_enrollment.enrollment_status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('preferred_enrollment.id')->limit(1);

        return $query->where('tb_student_enrollment.id', '=', $preferred);
    }

    private function requestActiveYearId(Request $request): ?int
    {
        if (! $request->attributes->has('enrollment_list_active_year_id')) {
            $request->attributes->set('enrollment_list_active_year_id', $this->activeYear()?->id);
        }
        return $request->attributes->get('enrollment_list_active_year_id');
    }

    private function matchingStudentIds(Request $request): array
    {
        if ($request->attributes->has('enrollment_list_matching_students')) return $request->attributes->get('enrollment_list_matching_students');
        $pattern = '%'.trim((string) $request->query('search', '')).'%';
        $students = Student::query()->where(function ($matching) use ($pattern) {
            $matching->where('student_no', 'like', $pattern)->orWhere('student_id', 'like', $pattern)
                ->orWhere('family_number', 'like', $pattern)->orWhere('full_name_en', 'like', $pattern)
                ->orWhere('full_name_kh', 'like', $pattern);
        })->pluck('id');
        $campuses = SchoolInfo::query()->where(function ($matching) use ($pattern) {
            $matching->where('campus_name_en', 'like', $pattern)->orWhere('campus_name_kh', 'like', $pattern);
        })->pluck('id');
        if ($campuses->isNotEmpty()) {
            $students = $students->merge(DB::table('tb_student_enrollment')->whereIn('campus_id', $campuses)->distinct()->pluck('student_id'));
        }
        $ids = $students->map(fn ($id) => (int) $id)->unique()->values()->all();
        $request->attributes->set('enrollment_list_matching_students', $ids);
        return $ids;
    }
}
