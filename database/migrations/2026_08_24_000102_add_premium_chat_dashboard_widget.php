<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('dashboard_widgets')->updateOrInsert(
            ['code' => 'premium_chat'],
            [
                'name' => 'Premium Chat',
                'type' => 'chat',
                'icon' => 'ti-messages',
                'color' => 'blue',
                'description' => 'Quick access to staff chat and unread messages.',
                'permission_code' => 'dashboard.view',
                'status' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('dashboard_widgets')->where('code', 'premium_chat')->delete();
    }
};
