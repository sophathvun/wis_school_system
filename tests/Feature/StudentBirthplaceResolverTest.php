<?php

use App\Models\Student;
use App\Support\StudentBirthplaceResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('tb_student',function (Blueprint $table) {
        $table->id(); $table->timestamps();
        foreach (['country','province','district','commune','village'] as $level) $table->integer('birth_'.$level.'_id')->nullable();
    });
    (require database_path('migrations/2026_10_06_000005_add_legacy_birthplace_text_to_students.php'))->up();
    $parent=null;
    foreach (['country','province','district','commune','village'] as $level) {
        Schema::create('tb_'.$level,function (Blueprint $table) use ($level,$parent) {
            $table->id();$table->string($level.'_name_en')->nullable();$table->string($level.'_name_kh')->nullable();
            if ($parent) $table->integer($parent.'_id');
        });
        $parent=$level;
    }
    DB::table('tb_country')->insert(['id'=>1,'country_name_en'=>'Cambodia','country_name_kh'=>'កម្ពុជា']);
    DB::table('tb_province')->insert([
        ['id'=>1,'country_id'=>1,'province_name_en'=>'Phnom Penh','province_name_kh'=>'ភ្នំពេញ'],
        ['id'=>2,'country_id'=>1,'province_name_en'=>'Kampong Cham','province_name_kh'=>'កំពង់ចាម'],
    ]);
    DB::table('tb_district')->insert(['id'=>1,'province_id'=>1,'district_name_en'=>'Sen Sok','district_name_kh'=>'សែនសុខ']);
    DB::table('tb_commune')->insert(['id'=>1,'district_id'=>1,'commune_name_en'=>'Tuek Thla','commune_name_kh'=>'ទឹកថ្លា']);
    DB::table('tb_village')->insert(['id'=>1,'commune_id'=>1,'village_name_en'=>'Chok Chey','village_name_kh'=>'ជោគជ័យ']);
});

it('maps the legacy country English and province Khmer to one bilingual province record',function () {
    $student=new Student(['birth_province_en'=>'Cambodia','birth_province_kh'=>'រាជធានីភ្នំពេញ']);
    $resolver=new StudentBirthplaceResolver();$changes=$resolver->changes($student);
    expect($changes)->toHaveKeys(['birth_country_id','birth_province_id'])
        ->and($changes['birth_province_en'])->toBe('Phnom Penh')->and($changes['birth_province_kh'])->toBe('ភ្នំពេញ');
    $student->fill($changes);
    expect($resolver->changes($student))->toBe([]);
});

it('links all four birthplace levels in their actual hierarchy',function () {
    $student=new Student(['birth_province_kh'=>'ភ្នំពេញ','birth_district_kh'=>'ខណ្ឌសែនសុខ','birth_commune_kh'=>'សង្កាត់ទឹកថ្លា','birth_village_kh'=>'ភូមិជោគជ័យ']);
    $changes=(new StudentBirthplaceResolver())->changes($student);
    foreach (['province','district','commune','village'] as $level) expect($changes['birth_'.$level.'_id'])->toBe(1);
    expect($changes['birth_village_en'])->toBe('Chok Chey')->and($changes['birth_commune_en'])->toBe('Tuek Thla');
});

it('keeps structured Student Information selections authoritative',function () {
    $student=new Student(['birth_province_id'=>2,'birth_province_kh'=>'ភ្នំពេញ','birth_province_en'=>'Phnom Penh']);
    $changes=(new StudentBirthplaceResolver())->changes($student);
    expect($changes)->not->toHaveKey('birth_province_id')->and($changes['birth_province_en'])->toBe('Kampong Cham');
});

it('does not guess missing or ambiguous subordinate locations',function () {
    DB::table('tb_village')->insert(['id'=>2,'commune_id'=>1,'village_name_en'=>'Chok Chey','village_name_kh'=>'ជោគជ័យ']);
    $student=new Student(['birth_province_id'=>1,'birth_district_id'=>1,'birth_commune_id'=>1,'birth_village_kh'=>'ជោគជ័យ']);
    $changes=(new StudentBirthplaceResolver())->changes($student);
    expect($changes)->not->toHaveKey('birth_village_id');
    expect((new StudentBirthplaceResolver())->changes(new Student()))->toBe([]);
});

it('clears stale imported text only when the saved form selection changes',function () {
    $student=new Student(['birth_province_id'=>1,'birth_district_id'=>1,'birth_village_kh'=>'ភូមិចាស់']);
    expect(StudentBirthplaceResolver::clearTextForSelectionChanges($student,['birth_province_id'=>1]))->toBe([]);
    expect(StudentBirthplaceResolver::clearTextForSelectionChanges($student,[]))->toBe([]);
    $changes=StudentBirthplaceResolver::clearTextForSelectionChanges($student,['birth_province_id'=>2]);
    expect($changes)->toHaveCount(8)->and($changes['birth_village_kh'])->toBeNull();
    $changes=StudentBirthplaceResolver::clearTextForSelectionChanges($student,['birth_district_id'=>null]);
    expect($changes)->toHaveCount(6)->not->toHaveKey('birth_province_kh');
});

it('normalizes saved birthplace records without source workbooks and safely supports rerunning',function () {
    DB::table('tb_student')->insert(['birth_province_en'=>'Cambodia','birth_province_kh'=>'ភ្នំពេញ']);
    $this->artisan('legacy:normalize-student-birthplaces',['--dry-run'=>true])->expectsOutput('Would normalize: 1 student birthplaces.')->assertSuccessful();
    expect(DB::table('tb_student')->value('birth_province_id'))->toBeNull();
    $this->artisan('legacy:normalize-student-birthplaces')->expectsOutput('Normalized: 1 student birthplaces.')->assertSuccessful();
    expect(DB::table('tb_student')->value('birth_province_id'))->toBe(1)
        ->and(DB::table('tb_student')->value('birth_province_en'))->toBe('Phnom Penh')
        ->and(DB::table('tb_student')->value('birth_village_id'))->toBeNull();
    $this->artisan('legacy:normalize-student-birthplaces')->expectsOutput('Normalized: 0 student birthplaces.')->assertSuccessful();
});
