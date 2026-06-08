<?php

namespace App\Policies;

use App\Models\SharedList;
use App\Models\User;

class SharedListPolicy
{
    public function delete(User $user, SharedList $sharedList): bool
    {
        return $user->id === $sharedList->user_id;
    }
}
