<?php

namespace App\Contracts;

use App\Data\SiteSearchResult;
use App\Models\User;

interface SiteSearchProvider
{
    public function search(User $user, string $query): SiteSearchResult;
}
