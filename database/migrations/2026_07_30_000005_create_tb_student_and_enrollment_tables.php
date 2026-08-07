<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_student', function (Blueprint $table) {
            $table->id();
            $table->string('student_no', 30)->unique();
            $table->string('first_name_en', 50);
            $table->string('last_name_en', 50);
            $table->string('first_name_kh', 50)->nullable();
            $table->string('last_name_kh', 50)->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('tb_student_enrollment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('tb_student')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('tb_academic_year')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('tb_grade')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('tb_class')->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('tb_group')->nullOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('tb_session')->nullOnDelete();
            $table->tinyInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['student_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_student_enrollment');
        Schema::dropIfExists('tb_student');
    }
};
