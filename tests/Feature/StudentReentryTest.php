<?php

use App\Models\StudentEnrollment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

beforeEach(function () {
    $this->withoutMiddleware();
    $this->actingAs(new class extends App\Models\User { public function isSuperAdmin(): bool { return true; } });
    Schema::create('tb_student',function(Blueprint $t){
        $t->id();foreach(['student_id','student_no','full_name_en','full_name_kh','photo_path'] as $c)$t->string($c)->nullable();
    });
    Schema::create('tb_academic_year',function(Blueprint $t){$t->id();$t->string('academic_year');$t->string('period_type')->default('regular');$t->string('lifecycle_status');$t->softDeletes();});
    Schema::create('tb_school_info',function(Blueprint $t){$t->id();$t->string('campus_name_en');$t->boolean('status')->default(true);$t->softDeletes();});
    Schema::create('tb_grade',function(Blueprint $t){$t->id();$t->string('grade');$t->integer('grade_order');$t->boolean('status')->default(true);$t->softDeletes();});
    Schema::create('tb_class',function(Blueprint $t){$t->id();$t->string('class_name');$t->integer('grade_id');$t->boolean('status')->default(true);$t->softDeletes();});
    Schema::create('tb_session',function(Blueprint $t){$t->id();$t->string('session_short_name');$t->integer('session_order');$t->boolean('status')->default(true);$t->softDeletes();});
    Schema::create('tb_student_enrollment',function(Blueprint $t){
        $t->id();foreach(['student_id','academic_year_id','campus_id','grade_id','class_id','session_id'] as $c)$t->integer($c);
        $t->string('enrollment_status');$t->string('student_type')->default('old');$t->boolean('status')->default(true);
        $t->date('enrolled_on')->nullable();$t->date('ended_on')->nullable();$t->text('notes')->nullable();$t->timestamps();
    });
    Schema::create('tb_student_enrollment_history',function(Blueprint $t){
        $t->id();foreach(['enrollment_id','source_history_id','student_id','academic_year_id','campus_id','grade_id','class_id','session_id','changed_by'] as $c)$t->integer($c)->nullable();
        foreach(['action_type','withdrawal_status','enrollment_status','student_type','reason'] as $c)$t->string($c)->nullable();
        $t->date('effective_on')->nullable();$t->text('notes')->nullable();$t->timestamps();
    });
    (require database_path('migrations/2026_11_19_000001_add_reentry_source_enrollment_to_history.php'))->up();
    foreach([1=>'2023-2024',2=>'2024-2025',3=>'2025-2026',4=>'2026-2027',5=>'2027-2028'] as $id=>$year)
        DB::table('tb_academic_year')->insert(['id'=>$id,'academic_year'=>$year,'lifecycle_status'=>$id===4?'started':($id===5?'pending':'finished')]);
    DB::table('tb_academic_year')->insert(['id'=>6,'academic_year'=>'Summer 2026','period_type'=>'summer','lifecycle_status'=>'finished']);
    DB::table('tb_school_info')->insert(['id'=>1,'campus_name_en'=>'BCH']);
    DB::table('tb_grade')->insert(['id'=>1,'grade'=>'Grade 5','grade_order'=>5]);
    DB::table('tb_class')->insert(['id'=>1,'class_name'=>'A','grade_id'=>1]);
    DB::table('tb_session')->insert(['id'=>1,'session_short_name'=>'AM','session_order'=>1]);
    foreach(range(1,8) as $id)DB::table('tb_student')->insert(['id'=>$id,'student_id'=>'ID'.$id,'student_no'=>'NO'.$id,'full_name_en'=>'Student '.$id]);
    $this->enroll=fn($student,$year=2,$status='withdrawn')=>StudentEnrollment::create(['student_id'=>$student,'academic_year_id'=>$year,'campus_id'=>1,'grade_id'=>1,'class_id'=>1,'session_id'=>1,'enrollment_status'=>$status]);
    $this->payload=['academic_year_id'=>4,'campus_id'=>1,'grade_id'=>1,'class_id'=>1,'session_id'=>1,'effective_on'=>'2026-11-19'];
});

it('lists imported historical withdrawals without creating approval history and supports search',function(){
    $source=($this->enroll)(1);
    $this->getJson(route('student-reentry.options'))->assertOk()->assertJsonCount(1,'withdrawals')
        ->assertJsonPath('withdrawals.0.source_enrollment_id',$source->id)->assertJsonPath('withdrawals.0.return_type','withdrawn');
    $this->getJson(route('student-reentry.options',['search'=>'ID1']))->assertJsonCount(1,'withdrawals');
    $this->getJson(route('student-reentry.options',['search'=>'missing']))->assertJsonCount(0,'withdrawals');
    expect(DB::table('tb_student_enrollment_history')->count())->toBe(0);
});

it('identifies a full study gap using regular enrollment rather than Summer attendance',function(){
    $gap=($this->enroll)(1,2,'completed');($this->enroll)(1,6,'completed');
    ($this->enroll)(2,3,'completed'); // Previous year: use promotion, not gap re-entry.
    ($this->enroll)(3,2,'withdrawn');($this->enroll)(3,4,'active');
    ($this->enroll)(4,2,'completed');($this->enroll)(4,4,'pending');
    ($this->enroll)(5,2,'graduated');
    $this->getJson(route('student-reentry.options'))->assertOk()->assertJsonCount(1,'withdrawals')
        ->assertJsonPath('withdrawals.0.id',$gap->id)->assertJsonPath('withdrawals.0.return_type','study_gap');
    $next=$this->getJson(route('student-reentry.options',['academic_year_id'=>5]))->assertOk();
    expect(collect($next->json('withdrawals'))->pluck('student_id')->all())->toContain(2);
});

it('creates one old enrollment and linked audit while preserving the profile and historical source',function($status,$reason){
    $source=($this->enroll)(1,2,$status);$before=$source->fresh()->getRawOriginal();
    $student=(array)DB::table('tb_student')->where('id',1)->first();
    $payload=[...$this->payload,'source_enrollment_id'=>$source->id];
    $this->postJson(route('student-reentry.save'),$payload)->assertOk()
        ->assertJsonPath('data.student_id',1)->assertJsonPath('data.student_type','old')->assertJsonPath('data.enrollment_status','active');
    expect($source->fresh()->getRawOriginal())->toBe($before)
        ->and((array)DB::table('tb_student')->where('id',1)->first())->toBe($student)
        ->and(DB::table('tb_student')->count())->toBe(8);
    $this->assertDatabaseHas('tb_student_enrollment_history',['source_enrollment_id'=>$source->id,'source_history_id'=>null,'reason'=>$reason]);
    $this->postJson(route('student-reentry.save'),$payload)->assertUnprocessable();
    expect(StudentEnrollment::where('student_id',1)->where('academic_year_id',4)->count())->toBe(1);
    $this->getJson(route('student-reentry.options'))->assertJsonCount(0,'withdrawals');
})->with([['withdrawn','Returned after withdrawal'],['completed','Returned after a study gap']]);

it('retains approved workflow links and accepts the previous history-based request format',function(){
    $source=($this->enroll)(1);
    $history=DB::table('tb_student_enrollment_history')->insertGetId(['enrollment_id'=>$source->id,'student_id'=>1,'action_type'=>'withdrawal','withdrawal_status'=>'approved','enrollment_status'=>'withdrawn']);
    $this->postJson(route('student-reentry.save'),[...$this->payload,'source_history_id'=>$history])->assertOk();
    $this->assertDatabaseHas('tb_student_enrollment_history',['action_type'=>'re_entry','source_history_id'=>$history,'source_enrollment_id'=>$source->id]);
});

it('creates pending enrollment for the planning year and rejects completed destination years',function(){
    $source=($this->enroll)(1);
    $this->postJson(route('student-reentry.save'),[...$this->payload,'source_enrollment_id'=>$source->id,'academic_year_id'=>2])->assertUnprocessable();
    $this->postJson(route('student-reentry.save'),[...$this->payload,'source_enrollment_id'=>$source->id,'academic_year_id'=>5])
        ->assertOk()->assertJsonPath('data.enrollment_status','pending');
});

it('rejects ineligible sources and returns before the previous enrollment ended',function(){
    $recent=($this->enroll)(1,3,'completed');
    $this->postJson(route('student-reentry.save'),[...$this->payload,'source_enrollment_id'=>$recent->id])->assertUnprocessable();
    $source=($this->enroll)(2,4,'withdrawn');$source->update(['ended_on'=>'2026-11-20']);
    $this->postJson(route('student-reentry.save'),[...$this->payload,'source_enrollment_id'=>$source->id])->assertUnprocessable()->assertJsonValidationErrors('effective_on');
    expect(DB::table('tb_student_enrollment_history')->count())->toBe(0);
});

it('does not convert pending or rejected withdrawal requests into eligibility',function(){
    $source=($this->enroll)(1,4,'active');
    foreach(['pending','rejected','cancelled'] as $status)DB::table('tb_student_enrollment_history')->insert(['enrollment_id'=>$source->id,'student_id'=>1,'action_type'=>'withdrawal','withdrawal_status'=>$status]);
    $this->getJson(route('student-reentry.options'))->assertOk()->assertJsonCount(0,'withdrawals');
    $this->postJson(route('student-reentry.save'),[...$this->payload,'source_enrollment_id'=>$source->id])->assertUnprocessable();
});

it('allows a same-year return without overwriting the withdrawn enrollment',function(){
    $source=($this->enroll)(1,4,'withdrawn');$source->update(['ended_on'=>'2026-10-01']);
    $this->postJson(route('student-reentry.save'),[...$this->payload,'source_enrollment_id'=>$source->id])->assertOk();
    expect($source->fresh()->enrollment_status)->toBe('withdrawn')
        ->and(StudentEnrollment::where('student_id',1)->where('academic_year_id',4)->count())->toBe(2);
});

it('rejects a withdrawal history belonging to another source enrollment',function(){
    $source=($this->enroll)(1);$other=($this->enroll)(2);
    $history=DB::table('tb_student_enrollment_history')->insertGetId(['enrollment_id'=>$other->id,'student_id'=>2,'action_type'=>'withdrawal','withdrawal_status'=>'approved','enrollment_status'=>'withdrawn']);
    $this->postJson(route('student-reentry.save'),[...$this->payload,'source_enrollment_id'=>$source->id,'source_history_id'=>$history])
        ->assertUnprocessable()->assertJsonValidationErrors('source_enrollment_id');
    expect(StudentEnrollment::where('academic_year_id',4)->count())->toBe(0);
});
