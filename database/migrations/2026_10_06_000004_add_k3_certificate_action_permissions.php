<?php

use App\Support\K3CertificatePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (K3CertificatePermissions::catalog() as $code => $name) {
            DB::table('access_permissions')->updateOrInsert(['code' => $code], [
                'module' => 'reports.k3', 'action' => substr($code, strlen('reports.k3.')),
                'name' => $name, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $superAdmin = DB::table('access_roles')->where('code', 'super-admin')->value('id');
        if ($superAdmin) {
            foreach (DB::table('access_permissions')->whereIn('code', array_keys(K3CertificatePermissions::catalog()))->pluck('id') as $permissionId) {
                DB::table('access_role_permissions')->updateOrInsert(['role_id' => $superAdmin, 'permission_id' => $permissionId], ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('access_permissions')->whereIn('code', array_keys(K3CertificatePermissions::catalog()))->delete();
    }
};
