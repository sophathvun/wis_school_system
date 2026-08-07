<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_grade', function (Blueprint $table) {
            $table->foreignId('education_level_id')->nullable()->after('id')->constrained('tb_education_level')->nullOnDelete();
        });

        Schema::table('tb_class', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('id')->constrained('tb_academic_year')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->after('academic_year_id')->constrained('tb_grade')->nullOnDelete();
            $table->foreignId('session_id')->nullable()->after('grade_id')->constrained('tb_session')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tb_class', function (Blueprint $table) {
            $table->dropForeign(['session_id']);
            $table->dropForeign(['grade_id']);
            $table->dropForeign(['academic_year_id']);
            $table->dropColumn(['session_id', 'grade_id', 'academic_year_id']);
        });

        Schema::table('tb_grade', function (Blueprint $table) {
            $table->dropForeign(['education_level_id']);
            $table->dropColumn('education_level_id');
        });
    }
};
