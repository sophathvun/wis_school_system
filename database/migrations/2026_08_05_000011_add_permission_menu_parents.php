<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach ([
            ['administrator.view', 'administrator', 'view', 'View Administrator menu'],
            ['communication.view', 'communication', 'view', 'View Communication menu'],
        ] as [$code, $module, $action, $name]) {
            DB::table('access_permissions')->updateOrInsert(
                ['code' => $code],
                ['module' => $module, 'action' => $action, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('access_permissions')->whereIn('code', ['administrator.view', 'communication.view'])->delete();
    }
};
