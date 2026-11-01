<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_placement_test_applicant', function (Blueprint $table) {
            $table->id();
            $table->string('application_code', 30)->unique();
            $table->foreignId('academic_year_id')->nullable()->constrained('tb_academic_year')->nullOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained('tb_school_info')->nullOnDelete();
            $table->foreignId('target_grade_id')->nullable()->constrained('tb_grade')->nullOnDelete();
            $table->foreignId('recommended_grade_id')->nullable()->constrained('tb_grade')->nullOnDelete();
            $table->foreignId('enrolled_student_id')->nullable()->constrained('tb_student')->nullOnDelete();
            $table->string('full_name_en', 160);
            $table->string('full_name_kh', 160)->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('previous_school', 200)->nullable();
            $table->string('parent_name', 160)->nullable();
            $table->string('parent_phone', 50)->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['academic_year_id','campus_id','target_grade_id'],'placement_applicant_filters');
        });

        Schema::create('tb_placement_test_question', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained('tb_grade')->restrictOnDelete();
            $table->string('subject', 100);
            $table->string('topic', 160)->nullable();
            $table->string('difficulty', 20)->default('medium');
            $table->string('type', 32)->default('multiple_choice');
            $table->longText('prompt');
            $table->json('options');
            $table->string('correct_answer', 10);
            $table->decimal('default_points', 8, 2)->default(1);
            $table->text('explanation')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['grade_id','subject','difficulty'],'placement_question_filters');
        });

        Schema::create('tb_placement_test_form', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained('tb_grade')->restrictOnDelete();
            $table->string('name', 160);
            $table->char('form_code', 1);
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->decimal('max_score', 8, 2)->default(100);
            $table->decimal('pass_score', 8, 2)->default(60);
            $table->json('subject_rules')->nullable();
            $table->json('blueprint')->nullable();
            $table->text('instructions')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['grade_id','form_code','revision'],'placement_form_revision_unique');
        });

        Schema::create('tb_placement_test_form_question', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('tb_placement_test_form')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('tb_placement_test_question')->restrictOnDelete();
            $table->unsignedSmallInteger('order_no');
            $table->string('subject', 100);
            $table->decimal('points', 8, 2)->default(1);
            $table->timestamps();
            $table->unique(['form_id','question_id'],'placement_form_question_unique');
            $table->unique(['form_id','order_no'],'placement_form_order_unique');
        });

        Schema::create('tb_placement_test_attempt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('tb_placement_test_applicant')->cascadeOnDelete();
            $table->foreignId('form_id')->constrained('tb_placement_test_form')->restrictOnDelete();
            $table->string('access_token', 64)->unique();
            $table->string('paper_token', 24)->unique();
            $table->unsignedSmallInteger('attempt_no')->default(1);
            $table->string('method', 20)->default('online');
            $table->string('status', 24)->default('assigned')->index();
            $table->unsignedSmallInteger('duration_minutes');
            $table->decimal('max_score', 8, 2);
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('percentage', 6, 2)->nullable();
            $table->string('result', 20)->default('pending')->index();
            $table->json('subject_scores')->nullable();
            $table->json('snapshot');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['applicant_id','attempt_no'],'placement_applicant_attempt_unique');
        });

        Schema::create('tb_placement_test_answer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('tb_placement_test_attempt')->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('tb_placement_test_question')->nullOnDelete();
            $table->unsignedSmallInteger('question_order');
            $table->string('subject', 100);
            $table->json('question_snapshot');
            $table->string('answer', 20)->nullable();
            $table->string('detected_answer', 20)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('awarded_points', 8, 2)->default(0);
            $table->string('flag', 30)->nullable();
            $table->string('source', 20)->default('online');
            $table->timestamps();
            $table->unique(['attempt_id','question_order'],'placement_attempt_answer_unique');
        });

        Schema::create('tb_placement_test_scan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('tb_placement_test_attempt')->restrictOnDelete();
            $table->string('image_path');
            $table->string('original_path');
            $table->char('image_hash', 64);
            $table->json('detected')->nullable();
            $table->json('flags')->nullable();
            $table->json('confidence')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['attempt_id','image_hash'],'placement_scan_duplicate_unique');
        });

        Schema::create('tb_placement_test_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->nullable()->constrained('tb_placement_test_applicant')->cascadeOnDelete();
            $table->foreignId('attempt_id')->nullable()->constrained('tb_placement_test_attempt')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('tb_student_enrollment', function (Blueprint $table) {
            $table->foreignId('placement_attempt_id')->nullable()->unique()->constrained('tb_placement_test_attempt')->nullOnDelete();
        });

        $permissions = [
            ['code'=>'placement-tests.view','module'=>'placement-tests','action'=>'view','name'=>'View Student Placement Test'],
            ['code'=>'placement-tests.applicants','module'=>'placement-tests','action'=>'applicants','name'=>'Manage Placement Test Applicants'],
            ['code'=>'placement-tests.questions','module'=>'placement-tests','action'=>'questions','name'=>'Manage Placement Test Question Bank'],
            ['code'=>'placement-tests.forms','module'=>'placement-tests','action'=>'forms','name'=>'Manage Placement Test Forms'],
            ['code'=>'placement-tests.publish','module'=>'placement-tests','action'=>'publish','name'=>'Approve and Publish Placement Test Forms'],
            ['code'=>'placement-tests.assign','module'=>'placement-tests','action'=>'assign','name'=>'Assign Placement Tests'],
            ['code'=>'placement-tests.score','module'=>'placement-tests','action'=>'score','name'=>'Review and Score Placement Tests'],
            ['code'=>'placement-tests.scan','module'=>'placement-tests','action'=>'scan','name'=>'Upload and Verify Placement Test Answer Sheets'],
            ['code'=>'placement-tests.results','module'=>'placement-tests','action'=>'results','name'=>'View Placement Test Results'],
            ['code'=>'placement-tests.enroll','module'=>'placement-tests','action'=>'enroll','name'=>'Send Passed Applicants to Student Enrollment'],
            ['code'=>'placement-tests.settings','module'=>'placement-tests','action'=>'settings','name'=>'Manage Placement Test Settings'],
        ];
        foreach ($permissions as $permission) DB::table('access_permissions')->updateOrInsert(['code'=>$permission['code']],$permission+['created_at'=>now(),'updated_at'=>now()]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('tb_student_enrollment','placement_attempt_id')) Schema::table('tb_student_enrollment', function (Blueprint $table) {$table->dropConstrainedForeignId('placement_attempt_id');});
        foreach (['event','scan','answer','attempt','form_question','form','question','applicant'] as $table) Schema::dropIfExists('tb_placement_test_'.$table);
        DB::table('access_permissions')->where('code','like','placement-tests.%')->delete();
    }
};
