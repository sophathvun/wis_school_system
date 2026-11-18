<?php

use App\Http\Controllers\WmiManagementController;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->withoutVite();
    (require database_path('migrations/2026_11_18_000002_create_wmi_years.php'))->up();
    (require database_path('migrations/2026_11_18_000003_create_wmi_exam_centers.php'))->up();
    (require database_path('migrations/2026_07_31_000001_create_location_tables.php'))->up();
    (require database_path('migrations/2026_11_18_000004_create_wmi_schools.php'))->up();
    (require base_path('tests/Fixtures/wmi-enrollment-schema.php'))();
    (require database_path('migrations/2026_11_18_000006_create_wmi_registrations.php'))->up();
    app('view')->getFinder()->prependLocation(base_path('tests/Fixtures/wmi-views'));
});

it('opens the preliminary round by default',function () {
    $response=(new WmiManagementController)->index();
    expect($response->getTargetUrl())->toBe(route('wmi-management.section','preliminary-round'));
});

it('renders all preliminary sections in two columns with the selected tab highlighted',function () {
    foreach(WmiManagementController::SECTIONS['preliminary-round']['tabs'] as $key=>$tab){
        $view=(new WmiManagementController)->section(Request::create('/wmi-management/preliminary-round','GET',['tab'=>$key]),'preliminary-round');
        $html=$view->render();
        expect($view->getData()['tab'])->toBe($key)
            ->and($html)->toContain('wmi-navigation','wmi-content','aria-current="page"','data-wmi-toggle');
        foreach(['Pre Registration','List of Candidates','Exam Card','Scoring','Result','Certificates'] as $label)expect($html)->toContain($label);
    }
});

it('renders settings navigation and the final round landing page',function () {
    foreach(WmiManagementController::SECTIONS['settings']['tabs'] as $key=>$tab){
        $html=(new WmiManagementController)->section(Request::create('/wmi-management/settings','GET',['tab'=>$key]),'settings')->render();
        foreach(['WMI Year','Assign Exam Center','Assign Schools','Assign Date/Time','Awarding Ceremony'] as $label)expect($html)->toContain($label);
    }
    $html=(new WmiManagementController)->section(Request::create('/wmi-management/final-round'),'final-round')->render();
    expect($html)->toContain('Final Round','wmi-workspace-single')->not->toContain('data-wmi-toggle');
});

it('rejects unknown sections and tabs',function () {
    foreach([['invalid',[]],['settings',['tab'=>'bad-tab']],['preliminary-round',['tab'=>['bad']]]] as [$section,$query]){
        expect(fn()=>(new WmiManagementController)->section(Request::create('/wmi-management/'.$section,'GET',$query),$section))
            ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    }
});

it('shows the WMI main menu with the three requested submenus in order',function () {
    $html=view('layouts.partials.wmi-menu',['wmiMenuIcon'=>'/images/wmi_logo.png'])->render();
    expect($html)->toContain('WMI Management','Preliminary Round','Final Round','Settings');
    expect(strpos($html,'Preliminary Round'))->toBeLessThan(strpos($html,'Final Round'));
    expect(strpos($html,'Final Round'))->toBeLessThan(strpos($html,'>Settings<'));
    expect(strpos($html,'>Settings<'))->toBeLessThan(strpos($html,'Import/Export Data'));
});

it('places room allocation in settings and import export in its own submenu',function () {
    $html=(new WmiManagementController)->section(Request::create('/wmi-management/settings','GET',['tab'=>'room-seats']),'settings')->render();
    expect($html)->toContain('wmiSeatModal','data-seat-form')->not->toContain('data-id-import-form','data-registration-form');
    $html=(new WmiManagementController)->section(Request::create('/wmi-management/import-export'),'import-export')->render();
    expect($html)->toContain('Export Candidates','Import WMI IDs','wmiIdImportModal','data-id-import-form')->not->toContain('data-seat-form','data-registration-form');
});
