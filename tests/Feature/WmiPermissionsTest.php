<?php

use App\Models\{User,Permission,WmiYear,WmiExamCenter,WmiSchool,WmiRegistration,AcademicYear,Grade,Student,StudentEnrollment};
use App\Support\{WmiPermissions,PermissionHierarchy};
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Database\Schema\Blueprint;

require_once __DIR__.'/../Fixtures/UserPermissionInheritance.php';

function grantWmiPermissions(array $codes): User
{
    $parents=WmiPermissions::parents();
    foreach($codes as $code){$codesToGrant=[];do{$codesToGrant[]=$code;$code=$parents[$code]??null;}while($code);
        foreach($codesToGrant as $item){$id=Permission::where('code',$item)->value('id');if(!DB::table('access_role_permissions')->where(['role_id'=>1,'permission_id'=>$id])->exists())DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>$id]);}
    }
    return User::findOrFail(1);
}

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\TrackUserPresence::class);$this->withoutVite();
    app('view')->getFinder()->prependLocation(base_path('tests/Fixtures/wmi-views'));
    createUserPermissionInheritanceFixture();WmiPermissions::registerMissing();
    DB::table('users')->where('id',1)->update(['is_global'=>false,'active_campus_id'=>1,'department_id'=>null]);
    DB::table('access_user_roles')->where('user_id',1)->update(['campus_id'=>1]);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'STD'],['id'=>2,'campus_name_en'=>'BKK']]);
    DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>1]);
    (require base_path('tests/Fixtures/wmi-enrollment-schema.php'))();
    (require database_path('migrations/2026_07_31_000001_create_location_tables.php'))->up();
    foreach(['000002_create_wmi_years','000003_create_wmi_exam_centers','000004_create_wmi_schools','000005_make_wmi_school_details_optional','000006_create_wmi_registrations'] as $migration)
        (require database_path('migrations/2026_11_18_'.$migration.'.php'))->up();
    $this->year=WmiYear::create(['year'=>2026]);
    $this->center=WmiExamCenter::create(['wmi_year_id'=>$this->year->id,'name'=>'Center','name_key'=>'center','location'=>'Phnom Penh']);
    $this->school=WmiSchool::create(['name_en'=>'Other School','name_key'=>'other','school_type'=>'Private']);
    $this->data=['wmi_year_id'=>$this->year->id,'wmi_exam_center_id'=>$this->center->id,'student_source'=>'other','wmi_school_id'=>$this->school->id,'student_name'=>'External Student','gender'=>'M','current_grade'=>'5','grade_contest'=>'5','registration_channel'=>'online'];
    $this->actingAs(User::findOrFail(1));
});

it('registers the complete WMI hierarchy for role department and user pages without assigning grants',function () {
    $before=DB::table('access_role_permissions')->get()->toArray();WmiPermissions::registerMissing();WmiPermissions::registerMissing();
    expect(DB::table('access_role_permissions')->get()->toArray())->toEqual($before);
    $permissions=Permission::all();$tree=PermissionHierarchy::tree($permissions);$wmi=$tree['wmi-management'];
    expect($wmi['modules']['wmi-management.preliminary-round']['tabs']['pre-registration']['actions']->pluck('code')->all())
        ->toContain('wmi-management.preliminary-round.pre-registration.create','wmi-management.preliminary-round.pre-registration.delete');
    foreach(WmiPermissions::catalog() as $code=>$label)expect(PermissionHierarchy::visibleIds($permissions)->all())->toContain($permissions->firstWhere('code',$code)->id);
    foreach(['role','department','user'] as $prefix){
        $html=view('partials.permission-tree',['permissionHierarchy'=>$tree,'permissionPrefix'=>$prefix,'assignedPermissions'=>collect()])->render();
        expect($html)->toContain('WMI MANAGEMENT','Pre Registration','wmi-management.final-round','Assign Rooms &amp; Seats','wmi-management.import-export','wmi-management.settings.schools.delete','wmi-management.import-export.import-ids');
    }
});

it('removes orphan tab functions when their submenu or tab permission is missing',function () {
    $permissions=Permission::all();$codes=['wmi-management.view','wmi-management.preliminary-round','wmi-management.preliminary-round.pre-registration.create'];
    $ids=$permissions->whereIn('code',$codes)->modelKeys();
    $normalized=PermissionHierarchy::normalizeIds($ids,$permissions);
    expect($normalized)->not->toContain($permissions->firstWhere('code','wmi-management.preliminary-round.pre-registration.create')->id);
    $all=$permissions->whereIn('code',array_keys(WmiPermissions::catalog()))->modelKeys();
    expect(PermissionHierarchy::normalizeIds($all,$permissions))->toHaveCount(count(WmiPermissions::catalog()));
});

it('denies WMI navigation and endpoints without WMI grants',function () {
    $this->get(route('wmi-management.index'))->assertForbidden();
    $this->postJson(route('wmi-management.registrations.store'),$this->data)->assertForbidden();
    $this->get(route('wmi-management.registrations.export'))->assertForbidden();
    expect(view('layouts.partials.wmi-menu',['wmiMenuIcon'=>'/images/wmi_logo.png'])->render())->not->toContain('WMI Management');
});

it('allows a campus role to register Western students from other campuses and view all candidates',function () {
    $this->actingAs(grantWmiPermissions(['wmi-management.preliminary-round.pre-registration.create','wmi-management.preliminary-round.candidates']));
    $year=AcademicYear::create(['academic_year'=>'2025-2026']);$grade=Grade::create(['grade'=>'Grade 5']);
    $student=Student::create(['student_id'=>'OTHER-CAMPUS-1','full_name_en'=>'BKK STUDENT','gender'=>'Male']);
    $enrollment=StudentEnrollment::create(['student_id'=>$student->id,'academic_year_id'=>$year->id,'campus_id'=>2,'grade_id'=>$grade->id]);
    $this->getJson(route('wmi-management.registrations.enrollments',['q'=>'OTHER-CAMPUS','academic_year_id'=>$year->id,'campus_id'=>2]))->assertOk()->assertJsonPath('data.0.enrollment_id',$enrollment->id);
    $this->postJson(route('wmi-management.registrations.store'),array_replace($this->data,['student_source'=>'western','enrollment_id'=>$enrollment->id,'registration_channel'=>'campus','registrar_campus_id'=>2]))->assertCreated();
    expect(WmiRegistration::sole()->campus_study)->toBe('BKK')->and(WmiRegistration::sole()->campus_registered)->toBe('BKK');
    $this->get(route('wmi-management.section',['section'=>'preliminary-round','tab'=>'candidates']))->assertOk()->assertSee('BKK STUDENT');
    $this->postJson(route('wmi-management.registrations.store'),$this->data)->assertCreated();
    $this->get(route('wmi-management.section',['section'=>'preliminary-round','tab'=>'candidates']))->assertOk()->assertSee('External Student')->assertSee('BKK STUDENT');
    $this->postJson(route('wmi-management.registrations.store'),array_replace($this->data,['room_no'=>1,'seat_no'=>1]))->assertForbidden();
    expect(StudentEnrollment::find($enrollment->id)->campus_id)->toBe(2);
});

it('separates view permissions from mutation import export and seating permissions',function () {
    $this->actingAs(grantWmiPermissions(['wmi-management.preliminary-round.pre-registration','wmi-management.import-export','wmi-management.settings.room-seats']));
    $this->get(route('wmi-management.section',['section'=>'preliminary-round','tab'=>'pre-registration']))->assertOk();
    $this->postJson(route('wmi-management.registrations.store'),$this->data)->assertForbidden();
    $this->getJson(route('wmi-management.registrations.enrollments',['q'=>'TEST','academic_year_id'=>1]))->assertForbidden();
    $this->postJson(route('wmi-management.registrations.import-ids'),[])->assertForbidden();
    $this->get(route('wmi-management.registrations.export'))->assertForbidden();
    $this->postJson(route('wmi-management.registrations.allocate'),['wmi_exam_center_id'=>$this->center->id,'capacity'=>25])->assertForbidden();
    $this->deleteJson(route('wmi-management.years.destroy',$this->year))->assertForbidden();
});

it('opens the first permitted settings tab and denies unassigned tabs',function () {
    $this->actingAs(grantWmiPermissions(['wmi-management.settings.room-seats.assign']));
    $this->get(route('wmi-management.index'))->assertRedirect(route('wmi-management.section','settings'));
    $this->get(route('wmi-management.section','settings'))->assertOk()->assertSee('wmiSeatModal')->assertDontSee('href="'.route('wmi-management.section',['section'=>'settings','tab'=>'academic-year']).'"',false);
    $this->get(route('wmi-management.section',['section'=>'settings','tab'=>'academic-year']))->assertForbidden();
    $this->postJson(route('wmi-management.registrations.allocate'),['wmi_exam_center_id'=>$this->center->id,'capacity'=>25])->assertOk();
});

it('honors department and user grants and ignores inactive role grants across campus changes',function () {
    $user=grantWmiPermissions(['wmi-management.preliminary-round.candidates']);
    DB::table('users')->where('id',1)->update(['active_campus_id'=>2]);
    expect(WmiPermissions::allows($user->fresh(),'wmi-management.preliminary-round.candidates'))->toBeTrue();
    DB::table('access_roles')->where('id',1)->update(['status'=>0]);
    expect(WmiPermissions::allows($user->fresh(),'wmi-management.preliminary-round.candidates'))->toBeFalse();
    foreach(['wmi-management.view','wmi-management.preliminary-round'] as $code)DB::table('access_department_permissions')->insert(['department_id'=>1,'permission_id'=>Permission::where('code',$code)->value('id')]);
    DB::table('users')->where('id',1)->update(['department_id'=>1]);
    DB::table('access_user_permission_overrides')->insert(['user_id'=>1,'permission_id'=>Permission::where('code','wmi-management.preliminary-round.candidates')->value('id'),'allowed'=>true]);
    expect(WmiPermissions::allows($user->fresh(),'wmi-management.preliminary-round.candidates'))->toBeTrue();
});
