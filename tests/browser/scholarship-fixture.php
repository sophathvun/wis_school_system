<?php

// Render the real scholarship form with synthetic data, without reading the application database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
$user = Mockery::mock(\App\Models\User::class)->makePartial();
$user->id = 1;
$user->shouldReceive('isSuperAdmin')->andReturnTrue();
$user->shouldReceive('canAccessCampus')->andReturnTrue();
$user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>\App\Models\SchoolInfo::query());
$user->active_campus_id=1;
\Illuminate\Support\Facades\Auth::setUser($user);
$object = fn ($data) => (object) $data;
$campuses = collect([$object(['id'=>1,'campus_name_en'=>'BCH','campus_name_kh'=>'']), $object(['id'=>2,'campus_name_en'=>'BKK','campus_name_kh'=>''])]);
$academicYears = collect([$object(['id'=>1,'academic_year'=>'2025-2026']), $object(['id'=>2,'academic_year'=>'2026-2027'])]);
$grades = collect([['id'=>'1:1','label'=>'3A'],['id'=>'2:2','label'=>'4B']]);
$student = $object(['student_id'=>'2026001','student_no'=>'','full_name_en'=>'CHEAN CHAMNUNY','full_name_kh'=>'ចាន់ ចំនួន','photo_path'=>'testing/scholarship-photo.svg']);
$enrollment = $object(['id'=>1,'campus_id'=>1,'grade_id'=>1,'class_id'=>1,'student'=>$student,'academicYear'=>$academicYears[0],'campus'=>$campuses[0],'grade'=>$object(['id'=>1,'grade'=>'Grade 3','grade_short_name'=>'3']),'schoolClass'=>$object(['class_name'=>'A']),'schoolGroup'=>null,'session'=>$object(['session_short_name'=>'III'])]);
$records = new \Illuminate\Pagination\LengthAwarePaginator([$object(['id'=>1,'rank'=>3,'eoy_average'=>87.25,'student_enrollment_id'=>1,'enrollment'=>$enrollment,'assignedBy'=>$object(['name'=>'Rank Officer']),'created_at'=>\Illuminate\Support\Carbon::parse('2026-10-15 09:30')])], 1, 10);
$data=[
    'tabs'=>\App\Http\Controllers\ScholarshipController::TABS, 'tab'=>'assign-rank', 'campuses'=>$campuses,
    'academicYears'=>$academicYears, 'grades'=>$grades, 'filters'=>['academic_year_id'=>1,'campus_id'=>1,'grade_class'=>'1:1'],
    'selected'=>null, 'selectedRank'=>null, 'records'=>$records, 'canAssign'=>true,'rankUser'=>$user,'ranksLocked'=>false,'rankSummary'=>['total'=>1,1=>0,2=>0,3=>1],'canExportRanks'=>true,'canManageRankPrintSettings'=>true,
    'errors'=>new \Illuminate\Support\ViewErrorBag(),
];
if(($argv[1]??'')==='rank-saved') {
    session()->flash('scholarship_rank_saved',1);
    session()->flash('success','Class rank saved successfully.');
} elseif(($argv[1]??'')==='rank-edit') {
    $data['selected']=$enrollment;$data['selectedRank']=3;$data['selectedEoyAverage']=87.25;$data['selectedRankId']=1;
} elseif(isset($argv[1])) {
    config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);
    \Illuminate\Support\Facades\DB::purge('sqlite');
    require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';createScholarshipSchema();
    \Illuminate\Support\Facades\DB::table('tb_student')->where('id',1)->update(['photo_path'=>'testing/scholarship-photo.svg']);
    \Illuminate\Support\Facades\DB::table('tb_session')->insert(['id'=>1,'session_name'=>'Session III','session_short_name'=>'III']);
    \Illuminate\Support\Facades\DB::table('tb_student_enrollment')->where('academic_year_id',1)->update(['session_id'=>1]);
    \Illuminate\Support\Facades\DB::table('tb_grade')->where('id',1)->update(['grade'=>'Grade 1','grade_order'=>1]);
    $workflow=app(\App\Services\ScholarshipWorkflow::class);
    $subjects=[];foreach([[1,6],[7,12]]as[$from,$to])foreach(['Khmer','Mathematics','English','Science']as$name)$subjects[]=['name'=>$name,'grade_from'=>$from,'grade_to'=>$to,'max_score'=>100,'weight'=>1];
    $exam=$workflow->saveExam($user,null,['academic_year_id'=>1,'exam_date'=>'2026-11-20','room_capacity'=>25,'subjects'=>$subjects,'version'=>1]);
    $workflow->saveRank($user,\App\Models\StudentEnrollment::findOrFail(1),1);$exam=$exam->fresh();
    if(($argv[2]??'')==='pagination')foreach(range(2,77) as $i)$exam->candidates()->create(['campus_id'=>1,'class_id'=>1,'grade_number'=>1,'class_rank'=>1,
        'snapshot'=>['student_code'=>sprintf('SEAT-%03d',$i),'name_en'=>sprintf('STUDENT %03d',$i),'name_kh'=>'សិស្ស','campus'=>'BCH','class'=>'A']]);
    if(str_starts_with($argv[2]??'','centers')){
        $workflow->saveRank($user,\App\Models\StudentEnrollment::findOrFail(3),1);$exam=$exam->fresh();
        $exam=$workflow->saveExam($user,$exam,['academic_year_id'=>1,'exam_date'=>'2026-11-20','room_capacity'=>25,'subjects'=>$subjects,'version'=>$exam->version,
            'exam_centers'=>[['key'=>'north','name'=>'North Exam Center'],['key'=>'south','name'=>'South Exam Center']],
            'center_assignments'=>[1=>'north',2=>'south']]);
        foreach([1,2] as $campus)$exam->candidates()->create(['campus_id'=>$campus,'class_id'=>1,'grade_number'=>2,'class_rank'=>1,
            'snapshot'=>['student_code'=>'CENTER-'.$campus,'name_en'=>'GRADE TWO STUDENT '.$campus,'name_kh'=>'សិស្ស','campus'=>$campus===1?'BCH':'BKK','class'=>'A']]);
        if(($argv[2]??'')==='centers-locked'){$workflow->lockPlan($user,$exam,$workflow->plan($exam)['token']);$exam=$exam->fresh();}
    }
    if(($argv[2]??'')==='draft')foreach(range(2,8)as$i)$exam->candidates()->create(['campus_id'=>1,'class_id'=>1,'grade_number'=>1,'class_rank'=>1,'snapshot'=>['student_code'=>'AW-'.$i,'name_en'=>'AWARD STUDENT '.$i,'name_kh'=>'សិស្ស','campus'=>'BCH','class'=>'A']]);
    if(in_array($argv[1],['scoring','result','exam-committee'])) {
        $workflow->lockPlan($user,$exam,$workflow->plan($exam)['token']);$exam=$exam->fresh();
        $candidate=$exam->candidates()->first();$scores=$workflow->applicableSubjects($exam,1)->mapWithKeys(fn($s)=>[$s->id=>90])->all();
        $workflow->saveScores($user,$exam,$candidate,$scores,false,1);
        if($argv[1]==='result') {
            $workflow->review($user,$exam,$candidate->fresh(),2);$exam=$exam->fresh();
            foreach($exam->candidates()->where('id','!=',$candidate->id)->get()as$other){$workflow->saveScores($user,$exam,$other,$scores,false,1);$workflow->review($user,$exam,$other->fresh(),2);}$exam=$exam->fresh();
            if(($argv[2]??'')!=='draft') {
            $choices=$workflow->awardSlots($exam,1)->mapWithKeys(fn($slot)=>[$slot['key']=>['candidate_id'=>$slot['key']==='total_1'?$candidate->id:null,'award_percent'=>$slot['percent'],'reason'=>null]])->all();
            $workflow->saveAwards($user,$exam,1,$choices,$exam->version);$exam=$exam->fresh();
            $workflow->publish($user,$exam,$exam->version);$exam=$exam->fresh();
            }
        }
    }
    if(($argv[2]??'')==='new')$exam->delete();
    parse_str($argv[3]??'',$seatQuery);
    $request=\Illuminate\Http\Request::create('http://localhost/students/western-scholarship','GET',(($argv[2]??'')==='new'?['tab'=>'settings','new_exam'=>1,'academic_year_id'=>1]:['tab'=>$argv[1],'exam_id'=>$exam->id])+$seatQuery);
    if(!empty($seatQuery['seat_list']))$request->headers->set('Accept','application/json');
    $request->setUserResolver(fn()=>$user);$request->setLaravelSession(app('session')->driver());$app->instance('request',$request);
    $response=app(\App\Http\Controllers\ScholarshipController::class)->index($request);
    if(!empty($seatQuery['seat_list'])){echo $response->getContent();exit;}
    $data=$response->getData()+['errors'=>new \Illuminate\Support\ViewErrorBag()];
}
$template = file_get_contents(resource_path('views/western-scholarship/index.blade.php'));
preg_match("/@section\('content'\)(.*)@endsection/s", $template, $matches);
$body = preg_replace('/^@vite.*$/m', '', $matches[1]);
echo \Illuminate\Support\Facades\Blade::render($body,$data);
