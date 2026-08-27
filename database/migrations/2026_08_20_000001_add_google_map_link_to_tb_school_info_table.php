<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_school_info', function (Blueprint $table) {
            $table->text('google_map_url')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('tb_school_info', function (Blueprint $table) {
            $table->dropColumn('google_map_url');
        });
    }
};
