<?php

namespace App\Services;

use App\Models\MarketCpcDefault;
use App\Models\Search;
use App\Models\User;
use App\Services\GoogleAds\CpcBenchmarkResult;
use Illuminate\Support\Str;

class MarketCpcDefaultService
{
    public function find(User $user, string $niche, string $city, string $country = 'GB'): ?MarketCpcDefault
    {
        return MarketCpcDefault::query()
            ->where('user_id', $user->id)
            ->where('niche', $this->normalize($niche))
            ->where('city', $this->normalize($city))
            ->where('country', strtoupper($country))
            ->first();
    }

    /**
     * @param  array{
     *     cpc_benchmark?: float|null,
     *     cpc_source?: string|null,
     *     cpc_keywords?: list<string>|null,
     *     cpc_geo_target?: string|null,
     * }  $attributes
     */
    public function upsert(User $user, string $niche, string $city, string $country, array $attributes): MarketCpcDefault
    {
        return MarketCpcDefault::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'niche' => $this->normalize($niche),
                'city' => $this->normalize($city),
                'country' => strtoupper($country),
            ],
            [
                'cpc_benchmark' => $attributes['cpc_benchmark'] ?? null,
                'cpc_source' => $attributes['cpc_source'] ?? null,
                'cpc_keywords' => $attributes['cpc_keywords'] ?? null,
                'cpc_geo_target' => $attributes['cpc_geo_target'] ?? null,
            ],
        );
    }

    public function applyFromDefault(Search $search, User $user): Search
    {
        if ($search->cpc_benchmark !== null || $search->niche === null || $search->city === null) {
            return $search;
        }

        $default = $this->find($user, $search->niche, $search->city, $search->country ?? 'GB');

        if ($default === null || $default->cpc_benchmark === null) {
            return $search;
        }

        $search->update([
            'cpc_benchmark' => $default->cpc_benchmark,
            'cpc_source' => $default->cpc_source ?? 'market_default',
            'cpc_keywords' => $default->cpc_keywords,
            'cpc_geo_target' => $default->cpc_geo_target,
        ]);

        return $search->fresh();
    }

    public function syncFromResult(
        User $user,
        string $niche,
        string $city,
        string $country,
        CpcBenchmarkResult $result,
    ): MarketCpcDefault {
        return $this->upsert($user, $niche, $city, $country, [
            'cpc_benchmark' => $result->benchmark,
            'cpc_source' => $result->source,
            'cpc_keywords' => $result->keywords,
            'cpc_geo_target' => $result->geoTarget,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function format(?MarketCpcDefault $default): ?array
    {
        if ($default === null) {
            return null;
        }

        return [
            'cpc_benchmark' => $default->cpc_benchmark !== null
                ? number_format((float) $default->cpc_benchmark, 2, '.', '')
                : null,
            'cpc_source' => $default->cpc_source,
            'cpc_keywords' => $default->cpc_keywords ?? [],
            'cpc_geo_target' => $default->cpc_geo_target,
            'updated_at' => $default->updated_at?->toISOString(),
        ];
    }

    private function normalize(string $value): string
    {
        return Str::lower(trim($value));
    }
}
