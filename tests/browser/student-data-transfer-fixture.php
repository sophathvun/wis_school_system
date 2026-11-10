<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$source=str_replace("@extends('layouts.app')",'',file_get_contents(resource_path('views/student-data-transfer.blade.php')));
$source=preg_replace('/@vite\([^\n]*\)/','',$source);
$source.='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/node_modules/@tabler/core/dist/css/tabler.min.css"><link rel="stylesheet" href="/node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css"><link rel="stylesheet" href="/node_modules/sweetalert2/dist/sweetalert2.min.css"><link rel="stylesheet" href="/resources/css/pages/student-data-transfer.css"></head><body><main class="container-xl py-4">@yield("content")</main><script src="/node_modules/sweetalert2/dist/sweetalert2.all.min.js"></script>@stack("scripts")<script>window.errors=[];addEventListener("error",e=>errors.push(e.message));</script><script type="module" src="/resources/js/studentDataTransfer.js"></script></body></html>';
$html=Illuminate\Support\Facades\Blade::render($source,['errors'=>new Illuminate\Support\ViewErrorBag()]);
$dir=storage_path('app/testing/student-data-transfer');if(!is_dir($dir))mkdir($dir,0750,true);
file_put_contents($dir.'/page.html',$html);file_put_contents($dir.'/sample.csv',"student_id,academic_year_id\n00123,2025-2026\n");echo "Browser fixture ready\n";
