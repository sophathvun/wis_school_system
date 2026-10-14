<?php

use App\Models\Permission;
use App\Support\PermissionHierarchy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('access_permissions', function (Blueprint $table) {
        $table->id(); $table->string('code')->unique(); $table->string('module');
        $table->string('action'); $table->string('name'); $table->timestamps();
    });
    Schema::create('access_roles', function (Blueprint $table) {
        $table->id(); $table->string('code');
    });
    foreach (['access_role_permissions' => 'role_id', 'access_department_permissions' => 'department_id', 'access_user_permission_overrides' => 'user_id'] as $name => $owner) {
        Schema::create($name, function (Blueprint $table) use ($owner) {
            $table->integer($owner); $table->integer('permission_id'); $table->timestamps();
            $table->unique([$owner, 'permission_id']);
        });
    }
    DB::table('access_roles')->insert([['id' => 1, 'code' => 'super-admin'], ['id' => 2, 'code' => 'registrar']]);
    $this->migration = require database_path('migrations/2026_10_14_000002_add_pr_request_menu_permission.php');
});

it('adds the PR Request permission without granting it to ordinary roles and removes its assignments on rollback', function () {
    $this->migration->up();
    $permission = Permission::where('code', 'pr-request.view')->firstOrFail();
    expect(DB::table('access_role_permissions')->where('permission_id', $permission->id)->pluck('role_id')->all())->toBe([1]);
    DB::table('access_role_permissions')->insert(['role_id' => 2, 'permission_id' => $permission->id]);
    $this->migration->up();
    expect(Permission::where('code', 'pr-request.view')->count())->toBe(1)
        ->and(DB::table('access_role_permissions')->where('permission_id', $permission->id)->count())->toBe(2);
    $this->migration->down();
    expect(Permission::where('code', 'pr-request.view')->exists())->toBeFalse()
        ->and(DB::table('access_role_permissions')->where('permission_id', $permission->id)->exists())->toBeFalse();
});

it('includes an independent PR Request toggle in the permission hierarchy and full access selection', function () {
    $this->migration->up();
    $permissions = Permission::all();
    $permission = $permissions->firstWhere('code', 'pr-request.view');
    $tree = PermissionHierarchy::tree($permissions);
    expect($tree['pr-request']['label'])->toBe('PR Request')
        ->and($tree['pr-request']['permission']->id)->toBe($permission->id)
        ->and(PermissionHierarchy::visibleIds($permissions)->all())->toContain($permission->id)
        ->and(PermissionHierarchy::normalizeIds([$permission->id], $permissions))->toBe([$permission->id]);
    $html = view('partials.permission-tree', [
        'permissionHierarchy' => $tree, 'permissionPrefix' => 'pr', 'assignedPermissions' => $permissions,
    ])->render();
    expect($html)->toContain('PR REQUEST', 'pr-main-pr-request');
});

it('shows the external PR Request link above Students only when its menu permission is allowed', function () {
    $url = 'https://purchase-request.westernuniversity.digital/';
    $render = fn (array $codes) => view('layouts.partials.sidebar', ['permissionCodes' => $codes])->render();
    expect($render(['students.view']))->not->toContain($url);
    $html = $render(['pr-request.view', 'students.view']);
    expect($html)->toContain('href="'.$url.'"', '>PR Request</span>')
        ->and(strpos($html, '>PR Request</span>'))->toBeLessThan(strpos($html, '>Students</span>'));
    expect($render(['pr-request.view']))->toContain($url);
});
