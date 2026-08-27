<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('dashboard_widgets')->updateOrInsert(
            ['code' => 'students_by_grade_chart'],
            [
                'name' => 'Students by Grade',
                'type' => 'chart',
                'icon' => 'ti-chart-bar',
                'color' => 'indigo',
                'description' => 'Total students by grade with grand total.',
                'permission_code' => 'students.enrollment.view',
                'status' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('dashboard_widgets')->where('code', 'students_by_grade_chart')->delete();
    }
};
