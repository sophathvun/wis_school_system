<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('dashboard_widgets')->updateOrInsert(
            ['code' => 'staff_birthdays'],
            [
                'name' => 'Staff Birthdays',
                'type' => 'list',
                'icon' => 'ti-cake',
                'color' => 'pink',
                'description' => 'Upcoming staff birthdays.',
                'permission_code' => 'users.view',
                'status' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('dashboard_widgets')->where('code', 'staff_birthdays')->delete();
    }
};
