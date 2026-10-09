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
    Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->integer('active_campus_id')->nullable(); $t->integer('department_id')->nullable(); $t->boolean('is_global')->default(false); $t->integer('status')->default(1); $t->timestamps(); });
    Schema::create('access_roles', function (Blueprint $t) { $t->id(); $t->string('code'); $t->string('name'); $t->integer('status')->default(1); $t->boolean('is_global')->default(false); $t->boolean('is_system')->default(false); $t->integer('department_id')->nullable(); $t->softDeletes(); $t->timestamps(); });
    Schema::create('access_permissions', function (Blueprint $t) { $t->id(); $t->string('code')->unique(); $t->string('name'); $t->string('module'); $t->string('action'); $t->timestamps(); });
    Schema::create('access_user_roles', function (Blueprint $t) { $t->integer('user_id'); $t->integer('role_id'); $t->integer('campus_id')->nullable(); $t->timestamps(); });
    foreach (['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id','access_user_permission_overrides'=>'user_id'] as $table=>$owner) Schema::create($table, function (Blueprint $t) use ($owner) { $t->integer($owner); $t->integer('permission_id'); $t->unique([$owner,'permission_id']); $t->timestamps(); if ($owner==='user_id') $t->boolean('allowed'); });
    Schema::create('access_user_campuses', function (Blueprint $t) { $t->integer('user_id'); $t->integer('campus_id'); $t->boolean('is_primary')->default(true); $t->timestamp('assigned_at')->nullable(); $t->timestamps(); });
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
    $this->grant=function (int $user, array $actions) { foreach ($actions as $action) { $id=DB::table('access_permissions')->where('code',$action==='students.view'?$action:'student-skipping-grade.'.$action)->value('id'); DB::table('access_user_permission_overrides')->updateOrInsert(['user_id'=>$user,'permission_id'=>$id],['allowed'=>true]); } };
    ($this->grant)(2,['students.view','view','requests','approve','reject','settings','save-settings','request-template','edit-request-template','approval-template','edit-approval-template','print']);
    $this->campus=User::find(1); $this->central=User::find(2); $this->service=app(StudentSkippingGradeService::class);
    $this->data=['enrollment_id'=>1,'target_academic_year_id'=>1,'target_grade_id'=>2,'target_class_id'=>2,'target_session_id'=>null,'application_date'=>now()->toDateString(),'parent_name'=>'SOK RITH','parent_phone'=>'012345678','reason'=>'Completed Grade 3 at another school.','criteria'=>['age'=>true,'average'=>true,'documents'=>true,'recommendation'=>true],'average_score'=>95,'average_scale'=>100,'committee_names'=>array_fill(0,4,'Committee Member')];
    $this->approval=['vp_signed'=>true,'approval_date'=>now()->toDateString(),'received_date'=>now()->toDateString(),'review_date'=>now()->toDateString(),'effective_date'=>now()->toDateString(),'age_decision'=>'standard','school_decision'=>'external','obligations'=>['conduct'=>true,'rules'=>true,'study'=>true]];
    $this->draft=fn()=>$this->service->saveDraft($this->campus,$this->data);
    $this->pending=function () { return $this->service->submit($this->campus,($this->draft)(),now()->toDateString()); };
    $this->configure=function () { foreach (['signature','stamp'] as $key) Storage::disk('local')->put('settings/'.$key.'.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j3ioAAAAASUVORK5CYII=')); SkippingGradeSetting::current()->update(['signer_name_kh'=>'គីង រដ្ឋមុនី','signer_name_en'=>'KING ROTH MONY','signature_path'=>'settings/signature.png','stamp_path'=>'settings/stamp.png']); };
});

it('paginates skipping requests with a default of 20 and supports all rows without losing filters', function () {
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    $old=\App\Http\Controllers\StudentSkippingGradeController::CRITERIA['age'];
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
    DB::table('tb_student')->insert(['id'=>2,'student_id'=>'OTHER123','full_name_en'=>'OTHER CAMPUS STUDENT','full_name_kh'=>'សុខ','date_of_birth'=>'2017-03-02']);
    DB::table('tb_student_enrollment')->insert(['id'=>2,'student_id'=>2,'campus_id'=>2,'academic_year_id'=>1,'grade_id'=>1,'class_id'=>1]);
    $data=array_replace($this->data,['enrollment_id'=>2]);
    $this->actingAs($this->campus)->get('/students/skipping-grade/create')->assertOk()->assertSee('DNG');
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
    Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
        Schema::create('user_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
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
