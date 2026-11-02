<?php

use App\Support\G12NationalExamPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        G12NationalExamPermissions::registerMissing();
        $resultId = DB::table('access_permissions')->where('code', 'g12-national-exams.results')->value('id');
        $exportId = DB::table('access_permissions')->where('code', 'g12-national-exams.results-export')->value('id');
        $now = now();

        foreach (['access_role_permissions' => 'role_id', 'access_department_permissions' => 'department_id', 'access_user_permission_overrides' => 'user_id'] as $table => $owner) {
            $query = DB::table($table)->where('permission_id', $resultId);
            if ($owner === 'user_id') $query->where('allowed', true);
            foreach ($query->pluck($owner) as $ownerId) {
                DB::table($table)->insertOrIgnore([
                    $owner => $ownerId, 'permission_id' => $exportId,
                    'created_at' => $now, 'updated_at' => $now,
                ] + ($owner === 'user_id' ? ['allowed' => true] : []));
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('access_permissions')->where('code', 'g12-national-exams.results-export')->value('id');
        if (!$id) return;
        foreach (['access_role_permissions', 'access_department_permissions', 'access_user_permission_overrides'] as $table) DB::table($table)->where('permission_id', $id)->delete();
        DB::table('access_permissions')->where('id', $id)->delete();
    }
};
