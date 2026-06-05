<?php

namespace App\Http\Controllers;

use App\Http\Requests\BootstrapNichesFromSettingsRequest;
use App\Http\Requests\ScanNichesFromSettingsRequest;
use App\Http\Requests\UpdateUserSettingsRequest;
use App\Models\NicheScan;
use App\Services\AgencyBookingService;
use App\Services\ApiHealthService;
use App\Services\UserSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request, ApiHealthService $health, UserSettingsService $settings, AgencyBookingService $agencyBooking): Response
    {
        $setting = $settings->forUser($request->user());
        $bookingSettings = $agencyBooking->settings();
        $lastScan = NicheScan::query()->orderByDesc('ran_at')->first()?->ran_at;

        return Inertia::render('Settings/Index', [
            'settings' => [
                'default_country' => $setting->default_country,
                'agency_name' => $setting->agency_name ?? '',
                'booking_url' => $setting->booking_url ?? '',
            ],
            'agencyBooking' => [
                'enabled' => $bookingSettings->enabled,
                'fastmail_username' => $bookingSettings->fastmail_username ?? '',
                'has_app_password' => filled($bookingSettings->fastmail_app_password),
                'caldav_calendar_url' => $bookingSettings->caldav_calendar_url ?? '',
                'timezone' => $bookingSettings->timezone,
                'event_duration_minutes' => $bookingSettings->event_duration_minutes,
                'min_notice_hours' => $bookingSettings->min_notice_hours,
                'buffer_minutes' => $bookingSettings->buffer_minutes,
                'confirmation_from_email' => $bookingSettings->confirmation_from_email ?? '',
                'confirmation_from_name' => $bookingSettings->confirmation_from_name ?? '',
                'working_hours' => $bookingSettings->workingHoursSchedule(),
                'native_active' => $bookingSettings->nativeBookingActive(),
            ],
            'nicheMaintenance' => [
                'niche_count' => count(config('niches.niches', [])),
                'city_count' => count(config('niches.cities', [])),
                'last_scan_at' => $lastScan?->toISOString(),
                'last_scan_human' => $lastScan ? $lastScan->diffForHumans() : 'Never',
                'config_generated' => self::parseNichesConfigDate(),
            ],
            'health' => $health->checkAll(),
            'env' => [
                'reports_disk' => config('scanner.reports_disk', 'public'),
                'audit_driver' => config('scanner.audit_driver'),
                'screenshot_driver' => config('scanner.screenshot_driver'),
            ],
        ]);
    }

    public function update(UpdateUserSettingsRequest $request, UserSettingsService $settings): RedirectResponse
    {
        $validated = $request->validated();

        $setting = $settings->forUser($request->user());
        $setting->update($validated);

        return back()->with('success', 'Settings saved.');
    }

    public function scanNiches(ScanNichesFromSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $rateKey = 'settings-scan-niches:'.$user->id;

        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return back()->withErrors([
                'niche_scan' => "Please wait {$seconds} seconds before queueing another market scan.",
            ]);
        }

        RateLimiter::hit($rateKey, 300);

        $params = ! empty($validated['force']) ? ['--force' => true] : [];

        Artisan::queue('niches:scan', $params);

        return back()->with('success', 'Market scan queued.');
    }

    public function bootstrapNiches(BootstrapNichesFromSettingsRequest $request): RedirectResponse
    {
        $request->validated();
        $user = $request->user();
        $rateKey = 'settings-bootstrap-niches:'.$user->id;

        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return back()->withErrors([
                'niche_bootstrap' => "Please wait {$seconds} seconds before queueing another catalog refresh.",
            ]);
        }

        RateLimiter::hit($rateKey, 3600);

        Artisan::queue('niches:bootstrap', [
            '--no-interaction' => true,
            '--force' => true,
        ]);

        return back()->with('success', 'Catalog refresh queued.');
    }

    private static function parseNichesConfigDate(): ?string
    {
        $path = config_path('niches.php');

        if (! is_readable($path)) {
            return null;
        }

        $header = file_get_contents($path, false, null, 0, 512);

        if ($header === false) {
            return null;
        }

        if (preg_match('/Generated by niches:bootstrap on (\d{4}-\d{2}-\d{2})/', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
