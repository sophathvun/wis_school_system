<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        \App\Support\G12NationalExamPermissions::registerMissing();

        $scoringId = DB::table('access_permissions')
            ->where('code', 'g12-national-exams.scoring')
            ->value('id');
        $manageId = DB::table('access_permissions')
            ->where('code', 'g12-national-exams.scoring-manage')
            ->value('id');

        if (! $scoringId || ! $manageId) {
            return;
        }

        // Existing Scoring users were able to work in this section before the
        // dedicated score-entry permission was introduced. Preserve that access.
        foreach (['access_role_permissions' => 'role_id', 'access_department_permissions' => 'department_id', 'access_user_permission_overrides' => 'user_id'] as $table => $owner) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $owners = DB::table($table)
                ->where('permission_id', $scoringId)
                ->when($owner === 'user_id', fn ($query) => $query->where('allowed', true))
                ->pluck($owner);

            foreach ($owners as $ownerId) {
                DB::table($table)->insertOrIgnore([
                    $owner => $ownerId,
                    'permission_id' => $manageId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ] + ($owner === 'user_id' ? ['allowed' => true] : []));
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('access_permissions')) {
            return;
        }

        $permissionId = DB::table('access_permissions')
            ->where('code', 'g12-national-exams.scoring-manage')
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
