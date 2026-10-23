<?php
use App\Models\{ScholarshipPaper,ScholarshipAnswerKey,ScholarshipAnswerScan,ScholarshipAnswerSheet,ScholarshipTeacherKeySheet,ScholarshipEvent};
use App\Services\{ScholarshipAnswerSheets,ScholarshipTeacherKeys};
use App\Support\{ScholarshipPermissions,ScholarshipAnswerSheetLayout};
use App\Http\Controllers\ScholarshipAnswerSheetController;
use Illuminate\Support\Facades\{Storage,DB};
use Illuminate\Http\{Request,UploadedFile};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
require_once __DIR__.'/../Fixtures/ScholarshipAnswerSheets.php';
beforeEach(function(){
    createScholarshipSchema();
    foreach(['000001_create_scholarship_answer_sheet_workflow','000002_add_scholarship_question_scores','000007_create_scholarship_teacher_key_sheets'] as $file)
        (require database_path('migrations/2026_10_22_'.$file.'.php'))->up();
    Storage::fake('local');
});
function teacherRequest($user,array $data=[]): Request
{
    Illuminate\Support\Facades\Auth::setUser($user);
    $request=Request::create('/students/western-scholarship','POST',$data);$request->setUserResolver(fn()=>$user);
    $request->setLaravelSession(app('session')->driver());app()->instance('request',$request);return $request;
}
function teacherFixture(string $style='letters',int $choices=5): array
{
    [$exam,$user,$original,$studentSheet,$sheets]=answerSheetFixture();
    $paper=$sheets->savePaper($user,$exam,['subject_id'=>$exam->subjects()->where('name','English')->first()->id,
        'grade'=>1,'paper_version'=>'A','question_count'=>20,'choice_style'=>$style,'choice_count'=>$choices,'points'=>5,'version'=>0]);
    $keys=app(ScholarshipTeacherKeys::class);$teacherSheet=$keys->issue($user,$paper,$paper->version);
    return [$exam,$user,$paper,$teacherSheet,$keys,$sheets,$studentSheet];
}
it('prints a blank identifiable teacher key before seats or keys are approved without linking students',function(){
    [$exam,$user,$paper,$teacherSheet,$keys]=teacherFixture();
    $exam->update(['seats_locked_at'=>null]);
    $same=$keys->issue($user,$paper,$paper->version);
    expect($same->token)->toBe($teacherSheet->token)->and($paper->sheets()->count())->toBe(0)->and($paper->keys()->count())->toBe(0);
    $svg=app(ScholarshipAnswerSheetLayout::class)->teacherSvg($teacherSheet);
    expect($svg)->toContain('ANSWER KEY','Teacher Name:','Teacher signature:','English, Grade 1','Teacher Key ID:')
        ->not->toContain('Student ID:','Candidate signature:');
    expect(substr_count($svg,'<circle '))->toBe(100);
    $controller=app(ScholarshipAnswerSheetController::class);$request=teacherRequest($user,['version'=>$paper->version]);
    $result=$controller->teacherKeyIssue($request,$paper)->getData(true);
    $html=$controller->teacherKeyPrint($request,basename(parse_url($result['url'],PHP_URL_PATH)))->getContent();
    expect($html)->toContain('WIS Scholarship Teacher Answer Key','size:A4 portrait;margin:0')->and(substr_count($html,'class="answer-print-page"'))->toBe(1);
});
it('detects teacher bubbles and flags blanks or multiple marks without saving or approving',function($style,$choices,$correct){
    [$exam,$user,$paper,$sheet,$keys,$sheets]=teacherFixture($style,$choices);
    $marks=array_fill(1,20,[$correct]);$marks[3]=[];$marks[8]=[$paper->answer_choices[0],$correct];$marks[12]=[];
    $image=imagecreatefromstring(answerSheetPixels($sheet,$marks));$dot=app(ScholarshipAnswerSheetLayout::class)->questions(20,$paper->answer_choices,$sheet->snapshot['layout_version'])[11]['dots'][0];
    $radius=(int)ceil($dot['radius']);
    imagefilledrectangle($image,$dot['x'],$dot['y']-$radius,$dot['x']+$radius,$dot['y']-1,imagecolorallocate($image,0,0,0));
    ob_start();imagepng($image);$pixels=ob_get_clean();imagedestroy($image);
    $file=UploadedFile::fake()->createWithContent('key.png',$pixels);
    $result=$keys->detect($user,$paper,$file,$paper->version);
    expect($result['answers'][0])->toBe($correct)->and($result['answers'][2])->toBeNull()->and($result['flags'][3])->toBe('blank')
        ->and($result['answers'][7])->toBeNull()->and($result['flags'][8])->toBe('multiple')->and($result['flags'][12])->toBe('unclear')
        ->and($paper->fresh()->draft_answers)->toBeNull()->and($paper->keys()->count())->toBe(0)->and($paper->sheets()->count())->toBe(0);
    $event=ScholarshipEvent::where('action','teacher-answer-key-scanned')->first();expect($event->details['flags'][8])->toBe('multiple');
    $answers=$result['answers'];$answers[2]=$correct;$answers[7]=$correct;$answers[11]=$correct;$points=[1,2,3,...array_fill(0,16,5),14];
    $sheets->saveKey($user,$paper,$answers,$paper->version,$points);$key=$sheets->approveKey($user,$paper->fresh(),$paper->fresh()->version);
    expect($key->answers)->toBe(array_fill(0,20,$correct))->and($key->question_points)->toBe($points);
})->with([['letters',6,'F'],['numbers',6,'6']]);
it('shares the student sheet question layout while keeping teacher fields and identity separate',function($count,$choiceCount){
    [$exam,$user,$paper,$sheet,$keys,$sheets]=teacherFixture();
    $paper=$sheets->savePaper($user,$exam,['subject_id'=>$paper->subject_id,'grade'=>1,'paper_version'=>'A','question_count'=>$count,
        'choice_style'=>'letters','choice_count'=>$choiceCount,'points'=>1,'version'=>$paper->version],$paper->id);
    $sheet=$keys->issue($user,$paper,$paper->version);$snapshot=$sheet->snapshot;
    $student=new ScholarshipAnswerSheet(['token'=>'0123456789abcdef01234567','snapshot'=>$snapshot+[
        'center'=>'Main Exam Center','name'=>'Sample Student','student_code'=>'0000000','code'=>'G01-001','room'=>1,'seat'=>1]]);
    $layout=app(ScholarshipAnswerSheetLayout::class);$teacherSvg=$layout->teacherSvg($sheet);$studentSvg=$layout->svg($student);
    preg_match_all('/<circle [^>]+>/',$teacherSvg,$teacherBubbles);preg_match_all('/<circle [^>]+>/',$studentSvg,$studentBubbles);
    expect($teacherBubbles[0])->toHaveCount($count*$choiceCount)->toBe($studentBubbles[0]);
    expect($teacherSvg)->toContain('SCHOLARSHIP EXAMINATION','Academic Year:','Invigilators','ANSWER KEY','Teacher Name:','Teacher Key ID:')
        ->not->toContain('Student ID:','Seat No.:','Candidate signature:');
    $result=$keys->detect($user,$paper,UploadedFile::fake()->createWithContent('key.png',answerSheetPixels($sheet,array_fill(1,$count,[end($snapshot['choices'])]))),$paper->version);
    expect($result['answers'])->toBe(array_fill(0,$count,end($snapshot['choices'])))->and($result['flags'])->toBe([]);
})->with([[40,5],[100,26]]);
it('continues to print and scan previously issued teacher keys after the layout changes',function(){
    [$exam,$user,$paper,$sheet,$keys]=teacherFixture();
    $snapshot=$sheet->snapshot;unset($snapshot['layout_version']);
    $sheet->update(['snapshot'=>$snapshot,'fingerprint'=>hash('sha256',json_encode($snapshot))]);
    $new=$keys->issue($user,$paper,$paper->version);$layout=app(ScholarshipAnswerSheetLayout::class);
    expect($new->token)->not->toBe($sheet->token)->and($new->snapshot['layout_version'])->toBe(2)
        ->and($keys->current($sheet))->toBeTrue()->and($keys->current($new))->toBeTrue();
    expect($layout->teacherSvg($sheet))->toContain('Grade: 1')->not->toContain('SCHOLARSHIP EXAMINATION');
    expect($layout->teacherSvg($new))->toContain('SCHOLARSHIP EXAMINATION');
    foreach([$sheet,$new] as $printed){
        $result=$keys->detect($user,$paper,UploadedFile::fake()->createWithContent('key.png',answerSheetPixels($printed,array_fill(1,20,['D']))),$paper->version);
        expect($result['answers'])->toBe(array_fill(0,20,'D'))->and($result['flags'])->toBe([]);
    }
});
it('imports a corrected key without changing an approved revision scores or existing student sheets',function(){
    [$exam,$user,$paper,$sheet,$keys,$sheets]=teacherFixture();
    $sheets->saveKey($user,$paper,array_fill(0,20,'A'),$paper->version);$old=$sheets->approveKey($user,$paper->fresh(),$paper->fresh()->version);
    $linked=$sheets->linkStudents($user,$paper->fresh(),$paper->fresh()->version);$tokens=$linked->pluck('token')->all();
    $sheets->saveKey($user,$paper->fresh(),array_fill(0,20,'A'),$paper->fresh()->version,[1,2,3,...array_fill(0,16,5),14]);
    $result=$keys->detect($user,$paper->fresh(),UploadedFile::fake()->createWithContent('key.png',answerSheetPixels($sheet,array_fill(1,20,['B']))),$paper->fresh()->version);
    expect($old->fresh()->answers)->toBe(array_fill(0,20,'A'))->and($paper->keys()->count())->toBe(1)
        ->and($paper->fresh()->draft_points)->toBe([1,2,3,...array_fill(0,16,5),14])->and($paper->sheets()->pluck('token')->all())->toBe($tokens);
    $sheets->saveKey($user,$paper->fresh(),$result['answers'],$paper->fresh()->version);$new=$sheets->approveKey($user,$paper->fresh(),$paper->fresh()->version);
    expect($new->revision)->toBe(2)->and($old->fresh()->answers)->toBe(array_fill(0,20,'A'))->and($sheets->current($linked->first()))->toBeTrue();
});
it('rejects student sheets wrong papers stale configurations stale drafts and unreadable images',function(){
    [$exam,$user,$paper,$sheet,$keys,$sheets,$studentSheet]=teacherFixture();
    expect(fn()=>$keys->detect($user,$paper,UploadedFile::fake()->createWithContent('student.png',answerSheetPixels($studentSheet)),$paper->version))->toThrow(ValidationException::class);
    $wrong=$exam->subjects()->where('name','Science')->first();$other=$sheets->savePaper($user,$exam,['subject_id'=>$wrong->id,'grade'=>1,'paper_version'=>'A','question_count'=>20,'choice_style'=>'letters','choice_count'=>5,'points'=>5,'version'=>0]);
    $file=UploadedFile::fake()->createWithContent('key.png',answerSheetPixels($sheet));
    expect(fn()=>$keys->detect($user,$other,$file,$other->version))->toThrow(ValidationException::class);
    expect(fn()=>$keys->detect($user,$paper,$file,$paper->version-1))->toThrow(ValidationException::class);
    expect(fn()=>$keys->detect($user,$paper,UploadedFile::fake()->image('not-a-sheet.png'),$paper->version))->toThrow(ValidationException::class);
    $sheets->savePaper($user,$exam,['subject_id'=>$paper->subject_id,'grade'=>1,'paper_version'=>'A','question_count'=>22,'choice_style'=>'letters','choice_count'=>5,'points'=>5,'version'=>$paper->version],$paper->id);
    expect($keys->current($sheet))->toBeFalse();
    expect(fn()=>$keys->detect($user,$paper->fresh(),$file,$paper->fresh()->version))->toThrow(ValidationException::class);
    $new=$keys->issue($user,$paper->fresh(),$paper->fresh()->version);expect($new->token)->not->toBe($sheet->token);
});
it('never accepts a teacher key through student scanning',function(){
    [$exam,$user,$paper,$sheet,$keys,$sheets]=teacherFixture();
    $file=UploadedFile::fake()->createWithContent('teacher.png',answerSheetPixels($sheet));
    expect(fn()=>$sheets->upload($user,$exam,$paper,$file,$file))->toThrow(ValidationException::class);
    expect(ScholarshipAnswerScan::count())->toBe(0);
});
it('checks separate teacher print scan edit permissions for Central and Campus staff',function($central){
    [$exam,$user,$paper,$sheet,$keys]=teacherFixture();$file=UploadedFile::fake()->createWithContent('teacher.png',answerSheetPixels($sheet));
    $printOnly=answerSheetUser($central,['tab-answer-sheets','answer-key-print']);
    expect($keys->issue($printOnly,$paper,$paper->version)->id)->toBe($sheet->id);
    expect(fn()=>$keys->detect($printOnly,$paper,$file,$paper->version))->toThrow(HttpException::class);
    $scanOnly=answerSheetUser($central,['tab-answer-sheets','answer-key-scan']);
    expect(fn()=>$keys->detect($scanOnly,$paper,$file,$paper->version))->toThrow(HttpException::class);
    $editor=answerSheetUser($central,['tab-answer-sheets','answer-key','answer-key-scan']);
    expect($keys->detect($editor,$paper,$file,$paper->version)['paper_id'])->toBe($paper->id);
    expect(fn()=>$keys->issue($editor,$paper,$paper->version))->toThrow(HttpException::class);
    $exam->update(['published_at'=>now()]);expect(fn()=>$keys->detect($editor,$paper->fresh(),$file,$paper->version))->toThrow(ValidationException::class);
})->with([true,false]);
it('protects teacher print sessions and validates uploaded files on the controller',function(){
    [$exam,$user,$paper,$sheet,$keys]=teacherFixture();$controller=app(ScholarshipAnswerSheetController::class);
    expect(fn()=>$controller->teacherKeyPrint(teacherRequest($user),str_repeat('a',32)))->toThrow(HttpException::class);
    expect(fn()=>$controller->teacherKeyScan(teacherRequest($user,['version'=>$paper->version]),$paper))->toThrow(ValidationException::class);
    $request=teacherRequest($user,['version'=>$paper->version]);$request->files->set('image',UploadedFile::fake()->createWithContent('key.png',answerSheetPixels($sheet,array_fill(1,20,['C']))));
    expect($controller->teacherKeyScan($request,$paper)->getData(true)['answers'])->toBe(array_fill(0,20,'C'));
});
it('requires authentication on teacher print and scan routes',function(){
    $this->postJson(route('western-scholarship.answer-sheets.teacher-key-issue',1))->assertUnauthorized();
    $this->postJson(route('western-scholarship.answer-sheets.teacher-key-scan',1))->assertUnauthorized();
    $this->getJson(route('western-scholarship.answer-sheets.teacher-key-print',str_repeat('a',32)))->assertUnauthorized();
});
