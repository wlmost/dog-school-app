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

// Send payment reminders for invoices overdue by 7 days or more
Schedule::command('invoices:send-reminders --days=7')
    ->dailyAt('09:00')
    ->timezone('Europe/Berlin')
    ->onSuccess(function () {
        info('Payment reminders sent successfully');
    })
    ->onFailure(function () {
        error('Payment reminders failed to send');
    });

// Send payment reminders for invoices overdue by 14 days or more
Schedule::command('invoices:send-reminders --days=14')
    ->dailyAt('09:15')
    ->timezone('Europe/Berlin')
    ->when(function () {
        // Only run on weekdays
        return now()->isWeekday();
    });

// Clean up old failed jobs (older than 30 days)
Schedule::command('queue:prune-failed --hours=720')
    ->dailyAt('03:00')
    ->timezone('Europe/Berlin');
