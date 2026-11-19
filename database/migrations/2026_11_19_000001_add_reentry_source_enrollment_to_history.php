<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_student_enrollment_history', function (Blueprint $table) {
            $table->foreignId('source_enrollment_id')->nullable()->constrained('tb_student_enrollment')->nullOnDelete();
            $table->index(['source_enrollment_id', 'action_type'], 'history_reentry_enrollment_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tb_student_enrollment_history', function (Blueprint $table) {
            $table->dropForeign(['source_enrollment_id']);
            $table->dropIndex('history_reentry_enrollment_idx');
            $table->dropColumn('source_enrollment_id');
        });
    }
};
