<?php

namespace App\Services;

use App\Services\Niches\NichesCityCatalog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NichesBootstrapSteps
{
    private const TAXONOMY_URL = 'https://developers.google.com/maps/documentation/places/web-service/place-types';

    private const TYPE_BLOCKLIST = [
        'transit', 'station', 'airport', 'parking', 'atm', 'bank',
        'finance', 'government', 'post_office', 'embassy', 'courthouse',
        'fire_station', 'police', 'prison', 'cemetery', 'funeral',
        'storage', 'moving', 'laundry', 'car_wash', 'car_repair',
        'gas_station', 'electric_vehicle', 'lodging', 'campground',
        'rv_park', 'grocery', 'supermarket', 'convenience', 'liquor',
        'hardware', 'home_goods', 'furniture', 'electronics', 'clothing',
        'shoe', 'jewelry', 'book_store', 'bicycle', 'department_store',
        'shopping_mall', 'wholesale', 'florist', 'gift', 'toy',
        'pet_store', 'aquarium', 'zoo', 'museum', 'art_gallery',
        'amusement', 'casino', 'movie', 'stadium', 'bowling',
        'night_club', 'bar', 'cafe', 'bakery', 'meal_takeaway',
    ];

    private const TYPE_ALLOWLIST = [
        'doctor', 'dentist', 'health', 'medical', 'hospital', 'clinic',
        'lawyer', 'legal', 'accountant', 'finance_advisor', 'insurance',
        'real_estate', 'physiotherapist', 'veterinary', 'optician',
        'beauty', 'hair', 'spa', 'gym', 'fitness', 'plumber', 'electrician',
        'contractor', 'architect', 'tutor', 'school', 'consultant',
    ];

    private const FALLBACK_NICHES = [
        ['label' => 'Dental Practice', 'query' => 'dental practice', 'primary_type' => 'dentist'],
        ['label' => 'Physiotherapist', 'query' => 'physiotherapist', 'primary_type' => 'physiotherapist'],
        ['label' => 'Solicitor', 'query' => 'solicitor', 'primary_type' => 'lawyer'],
        ['label' => 'Accountant', 'query' => 'accountant', 'primary_type' => 'accounting'],
        ['label' => 'Estate Agent', 'query' => 'estate agent', 'primary_type' => 'real_estate_agency'],
        ['label' => 'Independent Hotel', 'query' => 'independent hotel', 'primary_type' => 'lodging'],
        ['label' => 'Restaurant', 'query' => 'restaurant', 'primary_type' => 'restaurant'],
        ['label' => 'Optician', 'query' => 'optician', 'primary_type' => 'optician'],
        ['label' => 'Veterinary Practice', 'query' => 'vet practice', 'primary_type' => 'veterinary_care'],
        ['label' => 'Private GP', 'query' => 'private GP', 'primary_type' => 'doctor'],
        ['label' => 'Osteopath', 'query' => 'osteopath', 'primary_type' => 'physiotherapist'],
        ['label' => 'Chiropractor', 'query' => 'chiropractor', 'primary_type' => 'physiotherapist'],
        ['label' => 'Beauty Salon', 'query' => 'beauty salon', 'primary_type' => 'beauty_salon'],
        ['label' => 'Barbershop', 'query' => 'barbershop', 'primary_type' => 'hair_care'],
        ['label' => 'Plumber', 'query' => 'plumber', 'primary_type' => 'plumber'],
        ['label' => 'Electrician', 'query' => 'electrician', 'primary_type' => 'electrician'],
        ['label' => 'Architect', 'query' => 'architect', 'primary_type' => 'architect'],
        ['label' => 'Financial Adviser', 'query' => 'financial adviser', 'primary_type' => 'finance'],
        ['label' => 'Mortgage Broker', 'query' => 'mortgage broker', 'primary_type' => 'finance'],
        ['label' => 'Private Tutor', 'query' => 'private tutor', 'primary_type' => 'tutoring_center'],
    ];

    public function __construct(private GooglePlacesService $places) {}

    /**
     * @param  callable(string): void|null  $warn
     * @return list<string>
     */
    public function fetchCities(?callable $warn = null): array
    {
        return (new NichesCityCatalog)->fetchCities($warn);
    }

    /**
     * @param  callable(string): void|null  $warn
     * @return list<array{label: string, query: string, primary_type: string}>
     */
    public function fetchNicheCandidates(?callable $warn = null): array
    {
        $response = Http::timeout(15)->get(self::TAXONOMY_URL);

        if ($response->failed()) {
            if ($warn !== null) {
                $warn('Places taxonomy fetch failed; using hardcoded niche list.');
            }

            return self::FALLBACK_NICHES;
        }

        preg_match_all('/\b[a-z][a-z_]{3,40}\b/', $response->body(), $matches);

        $filtered = collect($matches[0] ?? [])
            ->unique()
            ->filter(fn (string $type) => $this->typePassesFilter($type))
            ->map(fn (string $type) => [
                'primary_type' => $type,
                'label' => Str::title(str_replace('_', ' ', $type)),
                'query' => str_replace('_', ' ', $type),
            ])
            ->values()
            ->all();

        if ($filtered === []) {
            if ($warn !== null) {
                $warn('No types extracted from taxonomy; using hardcoded niche list.');
            }

            return self::FALLBACK_NICHES;
        }

        return $filtered;
    }

    /**
     * @param  list<array{label: string, query: string, primary_type: string}>  $niches
     * @param  callable(string): void|null  $warn
     * @param  callable(): void|null  $advance
     * @return array{niches: list<array{label: string, query: string, primary_type: string}>, apiCalls: int, dropped: int, keptUnvalidated: bool}
     */
    public function validateNiches(array $niches, int $minResults, ?callable $warn = null, ?callable $advance = null): array
    {
        $key = config('services.google_places.key');

        if ($key === null || $key === '') {
            if ($warn !== null) {
                $warn('GOOGLE_PLACES_API_KEY is not set; skipping validation pass.');
            }

            return ['niches' => $niches, 'apiCalls' => 0, 'dropped' => 0, 'keptUnvalidated' => false];
        }

        $minResults = max(1, $minResults);
        $kept = [];
        $dropped = 0;
        $apiCalls = 0;
        $zeroResultCount = 0;

        foreach ($niches as $niche) {
            $apiCalls++;
            $placeIds = $this->places->searchByNicheAndCity($niche['query'], 'Birmingham', 'GB');
            $count = count($placeIds);

            if ($count === 0) {
                $zeroResultCount++;
            }

            if ($count < $minResults) {
                $dropped++;
                if ($warn !== null) {
                    $warn("Dropped {$niche['label']}: {$count} results in Birmingham");
                }
            } else {
                $kept[] = $niche;
            }

            if ($advance !== null) {
                $advance();
            }
        }

        if ($zeroResultCount === count($niches) && count($niches) > 0) {
            if ($warn !== null) {
                $warn('Places API may have failed — keeping unvalidated niche list.');
            }

            return ['niches' => $niches, 'apiCalls' => $apiCalls, 'dropped' => 0, 'keptUnvalidated' => true];
        }

        return ['niches' => $kept, 'apiCalls' => $apiCalls, 'dropped' => $dropped, 'keptUnvalidated' => false];
    }

    /**
     * @param  list<array{label: string, query: string, primary_type: string}>  $niches
     * @param  list<string>  $cities
     */
    public function renderConfigPhp(array $niches, array $cities): string
    {
        $date = now()->toDateString();
        $export = var_export(['niches' => $niches, 'cities' => $cities], true);

        return <<<PHP
<?php

// Generated by niches:bootstrap on {$date}
// Edit this file manually to add, remove, or rename niches.
// Re-run niches:bootstrap only if you need to expand from scratch.

return {$export};

PHP;
    }

    private function typePassesFilter(string $type): bool
    {
        foreach (self::TYPE_ALLOWLIST as $signal) {
            if (str_contains($type, $signal)) {
                return true;
            }
        }

        foreach (self::TYPE_BLOCKLIST as $signal) {
            if (str_contains($type, $signal)) {
                return false;
            }
        }

        return false;
    }
}
