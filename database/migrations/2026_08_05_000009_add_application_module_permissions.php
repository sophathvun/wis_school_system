<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $modules = [
            'users' => 'User Management',
            'departments' => 'Department Management',
            'roles' => 'Role Management',
            'notifications' => 'Notifications',
            'chat' => 'Communication Chat',
            'academic-years' => 'Academic Years',
            'grades' => 'Grades',
            'classes' => 'Classes',
            'sessions' => 'Sessions',
            'education-levels' => 'Education Levels',
            'programs' => 'Programs',
            'school-info' => 'School Information',
            'locations' => 'Locations',
            'occupations' => 'Occupations',
            'withdrawal-reasons' => 'Withdrawal Reasons',
            'student-document-types' => 'Student Document Types',
            'student-documents' => 'Student Documents',
            'student-reentry' => 'Student Re-entry',
            'student-data-transfer' => 'Student Data Transfer',
            'branding' => 'Branding',
        ];

        foreach ($modules as $module => $label) {
            foreach (['view' => "View {$label}", 'manage' => "Manage {$label}"] as $action => $name) {
                DB::table('access_permissions')->updateOrInsert(
                    ['code' => "{$module}.{$action}"],
                    ['module' => $module, 'action' => $action, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        $modules = ['users', 'departments', 'roles', 'notifications', 'chat', 'academic-years', 'grades', 'classes', 'sessions', 'education-levels', 'programs', 'school-info', 'locations', 'occupations', 'withdrawal-reasons', 'student-document-types', 'student-documents', 'student-reentry', 'student-data-transfer', 'branding'];
        DB::table('access_permissions')->whereIn('code', collect($modules)->flatMap(fn ($module) => ["{$module}.view", "{$module}.manage"])->all())->delete();
    }
};
