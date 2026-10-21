<?php

use App\Http\Controllers\ScholarshipController;
use App\Models\{ScholarshipResultPrintSetting,ScholarshipSeatPrintSetting,User};
use App\Services\ScholarshipWorkflow;
use App\Support\{ScholarshipResultReports,ScholarshipSeatReports,ScholarshipSeatWorkbook};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
require_once __DIR__.'/../Fixtures/ScholarshipResultReports.php';
beforeEach(fn()=>createScholarshipSchema());

function resultReportRequest(array $data=[],string $method='GET',?string $denied=null): Request
{
    $user=Mockery::mock(User::class)->makePartial();$user->id=$denied===null?1:2;$user->is_global=true;$user->active_campus_id=1;$user->setRelation('roles',collect());
    $user->shouldReceive('isSuperAdmin')->andReturn($denied===null);$user->shouldReceive('hasPermission')->andReturnUsing(fn($code)=>$code!==$denied);
    Auth::setUser($user);$request=Request::create('/students/western-scholarship/results',$method,$data);$request->setUserResolver(fn()=>$user);
    $request->setLaravelSession(app('session')->driver());app()->instance('request',$request);return $request;
}

it('prints winners by grade in award order and matches the reference columns',function(){
    $exam=scholarshipResultReportExam();$response=app(ScholarshipController::class)->resultReport(resultReportRequest(['report_scope'=>'all-grades','report_students'=>'winners']),$exam,'print');
    $data=$response->getData();expect(array_column($data['pages'],'grade'))->toBe([1,2,3]);
    foreach($data['pages'] as $page){
        expect($page['students'])->toHaveCount(8)->and($page['students']->pluck('result')->take(4)->all())->toBe(['1st Place','2nd Place','3rd Place','Best in Khmer']);
        foreach($page['rows'] as $row)expect(array_sum(array_column($row['cells'],'span')))->toBe(60);
    }
    expect($response->render())->toContain('SCHOLARSHIP AWARD','Checked and Verified by','window.print()','November 20, 2026','DRAFT');
});

it('selects one multiple or all grades independently of the student filter and separates Excel sheets',function(){
    $exam=scholarshipResultReportExam(10);$controller=app(ScholarshipController::class);
    foreach([
        [['report_scope'=>'single-grade','report_grade'=>2],[2]],
        [['report_scope'=>'selected-grades','report_grades'=>[3,1]],[1,3]],
        [['report_scope'=>'all-grades'],[1,2,3]],
    ] as [$scope,$expected]){
        $data=$controller->resultReport(resultReportRequest($scope+['report_students'=>'winners','result_student'=>999]),$exam,'print')->getData();
        expect(array_column($data['pages'],'grade'))->toBe($expected);
    }
    $all=$controller->resultReport(resultReportRequest(['report_scope'=>'all-grades','report_students'=>'all']),$exam,'print')->getData();
    expect($all['pages'][0]['students'])->toHaveCount(9)->and($all['pages'][0]['students']->last()['result'])->toBe('No Award');
    $excel=$controller->resultReport(resultReportRequest(['report_scope'=>'all-grades','report_students'=>'winners']),$exam,'xlsx');$path=tempnam(sys_get_temp_dir(),'result-xlsx-');
    try{
        file_put_contents($path,$excel->getContent());$zip=new ZipArchive();$zip->open($path);
        expect($zip->getFromName('xl/workbook.xml'))->toContain('name="Grade 1"','name="Grade 2"','name="Grade 3"');
        expect($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('orientation="landscape"','SCHOLARSHIP AWARD')->not->toContain('<oddFooter>','<f>');$zip->close();
    }finally{unlink($path);}
});

it('validates grade and center selections and rejects unauthorized exports',function(){
    $exam=scholarshipResultReportExam();$controller=app(ScholarshipController::class);
    foreach([
        ['report_scope'=>'single-grade'],['report_scope'=>'selected-grades','report_grades'=>[]],['report_scope'=>'selected-grades','report_grades'=>[1,1]],
        ['report_scope'=>'single-grade','report_grade'=>12],['report_scope'=>'all-grades','report_center'=>999],
    ] as $scope)expect(fn()=>$controller->resultReport(resultReportRequest($scope+['report_students'=>'winners']),$exam,'print'))->toThrow(ValidationException::class);
    expect(fn()=>$controller->resultReport(resultReportRequest(['report_scope'=>'all-grades','report_students'=>'winners'],'GET','western-scholarship.export-results'),$exam,'print'))->toThrow(HttpException::class);
    $data=$controller->resultReport(resultReportRequest(['report_scope'=>'single-grade','report_grade'=>1,'report_center'=>2,'report_students'=>'winners']),$exam,'print')->getData();
    expect($data['pages'][0]['students']->pluck('campus')->unique()->all())->toBe(['BKK']);
});

it('exports one combined PDF or separate complete PDFs by grade',function(){
    $exam=scholarshipResultReportExam(20);$controller=app(ScholarshipController::class);$params=['report_scope'=>'all-grades','report_students'=>'all'];
    $combined=$controller->resultReport(resultReportRequest($params),$exam,'pdf');
    expect(substr($combined->getContent(),0,4))->toBe('%PDF')->and(preg_match_all('~/Type\s*/Page\b~',$combined->getContent()))->toBe(9);
    $separate=$controller->resultReport(resultReportRequest($params+['pdf_mode'=>'separate']),$exam,'pdf');$path=tempnam(sys_get_temp_dir(),'result-pdfs-');
    try{
        file_put_contents($path,$separate->getContent());$zip=new ZipArchive();$zip->open($path);expect($zip->numFiles)->toBe(3);
        foreach([1,2,3] as $grade)expect(preg_match_all('~/Type\s*/Page\b~',$zip->getFromName('Grade '.$grade.'.pdf')))->toBe(3);$zip->close();
    }finally{unlink($path);}
});

it('saves independent result signers with staff snapshots and signing location after publication',function(){
    $exam=scholarshipResultReportExam();$exam->update(['published_at'=>now()]);$controller=app(ScholarshipController::class);
    $params=['exam_id'=>$exam->id,'campus_id'=>1,'signing_location'=>'Battambang'];
    foreach(['prepared'=>1,'checked'=>2,'approved'=>3] as $role=>$id){$params[$role.'_staff_id']=$id;$params[$role.'_date']='2026-10-20';}
    $controller->saveResultPrintSettings(resultReportRequest($params,'POST'));
    expect(ScholarshipSeatPrintSetting::count())->toBe(0)->and(ScholarshipResultPrintSetting::count())->toBe(1);
    $loaded=$controller->resultPrintSettings(resultReportRequest(['exam_id'=>$exam->id]))->getData(true);
    expect($loaded['requires_verifier'])->toBeTrue()->and($loaded['staff'])->toHaveCount(3)->and($loaded['values']['prepared_by_name'])->toBe('BCH PREPARER');
    DB::table('hrm_staff')->where('id',1)->update(['name_en'=>'CHANGED NAME','deleted_at'=>now()]);
    $data=app(ScholarshipResultReports::class)->data($exam,app(ScholarshipWorkflow::class)->results($exam));
    expect($data['pages'][0]['signers'][0]['name'])->toBe('BCH PREPARER')->and($data['pages'][0]['signers'][0]['date'])->toBe('Battambang, October 20, 2026.');
    expect(view('western-scholarship.result-report',$data)->render())->not->toContain('>DRAFT<');
    $params['prepared_staff_id']=4;
    expect(fn()=>$controller->saveResultPrintSettings(resultReportRequest($params,'POST')))->toThrow(ValidationException::class);
    expect(fn()=>$controller->resultPrintSettings(resultReportRequest(['exam_id'=>$exam->id],'GET','western-scholarship.tab-result')))->toThrow(HttpException::class);
});
