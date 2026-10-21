<?php

use App\Http\Controllers\ScholarshipController;
use App\Models\User;
use App\Support\{ScholarshipScoreReports,ScholarshipSeatReports,ScholarshipSeatWorkbook};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB,Schema};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
beforeEach(fn()=>createScholarshipSchema());

function scholarshipScoreReportRequest(array $filters=[],bool $super=true,?string $denied=null): Request
{
    $user=Mockery::mock(User::class)->makePartial();$user->id=1;$user->active_campus_id=1;$user->setRelation('roles',collect());
    $user->shouldReceive('isSuperAdmin')->andReturn($super);$user->shouldReceive('hasPermission')->andReturnUsing(fn($code)=>$code!==$denied);
    Auth::setUser($user);$request=Request::create('/students/western-scholarship/score-list','GET',$filters);
    $request->setUserResolver(fn()=>$user);$request->setLaravelSession(app('session')->driver());app()->instance('request',$request);
    return $request;
}

function scoreReportData($exam): array
{return app(ScholarshipScoreReports::class)->data($exam,$exam->candidates()->with('scores')->get());}

it('follows the reference by room with saved scores zero absences and visible blank entries',function(){
    $exam=scholarshipSeatReportExam(26);$students=$exam->candidates()->where('campus_id',1)->orderBy('room_seat')->get();$subjects=$exam->subjects()->where('grade_from',1)->get();
    $students[0]->scores()->create(['subject_id'=>$subjects[0]->id,'score'=>0,'is_absent'=>true]);
    $students[0]->scores()->create(['subject_id'=>$subjects[1]->id,'score'=>0]);
    $students[1]->update(['absent'=>true]);
    $data=scoreReportData($exam);
    expect($data['orientation'])->toBe('landscape')->and($data['pages'])->toHaveCount(3)
        ->and($data['pages'][0]['students'])->toHaveCount(25)->and($data['pages'][1]['students'])->toHaveCount(1)
        ->and($data['pages'][1]['students'][0]['number'])->toBe(26)
        ->and($data['pages'][2]['center'])->toBe('South Exam Center')
        ->and($data['pages'][0]['students'][0]['scores'][$subjects[0]->id])->toBe(['value'=>0,'absent'=>true])
        ->and($data['pages'][0]['students'][0]['scores'][$subjects[1]->id])->toBe(['value'=>0.0,'absent'=>false])
        ->and($data['pages'][0]['students'][1]['total'])->toBe(0.0);
    foreach($data['pages'] as $page)foreach($page['rows'] as $row)expect(array_sum(array_column($row['cells'],'span')))->toBe(60);
    $html=view('western-scholarship.score-report',$data)->render();
    expect($html)->toContain('SCORES','GENDER','ROOM NO.','SEAT NO.','rowspan="2"','0 (A)','Draft / pending review','window.print()','November 20, 2026')->not->toContain('Secondary Only','Page 1');
});

it('always labels scoring lists for verification including published exams',function(bool $published,bool $pdfExport){
    $exam=scholarshipSeatReportExam(2);
    if($published)$exam->update(['published_at'=>now()]);
    $data=array_replace(scoreReportData($exam),['pdfExport'=>$pdfExport]);
    $html=view('western-scholarship.score-report',$data)->render();
    expect(substr_count($html,'alt="FOR VERIFICATION"'))->toBe($pdfExport?0:count($data['pages']))
        ->and($data['title'])->toBe('Score Verification List')
        ->and(base64_decode(explode(',',$data['verificationStampSrc'],2)[1]))->toContain('rotate(-10 200 80)','>FOR</text>','>VERIFICATION</text>');
})->with([
    'unpublished preview'=>[false,false],
    'published preview'=>[true,false],
    'unpublished PDF'=>[false,true],
    'published PDF'=>[true,true],
]);

it('uses the effective subject order for each grade and prevents excessive subject columns',function(){
    $exam=scholarshipSeatReportExam(2);$subjects=$exam->subjects()->where('grade_from',1)->get();
    $exam->update(['subject_orders'=>['grades'=>[1=>$subjects->reverse()->pluck('id')->all()]]]);
    $data=scoreReportData($exam->fresh());
    expect($data['pages'][0]['subjects']->pluck('name')->all())->toBe($subjects->reverse()->pluck('name')->all());
    foreach(range(1,3) as $number)$exam->subjects()->create(['name'=>'Additional '.$number,'grade_from'=>1,'grade_to'=>6,'max_score'=>100,'weight'=>1,'position'=>10+$number]);
    foreach(scoreReportData($exam->fresh())['pages'] as $page)expect($page['subjects']->count())->toBeLessThanOrEqual(6);
});

it('creates editable landscape Excel sheets per room with merged score headers and safe text',function(){
    $exam=scholarshipSeatReportExam(2);$student=$exam->candidates()->first();$student->update(['snapshot'=>array_replace($student->snapshot,['name_en'=>'=HYPERLINK("bad")'])]);
    $student->scores()->create(['subject_id'=>$exam->subjects()->first()->id,'score'=>0,'is_absent'=>true]);
    $data=scoreReportData($exam);$path=tempnam(sys_get_temp_dir(),'score-xlsx-');
    try{
        file_put_contents($path,app(ScholarshipSeatWorkbook::class)->generate($data));$zip=new ZipArchive();expect($zip->open($path))->toBeTrue();
        expect($zip->getFromName('xl/workbook.xml'))->toContain('ROOM 1 - North Exam Center','ROOM 1 - South Exam Center');
        $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');
        expect($sheet)->toContain('orientation="landscape"','paperSize="9"','<mergeCell ref="A6:C7"','HYPERLINK')->not->toContain('<f>','<oddFooter>');
        $styles=$zip->getFromName('xl/styles.xml');expect($styles)->toContain('FFFFDF6B','FFC6E0B4','FF548235','0.00&quot; (A)&quot;','applyNumberFormat="1"');
        expect($sheet)->toMatch('/<c r="[A-Z]+8" s="\d+"><v>0<\/v><\/c>/');
        $dom=new DOMDocument();expect($dom->loadXML($sheet))->toBeTrue();$zip->close();
    }finally{unlink($path);}
});

it('keeps a 25-student room on one landscape PDF page without a page-number footer',function(){
    $data=scoreReportData(scholarshipSeatReportExam(25));
    $pdf=app(ScholarshipSeatReports::class)->pdf($data);
    expect(preg_match_all('~/Type\s*/Page\b~',$pdf))->toBe(count($data['pages']))
        ->and($data['pages'][0]['students'])->toHaveCount(25);
});

it('selects Branding School Logo 1 and embeds it in the Excel output',function(){
    Schema::create('tb_branding_setting',function($table){$table->id();$table->string('report_logo_1_path');$table->string('report_logo_2_path');$table->timestamps();});
    $path=tempnam(storage_path('app/public'),'score-logo-');$xlsx=tempnam(sys_get_temp_dir(),'score-logo-xlsx-');
    try{
        $image=imagecreatetruecolor(120,45);imagefill($image,0,0,imagecolorallocate($image,32,60,101));imagepng($image,$path);imagedestroy($image);
        DB::table('tb_branding_setting')->insert(['report_logo_1_path'=>basename($path),'report_logo_2_path'=>'unused-logo.png']);
        $data=scoreReportData(scholarshipSeatReportExam(1));
        expect($data['logoLabel'])->toBe('School Logo 1')->and(base64_decode(explode(',',$data['logoSrc'],2)[1]))->toBe(file_get_contents($path));
        file_put_contents($xlsx,app(ScholarshipSeatWorkbook::class)->generate($data));$zip=new ZipArchive();$zip->open($xlsx);
        expect($zip->getFromName('xl/drawings/drawing1.xml'))->toContain('School Logo 1');$zip->close();
    }finally{unlink($path);unlink($xlsx);}
});

it('exports only authorized scoring assignments and validates the center room student cascade',function(){
    $exam=scholarshipSeatReportExam(2);$exam->committee()->create(['user_id'=>1,'role'=>'score-entry','exam_center_id'=>1,'room_number'=>1]);
    $request=scholarshipScoreReportRequest(['score_center'=>1,'score_room'=>1],false);
    $response=app(ScholarshipController::class)->scoreReport($request,$exam,'print');
    expect($response->getData()['pages'])->toHaveCount(1)->and($response->getData()['pages'][0]['students'])->toHaveCount(2);
    expect(fn()=>app(ScholarshipController::class)->scoreReport(scholarshipScoreReportRequest(['score_center'=>2,'score_room'=>1],false),$exam,'print'))->toThrow(ValidationException::class);
    expect(fn()=>app(ScholarshipController::class)->scoreReport(scholarshipScoreReportRequest(['score_room'=>1]),$exam,'print'))->toThrow(ValidationException::class);
    expect(fn()=>app(ScholarshipController::class)->scoreReport(scholarshipScoreReportRequest(['score_center'=>1,'score_room'=>1,'score_student'=>999]),$exam,'print'))->toThrow(ValidationException::class);
    $denied=scholarshipScoreReportRequest(['score_center'=>1,'score_room'=>1],false,'western-scholarship.export-scores');
    expect(fn()=>app(ScholarshipController::class)->scoreReport($denied,$exam,'print'))->toThrow(HttpException::class);
});

it('requires confirmed seats and supports all three scoring report formats',function(){
    $exam=scholarshipSeatReportExam(2);$controller=app(ScholarshipController::class);$params=['score_center'=>1,'score_room'=>1];
    $excel=$controller->scoreReport(scholarshipScoreReportRequest($params),$exam,'xlsx');
    expect($excel->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->and(substr($excel->getContent(),0,2))->toBe('PK');
    $pdf=$controller->scoreReport(scholarshipScoreReportRequest($params),$exam,'pdf');
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf')->and(substr($pdf->getContent(),0,4))->toBe('%PDF');
    $exam->update(['seats_locked_at'=>null]);
    expect(fn()=>$controller->scoreReport(scholarshipScoreReportRequest($params),$exam,'print'))->toThrow(ValidationException::class);
});

function scholarshipScoreReportRooms()
{
    $exam=scholarshipSeatReportExam(6);
    foreach($exam->candidates()->where('exam_center_id',1)->orderBy('room_seat')->get() as $index=>$student)
        $student->update(['room_number'=>[1,1,2,2,10,10][$index]]);
    return $exam;
}

it('exports one room selected rooms or all rooms in numeric order independently of the student filter',function(){
    $exam=scholarshipScoreReportRooms();$controller=app(ScholarshipController::class);
    foreach([
        [['report_scope'=>'single-room','report_room'=>2],[2]],
        [['report_scope'=>'selected-rooms','report_rooms'=>[10,1]],[1,10]],
        [['report_scope'=>'all-rooms'],[1,2,10]],
    ] as [$scope,$expected]){
        $response=$controller->scoreReport(scholarshipScoreReportRequest($scope+['report_center'=>1,'score_student'=>999]),$exam,'print');
        $pages=$response->getData()['pages'];
        expect(array_column($pages,'room'))->toBe($expected);
        foreach($pages as $page)expect($page['students'])->toHaveCount(2);
    }
    $excel=$controller->scoreReport(scholarshipScoreReportRequest(['report_center'=>1,'report_scope'=>'all-rooms']),$exam,'xlsx');
    $path=tempnam(sys_get_temp_dir(),'score-rooms-xlsx-');
    try{
        file_put_contents($path,$excel->getContent());$zip=new ZipArchive();$zip->open($path);
        expect($zip->getFromName('xl/workbook.xml'))->toContain('name="ROOM 1"','name="ROOM 2"','name="ROOM 10"')->not->toContain('South Exam Center');
        foreach([1,2,3] as $sheet)expect($zip->getFromName('xl/worksheets/sheet'.$sheet.'.xml'))->toContain('orientation="landscape"');
        $zip->close();
    }finally{unlink($path);}
});

it('rejects unavailable centers rooms empty selections and invalid PDF modes for scoped exports',function(){
    $exam=scholarshipScoreReportRooms();$controller=app(ScholarshipController::class);
    $exam->committee()->create(['user_id'=>1,'role'=>'score-entry','exam_center_id'=>1,'room_number'=>2]);
    foreach([
        ['report_center'=>2,'report_scope'=>'all-rooms'],
        ['report_center'=>1,'report_scope'=>'single-room','report_room'=>1],
        ['report_center'=>1,'report_scope'=>'selected-rooms','report_rooms'=>[2,10]],
        ['report_center'=>1,'report_scope'=>'selected-rooms','report_rooms'=>[]],
        ['report_center'=>1,'report_scope'=>'single-room'],
        ['report_scope'=>'all-rooms'],
    ] as $params)expect(fn()=>$controller->scoreReport(scholarshipScoreReportRequest($params,false),$exam,'print'))->toThrow(ValidationException::class);
    $allowed=$controller->scoreReport(scholarshipScoreReportRequest(['report_center'=>1,'report_scope'=>'all-rooms'],false),$exam,'print');
    expect(array_column($allowed->getData()['pages'],'room'))->toBe([2]);
    expect(fn()=>$controller->scoreReport(scholarshipScoreReportRequest(['report_center'=>1,'report_scope'=>'all-rooms','pdf_mode'=>'invalid']),$exam,'pdf'))->toThrow(ValidationException::class);
    expect(fn()=>$controller->scoreReport(scholarshipScoreReportRequest(['report_center'=>1,'report_scope'=>'all-rooms','pdf_mode'=>'separate']),$exam,'xlsx'))->toThrow(ValidationException::class);
});

it('combines room PDFs or packages separate complete room files in a ZIP download',function(){
    $exam=scholarshipScoreReportRooms();$controller=app(ScholarshipController::class);$params=['report_center'=>1,'report_scope'=>'all-rooms'];
    $combined=$controller->scoreReport(scholarshipScoreReportRequest($params+['pdf_mode'=>'combined']),$exam,'pdf');
    expect($combined->headers->get('Content-Type'))->toBe('application/pdf')->and(preg_match_all('~/Type\s*/Page\b~',$combined->getContent()))->toBe(3);
    $separate=$controller->scoreReport(scholarshipScoreReportRequest($params+['pdf_mode'=>'separate']),$exam,'pdf');
    expect($separate->headers->get('Content-Type'))->toBe('application/zip')->and($separate->headers->get('Content-Disposition'))->toContain('.zip');
    $path=tempnam(sys_get_temp_dir(),'score-room-pdfs-');
    try{
        file_put_contents($path,$separate->getContent());$zip=new ZipArchive();expect($zip->open($path))->toBeTrue()->and($zip->numFiles)->toBe(3);
        foreach([1,2,10] as $room){
            $bytes=$zip->getFromName('ROOM '.$room.'.pdf');
            expect(substr($bytes,0,4))->toBe('%PDF')->and(preg_match_all('~/Type\s*/Page\b~',$bytes))->toBe(1);
        }
        $zip->close();
    }finally{unlink($path);}
});

it('keeps all continuation pages together in each separate room PDF',function(){
    $exam=scholarshipSeatReportExam(26);
    $response=app(ScholarshipController::class)->scoreReport(scholarshipScoreReportRequest([
        'report_center'=>1,'report_scope'=>'all-rooms','pdf_mode'=>'separate',
    ]),$exam,'pdf');
    $path=tempnam(sys_get_temp_dir(),'score-room-continuation-');
    try{
        file_put_contents($path,$response->getContent());$zip=new ZipArchive();$zip->open($path);
        expect($zip->numFiles)->toBe(1)->and(preg_match_all('~/Type\s*/Page\b~',$zip->getFromName('ROOM 1.pdf')))->toBe(2);
        $zip->close();
    }finally{unlink($path);}
});
