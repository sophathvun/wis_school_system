<?php

use App\Http\Controllers\ScholarshipController;
use App\Models\{ScholarshipExam,ScholarshipPaper,SchoolInfo,User};
use App\Services\ScholarshipWorkflow;
use App\Support\{ScholarshipSubjectOrder,ScholarshipSeatReports,ScholarshipSeatWorkbook};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
beforeEach(fn()=>createScholarshipSchema());

function scholarshipOrderRequest(array $data,array $denied=[],array $campuses=[1,2]): Request
{
    DB::table('access_user_campuses')->where('user_id',1)->delete();
    foreach($campuses as $id)DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>$id]);
    $user=Mockery::mock(User::class)->makePartial();$user->id=1;
    $user->setRelation('roles',User::findOrFail(1)->roles()->get());
    $user->shouldReceive('isSuperAdmin')->andReturnFalse();
    $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>SchoolInfo::whereIn('id',$campuses));
    $user->shouldReceive('hasPermission')->andReturnUsing(fn($code,$campus)=>in_array($campus,$campuses,true)&&!in_array($code,$denied,true));
    $request=Request::create('/students/western-scholarship/subject-order','POST',$data);
    $request->headers->set('Accept','application/json');$request->setUserResolver(fn()=>$user);return $request;
}

it('applies defaults by grade band and independent grade overrides with reset to inheritance',function(){
    $exam=scholarshipSeatReportExam();$order=app(ScholarshipSubjectOrder::class);$state=$order->state($exam);
    $primary=array_reverse($state['bands']['primary']);$secondary=$state['bands']['secondary'];$custom=$state['bands']['primary'];
    $controller=app(ScholarshipController::class);
    $response=$controller->saveSubjectOrder(scholarshipOrderRequest(['version'=>$exam->version,'bands'=>['primary'=>$primary,'secondary'=>$secondary],'grades'=>[2=>$custom]]),$exam)->getData(true);
    $exam=$exam->fresh();
    expect($response['message'])->toBe('Subject exam order saved.')->and($exam->subject_orders['grades'][2])->toBe($custom)
        ->and($order->forGrade($exam,1)->pluck('id')->all())->toBe($primary)
        ->and($order->forGrade($exam,3)->pluck('id')->all())->toBe($primary)
        ->and($order->forGrade($exam,2)->pluck('id')->all())->toBe($custom)
        ->and($order->forGrade($exam,7)->pluck('id')->all())->toBe($secondary);
    $controller->saveSubjectOrder(scholarshipOrderRequest(['version'=>$exam->version,'bands'=>['primary'=>$primary,'secondary'=>$secondary],'grades'=>[]]),$exam);
    expect($order->forGrade($exam->fresh(),2)->pluck('id')->all())->toBe($primary);
});

it('preserves subjects scores answer sheet links awards and seats while reordering a published exam',function(){
    $exam=scholarshipSeatReportExam(25);$exam->update(['published_at'=>now()]);$candidate=$exam->candidates()->first();$subject=$exam->subjects()->first();
    $paper=ScholarshipPaper::create(['subject_id'=>$subject->id,'grade_number'=>1,'question_count'=>20]);
    $candidate->scores()->create(['subject_id'=>$subject->id,'score'=>90,'source_paper_id'=>$paper->id,'entry_method'=>'scan']);
    $exam->awards()->create(['grade_number'=>1,'slot'=>'subject_'.$subject->id,'candidate_id'=>$candidate->id,'award_percent'=>25]);
    $before=['subjects'=>$exam->subjects()->get()->toArray(),'candidates'=>$exam->candidates()->get()->toArray(),'scores'=>$candidate->scores()->get()->toArray(),'papers'=>ScholarshipPaper::all()->toArray(),'awards'=>$exam->awards()->get()->toArray()];
    $state=app(ScholarshipSubjectOrder::class)->state($exam);$state['bands']['primary']=array_reverse($state['bands']['primary']);
    app(ScholarshipController::class)->saveSubjectOrder(scholarshipOrderRequest(['version'=>$exam->version]+$state),$exam);
    expect($exam->fresh()->published_at)->not->toBeNull()->and($exam->fresh()->seats_locked_at)->not->toBeNull()
        ->and($exam->subjects()->get()->toArray())->toBe($before['subjects'])->and($exam->candidates()->get()->toArray())->toBe($before['candidates'])
        ->and($candidate->scores()->get()->toArray())->toBe($before['scores'])->and(ScholarshipPaper::all()->toArray())->toBe($before['papers'])
        ->and($exam->awards()->get()->toArray())->toBe($before['awards']);
});

it('orders attendance columns by grade across subject continuation pages in print PDF and Excel',function(){
    $exam=scholarshipSeatReportExam(25);$order=app(ScholarshipSubjectOrder::class);$state=$order->state($exam);
    $primary=array_reverse($state['bands']['primary']);$custom=$state['bands']['primary'];
    app(ScholarshipController::class)->saveSubjectOrder(scholarshipOrderRequest(['version'=>$exam->version,'bands'=>['primary'=>$primary,'secondary'=>$state['bands']['secondary']],'grades'=>[2=>$custom]]),$exam);
    $data=scholarshipSeatReportData($exam->fresh(),'attendance');$names=$exam->subjects()->pluck('name','id');
    foreach(['North Exam Center'=>$primary,'South Exam Center'=>$custom] as $center=>$ids){
        $headers=collect($data['pages'])->where('center',$center)->flatMap(fn($page)=>collect($page['rows'])->filter(fn($row)=>count($row['cells'])>5&&$row['cells'][0]['style']==='candidate-table-head')->flatMap(fn($row)=>array_slice($row['cells'],5)))->flatMap(fn($cell)=>$cell['lines'])->pluck('text')->all();
        expect($headers)->toBe(array_map(fn($id)=>mb_strtoupper($names[$id]),$ids));
    }
    $html=view('western-scholarship.seat-report',$data)->render();expect($html)->toContain('GENERAL KNOWLEDGE','MATHEMATICS');
    $pdf=app(ScholarshipSeatReports::class)->pdf($data);expect(preg_match_all('~/Type\s*/Page(?!s)\b~',$pdf))->toBe(4);
    $path=tempnam(sys_get_temp_dir(),'exam-order-');
    try{
        file_put_contents($path,app(ScholarshipSeatWorkbook::class)->generate($data));$zip=new ZipArchive();$zip->open($path);$xml=$zip->getFromName('xl/worksheets/sheet1.xml');$zip->close();
        expect(strpos($xml,'GENERAL KNOWLEDGE'))->toBeLessThan(strpos($xml,'MATHEMATICS'));
    }finally{unlink($path);}
});

it('rejects incomplete duplicate foreign and wrong grade orders atomically',function($invalid){
    $exam=scholarshipSeatReportExam();$state=app(ScholarshipSubjectOrder::class)->state($exam);$input=['version'=>$exam->version]+$state;
    match($invalid){
        'missing'=>$input['bands']['primary']=[],
        'duplicate'=>$input['bands']['primary'][1]=$input['bands']['primary'][0],
        'foreign'=>$input['bands']['primary'][0]=9999,
        'secondary-in-primary'=>$input['bands']['primary'][0]=$input['bands']['secondary'][0],
        'grade-13'=>$input['grades']=[13=>$input['bands']['primary']],
        'incomplete-grade'=>$input['grades']=[2=>[$input['bands']['primary'][0]]],
        'wrong-grade'=>$input['grades']=[7=>$input['bands']['primary']],
        'stale'=>$input['version']=$exam->version+1,
    };
    expect(fn()=>app(ScholarshipController::class)->saveSubjectOrder(scholarshipOrderRequest($input),$exam))->toThrow(ValidationException::class);
    expect($exam->fresh()->subject_orders)->toBeNull()->and($exam->fresh()->version)->toBe($exam->version);
})->with(['missing','duplicate','foreign','secondary-in-primary','grade-13','incomplete-grade','wrong-grade','stale']);

it('requires settings permission for every exam campus and the settings tab',function($denied,$campuses){
    $exam=scholarshipSeatReportExam();$state=app(ScholarshipSubjectOrder::class)->state($exam);
    expect(fn()=>app(ScholarshipController::class)->saveSubjectOrder(scholarshipOrderRequest(['version'=>$exam->version]+$state,$denied,$campuses),$exam))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect($exam->fresh()->subject_orders)->toBeNull();
})->with([[['western-scholarship.settings'],[1,2]],[['western-scholarship.tab-settings'],[1,2]],[[],[1]]]);

it('retains subject identities and valid grade overrides when saving reordered subject settings',function(){
    $exam=scholarshipSeatReportExam();$exam->update(['seats_locked_at'=>null]);$state=app(ScholarshipSubjectOrder::class)->state($exam);
    $state['grades'][2]=array_reverse($state['bands']['primary']);
    $exam->update(['subject_orders'=>$state]);$subjectRows=$exam->subjects()->get()->reverse()->map->only(['id','name','grade_from','grade_to','max_score','weight'])->values()->all();
    $request=scholarshipOrderRequest([]);$exam=app(ScholarshipWorkflow::class)->saveExam($request->user(),$exam,['academic_year_id'=>1,'exam_date'=>'2026-11-20','room_capacity'=>40,'version'=>$exam->version,'subjects'=>$subjectRows]);
    expect($exam->subjects()->pluck('id')->all())->toBe(array_column($subjectRows,'id'));
    expect($exam->subjects()->count())->toBe(6)->and($exam->subject_orders['grades'][2])->toBe($state['grades'][2]);
});

it('filters grade specific subjects from inherited sessions and preserves overrides when subjects are added',function(){
    $exam=scholarshipSeatReportExam();$exam->update(['seats_locked_at'=>null]);
    $specific=$exam->subjects()->create(['name'=>'Grade Two Only','grade_from'=>2,'grade_to'=>2,'max_score'=>100,'weight'=>1,'position'=>6]);
    $order=app(ScholarshipSubjectOrder::class);$state=$order->state($exam);
    $primary=[$specific->id,...array_values(array_diff($state['bands']['primary'],[$specific->id]))];
    $custom=array_reverse($order->forGrade($exam,1)->pluck('id')->all());
    app(ScholarshipController::class)->saveSubjectOrder(scholarshipOrderRequest(['version'=>$exam->version,'bands'=>['primary'=>$primary,'secondary'=>$state['bands']['secondary']],'grades'=>[1=>$custom]]),$exam);
    $exam=$exam->fresh();
    expect($order->forGrade($exam,2)->first()->id)->toBe($specific->id)->and($order->forGrade($exam,3)->pluck('id')->all())->not->toContain($specific->id);
    $rows=$exam->subjects()->get()->map->only(['id','name','grade_from','grade_to','max_score','weight'])->all();
    $rows[]=['name'=>'New Primary Subject','grade_from'=>1,'grade_to'=>6,'max_score'=>100,'weight'=>1];
    $exam=app(ScholarshipWorkflow::class)->saveExam(scholarshipOrderRequest([])->user(),$exam,['academic_year_id'=>1,'exam_date'=>'2026-11-20','room_capacity'=>40,'version'=>$exam->version,'subjects'=>$rows]);
    $new=$exam->subjects()->where('name','New Primary Subject')->first();
    expect($order->forGrade($exam,1)->pluck('id')->all())->toBe([...$custom,$new->id]);
});
