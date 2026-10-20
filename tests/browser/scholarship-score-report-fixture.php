<?php

// Visual report fixtures use only synthetic in-memory data.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);\Illuminate\Support\Facades\DB::purge('sqlite');
require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';createScholarshipSchema();
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
$exam=scholarshipSeatReportExam(25);$subjects=$exam->subjects()->where('grade_from',1)->get();
$students=$exam->candidates()->get();
foreach($students as $index=>$candidate){
    foreach($subjects as $subjectIndex=>$subject){
        if($index===3&&$subjectIndex===2)continue;
        $absent=$index===1||($index===0&&$subjectIndex===0);
        $candidate->scores()->create(['subject_id'=>$subject->id,'score'=>$absent?0:70+(($index+$subjectIndex)%30),'is_absent'=>$absent]);
    }
    if($index===1)$candidate->update(['absent'=>true]);
}
$logoPath=storage_path('app/public/school_logo/student_profile_report_logo.png');
if(!is_file($logoPath)){
    $logoPath=storage_path('app/public/testing/score-report-logo.png');
    if(!is_dir(dirname($logoPath)))mkdir(dirname($logoPath),0775,true);
    $image=imagecreatetruecolor(300,90);imagefill($image,0,0,imagecolorallocate($image,255,255,255));
    imagestring($image,5,55,35,'School Logo 1',imagecolorallocate($image,32,60,101));imagepng($image,$logoPath);imagedestroy($image);
}
\Illuminate\Support\Facades\Schema::create('tb_branding_setting',function($table){$table->id();$table->string('report_logo_1_path');$table->timestamps();});
\Illuminate\Support\Facades\DB::table('tb_branding_setting')->insert(['report_logo_1_path'=>str_replace('\\','/',substr($logoPath,strlen(storage_path('app/public'))+1))]);
$data=app(\App\Support\ScholarshipScoreReports::class)->data($exam,$exam->candidates()->with('scores')->get());
$output=storage_path('app/testing/score-reports');if(!is_dir($output))mkdir($output,0775,true);
file_put_contents($output.'/score-list.html',view('western-scholarship.score-report',$data)->render());
file_put_contents($output.'/score-list.pdf',app(\App\Support\ScholarshipSeatReports::class)->pdf($data));
file_put_contents($output.'/score-list.xlsx',app(\App\Support\ScholarshipSeatWorkbook::class)->generate($data));
echo json_encode(['pages'=>count($data['pages']),'output'=>$output]);
