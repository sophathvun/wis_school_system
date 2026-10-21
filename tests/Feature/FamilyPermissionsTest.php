<?php

use App\Models\Family;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\FamilyService;
use App\Support\PermissionHierarchy;
use App\Support\FamilyPermissions;
use App\Models\FamilyMember;
use App\Models\FamilyHistory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Keep route model binding and authentication enabled for the mutation routes.
    $this->withoutMiddleware(\App\Http\Middleware\TrackUserPresence::class);
    Storage::fake('public');
    Schema::create('users', function (Blueprint $t) {
        $t->id(); $t->string('name'); $t->integer('active_campus_id')->nullable(); $t->integer('department_id')->nullable();
        $t->boolean('is_global')->default(false); $t->integer('status')->default(1);
    });
    Schema::create('access_roles', function (Blueprint $t) { $t->id(); $t->string('code'); $t->integer('status')->default(1); $t->boolean('is_global')->default(false); });
    Schema::create('access_permissions', function (Blueprint $t) { $t->id(); $t->string('code')->unique(); $t->string('name'); $t->string('module'); $t->string('action'); $t->timestamps(); });
    Schema::create('access_user_roles', function (Blueprint $t) { $t->integer('user_id'); $t->integer('role_id'); $t->integer('campus_id')->nullable(); });
    Schema::create('access_role_permissions', function (Blueprint $t) { $t->integer('role_id'); $t->integer('permission_id'); $t->timestamps(); $t->unique(['role_id','permission_id']); });
    Schema::create('access_user_campuses', function (Blueprint $t) { $t->integer('user_id'); $t->integer('campus_id'); $t->boolean('is_primary')->default(true); $t->timestamp('assigned_at')->nullable(); $t->timestamps(); });
    Schema::create('access_department_permissions', function (Blueprint $t) { $t->integer('department_id'); $t->integer('permission_id'); $t->timestamps(); $t->unique(['department_id','permission_id']); });
    Schema::create('access_user_permission_overrides', function (Blueprint $t) { $t->integer('user_id'); $t->integer('permission_id'); $t->boolean('allowed')->default(true); $t->timestamps(); $t->unique(['user_id','permission_id']); });
    Schema::create('tb_school_info', function (Blueprint $t) { $t->id(); $t->string('campus_name_en'); $t->integer('status')->default(1); $t->softDeletes(); });
    Schema::create('tb_academic_year', function (Blueprint $t) { $t->id(); $t->string('academic_year'); $t->string('period_type'); $t->string('lifecycle_status'); $t->integer('parent_academic_year_id')->nullable(); $t->softDeletes(); });
    Schema::create('tb_student', function (Blueprint $t) {
        $t->id(); foreach ((new Student)->getFillable() as $column) $t->string($column)->nullable(); $t->timestamps();
    });
    Schema::create('tb_student_enrollment', function (Blueprint $t) {
        $t->id(); foreach ((new StudentEnrollment)->getFillable() as $column) {
            if (str_ends_with($column, '_id') || $column === 'status') $t->integer($column)->nullable();
            else $t->string($column)->nullable();
        } $t->timestamps();
    });
    foreach (['grade'=>['grade','grade_short_name','grade_order'], 'class'=>['class_name','grade_id'],
        'session'=>['session_short_name','session_order'], 'academic_track'=>['name_en','name_kh','code'], 'group'=>['group_name'],
        'country'=>['country_name_en','country_name_kh','nationality_name_en','nationality_name_kh','flag_path'],
        'occupation'=>['occupation_name_en','occupation_name_kh'], 'student_document_type'=>['type_key','name_en','name_kh','sort_order']] as $table=>$columns) {
        Schema::create('tb_'.$table, function (Blueprint $t) use ($columns) { $t->id(); foreach ($columns as $c) $t->string($c)->nullable(); $t->integer('status')->default(1); $t->softDeletes(); });
    }
    Schema::create('tb_family', function (Blueprint $t) {
        $t->id(); foreach ((new Family)->getFillable() as $column) {
            if ($column === 'status') $t->integer($column)->default(1); else $t->string($column)->nullable();
        } $t->timestamps(); $t->softDeletes();
    });
    Schema::create('tb_family_member', function (Blueprint $t) {
        $t->id(); foreach ((new FamilyMember)->getFillable() as $column) {
            if (str_starts_with($column,'is_') || str_starts_with($column,'has_')) $t->boolean($column)->default(false);
            elseif ($column === 'status') $t->integer($column)->default(1);
            elseif (str_ends_with($column,'_id')) $t->integer($column)->nullable();
            else $t->string($column)->nullable();
        } $t->timestamps(); $t->softDeletes();
    });
    Schema::create('tb_family_student', function (Blueprint $t) {
        $t->integer('student_id'); $t->integer('family_id'); $t->string('relationship_type')->nullable();
        $t->boolean('is_primary_contact')->default(false); $t->boolean('has_pickup_authorization')->default(false); $t->boolean('has_portal_access')->default(false); $t->timestamps(); $t->unique(['family_id','student_id']);
    });
    Schema::create('tb_student_family_member', function (Blueprint $t) {
        $t->integer('student_id'); $t->integer('family_member_id'); $t->string('relationship_type'); $t->boolean('is_primary_contact')->default(false); $t->timestamps(); $t->unique(['student_id','family_member_id']);
    });
    DB::table('users')->insert(['id'=>1,'name'=>'Campus Registrar','active_campus_id'=>1]);
    DB::table('access_roles')->insert(['id'=>1,'code'=>'registrar']);
    DB::table('access_user_roles')->insert(['user_id'=>1,'role_id'=>1,'campus_id'=>1]);
    DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>1]);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'BCH'],['id'=>2,'campus_name_en'=>'BKK'],['id'=>3,'campus_name_en'=>'CEN']]);
    DB::table('tb_academic_year')->insert([
        ['id'=>1,'academic_year'=>'2025-2026','period_type'=>'regular','lifecycle_status'=>'started'],
        ['id'=>2,'academic_year'=>'2024-2025','period_type'=>'regular','lifecycle_status'=>'finished'],
    ]);
    DB::table('tb_grade')->insert(['id'=>1,'grade'=>'Grade 1']);
    DB::table('tb_class')->insert(['id'=>1,'class_name'=>'A']);
    DB::table('tb_session')->insert(['id'=>1,'session_short_name'=>'AM']);
    foreach ([1,2,3,4] as $id) DB::table('tb_family')->insert(['id'=>$id,'family_number'=>'F'.$id,'family_name'=>'Family '.$id]);
    foreach ([1,2,3,4,5] as $id) {
        $familyId = [1=>1,2=>1,3=>2,4=>3,5=>null][$id];
        DB::table('tb_student')->insert(['id'=>$id,'student_id'=>'STU'.$id,'full_name_en'=>'STUDENT '.$id,'family_number'=>$familyId ? 'F'.$familyId : null,'status'=>1]);
        DB::table('tb_student_enrollment')->insert(['id'=>$id,'student_id'=>$id,'campus_id'=>in_array($id,[2,3]) ? 2 : 1,
            'academic_year_id'=>$id===4 ? 2 : 1,'enrollment_status'=>'active','status'=>1,'grade_id'=>1,'class_id'=>1,'session_id'=>1]);
        if ($familyId) DB::table('tb_family_student')->insert(['student_id'=>$id,'family_id'=>$familyId]);
    }
    foreach ([1,2,3] as $id) DB::table('tb_family_member')->insert(['id'=>$id,'family_id'=>$id,'relationship_type'=>'mother','full_name_en'=>'MOTHER '.$id,'phone'=>'+85512888888']);
    DB::table('tb_student_family_member')->insert([
        ['student_id'=>1,'family_member_id'=>1,'relationship_type'=>'mother'],['student_id'=>2,'family_member_id'=>1,'relationship_type'=>'mother'],
        ['student_id'=>3,'family_member_id'=>2,'relationship_type'=>'mother'],['student_id'=>4,'family_member_id'=>3,'relationship_type'=>'mother'],
    ]);
    $this->migration = require database_path('migrations/2026_10_21_000002_add_family_permissions_and_history.php');
    $this->migration->up();
    $this->grant = function (array $actions) {
        foreach (['students.view','families.view', ...array_map(fn ($action) => 'families.'.$action, $actions)] as $code) {
            $id=DB::table('access_permissions')->where('code',$code)->value('id');
            $id ??= DB::table('access_permissions')->insertGetId(['code'=>$code,'module'=>'families','action'=>(string) str($code)->afterLast('.'),'name'=>$code]);
            DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>1,'permission_id'=>$id]);
        }
    };
    $this->familyPayload = ['family_id'=>1,'family_number'=>'F1','status'=>1,'family_name'=>'Updated Family'];
    $this->memberPayload = ['full_name_en'=>'UPDATED MOTHER','relationship_type'=>'mother','status'=>1,
        'is_primary_contact'=>1,'has_pickup_authorization'=>0,'has_portal_access'=>0];
    $this->actingAs(User::find(1));
});

it('allows reading and searching all families but only edits a family with a current campus child', function () {
    ($this->grant)(['search','update']);
    $response=$this->getJson('/families/fetch?sort=family_number')->assertOk()->assertJsonPath('total',4);
    $data=collect($response->json('data'))->keyBy('id');
    expect($data[1]['can_update'])->toBeTrue()->and($data[2]['can_update'])->toBeFalse()->and($data[3]['can_update'])->toBeFalse();
    expect($data[1]['shared_info']['children'])->toBe(2)->and($data[1]['shared_info']['campuses'])->toBe(['BCH','BKK']);
    $this->getJson('/families/fetch?search=Family%202')->assertOk()->assertJsonPath('total',1);
    $this->postJson('/families/save',$this->familyPayload)->assertOk();
    $this->postJson('/families/save',[...$this->familyPayload,'family_id'=>2,'family_number'=>'F2'])->assertForbidden();
    $this->postJson('/families/save',[...$this->familyPayload,'family_id'=>3,'family_number'=>'F3'])->assertForbidden();
});

it('allows either linked campus to edit shared contacts with the contact permission', function () {
    ($this->grant)(['members-update']);
    $this->postJson('/families/1/members/save',[...$this->memberPayload,'family_member_id'=>1])->assertOk();
    expect(Student::find(2)->familyMembers()->first()->full_name_en)->toBe('UPDATED MOTHER');
    DB::table('users')->where('id',1)->update(['active_campus_id'=>2]);
    DB::table('access_user_roles')->where('user_id',1)->update(['campus_id'=>2]);
    DB::table('access_user_campuses')->where('user_id',1)->update(['campus_id'=>2]);
    $this->actingAs(User::find(1));
    $this->postJson('/families/1/members/save',[...$this->memberPayload,'family_member_id'=>1,'full_name_en'=>'BKK UPDATE'])->assertOk();
    expect(Student::find(1)->familyMembers()->first()->full_name_en)->toBe('BKK UPDATE');
});

it('requires separate permissions for contacts and rolls back unauthorized embedded changes', function () {
    ($this->grant)(['update']);
    $this->postJson('/families/1/members/save',[...$this->memberPayload,'family_member_id'=>1])->assertForbidden();
    $this->postJson('/families/save',[...$this->familyPayload,'members'=>['mother'=>['full_name_en'=>'FORBIDDEN']]])->assertForbidden();
    expect(Family::find(1)->family_name)->toBe('Family 1')->and(FamilyMember::find(1)->full_name_en)->toBe('MOTHER 1')->and(FamilyHistory::count())->toBe(0);
});

it('creates an unlinked family with initial contacts but does not grant ongoing orphan edits', function () {
    ($this->grant)(['create','update']);
    $response=$this->postJson('/families/save',['family_number'=>'NEW','status'=>1,'members'=>['mother'=>['full_name_en'=>'NEW MOTHER']]])->assertCreated();
    $id=$response->json('data.id');
    $this->postJson('/families/save',['family_id'=>$id,'family_number'=>'NEW','status'=>1])->assertForbidden();
    $this->postJson('/families/save',['family_number'=>'NEW','status'=>1])->assertUnprocessable()->assertJsonValidationErrors('family_number');
});

it('requires link and unlink permissions when moving only an own campus child', function () {
    ($this->grant)(['link']);
    $this->getJson('/families/link-options')->assertOk()->assertJsonCount(1,'students')->assertJsonPath('students.0.id',5);
    $this->postJson('/families/change-student-family',['student_id'=>1,'family_id'=>4])->assertForbidden();
    ($this->grant)(['unlink']);
    $original=(array) DB::table('tb_student_enrollment')->where('student_id',1)->first();
    $this->postJson('/families/change-student-family',['student_id'=>2,'family_id'=>4])->assertForbidden();
    $this->postJson('/families/change-student-family',['student_id'=>1,'family_id'=>2])->assertOk();
    expect(Student::find(1)->family_number)->toBe('F2')->and(Family::find(1)->students->modelKeys())->toBe([2]);
    expect(Student::find(1)->familyMembers->modelKeys())->toBe([2])->and(Student::find(2)->familyMembers->modelKeys())->toBe([1]);
    expect((array) DB::table('tb_student_enrollment')->where('student_id',1)->first())->toBe($original);
    expect(FamilyHistory::where('family_id',1)->value('action'))->toBe('student_unlinked')
        ->and(FamilyHistory::where('family_id',2)->value('action'))->toBe('student_linked');
});

it('links an unlinked student without unlink permission and blocks old students and foreign campuses', function () {
    ($this->grant)(['link']);
    $this->postJson('/families/change-student-family',['student_id'=>5,'family_id'=>4])->assertOk();
    $this->postJson('/families/change-student-family',['student_id'=>3,'family_id'=>4])->assertForbidden();
    $this->postJson('/families/change-student-family',['student_id'=>4,'family_id'=>4])->assertForbidden();
});

it('unlinks only the selected own campus child and preserves other children and history', function () {
    ($this->grant)(['unlink']);
    $this->deleteJson('/families/1/students/2')->assertForbidden();
    $this->deleteJson('/families/1/students/1')->assertOk();
    expect(Student::find(1)->family_number)->toBeNull()->and(Student::find(1)->familyMembers()->count())->toBe(0)
        ->and(Family::find(1)->students->modelKeys())->toBe([2])->and(Student::find(2)->familyMembers()->count())->toBe(1);
});

it('prevents campus status and deletion even with explicit grants, including the generic status endpoint', function () {
    ($this->grant)(['update','status','delete']);
    $this->postJson('/families/save',[...$this->familyPayload,'status'=>0])->assertForbidden();
    $this->postJson('/status/toggle',['entity'=>'family','id'=>1,'status'=>0])->assertForbidden();
    $this->deleteJson('/families/4')->assertForbidden();
    expect(Family::find(1)->status)->toBe(1);
});

it('allows central staff to correct former and other campus families only with permission', function (string $central) {
    if ($central==='campus') DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>3]);
    elseif ($central==='user') DB::table('users')->where('id',1)->update(['is_global'=>true]);
    else DB::table('access_roles')->where('id',1)->update(['is_global'=>true]);
    $this->actingAs(User::find(1));
    ($this->grant)(['update']);
    $this->postJson('/families/save',[...$this->familyPayload,'family_id'=>3,'family_number'=>'F3'])->assertOk();
    $this->deleteJson('/families/4')->assertForbidden();
    ($this->grant)(['delete','status']);
    $this->deleteJson('/families/1')->assertUnprocessable();
    $this->deleteJson('/families/4')->assertOk();
    $this->postJson('/status/toggle',['entity'=>'family','id'=>3,'status'=>0])->assertOk();
    expect(Family::withTrashed()->find(4)->trashed())->toBeTrue()->and(FamilyHistory::where('family_id',4)->value('action'))->toBe('family_deleted');
})->with(['campus','user','role']);

it('records the editor and before and after values and protects history by permission', function () {
    ($this->grant)(['update','members-update']);
    $this->postJson('/families/save',[...$this->familyPayload,'members'=>['mother'=>['full_name_en'=>'NEW NAME','phone'=>'+85512888888']]])->assertOk();
    $entry=FamilyHistory::first();
    expect($entry->changed_by)->toBe(1)->and($entry->changed_by_name)->toBe('Campus Registrar')
        ->and($entry->before_values['members'][0]['full_name_en'])->toBe('MOTHER 1')
        ->and($entry->after_values['members'][0]['full_name_en'])->toBe('NEW NAME');
    $this->getJson('/families/1/history')->assertForbidden();
    ($this->grant)(['history']);
    $this->getJson('/families/1/history')->assertOk()->assertJsonPath('total',1);
    $this->getJson('/families/history')->assertOk()->assertJsonPath('total',1);
});

it('enforces each contact action and synchronizes contact links for both campuses', function () {
    ($this->grant)(['members-create','members-delete']);
    $this->postJson('/families/2/members/save',$this->memberPayload)->assertForbidden();
    $response=$this->postJson('/families/1/members/save',[...$this->memberPayload,'relationship_type'=>'guardian'])->assertCreated();
    $id=$response->json('data.id');
    expect(Student::find(1)->familyMembers()->whereKey($id)->exists())->toBeTrue()->and(Student::find(2)->familyMembers()->whereKey($id)->exists())->toBeTrue();
    $this->deleteJson('/families/2/members/'.$id)->assertNotFound();
    $this->deleteJson('/families/1/members/'.$id)->assertOk();
    expect(Student::find(2)->familyMembers()->whereKey($id)->exists())->toBeFalse();
});

it('protects print and export separately and exports all filtered families', function () {
    ($this->grant)([]);
    $this->get('/families/print')->assertForbidden();
    $this->get('/families/export')->assertForbidden();
    ($this->grant)(['print','export','search']);
    $this->get('/families/print?search=F1')->assertOk()->assertSee('Family List')->assertSee('STUDENT 2');
    $response=$this->get('/families/export?search=F1')->assertOk()->assertDownload();
    $csv=$response->streamedContent();
    expect($csv)->toContain('Family Number')->toContain('STUDENT 1')->toContain('STUDENT 2')->not->toContain('STUDENT 3');
});

it('does not grant campus edits through a former or inactive enrollment', function (string $state) {
    ($this->grant)(['update']);
    if ($state==='year-deleted') DB::table('tb_academic_year')->where('id',1)->update(['deleted_at'=>now()]);
    elseif ($state==='year-finished') DB::table('tb_academic_year')->where('id',1)->update(['lifecycle_status'=>'finished']);
    elseif ($state==='inactive') DB::table('tb_student_enrollment')->where('id',1)->update(['status'=>0]);
    else DB::table('tb_student_enrollment')->where('id',1)->update(['enrollment_status'=>$state]);
    $this->postJson('/families/save',$this->familyPayload)->assertForbidden();
})->with(['year-deleted','year-finished','inactive','transferred','withdrawn']);

it('preserves all enrollment assignments when a family is renumbered', function () {
    ($this->grant)(['update']);
    $before=DB::table('tb_student_enrollment')->orderBy('id')->get()->toJson();
    $this->postJson('/families/save',[...$this->familyPayload,'family_number'=>'NEW-F1'])->assertOk();
    expect(Student::find(1)->family_number)->toBe('NEW-F1')->and(Student::find(2)->family_number)->toBe('NEW-F1')
        ->and(DB::table('tb_student_enrollment')->orderBy('id')->get()->toJson())->toBe($before);
});

it('preserves contact details that are not included in the family edit form', function () {
    ($this->grant)(['update','members-update']);
    DB::table('tb_family_member')->where('id',1)->update(['email'=>'parent@example.test','occupation_en'=>'Teacher']);
    $this->postJson('/families/save',[...$this->familyPayload,'members'=>['mother'=>['full_name_en'=>'UPDATED NAME']]])->assertOk();
    expect(FamilyMember::find(1)->email)->toBe('parent@example.test')->and(FamilyMember::find(1)->occupation_en)->toBe('Teacher');
});

it('preserves family data when recording the history fails', function () {
    ($this->grant)(['update']);
    $this->mock(\App\Services\FamilyHistoryRecorder::class, function ($mock) {
        $mock->shouldReceive('snapshot')->andReturn(['snapshot'=>'original']);
        $mock->shouldReceive('record')->andThrow(new RuntimeException('History storage failed'));
    });
    $this->postJson('/families/save',$this->familyPayload)->assertServerError();
    expect(Family::find(1)->family_name)->toBe('Family 1');
});

it('includes all family functions in the permission tree and migrates legacy grants', function () {
    ($this->grant)(['manage']);
    Schema::drop('tb_family_history'); $this->migration->up();
    $actions=PermissionHierarchy::tree(\App\Models\Permission::all())['students']['modules']['families']['actions']->pluck('action')->all();
    foreach (array_diff(array_keys(FamilyPermissions::catalog()),['view']) as $action) expect($actions)->toContain($action);
    expect($actions)->not->toContain('manage');
    $access=new FamilyPermissions(User::find(1));
    expect($access->allows('search'))->toBeTrue()->and($access->allows('members-update'))->toBeTrue()
        ->and($access->allows('link'))->toBeTrue()->and($access->allows('history'))->toBeTrue()->and($access->allows('delete'))->toBeFalse();
});
