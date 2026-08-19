<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_student_enrollment', function (Blueprint $table) {
            $table->index(
                ['academic_year_id', 'status', 'enrollment_status'],
                'enrollment_year_status_lookup'
            );
            $table->index(
                ['academic_year_id', 'campus_id', 'grade_id', 'class_id', 'status', 'enrollment_status'],
                'enrollment_class_status_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tb_student_enrollment', function (Blueprint $table) {
            $table->dropIndex('enrollment_year_status_lookup');
            $table->dropIndex('enrollment_class_status_lookup');
        });
    }
};
