<?php

namespace App\Policies;

use App\Models\ApiQuotaSetting;
use App\Models\User;

class ApiQuotaSettingPolicy
{
    public function update(User $user, ApiQuotaSetting $setting): bool
    {
        return true;
    }
}
