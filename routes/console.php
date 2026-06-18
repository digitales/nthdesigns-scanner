<?php

use App\Jobs\ProcessWarmupJob;
use App\Jobs\PurgeWarmupSendsJob;
use App\Jobs\WarmupHealthCheckJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('scanner:purge-expired')->daily();
Schedule::command('booking:retry-unsent-confirmations')->everyFifteenMinutes();
Schedule::job(new ProcessWarmupJob)->dailyAt('08:00');
Schedule::job(new WarmupHealthCheckJob)->dailyAt('09:00');
Schedule::job(new PurgeWarmupSendsJob)->weekly();
