<?php

namespace App\Services;

use App\Models\Search;
use Illuminate\Support\Facades\Log;

/**
 * Backfill niche, city, country, and benchmark snapshot on direct URL searches from GBP data.
 */
class DirectUrlSearchEnrichment
{
    public function __construct(
        private GbpPlaceContextResolver $context,
        private GooglePlacesService $places,
        private BenchmarkNormalizer $normalizer,
    ) {}

    /**
     * @return array<string, mixed> Attributes to persist on the search model
     */
    public function attributesFor(Search $search, array $gbpPayload): array
    {
        $resolved = $this->context->resolve($gbpPayload, $search->country ?? 'GB');

        $updates = [];

        if (filled($resolved['niche'])) {
            $updates['niche'] = $resolved['niche'];
        }

        if (filled($resolved['city'])) {
            $updates['city'] = $resolved['city'];
        }

        if (filled($resolved['country'])) {
            $updates['country'] = $resolved['country'];
        }

        $niche = $updates['niche'] ?? $search->niche;
        $city = $updates['city'] ?? $search->city;
        $country = $updates['country'] ?? $search->country ?? 'GB';

        if (! filled($niche) || ! filled($city)) {
            return $updates;
        }

        $benchmarkPlace = $this->places->getTopRankedInNiche(
            $niche,
            $city,
            $country,
            $gbpPayload['id'] ?? null,
        );

        if (! $benchmarkPlace) {
            Log::warning('DirectUrlSearchEnrichment: no benchmark place returned', [
                'search_id' => $search->id,
                'niche' => $niche,
                'city' => $city,
            ]);

            return $updates;
        }

        $updates['benchmark_snapshot'] = $this->normalizer->fromPlace($benchmarkPlace);

        return $updates;
    }
}
