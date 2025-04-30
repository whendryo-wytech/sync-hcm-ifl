<?php

use Illuminate\Support\Facades\Schedule;

//Schedule::call(static function () {
////    File::put(storage_path('logs/'.date('YmdHis')).'.txt', now());
//})->everySecond();

Schedule::command('rep:pendency')->everyTwoMinutes()->withoutOverlapping();
Schedule::command('sync:run')->dailyAt('22:00');
