<?php

use App\Http\Controllers\ScholarshipController;
use App\Models\User;
use App\Support\ScholarshipResultFilters;
use App\Services\ScholarshipWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';

beforeEach(fn()=>createScholarshipSchema());

function scholarshipResultRequest(array $params=[]): Request
{
    $user=Mockery::mock(User::class)->makePartial();$user->id=1;$user->active_campus_id=1;
    $user->shouldReceive('isSuperAdmin')->andReturnTrue();Auth::setUser($user);
    $request=Request::create('/students/western-scholarship','GET',['tab'=>'result']+$params);
    $request->setUserResolver(fn()=>$user);$request->setLaravelSession(app('session')->driver());
    app()->instance('request',$request);
    return $request;
}

it('filters results by center grade and student with numeric seat ordering',function(){
    $exam=scholarshipSeatReportExam(12);$controller=app(ScholarshipController::class);
    $params=['exam_id'=>$exam->id,'result_center'=>1,'result_grade'=>1];
    $data=$controller->index(scholarshipResultRequest($params))->getData();
    expect($data['results'])->toHaveCount(12)
        ->and($data['resultFilterOptions']['centers']->pluck('label')->all())->toBe(['North Exam Center','South Exam Center'])
        ->and($data['resultFilterOptions']['grades']->pluck('id')->all())->toBe([1])
        ->and($data['resultFilterOptions']['students']->pluck('label')->take(3)->all())->toBe(['1 - NORTH STUDENT 01','2 - NORTH STUDENT 02','3 - NORTH STUDENT 03'])
        ->and($data['resultFilterOptions']['students']->last()['label'])->toBe('12 - NORTH STUDENT 12');
    $id=$data['results']->last()['candidate']->id;
    $selected=$controller->index(scholarshipResultRequest($params+['result_student'=>$id]))->getData();
    expect($selected['results'])->toHaveCount(1)->and($selected['results'][0]['candidate']->id)->toBe($id)
        ->and($selected['awardGrades'][1])->toHaveCount(12);
    expect(view('western-scholarship._result-filters',$selected)->render())->toContain('Datas Filter','data-result-filters-toggle','Seat &amp; Student Name English');
});

it('does not recalculate ranks after narrowing results',function(){
    $candidate=fn($id,$seat)=>(object)['id'=>$id,'exam_center_id'=>1,'exam_center_name'=>'North','grade_number'=>1,'room_seat'=>$seat,'snapshot'=>['name_en'=>'STUDENT '.$id]];
    $rows=collect([['candidate'=>$candidate(1,10),'rank'=>1],['candidate'=>$candidate(2,2),'rank'=>3]]);
    $filtered=app(ScholarshipResultFilters::class)->apply(scholarshipResultRequest(['result_center'=>1,'result_grade'=>1,'result_student'=>2]),$rows);
    expect($filtered['rows'][0]['rank'])->toBe(3)->and($filtered['options']['students']->pluck('id')->all())->toBe([2,1]);
});

it('rejects result filters outside their parent center and grade',function($filters){
    $exam=scholarshipSeatReportExam(2);
    expect(fn()=>app(ScholarshipController::class)->index(scholarshipResultRequest(['exam_id'=>$exam->id]+$filters)))->toThrow(ValidationException::class);
})->with([
    [['result_center'=>999]],
    [['result_grade'=>3]],
    [['result_center'=>1,'result_grade'=>2]],
    [['result_student'=>1]],
    [['result_center'=>2,'result_grade'=>2,'result_student'=>1]],
]);

it('builds result options only from authorized rows',function(){
    $exam=scholarshipSeatReportExam(2);
    $rows=app(\App\Services\ScholarshipWorkflow::class)->results($exam)->filter(fn($row)=>$row['candidate']->campus_id===1);
    $filtered=app(ScholarshipResultFilters::class)->apply(scholarshipResultRequest(),$rows);
    expect($filtered['options']['centers']->pluck('id')->all())->toBe([1])->and($filtered['options']['grades']->pluck('id')->all())->toBe([1])->and($filtered['rows'])->toHaveCount(2);
    expect(fn()=>app(ScholarshipResultFilters::class)->apply(scholarshipResultRequest(['result_center'=>2]),$rows))->toThrow(ValidationException::class);
    expect(fn()=>app(ScholarshipResultFilters::class)->apply(scholarshipResultRequest(['result_grade'=>2]),$rows))->toThrow(ValidationException::class);
});

it('selects a grade and student across all exam centers including published results',function(){
    $exam=scholarshipSeatReportExam(2);$south=$exam->candidates()->where('exam_center_id',2)->first();
    $south->update(['grade_number'=>1]);$exam->update(['published_at'=>now()]);
    $controller=app(ScholarshipController::class);$params=['exam_id'=>$exam->id,'result_center'=>'','result_grade'=>1];
    $data=$controller->index(scholarshipResultRequest($params))->getData();
    expect($data['resultFilters']['center'])->toBeNull()
        ->and($data['resultFilterOptions']['grades']->pluck('id')->all())->toBe([1,2])
        ->and($data['results'])->toHaveCount(3)
        ->and($data['results']->pluck('candidate.exam_center_id')->unique()->sort()->values()->all())->toBe([1,2])
        ->and($data['results']->pluck('candidate.grade_number')->unique()->all())->toBe([1])
        ->and($data['awardGrades'][1])->toHaveCount(3)
        ->and($data['resultFilterOptions']['students']->pluck('id')->all())->toContain($south->id);
    $html=view('western-scholarship._result-filters',$data)->render();$dom=new DOMDocument();@$dom->loadHTML($html);
    expect((new DOMXPath($dom))->query('//select[@id="resultFilterGrade"][@disabled]')->length)->toBe(0);
    $selected=$controller->index(scholarshipResultRequest($params+['result_student'=>$south->id]))->getData();
    expect($selected['results'])->toHaveCount(1)->and($selected['results'][0]['candidate']->id)->toBe($south->id);
});

it('selects result academic years from configured exams with candidates',function(){
    $first=scholarshipSeatReportExam(2);$next=$first->replicate();$next->academic_year_id=2;$next->name='2026-2027';$next->save();
    $center=$next->centers()->create(['name'=>'Next Year Center','position'=>0]);
    $copy=$first->candidates()->first()->replicate();$copy->exam_id=$next->id;$copy->exam_center_id=$center->id;$copy->exam_center_name=$center->name;$copy->save();
    $controller=app(ScholarshipController::class);
    $data=$controller->index(scholarshipResultRequest(['academic_year_id'=>2,'exam_id'=>$first->id]))->getData();
    expect($data['exam']->id)->toBe($next->id)->and($data['academicYears']->pluck('id')->all())->toBe([2,1])
        ->and($data['resultFilterOptions']['centers']->pluck('label')->all())->toBe(['Next Year Center']);
    $copy->delete();$next->delete();
    $empty=$controller->index(scholarshipResultRequest(['academic_year_id'=>2]))->getData();
    expect($empty['exam'])->toBeNull()->and($empty['academicYears']->pluck('id')->all())->toBe([1])
        ->and($empty['resultFilterOptions']['centers'])->toBeEmpty();
    expect(view('western-scholarship._result-filters',$empty)->render())->toContain('Datas Filter');
});

function scholarshipReviewedResultExam(): \App\Models\ScholarshipExam
{
    $exam=scholarshipSeatReportExam(3);$workflow=app(ScholarshipWorkflow::class);
    $request=scholarshipResultRequest();
    foreach($exam->candidates()->get() as $candidate){
        $scores=$workflow->applicableSubjects($exam,$candidate->grade_number)->mapWithKeys(fn($subject)=>[$subject->id=>90])->all();
        $workflow->saveScores($request->user(),$exam,$candidate,$scores,false,$candidate->version);
        $workflow->review($request->user(),$exam,$candidate->fresh(),$candidate->fresh()->version);
    }
    return $exam->fresh();
}

it('renders grade subject columns student details and row award selections',function(){
    $exam=scholarshipReviewedResultExam();$candidate=$exam->candidates()->first();
    $candidate->update(['student_id'=>1,'student_enrollment_id'=>1,'snapshot'=>array_replace($candidate->snapshot,['eoy_average'=>89.5])]);
    \Illuminate\Support\Facades\DB::table('tb_student')->where('id',1)->update(['photo_path'=>'testing/result-photo.jpg']);
    $view=app(ScholarshipController::class)->index(scholarshipResultRequest(['exam_id'=>$exam->id]));
    $data=$view->getData();$html=view('western-scholarship._result',$data)->render();
    $dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
    expect($xpath->query('//tr[@data-result-candidate]')->length)->toBe(5)
        ->and($xpath->query('//tr[@data-result-candidate="'.$candidate->id.'"]//img[@width="600"][@height="800"]')->length)->toBe(1)
        ->and($xpath->query('//section[@data-result-grade="1"]//button[@data-result-sort]')->length)->toBe(7)
        ->and($xpath->query('//section[@data-result-grade="1"]//select[@data-result-award-select]/option')->length)->toBe(27)
        ->and($xpath->query('//form[@data-result-award-form]')->length)->toBe(2);
    expect($html)->toContain('Examinations','Code:','Exam Scores','1st Place','2nd Place','3rd Place','Best in English','data-result-award-percent','EOY Avg.:','89.50');
    expect($html)->not->toContain('Scholarship Winners</h3>');
});

it('saves result row awards as a complete grade with a new version and success message',function(){
    $exam=scholarshipReviewedResultExam();$slots=app(ScholarshipWorkflow::class)->awardSlots($exam,1);$ids=$exam->candidates()->where('grade_number',1)->pluck('id');
    $choices=$slots->mapWithKeys(fn($slot,$index)=>[$slot['key']=>['candidate_id'=>$ids[$index]??null,'award_percent'=>$slot['percent'],'reason'=>null]])->all();
    $request=scholarshipResultRequest();$request->setMethod('POST');$request->headers->set('Accept','application/json');$request->request->replace(['version'=>$exam->version,'awards'=>$choices]);
    $response=app(ScholarshipController::class)->saveAwards($request,$exam,1);$body=$response->getData(true);
    expect($body['message'])->toBe('Scholarship winners saved for Grade 1.')->and($body['version'])->toBe($exam->fresh()->version)
        ->and($body['version'])->toBeGreaterThan($exam->version)->and($exam->awards()->count())->toBe(8);
    $data=app(ScholarshipController::class)->index(scholarshipResultRequest(['exam_id'=>$exam->id,'result_center'=>1,'result_grade'=>1,'result_student'=>$ids[0]]))->getData();
    $html=view('western-scholarship._result-list',$data)->render();$dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
    expect($xpath->query('//tr[@data-result-candidate]')->length)->toBe(1)
        ->and($xpath->query('//input[@data-slot-candidate][@value="'.$ids[1].'"]')->length)->toBe(1)
        ->and($xpath->query('//select[@data-result-award-select]/option[@selected]')->item(0)->getAttribute('value'))->toBe('total_1')
        ->and($xpath->query('//select[@data-result-award-select][contains(@class,"is-award-assigned")]')->length)->toBe(1);
    expect(fn()=>app(ScholarshipController::class)->saveAwards($request,$exam,1))->toThrow(ValidationException::class);
});

it('locks row award controls for published exams',function(){
    $exam=scholarshipReviewedResultExam();$exam->update(['published_at'=>now()]);
    $data=app(ScholarshipController::class)->index(scholarshipResultRequest(['exam_id'=>$exam->id]))->getData();
    $html=view('western-scholarship._result-list',$data)->render();$dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
    expect($xpath->query('//select[@data-result-award-select][not(@disabled)]')->length)->toBe(0)
        ->and($xpath->query('//form[@data-result-award-form]')->length)->toBe(0);
});

it('shows subject ranks across the complete grade with ties zero and filtered students',function(){
    $exam=scholarshipSeatReportExam(5);$subject=$exam->subjects()->first();
    $north=$exam->candidates()->where('grade_number',1)->orderBy('id')->get();
    foreach([95,95,80,0,null] as $index=>$score)$north[$index]->scores()->create(['subject_id'=>$subject->id,'score'=>$score]);
    $south=$exam->candidates()->where('grade_number',2)->first();
    $south->scores()->create(['subject_id'=>$subject->id,'score'=>100]);
    $absent=$north[0]->replicate();$absent->room_seat=6;$absent->grade_exam_number=6;$absent->absent=true;$absent->save();
    $absent->scores()->create(['subject_id'=>$subject->id,'score'=>100]);
    $params=['exam_id'=>$exam->id,'result_center'=>1,'result_grade'=>1,'result_student'=>$north[2]->id];
    $data=app(ScholarshipController::class)->index(scholarshipResultRequest($params))->getData();
    expect($data['subjectRanks'][$north[0]->id][$subject->id])->toBe(1)
        ->and($data['subjectRanks'][$north[1]->id][$subject->id])->toBe(1)
        ->and($data['subjectRanks'][$north[2]->id][$subject->id])->toBe(3)
        ->and($data['subjectRanks'][$north[3]->id][$subject->id])->toBe(4)
        ->and($data['subjectRanks'][$north[4]->id][$subject->id]??null)->toBeNull()
        ->and($data['subjectRanks'][$absent->id][$subject->id]??null)->toBeNull()
        ->and($data['subjectRanks'][$south->id][$subject->id])->toBe(1);
    $html=view('western-scholarship._result-list',$data)->render();$dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
    expect(trim($xpath->query('//td[@data-result-score="'.$subject->id.'"]//*[@data-result-subject-rank]')->item(0)->textContent))->toBe('Rank: 3')
        ->and($xpath->query('//tr[@data-result-candidate]')->length)->toBe(1);
});
