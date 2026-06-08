<?php

namespace App\Services\Niches;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NichesTaxonomyParser
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

    /**
     * @param  callable(string): void|null  $warn
     * @return list<array{label: string, query: string, primary_type: string}>
     */
    public function fetchCandidates(?callable $warn = null): array
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
