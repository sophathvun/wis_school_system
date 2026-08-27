<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        DB::table('dashboard_widgets')->updateOrInsert(
            ['code' => 'dashboard_hero'],
            [
                'name' => 'Dashboard Hero',
                'type' => 'hero',
                'icon' => 'ti-id-badge-2',
                'color' => 'blue',
                'description' => 'Show your staff profile as a full-width dashboard hero.',
                'permission_code' => 'users.view',
                'status' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $widgetId = DB::table('dashboard_widgets')->where('code', 'dashboard_hero')->value('id');
        DB::table('dashboard_templates')->get(['id'])->each(function ($template) use ($widgetId, $now) {
            $exists = DB::table('dashboard_template_widgets')
                ->where('dashboard_template_id', $template->id)
                ->where('dashboard_widget_id', $widgetId)
                ->exists();

            if (!$exists) {
                DB::table('dashboard_template_widgets')->insert([
                    'dashboard_template_id' => $template->id,
                    'dashboard_widget_id' => $widgetId,
                    'display_order' => 0,
                    'width' => 'full',
                    'status' => true,
                    'settings' => json_encode(['section_id' => 'section-1']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        $widgetId = DB::table('dashboard_widgets')->where('code', 'dashboard_hero')->value('id');
        if ($widgetId) {
            DB::table('dashboard_template_widgets')->where('dashboard_widget_id', $widgetId)->delete();
        }
        DB::table('dashboard_widgets')->where('code', 'dashboard_hero')->delete();
    }
};
