<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the original values before removing the legacy name columns.
        if (! Schema::hasTable('tb_family_member_name_legacy_backup')) {
            Schema::create('tb_family_member_name_legacy_backup', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('family_member_record_id');
                $table->unsignedBigInteger('family_id')->nullable();
                $table->string('relationship_type', 30)->nullable();
                $table->string('full_name_en_before', 160)->nullable();
                $table->string('full_name_kh_before', 160)->nullable();
                $table->string('first_name_en', 160)->nullable();
                $table->string('last_name_en', 160)->nullable();
                $table->string('first_name_kh', 160)->nullable();
                $table->string('last_name_kh', 160)->nullable();
                $table->timestamp('backed_up_at')->useCurrent();
            });
        }

        // Explicit short names avoid MySQL's 64-character identifier limit.
        try {
            Schema::table('tb_family_member_name_legacy_backup', function (Blueprint $table) {
                $table->index('family_member_record_id', 'fam_member_legacy_record_idx');
                $table->index('family_id', 'fam_member_legacy_family_idx');
            });
        } catch (\Throwable) {
            // The indexes may already exist when retrying after a partial migration.
        }

        DB::statement("INSERT INTO tb_family_member_name_legacy_backup
            (family_member_record_id, family_id, relationship_type, full_name_en_before, full_name_kh_before, first_name_en, last_name_en, first_name_kh, last_name_kh)
            SELECT m.id, m.family_id, m.relationship_type, m.full_name_en, m.full_name_kh, m.first_name_en, m.last_name_en, m.first_name_kh, m.last_name_kh
            FROM tb_family_member m
            WHERE NOT EXISTS (SELECT 1 FROM tb_family_member_name_legacy_backup b WHERE b.family_member_record_id = m.id)");

        DB::statement("UPDATE tb_family_member SET
            full_name_en = COALESCE(NULLIF(TRIM(full_name_en), ''), NULLIF(TRIM(name_en), ''), NULLIF(TRIM(CONCAT_WS(' ', first_name_en, last_name_en)), '')),
            full_name_kh = COALESCE(NULLIF(TRIM(full_name_kh), ''), NULLIF(TRIM(name_kh), ''), NULLIF(TRIM(CONCAT_WS(' ', first_name_kh, last_name_kh)), ''))");

        Schema::table('tb_family_member', function (Blueprint $table) {
            $table->dropColumn(['first_name_en', 'last_name_en', 'first_name_kh', 'last_name_kh']);
        });
    }

    public function down(): void
    {
        Schema::table('tb_family_member', function (Blueprint $table) {
            $table->string('first_name_en', 160)->nullable();
            $table->string('last_name_en', 160)->nullable();
            $table->string('first_name_kh', 160)->nullable();
            $table->string('last_name_kh', 160)->nullable();
        });

        DB::statement("UPDATE tb_family_member m
            INNER JOIN tb_family_member_name_legacy_backup b ON b.family_member_record_id = m.id
            SET m.first_name_en = b.first_name_en, m.last_name_en = b.last_name_en,
                m.first_name_kh = b.first_name_kh, m.last_name_kh = b.last_name_kh");
    }
};
