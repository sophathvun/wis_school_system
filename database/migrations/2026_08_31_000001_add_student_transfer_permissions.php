<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['view', 'View Student Transfer'],
            ['manage', 'Manage Student Transfer'],
            ['execute', 'Transfer Students'],
            ['cancel', 'Cancel Student Transfer'],
            ['export', 'Export Transfer Records'],
        ];

        foreach ($permissions as [$action, $name]) {
            DB::table('access_permissions')->updateOrInsert(
                ['code' => "students.transfer.{$action}"],
                ['module' => 'students.transfer', 'action' => $action, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        DB::table('access_permissions')->where('code', 'students.promotion.view')->update(['name' => 'View Student Promotion']);
        DB::table('access_permissions')->where('code', 'students.promotion.execute')->update(['name' => 'Promote Students']);
        DB::table('access_permissions')->where('code', 'students.promotion.cancel')->update(['name' => 'Cancel Student Promotion']);
        DB::table('access_permissions')->where('code', 'students.promotion.export')->update(['name' => 'Export Promotion Records']);
    }

    public function down(): void
    {
        DB::table('access_permissions')->where('module', 'students.transfer')->delete();
    }
};
