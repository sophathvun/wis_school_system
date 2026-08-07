<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_student_enrollment_history', function (Blueprint $table) {
            if (!Schema::hasColumn('tb_student_enrollment_history', 'source_history_id')) {
                $table->foreignId('source_history_id')->nullable()->after('enrollment_id')->constrained('tb_student_enrollment_history')->nullOnDelete();
            }
            $table->index(['source_history_id', 'action_type'], 'student_history_reentry_source_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tb_student_enrollment_history', function (Blueprint $table) {
            $table->dropForeign(['source_history_id']);
            $table->dropIndex('student_history_reentry_source_idx');
            if (Schema::hasColumn('tb_student_enrollment_history', 'source_history_id')) $table->dropColumn('source_history_id');
        });
    }
};
