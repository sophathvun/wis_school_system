<?php

use App\Models\{User,Province,Country,WmiSchool};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\TrackUserPresence::class);$this->withoutVite();
    app('view')->getFinder()->prependLocation(base_path('tests/Fixtures/wmi-views'));
    (require database_path('migrations/2026_07_31_000001_create_location_tables.php'))->up();
    (require database_path('migrations/2026_11_18_000004_create_wmi_schools.php'))->up();
    (require database_path('migrations/2026_11_18_000005_make_wmi_school_details_optional.php'))->up();
    $this->actingAs(new User(['id'=>1,'name'=>'WMI Administrator']));
    $this->province=Province::create(['country_id'=>Country::first()->id,'province_name_en'=>'Phnom Penh','province_name_kh'=>'រាជធានីភ្នំពេញ']);
    $this->data=['name_en'=>'Western School','name_kh'=>'សាលាវេស្ទើន','school_type'=>'Private','province_id'=>$this->province->id,'remark'=>'Participating school'];
});

it('creates and edits a school using a province from location settings',function () {
    $this->postJson(route('wmi-management.schools.store'),$this->data)->assertCreated();
    $school=WmiSchool::sole();expect($school->province->province_name_en)->toBe('Phnom Penh');
    $this->putJson(route('wmi-management.schools.update',$school),array_replace($this->data,['school_type'=>'Public','remark'=>null]))->assertOk();
    expect($school->fresh()->school_type)->toBe('Public')->and($school->fresh()->remark)->toBeNull();
    $this->get(route('wmi-management.section',['section'=>'settings','tab'=>'schools']))->assertOk()
        ->assertSee('School Name (English)')->assertSee('School Name (Khmer)')->assertSee('School Type')->assertSee('School Location')->assertSee('Remark')
        ->assertSee('Western School')->assertSee('រាជធានីភ្នំពេញ')->assertSee('wmiSchoolModal')->assertSee('premium-pagination',false);
});

it('rejects duplicate schools and invalid types provinces and missing names',function () {
    $this->postJson(route('wmi-management.schools.store'),$this->data)->assertCreated();
    $this->postJson(route('wmi-management.schools.store'),array_replace($this->data,['name_en'=>' WESTERN   SCHOOL ']))->assertUnprocessable();
    foreach([['school_type'=>'Other'],['province_id'=>9999],['name_en'=>'  '],['remark'=>str_repeat('x',2001)]] as $invalid)
        $this->postJson(route('wmi-management.schools.store'),array_replace($this->data,$invalid))->assertUnprocessable();
    expect(WmiSchool::count())->toBe(1);
});

it('protects schools linked to registrations and deletes unused schools',function () {
    $this->postJson(route('wmi-management.schools.store'),$this->data)->assertCreated();$school=WmiSchool::sole();
    Schema::create('wmi_registrations',function(Blueprint $table){$table->id();$table->foreignId('wmi_school_id')->constrained('wmi_schools')->restrictOnDelete();});
    DB::table('wmi_registrations')->insert(['wmi_school_id'=>$school->id]);
    $this->deleteJson(route('wmi-management.schools.destroy',$school))->assertStatus(409);
    $this->get(route('wmi-management.section',['section'=>'settings','tab'=>'schools']))->assertOk()->assertSee('Linked to data');
    DB::table('wmi_registrations')->delete();
    $this->deleteJson(route('wmi-management.schools.destroy',$school))->assertOk();expect(WmiSchool::count())->toBe(0);
    auth()->logout();$this->postJson(route('wmi-management.schools.store'),$this->data)->assertUnauthorized();
});

it('paginates schools and preserves a province filter including all mode',function () {
    foreach(range(1,16) as $number) WmiSchool::create(array_replace($this->data,['name_en'=>sprintf('School %02d',$number),'name_key'=>hash('sha256',(string)$number)]));
    $other=Province::create(['country_id'=>Country::first()->id,'province_name_en'=>'Kampot']);
    $unassigned=Province::create(['country_id'=>Country::first()->id,'province_name_en'=>'Unassigned Province']);
    WmiSchool::create(array_replace($this->data,['name_en'=>'Other School','name_key'=>hash('sha256','other'),'province_id'=>$other->id]));
    $url=route('wmi-management.section',['section'=>'settings','tab'=>'schools','province_id'=>$this->province->id]);
    $this->get($url)->assertOk()->assertViewHas('schools',fn($rows)=>$rows->perPage()===15&&$rows->total()===16)->assertDontSee('Other School')
        ->assertViewHas('assignedSchoolProvinces',fn($rows)=>$rows->modelKeys()===[$other->id,$this->province->id])
        ->assertViewHas('schoolProvinces',fn($rows)=>$rows->contains('id',$unassigned->id));
    $this->get($url.'&page=2')->assertOk()->assertViewHas('schools',fn($rows)=>$rows->firstItem()===16&&$rows->count()===1);
    $this->get($url.'&per_page=all&page=2')->assertOk()->assertViewHas('schools',fn($rows)=>$rows->currentPage()===1&&$rows->count()===16);
    $this->getJson($url.'&per_page=10')->assertUnprocessable();
});


it('allows blank Khmer name and location and permits clearing existing values',function () {
    $this->postJson(route('wmi-management.schools.store'),array_replace($this->data,['name_kh'=>'','province_id'=>'']))->assertCreated();
    $school=WmiSchool::sole();
    expect($school->name_kh)->toBeNull()->and($school->province_id)->toBeNull();
    $this->putJson(route('wmi-management.schools.update',$school),$this->data)->assertOk();
    $this->putJson(route('wmi-management.schools.update',$school),array_replace($this->data,['name_kh'=>null,'province_id'=>null]))->assertOk();
    expect($school->fresh()->name_kh)->toBeNull()->and($school->fresh()->province_id)->toBeNull();
    $this->get(route('wmi-management.section',['section'=>'settings','tab'=>'schools']))->assertOk()->assertSee('Western School');
});
