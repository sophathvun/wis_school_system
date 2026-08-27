<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
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
        DB::table('dashboard_widgets')
            ->whereIn('code', collect($this->widgets())->pluck('code')->all())
            ->delete();
    }

    private function widgets(): array
    {
        return [
            [
                'name' => 'Graduated Students',
                'code' => 'graduated_students',
                'type' => 'stat_card',
                'icon' => 'ti-school',
                'color' => 'green',
                'description' => 'Graduated students in selected scope.',
                'permission_code' => 'students.graduation.view',
                'status' => true,
            ],
            [
                'name' => 'Students by Track',
                'code' => 'students_by_track',
                'type' => 'chart',
                'icon' => 'ti-route',
                'color' => 'violet',
                'description' => 'Student totals by academic track.',
                'permission_code' => 'students.enrollment.view',
                'status' => true,
            ],
            [
                'name' => 'Classes',
                'code' => 'classes_count',
                'type' => 'stat_card',
                'icon' => 'ti-door',
                'color' => 'azure',
                'description' => 'Active classes in selected scope.',
                'permission_code' => 'classes.view',
                'status' => true,
            ],
        ];
    }
};
