<?php

use App\Http\Controllers\AttendanceController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/G12NationalExam.php';
beforeEach(function(){
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-02 12:00:00','Asia/Bangkok'));
    createG12NationalSchema();
    Schema::table('users',fn(Blueprint $t)=>$t->unsignedBigInteger('position_id')->nullable());
    Schema::create('access_positions',function(Blueprint $t){$t->id();$t->string('name');});
    Schema::create('tb_family_member',function(Blueprint $t){$t->id();$t->string('full_name_en')->nullable();$t->string('full_name_kh')->nullable();$t->string('phone')->nullable();$t->string('relationship_type');$t->softDeletes();});
    Schema::create('tb_student_family_member',function(Blueprint $t){$t->integer('student_id');$t->integer('family_member_id');$t->string('relationship_type');$t->boolean('is_primary_contact')->default(false);$t->timestamps();});
    Schema::create('tb_session',function(Blueprint $t){$t->id();$t->string('session_short_name');$t->softDeletes();});
    Schema::create('tb_group',function(Blueprint $t){$t->id();$t->string('group_name');$t->softDeletes();});
    DB::table('tb_session')->insert(['id'=>1,'session_short_name'=>'III']);
    Schema::create('tb_academic_year',function(Blueprint $t){$t->id();$t->string('academic_year');$t->softDeletes();});
    Schema::create('tb_grade',function(Blueprint $t){$t->id();$t->string('grade');$t->string('grade_short_name')->nullable();$t->softDeletes();});
    Schema::create('tb_class',function(Blueprint $t){$t->id();$t->string('class_name');$t->integer('academic_year_id');$t->integer('grade_id')->nullable();$t->softDeletes();});
    Schema::table('tb_student',function(Blueprint $t){$t->string('full_name_kh')->nullable();});
    Schema::create('tb_student_enrollment',function(Blueprint $t){$t->id();$t->integer('student_id');$t->integer('campus_id');$t->integer('academic_year_id');$t->integer('class_id');$t->integer('grade_id')->nullable();$t->integer('session_id')->nullable()->default(1);$t->integer('group_id')->nullable();$t->string('enrollment_status');$t->date('enrolled_on')->nullable();$t->date('ended_on')->nullable();});
    (require database_path('migrations/2026_11_03_000002_create_student_attendance.php'))->up();
    (require database_path('migrations/2026_11_03_000003_create_daily_student_attendance.php'))->up();
    (require database_path('migrations/2026_11_03_000004_scope_daily_attendance_by_grade.php'))->up();
    (require database_path('migrations/2026_11_03_000005_add_daily_attendance_author.php'))->up();
    (require database_path('migrations/2026_11_03_000006_create_teaching_schedules.php'))->up();
    (require database_path('migrations/2026_11_03_000007_create_schedule_periods.php'))->up();
    (require database_path('migrations/2026_11_03_000008_add_daily_absent_periods.php'))->up();
    (require database_path('migrations/2026_11_03_000009_scope_periods_to_study_groups.php'))->up();
    (require database_path('migrations/2026_11_03_000010_configure_full_day_period_grades.php'))->up();
    (require database_path('migrations/2026_11_03_000011_create_attendance_leave.php'))->up();
    (require database_path('migrations/2026_11_03_000012_add_holiday_descriptions.php'))->up();
    DB::table('access_permissions')->insert(['code'=>'schedules.view','module'=>'schedules','action'=>'view','name'=>'Schedules']);
    foreach(['view','manage'] as $action)DB::table('access_permissions')->insert(['code'=>'attendance.'.$action,'module'=>'attendance','action'=>$action,'name'=>$action]);
    DB::table('tb_academic_year')->insert(['id'=>1,'academic_year'=>'2025-2026']);
    DB::table('tb_class')->insert(['id'=>1,'academic_year_id'=>1,'class_name'=>'12A']);
    DB::table('tb_student_enrollment')->insert(['id'=>1,'student_id'=>1,'campus_id'=>1,'academic_year_id'=>1,'class_id'=>1,'enrollment_status'=>'active','enrolled_on'=>'2025-01-01']);
    $ids=DB::table('access_permissions')->whereIn('code',['attendance.view','attendance.take'])->pluck('id');
    foreach($ids as $id)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>$id,'allowed'=>true]);
    $this->assignment=DB::table('attendance_assignments')->insertGetId(['campus_id'=>1,'academic_year_id'=>1,'class_id'=>1,'teacher_id'=>2,'subject'=>'Mathematics','weekday'=>1,'starts_at'=>'08:00','ends_at'=>'09:00','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    $this->controller=app(AttendanceController::class);
});

function teachingScheduleInput(array $changes=[]): array {
    return array_replace(['campus_id'=>1,'academic_year_id'=>1,'class_id'=>1,'teacher_id'=>2,'subject'=>'Biology','weekday'=>1,'period_id'=>4,
        'starts_at'=>'10:00','ends_at'=>'11:00','effective_from'=>'2026-11-01','effective_until'=>'2027-06-30','version'=>0],$changes);
}

it('shares the default morning and afternoon periods and restricts their setup',function(){
    $controller=app(\App\Http\Controllers\SchedulePeriodController::class);
    $periods=$controller->listing(g12Request(User::find(1)))->getData(true)['periods'];
    expect($periods)->toHaveCount(8)
        ->and(collect($periods)->where('session','morning'))->toHaveCount(4)
        ->and(collect($periods)->where('session','afternoon'))->toHaveCount(4)
        ->and(substr($periods[0]['starts_at'],0,5))->toBe('08:00')
        ->and(substr($periods[7]['ends_at'],0,5))->toBe('16:50');
    expect(fn()=>$controller->listing(g12Request(User::find(2))))->toThrow(HttpException::class);
    expect(fn()=>$controller->save(g12Request(User::find(1),['version'=>0,'name'=>'Overlapping','session'=>'morning','starts_at'=>'08:20','ends_at'=>'08:40','display_order'=>1,'active'=>true,'applicable_groups'=>['M','III']])))->toThrow(ValidationException::class);
});

it('derives schedule times from shared periods and preserves published times when setup changes',function(){
    $controller=app(\App\Http\Controllers\TeachingScheduleController::class);$admin=User::find(1);
    $id=$controller->save(g12Request($admin,teachingScheduleInput(['starts_at'=>'01:00','ends_at'=>'02:00'])))->getData(true)['id'];
    expect(substr(DB::table('teaching_schedules')->where('id',$id)->value('starts_at'),0,5))->toBe('10:00');
    $controller->publish(g12Request($admin,['version'=>1]),$id);
    $draft=$controller->save(g12Request($admin,teachingScheduleInput(['weekday'=>2])))->getData(true)['id'];
    $setup=app(\App\Http\Controllers\SchedulePeriodController::class);
    $setup->save(g12Request($admin,['id'=>4,'version'=>1,'name'=>'Morning Period 4','session'=>'morning','starts_at'=>'10:00','ends_at'=>'10:45','display_order'=>4,'active'=>true,'applicable_groups'=>['M','III']]));
    expect(substr(DB::table('attendance_assignments')->where('schedule_id',$id)->value('ends_at'),0,5))->toBe('10:50');
    expect(fn()=>$controller->publish(g12Request($admin,['version'=>1]),$draft))->toThrow(HttpException::class);
    expect(fn()=>$setup->save(g12Request($admin,['id'=>4,'version'=>1,'name'=>'Stale','session'=>'morning','starts_at'=>'10:00','ends_at'=>'10:40','display_order'=>4,'active'=>true,'applicable_groups'=>['M','III']])))->toThrow(HttpException::class);
    DB::table('schedule_periods')->where('id',4)->update(['active'=>false]);
    expect(fn()=>$controller->save(g12Request($admin,teachingScheduleInput())))->toThrow(HttpException::class);
});

it('publishes scheduled teaching sessions automatically into attendance and hides drafts',function(){
    $controller=app(\App\Http\Controllers\TeachingScheduleController::class);
    $admin=User::find(1);$teacher=User::find(2);
    $permission=DB::table('access_permissions')->where('code','schedules.view')->value('id');
    DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>$permission,'allowed'=>true]);
    $id=$controller->save(g12Request($admin,teachingScheduleInput()))->getData(true)['id'];
    expect(DB::table('attendance_assignments')->where('schedule_id',$id)->exists())->toBeFalse();
    expect($controller->listing(g12Request($teacher,['academic_year_id'=>1]))->getData(true)['schedules'])->toBe([]);
    $controller->publish(g12Request($admin,['version'=>1]),$id);
    expect($controller->listing(g12Request($teacher,['academic_year_id'=>1]))->getData(true)['schedules'])->toHaveCount(1);
    $filters=['date'=>'2026-11-02','academic_year_id'=>1,'mode'=>'mine'];
    $sessions=$this->controller->list(g12Request($teacher,$filters))->getData(true)['sessions'];
    expect(collect($sessions)->where('subject','Biology'))->toHaveCount(1);
    $filters['date']='2026-10-26';
    expect(collect($this->controller->list(g12Request($teacher,$filters))->getData(true)['sessions'])->where('subject','Biology'))->toHaveCount(0);
    $assignment=DB::table('attendance_assignments')->where('schedule_id',$id)->value('id');
    expect(fn()=>$this->controller->show(g12Request($teacher,['date'=>'2026-10-26']),$assignment))->toThrow(HttpException::class);
    expect(fn()=>$controller->publish(g12Request($admin,['version'=>1]),$id))->toThrow(HttpException::class);
});

it('rejects overlapping publication and unauthorized schedule changes',function(){
    $controller=app(\App\Http\Controllers\TeachingScheduleController::class);
    expect(fn()=>$controller->save(g12Request(User::find(2),teachingScheduleInput())))->toThrow(HttpException::class);
    $id=$controller->save(g12Request(User::find(1),teachingScheduleInput(['period_id'=>2])))->getData(true)['id'];
    expect(fn()=>$controller->publish(g12Request(User::find(1),['version'=>1]),$id))->toThrow(ValidationException::class);
    expect(DB::table('teaching_schedules')->where('id',$id)->value('status'))->toBe('draft');
    expect(DB::table('attendance_assignments')->where('schedule_id',$id)->count())->toBe(0);
});

it('revises published schedules without changing historical attendance',function(){
    $controller=app(\App\Http\Controllers\TeachingScheduleController::class);$admin=User::find(1);
    $id=$controller->save(g12Request($admin,teachingScheduleInput()))->getData(true)['id'];
    $controller->publish(g12Request($admin,['version'=>1]),$id);
    $assignment=DB::table('attendance_assignments')->where('schedule_id',$id)->value('id');
    $this->controller->save(g12Request(User::find(2),['date'=>'2026-11-02','version'=>0,'submit'=>true,'marks'=>[['student_id'=>1,'status'=>'present','late_minutes'=>0]]]),$assignment);
    expect(fn()=>$controller->save(g12Request($admin,teachingScheduleInput(['id'=>$id,'version'=>2]))))->toThrow(HttpException::class);
    $revision=$controller->save(g12Request($admin,teachingScheduleInput(['replaces_id'=>$id,'effective_from'=>'2026-11-09','period_id'=>5])))->getData(true)['id'];
    $controller->publish(g12Request($admin,['version'=>1]),$revision);
    expect(DB::table('attendance_assignments')->where('id',$assignment)->value('effective_until'))->toBe('2026-11-08');
    expect($this->controller->show(g12Request(User::find(2),['date'=>'2026-11-02']),$assignment)->getData(true)['students'][0]['mark']['status'])->toBe('present');
    $filters=['date'=>'2026-11-09','academic_year_id'=>1,'mode'=>'mine'];
    $sessions=collect($this->controller->list(g12Request(User::find(2),$filters))->getData(true)['sessions'])->where('subject','Biology');
    expect($sessions)->toHaveCount(1)->and($sessions->first()['starts_at'])->toStartWith('11:00');
});

it('rejects a revision that would overwrite recorded attendance',function(){
    $controller=app(\App\Http\Controllers\TeachingScheduleController::class);$admin=User::find(1);
    $id=$controller->save(g12Request($admin,teachingScheduleInput()))->getData(true)['id'];
    $controller->publish(g12Request($admin,['version'=>1]),$id);
    $assignment=DB::table('attendance_assignments')->where('schedule_id',$id)->value('id');
    DB::table('attendance_sessions')->insert(['assignment_id'=>$assignment,'attendance_date'=>'2026-11-09','status'=>'draft','created_at'=>now(),'updated_at'=>now()]);
    $revision=$controller->save(g12Request($admin,teachingScheduleInput(['replaces_id'=>$id,'effective_from'=>'2026-11-09'])))->getData(true)['id'];
    expect(fn()=>$controller->publish(g12Request($admin,['version'=>1]),$revision))->toThrow(HttpException::class);
    expect(DB::table('attendance_assignments')->where('id',$assignment)->value('effective_until'))->toBe('2027-06-30');
    expect(DB::table('teaching_schedules')->where('id',$revision)->value('status'))->toBe('draft');
});

it('keeps grade-specific schedule rosters separate for shared class letters',function(){
    DB::table('tb_grade')->insert([['id'=>1,'grade'=>'Grade 1','grade_short_name'=>'1'],['id'=>2,'grade'=>'Grade 2','grade_short_name'=>'2']]);
    DB::table('tb_class')->where('id',1)->update(['class_name'=>'A']);
    DB::table('tb_student_enrollment')->where('student_id',1)->update(['grade_id'=>1]);
    DB::table('tb_student')->insert(['id'=>2,'student_id'=>'002','full_name_en'=>'GRADE TWO']);
    DB::table('tb_student_enrollment')->insert(['student_id'=>2,'campus_id'=>1,'academic_year_id'=>1,'class_id'=>1,'grade_id'=>2,'enrollment_status'=>'active']);
    $controller=app(\App\Http\Controllers\TeachingScheduleController::class);$admin=User::find(1);
    $id=$controller->save(g12Request($admin,teachingScheduleInput(['grade_id'=>1])))->getData(true)['id'];
    $controller->publish(g12Request($admin,['version'=>1]),$id);
    $assignment=DB::table('attendance_assignments')->where('schedule_id',$id)->value('id');
    $students=$this->controller->show(g12Request(User::find(2),['date'=>'2026-11-02']),$assignment)->getData(true)['students'];
    expect($students)->toHaveCount(1)->and($students[0]['student_id'])->toBe(1);
});

it('restricts teachers to their sessions and campus admins to accessible campuses',function(){
    $teacher=User::find(2);
    $meta=$this->controller->meta(g12Request($teacher,[]))->getData(true);
    expect($meta['campuses'])->toHaveCount(1)->and($meta['manage_campuses'])->toBe([]);
    $register=$this->controller->show(g12Request($teacher,['date'=>'2026-11-02']),$this->assignment)->getData(true);
    expect($register['students'])->toHaveCount(1)->and($register['students'][0]['mark'])->toBeNull();
    $sessions=$this->controller->list(g12Request($teacher,['date'=>'2026-11-02','academic_year_id'=>1,'mode'=>'mine']))->getData(true);
    expect($sessions['sessions'])->toHaveCount(1);
    $other=DB::table('attendance_assignments')->insertGetId(['campus_id'=>2,'academic_year_id'=>1,'class_id'=>1,'teacher_id'=>1,'subject'=>'English','weekday'=>1,'starts_at'=>'09:00','ends_at'=>'10:00','active'=>true]);
    expect(fn()=>$this->controller->show(g12Request($teacher,['date'=>'2026-11-02']),$other))->toThrow(\Illuminate\Database\RecordNotFoundException::class);
    expect($this->controller->list(g12Request($teacher,['date'=>'2026-11-02','academic_year_id'=>1,'mode'=>'campus']))->getData(true)['sessions'])->toBe([]);
});

it('displays grade and class together without duplicating an existing grade prefix',function(){
    DB::table('tb_grade')->insert(['id'=>1,'grade'=>'Grade 12','grade_short_name'=>'12']);
    DB::table('tb_class')->where('id',1)->update(['grade_id'=>1,'class_name'=>'A']);
    $request=g12Request(User::find(1),[]);
    expect($this->controller->meta($request)->getData(true)['classes'][0]['class_name'])->toBe('12A');
    DB::table('tb_class')->where('id',1)->update(['class_name'=>'12A']);
    expect($this->controller->meta($request)->getData(true)['classes'][0]['class_name'])->toBe('12A');
});

it('separates grades sharing the same letter class in the daily dropdown and register',function(){
    DB::table('tb_grade')->insert([['id'=>1,'grade'=>'Grade 1','grade_short_name'=>'1'],['id'=>2,'grade'=>'Grade 2','grade_short_name'=>'2']]);
    DB::table('tb_class')->where('id',1)->update(['class_name'=>'A','grade_id'=>null]);
    DB::table('tb_student_enrollment')->where('id',1)->update(['grade_id'=>1]);
    DB::table('tb_student')->insert(['id'=>2,'student_id'=>'S2','full_name_en'=>'GRADE TWO']);
    DB::table('tb_student_enrollment')->insert(['student_id'=>2,'campus_id'=>1,'academic_year_id'=>1,'class_id'=>1,'grade_id'=>2,'enrollment_status'=>'active','enrolled_on'=>'2025-01-01']);
    $admin=User::find(1);$meta=$this->controller->meta(g12Request($admin,[]))->getData(true);
    expect(array_column($meta['class_scopes'],'label'))->toBe(['1A','2A']);
    $controller=app(\App\Http\Controllers\DailyAttendanceController::class);
    $scope=['academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'date'=>'2026-11-02'];
    expect(fn()=>$controller->show(g12Request($admin,$scope)))->toThrow(ValidationException::class);
    expect($controller->show(g12Request($admin,$scope+['grade_id'=>1]))->getData(true)['students'])->toHaveCount(1)
        ->and($controller->show(g12Request($admin,$scope+['grade_id'=>2]))->getData(true)['students'][0]['name'])->toBe('GRADE TWO');
});

it('saves drafts once locks submissions and requires a reason for admin corrections',function(){
    $teacher=User::find(2);$input=['date'=>'2026-11-02','version'=>0,'submit'=>false,'marks'=>[['student_id'=>1,'status'=>'late','late_minutes'=>12,'note'=>'Bus delay']]];
    $this->controller->save(g12Request($teacher,$input),$this->assignment);
    expect(DB::table('attendance_sessions')->count())->toBe(1)->and(DB::table('attendance_marks')->value('late_minutes'))->toBe(12);
    expect(fn()=>$this->controller->save(g12Request($teacher,$input),$this->assignment))->toThrow(HttpException::class);
    $input['version']=1;$input['submit']=true;
    $this->controller->save(g12Request($teacher,$input),$this->assignment);
    expect(DB::table('attendance_sessions')->value('status'))->toBe('submitted');
    $input['version']=2;
    expect(fn()=>$this->controller->save(g12Request($teacher,$input),$this->assignment))->toThrow(HttpException::class);
    expect(fn()=>$this->controller->reopen(g12Request($teacher,['date'=>'2026-11-02','reason'=>'Correct status']),$this->assignment))->toThrow(HttpException::class);
    $this->controller->reopen(g12Request(User::find(1),['date'=>'2026-11-02','reason'=>'Parent confirmed medical leave']),$this->assignment);
    expect(DB::table('attendance_sessions')->value('status'))->toBe('draft')->and(DB::table('attendance_events')->count())->toBe(3);
});

it('rejects outsiders unmarked submissions and overlapping assignments',function(){
    $input=['date'=>'2026-11-02','version'=>0,'submit'=>true,'marks'=>[['student_id'=>1,'status'=>null,'late_minutes'=>0]]];
    expect(fn()=>$this->controller->save(g12Request(User::find(2),$input),$this->assignment))->toThrow(ValidationException::class);
    $input['marks'][0]['student_id']=999;$input['marks'][0]['status']='present';
    expect(fn()=>$this->controller->save(g12Request(User::find(2),$input),$this->assignment))->toThrow(ValidationException::class);
    $assignment=['campus_id'=>1,'academic_year_id'=>1,'class_id'=>1,'teacher_id'=>2,'subject'=>'Science','weekday'=>1,'starts_at'=>'08:30','ends_at'=>'09:30'];
    expect(fn()=>$this->controller->assign(g12Request(User::find(1),$assignment)))->toThrow(ValidationException::class);
});

it('reports session counts without leaking another campus and preserves history on deactivation',function(){
    $teacher=User::find(2);
    $this->controller->save(g12Request($teacher,['date'=>'2026-11-02','version'=>0,'submit'=>true,'marks'=>[['student_id'=>1,'status'=>'absent','late_minutes'=>0,'note'=>'Parent contacted']]]),$this->assignment);
    $filters=['academic_year_id'=>1,'from'=>'2026-11-01','to'=>'2026-11-03'];
    $report=$this->controller->history(g12Request($teacher,$filters))->getData(true);
    expect($report['history'])->toHaveCount(1)->and((int)$report['history'][0]['absent'])->toBe(1);
    $this->controller->assignmentStatus(g12Request(User::find(1),['active'=>false]),$this->assignment);
    expect($this->controller->list(g12Request($teacher,['academic_year_id'=>1,'date'=>'2026-11-02','mode'=>'mine']))->getData(true)['sessions'])->toBe([])
        ->and($this->controller->history(g12Request($teacher,$filters))->getData(true)['history'])->toHaveCount(1);
    $csv=$this->controller->history(g12Request($teacher,$filters+['format'=>'csv']));
    expect($csv->headers->get('Content-Type'))->toContain('text/csv');
});

it('records daily admin attendance without teacher sessions and counts multiple missed sessions once',function(){
    DB::table('attendance_assignments')->delete();
    $controller=app(\App\Http\Controllers\DailyAttendanceController::class);
    $scope=['academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'date'=>'2026-11-02'];
    $admin=User::find(1);
    expect($controller->show(g12Request($admin,$scope))->getData(true)['students'])->toHaveCount(1);
    $input=$scope+['version'=>0,'marks'=>[['student_id'=>1,'status'=>'EX','missed_sessions'=>3,'remarks'=>'Medical appointment; missed three sessions']]];
    $controller->save(g12Request($admin,$input));
    expect(DB::table('attendance_daily_marks')->count())->toBe(1)
        ->and(DB::table('attendance_daily_marks')->value('missed_sessions'))->toBe(3);
    expect(fn()=>$controller->save(g12Request($admin,$input)))->toThrow(HttpException::class);
    $input['version']=1;$input['marks'][0]['status']='P';
    $controller->save(g12Request($admin,$input));
    expect(DB::table('attendance_daily_marks')->count())->toBe(1)
        ->and(DB::table('attendance_daily_marks')->value('missed_sessions'))->toBe(0)
        ->and(DB::table('attendance_daily_events')->count())->toBe(2);
    DB::table('tb_class')->insert(['id'=>2,'class_name'=>'12B','academic_year_id'=>1]);
    DB::table('tb_student_enrollment')->insert(['student_id'=>1,'campus_id'=>1,'academic_year_id'=>1,'class_id'=>2,'enrollment_status'=>'active','enrolled_on'=>'2025-01-01']);
    $other=$scope;$other['class_id']=2;
    expect($controller->show(g12Request($admin,$other))->getData(true)['students'][0]['other_class'])->toBeTrue();
    $input['class_id']=2;$input['version']=0;
    expect(fn()=>$controller->save(g12Request($admin,$input)))->toThrow(ValidationException::class);
});

it('autosaves marked students without marking or overwriting the remaining students',function(){
    DB::table('tb_student')->insert(['id'=>2,'full_name_en'=>'SECOND STUDENT','student_id'=>'SECOND']);
    DB::table('tb_student_enrollment')->insert(['student_id'=>2,'campus_id'=>1,'academic_year_id'=>1,'class_id'=>1,'enrollment_status'=>'active','enrolled_on'=>'2025-01-01']);
    $controller=app(\App\Http\Controllers\DailyAttendanceController::class);
    $scope=['academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'date'=>'2026-11-02'];
    $input=$scope+['version'=>0,'partial'=>true,'marks'=>[['student_id'=>1,'status'=>'P','missed_sessions'=>0]]];
    $result=$controller->save(g12Request(User::find(1),$input))->getData(true);
    expect($result['version'])->toBe(1)->and(DB::table('attendance_daily_marks')->count())->toBe(1);
    $input['version']=1;$input['marks']=[['student_id'=>2,'status'=>'EX','missed_sessions'=>2]];
    $controller->save(g12Request(User::find(1),$input));
    expect(DB::table('attendance_daily_marks')->count())->toBe(2)
        ->and(DB::table('attendance_daily_marks')->where('student_id',1)->value('status'))->toBe('P');
    expect(fn()=>$controller->save(g12Request(User::find(1),$input)))->toThrow(HttpException::class);
});

it('displays family contacts and preserves the original attendance author and date',function(){
    DB::table('access_positions')->insert(['id'=>1,'name'=>'Campus Administrator']);
    DB::table('users')->where('id',1)->update(['position_id'=>1]);
    DB::table('tb_student')->where('id',1)->update(['photo_path'=>'students/photo.webp']);
    foreach(['mother','father'] as $index=>$relationship){
        DB::table('tb_family_member')->insert(['id'=>$index+1,'full_name_en'=>ucfirst($relationship),'phone'=>'012345678','relationship_type'=>$relationship]);
        DB::table('tb_student_family_member')->insert(['student_id'=>1,'family_member_id'=>$index+1,'relationship_type'=>$relationship]);
    }
    $controller=app(\App\Http\Controllers\DailyAttendanceController::class);
    $scope=['academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'date'=>'2026-11-02'];
    $input=$scope+['version'=>0,'marks'=>[['student_id'=>1,'status'=>'P','missed_sessions'=>0]]];
    $controller->save(g12Request(User::find(1),$input));
    DB::table('attendance_daily_marks')->update(['created_at'=>'2026-11-02 08:00:00']);
    $input['version']=1;$input['marks'][0]['status']='L';
    $controller->save(g12Request(User::find(1),$input));
    $student=$controller->show(g12Request(User::find(1),$scope))->getData(true)['students'][0];
    expect($student['photo_url'])->toEndWith('/storage/students/photo.webp')
        ->and($student['mother']['name'])->toBe('Mother')
        ->and($student['father']['phone'])->toBe('012345678')
        ->and($student['added_by']['name'])->toBe('National Exam Admin')
        ->and($student['added_by']['position'])->toBe('Campus Administrator')
        ->and($student['added_by']['date'])->toBe('02-Nov-2026 08:00');
});

it('autosaves absent periods, counts them once per day and preserves their historical times',function(){
    DB::table('tb_grade')->insert(['id'=>1,'grade'=>'Grade 4','grade_short_name'=>'4']);
    DB::table('tb_student_enrollment')->where('student_id',1)->update(['grade_id'=>1]);
    $controller=app(\App\Http\Controllers\DailyAttendanceController::class);$admin=User::find(1);
    $scope=['academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'date'=>'2026-11-02'];
    $input=$scope+['version'=>0,'partial'=>true,'marks'=>[['student_id'=>1,'status'=>'EX','missed_sessions'=>99,'absent_period_ids'=>[2,3],'remarks'=>'Missed the first two periods']]];
    $controller->save(g12Request($admin,$input));
    $student=$controller->show(g12Request($admin,$scope))->getData(true)['students'][0];
    expect($student['mark']['missed_sessions'])->toBe(2)
        ->and($student['mark']['absent_periods'])->toHaveCount(2)
        ->and($student['mark']['absent_periods'][0]['starts_at'])->toBe('08:00')
        ->and(DB::table('attendance_daily_marks')->count())->toBe(1);
    DB::table('schedule_periods')->where('id',2)->update(['active'=>false,'starts_at'=>'08:10']);
    $input['version']=1;$controller->save(g12Request($admin,$input));
    $student=$controller->show(g12Request($admin,$scope))->getData(true)['students'][0];
    expect($student['mark']['absent_periods'][0]['starts_at'])->toBe('08:00');
    $input['version']=2;$input['marks'][0]['absent_period_ids']=[999];
    expect(fn()=>$controller->save(g12Request($admin,$input)))->toThrow(ValidationException::class);
    $input['marks'][0]['status']='P';$input['marks'][0]['absent_period_ids']=[];
    $controller->save(g12Request($admin,$input));
    $student=$controller->show(g12Request($admin,$scope))->getData(true)['students'][0];
    expect($student['mark']['missed_sessions'])->toBe(0)->and($student['mark']['absent_periods'])->toBe([]);
});

it('limits absent periods to each enrollment study group',function(){
    DB::table('tb_session')->insert([['id'=>2,'session_short_name'=>'M'],['id'=>3,'session_short_name'=>'A']]);
    $controller=app(\App\Http\Controllers\DailyAttendanceController::class);$admin=User::find(1);
    $scope=['academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'date'=>'2026-11-02'];
    foreach([2=>'M',3=>'A',1=>'III'] as $sessionId=>$group){
        DB::table('tb_student_enrollment')->where('student_id',1)->update(['session_id'=>$sessionId]);
        $student=$controller->show(g12Request($admin,$scope))->getData(true)['students'][0];
        expect($student['study_group'])->toBe($group);
        $allowed=DB::table('schedule_periods')->where('active',true)->whereNull('deleted_at')->get()->filter(fn($period)=>\App\Support\AttendanceStudyGroup::allows($period,$group));
        expect($allowed)->toHaveCount($group==='III'?7:4);
    }
    DB::table('tb_student_enrollment')->where('student_id',1)->update(['session_id'=>2]);
    $afternoon=DB::table('schedule_periods')->where('starts_at','16:00')->value('id');
    $input=$scope+['version'=>0,'partial'=>true,'marks'=>[['student_id'=>1,'status'=>'UX','missed_sessions'=>1,'absent_period_ids'=>[$afternoon]]]];
    expect(fn()=>$controller->save(g12Request($admin,$input)))->toThrow(ValidationException::class);
    expect(DB::table('attendance_daily_marks')->count())->toBe(0);
    DB::table('tb_student_enrollment')->where('student_id',1)->update(['session_id'=>null]);
    expect($controller->show(g12Request($admin,$scope))->getData(true)['students'][0]['study_group'])->toBeNull();
    expect(fn()=>$controller->save(g12Request($admin,$input)))->toThrow(ValidationException::class);
});

it('limits full-day nursery through grade three to six periods and retains seven for grades four through twelve',function(){
    $controller=app(\App\Http\Controllers\DailyAttendanceController::class);$admin=User::find(1);
    $scope=['academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'date'=>'2026-11-02'];
    foreach(['N','K1','K2','K3','1','2','3','4','12'] as $index=>$grade){
        DB::table('tb_grade')->insert(['id'=>$index+1,'grade'=>'Grade '.$grade,'grade_short_name'=>$grade]);
        DB::table('tb_student_enrollment')->where('student_id',1)->update(['grade_id'=>$index+1]);
        $student=$controller->show(g12Request($admin,$scope))->getData(true)['students'][0];
        expect($student['allowed_period_ids'])->toHaveCount(in_array($grade,['4','12'])?7:6);
        $eleven=DB::table('schedule_periods')->where('starts_at','11:00')->value('id');
        expect(in_array($eleven,$student['allowed_period_ids']))->toBe(in_array($grade,['4','12']));
    }
    DB::table('tb_student_enrollment')->where('student_id',1)->update(['grade_id'=>1]);
    $input=$scope+['version'=>0,'marks'=>[['student_id'=>1,'status'=>'UX','missed_sessions'=>1,'absent_period_ids'=>[$eleven]]]];
    expect(fn()=>$controller->save(g12Request($admin,$input)))->toThrow(ValidationException::class);
});

it('requires explicit edit mode for past dates and rejects future dates even in edit mode',function(){
    $controller=app(\App\Http\Controllers\DailyAttendanceController::class);$admin=User::find(1);
    $input=['academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'date'=>'2026-11-01','version'=>0,'partial'=>true,'marks'=>[['student_id'=>1,'status'=>'P','missed_sessions'=>0]]];
    expect(fn()=>$controller->save(g12Request($admin,$input)))->toThrow(HttpException::class);
    $input['edit_past']=true;$controller->save(g12Request($admin,$input));
    expect(DB::table('attendance_daily_marks')->value('status'))->toBe('P');
    $input['version']=1;$input['marks'][0]['status']='EX';unset($input['edit_past']);
    expect(fn()=>$controller->save(g12Request($admin,$input)))->toThrow(HttpException::class);
    $input['edit_past']=true;$controller->save(g12Request($admin,$input));
    expect(DB::table('attendance_daily_marks')->value('status'))->toBe('EX');
    $input['date']='2026-11-03';$input['version']=0;
    expect(fn()=>$controller->save(g12Request($admin,$input)))->toThrow(HttpException::class);
});

it('applies present only to blank students while preserving every recorded status',function(){
    for($id=2;$id<=5;$id++){
        DB::table('tb_student')->insert(['id'=>$id,'student_id'=>'S'.$id,'full_name_en'=>'Student '.$id]);
        DB::table('tb_student_enrollment')->insert(['student_id'=>$id,'campus_id'=>1,'academic_year_id'=>1,'class_id'=>1,'enrollment_status'=>'active']);
    }
    $controller=app(\App\Http\Controllers\DailyAttendanceController::class);$admin=User::find(1);
    $statuses=['P','EX','UX','L'];
    $input=['academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'date'=>'2026-11-02','version'=>0,'partial'=>true,
        'marks'=>array_map(fn($index)=>['student_id'=>$index+1,'status'=>$statuses[$index],'missed_sessions'=>0,'remarks'=>'Original note'],range(0,3))];
    $controller->save(g12Request($admin,$input));
    $input['version']=1;$input['only_blank']=true;
    $input['marks']=array_map(fn($id)=>['student_id'=>$id,'status'=>'P','missed_sessions'=>0],range(1,5));
    $controller->save(g12Request($admin,$input));
    expect(DB::table('attendance_daily_marks')->orderBy('student_id')->pluck('status')->all())->toBe(['P','EX','UX','L','P']);
    expect(DB::table('attendance_daily_marks')->where('student_id',2)->value('remarks'))->toBe('Original note');
});

it('restricts daily attendance to campus admins and rejects invalid daily statuses',function(){
    $controller=app(\App\Http\Controllers\DailyAttendanceController::class);
    $scope=['academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'date'=>'2026-11-02'];
    expect(fn()=>$controller->show(g12Request(User::find(2),$scope)))->toThrow(HttpException::class);
    $input=$scope+['version'=>0,'marks'=>[['student_id'=>1,'status'=>'absent','missed_sessions'=>1]]];
    expect(fn()=>$controller->save(g12Request(User::find(1),$input)))->toThrow(ValidationException::class);
    $input['marks'][0]['status']='UX';$input['marks'][0]['student_id']=999;
    expect(fn()=>$controller->save(g12Request(User::find(1),$input)))->toThrow(ValidationException::class);
});

it('reports daily exceptions with enrollment details and dependent options', function () {
    DB::table('tb_grade')->insert(['id'=>1,'grade'=>'Grade 1','grade_short_name'=>'1']);
    DB::table('tb_class')->where('id',1)->update(['class_name'=>'A','grade_id'=>1]);
    DB::table('tb_student_enrollment')->where('id',1)->update(['grade_id'=>1]);
    DB::table('tb_student')->where('id',1)->update(['photo_path'=>'students/test.webp','full_name_kh'=>"\u{179F}\u{17B7}\u{179F}\u{17D2}\u{179F}"]);
    $daily=app(\App\Http\Controllers\DailyAttendanceController::class);
    $admin=User::find(1);
    $daily->save(g12Request($admin,['academic_year_id'=>1,'campus_id'=>1,'grade_id'=>1,'class_id'=>1,'date'=>'2026-11-02','version'=>0,
        'marks'=>[['student_id'=>1,'status'=>'EX','missed_sessions'=>1,'remarks'=>'Medical leave']]]));
    $controller=app(\App\Http\Controllers\AttendanceReportController::class);
    $filters=['academic_year_id'=>1,'campus_id'=>1,'grade_class'=>'1:1','student_id'=>1];
    $options=$controller->options(g12Request($admin,$filters))->getData(true);
    expect(array_column($options['campuses'],'id'))->toBe([1])
        ->and($options['grades'][0]['label'])->toBe('1A')
        ->and($options['students'][0]['id'])->toBe(1);
    $result=$controller->details(g12Request($admin,$filters))->getData(true);
    expect($result['total'])->toBe(1)->and($result['per_page'])->toBe(15)
        ->and($result['data'][0]['reason'])->toBe('Medical leave')
        ->and($result['data'][0]['grade'])->toBe('BCH-1A-III')
        ->and($result['data'][0]['academic_year'])->toBe('2025-2026')
        ->and($result['data'][0]['photo_url'])->toContain('students/test.webp');
    expect($controller->details(g12Request($admin,$filters+['search'=>'unmatched']))->getData(true)['total'])->toBe(0);
    expect(fn()=>$controller->details(g12Request($admin,array_replace($filters,['campus_id'=>2]))))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect($controller->details(g12Request($admin,$filters+['date_from'=>'2026-11-03','date_to'=>'2026-11-04']))->getData(true)['total'])->toBe(0);
    expect($controller->details(g12Request($admin,$filters+['date_from'=>'2026-11-02','date_to'=>'2026-11-02']))->getData(true)['total'])->toBe(1);
    expect(fn()=>$controller->details(g12Request($admin,$filters+['date_from'=>'2026-11-03','date_to'=>'2026-11-02'])))->toThrow(ValidationException::class);
    expect($controller->details(g12Request($admin,$filters+['date_to'=>'2026-11-02']))->getData(true)['total'])->toBe(1);
    expect($controller->details(g12Request($admin,$filters))->getData(true)['student']['student_code'])->toBe('00123');
    expect($controller->details(g12Request($admin,$filters))->getData(true)['totals'])->toBe(['P'=>0,'EX'=>1,'UX'=>0,'L'=>0]);
    expect($controller->details(g12Request($admin,$filters+['date_from'=>'2026-11-03']))->getData(true)['totals']['EX'])->toBe(0);
    DB::table('attendance_daily_marks')->update(['status'=>'P']);
    expect($controller->details(g12Request($admin,$filters))->getData(true)['totals']['P'])->toBe(1);
    expect($controller->details(g12Request($admin,$filters))->getData(true)['total'])->toBe(0);
});

it('protects daily attendance reports from unauthorized campus access', function () {
    $controller=app(\App\Http\Controllers\AttendanceReportController::class);
    $user=User::find(2);
    expect(fn()=>$controller->details(g12Request($user,['academic_year_id'=>1])))->toThrow(ValidationException::class);
    $permission=DB::table('access_permissions')->where('code','attendance.manage')->value('id');
    DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>$permission,'allowed'=>true]);
    $user=User::find(2);
    expect($controller->options(g12Request($user,['academic_year_id'=>1,'campus_id'=>2]))->getData(true)['campuses'])->toHaveCount(1);
    expect($controller->options(g12Request($user,['academic_year_id'=>1]))->getData(true)['campuses'])->toHaveCount(1);
});

it('exports the selected students complete absent record with School Logo 1', function () {
    $admin=User::find(1);
    app(\App\Http\Controllers\DailyAttendanceController::class)->save(g12Request($admin,[
        'academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'date'=>'2026-11-02','version'=>0,
        'marks'=>[['student_id'=>1,'status'=>'EX','missed_sessions'=>1,'remarks'=>'Medical leave']],
    ]));
    $mark=(array)DB::table('attendance_daily_marks')->first();unset($mark['id']);
    for($day=1;$day<=15;$day++)DB::table('attendance_daily_marks')->insert(array_replace($mark,['attendance_date'=>'2026-10-'.str_pad($day,2,'0',STR_PAD_LEFT)]));
    $image=imagecreatetruecolor(4,4);ob_start();imagepng($image);$png=ob_get_clean();imagedestroy($image);
    $logo='data:image/png;base64,'.base64_encode($png);
    $this->mock(\App\Support\ScholarshipExport::class)->shouldReceive('reportLogo')->with(1)->andReturn($logo);
    $controller=app(\App\Http\Controllers\AttendanceReportController::class);
    $filters=['academic_year_id'=>1,'campus_id'=>1,'student_id'=>1,'page'=>2,'per_page'=>15];
    $print=$controller->export(g12Request($admin,$filters),'print');
    expect($print->getContent())->toContain('School Logo 1','Student Absent Record','02-Nov-2026','01-Oct-2026','Total Absent')->not->toContain('Total Present');
    $excel=$controller->export(g12Request($admin,$filters),'xlsx');
    $path=tempnam(sys_get_temp_dir(),'attendance-export-test-');file_put_contents($path,$excel->getContent());
    try {
        $zip=new \ZipArchive();$zip->open($path);
        $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');
        expect($sheet)->toContain('01-Oct-2026','02-Nov-2026','Medical leave')
            ->and($zip->getFromName('xl/media/image1.png'))->not->toBeFalse();
        $dom=new \DOMDocument();expect($dom->loadXML($sheet))->toBeTrue();$zip->close();
    } finally {unlink($path);}
    $pdf=$controller->export(g12Request($admin,$filters),'pdf');
    expect($pdf->getContent())->toStartWith('%PDF-')->and($pdf->headers->get('Content-Type'))->toBe('application/pdf');
    $limited=$controller->export(g12Request($admin,$filters+['date_from'=>'2026-11-01']),'print');
    expect($limited->getContent())->not->toContain('01-Oct-2026');
    expect($controller->export(g12Request(User::find(2),$filters),'xlsx')->getStatusCode())->toBe(200);
});

it('summarizes student absences once with late records separate and zero counts included', function () {
    $admin=User::find(1);
    app(\App\Http\Controllers\DailyAttendanceController::class)->save(g12Request($admin,[
        'academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'date'=>'2026-11-02','version'=>0,
        'marks'=>[['student_id'=>1,'status'=>'EX','missed_sessions'=>1,'remarks'=>'Leave']],
    ]));
    $mark=(array)DB::table('attendance_daily_marks')->first();unset($mark['id']);
    foreach(['2026-11-01'=>'UX','2026-10-31'=>'L','2026-10-30'=>'P'] as $date=>$status)
        DB::table('attendance_daily_marks')->insert(array_replace($mark,['attendance_date'=>$date,'status'=>$status]));
    DB::table('tb_student')->insert(['id'=>2,'student_id'=>'002','full_name_en'=>'No absences']);
    DB::table('tb_student_enrollment')->insert(['student_id'=>2,'campus_id'=>1,'academic_year_id'=>1,'class_id'=>1,'enrollment_status'=>'active']);
    $controller=app(\App\Http\Controllers\AttendanceReportController::class);
    $filters=['academic_year_id'=>1,'campus_id'=>1];
    $result=$controller->statistics(g12Request($admin,$filters))->getData(true);
    expect($result['total'])->toBe(2)->and($result['per_page'])->toBe(15)
        ->and($result['data'][0]['total_absent'])->toBe(2)
        ->and($result['data'][0]['EX'])->toBe(1)->and($result['data'][0]['UX'])->toBe(1)->and($result['data'][0]['L'])->toBe(1)
        ->and($result['data'][1]['total_absent'])->toBe(0)->and($result['data'][1]['L'])->toBe(0);
    expect($controller->statistics(g12Request($admin,$filters+['student_id'=>1]))->getData(true)['total'])->toBe(1);
    $ranged=$controller->statistics(g12Request($admin,$filters+['student_id'=>1,'date_from'=>'2026-11-02','date_to'=>'2026-11-02']))->getData(true);
    expect($ranged['data'][0]['total_absent'])->toBe(1)->and($ranged['data'][0]['EX'])->toBe(1)
        ->and($ranged['data'][0]['UX'])->toBe(0)->and($ranged['data'][0]['L'])->toBe(0);
    expect($controller->statistics(g12Request($admin,$filters+['date_from'=>'2026-11-03']))->getData(true)['data'][0]['total_absent'])->toBe(0);
    expect(fn()=>$controller->statistics(g12Request($admin,$filters+['date_from'=>'2026-11-03','date_to'=>'2026-11-02'])))->toThrow(ValidationException::class);
    expect($controller->statistics(g12Request($admin,$filters+['grade_class'=>'0:999']))->getData(true)['total'])->toBe(0);
    expect($controller->statistics(g12Request($admin,array_replace($filters,['campus_id'=>2])))->getData(true)['total'])->toBe(0);
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027']);
    expect($controller->statistics(g12Request($admin,['academic_year_id'=>2]))->getData(true)['total'])->toBe(0);
    expect($controller->statistics(g12Request(User::find(2),$filters))->getData(true)['total'])->toBe(2);
});

it('exports filtered absence statistics as A4 print Excel and PDF across all pages', function () {
    DB::table('tb_grade')->insert(['id'=>2,'grade'=>'Grade 2','grade_short_name'=>'2']);
    DB::table('tb_class')->where('id',1)->update(['class_name'=>'B','grade_id'=>2]);
    DB::table('tb_student_enrollment')->where('id',1)->update(['grade_id'=>2]);
    for($id=2;$id<=16;$id++){
        DB::table('tb_student')->insert(['id'=>$id,'student_id'=>'TEST'.str_pad($id,3,'0',STR_PAD_LEFT),'full_name_en'=>'Report Student '.$id]);
        DB::table('tb_student_enrollment')->insert(['student_id'=>$id,'campus_id'=>1,'academic_year_id'=>1,'class_id'=>1,'grade_id'=>2,'enrollment_status'=>'active']);
    }
    $image=imagecreatetruecolor(4,4);ob_start();imagepng($image);$png=ob_get_clean();imagedestroy($image);
    $this->mock(\App\Support\ScholarshipExport::class)->shouldReceive('reportLogo')->with(1)->andReturn('data:image/png;base64,'.base64_encode($png));
    $controller=app(\App\Http\Controllers\AttendanceReportController::class);$admin=User::find(1);
    $filters=['academic_year_id'=>1,'campus_id'=>1,'grade_class'=>'2:1','date_from'=>'2026-11-10','date_to'=>'2026-11-30','page'=>2,'per_page'=>15];
    $print=$controller->statisticsExport(g12Request($admin,$filters),'print');
    expect($print->getContent())->toContain('Student Absence Report - Grade 2B','School Logo 1','10-Nov-2026 to 30-Nov-2026','Academic Year:','Campus:','Report Student 2','Report Student 16','Total Tardy','Total Excused','Total Unexcused','Total Absent');
    $excel=$controller->statisticsExport(g12Request($admin,$filters),'xlsx');
    $path=tempnam(sys_get_temp_dir(),'attendance-statistics-test-');file_put_contents($path,$excel->getContent());
    try {
        $zip=new \ZipArchive();$zip->open($path);$sheet=$zip->getFromName('xl/worksheets/sheet1.xml');
        expect($sheet)->toContain('Report Student 2','Report Student 16','orientation="landscape"','Total Tardy','10-Nov-2026 to 30-Nov-2026')
            ->and($zip->getFromName('xl/media/image1.png'))->not->toBeFalse();
        $dom=new \DOMDocument();expect($dom->loadXML($sheet))->toBeTrue();
        expect($zip->getFromName('xl/workbook.xml'))->toContain('8:$8');$zip->close();
    } finally {unlink($path);}
    expect($controller->statisticsExport(g12Request($admin,$filters),'pdf')->getContent())->toStartWith('%PDF-');
    $single=$controller->statisticsExport(g12Request($admin,$filters+['student_id'=>2]),'print')->getContent();
    expect($single)->toContain('Report Student 2')->not->toContain('Report Student 16');
    DB::table('tb_grade')->insert(['id'=>1,'grade'=>'Grade 1','grade_short_name'=>'1']);
    DB::table('tb_class')->insert(['id'=>2,'academic_year_id'=>1,'grade_id'=>1,'class_name'=>'A']);
    DB::table('tb_student_enrollment')->where('student_id',16)->update(['grade_id'=>1,'class_id'=>2]);
    $allGrades=$filters;unset($allGrades['grade_class']);
    $grouped=$controller->statisticsExport(g12Request($admin,$allGrades),'print')->getContent();
    expect($grouped)->toContain('Grade: All Grades','Grade: 1A','Grade: 2B','statistics-grade-header')
        ->and(strpos($grouped,'Grade: 1A'))->toBeLessThan(strpos($grouped,'Grade: 2B'));
    expect($print->getContent())->not->toContain('statistics-grade-header');
    expect($controller->statisticsExport(g12Request(User::find(2),$filters),'pdf')->getStatusCode())->toBe(200);
});

function attendanceGrant(int $userId, array $actions, bool $allowed=true): void {
    foreach($actions as $action)DB::table('access_user_permission_overrides')->updateOrInsert(
        ['user_id'=>$userId,'permission_id'=>DB::table('access_permissions')->where('code','attendance.'.$action)->value('id')],
        ['allowed'=>$allowed,'created_at'=>now(),'updated_at'=>now()]);
}

it('enforces separate daily create edit delete rights and retains deletion audit history', function () {
    (require database_path('migrations/2026_11_04_000001_add_attendance_permissions.php'))->up();
    $controller=app(\App\Http\Controllers\DailyAttendanceController::class);$user=User::find(2);
    $scope=['academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'date'=>'2026-11-02'];
    $input=$scope+['version'=>0,'partial'=>true,'marks'=>[['student_id'=>1,'status'=>'P','missed_sessions'=>0,'remarks'=>'']]];
    expect($controller->show(g12Request($user,$scope))->getData(true)['permissions']['create'])->toBeFalse();
    expect(fn()=>$controller->save(g12Request($user,$input)))->toThrow(HttpException::class);
    attendanceGrant(2,['create']);$controller->save(g12Request($user,$input));
    expect(DB::table('attendance_daily_marks')->value('status'))->toBe('P');
    $input['version']=1;$input['marks'][0]['status']='EX';
    expect(fn()=>$controller->save(g12Request($user,$input)))->toThrow(HttpException::class);
    attendanceGrant(2,['edit']);$controller->save(g12Request($user,$input));
    expect(DB::table('attendance_daily_marks')->value('status'))->toBe('EX');
    $delete=$scope+['student_id'=>1,'version'=>2,'reason'=>'Duplicate entry corrected'];
    expect(fn()=>$controller->delete(g12Request($user,$delete)))->toThrow(HttpException::class);
    attendanceGrant(2,['delete']);$controller->delete(g12Request($user,$delete));
    expect(DB::table('attendance_daily_marks')->count())->toBe(0)
        ->and(DB::table('attendance_daily_events')->count())->toBe(3)
        ->and(DB::table('attendance_daily_events')->orderByDesc('id')->value('details'))->toContain('Duplicate entry corrected','deleted');
    foreach(['show','save','delete'] as $action)expect(fn()=>$controller->$action(g12Request($user,array_replace($action==='delete'?$delete:($action==='save'?$input:$scope),['campus_id'=>2]))))->toThrow(HttpException::class);
});

it('permits campus report readers to see all campuses without granting cross campus changes', function () {
    (require database_path('migrations/2026_11_04_000001_add_attendance_permissions.php'))->up();
    DB::table('tb_student')->insert(['id'=>2,'student_id'=>'OTHER002','full_name_en'=>'Other Campus Student']);
    DB::table('tb_student_enrollment')->insert(['student_id'=>2,'campus_id'=>2,'academic_year_id'=>1,'class_id'=>1,'enrollment_status'=>'active']);
    $daily=app(\App\Http\Controllers\DailyAttendanceController::class);
    $scope=['academic_year_id'=>1,'campus_id'=>2,'class_id'=>1,'date'=>'2026-11-02'];
    $daily->save(g12Request(User::find(1),$scope+['version'=>0,'marks'=>[['student_id'=>2,'status'=>'UX','missed_sessions'=>1,'remarks'=>'Absent']]]));
    $user=User::find(2);$controller=app(\App\Http\Controllers\AttendanceReportController::class);
    expect($controller->options(g12Request($user,['academic_year_id'=>1]))->getData(true)['campuses'])->toHaveCount(2);
    $filters=['academic_year_id'=>1,'campus_id'=>2,'student_id'=>2];
    expect($controller->details(g12Request($user,$filters))->getData(true)['data'][0]['status'])->toBe('UX')
        ->and($controller->statistics(g12Request($user,$filters))->getData(true)['data'][0]['total_absent'])->toBe(1)
        ->and($controller->export(g12Request($user,$filters),'print')->getStatusCode())->toBe(200);
    attendanceGrant(2,['print'],false);
    expect(fn()=>$controller->export(g12Request($user,$filters),'print'))->toThrow(HttpException::class);
    expect(fn()=>$controller->statisticsExport(g12Request($user,$filters),'print'))->toThrow(HttpException::class);
    attendanceGrant(2,['detail'],false);
    expect(fn()=>$controller->details(g12Request($user,$filters)))->toThrow(HttpException::class);
    expect(fn()=>$controller->options(g12Request($user,$filters)))->toThrow(HttpException::class);
    expect($controller->options(g12Request($user,$filters+['report'=>'statistics']))->getData(true)['students'])->toHaveCount(1);
    attendanceGrant(2,['create','edit','delete']);
    expect(fn()=>$daily->save(g12Request($user,$scope+['version'=>1,'marks'=>[['student_id'=>2,'status'=>'P','missed_sessions'=>0]]])))->toThrow(HttpException::class);
    DB::table('users')->where('id',2)->update(['is_global'=>true]);$central=User::find(2);
    $daily->save(g12Request($central,$scope+['version'=>1,'marks'=>[['student_id'=>2,'status'=>'P','missed_sessions'=>0]]]));
    expect(DB::table('attendance_daily_marks')->value('status'))->toBe('P');
    attendanceGrant(2,['edit'],false);
    expect(fn()=>$daily->save(g12Request($central,$scope+['version'=>2,'marks'=>[['student_id'=>2,'status'=>'EX','missed_sessions'=>1]]])))->toThrow(HttpException::class);
});

it('requires parent menu permissions and exposes all attendance controls in the permission tree', function () {
    (require database_path('migrations/2026_11_04_000001_add_attendance_permissions.php'))->up();
    $permissions=\App\Models\Permission::all();
    $tree=\App\Support\PermissionHierarchy::tree($permissions)['attendance'];
    expect(array_keys($tree['modules']))->toBe(['attendance.subject','attendance.daily','attendance.reports','attendance.settings']);
    expect($tree['modules']['attendance.daily']['actions']->pluck('code')->all())->toContain('attendance.create','attendance.edit','attendance.delete');
    attendanceGrant(2,['reports'],false);
    expect(\App\Support\AttendancePermissions::allows(User::find(2),'statistics'))->toBeFalse();
    $normalized=\App\Support\PermissionHierarchy::normalizeIds($permissions->whereIn('code',['attendance.view','attendance.statistics'])->pluck('id')->all(),$permissions);
    expect($normalized)->not->toContain($permissions->firstWhere('code','attendance.statistics')->id);
});

it('limits calendar changes to permitted campus and settings tabs', function () {
    (require database_path('migrations/2026_11_04_000001_add_attendance_permissions.php'))->up();
    attendanceGrant(2,['settings','weekdays','calendar-manage']);
    $user=User::find(2);$controller=app(\App\Http\Controllers\AttendanceLeaveController::class);$service=app(\App\Services\AttendanceLeave::class);
    $scope=['academic_year_id'=>1,'campus_id'=>1];
    $body=$scope+['weekdays'=>[1,2,3,4,5,6],'holidays'=>['2026-11-09'],'holiday_descriptions'=>['2026-11-09'=>'Not permitted'],'version'=>0];
    $controller->calendar(g12Request($user,$body,'/attendance/calendar','POST'),$service);
    expect($service->calendar(1,1)['weekdays'])->toBe([1,2,3,4,5,6])->and($service->calendar(1,1)['holidays'])->toBe([]);
    expect(fn()=>$controller->calendar(g12Request($user,array_replace($body,['campus_id'=>2]),'/attendance/calendar','POST'),$service))->toThrow(HttpException::class);
    attendanceGrant(2,['calendar-manage'],false);
    expect(fn()=>$controller->calendar(g12Request($user,array_replace($body,['version'=>1]),'/attendance/calendar','POST'),$service))->toThrow(HttpException::class);
});
