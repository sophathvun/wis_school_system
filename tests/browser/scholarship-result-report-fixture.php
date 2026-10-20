<?php

// Isolated report/settings fixtures never read or modify production exam records.
require __DIR__.'/../../vendor/autoload.php';$app=require __DIR__.'/../../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','session.driver'=>'array']);\Illuminate\Support\Facades\DB::purge('sqlite');
require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';createScholarshipSchema();
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';require_once __DIR__.'/../Fixtures/ScholarshipResultReports.php';
$exam=scholarshipResultReportExam((int)($argv[2]??8));$exam->update(['published_at'=>now()]);
$user=Mockery::mock(\App\Models\User::class)->makePartial();$user->id=1;$user->active_campus_id=1;$user->shouldReceive('isSuperAdmin')->andReturnTrue();\Illuminate\Support\Facades\Auth::setUser($user);
$setting=['exam_id'=>$exam->id,'campus_id'=>1,'signing_location'=>'Phnom Penh'];
foreach(['prepared'=>1,'checked'=>2,'approved'=>3] as $role=>$id){$setting[$role.'_staff_id']=$id;$setting[$role.'_by_name']='BCH '.strtoupper($role);$setting[$role.'_position']=['prepared'=>'Registrar Manager','checked'=>'Registrar Director','approved'=>'Vice President'][$role];$setting[$role.'_date']='2026-10-20';}
\App\Models\ScholarshipResultPrintSetting::create($setting);
if(($argv[1]??'')==='settings'){
    parse_str($argv[3]??'',$params);$params['exam_id']=$exam->id;
    $request=\Illuminate\Http\Request::create('/results/print-settings','GET',$params);$request->setUserResolver(fn()=>$user);$request->setLaravelSession(app('session')->driver());app()->instance('request',$request);
    echo app(\App\Http\Controllers\ScholarshipController::class)->resultPrintSettings($request)->getContent();exit;
}
$logo='school_logo/student_profile_report_logo.png';
if(is_file(storage_path('app/public/'.$logo))){
    \Illuminate\Support\Facades\Schema::create('tb_branding_setting',function($table){$table->id();$table->string('report_logo_1_path');$table->timestamps();});
    \Illuminate\Support\Facades\DB::table('tb_branding_setting')->insert(['report_logo_1_path'=>$logo]);
}
$data=app(\App\Support\ScholarshipResultReports::class)->data($exam,app(\App\Services\ScholarshipWorkflow::class)->results($exam));
$output=storage_path('app/testing/result-reports');if(!is_dir($output))mkdir($output,0775,true);
file_put_contents($output.'/result-list.html',view('western-scholarship.result-report',$data)->render());
file_put_contents($output.'/result-list.pdf',app(\App\Support\ScholarshipSeatReports::class)->pdf($data));
file_put_contents($output.'/result-list.xlsx',app(\App\Support\ScholarshipSeatWorkbook::class)->generate($data));
file_put_contents($output.'/result-list.zip',app(\App\Support\ScholarshipResultReports::class)->pdfArchive($data));
echo json_encode(['pages'=>count($data['pages']),'output'=>$output]);
