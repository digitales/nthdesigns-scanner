<?php

namespace App\Jobs;

use App\Models\Prospect;
use App\Models\Search;
use App\Support\SearchQueue;
use App\Support\WebsiteUrlNormalizer;
use App\Services\DirectUrlSearchEnrichment;
use App\Services\GbpScoringService;
use App\Services\GooglePlacesService;
use App\Services\ProspectExclusionService;
use App\Services\SearchStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DirectUrlScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 15;

    public function __construct(public Search $search)
    {
        SearchQueue::apply($this);
    }

    public function handle(
        GooglePlacesService $places,
        GbpScoringService $scorer,
        SearchStatusService $searchStatus,
        WebsiteUrlNormalizer $normalizer,
        DirectUrlSearchEnrichment $enrichment,
        ProspectExclusionService $exclusions,
    ): void {
        $search = $this->search->fresh();

        if (! $search || ! $search->isDirectUrl() || ! $search->submitted_url) {
            return;
        }

        $search->update(['status' => 'discovering']);

        $url = $search->submitted_url;
        $payload = $places->findByWebsiteUrl($url);

        $placeId = $payload['id'] ?? 'direct:'.hash('sha256', $normalizer->normalize($url));

        if ($exclusions->isIgnored($search->user_id, $placeId)) {
            $search->update(['status' => 'complete', 'total_found' => 0]);

            return;
        }

        try {
            if ($payload) {
                $searchUpdates = $enrichment->attributesFor($search, $payload);

                if ($searchUpdates !== []) {
                    $search->update($searchUpdates);
                    $search->refresh();
                }

                $overlay = $scorer->overlayProspectFields($payload, new Prospect(['website_url' => $url]));
                $fields = $scorer->extractFields($overlay);
                $scored = $scorer->score(
                    $overlay,
                    $search->benchmark_snapshot,
                    $search->city ?? '',
                );

                $prospect = Prospect::create(array_merge($fields, [
                    'search_id'       => $search->id,
                    'place_id'        => $payload['id'],
                    'website_url'     => $url,
                    'gbp_score'       => $scored['score'],
                    'gbp_flags'       => $scored['flags'],
                    'raw_gbp_payload' => $payload,
                    'expires_at'      => now()->addDays(config('scanner.report_expiry_days', 30)),
                    'audit_status'    => 'pending',
                ]));
            } else {
                $prospect = Prospect::create([
                    'search_id'    => $search->id,
                    'place_id'     => 'direct:'.hash('sha256', $normalizer->normalize($url)),
                    'business_name'=> $normalizer->displayNameFromUrl($url),
                    'website_url'  => $url,
                    'gbp_score'    => 0,
                    'gbp_flags'    => ['No GBP match found'],
                    'raw_gbp_payload' => null,
                    'expires_at'   => now()->addDays(config('scanner.report_expiry_days', 30)),
                    'audit_status' => 'pending',
                ]);
            }

            $search->update(['total_found' => 1]);
            AuditSiteJob::dispatch($prospect);
            $searchStatus->refresh($search->fresh());
        } catch (\Throwable $e) {
            Log::error('DirectUrlScanJob failed', [
                'search_id' => $search->id,
                'error'     => $e->getMessage(),
            ]);
            $search->update(['status' => 'failed']);
            throw $e;
        }
    }
}
