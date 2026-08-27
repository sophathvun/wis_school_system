<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_student_enrollment_workflow', function (Blueprint $table) {
            $table->string('status', 20)->default('completed')->after('action_type');
            $table->foreignId('parent_workflow_id')->nullable()->after('target_enrollment_id')
                ->constrained('tb_student_enrollment_workflow')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable()->after('effective_on');
            $table->foreignId('cancelled_by')->nullable()->after('changed_by')->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 255)->nullable()->after('cancelled_by');
            $table->index(['student_id', 'to_academic_year_id', 'status'], 'workflow_promotion_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('tb_student_enrollment_workflow', function (Blueprint $table) {
            $table->dropIndex('workflow_promotion_status_index');
            $table->dropForeign(['parent_workflow_id']);
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn(['status', 'parent_workflow_id', 'cancelled_at', 'cancelled_by', 'cancellation_reason']);
        });
    }
};
