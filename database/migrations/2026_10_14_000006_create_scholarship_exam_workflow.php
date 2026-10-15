<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_scholarship_exam', function (Blueprint $table) {
            $table->id(); $table->foreignId('academic_year_id')->constrained('tb_academic_year')->restrictOnDelete();
            $table->string('name', 160); $table->date('exam_date'); $table->unsignedSmallInteger('room_capacity')->default(25);
            $table->json('campus_ids'); $table->json('eligible_ranks'); $table->string('tie_policy', 20)->default('competition');
            $table->json('award_rules')->nullable(); $table->unsignedInteger('version')->default(1);
            $table->timestamp('seats_locked_at')->nullable(); $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });
        Schema::create('tb_scholarship_subject', function (Blueprint $table) {
            $table->id(); $table->foreignId('exam_id')->constrained('tb_scholarship_exam')->cascadeOnDelete();
            $table->string('name', 100); $table->unsignedTinyInteger('grade_from'); $table->unsignedTinyInteger('grade_to');
            $table->decimal('max_score', 8, 2); $table->decimal('weight', 8, 2)->default(1); $table->unsignedSmallInteger('position'); $table->timestamps();
        });
        Schema::create('tb_scholarship_candidate', function (Blueprint $table) {
            $table->id(); $table->foreignId('exam_id')->constrained('tb_scholarship_exam')->cascadeOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->constrained('tb_student_enrollment')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('tb_student')->nullOnDelete();
            $table->unsignedBigInteger('campus_id'); $table->unsignedBigInteger('class_id'); $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedTinyInteger('grade_number'); $table->unsignedInteger('class_rank'); $table->json('snapshot');
            $table->unsignedInteger('room_number')->nullable(); $table->unsignedInteger('room_seat')->nullable();
            $table->unsignedInteger('grade_exam_number')->nullable(); $table->string('candidate_code', 24)->nullable();
            $table->boolean('absent')->default(false); $table->unsignedInteger('version')->default(1);
            $table->foreignId('scored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('reviewed_at')->nullable();
            $table->decimal('result_total', 10, 2)->nullable(); $table->decimal('result_percentage', 8, 4)->nullable();
            $table->unsignedInteger('result_rank')->nullable(); $table->decimal('award_percent', 5, 2)->nullable(); $table->timestamps();
            $table->unique(['exam_id', 'student_enrollment_id'], 'scholarship_candidate_enrollment_unique');
            $table->unique(['exam_id', 'student_id'], 'scholarship_candidate_student_unique');
            $table->unique(['exam_id', 'room_number', 'room_seat'], 'scholarship_candidate_seat_unique');
            $table->index(['exam_id', 'grade_number']);
        });
        Schema::create('tb_scholarship_score', function (Blueprint $table) {
            $table->id(); $table->foreignId('candidate_id')->constrained('tb_scholarship_candidate')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('tb_scholarship_subject')->restrictOnDelete();
            $table->decimal('score', 8, 2)->nullable(); $table->timestamps(); $table->unique(['candidate_id', 'subject_id']);
        });
        Schema::create('tb_scholarship_committee', function (Blueprint $table) {
            $table->id(); $table->foreignId('exam_id')->constrained('tb_scholarship_exam')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); $table->string('role', 30);
            $table->unsignedTinyInteger('grade_number')->nullable(); $table->unsignedInteger('room_number')->nullable(); $table->timestamps();
        });
        Schema::create('tb_scholarship_event', function (Blueprint $table) {
            $table->id(); $table->foreignId('exam_id')->constrained('tb_scholarship_exam')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50); $table->json('details')->nullable(); $table->timestamp('created_at')->useCurrent();
        });
        foreach (\App\Support\ScholarshipPermissions::catalog() as $action => $name) {
            DB::table('access_permissions')->updateOrInsert(['code'=>'western-scholarship.'.$action], ['module'=>'western-scholarship','action'=>$action,'name'=>$name,'created_at'=>now(),'updated_at'=>now()]);
            $role = DB::table('access_roles')->where('code','super-admin')->value('id');
            if ($role) DB::table('access_role_permissions')->updateOrInsert(['role_id'=>$role,'permission_id'=>DB::table('access_permissions')->where('code','western-scholarship.'.$action)->value('id')], ['created_at'=>now(),'updated_at'=>now()]);
        }
    }

    public function down(): void
    {
        foreach (['event','committee','score','candidate','subject','exam'] as $table) Schema::dropIfExists('tb_scholarship_'.$table);
        $ids = DB::table('access_permissions')->where('module','western-scholarship')->whereNotIn('action',['view','assign-rank'])->pluck('id');
        foreach (['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table) DB::table($table)->whereIn('permission_id',$ids)->delete();
        DB::table('access_permissions')->whereIn('id',$ids)->delete();
    }
};
