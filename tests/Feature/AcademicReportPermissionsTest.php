<?php

use App\Http\Controllers\ReportsController;
use App\Http\Middleware\EnsureAcademicReportPermission;
use App\Models\Permission;
use App\Models\User;
use App\Support\AcademicReportPermissions;
use App\Support\PermissionHierarchy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->id(); $t->string('name'); $t->integer('active_campus_id')->nullable(); $t->integer('department_id')->nullable(); $t->boolean('is_global')->default(false);
    });
    Schema::create('access_roles', function (Blueprint $t) {
        $t->id(); $t->string('code'); $t->string('name'); $t->integer('status')->default(1); $t->boolean('is_global')->default(false); $t->boolean('is_system')->default(false); $t->integer('department_id')->nullable(); $t->softDeletes(); $t->timestamps();
    });
    Schema::create('access_permissions', function (Blueprint $t) {
        $t->id(); $t->string('code')->unique(); $t->string('name'); $t->string('module'); $t->string('action'); $t->timestamps();
    });
    Schema::create('access_user_roles', function (Blueprint $t) {
        $t->integer('user_id'); $t->integer('role_id'); $t->integer('campus_id')->nullable(); $t->timestamps();
    });
    foreach (['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id','access_user_permission_overrides'=>'user_id'] as $table=>$owner) {
        Schema::create($table, function (Blueprint $t) use ($owner) {
            $t->integer($owner); $t->integer('permission_id'); $t->unique([$owner,'permission_id']); $t->timestamps();
            if ($owner==='user_id') $t->boolean('allowed');
        });
    }
    Schema::create('access_departments', function (Blueprint $t) {
        $t->id(); $t->string('name'); $t->integer('status')->default(1); $t->softDeletes();
    });
    Schema::create('tb_school_info', function (Blueprint $t) {
        $t->id(); $t->string('campus_name_en'); $t->integer('status')->default(1); $t->softDeletes();
    });
    Schema::create('access_user_campuses', function (Blueprint $t) {
        $t->integer('user_id'); $t->integer('campus_id'); $t->boolean('is_primary')->default(true); $t->timestamp('assigned_at')->nullable(); $t->timestamps();
    });
    DB::table('tb_school_info')->insert(['id'=>2,'campus_name_en'=>'BCH']);
    DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>2]);
    DB::table('users')->insert(['id'=>1,'name'=>'Registrar','active_campus_id'=>2]);
    DB::table('access_roles')->insert([
        ['id'=>1,'code'=>'registrar','name'=>'Registrar'],
        ['id'=>2,'code'=>'super-admin','name'=>'Super Administrator'],
    ]);
    DB::table('access_user_roles')->insert(['user_id'=>1,'role_id'=>1,'campus_id'=>2]);
    DB::table('access_permissions')->insert([
        ['code'=>'reports.view','module'=>'reports','action'=>'view','name'=>'View Reports'],
        ['code'=>'reports.export','module'=>'reports','action'=>'export','name'=>'Export Reports'],
        ['code'=>'reports.k3.edit-template','module'=>'reports.k3','action'=>'edit-template','name'=>'K3 Certificate: Edit Template'],
    ]);
    $this->migration=require database_path('migrations/2026_10_07_000002_add_academic_report_tab_permissions.php');
    $this->grant=function (array $codes, string $source='user') {
        foreach (DB::table('access_permissions')->whereIn('code',$codes)->pluck('id') as $id) {
            if ($source==='role') DB::table('access_role_permissions')->updateOrInsert(['role_id'=>1,'permission_id'=>$id],[]);
            elseif ($source==='department') DB::table('access_department_permissions')->updateOrInsert(['department_id'=>1,'permission_id'=>$id],[]);
            else DB::table('access_user_permission_overrides')->updateOrInsert(['user_id'=>1,'permission_id'=>$id],['allowed'=>true]);
        }
    };
    $this->checkRoute=function (string $url, string $method='GET') {
        $request=Request::create($url,$method);
        $route=app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn()=>$route);
        $request->setUserResolver(fn()=>User::findOrFail(1));
        expect($route->gatherMiddleware())->toContain(EnsureAcademicReportPermission::class);
        return (new EnsureAcademicReportPermission)->handle($request,fn()=>response('Allowed'));
    };
});

it('adds every tab to the permission hierarchy and nests certificate actions under their tab', function () {
    $this->migration->up();
    $permissions=Permission::all();
    $tree=PermissionHierarchy::tree($permissions);
    expect(array_keys($tree['reports']['modules']))->toBe(array_keys(AcademicReportPermissions::moduleLabels()))
        ->and($tree['reports']['modules_label'])->toBe('Academic Reports')
        ->and($tree['reports']['modules']['reports.k3']['actions']->pluck('code')->all())->toBe(['reports.k3.edit-template'])
        ->and(PermissionHierarchy::visibleIds($permissions))->toHaveCount(18);
    $html=view('partials.permission-tree',[
        'permissionHierarchy'=>$tree,'permissionPrefix'=>'reports','assignedPermissions'=>collect(),
    ])->render();
    foreach (AcademicReportPermissions::labels() as $label) expect($html)->toContain(strtoupper($label));
    expect($html)->toContain('Academic Reports','reports.k3.edit-template');
});

it('preserves legacy role department and user report grants without adding certificate edit rights', function () {
    foreach (['role','department','user'] as $source) ($this->grant)(['reports.view'],$source);
    $this->migration->up();
    $ids=DB::table('access_permissions')->whereIn('code',array_column(AcademicReportPermissions::catalog(),'code'))->pluck('id');
    foreach (['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id','access_user_permission_overrides'=>'user_id'] as $table=>$owner) {
        expect(DB::table($table)->where($owner,1)->whereIn('permission_id',$ids)->count())->toBe(15);
    }
    $editId=DB::table('access_permissions')->where('code','reports.k3.edit-template')->value('id');
    expect(DB::table('access_role_permissions')->where('role_id',1)->where('permission_id',$editId)->exists())->toBeFalse()
        ->and(DB::table('access_role_permissions')->where('role_id',2)->whereIn('permission_id',$ids)->count())->toBe(15);
    DB::table('access_user_permission_overrides')->where('user_id',1)->where('permission_id',$ids->first())->update(['allowed'=>false]);
    $this->migration->up();
    expect(DB::table('access_user_permission_overrides')->where('user_id',1)->count())->toBe(16)
        ->and(DB::table('access_user_permission_overrides')->where('user_id',1)->where('permission_id',$ids->first())->value('allowed'))->toBe(0);
});

it('does not grant tabs to users who did not have Reports access', function () {
    $this->migration->up();
    expect(AcademicReportPermissions::visibleTypes(User::findOrFail(1)))->toBe([])
        ->and(DB::table('access_role_permissions')->where('role_id',1)->count())->toBe(0)
        ->and(DB::table('access_user_permission_overrides')->count())->toBe(0);
});

it('requires the Reports parent and tab permissions when saving assignments', function () {
    $this->migration->up();
    $permissions=Permission::all();
    $codes=['reports.view','reports.k3.view','reports.k3.edit-template','reports.student-photo.view'];
    $ids=$permissions->whereIn('code',$codes)->modelKeys();
    expect(PermissionHierarchy::normalizeIds($ids,$permissions))->toHaveCount(4);
    $ids=$permissions->whereIn('code',['reports.view','reports.k3.edit-template'])->modelKeys();
    expect(PermissionHierarchy::normalizeIds($ids,$permissions))->toBe([$permissions->firstWhere('code','reports.view')->id]);
    $ids=$permissions->whereIn('code',['reports.k3.view','reports.k3.edit-template'])->modelKeys();
    expect(PermissionHierarchy::normalizeIds($ids,$permissions))->toBe([]);
});

it('limits visible tabs using user role or department grants and the active campus', function (string $source) {
    $this->migration->up();
    if ($source==='department') {
        DB::table('access_departments')->insert(['id'=>1,'name'=>'Registrar']);
        DB::table('users')->where('id',1)->update(['department_id'=>1]);
    }
    ($this->grant)(['reports.view','reports.student-photo.view'],$source);
    $user=User::findOrFail(1);
    expect(AcademicReportPermissions::visibleTypes($user))->toBe(['student-photo'=>'Print Stu. Photo']);
    $user->active_campus_id=3;
    expect(AcademicReportPermissions::visibleTypes($user))->toBe($source==='user'?['student-photo'=>'Print Stu. Photo']:[]);
})->with(['user','role','department']);

it('enforces every tab on direct preview print Excel and PDF routes', function () {
    $this->migration->up();
    ($this->grant)(['reports.view']);
    foreach (AcademicReportPermissions::REPORTS as $type=>$report) {
        $urls=['/reports?type='.$type,'/reports/'.$type,'/reports/'.$type.'/excel','/reports/'.$type.'/pdf'];
        foreach ($urls as $url) {
            try {
                ($this->checkRoute)($url);
                throw new RuntimeException('Denied report was allowed: '.$url);
            } catch (HttpException $exception) {
                expect($exception->getStatusCode())->toBe(403);
            }
        }
        ($this->grant)([$report['module'].'.view']);
        foreach ($urls as $url) expect(($this->checkRoute)($url)->getStatusCode())->toBe(200);
        DB::table('access_user_permission_overrides')->where('permission_id',Permission::where('code',$report['module'].'.view')->value('id'))->delete();
    }
});

it('protects certificate settings template routes and transcript assets with tab permissions', function () {
    $this->migration->up();
    ($this->grant)(['reports.view','reports.k3.edit-template']);
    foreach (['k3','g9','g12'] as $level) {
        foreach (['settings','template'] as $action) {
            try { ($this->checkRoute)('/reports/'.$level.'-certificate-wis/'.$action,'POST'); throw new RuntimeException('Denied certificate was allowed'); }
            catch (HttpException $exception) { expect($exception->getStatusCode())->toBe(403); }
        }
    }
    try { ($this->checkRoute)('/reports/transcript-templates/primary/page-1.jpg'); throw new RuntimeException('Denied transcript was allowed'); }
    catch (HttpException $exception) { expect($exception->getStatusCode())->toBe(403); }
    ($this->grant)(['reports.k3.view','reports.moeys-sikkhakarik-book.view']);
    expect(($this->checkRoute)('/reports/k3-certificate-wis/template','POST')->getStatusCode())->toBe(200)
        ->and(($this->checkRoute)('/reports/transcript-templates/primary/page-1.jpg')->getStatusCode())->toBe(200);
});

it('allows super administrators every tab and rejects unauthorized explicit report selection', function () {
    $this->migration->up();
    DB::table('access_user_roles')->where('user_id',1)->update(['role_id'=>2]);
    expect(AcademicReportPermissions::visibleTypes(User::findOrFail(1)))->toBe(AcademicReportPermissions::labels());
    DB::table('access_user_roles')->where('user_id',1)->update(['role_id'=>1]);
    ($this->grant)(['reports.view','reports.student-photo.view']);
    $request=Request::create('/reports','GET',['type'=>'student-list']);
    $request->setUserResolver(fn()=>User::findOrFail(1));
    try { (new ReportsController)->index($request); throw new RuntimeException('Denied report selected'); }
    catch (HttpException $exception) { expect($exception->getStatusCode())->toBe(403); }
    expect(($this->checkRoute)('/reports')->getStatusCode())->toBe(200);
});

it('opens the first permitted report when the default Student List tab is not allowed', function () {
    Schema::create('tb_academic_year', function (Blueprint $t) {
        $t->id(); $t->string('academic_year'); $t->string('period_type'); $t->integer('parent_academic_year_id')->nullable(); $t->softDeletes();
    });
    Schema::create('tb_grade', function (Blueprint $t) {
        $t->id(); $t->string('grade'); $t->integer('grade_order'); $t->integer('status')->default(1); $t->softDeletes();
    });
    $this->migration->up();
    ($this->grant)(['reports.view','reports.student-photo.view']);
    $request=Request::create('/reports');
    $request->setUserResolver(fn()=>User::findOrFail(1));
    $data=(new ReportsController)->index($request)->getData();
    expect($data['type'])->toBe('student-photo')
        ->and($data['reportTypes'])->toBe(['student-photo'=>'Print Stu. Photo'])
        ->and($data['photoStudents'])->toBeEmpty();
});

it('keeps selected report tabs when refreshing the permission catalog', function () {
    $this->migration->up();
    ($this->grant)(['reports.view','reports.student-photo.view'],'role');
    (new \Database\Seeders\AccessFoundationSeeder)->run();
    $tabCodes=array_column(AcademicReportPermissions::catalog(),'code');
    expect(DB::table('access_role_permissions')->join('access_permissions','access_permissions.id','=','permission_id')
        ->where('role_id',1)->whereIn('code',$tabCodes)->pluck('code')->all())->toBe(['reports.student-photo.view']);
    expect(DB::table('access_role_permissions')->join('access_permissions','access_permissions.id','=','permission_id')
        ->where('role_id',2)->whereIn('code',$tabCodes)->count())->toBe(15);
});
