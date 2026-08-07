<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('tb_country')->where('country_code', 'kh')->update(['flag_path' => 'flags/cambodia.png']);
    }

    public function down(): void
    {
        DB::table('tb_country')->where('country_code', 'kh')->update(['flag_path' => 'flags/cambodia.svg']);
    }
};
