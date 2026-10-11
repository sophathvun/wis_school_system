<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_skipping_grade_settings', function (Blueprint $t) {
            $t->string('approval_flow_mode', 20)->default('legacy');
            $t->json('central_approval_steps')->nullable();
        });
        Schema::table('tb_skipping_grade_campus_settings', fn(Blueprint $t) => $t->json('approval_steps')->nullable());
        Schema::table('tb_student_skipping_grade', function (Blueprint $t) {
            // Existing records, including drafts, continue under the original flow.
            $t->string('approval_flow_mode', 20)->default('legacy');
            $t->json('workflow_signer_snapshot')->nullable();
            $t->json('supporting_documents')->nullable();
        });
        Schema::create('tb_skipping_grade_approval_steps', function (Blueprint $t) {
            $t->id(); $t->foreignId('request_id')->constrained('tb_student_skipping_grade')->cascadeOnDelete();
            $t->unsignedInteger('sequence'); $t->string('scope', 20); $t->string('title', 120);
            $t->boolean('is_final')->default(false); $t->string('status', 20)->default('waiting');
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('reviewer_snapshot'); $t->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->json('actor_snapshot')->nullable(); $t->timestamp('acted_at')->nullable(); $t->text('comments')->nullable();
            $t->unsignedInteger('revision')->default(1); $t->timestamps();
            $t->unique(['request_id', 'sequence']); $t->index(['reviewer_id', 'status']);
        });
        Schema::create('tb_skipping_grade_reassignments', function (Blueprint $t) {
            $t->id(); $t->foreignId('step_id')->constrained('tb_skipping_grade_approval_steps')->cascadeOnDelete();
            $t->json('previous_reviewer'); $t->json('replacement_reviewer');
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('changed_by_name', 200); $t->text('reason'); $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_skipping_grade_reassignments'); Schema::dropIfExists('tb_skipping_grade_approval_steps');
        Schema::table('tb_student_skipping_grade', fn(Blueprint $t) => $t->dropColumn(['approval_flow_mode','workflow_signer_snapshot','supporting_documents']));
        Schema::table('tb_skipping_grade_campus_settings', fn(Blueprint $t) => $t->dropColumn('approval_steps'));
        Schema::table('tb_skipping_grade_settings', fn(Blueprint $t) => $t->dropColumn(['approval_flow_mode','central_approval_steps']));
    }
};
