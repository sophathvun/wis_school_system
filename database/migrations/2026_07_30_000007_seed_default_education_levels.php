<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['level_name' => 'Kindergarten', 'level_short_name' => 'KG', 'level_order' => '1'],
            ['level_name' => 'Primary', 'level_short_name' => 'PRI', 'level_order' => '2'],
            ['level_name' => 'Secondary', 'level_short_name' => 'SEC', 'level_order' => '3'],
        ] as $level) {
            DB::table('tb_education_level')->updateOrInsert(
                ['level_short_name' => $level['level_short_name']],
                $level + ['status' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('tb_education_level')->whereIn('level_short_name', ['KG', 'PRI', 'SEC'])->delete();
    }
};
