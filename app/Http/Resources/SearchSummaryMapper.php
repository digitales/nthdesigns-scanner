<?php

namespace App\Http\Resources;

use App\Models\Search;

class SearchSummaryMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function format(Search $search, string $createdAtFormat = 'relative'): array
    {
        return [
            'id' => $search->id,
            'source' => $search->source->value,
            'submitted_url' => $search->submitted_url,
            'niche' => $search->niche,
            'city' => $search->city,
            'status' => $search->status->value,
            'total_found' => $search->total_found,
            'created_at' => self::formatCreatedAt($search, $createdAtFormat),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Search $search, string $createdAtFormat = 'iso'): array
    {
        return array_merge(self::format($search, $createdAtFormat), [
            'country' => $search->country,
            'scan_type' => $search->scan_type->value,
            'cpc_benchmark' => $search->cpc_benchmark !== null
                ? number_format((float) $search->cpc_benchmark, 2, '.', '')
                : null,
            'cpc_source' => $search->cpc_source,
            'cpc_keywords' => $search->cpc_keywords ?? [],
            'cpc_geo_target' => $search->cpc_geo_target,
        ]);
    }

    /**
     * @param  array<string, mixed>  $progressFlow
     * @return array<string, mixed>
     */
    public static function forShow(Search $search, array $progressFlow): array
    {
        return array_merge(self::detail($search), [
            'created_at' => $search->created_at->toISOString(),
            'progress_flow' => $progressFlow,
        ]);
    }

    private static function formatCreatedAt(Search $search, string $format): string
    {
        return match ($format) {
            'iso' => $search->created_at->toIso8601String(),
            default => $search->created_at->diffForHumans(),
        };
    }
}
