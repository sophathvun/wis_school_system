<?php

use App\Http\Controllers\DashboardController;
use App\Models\StudentEnrollment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('tb_student', function (Blueprint $table) {
        $table->id(); $table->string('gender')->nullable(); $table->string('gender_kh')->nullable();
    });
    Schema::create('tb_student_enrollment', function (Blueprint $table) {
        $table->id(); $table->integer('student_id'); $table->integer('campus_id');
        $table->integer('academic_year_id'); $table->string('enrollment_status');
    });
});

it('counts distinct active male and female students within the dashboard year and campus scope', function () {
    DB::table('tb_student')->insert([
        ['id' => 1, 'gender' => 'Male', 'gender_kh' => null],
        ['id' => 2, 'gender' => 'f', 'gender_kh' => null],
        ['id' => 3, 'gender' => null, 'gender_kh' => 'ប្រុស'],
        ['id' => 4, 'gender' => null, 'gender_kh' => 'ស្រី'],
        ['id' => 5, 'gender' => 'male', 'gender_kh' => null],
        ['id' => 6, 'gender' => 'female', 'gender_kh' => null],
        ['id' => 7, 'gender' => 'male', 'gender_kh' => null],
        ['id' => 8, 'gender' => 'female', 'gender_kh' => null],
    ]);
    foreach ([1, 2, 3, 4, 1] as $studentId) {
        DB::table('tb_student_enrollment')->insert(['student_id' => $studentId, 'campus_id' => 1, 'academic_year_id' => 10, 'enrollment_status' => 'active']);
    }
    DB::table('tb_student_enrollment')->insert([
        ['student_id' => 5, 'campus_id' => 1, 'academic_year_id' => 10, 'enrollment_status' => 'withdrawn'],
        ['student_id' => 6, 'campus_id' => 1, 'academic_year_id' => 10, 'enrollment_status' => 'completed'],
        ['student_id' => 7, 'campus_id' => 2, 'academic_year_id' => 10, 'enrollment_status' => 'active'],
        ['student_id' => 8, 'campus_id' => 1, 'academic_year_id' => 11, 'enrollment_status' => 'active'],
    ]);
    $scope = StudentEnrollment::where('academic_year_id', 10)->whereIn('campus_id', [1]);
    $counts = (new ReflectionMethod(DashboardController::class, 'enrolledStudentsGenderCounts'))->invoke(new DashboardController(), $scope);
    expect((int) $counts->male)->toBe(3)
        ->and((int) $counts->female)->toBe(3)
        ->and((int) $counts->active_male)->toBe(2)
        ->and((int) $counts->active_female)->toBe(2)
        ->and($scope->count())->toBe(7);
});

it('shows historical completion outcomes despite later withdrawal and excludes other years and campuses', function () {
    DB::table('tb_student')->insert([
        ['id'=>1,'gender'=>'male'], ['id'=>2,'gender'=>'female'],
        ['id'=>3,'gender'=>'female'], ['id'=>4,'gender'=>'male'],
        ['id'=>5,'gender'=>'male'], ['id'=>6,'gender'=>null],
    ]);
    $base = ['campus_id'=>1,'academic_year_id'=>10,'enrollment_status'=>'completed'];
    foreach ([
        ['student_id'=>1], ['student_id'=>1],
        ['student_id'=>1,'academic_year_id'=>11,'enrollment_status'=>'withdrawn'],
        ['student_id'=>2,'enrollment_status'=>'graduated'],
        ['student_id'=>3,'enrollment_status'=>'withdrawn'],
        ['student_id'=>4,'campus_id'=>2],
        ['student_id'=>5,'enrollment_status'=>'transferred'],
        ['student_id'=>6,'enrollment_status'=>'finished'],
    ] as $row) DB::table('tb_student_enrollment')->insert(array_replace($base,$row));
    $scope = StudentEnrollment::where('academic_year_id',10)->whereIn('campus_id',[1]);
    $method = new ReflectionMethod(DashboardController::class,'activeOrCompletedStudentsMetric');
    $metric = $method->invoke(new DashboardController(),$scope,true);
    expect($metric)->toMatchArray(['label'=>'Completed the Year','value'=>'3','male'=>1,'female'=>1])
        ->and($scope->count())->toBe(6);
    $empty = $method->invoke(new DashboardController(),StudentEnrollment::whereIn('campus_id',[]),true);
    expect($empty)->toMatchArray(['value'=>'0','male'=>0,'female'=>0]);
});

it('keeps the active year card limited to active enrollments', function () {
    DB::table('tb_student')->insert([['id'=>1,'gender'=>'female'],['id'=>2,'gender'=>'male']]);
    foreach (['active','completed','withdrawn'] as $status) {
        DB::table('tb_student_enrollment')->insert(['student_id'=>$status==='active'?1:2,'campus_id'=>1,'academic_year_id'=>10,'enrollment_status'=>$status]);
    }
    $metric = (new ReflectionMethod(DashboardController::class,'activeOrCompletedStudentsMetric'))->invoke(
        new DashboardController(),StudentEnrollment::where('academic_year_id',10),false
    );
    expect($metric)->toMatchArray(['label'=>'Active Students','value'=>'1','male'=>0,'female'=>1]);
});

it('returns zero male and female counts for an empty dashboard scope', function () {
    $counts = (new ReflectionMethod(DashboardController::class, 'enrolledStudentsGenderCounts'))->invoke(
        new DashboardController(), StudentEnrollment::whereIn('campus_id', [])
    );
    expect((int) $counts->male)->toBe(0)
        ->and((int) $counts->female)->toBe(0)
        ->and((int) $counts->active_male)->toBe(0)
        ->and((int) $counts->active_female)->toBe(0);
});

it('excludes withdrawn enrollments from campus grade tables and grade charts for current and completed years', function ($status) {
    Schema::table('tb_student_enrollment',fn(Blueprint $t)=>$t->integer('grade_id')->nullable());
    Schema::create('tb_grade',function(Blueprint $t){
        $t->id();$t->string('grade');$t->string('grade_short_name');$t->integer('grade_order');
        $t->boolean('status')->default(true);$t->softDeletes();
    });
    Schema::create('tb_school_info',function(Blueprint $t){$t->id();$t->string('campus_name_en');});
    DB::table('tb_grade')->insert([
        ['id'=>1,'grade'=>'Grade 1','grade_short_name'=>'1','grade_order'=>1],
        ['id'=>2,'grade'=>'Grade 2','grade_short_name'=>'2','grade_order'=>2],
    ]);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'BCH'],['id'=>2,'campus_name_en'=>'BKK']]);
    foreach([1=>'Male',2=>'Female',3=>'Female',4=>'Female',5=>'Male'] as $id=>$gender)
        DB::table('tb_student')->insert(['id'=>$id,'gender'=>$gender]);
    $base=['campus_id'=>1,'academic_year_id'=>10,'grade_id'=>1,'enrollment_status'=>$status];
    foreach([
        ['student_id'=>1],['student_id'=>1],['student_id'=>2],
        ['student_id'=>3,'grade_id'=>2,'enrollment_status'=>'withdrawn'],
        ['student_id'=>4,'campus_id'=>2,'grade_id'=>2],
        ['student_id'=>5,'campus_id'=>2,'enrollment_status'=>'withdrawn'],
        ['student_id'=>1,'academic_year_id'=>11,'enrollment_status'=>'withdrawn'],
    ] as $row)DB::table('tb_student_enrollment')->insert(array_replace($base,$row));
    $scope=StudentEnrollment::where('academic_year_id',10);
    $method=new ReflectionMethod(DashboardController::class,'studentStatisticsByCampusGrade');
    $table=$method->invoke(new DashboardController(),$scope);
    expect($table['rows'])->toBe([
        ['campus'=>'BCH','grades'=>[['total'=>2,'female'=>1],['total'=>0,'female'=>0]],'total'=>2,'female'=>1],
        ['campus'=>'BKK','grades'=>[['total'=>0,'female'=>0],['total'=>1,'female'=>1]],'total'=>1,'female'=>1],
    ])->and($table['grand_grades'])->toBe([['total'=>2,'female'=>1],['total'=>1,'female'=>1]])
        ->and($table['grand_total'])->toBe(['total'=>3,'female'=>2])
        ->and($scope->count())->toBe(6);
    $campus=$method->invoke(new DashboardController(),(clone $scope)->where('campus_id',2));
    expect($campus['grand_total'])->toBe(['total'=>1,'female'=>1]);
    $chartMethod=new ReflectionMethod(DashboardController::class,'studentsByGradeChart');
    $chart=collect($chartMethod->invoke(new DashboardController(),$scope))->keyBy('label');
    expect($chart['1'])->toMatchArray(['value'=>2,'secondary_value'=>1])
        ->and($chart['2'])->toMatchArray(['value'=>1,'secondary_value'=>1])
        ->and($chart['Total'])->toMatchArray(['value'=>3,'secondary_value'=>2]);
    $campusChart=collect($chartMethod->invoke(new DashboardController(),(clone $scope)->where('campus_id',2)))->keyBy('label');
    expect($campusChart['1'])->toMatchArray(['value'=>0,'secondary_value'=>0])
        ->and($campusChart['Total'])->toMatchArray(['value'=>1,'secondary_value'=>1])
        ->and($scope->count())->toBe(6);
})->with(['active','completed']);

it('counts only confirmed graduates while pending results and cancellations stay outside graduation totals',function(){
    Schema::create('tb_student_graduation',function(Blueprint $t){$t->id();$t->integer('student_id');$t->integer('academic_year_id');$t->integer('campus_id');$t->string('status');});
    DB::table('tb_student_graduation')->insert([
        ['student_id'=>1,'academic_year_id'=>10,'campus_id'=>1,'status'=>'pending_result'],
        ['student_id'=>2,'academic_year_id'=>10,'campus_id'=>1,'status'=>'completed'],
        ['student_id'=>3,'academic_year_id'=>10,'campus_id'=>1,'status'=>'cancelled'],
        ['student_id'=>4,'academic_year_id'=>11,'campus_id'=>1,'status'=>'completed'],
        ['student_id'=>5,'academic_year_id'=>10,'campus_id'=>2,'status'=>'completed'],
    ]);
    $count=(new ReflectionMethod(DashboardController::class,'graduatedStudentsCount'))->invoke(new DashboardController(),10,null,[1]);
    expect($count)->toBe(1);
});

it('includes completed and withdrawn student tracks within the selected year and campus without double counting', function () {
    DB::connection()->getPdo()->sqliteCreateFunction('FIELD', function ($value, ...$options) {
        $index = array_search($value, $options, true);
        return $index === false ? 0 : $index + 1;
    });
    Schema::table('tb_student_enrollment', fn (Blueprint $table) => $table->integer('academic_track_id')->nullable());
    Schema::create('tb_academic_track', function (Blueprint $table) {
        $table->id(); $table->string('name_en'); $table->string('stream_type');
        $table->string('language'); $table->boolean('status')->default(true); $table->softDeletes();
    });
    DB::table('tb_academic_track')->insert([
        ['id'=>1, 'name_en'=>'Science - Khmer', 'stream_type'=>'science', 'language'=>'khmer'],
        ['id'=>2, 'name_en'=>'Science - English', 'stream_type'=>'science', 'language'=>'english'],
    ]);
    foreach ([1=>'Male', 2=>'Female', 3=>'Female', 4=>'Female', 5=>'Male', 6=>'Female'] as $id=>$gender) {
        DB::table('tb_student')->insert(['id'=>$id, 'gender'=>$gender]);
    }
    $base=['campus_id'=>1, 'academic_year_id'=>10, 'enrollment_status'=>'completed', 'academic_track_id'=>1];
    foreach ([
        ['student_id'=>1],
        ['student_id'=>1],
        ['student_id'=>2, 'enrollment_status'=>'active', 'academic_track_id'=>2],
        ['student_id'=>3, 'enrollment_status'=>'withdrawn'],
        ['student_id'=>4, 'campus_id'=>2, 'academic_track_id'=>2],
        ['student_id'=>5, 'academic_year_id'=>11],
        ['student_id'=>6, 'academic_track_id'=>null],
    ] as $row) DB::table('tb_student_enrollment')->insert(array_replace($base, $row));
    $scope=StudentEnrollment::where('academic_year_id', 10)->whereIn('campus_id', [1]);
    $rows=(new ReflectionMethod(DashboardController::class, 'studentsByTrackChart'))->invoke(new DashboardController(), $scope);
    $chart=collect($rows)->keyBy('label');
    expect($chart['Science - Khmer'])->toMatchArray(['value'=>2, 'secondary_value'=>1])
        ->and($chart['Science - English'])->toMatchArray(['value'=>1, 'secondary_value'=>1])
        ->and($chart['Total'])->toMatchArray(['value'=>3, 'secondary_value'=>2])
        ->and($scope->count())->toBe(5);
});
