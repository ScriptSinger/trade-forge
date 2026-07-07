<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bot:run')
    ->everyFifteenSeconds()
    ->when(fn (): bool => (int) config('trading.bot.cycle_interval_seconds', 15) === 15);

Schedule::command('bot:run')
    ->everyThirtySeconds()
    ->when(fn (): bool => (int) config('trading.bot.cycle_interval_seconds', 15) === 30);

Schedule::command('bot:run')
    ->everyMinute()
    ->when(fn (): bool => (int) config('trading.bot.cycle_interval_seconds', 15) === 60);

Schedule::command('bot:z-report')
    ->dailyAt(config('trading.z_report.time', '05:05'))
    ->timezone(config('trading.z_report.timezone', config('app.timezone')));
