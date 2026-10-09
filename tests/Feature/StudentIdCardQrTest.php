<?php

use App\Http\Controllers\StudentIdCardQrController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('tb_academic_year', function (Blueprint $table) {
        $table->id();
        $table->string('academic_year');
        $table->string('period_type')->default('regular');
        $table->softDeletes();
    });
    Schema::create('tb_school_info', function (Blueprint $table) {
        $table->id();
        $table->string('campus_name_en');
        $table->string('campus_name_kh')->nullable();
        $table->softDeletes();
    });
    Schema::create('tb_grade', function (Blueprint $table) {
        $table->id();
        $table->string('grade');
        $table->string('grade_short_name')->nullable();
        $table->integer('grade_order');
        $table->softDeletes();
    });
    Schema::create('tb_class', function (Blueprint $table) {
        $table->id();
        $table->string('class_name');
        $table->integer('class_order')->default(1);
        $table->softDeletes();
    });
    Schema::create('tb_student', function (Blueprint $table) {
        $table->id();
        foreach (['student_id', 'student_no', 'full_name_en', 'full_name_kh', 'photo_path', 'home_phone'] as $column) {
            $table->string($column)->nullable();
        }
    });
    Schema::create('tb_student_enrollment', function (Blueprint $table) {
        $table->id();
        foreach (['student_id', 'academic_year_id', 'campus_id', 'grade_id', 'class_id'] as $column) {
            $table->integer($column);
        }
    });

    DB::table('tb_academic_year')->insert([
        ['id' => 1, 'academic_year' => '2025-2026'],
        ['id' => 2, 'academic_year' => '2026-2027'],
    ]);
    DB::table('tb_school_info')->insert([
        ['id' => 1, 'campus_name_en' => 'BCH'],
        ['id' => 2, 'campus_name_en' => 'BKK'],
    ]);
    DB::table('tb_class')->insert(['id' => 1, 'class_name' => 'A']);
    foreach (['N-', 'K1-', 'K2-', 'K3-', '1', '2', '3'] as $index => $code) {
        $id = $index + 1;
        DB::table('tb_grade')->insert([
            'id' => $id, 'grade' => $code, 'grade_short_name' => $code, 'grade_order' => $id,
        ]);
        DB::table('tb_student')->insert(['id' => $id, 'student_id' => 'TEST-' . $id, 'full_name_en' => 'STUDENT ' . $id]);
        DB::table('tb_student_enrollment')->insert([
            'student_id' => $id, 'academic_year_id' => 1, 'campus_id' => 1, 'grade_id' => $id, 'class_id' => 1,
        ]);
    }
});

it('lists only regular academic years with enrollments', function () {
    DB::table('tb_academic_year')->insert([
        'id' => 3, 'academic_year' => 'Summer 2025-2026', 'period_type' => 'summer',
    ]);
    foreach ([2, 3] as $yearId) {
        DB::table('tb_student_enrollment')->insert([
            'student_id' => 1, 'academic_year_id' => $yearId, 'campus_id' => 1, 'grade_id' => 1, 'class_id' => 1,
        ]);
    }
    $data = (new StudentIdCardQrController)->index(Request::create('/students/id-card-qr'))->getData();

    expect($data['academicYears']->pluck('id')->all())->toBe([2, 1])
        ->and($data['academicYears']->pluck('period_type')->unique()->all())->toBe(['regular']);
});

it('keeps nursery and kindergarten distinct from numbered grades and lists each enrolled pair once', function () {
    DB::table('tb_student_enrollment')->insert([
        'student_id' => 1, 'academic_year_id' => 1, 'campus_id' => 1, 'grade_id' => 1, 'class_id' => 1,
    ]);
    $data = (new StudentIdCardQrController)->index(Request::create('/students/id-card-qr', 'GET', [
        'academic_year_id' => 1, 'campus_id' => 1,
    ]))->getData();

    expect($data['classes']->pluck('label')->all())->toBe(['N-A', 'K1-A', 'K2-A', 'K3-A', '1A', '2A', '3A']);
});

it('filters kindergarten students by the original grade and class IDs', function () {
    $data = (new StudentIdCardQrController)->index(Request::create('/students/id-card-qr', 'GET', [
        'academic_year_id' => 1, 'campus_id' => 1, 'grade_class' => '2:1',
    ]))->getData();

    expect($data['students']->pluck('student_id')->all())->toBe(['TEST-2'])
        ->and($data['selectedStudent']->student_id)->toBe('TEST-2')
        ->and($data['classes']->pluck('label')->all())->toContain('K1-A', '1A');
});

it('keeps grade options scoped to the selected year and campus', function () {
    DB::table('tb_student_enrollment')->insert([
        'student_id' => 1, 'academic_year_id' => 2, 'campus_id' => 2, 'grade_id' => 1, 'class_id' => 1,
    ]);
    $controller = new StudentIdCardQrController;
    $selected = $controller->index(Request::create('/students/id-card-qr', 'GET', [
        'academic_year_id' => 2, 'campus_id' => 2,
    ]))->getData();
    $empty = $controller->index(Request::create('/students/id-card-qr', 'GET', [
        'academic_year_id' => 2, 'campus_id' => 1,
    ]))->getData();

    expect($selected['classes']->pluck('label')->all())->toBe(['N-A'])
        ->and($empty['classes'])->toBeEmpty();
});

it('avoids repeating a grade already included in a class name', function (string $code, string $class, string $expected) {
    DB::table('tb_grade')->where('id', 1)->update(['grade' => $code, 'grade_short_name' => $code]);
    DB::table('tb_class')->where('id', 1)->update(['class_name' => $class]);
    $data = (new StudentIdCardQrController)->index(Request::create('/students/id-card-qr', 'GET', [
        'academic_year_id' => 1, 'campus_id' => 1,
    ]))->getData();

    expect($data['classes']->first()->label)->toBe($expected);
})->with([
    ['K1-', 'K1-A', 'K1-A'],
    ['N-', 'N-A', 'N-A'],
    ['1', '1A', '1A'],
    ['G10', 'A', '10A'],
    ['Grade 1', 'A', '1A'],
    ['Nursery', 'A', 'N-A'],
]);
