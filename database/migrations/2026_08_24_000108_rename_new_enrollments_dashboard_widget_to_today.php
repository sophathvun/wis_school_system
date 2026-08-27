<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('dashboard_widgets')
            ->where('code', 'new_enrollments')
            ->update([
                'name' => 'New Enrolment Today',
                'icon' => 'ti-calendar-plus',
                'description' => 'Student enrolments created today in selected scope.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('dashboard_widgets')
            ->where('code', 'new_enrollments')
            ->update([
                'name' => 'New Enrollments',
                'icon' => 'ti-user-plus',
                'description' => 'Recent student enrollments.',
                'updated_at' => now(),
            ]);
    }
};
