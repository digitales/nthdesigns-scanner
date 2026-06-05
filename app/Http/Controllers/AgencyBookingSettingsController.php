<?php

namespace App\Http\Controllers;

use App\Services\AgencyBookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AgencyBookingSettingsController extends Controller
{
    public function update(Request $request, AgencyBookingService $agencyBooking): RedirectResponse
    {
        $settings = $agencyBooking->settings();

        $validated = $request->validate([
            'enabled' => 'sometimes|boolean',
            'fastmail_username' => 'nullable|email|max:255',
            'fastmail_app_password' => 'nullable|string|max:255',
            'caldav_calendar_url' => 'nullable|url|max:500',
            'timezone' => 'nullable|string|max:64',
            'event_duration_minutes' => 'nullable|integer|in:30',
            'min_notice_hours' => 'nullable|integer|min:1|max:168',
            'buffer_minutes' => 'nullable|integer|min:0|max:60',
            'confirmation_from_email' => 'nullable|email|max:255',
            'confirmation_from_name' => 'nullable|string|max:100',
            'working_hours' => 'nullable|array',
            'working_hours.*.enabled' => 'boolean',
            'working_hours.*.start' => 'nullable|string|max:5',
            'working_hours.*.end' => 'nullable|string|max:5',
        ]);

        if (array_key_exists('fastmail_app_password', $validated) && $validated['fastmail_app_password'] === '') {
            unset($validated['fastmail_app_password']);
        }

        $settings->update($validated);

        return back()->with('success', 'Agency booking settings saved.');
    }

    public function testConnection(Request $request, AgencyBookingService $agencyBooking): RedirectResponse
    {
        $settings = $agencyBooking->settings();

        $validated = $request->validate([
            'fastmail_username' => 'required|email|max:255',
            'fastmail_app_password' => 'nullable|string|max:255',
        ]);

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
