<?php

use App\Http\Controllers\G12NationalExamController;
use App\Models\User;
use App\Support\G12NationalExamPermissions as Access;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../Fixtures/G12NationalExam.php';

beforeEach(fn()=>createG12NationalSchema());

it('shows the records submenu and page with view permissions only', function (bool $unscopedRole) {
    $user=g12ExamUser(['view','records'],'role');
    DB::table('users')->where('id',2)->update(['active_campus_id'=>null]);
    if($unscopedRole)DB::table('access_user_roles')->where('user_id',2)->update(['campus_id'=>null]);
    $user=$user->fresh();
    $request=g12Request($user,[], '/g12-national-exams/student-records');
    $route=app('router')->getRoutes()->match($request);$request->setRouteResolver(fn()=>$route);
    auth()->setUser($user);
    $html=view('layouts.partials.g12-national-exams-menu',['moeysMenuIcon'=>'/icon.png'])->render();
    expect($html)->toContain('Student Exam Records','aria-current="page"')->not->toContain('Assign Exam Center','Certificate Tracking');
    $data=app(G12NationalExamController::class)->index($request)->getData();
    expect($data['tab'])->toBe('records')->and($data['permissions']['records'])->toBeTrue()
        ->and($data['permissions']['create'])->toBeFalse()->and($data['permissions']['edit'])->toBeFalse();
    $meta=app(G12NationalExamController::class)->meta(g12Request($user,['tab'=>'records']))->getData(true);
    expect(array_column($meta['campuses'],'id'))->toBe([1,2])
        ->and($meta['create_campuses'])->toBe([])->and($meta['edit_campuses'])->toBe([]);
    expect(Access::allows($user,'edit',2))->toBeFalse();
})->with(['campus role'=>false,'role without campus pivot'=>true]);

it('still requires the main menu and does not extend unscoped grants to campus mutations', function () {
    $user=g12ExamUser(['records','create','edit'],'role');
    expect(Access::allows($user,'records'))->toBeFalse();
    $id=DB::table('access_permissions')->where('code','g12-national-exams.view')->value('id');
    DB::table('access_role_permissions')->insert(['role_id'=>2,'permission_id'=>$id]);
    DB::table('access_user_roles')->where('user_id',2)->update(['campus_id'=>null]);
    $user=$user->fresh();
    expect(Access::allows($user,'records'))->toBeTrue()
        ->and(Access::allows($user,'create',1))->toBeFalse()
        ->and(Access::allows($user,'edit',1))->toBeFalse()
        ->and(Access::allows($user,'create',2))->toBeFalse();
});
