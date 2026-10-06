<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->withoutMiddleware([
        \Illuminate\Auth\Middleware\Authenticate::class,
        \App\Http\Middleware\TrackUserPresence::class,
    ]);
    Schema::create('tb_student', function (Blueprint $t) {
        $t->id(); $t->string('student_id'); $t->string('family_number')->nullable();
        foreach (['birth_village_id', 'birth_commune_id', 'birth_district_id', 'birth_province_id', 'nationality_country_id'] as $key) $t->integer($key)->nullable();
        $t->timestamps();
    });
    (require database_path('migrations/2026_10_06_000005_add_legacy_birthplace_text_to_students.php'))->up();
    foreach (['village', 'commune', 'district', 'province', 'occupation', 'nationality', 'country'] as $type) {
        Schema::create('tb_'.$type, function (Blueprint $t) use ($type) {
            $t->id(); $t->string($type.'_name_en')->nullable(); $t->string($type.'_name_kh')->nullable();
            if ($type === 'country') { $t->string('nationality_name_en')->nullable(); $t->string('nationality_name_kh')->nullable(); }
            if (in_array($type, ['occupation', 'nationality'])) $t->softDeletes();
        });
    }
    Schema::create('tb_family', function (Blueprint $t) { $t->id(); $t->string('family_number'); $t->softDeletes(); });
    Schema::create('tb_family_student', function (Blueprint $t) {
        $t->integer('family_id'); $t->integer('student_id'); $t->string('relationship_type')->nullable();
        foreach (['is_primary_contact', 'has_pickup_authorization', 'has_portal_access'] as $key) $t->boolean($key)->default(false);
        $t->timestamps();
    });
    Schema::create('tb_family_member', function (Blueprint $t) {
        $t->id(); $t->integer('family_id'); $t->string('relationship_type');
        foreach (['full_name_en', 'full_name_kh', 'phone', 'workplace', 'occupation', 'occupation_en', 'occupation_kh', 'nationality_en', 'nationality_kh'] as $key) $t->string($key)->nullable();
        foreach (['occupation_id', 'nationality_country_id', 'nationality_id'] as $key) $t->integer($key)->nullable();
        $t->string('email')->nullable(); $t->integer('status')->default(1);
        foreach (['is_primary_contact', 'has_pickup_authorization', 'has_portal_access'] as $key) $t->boolean($key)->default(false);
        $t->softDeletes(); $t->timestamps();
    });
    DB::table('tb_student')->insert(['id'=>1, 'student_id'=>'TEST001', 'family_number'=>'TEST-FAMILY']);
    DB::table('tb_family')->insert(['id'=>1, 'family_number'=>'TEST-FAMILY']);
    DB::table('tb_family_student')->insert(['family_id'=>1, 'student_id'=>1]);
});

it('loads the selected students birthplace and bilingual lookup values for all three family contacts', function () {
    foreach (['village'=>'ជោគជ័យ', 'commune'=>'ស្ពឺ', 'district'=>'ចំការលើ', 'province'=>'កំពង់ចាម'] as $type=>$khmer) {
        DB::table('tb_'.$type)->insert(['id'=>1, $type.'_name_en'=>ucfirst($type).' Name', $type.'_name_kh'=>$khmer]);
        DB::table('tb_student')->where('id',1)->update(['birth_'.$type.'_id'=>1]);
    }
    DB::table('tb_country')->insert(['id'=>1,'nationality_name_en'=>'Cambodian','nationality_name_kh'=>'ខ្មែរ']);
    DB::table('tb_student')->where('id',1)->update(['nationality_country_id'=>1]);
    DB::table('tb_occupation')->insert(['id'=>1,'occupation_name_en'=>'Teacher','occupation_name_kh'=>'គ្រូបង្រៀន']);
    foreach (['mother','father','guardian'] as $type) DB::table('tb_family_member')->insert(['family_id'=>1,'relationship_type'=>$type,'full_name_en'=>ucfirst($type),'occupation_id'=>1,'nationality_country_id'=>1]);
    $response=$this->getJson(route('student-enrollments.profile',1))->assertOk();
    foreach (['village'=>'ជោគជ័យ','commune'=>'ស្ពឺ','district'=>'ចំការលើ','province'=>'កំពង់ចាម'] as $type=>$khmer) {
        $response->assertJsonPath('student.birth_'.$type.'.'.$type.'_name_en',ucfirst($type).' Name')
            ->assertJsonPath('student.birth_'.$type.'.'.$type.'_name_kh',$khmer);
    }
    $response->assertJsonPath('student.nationality_country.nationality_name_kh','ខ្មែរ');
    foreach ([0,1,2] as $index) $response->assertJsonPath("student.families.0.members.$index.occupation_en",'Teacher')
        ->assertJsonPath("student.families.0.members.$index.occupation_kh",'គ្រូបង្រៀន')
        ->assertJsonPath("student.families.0.members.$index.nationality_en",'Cambodian')
        ->assertJsonPath("student.families.0.members.$index.nationality_kh",'ខ្មែរ');
});

it('retains text from legacy contacts and supports old nationality and retired occupation references', function () {
    DB::table('tb_nationality')->insert(['id'=>1,'nationality_name_en'=>'Cambodian','nationality_name_kh'=>'ខ្មែរ']);
    DB::table('tb_occupation')->insert(['id'=>1,'occupation_name_en'=>'Farmer','occupation_name_kh'=>'កសិករ','deleted_at'=>now()]);
    DB::table('tb_family_member')->insert([
        ['family_id'=>1,'relationship_type'=>'mother','occupation_en'=>'Trader','occupation_kh'=>'អាជីវករ','nationality_en'=>'Cambodian','nationality_kh'=>'ខ្មែរ','nationality_id'=>null,'occupation_id'=>null],
        ['family_id'=>1,'relationship_type'=>'father','occupation_en'=>null,'occupation_kh'=>null,'nationality_en'=>null,'nationality_kh'=>null,'nationality_id'=>1,'occupation_id'=>1],
    ]);
    $this->getJson(route('student-enrollments.profile',1))->assertOk()
        ->assertJsonPath('student.birth_province',null)
        ->assertJsonPath('student.families.0.members.0.occupation_en','Trader')
        ->assertJsonPath('student.families.0.members.0.occupation_kh','អាជីវករ')
        ->assertJsonPath('student.families.0.members.1.occupation_kh','កសិករ')
        ->assertJsonPath('student.families.0.members.1.nationality_kh','ខ្មែរ');
});

it('finds shared family contacts by family number when the older student has no family pivot', function () {
    DB::table('tb_family_student')->delete();
    DB::table('tb_family_member')->insert(['family_id'=>1,'relationship_type'=>'guardian','occupation'=>'Legacy occupation','nationality_kh'=>'ខ្មែរ']);
    $this->getJson(route('student-enrollments.profile',1))->assertOk()
        ->assertJsonPath('student.families.0.members.0.relationship_type','guardian')
        ->assertJsonPath('student.families.0.members.0.occupation_en','Legacy occupation');
});

it('keeps edit form lookup IDs while providing the missing family details', function () {
    DB::table('tb_country')->insert(['id'=>1,'nationality_name_en'=>'Cambodian','nationality_name_kh'=>'ខ្មែរ']);
    DB::table('tb_occupation')->insert(['id'=>1,'occupation_name_en'=>'Teacher','occupation_name_kh'=>'គ្រូបង្រៀន']);
    DB::table('tb_family_member')->insert(['family_id'=>1,'relationship_type'=>'mother','occupation_id'=>1,'nationality_country_id'=>1]);
    $this->getJson(route('student-enrollments.family-details',['family_number'=>'TEST-FAMILY']))->assertOk()
        ->assertJsonPath('members.0.occupation_id',1)->assertJsonPath('members.0.nationality_country_id',1)
        ->assertJsonPath('members.0.occupation_kh','គ្រូបង្រៀន')->assertJsonPath('members.0.nationality_en','Cambodian');
});

it('returns not found for a missing student', function () {
    $this->getJson(route('student-enrollments.profile',999))->assertNotFound();
});

it('returns permanently restored legacy birthplace text in the profile response', function () {
    DB::table('tb_student')->where('id',1)->update(['birth_province_kh'=>'រាជធានីភ្នំពេញ','birth_province_en'=>'Phnom Penh','birth_village_kh'=>'ភូមិ១']);
    $this->getJson(route('student-enrollments.profile',1))->assertOk()
        ->assertJsonPath('student.birth_province_kh','រាជធានីភ្នំពេញ')
        ->assertJsonPath('student.birth_province_en','Phnom Penh')
        ->assertJsonPath('student.birth_village_kh','ភូមិ១');
});

it('keeps unmatched occupation text when editing and clears the legacy fallback when explicitly removed', function () {
    $family=\App\Models\Family::findOrFail(1);
    $service=new \App\Services\FamilyService();
    $data=['full_name_en'=>'Sample Mother','occupation_id'=>null,'occupation_en'=>'Unlisted occupation','occupation_kh'=>'មុខរបរគំរូ'];
    $member=$service->syncEnrollmentMember($family,'mother',$data);
    expect($member->occupation_en)->toBe('Unlisted occupation')->and($member->occupation_kh)->toBe('មុខរបរគំរូ');
    $service->syncEnrollmentMember($family,'mother',$data);
    $this->getJson(route('student-enrollments.profile',1))->assertOk()
        ->assertJsonPath('student.families.0.members.0.occupation_en','Unlisted occupation');
    $service->syncEnrollmentMember($family,'mother',array_replace($data,['occupation_en'=>null,'occupation_kh'=>null]));
    $this->getJson(route('student-enrollments.profile',1))->assertOk()
        ->assertJsonPath('student.families.0.members.0.occupation_en',null)
        ->assertJsonPath('student.families.0.members.0.occupation_kh',null);
});

it('repairs missing imported values once and leaves existing values untouched', function () {
    DB::table('tb_student')->where('id',1)->update(['birth_village_en'=>'Saved village']);
    DB::table('tb_family_member')->insert(['family_id'=>1,'relationship_type'=>'mother','full_name_en'=>'Sample Mother']);
    $relative='tmp/profile-repair-test-'.bin2hex(random_bytes(6));
    $directory=base_path($relative); mkdir($directory);
    $write=function ($filename, array $data) use ($directory) {
        $sheet='<worksheet><sheetData>';
        foreach ([array_keys($data),array_values($data)] as $index=>$values) {
            $sheet.='<row r="'.($index+1).'">';
            foreach ($values as $column=>$value) $sheet.='<c r="'.chr(65+$column).($index+1).'" t="inlineStr"><is><t>'.htmlspecialchars($value,ENT_XML1).'</t></is></c>';
            $sheet.='</row>';
        }
        $sheet.='</sheetData></worksheet>';
        $zip=new ZipArchive(); $zip->open($directory.'/'.$filename,ZipArchive::CREATE);
        $zip->addFromString('xl/worksheets/sheet1.xml',$sheet); $zip->close();
    };
    try {
        $write('Student Information.xlsx',['student id'=>'TEST001','place of birth'=>'Phnom Penh','kh_place_of_birth'=>'រាជធានីភ្នំពេញ','phoum_pob'=>'Source village']);
        $write('Parent Information.xlsx',['family_number'=>'TEST-FAMILY','mother_name_en'=>'Sample Mother','mother_occupation_en'=>'Teacher','mother_occupation_kh'=>'គ្រូបង្រៀន']);
        $this->artisan('legacy:restore-student-profiles',['--path'=>$relative,'--dry-run'=>true])->assertSuccessful();
        expect(DB::table('tb_student')->value('birth_province_en'))->toBeNull();
        $this->artisan('legacy:restore-student-profiles',['--path'=>$relative])->assertSuccessful();
        expect(DB::table('tb_student')->value('birth_province_en'))->toBe('Phnom Penh')
            ->and(DB::table('tb_student')->value('birth_village_en'))->toBe('Saved village')
            ->and(DB::table('tb_family_member')->value('occupation_kh'))->toBe('គ្រូបង្រៀន');
        $this->artisan('legacy:restore-student-profiles',['--path'=>$relative])->expectsOutput('Restored: {"students":0,"parents":0}')->assertSuccessful();
        unlink($directory.'/Student Information.xlsx'); unlink($directory.'/Parent Information.xlsx');
        $this->getJson(route('student-enrollments.profile',1))->assertOk()->assertJsonPath('student.birth_province_en','Phnom Penh')
            ->assertJsonPath('student.families.0.members.0.occupation_kh','គ្រូបង្រៀន');
    } finally {
        foreach (['Student Information.xlsx','Parent Information.xlsx'] as $file) if (is_file($directory.'/'.$file)) unlink($directory.'/'.$file);
        rmdir($directory);
    }
});

it('requires authentication to load a profile', function () {
    $this->withMiddleware([\Illuminate\Auth\Middleware\Authenticate::class, \App\Http\Middleware\TrackUserPresence::class]);
    $this->getJson(route('student-enrollments.profile',1))->assertRedirect(route('login'));
});
