<?php

namespace App\Jobs;

use App\Enums\AuditStatus;
use App\Enums\ScanType;
use App\Enums\WebsiteUrlSource;
use App\Models\Prospect;
use App\Models\Search;
use App\Services\GbpScoringService;
use App\Services\GooglePlacesService;
use App\Services\SearchStatusService;
use App\Services\WebsiteDiscoveryService;
use App\Support\ScannerJobContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScorePlaceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public function __construct(
        #[WithoutRelations]
        public Search $search,
        public string $placeId,
    ) {}

    public function handle(
        GooglePlacesService $places,
        GbpScoringService $scorer,
        SearchStatusService $searchStatus,
        WebsiteDiscoveryService $discovery,
    ): void {
        ScannerJobContext::add(self::class, [
            'search_id' => $this->search->id,
            'place_id' => $this->placeId,
        ]);

        $existing = Prospect::where('search_id', $this->search->id)
            ->where('place_id', $this->placeId)
            ->first();

        if ($existing) {
            return;
        }

        $payload = $places->getPlaceDetails($this->placeId);

        if (! $payload) {
            Log::warning('ScorePlaceJob: getPlaceDetails returned null', [
                'place_id' => $this->placeId,
                'search_id' => $this->search->id,
            ]);
            $searchStatus->refresh($this->search);

            return;
        }

        $search = $this->search->fresh();
        $fields = $scorer->extractFields($payload);
        $scored = $scorer->score(
            $payload,
            $search->benchmark_snapshot,
            $search->city,
        );

        $prospect = Prospect::updateOrCreate(
            [
                'search_id' => $this->search->id,
                'place_id' => $this->placeId,
            ],
            [
                ...$fields,
                'website_url_source' => WebsiteUrlSource::Gbp,
                'gbp_score' => $scored['score'],
                'gbp_flags' => $scored['flags'],
                'raw_gbp_payload' => $payload,
                'expires_at' => now()->addDays(config('scanner.report_expiry_days', 30)),
                'audit_status' => AuditStatus::Pending,
            ]
        );

        $prospect = $this->applyWebsiteDiscovery($prospect, $search, $payload, $discovery);

        $this->dispatchNextStep($prospect, $search);
        $searchStatus->refresh($search);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyWebsiteDiscovery(
        Prospect $prospect,
        Search $search,
        array $payload,
        WebsiteDiscoveryService $discovery,
    ): Prospect {
        $match = $discovery->discover($prospect, $search, $payload);

        if ($match === null) {
            return $prospect;
        }

        return $discovery->applyMatch($prospect, $search, $match);
    }

    private function dispatchNextStep(Prospect $prospect, Search $search): void
    {
        $needsA11yAudit = in_array($search->scan_type, [ScanType::AccessibilityOnly, ScanType::Combined], true)
            && ! empty($prospect->website_url);

        if ($needsA11yAudit) {
            AuditSiteJob::dispatch($prospect);

            if (config('scanner.audit_driver') === 'skip' && config('scanner.cms_detect_driver') !== 'skip') {
                DetectCmsJob::dispatch($prospect);
            }

            return;
        }

        if (! empty($prospect->website_url) && config('scanner.cms_detect_driver') !== 'skip') {
            DetectCmsJob::dispatch($prospect);
        }

        CombineScoresJob::dispatch($prospect->fresh());
    }
}
