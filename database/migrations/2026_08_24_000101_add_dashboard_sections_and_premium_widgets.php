<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('dashboard_templates', 'settings')) {
                $table->json('settings')->nullable()->after('status');
            }
        });

        $now = now();
        foreach ($this->widgets() as $widget) {
            DB::table('dashboard_widgets')->updateOrInsert(
                ['code' => $widget['code']],
                $widget + ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('dashboard_widgets')->whereIn('code', collect($this->widgets())->pluck('code')->all())->delete();

        Schema::table('dashboard_templates', function (Blueprint $table) {
            if (Schema::hasColumn('dashboard_templates', 'settings')) {
                $table->dropColumn('settings');
            }
        });
    }

    private function widgets(): array
    {
        return [
            ['name' => 'Dashboard Filters', 'code' => 'dashboard_filters', 'type' => 'filter', 'icon' => 'ti-filter', 'color' => 'indigo', 'description' => 'Academic year and campus filters.', 'permission_code' => 'dashboard.view', 'status' => true],
            ['name' => 'New Students', 'code' => 'new_students', 'type' => 'stat_card', 'icon' => 'ti-user-star', 'color' => 'cyan', 'description' => 'New students in selected scope.', 'permission_code' => 'students.search.view', 'status' => true],
            ['name' => 'Student Status Chart', 'code' => 'student_status_chart', 'type' => 'chart', 'icon' => 'ti-chart-donut', 'color' => 'purple', 'description' => 'Active, withdrawn and completed student chart.', 'permission_code' => 'students.enrollment.view', 'status' => true],
            ['name' => 'Enrollment Trend Chart', 'code' => 'enrollment_trend_chart', 'type' => 'chart', 'icon' => 'ti-chart-line', 'color' => 'teal', 'description' => 'Monthly enrollment trend chart.', 'permission_code' => 'students.enrollment.view', 'status' => true],
        ];
    }
};
