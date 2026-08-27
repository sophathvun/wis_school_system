<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $widgetId = DB::table('dashboard_widgets')
            ->where('code', 'staff_profiles')
            ->value('id');

        if ($widgetId) {
            DB::table('dashboard_template_widgets')
                ->where('dashboard_widget_id', $widgetId)
                ->delete();
        }

        DB::table('dashboard_widgets')
            ->where('code', 'staff_profiles')
            ->delete();
    }

    public function down(): void
    {
        DB::table('dashboard_widgets')->updateOrInsert(
            ['code' => 'staff_profiles'],
            [
                'name' => 'Staff Profiles',
                'type' => 'profile',
                'icon' => 'ti-id-badge-2',
                'color' => 'violet',
                'description' => 'Premium staff profile cards with photos.',
                'permission_code' => 'users.view',
                'status' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
};
