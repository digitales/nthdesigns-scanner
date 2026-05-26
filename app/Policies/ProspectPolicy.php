<?php

namespace App\Policies;

use App\Models\Prospect;
use App\Models\User;

class ProspectPolicy
{
    public function view(User $user, Prospect $prospect): bool
    {
        return $user->id === $prospect->search->user_id;
    }

    public function update(User $user, Prospect $prospect): bool
    {
        return $this->view($user, $prospect);
    }
}
