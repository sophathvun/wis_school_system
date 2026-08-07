<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('access_permissions')
            ->whereIn('module', ['terms', 'campuses', 'groups'])
            ->delete();
    }

    public function down(): void
    {
        foreach (['terms' => 'Terms and Quarters', 'campuses' => 'Campuses', 'groups' => 'School Groups'] as $module => $label) {
            foreach (['view' => "View {$label}", 'manage' => "Manage {$label}"] as $action => $name) {
                DB::table('access_permissions')->updateOrInsert(
                    ['code' => "{$module}.{$action}"],
                    ['module' => $module, 'action' => $action, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }
};
