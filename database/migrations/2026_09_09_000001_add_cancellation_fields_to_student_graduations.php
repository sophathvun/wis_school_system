<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_student_graduation', function (Blueprint $table) {
            if (!Schema::hasColumn('tb_student_graduation', 'status')) {
                $table->string('status', 20)->default('completed')->after('is_alumni')->index();
            }
            if (!Schema::hasColumn('tb_student_graduation', 'cancellation_type')) {
                $table->string('cancellation_type', 30)->nullable()->after('status');
            }
            if (!Schema::hasColumn('tb_student_graduation', 'cancellation_reason')) {
                $table->string('cancellation_reason', 500)->nullable()->after('cancellation_type');
            }
            if (!Schema::hasColumn('tb_student_graduation', 'cancelled_at')) {
                $table->dateTime('cancelled_at')->nullable()->after('cancellation_reason');
            }
            if (!Schema::hasColumn('tb_student_graduation', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tb_student_graduation', function (Blueprint $table) {
            if (Schema::hasColumn('tb_student_graduation', 'cancelled_by')) {
                $table->dropForeign(['cancelled_by']);
            }
            $table->dropColumn(array_values(array_filter([
                Schema::hasColumn('tb_student_graduation', 'status') ? 'status' : null,
                Schema::hasColumn('tb_student_graduation', 'cancellation_type') ? 'cancellation_type' : null,
                Schema::hasColumn('tb_student_graduation', 'cancellation_reason') ? 'cancellation_reason' : null,
                Schema::hasColumn('tb_student_graduation', 'cancelled_at') ? 'cancelled_at' : null,
                Schema::hasColumn('tb_student_graduation', 'cancelled_by') ? 'cancelled_by' : null,
            ])));
        });
    }
};
