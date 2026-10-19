<?php

use App\Http\Controllers\ScholarshipController;
use App\Models\{ScholarshipExam,User};
use App\Services\ScholarshipWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB};
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
beforeEach(fn()=>createScholarshipSchema());

function scholarshipScoringRequest(array $params=[],bool $super=true,string $method='GET'): Request
{
    $user=Mockery::mock(User::class)->makePartial();$user->id=1;$user->active_campus_id=1;
    $user->setRelation('roles',collect());
    $user->shouldReceive('isSuperAdmin')->andReturn($super);
    $user->shouldReceive('hasPermission')->andReturnTrue();
    Auth::setUser($user);
    $request=Request::create('/students/western-scholarship',$method,['tab'=>'scoring']+$params);
    $request->setUserResolver(fn()=>$user);$request->setLaravelSession(app('session')->driver());
    app()->instance('request',$request);
    return $request;
}

function scholarshipScoringExam(): ScholarshipExam
{
    $exam=scholarshipSeatReportExam(3);$first=$exam->candidates()->first();
    $extra=$first->replicate();$extra->room_number=2;$extra->room_seat=4;$extra->grade_exam_number=4;$extra->candidate_code='G01-004';
    $extra->snapshot=array_replace($extra->snapshot,['name_en'=>'ROOM TWO ENGLISH NAME']);$extra->save();
    return $exam->fresh();
}

it('shows scoring rows only after selecting a center and room with cascading English name options',function(){
    $exam=scholarshipScoringExam();$controller=app(ScholarshipController::class);$params=['exam_id'=>$exam->id,'score_center'=>1,'score_room'=>2];
    $data=$controller->index(scholarshipScoringRequest($params))->getData();
    expect($data['scoringCandidates']->count())->toBe(1)
        ->and($data['scoreFilterOptions']['rooms']->pluck('id')->all())->toBe([1,2])
        ->and($data['scoreFilterOptions']['students']->pluck('label')->all())->toBe(['4 - ROOM TWO ENGLISH NAME']);
    $id=$data['scoringCandidates']->first()->id;
    expect($controller->index(scholarshipScoringRequest($params+['score_student'=>$id]))->getData()['scoringCandidates']->count())->toBe(1);
    $all=$controller->index(scholarshipScoringRequest(['exam_id'=>$exam->id]))->getData();
    expect($all['scoringCandidates'])->toBeEmpty()->and($all['scoreListReady'])->toBeFalse()->and($all['scoreFilterOptions']['rooms'])->toBeEmpty()->and($all['scoreFilterOptions']['students'])->toBeEmpty();
    expect(view('western-scholarship._scoring',$all)->render())->not->toContain('<table');
    $html=view('western-scholarship._scoring-filters',$data)->render();
    expect($html)->toContain('Datas Filter','data-scoring-filters-toggle','4 - ROOM TWO ENGLISH NAME');
});

it('rejects scoring filter combinations outside their parent center and room',function($filters){
    $exam=scholarshipScoringExam();
    expect(fn()=>app(ScholarshipController::class)->index(scholarshipScoringRequest(['exam_id'=>$exam->id]+$filters)))->toThrow(ValidationException::class);
})->with([
    [['score_room'=>1]],
    [['score_center'=>2,'score_room'=>2]],
    [['score_center'=>1,'score_room'=>2,'score_student'=>1]],
    [['score_student'=>1]],
    [['score_center'=>999]],
]);

it('loads every student in the selected room without pagination and ignores old page numbers',function(){
    $exam=scholarshipSeatReportExam(26);$controller=app(ScholarshipController::class);
    $centerOnly=$controller->index(scholarshipScoringRequest(['exam_id'=>$exam->id,'score_center'=>1]))->getData();
    expect($centerOnly['scoringCandidates'])->toBeEmpty()->and($centerOnly['scoreListReady'])->toBeFalse();
    $data=$controller->index(scholarshipScoringRequest(['exam_id'=>$exam->id,'score_center'=>1,'score_room'=>1,'score_page'=>2]))->getData();
    expect($data['scoringCandidates']->count())->toBe(26)->and($data['scoreListReady'])->toBeTrue();
    $html=view('western-scholarship._scoring',$data)->render();$dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
    expect($xpath->query('//tr[@data-scoring-candidate]')->length)->toBe(26);
    expect($xpath->query('//tr[@data-scoring-candidate][last()]/td[1]')->item(0)->textContent)->toBe('26');
    expect($html)->not->toContain('score_page=','class="pagination');
});

it('changes the scoring exam with academic year and excludes unconfigured years',function(){
    $first=scholarshipScoringExam();$next=$first->replicate();$next->academic_year_id=2;$next->name='2026-2027';$next->save();
    $center=$next->centers()->create(['name'=>'Next Year Center','position'=>0]);
    $copy=$first->candidates()->first()->replicate();$copy->exam_id=$next->id;$copy->exam_center_id=$center->id;$copy->seat_scope=$center->id;$copy->exam_center_name=$center->name;$copy->save();
    $data=app(ScholarshipController::class)->index(scholarshipScoringRequest(['academic_year_id'=>2,'exam_id'=>$first->id]))->getData();
    expect($data['exam']->id)->toBe($next->id)->and($data['academicYears']->pluck('id')->all())->toBe([2,1])
        ->and($data['scoreFilterOptions']['centers']->pluck('label')->all())->toBe(['Next Year Center']);
    expect(fn()=>app(ScholarshipController::class)->index(scholarshipScoringRequest(['academic_year_id'=>2,'score_center'=>1])))->toThrow(ValidationException::class);
    $copy->delete();$next->delete();
    $empty=app(ScholarshipController::class)->index(scholarshipScoringRequest(['academic_year_id'=>2]))->getData();
    expect($empty['exam'])->toBeNull()->and($empty['academicYears']->pluck('id')->all())->toBe([1])->and($empty['scoreFilterOptions']['centers'])->toBeEmpty();
});

it('does not expose other scoring assignments through filter options or rows',function(){
    $exam=scholarshipScoringExam();
    $exam->committee()->create(['user_id'=>1,'role'=>'score-entry','exam_center_id'=>1,'room_number'=>1]);
    $data=app(ScholarshipController::class)->index(scholarshipScoringRequest(['exam_id'=>$exam->id,'score_center'=>1,'score_room'=>1],false))->getData();
    expect($data['scoringCandidates']->count())->toBe(3)->and($data['scoreFilterOptions']['centers']->pluck('id')->all())->toBe([1])
        ->and($data['scoreFilterOptions']['rooms']->pluck('id')->all())->toBe([1]);
    expect(fn()=>app(ScholarshipController::class)->index(scholarshipScoringRequest(['exam_id'=>$exam->id,'score_center'=>2],false)))->toThrow(ValidationException::class);
});

it('renders grade specific subjects and one shared draft form with separate review actions',function(){
    $exam=scholarshipScoringExam();
    $candidate=$exam->candidates()->first();$candidate->update(['student_id'=>1,'student_enrollment_id'=>1]);
    DB::table('tb_student')->where('id',1)->update(['photo_path'=>'testing/student-photo.jpg']);
    DB::table('tb_grade')->where('id',1)->update(['grade_short_name'=>'1']);
    $secondary=$exam->candidates()->where('campus_id',2)->first();$secondary->update(['grade_number'=>7,'exam_center_id'=>1,'exam_center_name'=>'North Exam Center','seat_scope'=>1,'room_seat'=>5]);
    $data=app(ScholarshipController::class)->index(scholarshipScoringRequest(['exam_id'=>$exam->id,'score_center'=>1,'score_room'=>1]))->getData();
    $html=view('western-scholarship._scoring',$data)->render();$dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
    expect($xpath->query('//section[@aria-labelledby="scoringGrade1"]//thead//th')->length)->toBe(13);
    expect($xpath->query('//tr[@data-scoring-candidate="'.$candidate->id.'"]/td[@class="scholarship-scoring-attendance"]//input[@form="scholarshipScoreDrafts"]')->length)->toBe(1);
    expect($xpath->query('//button[@data-save-score-drafts]')->length)->toBe(1);
    expect($xpath->query('//button[@data-review-scores]')->length)->toBe(1);
    expect($xpath->query('//form[@data-score-review-form]//input[@type="checkbox"]')->length)->toBe(0);
    expect($xpath->query('//input[@data-score-input][not(@readonly)]')->length)->toBe(0);
    $filterHtml=view('western-scholarship._scoring-filters',$data)->render();
    $filterDom=new DOMDocument();@$filterDom->loadHTML($filterHtml);$filterXpath=new DOMXPath($filterDom);
    expect($filterXpath->query('//section[@data-scoring-filters-card]//select[@data-scoring-subject]')->length)->toBe(1);
    expect($filterXpath->query('//select[@data-scoring-subject]/ancestor::form')->length)->toBe(0);
    expect($xpath->query('//select[@data-scoring-subject]')->length)->toBe(0);
    expect($xpath->query('//tr[@data-scoring-candidate]//button[contains(text(),"Mark Reviewed")]')->length)->toBe(0);
    $secondaryTable=$xpath->query('//section[@aria-labelledby="scoringGrade7"]')->item(0);
    expect($secondaryTable->textContent)->toContain('Secondary Only')->not->toContain('Mathematics');
    expect($xpath->query('//tr[@data-scoring-candidate="'.$candidate->id.'"]//img[@width="600"][@height="800"]')->length)->toBe(1);
    foreach($xpath->query('//input[contains(@name,"[scores][")]') as $input){
        expect($xpath->query('//form[@id="'.$input->getAttribute('form').'"]')->length)->toBe(1);
    }
    expect($html)->toContain('2025-2026-BCH-1A','Room:','Seat:','Save Draft','Mark Reviewed')->not->toContain('name="exam_grade"');
});

it('saves multiple students scores and attendance in one draft action',function($compact){
    $exam=scholarshipScoringExam();$rows=[];$candidates=$exam->candidates()->where('exam_center_id',1)->limit(2)->get();
    foreach($candidates as $index=>$candidate)$rows[$candidate->id]=['version'=>$candidate->version,'scores'=>app(ScholarshipWorkflow::class)->applicableSubjects($exam,1)->mapWithKeys(fn($subject)=>[$subject->id=>80+$index])->all(),'absent'=>$index===1];
    $request=scholarshipScoringRequest($compact?['score_drafts'=>json_encode($rows)]:['rows'=>$rows],true,'POST');$request->query->replace(['score_center'=>1]);
    $response=app(ScholarshipController::class)->saveScoreDrafts($request,$exam);
    expect($response->getTargetUrl())->toContain('score_center=1');
    expect(app(ScholarshipWorkflow::class)->calculate($exam->fresh(),$candidates[0]->fresh())['total'])->toBe(400.0)
        ->and($candidates[1]->fresh()->absent)->toBeTrue();
    foreach($candidates as $candidate)expect($candidate->fresh()->version)->toBe($candidate->version+1);
})->with([false,true]);

it('rolls back every draft when a later row is invalid stale or unauthorized',function($failure){
    $exam=scholarshipScoringExam();$candidates=$exam->candidates()->where('exam_center_id',1)->limit(2)->get();
    if($failure==='unauthorized'){
        $exam->committee()->create(['user_id'=>1,'role'=>'score-entry','exam_center_id'=>1]);
        $candidates[1]=$exam->candidates()->where('exam_center_id',2)->first();
    }
    $rows=[];
    foreach($candidates as $candidate)$rows[$candidate->id]=['version'=>$candidate->version,'scores'=>app(ScholarshipWorkflow::class)->applicableSubjects($exam,$candidate->grade_number)->mapWithKeys(fn($subject)=>[$subject->id=>80])->all()];
    if($failure==='invalid')$rows[$candidates[1]->id]['scores'][array_key_first($rows[$candidates[1]->id]['scores'])]=101;
    if($failure==='stale')++$rows[$candidates[1]->id]['version'];
    if($failure==='published')$exam->update(['published_at'=>now()]);
    $request=scholarshipScoringRequest(['rows'=>$rows],$failure!=='unauthorized','POST');
    expect(fn()=>app(ScholarshipController::class)->saveScoreDrafts($request,$exam))->toThrow($failure==='unauthorized'?\Symfony\Component\HttpKernel\Exception\HttpException::class:ValidationException::class);
    foreach($candidates as $candidate)expect($candidate->fresh()->version)->toBe($candidate->version)->and($candidate->scores()->count())->toBe(0);
    expect($exam->fresh()->version)->toBe($exam->version);
})->with(['invalid','stale','unauthorized','published']);

it('keeps reviewed scores unchanged when saving an unchanged page',function(){
    $exam=scholarshipScoringExam();$candidate=$exam->candidates()->first();$request=scholarshipScoringRequest();
    $workflow=app(ScholarshipWorkflow::class);$scores=$workflow->applicableSubjects($exam,1)->mapWithKeys(fn($subject)=>[$subject->id=>80])->all();
    $workflow->saveScores($request->user(),$exam,$candidate,$scores,false,$candidate->version);
    $workflow->review($request->user(),$exam,$candidate->fresh(),$candidate->fresh()->version);
    $candidate=$candidate->fresh();$events=$exam->events()->count();
    $rows=[$candidate->id=>['version'=>$candidate->version,'scores'=>array_map(fn($value)=>number_format($value,2),$scores)]];
    app(ScholarshipController::class)->saveScoreDrafts(scholarshipScoringRequest(['rows'=>$rows],true,'POST'),$exam);
    expect($candidate->fresh()->reviewed_at)->not->toBeNull()->and($candidate->fresh()->version)->toBe($candidate->version)->and($exam->events()->count())->toBe($events);
});

it('reviews saved scores and absences together and retains the selected filters',function(){
    $exam=scholarshipScoringExam();$request=scholarshipScoringRequest();$workflow=app(ScholarshipWorkflow::class);
    $candidates=$exam->candidates()->where('exam_center_id',1)->limit(2)->get();$rows=[];
    foreach($candidates as $index=>$candidate){
        $scores=$workflow->applicableSubjects($exam,1)->mapWithKeys(fn($subject)=>[$subject->id=>80])->all();
        $workflow->saveScores($request->user(),$exam,$candidate,$index===1?[]:$scores,$index===1,$candidate->version);
        $rows[$candidate->id]=['version'=>$candidate->fresh()->version];
    }
    $request=scholarshipScoringRequest(['rows'=>$rows,'confirm'=>1],true,'POST');$request->query->replace(['score_center'=>1,'score_room'=>1]);
    $response=app(ScholarshipController::class)->reviewScores($request,$exam);
    expect($response->getTargetUrl())->toContain('score_center=1','score_room=1');
    foreach($candidates as $candidate)expect($candidate->fresh()->reviewed_at)->not->toBeNull()->and($candidate->fresh()->reviewed_by)->toBe(1);
    $data=app(ScholarshipController::class)->index(scholarshipScoringRequest(['exam_id'=>$exam->id,'score_center'=>1,'score_room'=>1]))->getData();
    $html=view('western-scholarship._scoring',$data)->render();
    $dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
    foreach($candidates as $candidate)expect($xpath->query('//form[@data-score-review-form]//input[@name="rows['.$candidate->id.'][version]"]')->length)->toBe(0);
});

it('rolls back a whole review when any row is incomplete stale unauthorized or published',function($failure){
    $exam=scholarshipScoringExam();$request=scholarshipScoringRequest();$workflow=app(ScholarshipWorkflow::class);
    $candidates=$exam->candidates()->where('exam_center_id',1)->limit(2)->get();
    if($failure==='unauthorized'){
        $exam->committee()->create(['user_id'=>1,'role'=>'score-reviewer','exam_center_id'=>1]);
        $candidates[1]=$exam->candidates()->where('exam_center_id',2)->first();
    }
    $rows=[];
    foreach($candidates as $index=>$candidate){
        if($failure!=='incomplete'||$index===0){
            $scores=$workflow->applicableSubjects($exam,$candidate->grade_number)->mapWithKeys(fn($subject)=>[$subject->id=>80])->all();
            $workflow->saveScores($request->user(),$exam,$candidate,$scores,false,$candidate->version);
        }
        $rows[$candidate->id]=['version'=>$candidate->fresh()->version];
    }
    if($failure==='stale')++$rows[$candidates[1]->id]['version'];
    if($failure==='published')$exam->update(['published_at'=>now()]);
    $events=$exam->events()->count();
    $request=scholarshipScoringRequest(['rows'=>$rows,'confirm'=>1],$failure!=='unauthorized','POST');
    expect(fn()=>app(ScholarshipController::class)->reviewScores($request,$exam))->toThrow($failure==='unauthorized'?\Symfony\Component\HttpKernel\Exception\HttpException::class:ValidationException::class);
    foreach($candidates as $candidate)expect($candidate->fresh()->reviewed_at)->toBeNull()->and($candidate->fresh()->version)->toBe($rows[$candidate->id]['version']-($failure==='stale'&&$candidate->id===$candidates[1]->id?1:0));
    expect($exam->events()->count())->toBe($events);
})->with(['incomplete','stale','unauthorized','published']);

it('reviews complete saved scores with one click and no confirmation checkbox',function(){
    $exam=scholarshipScoringExam();$candidate=$exam->candidates()->first();$request=scholarshipScoringRequest();
    $workflow=app(ScholarshipWorkflow::class);$scores=$workflow->applicableSubjects($exam,1)->mapWithKeys(fn($subject)=>[$subject->id=>80])->all();
    $workflow->saveScores($request->user(),$exam,$candidate,$scores,false,$candidate->version);
    app(ScholarshipController::class)->reviewScores(scholarshipScoringRequest(['rows'=>[$candidate->id=>['version'=>$candidate->fresh()->version]]],true,'POST'),$exam);
    expect($candidate->fresh()->reviewed_at)->not->toBeNull();
});

it('keeps scoring filters after saving a row and preserves score validation and publication locks',function(){
    $exam=scholarshipScoringExam();$candidate=$exam->candidates()->where('room_number',2)->first();
    $scores=app(ScholarshipWorkflow::class)->applicableSubjects($exam,1)->mapWithKeys(fn($subject)=>[$subject->id=>80])->all();
    $request=scholarshipScoringRequest(['version'=>$candidate->version,'scores'=>$scores],true,'POST');
    $request->query->replace(['score_center'=>1,'score_room'=>2,'score_student'=>$candidate->id]);
    $response=app(ScholarshipController::class)->saveScores($request,$exam,$candidate);
    parse_str(parse_url($response->getTargetUrl(),PHP_URL_QUERY),$query);
    expect($query)->toMatchArray(['tab'=>'scoring','score_center'=>'1','score_room'=>'2','score_student'=>(string)$candidate->id]);
    expect(app(ScholarshipWorkflow::class)->calculate($exam->fresh(),$candidate->fresh())['total'])->toBe(400.0);
    $exam->update(['published_at'=>now()]);
    $data=app(ScholarshipController::class)->index(scholarshipScoringRequest(['exam_id'=>$exam->id,'score_center'=>1,'score_room'=>1]))->getData();
    $html=view('western-scholarship._scoring',$data)->render();
    expect($html)->toContain('Scores are locked.','readonly')->not->toContain('>Save Draft<','>Mark Reviewed<');
    expect(fn()=>app(ScholarshipController::class)->saveScores(scholarshipScoringRequest(['version'=>$candidate->fresh()->version,'scores'=>$scores],true,'POST'),$exam,$candidate->fresh()))->toThrow(ValidationException::class);
});
