<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $permissions = [
            ['database-backups.view', 'database-backups', 'view', 'View database backups'],
            ['database-backups.create', 'database-backups', 'create', 'Create database backups'],
            ['database-backups.download', 'database-backups', 'download', 'Download database backups'],
            ['database-backups.delete', 'database-backups', 'delete', 'Delete database backups'],
        ];

        foreach ($permissions as [$code, $module, $action, $name]) {
            DB::table('access_permissions')->updateOrInsert(
                ['code' => $code],
                ['module' => $module, 'action' => $action, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('access_permissions')->whereIn('code', [
            'database-backups.view', 'database-backups.create',
            'database-backups.download', 'database-backups.delete',
        ])->delete();
    }
};
