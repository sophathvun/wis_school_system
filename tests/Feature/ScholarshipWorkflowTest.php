<?php

use App\Models\{Grade, ScholarshipCandidate, ScholarshipCommittee, ScholarshipExam, ScholarshipScore, ScholarshipStudentRank, SchoolInfo, User};
use App\Services\ScholarshipWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';

beforeEach(function () {
    createScholarshipSchema();
    DB::table('tb_grade')->where('id',1)->update(['grade'=>'Grade 1','grade_order'=>1]);
    DB::table('tb_grade')->where('id',2)->update(['grade'=>'Grade 2','grade_order'=>2]);
    $this->admin=Mockery::mock(User::class)->makePartial();$this->admin->id=1;
    $this->admin->shouldReceive('isSuperAdmin')->andReturnTrue();$this->admin->shouldReceive('canAccessCampus')->andReturnTrue();
    $this->admin->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>SchoolInfo::query());
    $this->workflow=app(ScholarshipWorkflow::class);
});

function scholarshipExamData(array $changes=[]): array
{
    return array_replace(['academic_year_id'=>1,'exam_date'=>'2026-11-20','room_capacity'=>25,'version'=>1,'subjects'=>[
        ['name'=>'Primary Subject A','grade_from'=>1,'grade_to'=>6,'max_score'=>100,'weight'=>1],
        ['name'=>'Primary Subject B','grade_from'=>1,'grade_to'=>6,'max_score'=>50,'weight'=>3],
        ['name'=>'Secondary Subject A','grade_from'=>7,'grade_to'=>12,'max_score'=>100,'weight'=>1],
        ['name'=>'Secondary Subject B','grade_from'=>7,'grade_to'=>12,'max_score'=>100,'weight'=>1],
    ]],$changes);
}

function scholarshipCandidate(ScholarshipExam $exam, string $name='STUDENT A', int $grade=1, int $campus=1): ScholarshipCandidate
{
    return $exam->candidates()->create(['campus_id'=>$campus,'class_id'=>1,'grade_number'=>$grade,'class_rank'=>1,
        'snapshot'=>['student_code'=>'S-'.$name,'name_en'=>$name,'name_kh'=>'សិស្ស','campus'=>$campus===1?'BCH':'BKK','class'=>'A']]);
}

function scholarshipCenterData(array $changes=[]): array
{
    return scholarshipExamData(array_replace(['exam_centers'=>[
        ['key'=>'north','name'=>'North Exam Center'],['key'=>'south','name'=>'South Exam Center'],
    ],'center_assignments'=>[1=>'north',2=>'south']],$changes));
}

it('saves multiple exam centers with one assignment per ranked campus and updates them without losing identities',function(){
    foreach([1,3] as $enrollment)$this->workflow->saveRank($this->admin,\App\Models\StudentEnrollment::findOrFail($enrollment),1,95);
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipCenterData());
    $centers=$exam->centers()->with('campuses')->get();
    expect($centers->pluck('name')->all())->toBe(['North Exam Center','South Exam Center'])
        ->and($centers[0]->campuses->modelKeys())->toBe([1])->and($centers[1]->campuses->modelKeys())->toBe([2]);
    $exam=$this->workflow->saveExam($this->admin,$exam,scholarshipCenterData(['version'=>$exam->version,'exam_centers'=>[
        ['id'=>$centers[1]->id,'key'=>'south','name'=>'Combined Center'],
    ],'center_assignments'=>[1=>'south',2=>'south']]));
    expect($exam->centers()->count())->toBe(1)->and($exam->centers()->first()->id)->toBe($centers[1]->id)
        ->and($exam->centers()->first()->campuses->modelKeys())->toBe([1,2])
        ->and($exam->events()->first()->details['exam_centers'][0]['name'])->toBe('Combined Center');
});

it('rejects invalid center names identifiers and campus assignments atomically',function($changes){
    foreach([1,3] as $enrollment)$this->workflow->saveRank($this->admin,\App\Models\StudentEnrollment::findOrFail($enrollment),1,95);
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipCenterData());
    expect(fn()=>$this->workflow->saveExam($this->admin,$exam,scholarshipCenterData($changes+['version'=>$exam->version])))->toThrow(ValidationException::class);
    expect($exam->fresh()->version)->toBe(1)->and($exam->centers()->pluck('name')->all())->toBe(['North Exam Center','South Exam Center'])
        ->and(DB::table('tb_scholarship_center_campus')->count())->toBe(2)->and($exam->subjects()->count())->toBe(4);
})->with([
    'missing campus'=>[['center_assignments'=>[1=>'north']]],
    'unranked campus'=>[['center_assignments'=>[1=>'north',2=>'south',3=>'north']]],
    'unknown center key'=>[['center_assignments'=>[1=>'north',2=>'missing']]],
    'duplicate names'=>[['exam_centers'=>[['key'=>'north','name'=>'Same'],['key'=>'south','name'=>' same ']]]],
    'blank name'=>[['exam_centers'=>[['key'=>'north','name'=>' '],['key'=>'south','name'=>'South']]]],
    'foreign center id'=>[['exam_centers'=>[['id'=>999,'key'=>'north','name'=>'North'],['key'=>'south','name'=>'South']]]],
    'duplicate keys'=>[['exam_centers'=>[['key'=>'north','name'=>'North'],['key'=>'north','name'=>'South']]]],
    'no centers'=>[['exam_centers'=>[]]],
]);

it('keeps rooms separate by center and grade while preserving global grade exam numbers and saved center snapshots',function(){
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipCenterData(['center_assignments'=>[]]));
    $centers=$exam->centers()->get();$centers[0]->campuses()->attach(1,['exam_id'=>$exam->id]);$centers[1]->campuses()->attach(2,['exam_id'=>$exam->id]);
    foreach([[2,'ALICE',1],[1,'BOB',1],[1,'CAROL',1],[1,'DAN',1],[1,'AARON',2],[2,'ZOE',2]] as [$campus,$name,$grade])scholarshipCandidate($exam,$name,$grade,$campus);
    $exam->forceFill(['room_capacity'=>2])->save();$plan=$this->workflow->plan($exam);
    expect(array_column($plan['rows'],'room_number'))->toBe([1,1,2,3,1,2])
        ->and(array_column($plan['rows'],'room_seat'))->toBe([1,2,3,4,1,2])
        ->and(array_column($plan['rows'],'grade_exam_number'))->toBe([2,3,4,1,1,2])
        ->and(array_column($plan['rows'],'exam_center_name'))->toBe(['North Exam Center','North Exam Center','North Exam Center','North Exam Center','South Exam Center','South Exam Center']);
    foreach(collect($plan['rows'])->groupBy(fn($r)=>$r['exam_center_id'].':'.$r['room_number']) as $room)expect($room->pluck('exam_center_id')->unique())->toHaveCount(1)->and($room->pluck('grade_number')->unique())->toHaveCount(1);
    $this->workflow->lockPlan($this->admin,$exam,$plan['token']);$exam=$exam->fresh();
    expect($exam->candidates()->whereNotNull('exam_center_id')->count())->toBe(6)->and($exam->candidates()->where('exam_center_name','South Exam Center')->count())->toBe(2);
    expect(fn()=>$this->workflow->saveExam($this->admin,$exam,scholarshipCenterData(['version'=>$exam->version,'center_assignments'=>[]])))->toThrow(ValidationException::class);
    $this->workflow->reopenPlan($this->admin,$exam,$exam->version,'Adjust exam center assignments.');
    expect($exam->candidates()->whereNotNull('exam_center_id')->count())->toBe(0)->and($exam->candidates()->whereNotNull('exam_center_name')->count())->toBe(0);
});

it('requires a new participating campus to be assigned before confirming seats and rejects the old preview',function(){
    $this->workflow->saveRank($this->admin,\App\Models\StudentEnrollment::findOrFail(1),1,95);
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipCenterData(['center_assignments'=>[1=>'north']]));$old=$this->workflow->plan($exam);
    $this->workflow->saveRank($this->admin,\App\Models\StudentEnrollment::findOrFail(3),1,95);$exam=$exam->fresh();
    $plan=$this->workflow->plan($exam);expect($plan['unassigned_campuses'])->toBe(['BKK']);
    expect(fn()=>$this->workflow->lockPlan($this->admin,$exam,$plan['token']))->toThrow(ValidationException::class);
    $centers=$exam->centers()->get();$data=scholarshipCenterData(['version'=>$exam->version,'exam_centers'=>$centers->map(fn($c,$i)=>['id'=>$c->id,'key'=>$i?'south':'north','name'=>$c->name])->all()]);
    $exam=$this->workflow->saveExam($this->admin,$exam,$data);
    expect($this->workflow->plan($exam)['unassigned_campuses'])->toBe([]);
    expect(fn()=>$this->workflow->lockPlan($this->admin,$exam,$old['token']))->toThrow(ValidationException::class);
    $this->workflow->lockPlan($this->admin,$exam,$this->workflow->plan($exam)['token']);
    expect($exam->candidates()->whereNotNull('exam_center_id')->count())->toBe(2);
});

function scholarshipLockedExam($test, int $count=1, int $year=1): ScholarshipExam
{
    $exam=$test->workflow->saveExam($test->admin,null,scholarshipExamData(['academic_year_id'=>$year]));
    for($i=0;$i<$count;$i++) scholarshipCandidate($exam,'STUDENT '.chr(65+$i));
    $test->workflow->lockPlan($test->admin,$exam,$test->workflow->plan($exam)['token']);
    return $exam->fresh(['subjects']);
}

function scholarshipChoices($test, ScholarshipExam $exam, int $grade=1): array
{
    $remaining=$test->workflow->results($exam)->filter(fn($r)=>$r['candidate']->grade_number===$grade && !$r['candidate']->absent && $r['complete'] && $r['candidate']->reviewed_at);
    $choices=[];
    foreach($test->workflow->awardSlots($exam,$grade) as $slot) {
        $best=$remaining->sortByDesc(fn($r)=>$slot['subject_id']?$r['candidate']->scores->firstWhere('subject_id',$slot['subject_id'])->score:$r['total'])->first();
        $choices[$slot['key']]=['candidate_id'=>$best['candidate']->id??null,'award_percent'=>$slot['percent'],'reason'=>null];
        if($best)$remaining=$remaining->reject(fn($r)=>$r['candidate']->id===$best['candidate']->id);
    }
    return $choices;
}

function scholarshipAwardExam($test, bool $ties=false): ScholarshipExam
{
    $subjects=collect(['Khmer','Mathematics','English','Science'])->map(fn($name)=>['name'=>$name,'grade_from'=>1,'grade_to'=>12,'max_score'=>100,'weight'=>1])->all();
    $exam=$test->workflow->saveExam($test->admin,null,scholarshipExamData(['subjects'=>$subjects]));
    foreach(range(0,7) as $i)scholarshipCandidate($exam,'STUDENT '.chr(65+$i),1,$i%2+1);
    $test->workflow->lockPlan($test->admin,$exam,$test->workflow->plan($exam)['token']);$exam=$exam->fresh();
    $marks=[[100,100,100,100],[99,99,99,99],[98,98,98,98],[97,60,60,60],[60,97,60,60],[60,60,97,60],[60,60,60,97],[50,50,50,50]];
    foreach($exam->candidates()->orderBy('id')->get() as $i=>$candidate) {
        $values=$ties && $i<3?[100,100,100,100]:$marks[$i];
        $scores=$exam->subjects->values()->mapWithKeys(fn($subject,$index)=>[$subject->id=>$values[$index]])->all();
        $test->workflow->saveScores($test->admin,$exam,$candidate,$scores,false,1);
        $test->workflow->review($test->admin,$exam,$candidate->fresh(),2);
    }
    return $exam->fresh();
}

it('selects seven distinct winners per grade with one award per subject and persists them on publication',function(){
    $exam=scholarshipAwardExam($this);$choices=scholarshipChoices($this,$exam);
    expect($choices)->toHaveCount(7)->and(array_column($choices,'award_percent'))->toEqual([100,75,50,25,25,25,25]);
    $this->workflow->saveAwards($this->admin,$exam,1,$choices,$exam->version);$exam=$exam->fresh();
    expect($exam->awards()->whereNotNull('candidate_id')->count())->toBe(7)
        ->and($exam->awards()->pluck('candidate_id')->unique())->toHaveCount(7);
    $rows=$this->workflow->results($exam);
    expect($rows->whereNotNull('award')->pluck('candidate.snapshot.name_en')->values()->all())->toBe(['STUDENT A','STUDENT B','STUDENT C','STUDENT D','STUDENT E','STUDENT F','STUDENT G'])
        ->and($rows->last()['award'])->toBeNull();
    $this->workflow->publish($this->admin,$exam,$exam->version);
    expect($exam->fresh()->published_at)->not->toBeNull()->and($exam->candidates()->whereNotNull('award_percent')->count())->toBe(7);
});

it('allows choosing one of three tied total scores and editing percentages and winners without duplicate awards',function(){
    $exam=scholarshipAwardExam($this,true);$choices=scholarshipChoices($this,$exam);$ids=$exam->candidates()->orderBy('id')->pluck('id');
    [$choices['total_1']['candidate_id'],$choices['total_2']['candidate_id'],$choices['total_3']['candidate_id']]=[$ids[2],$ids[0],$ids[1]];
    $this->workflow->saveAwards($this->admin,$exam,1,$choices,$exam->version);$exam=$exam->fresh();
    expect($exam->awards()->where('slot','total_1')->value('candidate_id'))->toBe($ids[2]);
    $choices['total_1']['candidate_id']=$ids[0];$choices['total_2']['candidate_id']=$ids[2];$choices['total_2']['award_percent']=80;
    $this->workflow->saveAwards($this->admin,$exam,1,$choices,$exam->version);
    expect($exam->awards()->where('slot','total_2')->value('award_percent'))->toEqual(80)
        ->and($exam->events()->where('action','awards-selected')->count())->toBe(2);
    expect(fn()=>$this->workflow->saveAwards($this->admin,$exam,1,$choices,$exam->version))->toThrow(ValidationException::class);
});

it('rejects duplicate winners, omitted slots, avoidable vacancies, and undocumented lower-score choices',function(){
    $exam=scholarshipAwardExam($this);$choices=scholarshipChoices($this,$exam);
    $invalid=$choices;$invalid['total_2']['candidate_id']=$invalid['total_1']['candidate_id'];
    expect(fn()=>$this->workflow->saveAwards($this->admin,$exam,1,$invalid,$exam->version))->toThrow(ValidationException::class);
    $invalid=$choices;unset($invalid['total_3']);
    expect(fn()=>$this->workflow->saveAwards($this->admin,$exam,1,$invalid,$exam->version))->toThrow(ValidationException::class);
    $invalid=$choices;$invalid['total_3']['candidate_id']=null;
    expect(fn()=>$this->workflow->saveAwards($this->admin,$exam,1,$invalid,$exam->version))->toThrow(ValidationException::class);
    $invalid=$choices;[$invalid['total_1']['candidate_id'],$invalid['total_2']['candidate_id']]=[$choices['total_2']['candidate_id'],$choices['total_1']['candidate_id']];
    expect(fn()=>$this->workflow->saveAwards($this->admin,$exam,1,$invalid,$exam->version))->toThrow(ValidationException::class);
    expect($exam->awards()->count())->toBe(0);
    $invalid['total_1']['reason']='Committee approved after the tie-break assessment.';
    $this->workflow->saveAwards($this->admin,$exam,1,$invalid,$exam->version);
    expect($exam->awards()->where('slot','total_1')->value('reason'))->toBe($invalid['total_1']['reason']);
});

it('uses raw total score for overall awards even when weighted percentage gives a different order',function(){
    $exam=scholarshipLockedExam($this,2);$candidates=$exam->candidates()->orderBy('id')->get();$subjects=$exam->subjects->take(2);
    foreach([[100,0],[20,40]] as $i=>$marks) {
        $this->workflow->saveScores($this->admin,$exam,$candidates[$i],[$subjects[0]->id=>$marks[0],$subjects[1]->id=>$marks[1]],false,1);
        $this->workflow->review($this->admin,$exam,$candidates[$i]->fresh(),2);
    }
    $exam=$exam->fresh();$choices=scholarshipChoices($this,$exam);
    expect($this->workflow->calculate($exam,$candidates[1]->fresh())['percentage'])->toBeGreaterThan($this->workflow->calculate($exam,$candidates[0]->fresh())['percentage'])
        ->and($choices['total_1']['candidate_id'])->toBe($candidates[0]->id);
    $this->workflow->saveAwards($this->admin,$exam,1,$choices,$exam->version);
    expect($exam->awards()->where('slot','total_1')->value('candidate_id'))->toBe($candidates[0]->id);
});

it('rejects unreviewed, absent, foreign-grade, and foreign-exam recipients',function(){
    $exam=scholarshipAwardExam($this);$choices=scholarshipChoices($this,$exam);
    $other=$this->workflow->saveExam($this->admin,null,scholarshipExamData(['academic_year_id'=>2]));$foreign=scholarshipCandidate($other);
    $invalid=$choices;$invalid['total_1']['candidate_id']=$foreign->id;
    expect(fn()=>$this->workflow->saveAwards($this->admin,$exam,1,$invalid,$exam->version))->toThrow(ValidationException::class);
    $winner=$exam->candidates()->find($choices['total_1']['candidate_id']);
    $winner->update(['reviewed_at'=>null]);
    expect(fn()=>$this->workflow->saveAwards($this->admin,$exam,1,$choices,$exam->version))->toThrow(ValidationException::class);
    $winner->update(['reviewed_at'=>now(),'absent'=>true]);
    expect(fn()=>$this->workflow->saveAwards($this->admin,$exam,1,$choices,$exam->version))->toThrow(ValidationException::class);
    $winner->update(['absent'=>false,'grade_number'=>2]);
    expect(fn()=>$this->workflow->saveAwards($this->admin,$exam,1,$choices,$exam->version))->toThrow(ValidationException::class);
});

it('clears the grades award selections when any score changes and requires selecting winners again',function(){
    $exam=scholarshipAwardExam($this);$choices=scholarshipChoices($this,$exam);
    $this->workflow->saveAwards($this->admin,$exam,1,$choices,$exam->version);$exam=$exam->fresh();
    $candidate=$exam->candidates()->orderByDesc('id')->first();$scores=$candidate->scores()->pluck('score','subject_id')->all();
    $this->workflow->saveScores($this->admin,$exam,$candidate,$scores,false,$candidate->version);
    expect($exam->awards()->count())->toBe(0)->and($exam->events()->where('action','awards-cleared')->exists())->toBeTrue();
    $this->workflow->review($this->admin,$exam,$candidate->fresh(),$candidate->fresh()->version);$exam=$exam->fresh();
    expect(fn()=>$this->workflow->publish($this->admin,$exam,$exam->version))->toThrow(ValidationException::class);
});

it('locks published awards and permits a documented revision while enforcing the awards permission',function(){
    $exam=scholarshipAwardExam($this);$choices=scholarshipChoices($this,$exam);
    $user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->setRelation('roles',new \Illuminate\Database\Eloquent\Collection());$user->shouldReceive('isSuperAdmin')->andReturnFalse();$user->shouldReceive('canAccessCampus')->andReturnTrue();
    $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>SchoolInfo::where('id',1));
    $user->shouldReceive('hasPermission')->andReturnUsing(fn($code)=>$code!=='western-scholarship.awards');
    expect(fn()=>$this->workflow->saveAwards($user,$exam,1,$choices,$exam->version))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->workflow->saveAwards($this->admin,$exam,1,$choices,$exam->version);$exam=$exam->fresh();$this->workflow->publish($this->admin,$exam,$exam->version);$exam=$exam->fresh();
    expect(fn()=>$this->workflow->saveAwards($this->admin,$exam,1,$choices,$exam->version))->toThrow(ValidationException::class);
    $this->workflow->reopenResults($this->admin,$exam,$exam->version,'Correct scholarship committee decision.');$exam=$exam->fresh();
    $choices['total_1']['award_percent']=90;$this->workflow->saveAwards($this->admin,$exam,1,$choices,$exam->version);
    expect($exam->awards()->where('slot','total_1')->value('award_percent'))->toEqual(90);
});

it('allocates 35 Grade 1 and 40 Grade 2 students into four separate rooms with alphabetical ordering',function(){
    $candidates=collect();
    foreach([1=>35,2=>40] as $grade=>$count) for($i=$count;$i>=1;$i--) $candidates->push(new ScholarshipCandidate(['id'=>$grade*100+$i,'grade_number'=>$grade,'snapshot'=>['name_en'=>sprintf('Student %02d',$i),'student_code'=>(string)$i]]));
    $plan=$this->workflow->allocate($candidates,25);$rooms=collect($plan)->groupBy('room_number');
    expect($rooms->map->count()->values()->all())->toBe([25,10,25,15])
        ->and($plan[0]['snapshot']['name_en'])->toBe('Student 01')->and($plan[34]['grade_exam_number'])->toBe(35)
        ->and($plan[35]['grade_exam_number'])->toBe(1)->and($plan[35]['room_number'])->toBe(3)->and($plan[35]['room_seat'])->toBe(36)
        ->and($plan[35]['candidate_code'])->toBe('G02-001')
        ->and(collect($plan)->unique(fn($row)=>$row['room_number'].':'.$row['room_seat']))->toHaveCount(75);
});

it('handles all twelve grades without mixing them or creating an empty room at a capacity boundary',function(){
    $candidates=collect();foreach(range(1,12)as$grade)foreach(range(1,25)as$seat)$candidates->push(new ScholarshipCandidate(['id'=>$grade*100+$seat,'grade_number'=>$grade,'snapshot'=>['name_en'=>'Name '.$seat,'student_code'=>(string)$seat]]));
    $rows=$this->workflow->allocate($candidates,25);
    expect(collect($rows)->pluck('room_number')->unique())->toHaveCount(12)->and($rows[25]['room_number'])->toBe(2)->and($rows[25]['grade_exam_number'])->toBe(1)
        ->and($rows[25]['room_seat'])->toBe(26)->and($rows[299]['room_seat'])->toBe(300);
});

it('continues 35 Grade 1 and 40 Grade 2 seats and restarts room and seat numbers for each center',function(){
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipCenterData(['center_assignments'=>[]]));$centers=$exam->centers()->get();
    foreach($centers as $i=>$center)$center->campuses()->attach($i+1,['exam_id'=>$exam->id]);
    foreach([1,2] as $campus)foreach([1=>35,2=>40] as $grade=>$count)foreach(range($count,1) as $number)scholarshipCandidate($exam,sprintf('Student %02d',$number),$grade,$campus);
    $plan=$this->workflow->plan($exam);
    foreach(collect($plan['rows'])->groupBy('exam_center_id') as $rows){
        $rows=$rows->values();expect($rows->pluck('room_seat')->all())->toBe(range(1,75))
            ->and($rows->groupBy('room_number')->map->count()->values()->all())->toBe([25,10,25,15])
            ->and($rows[0]['room_number'])->toBe(1)->and($rows[35]['room_number'])->toBe(3)->and($rows[35]['room_seat'])->toBe(36);
    }
    $this->workflow->lockPlan($this->admin,$exam,$plan['token']);$saved=$this->workflow->orderedSeats($exam,$exam->candidates()->get());
    expect($saved->pluck('id')->all())->toBe(array_column($plan['rows'],'id'))->and($saved->where('room_number',1)->where('room_seat',1))->toHaveCount(2);
});

it('keeps Room 1 committee scoring and candidate visibility separate for different centers',function(){
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipCenterData(['center_assignments'=>[]]));$centers=$exam->centers()->get();
    foreach($centers as $i=>$center){$center->campuses()->attach($i+1,['exam_id'=>$exam->id]);scholarshipCandidate($exam,'Student '.$i,1,$i+1);}
    $this->workflow->lockPlan($this->admin,$exam,$this->workflow->plan($exam)['token']);$exam=$exam->fresh();
    $candidates=$exam->candidates()->orderBy('id')->get();
    DB::table('tb_school_info')->insert(['id'=>3,'campus_name_en'=>'THIRD']);
    DB::table('access_user_campuses')->where('user_id',2)->update(['campus_id'=>3]);
    $user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->setRelation('roles',new \Illuminate\Database\Eloquent\Collection());
    $user->shouldReceive('isSuperAdmin')->andReturnFalse();$user->shouldReceive('hasPermission')->andReturnTrue();
    $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>SchoolInfo::where('id',3));
    $scope=['user_id'=>2,'role'=>'score-entry','room_number'=>1,'grade_number'=>1,'exam_center_id'=>$centers[0]->id];
    $this->workflow->addCommittee($this->admin,$exam,$scope);
    expect($this->workflow->canScore($user,$exam->fresh(),$candidates[0]))->toBeTrue()
        ->and($this->workflow->canScore($user,$exam->fresh(),$candidates[1]))->toBeFalse()
        ->and($this->workflow->candidates($user,$exam->fresh())->pluck('id')->all())->toBe([$candidates[0]->id]);
    expect(fn()=>$this->workflow->addCommittee($this->admin,$exam,array_replace($scope,['exam_center_id'=>null])))->toThrow(ValidationException::class);
    expect(fn()=>$this->workflow->addCommittee($this->admin,$exam,array_replace($scope,['exam_center_id'=>999])))->toThrow(ValidationException::class);
    expect(fn()=>$this->workflow->addCommittee($this->admin,$exam,array_replace($scope,['grade_number'=>2])))->toThrow(ValidationException::class);
    // An old or forged room-only duty cannot cover Room 1 at every center.
    $exam->committee()->delete();$exam->committee()->create(['user_id'=>2,'role'=>'score-entry','room_number'=>1]);
    expect($this->workflow->canScore($user,$exam->fresh(),$candidates[0]))->toBeFalse()->and($this->workflow->candidates($user,$exam->fresh())->count())->toBe(0);
    $exam->committee()->delete();$this->workflow->addCommittee($this->admin,$exam,array_replace($scope,['role'=>'final-approver']));
    expect($exam->committee()->first()->exam_center_id)->toBeNull()->and($exam->committee()->first()->room_number)->toBeNull();
});

it('clears room duties when reopening an unscored plan and retains broader center duties',function(){
    $exam=scholarshipLockedExam($this);
    $this->workflow->addCommittee($this->admin,$exam,['user_id'=>2,'role'=>'invigilator','room_number'=>1]);
    $this->workflow->addCommittee($this->admin,$exam,['user_id'=>2,'role'=>'score-entry','grade_number'=>1]);
    $this->workflow->reopenPlan($this->admin,$exam,$exam->version,'Change room allocation.');
    expect($exam->committee()->count())->toBe(1)->and($exam->committee()->first()->role)->toBe('score-entry')
        ->and($exam->events()->first()->details['cleared_room_duties'][0]['role'])->toBe('invigilator');
});

it('migrates historical seat constraints and room duties without renumbering confirmed students',function(){
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipCenterData(['center_assignments'=>[]]));$centers=$exam->centers()->get();
    foreach($centers as $i=>$center){
        $candidate=scholarshipCandidate($exam,'Student '.$i,1,$i+1);
        $candidate->update(['room_number'=>$i+1,'room_seat'=>1,'exam_center_id'=>$center->id,'exam_center_name'=>$center->name,'seat_scope'=>$center->id]);
    }
    $exam->forceFill(['seats_locked_at'=>now()])->save();
    $candidate=$exam->candidates()->first();$candidate->update(['reviewed_at'=>now(),'reviewed_by'=>1]);
    $candidate->scores()->create(['subject_id'=>$exam->subjects->first()->id,'score'=>0]);
    $exam->committee()->create(['user_id'=>2,'role'=>'score-entry','room_number'=>2]);
    $migration=require database_path('migrations/2026_10_15_000007_scope_scholarship_rooms_by_center.php');$migration->down();$migration->up();
    expect($exam->candidates()->orderBy('id')->pluck('room_number')->all())->toBe([1,2])
        ->and($exam->candidates()->pluck('room_seat')->all())->toBe([1,1])
        ->and($exam->candidates()->orderBy('id')->pluck('seat_scope')->all())->toBe($centers->modelKeys())
        ->and($exam->committee()->first()->exam_center_id)->toBe($centers[1]->id)
        ->and($exam->fresh()->seats_locked_at)->not->toBeNull()->and($candidate->fresh()->reviewed_at)->not->toBeNull()->and($candidate->scores()->first()->score)->toBe(0.0);
});

it('recognizes numeric Khmer and English grade names without treating kindergarten as Grade 1',function(){
    expect($this->workflow->gradeNumber(new Grade(['grade'=>'ថ្នាក់ទី១២'])))->toBe(12)
        ->and($this->workflow->gradeNumber(new Grade(['grade'=>'Grade 2'])))->toBe(2)
        ->and($this->workflow->gradeNumber(new Grade(['grade'=>'Kindergarten 1','grade_short_name'=>'K1'])))->toBeNull();
});

it('automatically adds every assigned rank without duplication and preserves confirmed candidate snapshots',function(){
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipExamData());
    $enrollment=\App\Models\StudentEnrollment::findOrFail(1);
    $this->workflow->saveRank($this->admin,$enrollment,1);$this->workflow->saveRank($this->admin,$enrollment,3);
    $exam=$exam->fresh();expect($exam->candidates()->count())->toBe(1)->and($exam->candidates()->first()->class_rank)->toBe(3)->and($exam->campus_ids)->toBe([1]);
    $this->workflow->lockPlan($this->admin,$exam,$this->workflow->plan($exam)['token']);
    DB::table('tb_student')->where('id',1)->update(['full_name_en'=>'CHANGED NAME']);
    expect($exam->candidates()->first()->snapshot['name_en'])->toBe('CHEAN CHAMNUNY');
    expect(fn()=>$this->workflow->saveRank($this->admin,$enrollment,2))->toThrow(ValidationException::class);
    expect(ScholarshipStudentRank::first()->rank)->toBe(3);
});

it('blocks assigning ranks in unpermitted campuses and automatically includes the chosen rank',function(){
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipExamData());
    $user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->setRelation('roles',new \Illuminate\Database\Eloquent\Collection());$user->shouldReceive('isSuperAdmin')->andReturnFalse();
    $user->shouldReceive('canAccessCampus')->andReturnUsing(fn($id)=>$id===1);
    $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>SchoolInfo::where('id',1));$user->shouldReceive('hasPermission')->andReturnTrue();
    $enrollment=\App\Models\StudentEnrollment::findOrFail(3);
    expect(fn()=>$this->workflow->saveRank($user,$enrollment,3))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect($exam->candidates()->count())->toBe(0)->and(ScholarshipStudentRank::count())->toBe(0);
    $this->workflow->saveRank($this->admin,$enrollment,3);
    expect($exam->candidates()->first()->class_rank)->toBe(3)->and($exam->fresh()->campus_ids)->toBe([2]);
});

it('uses the regular academic year as the session and automatically imports all previously assigned ranks across campuses',function(){
    $this->workflow->saveRank($this->admin,\App\Models\StudentEnrollment::findOrFail(1),1);
    // Previously recorded ranks are preserved; new assignments are restricted to 1–3.
    ScholarshipStudentRank::create(['student_enrollment_id'=>3,'rank'=>10,'assigned_by'=>1]);
    $this->workflow->saveRank($this->admin,\App\Models\StudentEnrollment::findOrFail(2),2);
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipExamData(['name'=>'Ignored custom name','campus_ids'=>[999],'eligible_ranks'=>[1],'tie_policy'=>'dense']));
    expect($exam->name)->toBe('2025-2026')->and($exam->getRawOriginal('name'))->toBe('2025-2026')
        ->and($exam->campus_ids)->toBe([1,2])->and($exam->eligible_ranks)->toBe([])->and($exam->tie_policy)->toBe('competition')
        ->and($exam->candidates()->orderBy('student_enrollment_id')->pluck('student_enrollment_id')->all())->toBe([1,3])
        ->and($exam->candidates()->orderBy('student_enrollment_id')->pluck('class_rank')->all())->toBe([1,10]);
    expect(fn()=>$this->workflow->saveExam($this->admin,null,scholarshipExamData()))->toThrow(ValidationException::class);
    $updated=$this->workflow->saveExam($this->admin,$exam,scholarshipExamData(['version'=>$exam->version,'room_capacity'=>30]));
    expect($updated->room_capacity)->toBe(30)->and($updated->candidates()->count())->toBe(2);
});

it('withdraws candidates by removing their assigned rank and derives the remaining campuses',function(){
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipExamData());
    $first=$this->workflow->saveRank($this->admin,\App\Models\StudentEnrollment::findOrFail(1),1);
    $second=$this->workflow->saveRank($this->admin,\App\Models\StudentEnrollment::findOrFail(3),2);
    expect($exam->fresh()->campus_ids)->toBe([1,2]);
    $this->workflow->removeRank($this->admin,$second);
    expect($exam->candidates()->count())->toBe(1)->and($exam->fresh()->campus_ids)->toBe([1])->and(ScholarshipStudentRank::count())->toBe(1);
    $this->workflow->removeRank($this->admin,$first);
    expect($exam->candidates()->count())->toBe(0)->and($exam->fresh()->campus_ids)->toBe([]);
});

it('preserves the EOY average in the candidate snapshot and locks corrections with the seat plan',function(){
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipExamData());
    $enrollment=\App\Models\StudentEnrollment::findOrFail(1);
    $this->workflow->saveRank($this->admin,$enrollment,1,85.5);$exam=$exam->fresh();
    $candidate=$exam->candidates()->first();
    expect($candidate->snapshot['eoy_average'])->toBe(85.5)->and($this->workflow->calculate($exam,$candidate)['complete'])->toBeFalse();
    $this->workflow->saveRank($this->admin,$enrollment,2,91.25);
    expect($candidate->fresh()->snapshot['eoy_average'])->toBe(91.25);
    expect(fn()=>$this->workflow->saveRank($this->admin,$enrollment,4,95))->toThrow(ValidationException::class);
    expect(fn()=>$this->workflow->saveRank($this->admin,$enrollment,1,100.01))->toThrow(ValidationException::class);
    $exam=$exam->fresh();$this->workflow->lockPlan($this->admin,$exam,$this->workflow->plan($exam)['token']);
    expect(fn()=>$this->workflow->saveRank($this->admin,$enrollment,1,95))->toThrow(ValidationException::class);
    expect($candidate->fresh()->snapshot['eoy_average'])->toBe(91.25)->and(ScholarshipStudentRank::first()->eoy_average)->toBe(91.25);
});

it('rejects duplicate ranked enrollments for the same student atomically',function(){
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipExamData());
    $this->workflow->saveRank($this->admin,\App\Models\StudentEnrollment::findOrFail(1),1);
    $duplicate=\App\Models\StudentEnrollment::create(['student_id'=>1,'academic_year_id'=>1,'campus_id'=>2,'grade_id'=>1,'class_id'=>1]);
    expect(fn()=>$this->workflow->saveRank($this->admin,$duplicate,2))->toThrow(ValidationException::class);
    expect($exam->candidates()->count())->toBe(1)->and(ScholarshipStudentRank::count())->toBe(1)->and($exam->fresh()->campus_ids)->toBe([1]);
});

it('locks adding editing and removing ranks after seat confirmation and unlocks them with an unscored seat plan revision',function(){
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipExamData());
    $rank=$this->workflow->saveRank($this->admin,\App\Models\StudentEnrollment::findOrFail(1),1);$exam=$exam->fresh();
    $token=$this->workflow->plan($exam)['token'];
    $this->workflow->saveRank($this->admin,\App\Models\StudentEnrollment::findOrFail(3),2);
    expect(fn()=>$this->workflow->lockPlan($this->admin,$exam,$token))->toThrow(ValidationException::class);
    $exam=$exam->fresh();$this->workflow->lockPlan($this->admin,$exam,$this->workflow->plan($exam)['token']);$exam=$exam->fresh();
    expect(fn()=>$this->workflow->removeRank($this->admin,$rank))->toThrow(ValidationException::class);
    expect(fn()=>$this->workflow->saveRank($this->admin,\App\Models\StudentEnrollment::findOrFail(1),3))->toThrow(ValidationException::class);
    $this->workflow->reopenPlan($this->admin,$exam,$exam->version,'Correct scholarship candidate list.');
    $this->workflow->removeRank($this->admin,$rank);
    expect($exam->candidates()->count())->toBe(1)->and($exam->fresh()->campus_ids)->toBe([2]);
});

it('requires settings access for all automatically participating campuses without allowing campus exclusions',function(){
    $this->workflow->saveRank($this->admin,\App\Models\StudentEnrollment::findOrFail(3),1);
    $user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->setRelation('roles',new \Illuminate\Database\Eloquent\Collection());$user->shouldReceive('isSuperAdmin')->andReturnFalse();
    $user->shouldReceive('canAccessCampus')->andReturnUsing(fn($id)=>$id===1);
    $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>SchoolInfo::where('id',1));$user->shouldReceive('hasPermission')->andReturnTrue();
    expect(fn()=>$this->workflow->saveExam($user,null,scholarshipExamData(['campus_ids'=>[1]])))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(ScholarshipExam::count())->toBe(0);
});

it('rejects summer years for exam setup rank assignment and all permissions on legacy summer exams',function(){
    DB::table('tb_academic_year')->where('id',2)->update(['period_type'=>'summer']);
    expect(fn()=>$this->workflow->saveExam($this->admin,null,scholarshipExamData(['academic_year_id'=>2])))->toThrow(ValidationException::class);
    expect(fn()=>$this->workflow->saveRank($this->admin,\App\Models\StudentEnrollment::findOrFail(2),1))->toThrow(ValidationException::class);
    $exam=ScholarshipExam::create(['name'=>'Old summer exam','academic_year_id'=>2,'exam_date'=>'2026-11-20','campus_ids'=>[1],'eligible_ranks'=>[1],'version'=>1]);
    $candidate=scholarshipCandidate($exam);
    expect($this->workflow->canView($this->admin,$exam))->toBeFalse()->and($this->workflow->central($this->admin,$exam,'settings'))->toBeFalse()
        ->and($this->workflow->canScore($this->admin,$exam,$candidate))->toBeFalse()->and(ScholarshipStudentRank::count())->toBe(0);
});

it('rejects outdated previews and missing subject coverage before locking seats',function(){
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipExamData());scholarshipCandidate($exam);
    $preview=$this->workflow->plan($exam);scholarshipCandidate($exam,'STUDENT B');
    expect(fn()=>$this->workflow->lockPlan($this->admin,$exam,$preview['token']))->toThrow(ValidationException::class);
    $exam->subjects()->where('grade_from',1)->delete();
    expect(fn()=>$this->workflow->lockPlan($this->admin,$exam->fresh(),$this->workflow->plan($exam->fresh())['token']))->toThrow(ValidationException::class);
    expect($exam->fresh()->seats_locked_at)->toBeNull();
});

it('computes weighted percentages from subject maxima while keeping blank scores distinct from zero',function(){
    $exam=scholarshipLockedExam($this);$candidate=$exam->candidates()->first();$subjects=$this->workflow->applicableSubjects($exam,1);
    $this->workflow->saveScores($this->admin,$exam,$candidate,[$subjects[0]->id=>0,$subjects[1]->id=>null],false,1);
    expect($this->workflow->calculate($exam,$candidate->fresh())['complete'])->toBeFalse();
    $this->workflow->saveScores($this->admin,$exam,$candidate->fresh(),[$subjects[0]->id=>80,$subjects[1]->id=>25],false,2);
    $result=$this->workflow->calculate($exam,$candidate->fresh());
    expect($result['total'])->toEqual(105)->and($result['percentage'])->toEqual(57.5)->and($result['complete'])->toBeTrue();
});

it('rejects out-of-range overprecise and foreign-subject scores atomically',function($value){
    $exam=scholarshipLockedExam($this);$candidate=$exam->candidates()->first();$subject=$exam->subjects->first();
    expect(fn()=>$this->workflow->saveScores($this->admin,$exam,$candidate,[$subject->id=>$value],false,1))->toThrow(ValidationException::class);
    expect(ScholarshipScore::count())->toBe(0)->and($candidate->fresh()->version)->toBe(1);
})->with([-1,101,10.123,'not-a-number']);

it('rejects stale score forms and subjects from another grade',function(){
    $exam=scholarshipLockedExam($this);$candidate=$exam->candidates()->first();$subjects=$this->workflow->applicableSubjects($exam,1);
    $this->workflow->saveScores($this->admin,$exam,$candidate,[$subjects[0]->id=>90],false,1);
    expect(fn()=>$this->workflow->saveScores($this->admin,$exam,$candidate,[$subjects[0]->id=>20],false,1))->toThrow(ValidationException::class);
    expect(fn()=>$this->workflow->saveScores($this->admin,$exam,$candidate->fresh(),[$exam->subjects->last()->id=>20],false,2))->toThrow(ValidationException::class);
    expect(ScholarshipScore::where('subject_id',$subjects[0]->id)->value('score'))->toEqual(90);
});

it('requires complete reviewed scores before publication and freezes published results until an explicit revision',function(){
    $exam=scholarshipLockedExam($this);$candidate=$exam->candidates()->first();$subjects=$this->workflow->applicableSubjects($exam,1);
    expect(fn()=>$this->workflow->review($this->admin,$exam,$candidate,1))->toThrow(ValidationException::class);
    expect(fn()=>$this->workflow->publish($this->admin,$exam,$exam->version))->toThrow(ValidationException::class);
    $this->workflow->saveScores($this->admin,$exam,$candidate,[$subjects[0]->id=>100,$subjects[1]->id=>50],false,1);
    $this->workflow->review($this->admin,$exam,$candidate->fresh(),2);$exam=$exam->fresh();
    expect(fn()=>$this->workflow->publish($this->admin,$exam,$exam->version))->toThrow(ValidationException::class);
    $this->workflow->saveAwards($this->admin,$exam,1,scholarshipChoices($this,$exam),$exam->version);$exam=$exam->fresh();
    $this->workflow->publish($this->admin,$exam,$exam->version);
    expect($exam->fresh()->published_at)->not->toBeNull()->and($candidate->fresh()->result_rank)->toBe(1)->and($candidate->fresh()->award_percent)->toEqual(100);
    expect(fn()=>$this->workflow->saveScores($this->admin,$exam->fresh(),$candidate->fresh(),[],false,3))->toThrow(ValidationException::class);
    $this->workflow->reopenResults($this->admin,$exam->fresh(),$exam->fresh()->version,'Correct a verified marking error.');
    $this->workflow->saveScores($this->admin,$exam->fresh(),$candidate->fresh(),[$subjects[0]->id=>90,$subjects[1]->id=>45],false,3);
    expect($candidate->fresh()->reviewed_at)->toBeNull()->and($candidate->fresh()->result_rank)->toBeNull();
    expect(fn()=>$this->workflow->publish($this->admin,$exam->fresh(),$exam->fresh()->version))->toThrow(ValidationException::class);
});

it('keeps absences out of ranks and permits publishing a reviewed absence without inventing zero scores',function(){
    $exam=scholarshipLockedExam($this);$candidate=$exam->candidates()->first();
    $this->workflow->saveScores($this->admin,$exam,$candidate,[],true,1);$this->workflow->review($this->admin,$exam,$candidate->fresh(),2);
    $exam=$exam->fresh();$this->workflow->saveAwards($this->admin,$exam,1,scholarshipChoices($this,$exam),$exam->version);$exam=$exam->fresh();
    $this->workflow->publish($this->admin,$exam,$exam->version);
    expect($candidate->fresh()->result_total)->toBeNull()->and($candidate->fresh()->result_rank)->toBeNull()->and($this->workflow->results($exam->fresh())->first()['status'])->toBe('Absent');
});

it('uses fixed total-score ranks per grade without automatically awarding scholarships to tied students',function(){
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipExamData());
    foreach([['A',1,100],['B',1,100],['C',1,80],['D',2,100]]as[$name,$grade,$value]) {
        $candidate=scholarshipCandidate($exam,$name,$grade);
        foreach($this->workflow->applicableSubjects($exam,$grade)as$subject)$candidate->scores()->create(['subject_id'=>$subject->id,'score'=>$subject->max_score*$value/100]);
    }
    $rows=$this->workflow->results($exam);expect($rows->pluck('rank')->all())->toBe([1,1,3,1])->and($rows->pluck('award')->all())->toEqual([null,null,null,null]);
});

it('limits committee scoring by grade and room and does not grant scoring through invigilation',function(){
    $exam=scholarshipLockedExam($this);$candidate=$exam->candidates()->first();
    $user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->setRelation('roles',new \Illuminate\Database\Eloquent\Collection());$user->shouldReceive('isSuperAdmin')->andReturnFalse();
    $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>SchoolInfo::where('id',1));$user->shouldReceive('hasPermission')->andReturnTrue();
    ScholarshipCommittee::create(['exam_id'=>$exam->id,'user_id'=>2,'role'=>'invigilator','room_number'=>1]);
    expect($this->workflow->canScore($user,$exam->fresh(),$candidate))->toBeFalse();
    ScholarshipCommittee::create(['exam_id'=>$exam->id,'user_id'=>2,'role'=>'score-entry','grade_number'=>2,'room_number'=>1]);
    expect($this->workflow->canScore($user,$exam->fresh(),$candidate))->toBeFalse();
    ScholarshipCommittee::create(['exam_id'=>$exam->id,'user_id'=>2,'role'=>'score-entry','grade_number'=>1,'room_number'=>1]);
    expect($this->workflow->canScore($user,$exam->fresh(),$candidate))->toBeTrue()->and($this->workflow->canScore($user,$exam->fresh(),$candidate,true))->toBeFalse();
});

it('prevents reopening a scored seat plan and changing subjects after seats are confirmed',function(){
    $exam=scholarshipLockedExam($this);$candidate=$exam->candidates()->first();
    expect(fn()=>$this->workflow->saveExam($this->admin,$exam,scholarshipExamData(['version'=>$exam->version])))->toThrow(ValidationException::class);
    $this->workflow->saveScores($this->admin,$exam,$candidate,[],false,1);
    expect(fn()=>$this->workflow->reopenPlan($this->admin,$exam,$exam->version,'Change the seating order.'))->toThrow(ValidationException::class);
    expect($exam->fresh()->seats_locked_at)->not->toBeNull();
});

it('rejects overlapping duplicate subjects and keeps separate exam sessions isolated',function(){
    $data=scholarshipExamData();$data['subjects'][]=$data['subjects'][0];
    expect(fn()=>$this->workflow->saveExam($this->admin,null,$data))->toThrow(ValidationException::class);
    expect(ScholarshipExam::count())->toBe(0);
    $first=scholarshipLockedExam($this);$second=scholarshipLockedExam($this,1,2);
    expect(fn()=>$this->workflow->saveScores($this->admin,$first,$second->candidates()->first(),[],false,1))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(ScholarshipScore::count())->toBe(0);
});

it('creates a valid Excel workbook preserving student ID strings and keeping names out of formulas',function(){
    $bytes=app(\App\Support\ScholarshipExport::class)->xlsx([['ID','Name','Score'],['00123','=SUM(A1:A9)',80]]);
    $path=tempnam(sys_get_temp_dir(),'scholarship-test-');file_put_contents($path,$bytes);
    try {
        $zip=new ZipArchive();expect($zip->open($path))->toBeTrue();$xml=$zip->getFromName('xl/worksheets/sheet1.xml');
        expect($xml)->toContain('00123')->toContain('=SUM(A1:A9)')->not->toContain('<f>');
        expect(simplexml_load_string($xml))->not->toBeFalse();$zip->close();
    }finally{unlink($path);}
});

it('keeps future answer-paper configurations independent by grade version subject and academic year',function(){
    $first=$this->workflow->saveExam($this->admin,null,scholarshipExamData());
    $second=$this->workflow->saveExam($this->admin,null,scholarshipExamData(['academic_year_id'=>2]));
    $subject=$first->subjects->first();
    $paper=$subject->papers()->create(['grade_number'=>1,'paper_version'=>'A','question_count'=>40,'answer_choices'=>['A','B','C','D'],'points_per_question'=>2.5]);
    $subject->papers()->create(['grade_number'=>2,'paper_version'=>'A','question_count'=>50,'answer_choices'=>['A','B','C'],'points_per_question'=>2]);
    $subject->papers()->create(['grade_number'=>1,'paper_version'=>'B','question_count'=>40]);
    $second->subjects->first()->papers()->create(['grade_number'=>1,'paper_version'=>'A','question_count'=>20]);
    expect($paper->fresh()->answer_choices)->toBe(['A','B','C','D'])->and($paper->fresh()->points_per_question)->toBe(2.5)
        ->and($paper->subject->exam->academic_year_id)->toBe(1)->and($subject->papers()->count())->toBe(3)
        ->and($second->subjects->first()->papers()->count())->toBe(1);
    expect(fn()=>$subject->papers()->create(['grade_number'=>1,'paper_version'=>'A']))->toThrow(\Illuminate\Database\QueryException::class);
});

it('prepares scan-source fields without changing existing zero blank or reviewed manual scores',function(){
    $exam=scholarshipLockedExam($this,2);$candidates=$exam->candidates()->orderBy('id')->get();$subject=$exam->subjects->first();
    $migration=require database_path('migrations/2026_10_14_000008_prepare_scholarship_answer_sheets.php');
    $migration->down();
    foreach([0,null]as$i=>$score)DB::table('tb_scholarship_score')->insert(['candidate_id'=>$candidates[$i]->id,'subject_id'=>$subject->id,'score'=>$score]);
    $candidates[0]->update(['reviewed_by'=>1,'reviewed_at'=>now()]);
    $migration->up();
    $scores=ScholarshipScore::orderBy('id')->get();
    expect($scores->pluck('score')->all())->toBe([0.0,null])->and($scores->pluck('entry_method')->all())->toBe(['manual','manual'])
        ->and($scores->pluck('source_paper_id')->all())->toBe([null,null])->and($scores->pluck('source_reference')->all())->toBe([null,null])
        ->and($candidates[0]->fresh()->reviewed_at)->not->toBeNull()->and(\App\Models\ScholarshipPaper::count())->toBe(0);
});

it('records the prior scan source when manually correcting a future scanned score and clears its active scan link',function(){
    $exam=scholarshipLockedExam($this);$candidate=$exam->candidates()->first();$subjects=$this->workflow->applicableSubjects($exam,1);
    $this->workflow->saveScores($this->admin,$exam,$candidate,[$subjects[0]->id=>80,$subjects[1]->id=>40],false,1);
    $this->workflow->review($this->admin,$exam,$candidate->fresh(),2);
    $paper=$subjects[0]->papers()->create(['grade_number'=>1,'paper_version'=>'A','question_count'=>100]);
    $candidate->scores()->where('subject_id',$subjects[0]->id)->update(['entry_method'=>'scan','source_paper_id'=>$paper->id,'source_reference'=>'scan-attempt-example']);
    $this->workflow->saveScores($this->admin,$exam,$candidate->fresh(),[$subjects[0]->id=>90,$subjects[1]->id=>40],false,3);
    $score=$candidate->scores()->where('subject_id',$subjects[0]->id)->first();
    $event=$exam->events()->where('action','scores-saved')->latest('id')->first();
    expect($score->score)->toBe(90.0)->and($score->entry_method)->toBe('manual')->and($score->source_paper_id)->toBeNull()->and($score->source_reference)->toBeNull()
        ->and($candidate->fresh()->reviewed_at)->toBeNull()->and($event->details['before_sources'][$subjects[0]->id]['source_reference'])->toBe('scan-attempt-example')
        ->and($event->details['before_sources'][$subjects[0]->id]['source_paper_id'])->toBe($paper->id)->and($event->details['entry_method'])->toBe('manual');
});

it('protects future paper configurations from being deleted by the current settings form',function(){
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipExamData());
    $paper=$exam->subjects->first()->papers()->create(['grade_number'=>1,'question_count'=>40]);
    expect(fn()=>$this->workflow->saveExam($this->admin,$exam,scholarshipExamData(['version'=>$exam->version])))->toThrow(ValidationException::class);
    expect($paper->fresh()->question_count)->toBe(40)->and($exam->subjects()->count())->toBe(4);
});

it('validates real settings form input including subject grade ranges before saving',function(){
    $data=scholarshipExamData();
    $request=\Illuminate\Http\Request::create('/students/western-scholarship/exams','POST',$data);$request->setUserResolver(fn()=>$this->admin);
    app(\App\Http\Controllers\ScholarshipController::class)->saveExam($request);
    expect(ScholarshipExam::count())->toBe(1);
    $data['subjects'][0]['grade_from']=6;$data['subjects'][0]['grade_to']=1;
    $request=\Illuminate\Http\Request::create('/students/western-scholarship/exams','POST',$data);$request->setUserResolver(fn()=>$this->admin);
    expect(fn()=>app(\App\Http\Controllers\ScholarshipController::class)->saveExam($request))->toThrow(ValidationException::class);
    expect(ScholarshipExam::count())->toBe(1);
});

it('allows explicit whole-exam committee duties across campuses while keeping ordinary campus viewing scoped',function(){
    $exam=$this->workflow->saveExam($this->admin,null,scholarshipExamData());scholarshipCandidate($exam,'CAMPUS ONE',1,1);scholarshipCandidate($exam,'CAMPUS TWO',1,2);
    $user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->setRelation('roles',new \Illuminate\Database\Eloquent\Collection());$user->shouldReceive('isSuperAdmin')->andReturnFalse();
    $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>SchoolInfo::where('id',1));$user->shouldReceive('hasPermission')->andReturnTrue();
    expect($this->workflow->candidates($user,$exam)->count())->toBe(1);
    ScholarshipCommittee::create(['exam_id'=>$exam->id,'user_id'=>2,'role'=>'score-entry']);
    expect($this->workflow->candidates($user,$exam->fresh())->count())->toBe(2);
});
