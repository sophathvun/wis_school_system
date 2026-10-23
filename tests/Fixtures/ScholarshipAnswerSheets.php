<?php
use App\Models\{User,SchoolInfo,ScholarshipPaper,ScholarshipAnswerSheet};
use App\Services\ScholarshipAnswerSheets;
use App\Support\{ScholarshipPermissions,ScholarshipAnswerSheetLayout};
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\UploadedFile;

function answerSheetUser(bool $central=true,?array $actions=null): User
{
    $user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->is_global=$central;
    $user->setRelation('roles',collect());$user->setRelation('campuses',SchoolInfo::whereKey(1)->get());
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $actions??=array_keys(ScholarshipPermissions::catalog());
    $codes=['students.view','western-scholarship.view',...array_map(fn($a)=>'western-scholarship.'.$a,$actions)];
    $user->shouldReceive('hasPermission')->andReturnUsing(fn($code)=>in_array($code,$codes,true));Auth::setUser($user);return $user;
}
function answerSheetFixture(string $style='letters',int $questions=20,int $students=2): array
{
    $exam=scholarshipSeatReportExam($students);$user=answerSheetUser();
    foreach(['score-entry','score-reviewer'] as $role)$exam->committee()->create(['user_id'=>2,'role'=>$role]);
    $service=app(ScholarshipAnswerSheets::class);
    $paper=$service->savePaper($user,$exam,['subject_id'=>$exam->subjects()->first()->id,'grade'=>1,'paper_version'=>'A','question_count'=>$questions,
        'choice_style'=>$style,'choice_count'=>5,'points'=>100/$questions,'version'=>0]);
    $service->saveKey($user,$paper,array_fill(0,$questions,$style==='letters'?'A':'1'),$paper->version);
    $service->approveKey($user,$paper->fresh(),$paper->fresh()->version);
    $candidate=$exam->candidates()->where('grade_number',1)->first();$sheet=$service->issue($user,$paper->fresh(),[$candidate->id])->first();
    return [$exam->fresh(),$user,$paper->fresh(),$sheet,$service];
}
function answerSheetPixels(ScholarshipAnswerSheet|\App\Models\ScholarshipTeacherKeySheet $sheet,array $marks=[]): string
{
    $layout=app(ScholarshipAnswerSheetLayout::class);$image=imagecreatetruecolor(1000,1414);
    $white=imagecolorallocate($image,255,255,255);$black=imagecolorallocate($image,0,0,0);imagefill($image,0,0,$white);
    foreach($layout::MARKERS as [$x,$y])imagefilledrectangle($image,$x-10,$y-10,$x+9,$y+9,$black);
    foreach($layout->bits($sheet->token) as $i=>$bit)if($bit==='1')imagefilledrectangle($image,$layout::CODE_X+($i%12)*10,$layout::CODE_Y+intdiv($i,12)*10,$layout::CODE_X+($i%12)*10+9,$layout::CODE_Y+intdiv($i,12)*10+9,$black);
    foreach($layout->questions($sheet->snapshot['question_count'],$sheet->snapshot['choices'],$sheet->snapshot['layout_version']??1) as $row)foreach($row['dots'] as $dot){
        imageellipse($image,$dot['x'],$dot['y'],(int)round($dot['radius']*2),(int)round($dot['radius']*2),$black);
        if(in_array($dot['label'],$marks[$row['number']]??[],true))imagefilledellipse($image,$dot['x'],$dot['y'],(int)round($dot['radius']*2-1),(int)round($dot['radius']*2-1),$black);
    }
    ob_start();imagepng($image);$bytes=ob_get_clean();imagedestroy($image);return $bytes;
}
function answerSheetUpload($user,$exam,$paper,$sheet,array $marks=[])
{
    $file=UploadedFile::fake()->createWithContent('sheet.png',answerSheetPixels($sheet,$marks));
    return app(ScholarshipAnswerSheets::class)->upload($user,$exam,$paper,$file,$file);
}
