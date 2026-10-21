<?php
// Synthetic certificate workspace. Never connects to the application's database.
require __DIR__.'/../../vendor/autoload.php';$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$file=$argv[1];$path=$argv[2]??'/';$payload=json_decode($argv[3]??'{}',true);$role=$argv[4]??'central';
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$file,'session.driver'=>'array']);Illuminate\Support\Facades\DB::purge('sqlite');
use Illuminate\Support\Facades\{DB,Auth,Blade};
use App\Models\{AcademicYear,ScholarshipStudentRank,User,SchoolInfo,ScholarshipCertificate};
use App\Services\ScholarshipCertificates;
use App\Http\Controllers\{ScholarshipController,ScholarshipCertificateController};
use App\Support\ScholarshipPermissions;
if(!Illuminate\Support\Facades\Schema::hasTable('users')){
    require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';createScholarshipSchema();
    require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';require_once __DIR__.'/../Fixtures/ScholarshipResultReports.php';
    (require database_path('migrations/2026_10_21_000004_create_scholarship_certificates.php'))->up();
    $exam=scholarshipResultReportExam();$exam->update(['published_at'=>now()]);
    foreach($exam->candidates()->get() as $candidate){$id=DB::table('tb_student')->insertGetId(['full_name_en'=>$candidate->snapshot['name_en']]);$candidate->update(['student_id'=>$id]);}
    foreach([1,2] as $campus)foreach([1,2,3] as $rank){$id=DB::table('tb_student')->insertGetId(['full_name_en'=>"CLASS STUDENT $campus RANK $rank"]);$e=DB::table('tb_student_enrollment')->insertGetId(['student_id'=>$id,'academic_year_id'=>1,'campus_id'=>$campus,'grade_id'=>1,'class_id'=>1]);ScholarshipStudentRank::create(['student_enrollment_id'=>$e,'rank'=>$rank,'assigned_by'=>2]);}
}
$user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->is_global=$role==='central';$user->setRelation('roles',collect());$user->setRelation('campuses',SchoolInfo::whereKey(1)->get());$user->shouldReceive('isSuperAdmin')->andReturnFalse();
$actions=$role==='readonly'?['view','tab-certificates']:array_keys(ScholarshipPermissions::catalog());
$user->shouldReceive('hasPermission')->andReturnUsing(fn($code)=>$code==='students.view'||in_array($code,array_map(fn($a)=>'western-scholarship.'.$a,$actions),true));Auth::setUser($user);
$request=Illuminate\Http\Request::create('http://localhost'.$path,empty($payload)?'GET':'POST',$payload+['tab'=>'certificates','academic_year_id'=>1]);$request->setUserResolver(fn()=>$user);$request->setLaravelSession(app('session')->driver());$app->instance('request',$request);
if(!empty($payload['certificate_list'])){$request->headers->set('X-Requested-With','XMLHttpRequest');$request->headers->set('Accept','application/json');}
$year=AcademicYear::find(1);$service=app(ScholarshipCertificates::class);$controller=app(ScholarshipCertificateController::class);
try{
    if($path==='/generate'){
        $setting=['issue_date'=>'2026-10-21','location'=>'Phnom Penh','scholarship_year'=>'2026-2027','signers'=>[['name'=>'SAMPLE VICE PRESIDENT','position'=>'Vice President'],['name'=>'SAMPLE REGISTRAR DIRECTOR','position'=>'Registrar Director']]];
        $service->update($user,$year,$service->settings($year)['version'],$setting,'certificate-settings');$service->assign($user,$year,$service->plan($year)['hash']);
        $ids=collect(['class','exam','subject'])->map(fn($type)=>ScholarshipCertificate::where('type',$type)->first()->id)->all();
        $folder=storage_path('app/testing/certificates');if(!is_dir($folder))mkdir($folder,0755,true);
        foreach([true,false] as $frame){$r=Illuminate\Http\Request::create('/','GET',['ids'=>$ids,'format'=>'pdf','frame'=>$frame]);$r->setUserResolver(fn()=>$user);$response=$controller->report($r,$year);file_put_contents($folder.'/'.($frame?'framed':'content-only').'.pdf',$response->getContent());}
        echo json_encode(['status'=>200,'content'=>base64_encode('Certificate PDFs generated.'),'type'=>'text/plain']);exit;
    }
    if(preg_match('~/certificates/1/(settings|layout|plan|assign|report)$~',$path,$m)){
        $result=$controller->{$m[1]}($request,$year);
        if($result instanceof Illuminate\View\View){$template=file_get_contents(resource_path('views/western-scholarship/certificate-report.blade.php'));$content=Blade::render(preg_replace('/^@vite.*$/m','',$template),$result->getData());$type='text/html';}
        else{$content=$result->getContent();$type=$result->headers->get('Content-Type');}
    }else{
        $result=$controller->index($request,array_filter(ScholarshipController::TABS,fn($key)=>ScholarshipPermissions::allows($user,ScholarshipPermissions::TABS[$key]),ARRAY_FILTER_USE_KEY));
        if($result instanceof Illuminate\Http\JsonResponse){echo json_encode(['status'=>$result->getStatusCode(),'content'=>base64_encode($result->getContent()),'type'=>'application/json']);exit;}
        $data=$result->getData();
        $template=file_get_contents(resource_path('views/western-scholarship/certificates.blade.php'));preg_match("/@section\('content'\)(.*)@endsection/s",$template,$m);$content=Blade::render(preg_replace('/^@vite.*$/m','',$m[1]),$data);$type='text/html';
    }
    echo json_encode(['status'=>200,'content'=>base64_encode($content),'type'=>$type]);
}catch(Throwable $e){$status=$e instanceof Illuminate\Validation\ValidationException?422:($e instanceof Symfony\Component\HttpKernel\Exception\HttpException?$e->getStatusCode():500);echo json_encode(['status'=>$status,'content'=>base64_encode(json_encode(['message'=>$e->getMessage(),'errors'=>$e instanceof Illuminate\Validation\ValidationException?$e->errors():null])),'type'=>'application/json']);}
