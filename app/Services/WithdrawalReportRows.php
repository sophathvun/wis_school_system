<?php

namespace App\Services;

use App\Models\StudentEnrollmentHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{DB, Schema};

class WithdrawalReportRows
{
    private ?array $columns = null;

    public function query(): Builder
    {
        // Present legacy enrollment withdrawals in the read-only report without
        // fabricating persisted workflow requests, approvers or approval dates.
        $columns = $this->columns ??= Schema::getColumnListing('tb_student_enrollment_history');
        $mapping = [
            'id' => '-e.id', 'enrollment_id' => 'e.id',
            'action_type' => "'withdrawal'", 'withdrawal_status' => "'approved'",
            'enrollment_status' => 'e.enrollment_status', 'effective_on' => 'e.ended_on',
            'reason' => 'e.exit_reason', 'notes' => 'e.notes',
        ];
        foreach (['student_id', 'campus_id', 'academic_year_id', 'grade_id', 'class_id', 'academic_track_id', 'session_id', 'student_type'] as $column) {
            $mapping[$column] = 'e.'.$column;
        }
        $legacy = DB::table('tb_student_enrollment as e')->where('e.enrollment_status', 'withdrawn')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('tb_student_enrollment_history as h')
                ->whereColumn('h.enrollment_id', 'e.id')->where('h.action_type', 'withdrawal')
                ->where('h.withdrawal_status', 'approved')->where('h.enrollment_status', 'withdrawn'))
            ->select(array_map(fn ($column) => DB::raw(($mapping[$column] ?? 'NULL').' as '.$column), $columns));
        $history = DB::table('tb_student_enrollment_history')->select($columns)->unionAll($legacy);
        return StudentEnrollmentHistory::query()->fromSub($history, 'tb_student_enrollment_history');
    }
}
