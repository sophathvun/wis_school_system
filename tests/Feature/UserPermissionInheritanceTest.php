<?php

use App\Http\Controllers\AccessManagementController;
use App\Models\Permission;
use App\Models\User;
use App\Support\UserPermissionState;
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
