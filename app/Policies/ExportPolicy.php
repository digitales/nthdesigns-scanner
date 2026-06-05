<?php

namespace App\Policies;

use App\Models\User;

class ExportPolicy
{
    public function create(User $user): bool
    {
        return true;
    }
}
