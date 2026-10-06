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

it('loads Khmer parent occupations for the transcript preview and both printed levels', function ($useLookup) {
    Schema::create('tb_occupation', function (Blueprint $table) {
        $table->id(); $table->string('occupation_name_en'); $table->string('occupation_name_kh'); $table->softDeletes();
    });
    Schema::create('tb_family_member', function (Blueprint $table) {
        $table->id(); $table->integer('occupation_id')->nullable(); $table->string('relationship_type');
        foreach (['full_name_en', 'full_name_kh', 'phone', 'email', 'occupation', 'occupation_en', 'occupation_kh', 'workplace', 'nationality_en', 'nationality_kh'] as $column) $table->string($column)->nullable();
        $table->softDeletes();
    });
    Schema::create('tb_student_family_member', function (Blueprint $table) {
        $table->integer('student_id'); $table->integer('family_member_id'); $table->string('relationship_type');
        $table->boolean('is_primary_contact')->default(false); $table->timestamps();
    });
    DB::table('tb_occupation')->insert([
        ['id' => 1, 'occupation_name_en' => 'Teacher', 'occupation_name_kh' => 'គ្រូបង្រៀន', 'deleted_at' => null],
        ['id' => 2, 'occupation_name_en' => 'Civil servant', 'occupation_name_kh' => 'មន្ត្រីរាជការ', 'deleted_at' => now()],
    ]);
    foreach (['mother' => 1, 'father' => 2] as $relationship => $id) {
        DB::table('tb_family_member')->insert(['id' => $id, 'relationship_type' => $relationship,
            'full_name_kh' => 'ឪពុកម្តាយ', 'occupation_id' => $useLookup ? $id : null,
            'occupation_kh' => $useLookup ? null : ($id === 1 ? 'គ្រូបង្រៀន' : 'មន្ត្រីរាជការ')]);
        DB::table('tb_student_family_member')->insert(['student_id' => 1, 'family_member_id' => $id, 'relationship_type' => $relationship]);
    }
    $query = (new ReflectionMethod($this->reportsController, 'enrollments'))->invoke($this->reportsController,
        $this->reportRequest, $this->reportFilters + ['transcript_student_id' => 1], 'moeys-sikkhakarik-book');
    $loads = $query->getEagerLoads();
    // Keep the report's actual parent projection and lookup loading. Other report
    // relations are outside this fixture's minimal student-selection schema.
    $query->setEagerLoads(['student' => fn ($student) => $student->select('id', 'full_name_en', 'full_name_kh'),
        'student.familyMembers' => $loads['student.familyMembers'],
        'student.familyMembers.occupationRecord' => $loads['student.familyMembers.occupationRecord']]);
    DB::enableQueryLog();
    $rows = $query->get();
    $lookupQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'tb_occupation'));
    DB::disableQueryLog();
    expect($lookupQueries)->toHaveCount($useLookup ? 1 : 0);
    foreach ($rows as $row) foreach (['campus', 'academicYear', 'grade', 'schoolClass'] as $relation) $row->setRelation($relation, null);
    $preview = view('reports._moeys-sikkhakarik-book', ['enrollments' => $rows, 'filters' => ['transcript_level' => 'primary'], 'hasDataFilter' => true])->render();
    expect($preview)->toContain('គ្រូបង្រៀន', 'មន្ត្រីរាជការ');
    foreach (['primary', 'secondary'] as $level) {
        $html = view('reports._moeys-transcript-template-book', ['enrollments' => $rows,
            'filters' => ['transcript_level' => $level, 'print_mode' => 'content', 'report_date' => '2026-10-06']])->render();
        expect($html)->toContain('transcript-content-mother-occupation">គ្រូបង្រៀន</div>',
            'transcript-content-father-occupation">មន្ត្រីរាជការ</div>');
    }
})->with(['selected occupation including retired lookup' => true, 'legacy Khmer text' => false]);
