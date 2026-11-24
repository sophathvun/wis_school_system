<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_gep_student', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->unique()->constrained('tb_student')->restrictOnDelete();
            $t->string('source', 20); // existing or new; identity is shared, enrollments are separate.
            $t->timestamps();
        });
        Schema::create('tb_gep_enrollment', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gep_student_id')->constrained('tb_gep_student')->restrictOnDelete();
            $t->foreignId('term_assignment_id')->constrained('tb_gep_term_assignment')->restrictOnDelete();
            $t->foreignId('campus_id')->constrained('tb_school_info')->restrictOnDelete();
            $t->foreignId('level_id')->constrained('tb_gep_level')->restrictOnDelete();
            $t->foreignId('class_type_id')->constrained('tb_gep_class_type')->restrictOnDelete();
            $t->foreignId('class_id')->constrained('tb_gep_class')->restrictOnDelete();
            $t->foreignId('group_id')->constrained('tb_gep_group')->restrictOnDelete();
            $t->string('enrollment_status', 20)->default('active');
            $t->date('enrolled_on');
            $t->text('notes')->nullable();
            $t->string('guardian_name', 150)->nullable();
            $t->string('guardian_phone', 50)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedTinyInteger('active_slot')->nullable()->default(1);
            $t->timestamps();$t->softDeletes();
            $t->unique(['gep_student_id', 'term_assignment_id', 'active_slot'], 'gep_enrollment_student_term_unique');
            $t->index(['campus_id', 'term_assignment_id', 'enrollment_status'], 'gep_enrollment_scope_index');
        });
        Schema::create('tb_gep_enrollment_history', function (Blueprint $t) {
            $t->id();$t->foreignId('enrollment_id')->constrained('tb_gep_enrollment')->restrictOnDelete();
            $t->string('action', 30);$t->json('before_values')->nullable();$t->json('after_values')->nullable();
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();
        });
        \App\Support\GepEnrollmentPermissions::registerMissing();
        $ids = DB::table('access_permissions')->where('module', 'gep-students.enrollment')->pluck('id');
        foreach (DB::table('access_roles')->whereIn('code', ['super-admin', 'central-office-admin'])->pluck('id') as $role) {
            foreach ($ids as $permission) DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>$role,'permission_id'=>$permission,'created_at'=>now(),'updated_at'=>now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_gep_enrollment_history');Schema::dropIfExists('tb_gep_enrollment');Schema::dropIfExists('tb_gep_student');
        $ids = DB::table('access_permissions')->where('module','gep-students.enrollment')->whereIn('action',array_keys(\App\Support\GepEnrollmentPermissions::ACTIONS))->pluck('id');
        foreach (['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table) DB::table($table)->whereIn('permission_id',$ids)->delete();
        DB::table('access_permissions')->whereIn('id',$ids)->delete();
    }
};
