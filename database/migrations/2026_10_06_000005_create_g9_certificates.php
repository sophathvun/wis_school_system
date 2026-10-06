<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_g9_certificate_year', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->unique()->constrained('tb_academic_year')->restrictOnDelete();
            $table->date('given_date');
            $table->string('number_prefix', 16);
            $table->timestamps();
        });
        Schema::create('tb_g9_certificate', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_year_id')->constrained('tb_g9_certificate_year')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('tb_student')->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained('tb_student_enrollment')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('certificate_number', 32);
            $table->timestamps();
            $table->unique(['certificate_year_id', 'student_id'], 'g9_certificate_student_unique');
            $table->unique(['certificate_year_id', 'sequence'], 'g9_certificate_sequence_unique');
            $table->unique(['certificate_year_id', 'certificate_number'], 'g9_certificate_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_g9_certificate');
        Schema::dropIfExists('tb_g9_certificate_year');
    }
};
