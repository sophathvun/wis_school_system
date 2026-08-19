<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $finishedYearIds = DB::table('tb_academic_year')
            ->where('lifecycle_status', 'finished')
            ->pluck('id');

        $startedYearIds = DB::table('tb_academic_year')
            ->where('lifecycle_status', 'started')
            ->pluck('id');

        if ($finishedYearIds->isNotEmpty()) {
            DB::table('tb_student_enrollment')
                ->whereIn('academic_year_id', $finishedYearIds)
                ->update(['enrollment_status' => 'completed']);
        }

        if ($startedYearIds->isNotEmpty()) {
            DB::table('tb_student_enrollment')
                ->whereIn('academic_year_id', $startedYearIds)
                ->update(['enrollment_status' => 'active']);
        }
    }

    public function down(): void
    {
        // Status values are normalized from the academic-year lifecycle and cannot be safely restored.
    }
};
