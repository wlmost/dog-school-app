<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Here you may define all of your scheduled tasks. These tasks will be
| run by the scheduler service defined in docker-compose.yml.
|
*/

// Clean up old failed jobs (older than 30 days)
Schedule::command('queue:prune-failed --hours=720')
    ->dailyAt('03:00')
    ->timezone('Europe/Berlin');
