<?php

use App\Models\Student;
use App\Support\StudentAddressResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

beforeEach(function(){
    Schema::create('tb_student',function(Blueprint $t){
        $t->id();$t->string('student_id');$t->string('full_name_en');$t->text('current_address_en')->nullable();$t->text('current_address_kh')->nullable();$t->timestamps();
        foreach(['house_no','street'] as $part)foreach(['en','kh'] as $language)$t->string('address_'.$part.'_'.$language)->nullable();
        foreach(['birth','address'] as $prefix)foreach(['country','province','district','commune','village'] as $level)$t->integer($prefix.'_'.$level.'_id')->nullable();
    });
    (require database_path('migrations/2026_10_06_000005_add_legacy_birthplace_text_to_students.php'))->up();
    (require database_path('migrations/2026_11_24_000004_preserve_imported_student_locations.php'))->up();
    $parent=null;
    foreach(['country','province','district','commune','village'] as $level){
        Schema::create('tb_'.$level,function(Blueprint $t)use($level,$parent){$t->id();$t->string($level.'_name_en')->nullable();$t->string($level.'_name_kh')->nullable();if($parent)$t->integer($parent.'_id');});$parent=$level;
    }
    DB::table('tb_country')->insert(['id'=>1,'country_name_en'=>'Cambodia','country_name_kh'=>'កម្ពុជា']);
    DB::table('tb_province')->insert([['id'=>1,'country_id'=>1,'province_name_en'=>'Phnom Penh','province_name_kh'=>'ភ្នំពេញ'],['id'=>2,'country_id'=>1,'province_name_en'=>'Kandal','province_name_kh'=>'កណ្តាល']]);
    DB::table('tb_district')->insert(['id'=>1,'province_id'=>1,'district_name_en'=>'Sen Sok','district_name_kh'=>'សែនសុខ']);
    DB::table('tb_commune')->insert(['id'=>1,'district_id'=>1,'commune_name_en'=>'Tuek Thla','commune_name_kh'=>'ទឹកថ្លា']);
    DB::table('tb_village')->insert(['id'=>1,'commune_id'=>1,'village_name_en'=>'Village One','village_name_kh'=>'ភូមិមួយ']);
});

it('maps original workbook Khmer address columns without overwriting address text',function(){
    $student=new Student(['current_address_en'=>'Original full address']);
    $changes=(new StudentAddressResolver)->changes($student,['kh_city'=>'ភ្នំពេញ','kh_khan'=>'សែនសុខ','kh_sangkat'=>'ទឹកថ្លា']);
    expect($changes)->toBe(['address_province_id'=>1,'address_country_id'=>1,'address_district_id'=>1,'address_commune_id'=>1])
        ->and($student->current_address_en)->toBe('Original full address');
});

it('links exact comma separated address parts in their actual hierarchy',function(){
    $student=new Student(['current_address_en'=>'House 10, Street 20, Village One, Tuek Thla, Sen Sok, Phnom Penh, Cambodia']);
    $resolver=new StudentAddressResolver;$changes=$resolver->changes($student);
    expect($changes)->toBe(['address_house_no_en'=>'10','address_street_en'=>'20','address_village_en'=>'One','address_country_id'=>1,'address_province_id'=>1,'address_district_id'=>1,'address_commune_id'=>1,'address_village_id'=>1]);
    $student->fill($changes);expect($resolver->changes($student))->toBe([]);
});

it('preserves existing selections and leaves ambiguous or unrecognized components empty',function(){
    $resolver=new StudentAddressResolver;
    expect($resolver->changes(new Student(['address_country_id'=>1,'address_province_id'=>2,'current_address_en'=>'Phnom Penh, Sen Sok'])))->toBe([]);
    DB::table('tb_village')->insert(['id'=>2,'commune_id'=>1,'village_name_en'=>'Village One','village_name_kh'=>'ភូមិមួយ']);
    $changes=(new StudentAddressResolver)->changes(new Student(['current_address_en'=>'Village One, Tuek Thla, Sen Sok, Phnom Penh']));
    expect($changes)->toHaveKey('address_commune_id',1)->not->toHaveKeys(['address_village_id','address_house_no_en','address_street_en']);
    expect($resolver->changes(new Student(['current_address_en'=>'Unknown Address'])))->toBe([]);
});

it('does not guess across conflicting bilingual province names',function(){
    $resolver=new StudentAddressResolver;
    expect($resolver->changes(new Student(['current_address_en'=>'Phnom Penh','current_address_kh'=>'កណ្តាល'])))->toBe([]);
    expect($resolver->changes(new Student(),['address_province_en'=>'Unmatched spelling','kh_city'=>'រាជធានីភ្នំពេញ']))->toHaveKey('address_province_id',1);
});

it('keeps existing selections and house numbers while displaying an unmatched village as text',function(){
    $student=new Student(['address_country_id'=>1,'address_province_id'=>2,'address_house_no_en'=>'99',
        'current_address_en'=>'House 10, Street 20, Historical Village, Phnom Penh']);
    $changes=(new StudentAddressResolver)->changes($student);
    expect($changes)->toBe(['address_street_en'=>'20','address_village_en'=>'Historical']);
    expect($student->address_house_no_en)->toBe('99')->and($student->address_province_id)->toBe(2);
});

it('previews saved address repairs without changing the database and applies them idempotently',function(){
    $this->app->useStoragePath(storage_path('framework/testing/location-repair-'.bin2hex(random_bytes(5))));
    DB::table('tb_student')->insert(['student_id'=>'001','full_name_en'=>'TEST STUDENT','current_address_en'=>'Sen Sok, Phnom Penh']);
    $before=DB::table('tb_student')->first();
    $this->artisan('students:repair-imported-locations')->assertSuccessful();
    expect(DB::table('tb_student')->first())->toEqual($before);
    $this->artisan('students:repair-imported-locations',['--apply'=>true])->assertSuccessful();
    expect(DB::table('tb_student')->value('address_province_id'))->toBe(1)->and(DB::table('tb_student')->value('address_district_id'))->toBe(1);
    $journals=glob(storage_path('app/private/student-location-repair-*/before-after.jsonl'));
    expect($journals)->toHaveCount(1);$entry=json_decode(trim(file_get_contents($journals[0])),true);
    expect($entry['before']['address_province_id'])->toBeNull()->and($entry['after']['address_province_id'])->toBe(1);
    $after=DB::table('tb_student')->first();
    $this->artisan('students:repair-imported-locations',['--apply'=>true])->assertSuccessful();
    expect(DB::table('tb_student')->first())->toEqual($after);
});
