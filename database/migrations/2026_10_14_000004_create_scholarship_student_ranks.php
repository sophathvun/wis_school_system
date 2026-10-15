<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_scholarship_student_rank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->unique()->constrained('tb_student_enrollment')->cascadeOnDelete();
            $table->unsignedInteger('rank');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('tb_scholarship_student_rank'); }
};
