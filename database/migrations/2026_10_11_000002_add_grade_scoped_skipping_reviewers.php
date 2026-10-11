<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_skipping_grade_step_reviewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step_id')->constrained('tb_skipping_grade_approval_steps')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('reviewer_snapshot');
            $table->string('grade_scope', 20)->default('all');
            $table->json('grade_ids')->nullable();
            $table->timestamps();
            $table->unique(['step_id', 'user_id']);
            $table->index('user_id');
        });
        // Existing submitted requests retain their original single-reviewer steps.
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_skipping_grade_step_reviewers');
    }
};
