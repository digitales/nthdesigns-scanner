<?php

namespace App\Jobs;

use App\Models\Prospect;
use App\Models\Search;
use App\Services\GooglePlacesService;
use App\Services\GbpScoringService;
use App\Services\SearchStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScorePlaceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 15;

    public function __construct(
        public Search $search,
        public string $placeId,
    ) {}

    public function handle(
        GooglePlacesService $places,
        GbpScoringService $scorer,
        SearchStatusService $searchStatus,
    ): void {
        $existing = Prospect::where('search_id', $this->search->id)
            ->where('place_id', $this->placeId)
            ->first();

        if ($existing && !in_array($existing->audit_status, ['pending'], true)) {
            return;
        }

        $payload = $places->getPlaceDetails($this->placeId);

        if (!$payload) {
            Log::warning('ScorePlaceJob: getPlaceDetails returned null', [
                'place_id'  => $this->placeId,
                'search_id' => $this->search->id,
            ]);
            $searchStatus->refresh($this->search);
            return;
        }

        $fields = $scorer->extractFields($payload);
        $scored = $scorer->score($payload);
        $search = $this->search->fresh();

        $prospect = Prospect::updateOrCreate(
            [
                'search_id' => $this->search->id,
                'place_id'  => $this->placeId,
            ],
            [
                ...$fields,
                'gbp_score'       => $scored['score'],
                'gbp_flags'       => $scored['flags'],
                'raw_gbp_payload' => $payload,
                'expires_at'      => now()->addDays(config('scanner.report_expiry_days', 30)),
                'audit_status'    => 'pending',
            ]
        );

        $this->dispatchNextStep($prospect, $search);
        $searchStatus->refresh($search);
    }

    private function dispatchNextStep(Prospect $prospect, Search $search): void
    {
        $needsA11yAudit = in_array($search->scan_type, ['accessibility_only', 'combined'], true)
            && !empty($prospect->website_url);

        if ($needsA11yAudit) {
            AuditSiteJob::dispatch($prospect)->onQueue('auditing');

            return;
        }

        if (empty($prospect->website_url)) {
            $prospect->update(['audit_status' => 'skipped']);
        }

        CombineScoresJob::dispatch($prospect)->onQueue('auditing');
    }

    public function queue(): string
    {
        return 'scraping';
    }
}
