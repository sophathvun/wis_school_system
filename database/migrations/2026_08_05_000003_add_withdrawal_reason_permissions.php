<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $permissions = [
            ['withdrawal-reasons.view', 'Withdrawal Reasons', 'view'],
            ['withdrawal-reasons.create', 'Create Withdrawal Reason', 'create'],
            ['withdrawal-reasons.update', 'Update Withdrawal Reason', 'update'],
            ['withdrawal-reasons.delete', 'Deactivate Withdrawal Reason', 'delete'],
        ];
        foreach ($permissions as [$code, $name, $action]) {
            DB::table('access_permissions')->updateOrInsert(['code' => $code], ['module' => 'withdrawal-reasons', 'action' => $action, 'name' => $name, 'updated_at' => now(), 'created_at' => now()]);
        }
    }

    public function down(): void { DB::table('access_permissions')->where('module', 'withdrawal-reasons')->delete(); }
};
