<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('access_permissions')->updateOrInsert(
            ['code' => 'student-documents.preview'],
            [
                'module' => 'student-documents',
                'action' => 'preview',
                'name' => 'View Student Document Files',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $superAdminRoleId = DB::table('access_roles')
            ->where(function ($query) {
                $query->where('name', 'Super Administrator')
                    ->orWhere('name', 'Super Admin')
                    ->orWhere('code', 'super-administrator')
                    ->orWhere('code', 'super-admin');
            })
            ->value('id');

        $permissionId = DB::table('access_permissions')
            ->where('code', 'student-documents.preview')
            ->value('id');

        if ($superAdminRoleId && $permissionId) {
            DB::table('access_role_permissions')->updateOrInsert(
                ['role_id' => $superAdminRoleId, 'permission_id' => $permissionId],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('access_permissions')
            ->where('code', 'student-documents.preview')
            ->value('id');

        if ($permissionId) {
            DB::table('access_role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('access_department_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('access_user_permission_overrides')->where('permission_id', $permissionId)->delete();
        }

        DB::table('access_permissions')->where('code', 'student-documents.preview')->delete();
    }
};
