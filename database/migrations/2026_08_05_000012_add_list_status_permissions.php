<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $items = [
            ['users', 'Activate / Deactivate Users'], ['departments', 'Activate / Deactivate Departments'], ['roles', 'Activate / Deactivate Roles'],
            ['academic-years', 'Activate / Deactivate Academic Years'], ['grades', 'Activate / Deactivate Grades'], ['classes', 'Activate / Deactivate Classes'],
            ['sessions', 'Activate / Deactivate Sessions'], ['education-levels', 'Activate / Deactivate Education Levels'], ['programs', 'Activate / Deactivate Programs'],
            ['school-info', 'Activate / Deactivate School Information'], ['locations', 'Activate / Deactivate Locations'], ['occupations', 'Activate / Deactivate Occupations'],
            ['families', 'Activate / Deactivate Family Management'], ['withdrawal-reasons', 'Activate / Deactivate Withdrawal Reasons'],
            ['student-document-types', 'Activate / Deactivate Document Types'],
        ];

        foreach ($items as [$module, $name]) {
            DB::table('access_permissions')->updateOrInsert(
                ['code' => "{$module}.status"],
                ['module' => $module, 'action' => 'status', 'name' => $name, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('access_permissions')->where('action', 'status')->whereIn('module', [
            'users', 'departments', 'roles', 'academic-years', 'grades', 'classes', 'sessions', 'education-levels', 'programs', 'school-info', 'locations', 'occupations', 'families', 'withdrawal-reasons', 'student-document-types',
        ])->delete();
    }
};
