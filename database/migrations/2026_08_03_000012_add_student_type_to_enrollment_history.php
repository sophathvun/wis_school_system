<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_student_enrollment', function (Blueprint $table) {
            $table->string('student_type', 20)->default('new')->after('enrollment_status');
            $table->index(['academic_year_id', 'student_type'], 'enrollment_student_type_index');
        });

        Schema::table('tb_student_enrollment_history', function (Blueprint $table) {
            $table->string('student_type', 20)->nullable()->after('enrollment_status');
        });
    }

    public function down(): void
    {
        Schema::table('tb_student_enrollment_history', function (Blueprint $table) {
            $table->dropColumn('student_type');
        });
        Schema::table('tb_student_enrollment', function (Blueprint $table) {
            $table->dropIndex('enrollment_student_type_index');
            $table->dropColumn('student_type');
        });
    }
};
