<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('scanner:purge-expired')->daily();

Schedule::command('niches:scan')
    ->weekly()
    ->mondays()
    ->at('06:00')
    ->timezone('Europe/London');
