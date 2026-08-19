<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $withdrawnEnrollmentIds = DB::table('tb_student_enrollment_history')
            ->where('action_type', 'withdrawal')
            ->where('withdrawal_status', 'approved')
            ->where('enrollment_status', 'withdrawn')
            ->pluck('enrollment_id')
            ->filter()
            ->unique()
            ->values();

        if ($withdrawnEnrollmentIds->isNotEmpty()) {
            DB::table('tb_student_enrollment')
                ->whereIn('id', $withdrawnEnrollmentIds)
                ->update(['enrollment_status' => 'withdrawn']);
        }
    }

    public function down(): void
    {
        // Approved withdrawal history remains the source of truth for these records.
    }
};
