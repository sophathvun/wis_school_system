<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tb_village')->update([
            'village_name_en' => DB::raw("REPLACE(village_name_en, '\"', '')"),
            'village_name_kh' => DB::raw("REPLACE(village_name_kh, '\"', '')"),
        ]);
    }

    public function down(): void
    {
        //
    }
};
