<?php

// Exercise the real download response with isolated data, without opening the app database.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);
\Illuminate\Support\Facades\DB::purge('sqlite');
require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
createScholarshipSchema();
$user=Mockery::mock(\App\Models\User::class)->makePartial();$user->id=1;
$user->shouldReceive('isSuperAdmin')->andReturnTrue();
$exam=\App\Models\ScholarshipExam::create(['academic_year_id'=>1,'name'=>'2025-2026','exam_date'=>'2026-11-20','room_capacity'=>25,'campus_ids'=>[],'eligible_ranks'=>[]]);
foreach(['Khmer','Math','Science','English'] as $position=>$name)$exam->subjects()->create(['name'=>$name,'grade_from'=>1,'grade_to'=>12,'max_score'=>100,'weight'=>1,'position'=>$position]);
$request=\Illuminate\Http\Request::create('/students/western-scholarship/transfer/sample','GET',['exam_id'=>$exam->id,'format'=>$argv[1]??'csv']);
$request->setUserResolver(fn()=>$user);
$response=app(\App\Http\Controllers\ScholarshipTransferController::class)->sample($request);
if($response instanceof \Symfony\Component\HttpFoundation\StreamedResponse){ob_start();$response->sendContent();$bytes=ob_get_clean();}else $bytes=$response->getContent();
echo json_encode(['headers'=>$response->headers->all(),'body'=>base64_encode($bytes)],JSON_THROW_ON_ERROR);
