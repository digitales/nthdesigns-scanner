<?php

namespace App\Policies;

use App\Models\SharedSearch;
use App\Models\User;

class SharedSearchPolicy
{
    public function delete(User $user, SharedSearch $sharedSearch): bool
    {
        return $user->id === $sharedSearch->user_id;
    }
}
