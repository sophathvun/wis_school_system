<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A subject can cover several grades; each grade needs its own paper and versions.
        // No papers or answer keys are created until the answer-sheet module is implemented.
        Schema::create('tb_scholarship_paper', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('tb_scholarship_subject')->restrictOnDelete();
            $table->unsignedTinyInteger('grade_number');
            $table->string('paper_version', 20)->default('A');
            $table->unsignedSmallInteger('question_count')->nullable();
            $table->json('answer_choices')->nullable();
            $table->decimal('points_per_question', 10, 4)->nullable();
            $table->timestamps();
            $table->unique(['subject_id','grade_number','paper_version'], 'scholarship_paper_grade_version_unique');
        });
        Schema::table('tb_scholarship_score', function (Blueprint $table) {
            $table->string('entry_method', 20)->default('manual');
            $table->foreignId('source_paper_id')->nullable()->constrained('tb_scholarship_paper')->restrictOnDelete();
            $table->string('source_reference', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_scholarship_score', function (Blueprint $table) {
            $table->dropForeign(['source_paper_id']);
            $table->dropColumn(['entry_method','source_paper_id','source_reference']);
        });
        Schema::dropIfExists('tb_scholarship_paper');
    }
};
