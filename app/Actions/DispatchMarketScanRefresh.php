<?php

namespace App\Actions;

use App\Enums\NicheScanStatus;
use App\Jobs\ScanNicheJob;
use App\Models\NicheScan;
use App\Support\NicheQueryResolver;
use Illuminate\Support\Facades\RateLimiter;

final class DispatchMarketScanRefresh
{
    public function __invoke(
        string $niche,
        string $city,
        string $country,
        ?string $nicheQueryFallback = null,
        ?int $userId = null,
    ): DispatchMarketScanRefreshResult {
        $scanDate = now('Europe/London')->toDateString();

        $todayPending = NicheScan::query()
            ->where('niche', $niche)
            ->where('city', $city)
            ->whereDate('scan_date', $scanDate)
            ->where('status', NicheScanStatus::Pending)
            ->exists();

        if ($todayPending) {
            return DispatchMarketScanRefreshResult::alreadyPending();
        }

        if ($userId !== null) {
            $rateKey = self::rateLimitKey($userId, $niche, $city);

            if (RateLimiter::tooManyAttempts($rateKey, 1)) {
                return DispatchMarketScanRefreshResult::rateLimited(
                    RateLimiter::availableIn($rateKey),
                );
            }

            RateLimiter::hit($rateKey, 60);
        }

        ScanNicheJob::dispatch(
            niche: $niche,
            nicheQuery: NicheQueryResolver::forLabelWithFallback($niche, $nicheQueryFallback),
            city: $city,
            country: $country,
            sample: 5,
            scanDate: $scanDate,
            force: true,
        );

        return DispatchMarketScanRefreshResult::queued();
    }

    public static function rateLimitKey(int $userId, string $niche, string $city): string
    {
        return 'niche-scan-refresh:'.$userId.':'.$niche.':'.$city;
    }
}
