<?php

namespace App\Http\Controllers;

use App\Models\Prospect;
use App\Services\ReportBookingService;
use Illuminate\Http\RedirectResponse;

class ProspectBookingController extends Controller
{
    public function resendConfirmation(Prospect $prospect, ReportBookingService $bookings): RedirectResponse
    {
        $this->authorize('update', $prospect);

        $report = $prospect->report;
        $booking = $report?->booking;

        if (! $booking) {
            return back()->withErrors(['booking' => 'No booking found for this prospect.']);
        }

        $bookings->queueConfirmation($booking);

        return back()->with('success', 'Confirmation email queued.');
    }
}
