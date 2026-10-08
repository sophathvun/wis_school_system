<?php

use App\Support\AcademicReportPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (AcademicReportPermissions::catalog() as $permission) {
            DB::table('access_permissions')->updateOrInsert(['code' => $permission['code']], $permission + ['created_at' => $now, 'updated_at' => $now]);
        }
        $ids = DB::table('access_permissions')->whereIn('code', array_column(AcademicReportPermissions::catalog(), 'code'))->pluck('id');
        $reportsId = DB::table('access_permissions')->where('code', 'reports.view')->value('id');

        // Existing Reports grants already allowed every tab. Preserve that access during the upgrade.
        foreach (['access_role_permissions' => 'role_id', 'access_department_permissions' => 'department_id', 'access_user_permission_overrides' => 'user_id'] as $table => $owner) {
            $query = DB::table($table)->where('permission_id', $reportsId);
            if ($owner === 'user_id') $query->where('allowed', true);
            $owners = $query->pluck($owner);
            if ($owner === 'role_id') {
                $owners = $owners->merge(DB::table('access_roles')->where('code', 'super-admin')->pluck('id'))->unique();
            }
            foreach ($owners as $ownerId) {
                foreach ($ids as $permissionId) {
                    DB::table($table)->insertOrIgnore([
                        $owner => $ownerId, 'permission_id' => $permissionId,
                        'created_at' => $now, 'updated_at' => $now,
                    ] + ($owner === 'user_id' ? ['allowed' => true] : []));
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('access_permissions')->whereIn('code', array_column(AcademicReportPermissions::catalog(), 'code'))->pluck('id');
        foreach (['access_role_permissions', 'access_department_permissions', 'access_user_permission_overrides'] as $table) {
            DB::table($table)->whereIn('permission_id', $ids)->delete();
        }
        DB::table('access_permissions')->whereIn('id', $ids)->delete();
    }
};
