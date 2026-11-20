<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        \App\Support\GepAcademicPermissions::registerMissing();
        $ids = DB::table('access_permissions')->whereIn('code', array_column(\App\Support\GepAcademicPermissions::catalog(), 'code'))->pluck('id');
        foreach (DB::table('access_roles')->whereIn('code', ['super-admin', 'central-office-admin'])->pluck('id') as $role) {
            foreach ($ids as $permission) DB::table('access_role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $permission, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('access_permissions')->whereIn('code', array_column(\App\Support\GepAcademicPermissions::catalog(), 'code'))->pluck('id');
        foreach (['access_role_permissions', 'access_department_permissions', 'access_user_permission_overrides'] as $table) DB::table($table)->whereIn('permission_id', $ids)->delete();
        DB::table('access_permissions')->whereIn('id', $ids)->delete();
    }
};
