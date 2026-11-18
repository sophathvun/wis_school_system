<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatisticsDetailWithdrawals
{
    public function rows(Request $request, array $filters): Collection
    {
        $today = Carbon::now('Asia/Phnom_Penh')->startOfDay();
        $month = Carbon::createFromFormat('!Y-m-d', ($filters['month'] ?? $today->format('Y-m')).'-01');
        $cutoff = $month->copy()->endOfMonth()->min($today)->toDateString();
        $start = $month->copy()->month(9)->startOfMonth();
        if ($month->month < 9) $start->subYear();
        $history = DB::table('tb_student_enrollment_history as h')
            ->where('h.action_type', 'withdrawal')->where('h.withdrawal_status', 'approved')
            ->where('h.enrollment_status', 'withdrawn')
            ->select('h.student_id', 'h.academic_year_id', 'h.campus_id', 'h.grade_id', 'h.class_id', 'h.session_id', 'h.effective_on as withdrawal_date');
        // Imported withdrawals have no approval workflow history. Their enrollment
        // end date is the withdrawal date; never substitute a creation timestamp.
        $imported = DB::table('tb_student_enrollment as e')->where('e.enrollment_status', 'withdrawn')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('tb_student_enrollment_history as h')
                ->whereColumn('h.enrollment_id', 'e.id')->where('h.action_type', 'withdrawal')
                ->where('h.withdrawal_status', 'approved')->where('h.enrollment_status', 'withdrawn'))
            ->select('e.student_id', 'e.academic_year_id', 'e.campus_id', 'e.grade_id', 'e.class_id', 'e.session_id', 'e.ended_on as withdrawal_date');
        $events = DB::query()->fromSub($history->unionAll($imported), 'w')
            ->join('tb_academic_year as y', 'y.id', '=', 'w.academic_year_id')
            ->join('tb_school_info as c', 'c.id', '=', 'w.campus_id')
            ->join('tb_grade as g', 'g.id', '=', 'w.grade_id')
            ->leftJoin('tb_class as cl', 'cl.id', '=', 'w.class_id')
            ->whereBetween('w.withdrawal_date', [$start->toDateString(), $cutoff])
            ->when(($filters['period_type'] ?? 'all') !== 'all', fn ($q) => $q->where('y.period_type', $filters['period_type']))
            ->when(!$request->user()->isSuperAdmin(), fn ($q) => $q->whereIn('w.campus_id', $request->user()->accessibleCampuses()->pluck('tb_school_info.id')));
        foreach (['academic_year_id', 'campus_id', 'grade_id', 'class_id', 'session_id'] as $field) {
            if (!empty($filters[$field])) $events->where('w.'.$field, $filters[$field]);
        }
        return $events->select('w.*', 'c.campus_name_en', 'g.grade', 'g.grade_short_name', 'g.grade_order', 'cl.class_name', 'cl.class_order')->get()
            ->groupBy(fn ($row) => $row->campus_id.':'.($row->grade_id ?: 0).':'.($row->class_id ?: 0))
            ->map(function ($items) use ($month) {
                $row = clone $items->first();
                $row->monthly_dropout = $items->filter(fn ($event) => substr($event->withdrawal_date, 0, 7) === $month->format('Y-m'))->unique('student_id')->count();
                $row->cumulative_dropout = $items->unique('student_id')->count();
                return $row;
            });
    }
}
