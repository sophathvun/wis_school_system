<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('access_permissions')->updateOrInsert(
            ['code' => 'pr-request.view'],
            ['module' => 'pr-request', 'action' => 'view', 'name' => 'View PR Request menu', 'created_at' => now(), 'updated_at' => now()]
        );

        $roleId = DB::table('access_roles')->where('code', 'super-admin')->value('id');
        if ($roleId) {
            $permissionId = DB::table('access_permissions')->where('code', 'pr-request.view')->value('id');
            DB::table('access_role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        $ids = DB::table('access_permissions')->where('code', 'pr-request.view')->pluck('id');
        foreach (['access_role_permissions', 'access_department_permissions', 'access_user_permission_overrides'] as $table) {
            DB::table($table)->whereIn('permission_id', $ids)->delete();
        }
        DB::table('access_permissions')->whereIn('id', $ids)->delete();
    }
};
