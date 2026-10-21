<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function createUserPermissionInheritanceFixture(): array
{
    Schema::create('users', function (Blueprint $t) {
        $t->id(); $t->string('name'); $t->string('username'); $t->string('email')->nullable(); $t->integer('department_id')->nullable();
        $t->integer('active_campus_id')->nullable(); $t->boolean('is_global')->default(true); $t->integer('status')->default(1); $t->timestamps();
    });
    Schema::create('access_departments', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('code'); $t->integer('parent_id')->nullable(); $t->integer('status')->default(1); $t->timestamps(); });
    Schema::create('access_roles', function (Blueprint $t) { $t->id(); $t->string('code'); $t->string('name'); $t->integer('status')->default(1); $t->boolean('is_global')->default(false); $t->integer('department_id')->nullable(); $t->timestamps(); });
    Schema::create('access_permissions', function (Blueprint $t) { $t->id(); $t->string('code')->unique(); $t->string('module'); $t->string('action'); $t->string('name'); $t->timestamps(); });
    Schema::create('access_user_roles', function (Blueprint $t) { $t->integer('user_id'); $t->integer('role_id'); $t->integer('campus_id')->nullable(); });
    Schema::create('access_role_permissions', function (Blueprint $t) { $t->integer('role_id'); $t->integer('permission_id'); });
    Schema::create('access_department_permissions', function (Blueprint $t) { $t->integer('department_id'); $t->integer('permission_id'); });
    Schema::create('access_user_permission_overrides', function (Blueprint $t) { $t->integer('user_id'); $t->integer('permission_id'); $t->boolean('allowed'); $t->timestamps(); });
    Schema::create('access_user_campuses', function (Blueprint $t) { $t->integer('user_id'); $t->integer('campus_id'); $t->boolean('is_primary')->default(true); $t->timestamp('assigned_at')->nullable(); $t->timestamps(); });
    Schema::create('tb_school_info', function (Blueprint $t) { $t->id(); $t->string('campus_name_en'); $t->integer('status')->default(1); $t->softDeletes(); });
    DB::table('users')->insert(['id'=>1,'name'=>'Registrar Officer User','username'=>'registrar','department_id'=>1]);
    DB::table('access_departments')->insert(['id'=>1,'name'=>'Academics','code'=>'academics']);
    DB::table('access_roles')->insert(['id'=>1,'name'=>'Registrar Officer','code'=>'registrar-officer']);
    DB::table('access_user_roles')->insert(['user_id'=>1,'role_id'=>1,'campus_id'=>null]);
    $codes = ['photo-editor.view','dashboard.view','dashboard.customize','dashboard.reset','students.view','students.enrollment.view','students.enrollment.profile','students.enrollment.update',
        'summer-school.view','summer-school.create','hrm.view','hrm-settings.view','staff.view','staff.create','hrm-staff-data.view','hrm-staff-data.import',
        'student-skipping-grade.view','student-skipping-grade.request','student-skipping-grade.create'];
    foreach (\App\Support\ScholarshipPermissions::catalog() as $action=>$name) $codes[] = 'western-scholarship.'.$action;
    $ids = [];
    foreach ($codes as $code) {
        $module = substr($code, 0, strrpos($code,'.')); $action = substr($code,strrpos($code,'.')+1);
        $ids[$code] = DB::table('access_permissions')->insertGetId(['code'=>$code,'module'=>$module,'action'=>$action,'name'=>$code]);
    }
    $roleCodes = ['photo-editor.view','dashboard.view','students.view','students.enrollment.view','students.enrollment.profile','hrm.view','hrm-settings.view','staff.view','staff.create','hrm-staff-data.view',
        'western-scholarship.view','western-scholarship.tab-scoring','western-scholarship.scoring'];
    foreach ($roleCodes as $code) DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>$ids[$code]]);
    DB::table('access_department_permissions')->insert(['department_id'=>1,'permission_id'=>$ids['dashboard.customize']]);
    DB::table('access_user_permission_overrides')->insert(['user_id'=>1,'permission_id'=>$ids['dashboard.reset'],'allowed'=>true]);
    return $ids;
}
