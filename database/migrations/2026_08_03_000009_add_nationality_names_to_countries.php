<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_country', function (Blueprint $table) {
            $table->string('nationality_name_en', 100)->nullable()->after('country_name_kh');
            $table->string('nationality_name_kh', 100)->nullable()->after('nationality_name_en');
        });
    }

    public function down(): void
    {
        Schema::table('tb_country', function (Blueprint $table) {
            $table->dropColumn(['nationality_name_en', 'nationality_name_kh']);
        });
    }
};
