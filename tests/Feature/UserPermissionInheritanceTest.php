<?php

use App\Http\Controllers\AccessManagementController;
use App\Models\Permission;
use App\Models\User;
use App\Support\UserPermissionState;
use App\Support\{PermissionHierarchy,ScholarshipPermissions};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../Fixtures/UserPermissionInheritance.php';

beforeEach(function () {
    $this->withoutMiddleware();
    $this->ids = createUserPermissionInheritanceFixture();
});

it('shows inherited role and department grants together with additional user grants', function () {
    $state = UserPermissionState::forUser(User::find(1), Permission::all());
    expect($state['permissions']->pluck('code'))->toContain('photo-editor.view','students.enrollment.profile','western-scholarship.tab-scoring','western-scholarship.scoring','dashboard.customize','dashboard.reset')
        ->and($state['sources'][$this->ids['photo-editor.view']])->toBe(['Role: Registrar Officer'])
        ->and($state['sources'][$this->ids['dashboard.customize']])->toBe(['Department: Academics'])
        ->and($state['direct_ids'])->toBe([$this->ids['dashboard.reset']]);
});

it('provides the combined grants for expanded users and the selected user', function () {
    $view = app(AccessManagementController::class)->index(Request::create('/settings/access','GET'));
    $data = $view->getData();
    expect($data['userPermissionStates'][1]['permissions']->pluck('code'))->toContain('photo-editor.view','western-scholarship.scoring')
        ->and($data['selectedUserPermissionState']['permissions']->pluck('code'))->toContain('dashboard.customize','dashboard.reset');
});

it('does not show grants from inactive roles and refreshes after role revocation', function () {
    DB::table('access_roles')->where('id',1)->update(['status'=>0]);
    expect(UserPermissionState::forUser(User::find(1), Permission::all())['permissions']->pluck('code'))->not->toContain('photo-editor.view');
    DB::table('access_roles')->where('id',1)->update(['status'=>1]);
    DB::table('access_role_permissions')->where('permission_id',$this->ids['photo-editor.view'])->delete();
    expect(UserPermissionState::forUser(User::find(1), Permission::all())['permissions']->pluck('code'))->not->toContain('photo-editor.view');
});

it('saves user-only actions under inherited main menus without copying role or department grants', function () {
    $this->post(route('access-management.staff.save'), ['user_id'=>1,'permissions'=>[$this->ids['dashboard.reset'],$this->ids['students.enrollment.update'],$this->ids['western-scholarship.review-scores'],$this->ids['hrm-staff-data.import']]])->assertRedirect();
    expect(DB::table('access_user_permission_overrides')->pluck('permission_id')->sort()->values()->all())->toBe(collect([
        $this->ids['dashboard.reset'],$this->ids['students.enrollment.update'],$this->ids['western-scholarship.review-scores'],$this->ids['hrm-staff-data.import'],
    ])->sort()->values()->all());
    expect(UserPermissionState::forUser(User::find(1), Permission::all())['permissions']->pluck('code'))->toContain('photo-editor.view');
    DB::table('access_role_permissions')->where('permission_id',$this->ids['photo-editor.view'])->delete();
    expect(UserPermissionState::forUser(User::find(1), Permission::all())['permissions']->pluck('code'))->not->toContain('photo-editor.view');
});

it('ignores newly submitted inherited grants and preserves preexisting duplicate user grants', function () {
    DB::table('access_user_permission_overrides')->insert(['user_id'=>1,'permission_id'=>$this->ids['photo-editor.view'],'allowed'=>true]);
    $this->post(route('access-management.staff.save'), ['user_id'=>1,'permissions'=>[$this->ids['photo-editor.view'],$this->ids['students.view'],$this->ids['dashboard.customize']]])->assertRedirect();
    expect(DB::table('access_user_permission_overrides')->pluck('permission_id')->all())->toBe([$this->ids['photo-editor.view']]);
});

it('rejects additional actions whose required tab or main menu is not granted', function () {
    $this->post(route('access-management.staff.save'), ['user_id'=>1,'permissions'=>[$this->ids['western-scholarship.publish-results'],$this->ids['summer-school.create']]])->assertRedirect();
    expect(DB::table('access_user_permission_overrides')->count())->toBe(0);
});

it('derives Full Access from inherited and direct permissions without duplicating inherited grants', function () {
    DB::table('access_role_permissions')->delete();
    foreach (Permission::all() as $permission) DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>$permission->id]);
    $state = UserPermissionState::forUser(User::find(1), Permission::all());
    expect($state['full_access'])->toBeTrue()->and($state['inherited_full_access'])->toBeTrue();
    $this->post(route('access-management.staff.save'), ['user_id'=>1,'permissions'=>Permission::pluck('id')->all()])->assertRedirect();
    expect(DB::table('access_user_permission_overrides')->pluck('permission_id')->all())->toBe([$this->ids['dashboard.reset']]);
});

it('includes inherited permissions and their sources in the user permission report', function () {
    $report = (new ReflectionMethod(AccessManagementController::class,'permissionByUserReport'))->invoke(app(AccessManagementController::class));
    $photo = collect($report['rows'])->first(fn ($row) => $row[5]==='photo-editor.view');
    expect($photo)->not->toBeNull()->and($photo[7])->toContain('Role: Registrar Officer');
});

it('lists and saves every Answer Sheets action through role department and user permission forms',function($source){
    DB::table('tb_school_info')->insert(['id'=>1,'campus_name_en'=>'BCH','status'=>1]);
    DB::table('users')->where('id',1)->update(['is_global'=>false,'active_campus_id'=>1]);
    DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>1]);
    DB::table('access_user_roles')->update(['campus_id'=>1]);
    foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table)DB::table($table)->delete();
    $actions=ScholarshipPermissions::tabGroups()['answer-sheets']['actions'];
    $codes=['students.view','western-scholarship.view','western-scholarship.tab-answer-sheets',...array_map(fn($a)=>'western-scholarship.'.$a,$actions)];
    $ids=array_map(fn($code)=>$this->ids[$code],$codes);
    [$route,$payload]=match($source){
        'role'=>['access-management.roles.save',['role_id'=>1,'department_id'=>1]],
        'department'=>['access-management.departments.permissions.save',['department_id'=>1]],
        'user'=>['access-management.staff.save',['user_id'=>1,'campuses'=>[1]]],
    };
    $this->post(route($route),$payload+['permissions'=>$ids])->assertRedirect();
    $user=User::find(1);$catalog=Permission::all();$state=UserPermissionState::forUser($user,$catalog);
    expect($state['permissions']->pluck('code')->intersect($codes))->toHaveCount(count($codes))
        ->and(ScholarshipPermissions::allows($user,'answer-unlink',1))->toBeTrue();
    $tree=PermissionHierarchy::tree($catalog);$tab=$tree['students']['modules']['western-scholarship']['tabs']['answer-sheets'];
    expect($tab['permission']->code)->toBe('western-scholarship.tab-answer-sheets')
        ->and($tab['actions']->pluck('action')->all())->toBe($actions);
    $html=view('partials.permission-tree',['permissionHierarchy'=>$tree,'permissionPrefix'=>'test-'.$source,
        'assignedPermissions'=>$state['permissions'],'userPermissionState'=>$state])->render();
    $dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
    foreach(['tab-answer-sheets',...$actions] as $action){
        $toggle=$xpath->query('//*[@data-permission-code="western-scholarship.'.$action.'"][@data-status="1"]');
        expect($toggle->length)->toBe(1);
    }
    if($source!=='user')expect($state['inherited_ids'])->toContain($this->ids['western-scholarship.answer-unlink']);
    $table=match($source){'role'=>'access_role_permissions','department'=>'access_department_permissions','user'=>'access_user_permission_overrides'};
    DB::table($table)->where('permission_id',$this->ids['western-scholarship.answer-unlink'])->delete();
    expect(ScholarshipPermissions::allows(User::find(1),'answer-unlink',1))->toBeFalse();
})->with(['role','department','user']);

it('requires the Answer Sheets tab to save its action permissions',function(){
    $actions=ScholarshipPermissions::tabGroups()['answer-sheets']['actions'];
    $parents=[$this->ids['students.view'],$this->ids['western-scholarship.view']];
    $ids=array_map(fn($a)=>$this->ids['western-scholarship.'.$a],$actions);
    expect(PermissionHierarchy::normalizeIds([...$parents,...$ids],Permission::all()))->toBe($parents);
    $tab=$this->ids['western-scholarship.tab-answer-sheets'];
    expect(PermissionHierarchy::normalizeIds([...$parents,$tab,...$ids],Permission::all()))->toHaveCount(count($ids)+3);
});

it('can grant and revoke Clear Test Scans through role department and user permissions',function($source){
    DB::table('tb_school_info')->insert(['id'=>1,'campus_name_en'=>'BCH','status'=>1]);
    DB::table('users')->where('id',1)->update(['is_global'=>true,'active_campus_id'=>1]);
    DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>1]);
    DB::table('access_user_roles')->update(['campus_id'=>1]);
    foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table)DB::table($table)->delete();
    $codes=['students.view','western-scholarship.view','western-scholarship.tab-answer-sheets','western-scholarship.answer-clear-scans'];
    $ids=array_map(fn($code)=>$this->ids[$code],$codes);
    [$route,$payload]=match($source){
        'role'=>['access-management.roles.save',['role_id'=>1,'department_id'=>1]],
        'department'=>['access-management.departments.permissions.save',['department_id'=>1]],
        'user'=>['access-management.staff.save',['user_id'=>1,'campuses'=>[1]]],
    };
    $this->post(route($route),$payload+['permissions'=>$ids])->assertRedirect();
    expect(ScholarshipPermissions::allows(User::find(1),'answer-clear-scans'))->toBeTrue()
        ->and(UserPermissionState::forUser(User::find(1),Permission::all())['permissions']->pluck('code'))->toContain('western-scholarship.answer-clear-scans');
    $this->post(route($route),$payload+['permissions'=>array_slice($ids,0,3)])->assertRedirect();
    expect(ScholarshipPermissions::allows(User::find(1),'answer-clear-scans'))->toBeFalse()
        ->and(UserPermissionState::forUser(User::find(1),Permission::all())['permissions']->pluck('code'))->not->toContain('western-scholarship.answer-clear-scans');
})->with(['role','department','user']);
