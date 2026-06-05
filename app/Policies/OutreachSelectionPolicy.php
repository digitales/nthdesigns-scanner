<?php

namespace App\Policies;

use App\Models\OutreachSelection;
use App\Models\User;

class OutreachSelectionPolicy
{
    public function deleteAny(User $user): bool
    {
        return true;
    }

    public function delete(User $user, OutreachSelection $selection): bool
    {
        return $user->id === $selection->user_id;
    }
}
