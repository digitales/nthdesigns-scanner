<?php

namespace App\Http\Controllers;

use App\Models\ProspectReport;
use App\Support\TidyCalEmbed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PublicBookingController extends Controller
{
    public function show(Request $request): Response|RedirectResponse|HttpResponse
    {
        $bookingUrl = $this->resolveBookingUrl($request);

        if ($bookingUrl === null || $bookingUrl === '') {
            abort(404);
        }

        $embedPath = TidyCalEmbed::pathFromUrl($bookingUrl);

        if ($embedPath === null) {
            return redirect()->away($bookingUrl);
        }

        return Inertia::render('Book/Index', [
            'embedPath' => $embedPath,
        ]);
    }

    private function resolveBookingUrl(Request $request): ?string
    {
        $token = $request->query('report');

        if (is_string($token) && $token !== '') {
            $report = ProspectReport::query()->where('token', $token)->first();

            if ($report) {
                if ($report->expires_at && $report->expires_at->isPast()) {
                    abort(410, 'This report has expired.');
                }

                $data = $report->report_data ?? [];

                return $data['booking_url'] ?? config('scanner.report_booking_url');
            }
        }

        return config('scanner.report_booking_url');
    }
}
