<?php

namespace App\Policies;

use App\Models\AgencyBookingSetting;
use App\Models\User;

class AgencyBookingSettingPolicy
{
    public function update(User $user, AgencyBookingSetting $setting): bool
    {
        return true;
    }

    public function testConnection(User $user, AgencyBookingSetting $setting): bool
    {
        return true;
    }
}
