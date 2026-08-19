<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep an auditable recovery snapshot before removing the legacy columns.
        if (! Schema::hasTable('tb_student_name_legacy_backup')) {
            Schema::create('tb_student_name_legacy_backup', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('student_record_id')->index();
                $table->string('student_id')->nullable()->index();
                $table->string('full_name_en_before', 160)->nullable();
                $table->string('full_name_kh_before', 160)->nullable();
                $table->string('first_name_en', 160)->nullable();
                $table->string('last_name_en', 160)->nullable();
                $table->string('first_name_kh', 160)->nullable();
                $table->string('last_name_kh', 160)->nullable();
                $table->timestamp('backed_up_at')->useCurrent();
            });
        }

        DB::statement("INSERT INTO tb_student_name_legacy_backup
            (student_record_id, student_id, full_name_en_before, full_name_kh_before, first_name_en, last_name_en, first_name_kh, last_name_kh)
            SELECT s.id, s.student_id, s.full_name_en, s.full_name_kh, s.first_name_en, s.last_name_en, s.first_name_kh, s.last_name_kh
            FROM tb_student s
            WHERE NOT EXISTS (SELECT 1 FROM tb_student_name_legacy_backup b WHERE b.student_record_id = s.id)");

        DB::statement("UPDATE tb_student SET
            full_name_en = COALESCE(NULLIF(TRIM(full_name_en), ''), NULLIF(TRIM(CONCAT_WS(' ', first_name_en, last_name_en)), '')),
            full_name_kh = COALESCE(NULLIF(TRIM(full_name_kh), ''), NULLIF(TRIM(CONCAT_WS(' ', first_name_kh, last_name_kh)), ''))");

        Schema::table('tb_student', function (Blueprint $table) {
            $table->dropColumn(['first_name_en', 'last_name_en', 'first_name_kh', 'last_name_kh']);
        });
    }

    public function down(): void
    {
        Schema::table('tb_student', function (Blueprint $table) {
            $table->string('first_name_en', 160)->nullable();
            $table->string('last_name_en', 160)->nullable();
            $table->string('first_name_kh', 160)->nullable();
            $table->string('last_name_kh', 160)->nullable();
        });

        DB::statement("UPDATE tb_student s
            INNER JOIN tb_student_name_legacy_backup b ON b.student_record_id = s.id
            SET s.first_name_en = b.first_name_en, s.last_name_en = b.last_name_en,
                s.first_name_kh = b.first_name_kh, s.last_name_kh = b.last_name_kh");
    }
};
