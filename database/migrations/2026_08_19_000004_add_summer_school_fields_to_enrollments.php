<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_student_enrollment', function (Blueprint $table) {
            $table->string('enrollment_origin', 20)->default('internal')->after('student_type');
            $table->string('external_student_id', 60)->nullable()->after('enrollment_origin');
            $table->string('previous_school', 180)->nullable()->after('external_student_id');
            $table->string('continue_at_western', 20)->nullable()->after('previous_school');
            $table->text('summer_remarks')->nullable()->after('continue_at_western');
            $table->index(['academic_year_id', 'enrollment_origin'], 'summer_enrollment_origin_index');
        });
    }

    public function down(): void
    {
        Schema::table('tb_student_enrollment', function (Blueprint $table) {
            $table->dropIndex('summer_enrollment_origin_index');
            $table->dropColumn(['enrollment_origin', 'external_student_id', 'previous_school', 'continue_at_western', 'summer_remarks']);
        });
    }
};
