<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_student', function (Blueprint $table) {
            foreach (['village', 'commune', 'district', 'province'] as $level) {
                foreach (['en', 'kh'] as $language) $table->string("birth_{$level}_{$language}", 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tb_student', function (Blueprint $table) {
            foreach (['village', 'commune', 'district', 'province'] as $level) {
                foreach (['en', 'kh'] as $language) $table->dropColumn("birth_{$level}_{$language}");
            }
        });
    }
};
