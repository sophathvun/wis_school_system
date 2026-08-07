<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_student', function (Blueprint $table) {
            $table->string('full_name_en', 160)->nullable()->after('family_number');
            $table->string('full_name_kh', 160)->nullable()->after('full_name_en');
        });

        Schema::table('tb_family_member', function (Blueprint $table) {
            $table->string('full_name_en', 160)->nullable()->after('user_id');
            $table->string('full_name_kh', 160)->nullable()->after('full_name_en');
        });

        DB::statement("UPDATE tb_student SET full_name_en = NULLIF(TRIM(CONCAT_WS(' ', first_name_en, last_name_en)), ''), full_name_kh = NULLIF(TRIM(CONCAT_WS(' ', first_name_kh, last_name_kh)), '')");
        DB::statement("UPDATE tb_family_member SET full_name_en = COALESCE(NULLIF(TRIM(name_en), ''), NULLIF(TRIM(CONCAT_WS(' ', first_name_en, last_name_en)), '')), full_name_kh = COALESCE(NULLIF(TRIM(name_kh), ''), NULLIF(TRIM(CONCAT_WS(' ', first_name_kh, last_name_kh)), ''))");
    }

    public function down(): void
    {
        Schema::table('tb_student', fn (Blueprint $table) => $table->dropColumn(['full_name_en', 'full_name_kh']));
        Schema::table('tb_family_member', fn (Blueprint $table) => $table->dropColumn(['full_name_en', 'full_name_kh']));
    }
};
