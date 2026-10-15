<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach (\App\Support\ScholarshipPermissions::catalog() as $action => $name) {
            DB::table('access_permissions')->updateOrInsert(['code' => 'western-scholarship.'.$action], [
                'module' => 'western-scholarship', 'action' => $action, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $permission = DB::table('access_permissions')->where('code', 'western-scholarship.'.$action)->value('id');
            $role = DB::table('access_roles')->where('code', 'super-admin')->value('id');
            if ($role) DB::table('access_role_permissions')->updateOrInsert(['role_id' => $role, 'permission_id' => $permission], ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('access_permissions')->where('module', 'western-scholarship')->pluck('id');
        foreach (['access_role_permissions', 'access_department_permissions', 'access_user_permission_overrides'] as $table) DB::table($table)->whereIn('permission_id', $ids)->delete();
        DB::table('access_permissions')->whereIn('id', $ids)->delete();
    }
};
