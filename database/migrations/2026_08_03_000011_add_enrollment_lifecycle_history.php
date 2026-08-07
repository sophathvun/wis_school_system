<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_student_enrollment', function (Blueprint $table) {
            $table->string('enrollment_status', 30)->default('active')->after('status');
            $table->date('enrolled_on')->nullable()->after('enrollment_status');
            $table->date('ended_on')->nullable()->after('enrolled_on');
            $table->string('exit_reason', 255)->nullable()->after('ended_on');
            $table->text('notes')->nullable()->after('exit_reason');
            $table->index(['academic_year_id', 'campus_id', 'grade_id', 'class_id', 'session_id'], 'enrollment_assignment_index');
            $table->index(['student_id', 'enrollment_status'], 'student_enrollment_status_index');
        });

        Schema::create('tb_student_enrollment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('tb_student_enrollment')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('tb_student')->cascadeOnDelete();
            $table->string('action_type', 30);
            $table->foreignId('campus_id')->nullable()->constrained('tb_school_info')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('tb_academic_year')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('tb_grade')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('tb_class')->nullOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('tb_session')->nullOnDelete();
            $table->string('enrollment_status', 30)->nullable();
            $table->date('effective_on')->nullable();
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'effective_on']);
            $table->index(['enrollment_id', 'action_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_student_enrollment_history');
        Schema::table('tb_student_enrollment', function (Blueprint $table) {
            $table->dropIndex('enrollment_assignment_index');
            $table->dropIndex('student_enrollment_status_index');
            $table->dropColumn(['enrollment_status', 'enrolled_on', 'ended_on', 'exit_reason', 'notes']);
        });
    }
};
