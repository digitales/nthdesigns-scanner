<?php

namespace App\Policies;

use App\Models\User;

class NicheExclusionPolicy
{
    public function manage(User $user): bool
    {
        return true;
    }
}
