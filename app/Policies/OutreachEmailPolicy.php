<?php

namespace App\Policies;

use App\Models\OutreachEmail;
use App\Models\User;

class OutreachEmailPolicy
{
    public function update(User $user, OutreachEmail $outreachEmail): bool
    {
        return $user->id === $outreachEmail->user_id;
    }
}
