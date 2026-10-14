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
