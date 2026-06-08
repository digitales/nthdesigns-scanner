<?php

namespace App\Policies;

use App\Models\ProspectList;
use App\Models\User;

class ProspectListPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProspectList $list): bool
    {
        return $user->id === $list->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ProspectList $list): bool
    {
        return $user->id === $list->user_id;
    }

    public function delete(User $user, ProspectList $list): bool
    {
        return $user->id === $list->user_id;
    }
}
