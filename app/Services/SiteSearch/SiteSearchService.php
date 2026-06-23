<?php

namespace App\Services\SiteSearch;

use App\Contracts\SiteSearchProvider;
use App\Data\SiteSearchResult;
use App\Models\User;

class SiteSearchService
{
    public function __construct(
        private SiteSearchProvider $provider,
    ) {}

    public function search(User $user, string $query): SiteSearchResult
    {
        $query = trim($query);

        if (mb_strlen($query) < (int) config('site_search.min_query_length', 2)) {
            return SiteSearchResult::tooShort();
        }

        return $this->provider->search($user, $query);
    }
}
