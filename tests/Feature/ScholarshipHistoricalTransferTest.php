<?php

use App\Http\Controllers\{ScholarshipController,ScholarshipTransferController};
use App\Models\{ScholarshipCandidate,ScholarshipExam,ScholarshipStudentRank,SchoolInfo,StudentEnrollment,User};
use App\Services\{ScholarshipHistoricalTransfer,ScholarshipWorkflow};
use Illuminate\Http\{Request,UploadedFile};
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';

beforeEach(function(){
    createScholarshipSchema();
    $this->user=Mockery::mock(User::class)->makePartial();$this->user->id=1;$this->user->active_campus_id=1;$this->user->setRelation('roles',collect());
    $this->user->shouldReceive('isSuperAdmin')->andReturnTrue();$this->user->shouldReceive('canAccessCampus')->andReturnTrue();
    $this->user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>SchoolInfo::query());
    $this->workflow=app(ScholarshipWorkflow::class);$this->transfer=app(ScholarshipHistoricalTransfer::class);
    $this->exam=$this->workflow->saveExam($this->user,null,['academic_year_id'=>1,'exam_date'=>'2026-11-20','room_capacity'=>25,'version'=>1,'subjects'=>[
        ['name'=>'Khmer','grade_from'=>1,'grade_to'=>12,'max_score'=>100,'weight'=>1],['name'=>'English','grade_from'=>1,'grade_to'=>12,'max_score'=>50,'weight'=>3],
    ]]);
    $this->paths=[];
});
afterEach(function(){foreach($this->paths as $path)if(is_file($path))unlink($path);});

function historyCsv($test,array $rows): string
{
    $headers=$test->transfer->headers($test->exam);$path=tempnam(sys_get_temp_dir(),'wis-history-');$test->paths[]=$path;$stream=fopen($path,'wb');fwrite($stream,"\xEF\xBB\xBF");fputcsv($stream,$headers,',','"','');
    foreach($rows as $row)fputcsv($stream,array_values(array_replace(array_fill_keys($headers,''),['academic_year'=>'2025-2026','student_id'=>'2026001','class_rank'=>1],$row)),',','"','');fclose($stream);return $path;
}
function historyCommit($test,string $path,bool $replace=false): array
{
    $plan=$test->transfer->preview($test->user,$test->exam->fresh(),$path,$replace);expect($plan['errors'])->toBeEmpty();
    return $test->transfer->commit($test->user,$test->exam,$path,$replace,hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR)));
}
function historyRequest($test,array $data=[],array $files=[]): Request
{
    $request=Request::create('/students/western-scholarship/transfer','POST',$data,[], $files);$request->headers->set('Accept','application/json');$request->setUserResolver(fn()=>$test->user);$request->setLaravelSession(app('session')->driver());return $request;
}

it('round trips subject absences as A with zero totals without losing attended scores',function(){
    $path=historyCsv($this,[['score:Khmer'=>'A','score:English'=>40,'total_score'=>40,'percentage'=>60,'award_category'=>'subject','award_subject'=>'English','award_percent'=>25]]);
    historyCommit($this,$path);$candidate=$this->exam->candidates()->with('scores')->first();
    expect($candidate->absent)->toBeFalse()->and($candidate->scores->firstWhere('subject_id',$this->exam->subjects->firstWhere('name','Khmer')->id)->is_absent)->toBeTrue()
        ->and($candidate->result_total)->toBe(40.0);
    $export=iterator_to_array($this->transfer->export($this->user,$this->exam->fresh()));$row=array_combine($export[0],$export[1]);
    expect($row['score:Khmer'])->toBe('A')->and($row['score:English'])->toBe(40.0)->and($row['absent'])->toBe('0');
    $newPath=historyCsv($this,[$row]);historyCommit($this,$newPath,true);
    expect($candidate->fresh()->scores->firstWhere('subject_id',$this->exam->subjects->firstWhere('name','Khmer')->id)->is_absent)->toBeTrue();
});

it('imports whole exam absence with every subject zero while retaining the student in results',function(){
    $path=historyCsv($this,[['absent'=>1]]);historyCommit($this,$path);
    $candidate=$this->exam->candidates()->first();
    expect($candidate->absent)->toBeTrue()->and($candidate->result_total)->toBe(0.0)->and($candidate->scores()->where('is_absent',true)->count())->toBe(2);
    $export=iterator_to_array($this->transfer->export($this->user,$this->exam->fresh()));$row=array_combine($export[0],$export[1]);
    expect($row['score:Khmer'])->toBe('A')->and($row['score:English'])->toBe('A')->and($row['total_score'])->toBe(0.0);
    historyCommit($this,historyCsv($this,[$row]),true);
    expect($this->workflow->results($this->exam->fresh()))->toHaveCount(1);
});

it('rejects awards for missed subjects and overall awards after a subject absence',function($category,$subject){
    $path=historyCsv($this,[['score:Khmer'=>'A','score:English'=>40,'award_category'=>$category,'award_subject'=>$subject,'award_percent'=>25]]);
    expect($this->transfer->preview($this->user,$this->exam,$path)['errors'])->not->toBeEmpty();
})->with([['total_1',''],['subject','Khmer']]);

it('provides eight sample records with subject columns from the selected settings',function(){
    $rows=$this->transfer->sample($this->exam);expect($rows)->toHaveCount(9)->and($rows[0])->toContain('score:Khmer','score:English')->not->toContain('score:Science');
    foreach(array_slice($rows,1) as $row)expect($row)->toHaveCount(count($rows[0]));
    expect($this->transfer->sample(null)[1][0])->toBe('2023-2024');
});

it('demonstrates three overall awards four distinct subject awards and a student with no award',function(){
    foreach(['Mathematics','Science'] as $index=>$name)$this->exam->subjects()->create(['name'=>$name,'grade_from'=>1,'grade_to'=>12,'max_score'=>100,'weight'=>1,'position'=>$index+2]);
    $this->exam->refresh();
    foreach([$this->exam,null] as $exam){
        $sample=$this->transfer->sample($exam);$rows=collect(array_slice($sample,1))->map(fn($row)=>array_combine($sample[0],$row));
        expect($rows->take(3)->pluck('award_category')->all())->toBe(['total_1','total_2','total_3'])
            ->and($rows->take(3)->pluck('award_percent')->all())->toBe([100,75,50])
            ->and($rows->take(3)->pluck('exam_rank')->all())->toBe([1,2,3])
            ->and($rows->take(3)->pluck('award_subject')->unique()->all())->toBe(['']);
        $subjectRows=$rows->slice(3,4);expect($subjectRows->pluck('award_category')->all())->toBe(['subject','subject','subject','subject'])
            ->and($subjectRows->pluck('award_percent')->all())->toBe([25,25,25,25])->and($subjectRows->pluck('award_subject')->unique())->toHaveCount(4);
        foreach($subjectRows as $row)expect($row['score:'.$row['award_subject']])->toBe($rows->slice(3)->max('score:'.$row['award_subject']));
        expect($rows->last()['award_category'])->toBe('')->and($rows->last()['award_subject'])->toBe('')->and($rows->last()['award_percent'])->toBe('')
            ->and($rows->pluck('class_rank')->unique()->sort()->values()->all())->toBe([1,2,3]);
    }
});

it('generates eight configured sample records that pass import validation once example enrollments exist',function(){
    foreach(['Mathematics','Science'] as $index=>$name)$this->exam->subjects()->create(['name'=>$name,'grade_from'=>1,'grade_to'=>12,'max_score'=>100,'weight'=>1,'position'=>$index+2]);
    $this->exam->refresh();$sample=$this->transfer->sample($this->exam);$rows=array_map(fn($row)=>array_combine($sample[0],$row),array_slice($sample,1));
    DB::table('tb_session')->insert(['id'=>1,'session_name'=>'Session III','session_short_name'=>'III']);
    DB::table('tb_class')->insert([['id'=>3,'academic_year_id'=>1,'grade_id'=>1,'class_name'=>'B'],['id'=>4,'academic_year_id'=>1,'grade_id'=>1,'class_name'=>'C']]);
    foreach($rows as $index=>$row){
        $id=$index+1;
        if($id<=2)DB::table('tb_student')->where('id',$id)->update(['student_id'=>$row['student_id']]);
        else DB::table('tb_student')->insert(['id'=>$id,'student_id'=>$row['student_id'],'full_name_en'=>'EXAMPLE STUDENT '.$id,'full_name_kh'=>'']);
        $values=['student_id'=>$id,'academic_year_id'=>1,'campus_id'=>1,'grade_id'=>1,'class_id'=>match($row['class']){'A'=>1,'B'=>3,'C'=>4},'session_id'=>1];
        DB::table('tb_student_enrollment')->updateOrInsert(['student_id'=>$id,'academic_year_id'=>1],$values);
    }
    $path=historyCsv($this,$rows);$plan=$this->transfer->preview($this->user,$this->exam,$path);
    expect($plan['errors'])->toBeEmpty()->and($plan['created'])->toBe(8);
});

it('previews without writing then imports ranks scores and original awards into historical records',function(){
    $path=historyCsv($this,[['score:Khmer'=>90,'score:English'=>40,'total_score'=>130,'percentage'=>82.5,'exam_rank'=>2,'award_category'=>'total_2','award_percent'=>75,'eoy_average'=>95.25,'exam_center'=>'Old Center','room_no'=>2,'seat_no'=>29,'exam_code'=>'OLD-029'],
        ['student_id'=>'2026002','class_rank'=>2,'total_score'=>140,'exam_rank'=>1,'award_category'=>'total_1','award_percent'=>100]]);
    $plan=$this->transfer->preview($this->user,$this->exam,$path);
    expect($plan['errors'])->toBeEmpty()->and($plan['created'])->toBe(2)->and($this->exam->candidates()->count())->toBe(0)->and(ScholarshipStudentRank::count())->toBe(0);
    historyCommit($this,$path);$exam=$this->exam->fresh();$candidate=$exam->candidates()->where('student_id',1)->first();
    expect($exam->isHistorical())->toBeTrue()->and($exam->published_at)->toBeNull()->and($candidate->room_number)->toBe(2)->and($candidate->room_seat)->toBe(29)
        ->and($candidate->result_total)->toBe(130.0)->and($candidate->result_rank)->toBe(2)->and($candidate->scores()->count())->toBe(2)
        ->and($candidate->scores()->first()->entry_method)->toBe('historical')->and($exam->awards()->count())->toBe(2)->and(ScholarshipStudentRank::count())->toBe(2);
    $results=$this->workflow->results($exam)->keyBy(fn($row)=>$row['candidate']->student_id);
    expect($results[1]['rank'])->toBe(2)->and($results[1]['award'])->toBe(75.0)->and($results[2]['total'])->toBe(140.0)->and($results[2]['award_label'])->toBe('1st Place');
    expect($this->workflow->plan($exam)['rows'][0]['room_number'])->not->toBe(1);
    expect($exam->events()->where('action','historical-csv-imported')->count())->toBe(1);
});

it('preserves totals only awards only and missing information without inventing zero scores',function(){
    historyCommit($this,historyCsv($this,[['total_score'=>321,'exam_rank'=>4,'award_category'=>'Excellence Award','award_percent'=>30],['student_id'=>'2026002','class_rank'=>2,'award_category'=>'subject','award_subject'=>'Khmer','award_percent'=>25]]));
    $rows=$this->workflow->results($this->exam->fresh())->keyBy(fn($row)=>$row['candidate']->student_id);
    expect($rows[1]['total'])->toBe(321.0)->and($rows[1]['rank'])->toBe(4)->and($rows[2]['total'])->toBeNull()->and($rows[2]['award_label'])->toBe('Best in Khmer')
        ->and($rows[2]['candidate']->scores)->toBeEmpty()->and($rows[1]['candidate']->room_number)->toBeNull()->and(ScholarshipStudentRank::first()->eoy_average)->toBeNull();
});

it('rejects invalid historical rows without partial writes',function($row,$message){
    $path=historyCsv($this,[[],$row]);$plan=$this->transfer->preview($this->user,$this->exam,$path);
    expect($plan['errors'])->not->toBeEmpty()->and(implode(' ',array_column($plan['errors'],'message')))->toContain($message);
    expect(fn()=>$this->transfer->commit($this->user,$this->exam,$path,false,hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR))))->toThrow(ValidationException::class);
    expect($this->exam->candidates()->count())->toBe(0)->and(ScholarshipStudentRank::count())->toBe(0);
})->with([
    [['student_id'=>'MISSING'],'Student ID'],[['academic_year'=>'2023-2024'],'Academic Year'],[['campus'=>'BKK'],'campus'],[['grade'=>9],'grade'],[['class_rank'=>4],'class_rank'],
    [['eoy_average'=>101],'eoy_average'],[['score:English'=>51],'score:English'],[['score:Khmer'=>90,'score:English'=>40,'total_score'=>129],'Total Score'],
    [['score:Khmer'=>90,'score:English'=>40,'percentage'=>50],'Percentage'],[['room_no'=>1],'Room No.'],[['award_category'=>'subject','award_percent'=>25],'Award Subject'],
    [['award_percent'=>100],'Award Category'],[['absent'=>1,'total_score'=>1],'absent'],[[],'same student'],
]);

it('requires a matching historical enrollment rather than using the current enrollment',function(){
    DB::table('tb_student_enrollment')->where('id',1)->delete();$plan=$this->transfer->preview($this->user,$this->exam,historyCsv($this,[[]]));
    expect($plan['errors'][0]['message'])->toContain('historical academic year')->and($this->exam->candidates()->count())->toBe(0);
});

it('requires explicit replacements and retains records outside the CSV and other years',function(){
    historyCommit($this,historyCsv($this,[['total_score'=>130],['student_id'=>'2026002','class_rank'=>2,'total_score'=>100]]));
    $path=historyCsv($this,[['total_score'=>140,'exam_rank'=>1]]);
    expect($this->transfer->preview($this->user,$this->exam->fresh(),$path)['errors'][0]['message'])->toContain('Replace Matching Records');
    $other=$this->workflow->saveExam($this->user,null,['academic_year_id'=>2,'exam_date'=>'2027-11-20','room_capacity'=>25,'version'=>1,'subjects'=>[['name'=>'English','grade_from'=>1,'grade_to'=>12,'max_score'=>100,'weight'=>1]]]);
    historyCommit($this,$path,true);$rows=$this->exam->candidates()->orderBy('student_id')->get();
    expect($rows)->toHaveCount(2)->and($rows[0]->result_total)->toBe(140.0)->and($rows[1]->result_total)->toBe(100.0)->and($other->fresh()->isHistorical())->toBeFalse();
});

it('rejects a stale preview and a repeated add-only import',function(){
    $path=historyCsv($this,[[]]);$plan=$this->transfer->preview($this->user,$this->exam,$path);$this->exam->increment('version');
    expect(fn()=>$this->transfer->commit($this->user,$this->exam,$path,false,hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR))))->toThrow(ValidationException::class);
    historyCommit($this,$path);
    expect($this->transfer->preview($this->user,$this->exam->fresh(),$path)['errors'])->not->toBeEmpty();
});

it('does not overwrite an active confirmed or scored exam',function($state){
    if($state==='confirmed')$this->exam->forceFill(['seats_locked_at'=>now()])->save();
    else{$this->workflow->saveRank($this->user,StudentEnrollment::findOrFail(1),1);$candidate=$this->exam->candidates()->first();$candidate->scores()->create(['subject_id'=>$this->exam->subjects()->first()->id,'score'=>90]);}
    expect(fn()=>$this->transfer->preview($this->user,$this->exam->fresh(),historyCsv($this,[[]]),true))->toThrow(ValidationException::class);
    expect($this->exam->fresh()->isHistorical())->toBeFalse();
})->with(['confirmed','scored']);

it('protects imported records against normal rank settings seat and results mutations',function(){
    historyCommit($this,historyCsv($this,[[]]));$exam=$this->exam->fresh();
    foreach([
        fn()=>$this->workflow->saveRank($this->user,StudentEnrollment::findOrFail(1),2),
        fn()=>$this->workflow->removeRank($this->user,ScholarshipStudentRank::first()),
        fn()=>$this->workflow->lockPlan($this->user,$exam,''),
        fn()=>$this->workflow->reopenPlan($this->user,$exam,$exam->version,'Test reason'),
        fn()=>$this->workflow->publish($this->user,$exam,$exam->version),
        fn()=>$this->workflow->reopenResults($this->user,$exam,$exam->version,'Test reason'),
    ] as $action)expect($action)->toThrow(ValidationException::class);
    expect($this->workflow->canScore($this->user,$exam,$exam->candidates()->first()))->toBeFalse();
});

it('exports historical results in a reimportable CSV layout',function(){
    historyCommit($this,historyCsv($this,[['score:Khmer'=>90,'score:English'=>40,'exam_rank'=>2,'award_category'=>'total_2','award_percent'=>75]]));
    $rows=iterator_to_array($this->transfer->export($this->user,$this->exam->fresh()));$row=array_combine($rows[0],$rows[1]);
    expect($row['score:English'])->toBe(40.0)->and($row['exam_rank'])->toBe(2)->and($row['award_percent'])->toBe(75.0)->and($row['award_category'])->toBe('total_2');
    $path=historyCsv($this,[$row]);expect($this->transfer->preview($this->user,$this->exam->fresh(),$path,true)['errors'])->toBeEmpty();
});

it('binds preview tokens to the user and requires acknowledgement before a single successful import',function(){
    Storage::fake('local');$path=historyCsv($this,[[]]);$file=new UploadedFile($path,'history.csv','text/csv',null,true);$controller=app(ScholarshipTransferController::class);
    $preview=$controller->preview(historyRequest($this,['exam_id'=>$this->exam->id],['file'=>$file]))->getData(true);
    expect($preview['token'])->toHaveLength(64)->and($this->exam->candidates()->count())->toBe(0);
    expect(fn()=>$controller->import(historyRequest($this,['token'=>$preview['token']])))->toThrow(ValidationException::class);
    $response=$controller->import(historyRequest($this,['token'=>$preview['token'],'acknowledge'=>1]))->getData(true);
    expect($response['message'])->toContain('1 record(s) imported')->and($this->exam->candidates()->count())->toBe(1)->and(Storage::disk('local')->files('scholarship-imports'))->toBeEmpty();
    expect(fn()=>$controller->import(historyRequest($this,['token'=>$preview['token'],'acknowledge'=>1])))->toThrow(ValidationException::class);
});

it('renders the transfer tab and historical results without normal award editing controls',function(){
    historyCommit($this,historyCsv($this,[['total_score'=>130,'exam_rank'=>2,'award_category'=>'total_2','award_percent'=>75]]));
    auth()->setUser($this->user);$request=historyRequest($this,['tab'=>'data-transfer','exam_id'=>$this->exam->id]);$data=app(ScholarshipController::class)->index($request)->getData();
    expect(view('western-scholarship._data-transfer',$data)->render())->toContain('Sample CSV','Export CSV','Validate & Preview','Replace matching records');
    expect($data['examPermissions']['awards'])->toBeFalse()->and($data['examPermissions']['seat-plan'])->toBeFalse();
    $html=view('western-scholarship._result',$data)->render();expect($html)->toContain('Historical results','75.00%')->not->toContain('Publish &amp; Lock Results','Reopen Results');
});

it('blocks unauthorized imports exports and direct sample downloads',function($denied){
    $user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->active_campus_id=1;$user->setRelation('roles',collect());
    $user->shouldReceive('isSuperAdmin')->andReturnFalse();$user->shouldReceive('hasPermission')->andReturnUsing(fn($code,$campus)=>$campus===1&&$code!==$denied);
    $path=historyCsv($this,[[]]);
    if($denied==='western-scholarship.export-data')expect(fn()=>iterator_to_array($this->transfer->export($user,$this->exam)))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    else expect(fn()=>$this->transfer->preview($user,$this->exam,$path))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    if($denied==='western-scholarship.tab-data-transfer'){
        $this->user=$user;expect(fn()=>app(ScholarshipTransferController::class)->sample(historyRequest($this)))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    }
})->with(['western-scholarship.import-history','western-scholarship.tab-data-transfer','western-scholarship.export-data']);

it('rejects a duplicate seat and a seat belonging to a student outside the file',function(){
    $path=historyCsv($this,[['room_no'=>1,'seat_no'=>1],['student_id'=>'2026002','class_rank'=>2,'room_no'=>1,'seat_no'=>1]]);
    expect($this->transfer->preview($this->user,$this->exam,$path)['errors'][0]['message'])->toContain('Duplicate center');
    historyCommit($this,historyCsv($this,[['room_no'=>1,'seat_no'=>1]]));
    $path=historyCsv($this,[['student_id'=>'2026002','class_rank'=>2,'room_no'=>1,'seat_no'=>1]]);
    expect($this->transfer->preview($this->user,$this->exam->fresh(),$path)['errors'][0]['message'])->toContain('existing candidate outside this file');
});

it('rejects unknown columns mismatched file rows and unknown subject names',function($header){
    $path=historyCsv($this,[[]]);$stream=fopen($path,'wb');fputcsv($stream,['academic_year','student_id','class_rank',$header],',','"','');fputcsv($stream,['2025-2026','2026001',1,90],',','"','');fclose($stream);
    expect(fn()=>$this->transfer->preview($this->user,$this->exam,$path))->toThrow(ValidationException::class);
})->with(['unknown','score:Missing Subject']);

it('rejects expired previews and previews from another user',function(){
    Storage::fake('local');$path=historyCsv($this,[[]]);$controller=app(ScholarshipTransferController::class);
    $data=$controller->preview(historyRequest($this,['exam_id'=>$this->exam->id],['file'=>new UploadedFile($path,'history.csv','text/csv',null,true)]))->getData(true);
    $preview=session('scholarship_import_preview');session()->put('scholarship_import_preview',array_replace($preview,['user_id'=>2]));
    expect(fn()=>$controller->import(historyRequest($this,['token'=>$data['token'],'acknowledge'=>1])))->toThrow(ValidationException::class);
    session()->put('scholarship_import_preview',array_replace($preview,['expires'=>now()->subMinute()->timestamp]));
    expect(fn()=>$controller->import(historyRequest($this,['token'=>$data['token'],'acknowledge'=>1])))->toThrow(ValidationException::class);
    expect($this->exam->candidates()->count())->toBe(0);
});

it('downloads a UTF8 sample with exactly eight data records and protects exported text from formulas',function(){
    $controller=app(ScholarshipTransferController::class);
    $response=$controller->sample(historyRequest($this,['exam_id'=>$this->exam->id]));
    ob_start();$response->sendContent();$csv=ob_get_clean();
    expect($csv)->toStartWith("\xEF\xBB\xBF")->and($response->headers->get('Content-Disposition'))->toContain('sample-8-records.csv')
        ->and(substr_count($csv,"\r\n"))->toBe(9)->and(str_replace("\r\n",'', $csv))->not->toContain("\n");
    $lines=explode("\n",trim($csv));expect($lines)->toHaveCount(9);
    expect(str_getcsv(substr($lines[0],3),',','"',''))->toContain('score:Khmer','score:English');
    historyCommit($this,historyCsv($this,[['award_category'=>'=DANGEROUS()','award_percent'=>25]]));
    $response=$controller->export(historyRequest($this),$this->exam->fresh());ob_start();$response->sendContent();$csv=ob_get_clean();
    expect($csv)->toContain("'=DANGEROUS()")->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('downloads an Excel sample with the same headers and eight records as the CSV sample',function(){
    $controller=app(ScholarshipTransferController::class);$response=$controller->sample(historyRequest($this,['exam_id'=>$this->exam->id,'format'=>'xlsx']));
    expect($response->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="scholarship-sample-8-records.xlsx"')
        ->and($response->getContent())->toStartWith('PK');
    $path=tempnam(sys_get_temp_dir(),'wis-sample-xlsx-');$this->paths[]=$path;file_put_contents($path,$response->getContent());
    $zip=new ZipArchive();expect($zip->open($path))->toBeTrue();$sheet=simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));$zip->close();
    $sheet->registerXPathNamespace('s','http://schemas.openxmlformats.org/spreadsheetml/2006/main');$rows=$sheet->xpath('//s:sheetData/s:row');
    expect($rows)->toHaveCount(9);$expected=$this->transfer->sample($this->exam);
    foreach($rows as $index=>$row){$actual=[];foreach($row->c as $cell)$actual[]=(string)(isset($cell->is)?$cell->is->t:$cell->v);expect($actual)->toBe(array_map('strval',$expected[$index]));}
});

it('rejects unsupported sample download formats',function(){
    expect(fn()=>app(ScholarshipTransferController::class)->sample(historyRequest($this,['format'=>'exe'])))->toThrow(ValidationException::class);
});

it('does not expose another campus through CSV export',function(){
    historyCommit($this,historyCsv($this,[[],['student_id'=>'2026002','class_rank'=>2]]));
    $user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->is_global=true;$user->active_campus_id=1;$user->setRelation('roles',collect());
    $user->shouldReceive('isSuperAdmin')->andReturnFalse();$user->shouldReceive('hasPermission')->andReturnUsing(fn($code,$campus)=>$campus===1);
    $rows=iterator_to_array($this->transfer->export($user,$this->exam->fresh()));expect($rows)->toHaveCount(2);
    expect(array_combine($rows[0],$rows[1])['student_id'])->toBe('2026001');
});

it('revalidates campus permission for each incoming student',function(){
    $user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->active_campus_id=1;$user->setRelation('roles',collect());
    $user->shouldReceive('isSuperAdmin')->andReturnFalse();$user->shouldReceive('hasPermission')->andReturnUsing(fn($code,$campus)=>$campus===1);
    $path=historyCsv($this,[['student_id'=>'2026002','class_rank'=>2]]);
    expect(fn()=>$this->transfer->preview($user,$this->exam,$path))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect($this->exam->candidates()->count())->toBe(0);
});

it('imports center locations and independent band and grade orders with reviewed shared changes',function(){
    DB::table('tb_student_enrollment')->where('id',3)->update(['grade_id'=>2]);
    $band=['English','Khmer'];
    $path=historyCsv($this,[
        ['exam_center'=>'North Center','exam_center_location'=>'Phnom Penh','room_no'=>1,'seat_no'=>1,'score:Khmer'=>90,'score:English'=>40,'subject_order'=>json_encode(['band'=>$band,'grade'=>null])],
        ['student_id'=>'2026002','class_rank'=>2,'exam_center'=>'Province Center','exam_center_location'=>'Battambang','room_no'=>1,'seat_no'=>1,'subject_order'=>json_encode(['band'=>$band,'grade'=>['Khmer','English']])],
    ]);
    $plan=$this->transfer->preview($this->user,$this->exam,$path);
    expect($plan['errors'])->toBeEmpty()->and($plan['report_settings']['changes'])->toHaveCount(4)
        ->and($this->exam->centers()->count())->toBe(0)->and($this->exam->fresh()->subject_orders)->toBeNull();
    historyCommit($this,$path);$exam=$this->exam->fresh();$order=app(\App\Support\ScholarshipSubjectOrder::class);
    expect($exam->centers()->pluck('location','name')->all())->toBe(['North Center'=>'Phnom Penh','Province Center'=>'Battambang'])
        ->and($order->forGrade($exam,3)->pluck('name')->all())->toBe(['English','Khmer'])
        ->and($order->forGrade($exam,4)->pluck('name')->all())->toBe(['Khmer','English'])
        ->and($order->forGrade($exam,7)->pluck('name')->all())->toBe(['Khmer','English'])
        ->and($exam->candidates()->where('student_id',1)->first()->result_total)->toBe(130.0);
    $export=iterator_to_array($this->transfer->export($this->user,$exam));$first=array_combine($export[0],$export[1]);$second=array_combine($export[0],$export[2]);
    expect($first['exam_center_location'])->toBe('Phnom Penh')->and($second['exam_center_location'])->toBe('Battambang')
        ->and(json_decode($first['subject_order'],true))->toBe(['band'=>$band,'grade'=>null])
        ->and(json_decode($second['subject_order'],true))->toBe(['band'=>$band,'grade'=>['Khmer','English']]);
    $exportPath=historyCsv($this,[$first,$second]);historyCommit($this,$exportPath,true);
    expect($exam->fresh()->subject_orders)->toBe($exam->subject_orders)->and($exam->centers()->count())->toBe(2);
});

it('keeps saved report settings for blank fields and old CSV files without the new columns',function($oldFormat){
    $center=$this->exam->centers()->create(['name'=>'Existing Center','location'=>'Kampong Cham','position'=>0]);
    $ids=$this->exam->subjects()->pluck('id')->all();$order=['bands'=>['primary'=>array_reverse($ids),'secondary'=>$ids],'grades'=>[3=>$ids]];
    $this->exam->update(['subject_orders'=>$order]);
    $path=historyCsv($this,[['exam_center'=>'existing center']]);
    if($oldFormat){
        $stream=fopen($path,'wb');fputcsv($stream,['academic_year','student_id','class_rank','exam_center'],',','"','');fputcsv($stream,['2025-2026','2026001',1,'Existing Center'],',','"','');fclose($stream);
    }
    historyCommit($this,$path);
    expect($center->fresh()->location)->toBe('Kampong Cham')->and($this->exam->fresh()->subject_orders)->toBe($order)
        ->and($this->exam->centers()->count())->toBe(1)->and($this->exam->candidates()->first()->exam_center_id)->toBe($center->id);
})->with([true,false]);

it('rejects malformed conflicting or incomplete report settings without partial writes',function($first,$second,$message){
    $path=historyCsv($this,[$first,['student_id'=>'2026002','class_rank'=>2]+$second]);
    $plan=$this->transfer->preview($this->user,$this->exam,$path);
    expect(implode(' ',array_column($plan['errors'],'message')))->toContain($message);
    expect(fn()=>$this->transfer->commit($this->user,$this->exam,$path,false,hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR))))->toThrow(ValidationException::class);
    expect($this->exam->centers()->count())->toBe(0)->and($this->exam->fresh()->subject_orders)->toBeNull()->and($this->exam->candidates()->count())->toBe(0);
})->with([
    [['exam_center_location'=>'Battambang'],[],'Provide exam_center'],
    [['exam_center'=>'Center','exam_center_location'=>str_repeat('X',81)],[],'80 characters'],
    [['exam_center'=>'Center','exam_center_location'=>'Phnom Penh'],['exam_center'=>'CENTER','exam_center_location'=>'Battambang'],'Conflicting exam_center_location'],
    [['subject_order'=>'not-json'],[],'valid JSON'],
    [['subject_order'=>'{"band":["Khmer"],"grade":null}'],[],'exactly once'],
    [['subject_order'=>'{"band":["Khmer","Khmer"],"grade":null}'],[],'exactly once'],
    [['subject_order'=>'{"band":["Unknown","English"],"grade":null}'],[],'ineligible subject'],
    [['subject_order'=>'{"band":["Khmer","English"],"grade":["English"]}'],[],'exactly once'],
    [['subject_order'=>'{"band":["Khmer","English"],"grade":null}'],['subject_order'=>'{"band":["English","Khmer"],"grade":null}'],'Conflicting subject_order band'],
    [['subject_order'=>'{"band":["Khmer","English"],"grade":null}'],['subject_order'=>'{"band":["Khmer","English"],"grade":["English","Khmer"]}'],'Conflicting subject_order overrides'],
]);

it('requires explicit replacement before changing shared settings of an existing archive',function(){
    historyCommit($this,historyCsv($this,[['exam_center'=>'Center','exam_center_location'=>'Phnom Penh']]));
    $path=historyCsv($this,[['student_id'=>'2026002','class_rank'=>2,'exam_center'=>'Center','exam_center_location'=>'Battambang','subject_order'=>'{"band":["English","Khmer"],"grade":null}']]);
    $plan=$this->transfer->preview($this->user,$this->exam->fresh(),$path);
    expect(implode(' ',array_column($plan['errors'],'message')))->toContain('Enable Replace Matching Records')
        ->and($plan['report_settings']['changes'])->toHaveCount(2);
    historyCommit($this,$path,true);
    expect($this->exam->centers()->first()->location)->toBe('Battambang')->and($this->exam->candidates()->count())->toBe(2);
});

it('rejects a stale preview when shared location or exam order changes',function($field){
    $center=$this->exam->centers()->create(['name'=>'Center','location'=>'Phnom Penh','position'=>0]);
    $path=historyCsv($this,[['exam_center'=>'Center','exam_center_location'=>'Battambang','subject_order'=>'{"band":["English","Khmer"],"grade":null}']]);
    $plan=$this->transfer->preview($this->user,$this->exam,$path);expect($plan['errors'])->toBeEmpty();
    if($field==='location')$center->update(['location'=>'Kampong Cham']);
    else{$state=app(\App\Support\ScholarshipSubjectOrder::class)->state($this->exam);$state['bands']['primary']=array_reverse($state['bands']['primary']);$this->exam->update(['subject_orders'=>$state]);}
    expect(fn()=>$this->transfer->commit($this->user,$this->exam,$path,false,hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR))))->toThrow(ValidationException::class);
    expect($this->exam->candidates()->count())->toBe(0);
})->with(['location','order']);

it('round trips portable subject references when bands reuse names in different grade ranges',function(){
    $this->exam->subjects()->update(['grade_to'=>6]);
    foreach(['Khmer','English'] as $i=>$name)$this->exam->subjects()->create(['name'=>$name,'grade_from'=>7,'grade_to'=>12,'max_score'=>100,'weight'=>1,'position'=>$i+2]);
    $this->exam->refresh();$helper=app(\App\Support\ScholarshipTransferSettings::class);
    $json=$helper->exportOrder($this->exam,3);$decoded=json_decode($json,true);
    expect($decoded['band'][0])->toBe(['name'=>'Khmer','grade_from'=>1,'grade_to'=>6]);
    $plan=$this->transfer->preview($this->user,$this->exam,historyCsv($this,[['subject_order'=>$json]]));expect($plan['errors'])->toBeEmpty();
    $decoded['band']=['Khmer','English'];
    expect($this->transfer->preview($this->user,$this->exam,historyCsv($this,[['subject_order'=>json_encode($decoded)]]))['errors'])->toBeEmpty();
});

it('shows center examples and a portable order in both updated sample downloads',function(){
    $sample=$this->transfer->sample(null);$rows=collect(array_slice($sample,1))->map(fn($row)=>array_combine($sample[0],$row));
    expect($sample[0])->toContain('exam_center_location','subject_order')
        ->and($rows->pluck('exam_center_location')->unique()->values()->all())->toBe(['Phnom Penh','Battambang']);
    foreach($rows as $row)expect(json_decode($row['subject_order'],true))->toHaveKeys(['band','grade']);
    $controller=app(ScholarshipTransferController::class);$path=historyCsv($this,[['exam_center'=>'Center','exam_center_location'=>'Battambang','subject_order'=>'{"band":["English","Khmer"],"grade":null}']]);
    $response=$controller->preview(historyRequest($this,['exam_id'=>$this->exam->id],['file'=>new UploadedFile($path,'settings.csv','text/csv',null,true)]))->getData(true);
    expect($response['settings_changes'])->toHaveCount(2)->and($response['token'])->toHaveLength(64);
    $stored=session('scholarship_import_preview');Storage::disk('local')->delete($stored['path']);
});

it('restores portable subject orders into a configured year with different subject IDs',function(){
    $json='{"band":["English","Khmer"],"grade":["Khmer","English"]}';
    historyCommit($this,historyCsv($this,[['exam_center'=>'Province Center','exam_center_location'=>'Battambang','subject_order'=>$json,'score:Khmer'=>90,'score:English'=>40]]));
    $export=iterator_to_array($this->transfer->export($this->user,$this->exam->fresh()));$row=array_combine($export[0],$export[1]);
    $sourceIds=$this->exam->subjects()->pluck('id')->all();
    $this->exam=$this->workflow->saveExam($this->user,null,['academic_year_id'=>2,'exam_date'=>'2027-11-20','room_capacity'=>25,'version'=>1,'subjects'=>[
        ['name'=>'Khmer','grade_from'=>1,'grade_to'=>12,'max_score'=>100,'weight'=>1],['name'=>'English','grade_from'=>1,'grade_to'=>12,'max_score'=>50,'weight'=>3],
    ]]);
    $row=array_replace($row,['academic_year'=>'2026-2027','grade'=>4,'class'=>'B','group'=>'']);
    historyCommit($this,historyCsv($this,[$row]));$exam=$this->exam->fresh();
    expect(array_intersect($sourceIds,$exam->subjects()->pluck('id')->all()))->toBeEmpty()
        ->and($exam->subject_orders['grades'][4])->toBe($exam->subjects()->pluck('id')->all())
        ->and(app(\App\Support\ScholarshipSubjectOrder::class)->forGrade($exam,5)->pluck('name')->all())->toBe(['English','Khmer'])
        ->and($exam->centers()->first()->location)->toBe('Battambang')->and($exam->candidates()->first()->result_total)->toBe(130.0);
});
