<?php

// Real permission views with synthetic users, roles, departments and an isolated database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','session.driver'=>'array']);
\Illuminate\Support\Facades\DB::purge('sqlite');
require_once __DIR__.'/../Fixtures/UserPermissionInheritance.php';
$ids = createUserPermissionInheritanceFixture();
$mode = $argv[1] ?? 'user';
if ($mode === 'full') {
    \Illuminate\Support\Facades\DB::table('access_role_permissions')->delete();
    foreach ($ids as $id) \Illuminate\Support\Facades\DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>$id]);
}
$user = \App\Models\User::find(1);
$permissions = \App\Models\Permission::all();
$options = ['permissionHierarchy'=>\App\Support\PermissionHierarchy::tree($permissions),
    'permissionPrefix'=>'fixture', 'assignedPermissions'=>$user->roles()->first()->permissions];
if ($mode !== 'role') $options += [
    'userPermissionState'=>\App\Support\UserPermissionState::forUser($user,$permissions),
    'permissionUser'=>$user, 'showCampusAssignment'=>true,
];
echo '<h2>'.($mode==='role'?'Permissions by Role':'User Permissions: Registrar Officer').'</h2>';
echo '<form data-permission-form>'.view('partials.permission-tree',$options)->render().'</form>';
echo '<script>window.permissionFixtureIds='.json_encode($ids).';</script>';
