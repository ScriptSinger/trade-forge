<?php

use App\Jobs\TestQueueJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('bot:run')->everyMinute();

Schedule::command('bot:z-report')
    ->dailyAt(config('trading.z_report.time', '05:05'))
    ->timezone(config('trading.z_report.timezone', config('app.timezone')));
