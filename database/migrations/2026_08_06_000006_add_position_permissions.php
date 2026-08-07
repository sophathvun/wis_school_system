<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['view' => 'View Position Management', 'manage' => 'Manage Position Management'] as $action => $name) {
            DB::table('access_permissions')->updateOrInsert(
                ['code' => "positions.{$action}"],
                ['module' => 'positions', 'action' => $action, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('access_permissions')->whereIn('code', ['positions.view', 'positions.manage'])->delete();
    }
};
