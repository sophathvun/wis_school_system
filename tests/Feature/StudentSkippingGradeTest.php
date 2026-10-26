<?php

use App\Models\SkippingGradeSetting;
use App\Models\StudentEnrollment;
use App\Models\StudentEnrollmentHistory;
use App\Models\StudentSkippingGrade;
use App\Models\User;
use App\Services\StudentSkippingGradeService;
use App\Services\EnrollmentWorkflowService;
use App\Support\PermissionHierarchy;
use App\Support\StudentSkippingGradePermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\TrackUserPresence::class);
    Storage::fake('local');
    (require database_path('migrations/2026_08_04_000023_create_user_notifications_table.php'))->up();
    Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->integer('active_campus_id')->nullable(); $t->integer('department_id')->nullable(); $t->boolean('is_global')->default(false); $t->integer('status')->default(1); $t->timestamps(); });
    Schema::create('access_roles', function (Blueprint $t) { $t->id(); $t->string('code'); $t->string('name'); $t->integer('status')->default(1); $t->boolean('is_global')->default(false); $t->boolean('is_system')->default(false); $t->integer('department_id')->nullable(); $t->softDeletes(); $t->timestamps(); });
    Schema::create('access_permissions', function (Blueprint $t) { $t->id(); $t->string('code')->unique(); $t->string('name'); $t->string('module'); $t->string('action'); $t->timestamps(); });
    Schema::create('access_user_roles', function (Blueprint $t) { $t->integer('user_id'); $t->integer('role_id'); $t->integer('campus_id')->nullable(); $t->timestamps(); });
    foreach (['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id','access_user_permission_overrides'=>'user_id'] as $table=>$owner) Schema::create($table, function (Blueprint $t) use ($owner) { $t->integer($owner); $t->integer('permission_id'); $t->unique([$owner,'permission_id']); $t->timestamps(); if ($owner==='user_id') $t->boolean('allowed'); });
    Schema::create('access_user_campuses', function (Blueprint $t) { $t->integer('user_id'); $t->integer('campus_id'); $t->boolean('is_primary')->default(true); $t->timestamp('assigned_at')->nullable(); $t->timestamps(); });
    Schema::create('access_positions',function(Blueprint $t) { $t->id(); $t->string('name'); $t->boolean('status')->default(true); });
    DB::table('access_positions')->insert([
        ['name'=>'KH-Coordinator','status'=>1],['name'=>'Vice School Principal','status'=>1],
        ['name'=>'School Principal','status'=>1],['name'=>'Inactive Position','status'=>0],
    ]);
    Schema::create('tb_school_info', function (Blueprint $t) { $t->id(); $t->string('campus_name_en'); $t->string('campus_name_kh')->nullable(); $t->integer('status')->default(1); $t->softDeletes(); });
    Schema::create('tb_academic_year', function (Blueprint $t) { $t->id(); $t->string('academic_year'); $t->string('academic_year_code',20)->nullable(); $t->string('period_type')->default('regular'); $t->string('lifecycle_status')->default('started'); $t->softDeletes(); });
    Schema::create('tb_grade', function (Blueprint $t) { $t->id(); $t->string('grade'); $t->string('grade_short_name'); $t->integer('grade_order'); $t->integer('education_level_id')->default(1); $t->integer('status')->default(1); $t->softDeletes(); });
    Schema::create('tb_class', function (Blueprint $t) { $t->id(); $t->string('class_name'); $t->integer('class_order')->default(1); $t->integer('grade_id')->nullable(); $t->integer('session_id')->nullable(); $t->integer('academic_year_id')->nullable(); $t->integer('status')->default(1); $t->softDeletes(); });
    Schema::create('tb_session', function (Blueprint $t) { $t->id(); $t->string('session_name'); $t->integer('session_order')->default(1); $t->integer('status')->default(1); $t->softDeletes(); });
    Schema::create('tb_student', function (Blueprint $t) { $t->id(); $t->string('student_id'); $t->string('full_name_en'); $t->string('full_name_kh'); $t->string('gender')->nullable(); $t->string('photo_path')->nullable(); $t->date('date_of_birth')->nullable(); $t->integer('status')->default(1); $t->timestamps(); });
    Schema::create('tb_family_member', function (Blueprint $t) { $t->id(); $t->string('full_name_en'); $t->string('full_name_kh')->nullable(); $t->string('relationship_type'); $t->string('phone')->nullable(); $t->softDeletes(); });
    Schema::create('tb_student_family_member', function (Blueprint $t) { $t->integer('student_id'); $t->integer('family_member_id'); $t->string('relationship_type'); $t->boolean('is_primary_contact')->default(true); $t->timestamps(); });
    Schema::create('tb_student_enrollment', function (Blueprint $t) { $t->id(); foreach (['student_id','campus_id','academic_year_id','grade_id','class_id'] as $field) $t->integer($field); foreach (['session_id','group_id','academic_track_id'] as $field) $t->integer($field)->nullable(); $t->boolean('status')->default(true); $t->string('student_type')->default('old'); $t->string('enrollment_status')->default('active'); $t->date('enrolled_on')->nullable(); $t->text('notes')->nullable(); $t->timestamps(); });
    Schema::create('tb_student_enrollment_history', function (Blueprint $t) { $t->id(); foreach (['enrollment_id','source_history_id','student_id','campus_id','academic_year_id','grade_id','class_id','academic_track_id','session_id','changed_by'] as $field) $t->integer($field)->nullable(); $t->string('action_type'); $t->string('enrollment_status'); $t->string('student_type'); $t->date('effective_on'); $t->text('reason'); $t->text('notes')->nullable(); $t->timestamps(); });
    DB::table('users')->insert([['id'=>1,'name'=>'Campus Registrar','active_campus_id'=>1],['id'=>2,'name'=>'Central Office','active_campus_id'=>1]]);
    DB::table('access_roles')->insert([['id'=>1,'code'=>'registrar','name'=>'Registrar'],['id'=>2,'code'=>'super-admin','name'=>'Super Administrator']]);
    DB::table('access_user_roles')->insert(['user_id'=>1,'role_id'=>1,'campus_id'=>1]);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'BCH','campus_name_kh'=>'បឹងកេងកង'],['id'=>2,'campus_name_en'=>'DNG','campus_name_kh'=>'ដង្កោ']]);
    DB::table('access_user_campuses')->insert([['user_id'=>1,'campus_id'=>1],['user_id'=>2,'campus_id'=>1]]);
    DB::table('tb_academic_year')->insert(['id'=>1,'academic_year'=>'2025-2026','academic_year_code'=>'2526']);
    DB::table('tb_grade')->insert([['id'=>1,'grade'=>'Grade 2','grade_short_name'=>'G2','grade_order'=>2],['id'=>2,'grade'=>'Grade 4','grade_short_name'=>'G4','grade_order'=>4]]);
    DB::table('tb_class')->insert([['id'=>1,'class_name'=>'A','grade_id'=>1,'academic_year_id'=>1],['id'=>2,'class_name'=>'B','grade_id'=>2,'academic_year_id'=>1]]);
    DB::table('tb_student')->insert(['id'=>1,'student_id'=>'2612345','full_name_en'=>'SOK DARA','full_name_kh'=>'សុខ ដារា','date_of_birth'=>'2017-03-02','gender'=>'male']);
    DB::table('tb_family_member')->insert(['id'=>1,'full_name_en'=>'SOK RITH','relationship_type'=>'father','phone'=>'012345678']);
    DB::table('tb_student_family_member')->insert(['student_id'=>1,'family_member_id'=>1,'relationship_type'=>'father']);
    DB::table('tb_student_enrollment')->insert(['id'=>1,'student_id'=>1,'campus_id'=>1,'academic_year_id'=>1,'grade_id'=>1,'class_id'=>1,'academic_track_id'=>9,'group_id'=>3]);
    foreach (['students.view','students.enrollment.manage'] as $code) DB::table('access_permissions')->insert(['code'=>$code,'name'=>$code,'module'=>'students','action'=>'view']);
    foreach (DB::table('access_permissions')->pluck('id') as $id) DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>$id]);
    (require database_path('migrations/2026_08_03_000013_create_enrollment_workflow_actions.php'))->up();
    (require database_path('migrations/2026_08_26_000001_add_promotion_lifecycle_to_workflows.php'))->up();
    $this->migration=require database_path('migrations/2026_10_07_000003_create_student_skipping_grade_workflow.php');
    $this->migration->up();
    (require database_path('migrations/2026_10_08_000001_add_skipping_grade_form_templates.php'))->up();
    (require database_path('migrations/2026_10_08_000002_add_requested_academic_year_to_skipping_grade.php'))->up();
    (require database_path('migrations/2026_10_08_000003_add_skipping_grade_tab_permissions.php'))->up();
    (require database_path('migrations/2026_10_08_000004_add_central_skipping_grade_edit_permission.php'))->up();
    (require database_path('migrations/2026_10_08_000005_add_skipping_grade_campus_settings.php'))->up();
    (require database_path('migrations/2026_10_11_000001_add_configurable_skipping_grade_approval_flow.php'))->up();
    (require database_path('migrations/2026_10_11_000002_add_grade_scoped_skipping_reviewers.php'))->up();
    (require database_path('migrations/2026_10_14_000001_create_skipping_grade_document_archives.php'))->up();
    \Illuminate\Support\Facades\Queue::fake([\App\Jobs\ProcessSkippingDocumentArchive::class]);
    $this->grant=function (int $user, array $actions) { foreach ($actions as $action) { $id=DB::table('access_permissions')->where('code',$action==='students.view'?$action:'student-skipping-grade.'.$action)->value('id'); DB::table('access_user_permission_overrides')->updateOrInsert(['user_id'=>$user,'permission_id'=>$id],['allowed'=>true]); } };
    ($this->grant)(2,['students.view','view','requests','approve','reject','settings','save-settings','request-template','edit-request-template','approval-template','edit-approval-template','print']);
    $this->campus=User::find(1); $this->central=User::find(2); $this->service=app(StudentSkippingGradeService::class);
    $this->data=['enrollment_id'=>1,'target_academic_year_id'=>1,'target_grade_id'=>2,'target_class_id'=>2,'target_session_id'=>null,'application_date'=>now()->toDateString(),'parent_name'=>'SOK RITH','parent_phone'=>'012345678','reason'=>'Completed Grade 3 at another school.','criteria'=>['age'=>true,'average'=>true,'documents'=>true,'recommendation'=>true],'average_score'=>95,'average_scale'=>100,'committee_names'=>array_fill(0,4,'Committee Member')];
    $this->approval=['vp_signed'=>true,'approval_date'=>now()->toDateString(),'received_date'=>now()->toDateString(),'review_date'=>now()->toDateString(),'effective_date'=>now()->toDateString(),'age_decision'=>'standard','school_decision'=>'external','obligations'=>['conduct'=>true,'rules'=>true,'study'=>true]];
    $this->draft=fn()=>$this->service->saveDraft($this->campus,$this->data);
    $this->pending=function () { return $this->service->submit($this->campus,($this->draft)(),now()->toDateString()); };
    $this->configure=function () { foreach (['signature','stamp'] as $key) Storage::disk('local')->put('settings/'.$key.'.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j3ioAAAAASUVORK5CYII=')); SkippingGradeSetting::current()->update(['signer_name_kh'=>'គីង រដ្ឋមុនី','signer_name_en'=>'KING ROTH MONY','signature_path'=>'settings/signature.png','stamp_path'=>'settings/stamp.png']); };
    $this->workflow=app(\App\Services\SkippingGradeWorkflow::class);
    $this->workflowSetup=function () {
        ($this->configure)();
        Schema::table('users',fn(Blueprint $t)=>$t->string('photo_path')->nullable());
        Storage::fake('public');
        DB::table('access_user_roles')->insert(['user_id'=>2,'role_id'=>2,'campus_id'=>null]);
        DB::table('tb_school_info')->insert(['id'=>3,'campus_name_en'=>'CEN','campus_name_kh'=>'Central','status'=>1]);
        foreach ([3=>'KH Coordinator',4=>'Campus VSP',5=>'Vice President',6=>'Replacement VSP',7=>'Other Campus Viewer',8=>'DNG Coordinator',9=>'Central Reviewer'] as $id=>$name) {
            $campusId=in_array($id,[5,9])?3:(in_array($id,[7,8])?2:1);
            DB::table('users')->insert(['id'=>$id,'name'=>$name,'active_campus_id'=>$campusId]);
            DB::table('access_user_campuses')->insert(['user_id'=>$id,'campus_id'=>$campusId]);
            ($this->grant)($id,['students.view','view']);
        }
        DB::table('access_user_campuses')->insert(['user_id'=>7,'campus_id'=>3]);
        \App\Models\SkippingGradeCampusSetting::create(['campus_id'=>1,'committee_names'=>[],'approval_steps'=>[
            ['title'=>'KH Coordinator','user_id'=>3],['title'=>'VSP','user_id'=>4],
        ]]);
        \App\Models\SkippingGradeCampusSetting::create(['campus_id'=>2,'committee_names'=>[],'approval_steps'=>[['title'=>'KH Coordinator','user_id'=>8]]]);
        \App\Models\SkippingGradeCampusSetting::create(['campus_id'=>3,'committee_names'=>[],'approval_steps'=>[['title'=>'Coordinator','user_id'=>9]]]);
        $this->workflowSettings=fn($mode='sequential')=>['revision'=>$this->workflow->revision(),'mode'=>$mode,'campus_id'=>1,
            'campus_steps'=>[['title'=>'KH Coordinator','user_id'=>3],['title'=>'VSP','user_id'=>4]],
            'central_steps'=>[['title'=>'Central Reviewer','user_id'=>9]],'vice_president_id'=>5];
        $this->workflow->saveSettings($this->central,($this->workflowSettings)());
    };
    $this->stepData=function ($record) { $step=$this->workflow->currentStep($record); return ['approval_step_id'=>$step->id,'approval_step_revision'=>$step->revision]; };
    $this->scopedSettings=function () {
        $settings=($this->workflowSettings)();
        $settings['campus_steps'][0]=['title'=>'KH Coordinator','reviewers'=>[
            ['user_id'=>3,'grade_scope'=>'selected','grade_ids'=>[2]],
            ['user_id'=>6,'grade_scope'=>'selected','grade_ids'=>[1]],
        ]];
        $settings['central_steps']=[['title'=>'Central Reviewer','reviewers'=>[
            ['user_id'=>9,'grade_scope'=>'selected','grade_ids'=>[2]],
            ['user_id'=>7,'grade_scope'=>'selected','grade_ids'=>[1]],
        ]]];
        return $settings;
    };
    $this->olderStudent=function (?User $creator=null) {
        DB::table('tb_grade')->insert(['id'=>3,'grade'=>'Grade 6','grade_short_name'=>'G6','grade_order'=>6]);
        DB::table('tb_class')->insert(['id'=>3,'class_name'=>'A','grade_id'=>3,'academic_year_id'=>1]);
        DB::table('tb_student')->insert(['id'=>2,'student_id'=>'2619999','full_name_en'=>'OLDER STUDENT','full_name_kh'=>'Older student KH','date_of_birth'=>'2014-03-02','gender'=>'female']);
        DB::table('tb_student_enrollment')->insert(['id'=>2,'student_id'=>2,'campus_id'=>1,'academic_year_id'=>1,'grade_id'=>2,'class_id'=>2]);
        return $this->service->saveDraft($creator??$this->campus,array_replace($this->data,['enrollment_id'=>2,'target_grade_id'=>3,'target_class_id'=>3]));
    };
});

it('sends phone alerts for new grade skipping notifications only after commit without repeating existing alerts', function () {
    ($this->workflowSetup)();
    $sent = [];
    $this->mock(\App\Services\WebPushService::class, function ($mock) use (&$sent) {
        $mock->shouldReceive('sendToUsers')->andReturnUsing(function ($users, $payload) use (&$sent) { $sent[] = [$users, $payload]; });
    });
    DB::beginTransaction();
    $record = ($this->pending)();
    expect($sent)->toBe([]);
    DB::commit();
    $alert = \App\Models\UserNotification::firstOrFail();
    expect($sent)->toHaveCount(1)
        ->and($sent[0][0])->toBe([3])
        ->and($sent[0][1]['url'])->toBe(route('notifications.open', $alert));
    app(\App\Services\SkippingGradeNotifications::class)->awaiting($record->fresh());
    expect($sent)->toHaveCount(1);
    $this->service->reject(User::find(3), $record->fresh(), 'Missing document', ($this->stepData)($record));
    expect($sent)->toHaveCount(2)
        ->and($sent[1][0])->toBe([1])
        ->and($sent[1][1]['title'])->toBe('Grade Skipping Request Rejected');
});

it('does not send a phone alert when the grade skipping transaction is rolled back', function () {
    ($this->workflowSetup)();
    $this->mock(\App\Services\WebPushService::class, fn ($mock) => $mock->shouldNotReceive('sendToUsers'));
    DB::beginTransaction();
    ($this->pending)();
    expect(\App\Models\UserNotification::count())->toBe(1);
    DB::rollBack();
    expect(\App\Models\UserNotification::count())->toBe(0);
});

it('grade skipping alerts only current eligible reviewers and reading an alert keeps pending badges',function () {
    ($this->workflowSetup)();
    $this->workflow->saveSettings($this->central,($this->scopedSettings)());
    $record=($this->pending)();
    $alerts=\App\Models\UserNotification::all();
    expect($alerts->pluck('user_id')->all())->toBe([6]);
    $alert=$alerts->first();
    expect($alert->message)->toContain('SOK DARA','2612345','Campus Registrar',$record->submitted_at->format('d-M-Y H:i'));
    $this->actingAs(User::find(3))->get('/notifications/'.$alert->id.'/open')->assertForbidden();
    expect($alert->fresh()->read_at)->toBeNull();
    $this->actingAs(User::find(6))->getJson('/notifications/unread')->assertOk()->assertJsonPath('skipping_pending',1)
        ->assertJsonPath('items.0.url',route('notifications.open',$alert));
    $this->get('/notifications/'.$alert->id.'/open')->assertRedirect('/students/skipping-grade/'.$record->id);
    expect($alert->fresh()->read_at)->not->toBeNull();
    $this->getJson('/notifications/unread')->assertJsonPath('unread',0)->assertJsonPath('skipping_pending',1);
    $page=$this->get('/students/skipping-grade/my-approvals?scope=approvals&status=pending')->assertOk()
        ->assertViewHas('records',fn($records)=>$records->total()===1)->assertSee('data-skipping-pending-badge',false);
    expect(substr_count($page->getContent(),'data-skipping-pending-badge'))->toBe(3);
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('Approve and Forward')->assertSee('Reject Request');
    foreach ([1,3,4,5,7,9] as $id) expect(app(\App\Services\SkippingGradeNotifications::class)->pendingCount(User::find($id)))->toBe(0);
});

it('grade skipping step completion clears all eligible reviewers and notifies each next step through VP',function () {
    ($this->workflowSetup)(); $settings=($this->workflowSettings)();
    $settings['campus_steps'][0]=['title'=>'KH Coordinator','reviewers'=>[['user_id'=>3,'grade_scope'=>'all'],['user_id'=>6,'grade_scope'=>'all']]];
    $this->workflow->saveSettings($this->central,$settings);
    $record=($this->pending)(); $notifications=app(\App\Services\SkippingGradeNotifications::class);
    $firstAlerts=\App\Models\UserNotification::all();
    expect($firstAlerts->pluck('user_id')->all())->toBe([3,6]);
    $notifications->awaiting($record);
    expect(\App\Models\UserNotification::count())->toBe(2);
    foreach ([3=>4,4=>9,9=>5] as $actor=>$next) {
        $this->workflow->review(User::find($actor),$record->fresh(),($this->stepData)($record->fresh()));
        expect($notifications->pendingCount(User::find($actor)))->toBe(0)->and($notifications->pendingCount(User::find($next)))->toBe(1);
        expect(\App\Models\UserNotification::whereNull('read_at')->pluck('user_id')->all())->toBe([$next]);
    }
    expect($notifications->pendingCount(User::find(6)))->toBe(0);
    $this->service->approve(User::find(5),$record->fresh(),$this->approval+($this->stepData)($record->fresh()));
    expect($notifications->pendingCount(User::find(5)))->toBe(0);
    $result=\App\Models\UserNotification::whereNull('read_at')->first();
    expect($result->user_id)->toBe(1)->and($result->title)->toBe('Grade Skipping Request Approved');
    foreach ($firstAlerts as $alert) expect($alert->fresh()->read_at)->not->toBeNull();
    $this->actingAs(User::find(3))->get('/notifications/'.$firstAlerts->first()->id.'/open')->assertRedirect('/students/skipping-grade/'.$record->id);
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('Approved by Vice President')->assertDontSee('Approve and Forward');
});

it('grade skipping rejection closes reviewer alerts and notifies creator and actual submitter',function () {
    ($this->workflowSetup)();
    DB::table('users')->insert(['id'=>10,'name'=>'Second Requester','active_campus_id'=>1]);
    DB::table('access_user_campuses')->insert(['user_id'=>10,'campus_id'=>1]);
    ($this->grant)(10,['students.view','view','requests','submit']);
    $record=$this->service->submit(User::find(10),($this->draft)(),now()->toDateString());
    $this->service->reject(User::find(3),$record,'Missing signed document',($this->stepData)($record));
    expect(\App\Models\UserNotification::whereNull('read_at')->pluck('user_id')->all())->toBe([1,10]);
    foreach (\App\Models\UserNotification::whereNull('read_at')->get() as $alert) {
        expect($alert->title)->toBe('Grade Skipping Request Rejected')->and($alert->message)->toContain('Missing signed document');
    }
    foreach ([3,4,9,5] as $id) expect(app(\App\Services\SkippingGradeNotifications::class)->pendingCount(User::find($id)))->toBe(0);
});

it('grade skipping reassignment moves pending alerts without notifying waiting reviewers',function () {
    ($this->workflowSetup)(); $record=($this->pending)(); $first=$record->approvalSteps[0];
    $this->workflow->reassign($this->central,$record,$record->approvalSteps[1]->id,['reviewer_id'=>6,'revision'=>1,'reason'=>'Future reviewer changed']);
    expect(\App\Models\UserNotification::count())->toBe(1);
    $this->workflow->reassign($this->central,$record,$first->id,['reviewer_id'=>6,'revision'=>1,'reason'=>'Current reviewer changed']);
    expect(\App\Models\UserNotification::whereNull('read_at')->pluck('user_id')->all())->toBe([6]);
    expect(app(\App\Services\SkippingGradeNotifications::class)->pendingCount(User::find(3)))->toBe(0)
        ->and(app(\App\Services\SkippingGradeNotifications::class)->pendingCount(User::find(6)))->toBe(1);
    $this->actingAs(User::find(3))->get('/students/skipping-grade/'.$record->id)->assertOk()->assertDontSee('Approve and Forward');
});

it('grade skipping notifications roll back with failed submission and failed decisions',function () {
    ($this->workflowSetup)(); $draft=($this->draft)();
    \App\Models\UserNotification::creating(function () { throw new RuntimeException('Notification write failed'); });
    try {
        expect(fn()=>$this->service->submit($this->campus,$draft,now()->toDateString()))->toThrow(RuntimeException::class);
    } finally { \App\Models\UserNotification::flushEventListeners(); }
    expect($draft->fresh()->status)->toBe('draft')->and($draft->approvalSteps()->count())->toBe(0)->and(\App\Models\UserNotification::count())->toBe(0);
    $record=$this->service->submit($this->campus,$draft->fresh(),now()->toDateString());
    $data=($this->stepData)($record); $data['approval_step_revision']++;
    expect(fn()=>$this->workflow->review(User::find(3),$record,$data))->toThrow(ValidationException::class);
    expect(\App\Models\UserNotification::count())->toBe(1)->and(\App\Models\UserNotification::first()->read_at)->toBeNull();
});

it('grade skipping current flow notifies permitted approvers and counts saved legacy requests after mode changes',function () {
    $record=($this->pending)();
    expect(\App\Models\UserNotification::whereNull('read_at')->pluck('user_id')->all())->toBe([2]);
    SkippingGradeSetting::current()->update(['approval_flow_mode'=>'sequential']);
    $alerts=app(\App\Services\SkippingGradeNotifications::class);
    expect($alerts->pendingCount($this->central))->toBe(1)->and($alerts->pendingCount($this->campus))->toBe(0);
    $this->actingAs($this->central)->get('/students/skipping-grade/my-approvals?scope=approvals&status=pending')
        ->assertOk()->assertViewHas('records',fn($records)=>$records->total()===1);
    DB::table('users')->where('id',2)->update(['status'=>0]);
    expect($alerts->pendingCount($this->central->fresh()))->toBe(0);
});

it('grade skipping backfill restores existing pending alerts once and skips completed requests',function () {
    ($this->workflowSetup)(); $record=($this->pending)();
    \App\Models\UserNotification::query()->delete();
    $this->artisan('grade-skipping:notify-pending')->assertSuccessful();
    expect(\App\Models\UserNotification::pluck('user_id')->all())->toBe([3]);
    \App\Models\UserNotification::first()->update(['read_at'=>now()]);
    $this->artisan('grade-skipping:notify-pending')->assertSuccessful();
    expect(\App\Models\UserNotification::count())->toBe(1)->and(\App\Models\UserNotification::first()->read_at)->not->toBeNull();
    $this->service->reject(User::find(3),$record,'Rejected',($this->stepData)($record));
    $count=\App\Models\UserNotification::count();
    $this->artisan('grade-skipping:notify-pending')->assertSuccessful();
    expect(\App\Models\UserNotification::count())->toBe($count);
});

it('request committee fields follow the saved flow while new requests follow the current system setting',function () {
    $legacy=($this->draft)(); ($this->workflowSetup)();
    $this->actingAs($this->campus)->get('/students/skipping-grade/create')->assertOk()
        ->assertDontSee('name="committee_names[0]"',false)->assertSee('Reviewers follow the campus and Central approval flow');
    $this->get('/students/skipping-grade/'.$legacy->id.'/edit')->assertOk()->assertSee('name="committee_names[0]"',false);
    $sequential=($this->olderStudent)();
    $this->workflow->saveSettings($this->central,($this->workflowSettings)('legacy'));
    $this->get('/students/skipping-grade/'.$sequential->id.'/edit')->assertOk()->assertDontSee('name="committee_names[0]"',false);
    $this->get('/students/skipping-grade/create')->assertOk()->assertSee('name="committee_names[0]"',false)
        ->assertSee('name="documents[]"',false)->assertSee('enctype="multipart/form-data"',false);
});

it('campus staff can attach documents when creating a request without draft update permission',function (string $mode) {
    if ($mode==='sequential') ($this->workflowSetup)();
    $update=DB::table('access_permissions')->where('code','student-skipping-grade.update')->value('id');
    DB::table('access_role_permissions')->where('role_id',1)->where('permission_id',$update)->delete();
    $files=[UploadedFile::fake()->create('birth-certificate.pdf',10,'application/pdf'),UploadedFile::fake()->image('result.png')];
    $this->actingAs($this->campus)->postJson('/students/skipping-grade',$this->data+['documents'=>$files])->assertOk();
    $record=StudentSkippingGrade::firstOrFail();
    expect($record->supporting_documents)->toHaveCount(2)->and($record->supporting_documents[0]['name'])->toBe('birth-certificate.pdf')
        ->and($record->supporting_documents[0]['uploaded_by'])->toBe(1)->and($record->approval_flow_mode)->toBe($mode);
    foreach ($record->supporting_documents as $document) Storage::disk('local')->assertExists($document['path']);
    $this->get('/students/skipping-grade/'.$record->id.'/attachments/0')->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
    $record=$this->service->submit($this->campus,$record,now()->toDateString());
    $this->actingAs($mode==='sequential'?User::find(3):$this->central)->get('/students/skipping-grade/'.$record->id.'/attachments/0')->assertOk();
    if ($mode==='sequential') $this->actingAs(User::find(7))->getJson('/students/skipping-grade/'.$record->id.'/attachments/0')->assertForbidden();
})->with(['legacy','sequential']);

it('editing a draft appends private supporting documents while preserving earlier files',function () {
    ($this->workflowSetup)();
    $this->actingAs($this->campus)->postJson('/students/skipping-grade',$this->data+['documents'=>[UploadedFile::fake()->create('original.pdf',10,'application/pdf')]])->assertOk();
    $record=StudentSkippingGrade::firstOrFail(); $original=$record->supporting_documents[0];
    $this->get('/students/skipping-grade/'.$record->id.'/edit')->assertOk()->assertSee('original.pdf')->assertSee('Attach Supporting Documents');
    $data=array_replace($this->data,['reason'=>'Updated evidence','documents'=>[UploadedFile::fake()->image('additional.jpg')]]);
    $this->postJson('/students/skipping-grade/'.$record->id.'/update',$data)->assertOk();
    expect($record->fresh()->supporting_documents)->toHaveCount(2)->and($record->fresh()->supporting_documents[0])->toBe($original)
        ->and($record->fresh()->reason)->toBe('Updated evidence');
    foreach ($record->fresh()->supporting_documents as $document) Storage::disk('local')->assertExists($document['path']);
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('original.pdf')->assertSee('additional.webp');
});

describe('supporting document archives',function () {
    beforeEach(function () {
        ($this->grant)(2,['document-archive','archive-backup','archive-delete','archive-restore']);
        $this->archives=app(\App\Services\SkippingGradeDocumentArchiveService::class);
        $this->archiveRecord=$this->service->saveDraft($this->campus,$this->data+['documents'=>[UploadedFile::fake()->create('Birth Certificate.pdf',10,'application/pdf')]]);
        Storage::disk('local')->put('grade-skipping/signed-requests/legacy.pdf','original signed request');
        $this->archiveRecord->update(['status'=>'approved','reference_number'=>'SG2324-001','signed_request_path'=>'grade-skipping/signed-requests/legacy.pdf','submitted_at'=>now(),'parent_signed_at'=>now(),'approved_at'=>now(),'effective_date'=>now()->toDateString(),'approved_by'=>2]);
        DB::table('tb_academic_year')->where('id',1)->update(['academic_year'=>'2023-2024','lifecycle_status'=>'finished']);
        DB::table('tb_academic_year')->insert([
            ['id'=>20,'academic_year'=>'2025-2026','lifecycle_status'=>'finished'],
            ['id'=>21,'academic_year'=>'2026-2027','lifecycle_status'=>'started'],
            ['id'=>22,'academic_year'=>'2027-2028','lifecycle_status'=>'pending'],
        ]);
        $this->buildArchive=function () {
            $archive=$this->archives->create($this->central,1,null);
            $this->archives->prepare($archive);
            foreach ($archive->parts()->get() as $part) $this->archives->build($part);
            return $archive->fresh();
        };
        $this->downloadArchive=function ($archive) {
            $this->actingAs($this->central);
            foreach ($archive->parts as $part) $this->get(route('student-skipping-grade.archive.download',[$archive,$part]))->assertOk();
            return $archive->fresh();
        };
    });

    it('creates a private verified ZIP with year approval student manifest and original bytes',function () {
        $archive=($this->buildArchive)(); $part=$archive->parts->first();
        expect($archive->status)->toBe('ready')->and($archive->files()->count())->toBe(2)->and($part->verified_at)->not->toBeNull();
        $zip=new ZipArchive; expect($zip->open(Storage::disk('local')->path($part->path)))->toBeTrue();
        $manifest=json_decode($zip->getFromName('manifest.json'),true);
        expect($manifest['academic_year'])->toBe('2023-2024')->and($manifest['files'][0]['student']['student_id'])->toBe('2612345');
        foreach ($manifest['files'] as $entry) {
            expect($entry['path'])->toStartWith('2023-2024/SG2324-001/')
                ->and(hash('sha256',$zip->getFromName($entry['path'])))->toBe($entry['sha256']);
        }
        $zip->close();
        $this->actingAs($this->central)->get('/students/skipping-grade/document-archive')->assertOk()->assertSee('Supporting Document Archive');
        $this->get(route('student-skipping-grade.archive.show',$archive))->assertOk()->assertSee('Document List')->assertSee('SG2324-001');
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ProcessSkippingDocumentArchive::class,fn($job)=>$job->operation==='prepare' && $job->connection==='document-archives');
    });

    it('requires separate permissions for archive viewing backup deletion and restore',function () {
        $archive=($this->buildArchive)(); $part=$archive->parts->first();
        $this->actingAs($this->campus)->getJson('/students/skipping-grade/document-archive')->assertForbidden();
        $this->postJson('/students/skipping-grade/document-archive',['academic_year_id'=>1])->assertForbidden();
        $this->getJson(route('student-skipping-grade.archive.download',[$archive,$part]))->assertForbidden();
        $this->postJson(route('student-skipping-grade.archive.delete',$archive),['external_backup_saved'=>1,'confirmation'=>$archive->reference])->assertForbidden();
        ($this->grant)(1,['document-archive']);
        $this->get('/students/skipping-grade/document-archive')->assertOk();
        $this->postJson('/students/skipping-grade/document-archive',['academic_year_id'=>1])->assertForbidden();
        $this->postJson(route('student-skipping-grade.archive.restore',[$archive,$part]),[])->assertForbidden();
    });

    it('excludes unfinished requests and respects campus scope',function () {
        foreach (['pending','draft','rejected'] as $status) {
            $record=$this->archiveRecord->replicate();
            $record->status=$status; $record->reference_number=null; $record->campus_id=2;
            $record->supporting_documents=[['path'=>'grade-skipping/attachments/other-'.$status.'.pdf','name'=>$status.'.pdf','mime'=>'application/pdf']];
            $record->signed_request_path=null; $record->save();
            Storage::disk('local')->put($record->supporting_documents[0]['path'],$status);
        }
        $archive=$this->archives->create($this->central,1,2); $this->archives->prepare($archive);
        expect($archive->files()->count())->toBe(1)->and($archive->files()->first()->original_name)->toBe('rejected.pdf');
    });

    it('protects the current and previous years and ignores future planning years',function () {
        expect($this->archives->canDeleteYear('2023-2024'))->toBeTrue()
            ->and($this->archives->canDeleteYear('2025-2026'))->toBeFalse()
            ->and($this->archives->canDeleteYear('2026-2027'))->toBeFalse()
            ->and($this->archives->canDeleteYear('2027-2028'))->toBeFalse();
        $archive=($this->buildArchive)(); $archive=($this->downloadArchive)($archive);
        foreach (['2025-2026','2026-2027'] as $year) {
            $archive->update(['academic_year'=>$year]);
            expect(fn()=>$this->archives->requestDeletion($archive,$this->central))->toThrow(ValidationException::class);
        }
        DB::table('tb_academic_year')->update(['lifecycle_status'=>'finished']);
        expect($this->archives->canDeleteYear('2023-2024'))->toBeFalse();
    });

    it('requires all downloads and explicit external backup confirmation before deletion',function () {
        $archive=($this->buildArchive)();
        expect(fn()=>$this->archives->requestDeletion($archive,$this->central))->toThrow(ValidationException::class);
        $this->actingAs($this->central)->postJson(route('student-skipping-grade.archive.delete',$archive),['confirmation'=>$archive->reference])->assertUnprocessable();
        ($this->downloadArchive)($archive);
        $this->postJson(route('student-skipping-grade.archive.delete',$archive),['external_backup_saved'=>1,'confirmation'=>'wrong-reference'])->assertUnprocessable();
        expect($archive->fresh()->status)->toBe('ready');
        Storage::disk('local')->assertExists($this->archiveRecord->supporting_documents[0]['path']);
    });

    it('blocks deletion after an academic year is renamed into a protected year',function () {
        $archive=($this->downloadArchive)(($this->buildArchive)());
        DB::table('tb_academic_year')->where('id',1)->update(['academic_year'=>'2025-2026']);
        expect(fn()=>$this->archives->requestDeletion($archive,$this->central))->toThrow(ValidationException::class);
        foreach ($archive->files as $file) Storage::disk('local')->assertExists($file->source_path);
    });

    it('stops deletion when either a ZIP or a live file changes',function (string $changed) {
        $archive=($this->downloadArchive)(($this->buildArchive)());
        $path=$changed==='zip'?$archive->parts->first()->path:$this->archiveRecord->supporting_documents[0]['path'];
        Storage::disk('local')->put($path,'changed bytes');
        $this->archives->requestDeletion($archive,$this->central);
        expect(fn()=>$this->archives->deleteDocuments($archive->fresh(),$this->central->id))->toThrow(RuntimeException::class);
        expect($archive->files()->whereNotNull('deleted_at')->count())->toBe(0);
        Storage::disk('local')->assertExists('grade-skipping/signed-requests/legacy.pdf');
    })->with(['zip','live']);

    it('archives files frees server space and restores exact bytes without changing approvals',function () {
        $archive=($this->downloadArchive)(($this->buildArchive)()); $part=$archive->parts->first();
        $external=Storage::disk('local')->path('external-backup.zip');
        copy(Storage::disk('local')->path($part->path),$external);
        $before=$this->archiveRecord->fresh()->only(['reference_number','status','student_snapshot','approval_snapshot','submitted_at']);
        $this->archives->requestDeletion($archive,$this->central);
        $this->archives->deleteDocuments($archive->fresh(),$this->central->id);
        foreach ($archive->files as $file) Storage::disk('local')->assertMissing($file->source_path);
        Storage::disk('local')->assertMissing($part->path);
        expect($archive->fresh()->status)->toBe('deleted')->and($this->archiveRecord->fresh()->only(array_keys($before)))->toEqual($before);
        $this->actingAs($this->central)->get('/students/skipping-grade/'.$this->archiveRecord->id)->assertOk()->assertSee('Archived')->assertSee($archive->reference);
        $this->get('/students/skipping-grade/'.$this->archiveRecord->id.'/attachments/0')->assertGone();
        $this->get('/students/skipping-grade/'.$this->archiveRecord->id.'/signed-request')->assertGone();
        $this->archives->restore($part->fresh(),new UploadedFile($external,'backup.zip','application/zip',null,true),$this->central);
        foreach ($archive->files as $file) expect(hash_file('sha256',Storage::disk('local')->path($file->source_path)))->toBe($file->sha256);
        expect($archive->fresh()->status)->toBe('restored')->and($this->archiveRecord->fresh()->supporting_documents[0])->not->toHaveKey('archived_at')
            ->and($this->archiveRecord->fresh()->signed_request_archived_at)->toBeNull();
        $this->get('/students/skipping-grade/'.$this->archiveRecord->id.'/attachments/0')->assertOk();
        expect(DB::table('skipping_document_archive_events')->where('archive_id',$archive->id)->pluck('action')->all())->toContain('backup_verified','external_backup_confirmed','documents_deleted','part_restored');
    });

    it('rejects a different or tampered restore ZIP without writing any file',function () {
        $archive=($this->downloadArchive)(($this->buildArchive)()); $part=$archive->parts->first();
        $this->archives->requestDeletion($archive,$this->central); $this->archives->deleteDocuments($archive->fresh(),$this->central->id);
        $bad=Storage::disk('local')->path('bad.zip'); $zip=new ZipArchive; $zip->open($bad,ZipArchive::CREATE);
        $zip->addFromString('../outside.php','untrusted'); $zip->close();
        expect(fn()=>$this->archives->restore($part->fresh(),new UploadedFile($bad,'bad.zip','application/zip',null,true),$this->central))->toThrow(RuntimeException::class);
        foreach ($archive->files as $file) Storage::disk('local')->assertMissing($file->source_path);
        expect($archive->fresh()->status)->toBe('deleted');
    });

    it('organizes existing documents by requested academic year and approval number safely',function () {
        $storage=app(\App\Services\SkippingGradeDocumentStorage::class);
        $originals=$this->archiveRecord->supporting_documents;
        $storage->organize($this->archiveRecord->id);
        $record=$this->archiveRecord->fresh();
        expect($record->supporting_documents[0]['path'])->toStartWith('grade-skipping/attachments/2023-2024/SG2324-001/')
            ->and($record->signed_request_path)->toStartWith('grade-skipping/attachments/2023-2024/SG2324-001/');
        Storage::disk('local')->assertMissing($originals[0]['path']);
        $this->archiveRecord=$record;
        $archive=($this->buildArchive)();
        $storage->organize($record->id);
        expect($record->fresh()->supporting_documents)->toBe($record->supporting_documents);
        $this->archives->verify($archive->parts->first());
    });

    it('fails a missing source backup without deleting other files and can retry',function () {
        Storage::disk('local')->delete($this->archiveRecord->supporting_documents[0]['path']);
        $archive=$this->archives->create($this->central,1,null);
        $job=new \App\Jobs\ProcessSkippingDocumentArchive('prepare',$archive->id,$this->central->id);
        try { $job->handle($this->archives); $this->fail('Expected a missing-source error'); }
        catch (ValidationException $e) { $job->failed($e); }
        expect($archive->fresh()->status)->toBe('failed')->and($archive->parts()->count())->toBe(0);
        Storage::disk('local')->assertExists('grade-skipping/signed-requests/legacy.pdf');
        $this->archives->requestVerification($archive->fresh(),$this->central);
        expect($archive->fresh()->status)->toBe('building');
    });

    it('requires every ZIP part and restores multipart archives independently',function () {
        $this->archives=new class extends \App\Services\SkippingGradeDocumentArchiveService { public const PART_BYTES=24; };
        Storage::disk('local')->put($this->archiveRecord->supporting_documents[0]['path'],'twenty bytes of data');
        $archive=($this->buildArchive)();
        expect($archive->parts)->toHaveCount(2);
        $first=$archive->parts[0]; $second=$archive->parts[1];
        $external=[];
        foreach ($archive->parts as $part) {
            $external[$part->id]=Storage::disk('local')->path('external-'.$part->id.'.zip');
            copy(Storage::disk('local')->path($part->path),$external[$part->id]);
        }
        $this->actingAs($this->central)->get(route('student-skipping-grade.archive.download',[$archive,$first]))->assertOk();
        expect(fn()=>$this->archives->requestDeletion($archive,$this->central))->toThrow(ValidationException::class);
        $this->get(route('student-skipping-grade.archive.download',[$archive,$second]))->assertOk();
        $this->archives->requestDeletion($archive,$this->central); $this->archives->deleteDocuments($archive->fresh(),$this->central->id);
        $this->archives->restore($first->fresh(),new UploadedFile($external[$first->id],'backup.zip','application/zip',null,true),$this->central);
        expect($archive->fresh()->status)->toBe('partially_restored');
        $this->archives->restore($second->fresh(),new UploadedFile($external[$second->id],'backup.zip','application/zip',null,true),$this->central);
        expect($archive->fresh()->status)->toBe('restored');
    });

    it('keeps live bytes and verified ZIPs recoverable if deletion metadata cannot be saved',function () {
        $archive=($this->downloadArchive)(($this->buildArchive)());
        $this->archives->requestDeletion($archive,$this->central);
        \App\Models\SkippingDocumentArchiveFile::updating(function ($file) { if ($file->isDirty('deleted_at')) throw new RuntimeException('Archive metadata write failed'); });
        $job=new \App\Jobs\ProcessSkippingDocumentArchive('delete',$archive->id,$this->central->id);
        try { $job->handle($this->archives); $this->fail('Expected deletion metadata failure'); }
        catch (RuntimeException $e) { $job->failed($e); }
        finally { \App\Models\SkippingDocumentArchiveFile::flushEventListeners(); }
        expect($archive->fresh()->status)->toBe('delete_failed')->and($this->archiveRecord->fresh()->supporting_documents[0])->not->toHaveKey('archived_at');
        foreach ($archive->files as $file) Storage::disk('local')->assertExists($file->source_path);
        Storage::disk('local')->assertExists($archive->parts->first()->path);
        $this->archives->requestDeletion($archive->fresh(),$this->central);
        $job->handle($this->archives);
        expect($archive->fresh()->status)->toBe('deleted');
    });

    it('queues a restore upload and rechecks the permission when the worker executes',function () {
        $archive=($this->downloadArchive)(($this->buildArchive)()); $part=$archive->parts->first();
        $external=Storage::disk('local')->path('external-for-queued-restore.zip');
        copy(Storage::disk('local')->path($part->path),$external);
        $this->archives->requestDeletion($archive,$this->central); $this->archives->deleteDocuments($archive->fresh(),$this->central->id);
        $upload=new UploadedFile($external,'backup.zip','application/zip',null,true);
        $this->actingAs($this->central)->postJson(route('student-skipping-grade.archive.restore',[$archive,$part]),['backup'=>$upload])->assertOk();
        expect($archive->fresh()->status)->toBe('restoring');
        $job=\Illuminate\Support\Facades\Queue::pushed(\App\Jobs\ProcessSkippingDocumentArchive::class)->first(fn($job)=>$job->operation==='restore');
        expect($job)->not->toBeNull();
        $job->handle($this->archives);
        expect($archive->fresh()->status)->toBe('restored');
        Storage::disk('local')->assertMissing($job->uploadPath);
        DB::table('users')->where('id',2)->update(['status'=>0]);
        expect(fn()=>$job->handle($this->archives))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    });
});

it('accepts a supporting document at the 25 MB limit',function () {
    $this->actingAs($this->campus)->postJson('/students/skipping-grade',$this->data+[
        'documents'=>[UploadedFile::fake()->create('evidence.pdf',25600,'application/pdf')],
    ])->assertOk();
    $record=StudentSkippingGrade::firstOrFail();
    expect($record->supporting_documents)->toHaveCount(1);
    Storage::disk('local')->assertExists($record->supporting_documents[0]['path']);
});

it('request form uploads validate file type size and batch limits before saving a draft',function (string $failure) {
    $documents=match($failure) {
        'type'=>[UploadedFile::fake()->create('program.exe',10,'application/octet-stream')],
        'size'=>[UploadedFile::fake()->create('large.pdf',25601,'application/pdf')],
        'batch'=>array_map(fn($i)=>UploadedFile::fake()->create('evidence-'.$i.'.pdf',1,'application/pdf'),range(1,11)),
    };
    $this->actingAs($this->campus)->postJson('/students/skipping-grade',$this->data+['documents'=>$documents])->assertUnprocessable()
        ->assertJsonValidationErrors($failure==='batch'?'documents':'documents.0');
    expect(StudentSkippingGrade::count())->toBe(0)->and(Storage::disk('local')->allFiles('grade-skipping/attachments'))->toBe([]);
})->with(['type','size','batch']);

it('request form upload failure rolls back draft data and removes newly stored files',function () {
    StudentSkippingGrade::updating(function ($record) { if ($record->isDirty('supporting_documents')) throw new RuntimeException('Unable to persist attachments'); });
    try {
        expect(fn()=>$this->service->saveDraft($this->campus,$this->data+['documents'=>[UploadedFile::fake()->create('evidence.pdf',10,'application/pdf')]]))
            ->toThrow(RuntimeException::class,'Unable to persist attachments');
    } finally { StudentSkippingGrade::flushEventListeners(); }
    expect(StudentSkippingGrade::count())->toBe(0)->and(Storage::disk('local')->allFiles('grade-skipping/attachments'))->toBe([]);
});

it('draft edits reject excess attachments and submitted requests cannot receive files through the edit endpoint',function () {
    $files=fn($start)=>array_map(fn($i)=>UploadedFile::fake()->create('evidence-'.$i.'.pdf',1,'application/pdf'),range($start,$start+9));
    $record=$this->service->saveDraft($this->campus,$this->data+['documents'=>$files(1)]);
    $record=$this->service->saveDraft($this->campus,$this->data+['documents'=>$files(11)],$record);
    $newFile=[UploadedFile::fake()->create('extra.pdf',1,'application/pdf')];
    $this->actingAs($this->campus)->postJson('/students/skipping-grade/'.$record->id.'/update',array_replace($this->data,['reason'=>'Should roll back','documents'=>$newFile]))
        ->assertUnprocessable()->assertJsonValidationErrors('documents');
    expect($record->fresh()->supporting_documents)->toHaveCount(20)->and($record->fresh()->reason)->toBe($this->data['reason']);
    expect(Storage::disk('local')->allFiles('grade-skipping/attachments'))->toHaveCount(20);
    $record=$this->service->submit($this->campus,$record->fresh(),now()->toDateString());
    ($this->grant)(2,['central-update']);
    $this->actingAs($this->central)->postJson('/students/skipping-grade/'.$record->id.'/update',$this->data+['documents'=>$newFile])->assertForbidden();
    expect($record->fresh()->supporting_documents)->toHaveCount(20);
});

it('combined documents submission stores marked files and advances only the saved flow',function (string $mode) {
    ($this->workflowSetup)();
    if ($mode==='legacy') $this->workflow->saveSettings($this->central,($this->workflowSettings)('legacy'));
    $record=$this->service->saveDraft($this->campus,$this->data+['documents'=>[UploadedFile::fake()->create('saved.pdf',10,'application/pdf')]]);
    $this->actingAs($this->campus)->get('/students/skipping-grade/'.$record->id)->assertOk()
        ->assertSee('Supporting Documents &amp; Submission',false)->assertSee('Save Documents')
        ->assertSee('Parent-signed Request')->assertDontSee('Submit Parent-signed Request')->assertDontSee('name="signed_request"',false);
    $this->postJson('/students/skipping-grade/'.$record->id.'/submit',[
        'parent_signed'=>1,'parent_signed_date'=>now()->toDateString(),'document_markers'=>1,'document_count'=>1,
        'documents'=>[UploadedFile::fake()->create('parent-signed.pdf',10,'application/pdf'),UploadedFile::fake()->image('assessment.png')],
        'parent_signed_documents'=>[0,1],
    ])->assertOk();
    $record->refresh();
    expect($record->status)->toBe('pending')->and($record->supporting_documents)->toHaveCount(3)
        ->and(array_column($record->supporting_documents,'is_parent_signed'))->toBe([true,true,false])
        ->and($record->parent_signed_at->toDateString())->toBe(now()->toDateString())->and($record->signed_request_path)->toBeNull()
        ->and(StudentEnrollment::find(1)->grade_id)->toBe(1);
    foreach ($record->supporting_documents as $document) Storage::disk('local')->assertExists($document['path']);
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('parent-signed.pdf')->assertSee('Parent-signed Request')
        ->assertDontSee('Save Documents')->assertDontSee('data-skipping-documents-upload',false);
    if ($mode==='sequential') expect($record->approvalSteps()->where('status','pending')->count())->toBe(1);
})->with(['legacy','sequential']);

it('saving documents and updating parent signed markers does not require signing or submit the draft',function () {
    ($this->workflowSetup)(); $record=($this->draft)();
    $this->actingAs($this->campus)->postJson('/students/skipping-grade/'.$record->id.'/attachments',[
        'document_markers'=>1,'document_count'=>0,'documents'=>[UploadedFile::fake()->create('signed.pdf',10,'application/pdf')],'parent_signed_documents'=>[0],
    ])->assertOk();
    $path=$record->fresh()->supporting_documents[0]['path'];
    expect($record->fresh()->status)->toBe('draft')->and($record->fresh()->supporting_documents[0]['is_parent_signed'])->toBeTrue()
        ->and($record->fresh()->parent_signed_at)->toBeNull()->and($record->approvalSteps()->count())->toBe(0);
    $this->postJson('/students/skipping-grade/'.$record->id.'/attachments',['document_markers'=>1,'document_count'=>1])->assertOk();
    expect($record->fresh()->supporting_documents[0]['is_parent_signed'])->toBeFalse();
    Storage::disk('local')->assertExists($path);
    $this->postJson('/students/skipping-grade/'.$record->id.'/update',$this->data+['document_markers'=>1,'document_count'=>1,'parent_signed_documents'=>[0]])->assertOk();
    expect($record->fresh()->supporting_documents[0]['is_parent_signed'])->toBeTrue()->and($record->fresh()->status)->toBe('draft');
});

it('combined submission validates the parent confirmation date and files before saving attachments',function (string $missing) {
    ($this->workflowSetup)(); $record=($this->draft)();
    $data=['parent_signed'=>1,'parent_signed_date'=>now()->toDateString(),'documents'=>[UploadedFile::fake()->create('signed.pdf',10,'application/pdf')]];
    if ($missing==='file') $data['documents']=[UploadedFile::fake()->create('unsafe.exe',10,'application/octet-stream')];
    else unset($data[$missing]);
    $this->actingAs($this->campus)->postJson('/students/skipping-grade/'.$record->id.'/submit',$data)->assertUnprocessable()
        ->assertJsonValidationErrors($missing==='file'?'documents.0':$missing);
    expect($record->fresh()->status)->toBe('draft')->and($record->fresh()->supporting_documents)->toBeNull()->and($record->approvalSteps()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles('grade-skipping/attachments'))->toBe([]);
})->with(['parent_signed','parent_signed_date','file']);

it('failed combined submission removes new documents and captured reviewer assets while preserving saved files',function () {
    ($this->workflowSetup)();
    $record=$this->service->saveDraft($this->campus,$this->data+['documents'=>[UploadedFile::fake()->create('saved.pdf',10,'application/pdf')]]);
    $old=$record->supporting_documents; $filesBefore=Storage::disk('local')->allFiles(); sort($filesBefore);
    StudentSkippingGrade::updating(function ($request) { if ($request->isDirty('status') && $request->status==='pending') throw new RuntimeException('Submission failed'); });
    try {
        expect(fn()=>$this->service->submit($this->campus,$record,now()->toDateString(),null,[UploadedFile::fake()->create('new-signed.pdf',10,'application/pdf')],[1],1))
            ->toThrow(RuntimeException::class,'Submission failed');
    } finally { StudentSkippingGrade::flushEventListeners(); }
    expect($record->fresh()->status)->toBe('draft')->and($record->fresh()->supporting_documents)->toBe($old)
        ->and($record->fresh()->workflow_signer_snapshot)->toBeNull()->and($record->approvalSteps()->count())->toBe(0);
    $filesAfter=Storage::disk('local')->allFiles(); sort($filesAfter); expect($filesAfter)->toBe($filesBefore);
});

it('invalid parent signed document indexes roll back new files and leave the draft untouched',function () {
    ($this->workflowSetup)(); $record=($this->draft)();
    $this->actingAs($this->campus)->postJson('/students/skipping-grade/'.$record->id.'/submit',[
        'parent_signed'=>1,'parent_signed_date'=>now()->toDateString(),'document_markers'=>1,'document_count'=>0,
        'documents'=>[UploadedFile::fake()->create('one.pdf',10,'application/pdf')],'parent_signed_documents'=>[1],
    ])->assertUnprocessable()->assertJsonValidationErrors('parent_signed_documents');
    expect($record->fresh()->status)->toBe('draft')->and($record->fresh()->supporting_documents)->toBeNull();
    expect(Storage::disk('local')->allFiles('grade-skipping/attachments'))->toBe([]);
});

it('combined submission preserves draft edit permissions while still allowing submit only users to submit saved documents',function () {
    ($this->workflowSetup)();
    $record=$this->service->saveDraft($this->campus,$this->data+['documents'=>[UploadedFile::fake()->create('saved.pdf',10,'application/pdf')]]);
    $update=DB::table('access_permissions')->where('code','student-skipping-grade.update')->value('id');
    DB::table('access_role_permissions')->where('role_id',1)->where('permission_id',$update)->delete();
    $this->actingAs($this->campus)->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('Submit for Approval')->assertDontSee('data-skipping-documents-upload',false);
    $submit=['parent_signed'=>1,'parent_signed_date'=>now()->toDateString()];
    $this->postJson('/students/skipping-grade/'.$record->id.'/submit',$submit+['documents'=>[UploadedFile::fake()->create('forbidden.pdf',10,'application/pdf')]])->assertForbidden();
    $this->postJson('/students/skipping-grade/'.$record->id.'/submit',$submit+['document_markers'=>1,'parent_signed_documents'=>[0]])->assertForbidden();
    expect($record->fresh()->status)->toBe('draft')->and($record->fresh()->supporting_documents)->toHaveCount(1);
    $this->postJson('/students/skipping-grade/'.$record->id.'/submit',$submit)->assertOk();
    expect($record->fresh()->status)->toBe('pending');
});

it('stale document selection cannot mark a different file or submit the request',function () {
    ($this->workflowSetup)();
    $record=$this->service->saveDraft($this->campus,$this->data+['document_markers'=>1,'documents'=>[UploadedFile::fake()->create('saved.pdf',10,'application/pdf')],'parent_signed_documents'=>[0]]);
    $old=$record->supporting_documents;
    $this->actingAs($this->campus)->postJson('/students/skipping-grade/'.$record->id.'/submit',[
        'parent_signed'=>1,'parent_signed_date'=>now()->toDateString(),'document_markers'=>1,'document_count'=>0,
        'documents'=>[UploadedFile::fake()->create('queued.pdf',10,'application/pdf')],'parent_signed_documents'=>[0],
    ])->assertUnprocessable()->assertJsonValidationErrors('documents');
    expect($record->fresh()->status)->toBe('draft')->and($record->fresh()->supporting_documents)->toBe($old);
    expect(Storage::disk('local')->allFiles(dirname($record->fresh()->supporting_documents[0]['path'])))->toHaveCount(1);
});

it('campus staff with no selected campus can open the menu create and submit within assigned campus',function () {
    ($this->workflowSetup)();
    DB::table('users')->where('id',1)->update(['active_campus_id'=>null]);
    $user=User::find(1);
    $this->actingAs($user)->get('/students/skipping-grade')->assertOk();
    $this->get('/students/skipping-grade/create')->assertOk();
    $this->postJson('/students/skipping-grade',$this->data)->assertOk();
    $record=StudentSkippingGrade::firstOrFail();
    $this->postJson('/students/skipping-grade/'.$record->id.'/submit',['parent_signed'=>1,'parent_signed_date'=>now()->toDateString()])->assertOk();
    expect($record->fresh()->status)->toBe('pending')->and($record->approvalSteps->pluck('reviewer_id')->all())->toBe([3,4,9,5]);
    DB::table('tb_student_enrollment')->insert(['id'=>2,'student_id'=>1,'campus_id'=>2,'academic_year_id'=>1,'grade_id'=>1,'class_id'=>1]);
    $this->postJson('/students/skipping-grade',array_replace($this->data,['enrollment_id'=>2]))->assertForbidden();
    $this->getJson('/students/skipping-grade/approval-flow')->assertForbidden();
});

it('campus reviewers with no selected campus can open their inbox and decide only their current step',function () {
    ($this->workflowSetup)(); $record=($this->pending)();
    DB::table('access_roles')->insert(['id'=>3,'code'=>'campus-reviewer','name'=>'Campus Reviewer']);
    foreach (DB::table('access_permissions')->whereIn('code',['students.view','student-skipping-grade.view'])->pluck('id') as $permission) {
        DB::table('access_role_permissions')->insert(['role_id'=>3,'permission_id'=>$permission]);
    }
    foreach ([3,4] as $userId) {
        DB::table('access_user_permission_overrides')->where('user_id',$userId)->delete();
        DB::table('access_user_roles')->insert(['user_id'=>$userId,'role_id'=>3,'campus_id'=>1]);
        DB::table('users')->where('id',$userId)->update(['active_campus_id'=>null]);
    }
    $this->actingAs(User::find(3))->get('/students/skipping-grade')->assertRedirect('/students/skipping-grade/my-approvals');
    $this->get('/students/skipping-grade/my-approvals')->assertOk()->assertSee('SOK DARA');
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('Approve and Forward');
    $data=($this->stepData)($record);
    $this->actingAs(User::find(4))->postJson('/students/skipping-grade/'.$record->id.'/review',$data)->assertForbidden();
    $this->actingAs(User::find(3))->postJson('/students/skipping-grade/'.$record->id.'/review',$data)->assertOk();
    expect($record->fresh()->approvalSteps->first()->acted_by)->toBe(3);
    $this->postJson('/students/skipping-grade/'.$record->id.'/review',($this->stepData)($record->fresh()))->assertForbidden();
});

it('menu permission fallback uses the primary active assignment and requires explicit module permissions',function () {
    DB::table('access_user_campuses')->where('user_id',1)->update(['is_primary'=>false]);
    DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>2,'is_primary'=>true]);
    DB::table('users')->where('id',1)->update(['active_campus_id'=>null]);
    $user=User::find(1);
    expect(StudentSkippingGradePermissions::permissionCampus($user))->toBe(2);
    $this->actingAs($user)->getJson('/students/skipping-grade')->assertForbidden();
    DB::table('access_user_roles')->insert(['user_id'=>1,'role_id'=>1,'campus_id'=>2]);
    $this->get('/students/skipping-grade')->assertOk();
    DB::table('tb_school_info')->where('id',2)->update(['status'=>0]);
    expect(StudentSkippingGradePermissions::permissionCampus($user))->toBe(1);
    $this->get('/students/skipping-grade')->assertOk();
    $permission=DB::table('access_permissions')->where('code','student-skipping-grade.view')->value('id');
    DB::table('access_role_permissions')->where('role_id',1)->where('permission_id',$permission)->delete();
    $this->getJson('/students/skipping-grade')->assertForbidden();
});

it('campus assignment restricts request creation despite global access',function (string $access) {
    if ($access==='user') DB::table('users')->where('id',1)->update(['is_global'=>true]);
    else DB::table('access_roles')->where('id',1)->update(['is_global'=>true]);
    $user=User::find(1);
    expect($user->canAccessCampus(2))->toBeTrue();
    DB::table('tb_student_enrollment')->where('id',1)->update(['campus_id'=>2]);
    $this->actingAs($user)->postJson('/students/skipping-grade',$this->data)->assertForbidden();
    expect(StudentSkippingGrade::count())->toBe(0);
    DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>2]);
    $this->postJson('/students/skipping-grade',$this->data)->assertOk();
    $record=StudentSkippingGrade::firstOrFail();
    DB::table('access_user_campuses')->where('user_id',1)->where('campus_id',2)->delete();
    $this->postJson('/students/skipping-grade/'.$record->id.'/submit',['parent_signed'=>1,'parent_signed_date'=>now()->toDateString()])->assertForbidden();
})->with(['user','role']);

it('reviewer dropdowns use actual campus assignments including central and final approvers',function () {
    ($this->workflowSetup)();
    DB::table('users')->where('id',8)->update(['is_global'=>true]);
    expect(User::find(8)->canAccessCampus(1))->toBeTrue();
    $page=$this->actingAs($this->central)->get('/students/skipping-grade/approval-flow?campus_id=1')->assertOk();
    expect($page->viewData('campusReviewerOptions')->pluck('id')->all())->toEqualCanonicalizing([1,2,3,4,6]);
    expect($page->viewData('centralReviewerOptions')->pluck('id')->all())->toEqualCanonicalizing([5,7,9]);
    $page=$this->get('/students/skipping-grade/approval-flow?campus_id=2')->assertOk();
    expect($page->viewData('campusReviewerOptions')->pluck('id')->all())->toEqualCanonicalizing([7,8]);
    $html=$page->getContent();
    preg_match('/<select id="flowVP"[^>]*>(.*?)<\/select>/s',$html,$matches);
    expect($matches[1])->toContain('Vice President','Central Reviewer')->not->toContain('Campus VSP','DNG Coordinator','Central Office</option>');
});

it('reviewer eligibility checks assigned campus permissions before the reviewer has logged in',function () {
    ($this->workflowSetup)();
    DB::table('access_roles')->insert(['id'=>3,'code'=>'coordinator','name'=>'Coordinator']);
    foreach (DB::table('access_permissions')->whereIn('code',['students.view','student-skipping-grade.view'])->pluck('id') as $id) {
        DB::table('access_role_permissions')->insert(['role_id'=>3,'permission_id'=>$id]);
    }
    foreach ([3=>1,9=>3] as $userId=>$campusId) {
        DB::table('access_user_permission_overrides')->where('user_id',$userId)->delete();
        DB::table('access_user_roles')->insert(['user_id'=>$userId,'role_id'=>3,'campus_id'=>$campusId]);
        DB::table('users')->where('id',$userId)->update(['active_campus_id'=>null]);
    }
    expect(\App\Support\StudentSkippingGradePermissions::allows(User::find(3),'view'))->toBeTrue();
    $page=$this->actingAs($this->central)->get('/students/skipping-grade/approval-flow?campus_id=1')->assertOk();
    expect($page->viewData('campusReviewerOptions')->pluck('id')->all())->toContain(3);
    expect($page->viewData('centralReviewerOptions')->pluck('id')->all())->toContain(9);
    $this->postJson('/students/skipping-grade/approval-flow',($this->workflowSettings)())->assertOk();
    $record=($this->pending)();
    expect($record->approvalSteps->pluck('reviewer_id')->all())->toBe([3,4,9,5]);
    // Selection of another campus in a previous session must not hide assigned reviewers either.
    DB::table('users')->whereIn('id',[3,9])->update(['active_campus_id'=>2]);
    expect($this->workflow->reviewerIsEligible(User::find(3),1))->toBeTrue()
        ->and($this->workflow->reviewerIsEligible(User::find(9),null))->toBeTrue();
});

it('reviewer eligibility cannot use permissions from a different campus',function () {
    ($this->workflowSetup)();
    DB::table('access_roles')->insert(['id'=>3,'code'=>'other-campus-viewer','name'=>'Other Campus Viewer']);
    foreach (DB::table('access_permissions')->whereIn('code',['students.view','student-skipping-grade.view'])->pluck('id') as $id) {
        DB::table('access_role_permissions')->insert(['role_id'=>3,'permission_id'=>$id]);
    }
    DB::table('access_user_permission_overrides')->where('user_id',6)->delete();
    DB::table('access_user_roles')->insert(['user_id'=>6,'role_id'=>3,'campus_id'=>2]);
    DB::table('users')->where('id',6)->update(['active_campus_id'=>2]);
    expect(\App\Support\StudentSkippingGradePermissions::allows(User::find(6),'view'))->toBeTrue();
    $page=$this->actingAs($this->central)->get('/students/skipping-grade/approval-flow?campus_id=1')->assertOk();
    expect($page->viewData('campusReviewerOptions')->pluck('id')->all())->not->toContain(6);
    $settings=($this->workflowSettings)(); $settings['campus_steps'][0]['user_id']=6;
    $this->postJson('/students/skipping-grade/approval-flow',$settings)->assertUnprocessable()->assertJsonValidationErrors('campus_steps');
});

it('approval position dropdowns list active system positions and preserve existing custom step titles',function () {
    ($this->workflowSetup)();
    $page=$this->actingAs($this->central)->get('/students/skipping-grade/approval-flow?campus_id=1')->assertOk();
    $html=$page->getContent();
    preg_match('/<select id="campus_steps-0-member-0-title"[^>]*>(.*?)<\/select>/s',$html,$matches);
    expect($matches[1])->toContain('KH-Coordinator','Vice School Principal','School Principal','value="KH Coordinator" selected')
        ->not->toContain('Inactive Position');
    $settings=($this->workflowSettings)(); $settings['campus_steps'][0]['title']='KH-Coordinator';
    $this->postJson('/students/skipping-grade/approval-flow',$settings)->assertOk();
    expect(\App\Models\SkippingGradeCampusSetting::where('campus_id',1)->first()->approval_steps[0]['title'])->toBe('KH-Coordinator');
});

it('central reviewers save separate roles render every role selector and retain roles in approval history',function () {
    ($this->workflowSetup)(); $settings=($this->workflowSettings)();
    $settings['central_steps']=[['title'=>'Registrar Director','reviewers'=>[
        ['user_id'=>9,'title'=>'Registrar Director','grade_scope'=>'all'],
        ['user_id'=>7,'title'=>'International Program Director','grade_scope'=>'all'],
    ]]];
    $this->actingAs($this->central)->postJson('/students/skipping-grade/approval-flow',$settings)->assertOk();
    expect(array_column(SkippingGradeSetting::current()->central_approval_steps[0]['reviewers'],'title'))
        ->toBe(['Registrar Director','International Program Director']);
    $page=$this->get('/students/skipping-grade/approval-flow?campus_id=1')->assertOk();
    $html=$page->getContent();
    foreach ([0=>'Registrar Director',1=>'International Program Director'] as $index=>$title) {
        preg_match('/<select id="central_steps-0-member-'.$index.'-title"[^>]*>(.*?)<\/select>/s',$html,$matches);
        expect($matches[1])->toContain('value="'.$title.'" selected');
    }
    // The template cloned by Add Reviewer also contains an independent, initially empty selector.
    preg_match('/<select id="central_steps-0-member-new-title"[^>]*>(.*?)<\/select>/s',$html,$matches);
    expect($matches[1])->toContain('Select Position / Role')->not->toContain('selected');
    $record=($this->pending)(); $step=$record->approvalSteps[2];
    expect(array_column($step->reviewerPeople(),'title'))->toBe(['Registrar Director','International Program Director']);
    $settings['revision']=$this->workflow->revision(); $settings['central_steps'][0]['reviewers'][1]['title']='School Principal';
    $this->postJson('/students/skipping-grade/approval-flow',$settings)->assertOk();
    expect(array_column($step->fresh()->reviewerPeople(),'title'))->toBe(['Registrar Director','International Program Director']);
    $this->workflow->reassign($this->central,$record,$step->id,['reviewer_id'=>5,'member_id'=>$step->reviewers[1]->id,'revision'=>1,'reason'=>'Central appointment changed']);
    expect($step->fresh()->reviewers[1]->reviewer_snapshot['title'])->toBe('International Program Director')
        ->and($step->fresh()->reassignments->first()->replacement_reviewer['title'])->toBe('International Program Director');
    foreach ([3,4,5] as $id) $this->workflow->review(User::find($id),$record->fresh(),($this->stepData)($record->fresh()));
    expect($step->fresh()->actor_snapshot['title'])->toBe('International Program Director')
        ->and($record->fresh()->approvalSteps->last()->status)->toBe('pending');
});

it('campus reviewers retain their individual role when current grade matching selects another member',function () {
    ($this->workflowSetup)(); $settings=($this->scopedSettings)();
    $settings['campus_steps'][0]['reviewers'][0]['title']='KH-Coordinator';
    $settings['campus_steps'][0]['reviewers'][1]['title']='Vice School Principal';
    $this->actingAs($this->central)->postJson('/students/skipping-grade/approval-flow',$settings)->assertOk();
    $record=($this->pending)();
    expect($record->approvalSteps->first()->reviewer_snapshot['title'])->toBe('Vice School Principal');
    $this->workflow->review(User::find(6),$record,($this->stepData)($record));
    expect($record->fresh()->approvalSteps->first()->actor_snapshot['title'])->toBe('Vice School Principal');
});

it('starts the approval timeline with the saved requester and submission time for reviewers',function () {
    ($this->workflowSetup)();
    $photo=UploadedFile::fake()->image('requester.png');
    Storage::disk('public')->put('requester.png',file_get_contents($photo->getRealPath()));
    $this->campus->update(['photo_path'=>'requester.png']);
    $record=($this->pending)();
    $requester=$record->student_snapshot['requester'];
    expect($requester['user_id'])->toBe(1)->and($requester['name'])->toBe('Campus Registrar')
        ->and($requester['photo'])->toStartWith('data:image/jpeg;base64,');
    $this->campus->update(['name'=>'Changed Staff Name','photo_path'=>null]);
    Storage::disk('public')->delete('requester.png');
    $page=$this->actingAs(User::find(3))->get('/students/skipping-grade/'.$record->id)->assertOk();
    preg_match_all('/<li class="skipping-workflow-node[^\"]*">(.*?)<\/li>/s',$page->getContent(),$matches);
    expect($matches[1])->toHaveCount(5)->and($matches[1][0])->toContain('<strong>Campus Registrar</strong>',
        '<span>Requester</span>','src="'.$requester['photo'].'"','>Submitted</span>',
        $record->submitted_at->format('d-M-Y H:i'))->not->toContain('Changed Staff Name','Submitted by','>BCH</small>');
    expect($matches[1][1])->toContain('KH Coordinator','Awaiting Approval');
    expect($record->fresh()->approvalSteps)->toHaveCount(4);
    foreach (['/students/skipping-grade','/students/skipping-grade/my-approvals?status=all'] as $url) {
        $page=$this->actingAs(str_contains($url,'my-approvals')?User::find(3):$this->campus)->get($url)->assertOk();
        preg_match('/<div class="skipping-workflow-reviewer"[^>]*>(.*?)<\/div>/s',$page->getContent(),$card);
        expect($card[1])->toContain('Campus Registrar: Requester','src="'.$requester['photo'].'"',
            'datetime="'.$record->submitted_at->toIso8601String().'"','>'.$record->submitted_at->format('d-M-Y').'</time>')
            ->not->toContain('Changed Staff Name');
    }
});

it('shows the actual submitter when another assigned staff member submits the request',function () {
    ($this->workflowSetup)();
    DB::table('users')->insert(['id'=>10,'name'=>'Second Campus Registrar','active_campus_id'=>1]);
    DB::table('access_user_campuses')->insert(['user_id'=>10,'campus_id'=>1]);
    DB::table('access_user_roles')->insert(['user_id'=>10,'role_id'=>1,'campus_id'=>1]);
    $record=$this->service->submit(User::find(10),($this->draft)(),now()->toDateString());
    expect($record->created_by)->toBe(1)->and($record->submitted_by)->toBe(10)
        ->and($record->student_snapshot['submitter']['name'])->toBe('Second Campus Registrar');
    User::find(10)->update(['name'=>'Changed Submitter']);
    $this->actingAs(User::find(9))->get('/students/skipping-grade/'.$record->id)->assertOk()
        ->assertSee('Campus Registrar')->assertSee('Submitted by Second Campus Registrar')->assertDontSee('Changed Submitter');
});

it('displays the recorded requester and submission time on older requests without identity snapshots',function () {
    ($this->workflowSetup)(); $record=($this->pending)();
    $snapshot=$record->student_snapshot;
    unset($snapshot['requester'],$snapshot['submitter']);
    $record->update(['student_snapshot'=>$snapshot]);
    $page=$this->actingAs(User::find(3))->get('/students/skipping-grade/'.$record->id)->assertOk();
    preg_match('/<li class="skipping-workflow-node skipping-workflow-submitted">(.*?)<\/li>/s',$page->getContent(),$matches);
    expect($matches[1])->toContain('Campus Registrar','Requester',$record->submitted_at->format('d-M-Y H:i'));
    $this->actingAs($this->campus)->get('/students/skipping-grade')->assertOk()
        ->assertSee('title="Campus Registrar · Requester · Submitted · '.$record->submitted_at->format('d-M-Y H:i').'"',false);
});

it('preserves saved requester identities when a submitted current-flow request is edited',function () {
    $record=($this->pending)(); $before=$record->student_snapshot;
    ($this->grant)(2,['central-update']);
    $this->campus->update(['name'=>'Changed Staff Name']);
    $record=$this->service->saveDraft($this->central,array_replace($this->data,['reason'=>'Corrected reason']),$record);
    expect($record->student_snapshot['requester'])->toBe($before['requester'])
        ->and($record->student_snapshot['submitter'])->toBe($before['submitter'])
        ->and($record->reason)->toBe('Corrected reason')->and($record->status)->toBe('pending');
});

it('central approval tracking displays each saved reviewer with their own photo and role in details and lists',function () {
    ($this->workflowSetup)(); $settings=($this->workflowSettings)();
    $settings['central_steps']=[['title'=>'Registrar Director','reviewers'=>[
        ['user_id'=>9,'title'=>'Registrar Director','grade_scope'=>'all'],
        ['user_id'=>7,'title'=>'International Program Director','grade_scope'=>'all'],
    ]]];
    foreach ([9,7] as $id) {
        $photo=UploadedFile::fake()->image('reviewer-'.$id.'.png');
        Storage::disk('public')->put('reviewer-'.$id.'.png',file_get_contents($photo->getRealPath()));
        User::find($id)->update(['photo_path'=>'reviewer-'.$id.'.png']);
    }
    $this->workflow->saveSettings($this->central,$settings); $record=($this->pending)();
    $step=$record->approvalSteps[2];
    expect(array_column($step->trackingEntries(),'name'))->toBe(['Central Reviewer','Other Campus Viewer']);
    // Later staff changes must not change the people or roles in the saved request.
    User::find(9)->update(['name'=>'New Staff Name','photo_path'=>null]);
    $page=$this->actingAs($this->campus)->get('/students/skipping-grade/'.$record->id)->assertOk();
    preg_match_all('/<li class="skipping-workflow-node[^\"]*">(.*?)<\/li>/s',$page->getContent(),$matches);
    expect($matches[1])->toHaveCount(6);
    foreach ([0=>['Central Reviewer','Registrar Director'],1=>['Other Campus Viewer','International Program Director']] as $index=>$details) {
        $card=$matches[1][3+$index];
        expect($card)->toContain('<strong>'.$details[0].'</strong>','<span>'.$details[1].'</span>','src="'.$step->reviewerPeople()[$index]['photo'].'"')
            ->not->toContain($index===0?'Other Campus Viewer':'Central Reviewer,');
    }
    foreach (['/students/skipping-grade','/students/skipping-grade/my-approvals?status=all'] as $url) {
        $page=$this->actingAs(str_contains($url,'my-approvals')?User::find(7):$this->campus)->get($url)->assertOk();
        expect($page->getContent())->toContain('title="Central Reviewer · Registrar Director · Waiting"','title="Other Campus Viewer · International Program Director · Waiting"')
            ->not->toContain('title="Central Reviewer, Other Campus Viewer');
    }
});

it('central tracking records the actual decision separately from other members in the same step',function (string $decision) {
    ($this->workflowSetup)(); $settings=($this->workflowSettings)();
    $settings['central_steps']=[['title'=>'Central Reviewer','reviewers'=>[
        ['user_id'=>9,'title'=>'Registrar Director','grade_scope'=>'all'],
        ['user_id'=>7,'title'=>'International Program Director','grade_scope'=>'all'],
    ]]];
    $this->workflow->saveSettings($this->central,$settings); $record=($this->pending)();
    foreach ([3,4] as $id) $this->workflow->review(User::find($id),$record->fresh(),($this->stepData)($record->fresh()));
    $step=$record->fresh()->approvalSteps[2];
    expect(array_column($step->trackingEntries(),'label'))->toBe(['Awaiting Approval','Awaiting Approval']);
    if ($decision==='approved') $this->workflow->review(User::find(7),$record->fresh(),($this->stepData)($record->fresh())+['comments'=>'Central review complete']);
    else $this->service->reject(User::find(7),$record->fresh(),'Evidence rejected',($this->stepData)($record->fresh()));
    $entries=$step->fresh()->trackingEntries();
    expect($entries[0]['status'])->toBe('cancelled')->and($entries[0]['label'])->toBe($decision==='approved'?'Step completed':'Step closed')
        ->and($entries[0]['acted_at'])->toBeNull()->and($entries[0]['comments'])->toBeNull()
        ->and($entries[1]['status'])->toBe($decision)->and($entries[1]['person']['title'])->toBe('International Program Director')
        ->and($entries[1]['acted_at'])->not->toBeNull()->and($entries[1]['comments'])->toBe($decision==='approved'?'Central review complete':'Evidence rejected')
        ->and(StudentEnrollment::find(1)->grade_id)->toBe(1);
    expect($record->fresh()->status)->toBe($decision==='approved'?'pending':'rejected')
        ->and($record->fresh()->approvalSteps->last()->status)->toBe($decision==='approved'?'pending':'cancelled');
    $this->actingAs($this->campus)->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee($entries[0]['label'])->assertSee($entries[1]['comments']);
    foreach (['/students/skipping-grade','/students/skipping-grade/my-approvals?status=all'] as $url) {
        $page=$this->actingAs(str_contains($url,'my-approvals')?User::find(7):$this->campus)->get($url)->assertOk();
        preg_match_all('/<div class="skipping-workflow-reviewer"[^>]*>(.*?)<\/div>/s',$page->getContent(),$cards);
        expect($cards[1])->toHaveCount(6)->and($cards[1][3])->not->toContain('<time')
            ->and($cards[1][4])->toContain('datetime="'.$entries[1]['acted_at']->toIso8601String().'"',
                '>'.$entries[1]['acted_at']->format('d-M-Y').'</time>');
        if ($url==='/students/skipping-grade') $page->assertDontSee('<th>Parent / Guardian</th>',false);
    }
    $this->actingAs(User::find(9))->postJson('/students/skipping-grade/'.$record->id.'/review',[
        'approval_step_id'=>$step->id,'approval_step_revision'=>$step->revision,
    ])->assertForbidden();
})->with(['approved','rejected']);

it('central tracking preserves historical single reviewer steps without saved pool members',function () {
    ($this->workflowSetup)(); $record=($this->pending)(); $step=$record->approvalSteps[2];
    $step->reviewers()->delete();
    $entries=$step->fresh()->trackingEntries();
    expect($entries)->toHaveCount(1)->and($entries[0]['name'])->toBe('Central Reviewer')->and($entries[0]['person']['title'])->toBe('Central Reviewer');
    $this->actingAs($this->campus)->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('Central Reviewer');
});

it('approval flow validates the role for each added reviewer',function () {
    ($this->workflowSetup)(); $settings=($this->workflowSettings)();
    $settings['central_steps']=[['title'=>'Central Reviewer','reviewers'=>[
        ['user_id'=>9,'title'=>'Registrar Director','grade_scope'=>'all'],
        ['user_id'=>7,'title'=>'','grade_scope'=>'all'],
    ]]];
    $this->actingAs($this->central)->postJson('/students/skipping-grade/approval-flow',$settings)
        ->assertUnprocessable()->assertJsonValidationErrors('central_steps.0.reviewers.1.title');
    $settings['central_steps'][0]['reviewers'][1]['title']=str_repeat('x',121);
    $this->postJson('/students/skipping-grade/approval-flow',$settings)
        ->assertUnprocessable()->assertJsonValidationErrors('central_steps.0.reviewers.1.title');
    expect($this->workflow->revision())->toBe($settings['revision']);
});

it('reviewer configuration rejects global users without assignments and campus users for central approval',function () {
    ($this->workflowSetup)();
    DB::table('users')->where('id',8)->update(['is_global'=>true]);
    $url='/students/skipping-grade/approval-flow';
    $settings=($this->workflowSettings)(); $settings['campus_steps'][0]['user_id']=8;
    $this->actingAs($this->central)->postJson($url,$settings)->assertUnprocessable()->assertJsonValidationErrors('campus_steps');
    $settings=($this->workflowSettings)(); $settings['campus_steps'][0]['user_id']=2;
    $settings['campus_id']=2;
    $this->postJson($url,$settings)->assertUnprocessable()->assertJsonValidationErrors('campus_steps');
    $settings=($this->workflowSettings)(); $settings['central_steps'][0]['user_id']=4;
    $this->postJson($url,$settings)->assertUnprocessable()->assertJsonValidationErrors('central_steps');
    $settings=($this->workflowSettings)(); $settings['vice_president_id']=8;
    $this->postJson($url,$settings)->assertUnprocessable()->assertJsonValidationErrors('vice_president_id');
    expect($this->workflow->revision())->toBe($settings['revision']);
});

it('pending reviewer reassignment enforces campus and central membership without changing saved history',function () {
    ($this->workflowSetup)(); $record=($this->pending)();
    $campusStep=$record->approvalSteps[1]; $centralStep=$record->approvalSteps[2];
    DB::table('users')->where('id',8)->update(['is_global'=>true]);
    $url='/students/skipping-grade/'.$record->id.'/reviewers/';
    $this->actingAs($this->central)->postJson($url.$campusStep->id.'/reassign',['reviewer_id'=>8,'revision'=>1,'reason'=>'Campus replacement'])->assertUnprocessable()->assertJsonValidationErrors('reviewer_id');
    $this->postJson($url.$centralStep->id.'/reassign',['reviewer_id'=>4,'revision'=>1,'reason'=>'Central replacement'])->assertUnprocessable()->assertJsonValidationErrors('reviewer_id');
    expect($campusStep->fresh()->reviewer_id)->toBe(4)->and($centralStep->fresh()->reviewer_id)->toBe(9)
        ->and(\App\Models\SkippingGradeReassignment::count())->toBe(0);
    $this->postJson($url.$centralStep->id.'/reassign',['reviewer_id'=>7,'revision'=>1,'reason'=>'Central appointment'])->assertOk();
    expect($centralStep->fresh()->reviewer_id)->toBe(7);
});

it('grade scoped campus and central reviewers are matched to current grade rather than destination grade',function () {
    ($this->workflowSetup)();
    $this->actingAs($this->central)->postJson('/students/skipping-grade/approval-flow',($this->scopedSettings)())->assertOk();
    $record=($this->pending)();
    expect($record->source_grade_id)->toBe(1)->and($record->target_grade_id)->toBe(2)
        ->and($record->approvalSteps->pluck('reviewer_id')->all())->toBe([6,4,7,5])
        ->and($record->approvalSteps->first()->reviewers->pluck('user_id')->all())->toBe([6]);
    $this->actingAs(User::find(3))->getJson('/students/skipping-grade/'.$record->id)->assertForbidden();
    $this->postJson('/students/skipping-grade/'.$record->id.'/review',($this->stepData)($record))->assertForbidden();
    $this->actingAs(User::find(6))->get('/students/skipping-grade/my-approvals')->assertOk()->assertSee('SOK DARA');
    $this->postJson('/students/skipping-grade/'.$record->id.'/review',($this->stepData)($record))->assertOk();
    $this->workflow->review(User::find(4),$record->fresh(),($this->stepData)($record->fresh()));
    $this->actingAs(User::find(9))->getJson('/students/skipping-grade/'.$record->id)->assertForbidden();
    $this->actingAs(User::find(7))->postJson('/students/skipping-grade/'.$record->id.'/review',($this->stepData)($record->fresh()))->assertOk();
    expect($record->fresh()->approvalSteps[2]->actor_snapshot['name'])->toBe('Other Campus Viewer');
});

it('grade scoped groups need only one eligible approval and prevent a duplicate decision',function () {
    ($this->workflowSetup)(); $settings=($this->workflowSettings)();
    $settings['campus_steps'][0]=['title'=>'KH Coordinator','reviewers'=>[
        ['user_id'=>3,'grade_scope'=>'selected','grade_ids'=>[1]],['user_id'=>6,'grade_scope'=>'all'],
    ]];
    $this->workflow->saveSettings($this->central,$settings);
    $record=($this->pending)(); $data=($this->stepData)($record); $url='/students/skipping-grade/'.$record->id;
    expect($record->approvalSteps->first()->reviewers->pluck('user_id')->all())->toBe([3,6]);
    foreach ([3,6] as $id) $this->actingAs(User::find($id))->get('/students/skipping-grade/my-approvals')->assertOk()->assertSee('SOK DARA');
    $this->get($url)->assertOk()->assertSee('One of 2 eligible reviewers');
    $this->postJson($url.'/review',$data)->assertOk();
    $first=$record->fresh()->approvalSteps->first();
    expect($first->status)->toBe('approved')->and($first->acted_by)->toBe(6)->and($first->actor_snapshot['name'])->toBe('Replacement VSP')
        ->and($this->workflow->currentStep($record->fresh())->reviewer_id)->toBe(4)
        ->and(StudentEnrollment::find(1)->grade_id)->toBe(1);
    $this->actingAs(User::find(3))->postJson($url.'/review',$data)->assertForbidden();
    $this->get('/students/skipping-grade/my-approvals?status=approved')->assertOk()->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='SOK DARA'));
    $this->actingAs(User::find(6))->get('/students/skipping-grade/my-approvals?status=approved')->assertOk()->assertSee('SOK DARA');
});

it('grade scoped groups stop the entire workflow when one eligible reviewer rejects',function () {
    ($this->workflowSetup)(); $settings=($this->workflowSettings)();
    $settings['campus_steps'][0]=['title'=>'KH Coordinator','reviewers'=>[['user_id'=>3,'grade_scope'=>'all'],['user_id'=>6,'grade_scope'=>'all']]];
    $this->workflow->saveSettings($this->central,$settings); $record=($this->pending)();
    $this->actingAs(User::find(6))->postJson('/students/skipping-grade/'.$record->id.'/reject',($this->stepData)($record)+['rejection_reason'=>'Insufficient evidence'])->assertOk();
    expect($record->fresh()->status)->toBe('rejected')->and($record->fresh()->approvalSteps->first()->acted_by)->toBe(6)
        ->and($record->approvalSteps()->where('status','cancelled')->count())->toBe(3)->and(StudentEnrollment::find(1)->grade_id)->toBe(1);
});

it('grade scoped viewers cannot bypass assignments through requests reports exports or attachments',function () {
    ($this->workflowSetup)();
    (require database_path('migrations/2026_10_10_000001_add_skipping_report_permissions.php'))->up();
    (require database_path('migrations/2026_10_10_000002_move_skipping_report_into_requests.php'))->up();
    $this->workflow->saveSettings($this->central,($this->scopedSettings)());
    $allowed=($this->pending)(); $older=($this->olderStudent)();
    $this->actingAs($this->campus)->post('/students/skipping-grade/'.$older->id.'/attachments',['documents'=>[UploadedFile::fake()->create('private-evidence.pdf',10,'application/pdf')]])->assertRedirect();
    $other=$this->service->submit($this->campus,$older->fresh(),now()->toDateString(),UploadedFile::fake()->create('private-signed.pdf',10,'application/pdf'));
    ($this->grant)(6,['requests','print','print-reports','central-update']);
    ($this->grant)(3,['requests']);
    $this->actingAs(User::find(6));
    $page=$this->get('/students/skipping-grade')->assertOk()->assertSee('SOK DARA')->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='OLDER STUDENT'));
    expect($page->viewData('records')->pluck('id')->all())->toBe([$allowed->id]);
    foreach (['','/attachments/0','/signed-request','/print/request','/edit','/share/approval'] as $path) $this->getJson('/students/skipping-grade/'.$other->id.$path)->assertForbidden();
    $this->postJson('/students/skipping-grade/'.$other->id.'/review',($this->stepData)($other))->assertForbidden();
    foreach (['/print-report','/print-statistics'] as $path) {
        $preview=$this->get('/students/skipping-grade'.$path.'?status=')->assertOk();
        expect($preview->viewData('records')->pluck('id')->all())->toBe($path==='/print-statistics'?[]:[$allowed->id]);
    }
    $export=$this->get('/students/skipping-grade/reports/list/export/xlsx')->assertOk();
    $path=tempnam(sys_get_temp_dir(),'skipping-scope');
    try {
        file_put_contents($path,$export->getContent()); $zip=new ZipArchive(); expect($zip->open($path))->toBeTrue();
        expect($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('SOK DARA')->not->toContain('OLDER STUDENT','2619999'); $zip->close();
    } finally { unlink($path); }
    $draft=$other->replicate()->forceFill(['status'=>'draft','submitted_at'=>null,'signed_request_path'=>null]); $draft->save();
    $this->getJson('/students/skipping-grade/'.$draft->id.'/edit')->assertForbidden();
    $this->actingAs(User::find(3))->get('/students/skipping-grade')->assertOk()->assertSee('OLDER STUDENT')->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='SOK DARA'));
    $this->actingAs($this->central)->get('/students/skipping-grade')->assertOk()->assertSee('OLDER STUDENT')->assertSee('SOK DARA');
});

it('grade scoped assignments validate users grades and uncovered request steps',function () {
    ($this->workflowSetup)(); $settings=($this->scopedSettings)(); $url='/students/skipping-grade/approval-flow';
    $this->actingAs($this->central)->get($url)->assertOk()->assertSee('Add Reviewer to This Step')->assertSee('Current Grade Access')->assertSee('Apply Range');
    $settings['campus_steps'][0]['reviewers'][0]['grade_ids']=[];
    $this->postJson($url,$settings)->assertUnprocessable()->assertJsonValidationErrors('campus_steps');
    $settings['campus_steps'][0]['reviewers'][0]['grade_ids']=[99999];
    $this->postJson($url,$settings)->assertUnprocessable()->assertJsonValidationErrors('campus_steps');
    $settings=($this->scopedSettings)(); $settings['campus_steps'][0]['reviewers'][1]['user_id']=3;
    $this->postJson($url,$settings)->assertUnprocessable()->assertJsonValidationErrors('campus_steps');
    $settings['campus_steps'][0]['reviewers'][1]['user_id']=7;
    $this->postJson($url,$settings)->assertUnprocessable()->assertJsonValidationErrors('campus_steps');
    $settings=($this->scopedSettings)(); array_pop($settings['campus_steps'][0]['reviewers']);
    $this->postJson($url,$settings)->assertOk(); $draft=($this->draft)();
    $this->actingAs($this->campus)->postJson('/students/skipping-grade/'.$draft->id.'/submit',['parent_signed'=>1,'parent_signed_date'=>now()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('settings');
    expect($draft->fresh()->status)->toBe('draft')->and($draft->approvalSteps()->count())->toBe(0);
});

it('grade scoped requests preserve saved members when assignments change and audit individual replacements',function () {
    ($this->workflowSetup)(); $settings=($this->scopedSettings)();
    $settings['campus_steps'][0]['reviewers'][]=['user_id'=>4,'grade_scope'=>'all'];
    $this->workflow->saveSettings($this->central,$settings); $record=($this->pending)(); $first=$record->approvalSteps->first(); $members=$first->reviewers;
    expect($members->pluck('user_id')->all())->toBe([6,4]);
    $this->workflow->saveSettings($this->central,($this->workflowSettings)());
    expect($record->fresh()->approvalSteps->first()->reviewers->pluck('user_id')->all())->toBe([6,4]);
    $url='/students/skipping-grade/'.$record->id.'/reviewers/'.$first->id.'/reassign';
    $this->actingAs($this->central)->postJson($url,['reviewer_id'=>9,'revision'=>1,'reason'=>'New campus appointment'])->assertUnprocessable()->assertJsonValidationErrors('member_id');
    $replacement=['reviewer_id'=>3,'member_id'=>$members[0]->id,'revision'=>1,'reason'=>'New campus appointment'];
    $this->postJson($url,$replacement)->assertOk();
    $updated=$first->fresh();
    expect($updated->reviewers->pluck('user_id')->all())->toBe([3,4])->and($updated->reviewer_id)->toBe(3)
        ->and($updated->reviewers->first()->grade_ids)->toBe([1])
        ->and($updated->reassignments->first()->previous_reviewer['grade_ids'])->toBe([1]);
    $this->actingAs(User::find(6))->getJson('/students/skipping-grade/'.$record->id)->assertForbidden();
    $this->actingAs(User::find(4))->postJson('/students/skipping-grade/'.$record->id.'/review',($this->stepData)($record->fresh()))->assertOk();
    expect($first->fresh()->actor_snapshot['name'])->toBe('Campus VSP');
});

it('grade skipping permits multiple request creators at the same campus',function () {
    ($this->workflowSetup)(); ($this->grant)(6,['requests','create','update','submit']);
    $first=($this->draft)(); $second=($this->olderStudent)(User::find(6));
    expect($first->created_by)->toBe(1)->and($second->created_by)->toBe(6)->and($first->campus_id)->toBe(1)->and($second->campus_id)->toBe(1);
    $this->actingAs($this->campus)->postJson('/students/skipping-grade/'.$first->id.'/submit',['parent_signed'=>1,'parent_signed_date'=>now()->toDateString()])->assertOk();
    $this->actingAs(User::find(6))->postJson('/students/skipping-grade/'.$second->id.'/submit',['parent_signed'=>1,'parent_signed_date'=>now()->toDateString()])->assertOk();
    expect($first->fresh()->status)->toBe('pending')->and($second->fresh()->status)->toBe('pending');
});

it('grade scoped ranges include nursery and kindergarten using the system grade IDs',function () {
    ($this->workflowSetup)();
    DB::table('tb_grade')->where('id',1)->update(['grade_order'=>6]);
    DB::table('tb_grade')->where('id',2)->update(['grade_order'=>8]);
    foreach ([3=>'N',4=>'K1',5=>'K2',6=>'K3'] as $id=>$label) DB::table('tb_grade')->insert(['id'=>$id,'grade'=>$label,'grade_short_name'=>$label,'grade_order'=>$id-2]);
    $settings=($this->workflowSettings)();
    $settings['campus_steps'][0]=['title'=>'KH Coordinator','reviewers'=>[
        ['user_id'=>3,'grade_scope'=>'selected','grade_ids'=>[3,4,5,6]],['user_id'=>6,'grade_scope'=>'selected','grade_ids'=>[1,2]],
    ]];
    $this->workflow->saveSettings($this->central,$settings);
    $older=($this->pending)();
    DB::table('tb_student')->insert(['id'=>2,'student_id'=>'2617777','full_name_en'=>'NURSERY STUDENT','full_name_kh'=>'Nursery student KH','date_of_birth'=>'2023-03-02','gender'=>'male']);
    DB::table('tb_class')->insert(['id'=>3,'class_name'=>'A','grade_id'=>3,'academic_year_id'=>1]);
    DB::table('tb_student_enrollment')->insert(['id'=>2,'student_id'=>2,'campus_id'=>1,'academic_year_id'=>1,'grade_id'=>3,'class_id'=>3]);
    $nurseryDraft=$this->service->saveDraft($this->campus,array_replace($this->data,['enrollment_id'=>2]));
    $nursery=$this->service->submit($this->campus,$nurseryDraft,now()->toDateString());
    expect($nursery->approvalSteps->first()->reviewer_id)->toBe(3)
        ->and($nursery->approvalSteps->first()->reviewers->first()->reviewer_snapshot['grades'])->toBe(['N','K1','K2','K3'])
        ->and($older->approvalSteps->first()->reviewer_id)->toBe(6);
    $this->actingAs(User::find(3))->get('/students/skipping-grade/my-approvals')->assertOk()->assertSee('NURSERY STUDENT')->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='SOK DARA'));
    $this->actingAs(User::find(4))->get('/students/skipping-grade/my-approvals?status=all')->assertOk()->assertSee('NURSERY STUDENT')->assertSee('SOK DARA');
});

it('grade scoped enhancement retains earlier single reviewer requests and their reassignment behavior',function () {
    ($this->workflowSetup)(); $record=($this->pending)();
    foreach ($record->approvalSteps as $step) $step->reviewers()->delete();
    $this->workflow->saveSettings($this->central,($this->scopedSettings)());
    $this->actingAs(User::find(3))->get('/students/skipping-grade/my-approvals')->assertOk()->assertSee('SOK DARA');
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('Approve and Forward');
    $this->postJson('/students/skipping-grade/'.$record->id.'/review',($this->stepData)($record))->assertOk();
    $vsp=$this->workflow->currentStep($record->fresh());
    $this->workflow->reassign($this->central,$record->fresh(),$vsp->id,['reviewer_id'=>6,'revision'=>1,'reason'=>'Replace original single reviewer']);
    expect($vsp->fresh()->reviewer_id)->toBe(6)->and($vsp->reviewers()->count())->toBe(0)
        ->and($record->fresh()->approvalSteps->first()->actor_snapshot['name'])->toBe('KH Coordinator');
    $this->actingAs(User::find(6))->postJson('/students/skipping-grade/'.$record->id.'/review',($this->stepData)($record->fresh()))->assertOk();
});

it('sequential workflow captures the mode at creation and reviewers at submission',function () {
    $legacy=($this->draft)();
    ($this->workflowSetup)();
    expect($legacy->fresh()->approval_flow_mode)->toBe('legacy');
    $this->service->submit($this->campus,$legacy,now()->toDateString());
    expect($legacy->approvalSteps()->count())->toBe(0);
    $this->service->reject($this->central,$legacy->fresh(),'Legacy decision');
    $draft=($this->draft)();
    $this->workflow->saveSettings($this->central,($this->workflowSettings)('legacy'));
    $draft=$this->service->saveDraft($this->campus,$this->data,$draft);
    expect($draft->approval_flow_mode)->toBe('sequential')->and($draft->approvalSteps()->count())->toBe(0);
    \App\Models\SkippingGradeCampusSetting::where('campus_id',1)->update(['approval_steps'=>json_encode([['title'=>'New KH Coordinator','user_id'=>6]])]);
    $pending=$this->service->submit($this->campus,$draft,now()->toDateString());
    expect($pending->approvalSteps->pluck('reviewer_id')->all())->toBe([6,9,5])
        ->and($pending->approvalSteps->pluck('status')->all())->toBe(['pending','waiting','waiting'])
        ->and($pending->approvalSteps->last()->is_final)->toBeTrue();
    \App\Models\SkippingGradeCampusSetting::where('campus_id',1)->update(['approval_steps'=>json_encode([['title'=>'Another Coordinator','user_id'=>3]])]);
    expect($pending->fresh()->approvalSteps->first()->reviewer_snapshot['name'])->toBe('Replacement VSP');
});

it('sequential workflow advances only in order and changes enrollment only at final approval',function () {
    ($this->workflowSetup)();
    $record=($this->pending)(); $url='/students/skipping-grade/'.$record->id;
    $first=($this->stepData)($record);
    $this->actingAs(User::find(4))->postJson($url.'/review',$first)->assertForbidden();
    $this->actingAs($this->central)->postJson($url.'/approve',$this->approval+$first)->assertForbidden();
    $this->actingAs(User::find(3))->postJson($url.'/approve',$this->approval+$first)->assertUnprocessable()->assertJsonValidationErrors('status');
    foreach ([3,4,9] as $id) {
        $this->actingAs(User::find($id))->get($url)->assertOk()->assertSee('Approve and Forward')->assertDontSee('Approve and Move Grade');
        $data=($this->stepData)($record->fresh());
        $this->postJson($url.'/review',$data+['comments'=>'Reviewed attachments'])->assertOk();
        expect($record->fresh()->status)->toBe('pending')->and(StudentEnrollment::find(1)->grade_id)->toBe(1)
            ->and(DB::table('tb_student_enrollment_history')->count())->toBe(0);
        $this->postJson($url.'/review',$data)->assertForbidden();
    }
    $this->actingAs(User::find(5))->get($url)->assertOk()->assertSee('Approve and Move Grade');
    $this->postJson($url.'/approve',$this->approval+($this->stepData)($record->fresh()))->assertOk();
    $approved=$record->fresh();
    expect($approved->status)->toBe('approved')->and($approved->approved_by)->toBe(5)
        ->and(StudentEnrollment::find(1)->grade_id)->toBe(2)->and(DB::table('tb_student_enrollment_history')->count())->toBe(2)
        ->and($approved->approvalSteps->pluck('status')->all())->toBe(['approved','approved','approved','approved']);
    DB::table('users')->whereIn('id',[3,5])->update(['name'=>'Changed Staff Name']);
    $this->get($url)->assertOk()->assertSee('KH Coordinator')->assertSee('Approved by Vice President');
    expect($approved->fresh()->approvalSteps->first()->actor_snapshot['name'])->toBe('KH Coordinator');
    $this->actingAs($this->central)->postJson($url.'/reviewers/'.$approved->approvalSteps->first()->id.'/reassign',['reviewer_id'=>6,'revision'=>1,'reason'=>'New staff'])->assertUnprocessable();
});

it('sequential workflow rejection at any reviewer stops later steps without moving grade',function ($rejectAt) {
    ($this->workflowSetup)(); $record=($this->pending)();
    foreach ([3,4,9,5] as $index=>$userId) {
        if ($index===$rejectAt) {
            $this->service->reject(User::find($userId),$record->fresh(),'Missing evidence',($this->stepData)($record->fresh()));
            break;
        }
        $this->workflow->review(User::find($userId),$record->fresh(),($this->stepData)($record->fresh()));
    }
    expect($record->fresh()->status)->toBe('rejected')->and(StudentEnrollment::find(1)->grade_id)->toBe(1)
        ->and(DB::table('tb_student_enrollment_history')->count())->toBe(0)
        ->and($record->approvalSteps()->whereIn('status',['pending','waiting'])->count())->toBe(0)
        ->and($record->approvalSteps()->where('status','rejected')->count())->toBe(1);
    $this->actingAs(User::find(5))->postJson('/students/skipping-grade/'.$record->id.'/approve',$this->approval)->assertForbidden();
})->with([0,1,2,3]);

it('sequential workflow settings require Super Admin the selected campus and a final Vice President',function () {
    ($this->workflowSetup)(); $url='/students/skipping-grade/approval-flow';
    $this->actingAs(User::find(3))->getJson($url)->assertForbidden();
    $this->postJson($url,($this->workflowSettings)())->assertForbidden();
    $this->actingAs($this->central)->get($url)->assertOk()->assertSee('System Approval Flow')->assertSee('Vice President');
    $data=($this->workflowSettings)();
    $this->postJson($url,array_replace($data,['vice_president_id'=>null]))->assertUnprocessable()->assertJsonValidationErrors('vice_president_id');
    $this->postJson($url,array_replace($data,['campus_steps'=>[['title'=>'Invalid Campus','user_id'=>7]]]))->assertUnprocessable()->assertJsonValidationErrors('campus_steps');
    \App\Models\SkippingGradeCampusSetting::where('campus_id',2)->delete();
    $data=($this->workflowSettings)();
    $this->postJson($url,array_replace($data,['campus_steps'=>[]]))->assertUnprocessable()->assertJsonValidationErrors('campus_steps');
    $changed=$data; $changed['campus_steps'][0]['title']='Campus KH Coordinator';
    $this->postJson($url,$changed)->assertOk();
    $this->postJson($url,$data)->assertUnprocessable()->assertJsonValidationErrors('revision');
    $data=($this->workflowSettings)('legacy'); $data['campus_id']=2; $data['campus_steps']=[['title'=>'KH Coordinator','user_id'=>8]];
    $this->postJson($url,$data)->assertOk();
    expect(\App\Models\SkippingGradeCampusSetting::where('campus_id',2)->first()->committee_names)->toBe(['','','','']);
    DB::table('users')->where('id',8)->update(['status'=>0]);
    $data=($this->workflowSettings)(); $data['campus_id']=2; $data['campus_steps']=[['title'=>'KH Coordinator','user_id'=>8]];
    $this->postJson($url,$data)->assertUnprocessable()->assertJsonValidationErrors('campus_steps');
    expect(SkippingGradeSetting::current()->approval_flow_mode)->toBe('legacy');
});

it('saving one campus enables its workflow without requiring other campuses to be configured',function () {
    ($this->workflowSetup)();
    $this->workflow->saveSettings($this->central,($this->workflowSettings)('legacy'));
    $legacy=($this->draft)();
    $this->service->submit($this->campus,$legacy,now()->toDateString());
    \App\Models\SkippingGradeCampusSetting::whereIn('campus_id',[2,3])->delete();
    DB::table('tb_school_info')->insert(['id'=>4,'campus_name_en'=>'STD','status'=>1]);
    $this->actingAs($this->central)->postJson('/students/skipping-grade/approval-flow',($this->workflowSettings)())->assertOk();
    expect(SkippingGradeSetting::current()->approval_flow_mode)->toBe('sequential')
        ->and($legacy->fresh()->approval_flow_mode)->toBe('legacy')
        ->and($legacy->approvalSteps()->count())->toBe(0)
        ->and(\App\Models\SkippingGradeCampusSetting::where('campus_id',4)->exists())->toBeFalse();
    $this->service->reject($this->central,$legacy->fresh(),'Legacy decision');
    $pending=($this->pending)();
    expect($pending->approval_flow_mode)->toBe('sequential')
        ->and($pending->approvalSteps->pluck('reviewer_id')->all())->toBe([3,4,9,5]);
});

it('saving one campus ignores invalid reviewers in another campus but validates them on that campus submission',function () {
    ($this->workflowSetup)();
    DB::table('users')->where('id',8)->update(['status'=>0]);
    $this->actingAs($this->central)->postJson('/students/skipping-grade/approval-flow',($this->workflowSettings)())->assertOk();
    DB::table('tb_student')->insert(['id'=>2,'student_id'=>'DNG-STUDENT','full_name_en'=>'DNG STUDENT','full_name_kh'=>'DNG Student KH','date_of_birth'=>'2017-03-02']);
    DB::table('tb_student_enrollment')->insert(['id'=>2,'student_id'=>2,'campus_id'=>2,'academic_year_id'=>1,'grade_id'=>1,'class_id'=>1]);
    $draft=$this->service->saveDraft($this->central,array_replace($this->data,['enrollment_id'=>2]));
    $url='/students/skipping-grade/'.$draft->id.'/submit';
    $this->postJson($url,['parent_signed'=>1,'parent_signed_date'=>now()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('settings');
    expect($draft->fresh()->status)->toBe('draft')->and($draft->approvalSteps()->count())->toBe(0);
    \App\Models\SkippingGradeCampusSetting::where('campus_id',2)->delete();
    $this->postJson($url,['parent_signed'=>1,'parent_signed_date'=>now()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('settings');
    expect($draft->fresh()->status)->toBe('draft')->and($draft->approvalSteps()->count())->toBe(0);
    DB::table('users')->where('id',8)->update(['status'=>1]);
    $data=($this->workflowSettings)(); $data['campus_id']=2; $data['campus_steps']=[['title'=>'KH Coordinator','user_id'=>8]];
    $this->postJson('/students/skipping-grade/approval-flow',$data)->assertOk();
    $this->postJson($url,['parent_signed'=>1,'parent_signed_date'=>now()->toDateString()])->assertOk();
    expect($draft->fresh()->approvalSteps->pluck('reviewer_id')->all())->toBe([8,9,5]);
});

it('sequential workflow reassignment audits changes and cannot replace completed or stale steps',function () {
    ($this->workflowSetup)(); $record=($this->pending)(); $first=$record->approvalSteps->first(); $vsp=$record->approvalSteps[1];
    $this->workflow->review(User::find(3),$record,($this->stepData)($record));
    $url='/students/skipping-grade/'.$record->id.'/reviewers/';
    $replacement=['reviewer_id'=>6,'revision'=>1,'reason'=>'Campus VSP changed'];
    $this->actingAs(User::find(4))->postJson($url.$vsp->id.'/reassign',$replacement)->assertForbidden();
    $this->actingAs($this->central)->postJson($url.$first->id.'/reassign',$replacement)->assertUnprocessable();
    $this->postJson($url.$vsp->id.'/reassign',array_replace($replacement,['reviewer_id'=>7]))->assertUnprocessable()->assertJsonValidationErrors('reviewer_id');
    $oldData=($this->stepData)($record->fresh());
    $this->postJson($url.$vsp->id.'/reassign',$replacement)->assertOk();
    $step=$vsp->fresh(); $audit=$step->reassignments->first();
    expect($step->reviewer_id)->toBe(6)->and($step->revision)->toBe(2)->and($step->status)->toBe('pending')
        ->and($audit->previous_reviewer['name'])->toBe('Campus VSP')->and($audit->replacement_reviewer['name'])->toBe('Replacement VSP')
        ->and($audit->changed_by_name)->toBe('Central Office')->and($first->fresh()->actor_snapshot['name'])->toBe('KH Coordinator');
    $this->postJson($url.$vsp->id.'/reassign',$replacement)->assertUnprocessable()->assertJsonValidationErrors('revision');
    $this->actingAs(User::find(4))->postJson('/students/skipping-grade/'.$record->id.'/review',$oldData)->assertForbidden();
    $this->actingAs(User::find(6))->postJson('/students/skipping-grade/'.$record->id.'/review',$oldData)->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('Reviewer Change History')->assertSee('Campus VSP changed');
    $this->postJson('/students/skipping-grade/'.$record->id.'/review',($this->stepData)($record->fresh()))->assertOk();
});

it('sequential workflow inbox and private attachments are limited to assigned requests',function () {
    ($this->workflowSetup)(); $draft=($this->draft)(); $url='/students/skipping-grade/'.$draft->id;
    $this->actingAs($this->campus)->post($url.'/attachments',['documents'=>[UploadedFile::fake()->create('birth-certificate.pdf',10,'application/pdf')]])->assertRedirect();
    $record=$this->service->submit($this->campus,$draft->fresh(),now()->toDateString(),UploadedFile::fake()->create('signed-request.pdf',10,'application/pdf'));
    $this->actingAs(User::find(3))->get('/students/skipping-grade')->assertRedirect('/students/skipping-grade/my-approvals');
    $this->get('/students/skipping-grade/my-approvals')->assertOk()->assertSee('SOK DARA');
    $this->get('/students/skipping-grade/my-approvals?search=missing')->assertOk()->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='SOK DARA'));
    $this->get($url)->assertOk()->assertSee('birth-certificate.pdf')->assertSee('View Signed Request');
    $this->get($url.'/attachments/0')->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
    $this->get($url.'/signed-request')->assertOk();
    $this->getJson($url.'/attachments/99')->assertNotFound();
    $this->actingAs(User::find(4))->get('/students/skipping-grade/my-approvals?status=pending')->assertOk()->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='SOK DARA'));
    $this->get('/students/skipping-grade/my-approvals?status=all')->assertOk()->assertSee('SOK DARA');
    $this->get($url)->assertOk()->assertDontSee('Approve and Forward');
    $this->actingAs(User::find(7))->getJson($url)->assertForbidden();
    $this->getJson($url.'/attachments/0')->assertForbidden();
    $this->getJson($url.'/signed-request')->assertForbidden();
    $this->actingAs($this->central)->postJson($url.'/update',$this->data)->assertForbidden();
    $this->post($url.'/attachments',['documents'=>[UploadedFile::fake()->create('late.pdf',10,'application/pdf')]])->assertForbidden();
    $this->workflow->review(User::find(3),$record,($this->stepData)($record));
    $this->actingAs(User::find(3))->get('/students/skipping-grade/my-approvals?status=approved')->assertOk()->assertSee('SOK DARA');
});

it('my approvals opens with review history and retains an explicit awaiting approval filter',function () {
    ($this->workflowSetup)(); $record=($this->pending)();
    $this->workflow->review(User::find(3),$record,($this->stepData)($record));
    $page=$this->actingAs(User::find(3))->get('/students/skipping-grade/my-approvals')->assertOk()->assertSee('SOK DARA');
    expect($page->viewData('scope'))->toBe('approvals')->and($page->viewData('status'))->toBe('all');
    $this->get('/students/skipping-grade/my-approvals?status=pending')->assertOk()->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='SOK DARA'));
    $this->get('/students/skipping-grade/my-approvals?status=approved')->assertOk()->assertSee('SOK DARA');
    $this->actingAs(User::find(9))->get('/students/skipping-grade/my-approvals')->assertOk()->assertSee('SOK DARA');
    $this->get('/students/skipping-grade/my-approvals?status=pending')->assertOk()->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='SOK DARA'));
});

it('my requests includes created and submitted requests without requiring reviewer assignment',function () {
    ($this->workflowSetup)(); ($this->grant)(6,['requests','submit']);
    $record=$this->service->submit(User::find(6),($this->draft)(),now()->toDateString());
    $other=$this->service->submit($this->campus,($this->olderStudent)(),now()->toDateString());
    $page=$this->actingAs(User::find(6))->get('/students/skipping-grade/my-approvals')->assertOk()->assertSee('SOK DARA')->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='OLDER STUDENT'));
    expect($page->viewData('scope'))->toBe('requests')->and($page->viewData('records')->pluck('id')->all())->toBe([$record->id]);
    $this->get('/students/skipping-grade/'.$record->id)->assertOk();
    $this->get('/students/skipping-grade/my-approvals?scope=requests&status=draft')->assertOk()->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='SOK DARA'));
    $this->actingAs($this->campus)->get('/students/skipping-grade/my-approvals?scope=requests')->assertOk()->assertSee('SOK DARA')->assertSee('OLDER STUDENT');
    $this->get('/students/skipping-grade/my-approvals?scope=approvals')->assertOk()->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='SOK DARA'))->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='OLDER STUDENT'));
});

it('campus requests respects assigned campuses and grades and opens eligible current flow records',function () {
    ($this->workflowSetup)(); $this->workflow->saveSettings($this->central,($this->scopedSettings)());
    $allowed=($this->pending)();
    $older=$this->service->submit($this->campus,($this->olderStudent)(),now()->toDateString());
    $legacy=$allowed->replicate()->forceFill(['approval_flow_mode'=>'legacy','status'=>'approved','approved_at'=>now(),'effective_date'=>now()->toDateString(),'student_snapshot'=>array_replace($allowed->student_snapshot,['name_en'=>'LEGACY CAMPUS STUDENT'])]); $legacy->save();
    $outside=$allowed->replicate()->forceFill(['campus_id'=>2,'student_snapshot'=>array_replace($allowed->student_snapshot,['name_en'=>'OTHER CAMPUS STUDENT','campus_en'=>'DNG'])]); $outside->save();
    $page=$this->actingAs(User::find(6))->get('/students/skipping-grade/my-approvals?scope=campus')->assertOk()
        ->assertSee('SOK DARA')->assertSee('LEGACY CAMPUS STUDENT')->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='OLDER STUDENT'))->assertDontSee('OTHER CAMPUS STUDENT');
    expect($page->viewData('records')->pluck('id')->all())->toEqualCanonicalizing([$allowed->id,$legacy->id]);
    $this->get('/students/skipping-grade/'.$legacy->id)->assertOk();
    foreach ([$older,$outside] as $hidden) $this->getJson('/students/skipping-grade/'.$hidden->id)->assertForbidden();
    $this->get('/students/skipping-grade/my-approvals?scope=campus&status=approved')->assertOk()->assertSee('LEGACY CAMPUS STUDENT')->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='SOK DARA'));
    $this->get('/students/skipping-grade/my-approvals?scope=campus&search=2612345')->assertOk()->assertSee('SOK DARA');
    $this->get('/students/skipping-grade/my-approvals?scope=campus&search=missing')->assertOk()->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='SOK DARA'));
    $this->actingAs(User::find(8))->get('/students/skipping-grade/my-approvals?scope=campus')->assertOk()
        ->assertSee('OTHER CAMPUS STUDENT')->assertDontSee('LEGACY CAMPUS STUDENT')->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='OLDER STUDENT'));
});

it('campus requesters can browse their assigned campus and their own drafts',function () {
    ($this->workflowSetup)(); $record=($this->draft)();
    $other=$record->replicate()->forceFill(['campus_id'=>2,'created_by'=>8,'student_snapshot'=>array_replace($record->student_snapshot,['name_en'=>'OTHER CAMPUS STUDENT'])]); $other->save();
    $page=$this->actingAs($this->campus)->get('/students/skipping-grade/my-approvals?scope=campus')->assertOk()->assertSee('SOK DARA')->assertDontSee('OTHER CAMPUS STUDENT');
    expect($page->viewData('records')->pluck('id')->all())->toBe([$record->id]);
    $this->get('/students/skipping-grade/my-approvals?scope=requests&status=draft')->assertOk()->assertSee('SOK DARA');
    $this->getJson('/students/skipping-grade/my-approvals?scope=unknown')->assertUnprocessable();
});

it('current flow approvals remain available in personal review history',function (string $decision) {
    ($this->configure)(); $record=($this->pending)();
    $this->actingAs($this->central)->get('/students/skipping-grade/my-approvals?scope=approvals&status=pending')->assertOk()->assertSee('SOK DARA');
    if ($decision==='approved') $this->service->approve($this->central,$record,$this->approval);
    else $this->service->reject($this->central,$record,'Incomplete evidence');
    $this->get('/students/skipping-grade/my-approvals?scope=approvals&status='.$decision)->assertOk()->assertSee('SOK DARA');
    $this->get('/students/skipping-grade/my-approvals?scope=approvals&status=all')->assertOk()->assertSee('SOK DARA');
    $this->get('/students/skipping-grade/my-approvals?scope=approvals&status=pending')->assertOk()->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='SOK DARA'));
    $rejected=$this->get('/students/skipping-grade/my-approvals?scope=rejected')->assertOk();
    if ($decision==='rejected') $rejected->assertSee('SOK DARA');
    else $rejected->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='SOK DARA'));
})->with(['approved','rejected']);

it('personal request view choices follow requester and reviewer roles',function () {
    ($this->workflowSetup)();
    $reviewer=$this->actingAs(User::find(3))->get('/students/skipping-grade/my-approvals')->assertOk();
    expect($reviewer->viewData('scopeOptions'))->toBe(['campus'=>'Campus Requests','approvals'=>'My Approval','rejected'=>'My Rejected']);
    $requester=$this->actingAs($this->campus)->get('/students/skipping-grade/my-approvals')->assertOk();
    expect($requester->viewData('scopeOptions'))->toBe(['campus'=>'Campus Requests','requests'=>'My Requested']);
    ($this->grant)(3,['requests','create']);
    $both=$this->actingAs(User::find(3))->get('/students/skipping-grade/my-approvals')->assertOk();
    expect($both->viewData('scopeOptions'))->toBe(['campus'=>'Campus Requests','approvals'=>'My Approval','rejected'=>'My Rejected','requests'=>'My Requested']);
});

it('my rejected filters by the acting reviewer rather than the overall request status',function () {
    ($this->workflowSetup)(); $record=($this->pending)();
    $this->service->reject(User::find(3),$record,'Missing evidence',($this->stepData)($record));
    $other=$this->service->submit($this->campus,($this->olderStudent)(),now()->toDateString());
    $this->workflow->review(User::find(3),$other,($this->stepData)($other));
    $this->service->reject(User::find(4),$other->fresh(),'Rejected at next step',($this->stepData)($other->fresh()));
    $page=$this->actingAs(User::find(3))->get('/students/skipping-grade/my-approvals?scope=rejected&status=all')->assertOk()
        ->assertSee('SOK DARA')->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='OLDER STUDENT'));
    expect($page->viewData('status'))->toBe('rejected')->and($page->viewData('records')->pluck('id')->all())->toBe([$record->id]);
    $this->get('/students/skipping-grade/my-approvals?scope=approvals&status=approved')->assertOk()->assertSee('OLDER STUDENT')->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='SOK DARA'));
    $this->actingAs(User::find(4))->get('/students/skipping-grade/my-approvals?scope=rejected')->assertOk()->assertSee('OLDER STUDENT')->assertViewHas('records',fn($records)=>$records->getCollection()->every(fn($record)=>($record->student_snapshot['name_en']??null)!=='SOK DARA'));
});

it('personal approval and rejection lists show student photos and recorded current and skipped grades',function (string $decision) {
    ($this->workflowSetup)();
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','academic_year_code'=>'2627','lifecycle_status'=>'pending']);
    DB::table('tb_class')->where('id',2)->update(['academic_year_id'=>2]);
    DB::table('tb_session')->insert(['id'=>1,'session_name'=>'III']);
    StudentEnrollment::whereKey(1)->update(['session_id'=>1]);
    $photo=UploadedFile::fake()->image('inbox-student.png');
    Storage::disk('public')->put('students/inbox-student.png',file_get_contents($photo->getRealPath()));
    \App\Models\Student::find(1)->update(['photo_path'=>'students/inbox-student.png']);
    $draft=$this->service->saveDraft($this->campus,array_replace($this->data,['target_academic_year_id'=>2,'target_session_id'=>1]));
    $record=$this->service->submit($this->campus,$draft,now()->toDateString());
    if ($decision==='approved') $this->workflow->review(User::find(3),$record,($this->stepData)($record));
    else $this->service->reject(User::find(3),$record,'Missing evidence',($this->stepData)($record));
    $url='/students/skipping-grade/my-approvals?'.http_build_query(['scope'=>$decision==='approved'?'approvals':'rejected','status'=>$decision]);
    $page=$this->actingAs(User::find(3))->get($url)->assertOk()->assertSee('students/inbox-student.png')
        ->assertSee('<th scope="col">Student Photo</th><th scope="col">Student</th><th scope="col">Current Grade</th><th scope="col">Skipped Grade</th>',false)
        ->assertSee('<div>2025-2026</div><div>BCH-2A-III</div>',false)
        ->assertSee('<div>2026-2027</div><div>BCH-4B-III</div>',false)->assertDontSee('<th>Campus / Year</th>',false);
    expect($page->viewData('reportRows')[$record->id])->toMatchArray(['source_grade'=>'2A-III','target_grade'=>'4B-III']);
    Storage::disk('public')->delete('students/inbox-student.png');
    $this->get($url)->assertOk()->assertSee('No photo')->assertDontSee('students/inbox-student.png');
})->with(['approved','rejected']);

it('sequential workflow freezes reviewer photos and VP signer assets for pending requests',function () {
    ($this->workflowSetup)();
    $photoFile=UploadedFile::fake()->image('coordinator.png',80,80);
    $photo=file_get_contents($photoFile->getRealPath());
    Storage::disk('public')->put('staff/coordinator.png',$photo);
    DB::table('users')->where('id',3)->update(['photo_path'=>'staff/coordinator.png']);
    $record=($this->pending)(); $saved=$record->workflow_signer_snapshot;
    expect($record->approvalSteps->first()->reviewer_snapshot['photo'])->toStartWith('data:image/jpeg;base64,');
    Storage::disk('public')->delete('staff/coordinator.png');
    Storage::disk('local')->delete(['settings/signature.png','settings/stamp.png']);
    SkippingGradeSetting::current()->update(['signer_name_en'=>'New VP Name','signer_name_kh'=>'New VP KH']);
    foreach ([3,4,9] as $id) $this->workflow->review(User::find($id),$record->fresh(),($this->stepData)($record->fresh()));
    $approved=$this->service->approve(User::find(5),$record->fresh(),$this->approval+($this->stepData)($record->fresh()));
    expect($approved->approval_snapshot['signer_name_en'])->toBe('KING ROTH MONY')
        ->and(Storage::disk('local')->get($approved->approval_snapshot['signature_path']))->toBe(Storage::disk('local')->get($saved['signature_path']));
});

it('sequential workflow final approval rolls back the decision if enrollment validation fails',function () {
    ($this->workflowSetup)(); $record=($this->pending)();
    foreach ([3,4,9] as $id) $this->workflow->review(User::find($id),$record->fresh(),($this->stepData)($record->fresh()));
    StudentEnrollment::find(1)->update(['class_id'=>2]);
    $this->actingAs(User::find(5))->postJson('/students/skipping-grade/'.$record->id.'/approve',$this->approval+($this->stepData)($record->fresh()))->assertUnprocessable();
    expect($record->fresh()->status)->toBe('pending')->and($record->fresh()->approvalSteps->last()->status)->toBe('pending')
        ->and(DB::table('tb_student_enrollment_history')->count())->toBe(0)->and(Storage::disk('local')->allFiles('grade-skipping/approvals'))->toBe([]);
});

it('sequential workflow can reassign a waiting final reviewer and retain the earlier decisions',function () {
    ($this->workflowSetup)(); $record=($this->pending)(); $final=$record->approvalSteps->last();
    $originalSigner=$record->workflow_signer_snapshot;
    SkippingGradeSetting::current()->update(['signer_name_kh'=>'Replacement VP KH','signer_name_en'=>'Replacement VP']);
    $url='/students/skipping-grade/'.$record->id.'/reviewers/'.$final->id.'/reassign';
    $this->actingAs($this->central)->postJson($url,['reviewer_id'=>9,'revision'=>1,'reason'=>'Vice President appointment changed'])->assertOk();
    expect($final->fresh()->status)->toBe('waiting')->and($record->fresh()->workflow_signer_snapshot['signer_name_en'])->toBe('Replacement VP')
        ->and(Storage::disk('local')->exists($originalSigner['signature_path']))->toBeTrue();
    foreach ([3,4,9] as $id) $this->workflow->review(User::find($id),$record->fresh(),($this->stepData)($record->fresh()));
    $this->actingAs(User::find(5))->postJson('/students/skipping-grade/'.$record->id.'/approve',$this->approval+($this->stepData)($record->fresh()))->assertForbidden();
    $this->actingAs(User::find(9))->postJson('/students/skipping-grade/'.$record->id.'/approve',$this->approval+($this->stepData)($record->fresh()))->assertOk();
    expect($record->fresh()->approval_snapshot['signer_name_en'])->toBe('Replacement VP')->and($record->fresh()->approvalSteps->first()->actor_snapshot['name'])->toBe('KH Coordinator');
});

it('sequential workflow blocks inactive reviewers and validates the latest configuration on submission',function () {
    ($this->workflowSetup)(); $draft=($this->draft)();
    DB::table('users')->where('id',4)->update(['status'=>0]);
    $this->actingAs($this->campus)->postJson('/students/skipping-grade/'.$draft->id.'/submit',['parent_signed'=>1,'parent_signed_date'=>now()->toDateString()])->assertUnprocessable();
    expect($draft->fresh()->status)->toBe('draft')->and($draft->approvalSteps()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('grade-skipping/workflow-signers'))->toBe([]);
    DB::table('users')->where('id',4)->update(['status'=>1]);
    $record=$this->service->submit($this->campus,$draft->fresh(),now()->toDateString());
    DB::table('users')->where('id',3)->update(['status'=>0]);
    expect($this->workflow->canAct(User::find(3),$record))->toBeFalse();
    $first=$record->approvalSteps->first();
    $this->workflow->reassign($this->central,$record,$first->id,['reviewer_id'=>6,'revision'=>1,'reason'=>'Reviewer account deactivated']);
    expect($this->workflow->canAct(User::find(6),$record->fresh()))->toBeTrue();
});

it('sequential workflow leaves the original flow usable for requests created after switching back',function () {
    ($this->workflowSetup)();
    $this->workflow->saveSettings($this->central,($this->workflowSettings)('legacy'));
    $legacy=($this->pending)();
    expect($legacy->approval_flow_mode)->toBe('legacy')->and($legacy->approvalSteps()->count())->toBe(0);
    $approved=$this->service->approve($this->central,$legacy,$this->approval);
    expect($approved->status)->toBe('approved')->and($approved->approvalSteps()->count())->toBe(0)->and(StudentEnrollment::find(1)->grade_id)->toBe(2);
});

it('sequential workflow rejects a repeated decision when the same person has consecutive roles',function () {
    ($this->workflowSetup)();
    \App\Models\SkippingGradeCampusSetting::where('campus_id',1)->update(['approval_steps'=>json_encode([
        ['title'=>'KH Coordinator','user_id'=>3],['title'=>'Acting VSP','user_id'=>3],
    ])]);
    $record=($this->pending)(); $data=($this->stepData)($record); $url='/students/skipping-grade/'.$record->id.'/review';
    $this->actingAs(User::find(3))->postJson($url,$data)->assertOk();
    $this->postJson($url,$data)->assertUnprocessable()->assertJsonValidationErrors('status');
    expect($record->approvalSteps()->where('status','approved')->count())->toBe(1)
        ->and($this->workflow->currentStep($record->fresh())->title)->toBe('Acting VSP');
});

it('prints the same approved requests filtered by current year campus class group approval and search', function () {
    (require database_path('migrations/2026_10_10_000001_add_skipping_report_permissions.php'))->up();
    (require database_path('migrations/2026_10_10_000002_move_skipping_report_into_requests.php'))->up();
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','academic_year_code'=>'2627']);
    DB::table('tb_session')->insert(['id'=>1,'session_name'=>'III']);
    Storage::fake('public');
    Storage::disk('public')->put('students/report-photo.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j3ioAAAAASUVORK5CYII='));
    DB::table('tb_student')->where('id',1)->update(['photo_path'=>'students/report-photo.png']);
    $draft=($this->draft)();
    $snapshot=$draft->student_snapshot;
    $snapshot['target_academic_year']='2026-2027';
    $snapshot['source_session']='M';
    $snapshot['target_session']='III';
    $approved=$draft->replicate()->forceFill(['status'=>'approved','reference_number'=>'BCH-2627-001','target_academic_year_id'=>2,'target_session_id'=>1,'source_session_id'=>1,'student_snapshot'=>$snapshot]);
    $approved->save();
    $otherCampus=$approved->replicate()->forceFill(['campus_id'=>2,'reference_number'=>'DNG-2627-002']); $otherCampus->save();
    $otherGroup=$approved->replicate()->forceFill(['source_session_id'=>null,'reference_number'=>'BCH-2627-003']); $otherGroup->save();
    foreach (['pending','rejected'] as $status) $approved->replicate()->forceFill(['status'=>$status,'reference_number'=>null])->save();
    $this->actingAs($this->central);
    $this->get('/students/skipping-grade')->assertOk()->assertDontSee('Skipping Reports')->assertDontSee('Print Reports')
        ->assertSee('Print List')->assertSee('Print Statistics')->assertSee('Excel')->assertSee('PDF')->assertSee('Share Statistics')
        ->assertSee('data-skipping-report-print',false)->assertSee('data-skipping-share-statistics',false);
    $this->get('/students/skipping-grade?academic_year_id=2&status=approved')->assertOk()->assertViewHas('records',fn($rows)=>$rows->isEmpty());
    $year=$this->get('/students/skipping-grade?academic_year_id=1&status=approved')->assertOk()->assertSee('students/report-photo.png')->assertSee('data-skipping-report-print',false);
    expect($year->viewData('records')->total())->toBe(3)
        ->and($year->viewData('campuses')->pluck('id')->all())->toBe([1,2])
        ->and($year->viewData('reportRows')[$approved->id])->toMatchArray(['source_grade'=>'2A-M','target_grade'=>'4B-III','dob'=>'02-Mar-2017']);
    $query=['academic_year_id'=>1,'campus_id'=>1,'status'=>'approved','grade_class'=>'1:1:1','approval_number'=>'BCH-2627-001'];
    $actions=$this->get('/students/skipping-grade?'.http_build_query($query))->assertOk();
    foreach (['data-skipping-report-print','data-skipping-statistics-print'] as $attribute) {
        preg_match('/href="([^"]+)" '.$attribute.'/', $actions->getContent(), $printLink);
        expect($printLink)->toHaveCount(2);
        parse_str(parse_url(html_entity_decode($printLink[1]),PHP_URL_QUERY),$printQuery);
        expect($printQuery)->toMatchArray($query+['autoprint'=>'1'])->not->toHaveKey('per_page');
    }
    foreach (['xlsx','pdf'] as $format) {
        foreach (['list','statistics'] as $kind) {
            preg_match('/href="([^"]+\/reports\/'.$kind.'\/export\/'.$format.'[^"]*)"/', $actions->getContent(), $exportLink);
            expect($exportLink)->toHaveCount(2);
            parse_str(parse_url(html_entity_decode($exportLink[1]),PHP_URL_QUERY),$exportQuery);
            expect($exportQuery)->toMatchArray($query)->not->toHaveKey('per_page')->not->toHaveKey('autoprint');
        }
    }
    $actions->assertSee(route('student-skipping-grade.statistics.print',\Illuminate\Support\Arr::except($query,['status'])+['status'=>'approved']));
    foreach (['SOK DARA','2612345','BCH-2627-001','SOK RITH','012345678',$snapshot['name_kh']] as $term) {
        $page=$this->get('/students/skipping-grade?'.http_build_query($query+['search'=>$term]))->assertOk();
        expect($page->viewData('records')->pluck('id')->all())->toBe([$approved->id]);
        $print=$this->get('/students/skipping-grade/print-report?'.http_build_query($query+['search'=>$term]))->assertOk();
        expect($print->viewData('records')->pluck('id')->all())->toBe([$approved->id]);
    }
    $this->get('/students/skipping-grade?'.http_build_query($query+['search'=>'missing']))->assertOk()->assertViewHas('records',fn($rows)=>$rows->isEmpty());
    $print=$this->get('/students/skipping-grade/print-report?'.http_build_query($query))->assertOk()->assertSee('Skipped To')->assertSee('02-Mar-2017')->assertSee('2A-M')->assertSee('4B-III')->assertSee('2025-2026')->assertSee('2026-2027')->assertDontSee('Data Filters');
    expect($print->viewData('records')->pluck('id')->all())->toBe([$approved->id]);
    $this->get('/students/skipping-grade/print-report')->assertOk()->assertSee('Status: All Statuses');
    $this->getJson('/students/skipping-grade/print-report?status=invalid')->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->getJson('/students/skipping-grade?grade_class=bad')->assertUnprocessable()->assertJsonValidationErrors('grade_class');
    for ($i=1;$i<=24;$i++) $approved->replicate()->forceFill(['reference_number'=>'BCH-BULK-'.$i])->save();
    $list=$this->get('/students/skipping-grade?academic_year_id=1&campus_id=1&grade_class=1:1:1&status=approved&per_page=20')->assertOk();
    expect($list->viewData('records')->count())->toBe(20)->and($list->viewData('records')->total())->toBe(25);
    $all=$this->get('/students/skipping-grade/print-report?academic_year_id=1&campus_id=1&grade_class=1:1:1&status=approved&per_page=20&page=2')->assertOk();
    expect($all->viewData('records'))->toHaveCount(25);
    expect($this->get('/students/skipping-grade/print-report?status=approved')->assertOk()->viewData('records'))->toHaveCount(27);
});

it('prints every selected status using the same filters and link as the request list', function () {
    (require database_path('migrations/2026_10_10_000001_add_skipping_report_permissions.php'))->up();
    (require database_path('migrations/2026_10_10_000002_move_skipping_report_into_requests.php'))->up();
    $draft=($this->draft)();
    $approved=$draft->replicate()->forceFill(['status'=>'approved','reference_number'=>'BCH-PRINT-001']); $approved->save();
    foreach (['pending','rejected'] as $status) $draft->replicate()->forceFill(['status'=>$status])->save();
    $draft->replicate()->forceFill(['campus_id'=>2])->save();
    $this->actingAs($this->central);
    foreach ([''=>'All Statuses','draft'=>'Draft','pending'=>'Submitted','approved'=>'Approved','rejected'=>'Rejected'] as $status=>$label) {
        $query=['academic_year_id'=>1,'campus_id'=>1,'status'=>$status,'grade_class'=>'1:1:none','search'=>'012345678','per_page'=>20];
        $page=$this->get('/students/skipping-grade?'.http_build_query($query))->assertOk()->assertSee('data-skipping-report-print',false);
        preg_match('/href="([^"]+)" data-skipping-report-print/', $page->getContent(), $link);
        expect($link)->toHaveCount(2);
        $url=html_entity_decode($link[1],ENT_QUOTES);
        parse_str(parse_url($url,PHP_URL_QUERY),$params);
        expect($params)->toMatchArray(['academic_year_id'=>'1','campus_id'=>'1','grade_class'=>'1:1:none','search'=>'012345678'])
            ->and($params['status']??'')->toBe($status)->and($params)->not->toHaveKey('per_page');
        $print=$this->get($url)->assertOk()->assertSee('Status: '.$label)->assertSee('BCH');
        expect($print->viewData('records')->pluck('id')->all())->toBe($page->viewData('records')->pluck('id')->all())
            ->and($print->viewData('records'))->toHaveCount($status===''?4:1);
        if ($status==='') {
            foreach (['Draft','Submitted','Approved','Rejected'] as $rowStatus) $print->assertSee('Status: '.$rowStatus);
            $print->assertSee('Total Student: 4 ( Approved: 1 | Rejected: 1 | Draft: 1 | Submitted: 1 )');
        } else {
            $print->assertSee('Total Student: 1')->assertDontSee('( Approved:');
        }
    }
    $this->get('/students/skipping-grade?academic_year_id=1')->assertOk()->assertSee('data-skipping-report-print',false);
    expect($this->get('/students/skipping-grade/print-report?academic_year_id=1')->assertOk()->viewData('records'))->toHaveCount(5);
    $this->get('/students/skipping-grade/print-report?academic_year_id=1')->assertOk()
        ->assertSee('Total Student: 5 ( Approved: 1 | Rejected: 1 | Draft: 2 | Submitted: 1 )');
    $this->get('/students/skipping-grade?academic_year_id=1&status=draft')->assertOk()
        ->assertSee('data-skipping-report-print',false);
    $this->get('/students/skipping-grade?academic_year_id=1&search=missing')->assertOk()
        ->assertSee('data-skipping-report-help',false)->assertDontSee('data-skipping-report-print',false)
        ->assertSee('data-skipping-report-export="statistics"',false)->assertDontSee('data-skipping-report-export="list"',false);
});

it('requires requests and print permission for A4 reports and removes the former report tab permission', function () {
    (require database_path('migrations/2026_10_10_000001_add_skipping_report_permissions.php'))->up();
    (require database_path('migrations/2026_10_10_000002_move_skipping_report_into_requests.php'))->up();
    $this->actingAs($this->campus)->get('/students/skipping-grade')->assertOk();
    expect(DB::table('access_permissions')->where('code','student-skipping-grade.reports')->exists())->toBeFalse();
    $this->get('/students/skipping-grade/reports')->assertNotFound();
    $printId=DB::table('access_permissions')->where('code','student-skipping-grade.print-reports')->value('id');
    DB::table('access_role_permissions')->where('permission_id',$printId)->where('role_id',1)->delete();
    $this->get('/students/skipping-grade')->assertOk()->assertDontSee('data-skipping-report-export',false)->assertDontSee('data-skipping-share-statistics',false);
    $this->getJson('/students/skipping-grade/print-report?status=approved&academic_year_id=1')->assertForbidden();
    ($this->grant)(1,['print-reports']);
    $this->get('/students/skipping-grade/print-report?status=approved&academic_year_id=1')->assertOk();
    foreach (['requests'] as $action) {
        $id=DB::table('access_permissions')->where('code','student-skipping-grade.'.$action)->value('id');
        DB::table('access_user_permission_overrides')->updateOrInsert(['user_id'=>1,'permission_id'=>$id],['allowed'=>false]);
        DB::table('access_role_permissions')->where('permission_id',$id)->where('role_id',1)->delete();
    }
    $this->getJson('/students/skipping-grade')->assertForbidden();
    $this->getJson('/students/skipping-grade/print-report?status=approved&academic_year_id=1')->assertForbidden();
});

it('summarizes unique skipping students by current grade campus and gender using the report filters', function () {
    (require database_path('migrations/2026_10_10_000001_add_skipping_report_permissions.php'))->up();
    (require database_path('migrations/2026_10_10_000002_move_skipping_report_into_requests.php'))->up();
    $draft = ($this->draft)();
    $approved = $draft->replicate()->forceFill(['status'=>'approved','reference_number'=>'BCH-STAT-001']); $approved->save();
    $approved->replicate()->forceFill(['reference_number'=>'BCH-STAT-002'])->save(); // A second matching request must not count the same student twice.
    foreach ([2=>['2','4',1,1],3=>['4','6',1,2],4=>['N','K2',2,1]] as $id=>$placement) {
        DB::table('tb_student')->insert(['id'=>$id,'student_id'=>'STAT-'.$id,'full_name_en'=>'STUDENT '.$id,'full_name_kh'=>'សិស្ស','date_of_birth'=>'2017-03-02','gender'=>'female']);
        $snapshot = array_replace($draft->student_snapshot, ['student_id'=>'STAT-'.$id,'name_en'=>'STUDENT '.$id,'gender'=>'female','source_grade'=>$placement[0],'target_grade'=>$placement[1],'campus_en'=>$placement[2]===1?'BCH':'DNG']);
        $approved->replicate()->forceFill(['student_id'=>$id,'campus_id'=>$placement[2],'source_grade_id'=>$placement[3],'reference_number'=>'STAT-'.$id,'student_snapshot'=>$snapshot])->save();
    }
    DB::table('tb_student')->insert(['id'=>5,'student_id'=>'DRAFT-5','full_name_en'=>'DRAFT STUDENT','full_name_kh'=>'សិស្ស','date_of_birth'=>'2017-03-02','gender'=>'male']);
    $draft->replicate()->forceFill(['student_id'=>5])->save();
    $this->actingAs($this->central);
    $preview = $this->get('/students/skipping-grade/print-statistics?academic_year_id=1')->assertOk()->assertSee('Statistics of Students Skipping Grades')->assertSee('Status: Approved')->assertDontSee('2 skipped to 4')->assertDontSee('3 skipped to 5')->assertSee('4 skipped to 6')->assertSee('N skipped to K2')->assertSee('Share / A4 Image')->assertSee('Excel')->assertSee('PDF');
    $stats = $preview->viewData('statistics');
    preg_match_all('/<th[^>]*statistics-grade-header[^>]*>(.*?)<\/th>/s',$preview->getContent(),$gradeHeaders);
    $expectedHeaders=['N→K2','K1→K3','K2→G1','K3→G2',...array_map(fn($grade)=>'G'.$grade.'→G'.($grade+2),range(1,10))];
    expect(array_map(fn($header)=>trim(preg_replace('/\s+/u',' ',strip_tags($header))),$gradeHeaders[1]))->toBe($expectedHeaders);
    expect($stats['total'])->toBe(4)->and($stats['female'])->toBe(3)
        ->and($stats['totals']['2'])->toBe(['total'=>2,'female'=>1])
        ->and($stats['totals']['4'])->toBe(['total'=>1,'female'=>1])
        ->and($stats['totals']['N'])->toBe(['total'=>1,'female'=>1])
        ->and($stats['rows'][0])->toMatchArray(['campus'=>'BCH','total'=>3,'female'=>2])
        ->and($stats['rows'][1])->toMatchArray(['campus'=>'DNG','total'=>1,'female'=>1])
        ->and($stats['groupTotals']['primary'])->toMatchArray(['total'=>3,'female'=>2]);
    foreach (['','draft','pending','approved','rejected'] as $status) {
        $approvedOnly=$this->get('/students/skipping-grade/print-statistics?academic_year_id=1&status='.$status)->assertOk()
            ->assertSee('Status: Approved')->assertDontSee('statisticsStatus')->assertDontSee('Status: All Statuses');
        expect($approvedOnly->viewData('statistics')['total'])->toBe(4)
            ->and($approvedOnly->viewData('records')->pluck('status')->unique()->all())->toBe(['approved'])
            ->and($approvedOnly->viewData('exportFilters')['status'])->toBe('approved');
    }
    foreach (['campus_id=2','approval_number=STAT-4','search=STAT-4'] as $filter) {
        $stats = $this->get('/students/skipping-grade/print-statistics?academic_year_id=1&'.$filter)->assertOk()->viewData('statistics');
        expect($stats['total'])->toBe(1)->and($stats['female'])->toBe(1);
    }
    expect($this->get('/students/skipping-grade/print-statistics?grade_class=2:1:none')->assertOk()->viewData('statistics')['total'])->toBe(1);
    expect($this->get('/students/skipping-grade/print-statistics?search=missing')->assertOk()->viewData('statistics')['total'])->toBe(0);
    $this->getJson('/students/skipping-grade/print-statistics?status=invalid')->assertUnprocessable();
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','academic_year_code'=>'2627']);
    $approved->replicate()->forceFill(['academic_year_id'=>2,'reference_number'=>'BCH-NEXT-YEAR'])->save();
    expect($this->get('/students/skipping-grade/print-statistics')->assertOk()->viewData('statistics')['total'])->toBe(5);
});

it('exports filtered skipping lists and statistics as real A4 PDFs and Excel workbooks with permission checks', function () {
    (require database_path('migrations/2026_10_10_000001_add_skipping_report_permissions.php'))->up();
    (require database_path('migrations/2026_10_10_000002_move_skipping_report_into_requests.php'))->up();
    Storage::fake('public');
    $image = imagecreatetruecolor(20, 30); ob_start(); imagepng($image); $png = ob_get_clean(); imagedestroy($image);
    Storage::disk('public')->put('students/export-photo.png', $png);
    DB::table('tb_student')->where('id',1)->update(['photo_path'=>'students/export-photo.png']);
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','academic_year_code'=>'2627','lifecycle_status'=>'pending']);
    DB::table('tb_class')->where('id',2)->update(['academic_year_id'=>2]);
    $draft = $this->service->saveDraft($this->campus,array_replace($this->data,['target_academic_year_id'=>2]));
    $approved = $draft->replicate()->forceFill(['status'=>'approved','reference_number'=>'EXPORT-001','approval_date'=>'2026-09-25']); $approved->save();
    $this->actingAs($this->central);
    $this->get('/students/skipping-grade/print-report?status=approved')->assertOk()->assertSee('25-Sep-2026')->assertSee('Stu. ID:')->assertSee('No.:')->assertDontSee('Approved No.:');
    $yearSubtitle='From Academic Year: 2025-2026 Skipped to Academic Year: 2026-2027';
    $this->get('/students/skipping-grade/print-statistics?academic_year_id=1')->assertOk()->assertSee($yearSubtitle);
    $this->get('/students/skipping-grade/print-statistics?academic_year_id=1&search=missing')->assertOk()
        ->assertSee('From Academic Year: 2025-2026 Skipped to Academic Year: —')->assertDontSee($yearSubtitle);
    foreach (['list','statistics'] as $kind) {
        $query = '?academic_year_id=1&campus_id=1&status='.($kind==='statistics'?'rejected':'approved').'&approval_number=EXPORT-001';
        $xlsx = $this->get('/students/skipping-grade/reports/'.$kind.'/export/xlsx'.$query)->assertOk()->assertHeader('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'skipping-test-');
        try {
            file_put_contents($path,$xlsx->getContent()); $zip = new ZipArchive(); expect($zip->open($path))->toBeTrue();
            foreach (['[Content_Types].xml','xl/workbook.xml','xl/styles.xml','xl/worksheets/sheet1.xml'] as $part) expect(simplexml_load_string($zip->getFromName($part)))->not->toBeFalse();
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            expect($sheet)->toContain('BCH','EXPORT-001','paperSize="9"','orientation="'.($kind==='statistics'?'landscape':'portrait').'"')->not->toContain('<f>');
            if ($kind==='list') expect($sheet)->toContain('SOK DARA','សុខ ដារា','012345678','25-Sep-2026')->and($zip->getFromName('xl/media/image1.png'))->not->toBeFalse();
            else expect($sheet)->toContain($yearSubtitle,'Grand Total','Female: 0','N→K2','K1→K3','K2→G1','K3→G2',...array_map(fn($grade)=>'G'.$grade.'→G'.($grade+2),range(1,10)))->not->toContain('2 skipped to 4','3 skipped to 5');
            $zip->close();
        } finally { unlink($path); }
        $pdf = $this->get('/students/skipping-grade/reports/'.$kind.'/export/pdf'.$query)->assertOk()->assertHeader('Content-Type','application/pdf');
        expect(substr($pdf->getContent(), 0, 5))->toBe('%PDF-');
        preg_match('/\/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]/', $pdf->getContent(), $box);
        expect(round((float)$box[1], 1))->toBe($kind==='statistics'?841.9:595.3)
            ->and(round((float)$box[2], 1))->toBe($kind==='statistics'?595.3:841.9);
    }
    $this->get('/students/skipping-grade/reports/statistics/export/xlsx?status=')->assertOk();
    $this->get('/students/skipping-grade/reports/list/export/csv')->assertNotFound();
    $this->actingAs($this->campus);
    $printId = DB::table('access_permissions')->where('code','student-skipping-grade.print-reports')->value('id');
    DB::table('access_role_permissions')->where('permission_id',$printId)->where('role_id',1)->delete();
    foreach (['/print-statistics','/reports/list/export/pdf','/reports/list/export/xlsx','/reports/statistics/export/pdf','/reports/statistics/export/xlsx'] as $path) $this->getJson('/students/skipping-grade'.$path)->assertForbidden();
    ($this->grant)(1, ['print-reports']);
    $requestsId = DB::table('access_permissions')->where('code','student-skipping-grade.requests')->value('id');
    DB::table('access_user_permission_overrides')->updateOrInsert(['user_id'=>1,'permission_id'=>$requestsId],['allowed'=>false]);
    DB::table('access_role_permissions')->where('permission_id',$requestsId)->where('role_id',1)->delete();
    foreach (['/print-statistics','/reports/list/export/pdf','/reports/list/export/xlsx','/reports/statistics/export/pdf','/reports/statistics/export/xlsx'] as $path) $this->getJson('/students/skipping-grade'.$path)->assertForbidden();
});

it('paginates skipping requests with a default of 20 and supports all rows without losing filters', function () {
    $record=($this->draft)();
    for ($i=0;$i<100;$i++) $record->replicate()->save();
    $otherCampus=$record->replicate();
    $otherCampus->campus_id=2;
    $otherCampus->save();
    $this->actingAs($this->campus);
    $page=$this->get('/students/skipping-grade')->assertOk();
    expect($page->viewData('records')->perPage())->toBe(20)
        ->and($page->viewData('records')->count())->toBe(20)
        ->and($page->viewData('records')->total())->toBe(102);
    foreach ([20,50,75,100] as $size) {
        $page=$this->get('/students/skipping-grade?per_page='.$size.'&status=draft')->assertOk();
        $records=$page->viewData('records');
        expect($records->count())->toBe($size)->and($records->perPage())->toBe($size);
        parse_str(parse_url($records->nextPageUrl(),PHP_URL_QUERY),$next);
        expect($next)->toMatchArray(['per_page'=>(string)$size,'status'=>'draft','page'=>'2']);
        $page->assertSee('name="per_page" value="'.$size.'"',false);
    }
    $page=$this->get('/students/skipping-grade?per_page=20&page=6')->assertOk();
    expect($page->viewData('records')->count())->toBe(2)->and($page->viewData('records')->firstItem())->toBe(101);
    $page=$this->get('/students/skipping-grade?per_page=all&page=3')->assertOk();
    expect($page->viewData('records')->count())->toBe(102)
        ->and($page->viewData('records')->currentPage())->toBe(1)
        ->and($page->viewData('records')->hasMorePages())->toBeFalse();
    $page=$this->get('/students/skipping-grade?per_page=all&campus_id=2&status=draft&search=SOK')->assertOk();
    expect($page->viewData('records')->count())->toBe(1)->and($page->viewData('records')->first()->id)->toBe($otherCampus->id);
    $page=$this->get('/students/skipping-grade?per_page=all&search=not-found')->assertOk();
    expect($page->viewData('records')->count())->toBe(0)->and($page->viewData('records')->lastPage())->toBe(1);
    $this->getJson('/students/skipping-grade?per_page=30')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

it('scopes list campuses by year and statuses by year and campus without narrowing options by text search', function () {
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','academic_year_code'=>'2627']);
    DB::table('tb_school_info')->insert(['id'=>3,'campus_name_en'=>'TK','campus_name_kh'=>'ទួលគោក']);
    $draft=($this->draft)();
    $approved=$draft->replicate()->forceFill(['status'=>'approved']); $approved->save();
    $pending=$draft->replicate()->forceFill(['campus_id'=>2,'status'=>'pending']); $pending->save();
    $rejected=$draft->replicate()->forceFill(['academic_year_id'=>2,'campus_id'=>3,'status'=>'rejected']); $rejected->save();
    $this->actingAs($this->campus);
    $year=$this->get('/students/skipping-grade?academic_year_id=1')->assertOk();
    expect($year->viewData('campuses')->pluck('id')->all())->toBe([1,2])
        ->and(array_keys($year->viewData('statusOptions')))->toBe(['draft','pending','approved'])
        ->and($year->viewData('records')->total())->toBe(3);
    $campus=$this->get('/students/skipping-grade?academic_year_id=1&campus_id=2')->assertOk();
    expect($campus->viewData('campuses')->pluck('id')->all())->toBe([1,2])
        ->and(array_keys($campus->viewData('statusOptions')))->toBe(['pending'])
        ->and($campus->viewData('records')->pluck('id')->all())->toBe([$pending->id]);
    $status=$this->get('/students/skipping-grade?academic_year_id=1&campus_id=1&status=approved')->assertOk();
    expect(array_keys($status->viewData('statusOptions')))->toBe(['draft','approved'])
        ->and($status->viewData('records')->pluck('id')->all())->toBe([$approved->id]);
    $nextYear=$this->get('/students/skipping-grade?academic_year_id=2')->assertOk();
    expect($nextYear->viewData('campuses')->pluck('id')->all())->toBe([3])
        ->and(array_keys($nextYear->viewData('statusOptions')))->toBe(['rejected'])
        ->and($nextYear->viewData('records')->pluck('id')->all())->toBe([$rejected->id]);
    $search=$this->get('/students/skipping-grade?academic_year_id=1&campus_id=2&search=no-match')->assertOk();
    expect($search->viewData('records')->total())->toBe(0)
        ->and(array_keys($search->viewData('statusOptions')))->toBe(['pending'])
        ->and($search->viewData('campuses')->pluck('id')->all())->toBe([1,2]);
});

it('protects campus settings with separate tab and save permissions and assigned campus checks', function () {
    $this->actingAs($this->campus)->getJson('/students/skipping-grade/campus-settings')->assertForbidden();
    ($this->grant)(1,['campus-settings']);
    $page=$this->get('/students/skipping-grade/campus-settings')->assertOk()->assertSee('Campus Committee')->assertDontSee('Save Campus Settings');
    expect($page->viewData('settingsCampuses')->pluck('id')->all())->toBe([1]);
    $this->getJson('/students/skipping-grade/campus-settings?campus_id=2')->assertForbidden();
    $names=['TEACHER ONE','COORDINATOR ONE','VSP ONE','PRINCIPAL ONE'];
    $this->postJson('/students/skipping-grade/campus-settings',['campus_id'=>1,'committee_names'=>$names])->assertForbidden();
    ($this->grant)(1,['save-campus-settings']);
    $this->postJson('/students/skipping-grade/campus-settings',['campus_id'=>2,'committee_names'=>$names])->assertForbidden();
    $this->postJson('/students/skipping-grade/campus-settings',['campus_id'=>1,'committee_names'=>$names])->assertOk();
    expect(\App\Models\SkippingGradeCampusSetting::namesFor(1))->toBe($names);
    $this->postJson('/students/skipping-grade/campus-settings',['campus_id'=>1,'committee_names'=>[4=>'CENTRAL']])->assertUnprocessable()->assertJsonValidationErrors('committee_names');
    $this->postJson('/students/skipping-grade/campus-settings',['campus_id'=>1,'committee_names'=>[str_repeat('x',201)]])->assertUnprocessable()->assertJsonValidationErrors('committee_names.0');
    expect(\App\Models\SkippingGradeCampusSetting::count())->toBe(1);
    $savePermission=\App\Models\Permission::where('code','student-skipping-grade.save-campus-settings')->firstOrFail();
    $permissions=\App\Models\Permission::all();
    expect(PermissionHierarchy::normalizeIds([$savePermission->id],$permissions))->not->toContain($savePermission->id);
    DB::table('access_user_campuses')->where('user_id',1)->delete();
    $this->postJson('/students/skipping-grade/campus-settings',['campus_id'=>1,'committee_names'=>$names])->assertForbidden();
    $this->get('/students/skipping-grade/campus-settings')->assertOk()->assertSee('No assigned campuses');
});

it('uses the selected campus committee for new requests and preserves existing request names', function () {
    $names=['TEACHER ONE','COORDINATOR ONE','VSP ONE','PRINCIPAL ONE'];
    \App\Models\SkippingGradeCampusSetting::create(['campus_id'=>1,'committee_names'=>$names]);
    \App\Models\SkippingGradeCampusSetting::create(['campus_id'=>2,'committee_names'=>array_fill(0,4,'OTHER CAMPUS')]);
    $this->actingAs($this->campus)->getJson('/students/skipping-grade/enrollment/1')->assertOk()->assertJsonPath('campus_committee_names',$names);
    $data=$this->data;
    unset($data['committee_names']);
    $this->postJson('/students/skipping-grade',$data)->assertOk();
    $record=StudentSkippingGrade::firstOrFail();
    expect(array_slice($record->committee_names,0,4))->toBe($names);
    \App\Models\SkippingGradeCampusSetting::where('campus_id',1)->update(['committee_names'=>array_fill(0,4,'NEW DEFAULT')]);
    $this->postJson('/students/skipping-grade/'.$record->id.'/update',$data)->assertOk();
    expect(array_slice($record->fresh()->committee_names,0,4))->toBe($names);
    $override=array_fill(0,4,'REQUEST OVERRIDE');
    $this->postJson('/students/skipping-grade/'.$record->id.'/update',$data+['committee_names'=>$override])->assertOk();
    expect(array_slice($record->fresh()->committee_names,0,4))->toBe($override);
    expect(\App\Models\SkippingGradeCampusSetting::namesFor(2))->toBe(array_fill(0,4,'OTHER CAMPUS'));
});

it('restricts both template editors previews and saves to explicit template permissions', function () {
    $revision=app(\App\Services\SkippingGradeTemplateService::class)->revision([]);
    foreach (['request','approval'] as $form) {
        $this->actingAs($this->campus)->getJson('/students/skipping-grade/templates/'.$form)->assertForbidden();
        $this->getJson('/students/skipping-grade/templates/'.$form.'/preview')->assertForbidden();
        $this->postJson('/students/skipping-grade/templates/'.$form,['blocks'=>[],'revision'=>$revision])->assertForbidden();
    }
    expect(SkippingGradeSetting::current()->request_template)->toBeNull();
});

it('renders every editable block in both sample previews without creating a request or moving a student', function () {
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','academic_year_code'=>'AY26-27','lifecycle_status'=>'pending']);
    ($this->configure)();
    SkippingGradeSetting::current()->update(['number_prefix'=>'SK','signer_name_kh'=>'គីង រដ្ឋមុនី']);
    $templates=app(\App\Services\SkippingGradeTemplateService::class);
    foreach (['request','approval'] as $form) {
        $editor=$this->actingAs($this->central)->get('/students/skipping-grade/templates/'.$form)->assertOk();
        $editor->assertSee('Customize Request Form')->assertSee('Customize Approval Form')->assertSee('Premium Form Editor')->assertSee('data-template-config',false)->assertSee('data-template-add="checkbox"',false)->assertSee('Selected by default on new forms')->assertSee('Form Option Label');
        preg_match('~<script type="application/json" data-template-config>(.*?)</script>~s',$editor->getContent(),$config);
        $config=json_decode($config[1],true,512,JSON_THROW_ON_ERROR);
        expect($config)->toHaveKeys(['template','revision','defaults','values']);
        expect($config['values']['requested_academic_year'])->toBe('2026-2027')
            ->and($config['values']['academic_year'])->toBe('2026-2027')
            ->and($config['values']['source_academic_year'])->toBe('2025-2026')
            ->and($config['values']['reference_number'])->toBe('SK2526-001')
            ->and($config['values']['signer_name_kh'])->toBe('គីង រដ្ឋមុនី');
        $response=$this->actingAs($this->central)->get('/students/skipping-grade/templates/'.$form.'/preview')->assertOk();
        foreach (array_keys($templates->definitions($form)) as $key) {
            if ($form==='request' && in_array($key,['average-label','reason-label','reason','committee-heading-kh'],true)) {
                $response->assertDontSee('data-skipping-block="'.$key.'"',false);
                continue;
            }
            if ($form==='approval' && (in_array($key,['student-divider','school-external','check-school-external','average','notes'],true) || str_starts_with($key,'criterion-') || str_starts_with($key,'check-criterion-'))) {
                $response->assertDontSee('data-skipping-block="'.$key.'"',false);
                continue;
            }
            $response->assertSee('data-skipping-block="'.$key.'"',false);
        }
        $response->assertSee('STUDENT NAME')->assertSee('charset="UTF-8"',false);
        if ($form==='approval') $response->assertSee('គីង រដ្ឋមុនី')->assertDontSee('ឈ្មោះអ្នកអនុម័ត')
            ->assertSee('class="approval-stamp" src="data:image/png;base64,',false)
            ->assertSee('class="approval-signature" src="data:image/png;base64,',false);
        if ($form==='request') {
            preg_match('~<span[^>]*data-skipping-block="year"[^>]*>(.*?)</span>~s',$response->getContent(),$year);
            expect($year[1])->toBe('2026-2027');
        }
    }
    expect(StudentSkippingGrade::count())->toBe(0)->and(StudentEnrollment::find(1)->grade_id)->toBe(1);
});

it('prints bilingual approval student labels and only the requested grade number', function ($grade,$number) {
    DB::table('tb_grade')->where('id',2)->update(['grade_short_name'=>$grade]);
    $record=($this->pending)();
    ($this->configure)();
    $record=$this->service->approve($this->central,$record,$this->approval);
    $this->actingAs($this->central);
    $approval=$this->get('/students/skipping-grade/'.$record->id.'/print/approval')->assertOk()
        ->assertSee('របស់សិស្សឈ្មោះ៖')->assertSee("Student's Name")->assertSee('អត្តលេខ៖')->assertSee('Student ID')
        ->assertSee('គីង រដ្ឋមុនី')->assertDontSee('ឈ្មោះអ្នកអនុម័ត')
        ->assertSee('ស្នើសុំផ្លោះចូលថ្នាក់ទី៖')->assertSee('Requested Grade')->assertSee('approval-student-label-en',false)
        ->assertSee('គណៈកម្មការ / Committee')->assertSee('approval-committee-heading',false)
        ->assertSee('<span class="approval-committee-kh">គណៈកម្មការ</span> / Committee',false)
        ->assertSee('<span class="approval-committee-kh">គណៈកម្មការសម្រេច</span> / The Committee Decides',false);
    preg_match('~<span[^>]*data-skipping-block="target-grade"[^>]*>(.*?)</span>~s',$approval->getContent(),$value);
    expect($value[1])->toBe($number);
    $request=$this->get('/students/skipping-grade/'.$record->id.'/print/request')->assertOk();
    preg_match('~<span[^>]*data-skipping-block="target-grade"[^>]*>(.*?)</span>~s',$request->getContent(),$value);
    expect($value[1])->toBe($grade);
    $requestPreview=$this->get('/students/skipping-grade/templates/request/preview')->assertOk();
    preg_match('~<span[^>]*data-skipping-block="target-grade"[^>]*>(.*?)</span>~s',$requestPreview->getContent(),$value);
    expect($value[1])->toBe('5');
    $preview=$this->get('/students/skipping-grade/templates/approval/preview')->assertOk()->assertSee("Student's Name")->assertSee('គណៈកម្មការ / Committee');
    preg_match('~<span[^>]*data-skipping-block="target-grade"[^>]*>(.*?)</span>~s',$preview->getContent(),$value);
    expect($value[1])->toBe('5');
})->with([['G4','4'],['៥','5'],['Grade 12','12']]);

it('formats the third reference using the approval date in both languages', function ($date,$khmer,$english) {
    $record=($this->draft)();
    $record->forceFill(['application_date'=>'2026-09-10','received_date'=>'2026-09-11','review_date'=>'2026-09-12','approval_date'=>$date]);
    $templates=app(\App\Services\SkippingGradeTemplateService::class);
    $values=$templates->values($record,[]);
    expect($values['approval_date_reference_kh'])->toBe($khmer)
        ->and($values['approval_date_reference_en'])->toBe($english);
    $khmerText=(string)$templates->text('approval','reference-received',[],$values);
    $englishText=(string)$templates->text('approval','reference-received-en',[],$values);
    expect($khmerText)->toContain('តាមស្មារតីនៃអង្គប្រជុំរបស់គណៈកម្មការវាយតម្លៃសិស្សផ្លោះថ្នាក់នៅថ្ងៃទី '.$khmer)
        ->and($englishText)->toContain('Resolution of the Grade-Skipping Evaluation Committee meeting on '.$english)
        ->not->toContain('10-September-2026','12-September-2026','●');
    expect((string)$templates->text('approval','reference-reviewed',[],$values))
        ->toContain('តាមពាក្យស្នើសុំរបស់មាតាបីតាសិស្សនៅថ្ងៃទី ១០ កញ្ញា ២០២៦')
        ->and((string)$templates->text('approval','reference-reviewed-en',[],$values))
        ->toContain('Grade-Skipping Application submitted by the parents on 10-Sep-2026')
        ->not->toContain('11-Sep-2026','●');
})->with([
    ['2026-09-16','១៦ កញ្ញា ២០២៦','16-September-2026'],
    ['2028-02-29','២៩ កុម្ភៈ ២០២៨','29-February-2028'],
    [null,'',''],
]);

it('formats the Phnom Penh signoff using the saved approval date in both languages', function ($date,$khmer,$english) {
    $record=($this->draft)();
    $record->forceFill(['approval_date'=>$date]);
    $templates=app(\App\Services\SkippingGradeTemplateService::class);
    $values=$templates->values($record,[]);
    expect($values['approval_date_long_kh'])->toBe($khmer)
        ->and($values['approval_date_long_en'])->toBe($english);
    expect((string)$templates->text('approval','signoff-date',[],$values))->toContain('រាជធានីភ្នំពេញ '.$khmer)
        ->and((string)$templates->text('approval','signoff-date-en',[],$values))->toContain('Phnom Penh, '.$english.'.');
})->with([
    ['2026-09-25','ថ្ងៃទី២៥ ខែកញ្ញា ឆ្នាំ២០២៦','September 25, 2026'],
    ['2028-02-29','ថ្ងៃទី២៩ ខែកុម្ភៈ ឆ្នាំ២០២៨','February 29, 2028'],
    [null,'',''],
]);

it('saves independent templates and safely renders automatic values in actual request and approval prints', function () {
    $templates=app(\App\Services\SkippingGradeTemplateService::class);
    $requestBlocks=['heading-en'=>['text'=>'Custom Request {student_name_en} <script>alert(1)</script>','font'=>'Georgia','size'=>24,'color'=>'#123456','left'=>2,'top'=>-1,'bold'=>true],
        'label-name-kh'=>['text'=>'ឈ្មោះថ្មី៖']];
    $approvalBlocks=['heading'=>['text'=>'Custom Approval {student_id}','font'=>'Arial','size'=>26]];
    $this->actingAs($this->central)->postJson('/students/skipping-grade/templates/request',['blocks'=>$requestBlocks,'revision'=>$templates->revision([])])->assertOk();
    $this->postJson('/students/skipping-grade/templates/approval',['blocks'=>$approvalBlocks,'revision'=>$templates->revision([])])->assertOk();
    expect(SkippingGradeSetting::current()->request_template)->toBe($requestBlocks)->and(SkippingGradeSetting::current()->approval_template)->toBe($approvalBlocks);
    $record=($this->pending)();
    $this->get('/students/skipping-grade/'.$record->id.'/print/request')->assertOk()->assertSee('Custom Request SOK DARA')->assertSee('ឈ្មោះថ្មី៖')
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)->assertDontSee('<script>alert(1)</script>',false)
        ->assertSee('font-size:24pt;')->assertSee('left:2mm;')->assertSee('top:-1mm;')->assertDontSee('Custom Approval');
    ($this->configure)();
    $approved=$this->service->approve($this->central,$record,$this->approval);
    $this->get('/students/skipping-grade/'.$approved->id.'/print/approval')->assertOk()->assertSee('Custom Approval 2612345')->assertDontSee('Custom Request');
});

it('saves school logo position size and removal separately in both form templates and printouts', function ($form) {
    $templates=app(\App\Services\SkippingGradeTemplateService::class);
    $record=($this->pending)();
    ($this->configure)();
    $record=$this->service->approve($this->central,$record,$this->approval);
    $this->actingAs($this->central);
    $page=$this->get('/students/skipping-grade/templates/'.$form)->assertOk();
    preg_match('~<script type="application/json" data-template-config>(.*?)</script>~s',$page->getContent(),$config);
    $config=json_decode($config[1],true,512,JSON_THROW_ON_ERROR);
    expect($config['definitions']['school-logo'])->toBe(['type'=>'image','width'=>$form==='request'?68.4:60,'height'=>$form==='request'?21.6:27]);
    $blocks=['school-logo'=>['type'=>'image','left'=>12,'top'=>-4,'width'=>42,'height'=>16]];
    $this->postJson('/students/skipping-grade/templates/'.$form,['blocks'=>$blocks,'revision'=>$templates->revision([])])->assertOk();
    expect(SkippingGradeSetting::current()->{$form.'_template'})->toBe($blocks)
        ->and(SkippingGradeSetting::current()->{($form==='request'?'approval':'request').'_template'})->toBeNull();
    foreach (['/students/skipping-grade/templates/'.$form.'/preview','/students/skipping-grade/'.$record->id.'/print/'.$form] as $url) {
        $page=$this->get($url)->assertOk();
        preg_match('~<span[^>]*data-skipping-block="school-logo"[^>]*>~',$page->getContent(),$logo);
        expect($logo[0])->toContain('skipping-template-image','data-object-type="image"','width:42mm;','height:16mm;','transform:translate(12mm,-4mm);');
    }
    $source='data:image/png;base64,iVBORw0KGgo=';
    $logo=(string)$templates->logo($form,$blocks,$source);
    expect($logo)->toContain('src="'.$source.'"','alt="Western International School"','width:42mm;','height:16mm;');
    $removed=['school-logo'=>$blocks['school-logo']+['removed'=>true]];
    $this->postJson('/students/skipping-grade/templates/'.$form,['blocks'=>$removed,'revision'=>$templates->revision($blocks)])->assertOk();
    $page=$this->get('/students/skipping-grade/'.$record->id.'/print/'.$form)->assertOk();
    preg_match('~<span[^>]*data-skipping-block="school-logo"[^>]*>~',$page->getContent(),$logo);
    expect($logo[0])->toContain('is-removed','data-removed="true"');
    $this->postJson('/students/skipping-grade/templates/'.$form,['blocks'=>[],'revision'=>$templates->revision($removed)])->assertOk();
    $page=$this->get('/students/skipping-grade/'.$record->id.'/print/'.$form)->assertOk();
    preg_match('~<span[^>]*data-skipping-block="school-logo"[^>]*>~',$page->getContent(),$logo);
    expect($logo[0])->not->toContain('is-removed','width:42mm;','height:16mm;');
})->with(['request','approval']);

it('does not allow arbitrary image uploads or text styles through the form logo controls', function ($key,$block,$field) {
    $templates=app(\App\Services\SkippingGradeTemplateService::class);
    $this->actingAs($this->central)->postJson('/students/skipping-grade/templates/approval',['blocks'=>[$key=>$block],'revision'=>$templates->revision([])])
        ->assertUnprocessable()->assertJsonValidationErrors('blocks.'.$key.'.'.$field);
    expect(SkippingGradeSetting::current()->approval_template)->toBeNull();
})->with([
    ['custom-image',['type'=>'image','left'=>20,'top'=>20,'width'=>40,'height'=>20],'type'],
    ['school-logo',['text'=>'Replacement image'],'text'],
    ['school-logo',['font'=>'Arial'],'font'],
    ['school-logo',['width'=>211],'width'],
]);

it('saves bullet formatting for original and added text in previews and printouts without changing content', function ($form,$original) {
    $templates=app(\App\Services\SkippingGradeTemplateService::class);
    $content="For {student_name_en}\r\n\r\n<script>alert(1)</script>";
    $blocks=[
        $original=>['text'=>$content,'bullet'=>true],
        'custom-bullets'=>['type'=>'text','text'=>$content,'bullet'=>true,'left'=>20,'top'=>180,'width'=>90,'height'=>25,'font'=>'Arial','size'=>14,'color'=>'#123456'],
    ];
    $record=($this->pending)();
    ($this->configure)();
    $record=$this->service->approve($this->central,$record,$this->approval);
    $this->actingAs($this->central)->postJson('/students/skipping-grade/templates/'.$form,['blocks'=>$blocks,'revision'=>$templates->revision([])])->assertOk();
    expect(SkippingGradeSetting::current()->{$form.'_template'})->toBe($blocks);
    $parse=function ($html) {
        $dom=new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new DOMXPath($dom);
    };
    foreach (['/students/skipping-grade/templates/'.$form.'/preview'=>'STUDENT NAME','/students/skipping-grade/'.$record->id.'/print/'.$form=>'SOK DARA'] as $url=>$name) {
        $page=$this->get($url)->assertOk()->assertDontSee('<script>alert(1)</script>',false);
        $xpath=$parse($page->getContent());
        foreach ([$original,'custom-bullets'] as $key) {
            $element=$xpath->query('//*[@data-skipping-block="'.$key.'"]')->item(0);
            expect($element->getAttribute('class'))->toContain('skipping-template-bulleted');
            expect($element->getAttribute('data-template-text'))->toBe($content);
            expect($xpath->query('.//*[contains(@class,"skipping-template-bullet-marker")]',$element)->length)->toBe(2);
            expect($xpath->query('.//*[contains(@class,"skipping-template-bullet-space")]',$element)->length)->toBe(1);
            expect($xpath->query('.//script',$element)->length)->toBe(0);
            expect($element->textContent)->toContain('For '.$name,'<script>alert(1)</script>');
        }
    }
    $plain=$blocks;
    foreach ($plain as &$block) $block['bullet']=false;
    unset($block);
    $this->postJson('/students/skipping-grade/templates/'.$form,['blocks'=>$plain,'revision'=>$templates->revision($blocks)])->assertOk();
    $page=$this->get('/students/skipping-grade/'.$record->id.'/print/'.$form)->assertOk();
    $xpath=$parse($page->getContent());
    foreach ([$original,'custom-bullets'] as $key) {
        $element=$xpath->query('//*[@data-skipping-block="'.$key.'"]')->item(0);
        expect($element->getAttribute('class'))->not->toContain('skipping-template-bulleted');
        expect($element->textContent)->toBe(str_replace('{student_name_en}','SOK DARA',$content));
    }
})->with([['request','criterion-recommendation-en'],['approval','implementation']]);

it('rejects invalid bullet formatting and bullet formatting on shapes', function ($key,$block) {
    $templates=app(\App\Services\SkippingGradeTemplateService::class);
    $this->actingAs($this->central)->postJson('/students/skipping-grade/templates/request',['blocks'=>[$key=>$block],'revision'=>$templates->revision([])])
        ->assertUnprocessable()->assertJsonValidationErrors('blocks.'.$key.'.bullet');
    expect(SkippingGradeSetting::current()->request_template)->toBeNull();
})->with([
    ['reason',['bullet'=>'arbitrary']],
    ['average',['bullet'=>true]],
    ['criteria-check-age',['bullet'=>true]],
    ['custom-shape',['type'=>'box','bullet'=>true,'left'=>20,'top'=>180,'width'=>4,'height'=>4]],
]);

it('saves movable lines score borders custom objects and removals in both templates and printouts', function () {
    $templates=app(\App\Services\SkippingGradeTemplateService::class);
    $custom=[
        'custom-text-1'=>['type'=>'text','text'=>'Extra {student_name_en} <img src=x>','left'=>20,'top'=>200,'width'=>80,'height'=>12,'size'=>14,'border_style'=>'solid','border_width'=>1],
        'custom-box-1'=>['type'=>'box','left'=>20,'top'=>220,'width'=>40,'height'=>20,'border_color'=>'#123456','border_style'=>'dashed','border_width'=>2,'fill'=>'#abcdef'],
        'custom-line-1'=>['type'=>'line','left'=>20,'top'=>245,'width'=>60,'height'=>0,'border_style'=>'dotted','border_width'=>1],
        'student-name'=>['removed'=>true],
    ];
    $request=$custom+[
        'line-student-name'=>['left'=>3,'top'=>2,'width'=>55,'border_color'=>'#ff0000','border_style'=>'dashed','border_width'=>2],
        'average'=>['left'=>4,'top'=>-2,'width'=>35,'height'=>10,'border_color'=>'#112233','border_width'=>2],
        'committee-line-0-signature'=>['removed'=>true],
    ];
    $approval=$custom+['student-divider'=>['left'=>1,'top'=>3,'width'=>150],'check-age-standard'=>['left'=>1,'top'=>3,'width'=>5,'height'=>5]];
    foreach (['request'=>$request,'approval'=>$approval] as $form=>$blocks) {
        $this->actingAs($this->central)->postJson('/students/skipping-grade/templates/'.$form,['blocks'=>$blocks,'revision'=>$templates->revision([])])->assertOk();
        expect(SkippingGradeSetting::current()->{$form.'_template'})->toBe($blocks);
        $preview=$this->get('/students/skipping-grade/templates/'.$form.'/preview')->assertOk();
        $preview->assertSee('Extra STUDENT NAME &lt;img src=x&gt;',false)->assertSee('data-skipping-block="custom-box-1"',false)->assertSee('border-color:#123456;',false)
            ->assertSee('background-color:#abcdef;',false)->assertSee('width:60mm;',false);
        preg_match('~<span[^>]*data-skipping-block="student-name"[^>]*>~',$preview->getContent(),$removed);
        expect($removed[0])->toContain('is-removed','data-removed="true"');
    }
    $record=($this->pending)();
    $this->get('/students/skipping-grade/'.$record->id.'/print/request')->assertOk()->assertSee('Extra SOK DARA')->assertSee('transform:translate(3mm,2mm);',false)->assertSee('left:4mm;',false);
    ($this->configure)();
    $record=$this->service->approve($this->central,$record,$this->approval);
    $this->get('/students/skipping-grade/'.$record->id.'/print/approval')->assertOk()->assertSee('Extra SOK DARA')->assertSee('transform:translate(1mm,3mm);',false);
    $updated=$request;
    unset($updated['custom-box-1'],$updated['student-name']);
    $this->postJson('/students/skipping-grade/templates/request',['blocks'=>$updated,'revision'=>$templates->revision($request)])->assertOk();
    $print=$this->get('/students/skipping-grade/'.$record->id.'/print/request')->assertOk()->assertDontSee('data-skipping-block="custom-box-1"',false)->assertSee('data-skipping-block="custom-text-1"',false);
    preg_match('~<span[^>]*data-skipping-block="student-name"[^>]*>~',$print->getContent(),$restored);
    expect($restored[0])->toContain('data-removed="false"')->not->toContain('is-removed');
    expect(SkippingGradeSetting::current()->approval_template)->toBe($approval);
});

it('persists added checkbox states geometry and deletion in both previews and actual prints', function () {
    $templates=app(\App\Services\SkippingGradeTemplateService::class);
    $checkbox=['type'=>'checkbox','left'=>25,'top'=>180,'width'=>5,'height'=>5,'color'=>'#206bc4','border_color'=>'#123456','border_style'=>'solid','border_width'=>1,'fill'=>'#ffffff'];
    $blocks=[
        'custom-checked'=>$checkbox+['checked'=>true],
        'custom-unchecked'=>$checkbox+['checked'=>false],
        'custom-default'=>$checkbox,
    ];
    $record=($this->pending)();
    ($this->configure)();
    $record=$this->service->approve($this->central,$record,$this->approval);
    foreach (['request'=>'criteria-check-age','approval'=>'check-age-standard'] as $form=>$original) {
        $this->actingAs($this->central)->postJson('/students/skipping-grade/templates/'.$form,['blocks'=>$blocks,'revision'=>$templates->revision([])])->assertOk();
        expect(SkippingGradeSetting::current()->{$form.'_template'})->toBe($blocks);
        foreach (['/students/skipping-grade/templates/'.$form.'/preview','/students/skipping-grade/'.$record->id.'/print/'.$form] as $url) {
            $response=$this->get($url)->assertOk();
            foreach (['custom-checked'=>true,'custom-unchecked'=>false,'custom-default'=>false] as $key=>$checked) {
                preg_match('~<span[^>]*data-skipping-block="'.$key.'"[^>]*>~',$response->getContent(),$element);
                expect($element[0])->toContain('skipping-template-checkbox','skipping-template-custom','print-check','width:5mm;','height:5mm;','transform:translate(25mm,180mm);','border-color:#123456;');
                expect(str_contains($element[0],'print-check checked'))->toBe(str_contains($url,'/preview') && $checked);
            }
            preg_match('~<span[^>]*data-skipping-block="'.$original.'"[^>]*>~',$response->getContent(),$element);
            expect($element[0])->toContain('print-check checked');
        }
        $updated=$blocks;
        unset($updated['custom-checked']);
        $this->postJson('/students/skipping-grade/templates/'.$form,['blocks'=>$updated,'revision'=>$templates->revision($blocks)])->assertOk();
        $this->get('/students/skipping-grade/templates/'.$form.'/preview')->assertOk()->assertDontSee('data-skipping-block="custom-checked"',false)->assertSee('data-skipping-block="custom-unchecked"',false);
        $this->get('/students/skipping-grade/'.$record->id.'/print/'.$form)->assertOk()->assertDontSee('data-skipping-block="custom-checked"',false)->assertSee('data-skipping-block="custom-unchecked"',false);
    }
});

it('links replacement checkboxes to saved request and approval values without adding duplicate entry options', function () {
    $templates=app(\App\Services\SkippingGradeTemplateService::class);
    $criteria=['age'=>false,'average'=>true,'documents'=>false,'recommendation'=>true];
    $record=$this->service->saveDraft($this->campus,array_replace($this->data,['criteria'=>$criteria]));
    $record=$this->service->submit($this->campus,$record,now()->toDateString());
    ($this->configure)();
    $record=$this->service->approve($this->central,$record,array_replace($this->approval,[
        'age_decision'=>'exception','school_decision'=>'external','obligations'=>['conduct'=>false,'rules'=>true,'study'=>false],
    ]));
    foreach (['request','approval'] as $form) {
        $expected=[];
        foreach ($criteria as $key=>$checked) $expected[($form==='request'?'criteria-check-':'check-criterion-').$key]=$checked;
        if ($form==='approval') $expected+=[
            'check-age-standard'=>true,'check-age-exception'=>false,'check-school-internal'=>false,'check-school-external'=>true,
            'check-obligation-conduct'=>false,'check-obligation-rules'=>true,'check-obligation-study'=>false,
        ];
        $blocks=[];
        foreach ($expected as $source=>$checked) {
            $blocks['custom-'.$source]=['type'=>'checkbox','checkbox_source'=>$source,'checked'=>!$checked,'option_label'=>'Should not create another option','left'=>20,'top'=>180,'width'=>4,'height'=>4];
            $blocks[$source]=['removed'=>true];
        }
        $this->actingAs($this->central)->postJson('/students/skipping-grade/templates/'.$form,['blocks'=>$blocks,'revision'=>$templates->revision([])])->assertOk();
        expect($templates->checkboxOptions($blocks))->toBe([]);
        $page=$this->get('/students/skipping-grade/templates/'.$form)->assertOk()->assertSee('Checkbox Value')->assertSee('data-template-checkbox-source',false);
        if ($form==='approval') $page->assertSee('Decision: Approved')->assertSee('Decision: Rejected');
        $print=$this->get('/students/skipping-grade/'.$record->id.'/print/'.$form)->assertOk();
        foreach ($expected as $source=>$checked) {
            preg_match('~<span[^>]*data-skipping-block="custom-'.$source.'"[^>]*>~',$print->getContent(),$element);
            expect(str_contains($element[0],'print-check checked'))->toBe($checked);
            preg_match('~<span[^>]*data-skipping-block="'.$source.'"[^>]*>~',$print->getContent(),$original);
            if ($form==='approval' && ($source==='check-school-external' || str_starts_with($source,'check-criterion-'))) expect($original)->toBe([]);
            else expect($original[0])->toContain('is-removed');
        }
        $preview=$this->get('/students/skipping-grade/templates/'.$form.'/preview')->assertOk();
        if ($form==='approval') {
            preg_match('~<span[^>]*data-skipping-block="custom-check-age-standard"[^>]*>~',$preview->getContent(),$element);
            expect($element[0])->toContain('print-check checked');
            preg_match('~<span[^>]*data-skipping-block="custom-check-age-exception"[^>]*>~',$preview->getContent(),$element);
            expect($element[0])->not->toContain('print-check checked');
        }
    }
    $this->actingAs($this->campus)->get('/students/skipping-grade/create')->assertOk()->assertDontSee('Should not create another option');
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertDontSee('Should not create another option');
    expect($record->fresh()->approval_snapshot['age_decision'])->toBe('exception')
        ->and($record->fresh()->approval_snapshot['custom_options'])->toBe([]);
});

it('rejects invalid checkbox bindings and bindings on other object types', function ($form,$key,$type,$source) {
    $templates=app(\App\Services\SkippingGradeTemplateService::class);
    $block=['type'=>$type,'checkbox_source'=>$source,'left'=>20,'top'=>180,'width'=>4,'height'=>4];
    $this->actingAs($this->central)->postJson('/students/skipping-grade/templates/'.$form,['blocks'=>[$key=>$block],'revision'=>$templates->revision([])])
        ->assertUnprocessable()->assertJsonValidationErrors('blocks.'.$key.'.checkbox_source');
    expect(SkippingGradeSetting::current()->{$form.'_template'})->toBeNull();
})->with([
    ['approval','custom-invalid','checkbox','check-unknown'],
    ['approval','custom-invalid','checkbox','criteria-check-age'],
    ['request','custom-invalid','checkbox','check-age-standard'],
    ['approval','custom-invalid','text','check-age-standard'],
    ['approval','check-age-standard','checkbox','check-age-standard'],
]);

it('shows template checkbox options on request entry and saves editable selections for the student printout', function () {
    $template=[
        'custom-extra'=>['type'=>'checkbox','option_label'=>'Additional document verified','checked'=>true,'left'=>20,'top'=>160,'width'=>4,'height'=>4],
        'custom-second'=>['type'=>'checkbox','option_label'=>'ពិនិត្យឯកសារ <b>extra</b>','left'=>20,'top'=>170,'width'=>4,'height'=>4],
    ];
    $templates=app(\App\Services\SkippingGradeTemplateService::class);
    $this->actingAs($this->central)->postJson('/students/skipping-grade/templates/request',['blocks'=>$template,'revision'=>$templates->revision([])])->assertOk();
    $create=$this->actingAs($this->campus)->get('/students/skipping-grade/create')->assertOk()->assertSee('Additional Request Options')->assertSee('Additional document verified')->assertSee('&lt;b&gt;extra&lt;/b&gt;',false);
    preg_match('~<input class="form-check-input"[^>]*name="custom_options\[custom-extra\]"[^>]*>~',$create->getContent(),$input);
    expect($input[0])->toContain('checked');
    $data=$this->data+['custom_options'=>['custom-extra'=>0,'custom-second'=>1]];
    $this->postJson('/students/skipping-grade',$data)->assertOk();
    $record=StudentSkippingGrade::firstOrFail();
    expect($record->criteria['custom_options'])->toBe([
        'custom-extra'=>['label'=>'Additional document verified','checked'=>false],
        'custom-second'=>['label'=>'ពិនិត្យឯកសារ <b>extra</b>','checked'=>true],
    ]);
    $edit=$this->get('/students/skipping-grade/'.$record->id.'/edit')->assertOk();
    foreach (['custom-extra'=>false,'custom-second'=>true] as $key=>$checked) {
        preg_match('~<input class="form-check-input"[^>]*name="custom_options\['.$key.'\]"[^>]*>~',$edit->getContent(),$input);
        expect(str_contains($input[0],'checked'))->toBe($checked);
        $print=$this->get('/students/skipping-grade/'.$record->id.'/print/request')->assertOk();
        preg_match('~<span[^>]*data-skipping-block="'.$key.'"[^>]*>~',$print->getContent(),$element);
        expect(str_contains($element[0],'print-check checked'))->toBe($checked);
    }
    $this->postJson('/students/skipping-grade/'.$record->id.'/update',array_replace($data,['custom_options'=>['custom-extra'=>0,'custom-second'=>0]]))->assertOk();
    expect($record->fresh()->criteria['custom_options']['custom-second']['checked'])->toBeFalse();
    $template['custom-extra']['option_label']='A changed label';
    SkippingGradeSetting::current()->update(['request_template'=>$template]);
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('Additional document verified')->assertDontSee('A changed label');
});

it('saves approval checkbox choices separately from request choices and keeps their labels on the approved record', function () {
    $checkbox=['type'=>'checkbox','left'=>20,'top'=>180,'width'=>4,'height'=>4,'checked'=>true];
    SkippingGradeSetting::current()->update([
        'request_template'=>['custom-shared'=>$checkbox+['option_label'=>'Request extra option']],
        'approval_template'=>['custom-shared'=>$checkbox+['option_label'=>'Approval extra option'],'custom-approved'=>$checkbox+['option_label'=>'Committee verified extra evidence']],
    ]);
    $record=$this->service->saveDraft($this->campus,$this->data+['custom_options'=>['custom-shared'=>true]]);
    $record=$this->service->submit($this->campus,$record,now()->toDateString());
    $this->actingAs($this->central)->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('Additional Approval Options')->assertSee('name="custom_options[custom-approved]"',false);
    ($this->configure)();
    $this->postJson('/students/skipping-grade/'.$record->id.'/approve',$this->approval+['custom_options'=>['custom-shared'=>0,'custom-approved'=>1]])->assertOk();
    $record->refresh();
    expect($record->criteria['custom_options']['custom-shared']['checked'])->toBeTrue()
        ->and($record->approval_snapshot['custom_options']['custom-shared']['checked'])->toBeFalse()
        ->and($record->approval_snapshot['custom_options']['custom-approved'])->toBe(['label'=>'Committee verified extra evidence','checked'=>true]);
    foreach (['request'=>true,'approval'=>false] as $form=>$checked) {
        $print=$this->get('/students/skipping-grade/'.$record->id.'/print/'.$form)->assertOk();
        preg_match('~<span[^>]*data-skipping-block="custom-shared"[^>]*>~',$print->getContent(),$element);
        expect(str_contains($element[0],'print-check checked'))->toBe($checked);
    }
    SkippingGradeSetting::current()->update(['approval_template'=>[]]);
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('Committee verified extra evidence')->assertSee('Approval extra option');
    expect(StudentEnrollment::find(1)->grade_id)->toBe(2);
});

it('rejects removed unknown and non-checkbox option selections without saving or moving a student', function ($form, $key) {
    $template=[
        'custom-removed'=>['type'=>'checkbox','removed'=>true,'left'=>20,'top'=>180,'width'=>4,'height'=>4],
        'custom-text'=>['type'=>'text','text'=>'Not a checkbox','left'=>20,'top'=>180,'width'=>40,'height'=>10],
    ];
    SkippingGradeSetting::current()->update([$form.'_template'=>$template]);
    if ($form==='request') {
        $this->actingAs($this->campus)->postJson('/students/skipping-grade',$this->data+['custom_options'=>[$key=>1]])->assertUnprocessable()->assertJsonValidationErrors('custom_options.'.$key);
        expect(StudentSkippingGrade::count())->toBe(0);
    } else {
        $record=($this->pending)();
        ($this->configure)();
        $this->actingAs($this->central)->postJson('/students/skipping-grade/'.$record->id.'/approve',$this->approval+['custom_options'=>[$key=>1]])->assertUnprocessable()->assertJsonValidationErrors('custom_options.'.$key);
        expect($record->fresh()->status)->toBe('pending')->and(Storage::disk('local')->allFiles('grade-skipping/approvals'))->toBe([]);
    }
    expect(StudentEnrollment::find(1)->grade_id)->toBe(1);
})->with([
    ['request','custom-unknown'],['request','custom-removed'],['request','custom-text'],
    ['approval','custom-unknown'],['approval','custom-removed'],['approval','custom-text'],
]);

it('rejects unknown template blocks tokens and unsafe styles', function ($blocks, $field) {
    $this->actingAs($this->central)->postJson('/students/skipping-grade/templates/request',[
        'blocks'=>$blocks,'revision'=>app(\App\Services\SkippingGradeTemplateService::class)->revision([]),
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(SkippingGradeSetting::current()->request_template)->toBeNull();
})->with([
    'unknown block'=>[['fake'=>['text'=>'test']],'blocks'],
    'unknown token'=>[['heading-en'=>['text'=>'{unknown_student_value}']],'blocks.heading-en.text'],
    'unsafe font'=>[['heading-en'=>['font'=>'Arial; background:url(https://example.com)']],'blocks.heading-en.font'],
    'unsafe color'=>[['heading-en'=>['color'=>'red;background:blue']],'blocks.heading-en.color'],
    'too large'=>[['heading-en'=>['size'=>61]],'blocks.heading-en.size'],
    'outside paper'=>[['heading-en'=>['left'=>211]],'blocks.heading-en.left'],
    'unexpected property'=>[['heading-en'=>['html'=>'<b>text</b>']],'blocks.heading-en'],
    'invalid added type'=>[['custom-test'=>['type'=>'score']],'blocks.custom-test.type'],
    'invalid checked state'=>[['custom-test'=>['type'=>'checkbox','left'=>20,'top'=>20,'width'=>4,'height'=>4,'checked'=>'yes']],'blocks.custom-test.checked'],
    'original checked override'=>[['criteria-check-age'=>['checked'=>true]],'blocks.criteria-check-age.checked'],
    'text checked override'=>[['custom-test'=>['type'=>'text','left'=>20,'top'=>20,'width'=>40,'height'=>14,'checked'=>true]],'blocks.custom-test.checked'],
    'empty option label'=>[['custom-test'=>['type'=>'checkbox','left'=>20,'top'=>20,'width'=>4,'height'=>4,'option_label'=>'']],'blocks.custom-test.option_label'],
    'oversized option label'=>[['custom-test'=>['type'=>'checkbox','left'=>20,'top'=>20,'width'=>4,'height'=>4,'option_label'=>str_repeat('a',201)]],'blocks.custom-test.option_label'],
    'original option label override'=>[['criteria-check-age'=>['option_label'=>'Override']],'blocks.criteria-check-age.option_label'],
    'missing added geometry'=>[['custom-test'=>['type'=>'box']],'blocks.custom-test.left'],
    'too wide'=>[['line-student-name'=>['width'=>211]],'blocks.line-student-name.width'],
    'unsafe border'=>[['line-student-name'=>['border_color'=>'red;position:fixed']],'blocks.line-student-name.border_color'],
    'unsafe fill'=>[['heading-en'=>['fill'=>'url(https://example.com)']],'blocks.heading-en.fill'],
    'wrong original type'=>[['student-name'=>['type'=>'box']],'blocks.student-name.type'],
]);

it('updates the request Khmer title in saved templates and printouts while preserving customization', function () {
    $record=($this->draft)();
    $this->actingAs($this->central)->get('/students/skipping-grade/'.$record->id.'/print/request')
        ->assertOk()->assertSee('ពាក្យសុំផ្លោះថ្នាក់');
    $settings=SkippingGradeSetting::current();
    $heading=['text'=>'ពាក្យសុំឡើងថ្នាក់','size'=>20,'x'=>2];
    $approval=['heading'=>['text'=>'Custom approval title']];
    $settings->update(['request_template'=>['heading-kh'=>$heading],'approval_template'=>$approval]);
    $migration=require database_path('migrations/2026_10_09_000001_update_skipping_grade_request_khmer_title.php');
    $migration->up();
    expect($settings->fresh()->request_template['heading-kh'])->toBe(array_replace($heading,['text'=>'ពាក្យសុំផ្លោះថ្នាក់']))
        ->and($settings->fresh()->approval_template)->toBe($approval);
    $this->get('/students/skipping-grade/'.$record->id.'/print/request')->assertOk()->assertSee('ពាក្យសុំផ្លោះថ្នាក់');
    $settings->update(['request_template'=>['heading-kh'=>['text'=>'Custom request title']]]);
    $migration->up();
    expect($settings->fresh()->request_template['heading-kh']['text'])->toBe('Custom request title');
});

it('updates request field label colons while preserving formatting and unrelated text', function () {
    $settings=SkippingGradeSetting::current();
    $template=[
        'label-name-kh'=>['text'=>'ឈ្មោះសិស្ស៖','size'=>11,'left'=>2],
        'label-date-kh'=>['text'=>'ថ្ងៃធ្វើពាក្យ៖'],
        'custom-note'=>['text'=>'Custom note៖'],
    ];
    $approval=['reference'=>['text'=>'លេខ៖ {reference_number}']];
    $settings->update(['request_template'=>$template,'approval_template'=>$approval]);
    $migration=require database_path('migrations/2026_10_09_000002_update_skipping_grade_request_label_colons.php');
    $migration->up();
    $template['label-name-kh']['text']='ឈ្មោះសិស្ស:';
    $template['label-date-kh']['text']='ថ្ងៃធ្វើពាក្យ:';
    expect($settings->fresh()->request_template)->toBe($template)
        ->and($settings->fresh()->approval_template)->toBe($approval);
    $this->actingAs($this->central)->get('/students/skipping-grade/'.($this->draft)()->id.'/print/request')
        ->assertOk()->assertSee('ឈ្មោះសិស្ស:')->assertSee('ថ្ងៃធ្វើពាក្យ:');
});

it('updates the printed bilingual age criterion without changing request selections or approval templates', function () {
    // Historical migrations must be tested with their original input, even when
    // the editable form's current wording changes later.
    $old=['kh'=>'ត្រូវមានអាយុស្របតាមថ្នាក់ ដោយមានសំបុត្រកំណើតដើមជាភស្តុតាង។','en'=>'The student meets the legal age requirement for the requested grade, supported by the original birth certificate (for example, 6 years old for Grade 1).'];
    $template=[
        'criterion-age-kh'=>['text'=>$old['kh'],'size'=>11],
        'criterion-age-en'=>['text'=>$old['en'],'left'=>4],
    ];
    $approval=['criterion-age'=>['text'=>$old['kh']]];
    $settings=SkippingGradeSetting::current();
    $settings->update(['request_template'=>$template,'approval_template'=>$approval]);
    (require database_path('migrations/2026_10_09_000009_update_skipping_grade_request_age_criterion.php'))->up();
    (require database_path('migrations/2026_10_09_000013_correct_skipping_grade_request_age_criterion.php'))->up();
    $kh='ត្រូវមានអាយុត្រឹមត្រូវតាមច្បាប់រដ្ឋ (មានសេចក្តីចម្លងសំបុត្រកំណើតច្បាប់ដើមជាភស្តុតាង)';
    $en='The student must meet the legal requirement for the grade based on the original cophy of their birth certificate as proof from the district authorities. (e.g. 6 years old for Grade 1).';
    $template['criterion-age-kh']['text']=$kh;
    $template['criterion-age-en']['text']=$en;
    expect($settings->fresh()->request_template)->toBe($template)
        ->and($settings->fresh()->approval_template)->toBe($approval);
    $record=($this->draft)();
    $this->actingAs($this->central)->get('/students/skipping-grade/'.$record->id.'/print/request')
        ->assertOk()->assertSee($kh)->assertSee($en);
    expect($record->fresh()->criteria['age'])->toBeTrue();
});

it('formats the documents example separately while retaining editable text and escaping markup', function ($bullet) {
    $templates=app(\App\Services\SkippingGradeTemplateService::class);
    $example='ឧ. សិស្សត្រូវរៀនចប់ថ្នាក់ទី៣ មុនចូលថ្នាក់ទី៤';
    $content='Supporting documents ('.$example.') <script>alert(1)</script>';
    $html=(string)$templates->text('request','criterion-documents-kh',[
        'criterion-documents-kh'=>['text'=>$content,'size'=>11,'bullet'=>$bullet],
    ],[]);
    $dom=new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath=new DOMXPath($dom);
    $block=$xpath->query('//*[@data-skipping-block="criterion-documents-kh"]')->item(0);
    $label=$xpath->query('.//*[@class="request-criterion-example"]',$block);
    expect($label->length)->toBe(1)
        ->and($label->item(0)->textContent)->toBe($example)
        ->and($block->getAttribute('data-template-text'))->toBe($content)
        ->and($xpath->query('.//script',$block)->length)->toBe(0);
})->with(['plain'=>[false],'bulleted'=>[true]]);

it('formats Cambodian telephone numbers on request prints without changing the saved number', function ($phone, $formatted) {
    $record=($this->draft)();
    $record->update(['parent_phone'=>$phone]);
    $page=$this->actingAs($this->central)->get('/students/skipping-grade/'.$record->id.'/print/request')->assertOk();
    $dom=new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$page->getContent());
    $xpath=new DOMXPath($dom);
    expect($xpath->query('//*[@data-skipping-block="phone"]')->item(0)->textContent)->toBe($formatted)
        ->and($record->fresh()->parent_phone)->toBe($phone);
})->with([
    'local nine digits'=>['012998898','012 998 898'],
    'local ten digits'=>['0977878787','097 787 8787'],
    'international short'=>['+85512676787','+855 12 676 787'],
    'international long'=>['+855978787878','+855 97 878 7878'],
    'existing spacing'=>['097 787 8787','097 787 8787'],
    'international dial prefix'=>['0085512676787','+855 12 676 787'],
    'country code without plus'=>['855978787878','+855 97 878 7878'],
    'foreign number'=>['+66 81 234 5678','+66 81 234 5678'],
    'incomplete number'=>['01234','01234'],
    'empty number'=>[null,''],
]);

it('prints the score next to the Khmer average criterion without the Overall Average label', function () {
    $record=($this->draft)();
    $page=$this->actingAs($this->central)->get('/students/skipping-grade/'.$record->id.'/print/request')
        ->assertOk()->assertDontSee('Overall Average:');
    $dom=new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$page->getContent());
    $xpath=new DOMXPath($dom);
    $row=$xpath->query('//p[@class="request-average-heading"]')->item(0);
    expect($xpath->query('.//*[@data-skipping-block="criterion-average-kh"]',$row)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-skipping-block="average"]',$row)->item(0)->textContent)->toBe('95 / 100')
        ->and($xpath->query('.//*[@data-skipping-block="criterion-average-en"]',$row)->length)->toBe(0)
        ->and($xpath->query('//*[@data-skipping-block="criterion-average-en"]')->length)->toBe(1);
});

it('prevents stale template overwrites and restores the original without changing the other form', function () {
    $templates=app(\App\Services\SkippingGradeTemplateService::class);
    $revision=$templates->revision([]);
    $blocks=['heading-en'=>['text'=>'Saved heading']];
    $this->actingAs($this->central)->postJson('/students/skipping-grade/templates/request',['blocks'=>$blocks,'revision'=>$revision])->assertOk();
    SkippingGradeSetting::current()->update(['approval_template'=>['heading'=>['size'=>30]]]);
    $this->postJson('/students/skipping-grade/templates/request',['blocks'=>[],'revision'=>$revision])->assertConflict();
    expect(SkippingGradeSetting::current()->request_template)->toBe($blocks);
    $this->postJson('/students/skipping-grade/templates/request',['blocks'=>[],'revision'=>$templates->revision($blocks)])->assertOk();
    expect(SkippingGradeSetting::current()->request_template)->toBe([])->and(SkippingGradeSetting::current()->approval_template)->toBe(['heading'=>['size'=>30]]);
    $this->get('/students/skipping-grade/templates/request/preview')->assertOk()->assertSee('Grade Skipping Application Form')->assertDontSee('Saved heading');
});

it('grants campus request actions but reserves central approval and settings for explicit permissions', function () {
    foreach (['view','create','update','submit','print'] as $action) expect(StudentSkippingGradePermissions::allows($this->campus,$action))->toBeTrue();
    foreach (StudentSkippingGradePermissions::CENTRAL_ACTIONS as $action) expect(StudentSkippingGradePermissions::allows($this->campus,$action))->toBeFalse();
    $tree=PermissionHierarchy::tree(\App\Models\Permission::all());
    expect($tree['students']['modules']['student-skipping-grade']['actions'])->toHaveCount(count(StudentSkippingGradePermissions::catalog())-1);
    expect(DB::table('access_role_permissions')->where('role_id',2)->count())->toBe(count(StudentSkippingGradePermissions::catalog()));
});

it('saves a draft snapshot and preserves the current enrollment until final approval', function () {
    $record=($this->draft)();
    expect($record->status)->toBe('draft')->and($record->student_snapshot['source_grade'])->toBe('G2')->and($record->student_snapshot['target_grade'])->toBe('G4');
    expect(StudentEnrollment::find(1)->grade_id)->toBe(1)->and(StudentEnrollmentHistory::count())->toBe(0);
    $this->data['reason']='Updated reason';
    expect($this->service->saveDraft($this->campus,$this->data,$record)->reason)->toBe('Updated reason');
});

it('shows all campus students and requests but only permits campus changes for assigned campuses', function () {
    DB::table('tb_student')->insert(['id'=>2,'student_id'=>'OTHER123','full_name_en'=>'OTHER CAMPUS STUDENT','full_name_kh'=>'សុខ','date_of_birth'=>'2017-03-02']);
    DB::table('tb_student_enrollment')->insert(['id'=>2,'student_id'=>2,'campus_id'=>2,'academic_year_id'=>1,'grade_id'=>1,'class_id'=>1]);
    $data=array_replace($this->data,['enrollment_id'=>2]);
    $this->actingAs($this->campus)->get('/students/skipping-grade/create')->assertOk()->assertDontSee('DNG')->assertSee('request_action=create',false);
    $this->getJson('/students/skipping-grade/students?request_action=create&campus_id=2')->assertOk()->assertJsonCount(0,'students');
    $this->getJson('/students/skipping-grade/source-classes?request_action=create&campus_id=2')->assertOk()->assertJsonCount(0,'classes');
    $this->getJson('/students/skipping-grade/enrollment/2?request_action=create')->assertForbidden();
    $this->getJson('/students/skipping-grade/students?campus_id=2')->assertOk()->assertJsonPath('students.0.label','OTHER CAMPUS STUDENT');
    $this->getJson('/students/skipping-grade/source-classes?campus_id=2')->assertOk()->assertJsonPath('classes.0.value','1:1');
    $this->getJson('/students/skipping-grade/enrollment/2')->assertOk()->assertJsonPath('can_request',false);
    $this->getJson('/students/skipping-grade/classes?enrollment_id=2&grade_id=2')->assertOk();
    $this->postJson('/students/skipping-grade',$data)->assertForbidden();

    // Assignment to a second campus enables creation there, without changing the active campus.
    DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>2]);
    $this->postJson('/students/skipping-grade',$data)->assertOk();
    $record=StudentSkippingGrade::firstOrFail();
    DB::table('access_user_campuses')->where('user_id',1)->where('campus_id',2)->delete();
    $this->get('/students/skipping-grade?campus_id=2')->assertOk()->assertSee('OTHER CAMPUS STUDENT')->assertDontSee(route('student-skipping-grade.edit',$record));
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertDontSee('Edit Draft')->assertDontSee('Submit Parent-signed Request');
    $this->getJson('/students/skipping-grade/'.$record->id.'/edit')->assertForbidden();
    $this->postJson('/students/skipping-grade/'.$record->id.'/update',$data)->assertForbidden();
    $this->postJson('/students/skipping-grade/'.$record->id.'/submit',['parent_signed'=>1,'parent_signed_date'=>now()->toDateString()])->assertForbidden();
    $this->get('/students/skipping-grade/'.$record->id.'/print/request')->assertOk()->assertSee('OTHER CAMPUS STUDENT');
    $printId=DB::table('access_permissions')->where('code','student-skipping-grade.print')->value('id');
    DB::table('access_role_permissions')->where('role_id',1)->where('permission_id',$printId)->delete();
    $this->getJson('/students/skipping-grade/'.$record->id.'/print/request')->assertForbidden();
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertDontSee('Print Request');
});

it('offers table editing only for authorized campus drafts and locks edits once submitted to central office', function () {
    $record=($this->draft)();
    $editUrl=route('student-skipping-grade.edit',$record);
    $updateUrl=route('student-skipping-grade.update',$record);
    $this->actingAs($this->campus)->get('/students/skipping-grade')->assertOk()->assertSee($editUrl);
    $this->get($editUrl)->assertOk();
    $data=array_replace($this->data,['reason'=>'Updated by campus registrar']);
    $this->postJson($updateUrl,$data)->assertOk();
    expect($record->fresh()->reason)->toBe('Updated by campus registrar');

    $updateId=DB::table('access_permissions')->where('code','student-skipping-grade.update')->value('id');
    DB::table('access_role_permissions')->where('role_id',1)->where('permission_id',$updateId)->delete();
    $this->get('/students/skipping-grade')->assertOk()->assertDontSee($editUrl);
    $this->getJson($editUrl)->assertForbidden();
    $this->postJson($updateUrl,$this->data)->assertForbidden();
    ($this->grant)(1,['update']);

    $this->service->submit($this->campus,$record->fresh(),now()->toDateString());
    $submitted=$record->fresh()->toArray();
    $this->get('/students/skipping-grade')->assertOk()->assertDontSee($editUrl);
    $this->getJson($editUrl)->assertForbidden();
    $this->postJson($updateUrl,$this->data)->assertForbidden();
    expect($record->fresh()->toArray())->toBe($submitted);
});

it('allows explicitly authorized central approval across campuses without campus request editing access', function () {
    DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>2]);
    StudentEnrollment::whereKey(1)->update(['campus_id'=>2]);
    $record=($this->pending)();
    ($this->configure)();
    $this->actingAs($this->central)->getJson('/students/skipping-grade/'.$record->id.'/edit')->assertForbidden();
    $this->postJson('/students/skipping-grade/'.$record->id.'/approve',$this->approval)->assertOk();
    expect($record->fresh()->status)->toBe('approved')->and(StudentEnrollment::find(1)->campus_id)->toBe(2);
});

it('lets central editors change draft and submitted requests across campuses while preserving submission and enrollment', function ($status) {
    DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>2]);
    StudentEnrollment::whereKey(1)->update(['campus_id'=>2]);
    $record=($this->draft)();
    if ($status==='pending') $record=$this->service->submit($this->campus,$record,now()->toDateString(),UploadedFile::fake()->create('signed.pdf',10,'application/pdf'));
    DB::table('access_user_campuses')->where('user_id',1)->where('campus_id',2)->delete();
    $editUrl=route('student-skipping-grade.edit',$record);
    $updateUrl=route('student-skipping-grade.update',$record);
    $this->actingAs($this->central)->getJson($editUrl)->assertForbidden();
    $this->postJson($updateUrl,$this->data)->assertForbidden();
    ($this->grant)(2,['central-update']);
    $this->get('/students/skipping-grade')->assertOk()->assertSee($editUrl);
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee($editUrl);
    $this->get($editUrl)->assertOk();
    $this->getJson('/students/skipping-grade/enrollment/1')->assertOk()->assertJsonPath('can_update',true)->assertJsonPath('can_request',false);
    $before=collect($record->toArray())->only(['status','parent_signed_at','signed_request_path','submitted_at','submitted_by'])->all();
    $source=StudentEnrollment::find(1)->toArray();
    $data=array_replace($this->data,['reason'=>'Corrected by Central Office','parent_name'=>'New Parent','average_score'=>96]);
    $this->postJson($updateUrl,$data)->assertOk();
    $record->refresh();
    expect($record->reason)->toBe('Corrected by Central Office')->and($record->parent_name)->toBe('NEW PARENT')
        ->and($record->updated_by)->toBe(2)->and(collect($record->toArray())->only(array_keys($before))->all())->toBe($before)
        ->and(StudentEnrollment::find(1)->toArray())->toBe($source)->and(StudentEnrollmentHistory::count())->toBe(0);
    if ($record->signed_request_path) Storage::disk('local')->assertExists($record->signed_request_path);
    $this->postJson($updateUrl,array_replace($data,['enrollment_id'=>999]))->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');
    $this->actingAs($this->campus)->getJson($editUrl)->assertForbidden();
    $this->postJson($updateUrl,$data)->assertForbidden();
    foreach (['approved','rejected'] as $closedStatus) {
        $record->update(['status'=>$closedStatus]);
        $this->actingAs($this->central)->get('/students/skipping-grade')->assertOk()->assertDontSee($editUrl);
        $this->getJson($editUrl)->assertForbidden();
        $this->postJson($updateUrl,$data)->assertForbidden();
    }
})->with(['draft','pending']);

it('controls each tab independently and prevents view-only settings and template users from saving', function () {
    $requestsId=DB::table('access_permissions')->where('code','student-skipping-grade.requests')->value('id');
    DB::table('access_role_permissions')->where('role_id',1)->where('permission_id',$requestsId)->delete();
    ($this->grant)(1,['request-template']);
    $this->actingAs($this->campus)->get('/students/skipping-grade')->assertRedirect('/students/skipping-grade/templates/request');
    $this->getJson('/students/skipping-grade/students?campus_id=1')->assertForbidden();
    $this->getJson('/students/skipping-grade/create')->assertForbidden();
    $editor=$this->get('/students/skipping-grade/templates/request')->assertOk();
    expect($editor->viewData('canEdit'))->toBeFalse();
    $editor->assertDontSee('data-bs-title="Approval Settings"',false)->assertDontSee('data-bs-title="Customize Approval Form"',false);
    $this->get('/students/skipping-grade/templates/request/preview')->assertOk();
    $this->getJson('/students/skipping-grade/templates/approval')->assertForbidden();
    $this->getJson('/students/skipping-grade/templates/approval/preview')->assertForbidden();
    $revision=app(\App\Services\SkippingGradeTemplateService::class)->revision([]);
    $this->postJson('/students/skipping-grade/templates/request',['blocks'=>[],'revision'=>$revision])->assertForbidden();
    ($this->grant)(1,['edit-request-template']);
    $this->postJson('/students/skipping-grade/templates/request',['blocks'=>[],'revision'=>$revision])->assertOk();
    $this->postJson('/students/skipping-grade/templates/approval',['blocks'=>[],'revision'=>$revision])->assertForbidden();
    ($this->grant)(1,['settings']);
    $settings=$this->get('/students/skipping-grade/settings')->assertOk();
    expect($settings->viewData('canSave'))->toBeFalse();
    $settings->assertDontSee('Save Approval Settings');
    $this->postJson('/students/skipping-grade/settings',[])->assertForbidden();
    ($this->grant)(1,['save-settings']);
    $this->postJson('/students/skipping-grade/settings',[])->assertUnprocessable();
});

it('lists every skipping tab and action on the permission page and normalizes tab dependencies', function () {
    $permissions=\App\Models\Permission::all();
    $tree=PermissionHierarchy::tree($permissions);
    $html=view('partials.permission-tree',['permissionHierarchy'=>$tree,'permissionPrefix'=>'test','assignedPermissions'=>collect()])->render();
    expect($html)->toContain('STU. SKIPPING GRADE');
    foreach (StudentSkippingGradePermissions::catalog() as $action=>$label) {
        if ($action!=='view') expect($html)->toContain('student-skipping-grade.'.$action);
    }
    $ids=$permissions->filter(fn($permission)=>$permission->code==='students.view' || $permission->module==='student-skipping-grade')->pluck('id')->all();
    expect(PermissionHierarchy::normalizeIds($ids,$permissions))->toHaveCount(count($ids));
    $requestTab=$permissions->firstWhere('code','student-skipping-grade.requests')->id;
    $selected=PermissionHierarchy::normalizeIds(array_values(array_diff($ids,[$requestTab])),$permissions);
    foreach (['create','update','submit','approve','reject','print'] as $action) expect($selected)->not->toContain($permissions->firstWhere('code','student-skipping-grade.'.$action)->id);
    expect($selected)->toContain($permissions->firstWhere('code','student-skipping-grade.request-template')->id);
});

it('migrates existing tab access without overwriting explicit permission choices', function () {
    $migration=require database_path('migrations/2026_10_08_000003_add_skipping_grade_tab_permissions.php');
    $migration->down();
    $migration->up();
    expect(StudentSkippingGradePermissions::allows($this->campus,'requests'))->toBeTrue()
        ->and(StudentSkippingGradePermissions::allows($this->central,'edit-request-template'))->toBeTrue()
        ->and(StudentSkippingGradePermissions::allows($this->campus,'settings'))->toBeFalse();
    $id=DB::table('access_permissions')->where('code','student-skipping-grade.edit-request-template')->value('id');
    DB::table('access_user_permission_overrides')->where('user_id',2)->where('permission_id',$id)->update(['allowed'=>false]);
    $count=DB::table('access_role_permissions')->count();
    $migration->up();
    expect(DB::table('access_user_permission_overrides')->where('user_id',2)->where('permission_id',$id)->value('allowed'))->toBe(0)
        ->and(DB::table('access_role_permissions')->count())->toBe($count)
        ->and(StudentSkippingGradePermissions::allows($this->central,'edit-request-template'))->toBeFalse();
});

it('saves parent guardian names in uppercase on creation and update while preserving Khmer', function () {
    $this->actingAs($this->campus)->postJson('/students/skipping-grade',array_replace($this->data,['parent_name'=>'Sok rith សុខ']))->assertOk();
    $record=StudentSkippingGrade::firstOrFail();
    expect($record->parent_name)->toBe('SOK RITH សុខ');
    $this->postJson('/students/skipping-grade/'.$record->id.'/update',array_replace($this->data,['parent_name'=>'New Guardian']))->assertOk();
    expect($record->fresh()->parent_name)->toBe('NEW GUARDIAN');
});

it('limits request committee inputs to campus roles and approval defaults to central roles', function () {
    SkippingGradeSetting::current()->update(['committee_names'=>array_fill(0,8,'Legacy Default')]);
    $request=\Illuminate\Http\Request::create('/students/skipping-grade/create');
    $request->setUserResolver(fn()=>$this->campus);
    $data=app(\App\Http\Controllers\StudentSkippingGradeController::class)->create($request)->getData();
    expect(array_keys($data['campusCommitteeRoles']))->toBe([0,1,2,3])->and($data['record']->committee_names)->toBe([]);
    $request->setUserResolver(fn()=>$this->central);
    $settingsData=app(\App\Http\Controllers\StudentSkippingGradeController::class)->settings($request)->getData();
    expect(array_keys($settingsData['centralCommitteeRoles']))->toBe([4,5,6,7]);
});

it('uses central settings in request prints and freezes those names when the request is submitted', function () {
    $central=[4=>'CENTRAL IP',5=>'CENTRAL REGISTRAR',6=>'CENTRAL ACADEMIC',7=>'CENTRAL VP'];
    SkippingGradeSetting::current()->update(['committee_names'=>array_replace(array_fill(0,4,'Ignored Campus Default'),$central)]);
    $data=array_replace($this->data,['committee_names'=>[0=>'CAMPUS TEACHER',1=>'CAMPUS KH',2=>'CAMPUS VSP',3=>'CAMPUS PRINCIPAL']]);
    $this->actingAs($this->campus)->postJson('/students/skipping-grade',array_replace($data,['committee_names'=>$data['committee_names']+[4=>'FORGED IP']]))
        ->assertUnprocessable()->assertJsonValidationErrors('committee_names');
    $record=$this->service->saveDraft($this->campus,array_replace($data,['committee_names'=>$data['committee_names']+[4=>'FORGED IP']]));
    expect($record->committee_names)->toBe(array_replace($data['committee_names'],$central));
    SkippingGradeSetting::current()->update(['committee_names'=>array_replace($central,[4=>'NEW CENTRAL IP'])]);
    $this->get('/students/skipping-grade/'.$record->id.'/print/request')->assertOk()->assertSee('CAMPUS TEACHER')->assertSee('NEW CENTRAL IP')->assertSee('CENTRAL VP')->assertDontSee('FORGED IP');
    $record=$this->service->submit($this->campus,$record,now()->toDateString());
    expect($record->committee_names[4])->toBe('NEW CENTRAL IP');
    SkippingGradeSetting::current()->update(['committee_names'=>array_replace($central,[4=>'LATEST CENTRAL IP'])]);
    $this->get('/students/skipping-grade/'.$record->id.'/print/request')->assertOk()->assertSee('NEW CENTRAL IP')->assertDontSee('LATEST CENTRAL IP');
});

it('saves only central committee defaults without shifting their positions on the request form', function () {
    SkippingGradeSetting::current()->update(['committee_names'=>array_fill(0,8,'Legacy Default')]);
    $data=['signer_name_kh'=>'អនុប្រធាន','signer_title_kh'=>'អនុប្រធាន','signer_title_en'=>'Vice President','number_prefix'=>'SG',
        'committee_names'=>[4=>'IP NAME',5=>'REGISTRAR NAME',6=>'ACADEMIC NAME',7=>'VP NAME']];
    $this->actingAs($this->central)->postJson('/students/skipping-grade/settings',$data)->assertOk();
    expect(SkippingGradeSetting::current()->committee_names)->toBe($data['committee_names']);
    $this->postJson('/students/skipping-grade/settings',array_replace($data,['committee_names'=>[0=>'CAMPUS TEACHER']]))
        ->assertUnprocessable()->assertJsonValidationErrors('committee_names');
});

it('rejects invalid targets averages and inactive source enrollments without saving', function ($change, $field) {
    if (isset($change['source'])) StudentEnrollment::whereKey(1)->update($change['source']);
    if (isset($change['year'])) DB::table('tb_academic_year')->where('id',1)->update($change['year']);
    try { $this->service->saveDraft($this->campus,array_replace($this->data,$change['data']??[])); $this->fail('Expected validation'); } catch (ValidationException $e) { expect($e->errors())->toHaveKey($field); }
    expect(StudentSkippingGrade::count())->toBe(0)->and(StudentEnrollmentHistory::count())->toBe(0);
})->with([
    'same grade'=>[['data'=>['target_grade_id'=>1]],'target_grade_id'],
    'mismatched class'=>[['data'=>['target_class_id'=>1]],'target_class_id'],
    'unknown group'=>[['data'=>['target_session_id'=>88]],'target_session_id'],
    'over score scale'=>[['data'=>['average_score'=>95,'average_scale'=>10]],'average_score'],
    'withdrawn'=>[['source'=>['enrollment_status'=>'withdrawn']],'enrollment_id'],
    'closed year'=>[['year'=>['lifecycle_status'=>'ended']],'enrollment_id'],
    'summer'=>[['year'=>['period_type'=>'summer']],'enrollment_id'],
]);

it('blocks duplicate open requests and editing after submission', function () {
    $record=($this->pending)();
    expect(fn()=>($this->draft)())->toThrow(ValidationException::class);
    expect(fn()=>$this->service->saveDraft($this->campus,$this->data,$record))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(fn()=>$this->service->submit($this->campus,$record,now()->toDateString()))->toThrow(ValidationException::class);
});

it('requires signed confirmations and returns field-level JSON errors on mobile', function () {
    $this->actingAs($this->campus);
    $this->postJson('/students/skipping-grade',[])->assertUnprocessable()->assertJsonValidationErrors(['enrollment_id','target_grade_id','target_class_id','parent_name']);
    $record=($this->draft)();
    $this->postJson('/students/skipping-grade/'.$record->id.'/submit',['parent_signed_date'=>now()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('parent_signed');
    expect($record->fresh()->status)->toBe('draft');
    $this->postJson('/students/skipping-grade/'.$record->id.'/submit',['parent_signed'=>1,'parent_signed_date'=>now()->toDateString()])->assertOk();
    $this->actingAs($this->central)->postJson('/students/skipping-grade/'.$record->id.'/approve',array_diff_key($this->approval,['vp_signed'=>true]))->assertUnprocessable()->assertJsonValidationErrors('vp_signed');
});

it('denies campus users central actions and protects cross-campus student records', function () {
    $record=($this->pending)();
    $this->actingAs($this->campus)->postJson('/students/skipping-grade/'.$record->id.'/approve',$this->approval)->assertForbidden();
    $this->getJson('/students/skipping-grade/settings')->assertForbidden();
    StudentEnrollment::whereKey(1)->update(['campus_id'=>2]);
    $this->getJson('/students/skipping-grade/enrollment/1')->assertOk()->assertJsonPath('can_request',false)->assertJsonPath('can_update',false);
    $this->postJson('/students/skipping-grade',$this->data)->assertForbidden();
});

it('loads a bounded campus student search and existing parent details', function () {
    $this->actingAs($this->campus)->getJson('/students/skipping-grade/students?q=SOK')->assertOk()->assertJsonCount(1,'students');
    $this->getJson('/students/skipping-grade/students?q=SO&campus_id=2')->assertOk()->assertJsonCount(0,'students');
    $this->getJson('/students/skipping-grade/students')->assertOk()->assertJsonCount(0,'students');
    $this->getJson('/students/skipping-grade/enrollment/1')->assertOk()->assertJsonPath('parents.0.name','SOK RITH');
    $this->getJson('/students/skipping-grade/classes?enrollment_id=1&grade_id=2')->assertOk()->assertJsonPath('classes.0.id',2);
});

it('loads shared class letters and allows the request to save and move the student on approval', function () {
    DB::table('tb_class')->update(['grade_id'=>null,'academic_year_id'=>null]);
    $this->actingAs($this->campus)->getJson('/students/skipping-grade/classes?enrollment_id=1&grade_id=2')
        ->assertOk()->assertJsonCount(2,'classes')->assertJsonPath('classes.0.id',1)->assertJsonPath('classes.1.id',2);
    $record=($this->pending)();
    expect($record->target_class_id)->toBe(2);
    ($this->configure)();
    $this->service->approve($this->central,$record,$this->approval);
    expect(StudentEnrollment::find(1)->grade_id)->toBe(2)->and(StudentEnrollment::find(1)->class_id)->toBe(2);
});

it('keeps requested class options scoped to active shared or matching grade and year classes', function () {
    DB::table('tb_class')->insert([
        ['id'=>3,'class_name'=>'C','class_order'=>3,'grade_id'=>null,'academic_year_id'=>null,'status'=>1],
        ['id'=>4,'class_name'=>'D','class_order'=>4,'grade_id'=>null,'academic_year_id'=>2,'status'=>1],
        ['id'=>5,'class_name'=>'E','class_order'=>5,'grade_id'=>null,'academic_year_id'=>null,'status'=>0],
    ]);
    $this->actingAs($this->campus)->getJson('/students/skipping-grade/classes?enrollment_id=1&grade_id=2')
        ->assertOk()->assertJsonCount(2,'classes')->assertJsonPath('classes.0.id',2)->assertJsonPath('classes.1.id',3);
    foreach ([4,5] as $class) {
        try {
            $this->service->saveDraft($this->campus,array_replace($this->data,['target_class_id'=>$class]));
            $this->fail('Invalid class was accepted.');
        } catch (ValidationException $e) { expect($e->errors())->toHaveKey('target_class_id'); }
    }
});

it('shows only the English name in student choices while allowing ID and both language searches', function ($term) {
    $this->actingAs($this->campus)->getJson('/students/skipping-grade/students?q='.urlencode($term))
        ->assertOk()->assertJsonCount(1,'students')->assertJsonPath('students.0.id',1)
        ->assertJsonPath('students.0.label','SOK DARA')
        ->assertJsonPath('students.0.search_text','2612345 SOK DARA សុខ ដារា');
})->with(['2612345','DARA','សុខ']);

it('sorts student choices A to Z by English name before applying the result limit', function () {
    for ($id=2;$id<=35;$id++) {
        $name=$id===35?'  sort 00  ':'SORT '.str_pad((string)(35-$id),2,'0',STR_PAD_LEFT);
        DB::table('tb_student')->insert(['id'=>$id,'student_id'=>'26'.str_pad((string)$id,5,'0',STR_PAD_LEFT),'full_name_en'=>$name,'full_name_kh'=>'សុខ','status'=>1]);
        DB::table('tb_student_enrollment')->insert(['student_id'=>$id,'campus_id'=>1,'academic_year_id'=>1,'grade_id'=>1,'class_id'=>1]);
    }
    $response=$this->actingAs($this->campus)->getJson('/students/skipping-grade/students?academic_year_id=1&campus_id=1&grade_class=1:1&q=SORT')
        ->assertOk()->assertJsonCount(30,'students')->assertJsonPath('has_more',true);
    expect(array_column($response->json('students'),'label'))->toBe(array_merge(['sort 00'],array_map(fn($n)=>'SORT '.str_pad((string)$n,2,'0',STR_PAD_LEFT),range(1,29))));
});

it('includes the current student photo in the selected student preview with a safe missing-photo fallback', function () {
    Storage::fake('public');
    Storage::disk('public')->put('students/new-photo.png','photo');
    DB::table('tb_student')->where('id',1)->update(['photo_path'=>'students/new-photo.png','updated_at'=>'2026-10-07 12:00:00']);
    $this->actingAs($this->campus)->getJson('/students/skipping-grade/enrollment/1')->assertOk()
        ->assertJsonPath('name_kh','សុខ ដារា')->assertJsonPath('name_en','SOK DARA')
        ->assertJsonPath('photo_url',asset('storage/students/new-photo.png').'?v='.\Carbon\Carbon::parse('2026-10-07 12:00:00')->getTimestamp());
    $record=($this->draft)();
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('skipping-request-details-photo')
        ->assertSee('src="'.asset('storage/students/new-photo.png').'?v='.\Carbon\Carbon::parse('2026-10-07 12:00:00')->getTimestamp().'"',false);
    Storage::disk('public')->delete('students/new-photo.png');
    $this->getJson('/students/skipping-grade/enrollment/1')->assertOk()->assertJsonPath('photo_url',null);
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('No photo')->assertDontSee('src="'.asset('storage/students/new-photo.png'),false);
    DB::table('tb_student')->where('id',1)->update(['photo_path'=>'../outside.png']);
    $this->getJson('/students/skipping-grade/enrollment/1')->assertOk()->assertJsonPath('photo_url',null);
});

it('offers current and next requested years and scopes requested classes to the chosen year', function () {
    DB::table('tb_academic_year')->insert([
        ['id'=>2,'academic_year'=>'2026-2027','period_type'=>'regular','lifecycle_status'=>'pending'],
        ['id'=>3,'academic_year'=>'2027-2028','period_type'=>'regular','lifecycle_status'=>'pending'],
        ['id'=>4,'academic_year'=>'2024-2025','period_type'=>'regular','lifecycle_status'=>'finished'],
        ['id'=>5,'academic_year'=>'2026-2027 Summer','period_type'=>'summer','lifecycle_status'=>'pending'],
    ]);
    DB::table('tb_class')->insert([
        ['id'=>3,'class_name'=>'C','grade_id'=>2,'academic_year_id'=>2],
        ['id'=>4,'class_name'=>'D','grade_id'=>null,'academic_year_id'=>null],
    ]);
    $this->actingAs($this->campus)->get('/students/skipping-grade/create')->assertOk()->assertSee('Requested Academic Year')->assertSee('data-target-year',false);
    $this->getJson('/students/skipping-grade/enrollment/1')->assertOk()->assertJsonCount(2,'target_academic_years')
        ->assertJsonPath('target_academic_years.0.id',1)->assertJsonPath('target_academic_years.1.id',2);
    $this->getJson('/students/skipping-grade/classes?enrollment_id=1&grade_id=2&target_academic_year_id=2')->assertOk()->assertJsonCount(2,'classes')->assertJsonPath('classes.0.id',3);
    $this->getJson('/students/skipping-grade/classes?enrollment_id=1&grade_id=2&target_academic_year_id=3')->assertUnprocessable()->assertJsonValidationErrors('target_academic_year_id');
    $record=$this->service->saveDraft($this->campus,array_replace($this->data,['target_academic_year_id'=>2,'target_class_id'=>3]));
    $this->getJson('/students/skipping-grade/enrollment/1')->assertOk()->assertJsonPath('grade','G2A');
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()
        ->assertSee('G2A → G4C / 2026-2027')->assertDontSee('Requested Academic Year:');
    $edit=$this->get('/students/skipping-grade/'.$record->id.'/edit')->assertOk();
    preg_match('~<select[^>]*data-target-year[^>]*>(.*?)</select>~s',$edit->getContent(),$select);
    expect($select[1])->toMatch('/<option value="2"\s+selected/');
    $this->get('/students/skipping-grade/'.$record->id.'/print/request')->assertOk()->assertSee('>2026-2027</span>',false)
        ->assertSee('>G2A</span>',false)->assertSee('>G4</span>',false);
});

it('approves a next-year skip without changing the current-year enrollment and links both history entries', function ($lifecycle, $status) {
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','academic_year_code'=>'AY26-27','lifecycle_status'=>$lifecycle]);
    DB::table('tb_class')->insert(['id'=>3,'class_name'=>'A','grade_id'=>2,'academic_year_id'=>2]);
    $data=array_replace($this->data,['target_academic_year_id'=>2,'target_class_id'=>3]);
    $source=StudentEnrollment::find(1)->toArray();
    $this->actingAs($this->campus)->postJson('/students/skipping-grade',$data)->assertOk();
    $record=StudentSkippingGrade::firstOrFail();
    expect($record->target_academic_year_id)->toBe(2)->and($record->student_snapshot['target_academic_year'])->toBe('2026-2027');
    $record=$this->service->submit($this->campus,$record,now()->toDateString());
    ($this->configure)();
    $this->actingAs($this->central)->postJson('/students/skipping-grade/'.$record->id.'/approve',$this->approval)->assertOk();
    $record->refresh();
    $target=StudentEnrollment::findOrFail($record->target_enrollment_id);
    expect(StudentEnrollment::find(1)->toArray())->toBe($source)
        ->and([$target->student_id,$target->campus_id,$target->academic_year_id,$target->grade_id,$target->class_id,$target->enrollment_status,$target->academic_track_id])->toBe([1,1,2,2,3,$status,9])
        ->and($record->reference_number)->toBe('SG2526-001');
    $history=StudentEnrollmentHistory::orderBy('id')->get();
    expect($history)->toHaveCount(2)->and($history[0]->academic_year_id)->toBe(1)->and($history[1]->academic_year_id)->toBe(2)
        ->and($history[1]->enrollment_id)->toBe($target->id)->and($history[1]->source_history_id)->toBe($history[0]->id);
    $this->get('/students/skipping-grade/'.$record->id.'/print/request')->assertOk()->assertSee('>2026-2027</span>',false);
    $this->get('/students/skipping-grade/'.$record->id.'/print/approval')->assertOk()->assertSee('SG2526-001');
    expect(fn()=>$this->service->approve($this->central,$record,$this->approval))->toThrow(ValidationException::class);
    expect(StudentEnrollment::count())->toBe(2);
})->with([['pending','pending'],['started','active']]);

it('adds the approval number and saved source placement to enrollment and history remarks only on approval', function ($nextYear, $existingNotes, $requestReason) {
    StudentEnrollment::findOrFail(1)->update(['notes'=>$existingNotes]);
    $this->data['reason']=$requestReason;
    if ($nextYear) {
        DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','lifecycle_status'=>'pending']);
        DB::table('tb_class')->insert(['id'=>3,'class_name'=>'B','grade_id'=>2,'academic_year_id'=>2]);
        $this->data=array_replace($this->data,['target_academic_year_id'=>2,'target_class_id'=>3]);
    }
    $record=($this->pending)();
    expect(StudentEnrollment::find(1)->notes)->toBe($existingNotes);
    // The approved remark must describe the saved source, even if labels are renamed later.
    DB::table('tb_grade')->where('id',1)->update(['grade_short_name'=>'Renamed']);
    ($this->configure)();
    $record=$this->service->approve($this->central,$record,$this->approval);
    $remark='Skipped from G2A | Approval No: SG2526-001';
    $expected=!$nextYear && filled($existingNotes) ? $existingNotes."\n".$remark : $remark;
    expect($record->targetEnrollment->notes)->toBe($expected);
    $historyNotes=filled($requestReason) ? $requestReason."\n".$remark : $remark;
    $history=StudentEnrollmentHistory::orderBy('id')->get();
    expect($history->pluck('notes')->all())->toBe([$historyNotes,$historyNotes]);
    if ($nextYear) expect(StudentEnrollment::find(1)->notes)->toBe($existingNotes);
    expect(fn()=>$this->service->approve($this->central,$record,$this->approval))->toThrow(ValidationException::class);
    expect($record->targetEnrollment->fresh()->notes)->toBe($expected);
    expect(StudentEnrollmentHistory::count())->toBe(2);
})->with([[false,null,null],[false,"Existing remark\nSecond line",'Request reason'],[true,null,null],[true,'Source-only remark','Request reason']]);

it('rejects invalid requested years and classes for a different requested year', function ($yearId, $classId, $field) {
    DB::table('tb_academic_year')->insert([
        ['id'=>2,'academic_year'=>'2026-2027','period_type'=>'regular','lifecycle_status'=>'pending'],
        ['id'=>3,'academic_year'=>'2024-2025','period_type'=>'regular','lifecycle_status'=>'started'],
        ['id'=>4,'academic_year'=>'2027-2028','period_type'=>'regular','lifecycle_status'=>'pending'],
        ['id'=>5,'academic_year'=>'2026-2027','period_type'=>'summer','lifecycle_status'=>'pending'],
        ['id'=>6,'academic_year'=>'2026-2027','period_type'=>'regular','lifecycle_status'=>'finished'],
    ]);
    $this->actingAs($this->campus)->postJson('/students/skipping-grade',array_replace($this->data,['target_academic_year_id'=>$yearId,'target_class_id'=>$classId]))->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(StudentSkippingGrade::count())->toBe(0)->and(StudentEnrollment::find(1)->grade_id)->toBe(1);
})->with([[3,2,'target_academic_year_id'],[4,2,'target_academic_year_id'],[5,2,'target_academic_year_id'],[6,2,'target_academic_year_id'],[999,2,'target_academic_year_id'],[2,2,'target_class_id']]);

it('prevents next-year enrollment duplicates at draft submit and approval without overwriting another campus', function ($stage) {
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','academic_year_code'=>'2627','lifecycle_status'=>'pending']);
    DB::table('tb_class')->insert(['id'=>3,'class_name'=>'A','grade_id'=>2,'academic_year_id'=>2]);
    $data=array_replace($this->data,['target_academic_year_id'=>2,'target_class_id'=>3]);
    $record=$stage==='draft'?null:$this->service->saveDraft($this->campus,$data);
    if ($stage==='approval') $record=$this->service->submit($this->campus,$record,now()->toDateString());
    $existing=StudentEnrollment::create(['student_id'=>1,'campus_id'=>2,'academic_year_id'=>2,'grade_id'=>1,'class_id'=>1,'enrollment_status'=>'pending']);
    $before=$existing->fresh()->toArray();
    if ($stage==='draft') expect(fn()=>$this->service->saveDraft($this->campus,$data))->toThrow(ValidationException::class);
    elseif ($stage==='submit') expect(fn()=>$this->service->submit($this->campus,$record,now()->toDateString()))->toThrow(ValidationException::class);
    else {
        ($this->configure)();
        expect(fn()=>$this->service->approve($this->central,$record,$this->approval))->toThrow(ValidationException::class);
        expect(Storage::disk('local')->allFiles('grade-skipping/approvals'))->toBe([]);
    }
    expect($existing->fresh()->toArray())->toBe($before)->and(StudentEnrollment::count())->toBe(2)->and(StudentEnrollmentHistory::count())->toBe(0);
    if ($record) expect($record->fresh()->status)->toBe($stage==='approval'?'pending':'draft');
})->with(['draft','submit','approval']);

it('rolls back a new next-year enrollment and signature copies if approval history fails', function () {
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','academic_year_code'=>'2627','lifecycle_status'=>'pending']);
    DB::table('tb_class')->insert(['id'=>3,'class_name'=>'A','grade_id'=>2,'academic_year_id'=>2]);
    $record=$this->service->saveDraft($this->campus,array_replace($this->data,['target_academic_year_id'=>2,'target_class_id'=>3]));
    $record=$this->service->submit($this->campus,$record,now()->toDateString());
    ($this->configure)();
    StudentEnrollmentHistory::creating(function ($history) { if ($history->action_type==='grade_skipping') throw new RuntimeException('History write failed'); });
    try { expect(fn()=>$this->service->approve($this->central,$record,$this->approval))->toThrow(RuntimeException::class); }
    finally { StudentEnrollmentHistory::flushEventListeners(); }
    expect(StudentEnrollment::count())->toBe(1)->and(StudentEnrollmentHistory::count())->toBe(0)->and($record->fresh()->status)->toBe('pending')->and($record->fresh()->target_enrollment_id)->toBeNull();
    expect(Storage::disk('local')->allFiles('grade-skipping/approvals'))->toBe([]);
});

it('allows a future effective date for next-year enrollment while retaining same-year date validation', function () {
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','academic_year_code'=>'2627','lifecycle_status'=>'pending']);
    DB::table('tb_class')->insert(['id'=>3,'class_name'=>'A','grade_id'=>2,'academic_year_id'=>2]);
    $record=($this->pending)();
    ($this->configure)();
    $approval=array_replace($this->approval,['effective_date'=>now()->addMonth()->toDateString()]);
    $this->actingAs($this->central)->postJson('/students/skipping-grade/'.$record->id.'/approve',$approval)->assertUnprocessable()->assertJsonValidationErrors('effective_date');
    $this->service->reject($this->central,$record,'Request for next year instead.');
    $record=$this->service->saveDraft($this->campus,array_replace($this->data,['target_academic_year_id'=>2,'target_class_id'=>3]));
    $record=$this->service->submit($this->campus,$record,now()->toDateString());
    $this->postJson('/students/skipping-grade/'.$record->id.'/approve',$approval)->assertOk();
    expect($record->fresh()->targetEnrollment->enrolled_on->toDateString())->toBe($approval['effective_date']);
});

it('backfills older requests to their original academic year and links existing approvals', function () {
    $draft=($this->draft)();
    $migration=require database_path('migrations/2026_10_08_000002_add_requested_academic_year_to_skipping_grade.php');
    $migration->down();
    $migration->up();
    expect($draft->fresh()->target_academic_year_id)->toBe(1)->and($draft->fresh()->target_enrollment_id)->toBeNull();
    ($this->configure)();
    $record=$this->service->submit($this->campus,$draft->fresh(),now()->toDateString());
    $record=$this->service->approve($this->central,$record,$this->approval);
    $migration->down();
    $migration->up();
    expect($record->fresh()->target_academic_year_id)->toBe(1)->and($record->fresh()->target_enrollment_id)->toBe(1);
});

it('uses the saved AY Code at approval and preserves issued numbers when the code changes later', function () {
    ($this->configure)();
    $record=($this->pending)();
    DB::table('tb_academic_year')->where('id',1)->update(['academic_year_code'=>'AY25-26']);
    $record=$this->service->approve($this->central,$record,$this->approval);
    expect($record->reference_number)->toBe('SGAY25-26-001');
    DB::table('tb_academic_year')->where('id',1)->update(['academic_year_code'=>'NEW-CODE']);
    expect($record->fresh()->reference_number)->toBe('SGAY25-26-001');
});

it('requires the current academic year AY Code before issuing a next-year approval', function ($code) {
    ($this->configure)();
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','academic_year_code'=>'2627','lifecycle_status'=>'pending']);
    DB::table('tb_class')->insert(['id'=>3,'class_name'=>'A','grade_id'=>2,'academic_year_id'=>2]);
    $record=$this->service->saveDraft($this->campus,array_replace($this->data,['target_academic_year_id'=>2,'target_class_id'=>3]));
    $record=$this->service->submit($this->campus,$record,now()->toDateString());
    DB::table('tb_academic_year')->where('id',1)->update(['academic_year_code'=>$code]);
    $this->actingAs($this->central)->postJson('/students/skipping-grade/'.$record->id.'/approve',$this->approval)
        ->assertUnprocessable()->assertJsonValidationErrors('settings');
    expect($record->fresh()->status)->toBe('pending')
        ->and($record->fresh()->reference_number)->toBeNull()
        ->and(StudentEnrollment::find(1)->grade_id)->toBe(1)
        ->and(StudentEnrollmentHistory::count())->toBe(0);
    expect(Storage::disk('local')->allFiles('grade-skipping/approvals'))->toBe([]);
})->with([null,'','   ']);

it('uses the current year code even when the requested year code is missing', function () {
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','academic_year_code'=>null,'lifecycle_status'=>'pending']);
    DB::table('tb_class')->insert(['id'=>3,'class_name'=>'A','grade_id'=>2,'academic_year_id'=>2]);
    DB::table('tb_academic_year')->where('id',1)->update(['academic_year_code'=>'CURRENT-CODE']);
    $record=$this->service->saveDraft($this->campus,array_replace($this->data,['target_academic_year_id'=>2,'target_class_id'=>3]));
    $record=$this->service->submit($this->campus,$record,now()->toDateString());
    ($this->configure)();
    $record=$this->service->approve($this->central,$record,$this->approval);
    expect($record->reference_number)->toBe('SGCURRENT-CODE-001')
        ->and($record->academic_year_id)->toBe(1)
        ->and($record->target_enrollment_id)->not->toBe(1);
    DB::table('tb_academic_year')->where('id',1)->update(['academic_year_code'=>'NEW-CODE']);
    expect($record->fresh()->reference_number)->toBe('SGCURRENT-CODE-001');
});

it('corrects only approval 003 and its history to the authorized current-year code', function () {
    DB::table('tb_academic_year')->where('id',1)->update(['academic_year_code'=>'2627']);
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','academic_year_code'=>'2728','lifecycle_status'=>'pending']);
    DB::table('tb_class')->insert(['id'=>3,'class_name'=>'A','grade_id'=>2,'academic_year_id'=>2]);
    $record=$this->service->saveDraft($this->campus,array_replace($this->data,['target_academic_year_id'=>2,'target_class_id'=>3]));
    DB::table('tb_student_skipping_grade')->where('id',$record->id)->update(['id'=>3]);
    $record=StudentSkippingGrade::findOrFail(3);
    $record=$this->service->submit($this->campus,$record,now()->toDateString());
    ($this->configure)();
    $record=$this->service->approve($this->central,$record,$this->approval);
    $snapshot=$record->approval_snapshot;
    $record->update(['reference_number'=>'SG2728-003']);
    StudentEnrollmentHistory::query()->update(['reason'=>'Approved grade skipping: SG2728-003']);
    $other=$record->replicate();
    $other->reference_number='SG2728-004';
    $other->save();
    $migration=require database_path('migrations/2026_10_09_000014_correct_skipping_grade_approval_003_current_year_code.php');
    $migration->up();
    $migration->up();
    expect($record->fresh()->reference_number)->toBe('SG2627-003')
        ->and($record->fresh()->approval_snapshot)->toBe($snapshot)
        ->and($other->fresh()->reference_number)->toBe('SG2728-004')
        ->and(StudentEnrollmentHistory::pluck('reason')->all())->toBe(array_fill(0,2,'Approved grade skipping: SG2627-003'));
    $this->actingAs($this->central)->get('/students/skipping-grade/3/print/approval')->assertOk()->assertSee('SG2627-003');
    $this->get('/students/skipping-grade/3/share/approval')->assertOk()->assertSee('SG2627-003.png');
    $migration->down();
    expect($record->fresh()->reference_number)->toBe('SG2728-003');
});

it('corrects approval 002 only for student 2216236 and preserves the signed approval', function () {
    DB::table('tb_academic_year')->where('id',1)->update(['academic_year_code'=>'2627']);
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','academic_year_code'=>'2728','lifecycle_status'=>'pending']);
    DB::table('tb_class')->insert(['id'=>3,'class_name'=>'A','grade_id'=>2,'academic_year_id'=>2]);
    $record=$this->service->saveDraft($this->campus,array_replace($this->data,['target_academic_year_id'=>2,'target_class_id'=>3]));
    DB::table('tb_student_skipping_grade')->where('id',$record->id)->update(['id'=>2]);
    $record=$this->service->submit($this->campus,StudentSkippingGrade::findOrFail(2),now()->toDateString());
    ($this->configure)();
    $record=$this->service->approve($this->central,$record,$this->approval);
    $signed=$record->approval_snapshot;
    $approvedAt=$record->approved_at->toDateTimeString();
    $record->update(['reference_number'=>'SG2728-002']);
    StudentEnrollmentHistory::query()->update(['reason'=>'Approved grade skipping: SG2728-002']);
    $migration=require database_path('migrations/2026_10_09_000015_correct_skipping_grade_approval_002_current_year_code.php');
    $migration->up();
    expect($record->fresh()->reference_number)->toBe('SG2728-002');
    $snapshot=$record->student_snapshot;
    $snapshot['student_id']='2216236';
    $record->update(['student_snapshot'=>$snapshot]);
    $migration->up();
    $migration->up();
    expect($record->fresh()->reference_number)->toBe('SG2627-002')
        ->and($record->fresh()->approval_snapshot)->toBe($signed)
        ->and($record->fresh()->approved_at->toDateTimeString())->toBe($approvedAt)
        ->and(StudentEnrollmentHistory::pluck('reason')->all())->toBe(array_fill(0,2,'Approved grade skipping: SG2627-002'));
    $this->actingAs($this->central)->get('/students/skipping-grade/2/print/approval')->assertOk()->assertSee('SG2627-002');
    $this->get('/students/skipping-grade/2/share/approval')->assertOk()->assertSee('2216236-SG2627-002.png');
    $migration->down();
    expect($record->fresh()->reference_number)->toBe('SG2728-002')
        ->and(StudentEnrollmentHistory::pluck('reason')->all())->toBe(array_fill(0,2,'Approved grade skipping: SG2728-002'));
});

it('moves grade only once and freezes the signature stamp and before-after history on approval', function () {
    ($this->configure)(); $record=$this->service->approve($this->central,($this->pending)(),$this->approval);
    $enrollment=StudentEnrollment::find(1);
    expect($record->status)->toBe('approved')->and($record->reference_number)->toBe('SG2526-001');
    expect([$enrollment->grade_id,$enrollment->class_id,$enrollment->campus_id,$enrollment->academic_year_id,$enrollment->academic_track_id,$enrollment->group_id])->toBe([2,2,1,1,9,null]);
    $history=StudentEnrollmentHistory::orderBy('id')->get();
    expect($history)->toHaveCount(2)->and($history[0]->grade_id)->toBe(1)->and($history[1]->grade_id)->toBe(2)->and($history[1]->source_history_id)->toBe($history[0]->id);
    Storage::disk('local')->assertExists([$record->approval_snapshot['signature_path'],$record->approval_snapshot['stamp_path']]);
    SkippingGradeSetting::current()->update(['signer_name_kh'=>'New VP']); Storage::disk('local')->delete('settings/signature.png');
    expect($record->fresh()->approval_snapshot['signer_name_kh'])->toBe('គីង រដ្ឋមុនី');
    Storage::disk('local')->assertExists($record->approval_snapshot['signature_path']);
    $this->actingAs($this->central)->get('/students/skipping-grade/'.$record->id.'/print/approval')->assertOk()
        ->assertSee('class="approval-stamp" src="data:image/png;base64,',false)
        ->assertSee('class="approval-signature" src="data:image/png;base64,',false);
    expect(fn()=>$this->service->approve($this->central,$record,$this->approval))->toThrow(ValidationException::class);
    expect(fn()=>$this->service->reject($this->central,$record,'Rejected'))->toThrow(ValidationException::class);
    expect(StudentEnrollmentHistory::count())->toBe(2);
});

it('clears the academic track when the new grade changes education level', function () {
    DB::table('tb_grade')->where('id',2)->update(['education_level_id'=>2]); ($this->configure)();
    $this->service->approve($this->central,($this->pending)(),$this->approval);
    expect(StudentEnrollment::find(1)->academic_track_id)->toBeNull();
});

it('leaves grade and status unchanged when approval assets are missing or enrollment has changed', function ($mode) {
    $record=($this->pending)();
    if ($mode==='stale') { ($this->configure)(); StudentEnrollment::whereKey(1)->update(['session_id'=>5]); }
    if ($mode==='missing stamp') { ($this->configure)(); Storage::disk('local')->delete('settings/stamp.png'); }
    expect(fn()=>$this->service->approve($this->central,$record,$this->approval))->toThrow(ValidationException::class);
    expect($record->fresh()->status)->toBe('pending')->and(StudentEnrollment::find(1)->grade_id)->toBe(1)->and(StudentEnrollmentHistory::count())->toBe(0);
    expect(Storage::disk('local')->allFiles('grade-skipping/approvals'))->toBe([]);
})->with(['missing settings','missing stamp','stale']);

it('rolls back the grade status history and copied assets if the history write fails', function () {
    ($this->configure)(); $record=($this->pending)();
    StudentEnrollmentHistory::creating(function ($history) { if ($history->action_type==='grade_skipping') throw new RuntimeException('History write failed'); });
    try { expect(fn()=>$this->service->approve($this->central,$record,$this->approval))->toThrow(RuntimeException::class); }
    finally { StudentEnrollmentHistory::flushEventListeners(); }
    expect(StudentEnrollment::find(1)->grade_id)->toBe(1)->and(StudentEnrollmentHistory::count())->toBe(0)->and($record->fresh()->status)->toBe('pending');
    expect(Storage::disk('local')->allFiles('grade-skipping/approvals'))->toBe([]);
});

it('rejects a pending request without moving grade and allows a replacement draft', function () {
    $record=($this->pending)(); $this->service->reject($this->central,$record,'Insufficient supporting documents');
    expect($record->fresh()->status)->toBe('rejected')->and(StudentEnrollment::find(1)->grade_id)->toBe(1);
    expect(($this->draft)()->status)->toBe('draft');
});

it('prints the approved option and blocks printing for rejected requests', function ($status) {
    $record=($this->pending)();
    $url='/students/skipping-grade/'.$record->id.'/print/approval';
    $this->actingAs($this->campus)->getJson($url)->assertForbidden();
    if ($status==='approved') {
        ($this->configure)();
        $record=$this->service->approve($this->central,$record,array_replace($this->approval,['age_decision'=>'exception']));
    } else {
        $this->service->reject($this->central,$record,'Supporting documents are insufficient.');
        $record=$record->fresh();
    }
    StudentSkippingGrade::whereKey($record->id)->update(['campus_id'=>2]);
    if ($status==='rejected') {
        $this->getJson($url)->assertForbidden();
        $this->getJson('/students/skipping-grade/'.$record->id.'/print/request')->assertForbidden();
        $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertDontSee('Print Approval')->assertDontSee('Print Decision')->assertDontSee('Print Request');
        $this->get('/students/skipping-grade?campus_id=2')->assertOk()->assertDontSee(url($url))->assertDontSee(url('/students/skipping-grade/'.$record->id.'/print/request'));
        expect(StudentEnrollment::find(1)->grade_id)->toBe(1)->and(StudentEnrollmentHistory::count())->toBe(0);
        return;
    }
    $print=$this->get($url)->assertOk();
    preg_match_all('~<div class="approval-condition approval-decision-option" data-decision-option="(approved|rejected)"([^>]*)>~',$print->getContent(),$options,PREG_SET_ORDER);
    expect($options)->toHaveCount(2);
    foreach ($options as $option) expect(str_contains($option[2],'hidden'))->toBe($option[1]!==$status);
    foreach (['standard'=>'approved','exception'=>'rejected'] as $key=>$decision) {
        preg_match('~<span[^>]*data-skipping-block="check-age-'.$key.'"[^>]*>~',$print->getContent(),$checkbox);
        expect(str_contains($checkbox[0],'print-check checked'))->toBe($decision===$status);
    }
    expect(StudentEnrollment::find(1)->grade_id)->toBe($status==='approved'?2:1);
    $label=$status==='approved'?'Print Approval':'Print Decision';
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee($label);
    $this->get('/students/skipping-grade?campus_id=2')->assertOk()->assertSee(url($url));
    $id=DB::table('access_permissions')->where('code','student-skipping-grade.print')->value('id');
    DB::table('access_role_permissions')->where('role_id',1)->where('permission_id',$id)->delete();
    $this->getJson($url)->assertForbidden();
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertDontSee($label);
})->with(['approved','rejected']);

it('keeps signed requests private and prints approval only after final approval', function () {
    $record=$this->service->submit($this->campus,($this->draft)(),now()->toDateString(),UploadedFile::fake()->create('signed.pdf',10,'application/pdf'));
    Storage::disk('local')->assertExists($record->signed_request_path);
    $this->actingAs($this->campus)->get('/students/skipping-grade/'.$record->id.'/signed-request')->assertOk();
    $this->getJson('/students/skipping-grade/'.$record->id.'/print/approval')->assertForbidden();
    $response=$this->get('/students/skipping-grade/'.$record->id.'/print/request')->assertOk()->assertSee('SOK DARA')->assertSee('Committee Approval');
    expect($response->getContent())->not->toContain('imports/')->toContain('charset="UTF-8"');
    ($this->configure)(); $record=$this->service->approve($this->central,$record,$this->approval);
    $this->get('/students/skipping-grade/'.$record->id.'/print/approval')->assertOk()->assertSee('SG2526-001')->assertSee('data:image/png;base64',false);
    StudentSkippingGrade::whereKey($record->id)->update(['campus_id'=>2]);
    $this->getJson('/students/skipping-grade/'.$record->id.'/signed-request')->assertOk();
});

it('allows campus users to print another campus approval with print permission only after approval', function () {
    DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>2]);
    DB::table('tb_student_enrollment')->where('id',1)->update(['campus_id'=>2]);
    $record=($this->pending)();
    DB::table('access_user_campuses')->where('user_id',1)->where('campus_id',2)->delete();
    expect($this->campus->canAccessCampus(2))->toBeFalse();
    $url='/students/skipping-grade/'.$record->id.'/print/approval';
    $this->actingAs($this->campus)->getJson($url)->assertForbidden();
    ($this->configure)();
    $record=$this->service->approve($this->central,$record,$this->approval);
    $this->get($url)->assertOk()->assertSee('SOK DARA')->assertSee('data:image/png;base64',false);
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('Print Approval');
    $this->get('/students/skipping-grade?campus_id=2')->assertOk()->assertSee(url($url));
    $id=DB::table('access_permissions')->where('code','student-skipping-grade.print')->value('id');
    DB::table('access_role_permissions')->where('role_id',1)->where('permission_id',$id)->delete();
    $this->getJson($url)->assertForbidden();
    $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertDontSee('Print Approval');
});

it('shares an approved A4 form across campuses with the saved template and issued signing images', function () {
    ($this->configure)();
    $record=$this->service->approve($this->central,($this->pending)(),$this->approval);
    $record->update(['campus_id'=>2]);
    SkippingGradeSetting::current()->update(['approval_template'=>['heading-en'=>['text'=>'Custom Approval for {student_name_en}','size'=>14]]]);
    expect($this->campus->canAccessCampus(2))->toBeFalse();
    $url=route('student-skipping-grade.share-approval',$record);
    $this->actingAs($this->campus)->get($url)->assertOk()
        ->assertHeader('Cache-Control','no-store, private')
        ->assertSee('data-skipping-image-export',false)->assertSee('data-approval-filename="SOK DARA-2612345-SG2526-001.png"',false)
        ->assertSee('Custom Approval for SOK DARA')->assertSee('font-size:14pt;',false)
        ->assertSee('class="approval-stamp" src="data:image/png;base64,',false)
        ->assertSee('class="approval-signature" src="data:image/png;base64,',false)
        ->assertDontSee('data-skipping-print',false);
    $this->get('/students/skipping-grade')->assertOk()->assertSee('data-skipping-share-approval="'.$url.'"',false);
    $this->postJson($url)->assertStatus(405);
    $this->get('/shared/grade-skipping-approval/'.$record->id.'/00000000-0000-4000-8000-000000000000.png')->assertNotFound();
    $id=DB::table('access_permissions')->where('code','student-skipping-grade.print')->value('id');
    DB::table('access_role_permissions')->where('role_id',1)->where('permission_id',$id)->delete();
    $this->getJson($url)->assertForbidden();
    $this->get('/students/skipping-grade')->assertOk()->assertDontSee('data-skipping-share-approval',false);
});

it('does not expose sharing before approval or for rejected requests', function ($status) {
    $record=($this->draft)();
    $record->update(['status'=>$status]);
    $this->actingAs($this->campus)->getJson(route('student-skipping-grade.share-approval',$record))->assertForbidden();
    $this->get('/students/skipping-grade')->assertOk()->assertDontSee('data-skipping-share-approval',false);
})->with(['draft','pending','rejected']);

it('accepts both central settings images up to 2 MB each in one submission', function ($signatureKb, $stampKb) {
    $this->actingAs($this->central)->post('/students/skipping-grade/settings',[
        'signer_name_kh'=>'VP','signer_title_kh'=>'Chair','signer_title_en'=>'Chair','number_prefix'=>'SG',
        'signature'=>UploadedFile::fake()->image('signature.png')->size($signatureKb),
        'stamp'=>UploadedFile::fake()->image('stamp.png')->size($stampKb),
    ],['Accept'=>'application/json'])->assertOk();
    $settings=SkippingGradeSetting::current()->fresh();
    Storage::disk('local')->assertExists([$settings->signature_path,$settings->stamp_path]);
})->with(['reported image sizes'=>[222,800],'both at the maximum'=>[2048,2048]]);

it('rejects an image above 2 MB without changing existing central settings or images', function ($field) {
    ($this->configure)();
    $before=SkippingGradeSetting::current()->getAttributes();
    $this->actingAs($this->central)->post('/students/skipping-grade/settings',[
        'signer_name_kh'=>'New VP','signer_title_kh'=>'Chair','signer_title_en'=>'Chair','number_prefix'=>'SG',
        'signature'=>UploadedFile::fake()->image('signature.png')->size($field==='signature'?2049:222),
        'stamp'=>UploadedFile::fake()->image('stamp.png')->size($field==='stamp'?2049:800),
    ],['Accept'=>'application/json'])->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(SkippingGradeSetting::current()->fresh()->getAttributes())->toBe($before);
    Storage::disk('local')->assertExists(['settings/signature.png','settings/stamp.png']);
})->with(['signature','stamp']);

it('replaces settings uploads while preserving images copied into issued approval forms', function () {
    ($this->configure)(); $record=$this->service->approve($this->central,($this->pending)(),$this->approval);
    $this->actingAs($this->central)->post('/students/skipping-grade/settings',[
        'signer_name_kh'=>'អ្នកថ្មី','signer_title_kh'=>'អនុប្រធាន','signer_title_en'=>'Vice President','number_prefix'=>'SG',
        'signature'=>UploadedFile::fake()->image('new-signature.png'),'stamp'=>UploadedFile::fake()->image('new-stamp.png'),
    ],['Accept'=>'application/json'])->assertOk();
    Storage::disk('local')->assertMissing(['settings/signature.png','settings/stamp.png']);
    Storage::disk('local')->assertExists([$record->approval_snapshot['signature_path'],$record->approval_snapshot['stamp_path']]);
    expect(SkippingGradeSetting::current()->signer_name_kh)->toBe('អ្នកថ្មី');
});

it('approves through the actual JSON endpoint and saves the selected letter conditions', function () {
    ($this->configure)(); $record=($this->pending)();
    $this->actingAs($this->central)->postJson('/students/skipping-grade/'.$record->id.'/approve',$this->approval)->assertOk()->assertJsonStructure(['message','redirect']);
    expect(StudentEnrollment::find(1)->grade_id)->toBe(2)
        ->and($record->fresh()->approval_snapshot['school_decision'])->toBe('external')
        ->and($record->fresh()->approval_snapshot['obligations']['conduct'])->toBeTrue();
    $this->postJson('/students/skipping-grade/'.$record->id.'/approve',$this->approval)->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('validates the approval date sequence and letter choices before changing enrollment', function () {
    ($this->configure)(); $record=($this->pending)();
    $page=$this->actingAs($this->central)->get('/students/skipping-grade/'.$record->id)->assertOk()
        ->assertSee('skipping-approval-date-grid')->assertDontSee('type="date"',false)->assertSee('value="'.now()->format('d-M-Y').'"',false);
    preg_match_all('/<input type="hidden" name="([^"]+)" data-premium-date-value value="([^"]*)"/',$page->getContent(),$dates,PREG_SET_ORDER);
    expect(array_column($dates,1))->toBe(['received_date','review_date','approval_date','effective_date']);
    foreach ($dates as $date) expect($date[2])->toBe(now()->toDateString());
    $this->actingAs($this->central)->postJson('/students/skipping-grade/'.$record->id.'/approve',array_replace($this->approval,[
        'received_date'=>now()->addDay()->toDateString(),'review_date'=>now()->subDay()->toDateString(),'age_decision'=>'unknown',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['received_date','review_date','age_decision']);
    expect($record->fresh()->status)->toBe('pending')->and(StudentEnrollment::find(1)->grade_id)->toBe(1);
});

it('requires the requested class academic year and group to match the enrollment', function () {
    DB::table('tb_class')->where('id',2)->update(['academic_year_id'=>2]);
    expect(fn()=>($this->draft)())->toThrow(ValidationException::class);
    DB::table('tb_class')->where('id',2)->update(['academic_year_id'=>1,'session_id'=>1]);
    DB::table('tb_session')->insert(['id'=>1,'session_name'=>'Morning']);
    expect(fn()=>($this->draft)())->toThrow(ValidationException::class);
    $this->data['target_session_id']=1;
    expect(($this->draft)()->target_session_id)->toBe(1);
});

it('limits large student searches to 30 results and reports when more matches exist', function () {
    for ($id=2;$id<=40;$id++) {
        DB::table('tb_student')->insert(['id'=>$id,'student_id'=>'26'.str_pad((string)$id,5,'0',STR_PAD_LEFT),'full_name_en'=>'SOK TEST '.$id,'full_name_kh'=>'សុខ','status'=>1]);
        DB::table('tb_student_enrollment')->insert(['student_id'=>$id,'campus_id'=>1,'academic_year_id'=>1,'grade_id'=>1,'class_id'=>1]);
    }
    $this->actingAs($this->campus)->getJson('/students/skipping-grade/students?q=SOK')->assertOk()->assertJsonCount(30,'students')->assertJsonPath('has_more',true);
});

it('filters the student picker by current grade together with campus year and either language name', function () {
    DB::table('tb_student')->insert(['id'=>2,'student_id'=>'2600002','full_name_en'=>'SOK OTHER','full_name_kh'=>'សុខ ផ្សេង','status'=>1]);
    DB::table('tb_student_enrollment')->insert(['id'=>2,'student_id'=>2,'campus_id'=>1,'academic_year_id'=>1,'grade_id'=>2,'class_id'=>2]);
    $this->actingAs($this->campus)->getJson('/students/skipping-grade/students?academic_year_id=1&campus_id=1&grade_id=1')
        ->assertOk()->assertJsonCount(1,'students')->assertJsonPath('students.0.id',1);
    $this->getJson('/students/skipping-grade/students?academic_year_id=1&campus_id=1&grade_id=2&q='.urlencode('សុខ'))
        ->assertOk()->assertJsonCount(1,'students')->assertJsonPath('students.0.id',2);
    $this->getJson('/students/skipping-grade/students?grade_id=2&q=DARA')->assertOk()->assertJsonCount(0,'students');
    $this->getJson('/students/skipping-grade/students?grade_id=1&campus_id=2')->assertOk()->assertJsonCount(0,'students');
    $this->getJson('/students/skipping-grade/students?grade_id=invalid')->assertUnprocessable()->assertJsonValidationErrors('grade_id');
});

it('filters students by the selected grade and class rather than the whole grade', function () {
    DB::table('tb_class')->insert(['id'=>3,'class_name'=>'B','grade_id'=>1,'academic_year_id'=>1]);
    DB::table('tb_student')->insert(['id'=>2,'student_id'=>'2600002','full_name_en'=>'SOK OTHER','full_name_kh'=>'សុខ ផ្សេង','status'=>1]);
    DB::table('tb_student_enrollment')->insert(['id'=>2,'student_id'=>2,'campus_id'=>1,'academic_year_id'=>1,'grade_id'=>1,'class_id'=>3]);
    $this->actingAs($this->campus)->getJson('/students/skipping-grade/students?academic_year_id=1&campus_id=1&grade_class=1:1')
        ->assertOk()->assertJsonCount(1,'students')->assertJsonPath('students.0.id',1);
    $this->getJson('/students/skipping-grade/students?grade_class=1:3&q='.urlencode('សុខ'))
        ->assertOk()->assertJsonCount(1,'students')->assertJsonPath('students.0.id',2);
    $this->getJson('/students/skipping-grade/students?grade_class=2:3')->assertOk()->assertJsonCount(0,'students');
    $this->getJson('/students/skipping-grade/students?grade_class=1:3&campus_id=2')->assertOk()->assertJsonCount(0,'students');
    $this->getJson('/students/skipping-grade/students?grade_class=1:3&academic_year_id=2')->assertOk()->assertJsonCount(0,'students');
    $this->getJson('/students/skipping-grade/students?grade_class=invalid')->assertUnprocessable()->assertJsonValidationErrors('grade_class');
});

it('loads grade class options from enrollments when class letters are shared across grades and years', function () {
    DB::table('tb_grade')->where('id',1)->update(['grade_short_name'=>'1']);
    DB::table('tb_grade')->where('id',2)->update(['grade_short_name'=>'2']);
    DB::table('tb_class')->update(['grade_id'=>null,'academic_year_id'=>null]);
    DB::table('tb_student_enrollment')->insert([
        ['student_id'=>1,'campus_id'=>1,'academic_year_id'=>1,'grade_id'=>1,'class_id'=>2],
        ['student_id'=>1,'campus_id'=>1,'academic_year_id'=>1,'grade_id'=>2,'class_id'=>1],
        ['student_id'=>1,'campus_id'=>1,'academic_year_id'=>1,'grade_id'=>1,'class_id'=>1],
        ['student_id'=>1,'campus_id'=>2,'academic_year_id'=>1,'grade_id'=>2,'class_id'=>2],
        ['student_id'=>1,'campus_id'=>1,'academic_year_id'=>2,'grade_id'=>2,'class_id'=>2],
    ]);
    $this->actingAs($this->campus)->getJson('/students/skipping-grade/source-classes?academic_year_id=1&campus_id=1')
        ->assertOk()->assertExactJson(['classes'=>[
            ['value'=>'1:1','label'=>'1A'],['value'=>'1:2','label'=>'1B'],['value'=>'2:1','label'=>'2A'],
        ]]);
    $this->getJson('/students/skipping-grade/source-classes?academic_year_id=1&campus_id=2')->assertOk()->assertJsonCount(1,'classes')->assertJsonPath('classes.0.value','2:2');
    $this->getJson('/students/skipping-grade/source-classes?academic_year_id=2&campus_id=1')->assertOk()->assertJsonCount(0,'classes');
    $this->getJson('/students/skipping-grade/source-classes')->assertOk()->assertJsonCount(0,'classes');
    $this->getJson('/students/skipping-grade/source-classes?academic_year_id=invalid')->assertUnprocessable()->assertJsonValidationErrors('academic_year_id');
    DB::table('tb_student')->where('id',1)->update(['status'=>0]);
    $this->getJson('/students/skipping-grade/source-classes?academic_year_id=1&campus_id=1')->assertOk()->assertJsonCount(0,'classes');
});

it('loads bounded student choices when only a source filter is selected', function ($filter) {
    $this->actingAs($this->campus)->getJson('/students/skipping-grade/students?'.$filter)
        ->assertOk()->assertJsonCount(1,'students')->assertJsonPath('students.0.id',1);
})->with(['academic_year_id=1','campus_id=1','grade_id=1','grade_class=1:1']);


describe('promotion with approved grade-skipping placements', function () {
    beforeEach(function () {
        Schema::table('tb_student_enrollment', function (Blueprint $t) {
            $t->date('ended_on')->nullable(); $t->string('exit_reason')->nullable();
            $t->unique(['student_id','academic_year_id']);
        });
        Schema::table('tb_student', fn(Blueprint $t)=>$t->string('student_no')->nullable());
        Schema::table('tb_session', fn(Blueprint $t)=>$t->string('session_short_name')->nullable());
        DB::table('tb_grade')->where('id',1)->update(['grade'=>'Grade 3','grade_short_name'=>'G3','grade_order'=>3]);
        DB::table('tb_grade')->where('id',2)->update(['grade'=>'Grade 5','grade_short_name'=>'G5','grade_order'=>5]);
        DB::table('tb_grade')->insert(['id'=>3,'grade'=>'Grade 4','grade_short_name'=>'G4','grade_order'=>4]);
        DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','academic_year_code'=>'2627','lifecycle_status'=>'pending']);
        DB::table('tb_class')->insert([
            ['id'=>3,'class_name'=>'A','grade_id'=>2,'academic_year_id'=>2],
            ['id'=>4,'class_name'=>'A','grade_id'=>3,'academic_year_id'=>2],
        ]);
        $request=$this->service->saveDraft($this->campus,array_replace($this->data,['target_academic_year_id'=>2,'target_class_id'=>3]));
        $request=$this->service->submit($this->campus,$request,now()->toDateString());
        ($this->configure)();
        $this->skipping=$this->service->approve($this->central,$request,$this->approval);
        DB::table('tb_student')->insert(['id'=>2,'student_id'=>'2622222','full_name_en'=>'REGULAR STUDENT','full_name_kh'=>'សិស្ស']);
        $this->regular=StudentEnrollment::create(['student_id'=>2,'campus_id'=>1,'academic_year_id'=>1,'grade_id'=>1,'class_id'=>1,'status'=>true,'enrollment_status'=>'active','student_type'=>'old']);
        $this->promotion=['from_academic_year_id'=>1,'from_campus_id'=>1,'from_grade_id'=>1,'from_class_id'=>1,
            'to_academic_year_id'=>2,'to_campus_id'=>1,'to_grade_id'=>3,'to_class_id'=>4,'to_session_id'=>null,
            'effective_on'=>now()->toDateString(),'enrollment_ids'=>[1,$this->regular->id]];
        $this->workflow=app(EnrollmentWorkflowService::class);
        $this->actingAs($this->campus);
    });

    it('promotes the remaining class or selected students while preserving the approved placement and current enrollment', function ($endpoint) {
        $source=StudentEnrollment::findOrFail(1)->toArray();
        $target=$this->skipping->targetEnrollment->toArray();
        $approval=$this->skipping->attributesToArray();
        $this->postJson('/student-enrollment-workflows/'.$endpoint,$this->promotion)->assertOk()
            ->assertJsonPath('count',1)->assertJsonPath('skipped_count',1)
            ->assertJsonPath('skipped_students.0.student_id','2612345')
            ->assertJsonPath('skipped_students.0.grade','G5')->assertJsonPath('skipped_students.0.class','A')
            ->assertJsonPath('skipped_students.0.academic_year','2026-2027');
        expect(StudentEnrollment::findOrFail(1)->toArray())->toBe($source)
            ->and($this->skipping->fresh()->targetEnrollment->toArray())->toBe($target)
            ->and($this->skipping->fresh()->attributesToArray())->toBe($approval)
            ->and(StudentEnrollment::where('student_id',1)->where('academic_year_id',2)->count())->toBe(1)
            ->and(StudentEnrollment::where('student_id',2)->where('academic_year_id',2)->value('grade_id'))->toBe(3)
            ->and($this->regular->fresh()->enrollment_status)->toBe('completed')
            ->and(DB::table('tb_student_enrollment_workflow')->count())->toBe(1)
            ->and(StudentEnrollmentHistory::count())->toBe(3);
        $this->postJson('/student-enrollment-workflows/'.$endpoint,$this->promotion)->assertUnprocessable();
        expect(StudentEnrollment::count())->toBe(4);
    })->with(['class-promote','selected-promote']);

    it('shows grade skipping in the promotion preview and keeps the existing default picker behavior', function () {
        $query='academic_year_id=1&campus_id=1&grade_id=1&class_id=1&target_academic_year_id=2';
        $page=$this->getJson('/student-enrollment-workflows/enrollments?'.$query.'&include_existing=1')->assertOk();
        $students=collect($page->json())->keyBy('student_id');
        expect($students[1]['promotion_preview']['status'])->toBe('grade_skipping')
            ->and($students[1]['promotion_preview']['reference_number'])->toBe($this->skipping->reference_number)
            ->and($students[1]['promotion_preview']['grade'])->toBe('G5')
            ->and($students[2]['promotion_preview']['status'])->toBe('eligible');
        $this->getJson('/student-enrollment-workflows/enrollments?'.$query)->assertOk()->assertJsonCount(1)->assertJsonPath('0.student_id',2);
    });

    it('does not block a class where everyone already has an approved skipping placement', function () {
        $this->regular->delete();
        $this->postJson('/student-enrollment-workflows/class-promote',$this->promotion)->assertOk()
            ->assertJsonPath('count',0)->assertJsonPath('skipped_count',1);
        $this->postJson('/student-enrollment-workflows/class-promote',$this->promotion)->assertOk()
            ->assertJsonPath('count',0)->assertJsonPath('skipped_count',1);
        expect(StudentEnrollment::count())->toBe(2)
            ->and(StudentEnrollment::find(1)->enrollment_status)->toBe('active')
            ->and(DB::table('tb_student_enrollment_workflow')->count())->toBe(0)
            ->and(StudentEnrollmentHistory::count())->toBe(2);
    });

    it('keeps an existing skipping placement for individual promotion too', function () {
        $payload=$this->promotion+['enrollment_id'=>1];
        $this->postJson('/student-enrollment-workflows/promote',$payload)->assertOk()
            ->assertJsonPath('count',0)->assertJsonPath('skipped_count',1)->assertJsonPath('data.grade_id',2);
        expect(StudentEnrollment::count())->toBe(3)
            ->and(StudentEnrollment::find(1)->enrollment_status)->toBe('active')
            ->and(StudentEnrollmentHistory::count())->toBe(2);
    });

    it('requires review for an unrelated existing enrollment and rolls back the entire batch', function () {
        StudentEnrollment::create(['student_id'=>2,'campus_id'=>1,'academic_year_id'=>2,'grade_id'=>3,'class_id'=>4,'status'=>true,'enrollment_status'=>'pending']);
        $this->postJson('/student-enrollment-workflows/class-promote',$this->promotion)->assertUnprocessable()->assertJsonValidationErrors('from_class_id');
        $this->postJson('/student-enrollment-workflows/selected-promote',$this->promotion)->assertUnprocessable()->assertJsonValidationErrors('enrollment_ids');
        $preview=$this->getJson('/student-enrollment-workflows/enrollments?academic_year_id=1&target_academic_year_id=2&include_existing=1')->assertOk();
        expect(collect($preview->json())->keyBy('student_id')[2]['promotion_preview']['status'])->toBe('review')
            ->and($this->regular->fresh()->enrollment_status)->toBe('active')
            ->and(DB::table('tb_student_enrollment_workflow')->count())->toBe(0)
            ->and(StudentEnrollmentHistory::count())->toBe(2);
    });

    it('requires review when the skipping approval or its linked target is no longer valid', function ($invalid) {
        if ($invalid==='not approved') $this->skipping->update(['status'=>'rejected']);
        elseif ($invalid==='cancelled target') $this->skipping->targetEnrollment->update(['enrollment_status'=>'cancelled']);
        elseif ($invalid==='changed placement') $this->skipping->targetEnrollment->update(['grade_id'=>3,'class_id'=>4]);
        else $this->skipping->update(['target_enrollment_id'=>$this->regular->id]);
        $this->postJson('/student-enrollment-workflows/class-promote',$this->promotion)->assertUnprocessable()->assertJsonValidationErrors('from_class_id');
        expect($this->regular->fresh()->enrollment_status)->toBe('active')
            ->and(DB::table('tb_student_enrollment_workflow')->count())->toBe(0);
    })->with(['not approved','cancelled target','changed placement','wrong link']);

    it('does not complete the skipping source until its academic year finishes', function () {
        $this->workflow->promoteClass($this->promotion);
        expect(StudentEnrollment::find(1)->enrollment_status)->toBe('active');
        $year=\App\Models\AcademicYear::findOrFail(1);
        $year->lifecycle_status='finished';
        $sync=new ReflectionMethod(\App\Http\Controllers\AcademicYearController::class,'syncEnrollmentStatuses');
        $sync->invoke(app(\App\Http\Controllers\AcademicYearController::class),$year);
        expect(StudentEnrollment::find(1)->enrollment_status)->toBe('completed')
            ->and($this->skipping->fresh()->targetEnrollment->enrollment_status)->toBe('pending')
            ->and($this->skipping->fresh()->targetEnrollment->grade_id)->toBe(2);
    });
});


describe('grade skipping after normal promotion', function () {
    beforeEach(function () {
        Schema::table('tb_student_enrollment', function (Blueprint $t) {
            $t->date('ended_on')->nullable(); $t->string('exit_reason')->nullable();
            $t->unique(['student_id','academic_year_id']);
        });
        DB::table('tb_grade')->where('id',1)->update(['grade'=>'Grade 3','grade_short_name'=>'G3','grade_order'=>3]);
        DB::table('tb_grade')->where('id',2)->update(['grade'=>'Grade 5','grade_short_name'=>'G5','grade_order'=>5]);
        DB::table('tb_grade')->insert(['id'=>3,'grade'=>'Grade 4','grade_short_name'=>'G4','grade_order'=>4]);
        DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027','academic_year_code'=>'2627','lifecycle_status'=>'pending']);
        DB::table('tb_class')->insert([
            ['id'=>3,'class_name'=>'A','grade_id'=>2,'academic_year_id'=>2],
            ['id'=>4,'class_name'=>'A','grade_id'=>3,'academic_year_id'=>2],
        ]);
        $this->actingAs($this->campus);
        $this->workflow=app(EnrollmentWorkflowService::class);
        $this->promotionData=['to_academic_year_id'=>2,'to_grade_id'=>3,'to_class_id'=>4,'effective_on'=>now()->toDateString(),'reason'=>'Normal promotion'];
        $this->promoted=$this->workflow->promote(StudentEnrollment::findOrFail(1),$this->promotionData)->fresh();
        $this->originalPromotion=\App\Models\EnrollmentWorkflowAction::firstOrFail();
        $this->data=array_replace($this->data,['target_academic_year_id'=>2,'target_class_id'=>3]);
        ($this->configure)();
    });

    it('allows selecting the original completed enrollment and shows its already-promoted placement', function () {
        $this->getJson('/students/skipping-grade/students?academic_year_id=1&q=SOK')->assertOk()->assertJsonPath('students.0.id',1);
        $this->getJson('/students/skipping-grade/source-classes?academic_year_id=1&campus_id=1')->assertOk()->assertJsonPath('classes.0.label','G3A');
        $this->getJson('/students/skipping-grade/enrollment/1')->assertOk()
            ->assertJsonPath('existing_promotions.0.grade','G4A')->assertJsonPath('existing_promotions.0.grade_order',4)
            ->assertJsonPath('target_academic_years.0.id',2)->assertJsonCount(1,'target_academic_years');
        $this->postJson('/students/skipping-grade',$this->data)->assertOk();
        $record=StudentSkippingGrade::firstOrFail();
        expect($record->student_snapshot['source_grade'])->toBe('G3')
            ->and($record->student_snapshot['existing_promotion']['target']['id'])->toBe((string)$this->promoted->id)
            ->and($this->promoted->fresh()->grade_id)->toBe(3);
        $this->get('/students/skipping-grade/'.$record->id.'/edit')->assertOk()->assertSee('Already promoted to G4A');
        $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('Already Promoted')->assertSee('G4A → G5A');
    });

    it('updates the existing next-year enrollment only after central approval and preserves the original promotion', function ($yearStatus) {
        DB::table('tb_academic_year')->where('id',2)->update(['lifecycle_status'=>$yearStatus]);
        $this->promoted->update(['enrollment_status'=>$yearStatus==='started'?'active':'pending']);
        $source=StudentEnrollment::find(1)->toArray();
        $oldPromotion=$this->originalPromotion->toArray();
        $oldHistory=StudentEnrollmentHistory::firstOrFail()->toArray();
        $record=($this->pending)();
        $this->actingAs($this->central)->postJson('/students/skipping-grade/'.$record->id.'/approve',$this->approval)->assertOk();
        $record->refresh();
        expect(StudentEnrollment::count())->toBe(2)
            ->and($record->target_enrollment_id)->toBe($this->promoted->id)
            ->and($this->promoted->fresh()->grade_id)->toBe(2)
            ->and($this->promoted->fresh()->class_id)->toBe(3)
            ->and($this->promoted->fresh()->enrolled_on->toDateString())->toBe($this->promoted->enrolled_on->toDateString())
            ->and($this->promoted->fresh()->enrollment_status)->toBe($yearStatus==='started'?'active':'pending')
            ->and(StudentEnrollment::find(1)->toArray())->toBe($source)
            ->and($record->reference_number)->toBe('SG2526-001')
            ->and($this->originalPromotion->fresh()->toArray())->toBe($oldPromotion)
            ->and(StudentEnrollmentHistory::firstOrFail()->toArray())->toBe($oldHistory);
        $history=StudentEnrollmentHistory::where('action_type','grade_skipping_from')->firstOrFail();
        $after=StudentEnrollmentHistory::where('action_type','grade_skipping')->firstOrFail();
        expect([$history->enrollment_id,$history->academic_year_id,$history->grade_id])->toBe([$this->promoted->id,2,3])
            ->and([$after->enrollment_id,$after->academic_year_id,$after->grade_id,$after->source_history_id])->toBe([$this->promoted->id,2,2,$history->id]);
        $this->get('/students/skipping-grade/'.$record->id)->assertOk()->assertSee('The existing enrollment was updated');
    })->with(['pending','started']);

    it('appends skipping approval remarks to an already promoted enrollment without losing existing notes', function () {
        StudentEnrollment::findOrFail(1)->update(['notes'=>'Original source remark']);
        $this->promoted->update(['notes'=>"Normal promotion remark\nKeep this instruction"]);
        $record=($this->pending)();
        expect($this->promoted->fresh()->notes)->toBe("Normal promotion remark\nKeep this instruction");
        $record=$this->service->approve($this->central,$record,$this->approval);
        expect($record->target_enrollment_id)->toBe($this->promoted->id)
            ->and($this->promoted->fresh()->notes)->toBe("Normal promotion remark\nKeep this instruction\nSkipped from G3A | Approval No: SG2526-001")
            ->and(StudentEnrollment::find(1)->notes)->toBe('Original source remark')
            ->and(StudentEnrollment::count())->toBe(2);
        $historyNotes=filled($record->reason) ? $record->reason."\nSkipped from G3A | Approval No: SG2526-001" : 'Skipped from G3A | Approval No: SG2526-001';
        expect(StudentEnrollmentHistory::whereIn('action_type',['grade_skipping_from','grade_skipping'])->pluck('notes')->all())
            ->toBe([$historyNotes,$historyNotes]);
    });

    it('keeps the promoted grade unchanged while pending or rejected', function () {
        $this->promoted->update(['notes'=>'Existing promotion remark']);
        $placement=$this->promoted->toArray();
        $record=($this->pending)();
        expect($this->promoted->fresh()->toArray())->toBe($placement);
        $this->service->reject($this->central,$record,'Committee declined the skipping request.');
        expect($this->promoted->fresh()->toArray())->toBe($placement)
            ->and(StudentEnrollment::count())->toBe(2)
            ->and(StudentEnrollmentHistory::count())->toBe(1)
            ->and(StudentEnrollment::find(1)->enrollment_status)->toBe('completed');
    });

    it('requires a higher grade than the existing promoted placement', function () {
        $this->postJson('/students/skipping-grade',array_replace($this->data,['target_grade_id'=>3,'target_class_id'=>4]))
            ->assertUnprocessable()->assertJsonValidationErrors('target_grade_id');
        expect(StudentSkippingGrade::count())->toBe(0)->and($this->promoted->fresh()->grade_id)->toBe(3);
    });

    it('blocks approval if the placement changed after submission', function ($changed) {
        $record=($this->pending)();
        if ($changed==='group') $this->promoted->update(['group_id'=>99]);
        elseif ($changed==='class') $this->promoted->update(['class_id'=>3]);
        elseif ($changed==='status') $this->promoted->update(['enrollment_status'=>'withdrawn']);
        else $this->workflow->cancelPromotion($this->originalPromotion,['effective_on'=>now()->toDateString(),'reason'=>'Placement cancelled']);
        $before=$this->promoted->fresh()->toArray();
        $this->actingAs($this->central)->postJson('/students/skipping-grade/'.$record->id.'/approve',$this->approval)->assertUnprocessable();
        expect($record->fresh()->status)->toBe('pending')
            ->and($this->promoted->fresh()->toArray())->toBe($before)
            ->and(StudentEnrollmentHistory::whereIn('action_type',['grade_skipping_from','grade_skipping'])->count())->toBe(0)
            ->and(Storage::disk('local')->allFiles('grade-skipping/approvals'))->toBe([]);
    })->with(['group','class','status','cancelled']);

    it('requires review when normal promotion occurs after a skipping draft was saved', function () {
        StudentEnrollmentHistory::query()->delete();
        $this->originalPromotion->delete();
        $this->promoted->delete();
        $source=StudentEnrollment::find(1);
        $source->update(['enrollment_status'=>'active','ended_on'=>null,'exit_reason'=>null]);
        $record=($this->draft)();
        $this->workflow->promote($source,$this->promotionData);
        expect(fn()=>$this->service->submit($this->campus,$record,now()->toDateString()))->toThrow(ValidationException::class);
        $record=$this->service->saveDraft($this->campus,$this->data,$record);
        $record=$this->service->submit($this->campus,$record,now()->toDateString());
        expect($record->status)->toBe('pending')->and($record->student_snapshot['existing_promotion']['grade'])->toBe('G4A');
    });

    it('can use the original enrollment after its academic year finishes', function () {
        DB::table('tb_academic_year')->where('id',1)->update(['lifecycle_status'=>'finished']);
        $this->getJson('/students/skipping-grade/students?academic_year_id=1&q=SOK')->assertOk()->assertJsonPath('students.0.id',1);
        $record=($this->pending)();
        $record=$this->service->approve($this->central,$record,$this->approval);
        expect($record->reference_number)->toBe('SG2526-001')->and(StudentEnrollment::find(1)->enrollment_status)->toBe('completed');
    });

    it('allows a valid re-promotion while retaining its original source enrollment', function () {
        $cancelled=$this->workflow->cancelPromotion($this->originalPromotion,['effective_on'=>now()->toDateString(),'reason'=>'Temporarily cancelled']);
        $this->workflow->repromote($cancelled,['effective_on'=>now()->toDateString(),'reason'=>'Returned']);
        $this->getJson('/students/skipping-grade/students?academic_year_id=1&q=SOK')->assertOk()->assertJsonPath('students.0.id',1);
        $record=($this->pending)();
        $record=$this->service->approve($this->central,$record,$this->approval);
        expect($record->target_enrollment_id)->toBe($this->promoted->id)
            ->and($record->approval_snapshot['existing_promotion']['workflow_id'])->not->toBe($this->originalPromotion->id)
            ->and($this->promoted->fresh()->grade_id)->toBe(2)
            ->and(StudentEnrollment::count())->toBe(2);
    });

    it('does not let cancellation undo an approved skipping placement', function () {
        $record=($this->pending)();
        $record=$this->service->approve($this->central,$record,$this->approval);
        expect(fn()=>$this->workflow->cancelPromotion($this->originalPromotion,['effective_on'=>now()->toDateString(),'reason'=>'Cancel']))->toThrow(ValidationException::class);
        expect($this->promoted->fresh()->grade_id)->toBe(2)->and($this->originalPromotion->fresh()->status)->toBe('completed');
    });

    it('requires a real promotion link and keeps campus creation permissions on completed sources', function () {
        $this->originalPromotion->delete();
        $this->getJson('/students/skipping-grade/students?academic_year_id=1&q=SOK')->assertOk()->assertJsonCount(0,'students');
        $this->postJson('/students/skipping-grade',$this->data)->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');
        DB::table('tb_student_enrollment')->where('id',1)->update(['campus_id'=>2]);
        $this->postJson('/students/skipping-grade',$this->data)->assertForbidden();
        expect(StudentSkippingGrade::count())->toBe(0)->and($this->promoted->fresh()->grade_id)->toBe(3);
    });

    it('rolls back the reused enrollment and signature copies when approval history fails', function () {
        $record=($this->pending)();
        $before=$this->promoted->toArray();
        StudentEnrollmentHistory::creating(function ($history) { if ($history->action_type==='grade_skipping') throw new RuntimeException('History write failed'); });
        try { expect(fn()=>$this->service->approve($this->central,$record,$this->approval))->toThrow(RuntimeException::class); }
        finally { StudentEnrollmentHistory::flushEventListeners(); }
        expect($record->fresh()->status)->toBe('pending')
            ->and($this->promoted->fresh()->toArray())->toBe($before)
            ->and(StudentEnrollmentHistory::count())->toBe(1)
            ->and(Storage::disk('local')->allFiles('grade-skipping/approvals'))->toBe([]);
    });
});
