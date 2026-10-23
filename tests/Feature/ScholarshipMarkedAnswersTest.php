<?php

use App\Http\Controllers\{ScholarshipAnswerSheetController,ScholarshipController};
use App\Models\{ScholarshipAnswerScan,ScholarshipEvent,ScholarshipScore,Permission};
use App\Support\{ScholarshipAnswerSheetLayout,ScholarshipMarkedAnswers,ScholarshipPermissions};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Blade,DB,Storage};
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
require_once __DIR__.'/../Fixtures/ScholarshipAnswerSheets.php';

beforeEach(function(){
    createScholarshipSchema();
    (require database_path('migrations/2026_10_22_000001_create_scholarship_answer_sheet_workflow.php'))->up();
    (require database_path('migrations/2026_10_22_000002_add_scholarship_question_scores.php'))->up();
    Storage::fake('local');
});

function markedAnswerRequest($user): Request
{
    Auth::setUser($user);$request=Request::create('/students/western-scholarship','GET',['academic_year_id'=>1,'tab'=>'answer-sheets']);
    $request->setUserResolver(fn()=>$user);$request->setLaravelSession(app('session')->driver());app()->instance('request',$request);return $request;
}

it('compares correct wrong blank multiple and unclear answers with the saved key and individual points',function($style){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture($style,5);$choices=$paper->answer_choices;
    $scan=answerSheetUpload($user,$exam,$paper,$sheet,[1=>[$choices[0]],2=>[$choices[1]],4=>[$choices[0],$choices[2]]]);
    $flags=$scan->flags;$flags[5]='unclear';$confidence=$scan->confidence;$confidence[4][$choices[3]]=.2;$scan->update(['flags'=>$flags,'confidence'=>$confidence]);
    $scan->key->update(['question_points'=>[10,15,20,25,30]]);
    $data=app(ScholarshipAnswerSheetController::class)->marked(markedAnswerRequest($user),$scan)->getData(true);
    expect($data['totals'])->toBe(['correct'=>1,'incorrect'=>1,'unanswered'=>1,'multiple'=>1,'unclear'=>1])
        ->and($data['total'])->toBe(10)->and($data['maximum'])->toBe(100)->and($data['rows'][1]['answer'])->toBe($choices[1])
        ->and($data['rows'][1]['correct'])->toBe($choices[0])->and($data['rows'][1]['earned'])->toBe(0)
        ->and(array_column($data['rows'][1]['borders'],'state'))->toBe(['correct','incorrect'])
        ->and(array_column($data['rows'][3]['borders'],'state'))->toContain('warning')
        ->and(array_column($data['rows'][4]['borders'],'state'))->toContain('warning')
        ->and($scan->fresh()->status)->toBe('pending')->and(ScholarshipScore::count())->toBe(0);
    $geometry=app(ScholarshipAnswerSheetLayout::class)->questions(5,$choices,2);
    expect($data['rows'][1]['borders'][1]['x'])->toBe($geometry[1]['dots'][1]['x'])
        ->and($data['width'])->toBe(1000)->and($data['height'])->toBe(1414);
})->with(['letters','numbers']);

it('keeps the original approved revision and verified corrections without changing scores or history',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();
    $scan=answerSheetUpload($user,$exam,$paper,$sheet,[1=>['B'],2=>['A']]);$responses=$scan->detected;$responses[0]='A';
    $service->verify($user,$scan,$responses,$scan->candidate_version,false,'Corrected faint detected mark');
    $service->saveKey($user,$paper->fresh(),array_fill(0,20,'B'),$paper->fresh()->version);$service->approveKey($user,$paper->fresh(),$paper->fresh()->version);
    $controller=app(ScholarshipAnswerSheetController::class);$viewer=answerSheetUser(true,['tab-answer-sheets','answer-marked-view']);
    $score=ScholarshipScore::get()->toArray();$before=$scan->fresh()->toArray();$events=ScholarshipEvent::count();
    $response=$controller->marked(markedAnswerRequest($viewer),$scan->fresh());$data=$response->getData(true);
    expect($data['key_revision'])->toBe(1)->and($data['latest_revision'])->toBe(2)->and($data['total'])->toBe(10)
        ->and($data['rows'][0])->toMatchArray(['answer'=>'A','correct'=>'A','corrected'=>true,'detected'=>'B','status'=>'correct'])
        ->and($data['rows'][1]['correct'])->toBe('A')->and($data['status'])->toBe('accepted')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store','private')
        ->and($scan->fresh()->toArray())->toBe($before)->and(ScholarshipScore::get()->toArray())->toBe($score)->and(ScholarshipEvent::count())->toBe($events);
    expect(fn()=>$controller->details(markedAnswerRequest($viewer),$scan))->toThrow(HttpException::class);
    expect($controller->markedImage(markedAnswerRequest($viewer),$scan)->headers->get('Cache-Control'))->toContain('no-store','private');
    $exam->update(['published_at'=>now()]);expect($controller->marked(markedAnswerRequest($viewer),$scan->fresh())->getData(true)['total'])->toBe(10);
});

it('uses issued geometry and uniform points for legacy sheet versions',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture('numbers',20);
    $snapshot=$service->snapshot($paper,$sheet->candidate,1);$sheet->update(['snapshot'=>$snapshot,'fingerprint'=>hash('sha256',json_encode($snapshot))]);
    $scan=answerSheetUpload($user,$exam,$paper,$sheet,[20=>['5']]);$scan->key->update(['question_points'=>null]);
    $data=app(ScholarshipMarkedAnswers::class)->data($scan);$dot=app(ScholarshipAnswerSheetLayout::class)->questions(20,$paper->answer_choices,1)[19]['dots'][4];
    expect($data['rows'][19]['answer'])->toBe('5')->and($data['rows'][19]['points'])->toBe(5.0)
        ->and($data['rows'][19]['borders'][1]['x'])->toBe($dot['x'])->and($data['rows'][19]['borders'][1]['y'])->toBe($dot['y']);
});

it('denies answer and image access without the marked-view grant or Central scope',function($central,$actions){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();$scan=answerSheetUpload($user,$exam,$paper,$sheet);$controller=app(ScholarshipAnswerSheetController::class);
    $restricted=answerSheetUser($central,$actions);
    expect(fn()=>$controller->marked(markedAnswerRequest($restricted),$scan))->toThrow(HttpException::class)
        ->and(fn()=>$controller->markedImage(markedAnswerRequest($restricted),$scan))->toThrow(HttpException::class);
})->with([[false,['tab-answer-sheets','answer-marked-view']],[true,['tab-answer-sheets','answer-verify']],[true,['answer-marked-view']]]);

it('renders the read-only view action only with permission and keeps verification separate',function($allowed){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();$scan=answerSheetUpload($user,$exam,$paper,$sheet);$scan->update(['uploaded_by'=>1]);
    $viewer=answerSheetUser(true,['tab-answer-sheets',...($allowed?['answer-marked-view']:[])]);
    $view=app(ScholarshipAnswerSheetController::class)->index(markedAnswerRequest($viewer),ScholarshipController::TABS)->getData();
    expect($view['scans']->total())->toBe($allowed?1:0);
    $template=preg_replace('/^@extends.*$|^@section.*$|^@endsection.*$|^@vite.*$/m','',file_get_contents(resource_path('views/western-scholarship/answer-sheets.blade.php')));
    $dom=new DOMDocument();@$dom->loadHTML(Blade::render($template,$view));$xpath=new DOMXPath($dom);
    expect($xpath->query('//*[@data-answer-marked]')->length)->toBe($allowed?1:0)
        ->and($xpath->query('//*[@id="answerMarkedModal"]')->length)->toBe($allowed?1:0)
        ->and($xpath->query('//*[@data-answer-review]')->length)->toBe(0)
        ->and($xpath->query('//*[@id="answerMarkedModal"]//form')->length)->toBe(0);
})->with([true,false]);

it('registers the viewing permission without automatically granting correct-answer access',function(){
    DB::table('access_permissions')->where('code','western-scholarship.answer-marked-view')->delete();
    $migration=require database_path('migrations/2026_10_23_000002_add_marked_answer_sheet_permission.php');$migration->up();$migration->up();
    $permission=Permission::where('code','western-scholarship.answer-marked-view')->sole();
    expect(ScholarshipPermissions::tabGroups()['answer-sheets']['actions'])->toContain('answer-marked-view');
    foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table)expect(DB::table($table)->where('permission_id',$permission->id)->exists())->toBeFalse();
    $migration->down();expect(Permission::where('code',$permission->code)->exists())->toBeFalse();
});

it('requires authentication for the marked data and private image routes',function(){
    $this->getJson(route('western-scholarship.answer-sheets.marked',1))->assertUnauthorized();
    $this->getJson(route('western-scholarship.answer-sheets.marked-image',1))->assertUnauthorized();
});
