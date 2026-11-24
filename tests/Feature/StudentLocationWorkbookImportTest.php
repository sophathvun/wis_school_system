<?php

use App\Models\Student;
use App\Services\StudentLocationWorkbookImport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

beforeEach(function(){
    $this->app->useStoragePath(storage_path('framework/testing/workbook-locations-'.bin2hex(random_bytes(5))));
    Schema::create('tb_student',function(Blueprint $t){
        $t->id();$t->string('student_id')->unique();$t->string('student_no')->nullable()->unique();$t->timestamps();
        foreach(['full_name_en','full_name_kh','gender','gender_kh','date_of_birth','family_number','home_phone','email','previous_school','experienced_english','test_result','tested_by','remarks','current_address_en','current_address_kh'] as $field)$t->text($field)->nullable();
        $t->integer('status')->default(1);$t->integer('nationality_country_id')->nullable();
        foreach(['house_no','street'] as $part)foreach(['en','kh'] as $language)$t->string('address_'.$part.'_'.$language)->nullable();
        foreach(['birth','address'] as $prefix)foreach(['country','province','district','commune','village'] as $level)$t->integer($prefix.'_'.$level.'_id')->nullable();
    });
    Schema::create('tb_student_number_sequence',function(Blueprint $t){$t->id();$t->integer('last_number');});
    DB::table('tb_student_number_sequence')->insert(['id'=>1,'last_number'=>1]);
    Schema::create('tb_student_enrollment',fn(Blueprint $t)=>$t->id());
    (require database_path('migrations/2026_10_06_000005_add_legacy_birthplace_text_to_students.php'))->up();
    (require database_path('migrations/2026_11_24_000004_preserve_imported_student_locations.php'))->up();
    $parent=null;
    foreach(['country','province','district','commune','village'] as $level){
        Schema::create('tb_'.$level,function(Blueprint $t)use($level,$parent){$t->id();$t->string($level.'_name_en')->nullable();$t->string($level.'_name_kh')->nullable();if($parent)$t->integer($parent.'_id');if($level==='country'){$t->string('nationality_name_en')->nullable();$t->string('nationality_name_kh')->nullable();}});$parent=$level;
    }
    DB::table('tb_country')->insert(['id'=>1,'country_name_en'=>'Cambodia','country_name_kh'=>'កម្ពុជា']);
    DB::table('tb_province')->insert(['id'=>1,'country_id'=>1,'province_name_en'=>'Phnom Penh','province_name_kh'=>'ភ្នំពេញ']);
    DB::table('tb_district')->insert(['id'=>1,'province_id'=>1,'district_name_en'=>'Sen Sok','district_name_kh'=>'សែនសុខ']);
    DB::table('tb_commune')->insert(['id'=>1,'district_id'=>1,'commune_name_en'=>'Tuek Thla','commune_name_kh'=>'ទឹកថ្លា']);
    DB::table('tb_village')->insert(['id'=>1,'commune_id'=>1,'village_name_en'=>'Village One','village_name_kh'=>'ភូមិមួយ']);
    DB::table('tb_student')->insert(['id'=>1,'student_id'=>'1236','student_no'=>'00000001','full_name_en'=>'EXISTING STUDENT','full_name_kh'=>'សិស្សចាស់']);
    $this->source=storage_path('source.xlsx');@mkdir(dirname($this->source),0750,true);file_put_contents($this->source,'test source bytes');
    $this->rows=[['student id'=>'123៦','name in english'=>'Existing Student','place of birth'=>'Cambodia','kh_place_of_birth'=>'ភ្នំពេញ','kh_khan_pob'=>'សែនសុខ','kh_sangkat_pob'=>'ទឹកថ្លា','kh_phoum_pob'=>'Unlisted Village','home address'=>'House 5, Phnom Penh','kh_city'=>'ភ្នំពេញ','kh_khan'=>'សែនសុខ'],
        ['student id'=>'999','name in english'=>'New Student','name in khmer'=>'សិស្សថ្មី','date of birth'=>'2010-02-03','sex'=>'F','family_number'=>'F100','kh_place_of_birth'=>'ភ្នំពេញ','kh_phoum_pob'=>'Unknown historical village','kh_current_address'=>'Original Khmer Address','kh_city'=>'ភ្នំពេញ','kh_sangkat'=>'Unlisted Commune']];
});

it('previews without writes then preserves every source cell and creates profiles without enrollments',function(){
    $service=new StudentLocationWorkbookImport;
    $before=DB::table('tb_student')->get()->toJson();
    $preview=$service->run($this->rows,$this->source,false,true);
    expect($preview['new_profiles'])->toBe(1)->and($preview['normalized_student_ids'])->toBe(1)->and($preview['all_location_source_cells_preserved'])->toBeTrue()->and(DB::table('tb_student')->get()->toJson())->toBe($before);
    $result=$service->run($this->rows,$this->source,true,true);
    expect($result['all_source_rows_accounted_for'])->toBeTrue()->and($result['all_location_source_cells_preserved'])->toBeTrue()
        ->and($result['database_changes'])->toBe(2)->and(Student::count())->toBe(2)->and(DB::table('tb_student_enrollment')->count())->toBe(0);
    $existing=Student::find(1);$new=Student::where('student_id','999')->firstOrFail();
    expect($existing->birth_province_id)->toBe(1)->and($existing->birth_district_id)->toBe(1)->and($existing->birth_village_kh)->toBe('Unlisted Village')
        ->and($existing->imported_location_data['values']['place of birth'])->toBe('Cambodia')->and($existing->student_id)->toBe('1236')
        ->and($new->family_number)->toBe('F100')->and($new->date_of_birth->format('Y-m-d'))->toBe('2010-02-03')->and($new->student_no)->toBe('00000002')
        ->and($new->address_commune_kh)->toBe('Unlisted Commune');
    $csv=file_get_contents($result['private_directory'].'/new-student-profiles.csv');
    expect($csv)->toStartWith("\xEF\xBB\xBF")->toContain('999')->toContain('New Student')->toContain('F100');
    $repeat=$service->run($this->rows,$this->source,true,true);
    expect($repeat['new_profiles'])->toBe(0)->and($repeat['database_changes'])->toBe(0)->and(Student::count())->toBe(2);
});

it('retains saved fields and stores conflicting source values in the archive',function(){
    DB::table('tb_student')->where('id',1)->update(['current_address_en'=>'Newer saved home','birth_province_en'=>'Saved province']);
    $result=(new StudentLocationWorkbookImport)->run([$this->rows[0]],$this->source,true,false);
    $student=Student::find(1);
    expect($student->current_address_en)->toBe('Newer saved home')->and($student->birth_province_en)->toBe('Saved province')
        ->and($student->imported_location_data['values']['home address'])->toBe('House 5, Phnom Penh')
        ->and(file_get_contents($result['private_directory'].'/review.csv'))->toContain('existing_value_preserved');
});

it('rejects duplicate source IDs before writing database rows',function(){
    expect(fn()=>(new StudentLocationWorkbookImport)->run([$this->rows[1],$this->rows[1]],$this->source,true,true))->toThrow(RuntimeException::class);
    expect(Student::count())->toBe(1);
});

it('reads sparse workbook columns and rich text without dropping location text',function(){
    $file=storage_path('rich-source.xlsx');$zip=new ZipArchive;$zip->open($file,ZipArchive::CREATE|ZipArchive::OVERWRITE);
    $zip->addFromString('xl/sharedStrings.xml','<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><r><t>Phnom </t></r><r><t>Penh</t></r></si></sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml','<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Student ID</t></is></c><c r="C1" t="inlineStr"><is><t>Place of Birth</t></is></c><c r="E1" t="inlineStr"><is><t>Home Address</t></is></c></row><row r="2"><c r="A2"><v>1236</v></c><c r="C2" t="s"><v>0</v></c><c r="E2" t="inlineStr"><is><r><t>House 1, </t></r><r><t>Street 2</t></r></is></c></row></sheetData></worksheet>');$zip->close();
    $rows=(new App\Console\Commands\ImportLegacyStudentWorkbooks)->readWorkbook($file);
    expect($rows)->toBe([['student id'=>'1236','place of birth'=>'Phnom Penh','home address'=>'House 1, Street 2']]);
});

it('preserves both source rows when a Khmer digit ID and ASCII ID identify the same student',function(){
    $second=$this->rows[0];$second['student id']='1236';$second['home address']='Different older source address';
    $result=(new StudentLocationWorkbookImport)->run([$this->rows[0],$second],$this->source,true,true);
    expect($result['new_profiles'])->toBe(0)->and($result['all_location_source_cells_preserved'])->toBeTrue();
    $archive=Student::find(1)->imported_location_data;
    expect($archive['values']['home address'])->toBe('Different older source address')
        ->and($archive['additional_source_rows'][0]['values']['home address'])->toBe('House 5, Phnom Penh');
});
