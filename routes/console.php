<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

//Schedule::call(static function () {
////    File::put(storage_path('logs/'.date('YmdHis')).'.txt', now());
//})->everySecond();

//Schedule::command('rep:pendency')->everyTwoMinutes()->withoutOverlapping();
Schedule::command('sync:run')->dailyAt('22:00');


Schedule::call(static function () {
    Artisan::call('rep:clear', [
        '--devices' => env('DEVICE_MASTER_REP'),
    ]);
    Artisan::call('rep:reload');
    Artisan::call('rep:send', [
        '--devices'   => env('DEVICE_MASTER_REP'),
        '--with-slow' => true,
    ]);
    Artisan::call('rep:master');
})->dailyAt('20:00');
