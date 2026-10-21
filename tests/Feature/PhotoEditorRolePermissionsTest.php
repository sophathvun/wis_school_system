<?php

use App\Http\Controllers\PhotoEditorController;
use App\Models\User;
use App\Services\CampusContext;
use App\Services\StudentPhotoBackground;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->id(); $t->string('name'); $t->integer('active_campus_id')->nullable(); $t->integer('department_id')->nullable();
        $t->boolean('is_global')->default(false); $t->integer('status')->default(1); $t->timestamp('last_seen_at')->nullable(); $t->timestamps();
    });
    Schema::create('access_roles', function (Blueprint $t) { $t->id(); $t->string('code'); $t->integer('status')->default(1); $t->boolean('is_global')->default(false); });
    Schema::create('access_departments', function (Blueprint $t) { $t->id(); $t->string('name'); });
    Schema::create('tb_school_info', function (Blueprint $t) { $t->id(); $t->string('campus_name_en'); $t->integer('status')->default(1); $t->softDeletes(); });
    Schema::create('access_permissions', function (Blueprint $t) { $t->id(); $t->string('code'); $t->string('module'); $t->string('action'); $t->string('name'); $t->timestamps(); });
    Schema::create('access_user_roles', function (Blueprint $t) { $t->integer('user_id'); $t->integer('role_id'); $t->integer('campus_id')->nullable(); });
    Schema::create('access_role_permissions', function (Blueprint $t) { $t->integer('role_id'); $t->integer('permission_id'); });
    Schema::create('access_department_permissions', function (Blueprint $t) { $t->integer('department_id'); $t->integer('permission_id'); });
    Schema::create('access_user_permission_overrides', function (Blueprint $t) { $t->integer('user_id'); $t->integer('permission_id'); $t->boolean('allowed'); $t->timestamps(); });
    DB::table('access_permissions')->insert(['id'=>1, 'code'=>'photo-editor.view', 'module'=>'photo-editor', 'action'=>'view', 'name'=>'Use Photo Editor']);
    DB::table('tb_school_info')->insert(['id'=>7,'campus_name_en'=>'Campus 7']);
    DB::table('access_roles')->insert(['id'=>1, 'code'=>'registrar-officer', 'is_global'=>false]);
    DB::table('users')->insert(['id'=>1, 'name'=>'Permitted Registrar', 'is_global'=>true, 'active_campus_id'=>7, 'last_seen_at'=>now()]);
    DB::table('access_user_roles')->insert(['user_id'=>1, 'role_id'=>1, 'campus_id'=>null]);
    DB::table('access_role_permissions')->insert(['role_id'=>1, 'permission_id'=>1]);
    $this->actingAs(User::findOrFail(1));
});

it('honors a global users enabled registrar role even when that role is not marked global', function () {
    // This legitimate user/role combination failed the former campus-bound gate.
    expect(User::find(1)->hasPermission('photo-editor.view', 7))->toBeFalse();
    expect(User::find(1)->canUsePhotoEditor())->toBeTrue();
    $this->postJson(route('photo-editor.size'), ['photo'=>UploadedFile::fake()->image('crop.png',354,472)])->assertOk();
});

it('uses the same role grant for the page, background removal and downloads without needing a campus', function () {
    $this->mock(CampusContext::class)->shouldNotReceive('resolve');
    $controller = Mockery::mock(PhotoEditorController::class)->makePartial();
    $controller->shouldReceive('index')->once()->andReturn(response('Photo Editor'));
    $this->app->instance(PhotoEditorController::class, $controller);
    $this->mock(StudentPhotoBackground::class)->shouldReceive('remove')->once()->andReturn('cutout');
    $this->get(route('photo-editor.index'))->assertOk()->assertSee('Photo Editor');
    $this->postJson(route('photo-editor.background'), ['photo'=>UploadedFile::fake()->image('source.png',354,472)])->assertOk();
    $this->postJson(route('photo-editor.download'), ['photo'=>UploadedFile::fake()->image('crop.png',354,472),'format'=>'jpg'])->assertOk();
});

it('keeps a standalone role grant valid when the selected campus changes or is missing', function (?int $campus) {
    DB::table('users')->where('id',1)->update(['is_global'=>false, 'active_campus_id'=>$campus]);
    DB::table('access_user_roles')->update(['campus_id'=>7]);
    $this->actingAs(User::find(1));
    $this->postJson(route('photo-editor.size'), ['photo'=>UploadedFile::fake()->image('crop.png',354,472)])->assertOk();
})->with([8, null]);

it('still denies every route when the role has no photo editor grant or is inactive', function (bool $inactive) {
    if ($inactive) DB::table('access_roles')->where('id',1)->update(['status'=>0]);
    else DB::table('access_role_permissions')->delete();
    $this->mock(StudentPhotoBackground::class)->shouldNotReceive('remove');
    $this->getJson(route('photo-editor.index'))->assertForbidden();
    foreach (['size','background','download'] as $action) $this->postJson(route('photo-editor.'.$action))->assertForbidden();
})->with([false,true]);

it('honors explicit department or user grants without creating campus access', function (string $source) {
    DB::table('access_role_permissions')->delete();
    DB::table('users')->where('id',1)->update(['is_global'=>false,'active_campus_id'=>null]);
    if ($source==='department') {
        DB::table('access_departments')->insert(['id'=>1,'name'=>'Academics']);
        DB::table('users')->where('id',1)->update(['department_id'=>1]);
        DB::table('access_department_permissions')->insert(['department_id'=>1,'permission_id'=>1]);
    } else DB::table('access_user_permission_overrides')->insert(['user_id'=>1,'permission_id'=>1,'allowed'=>true]);
    $this->actingAs(User::find(1));
    $this->postJson(route('photo-editor.size'), ['photo'=>UploadedFile::fake()->image('crop.png',354,472)])->assertOk();
})->with(['department','user']);

it('preserves campus restrictions for other permissions', function () {
    DB::table('users')->where('id',1)->update(['is_global'=>false,'active_campus_id'=>8]);
    DB::table('access_user_roles')->update(['campus_id'=>7]);
    DB::table('access_permissions')->insert(['id'=>2,'code'=>'students.enrollment.update','module'=>'students.enrollment','action'=>'update','name'=>'Edit students']);
    DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>2]);
    $user = User::find(1);
    expect($user->canUsePhotoEditor())->toBeTrue()
        ->and($user->hasPermission('students.enrollment.update',7))->toBeTrue()
        ->and($user->hasPermission('students.enrollment.update',8))->toBeFalse();
});

it('rejects a direct user override that is not allowed', function () {
    DB::table('access_role_permissions')->delete();
    DB::table('access_user_permission_overrides')->insert(['user_id'=>1,'permission_id'=>1,'allowed'=>false]);
    $this->postJson(route('photo-editor.size'))->assertForbidden();
});
