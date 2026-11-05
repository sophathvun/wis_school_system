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
    $this->controller=app(G9NationalExamController::class);
    $this->input=['academic_year_id'=>1,'campus_id'=>1,'student_code'=>'000123','name_en'=>'G9 Candidate',
        'name_kh'=>"\u{179F}\u{17B7}\u{179F}\u{17D2}\u{179F}",'gender'=>'Female','nationality'=>'Cambodian','date_of_birth'=>'2011-05-12',
        'place_of_birth'=>'Phnom Penh','foreign_language'=>'English','father_name'=>'Father','father_occupation'=>'Teacher',
        'mother_name'=>'Mother','mother_occupation'=>'Doctor','exam_center'=>'Test Center','exam_date'=>'2026-08-10',
        'room_number'=>'01','seat_number'=>'005','school_name'=>'Western International School','grade'=>'9A',
        'phone_number'=>'012345678','exam_language'=>'Khmer','semester_1'=>'89.500','semester_2'=>'90.500','eoy_average'=>'90.000'];
    foreach(G9ExamFields::GROUPS['National Examination Scores'] as $key=>$label)$this->input[$key]='42.500';
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
    $permissions=DB::table('access_permissions')->whereIn('code',['g9-national-exams.view','g9-national-exams.create'])->pluck('id');
    foreach($permissions as $permission)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>$permission,'allowed'=>true]);
    expect($this->controller->listing(g12Request($user))->getData(true)['total'])->toBe(1);
    expect($this->controller->options(g12Request($user,['academic_year'=>1]))->getData(true)['exam_center'])->toBe(['Test Center']);
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
    foreach(['view'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $view=$this->controller->index(g12Request($user))->getData();
    expect($view['filterYears']->pluck('id')->all())->toBe([1])->and($view['years'])->toHaveCount(3);
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
    foreach(['view','edit'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    foreach(range(1,20) as $i)$this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'PERF'.$i])));
    $count=function($size)use($user){DB::flushQueryLog();DB::enableQueryLog();$response=$this->controller->listing(g12Request($user,['per_page'=>$size]));$queries=count(DB::getQueryLog());DB::disableQueryLog();return [$queries,$response->getData(true)];};
    [$small]=$count('15');[$large,$result]=$count('all');
    expect($large)->toBeLessThanOrEqual($small+1)->and($result['data'])->toHaveCount(20);
    DB::table('access_user_permission_overrides')->where('user_id',2)->delete();
    expect(fn()=>$this->controller->listing(g12Request(User::find(2))))->toThrow(HttpException::class);
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
    foreach(['view','import','export','create','edit'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
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
        if($type==='profile')continue;
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

it('requires an academic year and export permission and keeps report records campus scoped',function(){
    $admin=User::find(1);$user=User::find(2);$report=app(\App\Support\G9CandidateReport::class);
    $this->controller->save(g12Request($admin,$this->input));
    $this->controller->save(g12Request($admin,array_replace($this->input,['student_code'=>'PRIVATE','campus_id'=>2,'exam_center'=>'Other Campus Secret'])));
    expect(fn()=>$this->controller->report(g12Request($admin),'candidates','print',$report))->toThrow(ValidationException::class);
    foreach(['view','export'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $html=$this->controller->report(g12Request($user,['academic_year'=>1]),'candidates','print',$report)->getContent();
    expect($html)->toContain('Test Center')->not->toContain('Other Campus Secret');
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
