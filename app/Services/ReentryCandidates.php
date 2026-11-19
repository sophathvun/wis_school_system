<?php

namespace App\Services;

use App\Models\{AcademicYear, StudentEnrollment};
use Illuminate\Database\Eloquent\Builder;

class ReentryCandidates
{
    public function targetYear(?int $id = null): ?AcademicYear
    {
        $query = AcademicYear::regular()->whereIn('lifecycle_status', ['started', 'pending']);
        return $id ? $query->find($id) : $query->orderByRaw("CASE WHEN lifecycle_status = 'started' THEN 0 ELSE 1 END")
            ->orderBy('academic_year')->first();
    }

    public function query(?AcademicYear $target): Builder
    {
        $query = StudentEnrollment::query()->whereHas('academicYear', fn ($q) => $q->where('period_type', 'regular'));
        if (!$target || !preg_match('/^(\d{4})-\d{4}$/', $target->academic_year, $match)) {
            return $query->whereRaw('1 = 0');
        }
        $previousYear = ((int) $match[1] - 1).'-'.$match[1];
        return $query->whereHas('academicYear', fn ($q) => $q->where('academic_year', '<=', $target->academic_year))
            ->where(function ($q) use ($previousYear) {
                $q->where('enrollment_status', 'withdrawn')
                    ->orWhere(fn ($gap) => $gap->whereIn('enrollment_status', ['completed', 'finished'])
                        ->whereHas('academicYear', fn ($year) => $year->where('academic_year', '<', $previousYear)));
            })
            // Summer attendance does not erase a regular-school study gap.
            ->whereNotExists(function ($later) {
                $later->selectRaw('1')->from('tb_student_enrollment as later')
                    ->join('tb_academic_year as ly', 'ly.id', '=', 'later.academic_year_id')
                    ->join('tb_academic_year as sy', 'sy.id', '=', 'tb_student_enrollment.academic_year_id')
                    ->whereColumn('later.student_id', 'tb_student_enrollment.student_id')
                    ->where('ly.period_type', 'regular')->whereNull('ly.deleted_at')
                    ->where(fn ($q) => $q->whereColumn('ly.academic_year', '>', 'sy.academic_year')
                        ->orWhere(fn ($same) => $same->whereColumn('later.academic_year_id', 'tb_student_enrollment.academic_year_id')
                            ->whereColumn('later.id', '>', 'tb_student_enrollment.id')));
            })
            ->whereNotExists(fn ($used) => $used->selectRaw('1')->from('tb_student_enrollment_history as used')
                ->where('used.action_type', 're_entry')->whereColumn('used.source_enrollment_id', 'tb_student_enrollment.id'))
            ->whereNotExists(fn ($used) => $used->selectRaw('1')->from('tb_student_enrollment_history as used')
                ->join('tb_student_enrollment_history as source', 'source.id', '=', 'used.source_history_id')
                ->where('used.action_type', 're_entry')->whereColumn('source.enrollment_id', 'tb_student_enrollment.id'));
    }
}
