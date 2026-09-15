<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['attendance', 'view', 'View Attendance menu'],
            ['attendance', 'manage', 'Manage Attendance records'],
            ['schedules', 'view', 'View Schedules menu'],
            ['schedules', 'manage', 'Manage Student and Teacher schedules'],
            ['grading-system', 'view', 'View Grading System menu'],
            ['grading-system', 'manage', 'Manage Grading System scores'],
        ];

        foreach ($permissions as [$module, $action, $name]) {
            DB::table('access_permissions')->updateOrInsert(
                ['code' => "{$module}.{$action}"],
                [
                    'module' => $module,
                    'action' => $action,
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $superAdminRoleId = DB::table('access_roles')->where('code', 'super-admin')->value('id');
        if ($superAdminRoleId) {
            $permissionIds = DB::table('access_permissions')
                ->whereIn('code', collect($permissions)->map(fn ($permission) => "{$permission[0]}.{$permission[1]}")->all())
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('access_role_permissions')->updateOrInsert(
                    ['role_id' => $superAdminRoleId, 'permission_id' => $permissionId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('access_permissions')->whereIn('code', [
            'attendance.view',
            'attendance.manage',
            'schedules.view',
            'schedules.manage',
            'grading-system.view',
            'grading-system.manage',
        ])->delete();
    }
};
