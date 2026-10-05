<?php

use App\Http\Controllers\ReportsController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // These fixtures use the test runner's isolated, in-memory SQLite database.
    Schema::create('tb_student', function (Blueprint $table) {
        $table->id(); $table->string('student_id'); $table->string('full_name_kh');
        $table->string('full_name_en'); $table->integer('status');
    });
    Schema::create('tb_grade', function (Blueprint $table) {
        $table->id(); $table->string('grade'); $table->string('grade_short_name'); $table->integer('grade_order');
        $table->softDeletes();
    });
    Schema::create('tb_class', function (Blueprint $table) {
        $table->id(); $table->string('class_name'); $table->integer('class_order');
    });
    Schema::create('tb_school_info', fn (Blueprint $table) => $table->id());
    Schema::create('tb_student_enrollment', function (Blueprint $table) {
        $table->id();
        foreach (['student_id', 'academic_year_id', 'campus_id', 'grade_id', 'class_id', 'session_id', 'status'] as $column) $table->integer($column);
        $table->string('enrollment_status');
    });
    DB::table('tb_grade')->insert([
        ['id' => 4, 'grade' => 'Grade 4', 'grade_short_name' => '4', 'grade_order' => 4],
        ['id' => 7, 'grade' => 'Grade 7', 'grade_short_name' => '7', 'grade_order' => 7],
    ]);
    DB::table('tb_class')->insert(['id' => 1, 'class_name' => 'A', 'class_order' => 1]);
    DB::table('tb_school_info')->insert([['id' => 1], ['id' => 2]]);
    foreach (range(1, 8) as $id) {
        DB::table('tb_student')->insert(['id' => $id, 'student_id' => 'ID-' . $id, 'full_name_kh' => 'សិស្ស ' . $id, 'full_name_en' => 'STUDENT ' . $id, 'status' => 1]);
        DB::table('tb_student_enrollment')->insert([
            'id' => $id, 'student_id' => $id, 'academic_year_id' => $id === 6 ? 2 : 1,
            'campus_id' => $id === 3 ? 2 : 1, 'grade_id' => $id === 4 ? 7 : 4,
            'class_id' => 1, 'session_id' => $id === 5 ? 2 : 1,
            'status' => $id === 7 ? 0 : 1,
            'enrollment_status' => $id === 8 ? 'withdrawn' : ($id === 2 ? 'completed' : 'active'),
        ]);
    }
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn () => DB::table('tb_school_info')->where('id', 1));
    $this->reportRequest = Request::create('/reports/moeys-sikkhakarik-book');
    $this->reportRequest->setUserResolver(fn () => $user);
    $this->reportFilters = ['academic_year_id' => 1, 'campus_id' => 1, 'transcript_level' => 'primary', 'grade_id' => 4, 'class_id' => 1, 'session_id' => 1];
    $this->reportsController = new ReportsController;
});

it('prints only the selected student and keeps all students as the default', function () {
    $method = new ReflectionMethod($this->reportsController, 'enrollments');
    $all = $method->invoke($this->reportsController, $this->reportRequest, $this->reportFilters, 'moeys-sikkhakarik-book')->withoutEagerLoads()->pluck('tb_student_enrollment.student_id')->all();
    $one = $method->invoke($this->reportsController, $this->reportRequest, $this->reportFilters + ['transcript_student_id' => 2], 'moeys-sikkhakarik-book')->withoutEagerLoads()->pluck('tb_student_enrollment.student_id')->all();
    expect($all)->toBe([1, 2])->and($one)->toBe([2]);
});

it('does not allow a student selection to bypass class, year, group, or campus access', function ($studentId) {
    $filters = $this->reportFilters + ['transcript_student_id' => $studentId];
    if ($studentId === 3) unset($filters['campus_id']);
    $query = (new ReflectionMethod($this->reportsController, 'enrollments'))->invoke($this->reportsController, $this->reportRequest, $filters, 'moeys-sikkhakarik-book');
    expect($query->withoutEagerLoads()->count())->toBe(0);
})->with([3, 4, 5, 6, 7, 8, 999]);

it('keeps the full eligible student dropdown after selecting one student', function () {
    $options = (new ReflectionMethod($this->reportsController, 'transcriptStudentOptions'))->invoke($this->reportsController, $this->reportRequest, $this->reportFilters + ['transcript_student_id' => 2]);
    expect($options->pluck('id')->all())->toBe([1, 2]);
    $html = view('reports._transcript-student-filter', ['filters' => ['transcript_student_id' => 2], 'transcriptStudentOptions' => $options])->render();
    expect($html)->toContain('All Students', 'STUDENT 1', 'STUDENT 2', 'ID-1', 'ID-2', 'value="2"')
        ->not->toContain('STUDENT 3', 'STUDENT 4');
});

it('does not change other report selections', function () {
    $query = (new ReflectionMethod($this->reportsController, 'enrollments'))->invoke($this->reportsController, $this->reportRequest, $this->reportFilters + ['transcript_student_id' => 2], 'student-id-books-moeys');
    expect($query->withoutEagerLoads()->pluck('tb_student_enrollment.student_id')->all())->toBe([1, 2]);
});
