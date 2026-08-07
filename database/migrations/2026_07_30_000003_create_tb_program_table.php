<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recreate the table if a previous failed migration left a partial table.
        Schema::dropIfExists('tb_program');
        Schema::create('tb_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->nullable()->constrained('tb_academic_year')->nullOnDelete();
            $table->foreignId('education_level_id')->constrained('tb_education_level')->cascadeOnDelete();
            $table->string('program_name', 100);
            $table->string('program_code', 30);
            $table->string('description', 100)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['academic_year_id', 'education_level_id', 'program_name'], 'program_year_level_name_unique');
            $table->unique(['academic_year_id', 'education_level_id', 'program_code'], 'program_year_level_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_program');
    }
};
