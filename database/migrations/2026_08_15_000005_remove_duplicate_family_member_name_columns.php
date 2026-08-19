<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tb_family_member_duplicate_name_backup')) {
            Schema::create('tb_family_member_duplicate_name_backup', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('family_member_record_id');
                $table->string('full_name_en_before', 160)->nullable();
                $table->string('full_name_kh_before', 160)->nullable();
                $table->string('name_en', 160)->nullable();
                $table->string('name_kh', 160)->nullable();
                $table->timestamp('backed_up_at')->useCurrent();
                $table->index('family_member_record_id', 'fam_member_duplicate_record_idx');
            });
        }

        DB::statement("INSERT INTO tb_family_member_duplicate_name_backup
            (family_member_record_id, full_name_en_before, full_name_kh_before, name_en, name_kh)
            SELECT m.id, m.full_name_en, m.full_name_kh, m.name_en, m.name_kh
            FROM tb_family_member m
            WHERE NOT EXISTS (SELECT 1 FROM tb_family_member_duplicate_name_backup b WHERE b.family_member_record_id = m.id)");

        DB::statement("UPDATE tb_family_member SET
            full_name_en = COALESCE(NULLIF(TRIM(full_name_en), ''), NULLIF(TRIM(name_en), '')),
            full_name_kh = COALESCE(NULLIF(TRIM(full_name_kh), ''), NULLIF(TRIM(name_kh), ''))");

        Schema::table('tb_family_member', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'name_kh']);
        });
    }

    public function down(): void
    {
        Schema::table('tb_family_member', function (Blueprint $table) {
            $table->string('name_en', 160)->nullable();
            $table->string('name_kh', 160)->nullable();
        });

        DB::statement("UPDATE tb_family_member m
            INNER JOIN tb_family_member_duplicate_name_backup b ON b.family_member_record_id = m.id
            SET m.name_en = b.name_en, m.name_kh = b.name_kh");
    }
};
