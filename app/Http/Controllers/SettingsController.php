<?php

namespace App\Http\Controllers;

use App\Services\ApiHealthService;
use App\Services\UserSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request, ApiHealthService $health, UserSettingsService $settings): Response
    {
        $setting = $settings->forUser($request->user());

        return Inertia::render('Settings/Index', [
            'settings' => [
                'default_country' => $setting->default_country,
                'agency_name'     => $setting->agency_name ?? '',
                'booking_url'     => $setting->booking_url ?? '',
            ],
            'health' => $health->checkAll(),
            'env'    => [
                'reports_disk'      => config('scanner.reports_disk', 'public'),
                'audit_driver'      => config('scanner.audit_driver'),
                'screenshot_driver' => config('scanner.screenshot_driver'),
            ],
        ]);
    }

    public function update(Request $request, UserSettingsService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'default_country' => 'required|string|size:2',
            'agency_name'     => 'nullable|string|max:100',
            'booking_url'     => 'nullable|url|max:255',
        ]);

        $setting = $settings->forUser($request->user());
        $setting->update($validated);

        return back()->with('success', 'Settings saved.');
    }
}
