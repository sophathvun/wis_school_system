<?php

use App\Http\Controllers\G9ExamResultController;
use App\Models\{G9ExamCandidate,User};
use App\Support\{G9ExamFields,G9ResultReport,G9ScoringReport};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/G12NationalExam.php';

beforeEach(function(){
    createG12NationalSchema();
    Schema::create('tb_academic_year',function(Blueprint $t){$t->id();$t->string('academic_year');$t->softDeletes();});
    DB::table('tb_academic_year')->insert(['id'=>1,'academic_year'=>'2025-2026']);
    foreach(['2026_11_04_000002_create_g9_exam_candidates','2026_11_04_000004_preserve_g9_legacy_tracking',
        '2026_11_06_000002_add_g9_exam_attendance','2026_11_06_000003_add_g9_absence_reason','2026_11_06_000004_add_g9_result_tabs'] as $migration)
        (require database_path('migrations/'.$migration.'.php'))->up();
    $this->reports=app(G9ResultReport::class);$this->controller=app(G9ExamResultController::class);
});

function g9ReportCandidate(string $code,array $data=[],array $attributes=[]): G9ExamCandidate
{
    return G9ExamCandidate::create(array_replace(['academic_year_id'=>1,'campus_id'=>1,'student_code'=>$code,'name_en'=>'REPORT STUDENT '.$code,'name_kh'=>'សិស្សប្រឡង '.$code,
        'data'=>array_replace(array_fill_keys(array_keys(G9ExamFields::GROUPS['National Examination Scores']),20),[
            'eoy_average'=>70,'score_math'=>80,'exam_language'=>'Khmer','exam_center'=>'មណ្ឌលប្រឡង ភ្នំពេញថ្មី','room_number'=>'1','seat_number'=>$code,
            'grade'=>'9A','gender'=>'Female','school_name'=>'វិទ្យាល័យ សាលាវេស្ទើនអន្តរជាតិ (ភ្នំពេញថ្មី)','date_of_birth'=>'2011-06-11','exam_date'=>'2026-08-04'], $data)],$attributes));
}

function g9ReportPages($test,string $type,array $filters=[],?User $user=null): array
{
    $response=$test->controller->report(g12Request($user??User::find(1),$filters+['academic_year'=>1]),$type,'print',$test->reports);
    return $response->getOriginalContent()->getData()['pages'];
}

it('exports all matching candidates in natural room and seat order instead of the current UI page',function(){
    for($i=30;$i>=1;$i--)g9ReportCandidate((string)$i);
    g9ReportCandidate('PRIVATE',[],['campus_id'=>2]);
    $before=G9ExamCandidate::all()->toJson();
    $pages=g9ReportPages($this,'list',['campus'=>1,'page'=>2,'per_page'=>25]);
    expect($pages)->toHaveCount(2)->and($pages[0]['tables'][0]['rows'])->toHaveCount(25)
        ->and($pages[1]['tables'][0]['rows'])->toHaveCount(5)
        ->and(array_map(fn($r)=>$r['cells'][0]['value'],array_slice($pages[0]['tables'][0]['rows'],0,3)))->toBe(['1','2','3'])
        ->and($pages[1]['tables'][0]['rows'][4]['cells'][0]['value'])->toBe('30');
    $filtered=g9ReportPages($this,'list',['search'=>'STUDENT 30','campus'=>1]);
    expect($filtered[0]['tables'][0]['rows'])->toHaveCount(1)->and(G9ExamCandidate::all()->toJson())->toBe($before)
        ->and(DB::table('tb_g9_exam_event')->count())->toBe(0);
    if(getenv('G9_REPORT_QA')){
        $dir=storage_path('app/testing/g9-result-reports');if(!is_dir($dir))mkdir($dir,0755,true);
        $pdf=$this->reports->pdf($pages,'list');expect(preg_match_all('~/Type\s*/Page\b~',$pdf))->toBe(count($pages));
        file_put_contents($dir.'/full-list.pdf',$pdf);
    }
});

it('continues large room tables with repeated center context and one final overall total',function(){
    for($i=1;$i<=60;$i++)g9ReportCandidate((string)$i,['room_number'=>(string)$i]);
    $pages=g9ReportPages($this,'room');
    expect($pages)->toHaveCount(3)->and($pages[1]['header'])->toBeFalse()
        ->and($pages[1]['tables'][0]['rows'][0]['section'])->toContain('(បន្ត)');
    $rows=collect($pages)->flatMap(fn($p)=>$p['tables'][0]['rows']);
    expect($rows->filter(fn($r)=>!isset($r['section'])&&empty($r['total'])))->toHaveCount(60)
        ->and($rows->last()['cells'][1]['value'])->toBe(60);
    if(getenv('G9_REPORT_QA')){
        $dir=storage_path('app/testing/g9-result-reports');if(!is_dir($dir))mkdir($dir,0755,true);
        file_put_contents($dir.'/full-room.pdf',$this->reports->pdf($pages,'room'));
    }
});

it('keeps room statistics separate across centers and exam languages and reconciles grade totals',function(){
    g9ReportCandidate('1');g9ReportCandidate('2',['exam_language'=>'English']);
    g9ReportCandidate('3',['exam_center'=>'Other center','score_math'=>0,'eoy_average'=>0]);
    g9ReportCandidate('4',[],['attendance_status'=>'absent']);g9ReportCandidate('5',['score_math'=>null]);
    $pages=g9ReportPages($this,'room');$rows=collect($pages)->flatMap(fn($p)=>$p['tables'][0]['rows']);
    $total=$rows->last()['cells'];
    expect($total[1]['value'])->toBe(5)->and($total[3]['value'])->toBe(4)->and($total[5]['value'])->toBe(.8)
        ->and($total[15]['value'])->toBe(1)->and($total[17]['value'])->toBe(.25);
    $roomRows=$rows->filter(fn($r)=>!isset($r['section'])&&empty($r['total']));
    expect($roomRows)->toHaveCount(3);
    $graded=0;foreach([6,9,12,15] as $ci)$graded+=$total[$ci]['value'];
    expect($graded)->toBe(3);
});

it('calculates subject statistics at half maximum using only entered scores of present eligible candidates',function(){
    g9ReportCandidate('1',['score_chemistry'=>12.5]);g9ReportCandidate('2',['score_chemistry'=>12.499]);
    g9ReportCandidate('3',['score_chemistry'=>null]);g9ReportCandidate('4',['score_chemistry'=>25],['attendance_status'=>'absent']);
    g9ReportCandidate('5',['exam_language'=>'English','score_english'=>50]);
    $tables=g9ReportPages($this,'subject')[0]['tables'];
    $chemistry=array_column($tables[0]['rows'][1]['cells'],'value');
    expect($chemistry)->toBe([2,'គីមីវិទ្យា',2,2,1,1,.5,1,1,.5]);
    $english=array_column($tables[1]['rows'][10]['cells'],'value');
    expect(array_slice($english,2))->toBe([0,0,0,0,null,0,0,null]);
    $full=g9ReportPages($this,'list',['result'=>'pending']);
    expect($full[0]['tables'][0]['rows'])->toHaveCount(1);
});

it('uses configured center result and DoEYS dates and signers',function(){
    g9ReportCandidate('1');
    DB::table('tb_g9_exam_year_setting')->insert(['academic_year_id'=>1,'data'=>json_encode([
        'resultDate'=>'Year date','report_centers'=>[['exam_center'=>'មណ្ឌលប្រឡង ភ្នំពេញថ្មី','location'=>'ខេត្តព្រះសីហនុ','signer'=>'ប្រធានមណ្ឌល','signer_name'=>'អ្នកចុះហត្ថលេខា',
            'dates'=>['resultDate'=>['solar'=>'Center result date','lunar'=>'Center lunar date'],'resultDateDOEYS'=>['solar'=>'Center DoEYS date','lunar'=>'DoEYS lunar date']]]],
    ]),'version'=>1,'updated_by'=>1]);
    expect(g9ReportPages($this,'list')[0])->toMatchArray(['solar'=>'Center result date','lunar'=>'Center lunar date','signer_name'=>'អ្នកចុះហត្ថលេខា'])
        ->and(g9ReportPages($this,'doeys')[0])->toMatchArray(['solar'=>'Center DoEYS date','lunar'=>'DoEYS lunar date']);
});

it('enforces campus and statistics permissions for every output format',function(){
    g9ReportCandidate('1');g9ReportCandidate('2',[],['campus_id'=>2]);$user=User::find(2);
    foreach(['print','excel','pdf'] as $format)expect(fn()=>$this->controller->report(g12Request($user,['academic_year'=>1]),'list',$format,$this->reports))->toThrow(HttpException::class);
    foreach(['view','results'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    expect(g9ReportPages($this,'list',[],$user)[0]['tables'][0]['rows'])->toHaveCount(1);
    foreach(['room','subject','doeys','branch','wis'] as $type)foreach(['print','excel','pdf'] as $format)
        expect(fn()=>$this->controller->report(g12Request($user,['academic_year'=>1]),$type,$format,$this->reports))->toThrow(HttpException::class);
    DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.results-statistics')->value('id'),'allowed'=>true]);
    expect(g9ReportPages($this,'branch',[],$user)[0]['tables'][0]['rows'][1]['cells'][1]['value'])->toBe(1);
    expect(fn()=>g9ReportPages($this,'list',['campus'=>2],$user))->toThrow(HttpException::class);
});

it('rejects invalid formats filters and empty reports',function(){
    expect(fn()=>g9ReportPages($this,'list'))->toThrow(HttpException::class);
    g9ReportCandidate('1');
    expect(fn()=>$this->controller->report(g12Request(User::find(1),['academic_year'=>1]),'secret','pdf',$this->reports))->toThrow(HttpException::class)
        ->and(fn()=>$this->controller->report(g12Request(User::find(1),['academic_year'=>1]),'list','csv',$this->reports))->toThrow(HttpException::class)
        ->and(fn()=>g9ReportPages($this,'list',['result'=>'fake']))->toThrow(ValidationException::class);
});

it('generates valid Khmer PDFs and styled typed Excel workbooks for all six reports',function(){
    for($i=1;$i<=32;$i++)g9ReportCandidate(sprintf('%03d',$i),[
        'exam_center'=>$i<=16?'មណ្ឌលប្រឡង ភ្នំពេញថ្មី':'មណ្ឌលប្រឡង ខេត្តព្រះសីហនុ','exam_language'=>$i<=8?'English':'Khmer',
        'room_number'=>(string)($i<=25?1:2),'score_math'=>$i===31?null:($i%3===0?0:80),
    ],['campus_id'=>$i%2+1,'attendance_status'=>$i===32?'absent':null]);
    g9ReportCandidate('=SUM(A1:A2)',['school_name'=>'=HYPERLINK("https://example.invalid")']);
    foreach(array_keys(G9ResultReport::TITLES) as $type){
        $pages=g9ReportPages($this,$type);
        $print=$this->controller->report(g12Request(User::find(1),['academic_year'=>1]),$type,'print',$this->reports);
        expect($print->getContent())->toContain(G9ResultReport::TITLES[$type]);
        $pdf=$this->reports->pdf($pages,$type);expect(substr($pdf,0,5))->toBe('%PDF-')
            ->and(preg_match_all('~/Type\s*/Page\b~',$pdf))->toBe(count($pages));
        $xlsx=$this->reports->xlsx($pages,$type);$path=tempnam(sys_get_temp_dir(),'g9-test-');file_put_contents($path,$xlsx);
        try{
            $zip=new ZipArchive;expect($zip->open($path))->toBeTrue();
            for($i=0;$i<$zip->numFiles;$i++){
                $xml=$zip->getFromIndex($i);$doc=new DOMDocument;expect($doc->loadXML($xml,LIBXML_NONET))->toBeTrue();
                expect($doc->getElementsByTagName('f')->length)->toBe(0);
            }
            $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');
            expect($sheet)->toContain('orientation="portrait"','fitToWidth="1"','<mergeCells');
            $zip->close();
        }finally{unlink($path);}
        if(getenv('G9_REPORT_QA')){
            $dir=storage_path('app/testing/g9-result-reports');if(!is_dir($dir))mkdir($dir,0755,true);
            file_put_contents($dir.'/'.$type.'.pdf',$pdf);file_put_contents($dir.'/'.$type.'.xlsx',$xlsx);file_put_contents($dir.'/'.$type.'.html',$print->getContent());
        }
    }
});
