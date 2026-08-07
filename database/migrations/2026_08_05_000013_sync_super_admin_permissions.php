<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $roleId = DB::table('access_roles')->where('code', 'super-admin')->value('id');
        if (!$roleId) return;

        foreach (DB::table('access_permissions')->pluck('id') as $permissionId) {
            DB::table('access_role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        // Super Admin permissions must not be removed during rollback.
    }
};
