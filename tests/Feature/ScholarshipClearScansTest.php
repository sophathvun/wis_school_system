<?php

use App\Models\{ScholarshipAnswerScan,ScholarshipAnswerSheet,ScholarshipEvent,ScholarshipPaper,ScholarshipScore};
use App\Services\{ScholarshipAnswerSheets,ScholarshipTestScans};
use App\Support\{PermissionHierarchy,ScholarshipPermissions};
use App\Http\Controllers\{ScholarshipAnswerSheetController,ScholarshipController};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Blade,DB,Storage};
use Illuminate\Validation\ValidationException;
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

function clearScansRequest($user,array $data=[]): Request
{
    Auth::setUser($user);$request=Request::create('/students/western-scholarship','GET',$data+['academic_year_id'=>1,'tab'=>'answer-sheets']);
    $request->setUserResolver(fn()=>$user);$request->setLaravelSession(app('session')->driver());app()->instance('request',$request);return $request;
}
function testScanAttempt($sheet,string $status='pending')
{
    $path='scholarship-answer-scans/'.Illuminate\Support\Str::uuid().'.png';Storage::disk('local')->put($path,'test image');
    return ScholarshipAnswerScan::create(['sheet_id'=>$sheet->id,'key_id'=>$sheet->paper->latestKey->id,'image_path'=>$path,'original_path'=>$path,
        'image_hash'=>hash('sha256',$path),'detected'=>array_fill(0,20,'A'),'flags'=>[],'confidence'=>[],'status'=>$status,
        'score'=>$status==='accepted'?90:null,'candidate_version'=>$sheet->candidate->version,'uploaded_by'=>2]);
}

it('registers a separate clear permission under Answer Sheets without granting it to anyone',function(){
    DB::table('access_permissions')->where('code','western-scholarship.answer-clear-scans')->delete();
    $migration=require database_path('migrations/2026_10_23_000001_add_answer_sheet_clear_scans_permission.php');$migration->up();$migration->up();
    $permission=DB::table('access_permissions')->where('code','western-scholarship.answer-clear-scans')->first();
    expect(DB::table('access_permissions')->where('code',$permission->code)->count())->toBe(1)
        ->and(ScholarshipPermissions::tabGroups()['answer-sheets']['actions'])->toContain('answer-clear-scans');
    foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table)expect(DB::table($table)->where('permission_id',$permission->id)->exists())->toBeFalse();
    expect(PermissionHierarchy::normalizeIds([$permission->id],App\Models\Permission::all()))->toBe([]);
    $migration->down();expect(DB::table('access_permissions')->where('code',$permission->code)->exists())->toBeFalse();
});

it('clears accepted and pending attempts and their current scan scores while keeping sheets keys and manual scores',function(){
    [$exam,$user,$paper,$sheet,$sheets]=answerSheetFixture();$service=app(ScholarshipTestScans::class);
    $scan=answerSheetUpload($user,$exam,$paper,$sheet,[1=>['A']]);$sheets->verify($user,$scan,$scan->detected,$scan->candidate_version,false,'');
    $recheck=$sheets->recheck($user,$scan->fresh(),'Testing another scan');
    $otherCandidate=$exam->candidates()->where('grade_number',1)->whereKeyNot($sheet->candidate_id)->first();
    $otherSheet=$sheets->issue($user,$paper,[$otherCandidate->id])->first();$pending=testScanAttempt($otherSheet);
    $manual=$otherCandidate->scores()->create(['subject_id'=>$paper->subject_id,'score'=>85,'entry_method'=>'manual']);
    $otherSubject=$sheet->candidate->scores()->where('subject_id','!=',$paper->subject_id)->first();$otherSubject->update(['score'=>74,'entry_method'=>'historical','source_reference'=>'history']);
    $otherBefore=$otherSubject->fresh()->toArray();$manualBefore=$manual->fresh()->toArray();
    $sheet->candidate->update(['reviewed_by'=>2,'reviewed_at'=>now(),'result_total'=>400,'result_rank'=>1,'award_percent'=>100]);
    $exam->awards()->create(['grade_number'=>1,'slot'=>'first','candidate_id'=>$sheet->candidate_id,'award_percent'=>100]);
    $exam->awards()->create(['grade_number'=>2,'slot'=>'first','candidate_id'=>$exam->candidates()->where('grade_number',2)->value('id'),'award_percent'=>100]);
    $tokens=$paper->sheets()->pluck('token')->all();$key=$paper->latestKey->toArray();$version=$sheet->candidate->fresh()->version;
    $preview=$service->preview($user,$paper);
    expect($preview)->toMatchArray(['scans'=>3,'pending'=>2,'accepted'=>1,'students'=>2,'scores'=>1,'manual_scores_kept'=>1,'reviews_reset'=>1,'awards_reset'=>1]);
    $result=$service->clear($user,$paper,[],$preview['fingerprint']);
    expect($result['scans'])->toBe(3)->and($result['image_cleanup_pending'])->toBe(0)->and(ScholarshipAnswerScan::count())->toBe(0)
        ->and($paper->sheets()->pluck('token')->all())->toBe($tokens)->and($paper->latestKey->fresh()->toArray())->toBe($key)
        ->and($manual->fresh()->toArray())->toBe($manualBefore)->and($otherSubject->fresh()->toArray())->toBe($otherBefore)
        ->and($sheet->candidate->scores()->where('subject_id',$paper->subject_id)->exists())->toBeFalse()
        ->and($sheet->candidate->fresh()->reviewed_at)->toBeNull()->and($sheet->candidate->fresh()->result_total)->toBeNull()
        ->and($sheet->candidate->fresh()->version)->toBe($version+1)->and($exam->awards()->pluck('grade_number')->all())->toBe([2]);
    Storage::disk('local')->assertMissing([$scan->image_path,$scan->original_path,$pending->image_path]);
    $audit=ScholarshipEvent::where('action','answer-test-scans-cleared')->sole();
    expect($audit->user_id)->toBe($user->id)->and($audit->details['counts']['scans'])->toBe(3)->and($audit->details['scores'])->toHaveCount(1);
    // Clearing makes the same physical sheet uploadable again without relinking or reprinting.
    $new=answerSheetUpload($user,$exam->fresh(),$paper,$sheet,[1=>['A']]);expect($new->status)->toBe('pending')->and($sheets->current($sheet->fresh()))->toBeTrue();
});

it('preserves a manually replaced score even if its earlier accepted scan is cleared',function(){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();$scan=testScanAttempt($sheet,'accepted');
    $score=$sheet->candidate->scores()->create(['subject_id'=>$paper->subject_id,'score'=>99,'entry_method'=>'manual']);
    $sheet->candidate->update(['reviewed_at'=>now(),'reviewed_by'=>2]);$before=$sheet->candidate->fresh()->toArray();
    $service=app(ScholarshipTestScans::class);$preview=$service->preview($user,$paper);expect($preview['scores'])->toBe(0);
    $service->clear($user,$paper,[],$preview['fingerprint']);
    expect($score->fresh()->score)->toBe(99.0)->and($sheet->candidate->fresh()->toArray())->toBe($before);
});

it('limits the reset to the selected paper center and room and retains shared image references',function(){
    [$exam,$user,$paper,$sheet,$sheets]=answerSheetFixture();$first=testScanAttempt($sheet);
    $candidate=$exam->candidates()->where('grade_number',1)->whereKeyNot($sheet->candidate_id)->first();$candidate->update(['room_number'=>2]);
    $roomSheet=$sheets->issue($user,$paper,[$candidate->id])->first();$roomScan=testScanAttempt($roomSheet);
    $shared=$roomScan->image_path;$roomScan->update(['image_path'=>$first->image_path]);
    $south=$exam->candidates()->where('grade_number',2)->first();$south->update(['grade_number'=>1]);
    $southScan=testScanAttempt($sheets->issue($user,$paper,[$south->id])->first());
    $different=$sheets->savePaper($user,$exam,['subject_id'=>$paper->subject_id,'grade'=>1,'paper_version'=>'B','question_count'=>20,'choice_style'=>'letters','choice_count'=>5,'points'=>5,'version'=>0]);
    $sheets->saveKey($user,$different,array_fill(0,20,'B'),$different->version);$sheets->approveKey($user,$different->fresh(),$different->fresh()->version);
    $differentScan=testScanAttempt($sheets->issue($user,$different->fresh(),[$sheet->candidate_id])->first());
    $filters=['center'=>$sheet->candidate->exam_center_id,'room'=>1];$service=app(ScholarshipTestScans::class);$preview=$service->preview($user,$paper,$filters);
    expect($preview['scans'])->toBe(1)->and($preview['scope'])->toContain('North Exam Center','Room 1','Paper A');
    $service->clear($user,$paper,$filters,$preview['fingerprint']);
    expect(ScholarshipAnswerScan::orderBy('id')->pluck('id')->all())->toBe([$roomScan->id,$southScan->id,$differentScan->id]);
    Storage::disk('local')->assertExists([$first->image_path,$shared,$southScan->image_path,$differentScan->image_path]);
});

it('rejects stale previews when a scan score or exam changes',function($change){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();$scan=testScanAttempt($sheet);$service=app(ScholarshipTestScans::class);$preview=$service->preview($user,$paper);
    match($change){'scan'=>testScanAttempt($sheet),'score'=>$sheet->candidate->scores()->create(['subject_id'=>$paper->subject_id,'score'=>50]),'exam'=>$exam->increment('version')};
    $before=ScholarshipAnswerScan::count();expect(fn()=>$service->clear($user,$paper,[],$preview['fingerprint']))->toThrow(ValidationException::class,'changed after the preview');
    expect(ScholarshipAnswerScan::count())->toBe($before);Storage::disk('local')->assertExists($scan->image_path);
})->with(['scan','score','exam']);

it('requires Central scope and both tab and clear grants for preview and reset',function($central,$actions){
    [$exam,$admin,$paper,$sheet]=answerSheetFixture();$scan=testScanAttempt($sheet);$service=app(ScholarshipTestScans::class);$preview=$service->preview($admin,$paper);
    $user=answerSheetUser($central,$actions);
    expect(fn()=>$service->preview($user,$paper))->toThrow(HttpException::class)
        ->and(fn()=>$service->clear($user,$paper,[],$preview['fingerprint']))->toThrow(HttpException::class);
    expect(ScholarshipAnswerScan::count())->toBe(1);Storage::disk('local')->assertExists($scan->image_path);
})->with([[false,['tab-answer-sheets','answer-clear-scans']],[true,['tab-answer-sheets']],[true,['answer-clear-scans']]]);

it('requires a current user-bound confirmation and rejects permission revoked after preview',function(){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();testScanAttempt($sheet);$controller=app(ScholarshipAnswerSheetController::class);
    $preview=$controller->clearScansPreview(clearScansRequest($user),$paper)->getData(true);
    expect($preview)->toHaveKey('token')->not->toHaveKey('fingerprint');
    expect(fn()=>$controller->clearScans(clearScansRequest($user,['confirmed'=>false,'token'=>$preview['token']]),$paper))->toThrow(ValidationException::class);
    expect(fn()=>$controller->clearScans(clearScansRequest($user,['confirmed'=>true,'token'=>str_repeat('0',32)]),$paper))->toThrow(HttpException::class);
    $revoked=answerSheetUser(true,['tab-answer-sheets']);
    expect(fn()=>$controller->clearScans(clearScansRequest($revoked,['confirmed'=>true,'token'=>$preview['token']]),$paper))->toThrow(HttpException::class);
    $result=$controller->clearScans(clearScansRequest($user,['confirmed'=>true,'token'=>$preview['token']]),$paper)->getData(true);
    expect($result['scans'])->toBe(1)->and(ScholarshipAnswerSheet::count())->toBe(1);
    expect(fn()=>$controller->clearScans(clearScansRequest($user,['confirmed'=>true,'token'=>$preview['token']]),$paper))->toThrow(HttpException::class);
});

it('rejects expired confirmations and leaves scans intact',function(){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();testScanAttempt($sheet);$controller=app(ScholarshipAnswerSheetController::class);
    $preview=$controller->clearScansPreview(clearScansRequest($user),$paper)->getData(true);$this->travel(11)->minutes();
    expect(fn()=>$controller->clearScans(clearScansRequest($user,['confirmed'=>true,'token'=>$preview['token']]),$paper))->toThrow(HttpException::class,'expired');
    expect(ScholarshipAnswerScan::count())->toBe(1);
});

it('rejects published or historical exams even when the preview was created before locking',function($field){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();$scan=testScanAttempt($sheet);$service=app(ScholarshipTestScans::class);$preview=$service->preview($user,$paper);
    $exam->update([$field=>now()]);
    expect(fn()=>$service->preview($user,$paper))->toThrow(ValidationException::class)
        ->and(fn()=>$service->clear($user,$paper,[],$preview['fingerprint']))->toThrow(ValidationException::class);
    expect(ScholarshipAnswerScan::count())->toBe(1);Storage::disk('local')->assertExists($scan->image_path);
})->with(['published_at','historical_imported_at']);

it('shows Clear Test Scans only to an authorized Central user and disables it for locked exams',function($allowed,$locked){
    [$exam,$admin,$paper]=answerSheetFixture();if($locked)$exam->update(['published_at'=>now()]);
    $user=answerSheetUser(true,['tab-answer-sheets',...($allowed?['answer-clear-scans']:[])]);
    $view=app(ScholarshipAnswerSheetController::class)->index(clearScansRequest($user),ScholarshipController::TABS)->getData();
    $template=preg_replace('/^@extends.*$|^@section.*$|^@endsection.*$|^@vite.*$/m','',file_get_contents(resource_path('views/western-scholarship/answer-sheets.blade.php')));
    $dom=new DOMDocument();@$dom->loadHTML(Blade::render($template,$view));$xpath=new DOMXPath($dom);
    expect($xpath->query('//*[@data-answer-clear-scans]')->length)->toBe($allowed?1:0)
        ->and($xpath->query('//*[@data-answer-clear-scans][@disabled]')->length)->toBe($allowed&&$locked?1:0);
})->with([[true,false],[false,false],[true,true]]);

it('requires authentication on both reset routes',function(){
    $this->getJson(route('western-scholarship.answer-sheets.clear-scans-preview',1))->assertUnauthorized();
    $this->postJson(route('western-scholarship.answer-sheets.clear-scans',1))->assertUnauthorized();
});
