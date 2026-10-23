<?php
// Isolated SQLite fixture and synthetic candidates only.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,Schema,Blade};
use App\Models\{ScholarshipExam,ScholarshipPaper,ScholarshipAnswerSheet};
use App\Http\Controllers\{ScholarshipAnswerSheetController,ScholarshipController};
use App\Support\ScholarshipAnswerSheetLayout;
$db=$argv[1];$path=$argv[2]??'/';$payload=json_decode(stream_get_contents(STDIN)?:'{}',true);
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'session.driver'=>'array','filesystems.disks.local.root'=>dirname($db).'/private']);DB::purge('sqlite');
require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';require_once __DIR__.'/../Fixtures/ScholarshipAnswerSheets.php';
if(!Schema::hasTable('users')){
    createScholarshipSchema();(require database_path('migrations/2026_10_22_000001_create_scholarship_answer_sheet_workflow.php'))->up();
    (require database_path('migrations/2026_10_22_000002_add_scholarship_question_scores.php'))->up();
    (require database_path('migrations/2026_10_22_000007_create_scholarship_teacher_key_sheets.php'))->up();
    [$exam,$user,$paper,$sheet]=answerSheetFixture('numbers',100);
}else{$exam=ScholarshipExam::first();$user=answerSheetUser();$paper=ScholarshipPaper::first();$sheet=ScholarshipAnswerSheet::first();}
$session=app('session')->driver();if(is_file($db.'.session'))foreach(json_decode(file_get_contents($db.'.session'),true) as $key=>$value)$session->put($key,$value);
$request=Illuminate\Http\Request::create('http://localhost'.$path,empty($payload)?'GET':'POST',$payload+['academic_year_id'=>1,'tab'=>'answer-sheets']);
$request->setUserResolver(fn()=>$user);$request->setLaravelSession($session);$app->instance('request',$request);$temps=[];
try{
    $controller=app(ScholarshipAnswerSheetController::class);
    if($path==='/batch-samples'){
        $prototype=$exam->candidates()->where('grade_number',1)->first();
        for($number=3;$number<=25;$number++){
            $code=sprintf('G01-%03d',$number);
            if($exam->candidates()->where('candidate_code',$code)->exists())continue;
            $candidate=$prototype->replicate();$candidate->candidate_code=$code;$candidate->room_seat=$number;$candidate->grade_exam_number=$number;
            $candidate->snapshot=array_replace($candidate->snapshot,['student_code'=>sprintf('BATCH%03d',$number),'name_en'=>'BATCH STUDENT '.$number]);$candidate->save();
        }
        $sheets=app(App\Services\ScholarshipAnswerSheets::class)->issue($user,$paper,$exam->candidates()->where('grade_number',1)->pluck('id')->all());
        $layout=app(ScholarshipAnswerSheetLayout::class);
        $wrong=new ScholarshipAnswerSheet(['token'=>str_repeat('0',24),'snapshot'=>$sheets->first()->snapshot]);
        $content=json_encode(['sheets'=>$sheets->map(fn($issued)=>['svg'=>$layout->svg($issued),'name'=>$issued->candidate->snapshot['name_en']])->all(),'wrong_svg'=>$layout->svg($wrong)]);$type='application/json';
    }
    elseif($path==='/sample.svg'){
        if(isset($payload['paper_id']))$sheet=ScholarshipAnswerSheet::where('paper_id',$payload['paper_id'])->firstOrFail();
        $content=app(ScholarshipAnswerSheetLayout::class)->svg($sheet);$type='image/svg+xml';
    }
    elseif($path==='/sample-layout.svg'){
        $sheet->snapshot=array_replace($sheet->snapshot,['layout_version'=>2,'question_count'=>(int)$payload['count'],'choices'=>array_slice(range('A','Z'),0,(int)$payload['choices'])]);
        $content=app(ScholarshipAnswerSheetLayout::class)->svg($sheet);$type='image/svg+xml';
    }
    elseif($path==='/read-layout'){$content=json_encode(app(ScholarshipAnswerSheetLayout::class)->read(base64_decode($payload['image']),$payload['configuration']));$type='application/json';}
    elseif($path==='/state'){$content=json_encode(['scores'=>App\Models\ScholarshipScore::all()->toArray(),'scans'=>App\Models\ScholarshipAnswerScan::all()->toArray()]);$type='application/json';}
    elseif(preg_match('~/papers/(\d+)/(teacher-key|teacher-key-scan)$~',$path,$m)){
        if($m[2]==='teacher-key-scan'){$f=dirname($db).'/'.bin2hex(random_bytes(8)).'.png';file_put_contents($f,base64_decode($payload['image']));$temps[]=$f;$request->files->set('image',new Illuminate\Http\UploadedFile($f,'teacher.png','image/png',null,true));}
        $result=$controller->{$m[2]==='teacher-key'?'teacherKeyIssue':'teacherKeyScan'}($request,ScholarshipPaper::findOrFail($m[1]));
    }
    elseif(preg_match('~/teacher-key-print/([a-f0-9]{32})$~',$path,$m))$result=$controller->teacherKeyPrint($request,$m[1]);
    elseif(preg_match('~/exams/(\d+)/(papers|scans)$~',$path,$m)){
        if($m[2]==='scans')foreach(['image','original'] as $name){$f=dirname($db).'/'.bin2hex(random_bytes(8)).'.png';file_put_contents($f,base64_decode($payload[$name]));$temps[]=$f;$request->files->set($name,new Illuminate\Http\UploadedFile($f,$name.'.png','image/png',null,true));}
        $result=$controller->{$m[2]==='papers'?'paper':'scan'}($request,ScholarshipExam::findOrFail($m[1]));
    }elseif(preg_match('~/papers/(\d+)/(key|approve|issue|link|unlink|update|delete)$~',$path,$m))$result=$controller->{$m[2]}($request,ScholarshipPaper::findOrFail($m[1]));
    elseif(preg_match('~/print/([a-f0-9]{32})$~',$path,$m))$result=$controller->print($request,$m[1]);
    elseif(preg_match('~/scans/(\d+)(?:/(verify|recheck|image))?$~',$path,$m))$result=$controller->{$m[2]??'details'}($request,App\Models\ScholarshipAnswerScan::findOrFail($m[1]));
    else $result=$controller->index($request,ScholarshipController::TABS);
    if(isset($result)){
        if($result instanceof Illuminate\View\View){$template=file_get_contents($result->getPath());preg_match("/@section\('content'\)(.*)@endsection/s",$template,$match);$content=Blade::render(preg_replace('/^@vite.*$/m','',$match[1]??$template),$result->getData());$type='text/html';}
        elseif($result instanceof Symfony\Component\HttpFoundation\BinaryFileResponse){$content=file_get_contents($result->getFile()->getPathname());$type='image/png';}
        else{$content=$result->getContent();$type=$result->headers->get('Content-Type')??'text/html';}
    }
    file_put_contents($db.'.session',json_encode($session->all()));
    echo json_encode(['status'=>200,'type'=>$type,'content'=>base64_encode($content)]);
}catch(Throwable $e){echo json_encode(['status'=>$e instanceof Illuminate\Validation\ValidationException?422:($e instanceof Symfony\Component\HttpKernel\Exception\HttpException?$e->getStatusCode():500),'type'=>'application/json','content'=>base64_encode(json_encode(['message'=>$e->getMessage(),'errors'=>$e instanceof Illuminate\Validation\ValidationException?$e->errors():null]))]);}
finally{foreach($temps as $file)unlink($file);}
