<?php

use App\Http\Controllers\StudentDataTransferController;
use Illuminate\Http\{Request,UploadedFile};
use Illuminate\Support\Facades\{Cache,Storage,URL};
use Illuminate\Support\Str;

it('publishes real intermediate and final row counts for every CSV import type',function(string $type,array $columns){
    Storage::fake('local');
    $snapshots=[];
    Cache::shouldReceive('add')->once()->andReturnTrue();
    Cache::shouldReceive('put')->andReturnUsing(function($key,$value)use(&$snapshots){$snapshots[]=$value;return true;});
    $stream=fopen('php://temp','w+');fputcsv($stream,$columns);
    for($i=0;$i<50;$i++)fputcsv($stream,array_fill(0,count($columns),'partial'));
    // Remove a required value without making the row empty; no database writes should occur.
    rewind($stream);$csv=stream_get_contents($stream);fclose($stream);
    $csv=str_replace('partial,partial',',partial',$csv);
    $csv.="\n,\n";
    $request=Request::create('/students/data-transfer/'.$type.'/import','POST',['progress_token'=>(string)Str::uuid()],[],['file'=>UploadedFile::fake()->createWithContent('data.csv',$csv)],['HTTP_ACCEPT'=>'application/json']);
    $request->setLaravelSession(app('session')->driver());app()->instance('request',$request);
    $user=new \App\Models\User();$user->id=7;$request->setUserResolver(fn()=>$user);
    $response=(new StudentDataTransferController())->import($request,$type);
    expect($response->getStatusCode())->toBe(200)->and($response->getData(true))->toMatchArray(['total'=>50,'imported'=>0,'failed'=>50]);
    expect(array_column($snapshots,'percent'))->toBe([0,50,100,100]);
    expect($snapshots[1])->toMatchArray(['processed'=>25,'total'=>50,'failed'=>25,'imported'=>0,'phase'=>'processing']);
    expect(end($snapshots))->toMatchArray(['phase'=>'complete','processed'=>50,'failed'=>50]);
    expect(session('import_report.failed'))->toHaveCount(50);
})->with([
    ['students',['student_id','full_name_en']],
    ['enrollments',['student_id','academic_year_id','campus_id','grade_id','class_id']],
    ['families',['family_number','relationship_type','full_name_en']],
]);

it('returns signed progress without session cookies or student details and rejects forged expired or wrong-owner URLs',function(){
    $token=(string)Str::uuid();
    Cache::put('student-csv-progress:'.$token,['user_id'=>7,'phase'=>'processing','processed'=>25,'total'=>50,'imported'=>20,'failed'=>5,'percent'=>50],now()->addHour());
    $url=URL::temporarySignedRoute('student-data-transfer.progress',now()->addHour(),['token'=>$token,'user'=>7]);
    $this->getJson($url)->assertOk()->assertExactJson(['phase'=>'processing','processed'=>25,'total'=>50,'imported'=>20,'failed'=>5,'percent'=>50])->assertHeaderMissing('Set-Cookie');
    $this->getJson(str_replace('user=7','user=8',$url))->assertForbidden();
    $wrong=URL::temporarySignedRoute('student-data-transfer.progress',now()->addHour(),['token'=>$token,'user'=>8]);
    $this->getJson($wrong)->assertNotFound();
    $this->travel(61)->minutes();$this->getJson($url)->assertForbidden();
});

it('rejects reusing an already-started progress token before processing rows',function(){
    $token=(string)Str::uuid();Cache::put('student-csv-progress:'.$token,['phase'=>'processing','user_id'=>7],now()->addHour());
    $request=Request::create('/students/data-transfer/students/import','POST',['progress_token'=>$token],[],['file'=>UploadedFile::fake()->createWithContent('data.csv',"student_id,full_name_en\n00123,Example\n")],['HTTP_ACCEPT'=>'application/json']);
    $request->setLaravelSession(app('session')->driver());app()->instance('request',$request);
    $response=(new StudentDataTransferController())->import($request,'students');
    expect($response->getStatusCode())->toBe(409)->and(session('import_report'))->toBeNull();
});

it('returns JSON validation errors without starting progress for missing CSV columns',function(){
    $token=(string)Str::uuid();
    $request=Request::create('/students/data-transfer/enrollments/import','POST',['progress_token'=>$token],[],['file'=>UploadedFile::fake()->createWithContent('data.csv',"student_id\n00123\n")],['HTTP_ACCEPT'=>'application/json']);
    $request->setLaravelSession(app('session')->driver());app()->instance('request',$request);
    $response=(new StudentDataTransferController())->import($request,'enrollments');
    expect($response->getStatusCode())->toBe(422)->and($response->getData(true)['message'])->toContain('Missing required columns')->and(Cache::has('student-csv-progress:'.$token))->toBeFalse();
});
