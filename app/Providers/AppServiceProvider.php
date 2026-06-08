<?php

namespace App\Providers;

use App\Models\AgencyBookingSetting;
use App\Models\ApiQuotaSetting;
use App\Models\Export;
use App\Models\IgnoredProspect;
use App\Models\OauthMcpRefreshTokenFamily;
use App\Models\OutreachEmail;
use App\Models\OutreachSelection;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\UserMcpKey;
use App\Policies\AgencyBookingSettingPolicy;
use App\Policies\ApiQuotaSettingPolicy;
use App\Policies\ExportPolicy;
use App\Policies\IgnoredProspectPolicy;
use App\Policies\NicheExclusionPolicy;
use App\Policies\OauthMcpRefreshTokenFamilyPolicy;
use App\Policies\OutreachEmailPolicy;
use App\Policies\OutreachSelectionPolicy;
use App\Policies\ProspectPolicy;
use App\Policies\SearchPolicy;
use App\Policies\UserMcpKeyPolicy;
use App\Queue\FailJobOnApiQuotaExceeded;
use App\Support\ScannerConfig;
use Illuminate\Support\Facades\Bus;
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
        Gate::policy(UserMcpKey::class, UserMcpKeyPolicy::class);
        Gate::policy(OutreachSelection::class, OutreachSelectionPolicy::class);
        Gate::policy(OutreachEmail::class, OutreachEmailPolicy::class);
        Gate::policy(OauthMcpRefreshTokenFamily::class, OauthMcpRefreshTokenFamilyPolicy::class);
        Gate::policy(IgnoredProspect::class, IgnoredProspectPolicy::class);
        Gate::policy(Export::class, ExportPolicy::class);
        Gate::policy(AgencyBookingSetting::class, AgencyBookingSettingPolicy::class);
        Gate::policy(ApiQuotaSetting::class, ApiQuotaSettingPolicy::class);

        Bus::pipeThrough([
            FailJobOnApiQuotaExceeded::class,
        ]);

        Gate::define('manageNicheExclusions', [NicheExclusionPolicy::class, 'manage']);

        Vite::prefetch(concurrency: 3);

        ScannerConfig::registerQueueRoutes();
    }
}
