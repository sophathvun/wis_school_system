<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tb_student_enrollment')
            ->whereIn('academic_year_id', DB::table('tb_academic_year')->where('lifecycle_status', 'pending')->pluck('id'))
            ->whereIn('enrollment_status', ['active', 'completed'])
            ->update(['enrollment_status' => 'pending']);
    }

    public function down(): void
    {
        // Pending statuses cannot be safely restored to their previous lifecycle status.
    }
};
