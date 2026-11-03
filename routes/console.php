<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('attendance:apply-leave',function(){
    app(\App\Services\AttendanceLeave::class)->applyDue();
    $this->info('Due approved leave applied without replacing recorded attendance.');
})->purpose('Apply approved leave to due daily attendance registers');
Schedule::command('attendance:apply-leave')->dailyAt('00:05')->timezone('Asia/Bangkok')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
