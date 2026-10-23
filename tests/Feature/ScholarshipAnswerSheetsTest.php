<?php
use App\Models\{ScholarshipPaper,ScholarshipAnswerKey,ScholarshipAnswerScan,ScholarshipAnswerSheet,ScholarshipScore,ScholarshipEvent};
use App\Services\{ScholarshipAnswerSheets,ScholarshipWorkflow};
use App\Support\{ScholarshipAnswerSheetLayout,ScholarshipPermissions,PermissionHierarchy};
use App\Http\Controllers\{ScholarshipAnswerSheetController,ScholarshipController};
use Illuminate\Support\Facades\{Storage,DB,Blade};
use Illuminate\Http\{Request,UploadedFile};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
require_once __DIR__.'/../Fixtures/ScholarshipAnswerSheets.php';
beforeEach(function(){createScholarshipSchema();(require database_path('migrations/2026_10_22_000001_create_scholarship_answer_sheet_workflow.php'))->up();(require database_path('migrations/2026_10_22_000002_add_scholarship_question_scores.php'))->up();Storage::fake('local');});

function answerRequest($user,array $data=[]): Request
{
    Illuminate\Support\Facades\Auth::setUser($user);
    $request=Request::create('/students/western-scholarship','GET',$data+['academic_year_id'=>1,'tab'=>'answer-sheets']);
    $request->setUserResolver(fn()=>$user);$request->setLaravelSession(app('session')->driver());app()->instance('request',$request);return $request;
}

it('explains every condition that disables the student scanner without blocking a ready paper',function($condition,$expected){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();
    if(in_array($condition,['seats','all'],true))$exam->update(['seats_locked_at'=>null]);
    if(in_array($condition,['published','all'],true))$exam->update(['published_at'=>now()]);
    if(in_array($condition,['historical','all'],true))$exam->update(['historical_imported_at'=>now()]);
    if(in_array($condition,['key','all'],true))$paper->keys()->delete();
    $view=app(ScholarshipAnswerSheetController::class)->index(answerRequest($user,['paper_id'=>$paper->id]),ScholarshipController::TABS)->getData();
    expect(array_keys($view['scanBlockers']))->toBe($expected);
    $template=preg_replace('/^@extends.*$|^@section.*$|^@endsection.*$|^@vite.*$/m','',file_get_contents(resource_path('views/western-scholarship/answer-sheets.blade.php')));
    $html=Blade::render($template,$view);$dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
    expect($xpath->query('//*[@id="answerScanFile"][@disabled]')->length)->toBe($expected?1:0)
        ->and($xpath->query('//*[@data-answer-camera][@disabled]')->length)->toBe($expected?1:0)
        ->and($xpath->query('//*[@data-answer-scan-blocker]')->length)->toBe(count($expected));
    foreach($view['scanBlockers'] as $message)expect($html)->toContain(e($message));
    if(!$expected)expect($html)->not->toContain('Uploads are currently unavailable');
})->with([
    ['ready',[]],['seats',['seats']],['key',['key']],['published',['published']],['historical',['historical']],
    ['all',['historical','published','seats','key']],
]);

it('returns identified student and pending verification status for each queued upload without exposing scores or keys',function(){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();
    $request=answerRequest($user,['paper_id'=>$paper->id]);
    foreach(['image','original'] as $field)$request->files->set($field,UploadedFile::fake()->createWithContent('sheet.png',answerSheetPixels($sheet,array_fill(1,20,['A']))));
    $result=app(ScholarshipAnswerSheetController::class)->scan($request,$exam)->getData(true);
    expect($result['student_name'])->toBe($sheet->candidate->snapshot['name_en'])->and($result['candidate_code'])->toBe($sheet->candidate->candidate_code)
        ->and($result['status'])->toBe('pending')->and($result['flagged_count'])->toBe(0)->and($result)->not->toHaveKeys(['score','answers','detected','confidence']);
    expect(ScholarshipAnswerScan::findOrFail($result['id'])->status)->toBe('pending')->and(ScholarshipScore::count())->toBe(0);
});

it('supports letter and numeric choices with distinct papers per grade and version',function($style,$expected){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture($style);
    expect($paper->answer_choices)->toBe($expected)->and($paper->latestKey->answers)->toBe(array_fill(0,20,$expected[0]));
    expect($sheet->snapshot['choices'])->toBe($expected)->and($sheet->snapshot['grade'])->toBe(1);
    $other=$service->savePaper($user,$exam,['subject_id'=>$paper->subject_id,'grade'=>2,'paper_version'=>'A','question_count'=>25,'choice_style'=>$style,'choice_count'=>4,'points'=>4,'version'=>0]);
    expect($other->id)->not->toBe($paper->id)->and($other->answer_choices)->toHaveCount(4);
})->with([['letters',['A','B','C','D','E']],['numbers',['1','2','3','4','5']]]);

it('rejects invalid totals grades choices and stale paper or key updates',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();
    $config=['subject_id'=>$paper->subject_id,'grade'=>1,'paper_version'=>'B','question_count'=>20,'choice_style'=>'letters','choice_count'=>5,'points'=>5,'version'=>0];
    foreach([['points'=>0],['grade'=>12],['choice_count'=>27],['paper_version'=>'bad script'],['version'=>2]] as $change)
        expect(fn()=>$service->savePaper($user,$exam,array_replace($config,$change)))->toThrow(ValidationException::class);
    expect(fn()=>$service->saveKey($user,$paper,array_fill(0,20,'F'),$paper->version))->toThrow(ValidationException::class);
    expect(fn()=>$service->saveKey($user,$paper,array_fill(0,20,'A'),1))->toThrow(ValidationException::class);
    expect(fn()=>$service->savePaper($user,$exam,array_replace($config,['paper_version'=>'A','version'=>$paper->version])))->toThrow(ValidationException::class);
});

it('rejects duplicate create requests without overwriting existing paper settings or approved keys',function($linked){
    [$exam,$user,$original,$sheet,$service]=answerSheetFixture();
    $paper=$linked?$original:unusedAnswerPaper($exam,$user,$original,$service);
    if(!$linked){$service->saveKey($user,$paper,array_fill(0,20,'B'),$paper->version);$service->approveKey($user,$paper->fresh(),$paper->fresh()->version);$paper=$paper->fresh();}
    $before=$paper->toArray();$keys=$paper->keys()->get()->toArray();$count=ScholarshipPaper::count();
    foreach([0,$paper->version] as $version){
        $payload=['subject_id'=>$paper->subject_id,'grade'=>$paper->grade_number,'paper_version'=>$paper->paper_version,
            'question_count'=>25,'choice_style'=>'numbers','choice_count'=>6,'points'=>4,'version'=>$version];
        expect(fn()=>app(ScholarshipAnswerSheetController::class)->paper(answerRequest($user,$payload),$exam))->toThrow(ValidationException::class,'already exists');
        expect(ScholarshipPaper::count())->toBe($count)->and($paper->fresh()->toArray())->toBe($before)->and($paper->keys()->get()->toArray())->toBe($keys);
    }
})->with([true,false]);

it('requires a complete saved key and freezes approved revisions',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();$original=$paper->latestKey;
    $answers=array_fill(0,20,'B');foreach([0,6,19] as $i)$answers[$i]=null;
    $service->saveKey($user,$paper,$answers,$paper->version);
    expect(fn()=>$service->approveKey($user,$paper->fresh(),$paper->fresh()->version))->toThrow(ValidationException::class,'Blank answers: Q1, Q7, Q20.');
    $service->saveKey($user,$paper->fresh(),array_fill(0,20,'B'),$paper->fresh()->version);
    $next=$service->approveKey($user,$paper->fresh(),$paper->fresh()->version);
    expect($next->revision)->toBe(2)->and($original->fresh()->answers)->toBe(array_fill(0,20,'A'));
});

it('issues idempotent personalized sheets and rejects wrong grades campuses and unconfirmed seats',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();
    expect($service->issue($user,$paper,[$sheet->candidate_id])->first()->token)->toBe($sheet->token)->and(ScholarshipAnswerSheet::count())->toBe(1);
    $foreign=$exam->candidates()->where('grade_number',2)->first();
    expect(fn()=>$service->issue($user,$paper,[$foreign->id]))->toThrow(ValidationException::class);
    $sheet->candidate->update(['campus_id'=>2]);
    expect(fn()=>$service->issue(answerSheetUser(false),$paper,[$sheet->candidate_id]))->toThrow(HttpException::class);
    $exam->update(['seats_locked_at'=>null]);expect(fn()=>$service->issue($user,$paper,[$sheet->candidate_id]))->toThrow(ValidationException::class);
});

it('prints no answer keys and preserves A4 geometry and sheet identification',function(){
    [$exam,$user,$paper,$sheet]=answerSheetFixture('numbers',100);
    $svg=app(ScholarshipAnswerSheetLayout::class)->svg($sheet);
    expect($svg)->toContain('width="210mm"','height="297mm"',$sheet->token,'>100<','SCHOLARSHIP EXAMINATION','Invigilators','Full Name:','Seat No.:','Answer')->not->toContain('Correct answer','draft_answers');
    $html=view('western-scholarship.answer-sheet-report',['pages'=>[$svg,$svg]])->render();
    expect(substr_count($html,'class="answer-print-page"'))->toBe(2)->and($html)->toContain('size:A4 portrait;margin:0','Actual size / 100%');
});

it('reads real marked pixels identifies the sheet and flags multiple and blank answers',function($style,$correct){
    [$exam,$user,$paper,$sheet]=answerSheetFixture($style);$choices=$paper->answer_choices;
    $result=app(ScholarshipAnswerSheetLayout::class)->read(answerSheetPixels($sheet,[1=>[$correct],2=>[$choices[0],$choices[1]]]),['question_count'=>20,'choices'=>$choices,'layout_version'=>$sheet->snapshot['layout_version']??1]);
    expect($result['token'])->toBe($sheet->token)->and($result['responses'][0])->toBe($correct)->and($result['responses'][1])->toBeNull()
        ->and($result['flags'][2])->toBe('multiple')->and($result['flags'][3])->toBe('blank');
})->with([['letters','A'],['numbers','1']]);

it('rejects missing corner markers and the wrong paper identifier',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();
    $image=imagecreatefromstring(answerSheetPixels($sheet));imagefilledrectangle($image,20,20,50,50,imagecolorallocate($image,255,255,255));ob_start();imagepng($image);$broken=ob_get_clean();imagedestroy($image);
    expect(fn()=>app(ScholarshipAnswerSheetLayout::class)->read($broken))->toThrow(ValidationException::class);
    $wrong=$service->savePaper($user,$exam,['subject_id'=>$paper->subject_id,'grade'=>1,'paper_version'=>'B','question_count'=>20,'choice_style'=>'letters','choice_count'=>5,'points'=>5,'version'=>0]);
    expect(fn()=>answerSheetUpload($user,$exam,$wrong,$sheet))->toThrow(ValidationException::class);
    expect(ScholarshipAnswerScan::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('keeps uploads private pending verification and rejects exact duplicate uploads',function(){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();$scan=answerSheetUpload($user,$exam,$paper,$sheet,[1=>['A']]);
    expect($scan->status)->toBe('pending')->and(ScholarshipScore::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toHaveCount(2);
    expect(fn()=>answerSheetUpload($user,$exam,$paper,$sheet,[1=>['A']]))->toThrow(ValidationException::class);
    expect(ScholarshipAnswerScan::count())->toBe(1);
});

it('uploads light pencil student answers with scan distortion and saves the score only after verification',function($style){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture($style,100);
    $correct=array_map(fn($i)=>$paper->answer_choices[$i%5],range(0,99));
    $service->saveKey($user,$paper,$correct,$paper->version);$service->approveKey($user,$paper->fresh(),$paper->fresh()->version);$paper=$paper->fresh();
    $image=imagecreatefromstring(answerSheetPixels($sheet));$rows=app(ScholarshipAnswerSheetLayout::class)->questions(100,$paper->answer_choices,2);
    $fill=function($dot,$shade)use($image){imagefilledellipse($image,$dot['x'],$dot['y'],(int)round($dot['radius']*2-1),(int)round($dot['radius']*2-1),imagecolorallocate($image,$shade,$shade,$shade));};
    foreach($rows as $i=>$row){
        if(in_array($i,[7,11],true))continue;
        $fill($row['dots'][$i%5],$i===4?244:($i===3?220:208+($i%4)*4));
    }
    foreach(array_slice($rows[7]['dots'],0,2) as $dot)$fill($dot,220);
    $dot=$rows[11]['dots'][0];$radius=(int)ceil($dot['radius']);imagefilledrectangle($image,$dot['x'],$dot['y']-$radius,$dot['x']+$radius,$dot['y']-1,imagecolorallocate($image,215,215,215));
    // Simulate the slight answer-grid shift and uneven scan lighting that
    // affected the real teacher scan, without changing the page identity.
    $grid=imagecreatetruecolor(900,820);imagecopy($grid,$image,0,0,50,430,900,820);
    imagefilledrectangle($image,50,430,949,1249,imagecolorallocate($image,255,255,255));imagecopy($image,$grid,52,431,0,0,900,820);imagedestroy($grid);
    for($y=430;$y<1250;$y++)for($x=65;$x<940;$x++){
        $value=imagecolorat($image,$x,$y)&255;$shade=(int)round($value*(.88+.1*($x-65)/875));imagesetpixel($image,$x,$y,($shade<<16)|($shade<<8)|$shade);
    }
    ob_start();imagejpeg($image,null,90);$pixels=ob_get_clean();imagedestroy($image);
    $request=answerRequest($user,['paper_id'=>$paper->id]);
    foreach(['image','original'] as $field)$request->files->set($field,UploadedFile::fake()->createWithContent('student-light-pencil.jpg',$pixels));
    $controller=app(ScholarshipAnswerSheetController::class);$result=$controller->scan($request,$exam)->getData(true);
    $scan=ScholarshipAnswerScan::findOrFail($result['id']);$expected=$correct;foreach([4,7,11] as $i)$expected[$i]=null;
    expect($scan->detected)->toBe($expected)->and($scan->detected[3])->toBe($paper->answer_choices[3])
        ->and($scan->flags)->toBe([5=>'blank',8=>'multiple',12=>'unclear'])->and($scan->status)->toBe('pending')->and(ScholarshipScore::count())->toBe(0);
    $details=$controller->details(answerRequest($user),$scan)->getData(true);
    expect($details['responses'])->toBe($expected)->and($details['flags'])->toBe($scan->flags)->and($details['existing_score'])->toBeNull();
    $service->verify($user,$scan,$scan->detected,$scan->candidate_version,false,'Checked light pencil against original');
    expect($scan->fresh()->status)->toBe('accepted')->and($scan->fresh()->score)->toBe(97.0)
        ->and($sheet->candidate->scores()->where('subject_id',$paper->subject_id)->first()->score)->toBe(97.0);
})->with(['letters','numbers']);

it('saves server-calculated draft scores and provenance using existing review safeguards',function($style,$correct){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture($style);$scan=answerSheetUpload($user,$exam,$paper,$sheet,[1=>[$correct],2=>[$correct]]);
    $service->verify($user,$scan,$scan->detected,$scan->candidate_version,false,'Checked original');
    $score=$sheet->candidate->scores()->where('subject_id',$paper->subject_id)->first();
    expect($score->score)->toBe(10.0)->and($score->entry_method)->toBe('scan')->and($score->source_reference)->toBe((string)$scan->id)
        ->and($score->source_paper_id)->toBe($paper->id)->and($scan->fresh()->status)->toBe('accepted')->and($sheet->candidate->fresh()->reviewed_at)->toBeNull();
    expect(fn()=>$service->verify($user,$scan,$scan->detected,$scan->candidate_version,false,''))->toThrow(ValidationException::class);
})->with([['letters','A'],['numbers','1']]);

it('requires explicit replacement permission confirmation and reason',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();$sheet->candidate->scores()->create(['subject_id'=>$paper->subject_id,'score'=>80]);
    $scan=answerSheetUpload($user,$exam,$paper,$sheet,[1=>['A']]);
    expect(fn()=>$service->verify($user,$scan,$scan->detected,$scan->candidate_version,false,''))->toThrow(ValidationException::class);
    $restricted=answerSheetUser(true,array_diff(array_keys(ScholarshipPermissions::catalog()),['answer-replace']));
    expect(fn()=>$service->verify($restricted,$scan,$scan->detected,$scan->candidate_version,true,'Correct scan'))->toThrow(HttpException::class);
    $service->verify($user,$scan,$scan->detected,$scan->candidate_version,true,'Replace manual score after checking');
    expect($scan->fresh()->score)->toBe(5.0)->and($sheet->candidate->scores()->first()->score)->toBe(5.0);
});

it('does not convert missing sheets into absences and rejects scans for marked absences',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();$candidate=$sheet->candidate;
    expect($candidate->fresh()->absent)->toBeFalse()->and($candidate->scores()->count())->toBe(0);
    $candidate->scores()->create(['subject_id'=>$paper->subject_id,'score'=>0,'is_absent'=>true]);$scan=answerSheetUpload($user,$exam,$paper,$sheet);
    expect(fn()=>$service->verify($user,$scan,$scan->detected,$scan->candidate_version,true,'Attendance checked'))->toThrow(ValidationException::class);
    expect($candidate->scores()->first()->is_absent)->toBeTrue();
});

it('rejects stale scans and revised keys until an explicit recheck then invalidates review and awards',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();$scan=answerSheetUpload($user,$exam,$paper,$sheet,[1=>['A']]);
    $service->verify($user,$scan,$scan->detected,$scan->candidate_version,false,'Checked original');
    $sheet->candidate->update(['reviewed_by'=>2,'reviewed_at'=>now()]);
    $exam->awards()->create(['grade_number'=>1,'slot'=>'overall_1','candidate_id'=>$sheet->candidate_id,'award_percent'=>100]);
    $service->saveKey($user,$paper,array_fill(0,20,'B'),$paper->version);$service->approveKey($user,$paper->fresh(),$paper->fresh()->version);
    expect($sheet->candidate->fresh()->reviewed_at)->toBeNull()->and($exam->awards()->count())->toBe(0);
    expect(fn()=>ScholarshipAnswerSheets::assertCurrentScanKeys($sheet->candidate))->toThrow(ValidationException::class);
    $new=$service->recheck($user,$scan,'Corrected key revision');
    expect($new->status)->toBe('pending')->and($new->source_scan_id)->toBe($scan->id);
    $service->verify($user,$new,$new->detected,$new->candidate_version,true,'Rechecked against key revision 2');
    ScholarshipAnswerSheets::assertCurrentScanKeys($sheet->candidate);
    expect($new->fresh()->score)->toBe(0.0)->and($scan->fresh()->score)->toBe(5.0);
});

it('rejects changed candidate versions changed seats and published or historical exams',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();$scan=answerSheetUpload($user,$exam,$paper,$sheet);
    $sheet->candidate->increment('version');
    expect(fn()=>$service->verify($user,$scan,$scan->detected,$scan->candidate_version,false,''))->toThrow(ValidationException::class);
    $sheet->candidate->update(['room_seat'=>99]);expect($service->current($sheet->fresh()))->toBeFalse();
    expect(fn()=>$service->recheck($user,$scan,'Seat changed'))->toThrow(ValidationException::class);
    $exam->update(['published_at'=>now()]);expect(fn()=>$service->approveKey($user,$paper,$paper->version))->toThrow(ValidationException::class);
});

it('registers answer-sheet actions and hides keys without permission while protecting Central score access',function(){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();$campus=answerSheetUser(false);
    foreach(['answer-paper','answer-key','answer-key-approve'] as $action)expect(ScholarshipPermissions::allows($campus,$action))->toBeTrue();
    foreach(['answer-verify','answer-replace','answer-delete'] as $action)expect(ScholarshipPermissions::allows($campus,$action))->toBeFalse();
    $campus=answerSheetUser(false,array_diff(array_keys(ScholarshipPermissions::catalog()),['answer-paper','answer-key','answer-key-approve']));
    expect(ScholarshipPermissions::tabGroups()['answer-sheets']['actions'])->toHaveCount(14);
    expect(DB::table('access_permissions')->where('code','western-scholarship.answer-scan')->exists())->toBeTrue();
    $scan=answerSheetUpload($user,$exam,$paper,$sheet);
    expect(fn()=>app(ScholarshipAnswerSheetController::class)->details(answerRequest($campus),$scan))->toThrow(HttpException::class);
    expect(fn()=>app(ScholarshipAnswerSheetController::class)->image(answerRequest($campus),$scan))->toThrow(HttpException::class);
    $data=app(ScholarshipAnswerSheetController::class)->index(answerRequest($campus),ScholarshipController::TABS)->getData();
    expect($data['data']['papers'][0]['answers'])->toBeNull()->and($data['data']['papers'][0]['approved'])->toBeNull();
});

it('allows permitted Central and Campus staff to create and revise shared papers and keys across campuses',function($central){
    [$exam,$admin,$original,$oldSheet,$service]=answerSheetFixture();
    $staff=answerSheetUser($central,['tab-answer-sheets','answer-paper','answer-key','answer-key-approve']);
    $paper=$service->savePaper($staff,$exam,['subject_id'=>$exam->subjects()->where('name','English')->first()->id,
        'grade'=>2,'paper_version'=>'A','question_count'=>10,'choice_style'=>'letters','choice_count'=>5,'points'=>10,'version'=>0]);
    $service->saveKey($staff,$paper,array_fill(0,10,'B'),$paper->version);
    $approved=$service->approveKey($staff,$paper->fresh(),$paper->fresh()->version);
    expect($approved->revision)->toBe(1);
    // This shared Grade 2 paper is issued outside the Campus user's campus.
    $foreign=$exam->candidates()->where('campus_id',2)->where('grade_number',2)->first();
    $sheet=$service->issue($admin,$paper->fresh(),[$foreign->id])->first();
    $service->saveKey($staff,$paper->fresh(),array_fill(0,10,'C'),$paper->fresh()->version);
    $revision=$service->approveKey($staff,$paper->fresh(),$paper->fresh()->version);
    expect($revision->revision)->toBe(2)->and($approved->fresh()->answers)->toBe(array_fill(0,10,'B'))
        ->and($sheet->fresh()->token)->toBe($sheet->token)->and($service->current($sheet->fresh()))->toBeTrue();
    $view=app(ScholarshipAnswerSheetController::class)->index(answerRequest($staff,['paper_id'=>$paper->id]),ScholarshipController::TABS)->getData();
    expect($view['permissions']['answer-paper'])->toBeTrue()->and($view['permissions']['answer-key'])->toBeTrue()
        ->and($view['permissions']['answer-key-approve'])->toBeTrue()
        ->and($view['data']['papers']->firstWhere('id',$paper->id)['approved'])->toBe(array_fill(0,10,'C'));
    $exam->update(['published_at'=>now()]);
    expect(fn()=>$service->saveKey($staff,$paper->fresh(),array_fill(0,10,'D'),$paper->fresh()->version))->toThrow(ValidationException::class);
})->with([true,false]);

it('requires separate shared paper key-edit key-approval and tab permissions for either staff scope',function($central){
    [$exam,$admin,$paper,$sheet,$service]=answerSheetFixture();
    $noPaper=answerSheetUser($central,['tab-answer-sheets','answer-key','answer-key-approve']);
    expect(fn()=>$service->savePaper($noPaper,$exam,[]))->toThrow(HttpException::class);
    $noKey=answerSheetUser($central,['tab-answer-sheets','answer-paper','answer-key-approve']);
    expect(fn()=>$service->saveKey($noKey,$paper,array_fill(0,20,'B'),$paper->version))->toThrow(HttpException::class);
    $noApproval=answerSheetUser($central,['tab-answer-sheets','answer-paper','answer-key']);
    expect(fn()=>$service->approveKey($noApproval,$paper,$paper->version))->toThrow(HttpException::class);
    $noTab=answerSheetUser($central,['answer-paper','answer-key','answer-key-approve']);
    expect(fn()=>$service->saveKey($noTab,$paper,array_fill(0,20,'B'),$paper->version))->toThrow(HttpException::class);
    $view=app(ScholarshipAnswerSheetController::class)->index(answerRequest($noKey),ScholarshipController::TABS)->getData();
    expect($view['permissions']['answer-key'])->toBeFalse()->and($view['permissions']['answer-key-approve'])->toBeTrue();
})->with([true,false]);

it('renders the workflow and accepts unrelated tab pagination without validation errors',function(){
    [$exam,$user,$paper]=answerSheetFixture();
    $view=app(ScholarshipController::class)->index(answerRequest($user,['per_page'=>10,'paper_id'=>$paper->id]));
    expect($view->name())->toBe('western-scholarship.answer-sheets');
    $template=preg_replace('/^@extends.*$|^@section.*$|^@endsection.*$|^@vite.*$/m','',file_get_contents(resource_path('views/western-scholarship/answer-sheets.blade.php')));
    $html=Blade::render($template,$view->getData());
    expect($html)->toContain('Letters (A, B, C…)','Numbers (1, 2, 3…)','Scan &amp; Upload','Paper Setup','Answer Key','Verify &amp; Save Draft Score','Correct Answer','Default Question Score');
});

it('keeps other subjects scan provenance and requires score-entry committee duties',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();
    $other=$exam->subjects()->where('id','!=',$paper->subject_id)->first();
    $sheet->candidate->scores()->create(['subject_id'=>$other->id,'score'=>72,'entry_method'=>'historical','source_reference'=>'import-history']);
    $scan=answerSheetUpload($user,$exam,$paper,$sheet,[1=>['A']]);
    $exam->committee()->where('role','score-entry')->delete();
    expect(fn()=>$service->verify($user,$scan,$scan->detected,$scan->candidate_version,false,''))->toThrow(HttpException::class);
    expect($scan->fresh()->status)->toBe('pending')->and($sheet->candidate->scores()->where('subject_id',$paper->subject_id)->exists())->toBeFalse();
    $exam->committee()->create(['user_id'=>2,'role'=>'score-entry']);
    $service->verify($user,$scan,$scan->detected,$scan->candidate_version,false,'Checked original');
    $preserved=$sheet->candidate->scores()->where('subject_id',$other->id)->first();
    expect($preserved->score)->toBe(72.0)->and($preserved->entry_method)->toBe('historical')->and($preserved->source_reference)->toBe('import-history');
});

it('requires correction reasons and rejects responses outside the configured labels',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture('numbers');$scan=answerSheetUpload($user,$exam,$paper,$sheet,[1=>['1']]);
    $corrected=$scan->detected;$corrected[0]='A';
    expect(fn()=>$service->verify($user,$scan,$corrected,$scan->candidate_version,false,'Checked original'))->toThrow(ValidationException::class);
    $corrected[0]='2';
    expect(fn()=>$service->verify($user,$scan,$corrected,$scan->candidate_version,false,''))->toThrow(ValidationException::class);
    $service->verify($user,$scan,$corrected,$scan->candidate_version,false,'Corrected a faint erased answer');
    expect($scan->fresh()->score)->toBe(0.0)->and($scan->fresh()->responses[0])->toBe('2');
});

it('protects upload verification and print HTTP routes from unauthenticated requests',function(){
    $this->postJson(route('western-scholarship.answer-sheets.scan',1))->assertUnauthorized();
    $this->postJson(route('western-scholarship.answer-sheets.verify',1))->assertUnauthorized();
    $this->getJson(route('western-scholarship.answer-sheets.print',str_repeat('a',32)))->assertUnauthorized();
});

it('prints selected rooms or students and rejects forged room and student scopes',function(){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();$controller=app(ScholarshipAnswerSheetController::class);$center=$sheet->candidate->exam_center_id;
    $result=$controller->issue(answerRequest($user,['scope'=>'rooms','center'=>$center,'rooms'=>[1]]),$paper)->getData(true);
    expect($result['count'])->toBe(2)->and($result['url'])->toContain('/answer-sheets/print/');
    $result=$controller->issue(answerRequest($user,['scope'=>'students','candidates'=>[$sheet->candidate_id]]),$paper)->getData(true);
    expect($result['count'])->toBe(1);
    expect(fn()=>$controller->issue(answerRequest($user,['scope'=>'rooms','center'=>$center,'rooms'=>[999]]),$paper))->toThrow(HttpException::class);
    expect(fn()=>$controller->issue(answerRequest($user,['scope'=>'students','candidates'=>[999999]]),$paper))->toThrow(HttpException::class);
    $request=answerRequest($user);expect(fn()=>$controller->print($request,str_repeat('a',32)))->toThrow(HttpException::class);
});

it('links a grade without printing and reuses its student sheets on repeat links and later printing',function(){
    [$exam,$user,$original,$originalSheet,$service]=answerSheetFixture();
    $exam->candidates()->where('campus_id',2)->first()->update(['grade_number'=>1]);
    $paper=$service->savePaper($user,$exam,['subject_id'=>$exam->subjects()->where('name','English')->first()->id,
        'grade'=>1,'paper_version'=>'A','question_count'=>20,'choice_style'=>'letters','choice_count'=>5,'points'=>5,'version'=>0]);
    $service->saveKey($user,$paper,array_fill(0,20,'A'),$paper->version);$service->approveKey($user,$paper->fresh(),$paper->fresh()->version);
    $linkUser=answerSheetUser(true,['tab-answer-sheets','answer-link']);$request=answerRequest($linkUser,['version'=>$paper->fresh()->version]);
    $controller=app(ScholarshipAnswerSheetController::class);$result=$controller->link($request,$paper->fresh())->getData(true);
    expect($result['count'])->toBe(3)->and($result['created'])->toBe(3)->and($result)->not->toHaveKey('url')
        ->and(collect(array_keys($request->session()->all()))->contains(fn($key)=>str_starts_with($key,'answer-sheet-print.')))->toBeFalse();
    $sheets=$paper->sheets()->get();expect($sheets->pluck('candidate.grade_number')->unique()->all())->toBe([1])
        ->and($sheets->pluck('candidate.campus_id')->unique()->sort()->values()->all())->toBe([1,2]);
    $tokens=$sheets->pluck('token','candidate_id')->all();
    $again=$controller->link(answerRequest($linkUser,['version'=>$paper->fresh()->version]),$paper->fresh())->getData(true);
    expect($again['count'])->toBe(3)->and($again['created'])->toBe(0)->and($paper->sheets()->count())->toBe(3);
    $printUser=answerSheetUser();$printed=$service->issue($printUser,$paper->fresh(),$sheets->pluck('candidate_id')->all());
    expect($printed->pluck('token','candidate_id')->all())->toBe($tokens)->and($paper->sheets()->count())->toBe(3)
        ->and($originalSheet->fresh()->token)->toBe($originalSheet->token)
        ->and(ScholarshipEvent::where('action','answer-students-linked')->where('details->paper_id',$paper->id)->count())->toBe(2);
});

it('keeps 27 students linked once across layout upgrades and counts existing sheet versions as the same students',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture('letters',20,27);
    $linked=$service->linkStudents($user,$paper,$paper->version);
    foreach($linked as $issued){
        $snapshot=$service->snapshot($paper,$issued->candidate,1);
        $issued->update(['snapshot'=>$snapshot,'fingerprint'=>hash('sha256',json_encode($snapshot))]);
    }
    $tokens=$paper->sheets()->pluck('token')->all();$controller=app(ScholarshipAnswerSheetController::class);
    $again=$controller->link(answerRequest($user,['version'=>$paper->version]),$paper)->getData(true);
    expect($again['count'])->toBe(27)->and($again['created'])->toBe(0)->and($paper->sheets()->count())->toBe(27)
        ->and($paper->sheets()->pluck('token')->all())->toBe($tokens);
    $oldScan=answerSheetUpload($user,$exam,$paper,$linked->first(),[1=>['A']]);
    $printed=$service->issue($user,$paper,$linked->pluck('candidate_id')->all());
    $newScan=answerSheetUpload($user,$exam,$paper,$printed->first(),[1=>['B']]);
    expect($paper->sheets()->count())->toBe(54)->and($service->current($linked->first()))->toBeTrue()->and($service->current($printed->first()))->toBeTrue();
    $again=$controller->link(answerRequest($user,['version'=>$paper->version]),$paper)->getData(true);
    $view=$controller->index(answerRequest($user),ScholarshipController::TABS)->getData();
    expect($again['count'])->toBe(27)->and($again['created'])->toBe(0)->and($paper->sheets()->count())->toBe(54)
        ->and($view['data']['papers']->firstWhere('id',$paper->id)['linked_sheets'])->toBe(27)
        ->and($view['papers']->firstWhere('id',$paper->id)->linked_students_count)->toBe(27)
        ->and($oldScan->fresh()->detected[0])->toBe('A')->and($newScan->fresh()->detected[0])->toBe('B');
    $template=preg_replace('/^@extends.*$|^@section.*$|^@endsection.*$|^@vite.*$/m','',file_get_contents(resource_path('views/western-scholarship/answer-sheets.blade.php')));
    $html=Blade::render($template,$view);expect($html)->toContain('27 student(s)')->not->toContain('54 student(s)');
    $candidate=$linked->last()->candidate->replicate();$candidate->candidate_code='G01-028';$candidate->room_seat=28;$candidate->grade_exam_number=28;
    $candidate->snapshot=array_replace($candidate->snapshot,['student_code'=>'NEW028','name_en'=>'NEW STUDENT 28']);$candidate->save();
    $added=$controller->link(answerRequest($user,['version'=>$paper->version]),$paper)->getData(true);
    expect($added['count'])->toBe(28)->and($added['created'])->toBe(1)->and($paper->sheets()->count())->toBe(55)
        ->and($paper->sheets()->where('candidate_id',$candidate->id)->count())->toBe(1)->and(ScholarshipAnswerScan::count())->toBe(2);
    $again=$controller->link(answerRequest($user,['version'=>$paper->version]),$paper)->getData(true);
    expect($again['count'])->toBe(28)->and($again['created'])->toBe(0)->and($paper->sheets()->count())->toBe(55);
});

it('does not create new student links when exam or candidate snapshot details change',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();
    $service->linkStudents($user,$paper,$paper->version);$tokens=$paper->sheets()->pluck('token')->all();
    $exam->update(['exam_date'=>'2026-11-21']);
    $candidate=$sheet->candidate;$candidate->snapshot=array_replace($candidate->snapshot,['name_en'=>'CORRECTED NAME']);$candidate->save();
    $linked=$service->linkStudents($user,$paper->fresh(),$paper->version);
    expect($linked)->toHaveCount(2)->and($linked->filter(fn($row)=>$row->wasRecentlyCreated))->toHaveCount(0)
        ->and($paper->sheets()->count())->toBe(2)->and($paper->sheets()->pluck('token')->all())->toBe($tokens);
});

it('counts students when unlinking multiple unscanned print versions and retains every removed token in the audit',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();
    $snapshot=$service->snapshot($paper,$sheet->candidate,1);$sheet->update(['snapshot'=>$snapshot,'fingerprint'=>hash('sha256',json_encode($snapshot))]);
    $service->issue($user,$paper,$exam->candidates()->where('grade_number',1)->pluck('id')->all());
    expect($paper->sheets()->count())->toBe(3);
    $result=$service->unlinkStudents($user,$paper,$paper->version);
    expect($result)->toBe(['count'=>2,'remaining'=>0])->and($paper->sheets()->count())->toBe(0)
        ->and(ScholarshipEvent::where('action','answer-students-unlinked')->first()->details['sheets'])->toHaveCount(3);
});

it('requires the separate link permission and links campus users only to permitted grade students',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();
    $exam->candidates()->where('campus_id',2)->first()->update(['grade_number'=>1]);
    $printOnly=answerSheetUser(true,['tab-answer-sheets','answer-print']);
    expect(fn()=>$service->linkStudents($printOnly,$paper,$paper->version))->toThrow(HttpException::class);
    $campus=answerSheetUser(false,['tab-answer-sheets','answer-link']);
    $linked=$service->linkStudents($campus,$paper,$paper->version);
    expect($linked)->toHaveCount(2)->and($linked->pluck('candidate.campus_id')->unique()->all())->toBe([1])
        ->and($paper->sheets()->whereHas('candidate',fn($q)=>$q->where('campus_id',2))->exists())->toBeFalse();
});

it('blocks student linking for stale drafts unapproved keys unconfirmed seats and locked exams',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();
    expect(fn()=>$service->linkStudents($user,$paper,$paper->version-1))->toThrow(ValidationException::class);
    $unapproved=unusedAnswerPaper($exam,$user,$paper,$service);
    expect(fn()=>$service->linkStudents($user,$unapproved,$unapproved->version))->toThrow(ValidationException::class);
    $exam->update(['seats_locked_at'=>null]);expect(fn()=>$service->linkStudents($user,$paper->fresh(),$paper->version))->toThrow(ValidationException::class);
    $exam->update(['seats_locked_at'=>now()]);
    $exam->candidates()->where('grade_number',1)->whereKeyNot($sheet->candidate_id)->update(['room_seat'=>null]);
    expect(fn()=>$service->linkStudents($user,$paper->fresh(),$paper->version))->toThrow(ValidationException::class);
    expect($paper->sheets()->count())->toBe(1);
    $exam->update(['published_at'=>now()]);expect(fn()=>$service->linkStudents($user,$paper->fresh(),$paper->version))->toThrow(ValidationException::class);
});

it('unlinks unscanned students so the same paper can grow from 20 to 22 questions and be printed again',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();$controller=app(ScholarshipAnswerSheetController::class);
    $print=$controller->issue(answerRequest($user,['scope'=>'all']),$paper)->getData(true);$oldBatch=basename(parse_url($print['url'],PHP_URL_PATH));
    $oldTokens=$paper->sheets()->pluck('token')->all();
    $result=$controller->unlink(answerRequest($user,['version'=>$paper->version]),$paper)->getData(true);
    expect($result['count'])->toBe(2)->and($result['remaining'])->toBe(0)->and($service->linked($paper->fresh()))->toBeFalse()
        ->and($paper->fresh()->latestKey->revision)->toBe(1);
    $event=ScholarshipEvent::where('action','answer-students-unlinked')->first();
    expect(collect($event->details['sheets'])->pluck('token')->all())->toBe($oldTokens);
    expect(fn()=>$controller->print(answerRequest($user),$oldBatch))->toThrow(HttpException::class);
    expect(fn()=>answerSheetUpload($user,$exam,$paper->fresh(),$sheet))->toThrow(ValidationException::class);
    $updated=$service->savePaper($user,$exam,['subject_id'=>$paper->subject_id,'grade'=>1,'paper_version'=>'A',
        'question_count'=>22,'choice_style'=>'letters','choice_count'=>5,'points'=>5,'version'=>$paper->fresh()->version],$paper->id);
    expect($updated->id)->toBe($paper->id)->and($updated->question_count)->toBe(22)->and($updated->keys()->count())->toBe(0);
    $service->saveKey($user,$updated,array_fill(0,22,'B'),$updated->version,[...array_fill(0,20,4),10,10]);
    $service->approveKey($user,$updated->fresh(),$updated->fresh()->version);
    $new=$service->linkStudents($user,$updated->fresh(),$updated->fresh()->version);
    expect($new)->toHaveCount(2)->and($new->pluck('snapshot.question_count')->unique()->all())->toBe([22])
        ->and(array_intersect($new->pluck('token')->all(),$oldTokens))->toBe([]);
    $reprint=$controller->issue(answerRequest($user,['scope'=>'all']),$updated->fresh())->getData(true);
    expect($reprint['count'])->toBe(2)->and($updated->sheets()->count())->toBe(2);
});

it('blocks unlinking after any scan or recorded subject score',function($activity){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();
    if($activity==='pending-scan')answerSheetUpload($user,$exam,$paper,$sheet);
    else $sheet->candidate->scores()->create(['subject_id'=>$paper->subject_id,'score'=>$activity==='absence'?null:0,
        'is_absent'=>$activity==='absence','source_paper_id'=>$activity==='paper-score'?$paper->id:null,'entry_method'=>'manual']);
    expect(fn()=>$service->unlinkStudents($user,$paper,$paper->version))->toThrow(ValidationException::class);
    expect($paper->sheets()->count())->toBe(1);
    $view=app(ScholarshipAnswerSheetController::class)->index(answerRequest($user),ScholarshipController::TABS)->getData();
    $template=preg_replace('/^@extends.*$|^@section.*$|^@endsection.*$|^@vite.*$/m','',file_get_contents(resource_path('views/western-scholarship/answer-sheets.blade.php')));
    $dom=new DOMDocument();@$dom->loadHTML(Blade::render($template,$view));$xpath=new DOMXPath($dom);
    expect($xpath->query('//*[@data-answer-paper-unlink="'.$paper->id.'"][@disabled]')->length)->toBe(1);
})->with(['pending-scan','paper-score','manual-score','absence']);

it('enforces unlink permissions and campus boundaries and keeps shared setup locked until every link is removed',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();
    $exam->candidates()->where('campus_id',2)->first()->update(['grade_number'=>1]);
    $service->linkStudents($user,$paper,$paper->version);
    $noUnlink=answerSheetUser(true,['tab-answer-sheets','answer-link','answer-paper']);
    expect(fn()=>$service->unlinkStudents($noUnlink,$paper,$paper->version))->toThrow(HttpException::class);
    $campus=answerSheetUser(false,['tab-answer-sheets','answer-unlink','answer-paper']);
    expect(fn()=>$service->unlinkStudents($campus,$paper,$paper->version-1))->toThrow(ValidationException::class);
    $result=$service->unlinkStudents($campus,$paper,$paper->version);
    expect($result)->toBe(['count'=>2,'remaining'=>1])->and($paper->sheets()->first()->candidate->campus_id)->toBe(2)
        ->and($service->linked($paper->fresh()))->toBeTrue();
    expect(fn()=>$service->savePaper($campus,$exam,['subject_id'=>$paper->subject_id,'grade'=>1,'paper_version'=>'A',
        'question_count'=>22,'choice_style'=>'letters','choice_count'=>5,'points'=>5,'version'=>$paper->fresh()->version],$paper->id))->toThrow(ValidationException::class);
    $user=answerSheetUser();$exam->update(['published_at'=>now()]);
    expect(fn()=>$service->unlinkStudents($user,$paper->fresh(),$paper->fresh()->version))->toThrow(ValidationException::class);
    expect($paper->sheets()->count())->toBe(1);
});

it('allows unlinking when scores are blank or belong to another paper or subject',function($source){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();
    $other=$source==='other-paper'?unusedAnswerPaper($exam,$user,$paper,$service):null;
    $sheet->candidate->scores()->create(['subject_id'=>$paper->subject_id,'score'=>$other?90:null,'source_paper_id'=>$other?->id,'is_absent'=>false]);
    $sheet->candidate->scores()->create(['subject_id'=>$exam->subjects()->whereKeyNot($paper->subject_id)->first()->id,'score'=>85]);
    expect($service->unlinkStudents($user,$paper,$paper->version))->toBe(['count'=>1,'remaining'=>0]);
    expect($sheet->candidate->scores()->count())->toBe(2);
})->with(['blank','other-paper']);

it('supports customized ranges through F Z and numeric 6 26 without overflowing A4',function($style,$count,$last){
    [$exam,$user,$original,$oldSheet,$service]=answerSheetFixture();
    $paper=$service->savePaper($user,$exam,['subject_id'=>$original->subject_id,'grade'=>1,'paper_version'=>'CUSTOM','question_count'=>100,
        'choice_style'=>$style,'choice_count'=>$count,'points'=>1,'version'=>0]);
    expect($paper->answer_choices)->toHaveCount($count)->and($paper->answer_choices[$count-1])->toBe($last);
    $service->saveKey($user,$paper,array_fill(0,100,$last),$paper->version);
    $service->approveKey($user,$paper->fresh(),$paper->fresh()->version);
    $sheet=$service->issue($user,$paper->fresh(),[$oldSheet->candidate_id])->first();
    $layout=app(ScholarshipAnswerSheetLayout::class);$rows=$layout->questions(100,$paper->answer_choices,$sheet->snapshot['layout_version']);
    foreach($rows as $row)foreach($row['dots'] as $dot){
        expect($dot['x']-$dot['radius'])->toBeGreaterThan(60)->and($dot['x']+$dot['radius'])->toBeLessThan(950)
            ->and($dot['y']+$dot['radius'])->toBeLessThan(1250);
    }
    $scan=answerSheetUpload($user,$exam,$paper,$sheet,[1=>[$last],100=>[$last]]);
    expect($scan->detected[0])->toBe($last)->and($scan->detected[99])->toBe($last);
    $service->verify($user,$scan,$scan->detected,$scan->candidate_version,false,'Checked wider choices');
    expect($scan->fresh()->score)->toBe(2.0);
})->with([['letters',6,'F'],['letters',26,'Z'],['numbers',6,'6'],['numbers',26,'26']]);

it('calculates mixed question scores using the immutable approved key',function(){
    [$exam,$user,$original,$oldSheet,$service]=answerSheetFixture();
    $paper=$service->savePaper($user,$exam,['subject_id'=>$original->subject_id,'grade'=>1,'paper_version'=>'MIXED','question_count'=>4,
        'choice_style'=>'letters','choice_count'=>6,'points'=>1,'version'=>0]);
    $service->saveKey($user,$paper,['F','A','B','C'],$paper->version,[1,2,3,94]);
    $key=$service->approveKey($user,$paper->fresh(),$paper->fresh()->version);
    expect(array_map('floatval',$key->question_points))->toBe([1.0,2.0,3.0,94.0]);
    $sheet=$service->issue($user,$paper->fresh(),[$oldSheet->candidate_id])->first();
    $scan=answerSheetUpload($user,$exam,$paper,$sheet,[1=>['F'],2=>['A'],3=>['B'],4=>['A']]);
    $service->verify($user,$scan,$scan->detected,$scan->candidate_version,false,'Checked mixed question scores');
    expect($scan->fresh()->score)->toBe(6.0);
    $service->saveKey($user,$paper->fresh(),['F','A','B','C'],$paper->fresh()->version,[2,3,4,91]);
    $revised=$service->approveKey($user,$paper->fresh(),$paper->fresh()->version);
    expect($revised->revision)->toBe(2)->and($key->fresh()->question_points)->toBe([1,2,3,94]);
    expect(fn()=>ScholarshipAnswerSheets::assertCurrentScanKeys($sheet->candidate))->toThrow(ValidationException::class);
    $retry=$service->recheck($user,$scan,'Question points corrected');
    $service->verify($user,$retry,$retry->detected,$retry->candidate_version,true,'Rechecked revised question points');
    expect($retry->fresh()->score)->toBe(9.0)->and($scan->fresh()->score)->toBe(6.0);
});

it('validates every question score and requires the approved total to match the subject maximum',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();$answers=array_fill(0,20,'A');
    foreach([array_fill(0,19,5),[-1,...array_fill(0,19,5)],['bad',...array_fill(0,19,5)],[101,...array_fill(0,19,5)]] as $points)
        expect(fn()=>$service->saveKey($user,$paper,$answers,$paper->version,$points))->toThrow(ValidationException::class);
    $service->saveKey($user,$paper,$answers,$paper->version,array_fill(0,20,1));
    expect(fn()=>$service->approveKey($user,$paper->fresh(),$paper->fresh()->version))->toThrow(ValidationException::class);
    expect($paper->keys()->count())->toBe(1);
});

it('keeps legacy uniform keys and previously issued sheets valid after the score upgrade',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();
    $paper->keys()->update(['question_points'=>null]);$paper->update(['draft_points'=>null]);
    expect($service->current($sheet))->toBeTrue();
    $scan=answerSheetUpload($user,$exam,$paper,$sheet,[1=>['A'],2=>['A']]);
    $service->verify($user,$scan,$scan->detected,$scan->candidate_version,false,'Checked legacy key');
    expect($scan->fresh()->score)->toBe(10.0);
});

it('keeps old printed layouts readable while issuing the new sample layout',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();
    $snapshot=$service->snapshot($paper,$sheet->candidate,1);
    $sheet->update(['snapshot'=>$snapshot,'fingerprint'=>hash('sha256',json_encode($snapshot))]);
    expect($service->current($sheet->fresh()))->toBeTrue();
    $oldSvg=app(ScholarshipAnswerSheetLayout::class)->svg($sheet->fresh());
    expect($oldSvg)->toContain('WIS SCHOLARSHIP — ANSWER SHEET')->not->toContain('SCHOLARSHIP EXAMINATION');
    $next=$service->issue($user,$paper,[$sheet->candidate_id])->first();
    expect($next->token)->not->toBe($sheet->token)->and($next->snapshot['layout_version'])->toBe(2)
        ->and($service->current($sheet->fresh()))->toBeTrue()->and($service->current($next))->toBeTrue();
    $old=answerSheetUpload($user,$exam,$paper,$sheet->fresh(),[1=>['A']]);
    $new=answerSheetUpload($user,$exam,$paper,$next,[1=>['B']]);
    expect($old->detected[0])->toBe('A')->and($new->detected[0])->toBe('B');
});

it('arranges 40 questions in the sample groups 1 to 16 17 to 32 and 33 to 40',function(){
    $rows=app(ScholarshipAnswerSheetLayout::class)->questions(40,['A','B','C','D','E'],2);
    expect($rows)->toHaveCount(40)->and($rows[0]['y'])->toBe($rows[16]['y'])->and($rows[0]['y'])->toBe($rows[32]['y'])
        ->and($rows[16]['x'])->toBeGreaterThan($rows[0]['x'])->and($rows[32]['x'])->toBeGreaterThan($rows[16]['x'])
        ->and($rows[8]['y']-$rows[7]['y'])->toBeGreaterThan($rows[7]['y']-$rows[6]['y']);
});

function unusedAnswerPaper($exam,$user,$original,$service): ScholarshipPaper
{
    return $service->savePaper($user,$exam,['subject_id'=>$original->subject_id,'grade'=>1,'paper_version'=>'UNUSED','question_count'=>20,
        'choice_style'=>'letters','choice_count'=>5,'points'=>5,'version'=>0]);
}

it('deletes an unlinked approved paper and retains its key in the audit history',function(){
    [$exam,$user,$original,$sheet,$service]=answerSheetFixture();$paper=unusedAnswerPaper($exam,$user,$original,$service);
    $service->saveKey($user,$paper,array_fill(0,20,'A'),$paper->version);$service->approveKey($user,$paper->fresh(),$paper->fresh()->version);
    $id=$paper->id;$key=$paper->keys()->first()->id;$service->deletePaper($user,$paper->fresh(),$paper->fresh()->version);
    expect(ScholarshipPaper::find($id))->toBeNull()->and(ScholarshipAnswerKey::find($key))->toBeNull()->and(ScholarshipPaper::find($original->id))->not->toBeNull();
    $event=ScholarshipEvent::where('action','answer-paper-deleted')->first();
    expect($event->details['paper']['id'])->toBe($id)->and($event->details['approved_keys'][0]['answers'])->toBe(array_fill(0,20,'A'));
});

it('blocks deletion of any student-linked paper even before scanning',function(){
    [$exam,$user,$paper,$sheet,$service]=answerSheetFixture();
    expect(ScholarshipAnswerScan::count())->toBe(0)->and($service->linked($paper))->toBeTrue();
    expect(fn()=>$service->deletePaper($user,$paper,$paper->version))->toThrow(ValidationException::class);
    expect(ScholarshipPaper::find($paper->id))->not->toBeNull()->and(ScholarshipAnswerSheet::find($sheet->id))->not->toBeNull();
});

it('protects papers referenced by legacy scores and denies stale or unauthorized deletion',function(){
    [$exam,$user,$original,$sheet,$service]=answerSheetFixture();$paper=unusedAnswerPaper($exam,$user,$original,$service);
    $withoutDelete=answerSheetUser(true,array_diff(array_keys(ScholarshipPermissions::catalog()),['answer-delete']));
    expect(fn()=>$service->deletePaper($withoutDelete,$paper,$paper->version))->toThrow(HttpException::class);
    expect(fn()=>$service->deletePaper(answerSheetUser(false),$paper,$paper->version))->toThrow(HttpException::class);
    expect(fn()=>$service->deletePaper($user,$paper,999))->toThrow(ValidationException::class);
    $sheet->candidate->scores()->create(['subject_id'=>$paper->subject_id,'score'=>10,'source_paper_id'=>$paper->id,'entry_method'=>'scan']);
    expect(fn()=>$service->deletePaper($user,$paper,$paper->version))->toThrow(ValidationException::class);
});

it('edits an unlinked approved paper in place and resets the key for reapproval',function(){
    [$exam,$user,$original,$sheet,$service]=answerSheetFixture();$paper=unusedAnswerPaper($exam,$user,$original,$service);
    $service->saveKey($user,$paper,array_fill(0,20,'A'),$paper->version);$service->approveKey($user,$paper->fresh(),$paper->fresh()->version);
    $id=$paper->id;$updated=$service->savePaper($user,$exam,['subject_id'=>$paper->subject_id,'grade'=>1,'paper_version'=>'EDITED','question_count'=>25,
        'choice_style'=>'numbers','choice_count'=>6,'points'=>4,'version'=>$paper->fresh()->version],$id);
    expect($updated->id)->toBe($id)->and($updated->paper_version)->toBe('EDITED')->and($updated->question_count)->toBe(25)
        ->and($updated->answer_choices)->toBe(['1','2','3','4','5','6'])->and($updated->draft_answers)->toBeNull()->and($updated->keys()->count())->toBe(0);
    $event=ScholarshipEvent::where('action','answer-paper-saved')->latest('id')->first();
    expect($event->details['cleared_keys'])->toHaveCount(1)->and($event->details['before']['paper_version'])->toBe('UNUSED');
});

it('rejects editing linked papers duplicate identities and locked exams',function(){
    [$exam,$user,$original,$sheet,$service]=answerSheetFixture();$paper=unusedAnswerPaper($exam,$user,$original,$service);
    $config=['subject_id'=>$original->subject_id,'grade'=>1,'paper_version'=>'A','question_count'=>20,'choice_style'=>'letters','choice_count'=>5,'points'=>5,'version'=>$paper->version];
    expect(fn()=>$service->savePaper($user,$exam,$config,$paper->id))->toThrow(ValidationException::class);
    expect(fn()=>$service->savePaper($user,$exam,array_replace($config,['version'=>$original->version]),$original->id))->toThrow(ValidationException::class);
    $exam->update(['published_at'=>now()]);
    expect(fn()=>$service->deletePaper($user,$paper,$paper->version))->toThrow(ValidationException::class);
});

it('lists paper view edit and delete actions with linked-paper protection',function(){
    [$exam,$user,$original,$sheet,$service]=answerSheetFixture();$paper=unusedAnswerPaper($exam,$user,$original,$service);
    $view=app(ScholarshipAnswerSheetController::class)->index(answerRequest($user),ScholarshipController::TABS);$data=$view->getData();
    expect($data['data']['papers']->firstWhere('id',$original->id)['linked'])->toBeTrue()->and($data['data']['papers']->firstWhere('id',$paper->id)['linked'])->toBeFalse();
    $template=preg_replace('/^@extends.*$|^@section.*$|^@endsection.*$|^@vite.*$/m','',file_get_contents(resource_path('views/western-scholarship/answer-sheets.blade.php')));
    $html=Blade::render($template,$data);$dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
    expect($xpath->query('//*[@data-answer-paper-row]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-answer-paper-delete="'.$original->id.'"][@disabled]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-answer-paper-delete="'.$paper->id.'"][@disabled]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-answer-paper-view="'.$original->id.'"]')->length)->toBe(1);
});
