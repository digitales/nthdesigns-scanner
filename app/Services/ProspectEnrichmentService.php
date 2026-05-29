<?php

namespace App\Services;

use App\Jobs\AuditSiteJob;
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
        if ($prospect->audit_status === 'pending') {
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
        $combined = $this->combiner->combine($prospect, $prospect->search->scan_type);

        $updates = array_merge(
            $prospect->only(['business_name', 'phone', 'website_url', 'address']),
            $combined,
            [
                'gbp_score' => $scored['score'],
                'gbp_flags' => $scored['flags'],
            ],
        );

        $auditQueued = false;

        if ($websiteChanged && $this->shouldAudit($prospect)) {
            $updates = array_merge($updates, $this->audits->auditResetFields(), [
                'suppress_auto_report' => true,
            ]);
            $auditQueued = true;
        }

        $prospect->update($updates);
        $prospect->refresh();

        if ($auditQueued) {
            AuditSiteJob::dispatch($prospect);
        }

        return ['audit_queued' => $auditQueued];
    }

    private function shouldAudit(Prospect $prospect): bool
    {
        return in_array($prospect->search->scan_type, ['accessibility_only', 'combined'], true)
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
