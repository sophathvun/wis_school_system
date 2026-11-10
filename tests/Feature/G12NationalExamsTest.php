<?php
use App\Models\{User,G12ExamRecord,G12ExamImport,G12ExamCenter,G12ExamFile,G12ExamCertificate};
use App\Services\{G12NationalExams,G12NationalExamFiles,G12ScoringImports,G12ResultImageOcr,G12ResultReports};
use App\Support\{G12NationalExamPermissions as Access,G12NationalExamWorkbook,PermissionHierarchy};
use App\Http\Controllers\G12NationalExamController;
use Illuminate\Support\Facades\{DB,Storage,Blade};
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
require_once __DIR__.'/../Fixtures/G12NationalExam.php';
beforeEach(fn()=>createG12NationalSchema());

it('prepares the five G12 result reports with Khmer gender totals and three-decimal ranks',function(){
    $user=User::find(1);$exams=app(G12NationalExams::class);
    $a=$exams->save($user,g12ExamData(['student_id'=>'A001','student_name_en'=>'GRADE A STUDENT','student_name_kh'=>'សិស្ស និទ្ទេសអេ','gender'=>'ប','language_exam'=>'English','class_sellected'=>'Science','g12_score_rank'=>'98.1','g12_letter_grade'=>'A']));
    $exams->save($user,g12ExamData(['student_id'=>'F001','student_name_en'=>'FAILED STUDENT','student_name_kh'=>'សិស្ស ធ្លាក់','gender'=>'ស','language_exam'=>'Khmer','class_sellected'=>'Social Science','g12_letter_grade'=>'F']));
    $a->certificates()->where('type','soft')->update(['number'=>'1226-009']);
    $records=G12ExamRecord::with(['center','campus','certificates'])->get();$reports=app(G12ResultReports::class);
    $gradeA=$reports->prepare($records,'grade-a');$statistics=$reports->prepare($records,'statistics-doeys');
    expect($gradeA['rows'])->toHaveCount(1)->and($gradeA['pages'])->toHaveCount(1)->and($gradeA['rows'][0]['number'])->toBe(1)
        ->and($gradeA['rows'][0]['certificate_number'])->toBe('1226-009')->and($gradeA['rows'][0]['score_rank'])->toBe('98.100')
        ->and($statistics['statistics']['rows'][0]['registered']['male'])->toBe(1)
        ->and(collect($statistics['statistics']['rows'])->firstWhere('label','Social Science - Khmer')['grades']['F']['female'])->toBe(1);
});

it('uses full preview pages and print-safe pages for the Grade A A4 report',function(){
    $records=collect(range(1,49))->map(function($number){
        $record=new G12ExamRecord(['academic_year'=>'2025-2026','student_code'=>(string)$number,'name_en'=>'STUDENT '.$number,'name_kh'=>'សិស្ស','grade'=>'12A','overall_grade'=>'A','data'=>['gender'=>$number%2?'ប':'ស','g12_score_rank'=>100-$number/100]]);
        $record->setRelation('campus',new App\Models\SchoolInfo(['campus_name_en'=>'BCH']));$record->setRelation('certificates',collect());return $record;
    });
    $report=app(G12ResultReports::class)->prepare($records,'grade-a');
    expect(array_map('count',$report['previewPages']))->toBe([25,24])
        ->and(array_map('count',$report['pages']))->toBe([17,25,7])
        ->and($report['rows'][0]['number'])->toBe(1)->and($report['rows'][48]['number'])->toBe(49);
});

it('exports every G12 result report as print Excel and PDF',function(){
    app(G12NationalExams::class)->save(User::find(1),g12ExamData(['gender'=>'ប','language_exam'=>'English','class_sellected'=>'Science','g12_score_rank'=>'99.125','g12_letter_grade'=>'A']));
    $controller=app(G12NationalExamController::class);
    foreach(array_keys(G12ResultReports::TYPES) as $type){
        $print=$controller->report(g12Request(User::find(1),['tab'=>'results','format'=>'print','report_type'=>$type,'academic_year'=>'2025-2026']));
        $html=$print->render();expect($html)->toContain(G12ResultReports::TYPES[$type]);
        if(in_array($type,['grade-a','grade-a-campus','result-list'],true))expect($html)->toContain('font-family:KhmerMuolLight','class="grade-a-authority khmer-muol"','<h1 class="khmer-muol">')
            ->not->toContain('WESTERN INTERNATIONAL SCHOOL');
        elseif($type==='statistics-doeys')expect($html)->toContain('doeys-table','បេក្ខជនចុះឈ្មោះ','នាយកសាលា');
        elseif($type==='statistics-all')expect($html)->toContain('all-statistics-table','សរុបរួម','ការិយាល័យសិក្សា');
        else expect($html)->toContain('WESTERN INTERNATIONAL SCHOOL');
    }
    $excel=$controller->report(g12Request(User::find(1),['tab'=>'results','format'=>'xlsx','report_type'=>'grade-a']));
    $workbookPath=tempnam(sys_get_temp_dir(),'g12-report-test-');
    try{
        file_put_contents($workbookPath,$excel->getContent());
        $archive=new \ZipArchive();expect($archive->open($workbookPath))->toBeTrue();
        foreach(['xl/workbook.xml','xl/worksheets/sheet1.xml','xl/styles.xml'] as $part){
            $xml=new \DOMDocument();expect($xml->loadXML($archive->getFromName($part)))->toBeTrue();
        }
        expect($archive->getFromName('xl/worksheets/sheet1.xml'))->toContain('paperSize="9"','orientation="portrait"','Letter Grade','99.125','mergeCell')
            ->and($archive->getFromName('xl/styles.xml'))->toContain('Khmer OS Muol Light','FFDBEAFE');
        $archive->close();
        $campusExcel=$controller->report(g12Request(User::find(1),['tab'=>'results','format'=>'xlsx','report_type'=>'grade-a-campus']));
        file_put_contents($workbookPath,$campusExcel->getContent());
        expect($archive->open($workbookPath))->toBeTrue();
        $sheet=$archive->getFromName('xl/worksheets/sheet1.xml');
        $xml=new \DOMDocument();expect($xml->loadXML($sheet))->toBeTrue();
        expect($sheet)->toContain('BCH Campus','Name in Khmer','paperSize="9"','s="11"')
            ->and($archive->getFromName('xl/styles.xml'))->toContain('FFFFF2CC');
        $archive->close();
    }finally{unlink($workbookPath);}
    $pdf=$controller->report(g12Request(User::find(1),['tab'=>'results','format'=>'pdf','report_type'=>'statistics-all']));
    foreach(['statistics-all','statistics-doeys'] as $statisticsType){
        $statisticsExcel=$controller->report(g12Request(User::find(1),['tab'=>'results','format'=>'xlsx','report_type'=>$statisticsType]));
        $statisticsPath=tempnam(sys_get_temp_dir(),'g12-stat-test-');
        try{
            file_put_contents($statisticsPath,$statisticsExcel->getContent());$statisticsZip=new \ZipArchive();
            expect($statisticsZip->open($statisticsPath))->toBeTrue();
            foreach(['xl/workbook.xml','xl/worksheets/sheet1.xml','xl/styles.xml'] as $part){$document=new \DOMDocument();expect($document->loadXML($statisticsZip->getFromName($part)))->toBeTrue();}
            expect($statisticsZip->getFromName('xl/worksheets/sheet1.xml'))->toContain('orientation="landscape"','បេក្ខជនចុះឈ្មោះ','សរុប');
            $statisticsZip->close();
        }finally{unlink($statisticsPath);}
    }
    $gradeAPdf=$controller->report(g12Request(User::find(1),['tab'=>'results','format'=>'pdf','report_type'=>'grade-a']));
    $campusPdf=$controller->report(g12Request(User::find(1),['tab'=>'results','format'=>'pdf','report_type'=>'grade-a-campus']));
    $resultPdf=$controller->report(g12Request(User::find(1),['tab'=>'results','format'=>'pdf','report_type'=>'result-list']));
    expect(substr($resultPdf->getContent(),0,4))->toBe('%PDF')
        ->and($resultPdf->headers->get('Content-Disposition'))->toContain('g12-result-list-');
    $resultExcel=$controller->report(g12Request(User::find(1),['tab'=>'results','format'=>'xlsx','report_type'=>'result-list']));
    $resultPath=tempnam(sys_get_temp_dir(),'g12-result-test-');
    try {
        file_put_contents($resultPath,$resultExcel->getContent());
        $resultZip=new \ZipArchive();expect($resultZip->open($resultPath))->toBeTrue();
        $resultSheet=$resultZip->getFromName('xl/worksheets/sheet1.xml');
        $resultXml=new \DOMDocument();expect($resultXml->loadXML($resultSheet))->toBeTrue();
        expect($resultSheet)->toContain('គោត្តនាម និងនាមខ្លួន','ថ្នាក់ជម្រើស','វិទ្យាសាស្ត្រ','paperSize="9"')
            ->not->toContain('Name in English','NATIONAL EXAM STUDENT');
        $resultZip->close();
    } finally { unlink($resultPath); }
    expect(substr($campusPdf->getContent(),0,4))->toBe('%PDF')
        ->and($campusPdf->headers->get('Content-Disposition'))->toContain('g12-grade-a-campus-');
    expect(substr($gradeAPdf->getContent(),0,4))->toBe('%PDF')
        ->and($gradeAPdf->headers->get('Content-Disposition'))->toContain('g12-grade-a-');
    expect(substr($excel->getContent(),0,2))->toBe('PK')->and($excel->headers->get('Content-Disposition'))->toContain('g12-grade-a-')
        ->and(substr($pdf->getContent(),0,4))->toBe('%PDF')->and($pdf->headers->get('Content-Disposition'))->toContain('g12-statistics-all-');
});

it('stores unique student-year exam snapshots without changing school profiles and keeps folders stable',function(){
    $service=app(G12NationalExams::class);$user=User::find(1);$record=$service->save($user,g12ExamData());$folder=$record->document_folder;
    expect($record->student_code)->toBe('00123')->and($record->student_id)->toBe(1)->and($record->certificates()->count())->toBe(2)->and(DB::table('tb_student')->value('full_name_en'))->toBe('Existing school name');
    $service->save($user,g12ExamData(['student_name_en'=>'Corrected Name','version'=>1]),$record);
    expect($record->fresh()->document_folder)->toBe($folder)->and($record->fresh()->version)->toBe(2);
    expect(fn()=>$service->save($user,g12ExamData()))->toThrow(ValidationException::class,'already has');
    expect(fn()=>$service->save($user,g12ExamData(['version'=>1]),$record))->toThrow(ValidationException::class,'another window');
    $next=$service->save($user,g12ExamData(['academic_year'=>'2026-2027']));expect($next->id)->not->toBe($record->id);
});
it('returns the student birth date and parent details required by the exam record list',function(){
    $record=app(G12NationalExams::class)->save(User::find(1),g12ExamData([
        'dob'=>'2020-09-14','mother_name'=>'Mother Name','mother_occupation'=>'Teacher',
        'father_name'=>'Father Name','father_occupation'=>'Engineer',
        'g9_exam_center'=>'G9 Center','g9_exam_date'=>'2023-11-06','g9_exam_room'=>'12','g9_exam_seat'=>'34',
        'language_exam'=>'English','class_sellected'=>'វិទ្យាសាស្ត្រ','g12_exam_date'=>'2026-11-03',
    ]));
    $list=app(G12NationalExamController::class)->list(g12Request(User::find(1),['tab'=>'records']))->getData(true);
    expect($list['data'][0])->toMatchArray([
        'id'=>$record->id,'dob'=>'2020-09-14','mother_name'=>'Mother Name','mother_occupation'=>'Teacher',
        'father_name'=>'Father Name','father_occupation'=>'Engineer','campus'=>'BCH','grade'=>'12A',
        'g9_exam_center'=>'G9 Center','g9_exam_date'=>'2023-11-06','g9_exam_room'=>'12','g9_exam_seat'=>'34',
        'language_exam'=>'English','class_selected'=>'វិទ្យាសាស្ត្រ','g12_exam_date'=>'2026-11-03',
    ]);
});
it('displays legacy Excel G9 exam dates without changing the stored exam record',function(){
    $record=app(G12NationalExams::class)->save(User::find(1),g12ExamData(['g9_exam_date'=>'43682']));
    $controller=app(G12NationalExamController::class);
    $list=$controller->list(g12Request(User::find(1),['tab'=>'records']))->getData(true);
    $detail=$controller->show(g12Request(User::find(1)),$record)->getData(true);
    expect($list['data'][0]['g9_exam_date'])->toBe('2019-08-05')
        ->and($detail['g9_exam_date'])->toBe('2019-08-05')
        ->and($record->fresh()->data['g9_exam_date'])->toBe('43682');
    foreach(['','2023-11-06','០៤ សីហា ២០១៩','Unknown'] as $value){
        expect(\App\Support\G12NationalExamFields::examDate($value))->toBe($value);
    }
    expect(\App\Support\G12NationalExamFields::examDate('43682.5'))->toBe('2019-08-05');
});
it('converts numeric exam dates from both Excel calendar systems during import',function(bool $date1904){
    $path=tempnam(sys_get_temp_dir(),'g12-exam-dates-');
    try{
        $zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml','<workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><workbookPr date1904="'.($date1904?'1':'0').'"/><sheets><sheet name="Students" r:id="rId1"/><sheet name="Centers" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<Relationships><Relationship Id="rId1" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Target="worksheets/sheet2.xml"/></Relationships>');
        $headers=['student_id','academic_year','student_name_en','campus','g9_exam_date','g12_exam_date'];
        $values=['SAMPLE-001','2025-2026','TEST STUDENT','BCH','43682','45000'];
        $sheet='<worksheet><sheetData>';
        foreach([$headers,$values] as $index=>$row){
            $sheet.='<row r="'.($index+1).'">';
            foreach($row as $column=>$value)$sheet.='<c r="'.chr(65+$column).($index+1).'" t="inlineStr"><is><t>'.$value.'</t></is></c>';
            $sheet.='</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml',$sheet.'</sheetData></worksheet>');
        $zip->addFromString('xl/worksheets/sheet2.xml','<worksheet><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>exam_center</t></is></c></row></sheetData></worksheet>');
        $zip->close();
        $record=app(G12NationalExamWorkbook::class)->read($path)['records'][0];
        expect($record['g9_exam_date'])->toBe($date1904?'2023-08-06':'2019-08-05')
            ->and($record['g12_exam_date'])->toBe($date1904?'2027-03-16':'2023-03-15')
            ->and($record['student_id'])->toBe('SAMPLE-001');
    }finally{unlink($path);}
})->with([false,true]);
it('renders the filtered A4 candidate print report with Branding School Logo 1',function(){
    Storage::fake('public');
    \Illuminate\Support\Facades\Schema::create('tb_branding_setting',function(\Illuminate\Database\Schema\Blueprint $table){
        $table->id();$table->string('report_logo_1_path')->nullable();$table->string('footer_text')->nullable();$table->timestamps();
    });
    Storage::disk('public')->put('branding/g12-report-logo.jpg',g12PhotoBytes());
    DB::table('tb_branding_setting')->insert(['report_logo_1_path'=>'branding/g12-report-logo.jpg','footer_text'=>'School','created_at'=>now(),'updated_at'=>now()]);
    app(G12NationalExams::class)->save(User::find(1),g12ExamData());
    $response=app(G12NationalExamController::class)->report(g12Request(User::find(1),['format'=>'print','academic_year'=>'2025-2026']));
    $html=$response->render();
    $pdf=app(G12NationalExamController::class)->report(g12Request(User::find(1),['format'=>'pdf','academic_year'=>'2025-2026']));
    expect($html)->toContain('List of Candidates','School Logo 1','data:image/jpeg;base64','@page{size:A4 landscape')
        ->and($pdf->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($pdf->headers->get('Content-Disposition'))->toContain('g12-list-of-candidates.pdf')
        ->and(substr($pdf->getContent(),0,4))->toBe('%PDF');
});
it('filters student exam records by exam language and selected class and returns searchable options',function(){
    $user=User::find(1);$service=app(G12NationalExams::class);$controller=app(G12NationalExamController::class);
    $service->save($user,g12ExamData(['language_exam'=>'English','class_sellected'=>'Science']));
    $service->save($user,g12ExamData(['student_id'=>'00999','student_name_en'=>'SECOND STUDENT','language_exam'=>'French','class_sellected'=>'Social Science']));
    $filtered=$controller->list(g12Request($user,['tab'=>'records','language_exam'=>'French','class_selected'=>'Social Science']))->getData(true);
    $meta=$controller->meta(g12Request($user,['tab'=>'records']))->getData(true);
    expect($filtered['total'])->toBe(1)->and($filtered['data'][0]['student_code'])->toBe('00999')
        ->and($meta['exam_languages'])->toBe(['English','French'])
        ->and($meta['selected_classes'])->toBe(['Science','Social Science']);
});
it('returns filtered student totals by gender exam group and campus',function(){
    $user=User::find(1);$service=app(G12NationalExams::class);
    $rows=[
        ['student_id'=>'1001','gender'=>'ប','class_sellected'=>'Science','language_exam'=>'Khmer','campus_id'=>1,'campus'=>'BCH'],
        ['student_id'=>'1002','gender'=>'ស','class_sellected'=>'Science','language_exam'=>'English','campus_id'=>1,'campus'=>'BCH'],
        ['student_id'=>'1003','gender'=>'ស្រី','class_sellected'=>'Social Science','language_exam'=>'ខ្មែរ','campus_id'=>2,'campus'=>'BKK'],
        ['student_id'=>'1004','gender'=>'ប្រុស','class_sellected'=>'វិទ្យាសាស្ត្រសង្គម','language_exam'=>'អង់គ្លេស','campus_id'=>2,'campus'=>'BKK'],
    ];
    foreach($rows as $index=>$row)$service->save($user,g12ExamData($row+['student_name_en'=>'STUDENT '.($index+1)]));
    $list=app(G12NationalExamController::class)->list(g12Request($user,['tab'=>'records','academic_year'=>'2025-2026']))->getData(true);
    expect($list['summary'])->toMatchArray([
        'total'=>4,'male'=>2,'female'=>2,
        'groups'=>['science_khmer'=>1,'science_english'=>1,'social_khmer'=>1,'social_english'=>1],
        'campuses'=>['BCH'=>2,'BKK'=>2],
    ]);
});
it('inherits permissions from roles, departments and user grants while enforcing campus boundaries',function($source){
    $user=g12ExamUser(['view','records','create'],$source);expect(Access::allows($user,'create',1))->toBeTrue()->and(Access::allows($user,'create',2))->toBeFalse();
    app(G12NationalExams::class)->save($user,g12ExamData());
    expect(fn()=>app(G12NationalExams::class)->save($user,g12ExamData(['student_id'=>'999','campus_id'=>2])))->toThrow(HttpException::class);
})->with(['role','department','user']);
it('groups every action under the independent G12 main menu and enforces submenu dependencies',function(){
    $catalog=App\Models\Permission::all();$tree=PermissionHierarchy::tree($catalog);
    expect($tree['g12-national-exams']['modules'])->toHaveCount(9)
        ->and($tree['students']['modules'])->not->toHaveKey('g12-national-exams');
    foreach(Access::submenus() as $key=>$section){
        $module=$tree['g12-national-exams']['modules']['g12-national-exams.'.$key];
        expect($module['permission']->code)->toBe('g12-national-exams.'.$key)
            ->and($module['actions']->pluck('action')->all())->toBe($section[1]);
    }
    $nationalIds=$catalog->where('module','g12-national-exams')->pluck('id')->all();
    expect(array_diff($nationalIds,PermissionHierarchy::visibleIds($catalog)->all()))->toBe([])
        ->and(PermissionHierarchy::normalizeIds($nationalIds,$catalog))->toEqualCanonicalizing($nationalIds);
    $selected=$catalog->whereIn('code',['students.view','g12-national-exams.view','g12-national-exams.collection'])->pluck('id')->all();
    expect(PermissionHierarchy::normalizeIds($selected,$catalog))->not->toContain($catalog->firstWhere('code','g12-national-exams.collection')->id);
    $withoutMain=$catalog->where('module','g12-national-exams')->where('action','!=','view')->pluck('id')->all();
    expect(PermissionHierarchy::normalizeIds($withoutMain,$catalog))->toBe([]);
});
it('resolves all eight submenu URLs and blocks direct access to ungranted sections',function(){
    $user=g12ExamUser(['view','records']);$controller=app(G12NationalExamController::class);
    foreach(Access::PATHS as $section=>$path){
        $request=g12Request($user,[], '/g12-national-exams/'.$path);
        $route=app('router')->getRoutes()->match($request);$request->setRouteResolver(fn()=>$route);
        expect($route->getName())->toBe('g12-national-exams.'.$section)->and($route->parameter('section'))->toBe($section)
            ->and(route('g12-national-exams.'.$section,[],false))->toBe('/g12-national-exams/'.$path);
        if($section==='records')expect($controller->index($request)->getData()['tab'])->toBe('records');
        else expect(fn()=>$controller->index($request))->toThrow(HttpException::class);
    }
});
it('places Assign Exam Center below Student Exam Records with its requested filters',function(){
    $user=g12ExamUser(['view','assign-centers']);$request=g12Request($user,[], '/g12-national-exams/assign-exam-center');
    $route=app('router')->getRoutes()->match($request);$request->setRouteResolver(fn()=>$route);
    $view=app(G12NationalExamController::class)->index($request);$template=file_get_contents(resource_path('views/g12-national-exams/index.blade.php'));
    expect(array_slice(array_keys(Access::submenus()),0,3))->toBe(['records','assign-centers','scoring'])
        ->and($view->getData()['tab'])->toBe('assign-centers')
        ->and($template)->toContain('Datas Filter','g12-assign-filter-fields','data-g12-filter-toggle')
        ->and($template)->toContain("'academic_year'=>'Academic Year','campus_id'=>'Campus','language_exam'=>'Exam Language','class_selected'=>'Select','grade'=>'Grade'",'name="search"','Exam Center List','Phone Number','Examinations','Added by','data-g12-records');
});
it('places Scoring below Assign Exam Center with searchable filter data',function(){
    $record=app(G12NationalExams::class)->save(User::find(1),g12ExamData(['student_name_en'=>'SCORING STUDENT','language_exam'=>'English','class_sellected'=>'Science']));
    $user=g12ExamUser(['view','scoring']);$request=g12Request($user,[], '/g12-national-exams/scoring');
    $route=app('router')->getRoutes()->match($request);$request->setRouteResolver(fn()=>$route);
    $controller=app(G12NationalExamController::class);$view=$controller->index($request);
    $meta=$controller->meta(g12Request($user,['tab'=>'scoring']))->getData(true);
    $list=$controller->list(g12Request($user,['tab'=>'scoring','academic_year'=>'2025-2026']))->getData(true);
    $template=file_get_contents(resource_path('views/g12-national-exams/index.blade.php'));
    expect(array_slice(array_keys(Access::submenus()),0,3))->toBe(['records','assign-centers','scoring'])
        ->and($route->getName())->toBe('g12-national-exams.scoring')
        ->and($view->getData()['tab'])->toBe('scoring')
        ->and($template)->toContain('g12-scoring-filter-fields','g12-scoring-table','Scoring List',"'academic_year'=>'Academic Year','campus_id'=>'Campus','class_selected'=>'Selected','language_exam'=>'Language Exam','center_id'=>'Exam Center','record_id'=>'Student Name'")
        ->and($meta['students'][0])->toMatchArray(['name_en'=>'SCORING STUDENT','student_code'=>'00123'])
        ->and($list['total'])->toBe(1)
        ->and($list['data'][0])->toMatchArray(['name_en'=>'SCORING STUDENT','language_exam'=>'English','score_rank'=>'','can_score'=>false])
        ->and(collect($list['data'][0]['subject_scores'])->pluck('key')->all())->toBe(['khmer','math','biology','history','chemistry','physics'])
        ->and(route('g12-national-exams.scores.update',$record,false))->toBe('/g12-national-exams/records/'.$record->id.'/scores');
    $scores=['khmer'=>'A','math'=>'B','biology'=>'C','history'=>'D','chemistry'=>'E','physics'=>'F'];
    expect(fn()=>$controller->saveScores(g12Request($user,['scores'=>$scores,'score_rank'=>'12.345','letter_grade'=>'B','version'=>1],'/g12-national-exams/records/'.$record->id.'/scores','POST'),$record))->toThrow(HttpException::class);
    (require database_path('migrations/2026_10_31_000001_add_g12_scoring_manage_permission.php'))->up();
    $authorized=$controller->list(g12Request($user->fresh(),['tab'=>'scoring','academic_year'=>'2025-2026']))->getData(true);
    expect($authorized['data'][0]['can_score'])->toBeTrue();
    $response=$controller->saveScores(g12Request($user->fresh(),['scores'=>$scores,'score_rank'=>'12.345','letter_grade'=>'B','version'=>1],'/g12-national-exams/records/'.$record->id.'/scores','POST'),$record)->getData(true);
    expect($response)->toMatchArray(['message'=>'Grades saved.','version'=>2,'score_rank'=>'12.345','letter_grade'=>'B','result_status'=>'passed','scores'=>$scores])
        ->and($record->fresh()->data)->toMatchArray(['lettergrade_khmer'=>'A','lettergrade_math'=>'B','lettergrade_biology'=>'C','lettergrade_history'=>'D','lettergrade_chemistry'=>'E','lettergrade_physics'=>'F','g12_score_rank'=>'12.345','g12_letter_grade'=>'B'])
        ->and($record->fresh()->overall_grade)->toBe('B')
        ->and($record->fresh()->result_status)->toBe('passed')
        ->and(DB::table('tb_g12_exam_event')->where('record_id',$record->id)->where('action','scores-updated')->exists())->toBeTrue();
    expect(fn()=>$controller->saveScores(g12Request($user->fresh(),['scores'=>$scores+['english'=>'A'],'score_rank'=>'12','letter_grade'=>'B','version'=>2],'/g12-national-exams/records/'.$record->id.'/scores','POST'),$record->fresh()))->toThrow(ValidationException::class);
    expect(fn()=>$controller->saveScores(g12Request($user->fresh(),['scores'=>array_replace($scores,['khmer'=>'G']),'score_rank'=>'12','letter_grade'=>'B','version'=>2],'/g12-national-exams/records/'.$record->id.'/scores','POST'),$record->fresh()))->toThrow(ValidationException::class);
    expect(fn()=>$controller->saveScores(g12Request($user->fresh(),['scores'=>$scores,'score_rank'=>'12.3456','letter_grade'=>'B','version'=>2],'/g12-national-exams/records/'.$record->id.'/scores','POST'),$record->fresh()))->toThrow(ValidationException::class);
    $failed=$controller->saveScores(g12Request($user->fresh(),['scores'=>$scores,'score_rank'=>'12.3','letter_grade'=>'F','version'=>2],'/g12-national-exams/records/'.$record->id.'/scores','POST'),$record->fresh())->getData(true);
    expect($failed)->toMatchArray(['letter_grade'=>'F','result_status'=>'failed','score_rank'=>'12.3']);
});
it('returns filtered scoring totals by gender selection language and letter grade',function(){
    $user=User::find(1);$service=app(G12NationalExams::class);
    $rows=[
        ['student_id'=>'3101','gender'=>'ប','class_sellected'=>'Science','language_exam'=>'Khmer','g12_letter_grade'=>'A'],
        ['student_id'=>'3102','gender'=>'ស','class_sellected'=>'Science','language_exam'=>'English','g12_letter_grade'=>'A'],
        ['student_id'=>'3103','gender'=>'ស្រី','class_sellected'=>'Social Science','language_exam'=>'Khmer','g12_letter_grade'=>'B'],
        ['student_id'=>'3104','gender'=>'ប្រុស','class_sellected'=>'Social Science','language_exam'=>'English','g12_letter_grade'=>'C'],
        ['student_id'=>'3105','gender'=>'ស','class_sellected'=>'Science','language_exam'=>'ខ្មែរ','g12_letter_grade'=>'D'],
        ['student_id'=>'3106','gender'=>'ប','class_sellected'=>'វិទ្យាសាស្ត្រសង្គម','language_exam'=>'អង់គ្លេស','g12_letter_grade'=>'F'],
        ['student_id'=>'3107','gender'=>'ស','class_sellected'=>'វិទ្យាសាស្ត្រ','language_exam'=>'ខ្មែរ','g12_letter_grade'=>''],
    ];
    foreach($rows as $index=>$row)$service->save($user,g12ExamData($row+['student_name_en'=>'SCORING SUMMARY '.($index+1)]));
    $list=app(G12NationalExamController::class)->list(g12Request($user,['tab'=>'scoring','academic_year'=>'2025-2026']))->getData(true);
    expect($list['summary'])->toMatchArray([
        'total'=>7,'male'=>3,'female'=>4,
        'selections'=>['science'=>4,'social'=>3],
        'languages'=>['khmer'=>4,'english'=>3],
        'groups'=>['science_khmer'=>3,'social_khmer'=>1,'science_english'=>1,'social_english'=>2],
        'grades'=>[
            'A'=>['total'=>2,'percentage'=>28.6,'male'=>1,'female'=>1],
            'B'=>['total'=>1,'percentage'=>14.3,'male'=>0,'female'=>1],
            'C'=>['total'=>1,'percentage'=>14.3,'male'=>1,'female'=>0],
            'D'=>['total'=>1,'percentage'=>14.3,'male'=>0,'female'=>1],
            'E'=>['total'=>0,'percentage'=>0,'male'=>0,'female'=>0],
            'F'=>['total'=>1,'percentage'=>14.3,'male'=>1,'female'=>0],
            'pending'=>['total'=>1,'percentage'=>14.3,'male'=>0,'female'=>1],
        ],
    ]);
    expect(file_get_contents(resource_path('views/g12-national-exams/index.blade.php')))->toContain('data-g12-scoring-summary','Letter Grade Distribution','Total Candidates');
});
it('selects scoring subjects from exam language and selected class',function(){
    $service=app(G12NationalExams::class);$user=User::find(1);
    $service->save($user,g12ExamData(['student_id'=>'2001','student_name_en'=>'ENGLISH SCIENCE','language_exam'=>'English','class_sellected'=>'Science']));
    $service->save($user,g12ExamData(['student_id'=>'2002','student_name_en'=>'KHMER SCIENCE','language_exam'=>'Khmer','class_sellected'=>'Science']));
    $service->save($user,g12ExamData(['student_id'=>'2003','student_name_en'=>'KHMER SOCIAL','language_exam'=>'Khmer','class_sellected'=>'Social Science']));
    $rows=collect(app(G12NationalExamController::class)->list(g12Request($user,['tab'=>'scoring','academic_year'=>'2025-2026','per_page'=>'all']))->getData(true)['data'])->keyBy('name_en');
    $keys=fn($name)=>collect($rows[$name]['subject_scores'])->pluck('key')->all();
    $social=app(G12NationalExamController::class)->list(g12Request($user,['tab'=>'scoring','academic_year'=>'2025-2026','class_selected'=>'Social Science']))->getData(true);
    expect($keys('ENGLISH SCIENCE'))->toBe(['khmer','math','biology','history','chemistry','physics'])
        ->and($keys('KHMER SCIENCE'))->toBe(['khmer','math','biology','history','chemistry','physics','english'])
        ->and($keys('KHMER SOCIAL'))->toBe(['khmer','math','earthscience','geography','history','values','english'])
        ->and($social['total'])->toBe(1)->and($social['data'][0]['name_en'])->toBe('KHMER SOCIAL');
});
it('exports a scoring CSV and previews and confirms validated A-F score updates',function(){
    $user=User::find(1);$record=app(G12NationalExams::class)->save($user,g12ExamData(['language_exam'=>'English','class_sellected'=>'Science']));$service=app(G12ScoringImports::class);
    $row=$service->exportRows([$record->load(['campus','center'])])[0];
    foreach(['Khmer'=>'A','Math'=>'B','Biology'=>'C','History'=>'D','Chemistry'=>'E','Physics'=>'F'] as $subject=>$grade)$row[$subject]=$grade;
    $row['Score Rank']='12.345';$row['Letter Grade']='B';$stream=fopen('php://temp','w+');fputcsv($stream,$service->headers());fputcsv($stream,$row);rewind($stream);$csv=stream_get_contents($stream);fclose($stream);
    $batch=$service->preview($user,UploadedFile::fake()->createWithContent('scores.csv',$csv));$preview=$service->inspect($user,$batch);
    expect($preview)->toMatchArray(['total'=>1,'valid'=>1,'errors'=>0]);$summary=$service->confirm($user,$batch);
    expect($summary)->toBe(['total'=>1,'updated'=>1])
        ->and($record->fresh()->data)->toMatchArray(['lettergrade_khmer'=>'A','lettergrade_math'=>'B','lettergrade_biology'=>'C','lettergrade_history'=>'D','lettergrade_chemistry'=>'E','lettergrade_physics'=>'F','g12_score_rank'=>'12.345','g12_letter_grade'=>'B'])
        ->and($record->fresh()->result_status)->toBe('passed')
        ->and(DB::table('tb_g12_exam_event')->where('action','scores-csv-imported')->exists())->toBeTrue();
});
it('encrypts OCR credentials enforces the monthly limit and imports only reviewed image matches',function(){
    $user=User::find(1);$center=G12ExamCenter::create(['name'=>'OCR Center','name_key'=>'ocr center','active'=>true]);
    $record=app(G12NationalExams::class)->save($user,g12ExamData(['center_id'=>$center->id,'g12_exam_center'=>'OCR Center','language_exam'=>'English','class_sellected'=>'Science','g12_exam_seat'=>'005']));
    $privateKey=<<<'PEM'
-----BEGIN PRIVATE KEY-----
MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBANLWdJgTWPB0sYUB
MEcfSRmyykOZlnSju8ZqwijW8pdzjP4DoaGc+AbRONeBn0EAj71l7xi9nKMiHMcs
IcnY7+GSZrP1FwWxbHUg773UBnq7eCiJmfzR5yOOUGrmTK/9lcHnbfsaAsm2YgJA
slBMfXEFe3Zdl0PzPBXTOaSEOonpAgMBAAECgYBZEWv0CshZ3JJZ4+SnRxo0lwlH
gYzD/Z+H9PXNFvEo9hGsBZC0m4FUL/LZZ9MFPN18X6GgTAnuCK0rnwMvKEw9tLO1
TK+V/kxhIuY+Nh324OytcFN4nMCoEaj+epg+sFleW0LBDZ6YbUpk+aZtr92Pp8zf
EliMYHwJBSLvvGoQGQJBAOq8wacMjU1uQ9kRoCdXHDkqR0lHETroDyiv/myJePvF
x7K5ySjVcjirExS7WOVuKOuMJL/ia1xFDifgtZP8rcMCQQDl73/BQ2LBNpVMT80d
goOiirNJYIp/vSTojEiQ2o9OX5HcqpzdohaZjr8NYfSD0suOYYrqN0VuOIl/oU6q
LFLjAkAVIsvZC8NJXo1TZUfjYu+SoiDrEzAU+sIqqrYg9bvkB6n0iM/5EkcHnTfJ
nRjjB6k9lE/Wyz9ZfopIwu32lkDbAkBD0pApbnn7qeEB4GwNL0kqsaTOmQZhRWZU
ncRtzboFkRUSNNUOXtgxtTRPezxHge7+8XCrQ2BccZrFJcxxT5+nAkEAi+4bNC+o
BOSVBb56eqxwDQ8kekM154JDkjO/lVPQP79UsHrLuah6S4Y7u6VL97vTT9tXGRVe
bHYxGamZGUsR6Q==
-----END PRIVATE KEY-----
PEM;
    $credentials=['type'=>'service_account','project_id'=>'school-ocr','client_email'=>'ocr@school.test','private_key'=>$privateKey,'token_uri'=>'https://oauth2.googleapis.com/token'];
    $service=app(G12ResultImageOcr::class);$service->saveSetting($user,['enabled'=>true,'monthly_limit'=>1,'school_aliases'=>'Western International School'],UploadedFile::fake()->createWithContent('service-account.json',json_encode($credentials)));
    expect(DB::table('tb_g12_ocr_setting')->value('credentials'))->not->toContain('ocr@school.test');
    $batch=$service->upload($user,['academic_year'=>'2025-2026','center_id'=>$center->id,'language_exam'=>'English','class_selected'=>'Science','room'=>'01'],[UploadedFile::fake()->createWithContent('room-01.jpg',g12PhotoBytes())]);
    expect($service->status($user))->toMatchArray(['used'=>1,'remaining'=>0]);
    expect(fn()=>$service->upload($user,['academic_year'=>'2025-2026','center_id'=>$center->id,'language_exam'=>'English','class_selected'=>'Science','room'=>'01'],[UploadedFile::fake()->createWithContent('extra.jpg',g12PhotoBytes())]))->toThrow(ValidationException::class,'only 0 remain');
    $tokens=['005','Student','A','B','C','D','E','F','B'];$words=[];foreach($tokens as $index=>$token)$words[]=['symbols'=>array_map(fn($letter)=>['text'=>$letter],str_split($token)),'boundingBox'=>['vertices'=>[['x'=>$index*40,'y'=>20],['x'=>$index*40+25,'y'=>20],['x'=>$index*40+25,'y'=>35],['x'=>$index*40,'y'=>35]]]];
    \Illuminate\Support\Facades\Http::fake(['https://oauth2.googleapis.com/token'=>\Illuminate\Support\Facades\Http::response(['access_token'=>'test-token']),
        'https://vision.googleapis.com/v1/images:annotate'=>\Illuminate\Support\Facades\Http::response(['responses'=>[['fullTextAnnotation'=>['pages'=>[['blocks'=>[['paragraphs'=>[['words'=>$words]]]]]]]]]])]);
    $preview=$service->processNext($user,$batch);expect($preview['pending_images'])->toBe(0)->and($preview['rows'])->toHaveCount(1)->and($preview['rows'][0])->toMatchArray(['record_id'=>$record->id,'seat'=>'5','letter_grade'=>'B','match_status'=>'matched']);
    $summary=$service->confirm($user,$batch,$preview['rows']);expect($summary['updated'])->toBe(1)->and($record->fresh()->overall_grade)->toBe('B')->and($record->fresh()->data['lettergrade_physics'])->toBe('F');
});
it('lists students for exam center assignment and securely saves center room and seat',function(){
    $record=app(G12NationalExams::class)->save(User::find(1),g12ExamData(['phone_number'=>'012 345 678','gender'=>'ប']));
    $center=G12ExamCenter::create(['name'=>'Assignment Center','name_key'=>'assignment center','active'=>true]);
    $viewer=g12ExamUser(['view','assign-centers']);$controller=app(G12NationalExamController::class);
    $list=$controller->list(g12Request($viewer,['tab'=>'assign-centers','academic_year'=>'2025-2026']))->getData(true);
    expect($list['data'][0])->toMatchArray(['id'=>$record->id,'phone_number'=>'012 345 678','center_id'=>null,'room'=>'01','seat'=>'005','can_assign'=>false])
        ->and($list['summary'])->toMatchArray(['total'=>1,'male'=>1,'female'=>0,'centers'=>['assigned'=>0,'blank'=>1],'rooms'=>['assigned'=>1,'blank'=>0],'seats'=>['assigned'=>1,'blank'=>0]])
        ->and($list['data'][0]['added_by'])->toMatchArray(['name'=>'National Exam Admin','position'=>'—','campus'=>'BCH'])
        ->and($list['data'][0]['added_by']['date'])->not->toBeEmpty()
        ->and(route('g12-national-exams.assignment.update',$record,false))->toBe('/g12-national-exams/records/'.$record->id.'/exam-assignment');
    expect(fn()=>$controller->saveExamAssignment(g12Request($viewer,['center_id'=>$center->id,'room'=>'18','seat'=>'440','version'=>$record->version],'/g12-national-exams/records/'.$record->id.'/exam-assignment','POST'),$record))->toThrow(HttpException::class);
    $permission=App\Models\Permission::where('code','g12-national-exams.assign-centers-manage')->value('id');
    DB::table('access_user_permission_overrides')->insert(['user_id'=>$viewer->id,'permission_id'=>$permission,'allowed'=>true,'created_at'=>now(),'updated_at'=>now()]);
    $response=$controller->saveExamAssignment(g12Request($viewer->fresh(),['center_id'=>$center->id,'room'=>'18','seat'=>'440','version'=>$record->version],'/g12-national-exams/records/'.$record->id.'/exam-assignment','POST'),$record)->getData(true);
    $saved=$record->fresh();
    expect($response)->toMatchArray(['message'=>'Exam assignment saved.','version'=>2,'center'=>'Assignment Center','room'=>'18','seat'=>'440'])
        ->and($saved->center_id)->toBe($center->id)
        ->and($saved->data)->toMatchArray(['g12_exam_center'=>'Assignment Center','g12_exam_room'=>'18','g12_exam_seat'=>'440'])
        ->and(DB::table('tb_g12_exam_event')->where('record_id',$record->id)->where('action','exam-center-assigned')->exists())->toBeTrue();
});
it('orders exam center assignments by campus grade and Khmer student name',function(){
    DB::table('tb_school_info')->where('id',1)->update(['campus_name_en'=>'Zeta Campus']);
    DB::table('tb_school_info')->where('id',2)->update(['campus_name_en'=>'Alpha Campus']);
    $service=app(G12NationalExams::class);$user=User::find(1);
    $service->save($user,g12ExamData(['student_id'=>'1001','student_name_kh'=>'KHMER D','campus_id'=>1,'grade'=>'12A']));
    $service->save($user,g12ExamData(['student_id'=>'1002','student_name_kh'=>'KHMER C','campus_id'=>2,'grade'=>'12B']));
    $service->save($user,g12ExamData(['student_id'=>'1003','student_name_kh'=>'KHMER B','campus_id'=>2,'grade'=>'12A']));
    $service->save($user,g12ExamData(['student_id'=>'1004','student_name_kh'=>'KHMER A','campus_id'=>2,'grade'=>'12A']));
    $list=app(G12NationalExamController::class)->list(g12Request($user,['tab'=>'assign-centers','academic_year'=>'2025-2026']))->getData(true);
    expect(collect($list['data'])->pluck('name_kh')->all())->toBe(['KHMER A','KHMER B','KHMER C','KHMER D']);
});
it('shows only granted sidebar submenus and marks the current section active',function(){
    $user=g12ExamUser(['view','records']);$request=g12Request($user,[], '/g12-national-exams/student-records');
    $route=app('router')->getRoutes()->match($request);$request->setRouteResolver(fn()=>$route);auth()->setUser($user);
    $html=view('layouts.partials.g12-national-exams-menu',['moeysMenuIcon'=>'/icon.png'])->render();
    expect(substr_count($html,'class="dropdown-item'))->toBe(1)->and($html)->toContain('Student Exam Records','aria-current="page"')->not->toContain('Certificate Tracking','Excel Imports / History');
    DB::table('access_user_permission_overrides')->where('user_id',2)->where('permission_id',App\Models\Permission::where('code','g12-national-exams.view')->value('id'))->update(['allowed'=>false]);
    auth()->setUser($user->fresh());
    expect(trim(view('layouts.partials.g12-national-exams-menu',['moeysMenuIcon'=>'/icon.png'])->render()))->toBe('');
});
it('adds the Result submenu immediately below Scoring and preserves existing scoring access',function(){
    $user=g12ExamUser(['view','scoring']);
    expect(Access::allows($user,'results',1))->toBeFalse();
    (require database_path('migrations/2026_11_02_000002_add_g12_result_submenu.php'))->up();
    $user=$user->fresh();
    expect(Access::allows($user,'results',1))->toBeTrue()
        ->and(route('g12-national-exams.results'))->toEndWith('/g12-national-exams/result');
    auth()->setUser($user);
    $html=view('layouts.partials.g12-national-exams-menu',['moeysMenuIcon'=>'/icon.png'])->render();
    $sections=array_keys(Access::submenus());
    expect(array_search('results',$sections,true))->toBe(array_search('scoring',$sections,true)+1)
        ->and(strpos($html,'>Result<'))->toBeGreaterThan(strpos($html,'>Scoring<'));

    app(G12NationalExams::class)->save(User::find(1),g12ExamData());
    $list=app(G12NationalExamController::class)->list(g12Request($user,['tab'=>'results','academic_year'=>'2025-2026']))->getData(true);
    expect($list['total'])->toBe(1)->and($list['data'][0])->toMatchArray(['student_code'=>'00123','result_status'=>'passed','overall_grade'=>'A']);
});
it('renames old tab labels without changing permission IDs or grants',function(){
    $user=g12ExamUser(['view','records','edit'],'role');
    DB::table('access_permissions')->where('code','g12-national-exams.records')->update(['name'=>'Tab: Student Exam Records']);
    DB::table('access_permissions')->where('code','g12-national-exams.view')->update(['name'=>'G12 National Exams: View submenu']);
    $grants=DB::table('access_role_permissions')->get()->toArray();
    (require database_path('migrations/2026_10_26_000002_move_g12_sections_to_submenus.php'))->up();
    expect(DB::table('access_permissions')->where('code','g12-national-exams.records')->value('name'))->toBe('Submenu: Student Exam Records')
        ->and(DB::table('access_permissions')->where('code','g12-national-exams.view')->value('name'))->toBe('G12 National Exams: Main menu')
        ->and(DB::table('access_role_permissions')->get()->toArray())->toEqual($grants)
        ->and(Access::allows($user->fresh(),'edit',1))->toBeTrue();
});
it('previews center mappings and imports Withdrew as collected without inventing receipt evidence',function(){
    $user=User::find(1);$service=app(G12NationalExams::class);$batch=g12Batch([g12ExamData(['_row'=>2,'received_soft_certificate'=>'Withdrew','received_soft_cert_date'=>'2025-10-21','received_soft_cert_by'=>'Student','image_soft_certreceived_name'=>'signed_receipt'])],[['excenter_id'=>'05','exam_center'=>'Canonical Center']]);
    $preview=$service->inspect($user,$batch);expect($preview['errors'])->toBe(1)->and($preview['missing_centers'])->toBe(['Test Center']);
    $maps=['centers'=>['Test Center'=>'@Canonical Center']];$preview=$service->inspect($user,$batch,$maps);
    $service->confirm($user,$batch,$maps,['2'=>null]);$record=G12ExamRecord::sole();$soft=$record->certificates()->where('type','soft')->sole();
    expect($record->center->name)->toBe('Canonical Center')->and($soft->status)->toBe('collected')->and($soft->collected_date)->toBe('2025-10-21')->and($soft->recipient)->toBe('Student')->and($record->files()->count())->toBe(0);
    expect(fn()=>$service->confirm($user,$batch,$maps,['2'=>null]))->toThrow(ValidationException::class,'already imported');
});
it('assigns center IDs automatically and keeps IDs stable when edited',function(){
    $user=g12ExamUser(['view','centers','centers-manage']);$controller=app(G12NationalExamController::class);
    $controller->saveCenter(g12Request($user,['name'=>'New Test Center','active'=>true,'source_id'=>'MANUAL-ID']));
    $first=G12ExamCenter::sole();$code=$first->source_id;
    expect($code)->toMatch('/^EC-\d{6,}$/')->not->toBe('MANUAL-ID');
    $controller->saveCenter(g12Request($user,['name'=>'Renamed Test Center','active'=>false,'source_id'=>'CHANGED-ID']),$first);
    expect($first->fresh()->source_id)->toBe($code)->and($first->fresh()->active)->toBeFalse();
    $controller->saveCenter(g12Request($user,['name'=>'Second Center','active'=>true]));
    expect(G12ExamCenter::where('name','Second Center')->value('source_id'))->not->toBe($code);
    $imported=G12ExamCenter::create(['name'=>'MoEYS Center','name_key'=>'moeys center','source_id'=>'00027']);
    $controller->saveCenter(g12Request($user,['name'=>'Corrected MoEYS Center','active'=>true]),$imported);
    expect($imported->fresh()->source_id)->toBe('00027');
    expect(fn()=>$controller->saveCenter(g12Request($user,['name'=>'Second Center','active'=>true])))->toThrow(ValidationException::class,'already exists');
});
it('toggles exam center status with manage permission and records the change',function(){
    $manager=g12ExamUser(['view','centers','centers-manage']);$controller=app(G12NationalExamController::class);
    $center=G12ExamCenter::create(['name'=>'Status Center','name_key'=>'status center','active'=>true]);
    $response=$controller->centerStatus(g12Request($manager,['active'=>false]),$center)->getData(true);
    expect($response['active'])->toBeFalse()->and($center->fresh()->active)->toBeFalse()
        ->and(DB::table('tb_g12_exam_event')->where('action','center-status-updated')->exists())->toBeTrue();
    DB::table('access_user_permission_overrides')->where('user_id',$manager->id)->where('permission_id',App\Models\Permission::where('code','g12-national-exams.centers-manage')->value('id'))->delete();
    $viewer=$manager->fresh();
    expect(fn()=>$controller->centerStatus(g12Request($viewer,['active'=>true]),$center))->toThrow(HttpException::class);
});
it('paginates and searches exam center settings like the student enrollment list',function(){
    $user=g12ExamUser(['view','centers']);$controller=app(G12NationalExamController::class);
    foreach(range(1,23) as $number)G12ExamCenter::create(['name'=>sprintf('Center %02d',$number),'name_key'=>sprintf('center %02d',$number),'active'=>$number%2===1]);
    $page=$controller->centers(g12Request($user,['page'=>2,'per_page'=>'10']))->getData(true);
    expect($page['current_page'])->toBe(2)->and($page['per_page'])->toBe(10)->and($page['total'])->toBe(23)->and($page['data'])->toHaveCount(10)
        ->and($page['data'][0]['name'])->toBe('Center 11')->and($page['data'][0]['active'])->toBeTrue();
    $byId=$controller->centers(g12Request($user,['sort_by'=>'source_id','sort_dir'=>'desc','per_page'=>'10']))->getData(true);
    expect($byId['data'][0]['source_id'])->toBe('EC-000023');
    $filtered=$controller->centers(g12Request($user,['search'=>'Center 2','per_page'=>'10']))->getData(true);
    expect($filtered['total'])->toBe(4)->and(array_column($filtered['data'],'name'))->toBe(['Center 20','Center 21','Center 22','Center 23']);
    DB::table('access_user_permission_overrides')->where('user_id',$user->id)->where('permission_id',App\Models\Permission::where('code','g12-national-exams.centers')->value('id'))->update(['allowed'=>false]);
    expect(fn()=>$controller->centers(g12Request($user->fresh(),['per_page'=>'10'])))->toThrow(HttpException::class);
});
it('blocks duplicate workbook rows, unassigned campuses and stale import previews',function(){
    $user=User::find(1);$service=app(G12NationalExams::class);$row=g12ExamData(['_row'=>2,'g12_exam_center'=>'']);$batch=g12Batch([$row,array_replace($row,['_row'=>3])]);
    expect($service->inspect($user,$batch)['errors'])->toBe(1);
    expect(fn()=>$service->confirm($user,$batch,[],['2'=>null,'3'=>null]))->toThrow(ValidationException::class,'Resolve');expect(G12ExamRecord::count())->toBe(0);
    $record=$service->save($user,$row);$batch=g12Batch([$row]);$preview=$service->inspect($user,$batch);$service->save($user,$row+['version'=>1],$record);
    expect(fn()=>$service->confirm($user,$batch,[],['2'=>$preview['rows'][0]['version']]))->toThrow(ValidationException::class,'changed after preview');
    $campusUser=g12ExamUser(['view','imports','import']);$denied=G12ExamImport::create(['user_id'=>2,'kind'=>'excel','filename'=>'denied.xlsx','payload'=>['records'=>[array_replace($row,['campus'=>'BKK'])],'centers'=>[]],'expires_at'=>now()->addHour()]);
    expect($service->inspect($campusUser,$denied,['campuses'=>['BKK'=>2]])['errors'])->toBe(1);
});
it('preserves managed certificate collections and all document versions when reimporting students',function(){
    $user=User::find(1);$service=app(G12NationalExams::class);$record=$service->save($user,g12ExamData());$files=app(G12NationalExamFiles::class);
    $receipt=$files->store($user,$record,'soft_certificate_received',g12PhotoBytes(),'receipt.jpg');
    $files->collection($user,$record,'soft',['status'=>'collected','collected_date'=>'2026-10-26','recipient'=>'Student','version'=>1]);
    $service->save($user,g12ExamData(['received_soft_certificate'=>'Not Yet']),$record,true);
    expect($record->certificates()->where('type','soft')->sole()->status)->toBe('collected')->and($record->files()->count())->toBe(1);Storage::disk('local')->assertExists($receipt->path);
});
it('marks failed students as certificate ineligible and restores eligibility after a corrected result',function(){
    $user=User::find(1);$exams=app(G12NationalExams::class);$files=app(G12NationalExamFiles::class);
    $record=$exams->save($user,g12ExamData(['g12_letter_grade'=>'F']));
    $certificates=$record->certificates()->orderBy('type')->get();
    expect($record->result_status)->toBe('failed')
        ->and($certificates)->toHaveCount(2)
        ->and($certificates->pluck('status')->unique()->all())->toBe(['not_applicable'])
        ->and($certificates->pluck('remark')->unique()->all())->toBe([G12NationalExams::FAILED_CERTIFICATE_REMARK]);
    expect(fn()=>$files->store($user,$record,'soft_certificate',g12PhotoBytes(),'certificate.jpg'))
        ->toThrow(ValidationException::class,'not issued');
    expect(fn()=>$files->collection($user,$record,'soft',['status'=>'awaiting','version'=>1]))
        ->toThrow(ValidationException::class,'not applicable');
    $list=app(G12NationalExamController::class)->list(g12Request($user,['tab'=>'certificates']))->getData(true);
    expect($list['data'][0])->toMatchArray(['certificate_eligible'=>false,'can_upload_documents'=>false,'can_manage_collection'=>false]);

    $exams->save($user,g12ExamData(['g12_letter_grade'=>'B','version'=>$record->version]),$record);
    $certificates=$record->fresh()->certificates()->orderBy('type')->get();
    expect($record->fresh()->result_status)->toBe('passed')
        ->and($certificates->pluck('status')->unique()->all())->toBe(['awaiting'])
        ->and($certificates->pluck('remark')->filter())->toBeEmpty();
});
it('requires a signed receipt, permission and reason for collection corrections and keeps other certificates unchanged',function(){
    $admin=User::find(1);$record=app(G12NationalExams::class)->save($admin,g12ExamData());$service=app(G12NationalExamFiles::class);
    $data=['status'=>'collected','collected_date'=>'2026-10-26','recipient'=>'Student','version'=>1];
    expect(fn()=>$service->collection($admin,$record,'soft',$data))->toThrow(ValidationException::class,'signed certificate');
    $service->store($admin,$record,'soft_certificate_received',g12PhotoBytes(),'receipt.jpg');$service->collection($admin,$record,'soft',$data);
    expect($record->certificates()->where('type','hard')->sole()->status)->toBe('awaiting');
    $user=g12ExamUser(['view','certificates','collection']);
    expect(fn()=>$service->collection($user,$record,'soft',array_replace($data,['version'=>2,'reason'=>'Correction requested'])))->toThrow(HttpException::class);
    expect(fn()=>$service->collection($admin,$record,'soft',array_replace($data,['version'=>2])))->toThrow(ValidationException::class,'reason');
    $service->collection($admin,$record,'soft',array_replace($data,['version'=>2,'recipient'=>'Corrected Recipient','reason'=>'Recipient spelling corrected']));
    expect($record->certificates()->where('type','soft')->sole()->recipient)->toBe('Corrected Recipient')->and(DB::table('tb_g12_exam_event')->where('action','certificate-updated')->count())->toBe(2);
});
it('stores portraits by year and documents by stable student folder, retaining replaced photo bytes',function(){
    $user=User::find(1);$record=app(G12NationalExams::class)->save($user,g12ExamData());$service=app(G12NationalExamFiles::class);
    $photo=$service->store($user,$record,'portrait',g12PhotoBytes(),'120804049559121000001.jpg');expect($photo->path)->toBe('stuimages_g12_national_exam/2025-2026/120804049559121000001.webp')
        ->and($photo->original_name)->toBe('120804049559121000001.jpg')->and($photo->mime)->toBe('image/webp')->and($photo->size)->toBeLessThan(40_000);
    $saved=Storage::disk('local')->get($photo->path);expect(getimagesizefromstring($saved)['mime'])->toBe('image/webp')
        ->and($photo->sha256)->toBe(hash('sha256',$saved))->and($photo->size)->toBe(strlen($saved));
    $response=app(G12NationalExamController::class)->file(g12Request($user,['download'=>1]),$photo);
    expect($response->headers->get('Content-Type'))->toBe('image/webp')->and($response->headers->get('Content-Disposition'))->toContain('120804049559121000001.webp');
    expect(fn()=>$service->store($user,$record->fresh(),'portrait',g12PhotoBytes(),'replacement.jpg'))->toThrow(ValidationException::class,'Confirm Replace');
    $bytes=g12PhotoBytes(180);$replacement=$service->store($user,$record->fresh(),'portrait',$bytes,'replacement.jpg',true);
    expect($record->fresh()->photo_path)->toBe($replacement->path)->and($replacement->path)->toEndWith('/replacement.webp');Storage::disk('local')->assertExists($photo->path);
    $doc=$service->store($user,$record,'hard_certificate',g12PhotoBytes(),'MoEYS Diploma.jpg');expect($doc->path)->toStartWith('g12_national_exam_documents/2025-2026/'.$record->document_folder.'/hard_certificate/')
        ->and($doc->path)->toEndWith('/MoEYS Diploma.webp')->and($doc->original_name)->toBe('MoEYS Diploma.jpg')->and($doc->mime)->toBe('image/webp')
        ->and($doc->size)->toBeLessThanOrEqual(App\Services\G12CertificateImage::MAX_MASTER_BYTES)
        ->and($doc->thumbnail_size)->toBeLessThanOrEqual(App\Services\G12CertificateImage::MAX_THUMBNAIL_BYTES)
        ->and($doc->width)->toBe(600)->and($doc->height)->toBe(800);
    Storage::disk('local')->assertExists($doc->path);Storage::disk('local')->assertExists($doc->thumbnail_path);
    expect(getimagesizefromstring(Storage::disk('local')->get($doc->path))['mime'])->toBe('image/webp')
        ->and(getimagesizefromstring(Storage::disk('local')->get($doc->thumbnail_path))['mime'])->toBe('image/webp');
    $thumbnail=app(G12NationalExamController::class)->file(g12Request($user,['variant'=>'thumbnail']),$doc);
    $jpeg=app(G12NationalExamController::class)->file(g12Request($user,['format'=>'jpg','download'=>1,'quality'=>94]),$doc);
    expect($thumbnail->headers->get('Content-Type'))->toBe('image/webp')
        ->and($jpeg->headers->get('Content-Type'))->toBe('image/jpeg')
        ->and($jpeg->headers->get('Content-Disposition'))->toContain('MoEYS-Diploma-high-quality.jpg')
        ->and(getimagesizefromstring($jpeg->getContent())['mime'])->toBe('image/jpeg');
    $certificateList=app(G12NationalExamController::class)->list(g12Request($user,['tab'=>'certificates','academic_year'=>'2025-2026']))->getData(true);
    expect($certificateList['data'][0]['documents']['hard_certificate'])->toMatchArray(['id'=>$doc->id,'optimized'=>true,'is_image'=>true])
        ->and($certificateList['data'][0]['documents']['hard_certificate']['thumbnail_url'])->toContain('variant=thumbnail')
        ->and($certificateList['data'][0]['documents']['hard_certificate']['download_jpg'])->toContain('format=jpg');
    expect(fn()=>$service->store($user,$record,'other_documents','malicious code','file.jpg'))->toThrow(ValidationException::class,'Choose a JPG');
});
it('matches exact MoEYS photo filenames and student IDs, flags ambiguity, and permits manual unmatched assignment',function(){
    $user=User::find(1);$exam=app(G12NationalExams::class);$record=$exam->save($user,g12ExamData(['photo_filename'=>'120804049559121000001.jpg']));
    $service=app(G12NationalExamFiles::class);$file=UploadedFile::fake()->createWithContent('120804049559121000001.jpg',g12PhotoBytes());$batch=$service->preview($user,'2025-2026',[$file]);
    expect($batch->payload['entries'][0]['status'])->toBe('matched');$service->process($user,$batch,0,null,false);
    expect($record->fresh()->photo_path)->not->toBeNull();$service->process($user,$batch,0,null,false);expect($record->files()->count())->toBe(1);
    $unknown=$service->preview($user,'2025-2026',[UploadedFile::fake()->createWithContent('unknown.jpg',g12PhotoBytes())]);expect($unknown->payload['entries'][0]['status'])->toBe('unmatched');
    $service->process($user,$unknown,0,$record->id,true);expect($record->files()->count())->toBe(1); // identical image is retained once
    $next=$exam->save($user,g12ExamData(['student_id'=>'00999','academic_year'=>'2026-2027']));
    expect(fn()=>$service->process($user,$unknown,0,$next->id,true))->not->toThrow(Throwable::class); // retry is idempotent
    $wrongYear=$service->preview($user,'2025-2026',[UploadedFile::fake()->createWithContent('wrongyear.jpg',g12PhotoBytes())]);
    expect(fn()=>$service->process($user,$wrongYear,0,$next->id,false))->toThrow(ValidationException::class,'same academic year');
});
it('rejects ZIP traversal and previews safe nested photo entries without extracting paths',function(){
    $user=User::find(1);app(G12NationalExams::class)->save($user,g12ExamData());$service=app(G12NationalExamFiles::class);
    $path=tempnam(sys_get_temp_dir(),'g12zip');try{$zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);$zip->addFromString('../escape.jpg',g12PhotoBytes());$zip->close();
        expect(fn()=>$service->preview($user,'2025-2026',[new UploadedFile($path,'photos.zip','application/zip',null,true)]))->toThrow(ValidationException::class,'unsafe paths');
        $zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);$zip->addFromString('class/00123.jpg',g12PhotoBytes());$zip->close();
        $batch=$service->preview($user,'2025-2026',[new UploadedFile($path,'photos.zip','application/zip',null,true)]);expect($batch->payload['entries'][0]['status'])->toBe('matched');$service->process($user,$batch,0,null,false);
        $stored=G12ExamFile::where('kind','portrait')->sole();expect($stored->path)->toEndWith('/00123.webp')->and($stored->size)->toBeLessThan(40_000)
            ->and(getimagesizefromstring(Storage::disk('local')->get($stored->path))['mime'])->toBe('image/webp');
    }finally{@unlink($path);}
});
it('returns every ZIP photo as a preview row with a stable index and matching status',function(){
    $user=User::find(1);app(G12NationalExams::class)->save($user,g12ExamData(['photo_filename'=>'120804049559121000001.jpg']));
    $path=tempnam(sys_get_temp_dir(),'g12-preview-zip-');
    try{
        $zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);
        $zip->addFromString('photo_2025/120804049559121000001.jpg',g12PhotoBytes());
        $zip->addFromString('photo_2025/unmatched.jpg',g12PhotoBytes());$zip->close();
        $request=g12Request($user,['academic_year'=>'2025-2026']);
        $request->files->set('files',[new UploadedFile($path,'photo_2025.zip','application/zip',null,true)]);
        $response=app(G12NationalExamController::class)->photoPreview($request)->getData(true);
        expect($response['year'])->toBe('2025-2026')->and(array_is_list($response['entries']))->toBeTrue()
            ->and($response['entries'])->toHaveCount(2)
            ->and(array_column($response['entries'],'index'))->toBe([0,1])
            ->and(array_column($response['entries'],'status'))->toBe(['matched','unmatched'])
            ->and($response['entries'][0]['record_id'])->toBe(G12ExamRecord::sole()->id);
    }finally{@unlink($path);}
});
it('returns JSON upload validation errors through the real G12 route instead of redirecting to an HTML page',function(){
    $this->withoutMiddleware()->actingAs(User::find(1));
    $this->postJson(route('g12-national-exams.photos.preview'),['academic_year'=>'2025-2026'])
        ->assertUnprocessable()->assertJsonValidationErrors('files');
    $response=$this->postJson(route('g12-national-exams.photos.preview'),['academic_year'=>'2025-2026','files'=>[new UploadedFile('', 'photo_2025-2026.zip', 'application/zip', UPLOAD_ERR_INI_SIZE, true)]])
        ->assertUnprocessable()->assertJsonValidationErrors('files.0');
    expect($response->json('errors')['files.0'][0])->toContain('PHP error 1')->toContain('PHP-FPM');
    $partial=$this->postJson(route('g12-national-exams.photos.preview'),['academic_year'=>'2025-2026','files'=>[new UploadedFile('', 'photo_2025-2026.zip', 'application/zip', UPLOAD_ERR_PARTIAL, true)]])
        ->assertUnprocessable()->assertJsonValidationErrors('files.0');
    expect($partial->json('errors')['files.0'][0])->toContain('partially uploaded')->toContain('PHP error 3');
});
it('rejects oversized photo ZIPs with a JSON size error through the upload route',function(){
    $this->withoutMiddleware()->actingAs(User::find(1));
    $file=UploadedFile::fake()->create('photo_2025-2026.zip',598*1024,'application/zip');
    $this->postJson(route('g12-national-exams.photos.preview'),['academic_year'=>'2025-2026','files'=>[$file]])
        ->assertUnprocessable()->assertJsonValidationErrors('files.0');
    expect(G12ExamImport::count())->toBe(0);
});
it('keeps identical MoEYS filenames for different students without overwriting either image',function(){
    $user=User::find(1);$exams=app(G12NationalExams::class);$first=$exams->save($user,g12ExamData());$second=$exams->save($user,g12ExamData(['student_id'=>'00456']));$files=app(G12NationalExamFiles::class);
    $a=$files->store($user,$first,'portrait',g12PhotoBytes(),'same-moeys-name.jpg');$b=$files->store($user,$second,'portrait',g12PhotoBytes().' ','same-moeys-name.jpg');
    $encoded=app(App\Services\G12NationalExamPhoto::class)->webp(g12PhotoBytes());
    expect($a->path)->not->toBe($b->path)->and($a->path)->toEndWith('/same-moeys-name.webp')->and(Storage::disk('local')->get($a->path))->toBe($encoded)->and(Storage::disk('local')->get($b->path))->toBe($encoded);
});
it('remembers a manual photo filename match for later uploads and rejects expired or foreign batches',function(){
    $admin=User::find(1);$record=app(G12NationalExams::class)->save($admin,g12ExamData());$files=app(G12NationalExamFiles::class);
    $photo=UploadedFile::fake()->createWithContent('120804049559121000001.jpg',g12PhotoBytes());$batch=$files->preview($admin,'2025-2026',[$photo]);expect($batch->payload['entries'][0]['status'])->toBe('unmatched');
    $files->process($admin,$batch,0,$record->id,false);expect($record->fresh()->photo_filename)->toBe('120804049559121000001.jpg');
    $retry=$files->preview($admin,'2025-2026',[$photo]);expect($retry->payload['entries'][0]['status'])->toBe('existing');
    $user=g12ExamUser(['view','photos','photos-upload']);expect(fn()=>$files->process($user,$retry,0,$record->id,true))->toThrow(HttpException::class);
    $retry->update(['expires_at'=>now()->subMinute()]);expect(fn()=>$files->process($admin,$retry,0,$record->id,true))->toThrow(ValidationException::class,'expired');
});
it('scopes list, reports, private files and record details to authorized campuses',function(){
    $admin=User::find(1);$service=app(G12NationalExams::class);$first=$service->save($admin,g12ExamData());$other=$service->save($admin,g12ExamData(['student_id'=>'other','campus_id'=>2]));
    $file=app(G12NationalExamFiles::class)->store($admin,$other,'soft_certificate',g12PhotoBytes(),'certificate.jpg');
    $user=g12ExamUser(['view','records','reports','export','certificates','documents-view']);$controller=app(G12NationalExamController::class);
    expect(Access::allows($user,'records',1))->toBeTrue()->and(Access::allows($user,'records'))->toBeTrue();
    $list=$controller->list(g12Request($user,['tab'=>'records','per_page'=>'15','page'=>99]))->getData(true);expect($list['total'])->toBe(1)->and($list['current_page'])->toBe(1)->and($list['data'][0]['id'])->toBe($first->id);
    expect(fn()=>$controller->show(g12Request($user),$other))->toThrow(HttpException::class);
    expect(fn()=>$controller->file(g12Request($user),$file))->toThrow(HttpException::class);
    $report=$controller->report(g12Request($user,['format'=>'print']));expect($report->getData()['records']->modelKeys())->toBe([$first->id]);
});
it('lists distinct academic years from student exam records within photo upload campus access',function(){
    $admin=User::find(1);$service=app(G12NationalExams::class);
    $service->save($admin,g12ExamData());$service->save($admin,g12ExamData(['student_id'=>'00999']));
    $service->save($admin,g12ExamData(['academic_year'=>'2026-2027']));
    $service->save($admin,g12ExamData(['academic_year'=>'2024-2025','campus_id'=>2]));
    $user=g12ExamUser(['view','photos','photos-upload']);
    $result=app(G12NationalExamController::class)->meta(g12Request($user,['tab'=>'photos']))->getData(true);
    expect($result['years'])->toBe(['2026-2027','2025-2026']);
    G12ExamRecord::where('campus_id',1)->update(['campus_id'=>2]);
    expect(app(G12NationalExamController::class)->meta(g12Request($user,['tab'=>'photos']))->getData(true)['years'])->toBe([]);
});
it('reads the provided MoEYS workbook fields, Excel dates and center settings without importing real records',function(){
    $path=storage_path('app/imports/g12_national_exams.xlsx');if(!is_file($path))$this->markTestSkipped('Local sample not present.');
    $copy=tempnam(sys_get_temp_dir(),'g12sample');try{copy($path,$copy);$book=app(G12NationalExamWorkbook::class)->read($copy);
        expect($book['records'])->not->toBeEmpty()->and($book['centers'])->not->toBeEmpty()->and($book['headers'])->toContain('student_id','inage_hard_certreceived_name','received_soft_certificate')->and($book['records'][0]['dob'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');expect(G12ExamRecord::count())->toBe(0);
    }finally{@unlink($copy);}
});
it('downloads a template that the actual importer can preview and confirm with text identifiers intact',function(){
    G12ExamCenter::create(['name'=>'Center & School','name_key'=>'center & school','source_id'=>'001']);
    G12ExamCenter::create(['name'=>'Old Center','name_key'=>'old center','source_id'=>'099','active'=>false]);
    $user=g12ExamUser(['view','imports','import']);$request=g12Request($user);
    $response=app(G12NationalExamController::class)->template($request);
    expect($response->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($response->headers->get('Content-Disposition'))->toContain('g12-national-exams-import-template.xlsx');
    $path=tempnam(sys_get_temp_dir(),'g12-template-test-');
    try {
        file_put_contents($path,$response->getContent());
        $book=app(G12NationalExamWorkbook::class)->read($path);
        expect($book['headers'])->toEqual(app(App\Support\G12NationalExamTemplate::class)->headers())
            ->toContain('photo_filename','received_soft_certificate','received_hard_cert_date','inage_hard_certreceived_name');
        expect($book['records'])->toHaveCount(1)->and($book['records'][0]['student_id'])->toBe('SAMPLE-001')
            ->and($book['records'][0]['phone_number'])->toBe('012345678')
            ->and($book['records'][0]['g12_exam_seat'])->toBe('005')
            ->and($book['records'][0]['photo_filename'])->toBe('120804049559121000001.jpg')
            ->and($book['records'][0]['campus'])->toBe('BCH')
            ->and($book['centers'])->toHaveCount(1)->and($book['centers'][0]['excenter_id'])->toBe('001')
            ->and($book['centers'][0]['exam_center'])->toBe('Center & School');
        $zip=new ZipArchive();$zip->open($path);
        expect($zip->getFromName('xl/workbook.xml'))->toContain('name="Instructions"')
            ->and($zip->getFromName('xl/worksheets/sheet3.xml'))->toContain('Replace the SAMPLE student row')
            ->and($zip->getFromName('xl/styles.xml'))->toContain('numFmtId="49"');
        for($i=0;$i<$zip->numFiles;$i++){
            $doc=new DOMDocument();expect($doc->loadXML($zip->getFromIndex($i),LIBXML_NONET))->toBeTrue();
        }
        $zip->close();
        $service=app(G12NationalExams::class);$batch=$service->preview($user,new UploadedFile($path,'template.xlsx',null,null,true));
        $preview=$service->inspect($user,$batch);expect($preview['errors'])->toBe(0);
        $service->confirm($user,$batch,[],['2'=>null]);
        expect(G12ExamRecord::sole()->student_code)->toBe('SAMPLE-001');
    }finally{@unlink($path);}
});
it('provides fictional sample center data on an empty installation and requires import permission to download',function(){
    $user=g12ExamUser(['view','imports']);$controller=app(G12NationalExamController::class);
    expect(fn()=>$controller->template(g12Request($user)))->toThrow(HttpException::class);
    $route=app('router')->getRoutes()->match(g12Request(User::find(1),[], '/g12-national-exams/imports/template'));
    expect($route->getName())->toBe('g12-national-exams.template');
    $response=$controller->template(g12Request(User::find(1)));
    $path=tempnam(sys_get_temp_dir(),'g12-template-empty-');
    try{
        file_put_contents($path,$response->getContent());$book=app(G12NationalExamWorkbook::class)->read($path);
        expect($book['centers'][0]['exam_center'])->toBe('SAMPLE EXAM CENTER')
            ->and($book['records'][0]['g12_exam_center'])->toBe('SAMPLE EXAM CENTER')
            ->and(G12ExamRecord::count())->toBe(0)->and(G12ExamImport::count())->toBe(0);
    }finally{@unlink($path);}
});
