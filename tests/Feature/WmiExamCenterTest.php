<?php

use App\Models\{User,WmiYear,WmiExamCenter};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\TrackUserPresence::class);$this->withoutVite();
    app('view')->getFinder()->prependLocation(base_path('tests/Fixtures/wmi-views'));
    (require database_path('migrations/2026_11_18_000002_create_wmi_years.php'))->up();
    (require database_path('migrations/2026_11_18_000003_create_wmi_exam_centers.php'))->up();
    $this->actingAs(new User(['id'=>1,'name'=>'WMI Administrator']));
    $this->year=WmiYear::create(['year'=>2026]);$this->otherYear=WmiYear::create(['year'=>2025]);
    $this->data=['wmi_year_id'=>$this->year->id,'name'=>'Western International School','location'=>'Phnom Penh'];
});

it('creates an exam center linked to its WMI year and permits editing location',function () {
    $this->postJson(route('wmi-management.centers.store'),$this->data)->assertCreated();
    $center=WmiExamCenter::sole();
    expect($center->wmiYear->year)->toBe(2026)->and($center->location)->toBe('Phnom Penh');
    $this->putJson(route('wmi-management.centers.update',$center),array_replace($this->data,['location'=>'Sihanoukville']))->assertOk();
    expect($center->fresh()->location)->toBe('Sihanoukville');
    $this->deleteJson(route('wmi-management.years.destroy',$this->year))->assertStatus(409);
});

it('rejects duplicate centers within a year while allowing the same center in another year',function () {
    $this->postJson(route('wmi-management.centers.store'),$this->data)->assertCreated();
    $this->postJson(route('wmi-management.centers.store'),array_replace($this->data,['name'=>' WESTERN   INTERNATIONAL SCHOOL ']))->assertUnprocessable();
    $this->postJson(route('wmi-management.centers.store'),array_replace($this->data,['wmi_year_id'=>$this->otherYear->id]))->assertCreated();
    expect(WmiExamCenter::count())->toBe(2);
});

it('validates year center name and location',function () {
    foreach ([['wmi_year_id'=>9999],['name'=>''],['location'=>''],['name'=>str_repeat('a',256)]] as $invalid)
        $this->postJson(route('wmi-management.centers.store'),array_replace($this->data,$invalid))->assertUnprocessable();
    expect(WmiExamCenter::count())->toBe(0);
});

it('renders numbered centers and their locations and filters by WMI year',function () {
    $this->postJson(route('wmi-management.centers.store'),$this->data)->assertCreated();
    $this->postJson(route('wmi-management.centers.store'),array_replace($this->data,['wmi_year_id'=>$this->otherYear->id,'name'=>'Previous Year Center']))->assertCreated();
    $this->get(route('wmi-management.section',['section'=>'settings','tab'=>'exam-centers','wmi_year_id'=>$this->year->id]))->assertOk()
        ->assertSee('WMI 2026')->assertSee('Western International School')->assertSee('Phnom Penh')->assertSee('No.')
        ->assertSee('data-auto-filter',false)->assertSee('data-wmi-year-picker',false)->assertDontSee('>Filter</button>',false)
        ->assertSee('Add New')->assertSee('wmiCenterModal')->assertSee('data-wmi-center-edit',false)->assertDontSee('Previous Year Center');
});

it('blocks linked center deletion and changing its year but allows normal edits',function () {
    $this->postJson(route('wmi-management.centers.store'),$this->data)->assertCreated();$center=WmiExamCenter::sole();
    Schema::create('wmi_registrations',function(Blueprint $t){$t->id();$t->foreignId('wmi_exam_center_id')->constrained('wmi_exam_centers')->restrictOnDelete();});
    DB::table('wmi_registrations')->insert(['wmi_exam_center_id'=>$center->id]);
    $this->deleteJson(route('wmi-management.centers.destroy',$center))->assertStatus(409);
    $this->putJson(route('wmi-management.centers.update',$center),array_replace($this->data,['wmi_year_id'=>$this->otherYear->id]))->assertStatus(409);
    $this->putJson(route('wmi-management.centers.update',$center),array_replace($this->data,['location'=>'Updated location']))->assertOk();
    $this->get(route('wmi-management.section',['section'=>'settings','tab'=>'exam-centers']))->assertOk()->assertSee('Linked to data')->assertSee('disabled',false);
    expect($center->fresh()->wmi_year_id)->toBe($this->year->id)->and(DB::table('wmi_registrations')->count())->toBe(1);
});

it('deletes an unused center and requires authentication for new assignments',function () {
    $this->postJson(route('wmi-management.centers.store'),$this->data)->assertCreated();$center=WmiExamCenter::sole();
    $this->deleteJson(route('wmi-management.centers.destroy',$center))->assertOk();expect(WmiExamCenter::count())->toBe(0);
    auth()->logout();$this->postJson(route('wmi-management.centers.store'),$this->data)->assertUnauthorized();
});


it('paginates centers with fifteen by default and supports all allowed page sizes',function () {
    foreach(range(1,16) as $number) WmiExamCenter::create(array_replace($this->data,['name'=>sprintf('Center %02d',$number),'name_key'=>sprintf('center %02d',$number)]));
    WmiExamCenter::create(array_replace($this->data,['wmi_year_id'=>$this->otherYear->id,'name'=>'Other year','name_key'=>'other year']));
    $url=route('wmi-management.section',['section'=>'settings','tab'=>'exam-centers','wmi_year_id'=>$this->year->id]);
    $this->get($url)->assertOk()->assertViewHas('examCenters',fn($rows)=>$rows->perPage()===15&&$rows->count()===15&&$rows->total()===16)
        ->assertSee('premium-pagination',false)->assertSee('data-pagination-per-page',false)->assertSee('Go to')->assertSee('15 / page')->assertDontSee('Center 16');
    $this->get($url.'&page=2')->assertOk()->assertSee('Center 16')->assertViewHas('examCenters',fn($rows)=>$rows->firstItem()===16&&$rows->count()===1);
    foreach(['50','75','100','all'] as $size) {
        $this->get($url.'&per_page='.$size.($size==='all'?'&page=2':''))->assertOk()
            ->assertViewHas('examCenters',fn($rows)=>$rows->count()===16&&$rows->currentPage()===1)->assertDontSee('Other year');
    }
    $this->getJson($url.'&per_page=999')->assertUnprocessable();
});
