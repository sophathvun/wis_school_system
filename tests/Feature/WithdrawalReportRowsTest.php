<?php

use App\Http\Controllers\ReportsController;
use App\Models\{StudentEnrollmentHistory,User};
use App\Services\WithdrawalReportRows;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema};

beforeEach(function(){
    Schema::create('tb_student_enrollment_history',function(Blueprint $t){$t->id();foreach((new StudentEnrollmentHistory)->getFillable() as $c){if(str_ends_with($c,'_id'))$t->integer($c)->nullable();else $t->string($c)->nullable();}$t->timestamps();});
    Schema::create('tb_student_enrollment',function(Blueprint $t){$t->id();foreach(['student_id','academic_year_id','campus_id','grade_id','class_id','academic_track_id','session_id'] as $c)$t->integer($c)->nullable();foreach(['student_type','enrollment_status','ended_on','exit_reason','notes'] as $c)$t->string($c)->nullable();});
    Schema::create('tb_academic_year',function(Blueprint $t){$t->id();$t->string('period_type');$t->softDeletes();});
    Schema::create('tb_school_info',function(Blueprint $t){$t->id();$t->string('campus_name_en');$t->integer('status')->default(1);$t->softDeletes();});
    Schema::create('tb_grade',function(Blueprint $t){$t->id();$t->string('grade');$t->string('grade_short_name');$t->integer('grade_order');});
    Schema::create('tb_class',function(Blueprint $t){$t->id();$t->string('class_name');$t->integer('class_order');});
    Schema::create('tb_session',function(Blueprint $t){$t->id();$t->string('session_short_name');$t->integer('session_order');});
    DB::table('tb_academic_year')->insert([['id'=>1,'period_type'=>'regular'],['id'=>2,'period_type'=>'summer']]);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'BCH'],['id'=>2,'campus_name_en'=>'BKK'],['id'=>3,'campus_name_en'=>'EMPTY']]);
    DB::table('tb_grade')->insert(['id'=>1,'grade'=>'Grade 1','grade_short_name'=>'1','grade_order'=>1]);
    DB::table('tb_class')->insert(['id'=>1,'class_name'=>'A','class_order'=>1]);
    DB::table('tb_session')->insert(['id'=>1,'session_short_name'=>'III','session_order'=>1]);
    $user=Mockery::mock(User::class)->makePartial();$user->shouldReceive('isSuperAdmin')->andReturn(true);
    $this->request=Request::create('/');$this->request->setUserResolver(fn()=>$user);
    $this->filters=['academic_year_id'=>1,'period_type'=>'regular','withdrawal_status'=>'approved'];
    $this->legacy=function(int $student,int $campus=1,int $year=1,string $status='withdrawn'){
        return DB::table('tb_student_enrollment')->insertGetId(['student_id'=>$student,'academic_year_id'=>$year,'campus_id'=>$campus,'grade_id'=>1,'class_id'=>1,'session_id'=>1,'enrollment_status'=>$status,'ended_on'=>'2026-11-06','exit_reason'=>'Moved school']);
    };
    $this->invoke=function(string $method,array $args){return (new ReflectionMethod(ReportsController::class,$method))->invoke(app(ReportsController::class),...$args);};
});

it('populates campus grade and group choices from imported withdrawals without writing history',function(){
    ($this->legacy)(1);($this->legacy)(2,2);($this->legacy)(3,3,2);($this->legacy)(4,3,1,'active');
    $campuses=($this->invoke)('campuses',[$this->request,$this->filters,'withdrawn-students']);
    expect($campuses->pluck('campus_name_en')->all())->toBe(['BCH','BKK']);
    expect(($this->invoke)('withdrawalGradeClassOptions',[$this->request,$this->filters]))->toHaveCount(1);
    expect(($this->invoke)('withdrawalGroupOptions',[$this->request,$this->filters]))->toHaveCount(1);
    $rows=($this->invoke)('withdrawalOptionBaseQuery',[$this->request,[...$this->filters,'campus_id'=>2]])->get();
    expect($rows)->toHaveCount(1)->and((int)$rows->first()->student_id)->toBe(2)
        ->and($rows->first()->effective_on->format('Y-m-d'))->toBe('2026-11-06')
        ->and($rows->first()->reason)->toBe('Moved school')->and($rows->first()->approved_by)->toBeNull()
        ->and(DB::table('tb_student_enrollment_history')->count())->toBe(0);
    expect(($this->invoke)('withdrawalOptionBaseQuery',[$this->request,[...$this->filters,'withdrawal_status'=>'pending']])->count())->toBe(0);
});

it('preserves real workflow rows and avoids duplicating an approved withdrawal',function(){
    $id=($this->legacy)(1);
    DB::table('tb_student_enrollment_history')->insert(['enrollment_id'=>$id,'student_id'=>1,'academic_year_id'=>1,'campus_id'=>1,'grade_id'=>1,'class_id'=>1,'session_id'=>1,'action_type'=>'withdrawal','withdrawal_status'=>'approved','enrollment_status'=>'withdrawn','effective_on'=>'2026-10-15','reason'=>'Original reason']);
    DB::table('tb_student_enrollment_history')->insert(['student_id'=>2,'academic_year_id'=>1,'campus_id'=>2,'action_type'=>'withdrawal','withdrawal_status'=>'pending']);
    $rows=app(WithdrawalReportRows::class)->query()->get();
    expect($rows)->toHaveCount(2)->and($rows->firstWhere('withdrawal_status','approved')->reason)->toBe('Original reason');
    expect(($this->invoke)('withdrawalOptionBaseQuery',[$this->request,[...$this->filters,'withdrawal_status'=>'pending']])->count())->toBe(1);
});

it('keeps campus choices and imported withdrawal data within the users accessible campuses',function(){
    ($this->legacy)(1);($this->legacy)(2,2);
    $user=Mockery::mock(User::class)->makePartial();$user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>App\Models\SchoolInfo::where('id',1));
    $this->request->setUserResolver(fn()=>$user);
    expect(($this->invoke)('campuses',[$this->request,$this->filters,'withdrawn-students'])->pluck('id')->all())->toBe([1]);
    expect(($this->invoke)('withdrawalOptionBaseQuery',[$this->request,$this->filters])->count())->toBe(1);
    expect(($this->invoke)('withdrawalOptionBaseQuery',[$this->request,[...$this->filters,'campus_id'=>2]])->count())->toBe(0);
});
