<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestAgencyBookingConnectionRequest;
use App\Http\Requests\UpdateAgencyBookingSettingsRequest;
use App\Services\AgencyBookingService;
use Illuminate\Http\RedirectResponse;

class AgencyBookingSettingsController extends Controller
{
    public function update(UpdateAgencyBookingSettingsRequest $request, AgencyBookingService $agencyBooking): RedirectResponse
    {
        $settings = $agencyBooking->settings();
        $this->authorize('update', $settings);

        $validated = $request->validated();

        if (array_key_exists('fastmail_app_password', $validated) && $validated['fastmail_app_password'] === '') {
            unset($validated['fastmail_app_password']);
        }

        $settings->update($validated);

        return back()->with('success', 'Agency booking settings saved.');
    }

    public function testConnection(TestAgencyBookingConnectionRequest $request, AgencyBookingService $agencyBooking): RedirectResponse
    {
        $settings = $agencyBooking->settings();
        $this->authorize('testConnection', $settings);

        $validated = $request->validated();

        $password = $validated['fastmail_app_password'] ?? $settings->fastmail_app_password;

        if (! filled($password)) {
            return back()->withErrors(['agency_booking' => 'Enter an app password to test the connection.']);
        }

        $result = $agencyBooking->testConnection(
            $validated['fastmail_username'],
            $password,
        );

        if (! $result['ok']) {
            return back()->withErrors(['agency_booking' => $result['message']]);
        }

        $updates = ['fastmail_username' => $validated['fastmail_username']];

        if (filled($validated['fastmail_app_password'] ?? null)) {
            $updates['fastmail_app_password'] = $validated['fastmail_app_password'];
        }

        if (! $settings->caldav_calendar_url && ! empty($result['calendars'][0]['url'])) {
            $updates['caldav_calendar_url'] = $result['calendars'][0]['url'];
        }

        $settings->update($updates);

        return back()->with([
            'success' => $result['message'],
            'agency_booking_calendars' => $result['calendars'] ?? [],
        ]);
    }
}
