<?php

use App\Models\User;
use App\Services\StatisticsDetailWithdrawals;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema};
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-18 12:00:00','Asia/Phnom_Penh'));
    Schema::create('tb_student_enrollment',function(Blueprint $t){$t->id();foreach(['student_id','academic_year_id','campus_id','grade_id','class_id','session_id'] as $c)$t->integer($c)->nullable();$t->string('enrollment_status');$t->date('ended_on')->nullable();});
    Schema::create('tb_student_enrollment_history',function(Blueprint $t){$t->id();foreach(['enrollment_id','student_id','academic_year_id','campus_id','grade_id','class_id','session_id'] as $c)$t->integer($c)->nullable();foreach(['action_type','withdrawal_status','enrollment_status'] as $c)$t->string($c);$t->date('effective_on')->nullable();});
    Schema::create('tb_academic_year',function(Blueprint $t){$t->id();$t->string('period_type');});
    Schema::create('tb_school_info',function(Blueprint $t){$t->id();$t->string('campus_name_en');});
    Schema::create('tb_grade',function(Blueprint $t){$t->id();$t->string('grade');$t->string('grade_short_name');$t->integer('grade_order');});
    Schema::create('tb_class',function(Blueprint $t){$t->id();$t->string('class_name');$t->integer('class_order');});
    DB::table('tb_academic_year')->insert([['id'=>1,'period_type'=>'regular'],['id'=>2,'period_type'=>'summer']]);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'BCH'],['id'=>2,'campus_name_en'=>'BKK']]);
    DB::table('tb_grade')->insert(['id'=>1,'grade'=>'Grade 1','grade_short_name'=>'1','grade_order'=>1]);
    DB::table('tb_class')->insert(['id'=>1,'class_name'=>'A','class_order'=>1]);
    $this->request=Request::create('/');
    $user=Mockery::mock(User::class)->makePartial();$user->shouldReceive('isSuperAdmin')->andReturn(true);
    $this->request->setUserResolver(fn()=>$user);
    $this->filters=['month'=>'2026-11','academic_year_id'=>1,'period_type'=>'regular'];
    $this->enrollment=function(int $student,?string $date,int $campus=1,int $year=1,string $status='withdrawn'){
        return DB::table('tb_student_enrollment')->insertGetId(['student_id'=>$student,'academic_year_id'=>$year,'campus_id'=>$campus,'grade_id'=>1,'class_id'=>1,'session_id'=>1,'enrollment_status'=>$status,'ended_on'=>$date]);
    };
    $this->history=function(int $student,int $enrollment,string $date,string $status='approved',int $campus=1){
        DB::table('tb_student_enrollment_history')->insert(['enrollment_id'=>$enrollment,'student_id'=>$student,'academic_year_id'=>1,'campus_id'=>$campus,'grade_id'=>1,'class_id'=>1,'session_id'=>1,'action_type'=>'withdrawal','withdrawal_status'=>$status,'enrollment_status'=>'withdrawn','effective_on'=>$date]);
    };
});

afterEach(fn()=>Carbon::setTestNow());

it('includes imported enrollment withdrawals from September through today with monthly counts per campus',function(){
    ($this->enrollment)(1,'2026-09-01');($this->enrollment)(2,'2026-10-31');($this->enrollment)(3,'2026-11-18');
    ($this->enrollment)(4,'2026-08-31');($this->enrollment)(5,'2026-11-19');($this->enrollment)(6,null);
    ($this->enrollment)(7,'2026-11-01',2);($this->enrollment)(8,'2026-11-01',1,2);
    $rows=app(StatisticsDetailWithdrawals::class)->rows($this->request,$this->filters);
    expect($rows['1:1:1']->monthly_dropout)->toBe(1)->and($rows['1:1:1']->cumulative_dropout)->toBe(3)
        ->and($rows['2:1:1']->monthly_dropout)->toBe(1)->and($rows['2:1:1']->cumulative_dropout)->toBe(1);
    $filtered=app(StatisticsDetailWithdrawals::class)->rows($this->request,[...$this->filters,'campus_id'=>2]);
    expect($filtered->keys()->all())->toBe(['2:1:1']);
});

it('counts approved history once, excludes pending cancelled requests and keeps approved withdrawals after reentry',function(){
    $id=($this->enrollment)(1,'2026-11-03');($this->history)(1,$id,'2026-10-12');($this->history)(1,$id,'2026-10-12');
    $reentry=($this->enrollment)(2,null,1,1,'active');($this->history)(2,$reentry,'2026-11-02');
    $pending=($this->enrollment)(3,null,1,1,'active');($this->history)(3,$pending,'2026-11-02','pending');
    ($this->history)(4,$pending,'2026-11-02','cancelled');
    $rows=app(StatisticsDetailWithdrawals::class)->rows($this->request,$this->filters);
    expect($rows['1:1:1']->monthly_dropout)->toBe(1)->and($rows['1:1:1']->cumulative_dropout)->toBe(2);
});

it('uses the selected historical month end and keeps session filters',function(){
    ($this->enrollment)(1,'2026-09-10');($this->enrollment)(2,'2026-10-10');($this->enrollment)(3,'2026-11-02');
    $rows=app(StatisticsDetailWithdrawals::class)->rows($this->request,[...$this->filters,'month'=>'2026-10']);
    expect($rows['1:1:1']->monthly_dropout)->toBe(1)->and($rows['1:1:1']->cumulative_dropout)->toBe(2);
    expect(app(StatisticsDetailWithdrawals::class)->rows($this->request,[...$this->filters,'session_id'=>2]))->toBeEmpty();
});

it('limits withdrawal rows to the users accessible campuses',function(){
    ($this->enrollment)(1,'2026-11-02');($this->enrollment)(2,'2026-11-02',2);
    $user=Mockery::mock(User::class)->makePartial();$user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('accessibleCampuses')->andReturn(DB::table('tb_school_info')->where('id',1));
    $this->request->setUserResolver(fn()=>$user);
    $rows=app(StatisticsDetailWithdrawals::class)->rows($this->request,$this->filters);
    expect($rows->keys()->all())->toBe(['1:1:1']);
});
