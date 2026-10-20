<?php

use App\Support\StudentEnrollmentPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (StudentEnrollmentPermissions::catalog() as $action => $name) {
            DB::table('access_permissions')->updateOrInsert(['code' => 'students.enrollment.'.$action], [
                'module' => 'students.enrollment', 'action' => $action, 'name' => $name,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // Preserve existing capabilities once, then use the individual permissions.
        // Manage covers create/edit, but never implicitly grants deletion.
        $mapping = [
            'students.enrollment.view' => ['filter', 'profile', 'history', 'print'],
            'students.enrollment.manage' => ['create', 'update'],
        ];
        foreach (['access_role_permissions' => 'role_id', 'access_department_permissions' => 'department_id',
            'access_user_permission_overrides' => 'user_id'] as $table => $owner) {
            foreach ($mapping as $oldCode => $actions) {
                $oldId = DB::table('access_permissions')->where('code', $oldCode)->value('id');
                if (!$oldId) continue;
                $owners = DB::table($table)->where('permission_id', $oldId)
                    ->when($owner === 'user_id', fn ($q) => $q->where('allowed', true))->pluck($owner);
                foreach ($owners as $ownerId) foreach ($actions as $action) {
                    $permissionId = DB::table('access_permissions')->where('code', 'students.enrollment.'.$action)->value('id');
                    DB::table($table)->insertOrIgnore([$owner => $ownerId, 'permission_id' => $permissionId,
                        'created_at' => now(), 'updated_at' => now()] + ($owner === 'user_id' ? ['allowed' => true] : []));
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('access_permissions')->whereIn('code', array_map(fn ($action) => 'students.enrollment.'.$action,
            ['filter', 'profile', 'history', 'print']))->delete();
    }
};
