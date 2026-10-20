<?php

use App\Http\Controllers\ScholarshipController;
use App\Models\{ScholarshipExam,User};
use App\Services\ScholarshipWorkflow;
use App\Support\ScholarshipSubjectRanks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';

beforeEach(function(){
    createScholarshipSchema();
    $this->user=Mockery::mock(User::class)->makePartial();$this->user->id=1;$this->user->active_campus_id=1;
    $this->user->shouldReceive('isSuperAdmin')->andReturnTrue();Auth::setUser($this->user);
    $this->workflow=app(ScholarshipWorkflow::class);
    $this->exam=ScholarshipExam::create(['academic_year_id'=>1,'exam_date'=>'2026-11-20','name'=>'2025-2026','room_capacity'=>25,'campus_ids'=>[1],'eligible_ranks'=>[],'seats_locked_at'=>now()]);
    foreach([['English',100,1],['Math',50,3]] as $i=>[$name,$max,$weight])$this->exam->subjects()->create(['name'=>$name,'grade_from'=>1,'grade_to'=>12,'max_score'=>$max,'weight'=>$weight,'position'=>$i]);
    $this->subjects=$this->exam->subjects()->get();
    $this->candidate=$this->exam->candidates()->create(['campus_id'=>1,'class_id'=>1,'grade_number'=>1,'class_rank'=>1,'room_number'=>1,'room_seat'=>1,'grade_exam_number'=>1,'candidate_code'=>'2526-G01-001','snapshot'=>['student_code'=>'A001','name_en'=>'ATTENDANCE STUDENT','name_kh'=>'សិស្ស','campus'=>'BCH','class'=>'1A / III']]);
});

function attendanceRequest($test,array $params=[],string $method='GET'): Request
{
    $request=Request::create('/students/western-scholarship',$method,$params);
    $request->setUserResolver(fn()=>$test->user);$request->setLaravelSession(app('session')->driver());app()->instance('request',$request);
    return $request;
}

it('stores subject absences as zero without clearing attended subject scores and computes weighted totals',function(){
    [$english,$math]=$this->subjects;
    $this->workflow->saveScores($this->user,$this->exam,$this->candidate,[$english->id=>80,$math->id=>40],false,1);
    $this->workflow->saveScores($this->user,$this->exam,$this->candidate->fresh(),[$english->id=>80,$math->id=>40],false,2,false,[$english->id=>1,$math->id=>0]);
    $candidate=$this->candidate->fresh();$calculation=$this->workflow->calculate($this->exam,$candidate);
    expect($candidate->absent)->toBeFalse()->and($candidate->scores->firstWhere('subject_id',$english->id)->score)->toBe(0.0)
        ->and($candidate->scores->firstWhere('subject_id',$english->id)->is_absent)->toBeTrue()
        ->and($candidate->scores->firstWhere('subject_id',$math->id)->score)->toBe(40.0)
        ->and($calculation['total'])->toBe(40.0)->and($calculation['percentage'])->toBe(60.0)
        ->and($calculation['complete'])->toBeTrue()->and($calculation['overall_eligible'])->toBeFalse();
    $event=$this->exam->events()->where('action','scores-saved')->first()->details;
    expect($event['before'][$english->id])->toEqual(80)->and($event['subject_absences'][$english->id])->toBeTrue();
});

it('marks all subjects absent with zero totals while keeping the candidate in scoring and result lists',function(){
    $this->workflow->saveScores($this->user,$this->exam,$this->candidate,[],true,1);
    $this->workflow->review($this->user,$this->exam,$this->candidate->fresh(),2);
    $result=$this->workflow->results($this->exam)->first();
    expect($result['total'])->toBe(0.0)->and($result['percentage'])->toBe(0.0)->and($result['rank'])->toBeNull()->and($result['all_absent'])->toBeTrue();
    $controller=app(ScholarshipController::class);
    $data=$controller->index(attendanceRequest($this,['tab'=>'scoring','exam_id'=>$this->exam->id,'score_center'=>0,'score_room'=>1]))->getData();
    expect($data['scoringCandidates'])->toHaveCount(1);
    $html=view('western-scholarship._scoring',$data)->render();
    expect($html)->toContain('scholarship-score-absent-letter','Absent All Subjects','data-subject-absent="true"');
    $data=$controller->index(attendanceRequest($this,['tab'=>'result','exam_id'=>$this->exam->id]))->getData();
    expect($data['results'])->toHaveCount(1)->and(view('western-scholarship._result-list',$data)->render())->toContain('Absent — All Subjects','data-sort-value="0"');
});

it('requires score entry after a subject is changed back to present and keeps absence distinct from earned zero',function(){
    [$english,$math]=$this->subjects;
    $this->workflow->saveScores($this->user,$this->exam,$this->candidate,[$math->id=>0],false,1,false,[$english->id=>1]);
    $this->workflow->review($this->user,$this->exam,$this->candidate->fresh(),2);
    $this->workflow->saveScores($this->user,$this->exam,$this->candidate->fresh(),[$english->id=>null,$math->id=>0],false,3,true,[$english->id=>0]);
    $candidate=$this->candidate->fresh();
    expect($candidate->reviewed_at)->toBeNull()->and($candidate->scores->firstWhere('subject_id',$english->id)->score)->toBeNull()
        ->and($this->workflow->calculate($this->exam,$candidate)['complete'])->toBeFalse();
    expect(fn()=>$this->workflow->review($this->user,$this->exam,$candidate,$candidate->version))->toThrow(ValidationException::class);
    $this->workflow->saveScores($this->user,$this->exam,$candidate,[$english->id=>0,$math->id=>0],false,$candidate->version,false,[$english->id=>0]);
    $ranks=app(ScholarshipSubjectRanks::class)->forResults($this->workflow->results($this->exam));
    expect($ranks[$candidate->id][$english->id])->toBe(1)->and($this->candidate->fresh()->absent)->toBeFalse();
});

it('derives whole exam absence when every subject is marked absent and preserves unchanged reviews',function(){
    $absences=$this->subjects->mapWithKeys(fn($subject)=>[$subject->id=>1])->all();
    $this->workflow->saveScores($this->user,$this->exam,$this->candidate,[],false,1,false,$absences);
    expect($this->candidate->fresh()->absent)->toBeTrue();
    $this->workflow->review($this->user,$this->exam,$this->candidate->fresh(),2);
    $before=$this->candidate->fresh();
    $this->workflow->saveScoreDrafts($this->user,$this->exam,[$before->id=>['version'=>$before->version,'scores'=>$this->subjects->mapWithKeys(fn($subject)=>[$subject->id=>0])->all(),'subject_absences'=>$absences]]);
    expect($this->candidate->fresh()->version)->toBe($before->version)->and($this->candidate->fresh()->reviewed_at->equalTo($before->reviewed_at))->toBeTrue();
});

it('rejects invalid subject attendance atomically and supports packed controller submissions',function(){
    $subject=$this->subjects[0];$candidate=$this->candidate;
    expect(fn()=>$this->workflow->saveScores($this->user,$this->exam,$candidate,[],false,1,false,[999=>1]))->toThrow(ValidationException::class);
    expect(fn()=>$this->workflow->saveScores($this->user,$this->exam,$candidate,[],false,1,false,[$subject->id=>'A']))->toThrow(ValidationException::class);
    expect($candidate->scores()->count())->toBe(0)->and($candidate->fresh()->version)->toBe(1);
    $request=attendanceRequest($this,['score_drafts'=>json_encode([$candidate->id=>['version'=>1,'scores'=>[$this->subjects[1]->id=>40],'subject_absences'=>[$subject->id=>1]]])],'POST');
    app(ScholarshipController::class)->saveScoreDrafts($request,$this->exam);
    expect($candidate->scores()->where('subject_id',$subject->id)->first()->is_absent)->toBeTrue();
});

it('permits a partially absent student only in an attended subject award and publishes all students',function(){
    [$english,$math]=$this->subjects;
    $this->workflow->saveScores($this->user,$this->exam,$this->candidate,[$english->id=>90],false,1,false,[$math->id=>1]);
    $this->workflow->review($this->user,$this->exam,$this->candidate->fresh(),2);
    $exam=$this->exam->fresh();$slots=$this->workflow->awardSlots($exam,1);
    $choices=$slots->mapWithKeys(fn($slot)=>[$slot['key']=>['candidate_id'=>$slot['subject_id']===$english->id?$this->candidate->id:null,'award_percent'=>$slot['percent'],'reason'=>null]])->all();
    $bad=$choices;$bad['total_1']['candidate_id']=$this->candidate->id;
    expect(fn()=>$this->workflow->saveAwards($this->user,$exam,1,$bad,$exam->version))->toThrow(ValidationException::class);
    $bad=$choices;$bad['subject_'.$english->id]['candidate_id']=null;$bad['subject_'.$math->id]['candidate_id']=$this->candidate->id;
    expect(fn()=>$this->workflow->saveAwards($this->user,$exam,1,$bad,$exam->version))->toThrow(ValidationException::class);
    $this->workflow->saveAwards($this->user,$exam,1,$choices,$exam->version);$exam=$exam->fresh();
    $this->workflow->publish($this->user,$exam,$exam->version);
    $row=$this->workflow->results($exam->fresh())->first();
    expect($row['total'])->toBe(90.0)->and($row['rank'])->toBeNull()->and($row['award'])->toBe(25.0)
        ->and($exam->candidates()->count())->toBe(1)->and($this->candidate->fresh()->result_total)->toBe(90.0);
});
