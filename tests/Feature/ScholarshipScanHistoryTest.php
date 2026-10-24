<?php
use App\Models\{Permission,ScholarshipAnswerScan,ScholarshipScore,ScholarshipEvent};
use App\Services\{ScholarshipAnswerSheets,ScholarshipScanAcceptance};
use App\Support\{ScholarshipPermissions,PermissionHierarchy};
use App\Http\Controllers\{ScholarshipAnswerSheetController,ScholarshipController};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB,Storage};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
require_once __DIR__.'/../Fixtures/ScholarshipAnswerSheets.php';
beforeEach(function(){
    createScholarshipSchema();
    (require database_path('migrations/2026_10_22_000001_create_scholarship_answer_sheet_workflow.php'))->up();
    (require database_path('migrations/2026_10_22_000002_add_scholarship_question_scores.php'))->up();
    (require database_path('migrations/2026_10_23_000007_add_scholarship_scan_absence_detection.php'))->up();
    Storage::fake('local');
});
function historyRequest($user,array $data=[]): Request
{
    Auth::setUser($user);$request=Request::create('/students/western-scholarship','GET',$data+['academic_year_id'=>1,'tab'=>'answer-sheets']);
    $request->setUserResolver(fn()=>$user);$request->setLaravelSession(app('session')->driver());app()->instance('request',$request);return $request;
}
function historyScan($sheet,bool $flagged=false,int $uploader=2): ScholarshipAnswerScan
{
    $answers=array_fill(0,$sheet->paper->question_count,'A');if($flagged)$answers[3]=null;
    return ScholarshipAnswerScan::create(['sheet_id'=>$sheet->id,'key_id'=>$sheet->paper->latestKey->id,'image_path'=>'testing/'.Illuminate\Support\Str::uuid().'.png','original_path'=>'testing/original.png',
        'image_hash'=>hash('sha256',Illuminate\Support\Str::uuid()),'detected'=>$answers,'flags'=>$flagged?[4=>'unclear']:[],'confidence'=>[],'status'=>'pending','candidate_version'=>$sheet->candidate->fresh()->version,'uploaded_by'=>$uploader]);
}

it('accepts clear and flagged scans only after confirmation while keeping unresolved answers at zero',function(){
    [$exam,$user,$paper,$sheet,$sheets]=answerSheetFixture();$second=$sheets->issue($user,$paper,[$exam->candidates()->where('grade_number',1)->whereKeyNot($sheet->candidate_id)->value('id')])->first();
    $one=historyScan($sheet,true);$two=historyScan($second);$service=app(ScholarshipScanAcceptance::class);$preview=$service->preview($user,$paper);
    expect($preview)->toMatchArray(['pending'=>2,'eligible'=>2,'flagged'=>1,'skipped'=>[]]);
    expect(fn()=>$service->accept($user,$paper,[],$preview['fingerprint'],false))->toThrow(ValidationException::class,'flagged');
    expect(ScholarshipScore::count())->toBe(0);
    $service->accept($user,$paper,[],$preview['fingerprint'],true);
    expect($one->fresh()->status)->toBe('accepted')->and($one->fresh()->score)->toBe(95.0)->and($one->fresh()->responses[3])->toBeNull()
        ->and($two->fresh()->score)->toBe(100.0)->and($sheet->candidate->fresh()->reviewed_at)->toBeNull()
        ->and(ScholarshipScore::where('source_reference',(string)$one->id)->value('entry_method'))->toBe('scan')
        ->and(ScholarshipEvent::where('action','answer-scans-accepted-all')->sole()->details['accepted_flagged'])->toBeTrue();
});

it('skips existing zero scores and duplicate pending attempts instead of overwriting or choosing a scan',function(){
    [$exam,$user,$paper,$sheet,$sheets]=answerSheetFixture();$other=$sheets->issue($user,$paper,[$exam->candidates()->where('grade_number',1)->whereKeyNot($sheet->candidate_id)->value('id')])->first();
    historyScan($sheet);historyScan($other);historyScan($other);$score=$sheet->candidate->scores()->create(['subject_id'=>$paper->subject_id,'score'=>0,'entry_method'=>'manual']);
    $preview=app(ScholarshipScanAcceptance::class)->preview($user,$paper);
    expect($preview['eligible'])->toBe(0)->and($preview['skipped'])->toBe(['Existing score (including zero)'=>1,'Multiple pending scans for the same student'=>2])
        ->and($score->fresh()->score)->toBe(0.0)->and(ScholarshipAnswerScan::where('status','pending')->count())->toBe(3);
});

it('rolls back every accepted score when the bulk audit cannot be saved',function(){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();$scan=historyScan($sheet);$service=app(ScholarshipScanAcceptance::class);$preview=$service->preview($user,$paper);
    ScholarshipEvent::creating(function($event){if($event->action==='answer-scans-accepted-all')throw new RuntimeException('Audit unavailable');});
    try{expect(fn()=>$service->accept($user,$paper,[],$preview['fingerprint'],false))->toThrow(RuntimeException::class,'Audit unavailable');}
    finally{ScholarshipEvent::flushEventListeners();}
    expect($scan->fresh()->status)->toBe('pending')->and(ScholarshipScore::count())->toBe(0);
});

it('limits bulk acceptance to the selected paper center and room',function(){
    [$exam,$user,$paper,$sheet,$sheets]=answerSheetFixture();$one=historyScan($sheet);$other=$exam->candidates()->where('grade_number',1)->whereKeyNot($sheet->candidate_id)->first();$other->update(['room_number'=>2]);
    $otherSheet=$sheets->issue($user,$paper,[$other->id])->first();$two=historyScan($otherSheet);
    $otherPaper=$sheets->savePaper($user,$exam,['subject_id'=>$exam->subjects()->where('id','!=',$paper->subject_id)->first()->id,'grade'=>1,'paper_version'=>'A','question_count'=>20,'choice_style'=>'letters','choice_count'=>5,'points'=>5,'version'=>0]);
    $sheets->saveKey($user,$otherPaper,array_fill(0,20,'A'),$otherPaper->version);$sheets->approveKey($user,$otherPaper->fresh(),$otherPaper->fresh()->version);
    $three=historyScan($sheets->issue($user,$otherPaper->fresh(),[$sheet->candidate_id])->first());
    $service=app(ScholarshipScanAcceptance::class);$filters=['center'=>$sheet->candidate->exam_center_id,'room'=>1];$preview=$service->preview($user,$paper,$filters);
    $service->accept($user,$paper,$filters,$preview['fingerprint'],true);
    expect($one->fresh()->status)->toBe('accepted')->and($two->fresh()->status)->toBe('pending')->and($three->fresh()->status)->toBe('pending');
});

it('rejects stale previews and published results atomically',function($change){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();$scan=historyScan($sheet);$service=app(ScholarshipScanAcceptance::class);$preview=$service->preview($user,$paper);
    match($change){'published'=>$exam->update(['published_at'=>now()]),'historical'=>$exam->update(['historical_imported_at'=>now()]),
        'scan'=>$scan->update(['flags'=>[1=>'unclear']]),'score'=>$sheet->candidate->scores()->create(['subject_id'=>$paper->subject_id,'score'=>50])};
    expect(fn()=>$service->accept($user,$paper,[],$preview['fingerprint'],true))->toThrow(ValidationException::class);
    expect($scan->fresh()->status)->toBe('pending');
})->with(['published','historical','scan','score']);

it('requires both Central acceptance and verification permissions',function($central,$denied){
    [$exam,$admin,$paper,$sheet]=answerSheetFixture();historyScan($sheet);
    $user=answerSheetUser($central,array_diff(array_keys(ScholarshipPermissions::catalog()),[$denied]));
    expect(fn()=>app(ScholarshipScanAcceptance::class)->preview($user,$paper))->toThrow(HttpException::class);
})->with([[true,'answer-accept-all'],[true,'answer-verify'],[true,'tab-answer-sheets'],[false,'']]);

it('requires user-bound unexpired one-use acceptance confirmation tokens',function(){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();$scan=historyScan($sheet,true);$controller=app(ScholarshipAnswerSheetController::class);
    $preview=$controller->acceptAllPreview(historyRequest($user),$paper)->getData(true);$body=['token'=>$preview['token'],'confirmed'=>true,'accept_flagged'=>true];
    expect(fn()=>$controller->acceptAll(historyRequest($user,array_replace($body,['confirmed'=>false])),$paper))->toThrow(ValidationException::class);
    expect(fn()=>$controller->acceptAll(historyRequest($user,array_replace($body,['accept_flagged'=>false])),$paper))->toThrow(ValidationException::class);
    $this->travel(11)->minutes();expect(fn()=>$controller->acceptAll(historyRequest($user,$body),$paper))->toThrow(HttpException::class);$this->travelBack();
    $controller->acceptAll(historyRequest($user,$body),$paper);expect($scan->fresh()->status)->toBe('accepted');
    expect(fn()=>$controller->acceptAll(historyRequest($user,$body),$paper))->toThrow(HttpException::class);
});

it('paginates scan history with enrollment page sizes and clamps out of range pages',function(){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();for($i=0;$i<35;$i++)historyScan($sheet,false,$i<21?2:1);
    $controller=app(ScholarshipAnswerSheetController::class);$view=$controller->index(historyRequest($user),ScholarshipController::TABS)->getData();
    expect($view['scans']->perPage())->toBe(10)->and($view['scans']->total())->toBe(35);
    $view=$controller->index(historyRequest($user,['scan_per_page'=>25,'scan_page'=>2]),ScholarshipController::TABS)->getData();expect($view['scans']->count())->toBe(10)->and($view['scans']->firstItem())->toBe(26);
    $view=$controller->index(historyRequest($user,['scan_page'=>999]),ScholarshipController::TABS)->getData();expect($view['scans']->currentPage())->toBe(4);
    $response=$controller->index(historyRequest($user,['scan_history'=>1,'scan_page'=>2]),ScholarshipController::TABS)->getData(true);
    expect($response['html'])->toContain('data-answer-scan-history-list','answerScansPagination','<td>11</td>');
    $campus=answerSheetUser(false,array_diff(array_keys(ScholarshipPermissions::catalog()),['answer-verify','answer-marked-view']));
    $view=$controller->index(historyRequest($campus),ScholarshipController::TABS)->getData();expect($view['scans']->total())->toBe(21);
});

it('rejects unsupported scan page sizes',function($size){
    app(ScholarshipAnswerSheetController::class)->index(historyRequest(answerSheetUser(),['scan_per_page'=>$size]),ScholarshipController::TABS);
})->with([0,15,'all',1000])->throws(ValidationException::class);

it('registers the acceptance permission without grants and requires verification when assigning it',function(){
    $migration=require database_path('migrations/2026_10_23_000006_add_answer_sheet_accept_all_permission.php');$migration->up();$migration->up();ScholarshipPermissions::registerMissing();
    $permission=Permission::where('code','western-scholarship.answer-accept-all')->sole();
    expect(ScholarshipPermissions::tabGroups()['answer-sheets']['actions'])->toContain('answer-accept-all');
    foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table)expect(DB::table($table)->where('permission_id',$permission->id)->exists())->toBeFalse();
    $ids=Permission::whereIn('code',['students.view','western-scholarship.view','western-scholarship.tab-answer-sheets','western-scholarship.answer-accept-all'])->pluck('id')->all();
    expect(PermissionHierarchy::normalizeIds($ids,Permission::all()))->not->toContain($permission->id);
    $this->postJson(route('western-scholarship.answer-sheets.accept-all',1))->assertUnauthorized();
});
