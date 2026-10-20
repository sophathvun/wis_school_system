<?php

use App\Models\Staff;
use App\Models\StaffWorkHistory;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\TrackUserPresence::class);
    Storage::fake('public');
    Storage::fake('local');
    Schema::create('users',function(Blueprint $t) { $t->id(); $t->string('name'); foreach(['staff_id','gender','phone','email','photo_path','username','password','login_identifier'] as $key) $t->string($key)->nullable(); $t->boolean('must_change_password')->default(false); $t->date('date_of_birth')->nullable(); foreach(['position_id','department_id','active_campus_id'] as $key) $t->integer($key)->nullable(); $t->boolean('is_global')->default(false); $t->integer('status')->default(1); $t->timestamps(); });
    Schema::create('access_roles',function(Blueprint $t) { $t->id(); $t->string('code'); $t->string('name'); $t->integer('status')->default(1); $t->boolean('is_global')->default(false); $t->boolean('is_system')->default(false); $t->integer('department_id')->nullable(); $t->softDeletes(); $t->timestamps(); });
    Schema::create('access_user_roles',function(Blueprint $t) { $t->integer('user_id'); $t->integer('role_id'); $t->integer('campus_id')->nullable(); $t->timestamps(); });
    Schema::create('access_permissions',function(Blueprint $t) { $t->id(); $t->string('code'); $t->string('name'); $t->string('module'); $t->string('action'); $t->timestamps(); });
    foreach(['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id','access_user_permission_overrides'=>'user_id'] as $table=>$key) Schema::create($table,function(Blueprint $t) use($key) { $t->integer($key); $t->integer('permission_id'); if($key==='user_id') $t->boolean('allowed'); $t->timestamps(); });
    Schema::create('access_user_campuses',function(Blueprint $t) { $t->integer('user_id'); $t->integer('campus_id'); $t->boolean('is_primary')->default(true); $t->timestamp('assigned_at')->nullable(); $t->timestamps(); });
    Schema::create('access_departments',function(Blueprint $t) { $t->id(); $t->string('name'); $t->string('code')->nullable()->unique(); $t->integer('status')->default(1); $t->timestamps(); });
    Schema::create('access_positions',function(Blueprint $t) { $t->id(); $t->string('name'); $t->string('code')->nullable()->unique(); $t->integer('department_id')->nullable(); $t->integer('status')->default(1); $t->timestamps(); });
    Schema::create('tb_school_info',function(Blueprint $t) { $t->id(); $t->string('campus_name_en'); $t->string('campus_name_kh')->nullable(); $t->integer('status')->default(1); $t->softDeletes(); });
    Schema::create('tb_country',function(Blueprint $t) { $t->id(); $t->string('country_name_en'); $t->string('country_name_kh')->nullable(); $t->string('nationality_name_en')->nullable(); $t->string('nationality_name_kh')->nullable(); $t->string('country_code')->nullable(); $t->string('flag_path')->nullable(); $t->boolean('status')->default(true); });
    DB::table('tb_country')->insert([
        ['id'=>1,'country_name_en'=>'Cambodia','country_name_kh'=>'កម្ពុជា','nationality_name_en'=>'Cambodian','nationality_name_kh'=>'ខ្មែរ','country_code'=>'kh','flag_path'=>'flags/cambodia.svg'],
        ['id'=>2,'country_name_en'=>'Thailand','country_name_kh'=>'ថៃ','nationality_name_en'=>'Thai','nationality_name_kh'=>'ថៃ','country_code'=>'th','flag_path'=>'flags/thailand.svg'],
    ]);
    Schema::create('tb_province',function(Blueprint $t) { $t->id(); $t->integer('country_id')->default(1); $t->string('province_name_en'); $t->string('province_name_kh')->nullable(); $t->boolean('status')->default(true); });
    foreach(['district'=>'province','commune'=>'district','village'=>'commune'] as $level=>$parent) Schema::create('tb_'.$level,function(Blueprint $t) use($level,$parent) {
        $t->id(); $t->integer($parent.'_id'); $t->string($level.'_name_en'); $t->string($level.'_name_kh')->nullable(); $t->boolean('status')->default(true);
    });
    Schema::create('homeroom_assignments',function(Blueprint $t) { $t->id(); $t->integer('teacher_id'); $t->integer('academic_year_id'); $t->integer('status')->default(1); });
    // The legacy migration uses a MySQL joined UPDATE; recreate its starting schema for SQLite.
    Schema::create('hrm_staff',function(Blueprint $t) {
        $t->id(); $t->string('staff_code',50)->unique(); $t->string('name_en');
        foreach(['name_kh','gender','phone','email','photo_path'] as $key) $t->string($key)->nullable();
        foreach(['position_id','department_id','primary_campus_id','created_by','updated_by'] as $key) $t->integer($key)->nullable();
        foreach(['date_of_birth','joined_on','ended_on'] as $key) $t->date($key)->nullable();
        $t->string('staff_category')->default('staff'); $t->string('employment_type')->default('FT'); $t->string('employment_status')->default('active');
        $t->text('notes')->nullable(); $t->boolean('status')->default(true); $t->timestamps(); $t->softDeletes();
    });
    Schema::create('hrm_staff_campuses',function(Blueprint $t) { $t->integer('staff_id'); $t->integer('campus_id'); $t->boolean('is_primary')->default(false); $t->boolean('status')->default(true); $t->timestamp('assigned_at')->nullable(); $t->timestamps(); $t->primary(['staff_id','campus_id']); });
    Schema::table('users',fn(Blueprint $t)=>$t->integer('staff_profile_id')->nullable());
    foreach(['view','create','edit','delete'] as $action) DB::table('access_permissions')->insert(['code'=>'staff.'.$action,'name'=>$action,'module'=>'staff','action'=>$action]);
    (require database_path('migrations/2026_09_29_000003_add_staff_profile_details.php'))->up();
    (require database_path('migrations/2026_10_13_000001_expand_staff_profiles_and_work_history.php'))->up();
    (require database_path('migrations/2026_10_13_000002_add_staff_contract_and_identity_details.php'))->up();
    (require database_path('migrations/2026_10_13_000003_add_parent_to_departments.php'))->up();
    (require database_path('migrations/2026_10_13_000004_add_birth_locations_to_staff.php'))->up();
    (require database_path('migrations/2026_10_13_000005_add_nationality_country_to_staff.php'))->up();
    (require database_path('migrations/2026_10_13_000006_add_address_locations_to_staff.php'))->up();
    (require database_path('migrations/2026_10_13_000007_add_staff_identity_issue_details.php'))->up();
    (require database_path('migrations/2026_08_04_000023_create_user_notifications_table.php'))->up();
    DB::table('users')->insert(['id'=>1,'name'=>'HR Manager','active_campus_id'=>1]);
    DB::table('access_roles')->insert(['id'=>1,'code'=>'super-admin','name'=>'Super Admin']);
    DB::table('access_user_roles')->insert(['user_id'=>1,'role_id'=>1]);
    (require database_path('migrations/2026_10_13_000008_sync_hrm_permissions.php'))->up();
    $this->permissionUser=function(array $codes) {
        $id=DB::table('users')->insertGetId(['name'=>'Permission Tester','active_campus_id'=>1,'is_global'=>true,'status'=>1]);
        foreach($codes as $code) DB::table('access_user_permission_overrides')->insert(['user_id'=>$id,'permission_id'=>DB::table('access_permissions')->where('code',$code)->value('id'),'allowed'=>true]);
        return User::find($id);
    };
    $this->csvUpload=function(array $headers,array $rows) {
        $stream=fopen('php://temp','w+');fwrite($stream,"\xEF\xBB\xBF");fputcsv($stream,$headers,',','"','');
        foreach($rows as $row) fputcsv($stream,$row,',','"','');
        rewind($stream);$text=stream_get_contents($stream);fclose($stream);
        return \Illuminate\Http\UploadedFile::fake()->createWithContent('staff.csv',$text);
    };
    DB::table('access_departments')->insert(['id'=>1,'name'=>'Academics']);
    DB::table('access_positions')->insert([['id'=>1,'name'=>'Teacher','department_id'=>1],['id'=>2,'name'=>'Coordinator','department_id'=>1]]);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'BCH'],['id'=>2,'campus_name_en'=>'STD']]);
    $this->actor=User::find(1); $this->actingAs($this->actor);
    $this->url='/hrm/staff-management';
    $this->data=['staff_code'=>'ST-001','name_en'=>'SOK DARA','name_kh'=>'សុខ ដារា','gender'=>'female','date_of_birth'=>'1990-10-12',
        'nationality'=>'Cambodian','nationality_kh'=>'ខ្មែរ','marital_status'=>'married','place_of_birth_en'=>'Phnom Penh','place_of_birth_kh'=>'ភ្នំពេញ',
        'phone'=>'012345678','email'=>'dara@example.com','house_number_en'=>'12A','street_en'=>'123','village_en'=>'Village 1','village_kh'=>'ភូមិ១',
        'commune_en'=>'Boeung Keng Kang','commune_kh'=>'បឹងកេងកង','district_en'=>'Chamkar Mon','district_kh'=>'ចំការមន','province_en'=>'Phnom Penh','province_kh'=>'ភ្នំពេញ',
        'current_address'=>'12A, Street 123, Phnom Penh','current_address_kh'=>'ផ្ទះ១២A ផ្លូវ១២៣ ភ្នំពេញ','notes'=>'Staff remarks',
        'position_id'=>1,'department_id'=>1,'primary_campus_id'=>1,'campuses'=>[1],'staff_category'=>'teacher','employment_type'=>'FT','employment_status'=>'active','joined_on'=>'2024-01-01','status'=>'1',
        'session_1_in'=>'07:30','session_1_out'=>'11:30','session_2_in'=>'13:00','session_2_out'=>'17:00',
        'work_schedule'=>['mon'=>['period'=>'morning'],'tue'=>['period'=>'full_day'],'fri'=>['period'=>'afternoon','session_2_in'=>'14:00','session_2_out'=>'18:00']],
        'educations'=>[['degree'=>'Bachelor','field_of_study'=>'Education','institution_name'=>'University','country'=>'Cambodia','graduated_year'=>2014,'notes'=>'Graduated with honors']],
        'experiences'=>[['company_name'=>'Previous School','position'=>'Teacher','started_on'=>'2015-01-01','ended_on'=>'2023-12-31','responsibilities'=>'Taught primary grades']],
    ];
    $this->create=function() { $this->post($this->url,$this->data)->assertSessionHasNoErrors()->assertRedirect($this->url); return Staff::first(); };
});

it('saves bilingual staff details two sessions education and previous employment',function() {
    $data=$this->data+['house_number_kh'=>'Forged','gender_kh'=>'Forged','nationality_note'=>'International'];
    $this->post($this->url,$data)->assertSessionHasNoErrors()->assertRedirect($this->url);
    $staff=Staff::with(['educations','experiences','workHistories'])->first();
    expect($staff->gender)->toBe('Female')->and($staff->gender_kh)->toBe('ស្រី')->and($staff->date_of_birth_kh)->toBe('១២ តុលា ១៩៩០')
        ->and($staff->marital_status_kh)->toBe('រៀបការ')->and($staff->nationality_note)->toBe('Cambodian')->and($staff->house_number_kh)->toBe('១២A')->and($staff->street_kh)->toBe('១២៣')
        ->and($staff->current_address_kh)->toBe($this->data['current_address_kh'])->and($staff->educations->first()->country)->toBe('Cambodia')
        ->and((int)$staff->educations->first()->graduated_year)->toBe(2014)->and($staff->experiences->first()->responsibilities)->toBe('Taught primary grades');
    expect($staff->work_schedule)->toHaveKeys(['mon','tue','fri'])->and($staff->work_schedule['mon']['session_2_in'])->toBeNull()
        ->and($staff->effective_work_schedule['tue']['session_1_in'])->toBe('07:30')->and($staff->work_schedule['fri']['session_1_in'])->toBeNull()
        ->and($staff->work_schedule['fri']['session_2_in'])->toBe('14:00');
    expect($staff->workHistories)->toHaveCount(1)->and($staff->workHistories->first()->change_type)->toBe('hired');
    $this->get($this->url.'?view='.$staff->id)->assertOk()->assertSee('Staff Name (Khmer)')->assertSee('Year of Graduation')->assertSee('Previous Employment')->assertSee('Work History')->assertSee('១២ តុលា ១៩៩០');
    $response=$this->get($this->url.'?edit='.$staff->id)->assertOk()->assertSee('name="place_of_birth_kh"',false)->assertSee('name="work_schedule[mon][period]"',false)->assertSee('Promotion')->assertSee('name="educations[0][country]"',false);
    $dom=new \DOMDocument(); $dom->loadHTML($response->getContent(),LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath=new \DOMXPath($dom);
    expect($xpath->query('//div[@data-staff-day]'))->toHaveCount(7)
        ->and($xpath->query('//div[@data-staff-day]/label/input[@type="time"]'))->toHaveCount(28);
});

it('uses Location province records for the staff birthplace and derives Khmer birth dates',function() {
    DB::table('tb_province')->insert(['id'=>1,'province_name_en'=>'Kampong Cham','province_name_kh'=>'កំពង់ចាម','status'=>1]);
    $this->post($this->url,array_replace($this->data,['birth_province_id'=>1,'place_of_birth_en'=>'Forged','place_of_birth_kh'=>'Forged','date_of_birth'=>'1979-09-11','date_of_birth_kh'=>'Forged']))->assertSessionHasNoErrors();
    $staff=Staff::first();
    expect($staff->place_of_birth_en)->toBe('Kampong Cham')->and($staff->place_of_birth_kh)->toBe('កំពង់ចាម')
        ->and($staff->date_of_birth_kh)->toBe('១១ កញ្ញា ១៩៧៩');
    $response=$this->get($this->url.'?edit='.$staff->id)->assertOk();
    $dom=new \DOMDocument(); $dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);
    $xpath=new \DOMXPath($dom);
    expect($xpath->query('//select[@data-staff-birth-province]/option[@value="1" and @selected]'))->toHaveCount(1)
        ->and($xpath->query('//input[@name="date_of_birth" and @type="hidden" and @value="1979-09-11"]'))->toHaveCount(1)
        ->and($xpath->query('//input[@data-premium-date-input and @value="11-Sep-1979"]'))->toHaveCount(1)
        ->and($xpath->query('//div[@data-premium-date-picker]/output[@data-staff-birth-date-kh]')->item(0)->textContent)->toBe('១១ កញ្ញា ១៩៧៩')
        ->and($xpath->query('//input[@name="date_of_birth_kh"]'))->toHaveCount(0)
        ->and($xpath->query('//div[contains(@class,"staff-personal-fields-grid")]/div[6]//select[@data-staff-marital-status]'))->toHaveCount(1)
        ->and($xpath->query('//input[@name="place_of_birth_kh" and @type="hidden" and @value="កំពង់ចាម"]'))->toHaveCount(1)
        ->and($xpath->query('//input[@name="place_of_birth_kh" and @type!="hidden"]'))->toHaveCount(0);
});

it('shows active birth provinces and retains existing birthplace entries for editing',function() {
    DB::table('tb_province')->insert([
        ['id'=>1,'province_name_en'=>'Phnom Penh','province_name_kh'=>'ភ្នំពេញ','status'=>0],
        ['id'=>2,'province_name_en'=>'Kampong Cham','province_name_kh'=>'កំពង់ចាម','status'=>1],
        ['id'=>3,'province_name_en'=>'Retired Province','province_name_kh'=>'ខេត្តចាស់','status'=>0],
    ]);
    $staff=($this->create)();
    $response=$this->get($this->url.'?edit='.$staff->id)->assertOk()->assertSee('data-en="Phnom Penh"',false)->assertSee('data-en="Kampong Cham"',false)->assertDontSee('Retired Province');
    $staff->update(['place_of_birth_en'=>'Historical birthplace','place_of_birth_kh'=>'ទីកន្លែងកំណើតចាស់']);
    $this->get($this->url.'?edit='.$staff->id)->assertOk()->assertSee('data-en="Historical birthplace"',false);
    $this->post($this->url,array_replace($this->data,['staff_id'=>$staff->id,'birth_province_id'=>'','place_of_birth_en'=>'Historical birthplace','place_of_birth_kh'=>'ទីកន្លែងកំណើតចាស់']))->assertSessionHasNoErrors();
    expect($staff->fresh()->place_of_birth_en)->toBe('Historical birthplace')->and($staff->fresh()->place_of_birth_kh)->toBe('ទីកន្លែងកំណើតចាស់');
});

it('rejects invalid birthplace selections without creating staff',function() {
    $this->post($this->url,$this->data+['birth_province_id'=>999])->assertSessionHasErrors('birth_province_id');
    expect(Staff::count())->toBe(0);
});

it('defaults staff birth country to Cambodia and filters province options by country',function() {
    DB::table('tb_province')->insert([
        ['id'=>1,'country_id'=>1,'province_name_en'=>'Phnom Penh','province_name_kh'=>'ភ្នំពេញ'],
        ['id'=>2,'country_id'=>2,'province_name_en'=>'Bangkok','province_name_kh'=>'បាងកក'],
    ]);
    $response=$this->get($this->url.'?create=1')->assertOk();
    $dom=new \DOMDocument(); $dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);
    $xpath=new \DOMXPath($dom);
    expect($xpath->query('//select[@name="birth_country_id"]/option[@value="1" and @selected and contains(@data-flag,"flags/cambodia.svg")]'))->toHaveCount(1)
        ->and($xpath->query('//select[@name="birth_province_id"]/option[@value="1" and not(@disabled)]'))->toHaveCount(1)
        ->and($xpath->query('//select[@name="birth_province_id"]/option[@value="2" and @disabled and @hidden]'))->toHaveCount(1);
    $data=array_replace($this->data,['birth_country_id'=>2,'birth_province_id'=>2]);
    $this->post($this->url,$data)->assertSessionHasNoErrors();
    $staff=Staff::first();
    expect($staff->birth_country_id)->toBe(2)->and($staff->birth_province_id)->toBe(2)
        ->and($staff->place_of_birth_en)->toBe('Bangkok')->and($staff->place_of_birth_kh)->toBe('បាងកក');
    $this->get($this->url.'?view='.$staff->id)->assertOk()->assertSee('Country of Birth')->assertSee('Thailand')->assertSee('ថៃ')->assertSee('flags/thailand.svg');
    $edit=$this->get($this->url.'?edit='.$staff->id)->assertOk();
    $dom->loadHTML('<?xml encoding="UTF-8">'.$edit->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);
    $xpath=new \DOMXPath($dom);
    expect($xpath->query('//select[@name="birth_country_id"]/option[@value="2" and @selected]'))->toHaveCount(1)
        ->and($xpath->query('//select[@name="birth_province_id"]/option[@value="2" and @selected and not(@disabled)]'))->toHaveCount(1);
});

it('rejects birth provinces belonging to another country and unknown countries',function() {
    DB::table('tb_province')->insert(['id'=>1,'country_id'=>1,'province_name_en'=>'Phnom Penh','province_name_kh'=>'ភ្នំពេញ']);
    $this->post($this->url,$this->data+['birth_country_id'=>2,'birth_province_id'=>1])->assertSessionHasErrors('birth_province_id');
    $this->post($this->url,$this->data+['birth_country_id'=>999])->assertSessionHasErrors('birth_country_id');
    expect(Staff::count())->toBe(0);
});

it('uses Location nationality names and flags and saves canonical names independently of birth country',function() {
    $response=$this->get($this->url.'?create=1')->assertOk();
    $dom=new \DOMDocument(); $dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);
    $xpath=new \DOMXPath($dom);
    expect($xpath->query('//select[@data-staff-nationality]/option[@value="1" and @selected and @data-en="Cambodian" and @data-kh="ខ្មែរ" and contains(@data-flag,"flags/cambodia.svg")]'))->toHaveCount(1)
        ->and($xpath->query('//input[@name="nationality_kh" and @type="hidden"]'))->toHaveCount(1)
        ->and($xpath->query('//input[@name="nationality_note" and @type="hidden"]'))->toHaveCount(1)
        ->and($xpath->query('//input[(@name="nationality_kh" or @name="nationality_note") and @type!="hidden"]'))->toHaveCount(0);
    $this->post($this->url,array_replace($this->data,['nationality_country_id'=>1,'nationality'=>'Forged','nationality_kh'=>'Forged','birth_country_id'=>2]))->assertSessionHasNoErrors();
    $staff=Staff::first();
    expect($staff->nationality_country_id)->toBe(1)->and($staff->nationality)->toBe('Cambodian')->and($staff->nationality_kh)->toBe('ខ្មែរ')
        ->and($staff->nationality_note)->toBe('Cambodian')->and($staff->birth_country_id)->toBe(2);
    $this->post($this->url,array_replace($this->data,['staff_id'=>$staff->id,'nationality_country_id'=>2]))->assertSessionHasNoErrors();
    expect($staff->fresh()->nationality_country_id)->toBe(2)->and($staff->fresh()->nationality)->toBe('Thai')->and($staff->fresh()->nationality_kh)->toBe('ថៃ')
        ->and($staff->fresh()->nationality_note)->toBe('International')->and($staff->fresh()->birth_country_id)->toBe(2);
    $this->post($this->url,array_replace($this->data,['staff_id'=>$staff->id,'nationality_country_id'=>'','nationality'=>'','nationality_kh'=>'']))->assertSessionHasNoErrors();
    expect($staff->fresh()->nationality_country_id)->toBeNull()->and($staff->fresh()->nationality)->toBeNull()->and($staff->fresh()->nationality_note)->toBe('');
});

it('preserves legacy nationality names and inactive selected nationality records',function() {
    $this->post($this->url,$this->data+['nationality_country_id'=>2])->assertSessionHasNoErrors();
    $staff=Staff::first();
    DB::table('tb_country')->where('id',2)->update(['status'=>0]);
    $response=$this->get($this->url.'?edit='.$staff->id)->assertOk();
    $dom=new \DOMDocument(); $dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);
    $xpath=new \DOMXPath($dom);
    expect($xpath->query('//select[@data-staff-nationality]/option[@value="2" and @selected]'))->toHaveCount(1);
    $this->get($this->url.'?create=1')->assertOk()->assertDontSee('data-en="Thai"',false);
    $staff->update(['nationality_country_id'=>null,'nationality'=>'Historical nationality','nationality_kh'=>'សញ្ជាតិចាស់']);
    $this->get($this->url.'?edit='.$staff->id)->assertOk()->assertSee('data-en="Historical nationality"',false);
});

it('rejects unknown nationality records without creating staff',function() {
    $this->post($this->url,$this->data+['nationality_country_id'=>999])->assertSessionHasErrors('nationality_country_id');
    expect(Staff::count())->toBe(0);
});

it('clears the previous province when birth country changes and retains an inactive saved country',function() {
    DB::table('tb_province')->insert(['id'=>1,'country_id'=>1,'province_name_en'=>'Phnom Penh','province_name_kh'=>'ភ្នំពេញ']);
    $this->post($this->url,$this->data+['birth_country_id'=>1,'birth_province_id'=>1])->assertSessionHasNoErrors();
    $staff=Staff::first();
    $this->post($this->url,$this->data+['staff_id'=>$staff->id,'birth_country_id'=>2])->assertSessionHasNoErrors();
    expect($staff->fresh()->birth_country_id)->toBe(2)->and($staff->fresh()->birth_province_id)->toBeNull()
        ->and($staff->fresh()->place_of_birth_en)->toBeNull()->and($staff->fresh()->place_of_birth_kh)->toBeNull();
    DB::table('tb_country')->where('id',2)->update(['status'=>0]);
    $this->get($this->url.'?edit='.$staff->id)->assertOk()->assertSee('data-en="Thailand"',false);
    $this->get($this->url.'?create=1')->assertOk()->assertDontSee('data-en="Thailand"',false);
});

it('preserves promotions campus transfers and status changes with actor and dated work snapshots',function() {
    $staff=($this->create)();
    $data=array_replace($this->data,['staff_id'=>$staff->id,'position_id'=>2,'work_change_type'=>'promotion','work_effective_on'=>'2026-10-01','work_change_reason'=>'Promoted to coordinator']);
    $this->post($this->url,$data)->assertSessionHasNoErrors();
    $promotion=StaffWorkHistory::latest('id')->first();
    expect($promotion->change_type)->toBe('promotion')->and($promotion->before_snapshot['position'])->toBe('Teacher')->and($promotion->after_snapshot['position'])->toBe('Coordinator')
        ->and($promotion->effective_on->format('Y-m-d'))->toBe('2026-10-01')->and($promotion->changed_by_name)->toBe('HR Manager');
    $data=array_replace($data,['primary_campus_id'=>2,'campuses'=>[2],'work_change_type'=>'campus_transfer']);
    $this->post($this->url,$data)->assertSessionHasNoErrors();
    expect(StaffWorkHistory::latest('id')->first()->before_snapshot['primary_campus'])->toBe('BCH')
        ->and(StaffWorkHistory::latest('id')->first()->after_snapshot['primary_campus'])->toBe('STD');
    $data=array_replace($data,['employment_status'=>'terminated','ended_on'=>'2026-10-13','employment_end_reason'=>'Contract ended','work_change_type'=>'termination']);
    $this->post($this->url,$data)->assertSessionHasNoErrors();
    expect(StaffWorkHistory::count())->toBe(4)->and(Staff::find($staff->id)->employment_status)->toBe('terminated');
    DB::table('access_positions')->where('id',1)->update(['name'=>'Renamed Teacher']);
    DB::table('users')->where('id',1)->update(['name'=>'Renamed Manager']);
    expect($promotion->fresh()->before_snapshot['position'])->toBe('Teacher')->and($promotion->fresh()->changed_by_name)->toBe('HR Manager');
});

it('does not create work history for personal edits or repeated unchanged saves',function() {
    $staff=($this->create)(); $data=$this->data+['staff_id'=>$staff->id];
    $this->post($this->url,array_replace($data,['phone'=>'098765432','notes'=>'Personal edit']))->assertSessionHasNoErrors();
    $this->post($this->url,$data)->assertSessionHasNoErrors();
    expect(StaffWorkHistory::count())->toBe(1);
});

it('preserves baseline work and existing education and employment when updating older profiles',function() {
    $staff=($this->create)(); StaffWorkHistory::query()->delete();
    $data=array_replace($this->data,['staff_id'=>$staff->id,'position_id'=>2]); unset($data['educations'],$data['experiences'],$data['work_schedule']);
    $this->post($this->url,$data)->assertSessionHasNoErrors();
    expect(StaffWorkHistory::count())->toBe(2)->and(StaffWorkHistory::oldest('id')->first()->change_type)->toBe('baseline')
        ->and(StaffWorkHistory::oldest('id')->first()->after_snapshot['position'])->toBe('Teacher')
        ->and($staff->educations()->count())->toBe(1)->and($staff->experiences()->count())->toBe(1)->and($staff->fresh()->work_schedule)->toHaveKey('mon');
});

it('validates session ranges day overrides dates and incomplete education rows before saving',function(array $change,string $error) {
    $data=array_replace($this->data,$change);
    $this->post($this->url,$data)->assertSessionHasErrors($error);
    expect(Staff::count())->toBe(0)->and(StaffWorkHistory::count())->toBe(0);
})->with([
    'reversed session'=>[['session_1_out'=>'07:00'],'session_1_out'],
    'overlapping sessions'=>[['session_2_in'=>'11:00'],'session_2_in'],
    'half a session'=>[['session_1_out'=>null],'session_1_out'],
    'bad day override'=>[['work_schedule'=>['mon'=>['period'=>'morning','session_1_in'=>'12:00','session_1_out'=>'11:00']]],'work_schedule.mon.session_1_out'],
    'unknown day'=>[['work_schedule'=>['other'=>['period'=>'full_day']]],'work_schedule'],
    'future birth'=>[['date_of_birth'=>'2100-01-01'],'date_of_birth'],
    'leaving before hire'=>[['employment_status'=>'resigned','ended_on'=>'2020-01-01'],'ended_on'],
    'missing termination date'=>[['employment_status'=>'terminated'],'ended_on'],
    'education without institution'=>[['educations'=>[['degree'=>'Bachelor']]],'educations.0.institution_name'],
    'future graduation'=>[['educations'=>[['institution_name'=>'School','graduated_year'=>2100]]],'educations.0.graduated_year'],
    'previous employment dates'=>[['experiences'=>[['company_name'=>'Employer','started_on'=>'2020-01-01','ended_on'=>'2019-01-01']]],'experiences.0.ended_on'],
]);

it('supports multiple education and employment rows and explicitly removing them all',function() {
    $data=$this->data; $data['educations'][]=['degree'=>'Master','institution_name'=>'Other University','country'=>'Japan','graduated_year'=>2020];
    $data['experiences'][]=['company_name'=>'Other Employer','position'=>'Assistant'];
    $this->post($this->url,$data)->assertSessionHasNoErrors(); $staff=Staff::first();
    expect($staff->educations()->count())->toBe(2)->and($staff->experiences()->count())->toBe(2);
    unset($data['educations'],$data['experiences']); $data+=['staff_id'=>$staff->id,'educations_present'=>1,'experiences_present'=>1];
    $this->post($this->url,$data)->assertSessionHasNoErrors();
    expect($staff->educations()->count())->toBe(0)->and($staff->experiences()->count())->toBe(0)->and(StaffWorkHistory::count())->toBe(1);
});

it('keeps all removed education and employment rows removed when redisplaying a validation error',function() {
    $staff=($this->create)();
    $editUrl=$this->url.'?edit='.$staff->id;
    $data=$this->data+['staff_id'=>$staff->id,'educations_present'=>1,'experiences_present'=>1];
    unset($data['educations'],$data['experiences']); $data['email']='invalid-email';
    $this->from($editUrl)->post($this->url,$data)->assertSessionHasErrors('email')->assertRedirect($editUrl);
    $this->get($editUrl)->assertOk()->assertDontSee('name="educations[0][institution_name]"',false)
        ->assertDontSee('name="experiences[0][company_name]"',false);
    expect($staff->educations()->count())->toBe(1)->and($staff->experiences()->count())->toBe(1);
});

it('enforces staff editing permissions and keeps user campus assignments separate from HR changes',function() {
    $staff=($this->create)();
    DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>1]);
    DB::table('users')->where('id',1)->update(['staff_profile_id'=>$staff->id]);
    $this->post($this->url,array_replace($this->data,['staff_id'=>$staff->id,'campuses'=>[2],'primary_campus_id'=>2]))->assertSessionHasNoErrors();
    expect(DB::table('access_user_campuses')->where('user_id',1)->pluck('campus_id')->all())->toBe([1]);
    DB::table('users')->insert(['id'=>2,'name'=>'Viewer','active_campus_id'=>1]);
    $permission=DB::table('access_permissions')->where('code','staff.view')->value('id');
    DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>$permission,'allowed'=>true]);
    $this->actingAs(User::find(2))->postJson($this->url,$this->data+['staff_id'=>$staff->id])->assertForbidden();
    expect(StaffWorkHistory::count())->toBe(2);
});

it('classifies international nationalities without storing a user supplied classification',function() {
    $this->post($this->url,array_replace($this->data,['nationality'=>'Japanese','nationality_kh'=>'ជប៉ុន','nationality_note'=>'Cambodian']))->assertSessionHasNoErrors();
    expect(Staff::first()->nationality_note)->toBe('International');
});

it('default session updates reach default days while preserving daily overrides and historical times',function() {
    $staff=($this->create)();
    $data=array_replace($this->data,['staff_id'=>$staff->id,'session_1_in'=>'08:00','session_1_out'=>'12:00','session_2_in'=>'13:30','session_2_out'=>'17:30']);
    $this->post($this->url,$data)->assertSessionHasNoErrors();
    expect($staff->fresh()->effective_work_schedule['mon']['session_1_in'])->toBe('08:00')
        ->and($staff->fresh()->effective_work_schedule['tue']['session_2_in'])->toBe('13:30')
        ->and($staff->fresh()->effective_work_schedule['fri']['session_2_in'])->toBe('14:00');
    $history=StaffWorkHistory::latest('id')->first();
    expect($history->before_snapshot['work_schedule']['mon']['session_1_in'])->toBe('07:30')
        ->and($history->after_snapshot['work_schedule']['mon']['session_1_in'])->toBe('08:00');
});

it('staff expansion migration preserves legacy data and adds a history table without changing assignments',function() {
    $migration=require database_path('migrations/2026_10_13_000001_expand_staff_profiles_and_work_history.php');
    $migration->down();
    $staff=Staff::create(['staff_code'=>'OLD-001','name_en'=>'Existing Staff','nationality'=>'Cambodian','current_address'=>'Original address','position_id'=>1]);
    $staff->educations()->create(['institution_name'=>'Existing University','ended_on'=>'2010-06-01','notes'=>'Old note']);
    $staff->campuses()->attach(1,['is_primary'=>true,'status'=>true]);
    $migration->up();
    expect($staff->fresh()->current_address)->toBe('Original address')->and($staff->educations->first()->notes)->toBe('Old note')
        ->and($staff->campuses()->pluck('tb_school_info.id')->all())->toBe([1])->and(StaffWorkHistory::count())->toBe(0);
});

it('saving a leaving date permits an older staff record whose hire date is unknown',function() {
    $this->post($this->url,array_replace($this->data,['joined_on'=>null,'employment_status'=>'resigned','ended_on'=>'2026-10-13']))->assertSessionHasNoErrors();
    expect(Staff::first()->joined_on)->toBeNull()->and(Staff::first()->employment_status)->toBe('resigned');
});

it('saves optional HR fields and preserves contract and supervisor changes in history',function() {
    $supervisor=Staff::create(['staff_code'=>'MANAGER-001','name_en'=>'Campus Manager']);
    $data=$this->data+['secondary_phone'=>'098123456','work_email'=>'dara@school.example','identity_document_type'=>'passport',
        'identity_document_number'=>'P123456','identity_document_expires_on'=>'2030-01-01','emergency_contact_address'=>'Phnom Penh',
        'contract_type'=>'fixed_term','contract_number'=>'CON-001','contract_started_on'=>'2024-01-01','contract_ends_on'=>'2026-11-01',
        'probation_ends_on'=>'2024-04-01','reports_to_staff_id'=>$supervisor->id];
    $this->post($this->url,$data)->assertSessionHasNoErrors();
    $staff=Staff::where('staff_code','ST-001')->first();
    expect($staff->work_email)->toBe('dara@school.example')->and($staff->contract_ends_on->format('Y-m-d'))->toBe('2026-11-01')
        ->and($staff->supervisor->name_en)->toBe('Campus Manager')->and($staff->identity_document_number)->toBe('P123456');
    $this->post($this->url,array_replace($data,['staff_id'=>$staff->id,'contract_ends_on'=>'2027-11-01','work_change_reason'=>'Contract renewed']))->assertSessionHasNoErrors();
    $history=StaffWorkHistory::latest('id')->first();
    expect($history->before_snapshot['contract_ends_on'])->toBe('2026-11-01')->and($history->after_snapshot['contract_ends_on'])->toBe('2027-11-01')
        ->and($history->after_snapshot['supervisor'])->toBe('Campus Manager');
    $supervisor->update(['name_en'=>'Renamed Manager']);
    expect($history->fresh()->after_snapshot['supervisor'])->toBe('Campus Manager');
    $supervisor->update(['employment_status'=>'retired']);
    $editResponse=$this->get($this->url.'?edit='.$staff->id)->assertOk()->assertSee('Renamed Manager')->assertSee('name="contract_ends_on"',false);
    $dom=new \DOMDocument(); $dom->loadHTML($editResponse->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);
    $xpath=new \DOMXPath($dom);
    foreach(['contract_started_on'=>['2024-01-01','01-Jan-2024'],'contract_ends_on'=>['2027-11-01','01-Nov-2027'],'probation_ends_on'=>['2024-04-01','01-Apr-2024']] as $name=>[$stored,$display]) {
        expect($xpath->query('//section[@aria-labelledby="staffContractProbationTitle"]//input[@name="'.$name.'" and @type="hidden" and @value="'.$stored.'"]'))->toHaveCount(1)
            ->and($xpath->query('//input[@id="staff-'.$name.'-direct" and @data-premium-date-input and @value="'.$display.'"]'))->toHaveCount(1);
    }
    $response=$this->get($this->url.'?view='.$staff->id)->assertOk()->assertSee('P123456')->assertSee('Contract renewed')->assertSee('Qualifications');
});

it('normalizes staff contact numbers and keeps international dial codes with the contacts inside Personal Information',function() {
    $this->post($this->url,array_replace($this->data,['phone'=>'+66 81 234 5678','secondary_phone'=>'098123456']))->assertSessionHasNoErrors();
    $staff=Staff::first();
    expect($staff->phone)->toBe('+66812345678')->and($staff->secondary_phone)->toBe('+85598123456');
    $this->post($this->url,array_replace($this->data,['staff_id'=>$staff->id,'secondary_phone'=>'+1 (202) 555-0123']))->assertSessionHasNoErrors();
    expect($staff->fresh()->secondary_phone)->toBe('+12025550123');
    $response=$this->get($this->url.'?edit='.$staff->id)->assertOk();
    $dom=new \DOMDocument(); $dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);
    $xpath=new \DOMXPath($dom);
    expect($xpath->query('//section[@aria-labelledby="staffPersonalInformationTitle"]//div[@data-staff-phone]'))->toHaveCount(2)
        ->and($xpath->query('//section[@aria-labelledby="staffPersonalInformationTitle"]//input[@name="email" and @type="email" and not(@data-email-enhanced)]'))->toHaveCount(1)
        ->and($xpath->query('//div[contains(@class,"staff-personal-fields-grid")]/div[10]//input[@name="phone"]'))->toHaveCount(1)
        ->and($xpath->query('//div[contains(@class,"staff-personal-fields-grid")]/div[11]//input[@name="secondary_phone"]'))->toHaveCount(1)
        ->and($xpath->query('//div[contains(@class,"staff-personal-fields-grid")]/div[12]//input[@name="email"]'))->toHaveCount(1);
});

it('saves English staff names in uppercase on create and edit while preserving Khmer names',function() {
    $this->post($this->url,array_replace($this->data,['name_en'=>' Sok Dara ']))->assertSessionHasNoErrors();
    $staff=Staff::first();
    expect($staff->name_en)->toBe('SOK DARA')->and($staff->name_kh)->toBe($this->data['name_kh']);
    $this->post($this->url,array_replace($this->data,['staff_id'=>$staff->id,'name_en'=>'Chea Phearum']))->assertSessionHasNoErrors();
    expect($staff->fresh()->name_en)->toBe('CHEA PHEARUM')->and($staff->fresh()->name_kh)->toBe($this->data['name_kh']);
});
it('validates the additional contract and contact fields',function(array $change,string $error) {
    $this->post($this->url,array_replace($this->data,$change))->assertSessionHasErrors($error);
    expect(Staff::count())->toBe(0);
})->with([
    'contract dates'=>[['contract_started_on'=>'2026-10-13','contract_ends_on'=>'2026-10-12'],'contract_ends_on'],
    'probation before hire'=>[['probation_ends_on'=>'2023-12-31'],'probation_ends_on'],
    'work email'=>[['work_email'=>'invalid-email'],'work_email'],
    'personal email'=>[['email'=>'invalid-email'],'email'],
    'contract type'=>[['contract_type'=>'unknown'],'contract_type'],
    'missing supervisor'=>[['reports_to_staff_id'=>999],'reports_to_staff_id'],
]);

it('does not allow staff to report to themselves',function() {
    $staff=($this->create)();
    $this->post($this->url,$this->data+['staff_id'=>$staff->id,'reports_to_staff_id'=>$staff->id])->assertSessionHasErrors('reports_to_staff_id');
    expect($staff->fresh()->reports_to_staff_id)->toBeNull()->and(StaffWorkHistory::count())->toBe(1);
});

it('filters directory records and summary counts by campus category status and contact search',function() {
    $staff=($this->create)();
    $staff->update(['contract_ends_on'=>now()->addDays(10)->toDateString(),'work_email'=>'teacher@school.example']);
    Staff::create(['staff_code'=>'ST-OTHER','name_en'=>'Other Staff','staff_category'=>'staff','employment_status'=>'resigned']);
    $response=$this->get($this->url.'?campus_id=1&staff_category=teacher&employment_status=active&search=teacher%40school.example')->assertOk();
    expect($response->viewData('staff')->total())->toBe(1)->and($response->viewData('staffMetrics'))->toBe(['total'=>1,'active'=>1,'teachers'=>1,'contracts'=>1]);
    $response=$this->get($this->url.'?campus_id=2')->assertOk();
    expect($response->viewData('staff')->total())->toBe(0)->and($response->viewData('staffMetrics')['total'])->toBe(0);
});

it('validates inherited schedules when only default times are updated',function() {
    $staff=($this->create)(); $data=$this->data+['staff_id'=>$staff->id];
    unset($data['work_schedule']); $data['session_1_out']='13:30'; $data['session_2_in']='13:30';
    $staff->update(['work_schedule'=>['tue'=>['period'=>'full_day','session_2_in'=>'13:00','session_2_out'=>'17:00']]]);
    $this->post($this->url,$data)->assertSessionHasErrors('work_schedule.tue.session_2_in');
    expect($staff->fresh()->session_1_out)->toBe('11:30');
});

it('creates a linked login using staff details and keeps login and HRM photos separate',function() {
    $staff=($this->create)();
    Storage::disk('public')->put('staff/original.jpg','Original HRM photo');
    $staff->update(['photo_path'=>'staff/original.jpg']); $original=$staff->fresh()->getAttributes();
    $this->post('/settings/users',['staff_profile_id'=>$staff->id,'username'=>'dara.login','email'=>'login@school.example',
        'role_id'=>1,'campuses'=>[2],'login_identifier'=>'both','photo'=>\Illuminate\Http\UploadedFile::fake()->image('login.jpg')])
        ->assertSessionHasNoErrors()->assertRedirect('/settings/users');
    $login=User::where('username','dara.login')->first();
    expect($login->staff_id)->toBe($staff->staff_code)->and($login->name)->toBe($staff->name_en)
        ->and($login->gender)->toBe('Female')->and($login->phone)->toBe($staff->phone)
        ->and($login->position_id)->toBe($staff->position_id)->and($login->department_id)->toBe($staff->department_id)
        ->and($login->date_of_birth->format('Y-m-d'))->toBe('1990-10-12')->and($login->email)->toBe('login@school.example')
        ->and($login->photo_path)->toStartWith('users/')->and($staff->fresh()->getAttributes())->toBe($original)
        ->and($login->campuses->modelKeys())->toBe([2])->and($staff->campuses->modelKeys())->toBe([1]);
    Storage::disk('public')->assertExists($login->photo_path); Storage::disk('public')->assertExists('staff/original.jpg');
    expect($login->photo_path)->toEndWith('.webp')
        ->and(Storage::disk('public')->size($login->photo_path))->toBeLessThanOrEqual(40*1024);
    $firstLoginPhoto=$login->photo_path;
    $this->actingAs($login)->from('/profile')->post('/profile',['name'=>$login->name,'username'=>$login->username,
        'email'=>$login->email,'login_identifier'=>'both','password'=>'NewPassword123','password_confirmation'=>'NewPassword123',
        'photo'=>\Illuminate\Http\UploadedFile::fake()->image('my-login.jpg')])->assertSessionHasNoErrors();
    expect($login->fresh()->photo_path)->not->toBe($firstLoginPhoto)->and($staff->fresh()->getAttributes())->toBe($original);
    expect($login->fresh()->photo_path)->toEndWith('.webp')
        ->and(Storage::disk('public')->size($login->fresh()->photo_path))->toBeLessThanOrEqual(40*1024);
    Storage::disk('public')->assertMissing($firstLoginPhoto);
    Storage::disk('public')->assertExists('staff/original.jpg');
});

it('stores the cropped HRM staff photo without changing the linked login photo',function() {
    $staff=($this->create)();
    Storage::disk('public')->put('users/login-photo.jpg','Login photo');
    DB::table('users')->where('id',1)->update(['staff_profile_id'=>$staff->id,'photo_path'=>'users/login-photo.jpg']);
    $this->post($this->url,$this->data+['staff_id'=>$staff->id,'photo'=>\Illuminate\Http\UploadedFile::fake()->image('staff-photo.jpg',400,400)])
        ->assertSessionHasNoErrors();
    $photoPath=$staff->fresh()->photo_path;
    expect($photoPath)->toStartWith('staff/')->and(User::find(1)->photo_path)->toBe('users/login-photo.jpg');
    Storage::disk('public')->assertExists($photoPath);
    Storage::disk('public')->assertExists('users/login-photo.jpg');
    $image=getimagesize(Storage::disk('public')->path($photoPath));
    expect($image[0])->toBe(400)->and($image[1])->toBe(400);
    expect($image['mime'])->toBe('image/webp')->and(Storage::disk('public')->size($photoPath))->toBeLessThanOrEqual(40*1024);
    $response=$this->get($this->url.'?create=1')->assertOk()->assertSee('data-staff-photo-dropzone',false)->assertSee('Staff Photo Studio')->assertSee('Exposure')->assertSee('Saturation');
    $dom=new \DOMDocument(); $dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);
    expect((new \DOMXPath($dom))->query('//div[@id="hrmStaffPhotoCropModal"]//canvas[@width="400" and @height="400"]'))->toHaveCount(1);
    expect((new \DOMXPath($dom))->query('//button[@data-staff-directory-photo]'))->toHaveCount(1)
        ->and((new \DOMXPath($dom))->query('//div[@id="staffDirectoryPhotoModal"]//a[@data-photo-download]'))->toHaveCount(1);
});

it('lists only unlinked staff and preserves the linked profile when editing an existing user',function() {
    $staff=($this->create)();
    DB::table('users')->where('id',1)->update(['staff_profile_id'=>$staff->id]);
    $unlinked=Staff::create(['staff_code'=>'FREE-001','name_en'=>'Available Staff']);
    $response=$this->get('/settings/users?create=1')->assertOk();
    expect($response->viewData('staffProfiles')->modelKeys())->toBe([$unlinked->id]);
    $staff->update(['status'=>false]);
    $response=$this->get('/settings/users?edit=1')->assertOk();
    expect($response->viewData('staffProfiles')->modelKeys())->toContain($staff->id);
    $this->post('/settings/users',['staff_profile_id'=>$staff->id,'staff_id'=>'ANOTHER-ID','name'=>'Another Account','username'=>'duplicate',
        'email'=>'duplicate@school.example','role_id'=>1,'campuses'=>[1],'login_identifier'=>'both'])->assertSessionHasErrors('staff_profile_id');
    expect(User::count())->toBe(1);
});

it('clears blank linked staff fields on account updates without changing HRM information',function() {
    $staff=($this->create)();
    DB::table('users')->where('id',1)->update(['staff_profile_id'=>$staff->id,'staff_id'=>$staff->staff_code,
        'username'=>'admin','email'=>'admin@school.example','phone'=>$staff->phone,'gender'=>'Female','position_id'=>1,'department_id'=>1]);
    $this->post('/settings/users',['user_id'=>1,'staff_profile_id'=>null,'staff_id'=>$staff->staff_code,'name'=>'Login Name','username'=>'admin',
        'email'=>'admin@school.example','role_id'=>1,'campuses'=>[1],'login_identifier'=>'both','status'=>'1',
        'phone'=>null,'gender'=>null,'position_id'=>null,'department_id'=>null])->assertSessionHasNoErrors();
    $login=User::find(1);
    expect($login->staff_profile_id)->toBeNull()->and($login->phone)->toBeNull()->and($login->position_id)->toBeNull()
        ->and($login->department_id)->toBeNull()->and($login->gender)->toBeNull()->and($staff->fresh()->name_en)->toBe('SOK DARA')
        ->and($staff->fresh()->phone)->toBe('+85512345678');
});

it('shows the shared settings menu only under HRM with the existing department and position permissions',function() {
    $html=view('layouts.partials.sidebar',['permissionCodes'=>['hrm.view','hrm-settings.view','departments.view','positions.view'],'branding'=>null])->render();
    $dom=new \DOMDocument(); $dom->loadHTML($html,LIBXML_NOERROR|LIBXML_NOWARNING); $xpath=new \DOMXPath($dom);
    $hrm=$xpath->query('//li[contains(@class,"dropdown")][a/span[@class="nav-link-title" and text()="HRM"]]')->item(0);
    expect($hrm)->not->toBeNull()->and($xpath->query('.//a[contains(@href,"/hrm/settings")]', $hrm))->toHaveCount(1)
        ->and($xpath->query('.//a[contains(@href,"/settings/departments") or contains(@href,"/settings/positions")]', $hrm))->toHaveCount(0)
        ->and($xpath->query('//a[contains(@href,"/hrm/staff-management")]'))->toHaveCount(0);
    $html=view('layouts.partials.sidebar',['permissionCodes'=>['*'],'branding'=>null])->render();
    $dom->loadHTML($html,LIBXML_NOERROR|LIBXML_NOWARNING); $xpath=new \DOMXPath($dom);
    $admin=$xpath->query('//li[contains(@class,"dropdown")][a/span[@class="nav-link-title" and text()="Administrator"]]')->item(0);
    expect($xpath->query('.//a[contains(@href,"/hrm/settings") or contains(@href,"/settings/departments") or contains(@href,"/settings/positions")]', $admin))->toHaveCount(0);
});

it('renders HRM settings with department and position popup forms',function() {
    $this->get('/hrm/settings')->assertOk()->assertSee('HRM Settings')->assertSee('New Department')->assertSee('Department List')
        ->assertSee('name="department_id"',false)->assertSee('action="'.route('hrm.settings.departments.save').'"',false);
    DB::table('access_departments')->where('id',1)->update(['status'=>0]);
    $response=$this->get('/hrm/settings?tab=positions&edit=1')->assertOk()->assertSee('Edit Position')->assertSee('Academics (Inactive)')
        ->assertSee('action="'.route('hrm.settings.positions.save').'"',false);
    $dom=new \DOMDocument(); $dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING); $xpath=new \DOMXPath($dom);
    expect($xpath->query('//nav[@aria-label="HRM settings tabs"]/a'))->toHaveCount(3)
        ->and($xpath->query('//nav[@aria-label="HRM settings tabs"]/a[@aria-current="page"]')->item(0)->textContent)->toContain('Position')
        ->and($xpath->query('//input[@name="name" and @value="Teacher"]'))->toHaveCount(1);
    $this->get('/hrm/settings?tab=unknown')->assertNotFound();
    $this->get('/hrm/departments')->assertRedirect('/hrm/settings?tab=departments');
    $this->get('/hrm/positions')->assertRedirect('/hrm/settings?tab=positions');
});

it('saves HRM settings to the existing department and position records and returns to the selected tab',function() {
    $this->post('/hrm/settings/departments',['name'=>'Human Resources','code'=>'HR','status'=>'1'])
        ->assertSessionHasNoErrors()->assertRedirect(route('hrm.settings.index',['tab'=>'departments']));
    $department=\App\Models\Department::where('code','HR')->firstOrFail();
    $this->post('/hrm/settings/positions',['name'=>'HR Officer','code'=>'HR-OFFICER','department_id'=>$department->id,'status'=>'1'])
        ->assertSessionHasNoErrors()->assertRedirect(route('hrm.settings.index',['tab'=>'positions']));
    $position=\App\Models\Position::where('code','HR-OFFICER')->firstOrFail();
    $this->post('/hrm/settings/positions',['position_id'=>$position->id,'name'=>'Senior HR Officer','code'=>'HR-OFFICER','department_id'=>$department->id,'status'=>'0'])
        ->assertSessionHasNoErrors()->assertRedirect(route('hrm.settings.index',['tab'=>'positions']));
    expect($position->fresh()->name)->toBe('Senior HR Officer')->and($position->fresh()->status)->toBeFalse();
    $response=$this->get('/hrm/settings?tab=positions&edit='.$position->id)->assertOk();
    $dom=new \DOMDocument(); $dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);
    expect((new \DOMXPath($dom))->query('//select[@id="hrmSettingStatus"]/option[@value="0" and @selected]'))->toHaveCount(1);
    $this->from('/hrm/settings?tab=departments')->post('/hrm/settings/departments',['name'=>'Duplicate','code'=>'HR','status'=>'1'])
        ->assertRedirect('/hrm/settings?tab=departments')->assertSessionHasErrors('code');
    // Existing endpoints still return to their original pages.
    $this->post('/settings/departments',['name'=>'Legacy Department','code'=>'LEGACY','status'=>'1'])->assertRedirect(route('departments.index'));
});

it('keeps HRM settings searches sorting pagination and editing within the active tab',function() {
    foreach(range(1,12) as $index) \App\Models\Position::create(['name'=>'Test Position '.str_pad($index,2,'0',STR_PAD_LEFT),'code'=>'TEST-'.$index,'department_id'=>1]);
    $response=$this->get('/hrm/settings?tab=positions&search=Test%20Position&sortBy=name&sortDir=desc&per_page=10&page=2')->assertOk();
    expect($response->viewData('positions')->total())->toBe(12)->and($response->viewData('positions')->currentPage())->toBe(2)
        ->and($response->viewData('positions')->first()->code)->toBe('TEST-2');
    $response->assertSee('tab=positions&amp;',false)->assertSee('name="tab" value="positions"',false);
});

it('preserves administrator access and prevents deleting settings assigned to users',function() {
    DB::table('users')->where('id',1)->update(['position_id'=>1,'department_id'=>1]);
    $this->from('/hrm/settings?tab=positions')->delete('/hrm/settings/positions/1')->assertSessionHasErrors('position');
    $this->from('/hrm/settings?tab=departments')->delete('/hrm/settings/departments/1')->assertSessionHasErrors('department');
    $this->delete('/hrm/settings/positions/2')->assertSessionHas('success');
    expect(\App\Models\Position::find(1))->not->toBeNull()->and(\App\Models\Position::find(2))->toBeNull();
    $user=User::create(['name'=>'Regular Staff','active_campus_id'=>1]);
    $this->actingAs($user)->get('/hrm/settings')->assertForbidden();
    $this->post('/hrm/settings/departments',['name'=>'Blocked','code'=>'BLOCKED','status'=>'1'])->assertForbidden();
    $this->delete('/hrm/settings/positions/1')->assertForbidden();
});

it('creates nested departments and displays the hierarchy across staff user position role and permission forms',function() {
    $registrar=\App\Models\Department::create(['name'=>'Registrar','code'=>'REG','parent_id'=>1]);
    $primary=\App\Models\Department::create(['name'=>'Primary Registrar','code'=>'REG-PRIMARY','parent_id'=>$registrar->id]);
    $this->get('/hrm/settings?tab=departments&edit='.$registrar->id)->assertOk()->assertSee('Parent Department')->assertSee('Academics › Registrar');
    $this->get('/settings/departments?edit='.$registrar->id)->assertOk()->assertSee('name="parent_id"',false)->assertSee('Academics › Registrar');
    $this->get('/hrm/staff-management?create=1')->assertOk()->assertSee('Academics › Registrar › Primary Registrar');
    $this->get('/settings/users?create=1')->assertOk()->assertSee('Academics › Registrar › Primary Registrar');
    $this->get('/settings/positions')->assertOk()->assertSee('Academics › Registrar › Primary Registrar');
    $this->get('/settings/roles')->assertOk()->assertSee('Academics › Registrar › Primary Registrar');
    $this->get('/settings/access')->assertOk()->assertSee('Academics › Registrar › Primary Registrar');
    Schema::table('users',fn(Blueprint $t)=>$t->timestamp('last_seen_at')->nullable());
    $this->get('/settings/notifications/send')->assertOk()->assertSee('Academics › Registrar › Primary Registrar');
    $this->get('/settings/departments/print')->assertOk()->assertSee('Academics › Registrar › Primary Registrar');
    expect($primary->hierarchy_name)->toBe('Academics › Registrar › Primary Registrar');
});

it('rejects self parenting descendants nonexistent parents and preserves the previous hierarchy on validation failure',function() {
    $root=\App\Models\Department::find(1); $root->update(['code'=>'ACA']);
    $child=\App\Models\Department::create(['name'=>'Registrar','code'=>'REG','parent_id'=>1]);
    $grandchild=\App\Models\Department::create(['name'=>'Primary Registrar','code'=>'REG-PRIMARY','parent_id'=>$child->id]);
    $url='/hrm/settings/departments';
    foreach([1,$child->id,$grandchild->id,9999] as $invalidParent) {
        $this->from('/hrm/settings?tab=departments&edit=1')->post($url,['department_id'=>1,'name'=>'Academics','code'=>'ACA','status'=>'1','parent_id'=>$invalidParent])
            ->assertSessionHasErrors('parent_id');
        expect($root->fresh()->parent_id)->toBeNull()->and($child->fresh()->parent_id)->toBe(1);
    }
    $response=$this->get('/hrm/settings?tab=departments&edit=1')->assertOk();
    $dom=new \DOMDocument(); $dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING); $xpath=new \DOMXPath($dom);
    foreach([1,$child->id,$grandchild->id] as $excluded) expect($xpath->query('//select[@name="parent_id"]/option[@value="'.$excluded.'" and not(@disabled)]'))->toHaveCount(0);
    $this->post($url,['department_id'=>$child->id,'name'=>'Registrar','code'=>'REG','status'=>'1','parent_id'=>''])->assertSessionHasNoErrors();
    expect($child->fresh()->parent_id)->toBeNull();
});

it('puts New Department in the list header and reopens the department modal with failed creation values',function() {
    $response=$this->get('/hrm/settings?tab=departments')->assertOk();
    $dom=new \DOMDocument(); $dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING); $xpath=new \DOMXPath($dom);
    expect($xpath->query('//section[contains(@class,"hrm-settings-list")]/div[contains(@class,"card-header")]//button[@data-bs-target="#hrmDepartmentModal"]'))->toHaveCount(1)
        ->and($xpath->query('//main/section[contains(@class,"hrm-settings-form-card")]'))->toHaveCount(0)
        ->and($xpath->query('//div[@id="hrmDepartmentModal" and @data-open-on-load="0"]//form'))->toHaveCount(1);
    $this->from('/hrm/settings?tab=departments&edit=1')->post('/hrm/settings/departments',[
        'hrm_department_form'=>'1','department_id'=>'','name'=>'New Registrar Team','code'=>'Invalid Code','status'=>'0','parent_id'=>1,
    ])->assertSessionHasErrors('code');
    $response=$this->get('/hrm/settings?tab=departments&edit=1')->assertOk()->assertSee('Create New Department');
    $this->withSession(['_old_input'=>['hrm_department_form'=>'1','department_id'=>'','name'=>'New Registrar Team','code'=>'Invalid Code','status'=>'0','parent_id'=>1]]);
    $html=view('hrm-settings.index',$response->original->getData())->withErrors(['code'=>'The code must use letters, numbers, dashes, or underscores.'])->render();
    $dom->loadHTML($html,LIBXML_NOERROR|LIBXML_NOWARNING); $xpath=new \DOMXPath($dom);
    expect($xpath->query('//div[@id="hrmDepartmentModal" and @data-open-on-load="1"]'))->toHaveCount(1)
        ->and($xpath->query('//div[@id="hrmDepartmentModal"]//input[@name="name" and @value="New Registrar Team"]'))->toHaveCount(1)
        ->and($xpath->query('//div[@id="hrmDepartmentModal"]//input[@name="department_id" and @value=""]'))->toHaveCount(1)
        ->and($xpath->query('//select[@name="parent_id"]/option[@value="1" and not(@disabled)]'))->toHaveCount(1)
        ->and($xpath->query('//div[@id="hrmDepartmentModal"]//*[@data-hrm-setting-errors]'))->toHaveCount(1);
});

it('uses the same list header and popup behavior for Position as Department',function() {
    $response=$this->get('/hrm/settings?tab=positions')->assertOk()->assertSee('New Position')->assertSee('Create New Position');
    $dom=new \DOMDocument(); $dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING); $xpath=new \DOMXPath($dom);
    expect($xpath->query('//main/section[contains(@class,"hrm-settings-form-card")]'))->toHaveCount(0)
        ->and($xpath->query('//section[contains(@class,"hrm-settings-list")]/div[contains(@class,"card-header")]//button[@data-bs-target="#hrmPositionModal"]'))->toHaveCount(1)
        ->and($xpath->query('//section[contains(@class,"hrm-settings-list")]/div[contains(@class,"card-header")]/form[@data-hrm-settings-search]'))->toHaveCount(1)
        ->and($xpath->query('//div[@id="hrmPositionModal" and @data-open-on-load="0"]//form[@data-hrm-setting-form]'))->toHaveCount(1);
    $response=$this->get('/hrm/settings?tab=positions&edit=1')->assertOk()->assertSee('Edit Position');
    $dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING); $xpath=new \DOMXPath($dom);
    expect($xpath->query('//div[@id="hrmPositionModal" and @data-open-on-load="1"]//input[@name="name" and @value="Teacher"]'))->toHaveCount(1);
    $this->from('/hrm/settings?tab=positions&edit=1')->post('/hrm/settings/positions',[
        'hrm_position_form'=>'1','position_id'=>'','name'=>'New Position Attempt','code'=>'INVALID CODE','department_id'=>1,'status'=>'1',
    ])->assertSessionHasErrors('code');
    $response=$this->get('/hrm/settings?tab=positions&edit=1')->assertOk()->assertSee('Create New Position');
    $this->withSession(['_old_input'=>['hrm_position_form'=>'1','position_id'=>'','name'=>'New Position Attempt','code'=>'INVALID CODE','department_id'=>1,'status'=>'1']]);
    $html=view('hrm-settings.index',$response->original->getData())->withErrors(['code'=>'Use letters, numbers, dashes, or underscores.'])->render();
    $dom->loadHTML($html,LIBXML_NOERROR|LIBXML_NOWARNING); $xpath=new \DOMXPath($dom);
    expect($xpath->query('//div[@id="hrmPositionModal" and @data-open-on-load="1"]'))->toHaveCount(1)
        ->and($xpath->query('//div[@id="hrmPositionModal"]//input[@name="position_id" and @value=""]'))->toHaveCount(1)
        ->and($xpath->query('//div[@id="hrmPositionModal"]//input[@name="name" and @value="New Position Attempt"]'))->toHaveCount(1)
        ->and($xpath->query('//div[@id="hrmPositionModal"]//*[@data-hrm-setting-errors]'))->toHaveCount(1);
});

it('keeps IDs staff assignments old work snapshots and explicit permissions when a department is reparented',function() {
    $staff=($this->create)();
    $operations=\App\Models\Department::create(['name'=>'Operations','code'=>'OPS']);
    $this->post('/hrm/settings/departments',['department_id'=>1,'name'=>'Academics','code'=>'ACA','status'=>'1','parent_id'=>$operations->id])->assertSessionHasNoErrors();
    expect($staff->fresh()->department_id)->toBe(1)->and($staff->fresh()->department->hierarchy_name)->toBe('Operations › Academics')
        ->and($staff->workHistories->first()->after_snapshot['department'])->toBe('Academics')->and($staff->workHistories)->toHaveCount(1);
    // Older callers which omit parent_id preserve the current parent.
    $this->post('/settings/departments',['department_id'=>1,'name'=>'Academics','code'=>'ACA','status'=>'1'])->assertSessionHasNoErrors();
    expect(\App\Models\Department::find(1)->parent_id)->toBe($operations->id);
    $user=User::create(['name'=>'Campus Staff','department_id'=>1,'active_campus_id'=>1]);
    $user->campuses()->attach(1, ['is_primary'=>true]);
    $permission=DB::table('access_permissions')->where('code','staff.edit')->value('id');
    DB::table('access_department_permissions')->insert(['department_id'=>$operations->id,'permission_id'=>$permission]);
    expect($user->hasPermission('staff.edit',1))->toBeFalse();
    DB::table('access_department_permissions')->insert(['department_id'=>1,'permission_id'=>$permission]);
    expect($user->fresh()->hasPermission('staff.edit',1))->toBeTrue();
});

it('orders department choices by hierarchy and keeps active children labelled under inactive parents',function() {
    $registrar=\App\Models\Department::create(['name'=>'Registrar','code'=>'REG','parent_id'=>1]);
    $operations=\App\Models\Department::create(['name'=>'Operations','code'=>'OPS']);
    \App\Models\Department::create(['name'=>'Admin','code'=>'ADMIN','parent_id'=>$operations->id]);
    \App\Models\Department::find(1)->update(['status'=>0]);
    $options=\App\Support\DepartmentHierarchy::options();
    expect($options->pluck('hierarchy_name')->all())->toBe(['Academics › Registrar','Operations','Operations › Admin'])
        ->and($options->first()->id)->toBe($registrar->id);
    expect(\App\Support\DepartmentHierarchy::options(false,1)->pluck('hierarchy_name')->all())->toBe(['Operations','Operations › Admin']);
});

it('blocks deleting parents and departments assigned to staff or positions while allowing unused leaves',function() {
    $parent=\App\Models\Department::create(['name'=>'Operations','code'=>'OPS']);
    $leaf=\App\Models\Department::create(['name'=>'HR','code'=>'HR','parent_id'=>$parent->id]);
    $this->from('/hrm/settings?tab=departments')->delete('/hrm/settings/departments/'.$parent->id)->assertSessionHasErrors('department');
    Schema::enableForeignKeyConstraints();
    expect(fn()=>DB::table('access_departments')->where('id',$parent->id)->delete())->toThrow(\Illuminate\Database\QueryException::class);
    ($this->create)()->update(['department_id'=>$leaf->id]);
    $this->delete('/hrm/settings/departments/'.$leaf->id)->assertSessionHasErrors('department');
    $this->delete('/hrm/settings/departments/1')->assertSessionHasErrors('department');
    $unused=\App\Models\Department::create(['name'=>'Unused Team','code'=>'UNUSED','parent_id'=>$parent->id]);
    $this->delete('/hrm/settings/departments/'.$unused->id)->assertSessionHas('success');
    expect(\App\Models\Department::find($unused->id))->toBeNull()->and($leaf->fresh()->parent_id)->toBe($parent->id);
});

it('paginates the staff directory with twenty rows by default and the requested row count choices',function() {
    $rows=[];
    for($number=1;$number<=117;$number++) $rows[]=['staff_code'=>'PAGE-'.$number,'name_en'=>sprintf('Directory Staff %03d',$number),'name_kh'=>'បុគ្គលិក'];
    DB::table('hrm_staff')->insert($rows);
    $response=$this->get($this->url)->assertOk();
    expect($response->viewData('staff')->count())->toBe(20)->and($response->viewData('staff')->lastPage())->toBe(6)
        ->and($response->viewData('pageSize'))->toBe('20');
    $dom=new \DOMDocument(); $dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING); $xpath=new \DOMXPath($dom);
    expect($xpath->query('//select[@data-pagination-per-page]/option[@value="20" and @selected]'))->toHaveCount(1)
        ->and($xpath->query('//button[@data-staff-back-to-top and @hidden and @aria-label="Back to top"]'))->toHaveCount(1);
    foreach(['20'=>20,'50'=>50,'75'=>75,'100'=>100,'all'=>117] as $size=>$count) {
        $response=$this->get($this->url.'?per_page='.$size)->assertOk();
        expect($response->viewData('staff')->count())->toBe($count)->and($response->viewData('pageSize'))->toBe((string)$size);
    }
    $response=$this->get($this->url.'?per_page=50&page=2&staff_category=staff')->assertOk();
    $records=$response->viewData('staff');
    expect($records->count())->toBe(50)->and($records->firstItem())->toBe(51)->and($records->lastItem())->toBe(100)
        ->and($records->nextPageUrl())->toContain('staff_category=staff')->and($records->nextPageUrl())->toContain('per_page=50');
    $response=$this->get($this->url.'?per_page=all&page=99')->assertOk();
    expect($response->viewData('staff')->currentPage())->toBe(1)->and($response->viewData('staff')->lastPage())->toBe(1)
        ->and($response->viewData('staff')->count())->toBe(117);
    $response=$this->get($this->url.'?per_page=10')->assertOk();
    expect($response->viewData('pageSize'))->toBe('20')->and($response->viewData('staff')->count())->toBe(20);
});

it('renders pagination on an empty directory and All includes only the filtered records',function() {
    $response=$this->get($this->url.'?per_page=all')->assertOk()->assertSee('Showing')->assertSee('0 entries');
    expect($response->viewData('staff')->lastPage())->toBe(1)->and($response->viewData('staff')->perPage())->toBe(1);
    $staff=($this->create)();
    Staff::create(['staff_code'=>'EXCLUDED','name_en'=>'Other Staff','staff_category'=>'staff']);
    $response=$this->get($this->url.'?per_page=all&staff_category=teacher')->assertOk();
    expect($response->viewData('staff')->modelKeys())->toBe([$staff->id]);
    $dom=new \DOMDocument(); $dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING); $xpath=new \DOMXPath($dom);
    expect($xpath->query('//input[@data-pagination-goto and @disabled]'))->toHaveCount(1)
        ->and($xpath->query('//form[@data-staff-filters]/input[@name="per_page" and @value="all"]'))->toHaveCount(1);
});

it('puts the Khmer name before the English name and labels the directory actions',function() {
    ($this->create)();
    $response=$this->get($this->url)->assertOk()->assertSee('>Edit</a>',false)->assertSee('>Delete</button>',false);
    $dom=new \DOMDocument(); $dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING); $xpath=new \DOMXPath($dom);
    $names=$xpath->query('//td[@data-label="Staff"]//div[div[contains(@class,"staff-person-khmer")]]/*');
    expect(trim($names->item(0)->textContent))->toBe($this->data['name_kh'])->and(trim($names->item(1)->textContent))->toBe('SOK DARA');
    $sizes=$xpath->query('//select[@data-pagination-per-page]/option');
    expect(array_map(fn($option)=>$option->getAttribute('value'),iterator_to_array($sizes)))->toBe(['all','20','50','75','100']);
});

function seedStaffAddressLocations(): void
{
    DB::table('tb_province')->insert([
        ['id'=>11,'country_id'=>1,'province_name_en'=>'Phnom Penh','province_name_kh'=>'ភ្នំពេញ'],
        ['id'=>12,'country_id'=>1,'province_name_en'=>'Kampong Cham','province_name_kh'=>'កំពង់ចាម'],
        ['id'=>13,'country_id'=>2,'province_name_en'=>'Bangkok','province_name_kh'=>'បាងកក'],
    ]);
    DB::table('tb_district')->insert([
        ['id'=>21,'province_id'=>11,'district_name_en'=>'Chamkar Mon','district_name_kh'=>'ចំការមន'],
        ['id'=>22,'province_id'=>12,'district_name_en'=>'Batheay','district_name_kh'=>'បាធាយ'],
        ['id'=>23,'province_id'=>13,'district_name_en'=>'Bang Rak','district_name_kh'=>'បាងរ៉ាក់'],
    ]);
    DB::table('tb_commune')->insert([
        ['id'=>31,'district_id'=>21,'commune_name_en'=>'Boeung Keng Kang','commune_name_kh'=>'បឹងកេងកង'],
        ['id'=>32,'district_id'=>22,'commune_name_en'=>'Batheay','commune_name_kh'=>'បាធាយ'],
        ['id'=>33,'district_id'=>23,'commune_name_en'=>'Si Phraya','commune_name_kh'=>'ស៊ីប្រាយ៉ា'],
    ]);
    DB::table('tb_village')->insert([
        ['id'=>41,'commune_id'=>31,'village_name_en'=>'Village 1','village_name_kh'=>'ភូមិ១','status'=>1],
        ['id'=>42,'commune_id'=>32,'village_name_en'=>'Village 2','village_name_kh'=>'ភូមិ២','status'=>1],
        ['id'=>43,'commune_id'=>33,'village_name_en'=>'Village 3','village_name_kh'=>'ភូមិ៣','status'=>1],
        ['id'=>44,'commune_id'=>31,'village_name_en'=>'Retired Village','village_name_kh'=>'ភូមិចាស់','status'=>0],
    ]);
}

it('uses hierarchical Location records for staff addresses and preserves selections when editing',function() {
    seedStaffAddressLocations();
    $ids=['address_country_id'=>1,'address_province_id'=>11,'address_district_id'=>21,'address_commune_id'=>31,'address_village_id'=>41];
    $forged=collect(['province','district','commune','village'])->flatMap(fn($level)=>[$level.'_en'=>'Forged',$level.'_kh'=>'Forged'])->all();
    $this->post($this->url,array_replace($this->data,$ids,$forged))->assertSessionHasNoErrors();
    $staff=Staff::first();
    foreach($ids as $field=>$value) expect($staff->{$field})->toBe($value);
    expect($staff->province_en)->toBe('Phnom Penh')->and($staff->province_kh)->toBe('ភ្នំពេញ')
        ->and($staff->district_en)->toBe('Chamkar Mon')->and($staff->commune_kh)->toBe('បឹងកេងកង')->and($staff->village_en)->toBe('Village 1');
    $response=$this->get($this->url.'?edit='.$staff->id)->assertOk();
    $dom=new \DOMDocument(); $dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);
    $xpath=new \DOMXPath($dom);
    expect($xpath->query('//section[@data-staff-address]//div[@class="staff-address-location-grid"]/div'))->toHaveCount(5);
    foreach($ids as $field=>$value) expect($xpath->query('//section[@data-staff-address]//select[@name="'.$field.'"]/option[@value="'.$value.'" and @selected]'))->toHaveCount(1);
    expect($xpath->query('//section[@data-staff-address]//textarea[@name="current_address" and @readonly]'))->toHaveCount(1)
        ->and($xpath->query('//section[@data-staff-address]//input[@name="province_en" and @type="hidden"]'))->toHaveCount(1);
});

it('rejects staff address locations outside their selected parent hierarchy',function(array $changes,string $error) {
    seedStaffAddressLocations();
    $ids=['address_country_id'=>1,'address_province_id'=>11,'address_district_id'=>21,'address_commune_id'=>31,'address_village_id'=>41];
    $this->post($this->url,array_replace($this->data,$ids,$changes))->assertSessionHasErrors($error);
    expect(Staff::count())->toBe(0);
})->with([
    'province outside country'=>[['address_country_id'=>2],'address_province_id'],
    'district outside province'=>[['address_province_id'=>12],'address_district_id'],
    'commune outside district'=>[['address_district_id'=>22,'address_province_id'=>12],'address_commune_id'],
    'village outside commune'=>[['address_village_id'=>42],'address_village_id'],
    'missing district'=>[['address_district_id'=>null],'address_commune_id'],
]);

it('filters staff address options by their parent and retains only selected inactive locations',function() {
    seedStaffAddressLocations();
    $url=$this->url.'/address-options';
    $this->getJson($url.'?level=province&parent_id=1')->assertOk()->assertJsonCount(2)->assertJsonPath('0.id',12);
    $this->getJson($url.'?level=district&parent_id=11')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id',21);
    $this->getJson($url.'?level=commune&parent_id=21')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id',31);
    $this->getJson($url.'?level=village&parent_id=31')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id',41);
    $this->getJson($url.'?level=village&parent_id=31&selected_id=44')->assertOk()->assertJsonCount(2);
    $this->getJson($url.'?level=village&parent_id=32&selected_id=44')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id',42);
    $this->getJson($url.'?level=province')->assertOk()->assertExactJson([]);
    $this->getJson($url.'?level=unknown')->assertUnprocessable();
    DB::table('users')->insert(['id'=>2,'name'=>'No Staff Access']);
    $this->actingAs(User::find(2))->getJson($url.'?level=country')->assertForbidden();
});

it('preserves legacy staff address text and includes Cambodia as the default address country',function() {
    $staff=($this->create)();
    $this->get($this->url.'?edit='.$staff->id)->assertOk()->assertSee('data-en="Phnom Penh"',false)->assertSee('data-en="Village 1"',false);
    $this->post($this->url,array_replace($this->data,['staff_id'=>$staff->id,'address_country_id'=>1,'address_province_id'=>null,'address_district_id'=>null,'address_commune_id'=>null,'address_village_id'=>null]))->assertSessionHasNoErrors();
    expect($staff->fresh()->province_en)->toBe($this->data['province_en'])->and($staff->fresh()->current_address)->toBe($this->data['current_address']);
    $response=$this->get($this->url.'?create=1')->assertOk();
    $dom=new \DOMDocument();$dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);
    expect((new \DOMXPath($dom))->query('//select[@name="address_country_id"]/option[@value="1" and @selected]'))->toHaveCount(1);
});

it('clears dependent staff address selections and retains saved inactive selections',function() {
    seedStaffAddressLocations();
    $ids=['address_country_id'=>1,'address_province_id'=>11,'address_district_id'=>21,'address_commune_id'=>31,'address_village_id'=>44];
    $this->post($this->url,array_replace($this->data,$ids))->assertSessionHasNoErrors();
    $staff=Staff::first();
    $this->get($this->url.'?edit='.$staff->id)->assertOk()->assertSee('data-en="Retired Village"',false);
    $clear=['staff_id'=>$staff->id,'address_country_id'=>2,'current_address'=>'Thailand','current_address_kh'=>'ថៃ'];
    foreach(['province','district','commune','village'] as $level) {
        $clear['address_'.$level.'_id']=null;$clear[$level.'_en']=null;$clear[$level.'_kh']=null;
    }
    $this->post($this->url,array_replace($this->data,$clear))->assertSessionHasNoErrors();
    $staff=$staff->fresh();
    expect($staff->address_country_id)->toBe(2)->and($staff->current_address)->toBe('Thailand');
    foreach(['province','district','commune','village'] as $level) expect($staff->{'address_'.$level.'_id'})->toBeNull()->and($staff->{$level.'_en'})->toBeNull()->and($staff->{$level.'_kh'})->toBeNull();
});
it('saves identification issue details and restores the selected document type when editing',function(string $type,string $label) {
    $details=['identity_document_type'=>$type,'identity_document_name'=>'Residence Certificate','identity_document_number'=>'DOC-123','identity_document_issued_on'=>'2025-01-01','identity_document_expires_on'=>'2030-01-01'];
    $this->post($this->url,array_replace($this->data,$details))->assertSessionHasNoErrors();
    $staff=Staff::first();
    expect($staff->identity_document_type)->toBe($type)->and($staff->identity_document_number)->toBe('DOC-123')
        ->and($staff->identity_document_issued_on->format('Y-m-d'))->toBe('2025-01-01')->and($staff->identity_document_expires_on->format('Y-m-d'))->toBe('2030-01-01')
        ->and($staff->identity_document_name)->toBe($type==='other'?'Residence Certificate':null);
    $response=$this->get($this->url.'?edit='.$staff->id)->assertOk();
    $dom=new \DOMDocument();$dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);$xpath=new \DOMXPath($dom);
    expect($xpath->query('//section[@data-staff-identification and @data-document-type="'.$type.'"]'))->toHaveCount(1)
        ->and($xpath->query('//section[@data-staff-identification]//label[@for="staff-identity_document_number"]')->item(0)->textContent)->toContain($label)
        ->and($xpath->query('//section[@data-staff-identification]//input[@name="identity_document_issued_on" and @type="hidden" and @value="2025-01-01"]'))->toHaveCount(1)
        ->and($xpath->query('//section[@data-staff-identification]//input[@data-premium-date-input and @value="01-Jan-2025"]'))->toHaveCount(1)
        ->and($xpath->query('//section[@data-staff-identification]//input[@name="identity_document_expires_on" and @type="hidden" and @value="2030-01-01"]'))->toHaveCount(1)
        ->and($xpath->query('//section[@data-staff-identification]//div[@data-premium-date-picker]'))->toHaveCount(2)
        ->and($xpath->query('//section[@data-staff-identification]//input[@name="identity_document_name"]'))->toHaveCount(1);
    $this->get($this->url.'?view='.$staff->id)->assertOk()->assertSee($label)->assertSee('01-Jan-2025');
})->with([
    'national ID'=>['national_id','ID Number'],
    'passport'=>['passport','Passport Number'],
    'other'=>['other','Document Number'],
]);

it('validates identification issue dates names and document types',function(array $details,string $error) {
    $this->post($this->url,array_replace($this->data,$details))->assertSessionHasErrors($error);
    expect(Staff::count())->toBe(0);
})->with([
    'expiry before issue'=>[['identity_document_type'=>'passport','identity_document_issued_on'=>'2025-06-01','identity_document_expires_on'=>'2025-05-01'],'identity_document_expires_on'],
    'invalid issue date'=>[['identity_document_type'=>'national_id','identity_document_issued_on'=>'invalid'],'identity_document_issued_on'],
    'unknown document type'=>[['identity_document_type'=>'unknown'],'identity_document_type'],
    'long document name'=>[['identity_document_type'=>'other','identity_document_name'=>str_repeat('a',256)],'identity_document_name'],
]);

it('clears the document name when selecting a standard identity type and clears details when removing identification',function() {
    $this->post($this->url,array_replace($this->data,['identity_document_type'=>'other','identity_document_name'=>'Certificate','identity_document_number'=>'C-123','identity_document_issued_on'=>'2025-01-01','identity_document_expires_on'=>'2030-01-01']))->assertSessionHasNoErrors();
    $staff=Staff::first();
    $this->post($this->url,array_replace($this->data,['staff_id'=>$staff->id,'identity_document_type'=>'national_id','identity_document_number'=>'ID-123']))->assertSessionHasNoErrors();
    expect($staff->fresh()->identity_document_name)->toBeNull()->and($staff->fresh()->identity_document_number)->toBe('ID-123');
    $this->post($this->url,array_replace($this->data,['staff_id'=>$staff->id,'identity_document_type'=>null]))->assertSessionHasNoErrors();
    $staff=$staff->fresh();
    foreach(['identity_document_type','identity_document_name','identity_document_number','identity_document_issued_on','identity_document_expires_on'] as $field) expect($staff->{$field})->toBeNull();
});
it('saves uppercase emergency contacts and international phone numbers while preserving hidden remarks',function() {
    $contact=['emergency_contact_name'=>' Sok Dara ','emergency_contact_phone'=>'0979099897','emergency_contact_relationship'=>'Spouse','emergency_contact_address'=>'House 12, Street 271, Phnom Penh'];
    $this->post($this->url,array_replace($this->data,$contact))->assertSessionHasNoErrors();
    $staff=Staff::first();
    expect($staff->emergency_contact_name)->toBe('SOK DARA')->and($staff->emergency_contact_phone)->toBe('+855979099897')
        ->and($staff->emergency_contact_relationship)->toBe('Spouse')->and($staff->emergency_contact_address)->toBe($contact['emergency_contact_address']);
    $response=$this->get($this->url.'?edit='.$staff->id)->assertOk();
    $dom=new \DOMDocument();$dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);$xpath=new \DOMXPath($dom);
    expect($xpath->query('//section[@data-staff-emergency-contact]//div[@class="staff-emergency-contact-grid"]/div'))->toHaveCount(4)
        ->and($xpath->query('//section[@data-staff-emergency-contact]//div[@data-staff-phone]/input[@name="emergency_contact_phone" and @type="hidden"]'))->toHaveCount(1)
        ->and($xpath->query('//section[@data-staff-emergency-contact]//textarea[@name="emergency_contact_address"]'))->toHaveCount(1)
        ->and($xpath->query('//textarea[@name="notes"]'))->toHaveCount(0);
    $update=array_replace($this->data,$contact,['staff_id'=>$staff->id,'emergency_contact_name'=>'Chea Phearum','emergency_contact_phone'=>'+66 81 234 5678']);
    unset($update['notes']);
    $this->post($this->url,$update)->assertSessionHasNoErrors();
    $staff=$staff->fresh();
    expect($staff->emergency_contact_name)->toBe('CHEA PHEARUM')->and($staff->emergency_contact_phone)->toBe('+66812345678')
        ->and($staff->notes)->toBe('Staff remarks');
    $this->get($this->url.'?view='.$staff->id)->assertOk()->assertSee('CHEA PHEARUM')->assertSee('+66812345678');
    $this->post($this->url,array_replace($update,['emergency_contact_name'=>null,'emergency_contact_phone'=>null,'emergency_contact_relationship'=>null,'emergency_contact_address'=>null]))->assertSessionHasNoErrors();
    expect($staff->fresh()->emergency_contact_name)->toBeNull()->and($staff->fresh()->emergency_contact_phone)->toBeNull()->and($staff->fresh()->notes)->toBe('Staff remarks');
});
it('shows one work assignment row with a premium hire calendar and selectable working campuses',function() {
    $this->post($this->url,array_replace($this->data,['campuses'=>[2],'primary_campus_id'=>1]))->assertSessionHasNoErrors();
    $staff=Staff::first();
    expect($staff->campuses()->pluck('tb_school_info.id')->sort()->values()->all())->toBe([1,2]);
    $response=$this->get($this->url.'?edit='.$staff->id)->assertOk();
    $dom=new \DOMDocument();$dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);$xpath=new \DOMXPath($dom);
    expect($xpath->query('//div[@class="staff-work-assignment-grid"]/div'))->toHaveCount(5)
        ->and($xpath->query('//div[@class="staff-work-assignment-grid"]/div[1]//input[@name="joined_on" and @type="hidden" and @value="2024-01-01"]'))->toHaveCount(1)
        ->and($xpath->query('//div[@class="staff-work-assignment-grid"]/div[1]//input[@data-premium-date-input and @value="01-Jan-2024"]'))->toHaveCount(1)
        ->and($xpath->query('//div[@class="staff-work-assignment-grid"]/div[2]//select[@name="position_id"]'))->toHaveCount(1)
        ->and($xpath->query('//div[@class="staff-work-assignment-grid"]/div[3]//select[@name="department_id"]'))->toHaveCount(1)
        ->and($xpath->query('//div[@class="staff-work-assignment-grid"]/div[4]//select[@name="reports_to_staff_id"]'))->toHaveCount(1)
        ->and($xpath->query('//div[@class="staff-work-assignment-grid"]/div[5]//select[@name="primary_campus_id"]'))->toHaveCount(1)
        ->and($xpath->query('//select[@name="campuses[]" and @multiple and @data-staff-working-campuses]/option[@selected]'))->toHaveCount(2)
        ->and($xpath->query('//input[@type="checkbox" and @name="campuses[]"]'))->toHaveCount(0);
    $this->post($this->url,array_replace($this->data,['staff_id'=>$staff->id,'primary_campus_id'=>2,'campuses'=>[1,2],'joined_on'=>'2025-02-03']))->assertSessionHasNoErrors();
    $staff=$staff->fresh();
    expect($staff->joined_on->format('Y-m-d'))->toBe('2025-02-03')->and($staff->primary_campus_id)->toBe(2)
        ->and((bool)$staff->campuses->firstWhere('id',2)->pivot->is_primary)->toBeTrue()->and((bool)$staff->campuses->firstWhere('id',1)->pivot->is_primary)->toBeFalse();
});

it('provides position department mappings and retains inactive work assignments for searchable selections',function() {
    DB::table('access_departments')->insert(['id'=>2,'name'=>'Registrar','parent_id'=>1,'status'=>0]);
    DB::table('access_positions')->where('id',2)->update(['name'=>'Registrar Officer','department_id'=>2,'status'=>1]);
    $staff=($this->create)();
    DB::table('access_positions')->where('id',1)->update(['status'=>0]);
    Staff::create(['staff_code'=>'SUP-001','name_en'=>'CAMPUS MANAGER','employment_status'=>'active','status'=>1]);
    $response=$this->get($this->url.'?edit='.$staff->id)->assertOk();
    $dom=new \DOMDocument();$dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);$xpath=new \DOMXPath($dom);
    expect($xpath->query('//select[@name="position_id" and @data-staff-work-select]/option[@value="1" and @selected and @data-department="1"]'))->toHaveCount(1)
        ->and($xpath->query('//select[@name="position_id"]/option[@value="2" and @data-department="2"]'))->toHaveCount(1)
        ->and($xpath->query('//select[@name="department_id"]/option[@value="2"]')->item(0)->textContent)->toBe('Academics › Registrar');
    $this->post($this->url,array_replace($this->data,['staff_id'=>$staff->id,'position_id'=>2,'department_id'=>2]))->assertSessionHasNoErrors();
    expect($staff->fresh()->position_id)->toBe(2)->and($staff->fresh()->department_id)->toBe(2);
});

it('keeps assigned inactive working campuses visible when editing',function() {
    DB::table('tb_school_info')->insert(['id'=>3,'campus_name_en'=>'Legacy Campus','status'=>0]);
    $this->post($this->url,array_replace($this->data,['campuses'=>[1,3]]))->assertSessionHasNoErrors();
    $staff=Staff::first();
    $response=$this->get($this->url.'?edit='.$staff->id)->assertOk();
    $dom=new \DOMDocument();$dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);$xpath=new \DOMXPath($dom);
    expect($xpath->query('//select[@data-staff-working-campuses]/option[@value="3" and @selected]'))->toHaveCount(1);
    $this->post($this->url,array_replace($this->data,['staff_id'=>$staff->id,'campuses'=>[1,3]]))->assertSessionHasNoErrors();
    expect($staff->fresh()->campuses()->pluck('tb_school_info.id')->sort()->values()->all())->toBe([1,3]);
});

it('allows clearing working campuses when the primary campus is cleared',function() {
    $staff=($this->create)();
    $this->post($this->url,array_replace($this->data,['staff_id'=>$staff->id,'primary_campus_id'=>null,'campuses'=>[]]))->assertSessionHasNoErrors();
    expect($staff->fresh()->primary_campus_id)->toBeNull()->and($staff->fresh()->campuses)->toHaveCount(0);
});
it('uses the shared ON OFF toggle for staff profile status and restores its saved state',function(string $status,string $label) {
    $this->post($this->url,array_replace($this->data,['status'=>$status]))->assertSessionHasNoErrors();
    $staff=Staff::first();
    expect($staff->status)->toBe($status==='1');
    $response=$this->get($this->url.'?edit='.$staff->id)->assertOk();
    $dom=new \DOMDocument();$dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);$xpath=new \DOMXPath($dom);
    expect($xpath->query('//div[@class="staff-work-details-grid"]/div'))->toHaveCount(5)
        ->and($xpath->query('//div[@class="staff-work-details-grid"]/div[1]//select[@name="staff_category"]'))->toHaveCount(1)
        ->and($xpath->query('//div[@class="staff-work-details-grid"]/div[2]//select[@name="employment_type"]'))->toHaveCount(1)
        ->and($xpath->query('//div[@class="staff-work-details-grid"]/div[3]//select[@data-staff-working-campuses]'))->toHaveCount(1)
        ->and($xpath->query('//div[@class="staff-work-details-grid"]/div[4]//select[@name="employment_status"]'))->toHaveCount(1)
        ->and($xpath->query('//div[@class="staff-work-details-grid"]/div[5]//input[@name="ended_on"]'))->toHaveCount(1)
        ->and($xpath->query('//div[@class="staff-work-follow-up-grid"]/div[1]//button[@data-staff-profile-status and not(@data-status-toggle)]'))->toHaveCount(1)
        ->and(trim($xpath->query('//button[@data-staff-profile-status]/span[@class="status-toggle-label"]')->item(0)->textContent))->toBe($label)
        ->and($xpath->query('//input[@name="status" and @type="hidden" and @value="'.$status.'"]'))->toHaveCount(1)
        ->and($xpath->query('//select[@name="status"]'))->toHaveCount(0);
})->with(['active'=>['1','ON'],'inactive'=>['0','OFF']]);
it('renders the premium employment end calendar with stored dates and status-specific details',function(string $employmentStatus) {
    $this->post($this->url,array_replace($this->data,['employment_status'=>$employmentStatus,'ended_on'=>'2026-10-13','employment_end_reason'=>'Family circumstances']))->assertSessionHasNoErrors();
    $staff=Staff::first();
    $response=$this->get($this->url.'?edit='.$staff->id)->assertOk();
    $dom=new \DOMDocument();$dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);$xpath=new \DOMXPath($dom);
    expect($xpath->query('//div[@id="staff-work-tab" and @data-employment-status="'.$employmentStatus.'"]'))->toHaveCount(1)
        ->and($xpath->query('//div[contains(@class,"staff-employment-end-date-field")]//div[@data-premium-date-picker]'))->toHaveCount(1)
        ->and($xpath->query('//div[contains(@class,"staff-employment-end-date-field")]//input[@name="ended_on" and @type="hidden" and @value="2026-10-13"]'))->toHaveCount(1)
        ->and($xpath->query('//div[contains(@class,"staff-employment-end-date-field")]//input[@data-premium-date-input and @value="13-Oct-2026"]'))->toHaveCount(1)
        ->and($xpath->query('//div[contains(@class,"staff-employment-end-reason-field")]/div/textarea[@name="employment_end_reason"]')->item(0)->textContent)->toBe('Family circumstances');
    $update=$this->data+['staff_id'=>$staff->id];
    $this->post($this->url,$update)->assertSessionHasNoErrors();
    expect($staff->fresh()->employment_end_reason)->toBe('Family circumstances');
})->with(['active','resigned']);

it('groups every HRM permission under HRM and normalizes settings dependencies',function() {
    $permissions=\App\Models\Permission::all();
    $tree=\App\Support\PermissionHierarchy::tree($permissions);
    expect($tree['hrm']['permission']->code)->toBe('hrm.view')
        ->and(array_keys($tree['hrm']['modules']))->toBe(['staff','hrm-settings','departments','positions','hrm-staff-data','hrm-staff-attendance','hrm-payroll','hrm-leave-requests','hrm-performance-reviews','hrm-staff-reports'])
        ->and($tree['administrator']['modules'])->not->toHaveKey('departments')->not->toHaveKey('positions');
    foreach(\App\Support\HrmPermissions::catalog() as $module=>$actions) foreach($actions as $action=>$label) {
        $permission=$permissions->firstWhere('code',$module.'.'.$action);
        expect($permission?->name)->toBe($label)->and(\App\Support\PermissionHierarchy::visibleIds($permissions)->contains($permission->id))->toBeTrue();
    }
    $ids=$permissions->keyBy('code');
    $selection=array_map(fn($code)=>$ids[$code]->id,['hrm.view','departments.view','departments.create']);
    expect(\App\Support\PermissionHierarchy::normalizeIds($selection,$permissions))->toBe([$ids['hrm.view']->id]);
    $selection[]=$ids['hrm-settings.view']->id;
    expect(\App\Support\PermissionHierarchy::normalizeIds($selection,$permissions))->toHaveCount(4);
});

it('permits position-only HRM settings viewing and blocks unassigned tabs and actions',function() {
    $user=($this->permissionUser)(['hrm.view','hrm-settings.view','positions.view']);
    $this->actingAs($user);
    $response=$this->get('/hrm/settings')->assertOk()->assertSee('Position List')->assertDontSee('data-hrm-setting-new',false)->assertDontSee('data-hrm-settings-delete',false)->assertDontSee('hrm-setting-modal',false);
    $dom=new \DOMDocument();$dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);$xpath=new \DOMXPath($dom);
    expect($xpath->query('//nav[@aria-label="HRM settings tabs"]/a'))->toHaveCount(1);
    $this->get('/hrm/settings?tab=departments')->assertForbidden();
    $this->get('/hrm/settings?tab=positions&edit=1')->assertForbidden();
    $this->get('/settings/positions/print')->assertForbidden();
    $this->get('/settings/positions/excel')->assertForbidden();
    $this->postJson('/hrm/settings/positions',['name'=>'Denied','code'=>'DEN','department_id'=>1,'status'=>1])->assertForbidden();
    $this->deleteJson('/hrm/settings/positions/2')->assertForbidden();
    $this->get('/hrm/payroll')->assertForbidden();
    $this->get($this->url)->assertForbidden();
});

it('enforces separate create edit delete print export and status permissions for HRM settings',function(string $module,string $idField) {
    $user=($this->permissionUser)(['hrm.view','hrm-settings.view',$module.'.view',$module.'.create']);
    $this->actingAs($user);
    $payload=['name'=>'Test Setting','code'=>'TEST-SETTING','status'=>1]+($module==='positions'?['department_id'=>1]:[]);
    $this->post('/hrm/settings/'.$module,$payload)->assertSessionHasNoErrors();
    $id=DB::table('access_'.$module)->where('code','TEST-SETTING')->value('id');
    $this->postJson('/hrm/settings/'.$module,$payload+[$idField=>$id])->assertForbidden();
    $this->deleteJson('/hrm/settings/'.$module.'/'.$id)->assertForbidden();
    $updatePermission=DB::table('access_permissions')->where('code',$module.'.update')->value('id');
    DB::table('access_user_permission_overrides')->insert(['user_id'=>$user->id,'permission_id'=>$updatePermission,'allowed'=>true]);
    $this->post('/hrm/settings/'.$module,array_replace($payload,[$idField=>$id,'name'=>'Updated Setting']))->assertSessionHasNoErrors();
    $this->postJson('/hrm/settings/'.$module,array_replace($payload,[$idField=>$id,'status'=>0]))->assertForbidden();
    $this->postJson('/status/toggle',['entity'=>rtrim($module,'s'),'id'=>$id,'status'=>false])->assertForbidden();
    DB::table('access_user_permission_overrides')->insert(['user_id'=>$user->id,'permission_id'=>DB::table('access_permissions')->where('code',$module.'.status')->value('id'),'allowed'=>true]);
    $this->post('/hrm/settings/'.$module,array_replace($payload,[$idField=>$id,'status'=>0]))->assertSessionHasNoErrors();
    expect((int)DB::table('access_'.$module)->where('id',$id)->value('status'))->toBe(0);
    foreach(['print','export','delete'] as $action) DB::table('access_user_permission_overrides')->insert(['user_id'=>$user->id,'permission_id'=>DB::table('access_permissions')->where('code',$module.'.'.$action)->value('id'),'allowed'=>true]);
    $this->get('/settings/'.$module.'/print')->assertOk();
    $this->get('/settings/'.$module.'/excel')->assertOk();
    $this->delete('/hrm/settings/'.$module.'/'.$id)->assertSessionHasNoErrors();
    expect(DB::table('access_'.$module)->where('id',$id)->exists())->toBeFalse();
})->with([['departments','department_id'],['positions','position_id']]);

it('requires profile status permission separately from editing staff information',function() {
    $staff=($this->create)();
    $user=($this->permissionUser)(['hrm.view','staff.view','staff.edit']);
    $this->actingAs($user);
    $response=$this->get($this->url.'?edit='.$staff->id)->assertOk();
    $dom=new \DOMDocument();$dom->loadHTML($response->getContent(),LIBXML_NOERROR|LIBXML_NOWARNING);$xpath=new \DOMXPath($dom);
    expect($xpath->query('//button[@data-staff-profile-status and @disabled]'))->toHaveCount(1);
    $this->post($this->url,array_replace($this->data,['staff_id'=>$staff->id,'name_en'=>'UPDATED STAFF']))->assertSessionHasNoErrors();
    $this->postJson($this->url,array_replace($this->data,['staff_id'=>$staff->id,'status'=>0]))->assertForbidden();
    expect((int)$staff->fresh()->status)->toBe(1);
    DB::table('access_user_permission_overrides')->insert(['user_id'=>$user->id,'permission_id'=>DB::table('access_permissions')->where('code','staff.status')->value('id'),'allowed'=>true]);
    $this->post($this->url,array_replace($this->data,['staff_id'=>$staff->id,'status'=>0]))->assertSessionHasNoErrors();
    expect((int)$staff->fresh()->status)->toBe(0);
});

it('preserves existing HRM assignments when syncing the catalog repeatedly',function() {
    $user=($this->permissionUser)(['staff.view','staff.edit','departments.view']);
    DB::table('access_roles')->insert(['id'=>2,'code'=>'hrm-test-role','name'=>'HRM Test Role']);
    DB::table('access_departments')->insert(['id'=>2,'name'=>'Operations']);
    foreach(['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id'] as $table=>$owner) {
        foreach(['staff.view','staff.edit','departments.view'] as $code) DB::table($table)->insert([$owner=>2,'permission_id'=>DB::table('access_permissions')->where('code',$code)->value('id')]);
    }
    DB::table('access_user_permission_overrides')->insert(['user_id'=>$user->id,'permission_id'=>DB::table('access_permissions')->where('code','staff.status')->value('id'),'allowed'=>false]);
    $migration=require database_path('migrations/2026_10_13_000008_sync_hrm_permissions.php');
    $migration->up();$migration->up();
    foreach(['hrm.view','hrm-settings.view','staff.view','departments.view'] as $code) expect($user->fresh()->hasPermission($code,1))->toBeTrue();
    foreach(['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id'] as $table=>$owner) {
        foreach(['hrm.view','hrm-settings.view','staff.status'] as $code) expect(DB::table($table)->where($owner,2)->where('permission_id',DB::table('access_permissions')->where('code',$code)->value('id'))->count())->toBe(1);
    }
    expect((bool)DB::table('access_user_permission_overrides')->where('user_id',$user->id)->where('permission_id',DB::table('access_permissions')->where('code','staff.status')->value('id'))->value('allowed'))->toBeFalse();
    expect(DB::table('access_permissions')->count())->toBe(DB::table('access_permissions')->distinct()->count('code'));
});

it('provides a UTF8 staff CSV template with two importable samples and readable headings',function() {
    $this->get('/hrm/settings?tab=import-export')->assertOk()->assertSee('Download CSV Template')->assertSee('Export Excel')->assertSee('Import Staff from CSV');
    $response=$this->get('/hrm/settings/staff-data/template')->assertOk();
    $text=$response->streamedContent();
    expect(substr($text,0,3))->toBe("\xEF\xBB\xBF");
    $stream=fopen('php://temp','w+');fwrite($stream,substr($text,3));rewind($stream);
    $headers=fgetcsv($stream,0,',','"','');$rows=[];while(($row=fgetcsv($stream,0,',','"',''))!==false)$rows[]=$row;fclose($stream);
    expect($rows)->toHaveCount(2)->and($headers)->toContain('Country of Birth (English)','Address Province/City (English)','Education 1 Institution','Previous Employment 1 Employer')
        ->and($headers)->not->toContain('birth_country_id','address_province_id','Position ID','Department ID');
    $this->post('/hrm/settings/staff-data/import',['file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('staff.csv',$text)])
        ->assertRedirect(route('hrm.settings.index',['tab'=>'import-export']))->assertSessionHas('staff_import_result.created',2);
    expect(Staff::count())->toBe(2)->and(\App\Models\StaffEducation::count())->toBe(2)->and(\App\Models\StaffExperience::count())->toBe(2)->and(StaffWorkHistory::count())->toBe(2);
});

it('resolves bilingual birth locations and address names without importing database IDs',function() {
    DB::table('tb_province')->insert([['id'=>7,'country_id'=>1,'province_name_en'=>'Phnom Penh','province_name_kh'=>'ភ្នំពេញ'],['id'=>8,'country_id'=>2,'province_name_en'=>'Phnom Penh','province_name_kh'=>'Foreign Province']]);
    DB::table('tb_district')->insert(['id'=>7,'province_id'=>7,'district_name_en'=>'Chamkar Mon','district_name_kh'=>'ចំការមន']);
    DB::table('tb_commune')->insert(['id'=>7,'district_id'=>7,'commune_name_en'=>'Boeung Keng Kang','commune_name_kh'=>'បឹងកេងកង']);
    DB::table('tb_village')->insert(['id'=>7,'commune_id'=>7,'village_name_en'=>'Village 1','village_name_kh'=>'ភូមិ១']);
    $headers=['Staff ID','Staff Name (English)','Country of Birth (Khmer)','Province/City of Birth (Khmer)','Address Country (English)','Address Province/City (English)','Address District/Khan (Khmer)','Address Commune/Sangkat (English)','Address Village (Khmer)','House Number (English)','Street (English)','Current Address (English)','Current Address (Khmer)','Position'];
    $file=($this->csvUpload)($headers,[['IMPORT-001','sok dara','កម្ពុជា','ភ្នំពេញ','Cambodia','Phnom Penh','ចំការមន','Boeung Keng Kang','ភូមិ១','12','271','House 12, Street 271','ផ្ទះលេខ១២ ផ្លូវ២៧១','Teacher']]);
    $this->post('/hrm/settings/staff-data/import',['file'=>$file])->assertSessionHas('staff_import_result.created',1);
    $staff=Staff::first();
    expect($staff->name_en)->toBe('SOK DARA')->and($staff->birth_country_id)->toBe(1)->and($staff->birth_province_id)->toBe(7)
        ->and($staff->address_village_id)->toBe(7)->and($staff->province_en)->toBe('Phnom Penh')->and($staff->house_number_kh)->toBe('១២')->and($staff->department_id)->toBe(1);
    $row=app(\App\Services\StaffDataExport::class)->row($staff);
    expect($row['birth_country_en'])->toBe('Cambodia')->and($row['place_of_birth_en'])->toBe('Phnom Penh')->and($row['village_kh'])->toBe('ភូមិ១');
});

it('imports valid staff rows and provides failed records for correction and retry',function() {
    $file=($this->csvUpload)(['Staff ID','Staff Name (English)','Email'],[['NEW-001','First Person','first@example.com'],['NEW-002','Second Person','invalid-email']]);
    $this->post('/hrm/settings/staff-data/import',['file'=>$file])->assertSessionHas('staff_import_result.created',1)->assertSessionHas('staff_import_result.skipped',1)->assertSessionHas('staff_import_result.errors.0.row',3);
    expect(Staff::count())->toBe(1)->and(StaffWorkHistory::count())->toBe(1);
    $this->get('/hrm/settings?tab=import-export')->assertOk()->assertSee('1 staff profile(s) imported.')->assertSee('NEW-002')->assertSee('Download Failed Records');
    expect($this->get('/hrm/settings/staff-data/errors')->assertOk()->streamedContent())->toContain('NEW-002');
    $failed=$this->get('/hrm/settings/staff-data/failed-records')->assertOk()->streamedContent();
    expect($failed)->toContain('Staff ID','NEW-002','invalid-email')->not->toContain('NEW-001','CSV Row','Problem');
    $this->post('/hrm/settings/staff-data/import',['file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('corrected.csv',str_replace('invalid-email','second@example.com',$failed))])->assertSessionHas('staff_import_result.created',1)->assertSessionHas('staff_import_result.skipped',0);
    expect(Staff::count())->toBe(2)->and(StaffWorkHistory::count())->toBe(2);
});

it('reports existing duplicate and ambiguous CSV values without overwriting staff',function() {
    $staff=($this->create)();
    $file=($this->csvUpload)(['Staff ID','Staff Name (English)'],[['ST-001','REPLACEMENT NAME'],['OTHER','Another'],['OTHER','Duplicate']]);
    $this->post('/hrm/settings/staff-data/import',['file'=>$file])->assertSessionHas('staff_import_result.created',1)->assertSessionHas('staff_import_result.error_count',2)->assertSessionHas('staff_import_result.skipped',2);
    expect($staff->fresh()->name_en)->toBe('SOK DARA')->and(Staff::count())->toBe(2);
    DB::table('tb_province')->insert([['id'=>1,'country_id'=>1,'province_name_en'=>'Shared Province'],['id'=>2,'country_id'=>2,'province_name_en'=>'Shared Province']]);
    $file=($this->csvUpload)(['Staff ID','Staff Name (English)','Province/City of Birth (English)'],[['NEW-001','New Person','Shared Province']]);
    $this->post('/hrm/settings/staff-data/import',['file'=>$file])->assertSessionHas('staff_import_result.created',0)->assertSessionHas('staff_import_result.error_count',1);
});

it('imports a supervisor listed later in the CSV and rejects cyclic supervisor relationships',function() {
    $file=($this->csvUpload)(['Staff ID','Staff Name (English)','Supervisor Staff ID'],[['EMPLOYEE','Employee','MANAGER'],['MANAGER','Manager','']]);
    $this->post('/hrm/settings/staff-data/import',['file'=>$file])->assertSessionHas('staff_import_result.created',2);
    expect(Staff::where('staff_code','EMPLOYEE')->first()->supervisor->staff_code)->toBe('MANAGER');
    $file=($this->csvUpload)(['Staff ID','Staff Name (English)','Supervisor Staff ID'],[['CYCLE-A','A','CYCLE-B'],['CYCLE-B','B','CYCLE-A'],['VALID','Valid Staff','']]);
    $this->post('/hrm/settings/staff-data/import',['file'=>$file])->assertSessionHas('staff_import_result.created',1)->assertSessionHas('staff_import_result.skipped',2);
    expect(Staff::count())->toBe(3);
});

it('exports a real Excel workbook with multiple education records text identifiers and readable locations',function() {
    $staff=($this->create)();
    $staff->update(['staff_code'=>'001234','birth_country_id'=>1,'address_country_id'=>1,'name_en'=>'=Formula-like Name']);
    $staff->educations()->create(['degree'=>'Master','institution_name'=>'Second University','country'=>'Cambodia','graduated_year'=>2020]);
    $response=$this->get('/hrm/settings/staff-data/excel')->assertOk();
    $path=$response->baseResponse->getFile()->getPathname();$zip=new \ZipArchive();expect($zip->open($path))->toBeTrue();
    $xml=$zip->getFromName('xl/worksheets/sheet1.xml');$zip->close();
    $dom=new \DOMDocument();expect($dom->loadXML($xml))->toBeTrue();$xpath=new \DOMXPath($dom);$xpath->registerNamespace('s','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    expect($xpath->query('//s:row'))->toHaveCount(2)->and($xpath->query('//s:f'))->toHaveCount(0)
        ->and($xml)->toContain('001234','Cambodia','Education 2 Institution','Second University','=Formula-like Name','Primary Campus')
        ->and($xml)->not->toContain('birth_country_id','address_country_id','department_id');
    unlink($path);
});

it('requires tab download export and import permissions independently and also requires staff creation for CSV imports',function() {
    $user=($this->permissionUser)(['hrm.view','hrm-settings.view','hrm-staff-data.view','staff.view']);$this->actingAs($user);
    $this->get('/hrm/settings')->assertOk()->assertSee('Staff Data Downloads')->assertDontSee('Download CSV Template')->assertDontSee('Export Excel')->assertDontSee('data-staff-csv-import',false);
    $this->get('/hrm/settings/staff-data/template')->assertForbidden();$this->get('/hrm/settings/staff-data/excel')->assertForbidden();
    $file=($this->csvUpload)(['Staff ID','Staff Name (English)'],[['DENIED','Denied']]);
    $this->postJson('/hrm/settings/staff-data/import',['file'=>$file])->assertForbidden();
    DB::table('access_user_permission_overrides')->insert(['user_id'=>$user->id,'permission_id'=>DB::table('access_permissions')->where('code','hrm-staff-data.import')->value('id'),'allowed'=>true]);
    $this->postJson('/hrm/settings/staff-data/import',['file'=>$file])->assertForbidden();
    expect(Staff::count())->toBe(0);
});

it('imports over one thousand staff in a single CSV batch with an initial work history for each profile',function() {
    $rows=[];for($index=1;$index<=1001;$index++)$rows[]=['BULK-'.str_pad((string)$index,5,'0',STR_PAD_LEFT),'Staff '.$index];
    $file=($this->csvUpload)(['Staff ID','Staff Name (English)'],$rows);
    $this->post('/hrm/settings/staff-data/import',['file'=>$file])->assertSessionHas('staff_import_result.created',1001);
    expect(Staff::count())->toBe(1001)->and(StaffWorkHistory::count())->toBe(1001)->and(Staff::where('staff_code','BULK-01001')->first()->name_en)->toBe('STAFF 1001');
});

it('reports every failed row beyond the first page and exports the full correction file',function() {
    $rows=[];for($index=1;$index<=125;$index++)$rows[]=['ERROR-'.$index,'Staff '.$index,'not-an-email'];
    $rows[]=['VALID-LAST','Last Staff','last@example.com'];
    $file=($this->csvUpload)(['Staff ID','Staff Name (English)','Email'],$rows);
    $this->post('/hrm/settings/staff-data/import',['file'=>$file])->assertSessionHas('staff_import_result.created',1)->assertSessionHas('staff_import_result.skipped',125)->assertSessionHas('staff_import_result.error_count',125);
    $this->get('/hrm/settings?tab=import-export&error_page=2')->assertOk()->assertSee('ERROR-125')->assertSee('101–125 of 125 issues');
    $failed=$this->get('/hrm/settings/staff-data/failed-records')->assertOk()->streamedContent();
    expect($failed)->toContain('ERROR-1','ERROR-125')->not->toContain('VALID-LAST');
    $report=$this->get('/hrm/settings/staff-data/errors')->assertOk()->streamedContent();expect($report)->toContain('ERROR-125');
    expect(Staff::count())->toBe(1);
    $this->actingAs(($this->permissionUser)(['hrm.view','hrm-settings.view','staff.view']))->get('/hrm/settings/staff-data/failed-records')->assertForbidden();
});

it('skips invalid supervisors and dependent rows while importing unrelated staff',function() {
    $file=($this->csvUpload)(['Staff ID','Staff Name (English)','Email','Supervisor Staff ID'],[
        ['DEPENDENT','Dependent Staff','dependent@example.com','BAD-MANAGER'],['BAD-MANAGER','Bad Manager','wrong-email',''],['UNRELATED','Unrelated Staff','other@example.com',''],
    ]);
    $this->post('/hrm/settings/staff-data/import',['file'=>$file])->assertSessionHas('staff_import_result.created',1)->assertSessionHas('staff_import_result.skipped',2);
    expect(Staff::first()->staff_code)->toBe('UNRELATED')->and(StaffWorkHistory::count())->toBe(1);
    expect($this->get('/hrm/settings/staff-data/failed-records')->assertOk()->streamedContent())->toContain('DEPENDENT','BAD-MANAGER')->not->toContain('UNRELATED');
});

it('continues after malformed rows while keeping those rows in the correction CSV',function() {
    $file=($this->csvUpload)(['Staff ID','Staff Name (English)'],[['BAD-MISSING'],['GOOD-AFTER','Good Staff'],['','Missing ID']]);
    $this->post('/hrm/settings/staff-data/import',['file'=>$file])->assertSessionHas('staff_import_result.created',1)->assertSessionHas('staff_import_result.skipped',2);
    expect(Staff::first()->staff_code)->toBe('GOOD-AFTER');
    expect($this->get('/hrm/settings/staff-data/failed-records')->assertOk()->streamedContent())->toContain('BAD-MISSING','Missing ID')->not->toContain('GOOD-AFTER');
});

it('suggests all misspelled birth and address corrections without changing or importing the rejected record',function() {
    DB::table('tb_province')->insert(['id'=>1,'country_id'=>1,'province_name_en'=>'Phnom Penh','province_name_kh'=>'ភ្នំពេញ']);
    $file=($this->csvUpload)(['Staff ID','Staff Name (English)','Country of Birth (English)','Province/City of Birth (English)','Address Country (English)','Address Province/City (English)'],[
        ['TYPO','Typo Staff','Cambodai','Phnoum Phen','Cambodai','Phnoum Phen'],['GOOD','Good Staff','Cambodia','Phnom Penh','Cambodia','Phnom Penh'],
    ]);
    $response=$this->post('/hrm/settings/staff-data/import',['file'=>$file])->assertSessionHas('staff_import_result.created',1)->assertSessionHas('staff_import_result.skipped',1)->assertSessionHas('staff_import_result.error_count',4);
    $issues=session('staff_import_result.errors');
    expect(collect($issues)->pluck('field')->all())->toBe(['Country of Birth','Province/City of Birth','Address Country','Address Province/City']);
    expect($issues[0]['suggestion'])->toContain('Cambodia')->and($issues[1]['suggestion'])->toContain('Phnom Penh');
    $this->get('/hrm/settings?tab=import-export')->assertOk()->assertSee('Suggested Correction')->assertSee('Cambodia')->assertSee('Phnom Penh');
    $csv=$this->get('/hrm/settings/staff-data/errors')->assertOk()->streamedContent();
    $stream=fopen('php://temp','w+');fwrite($stream,substr($csv,3));rewind($stream);
    expect(fgetcsv($stream,0,',','"',''))->toBe(['CSV Row','Staff ID','Field','Problem','Suggested Correction']);
    $count=0;while(($row=fgetcsv($stream,0,',','"',''))!==false){expect($row)->toHaveCount(5)->and($row[4])->not->toBe('');$count++;}fclose($stream);expect($count)->toBe(4);
    $failed=$this->get('/hrm/settings/staff-data/failed-records')->assertOk()->streamedContent();
    expect($failed)->toContain('Cambodai','Phnoum Phen')->not->toContain('Suggested Correction');
    expect(Staff::where('staff_code','TYPO')->exists())->toBeFalse()->and(Staff::where('staff_code','GOOD')->first()->birth_province_id)->toBe(1);
});

it('keeps location suggestions inside the selected country and reports when no close match exists',function() {
    DB::table('tb_province')->insert([['id'=>1,'country_id'=>1,'province_name_en'=>'Phnom Penh'],['id'=>2,'country_id'=>2,'province_name_en'=>'Foreign Province']]);
    $file=($this->csvUpload)(['Staff ID','Staff Name (English)','Country of Birth (English)','Province/City of Birth (English)'],[
        ['WRONG-COUNTRY','Wrong Country','Cambodia','Foreign Provnce'],['NO-MATCH','No Match','Totally Unrelated Place',''],
    ]);
    $this->post('/hrm/settings/staff-data/import',['file'=>$file])->assertSessionHas('staff_import_result.created',0)->assertSessionHas('staff_import_result.skipped',2);
    $issues=session('staff_import_result.errors');
    expect($issues[0]['suggestion'])->toContain('No close match')->not->toContain('Foreign Province')
        ->and($issues[1]['suggestion'])->toContain('No close match');
});

it('includes corrections for all linked work fields supervisors nationality and dropdown choices on the same CSV row',function() {
    Staff::create(['staff_code'=>'HR-0001','name_en'=>'CAMPUS MANAGER','employment_status'=>'active','status'=>1]);
    DB::table('access_positions')->where('id',2)->update(['name'=>'Registrar Officer']);
    DB::table('tb_school_info')->insert(['id'=>3,'campus_name_en'=>'Central Campus','status'=>1]);
    $headers=['Staff ID','Staff Name (English)','Department','Position','Primary Campus','Working Campuses','Nationality (English)','Supervisor Staff ID',
        'Gender (English)','Marital Status (English)','Staff Category','Employment Type','Employment Status','Profile Status','Contract Type','Document Type','Monday Working Period'];
    $file=($this->csvUpload)($headers,[['ALL-TYPOS','Typo Staff','Acadmeics','Registrra Officer','Centarl Campus','Centarl Campus | BHC','Camboidan','HR-0010',
        'Femlae','Maried','Teahcer','Full-Tmie','Actvie','Actvie','Permanet','Pasport','Mornign Only']]);
    $this->post('/hrm/settings/staff-data/import',['file'=>$file])->assertSessionHas('staff_import_result.created',0)->assertSessionHas('staff_import_result.skipped',1);
    $issues=collect(session('staff_import_result.errors'));
    foreach(['Department'=>'Academics','Position'=>'Registrar Officer','Primary Campus'=>'Central Campus','Nationality'=>'Cambodian','Supervisor Staff ID'=>'HR-0001 (CAMPUS MANAGER)',
        'Gender (English)'=>'Female','Marital Status (English)'=>'Married','Staff Category'=>'Teacher','Employment Type'=>'Full-Time','Employment Status'=>'Active','Profile Status'=>'Active','Contract Type'=>'Permanent','Document Type'=>'Passport','Monday Working Period'=>'Morning Only'] as $field=>$correction) {
        expect($issues->firstWhere('field',$field)['suggestion']??'')->toContain($correction);
    }
    $campuses=$issues->where('field','Working Campus')->values();
    expect($campuses)->toHaveCount(2)->and($campuses[0]['suggestion'])->toContain('Central Campus')->and($campuses[1]['suggestion'])->toContain('BCH');
    $csv=$this->get('/hrm/settings/staff-data/errors')->assertOk()->streamedContent();
    expect($csv)->toContain('Suggested Correction','Registrar Officer','HR-0001 (CAMPUS MANAGER)','Morning Only');
    expect(Staff::count())->toBe(1)->and(Staff::where('staff_code','ALL-TYPOS')->exists())->toBeFalse();
});

it('shows department context for ambiguous linked positions rather than choosing one automatically',function() {
    DB::table('access_departments')->insert(['id'=>2,'name'=>'Operations','status'=>1]);
    DB::table('access_positions')->insert(['id'=>3,'name'=>'Teacher','department_id'=>2,'status'=>1]);
    $file=($this->csvUpload)(['Staff ID','Staff Name (English)','Position'],[['AMBIGUOUS','Ambiguous Staff','Teacher']]);
    $this->post('/hrm/settings/staff-data/import',['file'=>$file])->assertSessionHas('staff_import_result.created',0)->assertSessionHas('staff_import_result.skipped',1);
    expect(session('staff_import_result.errors.0.suggestion'))->toContain('Teacher','Department: Academics','Department: Operations');
    expect(Staff::count())->toBe(0);
});
