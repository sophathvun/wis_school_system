<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->withoutMiddleware();
    Schema::create('tb_student', function (Blueprint $table) {
        $table->id();
        $table->string('student_id');
        $table->string('family_number')->nullable();
        $table->string('full_name_en')->nullable();
        $table->string('full_name_kh')->nullable();
    });
    for ($id = 1; $id <= 90; $id++) {
        DB::table('tb_student')->insert([
            'student_id' => 'ID'.$id, 'family_number' => sprintf('F%03d', $id),
            'full_name_en' => sprintf('STUDENT %03d', $id), 'full_name_kh' => $id === 80 ? 'សុខ ដារា' : null,
        ]);
    }
    DB::table('tb_student')->insert([
        ['student_id' => 'SIBLING80', 'family_number' => 'F080', 'full_name_en' => 'SIBLING LATE', 'full_name_kh' => null],
        ['student_id' => 'EMPTY', 'family_number' => '', 'full_name_en' => 'NO FAMILY', 'full_name_kh' => null],
        ['student_id' => 'NULL', 'family_number' => null, 'full_name_en' => 'NO FAMILY', 'full_name_kh' => null],
    ]);
});

it('sends only a small first batch rather than the entire family list', function () {
    $response = $this->getJson('/student-enrollments/quick-options')->assertOk()
        ->assertJsonCount(50, 'families')->assertJsonPath('hasMore', true)
        ->assertJsonPath('families.0.family_number', 'F001')
        ->assertJsonPath('families.49.family_number', 'F050');
    expect(strlen($response->getContent()))->toBeLessThan(5000);
});

it('finds families beyond the first batch by family number, student ID and either student name', function (string $query) {
    $response = $this->getJson('/student-enrollments/quick-options?q='.urlencode($query))->assertOk()
        ->assertJsonCount(1, 'families')->assertJsonPath('hasMore', false)
        ->assertJsonPath('families.0.family_number', 'F080');
    // Matching a sibling still returns the same representative family label.
    expect($response->json('families.0.full_name_en'))->toBe('SIBLING LATE');
})->with(['F080', 'ID80', 'SIBLING80', 'student 080', 'SIBLING LATE', 'សុខ ដារា']);

it('does not include empty family numbers or duplicate sibling families', function () {
    $this->getJson('/student-enrollments/quick-options?q=NO%20FAMILY')->assertOk()
        ->assertJsonCount(0, 'families')->assertJsonPath('hasMore', false);
    $this->getJson('/student-enrollments/quick-options?q=F08')->assertOk()
        ->assertJsonCount(10, 'families');
});
