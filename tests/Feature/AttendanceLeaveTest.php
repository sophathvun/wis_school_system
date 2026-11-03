<?php

use App\Http\Controllers\{AttendanceLeaveController,DailyAttendanceController};
use App\Models\User;
use App\Services\AttendanceLeave;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema,Storage};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/G12NationalExam.php';

beforeEach(function(){
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-02 12:00:00','Asia/Bangkok'));
    createG12NationalSchema();Storage::fake('local');
    Schema::table('users',fn(Blueprint $t)=>$t->integer('position_id')->nullable());
    Schema::create('access_positions',function(Blueprint $t){$t->id();$t->string('name');});
    Schema::table('tb_student',fn(Blueprint $t)=>$t->string('full_name_kh')->nullable());
    Schema::create('tb_family_member',function(Blueprint $t){$t->id();$t->string('full_name_en')->nullable();$t->string('full_name_kh')->nullable();$t->string('phone')->nullable();$t->string('relationship_type');$t->softDeletes();});
    Schema::create('tb_student_family_member',function(Blueprint $t){$t->integer('student_id');$t->integer('family_member_id');$t->string('relationship_type');$t->boolean('is_primary_contact')->default(false);$t->timestamps();});
    Schema::create('tb_academic_year',function(Blueprint $t){$t->id();$t->string('academic_year');$t->softDeletes();});
    Schema::create('tb_grade',function(Blueprint $t){$t->id();$t->string('grade');$t->string('grade_short_name');$t->softDeletes();});
    Schema::create('tb_class',function(Blueprint $t){$t->id();$t->string('class_name');$t->integer('academic_year_id');$t->softDeletes();});
    Schema::create('tb_session',function(Blueprint $t){$t->id();$t->string('session_short_name');$t->softDeletes();});
    Schema::create('tb_group',function(Blueprint $t){$t->id();$t->string('group_name');$t->softDeletes();});
    Schema::create('tb_student_enrollment',function(Blueprint $t){$t->id();foreach(['student_id','academic_year_id','campus_id','class_id','grade_id','session_id'] as $column)$t->integer($column);$t->integer('group_id')->nullable();$t->string('enrollment_status');$t->date('enrolled_on')->nullable();$t->date('ended_on')->nullable();});
    foreach(['000002_create_student_attendance','000003_create_daily_student_attendance','000004_scope_daily_attendance_by_grade','000005_add_daily_attendance_author','000006_create_teaching_schedules','000007_create_schedule_periods','000008_add_daily_absent_periods','000009_scope_periods_to_study_groups','000010_configure_full_day_period_grades','000011_create_attendance_leave','000012_add_holiday_descriptions'] as $migration){
        (require database_path('migrations/2026_11_03_'.$migration.'.php'))->up();
    }
    foreach(['view','manage'] as $action)DB::table('access_permissions')->insert(['code'=>'attendance.'.$action,'module'=>'attendance','action'=>$action,'name'=>$action]);
    DB::table('tb_academic_year')->insert(['id'=>1,'academic_year'=>'2025-2026']);
    DB::table('tb_grade')->insert(['id'=>1,'grade'=>'Grade 4','grade_short_name'=>'4']);
    DB::table('tb_class')->insert(['id'=>1,'academic_year_id'=>1,'class_name'=>'A']);
    DB::table('tb_session')->insert(['id'=>1,'session_short_name'=>'III']);
    DB::table('tb_student_enrollment')->insert(['id'=>1,'student_id'=>1,'academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'grade_id'=>1,'session_id'=>1,'enrollment_status'=>'active']);
    $this->controller=app(AttendanceLeaveController::class);$this->service=app(AttendanceLeave::class);$this->admin=User::find(1);
    $this->input=['academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'grade_id'=>1,'student_id'=>1,'starts_on'=>'2026-11-02','ends_on'=>'2026-11-04','reason'=>'Approved family leave','approved_by'=>'School Director','mode'=>'full'];
});

it('previews and applies three days of approved leave idempotently when each date is due',function(){
    $preview=$this->controller->create(g12Request($this->admin,$this->input+['preview'=>true]),$this->service)->getData(true);
    expect($preview['dates'])->toBe(['2026-11-02','2026-11-03','2026-11-04'])->and(DB::table('attendance_leaves')->count())->toBe(0);
    $this->controller->create(g12Request($this->admin,$this->input),$this->service);
    expect(DB::table('attendance_daily_marks')->count())->toBe(1)->and(DB::table('attendance_daily_marks')->value('status'))->toBe('EX')
        ->and(DB::table('attendance_daily_marks')->value('missed_sessions'))->toBe(7);
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-03 08:00:00','Asia/Bangkok'));
    $this->service->applyDue();$this->service->applyDue();
    expect(DB::table('attendance_daily_marks')->count())->toBe(2)->and(DB::table('attendance_leave_days')->where('status','scheduled')->count())->toBe(1);
});

it('skips configured holidays and weekends and supports partial-day periods',function(){
    $scope=['academic_year_id'=>1,'campus_id'=>1];
    $this->controller->calendar(g12Request($this->admin,$scope+['version'=>0,'weekdays'=>[1,2,3,4,5],'holidays'=>['2026-11-03']],'/attendance/calendar','POST'),$this->service);
    $input=array_replace($this->input,['ends_on'=>'2026-11-09','mode'=>'partial','period_ids'=>[2]]);
    $preview=$this->controller->create(g12Request($this->admin,$input+['preview'=>true]),$this->service)->getData(true);
    expect($preview['skipped'])->toBe(['2026-11-03','2026-11-07','2026-11-08']);
    $this->controller->create(g12Request($this->admin,$input),$this->service);
    expect(DB::table('attendance_daily_marks')->value('missed_sessions'))->toBe(1);
    expect(fn()=>$this->controller->calendar(g12Request(User::find(2),$scope),$this->service))->toThrow(HttpException::class);
});

it('rejects existing attendance and overlapping leave without replacing any data',function(){
    app(DailyAttendanceController::class)->save(g12Request($this->admin,['academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'grade_id'=>1,'date'=>'2026-11-02','version'=>0,'marks'=>[['student_id'=>1,'status'=>'P','missed_sessions'=>0]]]));
    expect(fn()=>$this->controller->create(g12Request($this->admin,$this->input),$this->service))->toThrow(ValidationException::class);
    expect(DB::table('attendance_daily_marks')->value('status'))->toBe('P')->and(DB::table('attendance_leaves')->count())->toBe(0);
    $future=array_replace($this->input,['starts_on'=>'2026-11-03']);
    $this->controller->create(g12Request($this->admin,$future),$this->service);
    expect(fn()=>$this->controller->create(g12Request($this->admin,$future),$this->service))->toThrow(ValidationException::class);
});

it('shortens or cancels pending leave while retaining applied attendance and audit history',function(){
    $input=array_replace($this->input,['ends_on'=>'2026-11-05']);
    $id=$this->controller->create(g12Request($this->admin,$input),$this->service)->getData(true)['id'];
    $this->controller->shorten(g12Request($this->admin,['ends_on'=>'2026-11-03','version'=>1,'reason'=>'Returning early']),$id);
    expect(DB::table('attendance_leave_days')->where('status','cancelled')->count())->toBe(2);
    $this->controller->cancel(g12Request($this->admin,['version'=>2,'reason'=>'Leave withdrawn']),$id);
    expect(DB::table('attendance_leave_days')->where('status','scheduled')->count())->toBe(0)
        ->and(DB::table('attendance_daily_marks')->value('status'))->toBe('EX')
        ->and(DB::table('attendance_leave_events')->count())->toBe(3);
});

it('keeps attachments private and denies leave entry outside authorized campuses',function(){
    $request=g12Request($this->admin,$this->input,'/attendance/leaves','POST');
    $request->files->set('attachment',\Illuminate\Http\UploadedFile::fake()->create('permission.pdf',10,'application/pdf'));
    $id=$this->controller->create($request,$this->service)->getData(true)['id'];
    $path=DB::table('attendance_leaves')->where('id',$id)->value('attachment_path');Storage::disk('local')->assertExists($path);
    expect(fn()=>$this->controller->attachment(g12Request(User::find(2)),$id))->toThrow(HttpException::class);
    expect($this->controller->attachment(g12Request($this->admin),$id)->headers->get('Content-Disposition'))->toContain('attachment');
    expect(fn()=>$this->controller->create(g12Request(User::find(2),$this->input),$this->service))->toThrow(HttpException::class);
});

it('skips pending leave after a holiday is added or the student changes class',function(){
    $input=array_replace($this->input,['starts_on'=>'2026-11-03']);
    $this->controller->create(g12Request($this->admin,$input),$this->service);
    $this->controller->calendar(g12Request($this->admin,['academic_year_id'=>1,'campus_id'=>1,'version'=>0,'weekdays'=>[1,2,3,4,5],'holidays'=>['2026-11-03']],'/attendance/calendar','POST'),$this->service);
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-03 08:00:00','Asia/Bangkok'));$this->service->applyDue();
    expect(DB::table('attendance_leave_days')->where('attendance_date','2026-11-03')->value('status'))->toBe('skipped');
    DB::table('tb_student_enrollment')->where('id',1)->update(['class_id'=>2]);
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-04 08:00:00','Asia/Bangkok'));$this->service->applyDue();
    expect(DB::table('attendance_daily_marks')->count())->toBe(0)->and(DB::table('attendance_leave_days')->where('status','skipped')->count())->toBe(2);
});

it('applies a calendar to all managed campuses atomically and rejects stale versions',function(){
    $scope=['academic_year_id'=>1,'campus_id'=>'all'];
    $loaded=$this->controller->calendar(g12Request($this->admin,$scope),$this->service)->getData(true);
    expect($loaded['campus_count'])->toBe(2)->and($loaded['mixed'])->toBeFalse();
    $input=$scope+['version'=>0,'versions'=>$loaded['versions'],'weekdays'=>[1,2,3,4,5,6],'holidays'=>['2026-11-09']];
    $this->controller->calendar(g12Request($this->admin,$input,'/attendance/calendar','POST'),$this->service);
    expect(DB::table('attendance_calendars')->count())->toBe(2)
        ->and($this->service->calendar(1,2)['holidays'])->toBe(['2026-11-09']);
    $loaded=$this->controller->calendar(g12Request($this->admin,$scope),$this->service)->getData(true);
    DB::table('attendance_calendars')->where('campus_id',2)->update(['version'=>2,'holidays'=>'[]']);
    $input['version']=1;$input['versions']=$loaded['versions'];$input['holidays']=['2026-11-10'];
    expect(fn()=>$this->controller->calendar(g12Request($this->admin,$input,'/attendance/calendar','POST'),$this->service))->toThrow(HttpException::class);
    expect($this->service->calendar(1,1)['holidays'])->toBe(['2026-11-09']);
    expect($this->controller->calendar(g12Request($this->admin,$scope),$this->service)->getData(true)['mixed'])->toBeTrue();
});

it('limits all-campus calendar assignment to campuses the staff member manages',function(){
    $permissions=DB::table('access_permissions')->whereIn('code',['attendance.view','attendance.manage'])->pluck('id');
    foreach($permissions as $id)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>$id,'allowed'=>true]);
    $user=User::find(2);$scope=['academic_year_id'=>1,'campus_id'=>'all'];
    $loaded=$this->controller->calendar(g12Request($user,$scope),$this->service)->getData(true);
    expect($loaded['campus_count'])->toBe(1);
    $input=$scope+['version'=>0,'versions'=>$loaded['versions'],'weekdays'=>[1,2,3,4,5],'holidays'=>['2026-11-09']];
    $this->controller->calendar(g12Request($user,$input,'/attendance/calendar','POST'),$this->service);
    expect(DB::table('attendance_calendars')->pluck('campus_id')->all())->toBe([1]);
});

it('stores holiday descriptions across campuses and preserves them when older clients save dates',function(){
    $scope=['academic_year_id'=>1,'campus_id'=>'all'];
    $loaded=$this->controller->calendar(g12Request($this->admin,$scope),$this->service)->getData(true);
    $input=$scope+['version'=>0,'versions'=>$loaded['versions'],'weekdays'=>[1,2,3,4,5],'holidays'=>['2026-11-09'],
        'holiday_descriptions'=>['2026-11-09'=>'School holiday','2026-12-01'=>'Not a listed date']];
    $this->controller->calendar(g12Request($this->admin,$input,'/attendance/calendar','POST'),$this->service);
    expect($this->service->calendar(1,2)['holiday_descriptions'])->toBe(['2026-11-09'=>'School holiday']);
    $input=['academic_year_id'=>1,'campus_id'=>1,'version'=>1,'weekdays'=>[1,2,3,4,5],'holidays'=>['2026-11-09']];
    $this->controller->calendar(g12Request($this->admin,$input,'/attendance/calendar','POST'),$this->service);
    expect($this->service->calendar(1,1)['holiday_descriptions'])->toBe(['2026-11-09'=>'School holiday']);
    $input['version']=2;$input['holidays']=[];
    $this->controller->calendar(g12Request($this->admin,$input,'/attendance/calendar','POST'),$this->service);
    expect($this->service->calendar(1,1)['holiday_descriptions'])->toBe([]);
});
