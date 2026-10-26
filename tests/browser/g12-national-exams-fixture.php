<?php
// Temporary SQLite and synthetic records only; never the application's configured database.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{User,G12ExamRecord,G12ExamCenter,G12ExamImport};
use App\Services\G12NationalExams;
use App\Http\Controllers\G12NationalExamController;
use Illuminate\Support\Facades\{DB,Schema,Blade};
$db=$argv[1];$path=$argv[2]??'/';$payload=json_decode(stream_get_contents(STDIN)?:'{}',true);
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'session.driver'=>'array','filesystems.disks.local.root'=>dirname($db).'/private']);DB::purge('sqlite');
require_once __DIR__.'/../Fixtures/G12NationalExam.php';
if(!Schema::hasTable('users')){
    createG12NationalSchema(false);config(['filesystems.disks.local.root'=>dirname($db).'/private']);app('filesystem')->forgetDisk('local');
    $user=User::findOrFail(1);$center=G12ExamCenter::create(['name'=>'MoEYS Test Center','name_key'=>'moeys test center','source_id'=>'001']);
    for($i=1;$i<=22;$i++)app(G12NationalExams::class)->save($user,g12ExamData(['student_id'=>sprintf('%05d',123+$i-1),'student_name_en'=>'NATIONAL EXAM STUDENT '.sprintf('%02d',$i),'center_id'=>$center->id,'photo_filename'=>$i===1?'120804049559121000001.jpg':null]));
}
$user=User::findOrFail(1);$request=g12Request($user,$payload+['tab'=>'records'],'http://localhost/g12-national-exams'.$path,empty($payload)?'GET':'POST');$temps=[];
auth()->setUser($user);
if($path==='/'||in_array(ltrim($path,'/'),\App\Support\G12NationalExamPermissions::PATHS,true)){
    $route=app('router')->getRoutes()->match($request);$request->setRouteResolver(fn()=>$route);
}
try{
    $controller=app(G12NationalExamController::class);$result=null;$type='application/json';
    foreach(['file','files'] as $field)if(isset($payload['_files'][$field])){
        $entries=$field==='files'?$payload['_files'][$field]:[$payload['_files'][$field]];$uploads=[];
        foreach($entries as $entry){$temp=dirname($db).'/'.bin2hex(random_bytes(8));file_put_contents($temp,base64_decode($entry['bytes']));$temps[]=$temp;$uploads[]=new Illuminate\Http\UploadedFile($temp,$entry['name'],null,null,true);}
        $request->files->set($field,$field==='files'?$uploads:$uploads[0]);
    }
    if($path==='/sample-workbook'){
        $rows=[g12ExamData(['_row'=>2,'student_id'=>'80001','student_name_en'=>'IMPORTED EXAM STUDENT','g12_exam_center'=>'MoEYS Test Center','received_soft_certificate'=>'Withdrew','received_soft_cert_date'=>'2025-10-21','received_soft_cert_by'=>'Imported Student'])];
        $batch=g12Batch($rows,[['excenter_id'=>'001','exam_center'=>'MoEYS Test Center']]);$result=['id'=>$batch->id,'preview'=>app(G12NationalExams::class)->inspect($user,$batch)];
    }elseif($path==='/sample-photo-zip'){
        $temp=dirname($db).'/sample-photos.zip';$temps[]=$temp;$zip=new ZipArchive();$zip->open($temp,ZipArchive::CREATE|ZipArchive::OVERWRITE);
        $zip->addFromString('photos/120804049559121000001.jpg',g12PhotoBytes());$zip->addFromString('photos/unmatched-test.jpg',g12PhotoBytes());$zip->close();
        $result=response(file_get_contents($temp),200,['Content-Type'=>'application/zip']);
    }elseif($path==='/state')$result=['records'=>G12ExamRecord::with('certificates')->get(),'files'=>App\Models\G12ExamFile::all(),'batches'=>G12ExamImport::get(['id','status','summary'])];
    elseif($path==='/meta')$result=$controller->meta($request);
    elseif($path==='/list')$result=$controller->list($request);
    elseif($path==='/report')$result=$controller->report($request);
    elseif($path==='/imports')$result=$controller->history($request);
    elseif($path==='/imports/template')$result=$controller->template($request);
    elseif($path==='/imports/preview')$result=$controller->preview($request);
    elseif(preg_match('~^/imports/(\d+)/(inspect|confirm)$~',$path,$m))$result=$controller->{$m[2]}($request,G12ExamImport::findOrFail($m[1]));
    elseif($path==='/photos/preview')$result=$controller->photoPreview($request);
    elseif(preg_match('~^/photos/(\d+)/(\d+)$~',$path,$m))$result=$controller->photoProcess($request,G12ExamImport::findOrFail($m[1]),(int)$m[2]);
    elseif(preg_match('~^/records/(\d+)/(files|photo)$~',$path,$m))$result=$controller->{$m[2]==='files'?'upload':'photo'}($request,G12ExamRecord::findOrFail($m[1]));
    elseif(preg_match('~^/records/(\d+)/certificates/(soft|hard)$~',$path,$m))$result=$controller->collection($request,G12ExamRecord::findOrFail($m[1]),$m[2]);
    elseif(preg_match('~^/records(?:/(\d+))?$~',$path,$m))$result=empty($payload)||array_keys($payload)===['tab']?$controller->show($request,G12ExamRecord::findOrFail($m[1])):$controller->save($request,isset($m[1])?G12ExamRecord::findOrFail($m[1]):null);
    elseif(preg_match('~^/centers(?:/(\d+))?$~',$path,$m))$result=$controller->saveCenter($request,isset($m[1])?G12ExamCenter::findOrFail($m[1]):null);
    elseif(preg_match('~^/files/(\d+)$~',$path,$m))$result=$controller->file($request,App\Models\G12ExamFile::findOrFail($m[1]));
    else $result=$controller->index($request);
    if($result instanceof Illuminate\View\View){
        $template=file_get_contents($result->getPath());preg_match("/@section\('content'\)(.*)@endsection/s",$template,$match);
        $content=Blade::render(preg_replace('/^@vite.*$/m','',$match[1]??$template),$result->getData()+['errors'=>new Illuminate\Support\ViewErrorBag]);$type='text/html';
        if($result->name()==='g12-national-exams.index')$content='<nav class="navbar navbar-vertical navbar-expand-md"><ul class="navbar-nav">'.view('layouts.partials.g12-national-exams-menu',['moeysMenuIcon'=>'/images/moeys.png'])->render().'</ul></nav><main class="page-wrapper">'.$content.'</main>';
    }elseif($result instanceof Symfony\Component\HttpFoundation\BinaryFileResponse){$content=file_get_contents($result->getFile()->getPathname());$type=$result->headers->get('Content-Type')??'application/octet-stream';}
    elseif($result instanceof Symfony\Component\HttpFoundation\StreamedResponse){ob_start();$result->sendContent();$content=ob_get_clean();$type=$result->headers->get('Content-Type')??'application/octet-stream';}
    elseif(is_array($result))$content=json_encode($result,JSON_UNESCAPED_UNICODE);else{$content=$result->getContent();$type=$result->headers->get('Content-Type')??'application/json';}
    echo json_encode(['status'=>200,'type'=>$type,'content'=>base64_encode($content)]);
}catch(Throwable $e){echo json_encode(['status'=>$e instanceof Illuminate\Validation\ValidationException?422:($e instanceof Symfony\Component\HttpKernel\Exception\HttpException?$e->getStatusCode():500),'type'=>'application/json','content'=>base64_encode(json_encode(['message'=>$e->getMessage(),'errors'=>$e instanceof Illuminate\Validation\ValidationException?$e->errors():null]))]);}
finally{foreach($temps as $temp)@unlink($temp);}
