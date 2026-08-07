<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_student_graduation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('tb_student')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('tb_student_enrollment')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('tb_academic_year')->restrictOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained('tb_school_info')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('tb_grade')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('tb_class')->nullOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('tb_session')->nullOnDelete();
            $table->date('graduation_date');
            $table->string('certificate_number', 80)->nullable();
            $table->boolean('is_alumni')->default(false);
            $table->string('notes', 500)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('enrollment_id');
            $table->index(['academic_year_id', 'campus_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_student_graduation');
    }
};
