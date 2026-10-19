<?php

use App\Http\Controllers\{ScholarshipController,ScholarshipInvigilatorController};
use App\Models\{ScholarshipExam,ScholarshipInvigilator,SchoolInfo,Staff,User};
use App\Services\{ScholarshipInvigilators,ScholarshipWorkflow};
use App\Support\{ScholarshipInvigilatorWorkbook,ScholarshipSeatReports};
use Illuminate\Http\{Request,UploadedFile};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
beforeEach(function(){
    createScholarshipSchema();$this->service=app(ScholarshipInvigilators::class);
    $this->user=Mockery::mock(User::class)->makePartial();$this->user->id=1;$this->user->shouldReceive('isSuperAdmin')->andReturnTrue();
    $this->exam=scholarshipSeatReportExam(25);$this->exam->subjects()->where('name','General Knowledge')->delete();$this->exam=$this->exam->fresh();
    $this->person=['source'=>'external','staff_code'=>null,'name_en'=>'EXTERNAL INVIGILATOR','gender'=>'Female','campus'=>'External','position'=>'Examiner','phone'=>'012345678','organization'=>'Partner School'];
    $this->paths=[];
});
afterEach(function(){foreach($this->paths as $path)if(is_file($path))unlink($path);});

function invigilatorSessions($test,int $count=4): void
{
    $rows=[];foreach(range(1,$count) as $number)$rows[]=['number'=>$number,'exam_date'=>'2026-11-20','starts_at'=>sprintf('%02d:00',7+$number),'ends_at'=>sprintf('%02d:45',7+$number)];
    $test->service->saveSessions($test->user,$test->exam,$rows);
}
function invigilatorDuty($test,$person,int $session=1,int $room=1,?int $center=null): array
{return ['invigilator_id'=>$person->id,'session_id'=>$test->exam->examSessions()->where('number',$session)->first()->id,'exam_center_id'=>$center??$test->exam->centers()->first()->id,'room_number'=>$room];}
function invigilatorBook($test): array
{return ['Invigilators'=>[['invigilator_code'=>'INV-001','staff_id'=>'EXT-001','staff_name_english'=>'EXTERNAL INVIGILATOR','gender'=>'Female','campus'=>'External','position'=>'Examiner','phone'=>'012345678']],
    'Room Assignments'=>[['invigilator_code'=>'INV-001','exam_center'=>'North Exam Center','session'=>1,'room'=>1]]];}
function invigilatorRequest($test,array $data=[],array $files=[]): Request
{
    $request=Request::create('/students/western-scholarship/invigilators','POST',$data,[],$files);$request->headers->set('Accept','application/json');
    $request->setUserResolver(fn()=>$test->user);$request->setLaravelSession(app('session')->driver());return $request;
}
function invigilatorWorkbookPath($test,array $book): string
{$path=tempnam(sys_get_temp_dir(),'invigilators-test-');file_put_contents($path,app(ScholarshipInvigilatorWorkbook::class)->write($book));$test->paths[]=$path;return $path;}

it('copies HRM details into an independent yearly snapshot and retains it after HRM deletion',function(){
    Staff::findOrFail(1)->update(['gender'=>'Male','phone'=>'012345678','email'=>'staff@example.test']);
    $person=$this->service->save($this->user,$this->exam,['source'=>'hrm','staff_id'=>1]);
    expect($person->only(['staff_code','name_en','gender','campus','position','phone','email']))->toBe([
        'staff_code'=>'HRM-001','name_en'=>'BCH PREPARER','gender'=>'Male','campus'=>'BCH','position'=>'Registrar','phone'=>'+85512345678','email'=>'staff@example.test']);
    Staff::findOrFail(1)->update(['name_en'=>'CHANGED NAME','phone'=>'099999999']);Staff::findOrFail(1)->forceDelete();
    expect($person->fresh()->name_en)->toBe('BCH PREPARER')->and($person->fresh()->phone)->toBe('+85512345678')->and($person->fresh()->source)->toBe('hrm');
});

it('allows completing missing HRM contact information without editing HRM',function(){
    $person=$this->service->save($this->user,$this->exam,['source'=>'hrm','staff_id'=>1,'gender'=>'Male','phone'=>'012345678']);
    expect($person->phone)->toBe('012345678')->and(Staff::findOrFail(1)->phone)->toBeNull();
});

it('creates and edits external invigilators with stable yearly codes',function(){
    $person=$this->service->save($this->user,$this->exam,$this->person);
    $other=$this->service->save($this->user,$this->exam,array_replace($this->person,['name_en'=>'OTHER INVIGILATOR']));
    $this->service->save($this->user,$this->exam,array_replace($this->person,['phone'=>'099999999']),$person);
    expect($person->fresh()->phone)->toBe('099999999')->and($person->code)->toBe('INV-001')->and($other->code)->toBe('INV-002');
});

it('requires complete invigilator information',function($field){
    $data=$this->person;$data[$field]=' ';
    expect(fn()=>$this->service->save($this->user,$this->exam,$data))->toThrow(ValidationException::class);
    expect($this->exam->invigilators()->count())->toBe(0);
})->with(['name_en','gender','campus','position','phone']);

it('rejects duplicate Staff IDs within a year but permits the next academic year',function(){
    $data=array_replace($this->person,['staff_code'=>'EXT-001']);$this->service->save($this->user,$this->exam,$data);
    expect(fn()=>$this->service->save($this->user,$this->exam,array_replace($data,['staff_code'=>'ext-001'])))->toThrow(ValidationException::class);
    $other=ScholarshipExam::create(array_replace($this->exam->getAttributes(),['id'=>null,'academic_year_id'=>2,'name'=>'2026-2027']));
    expect($this->service->save($this->user,$other,$data)->staff_code)->toBe('EXT-001');
});

it('assigns different invigilators by session and permits room changes between nonoverlapping sessions',function(){
    invigilatorSessions($this);$person=$this->service->save($this->user,$this->exam,$this->person);
    $candidate=$this->exam->candidates()->where('campus_id',1)->first();$candidate->update(['room_number'=>2]);
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person));
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person,2,2));
    expect($person->duties()->count())->toBe(2);
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person));expect($person->duties()->count())->toBe(2);
});

it('rejects a second room in the same session across centers',function(){
    invigilatorSessions($this);$person=$this->service->save($this->user,$this->exam,$this->person);
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person));
    expect(fn()=>$this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person,1,1,$this->exam->centers()->get()->last()->id)))->toThrow(ValidationException::class);
    expect($person->duties()->count())->toBe(1);
});

it('rejects overlapping duties even when their session numbers differ',function(){
    invigilatorSessions($this);$this->exam->examSessions()->where('number',2)->update(['starts_at'=>'08:15','ends_at'=>'09:15']);
    $person=$this->service->save($this->user,$this->exam,$this->person);$this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person));
    expect(fn()=>$this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person,2)))->toThrow(ValidationException::class);
});

it('rolls back schedule edits that would create overlapping duties',function(){
    invigilatorSessions($this);$person=$this->service->save($this->user,$this->exam,$this->person);
    foreach([1,2] as $session)$this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person,$session));
    expect(fn()=>$this->service->saveSessions($this->user,$this->exam,[['number'=>2,'exam_date'=>'2026-11-20','starts_at'=>'08:30','ends_at'=>'09:30']]))->toThrow(ValidationException::class);
    expect(substr($this->exam->examSessions()->where('number',2)->first()->starts_at,0,5))->toBe('09:00');
});

it('applies a room to all sessions atomically',function(){
    invigilatorSessions($this);$person=$this->service->save($this->user,$this->exam,$this->person);
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person)+['all_sessions'=>1]);expect($person->duties()->count())->toBe(4);
});

it('rejects invalid rooms sessions cross-year people and unconfirmed seats',function($invalid){
    invigilatorSessions($this);$person=$this->service->save($this->user,$this->exam,$this->person);$data=invigilatorDuty($this,$person);
    match($invalid){'room'=>$data['room_number']=999,'session'=>$data['session_id']=999,'center'=>$data['exam_center_id']=999,'person'=>$data['invigilator_id']=999,'unlocked'=>$this->exam->update(['seats_locked_at'=>null])};
    expect(fn()=>$this->service->assign($this->user,$this->exam,$data))->toThrow(ValidationException::class);
    expect($person->duties()->count())->toBe(0);
})->with(['room','session','center','person','unlocked']);

it('protects assigned invigilators and clears only duties when reopening the seat plan',function(){
    invigilatorSessions($this);$person=$this->service->save($this->user,$this->exam,$this->person);$this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person));
    expect(fn()=>$this->service->remove($this->user,$this->exam,$person))->toThrow(ValidationException::class);
    $exam=$this->exam->fresh();app(ScholarshipWorkflow::class)->reopenPlan($this->user,$exam,$exam->version,'Change campus assignment');
    expect($person->fresh())->not->toBeNull()->and($person->duties()->count())->toBe(0)->and($this->exam->examSessions()->count())->toBe(4);
});

it('protects published historical and unauthorized exams',function($kind){
    if($kind==='published')$this->exam->update(['published_at'=>now()]);
    if($kind==='historical')$this->exam->update(['historical_imported_at'=>now()]);
    if($kind==='unauthorized'){
        $this->user=Mockery::mock(User::class)->makePartial();$this->user->id=2;$this->user->shouldReceive('isSuperAdmin')->andReturnFalse();$this->user->shouldReceive('hasPermission')->andReturnFalse();
        $this->user->setRelation('roles',collect());$this->user->setRelation('campuses',User::findOrFail(2)->campuses()->get());
    }
    expect(fn()=>$this->service->save($this->user,$this->exam,$this->person))->toThrow($kind==='unauthorized'?\Symfony\Component\HttpKernel\Exception\HttpException::class:ValidationException::class);
})->with(['published','historical','unauthorized']);

it('round trips two worksheets preserving leading zeros and literal text',function(){
    $book=invigilatorBook($this);$book['Invigilators'][0]['staff_id']='000123';$book['Invigilators'][0]['notes']='=SUM(A1:A2)';
    $path=invigilatorWorkbookPath($this,$book);$read=app(ScholarshipInvigilatorWorkbook::class)->read($path);
    expect($read['Invigilators'][0]['staff_id'])->toBe('000123')->and($read['Invigilators'][0]['phone'])->toBe('012345678')->and($read['Invigilators'][0]['notes'])->toBe('=SUM(A1:A2)')->and($read['Room Assignments'][0]['session'])->toBe('1');
});

it('previews without retaining data and commits validated invigilators and room assignments',function(){
    invigilatorSessions($this);$version=$this->exam->fresh()->version;$book=invigilatorBook($this);
    $result=$this->service->import($this->user,$this->exam,$book,false,true,$version);
    expect($result['errors'])->toBeEmpty()->and($result['created'])->toBe(1)->and($result['assignments'])->toBe(1)
        ->and($this->exam->invigilators()->count())->toBe(0)->and($this->exam->invigilatorDuties()->count())->toBe(0)->and($this->exam->fresh()->version)->toBe($version);
    $this->service->import($this->user,$this->exam,$book,false,false,$version);
    expect($this->exam->invigilators()->count())->toBe(1)->and($this->exam->invigilatorDuties()->count())->toBe(1);
});

it('previews errors and rolls back the entire invalid import',function($kind){
    invigilatorSessions($this);$book=invigilatorBook($this);
    match($kind){'unknown-person'=>$book['Room Assignments'][0]['invigilator_code']='MISSING','unknown-center'=>$book['Room Assignments'][0]['exam_center']='Missing Center',
        'unknown-room'=>$book['Room Assignments'][0]['room']=999,'unknown-session'=>$book['Room Assignments'][0]['session']=99,
        'duplicate-duty'=>$book['Room Assignments'][]=$book['Room Assignments'][0],'duplicate-person'=>$book['Invigilators'][]=$book['Invigilators'][0],
        'missing-phone'=>$book['Invigilators'][0]['phone']=''};
    $version=$this->exam->fresh()->version;$result=$this->service->import($this->user,$this->exam,$book,false,true,$version);expect($result['errors'])->not->toBeEmpty();
    expect(fn()=>$this->service->import($this->user,$this->exam,$book,false,false,$version))->toThrow(ValidationException::class);
    expect($this->exam->invigilators()->count())->toBe(0)->and($this->exam->invigilatorDuties()->count())->toBe(0);
})->with(['unknown-person','unknown-center','unknown-room','unknown-session','duplicate-duty','duplicate-person','missing-phone']);

it('requires explicit replacement for existing person details and matching session room changes',function(){
    invigilatorSessions($this);$book=invigilatorBook($this);$this->service->import($this->user,$this->exam,$book,false,false,$this->exam->fresh()->version);
    $book['Invigilators'][0]['phone']='099999999';$book['Room Assignments'][0]['exam_center']='South Exam Center';
    $result=$this->service->import($this->user,$this->exam,$book,false,true,$this->exam->fresh()->version);expect($result['errors'])->not->toBeEmpty();
    $result=$this->service->import($this->user,$this->exam,$book,true,false,$this->exam->fresh()->version);
    expect($result['updated'])->toBe(1)->and($this->exam->invigilators()->first()->phone)->toBe('099999999')->and($this->exam->invigilatorDuties()->first()->exam_center_id)->toBe($this->exam->centers()->get()->last()->id);
});

it('rejects stale preview commits',function(){
    invigilatorSessions($this);$version=$this->exam->fresh()->version;$this->service->save($this->user,$this->exam,$this->person);
    expect(fn()=>$this->service->import($this->user,$this->exam,invigilatorBook($this),false,false,$version))->toThrow(ValidationException::class);
});

it('imports people without room assignments before seats and sessions are ready',function(){
    $this->exam->update(['seats_locked_at'=>null]);$book=invigilatorBook($this);$book['Room Assignments']=[];
    expect($this->service->import($this->user,$this->exam,$book,false,false,$this->exam->fresh()->version)['created'])->toBe(1);
});

it('prints per-subject invigilator names and phones independently for each room',function(){
    invigilatorSessions($this);$a=$this->service->save($this->user,$this->exam,$this->person);$b=$this->service->save($this->user,$this->exam,array_replace($this->person,['name_en'=>'SECOND INVIGILATOR','phone'=>'099999999']));
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$a));$this->service->assign($this->user,$this->exam,invigilatorDuty($this,$b,2));
    $data=scholarshipSeatReportData($this->exam->fresh(),'attendance-cover');expect($data['pages'])->toHaveCount(2);
    $north=collect($data['pages'])->firstWhere('center','North Exam Center');$south=collect($data['pages'])->firstWhere('center','South Exam Center');
    $texts=fn($page)=>collect($page['rows'])->flatMap(fn($r)=>$r['cells'])->flatMap(fn($c)=>$c['lines'])->pluck('text')->all();
    expect($texts($north))->toContain('EXTERNAL INVIGILATOR','012345678','SECOND INVIGILATOR','099999999','Khmer','Science')->and($texts($south))->not->toContain('EXTERNAL INVIGILATOR','012345678');
    expect(preg_match_all('~/Type\s*/Page(?!s)\b~',app(ScholarshipSeatReports::class)->pdf($data)))->toBe(2);
});

it('renders committee UI with HRM external Excel schedule and room assignment controls',function(){
    $request=invigilatorRequest($this,['tab'=>'exam-committee','exam_id'=>$this->exam->id]);$response=app(ScholarshipController::class)->index($request);
    $data=$response->getData();$html=view('western-scholarship._exam-committee',$data)->render();
    expect($html)->toContain('Staff Name English','HRM Staff','External Invigilator','Room Assignments','Sample Excel','Validate & Preview','Exam Session Schedule','System Access Assignments');
});

it('requires an acknowledged same-exam session preview before controller import',function(){
    $controller=app(ScholarshipInvigilatorController::class);
    expect(fn()=>$controller->import(invigilatorRequest($this,['token'=>str_repeat('x',64),'acknowledge'=>1]),$this->exam))->toThrow(ValidationException::class);
    invigilatorSessions($this);$path=invigilatorWorkbookPath($this,invigilatorBook($this));$file=new UploadedFile($path,'invigilators.xlsx',null,null,true);
    $preview=$controller->preview(invigilatorRequest($this,[],['file'=>$file]),$this->exam,app(ScholarshipInvigilatorWorkbook::class))->getData(true);
    expect($preview['token'])->toHaveLength(64)->and($this->exam->invigilators()->count())->toBe(0);
    $controller->import(invigilatorRequest($this,['token'=>$preview['token'],'acknowledge'=>1]),$this->exam);
    expect($this->exam->invigilators()->count())->toBe(1)->and($this->exam->invigilatorDuties()->count())->toBe(1);
    expect(fn()=>$controller->import(invigilatorRequest($this,['token'=>$preview['token'],'acknowledge'=>1]),$this->exam))->toThrow(ValidationException::class);
});

it('limits HRM choices to accessible active campus staff',function(){
    $user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->setRelation('roles',collect());$user->setRelation('campuses',User::findOrFail(2)->campuses()->get());
    $user->shouldReceive('isSuperAdmin')->andReturnFalse();
    Staff::findOrFail(2)->update(['status'=>false]);Staff::findOrFail(3)->update(['employment_status'=>'ended']);
    expect($this->service->staffOptions($user)->pluck('staff_code')->all())->toBe(['HRM-001']);
});

it('requires committee permission across every participating campus',function(){
    $user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->setRelation('roles',collect());$user->setRelation('campuses',User::findOrFail(2)->campuses()->get());
    $user->shouldReceive('isSuperAdmin')->andReturnFalse();$user->shouldReceive('hasPermission')->andReturnTrue();
    expect(fn()=>$this->service->save($user,$this->exam,$this->person))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('rejects impossible session times without saving any schedule',function(){
    expect(fn()=>$this->service->saveSessions($this->user,$this->exam,[['number'=>1,'exam_date'=>'2026-11-20','starts_at'=>'10:00','ends_at'=>'09:00']]))->toThrow(ValidationException::class);
    expect($this->exam->examSessions()->count())->toBe(0);
});

it('rolls back apply to all sessions when any session conflicts',function(){
    invigilatorSessions($this);$person=$this->service->save($this->user,$this->exam,$this->person);
    $south=$this->exam->centers()->get()->last()->id;$this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person,3,1,$south));
    expect(fn()=>$this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person)+['all_sessions'=>1]))->toThrow(ValidationException::class);
    expect($person->duties()->count())->toBe(1)->and($person->duties()->first()->exam_center_id)->toBe($south);
});

it('rejects malformed formula and headerless Excel files',function($kind){
    $path=invigilatorWorkbookPath($this,invigilatorBook($this));$zip=new ZipArchive();$zip->open($path);
    $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');
    if($kind==='formula')$sheet=str_replace('<is><t xml:space="preserve">INV-001</t></is>','<f>1+1</f><v>2</v>',$sheet);
    if($kind==='headers')$sheet=preg_replace('/<row r="1">.*?<\/row>/s','',$sheet);
    if($kind==='xml')$sheet='<!DOCTYPE worksheet [<!ENTITY bad SYSTEM "file:///invalid">]><worksheet>&bad;</worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml',$sheet);$zip->close();
    expect(fn()=>app(ScholarshipInvigilatorWorkbook::class)->read($path))->toThrow(ValidationException::class);
})->with(['formula','headers','xml']);

it('reads shared-string Excel cells used by Microsoft Excel',function(){
    $path=invigilatorWorkbookPath($this,invigilatorBook($this));$zip=new ZipArchive();$zip->open($path);
    $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');$sheet=str_replace('<c r="G2" s="0" t="inlineStr"><is><t xml:space="preserve">012345678</t></is></c>','<c r="G2" s="0" t="s"><v>0</v></c>',$sheet);
    $zip->addFromString('xl/worksheets/sheet1.xml',$sheet);$zip->addFromString('xl/sharedStrings.xml','<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>012345678</t></si></sst>');$zip->close();
    expect(app(ScholarshipInvigilatorWorkbook::class)->read($path)['Invigilators'][0]['phone'])->toBe('012345678');
});

it('continues automatic codes after importing a lowercase invigilator code',function(){
    $book=invigilatorBook($this);$book['Invigilators'][0]['invigilator_code']='inv-001';$book['Room Assignments']=[];
    $this->service->import($this->user,$this->exam,$book,false,false,$this->exam->fresh()->version);
    expect($this->service->save($this->user,$this->exam,$this->person)->code)->toBe('INV-002');
});

function linkInvigilatorSessions($test,array $numbers=[1,2],int $source=1,?int $center=null): void
{
    $test->service->linkSessions($test->user,$test->exam,[
        'version'=>$test->exam->fresh()->version,'exam_center_id'=>$center??$test->exam->centers()->first()->id,
        'session_ids'=>$test->exam->examSessions()->whereIn('number',$numbers)->pluck('id')->all(),
        'source_session_id'=>$test->exam->examSessions()->where('number',$source)->first()->id,'confirm'=>1,
    ]);
}

it('links the selected center using the explicitly chosen source and updates rooms from either session',function(){
    invigilatorSessions($this);$a=$this->service->save($this->user,$this->exam,$this->person);
    $b=$this->service->save($this->user,$this->exam,array_replace($this->person,['name_en'=>'OTHER PERSON']));
    $this->exam->candidates()->where('campus_id',1)->first()->update(['room_number'=>2]);
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$a,1));
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$b,2));
    linkInvigilatorSessions($this);
    expect($a->duties()->count())->toBe(2)->and($b->duties()->count())->toBe(0)->and($this->exam->sessionLinks()->count())->toBe(1);
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$a,2,2));
    expect($a->duties()->pluck('room_number')->all())->toBe([2,2]);
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$a,1,1));
    expect($a->duties()->pluck('room_number')->all())->toBe([1,1]);
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$b,2,2));
    expect($b->duties()->pluck('room_number')->all())->toBe([2,2]);
    expect($this->exam->examSessions()->count())->toBe(4);
});

it('removes a linked duty from all group members and keeps other sessions',function(){
    invigilatorSessions($this);$person=$this->service->save($this->user,$this->exam,$this->person);linkInvigilatorSessions($this);
    foreach([2,3] as $session)$this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person,$session));
    $this->service->removeDuty($this->user,$this->exam,$person->duties()->where('session_id',invigilatorDuty($this,$person,2)['session_id'])->first());
    expect($person->duties()->count())->toBe(1)->and($person->duties()->first()->session->number)->toBe(3);
});

it('unlinks without changing duties and then lets sessions change independently',function(){
    invigilatorSessions($this);$person=$this->service->save($this->user,$this->exam,$this->person);linkInvigilatorSessions($this);
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person));$before=$person->duties()->get()->toArray();
    $this->service->unlinkSessions($this->user,$this->exam,$this->exam->sessionLinks()->first(),$this->exam->fresh()->version);
    expect($person->duties()->get()->toArray())->toBe($before);
    $this->service->removeDuty($this->user,$this->exam,$person->duties()->first());
    expect($person->duties()->count())->toBe(1)->and($this->exam->sessionLinks()->count())->toBe(0);
});

it('supports separate linked groups per exam center',function(){
    invigilatorSessions($this);$north=$this->exam->centers()->first()->id;$south=$this->exam->centers()->get()->last()->id;
    linkInvigilatorSessions($this,[1,2],1,$north);linkInvigilatorSessions($this,[1,3],1,$south);
    $a=$this->service->save($this->user,$this->exam,$this->person);$b=$this->service->save($this->user,$this->exam,array_replace($this->person,['name_en'=>'SOUTH PERSON']));
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$a,2,1,$north));
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$b,3,1,$south));
    expect($a->duties()->with('session')->get()->pluck('session.number')->sort()->values()->all())->toBe([1,2]);
    expect($b->duties()->with('session')->get()->pluck('session.number')->sort()->values()->all())->toBe([1,3]);
});

it('rolls back links when a source duty conflicts with another center',function(){
    invigilatorSessions($this);$person=$this->service->save($this->user,$this->exam,$this->person);
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person));
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person,2,1,$this->exam->centers()->get()->last()->id));
    $before=$person->duties()->get()->toArray();
    expect(fn()=>linkInvigilatorSessions($this))->toThrow(ValidationException::class);
    expect($person->duties()->get()->toArray())->toBe($before)->and($this->exam->sessionLinks()->count())->toBe(0);
});

it('validates room subject availability in every linked session',function(){
    invigilatorSessions($this);$person=$this->service->save($this->user,$this->exam,$this->person);
    $this->exam->subjects()->where('name','English')->delete();linkInvigilatorSessions($this,[1,4]);
    expect(fn()=>$this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person)))->toThrow(ValidationException::class);
    expect($person->duties()->count())->toBe(0);
});

it('requires unlinking an existing group before changing its members',function(){
    invigilatorSessions($this);linkInvigilatorSessions($this);
    expect(fn()=>linkInvigilatorSessions($this,[2,3],2))->toThrow(ValidationException::class);
    expect($this->exam->sessionLinks()->count())->toBe(1);
});

it('rejects invalid or stale linking requests without changing assignments',function($kind){
    invigilatorSessions($this);$sessions=$this->exam->examSessions()->get();
    $input=['version'=>$this->exam->fresh()->version,'exam_center_id'=>$this->exam->centers()->first()->id,'session_ids'=>$sessions->take(2)->pluck('id')->all(),'source_session_id'=>$sessions[0]->id,'confirm'=>1];
    match($kind){'one-session'=>$input['session_ids']=[$sessions[0]->id],'duplicate'=>$input['session_ids']=[$sessions[0]->id,$sessions[0]->id],
        'foreign-session'=>$input['session_ids'][1]=9999,'source-not-selected'=>$input['source_session_id']=$sessions[2]->id,'foreign-center'=>$input['exam_center_id']=9999,
        'stale'=>$input['version']=0,'unconfirmed'=>$input['confirm']=0,'unlocked'=>$this->exam->update(['seats_locked_at'=>null]),'published'=>$this->exam->update(['published_at'=>now()])};
    expect(fn()=>$this->service->linkSessions($this->user,$this->exam,$input))->toThrow(ValidationException::class);
    expect($this->exam->sessionLinks()->count())->toBe(0);
})->with(['one-session','duplicate','foreign-session','source-not-selected','foreign-center','stale','unconfirmed','unlocked','published']);

it('rejects stale unlink and preserves the group',function(){
    invigilatorSessions($this);linkInvigilatorSessions($this);
    expect(fn()=>$this->service->unlinkSessions($this->user,$this->exam,$this->exam->sessionLinks()->first(),0))->toThrow(ValidationException::class);
    expect($this->exam->sessionLinks()->count())->toBe(1);
});

it('synchronizes Excel imports and expands the preview to all linked sessions',function(){
    invigilatorSessions($this);linkInvigilatorSessions($this);$book=invigilatorBook($this);
    $preview=$this->service->import($this->user,$this->exam,$book,false,true,$this->exam->fresh()->version);
    expect($preview['errors'])->toBeEmpty()->and($preview['assignments'])->toBe(2)->and(array_column($preview['duties'],'session'))->toBe([1,2]);
    expect($this->exam->invigilatorDuties()->count())->toBe(0);
    $this->service->import($this->user,$this->exam,$book,false,false,$this->exam->fresh()->version);
    $this->exam->candidates()->where('campus_id',1)->first()->update(['room_number'=>2]);
    $book['Room Assignments'][0]['session']=2;$book['Room Assignments'][0]['room']=2;
    $this->service->import($this->user,$this->exam,$book,true,false,$this->exam->fresh()->version);
    expect($this->exam->invigilatorDuties()->pluck('room_number')->all())->toBe([2,2]);
});

it('rejects contradictory Excel rows across linked sessions and accepts matching rows',function(){
    invigilatorSessions($this);linkInvigilatorSessions($this);$this->exam->candidates()->where('campus_id',1)->first()->update(['room_number'=>2]);
    $book=invigilatorBook($this);$book['Room Assignments'][]=array_replace($book['Room Assignments'][0],['session'=>2,'room'=>2]);
    $result=$this->service->import($this->user,$this->exam,$book,true,true,$this->exam->fresh()->version);
    expect(implode(' ',$result['errors']))->toContain('Linked sessions contain different rooms');
    expect(fn()=>$this->service->import($this->user,$this->exam,$book,true,false,$this->exam->fresh()->version))->toThrow(ValidationException::class);
    expect($this->exam->invigilators()->count())->toBe(0)->and($this->exam->invigilatorDuties()->count())->toBe(0);
    $book['Room Assignments'][1]['room']=1;
    $result=$this->service->import($this->user,$this->exam,$book,true,false,$this->exam->fresh()->version);
    expect($result['assignments'])->toBe(2)->and($this->exam->invigilatorDuties()->count())->toBe(2);
});

it('uses session two as the source for a three-session group and preserves its schedule',function(){
    invigilatorSessions($this);$person=$this->service->save($this->user,$this->exam,$this->person);
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person,2));$schedule=$this->exam->examSessions()->get()->toArray();
    linkInvigilatorSessions($this,[1,2,3],2);
    expect($person->duties()->count())->toBe(3)->and($this->exam->examSessions()->get()->toArray())->toBe($schedule);
    $data=scholarshipSeatReportData($this->exam->fresh(),'attendance-cover');
    $north=collect($data['pages'])->firstWhere('center','North Exam Center');
    $names=collect($north['rows'])->flatMap(fn($r)=>$r['cells'])->flatMap(fn($c)=>$c['lines'])->pluck('text');
    expect($names->filter(fn($text)=>$text==='EXTERNAL INVIGILATOR')->count())->toBe(3);
});

it('can explicitly use an empty source to clear and link the selected center sessions',function(){
    invigilatorSessions($this);$person=$this->service->save($this->user,$this->exam,$this->person);
    $this->service->assign($this->user,$this->exam,invigilatorDuty($this,$person,2));
    linkInvigilatorSessions($this);expect($person->duties()->count())->toBe(0)->and($this->exam->sessionLinks()->count())->toBe(1);
});

it('does not break an existing link by importing a different center assignment',function(){
    invigilatorSessions($this);$book=invigilatorBook($this);
    $this->service->import($this->user,$this->exam,$book,false,false,$this->exam->fresh()->version);linkInvigilatorSessions($this);
    $before=$this->exam->invigilatorDuties()->get()->toArray();$book['Room Assignments'][0]['exam_center']='South Exam Center';
    $result=$this->service->import($this->user,$this->exam,$book,true,true,$this->exam->fresh()->version);
    expect($result['errors'])->not->toBeEmpty()->and($this->exam->invigilatorDuties()->get()->toArray())->toBe($before);
});

it('protects linking and unlinking with committee permissions',function(){
    invigilatorSessions($this);linkInvigilatorSessions($this);
    $denied=Mockery::mock(User::class)->makePartial();$denied->id=2;$denied->setRelation('roles',collect());$denied->setRelation('campuses',User::findOrFail(2)->campuses()->get());
    $denied->shouldReceive('isSuperAdmin')->andReturnFalse();$denied->shouldReceive('hasPermission')->andReturnFalse();
    expect(fn()=>$this->service->linkSessions($denied,$this->exam,[]))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(fn()=>$this->service->unlinkSessions($denied,$this->exam,$this->exam->sessionLinks()->first(),$this->exam->fresh()->version))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
