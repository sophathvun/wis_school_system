<?php

use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\PermissionHierarchy;
use App\Support\StudentEnrollmentPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutMiddleware();
    Schema::create('users', function (Blueprint $t) {
        $t->id(); $t->string('name'); $t->integer('active_campus_id')->nullable();
        $t->integer('department_id')->nullable(); $t->boolean('is_global')->default(false);
    });
    Schema::create('access_roles', function (Blueprint $t) {
        $t->id(); $t->string('code'); $t->integer('status')->default(1); $t->boolean('is_global')->default(false);
    });
    Schema::create('access_permissions', function (Blueprint $t) {
        $t->id(); $t->string('code')->unique(); $t->string('name'); $t->string('module'); $t->string('action'); $t->timestamps();
    });
    Schema::create('access_user_roles', function (Blueprint $t) { $t->integer('user_id'); $t->integer('role_id'); $t->integer('campus_id')->nullable(); });
    Schema::create('access_role_permissions', function (Blueprint $t) { $t->integer('role_id'); $t->integer('permission_id'); $t->timestamps(); $t->unique(['role_id', 'permission_id']); });
    Schema::create('access_user_campuses', function (Blueprint $t) { $t->integer('user_id'); $t->integer('campus_id'); $t->boolean('is_primary')->default(true); $t->timestamp('assigned_at')->nullable(); $t->timestamps(); });
    Schema::create('access_department_permissions', function (Blueprint $t) { $t->integer('department_id'); $t->integer('permission_id'); $t->timestamps(); $t->unique(['department_id', 'permission_id']); });
    Schema::create('access_user_permission_overrides', function (Blueprint $t) { $t->integer('user_id'); $t->integer('permission_id'); $t->boolean('allowed')->default(true); $t->timestamps(); $t->unique(['user_id', 'permission_id']); });
    Schema::create('tb_school_info', function (Blueprint $t) { $t->id(); $t->string('campus_name_en'); $t->integer('status')->default(1); $t->softDeletes(); });
    Schema::create('tb_academic_year', function (Blueprint $t) { $t->id(); $t->string('academic_year'); $t->string('period_type')->default('regular'); $t->string('lifecycle_status')->default('started'); $t->softDeletes(); });
    Schema::create('tb_student_enrollment', function (Blueprint $t) {
        $t->id(); $t->integer('student_id'); $t->integer('campus_id'); $t->integer('academic_year_id'); $t->string('enrollment_status')->default('active');
    });
    Schema::create('tb_student_document', function (Blueprint $t) { $t->id(); $t->integer('student_id'); $t->string('file_path')->nullable(); $t->timestamps(); $t->softDeletes(); });
    DB::table('users')->insert(['id'=>1, 'name'=>'Campus Staff', 'active_campus_id'=>1]);
    DB::table('access_roles')->insert(['id'=>1, 'code'=>'registrar']);
    DB::table('access_user_roles')->insert(['user_id'=>1, 'role_id'=>1, 'campus_id'=>1]);
    DB::table('access_user_campuses')->insert(['user_id'=>1, 'campus_id'=>1]);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'BCH'], ['id'=>2,'campus_name_en'=>'BKK'], ['id'=>3,'campus_name_en'=>'CEN']]);
    DB::table('tb_academic_year')->insert(['id'=>1,'academic_year'=>'2025-2026']);
    DB::table('tb_student_enrollment')->insert([
        ['id'=>1,'student_id'=>1,'campus_id'=>1,'academic_year_id'=>1],
        ['id'=>2,'student_id'=>2,'campus_id'=>2,'academic_year_id'=>1],
    ]);
    $this->grant = function (array $actions) {
        foreach (['students.view', 'students.enrollment.view', ...array_map(fn ($action) => 'students.enrollment.'.$action, $actions)] as $code) {
            $id = DB::table('access_permissions')->where('code', $code)->value('id');
            $id ??= DB::table('access_permissions')->insertGetId(['code'=>$code, 'module'=>'students.enrollment', 'action'=>str($code)->afterLast('.'), 'name'=>$code]);
            DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>1,'permission_id'=>$id]);
        }
    };
    $this->actingAs(User::find(1));
});

it('allows campus editing and deleting only their own students with the corresponding permission', function () {
    ($this->grant)(['update', 'delete']);
    $access = new StudentEnrollmentPermissions(User::find(1));
    expect($access->canMutate(StudentEnrollment::find(1), 'update'))->toBeTrue()
        ->and($access->canMutate(StudentEnrollment::find(2), 'update'))->toBeFalse();
    $this->postJson('/student-enrollments/save', ['enrollment_id'=>2, 'campus_id'=>1])->assertForbidden();
    $this->deleteJson('/student-enrollments/delete/2')->assertForbidden();
    expect(DB::table('tb_student_enrollment')->where('id',2)->exists())->toBeTrue();
    $this->deleteJson('/student-enrollments/delete/1')->assertOk();
    expect(DB::table('tb_student_enrollment')->where('id',1)->exists())->toBeFalse();
});

it('requires explicit edit and delete permissions even for an assigned campus', function () {
    ($this->grant)(['profile']);
    $this->postJson('/student-enrollments/save', ['enrollment_id'=>1])->assertForbidden();
    $this->deleteJson('/student-enrollments/delete/1')->assertForbidden();
});

it('allows central staff across campuses but still requires action permissions', function (bool $global) {
    if ($global) DB::table('users')->where('id',1)->update(['is_global'=>true]);
    else DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>3]);
    $this->actingAs(User::find(1));
    ($this->grant)(['update']);
    $access = new StudentEnrollmentPermissions(User::find(1));
    expect($access->canMutate(StudentEnrollment::find(2), 'update'))->toBeTrue()
        ->and($access->canMutate(StudentEnrollment::find(2), 'delete'))->toBeFalse();
    $this->deleteJson('/student-enrollments/delete/2')->assertForbidden();
    ($this->grant)(['delete']);
    $this->deleteJson('/student-enrollments/delete/2')->assertOk();
})->with([false, true]);

it('does not grant a former campus edit access through historical enrollment', function () {
    ($this->grant)(['update']);
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2024-2025','lifecycle_status'=>'finished']);
    DB::table('tb_student_enrollment')->insert(['id'=>3,'student_id'=>2,'campus_id'=>1,'academic_year_id'=>2]);
    $this->postJson('/student-enrollments/save', ['enrollment_id'=>3])->assertForbidden();
});

it('supports edit permissions scoped to another assigned campus', function () {
    ($this->grant)([]);
    DB::table('access_roles')->insert(['id'=>2,'code'=>'second-campus-editor']);
    DB::table('access_user_roles')->insert(['user_id'=>1,'role_id'=>2,'campus_id'=>2]);
    DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>2]);
    $id = DB::table('access_permissions')->insertGetId(['code'=>'students.enrollment.update','name'=>'Edit','module'=>'students.enrollment','action'=>'update']);
    foreach (DB::table('access_permissions')->pluck('id') as $id) {
        DB::table('access_role_permissions')->insert(['role_id'=>2,'permission_id'=>$id]);
    }
    $access = new StudentEnrollmentPermissions(User::find(1));
    expect($access->canUseForm())->toBeTrue()->and($access->flags()['update'])->toBeTrue()
        ->and($access->canMutate(StudentEnrollment::find(2),'update'))->toBeTrue()
        ->and($access->canMutate(StudentEnrollment::find(1),'update'))->toBeFalse();
});

it('keeps cross campus reading and creating independent of mutation ownership', function () {
    ($this->grant)(['filter', 'profile', 'history', 'create']);
    $access = new StudentEnrollmentPermissions(User::find(1));
    foreach (['filter', 'profile', 'history', 'create'] as $action) expect($access->allows($action))->toBeTrue();
    expect($access->canMutate(StudentEnrollment::find(2), 'update'))->toBeFalse();
    // Validation rather than a campus denial: creating is authorized even for another campus.
    Schema::create('tb_student', function (Blueprint $t) { $t->id(); $t->string('student_id'); });
    $this->postJson('/student-enrollments/save', ['campus_id'=>2])->assertUnprocessable();
});

it('returns students, profiles and history from other campuses to a permitted campus viewer', function () {
    ($this->grant)(['filter','profile','history']);
    Schema::create('tb_student', function (Blueprint $t) {
        $t->id(); foreach (['student_no','student_id','full_name_en','full_name_kh','family_number'] as $c) $t->string($c)->nullable();
    });
    Schema::table('tb_student_enrollment', function (Blueprint $t) {
        foreach (['grade_id','class_id','academic_track_id','session_id','group_id'] as $c) $t->integer($c)->nullable();
    });
    foreach (['grade'=>['grade','grade_order'],'class'=>['class_name','class_order'],
        'academic_track'=>['name_en'],'session'=>['session_order','session_short_name'],'group'=>[]] as $table=>$columns) {
        Schema::create('tb_'.$table, function (Blueprint $t) use ($columns) {
            $t->id(); foreach ($columns as $c) $t->string($c)->nullable(); $t->softDeletes();
        });
    }
    Schema::create('tb_family', function (Blueprint $t) { $t->id(); $t->softDeletes(); });
    Schema::create('tb_family_student', function (Blueprint $t) {
        $t->integer('student_id'); $t->integer('family_id'); $t->string('relationship_type')->nullable();
        $t->boolean('is_primary_contact')->default(false); $t->boolean('has_pickup_authorization')->default(false);
        $t->boolean('has_portal_access')->default(false); $t->timestamps();
    });
    Schema::create('tb_student_enrollment_workflow', function (Blueprint $t) {
        $t->id(); $t->integer('target_enrollment_id'); $t->integer('from_campus_id'); $t->integer('to_campus_id'); $t->string('action_type');
    });
    Schema::create('tb_student_enrollment_history', function (Blueprint $t) {
        $t->id(); $t->integer('enrollment_id'); $t->date('effective_on')->nullable(); $t->timestamps();
    });
    DB::table('tb_student')->insert([['id'=>1,'student_id'=>'ONE','full_name_en'=>'A'], ['id'=>2,'student_id'=>'TWO','full_name_en'=>'B']]);
    $this->getJson('/student-enrollments/fetch')->assertOk()->assertJsonPath('total',2)
        ->assertJsonPath('data.1.campus_id',2)->assertJsonPath('data.1.can_edit',false)->assertJsonPath('data.1.can_delete',false);
    $this->getJson('/student-enrollments/fetch?campus_id=2')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.student_id',2);
    // Supply bound models explicitly; this fixture disables route middleware.
    $controller = app(\App\Http\Controllers\StudentEnrollmentController::class);
    expect($controller->profile(\App\Models\Student::find(2))->getData(true)['student']['student_id'])->toBe('TWO')
        ->and($controller->history(StudentEnrollment::find(2))->getData(true)['student']['student_id'])->toBe('TWO')
        ->and($controller->studentAcademicYears(\App\Models\Student::find(2))->getData(true)['enrollments'][0]['campus_id'])->toBe(2);
});

it('protects cross campus document deletion before deleting its file', function () {
    Storage::fake('public'); Storage::disk('public')->put('student-documents/test.pdf', 'original');
    DB::table('tb_student_document')->insert(['id'=>1,'student_id'=>2,'file_path'=>'student-documents/test.pdf']);
    request()->setUserResolver(fn () => User::find(1));
    expect(fn () => app(\App\Http\Controllers\StudentDocumentController::class)->delete(\App\Models\StudentDocument::find(1)))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'You can only change documents for students in your campus.');
    Storage::disk('public')->assertExists('student-documents/test.pdf');
    expect(DB::table('tb_student_document')->whereNull('deleted_at')->exists())->toBeTrue();
});

it('allows creation attachments temporarily without granting ongoing cross campus edits', function () {
    ($this->grant)(['create']);
    $this->withSession(['enrollment_document_creation'=>[2=>['user_id'=>1,'expires_at'=>now()->addMinutes(10)->timestamp]]]);
    $request = \Illuminate\Http\Request::create('/student-documents', 'POST');
    $request->setLaravelSession(app('session')->driver());
    app()->instance('request', $request);
    $access = new StudentEnrollmentPermissions(User::find(1));
    expect($access->canUploadDocuments(2))->toBeTrue()->and($access->canAccessStudentCampus(2))->toBeFalse();
    $request->session()->put('enrollment_document_creation.2.expires_at', now()->subSecond()->timestamp);
    expect($access->canUploadDocuments(2))->toBeFalse();
});

it('adds the action catalog to the permission tree and preserves existing read assignments', function () {
    ($this->grant)(['manage']);
    $migration = require database_path('migrations/2026_10_20_000001_add_student_enrollment_action_permissions.php');
    $migration->up(); $migration->up();
    $tree = PermissionHierarchy::tree(\App\Models\Permission::all());
    $actions = $tree['students']['modules']['students.enrollment']['actions']->pluck('action')->all();
    expect($actions)->not->toContain('manage', 'status');
    foreach (array_diff(array_keys(StudentEnrollmentPermissions::catalog()), ['view']) as $action) expect($actions)->toContain($action);
    $access = new StudentEnrollmentPermissions(User::find(1));
    expect($access->allows('filter'))->toBeTrue()->and($access->allows('history'))->toBeTrue()
        ->and($access->allows('update'))->toBeTrue()->and($access->allows('delete'))->toBeFalse();
});
