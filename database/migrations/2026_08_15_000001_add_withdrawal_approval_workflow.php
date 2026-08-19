<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_student_enrollment_history', function (Blueprint $table) {
            $table->string('withdrawal_status', 30)->default('pending')->after('action_type');
            $table->dateTime('paper_signed_at')->nullable()->after('withdrawal_status');
            $table->unsignedBigInteger('paper_signed_by')->nullable()->after('paper_signed_at');
            $table->string('signed_form_path')->nullable()->after('paper_signed_by');
            $table->dateTime('principal_approved_at')->nullable()->after('signed_form_path');
            $table->unsignedBigInteger('principal_approved_by')->nullable()->after('principal_approved_at');
            $table->dateTime('approved_at')->nullable()->after('principal_approved_by');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            $table->dateTime('rejected_at')->nullable()->after('approved_by');
            $table->unsignedBigInteger('rejected_by')->nullable()->after('rejected_at');
            $table->text('rejection_reason')->nullable()->after('rejected_by');
            $table->index(['action_type', 'withdrawal_status'], 'student_withdrawal_workflow_status_idx');
        });

        DB::table('tb_student_enrollment_history')
            ->where('action_type', 'withdrawal')
            ->update(['withdrawal_status' => 'approved', 'approved_at' => DB::raw('updated_at'), 'approved_by' => DB::raw('changed_by')]);
    }

    public function down(): void
    {
        Schema::table('tb_student_enrollment_history', function (Blueprint $table) {
            $table->dropIndex('student_withdrawal_workflow_status_idx');
            $table->dropColumn([
                'withdrawal_status', 'paper_signed_at', 'paper_signed_by', 'signed_form_path',
                'principal_approved_at', 'principal_approved_by', 'approved_at', 'approved_by',
                'rejected_at', 'rejected_by', 'rejection_reason',
            ]);
        });
    }
};
