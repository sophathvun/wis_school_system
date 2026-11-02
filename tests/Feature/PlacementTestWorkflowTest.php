<?php

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\PlacementTestSetting;
use App\Models\PlacementTestSubject;
use App\Models\PlacementTestTopic;
use App\Models\SchoolInfo;
use App\Models\User;
use App\Services\PlacementTests;
use App\Support\ScholarshipAnswerSheetLayout;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->integer('active_campus_id')->nullable();
        $t->integer('department_id')->nullable();
        $t->boolean('is_global')->default(false);
    });
    Schema::create('access_roles', function (Blueprint $t) {
        $t->id();
        $t->string('code');
        $t->integer('status')->default(1);
        $t->boolean('is_global')->default(false);
    });
    Schema::create('access_user_roles', function (Blueprint $t) {
        $t->integer('user_id');
        $t->integer('role_id');
        $t->integer('campus_id')->nullable();
    });
    Schema::create('access_permissions', function (Blueprint $t) {
        $t->id();
        $t->string('code')->unique();
        $t->string('module');
        $t->string('action');
        $t->string('name');
        $t->timestamps();
    });
    Schema::create('tb_school_info', function (Blueprint $t) {
        $t->id();
        $t->string('campus_name_en');
        $t->boolean('status')->default(true);
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('tb_academic_year', function (Blueprint $t) {
        $t->id();
        $t->string('academic_year');
        $t->string('period_type')->default('regular');
        $t->string('lifecycle_status')->default('started');
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('tb_grade', function (Blueprint $t) {
        $t->id();
        $t->string('grade');
        $t->string('grade_short_name');
        $t->integer('grade_order')->default(1);
        $t->boolean('status')->default(true);
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('tb_student', function (Blueprint $t) {
        $t->id();
    });
    Schema::create('tb_student_enrollment', function (Blueprint $t) {
        $t->id();
    });
    (require database_path('migrations/2026_11_01_000001_create_student_placement_test_module.php'))->up();
    (require database_path('migrations/2026_11_01_000002_create_placement_test_settings_table.php'))->up();
    (require database_path('migrations/2026_11_01_000003_add_option_label_style_to_placement_questions.php'))->up();
    (require database_path('migrations/2026_11_01_000004_expand_placement_test_written_answers.php'))->up();
    (require database_path('migrations/2026_11_01_000005_create_placement_test_subjects_and_topics.php'))->up();
    (require database_path('migrations/2026_11_01_000006_backfill_placement_test_subjects_and_topics.php'))->up();
    DB::table('users')->insert(['id' => 1, 'name' => 'Super Administrator', 'active_campus_id' => 1]);
    DB::table('access_roles')->insert(['id' => 1, 'code' => 'super-admin']);
    DB::table('access_user_roles')->insert(['user_id' => 1, 'role_id' => 1]);
    DB::table('tb_school_info')->insert(['id' => 1, 'campus_name_en' => 'Main Campus', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('tb_academic_year')->insert(['id' => 1, 'academic_year' => '2026-2027', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('tb_grade')->insert(['id' => 1, 'grade' => 'Grade 5', 'grade_short_name' => 'G5', 'created_at' => now(), 'updated_at' => now()]);
});

it('enforces campus settings and supports complete record lifecycles', function () {
    $user = User::findOrFail(1);
    $tests = app(PlacementTests::class);
    $grade = Grade::findOrFail(1);
    $campus = SchoolInfo::findOrFail(1);
    $year = AcademicYear::findOrFail(1);
    $applicant = $tests->saveApplicant($user, ['academic_year_id' => $year->id, 'campus_id' => $campus->id, 'target_grade_id' => $grade->id, 'full_name_en' => 'Lifecycle Candidate']);
    $question = $tests->saveQuestion($user, ['grade_id' => $grade->id, 'subject' => 'English', 'difficulty' => 'medium', 'type' => 'multiple_choice', 'prompt' => 'Choose A.', 'options' => ['Yes', 'No'], 'correct_answer' => 'A', 'default_points' => 1, 'status' => 'approved']);
    $numericQuestion = $tests->saveQuestion($user, ['grade_id' => $grade->id, 'subject' => 'Mathematics', 'difficulty' => 'easy', 'type' => 'multiple_choice', 'prompt' => 'Choose option seven.', 'option_label_style' => 'numbers', 'options' => ['One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven'], 'correct_answer' => '7', 'default_points' => 1, 'status' => 'draft']);
    expect($numericQuestion->option_label_style)->toBe('numbers')->and($numericQuestion->options)->toHaveCount(7)->and($numericQuestion->correct_answer)->toBe('7');
    $essayQuestion = $tests->saveQuestion($user, ['grade_id' => $grade->id, 'subject' => 'English', 'difficulty' => 'medium', 'type' => 'essay', 'prompt' => 'Write about your school.', 'correct_answer' => 'A strong response has a clear introduction, supporting details, and conclusion.', 'default_points' => 10, 'status' => 'draft']);
    expect($essayQuestion->type)->toBe('essay')->and($essayQuestion->options)->toBe([])->and($essayQuestion->correct_answer)->toContain('clear introduction');
    $subject = PlacementTestSubject::create(['code' => 'ENG', 'name_en' => 'English', 'name_kh' => 'ភាសាអង់គ្លេស', 'display_order' => 1, 'status' => 'active', 'created_by' => $user->id, 'updated_by' => $user->id]);
    $subject->grades()->sync([$grade->id]);
    $topic = PlacementTestTopic::create(['code' => 'ENG-GRAMMAR', 'subject_id' => $subject->id, 'name_en' => 'Grammar', 'name_kh' => 'វេយ្យាករណ៍', 'display_order' => 1, 'status' => 'active', 'created_by' => $user->id, 'updated_by' => $user->id]);
    $topic->grades()->sync([$grade->id]);
    $managedQuestion = $tests->saveQuestion($user, ['grade_id' => $grade->id, 'subject_id' => $subject->id, 'topic_id' => $topic->id, 'difficulty' => 'easy', 'type' => 'fill_blank', 'prompt' => 'Complete the sentence.', 'correct_answer' => 'school', 'default_points' => 1, 'status' => 'draft']);
    expect($managedQuestion->subject)->toBe('English')->and($managedQuestion->topic)->toBe('Grammar')->and($managedQuestion->subject_id)->toBe($subject->id)->and($managedQuestion->topic_id)->toBe($topic->id);
    $copy = $tests->duplicateQuestion($user, $question);
    expect($copy->status)->toBe('draft')->and($copy->id)->not->toBe($question->id);
    $tests->archiveQuestion($user, $copy);
    expect($copy->fresh()->status)->toBe('archived');
    $form = $tests->saveForm($user, ['grade_id' => $grade->id, 'name' => 'Lifecycle Test', 'form_code' => 'A', 'revision' => 1, 'duration_minutes' => 30, 'max_score' => 1, 'pass_score' => 1, 'subject_rules' => [['subject' => 'English', 'max_score' => 1, 'pass_score' => 1, 'required' => true]], 'status' => 'draft']);
    $tests->syncFormQuestions($user, $form, [['question_id' => $question->id, 'points' => 1]]);
    $tests->changeFormStatus($user, $form, 'approved');
    $form = $tests->changeFormStatus($user, $form, 'published');
    $revision = $tests->duplicateForm($user, $form);
    expect($revision->status)->toBe('draft')->and($revision->revision)->toBe(2)->and($revision->questions)->toHaveCount(1);
    PlacementTestSetting::create(['campus_id' => $campus->id, 'online_enabled' => false, 'paper_enabled' => true, 'manual_enabled' => true, 'scan_enabled' => true, 'show_result_to_candidate' => false, 'enrollment_handoff_enabled' => true, 'max_attempts' => 1, 'default_duration_minutes' => 45, 'default_max_score' => 100, 'default_pass_percentage' => 60, 'online_grace_minutes' => 1]);
    expect(fn () => $tests->assign($user, $applicant, $form, 'online'))->toThrow(ValidationException::class);
    $attempt = $tests->assign($user, $applicant, $form, 'manual');
    $tests->invalidateAttempt($user, $attempt, 'Candidate was absent.');
    expect($attempt->fresh()->status)->toBe('invalidated')->and($applicant->fresh()->status)->toBe('draft');
    expect(fn () => $tests->assign($user, $applicant, $form, 'manual'))->toThrow(ValidationException::class);
    $archivable = $tests->saveApplicant($user, ['academic_year_id' => $year->id, 'campus_id' => $campus->id, 'target_grade_id' => $grade->id, 'full_name_en' => 'Archive Candidate']);
    $tests->archiveApplicant($user, $archivable);
    expect($archivable->fresh()->status)->toBe('archived');
});

it('scores online and scanned placement tests and preserves comparable form controls', function () {
    Storage::fake('local');
    $user = User::findOrFail(1);
    $tests = app(PlacementTests::class);
    $grade = Grade::findOrFail(1);
    $applicant = $tests->saveApplicant($user, ['academic_year_id' => AcademicYear::first()->id, 'campus_id' => SchoolInfo::first()->id, 'target_grade_id' => $grade->id, 'full_name_en' => 'Test Candidate']);
    $question = $tests->saveQuestion($user, ['grade_id' => $grade->id, 'subject' => 'Mathematics', 'topic' => 'Number sense', 'difficulty' => 'medium', 'type' => 'multiple_choice', 'prompt' => 'Select the second option.', 'options' => ['One', 'Two', 'Three', 'Four'], 'correct_answer' => 'B', 'default_points' => 2, 'status' => 'approved']);
    $form = $tests->saveForm($user, ['grade_id' => $grade->id, 'name' => 'Grade 5 Placement', 'form_code' => 'A', 'revision' => 1, 'duration_minutes' => 30, 'max_score' => 2, 'pass_score' => 1, 'subject_rules' => [['subject' => 'Mathematics', 'max_score' => 2, 'pass_score' => 1, 'required' => true]], 'status' => 'draft']);
    $form = $tests->syncFormQuestions($user, $form, [['question_id' => $question->id, 'points' => 2]]);
    $form = $tests->changeFormStatus($user, $form, 'approved');
    $form = $tests->changeFormStatus($user, $form, 'published');
    expect((float) $form->blueprint['subjects']['Mathematics']['points'])->toBe(2.0)->and($form->blueprint['difficulty']['medium']['questions'])->toBe(1);

    $online = $tests->submit($tests->start($tests->assign($user, $applicant, $form, 'online')), [1 => 'B'], 'online', $user);
    expect($online->status)->toBe('verified')->and($online->result)->toBe('passed')->and((float) $online->score)->toBe(2.0);

    $paper = $tests->assign($user, $applicant, $form, 'paper');
    $layout = app(ScholarshipAnswerSheetLayout::class);
    $image = imagecreatetruecolor(1000, 1414);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefill($image, 0, 0, $white);
    foreach ($layout::MARKERS as [$x,$y]) {
        imagefilledrectangle($image, $x - 10, $y - 10, $x + 9, $y + 9, $black);
    }
    foreach ($layout->bits($paper->paper_token) as $i => $bit) {
        if ($bit === '1') {
            imagefilledrectangle($image, $layout::CODE_X + ($i % 12) * 10, $layout::CODE_Y + intdiv($i, 12) * 10, $layout::CODE_X + ($i % 12) * 10 + 9, $layout::CODE_Y + intdiv($i, 12) * 10 + 9, $black);
        }
    }
    foreach ($layout->questions(1, ['A', 'B', 'C', 'D'], $layout::VERSION) as $row) {
        foreach ($row['dots'] as $dot) {
            imageellipse($image, $dot['x'], $dot['y'], (int) round($dot['radius'] * 2), (int) round($dot['radius'] * 2), $black);
            if ($dot['label'] === 'B') {
                imagefilledellipse($image, $dot['x'], $dot['y'], (int) round($dot['radius'] * 2 - 1), (int) round($dot['radius'] * 2 - 1), $black);
            }
        }
    }
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    $upload = UploadedFile::fake()->createWithContent('answer-sheet.png', $bytes);
    $scan = $tests->uploadScan($user, $paper, $upload);
    $scored = $tests->verifyScan($user, $scan, [1 => 'B']);
    expect($scan->detected)->toBe(['B'])->and($scored->status)->toBe('verified')->and($scored->result)->toBe('passed')->and((float) $scored->score)->toBe(2.0);
});

it('keeps writing responses pending until an examiner awards points', function () {
    $user = User::findOrFail(1);
    $tests = app(PlacementTests::class);
    $grade = Grade::findOrFail(1);
    $applicant = $tests->saveApplicant($user, ['academic_year_id' => AcademicYear::first()->id, 'campus_id' => SchoolInfo::first()->id, 'target_grade_id' => $grade->id, 'full_name_en' => 'Writing Candidate']);
    $blank = $tests->saveQuestion($user, ['grade_id' => $grade->id, 'subject' => 'English', 'difficulty' => 'easy', 'type' => 'fill_blank', 'prompt' => 'The opposite of hot is ____.', 'correct_answer' => 'cold', 'default_points' => 2, 'status' => 'approved']);
    $essay = $tests->saveQuestion($user, ['grade_id' => $grade->id, 'subject' => 'English', 'difficulty' => 'medium', 'type' => 'essay', 'prompt' => 'Write about your school.', 'correct_answer' => 'Use a clear introduction, supporting details, and conclusion.', 'default_points' => 8, 'status' => 'approved']);
    $form = $tests->saveForm($user, ['grade_id' => $grade->id, 'name' => 'Writing Placement', 'form_code' => 'A', 'revision' => 1, 'duration_minutes' => 30, 'max_score' => 10, 'pass_score' => 5, 'subject_rules' => [['subject' => 'English', 'max_score' => 10, 'pass_score' => 5, 'required' => true]], 'status' => 'draft']);
    $tests->syncFormQuestions($user, $form, [['question_id' => $blank->id, 'points' => 2], ['question_id' => $essay->id, 'points' => 8]]);
    $tests->changeFormStatus($user, $form, 'approved');
    $form = $tests->changeFormStatus($user, $form, 'published');
    $attempt = $tests->start($tests->assign($user, $applicant, $form, 'online'));
    $submitted = $tests->submit($attempt, [1 => 'Cold', 2 => 'My school helps me learn and grow.'], 'online');
    expect($submitted->status)->toBe('submitted')->and($submitted->result)->toBe('pending')->and($submitted->score)->toBeNull();
    $marked = $tests->submit($submitted,[1 => 'Cold', 2 => 'My school helps me learn and grow.'],'manual',$user,[2 => 6]);
    expect($marked->status)->toBe('verified')->and($marked->result)->toBe('passed')->and((float) $marked->score)->toBe(8.0);
});
