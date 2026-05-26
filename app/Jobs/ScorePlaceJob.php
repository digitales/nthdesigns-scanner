<?php

namespace App\Jobs;

use App\Models\Prospect;
use App\Models\Search;
use App\Services\GooglePlacesService;
use App\Services\GbpScoringService;
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

    public function handle(GooglePlacesService $places, GbpScoringService $scorer): void
    {
        $existing = Prospect::where('search_id', $this->search->id)
            ->where('place_id', $this->placeId)
            ->first();

        if ($existing && $existing->audit_status !== 'pending') {
            return;
        }

        $payload = $places->getPlaceDetails($this->placeId);

        if (!$payload) {
            Log::warning('ScorePlaceJob: getPlaceDetails returned null', [
                'place_id'  => $this->placeId,
                'search_id' => $this->search->id,
            ]);
            $this->markSearchCompleteIfDone();
            return;
        }

        $fields    = $scorer->extractFields($payload);
        $scored    = $scorer->score($payload);
        $combined  = $scored['score'];

        Prospect::updateOrCreate(
            [
                'search_id' => $this->search->id,
                'place_id'  => $this->placeId,
            ],
            array_merge($fields, [
                'gbp_score'      => $scored['score'],
                'gbp_flags'      => $scored['flags'],
                'combined_score' => $combined,
                'dominant_angle' => 'gbp',
                'audit_status'   => empty($fields['website_url']) ? 'skipped' : 'pending',
                'raw_gbp_payload'=> $payload,
                'expires_at'     => now()->addDays(30),
            ])
        );

        $this->markSearchCompleteIfDone();
    }

    private function markSearchCompleteIfDone(): void
    {
        $search = $this->search->fresh();

        if (!$search || $search->status === 'complete') {
            return;
        }

        $totalFound   = $search->total_found ?? 0;
        $scoredCount  = Prospect::where('search_id', $search->id)
            ->whereIn('audit_status', ['pending', 'skipped', 'complete'])
            ->count();

        if ($totalFound > 0 && $scoredCount >= $totalFound) {
            $search->update(['status' => 'complete']);
        }
    }

    public function queue(): string
    {
        return 'scraping';
    }
}
