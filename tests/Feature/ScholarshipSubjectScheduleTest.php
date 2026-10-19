<?php
use App\Models\{ScholarshipExam,ScholarshipSubject,Staff,User};
use App\Services\{ScholarshipInvigilators,ScholarshipWorkflow};
use App\Support\{ScholarshipSubjectOrder,ScholarshipSubjectSchedule};
use Illuminate\Validation\ValidationException;
require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';

beforeEach(function(){
    createScholarshipSchema();$this->user=Mockery::mock(User::class)->makePartial();$this->user->id=1;$this->user->shouldReceive('isSuperAdmin')->andReturnTrue();
    $this->exam=scholarshipSeatReportExam(2);$this->schedule=app(ScholarshipSubjectSchedule::class);$this->workflow=app(ScholarshipWorkflow::class);$this->invigilators=app(ScholarshipInvigilators::class);
});
function schedulePayload($test): array
{
    $payload=$test->schedule->state($test->exam);
    foreach($payload['defaults'] as $id=>&$value){$subject=$test->exam->subjects->firstWhere('id',$id);$index=$test->exam->subjects->where('grade_from',$subject->grade_from)->values()->search(fn($s)=>$s->id===$id);
        $value=['exam_date'=>'2026-11-20','starts_at'=>sprintf('%02d:00',8+$index),'ends_at'=>sprintf('%02d:50',8+$index)];}unset($value);return $payload;
}
function saveSchedule($test,array $payload): ScholarshipExam
{
    $order=app(ScholarshipSubjectOrder::class)->state($test->exam->fresh());
    return $test->workflow->saveSubjectOrder($test->user,$test->exam,$order['bands'],$order['grades'],$test->exam->fresh()->version,$payload);
}
function schedulePerson($test)
{return $test->invigilators->save($test->user,$test->exam,['name_en'=>'TEST INVIGILATOR','gender'=>'Male','campus'=>'BCH','position'=>'Teacher','phone'=>'012345678']);}
function assignScheduleRoom($test,$person,int $number,int $room=1,int $center=0): void
{$test->invigilators->assign($test->user,$test->exam,['invigilator_id'=>$person->id,'session_id'=>$test->exam->examSessions()->where('number',$number)->first()->id,'exam_center_id'=>$center?:$test->exam->centers()->first()->id,'room_number'=>$room]);}

it('uses different grade-band subject durations and independent grade timing overrides',function(){
    $math=$this->exam->subjects()->create(['name'=>'Mathematics Secondary','grade_from'=>7,'grade_to'=>12,'max_score'=>100,'weight'=>1,'position'=>6]);$this->exam=$this->exam->fresh();
    $payload=schedulePayload($this);$payload['defaults'][$math->id]=['exam_date'=>'2026-11-21','starts_at'=>'08:00','ends_at'=>'09:50'];
    $primary=$this->exam->subjects->firstWhere('name','Mathematics');$payload['grades'][3][$primary->id]=['exam_date'=>'2026-11-22','starts_at'=>'08:30','ends_at'=>'09:30'];
    $exam=saveSchedule($this,$payload);
    expect($this->schedule->forSubject($exam,1,$primary->id)['ends_at'])->toBe('10:50')
        ->and($this->schedule->forSubject($exam,7,$math->id))->toBe(['exam_date'=>'2026-11-21','starts_at'=>'08:00','ends_at'=>'09:50'])
        ->and($this->schedule->forSubject($exam,3,$primary->id))->toBe(['exam_date'=>'2026-11-22','starts_at'=>'08:30','ends_at'=>'09:30']);
});

it('supports chemistry time overrides for grades ten through twelve without changing grade ranges',function(){
    $chem=$this->exam->subjects()->create(['name'=>'Chemistry','grade_from'=>7,'grade_to'=>12,'max_score'=>100,'weight'=>1,'position'=>7]);$this->exam=$this->exam->fresh();$payload=schedulePayload($this);
    $payload['defaults'][$chem->id]=['exam_date'=>'2026-11-20','starts_at'=>'09:00','ends_at'=>'10:30'];
    foreach([10,11,12] as $g)$payload['grades'][$g][$chem->id]=['exam_date'=>'2026-11-20','starts_at'=>'09:00','ends_at'=>'10:50'];
    $exam=saveSchedule($this,$payload);
    expect($this->schedule->forSubject($exam,9,$chem->id)['ends_at'])->toBe('10:30')->and($this->schedule->forSubject($exam,10,$chem->id)['ends_at'])->toBe('10:50')
        ->and($chem->fresh()->grade_from)->toBe(7)->and($chem->fresh()->grade_to)->toBe(12);
});

it('resets grade times to inherit the current subject defaults',function(){
    $payload=schedulePayload($this);$id=$this->exam->subjects->first()->id;$payload['grades'][1][$id]=['exam_date'=>'2026-11-20','starts_at'=>'10:00','ends_at'=>'11:00'];saveSchedule($this,$payload);
    $payload['grades']=[];$payload['defaults'][$id]['ends_at']='08:40';$exam=saveSchedule($this,$payload);
    expect($this->schedule->forSubject($exam,1,$id)['ends_at'])->toBe('08:40')->and($exam->subject_schedule_overrides)->toBe([]);
});

it('keeps subject timing attached to the subject when exam order changes',function(){
    $payload=schedulePayload($this);$exam=saveSchedule($this,$payload);$order=app(ScholarshipSubjectOrder::class)->state($exam);$order['bands']['primary']=array_reverse($order['bands']['primary']);
    $exam=$this->workflow->saveSubjectOrder($this->user,$exam,$order['bands'],$order['grades'],$exam->version);
    expect($this->schedule->forSession($exam,1,1))->toBe($payload['defaults'][$order['bands']['primary'][0]]);
});

it('rejects partial invalid foreign or ineligible timing settings atomically',function($kind){
    $payload=schedulePayload($this);$id=$this->exam->subjects->first()->id;
    match($kind){'end-before-start'=>$payload['defaults'][$id]['ends_at']='07:00','missing-end'=>$payload['defaults'][$id]['ends_at']='',
        'missing-date'=>$payload['defaults'][$id]['exam_date']='','invalid-date'=>$payload['defaults'][$id]['exam_date']='2026-02-30',
        'foreign'=>$payload['defaults'][999]=$payload['defaults'][$id],'missing-subject'=>array_pop($payload['defaults']),
        'wrong-grade'=>$payload['grades'][12][$id]=$payload['defaults'][$id],'bad-grade'=>$payload['grades'][13][$id]=$payload['defaults'][$id]};
    $before=$this->exam->fresh()->version;expect(fn()=>saveSchedule($this,$payload))->toThrow(ValidationException::class);
    expect($this->exam->fresh()->version)->toBe($before)->and($this->exam->subjects()->whereNotNull('starts_at')->count())->toBe(0);
})->with(['end-before-start','missing-end','missing-date','invalid-date','foreign','missing-subject','wrong-grade','bad-grade']);

it('creates session identities automatically and blocks assignments to unscheduled subjects',function(){
    $payload=schedulePayload($this);$id=$this->exam->subjects->first()->id;$payload['defaults'][$id]=['exam_date'=>'','starts_at'=>'','ends_at'=>''];$this->exam=saveSchedule($this,$payload);
    expect($this->exam->examSessions()->count())->toBe(5);$person=schedulePerson($this);
    expect(fn()=>assignScheduleRoom($this,$person,1))->toThrow(ValidationException::class);
});

it('checks conflicts using the room grades actual subject times rather than global session times',function(){
    $this->exam->candidates()->where('campus_id',2)->update(['grade_number'=>7]);
    $secondary=$this->exam->subjects->firstWhere('grade_from',7);$payload=schedulePayload($this);$payload['defaults'][$secondary->id]=['exam_date'=>'2026-11-20','starts_at'=>'08:00','ends_at'=>'09:50'];
    $this->exam=saveSchedule($this,$payload);$person=schedulePerson($this);$south=$this->exam->centers()->get()->last()->id;
    assignScheduleRoom($this,$person,1,1,$south);
    expect(fn()=>assignScheduleRoom($this,$person,2))->toThrow(ValidationException::class);
    $payload['defaults'][$this->exam->subjects->where('grade_from',1)->values()[1]->id]=['exam_date'=>'2026-11-20','starts_at'=>'10:00','ends_at'=>'10:50'];
    $this->exam=saveSchedule($this,$payload);assignScheduleRoom($this,$person,2);expect($person->duties()->count())->toBe(2);
});

it('rolls back a timing change that conflicts with existing linked duties',function(){
    $payload=schedulePayload($this);$this->exam=saveSchedule($this,$payload);$sessions=$this->exam->examSessions()->get();
    $this->invigilators->linkSessions($this->user,$this->exam,['version'=>$this->exam->version,'exam_center_id'=>$this->exam->centers()->first()->id,'session_ids'=>[$sessions[0]->id,$sessions[1]->id],'source_session_id'=>$sessions[0]->id,'confirm'=>1]);
    $person=schedulePerson($this);assignScheduleRoom($this,$person,1);$before=$this->exam->fresh()->version;
    $first=$this->exam->subjects->first()->id;$payload['defaults'][$first]['ends_at']='09:30';
    expect(fn()=>saveSchedule($this,$payload))->toThrow(ValidationException::class);
    expect($this->exam->fresh()->version)->toBe($before)->and($person->duties()->count())->toBe(2)->and(substr($this->exam->subjects()->find($first)->ends_at,0,5))->toBe('08:50');
});

it('allows schedule edits on confirmed seats while preserving seats scores and subjects',function(){
    $before=$this->exam->candidates()->get()->toArray();$subjects=$this->exam->subjects->map->only(['id','name','grade_from','grade_to','max_score','weight'])->all();
    $exam=saveSchedule($this,schedulePayload($this));expect($exam->seats_locked_at)->not->toBeNull()->and($exam->candidates()->get()->toArray())->toBe($before)
        ->and($exam->subjects->map->only(['id','name','grade_from','grade_to','max_score','weight'])->all())->toBe($subjects);
});

it('keeps published and historical schedules read only',function($kind){
    $this->exam->update([$kind=>now()]);expect(fn()=>saveSchedule($this,schedulePayload($this)))->toThrow(ValidationException::class);
})->with(['published_at','historical_imported_at']);

it('saves default subject date and times in the normal exam settings form',function(){
    $exam=$this->workflow->saveExam($this->user,null,['academic_year_id'=>2,'exam_date'=>'2026-11-20','room_capacity'=>25,'version'=>1,
        'subjects'=>[['name'=>'Math','grade_from'=>1,'grade_to'=>6,'max_score'=>100,'weight'=>1,'exam_date'=>'2026-11-21','starts_at'=>'08:00','ends_at'=>'08:50']]]);
    expect($exam->subjects->first()->starts_at)->toBe('08:00')->and($this->schedule->forSession($exam,1,1)['exam_date'])->toBe('2026-11-21')->and($exam->examSessions()->count())->toBe(1);
});

it('migrates old session schedules into equivalent defaults and grade overrides',function(){
    $migration=require database_path('migrations/2026_10_16_000009_add_scholarship_subject_schedules.php');$migration->down();
    $this->invigilators->saveSessions($this->user,$this->exam,[['number'=>1,'exam_date'=>'2026-11-20','starts_at'=>'08:00','ends_at'=>'08:50'],['number'=>2,'exam_date'=>'2026-11-20','starts_at'=>'09:00','ends_at'=>'10:00']]);
    $order=app(ScholarshipSubjectOrder::class)->state($this->exam);$order['grades'][2]=array_reverse($order['bands']['primary']);$this->exam->update(['subject_orders'=>$order]);
    $migration->up();$exam=$this->exam->fresh();
    $ids=app(ScholarshipSubjectOrder::class)->forGrade($exam,2)->pluck('id');
    expect($this->schedule->forSession($exam,1,1)['starts_at'])->toBe('08:00')->and($this->schedule->forSession($exam,2,1)['starts_at'])->toBe('08:00')
        ->and($this->schedule->forSession($exam,2,2)['ends_at'])->toBe('10:00');
});

it('shows the effective subject schedule in committee duties without a second editable schedule',function(){
    $this->exam=saveSchedule($this,schedulePayload($this));$person=schedulePerson($this);assignScheduleRoom($this,$person,1);
    $request=\Illuminate\Http\Request::create('/students/western-scholarship','GET',['tab'=>'exam-committee','exam_id'=>$this->exam->id]);
    $request->setUserResolver(fn()=>$this->user);$request->setLaravelSession(app('session')->driver());
    $data=app(\App\Http\Controllers\ScholarshipController::class)->index($request)->getData();$html=view('western-scholarship._exam-committee',$data)->render();
    expect($html)->toContain('08:00 - 08:50','Dates and times are managed by subject in Scholarship Settings')->not->toContain('Save Session Schedule','name="sessions[0][starts_at]"','00:00');
});
