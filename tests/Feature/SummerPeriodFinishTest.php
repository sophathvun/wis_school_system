<?php
use App\Models\{AcademicYear,User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

beforeEach(function(){
    Schema::create('tb_academic_year',function(Blueprint $t){
        $t->id();$t->string('academic_year');$t->string('academic_year_code')->nullable();$t->string('period_type');$t->string('lifecycle_status');$t->boolean('status')->default(true);
        $t->date('start_date')->nullable();$t->date('end_date')->nullable();$t->timestamps();$t->softDeletes();
    });
    Schema::create('tb_student_enrollment',function(Blueprint $t){$t->id();$t->unsignedBigInteger('academic_year_id');$t->string('enrollment_status');$t->date('ended_on')->nullable();$t->timestamps();});
    DB::table('tb_academic_year')->insert([
        ['id'=>1,'academic_year'=>'Summer 2026','period_type'=>'summer','lifecycle_status'=>'started','status'=>1,'start_date'=>'2026-07-01','end_date'=>'2026-08-31'],
        ['id'=>2,'academic_year'=>'2026-2027','period_type'=>'regular','lifecycle_status'=>'started','status'=>1,'start_date'=>null,'end_date'=>null],
        ['id'=>3,'academic_year'=>'Summer 2025','period_type'=>'summer','lifecycle_status'=>'pending','status'=>1,'start_date'=>'2025-07-01','end_date'=>'2025-08-31'],
    ]);
    foreach(['active','pending','completed','withdrawn','graduated'] as $index=>$status)DB::table('tb_student_enrollment')->insert(['id'=>$index+1,'academic_year_id'=>1,'enrollment_status'=>$status,'ended_on'=>null]);
    DB::table('tb_student_enrollment')->insert(['id'=>6,'academic_year_id'=>2,'enrollment_status'=>'active']);
    $this->manager=new class extends User {
        public bool $canManage=true;
        public function hasPermission(string $permission,?int $campusId=null): bool{return $this->canManage&&$permission==='academic-years.manage';}
    };
    $this->manager->id=1;$this->manager->status=1;$this->manager->last_seen_at=now();$this->actingAs($this->manager);
});
it('finishes a two month summer without creating a next period or changing regular enrollment',function(){
    $this->postJson('/academic-years/1/finish-summer')->assertOk()->assertJsonPath('data.lifecycle_status','finished')->assertJsonPath('data.status',0);
    expect(AcademicYear::count())->toBe(3)->and(AcademicYear::find(2)->lifecycle_status)->toBe('started');
    expect(DB::table('tb_student_enrollment')->orderBy('id')->pluck('enrollment_status')->all())->toBe(['completed','completed','completed','withdrawn','graduated','active']);
    expect(AcademicYear::find(1)->end_date->format('Y-m-d'))->toBe('2026-08-31');
    $this->postJson('/academic-years/1/finish-summer')->assertOk();
});
it('can close a past pending summer but rejects regular and archived periods',function(){
    $this->postJson('/academic-years/3/finish-summer')->assertOk();
    $this->postJson('/academic-years/2/finish-summer')->assertUnprocessable();
    AcademicYear::find(1)->update(['lifecycle_status'=>'archived']);
    $this->postJson('/academic-years/1/finish-summer')->assertUnprocessable();
    expect(DB::table('tb_student_enrollment')->where('id',1)->value('enrollment_status'))->toBe('active');
});
it('requires academic year management permission',function(){
    $this->manager->canManage=false;
    $this->postJson('/academic-years/1/finish-summer')->assertForbidden();
    expect(AcademicYear::find(1)->lifecycle_status)->toBe('started');
});
it('does not finish deleted summer periods',function(){
    AcademicYear::find(1)->delete();$this->postJson('/academic-years/1/finish-summer')->assertNotFound();
});
