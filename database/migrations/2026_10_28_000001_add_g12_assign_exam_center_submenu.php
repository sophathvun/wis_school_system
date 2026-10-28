<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        \App\Support\G12NationalExamPermissions::registerMissing();
    }

    public function down(): void
    {
        if (! Schema::hasTable('access_permissions')) {
            return;
        }

        $permissionIds = DB::table('access_permissions')
            ->whereIn('code', ['g12-national-exams.assign-centers', 'g12-national-exams.assign-centers-manage'])
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        foreach (['access_role_permissions', 'access_department_permissions'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->whereIn('permission_id', $permissionIds)->delete();
            }
        }

        if (Schema::hasTable('access_user_permission_overrides')) {
            DB::table('access_user_permission_overrides')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('access_permissions')->whereIn('id', $permissionIds)->delete();
    }
};
