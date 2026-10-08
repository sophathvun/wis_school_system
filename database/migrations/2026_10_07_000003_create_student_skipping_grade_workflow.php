<?php

use App\Support\StudentSkippingGradePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_student_skipping_grade', function (Blueprint $t) {
            $t->id();
            $t->foreignId('enrollment_id')->constrained('tb_student_enrollment')->restrictOnDelete();
            $t->foreignId('student_id')->constrained('tb_student')->restrictOnDelete();
            $t->foreignId('campus_id')->constrained('tb_school_info')->restrictOnDelete();
            $t->foreignId('academic_year_id')->constrained('tb_academic_year')->restrictOnDelete();
            $t->foreignId('source_grade_id')->constrained('tb_grade')->restrictOnDelete();
            $t->foreignId('source_class_id')->constrained('tb_class')->restrictOnDelete();
            $t->unsignedBigInteger('source_session_id')->nullable();
            $t->foreignId('target_grade_id')->constrained('tb_grade')->restrictOnDelete();
            $t->foreignId('target_class_id')->constrained('tb_class')->restrictOnDelete();
            $t->unsignedBigInteger('target_session_id')->nullable();
            $t->string('status', 20)->default('draft');
            $t->string('reference_number', 60)->nullable()->unique();
            $t->json('student_snapshot');
            $t->date('application_date');
            $t->string('parent_name', 200); $t->string('parent_phone', 50)->nullable();
            $t->text('reason')->nullable(); $t->json('criteria');
            $t->decimal('average_score', 6, 2)->nullable(); $t->unsignedTinyInteger('average_scale')->default(100);
            $t->json('committee_names');
            $t->string('signed_request_path')->nullable();
            $t->timestamp('parent_signed_at')->nullable(); $t->timestamp('submitted_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable(); $t->date('approval_date')->nullable();
            $t->date('received_date')->nullable(); $t->date('review_date')->nullable(); $t->date('effective_date')->nullable();
            $t->json('approval_snapshot')->nullable();
            $t->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('rejected_at')->nullable(); $t->text('rejection_reason')->nullable();
            $t->timestamps();
            $t->index(['campus_id','academic_year_id','status'], 'skipping_campus_year_status');
            $t->index(['enrollment_id','status'], 'skipping_enrollment_status');
        });
        Schema::create('tb_skipping_grade_settings', function (Blueprint $t) {
            $t->unsignedTinyInteger('id')->primary();
            $t->string('signer_name_kh', 200)->nullable(); $t->string('signer_name_en', 200)->nullable();
            $t->string('signer_title_kh', 200)->default('អនុប្រធាន');
            $t->string('signer_title_en', 200)->default('(Vice) President');
            $t->string('signature_path')->nullable(); $t->string('stamp_path')->nullable();
            $t->string('number_prefix', 16)->default('SG'); $t->json('committee_names')->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        DB::table('tb_skipping_grade_settings')->insert(['id'=>1,'created_at'=>now(),'updated_at'=>now()]);
        foreach (StudentSkippingGradePermissions::catalog() as $action=>$name) {
            DB::table('access_permissions')->updateOrInsert(['code'=>'student-skipping-grade.'.$action], [
                'module'=>'student-skipping-grade','action'=>$action,'name'=>$name,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
        $mapping = [
            'students.view'=>['view','print'], 'students.enrollment.manage'=>['create','update','submit'],
            'students.enrollment.create'=>['create','submit'], 'students.enrollment.update'=>['update','submit'],
        ];
        foreach (['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id','access_user_permission_overrides'=>'user_id'] as $table=>$owner) {
            foreach ($mapping as $oldCode=>$actions) {
                $oldId=DB::table('access_permissions')->where('code',$oldCode)->value('id');
                if (!$oldId) continue;
                $owners=DB::table($table)->where('permission_id',$oldId)->when($owner==='user_id',fn($q)=>$q->where('allowed',true))->pluck($owner);
                foreach ($owners as $ownerId) foreach ($actions as $action) {
                    $id=DB::table('access_permissions')->where('code','student-skipping-grade.'.$action)->value('id');
                    DB::table($table)->insertOrIgnore([$owner=>$ownerId,'permission_id'=>$id,'created_at'=>now(),'updated_at'=>now()]+($owner==='user_id'?['allowed'=>true]:[]));
                }
            }
        }
        $super=DB::table('access_roles')->where('code','super-admin')->value('id');
        if ($super) foreach (DB::table('access_permissions')->where('module','student-skipping-grade')->pluck('id') as $id) {
            DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>$super,'permission_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_student_skipping_grade'); Schema::dropIfExists('tb_skipping_grade_settings');
        DB::table('access_permissions')->where('module','student-skipping-grade')->delete();
    }
};
