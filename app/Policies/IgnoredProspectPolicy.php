<?php

namespace App\Policies;

use App\Models\IgnoredProspect;
use App\Models\User;

class IgnoredProspectPolicy
{
    public function delete(User $user, IgnoredProspect $ignoredProspect): bool
    {
        return $user->id === $ignoredProspect->user_id;
    }
}
