<?php

use App\Models\Family;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\FamilyService;
use App\Support\PermissionHierarchy;
use App\Support\SummerSchoolPermissions;
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
    Schema::create('tb_family', function (Blueprint $t) { $t->id(); $t->string('family_number')->nullable(); $t->softDeletes(); });
    Schema::create('tb_family_student', function (Blueprint $t) {
        $t->integer('student_id'); $t->integer('family_id'); $t->string('relationship_type')->nullable();
        $t->boolean('is_primary_contact')->default(false); $t->boolean('has_pickup_authorization')->default(false); $t->boolean('has_portal_access')->default(false); $t->timestamps();
    });
    DB::table('users')->insert(['id'=>1,'name'=>'Campus Registrar','active_campus_id'=>1]);
    DB::table('access_roles')->insert(['id'=>1,'code'=>'registrar']);
    DB::table('access_user_roles')->insert(['user_id'=>1,'role_id'=>1,'campus_id'=>1]);
    DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>1]);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'BCH'],['id'=>2,'campus_name_en'=>'BKK'],['id'=>3,'campus_name_en'=>'CEN']]);
    DB::table('tb_academic_year')->insert([
        ['id'=>1,'academic_year'=>'Summer 2026','period_type'=>'summer','lifecycle_status'=>'started'],
        ['id'=>2,'academic_year'=>'Summer 2025','period_type'=>'summer','lifecycle_status'=>'finished'],
        ['id'=>3,'academic_year'=>'2025-2026','period_type'=>'regular','lifecycle_status'=>'started'],
        ['id'=>4,'academic_year'=>'2026-2027','period_type'=>'regular','lifecycle_status'=>'pending'],
    ]);
    DB::table('tb_grade')->insert(['id'=>1,'grade'=>'Grade 1','grade_short_name'=>'1','grade_order'=>'1']);
    DB::table('tb_class')->insert(['id'=>1,'class_name'=>'1A','grade_id'=>1]);
    DB::table('tb_session')->insert(['id'=>1,'session_short_name'=>'AM','session_order'=>'1']);
    foreach ([1,2] as $id) {
        DB::table('tb_student')->insert(['id'=>$id,'student_no'=>'0000000'.$id,'student_id'=>'S260100'.$id,'full_name_en'=>'STUDENT '.$id,'family_number'=>'F'.$id,'status'=>1]);
        DB::table('tb_student_enrollment')->insert(['id'=>$id,'student_id'=>$id,'campus_id'=>$id,'academic_year_id'=>1,'grade_id'=>1,'class_id'=>1,'session_id'=>1,
            'enrollment_origin'=>'external','enrollment_status'=>'active','student_type'=>'new','continue_at_western'=>'yes','status'=>1,'enrolled_on'=>'2026-10-01']);
    }
    $this->grant = function (array $actions) {
        foreach (['students.view','summer-school.view', ...array_map(fn ($action) => 'summer-school.'.$action, $actions)] as $code) {
            $id = DB::table('access_permissions')->where('code',$code)->value('id');
            $id ??= DB::table('access_permissions')->insertGetId(['code'=>$code,'module'=>'summer-school','action'=>(string) str($code)->afterLast('.'),'name'=>$code]);
            DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>1,'permission_id'=>$id]);
        }
    };
    $this->payload = ['enrollment_origin'=>'external','academic_year_id'=>1,'campus_id'=>1,'grade_id'=>1,'class_id'=>1,'session_id'=>1,
        'full_name_en'=>'UPDATED STUDENT','mother_name_en'=>'MOTHER','mother_phone'=>'012345678','father_name_en'=>'FATHER','father_phone'=>'012345679','continue_at_western'=>'yes'];
    $this->mock(FamilyService::class, function ($mock) {
        $mock->shouldReceive('syncStudentFamily')->andReturn(new Family);
        $mock->shouldReceive('syncEnrollmentMember')->andReturnNull();
    });
    $this->actingAs(User::find(1));
});

it('allows filtering and viewing students across campuses without edit or delete permission', function () {
    ($this->grant)(['filter']);
    $this->getJson('/summer-school/fetch')->assertOk()->assertJsonPath('total',2)->assertJsonPath('data.0.can_edit',false)->assertJsonPath('data.0.can_delete',false);
    $this->getJson('/summer-school/fetch?campus_id=2&search=STUDENT')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.campus_id',2);
    $this->getJson('/summer-school/options')->assertOk()->assertJsonCount(3,'campuses');
    $this->getJson('/summer-school/filter-options')->assertOk()->assertJsonCount(2,'students');
    $this->getJson('/summer-school/2/details')->assertForbidden();
    $this->deleteJson('/summer-school/1')->assertForbidden();
});

it('requires filter and registration grants rather than just menu access', function () {
    ($this->grant)([]);
    $this->getJson('/summer-school/fetch?campus_id=2')->assertForbidden();
    $this->postJson('/summer-school/save',$this->payload)->assertForbidden();
});

it('registers a new external student at another campus with create permission only', function () {
    ($this->grant)(['create']);
    $this->postJson('/summer-school/save',[...$this->payload,'campus_id'=>2])->assertCreated()->assertJsonPath('data.campus_id',2);
    expect(DB::table('tb_student')->count())->toBe(3)->and(DB::table('tb_student_enrollment')->count())->toBe(3);
    $this->postJson('/summer-school/save',[...$this->payload,'enrollment_id'=>2])->assertForbidden();
});

it('registers an existing Western student across campuses without changing their profile', function () {
    ($this->grant)(['create']);
    $original = (array) DB::table('tb_student')->where('id',2)->first();
    $this->postJson('/summer-school/save',[...$this->payload,'campus_id'=>2,'student_record_id'=>2,'enrollment_origin'=>'internal'])
        ->assertCreated()->assertJsonPath('data.student_id',2);
    // QR generation is permitted; the personal and family details stay stored.
    expect(DB::table('tb_student')->where('id',2)->value('full_name_en'))->toBe($original['full_name_en'])
        ->and(DB::table('tb_student')->count())->toBe(2);
});

it('edits an own campus student in place and blocks another campus before writing', function () {
    ($this->grant)(['update']);
    $this->getJson('/summer-school/1/details')->assertOk()->assertJsonPath('student.student_id','S2601001');
    $this->getJson('/summer-school/2/details')->assertForbidden();
    $this->postJson('/summer-school/save',[...$this->payload,'enrollment_id'=>1])->assertOk()->assertJsonPath('data.id',1)->assertJsonPath('data.student.full_name_en','UPDATED STUDENT');
    expect(DB::table('tb_student')->count())->toBe(2)->and(DB::table('tb_student_enrollment')->count())->toBe(2);
    $this->postJson('/summer-school/save',[...$this->payload,'enrollment_id'=>2,'campus_id'=>1])->assertForbidden();
    expect(DB::table('tb_student')->where('id',2)->value('full_name_en'))->toBe('STUDENT 2');
});

it('preserves the student and assignment when editing an ended Summer period', function () {
    ($this->grant)(['update']);
    DB::table('tb_student_enrollment')->where('id',1)->update(['academic_year_id'=>2,'enrollment_status'=>'completed']);
    $this->postJson('/summer-school/save',[...$this->payload,'academic_year_id'=>2,'enrollment_id'=>1])->assertOk()->assertJsonPath('data.academic_year_id',2);
    $this->postJson('/summer-school/save',[...$this->payload,'enrollment_id'=>1,'campus_id'=>2])->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');
    $this->postJson('/summer-school/save',[...$this->payload,'enrollment_id'=>1,'student_record_id'=>2])->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');
    expect(DB::table('tb_student_enrollment')->where('id',1)->value('student_id'))->toBe(1);
});

it('rejects changes to enrollment information while preserving the student profile', function (string $field, mixed $value) {
    ($this->grant)(['update']);
    $original = (array) DB::table('tb_student_enrollment')->where('id',1)->first();
    $this->postJson('/summer-school/save',[...$this->payload,'enrollment_id'=>1,$field=>$value])
        ->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');
    expect((array) DB::table('tb_student_enrollment')->where('id',1)->first())->toBe($original)
        ->and(DB::table('tb_student')->where('id',1)->value('full_name_en'))->toBe('STUDENT 1');
})->with([
    ['academic_year_id',2], ['campus_id',2], ['grade_id',2], ['class_id',2],
    ['session_id',2], ['academic_track_id',2], ['group_id',2], ['enrollment_status','withdrawn'],
    ['enrolled_on','2026-10-02'], ['ended_on','2026-10-03'], ['student_record_id',2], ['status',0],
]);

it('limits deletion to the campus and requires its own permission', function () {
    ($this->grant)(['update','delete']);
    $this->deleteJson('/summer-school/2')->assertForbidden();
    $this->deleteJson('/summer-school/1')->assertOk();
    expect(DB::table('tb_student_enrollment')->where('id',2)->exists())->toBeTrue()->and(DB::table('tb_student')->count())->toBe(2);
});

it('rejects another campus photo edit before storing any file', function () {
    ($this->grant)(['update']);
    $this->postJson('/summer-school/save',[...$this->payload,'enrollment_id'=>2,'campus_id'=>2,
        'photo'=>\Illuminate\Http\UploadedFile::fake()->image('replacement.jpg',600,800)])->assertForbidden();
    expect(Storage::disk('public')->allFiles())->toBeEmpty()
        ->and(DB::table('tb_student')->where('id',2)->value('photo_path'))->toBeNull();
});

it('retains super administrator access across all campuses', function () {
    DB::table('access_roles')->where('id',1)->update(['code'=>'super-admin']);
    $this->postJson('/summer-school/save',[...$this->payload,'enrollment_id'=>2,'campus_id'=>2])->assertOk();
    $this->deleteJson('/summer-school/2')->assertOk();
});

it('allows central staff across campuses but never without the action grant', function (string $central) {
    if ($central === 'campus') DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>3]);
    elseif ($central === 'global-user') DB::table('users')->where('id',1)->update(['is_global'=>true]);
    else DB::table('access_roles')->where('id',1)->update(['is_global'=>true]);
    $this->actingAs(User::find(1));
    ($this->grant)(['update']);
    $this->postJson('/summer-school/save',[...$this->payload,'enrollment_id'=>2,'campus_id'=>2])->assertOk()->assertJsonPath('data.id',2);
    $this->deleteJson('/summer-school/2')->assertForbidden();
    ($this->grant)(['delete']);
    $this->deleteJson('/summer-school/2')->assertOk();
})->with(['campus','global-user','global-role']);

it('does not use an old Summer record to edit a transferred regular student profile', function () {
    ($this->grant)(['update']);
    DB::table('tb_student_enrollment')->insert(['id'=>3,'student_id'=>1,'campus_id'=>2,'academic_year_id'=>3,'enrollment_status'=>'active']);
    $this->postJson('/summer-school/save',[...$this->payload,'enrollment_id'=>1])->assertForbidden();
});

it('requires a separate Continue permission and checks the source campus', function () {
    ($this->grant)(['update']);
    $data = ['academic_year_id'=>4,'campus_id'=>2,'grade_id'=>1,'class_id'=>1,'session_id'=>1];
    $this->postJson('/summer-school/1/convert-to-western',$data)->assertForbidden();
    ($this->grant)(['convert']);
    $this->postJson('/summer-school/2/convert-to-western',$data)->assertForbidden();
    $this->postJson('/summer-school/1/convert-to-western',$data)->assertCreated()->assertJsonPath('data.campus_id',2);
});

it('does not permit Summer operations on regular enrollment IDs', function () {
    ($this->grant)(['update','delete','convert']);
    DB::table('tb_student_enrollment')->insert(['id'=>3,'student_id'=>1,'campus_id'=>1,'academic_year_id'=>3]);
    $this->deleteJson('/summer-school/3')->assertForbidden();
    $this->postJson('/summer-school/save',[...$this->payload,'enrollment_id'=>3])->assertForbidden();
});

it('registers all actions on the permission page without giving managers delete rights', function () {
    ($this->grant)(['manage']);
    $migration = require database_path('migrations/2026_10_21_000001_add_summer_school_action_permissions.php');
    $migration->up(); $migration->up();
    $actions = PermissionHierarchy::tree(\App\Models\Permission::all())['students']['modules']['summer-school']['actions']->pluck('action')->all();
    foreach (array_diff(array_keys(SummerSchoolPermissions::catalog()),['view']) as $action) expect($actions)->toContain($action);
    expect($actions)->not->toContain('manage');
    $access = new SummerSchoolPermissions(User::find(1));
    expect($access->allows('filter'))->toBeTrue()->and($access->allows('create'))->toBeTrue()->and($access->allows('update'))->toBeTrue()
        ->and($access->allows('convert'))->toBeTrue()->and($access->allows('delete'))->toBeFalse();
});
