<?php

use App\Models\{ScholarshipAnswerScan,ScholarshipAnswerSheet,ScholarshipEvent,ScholarshipExam,ScholarshipPaper,ScholarshipScore,ScholarshipStudentRank,StudentEnrollment,Permission};
use App\Services\{ScholarshipAnswerSheets,ScholarshipReseating};
use App\Support\{PermissionHierarchy,ScholarshipPermissions};
use App\Http\Controllers\{ScholarshipController,ScholarshipAnswerSheetController};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Blade,DB,Storage};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
require_once __DIR__.'/../Fixtures/ScholarshipAnswerSheets.php';
require_once __DIR__.'/../Fixtures/ScholarshipScoredExam.php';

beforeEach(function(){
    createScholarshipSchema();
    (require database_path('migrations/2026_10_22_000001_create_scholarship_answer_sheet_workflow.php'))->up();
    (require database_path('migrations/2026_10_22_000002_add_scholarship_question_scores.php'))->up();
    (require database_path('migrations/2026_10_23_000007_add_scholarship_scan_absence_detection.php'))->up();
    Storage::fake('local');
});

function reseatingRequest($user,array $data=[]): Request
{
    Auth::setUser($user);$request=Request::create('/students/western-scholarship','GET',$data+['academic_year_id'=>1,'tab'=>'seat-plan']);
    $request->setUserResolver(fn()=>$user);$request->setLaravelSession(app('session')->driver());app()->instance('request',$request);return $request;
}

it('clears all test work including zero manual scores and unlinks all subjects while preserving ranks papers and keys',function(){
    [$user,$workflow,$exam,$paper,$sheet,$scan]=scoredScholarshipFixture(true);$sheets=app(ScholarshipAnswerSheets::class);
    $second=$exam->candidates()->where('campus_id',2)->sole();
    $workflow->saveScores($user,$exam,$second->fresh(),[$paper->subject_id=>0],false,$second->fresh()->version);$workflow->review($user,$exam,$second->fresh(),$second->fresh()->version);
    $otherSheet=$sheets->issue($user,$paper,[$second->id])->first();$otherScan=answerSheetUpload($user,$exam,$paper,$otherSheet,[2=>['B']]);
    $recheck=$sheets->recheck($user,$scan,'Test scan recheck');
    $exam->awards()->create(['grade_number'=>3,'slot'=>'total_1','candidate_id'=>$sheet->candidate_id,'award_percent'=>100]);
    $exam->committee()->create(['user_id'=>2,'role'=>'invigilator','room_number'=>1,'exam_center_id'=>$sheet->candidate->exam_center_id]);
    $invigilator=$exam->invigilators()->create(['code'=>'INV-01','name_en'=>'Test Teacher','gender'=>'M','campus'=>'BCH','position'=>'Teacher','phone'=>'012345678']);
    $session=$exam->examSessions()->create(['number'=>1,'exam_date'=>'2026-11-20','starts_at'=>'08:00','ends_at'=>'09:00']);
    $exam->invigilatorDuties()->create(['invigilator_id'=>$invigilator->id,'session_id'=>$session->id,'exam_center_id'=>$sheet->candidate->exam_center_id,'room_number'=>1]);
    $ranks=ScholarshipStudentRank::get()->toArray();$keys=$paper->keys()->get()->toArray();$subjects=$exam->subjects()->get()->toArray();$centers=$exam->centers()->get()->toArray();
    $candidateIds=$exam->candidates()->pluck('id')->all();$paperBefore=$paper->fresh()->only(['id','subject_id','grade_number','paper_version','question_count','answer_choices','draft_answers','draft_points']);
    $service=app(ScholarshipReseating::class);$preview=$service->preview($user,$exam);
    expect($preview)->toMatchArray(['scope'=>$exam->examinationTitle().' — Academic Year: '.$exam->academicYear->academic_year,'students'=>2,'scores'=>2,'manual_scores'=>1,'scans'=>3,'links'=>2,'reviews'=>2,'awards'=>1,'seats'=>2,'room_duties'=>2,'papers_kept'=>1]);
    $result=$service->reset($user,$exam,$preview['fingerprint'],'Clear test work and rearrange seats');
    expect($result['image_cleanup_pending'])->toBe(0)->and(ScholarshipScore::count())->toBe(0)->and(ScholarshipAnswerScan::count())->toBe(0)
        ->and(ScholarshipAnswerSheet::count())->toBe(0)->and($exam->awards()->count())->toBe(0)->and($exam->invigilatorDuties()->count())->toBe(0)
        ->and($exam->committee()->whereNotNull('room_number')->count())->toBe(0)->and($exam->committee()->count())->toBe(2)
        ->and($exam->invigilators()->count())->toBe(1)->and($exam->examSessions()->count())->toBe(1)
        ->and(ScholarshipStudentRank::get()->toArray())->toBe($ranks)->and($paper->keys()->get()->toArray())->toBe($keys)
        ->and($exam->subjects()->get()->toArray())->toBe($subjects)->and($exam->centers()->get()->toArray())->toBe($centers)
        ->and($paper->fresh()->only(array_keys($paperBefore)))->toBe($paperBefore)->and($exam->candidates()->pluck('id')->all())->toBe($candidateIds)
        ->and($exam->fresh()->seats_locked_at)->toBeNull();
    foreach($exam->candidates()->get() as $candidate)expect($candidate->room_number)->toBeNull()->and($candidate->room_seat)->toBeNull()->and($candidate->candidate_code)->toBeNull()
        ->and($candidate->reviewed_at)->toBeNull()->and($candidate->scored_by)->toBeNull()->and($candidate->absent)->toBeFalse();
    Storage::disk('local')->assertMissing([$scan->image_path,$scan->original_path,$otherScan->image_path]);
    $audit=ScholarshipEvent::where('action','exam-reset-for-reseating')->sole();expect($audit->details['reason'])->toContain('rearrange seats')->and($audit->details['scores'])->toHaveCount(2);
});

it('allows adding a missing campus configuring centers and regenerating seats without recreating approved papers or keys',function(){
    [$user,$workflow,$exam,$paper,$oldSheet,$oldScan]=scoredScholarshipFixture();$service=app(ScholarshipReseating::class);
    $keyIds=$paper->keys()->pluck('id')->all();$preview=$service->preview($user,$exam);$service->reset($user,$exam,$preview['fingerprint'],'Restart testing with all campuses');
    $workflow->saveRank($user,StudentEnrollment::find(3),1,98);
    $center=$exam->centers()->first();$subject=$exam->subjects()->first();
    $config=['academic_year_id'=>1,'exam_date'=>'2026-11-20','room_capacity'=>2,'version'=>$exam->fresh()->version,
        'subjects'=>[$subject->only(['id','name','grade_from','grade_to','max_score','weight'])],
        'exam_centers'=>[['id'=>$center->id,'key'=>'north','name'=>$center->name],['key'=>'south','name'=>'BKK Center']],
        'center_assignments'=>[1=>'north',2=>'south']];
    foreach([['name'=>'Changed Subject'],['max_score'=>50],['grade_from'=>2],['weight'=>2]] as $change){
        $invalid=$config;$invalid['subjects'][0]=array_replace($invalid['subjects'][0],$change);
        expect(fn()=>$workflow->saveExam($user,$exam->fresh(),$invalid))->toThrow(ValidationException::class,'answer-sheet papers');
    }
    $exam=$workflow->saveExam($user,$exam->fresh(),$config);$plan=$workflow->plan($exam);
    expect($plan['unassigned_campuses'])->toBe([])->and($plan['rows'])->toHaveCount(2);
    $workflow->lockPlan($user,$exam,$plan['token']);$sheets=app(ScholarshipAnswerSheets::class)->linkStudents($user,$paper->fresh(),$paper->fresh()->version);
    expect($sheets)->toHaveCount(2)->and($sheets->pluck('token')->all())->not->toContain($oldSheet->token)
        ->and($paper->keys()->pluck('id')->all())->toBe($keyIds)->and($exam->fresh()->seats_locked_at)->not->toBeNull();
    expect(fn()=>answerSheetUpload($user,$exam->fresh(),$paper->fresh(),$oldSheet))->toThrow(ValidationException::class,'identification code');
    $newScan=answerSheetUpload($user,$exam->fresh(),$paper->fresh(),$sheets->first(),[1=>['A']]);expect($newScan->status)->toBe('pending');
});

it('preserves another exam and any shared image file still referenced there',function(){
    [$user,$workflow,$exam,$paper,$sheet,$scan]=scoredScholarshipFixture();
    $other=scholarshipSeatReportExam(1);$other->update(['academic_year_id'=>2]);$sheets=app(ScholarshipAnswerSheets::class);
    $otherPaper=$sheets->savePaper($user,$other,['subject_id'=>$other->subjects()->first()->id,'grade'=>1,'paper_version'=>'A','question_count'=>20,'choice_style'=>'letters','choice_count'=>5,'points'=>5,'version'=>0]);
    $sheets->saveKey($user,$otherPaper,array_fill(0,20,'A'),$otherPaper->version);$sheets->approveKey($user,$otherPaper->fresh(),$otherPaper->fresh()->version);
    $otherSheet=$sheets->issue($user,$otherPaper->fresh(),[$other->candidates()->where('grade_number',1)->value('id')])->first();
    $otherScan=$scan->replicate();$otherScan->sheet_id=$otherSheet->id;$otherScan->key_id=$otherPaper->fresh()->latestKey->id;$otherScan->save();
    $before=$otherScan->fresh()->toArray();$otherBefore=$other->fresh()->toArray();
    $service=app(ScholarshipReseating::class);$preview=$service->preview($user,$exam);$service->reset($user,$exam,$preview['fingerprint'],'Reset one selected exam');
    expect($otherScan->fresh()->toArray())->toBe($before)->and($other->fresh()->toArray())->toBe($otherBefore)->and($otherPaper->sheets()->count())->toBe(1);
    Storage::disk('local')->assertExists([$scan->image_path,$scan->original_path]);
});

it('rejects reset after data changes or publication and retains the files and scores',function($change){
    [$user,$workflow,$exam,$paper,$sheet,$scan]=scoredScholarshipFixture();$service=app(ScholarshipReseating::class);$preview=$service->preview($user,$exam);
    match($change){'published'=>$exam->update(['published_at'=>now()]),'historical'=>$exam->update(['historical_imported_at'=>now()]),
        'version'=>$exam->increment('version'),'score'=>ScholarshipScore::first()->update(['score'=>50]),'scan'=>app(ScholarshipAnswerSheets::class)->recheck($user,$scan,'Another test scan')};
    expect(fn()=>$service->reset($user,$exam,$preview['fingerprint'],'Clear exam testing data'))->toThrow(ValidationException::class);
    expect(ScholarshipScore::count())->toBe(1)->and(ScholarshipAnswerSheet::count())->toBe(1);Storage::disk('local')->assertExists($scan->image_path);
})->with(['published','historical','version','score','scan']);

it('requires Central scope and the dedicated reset and Seat Plan tab grants on both endpoints',function($central,$denied){
    [$admin,$workflow,$exam]=scoredScholarshipFixture();$user=answerSheetUser($central,array_diff(array_keys(ScholarshipPermissions::catalog()),$denied));
    $service=app(ScholarshipReseating::class);$preview=$service->preview($admin,$exam);
    expect(fn()=>$service->preview($user,$exam))->toThrow(HttpException::class)
        ->and(fn()=>$service->reset($user,$exam,$preview['fingerprint'],'Clear test exam data'))->toThrow(HttpException::class);
})->with([[false,[]],[true,['reset-for-reseating']],[true,['tab-seat-plan']]]);

it('requires a user-bound expiring preview typed confirmation and a reason and rechecks revoked permission',function(){
    [$user,$workflow,$exam]=scoredScholarshipFixture();$controller=app(ScholarshipController::class);
    $preview=$controller->reseatingPreview(reseatingRequest($user),$exam)->getData(true);
    expect($preview)->toHaveKey('token')->not->toHaveKey('fingerprint');
    $payload=['token'=>$preview['token'],'confirmed'=>true,'confirmation'=>'RESET','reason'=>'Clear testing and start again'];
    foreach([['confirmation'=>''],['confirmed'=>false],['reason'=>'']] as $invalid)expect(fn()=>$controller->reseatingReset(reseatingRequest($user,array_replace($payload,$invalid)),$exam))->toThrow(ValidationException::class);
    $other=answerSheetUser();$other->id=1;expect(fn()=>$controller->reseatingReset(reseatingRequest($other,$payload),$exam))->toThrow(HttpException::class);
    $revoked=answerSheetUser(true,array_diff(array_keys(ScholarshipPermissions::catalog()),['reset-for-reseating']));
    expect(fn()=>$controller->reseatingReset(reseatingRequest($revoked,$payload),$exam))->toThrow(HttpException::class);
    $result=$controller->reseatingReset(reseatingRequest($user,$payload),$exam)->getData(true);
    expect($result['url'])->toContain('tab=assign-rank')->and(ScholarshipScore::count())->toBe(0);
    expect(fn()=>$controller->reseatingReset(reseatingRequest($user,$payload),$exam))->toThrow(HttpException::class);
});

it('rejects an expired preview without removing any data',function(){
    [$user,$workflow,$exam]=scoredScholarshipFixture();$controller=app(ScholarshipController::class);$preview=$controller->reseatingPreview(reseatingRequest($user),$exam)->getData(true);
    $this->travel(11)->minutes();expect(fn()=>$controller->reseatingReset(reseatingRequest($user,['token'=>$preview['token'],'confirmed'=>true,'confirmation'=>'RESET','reason'=>'Clear test scores']),$exam))->toThrow(HttpException::class,'expired');
    expect(ScholarshipScore::count())->toBe(1);
});

it('rolls back all database deletion before touching image files when the reset audit cannot be saved',function(){
    [$user,$workflow,$exam,$paper,$sheet,$scan]=scoredScholarshipFixture();$service=app(ScholarshipReseating::class);$preview=$service->preview($user,$exam);
    ScholarshipEvent::creating(function($event){if($event->action==='exam-reset-for-reseating')throw new RuntimeException('Audit unavailable');});
    try{expect(fn()=>$service->reset($user,$exam,$preview['fingerprint'],'Reset exam testing'))->toThrow(RuntimeException::class,'Audit unavailable');}
    finally{ScholarshipEvent::flushEventListeners();}
    expect(ScholarshipScore::count())->toBe(1)->and(ScholarshipAnswerSheet::count())->toBe(1)->and(ScholarshipAnswerScan::count())->toBe(1)
        ->and($exam->fresh()->seats_locked_at)->not->toBeNull();Storage::disk('local')->assertExists($scan->image_path);
});

it('shows a reset button on the answer-sheet list only with permission and disables it when published',function($allowed,$published){
    [$admin,$workflow,$exam]=scoredScholarshipFixture();if($published)$exam->update(['published_at'=>now()]);
    $user=answerSheetUser(true,array_diff(array_keys(ScholarshipPermissions::catalog()),$allowed?[]:['reset-for-reseating']));
    $request=reseatingRequest($user);$request->merge(['tab'=>'answer-sheets']);
    $view=app(ScholarshipAnswerSheetController::class)->index($request,ScholarshipController::TABS)->getData();
    $template=preg_replace('/^@extends.*$|^@section.*$|^@endsection.*$|^@vite.*$/m','',file_get_contents(resource_path('views/western-scholarship/answer-sheets.blade.php')));
    $dom=new DOMDocument();@$dom->loadHTML(Blade::render($template,$view));$xpath=new DOMXPath($dom);
    expect($xpath->query('//*[@data-exam-reseating-reset]')->length)->toBe($allowed?1:0)
        ->and($xpath->query('//*[@data-exam-reseating-reset][@disabled]')->length)->toBe($allowed&&$published?1:0);
})->with([[true,false],[false,false],[true,true]]);

it('registers a separate Seat Plan permission without granting it and protects both routes from unauthenticated requests',function(){
    $migration=require database_path('migrations/2026_10_23_000004_add_scholarship_reseating_reset_permission.php');$migration->up();$migration->up();
    $permission=Permission::where('code','western-scholarship.reset-for-reseating')->sole();
    expect(ScholarshipPermissions::tabGroups()['seat-plan']['actions'])->toContain('reset-for-reseating')
        ->and(PermissionHierarchy::normalizeIds([$permission->id],Permission::all()))->toBe([]);
    foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table)expect(DB::table($table)->where('permission_id',$permission->id)->exists())->toBeFalse();
    $this->getJson(route('western-scholarship.seats.reset-preview',1))->assertUnauthorized();
    $this->postJson(route('western-scholarship.seats.reset',1))->assertUnauthorized();
});
