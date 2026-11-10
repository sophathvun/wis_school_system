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
    for($i=30;$i>=1;$i--)g9ReportCandidate((string)$i,$i===16?['score_math'=>0,'eoy_average'=>0]:[],$i===12?['attendance_status'=>'absent']:[]);
    g9ReportCandidate('PRIVATE',[],['campus_id'=>2]);
    $before=G9ExamCandidate::all()->toJson();
    $pages=g9ReportPages($this,'list',['campus'=>1,'page'=>2,'per_page'=>25]);
    expect($pages)->toHaveCount(2)->and($pages[0]['tables'][0]['rows'])->toHaveCount(25)
        ->and($pages[1]['tables'][0]['rows'])->toHaveCount(5)
        ->and(array_map(fn($r)=>$r['cells'][0]['value'],array_slice($pages[0]['tables'][0]['rows'],0,3)))->toBe(['1','2','3'])
        ->and($pages[1]['tables'][0]['rows'][4]['cells'][0]['value'])->toBe('30');
    expect($pages[0]['tables'][0]['rows'][11]['cells'][5]['value'])->toBe('ធ្លាក់')
        ->and($pages[0]['tables'][0]['rows'][11]['outcome'])->toBe('absent')
        ->and($pages[0]['list_totals']['absent'])->toBe(1);
    $filtered=g9ReportPages($this,'list',['search'=>'STUDENT 30','campus'=>1]);
    expect($filtered[0]['tables'][0]['rows'])->toHaveCount(1)->and(G9ExamCandidate::all()->toJson())->toBe($before)
        ->and(DB::table('tb_g9_exam_event')->count())->toBe(0);
    if(getenv('G9_REPORT_QA')){
        $dir=storage_path('app/testing/g9-result-reports');if(!is_dir($dir))mkdir($dir,0755,true);
        file_put_contents($dir.'/full-list.html',view('g9-national-exams.result-report',['pages'=>$pages,'type'=>'list'])->render());
        $pdf=$this->reports->pdf($pages,'list');file_put_contents($dir.'/full-list.pdf',$pdf);
        expect(preg_match_all('~/Type\s*/Page\b~',$pdf))->toBe(count($pages));
    }
});

it('continues large room tables with repeated center context and one final overall total',function(){
    for($i=1;$i<=60;$i++)g9ReportCandidate((string)$i,['room_number'=>(string)$i]);
    $pages=g9ReportPages($this,'room');
    expect($pages)->toHaveCount(2)->and($pages[1]['header'])->toBeFalse()
        ->and($pages[1]['tables'][0]['rows'][0]['section'])->toContain('(បន្ត)');
    $rows=collect($pages)->flatMap(fn($p)=>$p['tables'][0]['rows']);
    expect($rows->filter(fn($r)=>!isset($r['section'])&&empty($r['total'])))->toHaveCount(60)
        ->and($rows->last()['cells'][1]['value'])->toBe(60);
    if(getenv('G9_REPORT_QA')){
        $dir=storage_path('app/testing/g9-result-reports');if(!is_dir($dir))mkdir($dir,0755,true);
        file_put_contents($dir.'/full-room.pdf',$this->reports->pdf($pages,'room'));
        file_put_contents($dir.'/full-room.html',view('g9-national-exams.result-report',['pages'=>$pages,'type'=>'room'])->render());
    }
});

it('keeps room statistics separate across centers and exam languages and reconciles grade totals',function(){
    g9ReportCandidate('1');g9ReportCandidate('2',['exam_language'=>'English']);
    g9ReportCandidate('3',['exam_center'=>'Other center','score_math'=>0,'eoy_average'=>0]);
    g9ReportCandidate('4',[],['attendance_status'=>'absent']);g9ReportCandidate('5',['score_math'=>null]);
    $pages=g9ReportPages($this,'room');$rows=collect($pages)->flatMap(fn($p)=>$p['tables'][0]['rows']);
    $total=$rows->last()['cells'];
    expect($total[1]['value'])->toBe(5)->and($total[3]['value'])->toBe(4)->and($total[5]['value'])->toBe(.8)
        ->and($total[15]['value'])->toBe(2)->and($total[16]['value'])->toBe(2)->and($total[17]['value'])->toBe(.4);
    $roomRows=$rows->filter(fn($r)=>!isset($r['section'])&&empty($r['total']));
    expect($roomRows)->toHaveCount(3)
        ->and($roomRows->pluck('cells')->map(fn($cells)=>$cells[0]['value'])->values()->all())->toBe(['1','1','1']);
    $graded=0;foreach([6,9,12,15] as $ci)$graded+=$total[$ci]['value'];
    expect($graded)->toBe(4);
    $cards=$pages[0]['summary_cards'];
    expect(array_column($cards,'total'))->toBe([5,2,2])
        ->and(array_column($cards[0]['languages'],'total'))->toBe([1,4])
        ->and($cards[1]['languages'][0]['percentage'])->toBe(1.0)
        ->and($cards[1]['languages'][1]['percentage'])->toBe(.25)
        ->and($cards[2]['languages'][1]['percentage'])->toBe(.5)
        ->and(G9ExamCandidate::where('student_code','4')->first()->attendance_status)->toBe('absent');
    $wis=g9ReportPages($this,'wis');
    $wisTotal=collect($wis)->flatMap(fn($page)=>$page['tables'][0]['rows'])->last()['cells'];
    expect($wisTotal[15]['value'])->toBe(2)->and($wisTotal[17]['value'])->toBe(.4);
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

it('matches the DoEYS sample columns and reconciles rooms absences grades and pass rates',function(){
    g9ReportCandidate('1');g9ReportCandidate('2',['exam_language'=>'English']);
    g9ReportCandidate('3',['exam_center'=>'Other center','score_math'=>0,'eoy_average'=>0]);
    g9ReportCandidate('4',['room_number'=>'2'],['attendance_status'=>'absent']);
    g9ReportCandidate('5',['room_number'=>'2','score_math'=>null]);
    $before=G9ExamCandidate::all()->toJson();
    $page=g9ReportPages($this,'doeys')[0];$table=$page['tables'][0];
    $center=collect($table['rows'])->first(fn($row)=>$row['cells'][0]['value']==='ភ្នំពេញថ្មី')['cells'];
    expect($center[2]['value'])->toBe(4)->and($center[3]['value'])->toBe(4)
        ->and($center[4]['value'])->toBe(1)->and($center[5]['value'])->toBe(1);
    $total=array_column($table['rows'][2]['cells'],'value');
    expect($table['widths'])->toHaveCount(22)
        ->and(array_slice($total,0,8))->toBe(['សរុបរួម',3,5,5,1,1,.2,.2])
        ->and(array_slice($total,14,7))->toBe([2,2,.5,2,.5,2,.5])
        ->and(collect($table['rows'])->first(fn($row)=>$row['cells'][0]['value']==='ភ្នំពេញថ្មី')['cells'][1]['value'])->toBe(2)
        ->and($total[21])->toBe(App\Support\G9CandidateReport::khmerDate('2026-08-04'))
        ->and(G9ExamCandidate::all()->toJson())->toBe($before);
});

it('groups WIS campus rows under each exam center without repeating the academic year',function(){
    g9ReportCandidate('1');g9ReportCandidate('2',['exam_center'=>'Other center']);
    g9ReportCandidate('3',['exam_language'=>'English'],['attendance_status'=>'absent']);
    DB::table('tb_g9_exam_year_setting')->insert(['academic_year_id'=>1,'data'=>json_encode(['date_location'=>'ខេត្ត ព្រះសីហនុ','resultDate_iso'=>'2026-08-12']),'version'=>1,'updated_by'=>1]);
    $pages=g9ReportPages($this,'wis');$rows=collect($pages)->flatMap(fn($page)=>$page['tables'][0]['rows']);
    expect($rows->filter(fn($row)=>isset($row['section']))->pluck('section')->all())
        ->toBe(['មណ្ឌលប្រឡង៖ Other center','មណ្ឌលប្រឡង៖ មណ្ឌលប្រឡង ភ្នំពេញថ្មី'])
        ->and($rows->filter(fn($row)=>!isset($row['section'])&&empty($row['total'])))->toHaveCount(2)
        ->and($rows->last()['cells'][1]['value'])->toBe(3)
        ->and($rows->last()['cells'][15]['value'])->toBe(1)
        ->and($rows->filter(fn($row)=>!empty($row['total']))->pluck('cells')->map(fn($cells)=>$cells[0]['value'])->values()->all())->toBe(['សរុបមណ្ឌល','សរុបមណ្ឌល','សរុបរួម'])
        ->and($rows->last()['grand_total'])->toBeTrue();
    $html=view('g9-national-exams.result-report',['pages'=>$pages,'type'=>'wis'])->render();
    expect($html)->toContain('ស្ថិតិលទ្ធផលប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ','មន្ទីរអប់រំ យុវជន និងកីឡា ខេត្តព្រះសីហនុ','សាលាវេស្ទើនអន្តរជាតិ')->not->toContain('ឆ្នាំសិក្សា៖');
    expect($pages[0]['tables'][0]['headers'][0][0]['value'])->toBe('សាខា')
        ->and($pages[0]['tables'][0]['headers'][0][2]['value'])->toBe('បេក្ខជនមកប្រឡង')
        ->and($pages[0]['notes'])->toBe(['អវត្តមាន៖ 1 នាក់'])
        ->and($pages[0]['office_date'])->toBe('August 12, 2026')
        ->and($html)->toContain('ការិយាល័យសិក្សា','August 12, 2026')->not->toContain(' / KH',' / EN','បេក្ខជនមិនទាន់ពេញលេញ៖');
});

it('uses configured center result and DoEYS dates and signers',function(){
    g9ReportCandidate('1');
    expect(g9ReportPages($this,'doeys')[0]['doeys_signature_titles'])->toBe(['ជ.នាយកសាលា','នាយករង']);
    DB::table('tb_g9_exam_year_setting')->insert(['academic_year_id'=>1,'data'=>json_encode([
        'resultDate'=>'Year date','report_centers'=>[['exam_center'=>'មណ្ឌលប្រឡង ភ្នំពេញថ្មី','location'=>'ខេត្តព្រះសីហនុ','signer'=>'ប្រធានមណ្ឌល','signer_name'=>'អ្នកចុះហត្ថលេខា',
            'dates'=>['resultDate'=>['date'=>'2026-08-18','solar'=>'Center result date','lunar'=>'Center lunar date'],'resultDateDOEYS'=>['date'=>'2026-08-20','solar'=>'Center DoEYS date','lunar'=>'DoEYS lunar date']]]],
    ]),'version'=>1,'updated_by'=>1]);
    $page=g9ReportPages($this,'list')[0];
    expect($page)->toMatchArray(['solar'=>'Center result date','lunar'=>'Center lunar date','approval_solar'=>'Center DoEYS date','approval_lunar'=>'DoEYS lunar date','signer_name'=>'អ្នកចុះហត្ថលេខា'])
        ->and(g9ReportPages($this,'doeys')[0])->toMatchArray(['solar'=>'Center result date','lunar'=>'Center lunar date']);
    $doeysHtml=view('g9-national-exams.result-report-page',['page'=>g9ReportPages($this,'doeys')[0],'type'=>'doeys'])->render();
    expect($doeysHtml)->toContain('Center result date','Center lunar date')->not->toContain('Center DoEYS date','DoEYS lunar date');
    expect(strpos($doeysHtml,'Center lunar date'))->toBeLessThan(strpos($doeysHtml,'Center result date'))
        ->and(strpos($doeysHtml,'Center result date'))->toBeLessThan(strpos($doeysHtml,'នាយកសាលា'));
    expect($doeysHtml)->toContain('មន្ទីរអប់រំ យុវជន និងកីឡា ខេត្តព្រះសីហនុ')->not->toContain('ជ.នាយកសាលា','នាយករង','មន្ទីរអប់រំ យុវជន និងកីឡា រាជធានីភ្នំពេញ');
    $html=view('g9-national-exams.result-report-page',['page'=>$page,'type'=>'list'])->render();
    $doc=new DOMDocument();$doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NOERROR|LIBXML_NOWARNING);$xpath=new DOMXPath($doc);
    $approval=$xpath->query('//td[contains(@class,"result-list-approval")]')->item(0)->textContent;
    $center=$xpath->query('//td[contains(@class,"result-list-center-signature")]')->item(0)->textContent;
    expect($approval)->toContain('Center DoEYS date','DoEYS lunar date')->not->toContain('Center result date','Center lunar date');
    expect($center)->toContain('Center result date','Center lunar date')->not->toContain('Center DoEYS date','DoEYS lunar date');
});

it('enforces campus and statistics permissions for every output format',function(){
    g9ReportCandidate('1');g9ReportCandidate('2',[],['campus_id'=>2]);$user=User::find(2);
    foreach(['print','excel','pdf'] as $format)expect(fn()=>$this->controller->report(g12Request($user,['academic_year'=>1]),'list',$format,$this->reports))->toThrow(HttpException::class);
    foreach(['view','results'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    expect(g9ReportPages($this,'list',[],$user)[0]['tables'][0]['rows'])->toHaveCount(1);
    foreach(['room','subject','doeys','branch','wis'] as $type)foreach(['print','excel','pdf'] as $format)
        expect(fn()=>$this->controller->report(g12Request($user,['academic_year'=>1]),$type,$format,$this->reports))->toThrow(HttpException::class);
    DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.results-statistics')->value('id'),'allowed'=>true]);
    expect(g9ReportPages($this,'branch',[],$user)[0]['tables'][0]['rows'][0]['cells'][2]['value'])->toBe(1);
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
            expect($sheet)->toContain('orientation="'.(in_array($type,['doeys','branch'],true)?'landscape':'portrait').'"','fitToWidth="1"','<mergeCells');
            $zip->close();
        }finally{unlink($path);}
        if(getenv('G9_REPORT_QA')){
            $dir=storage_path('app/testing/g9-result-reports');if(!is_dir($dir))mkdir($dir,0755,true);
            file_put_contents($dir.'/'.$type.'.pdf',$pdf);file_put_contents($dir.'/'.$type.'.xlsx',$xlsx);file_put_contents($dir.'/'.$type.'.html',$print->getContent());
        }
    }
});
