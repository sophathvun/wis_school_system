<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_student_enrollment_workflow', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('tb_student')->cascadeOnDelete();
            $table->foreignId('source_enrollment_id')->constrained('tb_student_enrollment')->cascadeOnDelete();
            $table->foreignId('target_enrollment_id')->nullable()->constrained('tb_student_enrollment')->nullOnDelete();
            $table->string('action_type', 20);
            $table->foreignId('from_campus_id')->nullable()->constrained('tb_school_info')->nullOnDelete();
            $table->foreignId('to_campus_id')->nullable()->constrained('tb_school_info')->nullOnDelete();
            $table->foreignId('from_academic_year_id')->nullable()->constrained('tb_academic_year')->nullOnDelete();
            $table->foreignId('to_academic_year_id')->nullable()->constrained('tb_academic_year')->nullOnDelete();
            $table->foreignId('from_grade_id')->nullable()->constrained('tb_grade')->nullOnDelete();
            $table->foreignId('to_grade_id')->nullable()->constrained('tb_grade')->nullOnDelete();
            $table->foreignId('from_class_id')->nullable()->constrained('tb_class')->nullOnDelete();
            $table->foreignId('to_class_id')->nullable()->constrained('tb_class')->nullOnDelete();
            $table->foreignId('from_session_id')->nullable()->constrained('tb_session')->nullOnDelete();
            $table->foreignId('to_session_id')->nullable()->constrained('tb_session')->nullOnDelete();
            $table->date('effective_on');
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'action_type']);
            $table->index(['effective_on', 'action_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_student_enrollment_workflow');
    }
};
