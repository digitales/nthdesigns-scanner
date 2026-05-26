<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSetting;

class UserSettingsService
{
    public function forUser(User $user): UserSetting
    {
        return $user->setting ?? $user->setting()->create([
            'default_country' => 'GB',
        ]);
    }

    public function bookingUrl(User $user): ?string
    {
        $setting = $user->setting;

        return $setting?->booking_url ?: config('scanner.report_booking_url');
    }

    public function agencyName(User $user): ?string
    {
        return $user->setting?->agency_name;
    }

    public function defaultCountry(User $user): string
    {
        return $user->setting?->default_country ?? 'GB';
    }
}
