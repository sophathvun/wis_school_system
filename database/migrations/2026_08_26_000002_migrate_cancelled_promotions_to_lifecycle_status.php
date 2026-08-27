<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tb_student_enrollment')
            ->whereIn('id', function ($query) {
                $query->select('target_enrollment_id')
                    ->from('tb_student_enrollment_workflow')
                    ->where('status', 'cancelled')
                    ->whereNotNull('target_enrollment_id');
            })
            ->where('enrollment_status', 'withdrawn')
            ->where('exit_reason', 'Promotion cancelled')
            ->update(['enrollment_status' => 'promotion_cancelled']);
    }

    public function down(): void
    {
        DB::table('tb_student_enrollment')
            ->where('enrollment_status', 'promotion_cancelled')
            ->where('exit_reason', 'Promotion cancelled')
            ->update(['enrollment_status' => 'withdrawn']);
    }
};
