<?php

namespace App\Services;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Enums\SearchSource;
use App\Enums\SearchStatus;
use App\Jobs\DirectUrlScanJob;
use App\Models\Prospect;
use App\Models\ProspectReport;
use App\Models\Search;
use App\Models\User;
use App\Support\WebsiteUrlNormalizer;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HomepageAuditService
{
    public function __construct(
        private WebsiteUrlNormalizer $normalizer,
        private UserSettingsService $settings,
        private ProgressFlowService $progressFlow,
    ) {}

    public function isEnabled(): bool
    {
        if (! config('scanner.homepage_audit.enabled', true)) {
            return false;
        }

        return $this->ownerUser() !== null;
    }

    /**
     * @return array{token: string, status: string}
     */
    public function start(string $websiteUrl, string $clientIp): array
    {
        $owner = $this->ownerUser();

        if ($owner === null) {
            throw ValidationException::withMessages([
                'website_url' => 'Homepage audits are not available right now.',
            ]);
        }

        $this->assertWithinRateLimit($clientIp);

        $url = trim($websiteUrl);

        if ($url === '' || ! preg_match('/^(https?:\/\/)?[^\s\/]+\.[^\s\/]+/i', $url)) {
            throw ValidationException::withMessages([
                'website_url' => 'Enter a valid website address.',
            ]);
        }

        $normalized = $this->normalizer->normalize($url);
        $token = (string) Str::uuid();

        $search = $owner->searches()->create([
            'source' => SearchSource::Homepage,
            'public_token' => $token,
            'submitted_url' => $normalized,
            'country' => $this->settings->defaultCountry($owner),
            'scan_type' => ScanType::Combined,
            'status' => SearchStatus::Pending,
            'total_found' => 1,
        ]);

        DirectUrlScanJob::dispatch($search);

        return [
            'token' => $token,
            'status' => $search->status->value,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function status(string $token): ?array
    {
        $search = Search::query()
            ->where('public_token', $token)
            ->where('source', SearchSource::Homepage)
            ->first();

        if (! $search) {
            return null;
        }

        $prospect = $search->prospects()->with('report')->first();
        $prospects = $prospect ? collect([$prospect]) : collect();
        $flow = $this->progressFlow->searchFlow($search, $prospects);
        $report = $prospect?->report;
        $failed = $this->hasFailed($search, $prospect);
        $phase = $this->resolvePhase($search, $prospect, $report, $flow['phase']);

        return [
            'phase' => $phase,
            'message' => $this->statusMessage($phase),
            'percent' => $this->resolvePercent($phase, $flow['percent']),
            'complete' => $report !== null || $failed,
            'failed' => $failed,
            'report_url' => $report ? url('/r/'.$report->token) : null,
            'website_url' => $search->submitted_url,
        ];
    }

    private function ownerUser(): ?User
    {
        $userId = config('scanner.homepage_audit.user_id');

        if ($userId === null) {
            return null;
        }

        return User::query()->find($userId);
    }

    private function assertWithinRateLimit(string $clientIp): void
    {
        $ip = $clientIp !== '' ? $clientIp : 'unknown';
        $burstKey = 'homepage-audit-burst:'.$ip;
        $hourlyKey = 'homepage-audit-hourly:'.$ip;
        $burstDecay = (int) config('scanner.homepage_audit.rate_limit_seconds', 60);
        $hourlyLimit = (int) config('scanner.homepage_audit.hourly_limit', 5);

        if (RateLimiter::tooManyAttempts($burstKey, 1)) {
            $seconds = RateLimiter::availableIn($burstKey);

            throw ValidationException::withMessages([
                'website_url' => "Please wait {$seconds} seconds before starting another audit.",
            ]);
        }

        if (RateLimiter::tooManyAttempts($hourlyKey, $hourlyLimit)) {
            $seconds = RateLimiter::availableIn($hourlyKey);

            throw ValidationException::withMessages([
                'website_url' => 'You have reached the hourly audit limit. Try again later.',
            ]);
        }

        RateLimiter::hit($burstKey, $burstDecay);
        RateLimiter::hit($hourlyKey, 3600);
    }

    private function hasFailed(Search $search, ?Prospect $prospect): bool
    {
        if ($search->status === SearchStatus::Failed) {
            return true;
        }

        return $prospect !== null && $prospect->audit_status === AuditStatus::Failed;
    }

    /**
     * @param  Collection<int, Prospect>  $prospects
     */
    private function resolvePhase(Search $search, ?Prospect $prospect, ?ProspectReport $report, string $flowPhase): string
    {
        if ($this->hasFailed($search, $prospect)) {
            return 'failed';
        }

        if ($report !== null) {
            return 'complete';
        }

        if ($prospect !== null && in_array($prospect->audit_status, [AuditStatus::Complete, AuditStatus::Skipped, AuditStatus::Failed], true)) {
            return 'reporting';
        }

        return $flowPhase;
    }

    private function resolvePercent(string $phase, ?int $flowPercent): ?int
    {
        return match ($phase) {
            'queued' => 5,
            'discovering' => 25,
            'auditing' => max(45, min($flowPercent ?? 70, 85)),
            'reporting' => 95,
            'complete' => 100,
            'failed' => null,
            default => $flowPercent,
        };
    }

    private function statusMessage(string $phase): string
    {
        return match ($phase) {
            'queued' => 'Starting your audit…',
            'discovering' => 'Looking up your Google Business Profile…',
            'auditing' => 'Running WCAG 2.2 checks and Lighthouse…',
            'reporting' => 'Compiling your report…',
            'complete' => 'Your report is ready.',
            'failed' => 'We could not complete this audit. Check the URL and try again.',
            default => 'Running audit…',
        };
    }
}
