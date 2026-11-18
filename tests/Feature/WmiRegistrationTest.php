<?php

use App\Models\{User,WmiYear,WmiExamCenter,WmiSchool,WmiRegistration,AcademicYear,SchoolInfo,Student,StudentEnrollment,Grade,SchoolClass};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->mock(\App\Support\WmiPermissions::class)->shouldReceive('check')->andReturn(true);
    $this->withoutMiddleware(\App\Http\Middleware\TrackUserPresence::class);$this->withoutVite();
    app('view')->getFinder()->prependLocation(base_path('tests/Fixtures/wmi-views'));
    (require database_path('migrations/2026_07_31_000001_create_location_tables.php'))->up();
    (require base_path('tests/Fixtures/wmi-enrollment-schema.php'))();
    foreach(['000002_create_wmi_years','000003_create_wmi_exam_centers','000004_create_wmi_schools','000005_make_wmi_school_details_optional','000006_create_wmi_registrations'] as $migration)
        (require database_path('migrations/2026_11_18_'.$migration.'.php'))->up();
    $this->actingAs(new User(['id'=>1,'name'=>'Registrar']));
    $this->year=WmiYear::create(['year'=>2026]);
    $this->center=WmiExamCenter::create(['wmi_year_id'=>$this->year->id,'name'=>'Stadium','name_key'=>'stadium','location'=>'Phnom Penh']);
    $this->school=WmiSchool::create(['name_en'=>'Other School','name_kh'=>null,'name_key'=>'other school','school_type'=>'Private']);
    $this->campus=SchoolInfo::create(['campus_name_en'=>'STD','status'=>true]);
    $this->otherCampus=SchoolInfo::create(['campus_name_en'=>'BKK','status'=>true]);
    $this->academicYear=AcademicYear::create(['academic_year'=>'2025-2026','period_type'=>'regular']);
    $this->grade=Grade::create(['grade'=>'Grade 5']);$this->class=SchoolClass::create(['class_name'=>'5A']);
    $this->student=Student::create(['student_id'=>'001234','full_name_en'=>'WESTERN STUDENT','date_of_birth'=>'2015-01-02','gender'=>'Female','home_phone'=>'012345678']);
    $this->enrollment=StudentEnrollment::create(['student_id'=>$this->student->id,'academic_year_id'=>$this->academicYear->id,'campus_id'=>$this->campus->id,'grade_id'=>$this->grade->id,'class_id'=>$this->class->id]);
    $this->data=['wmi_year_id'=>$this->year->id,'wmi_exam_center_id'=>$this->center->id,'student_source'=>'other','wmi_school_id'=>$this->school->id,
        'student_name'=>'OTHER STUDENT','date_of_birth'=>'2015-03-04','gender'=>'M','current_grade'=>'Grade 5','grade_contest'=>'5','registration_channel'=>'online'];
});

it('registers external students without changing enrollment and allows any registrar campus or online',function () {
    $this->postJson(route('wmi-management.registrations.store'),$this->data)->assertCreated();$record=WmiRegistration::sole();
    expect($record->campus_study)->toBe('Other')->and($record->campus_registered)->toBe('Online')->and($record->wmi_id_number)->toBeNull()->and($record->enrollment_id)->toBeNull();
    $this->putJson(route('wmi-management.registrations.update',$record),array_replace($this->data,['registration_channel'=>'campus','registrar_campus_id'=>$this->otherCampus->id,'room_no'=>1,'seat_no'=>1]))->assertOk();
    expect($record->fresh()->campus_registered)->toBe('BKK')->and(StudentEnrollment::count())->toBe(1);
    $this->get(route('wmi-management.section',['section'=>'preliminary-round','tab'=>'pre-registration']))->assertOk()
        ->assertSee('OTHER STUDENT')->assertSee('Register Candidate')->assertDontSee('data-bs-target="#wmiIdImportModal"',false)->assertDontSee('data-bs-target="#wmiSeatModal"',false)->assertSee('wmiRegistrationModal');
});

it('uses authoritative enrollment details for Western students and prevents repeat registration',function () {
    $western=array_replace($this->data,['student_source'=>'western','enrollment_id'=>$this->enrollment->id,'student_name'=>'Spoofed','campus_study'=>'Wrong','gender'=>'M']);
    $this->postJson(route('wmi-management.registrations.store'),$western)->assertCreated();$record=WmiRegistration::sole();
    expect($record->student_name)->toBe('WESTERN STUDENT')->and($record->student_code)->toBe('001234')->and($record->gender)->toBe('F')
        ->and($record->campus_study)->toBe('STD')->and($record->current_grade)->toBe('5A')->and($record->wmi_school_id)->toBeNull();
    $this->postJson(route('wmi-management.registrations.store'),$western)->assertUnprocessable();
    $this->getJson(route('wmi-management.registrations.enrollments',['q'=>'0012','academic_year_id'=>$this->academicYear->id]))->assertOk()->assertJsonPath('data.0.enrollment_id',$this->enrollment->id);
    $this->getJson(route('wmi-management.registrations.enrollments',['academic_year_id'=>$this->academicYear->id,'campus_id'=>$this->campus->id,'grade_id'=>$this->grade->id]))->assertOk()->assertJsonCount(1,'data');
    $this->getJson(route('wmi-management.registrations.enrollments',['academic_year_id'=>$this->academicYear->id,'campus_id'=>$this->otherCampus->id]))->assertOk()->assertJsonCount(0,'data');
    $otherGrade=Grade::create(['grade'=>'Grade 6']);
    $this->getJson(route('wmi-management.registrations.enrollments',['academic_year_id'=>$this->academicYear->id,'grade_id'=>$otherGrade->id]))->assertOk()->assertJsonCount(0,'data');
    expect($this->enrollment->fresh()->campus_id)->toBe($this->campus->id);
});

it('validates center year contest grade required details and seat collisions',function () {
    foreach([['grade_contest'=>'13'],['student_name'=>''],['student_source'=>'western','enrollment_id'=>null],['room_no'=>1],['wmi_exam_center_id'=>999]] as $invalid)
        $this->postJson(route('wmi-management.registrations.store'),array_replace($this->data,$invalid))->assertUnprocessable();
    $this->postJson(route('wmi-management.registrations.store'),array_replace($this->data,['room_no'=>1,'seat_no'=>2]))->assertCreated();
    $this->postJson(route('wmi-management.registrations.store'),array_replace($this->data,['student_name'=>'ANOTHER','room_no'=>1,'seat_no'=>2]))->assertUnprocessable();
    $otherYear=WmiYear::create(['year'=>2025]);
    $this->postJson(route('wmi-management.registrations.store'),array_replace($this->data,['wmi_year_id'=>$otherYear->id]))->assertUnprocessable();
});

it('exports a permanent reference and imports committee IDs with leading zeros without modifying other fields',function () {
    $this->postJson(route('wmi-management.registrations.store'),$this->data)->assertCreated();$record=WmiRegistration::sole();
    $export=$this->get(route('wmi-management.registrations.export'));$export->assertOk();
    expect($export->streamedContent())->toContain('registration_reference','wmi_id_number',$record->reference,'OTHER STUDENT');
    $file=UploadedFile::fake()->createWithContent('ids.csv',"registration_reference,wmi_id_number\n{$record->reference},00WMI0260001\n");
    $this->postJson(route('wmi-management.registrations.import-ids'),['wmi_year_id'=>$this->year->id,'file'=>$file])->assertOk();
    expect($record->fresh()->wmi_id_number)->toBe('00WMI0260001')->and($record->fresh()->student_name)->toBe('OTHER STUDENT');
    $file=UploadedFile::fake()->createWithContent('ids.csv',"registration_reference,wmi_id_number\n{$record->reference},DIFFERENT\n");
    $this->postJson(route('wmi-management.registrations.import-ids'),['wmi_year_id'=>$this->year->id,'file'=>$file])->assertUnprocessable();
    expect($record->fresh()->wmi_id_number)->toBe('00WMI0260001');
});

it('rolls back ID imports with duplicate IDs or mismatched registration references',function () {
    foreach(['A','B'] as $name)$this->postJson(route('wmi-management.registrations.store'),array_replace($this->data,['student_name'=>$name]))->assertCreated();
    [$a,$b]=WmiRegistration::orderBy('id')->get()->all();
    foreach(["{$a->reference},SAME\n{$b->reference},SAME", "{$a->reference},VALID\nUNKNOWN,BAD"] as $rows){
        $file=UploadedFile::fake()->createWithContent('ids.csv',"registration_reference,wmi_id_number\n$rows\n");
        $this->postJson(route('wmi-management.registrations.import-ids'),['wmi_year_id'=>$this->year->id,'file'=>$file])->assertUnprocessable();
        expect(WmiRegistration::whereNotNull('wmi_id_number')->count())->toBe(0);
    }
});

it('reads XLSX committee ID cells as text',function () {
    $path=tempnam(sys_get_temp_dir(),'wmi');$zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);
    $zip->addFromString('xl/workbook.xml','<workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="IDs" r:id="s1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels','<Relationships><Relationship Id="s1" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml','<worksheet><sheetData><row><c r="A1" t="inlineStr"><is><t>registration_reference</t></is></c><c r="B1" t="inlineStr"><is><t>wmi_id_number</t></is></c></row><row><c r="A2" t="inlineStr"><is><t>REF-1</t></is></c><c r="B2" t="inlineStr"><is><t>00012</t></is></c></row></sheetData></worksheet>');$zip->close();
    try {expect((new \App\Support\WmiIdSpreadsheet)->read($path,'xlsx')[0]['wmi_id_number'])->toBe('00012');} finally {unlink($path);}
});

it('assigns unseated candidates deterministically while preserving existing room and seat assignments',function () {
    foreach(['ZED','AMY','BOB'] as $name)$this->postJson(route('wmi-management.registrations.store'),array_replace($this->data,['student_name'=>$name]))->assertCreated();
    WmiRegistration::where('student_name','ZED')->update(['room_no'=>3,'seat_no'=>10]);
    $this->postJson(route('wmi-management.registrations.allocate'),['wmi_exam_center_id'=>$this->center->id,'capacity'=>1])->assertOk();
    expect(WmiRegistration::where('student_name','ZED')->first()->room_no)->toBe(3)
        ->and(WmiRegistration::where('student_name','AMY')->first()->room_no)->toBe(4)
        ->and(WmiRegistration::where('student_name','BOB')->first()->room_no)->toBe(5);
    $this->postJson(route('wmi-management.registrations.allocate'),['wmi_exam_center_id'=>$this->center->id,'capacity'=>25])->assertOk();
    expect(WmiRegistration::where('student_name','BOB')->first()->room_no)->toBe(5);
});

it('filters candidates and requires authentication for registration and import',function () {
    $this->postJson(route('wmi-management.registrations.store'),$this->data)->assertCreated();
    $this->get(route('wmi-management.section',['section'=>'preliminary-round','tab'=>'candidates','q'=>'NO MATCH']))->assertOk()->assertDontSee('OTHER STUDENT');
    $this->get(route('wmi-management.section',['section'=>'import-export']))->assertOk()->assertSee('Export Candidates')->assertSee('wmiIdImportModal')->assertDontSee('id="wmiSeatModal"',false);
    $this->get(route('wmi-management.section',['section'=>'settings','tab'=>'room-seats']))->assertOk()->assertSee('wmiSeatModal')->assertDontSee('id="wmiIdImportModal"',false);
    auth()->logout();$this->postJson(route('wmi-management.registrations.store'),$this->data)->assertUnauthorized();
    $this->postJson(route('wmi-management.registrations.allocate'),['wmi_exam_center_id'=>$this->center->id,'capacity'=>25])->assertUnauthorized();
});

it('applies registration filters together and updates the summary above the candidate list',function () {
    DB::table('users')->insert(['id'=>1,'name'=>'Registration Staff']);
    $this->actingAs(User::findOrFail(1));
    $this->postJson(route('wmi-management.registrations.store'),$this->data)->assertCreated();
    $selected=WmiRegistration::sole();
    $this->postJson(route('wmi-management.registrations.store'),array_replace($this->data,['student_name'=>'CAMPUS CANDIDATE','grade_contest'=>'6','registration_channel'=>'campus','registrar_campus_id'=>$this->otherCampus->id]))->assertCreated();
    $query=['section'=>'preliminary-round','tab'=>'pre-registration','wmi_year_id'=>$this->year->id,'campus_study'=>'Other','campus_registered'=>'Online','grade_contest'=>'5','q'=>'OTHER','registered_by'=>1,'registered_date'=>$selected->created_at->format('Y-m-d')];
    $this->get(route('wmi-management.section',$query))->assertOk()->assertSee('Datas Filter')->assertSee('data-wmi-filter-toggle',false)
        ->assertSee('Registered Date')->assertSee('Registration Staff')->assertSee('OTHER STUDENT')->assertDontSee('CAMPUS CANDIDATE')
        ->assertDontSee('class="card wmi-heading mb-3"',false)
        ->assertViewHas('registrations',fn($rows)=>$rows->total()===1)->assertViewHas('registrationSummary',fn($summary)=>(int)$summary->total===1);
    $this->get(route('wmi-management.section',array_replace($query,['registered_date'=>'2001-01-01'])))->assertOk()->assertViewHas('registrations',fn($rows)=>$rows->total()===0);
    $this->getJson(route('wmi-management.section',array_replace($query,['registered_date'=>'invalid'])))->assertUnprocessable();
});

it('renders the grouped registration list and deletes a registration without deleting its student or enrollment',function () {
    DB::table('users')->insert(['id'=>1,'name'=>'Registration Staff']);$this->actingAs(User::findOrFail(1));
    $this->postJson(route('wmi-management.registrations.store'),array_replace($this->data,['phone_number'=>'012345678','telegram_number'=>'098765432','email_address'=>'parent@example.com']))->assertCreated();
    $record=WmiRegistration::sole();
    $this->get(route('wmi-management.section',['section'=>'preliminary-round','tab'=>'pre-registration']))->assertOk()
        ->assertSee('Student Name')->assertSee('Contact Info')->assertSee('Study Information')->assertSee('Registration Staff')
        ->assertSee('012345678')->assertSee('098765432')->assertSee('parent@example.com')->assertSee($record->created_at->format('d-M-Y'))
        ->assertSee('ti-brand-telegram',false)->assertSee('data-wmi-registration-delete',false)->assertSee('Registered Date:');
    auth()->logout();$this->deleteJson(route('wmi-management.registrations.destroy',$record))->assertUnauthorized();
    $this->actingAs(User::findOrFail(1));
    $this->deleteJson(route('wmi-management.registrations.destroy',$record))->assertOk();
    expect(WmiRegistration::count())->toBe(0)->and(Student::count())->toBe(1)->and(StudentEnrollment::count())->toBe(1)
        ->and(WmiYear::count())->toBe(1)->and(WmiSchool::count())->toBe(1);
});

it('rejects deletion of a registration linked to exam data',function () {
    $this->postJson(route('wmi-management.registrations.store'),$this->data)->assertCreated();$record=WmiRegistration::sole();
    \Illuminate\Support\Facades\Schema::create('wmi_exam_scores',function(\Illuminate\Database\Schema\Blueprint $table){$table->id();$table->foreignId('wmi_registration_id')->constrained('wmi_registrations')->restrictOnDelete();});
    DB::table('wmi_exam_scores')->insert(['wmi_registration_id'=>$record->id]);
    $this->deleteJson(route('wmi-management.registrations.destroy',$record))->assertStatus(409);
    expect(WmiRegistration::count())->toBe(1)->and(DB::table('wmi_exam_scores')->count())->toBe(1);
});
