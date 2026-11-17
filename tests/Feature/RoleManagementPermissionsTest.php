<?php

use App\Http\Controllers\RoleManagementController;
use App\Models\{Role, User};
use App\Support\RolePermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/UserPermissionInheritance.php';

beforeEach(function () {
    createUserPermissionInheritanceFixture();
    Schema::table('access_roles', fn (Blueprint $t) => $t->string('description')->nullable());
    DB::table('users')->where('id',1)->update(['active_campus_id'=>1]);
    DB::table('access_user_roles')->where('user_id',1)->update(['campus_id'=>1]);
    foreach (['view','create','update','manage','delete','print','export'] as $action) {
        $this->rolePermissionIds[$action] = DB::table('access_permissions')->insertGetId([
            'code'=>'roles.'.$action,'module'=>'roles','action'=>$action,'name'=>$action,
        ]);
    }
    $this->grantRoles = function (array $actions) {
        DB::table('access_role_permissions')->where('role_id',1)->delete();
        foreach ($actions as $action) DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>$this->rolePermissionIds[$action]]);
    };
    $this->roleRequest = function (array $data = [], string $method = 'GET') {
        $request = Request::create('/settings/roles',$method,$data);
        $request->setUserResolver(fn () => User::find(1));
        return $request;
    };
});

it('opens roles for a campus registrar with view permission and exposes only granted actions',function () {
    ($this->grantRoles)(['view','create']);
    $data = (new RoleManagementController)->index(($this->roleRequest)())->getData();
    expect($data['canCreateRole'])->toBeTrue()->and($data['canUpdateRole'])->toBeFalse()
        ->and($data['canDeleteRole'])->toBeFalse()->and($data['canExportRoles'])->toBeFalse()
        ->and($data['isSuperAdmin'])->toBeFalse();
});

it('denies missing view permission and grants from another campus or inactive role',function () {
    ($this->grantRoles)(['create']);
    expect(fn () => (new RoleManagementController)->index(($this->roleRequest)()))->toThrow(HttpException::class);
    ($this->grantRoles)(['view','create']);
    DB::table('access_user_roles')->where('user_id',1)->update(['campus_id'=>2]);
    expect(RolePermissions::allows(User::find(1),'view'))->toBeFalse();
    DB::table('access_roles')->where('id',1)->update(['status'=>0,'is_global'=>true]);
    expect(RolePermissions::allows(User::find(1),'view'))->toBeFalse();
});

it('allows a delegated registrar to create roles but blocks unauthorized update and delete requests',function () {
    ($this->grantRoles)(['view','create']);
    (new RoleManagementController)->save(($this->roleRequest)(['name'=>'Registrar Assistant','code'=>'registrar-assistant','status'=>1], 'POST'));
    $role = Role::where('code','registrar-assistant')->sole();
    expect($role->name)->toBe('Registrar Assistant');
    expect(fn () => (new RoleManagementController)->save(($this->roleRequest)(['role_id'=>$role->id,'name'=>'Changed','code'=>$role->code,'status'=>1],'POST')))->toThrow(HttpException::class);
    expect(fn () => (new RoleManagementController)->delete(($this->roleRequest)([],'DELETE'),$role))->toThrow(HttpException::class);
    expect($role->fresh()->name)->toBe('Registrar Assistant');
});

it('honors manage compatibility while enforcing print export and super administrator protection',function () {
    ($this->grantRoles)(['view','manage']);
    $user = User::find(1);
    expect(RolePermissions::allows($user,'create'))->toBeTrue()->and(RolePermissions::allows($user,'update'))->toBeTrue()
        ->and(RolePermissions::allows($user,'print'))->toBeFalse()->and(RolePermissions::allows($user,'export'))->toBeFalse();
    expect(fn () => (new RoleManagementController)->print(($this->roleRequest)()))->toThrow(HttpException::class);
    expect(fn () => (new RoleManagementController)->exportExcel(($this->roleRequest)()))->toThrow(HttpException::class);
    $super = Role::create(['code'=>'super-admin','name'=>'Super Administrator','status'=>1]);
    expect(fn () => (new RoleManagementController)->save(($this->roleRequest)(['role_id'=>$super->id,'code'=>'renamed-super','name'=>'Changed','status'=>0],'POST')))->toThrow(HttpException::class);
    expect(fn () => (new RoleManagementController)->save(($this->roleRequest)(['code'=>'super-admin','name'=>'Impersonated Super','status'=>1],'POST')))->toThrow(HttpException::class);
    expect(fn () => (new RoleManagementController)->save(($this->roleRequest)(['code'=>'Super-Admin','name'=>'Impersonated Super','status'=>1],'POST')))->toThrow(HttpException::class);
    expect($super->fresh()->code)->toBe('super-admin');
    ($this->grantRoles)(['view','print','export']);
    expect(RolePermissions::allows(User::find(1),'print'))->toBeTrue()->and(RolePermissions::allows(User::find(1),'export'))->toBeTrue();
});
