<?php

namespace App\Services;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Jobs\DetectCmsJob;
use App\Models\Prospect;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProspectEnrichmentService
{
    public function __construct(
        private GbpScoringService $gbpScorer,
        private CombineScoresService $combiner,
        private ProspectAuditService $audits,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{audit_queued: bool}
     */
    public function update(Prospect $prospect, array $data): array
    {
        if ($prospect->audit_status === AuditStatus::Pending) {
            throw ValidationException::withMessages([
                'website_url' => 'A site audit is already in progress. Wait for it to finish before saving changes.',
            ]);
        }

        $prospect->loadMissing('search');
        $previousWebsite = $this->normalizeWebsiteUrl($prospect->website_url);

        $prospect->fill(collect($data)->only([
            'business_name', 'phone', 'website_url', 'address',
        ])->all());

        $newWebsite = $this->normalizeWebsiteUrl($prospect->website_url);
        $websiteChanged = $previousWebsite !== $newWebsite;

        $scored = $this->gbpScorer->scoreProspect($prospect);
        $combined = $this->combiner->combineForProspect($prospect);

        $updates = array_merge(
            $prospect->only(['business_name', 'phone', 'website_url', 'address']),
            $combined,
            [
                'gbp_score' => $scored['score'],
                'gbp_flags' => $scored['flags'],
            ],
        );

        if ($websiteChanged) {
            $updates['cms_detection'] = null;
            $updates['website_url_source'] = 'operator';
            $updates['website_discovery_confidence'] = null;
            $updates['website_discovered_at'] = null;
        }

        $prospect->update($updates);
        $prospect->refresh();

        $auditQueued = false;

        if ($websiteChanged && $this->shouldAudit($prospect)) {
            $this->audits->queueSiteAudit($prospect, suppressAutoReport: true);
            $auditQueued = true;
        } elseif ($websiteChanged && ! empty($prospect->website_url)) {
            DetectCmsJob::dispatch($prospect);
        }

        return ['audit_queued' => $auditQueued];
    }

    private function shouldAudit(Prospect $prospect): bool
    {
        return in_array($prospect->search->scan_type, [ScanType::AccessibilityOnly, ScanType::Combined], true)
            && ! empty($prospect->website_url);
    }

    private function normalizeWebsiteUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return Str::lower(rtrim($url, '/'));
    }
}
