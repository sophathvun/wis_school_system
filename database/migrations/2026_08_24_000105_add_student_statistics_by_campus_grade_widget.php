<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('dashboard_widgets')->updateOrInsert(
            ['code' => 'student_statistics_by_campus_grade'],
            [
                'name' => 'Student Statistics by Campus and Grade',
                'type' => 'table',
                'icon' => 'ti-table',
                'color' => 'cyan',
                'description' => 'Campus and grade student totals with female count.',
                'permission_code' => 'students.enrollment.view',
                'status' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('dashboard_widgets')->where('code', 'student_statistics_by_campus_grade')->delete();
    }
};
