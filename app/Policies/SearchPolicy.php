<?php

namespace App\Policies;

use App\Models\Search;
use App\Models\User;

class SearchPolicy
{
    public function view(User $user, Search $search): bool
    {
        return $user->id === $search->user_id;
    }

    public function update(User $user, Search $search): bool
    {
        return $user->id === $search->user_id;
    }
}
