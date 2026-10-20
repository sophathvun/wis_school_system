<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->withoutMiddleware();
    $this->actingAs(new class extends \App\Models\User {
        public function isSuperAdmin(): bool { return true; }
    });
    Schema::create('tb_academic_year', function (Blueprint $t) {
        $t->id(); $t->string('academic_year'); $t->string('period_type')->default('regular');
        $t->string('lifecycle_status')->default('ended'); $t->softDeletes();
    });
    Schema::create('tb_student', function (Blueprint $t) {
        $t->id(); foreach (['student_id','student_no','full_name_en','full_name_kh','family_number','gender'] as $c) $t->string($c)->nullable();
    });
    foreach (['school_info'=>['campus_name_en','campus_name_kh'], 'grade'=>['grade','grade_order'],
        'class'=>['class_name','class_order'], 'academic_track'=>['name_en'], 'session'=>['session_short_name','session_order'], 'group'=>[]] as $table=>$columns) {
        Schema::create('tb_'.$table, function (Blueprint $t) use ($columns) {
            $t->id(); foreach ($columns as $c) $t->string($c)->nullable(); $t->softDeletes();
        });
    }
    Schema::create('tb_family', function (Blueprint $t) { $t->id(); $t->softDeletes(); });
    Schema::create('tb_family_student', function (Blueprint $t) { $t->integer('family_id'); $t->integer('student_id'); $t->string('relationship_type')->nullable(); $t->boolean('is_primary_contact')->default(false); $t->boolean('has_pickup_authorization')->default(false); $t->boolean('has_portal_access')->default(false); $t->timestamps(); });
    Schema::create('tb_student_enrollment', function (Blueprint $t) {
        $t->id(); foreach (['student_id','academic_year_id','campus_id','grade_id','class_id','session_id','academic_track_id','group_id'] as $c) $t->integer($c)->nullable();
        $t->string('enrollment_status')->default('active'); $t->string('student_type')->default('old');
    });
    Schema::create('tb_student_enrollment_workflow', function (Blueprint $t) { $t->id(); $t->integer('target_enrollment_id'); $t->integer('from_campus_id'); $t->integer('to_campus_id'); $t->string('action_type'); });
    DB::table('tb_academic_year')->insert([
        ['id'=>1,'academic_year'=>'2022-2023','lifecycle_status'=>'ended'],
        ['id'=>2,'academic_year'=>'2023-2024','lifecycle_status'=>'ended'],
        ['id'=>3,'academic_year'=>'2024-2025','lifecycle_status'=>'ended'],
        ['id'=>4,'academic_year'=>'2025-2026','lifecycle_status'=>'started'],
        ['id'=>5,'academic_year'=>'2026-2027','lifecycle_status'=>'pending'],
    ]);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'BCH'],['id'=>2,'campus_name_en'=>'BKK']]);
    DB::table('tb_grade')->insert(['id'=>1,'grade'=>'Grade 1','grade_order'=>'1']);
    DB::table('tb_class')->insert(['id'=>1,'class_name'=>'A','class_order'=>'1']);
    DB::table('tb_session')->insert(['id'=>1,'session_short_name'=>'AM','session_order'=>'1']);
    foreach ([1=>'MATCH CURRENT',2=>'MATCH FORMER',3=>'OTHER CURRENT'] as $id=>$name) DB::table('tb_student')->insert(['id'=>$id,'student_id'=>'ID'.$id,'full_name_en'=>$name,'gender'=>'Female']);
    $this->addRow = fn ($id,$student,$year,$extra=[]) => DB::table('tb_student_enrollment')->insert(array_replace([
        'id'=>$id,'student_id'=>$student,'academic_year_id'=>$year,'campus_id'=>1,'grade_id'=>1,'class_id'=>1,'session_id'=>1,
    ],$extra));
    foreach ([1,2,3,4,5] as $year) ($this->addRow)($year,1,$year);
    ($this->addRow)(10,2,3);
    ($this->addRow)(11,2,1); // Imported later, but chronologically older.
    ($this->addRow)(12,3,4);
});

it('opens with only the started regular academic year and one row per student', function () {
    $this->getJson('/student-enrollments/fetch')->assertOk()->assertJsonPath('total',2)
        ->assertJsonPath('data.0.id',4)->assertJsonPath('data.1.id',12);
    $this->getJson('/student-enrollments/stats')->assertOk()->assertJsonPath('total.count',2);
});

it('searches once per student using the active year then their latest academic year', function () {
    $response=$this->getJson('/student-enrollments/fetch?search=MATCH')->assertOk()->assertJsonPath('total',2);
    expect(collect($response->json('data'))->pluck('id')->sort()->values()->all())->toBe([4,10]);
    $this->getJson('/student-enrollments/stats?search=MATCH')->assertOk()->assertJsonPath('total.count',2);
    $this->getJson('/student-enrollments/fetch?search=ID2')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.id',10);
    $this->getJson('/student-enrollments/fetch?student_id=2')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.id',10);
});

it('keeps explicit historical year filters available without repeating students', function () {
    $this->getJson('/student-enrollments/fetch?academic_year_id=1&search=MATCH')->assertOk()->assertJsonPath('total',2);
    $this->getJson('/student-enrollments/fetch?academic_year_id=4&search=ID2')->assertOk()->assertJsonPath('total',0);
});

it('selects the active transfer placement before applying campus filters', function () {
    ($this->addRow)(13,1,4,['campus_id'=>2,'enrollment_status'=>'transferred']);
    $this->getJson('/student-enrollments/fetch?search=ID1')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.id',4);
    $this->getJson('/student-enrollments/fetch?search=ID1&campus_id=2')->assertOk()->assertJsonPath('total',0);
});

it('deduplicates before pagination and exposes former students in the student picker', function () {
    $a=$this->getJson('/student-enrollments/fetch?search=MATCH&perPage=1&page=1')->assertOk()->assertJsonPath('total',2);
    $b=$this->getJson('/student-enrollments/fetch?search=MATCH&perPage=1&page=2')->assertOk()->assertJsonPath('total',2);
    expect($a->json('data.0.student_id'))->not->toBe($b->json('data.0.student_id'));
    $options=$this->getJson('/student-enrollments/filter-options')->assertOk();
    expect(collect($options->json('students'))->pluck('student_record_id')->all())->toContain(2);
});

it('does not open a planning year when no academic year is active', function () {
    DB::table('tb_academic_year')->where('id',4)->update(['lifecycle_status'=>'ended']);
    $this->getJson('/student-enrollments/fetch')->assertOk()->assertJsonPath('total',0);
    $this->getJson('/student-enrollments/fetch?search=ID2')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.id',10);
});

it('keeps statistics consistent for campus searches and does not fall back to an older status', function () {
    $this->getJson('/student-enrollments/fetch?search=BCH')->assertOk()->assertJsonPath('total',3);
    $this->getJson('/student-enrollments/stats?search=BCH')->assertOk()->assertJsonPath('total.count',3);
    DB::table('tb_student_enrollment')->where('id',4)->update(['enrollment_status'=>'withdrawn']);
    $this->getJson('/student-enrollments/fetch?search=ID1&enrollment_status=active')->assertOk()->assertJsonPath('total',0);
    $this->getJson('/student-enrollments/fetch?search=ID1&enrollment_status=withdrawn')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.id',4);
});

it('ignores an active summer period when choosing the regular active year', function () {
    DB::table('tb_academic_year')->insert(['id'=>6,'academic_year'=>'2026 Summer','period_type'=>'summer','lifecycle_status'=>'started']);
    ($this->addRow)(20,1,6);
    $this->getJson('/student-enrollments/fetch')->assertOk()->assertJsonPath('total',2)->assertJsonPath('data.0.id',4);
});

it('searches by Khmer name and family number and returns no rows for an unknown name', function () {
    DB::table('tb_student')->where('id',2)->update(['full_name_kh'=>'សុខ ដារ៉ា','family_number'=>'FAMILY002']);
    foreach (['សុខ ដារ៉ា','FAMILY002',' MATCH FORMER '] as $term) {
        $this->getJson('/student-enrollments/fetch?search='.urlencode($term))->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.id',10);
        $this->getJson('/student-enrollments/stats?search='.urlencode($term))->assertOk()->assertJsonPath('total.count',1);
    }
    $this->getJson('/student-enrollments/fetch?search=NOT_FOUND_STUDENT')->assertOk()->assertJsonPath('total',0);
});
