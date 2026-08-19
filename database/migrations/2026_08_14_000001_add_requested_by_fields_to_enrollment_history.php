<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_student_enrollment_history', function (Blueprint $table) {
            if (!Schema::hasColumn('tb_student_enrollment_history', 'requested_by_type')) {
                $table->string('requested_by_type', 30)->nullable()->after('dropout_type');
            }
            if (!Schema::hasColumn('tb_student_enrollment_history', 'requested_by_name')) {
                $table->string('requested_by_name', 180)->nullable()->after('requested_by_type');
            }
            if (!Schema::hasColumn('tb_student_enrollment_history', 'requested_by_phone')) {
                $table->string('requested_by_phone', 50)->nullable()->after('requested_by_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tb_student_enrollment_history', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('tb_student_enrollment_history', 'requested_by_phone') ? 'requested_by_phone' : null,
                Schema::hasColumn('tb_student_enrollment_history', 'requested_by_name') ? 'requested_by_name' : null,
                Schema::hasColumn('tb_student_enrollment_history', 'requested_by_type') ? 'requested_by_type' : null,
            ]);

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
