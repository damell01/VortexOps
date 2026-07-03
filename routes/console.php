<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('db:backup')->dailyAt('02:00');
Schedule::command('health:check --notify')->everyFifteenMinutes();
Schedule::command('whatnot:import')->cron('0,30 * * * *');
Schedule::command('whatnot:import-orders --new-only')->cron('5,35 * * * *');
