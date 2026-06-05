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
            'source' => $search->source,
            'submitted_url' => $search->submitted_url,
            'niche' => $search->niche,
            'city' => $search->city,
            'status' => $search->status,
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
            'scan_type' => $search->scan_type,
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
