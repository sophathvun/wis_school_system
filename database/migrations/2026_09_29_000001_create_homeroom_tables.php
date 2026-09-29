<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homeroom_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('tb_academic_year')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('tb_school_info')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('tb_grade')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('tb_class')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['teacher_id', 'academic_year_id', 'campus_id', 'class_id'], 'homeroom_teacher_class_unique');
            $table->index(['academic_year_id', 'campus_id', 'class_id', 'status'], 'homeroom_assignment_scope_index');
        });

        Schema::create('homeroom_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->nullable()->constrained('homeroom_assignments')->nullOnDelete();
            $table->foreignId('student_id')->constrained('tb_student')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('tb_student_enrollment')->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained('tb_academic_year')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('tb_school_info')->cascadeOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('tb_grade')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('tb_class')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('tb_group')->nullOnDelete();
            $table->string('record_type', 80)->default('student_meeting');
            $table->string('problem_type', 120)->nullable();
            $table->date('recorded_on');
            $table->string('meeting_with', 40)->default('student');
            $table->string('status', 30)->default('open');
            $table->text('problem_description')->nullable();
            $table->text('action_taken')->nullable();
            $table->text('parent_feedback')->nullable();
            $table->date('follow_up_on')->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'recorded_on'], 'homeroom_record_student_date_index');
            $table->index(['academic_year_id', 'campus_id', 'class_id', 'status'], 'homeroom_record_scope_index');
        });

        $permissions = [
            ['homeroom.view', 'homeroom', 'view', 'View Homeroom Activities'],
            ['homeroom.create', 'homeroom', 'create', 'Create Homeroom Records'],
            ['homeroom.edit-own', 'homeroom', 'edit-own', 'Edit Own Homeroom Records'],
            ['homeroom.view-all', 'homeroom', 'view-all', 'View All Homeroom Records'],
            ['homeroom.manage-assignments', 'homeroom', 'manage-assignments', 'Manage Homeroom Teacher Assignments'],
            ['homeroom.export', 'homeroom', 'export', 'Export Homeroom Records'],
        ];

        foreach ($permissions as [$code, $module, $action, $name]) {
            DB::table('access_permissions')->updateOrInsert(
                ['code' => $code],
                ['module' => $module, 'action' => $action, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        if ($superAdminId = DB::table('access_roles')->where('code', 'super-admin')->value('id')) {
            $permissionIds = DB::table('access_permissions')->where('module', 'homeroom')->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('access_role_permissions')->updateOrInsert(
                    ['role_id' => $superAdminId, 'permission_id' => $permissionId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('homeroom_records');
        Schema::dropIfExists('homeroom_assignments');
        DB::table('access_permissions')->where('module', 'homeroom')->delete();
    }
};
