<?php
// All records and private files live in the browser test's temporary directory.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\{G9CertificateTrackingController,G9CertificateNumberController};
use App\Models\{G9ExamCandidate,G9ExamCertificateFile,User};
use App\Services\{G9CertificateTracking,G9CertificateNumbers};
use App\Support\G9ExamFields;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Blade,DB,Schema};

$db=$argv[1];$path=$argv[2]??'/';$payload=json_decode(stream_get_contents(STDIN)?:'{}',true);
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'session.driver'=>'array','filesystems.disks.local.root'=>dirname($db).'/documents']);DB::purge('sqlite');
require_once __DIR__.'/../Fixtures/G12NationalExam.php';
if(!Schema::hasTable('users')){
    createG12NationalSchema(false);
    Schema::create('tb_academic_year',function(Blueprint $t){$t->id();$t->string('academic_year');$t->softDeletes();});
    DB::table('tb_academic_year')->insert([['id'=>1,'academic_year'=>'2025-2026'],['id'=>2,'academic_year'=>'2024-2025']]);
    foreach(['2026_11_04_000002_create_g9_exam_candidates','2026_11_04_000004_preserve_g9_legacy_tracking','2026_11_06_000002_add_g9_exam_attendance','2026_11_06_000003_add_g9_absence_reason','2026_11_07_000001_create_g9_certificate_tracking','2026_11_07_000002_create_g9_certificate_numbers'] as $migration)(require database_path('migrations/'.$migration.'.php'))->up();
    for($i=1;$i<=32;$i++)G9ExamCandidate::create(['academic_year_id'=>1,'campus_id'=>$i%2+1,'student_code'=>sprintf('%05d',$i),'name_en'=>'CERTIFICATE STUDENT '.$i,'name_kh'=>'សិស្សប្រឡង '.$i,
        'attendance_status'=>$i===5?'absent':null,'legacy_data'=>$i===6?['received_cert'=>'Withdrew','cert_received_name'=>'ImportedSignedCopy']:null,
        'data'=>array_replace(array_fill_keys(array_keys(G9ExamFields::GROUPS['National Examination Scores']),$i===4?0:20),[
            'eoy_average'=>$i===4?0:70,'score_math'=>$i===32?null:($i===4?0:80),'exam_language'=>'Khmer','room_number'=>$i<=15?'1':'2','seat_number'=>(string)$i,
            'exam_center'=>'វិ. សាលាវេស្ទើនអន្តរជាតិ (ភ្នំពេញថ្មី)','exam_date'=>'2026-08-04','grade'=>$i%2?'9A':'9B','gender'=>$i%2?'Male':'Female'])]);
    $admin=User::find(1);g12Request($admin);$service=app(G9CertificateTracking::class);
    (require database_path('migrations/2026_11_07_000003_add_g9_certificate_import_provenance.php'))->up();
    $service->save($admin,G9ExamCandidate::find(2),['status'=>'printed_signed','number'=>'G926-002','printed_signed_date'=>'2026-10-01','version'=>0],['provisional'=>UploadedFile::fake()->createWithContent('provisional.jpg',g12PhotoBytes())]);
    DB::table('tb_g9_exam_certificate_tracking')->where('candidate_id',2)->update(['import_source'=>json_encode(['certificate_date'=>'2026-12-01'])]);
    $service->save($admin,G9ExamCandidate::find(3),['status'=>'received','number'=>'G926-003','printed_signed_date'=>'2026-10-01','received_date'=>'2026-10-03','recipient'=>'Student 3','version'=>0],['receipt'=>UploadedFile::fake()->createWithContent('receipt.pdf',"%PDF-1.4\n% synthetic receipt\n%%EOF\n")]);
}
$user=User::findOrFail(1);auth()->setUser($user);$request=g12Request($user,$payload['data']??[],'http://localhost/g9-national-exams/certificates'.$path,$payload['method']??'GET');$temporary=[];
try{
    foreach($payload['files']??[] as $field=>$file){$temp=tempnam(sys_get_temp_dir(),'g9-browser-upload-');$temporary[]=$temp;file_put_contents($temp,base64_decode($file['bytes']));$request->files->set($field,new UploadedFile($temp,$file['name'],$file['mime'],UPLOAD_ERR_OK,true));}
    $controller=app(G9CertificateTrackingController::class);$service=app(G9CertificateTracking::class);
    if(str_starts_with($path,'/numbers/')){
        $numberController=app(G9CertificateNumberController::class);$numbers=app(G9CertificateNumbers::class);
        $result=match($path){'/numbers/settings'=>($payload['method']??'GET')==='POST'?$numberController->save($request,$numbers):$numberController->settings($request,$numbers),
            '/numbers/preview'=>$numberController->preview($request,$numbers),'/numbers/generate'=>$numberController->generate($request,$numbers)};
    }
    elseif(preg_match('~^/files/(\d+)$~',$path,$m))$result=$controller->file($request,G9ExamCertificateFile::findOrFail($m[1]),$service);
    elseif(preg_match('~^/(\d+)$~',$path,$m))$result=($payload['method']??'GET')==='POST'?$controller->save($request,G9ExamCandidate::findOrFail($m[1]),$service):$controller->show($request,G9ExamCandidate::findOrFail($m[1]),$service);
    else $result=match($path){'/options'=>$controller->options($request),'/data'=>$controller->data($request,$service),default=>$controller->index($request)};
    if($result instanceof Illuminate\View\View){
        preg_match("/@section\('content'\)(.*)@endsection/s",file_get_contents($result->getPath()),$match);
        $content=Blade::render(preg_replace('/^@vite.*$/m','',$match[1]),$result->getData());$type='text/html';
    }else{
        $type=$result->headers->get('Content-Type','application/json');
        if($result instanceof Symfony\Component\HttpFoundation\StreamedResponse){ob_start();$result->sendContent();$content=ob_get_clean();}else $content=$result->getContent();
    }
    echo json_encode(['status'=>200,'type'=>$type,'content'=>base64_encode($content)]);
}catch(Throwable $e){
    $status=$e instanceof Illuminate\Validation\ValidationException?422:($e instanceof Symfony\Component\HttpKernel\Exception\HttpException?$e->getStatusCode():500);
    echo json_encode(['status'=>$status,'type'=>'application/json','content'=>base64_encode(json_encode(['message'=>$e->getMessage(),'errors'=>$e instanceof Illuminate\Validation\ValidationException?$e->errors():[]]))]);
}finally{foreach($temporary as $temp)if(is_file($temp))unlink($temp);}
