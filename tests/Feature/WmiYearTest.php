<?php

use App\Models\{User,WmiYear};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

beforeEach(function () {
    $this->mock(\App\Support\WmiPermissions::class)->shouldReceive('check')->andReturn(true);
    $this->withoutMiddleware(\App\Http\Middleware\TrackUserPresence::class);
    $this->withoutVite();
    app('view')->getFinder()->prependLocation(base_path('tests/Fixtures/wmi-views'));
    (require database_path('migrations/2026_11_18_000002_create_wmi_years.php'))->up();
    $this->actingAs(new User(['id'=>1,'name'=>'WMI Administrator']));
});

it('creates and edits a unique four digit WMI year',function () {
    $this->postJson(route('wmi-management.years.store'),['year'=>'2026'])->assertCreated();
    $year=WmiYear::sole();
    expect($year->year)->toBe(2026);
    $this->putJson(route('wmi-management.years.update',$year),['year'=>'2027'])->assertOk();
    expect($year->fresh()->year)->toBe(2027);
    $this->putJson(route('wmi-management.years.update',$year),['year'=>'2027'])->assertOk();
});

it('rejects missing malformed and duplicate years without modifying records',function () {
    $first=WmiYear::create(['year'=>2026]);$second=WmiYear::create(['year'=>2027]);
    foreach(['','abc','26','2025-2026','2026'] as $value)$this->postJson(route('wmi-management.years.store'),['year'=>$value])->assertUnprocessable();
    $this->putJson(route('wmi-management.years.update',$second),['year'=>2026])->assertUnprocessable();
    expect($first->fresh()->year)->toBe(2026)->and($second->fresh()->year)->toBe(2027)->and(WmiYear::count())->toBe(2);
});

it('deletes unused years but blocks years linked to WMI data on the server and list',function () {
    Schema::create('wmi_candidates',function(Blueprint $t){$t->id();$t->foreignId('wmi_year_id')->constrained('wmi_years')->restrictOnDelete();});
    $linked=WmiYear::create(['year'=>2026]);$unused=WmiYear::create(['year'=>2027]);
    DB::table('wmi_candidates')->insert(['wmi_year_id'=>$linked->id]);
    $this->deleteJson(route('wmi-management.years.destroy',$linked))->assertStatus(409)->assertJsonPath('message','This WMI year is linked to data and cannot be deleted.');
    $page=$this->get(route('wmi-management.section',['section'=>'settings','tab'=>'academic-year']))->assertOk();
    $page->assertSee('Linked to data')->assertSee('data-wmi-year-delete',false)->assertSee('disabled',false);
    expect(WmiYear::linkedYearIds([$linked->id,$unused->id]))->toBe([$linked->id]);
    $this->deleteJson(route('wmi-management.years.destroy',$unused))->assertOk();
    expect(WmiYear::count())->toBe(1)->and(DB::table('wmi_candidates')->count())->toBe(1);
});

it('renders list numbering add modal and edit delete controls',function () {
    WmiYear::create(['year'=>2026]);
    $this->get(route('wmi-management.section',['section'=>'settings','tab'=>'academic-year']))->assertOk()
        ->assertSee('WMI Year')->assertSee('2026')->assertSee('No.')->assertSee('Add New')
        ->assertSee('wmiYearModal')->assertSee('data-wmi-year-edit',false)->assertSee('data-wmi-year-delete',false);
});

it('requires authentication for year changes',function () {
    auth()->logout();
    $this->postJson(route('wmi-management.years.store'),['year'=>2026])->assertUnauthorized();
    expect(WmiYear::count())->toBe(0);
});
