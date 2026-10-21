<?php

use App\Support\SummerSchoolPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (SummerSchoolPermissions::catalog() as $action => $name) {
            DB::table('access_permissions')->updateOrInsert(['code' => 'summer-school.'.$action], [
                'module' => 'summer-school', 'action' => $action, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // Preserve existing viewers and managers; deleting requires a separate grant.
        foreach (['access_role_permissions' => 'role_id', 'access_department_permissions' => 'department_id',
            'access_user_permission_overrides' => 'user_id'] as $table => $owner) {
            foreach (['view' => ['filter'], 'manage' => ['create', 'update', 'convert']] as $oldAction => $actions) {
                $oldId = DB::table('access_permissions')->where('code', 'summer-school.'.$oldAction)->value('id');
                if (!$oldId) continue;
                $owners = DB::table($table)->where('permission_id', $oldId)
                    ->when($owner === 'user_id', fn ($q) => $q->where('allowed', true))->pluck($owner);
                foreach ($owners as $ownerId) foreach ($actions as $action) {
                    $id = DB::table('access_permissions')->where('code', 'summer-school.'.$action)->value('id');
                    DB::table($table)->insertOrIgnore([$owner => $ownerId, 'permission_id' => $id,
                        'created_at' => now(), 'updated_at' => now()] + ($owner === 'user_id' ? ['allowed' => true] : []));
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('access_permissions')->where('module', 'summer-school')->whereIn('action', ['filter','create','update','delete','convert'])->delete();
    }
};
