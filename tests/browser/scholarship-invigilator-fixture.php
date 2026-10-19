<?php
// Synthetic in-memory data for the invigilator UI and generated reports.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','session.driver'=>'array']);\Illuminate\Support\Facades\DB::purge('sqlite');
require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';createScholarshipSchema();
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
$user=Mockery::mock(\App\Models\User::class)->makePartial();$user->id=1;$user->active_campus_id=1;
$user->shouldReceive('isSuperAdmin')->andReturnTrue();$user->shouldReceive('canAccessCampus')->andReturnTrue();
$user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>\App\Models\SchoolInfo::query());\Illuminate\Support\Facades\Auth::setUser($user);
$exam=scholarshipSeatReportExam(25);$exam->subjects()->where('name','General Knowledge')->delete();$exam=$exam->fresh();
$exam->candidates()->where('campus_id',1)->orderByDesc('room_seat')->first()->update(['room_number'=>2,'room_seat'=>1]);
foreach(\App\Models\Staff::all() as $staff)$staff->update(['gender'=>$staff->id%2?'Male':'Female','phone'=>'012345678']);
$service=app(\App\Services\ScholarshipInvigilators::class);$sessions=[];
foreach(range(1,4) as $number)$sessions[]=['number'=>$number,'exam_date'=>'2026-11-20','starts_at'=>sprintf('%02d:00',7+$number),'ends_at'=>sprintf('%02d:45',7+$number)];
$service->saveSessions($user,$exam,$sessions);
$a=$service->save($user,$exam,['source'=>'hrm','staff_id'=>1]);
$b=$service->save($user,$exam,['source'=>'external','staff_code'=>'EXT-001','name_en'=>'EXTERNAL INVIGILATOR','gender'=>'Female','campus'=>'External','position'=>'Examiner','phone'=>'098765432','organization'=>'Partner School']);
foreach([$a,$b] as $person)$service->assign($user,$exam,['invigilator_id'=>$person->id,'exam_center_id'=>$exam->centers()->first()->id,'room_number'=>1,'all_sessions'=>1]);
$exam=$exam->fresh();
if(($argv[1]??'')==='linked'){
    $service->linkSessions($user,$exam,['version'=>$exam->version,'exam_center_id'=>$exam->centers()->first()->id,
        'session_ids'=>$exam->examSessions()->whereIn('number',[1,2])->pluck('id')->all(),
        'source_session_id'=>$exam->examSessions()->where('number',1)->first()->id,'confirm'=>1]);
    $exam=$exam->fresh();
}
if(($argv[1]??'')==='reports'){
    $output=storage_path('app/testing/invigilator-report');if(!is_dir($output))mkdir($output,0775,true);
    $data=scholarshipSeatReportData($exam,'attendance-cover');$path=storage_path('app/public/school_logo/student_profile_report_logo.png');
    if(is_file($path))$data['logoSrc']='data:image/png;base64,'.base64_encode(file_get_contents($path));
    file_put_contents($output.'/cover.html',view('western-scholarship.seat-report',$data)->render());
    file_put_contents($output.'/cover.pdf',app(\App\Support\ScholarshipSeatReports::class)->pdf($data));
    file_put_contents($output.'/cover.xlsx',app(\App\Support\ScholarshipSeatWorkbook::class)->generate($data));
    $request=\Illuminate\Http\Request::create('/','GET',['sample'=>1]);$request->setUserResolver(fn()=>$user);
    $sample=app(\App\Http\Controllers\ScholarshipInvigilatorController::class)->download($request,$exam,app(\App\Support\ScholarshipInvigilatorWorkbook::class));
    file_put_contents($output.'/invigilators-sample.xlsx',$sample->getContent());
    $request=\Illuminate\Http\Request::create('/','GET');$request->setUserResolver(fn()=>$user);
    file_put_contents($output.'/invigilators-export.xlsx',app(\App\Http\Controllers\ScholarshipInvigilatorController::class)->download($request,$exam,app(\App\Support\ScholarshipInvigilatorWorkbook::class))->getContent());
    echo json_encode(['pages'=>count($data['pages'])]);exit;
}
$request=\Illuminate\Http\Request::create('http://localhost/students/western-scholarship','GET',['tab'=>'exam-committee','exam_id'=>$exam->id]);
$request->setUserResolver(fn()=>$user);$request->setLaravelSession(app('session')->driver());$app->instance('request',$request);
$data=app(\App\Http\Controllers\ScholarshipController::class)->index($request)->getData()+['errors'=>new \Illuminate\Support\ViewErrorBag()];
$template=file_get_contents(resource_path('views/western-scholarship/index.blade.php'));preg_match("/@section\('content'\)(.*)@endsection/s",$template,$matches);
echo \Illuminate\Support\Facades\Blade::render(preg_replace('/^@vite.*$/m','',$matches[1]),$data);
