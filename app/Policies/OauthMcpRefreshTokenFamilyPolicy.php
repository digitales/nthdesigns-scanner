<?php

namespace App\Policies;

use App\Models\OauthMcpRefreshTokenFamily;
use App\Models\User;

class OauthMcpRefreshTokenFamilyPolicy
{
    public function delete(User $user, OauthMcpRefreshTokenFamily $family): bool
    {
        return $user->id === $family->user_id;
    }

    public function deleteAny(User $user): bool
    {
        return $user !== null;
    }
}
