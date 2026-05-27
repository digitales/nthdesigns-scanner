<?php

namespace App\Providers;

use App\Models\Prospect;
use App\Models\Search;
use App\Policies\ProspectPolicy;
use App\Policies\SearchPolicy;
use App\Support\ScannerConfig;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ScannerConfig::applyRuntimeOverrides();

        Gate::policy(Search::class, SearchPolicy::class);
        Gate::policy(Prospect::class, ProspectPolicy::class);

        Vite::prefetch(concurrency: 3);
    }
}
