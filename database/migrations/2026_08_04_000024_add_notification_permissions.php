<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $items = [
            ['notifications', 'view', 'View Notifications'],
            ['notifications', 'read', 'Mark Notifications as Read'],
            ['notifications', 'send', 'Send Notifications'],
            ['notifications', 'update', 'Edit Notifications'],
            ['notifications', 'delete', 'Delete Notifications'],
        ];

        foreach ($items as [$module, $action, $name]) {
            DB::table('access_permissions')->updateOrInsert(
                ['code' => "$module.$action"],
                ['module' => $module, 'action' => $action, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('access_permissions')->where('module', 'notifications')->delete();
    }
};
