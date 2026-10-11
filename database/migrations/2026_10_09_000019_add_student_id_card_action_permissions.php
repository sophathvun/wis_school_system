<?php

use App\Support\StudentIdCardPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (StudentIdCardPermissions::catalog() as $action => $name) {
            $code = StudentIdCardPermissions::MODULE . '.' . $action;
            $existing = DB::table('access_permissions')->where('code', $code)->first();
            if ($existing) {
                DB::table('access_permissions')->where('id', $existing->id)->update(['name' => $name, 'updated_at' => now()]);
            } else {
                DB::table('access_permissions')->insert([
                    'code' => $code, 'module' => StudentIdCardPermissions::MODULE, 'action' => $action,
                    'name' => $name, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        $superAdmin = DB::table('access_roles')->where('code', 'super-admin')->value('id');
        if ($superAdmin) {
            foreach (DB::table('access_permissions')->where('module', StudentIdCardPermissions::MODULE)->pluck('id') as $id) {
                DB::table('access_role_permissions')->updateOrInsert(['role_id' => $superAdmin, 'permission_id' => $id], ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('access_permissions')->whereIn('code', array_map(
            fn ($action) => StudentIdCardPermissions::MODULE . '.' . $action,
            ['generate-qr', 'print-card', 'print-class', 'copy-link']
        ))->delete();
    }
};
