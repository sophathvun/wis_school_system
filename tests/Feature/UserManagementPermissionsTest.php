<?php

use App\Models\{Role, User};
use App\Support\UserManagementPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

require_once __DIR__.'/../Fixtures/UserPermissionInheritance.php';

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\TrackUserPresence::class);
    createUserPermissionInheritanceFixture();
    Schema::table('users',function (Blueprint $t) {
        foreach (['staff_id','gender','phone','photo_path','password','login_identifier'] as $column) $t->string($column)->nullable();
        $t->integer('staff_profile_id')->nullable(); $t->integer('position_id')->nullable();
        $t->date('date_of_birth')->nullable(); $t->boolean('must_change_password')->default(false);
    });
    Schema::table('access_user_roles',fn (Blueprint $t) => $t->timestamps());
    DB::table('users')->where('id',1)->update(['active_campus_id'=>1]);
    DB::table('access_user_roles')->where('user_id',1)->update(['campus_id'=>1]);
    $this->grantUserActions = function (array $actions) {
        DB::table('access_role_permissions')->where('role_id',1)->delete();
        foreach ($actions as $action) {
            $id=DB::table('access_permissions')->where('code','users.'.$action)->value('id')
                ?? DB::table('access_permissions')->insertGetId(['code'=>'users.'.$action,'module'=>'users','action'=>$action,'name'=>$action]);
            DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>$id]);
        }
    };
    $this->newUserPayload = ['staff_id'=>'REG002','name'=>'New Registrar','username'=>'new-registrar',
        'email'=>'new-registrar@example.test','role_id'=>1,'campuses'=>[1],
        'status'=>1,'login_identifier'=>'username'];
    DB::table('tb_school_info')->insert(['id'=>1,'campus_name_en'=>'Campus One','status'=>1]);
    $this->actingAs(User::find(1));
});

it('creates a user with inherited registrar create permission and preserves campus role assignments',function () {
    ($this->grantUserActions)(['view','create']);
    $this->post(route('users.save'),$this->newUserPayload)->assertRedirect(route('users.index'))->assertSessionHasNoErrors();
    $user=User::where('username','new-registrar')->sole();
    expect($user->must_change_password)->toBeTrue()
        ->and($user->roles()->sole()->pivot->campus_id)->toBe(1)
        ->and($user->campuses()->pluck('tb_school_info.id')->all())->toBe([1]);
});

it('rejects creation without view or create permission and rejects update and delete with create-only permission',function () {
    ($this->grantUserActions)(['view']);
    $this->post(route('users.save'),$this->newUserPayload)->assertForbidden();
    ($this->grantUserActions)(['create']);
    $this->post(route('users.save'),$this->newUserPayload)->assertForbidden();
    ($this->grantUserActions)(['view','create']);
    $this->post(route('users.save'),array_merge($this->newUserPayload,['user_id'=>1]))->assertForbidden();
    $this->delete(route('users.delete',1))->assertForbidden();
    $this->get(route('users.index',['edit'=>1]))->assertForbidden();
    expect(User::count())->toBe(1);
});

it('protects super administrator assignment accounts and report endpoints from delegated creators',function () {
    ($this->grantUserActions)(['view','create','update','delete']);
    $super=Role::create(['name'=>'Super Administrator','code'=>'super-admin','status'=>1]);
    $this->post(route('users.save'),array_merge($this->newUserPayload,['role_id'=>$super->id]))->assertForbidden();
    $target=User::create(['name'=>'Protected Administrator','username'=>'protected-admin']);
    DB::table('access_user_roles')->insert(['user_id'=>$target->id,'role_id'=>$super->id,'campus_id'=>null]);
    $this->post(route('users.save'),array_merge($this->newUserPayload,['user_id'=>$target->id]))->assertForbidden();
    $this->delete(route('users.delete',$target))->assertForbidden();
    $this->get(route('users.print'))->assertForbidden();
    $this->get(route('users.excel'))->assertForbidden();
    expect($target->fresh()->username)->toBe('protected-admin');
});

it('honors legacy manage and campus boundaries without granting delete or export',function () {
    ($this->grantUserActions)(['view','manage']);
    $user=User::find(1);
    expect(UserManagementPermissions::allows($user,'create'))->toBeTrue()
        ->and(UserManagementPermissions::allows($user,'update'))->toBeTrue()
        ->and(UserManagementPermissions::allows($user,'delete'))->toBeFalse()
        ->and(UserManagementPermissions::allows($user,'export'))->toBeFalse();
    DB::table('access_user_roles')->where('user_id',1)->update(['campus_id'=>2]);
    expect(UserManagementPermissions::allows($user,'create'))->toBeFalse();
    DB::table('access_user_roles')->where('user_id',1)->update(['campus_id'=>1]);
    DB::table('access_roles')->where('id',1)->update(['status'=>0]);
    expect(UserManagementPermissions::allows($user,'create'))->toBeFalse();
});

it('allows the user photo tool for delegated creators and denies view-only users',function () {
    ($this->grantUserActions)(['view','create']);
    // An empty upload reaches image validation rather than the old super-admin 403.
    $this->postJson(route('users.photo.background'),[])->assertUnprocessable();
    ($this->grantUserActions)(['view']);
    $this->postJson(route('users.photo.background'),[])->assertForbidden();
});
