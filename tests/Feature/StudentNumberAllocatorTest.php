<?php

use App\Services\StudentNumberAllocator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Schema::create('tb_student', function (Blueprint $table) {
        $table->id();
        $table->string('student_no')->unique();
    });
    (require database_path('migrations/2026_10_07_000001_create_student_number_sequence.php'))->up();
    $this->allocator = new StudentNumberAllocator;
});

it('continues after existing and newly imported student numbers', function () {
    DB::table('tb_student')->insert(['student_no' => '00000420']);
    expect(DB::transaction(fn () => $this->allocator->allocate()))->toBe('00000421');
    DB::table('tb_student')->insert(['student_no' => '00000900']);
    expect(DB::transaction(fn () => $this->allocator->allocate()))->toBe('00000901');
});

it('does not reuse previously allocated numbers after a student is removed', function () {
    DB::table('tb_student_number_sequence')->where('id', 1)->update(['last_number' => 500]);
    expect(DB::transaction(fn () => $this->allocator->allocate()))->toBe('00000501');
});

it('requires a transaction so the number lock lasts through enrollment saving', function () {
    expect(fn () => $this->allocator->allocate())->toThrow(\LogicException::class);
    expect(DB::table('tb_student_number_sequence')->value('last_number'))->toBe(0);
});

it('reports the eight digit limit without advancing the sequence', function () {
    DB::table('tb_student_number_sequence')->where('id', 1)->update(['last_number' => 99999999]);
    expect(fn () => DB::transaction(fn () => $this->allocator->allocate()))->toThrow(ValidationException::class);
    expect(DB::table('tb_student_number_sequence')->value('last_number'))->toBe(99999999);
});
