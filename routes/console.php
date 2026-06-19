<?php

use App\Jobs\ProcessWarmupJob;
use App\Jobs\PurgeWarmupSendsJob;
use App\Jobs\RetryStaleWarmupInboxJob;
use App\Jobs\WarmupHealthCheckJob;
use App\Jobs\WarmupPoolHealthJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('scanner:purge-expired')->daily();
Schedule::command('booking:retry-unsent-confirmations')->everyFifteenMinutes();
Schedule::job(new ProcessWarmupJob)->dailyAt('08:00');
Schedule::job(new RetryStaleWarmupInboxJob)->dailyAt('09:00');
Schedule::job(new WarmupHealthCheckJob)->dailyAt('09:15');
Schedule::job(new WarmupPoolHealthJob)->dailyAt('09:30');
Schedule::job(new PurgeWarmupSendsJob)->weekly();
