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

        $permissionId = DB::table('access_permissions')
            ->where('code', 'g12-national-exams.scoring')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        foreach (['access_role_permissions', 'access_department_permissions', 'access_user_permission_overrides'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('permission_id', $permissionId)->delete();
            }
        }

        DB::table('access_permissions')->where('id', $permissionId)->delete();
    }
};
