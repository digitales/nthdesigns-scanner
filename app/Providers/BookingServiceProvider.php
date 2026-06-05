<?php

namespace App\Providers;

use App\Contracts\Calendar\CalendarProvider;
use App\Services\Calendar\FastmailCalDavProvider;
use Illuminate\Support\ServiceProvider;

class BookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CalendarProvider::class, FastmailCalDavProvider::class);
    }
}
