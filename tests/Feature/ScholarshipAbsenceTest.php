<?php
use App\Models\{ScholarshipScore,ScholarshipTeacherKeySheet};
use App\Services\{ScholarshipAnswerSheets,ScholarshipScanAcceptance,ScholarshipTestScans};
use App\Support\{ScholarshipAbsenceMark,ScholarshipAnswerSheetLayout,ScholarshipMarkedAnswers};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
require_once __DIR__.'/../Fixtures/ScholarshipAnswerSheets.php';
beforeEach(function(){
    createScholarshipSchema();
    foreach(['2026_10_22_000001_create_scholarship_answer_sheet_workflow','2026_10_22_000002_add_scholarship_question_scores','2026_10_23_000007_add_scholarship_scan_absence_detection'] as $file)
        (require database_path('migrations/'.$file.'.php'))->up();
    Storage::fake('local');
});
function absenceSheetPixels($sheet,string $mark='A',array $answers=[],int $shade=0): string
{
    $image=imagecreatefromstring(answerSheetPixels($sheet,$answers));$ink=imagecolorallocate($image,$shade,$shade,$shade);imagesetthickness($image,3);
    [$x,$y,$width,$height]=ScholarshipAbsenceMark::box($sheet->snapshot['layout_version']??1);imagerectangle($image,$x,$y,$x+$width,$y+$height,$ink);
    $line=fn($x1,$y1,$x2,$y2)=>imageline($image,$x1+$x-545,$y1+$y-263,$x2+$x-545,$y2+$y-263,$ink);
    if(in_array($mark,['A','slanted-A'],true)){
        $tip=$mark==='slanted-A'?574:568;$line(553,299,$tip,270);$line($tip,270,585,299);$line(559,289,579,289);
    }
    if($mark==='X'){$line(554,270,585,299);$line(585,270,554,299);}
    if($mark==='V'){$line(554,270,569,299);$line(569,299,585,270);}
    if($mark==='H'){$line(554,270,554,299);$line(585,270,585,299);$line(554,285,585,285);}
    if($mark==='tick'){$line(554,285,565,298);$line(565,298,585,270);}
    if($mark==='filled')imagefilledrectangle($image,$x+8,$y+7,$x+40,$y+36,$ink);
    ob_start();imagepng($image);$bytes=ob_get_clean();imagedestroy($image);return $bytes;
}
function uploadAbsenceSheet($exam,$user,$paper,$sheet,string $mark='A',array $answers=[],int $shade=0)
{
    $file=UploadedFile::fake()->createWithContent('absent.png',absenceSheetPixels($sheet,$mark,$answers,$shade));
    return app(ScholarshipAnswerSheets::class)->upload($user,$exam,$paper,$file,$file);
}

it('recognizes a handwritten capital A and saves zero with subject absence after acceptance',function($mark,$shade){
    [$exam,$user,$paper,$sheet,$sheets]=answerSheetFixture();
    $other=$exam->subjects()->where('id','!=',$paper->subject_id)->first();$score=$sheet->candidate->scores()->create(['subject_id'=>$other->id,'score'=>72,'is_absent'=>false,'entry_method'=>'historical','source_reference'=>'original']);$before=$score->only(['score','is_absent','entry_method','source_reference']);
    $scan=uploadAbsenceSheet($exam,$user,$paper,$sheet,$mark,[],$shade);
    expect($scan->detectedAbsent())->toBeTrue()->and($scan->status)->toBe('pending')->and($scan->flaggedCount())->toBe(0)
        ->and($sheet->candidate->scores()->where('subject_id',$paper->subject_id)->exists())->toBeFalse();
    $sheets->verify($user,$scan,$scan->detected,$scan->candidate_version,false,'');
    $saved=$sheet->candidate->scores()->where('subject_id',$paper->subject_id)->sole();
    expect($saved->score)->toBe(0.0)->and($saved->is_absent)->toBeTrue()->and($saved->entry_method)->toBe('scan')
        ->and($saved->source_reference)->toBe((string)$scan->id)->and($score->fresh()->only(array_keys($before)))->toBe($before)
        ->and($sheet->candidate->fresh()->absent)->toBeFalse()->and($scan->fresh()->subjectAbsent())->toBeTrue();
    $marked=app(ScholarshipMarkedAnswers::class)->data($scan->fresh());expect($marked['subject_absent'])->toBeTrue()->and($marked['total'])->toBe(0.0)->and($marked['rows'][0]['status'])->toBe('absent')->and($marked['rows'][0]['borders'])->toBe([]);
})->with([['A',0],['slanted-A',0],['A',210],['A',220]]);

it('leaves an empty absence box present and flags other handwriting for review',function($mark,$state){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();$scan=uploadAbsenceSheet($exam,$user,$paper,$sheet,$mark);
    expect($scan->absence['state'])->toBe($state)->and($scan->detectedAbsent())->toBeFalse();
})->with([['blank','blank'],['X','unclear'],['V','unclear'],['H','unclear'],['tick','unclear'],['filled','unclear']]);

it('requires individual attendance review for an A alongside answer marks and keeps the raw evidence',function(){
    [$exam,$user,$paper,$sheet,$sheets]=answerSheetFixture();$scan=uploadAbsenceSheet($exam,$user,$paper,$sheet,'A',[1=>['A']]);
    expect($scan->absence['state'])->toBe('conflict')->and($scan->detected[0])->toBe('A');
    expect(fn()=>$sheets->verify($user,$scan,$scan->detected,$scan->candidate_version,false,''))->toThrow(ValidationException::class,'attendance');
    $preview=app(ScholarshipScanAcceptance::class)->preview($user,$paper);expect($preview['eligible'])->toBe(0)->and($preview['skipped'])->toHaveKey('Absence mark needs individual review');
    $sheets->verify($user,$scan,$scan->detected,$scan->candidate_version,false,'Invigilator confirmed subject absence',true,true);
    expect($scan->fresh()->detected[0])->toBe('A')->and($scan->fresh()->responses[0])->toBeNull()->and($scan->fresh()->score)->toBe(0.0)->and($scan->fresh()->subjectAbsent())->toBeTrue()->and($scan->fresh()->flaggedCount())->toBe(0);
});

it('allows staff to correct detected absence to present only with attendance confirmation and a reason',function(){
    [$exam,$user,$paper,$sheet,$sheets]=answerSheetFixture();$scan=uploadAbsenceSheet($exam,$user,$paper,$sheet);
    expect(fn()=>$sheets->verify($user,$scan,$scan->detected,$scan->candidate_version,false,'',false,true))->toThrow(ValidationException::class,'attendance');
    $sheets->verify($user,$scan,$scan->detected,$scan->candidate_version,false,'Invigilator marked the wrong sheet',false,true);
    expect($scan->fresh()->score)->toBe(0.0)->and($scan->fresh()->subjectAbsent())->toBeFalse()->and($scan->fresh()->flaggedCount())->toBe(20)->and($sheet->candidate->scores()->where('subject_id',$paper->subject_id)->sole()->is_absent)->toBeFalse();
});

it('includes clear Auto Absent scans in Accept All and protects existing scores including zero',function($hasScore){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();if($hasScore)$sheet->candidate->scores()->create(['subject_id'=>$paper->subject_id,'score'=>0,'entry_method'=>'manual']);
    $scan=uploadAbsenceSheet($exam,$user,$paper,$sheet);$service=app(ScholarshipScanAcceptance::class);$preview=$service->preview($user,$paper);
    if($hasScore){expect($preview['eligible'])->toBe(0)->and($preview['skipped'])->toHaveKey('Existing score (including zero)');return;}
    expect($preview)->toMatchArray(['eligible'=>1,'flagged'=>0,'absent'=>1]);$service->accept($user,$paper,[],$preview['fingerprint'],false);
    expect($scan->fresh()->subjectAbsent())->toBeTrue()->and($scan->fresh()->score)->toBe(0.0);
})->with([false,true]);

it('does not overwrite a saved score with scanned absence without replacement permission and confirmation',function(){
    [$exam,$user,$paper,$sheet,$sheets]=answerSheetFixture();$score=$sheet->candidate->scores()->create(['subject_id'=>$paper->subject_id,'score'=>80,'entry_method'=>'manual']);$scan=uploadAbsenceSheet($exam,$user,$paper,$sheet);
    expect(fn()=>$sheets->verify($user,$scan,$scan->detected,$scan->candidate_version,false,''))->toThrow(ValidationException::class,'replacement');
    $sheets->verify($user,$scan,$scan->detected,$scan->candidate_version,true,'Confirmed replacement with subject absence');
    expect($score->fresh()->score)->toBe(0.0)->and($score->fresh()->is_absent)->toBeTrue();
});

it('keeps earlier layouts scannable without inferring absence from their former blank header area',function(){
    [$exam,$user,$paper,$sheet,$sheets]=answerSheetFixture();$snapshot=$sheets->snapshot($paper,$sheet->candidate,4);$sheet->update(['snapshot'=>$snapshot,'fingerprint'=>hash('sha256',json_encode($snapshot))]);
    $scan=uploadAbsenceSheet($exam,$user,$paper,$sheet);expect($scan->absence)->toBeNull()->and($scan->detectedAbsent())->toBeFalse();
    expect(app(ScholarshipAnswerSheetLayout::class)->svg($sheet))->not->toContain('Invigilator only','Write A');
});

it('preserves the printed box and A detection on previously issued sheets',function($version,$box){
    [$exam,$user,$paper,$sheet,$sheets]=answerSheetFixture();$snapshot=$sheets->snapshot($paper,$sheet->candidate,$version);
    $sheet->update(['snapshot'=>$snapshot,'fingerprint'=>hash('sha256',json_encode($snapshot))]);
    $svg=app(ScholarshipAnswerSheetLayout::class)->svg($sheet);
    [$x,$y,$width,$height]=$box;
    expect($svg)->toContain('x="'.$x.'" y="'.$y.'" width="'.$width.'" height="'.$height.'"');
    $scan=uploadAbsenceSheet($exam,$user,$paper,$sheet);expect($scan->detectedAbsent())->toBeTrue();
})->with([[5,[545,263,48,42]],[6,[545,253,48,42]],[7,[635,210,70,85]]]);

it('does not print or read a student absence box on teacher answer keys',function(){
    [$exam,$user,$paper,$sheet]=answerSheetFixture();$layout=app(ScholarshipAnswerSheetLayout::class);
    $result=$layout->read(absenceSheetPixels($sheet),['question_count'=>$paper->question_count,'choices'=>$paper->answer_choices,'layout_version'=>5]);
    $teacher=new ScholarshipTeacherKeySheet(['snapshot'=>$sheet->snapshot,'token'=>$sheet->token]);
    expect($result['absence'])->toBeNull()->and($layout->teacherSvg($teacher))->not->toContain('Invigilator only','Write A');
});

it('preserves absence evidence on recheck and clears only the scan-created subject absence during test cleanup',function(){
    [$exam,$user,$paper,$sheet,$sheets]=answerSheetFixture();$scan=uploadAbsenceSheet($exam,$user,$paper,$sheet);$sheets->verify($user,$scan,$scan->detected,$scan->candidate_version,false,'');
    $next=$sheets->recheck($user,$scan->fresh(),'Recheck the absent student');expect($next->detectedAbsent())->toBeTrue()->and($next->absence)->not->toHaveKey('verified_absent');
    $service=app(ScholarshipTestScans::class);$preview=$service->preview($user,$paper);$service->clear($user,$paper,[],$preview['fingerprint']);
    expect($sheet->candidate->scores()->where('subject_id',$paper->subject_id)->exists())->toBeFalse()->and($sheet->candidate->fresh()->absent)->toBeFalse();
});

it('never accepts absence after results are published',function(){
    [$exam,$user,$paper,$sheet,$sheets]=answerSheetFixture();$scan=uploadAbsenceSheet($exam,$user,$paper,$sheet);$exam->update(['published_at'=>now()]);
    expect(fn()=>$sheets->verify($user,$scan,$scan->detected,$scan->candidate_version,false,''))->toThrow(ValidationException::class,'Published');
    expect($scan->fresh()->status)->toBe('pending')->and(ScholarshipScore::count())->toBe(0);
});
