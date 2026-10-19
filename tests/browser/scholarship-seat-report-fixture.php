<?php

// Isolated synthetic data for visual report checks; never modifies the application database.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);\Illuminate\Support\Facades\DB::purge('sqlite');
require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';createScholarshipSchema();
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
$exam=scholarshipSeatReportExam(isset($argv[1])?25:26);$output=storage_path('app/testing/seat-reports'.(isset($argv[1])?'-25':''));if(!is_dir($output))mkdir($output,0775,true);
foreach($exam->centers()->get() as $index=>$center){
    $center->update(['location'=>$index?'Battambang':'Kampong Cham']);
    $values=['campus_id'=>$index+1];
    foreach(['prepared','checked','approved'] as $i=>$role){
        $staff=\App\Models\Staff::with('position')->find($index*3+$i+1);
        $values[$role.'_staff_id']=$staff->id;$values[$role.'_by_name']=$staff->name_en;
        $values[$role.'_position']=$staff->position->name;$values[$role.'_date']='2026-10-'.($index?'11':'10');
    }
    \App\Models\ScholarshipSeatPrintSetting::create(['exam_center_id'=>$center->id]+$values);
}
foreach(array_keys(\App\Support\ScholarshipSeatReports::DOCUMENTS) as $kind){
    $data=scholarshipSeatReportData($exam,$kind);
    $path=storage_path('app/public/school_logo/student_profile_report_logo.png');
    if(is_file($path))$data['logoSrc']='data:image/png;base64,'.base64_encode(file_get_contents($path));
    file_put_contents($output.'/'.$kind.'.html',view('western-scholarship.seat-report',$data)->render());
    file_put_contents($output.'/'.$kind.'.pdf',app(\App\Support\ScholarshipSeatReports::class)->pdf($data));
    file_put_contents($output.'/'.$kind.'.xlsx',app(\App\Support\ScholarshipSeatWorkbook::class)->generate($data));
    echo json_encode(['kind'=>$kind,'pages'=>count($data['pages'])]).PHP_EOL;
}
