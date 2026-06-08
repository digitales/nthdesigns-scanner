<?php

namespace App\Services\Mcp;

use App\Enums\ScanType;
use App\Enums\SearchSource;
use App\Enums\SearchStatus;
use App\Jobs\DirectUrlScanJob;
use App\Models\User;
use App\Services\UserSettingsService;
use App\Support\WebsiteUrlNormalizer;
use Illuminate\Support\Facades\RateLimiter;

class McpSingleSiteAuditService
{
    public function __construct(
        private WebsiteUrlNormalizer $normalizer,
        private UserSettingsService $settings,
    ) {}

    /**
     * @return array{search_id: int, status: string, app_url: string}
     */
    public function start(User $user, string $websiteUrl): array
    {
        $rateKey = 'search-submit:'.$user->id;
        $decay = (int) config('scanner.search_rate_limit_seconds', 30);

        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
            $seconds = RateLimiter::availableIn($rateKey);

            throw new \InvalidArgumentException(
                "Please wait {$seconds} seconds before starting another search."
            );
        }

        $url = trim($websiteUrl);
        if ($url === '' || ! preg_match('/^(https?:\/\/)?[^\s\/]+\.[^\s\/]+/i', $url)) {
            throw new \InvalidArgumentException('website_url must be a valid URL.');
        }

        if (strlen($url) > 2048) {
            throw new \InvalidArgumentException('website_url must not exceed 2048 characters.');
        }

        $normalized = $this->normalizer->normalize($url);

        RateLimiter::hit($rateKey, $decay);

        $search = $user->searches()->create([
            'source' => SearchSource::DirectUrl,
            'submitted_url' => $normalized,
            'country' => $this->settings->defaultCountry($user),
            'scan_type' => ScanType::Combined,
            'status' => SearchStatus::Pending,
            'total_found' => 1,
        ]);

        DirectUrlScanJob::dispatch($search);

        return [
            'search_id' => $search->id,
            'status' => $search->status->value,
            'app_url' => route('searches.show', $search),
        ];
    }
}
