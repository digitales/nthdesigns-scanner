<?php

namespace App\Http\Resources;

use App\Data\SiteSearchResult;

class SiteSearchResource
{
    /**
     * @return array{query: string, status: string, sections: list<array{key: string, label: string, items: list<array{title: string, subtitle: string|null, href: string}>}>}
     */
    public static function format(string $query, SiteSearchResult $result): array
    {
        return [
            'query' => $query,
            'status' => $result->status,
            'sections' => $result->sections,
        ];
    }
}
