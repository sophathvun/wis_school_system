<?php

use App\Http\Controllers\G9NationalExamController;
use App\Models\{G9ExamCandidate,User};
use App\Support\G9ExamFields;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Schema,Storage};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/G12NationalExam.php';

beforeEach(function(){
    createG12NationalSchema();Storage::fake('public');
    Schema::table('tb_student',function(Blueprint $t){$t->text('current_address_kh')->nullable();});
    Schema::create('tb_student_enrollment',function(Blueprint $t){$t->id();$t->unsignedBigInteger('student_id');$t->unsignedBigInteger('academic_year_id');$t->unsignedBigInteger('campus_id');});
    Schema::create('tb_academic_year',function(Blueprint $t){$t->id();$t->string('academic_year');$t->softDeletes();});
    DB::table('tb_academic_year')->insert(['id'=>1,'academic_year'=>'2025-2026']);
    (require database_path('migrations/2026_11_04_000002_create_g9_exam_candidates.php'))->up();
    (require database_path('migrations/2026_11_04_000003_create_g9_import_batches.php'))->up();
    (require database_path('migrations/2026_11_04_000004_preserve_g9_legacy_tracking.php'))->up();
    (require database_path('migrations/2026_11_04_000005_add_g9_date_settings_access.php'))->up();
    (require database_path('migrations/2026_11_04_000006_create_g9_photo_batches.php'))->up();
    (require database_path('migrations/2026_11_05_000001_add_g9_eoy_permissions.php'))->up();
    (require database_path('migrations/2026_11_05_000002_complete_g9_permission_catalog.php'))->up();
    (require database_path('migrations/2026_11_05_000003_create_g9_eoy_campus_settings.php'))->up();
    (require database_path('migrations/2026_11_06_000001_create_g9_eoy_campus_defaults.php'))->up();
    (require database_path('migrations/2026_11_06_000002_add_g9_exam_attendance.php'))->up();
    (require database_path('migrations/2026_11_06_000003_add_g9_absence_reason.php'))->up();
    $this->controller=app(G9NationalExamController::class);
    $this->input=['academic_year_id'=>1,'campus_id'=>1,'student_code'=>'000123','name_en'=>'G9 Candidate',
        'name_kh'=>"\u{179F}\u{17B7}\u{179F}\u{17D2}\u{179F}",'gender'=>'Female','nationality'=>'Cambodian','date_of_birth'=>'2011-05-12',
        'place_of_birth'=>'Phnom Penh','foreign_language'=>'English','father_name'=>'Father','father_occupation'=>'Teacher',
        'mother_name'=>'Mother','mother_occupation'=>'Doctor','exam_center'=>'Test Center','exam_date'=>'2026-08-10',
        'room_number'=>'01','seat_number'=>'005','school_name'=>'Western International School','grade'=>'9A',
        'phone_number'=>'012345678','exam_language'=>'Khmer','semester_1'=>'89.500','semester_2'=>'90.500','eoy_average'=>'90.000'];
    foreach(G9ExamFields::GROUPS['National Examination Scores'] as $key=>$label)$this->input[$key]='42.500';
});

it('marks exam attendance independently and preserves it when editing candidate details',function(){
    $admin=User::find(1);$id=$this->controller->save(g12Request($admin,$this->input))->getData(true)['id'];
    $record=G9ExamCandidate::find($id);$data=$record->data;$students=DB::table('tb_student')->get()->toJson();
    expect($record->attendance_status)->toBeNull();
    $result=app(\App\Http\Controllers\G9ScoringController::class)->attendance(g12Request($admin,['version'=>1,'attendance_status'=>'absent']),$id)->getData(true);
    expect($result)->toMatchArray(['id'=>$id,'version'=>2,'attendance_status'=>'absent'])
        ->and(G9ExamCandidate::find($id)->data)->toBe($data)->and(DB::table('tb_student')->get()->toJson())->toBe($students);
    $audit=json_decode(DB::table('tb_g9_exam_event')->where('action','set-exam-attendance')->value('details'),true);
    expect($audit)->toBe(['before'=>null,'after'=>'absent','reason_before'=>null,'reason_after'=>null]);
    app(\App\Http\Controllers\G9ScoringController::class)->attendance(g12Request($admin,['version'=>2,'attendance_status'=>'absent']),$id);
    expect(G9ExamCandidate::find($id)->version)->toBe(2)->and(DB::table('tb_g9_exam_event')->where('action','set-exam-attendance')->count())->toBe(1);
    $this->controller->save(g12Request($admin,array_replace($this->input,['version'=>2,'father_name'=>'Updated Father'])),$id);
    expect(G9ExamCandidate::find($id)->attendance_status)->toBe('absent');
    $listed=app(\App\Http\Controllers\G9ScoringController::class)->listing(g12Request($admin,['academic_year'=>1]))->getData(true)['data'][0];
    expect($listed)->toMatchArray(['attendance_status'=>'absent','can_edit'=>true,'version'=>3]);
    app(\App\Http\Controllers\G9ScoringController::class)->attendance(g12Request($admin,['version'=>3,'attendance_status'=>null]),$id);
    expect(G9ExamCandidate::find($id)->attendance_status)->toBeNull();
});

it('autosaves absence reasons and clears them when absence is removed without touching scores',function(){
    $admin=User::find(1);$scoring=app(\App\Http\Controllers\G9ScoringController::class);
    $id=$this->controller->save(g12Request($admin,$this->input))->getData(true)['id'];$before=G9ExamCandidate::find($id)->data;
    $scoring->attendance(g12Request($admin,['version'=>1,'attendance_status'=>'absent','attendance_reason'=>' Sick leave ']),$id);
    expect(G9ExamCandidate::find($id)->attendance_reason)->toBe('Sick leave');
    $result=$scoring->attendance(g12Request($admin,['version'=>2,'attendance_status'=>'absent','attendance_reason'=>'Medical appointment']),$id)->getData(true);
    expect($result)->toMatchArray(['version'=>3,'attendance_reason'=>'Medical appointment']);
    $scoring->attendance(g12Request($admin,['version'=>3,'attendance_status'=>'absent','attendance_reason'=>'Medical appointment']),$id);
    expect(G9ExamCandidate::find($id)->version)->toBe(3);
    $scoring->attendance(g12Request($admin,['version'=>3,'attendance_status'=>'absent']),$id);
    expect(G9ExamCandidate::find($id)->attendance_reason)->toBe('Medical appointment');
    $list=$scoring->listing(g12Request($admin,['academic_year'=>1]))->getData(true)['data'][0];expect($list['attendance_reason'])->toBe('Medical appointment');
    $audit=json_decode(DB::table('tb_g9_exam_event')->where('action','set-exam-attendance')->latest('id')->value('details'),true);
    expect($audit)->toMatchArray(['reason_before'=>'Sick leave','reason_after'=>'Medical appointment']);
    foreach([str_repeat('a',1001),['invalid']] as $reason)expect(fn()=>$scoring->attendance(g12Request($admin,['version'=>3,'attendance_status'=>'absent','attendance_reason'=>$reason]),$id))->toThrow(ValidationException::class);
    $scoring->attendance(g12Request($admin,['version'=>3,'attendance_status'=>null,'attendance_reason'=>'Ignored']),$id);
    expect(G9ExamCandidate::find($id)->attendance_status)->toBeNull()->and(G9ExamCandidate::find($id)->attendance_reason)->toBeNull()->and(G9ExamCandidate::find($id)->data)->toBe($before);
});

it('validates exam attendance and rejects stale candidate versions',function(){
    $admin=User::find(1);$id=$this->controller->save(g12Request($admin,$this->input))->getData(true)['id'];
    foreach([['version'=>1],['attendance_status'=>'absent'],['version'=>1,'attendance_status'=>'present'],['version'=>1,'attendance_status'=>'late'],['version'=>1,'attendance_status'=>['absent']]] as $input)
        expect(fn()=>app(\App\Http\Controllers\G9ScoringController::class)->attendance(g12Request($admin,$input),$id))->toThrow(ValidationException::class);
    app(\App\Http\Controllers\G9ScoringController::class)->attendance(g12Request($admin,['version'=>1,'attendance_status'=>'absent']),$id);
    try{app(\App\Http\Controllers\G9ScoringController::class)->attendance(g12Request($admin,['version'=>1,'attendance_status'=>null]),$id);test()->fail('Expected stale attendance conflict.');}
    catch(HttpException $failure){expect($failure->getStatusCode())->toBe(409);}
    expect(G9ExamCandidate::find($id)->attendance_status)->toBe('absent')->and(G9ExamCandidate::find($id)->version)->toBe(2);
});

it('limits exam attendance editing to permitted campuses and hides other campuses in scoring',function(){
    $admin=User::find(1);$user=User::find(2);
    $id=$this->controller->save(g12Request($admin,$this->input))->getData(true)['id'];
    $remote=$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'OTHER','campus_id'=>2])))->getData(true)['id'];
    foreach(['view','scoring'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    expect(fn()=>app(\App\Http\Controllers\G9ScoringController::class)->attendance(g12Request($user,['version'=>1,'attendance_status'=>'absent']),$id))->toThrow(HttpException::class);
    DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.scoring-edit')->value('id'),'allowed'=>true]);
    app(\App\Http\Controllers\G9ScoringController::class)->attendance(g12Request($user,['version'=>1,'attendance_status'=>'absent']),$id);
    expect(fn()=>app(\App\Http\Controllers\G9ScoringController::class)->attendance(g12Request($user,['version'=>1,'attendance_status'=>'absent']),$remote))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    app(\App\Http\Controllers\G9ScoringController::class)->attendance(g12Request($admin,['version'=>1,'attendance_status'=>'absent']),$remote);
    $rows=collect(app(\App\Http\Controllers\G9ScoringController::class)->listing(g12Request($user,['academic_year'=>1]))->getData(true)['data'])->keyBy('id');
    expect($rows[$id])->toMatchArray(['attendance_status'=>'absent','can_edit'=>true])
        ->and($rows->has($remote))->toBeFalse();
});

it('autosaves national subject scores without changing other fields and rejects stale versions',function(){
    $admin=User::find(1);$scoring=app(\App\Http\Controllers\G9ScoringController::class);
    $id=$this->controller->save(g12Request($admin,$this->input))->getData(true)['id'];
    $student=DB::table('tb_student')->get()->toJson();$before=G9ExamCandidate::find($id)->data;
    $result=$scoring->save(g12Request($admin,['version'=>1,'scores'=>['score_math'=>'0','score_english'=>null,'score_physics'=>'54.125']]),$id)->getData(true);
    expect($result['version'])->toBe(2)->and($result['scores'])->toHaveCount(11)->toMatchArray(['score_math'=>'0.000','score_english'=>null,'score_physics'=>'54.125']);
    expect(G9ExamCandidate::find($id)->data)->toBe(array_replace($before,['score_math'=>'0.000','score_english'=>null,'score_physics'=>'54.125']));
    expect(DB::table('tb_student')->get()->toJson())->toBe($student);
    $event=DB::table('tb_g9_exam_event')->where('action','save-scores')->first();$audit=json_decode($event->details,true);
    expect($audit['before'])->toMatchArray(['score_math'=>'42.500','score_english'=>'42.500'])
        ->and($audit['after'])->toMatchArray(['score_math'=>'0.000','score_english'=>null]);
    $unchanged=$scoring->save(g12Request($admin,['version'=>2,'scores'=>['score_math'=>'0.0']]),$id)->getData(true);
    expect($unchanged['version'])->toBe(2)->and(DB::table('tb_g9_exam_event')->where('action','save-scores')->count())->toBe(1);
    try{$scoring->save(g12Request($admin,['version'=>1,'scores'=>['score_math'=>90]]),$id);test()->fail('Expected a stale version conflict.');}
    catch(HttpException $failure){expect($failure->getStatusCode())->toBe(409);}
    expect(G9ExamCandidate::find($id)->data['score_math'])->toBe('0.000');
});

it('validates national scores and only accepts known subject fields',function(){
    $admin=User::find(1);$scoring=app(\App\Http\Controllers\G9ScoringController::class);
    $id=$this->controller->save(g12Request($admin,$this->input))->getData(true)['id'];$before=G9ExamCandidate::find($id)->toJson();
    foreach([['score_math'=>-1],['score_math'=>'1.2345'],['score_math'=>10000],['score_math'=>'invalid'],['eoy_average'=>100],['score_math'=>1,'father_name'=>'overwrite'],[]] as $scores)
        expect(fn()=>$scoring->save(g12Request($admin,['version'=>1,'scores'=>$scores]),$id))->toThrow(ValidationException::class);
    expect(fn()=>$scoring->save(g12Request($admin,['scores'=>['score_math'=>1]]),$id))->toThrow(ValidationException::class);
    expect(G9ExamCandidate::find($id)->toJson())->toBe($before);
    $result=$scoring->save(g12Request($admin,['version'=>1,'scores'=>array_fill_keys(array_keys(G9ExamFields::GROUPS['National Examination Scores']),'99.999')]),$id)->getData(true);
    expect(array_unique(array_values($result['scores'])))->toBe(['99.999'])->and($result['version'])->toBe(2);
});

it('scopes scoring views and edits by campus with separate permissions and parent denials',function(){
    $admin=User::find(1);$user=User::find(2);$scoring=app(\App\Http\Controllers\G9ScoringController::class);
    $local=$this->controller->save(g12Request($admin,$this->input))->getData(true)['id'];
    $remote=$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'REMOTE','campus_id'=>2])))->getData(true)['id'];
    expect(fn()=>$scoring->listing(g12Request($user,['academic_year'=>1])))->toThrow(HttpException::class);
    foreach(['view','scoring'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $list=$scoring->listing(g12Request($user,['academic_year'=>1]))->getData(true);
    expect($list['total'])->toBe(1)->and($list['data'][0]['can_edit'])->toBeFalse();
    expect(fn()=>$scoring->save(g12Request($user,['version'=>1,'scores'=>['score_math'=>1]]),$local))->toThrow(HttpException::class);
    DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.scoring-edit')->value('id'),'allowed'=>true]);
    expect($scoring->save(g12Request($user,['version'=>1,'scores'=>['score_math'=>0]]),$local)->getData(true)['scores']['score_math'])->toBe('0.000');
    expect(fn()=>$scoring->save(g12Request($user,['version'=>1,'scores'=>['score_math'=>1]]),$remote))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect($scoring->listing(g12Request($user,['academic_year'=>1,'campus'=>2]))->getData(true)['total'])->toBe(0);
    DB::table('users')->where('id',2)->update(['is_global'=>true]);$central=User::find(2);
    expect($scoring->save(g12Request($central,['version'=>1,'scores'=>['score_math'=>90]]),$remote)->getData(true)['version'])->toBe(2);
    DB::table('access_user_permission_overrides')->where('user_id',2)->where('permission_id',DB::table('access_permissions')->where('code','g9-national-exams.scoring')->value('id'))->update(['allowed'=>false]);
    expect(fn()=>$scoring->save(g12Request($central,['version'=>2,'scores'=>['score_math'=>1]]),$remote))->toThrow(HttpException::class);
});

it('cascades scoring filters and sorts by seat number numerically before room with pagination',function(){
    $admin=User::find(1);$scoring=app(\App\Http\Controllers\G9ScoringController::class);
    $first=$this->controller->save(g12Request($admin,array_replace($this->input,['room_number'=>'10','seat_number'=>'2'])))->getData(true)['id'];
    $second=$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'SECOND','room_number'=>'2','seat_number'=>'10'])))->getData(true)['id'];
    $third=$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'THIRD','room_number'=>'2','seat_number'=>'2','campus_id'=>2,'grade'=>'9B'])))->getData(true)['id'];
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027']);
    $view=$scoring->index(g12Request($admin))->getData();expect($view['years']->pluck('id')->all())->toBe([1])->and($view['subjects'])->toHaveCount(11);
    $list=$scoring->listing(g12Request($admin,['academic_year'=>1,'per_page'=>'100']))->getData(true);
    expect(array_column($list['data'],'id'))->toBe([$third,$first,$second])->and($list['total'])->toBe(3);
    expect($list['data'][0])->not->toHaveKeys(['father_name','phone_number','data']);
    expect($list['data'][0]['eoy_average'])->toBe('90.000');
    expect($list['data'][0]['exam_language'])->toBe('Khmer');
    $choices=$scoring->options(g12Request($admin,['academic_year'=>1,'exam_center'=>'Test Center','campus'=>2,'room'=>'2','grade'=>'9B']))->getData(true);
    expect($choices['exam_center'])->toBe(['Test Center'])->and($choices['campus'])->toHaveCount(2)->and($choices['room'])->toBe(['2'])
        ->and($choices['grade'])->toBe(['9B'])->and(array_column($choices['student'],'id'))->toBe([$third]);
    expect($scoring->listing(g12Request($admin,['academic_year'=>1,'campus'=>1,'grade'=>'9B']))->getData(true)['total'])->toBe(0);
    for($i=0;$i<25;$i++)$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'PAGED-'.$i])));
    $page=$scoring->listing(g12Request($admin,['academic_year'=>1,'page'=>2]))->getData(true);
    expect($page['total'])->toBe(28)->and($page['per_page'])->toBe(25)->and($page['data'])->toHaveCount(3);
});

it('calculates verification totals with identical language rules rounding and grading boundaries',function(){
    $scores=array_fill_keys(array_keys(G9ExamFields::GROUPS['National Examination Scores']),'40');
    foreach(['English','en','អង់គ្លេស'] as $language){
        $summary=\App\Support\G9ScoreSummary::calculate($scores+['exam_language'=>$language,'eoy_average'=>'90.5']);
        expect($summary)->toMatchArray(['total'=>491,'eoy'=>91,'complete'=>true,'english_contribution'=>0,'result'=>'ជាប់','grade'=>'ល្អ']);
    }
    foreach(['Khmer','kh','km','ខ្មែរ'] as $language)foreach(['24.999'=>490,'25'=>490,'25.001'=>490.001,'50'=>515] as $english=>$expected){
        $summary=\App\Support\G9ScoreSummary::calculate(array_replace($scores,['exam_language'=>$language,'eoy_average'=>90,'score_english'=>$english]));
        expect($summary['total'])->toEqual($expected)->and($summary['complete'])->toBeTrue();
    }
    foreach(['259.999'=>'ធ្លាក់','260'=>'មធ្យម','337.999'=>'មធ្យម','338'=>'ល្អបង្គួរ','415.999'=>'ល្អបង្គួរ','416'=>'ល្អ'] as $total=>$grade){
        $summary=\App\Support\G9ScoreSummary::calculate(array_replace(array_fill_keys(array_keys($scores),'0'),['exam_language'=>'English','eoy_average'=>0,'score_math'=>$total]));
        expect($summary['grade'])->toBe($grade);
    }
    $partial=\App\Support\G9ScoreSummary::calculate(array_replace($scores,['exam_language'=>'English','eoy_average'=>90,'score_math'=>null]));
    expect($partial)->toMatchArray(['complete'=>false,'result'=>null,'grade'=>null]);
    $excluded=\App\Support\G9ScoreSummary::calculate(array_replace($scores,['exam_language'=>'English','eoy_average'=>90,'score_english'=>null]));
    expect($excluded)->toMatchArray(['complete'=>true,'total'=>490]);
    foreach(['',null,'Other'] as $language)expect(\App\Support\G9ScoreSummary::calculate($scores+['exam_language'=>$language,'eoy_average'=>90]))->toMatchArray(['total'=>null,'complete'=>false,'result'=>null]);
    expect(\App\Support\G9ScoreSummary::display(0))->toBe('0')->and(\App\Support\G9ScoreSummary::display(null))->toBe('—');
});

it('prints verification sheets by room with 25 candidates per A4 landscape page and preserves all records',function(){
    $admin=User::find(1);$scoring=app(\App\Http\Controllers\G9ScoringController::class);$reports=app(\App\Support\G9ScoringReport::class);
    $data=array_replace($this->input,array_fill_keys(array_keys(G9ExamFields::GROUPS['National Examination Scores']),'40'),[
        'name_kh'=>'កង សុគន្ធនរិន្ទ','exam_center'=>'វិ. សាលាវេស្ទើនអន្តរជាតិ (ភ្នំពេញថ្មី) (ត្បូង)',
        'school_name'=>'វិ. សាលាវេស្ទើនអន្តរជាតិ (ភ្នំពេញថ្មី)','eoy_average'=>'90.500','score_english'=>'50']);
    for($i=26;$i>=1;$i--)$this->controller->save(g12Request($admin,array_replace($data,['student_code'=>'VERIFY-'.$i,'seat_number'=>(string)$i,'score_math'=>$i===1?null:'40'])));
    $this->controller->save(g12Request($admin,array_replace($data,['student_code'=>'ROOM-2','room_number'=>'2','exam_center'=>'Provincial Center','seat_number'=>'1'])));
    $this->controller->save(g12Request($admin,array_replace($data,['student_code'=>'REMOTE','campus_id'=>2,'exam_center'=>'Hidden Remote Center'])));
    $before=G9ExamCandidate::all()->toJson();
    DB::table('tb_g9_exam_year_setting')->insert(['academic_year_id'=>1,'version'=>1,'data'=>json_encode(['report_centers'=>[['exam_center'=>'Provincial Center','location'=>'ខេត្តព្រះសីហនុ']]]),'updated_by'=>1,'created_at'=>now(),'updated_at'=>now()]);
    $html=$scoring->report(g12Request($admin,['academic_year'=>1,'campus'=>1,'per_page'=>'25']),'print',$reports)->getContent();
    expect(substr_count($html,'<section class="verify-page">'))->toBe(3)->and($html)->toContain('តារាងផ្ទៀងផ្ទាត់ពិន្ទុ','ព្រះសីហនុ','516')->not->toContain('Hidden Remote Center','Father','012345678');
    expect($html)->toContain(\App\Support\G9ScoringReport::HEADING,'<th width="20mm" colspan="2">ភាសាបរទេស</th>')->not->toContain('ឆ្នាំសិក្សា៖','ភាសា បូកបន្ថែម');
    $records=G9ExamCandidate::where('campus_id',1)->where('data->room_number','01')->get();$pages=$reports->pages($records,[]);
    expect($pages)->toHaveCount(2)->and($pages[0]['rows'])->toHaveCount(25)->and($pages[1]['rows'])->toHaveCount(1)
        ->and($pages[0]['rows'][0]['seat'])->toBe('1')->and($pages[0]['rows'][24]['seat'])->toBe('25')
        ->and($pages[0]['summary'])->toMatchArray(['total'=>26,'female'=>26,'passed'=>25,'failed'=>0,'pending'=>1]);
    expect($pages[0]['rows'][0]['summary'])->toMatchArray(['complete'=>false,'result'=>null,'grade'=>null]);
    $pdf=$scoring->report(g12Request($admin,['academic_year'=>1,'campus'=>1]),'pdf',$reports);
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf')->and($pdf->getContent())->toStartWith('%PDF-');
    if(getenv('G9_REPORT_QA')){
        $folder=storage_path('app/private/g9-report-qa');if(!is_dir($folder))mkdir($folder,0755,true);
        file_put_contents($folder.'/scoring-verification.pdf',$pdf->getContent());file_put_contents($folder.'/scoring-verification.html',$html);
    }
    $reader=new \Mpdf\Mpdf(['tempDir'=>storage_path('app/private/g9-report-pdf')]);
    expect($reader->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($pdf->getContent())))->toBe(3);
    $size=$reader->getTemplateSize($reader->importPage(1));expect(round($size['width']))->toBe(297.0)->and(round($size['height']))->toBe(210.0);
    expect(G9ExamCandidate::all()->toJson())->toBe($before);
    expect(fn()=>$scoring->report(g12Request($admin,['academic_year'=>1,'student'=>999]),'print',$reports))->toThrow(HttpException::class);
});

it('prints score collection sheets with one foreign language column absence remarks and center signing dates',function(){
    $admin=User::find(1);$scoring=app(\App\Http\Controllers\G9ScoringController::class);$reports=app(\App\Support\G9ScoringReport::class);
    $input=array_replace($this->input,array_fill_keys(array_keys(G9ExamFields::GROUPS['National Examination Scores']),'20'),[
        'name_kh'=>'កង សុគន្ធនរិន្ទ','exam_center'=>'វិ. សាលាវេស្ទើនអន្តរជាតិ (ភ្នំពេញថ្មី) (ត្បូង)',
        'school_name'=>'វិ. សាលាវេស្ទើនអន្តរជាតិ (ភ្នំពេញថ្មី)','eoy_average'=>'90','score_english'=>'32','room_number'=>'18']);
    for($i=26;$i>=1;$i--){
        $candidate=array_replace($input,['student_code'=>'COLLECT-'.$i,'seat_number'=>(string)$i]);
        if($i===2)$candidate=array_replace($candidate,array_fill_keys(array_keys(G9ExamFields::GROUPS['National Examination Scores']),'0'));
        if($i===4)$candidate['score_math']=null;
        $id=$this->controller->save(g12Request($admin,$candidate))->getData(true)['id'];
        if($i===3)$scoring->attendance(g12Request($admin,['version'=>1,'attendance_status'=>'absent','attendance_reason'=>str_repeat('Medical leave ',50)]),$id);
    }
    $this->controller->save(g12Request($admin,array_replace($input,['student_code'=>'REMOTE-COLLECTION','campus_id'=>2,'exam_center'=>'Hidden Remote Center'])));
    $settings=['resultDate'=>'Year date','k_resultDate'=>'Year lunar','report_centers'=>[['exam_center'=>$input['exam_center'],'location'=>'ខេត្តព្រះសីហនុ','signer'=>'ប្រធានមណ្ឌល','signer_name'=>'សុខ សាន្ត',
        'dates'=>['resultDate'=>['date'=>'2026-08-30','solar'=>'Center date','lunar'=>'Center lunar date']]]]];
    DB::table('tb_g9_exam_year_setting')->insert(['academic_year_id'=>1,'version'=>1,'data'=>json_encode($settings),'updated_by'=>1,'created_at'=>now(),'updated_at'=>now()]);
    $before=G9ExamCandidate::all()->toJson();
    $pages=$reports->pages(G9ExamCandidate::where('campus_id',1)->get(),$settings,'scores');
    expect($pages)->toHaveCount(2)->and($pages[0]['rows'])->toHaveCount(25)->and($pages[1]['rows'])->toHaveCount(1)
        ->and($pages[0]['rows'][0]['seat'])->toBe('1')->and($pages[0]['rows'][2]['absent'])->toBeTrue()
        ->and($pages[0]['rows'][0]['summary']['english_contribution'])->toBe(7)
        ->and($pages[0]['summary'])->toMatchArray(['total'=>26,'female'=>26,'passed'=>23,'failed'=>1,'absent'=>1,'absent_female'=>1,'pending'=>1])
        ->and($pages[0]['solar'])->toContain('ខេត្តព្រះសីហនុ','៣០','សីហា')->and($pages[0]['lunar'])->toBe('Center lunar date');
    $html=$scoring->collectionReport(g12Request($admin,['academic_year'=>1,'campus'=>1,'per_page'=>'25']),'print',$reports)->getContent();
    expect($html)->toContain('តារាងស្រង់ពិន្ទុ','collection-scores','collection-absent','អវត្តមាន','សុខ សាន្ត','Center lunar date','>១<')
        ->not->toContain('verify-max','>50</th>','>-25</th>','Hidden Remote Center','សម្រាប់ផ្ទៀងផ្ទាត់','Medical leave');
    expect(substr_count($html,'<section class="verify-page">'))->toBe(2);
    $dom=new \DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new \DOMXPath($dom);
    expect($xpath->query('//span[@class="collection-seat-circle"]')->length)->toBe(23)
        ->and($xpath->query('//tr[contains(@class,"collection-failed")]')->length)->toBe(2)
        ->and($xpath->query('//tr[contains(@class,"collection-failed")]//span[@class="collection-seat-circle"]')->length)->toBe(0);
    $tableRows=$xpath->query('//table[contains(@class,"collection-scores")]/tbody/tr');
    expect($tableRows->item(0)->getElementsByTagName('span')->item(0)->getAttribute('class'))->toBe('collection-seat-circle')
        ->and($tableRows->item(1)->getAttribute('class'))->toContain('collection-failed')
        ->and($tableRows->item(3)->getAttribute('class'))->not->toContain('collection-failed')
        ->and($tableRows->item(3)->getElementsByTagName('span')->item(0)->getAttribute('class'))->toBe('');
    $pdf=$scoring->collectionReport(g12Request($admin,['academic_year'=>1,'campus'=>1]),'pdf',$reports);
    if(getenv('G9_REPORT_QA')){
        $folder=storage_path('app/private/g9-report-qa');if(!is_dir($folder))mkdir($folder,0755,true);
        file_put_contents($folder.'/score-collection.pdf',$pdf->getContent());file_put_contents($folder.'/score-collection.html',$html);
    }
    expect($pdf->getContent())->toStartWith('%PDF-')->and($pdf->headers->get('Content-Disposition'))->toContain('g9-score-collection.pdf')
        ->and(preg_match_all('~/Type\s*/Page\b~',$pdf->getContent()))->toBe(2);
    expect(G9ExamCandidate::all()->toJson())->toBe($before);
});

it('requires separate scoring print and PDF permissions with campus and parent restrictions',function(){
    $admin=User::find(1);$user=User::find(2);$scoring=app(\App\Http\Controllers\G9ScoringController::class);$reports=app(\App\Support\G9ScoringReport::class);
    $this->controller->save(g12Request($admin,$this->input));$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'REMOTE','campus_id'=>2,'exam_center'=>'Remote Private Center'])));
    foreach(['view','scoring'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    expect($scoring->index(g12Request($user))->getData()['reportFormats'])->toBe([]);
    expect(fn()=>$scoring->report(g12Request($user,['academic_year'=>1]),'print',$reports))->toThrow(HttpException::class);
    expect(fn()=>$scoring->collectionReport(g12Request($user,['academic_year'=>1]),'print',$reports))->toThrow(HttpException::class);
    DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.scoring-print')->value('id'),'allowed'=>true]);
    expect($scoring->index(g12Request($user))->getData()['reportFormats'])->toBe(['print']);
    $html=$scoring->report(g12Request($user,['academic_year'=>1]),'print',$reports)->getContent();expect($html)->toContain('Test Center')->not->toContain('Remote Private Center');
    expect($scoring->collectionReport(g12Request($user,['academic_year'=>1]),'print',$reports)->getContent())->toContain('Test Center')->not->toContain('Remote Private Center');
    expect(fn()=>$scoring->report(g12Request($user,['academic_year'=>1]),'pdf',$reports))->toThrow(HttpException::class);
    expect(fn()=>$scoring->collectionReport(g12Request($user,['academic_year'=>1]),'pdf',$reports))->toThrow(HttpException::class);
    expect(fn()=>$scoring->report(g12Request($user,['academic_year'=>1,'campus'=>2]),'print',$reports))->toThrow(HttpException::class);
    DB::table('users')->where('id',2)->update(['is_global'=>true]);$central=User::find(2);
    expect($scoring->report(g12Request($central,['academic_year'=>1,'campus'=>2]),'print',$reports)->getContent())->toContain('Remote Private Center');
    DB::table('access_user_permission_overrides')->where('user_id',2)->where('permission_id',DB::table('access_permissions')->where('code','g9-national-exams.scoring')->value('id'))->update(['allowed'=>false]);
    expect(fn()=>$scoring->report(g12Request($central,['academic_year'=>1]),'print',$reports))->toThrow(HttpException::class);
});

it('assigns the sum of semester scores rounded to a whole number without replacing candidate details',function(){
    $admin=User::find(1);$eoy=app(\App\Http\Controllers\G9EoyAverageController::class);
    $id=$this->controller->save(g12Request($admin,$this->input))->getData(true)['id'];
    $before=G9ExamCandidate::find($id)->data;
    $list=$eoy->listing(g12Request($admin,['academic_year'=>1]))->getData(true);
    expect($list['data'][0])->toMatchArray(['semester_1'=>'89.50','semester_2'=>'90.50','calculated_eoy'=>'180','saved_eoy'=>'90.000','can_assign'=>true]);
    expect(G9ExamCandidate::find($id)->data)->toBe($before);
    $result=$eoy->save(g12Request($admin,['version'=>1,'semester_1'=>'1.005','semester_2'=>'2.004']),$id)->getData(true);
    expect($result)->toMatchArray(['semester_1'=>'1.01','semester_2'=>'2.00','version'=>2,'eoy_average'=>'3']);
    $saved=G9ExamCandidate::find($id);
    expect($saved->data)->toMatchArray(['semester_1'=>'1.01','semester_2'=>'2.00','eoy_average'=>'3','father_name'=>'Father','score_math'=>'42.500']);
    expect(DB::table('tb_g9_exam_event')->where('action','assign-eoy')->count())->toBe(1);
    expect(fn()=>$eoy->save(g12Request($admin,['version'=>1,'semester_1'=>10,'semester_2'=>20]),$id))->toThrow(HttpException::class);
    expect(G9ExamCandidate::find($id)->data['eoy_average'])->toBe('3');
    expect(\App\Http\Controllers\G9EoyAverageController::total('0','0'))->toBe('0')
        ->and(\App\Http\Controllers\G9EoyAverageController::total(null,'0'))->toBeNull()
        ->and(\App\Http\Controllers\G9EoyAverageController::total('9999.999','9999.999'))->toBe('20000')
        ->and(\App\Http\Controllers\G9EoyAverageController::total('10.244','20.250'))->toBe('30')
        ->and(\App\Http\Controllers\G9EoyAverageController::total('10.245','20.250'))->toBe('31');
    expect(fn()=>$eoy->save(g12Request($admin,['version'=>2,'semester_1'=>-1,'semester_2'=>20]),$id))->toThrow(ValidationException::class);
});

it('autosaves individual semester scores while leaving an incomplete EOY total blank',function(){
    $admin=User::find(1);$eoy=app(\App\Http\Controllers\G9EoyAverageController::class);
    $id=$this->controller->save(g12Request($admin,array_replace($this->input,['semester_1'=>null,'semester_2'=>null,'eoy_average'=>null])))->getData(true)['id'];
    $first=$eoy->save(g12Request($admin,['version'=>1,'semester_1'=>'89.505','semester_2'=>null]),$id)->getData(true);
    expect($first)->toMatchArray(['version'=>2,'semester_1'=>'89.51','semester_2'=>null,'eoy_average'=>null]);
    $second=$eoy->save(g12Request($admin,['version'=>2,'semester_1'=>'89.51','semester_2'=>'90.50']),$id)->getData(true);
    expect($second)->toMatchArray(['version'=>3,'semester_1'=>'89.51','semester_2'=>'90.50','eoy_average'=>'180']);
    expect(fn()=>$eoy->save(g12Request($admin,['version'=>2,'semester_1'=>'1.00','semester_2'=>'2.00']),$id))->toThrow(HttpException::class);
    expect(G9ExamCandidate::find($id)->data)->toMatchArray(['semester_1'=>'89.51','semester_2'=>'90.50','eoy_average'=>'180','father_name'=>'Father']);
    expect(fn()=>$eoy->save(g12Request($admin,['version'=>3,'semester_1'=>'50.00']),$id))->toThrow(ValidationException::class);
});

it('preserves historical imported EOY totals without semester scores across reports and partial edits',function(string $saved,string $display){
    DB::table('tb_academic_year')->where('id',1)->update(['academic_year'=>'2024-2025']);
    $admin=User::find(1);$service=app(\App\Services\G9ExamImports::class);$reader=app(\App\Support\G9ExamSpreadsheet::class);
    $imports=app(\App\Http\Controllers\G9ExamImportController::class);$eoy=app(\App\Http\Controllers\G9EoyAverageController::class);$reports=app(\App\Support\G9EoyReport::class);
    $input=array_replace($this->input,['academic_year_id'=>'2024-2025','campus_id'=>'BCH','semester_1'=>null,'semester_2'=>null,'eoy_average'=>$saved]);
    $request=g12Request($admin,[],'/preview','POST');$request->files->set('file',UploadedFile::fake()->createWithContent('historical.csv',g9ImportCsv([$input])));
    $preview=$imports->preview($request,$service,$reader)->getData(true);
    expect($preview['summary']['errors'])->toBe([]);
    $imports->confirm(g12Request($admin,['token'=>$preview['token']],'/confirm','POST'),$service,$this->controller);
    $record=G9ExamCandidate::first();$before=$record->data;
    expect($before)->toMatchArray(['semester_1'=>null,'semester_2'=>null,'eoy_average'=>$saved]);
    $listed=$eoy->listing(g12Request($admin,['academic_year'=>1]))->getData(true)['data'][0];
    expect($listed)->toMatchArray(['semester_1'=>null,'semester_2'=>null,'saved_eoy'=>$saved,'calculated_eoy'=>null]);
    $pages=$reports->pages(G9ExamCandidate::all(),[],'card');
    expect($pages[0])->toMatchArray(['semester_1'=>null,'semester_2'=>null,'sum'=>$display,'eoy'=>$display]);
    $html=$eoy->report(g12Request($admin,['academic_year'=>1]),'card','print',$reports)->getContent();
    expect($html)->toContain('<div class="eoy-circle"><span>'.$display.'</span></div>');
    expect(G9ExamCandidate::first()->data)->toBe($before);
    if(getenv('G9_REPORT_QA')&&$saved==='90.000'){
        $folder=storage_path('app/private/g9-report-qa');if(!is_dir($folder))mkdir($folder,0755,true);
        file_put_contents($folder.'/eoy-historical.pdf',$reports->pdf($pages,'card'));
    }
    $partial=$eoy->save(g12Request($admin,['version'=>1,'semester_1'=>'12.345','semester_2'=>null]),$record->id)->getData(true);
    expect($partial)->toMatchArray(['semester_1'=>'12.35','semester_2'=>null,'eoy_average'=>$saved]);
    $audit=json_decode(DB::table('tb_g9_exam_event')->where('action','assign-eoy')->value('details'),true);
    expect($audit)->toMatchArray(['formula'=>null,'eoy_source'=>'saved-total']);
    $eoy->save(g12Request($admin,['version'=>2,'semester_1'=>null,'semester_2'=>null]),$record->id);
    expect(G9ExamCandidate::first()->data)->toMatchArray(['semester_1'=>null,'semester_2'=>null,'eoy_average'=>$saved,'father_name'=>'Father']);
    $complete=$eoy->save(g12Request($admin,['version'=>3,'semester_1'=>'12.35','semester_2'=>'20.25']),$record->id)->getData(true);
    expect($complete['eoy_average'])->toBe('33');
})->with([['90.000','90'],['0.000','0'],['90.500','91']]);

it('restricts EOY viewing and assignment separately to permitted campuses and cascades filters',function(){
    $admin=User::find(1);$user=User::find(2);$eoy=app(\App\Http\Controllers\G9EoyAverageController::class);
    $id=$this->controller->save(g12Request($admin,$this->input))->getData(true)['id'];
    $other=$this->controller->save(g12Request($admin,array_replace($this->input,['campus_id'=>2,'student_code'=>'OTHER','grade'=>'9B'])))->getData(true)['id'];
    expect(fn()=>$eoy->listing(g12Request($user,['academic_year'=>1])))->toThrow(HttpException::class);
    foreach(['view','eoy'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $list=$eoy->listing(g12Request($user,['academic_year'=>1]))->getData(true);
    expect($list['total'])->toBe(1)->and($list['data'][0]['can_assign'])->toBeFalse();
    expect(fn()=>$eoy->save(g12Request($user,['version'=>1,'semester_1'=>1,'semester_2'=>2]),$id))->toThrow(HttpException::class);
    DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.eoy-assign')->value('id'),'allowed'=>true]);
    expect($eoy->save(g12Request($user,['version'=>1,'semester_1'=>0,'semester_2'=>0]),$id)->getData(true)['eoy_average'])->toBe('0');
    expect(fn()=>$eoy->save(g12Request($user,['version'=>1,'semester_1'=>1,'semester_2'=>2]),$other))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    $choices=$eoy->options(g12Request($admin,['academic_year'=>1,'campus'=>2,'grade'=>'9B']))->getData(true);
    expect($choices['campus'])->toHaveCount(2)->and($choices['grade'])->toBe(['9B'])->and(array_column($choices['student'],'id'))->toBe([$other]);
    expect($eoy->listing(g12Request($admin,['academic_year'=>1,'campus'=>1,'grade'=>'9B']))->getData(true)['total'])->toBe(0);
});

it('exposes every G9 submenu and implemented action in the permission hierarchy',function(){
    $permissions=\App\Models\Permission::all();$tree=\App\Support\PermissionHierarchy::tree($permissions)['g9-national-exams'];
    expect($tree['permission']->code)->toBe('g9-national-exams.view')->and($tree['modules'])->toHaveCount(8);
    $codes=collect([$tree['permission']->code]);
    foreach($tree['modules'] as $module){$codes->push($module['permission']->code);$codes=$codes->merge($module['actions']->pluck('code'));}
    expect($codes->sort()->values()->all())->toBe(collect(array_keys(\App\Support\G9NationalExamPermissions::catalog()))->map(fn($action)=>'g9-national-exams.'.$action)->sort()->values()->all());
    $ids=$permissions->whereIn('code',['g9-national-exams.view','g9-national-exams.eoy-assign'])->pluck('id')->all();
    $normalized=\App\Support\PermissionHierarchy::normalizeIds($ids,$permissions);
    expect($normalized)->not->toContain($permissions->firstWhere('code','g9-national-exams.eoy-assign')->id);
});

it('enforces cross campus report permissions separately from EOY changes for campus and central users',function(){
    $admin=User::find(1);$user=User::find(2);$eoy=app(\App\Http\Controllers\G9EoyAverageController::class);$reports=app(\App\Support\G9CandidateReport::class);
    $local=$this->controller->save(g12Request($admin,$this->input))->getData(true)['id'];
    $remote=$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'REMOTE','campus_id'=>2,'exam_center'=>'Remote Center'])))->getData(true)['id'];
    foreach(['view','candidates','print','report-candidates','eoy','eoy-assign'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    expect($this->controller->listing(g12Request($user,['academic_year'=>1]))->getData(true)['total'])->toBe(2);
    $html=$this->controller->report(g12Request($user,['academic_year'=>1,'campus'=>2]),'candidates','print',$reports)->getContent();
    expect($html)->toContain('Remote Center');
    expect(fn()=>$this->controller->report(g12Request($user,['academic_year'=>1]),'candidates','pdf',$reports))->toThrow(HttpException::class);
    expect(fn()=>$this->controller->report(g12Request($user,['academic_year'=>1]),'profile','print',$reports))->toThrow(HttpException::class);
    $eoy->save(g12Request($user,['version'=>1,'semester_1'=>40,'semester_2'=>50]),$local);
    expect(fn()=>$eoy->save(g12Request($user,['version'=>1,'semester_1'=>40,'semester_2'=>50]),$remote))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect(G9ExamCandidate::find($remote)->data['semester_1'])->toBe('89.500');
    DB::table('users')->where('id',2)->update(['is_global'=>true]);$central=User::find(2);
    expect($eoy->save(g12Request($central,['version'=>1,'semester_1'=>40,'semester_2'=>50]),$remote)->getData(true)['eoy_average'])->toBe('90');
    DB::table('access_user_permission_overrides')->where('user_id',2)->where('permission_id',DB::table('access_permissions')->where('code','g9-national-exams.eoy-assign')->value('id'))->update(['allowed'=>false]);
    expect(fn()=>$eoy->save(g12Request($central,['version'=>2,'semester_1'=>1,'semester_2'=>2]),$remote))->toThrow(HttpException::class);
});

it('supports role and department grants and honors explicit G9 denials and parent switches',function(){
    $user=User::find(2);$codes=['view','candidates','print','report-candidates','eoy','eoy-assign'];
    DB::table('access_user_roles')->insert(['user_id'=>2,'role_id'=>2,'campus_id'=>1]);
    foreach($codes as $action)DB::table('access_role_permissions')->insert(['role_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id')]);
    g12Request($user);
    expect(\App\Support\G9NationalExamPermissions::reportCampuses($user,'print'))->toBe([1,2])
        ->and(\App\Support\G9NationalExamPermissions::campusIds($user,'eoy-assign'))->toBe([1]);
    DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.print')->value('id'),'allowed'=>false]);
    g12Request($user);expect(\App\Support\G9NationalExamPermissions::allows($user,'print'))->toBeFalse();
    DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.view')->value('id'),'allowed'=>false]);
    g12Request($user);expect(\App\Support\G9NationalExamPermissions::allows($user,'candidates'))->toBeFalse();
    DB::table('access_user_permission_overrides')->where('user_id',2)->delete();DB::table('access_user_roles')->where('user_id',2)->delete();
    DB::table('access_departments')->insert(['id'=>1,'name'=>'Campus Registrar']);DB::table('users')->where('id',2)->update(['department_id'=>1]);
    foreach($codes as $action)DB::table('access_department_permissions')->insert(['department_id'=>1,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id')]);
    $user=User::find(2);g12Request($user);
    expect(\App\Support\G9NationalExamPermissions::reportCampuses($user,'print'))->toBe([1,2])
        ->and(\App\Support\G9NationalExamPermissions::campusIds($user,'eoy-assign'))->toBe([1]);
});

it('retains legacy grants and explicit report denials when migrating the G9 permission list',function(){
    $before=DB::table('tb_student')->get()->toJson();
    foreach(['view','export'] as $action)DB::table('access_role_permissions')->insert(['role_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id')]);
    foreach(['view','export'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.pdf')->value('id'),'allowed'=>false]);
    $migration=require database_path('migrations/2026_11_05_000002_complete_g9_permission_catalog.php');$migration->up();$migration->up();
    $user=User::find(2);g12Request($user);
    expect(\App\Support\G9NationalExamPermissions::allows($user,'print'))->toBeTrue()
        ->and(\App\Support\G9NationalExamPermissions::allows($user,'pdf'))->toBeFalse();
    expect(DB::table('tb_student')->get()->toJson())->toBe($before);
    expect(DB::table('access_role_permissions')->where('role_id',2)->where('permission_id',DB::table('access_permissions')->where('code','g9-national-exams.print')->value('id'))->count())->toBe(1);
});

it('hides unauthorized G9 submenus and blocks direct routes',function(){
    $user=User::find(2);
    foreach(['view','eoy'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $request=g12Request($user);\Illuminate\Support\Facades\Auth::setUser($user);
    $menu=view('layouts.partials.g9-national-exams-menu',['moeysMenuIcon'=>'/test.png'])->render();
    expect($menu)->toContain('G9 National Exams','Assign EOY Average')->not->toContain('List of Candidate','G9 Bulk Stu. Photo','G9 Setting');
    $routes=app('router')->getRoutes();
    $scoring=$routes->getByName('g9-national-exams.scoring')->getAction('uses');
    expect($scoring)->toBe(\App\Http\Controllers\G9ScoringController::class.'@index');
    expect(fn()=>app(\App\Http\Controllers\G9ScoringController::class)->index($request))->toThrow(HttpException::class);
    $landing=$routes->getByName('g9-national-exams.index')->getAction('uses');
    expect($landing($request)->getTargetUrl())->toEndWith('/g9-national-exams/eoy-average');
});

it('allows active global roles to edit all permitted EOY campuses without expanding inactive roles',function(){
    $user=User::find(2);
    DB::table('access_roles')->where('id',2)->update(['is_global'=>true]);DB::table('access_user_roles')->insert(['user_id'=>2,'role_id'=>2,'campus_id'=>null]);
    foreach(['view','eoy','eoy-assign'] as $action)DB::table('access_role_permissions')->insert(['role_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id')]);
    g12Request($user);expect(\App\Support\G9NationalExamPermissions::campusIds($user,'eoy-assign'))->toBe([1,2]);
    DB::table('access_roles')->where('id',2)->update(['status'=>false]);
    g12Request($user);expect(\App\Support\G9NationalExamPermissions::campusIds($user,'eoy-assign'))->toBe([]);
});

it('saves annual score card names separately by campus and year with scoped permissions and audit history',function(){
    $admin=User::find(1);$user=User::find(2);$settings=app(\App\Http\Controllers\G9EoySettingController::class);
    foreach(['view','settings','eoy-settings-manage'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $input=['academic_year_id'=>1,'campus_id'=>1,'version'=>0,'compiler_name'=>'Compiler BCH','director_name'=>'Director BCH'];
    expect($settings->save(g12Request($user,$input))->getData(true))->toMatchArray(['version'=>1,'compiler_name'=>'Compiler BCH','director_name'=>'Director BCH','can_manage'=>true]);
    expect(fn()=>$settings->save(g12Request($user,$input)))->toThrow(HttpException::class);
    expect(fn()=>$settings->save(g12Request($user,array_replace($input,['campus_id'=>2]))))->toThrow(HttpException::class);
    $settings->save(g12Request($admin,array_replace($input,['campus_id'=>2,'compiler_name'=>'Compiler BKK','director_name'=>'Director BKK'])));
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027']);
    $settings->save(g12Request($user,array_replace($input,['academic_year_id'=>2,'director_name'=>'Next Year Director'])));
    expect(DB::table('tb_g9_eoy_campus_setting')->count())->toBe(3)->and(DB::table('tb_g9_exam_setting_event')->where('action','eoy-campus-names')->count())->toBe(3);
    expect($settings->show(g12Request($user,['academic_year_id'=>1,'campus_id'=>1]))->getData(true)['director_name'])->toBe('Director BCH');
    DB::table('access_user_permission_overrides')->where('user_id',2)->where('permission_id',DB::table('access_permissions')->where('code','g9-national-exams.eoy-settings-manage')->value('id'))->update(['allowed'=>false]);
    expect(fn()=>$settings->save(g12Request($user,array_replace($input,['version'=>1]))))->toThrow(HttpException::class);
});

it('reuses campus name defaults across existing and future academic years while preserving year-specific names',function(){
    $admin=User::find(1);$settings=app(\App\Http\Controllers\G9EoySettingController::class);$reports=app(\App\Support\G9EoyReport::class);
    $input=['academic_year_id'=>1,'campus_id'=>1,'version'=>0,'compiler_name'=>'Historical Compiler','director_name'=>'Historical Principal'];
    $settings->save(g12Request($admin,$input));
    $suggestion=$settings->show(g12Request($admin,['academic_year_id'=>1,'campus_id'=>1,'scope'=>'all']))->getData(true);
    expect($suggestion)->toMatchArray(['version'=>0,'source'=>'year-suggestion','director_name'=>'Historical Principal'])
        ->and(DB::table('tb_g9_eoy_campus_default')->count())->toBe(0);
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027']);
    foreach([1,2] as $year)$this->controller->save(g12Request($admin,array_replace($this->input,['academic_year_id'=>$year,'student_code'=>'YEAR-'.$year,'room_number'=>(string)$year])));
    $before=G9ExamCandidate::all()->toJson();$students=DB::table('tb_student')->get()->toJson();
    $shared=array_replace($input,['scope'=>'all','compiler_name'=>'Shared Compiler','director_name'=>'Shared Principal']);
    expect($settings->save(g12Request($admin,$shared))->getData(true))->toMatchArray(['version'=>1,'scope'=>'all','source'=>'all','director_name'=>'Shared Principal']);
    expect($settings->show(g12Request($admin,['academic_year_id'=>2,'campus_id'=>1]))->getData(true))
        ->toMatchArray(['version'=>0,'scope'=>'year','source'=>'all','director_name'=>'Shared Principal']);
    foreach(['card','cover'] as $type){
        $pages=$reports->pages(G9ExamCandidate::all(),[],$type);
        expect($pages[0]['director'])->toBe('Historical Principal')->and($pages[1]['director'])->toBe('Shared Principal');
    }
    $settings->save(g12Request($admin,array_replace($input,['academic_year_id'=>2,'compiler_name'=>'','director_name'=>'Year Two Principal'])));
    $settings->save(g12Request($admin,array_replace($shared,['version'=>1,'director_name'=>'Updated Shared Principal'])));
    DB::table('tb_academic_year')->insert(['id'=>3,'academic_year'=>'2027-2028']);
    expect($settings->show(g12Request($admin,['academic_year_id'=>3,'campus_id'=>1]))->getData(true))
        ->toMatchArray(['version'=>0,'source'=>'all','director_name'=>'Updated Shared Principal']);
    $pages=$reports->pages(G9ExamCandidate::all(),[],'card');
    expect($pages[0]['director'])->toBe('Historical Principal')->and($pages[1]['director'])->toBe('Year Two Principal')->and($pages[1]['compiler'])->toBe('')
        ->and(G9ExamCandidate::all()->toJson())->toBe($before)->and(DB::table('tb_student')->get()->toJson())->toBe($students)
        ->and(DB::table('tb_g9_eoy_campus_setting')->count())->toBe(2)->and(DB::table('tb_g9_eoy_campus_default')->count())->toBe(1);
    $event=DB::table('tb_g9_exam_setting_event')->where('action','eoy-campus-names-default')->orderByDesc('id')->first();$audit=json_decode($event->details,true);
    expect($audit['scope'])->toBe('all')->and($audit['before']['director_name'])->toBe('Shared Principal')->and($audit['after']['director_name'])->toBe('Updated Shared Principal');
});

it('protects all-year campus names with campus permissions scope validation and independent conflict checks',function(){
    $user=User::find(2);$settings=app(\App\Http\Controllers\G9EoySettingController::class);
    foreach(['view','settings','eoy-settings-manage'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $input=['academic_year_id'=>1,'campus_id'=>1,'scope'=>'all','version'=>0,'compiler_name'=>'Campus Compiler','director_name'=>'Campus Principal'];
    $settings->save(g12Request($user,$input));
    expect(fn()=>$settings->save(g12Request($user,$input)))->toThrow(HttpException::class);
    expect(fn()=>$settings->save(g12Request($user,array_replace($input,['campus_id'=>2]))))->toThrow(HttpException::class);
    expect(fn()=>$settings->show(g12Request($user,['academic_year_id'=>1,'campus_id'=>2,'scope'=>'all'])))->toThrow(HttpException::class);
    expect(fn()=>$settings->save(g12Request($user,array_replace($input,['scope'=>'invalid']))))->toThrow(ValidationException::class);
    expect(DB::table('tb_g9_eoy_campus_default')->count())->toBe(1)->and(DB::table('tb_g9_eoy_campus_setting')->count())->toBe(0);
    DB::table('access_user_permission_overrides')->where('user_id',2)->where('permission_id',DB::table('access_permissions')->where('code','g9-national-exams.eoy-settings-manage')->value('id'))->update(['allowed'=>false]);
    expect(fn()=>$settings->save(g12Request($user,array_replace($input,['version'=>1,'director_name'=>'Forbidden']))))->toThrow(HttpException::class);
    expect($settings->show(g12Request($user,['academic_year_id'=>1,'campus_id'=>1,'scope'=>'all']))->getData(true))
        ->toMatchArray(['version'=>1,'director_name'=>'Campus Principal','can_manage'=>false]);
});

it('lists saved campus names for all-year defaults and the selected year within view and edit permissions',function(){
    $admin=User::find(1);$user=User::find(2);$settings=app(\App\Http\Controllers\G9EoySettingController::class);
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027']);
    $base=['academic_year_id'=>1,'campus_id'=>1,'version'=>0,'compiler_name'=>'Compiler','director_name'=>'All-year Principal','scope'=>'all'];
    $settings->save(g12Request($admin,$base));
    $settings->save(g12Request($admin,array_replace($base,['scope'=>'year','director_name'=>'First Year Principal'])));
    $settings->save(g12Request($admin,array_replace($base,['scope'=>'year','academic_year_id'=>2,'director_name'=>'Second Year Principal'])));
    $settings->save(g12Request($admin,array_replace($base,['campus_id'=>2,'director_name'=>'Other Campus Principal'])));
    $before=DB::table('tb_g9_exam_setting_event')->count();
    $all=$settings->listing(g12Request($admin,['academic_year_id'=>1]))->getData(true);
    expect($all['rows'])->toHaveCount(3)->and($all['manageable_campus_ids'])->toBe([1,2])->and($all['can_create'])->toBeTrue()
        ->and(array_column($all['rows'],'scope'))->toBe(['all','year','all'])
        ->and(array_column($all['rows'],'director_name'))->toBe(['All-year Principal','First Year Principal','Other Campus Principal']);
    $otherYear=$settings->listing(g12Request($admin,['academic_year_id'=>2]))->getData(true);
    expect($otherYear['academic_year'])->toBe('2026-2027')->and($otherYear['rows'][1]['director_name'])->toBe('Second Year Principal');
    foreach(['view','settings'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $readonly=$settings->listing(g12Request($user,['academic_year_id'=>1]))->getData(true);
    expect($readonly['rows'])->toHaveCount(2)->and(array_column($readonly['rows'],'campus_id'))->toBe([1,1])
        ->and(array_column($readonly['rows'],'can_manage'))->toBe([false,false])->and($readonly['can_create'])->toBeFalse()->and($readonly['manageable_campus_ids'])->toBe([]);
    DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.eoy-settings-manage')->value('id'),'allowed'=>true]);
    $managed=$settings->listing(g12Request($user,['academic_year_id'=>1]))->getData(true);
    expect($managed['can_create'])->toBeTrue()->and($managed['manageable_campus_ids'])->toBe([1])->and(array_column($managed['rows'],'can_manage'))->toBe([true,true])
        ->and(DB::table('tb_g9_exam_setting_event')->count())->toBe($before);
    expect(fn()=>$settings->listing(g12Request($admin,['academic_year_id'=>999])))->toThrow(ValidationException::class);
    DB::table('access_user_permission_overrides')->where('user_id',2)->where('permission_id',DB::table('access_permissions')->where('code','g9-national-exams.settings')->value('id'))->update(['allowed'=>false]);
    expect(fn()=>$settings->listing(g12Request($user,['academic_year_id'=>1])))->toThrow(HttpException::class);
});

it('removes exactly one campus name configuration with an audit record and restores shared fallback',function(){
    $admin=User::find(1);$settings=app(\App\Http\Controllers\G9EoySettingController::class);
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027']);
    $base=['academic_year_id'=>1,'campus_id'=>1,'version'=>0,'compiler_name'=>'Compiler','director_name'=>'Default Principal','scope'=>'all'];
    $settings->save(g12Request($admin,$base));
    $settings->save(g12Request($admin,array_replace($base,['scope'=>'year','director_name'=>'Extra Principal'])));
    $settings->save(g12Request($admin,array_replace($base,['scope'=>'year','academic_year_id'=>2,'director_name'=>'Next Year Principal'])));
    $settings->save(g12Request($admin,array_replace($base,['campus_id'=>2,'director_name'=>'Other Campus Principal'])));
    $this->controller->save(g12Request($admin,$this->input));$records=G9ExamCandidate::all()->toJson();$students=DB::table('tb_student')->get()->toJson();
    $row=collect($settings->listing(g12Request($admin,['academic_year_id'=>1]))->getData(true)['rows'])->firstWhere('scope','year');
    $input=['academic_year_id'=>1,'campus_id'=>$row['campus_id'],'scope'=>$row['scope'],'id'=>$row['id'],'version'=>$row['version']];
    $settings->destroy(g12Request($admin,$input));
    expect(DB::table('tb_g9_eoy_campus_setting')->where('academic_year_id',1)->count())->toBe(0)
        ->and(DB::table('tb_g9_eoy_campus_setting')->where('academic_year_id',2)->value('director_name'))->toBe('Next Year Principal')
        ->and(DB::table('tb_g9_eoy_campus_default')->count())->toBe(2)
        ->and($settings->show(g12Request($admin,['academic_year_id'=>1,'campus_id'=>1]))->getData(true))->toMatchArray(['source'=>'all','director_name'=>'Default Principal']);
    $pages=app(\App\Support\G9EoyReport::class)->pages(G9ExamCandidate::all(),[],'card');expect($pages[0]['director'])->toBe('Default Principal');
    $audit=json_decode(DB::table('tb_g9_exam_setting_event')->where('action','remove-eoy-campus-names')->value('details'),true);
    expect($audit)->toMatchArray(['campus_id'=>1,'scope'=>'year','after'=>null])->and($audit['before']['director_name'])->toBe('Extra Principal');
    expect(fn()=>$settings->destroy(g12Request($admin,$input)))->toThrow(HttpException::class);
    $shared=DB::table('tb_g9_eoy_campus_default')->where('campus_id',1)->first();
    $settings->destroy(g12Request($admin,array_replace($input,['scope'=>'all','id'=>$shared->id,'version'=>$shared->version])));
    expect(DB::table('tb_g9_eoy_campus_default')->pluck('campus_id')->all())->toBe([2])
        ->and(DB::table('tb_g9_eoy_campus_setting')->count())->toBe(1)
        ->and($settings->show(g12Request($admin,['academic_year_id'=>1,'campus_id'=>1]))->getData(true)['source'])->toBe('none')
        ->and(G9ExamCandidate::all()->toJson())->toBe($records)->and(DB::table('tb_student')->get()->toJson())->toBe($students);
});

it('rejects campus name removal without permission or with changed or recreated records',function(){
    $admin=User::find(1);$user=User::find(2);$settings=app(\App\Http\Controllers\G9EoySettingController::class);
    $base=['academic_year_id'=>1,'campus_id'=>1,'scope'=>'all','version'=>0,'compiler_name'=>'Compiler','director_name'=>'Principal'];
    $settings->save(g12Request($admin,$base));$settings->save(g12Request($admin,array_replace($base,['campus_id'=>2])));
    $row=DB::table('tb_g9_eoy_campus_default')->where('campus_id',1)->first();
    $input=['academic_year_id'=>1,'campus_id'=>1,'scope'=>'all','id'=>$row->id,'version'=>$row->version];
    foreach(['view','settings'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    expect(fn()=>$settings->destroy(g12Request($user,$input)))->toThrow(HttpException::class);
    DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.eoy-settings-manage')->value('id'),'allowed'=>true]);
    expect(fn()=>$settings->destroy(g12Request($user,array_replace($input,['campus_id'=>2]))))->toThrow(HttpException::class);
    expect(fn()=>$settings->destroy(g12Request($admin,array_diff_key($input,['scope'=>true]))))->toThrow(ValidationException::class);
    $settings->save(g12Request($admin,array_replace($base,['version'=>1,'director_name'=>'Changed Principal'])));
    expect(fn()=>$settings->destroy(g12Request($user,$input)))->toThrow(HttpException::class);
    expect(DB::table('tb_g9_eoy_campus_default')->where('campus_id',1)->value('director_name'))->toBe('Changed Principal');
    $settings->destroy(g12Request($user,array_replace($input,['version'=>2])));
    $settings->save(g12Request($admin,$base));
    expect(fn()=>$settings->destroy(g12Request($user,$input)))->toThrow(HttpException::class);
    expect(DB::table('tb_g9_eoy_campus_default')->count())->toBe(2)
        ->and(DB::table('tb_g9_exam_setting_event')->where('action','remove-eoy-campus-names')->count())->toBe(1);
});

it('prints annual score cards and covers using campus names full filters and whole EOY totals',function(){
    $admin=User::find(1);$controller=app(\App\Http\Controllers\G9EoyAverageController::class);$reports=app(\App\Support\G9EoyReport::class);
    $this->controller->save(g12Request($admin,array_replace($this->input,['name_kh'=>'កង សុគន្ធនរិន្ទ','semester_1'=>'45.29','semester_2'=>'45.27','eoy_average'=>'91','exam_center'=>'វិ. សាលាវេស្ទើនអន្តរជាតិ (ភ្នំពេញថ្មី) (ត្បូង)','school_name'=>'វិ. សាលាវេស្ទើនអន្តរជាតិ (ភ្នំពេញថ្មី)','room_number'=>'1','seat_number'=>'10'])));
    $this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'SECOND','campus_id'=>2,'room_number'=>'2'])));
    foreach([1,2] as $campus)app(\App\Http\Controllers\G9EoySettingController::class)->save(g12Request($admin,['academic_year_id'=>1,'campus_id'=>$campus,'version'=>0,'compiler_name'=>$campus===1?'សុខ សាន្ត':'Remote Compiler','director_name'=>$campus===1?'លឹម សេងមាន':'Remote Director']));
    $before=G9ExamCandidate::all()->toJson();
    $settings=['resultDate_iso'=>'2026-07-06','date_location'=>'រាជធានីភ្នំពេញ','k_resultDate'=>'ថ្ងៃចន្ទ ៧កើត ខែអាសាឍ ឆ្នាំមមី អដ្ឋស័ក ពុទ្ធសករាជ ២៥៧០'];
    DB::table('tb_g9_exam_year_setting')->insert(['academic_year_id'=>1,'data'=>json_encode($settings),'version'=>1,'updated_by'=>1,'created_at'=>now(),'updated_at'=>now()]);
    $pages=$reports->pages(G9ExamCandidate::all(),$settings,'card');
    expect($pages)->toHaveCount(2)->and($pages[0])->toMatchArray(['semester_1'=>'45.29','semester_2'=>'45.27','sum'=>'90.56','eoy'=>'91','compiler'=>'សុខ សាន្ត','director'=>'លឹម សេងមាន'])
        ->and($pages[1]['director'])->toBe('Remote Director');
    foreach(['cover','card'] as $type){
        $request=g12Request($admin,['academic_year'=>1,'campus'=>1,'grade'=>'9A','per_page'=>'15']);
        $html=$controller->report($request,$type,'print',$reports)->getContent();
        expect($html)->toContain(\App\Support\G9EoyReport::TITLES[$type],'ទំព័រទី១','ទំព័រទី២')->not->toContain('Remote Director');
        $pdf=$controller->report(g12Request($admin,['academic_year'=>1,'campus'=>1]),$type,'pdf',$reports)->getContent();
        if(getenv('G9_REPORT_QA')){
            $folder=storage_path('app/private/g9-report-qa');if(!is_dir($folder))mkdir($folder,0755,true);
            file_put_contents($folder.'/eoy-'.$type.'.pdf',$pdf);file_put_contents($folder.'/eoy-'.$type.'.html',$html);
        }
        expect($pdf)->toStartWith('%PDF-')->and(preg_match_all('~/Type\s*/Page\b~',$pdf))->toBe(1);
    }
    expect($reports->pages(G9ExamCandidate::all(),$settings,'cover'))->toHaveCount(2);
    foreach(['cover','card'] as $type){
        $cards=$reports->pages(G9ExamCandidate::all(),$settings,$type);
        $two=$reports->pdf($cards,$type);$three=$reports->pdf([$cards[0],$cards[1],$cards[0]],$type);
        expect(preg_match_all('~/Type\s*/Page\b~',$two))->toBe(1)->and(preg_match_all('~/Type\s*/Page\b~',$three))->toBe(2);
        $reader=new \Mpdf\Mpdf(['tempDir'=>storage_path('app/private/g9-report-pdf')]);
        $reader->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($two));
        $size=$reader->getTemplateSize($reader->importPage(1));
        expect(round($size['width']))->toBe(210.0)->and(round($size['height']))->toBe(297.0);
        $html=view('g9-national-exams.eoy-report',['pages'=>$cards,'type'=>$type])->render();
        expect(substr_count($html,'<section class="eoy-print-sheet">'))->toBe(1)->and(substr_count($html,'<div class="eoy-print-half">'))->toBe(2);
        if(getenv('G9_REPORT_QA')){
            file_put_contents(storage_path('app/private/g9-report-qa/eoy-'.$type.'.pdf'),$two);
            file_put_contents(storage_path('app/private/g9-report-qa/eoy-'.$type.'.html'),$html);
            file_put_contents(storage_path('app/private/g9-report-qa/eoy-'.$type.'-source.pdf'),(new ReflectionMethod($reports,'sourcePdf'))->invoke($reports,$cards,$type));
        }
    }
    expect(G9ExamCandidate::all()->toJson())->toBe($before);
    expect(fn()=>$controller->report(g12Request($admin,['academic_year'=>1,'student'=>999]),'card','print',$reports))->toThrow(HttpException::class);
});

it('requires annual score report type and format permissions and keeps campus reports scoped',function(){
    $admin=User::find(1);$user=User::find(2);$controller=app(\App\Http\Controllers\G9EoyAverageController::class);$reports=app(\App\Support\G9EoyReport::class);
    $this->controller->save(g12Request($admin,$this->input));$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'OTHER','campus_id'=>2,'exam_center'=>'Remote Private Center'])));
    foreach(['view','eoy','eoy-print','eoy-report-card'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $html=$controller->report(g12Request($user,['academic_year'=>1]),'card','print',$reports)->getContent();
    expect($html)->toContain('89.50','90.50')->not->toContain('Remote Private Center');
    expect(fn()=>$controller->report(g12Request($user,['academic_year'=>1]),'cover','print',$reports))->toThrow(HttpException::class);
    expect(fn()=>$controller->report(g12Request($user,['academic_year'=>1]),'card','pdf',$reports))->toThrow(HttpException::class);
    expect(fn()=>$controller->report(g12Request($user,['academic_year'=>1,'campus'=>2]),'card','print',$reports))->toThrow(HttpException::class);
});

it('saves all G9 candidate fields independently of student records with audit history',function(){
    $admin=User::find(1);$before=DB::table('tb_student')->first();
    $id=$this->controller->save(g12Request($admin,$this->input))->getData(true)['id'];
    $record=G9ExamCandidate::find($id);
    foreach($this->input as $key=>$value)expect((string)($record->getAttributes()[$key]??$record->data[$key]))->toBe((string)$value);
    expect(DB::table('tb_student')->first())->toEqual($before)->and(DB::table('tb_g9_exam_event')->count())->toBe(1);
    expect(fn()=>$this->controller->save(g12Request($admin,$this->input)))->toThrow(ValidationException::class);
    $updated=$this->input+['version'=>1];$updated['father_name']='Updated Father';
    $this->controller->save(g12Request($admin,$updated),$id);
    expect(G9ExamCandidate::find($id)->version)->toBe(2)->and(G9ExamCandidate::find($id)->data['father_name'])->toBe('Updated Father');
    expect(fn()=>$this->controller->save(g12Request($admin,$updated),$id))->toThrow(HttpException::class);
    $invalid=$this->input;$invalid['student_code']='OTHER';$invalid['score_math']=-1;
    expect(fn()=>$this->controller->save(g12Request($admin,$invalid)))->toThrow(ValidationException::class);
});

it('filters G9 candidates and restricts viewing and editing to permitted campuses',function(){
    $admin=User::find(1);
    $id=$this->controller->save(g12Request($admin,$this->input))->getData(true)['id'];
    $this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'OTHER','campus_id'=>2,'exam_center'=>'Other Center'])));
    $user=User::find(2);
    expect(fn()=>$this->controller->listing(g12Request($user)))->toThrow(HttpException::class);
    $permissions=DB::table('access_permissions')->whereIn('code',['g9-national-exams.view','g9-national-exams.candidates','g9-national-exams.create'])->pluck('id');
    foreach($permissions as $permission)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>$permission,'allowed'=>true]);
    expect($this->controller->listing(g12Request($user))->getData(true)['total'])->toBe(2);
    expect($this->controller->options(g12Request($user,['academic_year'=>1]))->getData(true)['exam_center'])->toBe(['Other Center','Test Center']);
    expect($this->controller->listing(g12Request($user,['room'=>'01','seat'=>'005','student'=>$id]))->getData(true)['total'])->toBe(1);
    expect($this->controller->listing(g12Request($user,['search'=>'unmatched']))->getData(true)['total'])->toBe(0);
    expect(fn()=>$this->controller->save(g12Request($user,array_replace($this->input,['student_code'=>'NEW','campus_id'=>2]))))->toThrow(HttpException::class);
    expect(fn()=>$this->controller->save(g12Request($user,$this->input+['version'=>1]),$id))->toThrow(HttpException::class);
});

it('limits filter years to accessible G9 candidates and exam centers to the selected year',function(){
    $admin=User::find(1);$user=User::find(2);
    DB::table('tb_academic_year')->insert([['id'=>2,'academic_year'=>'2026-2027'],['id'=>3,'academic_year'=>'2027-2028']]);
    $this->controller->save(g12Request($admin,$this->input));
    $this->controller->save(g12Request($admin,array_replace($this->input,['academic_year_id'=>2,'campus_id'=>2,'exam_center'=>'Next Year Center'])));
    expect($this->controller->index(g12Request($admin))->getData()['filterYears']->pluck('id')->all())->toBe([2,1]);
    foreach(['view','candidates'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $view=$this->controller->index(g12Request($user))->getData();
    expect($view['filterYears']->pluck('id')->all())->toBe([2,1])->and($view['years'])->toHaveCount(3);
    expect($this->controller->options(g12Request($admin,['academic_year'=>1]))->getData(true)['exam_center'])->toBe(['Test Center'])
        ->and($this->controller->options(g12Request($admin,['academic_year'=>2]))->getData(true)['exam_center'])->toBe(['Next Year Center']);
});

it('summarizes filtered candidates across all pages by gender language and campus',function(){
    $admin=User::find(1);
    for($i=0;$i<16;$i++)$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'SUM'.$i,'gender'=>$i===0?'Male':'Female','exam_language'=>$i<10?'English':'Khmer','campus_id'=>$i<10?1:2])));
    $result=$this->controller->listing(g12Request($admin,['academic_year'=>1,'per_page'=>'15']))->getData(true);
    expect($result['data'])->toHaveCount(15)->and($result['summary'])->toMatchArray(['total'=>16,'male'=>1,'female'=>15])
        ->and(collect($result['summary']['languages'])->keyBy('label')['អង់គ្លេស'])->toMatchArray(['total'=>10,'female'=>9])
        ->and(collect($result['summary']['campuses'])->sum('total'))->toBe(16);
    $filtered=$this->controller->listing(g12Request($admin,['search'=>'SUM0']))->getData(true)['summary'];
    expect($filtered)->toMatchArray(['total'=>1,'male'=>1,'female'=>0])->and($filtered['campuses'])->toHaveCount(1);
});

it('sorts candidates by ascending numeric room number before pagination',function(){
    $admin=User::find(1);
    foreach(['10','2','1',''] as $index=>$room)$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'ORDER'.$index,'room_number'=>$room])));
    $result=$this->controller->listing(g12Request($admin,['academic_year'=>1]))->getData(true);
    expect(array_column($result['data'],'student_code'))->toBe(['ORDER2','ORDER1','ORDER0','ORDER3']);
});

it('returns dependent filter choices in one request without narrowing upstream options',function(){
    $admin=User::find(1);
    foreach([['A','1','1'],['A','2','2'],['B','3','3']] as $index=>[$center,$room,$seat])$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'CASCADE'.$index,'exam_center'=>$center,'room_number'=>$room,'seat_number'=>$seat])));
    $result=$this->controller->options(g12Request($admin,['academic_year'=>1,'cascade'=>1,'exam_center'=>'A','room'=>'2']))->getData(true);
    expect($result['exam_center'])->toBe(['A','B'])->and($result['room'])->toBe(['1','2'])->and($result['seat'])->toBe(['2'])
        ->and($result['student'])->toHaveCount(1);
    $stale=$this->controller->options(g12Request($admin,['academic_year'=>1,'cascade'=>1,'exam_center'=>'Missing','room'=>'Missing']))->getData(true);
    expect($stale['room'])->toBe(['1','2','3'])->and($stale['student'])->toHaveCount(3);
});

it('does not repeat permission queries for each displayed candidate and rechecks on the next request',function(){
    $admin=User::find(1);$user=User::find(2);
    foreach(['view','candidates','edit'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    foreach(range(1,20) as $i)$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'PERF'.$i])));
    $count=function($size)use($user){DB::flushQueryLog();DB::enableQueryLog();$response=$this->controller->listing(g12Request($user,['per_page'=>$size]));$queries=count(DB::getQueryLog());DB::disableQueryLog();return [$queries,$response->getData(true)];};
    [$small]=$count('15');[$large,$result]=$count('all');
    expect($large)->toBeLessThanOrEqual($small+1)->and($result['data'])->toHaveCount(20);
    DB::table('access_user_permission_overrides')->where('user_id',2)->delete();
    expect(fn()=>$this->controller->listing(g12Request(User::find(2))))->toThrow(HttpException::class);
});

it('filters campus choices after exam center and applies campus to lists summaries and reports',function(){
    $admin=User::find(1);$reports=app(\App\Support\G9CandidateReport::class);
    foreach([['A',1,'1'],['A',2,'2'],['B',2,'3']] as $index=>[$center,$campus,$room])$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'CAMPUS'.$index,'exam_center'=>$center,'campus_id'=>$campus,'room_number'=>$room,'school_name'=>'School-'.$campus])));
    $choices=$this->controller->options(g12Request($admin,['academic_year'=>1,'cascade'=>1,'exam_center'=>'A','campus'=>2]))->getData(true);
    expect(array_column($choices['campus'],'id'))->toBe([1,2])->and($choices['room'])->toBe(['2'])->and($choices['student'])->toHaveCount(1);
    $list=$this->controller->listing(g12Request($admin,['academic_year'=>1,'campus'=>1]))->getData(true);
    expect($list['total'])->toBe(1)->and($list['summary']['total'])->toBe(1)->and($list['data'][0]['student_code'])->toBe('CAMPUS0');
    $html=$this->controller->report(g12Request($admin,['academic_year'=>1,'campus'=>1]),'candidates','print',$reports)->getContent();
    expect(str_contains($html,'School-1'))->toBeTrue()->and(str_contains($html,'School-2'))->toBeFalse();
    foreach(['view','candidates'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $scoped=$this->controller->options(g12Request(User::find(2),['academic_year'=>1,'cascade'=>1,'exam_center'=>'A']))->getData(true);
    expect(array_column($scoped['campus'],'id'))->toBe([1,2]);
});

it('optimizes G9 photos and preserves existing photos on ordinary edits',function(){
    $admin=User::find(1);$request=g12Request($admin,$this->input);
    $request->files->set('photo',UploadedFile::fake()->image('student.jpg',600,800));
    $id=$this->controller->save($request)->getData(true)['id'];$path=G9ExamCandidate::find($id)->photo_path;
    Storage::disk('public')->assertExists($path);
    expect($path)->toStartWith('g9_national_exams/2025-2026/')->toEndWith('.webp')->and(strlen(Storage::disk('public')->get($path)))->toBeLessThan(40000);
    $this->controller->save(g12Request($admin,$this->input+['version'=>1]),$id);
    expect(G9ExamCandidate::find($id)->photo_path)->toBe($path);
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027']);
    $this->controller->save(g12Request($admin,array_replace($this->input,['academic_year_id'=>2,'version'=>2])),$id);
    $moved=G9ExamCandidate::find($id)->photo_path;
    expect($moved)->toStartWith('g9_national_exams/2026-2027/');
    Storage::disk('public')->assertExists($moved);
    expect(Storage::disk('public')->get($moved))->toBe(Storage::disk('public')->get($path));
});

function g9ImportCsv(array $rows): string {
    $stream=fopen('php://temp','w+');fwrite($stream,"\xEF\xBB\xBF");
    fputcsv($stream,array_keys(\App\Support\G9ExamSpreadsheet::COLUMNS),',','"','');
    foreach($rows as $row)fputcsv($stream,array_map(fn($field)=>$row[$field]??'',array_values(\App\Support\G9ExamSpreadsheet::COLUMNS)),',','"','');
    rewind($stream);$contents=stream_get_contents($stream);fclose($stream);return $contents;
}

function g9PhotoPreview(User $user,array $files,array $input=[]): array {
    $request=g12Request($user,array_replace(['academic_year_id'=>1],$input),'/g9-national-exams/bulk-photos/preview','POST');
    $request->files->set('files',$files);
    return app(\App\Http\Controllers\G9BulkPhotoController::class)->preview($request)->getData(true);
}
function g9PhotoConfirm(User $user,string $token) {
    return app(\App\Http\Controllers\G9BulkPhotoController::class)->confirm(g12Request($user,['token'=>$token]),app(\App\Services\G9ExamPhoto::class));
}

it('streams live ZIP photo optimization and matching without attaching photos before confirmation',function(){
    Storage::fake('local');$admin=User::find(1);$controller=app(\App\Http\Controllers\G9BulkPhotoController::class);
    foreach(['one','two'] as $name)$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>$name,'photo_filename'=>$name.'.jpg'])));
    $before=G9ExamCandidate::all()->toJson();$path=tempnam(sys_get_temp_dir(),'g9-photo-stream');$image=UploadedFile::fake()->image('source.jpg')->get();
    try{
        $zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);$zip->addFromString('folder/one.jpg',$image);$zip->addFromString('two.jpg',$image);$zip->addFromString('broken.jpg','invalid image');$zip->addFromString('notes.txt','ignore');$zip->close();
        $request=g12Request($admin,['academic_year_id'=>1],'/photos/preview','POST');$request->headers->set('Accept','application/json, application/x-ndjson');$request->files->set('files',[UploadedFile::fake()->createWithContent('photos.zip',file_get_contents($path))]);
        $response=$controller->preview($request);$observations=[];
        expect($response->headers->get('Content-Type'))->toContain('application/x-ndjson')->and($response->headers->get('X-Accel-Buffering'))->toBe('no');
        $events=g9ImportStreamEvents($response,function($event)use(&$observations){$observations[]=['type'=>$event['type'],'records'=>G9ExamCandidate::whereNotNull('photo_path')->count(),'batches'=>DB::table('tb_g9_photo_batch')->count()];});
    }finally{unlink($path);}
    $progress=collect($events)->where('type','progress');
    expect($progress->where('stage','optimizing')->pluck('processed')->all())->toBe([0,1,2,3])
        ->and($progress->where('stage','matching')->pluck('processed')->all())->toBe([1,2,3]);
    expect(array_column($events,'percent'))->toBe([0,0,16,33,50,66,83,99,100]);
    $result=end($events);expect($result['type'])->toBe('complete')->and($result['summary'])->toMatchArray(['images'=>3,'ready'=>2,'invalid'=>1])
        ->and($result['rows'][0])->not->toHaveKeys(['staged','old_path','version','key']);
    expect(array_unique(array_column($observations,'records')))->toBe([0])->and($observations[count($observations)-2]['batches'])->toBe(0)
        ->and(end($observations)['batches'])->toBe(1)->and(G9ExamCandidate::all()->toJson())->toBe($before)->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('streams saved photo counts and emits completion only after photo commit',function(){
    Storage::fake('local');$admin=User::find(1);$controller=app(\App\Http\Controllers\G9BulkPhotoController::class);
    foreach(['one','two'] as $name)$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>$name,'photo_filename'=>$name.'.jpg'])));
    $preview=g9PhotoPreview($admin,[UploadedFile::fake()->image('one.jpg'),UploadedFile::fake()->image('two.jpg')]);
    $request=g12Request($admin,['token'=>$preview['token']],'/photos/confirm','POST');$request->headers->set('Accept','application/json, application/x-ndjson');
    $response=$controller->confirm($request,app(\App\Services\G9ExamPhoto::class));$observations=[];
    $events=g9ImportStreamEvents($response,function($event)use(&$observations){$observations[]=['type'=>$event['type'],'level'=>DB::transactionLevel(),'status'=>DB::table('tb_g9_photo_batch')->value('status')];});
    expect(array_column($events,'type'))->toBe(['progress','progress','progress','complete'])->and(array_column($events,'percent'))->toBe([0,50,99,100])
        ->and(array_column(array_slice($events,0,3),'processed'))->toBe([0,1,2]);
    expect($observations[2])->toMatchArray(['level'=>1,'status'=>'preview'])->and($observations[3])->toMatchArray(['level'=>0,'status'=>'confirmed'])
        ->and(G9ExamCandidate::whereNotNull('photo_path')->count())->toBe(2)->and(Storage::disk('local')->allFiles('g9-photo-previews'))->toBe([]);
    foreach(G9ExamCandidate::all() as $record){expect($record->photo_path)->toStartWith('g9_national_exams/2025-2026/');Storage::disk('public')->assertExists($record->photo_path);}
    $repeat=g9ImportStreamEvents($controller->confirm($request,app(\App\Services\G9ExamPhoto::class)));
    expect($repeat)->toHaveCount(1)->and($repeat[0])->toMatchArray(['type'=>'error','status'=>409,'rolled_back'=>true]);
});

it('streams photo confirmation failures after cleaning up partial file writes and rolling back records',function(){
    Storage::fake('local');$admin=User::find(1);$controller=app(\App\Http\Controllers\G9BulkPhotoController::class);
    foreach(['one','two'] as $name)$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>$name,'photo_filename'=>$name.'.jpg'])));
    $preview=g9PhotoPreview($admin,[UploadedFile::fake()->image('one.jpg'),UploadedFile::fake()->image('two.jpg')]);G9ExamCandidate::where('student_code','two')->increment('version');
    $request=g12Request($admin,['token'=>$preview['token']],'/photos/confirm','POST');$request->headers->set('Accept','application/json, application/x-ndjson');
    $events=g9ImportStreamEvents($controller->confirm($request,app(\App\Services\G9ExamPhoto::class)));
    expect(array_column($events,'type'))->toBe(['progress','progress','error'])->and($events[1]['percent'])->toBe(50)
        ->and($events[2])->toMatchArray(['type'=>'error','status'=>409,'rolled_back'=>true])
        ->and(G9ExamCandidate::whereNotNull('photo_path')->count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(DB::table('tb_g9_exam_event')->where('action','bulk-photo')->count())->toBe(0)->and(DB::table('tb_g9_photo_batch')->value('status'))->toBe('preview');
});

it('streams invalid photo archive errors without creating a preview or bypassing permissions',function(){
    Storage::fake('local');$admin=User::find(1);$controller=app(\App\Http\Controllers\G9BulkPhotoController::class);
    $this->controller->save(g12Request($admin,$this->input+['photo_filename'=>'one.jpg']));
    $request=g12Request($admin,['academic_year_id'=>1],'/photos/preview','POST');$request->headers->set('Accept','application/json, application/x-ndjson');$request->files->set('files',[UploadedFile::fake()->createWithContent('invalid.zip','not a ZIP')]);
    $events=g9ImportStreamEvents($controller->preview($request));expect(array_column($events,'type'))->toBe(['progress','error'])
        ->and($events[1]['status'])->toBe(422)->and($events[1]['message'])->toContain('ZIP archive is invalid')
        ->and(DB::table('tb_g9_photo_batch')->count())->toBe(0)->and(Storage::disk('local')->allFiles('g9-photo-previews'))->toBe([]);
    $request=g12Request(User::find(2),['academic_year_id'=>1]);$request->headers->set('Accept','application/json, application/x-ndjson');
    expect(fn()=>$controller->preview($request))->toThrow(HttpException::class);
    expect(fn()=>$controller->confirm($request,app(\App\Services\G9ExamPhoto::class)))->toThrow(HttpException::class);
});

it('previews photo filename matches and confirms optimized academic-year photos once',function(){
    Storage::fake('local');$admin=User::find(1);
    $id=$this->controller->save(g12Request($admin,$this->input+['photo_filename'=>'MoEYS_001.jpg']))->getData(true)['id'];
    $preview=g9PhotoPreview($admin,[UploadedFile::fake()->image('MOEYS_001.png',600,800)]);
    expect($preview['summary']['ready'])->toBe(1)->and(G9ExamCandidate::find($id)->photo_path)->toBeNull();
    expect($preview['rows'][0])->not->toHaveKeys(['staged','old_path','version','key']);
    g9PhotoConfirm($admin,$preview['token']);$record=G9ExamCandidate::find($id);
    expect($record->photo_path)->toStartWith('g9_national_exams/2025-2026/')->toEndWith('.webp')
        ->and($record->data['photo_filename'])->toBe('MoEYS_001.jpg')->and($record->version)->toBe(2);
    Storage::disk('public')->assertExists($record->photo_path);
    expect(strlen(Storage::disk('public')->get($record->photo_path)))->toBeLessThan(40000)
        ->and(Storage::disk('local')->allFiles('g9-photo-previews'))->toBe([])
        ->and(DB::table('tb_g9_exam_event')->where('action','bulk-photo')->count())->toBe(1);
    expect(fn()=>g9PhotoConfirm($admin,$preview['token']))->toThrow(HttpException::class);
});

it('matches ZIP subfolders safely and skips duplicate ambiguous invalid and unmatched photos',function(){
    Storage::fake('local');$admin=User::find(1);
    foreach(['good.jpg','duplicate.jpg','shared.jpg','shared.jpg','missing.jpg'] as $i=>$name){
        $this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'ZIP'.$i,'photo_filename'=>$name])));
    }
    $image=UploadedFile::fake()->image('source.jpg')->get();$path=tempnam(sys_get_temp_dir(),'g9zip');
    try{
        $zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);
        foreach(['../../good.jpg','one/duplicate.jpg','two/duplicate.png','shared.jpg','unknown.jpg'] as $name)$zip->addFromString($name,$image);
        $zip->addFromString('broken.jpg','invalid image');$zip->addFromString('ignored.txt','not a photo');$zip->close();
        $preview=g9PhotoPreview($admin,[UploadedFile::fake()->createWithContent('photos.zip',file_get_contents($path))]);
    }finally{unlink($path);}
    expect($preview['summary'])->toMatchArray(['images'=>6,'ready'=>1,'duplicates'=>3,'invalid'=>1,'unmatched'=>1,'missing'=>4]);
    g9PhotoConfirm($admin,$preview['token']);
    expect(G9ExamCandidate::whereNotNull('photo_path')->count())->toBe(1)
        ->and(count(Storage::disk('public')->allFiles()))->toBe(1);
});

it('preserves existing photos unless replacement is explicitly permitted',function(){
    Storage::fake('local');$admin=User::find(1);
    $request=g12Request($admin,$this->input+['photo_filename'=>'old.jpg']);$request->files->set('photo',UploadedFile::fake()->image('old.jpg'));
    $id=$this->controller->save($request)->getData(true)['id'];$old=G9ExamCandidate::find($id)->photo_path;
    $preview=g9PhotoPreview($admin,[UploadedFile::fake()->image('old.jpg')]);
    expect($preview['summary']['existing'])->toBe(1)->and($preview['summary']['ready'])->toBe(0);
    $user=User::find(2);
    foreach(['view','photos','photos-upload'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    expect(fn()=>g9PhotoPreview($user,[UploadedFile::fake()->image('old.jpg')],['replace_existing'=>1]))->toThrow(HttpException::class);
    $preview=g9PhotoPreview($admin,[UploadedFile::fake()->image('old.jpg')],['replace_existing'=>1]);g9PhotoConfirm($admin,$preview['token']);
    expect(G9ExamCandidate::find($id)->photo_path)->not->toBe($old);Storage::disk('public')->assertExists($old);
});

it('scopes photo matches by campus and year and binds confirmations to the uploading user',function(){
    Storage::fake('local');$admin=User::find(1);$user=User::find(2);
    foreach(['view','photos','photos-upload'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $this->controller->save(g12Request($admin,$this->input+['photo_filename'=>'own.jpg']));
    $this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'OTHER','campus_id'=>2,'photo_filename'=>'other.jpg'])));
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027']);
    $this->controller->save(g12Request($admin,array_replace($this->input,['academic_year_id'=>2,'photo_filename'=>'year.jpg'])));
    $preview=g9PhotoPreview($user,[UploadedFile::fake()->image('own.jpg'),UploadedFile::fake()->image('other.jpg'),UploadedFile::fake()->image('year.jpg')]);
    expect($preview['summary']['ready'])->toBe(1)->and($preview['summary']['unmatched'])->toBe(2);
    expect(fn()=>g9PhotoConfirm($admin,$preview['token']))->toThrow(HttpException::class);
    DB::table('access_user_permission_overrides')->where('user_id',2)->delete();
    expect(fn()=>g9PhotoConfirm($user,$preview['token']))->toThrow(HttpException::class);
    expect(G9ExamCandidate::whereNotNull('photo_path')->count())->toBe(0);
});

it('rolls back photo writes when a candidate changes after preview and expires old previews',function(){
    Storage::fake('local');$admin=User::find(1);
    foreach(['one','two'] as $name)$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>$name,'photo_filename'=>$name.'.jpg'])));
    $preview=g9PhotoPreview($admin,[UploadedFile::fake()->image('one.jpg'),UploadedFile::fake()->image('two.jpg')]);
    G9ExamCandidate::where('student_code','two')->increment('version');
    expect(fn()=>g9PhotoConfirm($admin,$preview['token']))->toThrow(HttpException::class);
    expect(G9ExamCandidate::whereNotNull('photo_path')->count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe([]);
    DB::table('tb_g9_photo_batch')->update(['expires_at'=>now()->subMinute()]);
    expect(fn()=>g9PhotoConfirm($admin,$preview['token']))->toThrow(HttpException::class);
    $oldFolder=DB::table('tb_g9_photo_batch')->value('folder');
    g9PhotoPreview($admin,[UploadedFile::fake()->image('one.jpg')]);
    expect(Storage::disk('local')->allFiles($oldFolder))->toBe([])->and(DB::table('tb_g9_photo_batch')->where('folder',$oldFolder)->value('status'))->toBe('expired');
});

function g9ImportStreamEvents($response,?callable $observe=null): array {
    $output='';
    ob_start(function($chunk)use(&$output,$observe){$output.=$chunk;if($observe)foreach(explode("\n",trim($chunk)) as $line)if($line!=='')$observe(json_decode($line,true,512,JSON_THROW_ON_ERROR));return '';});
    try{($response->getCallback())();}finally{ob_end_clean();}
    return array_map(fn($line)=>json_decode($line,true,512,JSON_THROW_ON_ERROR),array_values(array_filter(explode("\n",$output))));
}

it('streams live preview validation progress for valid and invalid rows without changing candidates',function(){
    $admin=User::find(1);$service=app(\App\Services\G9ExamImports::class);$reader=app(\App\Support\G9ExamSpreadsheet::class);$controller=app(\App\Http\Controllers\G9ExamImportController::class);
    $base=array_replace($this->input,['academic_year_id'=>'2025-2026','campus_id'=>'BCH','semester_1'=>null,'semester_2'=>null,'eoy_average'=>'91']);
    $request=g12Request($admin,[],'/preview','POST');$request->headers->set('Accept','application/json, application/x-ndjson');
    $request->files->set('file',UploadedFile::fake()->createWithContent('progress.csv',g9ImportCsv([$base,array_replace($base,['student_code'=>'BAD','campus_id'=>'Unknown campus'])])));
    $response=$controller->preview($request,$service,$reader);$observations=[];
    expect($response->headers->get('Content-Type'))->toContain('application/x-ndjson')->and($response->headers->get('X-Accel-Buffering'))->toBe('no');
    expect(DB::table('tb_g9_exam_import')->count())->toBe(0);
    $events=g9ImportStreamEvents($response,function($event)use(&$observations){$observations[]=['type'=>$event['type'],'records'=>G9ExamCandidate::count(),'batches'=>DB::table('tb_g9_exam_import')->count()];});
    expect(array_column($events,'type'))->toBe(['progress','progress','progress','progress','progress','complete'])
        ->and(array_column($events,'percent'))->toBe([0,0,50,99,99,100])
        ->and(array_column(array_slice($events,0,5),'stage'))->toBe(['reading','validating','validating','validating','finalizing']);
    expect($events[3])->toMatchArray(['processed'=>2,'total'=>2])->and($events[5]['summary'])->toMatchArray(['total'=>2,'new'=>1,'updates'=>0])
        ->and($events[5]['summary']['errors'])->toHaveCount(1)->and(strlen($events[5]['token']))->toBe(64);
    expect(array_unique(array_column($observations,'records')))->toBe([0])->and($observations[4]['batches'])->toBe(0)->and($observations[5]['batches'])->toBe(1);
    $payload=json_decode(DB::table('tb_g9_exam_import')->value('payload'),true);
    expect($payload[0]['input'])->toMatchArray(['semester_1'=>null,'semester_2'=>null,'eoy_average'=>'91']);
});

it('streams unreadable preview errors without creating a batch or touching candidate records',function(){
    $admin=User::find(1);$service=app(\App\Services\G9ExamImports::class);$reader=app(\App\Support\G9ExamSpreadsheet::class);$controller=app(\App\Http\Controllers\G9ExamImportController::class);
    $this->controller->save(g12Request($admin,$this->input));$before=G9ExamCandidate::all()->toJson();
    $request=g12Request($admin,[],'/preview','POST');$request->headers->set('Accept','application/json, application/x-ndjson');
    $request->files->set('file',UploadedFile::fake()->createWithContent('bad.csv',"academic_year,unknown_header\n2025-2026,invalid\n"));
    $events=g9ImportStreamEvents($controller->preview($request,$service,$reader));
    expect(array_column($events,'type'))->toBe(['progress','error'])->and($events[1]['status'])->toBe(422)->and($events[1]['message'])->toContain('Unknown column');
    expect(DB::table('tb_g9_exam_import')->count())->toBe(0)->and(G9ExamCandidate::all()->toJson())->toBe($before);
    $unauthorized=g12Request(User::find(2),[],'/preview','POST');$unauthorized->headers->set('Accept','application/json, application/x-ndjson');
    expect(fn()=>$controller->preview($unauthorized,$service,$reader))->toThrow(HttpException::class);
});

it('streams actual import row progress and reports completion only after committing',function(){
    $admin=User::find(1);$service=app(\App\Services\G9ExamImports::class);$reader=app(\App\Support\G9ExamSpreadsheet::class);$controller=app(\App\Http\Controllers\G9ExamImportController::class);
    $base=array_replace($this->input,['academic_year_id'=>'2025-2026','campus_id'=>'BCH']);
    $request=g12Request($admin,[],'/preview','POST');$request->files->set('file',UploadedFile::fake()->createWithContent('progress.csv',g9ImportCsv([$base,array_replace($base,['student_code'=>'SECOND'])])));
    $preview=$controller->preview($request,$service,$reader)->getData(true);
    $request=g12Request($admin,['token'=>$preview['token']],'/confirm','POST');$request->headers->set('Accept','application/json, application/x-ndjson');
    $response=$controller->confirm($request,$service,$this->controller);$observations=[];
    expect($response->headers->get('Content-Type'))->toContain('application/x-ndjson')->and($response->headers->get('X-Accel-Buffering'))->toBe('no');
    expect(G9ExamCandidate::count())->toBe(0);
    $events=g9ImportStreamEvents($response,function($event)use(&$observations){$observations[]=['type'=>$event['type'],'level'=>DB::transactionLevel(),'records'=>G9ExamCandidate::count(),'status'=>DB::table('tb_g9_exam_import')->value('status')];});
    expect(array_column($events,'type'))->toBe(['progress','progress','progress','complete'])
        ->and(array_column($events,'percent'))->toBe([0,50,99,100])
        ->and(array_column(array_slice($events,0,3),'processed'))->toBe([0,1,2]);
    expect($events[2]['finalizing'])->toBeTrue()->and($events[3]['summary'])->toMatchArray(['new'=>2,'updates'=>0]);
    expect($observations[2])->toMatchArray(['level'=>1,'records'=>2,'status'=>'preview'])
        ->and($observations[3])->toMatchArray(['level'=>0,'records'=>2,'status'=>'confirmed']);
    $again=$controller->confirm($request,$service,$this->controller);$repeated=g9ImportStreamEvents($again);
    expect($repeated)->toHaveCount(1)->and($repeated[0])->toMatchArray(['type'=>'error','status'=>409,'rolled_back'=>true])->and(G9ExamCandidate::count())->toBe(2);
});

it('streams a failed confirmation without completing and rolls back all preceding rows',function(){
    $admin=User::find(1);$service=app(\App\Services\G9ExamImports::class);$reader=app(\App\Support\G9ExamSpreadsheet::class);$controller=app(\App\Http\Controllers\G9ExamImportController::class);
    $id=$this->controller->save(g12Request($admin,$this->input))->getData(true)['id'];
    $base=array_replace($this->input,['academic_year_id'=>'2025-2026','campus_id'=>'BCH']);
    $request=g12Request($admin,[],'/preview','POST');$request->files->set('file',UploadedFile::fake()->createWithContent('rollback.csv',g9ImportCsv([array_replace($base,['student_code'=>'FIRST-NEW']),$base])));
    $preview=$controller->preview($request,$service,$reader)->getData(true);
    $this->controller->save(g12Request($admin,array_replace($this->input,['version'=>1,'father_name'=>'Updated after preview'])),$id);
    $request=g12Request($admin,['token'=>$preview['token']],'/confirm','POST');$request->headers->set('Accept','application/json, application/x-ndjson');
    $events=g9ImportStreamEvents($controller->confirm($request,$service,$this->controller));
    expect(array_column($events,'type'))->toBe(['progress','progress','error'])->and($events[1]['percent'])->toBe(50)
        ->and($events[2])->toMatchArray(['type'=>'error','status'=>409,'rolled_back'=>true])
        ->and($events[2]['message'])->toContain('Candidate data changed');
    expect(G9ExamCandidate::count())->toBe(1)->and(G9ExamCandidate::first()->data['father_name'])->toBe('Updated after preview')
        ->and(DB::table('tb_g9_exam_import')->value('status'))->toBe('preview')->and(DB::transactionLevel())->toBe(0);
});

it('previews imports without saving and confirms them once with CSV round trip',function(){
    $admin=User::find(1);$service=app(\App\Services\G9ExamImports::class);$reader=app(\App\Support\G9ExamSpreadsheet::class);
    $controller=app(\App\Http\Controllers\G9ExamImportController::class);
    $input=array_replace($this->input,['academic_year_id'=>'2025-2026','campus_id'=>'BCH','ethnicity'=>'Khmer','photo_filename'=>'000123.jpg','date_of_birth'=>'12/05/2011','exam_date'=>"\u{17E0}\u{17E6} \u{179F}\u{17B8}\u{17A0}\u{17B6} \u{17E2}\u{17E0}\u{17E2}\u{17E4}"]);
    $request=g12Request($admin,[],'/g9-national-exams/imports-exports/preview','POST');
    $request->files->set('file',UploadedFile::fake()->createWithContent('moeys.csv',g9ImportCsv([$input])));
    $preview=$controller->preview($request,$service,$reader)->getData(true);
    expect($preview['summary']['errors'])->toBe([])->and($preview['summary']['new'])->toBe(1)->and(G9ExamCandidate::count())->toBe(0);
    $confirm=g12Request($admin,['token'=>$preview['token']],'/g9-national-exams/imports-exports/confirm','POST');
    $controller->confirm($confirm,$service,$this->controller);
    $record=G9ExamCandidate::first();
    expect($record->student_code)->toBe('000123')->and($record->data['date_of_birth'])->toBe('2011-05-12')
        ->and($record->data['exam_date'])->toBe('2024-08-06')->and($record->data['photo_filename'])->toBe('000123.jpg')
        ->and($record->data['ethnicity'])->toBe('Khmer')->and(DB::table('tb_g9_exam_import')->value('status'))->toBe('confirmed');
    expect(fn()=>$controller->confirm($confirm,$service,$this->controller))->toThrow(HttpException::class);
    $export=$controller->download(g12Request($admin),$service,'all');ob_start();$export->sendContent();$csv=ob_get_clean();
    expect($csv)->toContain('academic_year,stu_id,full_name_khmer,full_name_English','000123','000123.jpg','42.500');
    $path=tempnam(sys_get_temp_dir(),'g9-roundtrip-');file_put_contents($path,$csv);
    try{$rows=$reader->read($path,'csv');$again=$service->preview($admin,$rows);expect($again['summary']['updates'])->toBe(1)->and($again['summary']['errors'])->toBe([]);}finally{unlink($path);}
});

it('validates all MoEYS sample columns and rejects duplicates unknown headers and invalid rows',function(){
    $reader=app(\App\Support\G9ExamSpreadsheet::class);$service=app(\App\Services\G9ExamImports::class);$admin=User::find(1);
    $rows=$reader->read(storage_path('app/imports/g9_national_exams.xlsx'),'xlsx');
    expect($rows)->toHaveCount(14)->and(count($rows[0])-1)->toBe(38)->and($rows[0])->toHaveKeys(['ethnicity','photo_filename','score_khmer_dictation']);
    $input=array_replace($this->input,['academic_year_id'=>'2025-2026','campus_id'=>'BCH','_row'=>2]);
    $result=$service->preview($admin,[$input,array_replace($input,['_row'=>3,'score_math'=>-1])]);
    expect($result['summary']['errors'])->toHaveCount(1)->and(implode(' ',$result['summary']['errors'][0]['messages']))->toContain('Duplicate student','score math');
    $unknown=$service->preview($admin,[array_replace($input,['academic_year_id'=>'2099-2100','campus_id'=>'Unknown'])]);
    expect($unknown['summary']['errors'])->toHaveCount(1);
    $path=tempnam(sys_get_temp_dir(),'g9-invalid-');file_put_contents($path,"academic_year,stu_id,wrong_header\n2025-2026,1,2\n");
    try{expect(fn()=>$reader->read($path,'csv'))->toThrow(ValidationException::class);}finally{unlink($path);}
});

it('blocks stale previews and rolls back the entire import if a later candidate changes',function(){
    $admin=User::find(1);$service=app(\App\Services\G9ExamImports::class);$reader=app(\App\Support\G9ExamSpreadsheet::class);$controller=app(\App\Http\Controllers\G9ExamImportController::class);
    $id=$this->controller->save(g12Request($admin,$this->input))->getData(true)['id'];
    $first=array_replace($this->input,['academic_year_id'=>'2025-2026','campus_id'=>'BCH','student_code'=>'NEW0001']);
    $second=array_replace($first,['student_code'=>$this->input['student_code']]);
    $request=g12Request($admin,[],'/preview','POST');$request->files->set('file',UploadedFile::fake()->createWithContent('moeys.csv',g9ImportCsv([$first,$second])));
    $preview=$controller->preview($request,$service,$reader)->getData(true);
    $this->controller->save(g12Request($admin,$this->input+['version'=>1]),$id);
    expect(fn()=>$controller->confirm(g12Request($admin,['token'=>$preview['token']],'/confirm','POST'),$service,$this->controller))->toThrow(HttpException::class);
    expect(G9ExamCandidate::count())->toBe(1)->and(DB::table('tb_g9_exam_import')->value('status'))->toBe('preview');
});

it('restricts import export and preview confirmation to authorized users and campuses',function(){
    $admin=User::find(1);$user=User::find(2);$service=app(\App\Services\G9ExamImports::class);$reader=app(\App\Support\G9ExamSpreadsheet::class);$controller=app(\App\Http\Controllers\G9ExamImportController::class);
    expect(fn()=>$controller->download(g12Request($user),$service,'all'))->toThrow(HttpException::class);
    foreach(['view','candidates','imports','import','export','create','edit'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    g12Request($user);
    $input=array_replace($this->input,['academic_year_id'=>'2025-2026','campus_id'=>'BKK','_row'=>2]);
    expect($service->preview($user,[$input])['summary']['errors'])->toHaveCount(1);
    $this->controller->save(g12Request($admin,$this->input));$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'OTHER-CAMPUS','campus_id'=>2])));
    $export=$controller->download(g12Request($user),$service,'all');ob_start();$export->sendContent();$csv=ob_get_clean();expect($csv)->toContain('000123')->not->toContain('OTHER-CAMPUS');
    $request=g12Request($admin,[],'/preview','POST');$request->files->set('file',UploadedFile::fake()->createWithContent('moeys.csv',g9ImportCsv([array_replace($input,['campus_id'=>'BCH'])])));
    $preview=$controller->preview($request,$service,$reader)->getData(true);
    expect(fn()=>$controller->confirm(g12Request($user,['token'=>$preview['token']],'/confirm','POST'),$service,$this->controller))->toThrow(HttpException::class);
});

it('migrates legacy campus aliases certificate tracking and shared dates without renaming MoEYS photos',function(){
    DB::table('tb_school_info')->insert([['id'=>3,'campus_name_en'=>'BTB3'],['id'=>4,'campus_name_en'=>'CKD1']]);
    $admin=User::find(1);$service=app(\App\Services\G9ExamImports::class);$reader=app(\App\Support\G9ExamSpreadsheet::class);$controller=app(\App\Http\Controllers\G9ExamImportController::class);
    $base=array_replace($this->input,['academic_year_id'=>'2025-2026','campus_id'=>'BTB','photo_filename'=>'MoEYS_Original_File.JPG',
        'legacy_national_id'=>'OLD00001','legacy_certificate_number'=>'G925-001','legacy_received_cert'=>'Withdrew',
        'legacy_received_cert_Date'=>'2026-10-27','legacy_received_by'=>'Parent','legacy_cert_name'=>'OriginalCertificate',
        'legacy_cert_received_name'=>'SignedCopy','legacy_listDate'=>'Shared candidate list date','legacy_k_listdate'=>'Shared lunar date',
        'legacy_certDate'=>'Shared certificate date','legacy_score_verify_by'=>'Original Signer','_row'=>2]);
    $second=array_replace($base,['student_code'=>'SECOND','campus_id'=>'CKD','legacy_national_id'=>'OLD00002','legacy_certificate_number'=>'G925-002','legacy_received_cert_Date'=>'','legacy_received_by'=>'','_row'=>3]);
    $preview=$service->preview($admin,[$base,$second]);
    expect($preview['summary']['errors'])->toBe([])->and($preview['summary']['warnings'])->toHaveCount(1)
        ->and($preview['payload'][0]['input']['campus_id'])->toBe(3)->and($preview['payload'][1]['input']['campus_id'])->toBe(4)
        ->and($preview['payload'][0]['input']['photo_filename'])->toBe('MoEYS_Original_File.JPG');
    $stream=fopen('php://temp','w+');fputcsv($stream,array_keys(\App\Support\G9ExamSpreadsheet::allColumns()),',','"','');
    foreach([$base,$second] as $row)fputcsv($stream,array_map(fn($field)=>$row[$field]??'',array_values(\App\Support\G9ExamSpreadsheet::allColumns())),',','"','');
    rewind($stream);$csv=stream_get_contents($stream);fclose($stream);
    $request=g12Request($admin,[],'/preview','POST');$request->files->set('file',UploadedFile::fake()->createWithContent('old.csv',$csv));
    $result=$controller->preview($request,$service,$reader)->getData(true);
    $controller->confirm(g12Request($admin,['token'=>$result['token']],'/confirm','POST'),$service,$this->controller);
    $records=G9ExamCandidate::orderBy('id')->get();
    expect($records[0]->legacy_data['certificate_number'])->toBe('G925-001')
        ->and($records[0]->legacy_data['national_id'])->toBe('OLD00001')
        ->and($records[1]->legacy_data['received_cert_Date'])->toBe('')
        ->and($records[0]->data['photo_filename'])->toBe('MoEYS_Original_File.JPG')
        ->and(DB::table('tb_g9_exam_year_setting')->count())->toBe(1);
    $settings=json_decode(DB::table('tb_g9_exam_year_setting')->value('data'),true);
    expect($settings['listDate'])->toBe('Shared candidate list date')->and($settings['score_verify_by'])->toBe('Original Signer');
    $export=$controller->download(g12Request($admin),$service,'all');ob_start();$export->sendContent();$exported=ob_get_clean();
    expect($exported)->toContain('G925-001','G925-002','MoEYS_Original_File.JPG','Shared candidate list date','Original Signer','SignedCopy');
    // A subsequent MoEYS-only import keeps the school's tracking and shared dates.
    $moeys=array_replace($this->input,['academic_year_id'=>'2025-2026','campus_id'=>'BTB3','_row'=>2]);
    $updated=$service->preview($admin,[$moeys]);
    expect($updated['payload'][0]['legacy']['certificate_number'])->toBe('G925-001')->and(isset($updated['payload'][0]['year_setting']))->toBeFalse();
});

it('flags legacy certificate duplicates and conflicting shared dates instead of overwriting them',function(){
    $service=app(\App\Services\G9ExamImports::class);$admin=User::find(1);
    $row=array_replace($this->input,['academic_year_id'=>'2025-2026','campus_id'=>'BCH','legacy_certificate_number'=>'SAME','legacy_listDate'=>'Date one','_row'=>2]);
    $other=array_replace($row,['student_code'=>'SECOND','legacy_listDate'=>'Date two','_row'=>3]);
    $result=$service->preview($admin,[$row,$other]);
    expect($result['summary']['errors'])->toHaveCount(1)
        ->and(implode(' ',$result['summary']['errors'][0]['messages']))->toContain('Duplicate certificate','Conflicting academic-year setting');
    $failed=$service->preview($admin,[array_replace($row,['legacy_received_cert'=>'Failed'])]);
    expect($failed['summary']['errors'])->toBe([])->and($failed['summary']['warnings'])->toHaveCount(1)
        ->and($failed['payload'][0]['legacy']['certificate_number'])->toBe('SAME');
});

it('keeps saved year settings and warns once without blocking candidate imports',function(){
    $saved=['cardDate'=>'Edited card date','k_cardDate'=>'Edited card lunar date','certDate'=>'','k_certDate'=>'Edited certificate lunar date','certDate_iso'=>'2026-11-04'];
    DB::table('tb_g9_exam_year_setting')->insert(['academic_year_id'=>1,'data'=>json_encode($saved),'version'=>3,'updated_by'=>1,'created_at'=>now(),'updated_at'=>now()]);
    $service=app(\App\Services\G9ExamImports::class);$admin=User::find(1);
    $row=array_replace($this->input,['academic_year_id'=>'2025-2026','campus_id'=>'BCH','legacy_cardDate'=>'Old card date','legacy_k_cardDate'=>'Old lunar date','legacy_certDate'=>'Old certificate date','legacy_k_certDate'=>'Old certificate lunar date','_row'=>2]);
    $second=array_replace($row,['student_code'=>'SECOND','_row'=>3]);
    $result=$service->preview($admin,[$row,$second]);
    expect($result['summary']['errors'])->toBe([])->and($result['summary']['new'])->toBe(2)
        ->and($result['summary']['warnings'])->toHaveCount(1)
        ->and($result['summary']['warnings'][0]['message'])->toContain('will be kept','cardDate','certDate')
        ->and(isset($result['payload'][0]['year_setting']))->toBeFalse()
        ->and(json_decode(DB::table('tb_g9_exam_year_setting')->value('data'),true))->toBe($saved);
    $row['legacy_score_verify_by']='New signer';
    $merged=$service->preview($admin,[$row]);
    expect($merged['summary']['errors'])->toBe([])
        ->and($merged['payload'][0]['year_setting']['data']['cardDate'])->toBe('Edited card date')
        ->and($merged['payload'][0]['year_setting']['data']['certDate'])->toBe('')
        ->and($merged['payload'][0]['year_setting']['data']['score_verify_by'])->toBe('New signer');
    $second['legacy_cardDate']='Another source date';
    expect($service->preview($admin,[$row,$second])['summary']['errors'])->toHaveCount(1);
});

it('saves center-specific dates province and signer while preserving other centers years and candidate records',function(){
    $admin=User::find(1);$settings=app(\App\Http\Controllers\G9ExamSettingController::class);
    $this->controller->save(g12Request($admin,$this->input));
    $this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'CENTER-B','exam_center'=>'Other Center'])));
    DB::table('tb_academic_year')->insert(['id'=>2,'academic_year'=>'2026-2027']);
    $yearSettings=['listDate'=>'Legacy list text','listDate_iso'=>'2026-07-01','k_listdate'=>'Year lunar',
        'cardDate'=>'Year card date','cardDate_iso'=>'2026-07-02','resultDate_iso'=>'2026-07-03','score_verify_by'=>'Keep original signer'];
    DB::table('tb_g9_exam_year_setting')->insert(['academic_year_id'=>1,'data'=>json_encode($yearSettings),'version'=>1,'updated_by'=>1,'created_at'=>now(),'updated_at'=>now()]);
    DB::table('tb_g9_exam_year_setting')->insert(['academic_year_id'=>2,'data'=>json_encode(['cardDate'=>'Next year date']),'version'=>4,'updated_by'=>1,'created_at'=>now(),'updated_at'=>now()]);
    $before=G9ExamCandidate::all()->toJson();$students=DB::table('tb_student')->get()->toJson();
    $loaded=$settings->showCenter(g12Request($admin,['academic_year_id'=>1,'exam_center'=>'Test Center']))->getData(true);
    expect($loaded['date_overrides'])->toBe([])->and($loaded['dates']['cardDate']['date'])->toBe('2026-07-02');
    $dates=collect($loaded['dates'])->map(fn($date)=>$date+['inherit'=>true])->all();
    $dates['listDate']=['inherit'=>false,'date'=>'2026-08-01','solar'=>'Center list date','lunar'=>'Center list lunar'];
    $dates['cardDate']=['inherit'=>false,'date'=>'2026-08-02','solar'=>'Center card date','lunar'=>'Center card lunar'];
    $dates['resultDate']=['inherit'=>false,'date'=>'2026-08-03','solar'=>'Center result date','lunar'=>'Center result lunar'];
    $input=['academic_year_id'=>1,'exam_center'=>'Test Center','version'=>1,'location'=>'ខេត្តព្រះសីហនុ','department'=>'Center Department','signer'=>'ប្រធានមណ្ឌល','signer_name'=>'Center Signer','dates'=>$dates];
    $saved=$settings->saveCenter(g12Request($admin,$input))->getData(true);
    expect($saved)->toMatchArray(['version'=>2,'location'=>'ខេត្តព្រះសីហនុ','signer_name'=>'Center Signer'])->and($saved['dates']['cardDate']['date'])->toBe('2026-08-02')
        ->and($saved['date_overrides'])->toBe(['listDate','resultDate','cardDate']);
    $other=$settings->showCenter(g12Request($admin,['academic_year_id'=>1,'exam_center'=>'Other Center']))->getData(true);
    expect($other['date_overrides'])->toBe([])->and($other['dates']['cardDate']['date'])->toBe('2026-07-02');
    $stored=json_decode(DB::table('tb_g9_exam_year_setting')->where('academic_year_id',1)->value('data'),true);
    foreach($yearSettings as $key=>$value)expect($stored[$key])->toBe($value);
    expect(json_decode(DB::table('tb_g9_exam_year_setting')->where('academic_year_id',2)->value('data'),true))->toBe(['cardDate'=>'Next year date']);
    expect(G9ExamCandidate::all()->toJson())->toBe($before)->and(DB::table('tb_student')->get()->toJson())->toBe($students);
    expect(DB::table('tb_g9_exam_setting_event')->where('action','save-center-settings')->count())->toBe(1);
    expect(fn()=>$settings->saveCenter(g12Request($admin,$input)))->toThrow(HttpException::class);
    $year=$settings->show(g12Request($admin,['academic_year_id'=>1]))->getData(true);
    $settings->save(g12Request($admin,['academic_year_id'=>1,'version'=>2,'location'=>'Phnom Penh','dates'=>$year['dates'],'report_centers'=>[['exam_center'=>'Test Center','location'=>'ខេត្តព្រះសីហនុ','department'=>'Edited','signer'=>'ប្រធានមណ្ឌល']]]));
    $retained=$settings->showCenter(g12Request($admin,['academic_year_id'=>1,'exam_center'=>'Test Center']))->getData(true);
    expect($retained['dates']['cardDate']['date'])->toBe('2026-08-02')->and($retained['signer_name'])->toBe('Center Signer');
});

it('supports blank center dates and restoring inheritance without copying or deleting year defaults',function(){
    $admin=User::find(1);$settings=app(\App\Http\Controllers\G9ExamSettingController::class);
    $this->controller->save(g12Request($admin,$this->input));
    DB::table('tb_g9_exam_year_setting')->insert(['academic_year_id'=>1,'data'=>json_encode(['cardDate'=>'Year card','cardDate_iso'=>'2026-07-02']),'version'=>1,'updated_by'=>1,'created_at'=>now(),'updated_at'=>now()]);
    $loaded=$settings->showCenter(g12Request($admin,['academic_year_id'=>1,'exam_center'=>'Test Center']))->getData(true);
    $dates=collect($loaded['dates'])->map(fn($date)=>$date+['inherit'=>true])->all();$dates['cardDate']=['inherit'=>false,'date'=>null,'solar'=>'','lunar'=>''];
    $input=['academic_year_id'=>1,'exam_center'=>'Test Center','version'=>1,'location'=>'','department'=>'','signer'=>'','signer_name'=>'','dates'=>$dates];
    $saved=$settings->saveCenter(g12Request($admin,$input))->getData(true);
    expect($saved['dates']['cardDate'])->toBe(['date'=>'','solar'=>'','lunar'=>'']);
    $stored=json_decode(DB::table('tb_g9_exam_year_setting')->where('academic_year_id',1)->value('data'),true);
    expect(\App\Support\G9ExamCenterSettings::resolve($stored,'Test Center')['cardDate_iso'])->toBe('');
    $input['version']=2;$input['dates']['cardDate']['inherit']=true;
    $restored=$settings->saveCenter(g12Request($admin,$input))->getData(true);
    expect($restored['date_overrides'])->toBe([])->and($restored['dates']['cardDate']['date'])->toBe('2026-07-02');
    expect(json_decode(DB::table('tb_g9_exam_year_setting')->where('academic_year_id',1)->value('data'),true)['cardDate'])->toBe('Year card');
});

it('enforces permissions and validation on exam-center settings endpoints',function(){
    $admin=User::find(1);$user=User::find(2);$settings=app(\App\Http\Controllers\G9ExamSettingController::class);
    $this->controller->save(g12Request($admin,$this->input));
    expect(fn()=>$settings->showCenter(g12Request($user,['academic_year_id'=>1,'exam_center'=>'Test Center'])))->toThrow(HttpException::class);
    foreach(['view','settings','settings-manage'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $loaded=$settings->showCenter(g12Request($user,['academic_year_id'=>1,'exam_center'=>'Test Center']))->getData(true);expect($loaded['can_manage'])->toBeFalse();
    $dates=collect($loaded['dates'])->map(fn($date)=>$date+['inherit'=>true])->all();
    $input=['academic_year_id'=>1,'exam_center'=>'Test Center','version'=>0,'location'=>'Province','department'=>'','signer'=>'','signer_name'=>'','dates'=>$dates];
    expect(fn()=>$settings->saveCenter(g12Request($user,$input)))->toThrow(HttpException::class);
    expect(fn()=>$settings->showCenter(g12Request($admin,['academic_year_id'=>1,'exam_center'=>'Not in this year'])))->toThrow(HttpException::class);
    $invalid=$input;$invalid['dates']['cardDate']=['inherit'=>false,'date'=>'2026-02-30','solar'=>'','lunar'=>''];
    expect(fn()=>$settings->saveCenter(g12Request($admin,$invalid)))->toThrow(ValidationException::class);
    $invalid=$input;unset($invalid['dates']['certDate']);expect(fn()=>$settings->saveCenter(g12Request($admin,$invalid)))->toThrow(ValidationException::class);
    $invalid=$input;$invalid['photo_signature']='data:image/png;base64,'.base64_encode('<script>bad</script>');
    expect(fn()=>$settings->saveCenter(g12Request($admin,$invalid)))->toThrow(ValidationException::class);
    DB::table('users')->where('id',2)->update(['is_global'=>true]);$central=User::find(2);
    expect($settings->saveCenter(g12Request($central,$input))->getData(true)['version'])->toBe(1);
    DB::table('access_user_permission_overrides')->where('user_id',2)->where('permission_id',DB::table('access_permissions')->where('code','g9-national-exams.settings-manage')->value('id'))->update(['allowed'=>false]);
    $input['version']=1;expect(fn()=>$settings->saveCenter(g12Request($central,$input)))->toThrow(HttpException::class);
});

it('uses each center dates in candidate profiles and EOY reports while preserving manual attendance dates',function(){
    $admin=User::find(1);$settings=app(\App\Http\Controllers\G9ExamSettingController::class);$reports=app(\App\Support\G9CandidateReport::class);
    $this->controller->save(g12Request($admin,$this->input));$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'OTHER','exam_center'=>'Other Center'])));
    $data=['date_location'=>'Phnom Penh','listDate_iso'=>'2026-07-01','k_listdate'=>'Year list lunar','cardDate_iso'=>'2026-07-02','k_cardDate'=>'Year card lunar','resultDate_iso'=>'2026-07-03'];
    DB::table('tb_g9_exam_year_setting')->insert(['academic_year_id'=>1,'version'=>1,'data'=>json_encode($data),'updated_by'=>1,'created_at'=>now(),'updated_at'=>now()]);
    $loaded=$settings->showCenter(g12Request($admin,['academic_year_id'=>1,'exam_center'=>'Test Center']))->getData(true);
    $dates=collect($loaded['dates'])->map(fn($date)=>$date+['inherit'=>true])->all();
    foreach(['listDate'=>'2026-08-01','cardDate'=>'2026-08-02','resultDate'=>'2026-08-03'] as $key=>$iso)$dates[$key]=['inherit'=>false,'date'=>$iso,'solar'=>'Center '.$key,'lunar'=>'Center lunar '.$key];
    $signature='data:image/png;base64,'.base64_encode(g12PhotoBytes());
    $input=['academic_year_id'=>1,'exam_center'=>'Test Center','version'=>1,'location'=>'ខេត្តព្រះសីហនុ','department'=>'Center Department','signer'=>'ប្រធានមណ្ឌល','signer_name'=>'សុខ សាន្ត','photo_signature'=>$signature,'dates'=>$dates];
    $settings->saveCenter(g12Request($admin,$input));
    $stored=json_decode(DB::table('tb_g9_exam_year_setting')->where('academic_year_id',1)->value('data'),true);$records=G9ExamCandidate::orderBy('id')->get();
    $pages=$reports->pages($records,$stored,'candidates');expect($pages[0]['solar'])->toContain('០១','សីហា')->and($pages[0]['lunar'])->toBe('Center lunar listDate')->and($pages[0]['signer_name'])->toBe('សុខ សាន្ត')
        ->and($pages[1]['solar'])->toContain('កក្កដា')->and($pages[1]['lunar'])->toBe('Year list lunar');
    $branding=Mockery::mock(\App\Support\ScholarshipExport::class);$branding->shouldReceive('reportLogo')->with(2)->andReturn(null);app()->instance(\App\Support\ScholarshipExport::class,$branding);
    $profiles=$reports->pages($records,$stored,'profile');expect($profiles[0]['profile']['solar'])->toContain('០២','សីហា')->and($profiles[0]['profile']['lunar'])->toBe('Center lunar cardDate')
        ->and($profiles[0]['profile']['signature'])->toStartWith('data:image/png;base64,')->and($profiles[1]['profile']['solar'])->toContain('កក្កដា')->and($profiles[1]['profile']['signature'])->toBeNull();
    $eoy=app(\App\Support\G9EoyReport::class)->pages($records,$stored,'card');expect($eoy[0]['solar'])->toContain('០៣','សីហា')->and($eoy[1]['solar'])->toContain('កក្កដា');
    $attendance=$reports->pages($records,$stored,'attendance-1');expect($attendance[0]['solar'])->toContain('ខេត្តព្រះសីហនុ')->not->toContain('សីហា')->and($attendance[0]['lunar'])->toContain('ព.ស.២៥');
    $input['version']=2;unset($input['photo_signature']);$settings->saveCenter(g12Request($admin,$input));
    $retained=$settings->showCenter(g12Request($admin,['academic_year_id'=>1,'exam_center'=>'Test Center']))->getData(true);expect($retained['photo_signature'])->toBe($profiles[0]['profile']['signature']);
    $input['version']=3;$input['photo_signature']=null;$settings->saveCenter(g12Request($admin,$input));
    expect($settings->showCenter(g12Request($admin,['academic_year_id'=>1,'exam_center'=>'Test Center']))->getData(true)['photo_signature'])->toBeNull();
    if(getenv('G9_REPORT_QA')){
        $folder=storage_path('app/private/g9-report-qa');if(!is_dir($folder))mkdir($folder,0755,true);
        file_put_contents($folder.'/center-dates-candidates.pdf',$reports->pdf(['type'=>'candidates','pages'=>$pages]));
        file_put_contents($folder.'/center-dates-profile.pdf',$reports->pdf(['type'=>'profile','pages'=>$profiles]));
        file_put_contents($folder.'/center-dates-eoy.pdf',app(\App\Support\G9EoyReport::class)->pdf($eoy,'card'));
    }
});

it('loads legacy date text and saves academic year dates without losing signers',function(){
    $admin=User::find(1);$controller=app(\App\Http\Controllers\G9ExamSettingController::class);
    $raw="\u{179A}\u{17B6}\u{1787}\u{1792}\u{17B6}\u{1793}\u{17B8}\u{1797}\u{17D2}\u{1793}\u{17C6}\u{1796}\u{17C1}\u{1789} \u{1790}\u{17D2}\u{1784}\u{17C3}\u{1791}\u{17B8}\u{17E2}\u{17E1} \u{1781}\u{17C2}\u{1780}\u{1780}\u{17D2}\u{1780}\u{178A}\u{17B6} \u{1786}\u{17D2}\u{1793}\u{17B6}\u{17C6}\u{17E2}\u{17E0}\u{17E2}\u{17E5}";
    DB::table('tb_g9_exam_year_setting')->insert(['academic_year_id'=>1,'data'=>json_encode(['listDate'=>$raw,'k_listdate'=>'Original lunar text','resultDateDOEYS'=>'Unfilled legacy placeholder','score_verify_by'=>'Signer']), 'version'=>1,'updated_by'=>1,'created_at'=>now(),'updated_at'=>now()]);
    $result=$controller->show(g12Request($admin,['academic_year_id'=>1]))->getData(true);
    expect($result['dates']['listDate']['date'])->toBe('2025-07-21')->and($result['dates']['listDate']['solar'])->toBe($raw)
        ->and($result['dates']['resultDateDOEYS']['date'])->toBe('')->and($result['dates']['resultDateDOEYS']['solar'])->toBe('Unfilled legacy placeholder');
    $result['dates']['certDate']=['date'=>'2026-11-04','solar'=>'New solar text','lunar'=>'New lunar text'];
    $input=['academic_year_id'=>1,'version'=>1,'location'=>'Phnom Penh','dates'=>$result['dates']];
    $saved=$controller->save(g12Request($admin,$input))->getData(true);
    expect($saved['version'])->toBe(2)->and($saved['dates']['certDate']['date'])->toBe('2026-11-04')
        ->and(json_decode(DB::table('tb_g9_exam_year_setting')->value('data'),true)['score_verify_by'])->toBe('Signer')
        ->and(DB::table('tb_g9_exam_setting_event')->count())->toBe(1);
    expect(fn()=>$controller->save(g12Request($admin,$input)))->toThrow(HttpException::class);
});

it('restricts shared date changes to central users with explicit settings permissions',function(){
    $controller=app(\App\Http\Controllers\G9ExamSettingController::class);$user=User::find(2);
    foreach(['view','settings','settings-manage'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $data=$controller->show(g12Request($user,['academic_year_id'=>1]))->getData(true);
    expect($data['can_manage'])->toBeFalse();
    $input=['academic_year_id'=>1,'version'=>0,'location'=>'Phnom Penh','dates'=>$data['dates']];
    expect(fn()=>$controller->save(g12Request($user,$input)))->toThrow(HttpException::class);
    DB::table('users')->where('id',2)->update(['is_global'=>true]);$user=User::find(2);
    expect($controller->save(g12Request($user,$input))->getData(true)['version'])->toBe(1);
});

it('groups report pages by exam center room and date with natural seat ordering',function(){
    $admin=User::find(1);$report=app(\App\Support\G9CandidateReport::class);
    foreach(range(1,26) as $seat)$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'REPORT'.$seat,'seat_number'=>(string)$seat])));
    $this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'ROOM2','room_number'=>'2'])));
    $this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'DATE2','exam_date'=>'2026-08-11'])));
    $records=G9ExamCandidate::all();$settings=['listDate_iso'=>'2026-07-21','date_location'=>'រាជធានីភ្នំពេញ','report_centers'=>[['exam_center'=>'Test Center','location'=>'ខេត្តព្រះសីហនុ','department'=>'មន្ទីរអប់រំ យុវជន និងកីឡា ខេត្តព្រះសីហនុ','signer'=>'ប្រធានមណ្ឌល']]];
    $pages=$report->pages($records,$settings,'candidates');
    expect($pages)->toHaveCount(4)->and($pages[0]['total'])->toBe(25)->and($pages[1]['total'])->toBe(1)
        ->and($pages[0]['rows'][1]['seat'])->toBe('2')->and($pages[0]['solar'])->toStartWith('ខេត្តព្រះសីហនុ')
        ->and($pages[0]['female'])->toBe(25);
    $cover=$report->pages($records,$settings,'cover');expect($cover)->toHaveCount(3)->and($cover[0]['total'])->toBe(26);
    $pdf=$report->pdf(['type'=>'candidates','pages'=>array_slice($pages,0,2)]);
    expect(preg_match_all('~/Type\s*/Page\b~',$pdf))->toBe(2);
});

it('exports the same four report layouts to print PDF and valid Excel workbooks',function(){
    $admin=User::find(1);$report=app(\App\Support\G9CandidateReport::class);
    $this->controller->save(g12Request($admin,$this->input));
    foreach(array_keys(\App\Support\G9CandidateReport::TITLES) as $type){
        if(in_array($type,\App\Support\G9CandidateReport::PRINT_ONLY_TYPES,true))continue;
        $request=g12Request($admin,['academic_year'=>1]);
        $html=$this->controller->report($request,$type,'print',$report)->getContent();
        expect(str_contains($html,'Test Center')&&str_contains($html,'g9body')&&str_contains($html,'២០២៦'))->toBeTrue()
            ->and(str_contains($html,'$page[')||str_contains($html,'@foreach'))->toBeFalse();
        $pdf=$this->controller->report($request,$type,'pdf',$report)->getContent();expect($pdf)->toStartWith('%PDF-');
        expect(preg_match_all('~/Type\s*/Page\b~',$pdf))->toBe(1);
        $excel=$this->controller->report($request,$type,'excel',$report)->getContent();$path=tempnam(sys_get_temp_dir(),'g9-test-');file_put_contents($path,$excel);
        try{
            $zip=new ZipArchive();expect($zip->open($path))->toBeTrue();
            for($i=0;$i<$zip->numFiles;$i++)if(str_ends_with($zip->getNameIndex($i),'.xml')){ $doc=new DOMDocument();expect($doc->loadXML($zip->getFromIndex($i)))->toBeTrue(); }
            expect($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('paperSize="9"','fitToHeight="1"','Test Center');$zip->close();
        }finally{unlink($path);}
        if(getenv('G9_REPORT_QA')){
            $folder=storage_path('app/private/g9-report-qa');if(!is_dir($folder))mkdir($folder,0755,true);
            $qa=['type'=>$type,'pages'=>$report->pages(G9ExamCandidate::all(),['listDate_iso'=>'2026-07-21','date_location'=>'រាជធានីភ្នំពេញ','k_listdate'=>'ថ្ងៃអង្គារ ៧កើត ខែស្រាពណ៍ ឆ្នាំមមី អដ្ឋស័ក ព.ស.២៥៧០','report_centers'=>[['exam_center'=>'Test Center','location'=>'ខេត្តព្រះសីហនុ','department'=>'មន្ទីរអប់រំ យុវជន និងកីឡា ខេត្តព្រះសីហនុ']]],$type)];
            $qa['pages'][0]['center']='វិ. សាលាវេស្ទើនអន្តរជាតិ សាខាខេត្តព្រះសីហនុ';
            $sample=$qa['pages'][0]['rows'][0];$sample['name']='កង សុគន្ធនរិន្ទ';$sample['school']='វិ. សាលាវេស្ទើនអន្តរជាតិ (ភ្នំពេញថ្មី)';$sample['language']='អង់គ្លេស';
            $qa['pages'][0]['rows']=array_map(fn($seat)=>array_replace($sample,['seat'=>(string)$seat]),range(1,25));$qa['pages'][0]['total']=25;$qa['pages'][0]['female']=25;
            $qaPdf=$report->pdf($qa);expect(preg_match_all('~/Type\s*/Page\b~',$qaPdf))->toBe(1);
            file_put_contents($folder.'/'.$type.'.pdf',$qaPdf);file_put_contents($folder.'/'.$type.'.xlsx',$report->xlsx($qa));file_put_contents($folder.'/'.$type.'.html',view('g9-national-exams.candidate-report',$qa)->render());
        }
    }
});

it('requires report permissions and permits campus users to print all campuses',function(){
    $admin=User::find(1);$user=User::find(2);$report=app(\App\Support\G9CandidateReport::class);
    $this->controller->save(g12Request($admin,$this->input));
    $this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'PRIVATE','campus_id'=>2,'exam_center'=>'Other Campus Secret'])));
    expect(fn()=>$this->controller->report(g12Request($admin),'candidates','print',$report))->toThrow(ValidationException::class);
    foreach(['view','candidates','print','report-candidates'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $html=$this->controller->report(g12Request($user,['academic_year'=>1]),'candidates','print',$report)->getContent();
    expect($html)->toContain('Test Center','Other Campus Secret');
    DB::table('access_user_permission_overrides')->where('user_id',2)->delete();
    expect(fn()=>$this->controller->report(g12Request($user,['academic_year'=>1]),'candidates','print',$report))->toThrow(HttpException::class);
});

it('saves per-center provincial report headings without changing shared dates or candidate data',function(){
    $admin=User::find(1);$controller=app(\App\Http\Controllers\G9ExamSettingController::class);
    $this->controller->save(g12Request($admin,$this->input));$before=G9ExamCandidate::first()->toArray();
    $current=$controller->show(g12Request($admin,['academic_year_id'=>1]))->getData(true);
    expect($current['report_centers'])->toHaveCount(1);
    $input=['academic_year_id'=>1,'version'=>0,'location'=>'រាជធានីភ្នំពេញ','dates'=>$current['dates'],
        'report_centers'=>[['exam_center'=>'Test Center','location'=>'ខេត្តព្រះសីហនុ','department'=>'Department','signer'=>'ប្រធានមណ្ឌល']]];
    $saved=$controller->save(g12Request($admin,$input))->getData(true);
    expect($saved['report_centers'][0]['location'])->toBe('ខេត្តព្រះសីហនុ')->and($saved['dates'])->toBe($current['dates'])
        ->and(G9ExamCandidate::first()->toArray())->toBe($before);
});

it('prints individual profiles using card issue dates and the matching center signature',function(){
    $admin=User::find(1);$settings=app(\App\Http\Controllers\G9ExamSettingController::class);$reports=app(\App\Support\G9CandidateReport::class);
    $request=g12Request($admin,$this->input);$request->files->set('photo',UploadedFile::fake()->image('profile.jpg',600,800));
    $id=$this->controller->save($request)->getData(true)['id'];
    $this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'OTHERPROFILE','exam_center'=>'Other Center'])));
    $current=$settings->show(g12Request($admin,['academic_year_id'=>1]))->getData(true);
    $signatureImage=imagecreatetruecolor(500,160);imagealphablending($signatureImage,false);imagesavealpha($signatureImage,true);
    imagefill($signatureImage,0,0,imagecolorallocatealpha($signatureImage,255,255,255,127));
    $ink=imagecolorallocate($signatureImage,20,70,190);imagesetthickness($signatureImage,4);
    foreach([[20,120,100,30],[100,30,90,110],[90,110,190,50],[190,50,170,100],[170,100,320,45],[320,45,280,120],[280,120,460,70]] as [$x1,$y1,$x2,$y2])imageline($signatureImage,$x1,$y1,$x2,$y2,$ink);
    ob_start();imagepng($signatureImage);$signature='data:image/png;base64,'.base64_encode(ob_get_clean());imagedestroy($signatureImage);
    $input=['academic_year_id'=>1,'version'=>0,'location'=>'រាជធានីភ្នំពេញ','dates'=>$current['dates'],
        'report_centers'=>[['exam_center'=>'Test Center','location'=>'ខេត្តព្រះសីហនុ','signer'=>'ប្រធានមណ្ឌល','photo_signature'=>$signature]]];
    $input['dates']['cardDate']=['date'=>'2026-07-23','solar'=>'Card date','lunar'=>'Card lunar date'];
    $input['dates']['listDate']=['date'=>'2026-07-21','solar'=>'Wrong list date','lunar'=>'Wrong list lunar'];
    $settings->save(g12Request($admin,$input));
    $stored=json_decode(DB::table('tb_g9_exam_year_setting')->value('data'),true);
    $branding=Mockery::mock(\App\Support\ScholarshipExport::class);$branding->shouldReceive('reportLogo')->with(2)->andReturn($signature);
    app()->instance(\App\Support\ScholarshipExport::class,$branding);
    $pages=$reports->pages(G9ExamCandidate::all(),$stored,'profile');
    expect($pages)->toHaveCount(2)->and($pages[0]['profile']['lunar'])->toBe('Card lunar date')
        ->and($pages[0]['profile']['solar'])->toContain('២៣','ខេត្តព្រះសីហនុ')->and($pages[0]['profile']['signature'])->toStartWith('data:image/png;base64,')
        ->and($pages[1]['profile']['signature'])->toBeNull()->and($pages[0]['profile']['photo'])->toStartWith('data:image/webp;base64,');
    $html=$this->controller->report(g12Request($admin,['academic_year'=>1,'student'=>$id]),'profile','print',$reports)->getContent();
    expect(str_contains($html,'សលាកបត្រឯកត្តជន')&&str_contains($html,'profile-sheet')&&str_contains($html,'Card lunar date'))->toBeTrue()
        ->and(str_contains($html,'Wrong list date'))->toBeFalse()
        ->and(str_contains($html,'class="profile-background"'))->toBeTrue()->and(str_contains($html,'profile-student-id'))->toBeFalse()
        ->and($pages[0]['profile']['background'])->toBe($signature);
    $pdf=$reports->pdf(['type'=>'profile','pages'=>$pages]);expect(preg_match_all('~/Type\s*/Page\b~',$pdf))->toBe(1);
    $odd=$reports->pdf(['type'=>'profile','pages'=>[$pages[0],$pages[1],$pages[0]]]);expect(preg_match_all('~/Type\s*/Page\b~',$odd))->toBe(2);
    expect(fn()=>$this->controller->report(g12Request($admin,['academic_year'=>1]),'profile','excel',$reports))->toThrow(HttpException::class);
    if(getenv('G9_REPORT_QA')){
        $qa=$pages[0];$qa['profile']['photo']='data:image/png;base64,'.base64_encode(file_get_contents(storage_path('app/imports/studentimage.JPG')));
        $second=$qa;$second['profile']['name']='បេក្ខជនទីពីរ';
        file_put_contents(storage_path('app/private/g9-report-qa/profile.pdf'),$reports->pdf(['type'=>'profile','pages'=>[$qa,$second]]));
        file_put_contents(storage_path('app/private/g9-report-qa/profile.html'),view('g9-national-exams.candidate-report',['type'=>'profile','pages'=>[$qa,$second]])->render());
    }
    $input['version']=1;$input['report_centers'][0]['photo_signature']='data:image/png;base64,'.base64_encode('<script>invalid</script>');
    expect(fn()=>$settings->save(g12Request($admin,$input)))->toThrow(ValidationException::class);
    $input['version']=1;unset($input['report_centers'][0]['photo_signature']);$settings->save(g12Request($admin,$input));
    expect(json_decode(DB::table('tb_g9_exam_year_setting')->value('data'),true)['report_centers'][0]['photo_signature'])->toBe($pages[0]['profile']['signature']);
});

it('reads the latest Khmer address only from a unique exact student ID in the candidate year and campus',function(){
    $admin=User::find(1);$reports=app(\App\Support\G9CandidateReport::class);
    $id=$this->controller->save(g12Request($admin,$this->input))->getData(true)['id'];
    DB::table('tb_student')->insert([
        ['id'=>2,'student_id'=>'000123','full_name_en'=>'Exact Student','current_address_kh'=>'ផ្ទះលេខ១ ភូមិថ្មី រាជធានីភ្នំពេញ'],
        ['id'=>3,'student_id'=>'000123','full_name_en'=>'Other Campus','current_address_kh'=>'PRIVATE CAMPUS ADDRESS'],
        ['id'=>4,'student_id'=>'000123','full_name_en'=>'Other Year','current_address_kh'=>'PRIVATE YEAR ADDRESS'],
    ]);
    DB::table('tb_student_enrollment')->insert([
        ['student_id'=>1,'academic_year_id'=>1,'campus_id'=>1],['student_id'=>2,'academic_year_id'=>1,'campus_id'=>1],
        ['student_id'=>3,'academic_year_id'=>1,'campus_id'=>2],['student_id'=>4,'academic_year_id'=>2,'campus_id'=>1],
    ]);
    $before=DB::table('tb_student')->get()->toArray();
    $html=$this->controller->report(g12Request($admin,['academic_year'=>1]),'profile','print',$reports)->getContent();
    expect(str_contains($html,'ផ្ទះលេខ១ ភូមិថ្មី រាជធានីភ្នំពេញ'))->toBeTrue()
        ->and(str_contains($html,'PRIVATE CAMPUS ADDRESS')||str_contains($html,'PRIVATE YEAR ADDRESS'))->toBeFalse()
        ->and(DB::table('tb_student')->get()->toArray())->toEqual($before);
    DB::table('tb_student')->where('id',2)->update(['current_address_kh'=>'អាសយដ្ឋានថ្មី']);
    expect($reports->profilePages(G9ExamCandidate::all(),[])[0]['profile']['address_kh'])->toBe('អាសយដ្ឋានថ្មី');
    DB::table('tb_student_enrollment')->insert(['student_id'=>3,'academic_year_id'=>1,'campus_id'=>1]);
    expect($reports->profilePages(G9ExamCandidate::all(),[])[0]['profile']['address_kh'])->toBe('');
    G9ExamCandidate::find($id)->update(['student_code'=>'DIFFERENT-ID']);
    expect($reports->profilePages(G9ExamCandidate::all(),[])[0]['profile']['address_kh'])->toBe('');
});

it('allows an audited manual student link only within the selected campus and academic year',function(){
    $admin=User::find(1);$reports=app(\App\Support\G9CandidateReport::class);
    DB::table('tb_student')->where('id',1)->update(['current_address_kh'=>'អាសយដ្ឋានភ្ជាប់ដោយដៃ']);
    DB::table('tb_student_enrollment')->insert(['student_id'=>1,'academic_year_id'=>1,'campus_id'=>1]);
    $choices=$this->controller->studentLinks(g12Request($admin,['academic_year_id'=>1,'campus_id'=>1]))->getData(true);
    expect($choices)->toHaveCount(1)->and($choices[0]['id'])->toBe(1);
    $id=$this->controller->save(g12Request($admin,$this->input+['sis_student_id'=>1]))->getData(true)['id'];
    expect($reports->profilePages(G9ExamCandidate::all(),[])[0]['profile']['address_kh'])->toBe('អាសយដ្ឋានភ្ជាប់ដោយដៃ')
        ->and(G9ExamCandidate::find($id)->data['sis_student_id'])->toBe(1);
    expect(fn()=>$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'OTHER','campus_id'=>2,'sis_student_id'=>1]))))->toThrow(ValidationException::class);
    expect(fn()=>$this->controller->studentLinks(g12Request(User::find(2),['academic_year_id'=>1,'campus_id'=>1])))->toThrow(HttpException::class);
    DB::table('tb_student_enrollment')->where('student_id',1)->update(['campus_id'=>2]);
    expect($reports->profilePages(G9ExamCandidate::all(),[])[0]['profile']['address_kh'])->toBe('');
    expect(DB::table('tb_g9_exam_event')->where('candidate_id',$id)->count())->toBe(1);
});

it('prints student card covers per room with seat ranges and two covers per A4 sheet',function(){
    $admin=User::find(1);$reports=app(\App\Support\G9CandidateReport::class);
    foreach([['A','1','7'],['A','1','1'],['A','2','2'],['B','1','3']] as $index=>[$center,$room,$seat])$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'COVER'.$index,'exam_center'=>$center,'room_number'=>$room,'seat_number'=>$seat])));
    $pages=$reports->pages(G9ExamCandidate::all(),[],'profile-cover');
    expect($pages)->toHaveCount(3)->and($pages[0])->toMatchArray(['center'=>'A','room'=>'1','seatFrom'=>'1','seatTo'=>'7','total'=>2]);
    $html=$this->controller->report(g12Request($admin,['academic_year'=>1]),'profile-cover','print',$reports)->getContent();
    expect(substr_count($html,'<section class="profile-sheet">'))->toBe(2)->and(str_contains($html,'សៀវភៅកម្រងសលាកបត្រឯកត្តជន'))->toBeTrue();
    $pdf=$reports->pdf(['type'=>'profile-cover','pages'=>$pages]);expect(preg_match_all('~/Type\s*/Page\b~',$pdf))->toBe(2);
    expect(fn()=>$this->controller->report(g12Request($admin,['academic_year'=>1]),'profile-cover','excel',$reports))->toThrow(HttpException::class);
    $keys=array_keys(\App\Support\G9CandidateReport::TITLES);
    expect(array_search('profile-cover',$keys))->toBe(array_search('profile',$keys)-1);
    if(getenv('G9_REPORT_QA')){
        $pages[0]['center']='វិ. សាលាវេស្ទើនអន្តរជាតិ សាខាខេត្តព្រះសីហនុ';$pages[1]['center']=$pages[0]['center'];
        file_put_contents(storage_path('app/private/g9-report-qa/profile-cover.pdf'),$reports->pdf(['type'=>'profile-cover','pages'=>array_slice($pages,0,2)]));
    }
});

it('prints seat stickers and room plans in separate room pages with actual assigned seats',function(){
    $admin=User::find(1);$reports=app(\App\Support\G9CandidateReport::class);
    foreach(range(1,26) as $seat)$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'SEAT'.$seat,'seat_number'=>(string)$seat,'name_kh'=>'សិស្ស លេខ'.str_pad((string)$seat,2,'0',STR_PAD_LEFT)])));
    $this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'OTHERROOM','room_number'=>'2','seat_number'=>'99'])));
    foreach(['seat-stickers','room-seat-plan'] as $type){
        $pages=$reports->pages(G9ExamCandidate::all(),[],$type);
        expect($pages)->toHaveCount(3)->and($pages[0]['total'])->toBe($type==='seat-stickers'?18:25)
            ->and($pages[2]['room'])->toBe('2')->and($pages[2]['rows'][0]['seat'])->toBe('99');
        $html=$this->controller->report(g12Request($admin,['academic_year'=>1]),$type,'print',$reports)->getContent();
        expect(str_contains($html,\App\Support\G9CandidateReport::TITLES[$type]))->toBeTrue();
        $pdf=$reports->pdf(['type'=>$type,'pages'=>$pages]);expect(preg_match_all('~/Type\s*/Page\b~',$pdf))->toBe(3);
        expect(fn()=>$this->controller->report(g12Request($admin,['academic_year'=>1]),$type,'excel',$reports))->toThrow(HttpException::class);
        if($type==='room-seat-plan'){
            $firstPage=substr($html,strpos($html,'class="g9-room-seats"'));
            $names=array_map(fn($seat)=>strpos($firstPage,'សិស្ស លេខ'.str_pad((string)$seat,2,'0',STR_PAD_LEFT)),[1,10,11,20,21]);
            expect($names)->toBe(collect($names)->sort()->values()->all());
        }
        if(getenv('G9_REPORT_QA')){
            $sample=$pages[0];$sample['center']='វិ. សាលាវេស្ទើនអន្តរជាតិ (ភ្នំពេញថ្មី) (ត្បូង)';
            foreach($sample['rows'] as &$student)$student['name']='កង សុគន្ធនរិន្ទ';unset($student);
            file_put_contents(storage_path('app/private/g9-report-qa/'.$type.'.pdf'),$reports->pdf(['type'=>$type,'pages'=>[$sample]]));
        }
    }
    $keys=array_keys(\App\Support\G9CandidateReport::TITLES);
    expect(array_search('seat-stickers',$keys))->toBe(array_search('profile',$keys)+1)->and(array_search('room-seat-plan',$keys))->toBe(array_search('profile',$keys)+2);
});
