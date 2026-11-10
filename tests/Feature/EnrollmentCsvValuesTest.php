<?php

use App\Services\EnrollmentCsvValues;
use App\Http\Controllers\StudentDataTransferController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Http\{Request,UploadedFile};

it('renders import feedback without breaking Blade sections or script stacks',function(){
    $this->withoutVite();
    $source=str_replace("@extends('layouts.app')",'',file_get_contents(resource_path('views/student-data-transfer.blade.php')));
    $source.="\n@yield('content')\n@stack('scripts')";
    session()->put('enrollment_failed_rows',['count'=>2]);
    foreach([null,['type'=>'enrollments','total'=>2,'imported'=>1,'failed'=>[['line'=>3,'identifier'=>'MISSING','fields'=>[],'reason'=>'Student not found','data'=>[]]]],['type'=>'enrollments','total'=>1,'imported'=>1,'failed'=>[]]] as $report){
        session()->forget('import_report');
        if($report)session()->put('import_report',$report);
        $html=\Illuminate\Support\Facades\Blade::render($source,['errors'=>new \Illuminate\Support\ViewErrorBag()]);
        expect($html)->toContain('data-csv-import','data-csv-dropzone','data-csv-progress','Download Failed Rows CSV',route('student-data-transfer.failed-rows','enrollments'))->not->toContain('@push','@endpush','@endif');
        expect(substr_count($html,'data-csv-import '))->toBe(3);
        if($report)expect($html)->toContain('import-result',$report['imported']===1 && count($report['failed']) ? 'Import Needs Review' : 'Enrollment Import Complete');
    }
    session()->forget('import_report');
    session()->forget('enrollment_failed_rows');
});

beforeEach(function () {
    foreach (['academic_year'=>['academic_year','academic_year_code'],'school_info'=>['campus_name_en','campus_name_kh','school_name_en','school_name_kh'],'grade'=>['grade','grade_short_name'],'session'=>['session_name','session_short_name']] as $table=>$columns) {
        Schema::create('tb_'.$table,function(Blueprint $t)use($columns){$t->id();foreach($columns as $column)$t->string($column)->nullable();$t->softDeletes();});
    }
    Schema::create('tb_class',function(Blueprint $t){$t->id();$t->string('class_name');foreach(['academic_year_id','grade_id','session_id'] as $column)$t->integer($column)->nullable();$t->softDeletes();});
    Schema::create('tb_group',function(Blueprint $t){$t->id();$t->string('group_name');$t->integer('class_id')->nullable();$t->softDeletes();});
    Schema::create('tb_student',function(Blueprint $t){$t->id();$t->string('student_id');});
    Schema::create('tb_academic_track',function(Blueprint $t){$t->id();$t->integer('grade_id')->nullable();foreach(['code','name_en','name_kh'] as $c)$t->string($c)->nullable();$t->softDeletes();});
    Schema::create('tb_student_enrollment',function(Blueprint $t){$t->id();foreach(['student_id','academic_year_id','campus_id','grade_id','class_id','session_id','group_id'] as $c)$t->integer($c)->nullable();foreach(['student_type','enrollment_status','exit_reason','notes'] as $c)$t->string($c)->nullable();$t->date('enrolled_on')->nullable();$t->date('ended_on')->nullable();$t->boolean('status')->default(true);$t->timestamps();$t->unique(['student_id','academic_year_id']);});
    DB::table('tb_academic_year')->insert([['id'=>1,'academic_year'=>'2019-2020'],['id'=>2,'academic_year'=>'2020-2021']]);
    DB::table('tb_school_info')->insert(['id'=>1,'campus_name_en'=>'BCH']);
    DB::table('tb_grade')->insert([['id'=>1,'grade'=>'Grade 10','grade_short_name'=>'10'],['id'=>2,'grade'=>'K1','grade_short_name'=>'K1'],['id'=>3,'grade'=>'Nursery','grade_short_name'=>'Nursery']]);
    DB::table('tb_session')->insert([['id'=>1,'session_name'=>'Morning','session_short_name'=>'M'],['id'=>2,'session_name'=>'Afternoon','session_short_name'=>'A'],['id'=>3,'session_name'=>'Full-Day','session_short_name'=>'III']]);
    DB::table('tb_class')->insert([['id'=>1,'class_name'=>'A'],['id'=>2,'class_name'=>'B']]);
    DB::table('tb_student')->insert(['id'=>1,'student_id'=>'00123']);
    Schema::table('tb_student_enrollment',fn(Blueprint $t)=>$t->integer('academic_track_id')->nullable());
    DB::table('tb_academic_track')->insert([['id'=>1,'grade_id'=>1,'code'=>'SCI','name_en'=>'Science','name_kh'=>'វិទ្យាសាស្ត្រ'],['id'=>2,'grade_id'=>2,'code'=>'SCI','name_en'=>'Science','name_kh'=>'វិទ្យាសាស្ត្រ']]);
    $this->row=['student_id'=>'00123','academic_year_id'=>'2019-2020','campus_id'=>'BCH','grade_id'=>'Grade 10','class_id'=>'10A','session_id'=>'Morning','group_id'=>'Morning','student_type'=>'Old','enrolled_on'=>'9/1/2019','ended_on'=>'6/30/2020'];
});

it('archives complete rejected enrollment rows and allows correction and retry without exporting successful rows',function(){
    \Illuminate\Support\Facades\Storage::fake('local');
    $user=new \App\Models\User();$user->id=7;
    $columns=['student_id','academic_year_id','campus_id','grade_id','class_id','academic_track_id','session_id','group_id','student_type','enrollment_status','enrolled_on','ended_on','exit_reason','notes','status'];
    $good=array_replace($this->row,['academic_track_id'=>'SCI','enrollment_status'=>'completed','exit_reason'=>'Completed academic year','notes'=>'Successful row','status'=>'1']);
    $bad=array_replace($good,['class_id'=>'12A','notes'=>'Original "quoted", ភ្នំពេញ note','enrollment_status'=>'withdrawn','exit_reason'=>'Family relocation']);
    $makeCsv=function($columns,$rows){$f=fopen('php://temp','w+');fputcsv($f,$columns);foreach($rows as $row)fputcsv($f,array_map(fn($c)=>$row[$c]??'',$columns));rewind($f);$csv=stream_get_contents($f);fclose($f);return $csv;};
    $request=Request::create('/students/data-transfer/enrollments/import','POST',[],[],['file'=>UploadedFile::fake()->createWithContent('enrollments.csv',$makeCsv($columns,[$good,$bad]))]);
    $request->setLaravelSession(app('session')->driver());app()->instance('request',$request);$request->setUserResolver(fn()=>$user);
    $controller=new StudentDataTransferController();$controller->import($request,'enrollments');
    expect(session('import_report.imported'))->toBe(1)->and(session('import_report.failed'))->toHaveCount(1);
    $report=session('enrollment_failed_rows');
    expect($report['count'])->toBe(1);
    \Illuminate\Support\Facades\Storage::disk('local')->assertExists($report['path']);
    // The download is not tied to the one-request result flash.
    session()->forget('import_report');
    $response=$controller->failedRows($request,'enrollments');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    ob_start();$response->sendContent();$download=ob_get_clean();
    $f=fopen('php://temp','w+');fwrite($f,$download);rewind($f);$headers=fgetcsv($f);$headers[0]=preg_replace('/^\xEF\xBB\xBF/','',$headers[0]);$values=fgetcsv($f);expect(fgetcsv($f))->toBeFalse();fclose($f);
    $rejected=array_combine($headers,$values);
    foreach($columns as $column)expect($rejected[$column])->toBe((string)($bad[$column]??''));
    expect($rejected['import_csv_row'])->toBe('3')->and($rejected['import_error'])->toContain('does not match');
    $other=new \App\Models\User();$other->id=8;$request->setUserResolver(fn()=>$other);
    try{$controller->failedRows($request,'enrollments');$this->fail('Another user must not download rejected rows');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){expect($e->getStatusCode())->toBe(403);}
    $rejected['class_id']='10A';
    $retry=Request::create('/students/data-transfer/enrollments/import','POST',[],[],['file'=>UploadedFile::fake()->createWithContent('corrected.csv',$makeCsv($headers,[$rejected]))]);
    $retry->setLaravelSession(app('session')->driver());app()->instance('request',$retry);$retry->setUserResolver(fn()=>$user);
    $controller->import($retry,'enrollments');
    expect(session('import_report.imported'))->toBe(1)->and(session('import_report.failed'))->toBe([])->and(session('enrollment_failed_rows'))->toBeNull();
    $this->assertDatabaseHas('tb_student_enrollment',['student_id'=>1,'notes'=>$bad['notes'],'academic_track_id'=>1,'enrollment_status'=>'withdrawn']);
    \Illuminate\Support\Facades\Storage::disk('local')->assertExists($report['path']);
    try{$controller->failedRows($retry,'enrollments');$this->fail('A cleared download should return 404');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){expect($e->getStatusCode())->toBe(404);}
});

it('accepts the template class label, legacy repeated session, and Excel dates',function(){
    $before=DB::table('tb_class')->count();
    $values=(new EnrollmentCsvValues())->resolve($this->row);
    expect($values)->toMatchArray(['class_id'=>1,'group_id'=>null,'session_id'=>1,'enrolled_on'=>'2019-09-01','ended_on'=>'2020-06-30','student_type'=>'old','enrollment_status'=>'active','status'=>1]);
    expect(DB::table('tb_class')->count())->toBe($before);
    foreach([['K1','K1-A'],['Nursery','N-A']] as [$grade,$class])expect((new EnrollmentCsvValues())->resolve(array_replace($this->row,['grade_id'=>$grade,'class_id'=>$class]))['class_id'])->toBe(1);
});

it('reports actual successful and rejected row counts during a JSON enrollment import',function(){
    \Illuminate\Support\Facades\Storage::fake('local');$snapshots=[];
    \Illuminate\Support\Facades\Cache::shouldReceive('add')->once()->andReturnTrue();
    \Illuminate\Support\Facades\Cache::shouldReceive('put')->andReturnUsing(function($key,$value)use(&$snapshots){$snapshots[]=$value;return true;});
    $columns=array_keys($this->row);$stream=fopen('php://temp','w+');fputcsv($stream,$columns);
    for($i=0;$i<50;$i++){ $row=$i<20?$this->row:array_replace($this->row,['student_id'=>'MISSING']);fputcsv($stream,array_values($row)); }
    rewind($stream);$csv=stream_get_contents($stream);fclose($stream);
    $request=Request::create('/students/data-transfer/enrollments/import','POST',['progress_token'=>(string)\Illuminate\Support\Str::uuid()],[],['file'=>UploadedFile::fake()->createWithContent('data.csv',$csv)],['HTTP_ACCEPT'=>'application/json']);
    $request->setLaravelSession(app('session')->driver());app()->instance('request',$request);
    $user=new \App\Models\User();$user->id=7;$request->setUserResolver(fn()=>$user);
    $response=(new StudentDataTransferController())->import($request,'enrollments');
    expect($snapshots[1])->toMatchArray(['processed'=>25,'percent'=>50,'imported'=>20,'failed'=>5]);
    expect($response->getData(true))->toMatchArray(['total'=>50,'imported'=>20,'failed'=>30,'redirect'=>route('student-data-transfer.index')]);
    expect(session('enrollment_failed_rows.count'))->toBe(30)->and(DB::table('tb_student_enrollment')->count())->toBe(1);
});

it('prefers the most specific compatible class and refuses cross-placement IDs',function(){
    DB::table('tb_class')->insert([['id'=>3,'class_name'=>'A','academic_year_id'=>1,'grade_id'=>1,'session_id'=>1],['id'=>4,'class_name'=>'A','academic_year_id'=>2,'grade_id'=>1,'session_id'=>1]]);
    expect((new EnrollmentCsvValues())->resolve($this->row)['class_id'])->toBe(3);
    expect(fn()=>(new EnrollmentCsvValues())->resolve(array_replace($this->row,['class_id'=>'4'])))->toThrow(RuntimeException::class,'selected academic year');
    expect(fn()=>(new EnrollmentCsvValues())->resolve(array_replace($this->row,['grade_id'=>'K1'])))->toThrow(RuntimeException::class,'does not match');
    expect((new EnrollmentCsvValues())->resolve(array_replace($this->row,['class_id'=>'3']))['class_id'])->toBe(3);
});

it('does not guess ambiguous classes or use deleted class records',function(){
    DB::table('tb_class')->insert(['id'=>3,'class_name'=>'A']);
    expect(fn()=>(new EnrollmentCsvValues())->resolve($this->row))->toThrow(RuntimeException::class,'ambiguous');
    DB::table('tb_class')->where('id',3)->update(['deleted_at'=>now()]);
    expect((new EnrollmentCsvValues())->resolve($this->row)['class_id'])->toBe(1);
});

it('keeps real groups scoped to their class and rejects unknown group names',function(){
    DB::table('tb_group')->insert([['id'=>1,'class_id'=>1,'group_name'=>'Morning'],['id'=>2,'class_id'=>2,'group_name'=>'Other']]);
    expect((new EnrollmentCsvValues())->resolve($this->row)['group_id'])->toBe(1);
    expect(fn()=>(new EnrollmentCsvValues())->resolve(array_replace($this->row,['group_id'=>'Other'])))->toThrow(RuntimeException::class,'selected class');
    expect(fn()=>(new EnrollmentCsvValues())->resolve(array_replace($this->row,['group_id'=>'Unknown'])))->toThrow(RuntimeException::class,'Group is optional');
    expect((new EnrollmentCsvValues())->resolve(array_replace($this->row,['group_id'=>'']))['group_id'])->toBeNull();
});

it('rejects invalid dates and missing numeric reference IDs',function(){
    expect(fn()=>(new EnrollmentCsvValues())->resolve(array_replace($this->row,['enrolled_on'=>'2/30/2020'])))->toThrow(RuntimeException::class,'not a valid date');
    expect(fn()=>(new EnrollmentCsvValues())->resolve(array_replace($this->row,['session_id'=>'999'])))->toThrow(RuntimeException::class,"Session '999' was not found");
});

it('imports BOM-prefixed template CSV and updates a yearly enrollment on retry',function(){
    $controller=new StudentDataTransferController();
    ob_start();$controller->template('enrollments')->sendContent();$template=ob_get_clean();
    $file=fopen('php://temp','r+');fwrite($file,$template);rewind($file);
    $columns=fgetcsv($file);$columns[0]=ltrim($columns[0],"\xEF\xBB\xBF");
    $example=array_combine($columns,fgetcsv($file));fclose($file);
    expect($example)->toMatchArray(['enrollment_status'=>'completed','status'=>'1','academic_track_id'=>'']);
    $row=array_replace($this->row,['academic_track_id'=>'SCI','enrollment_status'=>'withdrawn','status'=>'1','exit_reason'=>'Family relocation','notes'=>'Historical withdrawal']);
    $file=fopen('php://temp','r+');fwrite($file,"\xEF\xBB\xBF");fputcsv($file,$columns);fputcsv($file,array_map(fn($c)=>$row[$c] ?? '',$columns));rewind($file);$csv=stream_get_contents($file);fclose($file);
    foreach([1,2] as $attempt){
        $request=Request::create('/student-data-transfer/import/enrollments','POST',[],[],['file'=>UploadedFile::fake()->createWithContent('enrollment.csv',$csv)]);
        $request->setLaravelSession(app('session')->driver());app()->instance('request',$request);
        $controller->import($request,'enrollments');
        expect(session('import_report')['imported'])->toBe(1)->and(session('import_report')['failed'])->toBe([]);
    }
    expect(DB::table('tb_student_enrollment')->count())->toBe(1);
    $this->assertDatabaseHas('tb_student_enrollment',['student_id'=>1,'class_id'=>1,'academic_track_id'=>1,'group_id'=>null,'student_type'=>'old','enrollment_status'=>'withdrawn','status'=>1,'exit_reason'=>'Family relocation']);
    expect(\App\Models\StudentEnrollment::first()->enrolled_on->format('Y-m-d'))->toBe('2019-09-01');
});

it('resolves tracks by configured code name or ID within the grade and preserves omitted tracks',function(){
    foreach(['SCI','Science','វិទ្យាសាស្ត្រ','1'] as $track) {
        expect((new EnrollmentCsvValues())->resolve(array_replace($this->row,['academic_track_id'=>$track]))['academic_track_id'])->toBe(1);
    }
    foreach(['2','UNKNOWN'] as $track) {
        expect(fn()=>(new EnrollmentCsvValues())->resolve(array_replace($this->row,['academic_track_id'=>$track])))->toThrow(RuntimeException::class,'Academic Track');
    }
    $controller=new StudentDataTransferController();$method=new ReflectionMethod($controller,'importRow');
    $method->invoke($controller,'enrollments',array_replace($this->row,['academic_track_id'=>'SCI']),2);
    $method->invoke($controller,'enrollments',$this->row,2);
    $this->assertDatabaseHas('tb_student_enrollment',['student_id'=>1,'academic_track_id'=>1]);
    $method->invoke($controller,'enrollments',array_replace($this->row,['academic_track_id'=>'']),2);
    $this->assertDatabaseHas('tb_student_enrollment',['student_id'=>1,'academic_track_id'=>null]);
    DB::table('tb_academic_track')->where('id',1)->update(['deleted_at'=>now()]);
    expect(fn()=>(new EnrollmentCsvValues())->resolve(array_replace($this->row,['academic_track_id'=>'1'])))->toThrow(RuntimeException::class,'Academic Track');
});

it('reports an incompatible placement without changing an existing enrollment',function(){
    $controller=new StudentDataTransferController();
    $method=new ReflectionMethod($controller,'importRow');
    $method->invoke($controller,'enrollments',$this->row,2);
    $before=DB::table('tb_student_enrollment')->first();
    expect(fn()=>$method->invoke($controller,'enrollments',array_replace($this->row,['grade_id'=>'K1']),3))->toThrow(RuntimeException::class,'does not match');
    expect((array)DB::table('tb_student_enrollment')->first())->toBe((array)$before);
    expect(fn()=>$method->invoke($controller,'enrollments',array_replace($this->row,['student_id'=>'MISSING']),4))->toThrow(RuntimeException::class,'Student Information');
    expect(DB::table('tb_student_enrollment')->count())->toBe(1);
});
