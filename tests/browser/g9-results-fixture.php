<?php
// Synthetic candidates in the supplied temporary SQLite file only.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{User,G9ExamCandidate};
use App\Support\G9ExamFields;
use App\Http\Controllers\G9ExamResultController;
use Illuminate\Support\Facades\{DB,Schema,Blade};
use Illuminate\Database\Schema\Blueprint;
$db=$argv[1];$path=$argv[2]??'/';$input=json_decode(stream_get_contents(STDIN)?:'{}',true);
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'session.driver'=>'array']);DB::purge('sqlite');
require_once __DIR__.'/../Fixtures/G12NationalExam.php';
if(!Schema::hasTable('users')){
    createG12NationalSchema(false);
    Schema::create('tb_academic_year',function(Blueprint $t){$t->id();$t->string('academic_year');$t->softDeletes();});
    DB::table('tb_academic_year')->insert([['id'=>1,'academic_year'=>'2025-2026'],['id'=>2,'academic_year'=>'2024-2025']]);
    foreach(['2026_11_04_000002_create_g9_exam_candidates','2026_11_04_000004_preserve_g9_legacy_tracking','2026_11_06_000002_add_g9_exam_attendance','2026_11_06_000003_add_g9_absence_reason','2026_11_06_000004_add_g9_result_tabs'] as $migration)(require database_path('migrations/'.$migration.'.php'))->up();
    for($i=1;$i<=32;$i++)G9ExamCandidate::create(['academic_year_id'=>1,'campus_id'=>$i%2+1,'student_code'=>sprintf('%05d',$i),'name_en'=>'EXAM CANDIDATE '.$i,'name_kh'=>'សិស្សប្រឡង '.$i,
        'attendance_status'=>$i===32?'absent':null,'attendance_reason'=>$i===32?'Sick leave':null,
        'data'=>array_replace(array_fill_keys(array_keys(G9ExamFields::GROUPS['National Examination Scores']),20),[
            'eoy_average'=>70,'exam_language'=>$i%2?'English':'Khmer','score_math'=>$i===31?null:($i%3?80:0),'room_number'=>$i<16?'1':'2','seat_number'=>(string)$i,
            'exam_center'=>$i%2?'វិ. សាលាវេស្ទើនអន្តរជាតិ (ភ្នំពេញថ្មី) (ត្បូង)':'មណ្ឌលប្រឡង ខេត្តព្រះសីហនុ','grade'=>$i%2?'9A':'9B','gender'=>$i%2?'Male':'Female'])]);
    G9ExamCandidate::create(['academic_year_id'=>2,'campus_id'=>1,'student_code'=>'OLD','name_en'=>'Old candidate','data'=>[]]);
}
$user=User::findOrFail(1);auth()->setUser($user);$request=g12Request($user,$input,'http://localhost/g9-national-exams/results'.$path);
try{
    $controller=app(G9ExamResultController::class);
    if(preg_match('~^/reports/([^/]+)/([^/]+)$~',$path,$match))$result=$controller->report($request,$match[1],$match[2],app(\App\Support\G9ResultReport::class));
    else $result=match($path){'/options'=>$controller->options($request),'/data'=>$controller->data($request),default=>$controller->index($request)};
    if($result instanceof Illuminate\View\View){
        preg_match("/@section\('content'\)(.*)@endsection/s",file_get_contents($result->getPath()),$match);
        $content=Blade::render(preg_replace('/^@vite.*$/m','',$match[1]),$result->getData());$type='text/html';
    }else{$content=$result->getContent();$type=$result->getOriginalContent() instanceof \Illuminate\View\View?'text/html':$result->headers->get('Content-Type','application/json');}
    echo json_encode(['status'=>200,'type'=>$type,'content'=>base64_encode($content)]);
}catch(Throwable $e){echo json_encode(['status'=>$e instanceof \Symfony\Component\HttpKernel\Exception\HttpException?$e->getStatusCode():500,'type'=>'application/json','content'=>base64_encode(json_encode(['message'=>$e->getMessage()]))]);}
