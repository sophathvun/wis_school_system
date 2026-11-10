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

it('returns zero male and female counts for an empty dashboard scope', function () {
    $counts = (new ReflectionMethod(DashboardController::class, 'enrolledStudentsGenderCounts'))->invoke(
        new DashboardController(), StudentEnrollment::whereIn('campus_id', [])
    );
    expect((int) $counts->male)->toBe(0)
        ->and((int) $counts->female)->toBe(0)
        ->and((int) $counts->active_male)->toBe(0)
        ->and((int) $counts->active_female)->toBe(0);
});

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
